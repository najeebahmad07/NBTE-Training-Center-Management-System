<?php
require_once 'includes/auth.php';
requireLogin();
require_once 'includes/db.php';

header('Content-Type: application/json');

$programId = (int)($_GET['program_id'] ?? 0);
$courseId  = (int)($_GET['course_id'] ?? 0);

if ($programId <= 0 || $courseId <= 0) {
    echo json_encode([]);
    exit;
}

$db = getDB();

$stmt = $db->prepare("
    SELECT
        id,
        subject_name,
        total_marks,
        has_practical,
        practical_marks
    FROM subjects
    WHERE program_id = :pid
      AND course_id = :cid
    ORDER BY subject_name ASC
");

$stmt->execute([
    ':pid' => $programId,
    ':cid' => $courseId
]);

echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
exit;