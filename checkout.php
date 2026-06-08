<?php
require_once 'includes/header.php';
require_once 'includes/inventory.php';
require_once 'includes/security.php';
sendSecurityHeaders();
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }
$uid = $_SESSION['user_id'];

// Fetch cart
$stmtItems = $conn->prepare("SELECT ci.quantity, p.id as pid, p.name, p.price, p.stock
    FROM cart c JOIN cart_items ci ON c.id=ci.cart_id JOIN products p ON ci.product_id=p.id
    WHERE c.user_id=?");
$stmtItems->bind_param("i", $uid);
$stmtItems->execute();
$items = $stmtItems->get_result();
$stmtItems->close();
$rows = $items->fetch_all(MYSQLI_ASSOC);
if (empty($rows)) { header('Location: cart.php'); exit; }

$subtotal = array_sum(array_map(fn($r) => $r['price'] * $r['quantity'], $rows));
$shipping = 0;
$grand    = $subtotal + $shipping;
$error    = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? null)) {
        $error = 'Invalid session token. Please refresh and try again.';
    }
    else {
    $address  = trim($_POST['address'] ?? '');
    $payment  = $_POST['payment'] ?? '';
    $phone    = trim($_POST['phone'] ?? '');
    $province = trim($_POST['province'] ?? '');

    // Payment-specific fields
    $momo_number   = trim($_POST['momo_number']   ?? '');
    $airtel_number = trim($_POST['airtel_number'] ?? '');
    $card_name     = trim($_POST['card_name']     ?? '');
    $card_expiry   = trim($_POST['card_expiry']   ?? '');
    $bank_name     = trim($_POST['bank_name']     ?? '');
    $bank_ref      = trim($_POST['bank_ref']      ?? '');

    if (!$address || !$payment || !$phone) {
        $error = 'Please fill in all required fields (Address, Phone, and Payment Method).';
    } elseif ($payment === 'momo'   && !$momo_number)   { $error = 'Please enter your MTN MoMo number.'; }
    elseif ($payment === 'airtel'   && !$airtel_number) { $error = 'Please enter your Airtel Money number.'; }
    elseif ($payment === 'card'     && (!$card_name || !$card_expiry)) { $error = 'Please fill in cardholder name and expiry.'; }
    elseif ($payment === 'bank'     && !$bank_ref)      { $error = 'Please enter your bank transfer reference.'; }
    else {
        foreach ($rows as $r) {
            if ((int)$r['quantity'] > (int)$r['stock']) {
                $error = htmlspecialchars($r['name']) . ' has only ' . (int)$r['stock'] . ' units available. Please update your cart.';
                break;
            }
        }
    }

    if (!$error) {
        // Build payment note
        $pay_note = match($payment) {
    'momo'   => "MTN MoMo: $momo_number",
    'airtel' => "Airtel Money: $airtel_number",
    'card'   => "Card: **** $card_name, exp $card_expiry",
            'bank'   => "Bank Transfer — Ref: $bank_ref" . ($bank_name ? " via $bank_name" : ''),
            default  => 'Cash on Delivery',
        };

        $grand_safe    = (float)$grand;
        $address_safe  = $conn->real_escape_string($address);
        $payment_safe  = $conn->real_escape_string($payment);
        $phone_safe    = $conn->real_escape_string($phone);
        $province_safe = $conn->real_escape_string($province);
        $note_safe     = $conn->real_escape_string($pay_note);

        $orderColumns = [];
        if ($columnsResult = $conn->query("SHOW COLUMNS FROM orders")) {
            while ($column = $columnsResult->fetch_assoc()) {
                $orderColumns[$column['Field']] = true;
            }
        }

        $insertColumns = ['user_id', 'total_price', 'shipping_fee', 'address', 'payment_method', 'status'];
        $insertValues  = [$uid, $grand_safe, $shipping, $address, $payment, 'pending'];

        if (isset($orderColumns['phone'])) {
            $insertColumns[] = 'phone';
            $insertValues[]  = $phone;
        }
        if (isset($orderColumns['province'])) {
            $insertColumns[] = 'province';
            $insertValues[]  = $province;
        }
        if (isset($orderColumns['payment_details'])) {
            $insertColumns[] = 'payment_details';
            $insertValues[]  = $pay_note;
        }

        try {
            $conn->begin_transaction();
            $placeholders = implode(', ', array_fill(0, count($insertValues), '?'));
            $otypes = '';
            foreach ($insertValues as $v) {
                if (is_int($v)) $otypes .= 'i';
                elseif (is_float($v)) $otypes .= 'd';
                else $otypes .= 's';
            }
            $stmtOrder = $conn->prepare("INSERT INTO orders (" . implode(', ', $insertColumns) . ") VALUES ($placeholders)");
            $stmtOrder->bind_param($otypes, ...$insertValues);
            if (!$stmtOrder->execute()) {
                throw new Exception('Order insert failed: ' . $stmtOrder->error);
            }
            $order_id = (int)$conn->insert_id;
            $stmtOrder->close();

            if (!$order_id) {
                throw new Exception('Order insert failed: ' . $conn->error);
            }

            foreach ($rows as $r) {
                $p = (float)$r['price'];
                $stmtOI = $conn->prepare("INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)");
                $stmtOI->bind_param("iiid", $order_id, $r['pid'], $r['quantity'], $p);
                if (!$stmtOI->execute()) {
                    throw new Exception("Order item insert failed for product {$r['pid']}: " . $stmtOI->error);
                }
                $stmtOI->close();
                if (!applyPurchasedInventory($conn, (int)$r['pid'], (int)$r['quantity'])) {
                    throw new Exception("Inventory retirement failed for product {$r['pid']} order $order_id");
                }
            }
            $stmtClean = $conn->prepare("DELETE ci FROM cart_items ci JOIN cart c ON ci.cart_id=c.id WHERE c.user_id=?");
            $stmtClean->bind_param("i", $uid);
            if (!$stmtClean->execute()) {
                throw new Exception('Cart cleanup failed: ' . $stmtClean->error);
            }
            $stmtClean->close();
            $conn->commit();

            require_once 'includes/mailer.php';
            $stmtUser = $conn->prepare("SELECT name, email FROM users WHERE id=?");
            $stmtUser->bind_param("i", $uid);
            $stmtUser->execute();
            $user = $stmtUser->get_result()->fetch_assoc();
            $stmtUser->close();
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
        } catch (Throwable $e) {
            $conn->rollback();
            error_log('Checkout order failed: ' . $e->getMessage());
            $error = 'Could not place order because one or more products may have just been bought. Please review your cart and try again.';
        }
    }
    }
}
?>
<div class="container py-5">
    <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <form method="POST" id="checkout-form">
    <?= csrfField() ?>
    <input type="hidden" name="address" id="main-address" value="<?= htmlspecialchars($_POST['address'] ?? '') ?>">
    <input type="hidden" name="phone" id="main-phone" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
    <input type="hidden" name="province" id="main-province" value="<?= htmlspecialchars($_POST['province'] ?? '') ?>">
    <input type="hidden" name="payment" value="<?= htmlspecialchars($_POST['payment'] ?? 'momo') ?>">
    <input type="hidden" name="momo_number" id="h-momo">
    <input type="hidden" name="airtel_number" id="h-airtel">
    <input type="hidden" name="card_name" id="h-card-name">
    <input type="hidden" name="card_expiry" id="h-card-exp">
    <input type="hidden" name="bank_ref" id="h-bank">

    <div class="row g-4">
        <!-- LEFT COLUMN -->
        <div class="col-lg-8">
            <!-- Section: Shipping Address -->
            <div class="card border-0 shadow-sm mb-4" style="border-radius:16px">
                <div class="card-body p-4">
                    <h5 class="fw-bold mb-4"><i class="bi bi-geo-alt text-primary me-2"></i>Shipping Address</h5>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="small fw-600 text-muted mb-1">Full Name</label>
                            <input type="text" id="inp-name" class="form-control" value="<?= htmlspecialchars($_SESSION['user_name'] ?? '') ?>" placeholder="Your full name">
                        </div>
                        <div class="col-md-6">
                            <label class="small fw-600 text-muted mb-1">Phone Number</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0">+250</span>
                                <input type="tel" id="inp-phone" class="form-control" placeholder="78XXXXXXX" value="<?= htmlspecialchars(preg_replace('/^\+?250\s*/', '', $_POST['phone'] ?? '')) ?>">
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="small fw-600 text-muted mb-1">Street Address</label>
                            <input type="text" id="inp-street" class="form-control" placeholder="Street, house/apartment/unit">
                        </div>
                        <div class="col-md-5">
                            <label class="small fw-600 text-muted mb-1">City</label>
                            <input type="text" id="inp-city" class="form-control" placeholder="Kigali">
                        </div>
                        <div class="col-md-4">
                            <label class="small fw-600 text-muted mb-1">Province</label>
                            <select id="inp-province" class="form-select">
                                <option value="Kigali City">Kigali City</option>
                                <option value="Northern Province">Northern Province</option>
                                <option value="Southern Province">Southern Province</option>
                                <option value="Eastern Province">Eastern Province</option>
                                <option value="Western Province">Western Province</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="small fw-600 text-muted mb-1">ZIP Code</label>
                            <input type="text" id="inp-zip" class="form-control" placeholder="Optional">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Section: Payment Method -->
            <div class="card border-0 shadow-sm mb-4" style="border-radius:16px">
                <div class="card-body p-4">
                    <h5 class="fw-bold mb-4"><i class="bi bi-credit-card text-primary me-2"></i>Payment Method</h5>
                    <div class="row g-3" id="payment-methods">
                        <!-- MTN MoMo -->
                        <div class="col-md-6">
                            <div class="pay-card selected" data-method="momo">
                                <div class="d-flex align-items-center">
                                    <div class="pay-radio me-3"></div>
                                    <div>
                                        <div class="fw-bold">MTN MoMo</div>
                                        <div class="small text-muted">Pay with Mobile Money</div>
                                    </div>
                                    <span class="badge bg-primary ms-auto" style="font-size:0.6rem">POPULAR</span>
                                </div>
                                <div class="pay-fields" id="fields-momo">
                                    <input type="tel" class="form-control mt-2" placeholder="078XXXXXXX" id="pay-momo">
                                </div>
                            </div>
                        </div>
                        <!-- Airtel Money -->
                        <div class="col-md-6">
                            <div class="pay-card" data-method="airtel">
                                <div class="d-flex align-items-center">
                                    <div class="pay-radio me-3"></div>
                                    <div>
                                        <div class="fw-bold">Airtel Money</div>
                                        <div class="small text-muted">Pay with Airtel Money</div>
                                    </div>
                                </div>
                                <div class="pay-fields" id="fields-airtel">
                                    <input type="tel" class="form-control mt-2" placeholder="073XXXXXXX" id="pay-airtel">
                                </div>
                            </div>
                        </div>
                        <!-- Card -->
                        <div class="col-md-6">
                            <div class="pay-card" data-method="card">
                                <div class="d-flex align-items-center">
                                    <div class="pay-radio me-3"></div>
                                    <div>
                                        <div class="fw-bold">Credit / Debit Card</div>
                                        <div class="small text-muted">Visa, Mastercard</div>
                                    </div>
                                </div>
                                <div class="pay-fields" id="fields-card">
                                    <input type="text" class="form-control mt-2" placeholder="Cardholder Name" id="pay-card-name">
                                    <input type="text" class="form-control mt-2" placeholder="MM/YY" id="pay-card-exp">
                                </div>
                            </div>
                        </div>
                        <!-- Bank Transfer -->
                        <div class="col-md-6">
                            <div class="pay-card" data-method="bank">
                                <div class="d-flex align-items-center">
                                    <div class="pay-radio me-3"></div>
                                    <div>
                                        <div class="fw-bold">Bank Transfer</div>
                                        <div class="small text-muted">BK Account</div>
                                    </div>
                                </div>
                                <div class="pay-fields" id="fields-bank">
                                    <div class="small text-muted mt-2 mb-1">BK Acc: 00040-0123456-78</div>
                                    <input type="text" class="form-control" placeholder="Transfer Reference Code" id="pay-bank">
                                </div>
                            </div>
                        </div>
                        <!-- Cash on Delivery -->
                        <div class="col-12">
                            <div class="pay-card" data-method="cod">
                                <div class="d-flex align-items-center">
                                    <div class="pay-radio me-3"></div>
                                    <div>
                                        <div class="fw-bold">Cash on Delivery</div>
                                        <div class="small text-muted">Pay in cash upon delivery</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Section: Order Items -->
            <div class="card border-0 shadow-sm mb-4" style="border-radius:16px">
                <div class="card-body p-4">
                    <h5 class="fw-bold mb-4"><i class="bi bi-bag text-primary me-2"></i>Order Items</h5>
                    <?php $idx = 0; foreach ($rows as $r): $idx++; ?>
                    <div class="d-flex justify-content-between align-items-center py-2 <?= $idx < count($rows) ? 'border-bottom' : '' ?>">
                        <div>
                            <span class="fw-600"><?= htmlspecialchars($r['name']) ?></span>
                            <span class="text-muted ms-2">× <?= $r['quantity'] ?></span>
                        </div>
                        <span class="fw-bold">RWF <?= number_format($r['price'] * $r['quantity']) ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Place Order Button -->
            <button type="button" class="btn btn-accent btn-lg w-100 py-3 fw-800 rounded-pill mb-4" id="btn-place-order">
                <i class="bi bi-lock-fill me-2"></i>Place Order & Pay — RWF <?= number_format($grand) ?>
            </button>
        </div>

        <!-- RIGHT COLUMN: Summary -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm sticky-top" style="top:20px; border-radius:16px">
                <div class="card-body p-4">
                    <h6 class="fw-bold mb-3">Order Summary</h6>
                    <div class="d-flex justify-content-between mb-2 small">
                        <span class="text-muted">Subtotal (<?= array_sum(array_column($rows, 'quantity')) ?> items)</span>
                        <span>RWF <?= number_format($subtotal) ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-3 small">
                        <span class="text-muted">Shipping</span>
                        <span class="text-success fw-bold">FREE</span>
                    </div>
                    <hr>
                    <div class="d-flex justify-content-between mb-3">
                        <h5 class="fw-800 mb-0">Total</h5>
                        <h5 class="fw-800 mb-0" style="color:#f5a623">RWF <?= number_format($grand) ?></h5>
                    </div>
                    <div class="d-flex justify-content-center gap-2 mb-3">
                        <img src="https://upload.wikimedia.org/wikipedia/commons/thumb/9/93/MTN_Logo.svg/120px-MTN_Logo.svg.png" style="height:16px">
                        <img src="https://upload.wikimedia.org/wikipedia/commons/thumb/5/5e/Visa_Inc._logo.svg/100px-Visa_Inc._logo.svg.png" style="height:14px">
                        <img src="https://upload.wikimedia.org/wikipedia/commons/thumb/2/2a/Mastercard-logo.svg/80px-Mastercard-logo.svg.png" style="height:14px">
                    </div>
                    <div class="text-center small text-muted"><i class="bi bi-shield-check me-1"></i>256-bit SSL encrypted checkout</div>
                </div>
            </div>
            <!-- Summary summary of summary: address preview -->
            <div class="card border-0 shadow-sm mt-3" style="border-radius:16px">
                <div class="card-body p-3" id="address-preview-box">
                    <div class="text-muted small mb-1"><i class="bi bi-geo-alt me-1"></i>Shipping to</div>
                    <div class="fw-600 small" id="preview-name">-</div>
                    <div class="small text-muted" id="preview-address">Not set yet</div>
                    <div class="small text-muted" id="preview-phone"></div>
                </div>
            </div>
        </div>
    </div>
    </form>
</div>

<!-- PIN Confirmation Modal -->
<div class="modal fade" id="pinModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content border-0 shadow-lg" style="border-radius:20px; overflow:hidden">
            <div class="modal-body p-4 text-center">
                <div id="pin-initial">
                    <div class="mb-3">
                        <i class="bi bi-shield-lock text-primary" style="font-size:2.5rem"></i>
                    </div>
                    <h5 class="fw-bold mb-1" id="pin-method-label">Confirm Payment</h5>
                    <p class="text-muted small mb-3">Enter your <span id="pin-type-text">PIN</span> to authorize<br><strong class="text-dark">RWF <?= number_format($grand) ?></strong></p>
                    <input type="password" id="pin-input" class="form-control form-control-lg text-center fw-bold mx-auto mb-3" placeholder="••••••" maxlength="6" style="max-width:200px; letter-spacing:8px; font-size:1.4rem; border-radius:12px; background:#f8f9fa" autocomplete="off">
                    <div id="pin-error" class="text-danger small mb-3 d-none"><i class="bi bi-exclamation-circle me-1"></i>Please enter your PIN</div>
                    <button type="button" class="btn btn-dark btn-lg w-100 fw-700 rounded-pill" id="btn-confirm-pin">Confirm & Pay</button>
                    <button type="button" class="btn btn-link btn-sm text-muted mt-2 text-decoration-none" data-bs-dismiss="modal">Cancel</button>
                </div>
                <div id="pin-processing" class="d-none py-4">
                    <div class="spinner-border text-primary mb-3" role="status" style="width:2.5rem; height:2.5rem"></div>
                    <h6 class="fw-700">Processing...</h6>
                    <p class="text-muted small mb-0">Please wait while we process your payment.</p>
                </div>
                <div id="pin-success" class="d-none py-4">
                    <i class="bi bi-check-circle-fill text-success mb-3" style="font-size:3rem"></i>
                    <h5 class="fw-bold">Payment Confirmed!</h5>
                    <p class="text-muted small mb-0">Placing your order now...</p>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.pay-card {
    border: 1.5px solid #e0e0e0;
    border-radius: 12px;
    padding: 14px;
    cursor: pointer;
    transition: all 0.2s;
    background: #fff;
    height: 100%;
}
.pay-card:hover { border-color: #0f3460; background: #fafcff; }
.pay-card.selected { border-color: #e94560; background: #fff5f6; }
.pay-radio { width: 18px; height: 18px; border: 2px solid #ddd; border-radius: 50%; flex-shrink: 0; }
.pay-card.selected .pay-radio { border-color: #e94560; border-width: 5px; }
.pay-fields { display: none; margin-top: 4px; }
.pay-card.selected .pay-fields { display: block; }
.pay-fields input { font-size: 0.85rem; }
.pay-fields .form-control:focus { border-color: #e94560 !important; box-shadow: 0 0 0 3px rgba(233,69,96,0.1) !important; }
.btn-accent { background: linear-gradient(135deg, #e94560, #f5a623); color: #fff; border: none; transition: all 0.3s; }
.btn-accent:hover { transform: translateY(-1px); box-shadow: 0 4px 16px rgba(233,69,96,0.35); color: #fff; }
.form-control, .form-select { border-radius: 10px !important; border: 1.5px solid #e0e0e0 !important; }
.form-control:focus, .form-select:focus { border-color: #0f3460 !important; box-shadow: 0 0 0 3px rgba(15,52,96,0.08) !important; }
.input-group .form-control:focus { box-shadow: none !important; border-color: #0f3460 !important; }
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const checkoutForm = document.getElementById('checkout-form');
    const pinModal = new bootstrap.Modal(document.getElementById('pinModal'));

    // ── Address auto-save ──
    function saveAddress() {
        const name = document.getElementById('inp-name').value.trim();
        const phone = document.getElementById('inp-phone').value.trim();
        const street = document.getElementById('inp-street').value.trim();
        const city = document.getElementById('inp-city').value.trim();
        const province = document.getElementById('inp-province').value;
        const zip = document.getElementById('inp-zip').value.trim();

        if (!name || !phone || !street || !city) return;

        const full = [street, city, province].filter(Boolean).join(', ');
        document.getElementById('main-address').value = full;
        document.getElementById('main-phone').value = '+250 ' + phone;
        document.getElementById('main-province').value = province;

        document.getElementById('preview-name').innerText = name;
        document.getElementById('preview-address').innerText = full;
        document.getElementById('preview-phone').innerText = '+250 ' + phone;
    }

    ['inp-name','inp-phone','inp-street','inp-city','inp-province'].forEach(id => {
        document.getElementById(id).addEventListener('input', saveAddress);
        document.getElementById(id).addEventListener('change', saveAddress);
    });
    saveAddress();

    // ── Payment method selection ──
    const payCards = document.querySelectorAll('.pay-card');
    function selectPayMethod(el) {
        payCards.forEach(c => c.classList.remove('selected'));
        el.classList.add('selected');
        const method = el.dataset.method;
        document.querySelector('input[name="payment"]').value = method;
    }
    payCards.forEach(c => c.addEventListener('click', function () {
        selectPayMethod(this);
        // Auto-focus the first field
        const first = this.querySelector('.pay-fields input');
        if (first) setTimeout(() => first.focus(), 200);
    }));
    // Select default
    selectPayMethod(document.querySelector('.pay-card.selected') || payCards[0]);

    // ── Payment field sync to hidden inputs (for PHP POST) ──
    function bindPayField(inputId, hiddenId) {
        const inp = document.getElementById(inputId);
        const hid = document.getElementById(hiddenId);
        if (!inp || !hid) return;
        inp.addEventListener('input', function () { hid.value = this.value; });
    }
    bindPayField('pay-momo', 'h-momo');
    bindPayField('pay-airtel', 'h-airtel');
    bindPayField('pay-card-name', 'h-card-name');
    bindPayField('pay-card-exp', 'h-card-exp');
    bindPayField('pay-bank', 'h-bank');

    // ── Validation helper ──
    function validate() {
        const addr = document.getElementById('main-address').value;
        const phone = document.getElementById('main-phone').value;
        if (!addr || !phone) return 'Please fill in your shipping address.';
        const method = document.querySelector('input[name="payment"]').value;
        if (method === 'momo' && !document.getElementById('h-momo').value) return 'Enter your MTN MoMo number.';
        if (method === 'airtel' && !document.getElementById('h-airtel').value) return 'Enter your Airtel Money number.';
        if (method === 'card' && (!document.getElementById('h-card-name').value || !document.getElementById('h-card-exp').value)) return 'Fill in cardholder name and expiry.';
        if (method === 'bank' && !document.getElementById('h-bank').value) return 'Enter your bank transfer reference.';
        return null;
    }

    // ── Place order / PIN modal flow ──
    function showPinModal() {
        const err = validate();
        if (err) { alert(err); return; }

        const method = document.querySelector('input[name="payment"]').value;
        const methodLabel = document.querySelector('.pay-card.selected .fw-bold').innerText;
        document.getElementById('pin-method-label').innerText = methodLabel;
        const typeMap = { momo: 'Mobile Money PIN', airtel: 'Airtel Money PIN', card: '3D Secure Password', bank: 'Banking PIN', cod: 'confirmation' };
        document.getElementById('pin-type-text').innerText = typeMap[method] || 'PIN';

        document.getElementById('pin-input').value = '';
        document.getElementById('pin-error').classList.add('d-none');
        document.getElementById('pin-initial').classList.remove('d-none');
        document.getElementById('pin-processing').classList.add('d-none');
        document.getElementById('pin-success').classList.add('d-none');
        pinModal.show();
        setTimeout(() => document.getElementById('pin-input').focus(), 300);
    }

    document.getElementById('btn-place-order').addEventListener('click', showPinModal);

    document.getElementById('btn-confirm-pin').addEventListener('click', function () {
        const method = document.querySelector('input[name="payment"]').value;
        if (method !== 'cod') {
            const pin = document.getElementById('pin-input').value;
            if (!pin) {
                document.getElementById('pin-error').classList.remove('d-none');
                return;
            }
        }
        document.getElementById('pin-error').classList.add('d-none');
        document.getElementById('pin-initial').classList.add('d-none');
        document.getElementById('pin-processing').classList.remove('d-none');

        setTimeout(() => {
            document.getElementById('pin-processing').classList.add('d-none');
            document.getElementById('pin-success').classList.remove('d-none');

            setTimeout(() => {
                pinModal.hide();
                // Submit the form
                checkoutForm.submit();
            }, 1000);
        }, 1500);
    });

    // Also allow Enter key on PIN input
    document.getElementById('pin-input').addEventListener('keydown', function (e) {
        if (e.key === 'Enter') document.getElementById('btn-confirm-pin').click();
    });

    // ── Keyboard shortcut: Enter on any field triggers place order ──
    checkoutForm.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            if (document.querySelector('.modal.show')) return;
            showPinModal();
        }
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>
