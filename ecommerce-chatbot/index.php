<?php
require_once 'includes/header.php';
$featured = $conn->query("SELECT p.*, c.name as cat_name FROM products p LEFT JOIN categories c ON p.category_id=c.id ORDER BY p.created_at DESC LIMIT 8");
?>

<!-- Hero -->
<section class="hero-section text-center">
    <div class="container">
        <h1 class="display-4 fw-bold mb-3">Welcome to <?= SITE_NAME ?></h1>
        <p class="lead mb-4">Shop smarter with AI-powered customer support available 24/7</p>
        <a href="products.php" class="btn btn-danger btn-lg me-2">Shop Now</a>
        <a href="#featured" class="btn btn-outline-light btn-lg">View Featured</a>
    </div>
</section>

<!-- Categories -->
<section class="py-5">
    <div class="container">
        <h2 class="text-center mb-4">Shop by Category</h2>
        <div class="row g-3 justify-content-center">
            <?php
            $cats = $conn->query("SELECT * FROM categories");
            $icons = ['bi-phone', 'bi-bag', 'bi-watch', 'bi-handbag'];
            $i = 0;
            while ($cat = $cats->fetch_assoc()):
            ?>
            <div class="col-6 col-md-3">
                <a href="products.php?category=<?= $cat['id'] ?>" class="text-decoration-none">
                    <div class="card text-center p-3 product-card h-100">
                        <i class="bi <?= $icons[$i % 4] ?> fs-1 text-primary mb-2"></i>
                        <h6 class="mb-0"><?= htmlspecialchars($cat['name']) ?></h6>
                    </div>
                </a>
            </div>
            <?php $i++; endwhile; ?>
        </div>
    </div>
</section>

<!-- Featured Products -->
<section class="py-5 bg-white" id="featured">
    <div class="container">
        <h2 class="text-center mb-4">Featured Products</h2>
        <div class="row g-4">
            <?php while ($p = $featured->fetch_assoc()): ?>
            <div class="col-6 col-md-3">
                <div class="card product-card h-100">
                    <img src="assets/images/<?= htmlspecialchars($p['image']) ?>"
                         class="card-img-top"
                         alt="<?= htmlspecialchars($p['name']) ?>"
                         onerror="this.src='assets/images/placeholder.jpg'">
                    <div class="card-body d-flex flex-column">
                        <small class="text-muted"><?= htmlspecialchars($p['cat_name'] ?? '') ?></small>
                        <h6 class="card-title mt-1"><?= htmlspecialchars($p['name']) ?></h6>
                        <p class="price-tag mt-auto">₱<?= number_format($p['price'], 2) ?></p>
                        <a href="product.php?id=<?= $p['id'] ?>" class="btn btn-dark btn-sm mt-2">View Details</a>
                    </div>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
    </div>
</section>

<!-- AI Chatbot Promo -->
<section class="py-5 bg-dark text-white text-center">
    <div class="container">
        <i class="bi bi-robot fs-1 text-warning mb-3 d-block"></i>
        <h3>Need Help? Ask Our AI Assistant</h3>
        <p class="text-muted">Available 24/7 — track orders, find products, get answers instantly</p>
        <button class="btn btn-warning" onclick="toggleChat(); if(!chatOpen) toggleChat();">
            Chat Now <i class="bi bi-chat-dots"></i>
        </button>
    </div>
</section>

<?php require_once 'includes/footer.php'; ?>
