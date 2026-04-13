<?php
/**
 * RISE - Subject Management (Super Admin Only)
 * ================================================
 */

$pageTitle = 'Subject Management';
require_once 'includes/header.php';
require_once 'includes/sidebar.php';
requireSuperAdmin();

$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCSRF();
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $programId = (int) ($_POST['program_id'] ?? 0);
        $subjectName = sanitize($_POST['subject_name'] ?? '');
        $totalMarks = (int) ($_POST['total_marks'] ?? 100);

       if ($programId <= 0 || empty($subjectName) || $totalMarks <= 0) {
    echo "<script>alert('All fields are required.');window.location='subject_management.php';</script>";
} else {
    $stmt = $db->prepare("INSERT INTO subjects (program_id, subject_name, total_marks) VALUES (:pid, :name, :marks)");
    $stmt->execute([':pid' => $programId, ':name' => $subjectName, ':marks' => $totalMarks]);
    echo "<script>alert('Subject created successfully.');window.location='subject_management.php';</script>";
}
exit;
    }

    if ($action === 'update') {
        $id = (int) ($_POST['subject_id'] ?? 0);
        $programId = (int) ($_POST['program_id'] ?? 0);
        $subjectName = sanitize($_POST['subject_name'] ?? '');
        $totalMarks = (int) ($_POST['total_marks'] ?? 100);

        if ($id > 0 && $programId > 0 && !empty($subjectName) && $totalMarks > 0) {
            $stmt = $db->prepare("UPDATE subjects SET program_id = :pid, subject_name = :name, total_marks = :marks WHERE id = :id");
            $stmt->execute([':pid' => $programId, ':name' => $subjectName, ':marks' => $totalMarks, ':id' => $id]);
           echo "<script>alert('Subject updated successfully.');window.location='subject_management.php';</script>";
}
exit;
    }

    if ($action === 'delete') {
        $id = (int) ($_POST['subject_id'] ?? 0);
        if ($id > 0) {
            $stmt = $db->prepare("SELECT COUNT(*) FROM marks WHERE subject_id = :id");
            $stmt->execute([':id' => $id]);
            if ($stmt->fetchColumn() > 0) {
                setFlashMessage('error', 'Cannot delete subject with existing marks.');
            } else {
                $stmt = $db->prepare("DELETE FROM subjects WHERE id = :id");
                $stmt->execute([':id' => $id]);
               echo "<script>alert('Subject deleted successfully.');window.location='subject_management.php';</script>";
            }
        }
        exit;
    }
}

$stmt = $db->query("SELECT s.*, p.program_name FROM subjects s JOIN programs p ON s.program_id = p.id ORDER BY p.program_name, s.subject_name");
$subjects = $stmt->fetchAll();

$stmtPrograms = $db->query("SELECT * FROM programs ORDER BY program_name");
$programs = $stmtPrograms->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h5 class="mb-0">Subjects</h5>
        <small class="text-muted"><?php echo count($subjects); ?> subject(s)</small>
    </div>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createSubjectModal">
        <i class="fas fa-plus me-2"></i>Add Subject
    </button>
</div>

<div class="card">
    <div class="card-body p-0">
        <?php if (empty($subjects)): ?>
        <div class="empty-state">
            <i class="fas fa-clipboard-list"></i>
            <p>No subjects found</p>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Subject Name</th>
                        <th>Program</th>
                        <th>Total Marks</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($subjects as $i => $subject): ?>
                    <tr>
                        <td><?php echo $i + 1; ?></td>
                        <td><strong><?php echo sanitize($subject['subject_name']); ?></strong></td>
                        <td><span class="badge bg-primary"><?php echo sanitize($subject['program_name']); ?></span></td>
                        <td><?php echo $subject['total_marks']; ?></td>
                        <td>
                            <button class="btn btn-sm btn-outline-primary"
                                    data-bs-toggle="modal" data-bs-target="#editSubjectModal"
                                    onclick="fillEditSubject(<?php echo $subject['id']; ?>, <?php echo $subject['program_id']; ?>, '<?php echo addslashes($subject['subject_name']); ?>', <?php echo $subject['total_marks']; ?>)">
                                <i class="fas fa-edit"></i>
                            </button>
                            <form method="POST" class="d-inline">
                                <?php echo csrfField(); ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="subject_id" value="<?php echo $subject['id']; ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger"
                                        data-confirm="Delete this subject?">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
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
<div class="modal fade" id="createSubjectModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <?php echo csrfField(); ?>
                <input type="hidden" name="action" value="create">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-plus me-2"></i>Add Subject</h5>
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
                        <label class="form-label">Subject Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="subject_name" required maxlength="255">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Total Marks <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" name="total_marks" value="100" required min="1" max="500">
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
<div class="modal fade" id="editSubjectModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <?php echo csrfField(); ?>
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="subject_id" id="edit_subject_id">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Edit Subject</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Program <span class="text-danger">*</span></label>
                        <select class="form-select" name="program_id" id="edit_subject_program" required>
                            <option value="">-- Select --</option>
                            <?php foreach ($programs as $p): ?>
                            <option value="<?php echo $p['id']; ?>"><?php echo sanitize($p['program_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Subject Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="subject_name" id="edit_subject_name" required maxlength="255">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Total Marks <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" name="total_marks" id="edit_subject_marks" required min="1" max="500">
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
function fillEditSubject(id, programId, name, marks) {
    document.getElementById('edit_subject_id').value = id;
    document.getElementById('edit_subject_program').value = programId;
    document.getElementById('edit_subject_name').value = name;
    document.getElementById('edit_subject_marks').value = marks;
}
</script>

<?php require_once 'includes/footer.php'; ?>