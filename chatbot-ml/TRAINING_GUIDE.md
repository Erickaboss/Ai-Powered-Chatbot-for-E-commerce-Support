# 🤖 Comprehensive Chatbot Training Guide

## Overview

This guide explains how to train your chatbot to handle ALL intents from your dataset and database, using both ML models and Google Gemini API for intelligent responses.

---

## 📋 What You Have

### 1. **Dataset Files**
- `intents.json` - 68.6KB with multiple intent categories
- `intents_part2.json` - Additional intents
- `dataset.csv` - Training data in CSV format

### 2. **Database Integration**
- Products table (with names, descriptions, prices, categories)
- Categories table
- FAQ table
- Users & Orders tables (for personalized context)

### 3. **Gemini API**
- Already configured in `config/db.php`
- Used for complex queries and multilingual support
- Falls back to ML model when quota exceeded

---

## 🚀 Step-by-Step Training Process

### Step 1: Install Python Dependencies

```bash
cd c:\xampp\htdocs\ecommerce-chatbot\chatbot-ml
pip install -r requirements.txt
```

**Required packages:**
```txt
pandas==2.0.3
numpy==1.24.3
scikit-learn==1.3.0
mysql-connector-python==8.1.0
tensorflow==2.13.0
flask==3.0.0
requests==2.31.0
```

### Step 2: Run Comprehensive Training

```bash
python train_comprehensive.py
```

**What this does:**
1. ✅ Loads all intents from JSON files
2. ✅ Fetches products from database
3. ✅ Loads FAQs from database
4. ✅ Creates training data combining all sources
5. ✅ Trains 4 different ML models:
   - Logistic Regression
   - Random Forest
   - MLP Neural Network
   - SVM (RBF kernel)
6. ✅ Saves best performing model
7. ✅ Generates detailed performance report

### Step 3: Deploy Trained Models

After training completes, copy models to the API directory:

```bash
# Copy trained models to API folder
xcopy /Y chatbot-ml\models\*.pkl api\models\
xcopy /Y chatbot-ml\models\*.json api\models\
```

**Files to copy:**
- `tfidf_vectorizer.pkl`
- `label_encoder.pkl`
- `best_model_name.pkl` (e.g., `logistic_regression.pkl`)
- `model_metadata.json`

### Step 4: Test the Chatbot

1. Open browser: `http://localhost/ecommerce-chatbot/index.php`
2. Test various queries:
   - **Greetings:** "Mwiriwe", "Hello", "Bonjour"
   - **Product search:** "Show me wireless headphones"
   - **Category browse:** "Browse electronics"
   - **Price queries:** "Headphones under 50000 RWF"
   - **FAQs:** "What's your return policy?"
   - **Personal info:** "Show my orders" (requires login)

---

## 🎯 How It Works

### Intent Detection Flow

```
User Message
    ↓
[1] Check if simple greeting/thanks/goodbye → Return canned response
    ↓
[2] Run ML model prediction → Get intent + confidence
    ↓
[3] Confidence > 70%? → Use database-grounded response
    ↓
[4] Complex query? → Call Gemini API with DB context
    ↓
[5] Gemini fails? → Fallback to ML response
```

### Gemini API Intelligence

The chatbot sends Gemini:
- ✅ **Product catalog** from database (NEVER invents products)
- ✅ **Category information** with price ranges
- ✅ **Customer context** (name, order history if logged in)
- ✅ **Conversation history** (last 12 messages)
- ✅ **Store policies** (shipping, returns, payment)

**System Prompt Example:**
```
You are the AI shopping assistant for "AI-Powered Chatbot For E-commerce Support".

CRITICAL RULES:
- ONLY recommend products from the PRODUCTS FROM DATABASE section
- Always show prices in RWF exactly as listed
- Respond in the SAME language the customer uses
- Never place orders or collect payment details
```

---

## 📊 Supported Intents

Your dataset includes these intent categories:

### Basic Conversational
1. `greeting` - Hello, Hi, Mwiriwe, etc.
2. `goodbye` - Bye, See you, etc.
3. `thanks` - Thank you, Thanks, etc.
4. `affirmation` - Yes, Correct, etc.
5. `denial` - No, Wrong, etc.

### E-commerce Specific
6. `product_search` - Find specific products
7. `category_search` - Browse categories
8. `price_check` - How much, Price of
9. `availability` - Do you have, In stock
10. `order_status` - Where is my order
11. `delivery_info` - Shipping time, Delivery
12. `return_policy` - Returns, Refunds
13. `payment_methods` - How to pay, Payment options

### Personal (Requires Login)
14. `my_profile` - My account, Profile info
15. `my_orders` - My order history
16. `update_details` - Change email/password

### FAQ
17. `faq` - Frequently asked questions

---

## 🔧 Advanced Configuration

### Adjust Confidence Threshold

Edit `api/chatbot.php`:

```php
// Line ~1600
if ($confidence >= 0.70) {  // Change this value (0.5 - 0.95)
    // Use ML response
} else {
    // Use Gemini API
}
```

### Enable/Disable Gemini

```php
// In config/db.php
define('GEMINI_API_KEY', 'your-api-key');  // Set key to enable
// define('GEMINI_API_KEY', '');  // Comment to disable
```

### Add New Intents

1. Edit `chatbot-ml/dataset/intents.json`:
```json
{
  "tag": "new_intent_name",
  "patterns": [
    "example pattern 1",
    "example pattern 2"
  ],
  "responses": [
    "Response to show user"
  ]
}
```

2. Re-run training: `python train_comprehensive.py`

---

## 📈 Performance Monitoring

### View Training Report

Open: `chatbot-ml/reports/comprehensive_training_report.txt`

**Metrics included:**
- Model accuracy
- Cross-validation scores
- Number of classes supported
- Training data sources used

### Check Chatbot Logs

Admin Panel → Chatbot Analytics
- View all conversations
- See intent predictions
- Monitor confidence scores
- Track Gemini API usage

---

## 🐛 Troubleshooting

### Problem: "No syntax errors" but chatbot still fails

**Solution:**
1. Check Apache error log: `C:\xampp\apache\logs\error.log`
2. Look for recent PHP errors
3. Restart Apache if needed

### Problem: Models not found

**Solution:**
```bash
# Verify models exist
dir chatbot-ml\models\*.pkl

# Copy to API folder
xcopy /Y chatbot-ml\models\* api\models\
```

### Problem: Gemini API quota exceeded

**Solution:**
- Chatbot automatically falls back to ML model
- No action needed - works seamlessly
- Consider upgrading API quota if frequent

### Problem: Low confidence scores

**Solution:**
1. Add more training patterns to intents
2. Include more diverse phrasings
3. Add Kinyarwanda/French variations
4. Re-train model

---

## 🎓 Best Practices

### 1. **Regular Retraining**
- Retrain weekly with new conversation logs
- Add failed queries as new training data
- Monitor low-confidence predictions

### 2. **Expand Dataset**
- Collect real user queries from logs
- Add seasonal intents (Black Friday, Christmas)
- Include product-specific FAQs

### 3. **Optimize Gemini Usage**
- Use ML for simple intents (greetings, thanks)
- Reserve Gemini for complex product queries
- Cache common Gemini responses

### 4. **Multilingual Support**
- Include patterns in English, French, Kinyarwanda
- Test responses in all languages
- Ensure proper encoding (UTF-8)

---

## 📝 Example Training Session Output

```
======================================================================
COMPREHENSIVE CHATBOT TRAINING SYSTEM
======================================================================
Started: 2026-04-03 15:30:45

🔍 Loading training data...
✅ Loaded intents from intents.json
✅ Loaded intents from intents_part2.json
📊 Loaded 850 intent patterns across 17 categories
✅ Loaded 449 products from database
✅ Loaded 25 FAQs from database
📊 Total training samples: 1523
📊 Total unique intents: 17

🔄 Preprocessing data...
   Training samples: 1218
   Test samples: 305

🚀 Training models...

Training Logistic Regression...
  ✅ Accuracy: 0.9541
  ✅ CV Score: 0.9487 (+/- 0.0123)

Training Random Forest...
  ✅ Accuracy: 0.9377
  ✅ CV Score: 0.9312 (+/- 0.0156)

Training MLP Neural Network...
  ✅ Accuracy: 0.9639
  ✅ CV Score: 0.9578 (+/- 0.0098)

Training SVM (RBF)...
  ✅ Accuracy: 0.9508
  ✅ CV Score: 0.9445 (+/- 0.0134)

💾 Saving best model: MLP Neural Network
  ✅ Model saved: mlp_neural_network.pkl
  ✅ Vectorizer saved
  ✅ Label encoder saved
  ✅ Metadata saved

======================================================================
BEST MODEL: MLP Neural Network
Accuracy: 96.39%
Classes: 17
======================================================================
```

---

## ✅ Success Checklist

- [ ] Python dependencies installed
- [ ] Training script runs without errors
- [ ] Models copied to `api/models/`
- [ ] Chatbot responds to greetings
- [ ] Product search works
- [ ] Multilingual support tested
- [ ] Admin analytics shows predictions
- [ ] No PHP errors in logs

---

## 🚀 Next Steps

1. **Monitor Performance**: Check analytics daily
2. **Collect Feedback**: Add thumbs up/down to responses
3. **A/B Testing**: Try different confidence thresholds
4. **Expand Knowledge**: Add more product attributes
5. **Voice Input**: Integrate speech-to-text

---

**Need Help?**  
Contact: [ADMIN_EMAIL](mailto:ADMIN_EMAIL)  
Support: [ADMIN_PHONE](tel:ADMIN_PHONE)
