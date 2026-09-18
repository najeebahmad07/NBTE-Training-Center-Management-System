<?php
/**
 * RISE - Wallet (Clean Static Version)
 */

$pageTitle = 'Wallet';
require_once 'includes/header.php';
require_once 'includes/sidebar.php';
requireLogin();

$db = getDB();
$userId = getCurrentUserId();

$balance = null;
$transactions = [];

if (isSuperAdmin()) {
    // Super Admin - All Transactions
    $stmt = $db->query("
        SELECT wt.*, a.name as admin_name 
        FROM wallet_transactions wt
        JOIN admins a ON wt.admin_id = a.id
        ORDER BY wt.created_at DESC
        LIMIT 100
    ");
    $transactions = $stmt->fetchAll();
} else {
    // Normal Admin Wallet Balance
    $balance = (float)getWalletBalance($userId);

    $stmt = $db->prepare("
        SELECT *
        FROM wallet_transactions
        WHERE admin_id = :admin_id
        ORDER BY created_at DESC
        LIMIT 100
    ");
    $stmt->execute([':admin_id' => $userId]);
    $transactions = $stmt->fetchAll();
}
?>

<?php if (!isSuperAdmin()): ?>
<!-- Wallet Balance Card -->
<div class="row mb-4">
    <div class="col-md-6">
        <div class="wallet-card">
            <div class="position-relative">
                <p class="mb-1 opacity-75">Available Balance</p>

                <div class="wallet-balance">
                    <span class="fw-bold">
                        ₹<?php echo number_format($balance, 2); ?>
                    </span>
                </div>

                <p class="mt-2 mb-0 opacity-75">
                    Approval Fee: ₹2000 per student | Computer Fee: ₹200
                </p>
            </div>
        </div>
    </div>

    <div class="col-md-6 d-flex align-items-center">
        <div class="card w-100">
            <div class="card-body text-center">
                <h5 class="mb-3">Recharge Wallet</h5>
                <p class="text-muted mb-3">
                    Minimum Recharge: ₹100
                </p>
                <a href="recharge.php" class="btn btn-primary btn-lg">
                    <i class="fas fa-plus me-2"></i>Add Money
                </a>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Transactions -->
<div class="card">
    <div class="card-header"> 
 
        <h6 class="mb-0">
            <i class="fas fa-history me-2"></i>
            <?php echo isSuperAdmin() ? 'All Transactions' : 'Transaction History'; ?>
        </h6>
    </div>

    <div class="card-body p-0">
        <?php if (empty($transactions)): ?>
            <div class="empty-state">
                <i class="fas fa-wallet"></i>
                <p>No transactions found</p>
            </div>
        <?php else: ?>

        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th>#</th>
                        <?php if (isSuperAdmin()): ?><th>Admin</th><?php endif; ?>
                        <th>Type</th>
                        <th>Amount</th>
                       
                        <th>Payment ID</th>
                        <th>Status</th>
                        <th>Date</th>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach ($transactions as $i => $t): ?>
                    <tr>
                        <td><?php echo $i + 1; ?></td>

                        <?php if (isSuperAdmin()): ?>
                            <td><?php echo sanitize($t['admin_name']); ?></td>
                        <?php endif; ?>

                        <!-- Type -->
                        <td>
                            <?php if ($t['type'] === 'credit'): ?>
                                <span class="badge bg-success">
                                    Credit
                                </span>
                            <?php else: ?>
                                <span class="badge bg-danger">
                                    Debit
                                </span>
                            <?php endif; ?>
                        </td>

                        <!-- Amount -->
                        <td class="fw-bold <?php echo $t['type'] === 'credit' ? 'text-success' : 'text-danger'; ?>">
                            <?php echo $t['type'] === 'credit' ? '+' : '-'; ?>
                            ₹<?php echo number_format((float)$t['amount'], 2); ?>
                        </td>

                       

                        <!-- Payment ID -->
                        <td>
                            <small>
                                <?php if (!empty($t['razorpay_payment_id'])): ?>
                                    <code><?php echo sanitize($t['razorpay_payment_id']); ?></code>
                                <?php else: ?>
                                    <span class="text-muted">N/A</span>
                                <?php endif; ?>
                            </small>
                        </td>

                        <!-- Status -->
                        <td>
                            <?php if ($t['status'] === 'success'): ?>
                                <span class="badge bg-success">Success</span>
                            <?php elseif ($t['status'] === 'pending'): ?>
                                <span class="badge bg-warning">Pending</span>
                            <?php else: ?>
                                <span class="badge bg-danger">Failed</span>
                            <?php endif; ?>
                        </td>

                        <!-- Date -->
                        <td>
                            <?php echo date('d M Y H:i', strtotime($t['created_at'])); ?>
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