<?php require_once 'includes/admin_header.php'; ?>
<?php
// ── Core stats ──
$totalMsgs   = $conn->query("SELECT COUNT(*) as c FROM chatbot_logs")->fetch_assoc()['c'];
$totalSess   = $conn->query("SELECT COUNT(DISTINCT session_id) as c FROM chatbot_logs")->fetch_assoc()['c'];
$guestMsgs   = $conn->query("SELECT COUNT(*) as c FROM chatbot_logs WHERE is_guest=1")->fetch_assoc()['c'];
$userMsgs    = $totalMsgs - $guestMsgs;
$todayMsgs   = $conn->query("SELECT COUNT(*) as c FROM chatbot_logs WHERE DATE(created_at)=CURDATE()")->fetch_assoc()['c'];
$weekMsgs    = $conn->query("SELECT COUNT(*) as c FROM chatbot_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetch_assoc()['c'];

// ── Satisfaction ratings ──
$totalRatings = $conn->query("SELECT COUNT(*) as c FROM chatbot_ratings")->fetch_assoc()['c'];
$thumbsUp     = $conn->query("SELECT COUNT(*) as c FROM chatbot_ratings WHERE rating=1")->fetch_assoc()['c'];
$satisfaction = $totalRatings > 0 ? round(($thumbsUp / $totalRatings) * 100) : 0;

// ── Top messages (most asked) ──
$topMsgs = $conn->query("SELECT message, COUNT(*) as cnt FROM chatbot_logs GROUP BY message ORDER BY cnt DESC LIMIT 10");

// ── Messages per day (last 14 days) ──
$dailyRes = $conn->query("SELECT DATE(created_at) as day, COUNT(*) as cnt FROM chatbot_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 14 DAY) GROUP BY DATE(created_at) ORDER BY day ASC");
$dailyLabels = []; $dailyData = [];
while ($r = $dailyRes->fetch_assoc()) { $dailyLabels[] = date('d M', strtotime($r['day'])); $dailyData[] = $r['cnt']; }

// ── Busiest hours ──
$hourRes = $conn->query("SELECT HOUR(created_at) as hr, COUNT(*) as cnt FROM chatbot_logs GROUP BY HOUR(created_at) ORDER BY hr ASC");
$hourLabels = []; $hourData = [];
while ($r = $hourRes->fetch_assoc()) { $hourLabels[] = $r['hr'] . ':00'; $hourData[] = $r['cnt']; }

// ── Guest vs Registered ──
$guestPct = $totalMsgs > 0 ? round(($guestMsgs / $totalMsgs) * 100) : 0;
$userPct  = 100 - $guestPct;

// ── Support tickets ──
$openTickets = $conn->query("SELECT COUNT(*) as c FROM support_tickets WHERE status='open'")->fetch_assoc()['c'];

// ── Stock notifications ──
$stockNotifs = $conn->query("SELECT COUNT(*) as c FROM stock_notifications WHERE notified=0")->fetch_assoc()['c'];
?>

<div class="admin-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4><i class="bi bi-bar-chart-line me-2"></i>Chatbot Analytics</h4>
        <small class="text-muted">Real-time data from your chatbot conversations</small>
    </div>

    <!-- KPI Cards -->
    <div class="row g-3 mb-4">
        <?php $kpis = [
            ['Total Messages',    $totalMsgs,   'bi-chat-dots',    '#0f3460', '#e8f0fe'],
            ['Conversations',     $totalSess,   'bi-people',       '#198754', '#e8f5e9'],
            ['Today\'s Messages', $todayMsgs,   'bi-calendar-day', '#0dcaf0', '#e0f7fa'],
            ['This Week',         $weekMsgs,    'bi-calendar-week','#6f42c1', '#f3e5f5'],
            ['Guest Messages',    $guestMsgs,   'bi-person',       '#6c757d', '#f8f9fa'],
            ['Registered Users',  $userMsgs,    'bi-person-check', '#198754', '#e8f5e9'],
            ['Satisfaction',      $satisfaction.'%','bi-hand-thumbs-up','#f5a623','#fff8e1'],
            ['Open Tickets',      $openTickets, 'bi-headset',      '#dc3545', '#fdecea'],
        ]; ?>
        <?php foreach ($kpis as [$label, $val, $icon, $color, $bg]): ?>
        <div class="col-6 col-md-3">
            <div class="card p-3 h-100" style="border-left:4px solid <?= $color ?>">
                <div class="d-flex align-items-center gap-3">
                    <div style="width:44px;height:44px;background:<?= $bg ?>;border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0">
                        <i class="bi <?= $icon ?>" style="color:<?= $color ?>;font-size:1.2rem"></i>
                    </div>
                    <div>
                        <div style="font-size:1.4rem;font-weight:700;color:<?= $color ?>"><?= $val ?></div>
                        <div style="font-size:.78rem;color:#888"><?= $label ?></div>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="row g-4 mb-4">
        <!-- Messages per day chart -->
        <div class="col-lg-8">
            <div class="card p-4 h-100">
                <h6 class="mb-3"><i class="bi bi-graph-up me-2 text-primary"></i>Messages per Day (Last 14 Days)</h6>
                <canvas id="dailyChart" height="80"></canvas>
            </div>
        </div>
        <!-- Guest vs Registered pie -->
        <div class="col-lg-4">
            <div class="card p-4 h-100">
                <h6 class="mb-3"><i class="bi bi-pie-chart me-2 text-success"></i>Guest vs Registered</h6>
                <canvas id="pieChart" height="160"></canvas>
                <div class="d-flex justify-content-center gap-4 mt-3" style="font-size:.82rem">
                    <span><span style="color:#6c757d">●</span> Guest <?= $guestPct ?>%</span>
                    <span><span style="color:#198754">●</span> Registered <?= $userPct ?>%</span>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <!-- Busiest hours -->
        <div class="col-lg-6">
            <div class="card p-4">
                <h6 class="mb-3"><i class="bi bi-clock me-2 text-warning"></i>Busiest Hours</h6>
                <canvas id="hourChart" height="100"></canvas>
            </div>
        </div>
        <!-- Satisfaction -->
        <div class="col-lg-6">
            <div class="card p-4">
                <h6 class="mb-3"><i class="bi bi-hand-thumbs-up me-2 text-success"></i>Response Satisfaction</h6>
                <?php if ($totalRatings > 0): ?>
                <div class="text-center mb-3">
                    <div style="font-size:3rem;font-weight:700;color:<?= $satisfaction >= 70 ? '#198754' : ($satisfaction >= 50 ? '#f5a623' : '#dc3545') ?>">
                        <?= $satisfaction ?>%
                    </div>
                    <div class="text-muted">Based on <?= $totalRatings ?> ratings</div>
                </div>
                <div class="progress" style="height:20px;border-radius:10px">
                    <div class="progress-bar bg-success" style="width:<?= $satisfaction ?>%"><?= $thumbsUp ?> 👍</div>
                    <div class="progress-bar bg-danger" style="width:<?= 100-$satisfaction ?>%"><?= $totalRatings-$thumbsUp ?> 👎</div>
                </div>
                <?php else: ?>
                <div class="text-center text-muted py-4">
                    <i class="bi bi-hand-thumbs-up" style="font-size:2rem;opacity:.3"></i>
                    <p class="mt-2">No ratings yet. Ratings appear after customers use 👍/👎 buttons.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Top questions -->
    <div class="card p-4">
        <h6 class="mb-3"><i class="bi bi-question-circle me-2 text-info"></i>Top 10 Most Asked Questions</h6>
        <?php if ($topMsgs->num_rows > 0): ?>
        <div class="table-responsive">
            <table class="table table-sm table-hover">
                <thead class="table-dark"><tr><th>#</th><th>Message</th><th>Times Asked</th><th>Frequency</th></tr></thead>
                <tbody>
                <?php $rank=1; $maxCnt=null; while ($r = $topMsgs->fetch_assoc()):
                    if (!$maxCnt) $maxCnt = $r['cnt'];
                    $pct = $maxCnt > 0 ? round(($r['cnt']/$maxCnt)*100) : 0;
                ?>
                <tr>
                    <td><span class="badge bg-secondary"><?= $rank++ ?></span></td>
                    <td><?= htmlspecialchars(mb_substr($r['message'], 0, 80)) ?></td>
                    <td><strong><?= $r['cnt'] ?></strong></td>
                    <td style="width:150px">
                        <div class="progress" style="height:8px">
                            <div class="progress-bar bg-primary" style="width:<?= $pct ?>%"></div>
                        </div>
                    </td>
                </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <p class="text-muted">No data yet.</p>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
// Daily chart
new Chart(document.getElementById('dailyChart'), {
    type: 'line',
    data: {
        labels: <?= json_encode($dailyLabels) ?>,
        datasets: [{ label: 'Messages', data: <?= json_encode($dailyData) ?>,
            borderColor:'#0f3460', backgroundColor:'rgba(15,52,96,.1)',
            fill:true, tension:.4, pointRadius:4, pointBackgroundColor:'#0f3460' }]
    },
    options: { responsive:true, plugins:{legend:{display:false}}, scales:{y:{beginAtZero:true,ticks:{stepSize:1}}} }
});

// Pie chart
new Chart(document.getElementById('pieChart'), {
    type: 'doughnut',
    data: {
        labels: ['Guest', 'Registered'],
        datasets: [{ data: [<?= $guestPct ?>, <?= $userPct ?>],
            backgroundColor: ['#6c757d','#198754'], borderWidth:0 }]
    },
    options: { responsive:true, plugins:{legend:{display:false}}, cutout:'65%' }
});

// Hour chart
new Chart(document.getElementById('hourChart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode($hourLabels) ?>,
        datasets: [{ label: 'Messages', data: <?= json_encode($hourData) ?>,
            backgroundColor:'#f5a623', borderRadius:4 }]
    },
    options: { responsive:true, plugins:{legend:{display:false}}, scales:{y:{beginAtZero:true}} }
});
</script>

<?php require_once 'includes/admin_footer.php'; ?>
