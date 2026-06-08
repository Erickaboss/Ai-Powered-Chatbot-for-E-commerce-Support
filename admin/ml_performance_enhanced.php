<?php
/**
 * Enhanced ML Performance Dashboard
 * Shows model performance metrics, accuracy, precision, recall, F1-score
 */

session_start();

// Check admin access
if (!isset($_SESSION['admin_id'])) {
    header('Location: ../index.php');
    exit;
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/metrics_collector.php';

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$metrics = new MetricsCollector($conn);

// Get data
$modelPerformance = $metrics->getModelPerformanceSummary();
$intentAccuracy = $metrics->getIntentAccuracySummary();
$userSatisfaction = $metrics->getUserSatisfactionSummary();
$geminiUsage = $metrics->getGeminiUsageSummary();

// Prepare data for charts
$modelNames = array_column($modelPerformance, 'model_name');
$accuracyScores = array_column($modelPerformance, 'accuracy');
$precisionScores = array_column($modelPerformance, 'precision');
$recallScores = array_column($modelPerformance, 'recall');
$f1Scores = array_column($modelPerformance, 'f1_score');

$intentTags = array_column($intentAccuracy, 'intent_tag');
$intentAccuracyPercent = array_column($intentAccuracy, 'accuracy_percent');

$satisfactionDates = array_column($userSatisfaction, 'date');
$satisfactionRatings = array_column($userSatisfaction, 'avg_rating');

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ML Performance Dashboard</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js" integrity="sha384-e6nUZLBkQ86NJ6TVVKAeSaK8jWa3NhkYWZFomE39AvDbQWeie9PlQqM3pmYW5d1g" crossorigin="anonymous"></script>
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
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        .header h1 {
            color: #333;
            margin-bottom: 10px;
        }
        
        .header p {
            color: #666;
            font-size: 14px;
        }
        
        .metrics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .metric-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        .metric-card h3 {
            color: #666;
            font-size: 12px;
            text-transform: uppercase;
            margin-bottom: 10px;
            letter-spacing: 1px;
        }
        
        .metric-value {
            font-size: 32px;
            font-weight: bold;
            color: #667eea;
        }
        
        .metric-subtext {
            color: #999;
            font-size: 12px;
            margin-top: 5px;
        }
        
        .charts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .chart-container {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        .chart-container h2 {
            color: #333;
            font-size: 16px;
            margin-bottom: 20px;
        }
        
        .chart-wrapper {
            position: relative;
            height: 300px;
        }
        
        .table-container {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
            overflow-x: auto;
        }
        
        .table-container h2 {
            color: #333;
            font-size: 16px;
            margin-bottom: 20px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th {
            background: #f5f5f5;
            padding: 12px;
            text-align: left;
            font-weight: 600;
            color: #333;
            border-bottom: 2px solid #ddd;
        }
        
        td {
            padding: 12px;
            border-bottom: 1px solid #eee;
            color: #666;
        }
        
        tr:hover {
            background: #f9f9f9;
        }
        
        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .badge-success {
            background: #d4edda;
            color: #155724;
        }
        
        .badge-warning {
            background: #fff3cd;
            color: #856404;
        }
        
        .badge-danger {
            background: #f8d7da;
            color: #721c24;
        }
        
        .progress-bar {
            width: 100%;
            height: 8px;
            background: #eee;
            border-radius: 4px;
            overflow: hidden;
        }
        
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #667eea, #764ba2);
            border-radius: 4px;
        }
        
        .back-link {
            display: inline-block;
            margin-bottom: 20px;
            color: white;
            text-decoration: none;
            font-size: 14px;
        }
        
        .back-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="index.php" class="back-link">← Back to Admin</a>
        
        <div class="header">
            <h1>🤖 ML Performance Dashboard</h1>
            <p>Real-time monitoring of chatbot AI model performance and metrics</p>
        </div>
        
        <!-- Key Metrics -->
        <div class="metrics-grid">
            <?php if (!empty($modelPerformance)): ?>
                <div class="metric-card">
                    <h3>Best Model Accuracy</h3>
                    <div class="metric-value"><?php echo round(max($accuracyScores) * 100, 1); ?>%</div>
                    <div class="metric-subtext"><?php echo $modelPerformance[0]['model_name']; ?></div>
                </div>
                
                <div class="metric-card">
                    <h3>Average Precision</h3>
                    <div class="metric-value"><?php echo round(array_sum($precisionScores) / count($precisionScores) * 100, 1); ?>%</div>
                    <div class="metric-subtext">Across all models</div>
                </div>
                
                <div class="metric-card">
                    <h3>Average Recall</h3>
                    <div class="metric-value"><?php echo round(array_sum($recallScores) / count($recallScores) * 100, 1); ?>%</div>
                    <div class="metric-subtext">Across all models</div>
                </div>
                
                <div class="metric-card">
                    <h3>Average F1-Score</h3>
                    <div class="metric-value"><?php echo round(array_sum($f1Scores) / count($f1Scores) * 100, 1); ?>%</div>
                    <div class="metric-subtext">Across all models</div>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Charts -->
        <div class="charts-grid">
            <!-- Model Comparison Chart -->
            <div class="chart-container">
                <h2>Model Performance Comparison</h2>
                <div class="chart-wrapper">
                    <canvas id="modelComparisonChart"></canvas>
                </div>
            </div>
            
            <!-- Intent Accuracy Chart -->
            <div class="chart-container">
                <h2>Intent Classification Accuracy</h2>
                <div class="chart-wrapper">
                    <canvas id="intentAccuracyChart"></canvas>
                </div>
            </div>
            
            <!-- User Satisfaction Trend -->
            <div class="chart-container">
                <h2>User Satisfaction Trend</h2>
                <div class="chart-wrapper">
                    <canvas id="satisfactionChart"></canvas>
                </div>
            </div>
        </div>
        
        <!-- Model Performance Table -->
        <div class="table-container">
            <h2>Model Performance Details</h2>
            <table>
                <thead>
                    <tr>
                        <th>Model Name</th>
                        <th>Accuracy</th>
                        <th>Precision</th>
                        <th>Recall</th>
                        <th>F1-Score</th>
                        <th>Training Samples</th>
                        <th>Test Samples</th>
                        <th>Trained At</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($modelPerformance as $model): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($model['model_name']); ?></strong></td>
                            <td>
                                <div class="progress-bar">
                                    <div class="progress-fill" style="width: <?php echo $model['accuracy'] * 100; ?>%"></div>
                                </div>
                                <?php echo round($model['accuracy'] * 100, 1); ?>%
                            </td>
                            <td><?php echo round($model['precision'] * 100, 1); ?>%</td>
                            <td><?php echo round($model['recall'] * 100, 1); ?>%</td>
                            <td><?php echo round($model['f1_score'] * 100, 1); ?>%</td>
                            <td><?php echo number_format($model['training_samples']); ?></td>
                            <td><?php echo number_format($model['test_samples']); ?></td>
                            <td><?php echo date('M d, Y', strtotime($model['trained_at'])); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Intent Accuracy Table -->
        <div class="table-container">
            <h2>Intent Classification Performance</h2>
            <table>
                <thead>
                    <tr>
                        <th>Intent Tag</th>
                        <th>Total Predictions</th>
                        <th>Correct Predictions</th>
                        <th>Accuracy</th>
                        <th>Avg Confidence</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($intentAccuracy as $intent): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($intent['intent_tag']); ?></strong></td>
                            <td><?php echo number_format($intent['total_predictions']); ?></td>
                            <td><?php echo number_format($intent['correct_predictions']); ?></td>
                            <td>
                                <span class="badge <?php echo $intent['accuracy_percent'] >= 90 ? 'badge-success' : ($intent['accuracy_percent'] >= 75 ? 'badge-warning' : 'badge-danger'); ?>">
                                    <?php echo $intent['accuracy_percent']; ?>%
                                </span>
                            </td>
                            <td><?php echo $intent['avg_confidence_percent']; ?>%</td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <script>
        // Model Comparison Chart
        const modelCtx = document.getElementById('modelComparisonChart').getContext('2d');
        new Chart(modelCtx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($modelNames); ?>,
                datasets: [
                    {
                        label: 'Accuracy',
                        data: <?php echo json_encode(array_map(fn($x) => round($x * 100, 1), $accuracyScores)); ?>,
                        backgroundColor: 'rgba(102, 126, 234, 0.8)',
                        borderColor: 'rgba(102, 126, 234, 1)',
                        borderWidth: 1
                    },
                    {
                        label: 'Precision',
                        data: <?php echo json_encode(array_map(fn($x) => round($x * 100, 1), $precisionScores)); ?>,
                        backgroundColor: 'rgba(118, 75, 162, 0.8)',
                        borderColor: 'rgba(118, 75, 162, 1)',
                        borderWidth: 1
                    },
                    {
                        label: 'Recall',
                        data: <?php echo json_encode(array_map(fn($x) => round($x * 100, 1), $recallScores)); ?>,
                        backgroundColor: 'rgba(237, 100, 166, 0.8)',
                        borderColor: 'rgba(237, 100, 166, 1)',
                        borderWidth: 1
                    },
                    {
                        label: 'F1-Score',
                        data: <?php echo json_encode(array_map(fn($x) => round($x * 100, 1), $f1Scores)); ?>,
                        backgroundColor: 'rgba(255, 159, 64, 0.8)',
                        borderColor: 'rgba(255, 159, 64, 1)',
                        borderWidth: 1
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 100,
                        ticks: {
                            callback: function(value) {
                                return value + '%';
                            }
                        }
                    }
                },
                plugins: {
                    legend: {
                        position: 'top'
                    }
                }
            }
        });
        
        // Intent Accuracy Chart
        const intentCtx = document.getElementById('intentAccuracyChart').getContext('2d');
        new Chart(intentCtx, {
            type: 'horizontalBar',
            data: {
                labels: <?php echo json_encode(array_slice($intentTags, 0, 10)); ?>,
                datasets: [{
                    label: 'Accuracy %',
                    data: <?php echo json_encode(array_slice($intentAccuracyPercent, 0, 10)); ?>,
                    backgroundColor: 'rgba(102, 126, 234, 0.8)',
                    borderColor: 'rgba(102, 126, 234, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: {
                        beginAtZero: true,
                        max: 100,
                        ticks: {
                            callback: function(value) {
                                return value + '%';
                            }
                        }
                    }
                }
            }
        });
        
        // User Satisfaction Chart
        const satisfactionCtx = document.getElementById('satisfactionChart').getContext('2d');
        new Chart(satisfactionCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_slice($satisfactionDates, 0, 30)); ?>,
                datasets: [{
                    label: 'Average Rating',
                    data: <?php echo json_encode(array_slice($satisfactionRatings, 0, 30)); ?>,
                    borderColor: 'rgba(102, 126, 234, 1)',
                    backgroundColor: 'rgba(102, 126, 234, 0.1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4,
                    pointRadius: 4,
                    pointBackgroundColor: 'rgba(102, 126, 234, 1)',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 5,
                        ticks: {
                            callback: function(value) {
                                return value + ' ⭐';
                            }
                        }
                    }
                },
                plugins: {
                    legend: {
                        position: 'top'
                    }
                }
            }
        });
    </script>
</body>
</html>
