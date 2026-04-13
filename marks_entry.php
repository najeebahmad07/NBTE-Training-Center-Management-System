<?php
/**
 * RISE - Marks Entry
 * =====================
 */

$pageTitle = 'Marks Entry';
require_once 'includes/header.php';
require_once 'includes/sidebar.php';
requireLogin();

$db = getDB();
$userId = getCurrentUserId();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCSRF();

    $studentId = (int) ($_POST['student_id'] ?? 0);
    $marks = $_POST['marks'] ?? [];

    if ($studentId <= 0 || empty($marks)) {
        setFlashMessage('error', 'Invalid data.');
        header('Location: marks_entry.php');
        exit;
    }

    if (!isSuperAdmin()) {
        verifyStudentOwnership($studentId);
    }

    // Check student is approved
    $stmt = $db->prepare("SELECT * FROM students WHERE id = :id AND status = 'Approved'");
    $stmt->execute([':id' => $studentId]);
    $student = $stmt->fetch();

    if (!$student) {
        setFlashMessage('error', 'Student must be approved before entering marks.');
        header('Location: marks_entry.php');
        exit;
    }

    $db->beginTransaction();
    try {
        // Delete existing marks
        $stmt = $db->prepare("DELETE FROM marks WHERE student_id = :sid");
        $stmt->execute([':sid' => $studentId]);

        // Insert new marks
        $stmtInsert = $db->prepare("INSERT INTO marks (student_id, subject_id, marks_obtained, grade) VALUES (:sid, :sub_id, :marks, :grade)");

        foreach ($marks as $subjectId => $marksObtained) {
            $subjectId = (int) $subjectId;
            $marksObtained = (int) $marksObtained;

            // Get subject total marks
            $stmtSub = $db->prepare("SELECT total_marks FROM subjects WHERE id = :id");
            $stmtSub->execute([':id' => $subjectId]);
            $subject = $stmtSub->fetch();

            if (!$subject) continue;

            if ($marksObtained < 0) $marksObtained = 0;
            if ($marksObtained > $subject['total_marks']) $marksObtained = $subject['total_marks'];

            $pct = ($marksObtained / $subject['total_marks']) * 100;
            $grade = calculateGrade($pct);

            $stmtInsert->execute([
                ':sid' => $studentId,
                ':sub_id' => $subjectId,
                ':marks' => $marksObtained,
                ':grade' => $grade,
            ]);
        }

        $db->commit();
        echo "<script>alert('Marks saved successfully.');window.location='view_student.php?id=$studentId';</script>";
exit;

    } catch (Exception $e) {
        $db->rollBack();
        error_log("Marks entry error: " . $e->getMessage());
        setFlashMessage('error', 'Failed to save marks.');
        header('Location: marks_entry.php');
        exit;
    }
}

// Get approved students
if (isSuperAdmin()) {
    $stmt = $db->query("SELECT s.id, s.full_name, s.enrollment_no, s.program_id, p.program_name FROM students s JOIN programs p ON s.program_id = p.id WHERE s.status = 'Approved' ORDER BY s.full_name");
} else {
    $stmt = $db->prepare("SELECT s.id, s.full_name, s.enrollment_no, s.program_id, p.program_name FROM students s JOIN programs p ON s.program_id = p.id WHERE s.admin_id = :admin_id AND s.status = 'Approved' ORDER BY s.full_name");
    $stmt->execute([':admin_id' => $userId]);
}
$students = $stmt->fetchAll();
?>

<div class="card">
    <div class="card-header">
        <h6 class="mb-0"><i class="fas fa-pen-alt me-2"></i>Marks Entry</h6>
    </div>
    <div class="card-body">
        <?php if (empty($students)): ?>
        <div class="empty-state">
            <i class="fas fa-user-graduate"></i>
            <p>No approved students found. Approve students first to enter marks.</p>
        </div>
        <?php else: ?>

        <form method="POST" id="marksForm">
            <?php echo csrfField(); ?>

            <div class="row mb-4">
                <div class="col-md-6">
                    <label class="form-label">Select Student <span class="text-danger">*</span></label>
                    <select class="form-select" name="student_id" id="studentSelect" required>
                        <option value="">-- Select Student --</option>
                        <?php foreach ($students as $st): ?>
                        <option value="<?php echo $st['id']; ?>" data-program="<?php echo $st['program_id']; ?>">
                            <?php echo sanitize($st['full_name']); ?> (<?php echo sanitize($st['enrollment_no']); ?>) - <?php echo sanitize($st['program_name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Subjects will load here -->
            <div id="subjectsContainer">
                <p class="text-muted">Select a student to load subjects.</p>
            </div>

            <div id="submitSection" style="display:none;" class="mt-4">
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="fas fa-save me-2"></i>Save Marks
                </button>
            </div>
        </form>

        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const studentSelect = document.getElementById('studentSelect');
    if (studentSelect) {
        studentSelect.addEventListener('change', function() {
            const option = this.options[this.selectedIndex];
            const programId = option.getAttribute('data-program');
            const submitSection = document.getElementById('submitSection');

            if (programId) {
                loadSubjects(programId, 'subjectsContainer');
                submitSection.style.display = 'block';
            } else {
                document.getElementById('subjectsContainer').innerHTML = '<p class="text-muted">Select a student.</p>';
                submitSection.style.display = 'none';
            }
        });
    }
});
</script>

<?php require_once 'includes/footer.php'; ?>