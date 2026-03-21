<?php require_once 'includes/admin_header.php'; ?>
<?php
$logs = $conn->query("SELECT cl.*, u.name as user_name FROM chatbot_logs cl LEFT JOIN users u ON cl.user_id=u.id ORDER BY cl.created_at DESC LIMIT 100");
$total = $conn->query("SELECT COUNT(*) as c FROM chatbot_logs")->fetch_assoc()['c'];
?>
<div class="admin-content">
    <h4 class="mb-4">Chatbot Logs <span class="badge bg-secondary"><?= $total ?> total</span></h4>
    <div class="card p-3">
        <table class="table table-hover align-middle">
            <thead class="table-dark">
                <tr><th>#</th><th>User</th><th>Message</th><th>Response</th><th>Time</th></tr>
            </thead>
            <tbody>
            <?php while ($log = $logs->fetch_assoc()): ?>
            <tr>
                <td><?= $log['id'] ?></td>
                <td><?= htmlspecialchars($log['user_name'] ?? 'Guest') ?></td>
                <td style="max-width:200px"><small><?= htmlspecialchars($log['message']) ?></small></td>
                <td style="max-width:300px"><small><?= strip_tags($log['response']) ?></small></td>
                <td><small class="text-muted"><?= date('M d, H:i', strtotime($log['created_at'])) ?></small></td>
            </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require_once 'includes/admin_footer.php'; ?>
