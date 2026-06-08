# 🔮 Gemini API Quick Reference Card
**At-a-Glance Guide for Monitoring & Optimization**

---

## 📊 **DASHBOARD ACCESS**

```
URL: http://localhost/ecommerce-chatbot/admin/gemini_monitor.php
Auto-Refresh: Every 60 seconds
Time Ranges: 7, 14, 30, or 90 days
```

---

## 💰 **PRICING QUICK MATH**

| Usage/Day | Monthly Cost | Within Free Tier? |
|-----------|--------------|-------------------|
| 100 AI chats | ~$1.09 | ✅ YES |
| 500 AI chats | ~$5.44 | ✅ YES |
| 1,000 AI chats | ~$10.88 | ⚠️ AT LIMIT |
| 1,500 AI chats | ~$16.32 | ❌ NEEDS UPGRADE |
| 2,000 AI chats | ~$21.76 | ❌ NEEDS UPGRADE |

**Formula:** `(Chats × $0.0003625) × 30 days`

---

## 🆓 **FREE TIER LIMITS**

```
✅ 60 requests per minute (RPM)
✅ 1,000 requests per day (RPD)
✅ Resets at midnight Pacific Time
```

**Dashboard Alerts:**
- 🟢 <800/day = Safe zone
- 🟡 800-950/day = Warning zone  
- 🔴 >950/day = Critical zone

---

## 🎯 **KEY METRICS TO WATCH**

### **Daily Checks (30 seconds):**

1. **Total Conversations** → Overall usage
2. **AI Responses** → How much Gemini used
3. **Escalated Chats** → Negative sentiment cases
4. **Estimated Cost** → Running total

### **Weekly Reviews (5 minutes):**

1. **Chat Trend Chart** → Growth pattern
2. **Language Distribution** → User demographics
3. **Cost Projection** → Monthly forecast
4. **Top Queries** → What users ask most

---

## ⚡ **OPTIMIZATION CHEAT SHEET**

### **If Hitting Quota Too Often:**

```php
// 1. Enable stricter fallback
if ($dailyGeminiCount > 900) {
    define('GEMINI_DISABLED', true);
}

// 2. Cache common responses
$cached = getContext($sessionId, 'cache_' . md5($message));
if ($cached) return $cached['response'];

// 3. Reduce token usage
generationConfig => ['maxOutputTokens' => 500] // Down from 1000
```

### **If Costs Too High:**

```php
// 1. Only use Gemini for complex queries
if (strlen($message) < 30 || !$uid) {
    return usePHPRules(); // Save API calls
}

// 2. Shorten response length
system = "Be concise (max 150 words)"

// 3. Implement time-based throttling
if ($isPeakHour && $usage > threshold) {
    return usePHPRules();
}
```

---

## 🐛 **TROUBLESHOOTING FLOWCHART**

```
Bot not responding with AI?
├─ Check dashboard → AI Response count
│  ├─ If 0 or very low → Check API key in config/secrets.php
│  └─ If declining → Check quota usage
│
Getting 429 errors?
├─ Dashboard shows >950/day?
│  ├─ YES → Upgrade to paid tier OR optimize usage
│  └─ NO → Wait for quota reset (midnight PT)
│
Costs higher than expected?
├─ Check "Top Product Queries" table
│  ├─ Long messages? → Reduce maxOutputTokens
│  └─ Repeated questions? → Enable caching
```

---

## 📞 **QUICK LINKS**

| Resource | URL |
|----------|-----|
| **Dashboard** | `/admin/gemini_monitor.php` |
| **Google Console** | https://console.cloud.google.com/apis/api/generativelanguage.googleapis.com/quotas |
| **Billing** | https://console.cloud.google.com/billing |
| **API Docs** | https://ai.google.dev/docs |
| **Pricing** | https://ai.google.dev/pricing |

---

## 🎯 **HEALTHY CHATBOT CHECKLIST**

### **Green Flags ✅:**
- [ ] AI response rate: 20-40%
- [ ] Escalation rate: <5%
- [ ] Daily cost: <$0.50
- [ ] Quota usage: <80%
- [ ] User satisfaction: >80% positive

### **Red Flags 🚨:**
- [ ] AI response rate: >80% (overusing Gemini)
- [ ] Escalation rate: >10% (customer service issue)
- [ ] Quota usage: >90% (need upgrade)
- [ ] Cost projection: >$20/month (optimize needed)

---

## 💡 **PRO TIPS**

### **Save Money:**
1. Use PHP rules for simple queries (greetings, order tracking)
2. Cache responses for common questions
3. Reduce max output tokens to 500
4. Only use Gemini for logged-in users during peak hours

### **Improve Performance:**
1. Monitor language distribution → Improve multilingual support
2. Track sentiment trends → Identify pain points
3. Analyze top queries → Update FAQ/rule database
4. Review escalated chats → Train human agents

### **Stay Informed:**
1. Dashboard auto-refreshes every 60 seconds
2. Set up Google Cloud alerts at 90% quota
3. Check dashboard daily for first week
4. Export monthly data for accounting

---

## 📊 **SAMPLE ANALYSIS WORKFLOW**

### **Morning Check (2 minutes):**

```
1. Open dashboard
2. Check yesterday's metrics:
   - Total conversations: ___
   - AI responses: ___
   - Escalated chats: ___
   - Estimated cost: $___
3. Compare to previous day
4. Note any anomalies
```

### **Weekly Review (10 minutes):**

```
1. Select "Last 7 Days" filter
2. Analyze trends:
   - Is AI usage increasing?
   - Are costs within budget?
   - Any spike in escalations?
   - Which languages growing?
3. Plan optimizations if needed
4. Document findings
```

### **Monthly Report (30 minutes):**

```
1. Export data (CSV feature)
2. Calculate month-over-month growth
3. Compare projected vs actual costs
4. Identify top 10 queries
5. Present to team
6. Plan next month improvements
```

---

## 🎉 **YOU'RE ALL SET!**

Your monitoring dashboard provides everything needed to:
- ✅ Track real-time usage and costs
- ✅ Stay within free tier limits
- ✅ Optimize AI performance
- ✅ Understand user behavior
- ✅ Make data-driven decisions

**Bookmark this page + dashboard for quick reference!**

---

**Quick Test Commands:**

```sql
-- Today's usage so far
SELECT COUNT(*) FROM chatbot_logs WHERE DATE(created_at) = CURDATE();

-- AI responses today
SELECT COUNT(*) FROM chatbot_logs 
WHERE DATE(created_at) = CURDATE() 
AND response LIKE '%🤖 ML:%';

-- Escalation rate this week
SELECT ROUND(SUM(escalated)/COUNT(*)*100, 2) as escalation_pct 
FROM chatbot_logs 
WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY);
```

---

**Created:** April 3, 2026  
**Last Updated:** April 3, 2026  
**Version:** 1.0  

Keep this card handy for quick reference! 📌
