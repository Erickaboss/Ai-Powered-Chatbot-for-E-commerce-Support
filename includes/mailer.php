<?php
/**
 * Email helper — PHPMailer via Brevo SMTP relay
 */
require_once __DIR__ . '/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/SMTP.php';
require_once __DIR__ . '/PHPMailer/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function sendMail(string $toEmail, string $toName, string $subject, string $htmlBody): bool {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = SMTP_PORT;

        $mail->setFrom(ADMIN_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($toEmail, $toName);
        $mail->isHTML(true);
        $mail->CharSet = 'UTF-8';
        $mail->Subject = $subject;
        $mail->Body    = $htmlBody;
        $mail->AltBody = strip_tags(str_replace(['<br>','<br/>','</p>','</li>'], "\n", $htmlBody));

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log('Mailer error: ' . $mail->ErrorInfo);
        return false;
    }
}

function emailWelcome(string $name): string {
    return emailWrap("Welcome to " . SITE_NAME . "!", "
        <h2 style='color:#0f3460'>Welcome, " . htmlspecialchars($name) . "! 🎉</h2>
        <p>Your account has been created successfully on <strong>" . SITE_NAME . "</strong>.</p>
        <p>You can now:</p>
        <ul>
            <li>Browse 1,000+ products across 15 categories</li>
            <li>Add items to your cart and place orders</li>
            <li>Track your orders in real time</li>
            <li>Chat with our AI assistant 24/7</li>
        </ul>
        <p style='text-align:center;margin-top:24px'>
            <a href='" . SITE_URL . "/products.php'
               style='background:#0f3460;color:#fff;padding:12px 28px;border-radius:6px;text-decoration:none;font-weight:bold;margin-right:8px'>
               Start Shopping →
            </a>
            <a href='" . SITE_URL . "/login.php'
               style='background:#e94560;color:#fff;padding:12px 28px;border-radius:6px;text-decoration:none;font-weight:bold'>
               Login to Account →
            </a>
        </p>
        <p style='color:#888;font-size:13px;margin-top:24px'>
            If you did not create this account, please ignore this email or contact us at
            <a href='mailto:" . ADMIN_EMAIL . "'>" . ADMIN_EMAIL . "</a>.
        </p>
    ");
}

function emailOrderConfirmation(array $order, array $items): string {
    $rows  = '';
    $total = 0;
    foreach ($items as $item) {
        $subtotal = $item['price'] * $item['quantity'];
        $total   += $subtotal;
        $rows .= "
        <tr>
            <td style='padding:10px;border-bottom:1px solid #eee'>" . htmlspecialchars($item['name']) . "</td>
            <td style='padding:10px;border-bottom:1px solid #eee;text-align:center'>" . $item['quantity'] . "</td>
            <td style='padding:10px;border-bottom:1px solid #eee;text-align:right'>RWF " . number_format($item['price']) . "</td>
            <td style='padding:10px;border-bottom:1px solid #eee;text-align:right'><strong>RWF " . number_format($subtotal) . "</strong></td>
        </tr>";
    }

    $statusColors = [
        'pending'    => '#ffc107',
        'processing' => '#17a2b8',
        'shipped'    => '#007bff',
        'delivered'  => '#28a745',
        'cancelled'  => '#dc3545',
    ];
    $statusBg   = $statusColors[$order['status']] ?? '#6c757d';
    $statusText = $order['status'] === 'pending' ? '#000' : '#fff';

    return emailWrap("Order Confirmation — #" . str_pad($order['id'], 6, '0', STR_PAD_LEFT), "
        <h2 style='color:#0f3460'>Order Confirmed! 🛍️</h2>
        <p>Hi <strong>" . htmlspecialchars($order['customer_name']) . "</strong>, thank you for your order on <strong>" . SITE_NAME . "</strong>.</p>

        <div style='background:#f8f9fa;border-radius:8px;padding:16px;margin:16px 0'>
            <table style='width:100%;font-size:14px;border-collapse:collapse'>
                <tr><td style='padding:6px 0'><strong>Order Number:</strong></td>
                    <td><strong style='color:#e63946'>#" . str_pad($order['id'], 6, '0', STR_PAD_LEFT) . "</strong></td></tr>
                <tr><td style='padding:6px 0'><strong>Date:</strong></td>
                    <td>" . date('d M Y, H:i', strtotime($order['created_at'])) . "</td></tr>
                <tr><td style='padding:6px 0'><strong>Payment Method:</strong></td>
                    <td>" . strtoupper($order['payment_method']) . "</td></tr>
                <tr><td style='padding:6px 0'><strong>Status:</strong></td>
                    <td><span style='background:" . $statusBg . ";color:" . $statusText . ";padding:3px 10px;border-radius:12px;font-size:12px'>" . ucfirst($order['status']) . "</span></td></tr>
                <tr><td style='padding:6px 0'><strong>Delivery Address:</strong></td>
                    <td>" . nl2br(htmlspecialchars($order['address'])) . "</td></tr>
            </table>
        </div>

        <h3 style='color:#0f3460;margin-top:24px'>Items Ordered</h3>
        <table style='width:100%;border-collapse:collapse;font-size:14px'>
            <thead>
                <tr style='background:#0f3460;color:#fff'>
                    <th style='padding:10px;text-align:left'>Product</th>
                    <th style='padding:10px;text-align:center'>Qty</th>
                    <th style='padding:10px;text-align:right'>Unit Price</th>
                    <th style='padding:10px;text-align:right'>Subtotal</th>
                </tr>
            </thead>
            <tbody>" . $rows . "</tbody>
            <tfoot>
                <tr style='background:#f8f9fa'>
                    <td colspan='3' style='padding:12px;text-align:right;font-weight:bold'>TOTAL</td>
                    <td style='padding:12px;text-align:right;font-weight:bold;color:#e63946;font-size:16px'>RWF " . number_format($total) . "</td>
                </tr>
            </tfoot>
        </table>

        <div style='background:#e8f5e9;border-left:4px solid #28a745;padding:14px;border-radius:4px;margin-top:20px'>
            <strong>📦 What happens next?</strong><br>
            <small>Our team will process your order within 24 hours. You will receive an update when it is shipped.
            Kigali deliveries take 1–2 days, other provinces 2–4 days.</small>
        </div>

        <p style='text-align:center;margin-top:24px'>
            <a href='" . SITE_URL . "/order_detail.php?id=" . $order['id'] . "'
               style='background:#0f3460;color:#fff;padding:12px 28px;border-radius:6px;text-decoration:none;font-weight:bold;margin-right:8px'>
               View Order Details →
            </a>
            <a href='" . SITE_URL . "/login.php'
               style='background:#e94560;color:#fff;padding:12px 28px;border-radius:6px;text-decoration:none;font-weight:bold'>
               Login to Account →
            </a>
        </p>
        <p style='color:#888;font-size:13px;margin-top:16px'>
            Questions? Contact us at <a href='mailto:" . ADMIN_EMAIL . "'>" . ADMIN_EMAIL . "</a>
            or chat with our AI assistant on the website.
        </p>
    ");
}

function emailOrderStatusUpdate(array $order, array $items): string {
    $statusConfig = [
        'processing' => [
            'icon'    => '✅',
            'color'   => '#17a2b8',
            'title'   => 'Order Confirmed &amp; Processing',
            'message' => 'Great news! Your order has been confirmed and is now being processed by our team.',
            'eta'     => 'Your order will be packed and ready for dispatch within <strong>24 hours</strong>.',
        ],
        'shipped' => [
            'icon'    => '🚚',
            'color'   => '#007bff',
            'title'   => 'Your Order Has Been Shipped!',
            'message' => 'Your order is on its way! Our delivery team has picked up your package.',
            'eta'     => 'Expected delivery: <strong>Kigali: 1–2 business days</strong> | Other provinces: <strong>2–4 business days</strong>.',
        ],
        'delivered' => [
            'icon'    => '🎉',
            'color'   => '#28a745',
            'title'   => 'Order Delivered Successfully!',
            'message' => 'Your order has been delivered. We hope you love your purchase!',
            'eta'     => 'If you have any issues, you can return items within <strong>7 days</strong> of delivery.',
        ],
        'cancelled' => [
            'icon'    => '❌',
            'color'   => '#dc3545',
            'title'   => 'Order Cancelled',
            'message' => 'Your order has been cancelled. If you did not request this, please contact us immediately.',
            'eta'     => 'Any payment made will be refunded within <strong>3–5 business days</strong>.',
        ],
    ];

    $cfg = $statusConfig[$order['status']] ?? [
        'icon' => '📦', 'color' => '#6c757d',
        'title' => 'Order Update', 'message' => 'Your order status has been updated.', 'eta' => '',
    ];

    $rows = '';
    foreach ($items as $item) {
        $subtotal = $item['price'] * $item['quantity'];
        $rows .= "
        <tr>
            <td style='padding:10px;border-bottom:1px solid #eee'>" . htmlspecialchars($item['name']) . "</td>
            <td style='padding:10px;border-bottom:1px solid #eee;text-align:center'>" . $item['quantity'] . "</td>
            <td style='padding:10px;border-bottom:1px solid #eee;text-align:right'>RWF " . number_format($item['price']) . "</td>
            <td style='padding:10px;border-bottom:1px solid #eee;text-align:right'><strong>RWF " . number_format($subtotal) . "</strong></td>
        </tr>";
    }

    // Progress timeline
    $steps   = ['pending'=>'Order Placed','processing'=>'Confirmed','shipped'=>'Shipped','delivered'=>'Delivered'];
    $reached = false;
    $timeline = "<div style='display:flex;justify-content:space-between;align-items:flex-start;margin:20px 0;padding:16px;background:#f8f9fa;border-radius:8px'>";
    foreach ($steps as $key => $label) {
        $isCurrent = ($key === $order['status']);
        if ($isCurrent) $reached = true;
        $isPast  = !$reached;
        $dotColor = $isCurrent ? $cfg['color'] : ($isPast ? '#28a745' : '#dee2e6');
        $txtColor = $isCurrent ? $cfg['color'] : ($isPast ? '#28a745' : '#aaa');
        $weight   = $isCurrent ? '700' : '400';
        $symbol   = $isCurrent ? '●' : ($isPast ? '✓' : '○');
        $timeline .= "<div style='text-align:center;flex:1'>
            <div style='width:30px;height:30px;border-radius:50%;background:{$dotColor};color:#fff;
                        display:flex;align-items:center;justify-content:center;margin:0 auto 6px;font-size:.75rem;font-weight:700'>{$symbol}</div>
            <div style='font-size:.7rem;color:{$txtColor};font-weight:{$weight}'>{$label}</div>
        </div>";
        if ($key !== 'delivered') {
            $lineColor = $isPast ? '#28a745' : '#dee2e6';
            $timeline .= "<div style='flex:0.5;border-top:2px solid {$lineColor};margin-top:15px'></div>";
        }
    }
    $timeline .= "</div>";

    $orderNum = '#' . str_pad($order['id'], 6, '0', STR_PAD_LEFT);

    return emailWrap($cfg['icon'] . ' ' . $cfg['title'] . " — {$orderNum}", "
        <div style='text-align:center;margin-bottom:24px'>
            <div style='font-size:3rem'>{$cfg['icon']}</div>
            <h2 style='color:{$cfg['color']};margin:8px 0'>{$cfg['title']}</h2>
            <p style='color:#555'>{$cfg['message']}</p>
        </div>

        {$timeline}

        <div style='background:#f0f7ff;border-left:4px solid {$cfg['color']};padding:14px;border-radius:4px;margin-bottom:20px'>
            <strong>⏱️ Estimated Timeline:</strong><br>{$cfg['eta']}
        </div>

        <div style='background:#f8f9fa;border-radius:8px;padding:16px;margin-bottom:20px'>
            <table style='width:100%;font-size:14px;border-collapse:collapse'>
                <tr><td style='padding:5px 0;width:140px'><strong>Order Number:</strong></td>
                    <td><strong style='color:#e63946'>{$orderNum}</strong></td></tr>
                <tr><td style='padding:5px 0'><strong>Status:</strong></td>
                    <td><span style='background:{$cfg['color']};color:#fff;padding:2px 10px;border-radius:12px;font-size:12px'>" . ucfirst($order['status']) . "</span></td></tr>
                <tr><td style='padding:5px 0'><strong>Delivery Address:</strong></td>
                    <td>" . nl2br(htmlspecialchars($order['address'])) . "</td></tr>
                <tr><td style='padding:5px 0'><strong>Payment:</strong></td>
                    <td>" . strtoupper($order['payment_method']) . "</td></tr>
            </table>
        </div>

        <h3 style='color:#0f3460;margin-bottom:12px'>Items in Your Order</h3>
        <table style='width:100%;border-collapse:collapse;font-size:14px'>
            <thead>
                <tr style='background:#0f3460;color:#fff'>
                    <th style='padding:10px;text-align:left'>Product</th>
                    <th style='padding:10px;text-align:center'>Qty</th>
                    <th style='padding:10px;text-align:right'>Unit Price</th>
                    <th style='padding:10px;text-align:right'>Subtotal</th>
                </tr>
            </thead>
            <tbody>{$rows}</tbody>
            <tfoot>
                <tr style='background:#f8f9fa'>
                    <td colspan='3' style='padding:12px;text-align:right;font-weight:bold'>TOTAL</td>
                    <td style='padding:12px;text-align:right;font-weight:bold;color:#e63946;font-size:16px'>
                        RWF " . number_format($order['total_price']) . "
                    </td>
                </tr>
            </tfoot>
        </table>

        <p style='text-align:center;margin-top:24px'>
            <a href='" . SITE_URL . "/order_detail.php?id=" . $order['id'] . "'
               style='background:#0f3460;color:#fff;padding:12px 28px;border-radius:6px;text-decoration:none;font-weight:bold;margin-right:8px'>
               View Order Details →
            </a>
            <a href='" . SITE_URL . "/login.php'
               style='background:#e94560;color:#fff;padding:12px 28px;border-radius:6px;text-decoration:none;font-weight:bold'>
               Login to Account →
            </a>
        </p>
        <p style='color:#888;font-size:13px;margin-top:16px'>
            Need help? Contact us at <a href='mailto:" . ADMIN_EMAIL . "'>" . ADMIN_EMAIL . "</a>
        </p>
    ");
}

function emailWrap(string $title, string $content): string {
    return "
    <!DOCTYPE html>
    <html>
    <head><meta charset='UTF-8'><meta name='viewport' content='width=device-width,initial-scale=1'></head>
    <body style='margin:0;padding:0;background:#f4f4f4;font-family:Segoe UI,Arial,sans-serif'>
        <table width='100%' cellpadding='0' cellspacing='0' style='background:#f4f4f4;padding:30px 0'>
            <tr><td align='center'>
                <table width='600' cellpadding='0' cellspacing='0'
                       style='background:#fff;border-radius:10px;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,0.08)'>
                    <tr>
                        <td style='background:linear-gradient(135deg,#0f3460,#e94560);padding:28px 32px;text-align:center'>
                            <h1 style='color:#fff;margin:0;font-size:20px'>🤖 " . SITE_NAME . "</h1>
                        </td>
                    </tr>
                    <tr>
                        <td style='padding:32px;color:#333;font-size:15px;line-height:1.7'>
                            " . $content . "
                        </td>
                    </tr>
                    <tr>
                        <td style='background:#f8f9fa;padding:20px 32px;text-align:center;color:#888;font-size:12px;border-top:1px solid #eee'>
                            &copy; " . date('Y') . " " . SITE_NAME . " &nbsp;|&nbsp;
                            <a href='" . SITE_URL . "' style='color:#0f3460'>Visit Store</a> &nbsp;|&nbsp;
                            <a href='mailto:" . ADMIN_EMAIL . "' style='color:#0f3460'>Support</a>
                        </td>
                    </tr>
                </table>
            </td></tr>
        </table>
    </body>
    </html>";
}

// ── Customer support message → admin email ────────────────────
function emailSupportMessage(string $customerName, string $customerEmail, string $message): string {
    return emailWrap("📩 New Support Message", "
        <h2 style='color:#0f3460;margin-top:0'>📩 New Customer Support Request</h2>
        <p>A customer has sent a support message via the chatbot:</p>
        <table width='100%' cellpadding='0' cellspacing='0' style='margin:16px 0'>
            <tr>
                <td style='padding:8px 0;color:#888;width:120px'>From:</td>
                <td style='padding:8px 0;font-weight:600'>" . htmlspecialchars($customerName) . "</td>
            </tr>
            <tr>
                <td style='padding:8px 0;color:#888'>Email:</td>
                <td style='padding:8px 0'><a href='mailto:" . htmlspecialchars($customerEmail) . "' style='color:#0f3460'>" . htmlspecialchars($customerEmail) . "</a></td>
            </tr>
        </table>
        <div style='background:#f8f9fa;border-left:4px solid #e94560;border-radius:6px;padding:16px 20px;margin:16px 0'>
            <p style='margin:0;font-size:15px;color:#333;line-height:1.7'>" . nl2br(htmlspecialchars($message)) . "</p>
        </div>
        <p style='margin-top:20px'>
            <a href='mailto:" . htmlspecialchars($customerEmail) . "' 
               style='background:#0f3460;color:#fff;padding:10px 22px;border-radius:6px;text-decoration:none;font-weight:600;display:inline-block'>
                Reply to Customer →
            </a>
        </p>
        <p style='color:#888;font-size:13px'>Sent via chatbot on " . date('d M Y, H:i') . " (Kigali time)</p>
    ");
}

// ── Auto-reply to customer after support message sent ─────────
function emailSupportAutoReply(string $customerName): string {
    return emailWrap("✅ We received your message", "
        <h2 style='color:#0f3460;margin-top:0'>✅ Message Received!</h2>
        <p>Hi <strong>" . htmlspecialchars($customerName) . "</strong>,</p>
        <p>Thank you for reaching out. We've received your support message and our team will get back to you as soon as possible.</p>
        <p><strong>Expected response time:</strong> within 24 hours (Mon–Sat, 8AM–6PM Kigali time)</p>
        <p>If your issue is urgent, you can also reach us directly:</p>
        <ul style='line-height:2'>
            <li>📧 <a href='mailto:" . ADMIN_EMAIL . "' style='color:#0f3460'>" . ADMIN_EMAIL . "</a></li>
            <li>📱 <a href='tel:" . ADMIN_PHONE . "' style='color:#0f3460'>" . ADMIN_PHONE . "</a></li>
        </ul>
        <a href='" . SITE_URL . "/login.php'
           style='background:#e94560;color:#fff;padding:10px 22px;border-radius:6px;text-decoration:none;font-weight:600;display:inline-block;margin-top:8px'>
            Login to Your Account →
        </a>
    ");
}
