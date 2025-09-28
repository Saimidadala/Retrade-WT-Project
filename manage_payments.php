<?php
require_once 'config.php';
requireRole('admin');

$page_title = 'Manage Payments';

// Handle actions: approve, release, refund
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tx_id = intval($_POST['tx_id'] ?? 0);
    $action = $_POST['action'] ?? '';

    if ($tx_id && in_array($action, ['approve','release','refund'])) {
        try {
            // Load transaction (no wallet balances)
            $stmt = $pdo->prepare("SELECT t.* FROM transactions t WHERE t.id = ?");
            $stmt->execute([$tx_id]);
            $tx = $stmt->fetch();
            if (!$tx) throw new Exception('Transaction not found');

            // State machine rules
            // approve: pending -> approved (no money movement)
            // release: (pending|approved|disputed) -> released (move seller_amount from admin to seller)
            // refund: (pending|approved|disputed) -> refunded (move amount from admin to buyer)

            if ($action === 'approve') {
                if ($tx['status'] !== 'pending') throw new Exception('Only pending can be approved');
                $stmt = $pdo->prepare("UPDATE transactions SET status = 'approved', notes = CONCAT(notes, '\n\nAdmin approved on ', NOW()) WHERE id = ?");
                $stmt->execute([$tx_id]);
            }

            if ($action === 'release') {
                if (!in_array($tx['status'], ['pending','approved']) && $tx['delivery_status'] !== 'disputed') {
                    throw new Exception('Cannot release in current state');
                }
                // Update transaction only (no wallet movements)
                $stmt = $pdo->prepare("UPDATE transactions SET status = 'released', delivery_status = IF(delivery_status='pending','confirmed',delivery_status), notes = CONCAT(notes, '\n\nAdmin released payment on ', NOW()) WHERE id = ?");
                $stmt->execute([$tx_id]);
            }

            if ($action === 'refund') {
                if (!in_array($tx['status'], ['pending','approved']) && $tx['delivery_status'] !== 'disputed') {
                    throw new Exception('Cannot refund in current state');
                }
                // Mark as refunded (no wallet movements)
                $stmt = $pdo->prepare("UPDATE transactions SET status = 'refunded', notes = CONCAT(notes, '\n\nAdmin refunded on ', NOW()) WHERE id = ?");
                $stmt->execute([$tx_id]);
            }

            header('Location: manage_payments.php?success=1');
            exit();
        } catch (Exception $ex) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            header('Location: manage_payments.php?error=' . urlencode($ex->getMessage()));
            exit();
        }
    }
}

// Filters
$status = $_GET["status"] ?? "pending"; // pending by default
$delivery = $_GET["delivery"] ?? ""; // optional: disputed
$allowed_status = ['pending','approved','released','refunded','rejected','all'];
if (!in_array($status, $allowed_status)) { $status = 'pending'; }

$query = "SELECT t.*, p.title, u1.name AS buyer_name, u2.name AS seller_name
          FROM transactions t
          JOIN products p ON t.product_id = p.id
          JOIN users u1 ON t.buyer_id = u1.id
          JOIN users u2 ON t.seller_id = u2.id
          WHERE 1=1";
$params = [];

if ($status !== 'all') {
    $query .= " AND t.status = ?";
    $params[] = $status;
}
if ($delivery === 'disputed') {
    $query .= " AND t.delivery_status = 'disputed'";
}

$query .= " ORDER BY t.created_at DESC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$transactions = $stmt->fetchAll();

include 'includes/header.php';
?>

<div class="container my-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2><i class="fas fa-cash-register text-info"></i> Manage Escrow Payments</h2>
        <a href="admin_panel.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-left"></i> Back</a>
    </div>

    <?php if (isset($_GET['error'])): ?>
        <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($_GET['error']); ?></div>
    <?php elseif (isset($_GET['success'])): ?>
        <div class="alert alert-success"><i class="fas fa-check-circle"></i> Action completed successfully.</div>
    <?php endif; ?>

    <div class="card mb-3">
        <div class="card-body">
            <form class="row g-2">
                <div class="col-md-3">
                    <label class="form-label">Transaction Status</label>
                    <select class="form-select" name="status" onchange="this.form.submit()">
                        <option value="pending" <?php echo $status==='pending'?'selected':''; ?>>Pending</option>
                        <option value="approved" <?php echo $status==='approved'?'selected':''; ?>>Approved</option>
                        <option value="released" <?php echo $status==='released'?'selected':''; ?>>Released</option>
                        <option value="refunded" <?php echo $status==='refunded'?'selected':''; ?>>Refunded</option>
                        <option value="rejected" <?php echo $status==='rejected'?'selected':''; ?>>Rejected</option>
                        <option value="all" <?php echo $status==='all'?'selected':''; ?>>All</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Delivery Status</label>
                    <select class="form-select" name="delivery" onchange="this.form.submit()">
                        <option value="" <?php echo $delivery===''?'selected':''; ?>>Any</option>
                        <option value="disputed" <?php echo $delivery==='disputed'?'selected':''; ?>>Disputed Only</option>
                    </select>
                </div>
                <div class="col-md-6 d-flex align-items-end justify-content-end">
                    <span class="badge bg-secondary"><?php echo count($transactions); ?> transactions</span>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Product</th>
                            <th>Buyer</th>
                            <th>Seller</th>
                            <th>Amount</th>
                            <th>Seller 90%</th>
                            <th>Admin 10%</th>
                            <th>Status</th>
                            <th>Delivery</th>
                            <th>Date</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($transactions)): ?>
                            <tr><td colspan="11" class="text-center py-4 text-muted">No transactions found</td></tr>
                        <?php else: ?>
                            <?php foreach ($transactions as $t): ?>
                                <tr class="transaction-<?php echo htmlspecialchars($t['status']); ?>">
                                    <td>#<?php echo $t['id']; ?></td>
                                    <td><?php echo htmlspecialchars($t['title']); ?></td>
                                    <td><?php echo htmlspecialchars($t['buyer_name']); ?></td>
                                    <td><?php echo htmlspecialchars($t['seller_name']); ?></td>
                                    <td class="fw-bold text-success"><?php echo formatPrice($t['amount']); ?></td>
                                    <td><?php echo formatPrice($t['seller_amount']); ?></td>
                                    <td><?php echo formatPrice($t['admin_commission']); ?></td>
                                    <td><span class="badge bg-<?php echo $t['status']==='released'?'info':($t['status']==='approved'?'success':($t['status']==='pending'?'warning':($t['status']==='refunded'?'secondary':'danger'))); ?>"><?php echo ucfirst($t['status']); ?></span></td>
                                    <td><span class="badge bg-<?php echo $t['delivery_status']==='disputed'?'danger':($t['delivery_status']==='confirmed'?'success':'warning'); ?>"><?php echo ucfirst($t['delivery_status']); ?></span></td>
                                    <td><?php echo date('M j, Y', strtotime($t['created_at'])); ?></td>
                                    <td class="text-end">
                                        <div class="btn-group">
                                            <form method="POST" class="me-1" onsubmit="return confirm('Approve this payment?');">
                                                <input type="hidden" name="tx_id" value="<?php echo $t['id']; ?>">
                                                <input type="hidden" name="action" value="approve">
                                                <button class="btn btn-sm btn-success" <?php echo $t['status']!=='pending'?'disabled':''; ?>>
                                                    <i class="fas fa-check"></i>
                                                </button>
                                            </form>
                                            <form method="POST" class="me-1" onsubmit="return confirm('Release funds to seller?');">
                                                <input type="hidden" name="tx_id" value="<?php echo $t['id']; ?>">
                                                <input type="hidden" name="action" value="release">
                                                <button class="btn btn-sm btn-info" <?php echo in_array($t['status'],['released','refunded'])?'disabled':''; ?>>
                                                    <i class="fas fa-unlock"></i>
                                                </button>
                                            </form>
                                            <form method="POST" onsubmit="return confirm('Refund buyer full amount?');">
                                                <input type="hidden" name="tx_id" value="<?php echo $t['id']; ?>">
                                                <input type="hidden" name="action" value="refund">
                                                <button class="btn btn-sm btn-secondary" <?php echo in_array($t['status'],['released','refunded'])?'disabled':''; ?>>
                                                    <i class="fas fa-undo"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
