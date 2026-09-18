<?php
require_once 'includes/db.php';

$db = getDB();

$search_query = '';
$search_type = '';
$student = null;
$error = '';
$searched = false;

if ($_SERVER['REQUEST_METHOD'] === 'GET' && !empty($_GET['q'])) {

    $searched = true;

    $search_query = trim($_GET['q']);

    $search_type = isset($_GET['type'])
        ? trim($_GET['type'])
        : 'enrollment';

    if (empty($search_query)) {

        $error = 'Please enter a search query.';

    } elseif (strlen($search_query) < 3) {

        $error = 'Search query must be at least 3 characters.';

    } else {

        $search_query = htmlspecialchars(
            $search_query,
            ENT_QUOTES,
            'UTF-8'
        );


        /* =====================================================
           SEARCH BY CERTIFICATE ID
           ===================================================== */

        if ($search_type === 'certificate') {

            $stmt = $db->prepare("
                SELECT
                    s.full_name,
                    s.enrollment_no,
                    s.roll_no,
                    s.photo,
                    s.status,
                    s.father_name,
                    s.gender,
                    s.dob,
                    s.session_name,
                    s.batch,
                    p.program_name,
                    p.duration,
                    c.course_name,
                    cert.certificate_id,
                    cert.issue_date

                FROM certificates cert

                JOIN students s
                    ON cert.student_id = s.id

                JOIN programs p
                    ON s.program_id = p.id

                JOIN courses c
                    ON s.course_id = c.id

                WHERE cert.certificate_id = :query
            ");

            $stmt->execute([
                ':query' => $search_query
            ]);

            $student = $stmt->fetch(PDO::FETCH_ASSOC);


            /* =================================================
               GET MARKS
               ================================================= */

            if ($student) {

                $marks_stmt = $db->prepare("
                    SELECT
                        SUM(m.marks_obtained) AS total_obtained,
                        SUM(sub.total_marks) AS total_max

                    FROM marks m

                    JOIN subjects sub
                        ON m.subject_id = sub.id

                    JOIN students s
                        ON m.student_id = s.id

                    JOIN certificates cert
                        ON cert.student_id = s.id

                    WHERE cert.certificate_id = :cert_id
                ");

                $marks_stmt->execute([
                    ':cert_id' => $search_query
                ]);

                $marks_data = $marks_stmt->fetch(PDO::FETCH_ASSOC);


                if (
                    $marks_data &&
                    $marks_data['total_max'] > 0
                ) {

                    $student['percentage'] = round(
                        (
                            $marks_data['total_obtained'] /
                            $marks_data['total_max']
                        ) * 100,
                        2
                    );


                    if ($student['percentage'] >= 75) {

                        $student['grade'] = 'A';

                    } elseif ($student['percentage'] >= 60) {

                        $student['grade'] = 'B';

                    } elseif ($student['percentage'] >= 50) {

                        $student['grade'] = 'C';

                    } else {

                        $student['grade'] = 'Fail';

                    }
                }
            }


        } else {


            /* =================================================
               SEARCH BY ENROLLMENT NUMBER
               ================================================= */

            $stmt = $db->prepare("
                SELECT
                    s.full_name,
                    s.enrollment_no,
                    s.roll_no,
                    s.photo,
                    s.status,
                    s.father_name,
                    s.gender,
                    s.dob,
                    s.session_name,
                    s.batch,
                    p.program_name,
                    p.duration,
                    c.course_name,
                    cert.certificate_id,
                    cert.issue_date

                FROM students s

                JOIN programs p
                    ON s.program_id = p.id

                JOIN courses c
                    ON s.course_id = c.id

                LEFT JOIN certificates cert
                    ON cert.student_id = s.id

                WHERE s.enrollment_no = :query
            ");

            $stmt->execute([
                ':query' => $search_query
            ]);

            $student = $stmt->fetch(PDO::FETCH_ASSOC);


            /* =================================================
               GET MARKS
               ================================================= */

            if ($student) {

                $marks_stmt = $db->prepare("
                    SELECT
                        SUM(m.marks_obtained) AS total_obtained,
                        SUM(sub.total_marks) AS total_max

                    FROM marks m

                    JOIN subjects sub
                        ON m.subject_id = sub.id

                    JOIN students s
                        ON m.student_id = s.id

                    WHERE s.enrollment_no = :enroll
                ");

                $marks_stmt->execute([
                    ':enroll' => $search_query
                ]);

                $marks_data = $marks_stmt->fetch(PDO::FETCH_ASSOC);


                if (
                    $marks_data &&
                    $marks_data['total_max'] > 0
                ) {

                    $student['percentage'] = round(
                        (
                            $marks_data['total_obtained'] /
                            $marks_data['total_max']
                        ) * 100,
                        2
                    );


                    if ($student['percentage'] >= 75) {

                        $student['grade'] = 'A';

                    } elseif ($student['percentage'] >= 60) {

                        $student['grade'] = 'B';

                    } elseif ($student['percentage'] >= 50) {

                        $student['grade'] = 'C';

                    } else {

                        $student['grade'] = 'Fail';

                    }
                }
            }
        }


        if (!$student) {

            $error =
                'No student found with the provided details.';

        }
    }
}
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
        Student Verification | National Board for Technical Education
    </title>


    <!-- Bootstrap -->

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >


    <!-- Bootstrap Icons -->

    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
    >


    <style>

        /* =====================================================
           NBTE COLORS
           ===================================================== */

        :root {

            --nbte-navy: #062A5A;

            --nbte-navy-dark: #041D3F;

            --nbte-gold: #D49729;

            --nbte-gold-light: #F4E6C6;

            --nbte-white: #FFFFFF;

            --nbte-bg: #F5F7FA;

            --nbte-text: #1F2937;

            --nbte-muted: #6B7280;

            --nbte-border: #E5E7EB;

            --nbte-success: #16803C;

            --nbte-danger: #C62828;

            --nbte-warning: #B7791F;

            --nbte-shadow:
                0 15px 45px rgba(6, 42, 90, 0.10);

        }


        /* =====================================================
           GLOBAL
           ===================================================== */

        * {
            box-sizing: border-box;
        }


        body {

            margin: 0;

            min-height: 100vh;

            background:
                linear-gradient(
                    180deg,
                    #F7F9FC 0%,
                    #FFFFFF 100%
                );

            color: var(--nbte-text);

            font-family:
                "Segoe UI",
                Arial,
                sans-serif;

        }


        /* =====================================================
           TOP NAVIGATION
           ===================================================== */

        .nbte-navbar {

            background: var(--nbte-white);

            border-bottom:
                1px solid rgba(6, 42, 90, 0.08);

            box-shadow:
                0 4px 20px rgba(6, 42, 90, 0.06);

            position: relative;

            z-index: 20;

        }


        .navbar-inner {

            min-height: 78px;

            display: flex;

            align-items: center;

            justify-content: space-between;

        }


        .nbte-brand {

            display: flex;

            align-items: center;

            gap: 14px;

            text-decoration: none;

            color: var(--nbte-navy);

        }


        .nbte-logo {

            width: 52px;

            height: 52px;

            object-fit: contain;

        }


        .brand-text {

            line-height: 1.15;

        }


        .brand-title {

            display: block;

            font-size: 18px;

            font-weight: 800;

            color: var(--nbte-navy);

        }


        .brand-subtitle {

            display: block;

            margin-top: 4px;

            font-size: 11px;

            font-weight: 600;

            color: var(--nbte-muted);

            letter-spacing: .3px;

        }


        .home-btn {

            display: inline-flex;

            align-items: center;

            gap: 7px;

            color: var(--nbte-navy);

            border:
                1px solid var(--nbte-border);

            background: #fff;

            padding: 9px 17px;

            border-radius: 8px;

            text-decoration: none;

            font-weight: 600;

            font-size: 14px;

            transition: .25s ease;

        }


        .home-btn:hover {

            background: var(--nbte-navy);

            color: #fff;

            border-color: var(--nbte-navy);

        }


        /* =====================================================
           HERO
           ===================================================== */

        .verification-hero {

            position: relative;

            overflow: hidden;

            padding:
                72px 0
                120px;

            color: #fff;

            background:
                linear-gradient(
                    135deg,
                    var(--nbte-navy-dark) 0%,
                    var(--nbte-navy) 55%,
                    #0B3C78 100%
                );

        }


        .verification-hero::before {

            content: "";

            position: absolute;

            width: 480px;

            height: 480px;

            border-radius: 50%;

            background:
                rgba(212,151,41,.12);

            right: -170px;

            top: -250px;

        }


        .verification-hero::after {

            content: "";

            position: absolute;

            width: 350px;

            height: 350px;

            border-radius: 50%;

            border:
                70px solid rgba(255,255,255,.035);

            left: -190px;

            bottom: -230px;

        }


        .hero-content {

            position: relative;

            z-index: 2;

        }


        .hero-badge {

            display: inline-flex;

            align-items: center;

            gap: 8px;

            padding: 8px 15px;

            border-radius: 50px;

            background:
                rgba(212,151,41,.15);

            border:
                1px solid rgba(212,151,41,.45);

            color: #F4D58D;

            font-size: 12px;

            font-weight: 700;

            letter-spacing: 1px;

            text-transform: uppercase;

            margin-bottom: 18px;

        }


        .hero-title {

            font-size:
                clamp(32px, 5vw, 48px);

            line-height: 1.15;

            font-weight: 800;

            margin-bottom: 15px;

        }


        .hero-title span {

            color: #F0C66A;

        }


        .hero-description {

            max-width: 680px;

            margin: 0 auto;

            color: rgba(255,255,255,.78);

            font-size: 16px;

            line-height: 1.7;

        }


        /* =====================================================
           SEARCH WRAPPER
           ===================================================== */

        .verification-container {

            position: relative;

            z-index: 5;

            margin-top: -65px;

            padding-bottom: 70px;

        }


        /* =====================================================
           SEARCH CARD
           ===================================================== */

        .search-card {

            max-width: 850px;

            margin: 0 auto;

            background: #fff;

            border-radius: 18px;

            box-shadow: var(--nbte-shadow);

            border:
                1px solid rgba(6,42,90,.07);

            padding: 34px;

        }


        .search-card-title {

            text-align: center;

            font-size: 21px;

            font-weight: 750;

            color: var(--nbte-navy);

            margin-bottom: 7px;

        }


        .search-card-subtitle {

            text-align: center;

            color: var(--nbte-muted);

            font-size: 14px;

            margin-bottom: 28px;

        }


        /* =====================================================
           SEARCH TYPE
           ===================================================== */

        .search-types {

            display: flex;

            justify-content: center;

            gap: 12px;

            margin-bottom: 22px;

        }


        .search-type-btn {

            cursor: pointer;

            display: inline-flex;

            align-items: center;

            gap: 8px;

            padding: 11px 18px;

            border:
                1px solid var(--nbte-border);

            border-radius: 9px;

            color: var(--nbte-muted);

            background: #fff;

            font-size: 14px;

            font-weight: 650;

            transition: .25s ease;

        }


        .search-type-btn:hover {

            border-color: var(--nbte-gold);

            color: var(--nbte-navy);

        }


        .search-type-btn.active {

            background:
                rgba(212,151,41,.10);

            color: var(--nbte-navy);

            border-color:
                var(--nbte-gold);

            box-shadow:
                0 3px 12px rgba(212,151,41,.12);

        }


        /* =====================================================
           SEARCH INPUT
           ===================================================== */

        .search-input-wrapper {

            display: flex;

            align-items: stretch;

            border:
                2px solid var(--nbte-border);

            border-radius: 11px;

            overflow: hidden;

            transition: .25s ease;

        }


        .search-input-wrapper:focus-within {

            border-color:
                var(--nbte-gold);

            box-shadow:
                0 0 0 4px rgba(212,151,41,.10);

        }


        .search-icon {

            width: 54px;

            display: flex;

            align-items: center;

            justify-content: center;

            color: var(--nbte-navy);

            background: #FAFAFA;

            font-size: 19px;

        }


        .search-input {

            flex: 1;

            min-width: 0;

            border: none;

            outline: none;

            padding: 14px 8px;

            font-size: 15px;

            color: var(--nbte-text);

        }


        .search-input::placeholder {

            color: #9CA3AF;

        }


        .verify-btn {

            border: none;

            background: var(--nbte-navy);

            color: #fff;

            padding: 0 28px;

            font-weight: 700;

            font-size: 14px;

            transition: .25s ease;

        }


        .verify-btn:hover {

            background: var(--nbte-gold);

            color: #fff;

        }


        .search-help {

            margin-top: 13px;

            text-align: center;

            color: var(--nbte-muted);

            font-size: 12px;

        }


        /* =====================================================
           RESULT WRAPPER
           ===================================================== */

        .result-wrapper {

            max-width: 1050px;

            margin:
                35px auto 0;

        }


        /* =====================================================
           RESULT CARD
           ===================================================== */

        .result-card {

            position: relative;

            overflow: hidden;

            background: #fff;

            border-radius: 18px;

            border:
                1px solid var(--nbte-border);

            box-shadow:
                0 12px 40px rgba(6,42,90,.08);

        }


        .result-top {

            padding:
                20px 28px;

            background:
                var(--nbte-navy);

            color: #fff;

            display: flex;

            align-items: center;

            justify-content: space-between;

            gap: 15px;

        }


        .result-title {

            margin: 0;

            font-size: 17px;

            font-weight: 700;

            display: flex;

            align-items: center;

            gap: 9px;

        }


        .valid-badge {

            display: inline-flex;

            align-items: center;

            gap: 7px;

            background:
                #E8F7EE;

            color:
                var(--nbte-success);

            border-radius: 50px;

            padding: 8px 14px;

            font-size: 12px;

            font-weight: 800;

            white-space: nowrap;

        }


        .pending-badge {

            display: inline-flex;

            align-items: center;

            gap: 7px;

            background:
                #FFF7E5;

            color:
                var(--nbte-warning);

            border-radius: 50px;

            padding: 8px 14px;

            font-size: 12px;

            font-weight: 800;

            white-space: nowrap;

        }


        /* =====================================================
           RESULT BODY
           ===================================================== */

        .result-body {

            padding: 32px;

            position: relative;

        }


        .verified-ribbon {

            position: absolute;

            top: 20px;

            right: -48px;

            transform: rotate(45deg);

            background:
                rgba(22,128,60,.07);

            color:
                rgba(22,128,60,.20);

            padding: 7px 55px;

            font-size: 12px;

            font-weight: 900;

            letter-spacing: 2px;

            pointer-events: none;

        }


        /* =====================================================
           STUDENT PHOTO
           ===================================================== */

        .student-photo-box {

            text-align: center;

        }


        .student-photo {

            width: 145px;

            height: 170px;

            object-fit: cover;

            border-radius: 10px;

            border:
                4px solid var(--nbte-gold);

            box-shadow:
                0 8px 22px rgba(6,42,90,.13);

            background: #F4F5F7;

        }


        .photo-label {

            margin-top: 10px;

            color: var(--nbte-muted);

            font-size: 11px;

            font-weight: 600;

            text-transform: uppercase;

            letter-spacing: .8px;

        }


        /* =====================================================
           GRADE
           ===================================================== */

        .grade-wrapper {

            margin-top: 15px;

        }


        .grade-badge {

            width: 48px;

            height: 48px;

            display: inline-flex;

            align-items: center;

            justify-content: center;

            border-radius: 50%;

            color: #fff;

            font-size: 18px;

            font-weight: 800;

        }


        .grade-A {

            background:
                var(--nbte-success);

        }


        .grade-B {

            background:
                #2563EB;

        }


        .grade-C {

            background:
                #D97706;

        }


        .grade-Fail {

            background:
                var(--nbte-danger);

        }


        /* =====================================================
           STUDENT DETAILS
           ===================================================== */

        .student-heading {

            color: var(--nbte-navy);

            font-size: 24px;

            font-weight: 800;

            margin-bottom: 5px;

        }


        .student-status-text {

            color: var(--nbte-muted);

            font-size: 13px;

            margin-bottom: 18px;

        }


        .details-grid {

            display: grid;

            grid-template-columns:
                repeat(2, 1fr);

            border-top:
                1px solid var(--nbte-border);

        }


        .detail-item {

            padding:
                13px 15px;

            border-bottom:
                1px solid var(--nbte-border);

        }


        .detail-item:nth-child(odd) {

            border-right:
                1px solid var(--nbte-border);

        }


        .detail-label {

            display: block;

            color: var(--nbte-muted);

            font-size: 11px;

            font-weight: 700;

            text-transform: uppercase;

            letter-spacing: .6px;

            margin-bottom: 5px;

        }


        .detail-value {

            color: var(--nbte-text);

            font-size: 14px;

            font-weight: 600;

            word-break: break-word;

        }


        .detail-value code {

            color: var(--nbte-navy);

            background:
                #F3F5F8;

            padding: 4px 7px;

            border-radius: 5px;

            font-size: 13px;

        }


        /* =====================================================
           STATUS
           ===================================================== */

        .approved-status {

            display: inline-flex;

            align-items: center;

            gap: 6px;

            padding: 6px 11px;

            border-radius: 50px;

            background: #E8F7EE;

            color: var(--nbte-success);

            font-size: 12px;

            font-weight: 700;

        }


        .pending-status {

            display: inline-flex;

            align-items: center;

            gap: 6px;

            padding: 6px 11px;

            border-radius: 50px;

            background: #FFF7E5;

            color: var(--nbte-warning);

            font-size: 12px;

            font-weight: 700;

        }


        /* =====================================================
           RESULT FOOTER
           ===================================================== */

        .result-footer {

            background:
                #F8FAFC;

            border-top:
                1px solid var(--nbte-border);

            padding:
                15px 25px;

            display: flex;

            align-items: center;

            justify-content: space-between;

            gap: 10px;

            flex-wrap: wrap;

        }


        .result-footer small {

            color: var(--nbte-muted);

            font-size: 11px;

        }


        .official-label {

            display: inline-flex;

            align-items: center;

            gap: 6px;

            color: var(--nbte-navy);

            font-weight: 700;

        }


        /* =====================================================
           ERROR CARD
           ===================================================== */

        .error-card {

            background: #fff;

            border:
                1px solid #F2D1D1;

            border-radius: 18px;

            overflow: hidden;

            box-shadow:
                0 10px 35px rgba(198,40,40,.07);

        }


        .error-header {

            background:
                var(--nbte-danger);

            color: #fff;

            padding: 18px 25px;

            display: flex;

            align-items: center;

            justify-content: space-between;

        }


        .error-header h5 {

            margin: 0;

            font-size: 16px;

            font-weight: 700;

        }


        .not-found-badge {

            background: rgba(255,255,255,.15);

            padding: 7px 12px;

            border-radius: 50px;

            font-size: 11px;

            font-weight: 800;

        }


        .error-body {

            text-align: center;

            padding: 50px 25px;

        }


        .error-icon {

            width: 75px;

            height: 75px;

            display: inline-flex;

            align-items: center;

            justify-content: center;

            border-radius: 50%;

            background: #FFF1F1;

            color: var(--nbte-danger);

            font-size: 35px;

        }


        .error-body h4 {

            color: var(--nbte-danger);

            margin-top: 18px;

            font-weight: 750;

        }


        .error-body p {

            color: var(--nbte-muted);

            font-size: 14px;

        }


        /* =====================================================
           FOOTER
           ===================================================== */

        .nbte-footer {

            background:
                var(--nbte-navy);

            color:
                rgba(255,255,255,.75);

            padding:
                28px 0;

        }


        .footer-inner {

            display: flex;

            align-items: center;

            justify-content: space-between;

            gap: 15px;

            flex-wrap: wrap;

        }


        .footer-brand {

            color: #fff;

            font-weight: 750;

        }


        .footer-text {

            margin: 0;

            font-size: 12px;

        }


        /* =====================================================
           RESPONSIVE
           ===================================================== */

        @media (max-width: 991px) {

            .verification-hero {

                padding:
                    60px 0
                    105px;

            }


            .result-body {

                padding: 25px;

            }

        }


        @media (max-width: 767px) {

            .navbar-inner {

                min-height: 68px;

            }


            .nbte-logo {

                width: 44px;

                height: 44px;

            }


            .brand-title {

                font-size: 15px;

            }


            .brand-subtitle {

                font-size: 9px;

            }


            .home-btn {

                padding: 8px 11px;

                font-size: 12px;

            }


            .home-btn span {

                display: none;

            }


            .verification-hero {

                padding:
                    48px 15px
                    90px;

            }


            .hero-title {

                font-size: 31px;

            }


            .hero-description {

                font-size: 14px;

            }


            .verification-container {

                margin-top: -50px;

                padding:
                    0 12px
                    50px;

            }


            .search-card {

                padding: 25px 18px;

            }


            .search-types {

                flex-direction: column;

            }


            .search-type-btn {

                justify-content: center;

            }


            .search-input-wrapper {

                flex-wrap: wrap;

            }


            .search-icon {

                width: 48px;

            }


            .search-input {

                width:
                    calc(100% - 48px);

            }


            .verify-btn {

                width: 100%;

                padding: 13px;

            }


            .result-top {

                align-items: flex-start;

                flex-direction: column;

                padding: 18px 20px;

            }


            .result-body {

                padding: 22px 17px;

            }


            .student-photo-box {

                margin-bottom: 25px;

            }


            .student-heading {

                font-size: 21px;

            }


            .details-grid {

                grid-template-columns: 1fr;

            }


            .detail-item:nth-child(odd) {

                border-right: none;

            }


            .result-footer {

                padding: 14px 17px;

                flex-direction: column;

                align-items: flex-start;

            }


            .footer-inner {

                flex-direction: column;

                text-align: center;

                justify-content: center;

            }

        }


    </style>

</head>


<body>


<!-- =====================================================
     NAVBAR
     ===================================================== -->

<nav class="nbte-navbar">

    <div class="container">

        <div class="navbar-inner">


            <a
                href="index.php"
                class="nbte-brand"
            >

                <img
                    src="assets/images/logo.jpg"
                    alt="NBTE Logo"
                    class="nbte-logo"
                >

                <div class="brand-text">

                    <span class="brand-title">
                        National Board for Technical Education
                    </span>

                    <span class="brand-subtitle">
                        Student Credential Verification Portal
                    </span>

                </div>

            </a>


            <a
                href="index.php"
                class="home-btn"
            >

                <i class="bi bi-house-door"></i>

                <span>
                    Home
                </span>

            </a>


        </div>

    </div>

</nav>



<!-- =====================================================
     HERO
     ===================================================== -->

<section class="verification-hero">

    <div class="container text-center">

        <div class="hero-content">

            <div class="hero-badge">

                <i class="bi bi-shield-check"></i>

                Official Verification Portal

            </div>


            <h1 class="hero-title">

                Student
                <span>Verification</span>

            </h1>


            <p class="hero-description">

                Verify the authenticity of student credentials
                issued through the National Board for Technical
                Education.

            </p>

        </div>

    </div>

</section>



<!-- =====================================================
     MAIN
     ===================================================== -->

<main class="verification-container">

    <div class="container">


        <!-- =================================================
             SEARCH CARD
             ================================================= -->

        <div class="search-card">


            <h2 class="search-card-title">

                Verify Student Credentials

            </h2>


            <p class="search-card-subtitle">

                Enter the Enrollment Number or Certificate ID
                to verify the student's credentials.

            </p>


            <form
                method="GET"
                action=""
                id="verifyForm"
            >


                <!-- SEARCH TYPES -->

                <div class="search-types">


                    <label
                        class="search-type-btn
                        <?= ($search_type !== 'certificate') ? 'active' : '' ?>"
                        id="btnEnrollment"
                    >

                        <input
                            type="radio"
                            name="type"
                            value="enrollment"
                            class="d-none"
                            <?= ($search_type !== 'certificate') ? 'checked' : '' ?>
                        >

                        <i class="bi bi-person-vcard"></i>

                        Enrollment Number

                    </label>



                    <label
                        class="search-type-btn
                        <?= ($search_type === 'certificate') ? 'active' : '' ?>"
                        id="btnCertificate"
                    >

                        <input
                            type="radio"
                            name="type"
                            value="certificate"
                            class="d-none"
                            <?= ($search_type === 'certificate') ? 'checked' : '' ?>
                        >

                        <i class="bi bi-patch-check"></i>

                        Certificate ID

                    </label>


                </div>


                <!-- SEARCH INPUT -->

                <div class="search-input-wrapper">


                    <div class="search-icon">

                        <i class="bi bi-search"></i>

                    </div>


                    <input
                        type="text"
                        name="q"
                        class="search-input"
                        placeholder="Enter Enrollment Number or Certificate ID..."
                        value="<?= htmlspecialchars($search_query) ?>"
                        required
                        minlength="3"
                        maxlength="50"
                    >


                    <button
                        type="submit"
                        class="verify-btn"
                    >

                        <i class="bi bi-shield-check me-1"></i>

                        Verify Now

                    </button>


                </div>


                <div class="search-help">

                    <i class="bi bi-info-circle me-1"></i>

                    Enter the number exactly as printed on
                    the official student document.

                </div>


            </form>

        </div>



        <!-- =================================================
             RESULTS
             ================================================= -->

        <?php if ($searched): ?>

            <div class="result-wrapper">


                <?php if ($error): ?>


                    <!-- =====================================
                         NOT FOUND
                         ===================================== -->

                    <div class="error-card">


                        <div class="error-header">

                            <h5>

                                <i class="bi bi-search me-2"></i>

                                Verification Result

                            </h5>


                            <span class="not-found-badge">

                                <i class="bi bi-x-circle me-1"></i>

                                NOT FOUND

                            </span>

                        </div>


                        <div class="error-body">


                            <div class="error-icon">

                                <i class="bi bi-file-earmark-x"></i>

                            </div>


                            <h4>

                                Verification Failed

                            </h4>


                            <p>

                                <?= htmlspecialchars($error) ?>

                            </p>


                            <p class="small">

                                Please check the Enrollment Number
                                or Certificate ID and try again.

                            </p>


                        </div>


                    </div>


                <?php elseif ($student): ?>


                    <!-- =====================================
                         VERIFIED RESULT
                         ===================================== -->

                    <div class="result-card">


                        <?php if ($student['status'] === 'Approved'): ?>

                            <div class="verified-ribbon">

                                VERIFIED

                            </div>

                        <?php endif; ?>


                        <!-- RESULT HEADER -->

                        <div class="result-top">


                            <h5 class="result-title">

                                <i class="bi bi-person-check-fill"></i>

                                Student Verification Result

                            </h5>


                            <?php if ($student['status'] === 'Approved'): ?>

                                <span class="valid-badge">

                                    <i class="bi bi-check-circle-fill"></i>

                                    VALID & VERIFIED

                                </span>

                            <?php else: ?>

                                <span class="pending-badge">

                                    <i class="bi bi-clock-fill"></i>

                                    PENDING APPROVAL

                                </span>

                            <?php endif; ?>


                        </div>


                        <!-- RESULT BODY -->

                        <div class="result-body">


                            <div class="row g-4">


                                <!-- =================================
                                     PHOTO
                                     ================================= -->

                                <div class="col-md-3">

                                    <div class="student-photo-box">


                                        <?php

                                        $photo_exists =
                                            !empty($student['photo']) &&
                                            file_exists(
                                                'uploads/photos/' .
                                                $student['photo']
                                            );

                                        ?>


                                        <?php if ($photo_exists): ?>

                                            <img
                                                src="uploads/photos/<?= htmlspecialchars($student['photo']) ?>"
                                                alt="Student Photo"
                                                class="student-photo"
                                            >

                                        <?php else: ?>

                                            <div
                                                class="student-photo d-inline-flex align-items-center justify-content-center"
                                            >

                                                <i
                                                    class="bi bi-person-fill text-secondary"
                                                    style="font-size:60px;"
                                                ></i>

                                            </div>

                                        <?php endif; ?>


                                        <div class="photo-label">

                                            Student Photograph

                                        </div>


                                        <?php if (isset($student['grade'])): ?>

                                            <div class="grade-wrapper">

                                                <span
                                                    class="grade-badge grade-<?= htmlspecialchars($student['grade']) ?>"
                                                >

                                                    <?= htmlspecialchars($student['grade']) ?>

                                                </span>

                                            </div>

                                        <?php endif; ?>


                                    </div>

                                </div>



                                <!-- =================================
                                     DETAILS
                                     ================================= -->

                                <div class="col-md-9">


                                    <h2 class="student-heading">

                                        <?= htmlspecialchars(
                                            strtoupper(
                                                $student['full_name']
                                            )
                                        ) ?>

                                    </h2>


                                    <p class="student-status-text">

                                        <i class="bi bi-shield-check me-1"></i>

                                        Official student credential record

                                    </p>


                                    <div class="details-grid">


                                        <!-- Student Name -->

                                        <div class="detail-item">

                                            <span class="detail-label">

                                                Student Name

                                            </span>

                                            <span class="detail-value">

                                                <?= htmlspecialchars(
                                                    strtoupper(
                                                        $student['full_name']
                                                    )
                                                ) ?>

                                            </span>

                                        </div>


                                        <!-- Father's Name -->

                                        <div class="detail-item">

                                            <span class="detail-label">

                                                Father's Name

                                            </span>

                                            <span class="detail-value">

                                                <?= htmlspecialchars(
                                                    $student['father_name']
                                                ) ?>

                                            </span>

                                        </div>


                                        <!-- Enrollment -->

                                        <div class="detail-item">

                                            <span class="detail-label">

                                                Enrollment Number

                                            </span>

                                            <span class="detail-value">

                                                <code>

                                                    <?= htmlspecialchars(
                                                        $student['enrollment_no']
                                                    ) ?>

                                                </code>

                                            </span>

                                        </div>


                                        <!-- Roll -->

                                        <div class="detail-item">

                                            <span class="detail-label">

                                                Roll Number

                                            </span>

                                            <span class="detail-value">

                                                <?= htmlspecialchars(
                                                    $student['roll_no']
                                                ) ?>

                                            </span>

                                        </div>


                                        <!-- Program -->

                                        <div class="detail-item">

                                            <span class="detail-label">

                                                Program

                                            </span>

                                            <span class="detail-value">

                                                <?= htmlspecialchars(
                                                    $student['program_name']
                                                ) ?>

                                            </span>

                                        </div>


                                        <!-- Course -->

                                        <div class="detail-item">

                                            <span class="detail-label">

                                                Course

                                            </span>

                                            <span class="detail-value">

                                                <?= htmlspecialchars(
                                                    $student['course_name']
                                                ) ?>

                                            </span>

                                        </div>


                                        <!-- Session -->

                                        <div class="detail-item">

                                            <span class="detail-label">

                                                Session

                                            </span>

                                            <span class="detail-value">

                                                <?= htmlspecialchars(
                                                    $student['session_name']
                                                ) ?>

                                            </span>

                                        </div>


                                        <!-- Grade -->

                                        <?php if (isset($student['percentage'])): ?>

                                            <div class="detail-item">

                                                <span class="detail-label">

                                                    Academic Grade

                                                </span>

                                                <span class="detail-value">

                                                    <?= htmlspecialchars(
                                                        $student['grade']
                                                    ) ?>

                                                    <span class="ms-2 text-muted">

                                                        (<?= htmlspecialchars(
                                                            $student['percentage']
                                                        ) ?>%)

                                                    </span>

                                                </span>

                                            </div>

                                        <?php endif; ?>


                                        <!-- Certificate -->

                                        <?php if (!empty($student['certificate_id'])): ?>

                                            <div class="detail-item">

                                                <span class="detail-label">

                                                    Certificate ID

                                                </span>

                                                <span class="detail-value">

                                                    <code>

                                                        <?= htmlspecialchars(
                                                            $student['certificate_id']
                                                        ) ?>

                                                    </code>

                                                </span>

                                            </div>


                                            <div class="detail-item">

                                                <span class="detail-label">

                                                    Certificate Issue Date

                                                </span>

                                                <span class="detail-value">

                                                    <?= date(
                                                        'd F Y',
                                                        strtotime(
                                                            $student['issue_date']
                                                        )
                                                    ) ?>

                                                </span>

                                            </div>

                                        <?php endif; ?>


                                        <!-- Status -->

                                        <div class="detail-item">

                                            <span class="detail-label">

                                                Verification Status

                                            </span>

                                            <span class="detail-value">


                                                <?php if ($student['status'] === 'Approved'): ?>

                                                    <span class="approved-status">

                                                        <i class="bi bi-check-circle-fill"></i>

                                                        Approved & Valid

                                                    </span>

                                                <?php else: ?>

                                                    <span class="pending-status">

                                                        <i class="bi bi-clock-fill"></i>

                                                        Pending Approval

                                                    </span>

                                                <?php endif; ?>


                                            </span>

                                        </div>


                                    </div>

                                </div>


                            </div>

                        </div>


                        <!-- RESULT FOOTER -->

                        <div class="result-footer">


                            <small>

                                <i class="bi bi-clock me-1"></i>

                                Verified on:
                                <?= date('d F Y, h:i A') ?>

                            </small>


                            <small class="official-label">

                                <i class="bi bi-shield-fill-check"></i>

                                National Board for Technical Education

                            </small>


                        </div>


                    </div>


                <?php endif; ?>


            </div>

        <?php endif; ?>


    </div>

</main>



<!-- =====================================================
     FOOTER
     ===================================================== -->

<footer class="nbte-footer">

    <div class="container">

        <div class="footer-inner">


            <p class="footer-text">

                © <?= date('Y') ?>

                <span class="footer-brand">

                    National Board for Technical Education

                </span>

                . All Rights Reserved.

            </p>


            <p class="footer-text">

                <i class="bi bi-shield-check me-1"></i>

                Official Credential Verification Portal

            </p>


        </div>

    </div>

</footer>



<!-- Bootstrap -->

<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
></script>



<script>

/* =====================================================
   SEARCH TYPE TOGGLE
   ===================================================== */

document
    .querySelectorAll('.search-type-btn')
    .forEach(function(btn) {

        btn.addEventListener('click', function() {


            document
                .querySelectorAll('.search-type-btn')
                .forEach(function(button) {

                    button.classList.remove('active');

                });


            this.classList.add('active');


            const radio =
                this.querySelector(
                    'input[type="radio"]'
                );


            radio.checked = true;


            const input =
                document.querySelector(
                    'input[name="q"]'
                );


            if (radio.value === 'certificate') {

                input.placeholder =
                    'Enter Certificate ID...';

            } else {

                input.placeholder =
                    'Enter Enrollment Number...';

            }

        });

    });


/* =====================================================
   FORM SUBMIT
   ===================================================== */

document
    .getElementById('verifyForm')
    .addEventListener('submit', function() {

        const button =
            this.querySelector('.verify-btn');

        button.innerHTML =
            '<i class="bi bi-arrow-repeat me-1"></i> Verifying...';

        button.disabled = true;

    });

</script>


</body>

</html>