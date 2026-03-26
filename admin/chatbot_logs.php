<?php require_once 'includes/admin_header.php'; ?>
<?php
// ── Stats ──
$totalMsgs  = $conn->query("SELECT COUNT(*) as c FROM chatbot_logs")->fetch_assoc()['c'];
$totalSess  = $conn->query("SELECT COUNT(DISTINCT session_id) as c FROM chatbot_logs WHERE session_id IS NOT NULL")->fetch_assoc()['c'];
$todayMsgs  = $conn->query("SELECT COUNT(*) as c FROM chatbot_logs WHERE DATE(created_at)=CURDATE()")->fetch_assoc()['c'];
$guestMsgs  = $conn->query("SELECT COUNT(*) as c FROM chatbot_logs WHERE is_guest=1")->fetch_assoc()['c'];

// ── Filters ──
$filterUser = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
$filterSess = isset($_GET['session_id']) ? trim($_GET['session_id']) : '';
$search     = isset($_GET['q']) ? trim($_GET['q']) : '';

// ── Sessions list ──
$where = "WHERE cl.session_id IS NOT NULL";
if ($filterUser) $where .= " AND cl.user_id = $filterUser";
if ($search)     $where .= " AND (cl.message LIKE '%" . $conn->real_escape_string($search) . "%' OR cl.response LIKE '%" . $conn->real_escape_string($search) . "%')";

$sessions = $conn->query("
    SELECT cl.session_id, cl.user_id, cl.is_guest,
           u.name AS user_name, u.email AS user_email,
           COUNT(cl.id) AS msg_count,
           MIN(cl.created_at) AS started_at,
           MAX(cl.created_at) AS last_at,
           (SELECT message FROM chatbot_logs WHERE session_id=cl.session_id ORDER BY created_at DESC LIMIT 1) AS last_message
    FROM chatbot_logs cl
    LEFT JOIN users u ON cl.user_id = u.id
    $where
    GROUP BY cl.session_id
    ORDER BY last_at DESC
    LIMIT 200
");

// ── Single session detail ──
$sessionDetail = [];
$detailUser    = null;
if ($filterSess) {
    $sid = $conn->real_escape_string($filterSess);
    $res = $conn->query("
        SELECT cl.*, u.name AS user_name, u.email AS user_email
        FROM chatbot_logs cl
        LEFT JOIN users u ON cl.user_id = u.id
        WHERE cl.session_id = '$sid'
        ORDER BY cl.created_at ASC
    ");
    while ($r = $res->fetch_assoc()) {
        $sessionDetail[] = $r;
        if (!$detailUser && $r['user_name']) $detailUser = $r;
    }
}
?>

<style>
.chat-bubble-user { background:#0d6efd;color:#fff;border-radius:18px 18px 4px 18px;padding:8px 14px;display:inline-block;max-width:80%; }
.chat-bubble-bot  { background:#f1f3f5;color:#212529;border-radius:18px 18px 18px 4px;padding:8px 14px;display:inline-block;max-width:80%; }
.session-row:hover { background:#f8f9fa;cursor:pointer; }
.ml-status-dot { width:10px;height:10px;border-radius:50%;display:inline-block;margin-right:6px; }
.ml-online  { background:#28a745; }
.ml-offline { background:#dc3545; }
.badge-guest { background:#6c757d; }
.badge-user  { background:#198754; }
.last-msg    { color:#6c757d;font-size:.82rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:260px; }
.drop-off-tag { font-size:.75rem;background:#fff3cd;color:#856404;border:1px solid #ffc107;border-radius:4px;padding:1px 6px; }
</style>

<div class="admin-content">

    <!-- Top bar -->
    <div class="admin-topbar mb-4">
        <h5 class="page-title">Chatbot Logs &amp; ML Performance</h5>
        <nav aria-label="breadcrumb"><ol class="breadcrumb mb-0"><li class="breadcrumb-item">Admin</li><li class="breadcrumb-item active">Chatbot</li></ol></nav>
    </div>

    <!-- ML Status Card -->
    <div class="admin-card mb-4" id="ml-panel">
        <div class="card-head">
            <h6><i class="bi bi-cpu me-2 text-primary"></i>ML Model Status &amp; Performance</h6>
            <button class="btn btn-sm btn-outline-primary" onclick="loadMLStatus()" style="border-radius:8px;font-size:.78rem">
                <i class="bi bi-arrow-clockwise me-1"></i>Refresh
            </button>
        </div>
        <div class="card-body-pad" id="ml-content">
            <div class="text-muted small"><i class="bi bi-hourglass me-2"></i>Checking ML API status...</div>
        </div>
    </div>

    <!-- Stats row -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="card text-center p-3">
                <div class="fs-3 fw-bold text-primary"><?= number_format($totalMsgs) ?></div>
                <small class="text-muted">Total Messages</small>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card text-center p-3">
                <div class="fs-3 fw-bold text-success"><?= number_format($totalSess) ?></div>
                <small class="text-muted">Conversations</small>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card text-center p-3">
                <div class="fs-3 fw-bold text-info"><?= number_format($todayMsgs) ?></div>
                <small class="text-muted">Today's Messages</small>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card text-center p-3">
                <div class="fs-3 fw-bold text-warning"><?= number_format($guestMsgs) ?></div>
                <small class="text-muted">Guest Messages</small>
            </div>
        </div>
    </div>

    <?php if ($filterSess && !empty($sessionDetail)): ?>
    <!-- CONVERSATION DETAIL VIEW -->
    <div class="d-flex align-items-center gap-2 mb-3">
        <a href="chatbot_logs.php" class="btn btn-sm btn-outline-secondary">← Back to all conversations</a>
        <button onclick="exportChatPDF()" class="btn btn-sm btn-dark ms-auto">
            <i class="bi bi-file-earmark-pdf me-1"></i>Export PDF
        </button>
        <h5 class="mb-0">
            Conversation:
            <?php if ($detailUser): ?>
                <strong><?= htmlspecialchars($detailUser['user_name']) ?></strong>
                <small class="text-muted">(<?= htmlspecialchars($detailUser['user_email']) ?>)</small>
            <?php else: ?>
                <span class="text-muted">Guest visitor</span>
            <?php endif; ?>
        </h5>
        <span class="badge bg-secondary ms-auto"><?= count($sessionDetail) ?> messages</span>
    </div>

    <div class="card p-4" style="max-height:70vh;overflow-y:auto" id="chatView">
        <?php foreach ($sessionDetail as $i => $msg): ?>
        <div class="mb-3">
            <div class="d-flex justify-content-end mb-1">
                <div>
                    <div class="chat-bubble-user"><?= htmlspecialchars($msg['message']) ?></div>
                    <div class="text-end" style="font-size:.72rem;color:#aaa;margin-top:2px">
                        <?= date('d M Y, H:i', strtotime($msg['created_at'])) ?>
                    </div>
                </div>
                <div class="ms-2 mt-1">
                    <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center" style="width:32px;height:32px;font-size:.8rem">
                        <?= $detailUser ? strtoupper(substr($detailUser['user_name'],0,1)) : 'G' ?>
                    </div>
                </div>
            </div>
            <div class="d-flex align-items-start mb-1">
                <div class="me-2 mt-1">
                    <div class="rounded-circle bg-dark text-white d-flex align-items-center justify-content-center" style="width:32px;height:32px;font-size:.8rem">🤖</div>
                </div>
                <div>
                    <div class="chat-bubble-bot"><?= $msg['response'] ?></div>
                </div>
            </div>
            <?php if ($i === count($sessionDetail) - 1): ?>
            <div class="text-center mt-3">
                <span class="drop-off-tag">⚠️ Customer stopped here — <?= date('d M Y, H:i', strtotime($msg['created_at'])) ?></span>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
    <script>var cv=document.getElementById('chatView');if(cv)cv.scrollTop=cv.scrollHeight;</script>

    <?php else: ?>
    <!-- SESSIONS LIST VIEW -->
    <div class="d-flex align-items-center gap-3 mb-3 flex-wrap">
        <h5 class="mb-0">All Conversations</h5>
        <div class="alert alert-info py-2 px-3 mb-0 small ms-auto" style="border-radius:8px">
            <i class="bi bi-info-circle me-1"></i>
            <strong>Guest</strong> = customer used chatbot without logging in.
            <strong>User</strong> = logged-in customer (name shown).
        </div>
    </div>
    <form class="d-flex gap-2 mb-3" method="GET">
        <input type="text" name="q" class="form-control form-control-sm" placeholder="Search messages..."
               value="<?= htmlspecialchars($search) ?>" style="width:220px">
        <button class="btn btn-sm btn-primary">Search</button>
        <?php if ($search): ?><a href="chatbot_logs.php" class="btn btn-sm btn-outline-secondary">Clear</a><?php endif; ?>
    </form>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-dark">
                    <tr>
                        <th>Customer</th>
                        <th>Messages</th>
                        <th>Last message (drop-off point)</th>
                        <th>Started</th>
                        <th>Last active</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($sessions->num_rows === 0): ?>
                    <tr><td colspan="6" class="text-center text-muted py-4">No conversations yet.</td></tr>
                <?php endif; ?>
                <?php while ($s = $sessions->fetch_assoc()): ?>
                <tr class="session-row" onclick="window.location='chatbot_logs.php?session_id=<?= urlencode($s['session_id']) ?>'">
                    <td>
                        <?php if ($s['user_name']): ?>
                            <span class="badge bg-success me-1">User</span>
                            <strong><?= htmlspecialchars($s['user_name']) ?></strong><br>
                            <small class="text-muted"><?= htmlspecialchars($s['user_email'] ?? '') ?></small>
                        <?php else: ?>
                            <span class="badge bg-secondary me-1">Guest</span>
                            <span class="text-muted">Anonymous visitor</span>
                        <?php endif; ?>
                    </td>
                    <td><span class="badge bg-primary rounded-pill"><?= $s['msg_count'] ?></span></td>
                    <td>
                        <div class="last-msg" title="<?= htmlspecialchars($s['last_message']) ?>">
                            💬 <?= htmlspecialchars(mb_substr($s['last_message'], 0, 80)) ?>
                        </div>
                    </td>
                    <td><small class="text-muted"><?= date('d M Y, H:i', strtotime($s['started_at'])) ?></small></td>
                    <td>
                        <small class="text-muted"><?= date('d M Y, H:i', strtotime($s['last_at'])) ?></small>
                        <?php
                        $mins = (time() - strtotime($s['last_at'])) / 60;
                        if ($mins < 30)   echo '<br><span class="badge bg-success" style="font-size:.7rem">Active</span>';
                        elseif ($mins < 1440) echo '<br><span class="badge bg-warning text-dark" style="font-size:.7rem">Today</span>';
                        ?>
                    </td>
                    <td>
                        <a href="chatbot_logs.php?session_id=<?= urlencode($s['session_id']) ?>"
                           class="btn btn-sm btn-outline-primary" onclick="event.stopPropagation()">View chat</a>
                    </td>
                </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
async function loadMLStatus() {
    const el = document.getElementById('ml-content');
    el.innerHTML = '<div class="text-muted small"><i class="bi bi-hourglass me-2"></i>Checking ML API...</div>';
    try {
        const res  = await fetch('<?= SITE_URL ?>/api/ml_status.php');
        const data = await res.json();
        if (!data.ml_online) {
            el.innerHTML = `<div class="d-flex align-items-center gap-2 mb-2">
                <span class="ml-status-dot ml-offline"></span>
                <strong class="text-danger">ML API Offline</strong></div>
                <p class="text-muted small mb-0">${data.message}</p>`;
            return;
        }
        const h = data.health;
        const p = data.performance?.results || {};
        const models = Object.keys(p);
        let modelRows = models.map(m =>
            `<tr><td><strong>${m}</strong></td>
             <td><span class="badge" style="background:#0f3460">${(p[m].accuracy*100).toFixed(1)}%</span></td>
             <td><span class="badge" style="background:#e94560">${(p[m].f1*100).toFixed(1)}%</span></td>
             <td>${m === data.performance?.best_model ? '<span class="badge bg-success">✓ Best</span>' : ''}</td></tr>`
        ).join('');
        el.innerHTML = `
            <div class="d-flex align-items-center gap-3 mb-3 flex-wrap">
                <div><span class="ml-status-dot ml-online"></span><strong class="text-success">ML API Online</strong></div>
                <span class="badge bg-info text-dark">Best: ${h.best_model}</span>
                <span class="badge bg-primary">${h.intents} intents</span>
            </div>
            ${models.length ? `<div class="table-responsive">
                <table class="table table-sm"><thead><tr><th>Model</th><th>Accuracy</th><th>F1 Score</th><th></th></tr></thead>
                <tbody>${modelRows}</tbody></table></div>
                <canvas id="mlChart" height="60"></canvas>` : ''}`;
        if (models.length) {
            new Chart(document.getElementById('mlChart'), {
                type: 'bar',
                data: {
                    labels: models,
                    datasets: [
                        { label: 'Accuracy (%)', data: models.map(m=>(p[m].accuracy*100).toFixed(1)), backgroundColor:'#0f3460', borderRadius:6 },
                        { label: 'F1 Score (%)', data: models.map(m=>(p[m].f1*100).toFixed(1)),       backgroundColor:'#e94560', borderRadius:6 },
                    ]
                },
                options: { responsive:true, plugins:{legend:{position:'top'}}, scales:{y:{beginAtZero:true,max:100,ticks:{callback:v=>v+'%'}}} }
            });
        }
    } catch(e) {
        document.getElementById('ml-content').innerHTML =
            '<div class="text-danger small"><i class="bi bi-exclamation-circle me-2"></i>Could not reach ML API. Make sure Flask is running on port 5000.</div>';
    }
}
loadMLStatus();
</script>

<script>
function exportChatPDF() {
    const cv = document.getElementById('chatView');
    if (!cv) return;
    const w = window.open('', '_blank');
    w.document.write(`<!DOCTYPE html><html><head><meta charset="UTF-8">
        <title>Chat Export</title>
        <style>
            body{font-family:Arial,sans-serif;padding:30px;color:#333;font-size:13px}
            h2{color:#0f3460;border-bottom:2px solid #e94560;padding-bottom:8px}
            .user-msg{background:#0d6efd;color:#fff;border-radius:12px 12px 4px 12px;padding:8px 14px;display:inline-block;max-width:70%;margin:4px 0}
            .bot-msg{background:#f1f3f5;color:#212529;border-radius:12px 12px 12px 4px;padding:8px 14px;display:inline-block;max-width:70%;margin:4px 0}
            .msg-row{margin-bottom:12px}
            .time{font-size:10px;color:#aaa;margin-top:2px}
            .right{text-align:right}
            @media print{body{padding:10px}}
        </style></head><body>
        <h2>💬 Chat Conversation Export</h2>
        <p style="color:#888;font-size:12px">Exported on ${new Date().toLocaleString()} | <?= SITE_NAME ?></p>
        <hr>
        ${cv.innerHTML}
        </body></html>`);
    w.document.close();
    setTimeout(() => w.print(), 500);
}
</script>

<?php require_once 'includes/admin_footer.php'; ?>
