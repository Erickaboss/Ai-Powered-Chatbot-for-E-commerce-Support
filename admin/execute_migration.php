<?php
/**
 * Migration Execution Script
 * Creates missing chatbot_context table
 */

require_once __DIR__ . '/../config/db.php';

// Check if user is admin
session_start();
if (!isset($_SESSION['user_id'])) {
    die(json_encode(['error' => 'Not authenticated']));
}

$userId = (int)$_SESSION['user_id'];
$user = $conn->query("SELECT role FROM users WHERE id=$userId")->fetch_assoc();

if (!$user || $user['role'] !== 'admin') {
    die(json_encode(['error' => 'Not authorized']));
}

// Create chatbot_context table
$sql = "CREATE TABLE IF NOT EXISTS chatbot_context (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id VARCHAR(64) NOT NULL,
    user_id INT DEFAULT NULL,
    context_key VARCHAR(100) NOT NULL,
    context_value TEXT DEFAULT NULL,
    expires_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_session (session_id),
    INDEX idx_user (user_id),
    INDEX idx_key (context_key),
    UNIQUE KEY unique_context (session_id, user_id, context_key),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
)";

if ($conn->query($sql)) {
    echo json_encode(['success' => true, 'message' => 'chatbot_context table created successfully']);
} else {
    echo json_encode(['success' => false, 'error' => $conn->error]);
}

$conn->close();
?>
