<?php
/**
 * RISE - Create Razorpay Order
 * ==============================
 * Called via AJAX before opening Razorpay checkout
 */
session_start();
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/csrf.php';

header('Content-Type: application/json');

if (!isLoggedIn() || isSuperAdmin()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

$amount = (float) ($_POST['amount'] ?? 0);

if ($amount < MIN_RECHARGE_AMOUNT) {
    echo json_encode(['success' => false, 'message' => 'Minimum recharge is ' . CURRENCY_SYMBOL . MIN_RECHARGE_AMOUNT]);
    exit;
}

if ($amount > 100000) {
    echo json_encode(['success' => false, 'message' => 'Amount exceeds maximum limit.']);
    exit;
}

// Create Razorpay Order via API
$orderData = [
    'amount'          => (int) ($amount * 100), // convert to paise
    'currency'        => CURRENCY,
    'receipt'         => 'wallet_' . getCurrentUserId() . '_' . time(),
    'payment_capture' => 1, // AUTO CAPTURE!
];

$ch = curl_init('https://api.razorpay.com/v1/orders');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($orderData));
curl_setopt($ch, CURLOPT_USERPWD, RAZORPAY_KEY_ID . ':' . RAZORPAY_KEY_SECRET);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
curl_setopt($ch, CURLOPT_CAINFO, '/etc/pki/tls/certs/ca-bundle.crt');

$response  = curl_exec($ch);
$httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($httpCode !== 200 || !$response) {
    error_log("Order creation failed: HTTP={$httpCode}, error={$curlError}");
    echo json_encode(['success' => false, 'message' => 'Could not initiate payment. Please try again.']);
    exit;
}

$order = json_decode($response, true);

if (!$order || !isset($order['id'])) {
    error_log("Invalid order response: " . $response);
    echo json_encode(['success' => false, 'message' => 'Payment initiation failed. Please try again.']);
    exit;
}

echo json_encode([
    'success'  => true,
    'order_id' => $order['id'],
    'amount'   => $order['amount'], // in paise
    'currency' => $order['currency'],
]);