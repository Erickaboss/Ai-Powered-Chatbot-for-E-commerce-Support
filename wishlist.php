<?php
require_once 'includes/header.php';
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }
$uid = $_SESSION['user_id'];

// Toggle wishlist
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pid = (int)($_POST['product_id'] ?? 0);
    $action = $_POST['action'] ?? '';
    if ($pid) {
        if ($action === 'add') {
            $conn->query("INSERT IGNORE INTO wishlists (user_id, product_id) VALUES ($uid, $pid)");
        } elseif ($action === 'remove') {
            $conn->query("DELETE FROM wishlists WHERE user_id=$uid AND product_id=$pid");
        }
    }
    // AJAX response
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
        $inWish = $conn->query("SELECT id FROM wishlists WHERE user_id=$uid AND product_id=$pid")->num_rows > 0;
        header('Content-Type: application/json');
        echo json_encode(['in_wishlist' => $inWish]);
        exit;
    }
    header('Location: wishlist.php'); exit;
}

$items = $conn->query("SELECT p.*, c.name as cat_name FROM wishlists w
    JOIN products p ON w.product_id=p.id
    LEFT JOIN categories c ON p.category_id=c.id
    WHERE w.user_id=$uid ORDER BY w.created_at DESC");
$rows = $items->fetch_all(MYSQLI_ASSOC);
?>

<div class="page-hero">
    <div class="container">
        <h2><i class="bi bi-heart me-2"></i>My Wishlist</h2>
        <p><?= count($rows) ?> saved item<?= count($rows)!=1?'s':'' ?></p>
    </div>
</div>

<div class="container pb-5">
<?php if (empty($rows)): ?>
    <div class="text-center py-5 card-clean p-5 mt-2">
        <div style="font-size:4rem;margin-bottom:16px">💔</div>
        <h4 class="text-muted fw-600">Your wishlist is empty</h4>
        <p class="text-muted small mb-4">Save products you love and come back to them later.</p>
        <a href="products.php" class="btn-primary-custom btn px-5">Browse Products</a>
    </div>
<?php else: ?>
    <div class="row g-4 mt-1">
    <?php foreach ($rows as $p): ?>
    <div class="col-6 col-md-4 col-lg-3">
        <div class="card product-card h-100">
            <div style="overflow:hidden;position:relative">
                <img src="<?= strpos($p['image'],'http')===0 ? htmlspecialchars($p['image']) : 'assets/images/'.htmlspecialchars($p['image']) ?>"
                     class="card-img-top" alt="<?= htmlspecialchars($p['name']) ?>"
                     onerror="this.src='assets/images/placeholder.jpg'">
                <form method="POST" style="position:absolute;top:8px;right:8px">
                    <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                    <input type="hidden" name="action" value="remove">
                    <button style="background:rgba(255,255,255,.9);border:none;border-radius:50%;width:32px;height:32px;cursor:pointer;color:#e94560" title="Remove from wishlist">
                        <i class="bi bi-heart-fill"></i>
                    </button>
                </form>
            </div>
            <div class="card-body d-flex flex-column">
                <span class="cat-badge"><?= htmlspecialchars($p['cat_name'] ?? '') ?></span>
                <h6 class="card-title"><?= htmlspecialchars($p['name']) ?></h6>
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
<?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>
