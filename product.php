<?php
require_once 'includes/header.php';
$id   = (int)($_GET['id'] ?? 0);
$stmt = $conn->prepare("SELECT p.*, c.name as cat_name FROM products p LEFT JOIN categories c ON p.category_id=c.id WHERE p.id=?");
$stmt->bind_param("i", $id);
$stmt->execute();
$p = $stmt->get_result()->fetch_assoc();
if (!$p) { header('Location: products.php'); exit; }

$related = $conn->query("SELECT * FROM products WHERE category_id={$p['category_id']} AND id!=$id AND stock>0 ORDER BY RAND() LIMIT 4");

// ── Handle review submission ──
$review_msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'review') {
    if (!isset($_SESSION['user_id'])) {
        $review_msg = 'error:Please login to leave a review.';
    } else {
        $uid    = (int)$_SESSION['user_id'];
        $rating = (int)$_POST['rating'];
        $comment = $conn->real_escape_string(trim($_POST['comment']));
        // Check if already reviewed
        $exists = $conn->query("SELECT id FROM reviews WHERE product_id=$id AND user_id=$uid")->num_rows;
        if ($exists) {
            $conn->query("UPDATE reviews SET rating=$rating, comment='$comment' WHERE product_id=$id AND user_id=$uid");
            $review_msg = 'success:Your review has been updated!';
        } else {
            $conn->query("INSERT INTO reviews (product_id, user_id, rating, comment) VALUES ($id, $uid, $rating, '$comment')");
            $review_msg = 'success:Thank you for your review!';
        }
    }
}

// ── Load reviews ──
$reviews    = $conn->query("SELECT r.*, u.name as uname FROM reviews r JOIN users u ON r.user_id=u.id WHERE r.product_id=$id ORDER BY r.created_at DESC");
$reviewStats = $conn->query("SELECT COUNT(*) as total, AVG(rating) as avg_rating FROM reviews WHERE product_id=$id")->fetch_assoc();
$avgRating  = round($reviewStats['avg_rating'] ?? 0, 1);
$totalReviews = (int)($reviewStats['total'] ?? 0);

// Check if current user already reviewed
$userReview = null;
if (isset($_SESSION['user_id'])) {
    $uid = (int)$_SESSION['user_id'];
    $userReview = $conn->query("SELECT * FROM reviews WHERE product_id=$id AND user_id=$uid")->fetch_assoc();
}
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

            <?php if ($totalReviews > 0): ?>
            <div class="d-flex align-items-center gap-2 mb-2">
                <?php for ($s=1;$s<=5;$s++): ?>
                <i class="bi bi-star<?= $s<=$avgRating?'-fill':($s-0.5<=$avgRating?'-half':'')?>" style="color:#f5a623;font-size:1rem"></i>
                <?php endfor; ?>
                <span class="fw-700" style="color:#f5a623"><?= $avgRating ?></span>
                <span class="text-muted small">(<?= $totalReviews ?> review<?= $totalReviews!=1?'s':'' ?>)</span>
            </div>
            <?php endif; ?>

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
            <?php if (isset($_SESSION['user_id'])): ?>
            <form method="POST" action="wishlist.php" class="mt-2">
                <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                <?php
                $inWish = $conn->query("SELECT id FROM wishlists WHERE user_id={$_SESSION['user_id']} AND product_id=$id")->num_rows > 0;
                ?>
                <input type="hidden" name="action" value="<?= $inWish ? 'remove' : 'add' ?>">
                <button class="btn btn-outline-secondary w-100" style="border-radius:12px;font-size:.88rem">
                    <i class="bi bi-heart<?= $inWish ? '-fill text-danger' : '' ?> me-2"></i>
                    <?= $inWish ? 'Remove from Wishlist' : 'Save to Wishlist' ?>
                </button>
            </form>
            <?php endif; ?>
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

    <!-- Reviews Section -->
    <div class="mt-5">
        <div class="section-title mb-1">Customer <span>Reviews</span></div>
        <div class="section-divider mb-4"></div>

        <?php if ($review_msg): [$rtype,$rtext] = explode(':',$review_msg,2); ?>
        <div class="alert alert-<?= $rtype==='error'?'danger':'success' ?> mb-3" style="border-radius:12px;border:none">
            <?= htmlspecialchars($rtext) ?>
        </div>
        <?php endif; ?>

        <div class="row g-4">
            <!-- Write review -->
            <div class="col-lg-4">
                <div class="card-clean p-4">
                    <h6 class="fw-700 mb-3"><?= $userReview ? 'Update Your Review' : 'Write a Review' ?></h6>
                    <?php if (isset($_SESSION['user_id'])): ?>
                    <form method="POST">
                        <input type="hidden" name="action" value="review">
                        <div class="mb-3">
                            <label class="form-label small fw-600">Your Rating</label>
                            <div class="star-select d-flex gap-2" id="starSelect">
                                <?php for ($s=1;$s<=5;$s++): ?>
                                <i class="bi bi-star<?= $userReview && $s<=$userReview['rating']?'-fill':'' ?>"
                                   data-val="<?= $s ?>" style="font-size:1.6rem;cursor:pointer;color:#f5a623"
                                   onclick="setRating(<?= $s ?>)"></i>
                                <?php endfor; ?>
                            </div>
                            <input type="hidden" name="rating" id="ratingInput" value="<?= $userReview['rating'] ?? 0 ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-600">Your Comment</label>
                            <textarea name="comment" class="form-control" rows="3" style="border-radius:10px"
                                      placeholder="Share your experience..."><?= htmlspecialchars($userReview['comment'] ?? '') ?></textarea>
                        </div>
                        <button class="btn w-100" style="background:linear-gradient(135deg,var(--primary),var(--accent));color:#fff;border-radius:10px;font-weight:600">
                            <i class="bi bi-send me-2"></i><?= $userReview ? 'Update Review' : 'Submit Review' ?>
                        </button>
                    </form>
                    <?php else: ?>
                    <p class="text-muted small">Please <a href="login.php" class="fw-600">login</a> to leave a review.</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Reviews list -->
            <div class="col-lg-8">
                <?php if ($totalReviews > 0): ?>
                <div class="d-flex align-items-center gap-3 mb-4 p-3" style="background:#f8faff;border-radius:12px">
                    <div class="text-center">
                        <div style="font-size:3rem;font-weight:800;color:#f5a623;line-height:1"><?= $avgRating ?></div>
                        <div class="d-flex gap-1 justify-content-center my-1">
                            <?php for ($s=1;$s<=5;$s++): ?>
                            <i class="bi bi-star<?= $s<=$avgRating?'-fill':'' ?>" style="color:#f5a623;font-size:.9rem"></i>
                            <?php endfor; ?>
                        </div>
                        <div class="text-muted small"><?= $totalReviews ?> reviews</div>
                    </div>
                </div>
                <?php while ($rv = $reviews->fetch_assoc()): ?>
                <div class="p-3 mb-3" style="background:#fff;border:1px solid #eee;border-radius:12px">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div class="d-flex align-items-center gap-2">
                            <div style="width:36px;height:36px;border-radius:50%;background:linear-gradient(135deg,var(--primary),var(--accent));color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:.85rem">
                                <?= strtoupper(substr($rv['uname'],0,1)) ?>
                            </div>
                            <div>
                                <div class="fw-600 small"><?= htmlspecialchars($rv['uname']) ?></div>
                                <div class="d-flex gap-1">
                                    <?php for ($s=1;$s<=5;$s++): ?>
                                    <i class="bi bi-star<?= $s<=$rv['rating']?'-fill':'' ?>" style="color:#f5a623;font-size:.75rem"></i>
                                    <?php endfor; ?>
                                </div>
                            </div>
                        </div>
                        <small class="text-muted"><?= date('d M Y', strtotime($rv['created_at'])) ?></small>
                    </div>
                    <?php if ($rv['comment']): ?>
                    <p class="mb-0 small" style="color:#555"><?= nl2br(htmlspecialchars($rv['comment'])) ?></p>
                    <?php endif; ?>
                </div>
                <?php endwhile; ?>
                <?php else: ?>
                <div class="text-center py-4 text-muted">
                    <i class="bi bi-star" style="font-size:2.5rem;opacity:.3"></i>
                    <p class="mt-2">No reviews yet. Be the first to review this product!</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Related Products -->
    <?php if ($related && $related->num_rows > 0): ?>    <div class="mt-5">
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
function setRating(val) {
    document.getElementById('ratingInput').value = val;
    document.querySelectorAll('#starSelect i').forEach((s,i) => {
        s.className = 'bi bi-star' + (i < val ? '-fill' : '');
    });
}
</script>

<?php require_once 'includes/footer.php'; ?>
