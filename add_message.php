<?php
/**
 * RISE - Add Message
 */

$pageTitle = 'Manage Messages';
require_once 'includes/header.php';
require_once 'includes/sidebar.php';
requireLogin();

if (!isSuperAdmin()) {
    header('Location: dashboard.php');
    exit;
}

$db = getDB();

// ================= ADD MESSAGE =================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCSRF();

    $message = trim($_POST['message'] ?? '');

    if (empty($message)) {
        setFlashMessage('error', 'Message cannot be empty.');
        header('Location: add_message.php');
        exit;
    }

    $stmt = $db->prepare("INSERT INTO messages (message) VALUES (:msg)");
    $stmt->execute([':msg' => $message]);

    echo "<script>alert('Message added successfully');window.location='add_message.php';</script>";
    exit;
}

// ================= DELETE MESSAGE =================
if (isset($_GET['delete'])) {
    $id = (int) $_GET['delete'];

    $stmt = $db->prepare("DELETE FROM messages WHERE id = :id");
    $stmt->execute([':id' => $id]);

    echo "<script>alert('Message deleted successfully');window.location='add_message.php';</script>";
    exit;
}

// ================= FETCH MESSAGES =================
$stmt = $db->query("SELECT * FROM messages ORDER BY id DESC");
$messages = $stmt->fetchAll();
?>

<!-- ================= UI ================= -->

<div class="card mb-4">
    <div class="card-header">
        <h6><i class="fas fa-bullhorn me-2"></i>Add New Message</h6>
    </div>
    <div class="card-body">

        <form method="POST">
            <?php echo csrfField(); ?>

            <div class="mb-3">
                <label class="form-label">Message</label>
                <textarea name="message" class="form-control" required placeholder="Enter message..."></textarea>
            </div>

            <button type="submit" class="btn btn-primary">
                <i class="fas fa-plus me-2"></i>Add Message
            </button>
        </form>

    </div>
</div>

<!-- ================= SHOW ALL MESSAGES ================= -->

<div class="card">
    <div class="card-header">
        <h6><i class="fas fa-list me-2"></i>All Messages</h6>
    </div>

    <div class="card-body">

        <?php if (empty($messages)): ?>
            <p class="text-muted">No messages found.</p>
        <?php else: ?>

        <table class="table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Message</th>
                    <th>Date</th>
                    <th>Action</th>
                </tr>
            </thead>

            <tbody>
                <?php foreach ($messages as $msg): ?>
                <tr>
                    <td><?php echo $msg['id']; ?></td>
                    <td><?php echo htmlspecialchars($msg['message']); ?></td>
                    <td><?php echo date('d M Y', strtotime($msg['created_at'])); ?></td>
                    <td>
                        <a href="?delete=<?php echo $msg['id']; ?>" 
                           class="btn btn-danger btn-sm"
                           onclick="return confirm('Delete this message?')">
                           Delete
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php endif; ?>

    </div>
</div>

<?php require_once 'includes/footer.php'; ?>