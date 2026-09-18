<?php
/**
 * RISE SaaS - Generate Marksheet (TCPDF) - With TH / PR columns
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

$student_id = (int)($_GET['id'] ?? $_GET['student_id'] ?? 0);
if ($student_id <= 0) {
    setFlashMessage('error', 'Invalid student ID.');
    header('Location: students.php');
    exit;
}

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
    $stmt->execute([':student_id' => $student_id, ':admin_id' => $_SESSION['user_id']]);
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
if ($student['marksheet_approved'] !== 'Approved') {
    setFlashMessage('error', 'Marksheet is locked until Super Admin approval.');
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
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . basename($existing_path) . '"');
        readfile($existing_path);
        exit;
    }
}

// ===================== AUTO SERIAL NUMBER =====================
$serial_number = $student['marksheet_serial'] ?? '';

if (empty($serial_number)) {

    // Year = 26
    $year_suffix = date('y');

    // Student ID as middle value
    $student_code = str_pad($student_id, 2, '0', STR_PAD_LEFT);

    $db->beginTransaction();

    try {

        // Prefix example = 2601
        $serial_prefix = $year_suffix . $student_code;

        $seq_stmt = $db->prepare("
            SELECT marksheet_serial
            FROM students
            WHERE marksheet_serial LIKE :prefix
            ORDER BY marksheet_serial DESC
            LIMIT 1
            FOR UPDATE
        ");

        $seq_stmt->execute([
            ':prefix' => $serial_prefix . '%'
        ]);

        $last = $seq_stmt->fetchColumn();

        if ($last) {

            // Last 4 digit running number
            $last_num = (int) substr($last, -4);

            $next_num = $last_num + 1;

        } else {

            $next_num = 1;
        }

        // Final format: 26010001
        $serial_number = $serial_prefix . str_pad($next_num, 4, '0', STR_PAD_LEFT);

        // Save serial
        $save_serial = $db->prepare("
            UPDATE students
            SET marksheet_serial = :serial
            WHERE id = :id
        ");

        $save_serial->execute([
            ':serial' => $serial_number,
            ':id' => $student_id
        ]);

        $db->commit();

    } catch (Exception $e) {

        $db->rollBack();

        // Fallback
        $serial_number = $year_suffix . $student_code . str_pad($student_id, 4, '0', STR_PAD_LEFT);
    }
}

// ===================== FETCH MARKS WITH PRACTICAL =====================
$marks_stmt = $db->prepare("
    SELECT m.*, m.th_marks, m.pr_marks,
           sub.subject_name, sub.total_marks, sub.has_practical, sub.practical_marks as pr_max
    FROM marks m
    JOIN subjects sub ON m.subject_id = sub.id
    WHERE m.student_id = :student_id
    ORDER BY sub.subject_name ASC
");
$marks_stmt->execute([':student_id' => $student_id]);
$marks = $marks_stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($marks)) {
    setFlashMessage('error', 'No marks found. Please enter marks first.');
    header('Location: view_student.php?id=' . $student_id);
    exit;
}

// ===================== TOTALS =====================
$total_max      = 0;
$total_obtained = 0;
foreach ($marks as $mark) {
    $th_max = (int)$mark['total_marks'];
    $pr_max = (int)($mark['pr_max'] ?? 0);
    $total_max      += $th_max + $pr_max;
    $total_obtained += (int)$mark['marks_obtained'];
}
$percentage = ($total_max > 0) ? round(($total_obtained / $total_max) * 100, 2) : 0;
if ($percentage >= 75)      { $grade_text = 'First Division';  $overall_grade = 'A'; }
elseif ($percentage >= 60)  { $grade_text = 'Second Division'; $overall_grade = 'B'; }
elseif ($percentage >= 50)  { $grade_text = 'Third Division';  $overall_grade = 'C'; }
else                        { $grade_text = 'Fail';            $overall_grade = 'F'; }
$result_status = ($percentage >= 50) ? 'PASS' : 'FAIL';

// ===================== LOAD TCPDF =====================
if (!class_exists('TCPDF')) {
    $paths = [
        __DIR__ . '/lib/TCPDF/tcpdf.php',
        __DIR__ . '/lib/tcpdf/tcpdf.php',
        __DIR__ . '/vendor/tecnickcom/tcpdf/tcpdf.php',
        __DIR__ . '/tcpdf/tcpdf.php',
    ];
    foreach ($paths as $p) {
        if (file_exists($p)) { require_once $p; break; }
    }
    if (!class_exists('TCPDF') && file_exists(__DIR__ . '/vendor/autoload.php')) {
        require_once __DIR__ . '/vendor/autoload.php';
    }
}
if (!class_exists('TCPDF')) die('TCPDF not found.');

class RISE_Marksheet extends TCPDF {
    public function Header() {}
    public function Footer() {}
}

// ===================== CREATE PDF =====================
$pdf = new RISE_Marksheet('P', 'mm', 'A4', true, 'UTF-8', false);
$pdf->SetCreator('RISE SaaS');
$pdf->SetAuthor('RISE');
$pdf->SetTitle('Marksheet - ' . htmlspecialchars($student['full_name']));
$pdf->SetMargins(15, 10, 15);
$pdf->SetAutoPageBreak(false, 0);
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
$pdf->Cell(160, 20, 'National Board for Technical Education', 0, 0, 'C');
$pdf->StopTransform();
$pdf->SetAlpha(1);
$pdf->SetTextColor(33, 37, 41);

// ===================== BORDER =====================
$pdf->SetDrawColor(212, 151, 41);
$pdf->SetLineWidth(1);
$pdf->Rect(8, 8, 194, 281, 'D');
$pdf->SetLineWidth(0.3);
$pdf->Rect(10, 10, 190, 277, 'D');

// ===================== LOGO =====================
$logo_path = __DIR__ . '/assets/images/logo.jpg';
if (file_exists($logo_path)) {
    $pdf->Image($logo_path, 85, 13, 40, 0, '', '', '', true, 300);
}

// ===================== INSTITUTE NAME =====================
$pdf->SetFont('helvetica', 'B', 18);
$pdf->SetTextColor(13, 54, 100);
$pdf->SetXY(15, 28);
$pdf->Cell(180, 8, 'National Board for Technical Education', 0, 1, 'C');
$pdf->SetFont('helvetica', '', 7);
$pdf->SetTextColor(80, 80, 80);
$pdf->SetX(15);
$pdf->Cell(180, 4,
    '(An Autonomous Institution Registered Under the Companies Act, 2013 / Sec 18, Incorporated Under Ministry of Corporate Affairs, Government of India)',
    0, 1, 'C'
);

// ===================== STATEMENT OF MARKS BAR =====================
$pdf->Ln(2);
$pdf->SetDrawColor(212, 151, 41);
$pdf->SetTextColor(255, 255, 255);
$pdf->SetFont('helvetica', 'B', 14);
$pdf->SetX(50);
$pdf->Cell(110, 9, 'STATEMENT OF MARKS', 0, 1, 'C', true);

// ===================== STUDENT DETAILS + PHOTO =====================
$details_start_y = $pdf->GetY() + 4;

$photo_x = 163; $photo_y = $details_start_y; $photo_w = 28; $photo_h = 33;
$photo_path = 'uploads/photos/' . ($student['photo'] ?? '');
if (!empty($student['photo']) && file_exists($photo_path)) {
    $pdf->Image($photo_path, $photo_x, $photo_y, $photo_w, $photo_h, '', '', '', true, 300);
}
$pdf->SetDrawColor(13, 110, 253);
$pdf->SetLineWidth(0.5);
$pdf->Rect($photo_x - 0.5, $photo_y - 0.5, $photo_w + 1, $photo_h + 1, 'D');

$details_left = [
    'Center Name'   => strtoupper($admin['college_name'] ?? ''),
    'Student Name'  => strtoupper($student['full_name'] ?? ''),
    "Father's Name" => strtoupper($student['father_name'] ?? ''),
    "Mother's Name" => strtoupper($student['mother_name'] ?? ''),
    'Date of Birth' => !empty($student['dob']) ? date('d-m-Y', strtotime($student['dob'])) : '',
    'Enrollment No' => $student['enrollment_no'] ?? '',
    'Roll No'       => $student['roll_no'] ?? '',
    'Program'       => $student['program_name'] ?? '',
    'Course'        => $student['course_name'] ?? '',
    'Session'       => $student['session_name'] ?? '',
    'Batch'         => $student['batch'] ?? '',
];

$y = $details_start_y;
$row_h = 5.5;
foreach ($details_left as $label => $value) {
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->SetTextColor(33, 37, 41);
    $pdf->SetXY(18, $y);
    $pdf->Cell(35, $row_h, $label, 0, 0, 'L');
    $pdf->SetFont('helvetica', '', 9);
    $pdf->Cell(5, $row_h, ':', 0, 0, 'C');
    $pdf->Cell(100, $row_h, htmlspecialchars($value), 0, 1, 'L');
    $y += $row_h;
}

// ===================== SERIAL NUMBER TOP LEFT =====================
$pdf->SetFont('helvetica', 'B', 10);
$pdf->SetTextColor(0, 0, 0); // black color

$pdf->SetXY(13, 14); // top-left position
$pdf->Cell(22, 5, 'Serial No :', 0, 0, 'L');

$pdf->SetFont('helvetica', 'B', 11);
$pdf->Cell(35, 5, $serial_number, 0, 1, 'L');

// ===================== FULL WIDTH MARKS TABLE =====================
$table_start_y = $y + 4;

// ---- Page width calculation ----
$pageWidth = $pdf->getPageWidth();
$leftMargin = 10;
$rightMargin = 10;
$usableWidth = $pageWidth - ($leftMargin + $rightMargin);

$pdf->SetXY($leftMargin, $table_start_y);

// ---- Font / padding ----
$pdf->SetFont('helvetica', '', 7.5);
$pdf->SetCellPadding(1);

// ---- Check practical ----
$anyPractical = false;
foreach ($marks as $m) {
    if ($m['has_practical']) { $anyPractical = true; break; }
}

// ---- Column widths (FULL WIDTH AUTO FIT) ----
$wSno     = 8;
$wSubject = 60;
$wGrade   = 12;

// remaining width split
$remaining = $usableWidth - ($wSno + $wSubject + $wGrade);
$colCount  = $anyPractical ? 8 : 6;
$col       = $remaining / $colCount;

// scheme
$wThMax  = $col;
$wThMin  = $col;
$wPrMax  = $col;
$wPrMin  = $col;
$wTotMax = $col;

// obtained
$wTh    = $col;
$wPr    = $col;
$wTotal = $col;

// ================= HEADER =================
$pdf->SetFillColor(13,110,253);
$pdf->SetTextColor(255,255,255);
$pdf->SetFont('helvetica','B',8);

$pdf->SetX($leftMargin);
$pdf->Cell($wSno,8,'S.No',1,0,'C',true);
$pdf->Cell($wSubject,8,'Subject',1,0,'C',true);

// grouped headers
$schemeWidth = $wThMax + $wThMin + ($anyPractical ? $wPrMax + $wPrMin : 0) + $wTotMax;
$obtainedWidth = $wTh + ($anyPractical ? $wPr : 0) + $wTotal + $wGrade;

$pdf->Cell($schemeWidth,4,'SCHEME OF MARKS',1,0,'C',true);
$pdf->Cell($obtainedWidth,4,'MARKS OBTAINED',1,1,'C',true);

// sub headers
$pdf->SetX($leftMargin);
$pdf->Cell($wSno,4,'',1,0,'C',true);
$pdf->Cell($wSubject,4,'',1,0,'C',true);

$pdf->Cell($wThMax,4,'TH MAX',1,0,'C',true);
$pdf->Cell($wThMin,4,'TH MIN',1,0,'C',true);

if ($anyPractical) {
    $pdf->Cell($wPrMax,4,'PR MAX',1,0,'C',true);
    $pdf->Cell($wPrMin,4,'PR MIN',1,0,'C',true);
}

$pdf->Cell($wTotMax,4,'TOTAL',1,0,'C',true);

$pdf->Cell($wTh,4,'TH',1,0,'C',true);

if ($anyPractical) {
    $pdf->Cell($wPr,4,'PR',1,0,'C',true);
}

$pdf->Cell($wTotal,4,'TOTAL',1,0,'C',true);
$pdf->Cell($wGrade,4,'GRADE',1,1,'C',true);

// ================= DATA =================
$pdf->SetTextColor(33,37,41);
$pdf->SetDrawColor(200,200,200);
$pdf->SetFont('helvetica','',7.5);

$sno = 1;
$fill = false;

// totals
$total_th = 0;
$total_pr = 0;

foreach ($marks as $mark) {

    $th_max = (int)$mark['total_marks'];
    $pr_max = (int)($mark['pr_max'] ?? 0);

    $th_min = round($th_max * 0.35);
    $pr_min = round($pr_max * 0.35);

    $th_obt = (int)($mark['th_marks'] ?? 0);
    $pr_obt = (int)($mark['pr_marks'] ?? 0);

    $tot_max = $th_max + $pr_max;
    $tot_obt = $th_obt + $pr_obt;

    $total_th += $th_obt;
    $total_pr += $pr_obt;

    $pct = ($tot_max > 0) ? ($tot_obt/$tot_max)*100 : 0;
    $grade = ($pct>=90)?'A+':(($pct>=75)?'A':(($pct>=60)?'B':(($pct>=50)?'C':'F')));

    $pdf->SetFillColor($fill?248:255,$fill?249:255,$fill?250:255);

    $pdf->SetX($leftMargin);
    $pdf->Cell($wSno,7,$sno,1,0,'C',true);

    // trim long subject
    $pdf->Cell($wSubject,7,substr($mark['subject_name'],0,30),1,0,'L',true);

    $pdf->Cell($wThMax,7,$th_max,1,0,'C',true);
    $pdf->Cell($wThMin,7,$th_min,1,0,'C',true);

    if ($anyPractical) {
        $pdf->Cell($wPrMax,7,$mark['has_practical']?$pr_max:'-',1,0,'C',true);
        $pdf->Cell($wPrMin,7,$mark['has_practical']?$pr_min:'-',1,0,'C',true);
    }

    $pdf->Cell($wTotMax,7,$tot_max,1,0,'C',true);

    $pdf->Cell($wTh,7,$th_obt,1,0,'C',true);

    if ($anyPractical) {
        $pdf->Cell($wPr,7,$mark['has_practical']?$pr_obt:'-',1,0,'C',true);
    }

    $pdf->Cell($wTotal,7,$tot_obt,1,0,'C',true);
    $pdf->Cell($wGrade,7,$grade,1,1,'C',true);

    $fill = !$fill;
    $sno++;
}

// ================= TOTAL ROW =================
$pdf->SetFont('helvetica','B',8);
$pdf->SetFillColor(13,110,253);
$pdf->SetTextColor(255,255,255);

$pdf->SetX($leftMargin);

$colspan = $wSno + $wSubject + $wThMax + $wThMin + ($anyPractical ? $wPrMax + $wPrMin : 0) + $wTotMax;

$pdf->Cell($colspan,8,'TOTAL',1,0,'R',true);

// TH total
$pdf->Cell($wTh,8,$total_th,1,0,'C',true);

// PR total
if ($anyPractical) {
    $pdf->Cell($wPr,8,$total_pr,1,0,'C',true);
}

// final total
$pdf->Cell($wTotal,8,$total_obtained,1,0,'C',true);

// grade
$pdf->Cell($wGrade,8,$overall_grade,1,1,'C',true);

// ===================== RESULT SUMMARY =====================
$pdf->Ln(4);
$pdf->SetTextColor(33, 37, 41);
$pdf->SetFont('helvetica', 'B', 10);
$pdf->SetX(20);
$pdf->Cell(60, 7, 'Total Maximum Marks:', 0, 0, 'L');
$pdf->SetFont('helvetica', '', 10);
$pdf->Cell(30, 7, $total_max, 0, 1, 'L');

$pdf->SetFont('helvetica', 'B', 10);
$pdf->SetX(20);
$pdf->Cell(60, 7, 'Total Obtained Marks:', 0, 0, 'L');
$pdf->SetFont('helvetica', '', 10);
$pdf->Cell(30, 7, $total_obtained, 0, 1, 'L');

$pdf->SetFont('helvetica', 'B', 10);
$pdf->SetX(20);
$pdf->Cell(40, 7, 'Percentage:', 0, 0, 'L');
$pdf->SetFont('helvetica', '', 10);
$pdf->Cell(40, 7, $percentage . '%', 0, 0, 'L');
$pdf->SetFont('helvetica', 'B', 10);
$pdf->Cell(30, 7, 'Division:', 0, 0, 'L');
$pdf->SetFont('helvetica', '', 10);
$pdf->Cell(50, 7, $grade_text, 0, 1, 'L');

$pdf->SetFont('helvetica', 'B', 10);
$pdf->SetX(20);
$pdf->Cell(40, 7, 'Overall Grade:', 0, 0, 'L');
$pdf->SetFont('helvetica', '', 10);
$pdf->Cell(40, 7, $overall_grade, 0, 0, 'L');
$pdf->SetFont('helvetica', 'B', 10);
$pdf->Cell(30, 7, 'Result:', 0, 0, 'L');
$pdf->SetTextColor($result_status === 'PASS' ? 25 : 220, $result_status === 'PASS' ? 135 : 53, $result_status === 'PASS' ? 84 : 69);
$pdf->SetFont('helvetica', 'B', 13);
$pdf->Cell(50, 7, $result_status, 0, 1, 'L');
$pdf->SetTextColor(33, 37, 41);

// ===================== ISSUE DATE =====================
$pdf->Ln(3);
$pdf->SetFont('helvetica', '', 9);
$pdf->SetX(18);
$pdf->Cell(80, 5, 'Date of Issue: ' . date('d-m-Y'), 0, 0, 'L');
$pdf->Ln(5);

// ===================== QR + SIGNATURE ROW =====================
$sig_y = $pdf->GetY();
$verify_url = 'https://' . ($_SERVER['HTTP_HOST'] ?? 'rise.example.com') .
              '/verify_student.php?enrollment=' . urlencode($student['enrollment_no']) .
              '&serial=' . urlencode($serial_number);

$qr_style = [
    'border' => 0,
    'padding' => 0,
    'fgcolor' => [13, 54, 100],
    'bgcolor' => false
];

// Very small QR
$pdf->write2DBarcode(
    $verify_url,
    'QRCODE,H',
    28,
    $sig_y,
    12,
    12,
    $qr_style,
    'N'
);

// Very small label
$pdf->SetFont('helvetica', 'B', 4.5);
$pdf->SetTextColor(13, 54, 100);
$pdf->SetXY(28, $sig_y + 12);
$pdf->Cell(12, 3, 'Verify', 0, 0, 'C');

$signature_path = __DIR__ . '/assets/images/director_signature.jpg';

if (file_exists($signature_path)) {
    // Smaller signature
    $pdf->Image($signature_path, 145, $sig_y, 20, 7, 'JPG');
}

// Smaller signature line
$pdf->SetDrawColor(33, 37, 41);
$pdf->SetLineWidth(0.2);
$pdf->Line(140, $sig_y + 9, 170, $sig_y + 9);

// Smaller Director text
$pdf->SetFont('helvetica', 'B', 7);
$pdf->SetTextColor(33, 37, 41);
$pdf->SetXY(140, $sig_y + 10);
$pdf->Cell(30, 4, 'Director', 0, 0, 'C');

// Grading scale
// $pdf->SetFont('helvetica', 'B', 7);
// $pdf->SetTextColor(33, 37, 41);
// $pdf->SetXY(18, $sig_y + 44);
// $pdf->Cell(164, 4, 'Grading Scale: A+ (90%+) | A (75-89%) | B (60-74%) | C (50-59%) | F (Below 50%)', 0, 1, 'L');

// ============================================================
// PAGE 2 - BACK PAGE (unchanged from original)
// ============================================================
$pdf->AddPage();
$pdf->SetAutoPageBreak(true, 15);
$pdf->SetTextColor(33, 37, 41);
$pdf->SetDrawColor(212, 151, 41);
$pdf->SetLineWidth(1);
$pdf->Rect(8, 8, 194, 280, 'D');
$pdf->SetLineWidth(0.3);
$pdf->Rect(10, 10, 190, 276, 'D');

$pdf->SetFont('helvetica', 'B', 14);
$pdf->SetTextColor(33, 37, 41);
$pdf->SetXY(15, 18);
$pdf->Cell(180, 8, 'Instruction', 0, 1, 'C');
$pdf->SetDrawColor(33, 37, 41);
$pdf->SetLineWidth(0.5);
$pdf->Line(75, 26, 135, 26);
$pdf->Ln(4);

$pdf->SetFont('helvetica', '', 9);
$pdf->SetLineWidth(0.2);
$instructions = [
    'Pass : 35% marks in each subject with an overall aggregate of 35% for all put together.',
    "Division :",
    'Carry Forward : One in which a student scores less than 35% marks, examination for carry forward paper will be held with regular student only.',
    'Re-evaluation : Application for re-evaluation of only theory papers(s) shall be accepted within 15 days of the date of declaration of results.',
    'This mark card cannot be used as a legal proof for date of birth.',
];
$y = $pdf->GetY();
foreach ($instructions as $idx => $instruction) {
    $pdf->SetXY(15, $y);
    $pdf->SetFont('helvetica', '', 9);
    $pdf->Cell(7, 5, ($idx + 1) . '.', 0, 0, 'L');
    if ($idx === 1) {
        $pdf->SetXY(22, $y);
        $pdf->Cell(20, 5, 'Division :', 0, 0, 'L');
        $y += 5;
        foreach ([['Distinction','80% & above'],['First Division','65% & above'],['Second Division','55% & above']] as $div) {
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

$pdf->Ln(4);
$pdf->SetFont('helvetica', 'B', 9);
foreach (['TH - THEORY','PR - PRACTICAL','IA - INTERNAL ASSESSMENT','YR - YEAR','SEM - SEMESTER'] as $line) {
    $pdf->SetX(15);
    $pdf->Cell(180, 6, $line, 0, 1, 'L');
}

$pdf->Ln(4);
$pdf->SetFillColor(33, 37, 41);
$pdf->SetTextColor(255, 255, 255);
$pdf->SetFont('helvetica', 'B', 11);
$pdf->SetX(15);
$pdf->Cell(180, 8, 'Grading of Marks Obtained', 1, 1, 'C', true);

$pdf->SetFillColor(230, 230, 230);
$pdf->SetTextColor(33, 37, 41);
$pdf->SetFont('helvetica', 'B', 9);
$pdf->SetX(15);
foreach (['Marks Range','Grade','Marks Range','Grade'] as $h) {
    $pdf->Cell(45, 7, $h, 1, 0, 'C', true);
}
$pdf->Ln();

$grade_rows = [['90 - 100','A+','40 - 49','C'],['75 - 89','A','33 - 39','D+'],['70 - 74','B+','20 - 32','D'],['60 - 69','B','0 - 19','E'],['50 - 59','C+','','']];
$pdf->SetFont('helvetica', '', 9);
$fill_row = false;
foreach ($grade_rows as $row) {
    $pdf->SetFillColor($fill_row ? 248 : 255, $fill_row ? 249 : 255, $fill_row ? 250 : 255);
    $pdf->SetX(15);
    foreach ($row as $cell) $pdf->Cell(45, 6, $cell, 1, 0, 'C', true);
    $pdf->Ln();
    $fill_row = !$fill_row;
}

// Back page QR + Seal
$pdf->Ln(10);
$back_qr_y = $pdf->GetY();
$pdf->write2DBarcode($verify_url, 'QRCODE,H', 15, $back_qr_y, 32, 32, $qr_style, 'N');
$pdf->SetFont('helvetica', 'B', 9);
$pdf->SetTextColor(13, 54, 100);
$pdf->SetXY(15, $back_qr_y + 33);
$pdf->Cell(32, 5, 'Scan to Verify', 0, 0, 'C');
$pdf->SetFont('helvetica', 'B', 8);
$pdf->SetTextColor(33, 37, 41);
$pdf->SetXY(15, $back_qr_y + 38);
$pdf->Cell(32, 4, 'Serial No : ' . $serial_number, 0, 0, 'C');

$pdf->SetFont('helvetica', 'B', 9);
$pdf->SetTextColor(33, 37, 41);
$pdf->SetXY(110, $back_qr_y);
$pdf->Cell(85, 5, 'Checked and Entered in the result record', 0, 1, 'C');
$pdf->SetDrawColor(13, 110, 253);
$pdf->SetLineWidth(0.5);
$pdf->Circle(152, $back_qr_y + 16, 12, 0, 360, 'D');
$seal_path = __DIR__ . '/assets/images/seal.jpg';
if (file_exists($seal_path)) {
    $pdf->Image($seal_path, 140, $back_qr_y + 4, 24, 24, '', '', '', true, 300);
}
$pdf->SetDrawColor(33, 37, 41);
$pdf->SetLineWidth(0.4);
$pdf->Line(110, $back_qr_y + 32, 195, $back_qr_y + 32);
$pdf->SetFont('helvetica', 'B', 9);
$pdf->SetXY(110, $back_qr_y + 33);
$pdf->Cell(85, 5, 'Authorized Seal', 0, 0, 'C');

// ===================== SAVE & OUTPUT =====================
$pdf_filename = 'MS_' . $student['enrollment_no'] . '_' . time() . '.pdf';
$pdf_path     = __DIR__ . '/uploads/marksheets/' . $pdf_filename;
if (!is_dir(__DIR__ . '/uploads/marksheets')) {
    mkdir(__DIR__ . '/uploads/marksheets', 0755, true);
}
$pdf->Output($pdf_path, 'F');
$update_stmt = $db->prepare("UPDATE students SET marksheet_pdf = :pdf WHERE id = :id");
$update_stmt->execute([':pdf' => $pdf_filename, ':id' => $student_id]);
$pdf->Output('RISE_Marksheet_' . $student['enrollment_no'] . '.pdf', 'I');
exit;