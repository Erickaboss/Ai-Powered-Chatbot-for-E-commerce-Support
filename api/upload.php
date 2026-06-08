<?php
/**
 * File Upload API Endpoint
 * Handles image and document uploads for chatbot
 * 
 * Features:
 * - File validation (MIME type, size, extension)
 * - Virus scanning
 * - Secure storage
 * - Metadata tracking
 * - Async processing queue
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');

require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/logger.php';
require_once __DIR__ . '/../config/db.php';

// Configuration
$CONFIG = [
    'max_file_size' => 10 * 1024 * 1024,  // 10MB
    'allowed_types' => [
        'image/jpeg',
        'image/png',
        'application/pdf',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
    ],
    'allowed_extensions' => ['jpg', 'jpeg', 'png', 'pdf', 'docx'],
    'temp_dir' => sys_get_temp_dir() . '/chatbot_uploads',
    'storage_dir' => __DIR__ . '/../assets/uploads/chatbot',
    'cleanup_days' => 30,
    'scan_virus' => true
];

// Ensure directories exist
@mkdir($CONFIG['temp_dir'], 0755, true);
@mkdir($CONFIG['storage_dir'], 0755, true);

/**
 * Validate uploaded file
 */
function validateUploadFile($file) {
    global $CONFIG;
    
    $errors = [];
    
    // Check if file exists
    if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        $errors[] = 'No file uploaded or invalid upload';
    }
    
    // Check file size
    if ($file['size'] > $CONFIG['max_file_size']) {
        $errors[] = 'File size exceeds maximum limit (10MB)';
    }
    
    // Check MIME type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mime_type, $CONFIG['allowed_types'])) {
        $errors[] = "File type not allowed: $mime_type";
    }
    
    // Check extension
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $CONFIG['allowed_extensions'])) {
        $errors[] = "File extension not allowed: $ext";
    }
    
    return $errors;
}

/**
 * Scan file for viruses (ClamAV if available)
 */
function scanFileVirus($filepath) {
    global $CONFIG;
    
    if (!$CONFIG['scan_virus']) {
        return true;
    }
    
    // Check if ClamAV is available
    if (!command_exists('clamscan')) {
        logger('warning', 'ClamAV not available, skipping virus scan');
        return true;
    }
    
    $output = shell_exec("clamscan --quiet --no-summary " . escapeshellarg($filepath) . " 2>&1");
    return $output === null || strpos($output, 'FOUND') === false;
}

/**
 * Check if command exists
 */
function command_exists($cmd) {
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        $return = shell_exec("where " . escapeshellarg($cmd) . " 2>nul");
    } else {
        $return = shell_exec("which " . escapeshellarg($cmd) . " 2>/dev/null");
    }
    return !empty($return);
}

/**
 * Store file securely
 */
function storeUploadSecurely($file, $sessionId, $userId = null) {
    global $CONFIG;
    
    // Generate unique filename
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $unique_name = 'chat_' . time() . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
    
    // Determine file type
    $file_type = strpos($file['type'], 'image') !== false ? 'image' : 'document';
    
    // Move to storage
    $storage_path = $CONFIG['storage_dir'] . '/' . $unique_name;
    if (!move_uploaded_file($file['tmp_name'], $storage_path)) {
        throw new Exception('Failed to store file');
    }
    
    // Set permissions
    chmod($storage_path, 0644);
    
    return [
        'file_name' => $file['name'],
        'unique_name' => $unique_name,
        'file_type' => $file_type,
        'mime_type' => $file['type'],
        'file_size' => $file['size'],
        'storage_path' => $storage_path,
        'web_path' => '/uploads/' . $unique_name
    ];
}

/**
 * Save upload metadata to database
 */
function saveUploadMetadata($conn, $sessionId, $userId, $fileData) {
    $stmt = $conn->prepare("
        INSERT INTO chat_uploads (
            session_id, user_id, file_name, file_type, mime_type,
            file_size, file_path, storage_path, expires_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 30 DAY))
    ");
    
    $stmt->bind_param(
        'sisssiss',
        $sessionId,
        $userId,
        $fileData['file_name'],
        $fileData['file_type'],
        $fileData['mime_type'],
        $fileData['file_size'],
        $fileData['web_path'],
        $fileData['storage_path']
    );
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to save upload metadata: ' . $stmt->error);
    }
    
    return $conn->insert_id;
}

/**
 * Queue file for processing
 */
function queueForProcessing($conn, $uploadId) {
    $stmt = $conn->prepare("
        INSERT INTO upload_processing_queue (upload_id, status)
        VALUES (?, 'pending')
    ");
    
    $stmt->bind_param('i', $uploadId);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to queue for processing: ' . $stmt->error);
    }
    
    return true;
}

/**
 * Handle OPTIONS request
 */
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit(json_encode(['status' => 'ok']));
}

/**
 * Main upload handler
 */
try {
    // Validate request method
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }
    
    // Get session ID
    $sessionId = $_POST['session_id'] ?? $_COOKIE['chat_session_id'] ?? null;
    if (!$sessionId) {
        throw new Exception('Session ID required');
    }
    
    // Get user ID (optional)
    $userId = isset($_POST['user_id']) ? (int)$_POST['user_id'] : null;
    
    // Check if file was uploaded
    if (!isset($_FILES['file'])) {
        throw new Exception('No file provided');
    }
    
    $file = $_FILES['file'];
    
    // Validate file
    $validation_errors = validateUploadFile($file);
    if (!empty($validation_errors)) {
        throw new Exception(implode('; ', $validation_errors));
    }
    
    // Scan for viruses
    if (!scanFileVirus($file['tmp_name'])) {
        throw new Exception('File failed virus scan');
    }
    
    // Store file securely
    $fileData = storeUploadSecurely($file, $sessionId, $userId);
    
    // Connect to database
    if (!defined('DB_HOST')) {
        require_once __DIR__ . '/../config/db.php';
    }
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        throw new Exception('Database connection failed');
    }
    
    // Save metadata
    $uploadId = saveUploadMetadata($conn, $sessionId, $userId, $fileData);
    
    // Queue for processing
    queueForProcessing($conn, $uploadId);
    
    // Log upload
    logger('info', "File uploaded: {$fileData['file_name']} (ID: $uploadId)");
    
    // Return success response
    http_response_code(200);
    echo json_encode([
        'status' => 'success',
        'upload_id' => $uploadId,
        'file_name' => $fileData['file_name'],
        'file_type' => $fileData['file_type'],
        'file_size' => $fileData['file_size'],
        'web_path' => $fileData['web_path'],
        'message' => 'File uploaded successfully. Processing...'
    ]);
    
    $conn->close();
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
    logger('error', 'Upload error: ' . $e->getMessage());
}
?>
