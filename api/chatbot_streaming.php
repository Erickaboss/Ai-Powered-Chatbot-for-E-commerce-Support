<?php
/**
 * Real-Time Streaming Chatbot API
 * Features:
 * - Typing indicators
 * - Progressive response streaming
 * - Optimized ML prediction with caching
 * - Faster Gemini API integration
 */

session_start();

// Enable streaming headers
header('Content-Type: application/x-ndjson; charset=UTF-8');
header('Cache-Control: no-cache');
header('X-Accel-Buffering: no');
ini_set('zlib.output_compression', '0');
ini_set('output_buffering', '0');
ignore_user_abort(true);
ob_implicit_flush(true);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/chatbot_gemini_gate.php';

function emitStreamEvent(array $payload): void {
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";

    if (ob_get_level() > 0) {
        @ob_flush();
    }

    flush();
}

try {
    // Get JSON input
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    $message = trim($data['message'] ?? '');
    $phpSessionId = session_id();
    $session_id = $data['session_id'] ?? $phpSessionId;
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;

    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
    
    if (empty($message)) {
        echo json_encode(['error' => 'Empty message']);
        exit;
    }
    
    $log_id = 0;

    // Send typing indicator immediately
    emitStreamEvent([
        'type' => 'typing',
        'message' => 'Bot is typing...'
    ]);

    // Small delay to show typing (200ms)
    usleep(200000);
    
    // Process the message
    $startTime = microtime(true);
    
    // Predict intent
    $intent = 'unknown';
    $confidence = 0.0;
    $modelUsed = null;
    $mlResult = askStreamingMLModel($message);

    if ($mlResult !== null) {
        $intent = $mlResult['intent'];
        $confidence = $mlResult['confidence'];
        $modelUsed = $mlResult['model_used'] ?? null;
    }

    $simpleIntents = [
        'greeting', 'goodbye', 'thanks', 'affirmation', 'denial',
        'product_search', 'order_track', 'order_history', 'order_cancel', 'invoice',
        'delivery_time', 'shipping_fee', 'delivery_info', 'order_status',
        'payment_methods', 'return_policy', 'warranty', 'contact_support',
        'discount_promo', 'account_help', 'complaint', 'stock_check',
        'recommendation', 'place_order', 'platform_info', 'bot_identity', 'product_price',
        'availability', 'price_check', 'faq', 'category_search',
    ];

    $useGemini = $mlResult !== null
        ? shouldInvokeGeminiLastResort($message, $mlResult)
        : false;

    if (in_array($intent, $simpleIntents, true) && $confidence >= 0.75) {
        $useGemini = false;
    }

    // Send progress update
    emitStreamEvent([
        'type' => 'processing',
        'intent' => $intent,
        'confidence' => round($confidence * 100, 1),
        'using_gemini' => $useGemini,
        'model_used' => $modelUsed
    ]);

    $forwardData = $data;
    $forwardData['message'] = $message;
    $forwardData['session_id'] = $session_id;

    $primaryResponse = askPrimaryChatbot($forwardData, $phpSessionId);
    if (!is_array($primaryResponse)) {
        throw new RuntimeException('Primary chatbot unavailable.');
    }

    // Calculate total processing time
    $totalTime = round((microtime(true) - $startTime) * 1000);

    $eventType = (($primaryResponse['type'] ?? '') === 'error') ? 'error' : 'response';
    $response = $primaryResponse['response'] ?? 'Sorry, I could not process that.';
    $quickReplies = $primaryResponse['quick_replies'] ?? ['Show me products', 'Contact support'];

    // Send final response
    emitStreamEvent([
        'type' => $eventType,
        'response' => $response,
        'quick_replies' => $quickReplies,
        'session_id' => $primaryResponse['session_id'] ?? $session_id,
        'log_id' => $primaryResponse['log_id'] ?? null,
        'processing_time_ms' => $totalTime,
        'intent' => $intent,
        'confidence' => round($confidence * 100, 1),
        'model_used' => $modelUsed
    ]);
    
} catch (Throwable $e) {
    emitStreamEvent([
        'type' => 'error',
        'response' => 'Something went wrong. Please try again.',
        'quick_replies' => ['Show me products', 'Contact support'],
        'error' => $e->getMessage()
    ]);
}

exit;

// ================================================================
// HELPER FUNCTIONS
// ================================================================

/**
 * Log chat conversation
 */
function logChat(string $sessionId, ?int $userId, string $message, string $response, string $intent, float $confidence, $conn): int {
    $columns = ['session_id', 'user_id', 'message', 'response'];
    $placeholders = ['?', '?', '?', '?'];
    $types = 'siss';
    $params = [$sessionId, $userId, $message, $response];

    if (chatbotLogHasColumn($conn, 'intent_tag')) {
        $columns[] = 'intent_tag';
        $placeholders[] = '?';
        $types .= 's';
        $params[] = $intent;
    } elseif (chatbotLogHasColumn($conn, 'intent')) {
        $columns[] = 'intent';
        $placeholders[] = '?';
        $types .= 's';
        $params[] = $intent;
    }

    if (chatbotLogHasColumn($conn, 'confidence')) {
        $columns[] = 'confidence';
        $placeholders[] = '?';
        $types .= 'd';
        $params[] = $confidence;
    }

    $sql = "INSERT INTO chatbot_logs (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ")";
    $stmt = $conn->prepare($sql);

    if ($stmt) {
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $logId = $stmt->insert_id;
        $stmt->close();
        return $logId;
    }
    return 0;
}

/**
 * Update the final streamed response in the conversation log
 */
function updateChatLog(int $logId, string $response, string $intent, float $confidence, $conn): void {
    $setParts = ['response=?'];
    $types = 's';
    $params = [$response];

    if (chatbotLogHasColumn($conn, 'intent_tag')) {
        $setParts[] = 'intent_tag=?';
        $types .= 's';
        $params[] = $intent;
    } elseif (chatbotLogHasColumn($conn, 'intent')) {
        $setParts[] = 'intent=?';
        $types .= 's';
        $params[] = $intent;
    }

    if (chatbotLogHasColumn($conn, 'confidence')) {
        $setParts[] = 'confidence=?';
        $types .= 'd';
        $params[] = $confidence;
    }

    $types .= 'i';
    $params[] = $logId;
    $sql = "UPDATE chatbot_logs SET " . implode(', ', $setParts) . " WHERE id=?";
    $stmt = $conn->prepare($sql);

    if ($stmt) {
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $stmt->close();
    }
}

/**
 * Detect available chatbot_logs columns so the stream endpoint works across schema versions
 */
function chatbotLogHasColumn($conn, string $column): bool {
    static $columns = null;

    if ($columns === null) {
        $columns = [];
        $result = $conn->query("SHOW COLUMNS FROM chatbot_logs");

        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $columns[$row['Field']] = true;
            }
        }
    }

    return isset($columns[$column]);
}

/**
 * Save conversation context
 */
function saveContext(string $sessionId, ?int $userId, string $key, string $value): void {
    global $conn;
    $expiresAt = date('Y-m-d H:i:s', strtotime("+24 hours"));
    $stmt = $conn->prepare("INSERT INTO chatbot_context 
                           (session_id, user_id, context_key, context_value, expires_at) 
                           VALUES (?, ?, ?, ?, ?)
                           ON DUPLICATE KEY UPDATE context_value=?, expires_at=?");
    if ($stmt) {
        $stmt->bind_param("sisssss", $sessionId, $userId, $key, $value, $expiresAt, $value, $expiresAt);
        $stmt->execute();
        $stmt->close();
    }
}

/**
 * Ask the running Flask ML service for the best intent prediction
 */
function askStreamingMLModel(string $message): ?array {
    $payload = json_encode([
        'message' => $message,
        'model' => 'best',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    $ch = curl_init('http://localhost:5001/predict');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT => 3,
        CURLOPT_CONNECTTIMEOUT => 2,
    ]);

    $response = curl_exec($ch);
    $error = curl_error($ch);
    $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false || $statusCode !== 200) {
        if ($error !== '') {
            error_log('Streaming ML request failed: ' . $error);
        }
        return null;
    }

    $data = json_decode($response, true);
    if (!is_array($data) || !isset($data['intent'], $data['confidence'])) {
        return null;
    }

    return [
        'intent' => (string)$data['intent'],
        'confidence' => (float)$data['confidence'],
        'model_used' => (string)($data['model_used'] ?? 'best'),
    ];
}

/**
 * Delegate final response generation to the primary chatbot endpoint
 */
function askPrimaryChatbot(array $payload, string $phpSessionId): ?array {
    $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    $ch = curl_init(SITE_URL . '/api/chatbot.php');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Cookie: ' . session_name() . '=' . rawurlencode($phpSessionId),
        ],
        CURLOPT_TIMEOUT => 15,
        CURLOPT_CONNECTTIMEOUT => 3,
    ]);

    $response = curl_exec($ch);
    $error = curl_error($ch);
    $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false || $statusCode !== 200) {
        if ($error !== '') {
            error_log('Primary chatbot request failed: ' . $error);
        }
        return null;
    }

    $data = json_decode($response, true);
    return is_array($data) ? $data : null;
}

/**
 * Get simple canned responses for basic intents
 */
function getSimpleResponse(string $intent, $conn): array {
    $responses = [
        'greeting' => [
            "Hello! Welcome to AI-Powered Chatbot For E-commerce Support. How can I help you today?",
            "Hi there! I'm your AI shopping assistant. What can I do for you?",
            "Hey! Great to see you. How can I assist you today?"
        ],
        'goodbye' => [
            "Goodbye! Have a great day. Come back anytime!",
            "See you later! Thank you for shopping with us.",
            "Bye! Feel free to return whenever you need help."
        ],
        'thanks' => [
            "You're welcome! Happy to help!",
            "My pleasure! Anything else you need?",
            "Anytime! That's what I'm here for!"
        ],
        'affirmation' => [
            "Great! Is there anything else you'd like to know?",
            "Perfect! How can I further assist you?",
            "Awesome! What else can I help you with?"
        ],
        'denial' => [
            "No problem! Let me know if you need anything else.",
            "That's okay! Feel free to ask something else.",
            "Understood! I'm here if you have other questions."
        ]
    ];
    
    $quickRepliesMap = [
        'greeting' => ['Show me products', 'Track my order', 'Delivery info'],
        'goodbye' => [],
        'thanks' => ['Show more products', 'Contact support'],
        'affirmation' => ['Continue shopping', 'View cart', 'Checkout'],
        'denial' => ['Show me something else', 'Browse categories', 'Help']
    ];
    
    $responseList = $responses[$intent] ?? ["I understand. How can I help?"];
    $randomResponse = $responseList[array_rand($responseList)];
    
    return [$randomResponse, $quickRepliesMap[$intent] ?? []];
}

/**
 * Get database-grounded response for product/order queries
 * Uses ML model trained on intents.json + intents_part2.json
 */
function getMLResponse(string $intent, string $message, ?int $userId, $conn, string $sessionId): array {
    $response = '';
    $quickReplies = [];
    
    // Load intent responses from your intents.json files
    $intentResponses = loadIntentResponses();
    
    switch ($intent) {
        case 'greeting':
            $responses = $intentResponses['greeting'] ?? [
                "Hello! Welcome to AI-Powered Chatbot For E-commerce Support. How can I help you today?",
                "Hi there! I'm your AI shopping assistant. What can I do for you?",
                "Hey! Great to see you. How can I assist you today?"
            ];
            $response = $responses[array_rand($responses)];
            $quickReplies = ['Show me products', 'Track my order', 'Delivery info'];
            break;
            
        case 'product_search':
            // Search products using keywords from message
            $keywords = preg_replace('/[^a-z0-9\s]/i', '', strtolower($message));
            $words = explode(' ', $keywords);
            
            $conditions = [];
            $params = [];
            $types = '';
            
            foreach ($words as $word) {
                if (strlen($word) > 2) {
                    $conditions[] = "(p.name LIKE ? OR p.description LIKE ? OR c.name LIKE ?)";
                    $likeWord = "%$word%";
                    $params[] = $likeWord;
                    $params[] = $likeWord;
                    $params[] = $likeWord;
                    $types .= 'sss';
                }
            }
            
            if (!empty($conditions)) {
                $whereClause = implode(' AND ', $conditions);
                $sql = "SELECT p.id, p.name, p.price, p.stock, c.name as cat_name 
                       FROM products p 
                       LEFT JOIN categories c ON p.category_id = c.id 
                       WHERE p.status = 1 AND p.stock > 0 AND $whereClause 
                       LIMIT 5";
                
                $stmt = $conn->prepare($sql);
                if (!empty($params)) {
                    $stmt->bind_param($types, ...$params);
                }
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows > 0) {
                    $response = "I found these products for you:\n\n";
                    while ($row = $result->fetch_assoc()) {
                        $response .= "• {$row['name']} - RWF " . number_format($row['price']) . 
                                   " ({$row['stock']} left) - Category: {$row['cat_name']}\n";
                        $response .= "  View: " . SITE_URL . "/product.php?id={$row['id']}\n\n";
                    }
                    $quickReplies = ['View details', 'Add to cart', 'Search again'];
                } else {
                    $response = "I couldn't find exact matches, but feel free to browse our catalog!";
                    $quickReplies = ['Browse all products', 'View categories', 'Help me choose'];
                }
            } else {
                $response = "What type of products are you looking for?";
                $quickReplies = ['Electronics', 'Fashion', 'Home & Living', 'All products'];
            }
            break;
            
        case 'order_track':
        case 'order_status':
            if ($userId) {
                $stmt = $conn->prepare("SELECT id, status, total_price, created_at 
                                       FROM orders WHERE user_id = ? 
                                       ORDER BY created_at DESC LIMIT 3");
                $stmt->bind_param("i", $userId);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows > 0) {
                    $response = "Your recent orders:\n\n";
                    while ($row = $result->fetch_assoc()) {
                        $response .= "• Order #{$row['id']} - Status: {$row['status']} - " .
                                   "RWF " . number_format($row['total_price']) . "\n";
                    }
                    $quickReplies = ['Track specific order', 'Order details', 'Delivery info'];
                } else {
                    $response = "You haven't placed any orders yet. Ready to start shopping?";
                    $quickReplies = ['Browse products', 'View categories', 'Special offers'];
                }
            } else {
                $response = "Please login to view your order history.";
                $quickReplies = ['Login', 'Register', 'Continue browsing'];
            }
            break;

        case 'delivery_time':
        case 'shipping_fee':
            $tag = $intent === 'shipping_fee' ? 'shipping_fee' : 'delivery_time';
            $responses = $intentResponses[$tag] ?? ($intentResponses['delivery_info'] ?? [
                "📦 Delivery: Kigali 1–2 days, other provinces 2–4 days. Free shipping over RWF 50,000.",
            ]);
            $response = $responses[array_rand($responses)];
            $quickReplies = ['Payment methods', 'Track order', 'Show me products'];
            break;
            
        case 'delivery_info':
            $responses = $intentResponses['delivery_info'] ?? [
                "📦 Delivery Information:\n\n" .
                "• Kigali: 1-2 business days\n" .
                "• Other provinces: 2-4 business days\n" .
                "• FREE delivery on orders above RWF 50,000\n" .
                "• Standard delivery fee: RWF 3,000\n\n" .
                "We'll contact you by phone/email when your order is out for delivery!"
            ];
            $response = $responses[array_rand($responses)];
            $quickReplies = ['Payment methods', 'Track order', 'Return policy'];
            break;
            
        case 'thanks':
            $responses = $intentResponses['thanks'] ?? [
                "You're welcome! Happy to help!",
                "My pleasure! Anything else you need?",
                "Anytime! That's what I'm here for!"
            ];
            $response = $responses[array_rand($responses)];
            $quickReplies = ['Show more products', 'Contact support'];
            break;
            
        default:
            // For other intents, try to find matching response from your dataset
            if (isset($intentResponses[$intent])) {
                $responses = $intentResponses[$intent];
                $response = $responses[array_rand($responses)];
            } else {
                $response = "I understand you're asking about '$message'. Could you please rephrase or be more specific?";
                $quickReplies = ['Show me products', 'Talk to support', 'Help'];
            }
    }
    
    return [$response, $quickReplies];
}

/**
 * Load intent responses from your intents.json and intents_part2.json files
 */
function loadIntentResponses(): array {
    static $responses = null;
    
    if ($responses === null) {
        $responses = [];
        $baseDir = __DIR__ . '/../chatbot-ml/dataset/';
        
        // Load main intents.json
        $intentsFile = $baseDir . 'intents.json';
        if (file_exists($intentsFile)) {
            $data = json_decode(file_get_contents($intentsFile), true);
            if (isset($data['intents'])) {
                foreach ($data['intents'] as $intent) {
                    if (isset($intent['tag'], $intent['responses'])) {
                        $responses[$intent['tag']] = $intent['responses'];
                    }
                }
            }
        }
        
        // Load intents_part2.json
        $part2File = $baseDir . 'intents_part2.json';
        if (file_exists($part2File)) {
            $data = json_decode(file_get_contents($part2File), true);
            if (isset($data['intents'])) {
                foreach ($data['intents'] as $intent) {
                    if (isset($intent['tag'], $intent['responses'])) {
                        // Merge or add new responses
                        if (isset($responses[$intent['tag']])) {
                            $responses[$intent['tag']] = array_merge(
                                $responses[$intent['tag']], 
                                $intent['responses']
                            );
                        } else {
                            $responses[$intent['tag']] = $intent['responses'];
                        }
                    }
                }
            }
        }
        
        error_log("Loaded " . count($responses) . " intent response categories from dataset");
    }
    
    return $responses;
}

/**
 * Fast Gemini API call with streaming support
 */
function askGeminiFast(string $message, ?int $userId, $conn, string $sessionId, string $intent, float $confidence): ?array {
    $apiKey = defined('GEMINI_API_KEY') ? GEMINI_API_KEY : '';
    if (empty($apiKey) || $apiKey === 'your-gemini-api-key-here') {
        return null;
    }
    
    // Build context quickly
    $context = buildGeminiContext($message, $userId, $conn, $sessionId);
    
    // Use fastest model
    $model = 'gemini-2.0-flash';
    $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";
    
    $payload = json_encode([
        'system_instruction' => ['parts' => [['text' => $context['system']]]],
        'contents' => $context['history'],
        'generationConfig' => [
            'temperature' => 0.2,
            'maxOutputTokens' => 800,
            'topP' => 0.8,
            'topK' => 40
        ],
    ]);
    
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT => 10, // Faster timeout
        CURLOPT_CONNECTTIMEOUT => 5,
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
            $text = preg_replace('/\*(.*?)\*/s', '<em>$1</em>', $text);
            $text = preg_replace('/\[([^\]]+)\]\(([^)]+)\)/', "<a href='$2'>$1</a>", $text);
            $text = preg_replace('/\n/', '<br>', $text);
            
            return [
                'text' => trim($text),
                'quick_replies' => ['Show me products', 'View details', 'Contact support']
            ];
        }
    }
    
    return null;
}

/**
 * Build Gemini context efficiently
 */
function buildGeminiContext(string $message, ?int $userId, $conn, string $sessionId): array {
    // Fetch products (limited for speed)
    $productsRes = $conn->query("
        SELECT p.id, p.name, p.brand, p.price, p.stock, p.description, c.name AS cat
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE p.stock > 0
        ORDER BY p.id DESC
        LIMIT 12
    ");
    
    $productCtx = "\nPRODUCTS FROM DATABASE (use ONLY these):\n";
    while ($p = $productsRes->fetch_assoc()) {
        $productCtx .= "• [ID:{$p['id']}] {$p['name']} | Price: RWF " . number_format($p['price']) . 
                      " | Stock: {$p['stock']}\n";
    }
    
    // Categories
    $catRes = $conn->query("
        SELECT c.name AS cat, COUNT(p.id) AS total, MIN(p.price) AS mn, MAX(p.price) AS mx 
        FROM categories c 
        LEFT JOIN products p ON p.category_id=c.id AND p.stock>0 
        GROUP BY c.id 
        ORDER BY c.id
    ");
    
    $catCtx = "\nSTORE CATEGORIES:\n";
    while ($c = $catRes->fetch_assoc()) {
        $catCtx .= "- {$c['cat']}: {$c['total']} products (RWF " . number_format($c['mn']) . 
                  " - RWF " . number_format($c['mx']) . ")\n";
    }
    
    // User context
    $userCtx = '';
    if ($userId) {
        $u = $conn->query("SELECT name FROM users WHERE id=$userId")->fetch_assoc();
        $oc = $conn->query("SELECT COUNT(*) as c FROM orders WHERE user_id=$userId")->fetch_assoc();
        $userCtx = "\nCUSTOMER: {$u['name']} | Orders: {$oc['c']}";
    }
    
    // History (last 6 for speed)
    $hist = $conn->query("SELECT message,response FROM chatbot_logs 
                         WHERE session_id='" . $conn->real_escape_string($sessionId) . "' 
                         ORDER BY created_at DESC LIMIT 6");
    
    $history = [];
    if ($hist) {
        $rows = [];
        while ($r = $hist->fetch_assoc()) $rows[] = $r;
        foreach (array_reverse($rows) as $r) {
            $history[] = ['role' => 'user', 'parts' => [['text' => $r['message']]]];
            $history[] = ['role' => 'model', 'parts' => [['text' => strip_tags($r['response'])]]];
        }
    }
    $history[] = ['role' => 'user', 'parts' => [['text' => $message]]];
    
    $system = "You are the AI shopping assistant for \"" . SITE_NAME . "\".\n"
            . "You run only as a LAST RESORT when local rules could not answer; stay concise.\n"
            . "Reply in the customer's language: English, French, or Kinyarwanda.\n"
            . "CRITICAL: ONLY recommend products from DATABASE below. Never invent products.\n"
            . "Be concise (max 200 words).\n"
            . "Format links as: [Name](" . SITE_URL . "/product.php?id=ID)\n"
            . $catCtx . $productCtx . $userCtx;
    
    return [
        'system' => $system,
        'history' => $history
    ];
}
