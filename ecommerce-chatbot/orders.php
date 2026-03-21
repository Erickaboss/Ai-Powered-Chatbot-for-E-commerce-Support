<?php
require_once 'includes/header.php';
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }
$uid = $_SESSION['user_id'];

$orders = $conn->query("SELECT * FROM orders WHERE user_id=$uid ORDER BY created_at DESC");
?>
<div class="container py-5">
    <h3 class="mb-4"><i class="bi bi-box-seam"></i> My Orders</h3>

    <?php if (!empty($_GET['success']) && !empty($_GET['order'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle-fill fs-5 me-2"></i>
        <strong>Order Placed Successfully!</strong><br>
        Your Order Number is: <strong style="font-size:1.2rem">#<?= (int)$_GET['order'] ?></strong><br>
        <small class="text-muted">Save this number to track your order via the chatbot.</small>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <?php if ($orders->num_rows === 0): ?>
        <div class="text-center py-5">
            <i class="bi bi-inbox fs-1 text-muted"></i>
            <p class="mt-3">No orders yet. <a href="products.php">Start shopping</a></p>
        </div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead class="table-dark">
                <tr>
                    <th>Order #</th>
                    <th>Date</th>
                    <th>Total</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
            <?php while ($o = $orders->fetch_assoc()): ?>
            <tr>
                <td>#<?= $o['id'] ?></td>
                <td><?= date('M d, Y', strtotime($o['created_at'])) ?></td>
                <td>₱<?= number_format($o['total_price'], 2) ?></td>
                <td><span class="badge status-<?= $o['status'] ?>"><?= ucfirst($o['status']) ?></span></td>
                <td><a href="order_detail.php?id=<?= $o['id'] ?>" class="btn btn-sm btn-outline-dark">View</a></td>
            </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>
<?php require_once 'includes/footer.php'; ?>
