<?php
/**
 * Background Job: Process Upload Queue
 *
 * Processes pending uploads from the queue
 * Run via cron every 5 minutes
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/gemini_vision_processor.php';
require_once __DIR__ . '/product_matcher.php';
require_once __DIR__ . '/../includes/logger.php';

// Configuration
$MAX_CONCURRENT = 5;
$TIMEOUT = 300; // 5 minutes

try {
    // Connect to database
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        throw new Exception('Database connection failed: ' . $conn->connect_error);
    }
    
    // Get pending uploads
    $stmt = $conn->prepare("
        SELECT u.id, u.storage_path, u.file_type, u.session_id, u.user_id
        FROM chat_uploads u
        JOIN upload_processing_queue q ON u.id = q.upload_id
        WHERE q.status = 'pending'
        ORDER BY u.created_at ASC
        LIMIT ?
    ");
    
    $stmt->bind_param('i', $MAX_CONCURRENT);
    $stmt->execute();
    $result = $stmt->get_result();
    
    // Use the constant from config/db.php (loaded from secrets.php)
    $apiKey = defined('GEMINI_API_KEY') ? GEMINI_API_KEY : getenv('GEMINI_API_KEY');
    if (!$apiKey || $apiKey === 'your-gemini-api-key-here') {
        throw new Exception('GEMINI_API_KEY not configured in config/secrets.php');
    }
    
    $processor = new GeminiVisionProcessor($apiKey, $conn);
    $matcher = new ProductMatcher($conn);
    
    $processed = 0;
    $errors = 0;
    
    while ($upload = $result->fetch_assoc()) {
        try {
            logger('info', "Processing upload {$upload['id']}...");
            
            // Update status to processing
            updateProcessingStatus($conn, $upload['id'], 'processing');
            
            // Process upload
            $startTime = microtime(true);
            
            if ($upload['file_type'] === 'image') {
                $analysis = $processor->analyzeProductImage($upload['storage_path']);
            } else {
                $analysis = $processor->extractDocumentText($upload['storage_path']);
            }
            
            $responseTime = (int)((microtime(true) - $startTime) * 1000);
            
            // Save analysis results
            saveAnalysisResults($conn, $upload['id'], $analysis);
            
            // Find matching products
            if ($upload['file_type'] === 'image' && isset($analysis['search_query'])) {
                $matches = $matcher->findMatches($analysis, 10);
                $matcher->saveMatches($upload['id'], $matches);
            }
            
            // Track usage
            $processor->trackUsage($upload['id'], 0, 0, $responseTime);
            
            // Update status to completed
            updateProcessingStatus($conn, $upload['id'], 'completed');
            
            logger('info', "✓ Upload {$upload['id']} processed successfully (${responseTime}ms)");
            $processed++;
            
        } catch (Exception $e) {
            logger('error', "✗ Error processing upload {$upload['id']}: " . $e->getMessage());
            updateProcessingStatus($conn, $upload['id'], 'failed', $e->getMessage());
            $errors++;
        }
    }
    
    // Log summary
    logger('info', "Upload queue processing complete: $processed processed, $errors errors");
    
    // Cleanup expired uploads
    cleanupExpiredUploads($conn);
    
    $conn->close();
    
} catch (Exception $e) {
    logger('error', 'Upload queue processor error: ' . $e->getMessage());
    exit(1);
}

/**
 * Update processing status
 */
function updateProcessingStatus($conn, $uploadId, $status, $errorMessage = null) {
    $stmt = $conn->prepare("
        UPDATE upload_processing_queue
        SET status = ?, error_message = ?, updated_at = NOW()
        WHERE upload_id = ?
    ");
    
    $stmt->bind_param('ssi', $status, $errorMessage, $uploadId);
    $stmt->execute();
}

/**
 * Save analysis results
 */
function saveAnalysisResults($conn, $uploadId, $analysis) {
    $analysisJson = json_encode($analysis);
    $confidence = $analysis['confidence'] ?? 0.5;
    
    $stmt = $conn->prepare("
        UPDATE chat_uploads
        SET gemini_analysis = ?, confidence_score = ?
        WHERE id = ?
    ");
    
    $stmt->bind_param('sdi', $analysisJson, $confidence, $uploadId);
    $stmt->execute();
}

/**
 * Cleanup expired uploads
 */
function cleanupExpiredUploads($conn) {
    try {
        // Get expired uploads
        $stmt = $conn->prepare("
            SELECT storage_path FROM chat_uploads
            WHERE expires_at IS NOT NULL AND expires_at < NOW()
        ");
        $stmt->execute();
        $result = $stmt->get_result();
        
        $deleted = 0;
        while ($row = $result->fetch_assoc()) {
            if (file_exists($row['storage_path'])) {
                unlink($row['storage_path']);
            }
            $deleted++;
        }
        
        // Delete from database
        $stmt = $conn->prepare("
            DELETE FROM chat_uploads
            WHERE expires_at IS NOT NULL AND expires_at < NOW()
        ");
        $stmt->execute();
        
        if ($deleted > 0) {
            logger('info', "Cleaned up $deleted expired uploads");
        }
        
    } catch (Exception $e) {
        logger('error', 'Cleanup error: ' . $e->getMessage());
    }
}

?>
