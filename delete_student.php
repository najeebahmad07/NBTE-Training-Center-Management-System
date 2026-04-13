<?php
/**
 * RISE - Delete Student
 * ========================
 */

session_start();
require_once 'includes/db.php';
require_once 'includes/auth.php';
requireAdmin();

if (isSuperAdmin()) {
    setFlashMessage('error', 'Use admin account.');
    header('Location: students.php');
    exit;
}

$studentId = (int) ($_GET['id'] ?? 0);
if ($studentId <= 0) {
    setFlashMessage('error', 'Invalid student.');
    header('Location: students.php');
    exit;
}

verifyStudentOwnership($studentId);

$db = getDB();

// Get student data to delete files
$stmt = $db->prepare("SELECT * FROM students WHERE id = :id AND admin_id = :admin_id");
$stmt->execute([':id' => $studentId, ':admin_id' => getCurrentUserId()]);
$student = $stmt->fetch();

if (!$student) {
    echo "<script>alert('Student not found.');window.location='students.php';</script>";
exit;
}

// Delete uploaded files
$filesToDelete = [
    PHOTO_PATH . $student['photo'],
    SIGNATURE_PATH . $student['signature'],
    DOCUMENT_PATH . $student['aadhaar_upload'],
    MARKSHEET_PATH . $student['tenth_marksheet_upload'],
    MARKSHEET_PATH . $student['twelfth_marksheet_upload'],
];

foreach ($filesToDelete as $file) {
    if ($file && file_exists($file)) {
        @unlink($file);
    }
}

// Delete PDF files
if ($student['id_card_pdf'] && file_exists(ID_CARD_PATH . $student['id_card_pdf'])) {
    @unlink(ID_CARD_PATH . $student['id_card_pdf']);
}
if ($student['marksheet_pdf'] && file_exists(MARKSHEET_PATH . $student['marksheet_pdf'])) {
    @unlink(MARKSHEET_PATH . $student['marksheet_pdf']);
}
if ($student['certificate_pdf'] && file_exists(CERTIFICATE_PATH . $student['certificate_pdf'])) {
    @unlink(CERTIFICATE_PATH . $student['certificate_pdf']);
}

// Delete student (cascade will handle marks, certificates)
$stmt = $db->prepare("DELETE FROM students WHERE id = :id AND admin_id = :admin_id");
$stmt->execute([':id' => $studentId, ':admin_id' => getCurrentUserId()]);

setFlashMessage('success', 'Student deleted successfully.');
header('Location: students.php');
exit;