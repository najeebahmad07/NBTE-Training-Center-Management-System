<?php

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'includes/auth.php';
requireLogin();

if (!in_array($_SESSION['user_role'], ['admin', 'super_admin'])) {
    header('Location: dashboard.php');
    exit;
}

require_once 'includes/db.php';

$db = getDB();

// ── Get admin ─────────────────────────────────────────────────────────────────
$admin_id = (int)($_GET['id'] ?? 0);

if ($_SESSION['user_role'] === 'admin') {
    $admin_id = (int)$_SESSION['user_id'];
}

if ($admin_id <= 0) {
    die('Invalid admin ID.');
}

$stmt = $db->prepare("SELECT * FROM admins WHERE id = :id AND role = 'admin'");
$stmt->execute([':id' => $admin_id]);
$admin = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$admin) die('Admin not found.');
if ($admin['status'] !== 'active') die('Admin is not active.');

// ── College / center details ──────────────────────────────────────────────────
$college_name = trim($admin['college_name'] ?? $admin['name']);
$admin_name   = trim($admin['name']);
$admin_email  = trim($admin['email']);

// ── Certificate ID ────────────────────────────────────────────────────────────
$cert = null;
try {
    $cs = $db->prepare("SELECT * FROM admin_certificates WHERE admin_id = :id");
    $cs->execute([':id' => $admin_id]);
    $cert = $cs->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) { /* table may not exist yet */ }

if (!$cert) {
    $cert_id    = 'RISE-AUTH-' . date('Y') . '-' . str_pad($admin_id, 4, '0', STR_PAD_LEFT);
    $issue_date = date('Y-m-d');

    try {
        $ins = $db->prepare("
            INSERT INTO admin_certificates (admin_id, certificate_id, issue_date, created_at)
            VALUES (:a, :c, :d, NOW())
        ");
        $ins->execute([':a' => $admin_id, ':c' => $cert_id, ':d' => $issue_date]);
    } catch (Exception $e) {
        // Table doesn't exist — just use generated values
    }

    $cert = ['certificate_id' => $cert_id, 'issue_date' => $issue_date];
}

// ══════════════════════════════════════════════════════════════════════════════
//  IMAGE HELPER
// ══════════════════════════════════════════════════════════════════════════════
if (!function_exists('rise_auth_prep_img')) {
    function rise_auth_prep_img(string $path): array
    {
        if (!$path || !file_exists($path)) return ['path' => null, 'tmp' => null];
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if ($ext === 'png') {
            $src = @imagecreatefrompng($path);
            if (!$src) return ['path' => null, 'tmp' => null];
            $w = imagesx($src); $h = imagesy($src);
            $flat = imagecreatetruecolor($w, $h);
            imagefill($flat, 0, 0, imagecolorallocate($flat, 255, 255, 255));
            imagecopy($flat, $src, 0, 0, 0, 0, $w, $h);
            imagedestroy($src);
            $tmp = tempnam(sys_get_temp_dir(), 'rise_auth_') . '.jpg';
            imagejpeg($flat, $tmp, 95);
            imagedestroy($flat);
            return ['path' => $tmp, 'tmp' => $tmp];
        }
        return ['path' => $path, 'tmp' => null];
    }
}
if (!function_exists('rise_auth_cleanup')) {
    function rise_auth_cleanup(?string $t): void
    { if ($t && file_exists($t)) @unlink($t); }
}

// ── Assets ────────────────────────────────────────────────────────────────────
$logo_raw = file_exists(__DIR__ . '/assets/images/logo.jpg')
    ? __DIR__ . '/assets/images/logo.jpg'
    : __DIR__ . '/assets/images/logo.png';
$logo_img = rise_auth_prep_img($logo_raw);

$sig_raw = file_exists(__DIR__ . '/assets/images/authorized_signature.jpg')
    ? __DIR__ . '/assets/images/authorized_signature.jpg'
    : (file_exists(__DIR__ . '/assets/images/authorized_signature.png')
        ? __DIR__ . '/assets/images/authorized_signature.png' : '');
$sig_img = $sig_raw ? rise_auth_prep_img($sig_raw) : ['path' => null, 'tmp' => null];

$seal_raw = file_exists(__DIR__ . '/assets/images/seal.jpg')
    ? __DIR__ . '/assets/images/seal.jpg'
    : '';
$seal_img = $seal_raw ? rise_auth_prep_img($seal_raw) : ['path' => null, 'tmp' => null];

$clogo_raw = '';
foreach (['college_logo.jpg', 'college_logo.png'] as $f) {
    if (file_exists(__DIR__ . '/assets/images/' . $f)) {
        $clogo_raw = __DIR__ . '/assets/images/' . $f; break;
    }
}
$clogo_img = $clogo_raw ? rise_auth_prep_img($clogo_raw) : ['path' => null, 'tmp' => null];

$verify_url = 'https://' . ($_SERVER['HTTP_HOST'] ?? 'rise.example.com')
    . '/verify_admin_cert.php?cert_id=' . urlencode($cert['certificate_id']);

// ── TCPDF ─────────────────────────────────────────────────────────────────────
if (!class_exists('TCPDF')) {
    $paths = [
        __DIR__ . '/lib/TCPDF/tcpdf.php',
        __DIR__ . '/lib/tcpdf/tcpdf.php',
        __DIR__ . '/vendor/tecnickcom/tcpdf/tcpdf.php',
        __DIR__ . '/tcpdf/tcpdf.php'
    ];
    foreach ($paths as $p) {
        if (file_exists($p)) { require_once $p; break; }
    }
    if (!class_exists('TCPDF') && file_exists(__DIR__ . '/vendor/autoload.php')) {
        require_once __DIR__ . '/vendor/autoload.php';
    }
}

if (!class_exists('TCPDF')) die('TCPDF not found.');

if (!class_exists('RISE_AuthCert_PDF')) {
    class RISE_AuthCert_PDF extends TCPDF {
        public function Header() {}
        public function Footer() {}
    }
}

// ── Register Hindi Font ───────────────────────────────────────────────────────
$ttf_path = __DIR__ . '/lib/TCPDF/fonts/NotoSansDevanagari-Regular.ttf';
if (file_exists($ttf_path)) {
    $hindi_font = TCPDF_FONTS::addTTFfont($ttf_path, 'TrueTypeUnicode', '', 96);
} else {
    $hindi_font = 'dejavusans';
}

// ── A4 LANDSCAPE ──────────────────────────────────────────────────────────────
$pdf = new RISE_AuthCert_PDF('L', 'mm', 'A4', true, 'UTF-8', false);
$pdf->SetCreator('RISE SaaS');
$pdf->SetAuthor('RISE — Reliable Inclusive Skill Education');
$pdf->SetTitle('Authorization Certificate — ' . $college_name);
$pdf->SetMargins(0, 0, 0);
$pdf->SetAutoPageBreak(false, 0);
$pdf->SetPrintHeader(false);
$pdf->SetPrintFooter(false);
$pdf->AddPage();

$W = 297; $H = 210;

$navy  = [13,  54,  100];
$gold  = [180, 140,  50];
$white = [255, 255, 255];

// ══════════════════════════════════════════════════════════════════════════════
// 1. WHITE BACKGROUND
// ══════════════════════════════════════════════════════════════════════════════
$pdf->SetFillColor(255, 255, 255);
$pdf->Rect(0, 0, $W, $H, 'F');

// ══════════════════════════════════════════════════════════════════════════════
// 2. BORDERS
// ══════════════════════════════════════════════════════════════════════════════
$pdf->SetDrawColor(...$navy);
$pdf->SetLineWidth(3.5);
$pdf->Rect(5, 5, $W - 10, $H - 10, 'D');

$pdf->SetDrawColor(...$gold);
$pdf->SetLineWidth(1.0);
$pdf->Rect(9, 9, $W - 18, $H - 18, 'D');

$pdf->SetDrawColor(...$navy);
$pdf->SetLineWidth(0.4);
$pdf->Rect(12, 12, $W - 24, $H - 24, 'D');

// Gold corner ornaments
$pdf->SetFillColor(...$gold);
foreach ([[9,9], [$W-9,9], [9,$H-9], [$W-9,$H-9]] as [$cx,$cy]) {
    $r = 3;
    $pdf->Polygon([$cx,$cy-$r, $cx+$r,$cy, $cx,$cy+$r, $cx-$r,$cy], 'F');
}

// ══════════════════════════════════════════════════════════════════════════════
// 3. TOP NAVY HEADER BAND
// ══════════════════════════════════════════════════════════════════════════════
$hdr_h = 55; // tight fit — no empty gap below admin name

$pdf->SetFillColor(...$navy);
$pdf->Rect(5, 5, $W - 10, $hdr_h, 'F');

// Accent stripe at very top
$pdf->SetFillColor(25, 80, 150);
$pdf->Rect(5, 5, $W - 10, 3, 'F');

// Gold line at bottom of header
$pdf->SetDrawColor(...$gold);
$pdf->SetLineWidth(1.0);
$pdf->Line(5, 5 + $hdr_h - 1, $W - 5, 5 + $hdr_h - 1);

// ── RISE Logo — centred in header ────────────────────────────────────────────
$logo_w = 36;
$logo_h = 14;
$logo_x = ($W - $logo_w) / 2;
$logo_y = 5 + 4;

if ($logo_img['path']) {
    $pdf->Image($logo_img['path'], $logo_x, $logo_y, $logo_w, $logo_h, '', '', '', true, 300);
    rise_auth_cleanup($logo_img['tmp']);
} else {
    $pdf->SetFillColor(...$white);
    $pdf->Circle($W/2, $logo_y + $logo_h/2, $logo_h/2, 0, 360, 'F');
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->SetTextColor(...$navy);
    $pdf->SetXY($logo_x, $logo_y + $logo_h/2 - 4);
    $pdf->Cell($logo_w, 8, 'RISE', 0, 0, 'C');
}

// ── CERT NO — top-left, very bold ────────────────────────────────────────────
$pdf->SetFont('helvetica', 'B', 9);
$pdf->SetTextColor(...$gold);
$pdf->SetXY(15, $logo_y + 1);
$pdf->Cell(80, 4.5, 'Cert No:', 0, 1, 'L');

$pdf->SetFont('helvetica', 'B', 10);
$pdf->SetTextColor(...$white);
$pdf->SetXY(15, $logo_y + 5.5);
$pdf->Cell(80, 5.5, htmlspecialchars($cert['certificate_id']), 0, 0, 'L');

// ── ISSUE DATE — top-right, bold ─────────────────────────────────────────────
$pdf->SetFont('helvetica', 'B', 9);
$pdf->SetTextColor(...$gold);
$pdf->SetXY($W - 95, $logo_y + 1);
$pdf->Cell(80, 4.5, 'Issue Date:', 0, 1, 'R');

$pdf->SetFont('helvetica', 'B', 10);
$pdf->SetTextColor(...$white);
$pdf->SetXY($W - 95, $logo_y + 5.5);
$pdf->Cell(80, 5.5, date('d F Y', strtotime($cert['issue_date'])), 0, 0, 'R');

// ── "Reliable Inclusive Skill Education" — full-width, large, bold ────────────
$pdf->SetFont('helvetica', 'B', 16);
$pdf->SetTextColor(255, 255, 255);
$pdf->SetXY(5, $logo_y + $logo_h + 2);
$pdf->Cell($W - 10, 7, 'Reliable Inclusive Skill Education', 0, 1, 'C');

// ── Tagline — bold, full-width ────────────────────────────────────────────────
$pdf->SetFont('helvetica', 'B', 7.5);
$pdf->SetTextColor(200, 222, 248);
$pdf->SetXY(5, $pdf->GetY());
$pdf->MultiCell($W - 10, 4,
    '(An Autonomous Institution Registered Under the Companies Act, 2013 / Sec 18, Incorporated Under Ministry of Corporate Affairs, Government of India)',
    0, 'C'
);

// ── College logo (if any) ─────────────────────────────────────────────────────
$cur_y = $pdf->GetY() + 1;

if ($clogo_img['path']) {
    $clogo_w = 16;
    $clogo_h = 16;
    $clogo_x = ($W - $clogo_w) / 2;
    $pdf->Image($clogo_img['path'], $clogo_x, $cur_y, $clogo_w, $clogo_h, '', '', '', true, 200);
    rise_auth_cleanup($clogo_img['tmp']);
    $cur_y += $clogo_h + 1;
}

// ── Admin name — BIG & BOLD ───────────────────────────────────────────────────
$pdf->SetFont('helvetica', 'B', 15);
$pdf->SetTextColor(...$white);
$pdf->SetXY(5, $cur_y);
$pdf->Cell($W - 10, 6, htmlspecialchars($admin_name), 0, 1, 'C');

// ══════════════════════════════════════════════════════════════════════════════
// 4. BODY
// ══════════════════════════════════════════════════════════════════════════════
$body_y = 5 + $hdr_h + 10;

// "AUTHORIZATION CERTIFICATE"
$pdf->SetFont('helvetica', 'B', 14);
$pdf->SetTextColor(...$navy);
$pdf->SetXY(0, $body_y);
$pdf->Cell($W, 8, 'A U T H O R I Z A T I O N   C E R T I F I C A T E', 0, 1, 'C');

// Gold underline
$pdf->SetDrawColor(...$gold);
$pdf->SetLineWidth(0.8);
$pdf->Line(60, $body_y + 9, $W - 60, $body_y + 9);

// "This is to certify that"
$pdf->SetFont('times', 'I', 13);
$pdf->SetTextColor(70, 80, 100);
$pdf->SetXY(0, $body_y + 13);
$pdf->Cell($W, 7, 'This is to certify that', 0, 1, 'C');

// ── COLLEGE NAME ──────────────────────────────────────────────────────────────
$cn_y = $body_y + 22;
$cn_w = $W - 60;
$cn_x = 30;

$pdf->SetDrawColor(...$gold);
$pdf->SetLineWidth(1.5);
$pdf->RoundedRect($cn_x - 2, $cn_y - 2, $cn_w + 4, 22, 4, '1111', 'D');

$pdf->SetFillColor(...$navy);
$pdf->RoundedRect($cn_x, $cn_y, $cn_w, 18, 3, '1111', 'F');

$cn_fontsize = strlen($college_name) > 40 ? 16 : (strlen($college_name) > 28 ? 19 : 22);
$pdf->SetFont('times', 'B', $cn_fontsize);
$pdf->SetTextColor(...$white);
$pdf->SetXY($cn_x, $cn_y + 1);
$pdf->Cell($cn_w, 16, htmlspecialchars($college_name), 0, 0, 'C');

// "is an Officially Authorized Training Center of"
$pdf->SetFont('times', '', 12);
$pdf->SetTextColor(50, 60, 80);
$pdf->SetXY(0, $cn_y + 22);
$pdf->Cell($W, 7, 'is an Officially Authorized Training Center of', 0, 1, 'C');

// RISE highlighted
$pdf->SetFont('times', 'B', 15);
$pdf->SetTextColor(...$navy);
$pdf->SetX(0);
$pdf->Cell($W, 7, 'RISE — Reliable Inclusive Skill Education', 0, 1, 'C');

// ══════════════════════════════════════════════════════════════════════════════
// 5. SIGNATURE SECTION
// ══════════════════════════════════════════════════════════════════════════════
$sig_base_y = $H - 42;

$pdf->SetDrawColor(185, 205, 228);
$pdf->SetLineWidth(0.3);
$pdf->Line(15, $sig_base_y, $W - 15, $sig_base_y);

$pdf->SetFillColor(247, 250, 255);
$pdf->Rect(14, $sig_base_y, $W - 28, 28, 'F');

$sig_line_y = $sig_base_y + 20;
$sl         = 65;

// ── Left: QR Code ─────────────────────────────────────────────────────────────
$qr_s  = 24;
$qr_x  = 22;
$qr_y2 = $sig_base_y + 2;

$pdf->SetFillColor(255, 255, 255);
$pdf->SetDrawColor(...$navy);
$pdf->SetLineWidth(0.4);
$pdf->Rect($qr_x - 1.5, $qr_y2 - 1.5, $qr_s + 3, $qr_s + 3, 'DF');

$pdf->write2DBarcode(
    $verify_url, 'QRCODE,H',
    $qr_x, $qr_y2, $qr_s, $qr_s,
    ['border' => false, 'vpadding' => 0, 'hpadding' => 0,
     'fgcolor' => $navy, 'bgcolor' => $white],
    'N'
);
$pdf->SetFont('helvetica', 'B', 6);
$pdf->SetTextColor(...$navy);
$pdf->SetXY($qr_x - 1.5, $qr_y2 + $qr_s + 1.5);
$pdf->Cell($qr_s + 3, 3.5, 'Scan to Verify', 0, 0, 'C');

// ── Centre: SEAL ──────────────────────────────────────────────────────────────
$seal_cx = $W / 2;
$seal_cy = $sig_base_y + 16;

if ($seal_img['path']) {
    $seal_s = 24;
    $pdf->Image($seal_img['path'],
        $seal_cx - $seal_s / 2, $seal_cy - $seal_s / 2,
        $seal_s, $seal_s, '', '', '', true, 200);
    rise_auth_cleanup($seal_img['tmp']);
}

// ── Right: Director signature ─────────────────────────────────────────────────
$s2x = $W - 22 - $sl;

if ($sig_img['path']) {
    $pdf->Image($sig_img['path'],
        $s2x + ($sl / 2) - 18, $sig_line_y - 13,
        36, 12, '', '', '', true, 150, '', false, false, 0, 'CM');
    rise_auth_cleanup($sig_img['tmp']);
}

$pdf->SetDrawColor(...$navy);
$pdf->SetLineWidth(0.5);
$pdf->Line($s2x, $sig_line_y, $s2x + $sl, $sig_line_y);

$pdf->SetFont('helvetica', 'B', 7);
$pdf->SetTextColor(...$navy);
$pdf->SetXY($s2x, $sig_line_y + 2);
$pdf->Cell($sl, 4, 'Director', 0, 0, 'C');

$pdf->SetFont('helvetica', '', 6.5);
$pdf->SetTextColor(90, 105, 125);
$pdf->SetXY($s2x, $sig_line_y + 6);
$pdf->Cell($sl, 4, 'RISE — Reliable Inclusive Skill Education', 0, 0, 'C');

// ══════════════════════════════════════════════════════════════════════════════
// OUTPUT
// ══════════════════════════════════════════════════════════════════════════════
$safe_name = preg_replace('/[^A-Za-z0-9_]/', '_', $college_name);
$pdf->Output('RISE_Authorization_' . $safe_name . '.pdf', 'I');
exit;