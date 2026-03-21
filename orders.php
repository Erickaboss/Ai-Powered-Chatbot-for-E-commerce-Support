﻿<?php
require_once 'includes/header.php';
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }
$uid    = $_SESSION['user_id'];
$orders = $conn->query("SELECT * FROM orders WHERE user_id=$uid ORDER BY created_at DESC");
?>

<div class="page-hero">
    <div class="container">
        <h2><i class="bi bi-bag-check me-2"></i>My Orders</h2>
        <p>Track and manage all your orders</p>
    </div>
</div>

<div class="container pb-5">

    <?php if (!empty($_GET['new']) && !empty($_GET['id'])): ?>
    <div class="alert border-0 mb-4 p-4" style="background:linear-gradient(135deg,#e8f5e9,#f1f8e9);border-radius:16px">
        <div class="d-flex align-items-center gap-3">
            <div style="width:52px;height:52px;background:#28a745;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:1.5rem;flex-shrink:0">✅</div>
            <div>
                <div class="fw-700 fs-5" style="color:#1b5e20">Order Placed Successfully!</div>
                <div class="text-muted small">Order <strong>#<?= str_pad((int)$_GET['id'],6,'0',STR_PAD_LEFT) ?></strong> confirmed. Check your email for details.</div>
            </div>
            <a href="order_detail.php?id=<?= (int)$_GET['id'] ?>" class="btn ms-auto btn-sm" style="background:#28a745;color:#fff;border-radius:8px;white-space:nowrap">View Order</a>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($orders->num_rows === 0): ?>
    <div class="text-center py-5 card-clean p-5">
        <i class="bi bi-bag-x" style="font-size:3.5rem;color:#ccc"></i>
        <h5 class="mt-3 text-muted">No orders yet</h5>
        <p class="text-muted small mb-4">Start shopping and your orders will appear here</p>
        <a href="products.php" class="btn-primary-custom btn px-4">Browse Products</a>
    </div>
    <?php else: ?>
    <div class="card-clean overflow-hidden">
        <div class="p-4 border-bottom d-flex align-items-center justify-content-between">
            <h6 class="fw-700 mb-0" style="color:var(--dark)">Order History</h6>
            <span class="text-muted small"><?= $orders->num_rows ?> orders</span>
        </div>
        <div class="table-responsive">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Order #</th>
                        <th>Date</th>
                        <th>Total</th>
                        <th>Payment</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php while ($o = $orders->fetch_assoc()): ?>
                <tr class="order-row">
                    <td><strong style="color:var(--primary)">#<?= str_pad($o['id'],6,'0',STR_PAD_LEFT) ?></strong></td>
                    <td style="color:#666;font-size:.85rem"><?= date('d M Y, H:i', strtotime($o['created_at'])) ?></td>
                    <td><strong>RWF <?= number_format($o['total_price']) ?></strong></td>
                    <td style="font-size:.82rem;color:#666;text-transform:uppercase"><?= htmlspecialchars($o['payment_method'] ?? 'COD') ?></td>
                    <td><span class="status-<?= $o['status'] ?>"><?= ucfirst($o['status']) ?></span></td>
                    <td><a href="order_detail.php?id=<?= $o['id'] ?>" class="btn btn-sm btn-outline-primary" style="border-radius:8px;font-size:.78rem">View Details</a></td>
                </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>
