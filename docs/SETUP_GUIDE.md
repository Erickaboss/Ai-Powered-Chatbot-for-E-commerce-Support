# Quick Setup Guide - XAMPP Installation

## ✅ Files Successfully Copied to XAMPP
Your project has been copied to: `C:\xampp\htdocs\ecommerce-chatbot`

---

## 🚀 Step-by-Step Setup Instructions

### **Step 1: Start XAMPP Services**
1. Open **XAMPP Control Panel**
2. Click **Start** for **Apache**
3. Click **Start** for **MySQL**
4. Wait until both show "Running" status

### **Step 2: Create Database**
1. Open your browser and go to: `http://localhost/phpmyadmin`
2. Click on **"SQL"** tab at the top
3. Copy and paste the entire content of `database.sql` file
4. Click **"Go"** button to execute
5. You should see: "ecommerce_chatbot" database created with all tables and sample data

### **Step 3: Verify Database**
In phpMyAdmin, you should see these tables:
- users (6 users: 1 admin + 5 customers)
- categories (10 categories)
- products (118 products)
- cart, cart_items
- orders, order_items
- chatbot_logs, reviews, wishlists
- And more...

### **Step 4: Access Your E-Commerce Site**
Open browser and visit:
- **Main Site**: `http://localhost/ecommerce-chatbot`
- **Admin Panel**: `http://localhost/ecommerce-chatbot/admin`

### **Step 5: Login Credentials**

#### Admin Login:
- **Email**: `admin@shopai.rw`
- **Password**: `admin123`

#### Test Customer Accounts:
- **Email**: `eric@gmail.com` | **Password**: `password123`
- **Email**: `amina@gmail.com` | **Password**: `password123`
- **Email**: `jean@gmail.com` | **Password**: `password123`

---

## 🔧 Optional Configuration

### **Add OpenAI API Key (for GPT-3.5 fallback)**
1. Edit: `C:\xampp\htdocs\ecommerce-chatbot\config\db.php`
2. Find line: `define('OPENAI_API_KEY', 'your-openai-api-key-here');`
3. Replace with: `define('OPENAI_API_KEY', 'sk-your-actual-api-key-here');`

**Note**: The chatbot works WITHOUT OpenAI key using rule-based NLP!

### **Add Gemini API Key (for image recognition)**
1. Edit: `C:\xampp\htdocs\ecommerce-chatbot\config\secrets.php`
2. Add your Gemini API key

### **Configure Email (Brevo/Gmail SMTP)**
1. Edit: `C:\xampp\htdocs\ecommerce-chatbot\config\secrets.php`
2. Add your SMTP credentials for password reset emails

---

## 🤖 ML Chatbot Setup (Optional)

The Python-based ML chatbot is located in:
`C:\xampp\htdocs\ecommerce-chatbot\chatbot-ml\`

### **To Start Flask ML API:**
1. Open terminal in `chatbot-ml` folder
2. Run: `python app.py`
3. ML API will start on: `http://localhost:5000`

### **Auto-Start on Boot:**
Run the included batch file as Administrator:
```
C:\xampp\htdocs\ecommerce-chatbot\install_startup.bat
```

This will:
- Install Apache as Windows Service
- Install MySQL as Windows Service
- Create scheduled task for Flask ML API

---

## 📊 Project Statistics

- **Categories**: 10
- **Products**: 118 (priced from 1,200 RWF to 1,850,000 RWF)
- **Users**: 6 (1 admin + 5 customers)
- **Sample Orders**: 5
- **Reviews**: 5

### Product Categories:
1. Smartphones & Tablets (15 products)
2. Laptops & Computers (12 products)
3. TV & Audio (10 products)
4. Home Appliances (12 products)
5. Fashion — Men (12 products)
6. Fashion — Women (12 products)
7. Groceries & Food (15 products)
8. Health & Beauty (10 products)
9. Sports & Fitness (10 products)
10. Baby & Kids (10 products)

---

## 🎯 Key Features

✅ User registration & authentication  
✅ Shopping cart & wishlist  
✅ Order management with tracking  
✅ AI-powered chatbot (rule-based + ML + OpenAI fallback)  
✅ Admin dashboard  
✅ Product reviews & ratings  
✅ Password reset via email  
✅ Responsive design  

---

## 🐛 Troubleshooting

### Apache won't start?
- Check if port 80 is in use (Skype, IIS)
- Change Apache port in XAMPP config or stop conflicting services

### MySQL won't start?
- Check if port 3306 is in use
- Run XAMPP as Administrator

### Database errors?
- Make sure MySQL is running
- Verify database credentials in `config/db.php`:
  ```php
  define('DB_HOST', 'localhost');
  define('DB_USER', 'root');
  define('DB_PASS', '');
  define('DB_NAME', 'ecommerce_chatbot');
  ```

### Chatbot not working?
- Check browser console for JavaScript errors
- Ensure `api/chatbot.php` is accessible
- If using ML features, verify Flask is running on port 5000

---

## 📞 Support

For issues or questions, check:
- Project documentation in README.md
- Chatbot-ML documentation in `chatbot-ml/README.md`

---

**Ready to go! Visit: http://localhost/ecommerce-chatbot** 🚀
