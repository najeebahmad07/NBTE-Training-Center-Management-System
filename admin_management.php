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

function uploadAdminFile($fieldName, $prefix = 'admin')
{
    if (!isset($_FILES[$fieldName]) || $_FILES[$fieldName]['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if ($_FILES[$fieldName]['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Upload failed for ' . $fieldName);
    }

    $uploadDir = __DIR__ . '/uploads/admin/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $allowed = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];
    $extension = strtolower(pathinfo($_FILES[$fieldName]['name'], PATHINFO_EXTENSION));

    if (!in_array($extension, $allowed, true)) {
        throw new RuntimeException('Invalid file type for ' . $fieldName);
    }

    if ($_FILES[$fieldName]['size'] > 5 * 1024 * 1024) {
        throw new RuntimeException('Maximum file size is 5MB for ' . $fieldName);
    }

    $filename = $prefix . '_' . date('YmdHis') . '_' . bin2hex(random_bytes(5)) . '.' . $extension;
    $destination = $uploadDir . $filename;

    if (!move_uploaded_file($_FILES[$fieldName]['tmp_name'], $destination)) {
        throw new RuntimeException('Could not save uploaded file for ' . $fieldName);
    }

    return $filename;
}


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

        $phone_number       = sanitize($_POST['phone_number'] ?? '');
        $designation        = sanitize($_POST['designation'] ?? '');
        $last_qualification = sanitize($_POST['last_qualification'] ?? '');
        $qualification      = sanitize($_POST['qualification'] ?? '');
        $aadhaar_number     = sanitize($_POST['aadhaar_number'] ?? '');
        $pan_card_number    = sanitize($_POST['pan_card_number'] ?? '');
        $work_exp           = sanitize($_POST['work_exp'] ?? '');
        $permanent_address  = sanitize($_POST['permanent_address'] ?? '');
        $personal_state     = sanitize($_POST['personal_state'] ?? '');
        $personal_pin       = sanitize($_POST['personal_pin'] ?? '');
        $faculty            = sanitize($_POST['faculty'] ?? '');
        $department_it_computer = isset($_POST['department_it_computer']) ? 1 : 0;
        $department_fire_safety = isset($_POST['department_fire_safety']) ? 1 : 0;

        $institute_name     = sanitize($_POST['institute_name'] ?? '');
        $institute_address  = sanitize($_POST['institute_address'] ?? '');
        $institute_state    = sanitize($_POST['institute_state'] ?? '');
        $district           = sanitize($_POST['district'] ?? '');
        $city               = sanitize($_POST['city'] ?? '');
        $institute_pin      = sanitize($_POST['institute_pin'] ?? '');
        $institute_phone    = sanitize($_POST['institute_phone'] ?? '');
        $website            = sanitize($_POST['website'] ?? '');

        $registered_no      = sanitize($_POST['registered_no'] ?? '');
        $registration_type = sanitize($_POST['registration_type'] ?? '');
        $registration_name = sanitize($_POST['registration_name'] ?? '');
        $registration_no   = sanitize($_POST['registration_no'] ?? '');

        $total_area         = sanitize($_POST['total_area'] ?? '');
        $geo_location       = sanitize($_POST['geo_location'] ?? '');
        $total_pc           = (int)($_POST['total_pc'] ?? 0);
        $total_staffs       = (int)($_POST['total_staffs'] ?? 0);
        $practical_labs     = (int)($_POST['practical_labs'] ?? 0);
        $theory_rooms       = (int)($_POST['theory_rooms'] ?? 0);
        $office             = isset($_POST['office']) ? 1 : 0;
        $toilet             = isset($_POST['toilet']) ? 1 : 0;

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

                try {
                    $passport_pic       = uploadAdminFile('passport_pic', 'passport');
                    $qualification_doc  = uploadAdminFile('qualification_doc', 'qualification');
                    $aadhaar_card       = uploadAdminFile('aadhaar_card', 'aadhaar');
                    $pan_card_doc       = uploadAdminFile('pan_card_doc', 'pan');
                    $signature          = uploadAdminFile('signature', 'signature');
                    $registration_doc   = uploadAdminFile('registration_doc', 'registration');
                    $lab_picture        = uploadAdminFile('lab_picture', 'lab');
                    $theory_room_pic    = uploadAdminFile('theory_room_pic', 'theory');
                    $office_pic_inner   = uploadAdminFile('office_pic_inner', 'office_inner');
                    $office_pic_outter  = uploadAdminFile('office_pic_outter', 'office_outer');

                    $insert = $db->prepare("
                        INSERT INTO admins (
                            name, college_name, email, password, role, status,
                            phone_number, designation, last_qualification,
                            passport_pic, qualification, qualification_doc,
                            aadhaar_number, aadhaar_card,
                            pan_card_number, pan_card_doc,
                            work_exp, signature, permanent_address,
                            personal_state, personal_pin,
                            faculty, department_it_computer, department_fire_safety,
                            institute_name, institute_address, institute_state,
                            district, city, institute_pin, institute_phone, website,
                            registered_no, registration_type, registration_name,
                            registration_no, registration_doc,
                            total_area, geo_location, total_pc, total_staffs,
                            practical_labs, lab_picture, theory_rooms, theory_room_pic,
                            office, office_pic_inner, office_pic_outter, toilet
                        ) VALUES (
                            :name, :college, :email, :pass, 'admin', 'active',
                            :phone_number, :designation, :last_qualification,
                            :passport_pic, :qualification, :qualification_doc,
                            :aadhaar_number, :aadhaar_card,
                            :pan_card_number, :pan_card_doc,
                            :work_exp, :signature, :permanent_address,
                            :personal_state, :personal_pin,
                            :faculty, :department_it_computer, :department_fire_safety,
                            :institute_name, :institute_address, :institute_state,
                            :district, :city, :institute_pin, :institute_phone, :website,
                            :registered_no, :registration_type, :registration_name,
                            :registration_no, :registration_doc,
                            :total_area, :geo_location, :total_pc, :total_staffs,
                            :practical_labs, :lab_picture, :theory_rooms, :theory_room_pic,
                            :office, :office_pic_inner, :office_pic_outter, :toilet
                        )
                    ");

                    $insert->execute([
                        ':name'                    => $name,
                        ':college'                 => $college_name,
                        ':email'                   => $email,
                        ':pass'                    => $hash,
                        ':phone_number'            => $phone_number,
                        ':designation'             => $designation,
                        ':last_qualification'      => $last_qualification,
                        ':passport_pic'            => $passport_pic,
                        ':qualification'           => $qualification,
                        ':qualification_doc'      => $qualification_doc,
                        ':aadhaar_number'          => $aadhaar_number,
                        ':aadhaar_card'            => $aadhaar_card,
                        ':pan_card_number'         => $pan_card_number,
                        ':pan_card_doc'            => $pan_card_doc,
                        ':work_exp'               => $work_exp,
                        ':signature'              => $signature,
                        ':permanent_address'      => $permanent_address,
                        ':personal_state'         => $personal_state,
                        ':personal_pin'           => $personal_pin,
                        ':faculty'                => $faculty,
                        ':department_it_computer'=> $department_it_computer,
                        ':department_fire_safety'=> $department_fire_safety,
                        ':institute_name'         => $institute_name,
                        ':institute_address'      => $institute_address,
                        ':institute_state'        => $institute_state,
                        ':district'               => $district,
                        ':city'                   => $city,
                        ':institute_pin'         => $institute_pin,
                        ':institute_phone'        => $institute_phone,
                        ':website'                => $website,
                        ':registered_no'          => $registered_no,
                        ':registration_type'     => $registration_type,
                        ':registration_name'     => $registration_name,
                        ':registration_no'       => $registration_no,
                        ':registration_doc'     => $registration_doc,
                        ':total_area'             => $total_area,
                        ':geo_location'           => $geo_location,
                        ':total_pc'              => $total_pc,
                        ':total_staffs'          => $total_staffs,
                        ':practical_labs'        => $practical_labs,
                        ':lab_picture'           => $lab_picture,
                        ':theory_rooms'          => $theory_rooms,
                        ':theory_room_pic'      => $theory_room_pic,
                        ':office'                => $office,
                        ':office_pic_inner'     => $office_pic_inner,
                        ':office_pic_outter'    => $office_pic_outter,
                        ':toilet'                => $toilet,
                    ]);

                    $new_id = (int)$db->lastInsertId();
                } catch (Throwable $uploadError) {
                    setFlashMessage('error', $uploadError->getMessage());
                    echo "<script>window.location='admin_management.php';</script>";
                    exit;
                }

                try {
    $mail = new PHPMailer(true);

    // SMTP (Hostinger)
    $mail->isSMTP();
    $mail->Host       = 'smtp.hostinger.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'info@nbteind.in';
    $mail->Password   = 'Mail@nbte123'; // 🔴 replace
    $mail->SMTPSecure = 'tls';
    $mail->Port       = 587;

    // ===== ADMIN EMAIL =====
    $mail->setFrom('info@nbteind.in', 'NBTE Panel');
    $mail->addAddress($email, $name);

    $mail->isHTML(true);
    $mail->Subject = 'Admin Account Created';

   $mail->Body = "
<div style='font-family:Arial, sans-serif; background:#f4f6f9; padding:20px;'>

    <div style='max-width:600px; margin:auto; background:#ffffff; padding:25px; border-radius:8px; box-shadow:0 2px 8px rgba(0,0,0,0.1);'>

        <h2 style='color:#2c3e50; text-align:center;'>Welcome to NBTE Admin Panel </h2>

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
            If you share your login details, NBTE Authority will not be responsible for any misuse.
        </div>

        <br>

        <p>Regards,<br><strong>NBTE Authority</strong></p>

    </div>

</div>
";

    $mail->send();

    // ===== OWNER EMAIL =====
    $mail->clearAddresses();

    $mail->addAddress('info@nbteind.in');

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

// ── DELETE ADMIN ─────────────────────────────────────────
if ($action === 'delete_admin') {

    $adminId = (int)($_POST['admin_id'] ?? 0);

    if ($adminId > 0) {

        // Prevent self delete
        if ($adminId == getCurrentUserId()) {

            echo "<script>alert('You cannot delete yourself');window.location='admin_management.php';</script>";
            exit;
        }

        // Delete related records first
        $db->prepare("DELETE FROM admin_programs WHERE admin_id = :id")
           ->execute([':id' => $adminId]);

        $db->prepare("DELETE FROM admin_certificates WHERE admin_id = :id")
           ->execute([':id' => $adminId]);

        $db->prepare("DELETE FROM students WHERE admin_id = :id")
           ->execute([':id' => $adminId]);

        // Finally delete admin
        $db->prepare("DELETE FROM admins WHERE id = :id")
           ->execute([':id' => $adminId]);

        echo "<script>alert('Admin deleted successfully');window.location='admin_management.php';</script>";
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
                                   title="Generate NBTE Authorization Certificate for this center">
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

<form method="POST" class="d-inline"
      onsubmit="return confirm('Are you sure you want to delete this admin?');">

    <?php echo csrfField(); ?>

    <input type="hidden" name="action" value="delete_admin">

    <input type="hidden" name="admin_id"
           value="<?php echo $admin['id']; ?>">

    <button type="submit" class="btn btn-sm btn-danger">

        <i class="bi bi-trash"></i> Delete

    </button>

</form>


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
            <form method="POST" enctype="multipart/form-data">
                <?php echo csrfField(); ?>
                <input type="hidden" name="action" value="create">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="bi bi-person-plus me-2"></i>Create Admin</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">

                        <!-- Existing fields -->
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Admin Name <span class="text-danger">*</span></label>
                            <input class="form-control" name="name" placeholder="Full name" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold">College / Center Name <span class="text-danger">*</span></label>
                            <input class="form-control" name="college_name"
                                   placeholder="e.g. SunNBTE Institute of Technology" required>
                            <div class="form-text text-success">
                                <i class="bi bi-patch-check"></i>
                                This name is printed on the NBTE Authorization Certificate.
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

                        <!-- Personal Information -->
                        <div class="col-12">
                            <hr>
                            <h6 class="fw-bold text-primary mb-2">
                                <i class="bi bi-person-vcard me-2"></i>Personal Information
                            </h6>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold">Phone Number</label>
                            <input class="form-control" name="phone_number">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold">Designation</label>
                            <input class="form-control" name="designation">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold">Last Qualification</label>
                            <input class="form-control" name="last_qualification">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold">Qualification</label>
                            <input class="form-control" name="qualification">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold">Passport Pic</label>
                            <input class="form-control" type="file" name="passport_pic"
                                   accept=".jpg,.jpeg,.png,.webp">
                            <small class="text-muted">JPG, PNG, WEBP — Max 5MB</small>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold">Qualification Doc</label>
                            <input class="form-control" type="file" name="qualification_doc"
                                   accept=".jpg,.jpeg,.png,.webp,.pdf">
                            <small class="text-muted">JPG, PNG, WEBP, PDF — Max 5MB</small>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold">Aadhaar Card Number</label>
                            <input class="form-control" name="aadhaar_number">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold">Aadhar Card</label>
                            <input class="form-control" type="file" name="aadhaar_card"
                                   accept=".jpg,.jpeg,.png,.webp,.pdf">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold">PAN Card Number</label>
                            <input class="form-control" name="pan_card_number">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold">PAN Card Doc</label>
                            <input class="form-control" type="file" name="pan_card_doc"
                                   accept=".jpg,.jpeg,.png,.webp,.pdf">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold">Work Experience</label>
                            <input class="form-control" name="work_exp">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold">Signature</label>
                            <input class="form-control" type="file" name="signature"
                                   accept=".jpg,.jpeg,.png,.webp">
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-bold">Permanent Address</label>
                            <textarea class="form-control" name="permanent_address" rows="2"></textarea>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold">State</label>
                            <input class="form-control" name="personal_state">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold">PIN</label>
                            <input class="form-control" name="personal_pin">
                        </div>

                        <!-- Faculty / Departments -->
                        <div class="col-12">
                            <hr>
                            <h6 class="fw-bold text-primary mb-2">
                                <i class="bi bi-people me-2"></i>Faculty & Departments
                            </h6>
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-bold">Faculty</label>
                            <textarea class="form-control" name="faculty" rows="2"
                                      placeholder="Faculty details"></textarea>
                        </div>

                        <div class="col-md-6">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox"
                                       name="department_it_computer" value="1"
                                       id="department_it_computer">
                                <label class="form-check-label fw-semibold"
                                       for="department_it_computer">
                                    Department of IT Computer
                                </label>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox"
                                       name="department_fire_safety" value="1"
                                       id="department_fire_safety">
                                <label class="form-check-label fw-semibold"
                                       for="department_fire_safety">
                                    Department of Fire Safety
                                </label>
                            </div>
                        </div>

                        <!-- Institute Details -->
                        <div class="col-12">
                            <hr>
                            <h6 class="fw-bold text-primary mb-2">
                                <i class="bi bi-building me-2"></i>Institute Details
                            </h6>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold">Institute Name</label>
                            <input class="form-control" name="institute_name">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold">Phone</label>
                            <input class="form-control" name="institute_phone">
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-bold">Address</label>
                            <textarea class="form-control" name="institute_address" rows="2"></textarea>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-bold">State</label>
                            <input class="form-control" name="institute_state">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-bold">District</label>
                            <input class="form-control" name="district">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-bold">City</label>
                            <input class="form-control" name="city">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-bold">Pin Code</label>
                            <input class="form-control" name="institute_pin">
                        </div>

                        <div class="col-md-8">
                            <label class="form-label fw-bold">Website</label>
                            <input class="form-control" name="website" type="url"
                                   placeholder="https://">
                        </div>

                        <!-- Registration -->
                        <div class="col-12">
                            <hr>
                            <h6 class="fw-bold text-primary mb-2">
                                <i class="bi bi-file-earmark-text me-2"></i>Registration Details
                            </h6>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold">Registered No</label>
                            <input class="form-control" name="registered_no">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold">Type</label>
                            <input class="form-control" name="registration_type">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold">Registration Name</label>
                            <input class="form-control" name="registration_name">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold">Registration No</label>
                            <input class="form-control" name="registration_no">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold">Registration Doc</label>
                            <input class="form-control" type="file" name="registration_doc"
                                   accept=".jpg,.jpeg,.png,.webp,.pdf">
                        </div>

                        <!-- Infrastructure -->
                        <div class="col-12">
                            <hr>
                            <h6 class="fw-bold text-primary mb-2">
                                <i class="bi bi-building-gear me-2"></i>Infrastructure Details
                            </h6>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-bold">Total Area</label>
                            <input class="form-control" name="total_area">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-bold">Geo. Location</label>
                            <input class="form-control" name="geo_location">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-bold">Total PC</label>
                            <input class="form-control" type="number" name="total_pc" min="0">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-bold">Total Staffs</label>
                            <input class="form-control" type="number" name="total_staffs" min="0">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-bold">Total Number of Practical Labs</label>
                            <input class="form-control" type="number" name="practical_labs" min="0">
                        </div>

                        <div class="col-md-8">
                            <label class="form-label fw-bold">Lab Picture</label>
                            <input class="form-control" type="file" name="lab_picture"
                                   accept=".jpg,.jpeg,.png,.webp">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-bold">Total Number of Theory Rooms</label>
                            <input class="form-control" type="number" name="theory_rooms" min="0">
                        </div>

                        <div class="col-md-8">
                            <label class="form-label fw-bold">Theory Room Pic</label>
                            <input class="form-control" type="file" name="theory_room_pic"
                                   accept=".jpg,.jpeg,.png,.webp">
                        </div>

                        <div class="col-md-4">
                            <div class="form-check mt-4">
                                <input class="form-check-input" type="checkbox"
                                       name="office" value="1" id="office">
                                <label class="form-check-label fw-bold" for="office">
                                    Office?
                                </label>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold">Office Pic Inner</label>
                            <input class="form-control" type="file" name="office_pic_inner"
                                   accept=".jpg,.jpeg,.png,.webp">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold">Office Pic Outter</label>
                            <input class="form-control" type="file" name="office_pic_outter"
                                   accept=".jpg,.jpeg,.png,.webp">
                        </div>

                        <div class="col-md-4">
                            <div class="form-check mt-3">
                                <input class="form-check-input" type="checkbox"
                                       name="toilet" value="1" id="toilet">
                                <label class="form-check-label fw-bold" for="toilet">
                                    Toilet
                                </label>
                            </div>
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
                            <i class="bi bi-patch-check"></i> Printed on the NBTE Authorization Certificate.
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