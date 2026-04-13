<?php
/**
 * RISE - Program Management (Super Admin Only)
 * ===============================================
 */



$pageTitle = 'Program Management';
require_once 'includes/header.php';
require_once 'includes/sidebar.php';
requireSuperAdmin();

$db = getDB();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCSRF();
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $programName = sanitize($_POST['program_name'] ?? '');
        $duration = sanitize($_POST['duration'] ?? '');

       if (empty($programName) || empty($duration)) {

    echo "<script>
    alert('All fields are required.');
    window.location.href='program_management.php';
    </script>";

} else {

    $stmt = $db->prepare("INSERT INTO programs (program_name, duration) VALUES (:name, :duration)");
    $stmt->execute([':name' => $programName, ':duration' => $duration]);

    echo "<script>
    alert('Program created successfully.');
    window.location.href='program_management.php';
    </script>";

}

exit;}

    if ($action === 'update') {
        $id = (int) ($_POST['program_id'] ?? 0);
        $programName = sanitize($_POST['program_name'] ?? '');
        $duration = sanitize($_POST['duration'] ?? '');

        if ($id > 0 && !empty($programName) && !empty($duration)) {

    $stmt = $db->prepare("UPDATE programs SET program_name = :name, duration = :duration WHERE id = :id");
    $stmt->execute([
        ':name' => $programName,
        ':duration' => $duration,
        ':id' => $id
    ]);

    echo "<script>
    alert('Program updated successfully.');
    window.location.href='program_management.php';
    </script>";
}

exit;
    }

    if ($action === 'delete') {
    $id = (int) ($_POST['program_id'] ?? 0);
    if ($id > 0) {
        // Check if program has students
        $stmt = $db->prepare("SELECT COUNT(*) FROM students WHERE program_id = :id");
        $stmt->execute([':id' => $id]);
        if ($stmt->fetchColumn() > 0) {
            echo "<script>alert('Cannot delete program with existing students.');window.location='program_management.php';</script>";
        } else {
            $stmt = $db->prepare("DELETE FROM programs WHERE id = :id");
            $stmt->execute([':id' => $id]);
            echo "<script>alert('Program deleted successfully.');window.location='program_management.php';</script>";
        }
    }
    exit;
}}

// Fetch programs with counts
$stmt = $db->query("SELECT p.*, (SELECT COUNT(*) FROM courses WHERE program_id = p.id) as course_count, (SELECT COUNT(*) FROM subjects WHERE program_id = p.id) as subject_count, (SELECT COUNT(*) FROM students WHERE program_id = p.id) as student_count FROM programs p ORDER BY p.created_at DESC");
$programs = $stmt->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h5 class="mb-0">Programs</h5>
        <small class="text-muted"><?php echo count($programs); ?> program(s)</small>
    </div>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createProgramModal">
        <i class="fas fa-plus me-2"></i>Add Program
    </button>
</div>

<div class="card">
    <div class="card-body p-0">
        <?php if (empty($programs)): ?>
        <div class="empty-state">
            <i class="fas fa-graduation-cap"></i>
            <p>No programs found</p>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Program Name</th>
                        <th>Duration</th>
                        <th>Courses</th>
                        <th>Subjects</th>
                        <th>Students</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($programs as $i => $program): ?>
                    <tr>
                        <td><?php echo $i + 1; ?></td>
                        <td><strong><?php echo sanitize($program['program_name']); ?></strong></td>
                        <td><?php echo sanitize($program['duration']); ?></td>
                        <td><span class="badge bg-info"><?php echo $program['course_count']; ?></span></td>
                        <td><span class="badge bg-primary"><?php echo $program['subject_count']; ?></span></td>
                        <td><span class="badge bg-success"><?php echo $program['student_count']; ?></span></td>
                        <td>
                            <button class="btn btn-sm btn-outline-primary"
                                    data-bs-toggle="modal" data-bs-target="#editProgramModal"
                                    onclick="fillEditForm(<?php echo $program['id']; ?>, '<?php echo addslashes($program['program_name']); ?>', '<?php echo addslashes($program['duration']); ?>')">
                                <i class="fas fa-edit"></i>
                            </button>
                            <?php if ($program['student_count'] == 0): ?>
                            <form method="POST" class="d-inline">
                                <?php echo csrfField(); ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="program_id" value="<?php echo $program['id']; ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger"
                                        data-confirm="Are you sure you want to delete this program?">
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
<div class="modal fade" id="createProgramModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <?php echo csrfField(); ?>
                <input type="hidden" name="action" value="create">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-plus me-2"></i>Add Program</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Program Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="program_name" required maxlength="255">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Duration <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="duration" placeholder="e.g. 1 Year, 6 Months" required maxlength="100">
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
<div class="modal fade" id="editProgramModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <?php echo csrfField(); ?>
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="program_id" id="edit_program_id">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Edit Program</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Program Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="program_name" id="edit_program_name" required maxlength="255">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Duration <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="duration" id="edit_duration" required maxlength="100">
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
function fillEditForm(id, name, duration) {
    document.getElementById('edit_program_id').value = id;
    document.getElementById('edit_program_name').value = name;
    document.getElementById('edit_duration').value = duration;
}
</script>

<?php require_once 'includes/footer.php'; ?>