<?php
/**
 * Universal Language Detection for Chatbot
 * Detects any language using pattern matching
 * Supports 20+ languages
 */

// Language patterns for detection
$LANGUAGE_PATTERNS = [
    'ar' => [  // Arabic
        'words' => ['السلام', 'مرحبا', 'شكرا', 'من', 'ما', 'أين', 'كم'],
        'script' => '/[\u0600-\u06FF]/u'
    ],
    'zh' => [  // Chinese
        'words' => ['你好', '谢谢', '多少', '什么', '哪里', '怎样'],
        'script' => '/[\u4E00-\u9FFF]/u'
    ],
    'ja' => [  // Japanese
        'words' => ['こんにちは', 'ありがとう', 'いくら', '何', 'どこ'],
        'script' => '/[\u3040-\u309F\u30A0-\u30FF]/u'
    ],
    'ko' => [  // Korean
        'words' => ['안녕하세요', '감사합니다', '얼마', '무엇', '어디'],
        'script' => '/[\uAC00-\uD7AF]/u'
    ],
    'ru' => [  // Russian
        'words' => ['привет', 'спасибо', 'сколько', 'что', 'где', 'как'],
        'script' => '/[\u0400-\u04FF]/u'
    ],
    'es' => [  // Spanish
        'words' => ['hola', 'gracias', 'cuánto', 'qué', 'dónde', 'cómo'],
        'script' => '/[a-záéíóúñ]/u'
    ],
    'pt' => [  // Portuguese
        'words' => ['olá', 'obrigado', 'quanto', 'o que', 'onde', 'como'],
        'script' => '/[a-záéíóúãõç]/u'
    ],
    'de' => [  // German
        'words' => ['hallo', 'danke', 'wie viel', 'was', 'wo', 'wie'],
        'script' => '/[a-zäöüß]/u'
    ],
    'it' => [  // Italian
        'words' => ['ciao', 'grazie', 'quanto', 'cosa', 'dove', 'come'],
        'script' => '/[a-zàèéìòù]/u'
    ],
    'nl' => [  // Dutch
        'words' => ['hallo', 'dank', 'hoeveel', 'wat', 'waar', 'hoe'],
        'script' => '/[a-z]/u'
    ],
    'pl' => [  // Polish
        'words' => ['cześć', 'dziękuję', 'ile', 'co', 'gdzie', 'jak'],
        'script' => '/[a-ząćęłńóśźż]/u'
    ],
    'tr' => [  // Turkish
        'words' => ['merhaba', 'teşekkür', 'kaç', 'ne', 'nerede', 'nasıl'],
        'script' => '/[a-zçğıöşü]/u'
    ],
    'vi' => [  // Vietnamese
        'words' => ['xin chào', 'cảm ơn', 'bao nhiêu', 'cái gì', 'ở đâu'],
        'script' => '/[a-zàáảãạăằắẳẵặâầấẩẫậèéẻẽẹêềếểễệìíỉĩịòóỏõọôồốổỗộơờớởỡợùúủũụưừứửữựỳýỷỹỵđ]/u'
    ],
    'th' => [  // Thai
        'words' => ['สวัสดี', 'ขอบคุณ', 'เท่าไหร่', 'อะไร', 'ที่ไหน'],
        'script' => '/[\u0E00-\u0E7F]/u'
    ],
    'hi' => [  // Hindi
        'words' => ['नमस्ते', 'धन्यवाद', 'कितना', 'क्या', 'कहाँ'],
        'script' => '/[\u0900-\u097F]/u'
    ],
    'sw' => [  // Swahili
        'words' => ['habari', 'asante', 'ngapi', 'nini', 'wapi', 'vipi'],
        'script' => '/[a-z]/u'
    ],
    'am' => [  // Amharic
        'words' => ['ሰላም', 'ምስጋና', 'ስንት', 'ምን', 'የት'],
        'script' => '/[\u1200-\u137F]/u'
    ],
    'rw' => [  // Kinyarwanda
        'words' => ['mwaramutse', 'mwiriwe', 'muraho', 'murakoze', 'zingahe'],
        'script' => '/[a-z]/u'
    ],
    'fr' => [  // French
        'words' => ['bonjour', 'merci', 'combien', 'quoi', 'où', 'comment'],
        'script' => '/[a-zàâäæçéèêëïîôöœùûüœ]/u'
    ],
    'en' => [  // English
        'words' => ['hello', 'thank', 'how', 'what', 'where', 'why'],
        'script' => '/[a-z]/u'
    ]
];

function detect_language($text) {
    global $LANGUAGE_PATTERNS;
    
    if (empty(trim($text))) {
        return 'en';
    }
    
    $text_lower = strtolower(trim($text));
    
    // Check for script patterns first (most reliable)
    foreach ($LANGUAGE_PATTERNS as $lang_code => $patterns) {
        if (isset($patterns['script'])) {
            if (preg_match($patterns['script'], $text_lower)) {
                return $lang_code;
            }
        }
    }
    
    // Check for word matches
    preg_match_all('/\b\w+\b/u', $text_lower, $matches);
    $words = $matches[0];
    
    if (empty($words)) {
        return 'en';
    }
    
    $lang_scores = [];
    foreach ($LANGUAGE_PATTERNS as $lang_code => $patterns) {
        if (isset($patterns['words'])) {
            $matches_count = 0;
            foreach ($words as $word) {
                if (in_array($word, $patterns['words'])) {
                    $matches_count++;
                }
            }
            if ($matches_count > 0) {
                $lang_scores[$lang_code] = $matches_count;
            }
        }
    }
    
    if (!empty($lang_scores)) {
        return array_key_first(array_slice($lang_scores, 0, 1, true));
    }
    
    return 'en';
}

function get_language_name($lang_code) {
    $language_names = [
        'en' => 'English',
        'fr' => 'French',
        'rw' => 'Kinyarwanda',
        'es' => 'Spanish',
        'pt' => 'Portuguese',
        'de' => 'German',
        'it' => 'Italian',
        'nl' => 'Dutch',
        'pl' => 'Polish',
        'tr' => 'Turkish',
        'ru' => 'Russian',
        'ar' => 'Arabic',
        'zh' => 'Chinese',
        'ja' => 'Japanese',
        'ko' => 'Korean',
        'vi' => 'Vietnamese',
        'th' => 'Thai',
        'hi' => 'Hindi',
        'sw' => 'Swahili',
        'am' => 'Amharic',
    ];
    
    return $language_names[$lang_code] ?? 'Unknown';
}

// Generic multilingual response templates
$GENERIC_RESPONSES = [
    'greeting' => [
        'en' => "Hello! Welcome to our store. How can I help you today?",
        'fr' => "Bonjour! Bienvenue dans notre magasin. Comment puis-je vous aider?",
        'rw' => "Muraho! Murakaza neza kuri ububiko bwacu. Ndi iki nkwifuza?",
        'es' => "¡Hola! Bienvenido a nuestra tienda. ¿Cómo puedo ayudarte?",
        'pt' => "Olá! Bem-vindo à nossa loja. Como posso ajudá-lo?",
        'de' => "Hallo! Willkommen in unserem Geschäft. Wie kann ich dir helfen?",
        'it' => "Ciao! Benvenuto nel nostro negozio. Come posso aiutarti?",
        'ar' => "مرحبا! أهلا وسهلا بك في متجرنا. كيف يمكنني مساعدتك؟",
        'zh' => "你好！欢迎来到我们的商店。我能帮你什么？",
        'ja' => "こんにちは！当店へようこそ。何かお手伝いできることはありますか？",
        'ko' => "안녕하세요! 저희 매장에 오신 것을 환영합니다. 어떻게 도와드릴까요?",
        'ru' => "Привет! Добро пожаловать в наш магазин. Чем я могу вам помочь?",
        'vi' => "Xin chào! Chào mừng bạn đến cửa hàng của chúng tôi. Tôi có thể giúp bạn điều gì?",
        'th' => "สวัสดี! ยินดีต้อนรับสู่ร้านค้าของเรา ฉันจะช่วยคุณได้อย่างไร?",
        'hi' => "नमस्ते! हमारे स्टोर में आपका स्वागत है। मैं आपकी कैसे मदद कर सकता हूँ?",
        'sw' => "Habari! Karibu kwenye duka letu. Ninaweza kukusaidia vipi?",
        'am' => "ሰላም! ወደ ሱቁ ደህና መጡ። እንዴት ሊረዳዎ ይችላል?",
    ],
    'product_search' => [
        'en' => "I can help you find products! What are you looking for?",
        'fr' => "Je peux vous aider à trouver des produits! Que cherchez-vous?",
        'rw' => "Nshobora kubagenzi gushaka ibicuruzwa! Ndi iki bushaka?",
        'es' => "¡Puedo ayudarte a encontrar productos! ¿Qué buscas?",
        'pt' => "Posso ajudá-lo a encontrar produtos! O que você procura?",
        'de' => "Ich kann dir helfen, Produkte zu finden! Was suchst du?",
        'it' => "Posso aiutarti a trovare prodotti! Cosa stai cercando?",
        'ar' => "يمكنني مساعدتك في العثور على المنتجات! ماذا تبحث عن؟",
        'zh' => "我可以帮你找到产品！你在找什么？",
        'ja' => "商品を見つけるのをお手伝いできます！何をお探しですか？",
        'ko' => "제품을 찾는 데 도움을 드릴 수 있습니다! 무엇을 찾고 있습니까?",
        'ru' => "Я могу помочь вам найти продукты! Что вы ищете?",
        'vi' => "Tôi có thể giúp bạn tìm sản phẩm! Bạn đang tìm kiếm cái gì?",
        'th' => "ฉันสามารถช่วยคุณค้นหาผลิตภัณฑ์! คุณกำลังมองหาอะไร?",
        'hi' => "मैं आपको उत्पाद खोजने में मदद कर सकता हूँ! आप क्या ढूंढ रहे हैं?",
        'sw' => "Ninaweza kukusaidia kupata bidhaa! Unatafuta nini?",
        'am' => "ምርቶችን ለማግኘት ሊረዳዎ ይችላል! ምን ይፈልጋሉ?",
    ],
    'thanks' => [
        'en' => "You're welcome! Is there anything else I can help with?",
        'fr' => "De rien! Y a-t-il autre chose que je puisse faire pour vous?",
        'rw' => "Ubwenge! Hari indi nkwifuza?",
        'es' => "¡De nada! ¿Hay algo más en lo que pueda ayudarte?",
        'pt' => "De nada! Há algo mais que eu possa ajudá-lo?",
        'de' => "Gerne! Kann ich dir noch bei etwas anderem helfen?",
        'it' => "Prego! C'è altro in cui posso aiutarti?",
        'ar' => "على الرحب والسعة! هل هناك أي شيء آخر يمكنني مساعدتك به؟",
        'zh' => "不客气！还有其他我可以帮助你的吗？",
        'ja' => "どういたしまして！他にお手伝いできることはありますか？",
        'ko' => "천만에요! 다른 도움이 필요하신 것이 있습니까?",
        'ru' => "Пожалуйста! Есть ли еще что-то, чем я могу вам помочь?",
        'vi' => "Không có gì! Có cái gì khác tôi có thể giúp bạn không?",
        'th' => "ยินดีครับ! มีอย่างอื่นที่ฉันสามารถช่วยได้หรือไม่?",
        'hi' => "स्वागत है! क्या कोई और चीज है जिसमें मैं आपकी मदद कर सकता हूँ?",
        'sw' => "Karibu! Je kuna kitu kingine ambacho ninaweza kukusaidia?",
        'am' => "ደህና መጡ! ሌላ ምንም ሊረዳዎ ይችላል?",
    ],
];

function get_generic_response($intent, $language = 'en') {
    global $GENERIC_RESPONSES;
    
    if (isset($GENERIC_RESPONSES[$intent][$language])) {
        return $GENERIC_RESPONSES[$intent][$language];
    }
    
    // Fallback to English
    if (isset($GENERIC_RESPONSES[$intent]['en'])) {
        return $GENERIC_RESPONSES[$intent]['en'];
    }
    
    return "How can I help you?";
}
