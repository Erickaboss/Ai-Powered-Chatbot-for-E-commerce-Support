<?php
require_once '../config/db.php';
require_once '../includes/mailer.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    die('Admin only');
}

$msg = '';
if (isset($_GET['user_id'])) {
    $uid = (int)$_GET['user_id'];
    $u = $conn->query("SELECT name, email FROM users WHERE id=$uid LIMIT 1")->fetch_assoc();
    if ($u) {
        $sent = sendMail($u['email'], $u['name'], '[' . SITE_NAME . '] Welcome to your new account!', emailWelcome($u['name']));
        $msg = $sent
            ? '<div class="alert alert-success">✅ Welcome email sent to ' . htmlspecialchars($u['email']) . '</div>'
            : '<div class="alert alert-danger">❌ Email failed. Check error log: C:\xampp\apache\logs\error.log</div>';
    }
}

$users = $conn->query("SELECT id, name, email, created_at FROM users WHERE role='customer' ORDER BY created_at DESC LIMIT 50");
?>
<!DOCTYPE html>
<html><head><meta charset="UTF-8"><title>Resend Welcome Email</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head><body class="p-4">
<div class="container">
    <h4 class="mb-3">Resend Welcome Email</h4>
    <?= $msg ?>
    <div class="card">
        <table class="table table-hover mb-0">
            <thead class="table-dark"><tr><th>Name</th><th>Email</th><th>Registered</th><th>Action</th></tr></thead>
            <tbody>
            <?php while ($u = $users->fetch_assoc()): ?>
            <tr>
                <td><?= htmlspecialchars($u['name']) ?></td>
                <td><?= htmlspecialchars($u['email']) ?></td>
                <td><?= date('d M Y, H:i', strtotime($u['created_at'])) ?></td>
                <td><a href="?user_id=<?= $u['id'] ?>" class="btn btn-sm btn-primary">📧 Resend Welcome</a></td>
            </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
    </div>
    <a href="users.php" class="btn btn-secondary mt-3">← Back to Customers</a>
</div>
</body></html>
