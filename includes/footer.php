<?php
// Hide chatbot widget for admin users and on invoice print page
$hideChat = (($_SESSION['user_role'] ?? '') === 'admin') || basename($_SERVER['PHP_SELF']) === 'invoice.php';
if (!$hideChat):
?>
<!-- ── Chatbot Widget ── -->
<div id="chatbot-widget">
    <div id="chat-header" onclick="toggleChat()">
        <div class="d-flex align-items-center gap-2">
            <div class="bot-avatar">🤖</div>
            <div>
                <div style="font-size:.88rem;font-weight:700">AI Assistant</div>
                <div style="font-size:.72rem;opacity:.8"><span class="status-dot"></span>Online 24/7</div>
            </div>
        </div>
        <span id="chat-toggle-icon"><i class="bi bi-chevron-down"></i></span>
    </div>
    <div id="chat-body">
        <div id="chat-messages">
            <div class="bot-msg">
                🤖 Hi! I'm your AI shopping assistant.<br>
                I can help you find products, track orders, and answer any question.<br>
                <div class="quick-replies">
                    <button class="qr-btn" onclick="quickReply('Show me products')">🛍️ Products</button>
                    <button class="qr-btn" onclick="quickReply('Track my order')">📦 Track Order</button>
                    <button class="qr-btn" onclick="quickReply('Delivery info')">🚚 Delivery</button>
                    <button class="qr-btn" onclick="quickReply('Payment methods')">💳 Payment</button>
                </div>
            </div>
        </div>
        <div id="chat-input-area" style="position:relative">
            <div id="chat-suggestions" style="display:none;position:absolute;bottom:100%;left:0;right:0;background:#1a1a2e;border:1px solid rgba(255,255,255,.1);border-radius:10px;margin-bottom:4px;z-index:999;overflow:hidden"></div>
            
            <input type="text" id="chat-input" placeholder="Type a message..." onkeypress="handleKey(event)" oninput="showSuggestions(this.value)" 
                   class="form-control">
            <button id="chat-send-btn" onclick="sendMessage()" title="Send" 
                    style="position:absolute;right:10px;top:50%;transform:translateY(-50%);background:linear-gradient(135deg,#e94560,#f5a623);border:none;color:white;cursor:pointer;padding:10px 14px;border-radius:8px;z-index:10;transition:all 0.3s ease;box-shadow:0 2px 8px rgba(245,166,35,0.3);" 
                    onmouseover="this.style.transform='translateY(-50%) scale(1.05)'" 
                    onmouseout="this.style.transform='translateY(-50%)'">
                <i class="bi bi-send-fill" style="font-size:1rem"></i>
            </button>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ── Footer ── -->
<footer class="site-footer mt-5">
    <div class="container">
        <div class="row g-4">
            <div class="col-lg-4 col-md-6">
                <div class="d-flex align-items-center gap-2 mb-3">
                    <div style="width:40px;height:40px;background:linear-gradient(135deg,#e94560,#f5a623);border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:1.2rem;">🤖</div>
                    <div style="color:#fff;font-weight:700;font-size:.9rem;line-height:1.2">AI-Powered<br><span style="color:#f5a623;font-size:.78rem;font-weight:400">E-commerce Support</span></div>
                </div>
                <p style="font-size:.85rem;color:rgba(255,255,255,.5);line-height:1.7">
                    Rwanda's smartest online store powered by AI. Shop 1,000+ products with 24/7 intelligent support.
                </p>
                <div class="d-flex gap-2 mt-3">
                    <a href="#" class="social-btn"><i class="bi bi-facebook"></i></a>
                    <a href="#" class="social-btn"><i class="bi bi-twitter-x"></i></a>
                    <a href="#" class="social-btn"><i class="bi bi-instagram"></i></a>
                    <a href="#" class="social-btn"><i class="bi bi-whatsapp"></i></a>
                </div>
            </div>
            <div class="col-lg-2 col-md-3 col-6">
                <h6>Shop</h6>
                <a href="<?= SITE_URL ?>/products.php">All Products</a>
                <a href="<?= SITE_URL ?>/products.php?category=1">Smartphones</a>
                <a href="<?= SITE_URL ?>/products.php?category=2">Laptops</a>
                <a href="<?= SITE_URL ?>/products.php?category=8">Health & Beauty</a>
                <a href="<?= SITE_URL ?>/products.php?category=11">Furniture</a>            </div>
            <div class="col-lg-2 col-md-3 col-6">
                <h6>Help</h6>
                <a href="<?= SITE_URL ?>/faq.php">FAQ</a>
                <a href="<?= SITE_URL ?>/login.php">Login</a>
                <a href="<?= SITE_URL ?>/register.php">Register</a>
                <a href="<?= SITE_URL ?>/orders.php">My Orders</a>
                <a href="<?= SITE_URL ?>/cart.php">My Cart</a>
                <a href="<?= SITE_URL ?>/profile.php">Profile</a>
            </div>
            <div class="col-lg-4 col-md-6">
                <h6>Contact & Support</h6>
                <div style="font-size:.85rem;color:rgba(255,255,255,.55);line-height:2">
                    <div><i class="bi bi-envelope me-2" style="color:#f5a623"></i><?= ADMIN_EMAIL ?></div>
                    <div><i class="bi bi-telephone me-2" style="color:#f5a623"></i><?= ADMIN_PHONE ?></div>
                    <div><i class="bi bi-geo-alt me-2" style="color:#f5a623"></i>Kigali, Rwanda</div>
                    <div><i class="bi bi-clock me-2" style="color:#f5a623"></i>Mon–Sat, 8AM–6PM</div>
                </div>
                <div class="mt-3 p-3" style="background:rgba(255,255,255,.05);border-radius:10px;border:1px solid rgba(255,255,255,.08)">
                    <div style="font-size:.78rem;color:rgba(255,255,255,.5);margin-bottom:6px">🤖 AI Support available 24/7</div>
                    <button class="btn btn-sm w-100" onclick="toggleChat()"
                        style="background:linear-gradient(135deg,#0f3460,#e94560);color:#fff;border-radius:8px;font-size:.82rem;font-weight:600">
                        Chat with AI Assistant
                    </button>
                </div>
            </div>
        </div>
        <div class="footer-bottom d-flex flex-wrap justify-content-between align-items-center gap-2">
            <span>&copy; <?= date('Y') ?> <?= SITE_NAME ?>. All rights reserved.</span>
            <span>Built with PHP &amp; MySQL</span>
        </div>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<?php if (!$hideChat): ?>
<!-- TensorFlow.js and MobileNet for FREE image recognition -->
<script src="https://cdn.jsdelivr.net/npm/@tensorflow/tfjs@latest/dist/tf.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@tensorflow-models/mobilenet@2.1.0/dist/mobilenet.min.js"></script>
<script src="<?= SITE_URL ?>/assets/js/free_image_recognition.js?v=<?= filemtime(__DIR__ . '/../assets/js/free_image_recognition.js') ?>"></script>
<script>
const CHATBOT_API_URL = '<?= SITE_URL ?>/api/chatbot.php';
const CHATBOT_STREAM_API_URL = '<?= SITE_URL ?>/api/chatbot_streaming.php';
</script>
<script src="<?= SITE_URL ?>/assets/js/chatbot.js?v=<?= filemtime(__DIR__ . '/../assets/js/chatbot.js') ?>"></script>
<?php endif; ?>
</body>
</html>
<?php ob_end_flush(); ?>
