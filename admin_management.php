<?php
$pageTitle = 'Admin Management';
require_once 'includes/header.php';
require_once 'includes/sidebar.php';
require __DIR__ . '/PHPMailer/src/PHPMailer.php';
require __DIR__ . '/PHPMailer/src/SMTP.php';
require __DIR__ . '/PHPMailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
requireSuperAdmin();

$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCSRF();
    $action = $_POST['action'] ?? '';

    // ── Create ────────────────────────────────────────────────────────────────
    if ($action === 'create') {
        $name         = sanitize($_POST['name'] ?? '');
        $college_name = sanitize($_POST['college_name'] ?? '');
        $email        = sanitize($_POST['email'] ?? '');
        $password     = $_POST['password'] ?? '';
        $confirm      = $_POST['confirm_password'] ?? '';
        $programs     = $_POST['programs'] ?? [];

        $errors = [];
        if (!$name)                                                $errors[] = 'Name required';
        if (!$college_name)                                        $errors[] = 'College name required';
        if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email required';
        if (strlen($password) < 6)                                 $errors[] = 'Password min 6 characters';
        if ($password !== $confirm)                                $errors[] = 'Passwords do not match';

        if (!$errors) {
            $chk = $db->prepare("SELECT COUNT(*) FROM admins WHERE email = :e");
            $chk->execute([':e' => $email]);
            if ($chk->fetchColumn() > 0) {
                setFlashMessage('error', 'Email already exists');
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $db->prepare("
                    INSERT INTO admins (name, college_name, email, password, role, status)
                    VALUES (:name, :college, :email, :pass, 'admin', 'active')
                ")->execute([
                    ':name'   => $name,
                    ':college'=> $college_name,
                    ':email'  => $email,
                    ':pass'   => $hash,
                ]);
                $new_id = (int)$db->lastInsertId();

                try {
    $mail = new PHPMailer(true);

    // SMTP (Hostinger)
    $mail->isSMTP();
    $mail->Host       = 'smtp.hostinger.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'info@reliableinclusiveskilledu.in';
    $mail->Password   = 'Mail@rise87'; // 🔴 replace
    $mail->SMTPSecure = 'tls';
    $mail->Port       = 587;

    // ===== ADMIN EMAIL =====
    $mail->setFrom('info@reliableinclusiveskilledu.in', 'RISE Panel');
    $mail->addAddress($email, $name);

    $mail->isHTML(true);
    $mail->Subject = 'Admin Account Created';

   $mail->Body = "
<div style='font-family:Arial, sans-serif; background:#f4f6f9; padding:20px;'>

    <div style='max-width:600px; margin:auto; background:#ffffff; padding:25px; border-radius:8px; box-shadow:0 2px 8px rgba(0,0,0,0.1);'>

        <h2 style='color:#2c3e50; text-align:center;'>Welcome to RISE Admin Panel </h2>

        <p>Dear <strong>$name</strong>,</p>

        <p>
            Your admin account has been successfully created for
            <strong>$college_name</strong>.
        </p>

        <div style='background:#f8f9fa; padding:15px; border-radius:6px; margin:20px 0;'>
            <h3 style='margin-top:0; color:#34495e;'>🔐 Login Credentials</h3>

            <p style='margin:5px 0;'>
                <strong>Username (Email):</strong><br> $email
            </p>

            <p style='margin:5px 0;'>
                <strong>Password:</strong><br> $password
            </p>
        </div>

        <div style='background:#fff3cd; padding:12px; border-radius:6px; color:#856404;'>
            <strong>⚠️ Important Note:</strong><br>
            Do not share your username and password with anyone.
            If you share your login details, RISE Authority will not be responsible for any misuse.
        </div>

        <br>

        <p>Regards,<br><strong>RISE Authority</strong></p>

    </div>

</div>
";

    $mail->send();

    // ===== OWNER EMAIL =====
    $mail->clearAddresses();

    $mail->addAddress('info@reliableinclusiveskilledu.in');

    $mail->Subject = 'New Admin Created';

    $mail->Body = "
<div style='font-family:Arial, sans-serif; background:#f4f6f9; padding:20px;'>

    <div style='max-width:600px; margin:auto; background:#ffffff; padding:25px; border-radius:8px;'>

        <h2 style='color:#2c3e50;'>New Admin Created</h2>

        <p><strong>Name:</strong> $name</p>
        <p><strong>College:</strong> $college_name</p>
        <p><strong>Email:</strong> $email</p>
        <p><strong>Password:</strong> $password</p>

        <br>

        <p style='color:#555;'>This admin has been successfully added to the system.</p>

    </div>

</div>
";

    $mail->send();

} catch (Exception $e) {
    // Uncomment for debug
    // echo $mail->ErrorInfo;
}

               if (!empty($programs)) {
    $ins = $db->prepare("INSERT IGNORE INTO admin_programs (admin_id, program_id) VALUES (:a, :p)");
    foreach ($programs as $pid) $ins->execute([':a' => $new_id, ':p' => (int)$pid]);
}

echo "<script>alert('Admin created successfully');window.location='admin_management.php';</script>";
}
} else {
    echo "<script>alert('" . implode("\\n", $errors) . "');window.location='admin_management.php';</script>";
}
exit;
}

    // ── Edit ──────────────────────────────────────────────────────────────────
    if ($action === 'edit') {
        $adminId      = (int)$_POST['admin_id'];
        $name         = sanitize($_POST['name'] ?? '');
        $college_name = sanitize($_POST['college_name'] ?? '');
        $email        = sanitize($_POST['email'] ?? '');

        $errors = [];
        if (!$name)                                                $errors[] = 'Name required';
        if (!$college_name)                                        $errors[] = 'College name required';
        if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email required';

        if (!$errors) {
            $chk = $db->prepare("SELECT COUNT(*) FROM admins WHERE email=:e AND id!=:id");
            $chk->execute([':e' => $email, ':id' => $adminId]);
            if ($chk->fetchColumn() > 0) {
                setFlashMessage('error', 'Email already used by another admin');
            } else {
    $db->prepare("UPDATE admins SET name=:n, college_name=:c, email=:e WHERE id=:id")
       ->execute([':n'=>$name,':c'=>$college_name,':e'=>$email,':id'=>$adminId]);
    echo "<script>alert('Admin updated');window.location='admin_management.php';</script>";
}
} else {
    echo "<script>alert('" . implode("\\n", $errors) . "');window.location='admin_management.php';</script>";
}
exit;
}

    // ── Assign Programs ───────────────────────────────────────────────────────
    if ($action === 'assign_programs') {
        $adminId  = (int)$_POST['admin_id'];
        $programs = $_POST['programs'] ?? [];
        $db->prepare("DELETE FROM admin_programs WHERE admin_id=:a")->execute([':a'=>$adminId]);
        if (!empty($programs)) {
            $ins = $db->prepare("INSERT IGNORE INTO admin_programs (admin_id, program_id) VALUES (:a,:p)");
            foreach ($programs as $pid) $ins->execute([':a'=>$adminId,':p'=>(int)$pid]);
        }
       echo "<script>alert('Programs assigned');window.location='admin_management.php';</script>";
exit;
    }

    // ── Change Password ───────────────────────────────────────────────────────
    if ($action === 'change_password') {
        $adminId = (int)$_POST['admin_id'];
        $pass    = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';
        if (strlen($pass) < 6) {
            setFlashMessage('error', 'Password min 6 characters');
        } elseif ($pass !== $confirm) {
            setFlashMessage('error', 'Passwords do not match');
        } else {
            $db->prepare("UPDATE admins SET password=:p WHERE id=:id")
               ->execute([':p'=>password_hash($pass,PASSWORD_DEFAULT),':id'=>$adminId]);
            echo "<script>alert('Password updated');window.location='admin_management.php';</script>";
}
exit;
}

    // ── Toggle Status ─────────────────────────────────────────────────────────
    if ($action === 'toggle_status') {
        $adminId = (int)$_POST['admin_id'];
        $status  = sanitize($_POST['new_status']);
        if ($adminId == getCurrentUserId()) {
            setFlashMessage('error', 'You cannot deactivate yourself');
        } else {
            $db->prepare("UPDATE admins SET status=:s WHERE id=:id")
               ->execute([':s'=>$status,':id'=>$adminId]);
           echo "<script>alert('Status updated');window.location='admin_management.php';</script>";
}
exit;
}}

// ── Fetch data ────────────────────────────────────────────────────────────────
$stmt = $db->prepare("
    SELECT a.*, (SELECT COUNT(*) FROM students WHERE admin_id=a.id) AS student_count
    FROM admins a WHERE a.role='admin' ORDER BY a.created_at DESC
");
$stmt->execute();
$admins = $stmt->fetchAll();

$all_programs = $db->query("SELECT * FROM programs ORDER BY program_name")->fetchAll();

$admin_programs_map = [];
foreach ($db->query("SELECT * FROM admin_programs")->fetchAll() as $r) {
    $admin_programs_map[$r['admin_id']][] = $r['program_id'];
}
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0">Admin Accounts</h5>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createAdminModal">
        <i class="bi bi-plus-lg me-1"></i> Create Admin
    </button>
</div>

<div class="card shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
               <thead class="table-light">
    <tr>
        <th class="fw-bold text-dark">#</th>
        <th class="fw-bold text-dark">Admin Name</th>
        <th class="fw-bold text-dark">clg/Center Name</th>
        <th class="fw-bold text-dark">Email</th>
        <th class="fw-bold text-dark">Students</th>
        <th class="fw-bold text-dark">Programs</th>
        <th class="fw-bold text-dark">Status</th>
        <th class="fw-bold text-dark">Actions</th>
    </tr>
</thead>
                <tbody>
                <?php foreach ($admins as $i => $admin): ?>
                    <?php $assigned = $admin_programs_map[$admin['id']] ?? []; ?>
                    <tr>
                        <td><?php echo $i + 1 ?></td>
                        <td><strong><?php echo htmlspecialchars($admin['name']) ?></strong></td>
                        <td>
                            <?php if (!empty($admin['college_name'])): ?>
                                <span class="fw-semibold text-primary">
                                    <?php echo htmlspecialchars($admin['college_name']) ?>
                                </span>
                            <?php else: ?>
                                <span class="text-muted small">—</span>
                            <?php endif ?>
                        </td>
                        <td class="small"><?php echo htmlspecialchars($admin['email']) ?></td>
                        <td><span class="badge bg-secondary"><?php echo $admin['student_count'] ?></span></td>
                        <td>
                            <?php if (empty($assigned)): ?>
                                <span class="text-muted small">None</span>
                            <?php else: ?>
                                <?php foreach ($all_programs as $p): ?>
                                    <?php if (in_array($p['id'], $assigned)): ?>
                                        <span class="badge bg-info text-dark me-1">
                                            <?php echo htmlspecialchars($p['program_name']) ?>
                                        </span>
                                    <?php endif ?>
                                <?php endforeach ?>
                            <?php endif ?>
                        </td>
                        <td>
                            <span class="badge bg-<?php echo $admin['status']==='active'?'success':'danger' ?>">
                                <?php echo ucfirst($admin['status']) ?>
                            </span>
                        </td>
                        <td>
                            <div class="d-flex flex-wrap gap-1">

                                <?php if ($admin['status'] === 'active'): ?>
                                <!-- ✅ Authorization Certificate — admin prints & hangs on wall -->
                                <a href="generate_admin_certificate.php?id=<?php echo $admin['id'] ?>"
                                   target="_blank"
                                   class="btn btn-sm btn-success"
                                   title="Generate RISE Authorization Certificate for this center">
                                    <i class="bi bi-patch-check-fill"></i> Auth Certificate
                                </a>
                                <?php endif ?>

                                <!-- Edit -->
                                <button class="btn btn-sm btn-outline-primary"
                                    data-bs-toggle="modal" data-bs-target="#editAdminModal"
                                    data-id="<?php echo $admin['id'] ?>"
                                    data-name="<?php echo htmlspecialchars($admin['name']) ?>"
                                    data-college="<?php echo htmlspecialchars($admin['college_name'] ?? '') ?>"
                                    data-email="<?php echo htmlspecialchars($admin['email']) ?>">
                                    <i class="bi bi-pencil"></i> Edit
                                </button>

                                <!-- Programs -->
                                <button class="btn btn-sm btn-primary"
                                    data-bs-toggle="modal" data-bs-target="#assignProgramModal"
                                    data-id="<?php echo $admin['id'] ?>"
                                    data-assigned="<?php echo htmlspecialchars(json_encode($assigned)) ?>">
                                    <i class="bi bi-bookmark"></i> Programs
                                </button>

                                <!-- Toggle Status -->
                                <form method="POST" class="d-inline">
                                    <?php echo csrfField(); ?>
                                    <input type="hidden" name="action" value="toggle_status">
                                    <input type="hidden" name="admin_id" value="<?php echo $admin['id'] ?>">
                                    <?php if ($admin['status']==='active'): ?>
                                        <input type="hidden" name="new_status" value="inactive">
                                        <button class="btn btn-sm btn-danger"><i class="bi bi-ban"></i> Deactivate</button>
                                    <?php else: ?>
                                        <input type="hidden" name="new_status" value="active">
                                        <button class="btn btn-sm btn-success"><i class="bi bi-check-circle"></i> Activate</button>
                                    <?php endif ?>
                                </form>

                                <!-- Change Password -->
                                <button class="btn btn-sm btn-warning"
                                    data-bs-toggle="modal" data-bs-target="#passwordModal"
                                    data-id="<?php echo $admin['id'] ?>">
                                    <i class="bi bi-key"></i> Password
                                </button>

                            </div>
                        </td>
                    </tr>
                <?php endforeach ?>
                <?php if (empty($admins)): ?>
                    <tr><td colspan="8" class="text-center text-muted py-4">No admins found</td></tr>
                <?php endif ?>
                </tbody>
            </table>
        </div>
    </div>
</div>


<!-- ── CREATE ADMIN ─────────────────────────────────────────────────────────── -->
<div class="modal fade" id="createAdminModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <?php echo csrfField(); ?>
                <input type="hidden" name="action" value="create">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="bi bi-person-plus me-2"></i>Create Admin</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">

                        <div class="col-md-6">
                            <label class="form-label fw-bold">Admin Name <span class="text-danger">*</span></label>
                            <input class="form-control" name="name" placeholder="Full name" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold">College / Center Name <span class="text-danger">*</span></label>
                            <input class="form-control" name="college_name"
                                   placeholder="e.g. Sunrise Institute of Technology" required>
                            <div class="form-text text-success">
                                <i class="bi bi-patch-check"></i>
                                This name is printed on the RISE Authorization Certificate.
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold">Email <span class="text-danger">*</span></label>
                            <input class="form-control" name="email" type="email"
                                   placeholder="admin@college.com" required>
                        </div>

                        <div class="col-md-6"></div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold">Password <span class="text-danger">*</span></label>
                            <input class="form-control" name="password" type="password"
                                   placeholder="Min 6 characters" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold">Confirm Password <span class="text-danger">*</span></label>
                            <input class="form-control" name="confirm_password" type="password"
                                   placeholder="Repeat password" required>
                        </div>

                        <div class="col-12">
                            <hr>
                            <label class="form-label fw-bold">Assign Programs</label>
                            <div class="row row-cols-2 row-cols-md-3 g-1">
                                <?php foreach ($all_programs as $p): ?>
                                    <div class="col">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox"
                                                   name="programs[]" value="<?php echo $p['id'] ?>"
                                                   id="cp_<?php echo $p['id'] ?>">
                                            <label class="form-check-label small" for="cp_<?php echo $p['id'] ?>">
                                                <?php echo htmlspecialchars($p['program_name']) ?>
                                            </label>
                                        </div>
                                    </div>
                                <?php endforeach ?>
                            </div>
                        </div>

                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-lg me-1"></i> Create Admin
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>


<!-- ── EDIT ADMIN ───────────────────────────────────────────────────────────── -->
<div class="modal fade" id="editAdminModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <?php echo csrfField(); ?>
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="admin_id" id="editAdminId">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="bi bi-pencil me-2"></i>Edit Admin</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Admin Name <span class="text-danger">*</span></label>
                        <input class="form-control" name="name" id="editName" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">College / Center Name <span class="text-danger">*</span></label>
                        <input class="form-control" name="college_name" id="editCollegeName" required>
                        <div class="form-text text-success">
                            <i class="bi bi-patch-check"></i> Printed on the RISE Authorization Certificate.
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Email <span class="text-danger">*</span></label>
                        <input class="form-control" name="email" id="editEmail" type="email" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-lg me-1"></i> Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>


<!-- ── ASSIGN PROGRAMS ──────────────────────────────────────────────────────── -->
<div class="modal fade" id="assignProgramModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <?php echo csrfField(); ?>
                <input type="hidden" name="action" value="assign_programs">
                <input type="hidden" name="admin_id" id="assignAdminId">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="bi bi-bookmark me-2"></i>Assign Programs</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row row-cols-1 row-cols-md-2 g-1">
                        <?php foreach ($all_programs as $p): ?>
                            <div class="col">
                                <div class="form-check">
                                    <input class="form-check-input assign-prog-check" type="checkbox"
                                           name="programs[]" value="<?php echo $p['id'] ?>"
                                           id="ap_<?php echo $p['id'] ?>">
                                    <label class="form-check-label" for="ap_<?php echo $p['id'] ?>">
                                        <?php echo htmlspecialchars($p['program_name']) ?>
                                    </label>
                                </div>
                            </div>
                        <?php endforeach ?>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Programs</button>
                </div>
            </form>
        </div>
    </div>
</div>


<!-- ── CHANGE PASSWORD ──────────────────────────────────────────────────────── -->
<div class="modal fade" id="passwordModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <?php echo csrfField(); ?>
                <input type="hidden" name="action" value="change_password">
                <input type="hidden" name="admin_id" id="adminPasswordId">
                <div class="modal-header bg-warning">
                    <h5 class="modal-title"><i class="bi bi-key me-2"></i>Change Password</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold">New Password</label>
                        <input class="form-control" type="password" name="new_password" placeholder="Min 6 characters" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Confirm Password</label>
                        <input class="form-control" type="password" name="confirm_password" placeholder="Repeat" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning"><i class="bi bi-check-lg me-1"></i>Update Password</button>
                </div>
            </form>
        </div>
    </div>
</div>


<script>
document.getElementById('passwordModal').addEventListener('show.bs.modal', function(e) {
    document.getElementById('adminPasswordId').value = e.relatedTarget.getAttribute('data-id');
});

document.getElementById('editAdminModal').addEventListener('show.bs.modal', function(e) {
    var b = e.relatedTarget;
    document.getElementById('editAdminId').value    = b.getAttribute('data-id');
    document.getElementById('editName').value        = b.getAttribute('data-name')    || '';
    document.getElementById('editCollegeName').value = b.getAttribute('data-college') || '';
    document.getElementById('editEmail').value       = b.getAttribute('data-email')   || '';
});

document.getElementById('assignProgramModal').addEventListener('show.bs.modal', function(e) {
    var b        = e.relatedTarget;
    var assigned = JSON.parse(b.getAttribute('data-assigned') || '[]');
    document.getElementById('assignAdminId').value = b.getAttribute('data-id');
    document.querySelectorAll('.assign-prog-check').forEach(function(cb) {
        cb.checked = assigned.includes(parseInt(cb.value));
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>