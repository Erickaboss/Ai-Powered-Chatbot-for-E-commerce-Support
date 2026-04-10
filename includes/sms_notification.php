<?php
/**
 * SMS Notification Service - Twilio Integration
 * Send delivery updates, order confirmations via SMS
 */

class SmsNotification {
    private $twilioSid;
    private $twilioToken;
    private $fromNumber;
    
    public function __construct() {
        // Load from environment or config
        $this->twilioSid = defined('TWILIO_SID') ? TWILIO_SID : '';
        $this->twilioToken = defined('TWILIO_TOKEN') ? TWILIO_TOKEN : '';
        $this->fromNumber = defined('TWILIO_FROM_NUMBER') ? TWILIO_FROM_NUMBER : '';
        
        // Disable SMS if credentials not configured
        if (empty($this->twilioSid) || empty($this->twilioToken) || empty($this->fromNumber)) {
            error_log('SMS Notifications disabled: Twilio credentials not configured');
        }
    }
    
    /**
     * Send SMS via Twilio API
     */
    public function sendSms(string $to, string $message): array {
        if (empty($this->twilioSid) || empty($this->twilioToken)) {
            return ['success' => false, 'error' => 'Twilio credentials not configured'];
        }
        
        $url = "https://api.twilio.com/2010-04-01/Accounts/{$this->twilioSid}/Messages.json";
        
        $data = [
            'From' => $this->fromNumber,
            'To' => $to,
            'Body' => $message
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERPWD, "{$this->twilioSid}:{$this->twilioToken}");
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200 || $httpCode === 201) {
            $result = json_decode($response, true);
            return [
                'success' => true,
                'message_sid' => $result['sid'] ?? null,
                'status' => $result['status'] ?? null
            ];
        } else {
            $error = json_decode($response, true);
            return [
                'success' => false,
                'error' => $error['message'] ?? 'Failed to send SMS',
                'code' => $error['code'] ?? $httpCode
            ];
        }
    }
    
    /**
     * Send delivery update SMS
     */
    public function sendDeliveryUpdate(string $phone, int $orderId, string $status, string $estimatedDate = null): array {
        $messages = [
            'pending' => "Your order #{$orderId} is being processed. We'll notify you when it ships!",
            'processing' => "Great news! Order #{$orderId} is being prepared for shipment.",
            'shipped' => "🚚 Your order #{$orderId} has been shipped! Est. delivery: {$estimatedDate}",
            'out_for_delivery' => "Your order #{$orderId} is out for delivery today!",
            'delivered' => "🎉 Your order #{$orderId} has been delivered. Thank you for shopping with us!",
            'cancelled' => "Your order #{$orderId} has been cancelled."
        ];
        
        $message = $messages[$status] ?? "Order #{$orderId} status updated: {$status}";
        
        // Log SMS notification
        $this->logSmsNotification($orderId, $phone, $status, $message);
        
        return $this->sendSms($phone, $message);
    }
    
    /**
     * Send order confirmation SMS
     */
    public function sendOrderConfirmation(string $phone, int $orderId, float $total): array {
        $message = "Order confirmed! #{$orderId} - Total: RWF " . number_format($total) . 
                   ". Thank you for your purchase! Track at: " . SITE_URL . "/orders.php";
        
        $this->logSmsNotification($orderId, $phone, 'confirmed', $message);
        
        return $this->sendSms($phone, $message);
    }
    
    /**
     * Send OTP for verification
     */
    public function sendOtp(string $phone, string $otp): array {
        $message = "Your verification code is: {$otp}. Valid for 10 minutes. Do not share this code.";
        return $this->sendSms($phone, $message);
    }
    
    /**
     * Log SMS notifications in database
     */
    private function logSmsNotification(int $orderId, string $phone, string $type, string $message): void {
        global $conn;
        $orderIdSafe = (int)$orderId;
        $phoneSafe = $conn->real_escape_string($phone);
        $typeSafe = $conn->real_escape_string($type);
        $msgSafe = $conn->real_escape_string($message);
        
        $conn->query("
            INSERT INTO sms_notifications (order_id, phone, type, message, sent_at) 
            VALUES ($orderIdSafe, '$phoneSafe', '$typeSafe', '$msgSafe', NOW())
        ");
    }
}

// Convenience functions
function sendSmsNotification(string $phone, string $message): array {
    $sms = new SmsNotification();
    return $sms->sendSms($phone, $message);
}

function sendDeliverySms(string $phone, int $orderId, string $status, string $estDate = null): array {
    $sms = new SmsNotification();
    return $sms->sendDeliveryUpdate($phone, $orderId, $status, $estDate);
}

// Auto-send SMS when delivery notification is sent (if enabled)
// Note: This requires manual integration with delivery_notification.php
// Example usage in delivery_notification.php:
// if (defined('ENABLE_SMS_NOTIFICATIONS') && ENABLE_SMS_NOTIFICATIONS) {
//     $sms = new SmsNotification();
//     $sms->sendDeliveryUpdate($phone, $orderId, $status, $estDate);
// }
