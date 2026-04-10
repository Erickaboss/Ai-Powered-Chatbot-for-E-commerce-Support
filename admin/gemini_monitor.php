<?php
require_once 'includes/admin_header.php';

// Verify admin access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

// Date range filter
$days = (int)($_GET['days'] ?? 7);
$startDate = date('Y-m-d', strtotime("-{$days} days"));
$endDate = date('Y-m-d');

// Total chats in period
$totalChats = $conn->query("SELECT COUNT(*) as cnt FROM chatbot_logs WHERE created_at >= '$startDate' AND created_at <= '$endDate'")->fetch_assoc()['cnt'];

// Gemini-powered responses (look for ML tag in response)
$geminiResponses = $conn->query("
    SELECT COUNT(*) as cnt 
    FROM chatbot_logs 
    WHERE created_at >= '$startDate' 
    AND created_at <= '$endDate'
    AND (response LIKE '%🤖 ML:%' OR sentiment_score IS NOT NULL)
")->fetch_assoc()['cnt'];

// Calculate estimated Gemini usage
$estimatedGeminiCalls = $geminiResponses;

// Estimate cost (Google Gemini pricing: $0.000125 per 1K input chars, $0.000375 per 1K output chars)
$avgInputChars = 500; // Average message length
$avgOutputChars = 800; // Average response length

$estimatedInputChars = $estimatedGeminiCalls * $avgInputChars;
$estimatedOutputChars = $estimatedGeminiCalls * $avgOutputChars;

$estimatedCost = ($estimatedInputChars / 1000 * 0.000125) + ($estimatedOutputChars / 1000 * 0.000375);
$dailyAvgCost = $days > 0 ? $estimatedCost / $days : 0;
$projectedMonthlyCost = $dailyAvgCost * 30;

// Chat trend by day
$chatTrend = $conn->query("
    SELECT 
        DATE(created_at) as date,
        COUNT(*) as total_chats,
        SUM(CASE WHEN response LIKE '%🤖 ML:%' OR sentiment_score IS NOT NULL THEN 1 ELSE 0 END) as ai_responses,
        AVG(LENGTH(message)) as avg_msg_length,
        AVG(LENGTH(response)) as avg_response_length
    FROM chatbot_logs 
    WHERE created_at >= '$startDate' AND created_at <= '$endDate'
    GROUP BY DATE(created_at)
    ORDER BY date ASC
");

// Sentiment distribution
$sentimentDist = $conn->query("
    SELECT 
        sentiment_label,
        COUNT(*) as count,
        AVG(sentiment_score) as avg_score
    FROM chatbot_logs 
    WHERE created_at >= '$startDate' 
    AND created_at <= '$endDate'
    AND sentiment_score IS NOT NULL
    GROUP BY sentiment_label
");

// Escalated chats count
$escalatedCount = $conn->query("
    SELECT COUNT(*) as cnt 
    FROM chatbot_logs 
    WHERE escalated = 1 
    AND created_at >= '$startDate'
")->fetch_assoc()['cnt'];

// Top queried products (from messages mentioning products)
$productQueries = $conn->query("
    SELECT message, COUNT(*) as freq
    FROM chatbot_logs
    WHERE (message LIKE '%phone%' OR message LIKE '%laptop%' OR message LIKE '%Samsung%' OR message LIKE '%iPhone%')
    AND created_at >= '$startDate'
    GROUP BY message
    ORDER BY freq DESC
    LIMIT 10
");

// Language detection (approximate from keywords)
$languageStats = [
    'English' => 0,
    'French' => 0,
    'Kinyarwanda' => 0
];

$langResult = $conn->query("
    SELECT message FROM chatbot_logs 
    WHERE created_at >= '$startDate'
");
while ($row = $langResult->fetch_assoc()) {
    $msg = strtolower($row['message']);
    if (preg_match('/muraho|mwaramutse|mwiriwe|murakoze|nshaka|nderagura/', $msg)) {
        $languageStats['Kinyarwanda']++;
    } elseif (preg_match('/bonjour|salut|merci|je veux|prix|commande/', $msg)) {
        $languageStats['French']++;
    } else {
        $languageStats['English']++;
    }
}
?>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2><i class="bi bi-graph-up me-2"></i>Gemini API Usage Monitor</h2>
            <p class="text-muted mb-0">Track AI usage, costs, and performance metrics</p>
        </div>
        <div class="d-flex gap-2">
            <select class="form-select" onchange="window.location.href='?days='+this.value">
                <option value="7" <?= $days==7?'selected':'' ?>>Last 7 Days</option>
                <option value="14" <?= $days==14?'selected':'' ?>>Last 14 Days</option>
                <option value="30" <?= $days==30?'selected':'' ?>>Last 30 Days</option>
                <option value="90" <?= $days==90?'selected':'' ?>>Last 90 Days</option>
            </select>
            <button class="btn btn-outline-primary" onclick="location.reload()">
                <i class="bi bi-arrow-clockwise me-1"></i>Refresh
            </button>
        </div>
    </div>

    <!-- Key Metrics Cards -->
    <div class="row g-4 mb-4">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100" style="border-radius: 15px;">
                <div class="card-body text-center p-4">
                    <div class="mb-2">
                        <i class="bi bi-chat-dots text-primary" style="font-size: 2rem;"></i>
                    </div>
                    <h3 class="fw-bold mb-1"><?= number_format($totalChats) ?></h3>
                    <p class="text-muted mb-0">Total Conversations</p>
                    <small class="text-success">
                        <i class="bi bi-arrow-up"></i> Active period
                    </small>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100" style="border-radius: 15px;">
                <div class="card-body text-center p-4">
                    <div class="mb-2">
                        <i class="bi bi-robot text-success" style="font-size: 2rem;"></i>
                    </div>
                    <h3 class="fw-bold mb-1"><?= number_format($geminiResponses) ?></h3>
                    <p class="text-muted mb-0">AI-Powered Responses</p>
                    <small class="text-muted">
                        <?= $totalChats > 0 ? round(($geminiResponses/$totalChats)*100, 1) : 0 ?>% of total
                    </small>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100" style="border-radius: 15px;">
                <div class="card-body text-center p-4">
                    <div class="mb-2">
                        <i class="bi bi-currency-dollar text-warning" style="font-size: 2rem;"></i>
                    </div>
                    <h3 class="fw-bold mb-1">$<?= number_format($estimatedCost, 4) ?></h3>
                    <p class="text-muted mb-0">Estimated Cost (<?= $days ?> days)</p>
                    <small class="text-info">
                        ~$<?= number_format($dailyAvgCost, 4) ?>/day
                    </small>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100" style="border-radius: 15px;">
                <div class="card-body text-center p-4">
                    <div class="mb-2">
                        <i class="bi bi-exclamation-triangle text-danger" style="font-size: 2rem;"></i>
                    </div>
                    <h3 class="fw-bold mb-1"><?= number_format($escalatedCount) ?></h3>
                    <p class="text-muted mb-0">Escalated Chats</p>
                    <small class="text-muted">
                        Negative sentiment cases
                    </small>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Row 1 -->
    <div class="row g-4 mb-4">
        <!-- Chat Trend -->
        <div class="col-md-8">
            <div class="card border-0 shadow-sm" style="border-radius: 15px;">
                <div class="card-header bg-white border-0 py-3">
                    <h6 class="mb-0 fw-bold"><i class="bi bi-graph-up me-2"></i>Daily Chat Activity & AI Usage</h6>
                </div>
                <div class="card-body">
                    <canvas id="chatTrendChart" height="100"></canvas>
                </div>
            </div>
        </div>
        
        <!-- Language Distribution -->
        <div class="col-md-4">
            <div class="card border-0 shadow-sm" style="border-radius: 15px;">
                <div class="card-header bg-white border-0 py-3">
                    <h6 class="mb-0 fw-bold"><i class="bi bi-translate me-2"></i>Language Distribution</h6>
                </div>
                <div class="card-body">
                    <canvas id="languageChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Row 2 -->
    <div class="row g-4 mb-4">
        <!-- Sentiment Analysis -->
        <div class="col-md-6">
            <div class="card border-0 shadow-sm" style="border-radius: 15px;">
                <div class="card-header bg-white border-0 py-3">
                    <h6 class="mb-0 fw-bold"><i class="bi bi-emoji-smile me-2"></i>Sentiment Distribution</h6>
                </div>
                <div class="card-body">
                    <canvas id="sentimentChart"></canvas>
                </div>
            </div>
        </div>
        
        <!-- Cost Projection -->
        <div class="col-md-6">
            <div class="card border-0 shadow-sm" style="border-radius: 15px;">
                <div class="card-header bg-white border-0 py-3">
                    <h6 class="mb-0 fw-bold"><i class="bi bi-calculator me-2"></i>Cost Analysis & Projection</h6>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Current Period Cost:</span>
                            <strong>$<?= number_format($estimatedCost, 4) ?></strong>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Daily Average:</span>
                            <strong>$<?= number_format($dailyAvgCost, 4) ?></strong>
                        </div>
                        <div class="d-flex justify-content-between mb-3">
                            <span class="text-muted">Projected Monthly:</span>
                            <strong class="text-primary">$<?= number_format($projectedMonthlyCost, 2) ?></strong>
                        </div>
                        <hr>
                        <div class="alert alert-info mb-0">
                            <strong>💡 Tip:</strong> Free tier includes 1,000 requests/day.<br>
                            <?php if ($geminiResponses / $days < 800): ?>
                                ✅ You're well within free tier limits!
                            <?php elseif ($geminiResponses / $days < 950): ?>
                                ⚠️ Approaching free tier limit. Consider paid tier (~$2/month).
                            <?php else: ?>
                                🚨 At capacity! Upgrade to paid tier recommended.
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Top Queries Table -->
    <div class="row g-4 mb-4">
        <div class="col-md-12">
            <div class="card border-0 shadow-sm" style="border-radius: 15px;">
                <div class="card-header bg-white border-0 py-3 d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-bold"><i class="bi bi-search me-2"></i>Top Product Queries</h6>
                    <span class="badge bg-primary"><?= $productQueries->num_rows ?> queries</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th class="border-0 py-3">Query</th>
                                    <th class="border-0 py-3 text-center">Frequency</th>
                                    <th class="border-0 py-3 text-center">Type</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($query = $productQueries->fetch_assoc()): ?>
                                <tr>
                                    <td class="py-3"><?= htmlspecialchars($query['message']) ?></td>
                                    <td class="py-3 text-center">
                                        <span class="badge bg-success"><?= $query['freq'] ?></span>
                                    </td>
                                    <td class="py-3 text-center">
                                        <?php if (stripos($query['message'], 'price') !== false): ?>
                                            <span class="badge bg-info">Price Check</span>
                                        <?php elseif (stripos($query['message'], 'buy') !== false || stripos($query['message'], 'want') !== false): ?>
                                            <span class="badge bg-warning">Purchase Intent</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">General</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Prepare data from PHP
const chatTrendData = <?= json_encode($chatTrend->fetch_all(MYSQLI_ASSOC)) ?>;
const sentimentData = <?= json_encode($sentimentDist->fetch_all(MYSQLI_ASSOC)) ?>;
const languageData = <?= json_encode($languageStats) ?>;

// Chat Trend Chart
new Chart(document.getElementById('chatTrendChart'), {
    type: 'line',
    data: {
        labels: chatTrendData.map(d => d.date),
        datasets: [{
            label: 'Total Chats',
            data: chatTrendData.map(d => d.total_chats),
            borderColor: '#e94560',
            backgroundColor: 'rgba(233, 69, 96, 0.1)',
            tension: 0.4
        }, {
            label: 'AI Responses',
            data: chatTrendData.map(d => d.ai_responses),
            borderColor: '#4caf50',
            backgroundColor: 'rgba(76, 175, 80, 0.1)',
            tension: 0.4
        }]
    },
    options: {
        responsive: true,
        interaction: {
            mode: 'index',
            intersect: false
        }
    }
});

// Language Distribution Chart
new Chart(document.getElementById('languageChart'), {
    type: 'doughnut',
    data: {
        labels: Object.keys(languageData),
        datasets: [{
            data: Object.values(languageData),
            backgroundColor: ['#0f3460', '#e94560', '#4caf50']
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { position: 'bottom' }
        }
    }
});

// Sentiment Distribution Chart
new Chart(document.getElementById('sentimentChart'), {
    type: 'bar',
    data: {
        labels: sentimentData.map(d => d.sentiment_label || 'Unknown'),
        datasets: [{
            label: 'Count',
            data: sentimentData.map(d => d.count),
            backgroundColor: ['#4caf50', '#ff9800', '#e94560']
        }]
    },
    options: {
        responsive: true,
        scales: {
            y: { beginAtZero: true }
        }
    }
});

// Auto-refresh every 60 seconds
setInterval(() => {
    location.reload();
}, 60000);
</script>

<?php require_once 'includes/admin_footer.php'; ?>
