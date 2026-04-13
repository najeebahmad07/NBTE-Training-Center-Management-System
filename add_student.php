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

    // 10th
    $tenthBoard = sanitize($_POST['tenth_board_name'] ?? '');
    $tenthYear = (int) ($_POST['tenth_passing_year'] ?? 0);
    $tenthPct = (float) ($_POST['tenth_percentage'] ?? 0);

    // 12th
    $twelfthBoard = sanitize($_POST['twelfth_board_name'] ?? '');
    $twelfthYear = (int) ($_POST['twelfth_passing_year'] ?? 0);
    $twelfthPct = (float) ($_POST['twelfth_percentage'] ?? 0);

    // UG
    $ugUniversity = sanitize($_POST['ug_university_name'] ?? '');
    $ugYear = !empty($_POST['ug_passing_year']) ? (int) $_POST['ug_passing_year'] : null;
    $ugPct = !empty($_POST['ug_percentage']) ? (float) $_POST['ug_percentage'] : null;

    // PG
    $pgUniversity = sanitize($_POST['pg_university_name'] ?? '');
    $pgYear = !empty($_POST['pg_passing_year']) ? (int) $_POST['pg_passing_year'] : null;
    $pgPct = !empty($_POST['pg_percentage']) ? (float) $_POST['pg_percentage'] : null;

    // Validations
    if (empty($fullName)) $errors[] = 'Full Name is required.';
    if (!in_array($gender, ['Male', 'Female', 'Other'])) $errors[] = 'Valid Gender is required.';
    if (empty($dob)) $errors[] = 'Date of Birth is required.';
    if (empty($fatherName)) $errors[] = 'Father Name is required.';
    if (empty($motherName)) $errors[] = 'Mother Name is required.';
    if (!preg_match('/^[0-9]{10}$/', $mobile)) $errors[] = 'Valid 10-digit mobile number is required.';
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email is required.';
    if (empty($address)) $errors[] = 'Address is required.';
    if (!preg_match('/^[0-9]{12}$/', $aadhaarNumber)) $errors[] = 'Valid 12-digit Aadhaar number is required.';
    if ($programId <= 0) $errors[] = 'Program is required.';
    if ($courseId <= 0) $errors[] = 'Course is required.';
    if (empty($sessionName)) $errors[] = 'Session is required.';
    if (empty($batch)) $errors[] = 'Batch is required.';
    if (empty($tenthBoard)) $errors[] = '10th Board Name is required.';
    if ($tenthYear < 1990 || $tenthYear > date('Y')) $errors[] = 'Valid 10th Passing Year is required.';
    if ($tenthPct <= 0 || $tenthPct > 100) $errors[] = 'Valid 10th Percentage is required.';
    if (empty($twelfthBoard)) $errors[] = '12th Board Name is required.';
    if ($twelfthYear < 1990 || $twelfthYear > date('Y')) $errors[] = 'Valid 12th Passing Year is required.';
    if ($twelfthPct <= 0 || $twelfthPct > 100) $errors[] = 'Valid 12th Percentage is required.';

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

    // 10th Marksheet
    if (isset($_FILES['tenth_marksheet_upload']) && $_FILES['tenth_marksheet_upload']['error'] === UPLOAD_ERR_OK) {
        $result = uploadFile($_FILES['tenth_marksheet_upload'], MARKSHEET_PATH, ALLOWED_DOC_TYPES, UPLOAD_MAX_SIZE);
        if ($result['success']) {
            $tenthMarksheetFilename = $result['filename'];
        } else {
            $errors[] = '10th Marksheet: ' . $result['error'];
        }
    } else {
        $errors[] = '10th Marksheet upload is required.';
    }

    // 12th Marksheet
    if (isset($_FILES['twelfth_marksheet_upload']) && $_FILES['twelfth_marksheet_upload']['error'] === UPLOAD_ERR_OK) {
        $result = uploadFile($_FILES['twelfth_marksheet_upload'], MARKSHEET_PATH, ALLOWED_DOC_TYPES, UPLOAD_MAX_SIZE);
        if ($result['success']) {
            $twelfthMarksheetFilename = $result['filename'];
        } else {
            $errors[] = '12th Marksheet: ' . $result['error'];
        }
    } else {
        $errors[] = '12th Marksheet upload is required.';
    }

    // If no errors, insert
    if (empty($errors)) {
        $enrollmentNo = generateEnrollmentNo();
        $rollNo = generateRollNo();

        $sql = "INSERT INTO students (
            admin_id, program_id, course_id, enrollment_no, roll_no, session_name, batch,
            full_name, gender, dob, father_name, mother_name, mobile, email, address,
            aadhaar_number, aadhaar_upload,
            tenth_board_name, tenth_passing_year, tenth_percentage, tenth_marksheet_upload,
            twelfth_board_name, twelfth_passing_year, twelfth_percentage, twelfth_marksheet_upload,
            ug_university_name, ug_passing_year, ug_percentage,
            pg_university_name, pg_passing_year, pg_percentage,
            photo, signature, status
        ) VALUES (
            :admin_id, :program_id, :course_id, :enrollment_no, :roll_no, :session_name, :batch,
            :full_name, :gender, :dob, :father_name, :mother_name, :mobile, :email, :address,
            :aadhaar_number, :aadhaar_upload,
            :tenth_board, :tenth_year, :tenth_pct, :tenth_marksheet,
            :twelfth_board, :twelfth_year, :twelfth_pct, :twelfth_marksheet,
            :ug_university, :ug_year, :ug_pct,
            :pg_university, :pg_year, :pg_pct,
            :photo, :signature, 'Pending'
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
            ':tenth_board' => $tenthBoard,
            ':tenth_year' => $tenthYear,
            ':tenth_pct' => $tenthPct,
            ':tenth_marksheet' => $tenthMarksheetFilename,
            ':twelfth_board' => $twelfthBoard,
            ':twelfth_year' => $twelfthYear,
            ':twelfth_pct' => $twelfthPct,
            ':twelfth_marksheet' => $twelfthMarksheetFilename,
            ':ug_university' => $ugUniversity ?: null,
            ':ug_year' => $ugYear,
            ':ug_pct' => $ugPct,
            ':pg_university' => $pgUniversity ?: null,
            ':pg_year' => $pgYear,
            ':pg_pct' => $pgPct,
            ':photo' => $photoFilename,
            ':signature' => $signatureFilename,
        ]);

     $successMessage = "Student added successfully! Enrollment: $enrollmentNo | Roll: $rollNo";

echo "<script>
alert(" . json_encode($successMessage) . ");
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

            <!-- 10th -->
            <h6 class="text-muted mb-3"><i class="fas fa-school me-1"></i> 10th Standard</h6>
            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <label class="form-label">Board Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="tenth_board_name" required maxlength="255"
                           value="<?php echo sanitize($old['tenth_board_name'] ?? ''); ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Passing Year <span class="text-danger">*</span></label>
                    <input type="number" class="form-control" name="tenth_passing_year" required min="1990" max="<?php echo date('Y'); ?>"
                           value="<?php echo sanitize($old['tenth_passing_year'] ?? ''); ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Percentage <span class="text-danger">*</span></label>
                    <input type="number" class="form-control" name="tenth_percentage" required step="0.01" min="1" max="100"
                           value="<?php echo sanitize($old['tenth_percentage'] ?? ''); ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Marksheet Upload <span class="text-danger">*</span></label>
                   <input type="file" class="form-control" name="tenth_marksheet_upload" id="tenthInput" accept=".jpg,.jpeg,.png,.pdf" required>
<div id="tenthPreview" style="margin-top:10px;"></div>
                    <small class="text-muted">JPG, PNG, PDF - Max 500KB</small>
                </div>
            </div>

            <!-- 12th -->
            <h6 class="text-muted mb-3"><i class="fas fa-school me-1"></i> 12th Standard</h6>
            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <label class="form-label">Board Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="twelfth_board_name" required maxlength="255"
                           value="<?php echo sanitize($old['twelfth_board_name'] ?? ''); ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Passing Year <span class="text-danger">*</span></label>
                    <input type="number" class="form-control" name="twelfth_passing_year" required min="1990" max="<?php echo date('Y'); ?>"
                           value="<?php echo sanitize($old['twelfth_passing_year'] ?? ''); ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Percentage <span class="text-danger">*</span></label>
                    <input type="number" class="form-control" name="twelfth_percentage" required step="0.01" min="1" max="100"
                           value="<?php echo sanitize($old['twelfth_percentage'] ?? ''); ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Marksheet Upload <span class="text-danger">*</span></label>
                 <input type="file" class="form-control" name="twelfth_marksheet_upload" id="twelfthInput" accept=".jpg,.jpeg,.png,.pdf" required>
<div id="twelfthPreview" style="margin-top:10px;"></div>
                    <small class="text-muted">JPG, PNG, PDF - Max 500KB</small>
                </div>
            </div>

            <!-- UG -->
            <h6 class="text-muted mb-3"><i class="fas fa-university me-1"></i> UG (Optional)</h6>
            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <label class="form-label">University Name</label>
                    <input type="text" class="form-control" name="ug_university_name" maxlength="255"
                           value="<?php echo sanitize($old['ug_university_name'] ?? ''); ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Passing Year</label>
                    <input type="number" class="form-control" name="ug_passing_year" min="1990" max="<?php echo date('Y'); ?>"
                           value="<?php echo sanitize($old['ug_passing_year'] ?? ''); ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Percentage</label>
                    <input type="number" class="form-control" name="ug_percentage" step="0.01" min="1" max="100"
                           value="<?php echo sanitize($old['ug_percentage'] ?? ''); ?>">
                </div>
            </div>

            <!-- PG -->
            <h6 class="text-muted mb-3"><i class="fas fa-university me-1"></i> PG (Optional)</h6>
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">University Name</label>
                    <input type="text" class="form-control" name="pg_university_name" maxlength="255"
                           value="<?php echo sanitize($old['pg_university_name'] ?? ''); ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Passing Year</label>
                    <input type="number" class="form-control" name="pg_passing_year" min="1990" max="<?php echo date('Y'); ?>"
                           value="<?php echo sanitize($old['pg_passing_year'] ?? ''); ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Percentage</label>
                    <input type="number" class="form-control" name="pg_percentage" step="0.01" min="1" max="100"
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

<?php require_once 'includes/footer.php'; ?>