<?php
require_once 'config.php';
requireRole('admin');

$page_title = 'Manage Users';

// Handle role updates safely (balance adjustments removed)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_role') {
        $user_id = intval($_POST['user_id'] ?? 0);
        $role = $_POST['role'] ?? '';
        if ($user_id && in_array($role, ['buyer','seller','admin'])) {
            try {
                // Prevent demoting yourself accidentally
                if ($user_id == $_SESSION['user_id']) {
                    header('Location: manage_users.php?error=cannot_change_own_role');
                    exit();
                }
                $stmt = $pdo->prepare("UPDATE users SET role=? WHERE id=?");
                $stmt->execute([$role, $user_id]);
                header('Location: manage_users.php?success=role_updated');
                exit();
            } catch (PDOException $e) {
                header('Location: manage_users.php?error=update_failed');
                exit();
            }
        }
    }

}

// Search and filter
$search = trim($_GET['search'] ?? '');
$role_filter = $_GET['role'] ?? '';

$query = "SELECT id, name, email, role, phone, created_at FROM users WHERE 1=1";
$params = [];

if ($search !== '') {
    $query .= " AND (name LIKE ? OR email LIKE ? OR phone LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (in_array($role_filter, ['buyer','seller','admin'])) {
    $query .= " AND role = ?";
    $params[] = $role_filter;
}

$query .= " ORDER BY created_at DESC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$users = $stmt->fetchAll();

include 'includes/header.php';
?>

<div class="container my-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2><i class="fas fa-users-cog text-primary"></i> Manage Users</h2>
        <a class="btn btn-outline-secondary" href="admin_panel.php"><i class="fas fa-arrow-left"></i> Back</a>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <form class="row g-2">
                <div class="col-md-4">
                    <input type="text" class="form-control" name="search" placeholder="Search by name, email or phone" value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="col-md-3">
                    <select class="form-select" name="role">
                        <option value="">All Roles</option>
                        <option value="buyer" <?php echo $role_filter==='buyer'?'selected':''; ?>>Buyer</option>
                        <option value="seller" <?php echo $role_filter==='seller'?'selected':''; ?>>Seller</option>
                        <option value="admin" <?php echo $role_filter==='admin'?'selected':''; ?>>Admin</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button class="btn btn-primary w-100" type="submit"><i class="fas fa-search"></i> Filter</button>
                </div>
                <div class="col-md-3 text-md-end">
                    <span class="badge bg-secondary"><?php echo count($users); ?> users</span>
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
                            <th>Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Role</th>
                            <th>Joined</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $u): ?>
                        <tr>
                            <td class="fw-semibold"><?php echo htmlspecialchars($u['name']); ?></td>
                            <td><?php echo htmlspecialchars($u['email']); ?></td>
                            <td><?php echo htmlspecialchars($u['phone']); ?></td>
                            <td>
                                <span class="badge bg-<?php echo $u['role']==='admin'?'dark':($u['role']==='seller'?'success':'primary'); ?>"><?php echo ucfirst($u['role']); ?></span>
                            </td>
                            <td><?php echo date('M j, Y', strtotime($u['created_at'])); ?></td>
                            <td class="text-end">
                                <div class="btn-group">
                                    <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#roleModal<?php echo $u['id']; ?>">
                                        <i class="fas fa-user-shield"></i>
                                    </button>
                                </div>

                                <!-- Role Modal -->
                                <div class="modal fade" id="roleModal<?php echo $u['id']; ?>" tabindex="-1" aria-hidden="true">
                                  <div class="modal-dialog">
                                    <div class="modal-content">
                                      <div class="modal-header">
                                        <h5 class="modal-title"><i class="fas fa-user-shield"></i> Update Role</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                      </div>
                                      <form method="POST">
                                        <div class="modal-body">
                                          <input type="hidden" name="action" value="update_role">
                                          <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                          <div class="mb-3">
                                            <label class="form-label">Role</label>
                                            <select class="form-select" name="role" required>
                                              <option value="buyer" <?php echo $u['role']==='buyer'?'selected':''; ?>>Buyer</option>
                                              <option value="seller" <?php echo $u['role']==='seller'?'selected':''; ?>>Seller</option>
                                              <option value="admin" <?php echo $u['role']==='admin'?'selected':''; ?>>Admin</option>
                                            </select>
                                          </div>
                                        </div>
                                        <div class="modal-footer">
                                          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                          <button type="submit" class="btn btn-primary">Update</button>
                                        </div>
                                      </form>
                                    </div>
                                  </div>
                                </div>

                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
