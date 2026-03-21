<?php
require_once 'includes/header.php';
$id = (int)($_GET['id'] ?? 0);
$stmt = $conn->prepare("SELECT p.*, c.name as cat_name FROM products p LEFT JOIN categories c ON p.category_id=c.id WHERE p.id=?");
$stmt->bind_param("i", $id);
$stmt->execute();
$p = $stmt->get_result()->fetch_assoc();
if (!$p) { header('Location: products.php'); exit; }
?>
<div class="container py-5">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="index.php">Home</a></li>
            <li class="breadcrumb-item"><a href="products.php">Products</a></li>
            <li class="breadcrumb-item active"><?= htmlspecialchars($p['name']) ?></li>
        </ol>
    </nav>
    <div class="row g-5">
        <div class="col-md-5">
            <img src="assets/images/<?= htmlspecialchars($p['image']) ?>"
                 class="img-fluid rounded shadow"
                 alt="<?= htmlspecialchars($p['name']) ?>"
                 onerror="this.src='assets/images/placeholder.jpg'">
        </div>
        <div class="col-md-7">
            <small class="text-muted"><?= htmlspecialchars($p['cat_name'] ?? '') ?></smal
l>
            <h2 class="mt-1"><?= htmlspecialchars($p['name']) ?></h2>
            <h3 class="price-tag my-3">₱<?= number_format($p['price'], 2) ?></h3>
            <p><?= nl2br(htmlspecialchars($p['description'])) ?></p>
            <?php if ($p['stock'] > 0): ?>
                <span class="badge bg-success mb-3">In Stock (<?= $p['stock'] ?> available)</span>
            <?php else: ?>
                <span class="badge bg-danger mb-3">Out of Stock</span>
            <?php endif; ?>

            <?php if (isset($_SESSION['user_id']) && $p['stock'] > 0): ?>
            <form method="POST" action="cart.php">
                <input type="hidden" name="action" value="add">
                <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                <div class="d-flex align-items-center gap-3 mb-3">
                    <label>Qty:</label>
                    <input type="number" name="quantity" value="1" min="1" max="<?= $p['stock'] ?>" class="form-control" style="width:80px">
                </div>
                <button class="btn btn-dark btn-lg"><i class="bi bi-cart-plus"></i> Add to Cart</button>
            </form>
            <?php elseif (!isset($_SESSION['user_id'])): ?>
                <a href="login.php" class="btn btn-dark btn-lg"><i class="bi bi-lock"></i> Login to Buy</a>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php require_once 'includes/footer.php'; ?>
