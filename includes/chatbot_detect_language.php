<?php

if (!function_exists('detectLanguage')) {
    /**
     * Detect language from user message.
     * Returns 'en' (default), 'fr' (French), or 'rw' (Kinyarwanda).
     * English is the default — only switch if clear Kinyarwanda or French words found.
     */
    function detectLanguage(string $text): string {
        $t = strtolower(trim($text));

        // ── Kinyarwanda — only clear, unambiguous words ──
        $rwWords = [
            'muraho', 'mwaramutse', 'mwiriwe', 'murakoze', 'ndashaka',
            'nshaka', 'mfite', 'nfite', 'kugura', 'ibicuruzwa', 'igiciro',
            'amafaranga', 'nyereka', 'erekana', 'mbwira', 'sobanura',
            'mufitemo', 'zingahe', 'bingahe', 'nabagufasha', 'nkugufasha',
            'telefoni', 'imyenda', 'gutumiza', 'kurikirana', 'yego', 'oya',
            'murakoze', 'ndabashimiye', 'ibintu', 'ibyiciro', 'budgeti',
        ];

        // ── French — only clear, unambiguous words ──
        $frWords = [
            'bonjour', 'salut', 'merci', 'oui', 'non',
            'acheter', 'produit', 'produits', 'prix', 'commande', 'livraison',
            'payer', 'retour', 'remboursement', 'montrez', 'afficher',
            'combien', 'quelles', 'quelle', "j'ai", 'mon budget',
            'je veux', 'je peux', 'moins de', 'plus de', 'cherche',
        ];

        $rwScore = 0;
        $frScore = 0;

        foreach ($rwWords as $w) {
            if (preg_match('/\b' . preg_quote($w, '/') . '\b/i', $t)) {
                $rwScore++;
            }
        }

        foreach ($frWords as $w) {
            if (strpos($t, $w) !== false) {
                $frScore++;
            }
        }

        if ($rwScore > 0 && $rwScore >= $frScore) return 'rw';
        if ($frScore > 0 && $frScore > $rwScore)  return 'fr';

        return 'en'; // default
    }
}
