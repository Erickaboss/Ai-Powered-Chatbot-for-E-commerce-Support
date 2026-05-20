<?php
/**
 * ShopAI Rwanda — Chatbot API
 * Handles all customer queries: products, budget, categories, brands, orders, support.
 * Uses Flask ML for intent classification with PHP rule-based fallback.
 * Never crashes — always returns a helpful response.
 */

header('Content-Type: application/json');
error_reporting(0);
ini_set('display_errors', '0');

if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config/db.php';

$conn->query("CREATE TABLE IF NOT EXISTS chatbot_memory (
    owner_key VARCHAR(96) PRIMARY KEY,
    user_id INT DEFAULT NULL,
    session_id VARCHAR(64) DEFAULT NULL,
    memory_json LONGTEXT NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_session_id (session_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

function normalizeChatSessionId(string $sessionId): string {
    $clean = preg_replace('/[^a-f0-9]/i', '', $sessionId);
    return strlen($clean) === 32 ? strtolower($clean) : bin2hex(random_bytes(16));
}

function resolveAuthenticatedUser($conn): ?int {
    $uid = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
    if ($uid <= 0) return null;

    $res = $conn->query("SELECT id FROM users WHERE id=$uid LIMIT 1");
    if ($res && $res->num_rows > 0) return $uid;

    unset($_SESSION['user_id']);
    return null;
}

$input  = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $_GET['action'] ?? '';

// ============================================================
// HISTORY ENDPOINT
// ============================================================
if ($action === 'history') {
    $sid = preg_replace('/[^a-f0-9]/i', '', $input['session_id'] ?? '');
    $uid = resolveAuthenticatedUser($conn);
    $history = [];
    if ($uid) {
        $uid = (int)$uid;
        $r = $conn->query("SELECT message, response, created_at FROM (SELECT message, response, created_at FROM chatbot_logs WHERE user_id=$uid ORDER BY created_at DESC LIMIT 40) recent ORDER BY created_at ASC");
        if ($r) while ($row = $r->fetch_assoc()) $history[] = $row;
    } elseif (strlen($sid) === 32) {
        $s = $conn->real_escape_string($sid);
        $r = $conn->query("SELECT message, response, created_at FROM (SELECT message, response, created_at FROM chatbot_logs WHERE session_id='$s' ORDER BY created_at DESC LIMIT 40) recent ORDER BY created_at ASC");
        if ($r) while ($row = $r->fetch_assoc()) $history[] = $row;
    }
    echo json_encode(['history' => $history]);
    exit;
}

// ============================================================
// RATE ENDPOINT
// ============================================================
if ($action === 'rate') {
    $logId  = (int)($input['log_id'] ?? 0);
    $rating = (int)($input['rating'] ?? -1);
    $uid2   = resolveAuthenticatedUser($conn);
    $sid2   = $conn->real_escape_string(preg_replace('/[^a-f0-9]/i', '', $input['session_id'] ?? ''));
    if ($logId && in_array($rating, [0, 1])) {
        $ui2 = $uid2 ? (int)$uid2 : 'NULL';
        $conn->query("INSERT IGNORE INTO chatbot_ratings (log_id, user_id, session_id, rating) VALUES ($logId, $ui2, '$sid2', $rating)");
    }
    echo json_encode(['ok' => true]);
    exit;
}

// ============================================================
// GEMINI VISION: analyze image and return structured keywords
// ============================================================
function analyzeImageWithGemini(string $tmpPath, string $mimeType): ?array {
    $apiKey = defined('GEMINI_API_KEY') ? GEMINI_API_KEY : '';
    if (empty($apiKey) || $apiKey === 'your-gemini-api-key-here') return null;

    $imageData   = base64_encode(file_get_contents($tmpPath));
    $visionPrompt = "Analyze this product image. Identify: 1) Exact product name 2) Brand if visible 3) Category (smartphone, laptop, furniture, clothing, etc.) 4) Key features visible. Reply in format: PRODUCT: [name] | BRAND: [brand or unknown] | CATEGORY: [category] | KEYWORDS: [keyword1, keyword2, keyword3]";

    $payload = json_encode([
        'contents' => [[
            'parts' => [
                ['text' => $visionPrompt],
                ['inlineData' => ['mimeType' => $mimeType, 'data' => $imageData]]
            ]
        ]],
        'generationConfig' => ['temperature' => 0.4, 'maxOutputTokens' => 256]
    ]);

    $models = ['gemini-2.5-flash-lite', 'gemini-2.0-flash-lite', 'gemini-2.0-flash'];
    foreach ($models as $model) {
        $ch = curl_init("https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}");
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_POSTFIELDS => $payload, CURLOPT_HTTPHEADER => ['Content-Type: application/json'], CURLOPT_TIMEOUT => 15]);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($code === 200) {
            $data = json_decode($resp, true);
            $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? null;
            if ($text) {
                // Parse structured response
                $parsed = ['product' => null, 'brand' => null, 'category' => null, 'keywords' => []];
                if (preg_match('/PRODUCT:\s*\[?([^\|\]]+)\]?/i', $text, $m)) $parsed['product'] = trim($m[1]);
                if (preg_match('/BRAND:\s*\[?([^\|\]]+)\]?/i', $text, $m)) $parsed['brand'] = trim($m[1]);
                if (preg_match('/CATEGORY:\s*\[?([^\|\]]+)\]?/i', $text, $m)) $parsed['category'] = trim($m[1]);
                if (preg_match('/KEYWORDS:\s*\[?([^\]]+)\]?/i', $text, $m)) {
                    $parsed['keywords'] = array_map('trim', explode(',', $m[1]));
                }
                return $parsed;
            }
        }
    }
    return null;
}

// ============================================================
// FILE / IMAGE UPLOAD ENDPOINT
// ============================================================
if ($action === 'upload') {
    $uid     = resolveAuthenticatedUser($conn);
    $msg     = trim($_POST['message'] ?? '');
    $sid     = normalizeChatSessionId($_POST['session_id'] ?? '');
    $response     = '';
    $quickReplies = ['Show me products', 'Browse categories'];

    if (empty($_FILES['file']['tmp_name'])) {
        echo json_encode(['response' => 'No file received. Please try again.', 'quick_replies' => $quickReplies]);
        exit;
    }

    $file = $_FILES['file'];
    $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $isImage = in_array($ext, ['jpg','jpeg','png','gif','webp','bmp']);
    $isDoc   = in_array($ext, ['pdf','doc','docx','txt']);

    if (!$isImage && !$isDoc) {
        echo json_encode(['response' => '❌ Unsupported file type. Please upload an image (JPG, PNG) or document (PDF, TXT, DOCX).', 'quick_replies' => $quickReplies]);
        exit;
    }

    if ($isImage) {
        // Use Gemini Vision to identify the product in the image
        $finfo    = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        $vision = analyzeImageWithGemini($file['tmp_name'], $mimeType);

        if ($vision) {
            // Build search term from Gemini's analysis
            $searchParts = array_filter([
                $vision['product'] !== 'unknown' ? $vision['product'] : null,
                $vision['brand']   !== 'unknown' ? $vision['brand']   : null,
            ]);
            $searchTerm = implode(' ', $searchParts) ?: implode(' ', array_slice($vision['keywords'], 0, 3));

            // Map Gemini category to DB category
            $cat = detectCategory($vision['category'] . ' ' . $searchTerm . ' ' . implode(' ', $vision['keywords']));

            // Search DB
            $products = queryProducts($conn, $cat, null, $searchTerm, 8);
            if (empty($products) && $cat) {
                $products = queryProducts($conn, $cat, null, null, 8);
            }
            if (empty($products) && !empty($vision['keywords'])) {
                foreach ($vision['keywords'] as $kw) {
                    $products = queryProducts($conn, null, null, $kw, 8);
                    if (!empty($products)) break;
                }
            }

            if (!empty($products)) {
                $productName = $vision['product'] ?? 'this product';
                $result      = formatProductList($products, "Products matching: " . htmlspecialchars($productName));
                $response    = "📷 <strong>Image recognized!</strong> I identified: <em>" . htmlspecialchars($vision['product'] ?? 'a product') . "</em>" . (!empty($vision['brand']) && $vision['brand'] !== 'unknown' ? " by <strong>" . htmlspecialchars($vision['brand']) . "</strong>" : "") . ".<br><br>" . $result['response'];
                $quickReplies = $result['quick_replies'];
            } else {
                // Product not in store — suggest similar categories
                $suggestedCat = $vision['category'] ?? 'Electronics';
                $response     = "📷 I identified <em>" . htmlspecialchars($vision['product'] ?? 'this product') . "</em> in your image, but this exact product is not currently in our store.<br><br>💡 You might find similar items in our <strong>" . htmlspecialchars($suggestedCat) . "</strong> category. Try browsing below!";
                $quickReplies = ['Browse categories', 'Show me products', 'Contact support'];
            }
        } else {
            // Gemini unavailable — fall back to message-based search
            $searchMsg = $msg ?: 'show me products';
            $cat       = detectCategory($searchMsg);
            $products  = queryProducts($conn, $cat, null, $searchMsg, 8);
            if (empty($products)) $products = queryProducts($conn, null, null, null, 8);
            $result       = formatProductList($products, $cat ? "$cat Products" : "Featured Products");
            $response     = "📷 I received your image! Here are some products that might match:<br><br>" . $result['response'];
            $quickReplies = $result['quick_replies'];
        }

    } else {
        // For documents: extract text and search for products
        $docText = '';
        if ($ext === 'txt') {
            $docText = file_get_contents($file['tmp_name']);
        } elseif ($ext === 'pdf') {
            // Try Gemini for PDF text extraction first
            $apiKey = defined('GEMINI_API_KEY') ? GEMINI_API_KEY : '';
            if (!empty($apiKey) && $apiKey !== 'your-gemini-api-key-here') {
                $pdfData = base64_encode(file_get_contents($file['tmp_name']));
                $payload = json_encode([
                    'contents' => [[
                        'parts' => [
                            ['text' => 'Extract all product names, brands, and categories mentioned in this document. List them comma-separated.'],
                            ['inlineData' => ['mimeType' => 'application/pdf', 'data' => $pdfData]]
                        ]
                    ]],
                    'generationConfig' => ['temperature' => 0.2, 'maxOutputTokens' => 512]
                ]);
                $ch = curl_init("https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key={$apiKey}");
                curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_POSTFIELDS => $payload, CURLOPT_HTTPHEADER => ['Content-Type: application/json'], CURLOPT_TIMEOUT => 15]);
                $resp = curl_exec($ch);
                $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                if ($code === 200) {
                    $data    = json_decode($resp, true);
                    $docText = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
                }
            }
            if (empty($docText)) {
                $content = file_get_contents($file['tmp_name']);
                preg_match_all('/\(([^\)]{2,100})\)/', $content, $matches);
                $docText = implode(' ', $matches[1] ?? []);
            }
        } elseif (in_array($ext, ['doc','docx'])) {
            if ($ext === 'docx') {
                $zip = new ZipArchive();
                if ($zip->open($file['tmp_name'])) {
                    $xml = $zip->getFromName('word/document.xml');
                    $zip->close();
                    if ($xml) {
                        $dom = new DOMDocument();
                        @$dom->loadXML($xml);
                        $xpath = new DOMXPath($dom);
                        $nodes = $xpath->query('//w:t');
                        $parts = [];
                        foreach ($nodes as $node) $parts[] = $node->nodeValue;
                        $docText = implode(' ', $parts);
                    }
                }
            }
            if (empty($docText)) {
                $content = file_get_contents($file['tmp_name']);
                $docText = preg_replace('/[^\x20-\x7E\n]/', ' ', $content);
            }
        }
        $docText = substr(strip_tags(preg_replace('/\s+/', ' ', $docText)), 0, 500);

        // Search products based on document content + user message
        $searchQuery = trim($msg . ' ' . $docText);
        $cat         = detectCategory($searchQuery);
        $budget      = parsePriceFilter($searchQuery) ?: parseBudget($searchQuery);
        $products    = queryProducts($conn, $cat, $budget, $searchQuery, 8);

        if (!empty($products)) {
            $result       = formatProductList($products, "Products matching your document");
            $response     = "📄 I read your document <strong>" . htmlspecialchars($file['name']) . "</strong> and found these matching products:<br><br>" . $result['response'];
            $quickReplies = $result['quick_replies'];
        } else {
            $response     = "📄 I read your document <strong>" . htmlspecialchars($file['name']) . "</strong> but couldn't find exact matches.<br><br>Please type what product you're looking for and I'll search our <strong>1,161 products</strong> for you.";
            $quickReplies = ['Show me products', 'Browse categories', 'Contact support'];
        }
    }

    // Save log
    $sm   = $conn->real_escape_string("📎 [{$ext}: {$file['name']}] " . ($msg ?: 'File uploaded'));
    $sr   = $conn->real_escape_string($response);
    $ui   = $uid ? (int)$uid : 'NULL';
    $s    = $conn->real_escape_string($sid);
    $conn->query("INSERT INTO chatbot_logs (user_id, session_id, is_guest, message, response, sentiment_score, sentiment_label) VALUES ($ui, '$s', " . ($uid ? 0 : 1) . ", '$sm', '$sr', 0.5, 'neutral')");
    $logId = (int)$conn->insert_id;

    echo json_encode(['response' => $response, 'quick_replies' => $quickReplies, 'session_id' => $sid, 'log_id' => $logId]);
    exit;
}

// ============================================================
// MAIN CHAT HANDLER
// ============================================================
try {
    $message    = trim($input['message'] ?? '');
    $session_id = normalizeChatSessionId((string)($input['session_id'] ?? ''));

    if (empty($message)) {
        echo json_encode(['response' => 'Please type a message.', 'quick_replies' => ['Show me products', 'Browse categories']]);
        exit;
    }

    $isDirectGreeting = preg_match('/^\s*(hi|hello|hey|good morning|good afternoon|good evening|muraho|bonjour|salut)\s*[!.?]*\s*$/i', $message);
    $isDirectAck = preg_match('/^\s*(ok|okay|k|yes|yeah|yep|sure|alright|fine|got it|noted|yego|oui)\s*[!.?]*\s*$/i', $message);

    // Resolve authenticated users only from the PHP session cookie.
    // The request body may include user_id for UI context, but it is not proof of login.
    $user_id = resolveAuthenticatedUser($conn);
    $_SESSION['chat_session_id'] = $session_id;

    // ── Session context memory (remembers last category, budget, products) ──
    $ctx = loadChatMemory($conn, $user_id ? (int)$user_id : null, $session_id);
    $ctx['customer'] = getCustomerProfile($conn, $user_id ? (int)$user_id : null);

    // ============================================================
    // STEP 1: ML MODEL — classify intent via Flask SVM
    // ============================================================
    $intent     = $isDirectGreeting ? 'greeting' : ($isDirectAck ? 'acknowledgement' : 'unknown');
    $confidence = ($isDirectGreeting || $isDirectAck) ? 1.0 : 0.0;
    $mlOnline   = (bool)($isDirectGreeting || $isDirectAck);

    if (!$isDirectGreeting && !$isDirectAck) {
        $ch = curl_init('http://127.0.0.1:5001/predict');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode(['message' => $message, 'model' => 'best']),
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT        => 3,
            CURLOPT_CONNECTTIMEOUT => 2,
        ]);
        $flask_resp = curl_exec($ch);
        $http_code  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code === 200 && $flask_resp) {
            $ml_data = json_decode($flask_resp, true);
            if ($ml_data && isset($ml_data['intent'])) {
                $intent     = $ml_data['intent'];
                $confidence = (float)($ml_data['confidence'] ?? 0);
                $mlOnline   = true;
            }
        }
    }

    // PHP rule-based fallback if Flask is unreachable
    if (!$mlOnline || $intent === 'unknown' || $confidence < 0.35) {
        $intent     = detectIntentFallback($message);
        $confidence = 0.0;
    }

    $mlower = strtolower($message);
    if (preg_match('/\b(forgot.*password|reset password|change password|password reset|recover account|can.?t login|cannot login|login problem)\b/i', $mlower)) {
        $intent = 'account_help';
    } elseif (preg_match('/\b(compare|comparison|versus|vs\.?)\b/i', $mlower)) {
        $intent = 'product_compare';
    } elseif (preg_match('/\b(start|request|open|create|make)\b.*\b(return|refund|exchange)\b|\b(return|refund|exchange)\b.*\b(request|item|order|product)\b/i', $mlower)) {
        $intent = 'return_request';
    } elseif (preg_match('/\b(how\s+(can|do)\s+i\s+(buy|purchase|order|place an order)|how\s+to\s+(buy|purchase|order|checkout)|place\s+(an\s+)?order|make\s+(an\s+)?order|complete\s+(my\s+)?purchase|checkout)\b/i', $mlower)) {
        $intent = 'order_place';
    }

    // ============================================================
    // STEP 2: LIVE DB QUERY — build response from database
    // ============================================================
    $result        = buildResponse($intent, $message, $user_id, $conn, $ctx);
    $response      = $result['response'];
    $quick_replies = $result['quick_replies'];
    $usedGemini    = false;
    $responseSource = ($mlOnline && $confidence >= 0.35) ? 'SVM + PHP + MySQL' : 'PHP + MySQL';

    // ============================================================
    // STEP 3: GEMINI FALLBACK — for complex/multilingual queries
    // Triggers when: low ML confidence OR no DB results found
    // ============================================================
    $noDbResult = (
        strpos($response, "I didn't quite understand") !== false ||
        strpos($response, "I couldn't find products") !== false ||
        strpos($response, "I'm not sure I understood") !== false
    );

    $lowConfidence = ($confidence < 0.55 && !isLocalCommerceIntent($intent) && !in_array($intent, [
        'greeting','goodbye','thanks','acknowledgement'
    ], true));

    $geminiSafeIntent = !isLocalCommerceIntent($intent);

    if (($noDbResult && $geminiSafeIntent) || $lowConfidence) {
        $geminiReply = askGeminiForQuery($message, $conn, $user_id ? (int)$user_id : null, $ctx);
        if ($geminiReply) {
            $response      = "🤖 " . nl2br(htmlspecialchars($geminiReply));
            $quick_replies = [];
            $usedGemini    = true;
            $responseSource = 'Gemini fallback';
        }
    }

    // ── Update context memory ──
    $detectedCat    = detectCategory($message);
    $detectedBudget = parsePriceFilter($message);
    if ($detectedCat)    { $ctx['last_category'] = $detectedCat; $ctx['page_offset'] = 0; }
    if ($detectedBudget) { $ctx['last_budget']   = $detectedBudget; $ctx['page_offset'] = 0; }
    $ctx['last_intent'] = $intent;

    // ── Save to chatbot_logs ──
    $ctx['last_user_message'] = $message;
    $ctx['last_bot_response'] = strip_tags($response);
    unset($ctx['customer']);
    saveChatMemory($conn, $user_id ? (int)$user_id : null, $session_id, $ctx);

    $sm   = $conn->real_escape_string($message);
    $sr   = $conn->real_escape_string($response);
    $sid  = $conn->real_escape_string($session_id);
    $ui   = $user_id ? (int)$user_id : 'NULL';
    $guest = $user_id ? 0 : 1;
    $conn->query("INSERT INTO chatbot_logs (user_id, session_id, is_guest, message, response, sentiment_score, sentiment_label)
                  VALUES ($ui, '$sid', $guest, '$sm', '$sr', 0.5, 'neutral')");
    $log_id = (int)$conn->insert_id;

    $responseProducts = $result['products'] ?? [];
    echo json_encode([
        'response'      => $response,
        'quick_replies' => $quick_replies,
        'session_id'    => $session_id,
        'log_id'        => $log_id,
        'intent'        => $intent,
        'confidence'    => $confidence,
        'response_source' => $responseSource,
        'used_gemini'   => $usedGemini,
        'ml_online'     => $mlOnline,
        'authenticated' => (bool)$user_id,
        'user_type'     => $user_id ? 'authenticated' : 'guest',
        'products'      => $responseProducts,
    ]);

} catch (Throwable $e) {
    error_log('CHATBOT_SIMPLE ERROR: ' . $e->getMessage() . ' | ' . $e->getFile() . ':' . $e->getLine());
    // Even on crash — try to show products
    $fallback = tryShowProducts($conn ?? null);
    echo json_encode([
        'response'      => $fallback['response'],
        'quick_replies' => $fallback['quick_replies'],
        'session_id'    => $session_id ?? '',
        'log_id'        => 0,
        'intent'        => 'error',
        'confidence'    => 0,
    ]);
}
exit;

// ============================================================
// INTENT DETECTION FALLBACK (when Flask is down)
// ============================================================
function detectIntentFallback(string $msg): string {
    $ml = strtolower($msg);
    if (preg_match('/^\s*(ok|okay|k|yes|yeah|yep|sure|alright|fine|got it|noted|yego|oui)\s*[!.?]*\s*$/i', $msg)) return 'acknowledgement';
    if (preg_match('/\b(how\s+(can|do)\s+i\s+(buy|purchase|order|place an order)|how\s+to\s+(buy|purchase|order|checkout)|place\s+(an\s+)?order|make\s+(an\s+)?order|complete\s+(my\s+)?purchase|checkout)\b/i', $ml)) return 'order_place';
    if (extractOrderReference($msg) || preg_match('/\b(track|order status|where is my order|my order|my orders|order number|commande|commande num|status.*order|order.*status)\b/i', $ml)) return 'order_track';
    if (preg_match('/\b(rate|rating|feedback)\b.*\b(chat|bot|chatbot|answer|response)\b|\b(chat|bot|chatbot|answer|response)\b.*\b(rate|rating|feedback)\b/i', $ml)) return 'chatbot_rating';
    if (preg_match('/\b(rating|ratings|review|reviews|stars?|rated|customer feedback)\b/i', $ml)) return 'product_rating';
    if (preg_match('/\b(compare|comparison|versus|vs\.?)\b/i', $ml)) return 'product_compare';
    if (preg_match('/\b(forgot.*password|reset password|change password|password reset|recover account|can.?t login|cannot login|login problem)\b/i', $ml)) return 'account_help';
    if (preg_match('/\b(under|below|less than|within|budget|afford|up to|max|maximum|between|from|range|over|above|more than|at least|minimum|about|around|approximately|approx|roughly)\b/i', $ml) && preg_match('/\d/', $ml)) return 'budget_search';
    if (preg_match('/\b(any|some|something|anything|what do you have|got anything|show me|find me)\b.*\b(products?|items?|things?|options?|choices?)\b/i', $ml) && preg_match('/\d/', $ml)) return 'budget_search';
    if (preg_match('/\b(my budget|i have|i can spend|i want to spend)\b.*\d/i', $ml)) return 'budget_search';
    if (preg_match('/\b(how much|price of|cost of|what.*price|price.*what|tell me the price)\b/i', $ml)) return 'product_price';
    if (preg_match('/\b(in stock|available|availability|stock of|can i buy)\b/i', $ml)) return 'stock_check';
    if (preg_match('/\b(browse categories|show categories|all categories|what categories|list categories)\b/i', $ml)) return 'category_search';
    if (preg_match('/\b(show me|browse|list|all|display)\b.*\b(phones?|laptops?|fashion|groceries|health|sports?|baby|furniture|tv|audio|jewelry|gaming|books?|car|appliance)\b/i', $ml)) return 'category_search';
    if (preg_match('/\b(show me products|all products|browse products|show products|view products)\b/i', $ml)) return 'product_search';
    if (preg_match('/\b(show me|find|i want|looking for|do you have|get me|search|i need|buy|purchase)\b/i', $ml)) return 'product_search';
    // Brand search — detect known brand names (handles Kinyarwanda like "mpayitza ya tecno")
    $brands = ['samsung','apple','hp','dell','lenovo','asus','acer','lg','sony','hisense','tecno','infinix','xiaomi','huawei','oppo','vivo','itel','nike','adidas','casio','pampers','johnson','lego','graco','motorola','nestle','colgate','ariel','dettol','heinz','pringles','coca-cola','nivea','neutrogena','garnier','dove','oral-b','centrum','vaseline','gillette','maybelline','indomie','lipton','inyange','akabanga','kimbo','philips','ramtons','bruhm','dyson','midea','kenwood','panasonic','jbl','bose','logitech','clarks','levis','zara','converse','fossil'];
    foreach ($brands as $b) { if (stripos($ml, $b) !== false) return 'brand_search'; }
    if (preg_match('/^(hi|hello|hey|good morning|good afternoon|good evening|muraho|bonjour|salut)\b/i', $ml)) return 'greeting';
    if (preg_match('/\b(delivery|shipping|how long|when will|arrive|dispatch)\b/i', $ml)) return 'delivery_time';
    if (preg_match('/\b(payment|pay|momo|airtel|cash|card|visa|mastercard)\b/i', $ml)) return 'payment_methods';
    if (preg_match('/\b(return|refund|exchange|money back|send back)\b/i', $ml)) return 'return_policy';
    if (preg_match('/\b(warranty|guarantee|broken|defective)\b/i', $ml)) return 'warranty';
    if (preg_match('/\b(help|support|contact|problem|issue|complaint)\b/i', $ml)) return 'contact_support';
    if (preg_match('/\b(thank|thanks|merci|murakoze)\b/i', $ml)) return 'thanks';
    if (preg_match('/\b(bye|goodbye|see you|au revoir)\b/i', $ml)) return 'goodbye';
    return 'product_search';
}

function isLocalCommerceIntent(string $intent): bool {
    return in_array($intent, [
        'budget_search', 'product_search', 'category_search', 'brand_search',
        'product_price', 'stock_check', 'product_rating', 'product_compare',
        'order_track', 'order_history', 'order_place', 'delivery_time', 'shipping_fee',
        'payment_methods', 'return_policy', 'warranty', 'contact_support',
        'support_ticket', 'chatbot_rating', 'account_help', 'place_order',
        'guest_order_guide', 'return_request'
    ], true);
}

// ============================================================
// BUDGET AMOUNT PARSER
// ============================================================
function parseMoneyAmount(string $number, string $suffix = ''): int {
    $amount = (float)str_replace(',', '', $number);
    $suffix = strtolower(trim($suffix));

    if (in_array($suffix, ['k', 'thousand', 'ibihumbi'], true)) {
        $amount *= 1000;
    } elseif (in_array($suffix, ['m', 'million', 'millions', 'milio', 'miliyoni'], true)) {
        $amount *= 1000000;
    } elseif ($amount > 0 && $amount < 1000 && $suffix === '') {
        $amount *= 1000;
    }

    return max(0, (int)round($amount));
}

function parsePriceFilter(string $text): ?array {
    $normalized = strtolower(str_replace(["\xc2\xa0", '–', '—'], [' ', '-', '-'], trim($text)));
    $num = '(\d[\d,]*(?:\.\d+)?)\s*(k|m|million|millions|milio|miliyoni|thousand|ibihumbi)?';

    if (preg_match('/(?:between|from|range|price range|entre|kuva|hagati(?:\s+ya)?)\s*(?:rwf|frw|amafaranga|francs?)?\s*' . $num . '\s*(?:and|to|-|et|na)\s*(?:rwf|frw|amafaranga|francs?)?\s*' . $num . '/iu', $normalized, $m)) {
        $a = parseMoneyAmount($m[1], $m[2] ?? '');
        $b = parseMoneyAmount($m[3], $m[4] ?? '');
        if ($a > 0 && $b > 0) {
            $min = min($a, $b);
            $max = max($a, $b);
            return [
                'min' => $min,
                'max' => $max,
                'exact' => false,
                'label' => 'between RWF ' . number_format($min) . ' and RWF ' . number_format($max),
            ];
        }
    }

    if (preg_match('/(?:under|below|less than|within|budget|up to|max|maximum|at most|cheaper than|moins de|jusqu|munsi ya|ntarenze|atarengeje|my budget is|i have|i can spend)\s*(?:rwf|frw|amafaranga|francs?)?\s*' . $num . '/iu', $normalized, $m)
        || preg_match('/(?:rwf|frw|amafaranga|francs?)?\s*' . $num . '\s*(?:or less|and below|max|maximum|budget)/iu', $normalized, $m)) {
        $max = parseMoneyAmount($m[1], $m[2] ?? '');
        if ($max > 0) {
            return [
                'min' => null,
                'max' => $max,
                'exact' => false,
                'label' => 'under RWF ' . number_format($max),
            ];
        }
    }

    if (preg_match('/(?:over|above|more than|at least|minimum|min|starting from|greater than|arenze)\s*(?:rwf|frw|amafaranga|francs?)?\s*' . $num . '/iu', $normalized, $m)) {
        $min = parseMoneyAmount($m[1], $m[2] ?? '');
        if ($min > 0) {
            return [
                'min' => $min,
                'max' => null,
                'exact' => false,
                'label' => 'from RWF ' . number_format($min),
            ];
        }
    }

    if (preg_match('/(?:exactly|exact price|price of|cost of|igiciro\s*cya|of\s*rwf|of\s*frw|yamafrw|ya\s*frw|ya\s*rwf)\s*(?:rwf|frw|amafaranga|francs?)?\s*' . $num . '/iu', $normalized, $m)) {
        $amount = parseMoneyAmount($m[1], $m[2] ?? '');
        if ($amount > 0) {
            return [
                'min' => $amount,
                'max' => $amount,
                'exact' => true,
                'label' => 'at exactly RWF ' . number_format($amount),
            ];
        }
    }

    return null;
}

function priceFilterLabel($priceFilter, string $qualifier = 'none'): string {
    $price = normalizePriceFilter($priceFilter, false, $qualifier);
    if (!$price) return '';
    if (!empty($price['label'])) return (string)$price['label'];
    return buildPriceLabel($price['min'], $price['max'], !empty($price['exact']), $qualifier);
}

// BUDGET AMOUNT PARSER
// Returns: ['amount' => int, 'exact' => bool]
// exact=true when customer says "of RWF X" (yamafrw, igiciro, exactly)
// exact=false when customer says "under/below X"
// ============================================================
function parseBudgetFull(string $text): ?array {
    $filter = parsePriceFilter($text);
    if ($filter && !empty($filter['max'])) {
        return ['amount' => (int)$filter['max'], 'exact' => !empty($filter['exact'])];
    }

    $t = strtolower($text);

    // Detect if this is an EXACT price request (yamafrw, igiciro cya, exactly, of RWF)
    $isExact = preg_match('/\b(yamafrw|ya\s*frw|ya\s*rwf|igiciro\s*cya|exactly|of\s*rwf|of\s*frw|amafaranga\s*y|coûte|coute|vaut|prix\s*de)\b/i', $t);

    // Extract the number
    $amount = null;
    if (preg_match('/(\d[\d,]*)\s*k\b/', $t, $m)) {
        $amount = (int)str_replace(',', '', $m[1]) * 1000;
    } elseif (preg_match('/(\d[\d,]+)/', $t, $m)) {
        $val = (int)str_replace(',', '', $m[1]);
        // Require 5+ digits for unqualified numbers to avoid
        // matching years/model numbers like "2024" in product names.
        // Numbers with qualifiers (under/below/for) are caught by parsePriceFilter.
        if ($val >= 10000) $amount = $val;
    } elseif (preg_match('/\b(\d{4,})\b/', $t, $m)) {
        $amount = (int)$m[1];
    }

    if (!$amount) return null;
    return ['amount' => $amount, 'exact' => (bool)$isExact];
}

// ============================================================
// ORDER MEMORY + LIVE ACCOUNT ORDER LOOKUP
// ============================================================
function extractOrderReference(string $message): ?int {
    $m = trim($message);

    if (preg_match('/#\s*0*(\d{1,10})\b/i', $m, $match)) {
        return max(1, (int)$match[1]);
    }

    if (preg_match('/\b(?:order|commande|cmd|invoice|facture)\s*(?:number|no\.?|num(?:ber)?|id)?\s*#?\s*0*(\d{1,10})\b/i', $m, $match)) {
        return max(1, (int)$match[1]);
    }

    return null;
}

function isOrderConversation(string $message, array $ctx = []): bool {
    $ml = strtolower($message);
    if (extractOrderReference($message)) return true;
    if (preg_match('/\b(order|orders|commande|commandes|tracking|track|status|shipped|delivered|cancelled|processing|pending|invoice|facture|receipt|delivery status|where is|when will|arrive)\b/i', $ml)) {
        return true;
    }
    if (!empty($ctx['last_order_id']) && preg_match('/\b(it|this|that|there|arrive|coming|delivered|shipped|status|where|when)\b/i', $ml)) {
        return true;
    }
    return false;
}

function getOrderWithItems($conn, int $userId, ?int $orderId = null): ?array {
    $where = "o.user_id=" . (int)$userId;
    if ($orderId) $where .= " AND o.id=" . (int)$orderId;

    $orderRes = $conn->query("SELECT o.*, u.name AS customer_name, u.email AS customer_email
                              FROM orders o
                              JOIN users u ON u.id=o.user_id
                              WHERE $where
                              ORDER BY o.created_at DESC, o.id DESC
                              LIMIT 1");
    if (!$orderRes || $orderRes->num_rows === 0) return null;

    $order = $orderRes->fetch_assoc();
    $oid = (int)$order['id'];
    $items = [];
    $itemsRes = $conn->query("SELECT oi.quantity, oi.price, p.name, p.image
                              FROM order_items oi
                              JOIN products p ON p.id=oi.product_id
                              WHERE oi.order_id=$oid
                              ORDER BY oi.id ASC");
    if ($itemsRes) {
        while ($item = $itemsRes->fetch_assoc()) $items[] = $item;
    }
    $order['items'] = $items;
    return $order;
}

function formatOrderNumber(int $orderId): string {
    return '#' . str_pad((string)$orderId, 6, '0', STR_PAD_LEFT);
}

function estimateDeliveryText(array $order): string {
    $created = strtotime($order['created_at'] ?? 'now') ?: time();
    $province = strtolower((string)($order['province'] ?? ''));
    $days = strpos($province, 'kigali') !== false ? 2 : 4;
    $eta = date('d M Y', strtotime("+$days days", $created));

    if (($order['status'] ?? '') === 'delivered') return 'Delivered';
    if (($order['status'] ?? '') === 'cancelled') return 'No delivery scheduled because the order is cancelled';
    return "Estimated by $eta";
}

function formatOrderDetails(array $order): array {
    $orderId = (int)$order['id'];
    $orderNo = formatOrderNumber($orderId);
    $status = strtolower($order['status'] ?? 'pending');
    $statusLabel = ucfirst($status);
    $statusHints = [
        'pending' => 'We received it and it is waiting for confirmation.',
        'processing' => 'Our team is preparing the items.',
        'shipped' => 'It has left the store and is on the way.',
        'delivered' => 'It has been delivered.',
        'cancelled' => 'This order was cancelled.',
    ];

    $itemsText = '';
    foreach (array_slice($order['items'] ?? [], 0, 4) as $item) {
        $itemsText .= "• " . htmlspecialchars($item['name']) . " x" . (int)$item['quantity'] . " - RWF " . number_format((float)$item['price'] * (int)$item['quantity']) . "<br>";
    }
    if (count($order['items'] ?? []) > 4) {
        $itemsText .= "• +" . (count($order['items']) - 4) . " more item(s)<br>";
    }
    if ($itemsText === '') $itemsText = 'Items are saved on the order details page.<br>';

    $address = trim((string)($order['address'] ?? ''));
    $created = !empty($order['created_at']) ? date('d M Y, H:i', strtotime($order['created_at'])) : 'Recently';
    $payment = strtoupper((string)($order['payment_method'] ?? 'cod'));
    $detailUrl = SITE_URL . "/order_detail.php?id=$orderId";

    $response = "📦 <strong>Order $orderNo</strong><br>"
        . "Status: <strong>$statusLabel</strong> - " . ($statusHints[$status] ?? 'Status updated.') . "<br>"
        . "Placed: <strong>$created</strong><br>"
        . "Total: <strong>RWF " . number_format((float)$order['total_price']) . "</strong><br>"
        . "Payment: <strong>$payment</strong><br>"
        . "Delivery: <strong>" . estimateDeliveryText($order) . "</strong><br>";

    if ($address !== '') {
        $response .= "Address: " . htmlspecialchars($address) . "<br>";
    }

    $response .= "<br><strong>Items:</strong><br>$itemsText"
        . "<br><a href='$detailUrl'><strong>View full order details →</strong></a>";

    return [
        'response' => $response,
        'quick_replies' => ['Where is my order?', 'Delivery info', 'My orders', 'Contact support'],
    ];
}

function formatRecentOrders($conn, int $userId): array {
    $res = $conn->query("SELECT id, total_price, status, created_at FROM orders WHERE user_id=$userId ORDER BY created_at DESC, id DESC LIMIT 5");
    if (!$res || $res->num_rows === 0) {
        return [
            'response' => "📦 I do not see any orders on your account yet. When you place an order, I will be able to track it here.",
            'quick_replies' => ['Show me products', 'Browse categories', 'Contact support'],
        ];
    }

    $out = "📦 <strong>Your recent orders:</strong><br><br>";
    $quick = [];
    while ($o = $res->fetch_assoc()) {
        $id = (int)$o['id'];
        $orderNo = formatOrderNumber($id);
        $out .= "• <strong>$orderNo</strong> - " . ucfirst($o['status']) . " - RWF " . number_format((float)$o['total_price']) . " - " . date('d M Y', strtotime($o['created_at'])) . "<br>";
        $quick[] = $orderNo;
    }
    $out .= "<br>Send an order number like <strong>#000002</strong> and I will show its status, items, delivery estimate, and payment method.";

    return [
        'response' => $out,
        'quick_replies' => array_slice(array_merge($quick, ['Delivery info', 'Contact support']), 0, 5),
    ];
}

function defaultChatMemory(): array {
    return [
        'last_category' => null,
        'last_budget' => null,
        'last_products' => [],
        'last_intent' => null,
        'last_order_id' => null,
        'last_user_message' => null,
        'last_bot_response' => null,
        'page_offset' => 0,
        'conversation_state' => null,
        'conversation_data' => [],
    ];
}

/**
 * Guided selling state machine.
 * Walks the customer through: category → budget → features → recommendations
 */
function handleGuidedSelling(string $message, $conn, array &$ctx): ?array {
    $ml = strtolower(trim($message));
    $state = $ctx['conversation_state'] ?? null;
    $data  = $ctx['conversation_data'] ?? [];

    // Detect buying intent to START the flow
    $buyingIntent = preg_match('/\b(help me find|help me choose|recommend|suggest|advise|what.*good|what.*best|i want to buy|i need to buy|i am looking|guide me|ndashaka|nshaka|mfasha)\b/i', $ml);
    if (!$state && $buyingIntent && !detectCategory($message) && !parsePriceFilter($message)) {
        $ctx['conversation_state'] = 'awaiting_category';
        return [
            'response' => "🛍️ I'd love to help you find the perfect product!<br><br>What category are you interested in? We have:<br>• 📱 Phones & Tablets<br>• 💻 Laptops & Computers<br>• 📺 TVs & Audio<br>• 🏠 Home Appliances<br>• 👔 Men's Fashion<br>• 👗 Women's Fashion<br>• 🛒 Groceries & Food<br>• 💄 Beauty & Health<br>• ⚽ Sports & Fitness<br>• 🧸 Baby & Kids<br>• 🛋️ Furniture<br>• 🚗 Car Accessories<br>• 📚 Books & Stationery<br>• ⌚ Watches & Jewelry<br>• 🎮 Gaming",
            'quick_replies' => ['Phones', 'Laptops', 'Fashion', 'Groceries', 'Skip'],
        ];
    }

    if ($state === 'awaiting_category') {
        $cat = detectCategory($message);
        if ($cat) {
            $ctx['last_category'] = $cat;
            $ctx['conversation_state'] = 'awaiting_budget';
            return [
                'response' => "Great choice! <strong>$cat</strong> it is. 🎉<br><br>What's your budget? (e.g., under 100k, between 50k and 200k, around 300k)",
                'quick_replies' => ['Under 100k', 'Under 300k', 'Under 500k', 'No budget limit'],
            ];
        }
        // Check for skip
        if (preg_match('/\b(skip|any|all|everything|show all|anything)\b/i', $ml)) {
            $ctx['conversation_state'] = 'complete';
            $products = queryProducts($conn, null, null, null, 8);
            if (!empty($products)) {
                $ctx['conversation_state'] = null;
                return formatProductList($products, 'Recommended Products for You');
            }
        }
        return [
            'response' => "Please pick a category from the list above, or type one! 😊",
            'quick_replies' => ['Phones', 'Laptops', 'Fashion', 'Groceries', 'Skip'],
        ];
    }

    if ($state === 'awaiting_budget') {
        $budget = parsePriceFilter($message);
        if ($budget) {
            $ctx['last_budget'] = $budget;
            $ctx['conversation_state'] = 'complete';
            $cat = $ctx['last_category'] ?? null;
            $products = queryProducts($conn, $cat, $budget, null, 8);
            if (!empty($products)) {
                $ctx['conversation_state'] = null;
                $title = $cat ? "$cat under " . priceFilterLabel($budget) : "Products " . priceFilterLabel($budget);
                return formatProductList($products, $title);
            }
            return [
                'response' => "I couldn't find products matching <strong>$cat</strong> " . priceFilterLabel($budget) . ". Try a different budget or category?",
                'quick_replies' => ['Change budget', 'Change category', 'Show me all products'],
            ];
        }
        // No budget detected — ask again
        return [
            'response' => "What's your budget? For example: <em>under 200k</em>, <em>between 50k and 150k</em>, <em>around 100k</em>",
            'quick_replies' => ['Under 100k', 'Under 300k', 'No budget limit'],
        ];
    }

    // Complete or unknown state — reset
    $ctx['conversation_state'] = null;
    return null;
}

function memoryOwnerKey(?int $userId, string $sessionId): string {
    return $userId ? 'user:' . $userId : 'guest:' . preg_replace('/[^a-f0-9]/i', '', $sessionId);
}

function loadChatMemory($conn, ?int $userId, string $sessionId): array {
    $memory = defaultChatMemory();
    $ownerKey = $conn->real_escape_string(memoryOwnerKey($userId, $sessionId));
    $res = $conn->query("SELECT memory_json FROM chatbot_memory WHERE owner_key='$ownerKey' LIMIT 1");

    if ($res && $row = $res->fetch_assoc()) {
        $saved = json_decode($row['memory_json'], true);
        if (is_array($saved)) $memory = array_merge($memory, $saved);
    }

    if ($userId && empty($memory['last_order_id'])) {
        $order = getOrderWithItems($conn, (int)$userId, null);
        if ($order) $memory['last_order_id'] = (int)$order['id'];
    }

    return $memory;
}

function saveChatMemory($conn, ?int $userId, string $sessionId, array $memory): void {
    $ownerKey = $conn->real_escape_string(memoryOwnerKey($userId, $sessionId));
    $safeSession = $conn->real_escape_string(preg_replace('/[^a-f0-9]/i', '', $sessionId));
    $safeJson = $conn->real_escape_string(json_encode($memory, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    $ui = $userId ? (int)$userId : 'NULL';

    $conn->query("INSERT INTO chatbot_memory (owner_key, user_id, session_id, memory_json)
                  VALUES ('$ownerKey', $ui, '$safeSession', '$safeJson')
                  ON DUPLICATE KEY UPDATE user_id=VALUES(user_id), session_id=VALUES(session_id), memory_json=VALUES(memory_json)");
}

function getCustomerProfile($conn, ?int $userId): ?array {
    if (!$userId) return null;
    $uid = (int)$userId;
    $res = $conn->query("SELECT id, name, email FROM users WHERE id=$uid LIMIT 1");
    return ($res && $res->num_rows) ? $res->fetch_assoc() : null;
}

// Keep backward-compatible wrapper
function parseBudget(string $text): ?int {
    $result = parseBudgetFull($text);
    return $result ? $result['amount'] : null;
}

// ============================================================
// CATEGORY DETECTOR — maps to exact DB category names
// ============================================================
function detectCategory(string $msg): ?string {
    $ml = strtolower($msg);
    // Order matters: more specific patterns first
    $map = [
        // Smartphones & Tablets
        'phone|smartphone|mobile|iphone|samsung.*phone|tecno|infinix|xiaomi|huawei|oppo|vivo|itel|tablet|ipad' => 'Smartphones & Tablets',
        // Laptops & Computers
        'laptop|computer|pc|dell|hp.*laptop|lenovo|asus|acer|macbook|desktop|monitor|keyboard|mouse|chromebook' => 'Laptops & Computers',
        // TV & Audio
        'tv|television|speaker|headphone|audio|sound|bose|jbl|hisense.*tv|lg.*tv|sony.*tv|samsung.*tv|earphone|earbuds|subwoofer|home theater' => 'TV & Audio',
        // Home Appliances
        'fridge|refrigerator|washing machine|microwave|cooker|air conditioner|vacuum|kettle|blender|appliance|heater|mixer|iron|toaster|dishwasher|water dispenser' => 'Home Appliances',
        // Fashion Men (check before generic fashion)
        'men.*shirt|men.*shoe|men.*suit|men.*trouser|men.*polo|men.*jacket|men.*fashion|men.*cloth|men.*jeans|men.*belt|men.*sneaker|men.*sandal|men.*hoodie|men.*blazer' => 'Fashion Men',
        // Fashion Women (check before generic fashion)
        'women.*dress|women.*shoe|women.*blouse|women.*skirt|women.*fashion|women.*cloth|ladies|female.*fashion|women.*handbag|women.*heels|women.*ankara|women.*top' => 'Fashion Women',
        // Generic fashion fallback — try to detect gender from context
        'shirt|pants|dress|blazer|clothing|clothes|jeans|suit|hoodie|polo|belt|backpack|handbag|sneaker|sandal|perfume|ankara|fashion' => 'Fashion Men',
        // Groceries & Food
        'grocery|groceries|food|milk|rice|oil|snack|beverage|drink|noodle|coffee|tea|sugar|flour|ketchup|yogurt|detergent|soap|toothpaste|cereal|pasta|juice|water|biscuit|chocolate' => 'Groceries & Food',
        // Health & Beauty
        'beauty|skincare|haircare|lotion|shampoo|makeup|vitamin|health|medicine|supplement|serum|razor|lipstick|sanitizer|cream|moisturizer|sunscreen|deodorant|cologne' => 'Health & Beauty',
        // Sports & Fitness
        'sport|fitness|gym|yoga|running|football|bicycle|dumbbell|protein|exercise|resistance|jump rope|cycling|treadmill|basketball|tennis|swimming|hiking' => 'Sports & Fitness',
        // Baby & Kids
        'baby|kid|toy|diaper|stroller|children|infant|toddler|lego|puzzle|baby monitor|kids clothing|nursery|feeding bottle|pram|crib' => 'Baby & Kids',
        // Furniture & Decor
        'furniture|bed|table|chair|sofa|decor|cabinet|shelf|mattress|wardrobe|curtain|lamp|mirror|couch|desk|bookshelf|rug|pillow|bedsheet|duvet' => 'Furniture & Decor',
        // Car Accessories
        'car|automotive|vehicle|tyre|tire|car accessory|dashboard|seat cover|car charger|car mat|steering|windshield|car speaker|car camera' => 'Car Accessories',
        // Books & Stationery
        'book|stationery|pen|notebook|pencil|school supply|office supply|calculator|textbook|novel|magazine|diary|folder|stapler|printer' => 'Books & Stationery',
        // Jewelry & Watches
        'jewelry|jewellery|watch|ring|necklace|bracelet|earring|gold|silver|pendant|bangle|anklet|brooch|cufflink' => 'Jewelry & Watches',
        // Gaming & Electronics
        'game|gaming|console|playstation|xbox|nintendo|controller|gaming headset|gpu|graphics card|gaming chair|gaming mouse|gaming keyboard|vr headset' => 'Gaming & Electronics',
    ];
    foreach ($map as $pattern => $cat) {
        if (preg_match('/(' . $pattern . ')/i', $ml)) return $cat;
    }
    return null;
}

// ============================================================
// PRODUCT QUERY
// ============================================================
/**
 * Detect the price qualifier type from user message.
 * Returns one of: 'under', 'over', 'around', 'exact', 'range', 'none'
 */
function detectPriceQualifier(string $message): string {
    $ml = strtolower($message);
    if (preg_match('/\b(under|below|less than|cheaper than|max|maximum|up to|at most|moins de|munsi ya|ntarenze|atarengeje)\b/i', $ml)) return 'under';
    if (preg_match('/\b(over|above|more than|at least|minimum|arenze)\b/i', $ml)) return 'over';
    if (preg_match('/\b(about|around|approximately|approx|roughly|nearly|close to|~)\b/i', $ml)) return 'around';
    if (preg_match('/\b(exactly|exact|precisely|igiciro\s*cya|yamafrw|ya\s*frw|ya\s*rwf|coûte|coute|vaut|prix\s*de)\b/i', $ml)) return 'exact';
    if (preg_match('/\b(between|from.*to|range)\b/i', $ml)) return 'range';
    if (preg_match('/\b(for|at)\s+(?:rwf|frw|amafaranga|francs?)?\s*\d/i', $ml)) return 'exact';
    return 'none';
}

/**
 * Generate a human-friendly price label based on the detected qualifier.
 */
function buildPriceLabel(?int $min, ?int $max, bool $exact, string $qualifier): string {
    if ($min !== null && $max !== null && $min === $max) {
        return 'at RWF ' . number_format($min);
    }
    if ($min !== null && $max !== null) {
        return 'between RWF ' . number_format($min) . ' and RWF ' . number_format($max);
    }
    if ($max !== null) {
        switch ($qualifier) {
            case 'under':   return 'under RWF ' . number_format($max);
            case 'around':  return 'around RWF ' . number_format($max);
            case 'exact':   return 'at RWF ' . number_format($max);
            case 'over':    return 'over RWF ' . number_format($max);
            case 'range':   return 'up to RWF ' . number_format($max);
            default:        return 'up to RWF ' . number_format($max);
        }
    }
    if ($min !== null) {
        return 'from RWF ' . number_format($min);
    }
    return '';
}

function normalizePriceFilter($priceFilter, bool $exactPrice = false, string $qualifier = 'none'): ?array {
    if (is_array($priceFilter)) {
        return [
            'min' => isset($priceFilter['min']) && $priceFilter['min'] !== null ? (int)$priceFilter['min'] : null,
            'max' => isset($priceFilter['max']) && $priceFilter['max'] !== null ? (int)$priceFilter['max'] : null,
            'exact' => !empty($priceFilter['exact']),
            'label' => $priceFilter['label'] ?? null,
        ];
    }

    $amount = (int)$priceFilter;
    if ($amount <= 0) return null;

    return $exactPrice
        ? ['min' => $amount, 'max' => $amount, 'exact' => true, 'label' => buildPriceLabel($amount, $amount, true, $qualifier)]
        : ['min' => null, 'max' => $amount, 'exact' => false, 'label' => buildPriceLabel(null, $amount, false, $qualifier)];
}

function extractProductSearchTerm(string $message): ?string {
    $text = strtolower($message);
    $text = preg_replace('/(?:rwf|frw|amafaranga|francs?)?\s*(?<![\p{L}\p{N}])\d[\d,]*(?:\.\d+)?\s*(?:k|m|million|millions|milio|miliyoni|thousand|ibihumbi)?(?![\p{L}\p{N}])/iu', ' ', $text);
    $text = preg_replace('/\b(under|below|less than|within|budget|afford|up to|max|maximum|between|from|range|over|above|more than|at least|minimum|about|around|approximately|approx|roughly|my budget is|i have|i can spend|i want to spend|for|with)\b/iu', ' ', $text);
    $text = preg_replace('/[^\p{L}\p{N}\s-]+/u', ' ', $text);

    $stopwords = [
        'show','me','find','search','looking','want','need','please','the','and','or',
        'you','have','get','buy','purchase','can','how','much','price','cost','what',
        'which','available','availability','stock','products','product','items','item',
        'cheap','affordable','best','top','popular','recommend','suggest','give','tell',
        'see','well','image','photo','picture','this','that','these','those','only',
        'any','some','something','anything','thing','things','option','options','choice','choices',
        'about','around','approximately','approx','roughly',
        'nshaka','ndashaka','shaka','igera','kugeza','amafaranga','amavuta',
        'murakoze','yego','oya','bite','icya','ibindi','nkunda',
        'mfasha','fasha','kigali','rwanda'
    ];
    $genericProductWords = [
        'phone','phones','smartphone','smartphones','mobile','mobiles','tablet','tablets',
        'laptop','laptops','computer','computers','pc','tv','television','speaker',
        'headphone','headphones','audio','fashion','clothes','clothing','shoe','shoes',
        'shirt','shirts','dress','dresses','grocery','groceries','food','beauty','health',
        'sports','sport','baby','kids','furniture','appliance','appliances','book','books',
        'jewelry','jewellery','watch','watches','gaming','electronics'
    ];

    $tokens = preg_split('/\s+/', trim($text));
    $kept = [];
    foreach ($tokens as $token) {
        $token = trim($token);
        if (strlen($token) < 2 || in_array($token, $stopwords, true)) continue;
        $kept[] = $token;
    }

    $specific = array_values(array_filter($kept, fn($token) => !in_array($token, $genericProductWords, true)));
    if (empty($specific)) return null;

    return implode(' ', array_slice($kept, 0, 6));
}

function formatProductRatingList($conn, array $products, string $title): array {
    if (empty($products)) {
        return [
            'response' => "I could not find that product to check ratings. Try the exact product name, e.g. <em>ratings for Samsung Galaxy A54</em>.",
            'quick_replies' => ['Show me products', 'Browse categories'],
        ];
    }

    $out = "⭐ <strong>" . htmlspecialchars($title) . "</strong><br><br>";
    foreach ($products as $p) {
        $pid = (int)$p['id'];
        $stats = ['total' => 0, 'avg_rating' => 0];
        $res = $conn->query("SELECT COUNT(*) AS total, AVG(rating) AS avg_rating FROM reviews WHERE product_id=$pid");
        if ($res && $row = $res->fetch_assoc()) $stats = $row;
        $avg = $stats['avg_rating'] ? round((float)$stats['avg_rating'], 1) : 0;
        $total = (int)($stats['total'] ?? 0);
        $link = SITE_URL . '/product.php?id=' . $pid;
        $out .= "• <a href='$link'><strong>" . htmlspecialchars($p['name']) . "</strong></a> - ";
        $out .= $total > 0 ? "<strong>$avg/5</strong> from $total review(s)" : "No customer reviews yet";
        $out .= "<br>";
    }

    return ['response' => $out, 'quick_replies' => ['Show me products', 'Browse categories']];
}

function extractComparisonTerms(string $message): array {
    $text = strtolower(trim($message));
    $text = preg_replace('/\b(compare|comparison|which is better|what is better|show difference|difference between)\b/i', ' ', $text);
    $parts = preg_split('/\s+(?:vs\.?|versus|and|with|to)\s+/i', $text);
    $terms = [];
    foreach ($parts as $part) {
        $term = extractProductSearchTerm($part);
        if ($term && !in_array($term, $terms, true)) $terms[] = $term;
    }
    return array_slice($terms, 0, 3);
}

function formatProductComparison($conn, string $message): array {
    $terms = extractComparisonTerms($message);
    if (count($terms) < 2) {
        return [
            'response' => "Tell me two products to compare, for example: <em>Compare Samsung Galaxy A14 and Samsung Galaxy A24</em>.",
            'quick_replies' => ['Compare Samsung Galaxy A14 and Samsung Galaxy A24', 'Show me products', 'Browse categories'],
        ];
    }

    $products = [];
    foreach ($terms as $term) {
        $matches = queryProducts($conn, null, null, $term, 1);
        if (!empty($matches)) $products[] = $matches[0];
    }

    if (count($products) < 2) {
        return [
            'response' => "I could not find enough matching products to compare. Please use exact product names from the store, for example: <em>Compare Samsung Galaxy A14 and Samsung Galaxy A24</em>.",
            'quick_replies' => ['Compare Samsung Galaxy A14 and Samsung Galaxy A24', 'Show me products', 'Browse categories'],
        ];
    }

    $out = "⚖️ <strong>Product Comparison</strong><br><br>";
    $out .= "<table style='width:100%;font-size:.78rem;border-collapse:collapse'>";
    $out .= "<tr><th style='text-align:left;padding:5px;border-bottom:1px solid rgba(255,255,255,.15)'>Product</th><th style='text-align:left;padding:5px;border-bottom:1px solid rgba(255,255,255,.15)'>Price</th><th style='text-align:left;padding:5px;border-bottom:1px solid rgba(255,255,255,.15)'>Stock</th><th style='text-align:left;padding:5px;border-bottom:1px solid rgba(255,255,255,.15)'>Rating</th></tr>";

    foreach ($products as $p) {
        $pid = (int)$p['id'];
        $stats = ['total' => 0, 'avg_rating' => 0];
        $res = $conn->query("SELECT COUNT(*) AS total, AVG(rating) AS avg_rating FROM reviews WHERE product_id=$pid");
        if ($res && $row = $res->fetch_assoc()) $stats = $row;
        $rating = !empty($stats['avg_rating']) ? round((float)$stats['avg_rating'], 1) . "/5" : "No reviews";
        $link = SITE_URL . '/product.php?id=' . $pid;
        $out .= "<tr>";
        $out .= "<td style='padding:5px;border-bottom:1px solid rgba(255,255,255,.08)'><a href='$link' style='color:#f5a623;text-decoration:none'><strong>" . htmlspecialchars($p['name']) . "</strong></a><br><small>" . htmlspecialchars($p['category'] ?? '') . "</small></td>";
        $out .= "<td style='padding:5px;border-bottom:1px solid rgba(255,255,255,.08)'>RWF " . number_format((float)$p['price']) . "</td>";
        $out .= "<td style='padding:5px;border-bottom:1px solid rgba(255,255,255,.08)'>" . ((int)$p['stock'] > 0 ? "In stock (" . (int)$p['stock'] . ")" : "Out") . "</td>";
        $out .= "<td style='padding:5px;border-bottom:1px solid rgba(255,255,255,.08)'>$rating</td>";
        $out .= "</tr>";
    }
    $out .= "</table>";

    usort($products, fn($a, $b) => (float)$a['price'] <=> (float)$b['price']);
    $out .= "<br><strong>Best budget choice:</strong> " . htmlspecialchars($products[0]['name']) . " at RWF " . number_format((float)$products[0]['price']) . ".";

    return ['response' => $out, 'quick_replies' => ['Show me similar', 'Browse categories', 'Show me products']];
}

/**
 * Query products by exact name match (prioritized over category search).
 * Returns products whose name contains ALL words from the search term.
 */
function queryProductsByName($conn, string $searchTerm, $maxPrice = null, bool $exactPrice = false): array {
    if (!$conn || strlen($searchTerm) < 2) return [];
    $safe = $conn->real_escape_string($searchTerm);
    $words = preg_split('/\s+/', strtolower(trim($searchTerm)));
    $wordClauses = [];
    foreach ($words as $w) {
        $w = trim($w);
        if (strlen($w) < 2) continue;
        $ws = $conn->real_escape_string($w);
        $wordClauses[] = "LOWER(p.name) LIKE '%$ws%'";
    }
    if (empty($wordClauses)) return [];

    $where = ['(' . implode(' AND ', $wordClauses) . ')'];
    $where[] = 'p.stock > 0';

    if ($maxPrice !== null) {
        $p = (int)$maxPrice;
        if ($exactPrice) {
            $low  = (int)($p * 0.90);
            $high = (int)($p * 1.10);
            $where[] = "p.price BETWEEN $low AND $high";
        } else {
            $where[] = "p.price <= $p";
        }
    }

    $sql = "SELECT p.id, p.name, p.brand, p.price, p.stock, p.description, p.image, c.name AS category
            FROM products p LEFT JOIN categories c ON p.category_id = c.id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY p.price ASC LIMIT 5";
    $res = $conn->query($sql);
    $rows = [];
    if ($res) while ($r = $res->fetch_assoc()) $rows[] = $r;
    return $rows;
}

function queryProducts($conn, ?string $catFragment, $maxPrice, ?string $searchTerm, int $limit = 10, int $offset = 0, bool $exactPrice = false): array {
    if (!$conn) return [];
    $where = ['p.stock > 0'];
    $price = normalizePriceFilter($maxPrice, $exactPrice);
    if ($catFragment) {
        $safe = $conn->real_escape_string($catFragment);
        $where[] = "c.name LIKE '%$safe%'";
    }
    if (false) {
        if ($exactPrice) {
            // Exact price: within ±10% of the requested amount
            $low  = (int)($maxPrice * 0.90);
            $high = (int)($maxPrice * 1.10);
            $where[] = "p.price BETWEEN $low AND $high";
        } else {
            $where[] = "p.price <= $maxPrice";
        }
    }
    if ($price) {
        if ($price['min'] !== null && $price['max'] !== null && (int)$price['min'] === (int)$price['max']) {
            $where[] = "p.price = " . (int)$price['min'];
        } else {
            if ($price['min'] !== null) $where[] = "p.price >= " . (int)$price['min'];
            if ($price['max'] !== null) $where[] = "p.price <= " . (int)$price['max'];
        }
    }
    if ($searchTerm && strlen($searchTerm) >= 2) {
        $safe = $conn->real_escape_string($searchTerm);
        $tokenStopwords = ['show','find','search','looking','want','need','please','with','under','below','between','from','range','budget','price','cost','products','product','items','item','rwf','frw','any','some','something','anything','about','around','approximately','approx','roughly','only'];
        $tokens = preg_split('/\s+/', strtolower(preg_replace('/[^\p{L}\p{N}\s-]+/u', ' ', $searchTerm)));
        $tokenClauses = [];
        foreach ($tokens as $token) {
            $token = trim($token);
            if (strlen($token) < 3 || is_numeric($token) || in_array($token, $tokenStopwords, true)) continue;
            $tok = $conn->real_escape_string($token);
            $tokenClauses[] = "(p.name LIKE '%$tok%' OR p.brand LIKE '%$tok%' OR p.description LIKE '%$tok%' OR c.name LIKE '%$tok%')";
        }
        $searchClauses = ["(p.name LIKE '%$safe%' OR p.brand LIKE '%$safe%' OR p.description LIKE '%$safe%')"];
        if (!empty($tokenClauses)) {
            $searchClauses[] = '(' . implode(' AND ', array_slice($tokenClauses, 0, 5)) . ')';
        }
        $where[] = '(' . implode(' OR ', $searchClauses) . ')';
    }
    $orderBy = ($price && !empty($price['exact']) && $price['max'])
        ? "ABS(p.price - " . (int)$price['max'] . ") ASC"
        : "p.price ASC";
    $sql = "SELECT p.id, p.name, p.brand, p.price, p.stock, p.description, p.image, c.name AS category
            FROM products p LEFT JOIN categories c ON p.category_id = c.id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY $orderBy LIMIT $limit OFFSET $offset";
    $res  = $conn->query($sql);
    $rows = [];
    if ($res) while ($r = $res->fetch_assoc()) $rows[] = $r;
    return $rows;
}

// ============================================================
// FORMAT PRODUCT LIST
// ============================================================
function formatProductList(array $products, string $title): array {
    if (empty($products)) {
        return [
            'response'      => "😔 No products found matching your request. Try a different search or browse our categories.",
            'quick_replies' => ['Browse categories', 'Show me products', 'Contact support'],
            'products'      => [],
        ];
    }
    $out = "✅ <strong>$title</strong><br><br>";
    $structured = [];
    foreach ($products as $p) {
        $stock    = (int)$p['stock'] > 0 ? "✅ In Stock ({$p['stock']})" : "❌ Out of Stock";
        $link     = SITE_URL . '/product.php?id=' . (int)$p['id'];
        $imgFile  = !empty($p['image']) ? trim((string)$p['image']) : 'placeholder.jpg';
        $imgUrl   = preg_match('/^https?:\/\//i', $imgFile)
            ? $imgFile
            : SITE_URL . '/assets/images/products/' . $imgFile;

        $out .= "<div class='chat-product-card' data-product-id='{$p['id']}'>";
        $out .= "<a href='$link' class='chat-product-img-link'><img src='$imgUrl' alt='" . htmlspecialchars($p['name']) . "' class='chat-product-img' onerror=\"this.src='" . SITE_URL . "/assets/images/placeholder.jpg'\"></a>";
        $out .= "<div class='chat-product-body'>";
        $out .= "<a href='$link' class='chat-product-name'>" . htmlspecialchars($p['name']) . "</a>";
        if (!empty($p['brand'])) $out .= " <span class='chat-product-brand'>({$p['brand']})</span>";
        $out .= "<div class='chat-product-price'>RWF " . number_format((float)$p['price']) . "</div>";
        $out .= "<span class='chat-product-stock'>" . ((int)$p['stock'] > 0 ? "✅ In Stock ({$p['stock']})" : "❌ Out of Stock") . "</span>";
        if (!empty($p['description'])) {
            $out .= "<div class='chat-product-desc'>" . htmlspecialchars(mb_substr(strip_tags($p['description']), 0, 75)) . "...</div>";
        }
        $out .= "<div class='chat-product-actions'>";
        $out .= "<a href='$link' class='chat-product-btn view-btn'>View Details</a>";
        if ((int)$p['stock'] > 0) {
            $out .= "<a href='" . SITE_URL . "/cart.php?action=add&id=" . (int)$p['id'] . "' class='chat-product-btn cart-btn'>🛒 Add to Cart</a>";
        }
        $out .= "</div></div></div>";

        $structured[] = [
            'id'     => (int)$p['id'],
            'name'   => $p['name'],
            'brand'  => $p['brand'] ?? '',
            'price'  => (float)$p['price'],
            'price_formatted' => 'RWF ' . number_format((float)$p['price']),
            'stock'  => (int)$p['stock'],
            'in_stock' => (int)$p['stock'] > 0,
            'image'  => $imgUrl,
            'description' => strip_tags($p['description'] ?? ''),
        ];
    }
    $qr = array_map(fn($p) => $p['name'], array_slice($products, 0, 3));
    $qr[] = 'Show me more';
    $qr[] = 'Browse categories';
    return ['response' => $out, 'quick_replies' => $qr, 'products' => $structured];
}

// ============================================================
// FALLBACK: TRY TO SHOW PRODUCTS EVEN ON ERROR
// ============================================================
function tryShowProducts($conn): array {
    if (!$conn) {
        return ['response' => '👋 Welcome to ShopAI Rwanda! How can I help you today?', 'quick_replies' => ['Show me products', 'Browse categories', 'Contact support']];
    }
    $res  = $conn->query("SELECT p.id, p.name, p.brand, p.price, p.stock, p.description, c.name AS category FROM products p LEFT JOIN categories c ON p.category_id=c.id WHERE p.stock>0 ORDER BY RAND() LIMIT 8");
    $rows = [];
    if ($res) while ($r = $res->fetch_assoc()) $rows[] = $r;
    return formatProductList($rows, 'Featured Products from ShopAI Rwanda');
}

// ============================================================
// GEMINI API FOR COMPLEX / MULTILINGUAL QUERIES
// Supports: English, French, Kinyarwanda + any language
// ============================================================
function askGeminiForQuery(string $message, $conn, ?int $userId = null, array $ctx = []): ?string {
    $apiKey = defined('GEMINI_API_KEY') ? GEMINI_API_KEY : '';
    if (empty($apiKey) || $apiKey === 'your-gemini-api-key-here') return null;

    // ── Detect language for response instruction ──
    $langHint = 'English';
    if (preg_match('/\b(muraho|mwaramutse|mwiriwe|ndashaka|nshaka|ibicuruzwa|igiciro|bingahe|murakoze|yego|oya|bite|amakuru|kugura|gufata|ibintu|amafaranga|ninde|iki|aho|igihe|uburyo|gusura|gutura|kigali|rwanda)\b/i', $message)) {
        $langHint = 'Kinyarwanda';
    } elseif (preg_match('/\b(bonjour|merci|produit|prix|livraison|commande|paiement|retour|aide|comment|combien|quoi|quel|acheter|vendre|disponible|stock|garantie|remboursement|frais|gratuit|rapide|lent)\b/i', $message)) {
        $langHint = 'French';
    }

    // ── Build rich store context with FULL catalog from live DB ──
    $customerContext = $userId
        ? "AUTHENTICATION: Logged-in customer. Name: " . (($ctx['customer']['name'] ?? '') ?: 'Customer') . ". Use only this customer's saved chat/order context."
        : "AUTHENTICATION: Guest user. Do not provide order/account details; ask them to log in for account-specific help.";
    $lastMessage = trim((string)($ctx['last_user_message'] ?? ''));
    if ($lastMessage !== '') {
        $customerContext .= "\nLAST SAVED CUSTOMER MESSAGE: " . mb_substr($lastMessage, 0, 180);
    }
    $requestedPrice = parsePriceFilter($message);
    $requestedCategory = detectCategory($message);
    $relevantProductLines = [];
    if ($conn && ($requestedPrice || $requestedCategory)) {
        $relevant = queryProducts($conn, $requestedCategory, $requestedPrice, null, 20, 0);
        foreach ($relevant as $p) {
            $relevantProductLines[] = "- [{$p['category']}] {$p['name']} ({$p['brand']}) RWF " . number_format((float)$p['price']) . " stock {$p['stock']}";
        }
    }

    $catalogContext = '';
    if ($conn) {
        // Get all 15 categories with product counts and price ranges
        $catRes = $conn->query("
            SELECT c.name, COUNT(p.id) as total, MIN(p.price) as min_p, MAX(p.price) as max_p
            FROM categories c LEFT JOIN products p ON p.category_id=c.id AND p.stock>0
            GROUP BY c.id, c.name ORDER BY c.name
        ");
        $catLines = [];
        if ($catRes) {
            while ($r = $catRes->fetch_assoc()) {
                $catLines[] = "  - {$r['name']}: {$r['total']} products, RWF " . number_format($r['min_p']) . " – " . number_format($r['max_p']);
            }
        }
        $catalogContext = "FULL CATALOG (15 categories):\n" . implode("\n", $catLines);

        // Get 2 cheapest products per category (efficient query)
        $topRes = $conn->query("
            SELECT p.name, p.brand, p.price, c.name as cat
            FROM products p
            LEFT JOIN categories c ON p.category_id=c.id
            WHERE p.stock > 0
            AND p.id IN (
                SELECT id FROM (
                    SELECT p2.id, p2.category_id,
                           ROW_NUMBER() OVER (PARTITION BY p2.category_id ORDER BY p2.price ASC) as rn
                    FROM products p2 WHERE p2.stock > 0
                ) ranked WHERE rn <= 2
            )
            ORDER BY c.name, p.price ASC
        ");
        $topProducts = [];
        if ($topRes) {
            while ($r = $topRes->fetch_assoc()) {
                $topProducts[] = "  [{$r['cat']}] {$r['name']} ({$r['brand']}) RWF " . number_format($r['price']);
            }
        }
        if (!empty($topProducts)) {
            $catalogContext .= "\n\nCHEAPEST PRODUCTS PER CATEGORY:\n" . implode("\n", $topProducts);
        }
        if (!empty($relevantProductLines)) {
            $catalogContext .= "\n\nSTRICT RELEVANT PRODUCTS FOR THIS QUERY";
            if ($requestedPrice) $catalogContext .= " (" . priceFilterLabel($requestedPrice) . ")";
            $catalogContext .= ":\n" . implode("\n", $relevantProductLines);
        }
    }

    $storeContext = "You are the AI shopping assistant for ShopAI Rwanda (shopai.rw), a professional e-commerce platform.

STORE FACTS:
- 1,161 products across 15 categories
- Currency: RWF (Rwandan Franc)
- Delivery: Kigali 1-2 business days | Other provinces 2-4 business days
- Free shipping on orders above RWF 50,000 | Standard shipping: RWF 2,000
- Payment: Cash on Delivery, MTN Mobile Money, Airtel Money, Bank Transfer, Visa/Mastercard
- Returns: 7 days after delivery (unused, original packaging)
- Warranty: Electronics 1-2 years, Appliances 1-3 years
- Support: ericniringiyimana123@gmail.com | +250782977559 | Mon-Sat 8AM-6PM

$catalogContext

$customerContext

LANGUAGE INSTRUCTION: The customer is writing in $langHint. You MUST respond in $langHint. If Kinyarwanda, use simple clear Kinyarwanda. If French, respond in French. If English, respond in English.

RULES:
1. Answer only the exact thing the customer asked.
2. Do not add menus, extra categories, product suggestions, policies, delivery info, or support details unless the customer specifically asks for them.
3. If the customer greets you, reply with a short greeting only.
4. If the customer asks about one topic, answer that topic only.
5. Keep response under 80 words unless the customer asks for a list.
6. Product, price, stock, and category answers must be grounded in the database context above.
7. If STRICT RELEVANT PRODUCTS are provided, mention ONLY those products and never add products outside the requested price range.
8. If the user is a guest, do not provide order/account details; tell them to log in.
9. Do NOT make up products not in the catalog above.";

    $payload = json_encode([
        'contents' => [[
            'parts' => [[
                'text' => $storeContext . "\n\nCustomer message: " . $message . "\n\nYour response:"
            ]]
        ]],
        'generationConfig' => [
            'temperature'     => 0.3,
            'maxOutputTokens' => 180,
            'topP'            => 0.9,
        ],
        'safetySettings' => [
            ['category' => 'HARM_CATEGORY_HARASSMENT',        'threshold' => 'BLOCK_ONLY_HIGH'],
            ['category' => 'HARM_CATEGORY_HATE_SPEECH',       'threshold' => 'BLOCK_ONLY_HIGH'],
            ['category' => 'HARM_CATEGORY_SEXUALLY_EXPLICIT', 'threshold' => 'BLOCK_ONLY_HIGH'],
            ['category' => 'HARM_CATEGORY_DANGEROUS_CONTENT', 'threshold' => 'BLOCK_ONLY_HIGH'],
        ]
    ]);

    $models = ['gemini-2.5-flash-lite', 'gemini-2.0-flash-lite', 'gemini-2.0-flash'];
    foreach ($models as $model) {
        $ch = curl_init("https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
        ]);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($code === 200) {
            $data = json_decode($resp, true);
            $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? null;
            if ($text && strlen(trim($text)) > 5) return trim($text);
        }
    }
    return null;
}

// ============================================================
// MAIN RESPONSE BUILDER
// ============================================================
function buildResponse(string $intent, string $message, ?int $user_id, $conn, array &$ctx = []): array {
    $ml = strtolower(trim($message));

    if (preg_match('/^\s*(hi|hello|hey|good morning|good afternoon|good evening|muraho|bonjour|salut)\s*[!.?]*\s*$/i', $message)) {
        $customerName = trim((string)($ctx['customer']['name'] ?? ''));
        $helloName = $customerName !== '' ? ' ' . htmlspecialchars($customerName) : '';
        $accountLine = $user_id ? "You're signed in, so I can use your saved chat and order history." : "You're chatting as a guest; log in for order tracking and personalized history.";
        return [
            'response'      => "👋 <strong>Hello$helloName! Welcome to ShopAI Rwanda.</strong><br>$accountLine<br>I can help with products, prices, orders, delivery, payments, returns, warranty, and support.",
            'quick_replies' => ['Show me products', 'Track my order', 'Delivery info', 'Payment methods'],
        ];
    }

    if (preg_match('/^\s*(ok|okay|k|yes|yeah|yep|sure|alright|fine|got it|noted|yego|oui)\s*[!.?]*\s*$/i', $message)) {
        return [
            'response'      => "Okay.",
            'quick_replies' => [],
        ];
    }

    if (preg_match('/\b(what did i ask before|what was my last question|previous question|last thing i asked|ibyo nabajije mbere)\b/i', $message)) {
        $last = trim((string)($ctx['last_user_message'] ?? ''));
        return [
            'response' => $last !== '' ? "You last asked: <strong>" . htmlspecialchars($last) . "</strong>" : "I do not have a previous question saved for this chat yet.",
            'quick_replies' => [],
        ];
    }

    if (preg_match('/\b(who am i|my name|which account|who is chatting)\b/i', $message)) {
        $customerName = trim((string)($ctx['customer']['name'] ?? ''));
        return [
            'response' => $customerName !== '' ? "You are signed in as <strong>" . htmlspecialchars($customerName) . "</strong>." : "You are chatting as a guest.",
            'quick_replies' => [],
        ];
    }

    if ($intent === 'account_help' || preg_match('/\b(forgot.*password|reset password|change password|password reset|recover account|can.?t login|cannot login|login problem)\b/i', $ml)) {
        return [
            'response' => "🔐 <strong>Password help:</strong><br>If you forgot your password, use <a href='" . SITE_URL . "/forgot_password.php'><strong>Forgot Password</strong></a> to receive a reset link. If you remember your password and just want to sign in, go to <a href='" . SITE_URL . "/login.php'><strong>Login</strong></a>.",
            'quick_replies' => ['Login', 'Contact support', 'Show me products'],
        ];
    }

    if ($intent === 'product_compare' || preg_match('/\b(compare|comparison|versus|vs\.?)\b/i', $ml)) {
        return formatProductComparison($conn, $message);
    }

    if (preg_match('/\b(start|request|open|create|make)\b.*\b(return|refund|exchange)\b|\b(return|refund|exchange)\b.*\b(request|item|order|product)\b/i', $ml)) {
        if (!$user_id) {
            return [
                'response' => "↩️ I can guide return and refund requests, but you need to <a href='" . SITE_URL . "/login.php'><strong>login</strong></a> first so we can verify the order belongs to you.",
                'quick_replies' => ['Login', 'Return policy', 'Contact support'],
            ];
        }
        return [
            'response' => "↩️ <strong>Start a return/refund request:</strong><br>1. Open <a href='" . SITE_URL . "/orders.php'><strong>My Orders</strong></a>.<br>2. Choose the order and review the item details.<br>3. Contact support with the order number, reason, and photos if the item is damaged.<br><br>Returns are accepted within <strong>7 days</strong> if the item is unused and in original packaging.",
            'quick_replies' => ['My orders', 'Return policy', 'Contact support'],
        ];
    }

    if ($intent === 'order_place' || preg_match('/\b(how\s+(can|do)\s+i\s+(buy|purchase|order|place an order)|how\s+to\s+(buy|purchase|order|checkout)|place\s+(an\s+)?order|make\s+(an\s+)?order|complete\s+(my\s+)?purchase|checkout)\b/i', $ml)) {
        $accountStep = $user_id
            ? "You're signed in, so you can go straight to checkout when your cart is ready."
            : "Guests can browse products, but create an account or login before checkout so we can save your delivery details, track your order, and protect your purchase history.";
        return [
            'response' => "🛒 <strong>How to place an order:</strong><br>1. Search for the product you want.<br>2. Open the product and click <strong>Add to Cart</strong>.<br>3. Go to your cart and click <strong>Checkout</strong>.<br>4. Add delivery details, choose payment, then confirm.<br><br>$accountStep",
            'quick_replies' => ['Show me products', 'Browse categories', 'Payment methods', 'Delivery info'],
        ];
    }

    $requestedOrderId = extractOrderReference($message);
    if ($requestedOrderId || isOrderConversation($message, $ctx)) {
        if (!$user_id) {
            return [
                'response' => "🔒 I can help with orders, but you are currently chatting as a guest. Please <a href='" . SITE_URL . "/login.php'><strong>login</strong></a>, then send your order number again, e.g. <strong>#000002</strong>.",
                'quick_replies' => ['Login', 'Register', 'Show me products'],
            ];
        }

        if ($requestedOrderId) {
            $order = getOrderWithItems($conn, (int)$user_id, $requestedOrderId);
            if (!$order) {
                return [
                    'response' => "I could not find <strong>" . htmlspecialchars(formatOrderNumber($requestedOrderId)) . "</strong> on your account. For privacy, I can only show orders that belong to the logged-in customer.",
                    'quick_replies' => ['My orders', 'Contact support', 'Show me products'],
                ];
            }
            $ctx['last_order_id'] = (int)$order['id'];
            return formatOrderDetails($order);
        }

        if (!empty($ctx['last_order_id'])) {
            $order = getOrderWithItems($conn, (int)$user_id, (int)$ctx['last_order_id']);
            if ($order) return formatOrderDetails($order);
        }

        if (preg_match('/\b(my orders|orders|recent orders|order history|all orders)\b/i', $ml)) {
            return formatRecentOrders($conn, (int)$user_id);
        }

        if (preg_match('/\b(last|latest|recent|current|my order|where is my order|track my order|status)\b/i', $ml)) {
            $order = getOrderWithItems($conn, (int)$user_id, null);
            if ($order) {
                $ctx['last_order_id'] = (int)$order['id'];
                return formatOrderDetails($order);
            }
        }

        return formatRecentOrders($conn, (int)$user_id);
    }

    // ── Pre-check: exact quick-reply phrases ──
    if (preg_match('/^(show me products|all products|browse products|show products|view products|featured products)$/i', $ml)) {
        $products = queryProducts($conn, null, null, null, 8);
        return formatProductList($products, 'Featured Products from ShopAI Rwanda');
    }
    if (preg_match('/^(show me more|more products|next products|see more)$/i', $ml)) {
        // Use context to paginate — increment offset by 8 each time
        $cat    = $ctx['last_category'] ?? null;
        $budget = $ctx['last_budget'] ?? null;
        $ctx['page_offset'] = ($ctx['page_offset'] ?? 0) + 8;
        $products = queryProducts($conn, $cat, $budget, null, 8, $ctx['page_offset']);
        // If no more results, reset and start from beginning
        if (empty($products)) {
            $ctx['page_offset'] = 0;
            $products = queryProducts($conn, $cat, $budget, null, 8, 0);
        }
        $title = $cat ? "More $cat Products" : "More Products";
        if ($budget) $title .= " " . priceFilterLabel($budget);
        return formatProductList($products, $title);
    }
    if (preg_match('/^(browse categories|show categories|all categories|list categories|view categories)$/i', $ml)) {
        $intent = 'category_search';
    }
    if (preg_match('/^(delivery info|delivery|shipping info|shipping)$/i', $ml)) $intent = 'delivery_time';
    if (preg_match('/^(payment methods?|payment|how to pay)$/i', $ml)) $intent = 'payment_methods';
    if (preg_match('/^(return policy|returns?|refund)$/i', $ml)) $intent = 'return_policy';
    if (preg_match('/^(contact support|support|help)$/i', $ml)) $intent = 'contact_support';
    if (preg_match('/^(track my order|track order|my orders?)$/i', $ml)) $intent = 'order_track';

    // ── Pre-check: budget in message always wins ──
    if (preg_match('/\b(under|below|less than|within|budget|up to|max|between|from|range|over|above|more than|at least|minimum|maximum|i have|i can spend|i want to spend)\b/i', $ml) && preg_match('/\d/', $ml)) {
        $intent = 'budget_search';
    }

    // ── Pre-check: French or Kinyarwanda → route to Gemini immediately ──
    $isKinyarwanda = preg_match('/\b(muraho|mwaramutse|mwiriwe|ndashaka|nshaka|ibicuruzwa|igiciro|bingahe|murakoze|yego|oya|bite|amakuru|kugura|gufata|ibintu|amafaranga|ninde|iki|aho|igihe|uburyo|gusura|gutura)\b/i', $message);
    $isFrench      = preg_match('/\b(bonjour|merci|produit|prix|livraison|commande|paiement|retour|aide|comment|combien|quoi|quel|acheter|vendre|disponible|stock|garantie|remboursement|frais|gratuit|je veux|je cherche|montrez|avez-vous|pouvez-vous)\b/i', $message);

    if (($isKinyarwanda || $isFrench) && $intent !== 'budget_search') {
        // Try to find products first if there's a category/budget
        $cat    = detectCategory($message);
        $budget = parsePriceFilter($message);
        // Fallback: standalone number like "110k" without explicit qualifier
        if (!$budget) {
            $bf = parseBudgetFull($message);
            if ($bf && $bf['amount'] > 0) {
                $budget = [
                    'min'   => null,
                    'max'   => $bf['amount'],
                    'exact' => $bf['exact'],
                    'label' => $bf['exact'] ? 'exactly RWF ' . number_format($bf['amount']) : 'up to RWF ' . number_format($bf['amount']),
                ];
            }
        }
        if ($cat || $budget) {
            $searchTerm = extractProductSearchTerm($message);
            $products = queryProducts($conn, $cat, $budget, $searchTerm, 8);
            if (!empty($products)) {
                $title = $searchTerm ? ucwords($searchTerm) : ($cat ? "$cat Products" : "Products");
                if ($budget) $title .= " " . priceFilterLabel($budget);
                return formatProductList($products, $title);
            }
        }
        // Route to Gemini for multilingual response
        $geminiReply = askGeminiForQuery($message, $conn, $user_id ? (int)$user_id : null, $ctx);
        if ($geminiReply) {
            return [
                'response'      => "🤖 " . nl2br(htmlspecialchars($geminiReply)),
                'quick_replies' => ['Show me products', 'Browse categories', 'Delivery info', 'Contact support'],
            ];
        }
    }

    // ── Guided selling state machine ──
    // If user is in a guided flow OR expresses buying intent, handle it
    $guidedResult = handleGuidedSelling($message, $conn, $ctx);
    if ($guidedResult !== null) {
        return $guidedResult;
    }

    switch ($intent) {

        // ── GREETING ──
        case 'greeting':
        case 'professional_greeting':
            $customerName = trim((string)($ctx['customer']['name'] ?? ''));
            $helloName = $customerName !== '' ? ' ' . htmlspecialchars($customerName) : '';
            $accountLine = $user_id ? "You're signed in, so I can use your saved chat and order history." : "You're chatting as a guest; log in for order tracking and personalized history.";
            return [
                'response'      => "👋 <strong>Hello$helloName! Welcome to ShopAI Rwanda.</strong><br>$accountLine<br>I can help with products, prices, orders, delivery, payments, returns, warranty, and support.",
                'quick_replies' => ['Show me products', 'Track my order', 'Delivery info', 'Payment methods'],
            ];

        case 'acknowledgement':
        case 'affirmation':
            return ['response' => "Okay.", 'quick_replies' => []];

        case 'account_help':
            return [
                'response' => "🔐 <strong>Account help:</strong><br>Use <a href='" . SITE_URL . "/forgot_password.php'><strong>Forgot Password</strong></a> if you cannot sign in. You can also login from <a href='" . SITE_URL . "/login.php'><strong>Login</strong></a> or contact support if the reset email does not arrive.",
                'quick_replies' => ['Login', 'Contact support', 'Show me products'],
            ];

        case 'place_order':
        case 'guest_order_guide':
        case 'order_place':
            $accountStep = $user_id
                ? "You're signed in, so you can checkout and track orders from My Orders."
                : "Guests can browse products, but login or register before checkout so order tracking and invoices work properly.";
            return [
                'response' => "🛒 <strong>How to place an order:</strong><br>1. Search for a product.<br>2. Open the product and click <strong>Add to Cart</strong>.<br>3. Open your cart and click <strong>Checkout</strong>.<br>4. Add delivery details, choose payment, and confirm.<br><br>$accountStep",
                'quick_replies' => ['Show me products', 'Browse categories', 'Payment methods', 'Delivery info'],
            ];

        case 'product_compare':
            return formatProductComparison($conn, $message);

        // ── BUDGET SEARCH ──
        case 'budget_search':
            $strictPriceFilter = parsePriceFilter($message);
            if ($strictPriceFilter) {
                $cat = detectCategory($message);
                $searchTerm = extractProductSearchTerm($message);
                $products = queryProducts($conn, $cat, $strictPriceFilter, $searchTerm, 12, 0);
                $qualifier = detectPriceQualifier($message);
                $title = ($searchTerm ? ucwords($searchTerm) : ($cat ? "$cat" : "Products")) . " " . priceFilterLabel($strictPriceFilter, $qualifier);
                $result = formatProductList($products, $title);
                if (empty($products)) {
                    if ($searchTerm) {
                        $result['response'] = "No <strong>" . htmlspecialchars($searchTerm) . "</strong> products found <strong>" . htmlspecialchars(priceFilterLabel($strictPriceFilter, $qualifier)) . "</strong>.<br>Try a different search or browse all categories.";
                        $result['quick_replies'] = ['Try a higher budget', 'Browse categories'];
                    } else {
                        $result['response'] = "No " . ($cat ? htmlspecialchars($cat) : "products") . " found <strong>" . htmlspecialchars(priceFilterLabel($strictPriceFilter, $qualifier)) . "</strong>.<br>Try a different search or browse all categories.";
                        $result['quick_replies'] = ['Browse categories', 'Show me products', 'Show me phones under 500k'];
                    }
                }
                return $result;
            }
            $budgetFull = parseBudgetFull($message);
            $budget     = $budgetFull ? $budgetFull['amount'] : null;
            $exactPrice = $budgetFull ? $budgetFull['exact'] : false;
            $cat        = detectCategory($message);
            $qualifier  = detectPriceQualifier($message);
            // If user mentioned a specific product name, prioritize that over category
            $searchTerm = extractProductSearchTerm($message);
            if ($searchTerm) {
                // Try exact product name match first
                $exactProducts = queryProductsByName($conn, $searchTerm, $budget, $exactPrice);
                if (!empty($exactProducts)) {
                    $label = buildPriceLabel(null, $budget, $exactPrice, $qualifier);
                    $title = ucwords($searchTerm) . " " . $label;
                    $result = formatProductList($exactProducts, $title);
                    return $result;
                }
            }
            if ($budget && $budget >= 500) {
                $products = queryProducts($conn, $cat, $budget, $searchTerm, 12, 0, $exactPrice);
                $label = buildPriceLabel(null, $budget, $exactPrice, $qualifier);
                $title = ($searchTerm ? ucwords($searchTerm) : ($cat ? "$cat" : "Products")) . " " . $label;
                $result = formatProductList($products, $title);
                if (empty($products)) {
                    if ($exactPrice) {
                        $products = queryProducts($conn, $cat, (int)($budget * 1.20), $searchTerm, 12, 0, false);
                        if (!empty($products)) {
                            $result = formatProductList($products, ($searchTerm ? ucwords($searchTerm) : ($cat ? "$cat" : "Products")) . " closest to RWF " . number_format($budget));
                        } else {
                            $result['response'] = "😔 No " . ($cat ?? "products") . " found at RWF " . number_format($budget) . ". Try browsing categories.";
                            $result['quick_replies'] = ['Browse categories', 'Show me products', 'Contact support'];
                        }
                    } else {
                        $result['response'] = "😔 No products found " . $label . ($cat ? " in $cat" : "") . ".<br>Try a different search or browse all categories.";
                        $result['quick_replies'] = ['Browse categories', 'Show me products', 'Contact support'];
                    }
                }
                return $result;
            }
            // No budget amount found — ask for it
            return [
                'response'      => "💰 What's your budget? Tell me like:<br>• <em>Show me phones under 200k</em><br>• <em>Laptops under 500,000</em><br>• <em>My budget is 100000</em>",
                'quick_replies' => ['Under 50,000', 'Under 100,000', 'Under 200,000', 'Under 500,000'],
            ];

        // ── PRODUCT SEARCH ──
        case 'product_search':
        case 'recommendation':
            // Check if message also has a budget
            $budget = parsePriceFilter($message) ?: parseBudget($message);
            $cat    = detectCategory($message);

            // ── Guard: detect conversational messages that are NOT product queries ──
            // If no category detected AND no budget AND message looks conversational → use Gemini
            $productTriggers = ['show','find','search','looking','want','need','get','buy','purchase','price','stock','available','recommend','suggest','cheap','affordable','best','top','popular'];
            $hasProductTrigger = false;
            foreach ($productTriggers as $trigger) {
                if (stripos($ml, $trigger) !== false) { $hasProductTrigger = true; break; }
            }

            if (!$cat && !$budget && !$hasProductTrigger) {
                // This is a conversational message — route to Gemini
                    $geminiReply = askGeminiForQuery($message, $conn, $user_id ? (int)$user_id : null, $ctx);
                if ($geminiReply) {
                    return ['response' => $geminiReply, 'quick_replies' => ['Show me products', 'Browse categories', 'Contact support']];
                }
                return [
                    'response'      => "Please clarify what you need.",
                    'quick_replies' => [],
                ];
            }

            $searchTerm = extractProductSearchTerm($message);

            $products = queryProducts($conn, $cat, $budget, $searchTerm ?: null, 10);

            // If no results with search term, try category only
            if (empty($products) && $cat && !$searchTerm) {
                $products = queryProducts($conn, $cat, $budget, null, 10);
            }
            // If still no results, ask for clarification — don't dump random products
            if (empty($products)) {
                return [
                    'response'      => "😔 I couldn't find products matching <em>\"" . htmlspecialchars($message) . "\"</em>.<br><br>Try being more specific, e.g.:<br>• <em>Show me Samsung phones</em><br>• <em>Laptops under 500k</em><br>• <em>Browse categories</em>",
                    'quick_replies' => ['Browse categories', 'Show me phones', 'Show me laptops', 'Contact support'],
                ];
            }
            $title = $cat ? "$cat Products" : "Products matching your search";
            if ($budget) $title .= " " . priceFilterLabel($budget);
            return formatProductList($products, $title);

        // ── CATEGORY SEARCH ──
        case 'category_search':
            $cat = detectCategory($message);
            if ($cat) {
                // Show products from that specific category using exact DB name
                $products = queryProducts($conn, $cat, null, null, 10);
                return formatProductList($products, "$cat Products");
            }
            // Show all categories with counts
            $res  = $conn->query("SELECT c.name, COUNT(p.id) as total, MIN(p.price) as min_p, MAX(p.price) as max_p FROM categories c LEFT JOIN products p ON p.category_id=c.id AND p.stock>0 GROUP BY c.id, c.name ORDER BY c.name");
            $out  = "📂 <strong>Browse our 15 categories:</strong><br><br>";
            $qr   = [];
            if ($res) {
                while ($r = $res->fetch_assoc()) {
                    $out .= "• <strong>" . htmlspecialchars($r['name']) . "</strong> — " . $r['total'] . " products";
                    if ($r['min_p']) $out .= " | RWF " . number_format($r['min_p']) . " – " . number_format($r['max_p']);
                    $out .= "<br>";
                    $qr[] = $r['name'];
                }
            }
            return ['response' => $out, 'quick_replies' => array_slice($qr, 0, 5)];

        // ── BRAND SEARCH ──
        case 'brand_search':
            // Extract brand name from message
            $brands = ['samsung','apple','hp','dell','lenovo','asus','acer','lg','sony','hisense','tecno','infinix','xiaomi','huawei','oppo','vivo','itel','nike','adidas','casio','pampers','johnson','lego','graco','motorola','nestle','colgate','ariel','dettol','heinz','pringles','coca-cola','nivea','neutrogena','garnier','dove','oral-b','centrum','vaseline','gillette','maybelline','indomie','lipton','inyange','akabanga','kimbo','philips','ramtons','bruhm','dyson','midea','kenwood','panasonic','jbl','bose','logitech','clarks','levis','zara','converse','fossil'];
            $found = null;
            foreach ($brands as $b) {
                if (stripos($ml, $b) !== false) { $found = $b; break; }
            }
            if ($found) {
                $products = queryProducts($conn, null, null, $found, 10);
                return formatProductList($products, ucfirst($found) . " Products");
            }
            // No brand found — fall back to category/product search
            $cat      = detectCategory($message);
            $products = queryProducts($conn, $cat, null, null, 8);
            if (empty($products)) $products = queryProducts($conn, null, null, null, 8);
            return formatProductList($products, $cat ? "$cat Products" : "Featured Products from ShopAI Rwanda");

        // ── PRICE INQUIRY ──
        case 'product_rating':
            $term = extractProductSearchTerm($message);
            $cat = detectCategory($message);
            $products = $term ? queryProducts($conn, $cat, null, $term, 5) : [];
            if (empty($products) && $cat && !$term) $products = queryProducts($conn, $cat, null, null, 5);
            return formatProductRatingList($conn, $products, $term ? "Ratings for $term" : "Product Ratings");

        case 'product_price':
            $term       = extractProductSearchTerm($message);
            $products   = $term ? queryProducts($conn, null, null, $term, 5) : [];
            if (empty($products)) {
                return ['response' => "Please specify which product you want the price for. Example: <em>price of Samsung Galaxy A54</em>", 'quick_replies' => ['Show me products', 'Browse categories']];
            }
            $out = "💰 <strong>Price Information:</strong><br><br>";
            foreach ($products as $p) {
                $stock = (int)$p['stock'] > 0 ? "✅ In Stock ({$p['stock']} units)" : "❌ Out of Stock";
                $out  .= "• <strong>" . htmlspecialchars($p['name']) . "</strong>";
                if (!empty($p['brand'])) $out .= " <em>({$p['brand']})</em>";
                $out  .= "<br>&nbsp;&nbsp;💵 <strong>RWF " . number_format((float)$p['price']) . "</strong> | $stock<br>";
                if (!empty($p['description'])) $out .= "&nbsp;&nbsp;<small>📝 " . htmlspecialchars(mb_substr(strip_tags($p['description']), 0, 100)) . "</small><br>";
                $out  .= "<br>";
            }
            return ['response' => $out, 'quick_replies' => ['Add to cart', 'Show me similar', 'Browse categories']];

        // ── STOCK CHECK ──
        case 'stock_check':
            $term3      = extractProductSearchTerm($message);
            $products   = $term3 ? queryProducts($conn, null, null, $term3, 5) : [];
            if (empty($products)) {
                return ['response' => "Please specify which product you want to check. Example: <em>Is Samsung Galaxy A54 in stock?</em>", 'quick_replies' => ['Show me products', 'Browse categories']];
            }
            $out = "📦 <strong>Stock Availability:</strong><br><br>";
            foreach ($products as $p) {
                $status = (int)$p['stock'] > 0 ? "✅ <strong>In Stock</strong> — {$p['stock']} units available" : "❌ <strong>Out of Stock</strong>";
                $out   .= "• <strong>" . htmlspecialchars($p['name']) . "</strong> — RWF " . number_format((float)$p['price']) . "<br>&nbsp;&nbsp;$status<br><br>";
            }
            return ['response' => $out, 'quick_replies' => ['Add to cart', 'Notify when in stock', 'Show me similar']];

        // ── ORDER TRACKING ──
        case 'order_track':
        case 'order_history':
            if (!$user_id) {
                return ['response' => "🔒 Please <a href='" . SITE_URL . "/login.php'><strong>login</strong></a> to track your orders.", 'quick_replies' => ['Login', 'Register', 'Show me products']];
            }
            return ['response' => "📦 View your orders here: <a href='" . SITE_URL . "/orders.php'><strong>My Orders →</strong></a>", 'quick_replies' => ['My orders', 'Show me products', 'Contact support']];

        // ── DELIVERY ──
        case 'delivery_time':
        case 'shipping_fee':
            return [
                'response'      => "🚚 <strong>Delivery Information:</strong><br>• <strong>Kigali:</strong> 1–2 business days<br>• <strong>Other provinces:</strong> 2–4 business days<br>• <strong>Free shipping</strong> on orders above RWF 50,000<br>• Standard shipping: <strong>RWF 2,000</strong>",
                'quick_replies' => ['Payment methods', 'Return policy', 'Show me products'],
            ];

        // ── PAYMENT ──
        case 'payment_methods':
            return [
                'response'      => "💳 <strong>Payment Methods:</strong><br>• Cash on Delivery (COD)<br>• MTN Mobile Money<br>• Airtel Money<br>• Bank Transfer<br>• Visa / Mastercard<br><br>All payments are <strong>secure and encrypted</strong>.",
                'quick_replies' => ['Delivery info', 'Return policy', 'Show me products'],
            ];

        // ── RETURN POLICY ──
        case 'return_policy':
            return [
                'response'      => "↩️ <strong>Return & Refund Policy:</strong><br>• <strong>7 days</strong> to return after delivery<br>• Item must be unused and in original packaging<br>• Refunds processed within <strong>3–5 business days</strong><br>• Contact us at <a href='mailto:" . ADMIN_EMAIL . "'>" . ADMIN_EMAIL . "</a>",
                'quick_replies' => ['Delivery info', 'Payment methods', 'Contact support'],
            ];

        // ── WARRANTY ──
        case 'warranty':
            return [
                'response'      => "🛡️ <strong>Warranty Information:</strong><br>• Electronics: <strong>1-year</strong> manufacturer warranty<br>• Appliances: <strong>1–3 years</strong><br>• Fashion & Accessories: <strong>7-day</strong> defect warranty<br>• For claims: email <a href='mailto:" . ADMIN_EMAIL . "'>" . ADMIN_EMAIL . "</a> with order number + photos",
                'quick_replies' => ['Return policy', 'Contact support', 'Show me products'],
            ];

        // ── SUPPORT ──
        case 'contact_support':
        case 'support_ticket':
            $geminiSupport = askGeminiForQuery($message, $conn, $user_id ? (int)$user_id : null, $ctx);
            $supportBase = "📞 <strong>Contact Support:</strong><br>• Email: <a href='mailto:" . ADMIN_EMAIL . "'>" . ADMIN_EMAIL . "</a><br>• Phone: <a href='tel:" . ADMIN_PHONE . "'>" . ADMIN_PHONE . "</a><br>• Hours: Mon–Sat, 8AM–6PM (Kigali time)<br><br>We respond within <strong>2 hours</strong> during business hours.";
            if ($geminiSupport) {
                $supportBase .= "<br><br>🤖 " . nl2br(htmlspecialchars($geminiSupport));
            }
            return [
                'response'      => $supportBase,
                'quick_replies' => ['Show me products', 'Delivery info', 'Return policy'],
            ];

        // ── THANKS ──
        case 'thanks':
        case 'chatbot_rating':
            return ['response' => "😊 You're welcome! Happy to help. Is there anything else I can assist you with?", 'quick_replies' => ['Show me products', 'Browse categories', 'Contact support']];

        // ── GOODBYE ──
        case 'goodbye':
            return ['response' => "👋 Goodbye! Thank you for shopping with <strong>ShopAI Rwanda</strong>. See you soon! 🛍️", 'quick_replies' => ['Show me products', 'Browse categories']];

        // ── STORE INFO ──
        case 'store_info':
        case 'bot_identity':
        case 'platform_info':
            $geminiInfo = askGeminiForQuery($message, $conn, $user_id ? (int)$user_id : null, $ctx);
            $storeBase = "🤖 <strong>About ShopAI Rwanda:</strong><br>We are an AI-powered e-commerce platform with <strong>1,161 products</strong> across <strong>15 categories</strong>.<br><br>📦 Fast delivery | 💳 Multiple payments | ↩️ 7-day returns | 🛡️ Warranty included";
            if ($geminiInfo) {
                $storeBase .= "<br><br>" . nl2br(htmlspecialchars($geminiInfo));
            }
            return [
                'response'      => $storeBase,
                'quick_replies' => ['Show me products', 'Browse categories', 'Delivery info', 'Contact support'],
            ];

        // ── DEFAULT: respond precisely to what was asked ──
        default:
            // Try to find products matching the exact message
            $cat      = detectCategory($message);
            $budget   = parsePriceFilter($message) ?: parseBudget($message);

            // Only search if message has clear product-related keywords
            $productTriggers2 = ['show','find','search','looking','want','need','get','buy','purchase','price','stock','available','recommend','suggest','cheap','affordable','best','top','popular'];
            $hasProductTrigger2 = false;
            foreach ($productTriggers2 as $trigger) {
                if (stripos($ml, $trigger) !== false) { $hasProductTrigger2 = true; break; }
            }

            if (($cat || $budget || $hasProductTrigger2) && strlen($ml) >= 4) {
                $searchTerm = extractProductSearchTerm($message);
                $products   = queryProducts($conn, $cat, $budget, $searchTerm ?: null, 10);
                if (!empty($products)) {
                    $title = $cat ? "$cat Products" : "Products matching your search";
                    if ($budget) $title .= " " . priceFilterLabel($budget);
                    return formatProductList($products, $title);
                }
            }

            // Not a product query — use Gemini for conversational/complex/multilingual queries
            $geminiReply = askGeminiForQuery($message, $conn, $user_id ? (int)$user_id : null, $ctx);
            if ($geminiReply) {
                return [
                    'response'      => "🤖 " . nl2br(htmlspecialchars($geminiReply)),
                    'quick_replies' => ['Show me products', 'Browse categories', 'Contact support'],
                ];
            }

            // Final fallback — ask user to clarify
            return [
                'response'      => "Please clarify what you need.",
                'quick_replies' => [],
            ];
    }
}
