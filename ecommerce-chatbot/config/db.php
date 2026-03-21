<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'ecommerce_chatbot');

// OpenAI API Key (set your key here or in .env)
define('OPENAI_API_KEY', getenv('OPENAI_API_KEY') ?: 'your-openai-api-key-here');

// Site config
define('SITE_NAME', 'ShopAI Rwanda');
define('SITE_URL', 'http://localhost/ecommerce-chatbot');
define('CURRENCY', 'RWF');
define('CURRENCY_SYMBOL', 'RWF ');

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($conn->connect_error) {
    die(json_encode(['error' => 'Database connection failed: ' . $conn->connect_error]));
}

$conn->set_charset('utf8mb4');
