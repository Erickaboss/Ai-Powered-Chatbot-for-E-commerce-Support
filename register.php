<?php
require_once 'config/db.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (isset($_SESSION['user_id'])) { header('Location: index.php'); exit; }

$error = $success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name  = trim($_POST['name']  ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $pass  = $_POST['password'] ?? '';
    $cpass = $_POST['confirm_password'] ?? '';

    if (!$name || !$email || !$pass)    $error = 'Name, email and password are required.';
    elseif ($pass !== $cpass)           $error = 'Passwords do not match.';
    elseif (strlen($pass) < 6)          $error = 'Password must be at least 6 characters.';
    else {
        $chk = $conn->prepare("SELECT id FROM users WHERE email=?");
        $chk->bind_param("s", $email); $chk->execute();
        if ($chk->get_result()->num_rows > 0) {
            $error = 'Email already registered.';
        } else {
            $hash = password_hash($pass, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO users (name,email,password,phone) VALUES (?,?,?,?)");
            $stmt->bind_param("ssss", $name, $email, $hash, $phone);
            $stmt->execute();
            require_once 'includes/mailer.php';
            sendMail($email, $name, 'Welcome to '.SITE_NAME.'!', emailWelcome($name));
            $success = 'Account created! <a href="login.php" class="fw-600">Login here →</a>';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Register — <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css">
</head>
<body>
<div class="auth-page">
    <div class="auth-card" style="max-width:480px">
        <div class="auth-logo">
            <div class="icon-wrap">🛍️</div>
            <h4 class="mb-1">Create Account</h4>
            <p class="text-muted small">Join thousands of happy shoppers</p>
        </div>

        <?php if ($error): ?>
        <div class="alert alert-danger py-2 small"><i class="bi bi-exclamation-circle me-2"></i><?= $error ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
        <div class="alert alert-success py-2 small"><i class="bi bi-check-circle me-2"></i><?= $success ?></div>
        <?php endif; ?>

        <?php if (!$success): ?>
        <form method="POST">
            <div class="mb-3">
                <label class="form-label small fw-600">Full Name <span class="text-danger">*</span></label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-person"></i></span>
                    <input type="text" name="name" class="form-control" placeholder="Your full name" required
                           value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label small fw-600">Email Address <span class="text-danger">*</span></label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                    <input type="email" name="email" class="form-control" placeholder="you@example.com" required
                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label small fw-600">Phone Number</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-telephone"></i></span>
                    <input type="tel" name="phone" class="form-control" placeholder="+250 7XX XXX XXX"
                           value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
                </div>
            </div>
            <div class="row g-2 mb-4">
                <div class="col-6">
                    <label class="form-label small fw-600">Password <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-lock"></i></span>
                        <input type="password" name="password" id="pwd" class="form-control" placeholder="Min 6 chars" required>
                    </div>
                </div>
                <div class="col-6">
                    <label class="form-label small fw-600">Confirm <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-lock-fill"></i></span>
                        <input type="password" name="confirm_password" class="form-control" placeholder="Repeat" required>
                    </div>
                </div>
            </div>
            <button type="submit" class="btn-auth btn mb-3">
                <i class="bi bi-person-plus me-2"></i>Create Account
            </button>
        </form>
        <?php endif; ?>

        <div class="text-center">
            <span class="text-muted small">Already have an account? </span>
            <a href="login.php" class="small fw-600">Sign in →</a>
        </div>
        <div class="text-center mt-3 pt-3 border-top">
            <a href="index.php" class="text-muted small"><i class="bi bi-arrow-left me-1"></i>Back to Store</a>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
