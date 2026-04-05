<?php
/**
 * When Google Gemini may run: last resort after PHP + ML, for complex / multilingual / substantive text.
 */
require_once __DIR__ . '/chatbot_detect_language.php';

if (!function_exists('shouldInvokeGeminiLastResort')) {
function shouldInvokeGeminiLastResort(string $msg, ?array $mlResult): bool {
    $t = trim($msg);
    $len = mb_strlen($t);
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

    if (in_array($lang, ['fr', 'rw'], true) && $len >= 14) {
        return true;
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

    if ($mlMissed && $len >= 40 && $wc >= 8) {
        return true;
    }

    return false;
}
}
