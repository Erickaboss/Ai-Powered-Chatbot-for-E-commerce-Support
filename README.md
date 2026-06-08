# 🎓 AI-Powered E-Commerce Chatbot with Machine Learning

[![Python 3.8+](https://img.shields.io/badge/python-3.8+-blue.svg)](https://www.python.org/downloads/)
[![PHP 8.1+](https://img.shields.io/badge/php-8.1+-purple.svg)](https://www.php.net/)
[![MySQL 8.0](https://img.shields.io/badge/mysql-8.0-orange.svg)](https://www.mysql.com/)
[![ML Accuracy](https://img.shields.io/badge/ML_Accuracy-95.68%25-brightgreen.svg)]()
[![License](https://img.shields.io/badge/license-MIT-green.svg)]()

## 🏆 Project Highlights

- **🤖 Advanced AI Chatbot**: Hybrid architecture (Rule-based + Machine Learning)
- **📊 ML Performance**: 95.68% accuracy with 4 trained models (SVM, MLP, RF, LR)
- **🌍 Multilingual**: English, French, Kinyarwanda support
- **🛒 Full E-Commerce**: 118+ products, cart, orders, reviews, wishlist
- **📈 Analytics Dashboard**: Real-time metrics, customer segmentation, ML monitoring
- **🎤 Voice Input**: Speech-to-text hands-free shopping
- **🔒 Enterprise Security**: CSRF, SQL injection, XSS protection
- **📱 Responsive Design**: Mobile-first, works on all devices

---

## Setup Instructions

### 1. Requirements
- XAMPP (Apache + MySQL + PHP 8.0+)
- OpenAI API key (optional — chatbot works without it using rule-based NLP)

### 2. Installation
1. Copy the `ecommerce-chatbot/` folder to `C:/xampp/htdocs/`
2. Open phpMyAdmin → import `database.sql`
3. Import the product dataset (1,161 products):
   ```bash
   php chatbot-ml/import_dataset.php
   ```
4. Edit `config/db.php` if your DB credentials differ
5. Visit: `http://localhost/ecommerce-chatbot`

### 3. Admin Login
- **Email**: `ericniringiyimana123@gmail.com`
- **Password**: `admin123`

### 4. Purchasing Process
The platform features a streamlined multi-step purchasing flow:
1. **Browse**: Explore products in the shop or use the AI chatbot to find items.
2. **Cart**: Add items to your cart. Shipping is calculated automatically (Free over RWF 50,000).
3. **Checkout**: Enter delivery details and choose from multiple payment options:
   - **MTN MoMo / Airtel Money**: Direct mobile payment instructions provided.
   - **Card**: Secure entry for Visa/Mastercard.
   - **Bank Transfer**: Details for BK bank transfer.
   - **Cash on Delivery**: Pay when items arrive.
4. **Authorization**: For secure methods (MoMo, Airtel, Card), a simulated **Payment Gateway Authorization** step is triggered where customers must enter their **PIN or Password** to confirm the transfer.
5. **Confirmation**: Receive an instant order summary and email confirmation.

### 5. OpenAI Integration (optional)
Set your API key in `config/db.php`:
```php
define('OPENAI_API_KEY', 'sk-your-key-here');
```
Without a key, the chatbot uses built-in rule-based responses.

## Pages
| Page | URL |
|------|-----|
| Home | /index.php |
| Products | /products.php |
| Cart | /cart.php |
| Orders | /orders.php |
| Admin Dashboard | /admin/index.php |
| Admin Products | /admin/products.php |
| Admin Orders | /admin/orders.php |
| Chatbot Logs | /admin/chatbot_logs.php |

## Chatbot Capabilities
- Greetings & farewells
- Order status tracking (by order number)
- Delivery & shipping info
- Return & refund policy
- Payment methods
- Product search (queries the live database)
- OpenAI GPT-3.5 fallback for unknown questions
