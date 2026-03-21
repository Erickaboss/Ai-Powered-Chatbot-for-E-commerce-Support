// ===== Chatbot Widget JS =====

let chatOpen = true;

function toggleChat() {
    const body = document.getElementById('chat-body');
    const icon = document.getElementById('chat-toggle-icon');
    chatOpen = !chatOpen;
    body.style.display = chatOpen ? 'flex' : 'none';
    icon.innerHTML = chatOpen
        ? '<i class="bi bi-chevron-down"></i>'
        : '<i class="bi bi-chevron-up"></i>';
}

function handleKey(e) {
    if (e.key === 'Enter') sendMessage();
}

function quickReply(text) {
    // add_to_cart:ID — send the command but show a friendly label
    if (text.startsWith('🛒 Add: add_to_cart:')) {
        const cmd = text.replace('🛒 Add: ', '');
        const productName = text.replace('🛒 Add: add_to_cart:', 'Product #');
        document.getElementById('chat-input').value = cmd;
        sendMessage();
        return;
    }
    document.getElementById('chat-input').value = text;
    sendMessage();
}

function appendMessage(text, type, quickReplies) {
    const messages = document.getElementById('chat-messages');
    const div = document.createElement('div');
    div.className = type === 'user' ? 'user-msg' : 'bot-msg';
    div.innerHTML = type === 'bot'
        ? `<i class="bi bi-robot"></i> ${text}`
        : text;

    // Render quick reply chips if provided
    if (quickReplies && quickReplies.length > 0) {
        const qrDiv = document.createElement('div');
        qrDiv.className = 'quick-replies';
        quickReplies.forEach(qr => {
            const btn = document.createElement('button');
            btn.className = 'qr-btn';
            // Clean display label for add_to_cart commands
            btn.textContent = qr.startsWith('🛒 Add: add_to_cart:') ? '🛒 Add to Cart' : qr;
            btn.onclick = () => quickReply(qr);
            qrDiv.appendChild(btn);
        });
        div.appendChild(qrDiv);
    }

    messages.appendChild(div);
    messages.scrollTop = messages.scrollHeight;
    return div;
}

function showTyping() {
    const messages = document.getElementById('chat-messages');
    const div = document.createElement('div');
    div.className = 'typing-indicator';
    div.id = 'typing';
    div.textContent = 'AI is typing...';
    messages.appendChild(div);
    messages.scrollTop = messages.scrollHeight;
}

function removeTyping() {
    const t = document.getElementById('typing');
    if (t) t.remove();
}

async function sendMessage() {
    const input = document.getElementById('chat-input');
    const msg = input.value.trim();
    if (!msg) return;

    appendMessage(msg, 'user');
    input.value = '';
    showTyping();

    try {
        const res = await fetch(CHATBOT_API_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ message: msg })
        });
        if (!res.ok) throw new Error('HTTP ' + res.status);
        const text = await res.text();
        let data;
        try { data = JSON.parse(text); }
        catch(e) {
            console.error('Chatbot non-JSON response:', text);
            throw new Error('Invalid response');
        }
        removeTyping();
        appendMessage(
            data.response || 'Sorry, I could not process that.',
            'bot',
            data.quick_replies || []
        );
    } catch (err) {
        removeTyping();
        console.error('Chatbot error:', err);
        appendMessage('Sorry, something went wrong. Please try again in a moment.', 'bot', ['Show me products', 'Contact support']);
    }
}
