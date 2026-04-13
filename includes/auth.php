<?php
/**
 * RISE - Authentication & Authorization
 * =======================================
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/db.php';

/**
 * Check if user is logged in
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['user_role']);
}

/**
 * Require login - redirect if not logged in
 */
function requireLogin() {
    if (!isLoggedIn()) {
        $_SESSION['flash_error'] = 'Please login to continue.';
        header('Location: login.php');
        exit;
    }
    // Verify session is still valid (user exists and is active)
    validateSession();
}

/**
 * Validate session against database
 */
function validateSession() {
    $db = getDB();
    $stmt = $db->prepare("SELECT id, role, status FROM admins WHERE id = :id");
    $stmt->execute([':id' => $_SESSION['user_id']]);
    $user = $stmt->fetch();

    if (!$user || $user['status'] !== 'active') {
        session_destroy();
        session_start();
        $_SESSION['flash_error'] = 'Your account has been deactivated.';
        header('Location: login.php');
        exit;
    }

    // Verify role hasn't changed
    if ($user['role'] !== $_SESSION['user_role']) {
        $_SESSION['user_role'] = $user['role'];
    }
}

/**
 * Require Super Admin role
 */
function requireSuperAdmin() {
    requireLogin();
    if ($_SESSION['user_role'] !== 'super_admin') {
        $_SESSION['flash_error'] = 'Unauthorized access.';
        header('Location: dashboard.php');
        exit;
    }
}

/**
 * Require Admin role (either admin or super_admin)
 */
function requireAdmin() {
    requireLogin();
    if (!in_array($_SESSION['user_role'], ['admin', 'super_admin'])) {
        $_SESSION['flash_error'] = 'Unauthorized access.';
        header('Location: dashboard.php');
        exit;
    }
}

/**
 * Check if current user is super admin
 */
function isSuperAdmin() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'super_admin';
}

/**
 * Get current user ID
 */
function getCurrentUserId() {
    return $_SESSION['user_id'] ?? null;
}

/**
 * Get current user info
 */
function getCurrentUser() {
    if (!isLoggedIn()) return null;
    $db = getDB();
    $stmt = $db->prepare("SELECT id, name, email, role, status, wallet_balance FROM admins WHERE id = :id");
    $stmt->execute([':id' => $_SESSION['user_id']]);
    return $stmt->fetch();
}

/**
 * Verify student belongs to current admin (prevent cross-admin access)
 */
function verifyStudentOwnership($studentId) {
    if (isSuperAdmin()) return true; // Super admin can access all

    $db = getDB();
    $stmt = $db->prepare("SELECT admin_id FROM students WHERE id = :id");
    $stmt->execute([':id' => $studentId]);
    $student = $stmt->fetch();

    if (!$student || $student['admin_id'] != getCurrentUserId()) {
        $_SESSION['flash_error'] = 'Unauthorized: You cannot access this student.';
        header('Location: students.php');
        exit;
    }
    return true;
}

/**
 * Login user
 */
function loginUser($email, $password) {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM admins WHERE email = :email");
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch();

    if (!$user) {
        return ['success' => false, 'message' => 'Invalid email or password.'];
    }

    if ($user['status'] !== 'active') {
        return ['success' => false, 'message' => 'Your account is inactive. Contact administrator.'];
    }

    if (!password_verify($password, $user['password'])) {
        return ['success' => false, 'message' => 'Invalid email or password.'];
    }

    // Regenerate session ID to prevent session fixation
    session_regenerate_id(true);

    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_name'] = $user['name'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_role'] = $user['role'];

    return ['success' => true, 'message' => 'Login successful.'];
}

/**
 * Sanitize input
 */
function sanitize($input) {
    if (is_array($input)) {
        return array_map('sanitize', $input);
    }
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

/**
 * Flash message helpers
 */
function setFlashMessage($type, $message) {
    $_SESSION['flash_' . $type] = $message;
}

function getFlashMessage($type) {
    $key = 'flash_' . $type;
    if (isset($_SESSION[$key])) {
        $message = $_SESSION[$key];
        unset($_SESSION[$key]);
        return $message;
    }
    return null;
}

/**
 * Generate unique enrollment number
 */
function generateEnrollmentNo() {
    $db = getDB();
    do {
        $enrollment = 'RISE' . date('Y') . str_pad(mt_rand(1, 99999), 5, '0', STR_PAD_LEFT);
        $stmt = $db->prepare("SELECT COUNT(*) FROM students WHERE enrollment_no = :enrollment");
        $stmt->execute([':enrollment' => $enrollment]);
    } while ($stmt->fetchColumn() > 0);

    return $enrollment;
}

/**
 * Generate unique roll number
 */
function generateRollNo() {
    $db = getDB();
    do {
        $roll = 'R' . date('ym') . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
        $stmt = $db->prepare("SELECT COUNT(*) FROM students WHERE roll_no = :roll");
        $stmt->execute([':roll' => $roll]);
    } while ($stmt->fetchColumn() > 0);

    return $roll;
}

/**
 * Generate unique certificate ID
 */
function generateCertificateId() {
    $db = getDB();
    do {
        $certId = 'CERT-' . date('Y') . '-' . strtoupper(bin2hex(random_bytes(4)));
        $stmt = $db->prepare("SELECT COUNT(*) FROM certificates WHERE certificate_id = :cert_id");
        $stmt->execute([':cert_id' => $certId]);
    } while ($stmt->fetchColumn() > 0);

    return $certId;
}

/**
 * Calculate grade from percentage
 */
function calculateGrade($percentage) {
    if ($percentage >= 75) return 'A';
    if ($percentage >= 60) return 'B';
    if ($percentage >= 50) return 'C';
    return 'Fail';
}

/**
 * Get wallet balance
 */
function getWalletBalance($adminId) {
    $db = getDB();
    $stmt = $db->prepare("SELECT wallet_balance FROM admins WHERE id = :id");
    $stmt->execute([':id' => $adminId]);
    return (float) $stmt->fetchColumn();
}

/**
 * File upload handler
 */
function uploadFile($file, $destination, $allowedTypes, $maxSize, $maxWidth = null, $maxHeight = null) {
    $result = ['success' => false, 'filename' => '', 'error' => ''];

    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        $result['error'] = 'File upload failed.';
        return $result;
    }

    // Check file size
    if ($file['size'] > $maxSize) {
        $result['error'] = 'File size exceeds ' . ($maxSize / 1024) . 'KB limit.';
        return $result;
    }

    // Check MIME type
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($file['tmp_name']);

    if (!in_array($mimeType, $allowedTypes)) {
        $result['error'] = 'Invalid file type. Allowed: ' . implode(', ', $allowedTypes);
        return $result;
    }

    // For images, validate dimensions
    if (in_array($mimeType, ['image/jpeg', 'image/png'])) {
        $imageInfo = getimagesize($file['tmp_name']);
        if ($imageInfo === false) {
            $result['error'] = 'Invalid image file.';
            return $result;
        }

        if ($maxWidth && $imageInfo[0] > $maxWidth) {
            $result['error'] = 'Image width exceeds ' . $maxWidth . 'px.';
            return $result;
        }
        if ($maxHeight && $imageInfo[1] > $maxHeight) {
            $result['error'] = 'Image height exceeds ' . $maxHeight . 'px.';
            return $result;
        }
    }

    // Generate unique filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $extension = strtolower($extension);
    $filename = uniqid('rise_', true) . '.' . $extension;

    // Create directory if not exists
    if (!is_dir($destination)) {
        mkdir($destination, 0755, true);
    }

    $filepath = $destination . $filename;

    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        $result['success'] = true;
        $result['filename'] = $filename;
    } else {
        $result['error'] = 'Failed to save uploaded file.';
    }

    return $result;
}