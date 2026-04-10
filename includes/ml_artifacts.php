<?php
/**
 * ML Artifacts loader — reads saved model_results.json and returns
 * structured data for the admin dashboard.
 */

function loadMlArtifacts(): array {
    $resultsPath = __DIR__ . '/../chatbot-ml/models/model_results.json';
    $plotsDir    = __DIR__ . '/../chatbot-ml/plots/';
    $reportsDir  = __DIR__ . '/../chatbot-ml/reports/';

    $empty = [
        'available'  => false,
        'summary'    => [],
        'models'     => [],
        'dataset'    => [],
        'vectorizer' => [],
        'split'      => [],
        'plots'      => [],
        'reports'    => [],
        'artifacts'  => [],
        'raw'        => [],
    ];

    if (!file_exists($resultsPath)) return $empty;

    $raw = json_decode(file_get_contents($resultsPath), true);
    if (!$raw) return $empty;

    // ── Summary ──
    $summary = $raw['summary'] ?? [];
    if (empty($summary) && !empty($raw['results'])) {
        // Legacy format — build summary from results
        $best = $raw['best_model'] ?? array_keys($raw['results'])[0];
        $allAcc = array_column($raw['results'], 'accuracy');
        $summary = [
            'best_model'       => $best,
            'accuracy'         => $raw['results'][$best]['accuracy'] ?? 0,
            'f1_score'         => $raw['results'][$best]['f1'] ?? 0,
            'average_accuracy' => array_sum($allAcc) / max(count($allAcc), 1),
            'num_classes'      => $raw['num_classes'] ?? count($raw['intents'] ?? []),
            'training_samples' => 0,
            'test_samples'     => 0,
            'model_version'    => '1.0.0',
            'all_models_above_85' => min($allAcc) >= 0.85,
            'target_accuracy'  => 0.85,
        ];
    }

    // ── Per-model rows ──
    $models = $raw['models'] ?? [];
    if (empty($models) && !empty($raw['results'])) {
        $cvResults = $raw['cv_results'] ?? [];
        foreach ($raw['results'] as $name => $m) {
            $cv = $cvResults[$name] ?? [];
            $cvMean = count($cv) ? array_sum($cv) / count($cv) : 0;
            $cvStd  = 0;
            if (count($cv) > 1) {
                $mean = $cvMean;
                $cvStd = sqrt(array_sum(array_map(fn($x) => ($x-$mean)**2, $cv)) / count($cv));
            }
            $models[] = [
                'model_name' => $name,
                'accuracy'   => $m['accuracy'],
                'precision'  => $m['precision'],
                'recall'     => $m['recall'],
                'f1_score'   => $m['f1'],
                'cv_mean'    => $cvMean,
                'cv_std'     => $cvStd,
                'is_best'    => $name === ($raw['best_model'] ?? ''),
            ];
        }
    }

    // ── Dataset info ──
    $dataset = $raw['dataset'] ?? [
        'total_samples'   => 0,
        'num_classes'     => $raw['num_classes'] ?? 0,
        'vocabulary_size' => 0,
    ];

    // ── Plots ──
    $plots = [];
    if (is_dir($plotsDir)) {
        foreach (glob($plotsDir . '*.png') as $plot) {
            $plots[] = [
                'filename' => basename($plot),
                'web_path' => SITE_URL . '/chatbot-ml/plots/' . basename($plot),
            ];
        }
    }

    // ── Reports ──
    $reports = [];
    if (is_dir($reportsDir)) {
        foreach (glob($reportsDir . '*.txt') as $report) {
            $reports[] = [
                'filename' => basename($report),
                'web_path' => null, // text files not served directly
                'size'     => filesize($report),
                'modified' => date('d M Y H:i', filemtime($report)),
            ];
        }
    }

    return [
        'available'  => true,
        'summary'    => $summary,
        'models'     => $models,
        'dataset'    => $dataset,
        'vectorizer' => ['type' => 'TF-IDF', 'ngram_range' => '(1,3)', 'max_features' => 8000],
        'split'      => ['train' => $summary['training_samples'] ?? 0, 'test' => $summary['test_samples'] ?? 0],
        'plots'      => $plots,
        'reports'    => $reports,
        'artifacts'  => ['model_results.json' => true],
        'raw'        => $raw,
    ];
}
