<!-- verify_admin_certificate.php -->

<!DOCTYPE html>
<html>
<head>
    <title>Verify Admin Certificate</title>
</head>
<body style="text-align:center; margin-top:100px; font-family:Arial; background:#f5f5f5;">

    <h2>Verify Admin Certificate</h2>

    <form method="GET" action="verify_admin_certificate.php">

        <input
            type="text"
            name="cert_id"
            placeholder="Enter Affiliation Code"
            required
            style="padding:10px; width:250px; border:1px solid #ccc; border-radius:5px;"
        >

        <br><br>

        <button
            type="submit"
            style="padding:10px 20px; background:#0d3664; color:white; border:none; border-radius:5px; cursor:pointer;"
        >
            Verify Certificate
        </button>

    </form>

</body>
</html>

<?php
require_once 'includes/db.php';

$db = getDB();

// Get Certificate ID
$cert_id = $_GET['cert_id'] ?? '';

if (empty($cert_id)) {
    exit;
}

// Fetch certificate
$stmt = $db->prepare("
    SELECT ac.*, a.name, a.college_name
    FROM admin_certificates ac
    JOIN admins a ON a.id = ac.admin_id
    WHERE ac.certificate_id = :cert_id
    LIMIT 1
");

$stmt->execute([
    ':cert_id' => $cert_id
]);

$certificate = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$certificate) {
    die('<h3 style="text-align:center;color:red;margin-top:50px;">Certificate Not Found</h3>');
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Certificate Verified</title>
</head>
<body style="font-family:Arial; background:#f5f5f5;">

<div style="
    width:500px;
    margin:50px auto;
    background:white;
    padding:30px;
    border-radius:10px;
    box-shadow:0 0 10px rgba(0,0,0,0.1);
    text-align:center;
">

    <h2 style="color:green;">✅ Certificate Verified</h2>

    <hr>

    <p><strong>Affiliation Code:</strong><br>
    <?php echo htmlspecialchars($certificate['certificate_id']); ?></p>

    <p><strong>Admin Name:</strong><br>
    <?php echo htmlspecialchars($certificate['name']); ?></p>

    <p><strong>College / Institute:</strong><br>
    <?php echo htmlspecialchars($certificate['college_name']); ?></p>

    <p><strong>Issue Date:</strong><br>
    <?php echo date('d M Y', strtotime($certificate['issue_date'])); ?></p>

    <div style="
        margin-top:20px;
        padding:15px;
        background:#e8fff0;
        border:1px solid #b6f0c2;
        border-radius:5px;
        color:green;
        font-weight:bold;
    ">
        This Certificate is Officially Verified by RISE
    </div>

</div>

</body>
</html>