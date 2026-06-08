/**
 * Chatbot Upload Handler
 * Handles file uploads with drag-and-drop support
 */

class ChatbotUploadHandler {
    constructor(options = {}) {
        this.apiUrl = options.apiUrl || '/api/upload.php';
        this.maxFileSize = options.maxFileSize || 10 * 1024 * 1024; // 10MB
        this.allowedTypes = options.allowedTypes || [
            'image/jpeg',
            'image/png',
            'application/pdf',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
        ];
        this.onUploadStart = options.onUploadStart || (() => {});
        this.onUploadProgress = options.onUploadProgress || (() => {});
        this.onUploadComplete = options.onUploadComplete || (() => {});
        this.onUploadError = options.onUploadError || (() => {});
        
        this.uploadZone = null;
        this.fileInput = null;
    }
    
    /**
     * Initialize upload handler
     */
    initialize(containerId) {
        const container = document.getElementById(containerId);
        if (!container) {
            console.error(`Container ${containerId} not found`);
            return;
        }
        
        // Create upload zone HTML
        container.innerHTML = `
            <div class="upload-zone" id="upload-zone-inner">
                <div class="upload-icon">📁</div>
                <p class="upload-text">Drag and drop files here or click to browse</p>
                <p class="upload-subtext">Supported: JPG, PNG, PDF, DOCX (Max 10MB)</p>
                <input type="file" id="file-input" style="display: none;" accept=".jpg,.jpeg,.png,.pdf,.docx">
            </div>
            <div id="upload-progress" style="display: none;">
                <div class="progress-bar">
                    <div class="progress-fill" id="progress-fill"></div>
                </div>
                <p id="progress-text">Uploading... 0%</p>
            </div>
        `;
        
        this.uploadZone = document.getElementById('upload-zone-inner');
        this.fileInput = document.getElementById('file-input');
        
        // Setup event listeners
        this.setupEventListeners();
    }
    
    /**
     * Setup event listeners
     */
    setupEventListeners() {
        // Click to browse
        this.uploadZone.addEventListener('click', () => this.fileInput.click());
        
        // File input change
        this.fileInput.addEventListener('change', (e) => {
            if (e.target.files.length > 0) {
                this.handleFile(e.target.files[0]);
            }
        });
        
        // Drag and drop
        this.uploadZone.addEventListener('dragover', (e) => {
            e.preventDefault();
            e.stopPropagation();
            this.uploadZone.classList.add('drag-over');
        });
        
        this.uploadZone.addEventListener('dragleave', (e) => {
            e.preventDefault();
            e.stopPropagation();
            this.uploadZone.classList.remove('drag-over');
        });
        
        this.uploadZone.addEventListener('drop', (e) => {
            e.preventDefault();
            e.stopPropagation();
            this.uploadZone.classList.remove('drag-over');
            
            if (e.dataTransfer.files.length > 0) {
                this.handleFile(e.dataTransfer.files[0]);
            }
        });
    }
    
    /**
     * Handle file upload
     */
    handleFile(file) {
        // Validate file
        const validation = this.validateFile(file);
        if (!validation.valid) {
            this.onUploadError(new Error(validation.error));
            return;
        }
        
        // Upload file
        this.uploadFile(file);
    }
    
    /**
     * Validate file
     */
    validateFile(file) {
        // Check file size
        if (file.size > this.maxFileSize) {
            return {
                valid: false,
                error: `File size exceeds maximum limit (10MB). Your file: ${(file.size / 1024 / 1024).toFixed(2)}MB`
            };
        }
        
        // Check file type
        if (!this.allowedTypes.includes(file.type)) {
            return {
                valid: false,
                error: `File type not allowed. Supported types: JPG, PNG, PDF, DOCX`
            };
        }
        
        return { valid: true };
    }
    
    /**
     * Upload file to server
     */
    uploadFile(file) {
        this.onUploadStart();
        
        const formData = new FormData();
        formData.append('file', file);
        formData.append('session_id', this.getSessionId());
        
        const xhr = new XMLHttpRequest();
        
        // Track upload progress
        xhr.upload.addEventListener('progress', (e) => {
            if (e.lengthComputable) {
                const percentComplete = (e.loaded / e.total) * 100;
                this.onUploadProgress(percentComplete);
                this.updateProgressBar(percentComplete);
            }
        });
        
        // Handle completion
        xhr.addEventListener('load', () => {
            if (xhr.status === 200) {
                try {
                    const response = JSON.parse(xhr.responseText);
                    this.onUploadComplete(response);
                } catch (e) {
                    this.onUploadError(new Error('Invalid server response'));
                }
            } else {
                try {
                    const error = JSON.parse(xhr.responseText);
                    this.onUploadError(new Error(error.message || 'Upload failed'));
                } catch (e) {
                    this.onUploadError(new Error('Upload failed'));
                }
            }
        });
        
        // Handle error
        xhr.addEventListener('error', () => {
            this.onUploadError(new Error('Network error during upload'));
        });
        
        // Send request
        xhr.open('POST', this.apiUrl);
        xhr.send(formData);
    }
    
    /**
     * Update progress bar
     */
    updateProgressBar(percent) {
        const progressDiv = document.getElementById('upload-progress');
        const progressFill = document.getElementById('progress-fill');
        const progressText = document.getElementById('progress-text');
        
        if (progressDiv) {
            progressDiv.style.display = 'block';
            if (progressFill) {
                progressFill.style.width = percent + '%';
            }
            if (progressText) {
                progressText.textContent = `Uploading... ${Math.round(percent)}%`;
            }
        }
    }
    
    /**
     * Get session ID from localStorage or generate new 32-char hex
     */
    getSessionId() {
        let sid = localStorage.getItem('chat_session_id');
        if (!sid || !/^[a-f0-9]{32}$/.test(sid)) {
            try {
                sid = Array.from(crypto.getRandomValues(new Uint8Array(16)))
                           .map(b => b.toString(16).padStart(2, '0')).join('');
            } catch (e) {
                sid = Array.from({length: 16}, () =>
                    Math.floor(Math.random() * 256).toString(16).padStart(2, '0')).join('');
            }
            localStorage.setItem('chat_session_id', sid);
        }
        return sid;
    }
}

// Export for use in other scripts
if (typeof module !== 'undefined' && module.exports) {
    module.exports = ChatbotUploadHandler;
}
