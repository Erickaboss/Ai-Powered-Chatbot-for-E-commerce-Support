﻿<?php
require_once 'includes/header.php';
$id   = (int)($_GET['id'] ?? 0);
$stmt = $conn->prepare("SELECT p.*, c.name as cat_name FROM products p LEFT JOIN categories c ON p.category_id=c.id WHERE p.id=?");
$stmt->bind_param("i", $id);
$stmt->execute();
$p = $stmt->get_result()->fetch_assoc();
if (!$p) { header('Location: products.php'); exit; }

$related = $conn->query("SELECT * FROM products WHERE category_id={$p['category_id']} AND id!=$id AND stock>0 ORDER BY RAND() LIMIT 4");
?>

<div class="page-hero">
    <div class="container">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0" style="font-size:.8rem">
                <li class="breadcrumb-item"><a href="index.php" style="color:rgba(255,255,255,.6)">Home</a></li>
                <li class="breadcrumb-item"><a href="products.php" style="color:rgba(255,255,255,.6)">Products</a></li>
                <li class="breadcrumb-item"><a href="products.php?category=<?= $p['category_id'] ?>" style="color:rgba(255,255,255,.6)"><?= htmlspecialchars($p['cat_name'] ?? '') ?></a></li>
                <li class="breadcrumb-item active" style="color:rgba(255,255,255,.8)"><?= htmlspecialchars($p['name']) ?></li>
            </ol>
        </nav>
    </div>
</div>

<div class="container py-5">
    <div class="row g-5">
        <!-- Image -->
        <div class="col-md-5">
            <div class="product-detail-img">
                <img src="<?= strpos($p['image'],'http')===0 ? htmlspecialchars($p['image']) : 'assets/images/'.htmlspecialchars($p['image']) ?>"
                     alt="<?= htmlspecialchars($p['name']) ?>"
                     onerror="this.src='assets/images/placeholder.jpg'">
            </div>
        </div>

        <!-- Info -->
        <div class="col-md-7 product-detail-info">
            <span class="cat-badge mb-2 d-inline-block"><?= htmlspecialchars($p['cat_name'] ?? '') ?></span>
            <h2 class="fw-800 mb-1" style="color:var(--dark)"><?= htmlspecialchars($p['name']) ?></h2>
            <?php if ($p['brand']): ?>
            <div class="text-muted mb-2" style="font-size:.88rem"><i class="bi bi-tag me-1"></i>Brand: <strong><?= htmlspecialchars($p['brand']) ?></strong></div>
            <?php endif; ?>

            <div class="price-big my-3">RWF <?= number_format($p['price']) ?></div>

            <?php if ($p['stock'] > 0): ?>
            <span class="stock-badge-in mb-3 d-inline-block"><i class="bi bi-check-circle me-1"></i>In Stock (<?= $p['stock'] ?> available)</span>
            <?php else: ?>
            <span class="stock-badge-out mb-3 d-inline-block"><i class="bi bi-x-circle me-1"></i>Out of Stock</span>
            <?php endif; ?>

            <?php if ($p['description']): ?>
            <p style="color:#555;line-height:1.7;font-size:.92rem;margin-bottom:24px"><?= nl2br(htmlspecialchars($p['description'])) ?></p>
            <?php endif; ?>

            <!-- Delivery info strip -->
            <div class="row g-2 mb-4">
                <?php foreach ([
                    ['bi-truck','Free shipping','on orders above RWF 50k'],
                    ['bi-arrow-counterclockwise','7-day returns','hassle-free'],
                    ['bi-shield-check','Secure payment','SSL encrypted'],
                ] as [$icon,$t,$s]): ?>
                <div class="col-4">
                    <div class="text-center p-2" style="background:#f8faff;border-radius:10px;font-size:.75rem">
                        <i class="bi <?= $icon ?> text-primary d-block mb-1 fs-5"></i>
                        <strong><?= $t ?></strong><br><span class="text-muted"><?= $s ?></span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <?php if (isset($_SESSION['user_id']) && $p['stock'] > 0): ?>
            <form method="POST" action="cart.php" class="d-flex align-items-center gap-3 flex-wrap">
                <input type="hidden" name="action" value="add">
                <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                <div class="qty-control">
                    <button type="button" onclick="changeQty(-1)">−</button>
                    <input type="number" name="quantity" id="qty" value="1" min="1" max="<?= $p['stock'] ?>">
                    <button type="button" onclick="changeQty(1)">+</button>
                </div>
                <button class="btn btn-lg flex-grow-1" style="background:linear-gradient(135deg,var(--primary),#1e5fa8);color:#fff;border-radius:12px;font-weight:700">
                    <i class="bi bi-cart-plus me-2"></i>Add to Cart
                </button>
            </form>
            <?php elseif (!isset($_SESSION['user_id'])): ?>
            <a href="login.php" class="btn btn-lg w-100" style="background:linear-gradient(135deg,var(--primary),var(--accent));color:#fff;border-radius:12px;font-weight:700">
                <i class="bi bi-lock me-2"></i>Login to Buy
            </a>
            <?php else: ?>
            <button class="btn btn-lg w-100" disabled style="background:#eee;color:#999;border-radius:12px;font-weight:700">
                <i class="bi bi-x-circle me-2"></i>Out of Stock
            </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Related Products -->
    <?php if ($related && $related->num_rows > 0): ?>
    <div class="mt-5">
        <div class="section-title mb-1">Related <span>Products</span></div>
        <div class="section-divider"></div>
        <div class="row g-3 mt-2">
            <?php while ($r = $related->fetch_assoc()): ?>
            <div class="col-6 col-md-3">
                <div class="card product-card h-100">
                    <div style="overflow:hidden">
                        <img src="<?= strpos($r['image'],'http')===0 ? htmlspecialchars($r['image']) : 'assets/images/'.htmlspecialchars($r['image']) ?>"
                             class="card-img-top" alt="<?= htmlspecialchars($r['name']) ?>"
                             onerror="this.src='assets/images/placeholder.jpg'">
                    </div>
                    <div class="card-body d-flex flex-column">
                        <h6 class="card-title" style="font-size:.82rem"><?= htmlspecialchars($r['name']) ?></h6>
                        <span class="price-tag mt-auto mb-2" style="font-size:.9rem">RWF <?= number_format($r['price']) ?></span>
                        <a href="product.php?id=<?= $r['id'] ?>" class="btn-add-cart btn" style="font-size:.78rem">View</a>
                    </div>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
function changeQty(d) {
    const i = document.getElementById('qty');
    const v = parseInt(i.value) + d;
    if (v >= 1 && v <= <?= $p['stock'] ?>) i.value = v;
}
</script>

<?php require_once 'includes/footer.php'; ?>
