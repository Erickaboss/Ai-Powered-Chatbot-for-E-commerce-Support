<?php

if (!function_exists('detectLanguage')) {
    /**
     * Detect language from user message (English default, French, Kinyarwanda cues).
     */
    function detectLanguage(string $text): string {
        $textLower = strtolower($text);

        $kinyarwandaWords = [
            'muraho', 'mwaramutse', 'mwiriwe', 'murakoze', 'yego', 'oya',
            'nde', 'iki', 'ikihe', 'uwuhe', 'ryari', 'he', 'iki', ' gute',
            'mfite', 'nshaka', 'bashaka', 'gurisha', 'kugura', 'ifishi',
            'ubwishyu', 'konti', '订单', 'delivery', 'vuba', 'ahantu',
        ];

        $frenchWords = [
            'bonjour', 'salut', 'merci', 'oui', 'non', 'je', 'tu', 'vous',
            'vouloir', 'acheter', 'produit', 'prix', 'commande', 'livraison',
            'payer', 'retour', 'remboursement', 'aide', 's\'il vous plaît',
            'comment', 'où', 'quoi', 'quel', 'quelle', 'combien', 'est-ce',
        ];

        $kinyaScore  = 0;
        $frenchScore = 0;

        foreach ($kinyarwandaWords as $word) {
            if (strpos($textLower, $word) !== false) {
                $kinyaScore++;
            }
        }

        foreach ($frenchWords as $word) {
            if (strpos($textLower, $word) !== false) {
                $frenchScore++;
            }
        }

        if ($kinyaScore > 0 && $kinyaScore >= $frenchScore) {
            return 'rw';
        }
        if ($frenchScore > 0 && $frenchScore > $kinyaScore) {
            return 'fr';
        }

        return 'en';
    }
}
