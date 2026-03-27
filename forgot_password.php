<?php
require_once 'config/db.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (isset($_SESSION['user_id'])) { header('Location: index.php'); exit; }

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $user  = $conn->query("SELECT id, name FROM users WHERE email='" . $conn->real_escape_string($email) . "' LIMIT 1")->fetch_assoc();
    if ($user) {
        $token   = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
        $safeEmail = $conn->real_escape_string($email);
        $conn->query("DELETE FROM password_resets WHERE email='$safeEmail'");
        $conn->query("INSERT INTO password_resets (email, token, expires_at) VALUES ('$safeEmail', '$token', '$expires')");
        $resetLink = SITE_URL . '/reset_password.php?token=' . $token;
        require_once 'includes/mailer.php';
        sendMail($email, $user['name'], '[' . SITE_NAME . '] Password Reset Request',
            emailWrap('🔑 Password Reset', "
                <h2 style='color:#0f3460'>Reset Your Password</h2>
                <p>Hi <strong>" . htmlspecialchars($user['name']) . "</strong>,</p>
                <p>We received a request to reset your password. Click the button below to set a new password:</p>
                <p style='text-align:center;margin:28px 0'>
                    <a href='$resetLink' style='background:#e94560;color:#fff;padding:14px 32px;border-radius:8px;text-decoration:none;font-weight:700;font-size:1rem'>
                        Reset Password →
                    </a>
                </p>
                <p style='color:#888;font-size:13px'>This link expires in <strong>1 hour</strong>. If you didn't request this, ignore this email.</p>
                <p style='color:#888;font-size:12px'>Or copy this link: $resetLink</p>
            ")
        );
    }
    // Always show success (security — don't reveal if email exists)
    $msg = 'success:If that email is registered, a reset link has been sent. Check your inbox.';
}
?>
<!DOCTYPE html><html lang="en"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Forgot Password — <?= SITE_NAME ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
<link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css">
</head><body>
<div class="auth-page">
    <div class="auth-card">
        <div class="auth-logo">
            <div class="icon-wrap">🔑</div>
            <h4 class="mb-1">Forgot Password?</h4>
            <p class="text-muted small">Enter your email and we'll send a reset link</p>
        </div>
        <?php if ($msg): [$t,$m] = explode(':',$msg,2); ?>
        <div class="alert alert-<?= $t==='error'?'danger':'success' ?> py-2 small"><?= htmlspecialchars($m) ?></div>
        <?php endif; ?>
        <?php if (!$msg): ?>
        <form method="POST">
            <div class="mb-4">
                <label class="form-label small fw-600">Email Address</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                    <input type="email" name="email" class="form-control" placeholder="you@example.com" required>
                </div>
            </div>
            <button type="submit" class="btn-auth btn mb-3"><i class="bi bi-send me-2"></i>Send Reset Link</button>
        </form>
        <?php endif; ?>
        <div class="text-center mt-2">
            <a href="login.php" class="small fw-600"><i class="bi bi-arrow-left me-1"></i>Back to Login</a>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body></html>
