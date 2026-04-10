-- ============================================================
-- Migration: Delivery Notifications & Image Upload Support
-- Date: April 3, 2026
-- ============================================================

USE ecommerce_chatbot;

-- Table for delivery notifications tracking
CREATE TABLE IF NOT EXISTS delivery_notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    notified_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('pending', 'processing', 'shipped', 'out_for_delivery', 'delivered', 'failed') DEFAULT 'pending',
    estimated_delivery DATETIME DEFAULT NULL,
    actual_delivery DATETIME DEFAULT NULL,
    notification_sent TINYINT(1) DEFAULT 0,
    tracking_info TEXT DEFAULT NULL,
    INDEX idx_order (order_id),
    INDEX idx_status (status),
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
);

-- Table for image uploads in chatbot (for visual search)
CREATE TABLE IF NOT EXISTS chatbot_images (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id VARCHAR(64) DEFAULT NULL,
    user_id INT DEFAULT NULL,
    image_path VARCHAR(255) NOT NULL,
    image_type VARCHAR(50) DEFAULT 'screenshot',
    description TEXT DEFAULT NULL,
    analyzed TINYINT(1) DEFAULT 0,
    analysis_result TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_session (session_id),
    INDEX idx_user (user_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Add ML model performance tracking table
CREATE TABLE IF NOT EXISTS ml_model_performance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    model_name VARCHAR(100) NOT NULL,
    accuracy DECIMAL(5,4) DEFAULT 0.0000,
    precision DECIMAL(5,4) DEFAULT 0.0000,
    recall DECIMAL(5,4) DEFAULT 0.0000,
    f1_score DECIMAL(5,4) DEFAULT 0.0000,
    training_samples INT DEFAULT 0,
    test_samples INT DEFAULT 0,
    trained_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    model_version VARCHAR(20) DEFAULT '1.0',
    metadata JSON DEFAULT NULL,
    INDEX idx_model (model_name),
    INDEX idx_trained (trained_at)
);

-- Insert initial ML model performance data (from your training results)
INSERT INTO ml_model_performance (model_name, accuracy, precision, recall, f1_score, training_samples, test_samples, model_version) VALUES
('Logistic Regression', 0.9600, 0.9600, 0.9600, 0.9600, 960, 240, '1.0'),
('Random Forest', 0.9700, 0.9700, 0.9700, 0.9700, 960, 240, '1.0'),
('SVM (RBF Kernel)', 0.9750, 0.9750, 0.9750, 0.9750, 960, 240, '1.0'),
('MLP Neural Network', 0.9800, 0.9800, 0.9800, 0.9800, 960, 240, '1.0');

-- Add new intents for delivery tracking to chatbot_logs reference
ALTER TABLE chatbot_logs ADD COLUMN intent_tag VARCHAR(50) DEFAULT NULL AFTER response;
ALTER TABLE chatbot_logs ADD INDEX idx_intent (intent_tag);

-- Add column for image attachment reference
ALTER TABLE chatbot_logs ADD COLUMN image_id INT DEFAULT NULL AFTER intent_tag;
ALTER TABLE chatbot_logs ADD FOREIGN KEY (image_id) REFERENCES chatbot_images(id) ON DELETE SET NULL;

-- Update admin permissions if needed
-- No changes needed as admin already has full access

COMMIT;
