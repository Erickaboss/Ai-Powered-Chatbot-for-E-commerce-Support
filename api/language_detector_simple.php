<?php
/**
 * Simple Language Detection for Chatbot
 * Detects only: English, French, Kinyarwanda
 */

// Kinyarwanda words
$KINYARWANDA_WORDS = [
    'mwaramutse', 'mwiriwe', 'muraho', 'murakoze', 'habari', 'amakuru', 'iki', 'nde', 'ute',
    'inyumba', 'umuntu', 'igitabo', 'amafaranga', 'ubwoko', 'ikinini', 'icyuma',
    'urakoze', 'asante', 'ahubwo', 'yego', 'oya', 'nta', 'niba',
    'ariko', 'kandi', 'cyangwa', 'kubera', 'nkuko', 'uko', 'aho', 'hano',
    'hari', 'nari', 'umunsi', 'igice', 'igipimo', 'igihe', 'igikorwa',
    'abantu', 'abakazi', 'abagabo', 'abana', 'abakungu', 'abakobwa',
    'kugura', 'kuguza', 'kugurisha', 'kunywa', 'kurya', 'kuryamwo',
    'gusoma', 'gusomeka', 'kwiyandikisha', 'kwiyandikira', 'gukora', 'gukoresha',
    'gusobanura', 'gusaba', 'nyereka', 'erekana', 'mpore', 'mbwira', 'ndashaka', 'nshaka',
    'fungura', 'reba', 'soma', 'zingahe', 'bingahe', 'ibicuruzwa', 'ibintu', 'telefoni',
    'laptop', 'simu', 'imyenda', 'inzu', 'imodoka', 'igiciro', 'inzira', 'umwanya',
];

// French words
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
    'combien', 'quel', 'quoi', 'comment', 'pourquoi', 'où', 'quand',
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

// English words
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
        return 'english';
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
