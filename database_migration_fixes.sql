-- Database migration: indexes and fixes
-- Run this to address audit findings

-- Indexes for better query performance
ALTER TABLE products ADD INDEX IF NOT EXISTS idx_brand (brand(50));
ALTER TABLE products ADD INDEX IF NOT EXISTS idx_description (description(200));
ALTER TABLE chatbot_logs ADD INDEX IF NOT EXISTS idx_created_at (created_at);
ALTER TABLE chatbot_logs ADD INDEX IF NOT EXISTS idx_response_source (response_source);
ALTER TABLE chatbot_memory ADD INDEX IF NOT EXISTS idx_updated_at (updated_at);
ALTER TABLE orders ADD INDEX IF NOT EXISTS idx_user_status (user_id, status);
ALTER TABLE order_items ADD INDEX IF NOT EXISTS idx_product_id (product_id);
ALTER TABLE reviews ADD INDEX IF NOT EXISTS idx_product_rating (product_id, rating);
ALTER TABLE sessions ADD INDEX IF NOT EXISTS idx_expires (expires);

-- View fix (already fixed in database_enhancements.sql)
-- See v_model_performance_latest fix

-- Fulltext index for product search
ALTER TABLE products ADD FULLTEXT INDEX IF NOT EXISTS ft_search (name, brand, description);
