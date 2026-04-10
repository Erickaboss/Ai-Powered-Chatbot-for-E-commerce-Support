<?php
/**
 * FREE Delivery Notification System
 * Uses email instead of SMS to avoid Twilio costs
 * Already configured with your Gmail SMTP
 */

class FreeDeliveryNotifier {
    
    /**
     * Send delivery notification via EMAIL (FREE)
     * This replaces the paid Twilio SMS solution
     */
    public static function sendFreeDeliveryNotification(int $orderId, mysqli $conn): bool {
        global $site_url;
        
        // Get order and customer details
        $order = $conn->query("
            SELECT o.*, u.name as customer_name, u.email as customer_email, u.phone as customer_phone
            FROM orders o 
            JOIN users u ON o.user_id = u.id 
            WHERE o.id = $orderId
        ")->fetch_assoc();
        
        if (!$order || $order['status'] !== 'shipped') {
            return false;
        }
        
        // Calculate estimated delivery date (4 business days from now)
        $estimatedDelivery = date('l, F j, Y', strtotime('+4 weekdays'));
        $estDate = date('Y-m-d H:i:s', strtotime('+4 weekdays'));
        
        // Build email content
        $subject = "🚚 Your Order #$orderId is On the Way!";
        
        $html = "<html><body style='font-family: Arial, sans-serif; color: #333;'>";
        $html .= "<h2 style='color: #e94560;'>Great News! Your Order is Shipped!</h2>";
        $html .= "<p>Dear <strong>" . htmlspecialchars($order['customer_name']) . "</strong>,</p>";
        $html .= "<p>Your order <strong>#" . str_pad($orderId, 6, '0', STR_PAD_LEFT) . "</strong> has been shipped and is on its way to you!</p>";
        
        $html .= "<div style='background: #f8f9fa; padding: 20px; margin: 20px 0; border-left: 4px solid #e94560;'>";
        $html .= "<h3 style='margin-top: 0;'>📦 Order Details:</h3>";
        $html .= "<p><strong>Order Number:</strong> #" . str_pad($orderId, 6, '0', STR_PAD_LEFT) . "</p>";
        $html .= "<p><strong>Estimated Delivery:</strong> $estimatedDelivery</p>";
        $html .= "<p><strong>Shipping Address:</strong><br>" . nl2br(htmlspecialchars($order['shipping_address'])) . "</p>";
        $html .= "</div>";
        
        $html .= "<div style='background: #fff3cd; padding: 15px; margin: 20px 0; border-radius: 5px;'>";
        $html .= "<h4 style='margin-top: 0; color: #856404;'>🚚 Track Your Delivery:</h4>";
        $html .= "<p>You can track your order status anytime by visiting:</p>";
        $html .= "<a href='$site_url/orders.php' style='display: inline-block; background: #e94560; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; margin: 10px 0;'>Track My Order</a>";
        $html .= "</div>";
        
        $html .= "<div style='margin-top: 30px; padding-top: 20px; border-top: 2px solid #eee;'>";
        $html .= "<h4>💬 Quick Questions?</h4>";
        $html .= "<p>Our AI chatbot can answer your questions instantly! Just ask:</p>";
        $html .= "<ul>";
        $html .= "<li><strong>\"Where is my order?\"</strong></li>";
        $html .= "<li><strong>\"Track order #" . str_pad($orderId, 6, '0', STR_PAD_LEFT) . "\"</strong></li>";
        $html .= "<li><strong>\"When will my order arrive?\"</strong></li>";
        $html .= "</ul>";
        $html .= "</div>";
        
        $html .= "<p style='margin-top: 30px;'>Thank you for shopping with us!</p>";
        $html .= "<p style='color: #666; font-size: 14px;'>Questions? Reply to this email or contact support.</p>";
        $html .= "</body></html>";
        
        // Plain text version
        $plainText = "Great News! Your Order #$orderId is Shipped!\n\n";
        $plainText .= "Dear {$order['customer_name']},\n\n";
        $plainText .= "Your order #" . str_pad($orderId, 6, '0', STR_PAD_LEFT) . " has been shipped!\n\n";
        $plainText .= "Estimated Delivery: $estimatedDelivery\n\n";
        $plainText .= "Track your order: $site_url/orders.php\n\n";
        $plainText .= "Questions? Ask our chatbot: \"Where is my order?\"\n\n";
        $plainText .= "Thank you for shopping with us!";
        
        // Send email using existing mailer
        require_once __DIR__ . '/mailer.php';
        
        try {
            $sent = sendMail(
                $order['customer_email'],
                $order['customer_name'],
                $subject,
                $html,
                $plainText
            );
            
            // Log notification to database
            if ($sent) {
                $trackingInfo = json_encode([
                    'method' => 'email',
                    'sent_at' => date('Y-m-d H:i:s'),
                    'estimated_delivery' => $estDate
                ]);
                
                $conn->query("
                    INSERT INTO delivery_notifications (order_id, notified_at, status, estimated_delivery, notification_sent, tracking_info)
                    VALUES ($orderId, NOW(), 'shipped', '$estDate', 1, '" . $conn->real_escape_string($trackingInfo) . "')
                ");
                
                error_log("FREE Delivery notification sent for Order #$orderId to {$order['customer_email']}");
            }
            
            return $sent;
            
        } catch (Exception $e) {
            error_log("FREE Delivery notification failed for Order #$orderId: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send order confirmation via EMAIL (FREE)
     */
    public static function sendOrderConfirmation(int $orderId, mysqli $conn): bool {
        global $site_url;
        
        $order = $conn->query("
            SELECT o.*, u.name as customer_name, u.email as customer_email
            FROM orders o 
            JOIN users u ON o.user_id = u.id 
            WHERE o.id = $orderId
        ")->fetch_assoc();
        
        if (!$order) return false;
        
        $subject = "✅ Order Confirmed! #$orderId";
        
        $html = "<html><body style='font-family: Arial;'>";
        $html .= "<h2 style='color: #2ecc71;'>Thank You for Your Order!</h2>";
        $html .= "<p>Dear <strong>{$order['customer_name']}</strong>,</p>";
        $html .= "<p>We've received your order <strong>#" . str_pad($orderId, 6, '0', STR_PAD_LEFT) . "</strong>.</p>";
        $html .= "<p><strong>Total Amount:</strong> RWF " . number_format($order['total_amount']) . "</p>";
        $html .= "<p>We'll notify you when it ships!</p>";
        $html .= "<a href='$site_url/orders.php'>View Order Details</a>";
        $html .= "</body></html>";
        
        require_once __DIR__ . '/mailer.php';
        
        return sendMail($order['customer_email'], $order['customer_name'], $subject, $html);
    }
    
    /**
     * Get delivery status for chatbot queries
     */
    public static function getDeliveryStatus(int $orderId, mysqli $conn): array {
        $notification = $conn->query("
            SELECT dn.*, o.status as order_status
            FROM delivery_notifications dn
            JOIN orders o ON dn.order_id = o.id
            WHERE dn.order_id = $orderId
            ORDER BY dn.notified_at DESC
            LIMIT 1
        ")->fetch_assoc();
        
        if (!$notification) {
            return [
                'found' => false,
                'message' => "No delivery information found for order #$orderId"
            ];
        }
        
        $status = ucfirst(str_replace('_', ' ', $notification['status']));
        $estimatedDelivery = $notification['estimated_delivery'] 
            ? date('l, F j, Y', strtotime($notification['estimated_delivery']))
            : 'Not yet scheduled';
        
        // Calculate days remaining
        $daysRemaining = null;
        if ($notification['estimated_delivery']) {
            $now = new DateTime();
            $est = new DateTime($notification['estimated_delivery']);
            $diff = $now->diff($est);
            $daysRemaining = $diff->days;
        }
        
        return [
            'found' => true,
            'order_id' => $orderId,
            'status' => $status,
            'estimated_delivery' => $estimatedDelivery,
            'days_remaining' => $daysRemaining,
            'notified_at' => $notification['notified_at'],
            'message' => "Your order is $status. Estimated delivery: $estimatedDelivery"
        ];
    }
}

// Helper functions for easy integration
function sendFreeDeliveryEmail(int $orderId, mysqli $conn): bool {
    return FreeDeliveryNotifier::sendFreeDeliveryNotification($orderId, $conn);
}

function sendFreeOrderConfirmation(int $orderId, mysqli $conn): bool {
    return FreeDeliveryNotifier::sendOrderConfirmation($orderId, $conn);
}

function checkDeliveryStatus(int $orderId, mysqli $conn): array {
    return FreeDeliveryNotifier::getDeliveryStatus($orderId, $conn);
}
