<?php
/**
 * Proxy endpoint — returns ML model status & performance from Flask API
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db.php';

// Only admin can access
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$flask_base = 'http://localhost:5000';

// Check Flask health
$ch = curl_init($flask_base . '/health');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 3,
    CURLOPT_CONNECTTIMEOUT => 2,
]);
$health_resp = curl_exec($ch);
$health_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($health_code !== 200) {
    echo json_encode([
        'ml_online'  => false,
        'message'    => 'ML API is offline. Run: cd chatbot-ml && python app.py',
        'performance'=> null,
    ]);
    exit;
}

$health = json_decode($health_resp, true);

// Get performance metrics
$ch2 = curl_init($flask_base . '/models/performance');
curl_setopt_array($ch2, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 3]);
$perf_resp = curl_exec($ch2);
curl_close($ch2);
$performance = json_decode($perf_resp, true);

echo json_encode([
    'ml_online'   => true,
    'health'      => $health,
    'performance' => $performance,
]);
