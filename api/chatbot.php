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
    error_log("CHATBOT EXCEPTION: " . $e->getMessage() . " | File: " . $e->getFile() . " | Line: " . $e->getLine());
    error_log("Stack trace: " . $e->getTraceAsString());
    if (!headers_sent()) header('Content-Type: application/json');
    echo json_encode(['response' => 'Something went wrong. Please try again.', 'quick_replies' => ['Show me products', 'Contact support']]);
    exit;
});
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    error_log("CHATBOT ERROR [$errno]: $errstr | File: $errfile | Line: $errline");
    if ($errno === E_ERROR || $errno === E_PARSE) {
        if (!headers_sent()) header('Content-Type: application/json');
        echo json_encode(['response' => 'Something went wrong. Please try again.', 'quick_replies' => []]);
        exit;
    }
    return false;
});

session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/inventory.php';
require_once __DIR__ . '/../includes/chatbot_gemini_gate.php';
require_once __DIR__ . '/language_detector_simple.php';

// ── Initialize chatbot_context table if it doesn't exist ──
$conn->query("CREATE TABLE IF NOT EXISTS chatbot_context (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id VARCHAR(64) NOT NULL,
    user_id INT DEFAULT NULL,
    context_key VARCHAR(100) NOT NULL,
    context_value TEXT DEFAULT NULL,
    expires_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_session (session_id),
    INDEX idx_user (user_id),
    INDEX idx_key (context_key),
    UNIQUE KEY unique_context (session_id, user_id, context_key),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
)");

// ================================================================
// CONTEXT AWARENESS & SENTIMENT ANALYSIS FUNCTIONS
// ================================================================

/**
 * Save conversation context for session persistence
 */
function saveContext(string $sessionId, ?int $userId, string $key, string $value, int $expiryHours = 24): void {
    global $conn;
    $expiresAt = date('Y-m-d H:i:s', strtotime("+{$expiryHours} hours"));

    if ($userId === null) {
        $find = $conn->prepare("SELECT id FROM chatbot_context WHERE session_id=? AND user_id IS NULL AND context_key=? LIMIT 1");
        if ($find) {
            $find->bind_param("ss", $sessionId, $key);
            $find->execute();
            $row = $find->get_result()->fetch_assoc();
            $find->close();
            if ($row) {
                $id = (int)$row['id'];
                $stmt = $conn->prepare("UPDATE chatbot_context SET context_value=?, expires_at=? WHERE id=?");
                if ($stmt) {
                    $stmt->bind_param("ssi", $value, $expiresAt, $id);
                    $stmt->execute();
                    $stmt->close();
                }
                return;
            }
        }
        $stmt = $conn->prepare("INSERT INTO chatbot_context (session_id, user_id, context_key, context_value, expires_at) VALUES (?, NULL, ?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param("ssss", $sessionId, $key, $value, $expiresAt);
            $stmt->execute();
            $stmt->close();
        }
        return;
    }

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

function createEmptyChatContext(): array {
    return [
        'awaiting' => null,
        'last_products' => [],
        'order_cart' => [],
        'order_step' => null,
        'order_data' => [],
        'last_search_term' => ''
    ];
}

function loadChatContext(string $sessionId, ?int $userId): array {
    $ctxJson = getContext($sessionId, 'chat_ctx');
    if ($ctxJson) {
        $decoded = json_decode($ctxJson, true);
        if (is_array($decoded)) {
            return array_merge(createEmptyChatContext(), $decoded);
        }
    }
    return createEmptyChatContext();
}

function persistChatContext(string $sessionId, ?int $userId, array $ctx): void {
    saveContext($sessionId, $userId, 'chat_ctx', json_encode($ctx), 72);
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
    // This function now works with the multilingual system
    if (empty($responses)) {
        return "How can I help you?";
    }
    return $responses[array_rand($responses)];
}

// ================================================================
// BUDGET, PRICE & STOCK HANDLER FUNCTIONS
// ================================================================

/**
 * Normalize budget strings to integer RWF.
 * "50k" → 50000, "50,000" → 50000, "50000" → 50000, "about 100k" → 100000
 * Returns null if no valid amount found.
 */
function parseBudgetAmount(string $text): ?int {
    $text = strtolower(trim($text));
    
    // Remove common trailing words that don't affect the amount
    $text = preg_replace('/\s+(only|alone|maximum|max|tops|budget|francs|rwf|amafaranga)\?*$/i', '', $text);
    // Also strip "only" and "alone" when they appear mid-string between qualifier and number
    $text = preg_replace('/\b(only|alone)\b/i', '', $text);
    $text = preg_replace('/\s+/', ' ', trim($text));
    
    // Enhanced: Match Kinyarwanda patterns "amafaranga 50000", "ibihumbi 50"
    if (preg_match('/(?:amafaranga|francs|rwf|money|budget)\s*=?\s*(\d[\d,]*)\s*k?\b/', $text, $m)) {
        $val = (int)str_replace(',', '', $m[1]);
        if (stripos($text, 'k') !== false && preg_match('/\d\s*k\b/', $text)) {
            return $val * 1000;
        }
        return $val >= 1000 ? $val : $val * 1000;
    }
    
    // Match patterns like "50k", "50,000", "50000", "1,000,000"
    // Handles "about 50k", "around 100k", "~200k", "approx 100k", and intervening words
    if (preg_match('/(?:about|around|approximately|approx|~|roughly|~)?\s*(?:\w+\s+)*?(\d[\d,]*)\s*k\b/', $text, $m)) {
        return (int)(str_replace(',', '', $m[1])) * 1000;
    }
    
    // Match large numbers with commas like "50,000", "1,000,000"
    if (preg_match('/(\d{1,3}(?:,\d{3})+)/', $text, $m)) {
        $val = (int)str_replace(',', '', $m[1]);
        if ($val >= 1000) return $val;
    }
    
    // Match 4+ digit plain numbers (50000, 100000, 500000)
    if (preg_match('/\b(\d{4,})\b/', $text, $m)) {
        return (int)$m[1];
    }
    
    return null;
}

/**
 * Format integer as "RWF X,XXX,XXX"
 */
function formatRWF(int $amount): string {
    return 'RWF ' . number_format($amount);
}

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
function buildPriceLabel(?int $min, ?int $max, string $qualifier = 'none'): string {
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

/**
 * Return products with price <= budget, optionally filtered by category.
 * Cross-category: top 5 per category. Single category: top 10.
 */
/**
 * Check whether an intent requires the user to be authenticated.
 */
function requiresAuth(string $intent): bool {
    return in_array($intent, ['order_track', 'order_history', 'invoice', 'account_details'], true);
}

function handleBudgetQuery(int $budget, ?string $category, $conn, ?int $minBudget = null, string $qualifier = 'none'): array {
    $fmtBudget = formatRWF($budget);

    // Map shorthand category keywords to DB category name fragments
    $catMap = [
        'smartphones' => 'Smartphones',
        'laptops'     => 'Laptops',
        'tv'          => 'TV',
        'fashion'     => 'Fashion',
        'groceries'   => 'Groceries',
        'health'      => 'Health',
        'sports'      => 'Sports',
        'baby'        => 'Baby',
        'appliances'  => 'Appliances',
        'audio'       => 'Audio',
    ];
    $dbCatFragment = $category ? ($catMap[$category] ?? $category) : null;

    if ($dbCatFragment) {
        // Single category budget query
        $rangeLabel = $minBudget ? formatRWF($minBudget) . " – " . $fmtBudget : buildPriceLabel(null, $budget, $qualifier);
        $likeCat = '%' . $dbCatFragment . '%';
        if ($minBudget) {
            $stmt = $conn->prepare("
                SELECT p.id, p.name, p.brand, p.price, p.stock, p.description, c.name AS cat
                FROM products p
                LEFT JOIN categories c ON p.category_id = c.id
                WHERE p.price <= ?
                  AND p.price >= ?
                  AND c.name LIKE ?
                ORDER BY p.price ASC
                LIMIT 10
            ");
            if ($stmt) {
                $stmt->bind_param("iis", $budget, $minBudget, $likeCat);
                $stmt->execute();
                $res = $stmt->get_result();
                $stmt->close();
            } else {
                $res = false;
            }
        } else {
            $stmt = $conn->prepare("
                SELECT p.id, p.name, p.brand, p.price, p.stock, p.description, c.name AS cat
                FROM products p
                LEFT JOIN categories c ON p.category_id = c.id
                WHERE p.price <= ?
                  AND c.name LIKE ?
                ORDER BY p.price ASC
                LIMIT 10
            ");
            if ($stmt) {
                $stmt->bind_param("is", $budget, $likeCat);
                $stmt->execute();
                $res = $stmt->get_result();
                $stmt->close();
            } else {
                $res = false;
            }
        }
        $rows = [];
        if ($res) while ($r = $res->fetch_assoc()) $rows[] = $r;

        if (empty($rows)) {
            return [
                'response' => "😔 No products found $rangeLabel in that category. Try a different budget or browse all categories.",
                'quick_replies' => ['Show me all products', 'Show me categories', 'Contact support']
            ];
        }

        $displayCat = $rows[0]['cat'] ?? $dbCatFragment;
        $out = "✅ " . strip_tags($displayCat . " " . $rangeLabel) . "\n\n";
        foreach ($rows as $p) {
            $stock = $p['stock'] > 0 ? "✅ In Stock ({$p['stock']})" : "❌ Out of Stock";
            $name  = htmlspecialchars($p['name']);
            $brand = $p['brand'] ? " (" . htmlspecialchars($p['brand']) . ")" : "";
            $price = formatRWF((int)$p['price']);
            $out .= "• $name$brand — $price | $stock\n";
            if (!empty($p['description'])) {
                $out .= "  " . htmlspecialchars(mb_substr(strip_tags($p['description']), 0, 90)) . "\n";
            }
        }
        return [
            'response' => $out,
            'quick_replies' => ['Show me more', 'Show me categories', 'How to order?']
        ];
    }

    // Cross-category: top 5 per category
    $rangeLabel = $minBudget ? formatRWF($minBudget) . " – " . $fmtBudget : buildPriceLabel(null, $budget, $qualifier);
    if ($minBudget) {
        $stmt = $conn->prepare("
            SELECT p.id, p.name, p.brand, p.price, p.stock, c.name AS cat,
                   ROW_NUMBER() OVER (PARTITION BY p.category_id ORDER BY p.price ASC) AS rn
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            WHERE p.price <= ?
              AND p.price >= ?
            ORDER BY c.name, p.price ASC
        ");
        if ($stmt) {
            $stmt->bind_param("ii", $budget, $minBudget);
            $stmt->execute();
            $res = $stmt->get_result();
            $stmt->close();
        } else {
            $res = false;
        }
    } else {
        $stmt = $conn->prepare("
            SELECT p.id, p.name, p.brand, p.price, p.stock, c.name AS cat,
                   ROW_NUMBER() OVER (PARTITION BY p.category_id ORDER BY p.price ASC) AS rn
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            WHERE p.price <= ?
            ORDER BY c.name, p.price ASC
        ");
        if ($stmt) {
            $stmt->bind_param("i", $budget);
            $stmt->execute();
            $res = $stmt->get_result();
            $stmt->close();
        } else {
            $res = false;
        }
    }

    $byCategory = [];
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            if ((int)$r['rn'] <= 5) {
                $byCategory[$r['cat']][] = $r;
            }
        }
    }

    if (empty($byCategory)) {
        return [
            'response' => "😔 No products found $rangeLabel. Our lowest priced items start from RWF 1,200.",
            'quick_replies' => ['Show me all products', 'Show me categories', 'Contact support']
        ];
    }

    $out = "✅ Products " . strip_tags($rangeLabel) . " across all categories\n\n";
    foreach ($byCategory as $catName => $products) {
        $out .= "$catName\n";
        foreach ($products as $p) {
            $stock = $p['stock'] > 0 ? "✅" : "❌";
            $name  = htmlspecialchars($p['name']);
            $brand = $p['brand'] ? " (" . htmlspecialchars($p['brand']) . ")" : "";
            $price = formatRWF((int)$p['price']);
            $out .= "  • $name$brand — $price $stock\n";
        }
    }

    return [
        'response' => $out,
        'quick_replies' => ['Show me phones under 100k', 'Show me laptops', 'How to order?']
    ];
}

/**
 * Return top 10 products from a category.
 */
function handleCategoryQuery(string $category, $conn): array {
    $likeCat = '%' . $category . '%';
    $stmt = $conn->prepare("
        SELECT p.id, p.name, p.brand, p.price, p.stock, p.description
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE LOWER(c.name) LIKE LOWER(?)
        ORDER BY p.stock DESC, p.price ASC
        LIMIT 10
    ");
    if ($stmt) {
        $stmt->bind_param("s", $likeCat);
        $stmt->execute();
        $res = $stmt->get_result();
        $stmt->close();
    } else {
        $res = false;
    }
    $rows = [];
    if ($res) while ($r = $res->fetch_assoc()) $rows[] = $r;

    if (empty($rows)) {
        return [
            'response' => "😔 No products found in the \"$category\" category. Try browsing all categories.",
            'quick_replies' => ['Show me all categories', 'Show me phones', 'Show me laptops']
        ];
    }

    $out = "✅ <strong>Products in $category:</strong><br><br>";
    foreach ($rows as $p) {
        $stock = $p['stock'] > 0 ? "✅ In Stock" : "❌ Out of Stock";
        $out .= "• <strong>" . htmlspecialchars($p['name']) . "</strong>";
        if ($p['brand']) $out .= " <em>({$p['brand']})</em>";
        $out .= " — <strong>" . formatRWF((int)$p['price']) . "</strong> | $stock<br>";
        if (!empty($p['description'])) {
            $out .= "&nbsp;&nbsp;<small>📝 " . htmlspecialchars(mb_substr(strip_tags($p['description']), 0, 90)) . "</small><br>";
        }
    }

    return [
        'response' => $out,
        'quick_replies' => ['Show me more', 'Filter by budget', 'How to order?']
    ];
}

/**
 * Return exact price + full description for a product by name.
 */
function handlePriceInquiry(string $productName, $conn): array {
    $likeName = '%' . $productName . '%';
    $stmt = $conn->prepare("
        SELECT p.id, p.name, p.brand, p.price, p.stock, p.description, c.name AS cat
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE LOWER(p.name) LIKE LOWER(?)
        ORDER BY p.stock DESC
        LIMIT 5
    ");
    if ($stmt) {
        $stmt->bind_param("s", $likeName);
        $stmt->execute();
        $res = $stmt->get_result();
        $stmt->close();
    } else {
        $res = false;
    }
    $rows = [];
    if ($res) while ($r = $res->fetch_assoc()) $rows[] = $r;

    if (empty($rows)) {
        return [
            'response' => "😔 I couldn't find a product matching \"" . htmlspecialchars($productName) . "\". Try searching by category or brand.",
            'quick_replies' => ['Show me products', 'Show me categories', 'Contact support']
        ];
    }

    if (count($rows) === 1) {
        $p = $rows[0];
        $stock = $p['stock'] > 0 ? "✅ In Stock ({$p['stock']} available)" : "❌ Out of Stock";
        $desc  = $p['description'] ? "<br><small>📝 " . htmlspecialchars(mb_substr(strip_tags($p['description']), 0, 120)) . "</small>" : '';
        return [
            'response' => "💰 <strong>" . htmlspecialchars($p['name']) . "</strong>"
                . ($p['brand'] ? " by <em>{$p['brand']}</em>" : '')
                . ($p['cat'] ? " — <em>{$p['cat']}</em>" : '')
                . "<br>Price: <strong>" . formatRWF((int)$p['price']) . "</strong>"
                . "<br>Status: $stock"
                . $desc,
            'quick_replies' => ['Add to cart', 'Is it in stock?', 'Show me similar']
        ];
    }

    $out = "💰 <strong>Prices for \"" . htmlspecialchars($productName) . "\":</strong><br><br>";
    foreach ($rows as $p) {
        $stock = $p['stock'] > 0 ? "✅" : "❌";
        $out .= "• <strong>" . htmlspecialchars($p['name']) . "</strong>"
              . ($p['brand'] ? " <em>({$p['brand']})</em>" : '')
              . " — <strong>" . formatRWF((int)$p['price']) . "</strong> $stock<br>";
        if ($p['description']) {
            $out .= "&nbsp;&nbsp;<small>" . htmlspecialchars(mb_substr(strip_tags($p['description']), 0, 80)) . "</small><br>";
        }
    }

    return [
        'response' => $out,
        'quick_replies' => ['Show me more', 'Filter by budget', 'How to order?']
    ];
}

/**
 * Return stock availability + full description for a product.
 */
function handleStockCheck(string $productName, $conn): array {
    $likeName = '%' . $productName . '%';
    $stmt = $conn->prepare("
        SELECT p.id, p.name, p.brand, p.price, p.stock, p.description
        FROM products p
        WHERE LOWER(p.name) LIKE LOWER(?)
        ORDER BY p.stock DESC
        LIMIT 5
    ");
    if ($stmt) {
        $stmt->bind_param("s", $likeName);
        $stmt->execute();
        $res = $stmt->get_result();
        $stmt->close();
    } else {
        $res = false;
    }
    $rows = [];
    if ($res) while ($r = $res->fetch_assoc()) $rows[] = $r;

    if (empty($rows)) {
        return [
            'response' => "😔 I couldn't find \"" . htmlspecialchars($productName) . "\" in our store. Try a different name or browse categories.",
            'quick_replies' => ['Show me products', 'Show me categories', 'Contact support']
        ];
    }

    $out = "📦 <strong>Stock availability for \"" . htmlspecialchars($productName) . "\":</strong><br><br>";
    foreach ($rows as $p) {
        if ($p['stock'] > 0) {
            $status = "✅ <strong>In Stock</strong> — {$p['stock']} units available";
        } else {
            $status = "❌ <strong>Out of Stock</strong> — notify me when available";
        }
        $out .= "• <strong>" . htmlspecialchars($p['name']) . "</strong>"
              . ($p['brand'] ? " <em>({$p['brand']})</em>" : '')
              . " — " . formatRWF((int)$p['price']) . "<br>"
              . "&nbsp;&nbsp;" . $status . "<br>";
        if ($p['description']) {
            $out .= "&nbsp;&nbsp;<small>📝 " . htmlspecialchars(mb_substr(strip_tags($p['description']), 0, 100)) . "</small><br>";
        }
        $out .= "<br>";
    }

    return [
        'response' => $out,
        'quick_replies' => ['Add to cart', 'Notify when in stock', 'Show me similar']
    ];
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
        $stmtUser = $conn->prepare("SELECT name, email FROM users WHERE id=?");
        if ($stmtUser) {
            $stmtUser->bind_param("i", $userId);
            $stmtUser->execute();
            $userResult = $stmtUser->get_result();
            if ($row = $userResult->fetch_assoc()) {
                $userInfo = "User: {$row['name']} ({$row['email']})\n";
            }
            $stmtUser->close();
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
$detectedLang = detect_language($message);

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
        $stmt = $conn->prepare("SELECT message, response, created_at FROM chatbot_logs WHERE user_id=? ORDER BY created_at ASC LIMIT 40");
        if ($stmt) {
            $stmt->bind_param("i", $uid);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($res) while ($r = $res->fetch_assoc()) $history[] = $r;
            $stmt->close();
        }
    }
    // For guests: load by localStorage session_id
    elseif (strlen($clientSid) === 32) {
        $stmt = $conn->prepare("SELECT message, response, created_at FROM chatbot_logs WHERE session_id=? ORDER BY created_at ASC LIMIT 40");
        if ($stmt) {
            $stmt->bind_param("s", $clientSid);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($res) while ($r = $res->fetch_assoc()) $history[] = $r;
            $stmt->close();
        }
    }

    echo json_encode(['history' => $history]);
    exit;
}

// ── File/Image upload endpoint ──
if (($_GET['action'] ?? '') === 'upload') {
    header('Content-Type: application/json');
    $uid = $_SESSION['user_id'] ?? null;
    $msg = trim($_POST['message'] ?? '');
    $sid = preg_replace('/[^a-f0-9]/i', '', $_POST['session_id'] ?? '');

    if (empty($_FILES['file']['tmp_name'])) {
        echo json_encode(['response' => 'No file received. Please try again.', 'quick_replies' => ['Show me products']]);
        exit;
    }

    $file    = $_FILES['file'];
    $ext     = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $isImage = in_array($ext, ['jpg','jpeg','png','gif','webp','bmp']);
    $isDoc   = in_array($ext, ['pdf','doc','docx','txt']);

    if (!$isImage && !$isDoc) {
        echo json_encode(['response' => '❌ Unsupported file. Please upload an image (JPG, PNG, WEBP) or document (PDF, TXT).', 'quick_replies' => ['Show me products']]);
        exit;
    }

    $apiKey = GEMINI_API_KEY ?? '';

    // ── Helper: search DB with multiple keywords ──
    $searchDB = function(string $query) use ($conn): array {
        $words = array_filter(explode(' ', preg_replace('/[^a-z0-9\s]/i', '', strtolower($query))), fn($w) => strlen($w) >= 3);
        $rows = [];
        foreach (array_slice($words, 0, 5) as $w) {
            $likeW = '%' . $w . '%';
            $stmt = $conn->prepare("SELECT p.id,p.name,p.brand,p.price,p.stock,p.description,c.name AS cat
                FROM products p LEFT JOIN categories c ON p.category_id=c.id
                WHERE p.stock>0 AND (p.name LIKE ? OR p.brand LIKE ? OR p.description LIKE ?)
                ORDER BY p.price ASC LIMIT 4");
            if ($stmt) {
                $stmt->bind_param("sss", $likeW, $likeW, $likeW);
                $stmt->execute();
                $res = $stmt->get_result();
                $stmt->close();
                while ($r = $res->fetch_assoc()) {
                    if (!in_array($r['id'], array_column($rows, 'id'))) $rows[] = $r;
                }
            }
            if (count($rows) >= 8) break;
        }
        return $rows;
    };

    if ($isImage) {
        // ── IMAGE: Gemini Vision identifies product + searches DB ──
        $imageData = base64_encode(file_get_contents($file['tmp_name']));
        $mimeType  = $file['type'] ?: 'image/jpeg';
        $geminiAnalysis = null;

        if ($apiKey && $apiKey !== 'your-gemini-api-key-here') {
            // Ask Gemini to analyze the image in detail
            $prompt = "Analyze this image carefully. "
                . "1. What product(s) do you see? Give the exact product name, brand if visible, and category (e.g. smartphone, laptop, sofa, dress, etc.). "
                . "2. Describe key features visible (color, size, model number if visible). "
                . "3. What search keywords would find this product in an e-commerce store? "
                . "Reply in this format: PRODUCT: [name] | BRAND: [brand or unknown] | CATEGORY: [category] | KEYWORDS: [keyword1, keyword2, keyword3]";

            $visionPayload = json_encode([
                'contents' => [[
                    'parts' => [
                        ['text' => $prompt],
                        ['inline_data' => ['mime_type' => $mimeType, 'data' => $imageData]]
                    ]
                ]],
                'generationConfig' => ['temperature' => 0.1, 'maxOutputTokens' => 200]
            ]);

            $models = ['gemini-2.0-flash', 'gemini-2.5-flash', 'gemini-flash-latest'];
            foreach ($models as $model) {
                $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";
                $ch  = curl_init($url);
                curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_POST=>true,
                    CURLOPT_POSTFIELDS=>$visionPayload, CURLOPT_HTTPHEADER=>['Content-Type: application/json'], CURLOPT_TIMEOUT=>20]);
                $resp = curl_exec($ch);
                $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                if ($code === 200) {
                    $data = json_decode($resp, true);
                    $geminiAnalysis = trim($data['candidates'][0]['content']['parts'][0]['text'] ?? '');
                    break;
                }
            }
        }

        $rows = [];
        $productName = '';
        $brandName   = '';

        if ($geminiAnalysis) {
            // Parse Gemini response
            preg_match('/PRODUCT:\s*([^|]+)/i', $geminiAnalysis, $pm);
            preg_match('/BRAND:\s*([^|]+)/i',   $geminiAnalysis, $bm);
            preg_match('/KEYWORDS:\s*(.+)/i',   $geminiAnalysis, $km);

            $productName = trim($pm[1] ?? '');
            $brandName   = trim($bm[1] ?? '');
            $keywords    = trim($km[1] ?? '');

            // Search by product name first
            if ($productName && strtolower($productName) !== 'unknown') {
                $rows = $searchDB($productName);
            }
            // If not enough results, search by keywords
            if (count($rows) < 3 && $keywords) {
                $moreRows = $searchDB($keywords);
                foreach ($moreRows as $r) {
                    if (!in_array($r['id'], array_column($rows, 'id'))) $rows[] = $r;
                }
            }
            // Also try brand
            if (count($rows) < 3 && $brandName && strtolower($brandName) !== 'unknown') {
                $moreRows = $searchDB($brandName);
                foreach ($moreRows as $r) {
                    if (!in_array($r['id'], array_column($rows, 'id'))) $rows[] = $r;
                }
            }
        }

        // Also use customer's typed message as additional search
        if ($msg && count($rows) < 5) {
            $moreRows = $searchDB($msg);
            foreach ($moreRows as $r) {
                if (!in_array($r['id'], array_column($rows, 'id'))) $rows[] = $r;
            }
        }

        $rows = array_slice($rows, 0, 8);

        if (!empty($rows)) {
            $identified = $productName ? "<strong>" . htmlspecialchars($productName) . "</strong>" . ($brandName && strtolower($brandName) !== 'unknown' ? " by <strong>" . htmlspecialchars($brandName) . "</strong>" : '') : "a product";
            $out = "📷 I analyzed your image and identified $identified.<br><br>✅ <strong>Here are matching products from our store:</strong><br>";
            foreach ($rows as $p) {
                $out .= "• <a href='" . SITE_URL . "/product.php?id={$p['id']}'><strong>" . htmlspecialchars($p['name']) . "</strong></a>"
                      . ($p['brand'] ? " <em>({$p['brand']})</em>" : '')
                      . " — <strong>RWF " . number_format($p['price']) . "</strong>"
                      . " | " . $p['stock'] . " in stock";
                if (!empty($p['description'])) {
                    $out .= "<br><small style='color:rgba(255,255,255,.6)'>📝 " . htmlspecialchars(mb_substr(strip_tags($p['description']), 0, 100)) . "...</small>";
                }
                $out .= "<br>";
            }
            $qr = array_map(fn($p) => "🛒 Add: add_to_cart:{$p['id']}", array_slice($rows, 0, 3));
            $response     = $out;
            $quickReplies = array_merge($qr, ['Show me more', 'Show me products']);
        } else {
            $notFound = $productName ? "I identified <strong>" . htmlspecialchars($productName) . "</strong> in your image, but we don't currently carry this exact product." : "I couldn't identify a specific product in your image.";
            $response = "📷 " . $notFound . "<br><br>💡 Try describing what you're looking for and I'll search our 1,161+ products across 15 categories!";
            $quickReplies = ['Show me products', 'Show me phones', 'Show me furniture', 'Show me fashion'];
        }

    } else {
        // ── DOCUMENT: Gemini reads the document + searches DB ──
        $docText = '';
        if ($ext === 'txt') {
            $docText = file_get_contents($file['tmp_name']);
        } elseif ($ext === 'pdf') {
            $content = file_get_contents($file['tmp_name']);
            // Extract readable text from PDF
            preg_match_all('/\(([^\)]{2,100})\)/', $content, $matches);
            $docText = implode(' ', $matches[1] ?? []);
            // Also try stream extraction
            preg_match_all('/stream(.*?)endstream/s', $content, $streams);
            foreach ($streams[1] ?? [] as $stream) {
                $decoded = @gzuncompress($stream);
                if ($decoded === false && strlen($stream) > 0) {
                    error_log('CHATBOT: Failed to decompress PDF stream (not gzipped)');
                }
                if ($decoded) $docText .= ' ' . preg_replace('/[^\x20-\x7E]/', ' ', $decoded);
            }
        } elseif (in_array($ext, ['doc','docx'])) {
            $content = file_get_contents($file['tmp_name']);
            $docText = preg_replace('/[^\x20-\x7E\n]/', ' ', $content);
        }

        $docText = substr(strip_tags(preg_replace('/\s+/', ' ', $docText)), 0, 1000);

        // Use Gemini to understand the document if we have API key
        $geminiSummary = '';
        if ($apiKey && $apiKey !== 'your-gemini-api-key-here' && strlen($docText) > 20) {
            $docPayload = json_encode([
                'contents' => [[
                    'parts' => [['text' => "This is text from a document a customer uploaded to an e-commerce store. Extract: 1) What product(s) are they looking for? 2) What is their budget if mentioned? 3) Key product features they want. Reply with: PRODUCTS: [list] | BUDGET: [amount or none] | FEATURES: [list]\n\nDocument text: " . $docText]]
                ]],
                'generationConfig' => ['temperature' => 0.1, 'maxOutputTokens' => 150]
            ]);
            $models = ['gemini-2.0-flash', 'gemini-2.5-flash', 'gemini-flash-latest'];
            foreach ($models as $model) {
                $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";
                $ch  = curl_init($url);
                curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_POST=>true,
                    CURLOPT_POSTFIELDS=>$docPayload, CURLOPT_HTTPHEADER=>['Content-Type: application/json'], CURLOPT_TIMEOUT=>15]);
                $resp = curl_exec($ch);
                $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                if ($code === 200) {
                    $data = json_decode($resp, true);
                    $geminiSummary = trim($data['candidates'][0]['content']['parts'][0]['text'] ?? '');
                    break;
                }
            }
        }

        // Build search query from Gemini analysis + document text + customer message
        $searchQuery = $geminiSummary . ' ' . $docText . ' ' . $msg;
        $rows = $searchDB($searchQuery);

        if (!empty($rows)) {
            $fp = formatProducts($rows, 'Products matching your document', true);
            $response = "📄 I analyzed your document and found these matching products in our store:<br><br>" . $fp['text'];
            $quickReplies = array_merge($fp['qr'], ['Show me more', 'Show me products']);
        } elseif (!empty($docText)) {
            $response = "📄 I read your document but couldn't find exact matches. Could you tell me specifically what product you're looking for? I'll search our 1,161+ products for you.";
            $quickReplies = ['Show me products', 'Show me phones', 'Show me furniture', 'Contact support'];
        } else {
            $response = "📄 I received your document! Please type what product you're looking for and I'll search our entire store for you.";
            $quickReplies = ['Show me products', 'Show me phones', 'Show me laptops'];
        }
    }

    // Log the interaction
    $logMsg   = "📎 [" . strtoupper($ext) . ": {$file['name']}] " . ($msg ?: 'File uploaded');
    $ui       = $uid ? (int)$uid : null;
    $guest    = $uid ? 0 : 1;
    $stmt = $conn->prepare("INSERT INTO chatbot_logs (user_id, session_id, is_guest, message, response, response_source) VALUES (?, ?, ?, ?, ?, 'image')");
    if ($stmt) {
        $stmt->bind_param("isiss", $ui, $sid, $guest, $logMsg, $response);
        $stmt->execute();
        $stmt->close();
    }
    $logId = (int)$conn->insert_id;

    echo json_encode(['response' => $response, 'quick_replies' => $quickReplies, 'log_id' => $logId, 'session_id' => $sid]);
    exit;
}

// ── Rate endpoint ──
if (($_GET['action'] ?? '') === 'rate') {    header('Content-Type: application/json');
    $logId  = (int)($input['log_id'] ?? 0);
    $rating = (int)($input['rating'] ?? -1);
    $uid2   = $_SESSION['user_id'] ?? null;
    $sid2   = preg_replace('/[^a-f0-9]/i','', $input['session_id'] ?? '');
    if ($logId && in_array($rating, [0,1])) {
        $stmt = $conn->prepare("INSERT IGNORE INTO chatbot_ratings (log_id, user_id, session_id, rating) VALUES (?, ?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param("iisi", $logId, $uid2, $sid2, $rating);
            $stmt->execute();
            $stmt->close();
        }
    }
    echo json_encode(['ok' => true]);
    exit;
}

// ── Stock notification endpoint ──
if (($_GET['action'] ?? '') === 'stock_notify') {
    header('Content-Type: application/json');
    $pid   = (int)($input['product_id'] ?? 0);
    $email = trim($input['email'] ?? '');
    $name  = trim($input['name'] ?? 'Customer');
    if ($pid && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $stmt = $conn->prepare("INSERT IGNORE INTO stock_notifications (product_id, email, name) VALUES (?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param("iss", $pid, $email, $name);
            $stmt->execute();
            $stmt->close();
        }
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
        $stmt = $conn->prepare("SELECT id FROM users WHERE id=? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $chk = $stmt->get_result();
            $stmt->close();
            if (!$chk || $chk->num_rows === 0) { $user_id = null; $_SESSION['user_id'] = null; }
        }
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
        $_SESSION['chat_ctx'] = loadChatContext($session_id, $user_id);
    }
    $_SESSION['chat_ctx_sid'] = $session_id;

    if (!isset($_SESSION['chat_ctx'])) {
        $_SESSION['chat_ctx'] = loadChatContext($session_id, $user_id);
    }
    $ctx = &$_SESSION['chat_ctx'];

    $result   = processMessage($message, $user_id, $conn, $ctx, $session_id);
    try {
        persistChatContext($session_id, $user_id, $ctx);
    } catch (Throwable $e) {
        error_log("Warning: Failed to persist chat context: " . $e->getMessage());
    }
    
    // Safety check: ensure result is an array
    if (!is_array($result)) {
        error_log("processMessage returned non-array: " . gettype($result) . " | Message: " . $message);
        $result = ['response' => 'Something went wrong. Please try again.', 'quick_replies' => ['Show me products', 'Contact support']];
    }
    
    $response = $result['response'] ?? 'Something went wrong. Please try again.';
    $qr       = $result['quick_replies'] ?? [];

    // ================================================================
    // SENTIMENT ANALYSIS - Analyze user emotion
    // ================================================================
    $sentiment = analyzeSentiment($message);
    
    $ui    = $user_id ? (int)$user_id : null;
    $guest = $user_id ? 0 : 1;
    $esc   = $sentiment['escalate'] ? 1 : 0;
    $stmt  = $conn->prepare("INSERT INTO chatbot_logs (user_id, session_id, is_guest, message, response, response_source, sentiment_score, sentiment_label, escalated) 
                             VALUES (?, ?, ?, ?, ?, 'php', ?, ?, ?)");
    if ($stmt) {
        $stmt->bind_param("isissdsi", $ui, $session_id, $guest, $message, $response, $sentiment['score'], $sentiment['label'], $esc);
        $stmt->execute();
        $stmt->close();
        $saved = true;
    } else {
        $saved = false;
        error_log("chatbot_logs INSERT prepare failed: " . $conn->error);
    }
    if (!$saved) {
        error_log("chatbot_logs INSERT failed: " . $conn->error);
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
        try {
            saveContext($session_id, $user_id, 'last_product_query', $message);
        } catch (Throwable $e) {
            error_log("Warning: Failed to save product query context: " . $e->getMessage());
        }
    }
    
    // Track order-related queries
    if (stripos($message, 'order') !== false || stripos($message, 'delivery') !== false || stripos($message, 'tracking') !== false) {
        try {
            saveContext($session_id, $user_id, 'last_order_query', $message);
        } catch (Throwable $e) {
            error_log("Warning: Failed to save order query context: " . $e->getMessage());
        }
    }

    echo json_encode(['response' => $response, 'quick_replies' => $qr, 'session_id' => $session_id, 'log_id' => $log_id]);
} catch (Throwable $e) {
    error_log("CHATBOT MAIN EXCEPTION: " . $e->getMessage() . " | File: " . $e->getFile() . " | Line: " . $e->getLine());
    error_log("Stack trace: " . $e->getTraceAsString());
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
function intentMlFastReply(string $intent, string $msg, ?int $uid, $conn, array &$ctx, string $session_id = ''): ?array {
    $ml = strtolower(trim($msg));
    $lang = $ctx['language'] ?? detect_language($msg);

    switch ($intent) {
        case 'delivery_time':
            $responses = [
                'english' => "🚚 <strong>Delivery Times (Rwanda):</strong><br>" .
                    "• <strong>Kigali:</strong> 1–2 business days<br>" .
                    "• <strong>Other provinces:</strong> 2–4 business days<br>" .
                    "• <strong>Remote areas:</strong> up to 5–7 days<br>" .
                    "You'll receive an SMS/email update once your order is shipped! 📱",
                'kinyarwanda' => "🚚 <strong>Igihe cy'ugerageza (u Rwanda):</strong><br>" .
                    "• <strong>Kigali:</strong> iminsi 1–2 y'akazi<br>" .
                    "• <strong>Ibindi bigo:</strong> iminsi 2–4 y'akazi<br>" .
                    "• <strong>Ahantu hahuje:</strong> kugeza iminsi 5–7<br>" .
                    "Uzakira SMS/email igihe agaciro gakoresha! 📱",
                'french' => "🚚 <strong>Délais de livraison (Rwanda):</strong><br>" .
                    "• <strong>Kigali:</strong> 1–2 jours ouvrables<br>" .
                    "• <strong>Autres provinces:</strong> 2–4 jours ouvrables<br>" .
                    "• <strong>Zones éloignées:</strong> jusqu'à 5–7 jours<br>" .
                    "Vous recevrez une mise à jour SMS/email une fois votre commande expédiée! 📱"
            ];
            $response_text = $responses[$lang] ?? $responses['english'];
            return reply($response_text, ['Shipping fees', 'Track my order', 'Payment methods']);

        case 'shipping_fee':
            $responses = [
                'english' => "📦 <strong>Shipping:</strong><br>" .
                    "• <strong>FREE shipping</strong> on all orders 🎉<br>" .
                    "• Express delivery (Kigali only) → <strong>RWF 3,500</strong>",
                'kinyarwanda' => "📦 <strong>Kugerageza:</strong><br>" .
                    "• <strong>Kugerageza kubuntu</strong> kuri buri agaciro 🎉<br>" .
                    "• Kugerageza vuba (Kigali gusa) → <strong>RWF 3,500</strong>",
                'french' => "📦 <strong>Expédition:</strong><br>" .
                    "• <strong>Livraison GRATUITE</strong> sur toutes les commandes 🎉<br>" .
                    "• Livraison express (Kigali uniquement) → <strong>RWF 3 500</strong>"
            ];
            $response_text = $responses[$lang] ?? $responses['english'];
            return reply($response_text, ['Delivery time', 'Payment methods', 'Show me products']);

        case 'payment_methods':
            $responses = [
                'english' => "💳 <strong>Payment Methods We Accept:</strong><br>" .
                    "• 💵 Cash on Delivery (COD)<br>" .
                    "• 📱 MTN Mobile Money (MoMo)<br>" .
                    "• 📱 Airtel Money<br>" .
                    "• 🏦 Bank Transfer (BK, Equity, I&M)<br>" .
                    "• 💳 Visa / Mastercard<br>" .
                    "All online payments are <strong>SSL secured</strong> 🔒",
                'kinyarwanda' => "💳 <strong>Ubwoko bw'amafaranga dukwemera:</strong><br>" .
                    "• 💵 Amafaranga mu gihe cy'ugerageza (COD)<br>" .
                    "• 📱 MTN Mobile Money (MoMo)<br>" .
                    "• 📱 Airtel Money<br>" .
                    "• 🏦 Kwishyura mu banki (BK, Equity, I&M)<br>" .
                    "• 💳 Visa / Mastercard<br>" .
                    "Amafaranga yose yonline ari <strong>SSL secured</strong> 🔒",
                'french' => "💳 <strong>Méthodes de paiement acceptées:</strong><br>" .
                    "• 💵 Paiement à la livraison (COD)<br>" .
                    "• 📱 MTN Mobile Money (MoMo)<br>" .
                    "• 📱 Airtel Money<br>" .
                    "• 🏦 Virement bancaire (BK, Equity, I&M)<br>" .
                    "• 💳 Visa / Mastercard<br>" .
                    "Tous les paiements en ligne sont <strong>sécurisés par SSL</strong> 🔒"
            ];
            $response_text = $responses[$lang] ?? $responses['english'];
            return reply($response_text, ['Delivery info', 'Return policy', 'Show me products']);

        case 'return_policy':
            $responses = [
                'english' => "↩️ <strong>Return & Refund Policy:</strong><br>" .
                    "• Items returnable within <strong>7 days</strong> of delivery<br>" .
                    "• Item must be unused and in original packaging<br>" .
                    "• Damaged or wrong items: full refund or free replacement<br>" .
                    "• Refunds processed within <strong>3–5 business days</strong><br>" .
                    "📧 Start a return: <a href='mailto:" . ADMIN_EMAIL . "'>" . ADMIN_EMAIL . "</a>",
                'kinyarwanda' => "↩️ <strong>Politiki y'ugarura n'amafaranga:</strong><br>" .
                    "• Ibicuruzwa bishobora kugarurwa mu <strong>iminsi 7</strong> nyuma y'ugerageza<br>" .
                    "• Ibicuruzwa byombi bitakoresha no mu nzira y'ibanze<br>" .
                    "• Ibicuruzwa byakubitswe cyangwa byarakosa: amafaranga yose cyangwa ibicuruzwa bishya<br>" .
                    "• Amafaranga akurikirwa mu <strong>iminsi 3–5 y'akazi</strong><br>" .
                    "📧 Tangira ugarura: <a href='mailto:" . ADMIN_EMAIL . "'>" . ADMIN_EMAIL . "</a>",
                'french' => "↩️ <strong>Politique de retour et de remboursement:</strong><br>" .
                    "• Articles retournables dans les <strong>7 jours</strong> suivant la livraison<br>" .
                    "• L'article doit être inutilisé et dans son emballage d'origine<br>" .
                    "• Articles endommagés ou incorrects: remboursement complet ou remplacement gratuit<br>" .
                    "• Remboursements traités dans les <strong>3–5 jours ouvrables</strong><br>" .
                    "📧 Commencer un retour: <a href='mailto:" . ADMIN_EMAIL . "'>" . ADMIN_EMAIL . "</a>"
            ];
            $response_text = $responses[$lang] ?? $responses['english'];
            return reply($response_text, ['Delivery info', 'Contact support', 'Warranty info']);

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
                "• 🎉 <strong>Free shipping</strong> on all orders!<br>" .
                "• New arrivals added weekly across all categories<br>" .
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
                getPlatformKnowledgeText($conn, $uid, $lang),
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
            // Try to detect specific category from message and show its products
            $catId = detectCategory($ml);
            if ($catId) {
                $rows = dbProductSearch('', $conn, $catId);
                if (!empty($rows)) {
                    $ctx['last_products'] = $rows;
                    $fp = formatProducts($rows, '', false, $lang);
                    return reply($fp['text'], array_merge($fp['qr'], ['Filter by budget', 'Show me categories']));
                }
            }
            return reply(getCategorySummary($conn), ['Show me phones', 'Show me laptops', 'Show me products']);

        case 'order_track':
            if (!$uid) {
                return reply("🔒 Please <a href='" . SITE_URL . "/login.php'>login</a> first to track your orders.", ['Login', 'Register']);
            }
            if (preg_match('/#?0*(\d+)\b/', $msg, $m)) {
                return reply(trackOrder((int)$m[1], $uid, $conn), ['View all orders', 'Cancel an order']);
            }
            $stmtLo = $conn->prepare("SELECT id,status FROM orders WHERE user_id=? ORDER BY created_at DESC LIMIT 1");
            $latest = null;
            if ($stmtLo) { $stmtLo->bind_param("i", $uid); $stmtLo->execute(); $latest = $stmtLo->get_result()->fetch_assoc(); $stmtLo->close(); }
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
                $stmti = $conn->prepare("SELECT id FROM orders WHERE id=? AND user_id=? LIMIT 1");
                $chk = null;
                if ($stmti) { $stmti->bind_param("ii", $oid, $uid); $stmti->execute(); $chk = $stmti->get_result()->fetch_assoc(); $stmti->close(); }
                if ($chk) {
                    return reply(
                        "🧾 <strong>Invoice for Order #" . str_pad((string)$oid, 6, '0', STR_PAD_LEFT) . "</strong><br><br>" .
                        "<a href='" . SITE_URL . "/invoice.php?id=$oid' target='_blank'><strong>📄 Download / Print Invoice →</strong></a>",
                        ['My orders', 'Track my order']
                    );
                }
                return reply("❌ Order #$oid not found under your account.", ['My orders']);
            }
            $stmtOi = $conn->prepare("SELECT id FROM orders WHERE user_id=? ORDER BY created_at DESC LIMIT 5");
            $links = '';
            if ($stmtOi) { $stmtOi->bind_param("i", $uid); $stmtOi->execute(); $res = $stmtOi->get_result(); $stmtOi->close(); } else { $res = false; }
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

        case 'budget_search':
            // Extract budget amount from message
            $budget = parseBudgetAmount($msg);
            // Also check for price range (min–max)
            [$minBudget, $maxBudget] = extractPriceRange($ml);
            if ($maxBudget && $maxBudget >= 1000) {
                $budget = $maxBudget; // use range max as the budget ceiling
            }
            if ($budget) {
                // Save budget to context for personalization
                try {
                    saveContext($session_id, $uid, 'last_budget', (string)$budget);
                    if ($minBudget) saveContext($session_id, $uid, 'last_budget_min', (string)$minBudget);
                } catch (Throwable $e) {
                    error_log("Warning: Failed to save budget context: " . $e->getMessage());
                }
                // Extract category keyword from message
                $extractedCategory = null;
                $categoryKeywords = [
                    'phones' => 'smartphones', 'phone' => 'smartphones', 'mobile' => 'smartphones',
                    'smartphone' => 'smartphones', 'smartphones' => 'smartphones', 'tablet' => 'smartphones',
                    'laptops' => 'laptops', 'laptop' => 'laptops', 'computer' => 'laptops', 'pc' => 'laptops',
                    'tv' => 'tv', 'television' => 'tv', 'audio' => 'tv', 'speaker' => 'tv', 'headphone' => 'tv',
                    'fashion' => 'fashion', 'clothes' => 'fashion', 'clothing' => 'fashion',
                    'dress' => 'fashion', 'shirt' => 'fashion', 'shoes' => 'fashion',
                    'groceries' => 'groceries', 'grocery' => 'groceries', 'food' => 'groceries',
                    'health' => 'health', 'beauty' => 'health',
                    'sports' => 'sports', 'sport' => 'sports', 'gym' => 'sports', 'fitness' => 'sports',
                    'baby' => 'baby', 'kids' => 'baby', 'toy' => 'baby',
                    'appliances' => 'appliances', 'appliance' => 'appliances', 'fridge' => 'appliances',
                    'washing' => 'appliances', 'microwave' => 'appliances',
                ];
                $msgLower = strtolower($msg);
                foreach ($categoryKeywords as $kw => $cat) {
                    if (stripos($msgLower, $kw) !== false) {
                        $extractedCategory = $cat;
                        break;
                    }
                }
                $qualifier = detectPriceQualifier($msg);
                $result = handleBudgetQuery($budget, $extractedCategory, $conn, $minBudget ?: null, $qualifier);
                return reply($result['response'], $result['quick_replies']);
            }
            // Fallback: no number found — ask for budget
            return reply(
                "💰 I'd love to help you find products within your budget! How much are you looking to spend? (e.g. RWF 50,000 or 100k)",
                ['Under 50,000', 'Under 100,000', 'Under 200,000', 'Under 500,000']
            );

        case 'product_price':
            // Extract product name by stripping common query words
            $productName = preg_replace('/\b(how much is|how much does|price of|cost of|what is the price of|what is the price for|price for|how much|what.*price|igiciro cya|ni angahe|bingahe|combien|prix de)\b/i', '', $msg);
            $productName = trim(preg_replace('/\s+/', ' ', $productName));
            if (strlen($productName) >= 2) {
                $result = handlePriceInquiry($productName, $conn);
                return reply($result['response'], $result['quick_replies']);
            }
            // Fallback to generic product search
            $rows = dbProductSearch($msg, $conn);
            if (!empty($rows)) {
                $ctx['last_products'] = $rows;
                $fp = formatProducts($rows, '', false, $lang);
                return reply($fp['text'], array_merge($fp['qr'], getBudgetQuickReplies($lang)));
            }
            return null;

        case 'stock_check':
            // Extract product name by stripping common stock-check words
            $productName = preg_replace('/\b(is|are|do you have|in stock|available|out of stock|stock of|check stock|stock check|how many left|notify me when)\b/i', '', $msg);
            $productName = trim(preg_replace('/\s+/', ' ', $productName));
            if (strlen($productName) >= 2) {
                $result = handleStockCheck($productName, $conn);
                return reply($result['response'], $result['quick_replies']);
            }
            // Fallback to generic product search
            $rows = dbProductSearch($msg, $conn);
            if (!empty($rows)) {
                $ctx['last_products'] = $rows;
                $fp = formatProducts($rows, '', false, $lang);
                return reply($fp['text'], array_merge($fp['qr'], getBudgetQuickReplies($lang)));
            }
            return null;

        case 'product_search':
        case 'recommendation':
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
            $fragments = [];
            $params = [];
            $types = '';
            foreach (array_slice($kws, 0, 4) as $w) {
                $likeW = '%' . $w . '%';
                $fragments[] = "(LOWER(question) LIKE ? OR LOWER(answer) LIKE ?)";
                $params[] = $likeW;
                $params[] = $likeW;
                $types .= 'ss';
            }
            $sql = "SELECT question, answer FROM faq WHERE status = 1 AND (" . implode(' OR ', $fragments) . ") LIMIT 3";
            $frq = $conn->prepare($sql);
            if ($frq) {
                $frq->bind_param($types, ...$params);
                $frq->execute();
                $fr = $frq->get_result();
                $frq->close();
            } else {
                $fr = false;
            }
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

    // Normalize plurals to singular for better DB matching
    $pluralMap = [
        'laptops'=>'laptop','phones'=>'phone','smartphones'=>'smartphone','tablets'=>'tablet',
        'computers'=>'computer','televisions'=>'television','fridges'=>'fridge','watches'=>'watch',
        'shoes'=>'shoe','bags'=>'bag','sofas'=>'sofa','chairs'=>'chair','beds'=>'bed','tables'=>'table',
        'speakers'=>'speaker','headphones'=>'headphone','earphones'=>'earphone','printers'=>'printer',
        'cameras'=>'camera','dresses'=>'dress','shirts'=>'shirt','trousers'=>'trouser','jackets'=>'jacket',
        'books'=>'book','pens'=>'pen','toys'=>'toy','diapers'=>'diaper','bottles'=>'bottle',
    ];
    $words = array_filter(
        explode(' ', preg_replace('/[^a-z0-9\s]/i', '', strtolower(trim($msg)))),
        fn($w) => strlen($w) >= 3 && !in_array($w, $stop)
    );
    // Apply plural normalization
    $words = array_map(fn($w) => $pluralMap[$w] ?? $w, array_values($words));
    return array_values($words);
}

// ================================================================
// PRICE RANGE EXTRACTOR
// ================================================================
function parseBudgetAmountPart(string $number, string $suffix = ''): int {
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
        $min = parseBudgetAmountPart($m[1], $m[2] ?? '');
        $max = parseBudgetAmountPart($m[3], $m[4] ?? '');
        if ($min > $max) {
            [$min, $max] = [$max, $min];
        }
    }

    if (!$max && preg_match('/(?:under|below|less than|cheaper than|maximum|max|at most|moins de|inferieur a|inférieur à|jusqu a|jusquà|ntarenze|atarengeje|munsi ya)\s*(?:rwf|frw|amafaranga|francs?)?\s*' . $num . '\s*(k|m|million|millions|milio|miliyoni)?/iu', $normalized, $m)) {
        $max = parseBudgetAmountPart($m[1], $m[2] ?? '');
    }

    if (!$min && preg_match('/(?:above|over|more than|minimum|min|at least|plus de|superieur a|supérieur à|kurenga|hejuru ya|guhera kuri|from)\s*(?:rwf|frw|amafaranga|francs?)?\s*' . $num . '\s*(k|m|million|millions|milio|miliyoni)?/iu', $normalized, $m)) {
        $min = parseBudgetAmountPart($m[1], $m[2] ?? '');
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
    $params = [];
    $types = '';

    if ($catId) {
        $conditions[] = "p.category_id = ?";
        $params[] = $catId;
        $types .= 'i';
    }
    if ($maxPrice) {
        $conditions[] = "p.price < ?";
        $params[] = $maxPrice;
        $types .= 'i';
    }
    if ($minPrice) {
        $conditions[] = "p.price >= ?";
        $params[] = $minPrice;
        $types .= 'i';
    }

    $kwConds = [];
    $kwLikeVals = [];
    foreach (array_slice($keywords, 0, 4) as $w) {
        $likeW = '%' . $w . '%';
        $kwConds[] = "(p.name LIKE ? OR p.brand LIKE ? OR p.description LIKE ?)";
        $kwLikeVals[] = $likeW;
        $kwLikeVals[] = $likeW;
        $kwLikeVals[] = $likeW;
        $types .= 'sss';
    }
    if ($kwConds) {
        $conditions[] = '(' . implode(' OR ', $kwConds) . ')';
        $params = array_merge($params, $kwLikeVals);
    }

    $firstKw = $keywords[0] ?? '';
    $firstKwLike = $firstKw ? '%' . $firstKw . '%' : '';
    $order   = $firstKw ? "CASE WHEN p.name LIKE ? THEN 0 ELSE 1 END, p.price ASC" : "p.price ASC";
    if ($firstKw) {
        $orderTypes = $types . 's';
        $orderParams = $params;
        $orderParams[] = $firstKwLike;
    } else {
        $orderTypes = $types;
        $orderParams = $params;
    }

    $where = 'WHERE ' . implode(' AND ', $conditions);
    $qsql = "SELECT p.id,p.name,p.brand,p.price,p.stock,p.description,c.name AS cat
        FROM products p LEFT JOIN categories c ON p.category_id=c.id $where ORDER BY $order LIMIT 8";
    $stmt = $conn->prepare($qsql);
    $rows = [];
    if ($stmt) {
        if ($firstKw) {
            $stmt->bind_param($orderTypes, ...$orderParams);
        } elseif ($types !== '') {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $res = $stmt->get_result();
        $stmt->close();
        if ($res) while ($r = $res->fetch_assoc()) $rows[] = $r;
    }

    // If we have results but first keyword doesn't appear in any name, try name-only search first
    if (!empty($rows) && $firstKw && $catId && ($maxPrice || $minPrice)) {
        $nameMatch = array_filter($rows, fn($r) => stripos($r['name'], $firstKw) !== false);
        if (!empty($nameMatch)) {
            return array_values($nameMatch);
        }
        // No name matches — try name-only search without keyword condition
        $conds3 = ["p.stock > 0", "p.category_id = ?",
                   "(p.name LIKE ? OR p.brand LIKE ?)"];
        $params3 = [$catId, $firstKwLike, $firstKwLike];
        $types3 = 'iss';
        if ($maxPrice) { $conds3[] = "p.price < ?"; $params3[] = $maxPrice; $types3 .= 'i'; }
        if ($minPrice) { $conds3[] = "p.price >= ?"; $params3[] = $minPrice; $types3 .= 'i'; }
        $stmt3 = $conn->prepare("SELECT p.id,p.name,p.brand,p.price,p.stock,p.description,c.name AS cat
            FROM products p LEFT JOIN categories c ON p.category_id=c.id
            WHERE " . implode(' AND ', $conds3) . " ORDER BY p.price ASC LIMIT 8");
        $nameRows = [];
        if ($stmt3) {
            $stmt3->bind_param($types3, ...$params3);
            $stmt3->execute();
            $res3 = $stmt3->get_result();
            $stmt3->close();
            if ($res3) while ($r = $res3->fetch_assoc()) $nameRows[] = $r;
        }
        if (!empty($nameRows)) return $nameRows;
    }

    // Relax: drop keyword conditions if no results but category/price matched
    if (empty($rows) && $kwConds && ($catId || $maxPrice || $minPrice)) {
        $conds2 = ['p.stock > 0'];
        $params2 = [];
        $types2 = '';
        if ($catId) { $conds2[] = "p.category_id = ?"; $params2[] = $catId; $types2 .= 'i'; }
        if ($maxPrice) { $conds2[] = "p.price < ?"; $params2[] = $maxPrice; $types2 .= 'i'; }
        if ($minPrice) { $conds2[] = "p.price >= ?"; $params2[] = $minPrice; $types2 .= 'i'; }
        $stmt2 = $conn->prepare("SELECT p.id,p.name,p.brand,p.price,p.stock,p.description,c.name AS cat
            FROM products p LEFT JOIN categories c ON p.category_id=c.id
            WHERE " . implode(' AND ', $conds2) . " ORDER BY p.price ASC LIMIT 8");
        if ($stmt2) {
            if ($types2 !== '') $stmt2->bind_param($types2, ...$params2);
            $stmt2->execute();
            $res2 = $stmt2->get_result();
            $stmt2->close();
            if ($res2) while ($r = $res2->fetch_assoc()) $rows[] = $r;
        }
    }

    // If still empty and we have a category + price, return cheapest in category above budget
    if (empty($rows) && $catId && $maxPrice) {
        $stmt3b = $conn->prepare("SELECT p.id,p.name,p.brand,p.price,p.stock,p.description,c.name AS cat
            FROM products p LEFT JOIN categories c ON p.category_id=c.id
            WHERE p.stock > 0 AND p.category_id = ?
            ORDER BY p.price ASC LIMIT 5");
        if ($stmt3b) {
            $stmt3b->bind_param("i", $catId);
            $stmt3b->execute();
            $res3b = $stmt3b->get_result();
            $stmt3b->close();
            if ($res3b) while ($r = $res3b->fetch_assoc()) $rows[] = $r;
        }
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

    $out = ($label ? strip_tags($label) : $defaultHeading) . "\n\n";
    $qr  = [];
    foreach ($rows as $p) {
        $name  = htmlspecialchars($p['name']);
        $brand = $p['brand'] ? " (" . htmlspecialchars($p['brand']) . ")" : "";
        $price = "RWF " . number_format($p['price']);
        $prodUrl = SITE_URL . '/product.php?id=' . (int)$p['id'];
        $out .= "• <a href='$prodUrl'>$name</a>$brand — $price | " . $p['stock'] . " " . $stockSuffix . "\n";
        if ($showDesc && !empty($p['description'])) {
            $desc = mb_substr(strip_tags($p['description']), 0, 120);
            $out .= "  " . htmlspecialchars($desc) . (strlen($p['description']) > 120 ? '...' : '') . "\n";
        }
        $qr[] = "🛒 Add: add_to_cart:{$p['id']}";
    }
    return ['text' => $out, 'qr' => array_slice($qr, 0, 4)];
}

// ── Full detail for a single product ──
function formatProductDetail(array $p): string {
    $stars = '';
    $out  = "🛍️ <strong><a href='" . SITE_URL . "/product.php?id={$p['id']}'>" . htmlspecialchars($p['name']) . "</a></strong><br>";
    if (!empty($p['brand']))   $out .= "🏷️ Brand: <strong>" . htmlspecialchars($p['brand']) . "</strong><br>";
    if (!empty($p['cat']))     $out .= "📂 Category: " . htmlspecialchars($p['cat']) . "<br>";
    $out .= "💰 Price: <strong>RWF " . number_format($p['price']) . "</strong><br>";
    $stockLabel = $p['stock'] > 0 ? $p['stock'] . " units available ✅" : "Out of Stock ❌";
    $out .= "📦 Stock: <strong>" . $stockLabel . "</strong><br>";
    if (!empty($p['description'])) {
        // Show full description — no truncation
        $fullDesc = strip_tags($p['description']);
        $out .= "📝 <em>" . htmlspecialchars($fullDesc) . "</em><br>";
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
        'orders' => 0,
        'pending_orders' => 0,
        'delivered_orders' => 0,
        'customers' => 0,
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

    $orders = $conn->query("
        SELECT
            COUNT(*) AS orders,
            SUM(CASE WHEN status='pending' THEN 1 ELSE 0 END) AS pending_orders,
            SUM(CASE WHEN status='delivered' THEN 1 ELSE 0 END) AS delivered_orders
        FROM orders
    ");
    if ($orders && ($row = $orders->fetch_assoc())) {
        $snapshot['orders'] = (int)($row['orders'] ?? 0);
        $snapshot['pending_orders'] = (int)($row['pending_orders'] ?? 0);
        $snapshot['delivered_orders'] = (int)($row['delivered_orders'] ?? 0);
    }

    $customers = $conn->query("SELECT COUNT(*) AS customers FROM users");
    if ($customers && ($row = $customers->fetch_assoc())) {
        $snapshot['customers'] = (int)($row['customers'] ?? 0);
    }

    return $snapshot;
}

function getStoreOverviewText($conn, string $lang = 'en'): string {
    // Normalize language codes
    if ($lang === 'kinyarwanda') $lang = 'rw';
    elseif ($lang === 'french') $lang = 'fr';
    elseif ($lang === 'english') $lang = 'en';
    
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

function getPlatformKnowledgeText($conn, ?int $uid, string $lang = 'en'): string {
    if ($lang === 'kinyarwanda') $lang = 'rw';
    elseif ($lang === 'french') $lang = 'fr';
    elseif ($lang === 'english') $lang = 'en';

    $snapshot = getStoreSnapshotData($conn);
    $topCategories = [];
    foreach ($snapshot['top_categories'] as $category) {
        $topCategories[] = htmlspecialchars($category['name']) . ' (' . number_format($category['total']) . ')';
    }
    $topCategoriesText = $topCategories ? implode(', ', $topCategories) : 'N/A';

    $text = "<strong>" . SITE_NAME . " platform knowledge base</strong><br>" .
        "Products in stock: <strong>" . number_format($snapshot['products']) . "</strong><br>" .
        "Categories: <strong>" . number_format($snapshot['categories']) . "</strong><br>" .
        "Brands: <strong>" . number_format($snapshot['brands']) . "</strong><br>" .
        "Registered customers: <strong>" . number_format($snapshot['customers']) . "</strong><br>" .
        "Orders recorded: <strong>" . number_format($snapshot['orders']) . "</strong><br>" .
        "Price range: <strong>RWF " . number_format($snapshot['min_price']) . " - RWF " . number_format($snapshot['max_price']) . "</strong><br>" .
        "Top categories: " . $topCategoriesText . "<br><br>" .
        "<strong>What I answer from the database:</strong><br>" .
        "1. Product search by name, category, brand, price, stock and description<br>" .
        "2. Budget recommendations using live product prices<br>" .
        "3. Logged-in customer order tracking, order history, invoices and cancellation guidance<br>" .
        "4. Guest ordering steps from browsing to login/register, checkout, address, payment and confirmation<br>" .
        "5. Delivery, payment, return, warranty and support information<br><br>" .
        "<strong>Core store policies:</strong><br>" .
        "Delivery: Kigali 1-2 business days, other provinces 2-4 business days<br>" .
        "Free shipping on all orders<br>" .
        "Payments: Cash on Delivery, MTN MoMo, Airtel Money, Visa/Mastercard and bank transfer<br>" .
        "Returns: 7 days after delivery for eligible items<br>" .
        "Support: <a href='mailto:" . ADMIN_EMAIL . "'>" . ADMIN_EMAIL . "</a> | " . ADMIN_PHONE;

    if ($lang === 'fr') {
        $text .= "<br><br>Je donne les reponses de boutique directement depuis la base de donnees quand c'est possible.";
    } elseif ($lang === 'rw') {
        $text .= "<br><br>Amakuru y'ibicuruzwa, order na platform nyakura muri database igihe bishoboka.";
    }

    return $text;
}

function getCategoryNameById($conn, ?int $catId): ?string {
    if (!$catId) return null;
    $stmt = $conn->prepare("SELECT name FROM categories WHERE id=? LIMIT 1");
    if (!$stmt) return null;
    $stmt->bind_param("i", $catId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row['name'] ?? null;
}

function recommendProductsForBudget(int $budget, ?int $catId, $conn, string $lang = 'en'): array {
    $params = [$budget];
    $types = 'i';
    $catClause = '';
    if ($catId) { $catClause = "AND p.category_id = ?"; $params[] = $catId; $types .= 'i'; }

    $rows = [];
    $stmt = $conn->prepare("SELECT p.id,p.name,p.brand,p.price,p.stock,p.description,c.name AS cat
        FROM products p LEFT JOIN categories c ON p.category_id=c.id
        WHERE p.stock > 0 AND p.price <= ? $catClause
        ORDER BY p.price DESC, p.stock DESC
        LIMIT 8");
    if ($stmt) {
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $res = $stmt->get_result();
        $stmt->close();
        if ($res) while ($r = $res->fetch_assoc()) $rows[] = $r;
    }

    $catName = getCategoryNameById($conn, $catId);
    if (!empty($rows)) {
        $label = "Best recommendations" . ($catName ? " in $catName" : "") . " within RWF " . number_format($budget);
        $fp = formatProducts($rows, $label, true, $lang);
        return reply(
            "I matched your budget against live product prices and prioritized strong options closest to your amount.<br><br>" . $fp['text'],
            array_merge($fp['qr'], ['Different category', 'How to order'])
        );
    }

    $alt = [];
    $params2 = [$budget];
    $types2 = 'i';
    $catClause2 = '';
    if ($catId) { $catClause2 = "AND p.category_id = ?"; $params2[] = $catId; $types2 .= 'i'; }
    $stmt2 = $conn->prepare("SELECT p.id,p.name,p.brand,p.price,p.stock,p.description,c.name AS cat
        FROM products p LEFT JOIN categories c ON p.category_id=c.id
        WHERE p.stock > 0 AND p.price > ? $catClause2
        ORDER BY p.price ASC
        LIMIT 5");
    if ($stmt2) {
        $stmt2->bind_param($types2, ...$params2);
        $stmt2->execute();
        $res2 = $stmt2->get_result();
        $stmt2->close();
        if ($res2) while ($r = $res2->fetch_assoc()) $alt[] = $r;
    }

    if (!empty($alt)) {
        $fp = formatProducts($alt, "Closest options above RWF " . number_format($budget), true, $lang);
        return reply(
            "I did not find an in-stock product within that exact budget" . ($catName ? " in $catName" : "") . ". Here are the closest affordable alternatives:<br><br>" . $fp['text'],
            array_merge($fp['qr'], ['Show me cheaper options'])
        );
    }

    return reply("I could not find a matching in-stock product for that budget. Try another category or a higher amount.", ['Show me products', 'Show me categories']);
}

function getOrderGuideText(?int $uid, string $lang = 'en'): string {
    // Normalize language codes
    if ($lang === 'kinyarwanda') $lang = 'rw';
    elseif ($lang === 'french') $lang = 'fr';
    elseif ($lang === 'english') $lang = 'en';
    
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
    
    // Normalize language codes
    if ($lang === 'kinyarwanda') $lang = 'rw';
    elseif ($lang === 'french') $lang = 'fr';
    elseif ($lang === 'english') $lang = 'en';
    
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
    // Normalize language codes
    if ($lang === 'kinyarwanda') $lang = 'rw';
    elseif ($lang === 'french') $lang = 'fr';
    elseif ($lang === 'english') $lang = 'en';
    
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
    // Normalize language codes
    if ($lang === 'kinyarwanda') $lang = 'rw';
    elseif ($lang === 'french') $lang = 'fr';
    elseif ($lang === 'english') $lang = 'en';
    
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
    // Normalize language codes
    if ($lang === 'kinyarwanda') $lang = 'rw';
    elseif ($lang === 'french') $lang = 'fr';
    elseif ($lang === 'english') $lang = 'en';
    
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
    // Normalize language codes
    if ($lang === 'kinyarwanda') $lang = 'rw';
    elseif ($lang === 'french') $lang = 'fr';
    elseif ($lang === 'english') $lang = 'en';
    
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

function isStartOrderRequest(string $ml): bool {
    return (bool)preg_match(
        '/\b(place|start|make|create|begin|new|another|continue|shop|shopping|buy|order)\b.*\border\b|\border\b.*\b(now|again|another|new|start|place|buy)\b|\bcontinue shopping\b/i',
        $ml
    );
}

function getStartOrderReply(?int $uid, array &$ctx): array {
    $ctx['awaiting'] = null;
    $ctx['order_step'] = null;
    unset($ctx['order_pending_product']);

    if (!$uid) {
        return reply(
            "Sure. Tell me the product you want, for example <em>I want Samsung Galaxy A54</em>.<br><br>" .
            "You can browse first, but you will need to login or register before confirming the order.",
            ['Show me products', 'Login', 'Register']
        );
    }

    if (!empty($ctx['order_cart'])) {
        return reply(
            "Sure. Your chat cart is ready. You can add another product or continue to checkout.<br><br>" .
            chatCartSummary($ctx['order_cart']),
            ['Add more products', 'Proceed to checkout', 'Clear cart']
        );
    }

    return reply(
        "Sure. What product would you like to order? You can type a product name, for example <em>I want Samsung Galaxy A54</em>, or browse products first.",
        ['Show me products', 'Show me phones', 'Show me laptops']
    );
}

function processMessage(string $msg, ?int $uid, $conn, array &$ctx, string $session_id): array {
    $ml = strtolower(trim($msg));

    // ── Spelling correction via Flask (if running) ──
    $cachedPredictResult = null;
    $predictCalled = false;
    if (strlen($msg) >= 2) {
        try {
            $corrCtx = stream_context_create(['http' => [
                'method'  => 'POST',
                'header'  => "Content-Type: application/json\r\n",
                'content' => json_encode(['message' => $msg, 'model' => 'best']),
                'timeout' => 3,
            ]]);
            $corrResp = @file_get_contents(ML_API_BASE . '/predict/ensemble', false, $corrCtx);
            if ($corrResp !== false) {
                $corrData = json_decode($corrResp, true);
                if ($corrData && !empty($corrData['corrected_message']) && $corrData['corrected_message'] !== $msg) {
                    $msg = $corrData['corrected_message'];
                    $ml = strtolower(trim($msg));
                }
                $cachedPredictResult = $corrData;
                $predictCalled = true;
            }
        } catch (Throwable $e) {
            error_log("Spell correction via Flask failed: " . $e->getMessage());
        }
    }

    // ── PHP fallback auto-correct for common typos (covers when Flask is unavailable) ──
    $typos = [
        '/\bant\b/i'     => 'any',
        '/\bteh\b/i'     => 'the',
        '/\byuo\b/i'     => 'you',
        '/\badn\b/i'     => 'and',
        '/\bjstu\b/i'    => 'just',
        '/\bjsut\b/i'    => 'just',
        '/\bwaht\b/i'    => 'what',
        '/\bhtat\b/i'    => 'that',
        '/\btaht\b/i'    => 'that',
        '/\bhten\b/i'    => 'then',
        '/\bthna\b/i'    => 'than',
        '/\bwoudl\b/i'   => 'would',
        '/\bshoudl\b/i'  => 'should',
        '/\bcoudl\b/i'   => 'could',
        '/\bale\b/i'     => 'all',
        '/\bdont\b/i'    => "don't",
        '/\bdidnt\b/i'   => "didn't",
        '/\bcant\b/i'    => "can't",
        '/\bwont\b/i'    => "won't",
        '/\bisnt\b/i'    => "isn't",
        '/\barent\b/i'   => "aren't",
        '/\bwasnt\b/i'   => "wasn't",
        '/\bwerent\b/i'  => "weren't",
        '/\bhavent\b/i'  => "haven't",
        '/\bhasnt\b/i'   => "hasn't",
        '/\bhadnt\b/i'   => "hadn't",
        '/\bdoesnt\b/i'  => "doesn't",
        '/\bpls\b/i'     => 'please',
        '/\bplz\b/i'     => 'please',
        '/\bthx\b/i'     => 'thanks',
        '/\bthnx\b/i'    => 'thanks',
        '/\bty\b/i'      => 'thank you',
        '/\bgonna\b/i'   => 'going to',
        '/\bwanna\b/i'   => 'want to',
    ];
    $corrected = preg_replace(array_keys($typos), array_values($typos), $msg);
    if ($corrected !== $msg) {
        error_log("processMessage: PHP auto-correct \"$msg\" -> \"$corrected\"");
        $msg = $corrected;
        $ml = strtolower(trim($msg));
    }

    // ── Get store snapshot data for use throughout the function ──
    $snapshot = getStoreSnapshotData($conn);
    
    // ── Detect language and store in context ──
    $detected_lang = detect_language($msg);
    $ctx['language'] = $detected_lang;
    
    // Try to save context, but don't fail if it errors
    try {
        saveContext($session_id, $uid, 'language', $detected_lang);
    } catch (Throwable $e) {
        error_log("Warning: Failed to save language context: " . $e->getMessage());
    }

    // ── Enrich size-only follow-ups with previous search context ──
    $searchMsg = $msg;
    $kws = extractKeywords($msg);
    $hasNewSearchKeywords = !empty($kws) || detectCategory($ml);
    $isSizeModifier = preg_match('/^(?:\d+(?:\.\d+)?\s*[a-zA-Z]{1,3}|\d+)$/i', trim($ml));
    if ($isSizeModifier && !$hasNewSearchKeywords) {
        $lastTerm = $ctx['last_search_term'] ?? '';
        if ($lastTerm) {
            $searchMsg = $lastTerm . ' ' . $msg;
        }
    } elseif ($hasNewSearchKeywords) {
        $ctx['last_search_term'] = $msg;
    }

    // ── Retrieve prior context for personalization ──
    $lastBudget = null;
    $lastBudgetMin = null;
    $lastProduct = null;
    try {
        $lastBudget    = getContext($session_id, 'last_budget');
        $lastBudgetMin = getContext($session_id, 'last_budget_min');
        $lastProduct   = getContext($session_id, 'last_product_interest');
    } catch (Throwable $e) {
        error_log("Warning: Failed to retrieve context: " . $e->getMessage());
    }

    // ── Follow-up: "show me options / more / what else" — use prior budget ──
    if ($lastBudget && (int)$lastBudget >= 1000 &&
        preg_match('/\b(show me options|show me more|what else|more options|other options|show more|see more)\b/i', $ml)) {
        $qualifier = detectPriceQualifier($ml);
        $result = handleBudgetQuery((int)$lastBudget, null, $conn, $lastBudgetMin ? (int)$lastBudgetMin : null, $qualifier);
        return reply(
            "Based on your earlier budget of " . formatRWF((int)$lastBudget) . ":<br><br>" . $result['response'],
            $result['quick_replies']
        );
    }

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
            $stmtsu = $conn->prepare("SELECT name, email FROM users WHERE id=?");
            if ($stmtsu) { $stmtsu->bind_param("i", $uid); $stmtsu->execute(); $u = $stmtsu->get_result()->fetch_assoc(); $stmtsu->close(); }
            if ($u) { $customerName = $u['name']; $customerEmail = $u['email']; }
        }
        // ── Save to support_tickets table ──
        $ui = $uid ? (int)$uid : null;
        $stmts = $conn->prepare("INSERT INTO support_tickets (user_id, session_id, customer_name, customer_email, message) VALUES (?, ?, ?, ?, ?)");
        if ($stmts) {
            $stmts->bind_param("issss", $ui, $session_id, $customerName, $customerEmail, $supportMsg);
            $stmts->execute();
            $stmts->close();
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

    if (preg_match('/\b(how to order|how do i order|how to buy|how do i buy|steps to buy|steps to purchase|how to shop|how to purchase|how to place an order|how to place order|ordering process|steps to order|steps to place an order|guide to order|guide to buy|can i order as a guest|can i place an order as a guest|order as a guest|place an order as a guest|place an order as a guest|how can a guest place an order|how does guest ordering work|guest checkout|guest order guide|guest order|can a guest order)\b/i', $ml)) {
        return reply(
            getOrderGuideText($uid, $ctx['language'] ?? 'en'),
            getOrderGuideQuickReplies($ctx['language'] ?? 'en', $uid)
        );
    }

    $isOrderManagementRequest = preg_match('/\b(track|tracking|status|history|past orders|previous orders|my orders|invoice|receipt|cancel|return|refund|delivery|shipping)\b/i', $ml);
    $mentionsSpecificProduct = detectCategory($ml) || preg_match('/\b(samsung|apple|iphone|nokia|tecno|infinix|xiaomi|oppo|vivo|hp|dell|lenovo|asus|acer|lg|sony|jbl|nike|adidas|huawei|bose|philips|panasonic|dyson|kenwood|casio|fossil|lego|pampers)\b/i', $ml);
    if (!$isOrderManagementRequest && !$mentionsSpecificProduct && isStartOrderRequest($ml)) {
        return getStartOrderReply($uid, $ctx);
    }

    if (preg_match('/\b(add more products|add another product|add more items|add another item)\b/i', $ml)) {
        $ctx['order_step'] = null;
        unset($ctx['order_pending_product']);
        return reply(
            "Of course. Type the product name you want to add, or browse a category.",
            ['Show me products', 'Show me phones', 'Show me laptops']
        );
    }

    if (preg_match('/\b(platform|store overview|about this store|about this platform|how many products|how many categories|how many brands|what do you sell|database|catalog overview|system information|business information|shop information|what can you do|what do you offer|tell me about yourself|what are your capabilities|what services do you offer|tell me about this store)\b/i', $ml)) {
        return reply(
            getPlatformKnowledgeText($conn, $uid, $ctx['language'] ?? 'en'),
            ['Show me categories', 'How to order', 'Payment methods', 'Delivery info']
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
        $stmttp = $conn->prepare("SELECT id,name,price,stock FROM products WHERE id=? AND stock>0 LIMIT 1");
        $p = null;
        if ($stmttp) { $stmttp->bind_param("i", $pid); $stmttp->execute(); $p = $stmttp->get_result()->fetch_assoc(); $stmttp->close(); }
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
        $lang = $ctx['language'] ?? detect_language($msg);
        $isGuest = !$uid;
        
        if ($lang === 'kinyarwanda') {
            if ($isGuest) {
                $response = "🛒 <strong>Uko Umushyitsi Atumiza (Guest Ordering):</strong><br><br>" .
                    "📝 <strong>Intambwe 1:</strong> Shakisha ibicuruzwa - Mbwira icyo ushaka (urugero: 'Ndashaka telefoni')<br>" .
                    "👀 <strong>Intambwe 2:</strong> Reba ibicuruzwa byose - Nzaguhitiramo ibicuruzwa byiza muri categories 15 dufite (1,161 products)<br>" .
                    "🛒 <strong>Intambwe 3:</strong> Ongera mu cart - Kanda 'Add to Cart' cyangwa mbwira uti 'Ndashaka [product name]'<br>" .
                    "🔐 <strong>Intambwe 4:</strong> Fungura account cyangwa winjire - Kanda <a href='" . SITE_URL . "/register.php'><strong>Iyandikishe</strong></a> (by'ubuntu!)<br>" .
                    "📍 <strong>Intambwe 5:</strong> Andika aho byakugerera - Uzuza address yawe ya delivery<br>" .
                    "💳 <strong>Intambwe 6:</strong> Hitamo uburyo bwo kwishyura - MoMo, Airtel Money, COD, Card, cyangwa Bank Transfer<br>" .
                    "✅ <strong>Intambwe 7:</strong> Emeza order yawe - Kanda 'Place Order'<br><br>" .
                    "💡 <strong>Inama:</strong> Gufungura account bifasha:
• Gukurikirana order yawe
• Kubona invoice
• Gufata wishlist yawe
• Kubona order history yawe";
            } else {
                $userName = getContext(session_id(), 'user_name') ?? '';
                $greeting = $userName ? " Murakoze $userName!" : "";
                $response = "🛒 <strong>Uko Utumiza (Waje muri Account):</strong>$greeting<br><br>" .
                    "✅ Waje muri account, byoroshye kurushaho!<br><br>" .
                    "1️⃣ Shakisha igicuruzwa cyangwa mbwira icyo ushaka<br>" .
                    "2️⃣ Kanda 'Add to Cart' cyangwa mbwira uti 'Ndashaka [product]'<br>" .
                    "3️⃣ Reba cart yawe <a href='" . SITE_URL . "/cart.php'><strong>Aha</strong></a><br>" .
                    "4️⃣ Kanda 'Proceed to Checkout'<br>" .
                    "5️⃣ Emeza address yawe na payment method<br>" .
                    "6️⃣ Kanda 'Place Order' - Byarangiye! 🎉<br><br>" .
                    "📦 Ushobora gukurikirana order yawe hano: <a href='" . SITE_URL . "/orders.php'><strong>My Orders</strong></a>";
            }
        } elseif ($lang === 'french') {
            if ($isGuest) {
                $response = "🛒 <strong>Comment Commander en tant qu'Invité:</strong><br><br>" .
                    "📝 <strong>Étape 1:</strong> Recherchez des produits - Dites-moi ce que vous cherchez (ex: 'Je veux un téléphone')<br>" .
                    "👀 <strong>Étape 2:</strong> Parcourez les produits - Je vous montrerai les meilleurs produits dans nos 15 catégories (1,161 produits)<br>" .
                    "🛒 <strong>Étape 3:</strong> Ajoutez au panier - Cliquez 'Add to Cart' ou dites 'Je veux [nom du produit]'<br>" .
                    "🔐 <strong>Étape 4:</strong> Créez un compte ou connectez-vous - Cliquez <a href='" . SITE_URL . "/register.php'><strong>Créer un compte</strong></a> (c'est gratuit!)<br>" .
                    "📍 <strong>Étape 5:</strong> Entrez votre adresse de livraison<br>" .
                    "💳 <strong>Étape 6:</strong> Choisissez le mode de paiement - MoMo, Airtel Money, COD, Carte ou Virement<br>" .
                    "✅ <strong>Étape 7:</strong> Confirmez votre commande - Cliquez 'Place Order'<br><br>" .
                    "💡 <strong>Conseil:</strong> Créer un compte vous permet de:
• Suivre vos commandes
• Télécharger des factures
• Sauvegarder votre wishlist
• Voir l'historique des commandes";
            } else {
                $response = "🛒 <strong>Comment Commander (Connecté):</strong><br><br>" .
                    "✅ Vous êtes connecté, c'est plus simple!<br><br>" .
                    "1️⃣ Recherchez un produit ou dites-moi ce que vous voulez<br>" .
                    "2️⃣ Cliquez 'Add to Cart' ou dites 'Je veux [produit]'<br>" .
                    "3️⃣ Voir votre panier <a href='" . SITE_URL . "/cart.php'><strong>Ici</strong></a><br>" .
                    "4️⃣ Cliquez 'Proceed to Checkout'<br>" .
                    "5️⃣ Confirmez votre adresse et mode de paiement<br>" .
                    "6️⃣ Cliquez 'Place Order' - C'est fait! 🎉<br><br>" .
                    "📦 Suivez votre commande ici: <a href='" . SITE_URL . "/orders.php'><strong>Mes Commandes</strong></a>";
            }
        } else {
            if ($isGuest) {
                $response = "🛒 <strong>How to Order as a Guest:</strong><br><br>" .
                    "📝 <strong>Step 1:</strong> Search for products - Tell me what you're looking for (e.g., 'Show me phones')<br>" .
                    "👀 <strong>Step 2:</strong> Browse products - I'll show you the best items across our 15 categories (1,161 products)<br>" .
                    "🛒 <strong>Step 3:</strong> Add to cart - Click 'Add to Cart' or say 'I want [product name]'<br>" .
                    "🔐 <strong>Step 4:</strong> Create account or login - Click <a href='" . SITE_URL . "/register.php'><strong>Create Account</strong></a> (it's free!)<br>" .
                    "📍 <strong>Step 5:</strong> Enter your delivery address<br>" .
                    "💳 <strong>Step 6:</strong> Choose payment method - MoMo, Airtel Money, COD, Card, or Bank Transfer<br>" .
                    "✅ <strong>Step 7:</strong> Confirm your order - Click 'Place Order'<br><br>" .
                    "💡 <strong>Tip:</strong> Creating an account lets you:
• Track your orders
• Download invoices
• Save your wishlist
• View order history";
            } else {
                $userName = getContext(session_id(), 'user_name') ?? '';
                $greeting = $userName ? " Hi $userName!" : "";
                $response = "🛒 <strong>How to Order (Logged In):</strong>$greeting<br><br>" .
                    "✅ You're logged in, making it even easier!<br><br>" .
                    "1️⃣ Search for a product or tell me what you want<br>" .
                    "2️⃣ Click 'Add to Cart' or say 'I want [product]'<br>" .
                    "3️⃣ View your cart <a href='" . SITE_URL . "/cart.php'><strong>Here</strong></a><br>" .
                    "4️⃣ Click 'Proceed to Checkout'<br>" .
                    "5️⃣ Confirm your address and payment method<br>" .
                    "6️⃣ Click 'Place Order' - Done! 🎉<br><br>" .
                    "📦 Track your order here: <a href='" . SITE_URL . "/orders.php'><strong>My Orders</strong></a>";
            }
        }
        
        return reply($response, ['Show me products', 'Create account', 'Track my order']);
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
    if (preg_match('/\b(hi|hello|hey|good morning|morning|good afternoon|good evening|bonjour|salut|muraho|mwaramutse|mwiriwe|howdy|hie|sup|yo)\b/i', $ml)) {
        // If message also contains a product/service request, skip greeting and let the right handler respond
        $hasProductRequest = preg_match('/\b(need|want|looking|find|show|search|buy|price|under|budget|smartphone|phone|laptop|tv|product|order|track|delivery|payment|return|invoice|cancel)\b/i', $ml);
        if (!$hasProductRequest) {
            $lang = $ctx['language'] ?? 'english';
            
            // ── Personalization: welcome back + recently viewed + segment ──
            $greeting = "Hello";
            $personalizedLine = '';
            if ($uid) {
                $stmto = $conn->prepare("SELECT COUNT(*) as cnt, COALESCE(SUM(total_price),0) as spent FROM orders WHERE user_id=? AND status != 'cancelled'");
                $orderCheck = null;
                if ($stmto) { $stmto->bind_param("i", $uid); $stmto->execute(); $orderCheck = $stmto->get_result(); $stmto->close(); }
                if ($orderCheck && $row = $orderCheck->fetch_assoc()) {
                    if ((int)$row['cnt'] > 0) {
                        $greeting = "Welcome back";
                        $personalizedLine = " You have <strong>{$row['cnt']} order(s)</strong> (RWF " . number_format((float)$row['spent']) . ")";
                        $spent = (float)$row['spent'];
                        if ($spent >= 500000) $personalizedLine .= " 🏆 VIP";
                        elseif ($spent >= 200000) $personalizedLine .= " ⭐ Regular";
                    }
                }
                $stmtrv = $conn->prepare("SELECT p.name FROM product_views pv JOIN products p ON p.id=pv.product_id WHERE pv.user_id=? ORDER BY pv.viewed_at DESC LIMIT 3");
                if ($stmtrv) { $stmtrv->bind_param("i", $uid); $stmtrv->execute(); $rvRes = $stmtrv->get_result(); $stmtrv->close(); }
                if ($rvRes && $rvRes->num_rows > 0) {
                    $rvNames = [];
                    while ($rv = $rvRes->fetch_assoc()) $rvNames[] = $rv['name'];
                    $personalizedLine .= "<br><small>Recently viewed: " . implode(', ', $rvNames) . "</small>";
                }
            }
            
            if ($lang === 'kinyarwanda') {
                return reply("🇷🇼 Muraho" . ($uid && $greeting === "Welcome back" ? " mwaramutse" : "") . "! Nkomeretswe kubafasha." . ($personalizedLine ? " " . $personalizedLine : ""), ['🛍️ Show me products', '📦 Track my order', '💰 I have a budget', '🛒 How to order?', '📞 Contact support']);
            } elseif ($lang === 'french') {
                return reply("🇫🇷 " . ($greeting === "Welcome back" ? "Bon retour" : "Bonjour") . "! Je suis votre assistant IA." . ($personalizedLine ? " " . $personalizedLine : ""), ['🛍️ Show me products', '📦 Track my order', '💰 I have a budget', '🛒 How to order?', '📞 Contact support']);
            } else {
                $name = '';
                if ($uid) {
                    $stmtr = $conn->prepare("SELECT name FROM users WHERE id=? LIMIT 1");
                    $uRes = null;
                    if ($stmtr) { $stmtr->bind_param("i", $uid); $stmtr->execute(); $uRes = $stmtr->get_result(); $stmtr->close(); }
                    if ($uRes && $u = $uRes->fetch_assoc()) $name = ' ' . explode(' ', $u['name'])[0];
                }
                return reply("👋 <strong>$greeting$name!</strong> I'm your AI shopping assistant." . "<br>" . ($personalizedLine ? $personalizedLine . "<br>" : "") . "How can I help you today?", ['🛍️ Show me products', '📦 Track my order', '💰 I have a budget', '🛒 How to order?', '📞 Contact support']);
            }
        }
    }
    
    // ── Recently viewed products query (after greeting check) ──
    if (preg_match('/\b(recently viewed|viewed recently|viewed products|products i viewed|browsing history|my history|what did i view|what did i see)\b/i', $ml)) {
        if ($uid) {
            $stmtrv2 = $conn->prepare("
                SELECT p.id, p.name, p.brand, p.price, p.stock, p.description, c.name AS category
                FROM product_views pv
                JOIN products p ON p.id = pv.product_id
                LEFT JOIN categories c ON p.category_id = c.id
                WHERE pv.user_id=?
                ORDER BY pv.viewed_at DESC LIMIT 8
            ");
            $rvRes = null;
            if ($stmtrv2) { $stmtrv2->bind_param("i", $uid); $stmtrv2->execute(); $rvRes = $stmtrv2->get_result(); $stmtrv2->close(); }
            $rvProducts = [];
            if ($rvRes) while ($r = $rvRes->fetch_assoc()) {
                $rvProducts[] = $r;
            }
            if (!empty($rvProducts)) {
                $fp = formatProducts($rvProducts, 'Recently Viewed Products', true);
                return reply("👁️ <strong>Products you viewed recently:</strong><br><br>" . $fp['text'], $fp['qr']);
            }
        }
        // Fallback: show popular products
        $popular = $conn->query("SELECT p.id, p.name, p.brand, p.price, p.stock FROM products p WHERE p.stock>0 ORDER BY RAND() LIMIT 5");
        $popList = [];
        if ($popular) while ($r = $popular->fetch_assoc()) $popList[] = $r;
        if (!empty($popList)) {
            $fp = formatProducts($popList, 'Popular Products', true);
            return reply("📭 No recently viewed products yet. Here are some popular items:<br><br>" . $fp['text'], $fp['qr']);
        }
        return reply("📭 You haven't viewed any products yet. Start browsing!", ['Show me products', 'Browse categories']);
    }

    // ── 2. GOODBYE / THANKS ──
    if (preg_match('/\b(bye|goodbye|see you|take care|later|thank you|thanks|merci|murakoze|au revoir|ciao)\b/i', $ml)) {
        $lang = $ctx['language'] ?? detect_language($msg);
        
        if ($lang === 'kinyarwanda') {
            $closing = $uid
                ? "😊 Murakoze, <strong>" . getFirstName($uid, $conn) . "</strong>! Nzira nziza. Subira igihe cyose! 🌟"
                : "😊 Murakoze kubisura <strong>" . SITE_NAME . "</strong>! Nzira nziza! 🌟";
        } elseif ($lang === 'french') {
            $closing = $uid
                ? "😊 Merci, <strong>" . getFirstName($uid, $conn) . "</strong>! Bonne journée. Revenez anytime! 🌟"
                : "😊 Merci d'avoir visité <strong>" . SITE_NAME . "</strong>! Bonne journée! 🌟";
        } else {
            $closing = $uid
                ? "😊 Thank you, <strong>" . getFirstName($uid, $conn) . "</strong>! Have a great day. Come back anytime! 🌟"
                : "😊 Thank you for visiting <strong>" . SITE_NAME . "</strong>! Have a great day! 🌟";
        }
        
        return reply($closing, ['Browse products', 'Contact support']);
    }

    // ── 3. SMALL TALK ──
    if (preg_match('/how are you|how r u|how do you do|ça va|comment allez|nari ute/i', $ml)) {
        $lang = $ctx['language'] ?? detect_language($msg);
        
        if ($lang === 'kinyarwanda') {
            $response = "😊 Nari neza, murakoze! Nari neza kubagenzi. Ndi iki nkwifuza?";
        } elseif ($lang === 'french') {
            $response = "😊 Je vais très bien, merci de demander! Toujours prêt à vous aider. Que puis-je trouver pour vous?";
        } else {
            $response = "😊 I'm doing great, thanks for asking! Always ready to help you shop. What can I find for you today?";
        }
        
        return reply($response, ['Show me products', 'Track my order']);
    }
    if (preg_match('/who are you|what are you|your name|are you (a bot|human|real|ai)/i', $ml)) {
        $lang = $ctx['language'] ?? detect_language($msg);
        
        if ($lang === 'kinyarwanda') {
            $response = "🤖 Ndi AI shopping assistant. Nshobora kubagenzi gushaka ibicuruzwa, kugereranya ibiciro, no kubagenzi ku delivery, payment, returns na support.";
        } elseif ($lang === 'french') {
            $response = "🤖 Je suis un assistant d'achat IA. Je peux vous aider à trouver des produits, comparer les prix, et répondre à vos questions sur la livraison, le paiement, les retours et le support.";
        } else {
            $response = "🤖 I'm an AI shopping assistant. I can help you find products, compare prices, and answer questions about delivery, payment, returns, and support.";
        }
        
        return reply($response, ['Show me products', 'How to order']);
    }
    if (preg_match('/tell me about (this platform|this store|this shop)|what do you know about (this platform|this store|this shop)|platform overview|store overview|shop overview|about this ecommerce platform|about your platform|how many products do you have|how many categories do you have|what categories do you have|what brands do you have|what do you sell here|catalog overview|parlez moi de cette plateforme|informations sur la boutique|combien de produits avez vous|quelles categories avez vous|que vendez vous ici|mbwira ibijyanye n(uru rubuga|iri duka)|amakuru y(ububiko|urubuga)|mufite ibicuruzwa bingahe|mugurisha iki/i', $ml)) {
        $lang = $ctx['language'] ?? detect_language($msg);
        
        if ($lang === 'kinyarwanda') {
            $response = "🏬 Dufite ibicuruzwa 1,161+ biri mu bubiko, 15 ibyiciro na 181 brands. Nshobora kubagenzi gushaka ibicuruzwa, kugereranya ibiciro, no kubagenzi ku delivery, payment, returns na support.";
        } elseif ($lang === 'french') {
            $response = "🏬 Nous avons plus de 1 161 produits en stock, 15 catégories et 181 marques. Je peux vous aider à trouver des produits, comparer les prix, et répondre à vos questions sur la livraison, le paiement, les retours et le support.";
        } else {
            $response = "🏬 We have 1,161+ products in stock, 15 categories, and 181 brands. I can help you find products, compare prices, and answer questions about delivery, payment, returns, and support.";
        }
        
        return reply($response, ['Show me products', 'Track my order']);
    }

    if (preg_match('/what can you do|how can you help|help me/i', $ml)) {
        $lang = $ctx['language'] ?? detect_language($msg);
        
        if ($lang === 'kinyarwanda') {
            $response = "🤖 Nshobora:<br>• Gushaka ibicuruzwa n'ibiciro<br>• Kugereranya ibiciro<br>• Kubagenzi ku delivery, payment, returns<br>• Gukurikirana orders<br>• Kubagenzi mu Cyongereza, Igifaransa no mu Kinyarwanda";
        } elseif ($lang === 'french') {
            $response = "🤖 Je peux:<br>• Chercher des produits et des prix<br>• Comparer les prix<br>• Vous aider avec la livraison, le paiement, les retours<br>• Suivre les commandes<br>• Répondre en anglais, français ou kinyarwanda";
        } else {
            $response = "🤖 I can:<br>• Search for products and prices<br>• Compare prices<br>• Help with delivery, payment, returns<br>• Track orders<br>• Answer in English, French, or Kinyarwanda";
        }
        
        return reply($response, ['Show me products', 'How to order']);
    }
    if (false && $uid) {
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
        } elseif (false) {
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

    // ── 4. ORDER TRACKING ──
    if (preg_match('/\b(track|tracking|order status|where is my order|check order|order #|order no|my order)\b/i', $ml)
        || preg_match('/^#\d+$/', trim($ml))
        || $ctx['awaiting'] === 'order_number') {
        if (!$uid) return reply(
            "👤 Order tracking requires an account. Please <a href='" . SITE_URL . "/login.php'><strong>login</strong></a> or <a href='" . SITE_URL . "/register.php'><strong>create a free account</strong></a> to track your orders and view order details.",
            ['🔑 Login', '📝 Register', '🛍️ Browse products', '📞 Contact support']
        );
        if (preg_match('/#?0*(\d+)\b/', $msg, $m)) {
            return reply(trackOrder((int)$m[1], $uid, $conn), ['View all orders', 'Cancel an order']);
        }
        $stmtr = $conn->prepare("SELECT id,status FROM orders WHERE user_id=? ORDER BY created_at DESC LIMIT 1");
        $latest = null;
        if ($stmtr) { $stmtr->bind_param("i", $uid); $stmtr->execute(); $latest = $stmtr->get_result()->fetch_assoc(); $stmtr->close(); }
        if ($latest) {
            return reply("Your latest order is <strong>#" . $latest['id'] . "</strong> — Status: <strong>" . ucfirst($latest['status']) . "</strong>.<br>Type the order number for full details.",
                ['Track order ' . $latest['id'], 'View all orders']);
        }
        $ctx['awaiting'] = 'order_number';
        return reply("Please provide your order number. Example: <em>track order 5</em><br>Find it on the <a href='" . SITE_URL . "/orders.php'>My Orders</a> page.");
    }

    // ── 5. ORDER CANCEL ──
    if (preg_match('/\b(cancel order|cancel my order|i want to cancel|stop my order)\b/i', $ml)) {
        if (!$uid) return reply(
            "👤 Order management requires an account. Please <a href='" . SITE_URL . "/login.php'><strong>login</strong></a> or <a href='" . SITE_URL . "/register.php'><strong>create a free account</strong></a> to cancel or manage your orders.",
            ['🔑 Login', '📝 Register', '🛍️ Browse products', '📞 Contact support']
        );
        if (preg_match('/\b(\d+)\b/', $msg, $m)) return reply(cancelOrder((int)$m[1], $uid, $conn));
        return reply("To cancel an order, type: <em>cancel order [number]</em><br>Find your order number on the <a href='" . SITE_URL . "/orders.php'>My Orders</a> page.", ['View my orders']);
    }

    // ── 6. ORDER HISTORY ──
    if (preg_match('/\b(my orders|order history|past orders|previous orders|all my orders|show orders|all orders)\b/i', $ml)) {
        if (!$uid) return reply(
            "👤 Order history requires an account. Please <a href='" . SITE_URL . "/login.php'><strong>login</strong></a> or <a href='" . SITE_URL . "/register.php'><strong>create a free account</strong></a> to view your order history.",
            ['🔑 Login', '📝 Register', '🛍️ Browse products', '📞 Contact support']
        );
        return reply(orderHistory($uid, $conn), ['Track an order', 'Cancel an order']);
    }

    // ── 6b. INVOICE DOWNLOAD ──
    if (preg_match('/\b(invoice|download invoice|get invoice|print invoice|receipt)\b/i', $ml)) {
        if (!$uid) return reply("🔒 Please <a href='" . SITE_URL . "/login.php'>login</a> to access your invoices.");
        if (preg_match('/#?0*(\d+)\b/', $msg, $m)) {
            $oid = (int)$m[1];
            $stmti2 = $conn->prepare("SELECT id FROM orders WHERE id=? AND user_id=? LIMIT 1");
            $chk = null;
            if ($stmti2) { $stmti2->bind_param("ii", $oid, $uid); $stmti2->execute(); $chk = $stmti2->get_result()->fetch_assoc(); $stmti2->close(); }
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
        $stmtr2 = $conn->prepare("SELECT id FROM orders WHERE user_id=? ORDER BY created_at DESC LIMIT 5");
        $res = false;
        if ($stmtr2) { $stmtr2->bind_param("i", $uid); $stmtr2->execute(); $res = $stmtr2->get_result(); $stmtr2->close(); }
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
            "📦 <strong>Shipping:</strong><br>" .
            "• <strong>FREE shipping</strong> on all orders 🎉<br>" .
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
            "• 🎉 <strong>Free shipping</strong> on all orders!<br>" .
            "• New arrivals added weekly across all categories<br>" .
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
        $rows = dbProductSearch($searchMsg, $conn);
        if (!empty($rows)) {
            $p = $rows[0];
            // Save product interest to context for personalization
            try {
                saveContext($session_id, $uid, 'last_product_interest', $p['name']);
            } catch (Throwable $e) {
                error_log("Warning: Failed to save product interest context: " . $e->getMessage());
            }
            return reply(
                formatProductDetail($p),
                ['🛒 Add: add_to_cart:' . $p['id'], 'Show similar products', 'Check price']
            );
        }
    }

    // ── 15c2. BUDGET QUERY — early rule-based catch ──
    // Catches: "show me phones under 200k", "i want a phone under 200k", "laptops under 500000"
    // Also catches: "any product about 100k only?", "something around 50k", "what do you have for 75k?"
    // Must run BEFORE step 19b ("i want" product search) to avoid false matches
    
    // ── Handler: Vague product queries with budget ──
    // Patterns: "any product about 100k", "something around 50k", "what do you have for 75k?", "got anything for 100k?"
    if (preg_match('/\b(any|some|something|what do you|got|have|recommend|find me)\b.*\b(product|item|stuff|thing|option|choice)\b/i', $ml) && preg_match('/\d/', $ml)) {
        $budget = parseBudgetAmount($msg);
        if ($budget && $budget >= 1000) {
            // Try to detect category
            $catId = detectCategory($ml);
            $catName = null;
            if ($catId) {
                $stmtcn = $conn->prepare("SELECT name FROM categories WHERE id=? LIMIT 1");
                if ($stmtcn) { $stmtcn->bind_param("i", $catId); $stmtcn->execute(); $catRes = $stmtcn->get_result(); $stmtcn->close(); }
                if ($catRes && $row = $catRes->fetch_assoc()) {
                    $catName = $row['name'];
                }
            }
            // Show top products within budget
            $stmtb1 = $conn->prepare("SELECT p.id, p.name, p.brand, p.price, p.stock, p.description, c.name as cat 
                    FROM products p 
                    LEFT JOIN categories c ON p.category_id = c.id 
                    WHERE p.stock > 0 AND p.price <= ?" . ($catId ? " AND p.category_id = ?" : "") . "
                    ORDER BY p.price DESC LIMIT 8");
            $rows = [];
            if ($stmtb1) {
                if ($catId) {
                    $stmtb1->bind_param("ii", $budget, $catId);
                } else {
                    $stmtb1->bind_param("i", $budget);
                }
                $stmtb1->execute();
                $res = $stmtb1->get_result();
                $stmtb1->close();
                if ($res) while ($r = $res->fetch_assoc()) $rows[] = $r;
            }
            
            if (!empty($rows)) {
                $lang = $ctx['language'] ?? detect_language($msg);
                $qual = detectPriceQualifier($ml);
                $priceLabel = buildPriceLabel(null, $budget, $qual);
                $label = $catName 
                    ? "✅ <strong>$catName $priceLabel</strong>"
                    : "✅ <strong>Products $priceLabel</strong>";
                $fp = formatProducts($rows, $label, true, $lang);
                return reply($fp['text'], array_merge($fp['qr'], ['Add to cart', 'Show more', 'Change budget']));
            }
        }
    }
    
    // ── Handler: "looking for X under Y" / "cheapest X" / "deals on X" ──
    // Patterns: "looking for phone under 100k", "cheapest laptop", "any deals on headphones?"
    if ((preg_match('/\b(looking for|searching for|want.*under|find.*under|cheapest|cheapest.*option|best deal on|deals on)\b/i', $ml) || preg_match('/\b(\w+).*under.*(\d+k?)$/i', $ml)) 
        && preg_match('/\d/', $ml)) {
        $budget = parseBudgetAmount($msg);
        $catId = detectCategory($ml);
        
        if ($budget && $budget >= 1000) {
            // Search for products within budget in detected category
            $stmtb2 = $conn->prepare("SELECT p.id, p.name, p.brand, p.price, p.stock, p.description, c.name as cat 
                    FROM products p 
                    LEFT JOIN categories c ON p.category_id = c.id 
                    WHERE p.stock > 0 AND p.price <= ?" . ($catId ? " AND p.category_id = ?" : "") . "
                    ORDER BY p.stock DESC, p.price ASC LIMIT 8");
            $rows = [];
            if ($stmtb2) {
                if ($catId) {
                    $stmtb2->bind_param("ii", $budget, $catId);
                } else {
                    $stmtb2->bind_param("i", $budget);
                }
                $stmtb2->execute();
                $res = $stmtb2->get_result();
                $stmtb2->close();
                if ($res) while ($r = $res->fetch_assoc()) $rows[] = $r;
            }
            
            if (!empty($rows)) {
                $lang = $ctx['language'] ?? detect_language($msg);
                $catName = !empty($rows[0]['cat']) ? $rows[0]['cat'] : null;
                $qual = detectPriceQualifier($ml);
                $priceLabel = buildPriceLabel(null, $budget, $qual);
                $label = ($catName ? "$catName " : "") . $priceLabel;
                $fp = formatProducts($rows, $label, true, $lang);
                return reply($fp['text'], array_merge($fp['qr'], ['Show more', 'Change price', 'Browse all']));
            }
        } elseif ($catId) {
            // No budget specified, just show category
            $stmtb3 = $conn->prepare("SELECT p.id, p.name, p.brand, p.price, p.stock, p.description, c.name as cat 
                    FROM products p 
                    LEFT JOIN categories c ON p.category_id = c.id 
                    WHERE p.stock > 0 AND p.category_id = ?
                    ORDER BY p.stock DESC, p.price ASC LIMIT 8");
            $rows = [];
            if ($stmtb3) {
                $stmtb3->bind_param("i", $catId);
                $stmtb3->execute();
                $res = $stmtb3->get_result();
                $stmtb3->close();
                if ($res) while ($r = $res->fetch_assoc()) $rows[] = $r;
            }
            
            if (!empty($rows)) {
                $lang = $ctx['language'] ?? detect_language($msg);
                $fp = formatProducts($rows, $rows[0]['cat'], true, $lang);
                return reply($fp['text'], array_merge($fp['qr'], ['Show more', 'Change category', 'Browse all']));
            }
        }
    }

    if (preg_match('/\b(recommend|suggest|best|what should i buy|advise|good option|best option)\b/i', $ml) && preg_match('/\d/', $ml)) {
        $budget = parseBudgetAmount($msg);
        if ($budget && $budget >= 1000) {
            return recommendProductsForBudget($budget, detectCategory($ml), $conn, $ctx['language'] ?? 'en');
        }
    }

    if (preg_match('/\b(under|below|less than|within|cheaper than|up to|max|maximum|about|around|approximately|approx)\b/i', $ml) && preg_match('/\d/', $ml)) {
        $budget = parseBudgetAmount($msg);
        if ($budget && $budget >= 1000) {
            $catKeywords = [
                'phone' => 'Smartphones', 'smartphone' => 'Smartphones', 'mobile' => 'Smartphones',
                'tablet' => 'Smartphones', 'iphone' => 'Smartphones',
                'laptop' => 'Laptops', 'computer' => 'Laptops', 'pc' => 'Laptops', 'macbook' => 'Laptops',
                'tv' => 'TV', 'television' => 'TV', 'speaker' => 'TV', 'headphone' => 'TV', 'audio' => 'TV',
                'fridge' => 'Appliances', 'washing' => 'Appliances', 'microwave' => 'Appliances', 'appliance' => 'Appliances',
                'shirt' => 'Fashion', 'dress' => 'Fashion', 'shoes' => 'Fashion', 'fashion' => 'Fashion', 'clothes' => 'Fashion',
                'food' => 'Groceries', 'groceries' => 'Groceries', 'grocery' => 'Groceries',
                'health' => 'Health', 'beauty' => 'Health', 'skincare' => 'Health',
                'sport' => 'Sports', 'gym' => 'Sports', 'fitness' => 'Sports',
                'baby' => 'Baby', 'kids' => 'Baby', 'toy' => 'Baby',
                'furniture' => 'Furniture', 'sofa' => 'Furniture', 'bed' => 'Furniture', 'chair' => 'Furniture',
                'book' => 'Books', 'stationery' => 'Books',
                'car' => 'Car', 'watch' => 'Jewelry', 'jewelry' => 'Jewelry', 'ring' => 'Jewelry',
                'game' => 'Gaming', 'gaming' => 'Gaming', 'console' => 'Gaming',
            ];
            $detectedCat = null;
            foreach ($catKeywords as $kw => $cat) {
                if (stripos($ml, $kw) !== false) { $detectedCat = $cat; break; }
            }
            $qualifier = detectPriceQualifier($ml);
            $result = handleBudgetQuery($budget, $detectedCat, $conn, null, $qualifier);
            return reply($result['response'], $result['quick_replies']);
        }
    }

    // ── 15c3. SHOW ME PRODUCTS / BROWSE CATEGORIES — direct listing ──
    if (preg_match('/\b(show me products|show products|all products|browse products|see products|view products|list products|display products)\b/i', $ml)) {
        $stmtsmp = $conn->query("SELECT p.id,p.name,p.brand,p.price,p.stock,p.image,c.name as cat_name FROM products p LEFT JOIN categories c ON p.category_id=c.id WHERE p.stock>0 ORDER BY RAND() LIMIT 8");
        $rows = [];
        if ($stmtsmp) while ($r = $stmtsmp->fetch_assoc()) $rows[] = $r;
        if (!empty($rows)) {
            $ctx['last_products'] = $rows;
            $fp = formatProducts($rows, 'Featured Products');
            return reply($fp['text'], array_merge($fp['qr'], ['Show me phones', 'Show me laptops', 'Show me fashion']));
        }
    }

    // ── 15c4. BROWSE CATEGORIES / SHOW CATEGORIES ──
    if (preg_match('/\b(browse categories|show categories|show me categories|all categories|list categories|what categories|view categories|see categories)\b/i', $ml)) {
        return reply(getCategorySummary($conn), [
            'Show me phones', 'Show me laptops', 'Show me fashion', 'Show me groceries', 'Show me products'
        ]);
    }

    // ── 15d. BUDGET-BASED SEARCH ──
    // "I have 50000 RWF" / "my budget is 200k" / "I want to spend 100k"
    if (preg_match('/\b(i have|my budget|i want to spend|i can spend|i only have|with|budget of|afford|i got|mon budget|je peux payer|je veux depenser|je veux dépenser|j ai|j\'ai|moins de|plus de|mfite|nfite|amafaranga|budget yanjye|nshobora kwishyura|ndi gushaka spending)\b/i', $ml)
        && preg_match('/\d/', $ml)) {
        $lang = $ctx['language'] ?? detect_language($msg);
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

            // If no specific category — show best products per category (2 per category)
            if (!$catId) {
                $stmtbs = $conn->prepare("
                    SELECT p.id, p.name, p.brand, p.price, p.stock, p.description, c.name AS cat, c.id AS cat_id,
                           ROW_NUMBER() OVER (PARTITION BY p.category_id ORDER BY p.price DESC) AS rn
                    FROM products p LEFT JOIN categories c ON p.category_id=c.id
                    WHERE p.stock > 0 AND p.price < ?
                    ORDER BY c.id, p.price DESC
                ");
                $rows = [];
                $catCounts = [];
                if ($stmtbs) {
                    $stmtbs->bind_param("i", $maxP);
                    $stmtbs->execute();
                    $res = $stmtbs->get_result();
                    $stmtbs->close();
                    if ($res) {
                        while ($r = $res->fetch_assoc()) {
                            $cid = $r['cat_id'];
                            if (!isset($catCounts[$cid])) $catCounts[$cid] = 0;
                            if ($catCounts[$cid] < 2) {
                                $rows[] = $r;
                                $catCounts[$cid]++;
                            }
                        }
                    }
                }
                // Limit total to 16
                $rows = array_slice($rows, 0, 16);
            } else {
                $stmtbs2 = $conn->prepare("SELECT p.id,p.name,p.brand,p.price,p.stock,p.description,c.name AS cat
                    FROM products p LEFT JOIN categories c ON p.category_id=c.id
                    WHERE p.stock > 0 AND p.price < ? AND p.category_id = ? ORDER BY p.price DESC LIMIT 8");
                $rows = [];
                if ($stmtbs2) {
                    $stmtbs2->bind_param("ii", $maxP, $catId);
                    $stmtbs2->execute();
                    $res = $stmtbs2->get_result();
                    $stmtbs2->close();
                    if ($res) while ($r = $res->fetch_assoc()) $rows[] = $r;
                }
            }

            if (!empty($rows)) {
                $qual = detectPriceQualifier($ml);
                $label = $catId
                    ? getBudgetLabelText($minP, $maxP, $lang, $rows[0]['cat'] ?? '')
                    : buildPriceLabel($minP, $maxP, $qual);
                $fp = formatProducts($rows, $label, true, $lang);
                return reply($fp['text'], array_merge($fp['qr'], getBudgetQuickReplies($lang)));
            }

            // Nothing found — recommend closest products above budget
            $stmta = $conn->prepare("SELECT p.id,p.name,p.brand,p.price,p.stock,p.description,c.name AS cat
                FROM products p LEFT JOIN categories c ON p.category_id=c.id
                WHERE p.stock > 0 AND p.price > ?" . ($catId ? " AND p.category_id=?" : "") . "
                ORDER BY p.price ASC LIMIT 5");
            $alt = [];
            if ($stmta) {
                if ($catId) {
                    $stmta->bind_param("ii", $maxP, $catId);
                } else {
                    $stmta->bind_param("i", $maxP);
                }
                $stmta->execute();
                $res2 = $stmta->get_result();
                $stmta->close();
                if ($res2) while ($r = $res2->fetch_assoc()) $alt[] = $r;
            }

            if (!empty($alt)) {
                $qual = detectPriceQualifier($ml);
                $neutralPriceLabel = buildPriceLabel(null, $maxP, $qual);
                $fallbackLabel = $lang === 'fr'
                    ? "Aucun produit " . $neutralPriceLabel . " — voici les options les plus proches :"
                    : ($lang === 'rw'
                        ? "Nta bicuruzwa " . $neutralPriceLabel . " — ariko dore ibikwegereye:"
                        : "No products found " . $neutralPriceLabel . " — but here are the closest options:");
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

            $stmtMin = $conn->query("SELECT MIN(price) as m FROM products WHERE stock>0");
            $minAvailable = (int)(($stmtMin ? $stmtMin->fetch_assoc() : ['m' => 0])['m'] ?? 0);
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
        $rows = dbProductSearch($searchMsg, $conn);
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
        $rangeResult = $conn->query("SELECT MIN(price) as mn, MAX(price) as mx FROM products");
        $range = $rangeResult ? $rangeResult->fetch_assoc() : ['mn' => 0, 'mx' => 0];
        return reply("💰 Our prices range from <strong>RWF " . number_format($range['mn']) . "</strong> to <strong>RWF " . number_format($range['mx']) . "</strong>.<br>Tell me the product name for an exact price!", ['Show me products']);
    }

    // ── 17. STOCK CHECK ──
    if (preg_match('/\b(in stock|out of stock|is available|do you have in stock|how many left|stock of|available stock|is there)\b/i', $ml)) {
        $rows = dbProductSearch($searchMsg, $conn);
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
        $stmtun = $conn->prepare("SELECT email, name FROM users WHERE id=? LIMIT 1");
        $u = null;
        if ($stmtun) { $stmtun->bind_param("i", $uid); $stmtun->execute(); $u = $stmtun->get_result()->fetch_assoc(); $stmtun->close(); }
        // Find last out-of-stock product from context
        if (!empty($ctx['last_products'])) {
            $p = $ctx['last_products'][0];
            $pid = (int)$p['id'];
            $stmtsn = $conn->prepare("INSERT IGNORE INTO stock_notifications (product_id, email, name) VALUES (?, ?, ?)");
            if ($stmtsn && $u) { $stmtsn->bind_param("iss", $pid, $u['email'], $u['name']); $stmtsn->execute(); $stmtsn->close(); }
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
        $stmtRec = $conn->prepare("SELECT p.id,p.name,p.brand,p.price,c.name as cat FROM products p LEFT JOIN categories c ON p.category_id=c.id WHERE p.stock>0" . ($catId ? " AND p.category_id=?" : "") . " ORDER BY RAND() LIMIT 5");
        if ($stmtRec) {
            if ($catId) $stmtRec->bind_param("i", $catId);
            $stmtRec->execute();
            $res = $stmtRec->get_result();
            $stmtRec->close();
        } else { $res = false; }
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
        $brand = trim($bm[count($bm)-1]);
        $likeBrand = '%' . $brand . '%';
        $stmtBr = $conn->prepare("SELECT p.id,p.name,p.brand,p.price,p.stock,p.description,c.name AS cat
            FROM products p LEFT JOIN categories c ON p.category_id=c.id
            WHERE p.stock>0 AND p.brand LIKE ? ORDER BY p.price ASC LIMIT 10");
        if ($stmtBr) {
            $stmtBr->bind_param("s", $likeBrand);
            $stmtBr->execute();
            $res = $stmtBr->get_result();
            $stmtBr->close();
        } else { $res = false; }
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
    // IMPORTANT: Skip if message contains budget keywords (handled by step 15c2)
    if (preg_match('/\b(i want|i need|buy|purchase|get me|order)\b/i', $ml)
        && !preg_match('/\b(to buy|to order|to cancel|to track|history|status)\b/i', $ml)
        && !preg_match('/\b(under|below|less than|within|cheaper than|up to|max|budget|rwf|\d+k)\b/i', $ml)) {
        $rows = dbProductSearch($searchMsg, $conn);
        if (!empty($rows)) {
            $p = $rows[0];
            $ctx['last_products'] = $rows;
            if (!$uid) {
                // Show ALL matching products for guest with login prompt
                [$minP, $maxP] = extractPriceRange($ml);
                $catId2 = detectCategory($ml);
                // Check if results actually match what was asked (e.g. "laptops" should show laptops not USB drives)
                $kws2 = extractKeywords($msg);
                $mainKw = $kws2[0] ?? '';
                if ($mainKw && count($rows) > 0) {
                    $filtered = array_filter($rows, fn($r) =>
                        stripos($r['name'], $mainKw) !== false ||
                        stripos($r['brand'] ?? '', $mainKw) !== false ||
                        stripos($r['description'] ?? '', $mainKw) !== false
                    );
                    if (!empty($filtered)) $rows = array_values($filtered);
                }
                if (empty($rows)) {
                    // No exact matches — tell customer and show closest in category
                    $catName = '';
                    $minInCat = 0;
                    if ($catId2) {
                        $stmtcn2 = $conn->prepare("SELECT name FROM categories WHERE id=? LIMIT 1");
                        if ($stmtcn2) { $stmtcn2->bind_param("i", $catId2); $stmtcn2->execute(); $catName = ($stmtcn2->get_result()->fetch_assoc()['name'] ?? ''); $stmtcn2->close(); }
                        $stmtmc2 = $conn->prepare("SELECT MIN(price) as m FROM products WHERE category_id=? AND stock>0");
                        if ($stmtmc2) { $stmtmc2->bind_param("i", $catId2); $stmtmc2->execute(); $minInCat = (int)(($stmtmc2->get_result()->fetch_assoc()['m'] ?? 0)); $stmtmc2->close(); }
                    }
                    $qual = detectPriceQualifier($ml);
                    $neutralPriceLabel = buildPriceLabel(null, $maxP, $qual);
                    $msg2 = "😔 No <strong>" . htmlspecialchars($mainKw) . "</strong> found";
                    if ($maxP) $msg2 .= " " . $neutralPriceLabel;
                    if ($minInCat) $msg2 .= ".<br>Our cheapest " . htmlspecialchars($catName ?: $mainKw) . " starts at <strong>RWF " . number_format($minInCat) . "</strong>.";
                    return reply($msg2, ['Show me products', 'Show me phones', 'Show me laptops']);
                }
                $qual = detectPriceQualifier($ml);
                $label = $maxP ? buildPriceLabel(null, $maxP, $qual) : '';
                $fp = formatProducts($rows, $label, true);
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

        $rows = dbProductSearch($searchMsg, $conn);
        if (!empty($rows)) {
            $ctx['last_products'] = $rows;
            [$minP, $maxP] = extractPriceRange($ml);
            $qual = detectPriceQualifier($ml);
            $label = '';
            if ($minP && $maxP) $label = "Products RWF " . number_format($minP) . " – RWF " . number_format($maxP);
            elseif ($maxP)      $label = "Products " . buildPriceLabel(null, $maxP, $qual);
            elseif ($minP)      $label = "Products " . buildPriceLabel($minP, null, $qual);
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
    // First try the full /chat pipeline (entity extraction + recommendations + Gemini formatting)
    $mlChat = askMLChat($msg, $session_id);
    if ($mlChat && !empty($mlChat['response']) && ($mlChat['confidence'] ?? 0) >= 0.55) {
        $intent     = $mlChat['intent'];
        $confidence = $mlChat['confidence'];

        // Build "Popular Products" section if Flask returned them
        $popularSection = '';
        if (!empty($mlChat['popular_products'])) {
            $popularSection .= "<br><br>🔥 <strong>Popular in this category:</strong><br>";
            foreach (array_slice($mlChat['popular_products'], 0, 3) as $pp) {
                $stock = ($pp['in_stock'] ?? false) ? "✅" : "❌";
                $popularSection .= "• <strong>" . htmlspecialchars($pp['name']) . "</strong>";
                if (!empty($pp['brand'])) $popularSection .= " <em>({$pp['brand']})</em>";
                $popularSection .= " — <strong>" . htmlspecialchars($pp['price_formatted'] ?? '') . "</strong> $stock<br>";
            }
        }

        // Build "Customers Also Bought" section if Flask returned them
        $alsoBoughtSection = '';
        if (!empty($mlChat['customers_also_bought'])) {
            $alsoBoughtSection .= "<br>🛒 <strong>Customers also bought:</strong><br>";
            foreach (array_slice($mlChat['customers_also_bought'], 0, 3) as $ab) {
                $stock = ($ab['in_stock'] ?? false) ? "✅" : "❌";
                $alsoBoughtSection .= "• <strong>" . htmlspecialchars($ab['name']) . "</strong>";
                if (!empty($ab['brand'])) $alsoBoughtSection .= " <em>({$ab['brand']})</em>";
                $alsoBoughtSection .= " — <strong>" . htmlspecialchars($ab['price_formatted'] ?? '') . "</strong> $stock<br>";
            }
        }

        // If Flask returned products and a Gemini-formatted response, use it directly
        // for product/budget/category intents
        $productIntents = ['product_search','budget_search','category_search','brand_search',
                           'product_recommendation','price_inquiry'];
        if (in_array($intent, $productIntents) && !empty($mlChat['products'])) {
            $qr = $mlChat['quick_replies'] ?? ['Show me more', 'Show me categories', 'How to order?'];
            $fullResponse = $mlChat['response'] . $popularSection . $alsoBoughtSection;
            return reply($fullResponse, $qr);
        }
        // For non-product intents, still try the PHP fast-reply first
        $fast = intentMlFastReply($intent, $msg, $uid, $conn, $ctx, $session_id);
        if ($fast !== null) return $fast;
        // Fall back to Gemini-formatted response from Flask
        if (!empty($mlChat['response'])) {
            $qr = $mlChat['quick_replies'] ?? getLocalizedPrimaryReplies($ctx['language'] ?? 'en', $uid);
            return reply($mlChat['response'], $qr);
        }
    }

    // Fallback: use legacy /predict endpoint (intent only, no products/Gemini)
    // Reuse cached result from early spell correction if available
    if ($predictCalled && !empty($cachedPredictResult)) {
        $mlResult = $cachedPredictResult;
    } else {
        $mlResult = askMLModel($msg);
    }
    if ($mlResult) {
        $intent     = $mlResult['intent'];
        $confidence = round($mlResult['confidence'] * 100, 1);
        $model_used = $mlResult['model_used'];
        $searchMsg  = $mlResult['corrected_message'] ?? $msg;
        $fast = intentMlFastReply($intent, $searchMsg, $uid, $conn, $ctx, $session_id);
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

    // ── 22b. CATCH REMAINING PRODUCT QUERIES — Final DB check before Gemini ──
    // This catches residual product queries like "any product about 100k?" that fell through other handlers
    if (preg_match('/\b(product|item|something|anything|what|got|have|find|search|show|see|browse|list)\b/i', $ml) && preg_match('/\d/', $ml)) {
        // Try to extract budget one more time
        $budget = parseBudgetAmount($msg);
        if ($budget && $budget >= 1000) {
            $catId = detectCategory($ml);
            $stmtCat = $conn->prepare("SELECT p.id, p.name, p.brand, p.price, p.stock, p.description, c.name as cat 
                    FROM products p 
                    LEFT JOIN categories c ON p.category_id = c.id 
                    WHERE p.stock > 0 AND p.price <= ?" . ($catId ? " AND p.category_id = ?" : "") . "
                    ORDER BY p.stock DESC, p.price ASC LIMIT 8");
            $rows = [];
            if ($stmtCat) {
                if ($catId) {
                    $stmtCat->bind_param("ii", $budget, $catId);
                } else {
                    $stmtCat->bind_param("i", $budget);
                }
                $stmtCat->execute();
                $res = $stmtCat->get_result();
                $stmtCat->close();
                if ($res) while ($r = $res->fetch_assoc()) $rows[] = $r;
            }
            
            if (!empty($rows)) {
                $lang = $ctx['language'] ?? detect_language($msg);
                $qual = detectPriceQualifier($ml);
                $priceLabel = buildPriceLabel(null, $budget, $qual);
                $label = "✅ <strong>Products $priceLabel</strong>";
                $fp = formatProducts($rows, $label, true, $lang);
                return reply($fp['text'], array_merge($fp['qr'], ['Show more', 'Different price', 'Show all']));
            }
        }
    }

    // ── 22c. GOOGLE GEMINI — handles complex, multilingual and unmatched queries
    // Gemini is called for: Kinyarwanda, French, complex English, or anything PHP+ML couldn't answer
    if (shouldInvokeGeminiLastResort($msg, $mlResult ?? null)) {
        $gemini = askGemini($msg, $uid, $conn, $session_id);
        if ($gemini) {
            return reply(
                $gemini,
                ['Show me products', 'Track my order', 'Delivery info', 'Contact support']
            );
        }
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

    $address = trim($ctx['order_data']['address'] ?? '');
    $payment = trim($ctx['order_data']['payment'] ?? 'cod');
    $total   = array_sum(array_map(fn($i) => (float)$i['price'] * (int)$i['qty'], $cart));

    if (empty($address)) {
        $ctx['order_step'] = 'address';
        return reply("📍 Please provide your delivery address first.");
    }

    // Final stock validation
    foreach ($cart as $item) {
        $stmtsv = $conn->prepare("SELECT stock, name FROM products WHERE id=? LIMIT 1");
        $row = null;
        if ($stmtsv) { $stmtsv->bind_param("i", $item['id']); $stmtsv->execute(); $row = $stmtsv->get_result()->fetch_assoc(); $stmtsv->close(); }
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
    try {
        $conn->begin_transaction();
        $stmtoi = $conn->prepare("INSERT INTO orders (user_id, total_price, address, payment_method, status)
                  VALUES (?, ?, ?, ?, 'pending')");
        if ($stmtoi) {
            $stmtoi->bind_param("idss", $uid, $total, $address, $payment);
            $stmtoi->execute();
            $stmtoi->close();
        }
        $order_id = (int)$conn->insert_id;

        if (!$order_id) {
            error_log("placeChatOrder INSERT failed: " . $conn->error . " | uid=$uid total=$total");
            $conn->rollback();
            return reply(
                "❌ Could not save your order. Please try again or use the <a href='" . SITE_URL . "/checkout.php'>checkout page</a>.",
                ['Try again', 'Contact support']
            );
        }

    // ── INSERT ORDER ITEMS + DEDUCT STOCK ──
    $stmtoii = $conn->prepare("INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)");
    foreach ($cart as $item) {
        $pid   = (int)$item['id'];
        $qty   = (int)$item['qty'];
        $price = (float)$item['price'];
        if ($stmtoii) {
            $stmtoii->bind_param("iiid", $order_id, $pid, $qty, $price);
            if (!$stmtoii->execute()) {
                throw new Exception("Order item insert failed for product $pid: " . $conn->error);
            }
        } else {
            throw new Exception("Order item prepare failed: " . $conn->error);
        }
        if (!applyPurchasedInventory($conn, $pid, $qty)) {
            error_log("Inventory retirement failed for product $pid order $order_id");
        }
    }

        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();
        error_log("placeChatOrder failed: " . $e->getMessage());
        return reply(
            "Could not save your order because one or more products may have just been bought. Please review your cart or use the <a href='" . SITE_URL . "/checkout.php'>checkout page</a>.",
            ['View cart', 'Clear cart', 'Contact support']
        );
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
        $stmtue = $conn->prepare("SELECT name, email FROM users WHERE id=? LIMIT 1");
        $user = null;
        if ($stmtue) { $stmtue->bind_param("i", $uid); $stmtue->execute(); $user = $stmtue->get_result()->fetch_assoc(); $stmtue->close(); }
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
    $stmto = $conn->prepare("SELECT o.*, GROUP_CONCAT(p.name SEPARATOR ', ') as items
        FROM orders o LEFT JOIN order_items oi ON o.id=oi.order_id
        LEFT JOIN products p ON oi.product_id=p.id
        WHERE o.id=? AND o.user_id=? GROUP BY o.id");
    $o = null;
    if ($stmto) { $stmto->bind_param("ii", $oid, $uid); $stmto->execute(); $o = $stmto->get_result()->fetch_assoc(); $stmto->close(); }
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
    $stmtco = $conn->prepare("SELECT id,status FROM orders WHERE id=? AND user_id=?");
    $o = null;
    if ($stmtco) { $stmtco->bind_param("ii", $oid, $uid); $stmtco->execute(); $o = $stmtco->get_result()->fetch_assoc(); $stmtco->close(); }
    if (!$o) return "❌ Order #$oid not found under your account.";
    if (in_array($o['status'], ['shipped','delivered']))
        return "⚠️ Order #$oid cannot be cancelled — it has already been <strong>" . $o['status'] . "</strong>.<br>Contact <a href='mailto:" . ADMIN_EMAIL . "'>" . ADMIN_EMAIL . "</a> | " . ADMIN_PHONE . " for a return/refund.";
    if ($o['status'] === 'cancelled') return "Order #$oid is already cancelled.";
    $stmtcu = $conn->prepare("UPDATE orders SET status='cancelled' WHERE id=? AND user_id=?");
    if ($stmtcu) { $stmtcu->bind_param("ii", $oid, $uid); $stmtcu->execute(); $stmtcu->close(); }
    return "✅ Order #$oid has been <strong>cancelled</strong> successfully.<br>Refunds processed within 3–5 business days.";
}

function orderHistory(int $uid, $conn): string {
    $stmtoh = $conn->prepare("SELECT id,status,total_price,created_at FROM orders WHERE user_id=? ORDER BY created_at DESC LIMIT 5");
    $orders = false;
    if ($stmtoh) { $stmtoh->bind_param("i", $uid); $stmtoh->execute(); $orders = $stmtoh->get_result(); $stmtoh->close(); }
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
    $stmtfn = $conn->prepare("SELECT name FROM users WHERE id=? LIMIT 1");
    $r = null;
    if ($stmtfn) { $stmtfn->bind_param("i", $uid); $stmtfn->execute(); $r = $stmtfn->get_result()->fetch_assoc(); $stmtfn->close(); }
    return $r ? explode(' ', $r['name'])[0] : 'there';
}

// ================================================================
// ML API — Python Flask intent classifier
// Called before Gemini for fast local intent detection
// ================================================================
function askMLModel(string $message): ?array {
    $url = ML_API_BASE . '/predict/ensemble';
    $ch  = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode(['message' => $message]),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT_MS     => 1200,
        CURLOPT_CONNECTTIMEOUT_MS => 400,
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code !== 200 || !$resp) return null;
    $data = json_decode($resp, true);
    if (!isset($data['intent'], $data['confidence'])) return null;

    // Only trust ML model if confidence is high enough
    if ($data['confidence'] < 0.40) return null;

    return [
        'intent'            => $data['intent'],
        'confidence'        => $data['confidence'],
        'model_used'        => $data['model_used'] ?? 'ML Model',
        'corrected_message' => $data['corrected_message'] ?? null,
    ];
}

/**
 * Call the full Flask /chat pipeline endpoint.
 * Returns structured response with intent, entities, products, and Gemini-formatted reply.
 * Falls back to null if Flask is unavailable.
 */
function askMLChat(string $message, string $sessionId): ?array {
    $url = ML_API_BASE . '/chat';
    $ch  = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode([
            'message'    => $message,
            'session_id' => $sessionId,
        ]),
        CURLOPT_HTTPHEADER        => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT_MS        => 3000,
        CURLOPT_CONNECTTIMEOUT_MS => 400,
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code !== 200 || !$resp) return null;
    $data = json_decode($resp, true);
    if (!isset($data['intent'], $data['response'])) return null;
    return $data;
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
        $stmtgu = $conn->prepare("SELECT name FROM users WHERE id=?");
        $u = null; if ($stmtgu) { $stmtgu->bind_param("i", $uid); $stmtgu->execute(); $u = $stmtgu->get_result()->fetch_assoc(); $stmtgu->close(); }
        $stmto1 = $conn->prepare("SELECT COUNT(*) as c FROM orders WHERE user_id=?");
        $oc = null; if ($stmto1) { $stmto1->bind_param("i", $uid); $stmto1->execute(); $oc = $stmto1->get_result()->fetch_assoc(); $stmto1->close(); }
        $stmto2 = $conn->prepare("SELECT o.id, o.status, o.total_price FROM orders o WHERE o.user_id=? ORDER BY o.created_at DESC LIMIT 3");
        $lo = false; if ($stmto2) { $stmto2->bind_param("i", $uid); $stmto2->execute(); $lo = $stmto2->get_result(); $stmto2->close(); }
        $userCtx = "\nCUSTOMER: {$u['name']} | Total orders: {$oc['c']}";
        if ($lo) {
            $userCtx .= "\nRECENT ORDERS:";
            while ($o = $lo->fetch_assoc())
                $userCtx .= "\n- Order #{$o['id']} | Status: {$o['status']} | RWF " . number_format($o['total_price']);
        }
    }

    // ── 6. Conversation history (last 12 turns) ──
    $stmth = $conn->prepare("SELECT message,response FROM chatbot_logs WHERE session_id=? ORDER BY created_at DESC LIMIT 12");
    $hist = false;
    if ($stmth) { $stmth->bind_param("s", $session_id); $stmth->execute(); $hist = $stmth->get_result(); $stmth->close(); }
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
        . "- Free shipping on all orders\n"
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
            CURLOPT_TIMEOUT_MS     => 4500,
            CURLOPT_CONNECTTIMEOUT_MS => 1000,
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
