<?php
/**
 * RISE SaaS - Generate Marksheet (TCPDF)
 * File: generate_marksheet.php
 */

require_once 'includes/auth.php';
requireLogin();

if (!in_array($_SESSION['user_role'], ['admin', 'super_admin'])) {
    header('Location: dashboard.php');
    exit;
}

require_once 'includes/db.php';
require_once 'includes/csrf.php';

$db = getDB();

// Validate student ID
$student_id = (int)($_GET['id'] ?? $_GET['student_id'] ?? 0);
if ($student_id <= 0) {
    setFlashMessage('error', 'Invalid student ID.');
    header('Location: students.php');
    exit;
}

// Fetch student with role-based access control
if ($_SESSION['user_role'] === 'super_admin') {
    $stmt = $db->prepare("
        SELECT s.*, p.program_name, p.duration, c.course_name, a.name as center_name
        FROM students s
        JOIN programs p ON s.program_id = p.id
        JOIN courses c ON s.course_id = c.id
        JOIN admins a ON s.admin_id = a.id
        WHERE s.id = :student_id
    ");
    $stmt->execute([':student_id' => $student_id]);
} else {
    $stmt = $db->prepare("
        SELECT s.*, p.program_name, p.duration, c.course_name
        FROM students s
        JOIN programs p ON s.program_id = p.id
        JOIN courses c ON s.course_id = c.id
        WHERE s.id = :student_id AND s.admin_id = :admin_id
    ");
    $stmt->execute([
        ':student_id' => $student_id,
        ':admin_id' => $_SESSION['user_id']
    ]);
}

$student = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$student) {
    setFlashMessage('error', 'Student not found or access denied.');
    header('Location: students.php');
    exit;
}

if ($student['status'] !== 'Approved') {
    setFlashMessage('error', 'Marksheet can only be generated for approved students.');
    header('Location: view_student.php?id=' . $student_id);
    exit;
}

$admin_stmt = $db->prepare("SELECT * FROM admins WHERE id = :id");
$admin_stmt->execute([':id' => $student['admin_id']]);
$admin = $admin_stmt->fetch(PDO::FETCH_ASSOC);

// ===================== CHECK EXISTING MARKSHEET =====================
if (!empty($student['marksheet_pdf'])) {

    $existing_path = __DIR__ . '/uploads/marksheets/' . $student['marksheet_pdf'];

    if (file_exists($existing_path)) {
        // Serve existing PDF
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . basename($existing_path) . '"');
        readfile($existing_path);
        exit;
    }
}

// Fetch marks with subjects
$marks_stmt = $db->prepare("
    SELECT m.*, sub.subject_name, sub.total_marks
    FROM marks m
    JOIN subjects sub ON m.subject_id = sub.id
    WHERE m.student_id = :student_id
    ORDER BY sub.subject_name ASC
");
$marks_stmt->execute([':student_id' => $student_id]);
$marks = $marks_stmt->fetchAll(PDO::FETCH_ASSOC);

// ================= TOTAL CALCULATION =================

$total_max = 0;
$total_obtained = 0;

foreach ($marks as $mark) {
    $total_max += (int)$mark['total_marks'];
    $total_obtained += (int)$mark['marks_obtained'];
}

if (empty($marks)) {
    setFlashMessage('error', 'No marks found for this student. Please enter marks first.');
    header('Location: view_student.php?id=' . $student_id);
    exit;
}

// ===================== LOAD TCPDF =====================
 // ── TCPDF ─────────────────────────────────────────────────────────────────────
if (!class_exists('TCPDF')) {

    $paths = [
        __DIR__ . '/lib/TCPDF/tcpdf.php',   // correct server path
        __DIR__ . '/lib/tcpdf/tcpdf.php',
        __DIR__ . '/vendor/tecnickcom/tcpdf/tcpdf.php',
        __DIR__ . '/tcpdf/tcpdf.php'
    ];

    foreach ($paths as $p) {
        if (file_exists($p)) {
            require_once $p;
            break;
        }
    }

    if (!class_exists('TCPDF') && file_exists(__DIR__ . '/vendor/autoload.php')) {
        require_once __DIR__ . '/vendor/autoload.php';
    }
}

if (!class_exists('TCPDF')) {
    die('TCPDF not found.');
}

if (!class_exists('RISE_AuthCert_PDF')) {
    class RISE_AuthCert_PDF extends TCPDF {
        public function Header() {}
        public function Footer() {}
    }
}


// Custom TCPDF class for Marksheet
class RISE_Marksheet extends TCPDF {
    public function Header() {}
    public function Footer() {}
}

// Create PDF - A4 Portrait
$pdf = new RISE_Marksheet('P', 'mm', 'A4', true, 'UTF-8', false);

$pdf->SetCreator('RISE SaaS');
$pdf->SetAuthor('RISE');
$pdf->SetTitle('Marksheet - ' . htmlspecialchars($student['full_name']));

$pdf->SetMargins(15, 10, 15);
$pdf->SetAutoPageBreak(true, 15);
$pdf->SetPrintHeader(false);
$pdf->SetPrintFooter(false);

$pdf->AddPage();

// ===================== WATERMARK =====================
$pdf->SetFont('helvetica', 'B', 45);
$pdf->SetTextColor(220, 220, 220);
$pdf->SetAlpha(0.15);
$pdf->StartTransform();
$pdf->Rotate(45, 105, 148);
$pdf->SetXY(20, 120);
$pdf->Cell(160, 20, 'Reliable Inclusive Skill Education', 0, 0, 'C');
$pdf->StopTransform();
$pdf->SetAlpha(1);
$pdf->SetTextColor(33, 37, 41);

// ===================== BORDER =====================
$pdf->SetDrawColor(13, 110, 253);
$pdf->SetLineWidth(1);
$pdf->Rect(8, 8, 194, 280, 'D');
$pdf->SetLineWidth(0.3);
$pdf->Rect(10, 10, 190, 276, 'D');

// ===================== HEADER SECTION =====================
// Logo - CENTER
// Logo above tagline
$logo_path = __DIR__ . '/assets/images/logo.jpg';

if (file_exists($logo_path)) {
    // Bigger centered logo
    $pdf->Image($logo_path, 85, 20, 40, 0, '', '', '', true, 300, '', false, false, 0);
}

// Tagline just below logo
// MAIN HEADING (BIG + BOLD + FULL WIDTH)
$pdf->SetFont('helvetica', 'B', 18);
$pdf->SetTextColor(13, 54, 100);
$pdf->SetXY(15, 35);
$pdf->Cell(180, 8, 'RELIABLE INCLUSIVE SKILL EDUCATION', 0, 1, 'C');

 

// Extra institute description
$W = 180; // page content width

$pdf->SetFont('helvetica', '', 7);
$pdf->SetTextColor(80, 80, 80);

$pdf->Cell($W, 4,
    '(An Autonomous Institution Registered Under the Companies Act, 2013 / Sec 18, Incorporated Under Ministry of Corporate Affairs, Government of India)',
    0, 1, 'C'
);
// Document title
$pdf->Ln(2);
$pdf->SetFillColor(13, 110, 253);
$pdf->SetTextColor(255, 255, 255);
$pdf->SetFont('helvetica', 'B', 14);
$pdf->SetX(50);
$pdf->Cell(110, 9, 'STATEMENT OF MARKS', 0, 1, 'C', true);

// ===================== STUDENT INFO =====================
$pdf->Ln(6);
$pdf->SetTextColor(33, 37, 41);

// Photo on right side
$photo_path = 'uploads/photos/' . $student['photo'];
if (!empty($student['photo']) && file_exists($photo_path)) {
    $pdf->Image($photo_path, 160, 70, 28, 32, '', '', '', true, 300);
    $pdf->SetDrawColor(13, 110, 253);
    $pdf->SetLineWidth(0.4);
    $pdf->Rect(159.5, 69.5, 29, 33, 'D');
}

// Student details - left column
$details_left = [
   'Center Name' => strtoupper($admin['college_name'] ?? ''),
    'Student Name' => strtoupper($student['full_name']),
    "Father's Name" => strtoupper($student['father_name']),
    "Mother's Name" => strtoupper($student['mother_name']),
    'Date of Birth' => date('d-m-Y', strtotime($student['dob'])),
    'Enrollment No' => $student['enrollment_no'],
    'Roll No' => $student['roll_no'],
    'Program' => $student['program_name'],
    'Course' => $student['course_name'],
    'Session' => $student['session_name'],
    'Batch' => $student['batch'],
];

$y = 70;
foreach ($details_left as $label => $value) {
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->SetXY(18, $y);
    $pdf->Cell(35, 5, $label, 0, 0, 'L');
    $pdf->SetFont('helvetica', '', 9);
    $pdf->Cell(5, 5, ':', 0, 0, 'C');
    $pdf->Cell(95, 5, htmlspecialchars($value), 0, 1, 'L');
    $y += 5.5;
}

// ===================== MARKS TABLE =====================
$pdf->Ln(8);
$table_y = $pdf->GetY();

// Table header
$pdf->SetFillColor(13, 110, 253);
$pdf->SetTextColor(255, 255, 255);
$pdf->SetFont('helvetica', 'B', 9);
$pdf->SetDrawColor(13, 110, 253);
$pdf->SetLineWidth(0.3);

$pdf->SetX(18);
$pdf->Cell(12, 8, 'S.No', 1, 0, 'C', true);
$pdf->Cell(75, 8, 'Subject Name', 1, 0, 'C', true);
$pdf->Cell(25, 8, 'Max Marks', 1, 0, 'C', true);
$pdf->Cell(30, 8, 'Marks Obtained', 1, 0, 'C', true);
$pdf->Cell(22, 8, 'Grade', 1, 1, 'C', true);

// Table body
$pdf->SetTextColor(33, 37, 41);
$pdf->SetDrawColor(200, 200, 200);
$pdf->SetFont('helvetica', '', 9);

$sno = 1;
$fill = false;
foreach ($marks as $mark) {
    if ($fill) {
        $pdf->SetFillColor(248, 249, 250);
    } else {
        $pdf->SetFillColor(255, 255, 255);
    }

    $pdf->SetX(18);
    $pdf->Cell(12, 7, $sno, 1, 0, 'C', true);
    $pdf->Cell(75, 7, htmlspecialchars($mark['subject_name']), 1, 0, 'L', true);
    $pdf->Cell(25, 7, $mark['total_marks'], 1, 0, 'C', true);
    $pdf->Cell(30, 7, $mark['marks_obtained'], 1, 0, 'C', true);

    // Individual subject grade
    $subj_pct = ($mark['total_marks'] > 0) ? ($mark['marks_obtained'] / $mark['total_marks']) * 100 : 0;
    if ($subj_pct >= 75) $subj_grade = 'A';
    elseif ($subj_pct >= 60) $subj_grade = 'B';
    elseif ($subj_pct >= 50) $subj_grade = 'C';
    else $subj_grade = 'F';

    $pdf->Cell(22, 7, $subj_grade, 1, 1, 'C', true);

    $sno++;
    $fill = !$fill;
}

// Total row
$pdf->SetFont('helvetica', 'B', 9);
$pdf->SetFillColor(13, 110, 253);
$pdf->SetTextColor(255, 255, 255);
$pdf->SetX(18);
$pdf->Cell(87, 8, 'TOTAL', 1, 0, 'R', true);
$pdf->Cell(25, 8, $total_max, 1, 0, 'C', true);
$pdf->Cell(30, 8, $total_obtained, 1, 0, 'C', true);
$pdf->Cell(22, 8, $overall_grade, 1, 1, 'C', true);

$pdf->Ln(5);
$pdf->SetFont('helvetica', 'B', 10);

$pdf->SetX(20);
$pdf->Cell(60, 8, 'Total Maximum Marks:', 0, 0, 'L');
$pdf->Cell(30, 8, $total_max, 0, 1, 'L');

$pdf->SetX(20);
$pdf->Cell(60, 8, 'Total Obtained Marks:', 0, 0, 'L');
$pdf->Cell(30, 8, $total_obtained, 0, 1, 'L');

$percentage = ($total_max > 0)
    ? round(($total_obtained / $total_max) * 100, 2)
    : 0;

if ($percentage >= 75) {
    $grade_text = 'First Division';
    $overall_grade = 'A';
} elseif ($percentage >= 60) {
    $grade_text = 'Second Division';
    $overall_grade = 'B';
} elseif ($percentage >= 50) {
    $grade_text = 'Third Division';
    $overall_grade = 'C';
} else {
    $grade_text = 'Fail';
    $overall_grade = 'F';
}

$result_status = ($percentage >= 50) ? 'PASS' : 'FAIL';

// ===================== RESULT SUMMARY =====================
$pdf->Ln(6);
$pdf->SetTextColor(33, 37, 41);

// Result box
$pdf->SetFont('helvetica', 'B', 10);
$pdf->SetX(18);
$pdf->Cell(40, 7, 'Percentage:', 0, 0, 'L');
$pdf->SetFont('helvetica', '', 10);
$pdf->Cell(40, 7, $percentage . '%', 0, 0, 'L');

$pdf->SetFont('helvetica', 'B', 10);
$pdf->Cell(30, 7, 'Division:', 0, 0, 'L');
$pdf->SetFont('helvetica', '', 10);
$pdf->Cell(50, 7, $grade_text, 0, 1, 'L');

$pdf->SetFont('helvetica', 'B', 10);
$pdf->SetX(18);
$pdf->Cell(40, 7, 'Overall Grade:', 0, 0, 'L');
$pdf->SetFont('helvetica', '', 10);
$pdf->Cell(40, 7, $overall_grade, 0, 0, 'L');

$pdf->SetFont('helvetica', 'B', 10);
$pdf->Cell(30, 7, 'Result:', 0, 0, 'L');

if ($result_status === 'PASS') {
    $pdf->SetTextColor(25, 135, 84);
} else {
    $pdf->SetTextColor(220, 53, 69);
}
$pdf->SetFont('helvetica', 'B', 13);
$pdf->Cell(50, 7, $result_status, 0, 1, 'L');

// ===================== ISSUE DATE & SIGNATURES =====================
$pdf->SetTextColor(33, 37, 41);
$pdf->Ln(10);

$pdf->SetFont('helvetica', '', 9);
$pdf->SetX(18);
$pdf->Cell(80, 5, 'Date of Issue: ' . date('d-m-Y'), 0, 0, 'L');

// Signature area
// Move down
$pdf->Ln(10);

// Signature image path (JPG recommended)
$signature_path = __DIR__ . '/assets/images/authorized_signature.jpg';

// Current Y position
$currentY = $pdf->GetY();

// LEFT SIGNATURE (Controller)
if (file_exists($signature_path)) {
    $pdf->Image($signature_path, 18, $currentY, 35, 12, 'JPG');
}

// RIGHT SIGNATURE (Director)
if (file_exists($signature_path)) {
    $pdf->Image($signature_path, 145, $currentY, 35, 12, 'JPG');
}

// Move below signature images
$pdf->Ln(15);

// Draw signature lines
$pdf->SetX(18);

$pdf->Line(140, $pdf->GetY(), 185, $pdf->GetY());

// Titles


$pdf->SetX(140);
$pdf->Cell(45, 5, 'Director', 0, 1, 'C');

// ===================== VERIFICATION NOTE =====================
// ===================== QR CODE (Bottom Left) =====================

// Verification URL
$verify_url = 'https://' . ($_SERVER['HTTP_HOST'] ?? 'rise.example.com') .
              '/verify_student.php?enrollment=' . urlencode($student['enrollment_no']);

// Position for QR (bottom left)
$qr_x = 18;
$qr_y = 245; // Adjust if needed

// QR style
$style = array(
    'border' => 0,
    'padding' => 1,
    'fgcolor' => array(0,0,0),
    'bgcolor' => false
);

// Generate QR Code (size 25x25)
$pdf->write2DBarcode($verify_url, 'QRCODE,H', $qr_x, $qr_y, 25, 25, $style, 'N');

// QR Label
$pdf->SetFont('helvetica', '', 7);
$pdf->SetTextColor(33, 37, 41);
$pdf->SetXY($qr_x, $qr_y + 26);
$pdf->Cell(25, 4, 'Scan to Verify', 0, 0, 'C');
$pdf->Ln(5);
$pdf->SetFont('helvetica', 'I', 7);
$pdf->SetTextColor(108, 117, 125);
$pdf->SetX(15);


// ===================== GRADING SCALE =====================
$pdf->Ln(2);
$pdf->SetFont('helvetica', 'B', 7);
$pdf->SetTextColor(33, 37, 41);
$pdf->SetX(18);
$pdf->Cell(160, 4, 'Grading Scale: A (75% & above) | B (60-74%) | C (50-59%) | F (Below 50%)', 0, 1, 'L');


// ============================================================
// ===================== PAGE 2 - BACK PAGE ===================
// ============================================================

$pdf->AddPage();

// Reset colors & fonts for back page
$pdf->SetTextColor(33, 37, 41);
$pdf->SetDrawColor(0, 0, 0);
$pdf->SetLineWidth(0.3);

// --- Decorative border (matching style of front) ---
$pdf->SetDrawColor(13, 110, 253);
$pdf->SetLineWidth(1);
$pdf->Rect(8, 8, 194, 280, 'D');
$pdf->SetLineWidth(0.3);
$pdf->Rect(10, 10, 190, 276, 'D');

// ===================== TITLE: Instruction =====================
$pdf->SetFont('helvetica', 'B', 14);
$pdf->SetTextColor(33, 37, 41);
$pdf->SetXY(15, 18);
$pdf->Cell(180, 8, 'Instruction', 0, 1, 'C');

// Underline the title
$pdf->SetDrawColor(33, 37, 41);
$pdf->SetLineWidth(0.5);
$pdf->Line(75, 26, 135, 26);

$pdf->Ln(4);

// ===================== INSTRUCTIONS LIST =====================
$pdf->SetFont('helvetica', '', 9);
$pdf->SetTextColor(33, 37, 41);
$pdf->SetLineWidth(0.2);

$instructions = [
    'Pass : 35% marks in each subject with an overall aggregate of 35% for all put together.',
    "Division :\n    Distinction         -    80% & above\n    First Division      -    65% & above\n    Second Division  -    55% & above",
    'Carry Forward : One in which a student scores less than 35% marks, examination for carry forward paper will be held with regular student only.',
    'Re-evaluation : Application for re-evaluation of only theory papers(s) shall be accepted within 15 days of the date of declaration of results. Re-evaluation relates to re-totalling and evaluation of only those questions which have not been evaluated.',
    'This mark card cannot be used as a legal proof for date of birth.',
];

$y = $pdf->GetY();
foreach ($instructions as $idx => $instruction) {
    $num = $idx + 1;
    $pdf->SetXY(15, $y);
    $pdf->SetFont('helvetica', '', 9);
    $pdf->Cell(7, 5, $num . '.', 0, 0, 'L');

    // Handle multi-line division instruction specially
    if ($idx === 1) {
        $pdf->SetXY(22, $y);
        $pdf->Cell(20, 5, 'Division :', 0, 0, 'L');
        $y += 5;
        $divisions = [
            ['Distinction',    '80% & above'],
            ['First Division', '65% & above'],
            ['Second Division','55% & above'],
        ];
        foreach ($divisions as $div) {
            $pdf->SetXY(30, $y);
            $pdf->Cell(35, 5, $div[0], 0, 0, 'L');
            $pdf->Cell(8,  5, '-',     0, 0, 'C');
            $pdf->Cell(35, 5, $div[1], 0, 0, 'L');
            $y += 5;
        }
    } else {
        $pdf->SetXY(22, $y);
        $pdf->MultiCell(168, 5, $instruction, 0, 'L');
        $y = $pdf->GetY() + 2;
    }
    $y += 2;
}

// ===================== ABBREVIATIONS =====================
$pdf->Ln(4);
$pdf->SetFont('helvetica', 'B', 9);
$pdf->SetX(15);
$abbr = [
    'TH - THEORY',
    'PR - PRACTICAL',
    'IA - INTERNAL ASSESSMENT',
    'YR - YEAR',
    'SEM - SEMESTER',
];
foreach ($abbr as $line) {
    $pdf->SetX(15);
    $pdf->Cell(180, 6, $line, 0, 1, 'L');
}

// ===================== GRADING TABLE =====================
$pdf->Ln(4);

// Table title bar
$pdf->SetFillColor(33, 37, 41);
$pdf->SetTextColor(255, 255, 255);
$pdf->SetFont('helvetica', 'B', 11);
$pdf->SetX(15);
$pdf->Cell(180, 8, 'Grading of Marks Obtained', 1, 1, 'C', true);

// Table column headers
$pdf->SetFillColor(230, 230, 230);
$pdf->SetTextColor(33, 37, 41);
$pdf->SetFont('helvetica', 'B', 9);
$pdf->SetX(15);
$pdf->Cell(45, 7, 'Marks Range', 1, 0, 'C', true);
$pdf->Cell(45, 7, 'Grade',       1, 0, 'C', true);
$pdf->Cell(45, 7, 'Marks Range', 1, 0, 'C', true);
$pdf->Cell(45, 7, 'Grade',       1, 1, 'C', true);

// Table rows - two columns layout
$grade_rows = [
    ['90 - 100', 'A+', '40 - 49', 'C'],
    ['75 - 89',  'A',  '33 - 39', 'D+'],
    ['70 - 74',  'B+', '20 - 32', 'D'],
    ['60 - 69',  'B',  '0 - 19',  'E'],
    ['50 - 59',  'C+', '',        ''],
];

$pdf->SetFont('helvetica', '', 9);
$fill_row = false;
foreach ($grade_rows as $row) {
    if ($fill_row) {
        $pdf->SetFillColor(248, 249, 250);
    } else {
        $pdf->SetFillColor(255, 255, 255);
    }
    $pdf->SetX(15);
    $pdf->Cell(45, 6, $row[0], 1, 0, 'C', true);
    $pdf->Cell(45, 6, $row[1], 1, 0, 'C', true);
    $pdf->Cell(45, 6, $row[2], 1, 0, 'C', true);
    $pdf->Cell(45, 6, $row[3], 1, 1, 'C', true);
    $fill_row = !$fill_row;
}

// ===================== QR CODE (Bottom Left) =====================
$pdf->Ln(10);
$back_qr_y = $pdf->GetY();

$back_verify_url = 'https://' . ($_SERVER['HTTP_HOST'] ?? 'rise.example.com') .
                   '/verify_student.php?enrollment=' . urlencode($student['enrollment_no']);

$back_qr_style = [
    'border'   => 0,
    'padding'  => 1,
    'fgcolor'  => [0, 0, 0],
    'bgcolor'  => false,
];

$pdf->write2DBarcode($back_verify_url, 'QRCODE,H', 15, $back_qr_y, 28, 28, $back_qr_style, 'N');

$pdf->SetFont('helvetica', '', 7);
$pdf->SetTextColor(33, 37, 41);
$pdf->SetXY(15, $back_qr_y + 29);
$pdf->Cell(28, 4, 'Scan for Verification', 0, 0, 'C');

// ===================== RIGHT SIDE: Checked & Signature =====================
$pdf->SetFont('helvetica', 'B', 9);
$pdf->SetTextColor(33, 37, 41);
$pdf->SetXY(110, $back_qr_y);
$pdf->Cell(85, 5, 'Checked and Entered in the result record', 0, 1, 'C');

// Signature stamp circle
$pdf->SetDrawColor(13, 110, 253);
$pdf->SetLineWidth(0.5);
$pdf->Circle(152, $back_qr_y + 16, 12, 0, 360, 'D');

// Seal Image inside the circle
$seal_path = __DIR__ . '/assets/images/seal.jpg';

if (file_exists($seal_path)) {
    $pdf->Image($seal_path, 140, $back_qr_y + 4, 24, 24, '', '', '', true, 300);
}

// Signature line
$pdf->SetDrawColor(33, 37, 41);
$pdf->SetLineWidth(0.4);
$pdf->Line(110, $back_qr_y + 32, 195, $back_qr_y + 32);

$pdf->SetFont('helvetica', '', 9);
$pdf->SetTextColor(33, 37, 41);
$pdf->SetXY(110, $back_qr_y + 33);
$pdf->Cell(85, 5, 'Authorized Seal', 0, 0, 'C');


// ============================================================
// ===================== SAVE & OUTPUT ========================
// ============================================================

// Save PDF to file
$pdf_filename = 'MS_' . $student['enrollment_no'] . '_' . time() . '.pdf';
$pdf_path = __DIR__ . '/uploads/marksheets/' . $pdf_filename;

if (!is_dir(__DIR__ . '/uploads/marksheets')) {
    mkdir(__DIR__ . '/uploads/marksheets', 0755, true);
}

$pdf->Output($pdf_path, 'F');

// Update student record
$update_stmt = $db->prepare("UPDATE students SET marksheet_pdf = :pdf WHERE id = :id");
$update_stmt->execute([
    ':pdf' => $pdf_filename,
    ':id'  => $student_id
]);

// Output to browser
$pdf->Output('RISE_Marksheet_' . $student['enrollment_no'] . '.pdf', 'I');
exit;