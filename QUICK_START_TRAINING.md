# 🚀 Quick Start - Chatbot Training

## ⚡ 3-Step Setup

### Step 1: Install Python Dependencies
```bash
cd c:\xampp\htdocs\ecommerce-chatbot
pip install pandas numpy scikit-learn mysql-connector-python requests
```

### Step 2: Run Training
```bash
train_chatbot.bat
```

**OR manually:**
```bash
cd chatbot-ml
python train_comprehensive.py
```

### Step 3: Test
Open: http://localhost/ecommerce-chatbot/index.php

---

## 📋 What You Get

After training:
- ✅ ML model trained on 850+ patterns
- ✅ 449 products from database
- ✅ 25+ FAQs integrated
- ✅ 17 intent categories
- ✅ Gemini API ready for complex queries
- ✅ Multilingual support (EN/FR/KW)

---

## 🎯 Test Queries

Try these in the chatbot:

```
👋 Greetings:
- "Hello"
- "Mwiriwe"
- "Bonjour"

🛍️ Product Search:
- "Show me wireless headphones"
- "Laptops under 500000 RWF"
- "Browse electronics"

❓ Questions:
- "What's your return policy?"
- "How long does delivery take?"
- "Do you have payment plans?"

👤 Personal (login required):
- "Show my profile"
- "Where is my order?"
- "Update my email"
```

---

## 📊 Expected Output

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

Training MLP Neural Network...
  ✅ Accuracy: 0.9639 ⭐ BEST

💾 Saving best model: MLP Neural Network
  ✅ Model saved: mlp_neural_network.pkl

======================================================================
✅ TRAINING COMPLETE!
======================================================================
```

---

## 🔧 Troubleshooting

### Error: "Python not found"
**Fix:** Install Python from https://www.python.org/downloads/

### Error: "Module not found"
**Fix:** 
```bash
pip install pandas numpy scikit-learn mysql-connector-python
```

### Error: "Database connection failed"
**Fix:**
1. Start MySQL in XAMPP
2. Verify database exists: `ecommerce_chatbot`

### Chatbot returns "Something went wrong"
**Check:** Apache error log at `C:\xampp\apache\logs\error.log`

---

## 📁 Files Created

```
chatbot-ml/
├── train_comprehensive.py       ⭐ Main training script
├── TRAINING_GUIDE.md            ⭐ Full documentation
├── ARCHITECTURE.md              ⭐ System design
├── models/                      ⭐ Trained models
│   ├── tfidf_vectorizer.pkl
│   ├── label_encoder.pkl
│   └── mlp_neural_network.pkl
└── reports/                     ⭐ Performance reports
    └── comprehensive_training_report.txt

Root:
├── train_chatbot.bat            ⭐ One-click training
└── COMPREHENSIVE_TRAINING_SUMMARY.md  ⭐ Implementation summary
```

---

## 🎓 Key Concepts

### Intent Detection
User says "Mwiriwe" → ML predicts "greeting" → Show welcome message

### Database Grounding
User asks "headphones" → Query products table → Return REAL products only

### Gemini Integration
Complex query → Send context to Gemini → Get intelligent response

### Confidence Threshold
High confidence (>70%) → Use ML response  
Low confidence (<70%) → Call Gemini API

---

## 📞 Need Help?

1. **Check logs:** `C:\xampp\apache\logs\error.log`
2. **View report:** `chatbot-ml\reports\comprehensive_training_report.txt`
3. **Test Gemini:** https://makersuite.google.com/app/apikey

---

## ✅ Success Checklist

- [ ] XAMPP running (Apache + MySQL)
- [ ] Python 3.8+ installed
- [ ] Dependencies installed
- [ ] Training completes without errors
- [ ] Models copied to `api/models/`
- [ ] Chatbot responds to "Hello"
- [ ] Product search works
- [ ] No PHP errors in logs

---

## 🎉 You're Ready!

Run: `train_chatbot.bat`  
Then test at: http://localhost/ecommerce-chatbot/index.php

**Happy chatting!** 💬
