<?php
/**
 * AI Chatbot — Full intent engine + Gemini polish
 * Works 100% from DB even when Gemini quota is exhausted
 */

// Suppress warnings from polluting JSON output
error_reporting(E_ERROR | E_PARSE);
ini_set('display_errors', '0');

// ── Global error handler — always return JSON, never HTML ──
set_exception_handler(function($e) {
    if (!headers_sent()) header('Content-Type: application/json');
    echo json_encode(['response' => 'Something went wrong. Please try again.', 'quick_replies' => ['Show me products', 'Contact support']]);
    exit;
});
set_error_handler(function($errno, $errstr) {
    if ($errno === E_ERROR || $errno === E_PARSE) {
        if (!headers_sent()) header('Content-Type: application/json');
        echo json_encode(['response' => 'Something went wrong. Please try again.', 'quick_replies' => []]);
        exit;
    }
    return false;
});

session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db.php';

$input   = json_decode(file_get_contents('php://input'), true);
$message = trim($input['message'] ?? '');
$user_id = $_SESSION['user_id'] ?? null;

// ── History endpoint: POST /api/chatbot.php?action=history ──
if (($_GET['action'] ?? '') === 'history') {
    $clientSid = preg_replace('/[^a-f0-9]/i', '', $input['session_id'] ?? '');
    $uid = $_SESSION['user_id'] ?? null;

    $history = [];

    // For logged-in users: load by user_id (most reliable)
    if ($uid) {
        $uid = (int)$uid;
        $res = $conn->query("SELECT message, response, created_at FROM chatbot_logs WHERE user_id=$uid ORDER BY created_at ASC LIMIT 40");
        if ($res) while ($r = $res->fetch_assoc()) $history[] = $r;
    }
    // For guests: load by localStorage session_id
    elseif (strlen($clientSid) === 32) {
        $sid = $conn->real_escape_string($clientSid);
        $res = $conn->query("SELECT message, response, created_at FROM chatbot_logs WHERE session_id='$sid' ORDER BY created_at ASC LIMIT 40");
        if ($res) while ($r = $res->fetch_assoc()) $history[] = $r;
    }

    echo json_encode(['history' => $history]);
    exit;
}

if (empty($message)) {
    echo json_encode(['response' => 'Please type a message.', 'quick_replies' => []]);
    exit;
}

try {
    if ($user_id) {
        $chk = $conn->query("SELECT id FROM users WHERE id=" . (int)$user_id . " LIMIT 1");
        if (!$chk || $chk->num_rows === 0) { $user_id = null; $_SESSION['user_id'] = null; }
    }

    // ── Accept persistent session_id from client (localStorage) ──
    // This ties the PHP session to the browser's localStorage session_id
    $clientSid = preg_replace('/[^a-f0-9]/i', '', $input['session_id'] ?? '');
    if (strlen($clientSid) === 32) {
        $_SESSION['chat_session_id'] = $clientSid;
    }
    if (empty($_SESSION['chat_session_id'])) {
        $_SESSION['chat_session_id'] = bin2hex(random_bytes(16));
    }
    $session_id = $_SESSION['chat_session_id'];

    // ── Reset chat_ctx if session_id changed (new browser session) ──
    if (isset($_SESSION['chat_ctx_sid']) && $_SESSION['chat_ctx_sid'] !== $session_id) {
        $_SESSION['chat_ctx'] = ['awaiting' => null, 'last_products' => [], 'order_cart' => [], 'order_step' => null, 'order_data' => []];
    }
    $_SESSION['chat_ctx_sid'] = $session_id;

    if (!isset($_SESSION['chat_ctx'])) {
        $_SESSION['chat_ctx'] = ['awaiting' => null, 'last_products' => [], 'order_cart' => [], 'order_step' => null, 'order_data' => []];
    }
    $ctx = &$_SESSION['chat_ctx'];

    $result   = processMessage($message, $user_id, $conn, $ctx, $session_id);
    $response = $result['response'];
    $qr       = $result['quick_replies'] ?? [];

    $sm  = $conn->real_escape_string($message);
    $sr  = $conn->real_escape_string($response);
    $ui  = $user_id ? (int)$user_id : 'NULL';
    $sid = $conn->real_escape_string($session_id);
    $guest = $user_id ? 0 : 1;
    $saved = $conn->query("INSERT INTO chatbot_logs (user_id, session_id, is_guest, message, response) VALUES ($ui, '$sid', $guest, '$sm', '$sr')");
    if (!$saved) {
        error_log("chatbot_logs INSERT failed: " . $conn->error . " | uid=$ui sid=$sid");
    }

    echo json_encode(['response' => $response, 'quick_replies' => $qr, 'session_id' => $session_id]);
} catch (Throwable $e) {
    echo json_encode(['response' => 'Something went wrong. Please try again.', 'quick_replies' => ['Show me products', 'Contact support']]);
}
exit;

// ================================================================
function reply(string $text, array $qr = []): array {
    return ['response' => $text, 'quick_replies' => $qr];
}

// ================================================================
// STOP WORDS + KEYWORD EXTRACTOR
// ================================================================
function extractKeywords(string $msg): array {
    $stop = ['do','you','have','sell','looking','for','find','search','available','is','the','a','an','any',
             'i','want','need','show','me','got','price','cost','how','much','stock','in','of','what','about',
             'recommend','suggest','best','popular','check','please','can','get','are','there','some','give',
             'tell','know','list','all','my','your','our','their','this','that','these','those','and','or',
             'under','above','below','good','nice','cheap','expensive','which','with','without','buy','purchase',
             'like','give','find','200k','100k','50k','300k','500k','150k','400k','600k','700k','800k','900k',
             '1m','rwf','between','less','than','more','minimum','maximum','cheapest','most','least','affordable',
             'budget','range','also','just','only','very','really','please','sir','madam','hello','hi','hey'];
    $words = array_filter(
        explode(' ', preg_replace('/[^a-z0-9\s]/i', '', strtolower(trim($msg)))),
        fn($w) => strlen($w) >= 3 && !in_array($w, $stop)
    );
    return array_values($words);
}

// ================================================================
// PRICE RANGE EXTRACTOR
// ================================================================
function extractPriceRange(string $ml): array {
    $min = null; $max = null;
    // between X and Y
    if (preg_match('/between\s*(?:rwf\s*)?(\d+)\s*k?\s*(?:and|to|-)\s*(?:rwf\s*)?(\d+)\s*(k|m)?/i', $ml, $m)) {
        $min = (int)$m[1] * (stripos($m[0],'k')!==false || (int)$m[1]<=999 ? 1000 : 1);
        $max = (int)$m[2] * (!empty($m[3]) && strtolower($m[3])==='m' ? 1000000 : (stripos($m[0],'k')!==false || (int)$m[2]<=999 ? 1000 : 1));
    }
    // under / below / less than / max
    if (!$max && preg_match('/(?:under|below|less than|cheaper than|maximum|max|at most)\s*(?:rwf\s*)?(\d+)\s*(k|m)?/i', $ml, $m)) {
        $n = (int)$m[1]; $max = $n * (!empty($m[2]) && strtolower($m[2])==='m' ? 1000000 : (!empty($m[2]) || $n <= 999 ? 1000 : 1));
    }
    // above / over / more than / min
    if (!$min && preg_match('/(?:above|over|more than|minimum|min|at least)\s*(?:rwf\s*)?(\d+)\s*(k|m)?/i', $ml, $m)) {
        $n = (int)$m[1]; $min = $n * (!empty($m[2]) && strtolower($m[2])==='m' ? 1000000 : (!empty($m[2]) || $n <= 999 ? 1000 : 1));
    }
    return [$min, $max];
}

// ================================================================
// CATEGORY DETECTOR
// ================================================================
function detectCategory(string $ml): ?int {
    $map = [
        1  => 'phone|phones|mobile|smartphone|smartphones|iphone|samsung|tecno|infinix|xiaomi|oppo|vivo|nokia|redmi|tablet|android',
        2  => 'laptop|laptops|computer|computers|pc|macbook|dell|hp|lenovo|acer|asus|notebook|chromebook',
        3  => 'tv|television|televisions|speaker|speakers|headphone|headphones|audio|sound|earphone|earphones|subwoofer|home theater|soundbar',
        4  => 'fridge|fridges|washing machine|microwave|appliance|appliances|cooker|kettle|blender|iron|vacuum|oven|dishwasher',
        5  => 'men shirt|men trouser|men suit|men shoe|men fashion|men cloth|men wear|men jacket|men clothing|menswear',
        6  => 'women dress|handbag|handbags|heels|ladies|women fashion|women cloth|skirt|blouse|women shoe|women clothing|womenswear|fashion|clothing|clothes|dress',
        7  => 'food|grocery|groceries|rice|milk|coffee|tea|sugar|flour|cooking oil|cereal|juice|snack|snacks',
        8  => 'beauty|skincare|lotion|shampoo|perfume|cream|makeup|deodorant|hair|cosmetic|moisturizer|cosmetics',
        9  => 'sport|sports|gym|fitness|football|running|yoga|exercise|dumbbell|treadmill|bicycle|jersey',
        10 => 'baby|kids|child|children|toy|toys|diaper|stroller|crib|nursery|infant|toddler',
        11 => 'furniture|sofa|bed|table|chair|wardrobe|shelf|decor|lamp|mirror|ottoman|mattress|curtain',
        12 => 'car|cars|vehicle|vehicles|tyre|tyres|auto|driving|motor|spare part|car accessory|car accessories',
        13 => 'book|books|pen|pens|notebook|stationery|school|pencil|ruler|eraser|calculator',
        14 => 'watch|watches|jewelry|jewellery|ring|necklace|bracelet|earring|gold|silver|pendant|accessories',
        15 => 'game|games|gaming|playstation|xbox|console|controller|nintendo|ps4|ps5',
    ];
    foreach ($map as $id => $pattern) {
        if (preg_match("/\b($pattern)\b/i", $ml)) return $id;
    }
    return null;
}

// ================================================================
// SMART PRODUCT SEARCH — category + price + keywords
// ================================================================
function dbProductSearch(string $msg, $conn, ?int $forceCatId = null): array {
    $ml = strtolower($msg);
    [$minPrice, $maxPrice] = extractPriceRange($ml);
    $catId    = $forceCatId ?? detectCategory($ml);
    $keywords = $msg !== '' ? extractKeywords($msg) : [];

    $conditions = ['p.stock > 0'];
    if ($catId)    $conditions[] = "p.category_id = $catId";
    if ($maxPrice) $conditions[] = "p.price <= $maxPrice";
    if ($minPrice) $conditions[] = "p.price >= $minPrice";

    $kwConds = [];
    foreach (array_slice($keywords, 0, 4) as $w) {
        $e = $conn->real_escape_string($w);
        $kwConds[] = "(p.name LIKE '%$e%' OR p.brand LIKE '%$e%' OR p.description LIKE '%$e%')";
    }
    if ($kwConds) $conditions[] = '(' . implode(' OR ', $kwConds) . ')';

    $where   = 'WHERE ' . implode(' AND ', $conditions);
    $firstKw = $conn->real_escape_string($keywords[0] ?? '');
    $order   = $firstKw ? "CASE WHEN p.name LIKE '%$firstKw%' THEN 0 ELSE 1 END, p.price ASC" : "p.price ASC";

    $res = $conn->query("SELECT p.id,p.name,p.brand,p.price,p.stock,p.description,c.name AS cat
        FROM products p LEFT JOIN categories c ON p.category_id=c.id $where ORDER BY $order LIMIT 8");

    $rows = [];
    if ($res) while ($r = $res->fetch_assoc()) $rows[] = $r;

    // Relax: drop keyword conditions if no results but category/price matched
    if (empty($rows) && $kwConds && ($catId || $maxPrice || $minPrice)) {
        $conds2 = ['p.stock > 0'];
        if ($catId)    $conds2[] = "p.category_id = $catId";
        if ($maxPrice) $conds2[] = "p.price <= $maxPrice";
        if ($minPrice) $conds2[] = "p.price >= $minPrice";
        $res2 = $conn->query("SELECT p.id,p.name,p.brand,p.price,p.stock,p.description,c.name AS cat
            FROM products p LEFT JOIN categories c ON p.category_id=c.id
            WHERE " . implode(' AND ', $conds2) . " ORDER BY p.price ASC LIMIT 8");
        if ($res2) while ($r = $res2->fetch_assoc()) $rows[] = $r;
    }
    return $rows;
}

function formatProducts(array $rows, string $label = ''): array {
    if (empty($rows)) return ['text' => '', 'qr' => []];
    $out = $label ? "🛍️ <strong>$label</strong><br>" : "🛍️ <strong>Here's what we have:</strong><br>";
    $qr  = [];
    foreach ($rows as $p) {
        $out .= "• <a href='" . SITE_URL . "/product.php?id={$p['id']}'>" . htmlspecialchars($p['name']) . "</a>"
              . ($p['brand'] ? " <em>({$p['brand']})</em>" : '')
              . " — <strong>RWF " . number_format($p['price']) . "</strong>"
              . " ({$p['stock']} in stock)<br>";
        $qr[] = "🛒 Add: add_to_cart:{$p['id']}";
    }
    $out .= "<a href='" . SITE_URL . "/products.php'>Browse all products →</a>";
    return ['text' => $out, 'qr' => array_slice($qr, 0, 4)];
}

// ================================================================
// MAIN PROCESSOR — every intent handled natively from DB
// ================================================================
function processMessage(string $msg, ?int $uid, $conn, array &$ctx, string $session_id): array {
    $ml = strtolower(trim($msg));

    // ── Awaiting order number from previous turn ──
    if ($ctx['awaiting'] === 'order_number' && preg_match('/#?0*(\d+)\b/', $msg, $m)) {
        $ctx['awaiting'] = null;
        return reply(trackOrder((int)$m[1], $uid, $conn), ['View all orders', 'Cancel an order']);
    }

    // ── GEMINI FIRST — use AI for every message when internet is available ──
    // Only skip for multi-step flows that need exact PHP state handling
    $inMultiStep = !empty($ctx['awaiting']) || !empty($ctx['order_step']);
    $isCartCmd   = preg_match('/^(add_to_cart:\d+$|confirm$|cancel$)/i', trim($msg));

    // Block Gemini from intercepting any order/cart/checkout intent — PHP handles these
    $isOrderIntent = preg_match(
        '/\b(add to cart|add_to_cart|place order|checkout|proceed to checkout|buy now|order now|' .
        'i want to buy|i want to order|confirm order|my cart|view cart|clear cart|' .
        'track.*order|cancel.*order|order.*cancel|order.*track|my order|order history|' .
        'order summary|order detail|order status|where is my order|payment method|' .
        'delivery address|finalize.*order|complete.*order|help.*place.*order|' .
        'place.*order|order.*place)\b/i',
        $ml
    );

    if (!$inMultiStep && !$isCartCmd && !$isOrderIntent) {
        $gemini = askGemini($msg, $uid, $conn, $session_id);
        if ($gemini) {
            return reply($gemini, ['Show me products', 'Track my order', 'Delivery info', 'Contact support']);
        }
    }

    // ── Awaiting support message — handle BEFORE anything else ──
    if ($ctx['awaiting'] === 'support_message') {
        $ctx['awaiting'] = null;
        $supportMsg = trim($msg);
        if (strlen($supportMsg) < 3) {
            return reply("Please type your message so we can help you.");
        }
        $customerName  = 'Guest';
        $customerEmail = '';
        if ($uid) {
            $u = $conn->query("SELECT name, email FROM users WHERE id=$uid")->fetch_assoc();
            if ($u) { $customerName = $u['name']; $customerEmail = $u['email']; }
        }
        require_once __DIR__ . '/../includes/mailer.php';
        $adminSent = sendMail(ADMIN_EMAIL, ADMIN_NAME,
            "📩 Support Request from $customerName",
            emailSupportMessage($customerName, $customerEmail ?: 'Not logged in', $supportMsg)
        );
        if ($uid && $customerEmail) {
            sendMail($customerEmail, $customerName,
                "✅ We received your message — " . SITE_NAME,
                emailSupportAutoReply($customerName)
            );
        }
        $confirm = $adminSent
            ? "✅ <strong>Your message has been sent!</strong> Our team will reply to <strong>" . htmlspecialchars($customerEmail ?: 'you') . "</strong> within 24 hours."
            : "⚠️ Message saved. You can also reach us directly at <a href='mailto:" . ADMIN_EMAIL . "'>" . ADMIN_EMAIL . "</a>.";
        return reply(
            $confirm . "<br><br>📱 For urgent issues: <a href='tel:" . ADMIN_PHONE . "'>" . ADMIN_PHONE . "</a>",
            ['Track my order', 'Return policy', 'Show me products']
        );
    }
    // ================================================================
    // CHATBOT ORDER FLOW — full multi-step cart + checkout via chat
    // Every step saves to DB on confirm. Gemini never touches this flow.
    // ================================================================

    // ── STEP: awaiting quantity ──
    if ($ctx['order_step'] === 'qty' && isset($ctx['order_pending_product'])) {
        $qty = (int)preg_replace('/[^0-9]/', '', $msg);
        if ($qty < 1) $qty = 1;
        $p = $ctx['order_pending_product'];
        if ($qty > (int)$p['stock']) {
            return reply("⚠️ Only <strong>{$p['stock']}</strong> units available. How many would you like? (max {$p['stock']})",
                ['1','2','3']);
        }
        // Merge into cart
        $found = false;
        foreach ($ctx['order_cart'] as &$item) {
            if ($item['id'] == $p['id']) { $item['qty'] += $qty; $found = true; break; }
        }
        unset($item);
        if (!$found) {
            $ctx['order_cart'][] = ['id'=>(int)$p['id'],'name'=>$p['name'],'price'=>(float)$p['price'],'qty'=>$qty,'stock'=>(int)$p['stock']];
        }
        $ctx['order_step'] = null;
        unset($ctx['order_pending_product']);
        $cartSummary = chatCartSummary($ctx['order_cart']);
        return reply(
            "✅ Added <strong>{$qty}x " . htmlspecialchars($p['name']) . "</strong> to your cart.<br><br>$cartSummary",
            ['Add more products', 'Proceed to checkout', 'Clear cart']
        );
    }

    // ── STEP: awaiting delivery address ──
    if ($ctx['order_step'] === 'address') {
        if (preg_match('/\bcancel\b/i', $ml)) {
            $ctx['order_cart'] = []; $ctx['order_step'] = null; $ctx['order_data'] = [];
            return reply("❌ Order cancelled. Cart cleared.", ['Show me products']);
        }
        $address = trim($msg);
        if (strlen($address) < 5) {
            return reply("📍 Please enter a valid delivery address (e.g. KG 15 Ave, Kigali, Gasabo District).");
        }
        $ctx['order_data']['address'] = $address;
        $ctx['order_step'] = 'payment';
        return reply(
            "📍 Delivery to: <strong>" . htmlspecialchars($address) . "</strong><br><br>" .
            "💳 <strong>Choose your payment method:</strong><br>" .
            "1️⃣ Cash on Delivery (COD)<br>" .
            "2️⃣ MTN Mobile Money (MoMo)<br>" .
            "3️⃣ Airtel Money<br>" .
            "4️⃣ Visa / Mastercard<br>" .
            "5️⃣ Bank Transfer<br><br>" .
            "Type the number or name of your preferred payment method.",
            ['1', '2', '3', '4', '5']
        );
    }

    // ── STEP: awaiting payment method ──
    if ($ctx['order_step'] === 'payment') {
        if (preg_match('/\bcancel\b/i', $ml)) {
            $ctx['order_cart'] = []; $ctx['order_step'] = null; $ctx['order_data'] = [];
            return reply("❌ Order cancelled. Cart cleared.", ['Show me products']);
        }
        $payMap = [
            '1'=>'cod','cod'=>'cod','cash'=>'cod','cash on delivery'=>'cod',
            '2'=>'momo','momo'=>'momo','mtn'=>'momo','mtn momo'=>'momo','mobile money'=>'momo',
            '3'=>'airtel','airtel'=>'airtel','airtel money'=>'airtel',
            '4'=>'card','visa'=>'card','mastercard'=>'card','card'=>'card',
            '5'=>'bank','bank'=>'bank','bank transfer'=>'bank',
        ];
        $payment = $payMap[strtolower(trim($msg))] ?? null;
        if (!$payment) {
            return reply("Please choose a valid payment method — type 1, 2, 3, 4, or 5.",
                ['1', '2', '3', '4', '5']);
        }
        $ctx['order_data']['payment'] = $payment;
        $ctx['order_step'] = 'confirm';
        $cartSummary = chatCartSummary($ctx['order_cart']);
        $total = array_sum(array_map(fn($i) => $i['price'] * $i['qty'], $ctx['order_cart']));
        $payLabels = ['cod'=>'Cash on Delivery','momo'=>'MTN Mobile Money','airtel'=>'Airtel Money','card'=>'Visa/Mastercard','bank'=>'Bank Transfer'];
        return reply(
            "📋 <strong>Order Summary — Please Confirm:</strong><br><br>" .
            $cartSummary . "<br><br>" .
            "📍 <strong>Delivery:</strong> " . htmlspecialchars($ctx['order_data']['address']) . "<br>" .
            "💳 <strong>Payment:</strong> " . ($payLabels[$payment] ?? $payment) . "<br>" .
            "💰 <strong>Total: RWF " . number_format($total) . "</strong><br><br>" .
            "✅ Type <strong>confirm</strong> to place your order<br>" .
            "❌ Type <strong>cancel</strong> to start over.",
            ['confirm', 'cancel']
        );
    }

    // ── STEP: awaiting final confirmation — THIS IS WHERE THE DB INSERT HAPPENS ──
    if ($ctx['order_step'] === 'confirm') {
        if (preg_match('/\bcancel\b/i', $ml)) {
            $ctx['order_cart'] = []; $ctx['order_step'] = null; $ctx['order_data'] = [];
            return reply("❌ Order cancelled. Your cart has been cleared.", ['Show me products', 'Track my order']);
        }
        if (preg_match('/\bconfirm\b/i', $ml)) {
            if (!$uid) {
                // Save cart state so it survives login redirect
                return reply(
                    "🔒 You need to be logged in to place an order.<br>" .
                    "<a href='" . SITE_URL . "/login.php'><strong>Login here →</strong></a> then come back to complete your order.",
                    ['Login', 'Register']
                );
            }
            // ── PLACE THE ORDER — saves to DB ──
            return placeChatOrder($uid, $ctx, $conn);
        }
        return reply(
            "Type <strong>confirm</strong> to place your order or <strong>cancel</strong> to start over.",
            ['confirm', 'cancel']
        );
    }

    // ── TRIGGER: add_to_cart:ID (from quick reply buttons) ──
    if (preg_match('/^add_to_cart:(\d+)$/i', $msg, $m)) {
        if (!$uid) {
            return reply(
                "🔒 Please <a href='" . SITE_URL . "/login.php'><strong>login</strong></a> first to add items to your cart and place an order.",
                ['Login', 'Register']
            );
        }
        $pid = (int)$m[1];
        $p = $conn->query("SELECT id,name,price,stock FROM products WHERE id=$pid AND stock>0 LIMIT 1")->fetch_assoc();
        if (!$p) return reply("❌ Product not found or out of stock. Please try another product.", ['Show me products']);
        $ctx['order_pending_product'] = $p;
        $ctx['order_step'] = 'qty';
        return reply(
            "🛒 <strong>" . htmlspecialchars($p['name']) . "</strong><br>" .
            "Price: <strong>RWF " . number_format($p['price']) . "</strong> | Stock: {$p['stock']} units<br><br>" .
            "How many would you like to order?",
            ['1', '2', '3', '5']
        );
    }

    // ── TRIGGER: view cart ──
    if (preg_match('/\b(my cart|view cart|show cart|what.*in.*cart|cart items|show my cart)\b/i', $ml)) {
        if (empty($ctx['order_cart'])) {
            return reply("🛒 Your cart is empty. Tell me what you're looking for!", ['Show me products']);
        }
        $cartSummary = chatCartSummary($ctx['order_cart']);
        return reply("🛒 <strong>Your Cart:</strong><br><br>$cartSummary", ['Proceed to checkout', 'Clear cart', 'Add more products']);
    }

    // ── TRIGGER: clear cart ──
    if (preg_match('/\b(clear cart|empty cart|remove all|start over)\b/i', $ml)) {
        $ctx['order_cart'] = []; $ctx['order_step'] = null; $ctx['order_data'] = [];
        return reply("🗑️ Cart cleared. What would you like to shop for?", ['Show me products']);
    }

    // ── TRIGGER: proceed to checkout ──
    if (preg_match('/\b(proceed to checkout|checkout|place order|buy now|order now|i want to buy|i want to order|finalize order|complete order)\b/i', $ml)) {
        if (!$uid) {
            return reply(
                "🔒 Please <a href='" . SITE_URL . "/login.php'><strong>login</strong></a> first to place an order.",
                ['Login', 'Register']
            );
        }
        if (empty($ctx['order_cart'])) {
            return reply("🛒 Your cart is empty. Tell me what product you'd like to buy!", ['Show me products']);
        }
        $ctx['order_step'] = 'address';
        $cartSummary = chatCartSummary($ctx['order_cart']);
        return reply(
            "🛒 <strong>Your Cart:</strong><br>" . $cartSummary . "<br><br>" .
            "📍 <strong>Step 1 of 3 — Delivery Address</strong><br>" .
            "Please type your full delivery address:<br>" .
            "<em>Example: KG 15 Ave, Kigali, Gasabo District</em>",
            ['Cancel order']
        );
    }

    // ── 1. GREETING ──
    if (preg_match('/\b(hi|hello|hey|good morning|good afternoon|good evening|bonjour|salut|muraho|mwaramutse|mwiriwe|howdy|hie|sup|yo)\b/i', $ml)) {
        if ($uid) {
            // ── Registered customer ──
            $u      = $conn->query("SELECT name FROM users WHERE id=$uid LIMIT 1")->fetch_assoc();
            $name   = $u ? explode(' ', trim($u['name']))[0] : 'there';
            $oCount = $conn->query("SELECT COUNT(*) as c FROM orders WHERE user_id=$uid")->fetch_assoc()['c'];
            $latest = $conn->query("SELECT id, status FROM orders WHERE user_id=$uid ORDER BY created_at DESC LIMIT 1")->fetch_assoc();
            $orderLine = '';
            if ($latest) {
                $emoji = ['pending'=>'⏳','processing'=>'⚙️','shipped'=>'🚚','delivered'=>'✅','cancelled'=>'❌'][$latest['status']] ?? '📦';
                $orderLine = "<br>📦 Your latest order <strong>#" . $latest['id'] . "</strong> is $emoji <strong>" . ucfirst($latest['status']) . "</strong>.";
            }
            return reply(
                "👋 Welcome back, <strong>$name</strong>! Great to see you at <strong>" . SITE_NAME . "</strong>.<br>" .
                "You have <strong>$oCount order" . ($oCount != 1 ? 's' : '') . "</strong> with us." . $orderLine . "<br><br>" .
                "How can I help you today?",
                ['Show me products', 'Track my order', 'My orders', 'Contact support']
            );
        } else {
            // ── Guest ──
            return reply(
                "👋 Hello! Welcome to <strong>" . SITE_NAME . "</strong>.<br><br>" .
                "I'm your AI shopping assistant. I can help you:<br>" .
                "• 🛍️ Browse & find products<br>" .
                "• 💰 Check prices<br>" .
                "• 🚚 Delivery & payment info<br>" .
                "• ↩️ Return policy<br><br>" .
                "💡 <strong>Tip:</strong> <a href='" . SITE_URL . "/register.php'><strong>Create a free account</strong></a> to place orders, track deliveries, and get order updates!",
                ['Show me products', 'Register free', 'Login', 'Delivery info']
            );
        }
    }

    // ── 2. GOODBYE / THANKS ──
    if (preg_match('/\b(bye|goodbye|see you|take care|later|thank you|thanks|merci|murakoze|au revoir|ciao)\b/i', $ml)) {
        $closing = $uid
            ? "😊 Thank you, <strong>" . getFirstName($uid, $conn) . "</strong>! Have a great day. Come back anytime! 🌟"
            : "😊 Thank you for visiting <strong>" . SITE_NAME . "</strong>! <a href='" . SITE_URL . "/register.php'>Create an account</a> to enjoy full shopping features. Have a great day! 🌟";
        return reply($closing, ['Browse products', 'Contact support']);
    }

    // ── 3. SMALL TALK ──
    if (preg_match('/how are you|how r u|how do you do/i', $ml)) {
        return reply("😊 I'm doing great, thanks for asking! Always ready to help you shop. What can I find for you today?",
            ['Show me products', 'Track my order']);
    }
    if (preg_match('/who are you|what are you|your name|are you (a bot|human|real|ai)/i', $ml)) {
        return reply("🤖 I'm the AI shopping assistant for <strong>" . SITE_NAME . "</strong>!<br>I can find products, check prices, track orders, and answer any question about our store — in English, French, or Kinyarwanda.",
            ['Show me products', 'What can you do?']);
    }
    if (preg_match('/what can you do|how can you help|help me/i', $ml)) {
        if ($uid) {
            $name = getFirstName($uid, $conn);
            return reply(
                "Here's what I can do for you, <strong>$name</strong>:<br>" .
                "• 🛍️ <em>Show me phones under 200k</em><br>" .
                "• 💰 <em>Price of Samsung Galaxy A54</em><br>" .
                "• 📦 <em>Track order 5</em> or <em>#000005</em><br>" .
                "• ❌ <em>Cancel order 3</em><br>" .
                "• 🛒 <em>I want Nokia G21</em> — place order via chat<br>" .
                "• 🚚 <em>Delivery time to Kigali</em><br>" .
                "• 💳 <em>Payment methods</em><br>" .
                "• ↩️ <em>Return policy</em><br>" .
                "Just type naturally — I understand English, French & Kinyarwanda!",
                ['Show me products', 'Track my order', 'My orders', 'Delivery info']
            );
        } else {
            return reply(
                "Here's what I can help you with:<br>" .
                "• 🛍️ <em>Show me phones under 200k</em><br>" .
                "• 💰 <em>Price of Samsung Galaxy A54</em><br>" .
                "• 🚚 <em>Delivery time to Kigali</em><br>" .
                "• 💳 <em>Payment methods</em><br>" .
                "• ↩️ <em>Return policy</em><br><br>" .
                "🔒 To place orders & track deliveries, <a href='" . SITE_URL . "/register.php'><strong>create a free account</strong></a> or <a href='" . SITE_URL . "/login.php'><strong>login</strong></a>.",
                ['Show me products', 'Register free', 'Login', 'Delivery info']
            );
        }
    }

    // ── 4. ORDER TRACKING ──
    if (preg_match('/\b(track|tracking|order status|where is my order|check order|order #|order no|my order)\b/i', $ml)
        || preg_match('/^#\d+$/', trim($ml))
        || $ctx['awaiting'] === 'order_number') {
        if (!$uid) return reply("🔒 Please <a href='" . SITE_URL . "/login.php'>login</a> first to track your orders.",
            ['Login', 'Register']);
        if (preg_match('/#?0*(\d+)\b/', $msg, $m)) {
            return reply(trackOrder((int)$m[1], $uid, $conn), ['View all orders', 'Cancel an order']);
        }
        $latest = $conn->query("SELECT id,status FROM orders WHERE user_id=$uid ORDER BY created_at DESC LIMIT 1")->fetch_assoc();
        if ($latest) {
            return reply("Your latest order is <strong>#" . $latest['id'] . "</strong> — Status: <strong>" . ucfirst($latest['status']) . "</strong>.<br>Type the order number for full details.",
                ['Track order ' . $latest['id'], 'View all orders']);
        }
        $ctx['awaiting'] = 'order_number';
        return reply("Please provide your order number. Example: <em>track order 5</em><br>Find it on the <a href='" . SITE_URL . "/orders.php'>My Orders</a> page.");
    }

    // ── 5. ORDER CANCEL ──
    if (preg_match('/\b(cancel order|cancel my order|i want to cancel|stop my order)\b/i', $ml)) {
        if (!$uid) return reply("🔒 Please <a href='" . SITE_URL . "/login.php'>login</a> to manage your orders.");
        if (preg_match('/\b(\d+)\b/', $msg, $m)) return reply(cancelOrder((int)$m[1], $uid, $conn));
        return reply("To cancel an order, type: <em>cancel order [number]</em><br>Find your order number on the <a href='" . SITE_URL . "/orders.php'>My Orders</a> page.", ['View my orders']);
    }

    // ── 6. ORDER HISTORY ──
    if (preg_match('/\b(my orders|order history|past orders|previous orders|all my orders|show orders|all orders)\b/i', $ml)) {
        if (!$uid) return reply("🔒 Please <a href='" . SITE_URL . "/login.php'>login</a> to view your orders.");
        return reply(orderHistory($uid, $conn), ['Track an order', 'Cancel an order']);
    }

    // ── 7. DELIVERY TIME ──
    if (preg_match('/\b(delivery time|how long|when will|shipping time|estimated delivery|dispatch|how many days|livraison|delivery day)\b/i', $ml)) {
        return reply(
            "🚚 <strong>Delivery Times (Rwanda):</strong><br>" .
            "• <strong>Kigali:</strong> 1–2 business days<br>" .
            "• <strong>Other provinces:</strong> 2–4 business days<br>" .
            "• <strong>Remote areas:</strong> up to 5–7 days<br>" .
            "You'll receive an SMS/email update once your order is shipped! 📱",
            ['Shipping fees', 'Track my order', 'Payment methods']
        );
    }

    // ── 8. DELIVERY COST / SHIPPING FEE ──
    if (preg_match('/\b(shipping fee|delivery fee|shipping cost|free delivery|free shipping|how much.*delivery|frais.*livraison)\b/i', $ml)) {
        return reply(
            "📦 <strong>Shipping Fees:</strong><br>" .
            "• Orders above <strong>RWF 50,000</strong> → <strong>FREE shipping</strong> 🎉<br>" .
            "• Orders below RWF 50,000 → <strong>RWF 2,000</strong> flat rate<br>" .
            "• Express delivery (Kigali only) → <strong>RWF 3,500</strong>",
            ['Delivery time', 'Payment methods', 'Show me products']
        );
    }

    // ── 9. PAYMENT METHODS ──
    if (preg_match('/\b(payment|how to pay|pay with|accept payment|momo|mobile money|cash on delivery|cod|bank transfer|card|visa|mastercard|airtel)\b/i', $ml)) {
        return reply(
            "💳 <strong>Payment Methods We Accept:</strong><br>" .
            "• 💵 Cash on Delivery (COD)<br>" .
            "• 📱 MTN Mobile Money (MoMo)<br>" .
            "• 📱 Airtel Money<br>" .
            "• 🏦 Bank Transfer (BK, Equity, I&M)<br>" .
            "• 💳 Visa / Mastercard<br>" .
            "All online payments are <strong>SSL secured</strong> 🔒",
            ['Delivery info', 'Return policy', 'Show me products']
        );
    }

    // ── 10. RETURN / REFUND POLICY ──
    if (preg_match('/\b(return policy|refund|how to return|can i return|exchange|send back|return item|politique.*retour)\b/i', $ml)) {
        return reply(
            "↩️ <strong>Return & Refund Policy:</strong><br>" .
            "• Items returnable within <strong>7 days</strong> of delivery<br>" .
            "• Item must be unused and in original packaging<br>" .
            "• Damaged or wrong items: full refund or free replacement<br>" .
            "• Refunds processed within <strong>3–5 business days</strong><br>" .
            "📧 Start a return: <a href='mailto:" . ADMIN_EMAIL . "'>" . ADMIN_EMAIL . "</a>",
            ['Delivery info', 'Contact support', 'Warranty info']
        );
    }

    // ── 11. WARRANTY ──
    if (preg_match('/\b(warranty|guarantee|broken after|stopped working|repair|garantie)\b/i', $ml)) {
        return reply(
            "🛡️ <strong>Warranty Information:</strong><br>" .
            "• 📱 Electronics & Phones: <strong>1 year</strong><br>" .
            "• 🏠 Home Appliances: <strong>1–2 years</strong><br>" .
            "• 👗 Clothing & Accessories: <strong>7 days</strong> defect warranty<br>" .
            "• ⌚ Watches: <strong>6 months</strong><br>" .
            "📧 Claims: <a href='mailto:" . ADMIN_EMAIL . "'>" . ADMIN_EMAIL . "</a> with order number + photos.",
            ['Return policy', 'Contact support']
        );
    }

    // ── 12. CONTACT / SUPPORT ──
    if (preg_match('/\b(contact|support|help desk|talk to agent|human agent|call|email support|phone number|customer service|whatsapp|send message|message admin|message us)\b/i', $ml)) {
        $ctx['awaiting'] = 'support_message';
        return reply(
            "📞 <strong>Contact & Support:</strong><br>" .
            "• 📧 Email: <a href='mailto:" . ADMIN_EMAIL . "'>" . ADMIN_EMAIL . "</a><br>" .
            "• 📱 Phone/WhatsApp: <a href='tel:" . ADMIN_PHONE . "'>" . ADMIN_PHONE . "</a><br>" .
            "• 🕐 Office Hours: Mon–Sat, 8AM–6PM (Kigali time)<br><br>" .
            "💬 <strong>Or type your message below and we'll email it to our team right now:</strong>",
            ['Return policy', 'Delivery info', 'Track my order']
        );
    }

    // ── 13. DISCOUNT / PROMO ──
    if (preg_match('/\b(discount|promo|sale|voucher|coupon|deal|offer|cheaper|promotion|remise)\b/i', $ml)) {
        return reply(
            "🏷️ <strong>Current Deals & Promotions:</strong><br>" .
            "• 🎉 Free shipping on orders above <strong>RWF 50,000</strong><br>" .
            "• New arrivals added weekly across all categories<br>" .
            "• 💡 Tip: Add more items to qualify for free shipping!<br>" .
            "Check our <a href='" . SITE_URL . "/products.php'>Products page</a> for latest prices.",
            ['Show me products', 'Delivery info']
        );
    }

    // ── 14. ACCOUNT HELP ──
    if (preg_match('/\b(my account|forgot password|reset password|change password|register|sign up|create account|login help|sign in|register free)\b/i', $ml)) {
        if (preg_match('/forgot|reset|change password/i', $ml))
            return reply("🔑 To reset your password, visit your <a href='" . SITE_URL . "/profile.php'>Profile page</a> or email <a href='mailto:" . ADMIN_EMAIL . "'>" . ADMIN_EMAIL . "</a>.", ['Login', 'Contact support']);
        if (preg_match('/register|sign up|create/i', $ml))
            return reply("📝 <a href='" . SITE_URL . "/register.php'><strong>Click here to create an account →</strong></a><br>You'll need your name, email, and a password.", ['Login']);
        if (preg_match('/login|sign in/i', $ml))
            return reply("🔐 <a href='" . SITE_URL . "/login.php'><strong>Click here to login →</strong></a>", ['Register', 'Forgot password']);
        return reply("👤 <strong>Account Help:</strong><br>• <a href='" . SITE_URL . "/login.php'>Login</a> | <a href='" . SITE_URL . "/register.php'>Register</a><br>• <a href='" . SITE_URL . "/profile.php'>Edit Profile</a><br>• Password issues: <a href='mailto:" . ADMIN_EMAIL . "'>" . ADMIN_EMAIL . "</a>", ['Login', 'Register']);
    }

    // ── 15. COMPLAINT ──
    if (preg_match('/\b(wrong item|damaged|broken|missing item|not received|bad quality|complaint|defective|fake|never arrived|plainte)\b/i', $ml)) {
        $r = "😔 I'm really sorry to hear that! We take all issues seriously.<br><br>";
        if (preg_match('/wrong/i', $ml))
            $r .= "📦 <strong>Wrong Item:</strong> Email <a href='mailto:" . ADMIN_EMAIL . "'>" . ADMIN_EMAIL . "</a> with your order number + photo. We'll send the correct item within 2–3 days.";
        elseif (preg_match('/damaged|broken|defective/i', $ml))
            $r .= "🔧 <strong>Damaged Item:</strong> Document with photos and contact us within 7 days for a full replacement or refund.";
        elseif (preg_match('/not received|missing|never arrived/i', $ml))
            $r .= "📭 <strong>Not Received:</strong> Type <em>track order [number]</em> to check status. If it shows delivered but you didn't receive it, contact us immediately.";
        else
            $r .= "Please contact us:<br>📧 <a href='mailto:" . ADMIN_EMAIL . "'>" . ADMIN_EMAIL . "</a> | 📱 " . ADMIN_PHONE . "<br>Include your order number for faster help.";
        return reply($r, ['Return policy', 'Contact support', 'Track my order']);
    }

    // ── 16. PRODUCT PRICE QUERY ──
    if (preg_match('/\b(price of|how much is|cost of|how much does|what is the price|price for|how much.*cost|combien)\b/i', $ml)) {
        $rows = dbProductSearch($msg, $conn);
        if (!empty($rows)) {
            $out = "💰 <strong>Prices:</strong><br>";
            foreach ($rows as $p) {
                $out .= "• <a href='" . SITE_URL . "/product.php?id={$p['id']}'>" . htmlspecialchars($p['name']) . "</a>"
                      . " — <strong>RWF " . number_format($p['price']) . "</strong><br>";
            }
            return reply($out, ['Add to cart', 'Show me more', 'Check stock']);
        }
        $range = $conn->query("SELECT MIN(price) as mn, MAX(price) as mx FROM products")->fetch_assoc();
        return reply("💰 Our prices range from <strong>RWF " . number_format($range['mn']) . "</strong> to <strong>RWF " . number_format($range['mx']) . "</strong>.<br>Tell me the product name for an exact price!", ['Show me products']);
    }

    // ── 17. STOCK CHECK ──
    if (preg_match('/\b(in stock|out of stock|is available|do you have in stock|how many left|stock of|available stock|is there)\b/i', $ml)) {
        $rows = dbProductSearch($msg, $conn);
        if (!empty($rows)) {
            $p = $rows[0];
            return reply(
                $p['stock'] > 0
                    ? "✅ <strong>" . htmlspecialchars($p['name']) . "</strong> is in stock — <strong>" . $p['stock'] . " units</strong> available.<br><a href='" . SITE_URL . "/product.php?id={$p['id']}'>View product →</a>"
                    : "❌ <strong>" . htmlspecialchars($p['name']) . "</strong> is currently out of stock. Would you like a similar product?",
                ['Show similar products', 'Browse all products']
            );
        }
        return reply("Which product would you like to check? Type the product name, e.g. <em>is Samsung A54 in stock?</em>");
    }

    // ── 18. RECOMMENDATION ──
    if (preg_match('/\b(recommend|suggest|best|popular|top rated|what should i buy|which is better|advise|good phone|good laptop|best phone|best laptop|best tv)\b/i', $ml)) {
        $catId = detectCategory($ml);
        $where = $catId ? "WHERE p.stock>0 AND p.category_id=$catId" : "WHERE p.stock>0";
        $res   = $conn->query("SELECT p.id,p.name,p.brand,p.price,c.name as cat FROM products p LEFT JOIN categories c ON p.category_id=c.id $where ORDER BY RAND() LIMIT 5");
        $rows  = [];
        if ($res) while ($r = $res->fetch_assoc()) $rows[] = $r;
        if (!empty($rows)) {
            $out = "⭐ <strong>Recommended for you:</strong><br>";
            foreach ($rows as $p) {
                $out .= "• <a href='" . SITE_URL . "/product.php?id={$p['id']}'>" . htmlspecialchars($p['name']) . "</a>"
                      . ($p['brand'] ? " <em>({$p['brand']})</em>" : '')
                      . " — RWF " . number_format($p['price']) . "<br>";
            }
            $out .= "<a href='" . SITE_URL . "/products.php'>Browse all products →</a>";
            $qr = array_map(fn($p) => "🛒 Add: add_to_cart:{$p['id']}", array_slice($rows, 0, 3));
            return reply($out, array_merge($qr, ['Delivery info']));
        }
    }

    // ── 19. PRODUCT SEARCH (show me / i want / do you have / looking for / find me) ──
    // "Show me products" / "all products" / "browse" → show random selection from all categories
    if (preg_match('/^(show me products|all products|browse products|browse|show products|view products|see products)$/i', trim($ml))) {
        $res  = $conn->query("SELECT p.id,p.name,p.brand,p.price,p.stock,p.image,c.name as cat_name FROM products p LEFT JOIN categories c ON p.category_id=c.id WHERE p.stock>0 ORDER BY RAND() LIMIT 8");
        $rows = [];
        if ($res) while ($r = $res->fetch_assoc()) $rows[] = $r;
        if (!empty($rows)) {
            $ctx['last_products'] = $rows;
            $fp = formatProducts($rows, 'Featured Products');
            return reply($fp['text'], array_merge($fp['qr'], ['Show me phones', 'Show me laptops', 'Show me fashion']));
        }
        return reply("Browse all our products here: <a href='" . SITE_URL . "/products.php'><strong>All Products →</strong></a>",
            ['Show me phones', 'Show me laptops']);
    }

    // "Show me phones/laptops/fashion/..." quick-reply shortcuts → force category search
    if (preg_match('/^show me (phones?|mobiles?|smartphones?)$/i', trim($ml))) {
        $rows = dbProductSearch('', $conn, 1);
        if (!empty($rows)) { $ctx['last_products'] = $rows; $fp = formatProducts($rows, 'Phones & Mobiles'); return reply($fp['text'], array_merge($fp['qr'], ['Show me laptops', 'Show me TVs', 'Show me products'])); }
    }
    if (preg_match('/^show me (laptops?|computers?|notebooks?)$/i', trim($ml))) {
        $rows = dbProductSearch('', $conn, 2);
        if (!empty($rows)) { $ctx['last_products'] = $rows; $fp = formatProducts($rows, 'Laptops & Computers'); return reply($fp['text'], array_merge($fp['qr'], ['Show me phones', 'Show me TVs', 'Show me products'])); }
    }
    if (preg_match('/^show me (fashion|clothes|clothing|dresses?|women|men)$/i', trim($ml))) {
        $catId = preg_match('/men/i', $ml) ? 5 : 6;
        $rows = dbProductSearch('', $conn, $catId);
        if (!empty($rows)) { $ctx['last_products'] = $rows; $fp = formatProducts($rows, 'Fashion & Clothing'); return reply($fp['text'], array_merge($fp['qr'], ['Show me phones', 'Show me laptops', 'Show me products'])); }
    }
    if (preg_match('/^show me (tvs?|televisions?|electronics?|speakers?|audio)$/i', trim($ml))) {
        $rows = dbProductSearch('', $conn, 3);
        if (!empty($rows)) { $ctx['last_products'] = $rows; $fp = formatProducts($rows, 'TVs & Electronics'); return reply($fp['text'], array_merge($fp['qr'], ['Show me phones', 'Show me laptops', 'Show me products'])); }
    }
    if (preg_match('/^show me (appliances?|fridges?|washing machines?|microwaves?)$/i', trim($ml))) {
        $rows = dbProductSearch('', $conn, 4);
        if (!empty($rows)) { $ctx['last_products'] = $rows; $fp = formatProducts($rows, 'Home Appliances'); return reply($fp['text'], array_merge($fp['qr'], ['Show me phones', 'Show me products'])); }
    }
    if (preg_match('/^show me (sports?|gym|fitness|exercise)$/i', trim($ml))) {
        $rows = dbProductSearch('', $conn, 9);
        if (!empty($rows)) { $ctx['last_products'] = $rows; $fp = formatProducts($rows, 'Sports & Fitness'); return reply($fp['text'], array_merge($fp['qr'], ['Show me phones', 'Show me products'])); }
    }
    if (preg_match('/^show me (beauty|skincare|cosmetics?|perfumes?|makeup)$/i', trim($ml))) {
        $rows = dbProductSearch('', $conn, 8);
        if (!empty($rows)) { $ctx['last_products'] = $rows; $fp = formatProducts($rows, 'Beauty & Skincare'); return reply($fp['text'], array_merge($fp['qr'], ['Show me fashion', 'Show me products'])); }
    }
    if (preg_match('/^show me (watches?|jewelry|jewellery|accessories)$/i', trim($ml))) {
        $rows = dbProductSearch('', $conn, 14);
        if (!empty($rows)) { $ctx['last_products'] = $rows; $fp = formatProducts($rows, 'Watches & Jewelry'); return reply($fp['text'], array_merge($fp['qr'], ['Show me fashion', 'Show me products'])); }
    }
    if (preg_match('/^show me (furniture|sofas?|beds?|chairs?|tables?)$/i', trim($ml))) {
        $rows = dbProductSearch('', $conn, 11);
        if (!empty($rows)) { $ctx['last_products'] = $rows; $fp = formatProducts($rows, 'Furniture & Home Decor'); return reply($fp['text'], array_merge($fp['qr'], ['Show me appliances', 'Show me products'])); }
    }
    if (preg_match('/^show me (baby|kids|toys?|children)$/i', trim($ml))) {
        $rows = dbProductSearch('', $conn, 10);
        if (!empty($rows)) { $ctx['last_products'] = $rows; $fp = formatProducts($rows, 'Baby & Kids'); return reply($fp['text'], array_merge($fp['qr'], ['Show me fashion', 'Show me products'])); }
    }
    if (preg_match('/^show me (cars?|vehicles?|auto|car accessories)$/i', trim($ml))) {
        $rows = dbProductSearch('', $conn, 12);
        if (!empty($rows)) { $ctx['last_products'] = $rows; $fp = formatProducts($rows, 'Cars & Auto'); return reply($fp['text'], array_merge($fp['qr'], ['Show me products'])); }
    }
    if (preg_match('/^show me (games?|gaming|playstation|xbox|consoles?)$/i', trim($ml))) {
        $rows = dbProductSearch('', $conn, 15);
        if (!empty($rows)) { $ctx['last_products'] = $rows; $fp = formatProducts($rows, 'Gaming'); return reply($fp['text'], array_merge($fp['qr'], ['Show me phones', 'Show me products'])); }
    }

    // ── 19b. "I WANT [product]" — find product and start cart flow directly ──
    if (preg_match('/\b(i want|i need|buy|purchase|get me|order)\b/i', $ml) && !preg_match('/\b(to buy|to order|to cancel|to track|history|status)\b/i', $ml)) {
        $rows = dbProductSearch($msg, $conn);
        if (!empty($rows)) {
            $p = $rows[0];
            $ctx['last_products'] = $rows;
            if (!$uid) {
                // Show product but prompt login
                $out = "🛍️ Found: <a href='" . SITE_URL . "/product.php?id={$p['id']}'><strong>" . htmlspecialchars($p['name']) . "</strong></a>"
                     . ($p['brand'] ? " <em>({$p['brand']})</em>" : '')
                     . " — <strong>RWF " . number_format($p['price']) . "</strong> ({$p['stock']} in stock)<br><br>"
                     . "🔒 Please <a href='" . SITE_URL . "/login.php'><strong>login</strong></a> to add this to your cart and place an order.";
                return reply($out, ['Login', 'Register', 'Show me more']);
            }
            // Logged in — start cart flow immediately
            $ctx['order_pending_product'] = ['id'=>(int)$p['id'],'name'=>$p['name'],'price'=>(float)$p['price'],'stock'=>(int)$p['stock']];
            $ctx['order_step'] = 'qty';
            $out = "🛍️ <strong>" . htmlspecialchars($p['name']) . "</strong>"
                 . ($p['brand'] ? " <em>({$p['brand']})</em>" : '')
                 . "<br>Price: <strong>RWF " . number_format($p['price']) . "</strong> | Stock: {$p['stock']} units<br><br>"
                 . "How many would you like to order?";
            if (count($rows) > 1) {
                $out .= "<br><br><small>Other options: ";
                foreach (array_slice($rows, 1, 3) as $r) {
                    $out .= "<a href='" . SITE_URL . "/product.php?id={$r['id']}'>" . htmlspecialchars($r['name']) . "</a> (RWF " . number_format($r['price']) . "), ";
                }
                $out = rtrim($out, ', ') . "</small>";
            }
            return reply($out, ['1', '2', '3', '5']);
        }
    }

    if (preg_match('/\b(show me|do you have|do you sell|looking for|find me|search for|i need|i want|i am looking|got any|list|display|give me)\b/i', $ml)
        || detectCategory($ml)
        || extractPriceRange($ml) !== [null, null]) {

        $rows = dbProductSearch($msg, $conn);
        if (!empty($rows)) {
            $ctx['last_products'] = $rows;
            [$minP, $maxP] = extractPriceRange($ml);
            $label = '';
            if ($minP && $maxP) $label = "Products RWF " . number_format($minP) . " – RWF " . number_format($maxP);
            elseif ($maxP)      $label = "Products under RWF " . number_format($maxP);
            elseif ($minP)      $label = "Products above RWF " . number_format($minP);
            $fp = formatProducts($rows, $label);
            return reply($fp['text'], array_merge($fp['qr'], ['Show me more', 'Recommend something']));
        }
        $kws = extractKeywords($msg);
        $kw  = implode(' ', array_slice($kws, 0, 2));
        return reply(
            "😕 No products found" . ($kw ? " for \"<strong>$kw</strong>\"" : "") . ".<br>" .
            "Try browsing: <a href='" . SITE_URL . "/products.php'>All products →</a>",
            ['Show me phones', 'Show me laptops', 'Show me fashion']
        );
    }

    // ── 20. ML MODEL — Python Flask classifier (if running) ──
    $mlResult = askMLModel($msg);
    if ($mlResult) {
        // ML detected an intent with high confidence — log it and let Gemini polish
        $intent     = $mlResult['intent'];
        $confidence = round($mlResult['confidence'] * 100, 1);
        $model_used = $mlResult['model_used'];
        // Try Gemini with ML intent context for a polished response
        $gemini = askGemini("[$intent intent detected by $model_used with {$confidence}% confidence] " . $msg, $uid, $conn, $session_id);
        if ($gemini) return reply($gemini . "<br><small style='color:#aaa;font-size:.7rem'>🤖 ML: $model_used ({$confidence}%)</small>",
            ['Show me products', 'Track my order', 'Delivery info', 'Contact support']);
    }

    // ── 21. GEMINI — for anything else (multilingual, complex questions) ──
    $gemini = askGemini($msg, $uid, $conn, $session_id);
    if ($gemini) return reply($gemini, ['Show me products', 'Track my order', 'Delivery info', 'Contact support']);

    // ── 22. KINYARWANDA FALLBACK — when Gemini is offline ──
    // Common Kinyarwanda shopping phrases mapped to actions
    if (preg_match('/\b(nyereka|erekana|mpore|mbwira|ndashaka|nshaka|fungura|reba|soma)\b/i', $ml)) {
        // Product search in Kinyarwanda
        if (preg_match('/\b(ibicuruzwa|ibintu|products?|telefoni|laptop|simu|imyenda|inzu|imodoka)\b/i', $ml)) {
            $catId = null;
            if (preg_match('/telefoni|simu|phone/i', $ml))    $catId = 1;
            elseif (preg_match('/laptop|ordinateur/i', $ml))  $catId = 2;
            elseif (preg_match('/imyenda|clothes|fashion/i', $ml)) $catId = 6;
            $rows = dbProductSearch('', $conn, $catId);
            if (!empty($rows)) {
                $ctx['last_products'] = $rows;
                $fp = formatProducts($rows, $catId ? '' : 'Ibicuruzwa / Products');
                return reply($fp['text'], array_merge($fp['qr'], ['Show me products', 'Delivery info']));
            }
        }
        // Price in Kinyarwanda
        if (preg_match('/\b(igiciro|price|bingahe|angahe|mafrw|amafaranga)\b/i', $ml)) {
            $rows = dbProductSearch($msg, $conn);
            if (!empty($rows)) {
                $out = "💰 <strong>Ibiciro / Prices:</strong><br>";
                foreach ($rows as $p)
                    $out .= "• <a href='" . SITE_URL . "/product.php?id={$p['id']}'>" . htmlspecialchars($p['name']) . "</a> — <strong>RWF " . number_format($p['price']) . "</strong><br>";
                return reply($out, ['Show me products', 'Delivery info']);
            }
        }
        // Generic — show products
        $rows = dbProductSearch('', $conn, null);
        if (!empty($rows)) {
            $fp = formatProducts($rows, 'Ibicuruzwa / Products');
            return reply($fp['text'], array_merge($fp['qr'], ['Show me products', 'Delivery info']));
        }
    }

    // French fallback
    if (preg_match('/\b(montrez|afficher|cherche|produits|téléphone|livraison|paiement|retour|prix)\b/i', $ml)) {
        if (preg_match('/produits|afficher|montrez/i', $ml)) {
            $rows = dbProductSearch('', $conn, null);
            if (!empty($rows)) { $fp = formatProducts($rows, 'Produits'); return reply($fp['text'], array_merge($fp['qr'], ['Show me products'])); }
        }
        if (preg_match('/livraison/i', $ml)) return reply("🚚 <strong>Délais de livraison:</strong><br>• Kigali: 1–2 jours<br>• Autres provinces: 2–4 jours<br>• Livraison gratuite au-dessus de RWF 50,000", ['Show me products', 'Payment methods']);
        if (preg_match('/paiement/i', $ml)) return reply("💳 <strong>Modes de paiement:</strong><br>• MTN MoMo • Airtel Money • Cash • Virement bancaire • Visa/Mastercard", ['Delivery info', 'Show me products']);
        if (preg_match('/retour/i', $ml))   return reply("↩️ <strong>Politique de retour:</strong> 7 jours après livraison. Email: <a href='mailto:" . ADMIN_EMAIL . "'>" . ADMIN_EMAIL . "</a>", ['Contact support']);
    }

    // ── 23. FINAL FALLBACK ──
    return reply(
        "😊 I'm not sure I understood that. Here's what I can help with:<br>" .
        "• 🛍️ <em>Show me phones / laptops / fashion</em><br>" .
        "• 💰 <em>Price of Samsung Galaxy</em><br>" .
        "• 📦 <em>Track order 5</em><br>" .
        "• 🚚 <em>Delivery time / Shipping fees</em><br>" .
        "• ↩️ <em>Return policy</em><br>" .
        "• 💳 <em>Payment methods</em>",
        ['Show me products', 'Track my order', 'Delivery info', 'Return policy']
    );
}

// ================================================================
// DB HELPERS
// ================================================================
function chatCartSummary(array $cart): string {
    if (empty($cart)) return "🛒 Cart is empty.";
    $out   = '';
    $total = 0;
    foreach ($cart as $item) {
        $sub    = $item['price'] * $item['qty'];
        $total += $sub;
        $out   .= "• {$item['qty']}x <strong>" . htmlspecialchars($item['name']) . "</strong> — RWF " . number_format($sub) . "<br>";
    }
    $out .= "<strong>Total: RWF " . number_format($total) . "</strong>";
    return $out;
}

function placeChatOrder(int $uid, array &$ctx, $conn): array {
    $cart    = $ctx['order_cart'];
    if (empty($cart)) {
        return reply("🛒 Your cart is empty. Please add products first.", ['Show me products']);
    }

    $address = $conn->real_escape_string(trim($ctx['order_data']['address'] ?? ''));
    $payment = $conn->real_escape_string(trim($ctx['order_data']['payment'] ?? 'cod'));
    $total   = array_sum(array_map(fn($i) => (float)$i['price'] * (int)$i['qty'], $cart));

    if (empty($address)) {
        $ctx['order_step'] = 'address';
        return reply("📍 Please provide your delivery address first.");
    }

    // Final stock validation
    foreach ($cart as $item) {
        $row = $conn->query("SELECT stock, name FROM products WHERE id=" . (int)$item['id'] . " LIMIT 1")->fetch_assoc();
        if (!$row || (int)$row['stock'] < (int)$item['qty']) {
            $avail = $row['stock'] ?? 0;
            return reply(
                "⚠️ <strong>" . htmlspecialchars($item['name']) . "</strong> only has <strong>$avail</strong> units left. " .
                "Please update your cart.",
                ['View cart', 'Clear cart']
            );
        }
    }

    // ── INSERT ORDER ──
    $conn->query("INSERT INTO orders (user_id, total_price, address, payment_method, status)
                  VALUES ($uid, $total, '$address', '$payment', 'pending')");
    $order_id = (int)$conn->insert_id;

    if (!$order_id) {
        // Log the MySQL error for debugging
        error_log("placeChatOrder INSERT failed: " . $conn->error . " | uid=$uid total=$total");
        return reply(
            "❌ Could not save your order. Please try again or use the <a href='" . SITE_URL . "/checkout.php'>checkout page</a>.",
            ['Try again', 'Contact support']
        );
    }

    // ── INSERT ORDER ITEMS + DEDUCT STOCK ──
    foreach ($cart as $item) {
        $pid   = (int)$item['id'];
        $qty   = (int)$item['qty'];
        $price = (float)$item['price'];
        $conn->query("INSERT INTO order_items (order_id, product_id, quantity, price)
                      VALUES ($order_id, $pid, $qty, $price)");
        $conn->query("UPDATE products SET stock = stock - $qty WHERE id = $pid AND stock >= $qty");
    }

    // ── SEND CONFIRMATION EMAIL ──
    $savedAddress = $ctx['order_data']['address'];
    $savedPayment = $ctx['order_data']['payment'];

    // Clear cart BEFORE email (so any email error doesn't block the success message)
    $ctx['order_cart'] = [];
    $ctx['order_step'] = null;
    $ctx['order_data'] = [];

    try {
        require_once __DIR__ . '/../includes/mailer.php';
        $user = $conn->query("SELECT name, email FROM users WHERE id=$uid LIMIT 1")->fetch_assoc();
        if ($user && !empty($user['email'])) {
            $emailItems = array_map(fn($i) => ['name'=>$i['name'],'price'=>$i['price'],'quantity'=>$i['qty']], $cart);
            $orderData  = [
                'id'              => $order_id,
                'customer_name'   => $user['name'],
                'address'         => $savedAddress,
                'payment_method'  => $savedPayment,
                'status'          => 'pending',
                'created_at'      => date('Y-m-d H:i:s'),
            ];
            sendMail(
                $user['email'], $user['name'],
                'Order Confirmed — #' . str_pad($order_id, 6, '0', STR_PAD_LEFT) . ' | ' . SITE_NAME,
                emailOrderConfirmation($orderData, $emailItems)
            );
        }
    } catch (Throwable $e) {
        error_log("placeChatOrder email error: " . $e->getMessage());
        // Email failure must NOT prevent showing success
    }

    $payLabels = ['cod'=>'Cash on Delivery','momo'=>'MTN Mobile Money','airtel'=>'Airtel Money','card'=>'Visa/Mastercard','bank'=>'Bank Transfer'];
    $orderNum  = str_pad($order_id, 6, '0', STR_PAD_LEFT);

    return reply(
        "🎉 <strong>Order Placed Successfully!</strong><br><br>" .
        "📦 <strong>Order #$orderNum</strong><br>" .
        "💰 Total: <strong>RWF " . number_format($total) . "</strong><br>" .
        "📍 Delivery to: <strong>" . htmlspecialchars($savedAddress) . "</strong><br>" .
        "💳 Payment: <strong>" . ($payLabels[$savedPayment] ?? $savedPayment) . "</strong><br>" .
        "📧 Confirmation email sent.<br><br>" .
        "🚚 Expected delivery: <strong>1–4 business days</strong><br><br>" .
        "<a href='" . SITE_URL . "/order_detail.php?id=$order_id'><strong>View Order Details →</strong></a> | " .
        "<a href='" . SITE_URL . "/orders.php'>My Orders →</a>",
        ['Track my order', 'Continue shopping', 'Contact support']
    );
}

function trackOrder(int $oid, ?int $uid, $conn): string {
    if (!$uid) return "🔒 Please <a href='" . SITE_URL . "/login.php'>login</a> to track orders.";
    $o = $conn->query("SELECT o.*, GROUP_CONCAT(p.name SEPARATOR ', ') as items
        FROM orders o LEFT JOIN order_items oi ON o.id=oi.order_id
        LEFT JOIN products p ON oi.product_id=p.id
        WHERE o.id=$oid AND o.user_id=$uid GROUP BY o.id")->fetch_assoc();
    if (!$o) return "❌ Order #$oid not found under your account. Please check the order number.";
    $emoji = ['pending'=>'⏳','processing'=>'⚙️','shipped'=>'🚚','delivered'=>'✅','cancelled'=>'❌'][$o['status']] ?? '📦';
    return "$emoji <strong>Order #" . $o['id'] . "</strong><br>"
         . "Status: <strong>" . ucfirst($o['status']) . "</strong><br>"
         . "Items: " . htmlspecialchars($o['items'] ?? 'N/A') . "<br>"
         . "Total: <strong>RWF " . number_format($o['total_price']) . "</strong><br>"
         . "Placed: " . date('d M Y, H:i', strtotime($o['created_at'])) . "<br>"
         . "<a href='" . SITE_URL . "/order_detail.php?id=" . $o['id'] . "'>View full details →</a>";
}

function cancelOrder(int $oid, int $uid, $conn): string {
    $o = $conn->query("SELECT id,status FROM orders WHERE id=$oid AND user_id=$uid")->fetch_assoc();
    if (!$o) return "❌ Order #$oid not found under your account.";
    if (in_array($o['status'], ['shipped','delivered']))
        return "⚠️ Order #$oid cannot be cancelled — it has already been <strong>" . $o['status'] . "</strong>.<br>Contact <a href='mailto:" . ADMIN_EMAIL . "'>" . ADMIN_EMAIL . "</a> | " . ADMIN_PHONE . " for a return/refund.";
    if ($o['status'] === 'cancelled') return "Order #$oid is already cancelled.";
    $conn->query("UPDATE orders SET status='cancelled' WHERE id=$oid AND user_id=$uid");
    return "✅ Order #$oid has been <strong>cancelled</strong> successfully.<br>Refunds processed within 3–5 business days.";
}

function orderHistory(int $uid, $conn): string {
    $orders = $conn->query("SELECT id,status,total_price,created_at FROM orders WHERE user_id=$uid ORDER BY created_at DESC LIMIT 5");
    if ($orders->num_rows === 0) return "You haven't placed any orders yet. <a href='" . SITE_URL . "/products.php'>Start shopping →</a>";
    $list = "📋 <strong>Your Recent Orders:</strong><br>";
    while ($o = $orders->fetch_assoc()) {
        $list .= "• <a href='" . SITE_URL . "/order_detail.php?id=" . $o['id'] . "'>#" . $o['id'] . "</a> — "
               . ucfirst($o['status']) . " — RWF " . number_format($o['total_price'])
               . " (" . date('d M Y', strtotime($o['created_at'])) . ")<br>";
    }
    $list .= "<a href='" . SITE_URL . "/orders.php'>View all orders →</a>";
    return $list;
}

function getFirstName(int $uid, $conn): string {
    $r = $conn->query("SELECT name FROM users WHERE id=$uid LIMIT 1")->fetch_assoc();
    return $r ? explode(' ', $r['name'])[0] : 'there';
}

// ================================================================
// ML API — Python Flask intent classifier
// Called before Gemini for fast local intent detection
// ================================================================
function askMLModel(string $message): ?array {
    $url = 'http://localhost:5000/predict';
    $ch  = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode(['message' => $message, 'model' => 'best']),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT        => 3,   // fast timeout — fallback to PHP engine if slow
        CURLOPT_CONNECTTIMEOUT => 2,
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code !== 200 || !$resp) return null;
    $data = json_decode($resp, true);
    if (!isset($data['intent'], $data['confidence'])) return null;

    // Only trust ML model if confidence is high enough
    if ($data['confidence'] < 0.55) return null;

    return [
        'intent'     => $data['intent'],
        'confidence' => $data['confidence'],
        'model_used' => $data['model_used'] ?? 'ML Model',
    ];
}

// ================================================================
// GEMINI — DB-grounded AI responses
// ================================================================
function askGemini(string $userMessage, ?int $uid, $conn, string $session_id): ?string {
    $apiKey = GEMINI_API_KEY ?? '';
    if (empty($apiKey) || $apiKey === 'your-gemini-api-key-here') return null;

    $ml = strtolower($userMessage);

    // ── 1. Detect category from message ──
    $catId = detectCategory($ml);

    // ── 2. Fetch matching products (keyword + category) ──
    $rows = dbProductSearch($userMessage, $conn);

    // If no keyword match, try category-only
    if (empty($rows) && $catId) {
        $rows = dbProductSearch('', $conn, $catId);
    }

    // If still empty, fetch a broad sample: 3 products from each category
    if (empty($rows)) {
        $sampleRes = $conn->query("
            SELECT p.id, p.name, p.brand, p.price, p.stock, p.description, c.name AS cat
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            WHERE p.stock > 0
            ORDER BY RAND()
            LIMIT 16
        ");
        if ($sampleRes) while ($r = $sampleRes->fetch_assoc()) $rows[] = $r;
    }

    // ── 3. Build rich product context ──
    $productCtx = '';
    if (!empty($rows)) {
        $productCtx = "\nPRODUCTS FROM DATABASE (use ONLY these — never invent):\n";
        foreach ($rows as $p) {
            $productCtx .= "• [ID:{$p['id']}] {$p['name']}"
                . ($p['brand'] ? " ({$p['brand']})" : '')
                . " | Price: RWF " . number_format($p['price'])
                . " | Stock: {$p['stock']} units"
                . (!empty($p['description']) ? " | " . mb_substr(strip_tags($p['description']), 0, 60) : '')
                . "\n";
        }
    }

    // ── 4. Category catalog (totals + price ranges) ──
    $catRows  = $conn->query("SELECT c.name AS cat, COUNT(p.id) AS total, MIN(p.price) AS mn, MAX(p.price) AS mx FROM categories c LEFT JOIN products p ON p.category_id=c.id AND p.stock>0 GROUP BY c.id ORDER BY c.id");
    $catCtx   = "\nSTORE CATEGORIES:\n";
    while ($row = $catRows->fetch_assoc())
        $catCtx .= "- {$row['cat']}: {$row['total']} products, RWF " . number_format($row['mn']) . " – RWF " . number_format($row['mx']) . "\n";

    // ── 5. Customer context ──
    $userCtx = '';
    if ($uid) {
        $u  = $conn->query("SELECT name FROM users WHERE id=$uid")->fetch_assoc();
        $oc = $conn->query("SELECT COUNT(*) as c FROM orders WHERE user_id=$uid")->fetch_assoc();
        $lo = $conn->query("SELECT o.id, o.status, o.total_price FROM orders o WHERE o.user_id=$uid ORDER BY o.created_at DESC LIMIT 3");
        $userCtx = "\nCUSTOMER: {$u['name']} | Total orders: {$oc['c']}";
        if ($lo) {
            $userCtx .= "\nRECENT ORDERS:";
            while ($o = $lo->fetch_assoc())
                $userCtx .= "\n- Order #{$o['id']} | Status: {$o['status']} | RWF " . number_format($o['total_price']);
        }
    }

    // ── 6. Conversation history (last 12 turns) ──
    $sid  = $conn->real_escape_string($session_id);
    $hist = $conn->query("SELECT message,response FROM chatbot_logs WHERE session_id='$sid' ORDER BY created_at DESC LIMIT 12");
    $history = [];
    if ($hist) {
        $rows2 = [];
        while ($r = $hist->fetch_assoc()) $rows2[] = $r;
        foreach (array_reverse($rows2) as $r) {
            $history[] = ['role' => 'user',  'parts' => [['text' => $r['message']]]];
            $history[] = ['role' => 'model', 'parts' => [['text' => strip_tags($r['response'])]]];
        }
    }
    $history[] = ['role' => 'user', 'parts' => [['text' => $userMessage]]];

    // ── 7. System prompt ──
    $system = "You are the AI shopping assistant for \"" . SITE_NAME . "\", an e-commerce store in Rwanda.\n"
        . "CRITICAL RULES:\n"
        . "- ONLY recommend products listed in the PRODUCTS FROM DATABASE section below. Never invent products, names, or prices.\n"
        . "- Always show prices in RWF exactly as listed.\n"
        . "- When showing products, always include the product name and price from the database.\n"
        . "- Be friendly, helpful, and concise (max 250 words).\n"
        . "- Respond in the SAME language the customer uses (English, French, or Kinyarwanda).\n"
        . "- For product links, format as: [Product Name](" . SITE_URL . "/product.php?id=ID)\n"
        . "- NEVER place orders, add items to cart, confirm orders, or collect delivery/payment details. "
        . "  If a customer wants to buy or place an order, tell them to click the product link or use the Add to Cart button. "
        . "  Order placement is handled by the system — you must NOT simulate or pretend to place orders.\n"
        . "- IMPORTANT: The conversation history below contains ALL previous messages from this customer. Use it to:\n"
        . "  * Remember what products they asked about before\n"
        . "  * Avoid repeating the same products if they already saw them\n"
        . "  * Understand their preferences and budget from earlier messages\n"
        . "  * Give follow-up answers that reference what was already discussed\n"
        . "\nSTORE POLICIES:\n"
        . "- Free shipping on orders above RWF 50,000\n"
        . "- Delivery: 1-2 days Kigali | 2-4 days other provinces\n"
        . "- Returns: 7 days after delivery\n"
        . "- Payment: MTN MoMo, Airtel Money, Cash on Delivery, Bank Transfer, Visa/Mastercard\n"
        . "- Support: " . ADMIN_EMAIL . " | " . ADMIN_PHONE . "\n"
        . $catCtx
        . $productCtx
        . $userCtx;

    $payload = json_encode([
        'system_instruction' => ['parts' => [['text' => $system]]],
        'contents'           => $history,
        'generationConfig'   => ['temperature' => 0.2, 'maxOutputTokens' => 1000],
    ]);

    // ── 8. Try Gemini models in order ──
    $models = ['gemini-2.0-flash', 'gemini-2.5-flash', 'gemini-flash-latest'];
    foreach ($models as $model) {
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";
        $ch  = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT        => 15,
        ]);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code === 200) {
            $data = json_decode($resp, true);
            $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? null;
            if ($text) {
                // Convert markdown to HTML
                $text = preg_replace('/\*\*(.*?)\*\*/s', '<strong>$1</strong>', $text);
                $text = preg_replace('/\*(.*?)\*/s',     '<em>$1</em>',         $text);
                $text = preg_replace('/\[([^\]]+)\]\(([^)]+)\)/', "<a href='$2'>$1</a>", $text);
                $text = preg_replace('/\n/', '<br>', $text);
                return trim($text);
            }
        }
        if ($code !== 429 && $code !== 404) break;
    }
    return null;
}
