<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../config/db.php';
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ' . SITE_URL . '/login.php'); exit;
}
$current       = basename($_SERVER['PHP_SELF']);
$pending_count = $conn->query("SELECT COUNT(*) as c FROM orders WHERE status='pending'")->fetch_assoc()['c'];
$ticket_count  = $conn->query("SELECT COUNT(*) as c FROM support_tickets WHERE status='open'")->fetch_assoc()['c'];
$lowStock = $conn->query("SELECT id, name, stock FROM products WHERE stock > 0 AND stock <= 5 ORDER BY stock ASC LIMIT 10");
$lowStockItems = $lowStock ? $lowStock->fetch_all(MYSQLI_ASSOC) : [];
// Send low stock email once per day per product
if (!empty($lowStockItems)) {
    $today = date('Y-m-d');
    $lastAlert = $_SESSION['low_stock_alert_date'] ?? '';
    if ($lastAlert !== $today) {
        $_SESSION['low_stock_alert_date'] = $today;
        require_once __DIR__ . '/../../includes/mailer.php';
        $itemList = implode('', array_map(fn($i) => "<li><strong>{$i['name']}</strong> — only <strong>{$i['stock']}</strong> left</li>", $lowStockItems));
        sendMail(ADMIN_EMAIL, ADMIN_NAME, '[' . SITE_NAME . '] ⚠️ Low Stock Alert — ' . count($lowStockItems) . ' products',
            emailWrap('⚠️ Low Stock Alert', "<h2 style='color:#dc3545'>⚠️ Low Stock Warning</h2><p>The following products are running low (5 or fewer units):</p><ul style='line-height:2'>$itemList</ul><p><a href='" . SITE_URL . "/admin/products.php' style='background:#0f3460;color:#fff;padding:10px 22px;border-radius:6px;text-decoration:none;font-weight:600'>Manage Products →</a></p>")
        );
    }
}
$nav = [
    'index.php'        => ['bi-speedometer2', 'Dashboard'],
    'products.php'     => ['bi-box-seam',     'Products'],
    'categories.php'   => ['bi-tags',         'Categories'],
    'orders.php'       => ['bi-bag',          'Orders'],
    'users.php'        => ['bi-people',       'Customers'],
    'chatbot_logs.php'     => ['bi-chat-dots',    'Chatbot Logs'],
    'ml_performance.php' => ['bi-graph-up-arrow', 'ML Performance'],
    'chatbot_analytics.php'=> ['bi-bar-chart-line','Analytics'],
    'support_tickets.php'  => ['bi-headset',       'Support Tickets'],
    'product_images.php'   => ['bi-images',        'Product Images'],
    'store.php'            => ['bi-shop',           'View Store'],
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
            <?php if ($file==='support_tickets.php' && $ticket_count > 0): ?>
            <span class="badge bg-danger ms-auto" style="font-size:.65rem"><?= $ticket_count ?></span>
            <?php endif; ?>
        </a>
        <?php endforeach; ?>
    </nav>

    <!-- Logout Button (Prominent) -->
    <div class="mt-3 px-3">
        <a href="<?= SITE_URL ?>/logout.php" class="btn btn-danger w-100" style="border-radius: 8px;">
            <i class="bi bi-box-arrow-right me-2"></i>Logout
        </a>
    </div>

    <!-- Footer -->
    <div class="sidebar-footer">
        <a href="<?= SITE_URL ?>/logout.php">
            <i class="bi bi-box-arrow-left"></i> Logout
        </a>
    </div>
</div>
