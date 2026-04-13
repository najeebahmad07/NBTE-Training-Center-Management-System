<?php
/**
 * Change Password
 */

$pageTitle = "Change Password";

require_once 'includes/header.php';
require_once 'includes/sidebar.php';
requireLogin();

$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    requireCSRF();

    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    $userId = getCurrentUserId();

    $errors = [];

    if (empty($currentPassword)) {
        $errors[] = "Current password required";
    }

    if (strlen($newPassword) < 6) {
        $errors[] = "New password must be at least 6 characters";
    }

    if ($newPassword !== $confirmPassword) {
        $errors[] = "Passwords do not match";
    }

    if (empty($errors)) {

        $stmt = $db->prepare("SELECT password FROM admins WHERE id = :id");
        $stmt->execute([':id' => $userId]);

        $user = $stmt->fetch();

        if (!$user || !password_verify($currentPassword, $user['password'])) {

            $errors[] = "Current password incorrect";

        } else {

            $hash = password_hash($newPassword, PASSWORD_DEFAULT);

            $update = $db->prepare("UPDATE admins SET password = :password WHERE id = :id");

            $update->execute([
                ':password' => $hash,
                ':id' => $userId
            ]);

            setFlashMessage('success', 'Password changed successfully');

echo "<script>
alert('Password changed successfully');
</script>";
        }
    }

    if (!empty($errors)) {
        setFlashMessage('error', implode('<br>', $errors));
    }
}
?>



<div class="card">

<div class="card-header">
<h5><i class="fas fa-key me-2"></i>Change Own Password</h5>
</div>

<div class="card-body">

<form method="POST">

<?php echo csrfField(); ?>

<div class="mb-3">

<label class="form-label">Current Password</label>

<input type="password"
class="form-control"
name="current_password"
required>

</div>


<div class="mb-3">

<label class="form-label">New Password</label>

<input type="password"
class="form-control"
name="new_password"
required
minlength="6">

</div>


<div class="mb-3">

<label class="form-label">Confirm Password</label>

<input type="password"
class="form-control"
name="confirm_password"
required
minlength="6">

</div>


<button type="submit" class="btn btn-primary">

<i class="fas fa-save me-1"></i>
Update Password

</button>

</form>

</div>

</div>



<?php require_once 'includes/footer.php'; ?>