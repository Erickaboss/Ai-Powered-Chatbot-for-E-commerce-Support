<?php
/**
 * Gemini Vision API Processor
 * Analyzes uploaded images using Google Gemini Vision API
 * 
 * Features:
 * - Image analysis with Gemini 2.0 Flash
 * - Product detail extraction
 * - Document text extraction
 * - Confidence scoring
 * - Result caching
 */

require_once __DIR__ . '/../includes/logger.php';

class GeminiVisionProcessor {
    private $api_key;
    private $model = 'gemini-2.0-flash';
    private $api_endpoint = 'https://generativelanguage.googleapis.com/v1beta/models';
    private $conn;
    
    public function __construct($api_key, $conn = null) {
        $this->api_key = $api_key;
        $this->conn = $conn;
    }
    
    /**
     * Analyze image and extract product details
     */
    public function analyzeProductImage($imagePath) {
        try {
            // Read image file
            if (!file_exists($imagePath)) {
                throw new Exception("Image file not found: $imagePath");
            }
            
            $imageData = file_get_contents($imagePath);
            $base64Image = base64_encode($imageData);
            
            // Determine MIME type
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $imagePath);
            finfo_close($finfo);
            
            // Prepare prompt for product analysis
            $prompt = $this->getProductAnalysisPrompt();
            
            // Call Gemini API
            $response = $this->callGeminiAPI($base64Image, $mimeType, $prompt);
            
            // Parse response
            $analysis = $this->parseProductAnalysis($response);
            
            return $analysis;
            
        } catch (Exception $e) {
            logger('error', 'Gemini Vision error: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Extract text from document
     */
    public function extractDocumentText($documentPath) {
        try {
            if (!file_exists($documentPath)) {
                throw new Exception("Document file not found: $documentPath");
            }
            
            $ext = strtolower(pathinfo($documentPath, PATHINFO_EXTENSION));
            
            if ($ext === 'pdf') {
                return $this->extractPdfText($documentPath);
            } elseif ($ext === 'docx') {
                return $this->extractDocxText($documentPath);
            } else {
                throw new Exception("Unsupported document type: $ext");
            }
            
        } catch (Exception $e) {
            logger('error', 'Document extraction error: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Extract text from PDF using Gemini
     */
    private function extractPdfText($pdfPath) {
        // For PDF, we'll use Gemini's document understanding
        $imageData = file_get_contents($pdfPath);
        $base64Data = base64_encode($imageData);
        
        $prompt = "Extract all text from this PDF document. Format it clearly with sections and paragraphs.";
        
        $response = $this->callGeminiAPI($base64Data, 'application/pdf', $prompt);
        
        return $this->parseTextExtraction($response);
    }
    
    /**
     * Extract text from DOCX
     */
    private function extractDocxText($docxPath) {
        // DOCX is a ZIP file, extract and read XML
        $zip = new ZipArchive();
        if (!$zip->open($docxPath)) {
            throw new Exception("Failed to open DOCX file");
        }
        
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();
        
        if (!$xml) {
            throw new Exception("Failed to read DOCX content");
        }
        
        // Parse XML and extract text
        $dom = new DOMDocument();
        $dom->loadXML($xml);
        
        $xpath = new DOMXPath($dom);
        $paragraphs = $xpath->query('//w:p');
        
        $text = [];
        foreach ($paragraphs as $para) {
            $runs = $xpath->query('.//w:t', $para);
            $paraText = '';
            foreach ($runs as $run) {
                $paraText .= $run->nodeValue;
            }
            if (!empty($paraText)) {
                $text[] = $paraText;
            }
        }
        
        return [
            'text' => implode("\n", $text),
            'word_count' => str_word_count(implode(" ", $text)),
            'extraction_method' => 'docx_xml_parsing'
        ];
    }
    
    /**
     * Call Gemini Vision API
     */
    private function callGeminiAPI($base64Data, $mimeType, $prompt) {
        $url = "{$this->api_endpoint}/{$this->model}:generateContent";
        
        $payload = [
            'contents' => [
                [
                    'parts' => [
                        [
                            'text' => $prompt
                        ],
                        [
                            'inlineData' => [
                                'mimeType' => $mimeType,
                                'data' => $base64Data
                            ]
                        ]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature' => 0.7,
                'topK' => 40,
                'topP' => 0.95,
                'maxOutputTokens' => 2048
            ]
        ];
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'X-Goog-Api-Key: ' . $this->api_key],
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_TIMEOUT => 30
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            throw new Exception("Gemini API error: HTTP $httpCode");
        }
        
        $data = json_decode($response, true);
        
        if (!isset($data['candidates'][0]['content']['parts'][0]['text'])) {
            throw new Exception("Invalid Gemini API response");
        }
        
        return $data['candidates'][0]['content']['parts'][0]['text'];
    }
    
    /**
     * Get product analysis prompt
     */
    private function getProductAnalysisPrompt() {
        return <<<PROMPT
Analyze this product image and extract the following information in JSON format:
{
  "product_type": "Type of product (e.g., smartphone, laptop, etc.)",
  "brand": "Brand name if visible",
  "model": "Model name/number if visible",
  "key_features": ["Feature 1", "Feature 2", ...],
  "visible_specs": {
    "color": "Color if visible",
    "size": "Size if visible",
    "other_specs": "Any other visible specifications"
  },
  "condition": "New/Used/Refurbished",
  "estimated_price_range": "If price is visible or can be estimated",
  "confidence": 0.0-1.0,
  "search_query": "Suggested search query to find this product"
}

Be precise and only include information that is clearly visible in the image.
PROMPT;
    }
    
    /**
     * Parse product analysis response
     */
    private function parseProductAnalysis($response) {
        // Extract JSON from response
        $jsonMatch = preg_match('/\{[\s\S]*\}/', $response, $matches);
        
        if (!$jsonMatch) {
            throw new Exception("Failed to parse Gemini response");
        }
        
        $analysis = json_decode($matches[0], true);
        
        if (!$analysis) {
            throw new Exception("Invalid JSON in Gemini response");
        }
        
        return [
            'product_type' => $analysis['product_type'] ?? null,
            'brand' => $analysis['brand'] ?? null,
            'model' => $analysis['model'] ?? null,
            'key_features' => $analysis['key_features'] ?? [],
            'visible_specs' => $analysis['visible_specs'] ?? [],
            'condition' => $analysis['condition'] ?? 'Unknown',
            'estimated_price_range' => $analysis['estimated_price_range'] ?? null,
            'confidence' => $analysis['confidence'] ?? 0.5,
            'search_query' => $analysis['search_query'] ?? null
        ];
    }
    
    /**
     * Parse text extraction response
     */
    private function parseTextExtraction($response) {
        return [
            'text' => $response,
            'word_count' => str_word_count($response),
            'extraction_method' => 'gemini_vision'
        ];
    }
    
    /**
     * Track API usage
     */
    public function trackUsage($uploadId, $inputTokens, $outputTokens, $responseTime) {
        if (!$this->conn) {
            return;
        }
        
        $estimatedCost = ($inputTokens * 0.000075 + $outputTokens * 0.0003) / 1000;
        
        $stmt = $this->conn->prepare("
            INSERT INTO gemini_api_usage (
                api_type, model_name, input_tokens, output_tokens,
                total_tokens, estimated_cost, response_time_ms, status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, 'success')
        ");
        
        $totalTokens = $inputTokens + $outputTokens;
        $stmt->bind_param(
            'ssiiiid',
            $apiType = 'vision',
            $this->model,
            $inputTokens,
            $outputTokens,
            $totalTokens,
            $estimatedCost,
            $responseTime
        );
        
        $stmt->execute();
    }
}

/**
 * Process upload from queue
 */
function processUploadFromQueue($uploadId, $conn, $apiKey) {
    try {
        // Get upload details
        $stmt = $conn->prepare("
            SELECT id, file_name, storage_path, file_type, session_id, user_id
            FROM chat_uploads
            WHERE id = ?
        ");
        $stmt->bind_param('i', $uploadId);
        $stmt->execute();
        $result = $stmt->get_result();
        $upload = $result->fetch_assoc();
        
        if (!$upload) {
            throw new Exception("Upload not found: $uploadId");
        }
        
        // Update processing status
        updateProcessingStatus($conn, $uploadId, 'processing');
        
        // Initialize processor
        $processor = new GeminiVisionProcessor($apiKey, $conn);
        
        // Process based on file type
        $startTime = microtime(true);
        
        if ($upload['file_type'] === 'image') {
            $analysis = $processor->analyzeProductImage($upload['storage_path']);
        } else {
            $analysis = $processor->extractDocumentText($upload['storage_path']);
        }
        
        $responseTime = (int)((microtime(true) - $startTime) * 1000);
        
        // Save analysis results
        saveAnalysisResults($conn, $uploadId, $analysis);
        
        // Track usage
        $processor->trackUsage($uploadId, 0, 0, $responseTime);
        
        // Update processing status
        updateProcessingStatus($conn, $uploadId, 'completed');
        
        logger('info', "Upload $uploadId processed successfully");
        
        return $analysis;
        
    } catch (Exception $e) {
        logger('error', "Upload processing error: " . $e->getMessage());
        updateProcessingStatus($conn, $uploadId, 'failed', $e->getMessage());
        throw $e;
    }
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

?>
