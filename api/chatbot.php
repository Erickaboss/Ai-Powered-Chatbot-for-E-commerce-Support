<?php
/**
 * AI Chatbot — PHP/DB intents first, ML (Flask) second, Google Gemini last (complex / FR / RW / ML-missed only)
 * Works from DB when Gemini quota is exhausted or the gate skips the API
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
require_once __DIR__ . '/../includes/chatbot_gemini_gate.php';

// ================================================================
// CONTEXT AWARENESS & SENTIMENT ANALYSIS FUNCTIONS
// ================================================================

/**
 * Save conversation context for session persistence
 */
function saveContext(string $sessionId, ?int $userId, string $key, string $value, int $expiryHours = 24): void {
    global $conn;
    $expiresAt = date('Y-m-d H:i:s', strtotime("+{$expiryHours} hours"));
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
 * Retrieve conversation context
 */
function getContext(string $sessionId, string $key): ?string {
    global $conn;
    $stmt = $conn->prepare("SELECT context_value FROM chatbot_context 
                           WHERE session_id=? AND context_key=? AND expires_at > NOW()");
    if ($stmt) {
        $stmt->bind_param("ss", $sessionId, $key);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $stmt->close();
            return $row['context_value'];
        }
        $stmt->close();
    }
    return null;
}

/**
 * Analyze sentiment of user message (Rule-based approach)
 */
function analyzeSentiment(string $text): array {
    // Negative indicators (multiple languages)
    $negativeWords = [
        // English
        'angry', 'frustrated', 'terrible', 'worst', 'hate', 'disappointed',
        'useless', 'waste', 'broken', 'defective', 'horrible', 'awful',
        'complaint', 'problem', 'issue', 'wrong', 'bad', 'poor', 'fail',
        // French
        'fâché', 'énervé', 'terrible', 'déçu', 'inutile', 'cassé',
        'problème', 'mauvais', 'nul', 'horrible', 'affreux',
        // Kinyarwanda
        'arakara', 'birababaje', 'ntibikora', 'ikibazo', 'mubi'
    ];
    
    // Positive indicators (multiple languages)
    $positiveWords = [
        // English
        'great', 'excellent', 'happy', 'love', 'amazing', 'thank', 'thanks',
        'perfect', 'awesome', 'good', 'best', 'helpful', 'satisfied',
        'wonderful', 'fantastic', 'beautiful', 'nice',
        // French
        'super', 'excellent', 'heureux', 'amour', 'merci', 'parfait',
        'génial', 'bon', 'formidable', 'beau', 'content',
        // Kinyarwanda
        'neza', 'murakoze', 'byiza', 'ndashima', 'mwiza'
    ];
    
    // Intensifiers (multiply sentiment)
    $intensifiers = ['very', 'really', 'extremely', 'absolutely', 'totally', 'très', 'cyane', 'beaucoup'];
    
    $score = 0.0;
    $textLower = strtolower($text);
    $words = preg_split('/\s+/', $textLower);
    
    foreach ($words as $index => $word) {
        // Check if previous word is intensifier
        $isIntensified = ($index > 0 && in_array($words[$index - 1], $intensifiers));
        $multiplier = $isIntensified ? 1.5 : 1.0;
        
        if (in_array($word, $negativeWords)) {
            $score -= 0.2 * $multiplier;
        } elseif (in_array($word, $positiveWords)) {
            $score += 0.2 * $multiplier;
        }
    }
    
    // Normalize score to -1 to 1 range
    $score = max(-1.0, min(1.0, $score));
    
    // Determine label
    $label = 'neutral';
    if ($score < -0.3) $label = 'negative';
    elseif ($score > 0.3) $label = 'positive';
    
    // Detect urgency/escalation triggers
    $escalateTriggers = ['sue', 'lawyer', 'refund now', 'manager', 'cancel order', 'unacceptable', 'avocat', 'remboursement', 'mwishyura'];
    $shouldEscalate = false;
    foreach ($escalateTriggers as $trigger) {
        if (strpos($textLower, $trigger) !== false) {
            $shouldEscalate = true;
            break;
        }
    }
    
    // Auto-escalate if very negative or has escalation triggers
    if ($score < -0.5 || $shouldEscalate) {
        $shouldEscalate = true;
    }
    
    return [
        'score' => round($score, 2),
        'label' => $label,
        'escalate' => $shouldEscalate
    ];
}

/**
 * Get response in detected language
 */
function getLocalizedResponse(array $responses, string $lang): string {
    // If responses are already in the right language, return as-is
    return $responses[array_rand($responses)];
}

/**
 * Log sentiment analysis results
 */
function logSentiment(int $logId, float $score, string $label, bool $escalated): void {
    global $conn;
    $stmt = $conn->prepare("UPDATE chatbot_logs SET sentiment_score=?, sentiment_label=?, escalated=? WHERE id=?");
    if ($stmt) {
        $escalatedInt = $escalated ? 1 : 0;
        $stmt->bind_param("dsii", $score, $label, $escalatedInt, $logId);
        $stmt->execute();
        $stmt->close();
    }
}

/**
 * Create support ticket for escalated chats
 */
function createSupportTicket(?int $userId, string $sessionId, string $message, string $sentiment): void {
    global $conn;
    
    $safeMessage = $conn->real_escape_string($message);
    $safeSession = $conn->real_escape_string($sessionId);
    
    // Get user info if logged in
    $userInfo = '';
    if ($userId) {
        $userResult = $conn->query("SELECT name, email FROM users WHERE id=$userId");
        if ($userResult && $row = $userResult->fetch_assoc()) {
            $userInfo = "User: {$row['name']} ({$row['email']})\n";
        }
    }
    
    $ticketMessage = "ESCALATED CHATBOT CONVERSATION\n\n{$userInfo}Session: {$sessionId}\n\nCustomer said:\n{$safeMessage}\n\nSentiment: {$sentiment}";
    
    $stmt = $conn->prepare("INSERT INTO support_tickets (user_id, session_id, customer_name, message, status) VALUES (?, ?, 'Chatbot User', ?, 'open')");
    if ($stmt) {
        $stmt->bind_param("iss", $userId, $sessionId, $ticketMessage);
        $stmt->execute();
        $stmt->close();
        
        // Notify admin via email (if configured)
        if (defined('ADMIN_EMAIL')) {
            $subject = "🚨 Urgent: Escalated Chatbot Conversation";
            mail(ADMIN_EMAIL, $subject, $ticketMessage, "From: chatbot@shopai.rw\r\n");
        }
    }
}

$input   = json_decode(file_get_contents('php://input'), true);
$message = trim($input['message'] ?? '');
$user_id = $_SESSION['user_id'] ?? null;
$image   = $input['image'] ?? null;
$imageAnalysis = $input['image_analysis'] ?? null;

// Detect language from user message
$detectedLang = detectLanguage($message);

// Process image if uploaded
if ($image) {
    // Save image to uploads directory
    require_once __DIR__ . '/../config/db.php';
    
    // Remove data:image prefix if present
    if (preg_match('/^data:image\/(\w+);base64,/', $image, $type)) {
        $image = substr($image, strpos($image, ',') + 1);
        $type = strtolower($type[1]); // jpg, png, gif, etc.
    } else {
        $type = 'jpg';
    }
    
    // Decode base64
    $imageData = base64_decode($image);
    
    if ($imageData !== false) {
        // Generate unique filename
        $filename = 'chat_' . time() . '_' . bin2hex(random_bytes(8)) . '.' . $type;
        $uploadPath = __DIR__ . '/../assets/images/chat_uploads/' . $filename;
        
        // Create directory if not exists
        if (!is_dir(dirname($uploadPath))) {
            mkdir(dirname($uploadPath), 0755, true);
        }
        
        // Save image
        file_put_contents($uploadPath, $imageData);
        
        // Store image path for processing
        $imagePath = 'assets/images/chat_uploads/' . $filename;
        $imageUrl = SITE_URL . '/' . $imagePath;
        
        // If we have AI image analysis from TensorFlow.js
        if ($imageAnalysis && isset($imageAnalysis['topMatch'])) {
            // Use the analysis to enhance response
            $detectedObject = $imageAnalysis['topMatch'];
            $confidence = round(($imageAnalysis['confidence'] ?? 0) * 100);
            $labels = $imageAnalysis['labels'] ?? [];
            
            // Log the analysis
            error_log("Image Analysis: $detectedObject ({$confidence}% confidence)");
            
            // If no text message, create one based on analysis
            if (empty($message)) {
                $message = "Tell me about $detectedObject";
            }
            
            // Store analysis in session for context
            $_SESSION['last_image_analysis'] = [
                'object' => $detectedObject,
                'confidence' => $confidence,
                'labels' => $labels,
                'image_url' => $imageUrl
            ];
        }
        
        // If no text message, default to question about image
        if (empty($message)) {
            $message = "What is in this image?";
        }
    }
}

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

// ── Rate endpoint ──
if (($_GET['action'] ?? '') === 'rate') {
    header('Content-Type: application/json');
    $logId  = (int)($input['log_id'] ?? 0);
    $rating = (int)($input['rating'] ?? -1);
    $uid2   = $_SESSION['user_id'] ?? null;
    $sid2   = $conn->real_escape_string(preg_replace('/[^a-f0-9]/i','', $input['session_id'] ?? ''));
    if ($logId && in_array($rating, [0,1])) {
        $ui2 = $uid2 ? (int)$uid2 : 'NULL';
        $conn->query("INSERT IGNORE INTO chatbot_ratings (log_id, user_id, session_id, rating) VALUES ($logId, $ui2, '$sid2', $rating)");
    }
    echo json_encode(['ok' => true]);
    exit;
}

// ── Stock notification endpoint ──
if (($_GET['action'] ?? '') === 'stock_notify') {
    header('Content-Type: application/json');
    $pid   = (int)($input['product_id'] ?? 0);
    $email = trim($input['email'] ?? '');
    $name  = $conn->real_escape_string(trim($input['name'] ?? 'Customer'));
    if ($pid && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $safeEmail = $conn->real_escape_string($email);
        $conn->query("INSERT IGNORE INTO stock_notifications (product_id, email, name) VALUES ($pid, '$safeEmail', '$name')");
        echo json_encode(['ok' => true]);
    } else {
        echo json_encode(['error' => 'Invalid data']);
    }
    exit;
}

if (empty($message)) {
    echo json_encode(['response' => 'Please type a message.', 'quick_replies' => []]);
    exit;
}

// ================================================================
// IMAGE ANALYSIS HANDLING - REMOVED (feature disabled)
// ================================================================
// Image upload feature has been removed to improve performance and speed.
// All image-related processing code has been disabled.

/* DISABLED CODE - Image upload feature removed:
if ($imageAnalysis && isset($imageAnalysis['topMatch'])) {
    $detectedObject = $imageAnalysis['topMatch'];
    $confidence = round(($imageAnalysis['confidence'] ?? 0) * 100);
    $labels = $imageAnalysis['labels'] ?? [];
    
    // Log for debugging
    error_log("🔍 Image Analysis received: $detectedObject ({$confidence}% confidence)");
    
    // If user didn't provide text, auto-generate based on detection
    if (trim($message) === '' || strtolower(trim($message)) === 'what is in this image?') {
        // Search for products using detected object keywords
        $searchResults = dbProductSearch($detectedObject, $conn);
        
        // ... rest of image handling code ...
    }
    
    // Store in session for context
    $_SESSION['last_image_analysis'] = [
        'object' => $detectedObject,
        'confidence' => $confidence,
        'labels' => $labels
    ];
}
*/
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

    // ================================================================
    // SENTIMENT ANALYSIS - Analyze user emotion
    // ================================================================
    $sentiment = analyzeSentiment($message);
    
    $sm  = $conn->real_escape_string($message);
    $sr  = $conn->real_escape_string($response);
    $ui  = $user_id ? (int)$user_id : 'NULL';
    $sid = $conn->real_escape_string($session_id);
    $guest = $user_id ? 0 : 1;
    $saved = $conn->query("INSERT INTO chatbot_logs (user_id, session_id, is_guest, message, response, sentiment_score, sentiment_label, escalated) 
                          VALUES ($ui, '$sid', $guest, '$sm', '$sr', {$sentiment['score']}, '{$sentiment['label']}', " . ($sentiment['escalate'] ? 1 : 0) . ")");
    if (!$saved) {
        error_log("chatbot_logs INSERT failed: " . $conn->error . " | uid=$ui sid=$sid");
    }
    $log_id = (int)$conn->insert_id;
    
    // Handle escalation if sentiment is very negative
    if ($sentiment['escalate']) {
        createSupportTicket($user_id, $session_id, $message, $sentiment['label']);
        // Add empathetic response
        $response = "I'm really sorry to hear you're experiencing issues. I've escalated this to our support team and a human agent will contact you shortly. In the meantime, is there anything else I can help you with?";
        $qr[] = 'Speak to human agent';
        $qr[] = 'File complaint';
    }

    // ================================================================
    // CONTEXT AWARENESS - Save conversation context
    // ================================================================
    // Track last product viewed/searched
    if (stripos($message, 'product') !== false || stripos($message, 'item') !== false || stripos($message, 'buy') !== false) {
        saveContext($session_id, $user_id, 'last_product_query', $message);
    }
    
    // Track order-related queries
    if (stripos($message, 'order') !== false || stripos($message, 'delivery') !== false || stripos($message, 'tracking') !== false) {
        saveContext($session_id, $user_id, 'last_order_query', $message);
    }

    echo json_encode(['response' => $response, 'quick_replies' => $qr, 'session_id' => $session_id, 'log_id' => $log_id]);
} catch (Throwable $e) {
    echo json_encode(['response' => 'Something went wrong. Please try again.', 'quick_replies' => ['Show me products', 'Contact support']]);
}
exit;

// ================================================================
function reply(string $text, array $qr = []): array {
    return ['response' => $text, 'quick_replies' => $qr];
}

/**
 * Fast DB-grounded reply when the local ML service classifies intent with high confidence
 * but the message did not match earlier regex routes (e.g. unusual phrasing).
 * Avoids an extra Gemini round-trip for common store topics.
 */
function intentMlFastReply(string $intent, string $msg, ?int $uid, $conn, array &$ctx): ?array {
    $ml = strtolower(trim($msg));
    $lang = detectLanguage($msg);

    switch ($intent) {
        case 'delivery_time':
            return reply(
                "🚚 <strong>Delivery Times (Rwanda):</strong><br>" .
                "• <strong>Kigali:</strong> 1–2 business days<br>" .
                "• <strong>Other provinces:</strong> 2–4 business days<br>" .
                "• <strong>Remote areas:</strong> up to 5–7 days<br>" .
                "You'll receive an SMS/email update once your order is shipped! 📱",
                ['Shipping fees', 'Track my order', 'Payment methods']
            );

        case 'shipping_fee':
            return reply(
                "📦 <strong>Shipping Fees:</strong><br>" .
                "• Orders above <strong>RWF 50,000</strong> → <strong>FREE shipping</strong> 🎉<br>" .
                "• Orders below RWF 50,000 → <strong>RWF 2,000</strong> flat rate<br>" .
                "• Express delivery (Kigali only) → <strong>RWF 3,500</strong>",
                ['Delivery time', 'Payment methods', 'Show me products']
            );

        case 'payment_methods':
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

        case 'return_policy':
            return reply(
                "↩️ <strong>Return & Refund Policy:</strong><br>" .
                "• Items returnable within <strong>7 days</strong> of delivery<br>" .
                "• Item must be unused and in original packaging<br>" .
                "• Damaged or wrong items: full refund or free replacement<br>" .
                "• Refunds processed within <strong>3–5 business days</strong><br>" .
                "📧 Start a return: <a href='mailto:" . ADMIN_EMAIL . "'>" . ADMIN_EMAIL . "</a>",
                ['Delivery info', 'Contact support', 'Warranty info']
            );

        case 'warranty':
            return reply(
                "🛡️ <strong>Warranty Information:</strong><br>" .
                "• 📱 Electronics & Phones: <strong>1 year</strong><br>" .
                "• 🏠 Home Appliances: <strong>1–2 years</strong><br>" .
                "• 👗 Clothing & Accessories: <strong>7 days</strong> defect warranty<br>" .
                "• ⌚ Watches: <strong>6 months</strong><br>" .
                "📧 Claims: <a href='mailto:" . ADMIN_EMAIL . "'>" . ADMIN_EMAIL . "</a> with order number + photos.",
                ['Return policy', 'Contact support']
            );

        case 'contact_support':
        case 'support_ticket':
            $ctx['awaiting'] = 'support_message';
            return reply(
                "📞 <strong>Contact & Support:</strong><br>" .
                "• 📧 Email: <a href='mailto:" . ADMIN_EMAIL . "'>" . ADMIN_EMAIL . "</a><br>" .
                "• 📱 Phone/WhatsApp: <a href='tel:" . ADMIN_PHONE . "'>" . ADMIN_PHONE . "</a><br>" .
                "• 🕐 Office Hours: Mon–Sat, 8AM–6PM (Kigali time)<br><br>" .
                "💬 <strong>Or type your message below and we'll email it to our team right now:</strong>",
                ['Return policy', 'Delivery info', 'Track my order']
            );

        case 'discount_promo':
            return reply(
                "🏷️ <strong>Current Deals & Promotions:</strong><br>" .
                "• 🎉 Free shipping on orders above <strong>RWF 50,000</strong><br>" .
                "• New arrivals added weekly across all categories<br>" .
                "• 💡 Tip: Add more items to qualify for free shipping!<br>" .
                "Check our <a href='" . SITE_URL . "/products.php'>Products page</a> for latest prices.",
                ['Show me products', 'Delivery info']
            );

        case 'account_help':
            return reply(
                "👤 <strong>Account Help:</strong><br>• <a href='" . SITE_URL . "/login.php'>Login</a> | <a href='" . SITE_URL . "/register.php'>Register</a><br>• <a href='" . SITE_URL . "/profile.php'>Edit Profile</a><br>• Password issues: <a href='mailto:" . ADMIN_EMAIL . "'>" . ADMIN_EMAIL . "</a>",
                ['Login', 'Register']
            );

        case 'complaint':
            return reply(
                "😔 I'm really sorry to hear that! We take all issues seriously.<br><br>" .
                "Please contact us:<br>📧 <a href='mailto:" . ADMIN_EMAIL . "'>" . ADMIN_EMAIL . "</a> | 📱 " . ADMIN_PHONE . "<br>Include your order number for faster help.",
                ['Return policy', 'Contact support', 'Track my order']
            );

        case 'bot_identity':
            return reply(
                getCapabilityShowcaseText($conn, $uid, $lang),
                getLocalizedPrimaryReplies($lang, $uid)
            );
            return reply(
                "🤖 I'm the AI shopping assistant for <strong>" . SITE_NAME . "</strong>!<br>I can find products, check prices, track orders, and answer questions about our store — in English, French, or Kinyarwanda.",
                ['Show me products', 'What can you do?']
            );

        case 'platform_info':
            return reply(
                getStoreOverviewText($conn, $lang),
                getLocalizedPrimaryReplies($lang, $uid)
            );

        case 'chatbot_rating':
            return reply(
                "⭐ Thanks for your interest in rating us! After each answer you can use 👍 / 👎 under bot messages to give feedback.",
                ['Show me products', 'Contact support']
            );

        case 'stock_notification':
            return reply(
                "🔔 <strong>Stock alerts:</strong> Open a product page and use the notify option when an item is out of stock — we'll email you when it's back.",
                ['Show me products', 'Contact support']
            );

        case 'analytics':
        case 'category_search':
            return reply(getCategorySummary($conn), ['Show me phones', 'Show me laptops', 'Show me products']);

        case 'order_track':
            if (!$uid) {
                return reply("🔒 Please <a href='" . SITE_URL . "/login.php'>login</a> first to track your orders.", ['Login', 'Register']);
            }
            if (preg_match('/#?0*(\d+)\b/', $msg, $m)) {
                return reply(trackOrder((int)$m[1], $uid, $conn), ['View all orders', 'Cancel an order']);
            }
            $latest = $conn->query("SELECT id,status FROM orders WHERE user_id=$uid ORDER BY created_at DESC LIMIT 1")->fetch_assoc();
            if ($latest) {
                return reply(
                    "Your latest order is <strong>#" . $latest['id'] . "</strong> — Status: <strong>" . ucfirst($latest['status']) . "</strong>.<br>Type the order number for full details.",
                    ['Track order ' . $latest['id'], 'View all orders']
                );
            }
            $ctx['awaiting'] = 'order_number';
            return reply("Please provide your order number. Example: <em>track order 5</em><br>Find it on the <a href='" . SITE_URL . "/orders.php'>My Orders</a> page.");

        case 'order_cancel':
            if (!$uid) {
                return reply("🔒 Please <a href='" . SITE_URL . "/login.php'>login</a> to manage your orders.");
            }
            if (preg_match('/\b(\d+)\b/', $msg, $m)) {
                return reply(cancelOrder((int)$m[1], $uid, $conn));
            }
            return reply("To cancel an order, type: <em>cancel order [number]</em><br>Find your order number on the <a href='" . SITE_URL . "/orders.php'>My Orders</a> page.", ['View my orders']);

        case 'order_history':
            if (!$uid) {
                return reply("🔒 Please <a href='" . SITE_URL . "/login.php'>login</a> to view your orders.");
            }
            return reply(orderHistory($uid, $conn), ['Track an order', 'Cancel an order']);

        case 'invoice':
            if (!$uid) {
                return reply("🔒 Please <a href='" . SITE_URL . "/login.php'>login</a> to access your invoices.");
            }
            if (preg_match('/#?0*(\d+)\b/', $msg, $m)) {
                $oid = (int)$m[1];
                $chk = $conn->query("SELECT id FROM orders WHERE id=$oid AND user_id=$uid LIMIT 1")->fetch_assoc();
                if ($chk) {
                    return reply(
                        "🧾 <strong>Invoice for Order #" . str_pad((string)$oid, 6, '0', STR_PAD_LEFT) . "</strong><br><br>" .
                        "<a href='" . SITE_URL . "/invoice.php?id=$oid' target='_blank'><strong>📄 Download / Print Invoice →</strong></a>",
                        ['My orders', 'Track my order']
                    );
                }
                return reply("❌ Order #$oid not found under your account.", ['My orders']);
            }
            $res = $conn->query("SELECT id FROM orders WHERE user_id=$uid ORDER BY created_at DESC LIMIT 5");
            $links = '';
            if ($res) {
                while ($o = $res->fetch_assoc()) {
                    $num = str_pad((string)$o['id'], 6, '0', STR_PAD_LEFT);
                    $links .= "• <a href='" . SITE_URL . "/invoice.php?id={$o['id']}' target='_blank'>Invoice #$num →</a><br>";
                }
            }
            return reply(
                $links
                    ? "🧾 <strong>Your Recent Invoices:</strong><br><br>$links"
                    : "You have no orders yet.",
                ['My orders', 'Track my order']
            );

        case 'place_order':
            return reply(
                getOrderGuideText($uid, $lang),
                getOrderGuideQuickReplies($lang, $uid)
            );
            if (!$uid) {
                return reply(
                    "🔒 Please <a href='" . SITE_URL . "/login.php'><strong>login</strong></a> first to place an order.",
                    ['Login', 'Register']
                );
            }
            return reply(
                "🛒 To buy from chat, tell me <em>which product</em> (e.g. <em>I want Samsung Galaxy A54</em>) or open <a href='" . SITE_URL . "/products.php'>Products</a> and use <strong>Add to cart</strong>.",
                ['Show me products', 'My cart']
            );

        case 'product_search':
        case 'product_price':
        case 'recommendation':
        case 'stock_check':
            $rows = dbProductSearch($msg, $conn);
            if (!empty($rows)) {
                $ctx['last_products'] = $rows;
                [$minP, $maxP] = extractPriceRange($ml);
                $label = '';
                if ($minP && $maxP) {
                    $label = 'Products RWF ' . number_format($minP) . ' – RWF ' . number_format($maxP);
                } elseif ($maxP) {
                    $label = 'Products under RWF ' . number_format($maxP);
                } elseif ($minP) {
                    $label = 'Products above RWF ' . number_format($minP);
                }
                $label = getBudgetLabelText($minP, $maxP, $lang);
                $fp = formatProducts($rows, $label, false, $lang);
                return reply($fp['text'], array_merge($fp['qr'], getBudgetQuickReplies($lang)));
            }
            return null;

        case 'faq':
            $kws = extractKeywords($msg);
            if (empty($kws)) {
                return null;
            }
            $conditions = [];
            foreach (array_slice($kws, 0, 4) as $w) {
                $w = $conn->real_escape_string($w);
                $conditions[] = "(LOWER(question) LIKE '%$w%' OR LOWER(answer) LIKE '%$w%')";
            }
            $sql = "SELECT question, answer FROM faq WHERE status = 1 AND (" . implode(' OR ', $conditions) . ") LIMIT 3";
            $fr = @$conn->query($sql);
            if (!$fr || $fr->num_rows === 0) {
                return null;
            }
            $out = "📋 <strong>From our FAQ:</strong><br><br>";
            while ($row = $fr->fetch_assoc()) {
                $out .= "<strong>" . htmlspecialchars($row['question']) . "</strong><br>" . nl2br(htmlspecialchars($row['answer'])) . "<br><br>";
            }
            return reply(rtrim($out), ['Show me products', 'Contact support']);

        default:
            return null;
    }
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
    $stop = array_merge($stop, [
        'bonjour','salut','merci','montre','montrez','affiche','afficher','cherche','chercher','besoin','veux',
        'acheter','commande','livraison','paiement','retour','prix','produit','produits','avec','sans','pour',
        'mon','ma','mes','des','les','une','dans','sous','plus','moins','combien','quel','quelle','quelles',
        'muraho','murakoze','nyereka','erekana','mbwira','ndashaka','nshaka','mfite','nfite','gura','kugura',
        'igiciro','ibicuruzwa','amafaranga','budgeti','uru','iri','iki','ni','nde','he','kuri','yawe','yanjye'
    ]);
    $words = array_filter(
        explode(' ', preg_replace('/[^a-z0-9\s]/i', '', strtolower(trim($msg)))),
        fn($w) => strlen($w) >= 3 && !in_array($w, $stop)
    );
    return array_values($words);
}

// ================================================================
// PRICE RANGE EXTRACTOR
// ================================================================
function parseBudgetAmount(string $number, string $suffix = ''): int {
    $n = (int)preg_replace('/[^\d]/', '', $number);
    $suffix = strtolower(trim($suffix));
    if ($n <= 0) {
        return 0;
    }
    if (in_array($suffix, ['m', 'million', 'millions', 'milio', 'miliyoni'], true)) {
        return $n * 1000000;
    }
    if (in_array($suffix, ['k', 'thousand'], true)) {
        return $n * 1000;
    }
    return $n <= 999 ? $n * 1000 : $n;
}

function extractPriceRange(string $ml): array {
    $min = null;
    $max = null;
    $normalized = strtolower(trim($ml));
    $normalized = preg_replace('/(?<=\d)[,\s](?=\d{3}\b)/', '', $normalized);
    $num = '([0-9]+(?:[.,][0-9]+)?)';

    if (preg_match('/(?:between|entre|hagati(?:\s+ya)?|kuva)\s*(?:rwf|frw|amafaranga|francs?)?\s*' . $num . '\s*(k|m|million|millions|milio|miliyoni)?\s*(?:and|to|-|et|na)\s*(?:rwf|frw|amafaranga|francs?)?\s*' . $num . '\s*(k|m|million|millions|milio|miliyoni)?/iu', $normalized, $m)) {
        $min = parseBudgetAmount($m[1], $m[2] ?? '');
        $max = parseBudgetAmount($m[3], $m[4] ?? '');
        if ($min > $max) {
            [$min, $max] = [$max, $min];
        }
    }

    if (!$max && preg_match('/(?:under|below|less than|cheaper than|maximum|max|at most|moins de|inferieur a|inférieur à|jusqu a|jusquà|ntarenze|atarengeje|munsi ya)\s*(?:rwf|frw|amafaranga|francs?)?\s*' . $num . '\s*(k|m|million|millions|milio|miliyoni)?/iu', $normalized, $m)) {
        $max = parseBudgetAmount($m[1], $m[2] ?? '');
    }

    if (!$min && preg_match('/(?:above|over|more than|minimum|min|at least|plus de|superieur a|supérieur à|kurenga|hejuru ya|guhera kuri|from)\s*(?:rwf|frw|amafaranga|francs?)?\s*' . $num . '\s*(k|m|million|millions|milio|miliyoni)?/iu', $normalized, $m)) {
        $min = parseBudgetAmount($m[1], $m[2] ?? '');
    }

    return [$min, $max];
}

// ================================================================
// CATEGORY DETECTOR
// ================================================================
function detectCategory(string $ml): ?int {
    $map = [
        1  => 'phone|phones|mobile|smartphone|smartphones|iphone|samsung|tecno|infinix|xiaomi|oppo|vivo|nokia|redmi|tablet|android|ipad|galaxy|camon|spark|note|pro max',
        2  => 'laptop|laptops|computer|computers|pc|macbook|dell|hp|lenovo|acer|asus|notebook|chromebook|desktop|monitor|keyboard|mouse|ram|processor|hard drive|ssd',
        3  => 'smart tv|television|televisions|speaker|speakers|headphone|headphones|audio|sound|earphone|earphones|subwoofer|home theater|soundbar|home cinema|bluetooth speaker|wireless speaker|woofer|amplifier|projector',
        4  => 'fridge|fridges|washing machine|microwave|appliance|appliances|cooker|kettle|blender|iron|vacuum|oven|dishwasher|air conditioner|fan|heater|juicer|toaster|freezer|water dispenser',
        5  => 'men shirt|men trouser|men suit|men shoe|men fashion|men cloth|men wear|men jacket|men clothing|menswear|fashion for men|clothes for men|men style|men outfit|men collection|for men|men only|male fashion|male clothing|male wear|gents|gentlemen|men belt|men hoodie|men jeans|men polo|men sneakers',
        6  => 'women dress|handbag|handbags|heels|ladies|women fashion|women cloth|skirt|blouse|women shoe|women clothing|womenswear|fashion for women|clothes for women|female fashion|ladies fashion|for women|women only|ankara|leggings|women blazer|women jeans|women perfume|crossbody',
        7  => 'food|grocery|groceries|rice|milk|coffee|tea|sugar|flour|cooking oil|cereal|juice|snack|snacks|noodles|ketchup|detergent|soap|toothpaste|beverage|drinks|indomie|inyange|akabanga',
        8  => 'beauty|skincare|lotion|shampoo|perfume|cream|makeup|deodorant|hair|cosmetic|moisturizer|cosmetics|serum|face wash|lipstick|sanitizer|vitamin|multivitamin|razor|electric toothbrush',
        9  => 'sport|sports|gym|fitness|football|running|yoga|exercise|dumbbell|treadmill|bicycle|jersey|protein|whey|resistance band|jump rope|cycling|sneakers sport|water bottle gym',
        10 => 'baby|kids|child|children|toy|toys|diaper|stroller|crib|nursery|infant|toddler|pampers|baby lotion|feeding bottle|kids backpack|lego|puzzle|kids bicycle|baby monitor',
        11 => 'furniture|sofa|bed|table|chair|wardrobe|shelf|decor|lamp|mirror|ottoman|mattress|curtain|home decor|living room|bedroom|office chair|bookshelf|cabinet',
        12 => 'car accessory|car accessories|vehicle accessory|tyre|tyres|auto part|spare part|car seat|car charger|car mat|dashboard|steering wheel cover',
        13 => 'book|books|pen|pens|stationery|school supply|pencil|ruler|eraser|calculator|notebook school|office supply|marker|highlighter|stapler|file folder',
        14 => 'watch|watches|jewelry|jewellery|ring|necklace|bracelet|earring|gold|silver|pendant|wrist watch|engagement ring|wedding ring|chain|bangle',
        15 => 'game|games|gaming|playstation|xbox|console|controller|nintendo|ps4|ps5|gaming headset|gaming chair|gaming mouse|gaming keyboard|joystick|vr headset',
    ];
    $localized = [
        1  => 'telephone|telephones|téléphone|téléphones|portable|portables|telefoni|simu',
        2  => 'ordinateur|ordinateurs|ordinateur portable|ordinateurs portables|mudasobwa',
        3  => 'televiseur|televiseurs|téléviseur|téléviseurs|enceinte|haut parleur|hautparleur|ecouteur|écouteur|televiziyo',
        4  => 'refrigerateur|refrigerateurs|réfrigérateur|réfrigérateurs|frigo|machine a laver|machine à laver|mixeur|ibikoresho byo mu rugo',
        5  => 'vetement homme|vêtement homme|homme|abagabo|imyenda y abagabo',
        6  => 'robe|robes|sac a main|sac à main|chaussure femme|vetement femme|vêtement femme|femme|abagore|imyenda y abagore',
        7  => 'epicerie|épicerie|alimentation|nourriture|ibiribwa|ibiryo',
        8  => 'beaute|beauté|cosmetique|cosmétique|kwisiga',
        9  => 'sportif|sportswear|siporo',
        10 => 'bebe|bébé|jouet|jouets|umwana|abana',
        11 => 'meuble|meubles|ameublement',
        12 => 'voiture|voitures|imodoka',
        13 => 'livre|livres|papeterie|ibitabo',
        14 => 'montre|montres|bijou|bijoux|isaha',
        15 => 'jeu|jeux|imikino',
    ];
    foreach ($localized as $id => $pattern) {
        $map[$id] .= '|' . $pattern;
    }
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

function formatProducts(array $rows, string $label = '', bool $showDesc = false, string $lang = 'en'): array {
    if (empty($rows)) return ['text' => '', 'qr' => []];
    $defaultHeading = $lang === 'fr'
        ? "Voici une sélection pertinente :"
        : ($lang === 'rw' ? "Dore ibikubereye :" : "Here are some matching products:");
    $stockSuffix = $lang === 'fr'
        ? 'en stock'
        : ($lang === 'rw' ? 'biri muri stock' : 'in stock');
    $descPrefix = $lang === 'fr'
        ? 'Résumé'
        : ($lang === 'rw' ? 'Ibisobanuro' : 'Summary');
    $browseLabel = $lang === 'fr'
        ? 'Voir tous les produits'
        : ($lang === 'rw' ? 'Reba ibicuruzwa byose' : 'Browse all products');

    $out = $label ? "🛍️ <strong>$label</strong><br>" : "🛍️ <strong>$defaultHeading</strong><br>";
    $qr  = [];
    foreach ($rows as $p) {
        $out .= "• <a href='" . SITE_URL . "/product.php?id={$p['id']}'><strong>" . htmlspecialchars($p['name']) . "</strong></a>"
              . ($p['brand'] ? " <em>({$p['brand']})</em>" : '')
              . " — <strong>RWF " . number_format($p['price']) . "</strong>"
              . " | " . $p['stock'] . " " . $stockSuffix;
        if ($showDesc && !empty($p['description'])) {
            $desc = mb_substr(strip_tags($p['description']), 0, 80);
            $out .= "<br><small style='color:rgba(255,255,255,.6)'>📝 " . $descPrefix . ": " . htmlspecialchars($desc) . "...</small>";
        }
        $out .= "<br>";
        $qr[] = "🛒 Add: add_to_cart:{$p['id']}";
    }
    $out .= "<a href='" . SITE_URL . "/products.php'>" . $browseLabel . " →</a>";
    return ['text' => $out, 'qr' => array_slice($qr, 0, 4)];
}

// ── Full detail for a single product ──
function formatProductDetail(array $p): string {
    $stars = '';
    $out  = "🛍️ <strong><a href='" . SITE_URL . "/product.php?id={$p['id']}'>" . htmlspecialchars($p['name']) . "</a></strong><br>";
    if (!empty($p['brand']))   $out .= "🏷️ Brand: <strong>" . htmlspecialchars($p['brand']) . "</strong><br>";
    if (!empty($p['cat']))     $out .= "📂 Category: " . htmlspecialchars($p['cat']) . "<br>";
    $out .= "💰 Price: <strong>RWF " . number_format($p['price']) . "</strong><br>";
    $out .= "📦 Stock: <strong>" . $p['stock'] . " units available</strong><br>";
    if (!empty($p['description'])) {
        $desc = mb_substr(strip_tags($p['description']), 0, 200);
        $out .= "📝 <em>" . htmlspecialchars($desc) . (strlen($p['description']) > 200 ? '...' : '') . "</em><br>";
    }
    $out .= "<a href='" . SITE_URL . "/product.php?id={$p['id']}'>View full details →</a>";
    return $out;
}

// ── Category summary from DB ──
function getCategorySummary($conn): string {
    $res = $conn->query("SELECT c.name, COUNT(p.id) as total, MIN(p.price) as mn, MAX(p.price) as mx
        FROM categories c LEFT JOIN products p ON p.category_id=c.id AND p.stock>0
        GROUP BY c.id ORDER BY total DESC");
    $out = "🏪 <strong>Our Store — Products by Category:</strong><br>";
    $grandTotal = 0;
    while ($r = $res->fetch_assoc()) {
        if ($r['total'] > 0) {
            $out .= "• <strong>" . htmlspecialchars($r['name']) . "</strong>: {$r['total']} products"
                  . " (RWF " . number_format($r['mn']) . " – RWF " . number_format($r['mx']) . ")<br>";
            $grandTotal += $r['total'];
        }
    }
    $out .= "<br>📊 <strong>Total: $grandTotal products in stock</strong><br>";
    $out .= "<a href='" . SITE_URL . "/products.php'>Browse all →</a>";
    return $out;
}

// ================================================================
// MAIN PROCESSOR — every intent handled natively from DB
// ================================================================
function getStoreSnapshotData($conn): array {
    $snapshot = [
        'products' => 0,
        'categories' => 0,
        'brands' => 0,
        'min_price' => 0,
        'max_price' => 0,
        'top_categories' => [],
    ];

    $stats = $conn->query("
        SELECT
            COUNT(*) AS products,
            COUNT(DISTINCT category_id) AS categories,
            COUNT(DISTINCT NULLIF(TRIM(brand), '')) AS brands,
            MIN(price) AS min_price,
            MAX(price) AS max_price
        FROM products
        WHERE stock > 0
    ");
    if ($stats && ($row = $stats->fetch_assoc())) {
        $snapshot['products'] = (int)($row['products'] ?? 0);
        $snapshot['categories'] = (int)($row['categories'] ?? 0);
        $snapshot['brands'] = (int)($row['brands'] ?? 0);
        $snapshot['min_price'] = (float)($row['min_price'] ?? 0);
        $snapshot['max_price'] = (float)($row['max_price'] ?? 0);
    }

    $topCategories = $conn->query("
        SELECT c.name, COUNT(p.id) AS total
        FROM categories c
        LEFT JOIN products p ON p.category_id = c.id AND p.stock > 0
        GROUP BY c.id
        HAVING total > 0
        ORDER BY total DESC, c.name ASC
        LIMIT 5
    ");
    if ($topCategories) {
        while ($row = $topCategories->fetch_assoc()) {
            $snapshot['top_categories'][] = [
                'name' => $row['name'],
                'total' => (int)$row['total'],
            ];
        }
    }

    return $snapshot;
}

function getStoreOverviewText($conn, string $lang = 'en'): string {
    $snapshot = getStoreSnapshotData($conn);
    $topCategories = [];
    foreach ($snapshot['top_categories'] as $category) {
        $topCategories[] = htmlspecialchars($category['name']) . ' (' . $category['total'] . ')';
    }
    $topCategoriesText = !empty($topCategories) ? implode(', ', $topCategories) : 'Catalog categories';

    if ($lang === 'fr') {
        return "🏬 <strong>Aperçu de " . SITE_NAME . " :</strong><br>" .
            "• Catalogue en direct : <strong>" . number_format($snapshot['products']) . "</strong> produits en stock<br>" .
            "• Couverture : <strong>" . number_format($snapshot['categories']) . "</strong> catégories et <strong>" . number_format($snapshot['brands']) . "</strong> marques<br>" .
            "• Gamme de prix : <strong>RWF " . number_format($snapshot['min_price']) . " - RWF " . number_format($snapshot['max_price']) . "</strong><br>" .
            "• Catégories phares : " . $topCategoriesText . "<br><br>" .
            "Je peux répondre aux questions sur les produits, prix, stocks, livraisons, paiements, retours, recommandations par budget et suivi de commande.<br>" .
            "Pour les questions complexes en français, anglais ou kinyarwanda, j'utilise aussi l'assistance Gemini avec le contexte de la boutique.";
    }

    if ($lang === 'rw') {
        return "🏬 <strong>Incamake ya " . SITE_NAME . ":</strong><br>" .
            "• Dufite <strong>" . number_format($snapshot['products']) . "</strong> ibicuruzwa biri mu bubiko<br>" .
            "• Hari <strong>" . number_format($snapshot['categories']) . "</strong> ibyiciro na <strong>" . number_format($snapshot['brands']) . "</strong> brands<br>" .
            "• Ibiciro biri hagati ya <strong>RWF " . number_format($snapshot['min_price']) . " - RWF " . number_format($snapshot['max_price']) . "</strong><br>" .
            "• Ibyiciro bikomeye: " . $topCategoriesText . "<br><br>" .
            "Nshobora kugufasha ku bicuruzwa, ibiciro, stock, delivery, payment, returns, gukurikirana commande no kuguhuza n'ibikubereye ku ngengo y'imari yawe.<br>" .
            "Ibibazo bikomeye mu Cyongereza, Igifaransa cyangwa Ikinyarwanda bishyigikirwa na Gemini ariko bikaguma bishingiye ku makuru y'ububiko.";
    }

    return "🏬 <strong>" . SITE_NAME . " platform overview:</strong><br>" .
        "• Live catalog: <strong>" . number_format($snapshot['products']) . "</strong> in-stock products<br>" .
        "• Coverage: <strong>" . number_format($snapshot['categories']) . "</strong> categories and <strong>" . number_format($snapshot['brands']) . "</strong> brands<br>" .
        "• Price range: <strong>RWF " . number_format($snapshot['min_price']) . " - RWF " . number_format($snapshot['max_price']) . "</strong><br>" .
        "• Top categories: " . $topCategoriesText . "<br><br>" .
        "I can answer product, price, stock, delivery, payment, return, order-tracking and support questions directly from the platform data.";
}

function getOrderGuideText(?int $uid, string $lang = 'en'): string {
    if ($lang === 'fr') {
        if ($uid) {
            return "🛒 <strong>Comment commander via le chatbot :</strong><br>" .
                "1. Demandez un produit ou un budget, par exemple <em>montrez-moi des téléphones sous 200k</em>.<br>" .
                "2. Dites <em>je veux [produit]</em> ou utilisez le bouton <strong>Add to cart</strong>.<br>" .
                "3. Choisissez la quantité.<br>" .
                "4. Saisissez l'adresse de livraison.<br>" .
                "5. Choisissez le paiement : COD, MTN MoMo, Airtel Money, carte ou virement.<br>" .
                "6. Tapez <strong>confirm</strong> pour finaliser, puis suivez la commande depuis <a href='" . SITE_URL . "/orders.php'>Mes commandes</a>.";
        }

        return "🛒 <strong>Processus de commande pour les visiteurs :</strong><br>" .
            "1. Utilisez le chatbot pour chercher des produits, comparer les prix ou donner votre budget.<br>" .
            "2. Ouvrez la fiche produit pour voir les détails.<br>" .
            "3. <a href='" . SITE_URL . "/register.php'><strong>Créez un compte gratuit</strong></a> ou <a href='" . SITE_URL . "/login.php'><strong>connectez-vous</strong></a> avant de commander, suivre, annuler ou télécharger une facture.<br>" .
            "4. Ajoutez le produit au panier ou dites <em>je veux [produit]</em> après connexion.<br>" .
            "5. Confirmez quantité, adresse et mode de paiement.<br>" .
            "6. Finalisez la commande puis suivez-la dans votre compte.";
    }

    if ($lang === 'rw') {
        if ($uid) {
            return "🛒 <strong>Uko gutumiza bikora muri chatbot:</strong><br>" .
                "1. Mbwira igicuruzwa ushaka cyangwa budget yawe, nko kuvuga <em>nyereka telefoni ziri munsi ya 200k</em>.<br>" .
                "2. Vuga <em>ndashaka [izina ry'igicuruzwa]</em> cyangwa ukoreshe <strong>Add to cart</strong>.<br>" .
                "3. Hitamo umubare ushaka.<br>" .
                "4. Andika aho ushaka ko bikugeraho.<br>" .
                "5. Hitamo payment: COD, MTN MoMo, Airtel Money, card cyangwa bank transfer.<br>" .
                "6. Andika <strong>confirm</strong> kugira ngo order ishyirweho, hanyuma uyikurikirane kuri <a href='" . SITE_URL . "/orders.php'>My Orders</a>.";
        }

        return "🛒 <strong>Uko umushyitsi ashobora gutumiza:</strong><br>" .
            "1. Banza ukoreshe chatbot gushaka ibicuruzwa, kugereranya ibiciro cyangwa kuvuga amafaranga ufite.<br>" .
            "2. Reba page y'igicuruzwa kugira ngo ubone ibisobanuro byose.<br>" .
            "3. <a href='" . SITE_URL . "/register.php'><strong>Fungura konti ku buntu</strong></a> cyangwa <a href='" . SITE_URL . "/login.php'><strong>injira</strong></a> mbere yo gutumiza, gukurikirana order, kuyihagarika cyangwa kubona invoice.<br>" .
            "4. Nyuma yo kwinjira, shyira igicuruzwa muri cart cyangwa uvuge <em>ndashaka [igicuruzwa]</em>.<br>" .
            "5. Emeza quantity, address na payment method.<br>" .
            "6. Kanda confirm hanyuma ukurikirane order yawe muri konti.";
    }

    if ($uid) {
        return "🛒 <strong>How ordering works in chat:</strong><br>" .
            "1. Ask for a product or give me a budget, for example <em>show me phones under 200k</em>.<br>" .
            "2. Say <em>I want [product]</em> or use the <strong>Add to cart</strong> button.<br>" .
            "3. Choose the quantity.<br>" .
            "4. Enter your delivery address.<br>" .
            "5. Choose your payment method: COD, MTN MoMo, Airtel Money, card, or bank transfer.<br>" .
            "6. Type <strong>confirm</strong> to place the order, then track it from <a href='" . SITE_URL . "/orders.php'>My Orders</a>.";
    }

    return "🛒 <strong>How ordering works for guests:</strong><br>" .
        "1. Use the chatbot to search products, compare prices, or share your budget.<br>" .
        "2. Open the product page to review the details you want.<br>" .
        "3. <a href='" . SITE_URL . "/register.php'><strong>Create a free account</strong></a> or <a href='" . SITE_URL . "/login.php'><strong>login</strong></a> before placing, tracking, cancelling orders, or downloading invoices.<br>" .
        "4. After login, add the item to cart or say <em>I want [product]</em>.<br>" .
        "5. Confirm quantity, address, and payment method.<br>" .
        "6. Finalize the order and follow it from your account.";
}

function getCapabilityShowcaseText($conn, ?int $uid, string $lang = 'en'): string {
    $snapshot = getStoreSnapshotData($conn);
    $guestLineEn = $uid
        ? "Because you're logged in, I can also guide you through cart, checkout, order tracking, invoices, and support."
        : "Guests can browse and ask questions freely, and after login I can help with orders, tracking, invoices, and cancellations.";
    $guestLineFr = $uid
        ? "Comme vous êtes connecté, je peux aussi vous guider pour le panier, le checkout, le suivi, les factures et le support."
        : "Les visiteurs peuvent explorer librement, puis après connexion je peux aider pour les commandes, le suivi, les factures et les annulations.";
    $guestLineRw = $uid
        ? "Kubera ko winjiye, nshobora no kugufasha kuri cart, checkout, gukurikirana order, invoice na support."
        : "Abashyitsi bashobora gushaka amakuru yose ku bicuruzwa; nyuma yo kwinjira, nshobora gufasha no kuri order, tracking, invoice no guhagarika order.";

    if ($lang === 'fr') {
        return "🤖 <strong>Assistant IA de " . SITE_NAME . "</strong><br>" .
            "Je suis connecté aux données en direct de la boutique : <strong>" . number_format($snapshot['products']) . "</strong> produits en stock, <strong>" . number_format($snapshot['categories']) . "</strong> catégories et <strong>" . number_format($snapshot['brands']) . "</strong> marques.<br><br>" .
            "Je peux :<br>" .
            "• trouver des produits, prix, stocks et catégories<br>" .
            "• recommander selon votre budget<br>" .
            "• expliquer livraison, paiement, retours et support<br>" .
            "• répondre en anglais, français ou kinyarwanda<br><br>" .
            $guestLineFr;
    }

    if ($lang === 'rw') {
        return "🤖 <strong>AI shopping assistant ya " . SITE_NAME . "</strong><br>" .
            "Nkomeretswe ku makuru y'ububiko mu buryo bwa live: hari <strong>" . number_format($snapshot['products']) . "</strong> ibicuruzwa biri muri stock, <strong>" . number_format($snapshot['categories']) . "</strong> ibyiciro na <strong>" . number_format($snapshot['brands']) . "</strong> brands.<br><br>" .
            "Nshobora:<br>" .
            "• kukwereka ibicuruzwa, ibiciro, stock n'ibyiciro<br>" .
            "• kukugenera products zishingiye kuri budget yawe<br>" .
            "• gusobanura delivery, payment, returns na support<br>" .
            "• kuvugana nawe mu Cyongereza, Igifaransa no mu Kinyarwanda<br><br>" .
            $guestLineRw;
    }

    return "🤖 <strong>AI Shopping Assistant</strong><br>" .
        "I'm connected to live store data: <strong>" . number_format($snapshot['products']) . "</strong> in-stock products, <strong>" . number_format($snapshot['categories']) . "</strong> categories, and <strong>" . number_format($snapshot['brands']) . "</strong> brands.<br><br>" .
        "I can:<br>" .
        "• search products, prices, stock, and categories<br>" .
        "• recommend products based on budget<br>" .
        "• explain delivery, payment, returns, and support policies<br>" .
        "• answer in English, French, or Kinyarwanda<br><br>" .
        $guestLineEn;
}

function getLocalizedPrimaryReplies(string $lang, ?int $uid): array {
    if ($lang === 'fr') {
        return $uid
            ? ['Voir produits', 'Suivre ma commande', 'Mes commandes']
            : ['Voir produits', 'Comment commander', 'Créer un compte'];
    }

    if ($lang === 'rw') {
        return $uid
            ? ['Nyereka products', 'Kurikirana order', 'Orders zanjye']
            : ['Nyereka products', 'Uko gutumiza bikorwa', 'Fungura konti'];
    }

    return $uid
        ? ['Show me products', 'Track my order', 'My orders']
        : ['Show me products', 'How to order', 'Register free'];
}

function getOrderGuideQuickReplies(string $lang, ?int $uid): array {
    if ($lang === 'fr') {
        return $uid
            ? ['Voir produits', 'Mon panier', 'Livraison']
            : ['Voir produits', 'Créer un compte', 'Se connecter'];
    }

    if ($lang === 'rw') {
        return $uid
            ? ['Nyereka products', 'Cart yanjye', 'Delivery']
            : ['Nyereka products', 'Fungura konti', 'Injira'];
    }

    return $uid
        ? ['Show me products', 'My cart', 'Delivery info']
        : ['Show me products', 'Register free', 'Login'];
}

function getCapabilityExamplesText(string $lang = 'en'): string {
    if ($lang === 'fr') {
        return "<strong>Exemples :</strong><br>" .
            "• <em>montrez-moi des téléphones sous 200k</em><br>" .
            "• <em>quel est le prix du Samsung Galaxy A54</em><br>" .
            "• <em>comment commander en tant qu'invité</em>";
    }

    if ($lang === 'rw') {
        return "<strong>Urugero:</strong><br>" .
            "• <em>nyereka telefoni ziri munsi ya 200k</em><br>" .
            "• <em>igiciro cya Samsung Galaxy A54 ni angahe</em><br>" .
            "• <em>umushyitsi yatumiza ate</em>";
    }

    return "<strong>Examples:</strong><br>" .
        "• <em>show me phones under 200k</em><br>" .
        "• <em>price of Samsung Galaxy A54</em><br>" .
        "• <em>how can a guest place an order</em>";
}

function getBudgetLabelText(?int $minPrice, ?int $maxPrice, string $lang, string $categoryName = ''): string {
    if ($lang === 'fr') {
        if ($minPrice && $maxPrice) {
            return "Produits entre RWF " . number_format($minPrice) . " et RWF " . number_format($maxPrice);
        }
        if ($maxPrice) {
            $label = "Produits dans votre budget de RWF " . number_format($maxPrice);
            return $categoryName !== '' ? $label . " (" . $categoryName . ")" : $label;
        }
        if ($minPrice) {
            return "Produits à partir de RWF " . number_format($minPrice);
        }
    }

    if ($lang === 'rw') {
        if ($minPrice && $maxPrice) {
            return "Ibicuruzwa biri hagati ya RWF " . number_format($minPrice) . " na RWF " . number_format($maxPrice);
        }
        if ($maxPrice) {
            $label = "Ibicuruzwa bihuye na budget ya RWF " . number_format($maxPrice);
            return $categoryName !== '' ? $label . " (" . $categoryName . ")" : $label;
        }
        if ($minPrice) {
            return "Ibicuruzwa bitangirira kuri RWF " . number_format($minPrice);
        }
    }

    if ($minPrice && $maxPrice) {
        return "Products between RWF " . number_format($minPrice) . " and RWF " . number_format($maxPrice);
    }
    if ($maxPrice) {
        $label = "Products within your budget of RWF " . number_format($maxPrice);
        return $categoryName !== '' ? $label . " (" . $categoryName . ")" : $label;
    }
    if ($minPrice) {
        return "Products above RWF " . number_format($minPrice);
    }

    return '';
}

function getBudgetQuickReplies(string $lang, string $variant = 'default'): array {
    if ($lang === 'fr') {
        return $variant === 'fallback'
            ? ['Voir des options moins chères', 'Voir produits', 'Autre catégorie']
            : ['Voir plus', 'Autre catégorie', 'Livraison'];
    }

    if ($lang === 'rw') {
        return $variant === 'fallback'
            ? ['Nyereka ibihendutse', 'Nyereka products', 'Ikindi cyiciro']
            : ['Nyereka ibindi', 'Ikindi cyiciro', 'Delivery'];
    }

    return $variant === 'fallback'
        ? ['Show me cheaper options', 'Show me products', 'Different category']
        : ['Show me more', 'Different category', 'Delivery info'];
}

function processMessage(string $msg, ?int $uid, $conn, array &$ctx, string $session_id): array {
    $ml = strtolower(trim($msg));

    // ── Awaiting order number from previous turn ──
    if ($ctx['awaiting'] === 'order_number' && preg_match('/#?0*(\d+)\b/', $msg, $m)) {
        $ctx['awaiting'] = null;
        return reply(trackOrder((int)$m[1], $uid, $conn), ['View all orders', 'Cancel an order']);
    }

    // ── DB + regex intents first (fast). Gemini runs only as a late fallback below.

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
        // ── Save to support_tickets table ──
        $safeName    = $conn->real_escape_string($customerName);
        $safeEmail   = $conn->real_escape_string($customerEmail);
        $safeMsg     = $conn->real_escape_string($supportMsg);
        $safeSid     = $conn->real_escape_string($session_id);
        $ui          = $uid ? (int)$uid : 'NULL';
        $conn->query("INSERT INTO support_tickets (user_id, session_id, customer_name, customer_email, message) VALUES ($ui, '$safeSid', '$safeName', '$safeEmail', '$safeMsg')");

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
    if (preg_match('/\b(how to order|how do i order|how to place an order|how to place order|ordering process|steps to order|steps to place an order|guide to order|guide to buy|can i order as a guest|can i place an order as a guest|order as a guest|place an order as a guest|how can a guest place an order|how does guest ordering work|comment commander|comment acheter|comment passer commande|commander en tant quinvite|commander en tant qu\'invite|etapes pour commander|processus de commande|ni gute nagura|uburyo bwo kugura|intambwe zo kugura|nategeka nte|natumiza nte|guest order|can a guest order|umushyitsi yatumiza ate)\b/i', $ml)) {
        $lang = detectLanguage($msg);
        return reply(
            getOrderGuideText($uid, $lang),
            getOrderGuideQuickReplies($lang, $uid)
        );
    }

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
        // If message also contains a product/service request, skip greeting and let the right handler respond
        $hasProductRequest = preg_match('/\b(need|want|looking|find|show|search|buy|price|under|budget|smartphone|phone|laptop|tv|product|order|track|delivery|payment|return|invoice|cancel)\b/i', $ml);
        if ($hasProductRequest) {
            // Fall through to product search handlers below
        } else {
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
            // ── Remember last interest from chat history ──
            $lastInterest = '';
            $lastMsg = $conn->query("SELECT message FROM chatbot_logs WHERE user_id=$uid ORDER BY created_at DESC LIMIT 5");
            if ($lastMsg) {
                while ($lm = $lastMsg->fetch_assoc()) {
                    $catId = detectCategory(strtolower($lm['message']));
                    if ($catId) {
                        $catName = $conn->query("SELECT name FROM categories WHERE id=$catId")->fetch_assoc()['name'] ?? '';
                        if ($catName) { $lastInterest = $catName; break; }
                    }
                }
            }
            
            // Prepare common variables
            $interestLine = $lastInterest ? "<br>💡 Last time you were browsing <strong>$lastInterest</strong> — want to continue?" : '';
            
            // Detect language from current message
            $lang = detectLanguage($msg);
            
            if ($lang === 'rw') {
                // Kinyarwanda response
                return reply(
                    "🇷🇼 <strong>Muraho $name!</strong> Nezeza kubona!<br>" .
                    "Ufite konti <strong>$oCount" . ($oCount != 1 ? ' z' : ' y') . "</strong>" . $orderLine . "<br><br>" .
                    "Nabagufasha iki?",
                    ['🛍️ Nderagura...', '❓ Kuri konti', '🚚 Delivery']
                );
            } elseif ($lang === 'fr') {
                // French response
                return reply(
                    "🇫🇷 <strong>Bonjour $name!</strong> Ravi de vous revoir!<br>" .
                    "Vous avez <strong>$oCount commande" . ($oCount != 1 ? 's' : '') . "</strong>" . $orderLine . "<br><br>" .
                    "Comment puis-je vous aider?",
                    ['🛍️ Voir produits', '❓ Mes commandes', '🚚 Livraison']
                );
            } else {
                // English response (default)
                return reply(
                    "👋 Welcome back, <strong>$name</strong>! Great to see you at <strong>" . SITE_NAME . "</strong>.<br>" .
                    "You have <strong>$oCount order" . ($oCount != 1 ? 's' : '') . "</strong> with us." . $orderLine . $interestLine . "<br><br>" .
                    "How can I help you today?",
                    $lastInterest
                        ? ['Show me ' . strtolower($lastInterest), 'Track my order', 'My orders', 'Contact support']
                        : ['Show me products', 'Track my order', 'My orders', 'Contact support']
                );
            }
        } else {
            // ── Guest ──
            $lang = detectLanguage($msg);
            return reply(
                getCapabilityShowcaseText($conn, null, $lang) . "<br><br>" . getCapabilityExamplesText($lang),
                getLocalizedPrimaryReplies($lang, null)
            );
        }
        } // end hasProductRequest check
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
        $lang = detectLanguage($msg);
        return reply(
            getCapabilityShowcaseText($conn, $uid, $lang) . "<br><br>" . getCapabilityExamplesText($lang),
            getLocalizedPrimaryReplies($lang, $uid)
        );
    }
    if (preg_match('/tell me about (this platform|this store|this shop)|what do you know about (this platform|this store|this shop)|platform overview|store overview|shop overview|about this ecommerce platform|about your platform|how many products do you have|how many categories do you have|what categories do you have|what brands do you have|what do you sell here|catalog overview|parlez moi de cette plateforme|informations sur la boutique|combien de produits avez vous|quelles categories avez vous|que vendez vous ici|mbwira ibijyanye n(uru rubuga|iri duka)|amakuru y(ububiko|urubuga)|mufite ibicuruzwa bingahe|mugurisha iki/i', $ml)) {
        $lang = detectLanguage($msg);
        return reply(
            getStoreOverviewText($conn, $lang),
            getLocalizedPrimaryReplies($lang, $uid)
        );
    }

    if (preg_match('/what can you do|how can you help|help me/i', $ml)) {
        $lang = detectLanguage($msg);
        return reply(
            getCapabilityShowcaseText($conn, $uid, $lang) . "<br><br>" . getCapabilityExamplesText($lang),
            getLocalizedPrimaryReplies($lang, $uid)
        );
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

    // ── 6b. INVOICE DOWNLOAD ──
    if (preg_match('/\b(invoice|download invoice|get invoice|print invoice|receipt)\b/i', $ml)) {
        if (!$uid) return reply("🔒 Please <a href='" . SITE_URL . "/login.php'>login</a> to access your invoices.");
        if (preg_match('/#?0*(\d+)\b/', $msg, $m)) {
            $oid = (int)$m[1];
            $chk = $conn->query("SELECT id FROM orders WHERE id=$oid AND user_id=$uid LIMIT 1")->fetch_assoc();
            if ($chk) {
                return reply(
                    "🧾 <strong>Invoice for Order #" . str_pad($oid,6,'0',STR_PAD_LEFT) . "</strong><br><br>" .
                    "<a href='" . SITE_URL . "/invoice.php?id=$oid' target='_blank'><strong>📄 Download / Print Invoice →</strong></a>",
                    ['My orders', 'Track my order']
                );
            }
            return reply("❌ Order #$oid not found under your account.", ['My orders']);
        }
        // No order number — show list
        $res = $conn->query("SELECT id FROM orders WHERE user_id=$uid ORDER BY created_at DESC LIMIT 5");
        $links = '';
        while ($o = $res->fetch_assoc()) {
            $num = str_pad($o['id'],6,'0',STR_PAD_LEFT);
            $links .= "• <a href='" . SITE_URL . "/invoice.php?id={$o['id']}' target='_blank'>Invoice #$num →</a><br>";
        }
        return reply(
            $links
                ? "🧾 <strong>Your Recent Invoices:</strong><br><br>$links"
                : "You have no orders yet.",
            ['My orders', 'Track my order']
        );
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

    // ── 15b. CATEGORY COUNT / HOW MANY PRODUCTS ──
    if (preg_match('/\b(how many products|how many items|total products|number of products|what categories|list categories|all categories|what do you sell|what products do you have|what do you have|mufitemo|zingahe|ni zingahe|ibicuruzwa zingahe|ibicuruzwa bingahe|ni bingahe|combien de produits|combien d\'articles|quelles categories|que vendez vous)\b/i', $ml)) {
        return reply(getCategorySummary($conn),
            ['Show me phones', 'Show me laptops', 'Show me fashion', 'Show me products']);
    }

    // ── 15b2. PRODUCT COMPARISON ──
    if (preg_match('/\b(compare|vs|versus|difference between|which is better|which one is better)\b/i', $ml)) {
        // Extract two product names — split on "and", "vs", "versus", "or"
        $parts = preg_split('/\b(and|vs\.?|versus|or)\b/i', $ml, 2);
        if (count($parts) === 2) {
            $rows1 = dbProductSearch(trim($parts[0]), $conn);
            $rows2 = dbProductSearch(trim($parts[1]), $conn);
            if (!empty($rows1) && !empty($rows2)) {
                $p1 = $rows1[0]; $p2 = $rows2[0];
                $out = "⚖️ <strong>Product Comparison:</strong><br><br>";
                $out .= "<table style='width:100%;font-size:.82rem;border-collapse:collapse'>";
                $out .= "<tr style='background:rgba(255,255,255,.1)'><th style='padding:6px;text-align:left'>Feature</th><th style='padding:6px;text-align:center'>" . htmlspecialchars($p1['name']) . "</th><th style='padding:6px;text-align:center'>" . htmlspecialchars($p2['name']) . "</th></tr>";
                $fields = [
                    'Brand'    => ['brand','brand'],
                    'Price'    => ['price','price'],
                    'Stock'    => ['stock','stock'],
                    'Category' => ['cat','cat'],
                ];
                foreach ($fields as $label => [$f1,$f2]) {
                    $v1 = $f1==='price' ? 'RWF '.number_format($p1[$f1]) : htmlspecialchars($p1[$f1] ?? 'N/A');
                    $v2 = $f2==='price' ? 'RWF '.number_format($p2[$f2]) : htmlspecialchars($p2[$f2] ?? 'N/A');
                    // Highlight cheaper price
                    if ($f1==='price') {
                        if ($p1['price'] < $p2['price']) $v1 = "<strong style='color:#4caf50'>$v1 ✓</strong>";
                        elseif ($p2['price'] < $p1['price']) $v2 = "<strong style='color:#4caf50'>$v2 ✓</strong>";
                    }
                    $out .= "<tr style='border-bottom:1px solid rgba(255,255,255,.08)'><td style='padding:6px;color:rgba(255,255,255,.6)'>$label</td><td style='padding:6px;text-align:center'>$v1</td><td style='padding:6px;text-align:center'>$v2</td></tr>";
                }
                $out .= "</table><br>";
                $out .= "<a href='" . SITE_URL . "/product.php?id={$p1['id']}'>View {$p1['name']} →</a> | ";
                $out .= "<a href='" . SITE_URL . "/product.php?id={$p2['id']}'>View {$p2['name']} →</a>";
                return reply($out, ["🛒 Add: add_to_cart:{$p1['id']}", "🛒 Add: add_to_cart:{$p2['id']}"]);
            }
        }
        return reply("To compare products, type: <em>compare iPhone 14 and Samsung S23</em>", ['Show me products']);
    }

    // ── 15c. SINGLE PRODUCT FULL DETAIL ──
    // Triggered when customer asks about a specific product by name with detail keywords
    if (preg_match('/\b(tell me about|describe|details of|more about|info about|information about|specs of|specification|features of|what is|about the)\b/i', $ml)) {
        $rows = dbProductSearch($msg, $conn);
        if (!empty($rows)) {
            $p = $rows[0];
            return reply(
                formatProductDetail($p),
                ['🛒 Add: add_to_cart:' . $p['id'], 'Show similar products', 'Check price']
            );
        }
    }

    // ── 15d. BUDGET-BASED SEARCH ──
    // "I have 50000 RWF" / "my budget is 200k" / "I want to spend 100k"
    if (preg_match('/\b(i have|my budget|i want to spend|i can spend|i only have|with|budget of|afford|i got|mon budget|je peux payer|je veux depenser|je veux dépenser|j ai|j\'ai|moins de|plus de|mfite|nfite|amafaranga|budget yanjye|nshobora kwishyura|ndi gushaka spending)\b/i', $ml)
        && preg_match('/\d/', $ml)) {
        $lang = detectLanguage($msg);
        [$minP, $maxP] = extractPriceRange($ml);
        // If no range found, try to extract a plain number as max budget
        if (!$maxP && !$minP) {
            if (preg_match('/(\d+)\s*(k|m)?/i', $ml, $bm)) {
                $n = (int)$bm[1];
                $mult = !empty($bm[2]) && strtolower($bm[2])==='m' ? 1000000 : (!empty($bm[2]) || $n <= 9999 ? 1000 : 1);
                $maxP = $n * $mult;
            }
        }
        if ($maxP) {
            $catId = detectCategory($ml);
            // Search within budget
            $conds = ["p.stock > 0", "p.price <= $maxP"];
            if ($catId) $conds[] = "p.category_id = $catId";
            $res = $conn->query("SELECT p.id,p.name,p.brand,p.price,p.stock,p.description,c.name AS cat
                FROM products p LEFT JOIN categories c ON p.category_id=c.id
                WHERE " . implode(' AND ', $conds) . " ORDER BY p.price DESC LIMIT 8");
            $rows = [];
            if ($res) while ($r = $res->fetch_assoc()) $rows[] = $r;

            if (!empty($rows)) {
                $label = getBudgetLabelText($minP, $maxP, $lang, $catId ? ($rows[0]['cat'] ?? '') : '');
                $fp = formatProducts($rows, $label, true, $lang);
                return reply($fp['text'], array_merge($fp['qr'], getBudgetQuickReplies($lang)));
            }

            // Nothing found — recommend closest products above budget
            $res2 = $conn->query("SELECT p.id,p.name,p.brand,p.price,p.stock,p.description,c.name AS cat
                FROM products p LEFT JOIN categories c ON p.category_id=c.id
                WHERE p.stock > 0 AND p.price > $maxP
                " . ($catId ? "AND p.category_id=$catId" : "") . "
                ORDER BY p.price ASC LIMIT 5");
            $alt = [];
            if ($res2) while ($r = $res2->fetch_assoc()) $alt[] = $r;

            if (!empty($alt)) {
                $fallbackLabel = $lang === 'fr'
                    ? "Aucun produit sous RWF " . number_format($maxP) . " — voici les options les plus proches :"
                    : ($lang === 'rw'
                        ? "Nta bicuruzwa biri munsi ya RWF " . number_format($maxP) . " — ariko dore ibikwegereye:"
                        : "No products found under RWF " . number_format($maxP) . " — but here are the closest options:");
                $fp = formatProducts($alt, $fallbackLabel, true, $lang);
                $intro = $lang === 'fr'
                    ? "Nous n'avons pas de produits dans votre budget de <strong>RWF " . number_format($maxP) . "</strong>" . ($catId ? " pour cette catégorie" : "") . " pour le moment.<br><br>Voici les options les plus abordables proches de votre budget :<br>"
                    : ($lang === 'rw'
                        ? "Kuri ubu nta bicuruzwa bihuye na budget yawe ya <strong>RWF " . number_format($maxP) . "</strong>" . ($catId ? " muri icyo cyiciro" : "") . ".<br><br>Dore ibiciro bya hafi kandi bihendutse kurusha ibindi:<br>"
                        : "We don't have products within <strong>RWF " . number_format($maxP) . "</strong>" . ($catId ? " in that category" : "") . " right now.<br><br>Here are our most affordable options close to your budget:<br>");
                return reply(
                    $intro . $fp['text'],
                    array_merge($fp['qr'], getBudgetQuickReplies($lang, 'fallback'))
                );
            }

            $minAvailable = (int)($conn->query("SELECT MIN(price) as m FROM products WHERE stock>0")->fetch_assoc()['m'] ?? 0);
            $finalPrompt = $lang === 'fr'
                ? "Aucun produit trouvé dans <strong>RWF " . number_format($maxP) . "</strong>.<br>Nos options les plus abordables commencent à <strong>RWF " . number_format($minAvailable) . "</strong>.<br>Voulez-vous les voir ?"
                : ($lang === 'rw'
                    ? "Nta bicuruzwa twabonye muri <strong>RWF " . number_format($maxP) . "</strong>.<br>Ibicuruzwa byacu bihendutse bitangirira kuri <strong>RWF " . number_format($minAvailable) . "</strong>.<br>Wifuza ko nkubyereka?"
                    : "No products found within <strong>RWF " . number_format($maxP) . "</strong>.<br>Our most affordable products start from <strong>RWF " . number_format($minAvailable) . "</strong>.<br>Would you like to see them?");
            return reply(
                $finalPrompt,
                $lang === 'fr'
                    ? ['Voir les produits les moins chers', 'Voir produits']
                    : ($lang === 'rw' ? ['Nyereka ibihendutse', 'Nyereka products'] : ['Show me cheapest products', 'Show me products'])
            );
        }
    }

    // ── 16. PRODUCT PRICE QUERY ──
    if (preg_match('/\b(price of|how much is|cost of|how much does|what is the price|price for|how much.*cost|combien|prix de|quel prix|igiciro cya|ni angahe|bingahe)\b/i', $ml)) {
        $rows = dbProductSearch($msg, $conn);
        if (!empty($rows)) {
            // Single product — show full detail
            if (count($rows) === 1 || preg_match('/\b(price of|how much is|cost of)\b/i', $ml)) {
                $p = $rows[0];
                $out = "💰 <strong><a href='" . SITE_URL . "/product.php?id={$p['id']}'>" . htmlspecialchars($p['name']) . "</a></strong><br>";
                if ($p['brand']) $out .= "🏷️ Brand: {$p['brand']}<br>";
                $out .= "💰 Price: <strong>RWF " . number_format($p['price']) . "</strong><br>";
                $out .= "📦 Stock: {$p['stock']} units<br>";
                if (!empty($p['description'])) $out .= "📝 <em>" . mb_substr(strip_tags($p['description']),0,120) . "...</em><br>";
                $out .= "<a href='" . SITE_URL . "/product.php?id={$p['id']}'>View full details →</a>";
                return reply($out, ["🛒 Add: add_to_cart:{$p['id']}", 'Show similar products', 'Check stock']);
            }
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
            if ($p['stock'] > 0) {
                return reply(
                    "✅ <strong>" . htmlspecialchars($p['name']) . "</strong> is in stock — <strong>" . $p['stock'] . " units</strong> available.<br><a href='" . SITE_URL . "/product.php?id={$p['id']}'>View product →</a>",
                    ['Show similar products', 'Browse all products']
                );
            } else {
                // Out of stock — offer notification
                $notifyBtn = $uid
                    ? "notify_stock:{$p['id']}"
                    : "notify_stock_guest:{$p['id']}";
                return reply(
                    "❌ <strong>" . htmlspecialchars($p['name']) . "</strong> is currently <strong>out of stock</strong>.<br><br>" .
                    "🔔 Would you like to be notified by email when it's back in stock?",
                    ['🔔 Notify me when available', 'Show similar products', 'Browse all products']
                );
            }
        }
        return reply("Which product would you like to check? Type the product name, e.g. <em>is Samsung A54 in stock?</em>");
    }

    // ── Handle stock notification request ──
    if (preg_match('/\bnotify me when available\b|🔔 Notify me/i', $ml)) {
        if (!$uid) {
            return reply(
                "🔒 Please <a href='" . SITE_URL . "/login.php'><strong>login</strong></a> or provide your email to get notified.<br>" .
                "Type your email address and I'll save it:",
                ['Login', 'Register free']
            );
        }
        $u = $conn->query("SELECT email, name FROM users WHERE id=$uid LIMIT 1")->fetch_assoc();
        // Find last out-of-stock product from context
        if (!empty($ctx['last_products'])) {
            $p = $ctx['last_products'][0];
            $pid = (int)$p['id'];
            $safeEmail = $conn->real_escape_string($u['email']);
            $safeName  = $conn->real_escape_string($u['name']);
            $conn->query("INSERT IGNORE INTO stock_notifications (product_id, email, name) VALUES ($pid, '$safeEmail', '$safeName')");
            return reply(
                "🔔 Done! We'll email <strong>" . htmlspecialchars($u['email']) . "</strong> as soon as <strong>" . htmlspecialchars($p['name']) . "</strong> is back in stock.",
                ['Show similar products', 'Browse all products']
            );
        }
        return reply("Please tell me which product you'd like to be notified about.", ['Show me products']);
    }

    // ── 18. RECOMMENDATION ──
    if (preg_match('/\b(recommend|suggest|best|popular|top rated|what should i buy|which is better|advise|good phone|good laptop|best phone|best laptop|best tv|recommande|suggere|suggère|meilleur|populaire|nsabira|wansabira|icyiza)\b/i', $ml)) {
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
    // ── Brand search: "show me all Samsung products" / "Nike products" ──
    if (preg_match('/\b(all|show me|find|search)\b.*\b(\w+)\s+(products?|items?|phones?|laptops?|shoes?|clothes?)\b/i', $ml, $bm)
        || preg_match('/\b(samsung|apple|nokia|tecno|infinix|xiaomi|oppo|vivo|hp|dell|lenovo|asus|acer|lg|sony|jbl|nike|adidas|huawei|bose|philips|panasonic|dyson|kenwood|casio|fossil|lego|pampers|nivea|dove|garnier|colgate|gillette|maybelline|nescafe|lipton|heinz|coca.cola|indomie)\b/i', $ml, $bm)) {
        $brand = $conn->real_escape_string(trim($bm[count($bm)-1]));
        $res = $conn->query("SELECT p.id,p.name,p.brand,p.price,p.stock,p.description,c.name AS cat
            FROM products p LEFT JOIN categories c ON p.category_id=c.id
            WHERE p.stock>0 AND p.brand LIKE '%$brand%' ORDER BY p.price ASC LIMIT 10");
        $rows = [];
        if ($res) while ($r = $res->fetch_assoc()) $rows[] = $r;
        if (!empty($rows)) {
            $ctx['last_products'] = $rows;
            $fp = formatProducts($rows, ucfirst($brand) . ' Products (' . count($rows) . ' found)', true);
            return reply($fp['text'], array_merge($fp['qr'], ['Show me products', 'Show me more']));
        }
    }
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
        $catId = preg_match('/\bmen\b/i', $ml) ? 5 : 6;
        $rows = dbProductSearch('', $conn, $catId);
        if (!empty($rows)) { $ctx['last_products'] = $rows; $fp = formatProducts($rows, $catId===5 ? 'Fashion — Men' : 'Fashion — Women'); return reply($fp['text'], array_merge($fp['qr'], ['Show me phones', 'Show me laptops', 'Show me products'])); }
    }

    // ── Men's fashion — broader match ──
    if (preg_match('/\b(fashion for men|men fashion|men clothes|men clothing|men wear|men style|men outfit|men collection|clothes for men|male fashion|male clothing|gents|gentlemen|men only|for men)\b/i', $ml)) {
        $rows = dbProductSearch('', $conn, 5);
        if (!empty($rows)) { $ctx['last_products'] = $rows; $fp = formatProducts($rows, 'Fashion — Men', true); return reply($fp['text'], array_merge($fp['qr'], ['Show me women fashion', 'Show me products'])); }
    }

    // ── Women's fashion — broader match ──
    if (preg_match('/\b(fashion for women|women fashion|ladies fashion|women clothes|women clothing|female fashion|clothes for women|for women|women only|ladies only)\b/i', $ml)) {
        $rows = dbProductSearch('', $conn, 6);
        if (!empty($rows)) { $ctx['last_products'] = $rows; $fp = formatProducts($rows, 'Fashion — Women', true); return reply($fp['text'], array_merge($fp['qr'], ['Show me men fashion', 'Show me products'])); }
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
        if (!empty($rows)) { $ctx['last_products'] = $rows; $fp = formatProducts($rows, 'Car Accessories'); return reply($fp['text'], array_merge($fp['qr'], ['Show me products'])); }
    }
    if (preg_match('/^show me (games?|gaming|playstation|xbox|consoles?)$/i', trim($ml))) {
        $rows = dbProductSearch('', $conn, 15);
        if (!empty($rows)) { $ctx['last_products'] = $rows; $fp = formatProducts($rows, 'Gaming & Electronics'); return reply($fp['text'], array_merge($fp['qr'], ['Show me phones', 'Show me products'])); }
    }
    if (preg_match('/\b(show me books?|show me stationery|books and stationery|school supplies|office supplies)\b/i', $ml)) {
        $rows = dbProductSearch('', $conn, 13);
        if (!empty($rows)) { $ctx['last_products'] = $rows; $fp = formatProducts($rows, 'Books & Stationery'); return reply($fp['text'], array_merge($fp['qr'], ['Show me products'])); }
    }
    if (preg_match('/\b(show me jewelry|show me watches|jewelry and watches|show me accessories)\b/i', $ml)) {
        $rows = dbProductSearch('', $conn, 14);
        if (!empty($rows)) { $ctx['last_products'] = $rows; $fp = formatProducts($rows, 'Jewelry & Watches'); return reply($fp['text'], array_merge($fp['qr'], ['Show me fashion', 'Show me products'])); }
    }
    if (preg_match('/\b(show me groceries|show me food|groceries|food items|show me snacks)\b/i', $ml)) {
        $rows = dbProductSearch('', $conn, 7);
        if (!empty($rows)) { $ctx['last_products'] = $rows; $fp = formatProducts($rows, 'Groceries & Food'); return reply($fp['text'], array_merge($fp['qr'], ['Show me products'])); }
    }

    // ── 19b. "I WANT [product]" — find product and start cart flow directly ──
    if (preg_match('/\b(i want|i need|buy|purchase|get me|order)\b/i', $ml) && !preg_match('/\b(to buy|to order|to cancel|to track|history|status)\b/i', $ml)) {
        $rows = dbProductSearch($msg, $conn);
        if (!empty($rows)) {
            $p = $rows[0];
            $ctx['last_products'] = $rows;
            if (!$uid) {
                // Show ALL matching products for guest with login prompt
                $fp = formatProducts($rows, 'Smartphones under RWF 100,000', true);
                return reply(
                    $fp['text'] . "<br><br>🔒 <a href='" . SITE_URL . "/login.php'><strong>Login</strong></a> or <a href='" . SITE_URL . "/register.php'><strong>Register free</strong></a> to add to cart and place an order.",
                    ['Login', 'Register', 'Show me more']
                );
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

    // ── 19. PRODUCT SEARCH — show me X, find X, etc. ──
    // ONLY trigger product search if message contains shopping-related keywords
    $shoppingKeywords = ['show', 'find', 'buy', 'order', 'price', 'cost', 'stock', 'available',
                         'sell', 'have', 'product', 'item', 'shop', 'store', 'catalog', 'browse',
                         'montre', 'montrez', 'affiche', 'cherche', 'acheter', 'commande', 'prix',
                         'nyereka', 'erekana', 'gura', 'igiciro', 'ibicuruzwa', 'catalogue'];
    
    $hasShoppingIntent = false;
    foreach ($shoppingKeywords as $kw) {
        if (stripos($ml, $kw) !== false) {
            $hasShoppingIntent = true;
            break;
        }
    }
    
    // Also check for category names or brand names
    $hasCategoryOrBrand = preg_match('/\b(phone|laptop|tablet|shoe|bag|watch|furniture|electronics|clothing|dress|shirt|pants|Samsung|Apple|iPhone|Nike|Adidas|Sony|LG|HP|Dell|lenovo|huawei|tecno|infinix|itel|Mama|Indomie|Inyange|Coca-Cola|Sprite|Fanta)\b/i', $ml);
    
    // Only do product search if message has shopping intent OR category/brand mention
    if ($hasShoppingIntent || $hasCategoryOrBrand || detectCategory($ml) || extractPriceRange($ml) !== [null, null]) {

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

    // ── 20. ML MODEL — Flask classifier (if running): fast intent → DB-grounded reply (no Gemini)
    $mlResult = askMLModel($msg);
    if ($mlResult) {
        $intent     = $mlResult['intent'];
        $confidence = round($mlResult['confidence'] * 100, 1);
        $model_used = $mlResult['model_used'];
        $fast = intentMlFastReply($intent, $msg, $uid, $conn, $ctx);
        if ($fast !== null) {
            return $fast;
        }
    }

    // ── 21. KINYARWANDA FALLBACK (PHP) — before any LLM
    // Common Kinyarwanda shopping phrases mapped to actions
    if (preg_match('/\b(mufitemo|zingahe|bingahe|ni zingahe|ni bingahe|ibicuruzwa zingahe|ibicuruzwa bingahe)\b/i', $ml)) {
        return reply(getCategorySummary($conn), ['Show me phones', 'Show me laptops', 'Show me fashion', 'Show me products']);
    }
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

    // ── 22b. PERSONAL INFO QUESTIONS — Handle account/profile/order queries (Kinyarwanda/English) ──
    // Detect if user is asking about THEIR OWN information (not products)
    $personalKinya = preg_match('/\b(amakuru|wawe|yawe|konti|yange|yanjye|order yanjye|profile yange|email yange|telephone yange|address yange|password|ibyangombwa)\b/i', $ml);
    $personalEnglish = preg_match('/\b(my account|my profile|my orders|my information|my details|my email|my password|personal info|account settings)\b/i', $ml);
    
    if (($personalKinya || $personalEnglish) && $uid) {
        // User is logged in and asking about their personal info
        return reply(
            "👤 <strong>Your Account Information:</strong><br><br>" .
            "• To view your profile and personal details, go to <a href='" . SITE_URL . "/profile.php'><strong>Profile Page →</strong></a><br>" .
            "• To check your orders, visit <a href='" . SITE_URL . "/orders.php'><strong>My Orders →</strong></a><br>" .
            "• To update email or password, use <a href='" . SITE_URL . "/profile.php'><strong>Settings →</strong></a><br><br>" .
            "💡 If you have a specific question, please type it and I'll help!",
            ['My profile', 'My orders', 'Update details', 'Contact support']
        );
    } elseif (($personalKinya || $personalEnglish) && !$uid) {
        // User not logged in
        return reply(
            "🔒 <strong>Please login first:</strong><br><br>" .
            "To access your personal information, you need to be logged in.<br>" .
            "• <a href='" . SITE_URL . "/login.php'><strong>Login here</strong></a><br>" .
            "• Or <a href='" . SITE_URL . "/register.php'><strong>Create a free account</strong></a><br><br>" .
            "This protects your privacy and security! 🔐",
            ['Login', 'Register', 'Forgot password']
        );
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

    // ── 22c. GOOGLE GEMINI — handles complex, multilingual and unmatched queries
    // Gemini is called for: Kinyarwanda, French, complex English, or anything PHP+ML couldn't answer
    $gemini = askGemini($msg, $uid, $conn, $session_id);
    if ($gemini) {
        return reply(
            $gemini,
            ['Show me products', 'Track my order', 'Delivery info', 'Contact support']
        );
    }

    // ── 23. FINAL FALLBACK — with escalation after 3 failed attempts ──
    $ctx['fallback_count'] = ($ctx['fallback_count'] ?? 0) + 1;
    if ($ctx['fallback_count'] >= 3) {
        $ctx['fallback_count'] = 0;
        $ctx['awaiting'] = 'support_message';
        return reply(
            "😔 I've had trouble understanding your last few messages. Let me connect you with our support team.<br><br>" .
            "💬 <strong>Type your message below</strong> and a human agent will respond within 24 hours.<br>" .
            "📱 Or call us directly: <a href='tel:" . ADMIN_PHONE . "'><strong>" . ADMIN_PHONE . "</strong></a>",
            ['Contact support', 'Show me products']
        );
    }
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
            // ── Notify admin ──
            sendMail(ADMIN_EMAIL, ADMIN_NAME,
                '[' . SITE_NAME . '] 🛒 New Order #' . str_pad($order_id, 6, '0', STR_PAD_LEFT) . ' from ' . $user['name'],
                emailNewOrderAdmin($orderData, $emailItems)
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
    $url = 'http://localhost:5001/predict';
    $ch  = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode(['message' => $message, 'model' => 'best']),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT        => 2,
        CURLOPT_CONNECTTIMEOUT => 1,
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
            ORDER BY p.id DESC
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
    $snapshot = getStoreSnapshotData($conn);
    $topCategorySummary = [];
    foreach ($snapshot['top_categories'] as $category) {
        $topCategorySummary[] = $category['name'] . ' (' . $category['total'] . ')';
    }
    $snapshotCtx = "\nSTORE SNAPSHOT:\n"
        . "- In-stock products: " . number_format($snapshot['products']) . "\n"
        . "- Categories: " . number_format($snapshot['categories']) . "\n"
        . "- Brands: " . number_format($snapshot['brands']) . "\n"
        . "- Price range: RWF " . number_format($snapshot['min_price']) . " - RWF " . number_format($snapshot['max_price']) . "\n"
        . "- Top categories: " . (!empty($topCategorySummary) ? implode(', ', $topCategorySummary) : 'N/A') . "\n";

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
        . "You are only invoked as a LAST RESORT when faster PHP and ML rules could not answer — keep answers focused and efficient.\n"
        . "Always reply in the SAME language as the customer: English, French, or Kinyarwanda (match their message).\n"
        . "CRITICAL RULES:\n"
        . "- ONLY recommend products listed in the PRODUCTS FROM DATABASE section below. Never invent products, names, or prices.\n"
        . "- Always show prices in RWF exactly as listed.\n"
        . "- When showing products, always include the product name, price, and a brief description from the database.\n"
        . "- When a customer asks about a specific product, give FULL details: name, brand, price, stock, description.\n"
        . "- When a customer mentions a budget (e.g. 'I have 50000 RWF'), show ALL products within that budget. If none exist, recommend the closest affordable alternatives.\n"
        . "- Be friendly, helpful, and concise (max 300 words).\n"
        . "- For product links, format as: [Product Name](" . SITE_URL . "/product.php?id=ID)\n"
        . "- NEVER place orders, add items to cart, confirm orders, or collect delivery/payment details. "
        . "  If a customer wants to buy or place an order, tell them to click the product link or use the Add to Cart button. "
        . "  Order placement is handled by the system — you must NOT simulate or pretend to place orders.\n"
        . "- Guests may browse products and ask questions, but they must register or login before placing, tracking, cancelling orders, or downloading invoices.\n"
        . "- When the customer asks how ordering works, explain the real platform process clearly: browse products, login/register if needed, add to cart, provide address, choose payment, confirm, then track from My Orders.\n"
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
        . $snapshotCtx
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
            CURLOPT_TIMEOUT        => 10,
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
