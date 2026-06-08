# Multilingual Chatbot - Verification Checklist

## ✅ Pre-Deployment Verification

### Code Changes
- [x] Error logging added to exception handlers
- [x] Database table auto-creation implemented
- [x] bind_param type string corrected
- [x] $snapshot variable initialized in processMessage
- [x] saveContext calls wrapped in try-catch
- [x] No syntax errors in chatbot.php
- [x] Language detector properly included

### Database
- [x] chatbot_context table will be auto-created on first request
- [x] Table has proper indexes and constraints
- [x] Foreign key relationship to users table

### Language Detection
- [x] English detector working (hello, hi, hey, etc.)
- [x] French detector working (bonjour, merci, etc.)
- [x] Kinyarwanda detector working (mwaramutse, muraho, etc.)

## 🧪 Testing Checklist

### Test 1: Simple English Greeting
```
Step 1: Open chatbot
Step 2: Type "hello"
Step 3: Verify response is in English
Expected: "👋 Welcome back..." or "🤖 AI Shopping Assistant..."
Status: [ ] PASS [ ] FAIL
```

### Test 2: French Greeting
```
Step 1: Open chatbot (new session)
Step 2: Type "bonjour"
Step 3: Verify response is in French
Expected: "🇫🇷 Bonjour..." or "Je suis connecté..."
Status: [ ] PASS [ ] FAIL
```

### Test 3: Kinyarwanda Greeting
```
Step 1: Open chatbot (new session)
Step 2: Type "mwaramutse"
Step 3: Verify response is in Kinyarwanda
Expected: "🇷🇼 Muraho..." or "Nkomeretswe..."
Status: [ ] PASS [ ] FAIL
```

### Test 4: Error Logging
```
Step 1: Check server error logs
Step 2: Look for CHATBOT EXCEPTION entries
Expected: No CHATBOT EXCEPTION errors
Status: [ ] PASS [ ] FAIL
```

### Test 5: Database Persistence
```
Step 1: Send a message in French
Step 2: Query database: SELECT * FROM chatbot_context WHERE context_key='language';
Step 3: Verify language='french' is saved
Expected: Row with language='french'
Status: [ ] PASS [ ] FAIL
```

### Test 6: Multiple Languages in Sequence
```
Step 1: Send "hello" (English)
Step 2: Send "bonjour" (French)
Step 3: Send "mwaramutse" (Kinyarwanda)
Step 4: Verify each response is in correct language
Expected: 3 responses in correct languages
Status: [ ] PASS [ ] FAIL
```

### Test 7: Product Search in Different Languages
```
Step 1: Send "show me phones" (English)
Step 2: Send "montrez-moi des téléphones" (French)
Step 3: Send "nyereka telefoni" (Kinyarwanda)
Step 4: Verify products are shown in each language
Expected: Product lists in correct languages
Status: [ ] PASS [ ] FAIL
```

### Test 8: Error Handling
```
Step 1: Send a very long message (>5000 chars)
Step 2: Send special characters
Step 3: Send empty message
Step 4: Verify chatbot handles gracefully
Expected: No "Something went wrong" errors
Status: [ ] PASS [ ] FAIL
```

## 📊 Performance Checklist

### Response Time
- [ ] English greeting: < 500ms
- [ ] French greeting: < 500ms
- [ ] Kinyarwanda greeting: < 500ms
- [ ] Product search: < 1000ms

### Database
- [ ] chatbot_context table created successfully
- [ ] Language entries saved correctly
- [ ] No database errors in logs

### Error Logging
- [ ] Error logs are being written
- [ ] No CHATBOT EXCEPTION entries
- [ ] No PHP warnings or notices

## 🔍 Debugging Checklist

### If Tests Fail

#### Test 1-3 Fail (Greetings not working)
- [ ] Check if language_detector_simple.php is included
- [ ] Verify detect_language() function exists
- [ ] Check server error logs for exceptions
- [ ] Run: `php debug_chatbot.php`

#### Test 4 Fails (No error logging)
- [ ] Check PHP error_log configuration
- [ ] Verify error_reporting is set correctly
- [ ] Check file permissions on error log
- [ ] Verify error_log path is writable

#### Test 5 Fails (Database not saving)
- [ ] Check if chatbot_context table exists
- [ ] Verify database connection
- [ ] Check database user permissions
- [ ] Run: `php debug_chatbot.php`

#### Test 6-7 Fail (Language switching issues)
- [ ] Check if language is being detected correctly
- [ ] Verify context is being saved
- [ ] Check if language is being retrieved
- [ ] Review error logs

#### Test 8 Fails (Error handling)
- [ ] Check exception handler is working
- [ ] Verify error handler is working
- [ ] Check if errors are being logged
- [ ] Review error logs for details

## 📋 Final Verification

### Before Going Live
- [ ] All 8 tests pass
- [ ] No errors in server logs
- [ ] Database table exists and has entries
- [ ] Performance is acceptable
- [ ] Error logging is working
- [ ] Language detection is accurate

### After Deployment
- [ ] Monitor error logs for 24 hours
- [ ] Test with real users
- [ ] Verify language detection accuracy
- [ ] Check database for context entries
- [ ] Monitor response times
- [ ] Verify no breaking changes

## 🚀 Deployment Approval

- [ ] All tests passed
- [ ] Code review completed
- [ ] Database migration verified
- [ ] Error logging configured
- [ ] Performance acceptable
- [ ] Ready for production

**Approved by**: ________________  
**Date**: ________________  
**Notes**: ________________________________________________

---

## Quick Debug Commands

### Check Error Logs
```bash
tail -f /var/log/php-errors.log | grep CHATBOT
```

### Test Language Detection
```bash
php debug_chatbot.php
```

### Check Database
```sql
SELECT * FROM chatbot_context LIMIT 10;
SELECT COUNT(*) FROM chatbot_context;
```

### Verify Table Exists
```sql
SHOW TABLES LIKE 'chatbot_context';
DESCRIBE chatbot_context;
```

---

**Status**: Ready for verification and deployment
