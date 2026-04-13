<?php
require_once 'includes/db.php';

$db = getDB(); // ← ADD THIS

$search_query = '';
$search_type = '';
$student = null;
$error = '';
$searched = false;

if ($_SERVER['REQUEST_METHOD'] === 'GET' && !empty($_GET['q'])) {
    $searched = true;
    $search_query = trim($_GET['q']);
    $search_type = isset($_GET['type']) ? trim($_GET['type']) : 'enrollment';

    if (empty($search_query)) {
        $error = 'Please enter a search query.';
    } elseif (strlen($search_query) < 3) {
        $error = 'Search query must be at least 3 characters.';
    } else {
        // Sanitize
        $search_query = htmlspecialchars($search_query, ENT_QUOTES, 'UTF-8');

        if ($search_type === 'certificate') {
            // Search by Certificate ID
            $stmt = $db->prepare("
                SELECT s.full_name, s.enrollment_no, s.roll_no, s.photo, s.status,
                       s.father_name, s.gender, s.dob, s.session_name, s.batch,
                       p.program_name, p.duration, c.course_name,
                       cert.certificate_id, cert.issue_date
                FROM certificates cert
                JOIN students s ON cert.student_id = s.id
                JOIN programs p ON s.program_id = p.id
                JOIN courses c ON s.course_id = c.id
                WHERE cert.certificate_id = :query
            ");
            $stmt->execute([':query' => $search_query]);
            $student = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($student) {
                // Get marks for grade calculation
                $marks_stmt = $db->prepare("
                    SELECT SUM(m.marks_obtained) as total_obtained, SUM(sub.total_marks) as total_max
                    FROM marks m
                    JOIN subjects sub ON m.subject_id = sub.id
                    JOIN students s ON m.student_id = s.id
                    JOIN certificates cert ON cert.student_id = s.id
                    WHERE cert.certificate_id = :cert_id
                ");
                $marks_stmt->execute([':cert_id' => $search_query]);
                $marks_data = $marks_stmt->fetch(PDO::FETCH_ASSOC);

                if ($marks_data && $marks_data['total_max'] > 0) {
                    $student['percentage'] = round(($marks_data['total_obtained'] / $marks_data['total_max']) * 100, 2);
                    if ($student['percentage'] >= 75) $student['grade'] = 'A';
                    elseif ($student['percentage'] >= 60) $student['grade'] = 'B';
                    elseif ($student['percentage'] >= 50) $student['grade'] = 'C';
                    else $student['grade'] = 'Fail';
                }
            }
        } else {
            // Search by Enrollment No
            $stmt = $db->prepare("
                SELECT s.full_name, s.enrollment_no, s.roll_no, s.photo, s.status,
                       s.father_name, s.gender, s.dob, s.session_name, s.batch,
                       p.program_name, p.duration, c.course_name,
                       cert.certificate_id, cert.issue_date
                FROM students s
                JOIN programs p ON s.program_id = p.id
                JOIN courses c ON s.course_id = c.id
                LEFT JOIN certificates cert ON cert.student_id = s.id
                WHERE s.enrollment_no = :query
            ");
            $stmt->execute([':query' => $search_query]);
            $student = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($student) {
                // Get marks
                $marks_stmt = $db->prepare("
                    SELECT SUM(m.marks_obtained) as total_obtained, SUM(sub.total_marks) as total_max
                    FROM marks m
                    JOIN subjects sub ON m.subject_id = sub.id
                    JOIN students s ON m.student_id = s.id
                    WHERE s.enrollment_no = :enroll
                ");
                $marks_stmt->execute([':enroll' => $search_query]);
                $marks_data = $marks_stmt->fetch(PDO::FETCH_ASSOC);

                if ($marks_data && $marks_data['total_max'] > 0) {
                    $student['percentage'] = round(($marks_data['total_obtained'] / $marks_data['total_max']) * 100, 2);
                    if ($student['percentage'] >= 75) $student['grade'] = 'A';
                    elseif ($student['percentage'] >= 60) $student['grade'] = 'B';
                    elseif ($student['percentage'] >= 50) $student['grade'] = 'C';
                    else $student['grade'] = 'Fail';
                }
            }
        }

        if (!$student) {
            $error = 'No student found with the provided details.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Student - RISE</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #0d6efd 0%, #0a58ca 100%);
        }

        body {
            background: linear-gradient(135deg, #f0f4ff 0%, #e8ecf4 50%, #f8f9fa 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .verify-hero {
            background: var(--primary-gradient);
            padding: 60px 0 80px;
            color: white;
            position: relative;
            overflow: hidden;
        }

        .verify-hero::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -30%;
            width: 60%;
            height: 200%;
            background: rgba(255, 255, 255, 0.05);
            transform: rotate(20deg);
            border-radius: 50%;
        }

        .verify-hero::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            right: 0;
            height: 40px;
            background: linear-gradient(135deg, #f0f4ff 0%, #e8ecf4 50%, #f8f9fa 100%);
            clip-path: ellipse(55% 100% at 50% 100%);
        }

        .search-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.08);
            margin-top: -40px;
            position: relative;
            z-index: 10;
            border: none;
        }

        .result-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.06);
            border: none;
            overflow: hidden;
        }

        .result-card .card-header {
            background: var(--primary-gradient);
            border: none;
            padding: 20px 25px;
        }

        .status-badge-valid {
            background: linear-gradient(135deg, #198754 0%, #157347 100%);
            color: white;
            padding: 8px 20px;
            border-radius: 50px;
            font-weight: 600;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .status-badge-invalid {
            background: linear-gradient(135deg, #dc3545 0%, #bb2d3b 100%);
            color: white;
            padding: 8px 20px;
            border-radius: 50px;
            font-weight: 600;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .student-photo-verify {
            width: 120px;
            height: 140px;
            object-fit: cover;
            border-radius: 12px;
            border: 3px solid #0d6efd;
            box-shadow: 0 4px 12px rgba(13, 110, 253, 0.2);
        }

        .detail-row {
            display: flex;
            padding: 10px 0;
            border-bottom: 1px solid #f0f0f0;
        }

        .detail-row:last-child {
            border-bottom: none;
        }

        .detail-label {
            font-weight: 600;
            color: #6c757d;
            min-width: 160px;
            font-size: 14px;
        }

        .detail-value {
            color: #212529;
            font-weight: 500;
            font-size: 14px;
        }

        .grade-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            font-weight: 700;
            font-size: 18px;
            color: white;
        }

        .grade-A { background: linear-gradient(135deg, #198754, #157347); }
        .grade-B { background: linear-gradient(135deg, #0d6efd, #0a58ca); }
        .grade-C { background: linear-gradient(135deg, #fd7e14, #e8590c); }
        .grade-Fail { background: linear-gradient(135deg, #dc3545, #bb2d3b); }

        .btn-search {
            background: var(--primary-gradient);
            border: none;
            padding: 12px 30px;
            border-radius: 10px;
            font-weight: 600;
            transition: all 0.3s;
        }

        .btn-search:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(13, 110, 253, 0.3);
        }

        .search-type-btn {
            border: 2px solid #dee2e6;
            background: white;
            color: #6c757d;
            padding: 10px 20px;
            border-radius: 10px;
            font-weight: 500;
            transition: all 0.3s;
            cursor: pointer;
        }

        .search-type-btn:hover,
        .search-type-btn.active {
            border-color: #0d6efd;
            background: rgba(13, 110, 253, 0.05);
            color: #0d6efd;
        }

        .watermark-verified {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-30deg);
            font-size: 80px;
            font-weight: 900;
            opacity: 0.04;
            color: #198754;
            pointer-events: none;
            white-space: nowrap;
        }

        @media (max-width: 768px) {
            .detail-row {
                flex-direction: column;
            }
            .detail-label {
                min-width: auto;
                margin-bottom: 2px;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg" style="background: transparent; position: absolute; top: 0; width: 100%; z-index: 100;">
        <div class="container">
            <a class="navbar-brand text-white fw-bold fs-4" href="index.php">
                <i class="bi bi-rocket-takeoff me-2"></i>RISE
            </a>
            <a href="login.php" class="btn btn-outline-light btn-sm rounded-pill px-3">
                <i class="bi bi-box-arrow-in-right me-1"></i> Login
            </a>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="verify-hero">
        <div class="container text-center" style="padding-top: 30px;">
            <h1 class="display-5 fw-bold mb-2">
                <i class="bi bi-shield-check me-2"></i>Student Verification
            </h1>
            <p class="lead opacity-75">Verify the authenticity of RISE student credentials</p>
        </div>
    </section>

    <!-- Search Section -->
    <div class="container" style="max-width: 800px;">
        <div class="search-card p-4 p-md-5">
            <form method="GET" action="" id="verifyForm">
                <!-- Search Type Toggle -->
                <div class="d-flex justify-content-center gap-3 mb-4">
                    <label class="search-type-btn <?= ($search_type !== 'certificate') ? 'active' : '' ?>" id="btnEnrollment">
                        <input type="radio" name="type" value="enrollment" class="d-none"
                               <?= ($search_type !== 'certificate') ? 'checked' : '' ?>>
                        <i class="bi bi-person-badge me-2"></i>Enrollment No
                    </label>
                    <label class="search-type-btn <?= ($search_type === 'certificate') ? 'active' : '' ?>" id="btnCertificate">
                        <input type="radio" name="type" value="certificate" class="d-none"
                               <?= ($search_type === 'certificate') ? 'checked' : '' ?>>
                        <i class="bi bi-award me-2"></i>Certificate ID
                    </label>
                </div>

                <!-- Search Input -->
                <div class="input-group input-group-lg">
                    <span class="input-group-text bg-white border-end-0" style="border-radius: 10px 0 0 10px;">
                        <i class="bi bi-search text-muted"></i>
                    </span>
                    <input type="text" name="q" class="form-control border-start-0 ps-0"
                           placeholder="Enter Enrollment Number or Certificate ID..."
                           value="<?= htmlspecialchars($search_query) ?>"
                           style="border-radius: 0; font-size: 16px;"
                           required minlength="3" maxlength="50">
                    <button type="submit" class="btn btn-primary btn-search" style="border-radius: 0 10px 10px 0;">
                        <i class="bi bi-search me-1"></i> Verify
                    </button>
                </div>

                <div class="text-center mt-3">
                    <small class="text-muted">
                        <i class="bi bi-info-circle me-1"></i>
                        Enter the enrollment number or certificate ID printed on the student document
                    </small>
                </div>
            </form>
        </div>

        <!-- Results -->
        <?php if ($searched): ?>
            <div class="mt-4 mb-5">
                <?php if ($error): ?>
                    <!-- Not Found -->
                    <div class="result-card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="text-white mb-0">
                                <i class="bi bi-search me-2"></i>Verification Result
                            </h5>
                            <span class="status-badge-invalid">
                                <i class="bi bi-x-circle"></i> NOT FOUND
                            </span>
                        </div>
                        <div class="card-body text-center py-5">
                            <i class="bi bi-exclamation-triangle text-danger" style="font-size: 60px;"></i>
                            <h4 class="mt-3 text-danger">Verification Failed</h4>
                            <p class="text-muted"><?= htmlspecialchars($error) ?></p>
                            <p class="text-muted small">
                                If you believe this is an error, please contact RISE administration.
                            </p>
                        </div>
                    </div>
                <?php elseif ($student): ?>
                    <!-- Found -->
                    <div class="result-card position-relative">
                        <div class="watermark-verified">VERIFIED</div>
                        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <h5 class="text-white mb-0">
                                <i class="bi bi-person-check me-2"></i>Verification Result
                            </h5>
                            <?php if ($student['status'] === 'Approved'): ?>
                                <span class="status-badge-valid">
                                    <i class="bi bi-check-circle-fill"></i> VALID & VERIFIED
                                </span>
                            <?php else: ?>
                                <span class="status-badge-invalid">
                                    <i class="bi bi-clock-fill"></i> PENDING APPROVAL
                                </span>
                            <?php endif; ?>
                        </div>
                        <div class="card-body p-4">
                            <div class="row">
                                <!-- Photo Column -->
                                <div class="col-md-3 text-center mb-3 mb-md-0">
                                    <?php
                                    $photo_exists = !empty($student['photo']) && file_exists('uploads/photos/' . $student['photo']);
                                    ?>
                                    <?php if ($photo_exists): ?>
                                        <img src="uploads/photos/<?= htmlspecialchars($student['photo']) ?>"
                                             alt="Student Photo" class="student-photo-verify">
                                    <?php else: ?>
                                        <div class="student-photo-verify d-flex align-items-center justify-content-center bg-light">
                                            <i class="bi bi-person-fill text-muted" style="font-size: 50px;"></i>
                                        </div>
                                    <?php endif; ?>

                                    <?php if (isset($student['grade'])): ?>
                                        <div class="mt-3">
                                            <span class="grade-badge grade-<?= $student['grade'] ?>">
                                                <?= $student['grade'] ?>
                                            </span>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <!-- Details Column -->
                                <div class="col-md-9">
                                    <div class="detail-row">
                                        <span class="detail-label">
                                            <i class="bi bi-person me-2 text-primary"></i>Student Name
                                        </span>
                                        <span class="detail-value fw-bold">
                                            <?= htmlspecialchars(strtoupper($student['full_name'])) ?>
                                        </span>
                                    </div>

                                    <div class="detail-row">
                                        <span class="detail-label">
                                            <i class="bi bi-people me-2 text-primary"></i>Father's Name
                                        </span>
                                        <span class="detail-value">
                                            <?= htmlspecialchars($student['father_name']) ?>
                                        </span>
                                    </div>

                                    <div class="detail-row">
                                        <span class="detail-label">
                                            <i class="bi bi-hash me-2 text-primary"></i>Enrollment No
                                        </span>
                                        <span class="detail-value">
                                            <code class="fs-6"><?= htmlspecialchars($student['enrollment_no']) ?></code>
                                        </span>
                                    </div>

                                    <div class="detail-row">
                                        <span class="detail-label">
                                            <i class="bi bi-123 me-2 text-primary"></i>Roll No
                                        </span>
                                        <span class="detail-value">
                                            <?= htmlspecialchars($student['roll_no']) ?>
                                        </span>
                                    </div>

                                    <div class="detail-row">
                                        <span class="detail-label">
                                            <i class="bi bi-book me-2 text-primary"></i>Program
                                        </span>
                                        <span class="detail-value">
                                            <?= htmlspecialchars($student['program_name']) ?>
                                        </span>
                                    </div>

                                    <div class="detail-row">
                                        <span class="detail-label">
                                            <i class="bi bi-journal-text me-2 text-primary"></i>Course
                                        </span>
                                        <span class="detail-value">
                                            <?= htmlspecialchars($student['course_name']) ?>
                                        </span>
                                    </div>

                                    <div class="detail-row">
                                        <span class="detail-label">
                                            <i class="bi bi-calendar-event me-2 text-primary"></i>Session
                                        </span>
                                        <span class="detail-value">
                                            <?= htmlspecialchars($student['session_name']) ?>
                                        </span>
                                    </div>

                                    <?php if (isset($student['percentage'])): ?>
                                    <div class="detail-row">
                                        <span class="detail-label">
                                            <i class="bi bi-graph-up me-2 text-primary"></i>Percentage
                                        </span>
                                        <span class="detail-value fw-bold text-success">
                                            <?= $student['percentage'] ?>%
                                        </span>
                                    </div>

                                    <div class="detail-row">
                                        <span class="detail-label">
                                            <i class="bi bi-award me-2 text-primary"></i>Grade
                                        </span>
                                        <span class="detail-value">
                                            <span class="grade-badge grade-<?= $student['grade'] ?>" style="width: 32px; height: 32px; font-size: 14px;">
                                                <?= $student['grade'] ?>
                                            </span>
                                        </span>
                                    </div>
                                    <?php endif; ?>

                                    <?php if (!empty($student['certificate_id'])): ?>
                                    <div class="detail-row">
                                        <span class="detail-label">
                                            <i class="bi bi-file-earmark-check me-2 text-primary"></i>Certificate ID
                                        </span>
                                        <span class="detail-value">
                                            <code class="fs-6"><?= htmlspecialchars($student['certificate_id']) ?></code>
                                        </span>
                                    </div>

                                    <div class="detail-row">
                                        <span class="detail-label">
                                            <i class="bi bi-calendar-check me-2 text-primary"></i>Issue Date
                                        </span>
                                        <span class="detail-value">
                                            <?= date('d F Y', strtotime($student['issue_date'])) ?>
                                        </span>
                                    </div>
                                    <?php endif; ?>

                                    <div class="detail-row">
                                        <span class="detail-label">
                                            <i class="bi bi-shield-check me-2 text-primary"></i>Status
                                        </span>
                                        <span class="detail-value">
                                            <?php if ($student['status'] === 'Approved'): ?>
                                                <span class="badge bg-success rounded-pill px-3 py-2">
                                                    <i class="bi bi-check-circle me-1"></i> Approved & Valid
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-warning text-dark rounded-pill px-3 py-2">
                                                    <i class="bi bi-clock me-1"></i> Pending
                                                </span>
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Verification Footer -->
                        <div class="card-footer bg-light text-center py-3">
                            <small class="text-muted">
                                <i class="bi bi-clock me-1"></i>
                                Verified on: <?= date('d F Y, h:i A') ?> |
                                <i class="bi bi-shield-fill-check text-success me-1"></i>
                                This verification is provided by RISE SaaS Platform
                            </small>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Footer -->
    <footer class="text-center py-4 mt-5">
        <div class="container">
            <p class="text-muted mb-1">
                <i class="bi bi-rocket-takeoff me-1"></i>
                <strong>RISE</strong> - Above The Ordinary
            </p>
            <small class="text-muted">© <?= date('Y') ?> RISE SaaS. All rights reserved.</small>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Search type toggle
        document.querySelectorAll('.search-type-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                document.querySelectorAll('.search-type-btn').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                this.querySelector('input[type="radio"]').checked = true;

                const input = document.querySelector('input[name="q"]');
                if (this.querySelector('input').value === 'certificate') {
                    input.placeholder = 'Enter Certificate ID (e.g., RISE-2024-000001)...';
                } else {
                    input.placeholder = 'Enter Enrollment Number...';
                }
            });
        });
    </script>
</body>
</html>