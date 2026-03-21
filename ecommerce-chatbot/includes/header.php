<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config/db.php';

// Count cart items for badge
$cart_count = 0;
if (isset($_SESSION['user_id'])) {
    $uid = $_SESSION['user_id'];
    $res = $conn->query("SELECT SUM(ci.quantity) as total FROM cart c JOIN cart_items ci ON c.id=ci.cart_id WHERE c.user_id=$uid");
    $row = $res->fetch_assoc();
    $cart_count = $row['total'] ?? 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= SITE_NAME ?> - AI Powered Shop</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css">
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark sticky-top">
    <div class="container">
        <a class="navbar-brand fw-bold" href="<?= SITE_URL ?>/index.php">
            <i class="bi bi-shop"></i> <?= SITE_NAME ?>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMenu">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navMenu">
            <ul class="navbar-nav me-auto">
                <li class="nav-item"><a class="nav-link" href="<?= SITE_URL ?>/index.php">Home</a></li>
                <li class="nav-item"><a class="nav-link" href="<?= SITE_URL ?>/products.php">Products</a></li>
            </ul>
            <!-- Live Search Bar -->
            <div class="position-relative mx-3 my-2 my-lg-0" style="min-width:220px">
                <input type="text" id="nav-search" class="form-control form-control-sm bg-secondary text-white border-0"
                       placeholder="Search products..." autocomplete="off"
                       style="border-radius:20px;padding-left:14px">
                <div id="search-results" class="position-absolute bg-white shadow rounded w-100 mt-1"
                     style="display:none;z-index:9999;max-height:280px;overflow-y:auto"></div>
            </div>
            <ul class="navbar-nav ms-auto align-items-center">
                <li class="nav-item me-2">
                    <a class="nav-link" href="<?= SITE_URL ?>/cart.php">
                        <i class="bi bi-cart3"></i>
                        <span class="badge bg-danger"><?= $cart_count ?></span>
                    </a>
                </li>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <li class="nav-item"><a class="nav-link" href="<?= SITE_URL ?>/orders.php"><i class="bi bi-box"></i> Orders</a></li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                            <i class="bi bi-person-circle"></i> <?= htmlspecialchars(explode(' ', $_SESSION['user_name'])[0]) ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="<?= SITE_URL ?>/profile.php"><i class="bi bi-person me-2"></i>My Profile</a></li>
                            <li><a class="dropdown-item" href="<?= SITE_URL ?>/orders.php"><i class="bi bi-bag me-2"></i>My Orders</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="<?= SITE_URL ?>/logout.php"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
                        </ul>
                    </li>
                <?php else: ?>
                    <li class="nav-item"><a class="nav-link" href="<?= SITE_URL ?>/login.php">Login</a></li>
                    <li class="nav-item"><a class="btn btn-primary btn-sm ms-2" href="<?= SITE_URL ?>/register.php">Register</a></li>
                <?php endif; ?>
            </ul>
        </div>
<script>
// Live search
const navSearch = document.getElementById('nav-search');
const searchBox = document.getElementById('search-results');
let searchTimer;
navSearch?.addEventListener('input', function() {
    clearTimeout(searchTimer);
    const q = this.value.trim();
    if (q.length < 2) { searchBox.style.display='none'; return; }
    searchTimer = setTimeout(async () => {
        const res = await fetch('<?= SITE_URL ?>/api/search.php?q=' + encodeURIComponent(q));
        const data = await res.json();
        if (!data.length) { searchBox.style.display='none'; return; }
        searchBox.innerHTML = data.map(p =>
            `<a href="<?= SITE_URL ?>/product.php?id=${p.id}" class="d-flex align-items-center gap-2 p-2 text-decoration-none text-dark border-bottom search-item">
                <img src="<?= SITE_URL ?>/assets/images/${p.image}" style="width:36px;height:36px;object-fit:cover;border-radius:6px" onerror="this.src='<?= SITE_URL ?>/assets/images/placeholder.jpg'">
                <div><div class="small fw-semibold">${p.name}</div><div class="text-danger small">₱${parseFloat(p.price).toLocaleString()}</div></div>
            </a>`
        ).join('');
        searchBox.style.display = 'block';
    }, 300);
});
document.addEventListener('click', e => { if (!navSearch?.contains(e.target)) searchBox.style.display='none'; });
</script>
    </div>
</nav>
