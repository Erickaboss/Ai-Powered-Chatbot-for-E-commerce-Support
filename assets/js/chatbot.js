// ===== Chatbot Widget JS — Real-Time Streaming + Typing Indicators =====

let chatOpen = true;
let historyLoaded = false;
let isProcessing = false;

// ── Persistent session ID stored in localStorage ──
function generateHex32() {
    try {
        return Array.from(crypto.getRandomValues(new Uint8Array(16)))
                    .map(b => b.toString(16).padStart(2, '0')).join('');
    } catch (e) {
        return Array.from({length: 16}, () =>
            Math.floor(Math.random() * 256).toString(16).padStart(2, '0')).join('');
    }
}

function getChatSessionId() {
    let sid = localStorage.getItem('chat_session_id');
    if (!sid || !/^[a-f0-9]{32}$/.test(sid)) {
        sid = generateHex32();
        localStorage.setItem('chat_session_id', sid);
    }
    return sid;
}
const CHAT_SESSION_ID = getChatSessionId();

function getStreamingApiUrl() {
    return typeof CHATBOT_STREAM_API_URL === 'string' && CHATBOT_STREAM_API_URL.trim()
        ? CHATBOT_STREAM_API_URL
        : '';
}

function normalizeBotMessageHtml(text) {
    return String(text || '').replace(/\r?\n/g, '<br>');
}

function updateTyping(text = 'AI is thinking...') {
    const label = document.querySelector('#typing span');
    if (label) {
        label.textContent = text;
    }
}

function formatIntentLabel(intent) {
    return String(intent || 'your request').replace(/_/g, ' ');
}

function getProcessingMessage(event) {
    if (!event) return 'AI is thinking...';
    if (event.type === 'gemini_complete') return 'AI response is ready...';
    if (event.type !== 'processing') return 'AI is thinking...';
    if (event.using_gemini) return 'AI is preparing a detailed answer...';
    if (event.intent && event.intent !== 'unknown') {
        return `AI is working on ${formatIntentLabel(event.intent)}...`;
    }
    return 'AI is thinking...';
}

// ── Load history from DB on widget open ──
async function loadChatHistory() {
    if (historyLoaded) return;
    historyLoaded = true;

    try {
        const sid = localStorage.getItem('chat_session_id') || CHAT_SESSION_ID;
        const res = await fetch(CHATBOT_API_URL + '?action=history', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                session_id: sid,
                user_id: (typeof CHATBOT_USER_ID !== 'undefined' && CHATBOT_USER_ID) ? CHATBOT_USER_ID : null
            })
        });
        if (!res.ok) return;
        const data = await res.json();
        if (!data.history || data.history.length === 0) return;

        const messages = document.getElementById('chat-messages');
        // If the user already sent a message while history was loading, do not wipe the thread
        if (messages.querySelector('.user-msg')) {
            return;
        }
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
            <button class="qr-btn" onclick="quickReply('Show me products')">🛍️ Show me products</button>
            <button class="qr-btn" onclick="quickReply('Track my order')">📦 Track my order</button>
            <button class="qr-btn" onclick="quickReply('I have a budget')">💰 I have a budget</button>
            <button class="qr-btn" onclick="quickReply('How to order?')">🛒 How to order?</button>
            <button class="qr-btn" onclick="quickReply('Contact support')">📞 Contact support</button>
        </div>
    </div>`;
    historyLoaded = false;
    // Regenerate session ID
    localStorage.setItem('chat_session_id', generateHex32());
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
    else showSuggestions(e.target.value);
}

const chatSuggestions = [
    'Phones under 300k',
    'Compare Samsung Galaxy A14 and Samsung Galaxy A24',
    'Track my order',
    'How do I place an order?',
    'My orders',
    'Delivery info',
    'Payment methods',
    'I forgot my password',
    'Start a return request',
    'Return policy',
    'I have 50000 RWF',
    'Price of Samsung Galaxy',
    'Show me products',
    'Contact support',
    'Invoice for my order',
];

function showSuggestions(val) {
    const box = document.getElementById('chat-suggestions');
    if (!box) return;
    if (!val || val.length < 2) { box.style.display = 'none'; return; }
    const matches = chatSuggestions.filter(s => s.toLowerCase().includes(val.toLowerCase())).slice(0, 4);
    if (!matches.length) { box.style.display = 'none'; return; }
    box.innerHTML = matches.map(s =>
        `<div class="chat-suggestion-item" onclick="selectSuggestion('${s.replace(/'/g,"\\'")}')">💬 ${s}</div>`
    ).join('');
    box.style.display = 'block';
}

function selectSuggestion(text) {
    document.getElementById('chat-input').value = text;
    const box = document.getElementById('chat-suggestions');
    if (box) box.style.display = 'none';
    sendMessage();
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

function renderProductCards(products) {
    if (!products || products.length === 0) return '';
    const container = document.createElement('div');
    container.className = 'chat-product-grid';
    products.forEach(p => {
        const card = document.createElement('div');
        card.className = 'chat-product-card';
        const imgUrl = p.image || '/ecommerce-chatbot/assets/images/placeholder.jpg';

        const link = document.createElement('a');
        link.href = '/ecommerce-chatbot/product.php?id=' + encodeURIComponent(p.id);
        link.className = 'chat-product-img-link';
        const img = document.createElement('img');
        img.src = imgUrl;
        img.alt = p.name || '';
        img.className = 'chat-product-img';
        img.onerror = function () { this.src = '/ecommerce-chatbot/assets/images/placeholder.jpg'; };
        link.appendChild(img);
        card.appendChild(link);

        const body = document.createElement('div');
        body.className = 'chat-product-body';

        const nameLink = document.createElement('a');
        nameLink.href = '/ecommerce-chatbot/product.php?id=' + encodeURIComponent(p.id);
        nameLink.className = 'chat-product-name';
        nameLink.textContent = p.name;
        body.appendChild(nameLink);

        if (p.brand) {
            const brandSpan = document.createElement('span');
            brandSpan.className = 'chat-product-brand';
            brandSpan.textContent = '(' + p.brand + ')';
            body.appendChild(brandSpan);
        }

        const priceDiv = document.createElement('div');
        priceDiv.className = 'chat-product-price';
        priceDiv.textContent = p.price_formatted || 'RWF ' + Number(p.price).toLocaleString();
        body.appendChild(priceDiv);

        const stockSpan = document.createElement('span');
        stockSpan.className = 'chat-product-stock ' + (p.in_stock ? 'in-stock' : 'out-of-stock');
        stockSpan.textContent = p.in_stock ? '✅ In Stock' : '❌ Out of Stock';
        body.appendChild(stockSpan);

        const actionsDiv = document.createElement('div');
        actionsDiv.className = 'chat-product-actions';

        const viewBtn = document.createElement('a');
        viewBtn.href = '/ecommerce-chatbot/product.php?id=' + encodeURIComponent(p.id);
        viewBtn.className = 'chat-product-btn view-btn';
        viewBtn.textContent = 'View';
        actionsDiv.appendChild(viewBtn);

        if (p.in_stock) {
            const cartBtn = document.createElement('a');
            cartBtn.href = '/ecommerce-chatbot/cart.php?action=add&id=' + encodeURIComponent(p.id);
            cartBtn.className = 'chat-product-btn cart-btn';
            cartBtn.textContent = '🛒 Add to Cart';
            actionsDiv.appendChild(cartBtn);
        }

        body.appendChild(actionsDiv);
        card.appendChild(body);
        container.appendChild(card);
    });
    return container;
}

function appendMessage(text, type, quickReplies, logId = null, meta = {}) {
    const messages = document.getElementById('chat-messages');
    const div = document.createElement('div');
    div.className = type === 'user' ? 'user-msg' : 'bot-msg';
    if (type === 'bot') {
        const icon = document.createElement('i');
        icon.className = 'bi bi-robot';
        div.appendChild(icon);
        div.innerHTML += ' ' + String(text || '').replace(/\n/g, '<br>');
    } else {
        div.textContent = text;
    }

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
    div.innerHTML = `<i class="bi bi-three-dots"></i> <span>AI is thinking...</span>`;
    div.style.cssText = 'padding:8px 12px;background:rgba(255,255,255,0.05);border-radius:18px;display:inline-block;margin:4px 0;animation: pulse 1.5s infinite';
    messages.appendChild(div);
    messages.scrollTop = messages.scrollHeight;
}

function removeTyping() {
    const t = document.getElementById('typing');
    if (t) t.remove();
}

async function fetchStandardChatResponse(msg) {
    const res = await fetch(CHATBOT_API_URL, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            message: msg,
            session_id: localStorage.getItem('chat_session_id') || CHAT_SESSION_ID,
            user_id: (typeof CHATBOT_USER_ID !== 'undefined' && CHATBOT_USER_ID) ? CHATBOT_USER_ID : null
        })
    });

    if (!res.ok) {
        console.error('API returned status:', res.status);
        throw new Error('HTTP ' + res.status);
    }

    const text = await res.text();
    // console.log('API Response:', text);
    try {
        return JSON.parse(text);
    } catch (e) {
        console.error('Chatbot non-JSON response:', text);
        throw new Error('Invalid response');
    }
}


async function fetchStreamingChatResponse(msg) {
    // Skip streaming, use standard response
    return fetchStandardChatResponse(msg);
}

async function sendMessage() {
    const input = document.getElementById('chat-input');
    const msg = input.value.trim();
    
    // If no message or already processing, return
    if (!msg || isProcessing) return;
    
    // Set processing flag
    isProcessing = true;

    // Show user message
    appendMessage(msg, 'user');
    
    input.value = '';
    // Adapt voice recognition to detected language
    setVoiceLanguage(detectLanguage(msg));
    showTyping();

    try {
        const data = await fetchStreamingChatResponse(msg);
        
        removeTyping();

        // Keep localStorage in sync with server-confirmed session_id
        if (data.session_id && /^[a-f0-9]{32}$/.test(data.session_id)) {
            localStorage.setItem('chat_session_id', data.session_id);
        }

        // api/chatbot.php returns { response, quick_replies, session_id, log_id } — no "type" field.
        // Streaming / other endpoints may send type === 'response' | 'error'.
        if (data.type === 'error') {
            appendMessage(data.response || 'Error occurred', 'bot', data.quick_replies || []);
        } else if (typeof data.response === 'string') {
            appendMessage(
                data.response || 'Sorry, I could not process that.',
                'bot',
                data.quick_replies || [],
                data.log_id || null,
                {
                    response_source: data.response_source || '',
                    intent: data.intent || '',
                    confidence: data.confidence || 0,
                    products: data.products || []
                }
            );
            if (data.processing_time_ms) {
                // console.log(`Response time: ${data.processing_time_ms}ms`);
            }
        } else {
            appendMessage('Sorry, I could not process that.', 'bot', ['Show me products', 'Contact support']);
        }
        
    } catch (err) {
        removeTyping();
        console.error('Chatbot error:', err);
        appendMessage('Sorry, something went wrong. Please try again in a moment.', 'bot', ['Show me products', 'Contact support']);
    } finally {
        // Reset processing flag
        isProcessing = false;
    }
}

// ── Auto-load history on page ready ──
document.addEventListener('DOMContentLoaded', () => {
    // Small delay so the widget renders first
    setTimeout(loadChatHistory, 300);
});

// ── Rate a chatbot response ──
async function rateResponse(logId, rating, el) {
    try {
        await fetch(CHATBOT_API_URL + '?action=rate', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                log_id: logId,
                rating: rating,
                session_id: localStorage.getItem('chat_session_id') || CHAT_SESSION_ID
            })
        });
        el.innerHTML = rating === 1
            ? '<span style="color:#4caf50">👍 Thanks for your feedback!</span>'
            : '<span style="color:#e94560">👎 Thanks! We\'ll improve.</span>';
    } catch(e) {}
}

// ================================================================
// VOICE INPUT FEATURE - Speech-to-Text using Web Speech API
// ================================================================

let isListening = false;
let recognition = null;

// Detect language from text (simple client-side check)
function detectLanguage(text) {
    const t = text.toLowerCase();
    const rwWords = ['mwaramutse','mwiriwe','muraho','yego','oya','urakoze','murakoze','angahe','amafaranga','ibiciro','gusaba','kugura','fasha','mfasha'];
    const frWords = ['bonjour','salut','merci','combien','prix','livraison','commande','paiement','retour','produit','cherche','besoin','voulez','voudriez'];
    let rw = 0, fr = 0;
    const tokens = t.split(/\s+/);
    for (const token of tokens) {
        if (rwWords.includes(token)) rw++;
        if (frWords.includes(token)) fr++;
    }
    if (rw > fr && rw > 0) return 'rw-RW';
    if (fr > 0) return 'fr-FR';
    return 'en-US';
}

// Initialize voice recognition if supported
function initVoiceRecognition() {
    // Check browser support
    const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
    
    if (!SpeechRecognition) {
        // console.log('Voice input not supported in this browser');
        return null;
    }
    
    const recognition = new SpeechRecognition();
    recognition.lang = detectLanguage(document.getElementById('chat-input')?.value) || 'en-US';
    recognition.continuous = false;
    recognition.interimResults = false;
    recognition.maxAlternatives = 1;
    
    recognition.onstart = function() {
        isListening = true;
        updateVoiceButtonState();
    };
    
    recognition.onresult = function(event) {
        const transcript = event.results[0][0].transcript;
        const confidence = event.results[0].confidence;
        
        // console.log(`🎤 Voice input: "${transcript}" (${(confidence * 100).toFixed(0)}% confidence)`);
        
        // Set the transcribed text in chat input
        const input = document.getElementById('chat-input');
        input.value = transcript;
        
        // Auto-send after short delay
        setTimeout(() => {
            sendMessage();
        }, 500);
    };
    
    recognition.onerror = function(event) {
        console.error('Voice recognition error:', event.error);
        isListening = false;
        updateVoiceButtonState();
        
        if (event.error === 'no-speech') {
            alert('No speech detected. Please try again.');
        } else if (event.error === 'audio-capture') {
            alert('No microphone found. Please ensure microphone is connected.');
        } else if (event.error === 'not-allowed') {
            alert('Microphone permission denied. Please allow microphone access.');
        }
    };
    
    recognition.onend = function() {
        isListening = false;
        updateVoiceButtonState();
    };
    
    return recognition;
}

// Toggle voice input
function toggleVoiceInput() {
    if (!recognition) {
        recognition = initVoiceRecognition();
        if (!recognition) {
            alert('Voice input is not supported in your browser. Please use Chrome, Edge, or Safari.');
            return;
        }
    }
    
    if (isListening) {
        recognition.stop();
    } else {
        try {
            recognition.start();
        } catch (e) {
            console.error('Failed to start recognition:', e);
            alert('Failed to start voice input. Please try again.');
        }
    }
}

// Update voice button visual state
function updateVoiceButtonState() {
    const voiceBtn = document.getElementById('voice-input-btn');
    if (!voiceBtn) return;
    
    if (isListening) {
        voiceBtn.classList.add('listening');
        voiceBtn.innerHTML = '<i class="bi bi-mic-fill"></i>';
        voiceBtn.title = 'Listening... Click to stop';
    } else {
        voiceBtn.classList.remove('listening');
        voiceBtn.innerHTML = '<i class="bi bi-mic"></i>';
        voiceBtn.title = 'Voice Input';
    }
}

// Add multilingual support
function setVoiceLanguage(langCode) {
    if (recognition) {
        recognition.lang = langCode;
    }
}

// Create voice input button in chat UI
function createVoiceInputButton() {
    // Check if browser supports it
    if (!window.SpeechRecognition && !window.webkitSpeechRecognition) {
        return; // Don't show button if not supported
    }
    
    // Find the input area and add voice button
    const inputContainer = document.querySelector('.chat-input-area');
    if (!inputContainer) return;
    
    const voiceButton = document.createElement('button');
    voiceButton.id = 'voice-input-btn';
    voiceButton.className = 'btn btn-sm voice-input-btn';
    voiceButton.innerHTML = '<i class="bi bi-mic"></i>';
    voiceButton.title = 'Voice Input';
    voiceButton.onclick = toggleVoiceInput;
    
    // Insert before send button
    const sendButton = inputContainer.querySelector('button[type="button"]');
    if (sendButton) {
        inputContainer.insertBefore(voiceButton, sendButton);
    } else {
        inputContainer.appendChild(voiceButton);
    }
    
    // Add CSS styles for listening animation
    const style = document.createElement('style');
    style.textContent = `
        .voice-input-btn {
            background: none;
            border: 2px solid #ddd;
            border-radius: 50%;
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
            color: #666;
        }
        
        .voice-input-btn:hover {
            background: #f5f5f5;
            border-color: #bbb;
        }
        
        .voice-input-btn.listening {
            background: #e94560;
            border-color: #e94560;
            color: white;
            animation: pulse 1s infinite;
        }
        
        @keyframes pulse {
            0% { transform: scale(1); box-shadow: 0 0 0 0 rgba(233, 69, 96, 0.7); }
            50% { transform: scale(1.1); box-shadow: 0 0 0 10px rgba(233, 69, 96, 0); }
            100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(233, 69, 96, 0); }
        }
    `;
    document.head.appendChild(style);
}

// Initialize voice input on page load
document.addEventListener('DOMContentLoaded', () => {
    setTimeout(() => {
        createVoiceInputButton();
    }, 500);
});

// ── File/Image upload handler ──
let pendingFile = null;

function handleChatFileUpload(input) {
    const file = input.files[0];
    if (!file) return;
    pendingFile = file;

    const preview = document.getElementById('chat-file-preview');
    const isImage = file.type.startsWith('image/');
    preview.style.display = 'flex';

    if (isImage) {
        const reader = new FileReader();
        reader.onload = e => {
            preview.innerHTML = '';
            const img = document.createElement('img');
            img.src = e.target.result;
            img.style.cssText = 'height:40px;border-radius:6px;object-fit:cover';
            preview.appendChild(img);
            const span = document.createElement('span');
            span.textContent = '📷 ' + file.name;
            preview.appendChild(span);
            const btn = document.createElement('button');
            btn.onclick = clearFileUpload;
            btn.style.cssText = 'background:none;border:none;color:#e94560;cursor:pointer;margin-left:auto';
            btn.textContent = '✕';
            preview.appendChild(btn);
        };
        reader.readAsDataURL(file);
    } else {
        preview.innerHTML = '';
        const icon = document.createElement('i');
        icon.className = 'bi bi-file-earmark-text';
        icon.style.fontSize = '1.2rem';
        preview.appendChild(icon);
        const span = document.createElement('span');
        span.textContent = '📄 ' + file.name;
        preview.appendChild(span);
        const btn = document.createElement('button');
        btn.onclick = clearFileUpload;
        btn.style.cssText = 'background:none;border:none;color:#e94560;cursor:pointer;margin-left:auto';
        btn.textContent = '✕';
        preview.appendChild(btn);
    }

    // Don't auto-fill — let user type their own message
    const input2 = document.getElementById('chat-input');
    if (!input2.value) {
        input2.placeholder = isImage ? 'Ask about this image...' : 'Ask about this document...';
    }
    input2.focus();
}

function clearFileUpload() {
    pendingFile = null;
    document.getElementById('chat-file-upload').value = '';
    const preview = document.getElementById('chat-file-preview');
    preview.style.display = 'none';
    preview.innerHTML = '';
}

// Override sendMessage to handle file uploads
const _originalSendMessage = sendMessage;
sendMessage = async function() {
    if (!pendingFile) {
        return _originalSendMessage();
    }

    const input = document.getElementById('chat-input');
    const msg = input.value.trim() || (pendingFile.type.startsWith('image/') ? 'I uploaded an image, do you have this product?' : 'I uploaded a document about a product.');

    appendMessage(msg + ' 📎 ' + pendingFile.name, 'user');
    input.value = '';
    showTyping();

    try {
        const formData = new FormData();
        formData.append('message', msg);
        formData.append('session_id', localStorage.getItem('chat_session_id') || CHAT_SESSION_ID);
        formData.append('file', pendingFile);

        const res = await fetch(CHATBOT_API_URL + '?action=upload', {
            method: 'POST',
            body: formData
        });

        const data = await res.json();
        removeTyping();
        if (data.session_id) localStorage.setItem('chat_session_id', data.session_id);
        appendMessage(data.response || 'Sorry, I could not process that file.', 'bot', data.quick_replies || [], data.log_id || null);
        clearFileUpload();
    } catch(e) {
        removeTyping();
        appendMessage('Sorry, I could not process the file. Please try again.', 'bot', ['Show me products']);
        clearFileUpload();
    }
};
