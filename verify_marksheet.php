<?php

require_once 'includes/db.php';

$db = getDB();


// ============================================================
// GET ROLL NUMBER
// ============================================================

$enrollment = trim($_GET['enrollment'] ?? '');

if ($enrollment === '') {
    die('Invalid Roll Number');
}


// ============================================================
// GET STUDENT
// ============================================================

$stmt = $db->prepare("
    SELECT *
    FROM students
    WHERE roll_no = :roll_no
    LIMIT 1
");

$stmt->execute([
    ':roll_no' => $enrollment
]);

$student = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$student) {
    die('Student not found.');
}


// ============================================================
// GET CENTER / INSTITUTE NAME
// ============================================================

$centerName = 'RELIABLE INCLUSIVE SKILL EDUCATION';

try {

    $centerStmt = $db->prepare("
        SELECT college_name
        FROM admins
        WHERE id = :admin_id
        LIMIT 1
    ");

    $centerStmt->execute([
        ':admin_id' => $student['admin_id']
    ]);

    $center = $centerStmt->fetch(PDO::FETCH_ASSOC);

    if (!empty($center['college_name'])) {
        $centerName = $center['college_name'];
    }

} catch (Exception $e) {

    // Keep default center name

}


// ============================================================
// GET COURSE NAME + DURATION
// ============================================================

$courseName = '';
$courseDuration = '';

try {

    // Course name
    $courseStmt = $db->prepare("
        SELECT course_name
        FROM courses
        WHERE id = :course_id
        LIMIT 1
    ");

    $courseStmt->execute([
        ':course_id' => $student['course_id']
    ]);

    $course = $courseStmt->fetch(PDO::FETCH_ASSOC);

    if (!empty($course['course_name'])) {
        $courseName = $course['course_name'];
    }


    // Program duration
    $programStmt = $db->prepare("
        SELECT duration
        FROM programs
        WHERE id = :program_id
        LIMIT 1
    ");

    $programStmt->execute([
        ':program_id' => $student['program_id']
    ]);

    $program = $programStmt->fetch(PDO::FETCH_ASSOC);

    if (!empty($program['duration'])) {
        $courseDuration = $program['duration'];
    }

} catch (Exception $e) {

    $courseName = '';
    $courseDuration = '';

}


// ============================================================
// GET SUBJECT-WISE MARKS
// ============================================================

$marksStmt = $db->prepare("
    SELECT
        m.id,
        m.student_id,
        m.subject_id,
        m.marks_obtained,
        m.th_marks,
        m.pr_marks,
        m.grade,

        sub.subject_name,
        sub.total_marks,
        sub.has_practical,
        sub.practical_marks AS pr_max

    FROM marks m

    INNER JOIN subjects sub
        ON sub.id = m.subject_id

    WHERE m.student_id = :student_id

    ORDER BY sub.subject_name ASC
");

$marksStmt->execute([
    ':student_id' => $student['id']
]);

$marks = $marksStmt->fetchAll(PDO::FETCH_ASSOC);


if (empty($marks)) {
    die('No marks found for this student.');
}


// ============================================================
// CHECK PRACTICAL
// ============================================================

$hasPractical = false;

foreach ($marks as $mark) {

    if (
        !empty($mark['has_practical']) ||
        (int)$mark['pr_max'] > 0
    ) {

        $hasPractical = true;

        break;
    }
}


// ============================================================
// TOTALS
// ============================================================

$totalMarks = 0;
$totalObtained = 0;

$totalTheoryMax = 0;
$totalPracticalMax = 0;

$totalTheoryObtained = 0;
$totalPracticalObtained = 0;


// ============================================================
// PROCESS MARKS
// ============================================================

foreach ($marks as &$mark) {

    // Theory maximum
    $thMax = (int)($mark['total_marks'] ?? 0);


    // Practical maximum
    $prMax = 0;

    if (
        !empty($mark['has_practical']) ||
        (int)($mark['pr_max'] ?? 0) > 0
    ) {

        $prMax = (int)($mark['pr_max'] ?? 0);
    }


    // Minimum marks
    $thMin = round($thMax * 0.35);

    $prMin = $prMax > 0
        ? round($prMax * 0.35)
        : 0;


    // Obtained marks
    $thObtained = (int)($mark['th_marks'] ?? 0);

    $prObtained = (int)($mark['pr_marks'] ?? 0);


    // Original total obtained
    $marksObtained = (int)(
        $mark['marks_obtained'] ?? 0
    );


    // Subject maximum
    $subjectMax = $thMax + $prMax;


    // Store values
    $mark['th_max_display'] = $thMax;
    $mark['th_min_display'] = $thMin;

    $mark['pr_max_display'] = $prMax;
    $mark['pr_min_display'] = $prMin;

    $mark['th_obtained_display'] = $thObtained;
    $mark['pr_obtained_display'] = $prObtained;

    $mark['total_max_display'] = $subjectMax;
    $mark['total_obtained_display'] = $marksObtained;


    // Totals
    $totalMarks += $subjectMax;

    $totalObtained += $marksObtained;

    $totalTheoryMax += $thMax;

    $totalPracticalMax += $prMax;

    $totalTheoryObtained += $thObtained;

    $totalPracticalObtained += $prObtained;
}

unset($mark);


// ============================================================
// PERCENTAGE
// ============================================================

$percentage = 0;

if ($totalMarks > 0) {

    $percentage = round(
        ($totalObtained / $totalMarks) * 100,
        2
    );
}


// ============================================================
// RESULT STATUS
// ============================================================

$resultStatus = $percentage >= 40
    ? 'PASS'
    : 'FAIL';

?>


<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        Result -
        <?php echo htmlspecialchars($student['full_name']); ?>
    </title>


    <style>

        /* =========================================================
           NBTE COLORS
        ========================================================= */

        :root {

            --nbte-navy: #062A5A;
            --nbte-gold: #D49729;

            --nbte-light-gold: #FBF5E8;

            --text-dark: #1F2937;
            --text-muted: #6B7280;

            --border: #D9DEE5;

            --white: #FFFFFF;

            --success: #16834B;
            --danger: #C62828;

        }


        /* =========================================================
           GENERAL
        ========================================================= */

        * {
            box-sizing: border-box;
        }


        html {
            scroll-behavior: smooth;
        }


        body {

            margin: 0;

            padding: 30px 15px;

            background:
                linear-gradient(
                    135deg,
                    #F8F9FC 0%,
                    #FFFFFF 55%,
                    #FBF6EA 100%
                );

            font-family:
                Arial,
                Helvetica,
                sans-serif;

            color: var(--text-dark);

        }


        .result-wrapper {

            max-width: 1150px;

            margin: auto;

        }


        /* =========================================================
           PRINT BUTTON
        ========================================================= */

        .print-section {

            display: flex;

            justify-content: flex-end;

            margin-bottom: 15px;

        }


        .print-btn {

            border: none;

            background: var(--nbte-navy);

            color: #FFFFFF;

            padding: 11px 20px;

            border-radius: 7px;

            font-size: 13px;

            font-weight: 700;

            cursor: pointer;

            transition: all 0.25s ease;

        }


        .print-btn:hover {

            background: var(--nbte-gold);

            box-shadow:
                0 6px 15px rgba(212, 151, 41, 0.25);

        }


        /* =========================================================
           MAIN RESULT CARD
        ========================================================= */

        .result-card {

            background: var(--white);

            border: 1px solid var(--border);

            border-radius: 14px;

            overflow: hidden;

            box-shadow:
                0 12px 35px rgba(6, 42, 90, 0.10);

        }


        .result-card-top {

            height: 7px;

            background:
                linear-gradient(
                    to right,
                    var(--nbte-navy) 0%,
                    var(--nbte-navy) 70%,
                    var(--nbte-gold) 70%,
                    var(--nbte-gold) 100%
                );

        }


        .result-content {

            padding: 28px;

        }


        /* =========================================================
           HEADER
        ========================================================= */

        .result-header {

            text-align: center;

            padding-bottom: 22px;

            border-bottom: 1px solid var(--border);

            margin-bottom: 22px;

        }


        .result-header h1 {

            margin: 0;

            color: var(--nbte-navy);

            font-size: 28px;

            font-weight: 800;

            text-transform: uppercase;

            letter-spacing: 0.3px;

        }


        .result-header .result-title {

            margin-top: 7px;

            color: var(--nbte-gold);

            font-size: 18px;

            font-weight: 700;

        }


        .result-header .result-subtitle {

            margin-top: 4px;

            color: var(--text-muted);

            font-size: 12px;

        }


        /* =========================================================
           STUDENT AREA
        ========================================================= */

        .student-area {

            display: flex;

            gap: 20px;

            padding: 20px;

            background: #FCFCFD;

            border: 1px solid var(--border);

            border-radius: 10px;

            margin-bottom: 18px;

        }


        /* =========================================================
           PHOTO
        ========================================================= */

        .student-photo-box {

            flex: 0 0 115px;

        }


        .student-photo {

            width: 115px;

            height: 145px;

            object-fit: cover;

            display: block;

            border: 3px solid var(--nbte-gold);

            border-radius: 7px;

            background: #F1F3F5;

        }


        .student-photo-placeholder {

            width: 115px;

            height: 145px;

            display: flex;

            align-items: center;

            justify-content: center;

            border: 3px solid var(--nbte-gold);

            border-radius: 7px;

            background: var(--nbte-light-gold);

            color: var(--nbte-navy);

            font-size: 38px;

        }


        /* =========================================================
           STUDENT DETAILS
        ========================================================= */

        .student-details {

            width: 100%;

            border-collapse: collapse;

        }


        .student-details td {

            border: 1px solid #FFFFFF;

            padding: 8px 10px;

            font-size: 12px;

        }


        .student-details .label {

            width: 145px;

            background: var(--nbte-navy);

            color: #FFFFFF;

            font-weight: 700;

            white-space: nowrap;

        }


        .student-details .value {

            background: var(--nbte-light-gold);

            color: var(--text-dark);

            font-weight: 600;

        }


        /* =========================================================
           ACADEMIC INFORMATION
        ========================================================= */

        .info-grid {

            display: grid;

            grid-template-columns:
                repeat(4, 1fr);

            gap: 1px;

            background: #FFFFFF;

            border: 1px solid var(--border);

            margin-bottom: 18px;

            border-radius: 8px;

            overflow: hidden;

        }


        .info-item {

            text-align: center;

            padding: 10px 8px;

            background: var(--nbte-light-gold);

        }


        .info-item .label {

            display: block;

            color: var(--text-muted);

            font-size: 10px;

            font-weight: 700;

            text-transform: uppercase;

            margin-bottom: 4px;

        }


        .info-item .value {

            display: block;

            color: var(--nbte-navy);

            font-size: 12px;

            font-weight: 800;

        }


        /* =========================================================
           INSTITUTE
        ========================================================= */

        .institute-box {

            display: grid;

            grid-template-columns: 200px 1fr;

            border: 1px solid var(--border);

            border-radius: 8px;

            overflow: hidden;

            margin-bottom: 22px;

        }


        .institute-label {

            background: var(--nbte-navy);

            color: #FFFFFF;

            padding: 10px 12px;

            font-size: 11px;

            font-weight: 700;

            text-transform: uppercase;

        }


        .institute-value {

            background: var(--nbte-light-gold);

            color: var(--nbte-navy);

            padding: 10px 12px;

            font-size: 11px;

            font-weight: 800;

            text-transform: uppercase;

        }


        /* =========================================================
           MARKS SECTION TITLE
        ========================================================= */

        .section-title {

            display: flex;

            align-items: center;

            gap: 10px;

            margin-bottom: 10px;

        }


        .section-title-line {

            width: 5px;

            height: 25px;

            background: var(--nbte-gold);

            border-radius: 3px;

        }


        .section-title h2 {

            margin: 0;

            color: var(--nbte-navy);

            font-size: 17px;

            font-weight: 800;

        }


        /* =========================================================
           MARKS TABLE
        ========================================================= */

        .marks-scroll {

            width: 100%;

            overflow-x: auto;

        }


        .marks-table {

            width: 100%;

            border-collapse: collapse;

            font-size: 11px;

            min-width: 750px;

        }


        .marks-table thead th {

            background: var(--nbte-navy);

            color: #FFFFFF;

            padding: 10px 7px;

            border: 1px solid #FFFFFF;

            text-align: center;

            font-weight: 700;

            white-space: nowrap;

        }


        .marks-table tbody td {

            padding: 8px 7px;

            border: 1px solid #FFFFFF;

            vertical-align: middle;

        }


        .marks-table tbody tr:nth-child(odd) td {

            background: #F8F9FC;

        }


        .marks-table tbody tr:nth-child(even) td {

            background: var(--nbte-light-gold);

        }


        .marks-table tbody tr:hover td {

            background: #F2E7CC;

        }


        .marks-table .sno {

            width: 50px;

            text-align: center;

            font-weight: 700;

            color: var(--nbte-navy);

        }


        .marks-table .subject {

            text-align: left;

            min-width: 250px;

            color: var(--text-dark);

            font-weight: 600;

        }


        .marks-table .paper {

            width: 90px;

            text-align: center;

        }


        .marks-table .min,

        .marks-table .max,

        .marks-table .marks,

        .marks-table .grade {

            width: 80px;

            text-align: center;

        }


        .obtained-box {

            display: inline-block;

            min-width: 40px;

            padding: 4px 8px;

            background: #FFFFFF;

            border: 1px solid #D5D9DE;

            border-radius: 4px;

            font-weight: 800;

            color: var(--nbte-navy);

        }


        .grade-value {

            display: inline-block;

            min-width: 32px;

            padding: 4px 8px;

            background: var(--nbte-navy);

            color: #FFFFFF;

            border-radius: 4px;

            font-weight: 800;

        }


        /* =========================================================
           TOTALS
        ========================================================= */

        .totals-grid {

            display: grid;

            grid-template-columns:
                repeat(3, 1fr);

            gap: 12px;

            margin-top: 18px;

        }


        .total-box {

            border: 1px solid var(--border);

            border-radius: 8px;

            overflow: hidden;

        }


        .total-label {

            background: var(--nbte-navy);

            color: #FFFFFF;

            padding: 8px;

            text-align: center;

            font-size: 10px;

            font-weight: 700;

        }


        .total-value {

            background: var(--nbte-light-gold);

            color: var(--nbte-navy);

            padding: 12px 8px;

            text-align: center;

            font-size: 18px;

            font-weight: 800;

        }


        /* =========================================================
           RESULT STATUS
        ========================================================= */

        .result-status {

            margin-top: 18px;

            display: flex;

            justify-content: center;

        }


        .status-badge {

            min-width: 150px;

            padding: 10px 25px;

            border-radius: 7px;

            text-align: center;

            font-size: 15px;

            font-weight: 800;

            letter-spacing: 0.5px;

        }


        .status-pass {

            background: #E8F6EF;

            color: var(--success);

            border: 1px solid #B9E5CE;

        }


        .status-fail {

            background: #FDECEC;

            color: var(--danger);

            border: 1px solid #F2C1C1;

        }


        /* =========================================================
           FOOTER
        ========================================================= */

        .result-footer {

            margin-top: 25px;

            padding-top: 15px;

            border-top: 1px solid var(--border);

            text-align: center;

            color: var(--text-muted);

            font-size: 10px;

        }


        .result-footer strong {

            color: var(--nbte-navy);

        }


        .result-footer .gold {

            color: var(--nbte-gold);

        }


        /* =========================================================
           MOBILE
        ========================================================= */

        @media (max-width: 768px) {

            body {

                padding: 15px 8px;

            }


            .result-content {

                padding: 18px 12px;

            }


            .print-section {

                justify-content: center;

            }


            .student-area {

                flex-direction: column;

                align-items: center;

                padding: 15px;

            }


            .student-photo-box {

                flex: none;

            }


            .student-details {

                font-size: 11px;

            }


            .student-details .label {

                width: 120px;

            }


            .student-details td {

                padding: 7px 6px;

            }


            .info-grid {

                grid-template-columns:
                    repeat(2, 1fr);

            }


            .institute-box {

                grid-template-columns: 1fr;

            }


            .institute-label {

                text-align: center;

            }


            .institute-value {

                text-align: center;

            }


            .totals-grid {

                grid-template-columns: 1fr;

            }


            .result-header h1 {

                font-size: 21px;

            }


            .result-header .result-title {

                font-size: 16px;

            }

        }


        @media (max-width: 480px) {

            .info-grid {

                grid-template-columns: 1fr 1fr;

            }


            .student-photo {

                width: 100px;

                height: 125px;

            }


            .student-photo-placeholder {

                width: 100px;

                height: 125px;

            }


            .student-details {

                font-size: 10px;

            }


            .student-details .label {

                width: 100px;

            }


            .result-header h1 {

                font-size: 18px;

            }

        }


        /* =========================================================
           PRINT
        ========================================================= */

        @media print {

            @page {

                size: A4 landscape;

                margin: 8mm;

            }


            body {

                padding: 0;

                margin: 0;

                background: #FFFFFF;

            }


            .result-wrapper {

                max-width: 100%;

            }


            .print-section {

                display: none !important;

            }


            .result-card {

                border: 1px solid #999;

                box-shadow: none;

                border-radius: 0;

            }


            .result-card-top {

                height: 5px;

            }


            .result-content {

                padding: 12px;

            }


            .result-header {

                padding-bottom: 10px;

                margin-bottom: 12px;

            }


            .result-header h1 {

                font-size: 21px;

            }


            .result-header .result-title {

                font-size: 15px;

            }


            .student-area {

                padding: 10px;

                margin-bottom: 10px;

            }


            .student-photo {

                width: 80px;

                height: 100px;

            }


            .student-photo-placeholder {

                width: 80px;

                height: 100px;

            }


            .student-details td {

                padding: 4px 6px;

                font-size: 9px;

            }


            .info-grid {

                margin-bottom: 10px;

            }


            .info-item {

                padding: 6px;

            }


            .info-item .label {

                font-size: 8px;

            }


            .info-item .value {

                font-size: 9px;

            }


            .institute-box {

                margin-bottom: 10px;

            }


            .institute-label,

            .institute-value {

                padding: 6px 8px;

                font-size: 9px;

            }


            .section-title {

                margin-bottom: 6px;

            }


            .section-title h2 {

                font-size: 13px;

            }


            .marks-table {

                font-size: 8px;

            }


            .marks-table thead th {

                padding: 5px 4px;

            }


            .marks-table tbody td {

                padding: 4px;

            }


            .obtained-box,

            .grade-value {

                padding: 2px 5px;

            }


            .totals-grid {

                margin-top: 10px;

                gap: 7px;

            }


            .total-label {

                padding: 5px;

                font-size: 8px;

            }


            .total-value {

                padding: 7px;

                font-size: 13px;

            }


            .result-status {

                margin-top: 8px;

            }


            .status-badge {

                padding: 6px 18px;

                font-size: 11px;

            }


            .result-footer {

                margin-top: 10px;

                padding-top: 7px;

                font-size: 8px;

            }


            .marks-table tbody tr:nth-child(odd) td {

                background: #F8F9FC !important;

                -webkit-print-color-adjust: exact;

                print-color-adjust: exact;

            }


            .marks-table tbody tr:nth-child(even) td {

                background: #FBF5E8 !important;

                -webkit-print-color-adjust: exact;

                print-color-adjust: exact;

            }


            .marks-table thead th,

            .student-details .label,

            .total-label,

            .grade-value {

                background: #062A5A !important;

                color: #FFFFFF !important;

                -webkit-print-color-adjust: exact;

                print-color-adjust: exact;

            }


            .student-details .value,

            .info-item,

            .institute-value,

            .total-value {

                background: #FBF5E8 !important;

                -webkit-print-color-adjust: exact;

                print-color-adjust: exact;

            }

        }

    </style>

</head>


<body>


<div class="result-wrapper">


    <!-- =====================================================
         PRINT BUTTON
    ====================================================== -->

    <div class="print-section">

        <button
            type="button"
            class="print-btn"
            onclick="window.print()"
        >
            🖨 Print Result
        </button>

    </div>


    <!-- =====================================================
         ONE RESULT CARD
    ====================================================== -->

    <div class="result-card">


        <div class="result-card-top"></div>


        <div class="result-content">


            <!-- =================================================
                 RESULT HEADER
            ================================================== -->

            <div class="result-header">

                <h1>
                    National Board for Technical Education
                </h1>

                <div class="result-title">
                    RESULT
                </div>

                <div class="result-subtitle">
                    Statement of Marks — Web Copy
                </div>

            </div>


            <!-- =================================================
                 STUDENT INFORMATION
            ================================================== -->

            <div class="student-area">


                <!-- PHOTO -->

                <div class="student-photo-box">

                    <?php if (!empty($student['photo'])): ?>

                        <img
                            src="uploads/photos/<?php echo htmlspecialchars($student['photo']); ?>"
                            class="student-photo"
                            alt="Student Photo"
                        >

                    <?php else: ?>

                        <div class="student-photo-placeholder">
                            👤
                        </div>

                    <?php endif; ?>

                </div>


                <!-- DETAILS -->

                <div style="width:100%;">

                    <table class="student-details">

                        <tr>

                            <td class="label">
                                Name
                            </td>

                            <td class="value">

                                <?php

                                echo htmlspecialchars(
                                    strtoupper(
                                        $student['full_name'] ?? ''
                                    )
                                );

                                ?>

                            </td>

                        </tr>


                        <tr>

                            <td class="label">
                                Father Name
                            </td>

                            <td class="value">

                                <?php

                                echo htmlspecialchars(
                                    $student['father_name'] ?? '-'
                                );

                                ?>

                            </td>

                        </tr>


                        <tr>

                            <td class="label">
                                Mother Name
                            </td>

                            <td class="value">

                                <?php

                                echo htmlspecialchars(
                                    $student['mother_name'] ?? '-'
                                );

                                ?>

                            </td>

                        </tr>


                        <tr>

                            <td class="label">
                                Date of Birth
                            </td>

                            <td class="value">

                                <?php

                                if (!empty($student['dob'])) {

                                    echo date(
                                        'd-m-Y',
                                        strtotime(
                                            $student['dob']
                                        )
                                    );

                                } else {

                                    echo '-';

                                }

                                ?>

                            </td>

                        </tr>


                        <tr>

                            <td class="label">
                                Course
                            </td>

                            <td class="value">

                                <?php

                                echo htmlspecialchars(
                                    $courseName ?: '-'
                                );

                                ?>

                            </td>

                        </tr>


                        <tr>

                            <td class="label">
                                Course Duration
                            </td>

                            <td class="value">

                                <?php

                                echo htmlspecialchars(
                                    $courseDuration ?: '-'
                                );

                                ?>

                            </td>

                        </tr>

                    </table>

                </div>

            </div>


            <!-- =================================================
                 YEAR / SESSION / ENROLLMENT / ROLL
            ================================================== -->

            <div class="info-grid">


                <div class="info-item">

                    <span class="label">
                        Year
                    </span>

                    <span class="value">

                        <?php

                        echo htmlspecialchars(
                            $student['batch'] ?? '-'
                        );

                        ?>

                    </span>

                </div>


                <div class="info-item">

                    <span class="label">
                        Session
                    </span>

                    <span class="value">

                        <?php

                        echo htmlspecialchars(
                            $student['session_name'] ?? '-'
                        );

                        ?>

                    </span>

                </div>


                <div class="info-item">

                    <span class="label">
                        Enrollment No.
                    </span>

                    <span class="value">

                        <?php

                        echo htmlspecialchars(
                            $student['enrollment_no'] ?? '-'
                        );

                        ?>

                    </span>

                </div>


                <div class="info-item">

                    <span class="label">
                        Roll No.
                    </span>

                    <span class="value">

                        <?php

                        echo htmlspecialchars(
                            $student['roll_no'] ?? '-'
                        );

                        ?>

                    </span>

                </div>


            </div>


            <!-- =================================================
                 INSTITUTE
            ================================================== -->

            <div class="institute-box">

                <div class="institute-label">
                    Institute Name
                </div>

                <div class="institute-value">

                    <?php

                    echo htmlspecialchars(
                        $centerName
                    );

                    ?>

                </div>

            </div>


            <!-- =================================================
                 MARKS TITLE
            ================================================== -->

            <div class="section-title">

                <div class="section-title-line"></div>

                <h2>
                    Subject Wise Marks
                </h2>

            </div>


            <!-- =================================================
                 MARKS TABLE
            ================================================== -->

            <div class="marks-scroll">

                <table class="marks-table">


                    <thead>

                        <tr>

                            <th>
                                S.No.
                            </th>

                            <th>
                                Subject Name
                            </th>

                            <th>
                                Paper
                            </th>

                            <th>
                                Min Marks
                            </th>

                            <th>
                                Max Marks
                            </th>

                            <th>
                                Marks
                            </th>

                            <th>
                                Grade
                            </th>

                        </tr>

                    </thead>


                    <tbody>

                    <?php

                    $sno = 1;

                    ?>


                    <?php foreach ($marks as $mark): ?>


                        <?php

                        $isPractical =
                            !empty($mark['has_practical']) &&
                            (int)$mark['pr_max'] > 0;


                        $paperType =
                            $isPractical
                                ? 'Practical'
                                : 'Theory';


                        $maxMarks =
                            $mark['th_max_display'] +
                            $mark['pr_max_display'];


                        $minMarks =
                            $isPractical
                                ? round($maxMarks * 0.35)
                                : $mark['th_min_display'];


                        $obtainedMarks =
                            $mark['total_obtained_display'];

                        ?>


                        <tr>


                            <!-- S.NO -->

                            <td class="sno">

                                <?php echo $sno; ?>

                            </td>


                            <!-- SUBJECT -->

                            <td class="subject">

                                <?php

                                echo htmlspecialchars(
                                    $mark['subject_name'] ?? ''
                                );

                                ?>

                            </td>


                            <!-- PAPER -->

                            <td class="paper">

                                <?php echo $paperType; ?>

                            </td>


                            <!-- MIN -->

                            <td class="min">

                                <?php echo $minMarks; ?>

                            </td>


                            <!-- MAX -->

                            <td class="max">

                                <?php echo $maxMarks; ?>

                            </td>


                            <!-- MARKS -->

                            <td class="marks">

                                <span class="obtained-box">

                                    <?php echo $obtainedMarks; ?>

                                </span>

                            </td>


                            <!-- GRADE -->

                            <td class="grade">

                                <?php if (!empty($mark['grade'])): ?>

                                    <span class="grade-value">

                                        <?php

                                        echo htmlspecialchars(
                                            $mark['grade']
                                        );

                                        ?>

                                    </span>

                                <?php else: ?>

                                    -

                                <?php endif; ?>

                            </td>


                        </tr>


                        <?php

                        $sno++;

                        ?>


                    <?php endforeach; ?>


                    </tbody>

                </table>

            </div>


            <!-- =================================================
                 TOTALS
            ================================================== -->

            <div class="totals-grid">


                <div class="total-box">

                    <div class="total-label">
                        TOTAL MARKS
                    </div>

                    <div class="total-value">

                        <?php echo $totalMarks; ?>

                    </div>

                </div>


                <div class="total-box">

                    <div class="total-label">
                        OBTAINED MARKS
                    </div>

                    <div class="total-value">

                        <?php echo $totalObtained; ?>

                    </div>

                </div>


                <div class="total-box">

                    <div class="total-label">
                        PERCENTAGE
                    </div>

                    <div class="total-value">

                        <?php

                        echo number_format(
                            $percentage,
                            2
                        );

                        ?>%

                    </div>

                </div>


            </div>


            <!-- =================================================
                 RESULT STATUS
            ================================================== -->

            <div class="result-status">

                <?php if ($resultStatus === 'PASS'): ?>

                    <div class="status-badge status-pass">
                        ✓ <?php echo $resultStatus; ?>
                    </div>

                <?php else: ?>

                    <div class="status-badge status-fail">
                        ✕ <?php echo $resultStatus; ?>
                    </div>

                <?php endif; ?>

            </div>


            <!-- =================================================
                 FOOTER
            ================================================== -->

            <div class="result-footer">

                <strong>
                    National Board for Technical Education
                </strong>

                <span class="gold"> | </span>

                Result — Web Copy

            </div>


        </div>

    </div>


</div>


<script>

    function printMarksheet() {
        window.print();
    }

</script>


</body>
</html>