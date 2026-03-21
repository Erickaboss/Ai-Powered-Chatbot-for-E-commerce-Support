<?php require_once 'includes/admin_header.php'; ?>
<?php
$users = $conn->query("SELECT u.*, COUNT(o.id) as order_count FROM users u LEFT JOIN orders o ON u.id=o.user_id WHERE u.role='customer' GROUP BY u.id ORDER BY u.created_at DESC");
?>
<div class="admin-content">
    <h4 class="mb-4">Manage Customers</h4>
    <div class="card p-3">
        <table class="table table-hover align-middle">
            <thead class="table-dark">
                <tr><th>ID</th><th>Name</th><th>Email</th><th>Orders</th><th>Joined</th></tr>
            </thead>
            <tbody>
            <?php while ($u = $users->fetch_assoc()): ?>
            <tr>
                <td><?= $u['id'] ?></td>
                <td><?= htmlspecialchars($u['name']) ?></td>
                <td><?= htmlspecialchars($u['email']) ?></td>
                <td><span class="badge bg-primary"><?= $u['order_count'] ?></span></td>
                <td><?= date('M d, Y', strtotime($u['created_at'])) ?></td>
            </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require_once 'includes/admin_footer.php'; ?>
