// ===== Chatbot Widget JS — Real-Time Streaming + Typing Indicators =====

let chatOpen = true;
let historyLoaded = false;
let isProcessing = false;

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
            body: JSON.stringify({ session_id: sid })
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
    else showSuggestions(e.target.value);
}

const chatSuggestions = [
    'Show me phones under 200k',
    'Show me laptops',
    'Track my order',
    'My orders',
    'Delivery info',
    'Payment methods',
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

function appendMessage(text, type, quickReplies, logId = null) {
    const messages = document.getElementById('chat-messages');
    const div = document.createElement('div');
    div.className = type === 'user' ? 'user-msg' : 'bot-msg';
    div.innerHTML = type === 'bot'
        ? `<i class="bi bi-robot"></i> ${normalizeBotMessageHtml(text)}`
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

    // ── Rating buttons (only for bot messages) ──
    if (type === 'bot' && logId) {
        const rateDiv = document.createElement('div');
        rateDiv.className = 'chat-rating';
        rateDiv.style.cssText = 'margin-top:4px;font-size:.72rem;color:rgba(255,255,255,.5)';
        rateDiv.innerHTML = `<span style="margin-right:4px">Was this helpful?</span>
            <button onclick="rateResponse(${logId}, 1, this.parentElement)" style="background:none;border:none;cursor:pointer;font-size:.9rem;padding:0 3px" title="Yes">👍</button>
            <button onclick="rateResponse(${logId}, 0, this.parentElement)" style="background:none;border:none;cursor:pointer;font-size:.9rem;padding:0 3px" title="No">👎</button>`;
        div.appendChild(rateDiv);
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
            session_id: localStorage.getItem('chat_session_id') || CHAT_SESSION_ID
        })
    });

    if (!res.ok) {
        throw new Error('HTTP ' + res.status);
    }

    const text = await res.text();
    try {
        return JSON.parse(text);
    } catch (e) {
        console.error('Chatbot non-JSON response:', text);
        throw new Error('Invalid response');
    }
}

async function fetchStreamingChatResponse(msg) {
    const streamUrl = getStreamingApiUrl();
    if (!streamUrl) {
        return fetchStandardChatResponse(msg);
    }

    const res = await fetch(streamUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            message: msg,
            session_id: localStorage.getItem('chat_session_id') || CHAT_SESSION_ID
        })
    });

    if (!res.ok) {
        throw new Error('HTTP ' + res.status);
    }

    if (!res.body || typeof res.body.getReader !== 'function') {
        return fetchStandardChatResponse(msg);
    }

    const reader = res.body.getReader();
    const decoder = new TextDecoder();
    let buffer = '';
    let finalEvent = null;

    const processLine = (line) => {
        if (!line) return;

        let event;
        try {
            event = JSON.parse(line);
        } catch (err) {
            console.warn('Skipping invalid stream event:', line);
            return;
        }

        if (event.type === 'typing' || event.type === 'processing' || event.type === 'gemini_complete') {
            updateTyping(getProcessingMessage(event));
        }

        if (event.type === 'response' || event.type === 'error') {
            finalEvent = event;
        }
    };

    while (true) {
        const { value, done } = await reader.read();
        buffer += decoder.decode(value || new Uint8Array(), { stream: !done });

        const lines = buffer.split(/\r?\n/);
        buffer = lines.pop() || '';

        lines.forEach(line => processLine(line.trim()));

        if (done) {
            break;
        }
    }

    processLine(buffer.trim());

    if (!finalEvent) {
        throw new Error('Stream completed without a final response');
    }

    return finalEvent;
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
                data.log_id || null
            );
            if (data.processing_time_ms) {
                console.log(`Response time: ${data.processing_time_ms}ms`);
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

// Initialize voice recognition if supported
function initVoiceRecognition() {
    // Check browser support
    const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
    
    if (!SpeechRecognition) {
        console.log('Voice input not supported in this browser');
        return null;
    }
    
    const recognition = new SpeechRecognition();
    recognition.lang = 'en-US'; // Default to English
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
        
        console.log(`🎤 Voice input: "${transcript}" (${(confidence * 100).toFixed(0)}% confidence)`);
        
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
            preview.innerHTML = `<img src="${e.target.result}" style="height:40px;border-radius:6px;object-fit:cover">
                <span>📷 ${file.name}</span>
                <button onclick="clearFileUpload()" style="background:none;border:none;color:#e94560;cursor:pointer;margin-left:auto">✕</button>`;
        };
        reader.readAsDataURL(file);
    } else {
        preview.innerHTML = `<i class="bi bi-file-earmark-text" style="font-size:1.2rem"></i>
            <span>📄 ${file.name}</span>
            <button onclick="clearFileUpload()" style="background:none;border:none;color:#e94560;cursor:pointer;margin-left:auto">✕</button>`;
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
