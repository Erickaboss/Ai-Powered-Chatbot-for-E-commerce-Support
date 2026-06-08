# 🎬 Live Demo Script - Final Defense

## 📋 Pre-Demo Checklist (5 minutes before)

- [ ] XAMPP Apache running
- [ ] XAMPP MySQL running
- [ ] Flask API running (`python app.py` in chatbot-ml folder)
- [ ] Browser open: `http://localhost/ecommerce-chatbot`
- [ ] Admin credentials ready: admin@shop.com / password
- [ ] Chat widget visible on homepage

---

## 🎯 Demo Script (5-7 minutes)

### **Part 1: Homepage & Navigation (30 seconds)**

**What to Say:**
> "Let me start by showing you the main e-commerce platform. As you can see, we have a clean, responsive design that works on both desktop and mobile devices."

**Actions:**
1. Show homepage
2. Scroll down to show featured products
3. Click on "Products" in navigation

---

### **Part 2: Product Catalog (1 minute)**

**What to Say:**
> "Here's our product catalog with 118+ products across 10 categories. Users can browse, search, and filter products based on their preferences."

**Actions:**
1. Show product grid
2. Apply a filter (e.g., price range or brand)
3. Click on a product to show details page
4. Show product image, description, price, add to cart button

**What to Say:**
> "Each product has detailed information, reviews, and ratings. Users can also add products to their wishlist and share it with friends."

---

### **Part 3: AI Chatbot Demo (2-3 minutes) ⭐ MAIN FEATURE**

**What to Say:**
> "Now let me demonstrate the core feature - our AI-powered chatbot. This uses a hybrid architecture combining rule-based NLP with machine learning models achieving 95.68% accuracy."

#### **Demo 1: Greeting (15 seconds)**
**Type:** `Hello` or `Muraho` or `Bonjour`

**Expected Response:**
- Friendly greeting in the same language
- Quick reply buttons appear

**What to Say:**
> "The chatbot detects the language automatically and responds accordingly. It supports English, French, and Kinyarwanda."

---

#### **Demo 2: Product Search (30 seconds)**
**Type:** `Show me laptops`

**Expected Response:**
- Shows laptop products from database
- Displays product cards with images, prices

**What to Say:**
> "When users ask for products, the chatbot queries the live database and returns relevant results with images and prices. This happens in real-time."

---

#### **Demo 3: Product Price Query (30 seconds)**
**Type:** `What is the price of iPhone 14?`

**Expected Response:**
- Shows iPhone 14 product details
- Displays price, availability

**What to Say:**
> "The chatbot understands specific product queries and fetches accurate information from the database."

---

#### **Demo 4: Order Tracking (30 seconds)**
**Type:** `Track my order`

**Expected Response:**
- Asks for order number
- Type an order number (e.g., from database)

**What to Say:**
> "Users can track their orders directly through the chatbot. It queries the orders table and provides real-time status updates."

---

#### **Demo 5: Voice Input (30 seconds)**
**Actions:**
1. Click the microphone button 🎤
2. Speak: "Show me phones"
3. Show transcription and response

**What to Say:**
> "The chatbot also supports voice input using the Web Speech API. Users can speak their queries hands-free, making it accessible and convenient."

---

#### **Demo 6: Multilingual Support (30 seconds)**
**Type:** `Montrez-moi des téléphones` (French)

**Expected Response:**
- Shows phones in French

**Type:** `Ngaragira telefone` (Kinyarwanda)

**Expected Response:**
- Shows phones in Kinyarwanda

**What to Say:**
> "This multilingual capability is crucial for the Rwandan market, where people speak multiple languages."

---

### **Part 4: Shopping Cart & Checkout (1 minute)**

**What to Say:**
> "Let me show the complete shopping experience."

**Actions:**
1. Go back to a product page
2. Click "Add to Cart"
3. Navigate to cart page
4. Show cart items, quantities, total price
5. Click "Proceed to Checkout"
6. Show checkout form (don't complete)

**What to Say:**
> "The platform includes a full shopping cart and checkout system with address management and order confirmation."

---

### **Part 5: Admin Dashboard (1-2 minutes)**

**What to Say:**
> "Now let me show the admin dashboard where store managers can monitor sales, analyze chatbot performance, and manage the store."

**Actions:**
1. Navigate to: `http://localhost/ecommerce-chatbot/admin/index.php`
2. Login: admin@shop.com / password
3. Show dashboard metrics:
   - Total orders
   - Revenue
   - Customers
   - Products

**What to Say:**
> "The admin dashboard provides real-time insights into business performance."

---

#### **Admin Demo 1: Chatbot Logs (30 seconds)**
**Actions:**
1. Click on "Chatbot Logs"
2. Show conversation history
3. Show user queries and bot responses

**What to Say:**
> "Admins can review all chatbot interactions to improve responses and understand customer needs."

---

#### **Admin Demo 2: ML Performance (30 seconds)**
**Actions:**
1. Click on "ML Performance" or "Analytics"
2. Show accuracy charts
3. Show model comparison

**What to Say:**
> "This dashboard shows the machine learning model performance. As you can see, our best model achieves 95.68% accuracy, well above the 85% target."

**Point out:**
- Accuracy: 95.68%
- All 4 models comparison
- Confusion matrices
- Cross-validation results

---

#### **Admin Demo 3: Products Management (30 seconds)**
**Actions:**
1. Click on "Products"
2. Show product list
3. Show edit/add product functionality

**What to Say:**
> "Admins can easily manage the product catalog, add new products, update prices, and manage inventory."

---

### **Part 6: Conclusion (30 seconds)**

**What to Say:**
> "To summarize, this project demonstrates:
> 1. A fully functional e-commerce platform
> 2. An AI chatbot with 95.68% ML accuracy
> 3. Multilingual support for local markets
> 4. Real-time database integration
> 5. Comprehensive admin analytics
> 6. Enterprise-grade security
> 
> All objectives have been achieved successfully, and the system is production-ready."

---

## 🆘 Backup Plan (If Technical Issues)

### **If Flask API Not Working:**
- Chatbot will fall back to rule-based responses
- Say: "The rule-based system is still functional, but let me show the trained models..."
- Show ML performance charts from: `chatbot-ml/plots/`

### **If Database Not Connecting:**
- Show screenshots of working features
- Say: "Let me show you the recorded demo..."
- Play backup video if available

### **If Internet Issues:**
- All features work locally (no internet required)
- Only external images might not load
- Say: "The system is self-contained and works offline"

---

## 💡 Pro Tips for Demo

### **Before Starting:**
1. Clear browser cache
2. Close unnecessary tabs
3. Set browser to full screen (F11)
4. Test all features one more time
5. Have demo script printed or on phone

### **During Demo:**
1. Speak clearly and slowly
2. Don't rush - take your time
3. Explain what you're doing as you do it
4. Highlight key achievements (95.68% accuracy!)
5. Make eye contact with committee
6. Don't apologize for minor issues

### **If Something Breaks:**
1. Stay calm
2. Say: "Let me troubleshoot this quickly..."
3. Have backup screenshots ready
4. Move to next feature if needed
5. Don't panic - it happens to everyone!

---

## 📊 Key Numbers to Mention

- **95.68%** - Best ML model accuracy
- **35** - Intent categories trained
- **12,280** - Training samples
- **118+** - Products in catalog
- **19** - Database tables
- **13** - Advanced features
- **3** - Languages supported
- **< 2 seconds** - Response time
- **100-1,000** - Concurrent users capacity

---

## ✅ Post-Demo Actions

1. Thank the committee
2. Ask if they want to try the chatbot themselves
3. Be ready for questions
4. Have pen and paper to note their feedback
5. Stay confident and professional

---

**You've got this! Good luck! 🎓🚀**

---

*Demo Script Version: 1.0*  
*Created: April 2026*  
*Estimated Time: 5-7 minutes*
