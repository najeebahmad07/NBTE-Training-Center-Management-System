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


/* ==========================================================================
   NBTE AUTHORIZED TRAINING CENTRE CERTIFICATE
   ========================================================================== */


/* ==========================================================================
   1. GET ADMIN
   ========================================================================== */

$admin_id = (int)($_GET['id'] ?? 0);

if ($_SESSION['user_role'] === 'admin') {
    $admin_id = (int)$_SESSION['user_id'];
}

if ($admin_id <= 0) {
    die('Invalid admin ID.');
}


/* ==========================================================================
   2. GET ADMIN DETAILS
   ========================================================================== */

$stmt = $db->prepare("
    SELECT *
    FROM admins
    WHERE id = :id
      AND role = 'admin'
    LIMIT 1
");

$stmt->execute([
    ':id' => $admin_id
]);

$admin = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$admin) {
    die('Admin not found.');
}

if (($admin['status'] ?? '') !== 'active') {
    die('Admin is not active.');
}


/* ==========================================================================
   3. DYNAMIC CENTRE DETAILS
   ========================================================================== */

$college_name = trim(
    $admin['college_name']
    ?? $admin['name']
    ?? 'Authorized Training Centre'
);

$admin_name = trim(
    $admin['name']
    ?? 'Authorized Centre Head'
);

$admin_email = trim(
    $admin['email']
    ?? ''
);


/* ==========================================================================
   4. GET ASSIGNED PROGRAM
   ========================================================================== */

$programStmt = $db->prepare("
    SELECT p.program_name
    FROM admin_programs ap
    INNER JOIN programs p
        ON p.id = ap.program_id
    WHERE ap.admin_id = :id
    LIMIT 1
");

$programStmt->execute([
    ':id' => $admin_id
]);

$programData = $programStmt->fetch(PDO::FETCH_ASSOC);

$department_name = trim(
    $programData['program_name'] ?? ''
);

if ($department_name === '') {
    $department_name = 'Programme Not Assigned';
}


/* ==========================================================================
   5. CERTIFICATE
   ========================================================================== */

$cert = null;

try {

    $cs = $db->prepare("
        SELECT *
        FROM admin_certificates
        WHERE admin_id = :id
        LIMIT 1
    ");

    $cs->execute([
        ':id' => $admin_id
    ]);

    $cert = $cs->fetch(PDO::FETCH_ASSOC);

} catch (Exception $e) {

    $cert = null;
}


/* ==========================================================================
   6. CREATE CERTIFICATE IF NOT EXISTS
   ========================================================================== */

if (!$cert) {

    $cert_id =
        'NBTE-AUTH-' .
        date('Y') .
        '-' .
        str_pad(
            $admin_id,
            4,
            '0',
            STR_PAD_LEFT
        );

    $issue_date = date('Y-m-d');

    try {

        $ins = $db->prepare("
            INSERT INTO admin_certificates
            (
                admin_id,
                certificate_id,
                issue_date,
                created_at
            )
            VALUES
            (
                :admin_id,
                :certificate_id,
                :issue_date,
                NOW()
            )
        ");

        $ins->execute([
            ':admin_id'       => $admin_id,
            ':certificate_id' => $cert_id,
            ':issue_date'     => $issue_date
        ]);

    } catch (Exception $e) {
        // Continue using generated certificate details.
    }

    $cert = [
        'certificate_id' => $cert_id,
        'issue_date'     => $issue_date
    ];
}


/* ==========================================================================
   7. CONVERT OLD RISE CERTIFICATE NUMBER
   ========================================================================== */

if (!empty($cert['certificate_id'])) {

    $old_certificate_id = trim(
        $cert['certificate_id']
    );

    if (
        stripos(
            $old_certificate_id,
            'RISE-AUTH-'
        ) === 0
    ) {

        $new_certificate_id = preg_replace(
            '/^RISE-AUTH-/i',
            'NBTE-AUTH-',
            $old_certificate_id
        );

        $cert['certificate_id'] =
            $new_certificate_id;

        try {

            $updateCert = $db->prepare("
                UPDATE admin_certificates
                SET certificate_id = :certificate_id
                WHERE admin_id = :admin_id
            ");

            $updateCert->execute([
                ':certificate_id' => $new_certificate_id,
                ':admin_id'       => $admin_id
            ]);

        } catch (Exception $e) {
            // Continue using converted ID in current certificate.
        }
    }
}


/* ==========================================================================
   8. IMAGE PREPARATION HELPER
   ========================================================================== */

if (!function_exists('nbte_prepare_image')) {

    function nbte_prepare_image(string $path): array
    {
        if (
            empty($path) ||
            !file_exists($path)
        ) {
            return [
                'path' => null,
                'tmp'  => null
            ];
        }

        $ext = strtolower(
            pathinfo(
                $path,
                PATHINFO_EXTENSION
            )
        );

        /*
         * jpg transparency is flattened onto white.
         * This prevents black/transparent backgrounds
         * when TCPDF renders the image.
         */

        if (
            $ext === 'jpg' &&
            function_exists('imagecreatefromjpg')
        ) {

            $src = @imagecreatefromjpg($path);

            if (!$src) {
                return [
                    'path' => $path,
                    'tmp'  => null
                ];
            }

            $width = imagesx($src);
            $height = imagesy($src);

            $flat = imagecreatetruecolor(
                $width,
                $height
            );

            $white = imagecolorallocate(
                $flat,
                255,
                255,
                255
            );

            imagefill(
                $flat,
                0,
                0,
                $white
            );

            imagecopy(
                $flat,
                $src,
                0,
                0,
                0,
                0,
                $width,
                $height
            );

            imagedestroy($src);

            $tmp = tempnam(
                sys_get_temp_dir(),
                'nbte_cert_'
            ) . '.jpg';

            imagejpeg(
                $flat,
                $tmp,
                95
            );

            imagedestroy($flat);

            return [
                'path' => $tmp,
                'tmp'  => $tmp
            ];
        }

        return [
            'path' => $path,
            'tmp'  => null
        ];
    }
}


/* ==========================================================================
   9. CLEANUP HELPER
   ========================================================================== */

if (!function_exists('nbte_cleanup_image')) {

    function nbte_cleanup_image(?string $tmp): void
    {
        if (
            !empty($tmp) &&
            file_exists($tmp)
        ) {
            @unlink($tmp);
        }
    }
}


/* ==========================================================================
   10. FIND NBTE LOGO
   ========================================================================== */

$logo_raw = '';

foreach ([
    'logo.jpg',
    'logo.jpg',
    'logo.jpeg'
] as $file) {

    $path =
        __DIR__ .
        '/assets/images/' .
        $file;

    if (file_exists($path)) {

        $logo_raw = $path;

        break;
    }
}

$logo_img = $logo_raw
    ? nbte_prepare_image($logo_raw)
    : [
        'path' => null,
        'tmp'  => null
    ];


/* ==========================================================================
   11. FIND AUTHORIZED SIGNATURE
   ========================================================================== */

$sig_raw = '';

foreach ([
    'authorized_signature.jpg',
    'authorized_signature.jpg',
    'authorized_signature.jpeg',
    'signature.jpg',
    'signature.jpg',
    'signature.jpeg'
] as $file) {

    $path =
        __DIR__ .
        '/assets/images/' .
        $file;

    if (file_exists($path)) {

        $sig_raw = $path;

        break;
    }
}

$sig_img = $sig_raw
    ? nbte_prepare_image($sig_raw)
    : [
        'path' => null,
        'tmp'  => null
    ];


/* ==========================================================================
   12. FIND SEAL
   ========================================================================== */

$seal_raw = '';

foreach ([
    'seal.jpg',
    'seal.jpg',
    'seal.jpeg'
] as $file) {

    $path =
        __DIR__ .
        '/assets/images/' .
        $file;

    if (file_exists($path)) {

        $seal_raw = $path;

        break;
    }
}

$seal_img = $seal_raw
    ? nbte_prepare_image($seal_raw)
    : [
        'path' => null,
        'tmp'  => null
    ];


/* ==========================================================================
   13. OPTIONAL COLLEGE LOGO
   ========================================================================== */

$clogo_raw = '';

foreach ([
    'college_logo.jpg',
    'college_logo.jpg',
    'college_logo.jpeg'
] as $file) {

    $path =
        __DIR__ .
        '/assets/images/' .
        $file;

    if (file_exists($path)) {

        $clogo_raw = $path;

        break;
    }
}

$clogo_img = $clogo_raw
    ? nbte_prepare_image($clogo_raw)
    : [
        'path' => null,
        'tmp'  => null
    ];


/* ==========================================================================
   14. VERIFICATION URL
   ========================================================================== */

$host = $_SERVER['HTTP_HOST'] ?? 'localhost';

$verify_url =
    'https://' .
    $host .
    '/verify_admin_certificate.php?cert_id=' .
    urlencode(
        $cert['certificate_id']
    );


/* ==========================================================================
   15. LOAD TCPDF
   ========================================================================== */

if (!class_exists('TCPDF')) {

    $tcpdf_paths = [

        __DIR__ . '/lib/TCPDF/tcpdf.php',

        __DIR__ . '/lib/tcpdf/tcpdf.php',

        __DIR__ . '/vendor/tecnickcom/tcpdf/tcpdf.php',

        __DIR__ . '/tcpdf/tcpdf.php'
    ];

    foreach ($tcpdf_paths as $path) {

        if (file_exists($path)) {

            require_once $path;

            break;
        }
    }

    if (
        !class_exists('TCPDF') &&
        file_exists(
            __DIR__ . '/vendor/autoload.php'
        )
    ) {

        require_once __DIR__ . '/vendor/autoload.php';
    }
}

if (!class_exists('TCPDF')) {

    die('TCPDF not found.');
}


/* ==========================================================================
   16. CUSTOM TCPDF CLASS
   ========================================================================== */

if (!class_exists('NBTE_Authorization_Certificate_PDF')) {

    class NBTE_Authorization_Certificate_PDF extends TCPDF
    {
        public function Header()
        {
        }

        public function Footer()
        {
        }
    }
}


/* ==========================================================================
   17. CREATE PDF
   ========================================================================== */

$pdf = new NBTE_Authorization_Certificate_PDF(
    'L',
    'mm',
    'A4',
    true,
    'UTF-8',
    false
);


/* ==========================================================================
   18. PDF METADATA
   ========================================================================== */

$pdf->SetCreator(
    'National Board for Technical Education'
);

$pdf->SetAuthor(
    'National Board for Technical Education'
);

$pdf->SetTitle(
    'Authorized Certificate - ' .
    $college_name
);

$pdf->SetSubject(
    'NBTE Authorized Training Centre Certificate'
);

$pdf->SetKeywords(
    'NBTE, National Board for Technical Education, Authorized Certificate'
);

$pdf->SetMargins(
    0,
    0,
    0
);

$pdf->SetAutoPageBreak(
    false,
    0
);

$pdf->SetPrintHeader(false);
$pdf->SetPrintFooter(false);

$pdf->AddPage();


/* ==========================================================================
   19. PAGE SIZE
   ========================================================================== */

$W = 297;
$H = 210;


/* ==========================================================================
   20. NBTE COLOUR PALETTE
   ========================================================================== */

$navy = [
    6,
    42,
    90
];

$gold = [
    212,
    151,
    41
];

$dark_gold = [
    170,
    116,
    25
];

$white = [
    255,
    255,
    255
];

$cream = [
    250,
    248,
    242
];

$soft = [
    247,
    249,
    252
];

$text = [
    55,
    65,
    80
];

$muted = [
    105,
    115,
    128
];

$line = [
    215,
    220,
    228
];


/* ==========================================================================
   21. BACKGROUND
   ========================================================================== */

$pdf->SetFillColor(
    ...$cream
);

$pdf->Rect(
    0,
    0,
    $W,
    $H,
    'F'
);


/* ==========================================================================
   22. OUTER FRAME
   ========================================================================== */

$pdf->SetDrawColor(
    ...$navy
);

$pdf->SetLineWidth(
    1.4
);

$pdf->Rect(
    8,
    8,
    $W - 16,
    $H - 16,
    'D'
);


/* ==========================================================================
   23. INNER FRAME
   ========================================================================== */

$pdf->SetDrawColor(
    ...$gold
);

$pdf->SetLineWidth(
    0.65
);

$pdf->Rect(
    11,
    11,
    $W - 22,
    $H - 22,
    'D'
);


/* ==========================================================================
   24. TOP GOLD ACCENT
   ========================================================================== */

$pdf->SetFillColor(
    ...$gold
);

$pdf->Rect(
    8,
    8,
    62,
    2.8,
    'F'
);


/* ==========================================================================
   25. LEFT GOLD ACCENT
   ========================================================================== */

$pdf->Rect(
    8,
    8,
    2.8,
    62,
    'F'
);


/* ==========================================================================
   26. BOTTOM GOLD ACCENT
   ========================================================================== */

$pdf->Rect(
    $W - 70,
    $H - 10.8,
    62,
    2.8,
    'F'
);


/* ==========================================================================
   27. RIGHT GOLD ACCENT
   ========================================================================== */

$pdf->Rect(
    $W - 10.8,
    $H - 70,
    2.8,
    62,
    'F'
);


/* ==========================================================================
   28. TOP ORGANIZATION TITLE
   ========================================================================== */

$pdf->SetTextColor(
    ...$navy
);

$pdf->SetFont(
    'helvetica',
    'B',
    14
);

$pdf->SetXY(
    55,
    13
);

$pdf->Cell(
    187,
    7,
    'NATIONAL BOARD FOR TECHNICAL EDUCATION',
    0,
    1,
    'C'
);


/* ==========================================================================
   29. SMALL NBTE LABEL
   ========================================================================== */

$pdf->SetTextColor(
    ...$muted
);

$pdf->SetFont(
    'helvetica',
    '',
    6
);

$pdf->SetXY(
    90,
    21
);

$pdf->Cell(
    117,
    4,
    'NBTE',
    0,
    1,
    'C'
);


/* ==========================================================================
   30. CERTIFICATE NUMBER
   ========================================================================== */

$pdf->SetTextColor(
    ...$muted
);

$pdf->SetFont(
    'helvetica',
    'B',
    5.5
);

$pdf->SetXY(
    18,
    18
);

$pdf->Cell(
    55,
    4,
    'CERTIFICATE NO.',
    0,
    1,
    'L'
);

$pdf->SetTextColor(
    ...$navy
);

$pdf->SetFont(
    'helvetica',
    'B',
    7
);

$pdf->SetXY(
    18,
    23
);

$pdf->Cell(
    62,
    5,
    $cert['certificate_id'],
    0,
    0,
    'L'
);


/* ==========================================================================
   31. ISSUE DATE
   ========================================================================== */

$pdf->SetTextColor(
    ...$muted
);

$pdf->SetFont(
    'helvetica',
    'B',
    5.5
);

$pdf->SetXY(
    $W - 80,
    18
);

$pdf->Cell(
    62,
    4,
    'DATE OF ISSUE',
    0,
    1,
    'R'
);

$pdf->SetTextColor(
    ...$navy
);

$pdf->SetFont(
    'helvetica',
    'B',
    7
);

$pdf->SetXY(
    $W - 88,
    23
);

$pdf->Cell(
    70,
    5,
    date(
        'd F Y',
        strtotime($cert['issue_date'])
    ),
    0,
    0,
    'R'
);


/* ==========================================================================
   32. NBTE LOGO
   ========================================================================== */

$logo_box_x = 136;
$logo_box_y = 25;
$logo_box_w = 25;
$logo_box_h = 21;


/*
 * White logo background.
 */

$pdf->SetFillColor(
    ...$white
);

$pdf->RoundedRect(
    $logo_box_x,
    $logo_box_y,
    $logo_box_w,
    $logo_box_h,
    2,
    '1111',
    'F'
);


/*
 * Logo with preserved aspect ratio.
 *
 * Height is set to 0 so TCPDF calculates
 * the correct proportional height.
 */

if ($logo_img['path']) {

    $pdf->Image(
        $logo_img['path'],
        139,
        25,
        19,
        0,
        '',
        '',
        '',
        true,
        250,
        '',
        false,
        false,
        0,
        false,
        false,
        false
    );

} else {

    $pdf->SetFillColor(
        ...$navy
    );

    $pdf->Circle(
        148.5,
        35,
        8,
        0,
        360,
        'F'
    );

    $pdf->SetTextColor(
        ...$white
    );

    $pdf->SetFont(
        'helvetica',
        'B',
        6.5
    );

    $pdf->SetXY(
        140.5,
        32
    );

    $pdf->Cell(
        16,
        5,
        'NBTE',
        0,
        0,
        'C'
    );
}


/* ==========================================================================
   33. GOLD DIVIDER
   ========================================================================== */

$pdf->SetDrawColor(
    ...$gold
);

$pdf->SetLineWidth(
    0.9
);

$pdf->Line(
    72,
    49,
    225,
    49
);


/* ==========================================================================
   34. MAIN CERTIFICATE TITLE
   ========================================================================== */

$pdf->SetTextColor(
    ...$navy
);

$pdf->SetFont(
    'times',
    'B',
    22
);

$pdf->SetXY(
    20,
    53
);

$pdf->Cell(
    257,
    10,
    'AUTHORIZED CERTIFICATE',
    0,
    1,
    'C'
);


/* ==========================================================================
   35. TITLE SUBTITLE
   ========================================================================== */

$pdf->SetTextColor(
    ...$gold
);

$pdf->SetFont(
    'helvetica',
    'B',
    6.5
);

$pdf->SetXY(
    20,
    64
);

$pdf->Cell(
    257,
    5,
    'TRAINING CENTRE RECOGNITION',
    0,
    1,
    'C'
);


/* ==========================================================================
   36. INTRODUCTION
   ========================================================================== */

$pdf->SetTextColor(
    ...$text
);

$pdf->SetFont(
    'times',
    'I',
    11
);

$pdf->SetXY(
    20,
    73
);

$pdf->Cell(
    257,
    6,
    'This is to certify that',
    0,
    1,
    'C'
);


/* ==========================================================================
   37. SUBTLE WATERMARK
   ========================================================================== */

$pdf->SetDrawColor(
    232,
    224,
    205
);

$pdf->SetLineWidth(
    0.3
);

for (
    $r = 16;
    $r <= 48;
    $r += 8
) {

    $pdf->Circle(
        232,
        108,
        $r,
        0,
        360,
        'D'
    );
}


/* ==========================================================================
   38. CENTRE NAME BOX
   ========================================================================== */

$cn_x = 40;
$cn_y = 82;
$cn_w = 217;
$cn_h = 23;


/*
 * Gold shadow.
 */

$pdf->SetFillColor(
    ...$gold
);

$pdf->RoundedRect(
    $cn_x + 2,
    $cn_y + 2,
    $cn_w,
    $cn_h,
    3,
    '1111',
    'F'
);


/*
 * White box.
 */

$pdf->SetFillColor(
    ...$white
);

$pdf->SetDrawColor(
    ...$navy
);

$pdf->SetLineWidth(
    0.7
);

$pdf->RoundedRect(
    $cn_x,
    $cn_y,
    $cn_w,
    $cn_h,
    3,
    '1111',
    'DF'
);


/* ==========================================================================
   39. COLLEGE LOGO INSIDE CENTRE BOX
   ========================================================================== */

$name_x = $cn_x + 5;
$name_w = $cn_w - 10;

if ($clogo_img['path']) {

    $college_logo_size = 14;

    $pdf->Image(
        $clogo_img['path'],
        $cn_x + 7,
        $cn_y + 4.5,
        $college_logo_size,
        0,
        '',
        '',
        '',
        true,
        180,
        '',
        false,
        false,
        0,
        false,
        false,
        false
    );

    $name_x =
        $cn_x + 25;

    $name_w =
        $cn_w - 30;
}


/* ==========================================================================
   40. DYNAMIC CENTRE NAME FONT SIZE
   ========================================================================== */

$college_length = mb_strlen(
    $college_name
);

if ($college_length > 75) {

    $college_font = 10.5;

} elseif ($college_length > 60) {

    $college_font = 12;

} elseif ($college_length > 45) {

    $college_font = 14;

} elseif ($college_length > 30) {

    $college_font = 16;

} else {

    $college_font = 19;
}


/* ==========================================================================
   41. CENTRE NAME
   ========================================================================== */

$pdf->SetTextColor(
    ...$navy
);

$pdf->SetFont(
    'times',
    'B',
    $college_font
);

$pdf->SetXY(
    $name_x,
    $cn_y + 5
);

$pdf->MultiCell(
    $name_w,
    7,
    $college_name,
    0,
    'C',
    false
);


/* ==========================================================================
   42. AUTHORIZATION SENTENCE
   ========================================================================== */

$pdf->SetTextColor(
    ...$text
);

$pdf->SetFont(
    'times',
    '',
    10
);

$pdf->SetXY(
    25,
    111
);

$pdf->Cell(
    247,
    6,
    'is hereby recognized as an Authorized Training Centre for the programme',
    0,
    1,
    'C'
);


/* ==========================================================================
   43. PROGRAMME NAME
   ========================================================================== */

$pdf->SetTextColor(
    ...$navy
);

$pdf->SetFont(
    'helvetica',
    'B',
    12
);

$pdf->SetXY(
    35,
    120
);

$pdf->Cell(
    227,
    7,
    $department_name,
    0,
    1,
    'C'
);


/* ==========================================================================
   44. PROGRAMME GOLD LINE
   ========================================================================== */

$pdf->SetDrawColor(
    ...$gold
);

$pdf->SetLineWidth(
    0.8
);

$pdf->Line(
    90,
    130,
    207,
    130
);


/* ==========================================================================
   45. AUTHORIZATION NOTE
   ========================================================================== */

$pdf->SetTextColor(
    ...$muted
);

$pdf->SetFont(
    'times',
    'I',
    7.5
);

$pdf->SetXY(
    42,
    135
);

$pdf->MultiCell(
    213,
    4.5,
    'This certificate is issued in recognition of the centre and its designated administration for the delivery of the above programme, subject to applicable NBTE requirements and verification.',
    0,
    'C',
    false
);


/* ==========================================================================
   46. LOWER INFORMATION PANEL
   ========================================================================== */

$panel_y = 154;

$pdf->SetFillColor(
    ...$soft
);

$pdf->Rect(
    14,
    $panel_y,
    $W - 28,
    29,
    'F'
);

$pdf->SetDrawColor(
    220,
    225,
    232
);

$pdf->SetLineWidth(
    0.3
);

$pdf->Line(
    14,
    $panel_y,
    $W - 14,
    $panel_y
);


/* ==========================================================================
   47. CENTRE HEAD
   ========================================================================== */

$pdf->SetTextColor(
    ...$muted
);

$pdf->SetFont(
    'helvetica',
    'B',
    5.5
);

$pdf->SetXY(
    30,
    $panel_y + 4
);

$pdf->Cell(
    70,
    4,
    'AUTHORIZED CENTRE HEAD',
    0,
    1,
    'C'
);

$pdf->SetTextColor(
    ...$navy
);

$pdf->SetFont(
    'helvetica',
    'B',
    9
);

$pdf->SetXY(
    30,
    $panel_y + 9
);

$pdf->Cell(
    70,
    5,
    $admin_name,
    0,
    1,
    'C'
);

if ($admin_email !== '') {

    $pdf->SetTextColor(
        ...$muted
    );

    $pdf->SetFont(
        'helvetica',
        '',
        5.3
    );

    $pdf->SetXY(
        24,
        $panel_y + 15
    );

    $pdf->Cell(
        82,
        4,
        $admin_email,
        0,
        1,
        'C'
    );
}


/* ==========================================================================
   48. QR CODE
   ========================================================================== */

/*
 * IMPORTANT:
 *
 * QR is deliberately positioned at 161mm.
 * Footer begins at 194mm.
 *
 * Therefore QR + label cannot overlap footer.
 */

$qr_x = 25;
$qr_y = 160;
$qr_size = 18;


/*
 * QR white container.
 */

$pdf->SetFillColor(
    ...$white
);

$pdf->SetDrawColor(
    ...$navy
);

$pdf->SetLineWidth(
    0.4
);

$pdf->RoundedRect(
    $qr_x - 2,
    $qr_y - 2,
    $qr_size + 4,
    $qr_size + 8,
    1.5,
    '1111',
    'DF'
);


/*
 * QR.
 */

$pdf->write2DBarcode(
    $verify_url,
    'QRCODE,H',
    $qr_x,
    $qr_y,
    $qr_size,
    $qr_size,
    [
        'border'   => false,
        'vpadding' => 0,
        'hpadding' => 0,
        'fgcolor'  => $navy,
        'bgcolor'  => $white
    ],
    'N'
);


/*
 * QR label.
 */

$pdf->SetTextColor(
    ...$navy
);

$pdf->SetFont(
    'helvetica',
    'B',
    5.2
);

$pdf->SetXY(
    $qr_x - 2,
    $qr_y + $qr_size + 1
);

$pdf->Cell(
    $qr_size + 4,
    4,
    'SCAN TO VERIFY',
    0,
    0,
    'C'
);


/* ==========================================================================
   49. SEAL
   ========================================================================== */

$seal_cx = 148.5;
$seal_cy = 169;

if ($seal_img['path']) {

    $seal_size = 23;

    $pdf->Image(
        $seal_img['path'],
        $seal_cx - ($seal_size / 2),
        $seal_cy - ($seal_size / 2),
        $seal_size,
        0,
        '',
        '',
        '',
        true,
        180,
        '',
        false,
        false,
        0,
        false,
        false,
        false
    );

} else {

    /*
     * Fallback seal.
     */

    $pdf->SetDrawColor(
        ...$gold
    );

    $pdf->SetLineWidth(
        0.8
    );

    $pdf->Circle(
        $seal_cx,
        $seal_cy,
        11,
        0,
        360,
        'D'
    );

    $pdf->SetLineWidth(
        0.4
    );

    $pdf->Circle(
        $seal_cx,
        $seal_cy,
        8,
        0,
        360,
        'D'
    );

    $pdf->SetTextColor(
        ...$gold
    );

    $pdf->SetFont(
        'helvetica',
        'B',
        6
    );

    $pdf->SetXY(
        $seal_cx - 10,
        $seal_cy - 3
    );

    $pdf->Cell(
        20,
        5,
        'NBTE',
        0,
        0,
        'C'
    );
}


/* ==========================================================================
   50. SIGNATURE
   ========================================================================== */

$signature_x = 205;
$signature_width = 65;
$signature_line_y = 177;


/*
 * Signature image.
 */

if ($sig_img['path']) {

    $pdf->Image(
        $sig_img['path'],
        $signature_x + 13,
        $signature_line_y - 15,
        39,
        0,
        '',
        '',
        '',
        true,
        160,
        '',
        false,
        false,
        0,
        false,
        false,
        false
    );
}


/*
 * Signature line.
 */

$pdf->SetDrawColor(
    ...$navy
);

$pdf->SetLineWidth(
    0.5
);

$pdf->Line(
    $signature_x,
    $signature_line_y,
    $signature_x + $signature_width,
    $signature_line_y
);


/*
 * Signature title.
 */

$pdf->SetTextColor(
    ...$navy
);

$pdf->SetFont(
    'helvetica',
    'B',
    6.5
);

$pdf->SetXY(
    $signature_x,
    $signature_line_y + 2
);

$pdf->Cell(
    $signature_width,
    4,
    'AUTHORIZED SIGNATORY',
    0,
    1,
    'C'
);


/*
 * Signature organization.
 */

$pdf->SetTextColor(
    ...$muted
);

$pdf->SetFont(
    'helvetica',
    '',
    5.2
);

$pdf->SetXY(
    $signature_x - 5,
    $signature_line_y + 7
);

$pdf->Cell(
    $signature_width + 10,
    4,
    'National Board for Technical Education',
    0,
    1,
    'C'
);


/* ==========================================================================
   51. CORNER CIRCLES
   ========================================================================== */

$pdf->SetDrawColor(
    ...$gold
);

$pdf->SetLineWidth(
    0.45
);

foreach ([
    [18, 28],
    [18, 174],
    [279, 28],
    [279, 174]
] as $point) {

    $pdf->Circle(
        $point[0],
        $point[1],
        2,
        0,
        360,
        'D'
    );
}


/* ==========================================================================
   52. FOOTER
   ========================================================================== */

$pdf->SetFillColor(
    ...$navy
);

$pdf->Rect(
    8,
    194,
    $W - 16,
    8,
    'F'
);


/*
 * Gold footer block.
 */

$pdf->SetFillColor(
    ...$gold
);

$pdf->Rect(
    8,
    194,
    38,
    8,
    'F'
);


/*
 * Footer text.
 */

$pdf->SetTextColor(
    ...$white
);

$pdf->SetFont(
    'helvetica',
    'B',
    5.2
);

$pdf->SetXY(
    50,
    196
);

$pdf->Cell(
    235,
    4,
    'NATIONAL BOARD FOR TECHNICAL EDUCATION  •  NBTE  •  AUTHORIZED CERTIFICATE',
    0,
    0,
    'R'
);


/* ==========================================================================
   53. CLEANUP TEMPORARY IMAGES
   ========================================================================== */

nbte_cleanup_image(
    $logo_img['tmp']
);

nbte_cleanup_image(
    $sig_img['tmp']
);

nbte_cleanup_image(
    $seal_img['tmp']
);

nbte_cleanup_image(
    $clogo_img['tmp']
);


/* ==========================================================================
   54. OUTPUT PDF
   ========================================================================== */

$safe_name = preg_replace(
    '/[^A-Za-z0-9_]/',
    '_',
    $college_name
);

$pdf->Output(
    'NBTE_Authorized_Certificate_' .
    $safe_name .
    '.pdf',
    'I'
);

exit;
?>