<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Result Search | National Board for Technical Education</title>

    <style>
        :root {
            --nbte-navy: #062A5A;
            --nbte-gold: #D49729;
            --light-bg: #F8F9FC;
            --text-dark: #1F2937;
            --border: #E5E7EB;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            background: var(--light-bg);
            color: var(--text-dark);
            min-height: 100vh;
        }

        /* =========================
           TOP BAR
        ========================= */

        .top-bar {
            background: var(--nbte-navy);
            color: #fff;
            text-align: center;
            padding: 10px 15px;
            font-size: 13px;
        }

        /* =========================
           NAVBAR
        ========================= */

        .navbar {
            height: 78px;
            background: #fff;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 7%;
            border-bottom: 1px solid var(--border);
            box-shadow: 0 3px 15px rgba(6, 42, 90, 0.07);
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
        }

        .brand-logo {
            width: 50px;
            height: 50px;
            object-fit: contain;
        }

        .brand-name {
            color: var(--nbte-navy);
            font-size: 17px;
            font-weight: 700;
        }

        .home-btn {
            text-decoration: none;
            color: var(--nbte-navy);
            border: 1px solid var(--nbte-navy);
            padding: 9px 17px;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            transition: 0.3s;
        }

        .home-btn:hover {
            background: var(--nbte-navy);
            color: #fff;
        }

        /* =========================
           MAIN
        ========================= */

        .main {
            min-height: calc(100vh - 130px);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 20px;
        }

        /* =========================
           CARD
        ========================= */

        .result-card {
            width: 100%;
            max-width: 520px;
            background: #fff;
            border-radius: 12px;
            overflow: hidden;
            border: 1px solid var(--border);
            box-shadow: 0 12px 35px rgba(6, 42, 90, 0.10);
        }

        .card-top {
            height: 6px;
            background: linear-gradient(
                to right,
                var(--nbte-navy) 0%,
                var(--nbte-navy) 65%,
                var(--nbte-gold) 65%,
                var(--nbte-gold) 100%
            );
        }

        .card-body {
            padding: 38px;
        }

        .result-icon {
            width: 65px;
            height: 65px;
            margin: 0 auto 18px;
            border-radius: 50%;
            background: rgba(212, 151, 41, 0.12);
            border: 2px solid rgba(212, 151, 41, 0.35);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
        }

        .card-title {
            text-align: center;
            color: var(--nbte-navy);
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .card-subtitle {
            text-align: center;
            color: #6B7280;
            font-size: 14px;
            margin-bottom: 28px;
        }

        /* =========================
           INPUT
        ========================= */

        .form-label {
            display: block;
            color: var(--nbte-navy);
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 8px;
        }

        .input-box {
            width: 100%;
            height: 52px;
            padding: 0 15px;
            border: 1px solid #D1D5DB;
            border-radius: 7px;
            outline: none;
            font-size: 15px;
            color: var(--text-dark);
            transition: 0.3s;
        }

        .input-box:focus {
            border-color: var(--nbte-gold);
            box-shadow: 0 0 0 3px rgba(212, 151, 41, 0.12);
        }

        .input-box::placeholder {
            color: #9CA3AF;
        }

        /* =========================
           BUTTON
        ========================= */

        .search-btn {
            width: 100%;
            height: 52px;
            margin-top: 20px;
            border: none;
            border-radius: 7px;
            background: var(--nbte-navy);
            color: #fff;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            transition: 0.3s;
        }

        .search-btn:hover {
            background: var(--nbte-gold);
            box-shadow: 0 7px 18px rgba(212, 151, 41, 0.25);
        }

        /* =========================
           FOOTER
        ========================= */

        .footer {
            text-align: center;
            margin-top: 20px;
            color: #6B7280;
            font-size: 12px;
        }

        .footer strong {
            color: var(--nbte-navy);
        }

        /* =========================
           MOBILE
        ========================= */

        @media (max-width: 600px) {

            .navbar {
                padding: 0 15px;
            }

            .brand-logo {
                width: 42px;
                height: 42px;
            }

            .brand-name {
                font-size: 13px;
                max-width: 190px;
            }

            .home-btn {
                padding: 8px 12px;
                font-size: 13px;
            }

            .main {
                padding: 30px 15px;
            }

            .card-body {
                padding: 28px 20px;
            }

            .card-title {
                font-size: 24px;
            }

            .top-bar {
                font-size: 11px;
            }
        }
    </style>
</head>

<body>

    <!-- TOP BAR -->
    <div class="top-bar">
        National Board for Technical Education
    </div>

    <!-- NAVBAR -->
    <nav class="navbar">

        <a href="index.php" class="brand">

            <img src="assets/images/logo.jpg"
                 alt="NBTE Logo"
                 class="brand-logo">

            <div class="brand-name">
                National Board for Technical Education
            </div>

        </a>

        <a href="index.php" class="home-btn">
            ← Home
        </a>

    </nav>

    <!-- MAIN -->
    <main class="main">

        <div>

            <!-- RESULT CARD -->
            <div class="result-card">

                <div class="card-top"></div>

                <div class="card-body">

                    <div class="result-icon">
                        🔎
                    </div>

                    <h1 class="card-title">
                        Result Search
                    </h1>

                    <p class="card-subtitle">
                        Search your result using your Roll Number
                    </p>

                    <form method="GET" action="verify_marksheet.php">

                        <label for="enrollment" class="form-label">
                            Roll Number
                        </label>

                        <input
                            type="text"
                            name="enrollment"
                            id="enrollment"
                            class="input-box"
                            placeholder="Enter Roll Number"
                            required
                            autocomplete="off"
                        >

                        <button type="submit" class="search-btn">
                            🔎 &nbsp; Search Result
                        </button>

                    </form>

                </div>

            </div>

            <!-- FOOTER -->
            <div class="footer">
                © <?php echo date('Y'); ?>
                <strong>National Board for Technical Education</strong>
                — All Rights Reserved.
            </div>

        </div>

    </main>

</body>
</html>