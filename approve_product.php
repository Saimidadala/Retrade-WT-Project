<?php
require_once 'config.php';
requireRole('admin');

$page_title = 'Approve Products';

// Handle approve/reject actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $product_id = intval($_POST['product_id'] ?? 0);
    $action = $_POST['action'] ?? '';
    if ($product_id && in_array($action, ['approve', 'reject'])) {
        try {
            $new_status = $action === 'approve' ? 'approved' : 'rejected';
            $stmt = $pdo->prepare("UPDATE products SET status = ? WHERE id = ?");
            $stmt->execute([$new_status, $product_id]);
            header('Location: approve_product.php?success=1');
            exit();
        } catch (PDOException $e) {
            header('Location: approve_product.php?error=1');
            exit();
        }
    }
}

// Filtering
$filter = $_GET['filter'] ?? 'pending';
$allowed = ['pending','approved','rejected','all'];
if (!in_array($filter, $allowed)) { $filter = 'pending'; }

$query = "SELECT p.*, u.name AS seller_name, u.email AS seller_email FROM products p JOIN users u ON p.seller_id=u.id";
$params = [];
if ($filter !== 'all') {
    $query .= " WHERE p.status = ?";
    $params[] = $filter;
}
$query .= " ORDER BY p.created_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$products = $stmt->fetchAll();

include 'includes/header.php';
?>

<div class="container my-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2><i class="fas fa-clipboard-check text-primary"></i> Product Approvals</h2>
        <div>
            <a href="admin_panel.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-left"></i> Back</a>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <form class="row g-2">
                <input type="hidden" name="page" value="approve_product">
                <div class="col-auto">
                    <label class="form-label">Filter</label>
                </div>
                <div class="col-auto">
                    <select name="filter" class="form-select" onchange="this.form.submit()">
                        <option value="pending" <?php echo $filter==='pending'?'selected':''; ?>>Pending</option>
                        <option value="approved" <?php echo $filter==='approved'?'selected':''; ?>>Approved</option>
                        <option value="rejected" <?php echo $filter==='rejected'?'selected':''; ?>>Rejected</option>
                        <option value="all" <?php echo $filter==='all'?'selected':''; ?>>All</option>
                    </select>
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
                            <th>Product</th>
                            <th>Seller</th>
                            <th>Price</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($products)): ?>
                            <tr><td colspan="6" class="text-center py-4 text-muted">No products found</td></tr>
                        <?php else: ?>
                            <?php foreach ($products as $p): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <?php if ($p['image'] && file_exists('assets/img/' . $p['image'])): ?>
                                                <img src="assets/img/<?php echo htmlspecialchars($p['image']); ?>" class="rounded me-2" style="width:40px;height:40px;object-fit:cover;">
                                            <?php else: ?>
                                                <div class="bg-light rounded me-2 d-flex align-items-center justify-content-center" style="width:40px;height:40px;">
                                                    <i class="fas fa-image text-muted"></i>
                                                </div>
                                            <?php endif; ?>
                                            <div>
                                                <div class="fw-bold"><?php echo htmlspecialchars($p['title']); ?></div>
                                                <small class="text-muted">Category: <?php echo htmlspecialchars($p['category'] ?? 'N/A'); ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="fw-bold"><?php echo htmlspecialchars($p['seller_name']); ?></div>
                                        <small class="text-muted"><?php echo htmlspecialchars($p['seller_email']); ?></small>
                                    </td>
                                    <td><?php echo formatPrice($p['price']); ?></td>
                                    <td><span class="badge bg-<?php echo $p['status']==='approved'?'success':($p['status']==='pending'?'warning':'danger'); ?>"><?php echo ucfirst($p['status']); ?></span></td>
                                    <td><?php echo date('M j, Y', strtotime($p['created_at'])); ?></td>
                                    <td class="text-end">
                                        <div class="btn-group">
                                            <a class="btn btn-sm btn-outline-primary" href="product_details.php?id=<?php echo $p['id']; ?>" target="_blank"><i class="fas fa-eye"></i></a>
                                            <form method="POST" onsubmit="return confirm('Approve this product?');">
                                                <input type="hidden" name="product_id" value="<?php echo $p['id']; ?>">
                                                <input type="hidden" name="action" value="approve">
                                                <button class="btn btn-sm btn-success" <?php echo $p['status']==='approved'?'disabled':''; ?>>
                                                    <i class="fas fa-check"></i>
                                                </button>
                                            </form>
                                            <form method="POST" onsubmit="return confirm('Reject this product?');">
                                                <input type="hidden" name="product_id" value="<?php echo $p['id']; ?>">
                                                <input type="hidden" name="action" value="reject">
                                                <button class="btn btn-sm btn-danger" <?php echo $p['status']==='rejected'?'disabled':''; ?>>
                                                    <i class="fas fa-times"></i>
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
