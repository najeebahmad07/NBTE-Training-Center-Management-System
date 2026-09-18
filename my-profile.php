<?php
/**
 * RISE SaaS - My Profile
 * ----------------------
 * Admin can view ONLY their own profile.
 * Profile is READ-ONLY.
 */

require_once 'includes/auth.php';
require_once 'includes/db.php';

requireLogin();

/*
 * This page is for normal Admin users only.
 * Super Admin has separate management access.
 */
if (($_SESSION['user_role'] ?? '') !== 'admin') {
    header('Location: dashboard.php');
    exit;
}

$db = getDB();
$adminId = (int)($_SESSION['user_id'] ?? 0);

if ($adminId <= 0) {
    header('Location: login.php');
    exit;
}

/* ============================================================
   FETCH LOGGED-IN ADMIN ONLY
   ============================================================ */

$stmt = $db->prepare("
    SELECT *
    FROM admins
    WHERE id = :id
      AND role = 'admin'
    LIMIT 1
");

$stmt->execute([
    ':id' => $adminId
]);

$admin = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$admin) {
    setFlashMessage('error', 'Profile not found.');
    header('Location: dashboard.php');
    exit;
}

/* ============================================================
   FETCH ASSIGNED PROGRAMS
   ============================================================ */

$assignedPrograms = [];

try {
    $programStmt = $db->prepare("
        SELECT p.*
        FROM programs p
        INNER JOIN admin_programs ap
            ON ap.program_id = p.id
        WHERE ap.admin_id = :admin_id
        ORDER BY p.program_name ASC
    ");

    $programStmt->execute([
        ':admin_id' => $adminId
    ]);

    $assignedPrograms = $programStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $assignedPrograms = [];
}

/* ============================================================
   HELPERS
   ============================================================ */

function profileValue($value, $default = 'Not Provided')
{
    $value = trim((string)$value);

    return $value !== ''
        ? htmlspecialchars($value, ENT_QUOTES, 'UTF-8')
        : $default;
}

function adminFileExists($filename)
{
    if (empty($filename)) {
        return false;
    }

    return file_exists(
        __DIR__ . '/uploads/admin/' . basename($filename)
    );
}

function adminFileUrl($filename)
{
    if (empty($filename)) {
        return '#';
    }

    return 'uploads/admin/' . rawurlencode(basename($filename));
}

function isImageFile($filename)
{
    if (empty($filename)) {
        return false;
    }

    $extension = strtolower(
        pathinfo($filename, PATHINFO_EXTENSION)
    );

    return in_array(
        $extension,
        ['jpg', 'jpeg', 'png', 'webp'],
        true
    );
}

$pageTitle = 'My Profile';

require_once 'includes/header.php';
require_once 'includes/sidebar.php';
?>

<style>
    .profile-page {
        padding-bottom: 30px;
    }

    .profile-card {
        border: 1px solid #e5e7eb;
        border-radius: 12px;
        background: #fff;
        overflow: hidden;
        box-shadow: 0 2px 10px rgba(0, 0, 0, .04);
    }

    .profile-header {
        padding: 24px;
        border-bottom: 1px solid #e5e7eb;
        background: #fafafa;
    }

    .profile-avatar {
        width: 100px;
        height: 100px;
        border-radius: 12px;
        object-fit: cover;
        border: 1px solid #dee2e6;
        background: #f5f5f5;
    }

    .profile-avatar-placeholder {
        width: 100px;
        height: 100px;
        border-radius: 12px;
        border: 1px solid #dee2e6;
        background: #f5f5f5;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #777;
        font-size: 38px;
    }

    .profile-name {
        font-size: 24px;
        font-weight: 700;
        margin-bottom: 4px;
        color: #212529;
    }

    .profile-section {
        padding: 22px;
        border-bottom: 1px solid #eee;
    }

    .profile-section:last-child {
        border-bottom: 0;
    }

    .profile-section-title {
        font-size: 16px;
        font-weight: 700;
        margin-bottom: 18px;
        color: #212529;
    }

    .profile-section-title i {
        margin-right: 8px;
    }

    .profile-item {
        margin-bottom: 16px;
    }

    .profile-item:last-child {
        margin-bottom: 0;
    }

    .profile-label {
        display: block;
        font-size: 12px;
        font-weight: 600;
        color: #6c757d;
        margin-bottom: 4px;
        text-transform: uppercase;
        letter-spacing: .03em;
    }

    .profile-value {
        color: #212529;
        font-size: 14px;
        word-break: break-word;
    }

    .document-box {
        border: 1px solid #e5e7eb;
        border-radius: 10px;
        padding: 14px;
        height: 100%;
        background: #fff;
    }

    .document-preview {
        width: 100%;
        height: 150px;
        object-fit: contain;
        border-radius: 8px;
        background: #f8f9fa;
        border: 1px solid #eee;
        margin-bottom: 10px;
    }

    .document-icon {
        width: 100%;
        height: 150px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 8px;
        background: #f8f9fa;
        border: 1px solid #eee;
        margin-bottom: 10px;
        font-size: 42px;
        color: #6c757d;
    }

    .readonly-note {
        font-size: 13px;
        color: #6c757d;
    }

    .program-badge {
        display: inline-block;
        padding: 7px 12px;
        border: 1px solid #dee2e6;
        border-radius: 20px;
        background: #f8f9fa;
        color: #343a40;
        margin: 0 6px 6px 0;
        font-size: 13px;
    }

    @media (max-width: 576px) {
        .profile-header {
            padding: 18px;
        }

        .profile-section {
            padding: 18px;
        }

        .profile-name {
            font-size: 20px;
        }
    }
</style>

<div class="container-fluid profile-page">

    <!-- Page Heading -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1">My Profile</h4>
            <div class="text-muted small">
                View your registered institute and personal information
            </div>
        </div>

        <span class="badge bg-secondary">
            <i class="fas fa-lock me-1"></i> Read Only
        </span>
    </div>

    <!-- ========================================================
         PROFILE HEADER
    ========================================================= -->
    <div class="profile-card mb-4">
        <div class="profile-header">

            <div class="d-flex align-items-center gap-3">

                <?php if (
                    !empty($admin['passport_pic']) &&
                    adminFileExists($admin['passport_pic']) &&
                    isImageFile($admin['passport_pic'])
                ): ?>

                    <img
                        src="<?php echo htmlspecialchars(adminFileUrl($admin['passport_pic']), ENT_QUOTES, 'UTF-8'); ?>"
                        class="profile-avatar"
                        alt="Profile Photo"
                    >

                <?php else: ?>

                    <div class="profile-avatar-placeholder">
                        <i class="fas fa-user"></i>
                    </div>

                <?php endif; ?>

                <div>
                    <div class="profile-name">
                        <?php echo profileValue($admin['name']); ?>
                    </div>

                    <div class="text-muted mb-1">
                        <?php echo profileValue($admin['designation'], 'Admin'); ?>
                    </div>

                    <div class="small text-muted">
                        <i class="fas fa-envelope me-1"></i>
                        <?php echo profileValue($admin['email']); ?>
                    </div>

                    <div class="mt-2">
                        <?php if (($admin['status'] ?? '') === 'active'): ?>
                            <span class="badge bg-success">Active</span>
                        <?php else: ?>
                            <span class="badge bg-secondary">
                                <?php echo profileValue($admin['status']); ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>

            </div>

        </div>

        <!-- ====================================================
             BASIC ACCOUNT INFORMATION
        ===================================================== -->
        <div class="profile-section">

            <div class="profile-section-title">
                <i class="fas fa-user-circle"></i>
                Account Information
            </div>

            <div class="row">

                <div class="col-md-4 profile-item">
                    <span class="profile-label">Admin ID</span>
                    <div class="profile-value">
                        <?php echo (int)$admin['id']; ?>
                    </div>
                </div>

                <div class="col-md-4 profile-item">
                    <span class="profile-label">Full Name</span>
                    <div class="profile-value">
                        <?php echo profileValue($admin['name']); ?>
                    </div>
                </div>

                <div class="col-md-4 profile-item">
                    <span class="profile-label">Email</span>
                    <div class="profile-value">
                        <?php echo profileValue($admin['email']); ?>
                    </div>
                </div>

                <div class="col-md-4 profile-item">
                    <span class="profile-label">Phone Number</span>
                    <div class="profile-value">
                        <?php echo profileValue($admin['phone_number']); ?>
                    </div>
                </div>

                <div class="col-md-4 profile-item">
                    <span class="profile-label">Designation</span>
                    <div class="profile-value">
                        <?php echo profileValue($admin['designation']); ?>
                    </div>
                </div>

                <div class="col-md-4 profile-item">
                    <span class="profile-label">Role</span>
                    <div class="profile-value">
                        <?php echo profileValue($admin['role']); ?>
                    </div>
                </div>

            </div>

        </div>

        <!-- ====================================================
             QUALIFICATION
        ===================================================== -->
        <div class="profile-section">

            <div class="profile-section-title">
                <i class="fas fa-graduation-cap"></i>
                Qualification & Experience
            </div>

            <div class="row">

                <div class="col-md-4 profile-item">
                    <span class="profile-label">Last Qualification</span>
                    <div class="profile-value">
                        <?php echo profileValue($admin['last_qualification']); ?>
                    </div>
                </div>

                <div class="col-md-4 profile-item">
                    <span class="profile-label">Qualification</span>
                    <div class="profile-value">
                        <?php echo profileValue($admin['qualification']); ?>
                    </div>
                </div>

                <div class="col-md-4 profile-item">
                    <span class="profile-label">Work Experience</span>
                    <div class="profile-value">
                        <?php echo profileValue($admin['work_exp']); ?>
                    </div>
                </div>

            </div>

        </div>

        <!-- ====================================================
             PERSONAL INFORMATION
        ===================================================== -->
        <div class="profile-section">

            <div class="profile-section-title">
                <i class="fas fa-address-card"></i>
                Personal Information
            </div>

            <div class="row">

                <div class="col-md-6 profile-item">
                    <span class="profile-label">Aadhaar Number</span>
                    <div class="profile-value">
                        <?php echo profileValue($admin['aadhaar_number']); ?>
                    </div>
                </div>

                <div class="col-md-6 profile-item">
                    <span class="profile-label">PAN Card Number</span>
                    <div class="profile-value">
                        <?php echo profileValue($admin['pan_card_number']); ?>
                    </div>
                </div>

                <div class="col-md-8 profile-item">
                    <span class="profile-label">Permanent Address</span>
                    <div class="profile-value">
                        <?php echo nl2br(profileValue($admin['permanent_address'])); ?>
                    </div>
                </div>

                <div class="col-md-2 profile-item">
                    <span class="profile-label">State</span>
                    <div class="profile-value">
                        <?php echo profileValue($admin['personal_state']); ?>
                    </div>
                </div>

                <div class="col-md-2 profile-item">
                    <span class="profile-label">PIN</span>
                    <div class="profile-value">
                        <?php echo profileValue($admin['personal_pin']); ?>
                    </div>
                </div>

            </div>

        </div>

        <!-- ====================================================
             FACULTY & DEPARTMENTS
        ===================================================== -->
        <div class="profile-section">

            <div class="profile-section-title">
                <i class="fas fa-users"></i>
                Faculty & Departments
            </div>

            <div class="row">

                <div class="col-md-12 profile-item">
                    <span class="profile-label">Faculty</span>
                    <div class="profile-value">
                        <?php echo nl2br(profileValue($admin['faculty'])); ?>
                    </div>
                </div>

                <div class="col-md-6 profile-item">
                    <span class="profile-label">Department of IT Computer</span>
                    <div class="profile-value">
                        <?php if (!empty($admin['department_it_computer'])): ?>
                            <span class="badge bg-success">Available</span>
                        <?php else: ?>
                            <span class="text-muted">Not Available</span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="col-md-6 profile-item">
                    <span class="profile-label">Department of Fire Safety</span>
                    <div class="profile-value">
                        <?php if (!empty($admin['department_fire_safety'])): ?>
                            <span class="badge bg-success">Available</span>
                        <?php else: ?>
                            <span class="text-muted">Not Available</span>
                        <?php endif; ?>
                    </div>
                </div>

            </div>

        </div>

        <!-- ====================================================
             INSTITUTE INFORMATION
        ===================================================== -->
        <div class="profile-section">

            <div class="profile-section-title">
                <i class="fas fa-building"></i>
                Institute / Center Information
            </div>

            <div class="row">

                <div class="col-md-6 profile-item">
                    <span class="profile-label">College / Center Name</span>
                    <div class="profile-value">
                        <?php echo profileValue($admin['college_name']); ?>
                    </div>
                </div>

                <div class="col-md-6 profile-item">
                    <span class="profile-label">Institute Name</span>
                    <div class="profile-value">
                        <?php echo profileValue($admin['institute_name']); ?>
                    </div>
                </div>

                <div class="col-md-8 profile-item">
                    <span class="profile-label">Address</span>
                    <div class="profile-value">
                        <?php echo nl2br(profileValue($admin['institute_address'])); ?>
                    </div>
                </div>

                <div class="col-md-4 profile-item">
                    <span class="profile-label">Phone</span>
                    <div class="profile-value">
                        <?php echo profileValue($admin['institute_phone']); ?>
                    </div>
                </div>

                <div class="col-md-3 profile-item">
                    <span class="profile-label">State</span>
                    <div class="profile-value">
                        <?php echo profileValue($admin['institute_state']); ?>
                    </div>
                </div>

                <div class="col-md-3 profile-item">
                    <span class="profile-label">District</span>
                    <div class="profile-value">
                        <?php echo profileValue($admin['district']); ?>
                    </div>
                </div>

                <div class="col-md-3 profile-item">
                    <span class="profile-label">City</span>
                    <div class="profile-value">
                        <?php echo profileValue($admin['city']); ?>
                    </div>
                </div>

                <div class="col-md-3 profile-item">
                    <span class="profile-label">Pin Code</span>
                    <div class="profile-value">
                        <?php echo profileValue($admin['institute_pin']); ?>
                    </div>
                </div>

                <div class="col-md-6 profile-item">
                    <span class="profile-label">Website</span>
                    <div class="profile-value">
                        <?php if (!empty($admin['website'])): ?>
                            <a href="<?php echo htmlspecialchars($admin['website'], ENT_QUOTES, 'UTF-8'); ?>"
                               target="_blank"
                               rel="noopener noreferrer">
                                <?php echo profileValue($admin['website']); ?>
                            </a>
                        <?php else: ?>
                            Not Provided
                        <?php endif; ?>
                    </div>
                </div>

            </div>

        </div>

        <!-- ====================================================
             REGISTRATION
        ===================================================== -->
        <div class="profile-section">

            <div class="profile-section-title">
                <i class="fas fa-file-alt"></i>
                Registration Information
            </div>

            <div class="row">

                <div class="col-md-3 profile-item">
                    <span class="profile-label">Registered Type</span>
                    <div class="profile-value">
                        <?php echo profileValue($admin['registered_no']); ?>
                    </div>
                </div>

                <div class="col-md-3 profile-item">
                    <span class="profile-label">Type</span>
                    <div class="profile-value">
                        <?php echo profileValue($admin['registration_type']); ?>
                    </div>
                </div>

                <div class="col-md-3 profile-item">
                    <span class="profile-label">Registration Name</span>
                    <div class="profile-value">
                        <?php echo profileValue($admin['registration_name']); ?>
                    </div>
                </div>

                <div class="col-md-3 profile-item">
                    <span class="profile-label">Registration No</span>
                    <div class="profile-value">
                        <?php echo profileValue($admin['registration_no']); ?>
                    </div>
                </div>

            </div>

        </div>

        <!-- ====================================================
             INFRASTRUCTURE
        ===================================================== -->
        <div class="profile-section">

            <div class="profile-section-title">
                <i class="fas fa-school"></i>
                Infrastructure Details
            </div>

            <div class="row">

                <div class="col-md-4 profile-item">
                    <span class="profile-label">Total Area</span>
                    <div class="profile-value">
                        <?php echo profileValue($admin['total_area']); ?>
                    </div>
                </div>

                <div class="col-md-4 profile-item">
                    <span class="profile-label">Geo. Location</span>
                    <div class="profile-value">
                        <?php echo profileValue($admin['geo_location']); ?>
                    </div>
                </div>

                <div class="col-md-4 profile-item">
                    <span class="profile-label">Total PC</span>
                    <div class="profile-value">
                        <?php echo profileValue($admin['total_pc'], '0'); ?>
                    </div>
                </div>

                <div class="col-md-4 profile-item">
                    <span class="profile-label">Total Staffs</span>
                    <div class="profile-value">
                        <?php echo profileValue($admin['total_staffs'], '0'); ?>
                    </div>
                </div>

                <div class="col-md-4 profile-item">
                    <span class="profile-label">Practical Labs</span>
                    <div class="profile-value">
                        <?php echo profileValue($admin['practical_labs'], '0'); ?>
                    </div>
                </div>

                <div class="col-md-4 profile-item">
                    <span class="profile-label">Theory Rooms</span>
                    <div class="profile-value">
                        <?php echo profileValue($admin['theory_rooms'], '0'); ?>
                    </div>
                </div>

                <div class="col-md-4 profile-item">
                    <span class="profile-label">Office</span>
                    <div class="profile-value">
                        <?php if (!empty($admin['office'])): ?>
                            <span class="badge bg-success">Available</span>
                        <?php else: ?>
                            <span class="text-muted">Not Available</span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="col-md-4 profile-item">
                    <span class="profile-label">Toilet</span>
                    <div class="profile-value">
                        <?php if (!empty($admin['toilet'])): ?>
                            <span class="badge bg-success">Available</span>
                        <?php else: ?>
                            <span class="text-muted">Not Available</span>
                        <?php endif; ?>
                    </div>
                </div>

            </div>

        </div>

        <!-- ====================================================
             ASSIGNED PROGRAMS
        ===================================================== -->
        <div class="profile-section">

            <div class="profile-section-title">
                <i class="fas fa-book"></i>
                Assigned Programs
            </div>

            <?php if (!empty($assignedPrograms)): ?>

                <?php foreach ($assignedPrograms as $program): ?>

                    <span class="program-badge">
                        <?php echo profileValue($program['program_name']); ?>

                        <?php if (!empty($program['duration'])): ?>
                            <span class="text-muted">
                                — <?php echo profileValue($program['duration']); ?>
                            </span>
                        <?php endif; ?>
                    </span>

                <?php endforeach; ?>

            <?php else: ?>

                <span class="text-muted">
                    No programs assigned.
                </span>

            <?php endif; ?>

        </div>

        <!-- ====================================================
             DOCUMENTS
        ===================================================== -->
        <div class="profile-section">

            <div class="profile-section-title">
                <i class="fas fa-folder-open"></i>
                Documents & Images
            </div>

            <div class="row g-3">

                <?php
                $documents = [
                    'passport_pic'       => 'Passport Pic',
                    'qualification_doc'  => 'Qualification Document',
                    'aadhaar_card'       => 'Aadhaar Card',
                    'pan_card_doc'       => 'PAN Card',
                    'signature'          => 'Signature',
                    'registration_doc'   => 'Registration Document',
                    'lab_picture'        => 'Lab Picture',
                    'theory_room_pic'    => 'Theory Room Picture',
                    'office_pic_inner'   => 'Office Inner Picture',
                    'office_pic_outter'  => 'Office Outer Picture',
                ];
                ?>

                <?php foreach ($documents as $column => $label): ?>

                    <?php if (!empty($admin[$column]) && adminFileExists($admin[$column])): ?>

                        <div class="col-xl-3 col-lg-4 col-md-6">

                            <div class="document-box">

                                <?php if (isImageFile($admin[$column])): ?>

                                    <img
                                        src="<?php echo htmlspecialchars(adminFileUrl($admin[$column]), ENT_QUOTES, 'UTF-8'); ?>"
                                        class="document-preview"
                                        alt="<?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>"
                                    >

                                <?php else: ?>

                                    <div class="document-icon">
                                        <i class="fas fa-file-pdf"></i>
                                    </div>

                                <?php endif; ?>

                                <div class="fw-semibold small mb-2">
                                    <?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>
                                </div>

                                <a
                                    href="<?php echo htmlspecialchars(adminFileUrl($admin[$column]), ENT_QUOTES, 'UTF-8'); ?>"
                                    target="_blank"
                                    class="btn btn-sm btn-outline-secondary w-100"
                                >
                                    <i class="fas fa-eye me-1"></i>
                                    View
                                </a>

                            </div>

                        </div>

                    <?php endif; ?>

                <?php endforeach; ?>

                <?php
                $hasDocuments = false;

                foreach ($documents as $column => $label) {
                    if (
                        !empty($admin[$column]) &&
                        adminFileExists($admin[$column])
                    ) {
                        $hasDocuments = true;
                        break;
                    }
                }
                ?>

                <?php if (!$hasDocuments): ?>

                    <div class="col-12">
                        <div class="text-muted small">
                            No documents uploaded.
                        </div>
                    </div>

                <?php endif; ?>

            </div>

        </div>

        <!-- ====================================================
             FOOTER NOTE
        ===================================================== -->
        <div class="profile-section">

            <div class="readonly-note">
                <i class="fas fa-info-circle me-1"></i>
                This profile is read-only. Your information is managed by the
                Super Admin. You cannot edit or change these details from this page.
            </div>

        </div>

    </div>

</div>

<?php require_once 'includes/footer.php'; ?>
