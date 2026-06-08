<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/env.php';

$input = json_decode(file_get_contents('php://input'), true);
$log_id    = isset($input['log_id']) ? (int)$input['log_id'] : 0;
$rating    = isset($input['rating']) ? (int)$input['rating'] : -1;
$comment   = trim((string)($input['comment'] ?? ''));
$session_id = (string)($input['session_id'] ?? '');

if ($log_id <= 0 || !in_array($rating, [0, 1], true)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid log_id or rating (must be 0 or 1)']);
    exit;
}

$stmt = $conn->prepare("INSERT INTO chatbot_feedback (log_id, session_id, user_id, rating, comment) VALUES (?, ?, ?, ?, ?)");
$ui = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
if ($ui === null) {
    $stmt->bind_param("issis", $log_id, $session_id, $rating, $comment);
    $stmt->execute();
} else {
    $stmt->bind_param("isiss", $log_id, $session_id, $ui, $rating, $comment);
    $stmt->execute();
}
$stmt->execute();

echo json_encode(['success' => true, 'id' => (int)$conn->insert_id]);
