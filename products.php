﻿<?php
require_once 'includes/header.php';

$where = "WHERE p.stock >= 0";
if (!empty($_GET['search'])) {
    $s = $conn->real_escape_string($_GET['search']);
    $where .= " AND (p.name LIKE '%$s%' OR p.description LIKE '%$s%' OR p.brand LIKE '%$s%')";
}
if (!empty($_GET['category'])) {
    $where .= " AND p.category_id=" . (int)$_GET['category'];
}
if (!empty($_GET['min_price'])) $where .= " AND p.price>=" . (float)$_GET['min_price'];
if (!empty($_GET['max_price'])) $where .= " AND p.price<=" . (float)$_GET['max_price'];
if (!empty($_GET['in_stock']))  $where .= " AND p.stock>0";

$sort = match($_GET['sort'] ?? '') {
    'price_asc'  => 'p.price ASC',
    'price_desc' => 'p.price DESC',
    'newest'     => 'p.id DESC',
    default      => 'p.id ASC'
};

$products   = $conn->query("SELECT p.*, c.name as cat_name FROM products p LEFT JOIN categories c ON p.category_id=c.id $where ORDER BY $sort");
$categories = $conn->query("SELECT c.*, COUNT(p.id) as cnt FROM categories c LEFT JOIN products p ON p.category_id=c.id AND p.stock>0 GROUP BY c.id ORDER BY c.id");
$active_cat = (int)($_GET['category'] ?? 0);
?>

<!-- Page Hero -->
<div class="page-hero">
    <div class="container">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-2" style="font-size:.8rem">
                <li class="breadcrumb-item"><a href="index.php" style="color:rgba(255,255,255,.6)">Home</a></li>
                <li class="breadcrumb-item active" style="color:rgba(255,255,255,.8)">Products</li>
            </ol>
        </nav>
        <h2><i class="bi bi-grid me-2"></i>All Products</h2>
        <p><?= $products->num_rows ?> products found<?= !empty($_GET['search']) ? ' for "'.htmlspecialchars($_GET['search']).'"' : '' ?></p>
    </div>
</div>

<div class="container pb-5">
    <div class="row g-4">

        <!-- ── Sidebar ── -->
        <div class="col-lg-3">
            <div class="card-clean p-4 sticky-top" style="top:80px">
                <h6 class="fw-700 mb-3" style="color:var(--dark)"><i class="bi bi-funnel me-2 text-primary"></i>Filters</h6>
                <form method="GET" id="filter-form">
                    <!-- Search -->
                    <div class="mb-3">
                        <label class="form-label small fw-600">Search</label>
                        <input type="text" name="search" class="form-control form-control-sm"
                               placeholder="Product name..." value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
                    </div>

                    <!-- Categories -->
                    <div class="mb-3">
                        <label class="form-label small fw-600">Category</label>
                        <div style="max-height:220px;overflow-y:auto">
                            <div class="form-check mb-1">
                                <input class="form-check-input" type="radio" name="category" value="" id="cat-all"
                                       <?= !$active_cat ? 'checked' : '' ?> onchange="this.form.submit()">
                                <label class="form-check-label small" for="cat-all">All Categories</label>
                            </div>
                            <?php while ($c = $categories->fetch_assoc()): ?>
                            <div class="form-check mb-1">
                                <input class="form-check-input" type="radio" name="category"
                                       value="<?= $c['id'] ?>" id="cat-<?= $c['id'] ?>"
                                       <?= $active_cat===$c['id'] ? 'checked' : '' ?> onchange="this.form.submit()">
                                <label class="form-check-label small d-flex justify-content-between" for="cat-<?= $c['id'] ?>">
                                    <?= htmlspecialchars($c['name']) ?>
                                    <span class="text-muted">(<?= $c['cnt'] ?>)</span>
                                </label>
                            </div>
                            <?php endwhile; ?>
                        </div>
                    </div>

                    <!-- Price Range -->
                    <div class="mb-3">
                        <label class="form-label small fw-600">Price Range (RWF)</label>
                        <div class="d-flex gap-2">
                            <input type="number" name="min_price" class="form-control form-control-sm"
                                   placeholder="Min" value="<?= htmlspecialchars($_GET['min_price'] ?? '') ?>">
                            <input type="number" name="max_price" class="form-control form-control-sm"
                                   placeholder="Max" value="<?= htmlspecialchars($_GET['max_price'] ?? '') ?>">
                        </div>
                    </div>

                    <!-- In Stock -->
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="in_stock" value="1" id="in-stock"
                                   <?= !empty($_GET['in_stock']) ? 'checked' : '' ?> onchange="this.form.submit()">
                            <label class="form-check-label small" for="in-stock">In Stock Only</label>
                        </div>
                    </div>

                    <!-- Sort -->
                    <div class="mb-3">
                        <label class="form-label small fw-600">Sort By</label>
                        <select name="sort" class="form-select form-select-sm" onchange="this.form.submit()">
                            <option value="">Default</option>
                            <option value="price_asc"  <?= ($_GET['sort']??'')==='price_asc'  ?'selected':'' ?>>Price: Low → High</option>
                            <option value="price_desc" <?= ($_GET['sort']??'')==='price_desc' ?'selected':'' ?>>Price: High → Low</option>
                            <option value="newest"     <?= ($_GET['sort']??'')==='newest'     ?'selected':'' ?>>Newest First</option>
                        </select>
                    </div>

                    <button type="submit" class="btn-primary-custom btn w-100 mb-2">Apply Filters</button>
                    <a href="products.php" class="btn btn-outline-secondary btn-sm w-100">Clear All</a>
                </form>
            </div>
        </div>

        <!-- ── Product Grid ── -->
        <div class="col-lg-9">
            <?php if ($products->num_rows === 0): ?>
            <div class="text-center py-5 card-clean p-5">
                <i class="bi bi-search" style="font-size:3rem;color:#ccc"></i>
                <h5 class="mt-3 text-muted">No products found</h5>
                <p class="text-muted small">Try adjusting your filters or <a href="products.php">browse all products</a></p>
            </div>
            <?php else: ?>
            <div class="row g-3">
                <?php while ($p = $products->fetch_assoc()): ?>
                <div class="col-6 col-md-4">
                    <div class="card product-card h-100">
                        <div style="overflow:hidden;position:relative">
                            <img src="<?= strpos($p['image'],'http')===0 ? htmlspecialchars($p['image']) : 'assets/images/'.htmlspecialchars($p['image']) ?>"
                                 class="card-img-top"
                                 alt="<?= htmlspecialchars($p['name']) ?>"
                                 onerror="this.src='assets/images/placeholder.jpg'">
                            <?php if ($p['stock'] == 0): ?>
                            <div style="position:absolute;inset:0;background:rgba(0,0,0,.45);display:flex;align-items:center;justify-content:center">
                                <span style="background:#dc3545;color:#fff;border-radius:50px;padding:4px 14px;font-size:.75rem;font-weight:700">Out of Stock</span>
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="card-body d-flex flex-column">
                            <span class="cat-badge"><?= htmlspecialchars($p['cat_name'] ?? '') ?></span>
                            <h6 class="card-title"><?= htmlspecialchars($p['name']) ?></h6>
                            <div class="d-flex align-items-center justify-content-between mt-auto mb-2">
                                <span class="price-tag">RWF <?= number_format($p['price']) ?></span>
                                <?php if ($p['stock'] > 0): ?>
                                <span class="stock-badge-in"><?= $p['stock'] ?> left</span>
                                <?php endif; ?>
                            </div>
                            <a href="product.php?id=<?= $p['id'] ?>" class="btn-add-cart btn">
                                <i class="bi bi-eye me-1"></i>View Details
                            </a>
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
            <?php endif; ?>
        </div>

    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
