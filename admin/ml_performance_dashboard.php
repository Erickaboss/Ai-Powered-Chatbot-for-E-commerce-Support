<?php
/**
 * ML Performance Dashboard
 * Displays model accuracy, precision, recall, F1-score
 * Compares all 4 ML models with performance metrics visualization
 */

session_start();
require_once __DIR__ . '/../config/db.php';

// Check admin access
if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'admin') {
    header('Location: ' . SITE_URL . '/login.php');
    exit;
}

// Load model results
$model_results_file = __DIR__ . '/../chatbot-ml/models/model_results.json';
$model_data = file_exists($model_results_file) ? json_decode(file_get_contents($model_results_file), true) : [];

// Extract metrics
$models = $model_data['models'] ?? [];
$summary = $model_data['summary'] ?? [];
$dataset = $model_data['dataset'] ?? [];
$artifacts = $model_data['artifacts'] ?? [];

// Sort models by accuracy
usort($models, function($a, $b) {
    return $b['accuracy'] <=> $a['accuracy'];
});

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ML Performance Dashboard - Admin</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .header {
            background: white;
            padding: 30px;
            border-radius: 10px;
            margin-bottom: 30px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        .header h1 {
            color: #333;
            margin-bottom: 10px;
            font-size: 2.5em;
        }
        
        .header p {
            color: #666;
            font-size: 1.1em;
        }
        
        .summary-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            border-left: 5px solid #667eea;
        }
        
        .card.best {
            border-left-color: #10b981;
            background: linear-gradient(135deg, #f0fdf4 0%, #ffffff 100%);
        }
        
        .card h3 {
            color: #666;
            font-size: 0.9em;
            text-transform: uppercase;
            margin-bottom: 10px;
            letter-spacing: 1px;
        }
        
        .card .value {
            font-size: 2.5em;
            font-weight: bold;
            color: #333;
        }
        
        .card .subtext {
            color: #999;
            font-size: 0.9em;
            margin-top: 10px;
        }
        
        .models-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .model-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        .model-card.best {
            border: 3px solid #10b981;
            background: linear-gradient(135deg, #f0fdf4 0%, #ffffff 100%);
        }
        
        .model-card h3 {
            color: #333;
            margin-bottom: 20px;
            font-size: 1.3em;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .model-card h3 .badge {
            background: #10b981;
            color: white;
            padding: 3px 8px;
            border-radius: 20px;
            font-size: 0.7em;
            font-weight: bold;
        }
        
        .metric {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid #eee;
        }
        
        .metric:last-child {
            border-bottom: none;
        }
        
        .metric-label {
            color: #666;
            font-weight: 500;
        }
        
        .metric-value {
            font-weight: bold;
            color: #333;
            font-size: 1.1em;
        }
        
        .progress-bar {
            width: 100%;
            height: 8px;
            background: #eee;
            border-radius: 4px;
            overflow: hidden;
            margin-top: 5px;
        }
        
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
            border-radius: 4px;
            transition: width 0.3s ease;
        }
        
        .dataset-info {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        
        .dataset-info h2 {
            color: #333;
            margin-bottom: 20px;
            font-size: 1.5em;
        }
        
        .dataset-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }
        
        .dataset-stat {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
        }
        
        .dataset-stat .label {
            font-size: 0.9em;
            opacity: 0.9;
            margin-bottom: 10px;
        }
        
        .dataset-stat .value {
            font-size: 2em;
            font-weight: bold;
        }
        
        .artifacts-section {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        
        .artifacts-section h2 {
            color: #333;
            margin-bottom: 20px;
            font-size: 1.5em;
        }
        
        .artifacts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }
        
        .artifact-item {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            border: 1px solid #dee2e6;
        }
        
        .artifact-item h4 {
            color: #333;
            margin-bottom: 8px;
            font-size: 1em;
        }
        
        .artifact-item p {
            color: #666;
            font-size: 0.9em;
            margin-bottom: 10px;
        }
        
        .artifact-item .meta {
            display: flex;
            justify-content: space-between;
            font-size: 0.85em;
            color: #999;
        }
        
        .comparison-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        
        .comparison-table thead {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .comparison-table th {
            padding: 15px;
            text-align: left;
            font-weight: 600;
        }
        
        .comparison-table td {
            padding: 15px;
            border-bottom: 1px solid #eee;
        }
        
        .comparison-table tbody tr:hover {
            background: #f8f9fa;
        }
        
        .comparison-table tbody tr.best {
            background: #f0fdf4;
        }
        
        .metric-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: 600;
        }
        
        .metric-badge.excellent {
            background: #d1fae5;
            color: #065f46;
        }
        
        .metric-badge.good {
            background: #dbeafe;
            color: #0c2d6b;
        }
        
        .metric-badge.fair {
            background: #fef3c7;
            color: #78350f;
        }
        
        .footer {
            text-align: center;
            color: white;
            padding: 20px;
            margin-top: 30px;
        }
        
        .back-link {
            display: inline-block;
            background: white;
            color: #667eea;
            padding: 12px 24px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            margin-bottom: 20px;
            transition: all 0.3s ease;
        }
        
        .back-link:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        
        @media (max-width: 768px) {
            .header h1 {
                font-size: 1.8em;
            }
            
            .models-grid {
                grid-template-columns: 1fr;
            }
            
            .comparison-table {
                font-size: 0.9em;
            }
            
            .comparison-table th,
            .comparison-table td {
                padding: 10px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="<?php echo SITE_URL; ?>/admin/index.php" class="back-link">← Back to Admin</a>
        
        <div class="header">
            <h1>🤖 ML Performance Dashboard</h1>
            <p>Real-time model performance metrics and comparison</p>
        </div>
        
        <!-- Summary Cards -->
        <div class="summary-cards">
            <div class="card best">
                <h3>Best Model</h3>
                <div class="value"><?php echo htmlspecialchars($summary['best_model'] ?? 'N/A'); ?></div>
                <div class="subtext">Accuracy: <?php echo number_format(($summary['best_accuracy'] ?? 0) * 100, 2); ?>%</div>
            </div>
            
            <div class="card">
                <h3>Average Accuracy</h3>
                <div class="value"><?php echo number_format(($summary['average_accuracy'] ?? 0) * 100, 2); ?>%</div>
                <div class="subtext">All 4 models</div>
            </div>
            
            <div class="card">
                <h3>Training Samples</h3>
                <div class="value"><?php echo number_format($summary['training_samples'] ?? 0); ?></div>
                <div class="subtext"><?php echo number_format($summary['test_samples'] ?? 0); ?> test samples</div>
            </div>
            
            <div class="card">
                <h3>Intent Classes</h3>
                <div class="value"><?php echo $summary['num_classes'] ?? 0; ?></div>
                <div class="subtext">Unique intents</div>
            </div>
        </div>
        
        <!-- Model Comparison Table -->
        <table class="comparison-table">
            <thead>
                <tr>
                    <th>Model Name</th>
                    <th>Accuracy</th>
                    <th>Precision</th>
                    <th>Recall</th>
                    <th>F1-Score</th>
                    <th>CV Mean</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($models as $model): ?>
                <tr <?php echo $model['model_name'] === $summary['best_model'] ? 'class="best"' : ''; ?>>
                    <td><strong><?php echo htmlspecialchars($model['model_name']); ?></strong></td>
                    <td>
                        <span class="metric-badge excellent"><?php echo number_format($model['accuracy'] * 100, 2); ?>%</span>
                    </td>
                    <td><?php echo number_format($model['precision'] * 100, 2); ?>%</td>
                    <td><?php echo number_format($model['recall'] * 100, 2); ?>%</td>
                    <td><?php echo number_format($model['f1_score'] * 100, 2); ?>%</td>
                    <td><?php echo number_format($model['cv_mean'] * 100, 2); ?>%</td>
                    <td>
                        <?php if ($model['meets_target']): ?>
                            <span class="metric-badge excellent">✓ Meets Target</span>
                        <?php else: ?>
                            <span class="metric-badge fair">Below Target</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <!-- Individual Model Cards -->
        <h2 style="color: white; margin-bottom: 20px; font-size: 1.8em;">📊 Detailed Model Performance</h2>
        <div class="models-grid">
            <?php foreach ($models as $model): ?>
            <div class="model-card <?php echo $model['model_name'] === $summary['best_model'] ? 'best' : ''; ?>">
                <h3>
                    <?php echo htmlspecialchars($model['model_name']); ?>
                    <?php if ($model['model_name'] === $summary['best_model']): ?>
                        <span class="badge">🏆 Best</span>
                    <?php endif; ?>
                </h3>
                
                <div class="metric">
                    <span class="metric-label">Accuracy</span>
                    <span class="metric-value"><?php echo number_format($model['accuracy'] * 100, 2); ?>%</span>
                </div>
                <div class="progress-bar">
                    <div class="progress-fill" style="width: <?php echo $model['accuracy'] * 100; ?>%"></div>
                </div>
                
                <div class="metric">
                    <span class="metric-label">Precision</span>
                    <span class="metric-value"><?php echo number_format($model['precision'] * 100, 2); ?>%</span>
                </div>
                <div class="progress-bar">
                    <div class="progress-fill" style="width: <?php echo $model['precision'] * 100; ?>%"></div>
                </div>
                
                <div class="metric">
                    <span class="metric-label">Recall</span>
                    <span class="metric-value"><?php echo number_format($model['recall'] * 100, 2); ?>%</span>
                </div>
                <div class="progress-bar">
                    <div class="progress-fill" style="width: <?php echo $model['recall'] * 100; ?>%"></div>
                </div>
                
                <div class="metric">
                    <span class="metric-label">F1-Score</span>
                    <span class="metric-value"><?php echo number_format($model['f1_score'] * 100, 2); ?>%</span>
                </div>
                <div class="progress-bar">
                    <div class="progress-fill" style="width: <?php echo $model['f1_score'] * 100; ?>%"></div>
                </div>
                
                <div class="metric">
                    <span class="metric-label">Cross-Validation Mean</span>
                    <span class="metric-value"><?php echo number_format($model['cv_mean'] * 100, 2); ?>%</span>
                </div>
                
                <div class="metric">
                    <span class="metric-label">Macro Precision</span>
                    <span class="metric-value"><?php echo number_format($model['macro_precision'] * 100, 2); ?>%</span>
                </div>
                
                <div class="metric">
                    <span class="metric-label">Macro Recall</span>
                    <span class="metric-value"><?php echo number_format($model['macro_recall'] * 100, 2); ?>%</span>
                </div>
                
                <div class="metric">
                    <span class="metric-label">Macro F1</span>
                    <span class="metric-value"><?php echo number_format($model['macro_f1'] * 100, 2); ?>%</span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Dataset Information -->
        <div class="dataset-info">
            <h2>📚 Dataset Statistics</h2>
            <div class="dataset-grid">
                <div class="dataset-stat">
                    <div class="label">Total Samples</div>
                    <div class="value"><?php echo number_format($dataset['total_samples'] ?? 0); ?></div>
                </div>
                <div class="dataset-stat">
                    <div class="label">Training Samples</div>
                    <div class="value"><?php echo number_format($summary['training_samples'] ?? 0); ?></div>
                </div>
                <div class="dataset-stat">
                    <div class="label">Test Samples</div>
                    <div class="value"><?php echo number_format($summary['test_samples'] ?? 0); ?></div>
                </div>
                <div class="dataset-stat">
                    <div class="label">Unique Intents</div>
                    <div class="value"><?php echo $dataset['merged_intent_count'] ?? 0; ?></div>
                </div>
                <div class="dataset-stat">
                    <div class="label">Products Loaded</div>
                    <div class="value"><?php echo number_format($dataset['database_augmentation']['products_loaded'] ?? 0); ?></div>
                </div>
                <div class="dataset-stat">
                    <div class="label">Vocabulary Size</div>
                    <div class="value"><?php echo number_format($model_data['vectorizer']['max_features'] ?? 0); ?></div>
                </div>
            </div>
        </div>
        
        <!-- Artifacts Section -->
        <div class="artifacts-section">
            <h2>📁 Model Artifacts</h2>
            
            <h3 style="color: #666; margin-top: 20px; margin-bottom: 15px;">Plots & Visualizations</h3>
            <div class="artifacts-grid">
                <?php foreach ($artifacts['plots'] ?? [] as $plot): ?>
                <div class="artifact-item">
                    <h4>📊 <?php echo htmlspecialchars($plot['label']); ?></h4>
                    <p><?php echo htmlspecialchars($plot['path']); ?></p>
                    <div class="meta">
                        <span><?php echo number_format($plot['size_bytes'] / 1024, 1); ?> KB</span>
                        <span><?php echo date('M d, Y', strtotime($plot['modified_at'])); ?></span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <h3 style="color: #666; margin-top: 20px; margin-bottom: 15px;">Model Files</h3>
            <div class="artifacts-grid">
                <?php foreach ($artifacts['models'] ?? [] as $model_file): ?>
                <div class="artifact-item">
                    <h4>🤖 <?php echo htmlspecialchars($model_file['label']); ?></h4>
                    <p><?php echo htmlspecialchars($model_file['path']); ?></p>
                    <div class="meta">
                        <span><?php echo number_format($model_file['size_bytes'] / (1024 * 1024), 1); ?> MB</span>
                        <span><?php echo date('M d, Y', strtotime($model_file['modified_at'])); ?></span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <div class="footer">
            <p>Last Updated: <?php echo date('M d, Y H:i:s', strtotime($summary['trained_at'] ?? 'now')); ?></p>
            <p>Model Version: <?php echo htmlspecialchars($summary['model_version'] ?? 'N/A'); ?></p>
        </div>
    </div>
</body>
</html>
