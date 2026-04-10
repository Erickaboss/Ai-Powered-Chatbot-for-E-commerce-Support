<?php
require_once 'includes/header.php';

$token = $_GET['token'] ?? '';
$shareData = null;
$products = [];

if ($token) {
    // Check session for share data
    $sessionKey = 'wishlist_share_' . $token;
    if (isset($_SESSION[$sessionKey])) {
        $shareData = $_SESSION[$sessionKey];
        $productIds = explode(',', $shareData['products']);
        
        // Fetch products
        $idList = implode(',', array_map('intval', $productIds));
        $result = $conn->query("SELECT p.*, c.name as cat_name, p.avg_rating, p.review_count 
                                FROM products p 
                                LEFT JOIN categories c ON p.category_id = c.id 
                                WHERE p.id IN ($idList)");
        $products = $result->fetch_all(MYSQLI_ASSOC);
    }
}
?>

<div class="page-hero">
    <div class="container">
        <h2><i class="bi bi-heart me-2"></i>Shared Wishlist</h2>
        <?php if ($shareData): ?>
            <p><?= count($products) ?> products shared with you</p>
        <?php else: ?>
            <p class="text-muted">This wishlist is not available or has expired</p>
        <?php endif; ?>
    </div>
</div>

<div class="container pb-5">
<?php if (!$shareData || empty($products)): ?>
    <div class="text-center py-5 card-clean p-5 mt-2">
        <div style="font-size:4rem;margin-bottom:16px">❌</div>
        <h4 class="text-muted fw-600">Wishlist Not Available</h4>
        <p class="text-muted small mb-4">This shared wishlist may have expired or been removed.</p>
        <a href="products.php" class="btn-primary-custom btn px-5">Browse Products</a>
    </div>
<?php else: ?>
    <div class="alert alert-info mb-4">
        <i class="bi bi-info-circle me-2"></i>
        <strong>Shared by User #<?= $shareData['user_id'] ?></strong> - 
        These products have been specially selected and shared with you!
    </div>
    
    <div class="row g-4 mt-1">
    <?php foreach ($products as $p): ?>
    <div class="col-6 col-md-4 col-lg-3">
        <div class="card product-card h-100">
            <div style="overflow:hidden;position:relative">
                <img src="<?= strpos($p['image'],'http')===0 ? htmlspecialchars($p['image']) : 'assets/images/'.htmlspecialchars($p['image']) ?>"
                     class="card-img-top" alt="<?= htmlspecialchars($p['name']) ?>"
                     onerror="this.src='assets/images/placeholder.jpg'">
            </div>
            <div class="card-body d-flex flex-column">
                <span class="cat-badge"><?= htmlspecialchars($p['cat_name'] ?? '') ?></span>
                <h6 class="card-title"><?= htmlspecialchars($p['name']) ?></h6>
                <?php if ($p['avg_rating'] > 0): ?>
                <div class="mb-2">
                    <small class="text-warning">
                        <?php for($i=1; $i<=5; $i++): ?>
                            <i class="bi bi-star<?= $i <= round($p['avg_rating']) ? '-fill' : '' ?>"></i>
                        <?php endfor; ?>
                    </small>
                    <small class="text-muted">(<?= $p['review_count'] ?>)</small>
                </div>
                <?php endif; ?>
                <div class="d-flex align-items-center justify-content-between mt-auto mb-2">
                    <span class="price-tag">RWF <?= number_format($p['price']) ?></span>
                    <?php if ($p['stock'] > 0): ?>
                    <span class="stock-badge-in"><?= $p['stock'] ?> left</span>
                    <?php else: ?>
                    <span class="stock-badge-out">Out of stock</span>
                    <?php endif; ?>
                </div>
                <a href="product.php?id=<?= $p['id'] ?>" class="btn-add-cart btn"><i class="bi bi-eye me-1"></i>View Details</a>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    </div>
    
    <div class="text-center mt-4">
        <a href="register.php" class="btn btn-primary btn-lg">
            <i class="bi bi-person-plus me-2"></i>Create Account to Save This Wishlist
        </a>
    </div>
<?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>
