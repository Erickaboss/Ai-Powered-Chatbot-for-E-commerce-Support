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

$subtotal = array_sum(array_map(fn($r) => $r['price'] * $r['quantity'], $rows));
$shipping = $subtotal >= 50000 ? 0 : 2000;
$grand    = $subtotal + $shipping;
$error    = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $address = trim($_POST['address'] ?? '');
    $payment = $_POST['payment'] ?? '';

    // Payment-specific fields
    $momo_number   = trim($_POST['momo_number']   ?? '');
    $airtel_number = trim($_POST['airtel_number'] ?? '');
    $card_number   = trim($_POST['card_number']   ?? '');
    $card_name     = trim($_POST['card_name']     ?? '');
    $card_expiry   = trim($_POST['card_expiry']   ?? '');
    $card_cvv      = trim($_POST['card_cvv']      ?? '');
    $bank_name     = trim($_POST['bank_name']     ?? '');
    $bank_ref      = trim($_POST['bank_ref']      ?? '');

    if (!$address || !$payment) {
        $error = 'Please fill in all required fields.';
    } elseif ($payment === 'momo'   && !$momo_number)   { $error = 'Please enter your MTN MoMo number.'; }
    elseif ($payment === 'airtel'   && !$airtel_number) { $error = 'Please enter your Airtel Money number.'; }
    elseif ($payment === 'card'     && (!$card_number || !$card_name || !$card_expiry || !$card_cvv)) { $error = 'Please fill in all card details.'; }
    elseif ($payment === 'bank'     && !$bank_ref)      { $error = 'Please enter your bank transfer reference.'; }
    else {
        // Build payment note
        $pay_note = match($payment) {
            'momo'   => "MTN MoMo: $momo_number",
            'airtel' => "Airtel Money: $airtel_number",
            'card'   => "Card: **** **** **** " . substr(preg_replace('/\s+/','',$card_number), -4) . " ($card_name)",
            'bank'   => "Bank Transfer — Ref: $bank_ref" . ($bank_name ? " via $bank_name" : ''),
            default  => 'Cash on Delivery',
        };

        $grand_safe   = (float)$grand;
        $address_safe = $conn->real_escape_string($address);
        $payment_safe = $conn->real_escape_string($payment);
        $note_safe    = $conn->real_escape_string($pay_note);

        $conn->query("INSERT INTO orders (user_id, total_price, address, payment_method, status)
                      VALUES ($uid, $grand_safe, '$address_safe', '$payment_safe', 'pending')");
        $order_id = (int)$conn->insert_id;

        if (!$order_id) {
            $error = 'Could not place order. Please try again. (' . $conn->error . ')';
        } else {
            foreach ($rows as $r) {
                $p = (float)$r['price'];
                $conn->query("INSERT INTO order_items (order_id, product_id, quantity, price)
                              VALUES ($order_id, {$r['pid']}, {$r['quantity']}, $p)");
                $conn->query("UPDATE products SET stock=stock-{$r['quantity']} WHERE id={$r['pid']}");
            }
            $conn->query("DELETE ci FROM cart_items ci JOIN cart c ON ci.cart_id=c.id WHERE c.user_id=$uid");

            require_once 'includes/mailer.php';
            $user = $conn->query("SELECT name, email FROM users WHERE id=$uid")->fetch_assoc();
            $orderData = [
                'id'             => $order_id,
                'customer_name'  => $user['name'],
                'address'        => $address,
                'payment_method' => $payment . ' (' . $pay_note . ')',
                'status'         => 'pending',
                'created_at'     => date('Y-m-d H:i:s'),
            ];
            // Normalize items for email
            $emailItems = array_map(fn($r) => ['name'=>$r['name'],'price'=>$r['price'],'quantity'=>$r['quantity']], $rows);
            sendMail(
                $user['email'], $user['name'],
                'Order Confirmed — #' . str_pad($order_id, 6, '0', STR_PAD_LEFT) . ' | ' . SITE_NAME,
                emailOrderConfirmation($orderData, $emailItems)
            );
            // ── Notify admin of new order ──
            sendMail(ADMIN_EMAIL, ADMIN_NAME,
                '[' . SITE_NAME . '] 🛒 New Order #' . str_pad($order_id, 6, '0', STR_PAD_LEFT) . ' from ' . $user['name'],
                emailNewOrderAdmin($orderData, $emailItems)
            );
            header("Location: order_detail.php?id=$order_id&new=1"); exit;
        }
    }
}
?>
<div class="container py-5">
    <h3 class="mb-4"><i class="bi bi-bag-check"></i> Checkout</h3>
    <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <form method="POST" id="checkout-form">
    <div class="row g-4">

        <!-- LEFT: Shipping + Payment -->
        <div class="col-lg-7">

            <!-- Shipping -->
            <div class="card p-4 mb-4">
                <h5 class="mb-3"><i class="bi bi-geo-alt me-2 text-primary"></i>Delivery Address</h5>
                <div class="mb-3">
                    <label class="form-label">Full Delivery Address <span class="text-danger">*</span></label>
                    <textarea name="address" class="form-control" rows="3"
                        placeholder="e.g. KG 15 Ave, Gasabo District, Kigali" required><?= htmlspecialchars($_POST['address'] ?? '') ?></textarea>
                </div>
                <div class="row g-2">
                    <div class="col-6">
                        <label class="form-label">Province</label>
                        <select name="province" class="form-select">
                            <option>Kigali City</option>
                            <option>Northern Province</option>
                            <option>Southern Province</option>
                            <option>Eastern Province</option>
                            <option>Western Province</option>
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="form-label">Phone (for delivery)</label>
                        <input type="tel" name="phone" class="form-control" placeholder="+250 7XX XXX XXX"
                               value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
                    </div>
                </div>
            </div>

            <!-- Payment Method -->
            <div class="card p-4">
                <h5 class="mb-3"><i class="bi bi-credit-card me-2 text-success"></i>Payment Method</h5>
                <input type="hidden" name="payment" id="payment-input" value="<?= htmlspecialchars($_POST['payment'] ?? '') ?>" required>

                <!-- Method selector cards -->
                <div class="row g-2 mb-3" id="payment-methods">

                    <div class="col-6 col-md-4">
                        <div class="pay-method-card <?= ($_POST['payment']??'')==='cod' ? 'selected' : '' ?>" data-method="cod">
                            <i class="bi bi-cash-coin fs-3 text-warning"></i>
                            <div class="fw-semibold mt-1">Cash on Delivery</div>
                            <small class="text-muted">Pay when delivered</small>
                        </div>
                    </div>

                    <div class="col-6 col-md-4">
                        <div class="pay-method-card <?= ($_POST['payment']??'')==='momo' ? 'selected' : '' ?>" data-method="momo">
                            <img src="https://upload.wikimedia.org/wikipedia/commons/thumb/9/93/MTN_Logo.svg/120px-MTN_Logo.svg.png"
                                 style="height:32px;object-fit:contain" onerror="this.style.display='none'">
                            <div class="fw-semibold mt-1">MTN MoMo</div>
                            <small class="text-muted">Mobile Money</small>
                        </div>
                    </div>

                    <div class="col-6 col-md-4">
                        <div class="pay-method-card <?= ($_POST['payment']??'')==='airtel' ? 'selected' : '' ?>" data-method="airtel">
                            <i class="bi bi-phone fs-3 text-danger"></i>
                            <div class="fw-semibold mt-1">Airtel Money</div>
                            <small class="text-muted">Mobile Money</small>
                        </div>
                    </div>

                    <div class="col-6 col-md-4">
                        <div class="pay-method-card <?= ($_POST['payment']??'')==='card' ? 'selected' : '' ?>" data-method="card">
                            <i class="bi bi-credit-card-2-front fs-3 text-primary"></i>
                            <div class="fw-semibold mt-1">Card</div>
                            <small class="text-muted">Visa / Mastercard</small>
                        </div>
                    </div>

                    <div class="col-6 col-md-4">
                        <div class="pay-method-card <?= ($_POST['payment']??'')==='bank' ? 'selected' : '' ?>" data-method="bank">
                            <i class="bi bi-bank fs-3 text-info"></i>
                            <div class="fw-semibold mt-1">Bank Transfer</div>
                            <small class="text-muted">BK, Equity, I&M</small>
                        </div>
                    </div>

                </div>

                <!-- COD panel -->
                <div class="pay-panel" id="panel-cod">
                    <div class="alert alert-warning mb-0">
                        <i class="bi bi-info-circle me-2"></i>
                        <strong>Cash on Delivery:</strong> Pay in cash when your order arrives at your door.
                        Our delivery agent will collect the exact amount: <strong>RWF <?= number_format($grand) ?></strong>.
                    </div>
                </div>

                <!-- MoMo panel -->
                <div class="pay-panel" id="panel-momo">
                    <div class="alert alert-info mb-3">
                        <strong>📱 MTN Mobile Money Instructions:</strong><br>
                        1. Dial <strong>*182#</strong> → Send Money → Enter merchant code <strong>182182</strong><br>
                        2. Amount: <strong>RWF <?= number_format($grand) ?></strong><br>
                        3. Enter your PIN and confirm<br>
                        4. Enter the MoMo number you paid from below.
                    </div>
                    <div class="mb-0">
                        <label class="form-label">MTN MoMo Number <span class="text-danger">*</span></label>
                        <input type="tel" name="momo_number" class="form-control"
                               placeholder="e.g. 0781234567"
                               value="<?= htmlspecialchars($_POST['momo_number'] ?? '') ?>">
                        <?php if (!empty($_POST['payment']) && $_POST['payment']==='momo' && empty($_POST['momo_number'])): ?>
                        <div class="text-danger small mt-1"><i class="bi bi-exclamation-circle me-1"></i>Please enter your MTN MoMo number.</div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Airtel panel -->
                <div class="pay-panel" id="panel-airtel">
                    <div class="alert alert-danger mb-3" style="background:#fff5f5;border-color:#f5c6cb;color:#721c24">
                        <strong>📱 Airtel Money Instructions:</strong><br>
                        1. Dial <strong>*500#</strong> → Make Payment → Business Payment<br>
                        2. Business number: <strong>500500</strong><br>
                        3. Amount: <strong>RWF <?= number_format($grand) ?></strong><br>
                        4. Enter your PIN and confirm<br>
                        5. Enter the Airtel number you paid from below.
                    </div>
                    <div class="mb-0">
                        <label class="form-label">Airtel Money Number <span class="text-danger">*</span></label>
                        <input type="tel" name="airtel_number" id="airtel_number" class="form-control"
                               placeholder="e.g. 0731234567"
                               value="<?= htmlspecialchars($_POST['airtel_number'] ?? '') ?>">
                        <?php if (!empty($_POST['payment']) && $_POST['payment']==='airtel' && empty($_POST['airtel_number'])): ?>
                        <div class="text-danger small mt-1"><i class="bi bi-exclamation-circle me-1"></i>Please enter your Airtel Money number.</div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Card panel -->
                <div class="pay-panel" id="panel-card">
                    <div class="alert alert-primary mb-3" style="background:#f0f4ff;border-color:#b8d0ff;color:#1a1a2e">
                        <i class="bi bi-shield-lock me-2"></i>
                        Your card details are <strong>SSL encrypted</strong> and never stored on our servers.
                    </div>
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Card Number <span class="text-danger">*</span></label>
                            <input type="text" name="card_number" class="form-control" id="card-number-input"
                                   placeholder="1234 5678 9012 3456" maxlength="19"
                                   value="<?= htmlspecialchars($_POST['card_number'] ?? '') ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Cardholder Name <span class="text-danger">*</span></label>
                            <input type="text" name="card_name" class="form-control"
                                   placeholder="Name as on card"
                                   value="<?= htmlspecialchars($_POST['card_name'] ?? '') ?>">
                        </div>
                        <div class="col-6">
                            <label class="form-label">Expiry Date <span class="text-danger">*</span></label>
                            <input type="text" name="card_expiry" class="form-control"
                                   placeholder="MM/YY" maxlength="5"
                                   value="<?= htmlspecialchars($_POST['card_expiry'] ?? '') ?>">
                        </div>
                        <div class="col-6">
                            <label class="form-label">CVV <span class="text-danger">*</span></label>
                            <input type="password" name="card_cvv" class="form-control"
                                   placeholder="3–4 digits" maxlength="4">
                        </div>
                    </div>
                </div>

                <!-- Bank Transfer panel -->
                <div class="pay-panel" id="panel-bank">
                    <div class="alert alert-info mb-3" style="background:#f0faff;border-color:#b8e0ff;color:#0c3547">
                        <strong>🏦 Bank Transfer Details:</strong><br>
                        Bank: <strong>Bank of Kigali (BK)</strong><br>
                        Account Name: <strong><?= SITE_NAME ?></strong><br>
                        Account Number: <strong>00040-0123456-78</strong><br>
                        Amount: <strong>RWF <?= number_format($grand) ?></strong><br>
                        Reference: Use your <strong>name + phone number</strong> as reference.
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Bank Name</label>
                            <select name="bank_name" class="form-select">
                                <option value="">Select your bank</option>
                                <option>Bank of Kigali (BK)</option>
                                <option>Equity Bank</option>
                                <option>I&M Bank</option>
                                <option>Cogebanque</option>
                                <option>GT Bank</option>
                                <option>Ecobank</option>
                                <option>Other</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Transfer Reference <span class="text-danger">*</span></label>
                            <input type="text" name="bank_ref" class="form-control"
                                   placeholder="e.g. JOHN0781234567"
                                   value="<?= htmlspecialchars($_POST['bank_ref'] ?? '') ?>">
                        </div>
                    </div>
                </div>

            </div><!-- /payment card -->
        </div>

        <!-- RIGHT: Order Summary -->
        <div class="col-lg-5">
            <div class="card p-4 sticky-top" style="top:20px">
                <h5 class="mb-3"><i class="bi bi-receipt me-2"></i>Order Summary</h5>
                <hr>
                <?php foreach ($rows as $r): ?>
                <div class="d-flex justify-content-between mb-2 align-items-start">
                    <span class="me-2" style="font-size:.9rem"><?= htmlspecialchars($r['name']) ?> <span class="badge bg-secondary">x<?= $r['quantity'] ?></span></span>
                    <span class="text-nowrap">RWF <?= number_format($r['price'] * $r['quantity']) ?></span>
                </div>
                <?php endforeach; ?>
                <hr>
                <div class="d-flex justify-content-between mb-1">
                    <span>Subtotal</span><span>RWF <?= number_format($subtotal) ?></span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span>Shipping</span>
                    <?php if ($shipping === 0): ?>
                        <span class="text-success fw-semibold">FREE 🎉</span>
                    <?php else: ?>
                        <span>RWF <?= number_format($shipping) ?></span>
                    <?php endif; ?>
                </div>
                <hr>
                <div class="d-flex justify-content-between mb-4">
                    <strong class="fs-5">Total</strong>
                    <strong class="fs-5 price-tag">RWF <?= number_format($grand) ?></strong>
                </div>
                <?php if ($shipping === 0): ?>
                <div class="alert alert-success py-2 small mb-3">🎉 You qualify for <strong>free shipping!</strong></div>
                <?php else: ?>
                <div class="alert alert-light py-2 small mb-3">💡 Add RWF <?= number_format(50000 - $subtotal) ?> more for <strong>free shipping</strong></div>
                <?php endif; ?>
                <button type="submit" class="btn btn-dark btn-lg w-100" id="place-order-btn">
                    <i class="bi bi-check-circle me-2"></i>Place Order
                </button>
                <a href="cart.php" class="btn btn-outline-secondary w-100 mt-2">
                    <i class="bi bi-arrow-left me-1"></i>Back to Cart
                </a>
            </div>
        </div>

    </div>
    </form>
</div>

<style>
.pay-method-card {
    border: 2px solid #dee2e6;
    border-radius: 10px;
    padding: 14px 10px;
    text-align: center;
    cursor: pointer;
    transition: all .2s;
    background: #fff;
    height: 100%;
}
.pay-method-card:hover { border-color: #0f3460; background: #f0f4ff; }
.pay-method-card.selected { border-color: #0f3460; background: #e8eeff; box-shadow: 0 0 0 3px rgba(15,52,96,.15); }
.pay-panel { display: none; margin-top: 16px; }
.pay-panel.active { display: block; }
</style>

<script>
const panels  = document.querySelectorAll('.pay-panel');
const cards   = document.querySelectorAll('.pay-method-card');
const input   = document.getElementById('payment-input');

function selectMethod(method) {
    input.value = method;
    cards.forEach(c => c.classList.toggle('selected', c.dataset.method === method));
    panels.forEach(p => p.classList.remove('active'));
    const panel = document.getElementById('panel-' + method);
    if (panel) panel.classList.add('active');
}

cards.forEach(c => c.addEventListener('click', () => selectMethod(c.dataset.method)));

// Restore selection on page reload (validation error)
const preSelected = input.value;
if (preSelected) {
    selectMethod(preSelected);
    // Scroll to payment section so customer sees the error field
    setTimeout(() => {
        const panel = document.getElementById('panel-' + preSelected);
        if (panel) panel.scrollIntoView({behavior: 'smooth', block: 'center'});
    }, 300);
}

// Card number auto-format
const cardInput = document.getElementById('card-number-input');
if (cardInput) {
    cardInput.addEventListener('input', function () {
        let v = this.value.replace(/\D/g, '').substring(0, 16);
        this.value = v.replace(/(.{4})/g, '$1 ').trim();
    });
}

// Expiry auto-format
document.querySelector('[name="card_expiry"]')?.addEventListener('input', function () {
    let v = this.value.replace(/\D/g, '').substring(0, 4);
    if (v.length >= 3) v = v.substring(0,2) + '/' + v.substring(2);
    this.value = v;
});

// Validate payment selected before submit
document.getElementById('checkout-form').addEventListener('submit', function (e) {
    if (!input.value) {
        e.preventDefault();
        alert('Please select a payment method.');
        document.getElementById('payment-methods').scrollIntoView({behavior:'smooth'});
    }
});
</script>

<?php require_once 'includes/footer.php'; ?>
