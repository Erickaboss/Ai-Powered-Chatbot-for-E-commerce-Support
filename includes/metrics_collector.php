<?php
/**
 * Metrics Collector
 * Collects performance metrics during chatbot operation
 */

class MetricsCollector {
    private $conn;
    
    public function __construct($conn) {
        $this->conn = $conn;
    }
    
    /**
     * Record prediction metric
     */
    public function recordPrediction($intentTag, $correct, $confidence) {
        try {
            $stmt = $this->conn->prepare("
                INSERT INTO prediction_metrics (intent_tag, total_predictions, correct_predictions, avg_confidence)
                VALUES (?, 1, ?, ?)
                ON DUPLICATE KEY UPDATE
                    total_predictions = total_predictions + 1,
                    correct_predictions = correct_predictions + ?,
                    avg_confidence = (avg_confidence + ?) / 2
            ");
            
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $this->conn->error);
            }
            
            $correct_int = $correct ? 1 : 0;
            $stmt->bind_param('sdddd', $intentTag, $confidence, $confidence, $correct_int, $confidence);
            $stmt->execute();
            $stmt->close();
        } catch (Exception $e) {
            error_log("Metrics error: " . $e->getMessage());
        }
    }
    
    /**
     * Record user satisfaction
     */
    public function recordSatisfaction($logId, $rating, $helpful, $accurate, $responseTime) {
        try {
            $stmt = $this->conn->prepare("
                INSERT INTO user_satisfaction_metrics (log_id, user_rating, helpful, accurate, response_time_ms)
                VALUES (?, ?, ?, ?, ?)
            ");
            
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $this->conn->error);
            }
            
            $stmt->bind_param('iiiii', $logId, $rating, $helpful, $accurate, $responseTime);
            $stmt->execute();
            $stmt->close();
        } catch (Exception $e) {
            error_log("Satisfaction metrics error: " . $e->getMessage());
        }
    }
    
    /**
     * Record response time
     */
    public function recordResponseTime($logId, $responseTimeMs) {
        try {
            $stmt = $this->conn->prepare("
                UPDATE chatbot_logs
                SET response_time_ms = ?
                WHERE id = ?
            ");
            
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $this->conn->error);
            }
            
            $stmt->bind_param('ii', $responseTimeMs, $logId);
            $stmt->execute();
            $stmt->close();
        } catch (Exception $e) {
            error_log("Response time metrics error: " . $e->getMessage());
        }
    }
    
    /**
     * Get model performance summary
     */
    public function getModelPerformanceSummary() {
        try {
            $result = $this->conn->query("
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
                ORDER BY trained_at DESC
                LIMIT 10
            ");
            
            $models = [];
            while ($row = $result->fetch_assoc()) {
                $models[] = $row;
            }
            
            return $models;
        } catch (Exception $e) {
            error_log("Model performance error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get intent accuracy summary
     */
    public function getIntentAccuracySummary() {
        try {
            $result = $this->conn->query("
                SELECT 
                    intent_tag,
                    total_predictions,
                    correct_predictions,
                    ROUND((correct_predictions / total_predictions) * 100, 2) as accuracy_percent,
                    ROUND(avg_confidence * 100, 2) as avg_confidence_percent
                FROM prediction_metrics
                ORDER BY accuracy_percent DESC
                LIMIT 20
            ");
            
            $intents = [];
            while ($row = $result->fetch_assoc()) {
                $intents[] = $row;
            }
            
            return $intents;
        } catch (Exception $e) {
            error_log("Intent accuracy error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get user satisfaction summary
     */
    public function getUserSatisfactionSummary() {
        try {
            $result = $this->conn->query("
                SELECT 
                    DATE(recorded_at) as date,
                    COUNT(*) as total_ratings,
                    ROUND(AVG(user_rating), 2) as avg_rating,
                    SUM(CASE WHEN helpful = 1 THEN 1 ELSE 0 END) as helpful_count,
                    SUM(CASE WHEN accurate = 1 THEN 1 ELSE 0 END) as accurate_count,
                    ROUND(AVG(response_time_ms), 0) as avg_response_time_ms
                FROM user_satisfaction_metrics
                WHERE recorded_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
                GROUP BY DATE(recorded_at)
                ORDER BY date DESC
            ");
            
            $summary = [];
            while ($row = $result->fetch_assoc()) {
                $summary[] = $row;
            }
            
            return $summary;
        } catch (Exception $e) {
            error_log("User satisfaction error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get Gemini API usage summary
     */
    public function getGeminiUsageSummary() {
        try {
            $result = $this->conn->query("
                SELECT 
                    DATE(created_at) as date,
                    api_type,
                    COUNT(*) as request_count,
                    SUM(input_tokens) as total_input_tokens,
                    SUM(output_tokens) as total_output_tokens,
                    ROUND(SUM(estimated_cost), 4) as total_cost,
                    ROUND(AVG(response_time_ms), 0) as avg_response_time_ms
                FROM gemini_api_usage
                WHERE status = 'success' AND created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
                GROUP BY DATE(created_at), api_type
                ORDER BY date DESC
            ");
            
            $usage = [];
            while ($row = $result->fetch_assoc()) {
                $usage[] = $row;
            }
            
            return $usage;
        } catch (Exception $e) {
            error_log("Gemini usage error: " . $e->getMessage());
            return [];
        }
    }
}

?>
