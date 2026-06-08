<?php
/**
 * Database Migration Script
 * Applies all database enhancements for image upload and ML monitoring
 */

require_once __DIR__ . '/../config/db.php';

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Read and execute migrations
$migrations_file = __DIR__ . '/../database_enhancements.sql';
$migrations = file_get_contents($migrations_file);

// Split by semicolon and execute each statement
$statements = array_filter(array_map('trim', explode(';', $migrations)));

$executed = 0;
$errors = [];

foreach ($statements as $statement) {
    if (empty($statement) || strpos($statement, '--') === 0) {
        continue;
    }
    
    if ($conn->query($statement) === TRUE) {
        $executed++;
    } else {
        $errors[] = "Error: " . $conn->error . "\nStatement: " . substr($statement, 0, 100) . "...";
    }
}

$conn->close();

// Output results
echo json_encode([
    'status' => empty($errors) ? 'success' : 'partial',
    'executed' => $executed,
    'errors' => $errors,
    'message' => empty($errors) ? "Successfully executed $executed migration statements" : "Executed $executed statements with " . count($errors) . " errors"
], JSON_PRETTY_PRINT);

?>
