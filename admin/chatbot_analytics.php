<?php require_once 'includes/admin_header.php'; ?>
<?php
// ── Hourly message distribution ──
$hourly_res = $conn->query("SELECT HOUR(created_at) as hr, COUNT(*) as c FROM chatbot_logs GROUP BY hr ORDER BY hr");
$hourly_labels = []; $hourly_data = [];
while ($r = $hourly_res->fetch_assoc()) {
    $hourly_labels[] = sprintf('%02d:00', $r['hr']);
    $hourly_data[] = (int)$r['c'];
}

// ── Daily conversation volume (last 14 days) ──
$daily_labels = []; $daily_data = []; $daily_guest = []; $daily_auth = [];
for ($d = 13; $d >= 0; $d--) {
    $date = date('Y-m-d', strtotime("-$d days"));
    $daily_labels[] = date('M d', strtotime("-$d days"));
    $res = $conn->query("SELECT COUNT(*) as c FROM chatbot_logs WHERE DATE(created_at)='$date'");
    $daily_data[] = (int)$res->fetch_assoc()['c'];
    $gres = $conn->query("SELECT COUNT(*) as c FROM chatbot_logs WHERE DATE(created_at)='$date' AND is_guest=1");
    $daily_guest[] = (int)$gres->fetch_assoc()['c'];
    $ares = $conn->query("SELECT COUNT(*) as c FROM chatbot_logs WHERE DATE(created_at)='$date' AND is_guest=0");
    $daily_auth[] = (int)$ares->fetch_assoc()['c'];
}

// ── Satisfaction ratings ──
$rating_res = $conn->query("SELECT
    COALESCE(SUM(CASE WHEN rating=1 THEN 1 ELSE 0 END),0) as thumbs_up,
    COALESCE(SUM(CASE WHEN rating=0 THEN 1 ELSE 0 END),0) as thumbs_down,
    COUNT(*) as total
    FROM chatbot_feedback");
$ratings = $rating_res->fetch_assoc();
$thumbs_up   = (int)$ratings['thumbs_up'];
$thumbs_down = (int)$ratings['thumbs_down'];
$total_ratings = $thumbs_up + $thumbs_down;
$satisfaction  = $total_ratings > 0 ? round($thumbs_up / $total_ratings * 100, 1) : 0;

// ── Daily satisfaction trend (last 7 days) ──
$sat_labels = []; $sat_data = [];
for ($d = 6; $d >= 0; $d--) {
    $date = date('Y-m-d', strtotime("-$d days"));
    $sat_labels[] = date('M d', strtotime("-$d days"));
    $sres = $conn->query("SELECT
        COALESCE(SUM(CASE WHEN rating=1 THEN 1 ELSE 0 END),0) as up,
        COALESCE(SUM(CASE WHEN rating=0 THEN 1 ELSE 0 END),0) as down
        FROM chatbot_feedback WHERE DATE(created_at)='$date'");
    $s = $sres->fetch_assoc();
    $st = (int)$s['up'] + (int)$s['down'];
    $sat_data[] = $st > 0 ? round((int)$s['up'] / $st * 100, 1) : 0;
}

// ── Recent feedback entries ──
$recent_feedback = $conn->query("SELECT f.*, l.message, LEFT(l.response,200) as response_preview
    FROM chatbot_feedback f
    LEFT JOIN chatbot_logs l ON f.log_id = l.id
    ORDER BY f.created_at DESC LIMIT 20");

// ── Low stock products ──
$low_stock = $conn->query("SELECT id, name, stock, price FROM products WHERE stock > 0 AND stock <= 5 ORDER BY stock ASC LIMIT 15");

// ── Chatbot total stats ──
$chat_total    = $conn->query("SELECT COUNT(*) as c FROM chatbot_logs")->fetch_assoc()['c'];
$chat_today    = $conn->query("SELECT COUNT(*) as c FROM chatbot_logs WHERE DATE(created_at)=CURDATE()")->fetch_assoc()['c'];
$chat_sessions = $conn->query("SELECT COUNT(DISTINCT session_id) as c FROM chatbot_logs WHERE session_id IS NOT NULL")->fetch_assoc()['c'];
$chat_avg_len = $conn->query("SELECT COALESCE(AVG(CHAR_LENGTH(response)),0) as avg FROM chatbot_logs")->fetch_assoc()['avg'];

// ── Top intents by volume ──
$top_intents = $conn->query("SELECT sentiment_label, COUNT(*) as c FROM chatbot_logs WHERE sentiment_label IS NOT NULL GROUP BY sentiment_label ORDER BY c DESC");
$sentiment_labels = []; $sentiment_data = []; $sentiment_colors = ['#2ecc71','#f5a623','#e94560'];
$si = 0;
while ($r = $top_intents->fetch_assoc()) {
    $sentiment_labels[] = ucfirst($r['sentiment_label']);
    $sentiment_data[]   = (int)$r['c'];
}
?>
<div class="admin-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0"><i class="bi bi-bar-chart-line me-2"></i>Chatbot Analytics</h2>
        <span class="text-muted" style="font-size:.85rem">Last updated: <?= date('M d, Y H:i') ?></span>
    </div>

    <!-- Stats Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-3"><div class="card p-3 text-center shadow-sm">
            <div class="text-muted small">Total Conversations</div>
            <div class="fs-3 fw-bold" style="color:#0f3460"><?= number_format($chat_total) ?></div>
        </div></div>
        <div class="col-md-3"><div class="card p-3 text-center shadow-sm">
            <div class="text-muted small">Today</div>
            <div class="fs-3 fw-bold" style="color:#e94560"><?= number_format($chat_today) ?></div>
        </div></div>
        <div class="col-md-3"><div class="card p-3 text-center shadow-sm">
            <div class="text-muted small">Satisfaction</div>
            <div class="fs-3 fw-bold" style="color:#2ecc71"><?= $satisfaction ?>%</div>
        </div></div>
        <div class="col-md-3"><div class="card p-3 text-center shadow-sm">
            <div class="text-muted small">Avg Response Length</div>
            <div class="fs-3 fw-bold" style="color:#f5a623"><?= number_format($chat_avg_len, 0) ?> chars</div>
        </div></div>
    </div>

    <div class="row g-3 mb-4">
        <!-- Daily Volume Chart -->
        <div class="col-md-8"><div class="card p-3 shadow-sm">
            <h5 class="mb-3"><i class="bi bi-graph-up me-2"></i>Daily Conversations (14 days)</h5>
            <canvas id="dailyChart" height="200"></canvas>
        </div></div>
        <!-- Satisfaction Trend -->
        <div class="col-md-4"><div class="card p-3 shadow-sm">
            <h5 class="mb-3"><i class="bi bi-emoji-smile me-2"></i>Satisfaction Trend (7 days)</h5>
            <canvas id="satChart" height="200"></canvas>
        </div></div>
    </div>

    <div class="row g-3 mb-4">
        <!-- Hourly Activity Distribution -->
        <div class="col-md-5"><div class="card p-3 shadow-sm">
            <h5 class="mb-3"><i class="bi bi-clock me-2"></i>Hourly Activity</h5>
            <canvas id="hourlyChart" height="220"></canvas>
        </div></div>
        <!-- Sentiment Distribution -->
        <div class="col-md-3"><div class="card p-3 shadow-sm">
            <h5 class="mb-3"><i class="bi bi-emoji-neutral me-2"></i>Sentiment</h5>
            <canvas id="sentimentChart" height="220"></canvas>
        </div></div>
        <!-- Low Stock Alerts -->
        <div class="col-md-4"><div class="card p-3 shadow-sm">
            <h5 class="mb-3"><i class="bi bi-exclamation-triangle me-2" style="color:#e94560"></i>Low Stock Alerts</h5>
            <?php if ($low_stock && $low_stock->num_rows > 0): ?>
            <div style="max-height:220px;overflow-y:auto">
                <table class="table table-sm table-hover mb-0">
                    <thead><tr><th>Product</th><th>Stock</th><th>Price</th></tr></thead>
                    <tbody>
                        <?php while ($p = $low_stock->fetch_assoc()): ?>
                        <tr>
                            <td><a href="<?= SITE_URL ?>/product.php?id=<?= $p['id'] ?>" target="_blank" style="color:#e94560;text-decoration:none;font-weight:500"><?= htmlspecialchars($p['name']) ?></a></td>
                            <td><span class="badge bg-<?= $p['stock'] <= 2 ? 'danger' : 'warning' ?>"><?= $p['stock'] ?></span></td>
                            <td>RWF <?= number_format((float)$p['price']) ?></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <p class="text-muted mb-0">All products have sufficient stock ✅</p>
            <?php endif; ?>
        </div></div>
    </div>

    <!-- Ratings Summary -->
    <div class="row g-3">
        <div class="col-md-6"><div class="card p-3 shadow-sm">
            <h5 class="mb-3"><i class="bi bi-hand-thumbs-up me-2"></i>Feedback Summary</h5>
            <?php if ($total_ratings > 0): ?>
            <div class="d-flex align-items-center gap-4">
                <div class="text-center">
                    <div class="fs-1 fw-bold" style="color:#2ecc71"><?= $thumbs_up ?></div>
                    <div class="text-muted small">👍 Helpful</div>
                </div>
                <div class="text-center">
                    <div class="fs-1 fw-bold" style="color:#e94560"><?= $thumbs_down ?></div>
                    <div class="text-muted small">👎 Not Helpful</div>
                </div>
                <div class="flex-grow-1">
                    <div class="progress" style="height:24px;border-radius:12px">
                        <div class="progress-bar bg-success" style="width:<?= $satisfaction ?>%"><?= $satisfaction ?>%</div>
                    </div>
                    <div class="text-muted small mt-1"><?= number_format($total_ratings) ?> total ratings</div>
                </div>
            </div>
            <?php else: ?>
            <p class="text-muted mb-0">No ratings collected yet.</p>
            <?php endif; ?>
        </div></div>
        <div class="col-md-6"><div class="card p-3 shadow-sm">
            <h5 class="mb-3"><i class="bi bi-people me-2"></i>Quick Stats</h5>
            <div class="row text-center">
                <div class="col-4">
                    <div class="fs-4 fw-bold"><?= number_format($chat_sessions) ?></div>
                    <div class="text-muted small">Sessions</div>
                </div>
                <div class="col-4">
                    <div class="fs-4 fw-bold"><?= number_format($chat_total > 0 ? round($chat_total / max($chat_sessions, 1), 1) : 0) ?></div>
                    <div class="text-muted small">Avg Msgs/Session</div>
                </div>
                <div class="col-4">
                    <div class="fs-4 fw-bold"><?= $satisfaction ?>%</div>
                    <div class="text-muted small">Satisfaction</div>
                </div>
            </div>
        </div></div>
    </div>

    <!-- Recent Feedback -->
    <div class="row g-3">
        <div class="col-12"><div class="card p-3 shadow-sm">
            <h5 class="mb-3"><i class="bi bi-chat-square-quote me-2"></i>Recent Feedback</h5>
            <?php if ($recent_feedback && $recent_feedback->num_rows > 0): ?>
            <div style="max-height:400px;overflow-y:auto">
                <table class="table table-sm table-hover mb-0">
                    <thead><tr><th>Date</th><th>Rating</th><th>User Msg</th><th>Bot Response</th><th>Comment</th></tr></thead>
                    <tbody>
                        <?php while ($fb = $recent_feedback->fetch_assoc()): ?>
                        <tr>
                            <td class="text-nowrap small"><?= date('M d, H:i', strtotime($fb['created_at'])) ?></td>
                            <td><?= (int)$fb['rating'] === 1 ? '<span style="color:#2ecc71">👍</span>' : '<span style="color:#e94560">👎</span>' ?></td>
                            <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="<?= htmlspecialchars($fb['message'] ?? '') ?>"><?= htmlspecialchars(mb_substr($fb['message'] ?? '', 0, 60)) ?></td>
                            <td style="max-width:250px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="<?= htmlspecialchars($fb['response_preview'] ?? '') ?>"><?= htmlspecialchars(mb_substr($fb['response_preview'] ?? '', 0, 80)) ?></td>
                            <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= htmlspecialchars($fb['comment'] ?? '') ?: '<span class="text-muted">—</span>' ?></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <p class="text-muted mb-0">No feedback collected yet.</p>
            <?php endif; ?>
        </div></div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js" integrity="sha384-e6nUZLBkQ86NJ6TVVKAeSaK8jWa3NhkYWZFomE39AvDbQWeie9PlQqM3pmYW5d1g" crossorigin="anonymous"></script>
<script>
// ── Daily Volume ──
new Chart(document.getElementById('dailyChart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode($daily_labels) ?>,
        datasets: [
            { label: 'Total', data: <?= json_encode($daily_data) ?>, backgroundColor: '#0f3460', borderRadius: 4 },
            { label: 'Guest', data: <?= json_encode($daily_guest) ?>, backgroundColor: '#f5a623', borderRadius: 4 },
            { label: 'Authenticated', data: <?= json_encode($daily_auth) ?>, backgroundColor: '#2ecc71', borderRadius: 4 },
        ]
    },
    options: { responsive: true, plugins: { legend: { position: 'top', labels: { boxWidth: 12 } } },
        scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } } }
});

// ── Satisfaction Trend ──
new Chart(document.getElementById('satChart'), {
    type: 'line',
    data: {
        labels: <?= json_encode($sat_labels) ?>,
        datasets: [{
            label: 'Satisfaction %', data: <?= json_encode($sat_data) ?>,
            borderColor: '#2ecc71', backgroundColor: 'rgba(46,204,113,0.1)',
            fill: true, tension: 0.3, pointBackgroundColor: '#2ecc71'
        }]
    },
    options: { responsive: true, plugins: { legend: { display: false } },
        scales: { y: { min: 0, max: 100, ticks: { callback: v => v + '%' } } } }
});

// ── Hourly Activity (Bar) ──
new Chart(document.getElementById('hourlyChart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode($hourly_labels) ?>,
        datasets: [{ label: 'Messages', data: <?= json_encode($hourly_data) ?>, backgroundColor: '#0f3460', borderRadius: 4 }]
    },
    options: { responsive: true, plugins: { legend: { display: false } },
        scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } } }
});

// ── Sentiment (Doughnut) ──
new Chart(document.getElementById('sentimentChart'), {
    type: 'doughnut',
    data: {
        labels: <?= json_encode($sentiment_labels) ?>,
        datasets: [{ data: <?= json_encode($sentiment_data) ?>, backgroundColor: <?= json_encode($sentiment_colors) ?> }]
    },
    options: { responsive: true, plugins: { legend: { position: 'bottom', labels: { boxWidth: 12 } } } }
});
</script>

<?php require_once 'includes/admin_footer.php'; ?>
