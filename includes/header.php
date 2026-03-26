<?php
ob_start();
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config/db.php';

$cart_count = 0;
if (isset($_SESSION['user_id'])) {
    $uid = $_SESSION['user_id'];
    $res = $conn->query("SELECT SUM(ci.quantity) as total FROM cart c JOIN cart_items ci ON c.id=ci.cart_id WHERE c.user_id=$uid");
    $cart_count = (int)($res->fetch_assoc()['total'] ?? 0);
}
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= SITE_NAME ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css?v=<?= filemtime(__DIR__ . '/../assets/css/style.css') ?>">
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-custom sticky-top">
    <div class="container">
        <!-- Brand -->
        <a class="navbar-brand d-flex align-items-center gap-2" href="<?= SITE_URL ?>/index.php">
            <div style="width:34px;height:34px;background:linear-gradient(135deg,#e94560,#f5a623);border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:1rem;">🤖</div>
            <span style="font-size:.88rem;line-height:1.2">AI-Powered<br><span style="color:#f5a623;font-size:.78rem;font-weight:500">E-commerce Support</span></span>
        </a>

        <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navMenu">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navMenu">
            <!-- Nav links -->
            <ul class="navbar-nav me-3">
                <li class="nav-item">
                    <a class="nav-link <?= $current_page==='index.php'?'active':'' ?>" href="<?= SITE_URL ?>/index.php">
                        <i class="bi bi-house me-1"></i>Home
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $current_page==='products.php'?'active':'' ?>" href="<?= SITE_URL ?>/products.php">
                        <i class="bi bi-grid me-1"></i>Products
                    </a>
                </li>
                <?php if (isset($_SESSION['user_id'])): ?>
                <li class="nav-item">
                    <a class="nav-link <?= $current_page==='orders.php'?'active':'' ?>" href="<?= SITE_URL ?>/orders.php">
                        <i class="bi bi-bag me-1"></i>My Orders
                    </a>
                </li>
                <?php endif; ?>
            </ul>

            <!-- Search -->
            <div class="search-wrap mx-auto my-2 my-lg-0" style="max-width:280px;flex:1">
                <i class="bi bi-search search-icon"></i>
                <input type="text" id="nav-search" placeholder="Search products..." autocomplete="off">
                <div id="search-results"></div>
            </div>

            <!-- Right actions -->
            <ul class="navbar-nav ms-3 align-items-center gap-1">
                <li class="nav-item">
                    <a class="nav-link cart-btn" href="<?= SITE_URL ?>/cart.php" title="Cart">
                        <i class="bi bi-cart3"></i>
                        <?php if ($cart_count > 0): ?>
                        <span class="cart-badge"><?= $cart_count ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <!-- Language Toggle -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown" style="font-size:.82rem" id="langBtn">
                        🌐 <span id="langLabel">EN</span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end shadow border-0" style="border-radius:10px;min-width:120px">
                        <li><a class="dropdown-item" href="#" onclick="setLang('en')">🇬🇧 English</a></li>
                        <li><a class="dropdown-item" href="#" onclick="setLang('fr')">🇫🇷 Français</a></li>
                        <li><a class="dropdown-item" href="#" onclick="setLang('rw')">🇷🇼 Kinyarwanda</a></li>
                    </ul>
                </li>
                <?php if (isset($_SESSION['user_id'])): ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle d-flex align-items-center gap-2" href="#" data-bs-toggle="dropdown">
                        <div style="width:32px;height:32px;border-radius:50%;background:linear-gradient(135deg,#0f3460,#e94560);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:.85rem;">
                            <?= strtoupper(substr($_SESSION['user_name'],0,1)) ?>
                        </div>
                        <span style="font-size:.85rem"><?= htmlspecialchars(explode(' ',$_SESSION['user_name'])[0]) ?></span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end shadow border-0" style="border-radius:12px;min-width:180px">
                        <li><div class="px-3 py-2 border-bottom"><div class="fw-semibold small"><?= htmlspecialchars($_SESSION['user_name']) ?></div></div></li>
                        <li><a class="dropdown-item py-2" href="<?= SITE_URL ?>/profile.php"><i class="bi bi-person me-2 text-primary"></i>My Profile</a></li>
                        <li><a class="dropdown-item py-2" href="<?= SITE_URL ?>/orders.php"><i class="bi bi-bag me-2 text-success"></i>My Orders</a></li>
                        <li><hr class="dropdown-divider my-1"></li>
                        <li><a class="dropdown-item py-2 text-danger" href="<?= SITE_URL ?>/logout.php"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
                    </ul>
                </li>
                <?php else: ?>
                <li class="nav-item">
                    <a class="nav-link" href="<?= SITE_URL ?>/login.php" style="font-size:.85rem">Login</a>
                </li>
                <li class="nav-item">
                    <a class="btn btn-sm ms-1 px-3" href="<?= SITE_URL ?>/register.php"
                       style="background:linear-gradient(135deg,#e94560,#f5a623);color:#fff;border-radius:20px;font-size:.82rem;font-weight:600">
                        Register
                    </a>
                </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

<script>
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
                <img src="<?= SITE_URL ?>/assets/images/${p.image}" style="width:40px;height:40px;object-fit:cover;border-radius:8px" onerror="this.src='<?= SITE_URL ?>/assets/images/placeholder.jpg'">
                <div><div class="small fw-semibold">${p.name}</div><div style="color:#e94560;font-size:.75rem;font-weight:700">RWF ${parseFloat(p.price).toLocaleString()}</div></div>
            </a>`
        ).join('');
        searchBox.style.display = 'block';
    }, 280);
});
document.addEventListener('click', e => { if (!navSearch?.contains(e.target)) searchBox.style.display='none'; });
</script>

<script>
// ── Language toggle ──
const translations = {
    en: { 'Home':'Home','Products':'Products','My Orders':'My Orders','Login':'Login','Register':'Register','Search products...':'Search products...' },
    fr: { 'Home':'Accueil','Products':'Produits','My Orders':'Mes Commandes','Login':'Connexion','Register':'S\'inscrire','Search products...':'Rechercher des produits...' },
    rw: { 'Home':'Ahabanza','Products':'Ibicuruzwa','My Orders':'Amabwiriza Yanjye','Login':'Injira','Register':'Iyandikishe','Search products...':'Shakisha ibicuruzwa...' }
};
function setLang(lang) {
    localStorage.setItem('site_lang', lang);
    const labels = { en:'EN', fr:'FR', rw:'RW' };
    document.getElementById('langLabel').textContent = labels[lang] || 'EN';
    const t = translations[lang] || translations.en;
    document.querySelectorAll('[data-translate]').forEach(el => {
        const key = el.getAttribute('data-translate');
        if (t[key]) el.textContent = t[key];
    });
    // Update search placeholder
    const si = document.getElementById('nav-search');
    if (si) si.placeholder = t['Search products...'] || 'Search products...';
    // Update chatbot greeting
    const ci = document.getElementById('chat-input');
    const greetings = { en:'Type a message...', fr:'Tapez un message...', rw:'Andika ubutumwa...' };
    if (ci) ci.placeholder = greetings[lang] || 'Type a message...';
}
// Apply saved language on load
const savedLang = localStorage.getItem('site_lang') || 'en';
document.addEventListener('DOMContentLoaded', () => setLang(savedLang));
</script>
