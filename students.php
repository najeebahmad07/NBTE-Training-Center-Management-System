<?php
/**
 * RISE - Students List
 * =======================
 */

$pageTitle = 'Students';
require_once 'includes/header.php';
require_once 'includes/sidebar.php';
requireLogin();

$db = getDB();
$userId = getCurrentUserId();

// Search & Filter
$search = sanitize($_GET['search'] ?? '');
$statusFilter = sanitize($_GET['status'] ?? '');
$programFilter = (int) ($_GET['program'] ?? 0);

$where = [];
$params = [];

if (isSuperAdmin()) {
    // Super admin sees all
} else {
    $where[] = "s.admin_id = :admin_id";
    $params[':admin_id'] = $userId;
}

if (!empty($search)) {
    $where[] = "(s.full_name LIKE :search OR s.enrollment_no LIKE :search2 OR s.roll_no LIKE :search3 OR s.mobile LIKE :search4)";
    $params[':search'] = "%$search%";
    $params[':search2'] = "%$search%";
    $params[':search3'] = "%$search%";
    $params[':search4'] = "%$search%";
}

if (!empty($statusFilter) && in_array($statusFilter, ['Pending', 'Approved'])) {
    $where[] = "s.status = :status";
    $params[':status'] = $statusFilter;
}

if ($programFilter > 0) {
    $where[] = "s.program_id = :program_id";
    $params[':program_id'] = $programFilter;
}

$whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

$sql = "SELECT s.*, p.program_name, c.course_name, a.name as admin_name
        FROM students s
        JOIN programs p ON s.program_id = p.id
        JOIN courses c ON s.course_id = c.id
        JOIN admins a ON s.admin_id = a.id
        $whereClause
        ORDER BY s.created_at DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$students = $stmt->fetchAll();

// Programs for filter
$stmtPrograms = $db->query("SELECT * FROM programs ORDER BY program_name");
$programs = $stmtPrograms->fetchAll();
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
    <div>
        <h5 class="mb-0">Students</h5>
        <small class="text-muted"><?php echo count($students); ?> student(s) found</small>
    </div>
    <?php if (!isSuperAdmin()): ?>
    <a href="add_student.php" class="btn btn-primary">
        <i class="fas fa-user-plus me-2"></i>Add Student
    </a>
    <?php endif; ?>
</div>

<!-- Filters -->
<div class="card mb-4">
    <div class="card-body py-3">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-4">
                <label class="form-label">Search</label>
                <input type="text" class="form-control" name="search" placeholder="Name, Enrollment, Roll, Mobile"
                       value="<?php echo $search; ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Status</label>
                <select class="form-select" name="status">
                    <option value="">All</option>
                    <option value="Pending" <?php echo $statusFilter === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="Approved" <?php echo $statusFilter === 'Approved' ? 'selected' : ''; ?>>Approved</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Program</label>
                <select class="form-select" name="program">
                    <option value="">All</option>
                    <?php foreach ($programs as $p): ?>
                    <option value="<?php echo $p['id']; ?>" <?php echo $programFilter == $p['id'] ? 'selected' : ''; ?>>
                        <?php echo sanitize($p['program_name']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100"><i class="fas fa-search me-1"></i>Filter</button>
            </div>
        </form>
    </div>
</div>

<!-- Students Table -->
<div class="card">
    <div class="card-body p-0">
        <?php if (empty($students)): ?>
        <div class="empty-state">
            <i class="fas fa-user-graduate"></i>
            <p>No students found</p>
            <?php if (!isSuperAdmin()): ?>
            <a href="add_student.php" class="btn btn-primary"><i class="fas fa-user-plus me-1"></i>Add First Student</a>
            <?php endif; ?>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Photo</th>
                        <th>Name</th>
                        <th>Enrollment</th>
                        <th>Program</th>
                        <th>Course</th>
                        <?php if (isSuperAdmin()): ?><th>Admin</th><?php endif; ?>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($students as $i => $s): ?>
                    <tr>
                        <td><?php echo $i + 1; ?></td>
                        <td>
                            <?php if ($s['photo']): ?>
                            <img src="uploads/photos/<?php echo sanitize($s['photo']); ?>"
                                 class="rounded-circle" width="36" height="36" style="object-fit:cover;">
                            <?php else: ?>
                            <div class="user-avatar" style="width:36px;height:36px;font-size:0.75rem;">
                                <?php echo strtoupper(substr($s['full_name'], 0, 1)); ?>
                            </div>
                            <?php endif; ?>
                        </td>
                        <td><strong><?php echo sanitize($s['full_name']); ?></strong></td>
                        <td><code><?php echo sanitize($s['enrollment_no']); ?></code></td>
                        <td><small><?php echo sanitize($s['program_name']); ?></small></td>
                        <td><small><?php echo sanitize($s['course_name']); ?></small></td>
                        <?php if (isSuperAdmin()): ?>
                        <td><small><?php echo sanitize($s['admin_name']); ?></small></td>
                        <?php endif; ?>
                        <td>
                            <?php if ($s['status'] === 'Approved'): ?>
                            <span class="badge bg-success">Approved</span>
                            <?php else: ?>
                            <span class="badge bg-warning text-dark">Pending</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <a href="view_student.php?id=<?php echo $s['id']; ?>" class="btn btn-outline-primary" title="View">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <?php if (!isSuperAdmin()): ?>
                                <a href="edit_student.php?id=<?php echo $s['id']; ?>" class="btn btn-outline-warning" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <?php if ($s['status'] === 'Pending'): ?>
                                <a href="approve_student.php?id=<?php echo $s['id']; ?>" class="btn btn-outline-success" title="Approve">
                                    <i class="fas fa-check"></i>
                                </a>
                                <?php endif; ?>
                                <a href="delete_student.php?id=<?php echo $s['id']; ?>" class="btn btn-outline-danger" title="Delete"
                                   data-confirm="Are you sure you want to delete this student?">
                                    <i class="fas fa-trash"></i>
                                </a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>