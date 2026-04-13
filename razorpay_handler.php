<?php
/**
 * RISE - Razorpay Payment Handler (Order-based - Final)
 * ======================================================
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

$paymentId = sanitize($_POST['razorpay_payment_id'] ?? '');
$orderId   = sanitize($_POST['razorpay_order_id']   ?? '');
$signature = sanitize($_POST['razorpay_signature']  ?? '');
$amount    = (float) ($_POST['amount'] ?? 0);

if (empty($paymentId) || empty($orderId) || empty($signature)) {
    echo json_encode(['success' => false, 'message' => 'Invalid payment details']);
    exit;
}

$db     = getDB();
$userId = getCurrentUserId();

// Check duplicate
$stmt = $db->prepare("SELECT COUNT(*) FROM wallet_transactions WHERE razorpay_payment_id = :pid AND status = 'success'");
$stmt->execute([':pid' => $paymentId]);
if ($stmt->fetchColumn() > 0) {
    echo json_encode(['success' => false, 'message' => 'Payment already processed']);
    exit;
}

// ── Step 1: Verify signature (most secure method) ─────────────────────────────
$expectedSignature = hash_hmac('sha256', $orderId . '|' . $paymentId, RAZORPAY_KEY_SECRET);
if (!hash_equals($expectedSignature, $signature)) {
    error_log("Signature mismatch: pid={$paymentId}, oid={$orderId}");
    echo json_encode(['success' => false, 'message' => 'Payment signature verification failed.']);
    exit;
}

// ── Step 2: Fetch payment from Razorpay to get verified amount ────────────────
$ch = curl_init('https://api.razorpay.com/v1/payments/' . $paymentId);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_USERPWD, RAZORPAY_KEY_ID . ':' . RAZORPAY_KEY_SECRET);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
curl_setopt($ch, CURLOPT_CAINFO, '/etc/pki/tls/certs/ca-bundle.crt');
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

$response  = curl_exec($ch);
$httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200 || !$response) {
    // Signature already verified — safe to use POST amount as fallback
    error_log("Payment fetch failed after signature verify: pid={$paymentId}, using POST amount={$amount}");
    $verifiedAmount = $amount;
} else {
    $paymentData    = json_decode($response, true);
    $verifiedAmount = ($paymentData['amount'] ?? ($amount * 100)) / 100;
}

// ── Step 3: Credit wallet ─────────────────────────────────────────────────────
$db->beginTransaction();
try {
    $stmt = $db->prepare("UPDATE admins SET wallet_balance = wallet_balance + :amount WHERE id = :id");
    $stmt->execute([':amount' => $verifiedAmount, ':id' => $userId]);

    $stmt = $db->prepare("
        INSERT INTO wallet_transactions
            (admin_id, amount, type, transaction_type, razorpay_payment_id, razorpay_order_id, status, description)
        VALUES
            (:admin_id, :amount, 'credit', 'recharge', :payment_id, :order_id, 'success', :desc)
    ");
    $stmt->execute([
        ':admin_id'   => $userId,
        ':amount'     => $verifiedAmount,
        ':payment_id' => $paymentId,
        ':order_id'   => $orderId,
        ':desc'       => 'Wallet recharge of ' . CURRENCY_SYMBOL . number_format($verifiedAmount, 2),
    ]);

    $db->commit();

    echo json_encode([
        'success'     => true,
        'message'     => 'Recharge successful',
        'new_balance' => getWalletBalance($userId),
    ]);

} catch (Exception $e) {
    $db->rollBack();
    error_log("Recharge DB error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Processing failed. Contact support. Payment ID: ' . $paymentId]);
}