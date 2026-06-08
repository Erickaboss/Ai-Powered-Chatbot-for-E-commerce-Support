# Comprehensive Chatbot Enhancements Guide

## Overview

This guide documents the comprehensive enhancements made to the e-commerce chatbot platform, including image recognition, multilingual support, full product database integration, and an advanced ML performance dashboard.

## New Components

### 1. Enhanced Chatbot API (`api/chatbot_enhanced.php`)

**Features:**
- Image/document upload handling with Gemini Vision API integration
- Product matching from all 15 product categories
- Budget-based product recommendations
- Multilingual support (English, French, Kinyarwanda)
- Guest ordering guidance
- Complete interaction logging

**Endpoints:**
```
POST /api/chatbot_enhanced.php
Content-Type: application/json

{
  "message": "Show me smartphones under 500000",
  "session_id": "optional_session_id",
  "upload_id": "optional_image_upload_id",
  "language": "en|fr|rw",
  "budget_min": 100000,
  "budget_max": 500000
}
```

**Response:**
```json
{
  "response": "Found 8 products for you...",
  "quick_replies": ["View details", "Add to cart", "Continue shopping"],
  "session_id": "session_id",
  "log_id": 123,
  "language": "en",
  "matched_products": [...]
}
```

### 2. Image Recognition Module (`api/image_recognition_module.php`)

**Features:**
- Secure image upload handling (JPEG, PNG, WebP)
- Gemini Vision API integration for image analysis
- Automatic product matching based on image content
- Fallback local analysis if API unavailable
- Database storage of matches with confidence scores

**Usage:**
```php
$module = new ImageRecognitionModule($conn);
$result = $module->processUpload($_FILES['image'], $session_id, $user_id);

// Returns:
{
  "success": true,
  "upload_id": 123,
  "file_name": "product.jpg",
  "analysis": "Image analysis text...",
  "matches": [...]
}
```

**Supported Image Types:**
- JPEG (image/jpeg)
- PNG (image/png)
- WebP (image/webp)
- Max file size: 10MB

### 3. ML Performance Dashboard (`admin/ml_performance_dashboard.php`)

**Features:**
- Real-time model performance metrics
- Comparison of all 4 ML models:
  - SVM (Linear) - Best performer
  - MLP Neural Network
  - Random Forest
  - Logistic Regression
- Detailed metrics display:
  - Accuracy, Precision, Recall, F1-Score
  - Cross-validation scores
  - Macro-averaged metrics
- Dataset statistics visualization
- Model artifacts and plots display
- Professional business presentation format

**Access:**
- URL: `/admin/ml_performance_dashboard.php`
- Requires admin authentication
- Displays real-time data from `chatbot-ml/models/model_results.json`

**Key Metrics:**
- Best Model Accuracy: 95.68% (SVM Linear)
- Average Accuracy: 95.28%
- Training Samples: 9,824
- Test Samples: 2,456
- Intent Classes: 35
- All models exceed 85% target accuracy

### 4. Updated Chatbot Simple (`api/chatbot_simple.php`)

**Enhancements:**
- Image upload support
- Full product database queries across all 15 categories
- Budget filtering with min/max price ranges
- Guest ordering guidance
- Gemini API fallback for complex queries
- Multilingual support (EN, FR, RW)
- Language auto-detection

**New Parameters:**
```json
{
  "message": "string",
  "session_id": "string",
  "upload_id": "integer (optional)",
  "language": "en|fr|rw",
  "budget_min": "integer (optional)",
  "budget_max": "integer (optional)"
}
```

## Database Schema

### New Tables

#### `chat_uploads`
```sql
CREATE TABLE chat_uploads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id VARCHAR(64) NOT NULL,
    user_id INT DEFAULT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_type VARCHAR(50),
    mime_type VARCHAR(100),
    file_size INT,
    file_path VARCHAR(255),
    storage_path VARCHAR(255),
    expires_at DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_session (session_id),
    INDEX idx_user (user_id),
    INDEX idx_expires (expires_at)
);
```

#### `image_recognition_matches`
```sql
CREATE TABLE image_recognition_matches (
    id INT AUTO_INCREMENT PRIMARY KEY,
    upload_id INT NOT NULL,
    product_id INT NOT NULL,
    match_score DECIMAL(5,4),
    matched_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (upload_id) REFERENCES chat_uploads(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    INDEX idx_upload (upload_id),
    INDEX idx_product (product_id),
    INDEX idx_score (match_score)
);
```

#### `upload_processing_queue`
```sql
CREATE TABLE upload_processing_queue (
    id INT AUTO_INCREMENT PRIMARY KEY,
    upload_id INT NOT NULL,
    status ENUM('pending', 'processing', 'completed', 'failed') DEFAULT 'pending',
    error_message TEXT,
    processed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (upload_id) REFERENCES chat_uploads(id) ON DELETE CASCADE,
    INDEX idx_status (status),
    INDEX idx_created (created_at)
);
```

### Setup Instructions

Run the migration script to create tables:
```bash
php api/create_image_recognition_tables.php
```

## Multilingual Support

### Supported Languages
- **English (en)** - Default
- **French (fr)** - Français
- **Kinyarwanda (rw)** - Ikinyarwanda

### Language Detection
Automatic detection based on keywords:
- French: "bonjour", "comment", "merci", "produit", "prix", "livraison"
- Kinyarwanda: "muraho", "mwaramutse", "ibicuruzwa", "umwaka", "agaciro"

### Localized Responses
All responses are available in all three languages:
- Greetings
- Product search results
- Budget recommendations
- Delivery information
- Payment methods
- Return policies
- Support contact information

## Product Categories (15 Total)

1. **Smartphones & Tablets** (15 products)
2. **Laptops & Computers** (12 products)
3. **TV & Audio** (10 products)
4. **Home Appliances** (12 products)
5. **Fashion — Men** (12 products)
6. **Fashion — Women** (12 products)
7. **Groceries & Food** (15 products)
8. **Health & Beauty** (10 products)
9. **Sports & Fitness** (10 products)
10. **Baby & Kids** (10 products)

Plus 5 additional categories for comprehensive coverage.

## Intent Recognition

The ML models recognize 35 different intents:

### Product-Related
- `product_search` - Search for products
- `recommendation` - Get product recommendations
- `product_price` - Query product prices
- `product_description` - Get product details
- `budget_search` - Find products within budget
- `category_search` - Browse categories
- `stock_check` - Check product availability

### Order-Related
- `place_order` - Place a new order
- `order_track` - Track existing order
- `order_history` - View order history
- `order_cancel` - Cancel an order
- `order_status_query` - Query order status
- `guest_order_guide` - Guide for guest checkout

### Information
- `delivery_time` - Delivery timeframes
- `shipping_fee` - Shipping costs
- `payment_methods` - Available payment options
- `return_policy` - Return and refund policy
- `warranty` - Product warranty info
- `invoice` - Invoice information

### Support
- `contact_support` - Contact support team
- `support_ticket` - Create support ticket
- `complaint` - File a complaint
- `chatbot_rating` - Rate the chatbot

### General
- `greeting` - Greeting message
- `bot_identity` - Bot information
- `professional_greeting` - Professional greeting
- `thanks` - Thank you message
- `goodbye` - Goodbye message
- `image_upload` - Image upload handling
- `multilingual_help` - Multilingual assistance
- `analytics` - Analytics queries
- `platform_info` - Platform information
- `discount_promo` - Discount/promotion info
- `account_help` - Account assistance

## API Integration

### Flask ML API
- **Endpoint:** `http://127.0.0.1:5001/predict`
- **Port:** 5001
- **Method:** POST
- **Payload:** `{"message": "user message", "model": "best"}`
- **Response:** `{"intent": "intent_name", "confidence": 0.95}`

### Google Gemini API
- **Endpoint:** `https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent`
- **Features:**
  - Image analysis and recognition
  - Multilingual query handling
  - Fallback for low-confidence predictions
- **Configuration:** Set `GEMINI_API_KEY` in `config/secrets.php`

## Usage Examples

### Example 1: Product Search
```json
{
  "message": "Show me smartphones",
  "language": "en"
}
```

Response: Lists available smartphones with prices and stock info

### Example 2: Budget Search
```json
{
  "message": "I have 300000 RWF",
  "language": "en",
  "budget_min": 200000,
  "budget_max": 300000
}
```

Response: Products within the specified budget range

### Example 3: Image Upload
```json
{
  "upload_id": 123,
  "language": "en"
}
```

Response: Matched products from image analysis

### Example 4: Multilingual Query
```json
{
  "message": "Bonjour, je cherche un laptop",
  "language": "fr"
}
```

Response: French language response with laptop recommendations

### Example 5: Guest Ordering
```json
{
  "message": "How do I place an order?",
  "language": "en"
}
```

Response: Step-by-step ordering guide for guests

## Performance Metrics

### Model Comparison

| Model | Accuracy | Precision | Recall | F1-Score | Status |
|-------|----------|-----------|--------|----------|--------|
| SVM (Linear) | 95.68% | 95.51% | 95.68% | 95.43% | ✓ Best |
| MLP Neural Network | 95.52% | 95.58% | 95.52% | 95.38% | ✓ Meets Target |
| Random Forest | 95.36% | 95.37% | 95.36% | 95.21% | ✓ Meets Target |
| Logistic Regression | 94.54% | 95.56% | 94.54% | 94.82% | ✓ Meets Target |

### Dataset Statistics
- **Total Samples:** 12,280
- **Training Samples:** 9,824 (80%)
- **Test Samples:** 2,456 (20%)
- **Unique Intents:** 35
- **Vocabulary Size:** 8,000 features
- **Languages:** 3 (English, French, Kinyarwanda)

## Security Features

### File Upload Security
- MIME type validation
- File size limits (10MB max)
- Secure file storage outside web root
- Automatic cleanup after 30 days
- Virus scanning support (ClamAV)

### Database Security
- Prepared statements for all queries
- Input sanitization
- SQL injection prevention
- User authentication required for admin features

### API Security
- Session-based authentication
- CORS headers configured
- Rate limiting recommended
- HTTPS recommended for production

## Configuration

### Environment Variables
Set in `config/secrets.php`:

```php
<?php
// Google Gemini API Key
define('_GEMINI_KEY', 'your-gemini-api-key-here');

// SMTP Configuration
define('_SMTP_USER', 'your-email@gmail.com');
define('_SMTP_PASS', 'your-app-password');

// Google Custom Search (optional)
define('_GOOGLE_CSE_KEY', 'your-cse-key');
define('_GOOGLE_CSE_CX', 'your-cse-cx');
?>
```

### Upload Directory
- **Path:** `assets/images/chat_uploads/`
- **Permissions:** 755
- **Auto-created:** Yes

## Troubleshooting

### Image Recognition Not Working
1. Check Gemini API key is set in `config/secrets.php`
2. Verify image file is valid (JPEG, PNG, WebP)
3. Check file size is under 10MB
4. Review error logs in `error_log`

### Multilingual Responses Not Showing
1. Verify language parameter is 'en', 'fr', or 'rw'
2. Check language detection keywords
3. Ensure database has UTF-8 encoding

### ML Model Not Responding
1. Verify Flask API is running on port 5001
2. Check Flask service status: `python chatbot-ml/app.py`
3. Review Flask error logs
4. Ensure models are trained: `python chatbot-ml/train.py`

### Database Connection Issues
1. Verify MySQL is running
2. Check credentials in `config/db.php`
3. Ensure database `ecommerce_chatbot` exists
4. Run migration: `php api/create_image_recognition_tables.php`

## Performance Optimization

### Caching
- Cache product queries for 1 hour
- Cache category list for 24 hours
- Cache model predictions for 5 minutes

### Database Indexing
- Session ID index on chatbot_logs
- User ID index on orders
- Product ID index on cart_items
- Category ID index on products

### API Optimization
- Connection pooling for database
- Async image processing queue
- Batch product queries
- Response compression

## Future Enhancements

1. **Advanced Analytics**
   - User behavior tracking
   - Conversation flow analysis
   - Intent prediction accuracy per user

2. **Personalization**
   - User preference learning
   - Personalized recommendations
   - Purchase history integration

3. **Advanced Features**
   - Voice input support
   - Video product demonstrations
   - AR product preview
   - Real-time inventory sync

4. **Performance**
   - Redis caching layer
   - Elasticsearch for product search
   - GraphQL API
   - WebSocket for real-time updates

## Support

For issues or questions:
- Email: eric@shopai.rw
- Phone: +250782977559
- Hours: Mon-Sat, 8AM-6PM (Kigali time)

## License

All enhancements are part of the ShopAI Rwanda e-commerce platform.
