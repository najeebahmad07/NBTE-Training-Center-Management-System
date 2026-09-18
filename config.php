<?php
/**
 * RISE SaaS Application - Configuration
 * ======================================
 */

// Error Reporting (set to 0 in production)
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/error.log');

// Session Configuration
ini_set('session.cookie_httponly', 1);
ini_set('session.use_strict_mode', 1);
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.gc_maxlifetime', 3600);

// Application Constants
define('APP_NAME', 'NBTE');
define('APP_TAGLINE', 'National Board for Technical Education');
define('APP_URL', 'http://localhost/nbteapp/');
define('APP_VERSION', '1.0.0');

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'nbte');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Razorpay Configuration
define('RAZORPAY_KEY_ID', 'rzp_test_TQWuPysRxCUw5Q');
define('RAZORPAY_KEY_SECRET', 'a2hHQaCGmM3he2WhnuYnlZDn');

// Wallet Configuration
// Wallet Configuration
define('MIN_RECHARGE_AMOUNT', 100);

// Default Approval Fee
define('APPROVAL_FEE', 2000);

// Special Program Fees
$PROGRAM_APPROVAL_FEES = [

    // 20 => 200,
    22 => 200,
    25 => 200,
    26 => 200,
    29 => 200,

];

define('CURRENCY', 'INR');
define('CURRENCY_SYMBOL', '₹');

// Upload Configuration
define('UPLOAD_MAX_SIZE', 512000); // 500KB in bytes
define('PHOTO_MAX_WIDTH', 400);
define('PHOTO_MAX_HEIGHT', 400);
define('SIGNATURE_MAX_WIDTH', 200);
define('SIGNATURE_MAX_HEIGHT', 100);
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png']);
define('ALLOWED_DOC_TYPES', ['image/jpeg', 'image/png', 'application/pdf']);

// Upload Paths
define('UPLOAD_PATH', __DIR__ . '/uploads/');
define('PHOTO_PATH', UPLOAD_PATH . 'photos/');
define('SIGNATURE_PATH', UPLOAD_PATH . 'signatures/');
define('DOCUMENT_PATH', UPLOAD_PATH . 'documents/');
define('MARKSHEET_PATH', UPLOAD_PATH . 'marksheets/');
define('ID_CARD_PATH', UPLOAD_PATH . 'id_cards/');
define('CERTIFICATE_PATH', UPLOAD_PATH . 'certificates/');

// Timezone
date_default_timezone_set('Asia/Kolkata');