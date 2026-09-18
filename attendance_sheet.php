<?php
/**
 * RISE - Examination Attendance Sheet
 */

require_once 'includes/db.php';

$db = getDB();


/*
|--------------------------------------------------------------------------
| GET STUDENT ID
|--------------------------------------------------------------------------
*/

$studentId = (int)($_GET['id'] ?? 0);

if ($studentId <= 0) {
    die('Invalid Student ID.');
}


/*
|--------------------------------------------------------------------------
| FETCH STUDENT
|--------------------------------------------------------------------------
*/

$stmt = $db->prepare("
    SELECT *
    FROM students
    WHERE id = :id
    LIMIT 1
");

$stmt->execute([
    ':id' => $studentId
]);

$student = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$student) {
    die('Student not found.');
}


/*
|--------------------------------------------------------------------------
| FETCH COURSE NAME
|--------------------------------------------------------------------------
*/

$courseName = '-';

if (!empty($student['course_id'])) {

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
}


/*
|--------------------------------------------------------------------------
| FETCH INSTITUTE / CENTER NAME
|--------------------------------------------------------------------------
*/

$instituteName = 'Authorized Study Center';

try {

    if (!empty($student['admin_id'])) {

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
            $instituteName = $center['college_name'];
        }
    }

} catch (Exception $e) {

    // Keep default institute name

}


/*
|--------------------------------------------------------------------------
| FETCH SUBJECTS
|--------------------------------------------------------------------------
*/

$subjects = [];

if (!empty($student['course_id'])) {

    $subjectStmt = $db->prepare("
        SELECT *
        FROM subjects
        WHERE course_id = :course_id
        ORDER BY id ASC
    ");

    $subjectStmt->execute([
        ':course_id' => $student['course_id']
    ]);

    $subjects = $subjectStmt->fetchAll(PDO::FETCH_ASSOC);
}


/*
|--------------------------------------------------------------------------
| SESSION
|--------------------------------------------------------------------------
*/

$sessionName = $student['session_name'] ?? '-';

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
        Examination Attendance Sheet -
        <?php echo htmlspecialchars($student['full_name']); ?>
    </title>


    <!-- Bootstrap 5 -->

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >


    <style>

        /*
        ============================================================
        BODY
        ============================================================
        */

        body {

            margin: 0;

            background: #f5f6f8;

            font-family:
                Arial,
                Helvetica,
                sans-serif;

            color: #212529;

        }


        /*
        ============================================================
        PAGE
        ============================================================
        */

        .attendance-page {

            padding: 30px;

        }


        /*
        ============================================================
        MAIN CARD
        ============================================================
        */

        .attendance-card {

            background: #ffffff;

            border: 1px solid #dee2e6;

            border-radius: 8px;

            overflow: hidden;

        }


        /*
        ============================================================
        HEADER
        ============================================================
        */

        .attendance-header {

            padding: 20px 20px 15px;

            background: #ffffff;

            border-bottom: 1px solid #dee2e6;

        }


        /*
        ============================================================
        TITLE
        ============================================================
        */

        .attendance-main-title {

            text-align: center;

            margin: 0 0 18px;

            font-size: 24px;

            font-weight: 800;

            letter-spacing: 0.5px;

            color: #212529;

        }


        /*
        ============================================================
        SESSION / COURSE / INSTITUTE
        ============================================================
        */

        .attendance-meta-row {

            width: 100%;

            display: grid;

            grid-template-columns: 1fr 1fr 1fr;

            align-items: center;

            font-size: 14px;

            color: #212529;

        }


        .attendance-meta-left {

            text-align: left;

        }


        .attendance-meta-center {

            text-align: center;

        }


        .attendance-meta-right {

            text-align: right;

        }


        /*
        ============================================================
        TABLE WRAPPER
        ============================================================
        */

        .attendance-table-wrapper {

            width: 100%;

            overflow-x: auto;

        }


        /*
        ============================================================
        TABLE
        ============================================================
        */

        .attendance-table {

            width: 100%;

            min-width: 1100px;

            margin: 0;

            border-collapse: collapse;

        }


        .attendance-table th,
        .attendance-table td {

            border: 2px solid #777 !important;

            text-align: center;

            vertical-align: middle !important;

        }


        /*
        ============================================================
        TABLE HEADER
        ============================================================
        */

        .attendance-table th {

            background: #f1f3f5 !important;

            color: #212529;

            font-size: 13px;

            font-weight: 700;

            padding: 10px 8px;

        }


        /*
        ============================================================
        TABLE DATA
        ============================================================
        */

        .attendance-table td {

            background: #ffffff;

            height: 100px;

            padding: 8px;

            font-size: 13px;

        }


        /*
        ============================================================
        COLUMN WIDTHS
        ============================================================
        */

        .sno-column {

            width: 60px;

            min-width: 60px;

        }


        .pic-column {

            width: 90px;

            min-width: 90px;

        }


        .student-column {

            width: 250px;

            min-width: 250px;

            text-align: left !important;

        }


        /*
        ============================================================
        SUBJECT COLUMN
        ============================================================
        */

        .subject-column {

            min-width: 150px;

            width: auto;

        }


        /*
        ============================================================
        DATE OF EXAMINATION CELL
        ============================================================
        */

        .date-examination-cell {

            height: 45px !important;

            text-align: left !important;

            font-size: 12px !important;

            font-weight: 700;

            white-space: nowrap;

            background: #f8f9fa !important;

        }


        /*
        ============================================================
        DATE BOX FOR EACH SUBJECT
        ============================================================
        */

        .subject-date-box {

            height: 45px !important;

            min-width: 150px;

            background: #ffffff !important;

        }


        /*
        ============================================================
        STUDENT PHOTO
        ============================================================
        */

        .table-student-photo {

            width: 60px;

            height: 75px;

            object-fit: cover;

            display: block;

            margin: auto;

            border: 1px solid #777;

            background: #f8f9fa;

        }


        .table-photo-placeholder {

            width: 60px;

            height: 75px;

            display: flex;

            align-items: center;

            justify-content: center;

            margin: auto;

            border: 1px solid #777;

            background: #f8f9fa;

            color: #777;

            font-size: 22px;

        }


        /*
        ============================================================
        STUDENT NAME
        ============================================================
        */

        .table-student-name {

            font-size: 14px;

            font-weight: 700;

            margin-bottom: 4px;

        }


        .table-enrollment {

            font-size: 13px;

            color: #444;

        }


        /*
        ============================================================
        ACTUAL SUBJECT NAME
        ============================================================
        */

        .subject-name {

            display: block;

            font-size: 12px;

            line-height: 1.35;

            font-weight: 700;

            white-space: normal;

            word-break: normal;

        }


        /*
        ============================================================
        SUBJECT CODE
        ============================================================
        */

        .subject-code {

            display: block;

            margin-top: 4px;

            color: #6c757d;

            font-size: 10px;

            font-weight: 400;

        }


        /*
        ============================================================
        STUDENT SUBJECT BOX
        ============================================================
        */

        .subject-box {

            height: 100px;

            min-width: 150px;

            background: #ffffff !important;

        }


        /*
        ============================================================
        NO SUBJECT
        ============================================================
        */

        .no-subject {

            padding: 50px;

            text-align: center;

            color: #6c757d;

        }


        /*
        ============================================================
        PRINT
        ============================================================
        */

        @media print {

            @page {

                size: A4 landscape;

                margin: 8mm;

            }


            html,
            body {

                margin: 0 !important;

                padding: 0 !important;

                background: #ffffff !important;

            }


            .attendance-page {

                padding: 0 !important;

            }


            .attendance-card {

                border: 0 !important;

                border-radius: 0 !important;

                box-shadow: none !important;

            }


            .attendance-header {

                padding: 8px 0;

                border-bottom: 1px solid #777;

            }


            .attendance-main-title {

                font-size: 20px;

                margin-bottom: 12px;

            }


            .attendance-meta-row {

                font-size: 11px;

            }


            .attendance-table-wrapper {

                overflow: visible !important;

            }


            .attendance-table {

                width: 100% !important;

                min-width: 0 !important;

            }


            .attendance-table th {

                font-size: 9px;

                padding: 5px;

            }


            .attendance-table td {

                height: 75px;

                padding: 5px;

                font-size: 9px;

            }


            .date-examination-cell {

                height: 35px !important;

                font-size: 9px !important;

            }


            .subject-date-box {

                height: 35px !important;

                min-width: 0;

            }


            .sno-column {

                width: 40px;

                min-width: 40px;

            }


            .pic-column {

                width: 60px;

                min-width: 60px;

            }


            .student-column {

                width: 160px;

                min-width: 160px;

            }


            .subject-column {

                min-width: 0;

            }


            .subject-name {

                font-size: 8px;

            }


            .subject-code {

                font-size: 7px;

            }


            .table-student-photo {

                width: 45px;

                height: 55px;

            }


            .table-photo-placeholder {

                width: 45px;

                height: 55px;

            }


            .table-student-name {

                font-size: 10px;

            }


            .table-enrollment {

                font-size: 9px;

            }

        }


        /*
        ============================================================
        MOBILE
        ============================================================
        */

        @media (max-width: 768px) {

            .attendance-page {

                padding: 15px;

            }


            .attendance-main-title {

                font-size: 20px;

            }


            .attendance-meta-row {

                grid-template-columns: 1fr;

                gap: 6px;

            }


            .attendance-meta-left,
            .attendance-meta-center,
            .attendance-meta-right {

                text-align: left;

            }

        }

    </style>

</head>


<body>


<div class="attendance-page">


    <div class="attendance-card">


        <!-- ====================================================
             EXAMINATION ATTENDANCE HEADER
        ==================================================== -->

        <div class="attendance-header">


            <!-- TITLE -->

            <h1 class="attendance-main-title">

                EXAMINATION ATTENDANCE SHEETS

            </h1>


            <!-- ==================================================
                 SESSION | COURSE | INSTITUTE
            ================================================== -->

            <div class="attendance-meta-row">


                <!-- SESSION - LEFT -->

                <div class="attendance-meta-left">

                    <strong>Session:</strong>

                    <?php

                    echo htmlspecialchars(
                        $sessionName
                    );

                    ?>

                </div>


                <!-- COURSE - CENTER -->

                <div class="attendance-meta-center">

                    <strong>Course Name:</strong>

                    <?php

                    echo htmlspecialchars(
                        $courseName
                    );

                    ?>

                </div>


                <!-- INSTITUTE - RIGHT -->

                <div class="attendance-meta-right">

                    <strong>Institute Name:</strong>

                    <?php

                    echo htmlspecialchars(
                        $instituteName
                    );

                    ?>

                </div>


            </div>


        </div>


        <!-- ====================================================
             SUBJECT CHECK
        ==================================================== -->

        <?php if (empty($subjects)): ?>


            <div class="no-subject">

                <h5 class="mt-3">

                    No Subjects Found

                </h5>


                <p class="mb-0">

                    No subjects are assigned to this
                    student's course.

                </p>

            </div>


        <?php else: ?>


            <!-- ==================================================
                 ATTENDANCE TABLE
            ================================================== -->

            <div class="attendance-table-wrapper">


                <table
                    class="table table-bordered attendance-table mb-0"
                >


                    <!-- =================================================
                         TABLE HEADER
                    ================================================== -->

                    <thead>


                        <!-- =================================================
                             ROW 1 - ACTUAL SUBJECT NAMES
                        ================================================== -->

                        <tr>


                            <!-- S.NO -->

                            <th class="sno-column">

                                S.No.

                            </th>


                            <!-- PHOTO -->

                            <th class="pic-column">

                                Pic

                            </th>


                            <!-- STUDENT -->

                            <th class="student-column">

                                Name

                                <br>

                                Enroll. No.

                            </th>


                            <!-- ACTUAL SUBJECTS -->

                            <?php foreach (
                                $subjects
                                as $subject
                            ): ?>


                                <th class="subject-column">


                                    <span class="subject-name">

                                        <?php

                                        echo htmlspecialchars(
                                            $subject['subject_name']
                                            ?? $subject['name']
                                            ?? 'Subject'
                                        );

                                        ?>

                                    </span>


                                    <?php if (
                                        !empty(
                                            $subject['code']
                                        )
                                    ): ?>


                                        <span class="subject-code">

                                            <?php

                                            echo htmlspecialchars(
                                                $subject['code']
                                            );

                                            ?>

                                        </span>


                                    <?php endif; ?>


                                </th>


                            <?php endforeach; ?>


                        </tr>


                        <!-- =================================================
                             ROW 2 - DATE OF EXAMINATION
                        ================================================== -->

                        <tr>


                            <!-- EMPTY S.NO -->

                            <td class="sno-column">

                            </td>


                            <!-- EMPTY PHOTO -->

                            <td class="pic-column">

                            </td>


                            <!-- DATE LABEL -->

                            <td class="student-column date-examination-cell">

                                Date of Examination

                            </td>


                            <!-- DATE BOX FOR EACH SUBJECT -->

                            <?php foreach (
                                $subjects
                                as $subject
                            ): ?>


                                <td class="subject-date-box">

                                </td>


                            <?php endforeach; ?>


                        </tr>


                    </thead>


                    <!-- =================================================
                         ROW 3 - STUDENT
                    ================================================== -->

                    <tbody>


                        <tr>


                            <!-- S.NO -->

                            <td class="sno-column">

                                1

                            </td>


                            <!-- PHOTO -->

                            <td class="pic-column">


                                <?php if (
                                    !empty(
                                        $student['photo']
                                    )
                                ): ?>


                                    <img
                                        src="uploads/photos/<?php
                                        echo htmlspecialchars(
                                            $student['photo']
                                        );
                                        ?>"
                                        class="table-student-photo"
                                        alt="Student Photo"
                                        onerror="
                                            this.style.display='none';
                                            this.nextElementSibling.style.display='flex';
                                        "
                                    >


                                    <div
                                        class="table-photo-placeholder"
                                        style="display:none;"
                                    >

                                        👤

                                    </div>


                                <?php else: ?>


                                    <div
                                        class="table-photo-placeholder"
                                    >

                                        👤

                                    </div>


                                <?php endif; ?>


                            </td>


                            <!-- =================================================
                                 STUDENT NAME + ENROLLMENT
                            ================================================== -->

                            <td class="student-column">


                                <div class="table-student-name">

                                    <?php

                                    echo htmlspecialchars(
                                        $student['full_name']
                                    );

                                    ?>

                                </div>


                                <div class="table-enrollment">

                                    <?php

                                    echo htmlspecialchars(
                                        $student['enrollment_no']
                                        ?? '-'
                                    );

                                    ?>

                                </div>


                            </td>


                            <!-- =================================================
                                 EMPTY BOX FOR EACH SUBJECT
                            ================================================== -->

                            <?php foreach (
                                $subjects
                                as $subject
                            ): ?>


                                <td class="subject-box">

                                </td>


                            <?php endforeach; ?>


                        </tr>


                    </tbody>


                </table>


            </div>


        <?php endif; ?>


    </div>


</div>


</body>

</html>