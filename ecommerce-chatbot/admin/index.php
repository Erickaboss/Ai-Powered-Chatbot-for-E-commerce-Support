<?php require_once 'includes/admin_header.php'; ?>
<?php
$total_products = $conn->query("SELECT COUNT(*) as c FROM products")->fetch_assoc()['c'];
$total_orders   = $conn->query("SELECT COUNT(*) as c FROM orders")->fetch_assoc()['c'];
$total_users    = $conn->query("SELECT COUNT(*) as c FROM users WHERE role='customer'")->fetch_assoc()['c'];
$total_revenue  = $conn->query("SELECT SUM(total_price) as s FROM orders WHERE status != 'cancelled'")->fetch_assoc()['s'] ?? 0;
$recent_orders  = $conn->query("SELECT o.*, u.name FROM orders o JOIN users u ON o.user_id=u.id ORDER BY o.created_at DESC LIMIT 5");
?>
<div class="admin-content">
    <h4 class="mb-4">Dashboard</h4>

    <!-- Stats Cards -->
    <div class="row g-4 mb-5">
        <div class="col-md-3">
            <div class="card text-white bg-primary p-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div><div class="fs-4 fw-bold"><?= $total_products ?></div><div>Products</div></div>
                    <i class="bi bi-box-seam fs-1 opacity-50"></i>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-white bg-success p-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div><div class="fs-4 fw-bold"><?= $total_orders ?></div><div>Orders</div></div>
                    <i class="bi bi-bag fs-1 opacity-50"></i>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-white bg-warning p-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div><div class="fs-4 fw-bold"><?= $total_users ?></div><div>Customers</div></div>
                    <i class="bi bi-people fs-1 opacity-50"></i>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-white bg-danger p-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div><div class="fs-4 fw-bold">₱<?= number_format($total_revenue, 0) ?></div><div>Revenue</div></div>
                    <i class="bi bi-currency-dollar fs-1 opacity-50"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Orders -->
    <div class="card p-4">
        <h5 class="mb-3">Recent Orders</h5>
        <table class="table table-hover">
            <thead><tr><th>#</th><th>Customer</th><th>Total</th><th>Status</th><th>Date</th><th>Action</th></tr></thead>
            <tbody>
            <?php while ($o = $recent_orders->fetch_assoc()): ?>
            <tr>
                <td>#<?= $o['id'] ?></td>
                <td><?= htmlspecialchars($o['name']) ?></td>
                <td>₱<?= number_format($o['total_price'], 2) ?></td>
                <td><span class="badge status-<?= $o['status'] ?>"><?= ucfirst($o['status']) ?></span></td>
                <td><?= date('M d, Y', strtotime($o['created_at'])) ?></td>
                <td><a href="orders.php?view=<?= $o['id'] ?>" class="btn btn-sm btn-outline-dark">View</a></td>
            </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require_once 'includes/admin_footer.php'; ?>
