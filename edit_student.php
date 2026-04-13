<?php
/**
 * RISE - Edit Student
 * =====================
 */

$pageTitle = 'Edit Student';
require_once 'includes/header.php';
require_once 'includes/sidebar.php';
requireAdmin();

if (isSuperAdmin()) {
    setFlashMessage('error', 'Use an admin account to edit students.');
    header('Location: students.php');
    exit;
}

$db = getDB();
$studentId = (int) ($_GET['id'] ?? 0);

if ($studentId <= 0) {
    setFlashMessage('error', 'Invalid student.');
    header('Location: students.php');
    exit;
}

verifyStudentOwnership($studentId);

// Fetch student
$stmt = $db->prepare("SELECT * FROM students WHERE id = :id AND admin_id = :admin_id");
$stmt->execute([':id' => $studentId, ':admin_id' => getCurrentUserId()]);
$student = $stmt->fetch();

if (!$student) {
    setFlashMessage('error', 'Student not found.');
    header('Location: students.php');
    exit;
}

$stmtPrograms = $db->query("SELECT * FROM programs ORDER BY program_name");
$programs = $stmtPrograms->fetchAll();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCSRF();

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
    $tenthBoard = sanitize($_POST['tenth_board_name'] ?? '');
    $tenthYear = (int) ($_POST['tenth_passing_year'] ?? 0);
    $tenthPct = (float) ($_POST['tenth_percentage'] ?? 0);
    $twelfthBoard = sanitize($_POST['twelfth_board_name'] ?? '');
    $twelfthYear = (int) ($_POST['twelfth_passing_year'] ?? 0);
    $twelfthPct = (float) ($_POST['twelfth_percentage'] ?? 0);
    $ugUniversity = sanitize($_POST['ug_university_name'] ?? '');
    $ugYear = !empty($_POST['ug_passing_year']) ? (int) $_POST['ug_passing_year'] : null;
    $ugPct = !empty($_POST['ug_percentage']) ? (float) $_POST['ug_percentage'] : null;
    $pgUniversity = sanitize($_POST['pg_university_name'] ?? '');
    $pgYear = !empty($_POST['pg_passing_year']) ? (int) $_POST['pg_passing_year'] : null;
    $pgPct = !empty($_POST['pg_percentage']) ? (float) $_POST['pg_percentage'] : null;

    // Validations
    if (empty($fullName)) $errors[] = 'Full Name is required.';
    if (!in_array($gender, ['Male', 'Female', 'Other'])) $errors[] = 'Valid Gender is required.';
    if (empty($dob)) $errors[] = 'Date of Birth is required.';
    if (empty($fatherName)) $errors[] = 'Father Name is required.';
    if (empty($motherName)) $errors[] = 'Mother Name is required.';
    if (!preg_match('/^[0-9]{10}$/', $mobile)) $errors[] = 'Valid mobile required.';
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email required.';
    if (empty($address)) $errors[] = 'Address is required.';
    if (!preg_match('/^[0-9]{12}$/', $aadhaarNumber)) $errors[] = 'Valid Aadhaar required.';
    if ($programId <= 0) $errors[] = 'Program is required.';
    if ($courseId <= 0) $errors[] = 'Course is required.';

    // Handle optional file uploads
    $photoFilename = $student['photo'];
    $signatureFilename = $student['signature'];
    $aadhaarFilename = $student['aadhaar_upload'];
    $tenthMarksheet = $student['tenth_marksheet_upload'];
    $twelfthMarksheet = $student['twelfth_marksheet_upload'];

    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $result = uploadFile($_FILES['photo'], PHOTO_PATH, ALLOWED_IMAGE_TYPES, UPLOAD_MAX_SIZE, PHOTO_MAX_WIDTH, PHOTO_MAX_HEIGHT);
        if ($result['success']) $photoFilename = $result['filename'];
        else $errors[] = 'Photo: ' . $result['error'];
    }

    if (isset($_FILES['signature']) && $_FILES['signature']['error'] === UPLOAD_ERR_OK) {
        $result = uploadFile($_FILES['signature'], SIGNATURE_PATH, ALLOWED_IMAGE_TYPES, UPLOAD_MAX_SIZE, SIGNATURE_MAX_WIDTH, SIGNATURE_MAX_HEIGHT);
        if ($result['success']) $signatureFilename = $result['filename'];
        else $errors[] = 'Signature: ' . $result['error'];
    }

    if (isset($_FILES['aadhaar_upload']) && $_FILES['aadhaar_upload']['error'] === UPLOAD_ERR_OK) {
        $result = uploadFile($_FILES['aadhaar_upload'], DOCUMENT_PATH, ALLOWED_DOC_TYPES, UPLOAD_MAX_SIZE);
        if ($result['success']) $aadhaarFilename = $result['filename'];
        else $errors[] = 'Aadhaar: ' . $result['error'];
    }

    if (isset($_FILES['tenth_marksheet_upload']) && $_FILES['tenth_marksheet_upload']['error'] === UPLOAD_ERR_OK) {
        $result = uploadFile($_FILES['tenth_marksheet_upload'], MARKSHEET_PATH, ALLOWED_DOC_TYPES, UPLOAD_MAX_SIZE);
        if ($result['success']) $tenthMarksheet = $result['filename'];
        else $errors[] = '10th Marksheet: ' . $result['error'];
    }

    if (isset($_FILES['twelfth_marksheet_upload']) && $_FILES['twelfth_marksheet_upload']['error'] === UPLOAD_ERR_OK) {
        $result = uploadFile($_FILES['twelfth_marksheet_upload'], MARKSHEET_PATH, ALLOWED_DOC_TYPES, UPLOAD_MAX_SIZE);
        if ($result['success']) $twelfthMarksheet = $result['filename'];
        else $errors[] = '12th Marksheet: ' . $result['error'];
    }

    if (empty($errors)) {
        $sql = "UPDATE students SET
            program_id = :program_id, course_id = :course_id, session_name = :session_name, batch = :batch,
            full_name = :full_name, gender = :gender, dob = :dob, father_name = :father_name,
            mother_name = :mother_name, mobile = :mobile, email = :email, address = :address,
            aadhaar_number = :aadhaar_number, aadhaar_upload = :aadhaar_upload,
            tenth_board_name = :tenth_board, tenth_passing_year = :tenth_year, tenth_percentage = :tenth_pct,
            tenth_marksheet_upload = :tenth_marksheet,
            twelfth_board_name = :twelfth_board, twelfth_passing_year = :twelfth_year, twelfth_percentage = :twelfth_pct,
            twelfth_marksheet_upload = :twelfth_marksheet,
            ug_university_name = :ug_uni, ug_passing_year = :ug_year, ug_percentage = :ug_pct,
            pg_university_name = :pg_uni, pg_passing_year = :pg_year, pg_percentage = :pg_pct,
            photo = :photo, signature = :signature
            WHERE id = :id AND admin_id = :admin_id";

        $stmt = $db->prepare($sql);
        $stmt->execute([
            ':program_id' => $programId, ':course_id' => $courseId,
            ':session_name' => $sessionName, ':batch' => $batch,
            ':full_name' => $fullName, ':gender' => $gender, ':dob' => $dob,
            ':father_name' => $fatherName, ':mother_name' => $motherName,
            ':mobile' => $mobile, ':email' => $email ?: null, ':address' => $address,
            ':aadhaar_number' => $aadhaarNumber, ':aadhaar_upload' => $aadhaarFilename,
            ':tenth_board' => $tenthBoard, ':tenth_year' => $tenthYear, ':tenth_pct' => $tenthPct,
            ':tenth_marksheet' => $tenthMarksheet,
            ':twelfth_board' => $twelfthBoard, ':twelfth_year' => $twelfthYear, ':twelfth_pct' => $twelfthPct,
            ':twelfth_marksheet' => $twelfthMarksheet,
            ':ug_uni' => $ugUniversity ?: null, ':ug_year' => $ugYear, ':ug_pct' => $ugPct,
            ':pg_uni' => $pgUniversity ?: null, ':pg_year' => $pgYear, ':pg_pct' => $pgPct,
            ':photo' => $photoFilename, ':signature' => $signatureFilename,
            ':id' => $studentId, ':admin_id' => getCurrentUserId(),
        ]);

       echo "<script>alert('Student updated successfully.');window.location='view_student.php?id=$studentId';</script>";
exit;
    }
}

// Use student data for form (or POST data on error)
$s = $_SERVER['REQUEST_METHOD'] === 'POST' ? array_merge($student, $_POST) : $student;
?>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger">
    <strong>Errors:</strong>
    <ul class="mb-0 mt-1">
        <?php foreach ($errors as $e): ?><li><?php echo $e; ?></li><?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<form method="POST" enctype="multipart/form-data">
    <?php echo csrfField(); ?>

    <div class="card mb-4">
        <div class="card-body">
            <div class="form-section-title"><i class="fas fa-user"></i> Basic Information</div>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Full Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="full_name" required value="<?php echo sanitize($s['full_name']); ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Gender <span class="text-danger">*</span></label>
                    <select class="form-select" name="gender" required>
                        <option value="">--</option>
                        <option value="Male" <?php echo $s['gender'] === 'Male' ? 'selected' : ''; ?>>Male</option>
                        <option value="Female" <?php echo $s['gender'] === 'Female' ? 'selected' : ''; ?>>Female</option>
                        <option value="Other" <?php echo $s['gender'] === 'Other' ? 'selected' : ''; ?>>Other</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">DOB <span class="text-danger">*</span></label>
                    <input type="date" class="form-control" name="dob" required value="<?php echo sanitize($s['dob']); ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Father's Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="father_name" required value="<?php echo sanitize($s['father_name']); ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Mother's Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="mother_name" required value="<?php echo sanitize($s['mother_name']); ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Mobile <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="mobile" required pattern="[0-9]{10}" value="<?php echo sanitize($s['mobile']); ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Email</label>
                    <input type="email" class="form-control" name="email" value="<?php echo sanitize($s['email'] ?? ''); ?>">
                </div>
                <div class="col-12">
                    <label class="form-label">Address <span class="text-danger">*</span></label>
                    <textarea class="form-control" name="address" rows="3" required><?php echo sanitize($s['address']); ?></textarea>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <div class="form-section-title"><i class="fas fa-id-card"></i> Aadhaar Details</div>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Aadhaar Number <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="aadhaar_number" required pattern="[0-9]{12}" value="<?php echo sanitize($s['aadhaar_number']); ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Aadhaar Upload</label>
                    <input type="file" class="form-control" name="aadhaar_upload" accept=".jpg,.jpeg,.png,.pdf">
                    <small class="text-muted">Leave empty to keep current file</small>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <div class="form-section-title"><i class="fas fa-graduation-cap"></i> Program</div>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Program <span class="text-danger">*</span></label>
                    <select class="form-select" name="program_id" id="programSelect" required
                            onchange="loadCourses(this.value, 'courseSelect')">
                        <option value="">--</option>
                        <?php foreach ($programs as $p): ?>
                        <option value="<?php echo $p['id']; ?>" <?php echo $s['program_id'] == $p['id'] ? 'selected' : ''; ?>>
                            <?php echo sanitize($p['program_name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Course <span class="text-danger">*</span></label>
                    <select class="form-select" name="course_id" id="courseSelect" required>
                        <option value="">--</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Session <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="session_name" required value="<?php echo sanitize($s['session_name']); ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Batch <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="batch" required value="<?php echo sanitize($s['batch']); ?>">
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <div class="form-section-title"><i class="fas fa-book-open"></i> Educational Qualification</div>
            <h6 class="text-muted mb-3">10th Standard</h6>
            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <label class="form-label">Board <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="tenth_board_name" required value="<?php echo sanitize($s['tenth_board_name']); ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Year <span class="text-danger">*</span></label>
                    <input type="number" class="form-control" name="tenth_passing_year" required value="<?php echo sanitize($s['tenth_passing_year']); ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">% <span class="text-danger">*</span></label>
                    <input type="number" class="form-control" name="tenth_percentage" required step="0.01" value="<?php echo sanitize($s['tenth_percentage']); ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Marksheet</label>
                    <input type="file" class="form-control" name="tenth_marksheet_upload" accept=".jpg,.jpeg,.png,.pdf">
                </div>
            </div>

            <h6 class="text-muted mb-3">12th Standard</h6>
            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <label class="form-label">Board <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="twelfth_board_name" required value="<?php echo sanitize($s['twelfth_board_name']); ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Year <span class="text-danger">*</span></label>
                    <input type="number" class="form-control" name="twelfth_passing_year" required value="<?php echo sanitize($s['twelfth_passing_year']); ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">% <span class="text-danger">*</span></label>
                    <input type="number" class="form-control" name="twelfth_percentage" required step="0.01" value="<?php echo sanitize($s['twelfth_percentage']); ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Marksheet</label>
                    <input type="file" class="form-control" name="twelfth_marksheet_upload" accept=".jpg,.jpeg,.png,.pdf">
                </div>
            </div>

            <h6 class="text-muted mb-3">UG (Optional)</h6>
            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <input type="text" class="form-control" name="ug_university_name" placeholder="University" value="<?php echo sanitize($s['ug_university_name'] ?? ''); ?>">
                </div>
                <div class="col-md-4">
                    <input type="number" class="form-control" name="ug_passing_year" placeholder="Year" value="<?php echo sanitize($s['ug_passing_year'] ?? ''); ?>">
                </div>
                <div class="col-md-4">
                    <input type="number" class="form-control" name="ug_percentage" placeholder="%" step="0.01" value="<?php echo sanitize($s['ug_percentage'] ?? ''); ?>">
                </div>
            </div>

            <h6 class="text-muted mb-3">PG (Optional)</h6>
            <div class="row g-3">
                <div class="col-md-4">
                    <input type="text" class="form-control" name="pg_university_name" placeholder="University" value="<?php echo sanitize($s['pg_university_name'] ?? ''); ?>">
                </div>
                <div class="col-md-4">
                    <input type="number" class="form-control" name="pg_passing_year" placeholder="Year" value="<?php echo sanitize($s['pg_passing_year'] ?? ''); ?>">
                </div>
                <div class="col-md-4">
                    <input type="number" class="form-control" name="pg_percentage" placeholder="%" step="0.01" value="<?php echo sanitize($s['pg_percentage'] ?? ''); ?>">
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <div class="form-section-title"><i class="fas fa-camera"></i> Photo & Signature</div>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Photo</label>
                    <?php if ($student['photo']): ?>
                    <div class="mb-2">
                        <img src="uploads/photos/<?php echo sanitize($student['photo']); ?>" class="rounded" width="80">
                    </div>
                    <?php endif; ?>
                    <input type="file" class="form-control" name="photo" accept=".jpg,.jpeg,.png">
                    <small class="text-muted">Leave empty to keep current</small>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Signature</label>
                    <?php if ($student['signature']): ?>
                    <div class="mb-2">
                        <img src="uploads/signatures/<?php echo sanitize($student['signature']); ?>" class="rounded" width="100">
                    </div>
                    <?php endif; ?>
                    <input type="file" class="form-control" name="signature" accept=".jpg,.jpeg,.png">
                    <small class="text-muted">Leave empty to keep current</small>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary btn-lg"><i class="fas fa-save me-2"></i>Update Student</button>
        <a href="students.php" class="btn btn-secondary btn-lg">Cancel</a>
    </div>
</form>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var ps = document.getElementById('programSelect');
    if (ps && ps.value) {
        loadCourses(ps.value, 'courseSelect', '<?php echo $s['course_id']; ?>');
    }
});
</script>

<?php require_once 'includes/footer.php'; ?>