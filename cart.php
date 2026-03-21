<?php
require_once 'includes/header.php';
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }
$uid = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add') {
        $pid = (int)$_POST['product_id'];
        $qty = max(1, (int)($_POST['quantity'] ?? 1));
        $res = $conn->query("SELECT id FROM cart WHERE user_id=$uid LIMIT 1");
        if ($res->num_rows === 0) { $conn->query("INSERT INTO cart (user_id) VALUES ($uid)"); $cart_id = $conn->insert_id; }
        else $cart_id = $res->fetch_assoc()['id'];
        $ex = $conn->query("SELECT id,quantity FROM cart_items WHERE cart_id=$cart_id AND product_id=$pid");
        if ($ex->num_rows > 0) { $row=$ex->fetch_assoc(); $conn->query("UPDATE cart_items SET quantity=".($row['quantity']+$qty)." WHERE id={$row['id']}"); }
        else $conn->query("INSERT INTO cart_items (cart_id,product_id,quantity) VALUES ($cart_id,$pid,$qty)");
        header('Location: cart.php'); exit;
    }
    if ($action === 'update') { $conn->query("UPDATE cart_items SET quantity=".max(1,(int)$_POST['quantity'])." WHERE id=".(int)$_POST['item_id']); header('Location: cart.php'); exit; }
    if ($action === 'remove') { $conn->query("DELETE FROM cart_items WHERE id=".(int)$_POST['item_id']); header('Location: cart.php'); exit; }
}

$items = $conn->query("SELECT ci.id,ci.quantity,p.name,p.price,p.image,p.stock,p.id as pid
    FROM cart c JOIN cart_items ci ON c.id=ci.cart_id JOIN products p ON ci.product_id=p.id WHERE c.user_id=$uid");
$rows  = $items->fetch_all(MYSQLI_ASSOC);
$subtotal = array_sum(array_map(fn($r) => $r['price'] * $r['quantity'], $rows));
$shipping = $subtotal >= 50000 ? 0 : ($subtotal > 0 ? 2000 : 0);
$grand    = $subtotal + $shipping;
?>

<div class="page-hero">
    <div class="container">
        <h2><i class="bi bi-cart3 me-2"></i>Shopping Cart</h2>
        <p><?= count($rows) ?> item<?= count($rows)!=1?'s':'' ?> in your cart</p>
    </div>
</div>

<div class="container pb-5">
<?php if (empty($rows)): ?>
    <div class="text-center py-5 card-clean p-5 mt-2">
        <div style="font-size:4rem;margin-bottom:16px">🛒</div>
        <h4 class="text-muted fw-600">Your cart is empty</h4>
        <p class="text-muted small mb-4">Looks like you haven't added anything yet.</p>
        <a href="products.php" class="btn-primary-custom btn px-5">Browse Products</a>
    </div>
<?php else: ?>
<div class="row g-4 mt-1">

    <!-- Cart Items -->
    <div class="col-lg-8">
        <div class="card-clean overflow-hidden">
            <div class="p-4 border-bottom d-flex align-items-center justify-content-between">
                <h6 class="fw-700 mb-0" style="color:var(--dark)">Cart Items</h6>
                <form method="POST" onsubmit="return confirm('Clear entire cart?')">
                    <input type="hidden" name="action" value="clear_all">
                    <a href="products.php" class="btn btn-sm btn-outline-secondary" style="border-radius:8px;font-size:.78rem">
                        <i class="bi bi-plus me-1"></i>Add More
                    </a>
                </form>
            </div>

            <?php foreach ($rows as $r): ?>
            <div class="d-flex align-items-center gap-3 p-4 border-bottom cart-item-row" style="transition:background .15s">
                <!-- Image -->
                <a href="product.php?id=<?= $r['pid'] ?>">
                    <img src="<?= strpos($r['image'],'http')===0 ? htmlspecialchars($r['image']) : 'assets/images/'.htmlspecialchars($r['image']) ?>"
                         style="width:80px;height:80px;object-fit:cover;border-radius:12px;flex-shrink:0"
                         onerror="this.src='assets/images/placeholder.jpg'">
                </a>
                <!-- Info -->
                <div class="flex-grow-1 min-w-0">
                    <a href="product.php?id=<?= $r['pid'] ?>" class="text-decoration-none">
                        <div class="fw-600" style="color:var(--dark);font-size:.92rem"><?= htmlspecialchars($r['name']) ?></div>
                    </a>
                    <div class="text-muted small mt-1">RWF <?= number_format($r['price']) ?> each</div>
                </div>
                <!-- Qty -->
                <form method="POST" class="d-flex align-items-center gap-2 flex-shrink-0">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="item_id" value="<?= $r['id'] ?>">
                    <div class="qty-control">
                        <button type="submit" name="quantity" value="<?= max(1,$r['quantity']-1) ?>">−</button>
                        <input type="number" name="quantity" value="<?= $r['quantity'] ?>" min="1" max="<?= $r['stock'] ?>" readonly style="width:44px">
                        <button type="submit" name="quantity" value="<?= min($r['stock'],$r['quantity']+1) ?>">+</button>
                    </div>
                </form>
                <!-- Subtotal -->
                <div class="text-end flex-shrink-0" style="min-width:100px">
                    <div class="fw-700" style="color:var(--accent)">RWF <?= number_format($r['price']*$r['quantity']) ?></div>
                    <form method="POST" class="mt-1">
                        <input type="hidden" name="action" value="remove">
                        <input type="hidden" name="item_id" value="<?= $r['id'] ?>">
                        <button class="btn btn-sm" style="color:#dc3545;background:none;border:none;font-size:.78rem;padding:0">
                            <i class="bi bi-trash me-1"></i>Remove
                        </button>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Summary -->
    <div class="col-lg-4">
        <div class="card-clean p-4 sticky-top" style="top:80px">
            <h6 class="fw-700 mb-4" style="color:var(--dark)">Order Summary</h6>

            <div class="d-flex justify-content-between mb-2 small">
                <span class="text-muted">Subtotal (<?= count($rows) ?> items)</span>
                <span>RWF <?= number_format($subtotal) ?></span>
            </div>
            <div class="d-flex justify-content-between mb-3 small">
                <span class="text-muted">Shipping</span>
                <?php if ($shipping === 0): ?>
                <span class="fw-600" style="color:#28a745">FREE 🎉</span>
                <?php else: ?>
                <span>RWF <?= number_format($shipping) ?></span>
                <?php endif; ?>
            </div>
            <hr>
            <div class="d-flex justify-content-between mb-4">
                <span class="fw-700 fs-6">Total</span>
                <span class="fw-800 fs-5" style="color:var(--accent)">RWF <?= number_format($grand) ?></span>
            </div>

            <?php if ($shipping > 0): ?>
            <div class="p-3 mb-3 small" style="background:#fff8e1;border-radius:10px;border-left:3px solid #f5a623">
                💡 Add <strong>RWF <?= number_format(50000-$subtotal) ?></strong> more for <strong>free shipping</strong>
            </div>
            <?php else: ?>
            <div class="p-3 mb-3 small" style="background:#e8f5e9;border-radius:10px;border-left:3px solid #28a745">
                🎉 You qualify for <strong>free shipping!</strong>
            </div>
            <?php endif; ?>

            <a href="checkout.php" class="btn w-100 mb-2 fw-700"
               style="background:linear-gradient(135deg,var(--primary),var(--accent));color:#fff;border-radius:12px;padding:12px">
                <i class="bi bi-bag-check me-2"></i>Proceed to Checkout
            </a>
            <a href="products.php" class="btn btn-outline-secondary w-100" style="border-radius:12px;font-size:.88rem">
                <i class="bi bi-arrow-left me-1"></i>Continue Shopping
            </a>

            <!-- Payment icons -->
            <div class="text-center mt-4">
                <div class="text-muted small mb-2">We accept</div>
                <div class="d-flex justify-content-center gap-2 flex-wrap">
                    <?php foreach (['💵 COD','📱 MoMo','📱 Airtel','💳 Card','🏦 Bank'] as $pm): ?>
                    <span style="background:#f4f6fb;border-radius:6px;padding:3px 10px;font-size:.72rem;color:#555"><?= $pm ?></span>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

</div>
<?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>
