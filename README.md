# AI-Powered E-Commerce Chatbot (PHP & MySQL)

## Setup Instructions

### 1. Requirements
- XAMPP (Apache + MySQL + PHP 8.0+)
- OpenAI API key (optional — chatbot works without it using rule-based NLP)

### 2. Installation
1. Copy the `ecommerce-chatbot/` folder to `C:/xampp/htdocs/`
2. Open phpMyAdmin → import `database.sql`
3. Edit `config/db.php` if your DB credentials differ
4. Visit: `http://localhost/ecommerce-chatbot`

### 3. Admin Login
- Email: `admin@shop.com`
- Password: `password`

### 4. OpenAI Integration (optional)
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
