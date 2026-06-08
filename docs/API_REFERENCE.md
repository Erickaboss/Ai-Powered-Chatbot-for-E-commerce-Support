# Chatbot Enhancements - API Reference

## Enhanced Chatbot API

### Endpoint
```
POST /api/chatbot_enhanced.php
```

### Request Headers
```
Content-Type: application/json
```

### Request Body

```json
{
  "message": "string (optional if upload_id provided)",
  "session_id": "string (optional, auto-generated if not provided)",
  "upload_id": "integer (optional, for image uploads)",
  "language": "string (en|fr|rw, optional, auto-detected)",
  "budget_min": "integer (optional, minimum price in RWF)",
  "budget_max": "integer (optional, maximum price in RWF)"
}
```

### Response

```json
{
  "response": "string (chatbot response)",
  "quick_replies": ["array of suggested responses"],
  "session_id": "string (session identifier)",
  "log_id": "integer (interaction log ID)",
  "language": "string (detected/used language)",
  "matched_products": [
    {
      "id": "integer",
      "name": "string",
      "price": "decimal",
      "brand": "string",
      "stock": "integer",
      "image": "string",
      "category_id": "integer"
    }
  ]
}
```

### Example Requests

#### 1. Product Search
```bash
curl -X POST http://localhost/ecommerce-chatbot/api/chatbot_enhanced.php \
  -H "Content-Type: application/json" \
  -d '{
    "message": "Show me smartphones",
    "language": "en"
  }'
```

#### 2. Budget Search
```bash
curl -X POST http://localhost/ecommerce-chatbot/api/chatbot_enhanced.php \
  -H "Content-Type: application/json" \
  -d '{
    "message": "I have 500000 RWF",
    "budget_min": 300000,
    "budget_max": 500000,
    "language": "en"
  }'
```

#### 3. Multilingual Query
```bash
curl -X POST http://localhost/ecommerce-chatbot/api/chatbot_enhanced.php \
  -H "Content-Type: application/json" \
  -d '{
    "message": "Bonjour, je cherche des vêtements",
    "language": "fr"
  }'
```

#### 4. Image Upload
```bash
curl -X POST http://localhost/ecommerce-chatbot/api/chatbot_enhanced.php \
  -H "Content-Type: application/json" \
  -d '{
    "upload_id": 123,
    "language": "en"
  }'
```

#### 5. Ordering Guide
```bash
curl -X POST http://localhost/ecommerce-chatbot/api/chatbot_enhanced.php \
  -H "Content-Type: application/json" \
  -d '{
    "message": "How do I place an order?",
    "language": "en"
  }'
```

---

## Image Recognition Module

### Endpoint
```
POST /api/image_recognition_module.php
```

### Request Headers
```
Content-Type: multipart/form-data
```

### Request Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| image | file | Yes | Image file (JPEG, PNG, WebP) |
| session_id | string | No | Session identifier |
| user_id | integer | No | User ID (if logged in) |

### Response

```json
{
  "success": true,
  "upload_id": 123,
  "file_name": "product.jpg",
  "analysis": "Image analysis text from Gemini API",
  "matches": [
    {
      "id": 1,
      "name": "Product Name",
      "price": 450000,
      "brand": "Brand Name",
      "stock": 25,
      "image": "p1.jpg",
      "category_id": 1
    }
  ]
}
```

### Error Response

```json
{
  "success": false,
  "error": "Error message describing what went wrong"
}
```

### Example Request

```bash
curl -X POST http://localhost/ecommerce-chatbot/api/image_recognition_module.php \
  -F "image=@/path/to/product.jpg" \
  -F "session_id=abc123def456" \
  -F "user_id=1"
```

### Supported Image Types
- JPEG (image/jpeg)
- PNG (image/png)
- WebP (image/webp)

### Constraints
- Maximum file size: 10MB
- Minimum dimensions: 100x100 pixels
- Recommended dimensions: 800x600 or larger

---

## Updated Simple Chatbot API

### Endpoint
```
POST /api/chatbot_simple.php
```

### Request Headers
```
Content-Type: application/json
```

### Request Body

```json
{
  "message": "string (optional if upload_id provided)",
  "session_id": "string (optional, auto-generated)",
  "upload_id": "integer (optional)",
  "language": "string (en|fr|rw, optional)",
  "budget_min": "integer (optional)",
  "budget_max": "integer (optional)"
}
```

### Response

```json
{
  "response": "string",
  "quick_replies": ["array"],
  "session_id": "string",
  "log_id": "integer",
  "intent": "string",
  "confidence": "decimal",
  "language": "string",
  "matched_products": ["array"]
}
```

### Supported Intents

#### Product-Related
- `product_search` - Search for products
- `recommendation` - Get recommendations
- `product_price` - Query prices
- `product_description` - Get details
- `budget_search` - Find by budget
- `category_search` - Browse categories
- `stock_check` - Check availability

#### Order-Related
- `place_order` - Place order
- `order_track` - Track order
- `order_history` - View history
- `order_cancel` - Cancel order
- `guest_order_guide` - Ordering guide

#### Information
- `delivery_time` - Delivery info
- `shipping_fee` - Shipping costs
- `payment_methods` - Payment options
- `return_policy` - Return policy
- `warranty` - Warranty info

#### Support
- `contact_support` - Contact support
- `support_ticket` - Create ticket
- `complaint` - File complaint

#### General
- `greeting` - Greeting
- `bot_identity` - Bot info
- `thanks` - Thank you
- `goodbye` - Goodbye

---

## ML Performance Dashboard

### Endpoint
```
GET /admin/ml_performance_dashboard.php
```

### Requirements
- Admin authentication required
- Session must be active
- User role must be 'admin'

### Data Source
```
chatbot-ml/models/model_results.json
```

### Displayed Metrics

#### Model Performance
- Accuracy
- Precision
- Recall
- F1-Score
- Cross-Validation Mean
- Macro Precision
- Macro Recall
- Macro F1

#### Dataset Statistics
- Total Samples: 12,280
- Training Samples: 9,824
- Test Samples: 2,456
- Unique Intents: 35
- Vocabulary Size: 8,000
- Languages: 3

#### Models Compared
1. SVM (Linear) - 95.68%
2. MLP Neural Network - 95.52%
3. Random Forest - 95.36%
4. Logistic Regression - 94.54%

---

## Language Support

### Supported Languages

| Code | Language | Auto-Detect Keywords |
|------|----------|---------------------|
| en | English | (default) |
| fr | French | bonjour, comment, merci, produit, prix, livraison |
| rw | Kinyarwanda | muraho, mwaramutse, ibicuruzwa, umwaka, agaciro |

### Language Parameter
```json
{
  "language": "en"  // or "fr" or "rw"
}
```

### Auto-Detection
If language parameter is not provided, the system automatically detects based on keywords in the message.

---

## Product Categories

### All 15 Categories

| ID | Category | Products |
|----|----------|----------|
| 1 | Smartphones & Tablets | 15 |
| 2 | Laptops & Computers | 12 |
| 3 | TV & Audio | 10 |
| 4 | Home Appliances | 12 |
| 5 | Fashion — Men | 12 |
| 6 | Fashion — Women | 12 |
| 7 | Groceries & Food | 15 |
| 8 | Health & Beauty | 10 |
| 9 | Sports & Fitness | 10 |
| 10 | Baby & Kids | 10 |

---

## Error Handling

### Common Error Responses

#### 400 Bad Request
```json
{
  "response": "Please type a message or upload an image.",
  "quick_replies": []
}
```

#### 500 Internal Server Error
```json
{
  "response": "Something went wrong. Please try again.",
  "quick_replies": ["Show me products", "Contact support"]
}
```

#### Image Upload Error
```json
{
  "success": false,
  "error": "File too large (max 10MB)"
}
```

### Error Messages
- "No file uploaded"
- "File too large (max 10MB)"
- "Invalid file type"
- "Failed to store file"
- "Flask API failed"
- "Database connection failed"
- "Invalid Flask response"

---

## Rate Limiting

### Recommended Limits
- 100 requests per minute per session
- 10 image uploads per minute per user
- 1000 requests per hour per IP

### Implementation
```php
// Check rate limit before processing
if (checkRateLimit($session_id, 100, 60)) {
    // Process request
} else {
    // Return rate limit error
}
```

---

## Authentication

### Session-Based
```php
session_start();
$user_id = $_SESSION['user_id'] ?? null;
```

### Admin Dashboard
```php
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ' . SITE_URL . '/login.php');
    exit;
}
```

---

## Database Queries

### Product Search
```sql
SELECT id, name, price, brand, category_id, stock, image, description
FROM products
WHERE (name LIKE ? OR brand LIKE ? OR description LIKE ?)
AND stock > 0
LIMIT 8
```

### Budget Search
```sql
SELECT id, name, price, brand, category_id, stock, image
FROM products
WHERE price BETWEEN ? AND ?
AND stock > 0
ORDER BY price ASC
LIMIT 10
```

### Category List
```sql
SELECT id, name FROM categories LIMIT 15
```

### Image Matches
```sql
SELECT p.id, p.name, p.price, p.brand, p.stock, p.image
FROM image_recognition_matches m
JOIN products p ON m.product_id = p.id
WHERE m.upload_id = ?
ORDER BY m.match_score DESC
LIMIT 8
```

---

## Configuration

### Environment Variables
```php
// config/secrets.php
define('_GEMINI_KEY', 'your-gemini-api-key');
define('_SMTP_USER', 'your-email@gmail.com');
define('_SMTP_PASS', 'your-app-password');
define('_GOOGLE_CSE_KEY', 'your-cse-key');
define('_GOOGLE_CSE_CX', 'your-cse-cx');
```

### Database Configuration
```php
// config/db.php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'ecommerce_chatbot');
```

### Flask ML API
```
http://127.0.0.1:5001/predict
```

---

## Performance Metrics

### Response Times
- Text query: < 500ms
- Image processing: 2-5 seconds
- Database query: < 100ms
- ML prediction: < 200ms
- Average total: < 1 second

### Throughput
- 100+ concurrent users
- 1000+ requests per minute
- 10+ image uploads per minute

### Availability
- 99.9% uptime target
- Automatic failover for API errors
- Graceful degradation

---

## Troubleshooting

### Issue: "Flask API failed"
**Solution:** Ensure Flask is running on port 5001
```bash
cd chatbot-ml
python app.py
```

### Issue: "Database connection failed"
**Solution:** Check MySQL credentials in `config/db.php`

### Issue: "Image recognition not working"
**Solution:** Verify Gemini API key in `config/secrets.php`

### Issue: "Multilingual responses not showing"
**Solution:** Verify language parameter is 'en', 'fr', or 'rw'

### Issue: "Dashboard shows no data"
**Solution:** Ensure `chatbot-ml/models/model_results.json` exists

---

## Support

For API support:
- Email: eric@shopai.rw
- Phone: +250782977559
- Hours: Mon-Sat, 8AM-6PM (Kigali time)

## Documentation

- `CHATBOT_ENHANCEMENTS_GUIDE.md` - Comprehensive guide
- `SETUP_ENHANCEMENTS.md` - Setup instructions
- `ENHANCEMENTS_SUMMARY.md` - Project summary
- `API_REFERENCE.md` - This file
