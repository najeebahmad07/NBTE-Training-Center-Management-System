<?php
/**
 * RISE - Dashboard
 * ==================
 */

$pageTitle = 'Dashboard';
require_once 'includes/header.php';
require_once 'includes/sidebar.php';
requireLogin();

$db = getDB();
$userId = getCurrentUserId();
$role = $_SESSION['user_role'];

// Statistics
if (isSuperAdmin()) {
    // Super Admin Stats
    $stmtStudents = $db->query("SELECT COUNT(*) FROM students");
    $totalStudents = $stmtStudents->fetchColumn();

    $stmtAdmins = $db->query("SELECT COUNT(*) FROM admins WHERE role = 'admin'");
    $totalAdmins = $stmtAdmins->fetchColumn();

    $stmtPrograms = $db->query("SELECT COUNT(*) FROM programs");
    $totalPrograms = $stmtPrograms->fetchColumn();

    $stmtCerts = $db->query("SELECT COUNT(*) FROM certificates");
    $totalCerts = $stmtCerts->fetchColumn();

    $stmtPending = $db->query("SELECT COUNT(*) FROM students WHERE status = 'Pending'");
    $totalPending = $stmtPending->fetchColumn();

    $stmtApproved = $db->query("SELECT COUNT(*) FROM students WHERE status = 'Approved'");
    $totalApproved = $stmtApproved->fetchColumn();

    // Revenue
    $stmtRevenue = $db->query("SELECT COALESCE(SUM(amount), 0) FROM wallet_transactions WHERE type = 'credit' AND status = 'success'");
    $totalRevenue = $stmtRevenue->fetchColumn();

    // Monthly students for chart
    $stmtMonthly = $db->query("SELECT DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as count FROM students WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH) GROUP BY month ORDER BY month");
    $monthlyData = $stmtMonthly->fetchAll();

    // Recent students
    $stmtRecent = $db->query("SELECT s.*, a.name as admin_name, p.program_name FROM students s JOIN admins a ON s.admin_id = a.id JOIN programs p ON s.program_id = p.id ORDER BY s.created_at DESC LIMIT 10");
    $recentStudents = $stmtRecent->fetchAll();

} else {
    // Admin Stats
    $stmtStudents = $db->prepare("SELECT COUNT(*) FROM students WHERE admin_id = :admin_id");
    $stmtStudents->execute([':admin_id' => $userId]);
    $totalStudents = $stmtStudents->fetchColumn();

    $stmtPending = $db->prepare("SELECT COUNT(*) FROM students WHERE admin_id = :admin_id AND status = 'Pending'");
    $stmtPending->execute([':admin_id' => $userId]);
    $totalPending = $stmtPending->fetchColumn();

    $stmtApproved = $db->prepare("SELECT COUNT(*) FROM students WHERE admin_id = :admin_id AND status = 'Approved'");
    $stmtApproved->execute([':admin_id' => $userId]);
    $totalApproved = $stmtApproved->fetchColumn();

    $walletBalance = getWalletBalance($userId);

    $stmtCerts = $db->prepare("SELECT COUNT(*) FROM certificates c JOIN students s ON c.student_id = s.id WHERE s.admin_id = :admin_id");
    $stmtCerts->execute([':admin_id' => $userId]);
    $totalCerts = $stmtCerts->fetchColumn();

    // Monthly students for chart
    $stmtMonthly = $db->prepare("SELECT DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as count FROM students WHERE admin_id = :admin_id AND created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH) GROUP BY month ORDER BY month");
    $stmtMonthly->execute([':admin_id' => $userId]);
    $monthlyData = $stmtMonthly->fetchAll();

    // Recent students
    $stmtRecent = $db->prepare("SELECT s.*, p.program_name FROM students s JOIN programs p ON s.program_id = p.id WHERE s.admin_id = :admin_id ORDER BY s.created_at DESC LIMIT 10");
    $stmtRecent->execute([':admin_id' => $userId]);
    $recentStudents = $stmtRecent->fetchAll();
}

// Prepare chart data
$chartLabels = [];
$chartValues = [];
foreach ($monthlyData as $md) {
    $chartLabels[] = date('M Y', strtotime($md['month'] . '-01'));
    $chartValues[] = (int) $md['count'];
}
?>

<!-- Stats Row -->
<div class="row g-4 mb-4">
    <?php if (isSuperAdmin()): ?>
    <div class="col-xl-3 col-md-6">
        <div class="stat-card border-primary">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="stat-value"><?php echo number_format($totalStudents); ?></div>
                    <div class="stat-label">Total Students</div>
                </div>
                <div class="stat-icon bg-primary-soft">
                    <i class="fas fa-user-graduate"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="stat-card border-success">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="stat-value"><?php echo number_format($totalAdmins); ?></div>
                    <div class="stat-label">Total Admins</div>
                </div>
                <div class="stat-icon bg-success-soft">
                    <i class="fas fa-users-cog"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="stat-card border-info">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="stat-value"><?php echo number_format($totalCerts); ?></div>
                    <div class="stat-label">Certificates</div>
                </div>
                <div class="stat-icon bg-info-soft">
                    <i class="fas fa-certificate"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="stat-card border-warning">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="stat-value"><?php echo CURRENCY_SYMBOL . number_format($totalRevenue); ?></div>
                    <div class="stat-label">Total Revenue</div>
                </div>
                <div class="stat-icon bg-warning-soft">
                    <i class="fas fa-rupee-sign"></i>
                </div>
            </div>
        </div>
    </div>

    <?php else: ?>
    <div class="col-xl-3 col-md-6">
        <div class="stat-card border-primary">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="stat-value"><?php echo number_format($totalStudents); ?></div>
                    <div class="stat-label">My Students</div>
                </div>
                <div class="stat-icon bg-primary-soft">
                    <i class="fas fa-user-graduate"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="stat-card border-warning">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="stat-value"><?php echo number_format($totalPending); ?></div>
                    <div class="stat-label">Pending</div>
                </div>
                <div class="stat-icon bg-warning-soft">
                    <i class="fas fa-clock"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="stat-card border-success">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="stat-value"><?php echo number_format($totalApproved); ?></div>
                    <div class="stat-label">Approved</div>
                </div>
                <div class="stat-icon bg-success-soft">
                    <i class="fas fa-check-circle"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="stat-card border-info">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="stat-value"><?php echo CURRENCY_SYMBOL . number_format($walletBalance, 2); ?></div>
                    <div class="stat-label">Wallet Balance</div>
                </div>
                <div class="stat-icon bg-info-soft">
                    <i class="fas fa-wallet"></i>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Charts & Data Row -->
<div class="row g-4 mb-4">
    <!-- Monthly Chart -->
    <div class="col-xl-8">
        <div class="card">
            <div class="card-header">
                <h6><i class="fas fa-chart-line me-2"></i>Student Registrations (Last 12 Months)</h6>
            </div>
            <div class="card-body">
                <div class="chart-container">
                    <canvas id="monthlyChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Status Pie -->
    <div class="col-xl-4">
        <div class="card">
            <div class="card-header">
                <h6><i class="fas fa-chart-pie me-2"></i>Student Status</h6>
            </div>
            <div class="card-body">
                <div class="chart-container">
                    <canvas id="statusChart"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Recent Students -->
<div class="card">
    <div class="card-header">
        <h6><i class="fas fa-clock me-2"></i>Recent Students</h6>
        <a href="students.php" class="btn btn-sm btn-outline-primary">View All</a>
    </div>
    <div class="card-body p-0">
        <?php if (empty($recentStudents)): ?>
        <div class="empty-state">
            <i class="fas fa-users"></i>
            <p>No students found</p>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Enrollment</th>
                        <th>Program</th>
                        <?php if (isSuperAdmin()): ?><th>Admin</th><?php endif; ?>
                        <th>Status</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentStudents as $student): ?>
                    <tr>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <?php if ($student['photo']): ?>
                                <img src="uploads/photos/<?php echo sanitize($student['photo']); ?>"
                                     alt="Photo" class="rounded-circle" width="32" height="32"
                                     style="object-fit:cover;">
                                <?php else: ?>
                                <div class="user-avatar" style="width:32px;height:32px;font-size:0.7rem;">
                                    <?php echo strtoupper(substr($student['full_name'], 0, 1)); ?>
                                </div>
                                <?php endif; ?>
                                <?php echo sanitize($student['full_name']); ?>
                            </div>
                        </td>
                        <td><code><?php echo sanitize($student['enrollment_no']); ?></code></td>
                        <td><?php echo sanitize($student['program_name']); ?></td>
                        <?php if (isSuperAdmin()): ?>
                        <td><?php echo sanitize($student['admin_name'] ?? ''); ?></td>
                        <?php endif; ?>
                        <td>
                            <?php if ($student['status'] === 'Approved'): ?>
                            <span class="badge bg-success">Approved</span>
                            <?php else: ?>
                            <span class="badge bg-warning text-dark">Pending</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo date('d M Y', strtotime($student['created_at'])); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Monthly Chart
    const monthlyCtx = document.getElementById('monthlyChart');
    if (monthlyCtx) {
        new Chart(monthlyCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($chartLabels); ?>,
                datasets: [{
                    label: 'Students Registered',
                    data: <?php echo json_encode($chartValues); ?>,
                    borderColor: '#4e73df',
                    backgroundColor: 'rgba(78, 115, 223, 0.1)',
                    fill: true,
                    tension: 0.4,
                    borderWidth: 2,
                    pointBackgroundColor: '#4e73df',
                    pointRadius: 4,
                    pointHoverRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { stepSize: 1 }
                    }
                }
            }
        });
    }

    // Status Chart
    const statusCtx = document.getElementById('statusChart');
    if (statusCtx) {
        new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: ['Approved', 'Pending'],
                datasets: [{
                    data: [<?php echo isSuperAdmin() ? $totalApproved : $totalApproved; ?>, <?php echo $totalPending; ?>],
                    backgroundColor: ['#1cc88a', '#f6c23e'],
                    borderWidth: 0,
                    hoverOffset: 10
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '65%',
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 20,
                            usePointStyle: true
                        }
                    }
                }
            }
        });
    }
});
</script>

<?php require_once 'includes/footer.php'; ?>