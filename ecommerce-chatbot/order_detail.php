<?php
require_once 'includes/header.php';
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }
$uid = $_SESSION['user_id'];
$oid = (int)($_GET['id'] ?? 0);

$stmt = $conn->prepare("SELECT * FROM orders WHERE id=? AND user_id=?");
$stmt->bind_param("ii", $oid, $uid);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
if (!$order) { header('Location: orders.php'); exit; }

$items = $conn->query("SELECT oi.*, p.name, p.image FROM order_items oi JOIN products p ON oi.product_id=p.id WHERE oi.order_id=$oid");
?>
<div class="container py-5">
    <h3 class="mb-4">Order #<?= $order['id'] ?> Details</h3>
    <div class="row">
        <div class="col-md-8">
            <div class="card p-4 mb-4">
                <h5>Items Ordered</h5>
                <hr>
                <?php while ($item = $items->fetch_assoc()): ?>
                <div class="d-flex align-items-center mb-3 gap-3">
                    <img src="assets/images/<?= htmlspecialchars($item['image']) ?>"
                         style="width:60px;height:60px;object-fit:cover;border-radius:8px"
                         onerror="this.src='assets/images/placeholder.jpg'">
                    <div class="flex-grow-1">
                        <strong><?= htmlspecialchars($item['name']) ?></strong>
                        <div class="text-muted small">Qty: <?= $item['quantity'] ?> × ₱<?= number_format($item['price'], 2) ?></div>
                    </div>
                    <strong>₱<?= number_format($item['price'] * $item['quantity'], 2) ?></strong>
                </div>
                <?php endwhile; ?>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card p-4">
                <h5>Order Info</h5>
                <hr>
                <p><strong>Status:</strong> <span class="badge status-<?= $order['status'] ?>"><?= ucfirst($order['status']) ?></span></p>
                <p><strong>Date:</strong> <?= date('M d, Y h:i A', strtotime($order['created_at'])) ?></p>
                <p><strong>Address:</strong><br><?= nl2br(htmlspecialchars($order['address'])) ?></p>
                <hr>
                <div class="d-flex justify-content-between">
                    <strong>Total</strong>
                    <strong class="price-tag">₱<?= number_format($order['total_price'], 2) ?></strong>
                </div>
            </div>
        </div>
    </div>
    <a href="orders.php" class="btn btn-outline-dark"><i class="bi bi-arrow-left"></i> Back to Orders</a>
</div>
<?php require_once 'includes/footer.php'; ?>
