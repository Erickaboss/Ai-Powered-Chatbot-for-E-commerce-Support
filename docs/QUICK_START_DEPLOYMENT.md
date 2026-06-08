# Quick Start: Deploy Advanced Chatbot Features

## 5-Minute Setup

### 1. Apply Database Migrations

```bash
# Navigate to project root
cd /var/www/html

# Apply migrations via PHP
php api/apply_migrations.php

# Or via MySQL directly
mysql -u root ecommerce_chatbot < database_enhancements.sql
```

### 2. Set Environment Variables

Create `.env` file in project root:
```bash
GEMINI_API_KEY=your_api_key_here
GEMINI_VISION_MODEL=gemini-2.0-flash
UPLOAD_MAX_SIZE=10485760
UPLOAD_TEMP_DIR=/tmp/chatbot_uploads
UPLOAD_STORAGE_DIR=/var/storage/chatbot_uploads
```

### 3. Create Upload Directories

```bash
mkdir -p /tmp/chatbot_uploads
mkdir -p /var/storage/chatbot_uploads
chmod 755 /tmp/chatbot_uploads
chmod 755 /var/storage/chatbot_uploads
```

### 4. Verify Files Are in Place

```bash
# Check API files
ls -la api/upload.php
ls -la api/gemini_vision_processor.php
ls -la api/product_matcher.php
ls -la api/get_upload_results.php
ls -la api/process_upload_queue.php

# Check frontend files
ls -la assets/js/upload-handler.js
ls -la assets/css/upload.css

# Check admin dashboard
ls -la admin/ml_performance_enhanced.php

# Check includes
ls -la includes/metrics_collector.php
```

### 5. Test Upload API

```bash
# Create a test image
echo "test" > /tmp/test.jpg

# Test upload
curl -X POST \
  -F "file=@/tmp/test.jpg" \
  -F "session_id=test_session" \
  http://localhost/api/upload.php
```

### 6. Access Dashboard

Open in browser:
```
http://localhost/admin/ml_performance_enhanced.php
```

## Integration with Chatbot (10 minutes)

### 1. Update `assets/js/chatbot.js`

Add at the top of the file:
```javascript
// Initialize upload handler
let uploadHandler = null;

function initializeUploadHandler() {
    uploadHandler = new ChatbotUploadHandler({
        apiUrl: '/api/upload.php',
        onUploadStart: () => {
            appendMessage('📤 Uploading file...', 'bot');
        },
        onUploadProgress: (percent) => {
            console.log(`Upload progress: ${percent}%`);
        },
        onUploadComplete: (response) => {
            handleUploadComplete(response);
        },
        onUploadError: (error) => {
            appendMessage(`❌ Upload failed: ${error.message}`, 'bot');
        }
    });
}

function handleUploadComplete(response) {
    if (response.status === 'success') {
        appendMessage(`📎 File uploaded: ${response.file_name}`, 'user');
        appendMessage('🔍 Analyzing image...', 'bot');
        
        // Poll for results
        pollUploadResults(response.upload_id);
    }
}

function pollUploadResults(uploadId, attempts = 0) {
    if (attempts > 30) {
        appendMessage('⏱️ Processing timeout', 'bot');
        return;
    }
    
    setTimeout(() => {
        fetch('/api/get_upload_results.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ upload_id: uploadId })
        })
        .then(r => r.json())
        .then(data => {
            if (data.status === 'completed') {
                displayUploadResults(data);
            } else if (data.status === 'failed') {
                appendMessage(`❌ ${data.error}`, 'bot');
            } else {
                pollUploadResults(uploadId, attempts + 1);
            }
        })
        .catch(err => appendMessage(`Error: ${err.message}`, 'bot'));
    }, 1000);
}

function displayUploadResults(data) {
    if (data.matches && data.matches.length > 0) {
        appendMessage(`✅ Found ${data.matches.length} matching products!`, 'bot');
        
        let html = '<div class="product-matches">';
        data.matches.forEach(match => {
            html += `
                <div class="product-match-card">
                    <img src="/assets/images/products/${match.image}" alt="${match.name}">
                    <h4>${match.name}</h4>
                    <p class="price">RWF ${match.price.toLocaleString()}</p>
                    <p class="match-score">Match: ${match.match_score}%</p>
                    <button onclick="addToCart(${match.product_id})">Add to Cart</button>
                </div>
            `;
        });
        html += '</div>';
        
        appendMessage(html, 'bot', { html: true });
    } else {
        appendMessage('No matching products found', 'bot');
    }
}
```

### 2. Add Upload Button to Chat UI

Find the chat input area in `assets/js/chatbot.js` and add:
```html
<button id="upload-btn" class="chat-btn upload-btn" title="Upload image">
    📁
</button>
```

### 3. Include Upload Styles

Add to the HTML head:
```html
<link rel="stylesheet" href="/assets/css/upload.css">
```

### 4. Include Upload Handler Script

Add before closing body tag:
```html
<script src="/assets/js/upload-handler.js"></script>
```

### 5. Initialize Upload Handler

In the chatbot initialization function, add:
```javascript
initializeUploadHandler();
```

## Generate ML Dataset (5 minutes)

```bash
cd chatbot-ml

# Install dependencies
pip install mysql-connector-python

# Generate comprehensive dataset
python3 build_comprehensive_dataset.py

# Output: intents_comprehensive.json with 5,000+ training samples
```

## Train ML Models (15-30 minutes)

```bash
cd chatbot-ml

# Train models with comprehensive dataset
python3 train_production.py \
    --dataset dataset/intents_comprehensive.json \
    --output models/production \
    --models logistic_regression random_forest svm mlp \
    --cv-folds 5 \
    --test-size 0.2
```

## Setup Cron Jobs (2 minutes)

```bash
# Edit crontab
crontab -e

# Add these lines:
*/5 * * * * php /var/www/html/api/process_upload_queue.php >> /var/log/chatbot_queue.log 2>&1
0 2 * * * php /var/www/html/api/cleanup_uploads.php >> /var/log/chatbot_cleanup.log 2>&1
```

## Verify Everything Works

### 1. Test Upload

```bash
curl -X POST \
  -F "file=@test_image.jpg" \
  -F "session_id=test_123" \
  http://localhost/api/upload.php
```

Expected response:
```json
{
  "status": "success",
  "upload_id": 1,
  "file_name": "test_image.jpg",
  "message": "File uploaded successfully. Processing..."
}
```

### 2. Check Dashboard

Open: `http://localhost/admin/ml_performance_enhanced.php`

Should show:
- Model performance metrics
- Accuracy, Precision, Recall, F1-Score
- Intent classification accuracy
- User satisfaction trends

### 3. Check Database

```bash
mysql -u root ecommerce_chatbot -e "SHOW TABLES LIKE '%upload%';"
```

Should show:
- chat_uploads
- upload_processing_queue
- product_image_matches

### 4. Check Logs

```bash
tail -f /var/log/chatbot_queue.log
```

Should show processing messages

## Troubleshooting

### Upload fails

```bash
# Check permissions
ls -la /tmp/chatbot_uploads
ls -la /var/storage/chatbot_uploads

# Fix if needed
chmod 777 /tmp/chatbot_uploads
chmod 777 /var/storage/chatbot_uploads
```

### Gemini API error

```bash
# Verify API key
echo $GEMINI_API_KEY

# Test API key
curl -X POST \
  -H "Content-Type: application/json" \
  -d '{"contents":[{"parts":[{"text":"test"}]}]}' \
  "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=$GEMINI_API_KEY"
```

### Dashboard shows no data

```bash
# Check if tables exist
mysql -u root ecommerce_chatbot -e "SELECT COUNT(*) FROM model_performance_history;"

# Check if data is being recorded
mysql -u root ecommerce_chatbot -e "SELECT COUNT(*) FROM prediction_metrics;"
```

## What's Now Available

✅ **Image Upload** - Users can upload product photos  
✅ **Product Matching** - AI finds matching products in store  
✅ **ML Dashboard** - Real-time performance metrics  
✅ **Comprehensive Dataset** - 5,000+ training samples  
✅ **Multilingual Support** - English, French, Kinyarwanda  
✅ **Budget Recommendations** - Filter by price range  
✅ **Complex Queries** - Gemini API for advanced questions  
✅ **Professional Responses** - Formatted product cards  
✅ **Metrics Tracking** - User satisfaction & performance  
✅ **Background Processing** - Async upload handling  

## Next Steps

1. ✅ Deploy all files
2. ✅ Apply database migrations
3. ✅ Configure environment
4. ✅ Test upload API
5. ✅ Integrate with chatbot
6. ✅ Generate ML dataset
7. ✅ Train models
8. ✅ Setup cron jobs
9. ✅ Monitor performance
10. ✅ Gather user feedback

## Support

- **Setup Issues**: See `IMPLEMENTATION_GUIDE_DETAILED.md`
- **Architecture**: See `COMPREHENSIVE_ENHANCEMENT_PLAN.md`
- **Full Details**: See `IMPLEMENTATION_COMPLETE.md`

---

**Total Setup Time**: ~30-45 minutes  
**Status**: Ready for Production  
**Version**: 1.0
