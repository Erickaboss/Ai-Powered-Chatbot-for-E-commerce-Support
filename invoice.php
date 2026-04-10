<?php
session_start();
require_once 'config/db.php';
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }

$uid  = (int)$_SESSION['user_id'];
$role = $_SESSION['user_role'] ?? 'customer';
$oid  = (int)($_GET['id'] ?? 0);

if ($role === 'admin') {
    $order = $conn->query("SELECT o.*, u.name as customer_name, u.email, u.phone, u.address as user_address
        FROM orders o JOIN users u ON o.user_id=u.id WHERE o.id=$oid")->fetch_assoc();
    if (!$order) { header('Location: admin/orders.php'); exit; }
} else {
    $order = $conn->query("SELECT o.*, u.name as customer_name, u.email, u.phone, u.address as user_address
        FROM orders o JOIN users u ON o.user_id=u.id WHERE o.id=$oid AND o.user_id=$uid")->fetch_assoc();
    if (!$order) { header('Location: orders.php'); exit; }
}

$items   = $conn->query("SELECT oi.*, p.name as product_name FROM order_items oi JOIN products p ON oi.product_id=p.id WHERE oi.order_id=$oid");
$backUrl = $role === 'admin' ? 'admin/orders.php' : 'orders.php';
$statusColors = ['delivered'=>'#28a745','shipped'=>'#17a2b8','processing'=>'#ffc107','cancelled'=>'#dc3545','pending'=>'#6c757d'];
$statusColor  = $statusColors[$order['status']] ?? '#6c757d';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Invoice #<?= str_pad($oid,6,'0',STR_PAD_LEFT) ?> — <?= SITE_NAME ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
<style>
body { background:#f0f2f5; font-family:'Segoe UI',Arial,sans-serif; }
.invoice-wrap { max-width:800px; margin:30px auto 60px; background:#fff; border-radius:14px; box-shadow:0 4px 24px rgba(0,0,0,.1); overflow:hidden; }
.inv-header { background:linear-gradient(135deg,#0f3460,#1e5fa8); color:#fff; padding:32px 40px; }
.inv-body   { padding:32px 40px; }
.inv-footer { background:#f8f9fa; padding:18px 40px; text-align:center; font-size:.82rem; color:#888; border-top:1px solid #eee; }
.toolbar    { max-width:800px; margin:20px auto 0; display:flex; justify-content:space-between; align-items:center; }
@media print {
    body { background:#fff !important; }
    .toolbar { display:none !important; }
    .invoice-wrap { box-shadow:none !important; border-radius:0 !important; margin:0 !important; }
}
</style>
</head>
<body>

<!-- Toolbar (hidden on print) -->
<div class="toolbar d-print-none px-2">
    <a href="<?= $backUrl ?>" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i>Back to Orders
    </a>
    <button onclick="window.print()" class="btn btn-dark btn-sm">
        <i class="bi bi-printer me-1"></i>Print / Save PDF
    </button>
</div>

<div class="invoice-wrap">

    <!-- Header -->
    <div class="inv-header">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
            <div>
                <div class="d-flex align-items-center gap-2 mb-1">
                    <div style="width:38px;height:38px;background:rgba(255,255,255,.2);border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:1.2rem">🤖</div>
                    <div>
                        <div style="font-weight:700;font-size:1.1rem"><?= SITE_NAME ?></div>
                        <div style="font-size:.78rem;opacity:.75">Kigali, Rwanda</div>
                    </div>
                </div>
                <div style="font-size:.8rem;opacity:.75;margin-top:6px">
                    <?= ADMIN_EMAIL ?> &nbsp;|&nbsp; <?= ADMIN_PHONE ?>
                </div>
            </div>
            <div class="text-end">
                <div style="font-size:1.6rem;font-weight:800;letter-spacing:2px;opacity:.9">INVOICE</div>
                <div style="font-size:1.1rem;font-weight:700">#<?= str_pad($order['id'],6,'0',STR_PAD_LEFT) ?></div>
                <div style="font-size:.8rem;opacity:.75">Date: <?= date('d M Y', strtotime($order['created_at'])) ?></div>
            </div>
        </div>
    </div>

    <!-- Body -->
    <div class="inv-body">

        <!-- Bill To + Order Details -->
        <div class="row mb-4">
            <div class="col-6">
                <div style="font-size:.72rem;font-weight:700;color:#888;text-transform:uppercase;letter-spacing:1px;margin-bottom:8px">Bill To</div>
                <div style="font-weight:700;font-size:1rem"><?= htmlspecialchars($order['customer_name']) ?></div>
                <div style="color:#555;font-size:.88rem"><?= htmlspecialchars($order['email']) ?></div>
                <?php if ($order['phone']): ?>
                <div style="color:#555;font-size:.88rem"><?= htmlspecialchars($order['phone']) ?></div>
                <?php endif; ?>
                <div style="color:#555;font-size:.88rem"><?= nl2br(htmlspecialchars($order['address'] ?: $order['user_address'])) ?></div>
            </div>
            <div class="col-6 text-end">
                <div style="font-size:.72rem;font-weight:700;color:#888;text-transform:uppercase;letter-spacing:1px;margin-bottom:8px">Order Details</div>
                <div style="font-size:.88rem;margin-bottom:4px">Order #: <strong><?= str_pad($order['id'],6,'0',STR_PAD_LEFT) ?></strong></div>
                <div style="font-size:.88rem;margin-bottom:4px">Payment: <strong><?= strtoupper($order['payment_method']) ?></strong></div>
                <div style="font-size:.88rem">Status:
                    <span style="background:<?= $statusColor ?>;color:#fff;padding:3px 10px;border-radius:20px;font-size:.75rem;font-weight:600">
                        <?= ucfirst($order['status']) ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- Items Table -->
        <table class="table" style="border-collapse:collapse;width:100%">
            <thead>
                <tr style="background:#0f3460;color:#fff">
                    <th style="padding:12px 14px;text-align:left">#</th>
                    <th style="padding:12px 14px;text-align:left">Product</th>
                    <th style="padding:12px 14px;text-align:center">Qty</th>
                    <th style="padding:12px 14px;text-align:right">Unit Price</th>
                    <th style="padding:12px 14px;text-align:right">Total</th>
                </tr>
            </thead>
            <tbody>
            <?php $i=1; $grand=0; while ($item = $items->fetch_assoc()):
                $sub = $item['price'] * $item['quantity'];
                $grand += $sub;
            ?>
            <tr style="border-bottom:1px solid #eee">
                <td style="padding:12px 14px;color:#888"><?= $i++ ?></td>
                <td style="padding:12px 14px;font-weight:600"><?= htmlspecialchars($item['product_name']) ?></td>
                <td style="padding:12px 14px;text-align:center"><?= $item['quantity'] ?></td>
                <td style="padding:12px 14px;text-align:right">RWF <?= number_format($item['price']) ?></td>
                <td style="padding:12px 14px;text-align:right;font-weight:600">RWF <?= number_format($sub) ?></td>
            </tr>
            <?php endwhile; ?>
            </tbody>
            <tfoot>
                <tr style="background:#f8f9fa">
                    <td colspan="4" style="padding:14px;text-align:right;font-weight:700;font-size:1rem">TOTAL</td>
                    <td style="padding:14px;text-align:right;font-weight:800;font-size:1.1rem;color:#e94560">
                        RWF <?= number_format($order['total_price']) ?>
                    </td>
                </tr>
            </tfoot>
        </table>

    </div>

    <!-- Footer -->
    <div class="inv-footer">
        Thank you for shopping with <strong><?= SITE_NAME ?></strong>!
        For support: <?= ADMIN_EMAIL ?> | <?= ADMIN_PHONE ?><br>
        This is a computer-generated invoice and does not require a signature.
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
