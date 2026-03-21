<?php
/**
 * AI Chatbot — Advanced NLP Engine
 * Handles: greetings, order tracking, complaints, product search,
 * recommendations, stock, price, cancel requests, FAQs, small talk, OpenAI fallback
 */
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db.php';

$input   = json_decode(file_get_contents('php://input'), true);
$message = trim($input['message'] ?? '');
$user_id = $_SESSION['user_id'] ?? null;

if (empty($message)) {
    echo json_encode(['response' => 'Please type a message.']);
    exit;
}

// --- Session-based conversation context ---
if (!isset($_SESSION['chat_context'])) {
    $_SESSION['chat_context'] = ['last_intent' => null, 'last_order_id' => null, 'last_product' => null];
}
$ctx = &$_SESSION['chat_context'];

$response = processMessage($message, $user_id, $conn, $ctx);

// Log conversation
$safe_msg = $conn->real_escape_string($message);
$safe_res = $conn->real_escape_string($response);
$conn->query("INSERT INTO chatbot_logs (user_id, message, response) VALUES (" . ($user_id ?? 'NULL') . ", '$safe_msg', '$safe_res')");

echo json_encode(['response' => $response]);

// ================================================================
// INTENT DETECTION — returns the best matching intent
// ================================================================
function detectIntent(string $msg): string {
    $patterns = [
        'greeting'        => '/\b(hi|hello|hey|good morning|good afternoon|good evening|howdy|sup|what\'s up)\b/',
        'goodbye'         => '/\b(bye|goodbye|see you|take care|ciao|later|ok thanks|thank you|thanks)\b/',
        'order_track'     => '/\b(track|tracking|where is|status of|check order|my order|order status)\b/',
        'order_cancel'    => '/\b(cancel|cancell?ation|i want to cancel|stop my order)\b/',
        'order_history'   => '/\b(my orders|order history|past orders|previous orders|all orders)\b/',
        'complaint'       => '/\b(wrong item|damaged|broken|missing|not received|bad quality|complaint|problem with|issue with|defective|fake)\b/',
        'refund_status'   => '/\b(refund status|when.*refund|got my refund|refund processed)\b/',
        'return_policy'   => '/\b(return|refund|exchange|send back|policy|how to return)\b/',
        'delivery_time'   => '/\b(delivery|shipping|how long|when.*arrive|estimated|dispatch|days)\b/',
        'delivery_cost'   => '/\b(shipping fee|delivery fee|shipping cost|free delivery|free shipping)\b/',
        'payment'         => '/\b(payment|pay|gcash|credit card|debit|cod|cash on delivery|bank transfer|installment|how to pay)\b/',
        'product_search'  => '/\b(do you have|sell|looking for|find|search|available|got any|show me|i need|i want)\b/',
        'product_price'   => '/\b(price|cost|how much|expensive|cheap|afford|budget)\b/',
        'product_stock'   => '/\b(in stock|out of stock|available|stock|left|remaining|quantity)\b/',
        'recommendation'  => '/\b(recommend|suggest|best|popular|top|what should|which one|good product)\b/',
        'discount'        => '/\b(discount|promo|sale|voucher|coupon|deal|offer|cheaper)\b/',
        'account'         => '/\b(account|profile|password|login|register|sign up|forgot password|change email)\b/',
        'contact'         => '/\b(contact|support|help|agent|human|talk to|call|email|phone number)\b/',
        'store_hours'     => '/\b(open|close|hours|working hours|business hours|when are you)\b/',
        'warranty'        => '/\b(warranty|guarantee|broken after|stopped working|repair)\b/',
        'small_talk'      => '/\b(how are you|what are you|who are you|are you a bot|are you human|your name)\b/',
        'compliment'      => '/\b(great|awesome|amazing|love|excellent|good job|well done|nice)\b/',
    ];

    foreach ($patterns as $intent => $pattern) {
        if (preg_match($pattern, $msg)) return $intent;
    }
    return 'unknown';
}

// ================================================================
// MAIN PROCESSOR
// ================================================================
function processMessage(string $msg, ?int $user_id, $conn, array &$ctx): string {
    $msg_lower = strtolower(trim($msg));
    $intent    = detectIntent($msg_lower);
    $ctx['last_intent'] = $intent;

    switch ($intent) {

        // ---- GREETING ----
        case 'greeting':
            $name = $user_id ? getUserName($user_id, $conn) : 'there';
            $tips = ["track your orders", "find products", "check prices", "learn about our policies"];
            $tip  = $tips[array_rand($tips)];
            return "👋 Hello, $name! Welcome to " . SITE_NAME . ". I can help you $tip. What do you need today?";

        // ---- GOODBYE ----
        case 'goodbye':
            return "😊 Thank you for visiting " . SITE_NAME . "! Have a great day. Come back anytime — I'm here 24/7!";

        // ---- COMPLIMENT ----
        case 'compliment':
            return "🙏 Thank you so much! That really means a lot. Is there anything else I can help you with?";

        // ---- SMALL TALK ----
        case 'small_talk':
            return handleSmallTalk($msg_lower);

        // ---- ORDER TRACKING ----
        case 'order_track':
            return handleOrderTrack($msg, $msg_lower, $user_id, $conn, $ctx);

        // ---- ORDER CANCEL REQUEST ----
        case 'order_cancel':
            return handleOrderCancel($msg, $msg_lower, $user_id, $conn, $ctx);

        // ---- ORDER HISTORY ----
        case 'order_history':
            return handleOrderHistory($user_id, $conn);

        // ---- COMPLAINT ----
        case 'complaint':
            return handleComplaint($msg_lower, $user_id, $conn, $ctx);

        // ---- REFUND STATUS ----
        case 'refund_status':
            return handleRefundStatus($msg, $user_id, $conn, $ctx);

        // ---- RETURN POLICY ----
        case 'return_policy':
            return "↩️ <strong>Return & Refund Policy:</strong><br>"
                 . "• Items returnable within <strong>7 days</strong> of delivery<br>"
                 . "• Item must be unused, in original packaging<br>"
                 . "• Damaged/wrong items: full refund or replacement<br>"
                 . "• Refunds processed within <strong>3–5 business days</strong><br>"
                 . "📧 Email: support@shopai.com to start a return.";

        // ---- DELIVERY TIME ----
        case 'delivery_time':
            return "🚚 <strong>Delivery Times:</strong><br>"
                 . "• Standard: <strong>3–5 business days</strong><br>"
                 . "• Express: <strong>1–2 business days</strong> (extra fee at checkout)<br>"
                 . "• Metro areas: usually faster<br>"
                 . "• Remote areas: may take up to 7 days<br>"
                 . "You'll receive a tracking update once your order is shipped!";

        // ---- DELIVERY COST ----
        case 'delivery_cost':
            return "📦 <strong>Shipping Fees:</strong><br>"
                 . "• Orders above ₱2,000 — <strong>FREE shipping</strong> 🎉<br>"
                 . "• Orders below ₱2,000 — ₱99 flat rate<br>"
                 . "• Express delivery — ₱199 additional";

        // ---- PAYMENT ----
        case 'payment':
            return "💳 <strong>Payment Methods We Accept:</strong><br>"
                 . "• 💵 Cash on Delivery (COD)<br>"
                 . "• 📱 GCash<br>"
                 . "• 💳 Credit / Debit Card (Visa, Mastercard)<br>"
                 . "• 🏦 Bank Transfer (BDO, BPI, Metrobank)<br>"
                 . "• 📲 Maya (PayMaya)<br>"
                 . "All online payments are <strong>SSL secured</strong>. 🔒";

        // ---- PRODUCT SEARCH ----
        case 'product_search':
            return handleProductSearch($msg_lower, $conn, $ctx);

        // ---- PRODUCT PRICE ----
        case 'product_price':
            return handleProductPrice($msg_lower, $conn, $ctx);

        // ---- PRODUCT STOCK ----
        case 'product_stock':
            return handleProductStock($msg_lower, $conn, $ctx);

        // ---- RECOMMENDATION ----
        case 'recommendation':
            return handleRecommendation($msg_lower, $conn);

        // ---- DISCOUNT ----
        case 'discount':
            return "🏷️ <strong>Current Promotions:</strong><br>"
                 . "• Free shipping on orders above ₱2,000<br>"
                 . "• Check our <a href='" . SITE_URL . "/products.php'>Products page</a> for sale items<br>"
                 . "• Follow us on social media for exclusive voucher codes!<br>"
                 . "💡 Tip: Add more items to your cart to qualify for free shipping.";

        // ---- ACCOUNT ----
        case 'account':
            return handleAccount($msg_lower);

        // ---- CONTACT ----
        case 'contact':
            return "📞 <strong>Contact & Support:</strong><br>"
                 . "• 📧 Email: support@shopai.com<br>"
                 . "• 📱 Phone: +63 912 345 6789<br>"
                 . "• 💬 Live Chat: Available here 24/7<br>"
                 . "• 🕐 Office Hours: Mon–Sat, 8AM–6PM<br>"
                 . "For urgent concerns, please call or email directly.";

        // ---- STORE HOURS ----
        case 'store_hours':
            return "🕐 <strong>Business Hours:</strong><br>"
                 . "• Monday – Saturday: 8:00 AM – 6:00 PM<br>"
                 . "• Sunday: 10:00 AM – 4:00 PM<br>"
                 . "• Our online store & chatbot are available <strong>24/7</strong>! 🌐";

        // ---- WARRANTY ----
        case 'warranty':
            return "🛡️ <strong>Warranty Information:</strong><br>"
                 . "• Electronics: <strong>1-year</strong> manufacturer warranty<br>"
                 . "• Clothing & Accessories: <strong>7-day</strong> defect warranty<br>"
                 . "• Watches: <strong>6-month</strong> warranty<br>"
                 . "For warranty claims, email support@shopai.com with your order number and photos of the issue.";

        // ---- UNKNOWN — try product search or OpenAI ----
        default:
            // Last resort: try searching the message as a product name
            $product_result = searchProductFallback($msg_lower, $conn);
            if ($product_result) return $product_result;
            return callOpenAI($msg);
    }
}

// ================================================================
// HANDLER FUNCTIONS
// ================================================================

function handleOrderTrack(string $msg, string $msg_lower, ?int $user_id, $conn, array &$ctx): string {
    if (!$user_id) {
        return "🔒 Please <a href='" . SITE_URL . "/login.php'>login</a> first to track your orders.";
    }
    // Extract order number from message
    if (preg_match('/\b(\d+)\b/', $msg, $matches)) {
        $order_id = (int)$matches[1];
        $ctx['last_order_id'] = $order_id;
        $result = $conn->query("SELECT o.*, GROUP_CONCAT(p.name SEPARATOR ', ') as items
            FROM orders o
            LEFT JOIN order_items oi ON o.id = oi.order_id
            LEFT JOIN products p ON oi.product_id = p.id
            WHERE o.id = $order_id AND o.user_id = $user_id
            GROUP BY o.id")->fetch_assoc();

        if ($result) {
            $status_emoji = ['pending'=>'⏳','processing'=>'⚙️','shipped'=>'🚚','delivered'=>'✅','cancelled'=>'❌'];
            $emoji = $status_emoji[$result['status']] ?? '📦';
            return "$emoji <strong>Order #" . $result['id'] . "</strong><br>"
                 . "Status: <strong>" . ucfirst($result['status']) . "</strong><br>"
                 . "Items: " . htmlspecialchars($result['items']) . "<br>"
                 . "Total: ₱" . number_format($result['total_price'], 2) . "<br>"
                 . "Placed: " . date('M d, Y h:i A', strtotime($result['created_at'])) . "<br>"
                 . "<a href='" . SITE_URL . "/order_detail.php?id=" . $result['id'] . "'>View full details →</a>";
        }
        return "❌ I couldn't find Order #$order_id under your account. Please double-check the order number.";
    }
    // No number — show their latest order
    $latest = $conn->query("SELECT id, status FROM orders WHERE user_id=$user_id ORDER BY created_at DESC LIMIT 1")->fetch_assoc();
    if ($latest) {
        return "Your latest order is <strong>#" . $latest['id'] . "</strong> — Status: <strong>" . ucfirst($latest['status']) . "</strong>.<br>"
             . "To track a specific order, type: <em>track order [number]</em>";
    }
    return "You don't have any orders yet. <a href='" . SITE_URL . "/products.php'>Start shopping →</a>";
}

function handleOrderCancel(string $msg, string $msg_lower, ?int $user_id, $conn, array &$ctx): string {
    if (!$user_id) {
        return "🔒 Please <a href='" . SITE_URL . "/login.php'>login</a> to manage your orders.";
    }
    if (preg_match('/\b(\d+)\b/', $msg, $matches)) {
        $order_id = (int)$matches[1];
        $order = $conn->query("SELECT id, status FROM orders WHERE id=$order_id AND user_id=$user_id")->fetch_assoc();
        if (!$order) {
            return "❌ Order #$order_id not found under your account.";
        }
        if (in_array($order['status'], ['shipped', 'delivered'])) {
            return "⚠️ Order #$order_id cannot be cancelled — it has already been <strong>" . $order['status'] . "</strong>.<br>"
                 . "If you have an issue, please contact support@shopai.com for a return/refund.";
        }
        if ($order['status'] === 'cancelled') {
            return "Order #$order_id is already cancelled.";
        }
        // Cancel it
        $conn->query("UPDATE orders SET status='cancelled' WHERE id=$order_id AND user_id=$user_id");
        return "✅ Order #$order_id has been <strong>cancelled</strong> successfully.<br>"
             . "If you paid online, your refund will be processed within 3–5 business days.";
    }
    return "To cancel an order, please provide the order number. Example: <em>cancel order 5</em><br>"
         . "You can find your order numbers on the <a href='" . SITE_URL . "/orders.php'>My Orders</a> page.";
}

function handleOrderHistory(?int $user_id, $conn): string {
    if (!$user_id) {
        return "🔒 Please <a href='" . SITE_URL . "/login.php'>login</a> to view your order history.";
    }
    $orders = $conn->query("SELECT id, status, total_price, created_at FROM orders WHERE user_id=$user_id ORDER BY created_at DESC LIMIT 5");
    if ($orders->num_rows === 0) {
        return "You haven't placed any orders yet. <a href='" . SITE_URL . "/products.php'>Browse products →</a>";
    }
    $list = "📋 <strong>Your Recent Orders:</strong><br>";
    while ($o = $orders->fetch_assoc()) {
        $list .= "• <a href='" . SITE_URL . "/order_detail.php?id=" . $o['id'] . "'>#" . $o['id'] . "</a> — "
               . ucfirst($o['status']) . " — ₱" . number_format($o['total_price'], 2)
               . " (" . date('M d, Y', strtotime($o['created_at'])) . ")<br>";
    }
    $list .= "<a href='" . SITE_URL . "/orders.php'>View all orders →</a>";
    return $list;
}

function handleComplaint(string $msg_lower, ?int $user_id, $conn, array &$ctx): string {
    $response = "😔 I'm really sorry to hear that! We take all complaints seriously.<br><br>";
    if (preg_match('/\b(wrong item|wrong product)\b/', $msg_lower)) {
        $response .= "📦 <strong>Wrong Item Received:</strong><br>"
                   . "• Take a photo of the item you received<br>"
                   . "• Email support@shopai.com with your order number + photo<br>"
                   . "• We'll arrange a free return and send the correct item within 2–3 days.";
    } elseif (preg_match('/\b(damaged|broken|defective)\b/', $msg_lower)) {
        $response .= "🔧 <strong>Damaged/Defective Item:</strong><br>"
                   . "• Document the damage with photos/video<br>"
                   . "• Contact us within 7 days of delivery<br>"
                   . "• We'll offer a full replacement or refund — your choice.";
    } elseif (preg_match('/\b(not received|missing|never arrived)\b/', $msg_lower)) {
        $response .= "📭 <strong>Item Not Received:</strong><br>"
                   . "• First, check your order status: type <em>track order [number]</em><br>"
                   . "• If status shows 'Delivered' but you didn't receive it, contact us immediately<br>"
                   . "• We'll investigate with the courier within 24–48 hours.";
    } else {
        $response .= "Please describe your issue in detail and contact us:<br>"
                   . "📧 support@shopai.com | 📱 +63 912 345 6789<br>"
                   . "Include your order number for faster resolution.";
    }
    return $response;
}

function handleRefundStatus(string $msg, ?int $user_id, $conn, array &$ctx): string {
    if (!$user_id) {
        return "🔒 Please <a href='" . SITE_URL . "/login.php'>login</a> to check your refund status.";
    }
    if (preg_match('/\b(\d+)\b/', $msg, $matches)) {
        $order_id = (int)$matches[1];
        $order = $conn->query("SELECT id, status FROM orders WHERE id=$order_id AND user_id=$user_id")->fetch_assoc();
        if ($order && $order['status'] === 'cancelled') {
            return "💰 Order #$order_id was cancelled. Refunds are processed within <strong>3–5 business days</strong>.<br>"
                 . "If it's been longer, please email support@shopai.com with your order number.";
        }
    }
    return "💰 Refunds are processed within <strong>3–5 business days</strong> after cancellation/return approval.<br>"
         . "To check a specific refund, type: <em>refund status [order number]</em><br>"
         . "Or contact support@shopai.com for updates.";
}

function handleProductSearch(string $msg_lower, $conn, array &$ctx): string {
    $keyword = extractKeyword($msg_lower);
    if (empty($keyword)) {
        return "What product are you looking for? You can type a name like <em>laptop</em>, <em>watch</em>, or <em>shirt</em>.";
    }
    $ctx['last_product'] = $keyword;
    $search = "%" . $conn->real_escape_string($keyword) . "%";
    $results = $conn->query("SELECT id, name, price, stock FROM products WHERE (name LIKE '$search' OR description LIKE '$search') AND stock > 0 LIMIT 4");
    if ($results->num_rows > 0) {
        $list = "🛍️ <strong>Results for \"$keyword\":</strong><br>";
        while ($p = $results->fetch_assoc()) {
            $list .= "• <a href='" . SITE_URL . "/product.php?id=" . $p['id'] . "'>" . htmlspecialchars($p['name']) . "</a>"
                   . " — ₱" . number_format($p['price'], 2)
                   . " (" . $p['stock'] . " in stock)<br>";
        }
        $list .= "<a href='" . SITE_URL . "/products.php?search=" . urlencode($keyword) . "'>See all results →</a>";
        return $list;
    }
    return "😕 No products found for \"$keyword\".<br>"
         . "Try browsing by category: <a href='" . SITE_URL . "/products.php'>All Products →</a><br>"
         . "Or ask me: <em>recommend me a good phone</em>";
}

function handleProductPrice(string $msg_lower, $conn, array &$ctx): string {
    $keyword = extractKeyword($msg_lower);
    if ($keyword) {
        $search = "%" . $conn->real_escape_string($keyword) . "%";
        $results = $conn->query("SELECT name, price FROM products WHERE name LIKE '$search' OR description LIKE '$search' LIMIT 3");
        if ($results->num_rows > 0) {
            $list = "💰 <strong>Prices for \"$keyword\":</strong><br>";
            while ($p = $results->fetch_assoc()) {
                $list .= "• " . htmlspecialchars($p['name']) . " — <strong>₱" . number_format($p['price'], 2) . "</strong><br>";
            }
            return $list;
        }
    }
    // Show price range
    $range = $conn->query("SELECT MIN(price) as min_p, MAX(price) as max_p FROM products")->fetch_assoc();
    return "💰 Our products range from <strong>₱" . number_format($range['min_p'], 2) . "</strong> to <strong>₱" . number_format($range['max_p'], 2) . "</strong>.<br>"
         . "Tell me what product you're looking for and I'll give you the exact price!";
}

function handleProductStock(string $msg_lower, $conn, array &$ctx): string {
    $keyword = extractKeyword($msg_lower);
    if ($keyword) {
        $search = "%" . $conn->real_escape_string($keyword) . "%";
        $p = $conn->query("SELECT name, stock FROM products WHERE name LIKE '$search' LIMIT 1")->fetch_assoc();
        if ($p) {
            if ($p['stock'] > 0) {
                return "✅ <strong>" . htmlspecialchars($p['name']) . "</strong> is in stock — <strong>" . $p['stock'] . " units</strong> available.";
            }
            return "❌ Sorry, <strong>" . htmlspecialchars($p['name']) . "</strong> is currently out of stock.<br>"
                 . "Would you like me to suggest a similar product?";
        }
    }
    return "Which product would you like to check stock for? Type the product name and I'll check for you.";
}

function handleRecommendation(string $msg_lower, $conn): string {
    // Category-based recommendation
    $cat_map = [
        'phone|mobile|smartphone' => 1,
        'shirt|clothing|fashion|dress|wear' => 2,
        'watch|timepiece' => 3,
        'bag|accessory|accessories' => 4,
    ];
    $cat_id = null;
    foreach ($cat_map as $pattern => $id) {
        if (preg_match("/\b($pattern)\b/", $msg_lower)) { $cat_id = $id; break; }
    }

    $where = $cat_id ? "AND category_id = $cat_id" : "";
    $products = $conn->query("SELECT p.id, p.name, p.price, c.name as cat FROM products p LEFT JOIN categories c ON p.category_id=c.id WHERE p.stock > 0 $where ORDER BY RAND() LIMIT 3");

    if ($products->num_rows === 0) {
        $products = $conn->query("SELECT p.id, p.name, p.price, c.name as cat FROM products p LEFT JOIN categories c ON p.category_id=c.id WHERE p.stock > 0 ORDER BY RAND() LIMIT 3");
    }

    $list = "⭐ <strong>Recommended for you:</strong><br>";
    while ($p = $products->fetch_assoc()) {
        $list .= "• <a href='" . SITE_URL . "/product.php?id=" . $p['id'] . "'>" . htmlspecialchars($p['name']) . "</a>"
               . " — ₱" . number_format($p['price'], 2) . " (" . htmlspecialchars($p['cat']) . ")<br>";
    }
    $list .= "<a href='" . SITE_URL . "/products.php'>Browse all products →</a>";
    return $list;
}

function handleAccount(string $msg_lower): string {
    if (preg_match('/\b(forgot password|reset password|change password)\b/', $msg_lower)) {
        return "🔑 <strong>Password Reset:</strong><br>"
             . "Currently, password reset is done by contacting support.<br>"
             . "📧 Email: support@shopai.com with your registered email address.";
    }
    if (preg_match('/\b(register|sign up|create account)\b/', $msg_lower)) {
        return "📝 Creating an account is easy! <a href='" . SITE_URL . "/register.php'>Click here to register →</a><br>"
             . "You'll need your name, email, and a password.";
    }
    if (preg_match('/\b(login|sign in)\b/', $msg_lower)) {
        return "🔐 <a href='" . SITE_URL . "/login.php'>Click here to login →</a><br>"
             . "Forgot your password? Contact support@shopai.com.";
    }
    return "👤 For account-related help:<br>"
         . "• <a href='" . SITE_URL . "/login.php'>Login</a> | <a href='" . SITE_URL . "/register.php'>Register</a><br>"
         . "• For password issues, email support@shopai.com";
}

function handleSmallTalk(string $msg_lower): string {
    if (preg_match('/\b(how are you|how do you do)\b/', $msg_lower)) {
        return "😊 I'm doing great, thanks for asking! I'm always ready to help you shop. What can I do for you?";
    }
    if (preg_match('/\b(who are you|what are you|your name)\b/', $msg_lower)) {
        return "🤖 I'm the AI shopping assistant for <strong>" . SITE_NAME . "</strong>! I can help you find products, track orders, answer questions, and more. What do you need?";
    }
    if (preg_match('/\b(are you a bot|are you human|are you real)\b/', $msg_lower)) {
        return "🤖 I'm an AI chatbot — not human, but I'm pretty smart! I can handle most questions. For complex issues, I'll connect you to a human agent.";
    }
    return "😄 Ha! I'm just a chatbot, but a helpful one. Ask me anything about our products or orders!";
}

function searchProductFallback(string $msg_lower, $conn): ?string {
    $keyword = extractKeyword($msg_lower);
    if (strlen($keyword) < 3) return null;
    $search = "%" . $conn->real_escape_string($keyword) . "%";
    $p = $conn->query("SELECT id, name, price FROM products WHERE name LIKE '$search' AND stock > 0 LIMIT 1")->fetch_assoc();
    if ($p) {
        return "🛍️ I found <strong><a href='" . SITE_URL . "/product.php?id=" . $p['id'] . "'>" . htmlspecialchars($p['name']) . "</a></strong> for ₱" . number_format($p['price'], 2) . ". Would you like more details?";
    }
    return null;
}

// ================================================================
// HELPERS
// ================================================================
function extractKeyword(string $msg): string {
    $stopwords = ['do','you','have','sell','looking','for','find','search','available','is','the','a','an','any',
                  'i','want','need','show','me','got','price','cost','how','much','stock','in','of','what','about',
                  'recommend','suggest','best','popular','check','please','can','get'];
    $words    = preg_split('/\s+/', $msg);
    $filtered = array_filter($words, fn($w) => !in_array($w, $stopwords) && strlen($w) > 2);
    return implode(' ', array_slice(array_values($filtered), 0, 3));
}

function getUserName(?int $user_id, $conn): string {
    if (!$user_id) return 'there';
    $row = $conn->query("SELECT name FROM users WHERE id=$user_id")->fetch_assoc();
    return $row ? explode(' ', $row['name'])[0] : 'there';
}

// ================================================================
// OPENAI FALLBACK
// ================================================================
function callOpenAI(string $message): string {
    $api_key = OPENAI_API_KEY;
    if ($api_key === 'your-openai-api-key-here') {
        return "🤔 I'm not sure about that one. Try asking about:<br>"
             . "• Products, prices, stock<br>"
             . "• Order tracking or cancellation<br>"
             . "• Delivery, payment, returns<br>"
             . "Or contact us at support@shopai.com";
    }
    $system = "You are a helpful AI customer support agent for an e-commerce store called " . SITE_NAME . ". "
            . "Keep answers short (2-3 sentences max), friendly, and focused on shopping support. "
            . "If asked something unrelated to shopping, politely redirect.";
    $data = [
        'model'    => 'gpt-3.5-turbo',
        'messages' => [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user',   'content' => $message]
        ],
        'max_tokens'  => 200,
        'temperature' => 0.7
    ];
    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Authorization: Bearer ' . $api_key],
        CURLOPT_POSTFIELDS     => json_encode($data),
        CURLOPT_TIMEOUT        => 10
    ]);
    $result = curl_exec($ch);
    curl_close($ch);
    $json = json_decode($result, true);
    return $json['choices'][0]['message']['content'] ?? "I'm not sure about that. Please contact support@shopai.com.";
}
