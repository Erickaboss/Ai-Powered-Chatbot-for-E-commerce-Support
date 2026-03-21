<!-- AI Chatbot Widget -->
<div id="chatbot-widget">
    <div id="chat-header" onclick="toggleChat()">
        <i class="bi bi-robot"></i> AI Support
        <span id="chat-toggle-icon" class="float-end"><i class="bi bi-chevron-up"></i></span>
    </div>
    <div id="chat-body">
        <div id="chat-messages">
            <div class="bot-msg">
                <i class="bi bi-robot"></i> Hi! I'm your AI shopping assistant. How can I help you today?
            </div>
        </div>
        <div id="chat-input-area">
            <input type="text" id="chat-input" placeholder="Ask me anything..." onkeypress="handleKey(event)">
            <button onclick="sendMessage()"><i class="bi bi-send"></i></button>
        </div>
    </div>
</div>

<footer class="bg-dark text-white mt-5 py-4">
    <div class="container text-center">
        <p class="mb-0">&copy; <?= date('Y') ?> <?= SITE_NAME ?>. All rights reserved.</p>
        <small class="text-muted">Powered by AI Chatbot Support</small>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= SITE_URL ?>/assets/js/chatbot.js"></script>
</body>
</html>
