<?php
/**
 * RISE - Notifications
 * =====================
 * Super Admin: sees certificate requests from admins → Approve / Reject
 * Admin:       sees responses (approved / rejected) from Super Admin
 */

$pageTitle = 'Notifications';
require_once 'includes/header.php';
require_once 'includes/sidebar.php';
requireLogin();

$db     = getDB();
$userId = getCurrentUserId();

// ── Super Admin: Handle approve / reject POST ─────────────────────────────────
if (isSuperAdmin() && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $notifId   = (int) ($_POST['notif_id']   ?? 0);
    $studentId = (int) ($_POST['student_id'] ?? 0);
    $action    = $_POST['action'] ?? '';

    if ($notifId > 0 && $studentId > 0 && in_array($action, ['approve', 'reject'])) {
        $db->beginTransaction();
        try {
            $certStatus = ($action === 'approve') ? 'Approved' : 'Rejected';

            // Update student certificate_approved
            $stmt = $db->prepare("UPDATE students SET certificate_approved = :status WHERE id = :id");
            $stmt->execute([':status' => $certStatus, ':id' => $studentId]);

            // Mark this notification as read
            $stmt = $db->prepare("UPDATE notifications SET is_read = 1 WHERE id = :id");
            $stmt->execute([':id' => $notifId]);

            // Fetch student info to notify admin back
            $stmt = $db->prepare("SELECT full_name, enrollment_no, admin_id FROM students WHERE id = :id");
            $stmt->execute([':id' => $studentId]);
            $studentData = $stmt->fetch();

            if ($studentData) {
                $msgType = ($action === 'approve') ? 'certificate_approved' : 'certificate_rejected';
                $msgText = ($action === 'approve')
                    ? 'Super Admin approved certificate generation for ' . $studentData['full_name'] . ' (' . $studentData['enrollment_no'] . '). You can now generate the certificate.'
                    : 'Super Admin rejected certificate generation for ' . $studentData['full_name'] . ' (' . $studentData['enrollment_no'] . '). Please contact support.';

                $stmt = $db->prepare("
                    INSERT INTO notifications (from_admin, to_admin, student_id, type, message, is_read)
                    VALUES (:from_admin, :to_admin, :student_id, :type, :message, 0)
                ");
                $stmt->execute([
                    ':from_admin' => $userId,
                    ':to_admin'   => $studentData['admin_id'],
                    ':student_id' => $studentId,
                    ':type'       => $msgType,
                    ':message'    => $msgText,
                ]);
            }

            $db->commit();
            setFlashMessage('success', 'Certificate ' . $certStatus . ' successfully.');

        } catch (Exception $e) {
            $db->rollBack();
            error_log("Certificate approval error: " . $e->getMessage());
            setFlashMessage('error', 'Action failed. Please try again.');
        }
    }

    header('Location: notifications.php');
    exit;
}

// ── Fetch notifications based on role ────────────────────────────────────────
if (isSuperAdmin()) {
    // Super Admin: incoming certificate requests from admins
    $stmt = $db->prepare("
        SELECT n.*, s.full_name, s.enrollment_no, s.certificate_approved,
               p.program_name, a.name AS admin_name, a.college_name
        FROM notifications n
        JOIN students s ON n.student_id = s.id
        JOIN programs p ON s.program_id = p.id
        JOIN admins a   ON n.from_admin = a.id
        WHERE n.to_admin = :to_admin
          AND n.type = 'certificate_request'
        ORDER BY n.is_read ASC, n.created_at DESC
    ");
    $stmt->execute([':to_admin' => $userId]);
} else {
    // Admin: responses from Super Admin (approved / rejected)
    $stmt = $db->prepare("
        SELECT n.*, s.full_name, s.enrollment_no, s.certificate_approved,
               p.program_name
        FROM notifications n
        JOIN students s ON n.student_id = s.id
        JOIN programs p ON s.program_id = p.id
        WHERE n.to_admin = :to_admin
          AND n.type IN ('certificate_approved', 'certificate_rejected')
        ORDER BY n.is_read ASC, n.created_at DESC
    ");
    $stmt->execute([':to_admin' => $userId]);
}

$notifications = $stmt->fetchAll();
$unread        = array_filter($notifications, fn($n) => !$n['is_read']);

// Mark all as read when admin opens page
if (!isSuperAdmin() && !empty($unread)) {
    $db->prepare("
        UPDATE notifications SET is_read = 1
        WHERE to_admin = :id AND type IN ('certificate_approved','certificate_rejected')
    ")->execute([':id' => $userId]);
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h5 class="mb-0">
            <i class="fas fa-bell me-2"></i>
            <?php echo isSuperAdmin() ? 'Certificate Approval Requests' : 'My Notifications'; ?>
        </h5>
        <small class="text-muted">
            <?php echo count($unread); ?> unread &bull; <?php echo count($notifications); ?> total
        </small>
    </div>
</div>

<?php $flash = getFlashMessage('success'); if ($flash): ?>
<div class="alert alert-success alert-dismissible fade show">
    <i class="fas fa-check-circle me-2"></i><?php echo sanitize($flash); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php $flashErr = getFlashMessage('error'); if ($flashErr): ?>
<div class="alert alert-danger alert-dismissible fade show">
    <i class="fas fa-exclamation-circle me-2"></i><?php echo sanitize($flashErr); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if (empty($notifications)): ?>
<!-- Empty State -->
<div class="card">
    <div class="card-body text-center py-5">
        <i class="fas fa-bell-slash fa-3x text-muted mb-3"></i>
        <p class="text-muted mb-0">No notifications yet.</p>
    </div>
</div>

<?php elseif (isSuperAdmin()): ?>
<!-- ══════════════════════════════════════════════════════════
     SUPER ADMIN VIEW — Certificate Requests Table
     ══════════════════════════════════════════════════════════ -->
<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table mb-0 align-middle">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Student</th>
                        <th>Enrollment</th>
                        <th>Program</th>
                        <th>Admin / Center</th>
                        <th>Requested At</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($notifications as $i => $n): ?>
                    <tr class="<?php echo !$n['is_read'] ? 'table-warning' : ''; ?>">
                        <td><?php echo $i + 1; ?></td>
                        <td>
                            <strong><?php echo sanitize($n['full_name']); ?></strong>
                            <?php if (!$n['is_read']): ?>
                            <span class="badge bg-danger ms-1">New</span>
                            <?php endif; ?>
                        </td>
                        <td><code><?php echo sanitize($n['enrollment_no']); ?></code></td>
                        <td><small><?php echo sanitize($n['program_name']); ?></small></td>
                        <td>
                            <small class="fw-bold"><?php echo sanitize($n['admin_name']); ?></small>
                            <?php if (!empty($n['college_name'])): ?>
                            <br><small class="text-muted"><?php echo sanitize($n['college_name']); ?></small>
                            <?php endif; ?>
                        </td>
                        <td><small><?php echo date('d M Y, h:i A', strtotime($n['created_at'])); ?></small></td>
                        <td>
                            <?php if ($n['certificate_approved'] === 'Approved'): ?>
                                <span class="badge bg-success">Approved</span>
                            <?php elseif ($n['certificate_approved'] === 'Rejected'): ?>
                                <span class="badge bg-danger">Rejected</span>
                            <?php else: ?>
                                <span class="badge bg-warning text-dark">Pending</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="d-flex gap-2 flex-wrap">
                                <!-- View Student -->
                                <a href="view_student.php?id=<?php echo $n['student_id']; ?>"
                                   class="btn btn-sm btn-outline-primary" target="_blank" title="View Student">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <?php if ($n['certificate_approved'] === 'Pending'): ?>
                                <!-- Approve -->
                                <form method="POST" style="display:inline"
                                      onsubmit="return confirm('Approve certificate for <?php echo sanitize($n['full_name']); ?>?')">
                                    <input type="hidden" name="notif_id"   value="<?php echo $n['id']; ?>">
                                    <input type="hidden" name="student_id" value="<?php echo $n['student_id']; ?>">
                                    <input type="hidden" name="action"     value="approve">
                                    <button type="submit" class="btn btn-sm btn-success">
                                        <i class="fas fa-check me-1"></i>Approve
                                    </button>
                                </form>
                                <!-- Reject -->
                                <form method="POST" style="display:inline"
                                      onsubmit="return confirm('Reject certificate for <?php echo sanitize($n['full_name']); ?>?')">
                                    <input type="hidden" name="notif_id"   value="<?php echo $n['id']; ?>">
                                    <input type="hidden" name="student_id" value="<?php echo $n['student_id']; ?>">
                                    <input type="hidden" name="action"     value="reject">
                                    <button type="submit" class="btn btn-sm btn-danger">
                                        <i class="fas fa-times me-1"></i>Reject
                                    </button>
                                </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php else: ?>
<!-- ══════════════════════════════════════════════════════════
     ADMIN VIEW — Responses from Super Admin (Notification Cards)
     ══════════════════════════════════════════════════════════ -->
<div class="row g-3">
    <?php foreach ($notifications as $n): ?>
    <div class="col-12">
        <div class="card border-<?php echo $n['type'] === 'certificate_approved' ? 'success' : 'danger'; ?>">
            <div class="card-body d-flex align-items-start gap-3">

                <!-- Icon -->
                <div class="flex-shrink-0">
                    <?php if ($n['type'] === 'certificate_approved'): ?>
                    <div class="rounded-circle bg-success d-flex align-items-center justify-content-center"
                         style="width:48px;height:48px;">
                        <i class="fas fa-check text-white fa-lg"></i>
                    </div>
                    <?php else: ?>
                    <div class="rounded-circle bg-danger d-flex align-items-center justify-content-center"
                         style="width:48px;height:48px;">
                        <i class="fas fa-times text-white fa-lg"></i>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Content -->
                <div class="flex-grow-1">
                    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                        <div>
                            <h6 class="mb-1">
                                <?php if ($n['type'] === 'certificate_approved'): ?>
                                <span class="text-success"><i class="fas fa-certificate me-1"></i>Certificate Approved</span>
                                <?php else: ?>
                                <span class="text-danger"><i class="fas fa-ban me-1"></i>Certificate Rejected</span>
                                <?php endif; ?>
                            </h6>
                            <p class="mb-1"><?php echo sanitize($n['message']); ?></p>
                            <small class="text-muted">
                                <i class="fas fa-graduation-cap me-1"></i><?php echo sanitize($n['program_name']); ?>
                                &bull;
                                <i class="fas fa-user me-1"></i><?php echo sanitize($n['full_name']); ?>
                                &bull;
                                <i class="fas fa-clock me-1"></i><?php echo date('d M Y, h:i A', strtotime($n['created_at'])); ?>
                            </small>
                        </div>
                        <div class="d-flex gap-2">
                            <a href="view_student.php?id=<?php echo $n['student_id']; ?>"
                               class="btn btn-sm btn-outline-primary">
                                <i class="fas fa-eye me-1"></i>View Student
                            </a>
                            <?php if ($n['type'] === 'certificate_approved'): ?>
                            <a href="generate_certificate.php?id=<?php echo $n['student_id']; ?>"
                               class="btn btn-sm btn-success" target="_blank">
                                <i class="fas fa-certificate me-1"></i>Generate Now
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>