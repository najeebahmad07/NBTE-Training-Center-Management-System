<?php
/**
 * RISE SaaS - PDF Debug Tool (FIXED)
 * File: debug_pdf.php
 * DELETE AFTER FIXING
 */

// Load same session config as your app
if (file_exists('config.php')) {
    require_once 'config.php';
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<!DOCTYPE html><html><head><title>RISE Debug</title>";
echo "<style>body{font-family:Arial;padding:20px;max-width:800px;margin:0 auto;}";
echo "code{background:#f0f0f0;padding:2px 6px;border-radius:3px;}</style></head><body>";
echo "<h2>🔍 RISE PDF Debug Report</h2><hr>";

// ====== CHECK 1: Session ======
echo "<h3>1️⃣ Session Check</h3>";
echo "Session ID: " . session_id() . "<br>";
echo "Session Save Path: " . session_save_path() . "<br>";
echo "Session Status: " . session_status() . "<br>";
echo "<br><strong>All Session Data:</strong><br>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";

if (isset($_SESSION['admin_id'])) {
    echo "✅ Logged in as Admin ID: <strong>" . $_SESSION['admin_id'] . "</strong><br>";
    echo "✅ Role: <strong>" . ($_SESSION['role'] ?? 'NOT SET') . "</strong><br>";
    echo "✅ Name: <strong>" . ($_SESSION['admin_name'] ?? 'NOT SET') . "</strong><br>";
} else {
    echo "❌ <strong style='color:red;'>NOT LOGGED IN</strong><br><br>";
    echo "Possible causes:<br>";
    echo "1. Session name mismatch between config.php and login.php<br>";
    echo "2. Session cookie path issue<br>";
    echo "3. You are accessing from different domain/port<br>";
    echo "<br>";

    // Show what login.php sets
    echo "<strong>Checking your auth.php:</strong><br>";
    if (file_exists('includes/auth.php')) {
        $auth_content = file_get_contents('includes/auth.php');
        echo "<pre>" . htmlspecialchars($auth_content) . "</pre>";
    } else {
        echo "❌ includes/auth.php NOT FOUND<br>";
    }

    echo "<br><strong>Checking your login.php session variables:</strong><br>";
    if (file_exists('login.php')) {
        $login_content = file_get_contents('login.php');
        // Find SESSION lines
        preg_match_all('/\$_SESSION\[.*\].*=.*/', $login_content, $matches);
        if (!empty($matches[0])) {
            foreach ($matches[0] as $line) {
                echo "<code>" . htmlspecialchars(trim($line)) . "</code><br>";
            }
        }
    }
}

echo "<hr>";

// ====== CHECK 2: TCPDF ======
echo "<h3>2️⃣ TCPDF Library Check</h3>";

// First scan lib directory
if (is_dir('lib')) {
    echo "📁 <strong>lib/</strong> folder contents:<br>";
    $lib_items = scandir('lib');
    foreach ($lib_items as $item) {
        if ($item !== '.' && $item !== '..') {
            echo "  ├── <strong>{$item}</strong>";
            if (is_dir("lib/{$item}")) {
                echo " (📁 folder)";
                // Check for tcpdf.php inside
                $sub_items = scandir("lib/{$item}");
                foreach ($sub_items as $sub) {
                    if (strtolower($sub) === 'tcpdf.php') {
                        echo " → <span style='color:green;'>✅ tcpdf.php found!</span>";
                        echo "<br>     <strong>Correct path: lib/{$item}/{$sub}</strong>";
                    }
                }
                // Also check one level deeper (lib/tcpdf/tcpdf/tcpdf.php)
                foreach ($sub_items as $sub) {
                    if ($sub !== '.' && $sub !== '..' && is_dir("lib/{$item}/{$sub}")) {
                        $deep_items = scandir("lib/{$item}/{$sub}");
                        foreach ($deep_items as $deep) {
                            if (strtolower($deep) === 'tcpdf.php') {
                                echo "<br>  │   ├── <strong>{$sub}/</strong> → ";
                                echo "<span style='color:orange;'>⚠️ tcpdf.php found DEEPER: lib/{$item}/{$sub}/{$deep}</span>";
                            }
                        }
                    }
                }
            }
            echo "<br>";
        }
    }
} else {
    echo "❌ <strong>lib/</strong> folder does NOT exist!<br>";
}

echo "<br>";

$tcpdf_paths = [
    'lib/tcpdf/tcpdf.php',
    'lib/TCPDF/tcpdf.php',
    'lib/tcpdf/TCPDF/tcpdf.php',
    'lib/TCPDF-main/tcpdf.php',
    'lib/tcpdf-main/tcpdf.php',
    'lib/TCPDF-6.7.5/tcpdf.php',
    'lib/TCPDF-6.7.6/tcpdf.php',
    'lib/TCPDF-6.6.5/tcpdf.php',
    'vendor/tecnickcom/tcpdf/tcpdf.php',
    'tcpdf/tcpdf.php',
    'TCPDF/tcpdf.php'
];

$tcpdf_found = false;
$tcpdf_actual_path = '';

foreach ($tcpdf_paths as $path) {
    if (file_exists($path)) {
        echo "✅ <strong style='color:green;'>FOUND at: {$path}</strong><br>";
        $tcpdf_found = true;
        $tcpdf_actual_path = $path;
        require_once $path;
        break;
    } else {
        echo "❌ Not at: {$path}<br>";
    }
}

if ($tcpdf_found) {
    if (class_exists('TCPDF')) {
        echo "<br>✅ TCPDF class loaded!<br>";
        echo "✅ Version: " . TCPDF_STATIC::getTCPDFVersion() . "<br>";
        echo "<br><strong style='color:green;'>👉 Use this path in your code: <code>{$tcpdf_actual_path}</code></strong><br>";
    }
} else {
    echo "<br>❌ <strong style='color:red;'>TCPDF NOT FOUND IN ANY LOCATION!</strong><br>";
}

echo "<hr>";

// ====== CHECK 3: Database ======
echo "<h3>3️⃣ Database Check</h3>";
try {
    require_once 'includes/db.php';
    echo "✅ Database connected!<br>";

    // Test student query
    if (isset($_GET['student_id'])) {
        $sid = (int)$_GET['student_id'];
        $stmt = $pdo->prepare("SELECT * FROM students WHERE id = :id");
        $stmt->execute([':id' => $sid]);
        $stu = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($stu) {
            echo "✅ Student #{$sid}: <strong>" . htmlspecialchars($stu['full_name']) . "</strong><br>";
            echo "  Status: <strong>" . $stu['status'] . "</strong><br>";
            echo "  Admin ID: " . $stu['admin_id'] . "<br>";
            echo "  Enrollment: " . $stu['enrollment_no'] . "<br>";

            if ($stu['status'] !== 'Approved') {
                echo "⚠️ <strong style='color:orange;'>NOT APPROVED - Approve student first!</strong><br>";
            }

            // Check marks
            $mstmt = $pdo->prepare("SELECT COUNT(*) FROM marks WHERE student_id = :id");
            $mstmt->execute([':id' => $sid]);
            $mc = $mstmt->fetchColumn();
            echo "  Marks entries: {$mc}<br>";
            if ($mc == 0) {
                echo "⚠️ <strong style='color:orange;'>No marks - Marksheet/Certificate needs marks!</strong><br>";
            }
        } else {
            echo "❌ Student #{$sid} NOT found<br>";
        }
    }
} catch (Exception $e) {
    echo "❌ DB Error: " . $e->getMessage() . "<br>";
}

echo "<hr>";

// ====== CHECK 4: Directories ======
echo "<h3>4️⃣ Upload Directories</h3>";
$dirs = ['uploads', 'uploads/photos', 'uploads/signatures', 'uploads/documents',
         'uploads/marksheets', 'uploads/id_cards', 'uploads/certificates'];

$all_ok = true;
foreach ($dirs as $dir) {
    if (is_dir($dir)) {
        if (is_writable($dir)) {
            echo "✅ {$dir} - OK<br>";
        } else {
            echo "❌ <strong>{$dir} - NOT WRITABLE!</strong><br>";
            $all_ok = false;
        }
    } else {
        echo "❌ <strong>{$dir} - MISSING!</strong><br>";
        $all_ok = false;
    }
}

if (!$all_ok) {
    echo "<br><strong>Run <code>setup_directories.php</code> first!</strong><br>";
}

// Test actual write
echo "<br><strong>Write test:</strong><br>";
$test_file = 'uploads/id_cards/write_test_' . time() . '.txt';
if (@file_put_contents($test_file, 'test')) {
    echo "✅ Can write to uploads/id_cards/<br>";
    unlink($test_file);
} else {
    echo "❌ <strong style='color:red;'>CANNOT WRITE to uploads/id_cards/</strong><br>";
    echo "Run: <code>chmod -R 777 uploads/</code> (temporary fix)<br>";
    echo "Then: <code>chmod -R 755 uploads/</code> (after it works)<br>";
}

echo "<hr>";

// ====== CHECK 5: Test PDF ======
echo "<h3>5️⃣ PDF Generation Test</h3>";
if ($tcpdf_found && class_exists('TCPDF') && is_writable('uploads/id_cards')) {
    try {
        $test_pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $test_pdf->SetPrintHeader(false);
        $test_pdf->SetPrintFooter(false);
        $test_pdf->AddPage();
        $test_pdf->SetFont('helvetica', 'B', 20);
        $test_pdf->Cell(0, 20, 'RISE PDF Working!', 0, 1, 'C');

        $test_path = 'uploads/id_cards/test_' . time() . '.pdf';
        $test_pdf->Output($test_path, 'F');

        if (file_exists($test_path)) {
            echo "✅ <strong style='color:green;'>PDF GENERATION WORKS!</strong><br>";
            echo "✅ File created: {$test_path}<br>";
            $size = filesize($test_path);
            echo "✅ File size: {$size} bytes<br>";
            unlink($test_path);
            echo "✅ Test file cleaned up<br>";
        }
    } catch (Exception $e) {
        echo "❌ PDF Error: " . $e->getMessage() . "<br>";
    }
}

echo "<hr>";

// ====== SUMMARY ======
echo "<h3>📋 Action Items</h3>";
echo "<table border='1' cellpadding='8' style='border-collapse:collapse;'>";
echo "<tr><th>Issue</th><th>Fix</th></tr>";

if (!isset($_SESSION['admin_id'])) {
    echo "<tr style='background:#ffebee;'>";
    echo "<td>❌ Session not working</td>";
    echo "<td>Check your <code>login.php</code> sets <code>\$_SESSION['admin_id']</code><br>";
    echo "Make sure <code>config.php</code> session settings match</td></tr>";
}

if (!$tcpdf_found) {
    echo "<tr style='background:#ffebee;'>";
    echo "<td>❌ TCPDF not found</td>";
    echo "<td>Move tcpdf.php to <code>lib/tcpdf/tcpdf.php</code></td></tr>";
}

if (!$all_ok) {
    echo "<tr style='background:#ffebee;'>";
    echo "<td>❌ Directories missing/not writable</td>";
    echo "<td>Run <code>setup_directories.php</code> or <code>chmod -R 755 uploads/</code></td></tr>";
}

echo "</table>";

echo "<br><strong style='color:red;'>⚠️ DELETE debug_pdf.php AND setup_directories.php after fixing!</strong>";
echo "</body></html>";
?>