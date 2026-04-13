<?php
/**
 * RISE - AJAX: Get subjects by program ID
 * ===========================================
 */

session_start();
require_once 'includes/db.php';
require_once 'includes/auth.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode([]);
    exit;
}

$programId = (int) ($_GET['program_id'] ?? 0);

if ($programId <= 0) {
    echo json_encode([]);
    exit;
}

$db = getDB();
$stmt = $db->prepare("SELECT id, subject_name, total_marks FROM subjects WHERE program_id = :pid ORDER BY subject_name");
$stmt->execute([':pid' => $programId]);
$subjects = $stmt->fetchAll();

echo json_encode($subjects);