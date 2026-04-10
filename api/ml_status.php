<?php
/**
 * Artifact-backed ML status endpoint with optional live Flask health check
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/ml_artifacts.php';

// Only admin can access
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$flask_base = 'http://localhost:5000';
$artifacts = loadMlArtifacts();
$mlOnline = false;
$health = null;

$ch = curl_init($flask_base . '/health');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 3,
    CURLOPT_CONNECTTIMEOUT => 2,
]);
$health_resp = curl_exec($ch);
$health_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($health_code === 200 && $health_resp) {
    $decodedHealth = json_decode($health_resp, true);
    if (is_array($decodedHealth)) {
        $health = $decodedHealth;
        $mlOnline = true;
    }
}

$message = $mlOnline
    ? 'Live ML service is online. Dashboard metrics are loaded from saved training artifacts.'
    : ($artifacts['available']
        ? 'Live ML service is offline. Showing the latest saved training artifacts instead.'
        : 'ML artifacts are not available yet. Run the chatbot training pipeline to generate them.');

echo json_encode([
    'ml_online'   => $mlOnline,
    'message'     => $message,
    'health'      => $health,
    'artifact'    => [
        'available'  => $artifacts['available'],
        'summary'    => $artifacts['summary'],
        'models'     => $artifacts['models'],
        'dataset'    => $artifacts['dataset'],
        'vectorizer' => $artifacts['vectorizer'],
        'split'      => $artifacts['split'],
        'plots'      => $artifacts['plots'],
        'reports'    => $artifacts['reports'],
        'artifacts'  => $artifacts['artifacts'],
    ],
    'performance' => !empty($artifacts['raw']['results']) ? $artifacts['raw']['results'] : null,
]);
