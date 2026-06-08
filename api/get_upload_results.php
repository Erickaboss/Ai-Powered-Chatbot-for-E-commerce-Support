<?php
/**
 * Get Upload Processing Results
 * Returns the results of image analysis and product matching
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/logger.php';

try {
    // Get request data
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['upload_id'])) {
        throw new Exception('Upload ID required');
    }
    
    $uploadId = (int)$input['upload_id'];
    
    // Connect to database
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        throw new Exception('Database connection failed');
    }
    
    // Get upload details
    $stmt = $conn->prepare("
        SELECT 
            u.id,
            u.file_name,
            u.file_type,
            u.gemini_analysis,
            u.product_matches,
            u.confidence_score,
            q.status,
            q.error_message
        FROM chat_uploads u
        LEFT JOIN upload_processing_queue q ON u.id = q.upload_id
        WHERE u.id = ?
    ");
    
    $stmt->bind_param('i', $uploadId);
    $stmt->execute();
    $result = $stmt->get_result();
    $upload = $result->fetch_assoc();
    
    if (!$upload) {
        throw new Exception('Upload not found');
    }
    
    // Check processing status
    $status = $upload['status'] ?? 'pending';
    
    if ($status === 'pending' || $status === 'processing') {
        http_response_code(202);
        echo json_encode([
            'status' => 'processing',
            'upload_id' => $uploadId,
            'message' => 'Still processing your file...'
        ]);
    } elseif ($status === 'failed') {
        http_response_code(400);
        echo json_encode([
            'status' => 'failed',
            'upload_id' => $uploadId,
            'error' => $upload['error_message'] ?? 'Processing failed'
        ]);
    } else {
        // Processing completed
        $analysis = json_decode($upload['gemini_analysis'], true);
        $matches = json_decode($upload['product_matches'], true);
        
        // Format matches for response
        $formattedMatches = [];
        if (is_array($matches)) {
            foreach ($matches as $match) {
                $formattedMatches[] = [
                    'product_id' => $match['id'] ?? $match['product_id'],
                    'name' => $match['name'],
                    'price' => (int)$match['price'],
                    'category' => $match['category_name'] ?? $match['category'],
                    'image' => $match['image'],
                    'stock' => (int)($match['stock'] ?? 0),
                    'match_score' => round(($match['match_score'] ?? 0) * 100, 1),
                    'match_reason' => $match['match_reason'] ?? 'Product match',
                    'in_stock' => ($match['stock'] ?? 0) > 0
                ];
            }
        }
        
        http_response_code(200);
        echo json_encode([
            'status' => 'completed',
            'upload_id' => $uploadId,
            'file_name' => $upload['file_name'],
            'file_type' => $upload['file_type'],
            'analysis' => $analysis,
            'matches' => $formattedMatches,
            'confidence' => (float)$upload['confidence_score'],
            'message' => count($formattedMatches) > 0 
                ? 'Found ' . count($formattedMatches) . ' matching products'
                : 'No matching products found'
        ]);
    }
    
    $conn->close();
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
    logger('error', 'Get upload results error: ' . $e->getMessage());
}

?>
