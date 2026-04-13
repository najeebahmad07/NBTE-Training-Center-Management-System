<?php
/**
 * RISE SaaS - AJAX: Dashboard Statistics
 * File: ajax_dashboard_stats.php
 */

require_once 'includes/auth.php';
requireLogin();
require_once 'includes/db.php';

header('Content-Type: application/json; charset=utf-8');

$role = $_SESSION['role'];
$admin_id = $_SESSION['admin_id'];

try {
    $response = [
        'status' => 'success',
        'stats' => [],
        'charts' => []
    ];

    // ===================== SUPER ADMIN STATS =====================
    if ($role === 'super_admin') {

        // Total Admins
        $stmt = $pdo->query("SELECT COUNT(*) FROM admins WHERE role = 'admin'");
        $response['stats']['total_admins'] = (int)$stmt->fetchColumn();

        // Active Admins
        $stmt = $pdo->query("SELECT COUNT(*) FROM admins WHERE role = 'admin' AND status = 'active'");
        $response['stats']['active_admins'] = (int)$stmt->fetchColumn();

        // Total Students
        $stmt = $pdo->query("SELECT COUNT(*) FROM students");
        $response['stats']['total_students'] = (int)$stmt->fetchColumn();

        // Approved Students
        $stmt = $pdo->query("SELECT COUNT(*) FROM students WHERE status = 'Approved'");
        $response['stats']['approved_students'] = (int)$stmt->fetchColumn();

        // Pending Students
        $stmt = $pdo->query("SELECT COUNT(*) FROM students WHERE status = 'Pending'");
        $response['stats']['pending_students'] = (int)$stmt->fetchColumn();

        // Total Programs
        $stmt = $pdo->query("SELECT COUNT(*) FROM programs");
        $response['stats']['total_programs'] = (int)$stmt->fetchColumn();

        // Total Courses
        $stmt = $pdo->query("SELECT COUNT(*) FROM courses");
        $response['stats']['total_courses'] = (int)$stmt->fetchColumn();

        // Total Subjects
        $stmt = $pdo->query("SELECT COUNT(*) FROM subjects");
        $response['stats']['total_subjects'] = (int)$stmt->fetchColumn();

        // Total Certificates
        $stmt = $pdo->query("SELECT COUNT(*) FROM certificates");
        $response['stats']['total_certificates'] = (int)$stmt->fetchColumn();

        // Total Wallet Transactions (Revenue)
        $stmt = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM wallet_transactions WHERE type = 'credit' AND status = 'success'");
        $response['stats']['total_revenue'] = (float)$stmt->fetchColumn();

        // Total Deductions
        $stmt = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM wallet_transactions WHERE type = 'debit' AND status = 'success'");
        $response['stats']['total_deductions'] = (float)$stmt->fetchColumn();

        // ---- CHART DATA ----

        // Students per Program (Pie Chart)
        $stmt = $pdo->query("
            SELECT p.program_name, COUNT(s.id) as count
            FROM programs p
            LEFT JOIN students s ON s.program_id = p.id
            GROUP BY p.id, p.program_name
            ORDER BY count DESC
            LIMIT 10
        ");
        $program_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $response['charts']['students_per_program'] = [
            'labels' => array_column($program_data, 'program_name'),
            'data' => array_map('intval', array_column($program_data, 'count'))
        ];

        // Monthly Student Registrations (Bar Chart - last 12 months)
        $stmt = $pdo->query("
            SELECT DATE_FORMAT(created_at, '%Y-%m') as month,
                   DATE_FORMAT(created_at, '%b %Y') as month_label,
                   COUNT(*) as count
            FROM students
            WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
            GROUP BY month
            ORDER BY month ASC
        ");
        $monthly_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $response['charts']['monthly_registrations'] = [
            'labels' => array_column($monthly_data, 'month_label'),
            'data' => array_map('intval', array_column($monthly_data, 'count'))
        ];

        // Monthly Revenue (Line Chart)
        $stmt = $pdo->query("
            SELECT DATE_FORMAT(created_at, '%Y-%m') as month,
                   DATE_FORMAT(created_at, '%b %Y') as month_label,
                   COALESCE(SUM(amount), 0) as total
            FROM wallet_transactions
            WHERE type = 'credit' AND status = 'success'
            AND created_at >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
            GROUP BY month
            ORDER BY month ASC
        ");
        $revenue_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $response['charts']['monthly_revenue'] = [
            'labels' => array_column($revenue_data, 'month_label'),
            'data' => array_map('floatval', array_column($revenue_data, 'total'))
        ];

        // Students by Status (Doughnut Chart)
        $response['charts']['students_by_status'] = [
            'labels' => ['Approved', 'Pending'],
            'data' => [
                $response['stats']['approved_students'],
                $response['stats']['pending_students']
            ]
        ];

        // Top 5 Admins by Student Count
        $stmt = $pdo->query("
            SELECT a.name, COUNT(s.id) as student_count
            FROM admins a
            LEFT JOIN students s ON s.admin_id = a.id
            WHERE a.role = 'admin'
            GROUP BY a.id, a.name
            ORDER BY student_count DESC
            LIMIT 5
        ");
        $top_admins = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $response['charts']['top_admins'] = [
            'labels' => array_column($top_admins, 'name'),
            'data' => array_map('intval', array_column($top_admins, 'student_count'))
        ];

        // Recent registrations (last 5)
        $stmt = $pdo->query("
            SELECT s.full_name, s.enrollment_no, s.status, s.created_at,
                   p.program_name, a.name as admin_name
            FROM students s
            JOIN programs p ON s.program_id = p.id
            JOIN admins a ON s.admin_id = a.id
            ORDER BY s.created_at DESC
            LIMIT 5
        ");
        $response['recent_students'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ===================== ADMIN STATS =====================
    } else {

        // My Total Students
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM students WHERE admin_id = :admin_id");
        $stmt->execute([':admin_id' => $admin_id]);
        $response['stats']['total_students'] = (int)$stmt->fetchColumn();

        // My Approved Students
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM students WHERE admin_id = :admin_id AND status = 'Approved'");
        $stmt->execute([':admin_id' => $admin_id]);
        $response['stats']['approved_students'] = (int)$stmt->fetchColumn();

        // My Pending Students
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM students WHERE admin_id = :admin_id AND status = 'Pending'");
        $stmt->execute([':admin_id' => $admin_id]);
        $response['stats']['pending_students'] = (int)$stmt->fetchColumn();

        // My Certificates Generated
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM certificates cert
            JOIN students s ON cert.student_id = s.id
            WHERE s.admin_id = :admin_id
        ");
        $stmt->execute([':admin_id' => $admin_id]);
        $response['stats']['total_certificates'] = (int)$stmt->fetchColumn();

        // Wallet Balance
        $credit_stmt = $pdo->prepare("
            SELECT COALESCE(SUM(amount), 0) FROM wallet_transactions
            WHERE admin_id = :admin_id AND type = 'credit' AND status = 'success'
        ");
        $credit_stmt->execute([':admin_id' => $admin_id]);
        $total_credit = (float)$credit_stmt->fetchColumn();

        $debit_stmt = $pdo->prepare("
            SELECT COALESCE(SUM(amount), 0) FROM wallet_transactions
            WHERE admin_id = :admin_id AND type = 'debit' AND status = 'success'
        ");
        $debit_stmt->execute([':admin_id' => $admin_id]);
        $total_debit = (float)$debit_stmt->fetchColumn();

        $response['stats']['wallet_balance'] = $total_credit - $total_debit;
        $response['stats']['total_recharged'] = $total_credit;
        $response['stats']['total_spent'] = $total_debit;

        // ---- CHART DATA ----

        // My Students per Program
        $stmt = $pdo->prepare("
            SELECT p.program_name, COUNT(s.id) as count
            FROM programs p
            LEFT JOIN students s ON s.program_id = p.id AND s.admin_id = :admin_id
            GROUP BY p.id, p.program_name
            HAVING count > 0
            ORDER BY count DESC
        ");
        $stmt->execute([':admin_id' => $admin_id]);
        $program_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $response['charts']['students_per_program'] = [
            'labels' => array_column($program_data, 'program_name'),
            'data' => array_map('intval', array_column($program_data, 'count'))
        ];

        // My Monthly Registrations
        $stmt = $pdo->prepare("
            SELECT DATE_FORMAT(created_at, '%Y-%m') as month,
                   DATE_FORMAT(created_at, '%b %Y') as month_label,
                   COUNT(*) as count
            FROM students
            WHERE admin_id = :admin_id
            AND created_at >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
            GROUP BY month
            ORDER BY month ASC
        ");
        $stmt->execute([':admin_id' => $admin_id]);
        $monthly_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $response['charts']['monthly_registrations'] = [
            'labels' => array_column($monthly_data, 'month_label'),
            'data' => array_map('intval', array_column($monthly_data, 'count'))
        ];

        // My Wallet Transactions (Line Chart)
        $stmt = $pdo->prepare("
            SELECT DATE_FORMAT(created_at, '%Y-%m') as month,
                   DATE_FORMAT(created_at, '%b %Y') as month_label,
                   SUM(CASE WHEN type = 'credit' THEN amount ELSE 0 END) as credits,
                   SUM(CASE WHEN type = 'debit' THEN amount ELSE 0 END) as debits
            FROM wallet_transactions
            WHERE admin_id = :admin_id AND status = 'success'
            AND created_at >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
            GROUP BY month
            ORDER BY month ASC
        ");
        $stmt->execute([':admin_id' => $admin_id]);
        $wallet_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $response['charts']['wallet_transactions'] = [
            'labels' => array_column($wallet_data, 'month_label'),
            'credits' => array_map('floatval', array_column($wallet_data, 'credits')),
            'debits' => array_map('floatval', array_column($wallet_data, 'debits'))
        ];

        // Students by Status
        $response['charts']['students_by_status'] = [
            'labels' => ['Approved', 'Pending'],
            'data' => [
                $response['stats']['approved_students'],
                $response['stats']['pending_students']
            ]
        ];

        // Recent Students (last 5)
        $stmt = $pdo->prepare("
            SELECT s.full_name, s.enrollment_no, s.status, s.created_at,
                   p.program_name
            FROM students s
            JOIN programs p ON s.program_id = p.id
            WHERE s.admin_id = :admin_id
            ORDER BY s.created_at DESC
            LIMIT 5
        ");
        $stmt->execute([':admin_id' => $admin_id]);
        $response['recent_students'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Recent Wallet Transactions (last 5)
        $stmt = $pdo->prepare("
            SELECT amount, type, transaction_type, status, created_at
            FROM wallet_transactions
            WHERE admin_id = :admin_id
            ORDER BY created_at DESC
            LIMIT 5
        ");
        $stmt->execute([':admin_id' => $admin_id]);
        $response['recent_transactions'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    echo json_encode($response);

} catch (PDOException $e) {
    error_log('Dashboard Stats Error: ' . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to load dashboard statistics.'
    ]);
}

exit;