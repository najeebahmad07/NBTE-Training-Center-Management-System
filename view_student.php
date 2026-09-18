<?php
/**
 * RISE - View Student
 * =====================
 */

$pageTitle = 'View Student';
require_once 'includes/header.php';
require_once 'includes/sidebar.php';
requireLogin();

$db = getDB();
$studentId = (int) ($_GET['id'] ?? 0);

if ($studentId <= 0) {
    setFlashMessage('error', 'Invalid student.');
    header('Location: students.php');
    exit;
}

verifyStudentOwnership($studentId);

$stmt = $db->prepare("SELECT s.*, p.program_name, p.duration, c.course_name, a.name as admin_name
    FROM students s
    JOIN programs p ON s.program_id = p.id
    JOIN courses c ON s.course_id = c.id
    JOIN admins a ON s.admin_id = a.id
    WHERE s.id = :id");
$stmt->execute([':id' => $studentId]);
$s = $stmt->fetch();

if (!$s) {
    setFlashMessage('error', 'Student not found.');
    header('Location: students.php');
    exit;
}

// Fetch marks
$stmtMarks = $db->prepare("SELECT m.*, sub.subject_name, sub.total_marks FROM marks m JOIN subjects sub ON m.subject_id = sub.id WHERE m.student_id = :sid ORDER BY sub.subject_name");
$stmtMarks->execute([':sid' => $studentId]);
$marks = $stmtMarks->fetchAll();

// Fetch certificate
$stmtCert = $db->prepare("SELECT * FROM certificates WHERE student_id = :sid");
$stmtCert->execute([':sid' => $studentId]);
$certificate = $stmtCert->fetch();

// Fetch admin notification (to show cert pending notice)
$notifStmt = $db->prepare("
    SELECT * FROM notifications
    WHERE student_id = :sid AND type IN ('certificate_request','certificate_approved','certificate_rejected')
    ORDER BY created_at DESC LIMIT 1
");
$notifStmt->execute([':sid' => $studentId]);
$latestNotif = $notifStmt->fetch();

// Calculate totals
$totalObtained = 0;
$totalMax      = 0;
foreach ($marks as $m) {
    $totalObtained += $m['marks_obtained'];
    $totalMax      += $m['total_marks'];
}
$percentage   = $totalMax > 0 ? ($totalObtained / $totalMax) * 100 : 0;
$overallGrade = calculateGrade($percentage);

// Certificate approval status shorthand
$certApproved = $s['certificate_approved'] ?? 'Pending';
?>

<!-- Student Header -->
<div class="card mb-4">
    <div class="card-body">
        <div class="row align-items-center">
            <div class="col-auto">
                <?php if ($s['photo']): ?>
                <img src="uploads/photos/<?php echo sanitize($s['photo']); ?>" class="student-photo-lg" alt="Photo">
                <?php else: ?>
                <div class="user-avatar" style="width:120px;height:120px;font-size:3rem;border-radius:50%;">
                    <?php echo strtoupper(substr($s['full_name'], 0, 1)); ?>
                </div>
                <?php endif; ?>
            </div>
            <div class="col">
                <h3 class="mb-1"><?php echo sanitize($s['full_name']); ?></h3>
                <div class="d-flex flex-wrap gap-3 mb-2">
                    <span><i class="fas fa-id-badge me-1"></i><?php echo sanitize($s['enrollment_no']); ?></span>
                    <span><i class="fas fa-hashtag me-1"></i><?php echo sanitize($s['roll_no']); ?></span>
                    <span><i class="fas fa-graduation-cap me-1"></i><?php echo sanitize($s['program_name']); ?></span>
                    <span><i class="fas fa-book me-1"></i><?php echo sanitize($s['course_name']); ?></span>
                </div>
                <div class="d-flex gap-2 flex-wrap">
                    <?php if ($s['status'] === 'Approved'): ?>
                    <span class="badge bg-success fs-6">✓ Approved</span>
                    <?php else: ?>
                    <span class="badge bg-warning text-dark fs-6">⏳ Pending</span>
                    <?php endif; ?>

                    <?php if ($s['status'] === 'Approved'): ?>
                        <?php if ($certApproved === 'Approved'): ?>
                        <span class="badge bg-success fs-6"><i class="fas fa-certificate me-1"></i>Certificate Unlocked</span>
                        <?php elseif ($certApproved === 'Rejected'): ?>
                        <span class="badge bg-danger fs-6"><i class="fas fa-times me-1"></i>Certificate Rejected</span>
                        <?php else: ?>
                        <span class="badge bg-secondary fs-6"><i class="fas fa-hourglass-half me-1"></i>Awaiting Super Admin</span>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-auto">
                <div class="d-flex flex-column gap-2">
                    <?php if (!isSuperAdmin()): ?>
                        <?php if ($s['status'] === 'Approved'): ?>

                        <!-- ID Card — always available once approved -->
                        <a href="generate_id_card.php?id=<?php echo $s['id']; ?>"
                           class="btn btn-sm btn-primary" target="_blank">
                            <i class="fas fa-id-card me-1"></i>ID Card
                        </a>

                        <?php if (!empty($marks) && $s['marksheet_approved'] === 'Approved'): ?>

<a href="generate_marksheet.php?id=<?php echo $s['id']; ?>"
   class="btn btn-sm btn-info" target="_blank">
    <i class="fas fa-file-alt me-1"></i>Marksheet
</a>

                        <!-- Certificate — only if Super Admin approved -->
                        <?php if ($certApproved === 'Approved'): ?>
                        <a href="generate_certificate.php?id=<?php echo $s['id']; ?>"
                           class="btn btn-sm btn-success" target="_blank">
                            <i class="fas fa-certificate me-1"></i>Certificate
                        </a>
                        <?php elseif ($certApproved === 'Rejected'): ?>
                        <button class="btn btn-sm btn-danger" disabled title="Certificate rejected by Super Admin">
                            <i class="fas fa-ban me-1"></i>Cert Rejected
                        </button>
                        <?php else: ?>
                        <button class="btn btn-sm btn-secondary" disabled
                                title="Waiting for Super Admin to approve certificate generation">
                            <i class="fas fa-hourglass-half me-1"></i>Cert Pending
                        </button>
                        <?php endif; ?>

                        <?php endif; // marks exist ?>

<button type="button"
        class="btn btn-sm btn-dark"
        onclick="openAttendanceModal(
            <?php echo (int)$s['id']; ?>
        )">
    <i class="fas fa-calendar-check me-1"></i>Attendance
</button>

<!-- =========================================================
     ATTENDANCE SHEET MODAL
========================================================= -->

<div class="modal fade"
     id="attendanceModal"
     tabindex="-1"
     aria-hidden="true">

    <div class="modal-dialog modal-xl modal-dialog-centered">

        <div class="modal-content">

            <div class="modal-header">

                <h5 class="modal-title">
                    <i class="fas fa-calendar-check me-2"></i>
                    Attendance Sheet
                </h5>

                <button type="button"
                        class="btn-close"
                        data-bs-dismiss="modal">
                </button>

            </div>


            <div class="modal-body">

                <div id="attendanceSheetContent">

                    <div class="text-center py-5">

                        <div class="spinner-border text-secondary">
                        </div>

                        <p class="mt-3 mb-0">
                            Loading attendance sheet...
                        </p>

                    </div>

                </div>

            </div>


            <div class="modal-footer">

                <button type="button"
                        class="btn btn-secondary"
                        data-bs-dismiss="modal">

                    <i class="fas fa-times me-1"></i>
                    Close

                </button>


                <button type="button"
                        class="btn btn-dark"
                        onclick="printAttendanceSheet()">

                    <i class="fas fa-print me-1"></i>
                    Print

                </button>

            </div>

        </div>

    </div>

</div>

<script>

function openAttendanceModal(studentId)
{
    const modalElement =
        document.getElementById('attendanceModal');

    const modal =
        new bootstrap.Modal(modalElement);

    const content =
        document.getElementById('attendanceSheetContent');


    content.innerHTML = `
        <div class="text-center py-5">

            <div class="spinner-border text-secondary">
            </div>

            <p class="mt-3">
                Loading attendance sheet...
            </p>

        </div>
    `;


    modal.show();


    fetch(
        'attendance_sheet.php?id=' +
        encodeURIComponent(studentId)
    )

    .then(response => {

        if (!response.ok) {
            throw new Error(
                'Unable to load attendance sheet.'
            );
        }

        return response.text();

    })

    .then(html => {

        content.innerHTML = html;

    })

    .catch(error => {

        content.innerHTML = `
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle me-2"></i>
                ${error.message}
            </div>
        `;

    });
}


function printAttendanceSheet()
{
    const content =
        document.getElementById(
            'attendanceSheetContent'
        ).innerHTML;


    const printWindow =
        window.open(
            '',
            '_blank',
            'width=1200,height=800'
        );


    printWindow.document.write(`

        <!DOCTYPE html>

        <html>

        <head>

            <title>Attendance Sheet</title>

            <style>

                @page {
                    size: A4 landscape;
                    margin: 10mm;
                }


                * {
                    box-sizing: border-box;
                }


                body {

                    margin: 0;

                    padding: 20px;

                    font-family:
                        Arial,
                        Helvetica,
                        sans-serif;

                    background: #fff;

                    color: #000;

                }


                .attendance-print-title {

                    text-align: center;

                    font-size: 22px;

                    font-weight: 700;

                    margin-bottom: 15px;

                }


                .attendance-table {

                    width: 100%;

                    border-collapse: collapse;

                    table-layout: fixed;

                }


                .attendance-table th,
                .attendance-table td {

                    border: 2px solid #777;

                    padding: 7px;

                    text-align: center;

                    vertical-align: middle;

                }


                .attendance-table th {

                    background: #fff;

                    font-size: 14px;

                    font-weight: 700;

                }


                .attendance-table td {

                    height: 70px;

                    font-size: 13px;

                }


                .attendance-table .student-column {

                    width: 240px;

                    text-align: left;

                }


                .attendance-table .photo-column {

                    width: 80px;

                }


                .attendance-table .sno-column {

                    width: 55px;

                }


                .student-photo {

                    width: 60px;

                    height: 65px;

                    object-fit: cover;

                    border: 1px solid #777;

                }


                .student-name {

                    font-weight: 600;

                    font-size: 14px;

                }


                .enrollment {

                    font-size: 13px;

                    margin-top: 3px;

                }


                .paper-column {

                    min-width: 75px;

                }


                .print-footer {

                    margin-top: 15px;

                    font-size: 11px;

                    text-align: right;

                }


            </style>

        </head>


        <body>

            ${content}

        </body>

        </html>

    `);


    printWindow.document.close();


    printWindow.focus();


    setTimeout(function() {

        printWindow.print();

    }, 500);

}

</script>

                        <?php else: ?>
                        <!-- Not yet approved by admin -->
                        <a href="approve_student.php?id=<?php echo $s['id']; ?>"
                           class="btn btn-sm btn-success">
                            <i class="fas fa-check me-1"></i>Approve
                        </a>
                        <?php endif; ?>

                    <a href="edit_student.php?id=<?php echo $s['id']; ?>" class="btn btn-sm btn-warning">
                        <i class="fas fa-edit me-1"></i>Edit
                    </a>
                    <?php endif; // !isSuperAdmin ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Certificate Status Notice for Admin -->
<?php if (!isSuperAdmin() && $s['status'] === 'Approved' && !empty($marks)): ?>
    <?php if ($certApproved === 'Pending'): ?>
    <div class="alert alert-info d-flex align-items-center gap-2 mb-4">
        <i class="fas fa-info-circle fa-lg"></i>
        <div>
            <strong>Marksheet & Certificate Approval Pending</strong> — A request has been sent to the Super Admin.
            The Marksheet & Certificate button will be unlocked once approved.
        </div>
    </div>
    <?php elseif ($certApproved === 'Rejected'): ?>
    <div class="alert alert-danger d-flex align-items-center gap-2 mb-4">
        <i class="fas fa-times-circle fa-lg"></i>
        <div>
            <strong>Certificate Generation Rejected</strong> — Super Admin has rejected the certificate request for this student.
            Please contact the Super Admin for more information.
        </div>
    </div>
    <?php elseif ($certApproved === 'Approved'): ?>
    <div class="alert alert-success d-flex align-items-center gap-2 mb-4">
        <i class="fas fa-check-circle fa-lg"></i>
        <div>
            <strong>Certificate Approved!</strong> — Super Admin has approved certificate generation.
            You can now generate the certificate using the button above.
        </div>
    </div>
    <?php endif; ?>
<?php endif; ?>

<!-- Details -->
<div class="row g-4">
    <!-- Basic Info -->
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header"><h6 class="mb-0"><i class="fas fa-user me-2"></i>Basic Information</h6></div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-6">
                        <div class="detail-label">Gender</div>
                        <div class="detail-value"><?php echo sanitize($s['gender']); ?></div>
                    </div>
                    <div class="col-6">
                        <div class="detail-label">DOB</div>
                        <div class="detail-value"><?php echo date('d M Y', strtotime($s['dob'])); ?></div>
                    </div>
                    <div class="col-6">
                        <div class="detail-label">Father's Name</div>
                        <div class="detail-value"><?php echo sanitize($s['father_name']); ?></div>
                    </div>
                    <div class="col-6">
                        <div class="detail-label">Mother's Name</div>
                        <div class="detail-value"><?php echo sanitize($s['mother_name']); ?></div>
                    </div>
                    <div class="col-6">
                        <div class="detail-label">Mobile</div>
                        <div class="detail-value"><?php echo sanitize($s['mobile']); ?></div>
                    </div>
                    <div class="col-6">
                        <div class="detail-label">Email</div>
                        <div class="detail-value"><?php echo sanitize($s['email'] ?? 'N/A'); ?></div>
                    </div>
                    <div class="col-12">
                        <div class="detail-label">Address</div>
                        <div class="detail-value"><?php echo sanitize($s['address']); ?></div>
                    </div>
                    <div class="col-6">
                        <div class="detail-label">Aadhaar</div>
                        <div class="detail-value"><?php echo sanitize($s['aadhaar_number']); ?></div>
                    </div>
                    <div class="col-6">
                        <div class="detail-label">Session / Batch</div>
                        <div class="detail-value"><?php echo sanitize($s['session_name']); ?> / <?php echo sanitize($s['batch']); ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
 <!-- Education -->
<div class="col-md-6">
    <div class="card h-100 border-0 shadow-sm">

        <div class="card-header bg-white border-bottom py-3">
            <h6 class="mb-0 fw-semibold">
                <i class="fas fa-graduation-cap text-primary me-2"></i>
                Educational Details
            </h6>
        </div>

        <div class="card-body">

            <!-- ================= 10TH ================= -->
            <div class="education-card mb-3">

                <div class="education-title">
                    <div>
                        <span class="education-icon">
                            <i class="fas fa-school"></i>
                        </span>

                        <span>10th Standard</span>
                    </div>

                    <?php if (!empty($s['tenth_percentage'])): ?>
                        <span class="percentage-badge">
                            <?php echo $s['tenth_percentage']; ?>%
                        </span>
                    <?php endif; ?>
                </div>

                <div class="education-details">

                    <div class="education-detail">
                        <span>Institute / School</span>
                        <strong>
                            <?php echo !empty($s['tenth_institute_name'])
                                ? sanitize($s['tenth_institute_name'])
                                : '-'; ?>
                        </strong>
                    </div>

                    <div class="education-detail">
                        <span>Board</span>
                        <strong>
                            <?php echo !empty($s['tenth_board_name'])
                                ? sanitize($s['tenth_board_name'])
                                : '-'; ?>
                        </strong>
                    </div>

                    <div class="education-detail">
                        <span>Registration No.</span>
                        <strong>
                            <?php echo !empty($s['tenth_registration_no'])
                                ? sanitize($s['tenth_registration_no'])
                                : '-'; ?>
                        </strong>
                    </div>

                    <div class="education-detail">
                        <span>Roll No.</span>
                        <strong>
                            <?php echo !empty($s['tenth_roll_no'])
                                ? sanitize($s['tenth_roll_no'])
                                : '-'; ?>
                        </strong>
                    </div>

                    <div class="education-detail">
                        <span>Passing Year</span>
                        <strong>
                            <?php echo !empty($s['tenth_passing_year'])
                                ? $s['tenth_passing_year']
                                : '-'; ?>
                        </strong>
                    </div>

                    <div class="education-detail">
                        <span>Marks</span>
                        <strong>
                            <?php
                            if (
                                $s['tenth_obtained_marks'] !== null &&
                                $s['tenth_total_marks'] !== null
                            ) {
                                echo $s['tenth_obtained_marks'] . ' / ' . $s['tenth_total_marks'];
                            } else {
                                echo '-';
                            }
                            ?>
                        </strong>
                    </div>

                </div>
            </div>


            <!-- ================= 12TH ================= -->
            <div class="education-card mb-3">

                <div class="education-title">
                    <div>
                        <span class="education-icon">
                            <i class="fas fa-school"></i>
                        </span>

                        <span>12th Standard</span>
                    </div>

                    <?php if (!empty($s['twelfth_percentage'])): ?>
                        <span class="percentage-badge">
                            <?php echo $s['twelfth_percentage']; ?>%
                        </span>
                    <?php endif; ?>
                </div>

                <div class="education-details">

                    <div class="education-detail">
                        <span>Institute / School</span>
                        <strong>
                            <?php echo !empty($s['twelfth_institute_name'])
                                ? sanitize($s['twelfth_institute_name'])
                                : '-'; ?>
                        </strong>
                    </div>

                    <div class="education-detail">
                        <span>Board</span>
                        <strong>
                            <?php echo !empty($s['twelfth_board_name'])
                                ? sanitize($s['twelfth_board_name'])
                                : '-'; ?>
                        </strong>
                    </div>

                    <div class="education-detail">
                        <span>Registration No.</span>
                        <strong>
                            <?php echo !empty($s['twelfth_registration_no'])
                                ? sanitize($s['twelfth_registration_no'])
                                : '-'; ?>
                        </strong>
                    </div>

                    <div class="education-detail">
                        <span>Roll No.</span>
                        <strong>
                            <?php echo !empty($s['twelfth_roll_no'])
                                ? sanitize($s['twelfth_roll_no'])
                                : '-'; ?>
                        </strong>
                    </div>

                    <div class="education-detail">
                        <span>Passing Year</span>
                        <strong>
                            <?php echo !empty($s['twelfth_passing_year'])
                                ? $s['twelfth_passing_year']
                                : '-'; ?>
                        </strong>
                    </div>

                    <div class="education-detail">
                        <span>Marks</span>
                        <strong>
                            <?php
                            if (
                                $s['twelfth_obtained_marks'] !== null &&
                                $s['twelfth_total_marks'] !== null
                            ) {
                                echo $s['twelfth_obtained_marks'] . ' / ' . $s['twelfth_total_marks'];
                            } else {
                                echo '-';
                            }
                            ?>
                        </strong>
                    </div>

                </div>
            </div>


            <!-- ================= UG ================= -->
            <?php if (!empty($s['ug_university_name']) || !empty($s['ug_institute_name'])): ?>

                <div class="education-card mb-3">

                    <div class="education-title">
                        <div>
                            <span class="education-icon">
                                <i class="fas fa-university"></i>
                            </span>

                            <span>Undergraduate (UG)</span>
                        </div>

                        <?php if (!empty($s['ug_percentage'])): ?>
                            <span class="percentage-badge">
                                <?php echo $s['ug_percentage']; ?>%
                            </span>
                        <?php endif; ?>
                    </div>

                    <div class="education-details">

                        <div class="education-detail">
                            <span>Institute / College</span>
                            <strong>
                                <?php echo !empty($s['ug_institute_name'])
                                    ? sanitize($s['ug_institute_name'])
                                    : '-'; ?>
                            </strong>
                        </div>

                        <div class="education-detail">
                            <span>University</span>
                            <strong>
                                <?php echo !empty($s['ug_university_name'])
                                    ? sanitize($s['ug_university_name'])
                                    : '-'; ?>
                            </strong>
                        </div>

                        <div class="education-detail">
                            <span>Registration No.</span>
                            <strong>
                                <?php echo !empty($s['ug_registration_no'])
                                    ? sanitize($s['ug_registration_no'])
                                    : '-'; ?>
                            </strong>
                        </div>

                        <div class="education-detail">
                            <span>Roll No.</span>
                            <strong>
                                <?php echo !empty($s['ug_roll_no'])
                                    ? sanitize($s['ug_roll_no'])
                                    : '-'; ?>
                            </strong>
                        </div>

                        <div class="education-detail">
                            <span>Passing Year</span>
                            <strong>
                                <?php echo !empty($s['ug_passing_year'])
                                    ? $s['ug_passing_year']
                                    : '-'; ?>
                            </strong>
                        </div>

                        <div class="education-detail">
                            <span>Marks</span>
                            <strong>
                                <?php
                                if (
                                    $s['ug_obtained_marks'] !== null &&
                                    $s['ug_total_marks'] !== null
                                ) {
                                    echo $s['ug_obtained_marks'] . ' / ' . $s['ug_total_marks'];
                                } else {
                                    echo '-';
                                }
                                ?>
                            </strong>
                        </div>

                    </div>
                </div>

            <?php endif; ?>


            <!-- ================= PG ================= -->
            <?php if (!empty($s['pg_university_name']) || !empty($s['pg_institute_name'])): ?>

                <div class="education-card">

                    <div class="education-title">
                        <div>
                            <span class="education-icon">
                                <i class="fas fa-university"></i>
                            </span>

                            <span>Postgraduate (PG)</span>
                        </div>

                        <?php if (!empty($s['pg_percentage'])): ?>
                            <span class="percentage-badge">
                                <?php echo $s['pg_percentage']; ?>%
                            </span>
                        <?php endif; ?>
                    </div>

                    <div class="education-details">

                        <div class="education-detail">
                            <span>Institute / College</span>
                            <strong>
                                <?php echo !empty($s['pg_institute_name'])
                                    ? sanitize($s['pg_institute_name'])
                                    : '-'; ?>
                            </strong>
                        </div>

                        <div class="education-detail">
                            <span>University</span>
                            <strong>
                                <?php echo !empty($s['pg_university_name'])
                                    ? sanitize($s['pg_university_name'])
                                    : '-'; ?>
                            </strong>
                        </div>

                        <div class="education-detail">
                            <span>Registration No.</span>
                            <strong>
                                <?php echo !empty($s['pg_registration_no'])
                                    ? sanitize($s['pg_registration_no'])
                                    : '-'; ?>
                            </strong>
                        </div>

                        <div class="education-detail">
                            <span>Roll No.</span>
                            <strong>
                                <?php echo !empty($s['pg_roll_no'])
                                    ? sanitize($s['pg_roll_no'])
                                    : '-'; ?>
                            </strong>
                        </div>

                        <div class="education-detail">
                            <span>Passing Year</span>
                            <strong>
                                <?php echo !empty($s['pg_passing_year'])
                                    ? $s['pg_passing_year']
                                    : '-'; ?>
                            </strong>
                        </div>

                        <div class="education-detail">
                            <span>Marks</span>
                            <strong>
                                <?php
                                if (
                                    $s['pg_obtained_marks'] !== null &&
                                    $s['pg_total_marks'] !== null
                                ) {
                                    echo $s['pg_obtained_marks'] . ' / ' . $s['pg_total_marks'];
                                } else {
                                    echo '-';
                                }
                                ?>
                            </strong>
                        </div>

                    </div>
                </div>

            <?php endif; ?>

        </div>
    </div>
</div>

<style>
    .education-card {
    background: #f8f9fa;
    border: 1px solid #e9ecef;
    border-radius: 12px;
    padding: 16px;
    transition: all 0.25s ease;
}

.education-card:hover {
    background: #fff;
    border-color: #d9dee3;
    box-shadow: 0 5px 18px rgba(0, 0, 0, 0.06);
}

.education-title {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 15px;
    padding-bottom: 12px;
    border-bottom: 1px solid #e5e7eb;
    font-size: 14px;
    font-weight: 600;
    color: #212529;
}

.education-title > div {
    display: flex;
    align-items: center;
    gap: 9px;
}

.education-icon {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    background: rgba(13, 110, 253, 0.1);
    color: #0d6efd;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 14px;
}

.percentage-badge {
    background: #e8f7ee;
    color: #198754;
    border-radius: 20px;
    padding: 5px 10px;
    font-size: 12px;
    font-weight: 700;
}

.education-details {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 12px 16px;
}

.education-detail {
    min-width: 0;
}

.education-detail span {
    display: block;
    color: #8a94a6;
    font-size: 11px;
    margin-bottom: 3px;
    text-transform: uppercase;
    letter-spacing: 0.3px;
}

.education-detail strong {
    display: block;
    color: #343a40;
    font-size: 13px;
    font-weight: 600;
    word-break: break-word;
}

@media (max-width: 576px) {
    .education-details {
        grid-template-columns: 1fr;
    }

    .education-card {
        padding: 14px;
    }
}
</style>

<!-- Photo & Signature -->
<div class="card mt-4">
    <div class="card-header">
        <h6 class="mb-0"><i class="fas fa-camera me-2"></i>Photo & Signature</h6>
    </div>
    <div class="card-body">
        <div class="row text-center">
            <div class="col-md-6">
                <h6>Student Photo</h6>
                <?php if ($s['photo']): ?>
                <img src="uploads/photos/<?php echo sanitize($s['photo']); ?>"
                     class="img-fluid border rounded" style="max-height:200px">
                <?php else: ?>
                <p class="text-muted">No Photo Uploaded</p>
                <?php endif; ?>
            </div>
            <div class="col-md-6">
                <h6>Student Signature</h6>
                <?php if ($s['signature']): ?>
                <img src="uploads/signatures/<?php echo sanitize($s['signature']); ?>"
                     class="img-fluid border rounded" style="max-height:120px">
                <?php else: ?>
                <p class="text-muted">No Signature Uploaded</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Documents -->
<div class="card mt-4">
    <div class="card-header">
        <h6 class="mb-0"><i class="fas fa-folder-open me-2"></i>Student Documents</h6>
    </div>
    <div class="card-body">
        <div class="row g-4">
            <!-- Aadhaar -->
            <div class="col-md-4 text-center">
                <h6>Aadhaar Card</h6>
                <?php if ($s['aadhaar_upload']): ?>
                    <?php $file = "uploads/documents/" . $s['aadhaar_upload']; $ext = pathinfo($file, PATHINFO_EXTENSION); ?>
                    <?php if ($ext == 'pdf'): ?>
                        <a href="<?php echo $file; ?>" target="_blank" class="btn btn-outline-primary"><i class="fas fa-file-pdf"></i> View PDF</a>
                    <?php else: ?>
                        <img src="<?php echo $file; ?>" class="img-fluid rounded border" style="max-height:200px">
                    <?php endif; ?>
                <?php else: ?>
                    <p class="text-muted">Not Uploaded</p>
                <?php endif; ?>
            </div>
            <!-- 10th Marksheet -->
            <div class="col-md-4 text-center">
                <h6>10th Marksheet</h6>
                <?php if ($s['tenth_marksheet_upload']): ?>
                    <?php $file = "uploads/marksheets/" . $s['tenth_marksheet_upload']; $ext = pathinfo($file, PATHINFO_EXTENSION); ?>
                    <?php if ($ext == 'pdf'): ?>
                        <a href="<?php echo $file; ?>" target="_blank" class="btn btn-outline-primary"><i class="fas fa-file-pdf"></i> View PDF</a>
                    <?php else: ?>
                        <img src="<?php echo $file; ?>" class="img-fluid rounded border" style="max-height:200px">
                    <?php endif; ?>
                <?php else: ?>
                    <p class="text-muted">Not Uploaded</p>
                <?php endif; ?>
            </div>
            <!-- 12th Marksheet -->
            <div class="col-md-4 text-center">
                <h6>12th Marksheet</h6>
                <?php if ($s['twelfth_marksheet_upload']): ?>
                    <?php $file = "uploads/marksheets/" . $s['twelfth_marksheet_upload']; $ext = pathinfo($file, PATHINFO_EXTENSION); ?>
                    <?php if ($ext == 'pdf'): ?>
                        <a href="<?php echo $file; ?>" target="_blank" class="btn btn-outline-primary"><i class="fas fa-file-pdf"></i> View PDF</a>
                    <?php else: ?>
                        <img src="<?php echo $file; ?>" class="img-fluid rounded border" style="max-height:200px">
                    <?php endif; ?>
                <?php else: ?>
                    <p class="text-muted">Not Uploaded</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Marks -->
<?php if (!empty($marks)): ?>
<div class="card mt-4">
    <div class="card-header"><h6 class="mb-0"><i class="fas fa-pen-alt me-2"></i>Marks</h6></div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr><th>Subject</th><th>Total</th><th>Obtained</th><th>Grade</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($marks as $m): ?>
                    <tr>
                        <td><?php echo sanitize($m['subject_name']); ?></td>
                        <td><?php echo $m['total_marks']; ?></td>
                        <td><?php echo $m['marks_obtained']; ?></td>
                        <td>
                            <span class="badge <?php echo $m['grade'] === 'Fail' ? 'bg-danger' : 'bg-success'; ?>">
                                <?php echo $m['grade']; ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <tr class="fw-bold">
                        <td>Total</td>
                        <td><?php echo $totalMax; ?></td>
                        <td><?php echo $totalObtained; ?></td>
                        <td>
                            <span class="badge <?php echo $overallGrade === 'Fail' ? 'bg-danger' : 'bg-primary'; ?> fs-6">
                                <?php echo number_format($percentage, 2); ?>% (<?php echo $overallGrade; ?>)
                            </span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Certificate Info (if issued) -->
<?php if ($certificate): ?>
<div class="card mt-4">
    <div class="card-body text-center">
        <i class="fas fa-certificate text-success fa-3x mb-2"></i>
        <h5>Certificate Issued</h5>
        <p>Certificate ID: <strong><?php echo sanitize($certificate['certificate_id']); ?></strong></p>
        <p>Issue Date: <?php echo date('d M Y', strtotime($certificate['issue_date'])); ?></p>
    </div>
</div>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>