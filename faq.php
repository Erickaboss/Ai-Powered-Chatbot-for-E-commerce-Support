<?php require_once 'includes/header.php'; ?>

<div class="page-hero">
    <div class="container">
        <h2><i class="bi bi-question-circle me-2"></i>Frequently Asked Questions</h2>
        <p>Everything you need to know about shopping with us</p>
    </div>
</div>

<div class="container py-5">
    <div class="row g-4">
        <div class="col-lg-8 mx-auto">

            <?php
            $faqs = [
                'Orders & Delivery' => [
                    ['How long does delivery take?', 'Kigali: 1–2 business days. Other provinces: 2–4 business days. Remote areas: up to 5–7 days. You will receive an SMS/email update once your order is shipped.'],
                    ['What are the shipping fees?', 'Orders above RWF 50,000 get FREE shipping. Orders below RWF 50,000 have a flat rate of RWF 2,000. Express delivery in Kigali costs RWF 3,500.'],
                    ['Can I cancel my order?', 'Yes — you can cancel a pending order from your My Orders page. Once an order is processing or shipped, cancellation is no longer possible.'],
                    ['How do I track my order?', 'Go to My Orders page or ask the AI chatbot "track order [number]". You can also type your order number like #000004 in the chatbot.'],
                ],
                'Payments' => [
                    ['What payment methods do you accept?', 'We accept: Cash on Delivery (COD), MTN Mobile Money (MoMo), Airtel Money, Bank Transfer (BK, Equity, I&M), and Visa/Mastercard.'],
                    ['Is online payment secure?', 'Yes. All online payments are SSL encrypted and processed securely.'],
                    ['When is payment collected for COD?', 'For Cash on Delivery, payment is collected when the delivery agent hands over your package.'],
                ],
                'Returns & Refunds' => [
                    ['What is your return policy?', 'Items can be returned within 7 days of delivery. The item must be unused and in original packaging.'],
                    ['How do I return an item?', 'Email us at ' . ADMIN_EMAIL . ' with your order number and photos of the item. We will arrange a pickup or guide you through the process.'],
                    ['How long do refunds take?', 'Refunds are processed within 3–5 business days after we receive the returned item.'],
                    ['What if I received a wrong or damaged item?', 'Contact us within 7 days with photos. We will send the correct item or issue a full refund at no extra cost.'],
                ],
                'Account & Security' => [
                    ['How do I create an account?', 'Click "Register" in the top navigation. You need your name, email, and a password. Registration is free.'],
                    ['I forgot my password. What do I do?', 'Click "Forgot password?" on the login page. Enter your email and we will send you a reset link valid for 1 hour.'],
                    ['Is my personal data safe?', 'Yes. We never share your personal data with third parties. Passwords are encrypted and credentials are stored securely.'],
                ],
                'AI Chatbot' => [
                    ['What can the AI chatbot do?', 'The chatbot can: find products by name/category/budget, show prices and descriptions, track orders, help place orders, answer delivery/payment questions, and connect you to support — in English, French, or Kinyarwanda.'],
                    ['Can the chatbot place orders for me?', 'Yes! Tell the chatbot "I want [product name]" and it will guide you through the full order process — quantity, delivery address, payment method, and confirmation.'],
                    ['Does the chatbot work 24/7?', 'Yes. The AI chatbot is available 24 hours a day, 7 days a week.'],
                    ['What languages does the chatbot support?', 'English, French, and Kinyarwanda.'],
                ],
            ];
            foreach ($faqs as $section => $items):
            ?>
            <div class="mb-4">
                <h5 class="fw-700 mb-3" style="color:var(--primary);border-bottom:2px solid var(--accent);padding-bottom:8px">
                    <?= htmlspecialchars($section) ?>
                </h5>
                <div class="accordion" id="faq-<?= md5($section) ?>">
                <?php foreach ($items as $i => [$q, $a]): ?>
                <div class="accordion-item border-0 mb-2" style="border-radius:12px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.06)">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed fw-600" type="button"
                                data-bs-toggle="collapse" data-bs-target="#faq-<?= md5($section.$i) ?>"
                                style="border-radius:12px;font-size:.92rem">
                            <?= htmlspecialchars($q) ?>
                        </button>
                    </h2>
                    <div id="faq-<?= md5($section.$i) ?>" class="accordion-collapse collapse">
                        <div class="accordion-body" style="color:#555;font-size:.9rem;line-height:1.7">
                            <?= htmlspecialchars($a) ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>

            <div class="card-clean p-4 text-center mt-4">
                <h6 class="fw-700 mb-2">Still have questions?</h6>
                <p class="text-muted small mb-3">Our AI assistant or support team can help you right away.</p>
                <div class="d-flex gap-2 justify-content-center flex-wrap">
                    <button onclick="toggleChat()" class="btn btn-sm px-4" style="background:linear-gradient(135deg,var(--primary),var(--accent));color:#fff;border-radius:10px;font-weight:600">
                        <i class="bi bi-robot me-2"></i>Ask AI Assistant
                    </button>
                    <a href="mailto:<?= ADMIN_EMAIL ?>" class="btn btn-sm btn-outline-secondary px-4" style="border-radius:10px">
                        <i class="bi bi-envelope me-2"></i>Email Support
                    </a>
                </div>
            </div>

        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
