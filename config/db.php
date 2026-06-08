<?php
// Load environment variables from .env
require_once __DIR__ . '/env.php';

// Load local secrets (not committed to git)
if (file_exists(__DIR__ . '/secrets.php')) {
    require_once __DIR__ . '/secrets.php';
}

// Database configuration
defined('DB_HOST') || define('DB_HOST', 'localhost');
defined('DB_USER') || define('DB_USER', 'root');
defined('DB_PASS') || define('DB_PASS', '');
defined('DB_NAME') || define('DB_NAME', 'ecommerce_chatbot');

// OpenAI API Key (optional)
define('OPENAI_API_KEY', getenv('OPENAI_API_KEY') ?: 'your-openai-api-key-here');

// Site config (from .env with fallbacks)
defined('SITE_NAME') || define('SITE_NAME', getenv('SITE_NAME') ?: 'AI-Powered Chatbot For E-commerce Support');
defined('SITE_URL')  || define('SITE_URL', getenv('SITE_URL') ?: 'http://localhost/ecommerce-chatbot');
defined('CURRENCY')  || define('CURRENCY', getenv('CURRENCY') ?: 'RWF');
define('CURRENCY_SYMBOL', CURRENCY . ' ');

// Admin contact (from .env with fallbacks)
defined('ADMIN_EMAIL') || define('ADMIN_EMAIL', getenv('ADMIN_EMAIL') ?: 'ericniringiyimana123@gmail.com');
defined('ADMIN_NAME')  || define('ADMIN_NAME', getenv('ADMIN_NAME') ?: 'Admin');
defined('ADMIN_PHONE') || define('ADMIN_PHONE', getenv('ADMIN_PHONE') ?: '+250782877559');

// ── Google Custom Search API (for auto product images) ──
define('GOOGLE_CSE_KEY', defined('_GOOGLE_CSE_KEY') ? _GOOGLE_CSE_KEY : '');
define('GOOGLE_CSE_CX',  defined('_GOOGLE_CSE_CX')  ? _GOOGLE_CSE_CX  : '');
// Set your Gemini API key in config/secrets.php (not committed to git)
defined('GEMINI_API_KEY') || define('GEMINI_API_KEY', defined('_GEMINI_KEY') ? _GEMINI_KEY : 'your-gemini-api-key-here');

// ── Email config (Gmail SMTP) ─────────────────────────────────
// Set your SMTP credentials in config/secrets.php (not committed to git)
define('BREVO_API_KEY',  '');
define('SMTP_HOST',      'smtp.gmail.com');
define('SMTP_PORT',      587);
defined('SMTP_USER') || define('SMTP_USER', defined('_SMTP_USER') ? _SMTP_USER : 'your-email@gmail.com');
defined('SMTP_PASS') || define('SMTP_PASS', defined('_SMTP_PASS') ? _SMTP_PASS : 'your-app-password');
define('SMTP_FROM_NAME', SITE_NAME);

function getDB(): mysqli {
    static $conn = null;
    if ($conn === null) {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if ($conn->connect_error) {
            die(json_encode(['error' => 'Database connection failed: ' . $conn->connect_error]));
        }
        $conn->set_charset('utf8mb4');
    }
    return $conn;
}
$conn = getDB();
