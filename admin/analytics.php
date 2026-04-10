<?php
require_once 'includes/admin_header.php';

// Verify admin access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

// Fetch real-time stats from dashboard view
$stats = [];
$result = $conn->query("SELECT * FROM admin_dashboard_stats");
while ($row = $result->fetch_assoc()) {
    $stats[$row['metric']] = $row['value'];
}

// Sales trend (last 30 days)
$salesTrend = $conn->query("
    SELECT DATE(created_at) as date, 
           COUNT(*) as orders, 
           SUM(total_price) as revenue
    FROM orders 
    WHERE status != 'cancelled' 
    AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY DATE(created_at)
    ORDER BY date ASC
");

// Top products
$topProducts = $conn->query("
    SELECT p.name, SUM(oi.quantity) as sold, SUM(oi.price * oi.quantity) as revenue
    FROM order_items oi
    JOIN products p ON oi.product_id = p.id
    JOIN orders o ON oi.order_id = o.id
    WHERE o.status != 'cancelled'
    GROUP BY p.id, p.name
    ORDER BY sold DESC
    LIMIT 10
");

// Customer segments distribution
$segments = $conn->query("
    SELECT segment, COUNT(*) as count, AVG(total_spent) as avg_spent
    FROM customer_segments
    GROUP BY segment
");

// Sentiment analysis (last 7 days)
$sentimentData = $conn->query("
    SELECT 
        DATE(created_at) as date,
        COUNT(*) as total_chats,
        AVG(sentiment_score) as avg_sentiment,
        SUM(CASE WHEN sentiment_label='positive' THEN 1 ELSE 0 END) as positive,
        SUM(CASE WHEN sentiment_label='negative' THEN 1 ELSE 0 END) as negative,
        SUM(escalated) as escalated_count
    FROM chatbot_logs
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    GROUP BY DATE(created_at)
");

// Low stock products
$lowStock = $conn->query("
    SELECT name, stock, category_id 
    FROM products 
    WHERE stock < 10 AND stock > 0
    ORDER BY stock ASC
    LIMIT 10
");

// Recent escalated chats
$escalatedChats = $conn->query("
    SELECT cl.message, cl.sentiment_score, cl.created_at, 
           u.name as user_name, u.email
    FROM chatbot_logs cl
    LEFT JOIN users u ON cl.user_id = u.id
    WHERE cl.escalated = 1
    ORDER BY cl.created_at DESC
    LIMIT 10
");
?>

<!-- Chart.js Library -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<div class="container-fluid py-4">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="bi bi-speedometer2 me-2"></i>Real-Time Analytics Dashboard</h2>
        <button class="btn btn-outline-primary" onclick="refreshDashboard()">
            <i class="bi bi-arrow-clockwise me-1"></i>Refresh
        </button>
    </div>

    <!-- Key Metrics Cards -->
    <div class="row g-4 mb-4">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100" style="border-radius: 15px;">
                <div class="card-body text-center p-4">
                    <div class="mb-2">
                        <i class="bi bi-cart3 text-primary" style="font-size: 2rem;"></i>
                    </div>
                    <h3 class="fw-bold mb-1"><?= number_format($stats['Total Sales'] ?? 0) ?></h3>
                    <p class="text-muted mb-0">Total Sales</p>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100" style="border-radius: 15px;">
                <div class="card-body text-center p-4">
                    <div class="mb-2">
                        <i class="bi bi-currency-dollar text-success" style="font-size: 2rem;"></i>
                    </div>
                    <h3 class="fw-bold mb-1">RWF <?= number_format($stats['Revenue Today'] ?? 0) ?></h3>
                    <p class="text-muted mb-0">Revenue Today</p>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100" style="border-radius: 15px;">
                <div class="card-body text-center p-4">
                    <div class="mb-2">
                        <i class="bi bi-clock-history text-warning" style="font-size: 2rem;"></i>
                    </div>
                    <h3 class="fw-bold mb-1"><?= number_format($stats['Pending Orders'] ?? 0) ?></h3>
                    <p class="text-muted mb-0">Pending Orders</p>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100" style="border-radius: 15px;">
                <div class="card-body text-center p-4">
                    <div class="mb-2">
                        <i class="bi bi-exclamation-triangle text-danger" style="font-size: 2rem;"></i>
                    </div>
                    <h3 class="fw-bold mb-1"><?= number_format($stats['Low Stock Products'] ?? 0) ?></h3>
                    <p class="text-muted mb-0">Low Stock Alert</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Row 1 -->
    <div class="row g-4 mb-4">
        <!-- Sales Trend -->
        <div class="col-md-8">
            <div class="card border-0 shadow-sm" style="border-radius: 15px;">
                <div class="card-header bg-white border-0 py-3">
                    <h6 class="mb-0 fw-bold"><i class="bi bi-graph-up me-2"></i>Sales Trend (Last 30 Days)</h6>
                </div>
                <div class="card-body">
                    <canvas id="salesTrendChart" height="80"></canvas>
                </div>
            </div>
        </div>
        
        <!-- Customer Segments -->
        <div class="col-md-4">
            <div class="card border-0 shadow-sm" style="border-radius: 15px;">
                <div class="card-header bg-white border-0 py-3">
                    <h6 class="mb-0 fw-bold"><i class="bi bi-people me-2"></i>Customer Segments</h6>
                </div>
                <div class="card-body">
                    <canvas id="segmentsChart"></canvas>
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
                    <h6 class="mb-0 fw-bold"><i class="bi bi-emoji-smile me-2"></i>Chatbot Sentiment Analysis (Last 7 Days)</h6>
                </div>
                <div class="card-body">
                    <canvas id="sentimentChart" height="100"></canvas>
                </div>
            </div>
        </div>
        
        <!-- Top Products -->
        <div class="col-md-6">
            <div class="card border-0 shadow-sm" style="border-radius: 15px;">
                <div class="card-header bg-white border-0 py-3">
                    <h6 class="mb-0 fw-bold"><i class="bi bi-trophy me-2"></i>Top Selling Products</h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th class="border-0 py-3">Product</th>
                                    <th class="border-0 py-3 text-center">Sold</th>
                                    <th class="border-0 py-3 text-end">Revenue</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($product = $topProducts->fetch_assoc()): ?>
                                <tr>
                                    <td class="py-3"><?= htmlspecialchars($product['name']) ?></td>
                                    <td class="py-3 text-center">
                                        <span class="badge bg-primary"><?= $product['sold'] ?></span>
                                    </td>
                                    <td class="py-3 text-end fw-bold">
                                        RWF <?= number_format($product['revenue']) ?>
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

    <!-- Alerts Row -->
    <div class="row g-4 mb-4">
        <!-- Low Stock Alerts -->
        <div class="col-md-6">
            <div class="card border-0 shadow-sm" style="border-radius: 15px;">
                <div class="card-header bg-white border-0 py-3 d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-bold"><i class="bi bi-exclamation-octagon text-danger me-2"></i>Low Stock Alerts</h6>
                    <a href="products.php" class="btn btn-sm btn-outline-primary">View All</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th class="border-0 py-3">Product</th>
                                    <th class="border-0 py-3 text-center">Stock Left</th>
                                    <th class="border-0 py-3 text-center">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($item = $lowStock->fetch_assoc()): ?>
                                <tr>
                                    <td class="py-3"><?= htmlspecialchars($item['name']) ?></td>
                                    <td class="py-3 text-center">
                                        <span class="badge bg-<?= $item['stock'] < 5 ? 'danger' : 'warning' ?>">
                                            <?= $item['stock'] ?>
                                        </span>
                                    </td>
                                    <td class="py-3 text-center">
                                        <?php if ($item['stock'] < 5): ?>
                                            <span class="text-danger small">Critical</span>
                                        <?php else: ?>
                                            <span class="text-warning small">Low</span>
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
        
        <!-- Escalated Chats -->
        <div class="col-md-6">
            <div class="card border-0 shadow-sm" style="border-radius: 15px;">
                <div class="card-header bg-white border-0 py-3 d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-bold"><i class="bi bi-chat-quote text-warning me-2"></i>Recent Escalated Chats</h6>
                    <a href="support_tickets.php" class="btn btn-sm btn-outline-primary">View All</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th class="border-0 py-3">Customer</th>
                                    <th class="border-0 py-3">Message</th>
                                    <th class="border-0 py-3 text-center">Sentiment</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($chat = $escalatedChats->fetch_assoc()): ?>
                                <tr>
                                    <td class="py-3">
                                        <div class="small">
                                            <strong><?= htmlspecialchars($chat['user_name'] ?: 'Guest') ?></strong><br>
                                            <span class="text-muted"><?= htmlspecialchars($chat['email'] ?: 'N/A') ?></span>
                                        </div>
                                    </td>
                                    <td class="py-3">
                                        <div class="text-truncate" style="max-width: 250px;" title="<?= htmlspecialchars($chat['message']) ?>">
                                            <?= htmlspecialchars(substr($chat['message'], 0, 80)) ?>...
                                        </div>
                                    </td>
                                    <td class="py-3 text-center">
                                        <span class="badge bg-<?= $chat['sentiment_score'] < -0.5 ? 'danger' : 'warning' ?>">
                                            <?= round($chat['sentiment_score'], 2) ?>
                                        </span>
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
const salesData = <?= json_encode($salesTrend->fetch_all(MYSQLI_ASSOC)) ?>;
const sentimentData = <?= json_encode($sentimentData->fetch_all(MYSQLI_ASSOC)) ?>;
const segmentData = <?= json_encode($segments->fetch_all(MYSQLI_ASSOC)) ?>;

// Sales Trend Chart
new Chart(document.getElementById('salesTrendChart'), {
    type: 'line',
    data: {
        labels: salesData.map(d => d.date),
        datasets: [{
            label: 'Orders',
            data: salesData.map(d => d.orders),
            borderColor: '#e94560',
            backgroundColor: 'rgba(233, 69, 96, 0.1)',
            tension: 0.4,
            yAxisID: 'y'
        }, {
            label: 'Revenue (RWF)',
            data: salesData.map(d => d.revenue),
            borderColor: '#4caf50',
            backgroundColor: 'rgba(76, 175, 80, 0.1)',
            tension: 0.4,
            yAxisID: 'y1'
        }]
    },
    options: {
        responsive: true,
        interaction: {
            mode: 'index',
            intersect: false
        },
        scales: {
            y: {
                type: 'linear',
                display: true,
                position: 'left',
                title: { display: true, text: 'Orders' }
            },
            y1: {
                type: 'linear',
                display: true,
                position: 'right',
                title: { display: true, text: 'Revenue (RWF)' },
                grid: { drawOnChartArea: false }
            }
        }
    }
});

// Customer Segments Chart
new Chart(document.getElementById('segmentsChart'), {
    type: 'doughnut',
    data: {
        labels: segmentData.map(d => d.segment),
        datasets: [{
            data: segmentData.map(d => d.count),
            backgroundColor: ['#e94560', '#f5a623', '#4caf50']
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { position: 'bottom' }
        }
    }
});

// Sentiment Chart
new Chart(document.getElementById('sentimentChart'), {
    type: 'bar',
    data: {
        labels: sentimentData.map(d => d.date),
        datasets: [{
            label: 'Positive',
            data: sentimentData.map(d => d.positive),
            backgroundColor: '#4caf50'
        }, {
            label: 'Negative',
            data: sentimentData.map(d => d.negative),
            backgroundColor: '#e94560'
        }, {
            label: 'Escalated',
            data: sentimentData.map(d => d.escalated_count),
            backgroundColor: '#ff9800'
        }]
    },
    options: {
        responsive: true,
        scales: {
            x: { stacked: true },
            y: { stacked: true }
        }
    }
});

// Auto-refresh every 30 seconds
function refreshDashboard() {
    location.reload();
}
setInterval(refreshDashboard, 30000);
</script>

<?php require_once 'includes/admin_footer.php'; ?>
