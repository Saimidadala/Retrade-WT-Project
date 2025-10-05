<?php
require_once 'config.php';
requireLogin();

$page_title = 'Dashboard';
$user_role = getUserRole();
$user_id = $_SESSION['user_id'];
// If admin, redirect to admin panel before any output
if ($user_role === 'admin') {
    header('Location: admin_panel.php');
    exit();
}

// Get user details
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

include 'includes/header.php';
?>

<div class="container my-4">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2><i class="fas fa-tachometer-alt text-primary"></i> Dashboard</h2>
                    <p class="text-muted mb-0">Welcome back, <?php echo htmlspecialchars($user['name']); ?>!</p>
                </div>
                <div class="text-end">
                    <span class="badge bg-<?php echo $user_role === 'buyer' ? 'primary' : ($user_role === 'seller' ? 'success' : 'warning'); ?> fs-6">
                        <?php echo ucfirst($user_role); ?>
                    </span>
                </div>
            </div>
        </div>

    </div>

    <?php if ($user_role === 'buyer'): ?>
        <!-- Buyer Dashboard -->
        <?php
        // Get buyer statistics
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total_purchases,
                SUM(amount) as total_spent,
                SUM(CASE WHEN delivery_status = 'pending' THEN 1 ELSE 0 END) as pending_deliveries,
                SUM(CASE WHEN delivery_status = 'confirmed' THEN 1 ELSE 0 END) as confirmed_deliveries
            FROM transactions 
            WHERE buyer_id = ?
        ");
        $stmt->execute([$user_id]);
        $buyer_stats = $stmt->fetch();

        // Get recent purchases
        $stmt = $pdo->prepare("
            SELECT t.*, p.title, p.image, u.name as seller_name 
            FROM transactions t
            JOIN products p ON t.product_id = p.id
            JOIN users u ON t.seller_id = u.id
            WHERE t.buyer_id = ?
            ORDER BY t.created_at DESC
            LIMIT 10
        ");
        $stmt->execute([$user_id]);
        $purchases = $stmt->fetchAll();

        // Ensure wishlist table exists then fetch wishlist items
        $pdo->exec("CREATE TABLE IF NOT EXISTS wishlist (
          id INT AUTO_INCREMENT PRIMARY KEY,
          buyer_id INT NOT NULL,
          product_id INT NOT NULL,
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          UNIQUE KEY uq_buyer_product (buyer_id, product_id),
          FOREIGN KEY (buyer_id) REFERENCES users(id) ON DELETE CASCADE,
          FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        $wq = $pdo->prepare("
            SELECT w.id as wid, p.*, u.name AS seller_name
            FROM wishlist w
            JOIN products p ON w.product_id = p.id
            JOIN users u ON p.seller_id = u.id
            WHERE w.buyer_id = ?
            ORDER BY w.created_at DESC
        ");
        $wq->execute([$user_id]);
        $wishlist = $wq->fetchAll();
        ?>

        <!-- Buyer Stats -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card dashboard-card text-center">
                    <div class="card-body">
                        <i class="fas fa-shopping-cart fa-2x text-primary mb-2"></i>
                        <h4><?php echo $buyer_stats['total_purchases']; ?></h4>
                        <small class="text-muted">Total Purchases</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card dashboard-card text-center">
                    <div class="card-body">
                        <i class="fas fa-rupee-sign fa-2x text-success mb-2"></i>
                        <h4><?php echo formatPrice($buyer_stats['total_spent'] ?? 0); ?></h4>
                        <small class="text-muted">Total Spent</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card dashboard-card text-center">
                    <div class="card-body">
                        <i class="fas fa-clock fa-2x text-warning mb-2"></i>
                        <h4><?php echo $buyer_stats['pending_deliveries']; ?></h4>
                        <small class="text-muted">Pending Deliveries</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card dashboard-card text-center">
                    <div class="card-body">
                        <i class="fas fa-check-circle fa-2x text-info mb-2"></i>
                        <h4><?php echo $buyer_stats['confirmed_deliveries']; ?></h4>
                        <small class="text-muted">Confirmed Orders</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Purchases -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-history"></i> Recent Purchases</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($purchases)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-shopping-cart fa-3x text-muted mb-3"></i>
                                <h5>No purchases yet</h5>
                                <p class="text-muted">Start shopping to see your purchase history here.</p>
                                <a href="index.php" class="btn btn-primary">Browse Products</a>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Product</th>
                                            <th>Seller</th>
                                            <th>Amount</th>
                                            <th>Status</th>
                                            <th>Delivery</th>
                                            <th>Date</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($purchases as $purchase): ?>
                                            <tr>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <?php if ($purchase['image']): ?>
                                                            <img src="assets/img/<?php echo htmlspecialchars($purchase['image']); ?>" 
                                                                 class="rounded me-2" style="width: 40px; height: 40px; object-fit: cover;">
                                                        <?php else: ?>
                                                            <div class="bg-light rounded me-2 d-flex align-items-center justify-content-center" 
                                                                 style="width: 40px; height: 40px;">
                                                                <i class="fas fa-image text-muted"></i>
                                                            </div>
                                                        <?php endif; ?>
                                                        <div>
                                                            <div class="fw-bold"><?php echo htmlspecialchars($purchase['title']); ?></div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td><?php echo htmlspecialchars($purchase['seller_name']); ?></td>
                                                <td><?php echo formatPrice($purchase['amount']); ?></td>
                                                <td>
                                                    <span class="badge bg-<?php 
                                                        echo $purchase['status'] === 'pending' ? 'warning' : 
                                                            ($purchase['status'] === 'approved' ? 'success' : 
                                                            ($purchase['status'] === 'released' ? 'info' : 'danger')); 
                                                    ?>">
                                                        <?php echo ucfirst($purchase['status']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?php 
                                                        echo $purchase['delivery_status'] === 'pending' ? 'warning' : 
                                                            ($purchase['delivery_status'] === 'confirmed' ? 'success' : 'danger'); 
                                                    ?>">
                                                        <?php echo ucfirst($purchase['delivery_status']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo date('M j, Y', strtotime($purchase['created_at'])); ?></td>
                                                <td>
                                                    <?php if ($purchase['delivery_status'] === 'pending' && $purchase['status'] === 'approved'): ?>
                                                        <a href="confirm_delivery.php?id=<?php echo $purchase['id']; ?>" 
                                                           class="btn btn-sm btn-success">
                                                            <i class="fas fa-check"></i> Confirm
                                                        </a>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

    <?php elseif ($user_role === 'seller'): ?>
        <!-- Seller Dashboard -->
        <?php
        // Get seller statistics
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total_products,
                SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_products,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_products
            FROM products 
            WHERE seller_id = ?
        ");
        $stmt->execute([$user_id]);
        $seller_stats = $stmt->fetch();

        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total_sales,
                SUM(seller_amount) as total_earnings,
                SUM(CASE WHEN status = 'released' THEN seller_amount ELSE 0 END) as released_earnings,
                SUM(CASE WHEN status = 'pending' OR status = 'approved' THEN seller_amount ELSE 0 END) as pending_earnings
            FROM transactions 
            WHERE seller_id = ?
        ");
        $stmt->execute([$user_id]);
        $sales_stats = $stmt->fetch();

        // Get recent products
        $stmt = $pdo->prepare("
            SELECT * FROM products 
            WHERE seller_id = ?
            ORDER BY created_at DESC
            LIMIT 10
        ");
        $stmt->execute([$user_id]);
        $products = $stmt->fetchAll();

        // Fetch active negotiations for this seller
        $negStmt = $pdo->prepare("\n            SELECT n.id AS negotiation_id, n.product_id, n.buyer_id, u.name AS buyer_name, p.title AS product_title,\n                   (SELECT m.message FROM messages m WHERE m.negotiation_id = n.id ORDER BY m.id DESC LIMIT 1) AS last_message,\n                   (SELECT m.created_at FROM messages m WHERE m.negotiation_id = n.id ORDER BY m.id DESC LIMIT 1) AS last_time\n            FROM negotiations n\n            JOIN users u ON n.buyer_id = u.id\n            JOIN products p ON n.product_id = p.id\n            WHERE n.seller_id = ?\n            ORDER BY n.updated_at DESC\n        ");
        $negStmt->execute([$user_id]);
        $negotiations = $negStmt->fetchAll();
        ?>

        <div class="row g-4">
          <!-- Left: Negotiations + Products -->
          <div class="col-lg-8">
            <div class="card mb-4">
              <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-comments me-2"></i>Negotiations</h5>
              </div>
              <div class="card-body">
                <?php if (empty($negotiations)): ?>
                  <div class="text-center py-4">
                    <i class="fas fa-comments fa-3x text-muted mb-3"></i>
                    <h5>No active negotiations yet</h5>
                    <p class="text-muted mb-0">When buyers initiate chats on your product pages, they will appear here.</p>
                  </div>
                <?php else: ?>
                  <div class="table-responsive">
                    <table class="table table-hover align-middle">
                      <thead>
                        <tr>
                          <th>Product</th>
                          <th>Buyer</th>
                          <th>Last Message</th>
                          <th>Updated</th>
                          <th></th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php foreach ($negotiations as $neg): ?>
                          <tr>
                            <td><?php echo htmlspecialchars($neg['product_title']); ?></td>
                            <td><?php echo htmlspecialchars($neg['buyer_name']); ?></td>
                            <td class="text-truncate" style="max-width: 320px;">
                              <?php echo htmlspecialchars($neg['last_message'] ?? ''); ?>
                            </td>
                            <td>
                              <small class="text-muted"><?php echo $neg['last_time'] ? date('M j, Y H:i', strtotime($neg['last_time'])) : '-'; ?></small>
                            </td>
                            <td class="text-end">
                              <button class="btn btn-sm btn-outline-gold openChatBtn"
                                      data-role="seller"
                                      data-product-id="<?php echo (int)$neg['product_id']; ?>"
                                      data-seller-id="<?php echo (int)$user_id; ?>"
                                      data-buyer-id="<?php echo (int)$neg['buyer_id']; ?>">
                                <i class="fas fa-comments"></i> Open Chat
                              </button>
                            </td>
                          </tr>
                        <?php endforeach; ?>
                      </tbody>
                    </table>
                  </div>
                <?php endif; ?>
              </div>
            </div>

            <div class="card">
              <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-box me-2"></i>My Products</h5>
                <a href="add_product.php" class="btn btn-outline-gold btn-sm"><i class="fas fa-plus"></i> Add Product</a>
              </div>
              <div class="card-body">
                <?php if (empty($products)): ?>
                  <div class="text-center py-4">
                    <i class="fas fa-box fa-3x text-muted mb-3"></i>
                    <h5>No products yet</h5>
                    <p class="text-muted">Start selling by adding your first product.</p>
                    <a href="add_product.php" class="btn btn-primary">Add Product</a>
                  </div>
                <?php else: ?>
                  <div class="table-responsive">
                    <table class="table table-hover">
                      <thead>
                        <tr>
                          <th>Product</th>
                          <th>Price</th>
                          <th>Category</th>
                          <th>Status</th>
                          <th>Created</th>
                          <th>Actions</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php foreach ($products as $product): ?>
                          <tr>
                            <td>
                              <div class="d-flex align-items-center">
                                <?php if ($product['image']): ?>
                                  <img src="assets/img/<?php echo htmlspecialchars($product['image']); ?>" class="rounded me-2" style="width: 40px; height: 40px; object-fit: cover;">
                                <?php else: ?>
                                  <div class="bg-light rounded me-2 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                    <i class="fas fa-image text-muted"></i>
                                  </div>
                                <?php endif; ?>
                                <div>
                                  <div class="fw-bold"><?php echo htmlspecialchars($product['title']); ?></div>
                                  <small class="text-muted"><?php echo htmlspecialchars(substr($product['description'], 0, 50)) . '...'; ?></small>
                                </div>
                              </div>
                            </td>
                            <td><?php echo formatPrice($product['price']); ?></td>
                            <td><?php echo htmlspecialchars($product['category'] ?? 'N/A'); ?></td>
                            <td>
                              <span class="badge bg-<?php echo $product['status'] === 'pending' ? 'warning' : ($product['status'] === 'approved' ? 'success' : 'danger'); ?>"><?php echo ucfirst($product['status']); ?></span>
                            </td>
                            <td><?php echo date('M j, Y', strtotime($product['created_at'])); ?></td>
                            <td>
                              <div class="btn-group btn-group-sm">
                                <a href="product_details.php?id=<?php echo $product['id']; ?>" class="btn btn-ghost" title="View"><i class="fas fa-eye"></i></a>
                                <a href="edit_product.php?id=<?php echo $product['id']; ?>" class="btn btn-secondary" title="Edit"><i class="fas fa-edit"></i></a>
                                <a href="delete_product.php?id=<?php echo $product['id']; ?>" class="btn btn-outline-danger delete-btn" data-action="delete this product" title="Delete"><i class="fas fa-trash"></i></a>
                              </div>
                            </td>
                          </tr>
                        <?php endforeach; ?>
                      </tbody>
                    </table>
                  </div>
                <?php endif; ?>
              </div>
            </div>
          </div>

          <!-- Right: Stats + Quick Actions -->
          <div class="col-lg-4">
            <div class="card dashboard-card text-center mb-4">
              <div class="card-body">
                <div class="row g-3">
                  <div class="col-6">
                    <div>
                      <div class="text-muted">Products</div>
                      <div class="fs-5 fw-bold"><?php echo $seller_stats['total_products']; ?></div>
                    </div>
                  </div>
                  <div class="col-6">
                    <div>
                      <div class="text-muted">Approved</div>
                      <div class="fs-5 fw-bold"><?php echo $seller_stats['approved_products']; ?></div>
                    </div>
                  </div>
                  <div class="col-6">
                    <div>
                      <div class="text-muted">Sales</div>
                      <div class="fs-5 fw-bold"><?php echo $sales_stats['total_sales']; ?></div>
                    </div>
                  </div>
                  <div class="col-6">
                    <div>
                      <div class="text-muted">Released</div>
                      <div class="fw-bold"><?php echo formatPrice($sales_stats['released_earnings'] ?? 0); ?></div>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <div class="card">
              <div class="card-body">
                <h5 class="mb-3"><i class="fas fa-bolt me-2"></i>Quick Actions</h5>
                <div class="d-grid gap-2">
                  <a href="add_product.php" class="btn btn-primary"><i class="fas fa-plus"></i> Add New Product</a>
                  <button class="btn btn-secondary" disabled><i class="fas fa-wallet"></i> Pending Earnings: <?php echo formatPrice($sales_stats['pending_earnings'] ?? 0); ?></button>
                  <button class="btn btn-ghost" disabled><i class="fas fa-clock"></i> Pending Approval: <?php echo (int)$seller_stats['pending_products']; ?></button>
                </div>
              </div>
            </div>
          </div>
        </div>

    <?php else: ?>
        <!-- Admin role should have been redirected server-side above -->
    <?php endif; ?>
</div>

<!-- Chat Modal on Dashboard (Seller/Buyer common) -->
<div class="modal fade chat-modal" id="chatModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-scrollable modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <div class="chat-header-title">
          <span class="presence-dot" id="chatPresence"></span>
          <strong>Negotiation Chat</strong>
        </div>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div id="chatMessages" class="chat-messages"></div>
        <div class="typing-indicator" id="typingIndicator" style="display:none;">Typing…</div>
      </div>
      <div class="modal-footer">
        <div class="input-group">
          <textarea class="form-control chat-input" id="chatInput" rows="1" placeholder="Type your message… (Enter to send, Shift+Enter for newline)"></textarea>
          <button class="btn btn-send" id="sendMsgBtn"><i class="fas fa-paper-plane"></i></button>
        </div>
      </div>
    </div>
  </div>
</div>

<?php include 'includes/footer.php'; ?>

