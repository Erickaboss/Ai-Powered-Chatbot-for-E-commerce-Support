<?php
// Load local secrets (not committed to git)
if (file_exists(__DIR__ . '/secrets.php')) {
    require_once __DIR__ . '/secrets.php';
}

// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'ecommerce_chatbot');

// OpenAI API Key (optional)
define('OPENAI_API_KEY', getenv('OPENAI_API_KEY') ?: 'your-openai-api-key-here');

// Site config
define('SITE_NAME', 'AI-Powered Chatbot For E-commerce Support');
define('SITE_URL', 'http://localhost/ecommerce-chatbot');
define('CURRENCY', 'RWF');
define('CURRENCY_SYMBOL', 'RWF ');

// Admin contact
define('ADMIN_EMAIL', 'ericniringiyimana123@gmail.com');
define('ADMIN_NAME',  'Eric Niringiyimana');
define('ADMIN_PHONE', '+250782977559');

// ── Google Custom Search API (for auto product images) ──
define('GOOGLE_CSE_KEY', defined('_GOOGLE_CSE_KEY') ? _GOOGLE_CSE_KEY : '');
define('GOOGLE_CSE_CX',  defined('_GOOGLE_CSE_CX')  ? _GOOGLE_CSE_CX  : '');
// Set your Gemini API key in config/secrets.php (not committed to git)
define('GEMINI_API_KEY', defined('_GEMINI_KEY') ? _GEMINI_KEY : 'your-gemini-api-key-here');

// ── Email config (Gmail SMTP) ─────────────────────────────────
// Set your SMTP credentials in config/secrets.php (not committed to git)
define('BREVO_API_KEY',  '');
define('SMTP_HOST',      'smtp.gmail.com');
define('SMTP_PORT',      587);
define('SMTP_USER',      defined('_SMTP_USER') ? _SMTP_USER : 'your-email@gmail.com');
define('SMTP_PASS',      defined('_SMTP_PASS') ? _SMTP_PASS : 'your-app-password');
define('SMTP_FROM_NAME', SITE_NAME);

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($conn->connect_error) {
    die(json_encode(['error' => 'Database connection failed: ' . $conn->connect_error]));
}

$conn->set_charset('utf8mb4');
