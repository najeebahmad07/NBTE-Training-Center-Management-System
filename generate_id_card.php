<?php

/**
 * RISE SaaS - Generate ID Card (TCPDF)
 * File: generate_id_card.php
 */

require_once 'includes/auth.php';
requireLogin();

// Only admin and super_admin can generate
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
    SELECT s.*, p.program_name, p.duration, c.course_name, a.college_name AS center_name
    FROM students s
    JOIN programs p ON s.program_id = p.id
    JOIN courses c ON s.course_id = c.id
    JOIN admins a ON s.admin_id = a.id
    WHERE s.id = :student_id AND s.admin_id = :admin_id
");
    $stmt->execute([':student_id' => $student_id]);
} else {
    $stmt = $db->prepare("
    SELECT s.*, p.program_name, p.duration, c.course_name, a.name as center_name
    FROM students s
    JOIN programs p ON s.program_id = p.id
    JOIN courses c ON s.course_id = c.id
    JOIN admins a ON s.admin_id = a.id
    WHERE s.id = :student_id AND s.admin_id = :admin_id
");
    $stmt->execute([
        ':student_id' => $student_id,
        ':admin_id'   => $_SESSION['user_id']
    ]);
}

$student = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$student) {
    setFlashMessage('error', 'Student not found or access denied.');
    header('Location: students.php');
    exit;
}

if ($student['status'] !== 'Approved') {
    setFlashMessage('error', 'ID Card can only be generated for approved students.');
    header('Location: view_student.php?id=' . $student_id);
    exit;
}

$admin_stmt = $db->prepare("SELECT college_name FROM admins WHERE id = :id");
$admin_stmt->execute([':id' => $student['admin_id']]);
$admin_data = $admin_stmt->fetch(PDO::FETCH_ASSOC);

$student['center_name'] = $admin_data['college_name'] ?? '';

// ===================== CHECK EXISTING ID CARD =====================
if (!empty($student['id_card_pdf'])) {

    $existing_path = __DIR__ . '/uploads/id_cards/' . $student['id_card_pdf'];

    if (file_exists($existing_path)) {
        // Serve existing PDF instead of regenerating
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . basename($existing_path) . '"');
        readfile($existing_path);
        exit;
    }
}
// ===================== TCPDF LOAD =====================

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
  

 

// ===================== HELPER: Flatten PNG alpha to JPEG =====================
/**
 * Prepares any image for TCPDF by flattening PNG alpha channels onto white.
 * Returns ['path' => usable_path, 'tmp' => temp_file_or_null]
 * Always call cleanup_tmp() after using the image.
 */
function prepare_image_for_tcpdf($path) {
    if (!$path || !file_exists($path)) {
        return ['path' => null, 'tmp' => null];
    }
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    if ($ext === 'png') {
        $src = @imagecreatefrompng($path);
        if (!$src) {
            return ['path' => null, 'tmp' => null];
        }
        $w    = imagesx($src);
        $h    = imagesy($src);
        $flat = imagecreatetruecolor($w, $h);
        // White background removes transparency
        imagefill($flat, 0, 0, imagecolorallocate($flat, 255, 255, 255));
        imagecopy($flat, $src, 0, 0, 0, 0, $w, $h);
        imagedestroy($src);
        $tmp = tempnam(sys_get_temp_dir(), 'rise_') . '.jpg';
        imagejpeg($flat, $tmp, 95);
        imagedestroy($flat);
        return ['path' => $tmp, 'tmp' => $tmp];
    }
    // JPG/GIF/BMP — safe to use directly
    return ['path' => $path, 'tmp' => null];
}

function cleanup_tmp($tmp) {
    if ($tmp && file_exists($tmp)) {
        @unlink($tmp);
    }
}

// ===================== CUSTOM TCPDF CLASS =====================

class RISE_IDCard extends TCPDF {
    public function Header() {}
    public function Footer() {}
}

// Card size: 90mm wide x 68mm tall (landscape)
$pdf = new RISE_IDCard('L', 'mm', [90, 68], true, 'UTF-8', false);

$pdf->SetCreator('RISE SaaS');
$pdf->SetAuthor('RISE');
$pdf->SetTitle('ID Card - ' . htmlspecialchars($student['full_name']));
$pdf->SetMargins(0, 0, 0);
$pdf->SetAutoPageBreak(false, 0);
$pdf->SetPrintHeader(false);
$pdf->SetPrintFooter(false);

// ===================== FRONT SIDE =====================
$pdf->AddPage();

// ---------- TOP BANNER: Logo LEFT + College Name RIGHT ----------
$logo_banner_h = 20;

// Auto-detect logo
$logo_raw = file_exists(__DIR__ . '/assets/images/logo.jpg')
    ? __DIR__ . '/assets/images/logo.jpg'
    : __DIR__ . '/assets/images/logo.png';

$logo_img = prepare_image_for_tcpdf($logo_raw);

// White banner background
$pdf->SetFillColor(255, 255, 255);
$pdf->Rect(0, 0, 90, $logo_banner_h, 'F');

// Blue top border
$pdf->SetDrawColor(13, 110, 253);
$pdf->SetLineWidth(0.8);
$pdf->Line(0, 0, 90, 0);

// Logo centered
if ($logo_img['path']) {
    $pdf->Image($logo_img['path'], 32, 2, 25, 0, '', '', '', true, 300);
    cleanup_tmp($logo_img['tmp']);
}

// MAIN TITLE (BIGGER)
$pdf->SetFont('helvetica', 'B', 7.5);
$pdf->SetTextColor(13, 37, 80);
$pdf->SetXY(0, 11);
$pdf->Cell(90, 4, 'RELIABLE INCLUSIVE SKILL EDUCATION', 0, 1, 'C');

// SUB TEXT (Legal Line)
$pdf->SetFont('helvetica', '', 4.5);
$pdf->SetTextColor(100, 100, 100);
$pdf->SetXY(2, 15);
$pdf->MultiCell(86, 3,
    '(An Autonomous Institution Registered Under the Companies Act, 2013 / Sec 18, Incorporated Under Ministry of Corporate Affairs, Government of India)',
    0,
    'C'
);

// Banner bottom border
$pdf->SetDrawColor(200, 200, 200);
$pdf->SetLineWidth(0.3);
$pdf->Line(0, $logo_banner_h, 90, $logo_banner_h);

// ---------- BLUE HEADER BAR ----------
$header_y = $logo_banner_h;
$header_h = 10;

$pdf->SetFillColor(13, 110, 253);
$pdf->Rect(0, $header_y, 90, $header_h, 'F');

$pdf->SetFillColor(10, 88, 202);
$pdf->Rect(0, $header_y, 90, 1.5, 'F');

// IDENTITY CARD label — centered
$pdf->SetFont('helvetica', 'B', 7);
$pdf->SetFillColor(220, 53, 69);
$pdf->SetTextColor(255, 255, 255);
$pdf->SetXY(28, $header_y + 3);
$pdf->Cell(34, 4, 'IDENTITY CARD', 0, 0, 'C', true);

// ---------- WHITE BODY ----------
$body_y = $header_y + $header_h;
$body_h = 36;

$pdf->SetFillColor(255, 255, 255);
$pdf->Rect(0, $body_y, 90, $body_h, 'F');

// --- Student Photo ---
$photo_raw = __DIR__ . '/uploads/photos/' . ($student['photo'] ?? '');
$photo_img = prepare_image_for_tcpdf($photo_raw);
$photo_top = $body_y + 2;

if (!empty($student['photo']) && $photo_img['path']) {
    $pdf->SetDrawColor(13, 110, 253);
    $pdf->SetLineWidth(0.5);
    $pdf->Rect(4, $photo_top, 22, 26, 'D');
    $pdf->Image($photo_img['path'], 4.5, $photo_top + 0.5, 21, 25, '', '', '', true, 300);
    cleanup_tmp($photo_img['tmp']);
} else {
    $pdf->SetFillColor(235, 240, 255);
    $pdf->SetDrawColor(13, 110, 253);
    $pdf->SetLineWidth(0.5);
    $pdf->Rect(4, $photo_top, 22, 26, 'DF');
    $pdf->SetFont('helvetica', '', 5.5);
    $pdf->SetTextColor(150, 150, 150);
    $pdf->SetXY(4, $photo_top + 10);
    $pdf->Cell(22, 5, 'PHOTO', 0, 0, 'C');
}

// --- Student Name — centered in right panel ---
$pdf->SetTextColor(13, 37, 80);
$pdf->SetFont('helvetica', 'B', 8);
$pdf->SetXY(29, $body_y + 2);
$pdf->Cell(57, 5, strtoupper(htmlspecialchars($student['full_name'])), 0, 0, 'C');

// Underline below name
$pdf->SetDrawColor(13, 110, 253);
$pdf->SetLineWidth(0.3);
$pdf->Line(29, $body_y + 7, 86, $body_y + 7);

// --- Student Details ---
$details = [
    'Center'     => $student['center_name'] ?? '',
    'Enrollment' => $student['enrollment_no'],
    'Roll No'    => $student['roll_no'],
    'Program'    => $student['program_name'],
    'Course'     => $student['course_name'],
    'DOB'        => date('d-m-Y', strtotime($student['dob'])),
];

$y = $body_y + 9;
foreach ($details as $label => $value) {
    $pdf->SetFont('helvetica', 'B', 5);
    $pdf->SetTextColor(100, 100, 100);
    $pdf->SetXY(29, $y);
    $pdf->Cell(16, 3.2, $label . ':', 0, 0, 'L');
    $pdf->SetFont('helvetica', '', 5.5);
    $pdf->SetTextColor(33, 37, 41);
    $pdf->Cell(41, 3.2, htmlspecialchars($value), 0, 1, 'L');
    $y += 3.5;
}

// ---------- BOTTOM BAR ----------
$bottom_y = $body_y + $body_h;

$pdf->SetFillColor(13, 110, 253);
$pdf->Rect(0, $bottom_y, 90, 9, 'F');

$pdf->SetFont('helvetica', '', 5);
$pdf->SetTextColor(255, 255, 255);
$pdf->SetXY(3, $bottom_y + 1.5);
$pdf->Cell(40, 3.5, 'Issue Date: ' . date('d-m-Y'), 0, 0, 'L');
$pdf->SetXY(45, $bottom_y + 1.5);
$pdf->Cell(42, 3.5, 'Session: ' . htmlspecialchars($student['session_name'] ?? ''), 0, 0, 'R');

// ===================== BACK SIDE =====================
$pdf->AddPage();

// Background
$pdf->SetFillColor(248, 249, 250);
$pdf->Rect(0, 0, 90, 68, 'F');

// ---------- TOP BAR ----------
$pdf->SetFillColor(13, 110, 253);
$pdf->Rect(0, 0, 90, 9, 'F');

$pdf->SetFont('helvetica', 'B', 8);
$pdf->SetTextColor(255, 255, 255);
$pdf->SetXY(5, 2);
$pdf->Cell(80, 5, 'RISE - Student Identity Card', 0, 0, 'C');

// ---------- TERMS (left column) ----------
$pdf->SetTextColor(33, 37, 41);
$pdf->SetFont('helvetica', '', 5.2);

$terms = [
    '1. This card is the property of RISE.',
    '2. If found, please return to the nearest RISE center.',
    '3. This card is non-transferable.',
    '4. Must be carried at all times during classes.',
    '5. Report loss immediately to the administration.',
];

$y = 12;
foreach ($terms as $term) {
    $pdf->SetXY(4, $y);
    $pdf->Cell(58, 3.5, $term, 0, 1, 'L');
    $y += 4;
}

// --- Father's Name ---
$pdf->SetFont('helvetica', 'B', 5.5);
$pdf->SetTextColor(80, 80, 80);
$pdf->SetXY(4, $y + 1);
$pdf->Cell(18, 4, "Father's Name:", 0, 0, 'L');
$pdf->SetFont('helvetica', '', 5.5);
$pdf->SetTextColor(33, 37, 41);
$pdf->Cell(38, 4, htmlspecialchars($student['father_name']), 0, 1, 'L');

// --- Address ---
$pdf->SetFont('helvetica', 'B', 5.5);
$pdf->SetTextColor(80, 80, 80);
$pdf->SetXY(4, $y + 5.5);
$pdf->Cell(13, 4, 'Address:', 0, 0, 'L');
$pdf->SetFont('helvetica', '', 5.2);
$pdf->SetTextColor(33, 37, 41);
$address = htmlspecialchars(substr($student['address'] ?? '', 0, 55));
$pdf->Cell(43, 4, $address, 0, 1, 'L');

// ---------- QR CODE (right column) ----------
$verify_url = 'https://' . ($_SERVER['HTTP_HOST'] ?? 'rise.example.com')
    . '/verify_student.php?enrollment=' . urlencode($student['enrollment_no']);

// White rounded box behind QR
$pdf->SetFillColor(255, 255, 255);
$pdf->RoundedRect(66, 10, 21, 23, 1.5, '1111', 'F');

// 16x16mm QR centered in the box
$pdf->write2DBarcode(
    $verify_url,
    'QRCODE,H',
    68, 11.5, 16, 16,
    [
        'border'        => false,
        'vpadding'      => 0,
        'hpadding'      => 0,
        'fgcolor'       => [33, 37, 41],
        'bgcolor'       => [255, 255, 255],
        'module_width'  => 1,
        'module_height' => 1,
    ],
    'N'
);

// Scan label centered under QR box
$pdf->SetFont('helvetica', 'B', 4.5);
$pdf->SetTextColor(13, 110, 253);
$pdf->SetXY(65, 28.5);
$pdf->Cell(23, 3, 'Scan to Verify', 0, 0, 'C');

// ---------- AUTHORIZED SIGNATURE — CENTERED ----------
$sig_raw = file_exists(__DIR__ . '/assets/images/authorized_signature.jpg')
    ? __DIR__ . '/assets/images/authorized_signature.jpg'
    : __DIR__ . '/assets/images/authorized_signature.png';

$sig_img = prepare_image_for_tcpdf($sig_raw);

// Center signature: card width=90, sig width=22 → X=(90-22)/2=34
$sig_w = 22;
$sig_x = (90 - $sig_w) / 2; // = 34

if ($sig_img['path']) {
    $pdf->Image($sig_img['path'], $sig_x, 44, $sig_w, 8, '', '', '', true, 300);
    cleanup_tmp($sig_img['tmp']);
}

// Centered signature line
$line_w  = 30;
$line_x1 = (90 - $line_w) / 2; // = 30
$line_x2 = $line_x1 + $line_w; // = 60

$pdf->SetDrawColor(150, 150, 150);
$pdf->SetLineWidth(0.3);
$pdf->Line($line_x1, 53, $line_x2, 53);

$pdf->SetFont('helvetica', '', 5);
$pdf->SetTextColor(100, 100, 100);
$pdf->SetXY($line_x1, 53.5);
$pdf->Cell($line_w, 3, 'Authorized Signature', 0, 0, 'C');

// ---------- BOTTOM BAR ----------
$pdf->SetFillColor(13, 110, 253);
$pdf->Rect(0, 64.5, 90, 3.5, 'F');

$pdf->SetFont('helvetica', '', 4);
$pdf->SetTextColor(255, 255, 255);
$pdf->SetXY(5, 65);
$pdf->Cell(80, 2.5, 'Verify at: ' . ($_SERVER['HTTP_HOST'] ?? 'rise.example.com') . '/verify_student.php', 0, 0, 'C');

// ===================== OUTPUT =====================

$pdf_filename = 'ID_' . $student['enrollment_no'] . '_' . time() . '.pdf';
$pdf_path     = __DIR__ . '/uploads/id_cards/' . $pdf_filename;

if (!is_dir(__DIR__ . '/uploads/id_cards')) {
    mkdir(__DIR__ . '/uploads/id_cards', 0755, true);
}

$pdf->Output($pdf_path, 'F');

$update_stmt = $db->prepare("UPDATE students SET id_card_pdf = :pdf WHERE id = :id");
$update_stmt->execute([':pdf' => $pdf_filename, ':id' => $student_id]);

$pdf->Output('RISE_ID_Card_' . $student['enrollment_no'] . '.pdf', 'I');
exit;