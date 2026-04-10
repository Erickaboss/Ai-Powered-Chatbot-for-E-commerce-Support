<?php
/**
 * Admin Panel - Auto Update Product Images & Descriptions
 * Uses Google Custom Search API to fetch real images and enhance descriptions
 */
require_once '../config/db.php';
require_once 'includes/admin_header.php';
require_once __DIR__ . '/../includes/auto_product_fetcher.php';

// Check if running batch process
$processing = false;
$results = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action === 'update_all') {
        $processing = true;
        $limit = (int)($_POST['limit'] ?? 20);
        $downloadImages = isset($_POST['download_images']);
        
        try {
            $fetcher = new ProductImageFetcher();
            $results = $fetcher->processAllProducts($conn, $limit, $downloadImages);
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    } elseif ($action === 'update_single') {
        $productId = (int)$_POST['product_id'];
        $downloadImages = isset($_POST['download_images']);
        
        $product = $conn->query("SELECT * FROM products WHERE id=$productId")->fetch_assoc();
        
        if ($product) {
            try {
                $fetcher = new ProductImageFetcher();
                $results = $fetcher->processProduct($product, $conn, $downloadImages);
            } catch (Exception $e) {
                $error = $e->getMessage();
            }
        }
    }
}

// Get product statistics
$totalProducts = $conn->query("SELECT COUNT(*) as count FROM products")->fetch_assoc()['count'];
$productsWithImages = $conn->query("SELECT COUNT(*) as count FROM products WHERE image != '' AND image IS NOT NULL")->fetch_assoc()['count'];
$productsWithDesc = $conn->query("SELECT COUNT(*) as count FROM products WHERE description != '' AND description IS NOT NULL")->fetch_assoc()['count'];
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-12">
            <h2 class="mb-4">🔄 Auto Update Product Images & Descriptions</h2>
            
            <?php if (isset($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                ❌ Error: <?= htmlspecialchars($error) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>
            
            <?php if ($results && !$processing): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <h5 class="alert-heading">✅ Processing Complete!</h5>
                <hr>
                <p class="mb-1"><strong>Total Processed:</strong> <?= $results['total_processed'] ?></p>
                <p class="mb-1"><strong>Successful:</strong> <?= $results['successful'] ?></p>
                <p class="mb-1"><strong>Failed:</strong> <?= $results['failed'] ?></p>
                
                <?php if (!empty($results['details'])): ?>
                <details style="margin-top: 15px;">
                    <summary style="cursor: pointer;">View Details</summary>
                    <ul style="margin-top: 10px; font-size: 0.9em;">
                        <?php foreach ($results['details'] as $detail): ?>
                        <li style="margin-bottom: 8px;">
                            <strong><?= htmlspecialchars($detail['product_name']) ?></strong><br>
                            <?php if ($detail['image_updated']): ?>
                                <span style="color: green;">✓ Image updated</span>
                            <?php endif; ?>
                            <?php if ($detail['description_updated']): ?>
                                <span style="color: blue;">✓ Description enhanced</span>
                            <?php endif; ?>
                            <?php if (!empty($detail['errors'])): ?>
                                <span style="color: red;">✗ <?= implode(', ', $detail['errors']) ?></span>
                            <?php endif; ?>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </details>
                <?php endif; ?>
                
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>
            
            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="card shadow-sm">
                        <div class="card-body text-center">
                            <h3 class="text-primary"><?= $totalProducts ?></h3>
                            <p class="text-muted mb-0">Total Products</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card shadow-sm">
                        <div class="card-body text-center">
                            <h3 class="text-success"><?= $productsWithImages ?></h3>
                            <p class="text-muted mb-0">With Images</p>
                            <small class="text-muted"><?= round(($productsWithImages / $totalProducts) * 100) ?>%</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card shadow-sm">
                        <div class="card-body text-center">
                            <h3 class="text-info"><?= $productsWithDesc ?></h3>
                            <p class="text-muted mb-0">With Descriptions</p>
                            <small class="text-muted"><?= round(($productsWithDesc / $totalProducts) * 100) ?>%</small>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Main Action Form -->
            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <h5 class="card-title">🚀 Batch Update All Products</h5>
                    <p class="text-muted">Automatically fetch real product images and enhance descriptions using AI.</p>
                    
                    <form method="POST" class="mt-4">
                        <input type="hidden" name="action" value="update_all">
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Number of Products to Process:</label>
                                <input type="number" name="limit" class="form-control" value="20" min="1" max="500">
                                <small class="text-muted">Start with 20 for testing, then process more.</small>
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label">Options:</label>
                                <div class="form-check">
                                    <input type="checkbox" name="download_images" class="form-check-input" id="download_images" checked>
                                    <label class="form-check-label" for="download_images">
                                        Download real product images from Google
                                    </label>
                                </div>
                                <small class="text-muted">Images will be saved to assets/images/products/</small>
                            </div>
                        </div>
                        
                        <div class="alert alert-warning">
                            <strong>⚠️ Important Notes:</strong>
                            <ul class="mb-0 mt-2">
                                <li>Google Custom Search API has a free limit of 100 searches/day</li>
                                <li>Processing takes ~2-3 seconds per product</li>
                                <li>This will overwrite existing images and descriptions</li>
                                <li>Recommended to backup database first</li>
                            </ul>
                        </div>
                        
                        <button type="submit" class="btn btn-primary btn-lg" onclick="return confirm('This will update product images and descriptions. Continue?')">
                            🚀 Start Auto-Update (<?= $totalProducts ?> products)
                        </button>
                    </form>
                </div>
            </div>
            
            <!-- Single Product Update -->
            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <h5 class="card-title">📦 Update Single Product</h5>
                    
                    <form method="POST" class="mt-4">
                        <input type="hidden" name="action" value="update_single">
                        
                        <div class="row mb-3">
                            <div class="col-md-8">
                                <label class="form-label">Select Product:</label>
                                <select name="product_id" class="form-select" required>
                                    <option value="">-- Choose a product --</option>
                                    <?php
                                    $products = $conn->query("SELECT id, name, brand, image FROM products ORDER BY name");
                                    while ($p = $products->fetch_assoc()):
                                    ?>
                                    <option value="<?= $p['id'] ?>">
                                        <?= htmlspecialchars($p['brand'] . ' - ' . $p['name']) ?>
                                        <?= $p['image'] ? ' [Has Image]' : ' [No Image]' ?>
                                    </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-4">
                                <label class="form-label">Options:</label>
                                <div class="form-check">
                                    <input type="checkbox" name="download_images" class="form-check-input" checked>
                                    <label class="form-check-label">Download Image</label>
                                </div>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-success">
                            🔄 Update This Product
                        </button>
                    </form>
                </div>
            </div>
            
            <!-- How It Works -->
            <div class="card shadow-sm">
                <div class="card-body">
                    <h5 class="card-title">ℹ️ How It Works</h5>
                    <ol>
                        <li><strong>Searches Google:</strong> Uses your Google Custom Search API to find official product photos</li>
                        <li><strong>Downloads Image:</strong> Saves high-quality image to assets/images/products/</li>
                        <li><strong>Enhances Description:</strong> Uses AI (Gemini) or templates to create detailed descriptions</li>
                        <li><strong>Updates Database:</strong> Automatically updates product record</li>
                    </ol>
                    
                    <h6 class="mt-4">💡 Tips:</h6>
                    <ul>
                        <li>Process in batches of 20-50 to monitor quality</li>
                        <li>Review results before processing all products</li>
                        <li>API is FREE for up to 100 searches/day</li>
                        <li>You can run this multiple times to update different products</li>
                    </ul>
                </div>
            </div>
            
        </div>
    </div>
</div>

<?php require_once 'includes/admin_footer.php'; ?>
