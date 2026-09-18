<?php
/**
 * RISE - Premium Dashboard
 * =========================
 * Admin + Super Admin Dashboard
 *
 * Uses existing database structure.
 */

$pageTitle = 'Dashboard';

require_once 'includes/header.php';
require_once 'includes/sidebar.php';
requireLogin();

$db = getDB();
$userId = getCurrentUserId();
$role = $_SESSION['user_role'] ?? '';

$isSuperAdmin = isSuperAdmin();

/* =========================================================
   DASHBOARD STATISTICS
   ========================================================= */

if ($isSuperAdmin) {

    /* -----------------------------------------
       SUPER ADMIN STATISTICS
       ----------------------------------------- */

    // Total Students
    $stmtStudents = $db->query("SELECT COUNT(*) FROM students");
    $totalStudents = (int) $stmtStudents->fetchColumn();

    // Total Admins
    $stmtAdmins = $db->query("SELECT COUNT(*) FROM admins WHERE role = 'admin'");
    $totalAdmins = (int) $stmtAdmins->fetchColumn();

    // Total Programs
    $stmtPrograms = $db->query("SELECT COUNT(*) FROM programs");
    $totalPrograms = (int) $stmtPrograms->fetchColumn();

    // Total Certificates
    $stmtCerts = $db->query("SELECT COUNT(*) FROM certificates");
    $totalCerts = (int) $stmtCerts->fetchColumn();

    // Pending Students
    $stmtPending = $db->query("
        SELECT COUNT(*)
        FROM students
        WHERE status = 'Pending'
    ");
    $totalPending = (int) $stmtPending->fetchColumn();

    // Approved Students
    $stmtApproved = $db->query("
        SELECT COUNT(*)
        FROM students
        WHERE status = 'Approved'
    ");
    $totalApproved = (int) $stmtApproved->fetchColumn();

    // Monthly Students
    $stmtMonthly = $db->query("
        SELECT
            DATE_FORMAT(created_at, '%Y-%m') AS month,
            COUNT(*) AS count
        FROM students
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
        GROUP BY month
        ORDER BY month
    ");

    $monthlyData = $stmtMonthly->fetchAll();

    // Recent Students
    $stmtRecent = $db->query("
        SELECT
            s.*,
            a.name AS admin_name,
            p.program_name
        FROM students s
        JOIN admins a ON s.admin_id = a.id
        JOIN programs p ON s.program_id = p.id
        ORDER BY s.created_at DESC
        LIMIT 10
    ");

    $recentStudents = $stmtRecent->fetchAll();

} else {

    /* -----------------------------------------
       ADMIN STATISTICS
       ----------------------------------------- */

    // My Students
    $stmtStudents = $db->prepare("
        SELECT COUNT(*)
        FROM students
        WHERE admin_id = :admin_id
    ");

    $stmtStudents->execute([
        ':admin_id' => $userId
    ]);

    $totalStudents = (int) $stmtStudents->fetchColumn();

    // Pending Students
    $stmtPending = $db->prepare("
        SELECT COUNT(*)
        FROM students
        WHERE admin_id = :admin_id
        AND status = 'Pending'
    ");

    $stmtPending->execute([
        ':admin_id' => $userId
    ]);

    $totalPending = (int) $stmtPending->fetchColumn();

    // Approved Students
    $stmtApproved = $db->prepare("
        SELECT COUNT(*)
        FROM students
        WHERE admin_id = :admin_id
        AND status = 'Approved'
    ");

    $stmtApproved->execute([
        ':admin_id' => $userId
    ]);

    $totalApproved = (int) $stmtApproved->fetchColumn();

    // Certificates
    $stmtCerts = $db->prepare("
        SELECT COUNT(*)
        FROM certificates c
        JOIN students s ON c.student_id = s.id
        WHERE s.admin_id = :admin_id
    ");

    $stmtCerts->execute([
        ':admin_id' => $userId
    ]);

    $totalCerts = (int) $stmtCerts->fetchColumn();

    // Monthly Students
    $stmtMonthly = $db->prepare("
        SELECT
            DATE_FORMAT(created_at, '%Y-%m') AS month,
            COUNT(*) AS count
        FROM students
        WHERE admin_id = :admin_id
        AND created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
        GROUP BY month
        ORDER BY month
    ");

    $stmtMonthly->execute([
        ':admin_id' => $userId
    ]);

    $monthlyData = $stmtMonthly->fetchAll();

    // Recent Students
    $stmtRecent = $db->prepare("
        SELECT
            s.*,
            p.program_name
        FROM students s
        JOIN programs p ON s.program_id = p.id
        WHERE s.admin_id = :admin_id
        ORDER BY s.created_at DESC
        LIMIT 10
    ");

    $stmtRecent->execute([
        ':admin_id' => $userId
    ]);

    $recentStudents = $stmtRecent->fetchAll();
}


/* =========================================================
   CHART DATA
   ========================================================= */

$chartLabels = [];
$chartValues = [];

foreach ($monthlyData as $md) {

    $chartLabels[] = date(
        'M Y',
        strtotime($md['month'] . '-01')
    );

    $chartValues[] = (int) $md['count'];
}


/* =========================================================
   APPROVAL PERCENTAGE
   ========================================================= */

$totalProcessed = $totalApproved + $totalPending;

if ($totalProcessed > 0) {
    $approvedPercentage = round(
        ($totalApproved / $totalProcessed) * 100
    );

    $pendingPercentage = 100 - $approvedPercentage;
} else {
    $approvedPercentage = 0;
    $pendingPercentage = 0;
}


/* =========================================================
   USER DISPLAY NAME
   ========================================================= */

$displayRole = $isSuperAdmin ? 'Super Administrator' : 'Administrator';

$displayName = '';

if (!empty($_SESSION['user_name'])) {
    $displayName = $_SESSION['user_name'];
} elseif (!empty($_SESSION['name'])) {
    $displayName = $_SESSION['name'];
} else {
    $displayName = $displayRole;
}

?>

<style>

/* =========================================================
   PREMIUM NBTE DASHBOARD
   ========================================================= */

:root {
    --nbte-navy: #062A5A;
    --nbte-navy-dark: #041d40;
    --nbte-gold: #D49729;
    --nbte-gold-light: #f7ead0;

    --dash-bg: #f5f7fb;
    --dash-white: #ffffff;
    --dash-text: #243447;
    --dash-muted: #7b8794;

    --dash-green: #1cc88a;
    --dash-orange: #f6c23e;
    --dash-blue: #36b9cc;
    --dash-red: #e74a3b;

    --dash-shadow:
        0 8px 30px rgba(6, 42, 90, 0.07);

    --dash-shadow-hover:
        0 16px 40px rgba(6, 42, 90, 0.13);
}


/* =========================================================
   PAGE BACKGROUND
   ========================================================= */

body {
    background: var(--dash-bg);
}

.dashboard-page {
    width: 100%;
}


/* =========================================================
   WELCOME HEADER
   ========================================================= */

.dashboard-welcome {
    position: relative;
    overflow: hidden;

    background:
        linear-gradient(
            135deg,
            var(--nbte-navy) 0%,
            #0b3c78 65%,
            #14528d 100%
        );

    border-radius: 20px;

    padding: 28px 32px;

    margin-bottom: 28px;

    color: #fff;

    box-shadow:
        0 12px 35px rgba(6, 42, 90, 0.18);
}

.dashboard-welcome::before {
    content: "";

    position: absolute;

    width: 260px;
    height: 260px;

    border-radius: 50%;

    background: rgba(212, 151, 41, 0.12);

    right: -80px;
    top: -120px;
}

.dashboard-welcome::after {
    content: "";

    position: absolute;

    width: 180px;
    height: 180px;

    border-radius: 50%;

    background: rgba(255,255,255,0.05);

    right: 130px;
    bottom: -120px;
}

.dashboard-welcome-content {
    position: relative;
    z-index: 2;
}

.dashboard-welcome-label {
    display: inline-flex;
    align-items: center;
    gap: 8px;

    font-size: 12px;
    font-weight: 700;

    text-transform: uppercase;
    letter-spacing: 1px;

    color: #f5d994;

    margin-bottom: 8px;
}

.dashboard-welcome-title {
    font-size: 28px;
    font-weight: 800;

    margin: 0 0 7px;

    color: #fff;
}

.dashboard-welcome-text {
    margin: 0;

    color: rgba(255,255,255,0.76);

    font-size: 14px;
}

.dashboard-time-box {
    position: relative;
    z-index: 2;

    display: inline-flex;
    align-items: center;
    gap: 10px;

    background: rgba(255,255,255,0.10);

    border: 1px solid rgba(255,255,255,0.15);

    border-radius: 12px;

    padding: 11px 16px;

    font-size: 13px;

    backdrop-filter: blur(8px);
}

.dashboard-time-box i {
    color: #f5d994;
}


/* =========================================================
   STAT CARDS
   ========================================================= */

.premium-stat-card {
    position: relative;

    height: 100%;

    background: #fff;

    border-radius: 17px;

    padding: 22px;

    border: 1px solid rgba(6, 42, 90, 0.06);

    box-shadow: var(--dash-shadow);

    overflow: hidden;

    transition:
        transform 0.25s ease,
        box-shadow 0.25s ease;
}

.premium-stat-card:hover {
    transform: translateY(-5px);

    box-shadow: var(--dash-shadow-hover);
}

.premium-stat-card::after {
    content: "";

    position: absolute;

    width: 90px;
    height: 90px;

    border-radius: 50%;

    right: -35px;
    bottom: -40px;

    background: var(--card-soft);
}

.premium-stat-top {
    display: flex;

    justify-content: space-between;

    align-items: flex-start;
}

.premium-stat-icon {
    width: 54px;
    height: 54px;

    border-radius: 15px;

    display: flex;

    align-items: center;
    justify-content: center;

    font-size: 21px;

    background: var(--card-soft);

    color: var(--card-color);
}

.premium-stat-label {
    font-size: 13px;

    color: var(--dash-muted);

    font-weight: 600;

    margin-top: 17px;
}

.premium-stat-value {
    font-size: 30px;

    line-height: 1.1;

    font-weight: 800;

    color: var(--dash-text);

    margin-top: 5px;
}

.premium-stat-line {
    width: 45px;
    height: 3px;

    border-radius: 10px;

    background: var(--card-color);

    margin-top: 15px;
}


/* Card color variants */

.stat-students {
    --card-color: var(--nbte-navy);
    --card-soft: rgba(6, 42, 90, 0.10);
}

.stat-admins {
    --card-color: var(--nbte-gold);
    --card-soft: rgba(212, 151, 41, 0.13);
}

.stat-programs {
    --card-color: #36b9cc;
    --card-soft: rgba(54, 185, 204, 0.12);
}

.stat-certificates {
    --card-color: #1cc88a;
    --card-soft: rgba(28, 200, 138, 0.12);
}

.stat-pending {
    --card-color: #f0a900;
    --card-soft: rgba(246, 194, 62, 0.14);
}

.stat-approved {
    --card-color: #1cc88a;
    --card-soft: rgba(28, 200, 138, 0.12);
}


/* =========================================================
   SECTION CARDS
   ========================================================= */

.dashboard-card {
    background: #fff;

    border-radius: 18px;

    border: 1px solid rgba(6, 42, 90, 0.06);

    box-shadow: var(--dash-shadow);

    overflow: hidden;

    height: 100%;
}

.dashboard-card-header {
    display: flex;

    align-items: center;

    justify-content: space-between;

    padding: 20px 22px;

    border-bottom: 1px solid #edf0f5;

    background: #fff;
}

.dashboard-card-title {
    display: flex;

    align-items: center;

    gap: 10px;

    margin: 0;

    font-size: 15px;

    font-weight: 750;

    color: var(--dash-text);
}

.dashboard-card-title i {
    color: var(--nbte-gold);

    font-size: 16px;
}

.dashboard-card-body {
    padding: 22px;
}


/* =========================================================
   CHART
   ========================================================= */

.dashboard-chart-container {
    position: relative;

    width: 100%;

    height: 330px;
}


/* =========================================================
   STATUS CHART
   ========================================================= */

.status-chart-wrapper {
    position: relative;

    height: 270px;

    display: flex;

    align-items: center;

    justify-content: center;
}

.status-center {
    position: absolute;

    top: 50%;
    left: 50%;

    transform: translate(-50%, -55%);

    text-align: center;

    pointer-events: none;
}

.status-center-value {
    font-size: 27px;

    font-weight: 800;

    color: var(--nbte-navy);
}

.status-center-label {
    font-size: 11px;

    color: var(--dash-muted);

    font-weight: 600;
}


/* =========================================================
   APPROVAL OVERVIEW
   ========================================================= */

.approval-item {
    margin-bottom: 22px;
}

.approval-item:last-child {
    margin-bottom: 0;
}

.approval-top {
    display: flex;

    justify-content: space-between;

    align-items: center;

    margin-bottom: 8px;
}

.approval-name {
    display: flex;

    align-items: center;

    gap: 9px;

    font-size: 13px;

    font-weight: 650;

    color: var(--dash-text);
}

.approval-number {
    font-size: 13px;

    font-weight: 800;

    color: var(--dash-text);
}

.approval-dot {
    width: 9px;
    height: 9px;

    border-radius: 50%;
}

.approval-progress {
    height: 8px;

    background: #edf1f5;

    border-radius: 20px;

    overflow: hidden;
}

.approval-progress-bar {
    height: 100%;

    border-radius: 20px;

    transition: width 1s ease;
}

.approved-bar {
    background: var(--dash-green);
}

.pending-bar {
    background: var(--dash-orange);
}

.certificate-bar {
    background: var(--nbte-gold);
}


/* =========================================================
   QUICK OVERVIEW
   ========================================================= */

.overview-mini {
    display: flex;

    align-items: center;

    gap: 14px;

    padding: 14px;

    border-radius: 13px;

    background: #f8f9fc;

    margin-bottom: 12px;
}

.overview-mini:last-child {
    margin-bottom: 0;
}

.overview-mini-icon {
    width: 42px;
    height: 42px;

    flex-shrink: 0;

    border-radius: 11px;

    display: flex;

    align-items: center;
    justify-content: center;

    background: var(--nbte-gold-light);

    color: var(--nbte-gold);
}

.overview-mini-content {
    flex: 1;
}

.overview-mini-title {
    font-size: 12px;

    color: var(--dash-muted);

    margin-bottom: 2px;
}

.overview-mini-value {
    font-size: 17px;

    font-weight: 800;

    color: var(--dash-text);
}


/* =========================================================
   TABLE
   ========================================================= */

.recent-table {
    margin: 0;
}

.recent-table thead th {
    background: #f8f9fc;

    border-bottom: 1px solid #e9edf2;

    color: #657383;

    font-size: 11px;

    text-transform: uppercase;

    letter-spacing: 0.5px;

    font-weight: 750;

    padding: 14px 18px;

    white-space: nowrap;
}

.recent-table tbody td {
    padding: 14px 18px;

    vertical-align: middle;

    border-bottom: 1px solid #f0f2f5;

    font-size: 13px;

    color: #455463;
}

.recent-table tbody tr:last-child td {
    border-bottom: 0;
}

.recent-table tbody tr {
    transition: background 0.2s ease;
}

.recent-table tbody tr:hover {
    background: #fafbfd;
}

.student-info {
    display: flex;

    align-items: center;

    gap: 11px;
}

.student-photo {
    width: 38px;
    height: 38px;

    border-radius: 50%;

    object-fit: cover;

    border: 2px solid #f0e2c5;
}

.student-avatar {
    width: 38px;
    height: 38px;

    border-radius: 50%;

    background:
        linear-gradient(
            135deg,
            var(--nbte-navy),
            #14528d
        );

    color: #fff;

    display: flex;

    align-items: center;
    justify-content: center;

    font-weight: 750;

    font-size: 13px;

    flex-shrink: 0;
}

.student-name {
    font-weight: 700;

    color: var(--dash-text);

    white-space: nowrap;
}

.enrollment-code {
    display: inline-block;

    background: #f4f6f9;

    border-radius: 6px;

    padding: 5px 8px;

    font-size: 11px;

    color: var(--nbte-navy);

    font-weight: 650;
}

.status-badge {
    display: inline-flex;

    align-items: center;

    gap: 5px;

    border-radius: 30px;

    padding: 6px 10px;

    font-size: 11px;

    font-weight: 700;
}

.status-approved {
    background: rgba(28, 200, 138, 0.11);

    color: #149563;
}

.status-pending {
    background: rgba(246, 194, 62, 0.16);

    color: #a97900;
}


/* =========================================================
   VIEW ALL BUTTON
   ========================================================= */

.dashboard-view-btn {
    display: inline-flex;

    align-items: center;

    gap: 6px;

    border: 1px solid rgba(6, 42, 90, 0.15);

    color: var(--nbte-navy);

    background: #fff;

    padding: 7px 12px;

    border-radius: 8px;

    font-size: 11px;

    font-weight: 700;

    text-decoration: none;

    transition: all 0.2s ease;
}

.dashboard-view-btn:hover {
    background: var(--nbte-navy);

    color: #fff;

    border-color: var(--nbte-navy);
}


/* =========================================================
   EMPTY STATE
   ========================================================= */

.dashboard-empty {
    padding: 55px 20px;

    text-align: center;

    color: var(--dash-muted);
}

.dashboard-empty i {
    font-size: 42px;

    color: #dfe4ea;

    margin-bottom: 13px;
}

.dashboard-empty p {
    margin: 0;

    font-size: 13px;
}


/* =========================================================
   RESPONSIVE
   ========================================================= */

@media (max-width: 991.98px) {

    .dashboard-welcome {
        padding: 24px;
    }

    .dashboard-welcome-title {
        font-size: 24px;
    }

    .dashboard-time-box {
        margin-top: 18px;
    }

    .dashboard-chart-container {
        height: 290px;
    }
}

@media (max-width: 767.98px) {

    .dashboard-welcome {
        border-radius: 15px;

        padding: 21px;
    }

    .dashboard-welcome-title {
        font-size: 21px;
    }

    .premium-stat-card {
        padding: 19px;
    }

    .premium-stat-value {
        font-size: 26px;
    }

    .dashboard-card-header {
        padding: 17px;
    }

    .dashboard-card-body {
        padding: 17px;
    }

    .dashboard-chart-container {
        height: 260px;
    }

    .status-chart-wrapper {
        height: 250px;
    }
}

</style>


<div class="dashboard-page">
<!-- =====================================================
     WELCOME SECTION
     ===================================================== -->

<?php
$currentHour = (int) date('H');

if ($currentHour >= 5 && $currentHour < 12) {
    $greeting = 'Good Morning';
    $greetingEmoji = '🌅';
} elseif ($currentHour >= 12 && $currentHour < 17) {
    $greeting = 'Good Afternoon';
    $greetingEmoji = '☀️';
} elseif ($currentHour >= 17 && $currentHour < 21) {
    $greeting = 'Good Evening';
    $greetingEmoji = '🌇';
} else {
    $greeting = 'Good Night';
    $greetingEmoji = '🌙';
}
?>

<div class="dashboard-welcome">

    <div class="row align-items-center">

        <div class="col-lg-8">

            <div class="dashboard-welcome-content">

                <div class="dashboard-welcome-label">
                    <i class="fas fa-shield-alt"></i>
                    NBTE Management Portal
                </div>

                <h1 class="dashboard-welcome-title">

                    <span class="welcome-emoji">
                        <?php echo $greetingEmoji; ?>
                    </span>

                    <?php echo $greeting; ?>,
                    <?php echo sanitize($displayName); ?>!

                </h1>

                <p class="dashboard-welcome-text">
                    Welcome to your NBTE Management Portal.
                    <span class="happy-emoji">😊</span>
                </p>

            </div>

        </div>

        <div class="col-lg-4 text-lg-end">

            <div class="dashboard-time-box">

                <i class="fas fa-calendar-alt"></i>

                <span id="dashboardLiveDateTime">
                    Loading...
                </span>

            </div>

        </div>

    </div>

</div>


<!-- =====================================================
     HAPPY EMOJI ANIMATION
     ===================================================== -->

<style>

.welcome-emoji {
    display: inline-block;
    font-size: 1.05em;
    margin-right: 8px;
    transform-origin: center bottom;
    animation: happyEmoji 2.5s ease-in-out infinite;
}

.happy-emoji {
    display: inline-block;
    margin-left: 5px;
    animation: happySmile 2s ease-in-out infinite;
}


/* Main greeting emoji */
@keyframes happyEmoji {

    0% {
        transform: translateY(0) rotate(0deg) scale(1);
    }

    15% {
        transform: translateY(-6px) rotate(-8deg) scale(1.08);
    }

    30% {
        transform: translateY(0) rotate(8deg) scale(1.05);
    }

    45% {
        transform: translateY(-4px) rotate(-5deg) scale(1.03);
    }

    60% {
        transform: translateY(0) rotate(3deg) scale(1);
    }

    100% {
        transform: translateY(0) rotate(0deg) scale(1);
    }

}


/* Small happy face */
@keyframes happySmile {

    0%, 100% {
        transform: scale(1) rotate(0deg);
    }

    25% {
        transform: scale(1.2) rotate(-8deg);
    }

    50% {
        transform: scale(1.1) rotate(8deg);
    }

    75% {
        transform: scale(1.2) rotate(-5deg);
    }

}


/* Mobile */
@media (max-width: 576px) {

    .welcome-emoji {
        font-size: 0.95em;
        margin-right: 4px;
    }

}

</style>

    <!-- =====================================================
         STATISTICS
         ===================================================== -->

    <div class="row g-4 mb-4">

        <?php if ($isSuperAdmin): ?>

            <!-- TOTAL STUDENTS -->

            <div class="col-xl-3 col-md-6">

                <div class="premium-stat-card stat-students">

                    <div class="premium-stat-top">

                        <div>
                            <div class="premium-stat-label">
                                Total Students
                            </div>

                            <div class="premium-stat-value">
                                <?php echo number_format($totalStudents); ?>
                            </div>

                            <div class="premium-stat-line"></div>
                        </div>

                        <div class="premium-stat-icon">
                            <i class="fas fa-user-graduate"></i>
                        </div>

                    </div>

                </div>

            </div>


            <!-- TOTAL ADMINS -->

            <div class="col-xl-3 col-md-6">

                <div class="premium-stat-card stat-admins">

                    <div class="premium-stat-top">

                        <div>
                            <div class="premium-stat-label">
                                Total Admins
                            </div>

                            <div class="premium-stat-value">
                                <?php echo number_format($totalAdmins); ?>
                            </div>

                            <div class="premium-stat-line"></div>
                        </div>

                        <div class="premium-stat-icon">
                            <i class="fas fa-users-cog"></i>
                        </div>

                    </div>

                </div>

            </div>


            <!-- PROGRAMS -->

            <div class="col-xl-3 col-md-6">

                <div class="premium-stat-card stat-programs">

                    <div class="premium-stat-top">

                        <div>
                            <div class="premium-stat-label">
                                Total Programs
                            </div>

                            <div class="premium-stat-value">
                                <?php echo number_format($totalPrograms); ?>
                            </div>

                            <div class="premium-stat-line"></div>
                        </div>

                        <div class="premium-stat-icon">
                            <i class="fas fa-book-open"></i>
                        </div>

                    </div>

                </div>

            </div>


            <!-- CERTIFICATES -->

            <div class="col-xl-3 col-md-6">

                <div class="premium-stat-card stat-certificates">

                    <div class="premium-stat-top">

                        <div>
                            <div class="premium-stat-label">
                                Certificates
                            </div>

                            <div class="premium-stat-value">
                                <?php echo number_format($totalCerts); ?>
                            </div>

                            <div class="premium-stat-line"></div>
                        </div>

                        <div class="premium-stat-icon">
                            <i class="fas fa-certificate"></i>
                        </div>

                    </div>

                </div>

            </div>


        <?php else: ?>


            <!-- MY STUDENTS -->

            <div class="col-xl-3 col-md-6">

                <div class="premium-stat-card stat-students">

                    <div class="premium-stat-top">

                        <div>
                            <div class="premium-stat-label">
                                My Students
                            </div>

                            <div class="premium-stat-value">
                                <?php echo number_format($totalStudents); ?>
                            </div>

                            <div class="premium-stat-line"></div>
                        </div>

                        <div class="premium-stat-icon">
                            <i class="fas fa-user-graduate"></i>
                        </div>

                    </div>

                </div>

            </div>


            <!-- PENDING -->

            <div class="col-xl-3 col-md-6">

                <div class="premium-stat-card stat-pending">

                    <div class="premium-stat-top">

                        <div>
                            <div class="premium-stat-label">
                                Pending
                            </div>

                            <div class="premium-stat-value">
                                <?php echo number_format($totalPending); ?>
                            </div>

                            <div class="premium-stat-line"></div>
                        </div>

                        <div class="premium-stat-icon">
                            <i class="fas fa-clock"></i>
                        </div>

                    </div>

                </div>

            </div>


            <!-- APPROVED -->

            <div class="col-xl-3 col-md-6">

                <div class="premium-stat-card stat-approved">

                    <div class="premium-stat-top">

                        <div>
                            <div class="premium-stat-label">
                                Approved
                            </div>

                            <div class="premium-stat-value">
                                <?php echo number_format($totalApproved); ?>
                            </div>

                            <div class="premium-stat-line"></div>
                        </div>

                        <div class="premium-stat-icon">
                            <i class="fas fa-check-circle"></i>
                        </div>

                    </div>

                </div>

            </div>


            <!-- CERTIFICATES -->

            <div class="col-xl-3 col-md-6">

                <div class="premium-stat-card stat-certificates">

                    <div class="premium-stat-top">

                        <div>
                            <div class="premium-stat-label">
                                Certificates
                            </div>

                            <div class="premium-stat-value">
                                <?php echo number_format($totalCerts); ?>
                            </div>

                            <div class="premium-stat-line"></div>
                        </div>

                        <div class="premium-stat-icon">
                            <i class="fas fa-certificate"></i>
                        </div>

                    </div>

                </div>

            </div>

        <?php endif; ?>

    </div>


    <!-- =====================================================
         CHARTS
         ===================================================== -->

    <div class="row g-4 mb-4">

        <!-- REGISTRATION CHART -->

        <div class="col-xl-8">

            <div class="dashboard-card">

                <div class="dashboard-card-header">

                    <h6 class="dashboard-card-title">

                        <i class="fas fa-chart-line"></i>

                        Student Registrations

                    </h6>

                    <span class="text-muted small">
                        Last 12 Months
                    </span>

                </div>

                <div class="dashboard-card-body">

                    <div class="dashboard-chart-container">

                        <canvas id="monthlyChart"></canvas>

                    </div>

                </div>

            </div>

        </div>


        <!-- STATUS CHART -->

        <div class="col-xl-4">

            <div class="dashboard-card">

                <div class="dashboard-card-header">

                    <h6 class="dashboard-card-title">

                        <i class="fas fa-chart-pie"></i>

                        Student Status

                    </h6>

                    <span class="text-muted small">
                        Current
                    </span>

                </div>

                <div class="dashboard-card-body">

                    <div class="status-chart-wrapper">

                        <canvas id="statusChart"></canvas>

                        <div class="status-center">

                            <div class="status-center-value">
                                <?php echo number_format($totalStudents); ?>
                            </div>

                            <div class="status-center-label">
                                Students
                            </div>

                        </div>

                    </div>

                </div>

            </div>

        </div>

    </div>


    <!-- =====================================================
         APPROVAL OVERVIEW + SYSTEM SUMMARY
         ===================================================== -->

    <div class="row g-4 mb-4">

        <!-- APPROVAL OVERVIEW -->

        <div class="col-xl-7">

            <div class="dashboard-card">

                <div class="dashboard-card-header">

                    <h6 class="dashboard-card-title">

                        <i class="fas fa-tasks"></i>

                        Approval Overview

                    </h6>

                </div>

                <div class="dashboard-card-body">

                    <!-- APPROVED -->

                    <div class="approval-item">

                        <div class="approval-top">

                            <div class="approval-name">

                                <span
                                    class="approval-dot"
                                    style="background:#1cc88a;">
                                </span>

                                Approved Students

                            </div>

                            <div class="approval-number">

                                <?php echo number_format($totalApproved); ?>

                                <span class="text-muted">
                                    (<?php echo $approvedPercentage; ?>%)
                                </span>

                            </div>

                        </div>

                        <div class="approval-progress">

                            <div
                                class="approval-progress-bar approved-bar"
                                style="width:<?php echo $approvedPercentage; ?>%;">
                            </div>

                        </div>

                    </div>


                    <!-- PENDING -->

                    <div class="approval-item">

                        <div class="approval-top">

                            <div class="approval-name">

                                <span
                                    class="approval-dot"
                                    style="background:#f6c23e;">
                                </span>

                                Pending Students

                            </div>

                            <div class="approval-number">

                                <?php echo number_format($totalPending); ?>

                                <span class="text-muted">
                                    (<?php echo $pendingPercentage; ?>%)
                                </span>

                            </div>

                        </div>

                        <div class="approval-progress">

                            <div
                                class="approval-progress-bar pending-bar"
                                style="width:<?php echo $pendingPercentage; ?>%;">
                            </div>

                        </div>

                    </div>


                    <!-- CERTIFICATES -->

                    <div class="approval-item">

                        <div class="approval-top">

                            <div class="approval-name">

                                <span
                                    class="approval-dot"
                                    style="background:#D49729;">
                                </span>

                                Certificates Generated

                            </div>

                            <div class="approval-number">

                                <?php echo number_format($totalCerts); ?>

                            </div>

                        </div>

                        <div class="approval-progress">

                            <?php

                            $certificatePercentage = 0;

                            if ($totalStudents > 0) {
                                $certificatePercentage = min(
                                    100,
                                    round(
                                        ($totalCerts / $totalStudents) * 100
                                    )
                                );
                            }

                            ?>

                            <div
                                class="approval-progress-bar certificate-bar"
                                style="width:<?php echo $certificatePercentage; ?>%;">
                            </div>

                        </div>

                    </div>

                </div>

            </div>

        </div>


        <!-- SYSTEM SUMMARY -->

        <div class="col-xl-5">

            <div class="dashboard-card">

                <div class="dashboard-card-header">

                    <h6 class="dashboard-card-title">

                        <i class="fas fa-chart-bar"></i>

                        <?php echo $isSuperAdmin ? 'System Summary' : 'My Summary'; ?>

                    </h6>

                </div>

                <div class="dashboard-card-body">

                    <!-- STUDENTS -->

                    <div class="overview-mini">

                        <div class="overview-mini-icon">

                            <i class="fas fa-user-graduate"></i>

                        </div>

                        <div class="overview-mini-content">

                            <div class="overview-mini-title">
                                <?php echo $isSuperAdmin ? 'Total Students' : 'My Students'; ?>
                            </div>

                            <div class="overview-mini-value">
                                <?php echo number_format($totalStudents); ?>
                            </div>

                        </div>

                    </div>


                    <!-- APPROVED -->

                    <div class="overview-mini">

                        <div
                            class="overview-mini-icon"
                            style="
                                background:rgba(28,200,138,0.10);
                                color:#1cc88a;
                            ">

                            <i class="fas fa-check-circle"></i>

                        </div>

                        <div class="overview-mini-content">

                            <div class="overview-mini-title">
                                Approved Students
                            </div>

                            <div class="overview-mini-value">
                                <?php echo number_format($totalApproved); ?>
                            </div>

                        </div>

                    </div>


                    <!-- PENDING -->

                    <div class="overview-mini">

                        <div
                            class="overview-mini-icon"
                            style="
                                background:rgba(246,194,62,0.14);
                                color:#c28b00;
                            ">

                            <i class="fas fa-clock"></i>

                        </div>

                        <div class="overview-mini-content">

                            <div class="overview-mini-title">
                                Pending Students
                            </div>

                            <div class="overview-mini-value">
                                <?php echo number_format($totalPending); ?>
                            </div>

                        </div>

                    </div>


                    <!-- CERTIFICATES -->

                    <div class="overview-mini">

                        <div
                            class="overview-mini-icon"
                            style="
                                background:rgba(212,151,41,0.13);
                                color:#D49729;
                            ">

                            <i class="fas fa-certificate"></i>

                        </div>

                        <div class="overview-mini-content">

                            <div class="overview-mini-title">
                                Certificates
                            </div>

                            <div class="overview-mini-value">
                                <?php echo number_format($totalCerts); ?>
                            </div>

                        </div>

                    </div>

                </div>

            </div>

        </div>

    </div>


    <!-- =====================================================
         RECENT STUDENTS
         ===================================================== -->

    <div class="dashboard-card mb-4">

        <div class="dashboard-card-header">

            <h6 class="dashboard-card-title">

                <i class="fas fa-users"></i>

                Recent Students

            </h6>

            <a
                href="students.php"
                class="dashboard-view-btn">

                View All

                <i class="fas fa-arrow-right"></i>

            </a>

        </div>


        <div class="dashboard-card-body p-0">

            <?php if (empty($recentStudents)): ?>

                <div class="dashboard-empty">

                    <i class="fas fa-users"></i>

                    <p>
                        No students found
                    </p>

                </div>

            <?php else: ?>

                <div class="table-responsive">

                    <table class="table recent-table mb-0">

                        <thead>

                            <tr>

                                <th>
                                    Student
                                </th>

                                <th>
                                    Enrollment
                                </th>

                                <th>
                                    Program
                                </th>

                                <?php if ($isSuperAdmin): ?>

                                    <th>
                                        Admin
                                    </th>

                                <?php endif; ?>

                                <th>
                                    Status
                                </th>

                                <th>
                                    Date
                                </th>

                            </tr>

                        </thead>


                        <tbody>

                            <?php foreach ($recentStudents as $student): ?>

                                <tr>

                                    <!-- STUDENT -->

                                    <td>

                                        <div class="student-info">

                                            <?php if (!empty($student['photo'])): ?>

                                                <img
                                                    src="uploads/photos/<?php echo sanitize($student['photo']); ?>"
                                                    alt="Student"
                                                    class="student-photo"
                                                >

                                            <?php else: ?>

                                                <div class="student-avatar">

                                                    <?php
                                                    echo strtoupper(
                                                        substr(
                                                            $student['full_name'] ?? 'S',
                                                            0,
                                                            1
                                                        )
                                                    );
                                                    ?>

                                                </div>

                                            <?php endif; ?>


                                            <div>

                                                <div class="student-name">

                                                    <?php
                                                    echo sanitize(
                                                        $student['full_name'] ?? ''
                                                    );
                                                    ?>

                                                </div>

                                            </div>

                                        </div>

                                    </td>


                                    <!-- ENROLLMENT -->

                                    <td>

                                        <span class="enrollment-code">

                                            <?php
                                            echo sanitize(
                                                $student['enrollment_no'] ?? ''
                                            );
                                            ?>

                                        </span>

                                    </td>


                                    <!-- PROGRAM -->

                                    <td>

                                        <?php
                                        echo sanitize(
                                            $student['program_name'] ?? ''
                                        );
                                        ?>

                                    </td>


                                    <!-- ADMIN -->

                                    <?php if ($isSuperAdmin): ?>

                                        <td>

                                            <?php
                                            echo sanitize(
                                                $student['admin_name'] ?? ''
                                            );
                                            ?>

                                        </td>

                                    <?php endif; ?>


                                    <!-- STATUS -->

                                    <td>

                                        <?php
                                        $studentStatus =
                                            $student['status'] ?? 'Pending';
                                        ?>

                                        <?php if ($studentStatus === 'Approved'): ?>

                                            <span class="status-badge status-approved">

                                                <i class="fas fa-check-circle"></i>

                                                Approved

                                            </span>

                                        <?php else: ?>

                                            <span class="status-badge status-pending">

                                                <i class="fas fa-clock"></i>

                                                Pending

                                            </span>

                                        <?php endif; ?>

                                    </td>


                                    <!-- DATE -->

                                    <td>

                                        <?php

                                        if (!empty($student['created_at'])) {

                                            echo date(
                                                'd M Y',
                                                strtotime(
                                                    $student['created_at']
                                                )
                                            );

                                        } else {

                                            echo '-';

                                        }

                                        ?>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            <?php endif; ?>

        </div>

    </div>

</div>


<!-- =========================================================
     JAVASCRIPT
     ========================================================= -->

<script>

document.addEventListener('DOMContentLoaded', function () {


    /* =====================================================
       LIVE DATE / TIME
       ===================================================== */

    function updateDashboardDateTime() {

        const element =
            document.getElementById('dashboardLiveDateTime');

        if (!element) {
            return;
        }

        const now = new Date();

        const formatted = now.toLocaleString('en-IN', {

            day: '2-digit',

            month: 'short',

            year: 'numeric',

            hour: '2-digit',

            minute: '2-digit',

            second: '2-digit',

            hour12: true

        });

        element.innerHTML = formatted;

    }

    updateDashboardDateTime();

    setInterval(updateDashboardDateTime, 1000);



    /* =====================================================
       CHART DEFAULTS
       ===================================================== */

    if (typeof Chart !== 'undefined') {

        Chart.defaults.font.family =
            'Inter, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif';

        Chart.defaults.color = '#7b8794';

    }


    /* =====================================================
       MONTHLY STUDENT REGISTRATION CHART
       ===================================================== */

    const monthlyCanvas =
        document.getElementById('monthlyChart');

    if (
        monthlyCanvas &&
        typeof Chart !== 'undefined'
    ) {

        new Chart(monthlyCanvas, {

            type: 'line',

            data: {

                labels:
                    <?php echo json_encode($chartLabels); ?>,

                datasets: [

                    {

                        label: 'Students Registered',

                        data:
                            <?php echo json_encode($chartValues); ?>,

                        borderColor: '#062A5A',

                        backgroundColor:
                            'rgba(6, 42, 90, 0.08)',

                        fill: true,

                        tension: 0.42,

                        borderWidth: 3,

                        pointBackgroundColor:
                            '#D49729',

                        pointBorderColor:
                            '#ffffff',

                        pointBorderWidth: 2,

                        pointRadius: 5,

                        pointHoverRadius: 7

                    }

                ]

            },


            options: {

                responsive: true,

                maintainAspectRatio: false,

                interaction: {

                    intersect: false,

                    mode: 'index'

                },


                plugins: {

                    legend: {

                        display: false

                    },


                    tooltip: {

                        backgroundColor: '#062A5A',

                        titleColor: '#fff',

                        bodyColor: '#fff',

                        padding: 12,

                        displayColors: false,

                        cornerRadius: 9

                    }

                },


                scales: {

                    x: {

                        grid: {

                            display: false

                        },

                        border: {

                            display: false

                        },

                        ticks: {

                            font: {

                                size: 11

                            }

                        }

                    },


                    y: {

                        beginAtZero: true,

                        border: {

                            display: false

                        },

                        grid: {

                            color:
                                'rgba(6,42,90,0.06)'

                        },

                        ticks: {

                            precision: 0,

                            font: {

                                size: 11

                            }

                        }

                    }

                }

            }

        });

    }


    /* =====================================================
       STUDENT STATUS DOUGHNUT
       ===================================================== */

    const statusCanvas =
        document.getElementById('statusChart');

    if (
        statusCanvas &&
        typeof Chart !== 'undefined'
    ) {

        new Chart(statusCanvas, {

            type: 'doughnut',

            data: {

                labels: [
                    'Approved',
                    'Pending'
                ],

                datasets: [

                    {

                        data: [

                            <?php echo $totalApproved; ?>,

                            <?php echo $totalPending; ?>

                        ],

                        backgroundColor: [

                            '#1cc88a',

                            '#f6c23e'

                        ],

                        borderWidth: 0,

                        hoverOffset: 8

                    }

                ]

            },


            options: {

                responsive: true,

                maintainAspectRatio: false,

                cutout: '72%',


                plugins: {

                    legend: {

                        position: 'bottom',

                        labels: {

                            padding: 18,

                            usePointStyle: true,

                            pointStyle: 'circle',

                            font: {

                                size: 11,

                                weight: '600'

                            }

                        }

                    },


                    tooltip: {

                        backgroundColor: '#062A5A',

                        padding: 11,

                        cornerRadius: 9

                    }

                }

            }

        });

    }

});

</script>


<?php require_once 'includes/footer.php'; ?>