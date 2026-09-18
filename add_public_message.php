<?php
/**
 * RISE - Public Message
 */

$pageTitle = 'Public Messages';

require_once 'includes/header.php';
require_once 'includes/sidebar.php';

requireLogin();

if (!isSuperAdmin()) {
    header('Location: dashboard.php');
    exit;
}

$db = getDB();

/* ================= EDIT FETCH ================= */

$editData = null;

if (isset($_GET['edit'])) {

    $edit_id = (int) $_GET['edit'];

    $stmt = $db->prepare("
        SELECT * FROM public_messages
        WHERE id = :id
    ");

    $stmt->execute([
        ':id' => $edit_id
    ]);

    $editData = $stmt->fetch();
}

/* ================= ADD / UPDATE MESSAGE ================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    requireCSRF();

    $message = trim($_POST['message'] ?? '');
    $edit_id = (int) ($_POST['edit_id'] ?? 0);

    if (empty($message)) {

        setFlashMessage('error', 'Message cannot be empty.');

        header('Location: add_public_message.php');
        exit;
    }

    /* ================= UPDATE ================= */

    if ($edit_id > 0) {

        $stmt = $db->prepare("
            UPDATE public_messages
            SET message = :msg
            WHERE id = :id
        ");

        $stmt->execute([
            ':msg' => $message,
            ':id'  => $edit_id
        ]);

        echo "
        <script>
            alert('Public Message Updated Successfully');
            window.location='add_public_message.php';
        </script>
        ";

        exit;
    }

    /* ================= INSERT ================= */

    $stmt = $db->prepare("
        INSERT INTO public_messages (message)
        VALUES (:msg)
    ");

    $stmt->execute([
        ':msg' => $message
    ]);

    echo "
    <script>
        alert('Public Message Added Successfully');
        window.location='add_public_message.php';
    </script>
    ";

    exit;
}

/* ================= DELETE MESSAGE ================= */

if (isset($_GET['delete'])) {

    $id = (int) $_GET['delete'];

    $stmt = $db->prepare("
        DELETE FROM public_messages
        WHERE id = :id
    ");

    $stmt->execute([
        ':id' => $id
    ]);

    echo "
    <script>
        alert('Message Deleted Successfully');
        window.location='add_public_message.php';
    </script>
    ";

    exit;
}

/* ================= FETCH MESSAGES ================= */

$stmt = $db->query("
    SELECT * FROM public_messages
    ORDER BY id DESC
");

$messages = $stmt->fetchAll();
?>

<!-- ================= UI ================= -->

<div class="card mb-4">

    <div class="card-header">
        <h6>
            <i class="fas fa-bullhorn me-2"></i>

            <?php if ($editData): ?>
                Edit Public Message
            <?php else: ?>
                Add Public Message
            <?php endif; ?>

        </h6>
    </div>

    <div class="card-body">

        <form method="POST">

            <?php echo csrfField(); ?>

            <input
                type="hidden"
                name="edit_id"
                value="<?php echo $editData['id'] ?? ''; ?>"
            >

            <div class="mb-3">

                <label class="form-label">
                    Message
                </label>

                <textarea
                    name="message"
                    class="form-control"
                    required
                    placeholder="Enter public message..."
                    rows="4"
                ><?php echo htmlspecialchars($editData['message'] ?? ''); ?></textarea>

            </div>

            <button type="submit" class="btn btn-primary">

                <i class="fas fa-save me-2"></i>

                <?php if ($editData): ?>
                    Update Message
                <?php else: ?>
                    Add Public Message
                <?php endif; ?>

            </button>

            <?php if ($editData): ?>

                <a href="add_public_message.php" class="btn btn-secondary">
                    Cancel
                </a>

            <?php endif; ?>

        </form>

    </div>

</div>

<!-- ================= SHOW ALL MESSAGES ================= -->

<div class="card">

    <div class="card-header">
        <h6>
            <i class="fas fa-list me-2"></i>
            All Public Messages
        </h6>
    </div>

    <div class="card-body">

        <?php if (empty($messages)): ?>

            <p class="text-muted">
                No public messages found.
            </p>

        <?php else: ?>

        <div class="table-responsive">

            <table class="table">

                <thead>

                    <tr>
                        <th>#</th>
                        <th>Message</th>
                        <th>Date</th>
                        <th width="180">Action</th>
                    </tr>

                </thead>

                <tbody>

                    <?php foreach ($messages as $msg): ?>

                    <tr>

                        <td>
                            <?php echo $msg['id']; ?>
                        </td>

                        <td>
                            <?php echo htmlspecialchars($msg['message']); ?>
                        </td>

                        <td>
                            <?php echo date('d M Y', strtotime($msg['created_at'])); ?>
                        </td>

                        <td>

                            <a
                                href="?edit=<?php echo $msg['id']; ?>"
                                class="btn btn-primary btn-sm"
                            >

                                Edit

                            </a>

                            <a
                                href="?delete=<?php echo $msg['id']; ?>"
                                class="btn btn-danger btn-sm"
                                onclick="return confirm('Delete this public message?')"
                            >

                                Delete

                            </a>

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