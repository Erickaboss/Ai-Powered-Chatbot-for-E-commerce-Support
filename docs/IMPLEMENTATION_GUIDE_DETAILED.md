# Detailed Implementation Guide

## Quick Start

This guide provides step-by-step instructions for implementing all enhancements to the multilingual e-commerce chatbot.

---

## Phase 1: Database Setup (Week 1)

### Step 1.1: Apply Database Migrations

```bash
# Connect to MySQL
mysql -u root -p ecommerce_chatbot < database_enhancements.sql
```

**What this does:**
- Creates 13 new tables for uploads, metrics, and tracking
- Creates views for analytics
- Creates stored procedures for maintenance
- Creates triggers for automatic updates
- Adds indexes for performance

**Verify:**
```sql
SHOW TABLES LIKE '%upload%';
SHOW TABLES LIKE '%metric%';
SHOW TABLES LIKE '%gemini%';
```

### Step 1.2: Configure Environment Variables

Create/update `.env`:
```bash
# Google Gemini API
GEMINI_API_KEY=your_api_key_here
GEMINI_VISION_MODEL=gemini-2.0-flash

# File Upload Configuration
UPLOAD_MAX_SIZE=10485760  # 10MB
UPLOAD_TEMP_DIR=/tmp/chatbot_uploads
UPLOAD_STORAGE_DIR=/var/storage/chatbot_uploads

# ML Configuration
ML_MODEL_PATH=/var/models/chatbot
CACHE_TTL=3600

# Metrics
METRICS_RETENTION_DAYS=90
```

### Step 1.3: Create Upload Directories

```bash
# Create directories with proper permissions
mkdir -p /tmp/chatbot_uploads
mkdir -p /var/storage/chatbot_uploads
chmod 755 /tmp/chatbot_uploads
chmod 755 /var/storage/chatbot_uploads

# Create web-accessible symlink (optional)
ln -s /var/storage/chatbot_uploads /var/www/html/uploads
```

---

## Phase 2: API Implementation (Week 1-2)

### Step 2.1: Deploy Upload API

**File:** `api/upload.php`

```bash
# Copy file to API directory
cp api/upload.php /var/www/html/api/

# Set permissions
chmod 644 /var/www/html/api/upload.php
```

**Test the endpoint:**
```bash
curl -X POST \
  -F "file=@test_image.jpg" \
  -F "session_id=test_session_123" \
  http://localhost/api/upload.php
```

**Expected response:**
```json
{
  "status": "success",
  "upload_id": 1,
  "file_name": "test_image.jpg",
  "file_type": "image",
  "file_size": 102400,
  "web_path": "/uploads/chat_1234567890_abc123.jpg",
  "message": "File uploaded successfully. Processing..."
}
```

### Step 2.2: Deploy Gemini Vision Processor

**File:** `api/gemini_vision_processor.php`

```bash
cp api/gemini_vision_processor.php /var/www/html/api/
chmod 644 /var/www/html/api/gemini_vision_processor.php
```

**Test the processor:**
```php
<?php
require_once 'api/gemini_vision_processor.php';

$processor = new GeminiVisionProcessor(getenv('GEMINI_API_KEY'));
$analysis = $processor->analyzeProductImage('/path/to/image.jpg');
print_r($analysis);
?>
```

### Step 2.3: Deploy Product Matcher

**File:** `api/product_matcher.php`

```bash
cp api/product_matcher.php /var/www/html/api/
chmod 644 /var/www/html/api/product_matcher.php
```

**Test the matcher:**
```php
<?php
require_once 'api/product_matcher.php';
require_once 'includes/db.php';

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
$matcher = new ProductMatcher($conn);

$analysis = [
    'product_type' => 'smartphone',
    'brand' => 'Samsung',
    'model' => 'Galaxy S21',
    'confidence' => 0.95
];

$matches = $matcher->findMatches($analysis);
print_r($matches);
?>
```

### Step 2.4: Create Processing Queue Handler

**File:** `api/process_upload_queue.php`

```php
<?php
/**
 * Background job to process upload queue
 * Run via cron: */5 * * * * php /var/www/html/api/process_upload_queue.php
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/gemini_vision_processor.php';
require_once __DIR__ . '/product_matcher.php';

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get pending uploads
$stmt = $conn->prepare("
    SELECT u.id, u.storage_path, u.file_type
    FROM chat_uploads u
    JOIN upload_processing_queue q ON u.id = q.upload_id
    WHERE q.status = 'pending'
    LIMIT 10
");
$stmt->execute();
$result = $stmt->get_result();

$apiKey = getenv('GEMINI_API_KEY');
$processor = new GeminiVisionProcessor($apiKey, $conn);
$matcher = new ProductMatcher($conn);

while ($upload = $result->fetch_assoc()) {
    try {
        // Process upload
        $analysis = processUploadFromQueue($upload['id'], $conn, $apiKey);
        
        // Find matches
        if ($upload['file_type'] === 'image') {
            $matches = $matcher->findMatches($analysis, 10);
            $matcher->saveMatches($upload['id'], $matches);
        }
        
        echo "✓ Processed upload {$upload['id']}\n";
    } catch (Exception $e) {
        echo "✗ Error processing upload {$upload['id']}: " . $e->getMessage() . "\n";
    }
}

$conn->close();
?>
```

### Step 2.5: Setup Cron Job

```bash
# Edit crontab
crontab -e

# Add this line to process uploads every 5 minutes
*/5 * * * * php /var/www/html/api/process_upload_queue.php >> /var/log/chatbot_queue.log 2>&1

# Add this line to cleanup expired uploads daily
0 2 * * * php /var/www/html/api/cleanup_uploads.php >> /var/log/chatbot_cleanup.log 2>&1
```

---

## Phase 3: Frontend Integration (Week 2)

### Step 3.1: Deploy Upload Handler

**File:** `assets/js/upload-handler.js`

```bash
cp assets/js/upload-handler.js /var/www/html/assets/js/
chmod 644 /var/www/html/assets/js/upload-handler.js
```

### Step 3.2: Integrate with Chatbot Widget

**Modify:** `assets/js/chatbot.js`

```javascript
// Add at the top of the file
let uploadHandler = null;

// Initialize upload handler when chatbot loads
function initializeChatbot() {
    // ... existing code ...
    
    // Initialize upload handler
    uploadHandler = new ChatbotUploadHandler({
        apiUrl: '/api/upload.php',
        onUploadStart: () => {
            updateTyping('Uploading file...');
        },
        onUploadProgress: (percent) => {
            updateTyping(`Uploading... ${Math.round(percent)}%`);
        },
        onUploadComplete: (response) => {
            handleUploadComplete(response);
        },
        onUploadError: (error) => {
            appendMessage(`Upload failed: ${error.message}`, 'bot');
        }
    });
    
    uploadHandler.initialize('upload-zone');
}

// Handle upload completion
function handleUploadComplete(response) {
    if (response.status === 'success') {
        // Show upload confirmation
        appendMessage(`📎 Uploaded: ${response.file_name}`, 'user');
        
        // Show processing message
        appendMessage('🔍 Analyzing image...', 'bot');
        
        // Poll for results
        pollUploadResults(response.upload_id);
    }
}

// Poll for upload processing results
function pollUploadResults(uploadId, maxAttempts = 30) {
    let attempts = 0;
    
    const pollInterval = setInterval(async () => {
        attempts++;
        
        try {
            const response = await fetch('/api/get_upload_results.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ upload_id: uploadId })
            });
            
            const data = await response.json();
            
            if (data.status === 'completed') {
                clearInterval(pollInterval);
                displayUploadResults(data);
            } else if (data.status === 'failed') {
                clearInterval(pollInterval);
                appendMessage(`❌ Processing failed: ${data.error}`, 'bot');
            }
        } catch (error) {
            console.error('Poll error:', error);
        }
        
        if (attempts >= maxAttempts) {
            clearInterval(pollInterval);
            appendMessage('⏱️ Processing timeout. Please try again.', 'bot');
        }
    }, 1000);
}

// Display upload results
function displayUploadResults(data) {
    if (data.matches && data.matches.length > 0) {
        appendMessage('✅ Found matching products!', 'bot');
        
        // Format and display matches
        const matchesHtml = formatProductMatches(data.matches);
        appendMessage(matchesHtml, 'bot', { html: true });
    } else {
        appendMessage('No matching products found. Try uploading a different image.', 'bot');
    }
}

// Format product matches for display
function formatProductMatches(matches) {
    let html = '<div class="product-matches">';
    
    matches.forEach(match => {
        html += `
            <div class="product-match-card">
                <img src="/assets/images/products/${match.image}" alt="${match.name}">
                <h4>${match.name}</h4>
                <p class="price">RWF ${match.price.toLocaleString()}</p>
                <p class="match-score">Match: ${match.match_score}%</p>
                <p class="match-reason">${match.match_reason}</p>
                <button onclick="addToCart(${match.product_id})">Add to Cart</button>
            </div>
        `;
    });
    
    html += '</div>';
    return html;
}
```

### Step 3.3: Add Upload Button to Chat Widget

**Modify:** `assets/js/chatbot.js` (chat UI section)

```javascript
// Add upload button to chat input area
function createChatInputArea() {
    return `
        <div class="chat-input-area">
            <div class="input-controls">
                <button id="upload-btn" class="chat-btn upload-btn" title="Upload image or document">
                    <i class="bi bi-cloud-arrow-up"></i>
                </button>
                <input type="text" id="chat-input" placeholder="Type your message..." class="chat-input">
                <button id="send-btn" class="chat-btn send-btn">
                    <i class="bi bi-send"></i>
                </button>
            </div>
            <div id="upload-zone" class="upload-zone-container" style="display: none;"></div>
        </div>
    `;
}

// Toggle upload zone
document.addEventListener('click', function(e) {
    if (e.target.closest('#upload-btn')) {
        const uploadZone = document.getElementById('upload-zone');
        uploadZone.style.display = uploadZone.style.display === 'none' ? 'block' : 'none';
    }
});
```

### Step 3.4: Add CSS Styles

**File:** `assets/css/upload.css`

```css
.upload-zone-container {
    margin: 10px 0;
    padding: 10px;
    background-color: #f9f9f9;
    border-radius: 8px;
}

.upload-zone {
    border: 2px dashed #ccc;
    border-radius: 8px;
    padding: 30px 20px;
    text-align: center;
    cursor: pointer;
    transition: all 0.3s ease;
}

.upload-zone:hover {
    border-color: #0f3460;
    background-color: #f0f5ff;
}

.upload-zone.drag-over {
    border-color: #0f3460;
    background-color: #e8f1ff;
    box-shadow: 0 0 10px rgba(15, 52, 96, 0.2);
}

.product-matches {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
    gap: 10px;
    margin: 10px 0;
}

.product-match-card {
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 10px;
    text-align: center;
    background: white;
}

.product-match-card img {
    width: 100%;
    height: 120px;
    object-fit: cover;
    border-radius: 4px;
    margin-bottom: 8px;
}

.product-match-card h4 {
    font-size: 12px;
    margin: 5px 0;
    color: #333;
}

.product-match-card .price {
    font-weight: bold;
    color: #0f3460;
    margin: 5px 0;
}

.product-match-card .match-score {
    font-size: 11px;
    color: #666;
    margin: 3px 0;
}

.product-match-card .match-reason {
    font-size: 10px;
    color: #999;
    margin: 3px 0;
}

.product-match-card button {
    width: 100%;
    padding: 6px;
    background-color: #0f3460;
    color: white;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-size: 11px;
    margin-top: 8px;
}

.product-match-card button:hover {
    background-color: #1f8a70;
}
```

---

## Phase 4: ML Training Enhancement (Week 3)

### Step 4.1: Generate Comprehensive Dataset

```bash
cd chatbot-ml

# Install dependencies
pip install mysql-connector-python

# Generate comprehensive dataset
python3 build_comprehensive_dataset.py
```

**Output:**
```
✓ Database connected
✓ Loaded 15 categories
✓ Loaded 1161 products
✓ Generated 450 product search intents
✓ Generated 1500 budget query intents
✓ Generated 300 price inquiry intents
✓ Generated 1200 complex query intents
✓ Saved 3450 intents to chatbot-ml/dataset/intents_comprehensive.json

==================================================
DATASET STATISTICS
==================================================
Total Intents: 3450
Total Patterns: 15000+
By Intent Type:
  - product_search: 450
  - budget_query: 1500
  - price_inquiry: 300
  - complex_query: 1200
By Language:
  - en: 1500
  - fr: 1000
  - kin: 950
==================================================
```

### Step 4.2: Merge Datasets

```bash
# Merge all intent files
python3 merge_intents.py \
    dataset/intents.json \
    dataset/intents_part2.json \
    dataset/intents_comprehensive.json \
    -o dataset/intents_merged_final.json
```

### Step 4.3: Train Enhanced Models

```bash
# Train with comprehensive dataset
python3 train_production.py \
    --dataset dataset/intents_merged_final.json \
    --output models/production \
    --models logistic_regression random_forest svm mlp \
    --cv-folds 5 \
    --test-size 0.2
```

**Expected output:**
```
Loading dataset...
✓ Loaded 5000+ training samples
✓ 50+ intent classes
✓ 3 languages

Preprocessing...
✓ Tokenization complete
✓ Vectorization complete (8000 features)

Training models...
✓ Logistic Regression: 92.5% accuracy
✓ Random Forest: 94.2% accuracy
✓ SVM (RBF): 93.8% accuracy
✓ MLP Neural Network: 95.1% accuracy

Cross-validation results:
✓ LR: 91.8% ± 1.2%
✓ RF: 93.5% ± 1.5%
✓ SVM: 92.9% ± 1.3%
✓ MLP: 94.2% ± 1.1%

Generating visualizations...
✓ Confusion matrices saved
✓ Performance charts saved
✓ Training report saved

✓ Training complete! Best model: MLP (95.1%)
```

### Step 4.4: Deploy Trained Models

```bash
# Copy models to production
cp models/production/* /var/models/chatbot/

# Update Flask API
systemctl restart chatbot-ml-api
```

---

## Phase 5: Dashboard Implementation (Week 4)

### Step 5.1: Create Enhanced Dashboard

**File:** `admin/ml_performance_enhanced.php`

```bash
cp admin/ml_performance_enhanced.php /var/www/html/admin/
chmod 644 /var/www/html/admin/ml_performance_enhanced.php
```

### Step 5.2: Add Metrics Collection

**File:** `includes/metrics_collector.php`

```php
<?php
/**
 * Metrics Collector
 * Collects performance metrics during chatbot operation
 */

class MetricsCollector {
    private $conn;
    
    public function __construct($conn) {
        $this->conn = $conn;
    }
    
    /**
     * Record prediction metric
     */
    public function recordPrediction($intentTag, $correct, $confidence) {
        $stmt = $this->conn->prepare("
            INSERT INTO prediction_metrics (intent_tag, total_predictions, correct_predictions, avg_confidence)
            VALUES (?, 1, ?, ?)
            ON DUPLICATE KEY UPDATE
                total_predictions = total_predictions + 1,
                correct_predictions = correct_predictions + ?,
                avg_confidence = (avg_confidence + ?) / 2
        ");
        
        $stmt->bind_param('sddd', $intentTag, $correct, $confidence, $correct, $confidence);
        $stmt->execute();
    }
    
    /**
     * Record user satisfaction
     */
    public function recordSatisfaction($logId, $rating, $helpful, $accurate, $responseTime) {
        $stmt = $this->conn->prepare("
            INSERT INTO user_satisfaction_metrics (log_id, user_rating, helpful, accurate, response_time_ms)
            VALUES (?, ?, ?, ?, ?)
        ");
        
        $stmt->bind_param('iiiii', $logId, $rating, $helpful, $accurate, $responseTime);
        $stmt->execute();
    }
}
?>
```

### Step 5.3: Integrate Metrics into Chatbot

**Modify:** `api/chatbot.php`

```php
// Add at the top
require_once __DIR__ . '/../includes/metrics_collector.php';
$metricsCollector = new MetricsCollector($conn);

// After processing message
$startTime = microtime(true);
$response = processMessage($msg, $uid, $conn, $ctx, $session_id);
$responseTime = (int)((microtime(true) - $startTime) * 1000);

// Record metrics
if (isset($response['intent'])) {
    $metricsCollector->recordPrediction(
        $response['intent'],
        $response['confidence'] > 0.8 ? 1 : 0,
        $response['confidence']
    );
}

// Log response time
$stmt = $conn->prepare("UPDATE chatbot_logs SET response_time_ms = ? WHERE id = ?");
$stmt->bind_param('ii', $responseTime, $logId);
$stmt->execute();
```

---

## Phase 6: Testing & Validation (Week 5)

### Step 6.1: Unit Tests

**File:** `tests/test_upload.php`

```php
<?php
require_once __DIR__ . '/../api/upload.php';

class UploadTest {
    public function testFileValidation() {
        $file = [
            'name' => 'test.jpg',
            'type' => 'image/jpeg',
            'size' => 1024,
            'tmp_name' => '/tmp/test.jpg'
        ];
        
        $errors = validateUploadFile($file);
        assert(empty($errors), 'File validation failed');
        echo "✓ File validation test passed\n";
    }
    
    public function testInvalidFileType() {
        $file = [
            'name' => 'test.exe',
            'type' => 'application/x-msdownload',
            'size' => 1024,
            'tmp_name' => '/tmp/test.exe'
        ];
        
        $errors = validateUploadFile($file);
        assert(!empty($errors), 'Should reject invalid file type');
        echo "✓ Invalid file type test passed\n";
    }
}

$test = new UploadTest();
$test->testFileValidation();
$test->testInvalidFileType();
?>
```

### Step 6.2: Integration Tests

```bash
# Test upload endpoint
curl -X POST \
  -F "file=@test_image.jpg" \
  -F "session_id=test_123" \
  http://localhost/api/upload.php

# Test product matching
curl -X POST \
  -H "Content-Type: application/json" \
  -d '{"upload_id": 1}' \
  http://localhost/api/get_upload_results.php

# Test dashboard
curl http://localhost/admin/ml_performance_enhanced.php
```

### Step 6.3: Performance Tests

```bash
# Test upload speed
time curl -X POST \
  -F "file=@large_image.jpg" \
  -F "session_id=test_123" \
  http://localhost/api/upload.php

# Test dashboard load time
time curl http://localhost/admin/ml_performance_enhanced.php > /dev/null
```

---

## Phase 7: Deployment (Week 6)

### Step 7.1: Pre-Deployment Checklist

- [ ] Database migrations applied
- [ ] All API endpoints tested
- [ ] Frontend components tested
- [ ] ML models trained and evaluated
- [ ] Dashboard metrics verified
- [ ] Security audit completed
- [ ] Performance benchmarks met
- [ ] Documentation updated

### Step 7.2: Backup Database

```bash
mysqldump -u root -p ecommerce_chatbot > backup_$(date +%Y%m%d_%H%M%S).sql
```

### Step 7.3: Deploy to Production

```bash
# Copy all files
cp -r api/* /var/www/html/api/
cp -r assets/js/upload-handler.js /var/www/html/assets/js/
cp -r admin/ml_performance_enhanced.php /var/www/html/admin/
cp -r includes/metrics_collector.php /var/www/html/includes/

# Set permissions
chmod 755 /var/www/html/api
chmod 644 /var/www/html/api/*.php
chmod 755 /var/www/html/admin
chmod 644 /var/www/html/admin/*.php

# Restart services
systemctl restart apache2
systemctl restart chatbot-ml-api
```

### Step 7.4: Verify Deployment

```bash
# Check API endpoints
curl http://localhost/api/upload.php

# Check dashboard
curl http://localhost/admin/ml_performance_enhanced.php

# Check logs
tail -f /var/log/apache2/error.log
tail -f /var/log/chatbot_queue.log
```

---

## Troubleshooting

### Upload fails with "Permission denied"

```bash
# Fix directory permissions
chmod 777 /tmp/chatbot_uploads
chmod 777 /var/storage/chatbot_uploads
```

### Gemini API returns 401 error

```bash
# Verify API key
echo $GEMINI_API_KEY

# Test API key
curl -X POST \
  -H "Content-Type: application/json" \
  -d '{"contents":[{"parts":[{"text":"test"}]}]}' \
  "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=$GEMINI_API_KEY"
```

### Dashboard shows no metrics

```bash
# Check if metrics are being recorded
mysql -u root -p ecommerce_chatbot -e "SELECT COUNT(*) FROM prediction_metrics;"

# Check if chatbot is logging
mysql -u root -p ecommerce_chatbot -e "SELECT COUNT(*) FROM chatbot_logs WHERE created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR);"
```

### ML models not loading

```bash
# Check model files
ls -la /var/models/chatbot/

# Check Flask API status
systemctl status chatbot-ml-api

# Check Flask logs
journalctl -u chatbot-ml-api -f
```

---

## Maintenance

### Daily Tasks

```bash
# Monitor error logs
tail -f /var/log/apache2/error.log

# Check upload queue
mysql -u root -p ecommerce_chatbot -e "SELECT COUNT(*) FROM upload_processing_queue WHERE status = 'pending';"
```

### Weekly Tasks

```bash
# Cleanup expired uploads
php /var/www/html/api/cleanup_uploads.php

# Review metrics
mysql -u root -p ecommerce_chatbot -e "SELECT * FROM v_user_satisfaction_summary LIMIT 7;"
```

### Monthly Tasks

```bash
# Retrain models
cd /var/www/html/chatbot-ml
python3 train_production.py

# Archive old logs
gzip /var/log/chatbot_*.log
```

---

## Support & Documentation

- **API Documentation**: See `API_DOCUMENTATION.md`
- **ML Training Guide**: See `chatbot-ml/TRAINING_GUIDE.md`
- **Architecture**: See `ARCHITECTURE.md`
- **Troubleshooting**: See `TROUBLESHOOTING.md`

---

**Last Updated**: 2026  
**Version**: 1.0
