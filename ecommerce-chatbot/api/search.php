<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db.php';

$q = trim($_GET['q'] ?? '');
if (strlen($q) < 2) { echo '[]'; exit; }

$safe = "%" . $conn->real_escape_string($q) . "%";
$res  = $conn->query("SELECT id, name, price, image FROM products WHERE (name LIKE '$safe' OR description LIKE '$safe') AND stock > 0 LIMIT 6");
echo json_encode($res->fetch_all(MYSQLI_ASSOC));
