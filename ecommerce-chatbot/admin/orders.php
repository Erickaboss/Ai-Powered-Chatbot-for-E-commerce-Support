<?php require_once 'includes/admin_header.php'; ?>
<?php
$msg = '';

// Update order status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['order_id'])) {
    $oid    = (int)$_POST['order_id'];
    $status = $conn->real_escape_string($_POST['status']);
    $conn->query("UPDATE orders SET status='$status' WHERE id=$oid");
    $msg = '<div class="alert alert-success">Order status updated.</div>';
}

// View single order
$view_order = null;
if (!empty($_GET['view'])) {
    $vid = (int)$_GET['view'];
    $view_order = $conn->query("SELECT o.*, u.name as customer FROM orders o JOIN users u ON o.user_id=u.id WHERE o.id=$vid")->fetch_assoc();
    $view_items = $conn->query("SELECT oi.*, p.name FROM order_items oi JOIN products p ON oi.product_id=p.id WHERE oi.order_id=$vid");
}

$orders = $conn->query("SELECT o.*, u.name as customer FROM orders o JOIN users u ON o.user_id=u.id ORDER BY o.created_at DESC");
$statuses = ['pending','processing','shipped','delivered','cancelled'];
?>
<div class="admin-content">
    <h4 class="mb-4">Manage Orders</h4>
    <?= $msg ?>

    <?php if ($view_order): ?>
    <div class="card p-4 mb-4">
        <div class="d-flex justify-content-between">
            <h5>Order #<?= $view_order['id'] ?> — <?= htmlspecialchars($view_order['customer']) ?></h5>
            <a href="orders.php" class="btn btn-sm btn-outline-secondary">Back</a>
        </div>
        <p class="text-muted mb-2"><?= date('M d, Y h:i A', strtotime($view_order['created_at'])) ?> | Address: <?= htmlspecialchars($view_order['address']) ?></p>
        <table class="table table-sm mb-3">
            <thead><tr><th>Product</th><th>Qty</th><th>Price</th><th>Subtotal</th></tr></thead>
            <tbody>
            <?php while ($item = $view_items->fetch_assoc()): ?>
            <tr>
                <td><?= htmlspecialchars($item['name']) ?></td>
                <td><?= $item['quantity'] ?></td>
                <td>₱<?= number_format($item['price'], 2) ?></td>
                <td>₱<?= number_format($item['price'] * $item['quantity'], 2) ?></td>
            </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
        <div class="d-flex justify-content-between align-items-center">
            <strong>Total: ₱<?= number_format($view_order['total_price'], 2) ?></strong>
            <form method="POST" class="d-flex gap-2">
                <input type="hidden" name="order_id" value="<?= $view_order['id'] ?>">
                <select name="status" class="form-select form-select-sm">
                    <?php foreach ($statuses as $s): ?>
                    <option value="<?= $s ?>" <?= $view_order['status'] === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                    <?php endforeach; ?>
                </select>
                <button class="btn btn-sm btn-dark">Update Status</button>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <div class="card p-3">
        <table class="table table-hover align-middle">
            <thead class="table-dark">
                <tr><th>#</th><th>Customer</th><th>Total</th><th>Status</th><th>Date</th><th>Actions</th></tr>
            </thead>
            <tbody>
            <?php while ($o = $orders->fetch_assoc()): ?>
            <tr>
                <td>#<?= $o['id'] ?></td>
                <td><?= htmlspecialchars($o['customer']) ?></td>
                <td>₱<?= number_format($o['total_price'], 2) ?></td>
                <td><span class="badge status-<?= $o['status'] ?>"><?= ucfirst($o['status']) ?></span></td>
                <td><?= date('M d, Y', strtotime($o['created_at'])) ?></td>
                <td class="d-flex gap-1 flex-wrap">
                    <!-- View button -->
                    <a href="?view=<?= $o['id'] ?>" class="btn btn-sm btn-outline-dark">
                        <i class="bi bi-eye"></i> View
                    </a>

                    <!-- Quick approve: only show if pending -->
                    <?php if ($o['status'] === 'pending'): ?>
                    <form method="POST" class="d-inline">
                        <input type="hidden" name="order_id" value="<?= $o['id'] ?>">
                        <input type="hidden" name="status" value="processing">
                        <button class="btn btn-sm btn-success" onclick="return confirm('Approve Order #<?= $o['id'] ?>?')">
                            <i class="bi bi-check-circle"></i> Approve
                        </button>
                    </form>
                    <?php endif; ?>

                    <!-- Quick ship: only show if processing -->
                    <?php if ($o['status'] === 'processing'): ?>
                    <form method="POST" class="d-inline">
                        <input type="hidden" name="order_id" value="<?= $o['id'] ?>">
                        <input type="hidden" name="status" value="shipped">
                        <button class="btn btn-sm btn-primary" onclick="return confirm('Mark Order #<?= $o['id'] ?> as Shipped?')">
                            <i class="bi bi-truck"></i> Ship
                        </button>
                    </form>
                    <?php endif; ?>

                    <!-- Mark delivered: only show if shipped -->
                    <?php if ($o['status'] === 'shipped'): ?>
                    <form method="POST" class="d-inline">
                        <input type="hidden" name="order_id" value="<?= $o['id'] ?>">
                        <input type="hidden" name="status" value="delivered">
                        <button class="btn btn-sm btn-info text-white" onclick="return confirm('Mark Order #<?= $o['id'] ?> as Delivered?')">
                            <i class="bi bi-bag-check"></i> Delivered
                        </button>
                    </form>
                    <?php endif; ?>

                    <!-- Cancel: show if not delivered/cancelled -->
                    <?php if (!in_array($o['status'], ['delivered','cancelled'])): ?>
                    <form method="POST" class="d-inline">
                        <input type="hidden" name="order_id" value="<?= $o['id'] ?>">
                        <input type="hidden" name="status" value="cancelled">
                        <button class="btn btn-sm btn-outline-danger" onclick="return confirm('Cancel Order #<?= $o['id'] ?>?')">
                            <i class="bi bi-x-circle"></i>
                        </button>
                    </form>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require_once 'includes/admin_footer.php'; ?>
