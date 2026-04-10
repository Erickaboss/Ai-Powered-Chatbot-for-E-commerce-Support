-- ============================================================
-- Feature Enhancements Migration Script
-- Date: 2026-04-03
-- Features: Wishlist Sharing, Advanced Search, Chatbot Improvements, Admin Analytics
-- ============================================================

USE ecommerce_chatbot;

-- 1. Add sharing token to wishlists for link sharing
ALTER TABLE wishlists 
ADD COLUMN share_token VARCHAR(64) UNIQUE NULL AFTER created_at,
ADD COLUMN is_public TINYINT(1) DEFAULT 0 AFTER share_token,
ADD INDEX idx_share_token (share_token);

-- 2. Add helpfulness tracking to reviews
ALTER TABLE reviews
ADD COLUMN helpful_count INT DEFAULT 0 AFTER created_at,
ADD COLUMN verified_purchase TINYINT(1) DEFAULT 0 AFTER helpful_count;

-- 3. Add average rating cache to products (for performance)
ALTER TABLE products
ADD COLUMN avg_rating DECIMAL(3,2) DEFAULT 0.00 AFTER brand,
ADD COLUMN review_count INT DEFAULT 0 AFTER avg_rating;

-- 4. Add chatbot conversation context table
CREATE TABLE IF NOT EXISTS chatbot_context (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id VARCHAR(64) NOT NULL,
    user_id INT DEFAULT NULL,
    context_key VARCHAR(50) NOT NULL,
    context_value TEXT,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_session (session_id),
    INDEX idx_expires (expires_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. Add sentiment analysis results to chatbot_logs
ALTER TABLE chatbot_logs
ADD COLUMN sentiment_score DECIMAL(3,2) DEFAULT NULL AFTER response,
ADD COLUMN sentiment_label VARCHAR(20) DEFAULT NULL AFTER sentiment_score,
ADD COLUMN escalated TINYINT(1) DEFAULT 0 AFTER sentiment_label,
ADD INDEX idx_sentiment (sentiment_label);

-- 6. Create admin analytics view for real-time dashboard
CREATE OR REPLACE VIEW admin_dashboard_stats AS
SELECT 
    'Total Sales' as metric,
    COUNT(DISTINCT o.id) as value
FROM orders o
WHERE o.status != 'cancelled'
UNION ALL
SELECT 
    'Revenue Today',
    COALESCE(SUM(o.total_price), 0)
FROM orders o
WHERE DATE(o.created_at) = CURDATE() AND o.status != 'cancelled'
UNION ALL
SELECT
    'Pending Orders',
    COUNT(*)
FROM orders
WHERE status = 'pending'
UNION ALL
SELECT
    'Low Stock Products',
    COUNT(*)
FROM products
WHERE stock < 10 AND stock > 0
UNION ALL
SELECT
    'Active Users (30 days)',
    COUNT(DISTINCT u.id)
FROM users u
JOIN orders o ON u.id = o.user_id
WHERE o.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY);

-- 7. Create customer segmentation view
CREATE OR REPLACE VIEW customer_segments AS
SELECT 
    u.id,
    u.name,
    u.email,
    COUNT(o.id) as total_orders,
    SUM(o.total_price) as total_spent,
    AVG(o.total_price) as avg_order_value,
    MAX(o.created_at) as last_order_date,
    CASE 
        WHEN SUM(o.total_price) >= 500000 THEN 'VIP'
        WHEN SUM(o.total_price) >= 200000 THEN 'Regular'
        ELSE 'New'
    END as segment,
    DATEDIFF(NOW(), MAX(o.created_at)) as days_since_last_order
FROM users u
LEFT JOIN orders o ON u.id = o.user_id AND o.status != 'cancelled'
GROUP BY u.id, u.name, u.email
HAVING total_orders > 0;

-- 8. Add email notification preferences
ALTER TABLE users
ADD COLUMN email_notifications TINYINT(1) DEFAULT 1 AFTER address,
ADD COLUMN sms_notifications TINYINT(1) DEFAULT 0 AFTER email_notifications;

-- 9. Update stock notifications table for better tracking
ALTER TABLE stock_notifications
ADD COLUMN user_id INT DEFAULT NULL AFTER email,
ADD COLUMN sent_at DATETIME DEFAULT NULL AFTER notified,
ADD FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
ADD FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE;

-- 10. Add product views tracking (for recommendations)
CREATE TABLE IF NOT EXISTS product_views (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT DEFAULT NULL,
    session_id VARCHAR(64) DEFAULT NULL,
    product_id INT NOT NULL,
    viewed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    INDEX idx_product (product_id),
    INDEX idx_session (session_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 11. Add recommendation cache
CREATE TABLE IF NOT EXISTS product_recommendations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    recommended_product_id INT NOT NULL,
    score DECIMAL(5,4) DEFAULT 0.0000,
    reason VARCHAR(50) DEFAULT 'popular',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NOT NULL,
    UNIQUE KEY unique_rec (user_id, recommended_product_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (recommended_product_id) REFERENCES products(id) ON DELETE CASCADE,
    INDEX idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- Update existing data
-- ============================================================

-- Calculate average ratings for existing products
UPDATE products p
LEFT JOIN (
    SELECT product_id, AVG(rating) as avg_rating, COUNT(*) as review_count
    FROM reviews
    GROUP BY product_id
) r ON p.id = r.product_id
SET 
    p.avg_rating = COALESCE(r.avg_rating, 0),
    p.review_count = COALESCE(r.review_count, 0);

-- Generate share tokens for existing wishlists
UPDATE wishlists
SET share_token = MD5(CONCAT(user_id, product_id, RAND(), NOW()))
WHERE share_token IS NULL;

-- ============================================================
-- Insert sample data for testing
-- ============================================================

-- Sample product reviews
INSERT INTO reviews (product_id, user_id, rating, comment, verified_purchase) VALUES
(1, 2, 5, 'Excellent phone! Battery life is amazing and camera quality is superb.', 1),
(1, 3, 4, 'Good value for money. Fast delivery and well packaged.', 1),
(2, 4, 5, 'Best smartphone I have ever owned. Worth every franc!', 1),
(3, 2, 5, 'iPhone quality as expected. Fast shipping!', 1),
(5, 5, 4, 'Comfortable shoes, true to size. Good quality.', 1);

-- Sample wishlist entries with share tokens
INSERT INTO wishlists (user_id, product_id, share_token, is_public) VALUES
(2, 1, MD5(CONCAT('2-1-', RAND())), 1),
(2, 5, MD5(CONCAT('2-5-', RAND())), 1),
(3, 2, MD5(CONCAT('3-2-', RAND())), 0);

-- ============================================================
-- End of Migration
-- ============================================================
