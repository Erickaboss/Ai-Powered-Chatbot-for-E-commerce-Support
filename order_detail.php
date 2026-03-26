<?php
require_once 'includes/header.php';
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }
$uid  = $_SESSION['user_id'];
$oid  = (int)($_GET['id'] ?? 0);
$stmt = $conn->prepare("SELECT * FROM orders WHERE id=? AND user_id=?");
$stmt->bind_param("ii", $oid, $uid); $stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
if (!$order) { header('Location: orders.php'); exit; }
$items = $conn->query("SELECT oi.*, p.name, p.image FROM order_items oi JOIN products p ON oi.product_id=p.id WHERE oi.order_id=$oid");

$steps    = ['pending','processing','shipped','delivered'];
$cur_step = array_search($order['status'], $steps);
$step_icons = ['bi-clock','bi-gear','bi-truck','bi-check-circle'];
$step_labels = ['Order Placed','Processing','Shipped','Delivered'];
?>

<div class="page-hero">
    <div class="container">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-2" style="font-size:.8rem">
                <li class="breadcrumb-item"><a href="index.php" style="color:rgba(255,255,255,.6)">Home</a></li>
                <li class="breadcrumb-item"><a href="orders.php" style="color:rgba(255,255,255,.6)">My Orders</a></li>
                <li class="breadcrumb-item active" style="color:rgba(255,255,255,.8)">Order #<?= str_pad($oid,6,'0',STR_PAD_LEFT) ?></li>
            </ol>
        </nav>
        <div class="d-flex align-items-center gap-3 flex-wrap">
            <h2 class="mb-0">Order #<?= str_pad($oid,6,'0',STR_PAD_LEFT) ?></h2>
            <span class="status-<?= $order['status'] ?>"><?= ucfirst($order['status']) ?></span>
        </div>
        <p style="color:rgba(255,255,255,.65);margin-top:6px">Placed on <?= date('d M Y, H:i', strtotime($order['created_at'])) ?></p>
    </div>
</div>

<div class="container pb-5">

    <!-- Order Progress (only if not cancelled) -->
    <?php if ($order['status'] !== 'cancelled'): ?>
    <div class="card-clean p-4 mb-4">
        <h6 class="fw-700 mb-4" style="color:var(--dark)"><i class="bi bi-map me-2 text-primary"></i>Order Progress</h6>
        <div class="d-flex justify-content-between position-relative" style="padding:0 20px">
            <div style="position:absolute;top:20px;left:40px;right:40px;height:3px;background:#e0e0e0;z-index:0">
                <div style="height:100%;background:linear-gradient(90deg,var(--primary),#1e5fa8);width:<?= $cur_step===false ? 0 : min(100, ($cur_step/3)*100) ?>%;transition:width .5s"></div>
            </div>
            <?php foreach ($steps as $i => $step): ?>
            <?php $done = $cur_step !== false && $i <= $cur_step; $active = $cur_step !== false && $i === $cur_step; ?>
            <div class="text-center position-relative" style="z-index:1;flex:1">
                <div class="mx-auto mb-2 d-flex align-items-center justify-content-center"
                     style="width:40px;height:40px;border-radius:50%;
                            background:<?= $done ? 'linear-gradient(135deg,var(--primary),#1e5fa8)' : '#e0e0e0' ?>;
                            color:<?= $done ? '#fff' : '#aaa' ?>;
                            font-size:1rem;
                            box-shadow:<?= $active ? '0 0 0 4px rgba(15,52,96,.2)' : 'none' ?>">
                    <i class="bi <?= $step_icons[$i] ?>"></i>
                </div>
                <div style="font-size:.72rem;font-weight:<?= $active ? '700' : '500' ?>;color:<?= $done ? 'var(--primary)' : '#aaa' ?>"><?= $step_labels[$i] ?></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- Items -->
        <div class="col-lg-8">
            <div class="card-clean overflow-hidden">
                <div class="p-4 border-bottom">
                    <h6 class="fw-700 mb-0" style="color:var(--dark)"><i class="bi bi-bag me-2 text-primary"></i>Items Ordered</h6>
                </div>
                <?php $grand=0; while ($item = $items->fetch_assoc()): $sub=$item['price']*$item['quantity']; $grand+=$sub; ?>
                <div class="d-flex align-items-center gap-3 p-4 border-bottom">
                    <img src="<?= strpos($item['image'],'http')===0 ? htmlspecialchars($item['image']) : 'assets/images/products/'.htmlspecialchars($item['image']) ?>"
                         style="width:70px;height:70px;object-fit:cover;border-radius:12px;flex-shrink:0"
                         onerror="this.src='assets/images/placeholder.jpg'">
                    <div class="flex-grow-1">
                        <div class="fw-600"><?= htmlspecialchars($item['name']) ?></div>
                        <div class="text-muted small mt-1">Qty: <?= $item['quantity'] ?> × RWF <?= number_format($item['price']) ?></div>
                    </div>
                    <div class="fw-700 text-end" style="color:var(--accent)">RWF <?= number_format($sub) ?></div>
                </div>
                <?php endwhile; ?>
                <div class="p-4 d-flex justify-content-between align-items-center" style="background:#f8faff">
                    <span class="fw-700">Total Paid</span>
                    <span class="fw-800 fs-5" style="color:var(--accent)">RWF <?= number_format($order['total_price']) ?></span>
                </div>
            </div>
        </div>

        <!-- Order Info -->
        <div class="col-lg-4">
            <div class="card-clean p-4 mb-3">
                <h6 class="fw-700 mb-3" style="color:var(--dark)"><i class="bi bi-info-circle me-2 text-primary"></i>Order Details</h6>
                <div class="d-flex justify-content-between mb-2 small">
                    <span class="text-muted">Order Number</span>
                    <strong>#<?= str_pad($oid,6,'0',STR_PAD_LEFT) ?></strong>
                </div>
                <div class="d-flex justify-content-between mb-2 small">
                    <span class="text-muted">Date</span>
                    <span><?= date('d M Y', strtotime($order['created_at'])) ?></span>
                </div>
                <div class="d-flex justify-content-between mb-2 small">
                    <span class="text-muted">Payment</span>
                    <span class="text-uppercase fw-600"><?= htmlspecialchars($order['payment_method'] ?? 'COD') ?></span>
                </div>
                <div class="d-flex justify-content-between mb-2 small">
                    <span class="text-muted">Status</span>
                    <span class="status-<?= $order['status'] ?>"><?= ucfirst($order['status']) ?></span>
                </div>
                <hr>
                <div class="small">
                    <div class="text-muted mb-1">Delivery Address</div>
                    <div class="fw-500"><?= nl2br(htmlspecialchars($order['address'])) ?></div>
                </div>
            </div>

            <div class="card-clean p-4">
                <h6 class="fw-700 mb-3" style="color:var(--dark)"><i class="bi bi-headset me-2 text-success"></i>Need Help?</h6>
                <p class="text-muted small mb-3">Have an issue with this order? Our AI assistant or support team can help.</p>
                <button class="btn w-100 mb-2 btn-sm" onclick="toggleChat()"
                        style="background:linear-gradient(135deg,var(--primary),var(--accent));color:#fff;border-radius:8px;font-weight:600">
                    <i class="bi bi-robot me-2"></i>Ask AI Assistant
                </button>
                <a href="mailto:<?= ADMIN_EMAIL ?>" class="btn btn-outline-secondary btn-sm w-100" style="border-radius:8px">
                    <i class="bi bi-envelope me-2"></i>Email Support
                </a>
            </div>
        </div>
    </div>

    <div class="mt-4 d-flex gap-2 flex-wrap">
        <a href="orders.php" class="btn btn-outline-secondary" style="border-radius:10px">
            <i class="bi bi-arrow-left me-2"></i>Back to Orders
        </a>
        <a href="invoice.php?id=<?= $oid ?>" class="btn btn-dark" style="border-radius:10px" target="_blank">
            <i class="bi bi-file-earmark-text me-2"></i>Download Invoice
        </a>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
