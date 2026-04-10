# System Architecture - AI-Powered E-Commerce Chatbot

## High-Level Architecture

```
┌─────────────────────────────────────────────────────────────────────┐
│                         CLIENT LAYER                                │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐              │
│  │ Web Browser  │  │ Mobile Device│  │ Admin Panel  │              │
│  │ (JavaScript) │  │ (Responsive) │  │ (Dashboard)  │              │
│  └──────────────┘  └──────────────┘  └──────────────┘              │
│                          │                                           │
└──────────────────────────┼───────────────────────────────────────────┘
                           │ HTTP/HTTPS
┌──────────────────────────┼───────────────────────────────────────────┐
│                    APPLICATION LAYER                                  │
│                           ▼                                           │
│  ┌─────────────────────────────────────────────────────────────┐    │
│  │           Apache Web Server (PHP 8.2)                       │    │
│  │  ┌──────────────────────────────────────────────────────┐   │    │
│  │  │              PHP Application Layer                   │   │    │
│  │  │  ┌────────────┐ ┌────────────┐ ┌────────────┐       │   │    │
│  │  │  │  Front     │ │   Admin    │ │    API     │       │   │    │
│  │  │  │  Controller│ │ Controller │ │ Endpoints  │       │   │    │
│  │  │  └────────────┘ └────────────┘ └────────────┘       │   │    │
│  │  │                                                       │   │    │
│  │  │  ┌──────────────────────────────────────────────┐    │   │    │
│  │  │  │         Security & Middleware                │    │   │    │
│  │  │  │  • CSRF Protection  • Rate Limiting          │    │   │    │
│  │  │  │  • Input Sanitization • Session Management   │    │   │    │
│  │  │  └──────────────────────────────────────────────┘    │   │    │
│  │  └──────────────────────────────────────────────────────┘   │    │
│  └─────────────────────────────────────────────────────────────┘    │
│                           │                                           │
└───────────────────────────┼───────────────────────────────────────────┘
                            │
        ┌───────────────────┼───────────────────┐
        │                   │                   │
        ▼                   ▼                   ▼
┌──────────────┐   ┌──────────────┐   ┌──────────────┐
│  DATA LAYER  │   │   ML LAYER   │   │ CACHE LAYER  │
│              │   │              │   │              │
│ ┌──────────┐ │   │ ┌──────────┐ │   │ ┌──────────┐ │
│ │  MySQL   │ │   │ │  Flask   │ │   │ │  Redis   │ │
│ │ Database │ │   │ │   API    │ │   │ │  Cache   │ │
│ │          │ │   │ │ (Python) │ │   │ │          │ │
│ │• Users   │ │   │ │          │ │   │ │• Sessions│ │
│ │• Products│ │   │ │• Intent  │ │   │ │• Search  │ │
│ │• Orders  │ │   │ │  Classif.│ │   │ │• Predict.│ │
│ │• Cart    │ │   │ │• TF-IDF  │ │   │ │• Query   │ │
│ │• Logs    │ │   │ │• Models  │ │   │ │  Results │ │
│ └──────────┘ │   │ └──────────┘ │   │ └──────────┘ │
└──────────────┘   └──────────────┘   └──────────────┘
```

## Data Flow Diagrams

### 1. User Request Flow

```
User → Header/Footer → Page Controller → Database → Response
                        ↓
                    Session Check
                        ↓
                  Load User Data
                        ↓
                  Render Template
```

### 2. Chatbot Message Flow

```
User Types Message
        ↓
JavaScript (chatbot.js)
        ↓
POST /api/chatbot.php
        ↓
Session Validation
        ↓
Intent Processing
    ┌─────────────┴─────────────┐
    │                           │
Rule-Based Check          ML Classification
    │                           │
    │                     POST /predict (Flask)
    │                           │
    │                     TF-IDF Vectorize
    │                           │
    │                     Model Prediction
    │                           │
    └──────────┬────────────────┘
               │
         Get Response Template
               │
         Query Database (if product/order)
               │
         Format Response + Quick Replies
               │
         Log to chatbot_logs
               │
         Return JSON Response
               ↓
Display in Chat Widget
```

### 3. Order Creation Flow

```
User Adds to Cart
        ↓
cart.php → Add to cart_items
        ↓
checkout.php
        ↓
Validate Address & Payment
        ↓
Create Order (transactions)
    ┌──────────────────────┐
    │ START TRANSACTION    │
    │                      │
    │ INSERT orders        │
    │ INSERT order_items   │
    │ UPDATE products.stock│
    │ DELETE cart_items    │
    │                      │
    │ COMMIT               │
    └──────────────────────┘
        ↓
Send Confirmation Email
        ↓
Clear Cart
        ↓
Redirect to order_detail.php
```

### 4. ML Model Training Pipeline

```
intents.json Dataset
        ↓
Data Preprocessing
    • Lowercase conversion
    • Tokenization
    • Remove duplicates
        ↓
TF-IDF Vectorization
    • Max features: 8000
    • N-gram range: (1,3)
    • Sublinear TF: true
        ↓
Split Dataset (80/20)
    • Stratified sampling
        ↓
Train 4 Models in Parallel
    ┌──────┬─────────┬─────┬─────┐
    │  LR  │   RF    │ SVM │ MLP │
    └──────┴─────────┴─────┴─────┘
        ↓
Evaluate Each Model
    • Accuracy
    • Precision
    • Recall
    • F1 Score
    • Cross-validation
        ↓
Generate Visualizations
    • Confusion matrices
    • Model comparison charts
    • Performance metrics
        ↓
Save Best Model + Pickle Files
        ↓
Deploy to Flask API
```

## Component Interactions

### Security Layer

```
Request → CSRF Check → Input Sanitization → Rate Limit → Business Logic
              ↓              ↓                   ↓
         Validate      Escape HTML       Check Limits
         Token         Special Chars     Per IP/Session
```

### Error Handling

```
Error Occurs
        ↓
Error Logger Captures
    • Timestamp
    • Error Level
    • File & Line
    • Stack Trace
    • IP & URL
        ↓
Write to logs/error.log
        ↓
Show Custom Error Page
    • 404 for not found
    • 500 for server errors
        ↓
Admin Notification (critical only)
```

## Deployment Architecture

### Development (XAMPP)

```
localhost:80 (Apache)
    ├── PHP 8.2
    ├── MySQL 8.0 (port 3306)
    └── Python Flask (port 5000)
```

### Production (Docker)

```
Internet
    ↓
Nginx Reverse Proxy (SSL Termination)
    ↓
┌────────────────────────────────────┐
│  Docker Containers                 │
│                                    │
│  app:80        (PHP Application)   │
│  ml-api:5000   (Flask ML API)      │
│  db:3306       (MySQL Database)    │
│  redis:6379    (Cache Layer)       │
└────────────────────────────────────┘
    ↓
Persistent Volumes
    • db_data (MySQL)
    • redis_data (Redis)
    • ./logs (Application Logs)
```

## Database Schema

### Core Tables

```
users (id, email, password, role, created_at)
    ├── orders (user_id → id)
    ├── cart (user_id → id)
    ├── reviews (user_id → id)
    └── chatbot_logs (user_id → id)

products (id, category_id, name, price, stock)
    ├── order_items (product_id → id)
    ├── cart_items (product_id → id)
    └── reviews (product_id → id)

categories (id, name, description)
    └── products (category_id → id)

orders (id, user_id, total_price, status)
    └── order_items (order_id → id)
```

## API Gateway Pattern

```
Client Request
        ↓
/api/*.php (Unified Entry Point)
        ↓
Route Handler
    ├── Action: history → loadChatHistory()
    ├── Action: rate → rateResponse()
    ├── Action: stock_notify → registerNotification()
    └── Default → processMessage()
        ↓
Response Formatter (JSON)
        ↓
Client Receives Standardized Response
```

## Scalability Considerations

### Current Capacity
- **Users**: 100-1,000 concurrent
- **Messages**: 10,000/day
- **Products**: 10,000+ items

### Scaling Strategies

#### Horizontal Scaling
```
Load Balancer
    ├── App Instance 1 + Redis
    ├── App Instance 2 + Redis
    └── App Instance N + Redis
            ↓
    Master-Slave DB Replication
```

#### Caching Strategy
```
Redis Cache Layers:
    1. Session Cache (user sessions)
    2. Query Cache (frequent SQL results)
    3. Object Cache (products, categories)
    4. ML Cache (common predictions)
```

## Monitoring & Observability

```
Application Metrics
    ├── Response Times
    ├── Error Rates
    ├── Request Volume
    └── Database Queries

ML Model Metrics
    ├── Prediction Accuracy
    ├── Confidence Scores
    ├── Intent Distribution
    └── User Satisfaction

Business Metrics
    ├── Conversion Rate
    ├── Cart Abandonment
    ├── Order Value
    └── Customer Retention
```

---

**Document Version**: 1.0  
**Last Updated**: April 2026  
**Maintained By**: Development Team
