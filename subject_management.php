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
        $programId      = (int) ($_POST['program_id']      ?? 0);
        $courseId = (int) ($_POST['course_id'] ?? 0);
        $subjectName    = sanitize($_POST['subject_name']  ?? '');
        $totalMarks     = (int) ($_POST['total_marks']     ?? 100);
        $hasPractical   = isset($_POST['has_practical']) ? 1 : 0;
        $practicalMarks = $hasPractical ? (int) ($_POST['practical_marks'] ?? 0) : 0;

       if ($programId <= 0 || empty($subjectName) || $totalMarks < 0) {
            echo "<script>alert('All fields are required.');window.location='subject_management.php';</script>";
        } elseif ($hasPractical && $practicalMarks <= 0) {
            echo "<script>alert('Please enter valid Practical Marks.');window.location='subject_management.php';</script>";
        } else {
            $stmt = $db->prepare("
                INSERT INTO subjects
(program_id, course_id, subject_name, total_marks, has_practical, practical_marks)

VALUES
(:pid, :cid, :name, :marks, :hp, :pm)
            ");
            $stmt->execute([
                ':pid'   => $programId,
                ':cid'   => $courseId,
                ':name'  => $subjectName,
                ':marks' => $totalMarks,
                ':hp'    => $hasPractical,
                ':pm'    => $practicalMarks,
            ]);
            echo "<script>alert('Subject created successfully.');window.location='subject_management.php';</script>";
        }
        exit;
    }

    if ($action === 'update') {
        $id             = (int) ($_POST['subject_id']      ?? 0);
        $programId      = (int) ($_POST['program_id']      ?? 0);
        $subjectName    = sanitize($_POST['subject_name']  ?? '');
        $totalMarks     = (int) ($_POST['total_marks']     ?? 100);
        $hasPractical   = isset($_POST['has_practical']) ? 1 : 0;
        $practicalMarks = $hasPractical ? (int) ($_POST['practical_marks'] ?? 0) : 0;

      if ($id > 0 && $programId > 0 && !empty($subjectName) && $totalMarks >= 0) {
            $stmt = $db->prepare("
                UPDATE subjects
                SET program_id = :pid, subject_name = :name, total_marks = :marks,
                    has_practical = :hp, practical_marks = :pm
                WHERE id = :id
            ");
            $stmt->execute([
                ':pid'   => $programId,
                ':name'  => $subjectName,
                ':marks' => $totalMarks,
                ':hp'    => $hasPractical,
                ':pm'    => $practicalMarks,
                ':id'    => $id,
            ]);
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

$stmt = $db->query("
SELECT
s.*,
p.program_name,
c.course_name

FROM subjects s

JOIN programs p ON s.program_id = p.id
JOIN courses c ON s.course_id = c.id
    ORDER BY s.id DESC
");
$subjects = $stmt->fetchAll();

$stmtPrograms = $db->query("SELECT * FROM programs ORDER BY program_name");
$programs = $stmtPrograms->fetchAll();

$stmtCourses = $db->query("SELECT * FROM courses ORDER BY course_name");
$allCourses = $stmtCourses->fetchAll();
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

<!-- ==================== FILTER BAR ==================== -->
<div class="card mb-3">
    <div class="card-body py-3">
        <div class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label mb-1">Filter by Program</label>
                <select class="form-select" id="filterProgram" onchange="applySubjectFilters()">
                    <option value="">-- All Programs --</option>
                    <?php foreach ($programs as $p): ?>
                    <option value="<?php echo $p['id']; ?>"><?php echo sanitize($p['program_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label mb-1">Filter by Course</label>
                <select class="form-select" id="filterCourse" onchange="applySubjectFilters()">
                    <option value="">-- All Courses --</option>
                    <?php foreach ($allCourses as $fc): ?>
                    <option value="<?php echo $fc['id']; ?>"><?php echo sanitize($fc['course_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <button type="button" class="btn btn-outline-secondary w-100" onclick="clearSubjectFilters()">
                    <i class="fas fa-times me-1"></i>Clear Filters
                </button>
            </div>
        </div>
    </div>
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
                        <th>Course</th>
                        <th>Theory Marks</th>
                        <th>Practical</th>
                        <th>Practical Marks</th>
                        <th>Total Marks</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($subjects as $i => $subject): ?>
                    <?php
                        $theoryMax = (int)$subject['total_marks'];
                        $prMax     = (int)($subject['practical_marks'] ?? 0);
                        $grandTotal = $theoryMax + $prMax;
                    ?>
                    <tr data-program-id="<?php echo $subject['program_id']; ?>" data-course-id="<?php echo $subject['course_id']; ?>">
                        <td><?php echo $i + 1; ?></td>
                        <td><strong><?php echo sanitize($subject['subject_name']); ?></strong></td>
                        <td><span class="badge bg-primary"><?php echo sanitize($subject['program_name']); ?></span></td>
                        <td>
    <span class="badge bg-dark">
        <?php echo sanitize($subject['course_name']); ?>
    </span>
</td>
                        <td><?php echo $theoryMax; ?></td>
                        <td>
                            <?php if ($subject['has_practical']): ?>
                                <span class="badge bg-success">Yes</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">No</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo $subject['has_practical'] ? $prMax : '-'; ?></td>
                        <td><strong><?php echo $grandTotal; ?></strong></td>
                        <td>
                            <button class="btn btn-sm btn-outline-primary"
                                    data-bs-toggle="modal" data-bs-target="#editSubjectModal"
                                    onclick="fillEditSubject(
                                        <?php echo $subject['id']; ?>,
                                        <?php echo $subject['program_id']; ?>,
                                        '<?php echo addslashes($subject['subject_name']); ?>',
                                        <?php echo $theoryMax; ?>,
                                        <?php echo (int)$subject['has_practical']; ?>,
                                        <?php echo $prMax; ?>
                                    )">
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

<!-- ==================== CREATE MODAL ==================== -->
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

    <label class="form-label">
        Program <span class="text-danger">*</span>
    </label>

    <select class="form-select" name="program_id" required>

        <option value="">-- Select Program --</option>

        <?php foreach ($programs as $p): ?>

        <option value="<?php echo $p['id']; ?>">

            <?php echo sanitize($p['program_name']); ?>

        </option>

        <?php endforeach; ?>

    </select>

</div>

<div class="mb-3">

    <label class="form-label">
        Course <span class="text-danger">*</span>
    </label>

    <select class="form-select" name="course_id" required>

        <option value="">-- Select Course --</option>

        <?php
        $courseStmt = $db->query("SELECT * FROM courses ORDER BY course_name");
        $courses = $courseStmt->fetchAll();

        foreach ($courses as $course):
        ?>

        <option value="<?php echo $course['id']; ?>">

            <?php echo sanitize($course['course_name']); ?>

        </option>

        <?php endforeach; ?>

    </select>

</div>
                    <div class="mb-3">
                        <label class="form-label">Subject Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="subject_name" required maxlength="255">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Theory Marks (TH) <span class="text-danger">*</span></label>
                      <input type="number" class="form-control" name="total_marks" value="0" required min="0" max="500">
                    </div>

                    <!-- Practical Checkbox -->
                    <div class="mb-3">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="has_practical"
                                   id="create_has_practical" value="1"
                                   onchange="togglePractical('create_practical_box', this.checked)">
                            <label class="form-check-label fw-bold" for="create_has_practical">
                                <i class="fas fa-flask me-1 text-success"></i> Has Practical (PR)?
                            </label>
                        </div>
                    </div>

                    <!-- Practical Marks (hidden by default) -->
                    <div class="mb-3" id="create_practical_box" style="display:none;">
                        <label class="form-label">Practical Marks (PR) <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" name="practical_marks"
                               id="create_practical_marks" min="1" max="500" value="50">
                        <small class="text-muted">
                            Total subject marks = Theory + Practical
                        </small>
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

<!-- ==================== EDIT MODAL ==================== -->
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
                        <label class="form-label">Theory Marks (TH) <span class="text-danger">*</span></label>
                       <input type="number" class="form-control" name="total_marks" id="edit_subject_marks" required min="0" max="500">
                    </div>

                    <!-- Practical Checkbox -->
                    <div class="mb-3">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="has_practical"
                                   id="edit_has_practical" value="1"
                                   onchange="togglePractical('edit_practical_box', this.checked)">
                            <label class="form-check-label fw-bold" for="edit_has_practical">
                                <i class="fas fa-flask me-1 text-success"></i> Has Practical (PR)?
                            </label>
                        </div>
                    </div>

                    <!-- Practical Marks (hidden by default) -->
                    <div class="mb-3" id="edit_practical_box" style="display:none;">
                        <label class="form-label">Practical Marks (PR) <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" name="practical_marks"
                               id="edit_practical_marks" min="1" max="500">
                        <small class="text-muted">
                            Total subject marks = Theory + Practical
                        </small>
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
function togglePractical(boxId, show) {
    const box = document.getElementById(boxId);
    if (box) {
        box.style.display = show ? 'block' : 'none';
    }
}

function fillEditSubject(id, programId, name, marks, hasPractical, practicalMarks) {
    document.getElementById('edit_subject_id').value    = id;
    document.getElementById('edit_subject_program').value = programId;
    document.getElementById('edit_subject_name').value  = name;
    document.getElementById('edit_subject_marks').value = marks;

    const chk = document.getElementById('edit_has_practical');
    chk.checked = (hasPractical == 1);
    togglePractical('edit_practical_box', chk.checked);

    if (hasPractical == 1) {
        document.getElementById('edit_practical_marks').value = practicalMarks;
    }
}

function applySubjectFilters() {
    const programVal = document.getElementById('filterProgram').value;
    const courseVal  = document.getElementById('filterCourse').value;
    const rows = document.querySelectorAll('table.table tbody tr');

    rows.forEach(row => {
        const rowProgram = row.getAttribute('data-program-id');
        const rowCourse  = row.getAttribute('data-course-id');

        const programMatch = !programVal || rowProgram === programVal;
        const courseMatch  = !courseVal || rowCourse === courseVal;

        row.style.display = (programMatch && courseMatch) ? '' : 'none';
    });
}

function clearSubjectFilters() {
    document.getElementById('filterProgram').value = '';
    document.getElementById('filterCourse').value = '';
    applySubjectFilters();
}
</script>

<?php require_once 'includes/footer.php'; ?>