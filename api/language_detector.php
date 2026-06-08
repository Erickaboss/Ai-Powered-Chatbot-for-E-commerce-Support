<?php
/**
 * Language Detection for Chatbot
 * Detects English, Kinyarwanda, and French
 */

// Kinyarwanda common words
$KINYARWANDA_WORDS = [
    'mwaramutse', 'mwiriwe', 'muraho', 'habari', 'amakuru', 'iki', 'nde', 'ute',
    'inyumba', 'umuntu', 'igitabo', 'amafaranga', 'ubwoko', 'ikinini', 'icyuma',
    'murakoze', 'urakoze', 'asante', 'ahubwo', 'yego', 'oya', 'nta', 'niba',
    'ariko', 'kandi', 'cyangwa', 'kubera', 'nkuko', 'uko', 'aho', 'hano',
    'hari', 'nari', 'niba', 'nkuko', 'ubwoko', 'icyiciro', 'inzira', 'umwanya',
    'umunsi', 'igice', 'igipimo', 'igihe', 'igikorwa', 'igitabo', 'igikoni',
    'igikoresho', 'igitambara', 'igitero', 'igitonda', 'igituba', 'igitugu',
    'kugura', 'kuguza', 'kugurisha', 'kugurwa', 'kunywa', 'kurya', 'kuryamwo',
    'gusoma', 'gusomeka', 'kwiyandikisha', 'kwiyandikira', 'gukora', 'gukoresha',
    'gusobanura', 'gusaba', 'gusabye', 'gusabyeho',
];

// French common words
$FRENCH_WORDS = [
    'bonjour', 'bonsoir', 'bonne', 'nuit', 'salut', 'coucou', 'allo', 'allô',
    'merci', 'merci beaucoup', 'merci bien', 'de rien', 'au revoir', 'adieu',
    'oui', 'non', 'peut-être', 'peut', 'peux', 'pouvez', 'pouvez-vous',
    'je', 'tu', 'il', 'elle', 'nous', 'vous', 'ils', 'elles',
    'mon', 'ma', 'mes', 'ton', 'ta', 'tes', 'son', 'sa', 'ses',
    'notre', 'nos', 'votre', 'vos', 'leur', 'leurs',
    'un', 'une', 'des', 'le', 'la', 'les', 'l',
    'et', 'ou', 'mais', 'donc', 'car', 'parce', 'que', 'qui', 'quoi',
    'où', 'quand', 'comment', 'pourquoi', 'combien', 'quel', 'quelle',
    'quels', 'quelles', 'lequel', 'laquelle', 'lesquels', 'lesquelles',
    'ce', 'cet', 'cette', 'ces', 'celui', 'celle', 'ceux', 'celles',
    'ça', 'cela', 'ceci', 'là', 'ici', 'là-bas', 'là-haut', 'là-dedans',
    'veux', 'vouloir', 'voulez', 'voudriez', 'aimerais', 'aimer', 'aimez',
    'besoin', 'cherche', 'chercher', 'cherchez', 'trouve', 'trouver', 'trouvez',
    'prix', 'coût', 'coûte', 'coûter', 'coûtez', 'livraison', 'livrer', 'livrez',
    'commande', 'commander', 'commandez', 'paiement', 'payer', 'payez',
    'retour', 'retourner', 'retournez', 'produit', 'produits', 'article', 'articles',
    'magasin', 'boutique', 'store', 'shop', 'commerce', 'client', 'clients',
    'vendeur', 'vendeurs', 'seller', 'sellers', 'marchand', 'marchands',
    'combien', 'combien ça coûte', 'quel est le prix', 'quel prix',
    'frais', 'frais de port', 'frais de livraison', 'gratuit', 'gratuite',
    'gratuits', 'gratuites', 'free', 'sans frais', 'jour', 'jours',
    'jour ouvrable', 'jours ouvrables', 'semaine', 'semaines', 'mois', 'année',
    'rapide', 'rapides', 'lent', 'lents', 'cher', 'chère', 'chers', 'chères',
    'bon marché', 'bon', 'bonne', 'bons', 'bonnes', 'cheap', 'qualité',
    'qualités', 'quality', 'mauvais', 'mauvaise', 'excellent', 'excellente',
    'excellents', 'excellentes', 'terrible', 'terribles', 'bien', 'mal',
    'très', 'beaucoup', 'peu', 'assez', 'trop', 'pas', 'ne', 'rien',
    'jamais', 'toujours', 'encore', 'déjà', 'bientôt', 'maintenant',
    'aujourd', 'aujourd hui', 'aujourd\'hui', 'hier', 'demain',
];

// English common words
$ENGLISH_WORDS = [
    'hello', 'hi', 'hey', 'good', 'morning', 'afternoon', 'evening', 'night',
    'thank', 'thanks', 'thank you', 'thanks a lot', 'thanks so much',
    'yes', 'no', 'maybe', 'can', 'could', 'would', 'should', 'will', 'shall',
    'i', 'you', 'he', 'she', 'it', 'we', 'they', 'me', 'him', 'her', 'us', 'them',
    'my', 'your', 'his', 'her', 'its', 'our', 'their', 'mine', 'yours',
    'a', 'an', 'the', 'and', 'or', 'but', 'so', 'because', 'that', 'which', 'who',
    'what', 'where', 'when', 'why', 'how', 'how much', 'how many',
    'this', 'that', 'these', 'those', 'here', 'there',
    'want', 'need', 'like', 'love', 'hate', 'prefer', 'choose', 'select', 'pick',
    'buy', 'purchase', 'sell', 'shop', 'shopping', 'store',
    'product', 'products', 'item', 'items', 'thing', 'things', 'stuff',
    'price', 'cost', 'money', 'pay', 'payment', 'charge', 'fee', 'fees',
    'delivery', 'shipping', 'ship', 'deliver', 'send', 'sent', 'arrive', 'arrived',
    'order', 'orders', 'track', 'tracking', 'return', 'returns',
    'refund', 'refunds', 'cancel', 'cancellation', 'help', 'support',
    'question', 'questions', 'answer', 'answers', 'information', 'info',
    'know', 'understand', 'see', 'look', 'find', 'search', 'show', 'display', 'list', 'browse',
    'fast', 'quick', 'slow', 'expensive', 'cheap', 'good', 'bad',
    'excellent', 'terrible', 'very', 'much', 'little', 'enough', 'too',
    'not', 'nothing', 'never', 'always', 'still', 'already', 'soon', 'now',
    'today', 'yesterday', 'tomorrow',
];

function detect_language($text) {
    global $KINYARWANDA_WORDS, $FRENCH_WORDS, $ENGLISH_WORDS;
    
    if (empty(trim($text))) {
        return 'english'; // default
    }
    
    $text_lower = strtolower(trim($text));
    preg_match_all('/\b\w+\b/', $text_lower, $matches);
    $words = $matches[0];
    
    if (empty($words)) {
        return 'english';
    }
    
    // Count matches for each language
    $kinyarwanda_count = 0;
    $french_count = 0;
    $english_count = 0;
    
    foreach ($words as $word) {
        if (in_array($word, $KINYARWANDA_WORDS)) {
            $kinyarwanda_count++;
        }
        if (in_array($word, $FRENCH_WORDS)) {
            $french_count++;
        }
        if (in_array($word, $ENGLISH_WORDS)) {
            $english_count++;
        }
    }
    
    // Determine language by highest count
    if ($kinyarwanda_count > $french_count && $kinyarwanda_count > $english_count) {
        return 'kinyarwanda';
    } elseif ($french_count > $english_count) {
        return 'french';
    } else {
        return 'english';
    }
}

// Multilingual response templates
$MULTILINGUAL_RESPONSES = [
    'greeting' => [
        'english' => [
            "Hello! Welcome to AI-Powered Chatbot For E-commerce Support. How can I help you today?",
            "Hi there! I'm your AI shopping assistant. What can I do for you?",
            "Hey! Great to see you. How can I assist you today?"
        ],
        'kinyarwanda' => [
            "Mwaramutse! Murakaza neza kuri AI-Powered Chatbot For E-commerce Support. Ndi iki nkwifuza?",
            "Mwiriwe! Ndi umukozi wacu wa shopping. Ndi iki nkwifuza?",
            "Muraho! Neza kuguha. Ndi iki nkwifuza?"
        ],
        'french' => [
            "Bonjour! Bienvenue sur le Chatbot IA pour le support e-commerce. Comment puis-je vous aider?",
            "Salut! Je suis votre assistant d'achat IA. Que puis-je faire pour vous?",
            "Hé! Ravi de vous voir. Comment puis-je vous aider?"
        ]
    ],
    'thanks' => [
        'english' => [
            "You're welcome! Happy to help.",
            "Glad I could assist! Is there anything else you need?",
            "My pleasure! Let me know if you need anything else."
        ],
        'kinyarwanda' => [
            "Ubwenge! Nari neza kubagenzi.",
            "Nari neza kubagenzi! Hari indi nkwifuza?",
            "Nari neza! Mbabarire niba hari indi nkwifuza."
        ],
        'french' => [
            "De rien! Heureux de pouvoir aider.",
            "Heureux d'avoir pu vous aider! Y a-t-il autre chose dont vous avez besoin?",
            "Mon plaisir! Faites-moi savoir si vous avez besoin d'autre chose."
        ]
    ],
    'product_search' => [
        'english' => [
            "I can help you find products! What category are you looking for?",
            "Sure! We have 1,161+ products. What are you looking for?",
            "Let me search our catalog for you. What product do you need?"
        ],
        'kinyarwanda' => [
            "Nshobora kubagenzi gushaka ibicuruzwa! Ubwoko biki bushaka?",
            "Yego! Dufite ibicuruzwa 1,161+. Ndi iki bushaka?",
            "Nzashaka mu katalogo yacu. Ndi iki bushaka?"
        ],
        'french' => [
            "Je peux vous aider à trouver des produits! Quelle catégorie cherchez-vous?",
            "Bien sûr! Nous avons plus de 1 161 produits. Que cherchez-vous?",
            "Laissez-moi chercher dans notre catalogue. Quel produit avez-vous besoin?"
        ]
    ],
    'product_price' => [
        'english' => [
            "I can check the price for you! Which product are you asking about?",
            "Our prices range from RWF 1,000 to RWF 5,000,000. Which product?",
            "Let me find the price for that product right away!"
        ],
        'kinyarwanda' => [
            "Nshobora kubagenzi gushaka igiciro! Iki bicuruzwa?",
            "Igiciro cyacu gihera kuri RWF 1,000 kugeza RWF 5,000,000. Iki bicuruzwa?",
            "Nzashaka igiciro cy'icyo bicuruzwa!"
        ],
        'french' => [
            "Je peux vérifier le prix pour vous! Quel produit demandez-vous?",
            "Nos prix vont de RWF 1 000 à RWF 5 000 000. Quel produit?",
            "Laissez-moi trouver le prix de ce produit tout de suite!"
        ]
    ],
    'order_track' => [
        'english' => [
            "I can help you track your order! Please provide your order number.",
            "To track your order, please share your order number.",
            "Let me check your order status. What's your order number?"
        ],
        'kinyarwanda' => [
            "Nshobora kubagenzi gushaka agaciro k'agaciro. Mbabarire numero y'agaciro.",
            "Gushaka agaciro, mbabarire numero y'agaciro.",
            "Nzashaka agaciro. Numero y'agaciro ari iki?"
        ],
        'french' => [
            "Je peux vous aider à suivre votre commande! Veuillez fournir votre numéro de commande.",
            "Pour suivre votre commande, veuillez partager votre numéro de commande.",
            "Laissez-moi vérifier l'état de votre commande. Quel est votre numéro de commande?"
        ]
    ],
    'delivery_time' => [
        'english' => [
            "Delivery to Kigali takes 1-2 business days. Other provinces take 2-4 days.",
            "We deliver within 1-2 days in Kigali and 2-4 days in other provinces.",
            "Standard delivery: Kigali 1-2 days, Provinces 2-4 days, Remote areas up to 7 days."
        ],
        'kinyarwanda' => [
            "Kugerageza i Kigali gutwara iminsi 1-2. Ibindi bigo gutwara iminsi 2-4.",
            "Tugerageza mu minsi 1-2 i Kigali no mu minsi 2-4 mu bigo.",
            "Kugerageza ibisanzwe: Kigali iminsi 1-2, Ibindi bigo iminsi 2-4, Ahantu hahuje kugeza iminsi 7."
        ],
        'french' => [
            "La livraison à Kigali prend 1-2 jours ouvrables. Les autres provinces prennent 2-4 jours.",
            "Nous livrons dans 1-2 jours à Kigali et 2-4 jours dans les autres provinces.",
            "Livraison standard: Kigali 1-2 jours, Provinces 2-4 jours, Zones éloignées jusqu'à 7 jours."
        ]
    ],
    'shipping_fee' => [
        'english' => [
            "Free shipping on all orders! No minimum required.",
            "All orders get FREE shipping, no minimum purchase!",
            "Shipping is free for all orders."
        ],
        'kinyarwanda' => [
            "Kugerageza kubuntu kuri buri gurwa!",
            "Buri gurwa rifite kugerageza kubuntu.",
            "Kugerageza kubuntu kuri buri gurwa."
        ],
        'french' => [
            "Livraison gratuite sur toutes les commandes!",
            "Toutes les commandes bénéficient d'une livraison GRATUITE.",
            "La livraison est gratuite pour toutes les commandes."
        ]
    ],
    'payment_methods' => [
        'english' => [
            "We accept: Cash on Delivery, MTN MoMo, Airtel Money, Visa/Mastercard, Bank Transfer.",
            "Payment options: COD, MTN Mobile Money, Airtel Money, Card, Bank Transfer.",
            "You can pay via Cash on Delivery, Mobile Money (MTN/Airtel), Card, or Bank Transfer."
        ],
        'kinyarwanda' => [
            "Dukwemera: Amafaranga mu gihe cy'ugerageza, MTN MoMo, Airtel Money, Visa/Mastercard, Kwishyura mu banki.",
            "Ubwoko bw'amafaranga: COD, MTN Mobile Money, Airtel Money, Karita, Kwishyura mu banki.",
            "Urashobora kwishyura mu gihe cy'ugerageza, Mobile Money (MTN/Airtel), Karita, cyangwa Kwishyura mu banki."
        ],
        'french' => [
            "Nous acceptons: Paiement à la livraison, MTN MoMo, Airtel Money, Visa/Mastercard, Virement bancaire.",
            "Options de paiement: COD, MTN Mobile Money, Airtel Money, Carte, Virement bancaire.",
            "Vous pouvez payer par Paiement à la livraison, Mobile Money (MTN/Airtel), Carte ou Virement bancaire."
        ]
    ],
    'return_policy' => [
        'english' => [
            "You can return items within 7 days of delivery for a full refund.",
            "Return policy: 7 days from delivery date. Items must be unused and in original packaging.",
            "We offer hassle-free returns within 7 days. Contact support for details."
        ],
        'kinyarwanda' => [
            "Urashobora kugarura ibicuruzwa mu minsi 7 nyuma y'ugerageza.",
            "Politiki y'ugarura: iminsi 7kuva ku minsi y'ugerageza. Ibicuruzwa byombi bitakoresha no mu nzira y'ibanze.",
            "Dufite ugarura burinshi mu minsi 7. Menya inyandiko z'inyubako."
        ],
        'french' => [
            "Vous pouvez retourner les articles dans les 7 jours suivant la livraison pour un remboursement complet.",
            "Politique de retour: 7 jours à partir de la date de livraison. Les articles doivent être inutilisés et dans l'emballage d'origine.",
            "Nous offrons des retours sans tracas dans les 7 jours. Contactez le support pour plus de détails."
        ]
    ]
];

function get_multilingual_response($intent, $language = 'english') {
    global $MULTILINGUAL_RESPONSES;
    
    if (isset($MULTILINGUAL_RESPONSES[$intent][$language])) {
        $responses = $MULTILINGUAL_RESPONSES[$intent][$language];
        return $responses[array_rand($responses)];
    }
    
    // Fallback to English
    if (isset($MULTILINGUAL_RESPONSES[$intent]['english'])) {
        $responses = $MULTILINGUAL_RESPONSES[$intent]['english'];
        return $responses[array_rand($responses)];
    }
    
    return "How can I help you?";
}
