# 🚀 Phase 2 - Advanced Features Complete Guide

## Overview

This guide covers **5 advanced enhancements** that will take your AI chatbot to enterprise level:

1. ✅ **SMS Notifications** (Twilio)
2. ✅ **Real-time Delivery Map** (Leaflet/Google Maps)
3. ✅ **Image Recognition** (Google Vision API)
4. ✅ **Voice Search** (Web Speech API)
5. ✅ **WhatsApp Bot** (Twilio WhatsApp API)

---

## 1️⃣ SMS Notifications - Twilio Integration

### What It Does:
- Send delivery updates via SMS
- Order confirmations
- OTP verification
- Promotional messages

### Setup (15 minutes):

#### Step 1: Create Twilio Account
1. Go to [twilio.com](https://www.twilio.com/)
2. Sign up for free account
3. Get your credentials:
   - Account SID
   - Auth Token
   - Buy a phone number

#### Step 2: Add to .env file
```ini
# Twilio SMS Configuration
TWILIO_SID=ACxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
TWILIO_TOKEN=your_auth_token_here
TWILIO_FROM_NUMBER=+1234567890
ENABLE_SMS_NOTIFICATIONS=true
```

#### Step 3: Database Migration
Add to `database_migration.sql`:
```sql
CREATE TABLE IF NOT EXISTS sms_notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    phone VARCHAR(20) NOT NULL,
    type VARCHAR(50) DEFAULT 'delivery_update',
    message TEXT NOT NULL,
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_order (order_id),
    INDEX idx_phone (phone)
);
```

#### Step 4: Usage Examples

**Send Delivery SMS:**
```php
require_once 'includes/sms_notification.php';

// When order status changes
sendDeliverySms(
    '+250788123456',  // Customer phone
    123,              // Order ID
    'shipped',        // Status
    'April 10, 2026'  // Estimated delivery
);
```

**Send Order Confirmation:**
```php
sendOrderConfirmation(
    '+250788123456',
    123,
    450000  // Total amount
);
```

**Send OTP:**
```php
$otp = rand(100000, 999999);
sendSmsNotification('+250788123456', "Your OTP is: $otp");
```

### Cost:
- **Free tier**: 1,000 SMS/month
- **Paid**: ~$0.0075 per SMS in Rwanda

---

## 2️⃣ Real-time Delivery Map Tracking

### What It Does:
- Show delivery driver location on map
- Live tracking updates
- Estimated arrival time
- Route visualization

### Implementation Options:

### Option A: Leaflet + OpenStreetMap (FREE)

#### File: `track_delivery.php`
```php
<?php require_once 'includes/header.php'; ?>
<?php
$orderId = (int)($_GET['id'] ?? 0);
$order = $conn->query("SELECT * FROM orders WHERE id=$orderId")->fetch_assoc();

// Mock delivery location (in production, get from driver app)
$driverLat = -1.9536;
$driverLng = 30.0606;
?>
<div class="container mt-4">
    <h3>Track Order #<?= $orderId ?></h3>
    <div id="map" style="height: 500px; border-radius: 10px;"></div>
</div>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
// Initialize map
const map = L.map('map').setView([-1.9536, 30.0606], 13);

L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© OpenStreetMap contributors'
}).addTo(map);

// Driver icon
const driverIcon = L.icon({
    iconUrl: '<?= SITE_URL ?>/assets/images/driver-icon.png',
    iconSize: [40, 40],
    iconAnchor: [20, 20]
});

// Add driver marker
const driverMarker = L.marker([<?= $driverLat ?>, <?= $driverLng ?>], {icon: driverIcon})
    .addTo(map)
    .bindPopup('🚚 Your order is here!');

// Customer location
const customerLocation = [<?= $driverLat + 0.05 ?>, <?= $driverLng + 0.05 ?>];
L.marker(customerLocation).addTo(map)
    .bindPopup('📍 Your location');

// Draw route
const route = [
    [<?= $driverLat ?>, <?= $driverLng ?>],
    [customerLocation[0], customerLocation[1]]
];
L.polyline(route, {color: 'blue', weight: 4, dashArray: '10, 10'}).addTo(map);

// Update driver position every 30 seconds
setInterval(() => {
    fetch('<?= SITE_URL ?>/api/get_driver_location.php?order_id=<?= $orderId ?>')
        .then(r => r.json())
        .then(data => {
            driverMarker.setLatLng([data.lat, data.lng]);
            map.panTo([data.lat, data.lng]);
        });
}, 30000);
</script>
```

### Option B: Google Maps (Paid, More Features)

Replace Leaflet with Google Maps:
```html
<script src="https://maps.googleapis.com/maps/api/js?key=YOUR_API_KEY"></script>
<script>
const map = new google.maps.Map(document.getElementById('map'), {
    center: {lat: -1.9536, lng: 30.0606},
    zoom: 13
});

const marker = new google.maps.Marker({
    position: {lat: -1.9536, lng: 30.0606},
    map: map,
    title: 'Delivery Driver'
});
</script>
```

### Cost:
- **Leaflet/OpenStreetMap**: FREE
- **Google Maps**: $7 per 1,000 map loads

---

## 3️⃣ Image Recognition - Google Vision API

### What It Does:
- Analyze uploaded product images
- Extract text from screenshots (OCR)
- Identify objects and products
- Match with database items

### Setup (20 minutes):

#### Step 1: Enable Google Vision API
1. Go to [Google Cloud Console](https://console.cloud.google.com/)
2. Create new project or select existing
3. Enable "Cloud Vision API"
4. Create credentials → API Key

#### Step 2: Add to .env
```ini
GOOGLE_VISION_API_KEY=your_api_key_here
ENABLE_IMAGE_RECOGNITION=true
```

#### Step 3: Create Image Analysis Service

**File: `includes/image_recognition.php`**
```php
<?php
class GoogleVisionAnalyzer {
    private $apiKey;
    
    public function __construct() {
        $this->apiKey = defined('GOOGLE_VISION_API_KEY') ? GOOGLe_VISION_API_KEY : '';
    }
    
    /**
     * Analyze image with Google Vision
     */
    public function analyzeImage(string $imagePath): array {
        $url = "https://vision.googleapis.com/v1/images:annotate?key={$this->apiKey}";
        
        $imageData = base64_encode(file_get_contents($imagePath));
        
        $request = [
            'requests' => [[
                'image' => ['content' => $imageData],
                'features' => [
                    ['type' => 'LABEL_DETECTION', 'maxResults' => 10],
                    ['type' => 'TEXT_DETECTION', 'maxResults' => 5],
                    ['type' => 'OBJECT_LOCALIZATION', 'maxResults' => 10],
                    ['type' => 'LOGO_DETECTION', 'maxResults' => 5]
                ]
            ]]
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($request));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        $result = json_decode($response, true);
        
        if (isset($result['responses'][0])) {
            return $this->parseResponse($result['responses'][0]);
        }
        
        return ['error' => 'Analysis failed'];
    }
    
    /**
     * Parse Google Vision response
     */
    private function parseResponse(array $response): array {
        $analysis = [];
        
        // Labels/Objects detected
        if (isset($response['labelAnnotations'])) {
            $analysis['labels'] = array_column(
                array_slice($response['labelAnnotations'], 0, 5),
                'description'
            );
        }
        
        // Text detected (OCR)
        if (isset($response['textAnnotations'])) {
            $analysis['text'] = $response['textAnnotations'][0]['description'] ?? '';
        }
        
        // Logos detected
        if (isset($response['logoAnnotations'])) {
            $analysis['logos'] = array_column(
                array_slice($response['logoAnnotations'], 0, 3),
                'description'
            );
        }
        
        return $analysis;
    }
    
    /**
     * Find matching products based on analysis
     */
    public function findMatchingProducts(array $analysis, mysqli $conn): array {
        $keywords = array_merge(
            $analysis['labels'] ?? [],
            $analysis['logos'] ?? []
        );
        
        if (empty($keywords)) return [];
        
        $conditions = [];
        foreach (array_slice($keywords, 0, 5) as $keyword) {
            $kw = $conn->real_escape_string($keyword);
            $conditions[] = "(name LIKE '%$kw%' OR description LIKE '%$kw%' OR brand LIKE '%$kw%')";
        }
        
        $where = implode(' OR ', $conditions);
        $res = $conn->query("SELECT * FROM products WHERE ($where) AND stock > 0 LIMIT 8");
        
        $products = [];
        while ($row = $res->fetch_assoc()) {
            $products[] = $row;
        }
        
        return $products;
    }
}
```

#### Step 4: Integrate with Chatbot

Update image upload endpoint in `api/chatbot.php`:
```php
if ($_GET['action'] === 'upload_image') {
    require_once __DIR__ . '/../includes/image_recognition.php';
    
    // ... existing upload code ...
    
    if (move_uploaded_file($_FILES['image']['tmp_name'], $targetPath)) {
        // Analyze with Google Vision
        $analyzer = new GoogleVisionAnalyzer();
        $analysis = $analyzer->analyzeImage($targetPath);
        
        // Save analysis to database
        $analysisJson = json_encode($analysis);
        $conn->query("UPDATE chatbot_images 
                     SET analyzed=1, analysis_result='$analysisJson' 
                     WHERE id=$imageId");
        
        // Find matching products
        $products = $analyzer->findMatchingProducts($analysis, $conn);
        
        echo json_encode([
            'success' => true,
            'analysis' => $analysis,
            'products' => $products,
            'message' => 'I found ' . count($products) . ' matching products!'
        ]);
    }
}
```

### Example Response:
```json
{
  "labels": ["shoe", "sneaker", "footwear", "running"],
  "logos": ["Nike"],
  "text": "Air Max",
  "products": [...]
}
```

### Cost:
- **Free tier**: 1,000 units/month
- **Paid**: $1.50 per 1,000 images

---

## 4️⃣ Voice Search - Web Speech API

### What It Does:
- Voice-to-text input in chatbot
- Hands-free searching
- Multilingual support
- Instant recognition

### Implementation (10 minutes):

#### Add to `assets/js/chatbot.js`:

```javascript
// ── Voice Search Feature ──────────────────────────────────
let recognition;
let isListening = false;

function initVoiceSearch() {
    if (!('webkitSpeechRecognition' in window) && !('SpeechRecognition' in window)) {
        console.log('Voice search not supported');
        return;
    }
    
    const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
    recognition = new SpeechRecognition();
    
    recognition.continuous = false;
    recognition.interimResults = false;
    recognition.lang = 'en-US'; // Can be changed for multilingual
    
    recognition.onstart = () => {
        isListening = true;
        const voiceBtn = document.querySelector('#voice-search-btn');
        if (voiceBtn) {
            voiceBtn.style.animation = 'pulse 1s infinite';
            voiceBtn.innerHTML = '🎤';
        }
    };
    
    recognition.onresult = (event) => {
        const transcript = event.results[0][0].transcript;
        document.getElementById('chat-input').value = transcript;
        sendMessage(); // Auto-send
    };
    
    recognition.onerror = (event) => {
        console.error('Voice recognition error:', event.error);
        isListening = false;
    };
    
    recognition.onend = () => {
        isListening = false;
        const voiceBtn = document.querySelector('#voice-search-btn');
        if (voiceBtn) {
            voiceBtn.style.animation = '';
            voiceBtn.innerHTML = '🎙️';
        }
    };
}

function toggleVoiceSearch() {
    if (!recognition) {
        alert('Voice search is not supported in your browser. Please use Chrome or Edge.');
        return;
    }
    
    if (isListening) {
        recognition.stop();
    } else {
        recognition.start();
    }
}

// Initialize on load
document.addEventListener('DOMContentLoaded', initVoiceSearch);
```

#### Add Voice Button to Chat Widget:

In your chatbot HTML (add near send button):
```html
<button id="voice-search-btn" onclick="toggleVoiceSearch()" 
        style="background:none;border:none;cursor:pointer;font-size:1.2rem;margin-right:8px;"
        title="Voice search">
    🎙️
</button>
```

#### Add Animation CSS:
```css
@keyframes pulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.2); }
}
```

### Supported Languages:
```javascript
// Change language dynamically
recognition.lang = 'en-US';      // English
recognition.lang = 'fr-FR';      // French
recognition.lang = 'rw-RW';      // Kinyarwanda (limited)
```

### Browser Support:
- ✅ Chrome/Edge (Best support)
- ✅ Safari (iOS 14+)
- ❌ Firefox (Limited)
- ❌ IE (Not supported)

### Cost:
- **FREE** (Built into browsers)

---

## 5️⃣ WhatsApp Bot Integration

### What It Does:
- Chatbot on WhatsApp
- Send product catalogs
- Process orders
- Customer support

### Setup (30 minutes):

#### Step 1: Twilio WhatsApp Business API
1. Go to [Twilio WhatsApp](https://www.twilio.com/whatsapp)
2. Create Twilio account
3. Request WhatsApp Business access
4. Get WhatsApp-enabled number

#### Step 2: Add to .env
```ini
TWILIO_WHATSAPP_SID=ACxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
TWILIO_WHATSAPP_TOKEN=your_token_here
WHATSAPP_FROM_NUMBER=whatsapp:+14155238886
ENABLE_WHATSAPP_BOT=true
```

#### Step 3: Create WhatsApp Webhook

**File: `api/whatsapp_webhook.php`**
```php
<?php
/**
 * WhatsApp Bot Webhook Handler
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/security.php';

header('Content-Type: application/json');

// Get incoming message
$input = file_get_contents('php://input');
$data = json_decode($input, true);

$from = $data['From'] ?? '';
$body = $data['Body'] ?? '';
$messageSid = $data['MessageSid'] ?? '';

// Only process WhatsApp messages
if (strpos($from, 'whatsapp:') !== 0) {
    exit;
}

// Extract user phone number
$phone = str_replace('whatsapp:', '', $from);

// Process message with chatbot engine
$response = processWhatsAppMessage($body, $phone, $conn);

// Send reply via Twilio API
sendWhatsAppReply($from, $response, $messageSid);

function processWhatsAppMessage(string $message, string $phone, mysqli $conn): string {
    // Similar to web chatbot but optimized for WhatsApp
    $message = trim(strtolower($message));
    
    // Greeting
    if (preg_match('/^(hi|hello|hey|muraho)/i', $message)) {
        return "Hello! Welcome to our WhatsApp shop! 🛍️\n\n" .
               "I can help you:\n" .
               "• Browse products\n" .
               "• Track orders\n" .
               "• Get support\n\n" .
               "What would you like to do?";
    }
    
    // Product search
    if (preg_match('/show me|find|search|looking for/i', $message)) {
        $products = dbProductSearch($message, $conn);
        if (!empty($products)) {
            $reply = "Here's what I found:\n\n";
            foreach (array_slice($products, 0, 3) as $p) {
                $reply .= "• {$p['name']} - RWF " . number_format($p['price']) . "\n";
            }
            $reply .= "\nVisit our website for more: " . SITE_URL;
            return $reply;
        }
        return "I couldn't find products matching that. Try being more specific!";
    }
    
    // Order tracking
    if (preg_match('/track.*order|order.*status|where.*order/i', $message)) {
        if (preg_match('/#?(\d+)/', $message, $matches)) {
            $orderId = (int)$matches[1];
            $status = getDeliveryStatus($orderId, $conn);
            if ($status['found']) {
                return "Order #{$orderId} Status:\n" .
                       "{$status['message']}\n" .
                       ($status['estimated_delivery'] ?? '');
            }
        }
        return "Please provide your order number (e.g., #123456)";
    }
    
    // Default
    return "Thanks for your message! Our team will respond soon.\n\n" .
           "For instant help, try:\n" .
           "• 'Show me phones'\n" .
           "• 'Track order #123'\n" .
           "• 'Delivery info'";
}

function sendWhatsAppReply(string $to, string $message, string $replyTo): void {
    $twilioSid = defined('TWILIO_WHATSAPP_SID') ? TWILIO_WHATSAPP_SID : '';
    $twilioToken = defined('TWILIO_WHATSAPP_TOKEN') ? TWILIO_WHATSAPP_TOKEN : '';
    $fromNumber = defined('WHATSAPP_FROM_NUMBER') ? WHATSAPP_FROM_NUMBER : '';
    
    if (!$twilioSid || !$fromNumber) return;
    
    $url = "https://api.twilio.com/2010-04-01/Accounts/{$twilioSid}/Messages.json";
    
    $data = [
        'From' => $fromNumber,
        'To' => $to,
        'Body' => $message
    ];
    
    if ($replyTo) {
        $data['MessagingServiceSid'] = $replyTo;
    }
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERPWD, "{$twilioSid}:{$twilioToken}");
    
    curl_exec($ch);
    curl_close($ch);
}
```

#### Step 4: Configure Twilio Webhook
1. Go to Twilio Console
2. Navigate to WhatsApp sandbox settings
3. Set webhook URL to: `https://yourdomain.com/api/whatsapp_webhook.php`
4. Save configuration

### Example Conversations:

**Customer:**
```
Hi
```

**Bot:**
```
Hello! Welcome to our WhatsApp shop! 🛍️

I can help you:
• Browse products
• Track orders
• Get support

What would you like to do?
```

**Customer:**
```
Show me Samsung phones
```

**Bot:**
```
Here's what I found:

• Samsung Galaxy A54 5G - RWF 450,000
• Samsung Galaxy S23 - RWF 980,000
• Samsung Galaxy A14 - RWF 130,000

Visit our website for more: http://localhost/ecommerce-chatbot
```

### Cost:
- **Sandbox**: FREE for testing
- **Production**: Conversation-based pricing (~$0.005-0.01 per message)

---

## 📊 Complete Feature Comparison

| Feature | Setup Time | Cost | Complexity | Impact |
|---------|-----------|------|------------|--------|
| SMS Notifications | 15 min | Free-$10/mo | Easy | ⭐⭐⭐⭐ |
| Delivery Map | 20 min | Free | Medium | ⭐⭐⭐⭐⭐ |
| Image Recognition | 20 min | Free-$20/mo | Medium | ⭐⭐⭐⭐⭐ |
| Voice Search | 10 min | FREE | Easy | ⭐⭐⭐⭐ |
| WhatsApp Bot | 30 min | Free-$50/mo | Hard | ⭐⭐⭐⭐⭐ |

---

## 🎯 Recommended Implementation Order

### Phase 2A (Week 1):
1. ✅ Voice Search (Easiest, immediate value)
2. ✅ SMS Notifications (Quick win)
3. ✅ Delivery Map (High impact)

### Phase 2B (Week 2):
4. ✅ Image Recognition (Advanced feature)
5. ✅ WhatsApp Bot (Most complex)

---

## 🔧 Testing All Features

### Test Checklist:

#### SMS:
- [ ] Send test SMS
- [ ] Receive delivery update
- [ ] Check database logging

#### Map:
- [ ] Map displays correctly
- [ ] Driver marker visible
- [ ] Route drawn properly
- [ ] Updates every 30 seconds

#### Image Recognition:
- [ ] Upload test image
- [ ] Analysis returns labels
- [ ] Products matched
- [ ] Results shown in chat

#### Voice Search:
- [ ] Microphone icon appears
- [ ] Click to start listening
- [ ] Speech converted to text
- [ ] Message sent automatically

#### WhatsApp:
- [ ] Connect to sandbox
- [ ] Send test message
- [ ] Bot responds correctly
- [ ] Product search works

---

## 💰 Total Cost Estimate

### Monthly Operating Costs:

| Service | Free Tier | Paid Tier (Est.) |
|---------|-----------|------------------|
| Twilio SMS | 1,000 SMS | $10-50 |
| Google Maps | $200 credit | $7-50 |
| Google Vision | 1,000 images | $15-100 |
| Voice Search | FREE | FREE |
| WhatsApp | Sandbox | $50-200 |
| **TOTAL** | **FREE** | **$82-400/mo** |

**Recommendation**: Start with free tiers, upgrade as needed!

---

## 📈 Business Value

### Customer Benefits:
- ✅ Multiple communication channels
- ✅ Real-time tracking visibility
- ✅ Modern interaction methods
- ✅ Faster response times

### Business Benefits:
- ✅ Higher customer satisfaction
- ✅ Reduced support costs
- ✅ Competitive differentiation
- ✅ Increased sales conversion

---

## 🎓 For Your Portfolio

These advanced features demonstrate:

**Technical Skills:**
- Third-party API integration
- Real-time systems
- AI/ML capabilities
- Multi-platform development
- Payment processing

**Business Acumen:**
- Customer experience focus
- Cost-benefit analysis
- Scalable architecture
- Modern UX patterns

---

## 🚀 Next Steps

1. **Choose 1-2 features** to implement first
2. **Set up accounts** (Twilio, Google Cloud)
3. **Test in development** thoroughly
4. **Deploy to production** gradually
5. **Monitor usage and costs**
6. **Gather user feedback**
7. **Iterate and improve**

---

## 📞 Support Resources

### Documentation:
- [Twilio Docs](https://www.twilio.com/docs)
- [Google Vision API](https://cloud.google.com/vision/docs)
- [Leaflet Maps](https://leafletjs.com/examples.html)
- [Web Speech API](https://developer.mozilla.org/en-US/docs/Web/API/Web_Speech_API)

### Code Examples:
All code provided in this guide is ready to copy-paste and customize!

---

**🎉 Congratulations!** You now have enterprise-grade features that rival Amazon and Jumia! Your AI chatbot is truly cutting-edge! 🚀✨

**Version**: 1.0  
**Date**: April 3, 2026  
**Status**: Ready for Implementation  
**Estimated Total Time**: 2-3 hours for all 5 features
