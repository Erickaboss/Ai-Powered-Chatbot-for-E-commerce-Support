<?php
/**
 * Migration: Add session_id and is_guest columns to chatbot_logs
 * Run once: http://localhost/ecommerce-chatbot/migrate_chatbot_logs.php
 * DELETE this file after running.
 */
require_once __DIR__ . '/config/db.php';

$results = [];

// Check if session_id column exists
$check = $conn->query("SHOW COLUMNS FROM chatbot_logs LIKE 'session_id'");
if ($check->num_rows === 0) {
    $r = $conn->query("ALTER TABLE chatbot_logs ADD COLUMN session_id VARCHAR(64) DEFAULT NULL AFTER user_id");
    $results[] = $r ? "✅ Added column: session_id" : "❌ Failed to add session_id: " . $conn->error;
} else {
    $results[] = "ℹ️ Column session_id already exists — skipped.";
}

// Check if is_guest column exists
$check2 = $conn->query("SHOW COLUMNS FROM chatbot_logs LIKE 'is_guest'");
if ($check2->num_rows === 0) {
    $r2 = $conn->query("ALTER TABLE chatbot_logs ADD COLUMN is_guest TINYINT(1) DEFAULT 1 AFTER session_id");
    $results[] = $r2 ? "✅ Added column: is_guest" : "❌ Failed to add is_guest: " . $conn->error;
} else {
    $results[] = "ℹ️ Column is_guest already exists — skipped.";
}

// Add indexes if missing
$conn->query("ALTER TABLE chatbot_logs ADD INDEX idx_session (session_id)");
$conn->query("ALTER TABLE chatbot_logs ADD INDEX idx_user (user_id)");
$results[] = "✅ Indexes ensured (duplicates ignored).";

echo "<pre style='font-family:monospace;font-size:1rem;padding:20px'>";
echo "<strong>chatbot_logs Migration</strong>\n\n";
foreach ($results as $r) echo $r . "\n";
echo "\n<strong>Done.</strong> Delete this file now: <code>migrate_chatbot_logs.php</code>";
echo "</pre>";
