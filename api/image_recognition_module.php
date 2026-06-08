<?php
/**
 * Image Recognition Module
 * Handles image upload, analysis, and product matching
 */

header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', '0');

require_once __DIR__ . '/../config/db.php';

class ImageRecognitionModule {
    private $conn;
    private $upload_dir = __DIR__ . '/../assets/images/chat_uploads/';
    private $max_file_size = 10 * 1024 * 1024; // 10MB
    private $allowed_types = ['image/jpeg', 'image/png', 'image/webp'];
    
    public function __construct($conn) {
        $this->conn = $conn;
        if (!is_dir($this->upload_dir)) {
            mkdir($this->upload_dir, 0755, true);
        }
    }
    
    /**
     * Process uploaded image
     */
    public function processUpload($file, $session_id, $user_id = null) {
        try {
            // Validate file
            $validation = $this->validateFile($file);
            if (!$validation['valid']) {
                return ['success' => false, 'error' => $validation['error']];
            }
            
            // Store file
            $file_info = $this->storeFile($file, $session_id);
            if (!$file_info) {
                return ['success' => false, 'error' => 'Failed to store file'];
            }
            
            // Save to database
            $upload_id = $this->saveUploadMetadata($session_id, $user_id, $file_info);
            
            // Analyze image
            $analysis = $this->analyzeImage($file_info['path']);
            
            // Match products
            $matches = $this->matchProducts($analysis);
            
            // Save matches
            $this->saveMatches($upload_id, $matches);
            
            return [
                'success' => true,
                'upload_id' => $upload_id,
                'file_name' => $file_info['name'],
                'analysis' => $analysis,
                'matches' => $matches
            ];
            
        } catch (Exception $e) {
            error_log("Image recognition error: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Validate uploaded file
     */
    private function validateFile($file) {
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            return ['valid' => false, 'error' => 'No file uploaded'];
        }
        
        if ($file['size'] > $this->max_file_size) {
            return ['valid' => false, 'error' => 'File too large (max 10MB)'];
        }
        
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($mime, $this->allowed_types)) {
            return ['valid' => false, 'error' => 'Invalid file type'];
        }
        
        return ['valid' => true];
    }
    
    /**
     * Store file securely
     */
    private function storeFile($file, $session_id) {
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $filename = 'chat_' . time() . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
        $filepath = $this->upload_dir . $filename;
        
        if (!move_uploaded_file($file['tmp_name'], $filepath)) {
            return null;
        }
        
        chmod($filepath, 0644);
        
        return [
            'name' => $file['name'],
            'filename' => $filename,
            'path' => $filepath,
            'size' => $file['size'],
            'mime' => $file['type']
        ];
    }
    
    /**
     * Save upload metadata to database
     */
    private function saveUploadMetadata($session_id, $user_id, $file_info) {
        $stmt = $this->conn->prepare("
            INSERT INTO chat_uploads (session_id, user_id, file_name, file_type, mime_type, file_size, file_path, storage_path, expires_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 30 DAY))
        ");
        
        $file_type = strpos($file_info['mime'], 'image') !== false ? 'image' : 'document';
        $web_path = '/assets/images/chat_uploads/' . $file_info['filename'];
        
        $stmt->bind_param(
            'sisssiss',
            $session_id,
            $user_id,
            $file_info['name'],
            $file_type,
            $file_info['mime'],
            $file_info['size'],
            $web_path,
            $file_info['path']
        );
        
        if (!$stmt->execute()) {
            throw new Exception('Failed to save metadata: ' . $stmt->error);
        }
        
        return $this->conn->insert_id;
    }
    
    /**
     * Analyze image using Gemini Vision API
     */
    private function analyzeImage($image_path) {
        $api_key = GEMINI_API_KEY;
        if (empty($api_key)) {
            return $this->analyzeImageLocally($image_path);
        }
        
        try {
            $image_data = base64_encode(file_get_contents($image_path));
            $mime_type = mime_content_type($image_path);
            
            $payload = [
                'contents' => [
                    [
                        'parts' => [
                            [
                                'text' => 'Analyze this product image. Identify: 1) Product category (e.g., electronics, clothing, home appliances) 2) Product type/name 3) Brand if visible 4) Color/style 5) Estimated price range in RWF. Be concise and specific.'
                            ],
                            [
                                'inlineData' => [
                                    'mimeType' => $mime_type,
                                    'data' => $image_data
                                ]
                            ]
                        ]
                    ]
                ]
            ];
            
            $ch = curl_init("https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent");
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($payload),
                CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'X-Goog-Api-Key: ' . $api_key],
                CURLOPT_TIMEOUT => 15,
            ]);
            
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($http_code !== 200) {
                return $this->analyzeImageLocally($image_path);
            }
            
            $data = json_decode($response, true);
            $analysis = $data['candidates'][0]['content']['parts'][0]['text'] ?? null;
            
            return $analysis ?: $this->analyzeImageLocally($image_path);
            
        } catch (Exception $e) {
            error_log("Gemini API error: " . $e->getMessage());
            return $this->analyzeImageLocally($image_path);
        }
    }
    
    /**
     * Fallback local image analysis
     */
    private function analyzeImageLocally($image_path) {
        $filename = basename($image_path);
        $size = getimagesize($image_path);
        
        return "Image: $filename | Dimensions: {$size[0]}x{$size[1]} | Type: {$size['mime']}. Please describe what you're looking for.";
    }
    
    /**
     * Match products based on image analysis
     */
    private function matchProducts($analysis) {
        $keywords = $this->extractKeywords($analysis);
        $matched = [];
        
        foreach ($keywords as $keyword) {
            $stmt = $this->conn->prepare("
                SELECT id, name, price, brand, category_id, stock, image, description
                FROM products
                WHERE (name LIKE ? OR brand LIKE ? OR description LIKE ?)
                AND stock > 0
                LIMIT 5
            ");
            
            $search = "%$keyword%";
            $stmt->bind_param('sss', $search, $search, $search);
            $stmt->execute();
            $result = $stmt->get_result();
            
            while ($row = $result->fetch_assoc()) {
                if (!isset($matched[$row['id']])) {
                    $matched[$row['id']] = $row;
                }
            }
        }
        
        return array_slice($matched, 0, 8);
    }
    
    /**
     * Extract keywords from analysis
     */
    private function extractKeywords($text) {
        $keywords = [];
        $words = preg_split('/[\s,\.;:\-()]+/', strtolower($text));
        
        $stopwords = ['the', 'a', 'an', 'is', 'are', 'this', 'that', 'and', 'or', 'in', 'on', 'at', 'to', 'for', 'of', 'with', 'by', 'from', 'as', 'be', 'have', 'has', 'had', 'do', 'does', 'did', 'will', 'would', 'could', 'should', 'may', 'might', 'must', 'can', 'if', 'else', 'image', 'product', 'please', 'describe', 'looking'];
        
        foreach ($words as $word) {
            if (strlen($word) > 2 && !in_array($word, $stopwords) && !is_numeric($word)) {
                $keywords[] = $word;
            }
        }
        
        return array_slice(array_unique($keywords), 0, 8);
    }
    
    /**
     * Save matches to database
     */
    private function saveMatches($upload_id, $matches) {
        foreach ($matches as $product) {
            $stmt = $this->conn->prepare("
                INSERT INTO image_recognition_matches (upload_id, product_id, match_score, matched_at)
                VALUES (?, ?, ?, NOW())
            ");
            
            $score = 0.85; // Default confidence score
            $stmt->bind_param('iii', $upload_id, $product['id'], $score);
            $stmt->execute();
        }
    }
    
    /**
     * Get matches for upload
     */
    public function getMatches($upload_id) {
        $stmt = $this->conn->prepare("
            SELECT p.id, p.name, p.price, p.brand, p.stock, p.image, m.match_score
            FROM image_recognition_matches m
            JOIN products p ON m.product_id = p.id
            WHERE m.upload_id = ?
            ORDER BY m.match_score DESC
        ");
        
        $stmt->bind_param('i', $upload_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $matches = [];
        while ($row = $result->fetch_assoc()) {
            $matches[] = $row;
        }
        
        return $matches;
    }
}

// Handle requests
try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!isset($_FILES['image'])) {
            throw new Exception('No image provided');
        }
        
        $session_id = $_POST['session_id'] ?? bin2hex(random_bytes(16));
        $user_id = isset($_POST['user_id']) ? (int)$_POST['user_id'] : null;
        
        $module = new ImageRecognitionModule($conn);
        $result = $module->processUpload($_FILES['image'], $session_id, $user_id);
        
        echo json_encode($result);
    } else {
        throw new Exception('Invalid request method');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
