<?php
/**
 * Gemini is reserved for complex English questions only.
 * Store, product, order, budget, delivery and account questions should be answered
 * by PHP + database rules or the local SVM intent classifier.
 */
require_once __DIR__ . '/chatbot_detect_language.php';

if (!function_exists('shouldInvokeGeminiLastResort')) {
function shouldInvokeGeminiLastResort(string $msg, ?array $mlResult): bool {
    $t = trim($msg);
    $len = mb_strlen($t);

    // Short general-knowledge / complex questions should reach Gemini
    if (preg_match('/\b(who is|what is|tell me about|define|meaning of|how does|how do|how can|why is|why do)\b/i', $t) && $len >= 8) {
        return true;
    }

    if ($len < 14) {
        return false;
    }

    if (preg_match('/^(ok+|okay|yes|yeah|yep|no|nope|sure|fine|thanks?|thank you|merci|murakoze|oui|non)\s*[!.]*$/iu', $t)) {
        return false;
    }

    $lang     = detectLanguage($t);
    $mlMissed = $mlResult === null;
    $words    = preg_split('/\s+/u', $t, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $wc       = count($words);

    if ($lang !== 'en') {
        return false;
    }

    if (preg_match('/\b(order|track|tracking|invoice|cancel|refund|return|delivery|shipping|payment|momo|airtel|cash|login|register|account|password|cart|checkout|stock|price|cost|budget|under|below|product|category|brand|phone|laptop|fashion|groceries|warranty|support|contact|show|find|search|browse|list|display|available|get me)\b|in stock|do you have/i', $t)) {
        return false;
    }

    if (preg_match('/\b(why|how come|explain|what if|compare|versus|difference between|clarify|elaborate|in detail|step by step|help me understand)\b/i', $t)) {
        return true;
    }
    if (substr_count($t, '?') >= 2 && $len >= 28) {
        return true;
    }
    if ($wc >= 14 && $len >= 55) {
        return true;
    }

    if ($mlMissed && $len >= 70 && $wc >= 12) {
        return true;
    }

    return false;
}
}
