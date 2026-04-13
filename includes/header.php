<?php
/**
 * RISE - Header Include
 * =======================
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/csrf.php';

$currentUser = getCurrentUser();
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
?>
<!DOCTYPE html>
<html lang="en" data-theme="light" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title><?php echo isset($pageTitle) ? sanitize($pageTitle) . ' - ' : ''; ?><?php echo APP_NAME; ?></title>

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome 6 -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="assets/css/style.css" rel="stylesheet">

    <style>
        /* Inline fix for dropdown in dark mode */
        [data-theme="dark"] .dropdown-menu {
            background-color: var(--bg-card);
            border: 1px solid var(--border-color);
        }
        [data-theme="dark"] .dropdown-item {
            color: var(--text-primary);
        }
        [data-theme="dark"] .dropdown-item:hover {
            background-color: rgba(78, 115, 223, 0.1);
        }
    </style>
</head>
<body>

<?php
// Show message only to admins (not super admin)
if (!isSuperAdmin()) {
    require_once __DIR__ . '/db.php';
    $db = getDB();

    $stmt = $db->query("SELECT message FROM messages ORDER BY id DESC LIMIT 1");
    $topMessage = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>

<?php if (!isSuperAdmin() && !empty($topMessage['message'])): ?>
<div style="background:white; color:red; padding:6px; font-weight:bold;">
    <marquee>
        🔔 <?php echo htmlspecialchars($topMessage['message']); ?>
    </marquee>
</div>
<?php endif; ?>