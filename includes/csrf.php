<?php
/**
 * RISE - CSRF Protection
 * =======================
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Generate CSRF token
 */
function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Get CSRF hidden input field
 */
function csrfField() {
    $token = generateCSRFToken();
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token) . '">';
}

/**
 * Validate CSRF token
 */
function validateCSRFToken($token = null) {
    if ($token === null) {
        $token = $_POST['csrf_token'] ?? '';
    }

    if (empty($token) || empty($_SESSION['csrf_token'])) {
        return false;
    }

    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Require valid CSRF token - die on failure
 */
function requireCSRF() {
    if (!validateCSRFToken()) {
        http_response_code(403);
        die('CSRF token validation failed. Please refresh the page and try again.');
    }
}