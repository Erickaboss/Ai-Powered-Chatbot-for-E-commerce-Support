<?php
/**
 * AUTO Product Image & Description Fetcher
 * Uses Google Custom Search API (already configured in your project)
 * Fetches real product images and enhances descriptions
 * 
 * Cost: FREE for moderate usage (100 searches/day free)
 */

class ProductImageFetcher {
    
    private $apiKey;
    private $cx;
    private $uploadDir;
    
    public function __construct() {
        global $conn;
        
        // Use your existing Google CSE credentials
        $this->apiKey = defined('_GOOGLE_CSE_KEY') ? _GOOGLE_CSE_KEY : null;
        $this->cx = defined('_GOOGLE_CSE_CX') ? _GOOGLE_CSE_CX : null;
        
        if (!$this->apiKey || !$this->cx) {
            throw new Exception('Google Custom Search API not configured. Check config/secrets.php');
        }
        
        $this->uploadDir = __DIR__ . '/../assets/images/products/';
        
        // Ensure upload directory exists
        if (!is_dir($this->uploadDir)) {
            mkdir($this->uploadDir, 0755, true);
        }
    }
    
    /**
     * Search for product images using Google Custom Search
     */
    public function searchProductImages(string $productName, string $brand = ''): array {
        $query = urlencode("$brand $productName official product photo");
        
        $url = "https://www.googleapis.com/customsearch/v1?" . http_build_query([
            'key' => $this->apiKey,
            'cx' => $this->cx,
            'q' => $query,
            'searchType' => 'image',
            'num' => 5,
            'imgSize' => 'medium',
            'fileType' => 'jpg,png'
        ]);
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // For localhost
        $response = curl_exec($ch);
        curl_close($ch);
        
        $data = json_decode($response, true);
        
        if (isset($data['error'])) {
            return [
                'success' => false,
                'error' => $data['error']['message'] ?? 'API error'
            ];
        }
        
        $images = [];
        if (isset($data['items']) && is_array($data['items'])) {
            foreach ($data['items'] as $item) {
                $images[] = [
                    'url' => $item['link'],
                    'title' => $item['title'] ?? '',
                    'source' => $item['displayLink'] ?? ''
                ];
            }
        }
        
        return [
            'success' => true,
            'count' => count($images),
            'images' => $images
        ];
    }
    
    /**
     * Download and save product image
     */
    public function downloadAndSaveImage(string $imageUrl, string $productName): array {
        try {
            // Generate unique filename
            $safeName = preg_replace('/[^A-Za-z0-9]/', '_', $productName);
            $filename = substr($safeName, 0, 50) . '_' . time() . '.jpg';
            $targetPath = $this->uploadDir . $filename;
            
            // Download image
            $imageData = file_get_contents($imageUrl);
            
            if ($imageData === false) {
                return ['success' => false, 'error' => 'Failed to download image'];
            }
            
            // Save locally
            file_put_contents($targetPath, $imageData);
            
            // Optimize image if GD available
            if (function_exists('imagejpeg')) {
                $source = imagecreatefromstring($imageData);
                if ($source) {
                    imagejpeg($source, $targetPath, 85); // Compress to 85% quality
                    imagedestroy($source);
                }
            }
            
            $relativePath = 'assets/images/products/' . $filename;
            
            return [
                'success' => true,
                'path' => $relativePath,
                'filename' => $filename
            ];
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Enhance product description using AI
     */
    public function enhanceDescription(string $productName, string $currentDesc, string $brand = ''): string {
        // If you have Gemini API configured, use it
        if (defined('_GEMINI_KEY')) {
            return $this->enhanceWithGemini($productName, $currentDesc, $brand);
        }
        
        // Fallback: Template-based enhancement
        return $this->enhanceWithTemplate($productName, $currentDesc, $brand);
    }
    
    /**
     * Enhance description using Gemini AI
     */
    private function enhanceWithGemini(string $name, string $desc, string $brand): string {
        $geminiKey = _GEMINI_KEY;
        
        $prompt = "Enhance this product description to be more detailed and professional. " .
                  "Include specifications, features, and benefits. Keep it under 200 words.\n\n" .
                  "Product: $brand $name\n" .
                  "Current: $desc\n\n" .
                  "Enhanced:";
        
        $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-pro:generateContent?key=$geminiKey";
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            'contents' => [[
                'parts' => [['text' => $prompt]]
            ]]
        ]));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        $data = json_decode($response, true);
        
        if (isset($data['candidates'][0]['content']['parts'][0]['text'])) {
            return trim($data['candidates'][0]['content']['parts'][0]['text']);
        }
        
        return $this->enhanceWithTemplate($name, $desc, $brand);
    }
    
    /**
     * Template-based description enhancement
     */
    private function enhanceWithTemplate(string $name, string $desc, string $brand): string {
        $templates = [
            'phone' => "📱 **$brand $name**\n\n" .
                       "**Display:** Premium high-resolution display\n" .
                       "**Performance:** Fast processor for smooth multitasking\n" .
                       "**Camera:** Advanced camera system for stunning photos\n" .
                       "**Battery:** Long-lasting battery with fast charging\n" .
                       "**Storage:** Ample space for all your apps and media\n\n" .
                       "Original specs: $desc",
            
            'laptop' => "💻 **$brand $name**\n\n" .
                        "**Processor:** High-performance CPU for demanding tasks\n" .
                        "**Memory:** RAM optimized for multitasking\n" .
                        "**Storage:** Fast SSD for quick boot and load times\n" .
                        "**Display:** Crystal-clear screen for work and entertainment\n" .
                        "**Design:** Sleek, portable build\n\n" .
                        "Original specs: $desc",
            
            'default' => "✨ **$brand $name** - Premium Quality Product\n\n" .
                         "**Features:**\n" .
                         "• High-quality materials and construction\n" .
                         "• Advanced functionality\n" .
                         "• Reliable performance\n" .
                         "• Excellent value for money\n\n" .
                         "**Specifications:** $desc\n\n" .
                         "**Why Choose This Product?**\n" .
                         "This $brand product combines quality, performance, and affordability. " .
                         "Perfect for everyday use with reliable durability."
        ];
        
        // Detect category from name/description
        $lower = strtolower($name . ' ' . $desc);
        $category = 'default';
        
        if (strpos($lower, 'phone') !== false || strpos($lower, 'galaxy') !== false || 
            strpos($lower, 'iphone') !== false) {
            $category = 'phone';
        } elseif (strpos($lower, 'laptop') !== false || strpos($lower, 'macbook') !== false || 
                  strpos($lower, 'thinkpad') !== false) {
            $category = 'laptop';
        }
        
        return str_replace('$name', $name, 
               str_replace('$brand', $brand, 
               str_replace('$desc', $desc, $templates[$category])));
    }
    
    /**
     * Update product in database
     */
    public function updateProduct(int $productId, string $imagePath, string $newDesc, mysqli $conn): bool {
        $productId = (int)$productId;
        $imagePathSafe = $conn->real_escape_string($imagePath);
        $descSafe = $conn->real_escape_string($newDesc);
        
        $result = $conn->query("
            UPDATE products 
            SET image = '$imagePathSafe', 
                description = '$descSafe'
            WHERE id = $productId
        ");
        
        return $result !== false;
    }
    
    /**
     * Process single product
     */
    public function processProduct(array $product, mysqli $conn, bool $downloadImage = true): array {
        $productId = $product['id'];
        $name = $product['name'];
        $brand = $product['brand'] ?? '';
        $currentDesc = $product['description'] ?? '';
        
        $result = [
            'product_id' => $productId,
            'product_name' => $name,
            'image_updated' => false,
            'description_updated' => false,
            'errors' => []
        ];
        
        // Step 1: Search for images
        $searchResult = $this->searchProductImages($name, $brand);
        
        if (!$searchResult['success'] || $searchResult['count'] === 0) {
            $result['errors'][] = 'No images found: ' . ($searchResult['error'] ?? 'Unknown error');
        } else {
            // Step 2: Download best image
            if ($downloadImage) {
                $bestImage = $searchResult['images'][0];
                $downloadResult = $this->downloadAndSaveImage($bestImage['url'], $name);
                
                if ($downloadResult['success']) {
                    $this->updateProduct($productId, $downloadResult['path'], $currentDesc, $conn);
                    $result['image_updated'] = true;
                    $result['image_path'] = $downloadResult['path'];
                } else {
                    $result['errors'][] = 'Download failed: ' . $downloadResult['error'];
                }
            }
        }
        
        // Step 3: Enhance description
        try {
            $enhancedDesc = $this->enhanceDescription($name, $currentDesc, $brand);
            
            if ($enhancedDesc !== $currentDesc) {
                $this->updateProduct($productId, 
                    $downloadImage && isset($downloadResult['path']) ? $downloadResult['path'] : $product['image'],
                    $enhancedDesc, 
                    $conn
                );
                $result['description_updated'] = true;
                $result['new_description'] = $enhancedDesc;
            }
        } catch (Exception $e) {
            $result['errors'][] = 'Description enhancement failed: ' . $e->getMessage();
        }
        
        return $result;
    }
    
    /**
     * Batch process all products
     */
    public function processAllProducts(mysqli $conn, int $limit = 100, bool $downloadImages = true): array {
        $results = [];
        $processed = 0;
        $success = 0;
        $failed = 0;
        
        $res = $conn->query("SELECT id, name, description, brand, image FROM products LIMIT $limit");
        
        while ($product = $res->fetch_assoc()) {
            $processed++;
            echo "Processing product #$processed: {$product['name']}...\n";
            
            $result = $this->processProduct($product, $conn, $downloadImages);
            $results[] = $result;
            
            if ($result['image_updated'] || $result['description_updated']) {
                $success++;
                echo "✅ Success! ";
                if ($result['image_updated']) echo "[Image updated] ";
                if ($result['description_updated']) echo "[Description enhanced] ";
                echo "\n";
            } else {
                $failed++;
                echo "❌ Failed: " . implode(', ', $result['errors']) . "\n";
            }
            
            // Rate limiting: Sleep to avoid API quota
            usleep(500000); // 0.5 second delay
        }
        
        return [
            'total_processed' => $processed,
            'successful' => $success,
            'failed' => $failed,
            'details' => $results
        ];
    }
}

// CLI execution support
if (php_sapi_name() === 'cli') {
    require_once __DIR__ . '/config/db.php';
    
    echo "🚀 Starting Auto Image & Description Fetcher...\n\n";
    
    try {
        $fetcher = new ProductImageFetcher();
        $results = $fetcher->processAllProducts($conn, 50, true);
        
        echo "\n" . str_repeat('=', 60) . "\n";
        echo "✅ PROCESSING COMPLETE!\n";
        echo str_repeat('=', 60) . "\n";
        echo "Total processed: {$results['total_processed']}\n";
        echo "Successful: {$results['successful']}\n";
        echo "Failed: {$results['failed']}\n";
        echo str_repeat('=', 60) . "\n";
        
    } catch (Exception $e) {
        echo "❌ Error: " . $e->getMessage() . "\n";
        exit(1);
    }
}
