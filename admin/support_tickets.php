<?php require_once 'includes/admin_header.php'; ?>
<?php
$msg = '';

// ── Handle admin reply ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ticket_id'])) {
    $tid   = (int)$_POST['ticket_id'];
    $reply = $conn->real_escape_string(trim($_POST['admin_reply']));
    if ($reply) {
        $conn->query("UPDATE support_tickets SET admin_reply='$reply', replied_at=NOW(), status='replied' WHERE id=$tid");
        // Send reply email to customer
        $ticket = $conn->query("SELECT * FROM support_tickets WHERE id=$tid")->fetch_assoc();
        if ($ticket && $ticket['customer_email']) {
            require_once '../includes/mailer.php';
            sendMail(
                $ticket['customer_email'],
                $ticket['customer_name'] ?: 'Customer',
                'Re: Your Support Request — ' . SITE_NAME,
                emailAdminReply($ticket['customer_name'] ?: 'Customer', $ticket['message'], $reply)
            );
        }
        $msg = '<div class="alert alert-success"><i class="bi bi-check-circle me-2"></i>Reply sent successfully!</div>';
    }
}

// ── Close ticket ──
if (isset($_GET['close'])) {
    $conn->query("UPDATE support_tickets SET status='closed' WHERE id=" . (int)$_GET['close']);
    header('Location: support_tickets.php'); exit;
}

$filter = $_GET['filter'] ?? 'open';
$where  = $filter === 'all' ? '' : "WHERE status='" . $conn->real_escape_string($filter) . "'";
$tickets = $conn->query("SELECT * FROM support_tickets $where ORDER BY created_at DESC");

$counts = [];
foreach (['open','replied','closed','all'] as $s) {
    $w = $s === 'all' ? '' : "WHERE status='$s'";
    $counts[$s] = $conn->query("SELECT COUNT(*) as c FROM support_tickets $w")->fetch_assoc()['c'];
}
?>

<div class="admin-content">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <h4><i class="bi bi-headset me-2"></i>Support Tickets</h4>
        <div class="d-flex gap-2 flex-wrap">
            <?php foreach (['open'=>'danger','replied'=>'success','closed'=>'secondary','all'=>'dark'] as $s=>$c): ?>
            <a href="?filter=<?= $s ?>" class="btn btn-sm btn-<?= $filter===$s ? $c : 'outline-'.$c ?>">
                <?= ucfirst($s) ?> <span class="badge bg-white text-dark ms-1"><?= $counts[$s] ?></span>
            </a>
            <?php endforeach; ?>
        </div>
    </div>

    <?= $msg ?>

    <?php if ($tickets->num_rows === 0): ?>
    <div class="alert alert-info"><i class="bi bi-info-circle me-2"></i>No <?= $filter ?> tickets.</div>
    <?php else: ?>
    <div class="row g-3">
    <?php while ($t = $tickets->fetch_assoc()): ?>
    <div class="col-12">
        <div class="card shadow-sm">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
                    <div>
                        <h6 class="mb-1">
                            <i class="bi bi-person-circle me-2 text-primary"></i>
                            <strong><?= htmlspecialchars($t['customer_name'] ?: 'Guest') ?></strong>
                            <?php if ($t['customer_email']): ?>
                            <a href="mailto:<?= htmlspecialchars($t['customer_email']) ?>" class="text-muted ms-2" style="font-size:.85rem">
                                <i class="bi bi-envelope me-1"></i><?= htmlspecialchars($t['customer_email']) ?>
                            </a>
                            <?php endif; ?>
                        </h6>
                        <small class="text-muted"><i class="bi bi-clock me-1"></i><?= date('d M Y, H:i', strtotime($t['created_at'])) ?></small>
                    </div>
                    <div class="d-flex gap-2 align-items-center">
                        <?php
                        $badges = ['open'=>'danger','replied'=>'success','closed'=>'secondary'];
                        $icons  = ['open'=>'bi-envelope-open','replied'=>'bi-check-circle','closed'=>'bi-x-circle'];
                        ?>
                        <span class="badge bg-<?= $badges[$t['status']] ?>">
                            <i class="bi <?= $icons[$t['status']] ?> me-1"></i><?= ucfirst($t['status']) ?>
                        </span>
                        <?php if ($t['status'] !== 'closed'): ?>
                        <a href="?close=<?= $t['id'] ?>" class="btn btn-sm btn-outline-secondary"
                           onclick="return confirm('Close this ticket?')">
                            <i class="bi bi-x-circle me-1"></i>Close
                        </a>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Customer message -->
                <div class="p-3 mb-3" style="background:#f8f9fa;border-left:4px solid #e94560;border-radius:6px">
                    <small class="text-muted d-block mb-1"><i class="bi bi-chat-left-text me-1"></i>Customer message:</small>
                    <p class="mb-0"><?= nl2br(htmlspecialchars($t['message'])) ?></p>
                </div>

                <!-- Admin reply (if exists) -->
                <?php if ($t['admin_reply']): ?>
                <div class="p-3 mb-3" style="background:#e8f5e9;border-left:4px solid #28a745;border-radius:6px">
                    <small class="text-muted d-block mb-1"><i class="bi bi-reply me-1"></i>Your reply — <?= date('d M Y, H:i', strtotime($t['replied_at'])) ?>:</small>
                    <p class="mb-0"><?= nl2br(htmlspecialchars($t['admin_reply'])) ?></p>
                </div>
                <?php endif; ?>

                <!-- Reply form -->
                <?php if ($t['status'] !== 'closed'): ?>
                <form method="POST">
                    <input type="hidden" name="ticket_id" value="<?= $t['id'] ?>">
                    <div class="mb-2">
                        <textarea name="admin_reply" class="form-control" rows="3"
                                  placeholder="Type your reply to <?= htmlspecialchars($t['customer_name'] ?: 'customer') ?>..."
                                  required><?= htmlspecialchars($t['admin_reply'] ?? '') ?></textarea>
                    </div>
                    <button class="btn btn-success btn-sm">
                        <i class="bi bi-send me-1"></i>Send Reply via Email
                    </button>
                </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endwhile; ?>
    </div>
    <?php endif; ?>
</div>

<?php require_once 'includes/admin_footer.php'; ?>
