<?php
/**
 * RISE - Course Management (Super Admin Only)
 * ==============================================
 */

$pageTitle = 'Course Management';
require_once 'includes/header.php';
require_once 'includes/sidebar.php';
requireSuperAdmin();

$db = getDB();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCSRF();
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $programId = (int) ($_POST['program_id'] ?? 0);
        $courseName = sanitize($_POST['course_name'] ?? '');

       if ($programId <= 0 || empty($courseName)) {
    echo "<script>alert('All fields are required.');window.location='course_management.php';</script>";
} else {
    $stmt = $db->prepare("INSERT INTO courses (program_id, course_name) VALUES (:pid, :name)");
    $stmt->execute([':pid' => $programId, ':name' => $courseName]);
    echo "<script>alert('Course created successfully.');window.location='course_management.php';</script>";
}
exit;
    }



    if ($action === 'update') {
        $id = (int) ($_POST['course_id'] ?? 0);
        $programId = (int) ($_POST['program_id'] ?? 0);
        $courseName = sanitize($_POST['course_name'] ?? '');

       if ($id > 0 && $programId > 0 && !empty($courseName)) {
    $stmt = $db->prepare("UPDATE courses SET program_id = :pid, course_name = :name WHERE id = :id");
    $stmt->execute([':pid' => $programId, ':name' => $courseName, ':id' => $id]);
    echo "<script>alert('Course updated successfully.');window.location='course_management.php';</script>";
}
exit;
    }

    if ($action === 'delete') {
        $id = (int) ($_POST['course_id'] ?? 0);
        if ($id > 0) {
            $stmt = $db->prepare("SELECT COUNT(*) FROM students WHERE course_id = :id");
            $stmt->execute([':id' => $id]);
           if ($stmt->fetchColumn() > 0) {
    echo "<script>alert('Cannot delete course with existing students.');window.location='course_management.php';</script>";
} else {
    $stmt = $db->prepare("DELETE FROM courses WHERE id = :id");
    $stmt->execute([':id' => $id]);
    echo "<script>alert('Course deleted successfully.');window.location='course_management.php';</script>";
}
exit;}}
}

// Fetch courses
$stmt = $db->query("SELECT c.*, p.program_name, (SELECT COUNT(*) FROM students WHERE course_id = c.id) as student_count FROM courses c JOIN programs p ON c.program_id = p.id ORDER BY p.program_name, c.course_name");
$courses = $stmt->fetchAll();

// Fetch programs for dropdown
$stmtPrograms = $db->query("SELECT * FROM programs ORDER BY program_name");
$programs = $stmtPrograms->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h5 class="mb-0">Courses</h5>
        <small class="text-muted"><?php echo count($courses); ?> course(s)</small>
    </div>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createCourseModal">
        <i class="fas fa-plus me-2"></i>Add Course
    </button>
</div>

<div class="card">
    <div class="card-body p-0">
        <?php if (empty($courses)): ?>
        <div class="empty-state">
            <i class="fas fa-book"></i>
            <p>No courses found</p>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Course Name</th>
                        <th>Program</th>
                        <th>Students</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($courses as $i => $course): ?>
                    <tr>
                        <td><?php echo $i + 1; ?></td>
                        <td><strong><?php echo sanitize($course['course_name']); ?></strong></td>
                        <td><span class="badge bg-primary"><?php echo sanitize($course['program_name']); ?></span></td>
                        <td><?php echo $course['student_count']; ?></td>
                        <td>
                            <button class="btn btn-sm btn-outline-primary"
                                    data-bs-toggle="modal" data-bs-target="#editCourseModal"
                                    onclick="fillEditCourse(<?php echo $course['id']; ?>, <?php echo $course['program_id']; ?>, '<?php echo addslashes($course['course_name']); ?>')">
                                <i class="fas fa-edit"></i>
                            </button>
                            <?php if ($course['student_count'] == 0): ?>
                            <form method="POST" class="d-inline">
                                <?php echo csrfField(); ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="course_id" value="<?php echo $course['id']; ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger"
                                        data-confirm="Delete this course?">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Create Modal -->
<div class="modal fade" id="createCourseModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <?php echo csrfField(); ?>
                <input type="hidden" name="action" value="create">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-plus me-2"></i>Add Course</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Program <span class="text-danger">*</span></label>
                        <select class="form-select" name="program_id" required>
                            <option value="">-- Select Program --</option>
                            <?php foreach ($programs as $p): ?>
                            <option value="<?php echo $p['id']; ?>"><?php echo sanitize($p['program_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Course Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="course_name" required maxlength="255">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editCourseModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <?php echo csrfField(); ?>
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="course_id" id="edit_course_id">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Edit Course</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Program <span class="text-danger">*</span></label>
                        <select class="form-select" name="program_id" id="edit_course_program" required>
                            <option value="">-- Select Program --</option>
                            <?php foreach ($programs as $p): ?>
                            <option value="<?php echo $p['id']; ?>"><?php echo sanitize($p['program_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Course Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="course_name" id="edit_course_name" required maxlength="255">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Update</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function fillEditCourse(id, programId, name) {
    document.getElementById('edit_course_id').value = id;
    document.getElementById('edit_course_program').value = programId;
    document.getElementById('edit_course_name').value = name;
}
</script>

<?php require_once 'includes/footer.php'; ?>