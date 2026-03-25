<?php
// ── Copy this file to config/secrets.php and fill in your values ──
// config/secrets.php is excluded from git (see .gitignore)

define('_GEMINI_KEY',     'your-gemini-api-key-here');
define('_SMTP_USER',      'your-email@gmail.com');
define('_SMTP_PASS',      'your-gmail-app-password');

// ── Google Custom Search API (for auto product images) ──
// 1. Get API key: https://developers.google.com/custom-search/v1/overview
// 2. Create Search Engine: https://programmablesearchengine.google.com/
//    → Enable "Search the entire web" + "Image search"
define('_GOOGLE_CSE_KEY', 'your-google-api-key-here');
define('_GOOGLE_CSE_CX',  'your-search-engine-id-here');
