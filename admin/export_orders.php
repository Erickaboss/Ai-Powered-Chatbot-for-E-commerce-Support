<?php
require_once 'includes/admin_header.php';

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="orders_' . date('Y-m-d') . '.csv"');

$out = fopen('php://output', 'w');
fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM for Excel

fputcsv($out, ['Order #','Customer','Email','Phone','Total (RWF)','Payment','Status','Address','Date']);

$orders = $conn->query("
    SELECT o.id, u.name, u.email, u.phone, o.total_price, o.payment_method, o.status, o.address, o.created_at
    FROM orders o JOIN users u ON o.user_id=u.id
    ORDER BY o.created_at DESC
");

while ($o = $orders->fetch_assoc()) {
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
