<?php
/**
 * Product Matcher
 * Matches uploaded product images to database products
 * 
 * Features:
 * - Semantic search
 * - Similarity scoring
 * - Category filtering
 * - Price range matching
 * - Ranked results
 */

require_once __DIR__ . '/../includes/logger.php';

class ProductMatcher {
    private $conn;
    private $minConfidence = 0.6;
    
    public function __construct($conn) {
        $this->conn = $conn;
    }
    
    /**
     * Find matching products from image analysis
     */
    public function findMatches($imageAnalysis, $limit = 10) {
        try {
            // Generate search queries
            $searchQueries = $this->generateSearchQueries($imageAnalysis);
            
            // Search for products
            $matches = [];
            foreach ($searchQueries as $query) {
                $results = $this->searchProducts($query, $imageAnalysis);
                $matches = array_merge($matches, $results);
            }
            
            // Remove duplicates and rank
            $matches = $this->deduplicateAndRank($matches);
            
            // Limit results
            return array_slice($matches, 0, $limit);
            
        } catch (Exception $e) {
            logger('error', 'Product matching error: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Generate search queries from image analysis
     */
    private function generateSearchQueries($analysis) {
        $queries = [];
        
        // Query 1: Brand + Model
        if (!empty($analysis['brand']) && !empty($analysis['model'])) {
            $queries[] = $analysis['brand'] . ' ' . $analysis['model'];
        }
        
        // Query 2: Brand + Product Type
        if (!empty($analysis['brand']) && !empty($analysis['product_type'])) {
            $queries[] = $analysis['brand'] . ' ' . $analysis['product_type'];
        }
        
        // Query 3: Product Type
        if (!empty($analysis['product_type'])) {
            $queries[] = $analysis['product_type'];
        }
        
        // Query 4: Suggested search query
        if (!empty($analysis['search_query'])) {
            $queries[] = $analysis['search_query'];
        }
        
        // Query 5: Key features
        if (!empty($analysis['key_features'])) {
            $queries[] = implode(' ', array_slice($analysis['key_features'], 0, 3));
        }
        
        return array_filter(array_unique($queries));
    }
    
    /**
     * Search products by query
     */
    private function searchProducts($query, $imageAnalysis) {
        try {
            // Escape query
            $searchTerm = '%' . $this->conn->real_escape_string($query) . '%';
            
            // Build SQL query
            $sql = "
                SELECT 
                    p.id,
                    p.name,
                    p.description,
                    p.price,
                    p.image,
                    p.category_id,
                    p.stock,
                    p.brand,
                    c.name as category_name,
                    CASE 
                        WHEN p.name LIKE ? THEN 0.95
                        WHEN p.description LIKE ? THEN 0.85
                        WHEN p.brand LIKE ? THEN 0.75
                        ELSE 0.5
                    END as relevance_score
                FROM products p
                LEFT JOIN categories c ON p.category_id = c.id
                WHERE p.name LIKE ? 
                   OR p.description LIKE ?
                   OR p.brand LIKE ?
                ORDER BY relevance_score DESC
                LIMIT 20
            ";
            
            $stmt = $this->conn->prepare($sql);
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $this->conn->error);
            }
            
            $stmt->bind_param('ssssss', $searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $products = [];
            while ($row = $result->fetch_assoc()) {
                // Calculate match score
                $matchScore = $this->calculateMatchScore($row, $imageAnalysis);
                
                if ($matchScore >= $this->minConfidence) {
                    $row['match_score'] = $matchScore;
                    $row['match_reason'] = $this->getMatchReason($row, $imageAnalysis);
                    $products[] = $row;
                }
            }
            
            return $products;
            
        } catch (Exception $e) {
            logger('error', 'Search error: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Calculate match score between product and image analysis
     */
    private function calculateMatchScore($product, $imageAnalysis) {
        $score = 0;
        $factors = 0;
        
        // Brand match
        if (!empty($imageAnalysis['brand']) && !empty($product['brand'])) {
            if (stripos($product['brand'], $imageAnalysis['brand']) !== false ||
                stripos($imageAnalysis['brand'], $product['brand']) !== false) {
                $score += 0.3;
            }
            $factors += 0.3;
        }
        
        // Product type match
        if (!empty($imageAnalysis['product_type'])) {
            if (stripos($product['name'], $imageAnalysis['product_type']) !== false ||
                stripos($product['description'], $imageAnalysis['product_type']) !== false) {
                $score += 0.3;
            }
            $factors += 0.3;
        }
        
        // Price range match
        if (!empty($imageAnalysis['estimated_price_range'])) {
            if ($this->isPriceInRange($product['price'], $imageAnalysis['estimated_price_range'])) {
                $score += 0.2;
            }
            $factors += 0.2;
        }
        
        // Features match
        if (!empty($imageAnalysis['key_features'])) {
            $matchedFeatures = 0;
            foreach ($imageAnalysis['key_features'] as $feature) {
                if (stripos($product['description'], $feature) !== false) {
                    $matchedFeatures++;
                }
            }
            $featureScore = min(0.2, ($matchedFeatures / count($imageAnalysis['key_features'])) * 0.2);
            $score += $featureScore;
            $factors += 0.2;
        }
        
        // Normalize score
        return $factors > 0 ? $score / $factors : 0;
    }
    
    /**
     * Check if price is in estimated range
     */
    private function isPriceInRange($productPrice, $priceRange) {
        // Parse price range string (e.g., "50,000 - 100,000 RWF")
        preg_match('/(\d+)\s*-\s*(\d+)/', str_replace(',', '', $priceRange), $matches);
        
        if (count($matches) < 3) {
            return true; // Can't determine, assume match
        }
        
        $minPrice = (int)$matches[1];
        $maxPrice = (int)$matches[2];
        
        return $productPrice >= $minPrice && $productPrice <= $maxPrice;
    }
    
    /**
     * Get match reason
     */
    private function getMatchReason($product, $imageAnalysis) {
        $reasons = [];
        
        if (!empty($imageAnalysis['brand']) && !empty($product['brand'])) {
            if (stripos($product['brand'], $imageAnalysis['brand']) !== false) {
                $reasons[] = 'Brand match';
            }
        }
        
        if (!empty($imageAnalysis['product_type'])) {
            if (stripos($product['name'], $imageAnalysis['product_type']) !== false) {
                $reasons[] = 'Type match';
            }
        }
        
        if (empty($reasons)) {
            $reasons[] = 'Name match';
        }
        
        return implode(', ', $reasons);
    }
    
    /**
     * Deduplicate and rank matches
     */
    private function deduplicateAndRank($matches) {
        // Group by product ID
        $grouped = [];
        foreach ($matches as $match) {
            $id = $match['id'];
            if (!isset($grouped[$id])) {
                $grouped[$id] = $match;
            } else {
                // Keep highest score
                if ($match['match_score'] > $grouped[$id]['match_score']) {
                    $grouped[$id] = $match;
                }
            }
        }
        
        // Sort by match score
        usort($grouped, function($a, $b) {
            return $b['match_score'] <=> $a['match_score'];
        });
        
        return $grouped;
    }
    
    /**
     * Save match results
     */
    public function saveMatches($uploadId, $matches) {
        try {
            foreach ($matches as $match) {
                $stmt = $this->conn->prepare("
                    INSERT INTO product_image_matches (
                        upload_id, product_id, match_score, match_reason
                    ) VALUES (?, ?, ?, ?)
                ");
                
                $stmt->bind_param(
                    'idds',
                    $uploadId,
                    $match['id'],
                    $match['match_score'],
                    $match['match_reason']
                );
                
                $stmt->execute();
            }
            
            // Update upload with matches
            $matchesJson = json_encode($matches);
            $stmt = $this->conn->prepare("
                UPDATE chat_uploads
                SET product_matches = ?
                WHERE id = ?
            ");
            
            $stmt->bind_param('si', $matchesJson, $uploadId);
            $stmt->execute();
            
        } catch (Exception $e) {
            logger('error', 'Save matches error: ' . $e->getMessage());
        }
    }
    
    /**
     * Format matches for response
     */
    public function formatMatches($matches) {
        return array_map(function($match) {
            return [
                'product_id' => $match['id'],
                'name' => $match['name'],
                'price' => (int)$match['price'],
                'category' => $match['category_name'],
                'image' => $match['image'],
                'stock' => (int)$match['stock'],
                'match_score' => round($match['match_score'] * 100, 1),
                'match_reason' => $match['match_reason'],
                'in_stock' => $match['stock'] > 0
            ];
        }, $matches);
    }
}

?>
