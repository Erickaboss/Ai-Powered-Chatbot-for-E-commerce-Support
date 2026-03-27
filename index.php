<?php
require_once 'includes/header.php';
$featured   = $conn->query("SELECT p.*, c.name as cat_name FROM products p LEFT JOIN categories c ON p.category_id=c.id WHERE p.stock>0 ORDER BY p.id DESC LIMIT 8");
$categories = $conn->query("SELECT c.*, COUNT(p.id) as total FROM categories c LEFT JOIN products p ON p.category_id=c.id GROUP BY c.id ORDER BY c.id");
$cat_icons  = ['bi-phone','bi-laptop','bi-tv','bi-house-door','bi-person','bi-bag-heart','bi-basket','bi-heart-pulse','bi-bicycle','bi-emoji-smile','bi-lamp','bi-car-front','bi-book','bi-gem','bi-controller'];
?>

<!-- ── HERO ── -->
<section class="hero-section">
    <div class="container">
        <div class="row align-items-center g-5">
            <div class="col-lg-6">
                <div class="badge-pill"><i class="bi bi-stars me-1"></i> AI-Powered Shopping Experience</div>
                <h1 class="mb-3">Shop Smarter with <span style="color:#f5a623">AI Support</span> 24/7</h1>
                <p class="mb-4">Discover 1,000+ products across 15 categories. Our AI assistant helps you find, buy, and track orders — all in one conversation.</p>
                <div class="d-flex flex-wrap gap-3 mb-4">
                    <a href="<?= SITE_URL ?>/products.php" class="btn btn-lg px-4"
                       style="background:linear-gradient(135deg,#e94560,#f5a623);color:#fff;border-radius:12px;font-weight:700;border:none">
                        <i class="bi bi-grid me-2"></i>Shop Now
                    </a>
                    <button class="btn btn-lg px-4" onclick="toggleChat()"
                            style="background:rgba(255,255,255,.12);color:#fff;border:1.5px solid rgba(255,255,255,.3);border-radius:12px;font-weight:600;backdrop-filter:blur(8px)">
                        <i class="bi bi-robot me-2"></i>Ask AI Assistant
                    </button>
                </div>
                <div class="hero-stats">
                    <div class="hero-stat"><div class="num">1,161+</div><div class="lbl">Products</div></div>
                    <div class="hero-stat"><div class="num">15</div><div class="lbl">Categories</div></div>
                    <div class="hero-stat"><div class="num">24/7</div><div class="lbl">AI Support</div></div>
                    <div class="hero-stat"><div class="num">Free</div><div class="lbl">Shipping</div></div>
                </div>
            </div>
            <div class="col-lg-6 d-none d-lg-block">
                <div class="hero-visual">
                    <!-- Chat header -->
                    <div class="d-flex align-items-center gap-3 mb-4 p-3" style="background:rgba(255,255,255,.08);border-radius:12px">
                        <div style="width:44px;height:44px;background:linear-gradient(135deg,#e94560,#f5a623);border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:1.3rem;flex-shrink:0">🤖</div>
                        <div>
                            <div style="color:#fff;font-weight:600;font-size:.9rem">AI Assistant</div>
                            <div style="color:rgba(255,255,255,.5);font-size:.78rem">Always online, always helpful</div>
                        </div>
                        <div class="ms-auto d-flex align-items-center gap-1" style="font-size:.72rem;color:#4caf50">
                            <div style="width:8px;height:8px;background:#4caf50;border-radius:50%;animation:pulse-dot 2s infinite"></div>
                            Online
                        </div>
                    </div>
                    <!-- Sample chat messages -->
                    <div class="mb-2 text-end">
                        <span style="background:rgba(255,255,255,.15);color:#fff;border-radius:12px 12px 4px 12px;padding:8px 14px;font-size:.8rem;display:inline-block">Show me phones under 200k</span>
                    </div>
                    <div class="mb-3">
                        <span style="background:rgba(255,255,255,.92);color:#1a1a2e;border-radius:12px 12px 12px 4px;padding:8px 14px;font-size:.8rem;display:inline-block;max-width:85%">📱 Found 12 smartphones under RWF 200,000 — Tecno, Samsung, Infinix...</span>
                    </div>
                    <div class="mb-2 text-end">
                        <span style="background:rgba(255,255,255,.15);color:#fff;border-radius:12px 12px 4px 12px;padding:8px 14px;font-size:.8rem;display:inline-block">Track order 42</span>
                    </div>
                    <div class="mb-3">
                        <span style="background:rgba(255,255,255,.92);color:#1a1a2e;border-radius:12px 12px 12px 4px;padding:8px 14px;font-size:.8rem;display:inline-block;max-width:85%">📦 Order #42 — Status: <strong>Shipped</strong> 🚚 Expected delivery: tomorrow</span>
                    </div>
                    <!-- Quick reply chips -->
                    <div class="d-flex flex-wrap gap-2 mt-3">
                        <?php foreach (['🛍️ Products','📦 Track Order','💳 Payment','🚚 Delivery'] as $chip): ?>
                        <span style="background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.2);color:rgba(255,255,255,.85);border-radius:50px;padding:4px 12px;font-size:.75rem"><?= $chip ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ── FEATURES STRIP ── -->
<div class="features-strip">
    <div class="container">
        <div class="row g-0">
            <?php foreach ([
                ['bi-truck','#0f3460','#e8f0fe','Free Shipping','On orders above RWF 50,000'],
                ['bi-shield-check','#1b5e20','#e8f5e9','Secure Payment','SSL encrypted transactions'],
                ['bi-arrow-counterclockwise','#e65100','#fff3e0','7-Day Returns','Hassle-free return policy'],
                ['bi-robot','#6a1b9a','#f3e5f5','AI Support 24/7','Instant answers anytime'],
            ] as [$icon,$color,$bg,$title,$sub]): ?>
            <div class="col-6 col-md-3">
                <div class="feature-item">
                    <div class="fi-icon" style="background:<?= $bg ?>;color:<?= $color ?>">
                        <i class="bi <?= $icon ?>"></i>
                    </div>
                    <div>
                        <div class="fi-title"><?= $title ?></div>
                        <div class="fi-sub"><?= $sub ?></div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- ── CATEGORIES ── -->
<section class="py-5">
    <div class="container">
        <div class="text-center mb-4">
            <div class="section-title">Shop by <span>Category</span></div>
            <div class="section-divider mx-auto"></div>
            <p class="section-subtitle">15 categories, 1,161+ products</p>
        </div>
        <div class="row g-3">
            <?php $i=0; while ($cat = $categories->fetch_assoc()): ?>
            <div class="col-6 col-md-4 col-lg-2">
                <a href="products.php?category=<?= $cat['id'] ?>" class="cat-card">
                    <div class="cat-icon"><i class="bi <?= $cat_icons[$i % 15] ?>"></i></div>
                    <h6><?= htmlspecialchars($cat['name']) ?></h6>
                    <small><?= $cat['total'] ?> items</small>
                </a>
            </div>
            <?php $i++; endwhile; ?>
        </div>
    </div>
</section>

<!-- ── FEATURED PRODUCTS ── -->
<section class="py-5" style="background:#fff" id="featured">
    <div class="container">
        <div class="d-flex align-items-end justify-content-between mb-4 flex-wrap gap-2">
            <div>
                <div class="section-title">Featured <span>Products</span></div>
                <div class="section-divider"></div>
            </div>
            <a href="products.php" class="btn-primary-custom btn">View All Products <i class="bi bi-arrow-right ms-1"></i></a>
        </div>
        <div class="row g-4">
            <?php while ($p = $featured->fetch_assoc()): ?>
            <div class="col-6 col-md-4 col-lg-3">
                <div class="card product-card h-100">
                    <div style="overflow:hidden">
                        <img src="<?= strpos($p['image'],'http')===0 ? htmlspecialchars($p['image']) : 'assets/images/'.htmlspecialchars($p['image']) ?>"
                             class="card-img-top"
                             alt="<?= htmlspecialchars($p['name']) ?>"
                             onerror="this.src='assets/images/placeholder.jpg'">
                    </div>
                    <div class="card-body d-flex flex-column">
                        <span class="cat-badge"><?= htmlspecialchars($p['cat_name'] ?? '') ?></span>
                        <h6 class="card-title"><?= htmlspecialchars($p['name']) ?></h6>
                        <div class="d-flex align-items-center justify-content-between mt-auto mb-2">
                            <span class="price-tag">RWF <?= number_format($p['price']) ?></span>
                            <span class="stock-badge-in"><?= $p['stock'] ?> left</span>
                        </div>
                        <a href="product.php?id=<?= $p['id'] ?>" class="btn-add-cart btn">
                            <i class="bi bi-eye me-1"></i>View Details
                        </a>
                    </div>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
    </div>
</section>

<!-- ── AI CHATBOT PROMO ── -->
<section class="py-5">
    <div class="container">
        <div class="chatbot-banner">
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <div class="badge-pill mb-3"><i class="bi bi-robot me-1"></i>AI Assistant — Available 24/7</div>
                    <h3 class="fw-800 mb-2">Need Help? Our AI Assistant is Ready</h3>
                    <p style="color:rgba(255,255,255,.7);margin-bottom:24px">
                        Ask in English, French, or Kinyarwanda. Find products, track orders, get instant answers — 24/7.
                    </p>
                    <div class="d-flex flex-wrap gap-2">
                        <?php foreach (['"Show me phones under 200k"','"Track order 5"','"Return policy"','"Delivery time"'] as $ex): ?>
                        <span style="background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.2);color:rgba(255,255,255,.8);border-radius:50px;padding:5px 14px;font-size:.78rem;cursor:pointer"
                              onclick="document.getElementById('chat-input').value=<?= htmlspecialchars(json_encode(trim($ex,'"'))) ?>;if(!chatOpen)toggleChat();">
                            <?= $ex ?>
                        </span>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="col-lg-4 text-center mt-4 mt-lg-0">
                    <button class="btn btn-lg px-5 py-3 fw-700" onclick="toggleChat()"
                            style="background:#fff;color:#0f3460;border-radius:14px;font-weight:700;font-size:1rem;box-shadow:0 8px 24px rgba(0,0,0,.2)">
                        <i class="bi bi-robot me-2"></i>Chat Now — It's Free
                    </button>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require_once 'includes/footer.php'; ?>
