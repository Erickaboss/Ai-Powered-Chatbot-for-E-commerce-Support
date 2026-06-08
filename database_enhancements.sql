-- ============================================================
-- Database Enhancements for Image Upload & ML Monitoring
-- ============================================================

USE ecommerce_chatbot;

-- ============================================================
-- 1. FILE UPLOAD TABLES
-- ============================================================

CREATE TABLE IF NOT EXISTS chat_uploads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id VARCHAR(64) NOT NULL,
    user_id INT DEFAULT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_type ENUM('image', 'document') NOT NULL,
    mime_type VARCHAR(100) NOT NULL,
    file_size INT NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    storage_path VARCHAR(500) NOT NULL,
    gemini_analysis TEXT DEFAULT NULL,
    product_matches JSON DEFAULT NULL,
    confidence_score DECIMAL(5,3) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_session (session_id),
    INDEX idx_user (user_id),
    INDEX idx_created (created_at),
    INDEX idx_expires (expires_at)
);

CREATE TABLE IF NOT EXISTS upload_processing_queue (
    id INT AUTO_INCREMENT PRIMARY KEY,
    upload_id INT NOT NULL,
    status ENUM('pending', 'processing', 'completed', 'failed') DEFAULT 'pending',
    error_message TEXT DEFAULT NULL,
    processing_time_ms INT DEFAULT NULL,
    gemini_request_id VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (upload_id) REFERENCES chat_uploads(id) ON DELETE CASCADE,
    INDEX idx_status (status),
    INDEX idx_updated (updated_at)
);

-- ============================================================
-- 2. ML PERFORMANCE TRACKING TABLES
-- ============================================================

CREATE TABLE IF NOT EXISTS model_performance_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    model_name VARCHAR(100) NOT NULL,
    accuracy DECIMAL(5,4) NOT NULL,
    precision DECIMAL(5,4) NOT NULL,
    recall DECIMAL(5,4) NOT NULL,
    f1_score DECIMAL(5,4) NOT NULL,
    cv_mean DECIMAL(5,4) DEFAULT NULL,
    cv_std DECIMAL(5,4) DEFAULT NULL,
    training_samples INT DEFAULT NULL,
    test_samples INT DEFAULT NULL,
    training_time_seconds INT DEFAULT NULL,
    model_version VARCHAR(50) DEFAULT NULL,
    trained_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_model (model_name),
    INDEX idx_trained (trained_at),
    INDEX idx_accuracy (accuracy)
);

CREATE TABLE IF NOT EXISTS prediction_metrics (
    id INT AUTO_INCREMENT PRIMARY KEY,
    intent_tag VARCHAR(100) NOT NULL,
    total_predictions INT DEFAULT 0,
    correct_predictions INT DEFAULT 0,
    accuracy DECIMAL(5,4) DEFAULT NULL,
    avg_confidence DECIMAL(5,4) DEFAULT NULL,
    recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_intent (intent_tag),
    INDEX idx_recorded (recorded_at)
);

CREATE TABLE IF NOT EXISTS user_satisfaction_metrics (
    id INT AUTO_INCREMENT PRIMARY KEY,
    log_id INT NOT NULL,
    user_rating TINYINT DEFAULT NULL COMMENT '1-5 stars',
    helpful TINYINT(1) DEFAULT NULL,
    accurate TINYINT(1) DEFAULT NULL,
    response_time_ms INT DEFAULT NULL,
    recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (log_id) REFERENCES chatbot_logs(id) ON DELETE CASCADE,
    INDEX idx_recorded (recorded_at),
    INDEX idx_rating (user_rating)
);

-- ============================================================
-- 3. GEMINI API USAGE TRACKING
-- ============================================================

CREATE TABLE IF NOT EXISTS gemini_api_usage (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id VARCHAR(64) DEFAULT NULL,
    user_id INT DEFAULT NULL,
    api_type ENUM('vision', 'text', 'embedding') NOT NULL,
    model_name VARCHAR(100) NOT NULL,
    input_tokens INT DEFAULT NULL,
    output_tokens INT DEFAULT NULL,
    total_tokens INT DEFAULT NULL,
    estimated_cost DECIMAL(10,6) DEFAULT NULL,
    response_time_ms INT DEFAULT NULL,
    status ENUM('success', 'failed', 'rate_limited') DEFAULT 'success',
    error_message TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_session (session_id),
    INDEX idx_user (user_id),
    INDEX idx_created (created_at),
    INDEX idx_api_type (api_type)
);

-- ============================================================
-- 4. PRODUCT KNOWLEDGE BASE CACHE
-- ============================================================

CREATE TABLE IF NOT EXISTS product_knowledge_cache (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cache_key VARCHAR(255) UNIQUE NOT NULL,
    cache_type ENUM('category', 'price_range', 'search', 'recommendations') NOT NULL,
    cache_data JSON NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL,
    INDEX idx_type (cache_type),
    INDEX idx_expires (expires_at)
);

-- ============================================================
-- 5. BUDGET RECOMMENDATION TRACKING
-- ============================================================

CREATE TABLE IF NOT EXISTS budget_recommendations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id VARCHAR(64) NOT NULL,
    user_id INT DEFAULT NULL,
    budget_min INT NOT NULL,
    budget_max INT NOT NULL,
    category_id INT DEFAULT NULL,
    products_shown INT DEFAULT 0,
    products_clicked INT DEFAULT 0,
    products_purchased INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
    INDEX idx_session (session_id),
    INDEX idx_user (user_id),
    INDEX idx_created (created_at)
);

-- ============================================================
-- 6. COMPLEX QUERY TRACKING
-- ============================================================

CREATE TABLE IF NOT EXISTS complex_queries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id VARCHAR(64) NOT NULL,
    user_id INT DEFAULT NULL,
    query_text TEXT NOT NULL,
    query_type VARCHAR(100) NOT NULL,
    language VARCHAR(10) DEFAULT 'en',
    used_gemini TINYINT(1) DEFAULT 0,
    gemini_response TEXT DEFAULT NULL,
    user_satisfaction TINYINT DEFAULT NULL COMMENT '1-5 stars',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_session (session_id),
    INDEX idx_user (user_id),
    INDEX idx_type (query_type),
    INDEX idx_created (created_at)
);

-- ============================================================
-- 7. PRODUCT IMAGE MATCHING RESULTS
-- ============================================================

CREATE TABLE IF NOT EXISTS product_image_matches (
    id INT AUTO_INCREMENT PRIMARY KEY,
    upload_id INT NOT NULL,
    product_id INT NOT NULL,
    match_score DECIMAL(5,4) NOT NULL,
    match_reason VARCHAR(255) DEFAULT NULL,
    user_confirmed TINYINT(1) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (upload_id) REFERENCES chat_uploads(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    INDEX idx_upload (upload_id),
    INDEX idx_product (product_id),
    INDEX idx_score (match_score)
);

-- ============================================================
-- 8. TRAINING DATA AUGMENTATION LOG
-- ============================================================

CREATE TABLE IF NOT EXISTS training_data_augmentation (
    id INT AUTO_INCREMENT PRIMARY KEY,
    source_type ENUM('manual', 'database', 'faq', 'user_feedback') NOT NULL,
    intent_tag VARCHAR(100) NOT NULL,
    sample_count INT NOT NULL,
    language VARCHAR(10) DEFAULT 'en',
    augmentation_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_source (source_type),
    INDEX idx_intent (intent_tag),
    INDEX idx_date (augmentation_date)
);

-- ============================================================
-- 9. MULTILINGUAL SUPPORT TRACKING
-- ============================================================

CREATE TABLE IF NOT EXISTS multilingual_queries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id VARCHAR(64) NOT NULL,
    user_id INT DEFAULT NULL,
    original_language VARCHAR(10) NOT NULL,
    detected_language VARCHAR(10) NOT NULL,
    query_text TEXT NOT NULL,
    translated_query TEXT DEFAULT NULL,
    response_language VARCHAR(10) NOT NULL,
    confidence DECIMAL(5,4) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_session (session_id),
    INDEX idx_language (detected_language),
    INDEX idx_created (created_at)
);

-- ============================================================
-- 10. PROFESSIONAL RESPONSE FORMATTING LOG
-- ============================================================

CREATE TABLE IF NOT EXISTS professional_responses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    log_id INT NOT NULL,
    response_type ENUM('product_card', 'comparison_table', 'recommendation_panel', 'budget_panel', 'complex_answer') NOT NULL,
    formatting_applied VARCHAR(255) DEFAULT NULL,
    user_engagement TINYINT DEFAULT NULL COMMENT '0=ignored, 1=viewed, 2=interacted',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (log_id) REFERENCES chatbot_logs(id) ON DELETE CASCADE,
    INDEX idx_type (response_type),
    INDEX idx_created (created_at)
);

-- ============================================================
-- 11. INDEXES FOR PERFORMANCE
-- ============================================================

-- Optimize chatbot_logs for faster queries
ALTER TABLE chatbot_logs ADD INDEX idx_session_created (session_id, created_at);
ALTER TABLE chatbot_logs ADD INDEX idx_user_created (user_id, created_at);

-- Optimize products for search
ALTER TABLE products ADD INDEX idx_category_price (category_id, price);
ALTER TABLE products ADD INDEX idx_name_search (name);

-- Optimize orders for tracking
ALTER TABLE orders ADD INDEX idx_user_status (user_id, status);
ALTER TABLE orders ADD INDEX idx_created_status (created_at, status);

-- ============================================================
-- 12. VIEWS FOR ANALYTICS
-- ============================================================

CREATE OR REPLACE VIEW v_model_performance_latest AS
SELECT model_name, accuracy, precision, recall, f1_score, cv_mean, trained_at
FROM (
    SELECT 
        model_name,
        accuracy,
        precision,
        recall,
        f1_score,
        cv_mean,
        trained_at,
        ROW_NUMBER() OVER (PARTITION BY model_name ORDER BY trained_at DESC) as rn
    FROM model_performance_history
) ranked
WHERE rn = 1;

CREATE OR REPLACE VIEW v_intent_accuracy_summary AS
SELECT 
    intent_tag,
    total_predictions,
    correct_predictions,
    ROUND((correct_predictions / total_predictions) * 100, 2) as accuracy_percent,
    ROUND(avg_confidence * 100, 2) as avg_confidence_percent,
    recorded_at
FROM prediction_metrics
ORDER BY accuracy_percent DESC;

CREATE OR REPLACE VIEW v_user_satisfaction_summary AS
SELECT 
    DATE(recorded_at) as date,
    COUNT(*) as total_ratings,
    ROUND(AVG(user_rating), 2) as avg_rating,
    SUM(CASE WHEN helpful = 1 THEN 1 ELSE 0 END) as helpful_count,
    SUM(CASE WHEN accurate = 1 THEN 1 ELSE 0 END) as accurate_count,
    ROUND(AVG(response_time_ms), 0) as avg_response_time_ms
FROM user_satisfaction_metrics
GROUP BY DATE(recorded_at)
ORDER BY date DESC;

CREATE OR REPLACE VIEW v_gemini_api_cost_summary AS
SELECT 
    DATE(created_at) as date,
    api_type,
    COUNT(*) as request_count,
    SUM(input_tokens) as total_input_tokens,
    SUM(output_tokens) as total_output_tokens,
    ROUND(SUM(estimated_cost), 4) as total_cost,
    ROUND(AVG(response_time_ms), 0) as avg_response_time_ms
FROM gemini_api_usage
WHERE status = 'success'
GROUP BY DATE(created_at), api_type
ORDER BY date DESC;

-- ============================================================
-- 13. STORED PROCEDURES FOR COMMON OPERATIONS
-- ============================================================

DELIMITER //

CREATE PROCEDURE sp_cleanup_expired_uploads()
BEGIN
    DELETE FROM chat_uploads 
    WHERE expires_at IS NOT NULL AND expires_at < NOW();
    
    DELETE FROM upload_processing_queue 
    WHERE upload_id NOT IN (SELECT id FROM chat_uploads);
END //

CREATE PROCEDURE sp_aggregate_daily_metrics()
BEGIN
    INSERT INTO prediction_metrics (intent_tag, total_predictions, correct_predictions, accuracy, avg_confidence, recorded_at)
    SELECT 
        intent_tag,
        COUNT(*) as total_predictions,
        SUM(CASE WHEN correct_predictions > 0 THEN 1 ELSE 0 END) as correct_predictions,
        ROUND(SUM(CASE WHEN correct_predictions > 0 THEN 1 ELSE 0 END) / COUNT(*), 4) as accuracy,
        ROUND(AVG(avg_confidence), 4) as avg_confidence,
        NOW()
    FROM prediction_metrics
    WHERE DATE(recorded_at) = CURDATE()
    GROUP BY intent_tag
    ON DUPLICATE KEY UPDATE
        total_predictions = VALUES(total_predictions),
        correct_predictions = VALUES(correct_predictions),
        accuracy = VALUES(accuracy),
        avg_confidence = VALUES(avg_confidence);
END //

CREATE PROCEDURE sp_get_model_comparison()
BEGIN
    SELECT 
        model_name,
        accuracy,
        precision,
        recall,
        f1_score,
        cv_mean,
        training_samples,
        test_samples,
        trained_at
    FROM model_performance_history
    WHERE trained_at = (SELECT MAX(trained_at) FROM model_performance_history)
    ORDER BY accuracy DESC;
END //

DELIMITER ;

-- ============================================================
-- 14. TRIGGERS FOR AUTOMATIC UPDATES
-- ============================================================

DELIMITER //

CREATE TRIGGER tr_update_upload_processing_timestamp
BEFORE UPDATE ON upload_processing_queue
FOR EACH ROW
BEGIN
    SET NEW.updated_at = NOW();
END //

CREATE TRIGGER tr_calculate_prediction_accuracy
AFTER INSERT ON prediction_metrics
FOR EACH ROW
BEGIN
    UPDATE prediction_metrics 
    SET accuracy = ROUND((correct_predictions / total_predictions), 4)
    WHERE id = NEW.id;
END //

DELIMITER ;

-- ============================================================
-- 15. INITIAL DATA SETUP
-- ============================================================

-- Insert default cache expiration settings
INSERT IGNORE INTO product_knowledge_cache (cache_key, cache_type, cache_data, expires_at)
VALUES ('cache_config', 'category', '{"ttl": 3600}', DATE_ADD(NOW(), INTERVAL 1 HOUR));

-- ============================================================
-- COMPLETION
-- ============================================================

-- Verify all tables created
SELECT 
    TABLE_NAME,
    TABLE_ROWS,
    DATA_LENGTH,
    INDEX_LENGTH
FROM INFORMATION_SCHEMA.TABLES
WHERE TABLE_SCHEMA = 'ecommerce_chatbot'
AND TABLE_NAME IN (
    'chat_uploads',
    'upload_processing_queue',
    'model_performance_history',
    'prediction_metrics',
    'user_satisfaction_metrics',
    'gemini_api_usage',
    'product_knowledge_cache',
    'budget_recommendations',
    'complex_queries',
    'product_image_matches',
    'training_data_augmentation',
    'multilingual_queries',
    'professional_responses'
)
ORDER BY TABLE_NAME;
