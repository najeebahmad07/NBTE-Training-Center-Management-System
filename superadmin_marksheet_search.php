<?php
require_once 'includes/db.php';

$db = getDB();

// Get enrollment number
$enrollment = $_GET['enrollment'] ?? '';

if (empty($enrollment)) {
    die('Invalid Enrollment Number');
}

// Fetch student
$stmt = $db->prepare("SELECT * FROM students WHERE roll_no = :enrollment LIMIT 1");
$stmt->execute([':enrollment' => $enrollment]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$student || empty($student['marksheet_pdf'])) {
    die('Marksheet not found.');
}

// File path
$file = __DIR__ . '/uploads/marksheets/' . $student['marksheet_pdf'];

if (!file_exists($file)) {
    die('File not found.');
}

// Force open PDF in browser
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="Marksheet.pdf"');
readfile($file);
exit;