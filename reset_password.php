<?php
require_once 'config/db.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$token = trim($_GET['token'] ?? '');
$msg   = '';
$valid = false;

if ($token) {
    $safeToken = $token;
    $stmt = $conn->prepare("SELECT * FROM password_resets WHERE token=? AND used=0 AND expires_at > NOW() LIMIT 1");
    $stmt->bind_param("s", $safeToken);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if ($row) $valid = true;
    else $msg = 'error:This reset link is invalid or has expired. Please request a new one.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $valid) {
    $pass  = $_POST['password'] ?? '';
    $cpass = $_POST['confirm_password'] ?? '';
    if (strlen($pass) < 6) {
        $msg = 'error:Password must be at least 6 characters.';
    } elseif ($pass !== $cpass) {
        $msg = 'error:Passwords do not match.';
    } else {
        $hash      = password_hash($pass, PASSWORD_DEFAULT);
        $safeEmail = $row['email'];
        $stmt = $conn->prepare("UPDATE users SET password=? WHERE email=?");
        $stmt->bind_param("ss", $hash, $safeEmail);
        $stmt->execute();
        $stmt = $conn->prepare("UPDATE password_resets SET used=1 WHERE token=?");
        $stmt->bind_param("s", $safeToken);
        $stmt->execute();
        $msg = 'success:Password reset successfully! You can now login.';
        $valid = false;
    }
}
?>
<!DOCTYPE html><html lang="en"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Reset Password — <?= SITE_NAME ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
<link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css">
</head><body>
<div class="auth-page">
    <div class="auth-card">
        <div class="auth-logo">
            <div class="icon-wrap">🔒</div>
            <h4 class="mb-1">Set New Password</h4>
        </div>
        <?php if ($msg): [$t,$m] = explode(':',$msg,2); ?>
        <div class="alert alert-<?= $t==='error'?'danger':'success' ?> py-2 small"><?= htmlspecialchars($m) ?></div>
        <?php endif; ?>
        <?php if ($valid): ?>
        <form method="POST">
            <div class="mb-3">
                <label class="form-label small fw-600">New Password</label>
                <input type="password" name="password" class="form-control" placeholder="Min 6 characters" required minlength="6">
            </div>
            <div class="mb-4">
                <label class="form-label small fw-600">Confirm Password</label>
                <input type="password" name="confirm_password" class="form-control" placeholder="Repeat password" required>
            </div>
            <button type="submit" class="btn-auth btn mb-3"><i class="bi bi-lock me-2"></i>Reset Password</button>
        </form>
        <?php endif; ?>
        <div class="text-center mt-2">
            <a href="login.php" class="small fw-600"><i class="bi bi-arrow-left me-1"></i>Back to Login</a>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js" integrity="sha384-geWF76RCwLtnZ8qwWowPQNguL3RmwHVBC9FhGdlKrxdiJJigb/j/68SIy3Te4Bkz" crossorigin="anonymous"></script>
</body></html>
