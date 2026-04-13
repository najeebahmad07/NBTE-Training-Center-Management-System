<?php
/**
 * RISE - Sidebar Include
 * ========================
 */

// Get unread notification count for current user
$unreadNotifCount = 0;
if (isLoggedIn()) {
    $db = getDB();
    $notifStmt = $db->prepare("SELECT COUNT(*) FROM notifications WHERE to_admin = :id AND is_read = 0");
    $notifStmt->execute([':id' => getCurrentUserId()]);
    $unreadNotifCount = (int) $notifStmt->fetchColumn();
}
?>
<!-- Sidebar -->
<nav id="sidebar" class="sidebar">
    <!-- Brand -->
    <div class="sidebar-brand">
        <div>
            <div class="brand-text"><?php echo APP_NAME; ?></div>
            <div class="brand-tagline"><?php echo APP_TAGLINE; ?></div>
        </div>
    </div>

    <!-- Navigation -->
    <ul class="sidebar-nav">
        <!-- Dashboard -->
        <li class="nav-item">
            <a href="dashboard.php" class="nav-link <?php echo $currentPage === 'dashboard' ? 'active' : ''; ?>">
                <i class="fas fa-tachometer-alt"></i>
                <span class="nav-text">Dashboard</span>
            </a>
        </li>

        <?php if (isSuperAdmin()): ?>
        <!-- Super Admin Menu -->
        <div class="sidebar-section-title">Management</div>

        <li class="nav-item">
            <a href="admin_management.php" class="nav-link <?php echo $currentPage === 'admin_management' ? 'active' : ''; ?>">
                <i class="fas fa-users-cog"></i>
                <span class="nav-text">Admin Management</span>
            </a>
        </li>

        <li class="nav-item">
            <a href="program_management.php" class="nav-link <?php echo $currentPage === 'program_management' ? 'active' : ''; ?>">
                <i class="fas fa-graduation-cap"></i>
                <span class="nav-text">Programs</span>
            </a>
        </li>

        <li class="nav-item">
            <a href="course_management.php" class="nav-link <?php echo $currentPage === 'course_management' ? 'active' : ''; ?>">
                <i class="fas fa-book"></i>
                <span class="nav-text">Courses</span>
            </a>
        </li>

        <li class="nav-item">
            <a href="subject_management.php" class="nav-link <?php echo $currentPage === 'subject_management' ? 'active' : ''; ?>">
                <i class="fas fa-clipboard-list"></i>
                <span class="nav-text">Subjects</span>
            </a>
        </li>
        <?php endif; ?>

        <!-- Student Section -->
        <div class="sidebar-section-title">Students</div>

        <li class="nav-item">
            <a href="students.php" class="nav-link <?php echo $currentPage === 'students' ? 'active' : ''; ?>">
                <i class="fas fa-user-graduate"></i>
                <span class="nav-text">All Students</span>
            </a>
        </li>

        <?php if (!isSuperAdmin()): ?>
        <li class="nav-item">
            <a href="add_student.php" class="nav-link <?php echo $currentPage === 'add_student' ? 'active' : ''; ?>">
                <i class="fas fa-user-plus"></i>
                <span class="nav-text">Add Student</span>
            </a>
        </li>
        <?php endif; ?>

        <li class="nav-item">
            <a href="marks_entry.php" class="nav-link <?php echo $currentPage === 'marks_entry' ? 'active' : ''; ?>">
                <i class="fas fa-pen-alt"></i>
                <span class="nav-text">Marks Entry</span>
            </a>
        </li>

        <!-- Notifications -->
        <div class="sidebar-section-title">Notifications</div>

        <li class="nav-item">
            <a href="notifications.php" class="nav-link <?php echo $currentPage === 'notifications' ? 'active' : ''; ?> d-flex align-items-center justify-content-between">
                <span>
                    <i class="fas fa-bell"></i>
                    <span class="nav-text">
                        <?php echo isSuperAdmin() ? 'Certificate Requests' : 'My Notifications'; ?>
                    </span>
                </span>
                <?php if ($unreadNotifCount > 0): ?>
                <span class="badge bg-danger rounded-pill ms-1"><?php echo $unreadNotifCount; ?></span>
                <?php endif; ?>
            </a>
        </li>

        <!-- Finance -->
        <?php if (!isSuperAdmin()): ?>
        <div class="sidebar-section-title">Finance</div>

        <li class="nav-item">
            <a href="wallet.php" class="nav-link <?php echo $currentPage === 'wallet' ? 'active' : ''; ?>">
                <i class="fas fa-wallet"></i>
                <span class="nav-text">Wallet</span>
            </a>
        </li>
        <?php else: ?>
        <div class="sidebar-section-title">Finance</div>
        <li class="nav-item">
            <a href="wallet.php" class="nav-link <?php echo $currentPage === 'wallet' ? 'active' : ''; ?>">
                <i class="fas fa-wallet"></i>
                <span class="nav-text">All Transactions</span>
            </a>
        </li>
        <?php endif; ?>

        <?php if (isSuperAdmin()): ?>
        <li class="nav-item">
            <a href="change_password.php" class="nav-link <?php echo $currentPage === 'change_password' ? 'active' : ''; ?>">
                <i class="fas fa-key"></i>
                <span class="nav-text">Change Password</span>
            </a>
        </li>
        <?php endif; ?>


        
        <!-- Tools -->
        <div class="sidebar-section-title">Tools</div>
        <?php if (isSuperAdmin()): ?>
<li class="nav-item">
    <a href="add_message.php" class="nav-link">
        <i class="fas fa-bullhorn"></i>
        <span class="nav-text">Send Message</span>
    </a>
</li>
<?php endif; ?>
        <li class="nav-item">
            <a href="generate_admin_certificate.php" target="_blank" class="nav-link">
                <i class="fas fa-certificate"></i>
                <span class="nav-text">View Authorized Certificate</span>
            </a>
        </li>

        <li class="nav-item">
            <a href="verify_student.php" class="nav-link <?php echo $currentPage === 'verify_student' ? 'active' : ''; ?>">
                <i class="fas fa-search"></i>
                <span class="nav-text">Verify Student</span>
            </a>
        </li>

        <!-- Logout -->
        <div class="sidebar-section-title">Account</div>
        <li class="nav-item">
            <a href="logout.php" class="nav-link text-danger">
                <i class="fas fa-sign-out-alt"></i>
                <span class="nav-text">Logout</span>
            </a>
        </li>
    </ul>
</nav>

<!-- Sidebar Overlay for mobile -->
<div id="sidebarOverlay" class="sidebar-overlay"></div>

<!-- Main Content Wrapper -->
<div class="main-content">
    <!-- Top Navbar -->
    <div class="top-navbar">
        <div class="navbar-left">
            <button class="sidebar-toggle" id="sidebarToggle">
                <i class="fas fa-bars"></i>
            </button>
            <span class="page-title"><?php echo isset($pageTitle) ? sanitize($pageTitle) : 'Dashboard'; ?></span>
        </div>

        <div class="navbar-right">
            <?php if (!isSuperAdmin() && $currentUser): ?>
            <div class="d-none d-md-flex align-items-center me-2">
                <i class="fas fa-wallet text-success me-1"></i>
                <span class="fw-bold"><?php echo CURRENCY_SYMBOL . number_format($currentUser['wallet_balance'], 2); ?></span>
            </div>
            <?php endif; ?>

            <!-- Notification Bell (Top Navbar) -->
            <a href="notifications.php" class="btn btn-sm btn-outline-secondary position-relative me-2" title="Notifications">
                <i class="fas fa-bell"></i>
                <?php if ($unreadNotifCount > 0): ?>
                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size:0.6rem;">
                    <?php echo $unreadNotifCount; ?>
                </span>
                <?php endif; ?>
            </a>

            <!-- Theme Toggle -->
            <button class="theme-toggle" id="themeToggleBtn" title="Toggle Theme">
                <i class="fas fa-moon"></i>
            </button>

            <!-- User Dropdown -->
            <div class="dropdown user-dropdown">
                <button class="dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <div class="user-avatar">
                        <?php echo strtoupper(substr($currentUser['name'] ?? 'U', 0, 1)); ?>
                    </div>
                    <div class="user-info">
                        <div class="user-name"><?php echo sanitize($currentUser['name'] ?? 'User'); ?></div>
                        <div class="user-role"><?php echo sanitize(str_replace('_', ' ', $currentUser['role'] ?? '')); ?></div>
                    </div>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><span class="dropdown-item-text fw-bold"><?php echo sanitize($currentUser['email'] ?? ''); ?></span></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="dashboard.php"><i class="fas fa-tachometer-alt me-2"></i>Dashboard</a></li>
                    <?php if (!isSuperAdmin()): ?>
                    <li><a class="dropdown-item" href="wallet.php"><i class="fas fa-wallet me-2"></i>Wallet</a></li>
                    <?php endif; ?>
                    <li><a class="dropdown-item" href="notifications.php">
                        <i class="fas fa-bell me-2"></i>Notifications
                        <?php if ($unreadNotifCount > 0): ?>
                        <span class="badge bg-danger ms-1"><?php echo $unreadNotifCount; ?></span>
                        <?php endif; ?>
                    </a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item text-danger" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Content Area -->
    <div class="content-area">

    <?php
    // Flash Messages
    $flashSuccess = getFlashMessage('success');
    $flashError   = getFlashMessage('error');
    $flashWarning = getFlashMessage('warning');
    ?>

    <?php if ($flashSuccess): ?>
    <div class="alert alert-success alert-dismissible fade show alert-auto-dismiss" role="alert">
        <i class="fas fa-check-circle me-2"></i><?php echo $flashSuccess; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <?php if ($flashError): ?>
    <div class="alert alert-danger alert-dismissible fade show alert-auto-dismiss" role="alert">
        <i class="fas fa-exclamation-circle me-2"></i><?php echo $flashError; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <?php if ($flashWarning): ?>
    <div class="alert alert-warning alert-dismissible fade show alert-auto-dismiss" role="alert">
        <i class="fas fa-exclamation-triangle me-2"></i><?php echo $flashWarning; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>