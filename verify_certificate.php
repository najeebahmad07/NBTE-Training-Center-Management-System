<?php
require_once 'includes/db.php';

$db = getDB();

// Get input
$cert_id     = trim($_GET['cert_id'] ?? '');
$enrollment  = trim($_GET['enrollment'] ?? '');

// If nothing entered → show search form
if (!$cert_id && !$enrollment) {
?>
<!DOCTYPE html>
<html>
<head>
    <title>Verify Certificate</title>
    <style>
        body {
            font-family: Arial;
            background: #f5f7fb;
            text-align: center;
            padding: 60px;
        }
        .box {
            background: #fff;
            padding: 30px;
            max-width: 400px;
            margin: auto;
            border-radius: 10px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        input {
            width: 90%;
            padding: 10px;
            margin: 10px 0;
        }
        button {
            padding: 10px 20px;
            background: #0d6efd;
            color: #fff;
            border: none;
            cursor: pointer;
        }
    </style>
</head>
<body>

<div class="box">
    <h2>Download Certificate</h2>
    <form method="GET">
        <input type="text" name="enrollment" placeholder="Enter Enrollment No"><br>
        <b>OR</b><br>
        <input type="text" name="cert_id" placeholder="Enter Certificate No"><br>
        <button type="submit">Download Certificate</button>
    </form>
</div>

</body>
</html>
<?php
exit;
}

// ================= FETCH DATA =================

// Search by certificate ID or enrollment
$query = "
    SELECT s.*, c.certificate_id, s.certificate_pdf
    FROM students s
    LEFT JOIN certificates c ON s.id = c.student_id
    WHERE 1=1
";

$params = [];

if ($cert_id) {
    $query .= " AND c.certificate_id = :cert_id";
    $params[':cert_id'] = $cert_id;
}

if ($enrollment) {
    $query .= " AND s.enrollment_no = :enrollment";
    $params[':enrollment'] = $enrollment;
}

$stmt = $db->prepare($query);
$stmt->execute($params);

$data = $stmt->fetch(PDO::FETCH_ASSOC);

// ================= VALIDATION =================

if (!$data || empty($data['certificate_pdf'])) {
    die("<h3 style='text-align:center;color:red;'>Certificate not found or not generated.</h3>");
}

// ================= SHOW PDF =================

$pdf_path = __DIR__ . '/uploads/certificates/' . $data['certificate_pdf'];

if (!file_exists($pdf_path)) {
    die("<h3 style='text-align:center;color:red;'>Certificate file missing.</h3>");
}

// Output PDF
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="certificate.pdf"');
readfile($pdf_path);
exit;