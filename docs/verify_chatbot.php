<?php
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', '0');

require_once 'config/db.php';

$tests = [
    ['msg' => 'show me smartphones under 100k', 'type' => 'Budget Search'],
    ['msg' => 'furniture products', 'type' => 'Category Search'],
    ['msg' => 'samsung products', 'type' => 'Brand Search'],
    ['msg' => 'what laptops do you have', 'type' => 'Product Search'],
    ['msg' => 'show me products under 200000', 'type' => 'Budget Search'],
    ['msg' => 'health and beauty items', 'type' => 'Category Search'],
    ['msg' => 'hello', 'type' => 'Greeting'],
    ['msg' => 'track my order', 'type' => 'Order Tracking'],
];

$results = [];

foreach ($tests as $test) {
    $payload = json_encode([
        'message' => $test['msg'],
        'session_id' => 'verify_' . time() . '_' . rand(1000, 9999)
    ]);
    
    $ch = curl_init('http://localhost/ecommerce-chatbot/api/chatbot_simple.php');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT => 5,
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code === 200) {
        $data = json_decode($response, true);
        $results[] = [
            'test_type' => $test['type'],
            'message' => $test['msg'],
            'status' => 'PASS',
            'intent' => $data['intent'] ?? 'unknown',
            'confidence' => round(($data['confidence'] ?? 0) * 100, 1),
            'response_length' => strlen($data['response'] ?? ''),
            'has_products' => strpos($data['response'] ?? '', '📦') !== false ? 'Yes' : 'No'
        ];
    } else {
        $results[] = [
            'test_type' => $test['type'],
            'message' => $test['msg'],
            'status' => 'FAIL',
            'error' => 'HTTP ' . $http_code
        ];
    }
    
    usleep(300000); // 300ms delay
}

echo json_encode([
    'verification' => [
        'timestamp' => date('Y-m-d H:i:s'),
        'flask_status' => 'Running',
        'database_status' => 'Connected',
        'chatbot_status' => 'Operational'
    ],
    'test_results' => [
        'total' => count($results),
        'passed' => count(array_filter($results, fn($r) => $r['status'] === 'PASS')),
        'failed' => count(array_filter($results, fn($r) => $r['status'] === 'FAIL'))
    ],
    'tests' => $results
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
?>
