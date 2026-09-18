<?php
/**
 * RISE - Marks Entry (TH + PR Support)
 * =====================
 */

$pageTitle = 'Marks Entry';
require_once 'includes/header.php';
require_once 'includes/sidebar.php';
requireLogin();

$db = getDB();
$userId = getCurrentUserId();

/* ================================
   HANDLE FORM SUBMISSION
================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCSRF();

    $studentId  = (int) ($_POST['student_id'] ?? 0);
    $thMarks    = $_POST['th_marks']  ?? [];   // theory marks keyed by subject_id
    $prMarks    = $_POST['pr_marks']  ?? [];   // practical marks keyed by subject_id

    if ($studentId <= 0 || empty($thMarks)) {
        echo "<script>alert('Invalid data.');window.location.href='marks_entry.php';</script>";
        exit;
    }

    if (!isSuperAdmin()) {
        verifyStudentOwnership($studentId);
    }

    // Check student approved
    $stmt = $db->prepare("SELECT * FROM students WHERE id = :id AND status = 'Approved'");
    $stmt->execute([':id' => $studentId]);
    $student = $stmt->fetch();

    if (!$student) {
        echo "<script>alert('Student must be approved before entering marks.');window.location.href='marks_entry.php';</script>";
        exit;
    }

    // Prevent re-entry
    $stmtCheck = $db->prepare("SELECT COUNT(*) as total FROM marks WHERE student_id = :sid");
    $stmtCheck->execute([':sid' => $studentId]);
    $existingMarks = $stmtCheck->fetch();

    if ($existingMarks['total'] > 0) {
        echo "<script>alert('Marks already entered for this student. Re-entry is not allowed.');window.location.href='marks_entry.php';</script>";
        exit;
    }

    $db->beginTransaction();

    try {
        $stmtInsert = $db->prepare("
            INSERT INTO marks (student_id, subject_id, marks_obtained, th_marks, pr_marks, grade)
            VALUES (:sid, :sub_id, :total, :th, :pr, :grade)
        ");

        foreach ($thMarks as $subjectId => $thVal) {
            $subjectId = (int) $subjectId;
            $thVal     = (int) $thVal;
            $prVal     = (int) ($prMarks[$subjectId] ?? 0);

            // Fetch subject details
            $stmtSub = $db->prepare("SELECT total_marks, has_practical, practical_marks FROM subjects WHERE id = :id");
            $stmtSub->execute([':id' => $subjectId]);
            $subject = $stmtSub->fetch();

            if (!$subject) continue;

            $maxTh = (int) $subject['total_marks'];
            $maxPr = (int) ($subject['practical_marks'] ?? 0);

            // Clamp values
            $thVal = max(0, min($thVal, $maxTh));
            $prVal = $subject['has_practical'] ? max(0, min($prVal, $maxPr)) : 0;

            $totalObtained = $thVal + $prVal;
            $totalMax      = $maxTh + $maxPr;

            $pct   = ($totalMax > 0) ? ($totalObtained / $totalMax) * 100 : 0;
            $grade = calculateGrade($pct);

            $stmtInsert->execute([
                ':sid'   => $studentId,
                ':sub_id'=> $subjectId,
                ':total' => $totalObtained,
                ':th'    => $thVal,
                ':pr'    => $prVal,
                ':grade' => $grade,
            ]);
        }

        $db->commit();

        echo "<script>alert('Marks saved successfully. Re-entry is now locked.');window.location.href='marks_entry.php';</script>";
        exit;

    } catch (Exception $e) {
        $db->rollBack();
        error_log('Marks entry error: ' . $e->getMessage());
        echo "<script>alert('Failed to save marks.');window.location.href='marks_entry.php';</script>";
        exit;
    }
}

/* ================================
   FETCH APPROVED STUDENTS
================================ */

if (isSuperAdmin()) {

    $stmt = $db->query("
        SELECT
            s.id,
            s.full_name,
            s.enrollment_no,
            s.program_id,
            s.course_id,
            p.program_name
        FROM students s
        JOIN programs p ON s.program_id = p.id
        WHERE s.status = 'Approved'
        ORDER BY s.full_name
    ");

} else {

    $stmt = $db->prepare("
        SELECT
            s.id,
            s.full_name,
            s.enrollment_no,
            s.program_id,
            s.course_id,
            p.program_name
        FROM students s
        JOIN programs p ON s.program_id = p.id
        WHERE s.admin_id = :admin_id
        AND s.status = 'Approved'
        ORDER BY s.full_name
    ");

    $stmt->execute([
        ':admin_id' => $userId
    ]);
}

$students = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="card">
    <div class="card-header">
        <h6 class="mb-0"><i class="fas fa-pen-alt me-2"></i>Marks Entry</h6>
    </div>
    <div class="card-body">

        <div class="alert alert-warning">
            <strong>Important:</strong> Please fill the marks carefully. After submission, re-entry is not allowed.
        </div>

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
                      <option
    value="<?php echo $st['id']; ?>"
    data-program="<?php echo $st['program_id']; ?>"
    data-course="<?php echo $st['course_id']; ?>">

    <?php
    echo sanitize($st['full_name']);
    echo " | Program=".$st['program_id'];
    echo " | Course=".$st['course_id'];
    ?>

</option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Subjects Load Here -->
            <div id="subjectsContainer">
                <p class="text-muted">Select a student to load subjects.</p>
            </div>

         <div id="submitSection" style="display:none;" class="mt-4">
    <button type="submit" class="btn btn-primary btn-lg">
        <i class="fas fa-save me-2"></i>
        Save Marks
    </button>
</div>
        </form>

        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {

    const studentSelect = document.getElementById('studentSelect');
    if (studentSelect) {
        studentSelect.addEventListener('change', function () {
            const option = this.options[this.selectedIndex];

const programId = option.getAttribute('data-program');
const courseId  = option.getAttribute('data-course');
console.log({
    programId,
    courseId
});
if (programId && courseId) {
    loadSubjectsWithPractical(programId, courseId);
} else {
    document.getElementById('subjectsContainer').innerHTML =
        '<p class="text-muted">Select a student.</p>';

    document.getElementById('submitSection').style.display = 'none';
}
        });
    }

    // Confirm before submit
    const marksForm = document.getElementById('marksForm');
    if (marksForm) {
        marksForm.addEventListener('submit', function (e) {
            const confirmed = confirm(
                'Are you sure?\nPlease confirm one last check.\nAfter saving, marks cannot be edited again.'
            );
            if (!confirmed) e.preventDefault();
        });
    }
});

function loadSubjectsWithPractical(programId, courseId) {
    const container = document.getElementById('subjectsContainer');
    container.innerHTML = '<p class="text-muted"><i class="fas fa-spinner fa-spin me-2"></i>Loading subjects...</p>';

fetch(`ajax_get_subjects.php?program_id=${programId}&course_id=${courseId}`)
        .then(r => r.json())
        .then(subjects => {
            if (!subjects || subjects.length === 0) {
                container.innerHTML = '<div class="alert alert-info">No subjects found for this program.</div>';
                document.getElementById('submitSection').style.display = 'block';
                document.getElementById('submitSection').style.display = 'none';
                
                return;
            }

            let html = `
            <div class="table-responsive">
            <table class="table table-bordered align-middle">
                <thead class="table-dark text-light" >
                    <tr>
                        <th style="color: white;">#</th>
                        <th style="color: white;">Subject</th>
                        <th style="color: white;" class="text-center">TH Max</th>
                        <th style="color: white;" class="text-center">TH Obtained</th>
                        <th style="color: white;" class="text-center">PR Max</th>
                        <th style="color: white;" class="text-center">PR Obtained</th>
                        <th style="color: white;" class="text-center">Total Max</th>
                        <th style="color: white;" class="text-center">Total Obtained</th>
                    </tr>
                </thead>
                <tbody>`;

            subjects.forEach((sub, idx) => {
                const hasPr  = sub.has_practical == 1;
                const maxTh  = parseInt(sub.total_marks) || 0;
                const maxPr  = hasPr ? (parseInt(sub.practical_marks) || 0) : 0;
                const grandMax = maxTh + maxPr;

                html += `
                <tr>
                    <td>${idx + 1}</td>
                    <td>
                        <strong>${escHtml(sub.subject_name)}</strong>
                        ${hasPr ? '<span class="badge bg-success ms-1">TH + PR</span>' : '<span class="badge bg-secondary ms-1">Theory Only</span>'}
                    </td>
                    <td class="text-center text-muted">${maxTh}</td>
                    <td class="text-center">
                        <input type="number"
                               class="form-control form-control-sm text-center th-input"
                               name="th_marks[${sub.id}]"
                               min="0" max="${maxTh}"
                               value="0" required
                               data-max-th="${maxTh}"
                               data-max-pr="${maxPr}"
                               data-sub-id="${sub.id}"
                               onchange="recalcTotal(${sub.id})">
                    </td>
                    <td class="text-center text-muted">${hasPr ? maxPr : '-'}</td>
                    <td class="text-center">
                        ${hasPr
                            ? `<input type="number"
                                      class="form-control form-control-sm text-center pr-input"
                                      name="pr_marks[${sub.id}]"
                                      min="0" max="${maxPr}"
                                      value="0" required
                                      data-sub-id="${sub.id}"
                                      onchange="recalcTotal(${sub.id})">`
                            : `<input type="hidden" name="pr_marks[${sub.id}]" value="0">
                               <span class="text-muted">-</span>`
                        }
                    </td>
                    <td class="text-center fw-bold" id="grand_max_${sub.id}">${grandMax}</td>
                    <td class="text-center">
                        <span class="badge bg-primary fs-6" id="total_display_${sub.id}">0</span>
                        / ${grandMax}
                    </td>
                </tr>`;
            });

            html += '</tbody></table></div>';
            container.innerHTML = html;
        })
        .catch(err => {
            container.innerHTML = '<div class="alert alert-danger">Failed to load subjects.</div>';
            console.error(err);
        });
}

function recalcTotal(subId) {
    const thInput = document.querySelector(`input[name="th_marks[${subId}]"]`);
    const prInput = document.querySelector(`input[name="pr_marks[${subId}]"]`);
    const display = document.getElementById('total_display_' + subId);

    const thVal = parseInt(thInput?.value) || 0;
    const prVal = parseInt(prInput?.value) || 0;
    const total = thVal + prVal;

    if (display) {
        display.textContent = total;
        // Color feedback
        const grandMax = parseInt(thInput?.getAttribute('data-max-th') || 0)
                       + parseInt(thInput?.getAttribute('data-max-pr') || 0);
        const pct = grandMax > 0 ? (total / grandMax) * 100 : 0;
        display.className = 'badge fs-6 ' + (pct >= 50 ? 'bg-success' : 'bg-danger');
    }
}

function escHtml(text) {
    const d = document.createElement('div');
    d.appendChild(document.createTextNode(text));
    return d.innerHTML;
}
</script>

<?php require_once 'includes/footer.php'; ?>