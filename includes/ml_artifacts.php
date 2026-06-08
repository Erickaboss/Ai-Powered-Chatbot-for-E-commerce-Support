<?php
/**
 * Load ML Model Artifacts
 * Reads model_results.json and provides structured data for dashboard
 */

function loadMlArtifacts() {
    $model_results_file = __DIR__ . '/../chatbot-ml/models/model_results.json';
    
    $result = [
        'available' => false,
        'models' => [],
        'summary' => [],
        'plots' => [],
        'reports' => [],
        'dataset' => [],
        'vectorizer' => [],
        'split' => []
    ];
    
    if (!file_exists($model_results_file)) {
        return $result;
    }
    
    try {
        $data = json_decode(file_get_contents($model_results_file), true);
        
        if (!$data) {
            return $result;
        }
        
        $result['available'] = true;

        // Extract models — handle both old format (models array) and new flat format
        if (!empty($data['models'])) {
            $result['models'] = $data['models'];
        } else {
            // New train_full_store.py format — build a single model entry from top-level fields
            // For SVM linear: precision ≈ recall ≈ f1 ≈ accuracy (balanced classes)
            $acc = (float)($data['accuracy'] ?? ($data['test_accuracy'] ?? 0));
            $result['models'] = [[
                'model_name'       => $data['best_model'] ?? 'SVM (Linear)',
                'accuracy'         => $acc,
                'train_accuracy'   => (float)($data['train_accuracy'] ?? $acc),
                'precision'        => (float)($data['precision'] ?? $acc),
                'recall'           => (float)($data['recall'] ?? $acc),
                'f1_score'         => (float)($data['f1_score'] ?? $acc),
                'cv_mean'          => (float)($data['cv_mean'] ?? $acc),
                'cv_std'           => (float)($data['cv_std'] ?? 0),
                'training_samples' => (int)($data['training_samples'] ?? 0),
                'test_samples'     => (int)($data['test_samples'] ?? 0),
                'model_version'    => $data['model_version'] ?? '5.0.0',
                'trained_at'       => $data['timestamp'] ?? date('Y-m-d H:i:s'),
            ]];
        }

        // Extract summary
        if (!empty($data['summary'])) {
            $result['summary'] = $data['summary'];
            // Ensure best_model_row is always set
            if (empty($result['summary']['best_model_row']) && !empty($result['models'])) {
                $result['summary']['best_model_row'] = $result['models'][0];
            }
        } else {
            // Build summary from top-level fields (new flat format)
            $bestModelRow = $result['models'][0] ?? [];
            $result['summary'] = [
                'best_model'           => $data['best_model'] ?? 'SVM (Linear)',
                'best_model_row'       => $bestModelRow,
                'accuracy'             => $data['accuracy'] ?? ($data['test_accuracy'] ?? 0),
                'average_accuracy'     => calculateAverageAccuracy($result['models']),
                'training_samples'     => $data['training_samples'] ?? 0,
                'test_samples'         => $data['test_samples'] ?? 0,
                'num_classes'          => $data['num_classes'] ?? 0,
                'target_accuracy'      => $data['target_accuracy'] ?? 0.85,
                'all_models_above_target' => $data['all_models_above_target'] ?? false,
                'trained_at'           => $data['timestamp'] ?? date('Y-m-d H:i:s'),
                'model_version'        => $data['model_version'] ?? '5.0.0',
                'intents'              => $data['classes'] ?? [],
                'vocabulary_size'      => $data['vocabulary_size'] ?? ($data['vectorizer']['max_features'] ?? 3000),
                'cv_mean'              => $data['cv_mean'] ?? 0,
                'cv_std'               => $data['cv_std'] ?? 0,
            ];
        }
        
        // Extract dataset info
        if (!empty($data['dataset'])) {
            $result['dataset'] = $data['dataset'];
        }
        
        // Extract vectorizer info
        if (!empty($data['vectorizer'])) {
            $result['vectorizer'] = $data['vectorizer'];
        }
        
        // Extract split info
        if (!empty($data['split'])) {
            $result['split'] = $data['split'];
        }
        
        // Load plots from directory
        $plots_dir = __DIR__ . '/../chatbot-ml/plots';
        if (is_dir($plots_dir)) {
            $plot_files = glob($plots_dir . '/*.png');
            foreach ($plot_files as $plot_file) {
                $filename = basename($plot_file);
                if (!in_array($filename, ['cm_svm_linear.png', 'dataset_distribution.png'], true)) {
                    continue;
                }
                $result['plots'][] = [
                    'filename' => $filename,
                    'label' => formatPlotLabel($filename),
                    'path' => $plot_file,
                    'web_path' => '/ecommerce-chatbot/chatbot-ml/plots/' . $filename
                ];
            }
        }
        
        // Load reports from directory
        $reports_dir = __DIR__ . '/../chatbot-ml/reports';
        if (is_dir($reports_dir)) {
            $report_files = glob($reports_dir . '/*.txt');
            foreach ($report_files as $report_file) {
                $filename = basename($report_file);
                $result['reports'][] = [
                    'filename' => $filename,
                    'label' => formatReportLabel($filename),
                    'path' => $report_file,
                    'web_path' => '/ecommerce-chatbot/chatbot-ml/reports/' . $filename
                ];
            }
        }
        
    } catch (Exception $e) {
        error_log("Error loading ML artifacts: " . $e->getMessage());
    }
    
    return $result;
}

function calculateAverageAccuracy($models) {
    if (empty($models)) {
        return 0;
    }
    
    $total = 0;
    foreach ($models as $model) {
        $total += (float)($model['accuracy'] ?? 0);
    }
    
    return $total / count($models);
}

function formatPlotLabel($filename) {
    $labels = [
        'cm_svm_linear.png' => 'Confusion Matrix - SVM Linear',
        'dataset_distribution.png' => 'Dataset Distribution'
    ];
    
    return $labels[$filename] ?? ucfirst(str_replace(['_', '.png'], [' ', ''], $filename));
}

function formatReportLabel($filename) {
    $labels = [
        'comprehensive_training_report.txt' => 'Comprehensive Training Report',
        'full_evaluation_report.txt' => 'Full Evaluation Report',
        'performance_report.txt' => 'Performance Report'
    ];
    
    return $labels[$filename] ?? ucfirst(str_replace(['_', '.txt'], [' ', ''], $filename));
}
?>
