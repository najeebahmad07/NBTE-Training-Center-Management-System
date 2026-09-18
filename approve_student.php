<?php
/**
 * RISE - Approve Student (Wallet Debit + Notify Super Admin)
 * ===========================================================
 */
session_start();
require_once 'includes/db.php';
require_once 'includes/auth.php';
requireAdmin();

if (isSuperAdmin()) {
    setFlashMessage('error', 'Use an admin account.');
    header('Location: students.php');
    exit;
}

$db        = getDB();
$studentId = (int) ($_GET['id'] ?? 0);
$userId    = getCurrentUserId();

if ($studentId <= 0) {
    setFlashMessage('error', 'Invalid student.');
    header('Location: students.php');
    exit;
}

verifyStudentOwnership($studentId);

// Fetch student
$stmt = $db->prepare("SELECT * FROM students WHERE id = :id AND admin_id = :admin_id");
$stmt->execute([':id' => $studentId, ':admin_id' => $userId]);
$student = $stmt->fetch();

if (!$student) {
    setFlashMessage('error', 'Student not found.');
    header('Location: students.php');
    exit;
}

if ($student['status'] === 'Approved') {
    setFlashMessage('warning', 'Student is already approved.');
    header('Location: view_student.php?id=' . $studentId);
    exit;
}

// Check wallet balance
$balance = getWalletBalance($userId);
// Program-wise approval fee
$program_id = (int)$student['program_id'];

$fee = $PROGRAM_APPROVAL_FEES[$program_id] ?? APPROVAL_FEE;

if ($balance < $fee) {
    setFlashMessage('error',
        'Insufficient wallet balance. Required: ' . CURRENCY_SYMBOL . number_format($fee, 2) .
        '. Current balance: ' . CURRENCY_SYMBOL . number_format($balance, 2) . '. Please recharge.'
    );
    header('Location: wallet.php');
    exit;
}

// Fetch super admin id
$saStmt = $db->query("SELECT id FROM admins WHERE role = 'super_admin' LIMIT 1");
$superAdmin = $saStmt->fetch();

if (!$superAdmin) {
    setFlashMessage('error', 'Super admin not found. Please contact support.');
    header('Location: students.php');
    exit;
}

// Begin transaction
$db->beginTransaction();
try {
    // 1. Debit wallet
    $newBalance = $balance - $fee;
    $stmt = $db->prepare("UPDATE admins SET wallet_balance = :balance WHERE id = :id");
    $stmt->execute([':balance' => $newBalance, ':id' => $userId]);

    // 2. Record wallet transaction
    $stmt = $db->prepare("
        INSERT INTO wallet_transactions
            (admin_id, amount, type, transaction_type, description, status)
        VALUES
            (:admin_id, :amount, 'debit', 'approval_fee', :desc, 'success')
    ");
    $stmt->execute([
        ':admin_id' => $userId,
        ':amount'   => $fee,
        ':desc'     => 'Approval fee for student: ' . $student['full_name'] . ' (' . $student['enrollment_no'] . ')',
    ]);

    // 3. Approve student (status = Approved, certificate_approved stays Pending)
    $stmt = $db->prepare("
        UPDATE students
        SET status = 'Approved', approved_at = NOW()
        WHERE id = :id
    ");
    $stmt->execute([':id' => $studentId]);

    // 4. Send notification to Super Admin for certificate approval
    $message = 'Admin has approved student ' . $student['full_name'] .
               ' (' . $student['enrollment_no'] . '). Please review and approve certificate generation.';

    $stmt = $db->prepare("
        INSERT INTO notifications
            (from_admin, to_admin, student_id, type, message, is_read)
        VALUES
            (:from_admin, :to_admin, :student_id, 'certificate_request', :message, 0)
    ");
    $stmt->execute([
        ':from_admin' => $userId,
        ':to_admin'   => $superAdmin['id'],
        ':student_id' => $studentId,
        ':message'    => $message,
    ]);

    $db->commit();

    setFlashMessage('success',
        'Student approved successfully! ' . CURRENCY_SYMBOL . number_format($fee, 2) .
        ' debited from wallet. Super Admin has been notified for certificate approval.'
    );
    header('Location: view_student.php?id=' . $studentId);
    exit;

} catch (Exception $e) {
    $db->rollBack();
    error_log("Approval error: " . $e->getMessage());
    setFlashMessage('error', 'Approval failed. Please try again.');
    header('Location: students.php');
    exit;
}