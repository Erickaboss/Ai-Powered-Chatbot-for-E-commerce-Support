<?php require_once 'includes/admin_header.php'; ?>
<?php
// ── Core stats ────────────────────────────────────────────────
$total_products = $conn->query("SELECT COUNT(*) as c FROM products")->fetch_assoc()['c'];
$total_orders   = $conn->query("SELECT COUNT(*) as c FROM orders")->fetch_assoc()['c'];
$total_users    = $conn->query("SELECT COUNT(*) as c FROM users WHERE role='customer'")->fetch_assoc()['c'];
$total_revenue  = $conn->query("SELECT COALESCE(SUM(total_price),0) as s FROM orders WHERE status != 'cancelled'")->fetch_assoc()['s'];

// ── New customers this week ───────────────────────────────────
$new_customers  = $conn->query("SELECT COUNT(*) as c FROM users WHERE role='customer' AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetch_assoc()['c'];

// ── Order status breakdown ────────────────────────────────────
$status_res = $conn->query("SELECT status, COUNT(*) as c FROM orders GROUP BY status");
$order_statuses = ['pending'=>0,'processing'=>0,'shipped'=>0,'delivered'=>0,'cancelled'=>0];
while ($r = $status_res->fetch_assoc()) {
    if (isset($order_statuses[$r['status']])) $order_statuses[$r['status']] = (int)$r['c'];
}

// ── Revenue last 7 days ───────────────────────────────────────
$revenue_labels = [];
$revenue_data   = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $label = date('M d', strtotime("-$i days"));
    $revenue_labels[] = $label;
    $res = $conn->query("SELECT COALESCE(SUM(total_price),0) as s FROM orders WHERE DATE(created_at)='$date' AND status != 'cancelled'");
    $revenue_data[] = (float)$res->fetch_assoc()['s'];
}

// ── Chatbot stats ─────────────────────────────────────────────
$chat_total    = $conn->query("SELECT COUNT(*) as c FROM chatbot_logs")->fetch_assoc()['c'];
$chat_today    = $conn->query("SELECT COUNT(*) as c FROM chatbot_logs WHERE DATE(created_at)=CURDATE()")->fetch_assoc()['c'];
$chat_sessions = $conn->query("SELECT COUNT(DISTINCT session_id) as c FROM chatbot_logs WHERE session_id IS NOT NULL")->fetch_assoc()['c'];
$chat_guests   = $conn->query("SELECT COUNT(DISTINCT session_id) as c FROM chatbot_logs WHERE is_guest=1 AND session_id IS NOT NULL")->fetch_assoc()['c'];

// ── Top selling products ──────────────────────────────────────
$top_products = $conn->query("
    SELECT p.name, SUM(oi.quantity) as sold, SUM(oi.quantity * oi.price) as revenue
    FROM order_items oi
    JOIN products p ON oi.product_id = p.id
    JOIN orders o ON oi.order_id = o.id
    WHERE o.status != 'cancelled'
    GROUP BY oi.product_id
    ORDER BY sold DESC
    LIMIT 5
");

// ── Recent orders ─────────────────────────────────────────────
$recent_orders = $conn->query("SELECT o.*, u.name as uname FROM orders o JOIN users u ON o.user_id=u.id ORDER BY o.created_at DESC LIMIT 8");
?>

<div class="admin-content">
    <h4 class="mb-4">Dashboard</h4>

    <?php
    // Low stock warning banner
    $ls = $conn->query("SELECT id, name, stock FROM products WHERE stock > 0 AND stock <= 5 ORDER BY stock ASC LIMIT 5");
    $lsItems = $ls ? $ls->fetch_all(MYSQLI_ASSOC) : [];
    if (!empty($lsItems)):
    ?>
    <div class="alert alert-warning d-flex align-items-start gap-3 mb-4" style="border-radius:12px;border:none;background:#fff3cd">
        <i class="bi bi-exclamation-triangle-fill text-warning fs-4 mt-1"></i>
        <div>
            <strong>⚠️ Low Stock Alert:</strong>
            <?php foreach ($lsItems as $ls): ?>
            <span class="badge bg-warning text-dark me-1"><?= htmlspecialchars($ls['name']) ?> (<?= $ls['stock'] ?> left)</span>
            <?php endforeach; ?>
            <a href="products.php" class="ms-2 small fw-600">Manage →</a>
        </div>
    </div>
    <?php endif; ?>

    <!-- ── Stat Cards ── -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="card text-white bg-primary p-3 h-100">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="fs-4 fw-bold"><?= number_format($total_products) ?></div>
                        <div class="small">Products</div>
                    </div>
                    <i class="bi bi-box-seam fs-1 opacity-50"></i>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card text-white bg-success p-3 h-100">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="fs-4 fw-bold"><?= number_format($total_orders) ?></div>
                        <div class="small">Total Orders</div>
                    </div>
                    <i class="bi bi-bag fs-1 opacity-50"></i>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card text-white bg-warning p-3 h-100">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="fs-4 fw-bold"><?= number_format($total_users) ?></div>
                        <div class="small">Customers <span class="badge bg-dark ms-1">+<?= $new_customers ?> this week</span></div>
                    </div>
                    <i class="bi bi-people fs-1 opacity-50"></i>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card text-white bg-danger p-3 h-100">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="fs-4 fw-bold">RWF <?= number_format($total_revenue, 0) ?></div>
                        <div class="small">Total Revenue</div>
                    </div>
                    <i class="bi bi-cash-stack fs-1 opacity-50"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Revenue Chart + Order Status ── -->
    <div class="row g-3 mb-4">
        <div class="col-md-8">
            <div class="card p-4 h-100">
                <h6 class="mb-3 fw-semibold"><i class="bi bi-bar-chart-line me-2 text-primary"></i>Revenue — Last 7 Days</h6>
                <canvas id="revenueChart" height="100"></canvas>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card p-4 h-100">
                <h6 class="mb-3 fw-semibold"><i class="bi bi-pie-chart me-2 text-success"></i>Order Status</h6>
                <canvas id="statusChart" height="180"></canvas>
                <div class="mt-3">
                    <?php
                    $status_colors = ['pending'=>'warning','processing'=>'info','shipped'=>'primary','delivered'=>'success','cancelled'=>'danger'];
                    foreach ($order_statuses as $s => $c):
                    ?>
                    <div class="d-flex justify-content-between small mb-1">
                        <span><span class="badge bg-<?= $status_colors[$s] ?> me-1">&nbsp;</span><?= ucfirst($s) ?></span>
                        <strong><?= $c ?></strong>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Chatbot Stats ── -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="card p-3 border-start border-4 border-info">
                <div class="small text-muted">Total Messages</div>
                <div class="fs-4 fw-bold text-info"><?= number_format($chat_total) ?></div>
                <i class="bi bi-chat-dots text-info opacity-25" style="font-size:2rem;position:absolute;right:12px;top:12px"></i>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card p-3 border-start border-4 border-primary">
                <div class="small text-muted">Messages Today</div>
                <div class="fs-4 fw-bold text-primary"><?= number_format($chat_today) ?></div>
                <i class="bi bi-chat-text text-primary opacity-25" style="font-size:2rem;position:absolute;right:12px;top:12px"></i>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card p-3 border-start border-4 border-success">
                <div class="small text-muted">Total Sessions</div>
                <div class="fs-4 fw-bold text-success"><?= number_format($chat_sessions) ?></div>
                <i class="bi bi-people text-success opacity-25" style="font-size:2rem;position:absolute;right:12px;top:12px"></i>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card p-3 border-start border-4 border-warning">
                <div class="small text-muted">Guest Sessions</div>
                <div class="fs-4 fw-bold text-warning"><?= number_format($chat_guests) ?></div>
                <i class="bi bi-person-question text-warning opacity-25" style="font-size:2rem;position:absolute;right:12px;top:12px"></i>
            </div>
        </div>
    </div>

    <!-- ── Top Products + Recent Orders ── -->
    <div class="row g-3">
        <div class="col-md-4">
            <div class="card p-4 h-100">
                <h6 class="mb-3 fw-semibold"><i class="bi bi-trophy me-2 text-warning"></i>Top Selling Products</h6>
                <?php if ($top_products && $top_products->num_rows > 0): ?>
                <ol class="ps-3 mb-0">
                    <?php while ($tp = $top_products->fetch_assoc()): ?>
                    <li class="mb-2">
                        <div class="fw-semibold small"><?= htmlspecialchars($tp['name']) ?></div>
                        <div class="text-muted" style="font-size:.78rem"><?= $tp['sold'] ?> sold &middot; RWF <?= number_format($tp['revenue'], 0) ?></div>
                    </li>
                    <?php endwhile; ?>
                </ol>
                <?php else: ?>
                <p class="text-muted small">No sales data yet.</p>
                <?php endif; ?>
            </div>
        </div>
        <div class="col-md-8">
            <div class="card p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="mb-0 fw-semibold"><i class="bi bi-clock-history me-2 text-secondary"></i>Recent Orders</h6>
                    <a href="orders.php" class="btn btn-sm btn-outline-secondary">View All</a>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover table-sm mb-0">
                        <thead class="table-light">
                            <tr><th>#</th><th>Customer</th><th>Total</th><th>Status</th><th>Date</th><th></th></tr>
                        </thead>
                        <tbody>
                        <?php while ($o = $recent_orders->fetch_assoc()): ?>
                        <tr>
                            <td class="text-muted">#<?= $o['id'] ?></td>
                            <td><?= htmlspecialchars($o['uname']) ?></td>
                            <td>RWF <?= number_format($o['total_price'], 0) ?></td>
                            <td><span class="badge status-<?= $o['status'] ?>"><?= ucfirst($o['status']) ?></span></td>
                            <td class="text-muted small"><?= date('M d, Y', strtotime($o['created_at'])) ?></td>
                            <td><a href="orders.php?view=<?= $o['id'] ?>" class="btn btn-sm btn-outline-dark py-0">View</a></td>
                        </tr>
                        <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
// Revenue bar chart
new Chart(document.getElementById('revenueChart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode($revenue_labels) ?>,
        datasets: [{
            label: 'Revenue (RWF)',
            data: <?= json_encode($revenue_data) ?>,
            backgroundColor: 'rgba(13,110,253,0.7)',
            borderRadius: 6,
            borderSkipped: false
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: v => 'RWF ' + (v >= 1000 ? (v/1000).toFixed(0)+'k' : v)
                }
            }
        }
    }
});

// Order status doughnut
new Chart(document.getElementById('statusChart'), {
    type: 'doughnut',
    data: {
        labels: ['Pending','Processing','Shipped','Delivered','Cancelled'],
        datasets: [{
            data: <?= json_encode(array_values($order_statuses)) ?>,
            backgroundColor: ['#ffc107','#0dcaf0','#0d6efd','#198754','#dc3545'],
            borderWidth: 2
        }]
    },
    options: {
        responsive: true,
        cutout: '65%',
        plugins: { legend: { display: false } }
    }
});
</script>

<?php require_once 'includes/admin_footer.php'; ?>
