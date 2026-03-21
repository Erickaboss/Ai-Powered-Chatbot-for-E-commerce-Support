<?php
require_once 'includes/header.php';
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }
$uid = $_SESSION['user_id'];
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name    = $conn->real_escape_string(trim($_POST['name']));
    $phone   = $conn->real_escape_string(trim($_POST['phone']));
    $address = $conn->real_escape_string(trim($_POST['address']));
    if (!empty($_POST['new_password'])) {
        $row = $conn->query("SELECT password FROM users WHERE id=$uid")->fetch_assoc();
        if (!password_verify($_POST['current_password'], $row['password'])) {
            $msg = 'error:Current password is incorrect.';
        } elseif ($_POST['new_password'] !== $_POST['confirm_password']) {
            $msg = 'error:New passwords do not match.';
        } else {
            $hash = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
            $conn->query("UPDATE users SET name='$name',phone='$phone',address='$address',password='$hash' WHERE id=$uid");
            $_SESSION['user_name'] = $name;
            $msg = 'success:Profile and password updated successfully.';
        }
    } else {
        $conn->query("UPDATE users SET name='$name',phone='$phone',address='$address' WHERE id=$uid");
        $_SESSION['user_name'] = $name;
        $msg = 'success:Profile updated successfully.';
    }
}

$user  = $conn->query("SELECT * FROM users WHERE id=$uid")->fetch_assoc();
$stats = $conn->query("SELECT COUNT(*) as total, SUM(CASE WHEN status='delivered' THEN 1 ELSE 0 END) as delivered, COALESCE(SUM(total_price),0) as spent FROM orders WHERE user_id=$uid")->fetch_assoc();
?>

<div class="page-hero">
    <div class="container">
        <h2><i class="bi bi-person-circle me-2"></i>My Profile</h2>
        <p>Manage your account information</p>
    </div>
</div>

<div class="container pb-5">
    <?php if ($msg): [$type,$text] = explode(':',$msg,2); ?>
    <div class="alert alert-<?= $type==='error'?'danger':'success' ?> alert-dismissible fade show mt-3" style="border-radius:12px;border:none">
        <i class="bi bi-<?= $type==='error'?'exclamation-circle':'check-circle' ?> me-2"></i><?= htmlspecialchars($text) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <div class="row g-4 mt-1">

        <!-- Sidebar -->
        <div class="col-lg-3">
            <div class="card-clean p-4 text-center mb-3">
                <div class="mx-auto mb-3 d-flex align-items-center justify-content-center fw-800"
                     style="width:80px;height:80px;border-radius:50%;background:linear-gradient(135deg,var(--primary),var(--accent));color:#fff;font-size:2rem">
                    <?= strtoupper(substr($user['name'],0,1)) ?>
                </div>
                <h6 class="fw-700 mb-0"><?= htmlspecialchars($user['name']) ?></h6>
                <div class="text-muted small mb-3"><?= htmlspecialchars($user['email']) ?></div>
                <div class="row g-2 text-center mb-3">
                    <div class="col-4">
                        <div class="p-2" style="background:#f4f6fb;border-radius:10px">
                            <div class="fw-800" style="color:var(--primary)"><?= $stats['total'] ?></div>
                            <div style="font-size:.68rem;color:#888">Orders</div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="p-2" style="background:#e8f5e9;border-radius:10px">
                            <div class="fw-800" style="color:#28a745"><?= $stats['delivered'] ?></div>
                            <div style="font-size:.68rem;color:#888">Delivered</div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="p-2" style="background:#fff3e0;border-radius:10px">
                            <div class="fw-800" style="color:#f57c00;font-size:.75rem"><?= number_format($stats['spent']/1000) ?>k</div>
                            <div style="font-size:.68rem;color:#888">Spent</div>
                        </div>
                    </div>
                </div>
                <div class="text-start small" style="color:#666;line-height:2">
                    <div><i class="bi bi-telephone me-2 text-primary"></i><?= htmlspecialchars($user['phone'] ?: 'Not set') ?></div>
                    <div><i class="bi bi-geo-alt me-2 text-primary"></i><?= htmlspecialchars($user['address'] ?: 'Not set') ?></div>
                    <div><i class="bi bi-calendar me-2 text-primary"></i>Joined <?= date('M Y', strtotime($user['created_at'])) ?></div>
                </div>
            </div>
            <a href="orders.php" class="btn w-100 mb-2" style="background:var(--primary);color:#fff;border-radius:10px;font-size:.88rem">
                <i class="bi bi-bag me-2"></i>My Orders
            </a>
            <a href="products.php" class="btn btn-outline-secondary w-100" style="border-radius:10px;font-size:.88rem">
                <i class="bi bi-grid me-2"></i>Browse Products
            </a>
        </div>

        <!-- Edit Form -->
        <div class="col-lg-9">
            <form method="POST">
                <!-- Personal Info -->
                <div class="card-clean p-4 mb-4">
                    <h6 class="fw-700 mb-4" style="color:var(--dark)"><i class="bi bi-person me-2 text-primary"></i>Personal Information</h6>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-600">Full Name</label>
                            <input type="text" name="name" class="form-control" style="border-radius:10px"
                                   value="<?= htmlspecialchars($user['name']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-600">Email Address</label>
                            <input type="email" class="form-control" style="border-radius:10px;background:#f4f6fb"
                                   value="<?= htmlspecialchars($user['email']) ?>" disabled>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-600">Phone Number</label>
                            <input type="tel" name="phone" class="form-control" style="border-radius:10px"
                                   value="<?= htmlspecialchars($user['phone'] ?? '') ?>" placeholder="+250 7XX XXX XXX">
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-600">Default Delivery Address</label>
                            <textarea name="address" class="form-control" rows="2" style="border-radius:10px"
                                      placeholder="Street, District, City"><?= htmlspecialchars($user['address'] ?? '') ?></textarea>
                        </div>
                    </div>
                </div>

                <!-- Change Password -->
                <div class="card-clean p-4 mb-4">
                    <h6 class="fw-700 mb-1" style="color:var(--dark)"><i class="bi bi-lock me-2 text-warning"></i>Change Password</h6>
                    <p class="text-muted small mb-4">Leave blank to keep your current password</p>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label small fw-600">Current Password</label>
                            <input type="password" name="current_password" class="form-control" style="border-radius:10px" placeholder="••••••••">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-600">New Password</label>
                            <input type="password" name="new_password" class="form-control" style="border-radius:10px" placeholder="Min 6 chars" minlength="6">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-600">Confirm New Password</label>
                            <input type="password" name="confirm_password" class="form-control" style="border-radius:10px" placeholder="Repeat">
                        </div>
                    </div>
                </div>

                <div class="d-flex gap-2">
                    <button type="submit" class="btn-primary-custom btn px-4">
                        <i class="bi bi-save me-2"></i>Save Changes
                    </button>
                    <a href="orders.php" class="btn btn-outline-secondary px-4" style="border-radius:10px">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
