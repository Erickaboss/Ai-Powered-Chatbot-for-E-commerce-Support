/**
 * FREE Image Recognition using TensorFlow.js
 * No API keys required - runs entirely in the browser!
 * Uses pre-trained MobileNet model for object detection
 */

// Load TensorFlow.js and MobileNet model
let imageClassifier = null;

async function initImageRecognition() {
    if (imageClassifier) return true; // Already loaded
    
    try {
        // Load MobileNet (lightweight, fast model)
        imageClassifier = await mobilenet.load();
        // console.log('✅ FREE Image Recognition loaded!');
        return true;
    } catch (error) {
        console.error('❌ Failed to load image recognition:', error);
        return false;
    }
}

/**
 * Classify uploaded image using TensorFlow.js
 * Returns labels and confidence scores
 */
async function classifyImage(imageElement) {
    if (!imageClassifier) {
        await initImageRecognition();
    }
    
    try {
        const predictions = await imageClassifier.classify(imageElement);
        
        // Format results
        const analysis = {
            labels: predictions.map(p => ({
                label: p.className,
                confidence: p.probability.toFixed(3)
            })),
            topMatch: predictions[0]?.className || 'Unknown',
            confidence: predictions[0]?.probability || 0
        };
        
        // console.log('🔍 Image Analysis:', analysis);
        return analysis;
    } catch (error) {
        console.error('Classification error:', error);
        return { error: 'Failed to analyze image' };
    }
}

/**
 * Find matching products based on image analysis
 * Searches database using detected labels
 */
async function findProductsFromImage(analysis) {
    if (!analysis || analysis.error) return [];
    
    // Get top 3 labels
    const keywords = analysis.labels
        .slice(0, 3)
        .map(l => l.label.split(',')[0]) // Take first word of multi-word labels
        .filter(l => l.length > 3); // Filter out very short words
    
    if (keywords.length === 0) return [];
    
    try {
        // Search products using detected keywords
        const response = await fetch(CHATBOT_API_URL + '?action=search_by_keywords', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ 
                keywords: keywords,
                session_id: CHAT_SESSION_ID
            })
        });
        
        const data = await response.json();
        return data.products || [];
    } catch (error) {
        console.error('Product search failed:', error);
        return [];
    }
}

/**
 * Handle image upload and analysis
 * Integrates with chatbot UI
 */
async function handleImageUploadAndAnalysis(file) {
    return new Promise(async (resolve, reject) => {
        try {
            // Create image element
            const img = document.createElement('img');
            img.onload = async () => {
                const messages = document.getElementById('chat-messages');

                // Analyze image
                const analysis = await classifyImage(img);

                // Display image as user message using DOM
                const userDiv = document.createElement('div');
                userDiv.className = 'user-msg';
                const imgDisplay = document.createElement('img');
                const blobUrl = URL.createObjectURL(file);
                imgDisplay.src = blobUrl;
                imgDisplay.style.cssText = 'max-width: 200px; border-radius: 8px;';
                userDiv.appendChild(imgDisplay);
                messages.appendChild(userDiv);
                messages.scrollTop = messages.scrollHeight;
                URL.revokeObjectURL(blobUrl); // Free memory

                // Show analysis results
                if (analysis.labels && analysis.labels.length > 0) {
                    const botDiv = document.createElement('div');
                    botDiv.className = 'bot-msg';

                    const icon = document.createElement('i');
                    icon.className = 'bi bi-robot';
                    botDiv.appendChild(icon);
                    botDiv.appendChild(document.createTextNode(' '));

                    const txt1 = document.createTextNode('🔍 I see: ');
                    botDiv.appendChild(txt1);
                    const strong1 = document.createElement('strong');
                    strong1.textContent = analysis.topMatch;
                    botDiv.appendChild(strong1);
                    const txt2 = document.createTextNode(' (' + (analysis.confidence * 100).toFixed(1) + '% confidence)');
                    botDiv.appendChild(txt2);
                    botDiv.appendChild(document.createElement('br'));
                    botDiv.appendChild(document.createElement('br'));

                    const strong2 = document.createElement('strong');
                    strong2.textContent = 'Detected objects:';
                    botDiv.appendChild(strong2);
                    botDiv.appendChild(document.createElement('br'));

                    const ul = document.createElement('ul');
                    ul.style.fontSize = '0.9em';
                    analysis.labels.slice(0, 5).forEach(label => {
                        const li = document.createElement('li');
                        li.textContent = label.label + ' (' + (label.confidence * 100).toFixed(0) + '%)';
                        ul.appendChild(li);
                    });
                    botDiv.appendChild(ul);

                    messages.appendChild(botDiv);
                    messages.scrollTop = messages.scrollHeight;

                    // Find matching products
                    const products = await findProductsFromImage(analysis);

                    if (products.length > 0) {
                        const prodDiv = document.createElement('div');
                        prodDiv.className = 'bot-msg';

                        const prodIcon = document.createElement('i');
                        prodIcon.className = 'bi bi-robot';
                        prodDiv.appendChild(prodIcon);
                        prodDiv.appendChild(document.createTextNode(' '));

                        prodDiv.appendChild(document.createTextNode('✨ I found ' + products.length + ' matching products!'));
                        prodDiv.appendChild(document.createElement('br'));
                        prodDiv.appendChild(document.createElement('br'));

                        products.slice(0, 3).forEach(product => {
                            prodDiv.appendChild(document.createTextNode('• ' + product.name + ' - RWF ' + parseInt(product.price).toLocaleString()));
                            prodDiv.appendChild(document.createElement('br'));
                        });

                        messages.appendChild(prodDiv);
                        messages.scrollTop = messages.scrollHeight;
                    } else {
                        appendMessage("I couldn't find matching products. Try describing what you're looking for!", 'bot');
                    }
                }

                resolve(analysis);
            };

            img.onerror = () => reject(new Error('Failed to load image'));
            img.src = URL.createObjectURL(file);
        } catch (error) {
            reject(error);
        }
    });
}

/**
 * Initialize image upload button in chatbot
 */
function setupFreeImageUpload() {
    const inputArea = document.querySelector('.chat-input-area');
    if (!inputArea) return;
    
    // Create file input
    const fileInput = document.createElement('input');
    fileInput.type = 'file';
    fileInput.accept = 'image/*';
    fileInput.style.display = 'none';
    fileInput.id = 'chat-image-upload';
    
    // Create upload button
    const uploadBtn = document.createElement('button');
    uploadBtn.innerHTML = '📷';
    uploadBtn.title = 'Upload image for visual search';
    uploadBtn.style.cssText = `
        background: none;
        border: none;
        cursor: pointer;
        font-size: 1.2rem;
        margin-right: 8px;
        transition: transform 0.2s;
    `;
    uploadBtn.onmouseover = () => uploadBtn.style.transform = 'scale(1.1)';
    uploadBtn.onmouseout = () => uploadBtn.style.transform = 'scale(1)';
    
    uploadBtn.onclick = () => fileInput.click();
    
    fileInput.onchange = (e) => {
        const file = e.target.files[0];
        if (file) {
            handleImageUploadAndAnalysis(file);
        }
        fileInput.value = ''; // Reset for next upload
    };
    
    inputArea.insertBefore(fileInput, inputArea.firstChild);
    inputArea.insertBefore(uploadBtn, inputArea.firstChild.nextSibling);
    
    // console.log('✅ FREE image upload enabled!');
}

// Auto-initialize when page loads
document.addEventListener('DOMContentLoaded', () => {
    // Load TensorFlow.js from CDN
    const script = document.createElement('script');
    script.src = 'https://cdn.jsdelivr.net/npm/@tensorflow/tfjs@latest/dist/tf.min.js';
    script.onload = () => {
        // console.log('✅ TensorFlow.js loaded');
        
        // Load MobileNet model
        const modelScript = document.createElement('script');
        modelScript.src = 'https://cdn.jsdelivr.net/npm/@tensorflow-models/mobilenet@2.1.0/dist/mobilenet.js';
        modelScript.onload = () => {
            // console.log('✅ MobileNet model loaded');
            setupFreeImageUpload();
        };
        document.head.appendChild(modelScript);
    };
    document.head.appendChild(script);
});

// Export functions for use in chatbot.js
window.freeImageRecognition = {
    init: initImageRecognition,
    classify: classifyImage,
    handleUpload: handleImageUploadAndAnalysis,
    setup: setupFreeImageUpload
};
