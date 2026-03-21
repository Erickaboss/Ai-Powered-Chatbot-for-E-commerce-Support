<?php
require_once 'includes/header.php';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $pass  = $_POST['password'] ?? '';

    $stmt = $conn->prepare("SELECT id, name, password, role FROM users WHERE email=?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    if ($user && password_verify($pass, $user['password'])) {
        $_SESSION['user_id']   = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_role'] = $user['role'];

        if ($user['role'] === 'admin') {
            header('Location: admin/index.php'); exit;
        }
        header('Location: index.php'); exit;
    } else {
        $error = 'Invalid email or password.';
    }
}
?>
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-5">
            <div class="card shadow p-4">
                <h4 class="text-center mb-4"><i class="bi bi-box-arrow-in-right"></i> Login</h4>
                <?php if ($error): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>
                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label">Email Address</label>
                        <input type="email" name="email" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>
                    <button class="btn btn-dark w-100">Login</button>
                </form>
                <p class="text-center mt-3 mb-0">No account? <a href="register.php">Register here</a></p>
                <hr>
                <p class="text-center text-muted small">Admin: admin@shop.com / password</p>
            </div>
        </div>
    </div>
</div>
<?php require_once 'includes/footer.php'; ?>
