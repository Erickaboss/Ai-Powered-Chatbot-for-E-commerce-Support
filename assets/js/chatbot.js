// ===== Chatbot Widget JS — Persistent History =====

let chatOpen = true;
let historyLoaded = false;

// ── Persistent session ID stored in localStorage ──
function getChatSessionId() {
    let sid = localStorage.getItem('chat_session_id');
    if (!sid || !/^[a-f0-9]{32}$/.test(sid)) {
        sid = Array.from(crypto.getRandomValues(new Uint8Array(16)))
                   .map(b => b.toString(16).padStart(2, '0')).join('');
        localStorage.setItem('chat_session_id', sid);
    }
    return sid;
}
const CHAT_SESSION_ID = getChatSessionId();

// ── Load history from DB on widget open ──
async function loadChatHistory() {
    if (historyLoaded) return;
    historyLoaded = true; // Set early to prevent duplicate calls

    try {
        const sid = localStorage.getItem('chat_session_id') || CHAT_SESSION_ID;
        const res = await fetch(CHATBOT_API_URL + '?action=history', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ session_id: sid })
        });
        if (!res.ok) return;
        const data = await res.json();
        if (!data.history || data.history.length === 0) return;

        const messages = document.getElementById('chat-messages');
        // Clear the default welcome message before loading history
        messages.innerHTML = '';

        // Show last 20 messages to avoid overwhelming the widget
        const recent = data.history.slice(-20);
        recent.forEach(row => {
            appendMessage(row.message, 'user');
            appendMessage(row.response, 'bot');
        });

        // Divider + clear button
        const divider = document.createElement('div');
        divider.style.cssText = 'text-align:center;font-size:.7rem;color:#aaa;padding:6px 0 2px;margin:4px 0;border-top:1px solid rgba(255,255,255,.08)';
        divider.innerHTML = '— Previous conversation restored —'
            + ' <button onclick="clearChatHistory()" style="background:none;border:none;color:#e94560;font-size:.7rem;cursor:pointer;text-decoration:underline">Clear</button>';
        messages.appendChild(divider);
        messages.scrollTop = messages.scrollHeight;
    } catch (e) {
        historyLoaded = false; // Allow retry on failure
    }
}

// ── Clear history from localStorage and reload widget ──
function clearChatHistory() {
    localStorage.removeItem('chat_session_id');
    const messages = document.getElementById('chat-messages');
    messages.innerHTML = `<div class="bot-msg">
        <i class="bi bi-robot"></i> Hi! I'm your AI shopping assistant.<br>
        I can help you find products, track orders, and answer any question.<br>
        <div class="quick-replies">
            <button class="qr-btn" onclick="quickReply('Show me products')">🛍️ Products</button>
            <button class="qr-btn" onclick="quickReply('Track my order')">📦 Track Order</button>
            <button class="qr-btn" onclick="quickReply('Delivery info')">🚚 Delivery</button>
            <button class="qr-btn" onclick="quickReply('Payment methods')">💳 Payment</button>
        </div>
    </div>`;
    historyLoaded = false;
    // Regenerate session ID
    const newSid = Array.from(crypto.getRandomValues(new Uint8Array(16)))
                        .map(b => b.toString(16).padStart(2, '0')).join('');
    localStorage.setItem('chat_session_id', newSid);
}

function toggleChat() {
    const body = document.getElementById('chat-body');
    const icon = document.getElementById('chat-toggle-icon');
    chatOpen = !chatOpen;
    body.style.display = chatOpen ? 'flex' : 'none';
    icon.innerHTML = chatOpen
        ? '<i class="bi bi-chevron-down"></i>'
        : '<i class="bi bi-chevron-up"></i>';
    // Load history when user opens the chat
    if (chatOpen && !historyLoaded) loadChatHistory();
}

function handleKey(e) {
    if (e.key === 'Enter') sendMessage();
}

function quickReply(text) {
    if (text.startsWith('🛒 Add: add_to_cart:')) {
        document.getElementById('chat-input').value = text.replace('🛒 Add: ', '');
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

    if (quickReplies && quickReplies.length > 0) {
        const qrDiv = document.createElement('div');
        qrDiv.className = 'quick-replies';
        quickReplies.forEach(qr => {
            const btn = document.createElement('button');
            btn.className = 'qr-btn';
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
            body: JSON.stringify({
                message: msg,
                session_id: localStorage.getItem('chat_session_id') || CHAT_SESSION_ID
            })
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
        // Keep localStorage in sync with server-confirmed session_id
        if (data.session_id && /^[a-f0-9]{32}$/.test(data.session_id)) {
            localStorage.setItem('chat_session_id', data.session_id);
        }
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

// ── Auto-load history on page ready ──
document.addEventListener('DOMContentLoaded', () => {
    // Small delay so the widget renders first
    setTimeout(loadChatHistory, 300);
});
