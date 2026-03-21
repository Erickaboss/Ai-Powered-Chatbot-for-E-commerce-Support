<?php
require_once 'includes/header.php';
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }
$uid = $_SESSION['user_id'];

// Fetch cart
$items = $conn->query("SELECT ci.quantity, p.id as pid, p.name, p.price, p.stock
    FROM cart c JOIN cart_items ci ON c.id=ci.cart_id JOIN products p ON ci.product_id=p.id
    WHERE c.user_id=$uid");
$rows = $items->fetch_all(MYSQLI_ASSOC);
if (empty($rows)) { header('Location: cart.php'); exit; }

$total = array_sum(array_map(fn($r) => $r['price'] * $r['quantity'], $rows));
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $address = trim($_POST['address'] ?? '');
    $payment = $_POST['payment'] ?? '';
    if (!$address || !$payment) {
        $error = 'Please fill in all fields.';
    } else {
        // Create order
        // Use direct query to avoid bind_param type issues with DECIMAL
        $total_safe   = (float)$total;
        $address_safe = $conn->real_escape_string($address);
        $conn->query("INSERT INTO orders (user_id, total_price, address) VALUES ($uid, $total_safe, '$address_safe')");
        $order_id = (int)$conn->insert_id;

        if ($order_id === 0) {
            $error = 'Could not place order. Please try again. (' . $conn->error . ')';
        } else {
            // Insert order items & update stock
            foreach ($rows as $r) {
                $price_safe = (float)$r['price'];
                $conn->query("INSERT INTO order_items (order_id, product_id, quantity, price) VALUES ($order_id, {$r['pid']}, {$r['quantity']}, $price_safe)");
                $conn->query("UPDATE products SET stock=stock-{$r['quantity']} WHERE id={$r['pid']}");
            }

            // Clear cart
            $conn->query("DELETE ci FROM cart_items ci JOIN cart c ON ci.cart_id=c.id WHERE c.user_id=$uid");

            header("Location: orders.php?success=1&order=$order_id"); exit;
        }
    }
}
?>
<div class="container py-5">
    <h3 class="mb-4"><i class="bi bi-bag-check"></i> Checkout</h3>
    <?php if ($error): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>
    <div class="row">
        <div class="col-md-7">
            <div class="card p-4 mb-4">
                <h5 class="mb-3">Shipping & Payment</h5>
                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label">Delivery Address</label>
                        <textarea name="address" class="form-control" rows="3" placeholder="House No., Street, City, Province" required></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Payment Method</label>
                        <select name="payment" class="form-select" required>
                            <option value="">Select payment method</option>
                            <option value="cod">Cash on Delivery (COD)</option>
                            <option value="gcash">GCash</option>
                            <option value="card">Credit / Debit Card</option>
                            <option value="bank">Bank Transfer</option>
                        </select>
                    </div>
                    <button class="btn btn-dark btn-lg w-100"><i class="bi bi-check-circle"></i> Place Order</button>
                </form>
            </div>
        </div>
        <div class="col-md-5">
            <div class="card p-4">
                <h5>Order Summary</h5>
                <hr>
                <?php foreach ($rows as $r): ?>
                <div class="d-flex justify-content-between mb-1">
                    <span><?= htmlspecialchars($r['name']) ?> x<?= $r['quantity'] ?></span>
                    <span>₱<?= number_format($r['price'] * $r['quantity'], 2) ?></span>
                </div>
                <?php endforeach; ?>
                <hr>
                <div class="d-flex justify-content-between">
                    <strong>Total</strong>
                    <strong class="price-tag">₱<?= number_format($total, 2) ?></strong>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require_once 'includes/footer.php'; ?>
