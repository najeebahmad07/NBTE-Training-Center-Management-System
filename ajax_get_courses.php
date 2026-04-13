<?php
/**
 * RISE - AJAX: Get courses by program ID
 * ==========================================
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
$stmt = $db->prepare("SELECT id, course_name FROM courses WHERE program_id = :pid ORDER BY course_name");
$stmt->execute([':pid' => $programId]);
$courses = $stmt->fetchAll();

echo json_encode($courses);