<?php
require_once 'includes/header.php';
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }
$uid = $_SESSION['user_id'];

// Handle share actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['share_action'])) {
    $action = $_POST['share_action'];
    $productIds = isset($_POST['product_ids']) ? array_map('intval', $_POST['product_ids']) : [];
    
    if ($action === 'generate_link' && !empty($productIds)) {
        // Generate shareable link for selected products
        $shareToken = bin2hex(random_bytes(32));
        $productList = implode(',', $productIds);
        
        // Store in session for simplicity (or use database)
        $_SESSION['wishlist_share_' . $shareToken] = [
            'user_id' => $uid,
            'products' => $productList,
            'created_at' => time()
        ];
        
        $shareUrl = SITE_URL . '/shared_wishlist.php?token=' . $shareToken;
        $shareMessage = "Check out my wishlist on ShopAI! View products here: $shareUrl";
    }
}

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

$items = $conn->query("SELECT p.*, c.name as cat_name, p.avg_rating, p.review_count FROM wishlists w
    JOIN products p ON w.product_id=p.id
    LEFT JOIN categories c ON p.category_id=c.id
    WHERE w.user_id=$uid ORDER BY w.created_at DESC");
$rows = $items->fetch_all(MYSQLI_ASSOC);
?>

<div class="page-hero">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div>
                <h2><i class="bi bi-heart me-2"></i>My Wishlist</h2>
                <p><?= count($rows) ?> saved item<?= count($rows)!=1?'s':'' ?></p>
            </div>
            <?php if (!empty($rows)): ?>
            <div class="d-flex gap-2">
                <button class="btn btn-outline-primary" onclick="selectAllProducts()">
                    <i class="bi bi-check-all me-1"></i>Select All
                </button>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#shareModal" onclick="prepareShare()">
                    <i class="bi bi-share me-1"></i>Share
                </button>
                <button class="btn btn-outline-success" onclick="addAllToCart()">
                    <i class="bi bi-cart-plus me-1"></i>Add All to Cart
                </button>
            </div>
            <?php endif; ?>
        </div>
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
    <div class="col-6 col-md-4 col-lg-3" data-product-id="<?= $p['id'] ?>">
        <div class="card product-card h-100">
            <div style="overflow:hidden;position:relative">
                <img src="<?= strpos($p['image'],'http')===0 ? htmlspecialchars($p['image']) : 'assets/images/'.htmlspecialchars($p['image']) ?>"
                     class="card-img-top" alt="<?= htmlspecialchars($p['name']) ?>"
                     onerror="this.src='assets/images/placeholder.jpg'">
                <div style="position:absolute;top:8px;left:8px;z-index:2">
                    <input type="checkbox" class="product-select" value="<?= $p['id'] ?>" style="width:18px;height:18px;cursor:pointer">
                </div>
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
<?php endif; ?>
</div>

<script>
// Select all products
function selectAllProducts() {
    const checkboxes = document.querySelectorAll('.product-select');
    const allChecked = Array.from(checkboxes).every(cb => cb.checked);
    checkboxes.forEach(cb => cb.checked = !allChecked);
}

// Prepare share modal
function prepareShare() {
    const selected = Array.from(document.querySelectorAll('.product-select:checked'))
                          .map(cb => cb.value);
    if (selected.length === 0) {
        alert('Please select at least one product to share');
        return false;
    }
    document.getElementById('shareProductIds').value = selected.join(',');
}

// Generate share link
async function generateShareLink() {
    const productIds = document.getElementById('shareProductIds').value;
    if (!productIds) {
        alert('Please select products to share');
        return;
    }
    
    const formData = new FormData();
    formData.append('share_action', 'generate_link');
    formData.append('product_ids', productIds);
    
    try {
        const response = await fetch('wishlist.php', {
            method: 'POST',
            body: formData
        });
        
        if (response.ok) {
            // Reload to get the share URL from session
            window.location.reload();
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Failed to generate share link');
    }
}

// Copy share link
function copyShareLink() {
    const shareUrl = document.getElementById('shareUrlDisplay');
    if (shareUrl) {
        shareUrl.select();
        document.execCommand('copy');
        alert('Link copied to clipboard!');
    }
}

// Share via email
function shareViaEmail() {
    const shareUrl = document.getElementById('shareUrlDisplay').value;
    const subject = encodeURIComponent('Check out my wishlist on ShopAI!');
    const body = encodeURIComponent(`Hi!\n\nI wanted to share my wishlist with you. Check out these amazing products:\n\n${shareUrl}\n\nHappy shopping!`);
    window.location.href = `mailto:?subject=${subject}&body=${body}`;
}

// Add all to cart
async function addAllToCart() {
    const selected = Array.from(document.querySelectorAll('.product-select:checked'))
                          .map(cb => parseInt(cb.value));
    
    if (selected.length === 0) {
        alert('Please select at least one product to add to cart');
        return;
    }
    
    // Redirect to cart with add action
    window.location.href = `cart.php?add_wishlist_products=${selected.join(',')}`;
}
</script>

<!-- Share Modal -->
<div class="modal fade" id="shareModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-share me-2"></i>Share Your Wishlist</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <?php if (isset($shareUrl)): ?>
                <div class="alert alert-success">
                    <strong>Share Link Generated!</strong>
                    <div class="mt-2">
                        <input type="text" class="form-control mb-2" id="shareUrlDisplay" value="<?= htmlspecialchars($shareUrl) ?>" readonly>
                        <button class="btn btn-sm btn-primary" onclick="copyShareLink()">
                            <i class="bi bi-clipboard me-1"></i>Copy Link
                        </button>
                        <button class="btn btn-sm btn-outline-success ms-2" onclick="shareViaEmail()">
                            <i class="bi bi-envelope me-1"></i>Share via Email
                        </button>
                    </div>
                </div>
                <?php else: ?>
                <p>Select how you want to share your wishlist:</p>
                <form method="POST">
                    <input type="hidden" name="share_action" value="generate_link">
                    <input type="hidden" name="product_ids" id="shareProductIds">
                    <div class="d-grid gap-2">
                        <button type="button" class="btn btn-outline-primary" onclick="generateShareLink()">
                            <i class="bi bi-link-45deg me-2"></i>Generate Shareable Link
                        </button>
                        <button type="button" class="btn btn-outline-success" onclick="shareViaEmail()">
                            <i class="bi bi-envelope me-2"></i>Send via Email
                        </button>
                    </div>
                </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
