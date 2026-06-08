<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db.php';

$q = trim($_GET['q'] ?? '');
if (strlen($q) < 2) { echo '[]'; exit; }

$safe = "%" . $conn->real_escape_string($q) . "%";
$stmt = $conn->prepare("SELECT id, name, price, image FROM products WHERE (name LIKE ? OR description LIKE ?) AND stock > 0 LIMIT 6");
$stmt->bind_param("ss", $safe, $safe);
$stmt->execute();
$res = $stmt->get_result();
echo json_encode($res->fetch_all(MYSQLI_ASSOC));
