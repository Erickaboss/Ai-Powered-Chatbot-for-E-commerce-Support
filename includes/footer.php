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
        <div id="chat-input-area">
            <input type="text" id="chat-input" placeholder="Type a message..." onkeypress="handleKey(event)">
            <button onclick="sendMessage()" title="Send"><i class="bi bi-send-fill"></i></button>
        </div>
    </div>
</div>

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
                <a href="<?= SITE_URL ?>/products.php?category=11">Furniture</a>
            </div>
            <div class="col-lg-2 col-md-3 col-6">
                <h6>Account</h6>
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
            <span>Powered by <strong style="color:#f5a623">Gemini AI</strong> &middot; Built with PHP &amp; MySQL</span>
        </div>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= SITE_URL ?>/assets/js/chatbot.js"></script>
</body>
</html>
<?php ob_end_flush(); ?>
