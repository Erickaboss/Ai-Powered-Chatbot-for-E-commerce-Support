# 🔮 Google Gemini API Monitoring & Optimization Guide
**Track Usage, Costs, and Performance**  
*April 3, 2026*

---

## 📊 **NEW MONITORING DASHBOARD CREATED**

### **Location:**
```
http://localhost/ecommerce-chatbot/admin/gemini_monitor.php
```

### **What It Tracks:**

1. ✅ **Total Conversations** - All chatbot interactions
2. ✅ **AI-Powered Responses** - Gemini-generated replies
3. ✅ **Estimated Costs** - Real-time cost calculation
4. ✅ **Escalated Chats** - Negative sentiment cases
5. ✅ **Daily Trends** - Chat activity over time
6. ✅ **Language Distribution** - English/French/Kinyarwanda usage
7. ✅ **Sentiment Analysis** - Positive/Neutral/Negative breakdown
8. ✅ **Cost Projections** - Monthly cost estimates
9. ✅ **Top Product Queries** - Most asked questions
10. ✅ **Free Tier Status** - Are you within limits?

---

## 🎯 **HOW TO USE THE DASHBOARD**

### **Step 1: Open Dashboard**
```
1. Login as admin
2. Go to: /admin/gemini_monitor.php
3. Select time range (7/14/30/90 days)
```

### **Step 2: Check Key Metrics**

**Total Conversations:**
- Shows overall chatbot usage
- Higher = more active users
- Track growth over time

**AI-Powered Responses:**
- Count of Gemini-generated replies
- Does NOT include simple PHP rule responses
- Look for ~20-40% AI usage (healthy balance)

**Estimated Cost:**
- Calculated from average message/response lengths
- Based on Google's pricing:
  - Input: $0.000125 per 1K characters
  - Output: $0.000375 per 1K characters
- Updated every 60 seconds (auto-refresh)

**Escalated Chats:**
- Negative sentiment cases needing human review
- Should be <5% of total chats
- Click count to see details in chatbot_logs

---

## 💰 **UNDERSTANDING COSTS**

### **Current Pricing (Google Gemini):**

| Component | Rate | Example |
|-----------|------|---------|
| **Input** | $0.000125 / 1K chars | 500 chars = $0.0000625 |
| **Output** | $0.000375 / 1K chars | 800 chars = $0.0003 |
| **Total per conversation** | ~$0.0003625 | Average query |

### **Cost Examples:**

**Scenario A: Light Usage (100 AI conversations/day)**
```
100 conversations × $0.0003625 = $0.036/day
Monthly: ~$1.09
Well within free tier (1,000/day)!
```

**Scenario B: Medium Usage (500 AI conversations/day)**
```
500 conversations × $0.0003625 = $0.181/day
Monthly: ~$5.44
Still within free tier!
```

**Scenario C: Heavy Usage (1,500 AI conversations/day)**
```
1,500 conversations × $0.0003625 = $0.544/day
First 1,000 FREE, then 500 paid
Monthly: ~$16.32 (paid portion only)
```

---

## 🆓 **FREE TIER LIMITS**

### **Google Gemini Free Quota:**

| Limit | Value | Reset Period |
|-------|-------|--------------|
| **Requests Per Minute** | 60 RPM | Every minute |
| **Requests Per Day** | 1,000 RPD | Daily at midnight PT |
| **Max Tokens/Request** | 32,768 input<br>8,192 output | Per request |

### **Dashboard Alerts:**

The dashboard shows colored alerts based on your usage:

✅ **Green** (<800/day): Well within free tier  
⚠️ **Yellow** (800-950/day): Approaching limit  
🚨 **Red** (>950/day): At capacity, upgrade recommended

---

## 📈 **OPTIMIZATION STRATEGIES**

### **Strategy 1: Smart Fallback (Already Implemented)**

Your bot automatically:
- Uses PHP rules for simple queries (greetings, order tracking)
- Only calls Gemini for complex questions
- Falls back when quota hit

**Result:** Saves ~60-70% of API calls

---

### **Strategy 2: Cache Common Responses**

Add response caching to avoid repeat calls:

```php
// In api/chatbot.php, before calling Gemini:
$cachedResponse = getContext($sessionId, 'cache_' . md5($message));
if ($cachedResponse && strtotime($cachedResponse['created_at']) > time() - 3600) {
    return $cachedResponse['response']; // Use cached (1 hour old max)
}

// After getting Gemini response:
saveContext($sessionId, $userId, 'cache_' . md5($message), $geminiResponse);
```

**Savings:** ~20-30% for repeated questions

---

### **Strategy 3: Reduce Token Usage**

Current prompt sends ~1,500-2,000 tokens. Optimize by:

**Before:**
```php
// Sends last 12 conversation turns
$history = getLast(12); // Too many!
```

**After:**
```php
// Send only last 6 turns + summary
$history = getLast(6);
$summary = getSummary(); // Compress older context
```

**Savings:** ~30-40% token reduction

---

### **Strategy 4: Time-Based Throttling**

During peak hours, be more selective:

```php
$isPeakHour = (date('H') >= 10 && date('H') <= 14); // 10am-2pm

if ($isPeakHour && $dailyGeminiCount > 800) {
    // Use PHP rules instead of Gemini
    return usePHPRules($message);
} else {
    return callGemini($message);
}
```

**Benefit:** Stays within daily quota

---

## 🔧 **QUOTA MANAGEMENT**

### **Monitor Your Quota:**

**Google Cloud Console:**
```
https://console.cloud.google.com/apis/api/generativelanguage.googleapis.com/quotas
```

**Check:**
- Requests per minute (current vs limit)
- Requests per day (current vs limit)
- Error rates (429 = quota exceeded)

### **Set Up Alerts:**

1. Go to Google Cloud Console
2. Navigate to "Monitoring" → "Alerting"
3. Create alert policy:
   - Metric: `Generative Language API > Requests per day`
   - Threshold: 900 (90% of 1,000)
   - Notification: Email when exceeded

---

## 🐛 **TROUBLESHOOTING**

### **Issue: Getting 429 Errors (Quota Exceeded)**

**Symptoms:**
- Bot responds with basic template answers
- No "🤖 ML:" tag in responses
- Dashboard shows high usage

**Immediate Solutions:**

1. **Wait for reset:**
   - Per-minute quota resets every 60 seconds
   - Per-day quota resets at midnight Pacific Time

2. **Check dashboard:**
   - See current usage vs limits
   - Identify spike patterns

3. **Enable stricter fallback:**
```php
// In api/chatbot.php
if ($dailyGeminiCount > 950) {
    // Force PHP mode for rest of day
    define('GEMINI_DISABLED', true);
}
```

**Long-term Solution:**
- Upgrade to paid tier (~$2-20/month depending on usage)
- Implement caching strategy
- Optimize prompts to use fewer tokens

---

### **Issue: Costs Higher Than Expected**

**Diagnosis:**

1. Check dashboard → "Cost Projection"
2. Review "Top Product Queries" table
3. Identify expensive queries (long messages/responses)

**Solutions:**

1. **Shorten responses:**
```php
// In system prompt
generationConfig => [
    'maxOutputTokens' => 500, // Reduce from 1000
    'temperature' => 0.2
]
```

2. **Filter low-value queries:**
```php
// Only use Gemini for logged-in users or complex queries
if (!$uid || strlen($message) < 20) {
    return usePHPRules(); // Save API calls
}
```

3. **Batch similar queries:**
```php
// Cache responses for common questions
$commonQuestions = ['where is my order', 'track order', 'delivery status'];
if (in_array(strtolower($message), $commonQuestions)) {
    return getCachedResponse('order_tracking');
}
```

---

## 📊 **DASHBOARD FEATURES GUIDE**

### **Time Range Selector:**

**Last 7 Days:** Good for recent trends  
**Last 14 Days:** Mid-term patterns  
**Last 30 Days:** Monthly overview  
**Last 90 Days:** Long-term trends

Changes affect all charts and metrics displayed.

---

### **Key Metrics Explained:**

**"AI-Powered Responses":**
- Counts responses with "🤖 ML:" tag OR sentiment_score set
- Includes both Gemini AND ML model responses
- Excludes simple PHP rule-based replies

**"Estimated Cost":**
- Calculated using averages (500 chars input, 800 chars output)
- Actual costs may vary ±20%
- Does NOT include free tier (first 1,000/day free)

**"Projected Monthly":**
- Based on current daily average
- Multiplied by 30 days
- Assumes consistent usage pattern

**"Escalated Chats":**
- Messages with negative sentiment AND escalation flag
- Indicates customers needing human intervention
- Should trigger support ticket creation

---

### **Charts Interpretation:**

**Daily Chat Activity Chart:**
- Blue line = Total conversations
- Green line = AI-powered responses
- Gap between lines = PHP rule responses
- Ideal: Green follows blue pattern (good AI adoption)

**Language Distribution:**
- Shows which languages customers use
- Helps plan multilingual improvements
- Kinyarwanda detection still improving

**Sentiment Distribution:**
- Green bars = Positive messages
- Orange bars = Neutral messages
- Red bars = Negative messages
- Healthy ratio: 60% positive, 30% neutral, 10% negative

---

## 🎯 **BEST PRACTICES**

### **Daily Checks:**
- [ ] Review dashboard for quota usage
- [ ] Check escalated chats count
- [ ] Monitor cost projection
- [ ] Scan top queries for patterns

### **Weekly Reviews:**
- [ ] Analyze week-over-week growth
- [ ] Identify most expensive queries
- [ ] Review language distribution changes
- [ ] Plan optimizations based on data

### **Monthly Actions:**
- [ ] Export data for accounting
- [ ] Compare projected vs actual Google bill
- [ ] Adjust optimization strategies
- [ ] Update team on chatbot performance

---

## 💡 **ADVANCED FEATURES**

### **Custom Alert System:**

Add email alerts when approaching quota:

```php
// In gemini_monitor.php
if ($geminiResponses / $days > 900) {
    mail(ADMIN_EMAIL, 
         '⚠️ Gemini Quota Alert', 
         'Daily usage at ' . round(($geminiResponses/$days)/10).'% of free tier limit!');
}
```

### **Export Functionality:**

Add CSV export button:

```php
// Add to dashboard header
<a href="export_gemini_data.php?days=<?= $days ?>" class="btn btn-outline-success">
    <i class="bi bi-download me-1"></i>Export CSV
</a>
```

### **Predictive Analytics:**

Forecast when you'll hit quota:

```php
$currentRate = $geminiResponses / $days;
$daysUntilLimit = 1000 / $currentRate;
echo "At current rate, you'll hit daily limit in " . round($daysUntilLimit, 1) . " days";
```

---

## 📞 **SUPPORT RESOURCES**

### **Google Cloud Console:**
- Quotas: https://console.cloud.google.com/apis/api/generativelanguage.googleapis.com/quotas
- Billing: https://console.cloud.google.com/billing
- API Dashboard: https://console.cloud.google.com/apis/dashboard

### **Internal Documentation:**
- `MULTILINGUAL_SUPPORT_GUIDE.md` - Language handling
- `ADVANCED_CHATBOT_FEATURES_COMPLETE.md` - AI features
- `DEPLOYMENT_GUIDE.md` - Deployment instructions

### **Quick SQL Queries:**

Check today's usage:
```sql
SELECT COUNT(*) as today_chats 
FROM chatbot_logs 
WHERE DATE(created_at) = CURDATE();
```

Check AI responses:
```sql
SELECT COUNT(*) as ai_responses 
FROM chatbot_logs 
WHERE DATE(created_at) = CURDATE()
AND (response LIKE '%🤖 ML:%' OR sentiment_score IS NOT NULL);
```

Check escalation rate:
```sql
SELECT 
    COUNT(*) as total,
    SUM(escalated) as escalated_count,
    ROUND(SUM(escalated)/COUNT(*)*100, 2) as escalation_pct
FROM chatbot_logs 
WHERE DATE(created_at) = CURDATE();
```

---

## ✅ **SUCCESS CRITERIA**

### **Healthy Chatbot Indicators:**

| Metric | Target | Status |
|--------|--------|--------|
| AI Response Rate | 20-40% | ✅ Optimal |
| Escalation Rate | <5% | ✅ Good |
| Daily Cost | <$0.50 | ✅ Budget-friendly |
| Quota Usage | <80% | ✅ Safe zone |
| User Satisfaction | >80% positive | ✅ Excellent |

### **Warning Signs:**

🚨 AI response rate >80% → Using too much Gemini  
🚨 Escalation rate >10% → Customer service issue  
🚨 Quota usage >90% → Need to upgrade or optimize  
🚨 Cost projection >$20/month → Optimization needed  

---

## 🎉 **CONCLUSION**

Your monitoring dashboard provides:
- ✅ Real-time usage tracking
- ✅ Cost estimation and projection
- ✅ Quota management alerts
- ✅ Performance analytics
- ✅ Language insights
- ✅ Sentiment monitoring

**Updated:** April 3, 2026  
**Dashboard Location:** `/admin/gemini_monitor.php`  
**Auto-refresh:** Every 60 seconds

**You're now fully equipped to monitor and optimize your Gemini API usage!** 🚀

For questions or enhancements, check the code comments in `admin/gemini_monitor.php`.
