<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../config/db.php';
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ' . SITE_URL . '/login.php'); exit;
}
$current       = basename($_SERVER['PHP_SELF']);
$pending_count = $conn->query("SELECT COUNT(*) as c FROM orders WHERE status='pending'")->fetch_assoc()['c'];
$nav = [
    'index.php'        => ['bi-speedometer2', 'Dashboard'],
    'products.php'     => ['bi-box-seam',     'Products'],
    'categories.php'   => ['bi-tags',         'Categories'],
    'orders.php'       => ['bi-bag',          'Orders'],
    'users.php'        => ['bi-people',       'Customers'],
    'chatbot_logs.php'   => ['bi-chat-dots',    'Chatbot Logs'],
    'product_images.php' => ['bi-images',        'Product Images'],
    'store.php'          => ['bi-shop',           'View Store'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin — <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css">
</head>
<body>

<div class="admin-sidebar">
    <!-- Brand -->
    <div class="sidebar-brand">
        <div class="brand-icon">🤖</div>
        <div class="brand-name"><?= SITE_NAME ?></div>
        <div class="brand-user mt-1"><i class="bi bi-person-circle me-1"></i><?= htmlspecialchars($_SESSION['user_name']) ?></div>
    </div>

    <!-- Nav -->
    <nav>
        <?php foreach ($nav as $file => [$icon, $label]): ?>
        <a href="<?= $file ?>" class="<?= $current===$file ? 'active' : '' ?>">
            <i class="bi <?= $icon ?> nav-icon"></i>
            <?= $label ?>
            <?php if ($file==='orders.php' && $pending_count > 0): ?>
            <span class="badge bg-danger ms-auto" style="font-size:.65rem"><?= $pending_count ?></span>
            <?php endif; ?>
        </a>
        <?php endforeach; ?>
    </nav>

    <!-- Footer -->
    <div class="sidebar-footer">
        <a href="<?= SITE_URL ?>/logout.php">
            <i class="bi bi-box-arrow-left"></i> Logout
        </a>
    </div>
</div>
