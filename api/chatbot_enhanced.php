<?php
/**
 * Enhanced Chatbot API - Comprehensive E-Commerce Support
 * Features:
 * - Image/document upload handling with recognition
 * - Product matching from all 15 categories
 * - Budget-based recommendations
 * - Multilingual support (EN, FR, RW) with Gemini fallback
 * - Guest ordering guidance
 * - Complete interaction logging
 */

header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', '0');

session_start();

try {
    require_once __DIR__ . '/../config/env.php';
    require_once __DIR__ . '/../config/db.php';
    // Use shared language detector to keep behavior consistent across endpoints
    require_once __DIR__ . '/../includes/chatbot_detect_language.php';
    
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $message = trim($input['message'] ?? '');
    $session_id = $input['session_id'] ?? bin2hex(random_bytes(16));
    $upload_id = $input['upload_id'] ?? null;
    $language = strtolower($input['language'] ?? 'en');
    
    if (empty($message) && !$upload_id) {
        echo json_encode(['response' => 'Please type a message or upload an image.', 'quick_replies' => []]);
        exit;
    }
    
    $_SESSION['chat_session_id'] = $session_id;
    $user_id = $_SESSION['user_id'] ?? null;
    
    // Detect language if not provided
    if (!in_array($language, ['en', 'fr', 'rw'])) {
        $language = detectLanguage($message);
    }
    
    $response = '';
    $quick_replies = [];
    $matched_products = [];
    
    // Handle image upload
    if ($upload_id) {
        $matched_products = processImageUpload($upload_id, $conn);
        if (!empty($matched_products)) {
            $response = formatImageMatchResponse($matched_products, $language);
            $quick_replies = ['View details', 'Add to cart', 'See more products', 'Continue shopping'];
        } else {
            $response = getLocalizedText('image_not_found', $language);
            $quick_replies = ['Search products', 'Browse categories', 'Contact support'];
        }
    }
    
    // Process text message
    if (!empty($message)) {
        // Call Flask ML API
        $ml_result = callFlaskML($message);
        $intent = $ml_result['intent'] ?? 'unknown';
        $confidence = $ml_result['confidence'] ?? 0;
        
        // Route to appropriate handler
        $handler_response = handleIntent($intent, $message, $user_id, $conn, $language);
        
        if (empty($response)) {
            $response = $handler_response['response'];
            $quick_replies = $handler_response['quick_replies'];
        }
    }
    
    // Log interaction
    $log_id = logInteraction($conn, $user_id, $session_id, $message, $response, $language);
    
    echo json_encode([
        'response' => $response,
        'quick_replies' => $quick_replies,
        'session_id' => $session_id,
        'log_id' => $log_id,
        'language' => $language,
        'matched_products' => $matched_products
    ]);
    
} catch (Throwable $e) {
    error_log("ENHANCED CHATBOT ERROR: " . $e->getMessage());
    echo json_encode([
        'response' => 'An error occurred. Please try again.',
        'quick_replies' => ['Show products', 'Contact support']
    ]);
}
exit;

// ============================================================
// HELPER FUNCTIONS
// ============================================================

// Note: language detection is provided by includes/chatbot_detect_language.php

function callFlaskML($message) {
    $url = ML_API_BASE . '/predict/ensemble';
    $payload = json_encode(['message' => $message, 'model' => 'best']);

    $attempts = 2;
    for ($i = 0; $i < $attempts; $i++) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_CONNECTTIMEOUT => 2,
            CURLOPT_TIMEOUT => 6,
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($response !== false && $httpCode >= 200 && $httpCode < 300) {
            $decoded = json_decode($response, true);
            if (is_array($decoded) && isset($decoded['intent'])) return $decoded;
            return ['intent' => 'unknown', 'confidence' => 0];
        }

        error_log("callFlaskML attempt {$i} failed: HTTP={$httpCode} err={$err}");
        // brief backoff before retry
        sleep($i + 1);
    }

    return ['intent' => 'unknown', 'confidence' => 0];
}

function processImageUpload($upload_id, $conn) {
    $stmt = $conn->prepare("SELECT file_path FROM chat_uploads WHERE id = ?");
    $stmt->bind_param('i', $upload_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) return [];
    
    $upload = $result->fetch_assoc();
    $image_path = $_SERVER['DOCUMENT_ROOT'] . $upload['file_path'];
    
    if (!file_exists($image_path)) return [];
    
    // Use Gemini Vision API for image recognition
    $image_analysis = analyzeImageWithGemini($image_path);
    
    if (!$image_analysis) return [];
    
    // Match products based on image analysis
    return matchProductsFromAnalysis($image_analysis, $conn);
}

function analyzeImageWithGemini($image_path) {
    $api_key = GEMINI_API_KEY;
    if (empty($api_key)) return null;
    
    $image_data = base64_encode(file_get_contents($image_path));
    $mime_type = mime_content_type($image_path);
    
    $payload = [
        'contents' => [
            [
                'parts' => [
                    ['text' => 'Analyze this product image. Describe: 1) Product type/category 2) Brand if visible 3) Color/style 4) Estimated price range. Be concise.'],
                    ['inlineData' => ['mimeType' => $mime_type, 'data' => $image_data]]
                ]
            ]
        ]
    ];
    
    $ch = curl_init("https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'X-Goog-Api-Key: ' . $api_key],
        CURLOPT_TIMEOUT => 10,
    ]);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    $data = json_decode($response, true);
    return $data['candidates'][0]['content']['parts'][0]['text'] ?? null;
}

function matchProductsFromAnalysis($analysis, $conn) {
    // Extract keywords from analysis
    $keywords = extractKeywordsFromAnalysis($analysis);
    
    $matched = [];
    foreach ($keywords as $keyword) {
        $stmt = $conn->prepare("
            SELECT id, name, price, brand, category_id, stock, image 
            FROM products 
            WHERE (name LIKE ? OR brand LIKE ? OR description LIKE ?) 
            AND stock > 0 
            LIMIT 5
        ");
        $search = "%$keyword%";
        $stmt->bind_param('sss', $search, $search, $search);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $matched[$row['id']] = $row;
        }
    }
    
    return array_slice($matched, 0, 8);
}

function extractKeywordsFromAnalysis($analysis) {
    $keywords = [];
    $text = strtolower($analysis ?? '');

    // Normalize punctuation and split into tokens
    $tokens = preg_split('/[^a-z0-9\p{L}]+/u', $text) ?: [];

    $stop = [
        'the','this','that','these','those','and','or','for','with','from','have','has','had',
        'product','products','item','items','model','type','color','colour','size','price','estimated',
            'range','brand','unknown', 'i', 'we', 'you'
    ];

    foreach ($tokens as $t) {
        $t = trim($t);
        if ($t === '' || strlen($t) < 3) continue;
        if (preg_match('/\d+$/', $t)) continue; // skip pure numbers
        if (in_array($t, $stop)) continue;
        $keywords[] = $t;
    }

    // return top unique keywords (preserve order)
    $unique = array_values(array_unique($keywords));
    return array_slice($unique, 0, 5);
}

function handleIntent($intent, $message, $user_id, $conn, $language) {
    switch ($intent) {
        case 'product_search':
        case 'recommendation':
            return handleProductSearch($message, $conn, $language);
            
        case 'budget_search':
            return handleBudgetSearch($message, $conn, $language);
            
        case 'category_search':
            return handleCategorySearch($message, $conn, $language);
            
        case 'product_price':
            return handlePriceQuery($message, $conn, $language);
            
        case 'place_order':
        case 'guest_order_guide':
            return handleOrderGuide($user_id, $language);
            
        case 'order_track':
            return handleOrderTracking($user_id, $language);
            
        case 'delivery_time':
        case 'shipping_fee':
            return handleDeliveryInfo($language);
            
        case 'payment_methods':
            return handlePaymentMethods($language);
            
        case 'return_policy':
            return handleReturnPolicy($language);
            
        case 'contact_support':
        case 'support_ticket':
            return handleSupportContact($language);
            
        case 'greeting':
        case 'bot_identity':
        case 'professional_greeting':
            return handleGreeting($user_id, $language);
            
        default:
            return handleDefault($language);
    }
}

function handleProductSearch($message, $conn, $language) {
    $stmt = $conn->prepare("
        SELECT id, name, price, brand, category_id, stock, image, description
        FROM products 
        WHERE (name LIKE ? OR description LIKE ? OR brand LIKE ?) 
        AND stock > 0 
        LIMIT 8
    ");
    $search = '%' . $conn->real_escape_string($message) . '%';
    $stmt->bind_param('sss', $search, $search, $search);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $products = [];
    while ($row = $result->fetch_assoc()) {
        $products[] = $row;
    }
    
    if (empty($products)) {
        return [
            'response' => getLocalizedText('no_products_found', $language),
            'quick_replies' => ['Browse categories', 'Set budget', 'Contact support']
        ];
    }
    
    $response = formatProductsResponse($products, $language);
    return [
        'response' => $response,
        'quick_replies' => ['View details', 'Filter by price', 'See more', 'Continue shopping']
    ];
}

function handleBudgetSearch($message, $conn, $language) {
    $text = strtolower(trim($message));

    $normalize = function($s) {
        $s = strtolower(trim($s));
        $k = false;
        if (strpos($s, 'k') !== false) {
            $k = true;
            $s = str_replace('k', '', $s);
        }
        $s = str_replace(',', '', $s);
        preg_match('/(\d+)/', $s, $m);
        if (!isset($m[1])) return null;
        $val = (int)$m[1];
        if ($k) return $val * 1000;
        return $val >= 1000 ? $val : $val * 1000;
    };

    $min_price = null;
    $max_price = null;

    // between X and Y
    if (preg_match('/between\s+(\d[\d,]*\s*k?)\s*(?:and|\-|to)\s*(\d[\d,]*\s*k?)/i', $text, $m)) {
        $min_price = $normalize($m[1]);
        $max_price = $normalize($m[2]);
    }
    // under / below X
    elseif (preg_match('/(?:under|below|less than|<)\s*(\d[\d,]*\s*k?)/i', $text, $m)) {
        $min_price = 0;
        $max_price = $normalize($m[1]);
    }
    // over / above X
    elseif (preg_match('/(?:over|above|more than|>)\s*(\d[\d,]*\s*k?)/i', $text, $m)) {
        $min_price = $normalize($m[1]);
        $max_price = (int)($min_price * 2);
    }
    // single value like '50k' or '50,000'
    elseif (preg_match('/(\d[\d,]*\s*k?)/i', $text, $m)) {
        $val = $normalize($m[1]);
        $min_price = (int)max(0, floor($val * 0.5));
        $max_price = (int)($val * 2);
    }

    if ($min_price === null || $max_price === null) {
        // fallback to a sensible default (show affordable range)
        $min_price = 0;
        $max_price = 5000000; // 5M RWF default upper bound
    }

    // Ensure integers
    $min_price = (int)$min_price;
    $max_price = (int)$max_price;

    $stmt = $conn->prepare("\n        SELECT id, name, price, brand, category_id, stock, image\n        FROM products \n        WHERE price BETWEEN ? AND ? \n        AND stock > 0 \n        ORDER BY price ASC \n        LIMIT 10\n    ");
    $stmt->bind_param('ii', $min_price, $max_price);
    $stmt->execute();
    $result = $stmt->get_result();

    $products = [];
    while ($row = $result->fetch_assoc()) {
        $products[] = $row;
    }

    $response = getLocalizedText('budget_results', $language, ['min' => $min_price, 'max' => $max_price]);
    $response .= formatProductsResponse($products, $language);

    return [
        'response' => $response,
        'quick_replies' => ['Adjust budget', 'View details', 'Add to cart', 'Continue shopping']
    ];
}

function handleCategorySearch($message, $conn, $language) {
    $stmt = $conn->prepare("SELECT id, name FROM categories LIMIT 15");
    $stmt->execute();
    $result = $stmt->get_result();
    
    $categories = [];
    while ($row = $result->fetch_assoc()) {
        $categories[] = $row;
    }
    
    $response = getLocalizedText('categories_available', $language) . "\n";
    $quick_replies = [];
    
    foreach ($categories as $cat) {
        $response .= "• " . htmlspecialchars($cat['name']) . "\n";
        $quick_replies[] = $cat['name'];
    }
    
    return [
        'response' => $response,
        'quick_replies' => array_slice($quick_replies, 0, 4)
    ];
}

function handlePriceQuery($message, $conn, $language) {
    $stmt = $conn->prepare("
        SELECT name, price, brand FROM products 
        WHERE (name LIKE ? OR brand LIKE ?) 
        AND stock > 0 
        LIMIT 5
    ");
    $search = '%' . $conn->real_escape_string($message) . '%';
    $stmt->bind_param('ss', $search, $search);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $response = getLocalizedText('price_info', $language) . "\n";
    while ($row = $result->fetch_assoc()) {
        $response .= "• " . htmlspecialchars($row['name']) . " (" . htmlspecialchars($row['brand']) . "): RWF " . number_format($row['price']) . "\n";
    }
    
    return [
        'response' => $response,
        'quick_replies' => ['View details', 'Set budget', 'Add to cart', 'Continue shopping']
    ];
}

function handleOrderGuide($user_id, $language) {
    $response = getLocalizedText('order_guide', $language);
    $quick_replies = ['Browse products', 'View cart', 'Checkout', 'Contact support'];
    
    return ['response' => $response, 'quick_replies' => $quick_replies];
}

function handleOrderTracking($user_id, $language) {
    if (!$user_id) {
        return [
            'response' => getLocalizedText('login_required', $language),
            'quick_replies' => ['Login', 'Register', 'Browse products']
        ];
    }
    
    return [
        'response' => getLocalizedText('order_tracking_info', $language),
        'quick_replies' => ['My orders', 'Browse products', 'Contact support']
    ];
}

function handleDeliveryInfo($language) {
    $response = getLocalizedText('delivery_info', $language);
    return [
        'response' => $response,
        'quick_replies' => ['Payment methods', 'Return policy', 'Browse products']
    ];
}

function handlePaymentMethods($language) {
    $response = getLocalizedText('payment_methods', $language);
    return [
        'response' => $response,
        'quick_replies' => ['Delivery info', 'Return policy', 'Browse products']
    ];
}

function handleReturnPolicy($language) {
    $response = getLocalizedText('return_policy', $language);
    return [
        'response' => $response,
        'quick_replies' => ['Delivery info', 'Payment methods', 'Contact support']
    ];
}

function handleSupportContact($language) {
    $response = getLocalizedText('support_contact', $language);
    return [
        'response' => $response,
        'quick_replies' => ['Browse products', 'Delivery info', 'Return policy']
    ];
}

function handleGreeting($user_id, $language) {
    $greeting = getLocalizedText('greeting', $language);
    if ($user_id) {
        $greeting = str_replace('[NAME]', 'valued customer', $greeting);
    }
    
    return [
        'response' => $greeting,
        'quick_replies' => ['Browse products', 'Set budget', 'Track order', 'Contact support']
    ];
}

function handleDefault($language) {
    return [
        'response' => getLocalizedText('default_response', $language),
        'quick_replies' => ['Browse products', 'Set budget', 'Contact support', 'Help']
    ];
}

function formatProductsResponse($products, $language) {
    $response = getLocalizedText('products_found', $language, ['count' => count($products)]) . "\n\n";
    
    foreach (array_slice($products, 0, 5) as $p) {
        $url = SITE_URL . '/product.php?id=' . (int)$p['id'];
        $response .= "🛍️ <a href='$url'><strong>" . htmlspecialchars($p['name']) . "</strong></a>\n";
        $response .= "   Brand: " . htmlspecialchars($p['brand']) . "\n";
        $response .= "   Price: RWF " . number_format($p['price']) . "\n";
        $response .= "   Stock: " . $p['stock'] . " available\n\n";
    }
    
    return $response;
}

function formatImageMatchResponse($products, $language) {
    $response = getLocalizedText('image_matched', $language) . "\n\n";
    
    foreach (array_slice($products, 0, 5) as $p) {
        $url = SITE_URL . '/product.php?id=' . (int)$p['id'];
        $response .= "✓ <a href='$url'>" . htmlspecialchars($p['name']) . "</a> - RWF " . number_format($p['price']) . "\n";
    }
    
    return $response;
}

function getLocalizedText($key, $language = 'en', $params = []) {
    $texts = [
        'en' => [
            'image_not_found' => 'Sorry, I couldn\'t find a matching product for that image. Try uploading a clearer photo or search by name.',
            'no_products_found' => 'No products found matching your search. Try different keywords or browse our categories.',
            'budget_results' => 'Found products in your budget range (RWF {min} - {max}):',
            'categories_available' => '📂 Browse our 15 product categories:',
            'price_info' => '💰 Price Information:',
            'order_guide' => '📋 How to Order:\n1. Browse products\n2. Add items to cart\n3. Proceed to checkout\n4. Choose payment method\n5. Confirm delivery address\n6. Place order\n\nNeed help? Contact our support team.',
            'login_required' => '🔒 Please login to track your orders.',
            'order_tracking_info' => '📦 To track your order, visit your My Orders page or provide your order number.',
            'delivery_info' => '🚚 Delivery Information:\n• Kigali: 1-2 business days\n• Other provinces: 2-4 business days\n• Free shipping on all orders 🎉',
            'payment_methods' => '💳 Payment Methods:\n• Cash on Delivery (COD)\n• MTN Mobile Money\n• Airtel Money\n• Bank Transfer\n• Visa/Mastercard',
            'return_policy' => '↩️ Return & Refund Policy:\n• 7 days to return after delivery\n• Item must be unused and in original packaging\n• Refunds processed within 3-5 business days',
            'support_contact' => '📞 Contact Support:\n• Email: ' . ADMIN_EMAIL . '\n• Phone: ' . ADMIN_PHONE . '\n• Hours: Mon-Sat, 8AM-6PM (Kigali time)',
            'greeting' => '👋 Welcome to ShopAI Rwanda! I\'m here to help you find the perfect products. What are you looking for today?',
            'default_response' => '😊 I\'m not sure I understood that. How can I help you? Browse products, check prices, track orders, or contact support.',
            'products_found' => 'Found {count} products for you:',
            'image_matched' => '✅ I found these matching products:'
        ],
        'fr' => [
            'image_not_found' => 'Désolé, je n\'ai pas trouvé de produit correspondant à cette image. Essayez de télécharger une photo plus claire ou recherchez par nom.',
            'no_products_found' => 'Aucun produit trouvé correspondant à votre recherche. Essayez d\'autres mots-clés ou parcourez nos catégories.',
            'budget_results' => 'Produits trouvés dans votre gamme de budget (RWF {min} - {max}):',
            'categories_available' => '📂 Parcourez nos 15 catégories de produits:',
            'price_info' => '💰 Informations sur les prix:',
            'order_guide' => '📋 Comment commander:\n1. Parcourez les produits\n2. Ajoutez des articles au panier\n3. Procédez au paiement\n4. Choisissez le mode de paiement\n5. Confirmez l\'adresse de livraison\n6. Passez la commande',
            'login_required' => '🔒 Veuillez vous connecter pour suivre vos commandes.',
            'order_tracking_info' => '📦 Pour suivre votre commande, visitez votre page Mes commandes ou fournissez votre numéro de commande.',
            'delivery_info' => '🚚 Informations de livraison:\n• Kigali: 1-2 jours ouvrables\n• Autres provinces: 2-4 jours ouvrables\n• Livraison gratuite sur toutes les commandes 🎉',
            'payment_methods' => '💳 Modes de paiement:\n• Paiement à la livraison (COD)\n• MTN Mobile Money\n• Airtel Money\n• Virement bancaire\n• Visa/Mastercard',
            'return_policy' => '↩️ Politique de retour et de remboursement:\n• 7 jours pour retourner après livraison\n• L\'article doit être inutilisé et dans son emballage d\'origine\n• Remboursements traités dans les 3-5 jours ouvrables',
            'support_contact' => '📞 Contacter le support:\n• Email: ' . ADMIN_EMAIL . '\n• Téléphone: ' . ADMIN_PHONE . '\n• Heures: Lun-Sam, 8h-18h (heure de Kigali)',
            'greeting' => '👋 Bienvenue chez ShopAI Rwanda! Je suis ici pour vous aider à trouver les produits parfaits. Que cherchez-vous aujourd\'hui?',
            'default_response' => '😊 Je ne suis pas sûr d\'avoir compris. Comment puis-je vous aider? Parcourez les produits, vérifiez les prix, suivez les commandes ou contactez le support.',
            'products_found' => '{count} produits trouvés pour vous:',
            'image_matched' => '✅ J\'ai trouvé ces produits correspondants:'
        ],
        'rw' => [
            'image_not_found' => 'Mwaramutse, ntabwo nshakiye igicuruzwa kigereranya ishusho. Gerageza gushyiramo ishusho neza cyangwa shakisha kuva izina.',
            'no_products_found' => 'Nta gicuruzwa gishakishijwe. Gerageza amagambo ahinduka cyangwa reba ibicuruzwa byacu.',
            'budget_results' => 'Ibicuruzwa byashakishijwe mu mahoro yayo (RWF {min} - {max}):',
            'categories_available' => '📂 Reba ibicuruzwa byacu 15:',
            'price_info' => '💰 Amakuru y\'agaciro:',
            'order_guide' => '📋 Uburyo bwo gucurura:\n1. Reba ibicuruzwa\n2. Ongeraho ibicuruzwa mu kariti\n3. Komeza ku mahoro\n4. Hitamo uburyo bwo kwishyura\n5. Emeza aderesi y\'ibikazo\n6. Komeza icurura',
            'login_required' => '🔒 Injira kugira ngo urebe icurura cyacu.',
            'order_tracking_info' => '📦 Kugira ngo urebe icurura cyacu, soma icurura cyacu cyangwa tanga numero y\'icurura.',
            'delivery_info' => '🚚 Amakuru y\'ibikazo:\n• Kigali: 1-2 iminsi y\'akazi\n• Ibindi bice: 2-4 iminsi y\'akazi\n• Ibikazo byemereza kuri buri gurwa 🎉',
            'payment_methods' => '💳 Uburyo bwo kwishyura:\n• Kwishyura igihe cy\'ibikazo\n• MTN Mobile Money\n• Airtel Money\n• Kwishyura mu banki\n• Visa/Mastercard',
            'return_policy' => '↩️ Politiki y\'igusugurano:\n• Iminsi 7 yo gusugurana nyuma y\'ibikazo\n• Igicuruzwa cyakwigwa kandi kiri mu nkubiri y\'ibanze\n• Igusugurano rikorwa mu minsi 3-5 y\'akazi',
            'support_contact' => '📞 Kontakta inzira y\'ubufasha:\n• Email: ' . ADMIN_EMAIL . '\n• Telefoni: ' . ADMIN_PHONE . '\n• Igihe: Ku wa mbere-Ku wa gatandatu, 8-18 (Kigali time)',
            'greeting' => '👋 Murakaza neza kuri ShopAI Rwanda! Ndi hano kugira ngo nkubire gushakisha ibicuruzwa byiza. Ubushakisha ki ubu?',
            'default_response' => '😊 Sinzira neza. Nigute nshobora kubafasha? Reba ibicuruzwa, reba agaciro, reba icurura, cyangwa kontakta inzira y\'ubufasha.',
            'products_found' => 'Ibicuruzwa {count} byashakishijwe:',
            'image_matched' => '✅ Nshakiye ibicuruzwa biri:'
        ]
    ];
    
    $text = $texts[$language][$key] ?? $texts['en'][$key] ?? $key;
    
    foreach ($params as $param => $value) {
        $text = str_replace('{' . $param . '}', $value, $text);
    }
    
    return $text;
}

function logInteraction($conn, $user_id, $session_id, $message, $response, $language) {
    $stmt = $conn->prepare("
        INSERT INTO chatbot_logs (user_id, session_id, is_guest, message, response, created_at)
        VALUES (?, ?, ?, ?, ?, NOW())
    ");
    
    $is_guest = $user_id ? 0 : 1;
    $stmt->bind_param('isiss', $user_id, $session_id, $is_guest, $message, $response);
    $stmt->execute();
    
    return $conn->insert_id;
}
?>
