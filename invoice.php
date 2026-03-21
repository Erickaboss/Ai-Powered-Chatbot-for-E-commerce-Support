<?php
require_once 'includes/header.php';
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }

$uid = $_SESSION['user_id'];
$oid = (int)($_GET['id'] ?? 0);

$order = $conn->query("SELECT o.*, u.name as customer_name, u.email, u.phone, u.address as user_address
    FROM orders o JOIN users u ON o.user_id=u.id
    WHERE o.id=$oid AND o.user_id=$uid")->fetch_assoc();

if (!$order) { header('Location: orders.php'); exit; }

$items = $conn->query("SELECT oi.*, p.name as product_name, p.image
    FROM order_items oi JOIN products p ON oi.product_id=p.id
    WHERE oi.order_id=$oid");
?>
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3 d-print-none">
        <a href="orders.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Back to Orders
        </a>
        <button onclick="window.print()" class="btn btn-dark btn-sm">
            <i class="bi bi-printer me-1"></i>Print / Save PDF
        </button>
    </div>

    <div class="card shadow-sm p-4" id="invoice-box">
        <!-- Header -->
        <div class="row mb-4">
            <div class="col-6">
                <h3 class="fw-bold"><i class="bi bi-shop me-2"></i><?= SITE_NAME ?></h3>
                <small class="text-muted">Kigali, Rwanda | <?= ADMIN_EMAIL ?> | <?= ADMIN_PHONE ?></small>
            </div>
            <div class="col-6 text-end">
                <h4 class="text-muted">INVOICE</h4>
                <div class="fw-bold">#<?= str_pad($order['id'], 6, '0', STR_PAD_LEFT) ?></div>
                <small class="text-muted">Date: <?= date('d M Y', strtotime($order['created_at'])) ?></small>
            </div>
        </div>
        <hr>

        <!-- Bill To -->
        <div class="row mb-4">
            <div class="col-6">
                <h6 class="text-muted text-uppercase">Bill To</h6>
                <div class="fw-semibold"><?= htmlspecialchars($order['customer_name']) ?></div>
                <div><?= htmlspecialchars($order['email']) ?></div>
                <div><?= htmlspecialchars($order['phone'] ?? '') ?></div>
                <div><?= nl2br(htmlspecialchars($order['address'] ?: $order['user_address'])) ?></div>
            </div>
            <div class="col-6 text-end">
                <h6 class="text-muted text-uppercase">Order Details</h6>
                <div>Order #: <strong><?= str_pad($order['id'], 6, '0', STR_PAD_LEFT) ?></strong></div>
                <div>Payment: <strong><?= strtoupper($order['payment_method']) ?></strong></div>
                <div>Status:
                    <span class="badge bg-<?= match($order['status']) {
                        'delivered' => 'success', 'shipped' => 'info',
                        'processing' => 'warning', 'cancelled' => 'danger', default => 'secondary'
                    } ?>"><?= ucfirst($order['status']) ?></span>
                </div>
            </div>
        </div>

        <!-- Items Table -->
        <table class="table table-bordered">
            <thead class="table-dark">
                <tr>
                    <th>#</th>
                    <th>Product</th>
                    <th class="text-center">Qty</th>
                    <th class="text-end">Unit Price</th>
                    <th class="text-end">Total</th>
                </tr>
            </thead>
            <tbody>
            <?php $i=1; while ($item = $items->fetch_assoc()): ?>
            <tr>
                <td><?= $i++ ?></td>
                <td><?= htmlspecialchars($item['product_name']) ?></td>
                <td class="text-center"><?= $item['quantity'] ?></td>
                <td class="text-end">RWF <?= number_format($item['price']) ?></td>
                <td class="text-end">RWF <?= number_format($item['price'] * $item['quantity']) ?></td>
            </tr>
            <?php endwhile; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="4" class="text-end fw-bold">TOTAL</td>
                    <td class="text-end fw-bold text-danger fs-5">RWF <?= number_format($order['total_price']) ?></td>
                </tr>
            </tfoot>
        </table>

        <!-- Footer Note -->
        <div class="text-center text-muted mt-3 small">
            <p>Thank you for shopping with <?= SITE_NAME ?>! For support, contact us at <?= ADMIN_EMAIL ?> | <?= ADMIN_PHONE ?></p>
            <p>This is a computer-generated invoice and does not require a signature.</p>
        </div>
    </div>
</div>

<style>
@media print {
    .d-print-none { display: none !important; }
    nav, footer, #chatbot-widget { display: none !important; }
    #invoice-box { box-shadow: none !important; border: none !important; }
    body { background: white !important; }
}
</style>
<?php require_once 'includes/footer.php'; ?>
