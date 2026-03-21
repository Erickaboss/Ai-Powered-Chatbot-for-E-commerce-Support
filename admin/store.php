<?php
require_once 'includes/admin_header.php';

$active_cat = (int)($_GET['cat'] ?? 0);
$search     = $conn->real_escape_string(trim($_GET['search'] ?? ''));
$view       = $_GET['view'] ?? 'remaining'; // remaining | shipped | all

// Categories
$categories = $conn->query("
    SELECT c.id, c.name, COUNT(p.id) as total, SUM(p.stock) as total_stock
    FROM categories c
    LEFT JOIN products p ON p.category_id = c.id
    GROUP BY c.id ORDER BY c.id
");

$cat_icons = ['bi-phone','bi-laptop','bi-tv','bi-house-door','bi-person','bi-bag-heart',
              'bi-basket','bi-heart-pulse','bi-bicycle','bi-emoji-smile','bi-lamp',
              'bi-car-front','bi-book','bi-gem','bi-controller'];

// Build WHERE
$where_parts = ["1=1"];
if ($active_cat) $where_parts[] = "p.category_id = $active_cat";
if ($search)     $where_parts[] = "(p.name LIKE '%$search%')";
if ($view === 'remaining') $where_parts[] = "p.stock > 0";
$where = "WHERE " . implode(" AND ", $where_parts);

// Get products with shipped quantity
$products = $conn->query("
    SELECT p.*, c.name as cat_name,
           COALESCE(SUM(oi.quantity), 0) as shipped_qty
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    LEFT JOIN order_items oi ON oi.product_id = p.id
    LEFT JOIN orders o ON oi.order_id = o.id AND o.status IN ('shipped','delivered')
    $where
    GROUP BY p.id
    ORDER BY c.id, p.name
");

$total_products = $products ? $products->num_rows : 0;

// Summary stats
$stats = $conn->query("
    SELECT
        COUNT(DISTINCT p.id) as total_products,
        SUM(p.stock) as total_remaining,
        COALESCE(SUM(oi_shipped.qty), 0) as total_shipped
    FROM products p
    LEFT JOIN (
        SELECT oi.product_id, SUM(oi.quantity) as qty
        FROM order_items oi
        JOIN orders o ON oi.order_id = o.id AND o.status IN ('shipped','delivered')
        GROUP BY oi.product_id
    ) oi_shipped ON oi_shipped.product_id = p.id
")->fetch_assoc();
?>
<div class="admin-content">

    <!-- Top bar -->
    <div class="admin-topbar mb-4">
        <div>
            <h5 class="page-title mb-0"><i class="bi bi-shop me-2 text-primary"></i>Store Inventory</h5>
            <div style="font-size:.8rem;color:#888;margin-top:2px">Remaining stock &amp; shipped products overview</div>
        </div>
        <a href="products.php" class="btn btn-sm btn-outline-primary">
            <i class="bi bi-pencil me-1"></i>Manage Products
        </a>
    </div>

    <!-- Stats cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div style="background:linear-gradient(135deg,#0f3460,#1e5fa8);border-radius:14px;padding:20px;color:#fff">
                <div style="font-size:.8rem;opacity:.75;margin-bottom:4px">Total Products</div>
                <div style="font-size:2rem;font-weight:800"><?= number_format($stats['total_products']) ?></div>
                <div style="font-size:.75rem;opacity:.65">across all categories</div>
            </div>
        </div>
        <div class="col-md-4">
            <div style="background:linear-gradient(135deg,#2e7d32,#43a047);border-radius:14px;padding:20px;color:#fff">
                <div style="font-size:.8rem;opacity:.75;margin-bottom:4px">Remaining in Store</div>
                <div style="font-size:2rem;font-weight:800"><?= number_format($stats['total_remaining']) ?></div>
                <div style="font-size:.75rem;opacity:.65">units still in stock</div>
            </div>
        </div>
        <div class="col-md-4">
            <div style="background:linear-gradient(135deg,#e94560,#c62828);border-radius:14px;padding:20px;color:#fff">
                <div style="font-size:.8rem;opacity:.75;margin-bottom:4px">Shipped / Delivered</div>
                <div style="font-size:2rem;font-weight:800"><?= number_format($stats['total_shipped']) ?></div>
                <div style="font-size:.75rem;opacity:.65">units shipped or delivered</div>
            </div>
        </div>
    </div>

    <!-- Filter bar -->
    <div class="admin-card mb-4">
        <div class="card-body-pad">
            <form method="GET" class="row g-2 align-items-end">
                <!-- View toggle -->
                <div class="col-md-3">
                    <label class="form-label small fw-600 mb-1">View</label>
                    <select name="view" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="remaining" <?= $view==='remaining'?'selected':'' ?>>Remaining in Store</option>
                        <option value="shipped"   <?= $view==='shipped'  ?'selected':'' ?>>Shipped Products</option>
                        <option value="all"       <?= $view==='all'      ?'selected':'' ?>>All Products</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-600 mb-1">Search</label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input type="text" name="search" class="form-control"
                               placeholder="Product name..." value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
                    </div>
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-600 mb-1">Category</label>
                    <select name="cat" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="0">All Categories</option>
                        <?php $categories->data_seek(0); while ($c = $categories->fetch_assoc()): ?>
                        <option value="<?= $c['id'] ?>" <?= $active_cat==$c['id']?'selected':'' ?>>
                            <?= htmlspecialchars($c['name']) ?>
                        </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-sm btn-primary w-100">
                        <i class="bi bi-funnel me-1"></i>Filter
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Result count -->
    <div class="d-flex align-items-center gap-2 mb-3">
        <span class="badge" style="background:var(--primary);font-size:.8rem;padding:6px 12px">
            <?= $total_products ?> products shown
        </span>
        <span style="font-size:.8rem;color:#888">
            <?php if ($view==='remaining'): ?>Showing products still in stock
            <?php elseif ($view==='shipped'): ?>Showing products that have been shipped/delivered
            <?php else: ?>Showing all products
            <?php endif; ?>
        </span>
    </div>

    <?php if ($total_products === 0): ?>
    <div class="admin-card">
        <div class="card-body-pad text-center py-5">
            <i class="bi bi-inbox" style="font-size:3rem;color:#ccc"></i>
            <h5 class="mt-3 text-muted">No products found</h5>
            <a href="store.php" class="btn btn-primary btn-sm mt-2">Clear Filters</a>
        </div>
    </div>
    <?php else: ?>

    <?php
    $grouped = [];
    $products->data_seek(0);
    while ($p = $products->fetch_assoc()) {
        // For shipped view: only show products that have been shipped
        if ($view === 'shipped' && $p['shipped_qty'] == 0) continue;
        $grouped[$p['cat_name'] ?? 'Uncategorized'][] = $p;
    }

    $icon_idx = 0;
    foreach ($grouped as $cat_name => $items):
        if (empty($items)) { $icon_idx++; continue; }
    ?>
    <div class="admin-card mb-4">
        <div class="card-head" style="background:linear-gradient(135deg,var(--dark),var(--primary));color:#fff;border-radius:16px 16px 0 0">
            <div class="d-flex align-items-center gap-3">
                <div style="width:40px;height:40px;background:rgba(255,255,255,.15);border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:1.2rem">
                    <i class="bi <?= $cat_icons[$icon_idx % 15] ?>"></i>
                </div>
                <div>
                    <h6 style="color:#fff;margin:0;font-size:1rem;font-weight:700"><?= htmlspecialchars($cat_name) ?></h6>
                    <div style="font-size:.75rem;color:rgba(255,255,255,.6)"><?= count($items) ?> products</div>
                </div>
            </div>
            <span style="background:rgba(255,255,255,.15);color:#fff;border-radius:50px;padding:4px 14px;font-size:.78rem;font-weight:600">
                <?= count($items) ?> items
            </span>
        </div>

        <div style="padding:16px">
            <div class="row g-3">
                <?php foreach ($items as $p): ?>
                <div class="col-6 col-md-3 col-lg-2">
                    <div style="background:#fff;border:1px solid #eef0f5;border-radius:12px;overflow:hidden;height:100%;transition:box-shadow .2s"
                         onmouseover="this.style.boxShadow='0 6px 20px rgba(15,52,96,.12)'"
                         onmouseout="this.style.boxShadow='none'">
                        <!-- Image -->
                        <div style="position:relative;height:120px;background:#f8faff;overflow:hidden">
                            <img src="<?= SITE_URL ?>/assets/images/products/<?= htmlspecialchars($p['image']) ?>"
                                 style="width:100%;height:100%;object-fit:cover"
                                 onerror="this.src='<?= SITE_URL ?>/assets/images/placeholder.jpg'">
                            <!-- Stock badge -->
                            <div style="position:absolute;top:5px;right:5px">
                                <?php if ($p['stock'] == 0): ?>
                                <span style="background:#555;color:#fff;border-radius:50px;padding:2px 7px;font-size:.62rem;font-weight:700">Out</span>
                                <?php elseif ($p['stock'] <= 5): ?>
                                <span style="background:#ff5722;color:#fff;border-radius:50px;padding:2px 7px;font-size:.62rem;font-weight:700">Low: <?= $p['stock'] ?></span>
                                <?php else: ?>
                                <span style="background:#2e7d32;color:#fff;border-radius:50px;padding:2px 7px;font-size:.62rem;font-weight:700"><?= $p['stock'] ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <!-- Info -->
                        <div style="padding:9px">
                            <div style="font-size:.75rem;font-weight:600;color:#1a1a2e;line-height:1.3;margin-bottom:6px;
                                        overflow:hidden;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical">
                                <?= htmlspecialchars($p['name']) ?>
                            </div>
                            <div style="color:#e94560;font-weight:700;font-size:.82rem;margin-bottom:6px">
                                RWF <?= number_format($p['price']) ?>
                            </div>
                            <!-- Stock + Shipped row -->
                            <div style="display:flex;gap:4px;flex-wrap:wrap">
                                <span style="background:#e8f5e9;color:#2e7d32;border-radius:50px;padding:2px 8px;font-size:.65rem;font-weight:600">
                                    <i class="bi bi-box-seam"></i> <?= $p['stock'] ?> left
                                </span>
                                <?php if ($p['shipped_qty'] > 0): ?>
                                <span style="background:#fce4ec;color:#c62828;border-radius:50px;padding:2px 8px;font-size:.65rem;font-weight:600">
                                    <i class="bi bi-truck"></i> <?= $p['shipped_qty'] ?> shipped
                                </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php $icon_idx++; endforeach; ?>
    <?php endif; ?>

</div>
<?php require_once 'includes/admin_footer.php'; ?>
