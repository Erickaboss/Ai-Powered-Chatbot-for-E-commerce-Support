# Deployment Checklist: Advanced Chatbot Features

## Pre-Deployment Verification

### Database
- [ ] `database_enhancements.sql` exists
- [ ] MySQL connection working
- [ ] Database `ecommerce_chatbot` exists
- [ ] User has CREATE TABLE permissions

### API Files
- [ ] `api/upload.php` exists and is readable
- [ ] `api/gemini_vision_processor.php` exists and is readable
- [ ] `api/product_matcher.php` exists and is readable
- [ ] `api/get_upload_results.php` exists and is readable
- [ ] `api/process_upload_queue.php` exists and is readable
- [ ] `api/apply_migrations.php` exists and is readable

### Frontend Files
- [ ] `assets/js/upload-handler.js` exists and is readable
- [ ] `assets/css/upload.css` exists and is readable

### Admin Files
- [ ] `admin/ml_performance_enhanced.php` exists and is readable

### Include Files
- [ ] `includes/metrics_collector.php` exists and is readable

### ML Files
- [ ] `chatbot-ml/build_comprehensive_dataset.py` exists
- [ ] Python 3 installed
- [ ] mysql-connector-python installed

### Documentation
- [ ] `IMPLEMENTATION_GUIDE_DETAILED.md` exists
- [ ] `COMPREHENSIVE_ENHANCEMENT_PLAN.md` exists
- [ ] `IMPLEMENTATION_COMPLETE.md` exists
- [ ] `QUICK_START_DEPLOYMENT.md` exists
- [ ] `DEPLOYMENT_CHECKLIST.md` exists (this file)

## Deployment Steps

### Step 1: Database Setup
- [ ] Run `php api/apply_migrations.php` or `mysql < database_enhancements.sql`
- [ ] Verify tables created: `mysql -e "SHOW TABLES LIKE '%upload%';"`
- [ ] Verify views created: `mysql -e "SHOW FULL TABLES WHERE TABLE_TYPE LIKE 'VIEW';"`
- [ ] Verify indexes created: `mysql -e "SHOW INDEXES FROM chat_uploads;"`

### Step 2: Environment Configuration
- [ ] Create `.env` file in project root
- [ ] Set `GEMINI_API_KEY` with valid API key
- [ ] Set `GEMINI_VISION_MODEL=gemini-2.0-flash`
- [ ] Set `UPLOAD_MAX_SIZE=10485760`
- [ ] Set `UPLOAD_TEMP_DIR=/tmp/chatbot_uploads`
- [ ] Set `UPLOAD_STORAGE_DIR=/var/storage/chatbot_uploads`
- [ ] Verify `.env` is readable by web server

### Step 3: Directory Setup
- [ ] Create `/tmp/chatbot_uploads` directory
- [ ] Create `/var/storage/chatbot_uploads` directory
- [ ] Set permissions: `chmod 755` on both directories
- [ ] Verify web server can write to directories
- [ ] Test: `touch /tmp/chatbot_uploads/test.txt` (should succeed)

### Step 4: API Deployment
- [ ] Copy `api/upload.php` to web root
- [ ] Copy `api/gemini_vision_processor.php` to web root
- [ ] Copy `api/product_matcher.php` to web root
- [ ] Copy `api/get_upload_results.php` to web root
- [ ] Copy `api/process_upload_queue.php` to web root
- [ ] Set permissions: `chmod 644` on all PHP files
- [ ] Test: `curl http://localhost/api/upload.php` (should return JSON)

### Step 5: Frontend Deployment
- [ ] Copy `assets/js/upload-handler.js` to web root
- [ ] Copy `assets/css/upload.css` to web root
- [ ] Set permissions: `chmod 644` on both files
- [ ] Verify files are accessible: `curl http://localhost/assets/js/upload-handler.js`

### Step 6: Admin Dashboard Deployment
- [ ] Copy `admin/ml_performance_enhanced.php` to web root
- [ ] Set permissions: `chmod 644`
- [ ] Test: `curl http://localhost/admin/ml_performance_enhanced.php`
- [ ] Verify dashboard loads in browser

### Step 7: Metrics Collector Deployment
- [ ] Copy `includes/metrics_collector.php` to web root
- [ ] Set permissions: `chmod 644`
- [ ] Verify file is readable by PHP

### Step 8: Cron Job Setup
- [ ] Edit crontab: `crontab -e`
- [ ] Add: `*/5 * * * * php /var/www/html/api/process_upload_queue.php >> /var/log/chatbot_queue.log 2>&1`
- [ ] Add: `0 2 * * * php /var/www/html/api/cleanup_uploads.php >> /var/log/chatbot_cleanup.log 2>&1`
- [ ] Verify cron jobs: `crontab -l`
- [ ] Create log files: `touch /var/log/chatbot_queue.log /var/log/chatbot_cleanup.log`
- [ ] Set permissions: `chmod 666` on log files

### Step 9: ML Dataset Generation
- [ ] Navigate to `chatbot-ml` directory
- [ ] Install dependencies: `pip install mysql-connector-python`
- [ ] Run: `python3 build_comprehensive_dataset.py`
- [ ] Verify output: `ls -la dataset/intents_comprehensive.json`
- [ ] Check file size: should be > 1MB
- [ ] Verify JSON is valid: `python3 -m json.tool dataset/intents_comprehensive.json > /dev/null`

### Step 10: ML Model Training
- [ ] Navigate to `chatbot-ml` directory
- [ ] Run: `python3 train_production.py --dataset dataset/intents_comprehensive.json --output models/production --models logistic_regression random_forest svm mlp --cv-folds 5 --test-size 0.2`
- [ ] Verify models created: `ls -la models/production/`
- [ ] Check model files exist:
  - [ ] `logistic_regression.pkl`
  - [ ] `random_forest.pkl`
  - [ ] `svm.pkl`
  - [ ] `mlp_neural_network.pkl`
  - [ ] `tfidf_vectorizer.pkl`
  - [ ] `label_encoder.pkl`

### Step 11: Chatbot Integration
- [ ] Update `assets/js/chatbot.js`:
  - [ ] Add upload handler initialization
  - [ ] Add upload button to UI
  - [ ] Add upload result handling
  - [ ] Add polling for results
- [ ] Update HTML head:
  - [ ] Add `<link rel="stylesheet" href="/assets/css/upload.css">`
- [ ] Update HTML body:
  - [ ] Add `<script src="/assets/js/upload-handler.js"></script>`
- [ ] Test chatbot loads without errors

### Step 12: Metrics Integration
- [ ] Update `api/chatbot.php`:
  - [ ] Add `require_once` for metrics_collector.php
  - [ ] Initialize MetricsCollector
  - [ ] Add prediction recording
  - [ ] Add response time tracking
- [ ] Test chatbot still responds correctly

## Testing & Verification

### API Testing
- [ ] Test upload endpoint:
  ```bash
  curl -X POST -F "file=@test.jpg" -F "session_id=test" http://localhost/api/upload.php
  ```
  Expected: `{"status":"success","upload_id":1,...}`

- [ ] Test get results endpoint:
  ```bash
  curl -X POST -H "Content-Type: application/json" -d '{"upload_id":1}' http://localhost/api/get_upload_results.php
  ```
  Expected: `{"status":"processing",...}` or `{"status":"completed",...}`

- [ ] Test dashboard:
  ```bash
  curl http://localhost/admin/ml_performance_enhanced.php | grep -q "ML Performance" && echo "OK" || echo "FAILED"
  ```

### Database Testing
- [ ] Check tables created:
  ```bash
  mysql -u root ecommerce_chatbot -e "SHOW TABLES LIKE '%upload%';" | wc -l
  ```
  Expected: 3 (chat_uploads, upload_processing_queue, product_image_matches)

- [ ] Check views created:
  ```bash
  mysql -u root ecommerce_chatbot -e "SHOW FULL TABLES WHERE TABLE_TYPE LIKE 'VIEW';" | wc -l
  ```
  Expected: 4 (v_model_performance_latest, v_intent_accuracy_summary, etc.)

- [ ] Check data can be inserted:
  ```bash
  mysql -u root ecommerce_chatbot -e "INSERT INTO prediction_metrics (intent_tag, total_predictions, correct_predictions) VALUES ('test', 1, 1);"
  ```
  Expected: No error

### Frontend Testing
- [ ] Open chatbot in browser
- [ ] Verify upload button appears
- [ ] Click upload button
- [ ] Verify upload zone appears
- [ ] Try uploading a test image
- [ ] Verify upload progress shows
- [ ] Verify upload completes

### Performance Testing
- [ ] Measure upload speed: should be < 2 seconds
- [ ] Measure image analysis: should be < 5 seconds
- [ ] Measure product matching: should be < 1 second
- [ ] Measure dashboard load: should be < 3 seconds

### Security Testing
- [ ] Try uploading executable file: should be rejected
- [ ] Try uploading file > 10MB: should be rejected
- [ ] Try uploading with invalid MIME type: should be rejected
- [ ] Verify files stored outside webroot
- [ ] Verify API key not exposed in logs

## Post-Deployment

### Monitoring
- [ ] Check error logs: `tail -f /var/log/apache2/error.log`
- [ ] Check upload queue: `tail -f /var/log/chatbot_queue.log`
- [ ] Monitor database: `mysql -e "SELECT COUNT(*) FROM chat_uploads;"`
- [ ] Monitor API usage: `mysql -e "SELECT SUM(estimated_cost) FROM gemini_api_usage;"`

### Optimization
- [ ] Review slow queries: `mysql -e "SHOW PROCESSLIST;"`
- [ ] Check database indexes: `ANALYZE TABLE chat_uploads;`
- [ ] Monitor disk space: `df -h /var/storage/chatbot_uploads`
- [ ] Review API costs: `mysql -e "SELECT * FROM gemini_api_usage ORDER BY created_at DESC LIMIT 10;"`

### Maintenance
- [ ] Setup daily backup: `mysqldump -u root ecommerce_chatbot > backup_$(date +%Y%m%d).sql`
- [ ] Setup log rotation: `logrotate /etc/logrotate.d/chatbot`
- [ ] Monitor cron jobs: `grep CRON /var/log/syslog`
- [ ] Review metrics weekly: `mysql -e "SELECT * FROM v_user_satisfaction_summary;"`

## Rollback Plan

If issues occur:

### Rollback Database
```bash
# Restore from backup
mysql -u root ecommerce_chatbot < backup_YYYYMMDD.sql
```

### Rollback Files
```bash
# Remove new files
rm api/upload.php
rm api/gemini_vision_processor.php
rm api/product_matcher.php
rm api/get_upload_results.php
rm api/process_upload_queue.php
rm assets/js/upload-handler.js
rm assets/css/upload.css
rm admin/ml_performance_enhanced.php
rm includes/metrics_collector.php
```

### Rollback Cron Jobs
```bash
# Edit crontab and remove new jobs
crontab -e
```

### Restart Services
```bash
systemctl restart apache2
systemctl restart chatbot-ml-api
```

## Sign-Off

- [ ] All files deployed
- [ ] All tests passed
- [ ] Dashboard working
- [ ] Upload working
- [ ] Metrics collecting
- [ ] Cron jobs running
- [ ] Performance acceptable
- [ ] Security verified
- [ ] Documentation complete
- [ ] Team trained

**Deployment Date**: _______________  
**Deployed By**: _______________  
**Verified By**: _______________  
**Notes**: _______________

---

**Status**: Ready for Production  
**Version**: 1.0  
**Last Updated**: April 13, 2026
