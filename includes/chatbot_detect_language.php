<?php

if (!function_exists('detectLanguage')) {
    /**
     * Detect language from user message (English default, French, Kinyarwanda cues).
     */
    function detectLanguage(string $text): string {
        $textLower = strtolower(trim(str_replace(["’", '`'], "'", $text)));
        $searchTexts = [$textLower];
        $asciiText = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $textLower);
        if (is_string($asciiText) && $asciiText !== '') {
            $searchTexts[] = strtolower($asciiText);
        }

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

        $frenchWords = array_merge($frenchWords, [
            "j'ai", 'j ai', 'mon budget', 'je veux', 'je peux',
            'avec un budget', 'pour des', 'pour une', 'sous', 'moins de',
            'plus de', 'invite',
        ]);

        $kinyaScore  = 0;
        $frenchScore = 0;

        foreach ($kinyarwandaWords as $word) {
            foreach ($searchTexts as $searchText) {
                if (strpos($searchText, $word) !== false) {
                    $kinyaScore++;
                    break;
                }
            }
        }

        foreach ($frenchWords as $word) {
            foreach ($searchTexts as $searchText) {
                if (strpos($searchText, $word) !== false) {
                    $frenchScore++;
                    break;
                }
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
