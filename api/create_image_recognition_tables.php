<?php
/**
 * Create Image Recognition Tables
 * Run this once to set up the database schema for image recognition
 */

require_once __DIR__ . '/../config/db.php';

try {
    // Create chat_uploads table
    $sql1 = "CREATE TABLE IF NOT EXISTS chat_uploads (
        id INT AUTO_INCREMENT PRIMARY KEY,
        session_id VARCHAR(64) NOT NULL,
        user_id INT DEFAULT NULL,
        file_name VARCHAR(255) NOT NULL,
        file_type VARCHAR(50),
        mime_type VARCHAR(100),
        file_size INT,
        file_path VARCHAR(255),
        storage_path VARCHAR(255),
        expires_at DATETIME,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
        INDEX idx_session (session_id),
        INDEX idx_user (user_id),
        INDEX idx_expires (expires_at)
    )";
    
    if ($conn->query($sql1)) {
        echo "✓ chat_uploads table created successfully\n";
    } else {
        echo "✗ Error creating chat_uploads table: " . $conn->error . "\n";
    }
    
    // Create image_recognition_matches table
    $sql2 = "CREATE TABLE IF NOT EXISTS image_recognition_matches (
        id INT AUTO_INCREMENT PRIMARY KEY,
        upload_id INT NOT NULL,
        product_id INT NOT NULL,
        match_score DECIMAL(5,4),
        matched_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (upload_id) REFERENCES chat_uploads(id) ON DELETE CASCADE,
        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
        INDEX idx_upload (upload_id),
        INDEX idx_product (product_id),
        INDEX idx_score (match_score)
    )";
    
    if ($conn->query($sql2)) {
        echo "✓ image_recognition_matches table created successfully\n";
    } else {
        echo "✗ Error creating image_recognition_matches table: " . $conn->error . "\n";
    }
    
    // Create upload_processing_queue table
    $sql3 = "CREATE TABLE IF NOT EXISTS upload_processing_queue (
        id INT AUTO_INCREMENT PRIMARY KEY,
        upload_id INT NOT NULL,
        status ENUM('pending', 'processing', 'completed', 'failed') DEFAULT 'pending',
        error_message TEXT,
        processed_at TIMESTAMP NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (upload_id) REFERENCES chat_uploads(id) ON DELETE CASCADE,
        INDEX idx_status (status),
        INDEX idx_created (created_at)
    )";
    
    if ($conn->query($sql3)) {
        echo "✓ upload_processing_queue table created successfully\n";
    } else {
        echo "✗ Error creating upload_processing_queue table: " . $conn->error . "\n";
    }
    
    echo "\n✅ All image recognition tables created successfully!\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

$conn->close();
?>
