<?php
/**
 * RISE - Add Student
 * =====================
 */

$pageTitle = 'Add Student';
require_once 'includes/header.php';
require_once 'includes/sidebar.php';
requireAdmin();

// Super admin cannot add students directly
if (isSuperAdmin()) {
    setFlashMessage('error', 'Super Admin cannot add students. Please use an admin account.');
    header('Location: students.php');
    exit;
}

$db = getDB();

// Fetch programs
// Fetch programs — only assigned programs for admin
$stmtPrograms = $db->prepare("
    SELECT p.* FROM programs p
    JOIN admin_programs ap ON p.id = ap.program_id
    WHERE ap.admin_id = :admin_id
    ORDER BY p.program_name
");
$stmtPrograms->execute([':admin_id' => getCurrentUserId()]);
$programs = $stmtPrograms->fetchAll();

if (empty($programs)) {
    setFlashMessage('error', 'No programs assigned to your account. Please contact Super Admin.');
    header('Location: students.php');
    exit;
}

$errors = [];
$old = [];
$successMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCSRF();

    // Collect and sanitize all inputs
    $old = $_POST;
    $fullName = sanitize($_POST['full_name'] ?? '');
    $gender = sanitize($_POST['gender'] ?? '');
    $dob = sanitize($_POST['dob'] ?? '');
    $fatherName = sanitize($_POST['father_name'] ?? '');
    $motherName = sanitize($_POST['mother_name'] ?? '');
    $mobile = sanitize($_POST['mobile'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $address = sanitize($_POST['address'] ?? '');
    $aadhaarNumber = sanitize($_POST['aadhaar_number'] ?? '');
    $programId = (int) ($_POST['program_id'] ?? 0);
    $courseId = (int) ($_POST['course_id'] ?? 0);
    $sessionName = sanitize($_POST['session_name'] ?? '');
    $batch = sanitize($_POST['batch'] ?? '');


// -------------------- 10th --------------------
$tenthInstitute = sanitize($_POST['tenth_institute_name'] ?? '');
$tenthBoard = sanitize($_POST['tenth_board_name'] ?? '');
$tenthRegistration = sanitize($_POST['tenth_registration_no'] ?? '');
$tenthRollNo = sanitize($_POST['tenth_roll_no'] ?? '');

$tenthYear = !empty($_POST['tenth_passing_year'])
    ? (int) $_POST['tenth_passing_year']
    : null;

$tenthTotalMarks = !empty($_POST['tenth_total_marks'])
    ? (float) $_POST['tenth_total_marks']
    : null;

$tenthObtainedMarks = !empty($_POST['tenth_obtained_marks'])
    ? (float) $_POST['tenth_obtained_marks']
    : null;

// Calculate percentage automatically on server side
$tenthPct = null;

if ($tenthTotalMarks !== null && $tenthTotalMarks > 0 && $tenthObtainedMarks !== null) {

    if ($tenthObtainedMarks > $tenthTotalMarks) {
        $errors[] = '10th Obtained Marks cannot be greater than Total Marks.';
    } else {
        $tenthPct = round(
            ($tenthObtainedMarks / $tenthTotalMarks) * 100,
            2
        );
    }
}



   // -------------------- 12th --------------------
$twelfthInstitute = sanitize($_POST['twelfth_institute_name'] ?? '');
$twelfthBoard = sanitize($_POST['twelfth_board_name'] ?? '');
$twelfthRegistration = sanitize($_POST['twelfth_registration_no'] ?? '');
$twelfthRollNo = sanitize($_POST['twelfth_roll_no'] ?? '');

$twelfthYear = !empty($_POST['twelfth_passing_year'])
    ? (int) $_POST['twelfth_passing_year']
    : null;

$twelfthTotalMarks = !empty($_POST['twelfth_total_marks'])
    ? (float) $_POST['twelfth_total_marks']
    : null;

$twelfthObtainedMarks = !empty($_POST['twelfth_obtained_marks'])
    ? (float) $_POST['twelfth_obtained_marks']
    : null;

// Calculate percentage automatically
$twelfthPct = null;

if ($twelfthTotalMarks !== null && $twelfthTotalMarks > 0 && $twelfthObtainedMarks !== null) {

    if ($twelfthObtainedMarks > $twelfthTotalMarks) {
        $errors[] = '12th Obtained Marks cannot be greater than Total Marks.';
    } else {
        $twelfthPct = round(
            ($twelfthObtainedMarks / $twelfthTotalMarks) * 100,
            2
        );
    }
}


// -------------------- UG --------------------
$ugInstitute = sanitize($_POST['ug_institute_name'] ?? '');
$ugUniversity = sanitize($_POST['ug_university_name'] ?? '');
$ugRegistration = sanitize($_POST['ug_registration_no'] ?? '');
$ugRollNo = sanitize($_POST['ug_roll_no'] ?? '');

$ugYear = !empty($_POST['ug_passing_year'])
    ? (int) $_POST['ug_passing_year']
    : null;

$ugTotalMarks = !empty($_POST['ug_total_marks'])
    ? (float) $_POST['ug_total_marks']
    : null;

$ugObtainedMarks = !empty($_POST['ug_obtained_marks'])
    ? (float) $_POST['ug_obtained_marks']
    : null;

// Calculate percentage automatically
$ugPct = null;

if ($ugTotalMarks !== null && $ugTotalMarks > 0 && $ugObtainedMarks !== null) {

    if ($ugObtainedMarks > $ugTotalMarks) {
        $errors[] = 'UG Obtained Marks cannot be greater than Total Marks.';
    } else {
        $ugPct = round(
            ($ugObtainedMarks / $ugTotalMarks) * 100,
            2
        );
    }
}


// -------------------- PG --------------------
$pgInstitute = sanitize($_POST['pg_institute_name'] ?? '');
$pgUniversity = sanitize($_POST['pg_university_name'] ?? '');
$pgRegistration = sanitize($_POST['pg_registration_no'] ?? '');
$pgRollNo = sanitize($_POST['pg_roll_no'] ?? '');

$pgYear = !empty($_POST['pg_passing_year'])
    ? (int) $_POST['pg_passing_year']
    : null;

$pgTotalMarks = !empty($_POST['pg_total_marks'])
    ? (float) $_POST['pg_total_marks']
    : null;

$pgObtainedMarks = !empty($_POST['pg_obtained_marks'])
    ? (float) $_POST['pg_obtained_marks']
    : null;

// Calculate percentage automatically
$pgPct = null;

if ($pgTotalMarks !== null && $pgTotalMarks > 0 && $pgObtainedMarks !== null) {

    if ($pgObtainedMarks > $pgTotalMarks) {
        $errors[] = 'PG Obtained Marks cannot be greater than Total Marks.';
    } else {
        $pgPct = round(
            ($pgObtainedMarks / $pgTotalMarks) * 100,
            2
        );
    }
}



 // ============================================================
// VALIDATION
// ============================================================

// 10th validation
if (
    !empty($tenthInstitute) ||
    !empty($tenthBoard) ||
    !empty($tenthRegistration) ||
    !empty($tenthRollNo) ||
    $tenthYear ||
    $tenthTotalMarks !== null ||
    $tenthObtainedMarks !== null
) {

    if (empty($tenthBoard)) {
        $errors[] = '10th Board Name is required when entering 10th details.';
    }

    if ($tenthYear && ($tenthYear < 1990 || $tenthYear > date('Y'))) {
        $errors[] = 'Valid 10th Passing Year is required.';
    }

    if ($tenthTotalMarks !== null && $tenthTotalMarks <= 0) {
        $errors[] = '10th Total Marks must be greater than 0.';
    }

    if ($tenthObtainedMarks !== null && $tenthObtainedMarks < 0) {
        $errors[] = '10th Obtained Marks cannot be negative.';
    }
}


// 12th validation
if (
    !empty($twelfthInstitute) ||
    !empty($twelfthBoard) ||
    !empty($twelfthRegistration) ||
    !empty($twelfthRollNo) ||
    $twelfthYear ||
    $twelfthTotalMarks !== null ||
    $twelfthObtainedMarks !== null
) {

    if (empty($twelfthBoard)) {
        $errors[] = '12th Board Name is required when entering 12th details.';
    }

    if ($twelfthYear && ($twelfthYear < 1990 || $twelfthYear > date('Y'))) {
        $errors[] = 'Valid 12th Passing Year is required.';
    }

    if ($twelfthTotalMarks !== null && $twelfthTotalMarks <= 0) {
        $errors[] = '12th Total Marks must be greater than 0.';
    }

    if ($twelfthObtainedMarks !== null && $twelfthObtainedMarks < 0) {
        $errors[] = '12th Obtained Marks cannot be negative.';
    }
}


// UG validation
if (
    !empty($ugInstitute) ||
    !empty($ugUniversity) ||
    !empty($ugRegistration) ||
    !empty($ugRollNo) ||
    $ugYear ||
    $ugTotalMarks !== null ||
    $ugObtainedMarks !== null
) {

    if ($ugYear && ($ugYear < 1990 || $ugYear > date('Y'))) {
        $errors[] = 'Valid UG Passing Year is required.';
    }

    if ($ugTotalMarks !== null && $ugTotalMarks <= 0) {
        $errors[] = 'UG Total Marks must be greater than 0.';
    }

    if ($ugObtainedMarks !== null && $ugObtainedMarks < 0) {
        $errors[] = 'UG Obtained Marks cannot be negative.';
    }
}


// PG validation
if (
    !empty($pgInstitute) ||
    !empty($pgUniversity) ||
    !empty($pgRegistration) ||
    !empty($pgRollNo) ||
    $pgYear ||
    $pgTotalMarks !== null ||
    $pgObtainedMarks !== null
) {

    if ($pgYear && ($pgYear < 1990 || $pgYear > date('Y'))) {
        $errors[] = 'Valid PG Passing Year is required.';
    }

    if ($pgTotalMarks !== null && $pgTotalMarks <= 0) {
        $errors[] = 'PG Total Marks must be greater than 0.';
    }

    if ($pgObtainedMarks !== null && $pgObtainedMarks < 0) {
        $errors[] = 'PG Obtained Marks cannot be negative.';
    }
}

    // File uploads
    $photoFilename = null;
    $signatureFilename = null;
    $aadhaarFilename = null;
    $tenthMarksheetFilename = null;
    $twelfthMarksheetFilename = null;

    // Photo
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $result = uploadFile($_FILES['photo'], PHOTO_PATH, ALLOWED_IMAGE_TYPES, UPLOAD_MAX_SIZE, PHOTO_MAX_WIDTH, PHOTO_MAX_HEIGHT);
        if ($result['success']) {
            $photoFilename = $result['filename'];
        } else {
            $errors[] = 'Photo: ' . $result['error'];
        }
    } else {
        $errors[] = 'Student Photo is required.';
    }

    // Signature
    if (isset($_FILES['signature']) && $_FILES['signature']['error'] === UPLOAD_ERR_OK) {
        $result = uploadFile($_FILES['signature'], SIGNATURE_PATH, ALLOWED_IMAGE_TYPES, UPLOAD_MAX_SIZE, SIGNATURE_MAX_WIDTH, SIGNATURE_MAX_HEIGHT);
        if ($result['success']) {
            $signatureFilename = $result['filename'];
        } else {
            $errors[] = 'Signature: ' . $result['error'];
        }
    } else {
        $errors[] = 'Student Signature is required.';
    }

    // Aadhaar Upload
    if (isset($_FILES['aadhaar_upload']) && $_FILES['aadhaar_upload']['error'] === UPLOAD_ERR_OK) {
        $result = uploadFile($_FILES['aadhaar_upload'], DOCUMENT_PATH, ALLOWED_DOC_TYPES, UPLOAD_MAX_SIZE);
        if ($result['success']) {
            $aadhaarFilename = $result['filename'];
        } else {
            $errors[] = 'Aadhaar Upload: ' . $result['error'];
        }
    } else {
        $errors[] = 'Aadhaar Card upload is required.';
    }

    // 10th Marksheet - optional
    if (isset($_FILES['tenth_marksheet_upload']) && $_FILES['tenth_marksheet_upload']['error'] === UPLOAD_ERR_OK) {
        $result = uploadFile($_FILES['tenth_marksheet_upload'], MARKSHEET_PATH, ALLOWED_DOC_TYPES, UPLOAD_MAX_SIZE);
        if ($result['success']) {
            $tenthMarksheetFilename = $result['filename'];
        } else {
            $errors[] = '10th Marksheet: ' . $result['error'];
        }
    }

    // 12th Marksheet - optional
    if (isset($_FILES['twelfth_marksheet_upload']) && $_FILES['twelfth_marksheet_upload']['error'] === UPLOAD_ERR_OK) {
        $result = uploadFile($_FILES['twelfth_marksheet_upload'], MARKSHEET_PATH, ALLOWED_DOC_TYPES, UPLOAD_MAX_SIZE);
        if ($result['success']) {
            $twelfthMarksheetFilename = $result['filename'];
        } else {
            $errors[] = '12th Marksheet: ' . $result['error'];
        }
    }

    // If no errors, insert
    if (empty($errors)) {
        $enrollmentNo = generateEnrollmentNo();
        $rollNo = generateRollNo();

       // ============================================================
// INSERT STUDENT
// Replace your existing INSERT query with this
// ============================================================

$sql = "INSERT INTO students (
    admin_id,
    program_id,
    course_id,
    enrollment_no,
    roll_no,
    session_name,
    batch,
    full_name,
    gender,
    dob,
    father_name,
    mother_name,
    mobile,
    email,
    address,
    aadhaar_number,
    aadhaar_upload,

    tenth_board_name,
    tenth_institute_name,
    tenth_registration_no,
    tenth_roll_no,
    tenth_passing_year,
    tenth_total_marks,
    tenth_obtained_marks,
    tenth_percentage,
    tenth_marksheet_upload,

    twelfth_board_name,
    twelfth_institute_name,
    twelfth_registration_no,
    twelfth_roll_no,
    twelfth_passing_year,
    twelfth_total_marks,
    twelfth_obtained_marks,
    twelfth_percentage,
    twelfth_marksheet_upload,

    ug_university_name,
    ug_institute_name,
    ug_registration_no,
    ug_roll_no,
    ug_passing_year,
    ug_total_marks,
    ug_obtained_marks,
    ug_percentage,

    pg_university_name,
    pg_institute_name,
    pg_registration_no,
    pg_roll_no,
    pg_passing_year,
    pg_total_marks,
    pg_obtained_marks,
    pg_percentage,

    photo,
    signature,
    status
) VALUES (
    :admin_id,
    :program_id,
    :course_id,
    :enrollment_no,
    :roll_no,
    :session_name,
    :batch,
    :full_name,
    :gender,
    :dob,
    :father_name,
    :mother_name,
    :mobile,
    :email,
    :address,
    :aadhaar_number,
    :aadhaar_upload,

    :tenth_board,
    :tenth_institute,
    :tenth_registration,
    :tenth_roll_no,
    :tenth_year,
    :tenth_total_marks,
    :tenth_obtained_marks,
    :tenth_pct,
    :tenth_marksheet,

    :twelfth_board,
    :twelfth_institute,
    :twelfth_registration,
    :twelfth_roll_no,
    :twelfth_year,
    :twelfth_total_marks,
    :twelfth_obtained_marks,
    :twelfth_pct,
    :twelfth_marksheet,

    :ug_university,
    :ug_institute,
    :ug_registration,
    :ug_roll_no,
    :ug_year,
    :ug_total_marks,
    :ug_obtained_marks,
    :ug_pct,

    :pg_university,
    :pg_institute,
    :pg_registration,
    :pg_roll_no,
    :pg_year,
    :pg_total_marks,
    :pg_obtained_marks,
    :pg_pct,

    :photo,
    :signature,
    'Pending'
)";

$stmt = $db->prepare($sql);

$stmt->execute([

    ':admin_id' => getCurrentUserId(),
    ':program_id' => $programId,
    ':course_id' => $courseId,
    ':enrollment_no' => $enrollmentNo,
    ':roll_no' => $rollNo,
    ':session_name' => $sessionName,
    ':batch' => $batch,

    ':full_name' => $fullName,
    ':gender' => $gender,
    ':dob' => $dob,
    ':father_name' => $fatherName,
    ':mother_name' => $motherName,
    ':mobile' => $mobile,
    ':email' => $email ?: null,
    ':address' => $address,

    ':aadhaar_number' => $aadhaarNumber,
    ':aadhaar_upload' => $aadhaarFilename,


    // ==================== 10th ====================

    ':tenth_board' => $tenthBoard ?: null,
    ':tenth_institute' => $tenthInstitute ?: null,
    ':tenth_registration' => $tenthRegistration ?: null,
    ':tenth_roll_no' => $tenthRollNo ?: null,
    ':tenth_year' => $tenthYear,
    ':tenth_total_marks' => $tenthTotalMarks,
    ':tenth_obtained_marks' => $tenthObtainedMarks,
    ':tenth_pct' => $tenthPct,
    ':tenth_marksheet' => $tenthMarksheetFilename,


    // ==================== 12th ====================

    ':twelfth_board' => $twelfthBoard ?: null,
    ':twelfth_institute' => $twelfthInstitute ?: null,
    ':twelfth_registration' => $twelfthRegistration ?: null,
    ':twelfth_roll_no' => $twelfthRollNo ?: null,
    ':twelfth_year' => $twelfthYear,
    ':twelfth_total_marks' => $twelfthTotalMarks,
    ':twelfth_obtained_marks' => $twelfthObtainedMarks,
    ':twelfth_pct' => $twelfthPct,
    ':twelfth_marksheet' => $twelfthMarksheetFilename,


    // ==================== UG ====================

    ':ug_university' => $ugUniversity ?: null,
    ':ug_institute' => $ugInstitute ?: null,
    ':ug_registration' => $ugRegistration ?: null,
    ':ug_roll_no' => $ugRollNo ?: null,
    ':ug_year' => $ugYear,
    ':ug_total_marks' => $ugTotalMarks,
    ':ug_obtained_marks' => $ugObtainedMarks,
    ':ug_pct' => $ugPct,


    // ==================== PG ====================

    ':pg_university' => $pgUniversity ?: null,
    ':pg_institute' => $pgInstitute ?: null,
    ':pg_registration' => $pgRegistration ?: null,
    ':pg_roll_no' => $pgRollNo ?: null,
    ':pg_year' => $pgYear,
    ':pg_total_marks' => $pgTotalMarks,
    ':pg_obtained_marks' => $pgObtainedMarks,
    ':pg_pct' => $pgPct,


    // ==================== Files ====================

    ':photo' => $photoFilename,
    ':signature' => $signatureFilename
]);

  $successMessage = "Student added successfully! Enrollment: $enrollmentNo | Roll: $rollNo";

echo "<script>
alert(" . json_encode($successMessage) . ");
window.location.href='students.php';
</script>";
$old = [];
exit;
    }
}
?>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger">
    <strong><i class="fas fa-exclamation-circle me-1"></i>Please fix the following errors:</strong>
    <ul class="mb-0 mt-2">
        <?php foreach ($errors as $e): ?>
        <li><?php echo $e; ?></li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<?php if (!empty($successMessage)): ?>
<div class="alert alert-success">
    <i class="fas fa-check-circle"></i>
    <?php echo $successMessage; ?>
</div>
<?php endif; ?>

<form method="POST" enctype="multipart/form-data" id="addStudentForm">
    <?php echo csrfField(); ?>

    <!-- Basic Information -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="form-section-title">
                <i class="fas fa-user"></i> Basic Information
            </div>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Full Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="full_name" required maxlength="200"
                           value="<?php echo sanitize($old['full_name'] ?? ''); ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Gender <span class="text-danger">*</span></label>
                    <select class="form-select" name="gender" required>
                        <option value="">-- Select --</option>
                        <option value="Male" <?php echo ($old['gender'] ?? '') === 'Male' ? 'selected' : ''; ?>>Male</option>
                        <option value="Female" <?php echo ($old['gender'] ?? '') === 'Female' ? 'selected' : ''; ?>>Female</option>
                        <option value="Other" <?php echo ($old['gender'] ?? '') === 'Other' ? 'selected' : ''; ?>>Other</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Date of Birth <span class="text-danger">*</span></label>
                    <input type="date" class="form-control" name="dob" required
                           value="<?php echo sanitize($old['dob'] ?? ''); ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Father's Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="father_name" required maxlength="200"
                           value="<?php echo sanitize($old['father_name'] ?? ''); ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Mother's Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="mother_name" required maxlength="200"
                           value="<?php echo sanitize($old['mother_name'] ?? ''); ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Mobile <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="mobile" required pattern="[0-9]{10}" maxlength="10"
                           value="<?php echo sanitize($old['mobile'] ?? ''); ?>" placeholder="10-digit mobile">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Email</label>
                    <input type="email" class="form-control" name="email" maxlength="255"
                           value="<?php echo sanitize($old['email'] ?? ''); ?>">
                </div>
                <div class="col-md-4">
                    <!-- Spacer -->
                </div>
                <div class="col-12">
                    <label class="form-label">Address <span class="text-danger">*</span></label>
                    <textarea class="form-control" name="address" rows="3" required><?php echo sanitize($old['address'] ?? ''); ?></textarea>
                </div>
            </div>
        </div>
    </div>

    <!-- Aadhaar Section -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="form-section-title">
                <i class="fas fa-id-card"></i> Aadhaar Details
            </div>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Aadhaar Number <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="aadhaar_number" required pattern="[0-9]{12}" maxlength="12"
                           value="<?php echo sanitize($old['aadhaar_number'] ?? ''); ?>" placeholder="12-digit Aadhaar">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Aadhaar Card Upload <span class="text-danger">*</span></label>
                <input type="file" class="form-control" name="aadhaar_upload" id="aadhaarInput" accept=".jpg,.jpeg,.png,.pdf" required>
<div id="aadhaarPreview" style="margin-top:10px;"></div>
                    <small class="text-muted">JPG, PNG, PDF - Max 500KB</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Program Section -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="form-section-title">
                <i class="fas fa-graduation-cap"></i> Program & Session
            </div>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Program <span class="text-danger">*</span></label>
                    <select class="form-select" name="program_id" id="programSelect" required
                            onchange="loadCourses(this.value, 'courseSelect')">
                        <option value="">-- Select Program --</option>
                        <?php foreach ($programs as $p): ?>
                        <option value="<?php echo $p['id']; ?>"
                            <?php echo ($old['program_id'] ?? '') == $p['id'] ? 'selected' : ''; ?>>
                            <?php echo sanitize($p['program_name']); ?> (<?php echo sanitize($p['duration']); ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Course <span class="text-danger">*</span></label>
                    <select class="form-select" name="course_id" id="courseSelect" required>
                        <option value="">-- Select Course --</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Session <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="session_name" required maxlength="100"
                           placeholder="e.g. 2024-2025" value="<?php echo sanitize($old['session_name'] ?? ''); ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Batch <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="batch" required maxlength="100"
                           placeholder="e.g. January 2024" value="<?php echo sanitize($old['batch'] ?? ''); ?>">
                </div>
            </div>
        </div>
    </div>

    <!-- Educational Qualification -->
<div class="card mb-4">
    <div class="card-body">

        <div class="form-section-title">
            <i class="fas fa-book-open"></i> Educational Qualification
        </div>

        <!-- ==================== 10th ==================== -->
        <h6 class="text-muted mb-3">
            <i class="fas fa-school me-1"></i> 10th Standard (Optional)
        </h6>

        <div class="row g-3 mb-4">

            <div class="col-md-4">
                <label class="form-label">Institute / School Name</label>
                <input type="text"
                       class="form-control"
                       name="tenth_institute_name"
                       maxlength="255"
                       value="<?php echo sanitize($old['tenth_institute_name'] ?? ''); ?>">
            </div>

            <div class="col-md-4">
                <label class="form-label">Board Name</label>
                <input type="text"
                       class="form-control"
                       name="tenth_board_name"
                       maxlength="255"
                       value="<?php echo sanitize($old['tenth_board_name'] ?? ''); ?>">
            </div>

            <div class="col-md-2">
                <label class="form-label">Registration No.</label>
                <input type="text"
                       class="form-control"
                       name="tenth_registration_no"
                       maxlength="100"
                       value="<?php echo sanitize($old['tenth_registration_no'] ?? ''); ?>">
            </div>

            <div class="col-md-2">
                <label class="form-label">Roll No.</label>
                <input type="text"
                       class="form-control"
                       name="tenth_roll_no"
                       maxlength="100"
                       value="<?php echo sanitize($old['tenth_roll_no'] ?? ''); ?>">
            </div>

            <div class="col-md-3">
                <label class="form-label">Passing Year</label>
                <input type="number"
                       class="form-control"
                       name="tenth_passing_year"
                       min="1990"
                       max="<?php echo date('Y'); ?>"
                       value="<?php echo sanitize($old['tenth_passing_year'] ?? ''); ?>">
            </div>

            <div class="col-md-3">
                <label class="form-label">Total Marks</label>
                <input type="number"
                       class="form-control"
                       name="tenth_total_marks"
                       id="tenth_total_marks"
                       min="1"
                       step="0.01"
                       value="<?php echo sanitize($old['tenth_total_marks'] ?? ''); ?>">
            </div>

            <div class="col-md-3">
                <label class="form-label">Obtained Marks</label>
                <input type="number"
                       class="form-control"
                       name="tenth_obtained_marks"
                       id="tenth_obtained_marks"
                       min="0"
                       step="0.01"
                       value="<?php echo sanitize($old['tenth_obtained_marks'] ?? ''); ?>">
            </div>

            <div class="col-md-3">
                <label class="form-label">Percentage</label>
                <input type="number"
                       class="form-control"
                       name="tenth_percentage"
                       id="tenth_percentage"
                       step="0.01"
                       readonly
                       value="<?php echo sanitize($old['tenth_percentage'] ?? ''); ?>">
            </div>

            <div class="col-md-4">
                <label class="form-label">Marksheet Upload</label>
                <input type="file"
                       class="form-control"
                       name="tenth_marksheet_upload"
                       id="tenthInput"
                       accept=".jpg,.jpeg,.png,.pdf">

                <div id="tenthPreview" style="margin-top:10px;"></div>

                <small class="text-muted">
                    JPG, PNG, PDF - Max 500KB
                </small>
            </div>

        </div>


        <!-- ==================== 12th ==================== -->
        <h6 class="text-muted mb-3">
            <i class="fas fa-school me-1"></i> 12th Standard (Optional)
        </h6>

        <div class="row g-3 mb-4">

            <div class="col-md-4">
                <label class="form-label">Institute / School Name</label>
                <input type="text"
                       class="form-control"
                       name="twelfth_institute_name"
                       maxlength="255"
                       value="<?php echo sanitize($old['twelfth_institute_name'] ?? ''); ?>">
            </div>

            <div class="col-md-4">
                <label class="form-label">Board Name</label>
                <input type="text"
                       class="form-control"
                       name="twelfth_board_name"
                       maxlength="255"
                       value="<?php echo sanitize($old['twelfth_board_name'] ?? ''); ?>">
            </div>

            <div class="col-md-2">
                <label class="form-label">Registration No.</label>
                <input type="text"
                       class="form-control"
                       name="twelfth_registration_no"
                       maxlength="100"
                       value="<?php echo sanitize($old['twelfth_registration_no'] ?? ''); ?>">
            </div>

            <div class="col-md-2">
                <label class="form-label">Roll No.</label>
                <input type="text"
                       class="form-control"
                       name="twelfth_roll_no"
                       maxlength="100"
                       value="<?php echo sanitize($old['twelfth_roll_no'] ?? ''); ?>">
            </div>

            <div class="col-md-3">
                <label class="form-label">Passing Year</label>
                <input type="number"
                       class="form-control"
                       name="twelfth_passing_year"
                       min="1990"
                       max="<?php echo date('Y'); ?>"
                       value="<?php echo sanitize($old['twelfth_passing_year'] ?? ''); ?>">
            </div>

            <div class="col-md-3">
                <label class="form-label">Total Marks</label>
                <input type="number"
                       class="form-control"
                       name="twelfth_total_marks"
                       id="twelfth_total_marks"
                       min="1"
                       step="0.01"
                       value="<?php echo sanitize($old['twelfth_total_marks'] ?? ''); ?>">
            </div>

            <div class="col-md-3">
                <label class="form-label">Obtained Marks</label>
                <input type="number"
                       class="form-control"
                       name="twelfth_obtained_marks"
                       id="twelfth_obtained_marks"
                       min="0"
                       step="0.01"
                       value="<?php echo sanitize($old['twelfth_obtained_marks'] ?? ''); ?>">
            </div>

            <div class="col-md-3">
                <label class="form-label">Percentage</label>
                <input type="number"
                       class="form-control"
                       name="twelfth_percentage"
                       id="twelfth_percentage"
                       step="0.01"
                       readonly
                       value="<?php echo sanitize($old['twelfth_percentage'] ?? ''); ?>">
            </div>

            <div class="col-md-4">
                <label class="form-label">Marksheet Upload</label>
                <input type="file"
                       class="form-control"
                       name="twelfth_marksheet_upload"
                       id="twelfthInput"
                       accept=".jpg,.jpeg,.png,.pdf">

                <div id="twelfthPreview" style="margin-top:10px;"></div>

                <small class="text-muted">
                    JPG, PNG, PDF - Max 500KB
                </small>
            </div>

        </div>


        <!-- ==================== UG ==================== -->
        <h6 class="text-muted mb-3">
            <i class="fas fa-university me-1"></i> UG (Optional)
        </h6>

        <div class="row g-3 mb-4">

            <div class="col-md-4">
                <label class="form-label">Institute / College Name</label>
                <input type="text"
                       class="form-control"
                       name="ug_institute_name"
                       maxlength="255"
                       value="<?php echo sanitize($old['ug_institute_name'] ?? ''); ?>">
            </div>

            <div class="col-md-4">
                <label class="form-label">University Name</label>
                <input type="text"
                       class="form-control"
                       name="ug_university_name"
                       maxlength="255"
                       value="<?php echo sanitize($old['ug_university_name'] ?? ''); ?>">
            </div>

            <div class="col-md-2">
                <label class="form-label">Registration No.</label>
                <input type="text"
                       class="form-control"
                       name="ug_registration_no"
                       maxlength="100"
                       value="<?php echo sanitize($old['ug_registration_no'] ?? ''); ?>">
            </div>

            <div class="col-md-2">
                <label class="form-label">Roll No.</label>
                <input type="text"
                       class="form-control"
                       name="ug_roll_no"
                       maxlength="100"
                       value="<?php echo sanitize($old['ug_roll_no'] ?? ''); ?>">
            </div>

            <div class="col-md-3">
                <label class="form-label">Passing Year</label>
                <input type="number"
                       class="form-control"
                       name="ug_passing_year"
                       min="1990"
                       max="<?php echo date('Y'); ?>"
                       value="<?php echo sanitize($old['ug_passing_year'] ?? ''); ?>">
            </div>

            <div class="col-md-3">
                <label class="form-label">Total Marks</label>
                <input type="number"
                       class="form-control"
                       name="ug_total_marks"
                       id="ug_total_marks"
                       min="1"
                       step="0.01"
                       value="<?php echo sanitize($old['ug_total_marks'] ?? ''); ?>">
            </div>

            <div class="col-md-3">
                <label class="form-label">Obtained Marks</label>
                <input type="number"
                       class="form-control"
                       name="ug_obtained_marks"
                       id="ug_obtained_marks"
                       min="0"
                       step="0.01"
                       value="<?php echo sanitize($old['ug_obtained_marks'] ?? ''); ?>">
            </div>

            <div class="col-md-3">
                <label class="form-label">Percentage</label>
                <input type="number"
                       class="form-control"
                       name="ug_percentage"
                       id="ug_percentage"
                       step="0.01"
                       readonly
                       value="<?php echo sanitize($old['ug_percentage'] ?? ''); ?>">
            </div>

        </div>


        <!-- ==================== PG ==================== -->
        <h6 class="text-muted mb-3">
            <i class="fas fa-university me-1"></i> PG (Optional)
        </h6>

        <div class="row g-3">

            <div class="col-md-4">
                <label class="form-label">Institute / College Name</label>
                <input type="text"
                       class="form-control"
                       name="pg_institute_name"
                       maxlength="255"
                       value="<?php echo sanitize($old['pg_institute_name'] ?? ''); ?>">
            </div>

            <div class="col-md-4">
                <label class="form-label">University Name</label>
                <input type="text"
                       class="form-control"
                       name="pg_university_name"
                       maxlength="255"
                       value="<?php echo sanitize($old['pg_university_name'] ?? ''); ?>">
            </div>

            <div class="col-md-2">
                <label class="form-label">Registration No.</label>
                <input type="text"
                       class="form-control"
                       name="pg_registration_no"
                       maxlength="100"
                       value="<?php echo sanitize($old['pg_registration_no'] ?? ''); ?>">
            </div>

            <div class="col-md-2">
                <label class="form-label">Roll No.</label>
                <input type="text"
                       class="form-control"
                       name="pg_roll_no"
                       maxlength="100"
                       value="<?php echo sanitize($old['pg_roll_no'] ?? ''); ?>">
            </div>

            <div class="col-md-3">
                <label class="form-label">Passing Year</label>
                <input type="number"
                       class="form-control"
                       name="pg_passing_year"
                       min="1990"
                       max="<?php echo date('Y'); ?>"
                       value="<?php echo sanitize($old['pg_passing_year'] ?? ''); ?>">
            </div>

            <div class="col-md-3">
                <label class="form-label">Total Marks</label>
                <input type="number"
                       class="form-control"
                       name="pg_total_marks"
                       id="pg_total_marks"
                       min="1"
                       step="0.01"
                       value="<?php echo sanitize($old['pg_total_marks'] ?? ''); ?>">
            </div>

            <div class="col-md-3">
                <label class="form-label">Obtained Marks</label>
                <input type="number"
                       class="form-control"
                       name="pg_obtained_marks"
                       id="pg_obtained_marks"
                       min="0"
                       step="0.01"
                       value="<?php echo sanitize($old['pg_obtained_marks'] ?? ''); ?>">
            </div>

            <div class="col-md-3">
                <label class="form-label">Percentage</label>
                <input type="number"
                       class="form-control"
                       name="pg_percentage"
                       id="pg_percentage"
                       step="0.01"
                       readonly
                       value="<?php echo sanitize($old['pg_percentage'] ?? ''); ?>">
            </div>

        </div>

    </div>
</div>



    <!-- Upload Section -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="form-section-title">
                <i class="fas fa-camera"></i> Photo & Signature
            </div>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Student Photo <span class="text-danger">*</span></label>
                   <input type="file" class="form-control" name="photo" id="photoInput" accept=".jpg,.jpeg,.png" required>
<img id="photoPreview" style="max-width:120px;margin-top:10px;display:none;">
                    <small class="text-muted">JPG/PNG, Max 400x400px, Max 500KB</small>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Student Signature <span class="text-danger">*</span></label>
                 <input type="file" class="form-control" name="signature" id="signatureInput" accept=".jpg,.jpeg,.png" required>
<img id="signaturePreview" style="max-width:120px;margin-top:10px;display:none;">
                    <small class="text-muted">JPG/PNG, Max 200x100px, Max 500KB</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Submit -->
    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary btn-lg">
            <i class="fas fa-save me-2"></i>Add Student
        </button>
        <a href="students.php" class="btn btn-secondary btn-lg">
            <i class="fas fa-times me-2"></i>Cancel
        </a>
    </div>
</form>

<script>
// Auto-load courses if program was selected (on form error reload)
document.addEventListener('DOMContentLoaded', function() {
    var programSelect = document.getElementById('programSelect');
    if (programSelect && programSelect.value) {
        loadCourses(programSelect.value, 'courseSelect', '<?php echo sanitize($old['course_id'] ?? ''); ?>');
    }
});
</script>

<script>

function previewImage(inputId, previewId) {

    const input = document.getElementById(inputId);
    const preview = document.getElementById(previewId);

    input.addEventListener("change", function(){

        const file = this.files[0];
        if(!file) return;

        const reader = new FileReader();

        reader.onload = function(e){
            preview.src = e.target.result;
            preview.style.display = "block";
        }

        reader.readAsDataURL(file);

    });

}

function previewFile(inputId, previewId) {

    const input = document.getElementById(inputId);
    const preview = document.getElementById(previewId);

    input.addEventListener("change", function(){

        const file = this.files[0];
        if(!file) return;

        if(file.type === "application/pdf"){

            preview.innerHTML = "<p style='color:green'>PDF Selected: "+file.name+"</p>";

        } else {

            const reader = new FileReader();

            reader.onload = function(e){

                preview.innerHTML = "<img src='"+e.target.result+"' style='max-width:120px'>";

            }

            reader.readAsDataURL(file);

        }

    });

}

previewImage("photoInput","photoPreview");
previewImage("signatureInput","signaturePreview");

previewFile("aadhaarInput","aadhaarPreview");
previewFile("tenthInput","tenthPreview");
previewFile("twelfthInput","twelfthPreview");



</script>

<script>
document.addEventListener('DOMContentLoaded', function () {

    function calculatePercentage(totalId, obtainedId, percentageId) {

        const total = document.getElementById(totalId);
        const obtained = document.getElementById(obtainedId);
        const percentage = document.getElementById(percentageId);

        if (!total || !obtained || !percentage) return;

        function calculate() {

            const totalMarks = parseFloat(total.value);
            const obtainedMarks = parseFloat(obtained.value);

            if (
                !isNaN(totalMarks) &&
                totalMarks > 0 &&
                !isNaN(obtainedMarks)
            ) {

                if (obtainedMarks > totalMarks) {
                    percentage.value = '';
                    obtained.setCustomValidity(
                        'Obtained marks cannot be greater than total marks.'
                    );
                    return;
                }

                obtained.setCustomValidity('');

                const result = (obtainedMarks / totalMarks) * 100;

                percentage.value = result.toFixed(2);

            } else {
                percentage.value = '';
                obtained.setCustomValidity('');
            }
        }

        total.addEventListener('input', calculate);
        obtained.addEventListener('input', calculate);

        // Calculate automatically if old values already exist
        calculate();
    }


    // 10th
    calculatePercentage(
        'tenth_total_marks',
        'tenth_obtained_marks',
        'tenth_percentage'
    );


    // 12th
    calculatePercentage(
        'twelfth_total_marks',
        'twelfth_obtained_marks',
        'twelfth_percentage'
    );


    // UG
    calculatePercentage(
        'ug_total_marks',
        'ug_obtained_marks',
        'ug_percentage'
    );


    // PG
    calculatePercentage(
        'pg_total_marks',
        'pg_obtained_marks',
        'pg_percentage'
    );

});
</script>

<?php require_once 'includes/footer.php'; ?>