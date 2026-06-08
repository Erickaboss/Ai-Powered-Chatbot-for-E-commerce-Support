# System Integration & Deployment Guide

## 🏗️ System Architecture Visualization

```
╔══════════════════════════════════════════════════════════════════════════╗
║                         E-COMMERCE CHATBOT SYSTEM                        ║
╚══════════════════════════════════════════════════════════════════════════╝

┌─────────────────────────────────────────────────────────────────────────┐
│                         FRONTEND LAYER (Client)                          │
│                                                                           │
│  Browser/App                                                              │
│  ├─ Chat Widget (JavaScript)                                             │
│  ├─ Product Pages                                                        │
│  ├─ Order Tracking                                                       │
│  └─ User Account                                                         │
│                                                                           │
│  [User types] → [JavaScript sends] → [API request]                       │
└─────────────────┬───────────────────────────────────────────────────────┘
                  │ HTTP POST /api/chatbot.php
                  │ {"message": "what do you have for 75k"}
                  ↓
┌─────────────────────────────────────────────────────────────────────────┐
│                       PHP APPLICATION LAYER                              │
│                      (Apache/XAMPP on port 80)                          │
│                                                                           │
│  api/chatbot.php (3,700+ lines)                                          │
│  ├─ Input validation & sanitization                                      │
│  ├─ Language detection                                                   │
│  ├─ Intent pattern matching (25+ patterns)                               │
│  ├─ Parameter extraction:                                                │
│  │  ├─ parseBudgetAmount() → 75000                                       │
│  │  ├─ detectCategory() → category_id                                    │
│  │  └─ extractKeywords() → search terms                                  │
│  ├─ Routing logic (decision tree)                                        │
│  └─ Response formatting                                                  │
│                                                                           │
│  Supporting files:                                                       │
│  ├─ config/db.php (MySQL connection)                                     │
│  ├─ includes/*.php (helper functions)                                    │
│  ├─ includes/chatbot_gemini_gate.php (Gemini integration)               │
│  └─ includes/mailer.php (email notifications)                            │
│                                                                           │
└─────────────────┬───────────────────────────────────────────────────────┘
                  │
         ┌────────┴────────┬──────────────┐
         │                 │              │
    [DB Path]      [ML Path]       [Gemini Path]
    (98%)          (1%)            (<1%)
         │                 │              │
         ↓                 ↓              ↓
    ┌─────────┐   ┌──────────┐   ┌─────────────┐
    │ MySQL   │   │  Flask   │   │   Google    │
    │ Database│   │  ML API  │   │   Gemini    │
    │ Port:   │   │ Port:    │   │   Cloud API │
    │ 3306    │   │ 5000     │   │             │
    └─────────┘   └──────────┘   └─────────────┘
         │                 │              │
         └────────┬────────┴──────────────┘
                  │
                  ↓
        ┌──────────────────┐
        │  Response JSON   │
        │  {               │
        │    "response":   │
        │      "Products..",
        │    "quick_replies"
        │      ["Show more"]
        │  }               │
        └────────┬─────────┘
                 │ HTTP 200
                 ↓
        ┌─────────────────────────────────────────────────────────────┐
        │             FRONTEND RECEIVES & RENDERS                     │
        │                                                             │
        │   ✅ Products matching your search under RWF 75,000        │
        │                                                             │
        │   • Indomie Instant Noodles 70g                            │
        │     RWF 1,200 ✅ In Stock                                   │
        │     [View Details] [Add to Cart]                           │
        │                                                             │
        │   [Show more] [Change budget] [Browse all]                 │
        └─────────────────────────────────────────────────────────────┘
```

---

## 🔌 Component Interactions

### 1. Frontend → PHP (Request)
```javascript
// assets/js/chatbot-widget.js
fetch('/api/chatbot.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({
        message: "what do you have for 75k",
        session_id: "abc123...",
        image: null  // optional
    })
})
.then(response => response.json())
.then(data => {
    // Render response
    displayChatMessage(data.response);
    displayQuickReplies(data.quick_replies);
});
```

### 2. PHP → MySQL (Query)
```php
// api/chatbot.php
$sql = "SELECT p.id, p.name, p.brand, p.price, p.stock, p.description
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE p.stock > 0 AND p.price <= $budget
        ORDER BY p.price DESC LIMIT 8";

$result = $conn->query($sql);
while ($row = $result->fetch_assoc()) {
    $products[] = $row;
}
```

### 3. PHP → Flask (ML Fallback)
```php
// When database returns no results
$mlPayload = json_encode([
    'message' => $msg,
    'model' => 'best',
    'context' => []
]);

$ch = curl_init('http://localhost:5000/predict');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $mlPayload,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    CURLOPT_TIMEOUT => 5
]);

$mlResponse = json_decode(curl_exec($ch), true);
```

### 4. PHP → Gemini (Complex Queries)
```php
// For unclear intent or multilingual support
$geminiPayload = json_encode([
    'contents' => [[
        'parts' => [['text' => $msg]]
    ]]
]);

$ch = curl_init("https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key={$apiKey}");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $geminiPayload,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    CURLOPT_TIMEOUT => 10
]);

$geminiResponse = json_decode(curl_exec($ch), true);
```

---

## 📦 Deployment Checklist

### Pre-Deployment
- [ ] All PHP syntax is valid (`php -l api/chatbot.php`)
- [ ] Database connection works
- [ ] Flask API is running (port 5000 accessible)
- [ ] Gemini API key is valid
- [ ] CORS headers are configured
- [ ] Session handling is secure
- [ ] Error logging is enabled

### Configuration Files
```php
// config/db.php - Must have:
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', 'password');
define('DB_NAME', 'ecommerce_chatbot');

// Also needs:
define('ADMIN_EMAIL', 'admin@shopai.rw');
define('ADMIN_PHONE', '+250XXXXXXXXX');
define('SITE_URL', 'http://localhost:80');
define('SITE_NAME', 'ShopAI');
define('GEMINI_API_KEY', 'your-key-here');
```

### Database Setup
```bash
# Create database
mysql -u root -p < database.sql

# Check tables exist
mysql -u root -p ecommerce_chatbot -e "SHOW TABLES;"

# Expected tables:
# - users
# - products
# - categories
# - orders
# - chatbot_logs
# - support_tickets
# - stock_notifications
```

### Flask API Setup
```bash
cd chatbot-ml

# Install dependencies
pip install -r requirements.txt

# Train models (if not trained)
python train.py

# Start Flask API
python app.py
# Should output: Running on http://127.0.0.1:5000
```

### Testing
```bash
# Test chatbot endpoint
curl -X POST http://localhost/api/chatbot.php \
  -H "Content-Type: application/json" \
  -d '{"message": "what do you have for 75k"}'

# Expected response:
# {"response": "✅ Products...", "quick_replies": [...]}

# Test ML API
curl -X POST http://localhost:5000/predict \
  -H "Content-Type: application/json" \
  -d '{"message": "show me phones"}'

# Expected response:
# {"intent": "product_search", "confidence": 0.92}
```

---

## 🚀 Deployment Steps

### 1. Stop Current Services
```bash
# Stop Apache
sudo systemctl stop apache2  # Linux
net stop Apache2.4          # Windows

# Stop Flask (if running)
pkill -f "python app.py"    # Linux
taskkill /IM python.exe     # Windows
```

### 2. Deploy Files
```bash
# Backup current version
cp -r /xampp/htdocs/ecommerce-chatbot /xampp/htdocs/ecommerce-chatbot.backup

# Copy new files
cp api/chatbot.php /xampp/htdocs/ecommerce-chatbot/api/

# Check permissions
chmod 755 api/chatbot.php
chmod 777 assets/images/chat_uploads/
chmod 755 includes/
```

### 3. Validate Installation
```bash
# Check PHP syntax
php -l /xampp/htdocs/ecommerce-chatbot/api/chatbot.php

# Check database connectivity
php -r "require 'config/db.php'; echo 'DB OK';"

# Check Flask API
curl http://localhost:5000/health
```

### 4. Start Services
```bash
# Start Apache
sudo systemctl start apache2    # Linux
net start Apache2.4            # Windows

# Start Flask
cd chatbot-ml
nohup python app.py > logs/flask.log 2>&1 &  # Linux
python app.py                                  # Windows

# Verify running
curl -s http://localhost/api/chatbot.php | head -c 50
curl -s http://localhost:5000/models/performance | head -c 50
```

### 5. Monitor
```bash
# Check error logs
tail -f /xampp/apache/logs/error.log

# Check PHP logs
tail -f /var/log/php-errors.log

# Monitor chatbot performance
mysql ecommerce_chatbot -e "
  SELECT 
    DATE_FORMAT(created_at, '%H:00') as hour,
    COUNT(*) as queries,
    AVG(processing_time_ms) as avg_time
  FROM chatbot_logs
  WHERE created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
  GROUP BY hour
  ORDER BY created_at DESC;
"
```

---

## 🔍 Monitoring & Health Checks

### Database Health
```sql
-- Check if database is accessible
SELECT 1 as status;

-- Check product inventory
SELECT COUNT(*) as total_products, 
       SUM(stock) as total_stock
FROM products;

-- Check for errors in logs
SELECT COUNT(*) as error_count
FROM chatbot_logs
WHERE response LIKE '%error%'
  AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR);
```

### API Health Checks
```bash
#!/bin/bash
# health_check.sh

echo "=== Chatbot System Health Check ==="

# 1. MySQL
echo -n "MySQL: "
mysql -u root -p$DB_PASS -e "SELECT 1" > /dev/null 2>&1 && echo "✅ UP" || echo "❌ DOWN"

# 2. PHP API
echo -n "PHP API: "
curl -s http://localhost/api/chatbot.php -d '{"message":"test"}' > /dev/null 2>&1 && echo "✅ UP" || echo "❌ DOWN"

# 3. Flask ML API
echo -n "Flask API: "
curl -s http://localhost:5000/health > /dev/null 2>&1 && echo "✅ UP" || echo "❌ DOWN"

# 4. Gemini API connectivity
echo -n "Gemini API: "
curl -s "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash?key=$GEMINI_KEY" | grep -q "models" && echo "✅ UP" || echo "❌ DOWN"

echo "=== Check Complete ==="
```

---

## 📊 Performance Tuning

### MySQL Optimization
```sql
-- Add indexes for fast queries
ALTER TABLE products ADD INDEX idx_price (price);
ALTER TABLE products ADD INDEX idx_stock (stock);
ALTER TABLE products ADD INDEX idx_category (category_id);
ALTER TABLE products ADD INDEX idx_name (name);

-- Enable query cache
SET GLOBAL query_cache_size = 262144;
SET GLOBAL query_cache_type = 1;

-- Check query performance
EXPLAIN SELECT * FROM products WHERE price <= 75000 AND stock > 0 LIMIT 8;
```

### PHP Optimization
```php
// Enable OPcache (cache compiled PHP)
// In php.ini:
opcache.enable=1
opcache.memory_consumption=128
opcache.interned_strings_buffer=8
opcache.max_accelerated_files=4000
opcache.revalidate_freq=60

// Use persistent connections
$conn = new mysqli(..., null, MYSQLI_CLIENT_COMPRESS);

// Enable compression for large responses
ob_start('ob_gzhandler');
```

### Flask Optimization
```python
# Use production WSGI server (gunicorn)
pip install gunicorn
gunicorn -w 4 -b 127.0.0.1:5000 app:app

# Load models once at startup
model = joblib.load('models/best_model.pkl')

# Use model caching
from functools import lru_cache
@lru_cache(maxsize=1000)
def cached_predict(message):
    return model.predict([message])
```

---

## 🚨 Troubleshooting

### Issue: "Can't connect to MySQL server"
```php
// Check config/db.php
echo DB_HOST . " | " . DB_USER . " | " . DB_NAME;

// Test connection
$test = @mysqli_connect(DB_HOST, DB_USER, DB_PASS);
if (!$test) {
    echo "Error: " . mysqli_connect_error();
} else {
    echo "Connected OK";
}
```

### Issue: Flask API not responding
```bash
# Check if Flask is running
ps aux | grep "python app.py"

# Check if port 5000 is listening
netstat -tlnp | grep 5000

# Restart Flask
pkill -f "python app.py"
cd chatbot-ml && python app.py
```

### Issue: Gemini API quota exceeded
```php
// Check remaining quota in logs
error_log("Gemini quota: " . json_encode($response));

// Fallback to local response
if ($geminiQuotaExceeded) {
    return reply(
        "Our AI is temporarily at capacity. Please try again in a moment.",
        ['Show me products', 'Contact support']
    );
}
```

### Issue: Slow database queries
```php
// Enable query logging
error_log("Query: $sql | Time: " . $time . "ms");

// Use EXPLAIN to analyze
$explain = $conn->query("EXPLAIN $sql");

// Add missing indexes
$conn->query("ALTER TABLE products ADD INDEX idx_budget_stock (price, stock)");
```

---

## 📈 Scaling Considerations

### For 1,000+ Concurrent Users

1. **Database**
   - Set up read replicas
   - Use connection pooling
   - Implement query caching

2. **PHP**
   - Deploy on multiple app servers
   - Use load balancer (nginx/HAProxy)
   - Enable OPcache

3. **Flask**
   - Run multiple worker processes
   - Use gunicorn with 4-8 workers
   - Implement model caching

4. **Infrastructure**
   - Set up CDN for static assets
   - Use sessions in Redis (not file)
   - Implement rate limiting

---

## ✅ Success Criteria

After deployment, verify:

- ✅ Chatbot responds to test queries in <200ms
- ✅ Budget queries work: "what do you have for 75k"
- ✅ Category queries work: "show me phones"
- ✅ Products display correctly with prices
- ✅ "Add to Cart" buttons function
- ✅ Order tracking works: "track order 5"
- ✅ Database queries are logged
- ✅ No JSON errors in response
- ✅ Mobile responsive design works
- ✅ Images load correctly

---

## 📞 Support

If issues occur:

1. **Check error logs**
   ```bash
   tail -f /xampp/apache/logs/error.log
   tail -f chatbot-ml/logs/*.log
   ```

2. **Enable debug mode**
   ```php
   define('DEBUG_CHATBOT', true);
   ```

3. **Test individual components**
   ```bash
   # Test DB
   mysql -u root -e "SELECT COUNT(*) FROM products;"
   
   # Test Flask
   curl http://localhost:5000/health
   
   # Test Gemini
   curl "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash/generateContent?key=YOUR_KEY"
   ```

4. **Contact support**
   - 📧 admin@shopai.rw
   - 📱 +250 XXX XXX XXX
