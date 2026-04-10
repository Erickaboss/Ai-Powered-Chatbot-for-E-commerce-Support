<?php
/**
 * CSRF Protection & Security Helper Functions
 */

// Generate CSRF token
function generateCSRFToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Verify CSRF token
function verifyCSRFToken(?string $token): bool {
    if (empty($token) || empty($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

// Add CSRF token to forms
function csrfField(): string {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(generateCSRFToken()) . '">';
}

// Sanitize input - prevent XSS
function sanitizeInput(string $input): string {
    $input = trim($input);
    $input = stripslashes($input);
    $input = htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
    return $input;
}

// Sanitize email
function sanitizeEmail(string $email): string {
    return filter_var(trim($email), FILTER_SANITIZE_EMAIL);
}

// Validate email format
function isValidEmail(string $email): bool {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

// Sanitize integer
function sanitizeInt($value, int $min = null, int $max = null): ?int {
    $filtered = filter_var($value, FILTER_VALIDATE_INT);
    if ($filtered === false) return null;
    if ($min !== null && $filtered < $min) return null;
    if ($max !== null && $filtered > $max) return null;
    return $filtered;
}

// Prevent SQL injection - use prepared statements wrapper
function prepareStatement(mysqli $conn, string $sql, string $types, array $params): mysqli_stmt {
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    $stmt->bind_param($types, ...$params);
    return $stmt;
}

// Rate limiting - prevent abuse
function checkRateLimit(string $identifier, int $limit = 10, int $window = 60): bool {
    $key = 'rate_limit_' . $identifier;
    $now = time();
    
    if (!isset($_SESSION[$key])) {
        $_SESSION[$key] = ['count' => 1, 'reset' => $now + $window];
        return true;
    }
    
    if ($now > $_SESSION[$key]['reset']) {
        $_SESSION[$key] = ['count' => 1, 'reset' => $now + $window];
        return true;
    }
    
    $_SESSION[$key]['count']++;
    return $_SESSION[$key]['count'] <= $limit;
}

// Get rate limit remaining
function getRateLimitRemaining(string $identifier): int {
    $key = 'rate_limit_' . $identifier;
    if (!isset($_SESSION[$key])) return 10;
    return max(0, 10 - $_SESSION[$key]['count']);
}

// Security headers
function sendSecurityHeaders(): void {
    header("X-Frame-Options: DENY");
    header("X-Content-Type-Options: nosniff");
    header("X-XSS-Protection: 1; mode=block");
    header("Referrer-Policy: strict-origin-when-cross-origin");
    header("Permissions-Policy: geolocation=(), microphone=(), camera=()");
}

// Validate AJAX request
function isAjaxRequest(): bool {
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
}

// Require AJAX
function requireAjax(): void {
    if (!isAjaxRequest()) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid request']);
        exit;
    }
}
