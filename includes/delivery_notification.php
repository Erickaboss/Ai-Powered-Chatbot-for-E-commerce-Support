<?php
/**
 * Delivery Notification & Tracking System
 * Sends notifications when order status changes to "shipped"
 * Allows customers to track delivery via chatbot
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/mailer.php';

/**
 * Send delivery notification when order is shipped
 */
function sendDeliveryNotification(int $orderId, mysqli $conn): bool {
    // Get order details with customer info
    $order = $conn->query("
        SELECT o.*, u.name as customer_name, u.email as customer_email, u.phone as customer_phone
        FROM orders o 
        JOIN users u ON o.user_id = u.id 
        WHERE o.id = $orderId
    ")->fetch_assoc();
    
    if (!$order || $order['status'] !== 'shipped') {
        return false;
    }
    
    // Get order items
    $items_res = $conn->query("
        SELECT oi.*, p.name, p.image 
        FROM order_items oi
        JOIN products p ON oi.product_id = p.id
        WHERE oi.order_id = $orderId
    ");
    $items = $items_res->fetch_all(MYSQLI_ASSOC);
    
    // Calculate estimated delivery date (3-5 business days from now)
    $estimatedDelivery = date('l, F j, Y', strtotime('+4 weekdays'));
    
    // Prepare email
    $subject = "Your Order #" . str_pad($orderId, 6, '0', STR_PAD_LEFT) . " Has Been Shipped!";
    
    $html = "<html><body style='font-family:Arial;color:#333;'>";
    $html .= "<h2 style='color:#e94560;'>Great News! Your Order is on the Way!</h2>";
    $html .= "<p>Dear <strong>" . htmlspecialchars($order['customer_name']) . "</strong>,</p>";
    $html .= "<p>Your order has been shipped and is on the way!</p>";
    
    $html .= "<div style='background:#e8f5e9;padding:20px;border-radius:8px;margin:20px 0;text-align:center;'>";
    $html .= "<h3 style='color:#2e7d32;margin:0;'>Shipped!</h3>";
    $html .= "<p><strong>Estimated Delivery:</strong> " . htmlspecialchars($estimatedDelivery) . "</p>";
    $html .= "<p style='font-size:14px;color:#666;'>You'll receive your products within 3-5 business days</p>";
    $html .= "</div>";
    
    $html .= "<div style='background:white;padding:20px;border-left:4px solid #e94560;'>";
    $html .= "<h4>Order Details</h4>";
    $html .= "<p><strong>Order #:</strong> #" . str_pad($orderId, 6, '0', STR_PAD_LEFT) . "</p>";
    $html .= "<p><strong>Shipping Address:</strong><br>" . nl2br(htmlspecialchars($order['address'])) . "</p>";
    $html .= "</div>";
    
    $html .= "<h4>Products:</h4>";
    foreach ($items as $item) {
        $html .= "<div style='margin:15px 0;padding:10px;background:#fff;border-radius:8px;'>";
        $html .= "<strong>" . htmlspecialchars($item['name']) . "</strong><br>";
        $html .= "Qty: " . $item['quantity'] . " x RWF " . number_format($item['price']);
        $html .= "</div>";
    }
    
    $html .= "<p style='text-align:center;margin-top:30px;'>";
    $html .= "<strong>Track Your Order:</strong> Ask our AI chatbot: \"Where is my order?\"</p>";
    $html .= "<p style='text-align:center;'><a href='" . SITE_URL . "/order_detail.php?id=" . $orderId . "' style='display:inline-block;padding:12px 30px;background:#e94560;color:white;text-decoration:none;border-radius:25px;'>View Order</a></p>";
    $html .= "</body></html>";
    
    // Send email
    $sent = sendMail($order['customer_email'], $order['customer_name'], $subject, $html);
    
    // Log notification in database
    if ($sent) {
        $orderIdSafe = (int)$orderId;
        $estDate = date('Y-m-d H:i:s', strtotime('+4 weekdays'));
        $conn->query("INSERT INTO delivery_notifications (order_id, notified_at, status, estimated_delivery) VALUES ($orderIdSafe, NOW(), 'shipped', '$estDate')");
    }
    
    return $sent;
}

/**
 * Get delivery status for chatbot
 */
function getDeliveryStatus(int $orderId, mysqli $conn): array {
    $order = $conn->query("
        SELECT o.*, u.name as customer_name,
               (SELECT notified_at FROM delivery_notifications WHERE order_id = o.id ORDER BY id DESC LIMIT 1) as last_notified
        FROM orders o 
        JOIN users u ON o.user_id = u.id 
        WHERE o.id = $orderId
    ")->fetch_assoc();
    
    if (!$order) {
        return ['found' => false, 'message' => "Order not found."];
    }
    
    $statusMsgs = [
        'pending' => "Your order is being processed.",
        'processing' => "Your order is being prepared for shipment.",
        'shipped' => "Your order has been shipped and is on the way!",
        'delivered' => "Your order has been delivered successfully!",
        'cancelled' => "This order was cancelled."
    ];
    
    $response = [
        'found' => true,
        'order_id' => $orderId,
        'status' => $order['status'],
        'message' => $statusMsgs[$order['status']] ?? "Status: " . $order['status'],
        'order_date' => $order['created_at'],
        'total' => $order['total_price'],
        'address' => $order['address']
    ];
    
    // Add estimated delivery if shipped
    if ($order['status'] === 'shipped') {
        $notif = $conn->query("SELECT estimated_delivery FROM delivery_notifications WHERE order_id = $orderId ORDER BY id DESC LIMIT 1")->fetch_assoc();
        if ($notif && $notif['estimated_delivery']) {
            $estDate = strtotime($notif['estimated_delivery']);
            $response['estimated_delivery'] = date('l, F j, Y', $estDate);
            $response['days_remaining'] = max(0, ceil((strtotime($notif['estimated_delivery']) - time()) / 86400));
        }
    }
    
    return $response;
}

// Auto-send when admin updates to shipped
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['order_id'])) {
    $orderId = (int)$_POST['order_id'];
    $newStatus = $_POST['status'];
    
    if ($newStatus === 'shipped') {
        sendDeliveryNotification($orderId, $conn);
    }
}
