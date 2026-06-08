<?php
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Strict', 'secure' => isset($_SERVER['HTTPS'])]);
    session_start();
}
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/security.php';
sendSecurityHeaders();
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ' . SITE_URL . '/login.php'); exit;
}

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="orders_' . date('Y-m-d') . '.csv"');

$out = fopen('php://output', 'w');
fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));

fputcsv($out, ['Order #','Customer','Email','Phone','Total (RWF)','Payment','Status','Address','Date']);

$orders = $conn->query("
    SELECT o.id, u.name, u.email, u.phone, o.total_price, o.payment_method, o.status, o.address, o.created_at
    FROM orders o JOIN users u ON o.user_id=u.id
    ORDER BY o.created_at DESC
");

if ($orders) while ($o = $orders->fetch_assoc()) {
    fputcsv($out, [
        str_pad($o['id'],6,'0',STR_PAD_LEFT),
        $o['name'],
        $o['email'],
        $o['phone'] ?? '',
        number_format($o['total_price'], 2),
        strtoupper($o['payment_method']),
        ucfirst($o['status']),
        $o['address'],
        date('d M Y H:i', strtotime($o['created_at']))
    ]);
}

fclose($out);
exit;
