<?php
require_once 'includes/header.php';
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }
$uid = $_SESSION['user_id'];

// Handle cart actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $pid = (int)$_POST['product_id'];
        $qty = max(1, (int)($_POST['quantity'] ?? 1));
        // Get or create cart
        $res = $conn->query("SELECT id FROM cart WHERE user_id=$uid LIMIT 1");
        if ($res->num_rows === 0) {
            $conn->query("INSERT INTO cart (user_id) VALUES ($uid)");
            $cart_id = $conn->insert_id;
        } else {
            $cart_id = $res->fetch_assoc()['id'];
        }
        // Check if item exists
        $ex = $conn->query("SELECT id, quantity FROM cart_items WHERE cart_id=$cart_id AND product_id=$pid");
        if ($ex->num_rows > 0) {
            $row = $ex->fetch_assoc();
            $new_qty = $row['quantity'] + $qty;
            $conn->query("UPDATE cart_items SET quantity=$new_qty WHERE id={$row['id']}");
        } else {
            $conn->query("INSERT INTO cart_items (cart_id, product_id, quantity) VALUES ($cart_id, $pid, $qty)");
        }
        header('Location: cart.php'); exit;
    }

    if ($action === 'update') {
        $item_id = (int)$_POST['item_id'];
        $qty = max(1, (int)$_POST['quantity']);
        $conn->query("UPDATE cart_items SET quantity=$qty WHERE id=$item_id");
        header('Location: cart.php'); exit;
    }

    if ($action === 'remove') {
        $item_id = (int)$_POST['item_id'];
        $conn->query("DELETE FROM cart_items WHERE id=$item_id");
        header('Location: cart.php'); exit;
    }
}

// Fetch cart items
$items = $conn->query("SELECT ci.id, ci.quantity, p.name, p.price, p.image, p.stock
    FROM cart c
    JOIN cart_items ci ON c.id=ci.cart_id
    JOIN products p ON ci.product_id=p.id
    WHERE c.user_id=$uid");

$total = 0;
$rows = $items->fetch_all(MYSQLI_ASSOC);
foreach ($rows as $r) $total += $r['price'] * $r['quantity'];
?>
<div class="container py-5">
    <h3 class="mb-4"><i class="bi bi-cart3"></i> Shopping Cart</h3>
    <?php if (empty($rows)): ?>
        <div class="text-center py-5">
            <i class="bi bi-cart-x fs-1 text-muted"></i>
            <p class="mt-3">Your cart is empty. <a href="products.php">Continue shopping</a></p>
        </div>
    <?php else: ?>
    <div class="row">
        <div class="col-md-8">
            <?php foreach ($rows as $r): ?>
            <div class="card mb-3 p-3">
                <div class="row align-items-center">
                    <div class="col-2">
                        <img src="assets/images/<?= htmlspecialchars($r['image']) ?>"
                             class="img-fluid rounded"
                             onerror="this.src='assets/images/placeholder.jpg'">
                    </div>
                    <div class="col-5">
                        <h6 class="mb-0"><?= htmlspecialchars($r['name']) ?></h6>
                        <small class="text-muted">₱<?= number_format($r['price'], 2) ?> each</small>
                    </div>
                    <div class="col-3">
                        <form method="POST" class="d-flex gap-1">
                            <input type="hidden" name="action" value="update">
                            <input type="hidden" name="item_id" value="<?= $r['id'] ?>">
                            <input type="number" name="quantity" value="<?= $r['quantity'] ?>" min="1" max="<?= $r['stock'] ?>" class="form-control form-control-sm" style="width:65px">
                            <button class="btn btn-sm btn-outline-secondary">Update</button>
                        </form>
                    </div>
                    <div class="col-2 text-end">
                        <strong>₱<?= number_format($r['price'] * $r['quantity'], 2) ?></strong>
                        <form method="POST" class="mt-1">
                            <input type="hidden" name="action" value="remove">
                            <input type="hidden" name="item_id" value="<?= $r['id'] ?>">
                            <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                        </form>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="col-md-4">
            <div class="card p-4">
                <h5>Order Summary</h5>
                <hr>
                <div class="d-flex justify-content-between mb-2">
                    <span>Subtotal</span><strong>₱<?= number_format($total, 2) ?></strong>
                </div>
                <div class="d-flex justify-content-between mb-3">
                    <span>Shipping</span><span class="text-success">Free</span>
                </div>
                <hr>
                <div class="d-flex justify-content-between mb-3">
                    <strong>Total</strong><strong class="price-tag">₱<?= number_format($total, 2) ?></strong>
                </div>
                <a href="checkout.php" class="btn btn-dark w-100">Proceed to Checkout</a>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>
<?php require_once 'includes/footer.php'; ?>
