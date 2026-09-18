<?php
/**
 * RISE - Generate Certificate (Gated by Super Admin Approval)
 * ============================================================
 */

require_once 'includes/auth.php';
requireLogin();

if (!in_array($_SESSION['user_role'], ['admin', 'super_admin'])) {
    header('Location: dashboard.php');
    exit;
}

require_once 'includes/db.php';

$db         = getDB();
$student_id = (int) ($_GET['id'] ?? $_GET['student_id'] ?? 0);

if ($student_id <= 0) {
    setFlashMessage('error', 'Invalid student ID.');
    header('Location: students.php');
    exit;
}


// ── Fetch student ─────────────────────────────────────────────────────────────
if ($_SESSION['user_role'] === 'super_admin') {
    $stmt = $db->prepare("
        SELECT s.*, p.program_name, p.duration, c.course_name, a.name as center_name
        FROM students s
        JOIN programs p  ON s.program_id = p.id
        JOIN courses  c  ON s.course_id  = c.id
        JOIN admins   a  ON s.admin_id   = a.id
        WHERE s.id = :student_id
    ");
    $stmt->execute([':student_id' => $student_id]);
} else {
    $stmt = $db->prepare("
        SELECT s.*, p.program_name, p.duration, c.course_name
        FROM students s
        JOIN programs p ON s.program_id = p.id
        JOIN courses  c ON s.course_id  = c.id
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


$admin_stmt = $db->prepare("SELECT * FROM admins WHERE id = :id");
$admin_stmt->execute([':id' => $student['admin_id']]);
$admin = $admin_stmt->fetch(PDO::FETCH_ASSOC);



// ── Gates ─────────────────────────────────────────────────────────────────────
if ($student['status'] !== 'Approved') {
    setFlashMessage('error', 'Certificate can only be generated for approved students.');
    header('Location: view_student.php?id=' . $student_id);
    exit;
}

if ($_SESSION['user_role'] !== 'super_admin') {
    if (($student['certificate_approved'] ?? 'Pending') !== 'Approved') {
        setFlashMessage('error', 'Certificate generation has not been approved by Super Admin yet.');
        header('Location: view_student.php?id=' . $student_id);
        exit;
    }
}

$marks_check = $db->prepare("SELECT COUNT(*) FROM marks WHERE student_id = :student_id");
$marks_check->execute([':student_id' => $student_id]);
if ($marks_check->fetchColumn() == 0) {
    setFlashMessage('error', 'Marks must be entered before generating certificate.');
    header('Location: view_student.php?id=' . $student_id);
    exit;
}


$college_name = trim($admin['college_name'] ?? '');
$admin_name   = trim($admin['name']);
$admin_email  = trim($admin['email']);

// ── Calculate marks ───────────────────────────────────────────────────────────
$marks_stmt = $db->prepare("
    SELECT
        m.marks_obtained,
        sub.total_marks,
        COALESCE(sub.practical_marks,0) AS pr_max
    FROM marks m
    JOIN subjects sub ON m.subject_id = sub.id
    WHERE m.student_id = :student_id
");

$marks_stmt->execute([
    ':student_id' => $student_id
]);

$marks = $marks_stmt->fetchAll(PDO::FETCH_ASSOC);

$total_max = 0;
$total_obtained = 0;

foreach ($marks as $mark) {

    $th_max = (int)$mark['total_marks'];
    $pr_max = (int)$mark['pr_max'];

    $total_max += ($th_max + $pr_max);
    $total_obtained += (int)$mark['marks_obtained'];
}

$percentage = ($total_max > 0)
    ? round(($total_obtained / $total_max) * 100, 2)
    : 0;

if ($percentage >= 75)     { $grade = 'A'; $grade_text = 'First Division with Distinction'; }
elseif ($percentage >= 60) { $grade = 'B'; $grade_text = 'First Division'; }
elseif ($percentage >= 50) { $grade = 'C'; $grade_text = 'Second Division'; }
else                       { $grade = 'Fail'; $grade_text = 'Fail'; }

if ($grade === 'Fail') {
    setFlashMessage('error', 'Certificate cannot be generated for failed students.');
    header('Location: view_student.php?id=' . $student_id);
    exit;
}

// ── Certificate record ────────────────────────────────────────────────────────
$cert_stmt = $db->prepare("SELECT * FROM certificates WHERE student_id = :student_id");
$cert_stmt->execute([':student_id' => $student_id]);
$certificate = $cert_stmt->fetch(PDO::FETCH_ASSOC);

if (!$certificate) {
    $cert_id = 'RISE-' . date('Y') . '-' . str_pad($student_id, 6, '0', STR_PAD_LEFT);
    $chk = $db->prepare("SELECT COUNT(*) FROM certificates WHERE certificate_id = :c");
    $chk->execute([':c' => $cert_id]);
    if ($chk->fetchColumn() > 0) $cert_id .= '-' . rand(100, 999);

    $ins = $db->prepare("INSERT INTO certificates (student_id, certificate_id, issue_date, created_at) VALUES (:s,:c,:d,NOW())");
    $ins->execute([':s' => $student_id, ':c' => $cert_id, ':d' => date('Y-m-d')]);
    $certificate = ['certificate_id' => $cert_id, 'issue_date' => date('Y-m-d')];
}

// ── Asset paths ───────────────────────────────────────────────────────────────
$student_photo_path   = '';
if (!empty($student['photo'])) {
    $p = __DIR__ . '/uploads/photos/' . $student['photo'];
    if (file_exists($p)) $student_photo_path = $p;
}

$logo_path            = __DIR__ . '/assets/images/logo.jpg';
$has_logo             = file_exists($logo_path);
$logos_path           = __DIR__ . '/assets/images/accreditation_logos.jpg';
$has_logos            = file_exists($logos_path);
$controller_sign_path = __DIR__ . '/assets/images/controller_signature.jpg';
$has_ctrl_sign        = file_exists($controller_sign_path);
$director_sign_path   = __DIR__ . '/assets/images/director_signature.jpg';
$has_dir_sign         = file_exists($director_sign_path);
$fallback_sign_path   = __DIR__ . '/assets/images/authorized_signature.jpg';
$has_fallback_sign    = file_exists($fallback_sign_path);
$seal_path            = __DIR__ . '/assets/images/seal.jpg';
$has_seal             = file_exists($seal_path);
if (!$has_seal) {
    $seal_path  = __DIR__ . '/assets/images/seal.jpg';
    $has_seal   = file_exists($seal_path);
}

$verify_base_url = 'https://one.reliableinclusiveskilledu.in/verify_certificate.php';
$qr_data = $verify_base_url
         . '?cert_id='    . urlencode($certificate['certificate_id'])
         . '&enrollment=' . urlencode($student['enrollment_no']);


// ===================== CHECK EXISTING CERTIFICATE =====================
if (!empty($student['certificate_pdf'])) {

    $existing_path = __DIR__ . '/uploads/certificates/' . $student['certificate_pdf'];

    if (file_exists($existing_path)) {
        // Show existing certificate instead of regenerating
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . basename($existing_path) . '"');
        readfile($existing_path);
        exit;
    }
}

// ── Load TCPDF ────────────────────────────────────────────────────────────────
$tcpdf_candidates = [
    __DIR__ . '/lib/TCPDF/tcpdf.php',
    __DIR__ . '/lib/tcpdf/tcpdf.php',
    __DIR__ . '/vendor/tecnickcom/tcpdf/tcpdf.php',
    __DIR__ . '/tcpdf/tcpdf.php',
    'tcpdf/tcpdf.php',
];
foreach ($tcpdf_candidates as $p) {
    if (file_exists($p)) { require_once $p; break; }
}
if (!class_exists('TCPDF') && file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}
if (!class_exists('TCPDF')) { die('TCPDF library not found. Please install it.'); }

// ── PDF class ─────────────────────────────────────────────────────────────────
if (!class_exists('RISE_Certificate')) {
    class RISE_Certificate extends TCPDF {
        public function Header() {}
        public function Footer() {}
    }
}

// ── Initialise PDF ────────────────────────────────────────────────────────────
$pdf = new RISE_Certificate('L', 'mm', 'A4', true, 'UTF-8', false);
$pdf->SetCreator('RISE SaaS');
$pdf->SetAuthor('RISE');
$pdf->SetTitle('Certificate – ' . $student['full_name']);
$pdf->SetMargins(0, 0, 0);
$pdf->SetAutoPageBreak(false, 0);
$pdf->SetPrintHeader(false);
$pdf->SetPrintFooter(false);
$pdf->AddPage();

// ── Page constants ────────────────────────────────────────────────────────────
$W = 297; $H = 210;

// ── Colour palette (R, G, B) ──────────────────────────────────────────────────
$navy   = [13,  54,  100];
$gold   = [184, 150,  46];
$gold2  = [212, 175,  55];
$white  = [255, 255, 255];
$cream  = [250, 247, 240];
$lBlue  = [235, 243, 253];
$lBlue2 = [240, 246, 255];
$grey1  = [85,  95,  110];
$grey2  = [65,  78,   95];
$grey3  = [100, 112, 128];
$green  = [26,  122,  69];
$dkGrey = [35,  42,   55];

// ── 1. Cream / warm-white background ─────────────────────────────────────────
$pdf->SetFillColor(...$cream);
$pdf->Rect(0, 0, $W, $H, 'F');

// ── 2. Outer gold border ──────────────────────────────────────────────────────
$pdf->SetDrawColor(...$gold);
$pdf->SetLineWidth(1.0);
$pdf->Rect(4, 4, $W - 8, $H - 8, 'D');

// ── 3. Inner navy border ──────────────────────────────────────────────────────
$pdf->SetDrawColor(...$navy);
$pdf->SetLineWidth(2.0);
$pdf->Rect(6.5, 6.5, $W - 13, $H - 13, 'D');

// ── 4. Navy header band (FULL WIDTH inside border) ────────────────────────────
$hdr_h = 48;  // Increased header height
$pdf->SetFillColor(...$navy);
$pdf->Rect(6.5, 6.5, $W - 13, $hdr_h, 'F');

// -- Gold decorative line at bottom of header ---------------------------------
$pdf->SetDrawColor(...$gold);
$pdf->SetLineWidth(0.7);
$pdf->Line(6.5, 6.5 + $hdr_h, $W - 6.5, 6.5 + $hdr_h);

// ── 5. Certificate No (TOP LEFT CORNER) ──────────────────────────────────────
$pdf->SetFont('helvetica', 'B', 7.5);
$pdf->SetTextColor(200, 220, 240);
$pdf->SetXY(12, 9);
$pdf->SetFont('helvetica', 'B', 12);

$pdf->Cell(
    80,
    5,
    'Certificate No: ' . htmlspecialchars($certificate['certificate_id']),
    0,
    0,
    'L'
);

// ── 6. Enrollment No (TOP RIGHT CORNER) ──────────────────────────────────────
$pdf->SetFont('helvetica', 'B', 7.5);
$pdf->SetTextColor(200, 220, 240);
$pdf->SetXY($W - 92, 9);
$pdf->SetFont('helvetica', 'B', 12);

$pdf->Cell(
    80,
    5,
    'Enrollment No: ' . htmlspecialchars($student['enrollment_no']),
    0,
    0,
    'R'
);

// ── 7. Logo (centred in header) ───────────────────────────────────────────────
$logo_w = 24; $logo_y = 14; $logo_x = ($W - $logo_w) / 2;
$logo_h = 0;
if ($has_logo) {
    list($orig_w, $orig_h) = getimagesize($logo_path);
    $logo_h = ($orig_h / $orig_w) * $logo_w;
    $pdf->Image($logo_path, $logo_x, $logo_y, $logo_w, 0, '', '', '', true, 300);
}
$text_after_logo_y = $logo_y + max($logo_h, 12);

// ── College heading ───────────────────────────────────────────────────────────
$pdf->SetFont('helvetica', 'B', 24);
$pdf->SetTextColor(220, 235, 255);

$pdf->SetXY(6.5, $text_after_logo_y);
$pdf->Cell($W - 13, 14, 'RELIABLE INCLUSIVE SKILL EDUCATION', 0, 1, 'C');

// -- Gold separator line (after college name) ---------------------------------
$line_y = $text_after_logo_y + 13;
$pdf->SetDrawColor(...$gold2);
$pdf->SetLineWidth(0.5);


// -- Sub-line (below college heading, above Certificate title) ----------------
$pdf->SetFont('helvetica', '', 6.5);
$pdf->SetTextColor(180, 210, 245);
$pdf->SetXY(6.5, $line_y + 1);
$pdf->Cell(
    $W - 13,
    4,
    '(An Autonomous Institution Registered Under the Companies Act, 2013 / Sec 18, Incorporated Under Ministry of Corporate Affairs, Government of India)',
    0,
    1,
    'C'
);

// -- Certificate title (below sub-line) ---------------------------------------
$pdf->SetFont('helvetica', 'B', 16);
$pdf->SetTextColor(212, 175, 55);
$pdf->SetXY(6.5, $line_y + 6);
$pdf->Cell($W - 13, 8, 'C E R T I F I C A T E   O F   C O M P L E T I O N', 0, 1, 'C');

// ── 8. Body area background ──────────────────────────────────────────────────
$body_top = 6.5 + $hdr_h + 0.7;
$pdf->SetFillColor(...$cream);
$pdf->Rect(6.5, $body_top, $W - 13, $H - $body_top - 6.5, 'F');

// -- Watermark (centred RISE text) --------------------------------------------
$pdf->SetFont('helvetica', 'B', 70);
$pdf->SetTextColor(13, 54, 100);
$pdf->SetAlpha(0.04);
$pdf->SetXY(0, ($H / 2) - 15);
$pdf->Cell($W, 44, 'RISE', 0, 0, 'C');
$pdf->SetAlpha(1);

// ── 9. "This is to certify that" ─────────────────────────────────────────────
$bt = $body_top + 4;

$pdf->SetFont('times', 'I', 11);
$pdf->SetTextColor(...$grey1);
$pdf->SetXY(0, $bt);
$pdf->Cell($W, 6, 'This is to certify that', 0, 1, 'C');

// ── 10. Student name ──────────────────────────────────────────────────────────
$pdf->SetFont('times', 'B', 32);
$pdf->SetTextColor(...$navy);
$pdf->SetXY(0, $bt + 6);
$pdf->Cell($W, 14, htmlspecialchars($student['full_name']), 0, 1, 'C');

// -- Underline beneath name ---------------------------------------------------
$nw  = min($pdf->GetStringWidth($student['full_name']), 175);
$nux = ($W - $nw) / 2;
$nuy = $bt + 20.5;
$pdf->SetDrawColor(...$navy);
$pdf->SetLineWidth(0.8);
$pdf->Line($nux, $nuy, $nux + $nw, $nuy);

// ── 11. "has successfully completed …" ───────────────────────────────────────
$pdf->SetFont('times', '', 11.5);
$pdf->SetTextColor(...$dkGrey);
$pdf->SetXY(0, $bt + 23);
$pdf->Cell($W, 6, 'has successfully completed all requirements of the Course', 0, 1, 'C');

// ── 12. Program pill (gradient look via layered rects) ───────────────────────
$pb_w = 172; $pb_x = ($W - $pb_w) / 2; $pb_y = $bt + 30; $pb_h = 13;

// Outer rounded fill (dark navy)
$pdf->SetFillColor(...$navy);
$pdf->SetDrawColor(...$gold2);
$pdf->SetLineWidth(0.5);
$pdf->RoundedRect($pb_x, $pb_y, $pb_w, $pb_h, 3, '1111', 'DF');

// Left accent bar (gold)
$pdf->SetFillColor(...$gold);
$pdf->RoundedRect($pb_x, $pb_y, 4, $pb_h, 3, '1000', 'F');

// Program text
$pdf->SetFont('times', 'B', 15);
$pdf->SetTextColor(...$white);
$pdf->SetXY($pb_x + 4, $pb_y + 2.5);

$pdf->Cell(
    $pb_w - 4,
    8,
    htmlspecialchars($student['course_name'] ?? 'N/A'),
    0,
    0,
    'C'
);

// ── 13. Session / Batch row ──────────────────────────────────────────────────
$pdf->SetFont('helvetica', '', 8);
$pdf->SetTextColor(...$grey2);
$pdf->SetXY(0, $pb_y + $pb_h + 2.5);
$session_val = htmlspecialchars($student['session_name'] ?? 'N/A');
$batch_val   = htmlspecialchars($student['batch'] ?? 'N/A');
$course_val  = htmlspecialchars($student['course_name'] ?? '');
$pdf->Cell($W, 5, "Course: {$course_val}   |   Session: {$session_val}   |   Batch: {$batch_val}", 0, 1, 'C');

// ── 14. Grade pill ───────────────────────────────────────────────────────────
$pl_w = 125; $pl_x = ($W - $pl_w) / 2; $pl_y = $pb_y + $pb_h + 8.5;

// Score pill
$pdf->SetFillColor(...$green);
$pdf->RoundedRect($pl_x, $pl_y, 36, 8.5, 4.25, '1111', 'F');
$pdf->SetFont('helvetica', 'B', 7.5);
$pdf->SetTextColor(...$white);
$pdf->SetXY($pl_x, $pl_y + 1.5);
$pdf->Cell(36, 5.5, 'Score: ' . $percentage . '%', 0, 0, 'C');

// Grade pill
$pdf->SetFillColor(...$navy);
$pdf->RoundedRect($pl_x + 38, $pl_y, 24, 8.5, 4.25, '1111', 'F');
$pdf->SetXY($pl_x + 38, $pl_y + 1.5);
$pdf->Cell(24, 5.5, 'Grade: ' . $grade, 0, 0, 'C');

// Grade text pill
$pdf->RoundedRect($pl_x + 64, $pl_y, 60, 8.5, 4.25, '1111', 'F');
$pdf->SetXY($pl_x + 64, $pl_y + 1.5);
$pdf->Cell(60, 5.5, $grade_text, 0, 0, 'C');

// ── 15. Info grid (left side) ────────────────────────────────────────────────
$m       = 14;
$grid_y  = $pl_y + 12;
$col1_x  = $m + 2;
$lbl_w   = 36;
$val_w   = 60;
$row_h   = 8;

$fields = [
    ["Father's Name", htmlspecialchars($student['father_name'] ?? 'N/A')],
    ['Date of Birth',  !empty($student['dob']) ? date('d M Y', strtotime($student['dob'])) : 'N/A'],
    ['Duration',       ($student['duration'] ?? 'N/A') . ''],
    ['Center Name', htmlspecialchars($college_name)],
];

// Grid outer border
$pdf->SetDrawColor(180, 200, 225);
$pdf->SetLineWidth(0.3);
$pdf->Rect($col1_x, $grid_y, $lbl_w + $val_w + 2, count($fields) * $row_h - 1, 'D');


foreach ($fields as $i => [$label, $value]) {
    $row_y = $grid_y + $i * $row_h;

    // Alternating row background
    if ($i % 2 === 0) {
        $pdf->SetFillColor(...$lBlue2);
    } else {
        $pdf->SetFillColor(248, 251, 255);
    }
    $pdf->Rect($col1_x, $row_y, $lbl_w + $val_w + 2, $row_h - 1, 'F');

    // Label (UPDATED SIZE)
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->SetTextColor(...$navy);
    $pdf->SetXY($col1_x + 1.5, $row_y + 1.8);
    $pdf->Cell($lbl_w - 1, 5, $label . ':', 0, 0, 'L');

    // Value (UPDATED SIZE)
    $pdf->SetFont('helvetica', '', 12);
    $pdf->SetTextColor(...$dkGrey);
    $pdf->SetXY($col1_x + $lbl_w + 2, $row_y + 1.8);
    $pdf->Cell($val_w - 2, 5, $value, 0, 0, 'L');

    // Row divider
    if ($i < count($fields) - 1) {
        $pdf->SetDrawColor(195, 215, 235);
        $pdf->SetLineWidth(0.2);
        $pdf->Line($col1_x, $row_y + $row_h - 1, $col1_x + $lbl_w + $val_w + 2, $row_y + $row_h - 1);
    }
}

// ── 16. Student photo (top-right of body) ────────────────────────────────────
$ph_w = 28; $ph_h = 35;
$ph_x = $W - $m - $ph_w - 5;
$ph_y = $body_top + 1;

// Navy frame
$pdf->SetFillColor(...$navy);
$pdf->Rect($ph_x - 2, $ph_y - 2, $ph_w + 4, $ph_h + 4, 'F');

if ($student_photo_path) {
    $pdf->Image($student_photo_path, $ph_x, $ph_y, $ph_w, $ph_h, '', '', '', true, 150);
} else {
    $pdf->SetFillColor(215, 230, 248);
    $pdf->Rect($ph_x, $ph_y, $ph_w, $ph_h, 'F');
    $pdf->SetFont('helvetica', '', 6);
    $pdf->SetTextColor(60, 90, 130);
    $pdf->SetXY($ph_x, $ph_y + ($ph_h / 2) - 4);
    $pdf->Cell($ph_w, 6, 'PHOTO', 0, 0, 'C');
}

// "Certified Candidate" caption
$pdf->SetFont('helvetica', '', 5.5);
$pdf->SetTextColor(80, 92, 110);
$pdf->SetXY($ph_x - 2, $ph_y + $ph_h + 3);
$pdf->Cell($ph_w + 4, 4, 'Certified Candidate', 0, 0, 'C');

// ── 17. Footer / signature section ──────────────────────────────────────────
$sep_y   = $H - 38;
$sig_y   = $H - 16;
$sig_lbl = $sig_y + 2;
$sl      = 54;

// Light separator line
$pdf->SetDrawColor(185, 205, 228);
$pdf->SetLineWidth(0.35);
$pdf->Line($m, $sep_y, $W - $m, $sep_y);

// Footer background
$pdf->SetFillColor(245, 249, 255);
$pdf->Rect($m, $sep_y, $W - ($m * 2), $H - $sep_y - 6.5, 'F');

// ── Controller of Examinations (left) ────────────────────────────────────────
$s1x = $m + 6;
if ($has_ctrl_sign) {
    $pdf->Image($controller_sign_path,
        $s1x + ($sl / 2) - 20,
        $sig_y - 17, 40, 14, '',
        '', '', true, 150, '', false, false, 0, 'CM');
}
$pdf->SetDrawColor(...$navy);
$pdf->SetLineWidth(0.5);
$pdf->Line($s1x, $sig_y, $s1x + $sl, $sig_y);
$pdf->SetFont('helvetica', 'B', 7);
$pdf->SetTextColor(...$navy);
$pdf->SetXY($s1x, $sig_lbl);
$pdf->Cell($sl, 4, 'Controller of Examinations', 0, 0, 'C');

// ── Centre column: Date + Seal ──────────────────────────────────────────────
$cx = $W / 2;

// Date
$pdf->SetFont('helvetica', '', 7);
$pdf->SetTextColor(75, 90, 110);
$pdf->SetXY(0, $sep_y + 3);
$pdf->Cell($W, 4.5, 'DATE: ' . date('d F Y', strtotime($certificate['issue_date'])), 0, 1, 'C');

// Seal
$seal_size = 22;
$sc_x      = $cx - ($seal_size / 2);
$sc_y      = $sep_y + 9;

if ($has_seal) {
    $seal_ext = strtolower(pathinfo($seal_path, PATHINFO_EXTENSION));
    $pdf->Image($seal_path, $sc_x, $sc_y, $seal_size, $seal_size,
        strtoupper($seal_ext), '', '', true, 150);
} else {
    // Fallback: draw a double-circle seal
    $pdf->SetDrawColor(...$navy);
    $pdf->SetLineWidth(0.6);
    $pdf->Circle($cx, $sc_y + $seal_size / 2, $seal_size / 2, 0, 360, 'D');
    $pdf->SetLineWidth(0.3);
    $pdf->Circle($cx, $sc_y + $seal_size / 2, ($seal_size / 2) - 2, 0, 360, 'D');
    $pdf->SetFont('helvetica', 'B', 5);
    $pdf->SetTextColor(...$navy);
    $pdf->SetXY($cx - 12, $sc_y + ($seal_size / 2) - 3);
    $pdf->Cell(24, 4, 'OFFICIAL SEAL', 0, 0, 'C');
}

// ── Director (right) ────────────────────────────────────────────────────────
$s2x = $W - $m - 6 - $sl;
$use_sign = $has_dir_sign ? $director_sign_path : ($has_fallback_sign ? $fallback_sign_path : null);
if ($use_sign) {
    $pdf->Image($use_sign,
        $s2x + ($sl / 2) - 20,
        $sig_y - 17, 40, 14, 'JPG',
        '', '', true, 150, '', false, false, 0, 'CM');
}
$pdf->SetDrawColor(...$navy);
$pdf->SetLineWidth(0.5);
$pdf->Line($s2x, $sig_y, $s2x + $sl, $sig_y);
$pdf->SetFont('helvetica', 'B', 7);
$pdf->SetTextColor(...$navy);
$pdf->SetXY($s2x, $sig_lbl);
$pdf->Cell($sl, 4, 'Director', 0, 0, 'C');

// ── 18. QR Code ─────────────────────────────────────────────────────────────
$qr_s = 16;
$qr_x = $W - $m - $qr_s - 1;
$qr_y = $sep_y + 3;

$pdf->SetFillColor(...$white);
$pdf->SetDrawColor(...$navy);
$pdf->SetLineWidth(0.4);
$pdf->Rect($qr_x - 1.5, $qr_y - 1.5, $qr_s + 3, $qr_s + 3, 'DF');

$pdf->write2DBarcode(
    $qr_data,
    'QRCODE,H',
    $qr_x, $qr_y,
    $qr_s, $qr_s,
    [
        'border'   => false,
        'vpadding' => 0,
        'hpadding' => 0,
        'fgcolor'  => $navy,
        'bgcolor'  => $white,
    ],
    'N'
);

$pdf->SetFont('helvetica', '', 5.5);
$pdf->SetTextColor(75, 90, 110);
$pdf->SetXY($qr_x - 1.5, $qr_y + $qr_s + 2.5);
$pdf->Cell($qr_s + 3, 3.5, 'Scan to Verify', 0, 1, 'C');
$pdf->SetX($qr_x - 1.5);

// ── 19. Accreditation logos / text ──────────────────────────────────────────
if ($has_logos) {
    $lg_w = 80; $lg_h = 8;
    $pdf->Image($logos_path, ($W - $lg_w) / 2, $H - 10, $lg_w, $lg_h, '', '', '', true, 300);
} else {
    $pdf->SetFont('helvetica', '', 5);
    $pdf->SetTextColor(140, 155, 175);
    $pdf->SetXY(0, $H - 11);
    $pdf->Cell($W, 4, 'ISO  ·  NSDC  ·  Skill India  ·  MSME  ·  NSC  ·  IAF', 0, 0, 'C');
}

// ── 20. Save PDF to disk ─────────────────────────────────────────────────────
$pdf_filename = 'CERT_' . $student['enrollment_no'] . '_' . time() . '.pdf';
$pdf_dir      = __DIR__ . '/uploads/certificates/';
if (!is_dir($pdf_dir)) { mkdir($pdf_dir, 0755, true); }

$pdf->Output($pdf_dir . $pdf_filename, 'F');

// ── 21. Update student record ────────────────────────────────────────────────
$upd = $db->prepare("UPDATE students SET certificate_pdf = :pdf WHERE id = :id");
$upd->execute([':pdf' => $pdf_filename, ':id' => $student_id]);

// ── 22. Stream to browser ────────────────────────────────────────────────────
$pdf->Output('RISE_Certificate_' . $student['enrollment_no'] . '.pdf', 'I');
exit;