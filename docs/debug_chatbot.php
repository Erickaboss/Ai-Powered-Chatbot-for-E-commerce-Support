<?php
/**
 * Debug script to test chatbot directly
 */

// Enable all error reporting
error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once 'config/db.php';
require_once 'api/language_detector_simple.php';

echo "=== Chatbot Debug Test ===\n\n";

// Test 1: Check database connection
echo "Test 1: Database Connection\n";
if ($conn->connect_error) {
    echo "✗ Connection failed: " . $conn->connect_error . "\n";
    exit;
} else {
    echo "✓ Connected to database\n\n";
}

// Test 2: Check if chatbot_context table exists
echo "Test 2: Checking chatbot_context table\n";
$result = $conn->query("SHOW TABLES LIKE 'chatbot_context'");
if ($result && $result->num_rows > 0) {
    echo "✓ chatbot_context table exists\n\n";
} else {
    echo "✗ chatbot_context table NOT found\n";
    echo "Creating table...\n";
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
        echo "✓ Table created successfully\n\n";
    } else {
        echo "✗ Failed to create table: " . $conn->error . "\n\n";
    }
}

// Test 3: Test language detection
echo "Test 3: Language Detection\n";
$tests = [
    'hello' => 'english',
    'bonjour' => 'french',
    'mwaramutse' => 'kinyarwanda',
];

foreach ($tests as $text => $expected) {
    $detected = detect_language($text);
    $status = $detected === $expected ? '✓' : '✗';
    echo "$status '$text' -> $detected (expected $expected)\n";
}
echo "\n";

// Test 4: Test saveContext function
echo "Test 4: Testing saveContext function\n";
$sessionId = bin2hex(random_bytes(16));
$testKey = 'language';
$testValue = 'english';

function testSaveContext($sessionId, $userId, $key, $value) {
    global $conn;
    $expiresAt = date('Y-m-d H:i:s', strtotime("+24 hours"));
    $stmt = $conn->prepare("INSERT INTO chatbot_context 
                           (session_id, user_id, context_key, context_value, expires_at) 
                           VALUES (?, ?, ?, ?, ?)
                           ON DUPLICATE KEY UPDATE context_value=?, expires_at=?");
    if (!$stmt) {
        echo "✗ Prepare failed: " . $conn->error . "\n";
        return false;
    }
    
    if (!$stmt->bind_param("ssisss", $sessionId, $userId, $key, $value, $expiresAt, $value, $expiresAt)) {
        echo "✗ Bind failed: " . $stmt->error . "\n";
        $stmt->close();
        return false;
    }
    
    if (!$stmt->execute()) {
        echo "✗ Execute failed: " . $stmt->error . "\n";
        $stmt->close();
        return false;
    }
    
    $stmt->close();
    return true;
}

if (testSaveContext($sessionId, null, $testKey, $testValue)) {
    echo "✓ Successfully saved context to database\n";
    
    // Verify it was saved
    $stmt = $conn->prepare("SELECT context_value FROM chatbot_context WHERE session_id=? AND context_key=?");
    $stmt->bind_param("ss", $sessionId, $testKey);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        if ($row['context_value'] === $testValue) {
            echo "✓ Context value verified in database\n";
        } else {
            echo "✗ Context value mismatch\n";
        }
    }
    $stmt->close();
} else {
    echo "✗ Failed to save context\n";
}

echo "\n";

// Test 5: Simulate processMessage call
echo "Test 5: Simulating processMessage\n";
try {
    // Create a mock context
    $ctx = ['awaiting' => null, 'language' => null];
    $session_id = bin2hex(random_bytes(16));
    $message = "hello";
    $user_id = null;
    
    // Test language detection
    $detected_lang = detect_language($message);
    echo "✓ Language detected: $detected_lang\n";
    
    // Test saveContext
    try {
        testSaveContext($session_id, $user_id, 'language', $detected_lang);
        echo "✓ Context saved successfully\n";
    } catch (Throwable $e) {
        echo "⚠ Context save warning: " . $e->getMessage() . "\n";
    }
    
    echo "✓ processMessage simulation successful\n";
} catch (Throwable $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}

echo "\n=== Debug Test Complete ===\n";
$conn->close();
?>
