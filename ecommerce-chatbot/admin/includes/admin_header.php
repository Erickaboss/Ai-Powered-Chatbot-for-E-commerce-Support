<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ' . SITE_URL . '/login.php'); exit;
}
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
<!-- Admin Sidebar -->
<div class="admin-sidebar">
    <div class="text-center py-3 border-bottom border-secondary mb-2">
        <i class="bi bi-shop fs-3 text-warning"></i>
        <div class="fw-bold mt-1"><?= SITE_NAME ?> Admin</div>
        <small class="text-muted"><?= htmlspecialchars($_SESSION['user_name']) ?></small>
    </div>
    <?php
    $current = basename($_SERVER['PHP_SELF']);
    $links = [
        'index.php'    => ['bi-speedometer2', 'Dashboard'],
        'products.php' => ['bi-box-seam', 'Products'],
        'categories.php'=> ['bi-tags', 'Categories'],
        'orders.php'   => ['bi-bag', 'Orders'],
        'users.php'    => ['bi-people', 'Customers'],
        'chatbot_logs.php' => ['bi-chat-dots', 'Chatbot Logs'],
    ];
    foreach ($links as $file => [$icon, $label]):
    ?>
    <a href="<?= $file ?>" class="<?= $current === $file ? 'active' : '' ?>">
        <i class="bi <?= $icon ?> me-2"></i><?= $label ?>
    </a>
    <?php endforeach; ?>
    <a href="<?= SITE_URL ?>/logout.php" class="mt-auto" style="position:absolute;bottom:20px;width:100%">
        <i class="bi bi-box-arrow-left me-2"></i>Logout
    </a>
</div>
