<?php
require_once 'includes/header.php';

$where = "WHERE 1=1";
$params = [];
$types = "";

if (!empty($_GET['search'])) {
    $s = "%" . $conn->real_escape_string($_GET['search']) . "%";
    $where .= " AND (p.name LIKE '$s' OR p.description LIKE '$s')";
}
if (!empty($_GET['category'])) {
    $cid = (int)$_GET['category'];
    $where .= " AND p.category_id = $cid";
}

$sort = match($_GET['sort'] ?? '') {
    'price_asc'  => 'p.price ASC',
    'price_desc' => 'p.price DESC',
    'newest'     => 'p.created_at DESC',
    default      => 'p.id ASC'
};

$products = $conn->query("SELECT p.*, c.name as cat_name FROM products p LEFT JOIN categories c ON p.category_id=c.id $where ORDER BY $sort");
$categories = $conn->query("SELECT * FROM categories");
?>

<div class="container py-5">
    <div class="row">
        <!-- Sidebar Filters -->
        <div class="col-md-3 mb-4">
            <div class="card p-3">
                <h6 class="fw-bold mb-3">Filter Products</h6>
                <form method="GET">
                    <input type="text" name="search" class="form-control mb-2" placeholder="Search..." value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
                    <label class="form-label small">Category</label>
                    <select name="category" class="form-select mb-2">
                        <option value="">All Categories</option>
                        <?php while ($c = $categories->fetch_assoc()): ?>
                        <option value="<?= $c['id'] ?>" <?= ($_GET['category'] ?? '') == $c['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($c['name']) ?>
                        </option>
                        <?php endwhile; ?>
                    </select>
                    <label class="form-label small">Sort By</label>
                    <select name="sort" class="form-select mb-3">
                        <option value="">Default</option>
                        <option value="price_asc"  <?= ($_GET['sort'] ?? '') === 'price_asc'  ? 'selected' : '' ?>>Price: Low to High</option>
                        <option value="price_desc" <?= ($_GET['sort'] ?? '') === 'price_desc' ? 'selected' : '' ?>>Price: High to Low</option>
                        <option value="newest"     <?= ($_GET['sort'] ?? '') === 'newest'     ? 'selected' : '' ?>>Newest</option>
                    </select>
                    <button class="btn btn-dark w-100">Apply</button>
                </form>
            </div>
        </div>

        <!-- Product Grid -->
        <div class="col-md-9">
            <h4 class="mb-4">All Products <small class="text-muted fs-6">(<?= $products->num_rows ?> items)</small></h4>
            <div class="row g-4">
                <?php if ($products->num_rows === 0): ?>
                    <div class="col-12 text-center py-5">
                        <i class="bi bi-search fs-1 text-muted"></i>
                        <p class="mt-2 text-muted">No products found. Try a different search.</p>
                    </div>
                <?php endif; ?>
                <?php while ($p = $products->fetch_assoc()): ?>
                <div class="col-6 col-lg-4">
                    <div class="card product-card h-100">
                        <img src="assets/images/<?= htmlspecialchars($p['image']) ?>"
                             class="card-img-top"
                             alt="<?= htmlspecialchars($p['name']) ?>"
                             onerror="this.src='assets/images/placeholder.jpg'">
                        <div class="card-body d-flex flex-column">
                            <small class="text-muted"><?= htmlspecialchars($p['cat_name'] ?? '') ?></small>
                            <h6 class="card-title mt-1"><?= htmlspecialchars($p['name']) ?></h6>
                            <p class="price-tag mt-auto mb-2">₱<?= number_format($p['price'], 2) ?></p>
                            <?php if ($p['stock'] > 0): ?>
                                <span class="badge bg-success mb-2">In Stock (<?= $p['stock'] ?>)</span>
                            <?php else: ?>
                                <span class="badge bg-danger mb-2">Out of Stock</span>
                            <?php endif; ?>
                            <a href="product.php?id=<?= $p['id'] ?>" class="btn btn-dark btn-sm">View Details</a>
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
