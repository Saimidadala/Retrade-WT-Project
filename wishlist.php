<?php
require_once __DIR__ . '/config.php';
requireLogin();
if (getUserRole() !== 'buyer') { header('Location: dashboard.php'); exit; }

$page_title = 'My Wishlist';
$buyerId = (int)$_SESSION['user_id'];

// Ensure table exists
$pdo->exec("CREATE TABLE IF NOT EXISTS wishlist (
  id INT AUTO_INCREMENT PRIMARY KEY,
  buyer_id INT NOT NULL,
  product_id INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_buyer_product (buyer_id, product_id),
  FOREIGN KEY (buyer_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

// Filters
$search = trim($_GET['search'] ?? '');
$category = trim($_GET['category'] ?? '');
$params = [$buyerId];
$where = ' WHERE w.buyer_id = ? ';
if ($search !== '') { $where .= ' AND (p.title LIKE ? OR p.description LIKE ?) '; $params[] = "%$search%"; $params[] = "%$search%"; }
if ($category !== '') { $where .= ' AND p.category = ? '; $params[] = $category; }

$catRows = $pdo->query("SELECT name FROM categories ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);

$sql = "SELECT w.id AS wid, p.*, u.name AS seller_name
        FROM wishlist w
        JOIN products p ON w.product_id = p.id
        JOIN users u ON p.seller_id = u.id
        $where
        ORDER BY w.created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$items = $stmt->fetchAll();

include __DIR__ . '/includes/header.php';
?>
<div class="container my-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h2><i class="fas fa-heart text-danger"></i> My Wishlist</h2>
    <span class="badge bg-secondary rounded-pill"><?php echo count($items); ?> items</span>
  </div>

  <div class="card mb-4">
    <div class="card-body">
      <form method="get" class="row g-3">
        <div class="col-md-4">
          <label class="form-label" for="search">Search</label>
          <input type="text" class="form-control" id="search" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search in wishlist...">
        </div>
        <div class="col-md-4">
          <label class="form-label" for="category">Category</label>
          <select class="form-select" id="category" name="category">
            <option value="">All</option>
            <?php foreach ($catRows as $cat): ?>
              <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo $category===$cat?'selected':''; ?>><?php echo htmlspecialchars($cat); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-2 d-flex align-items-end">
          <button class="btn btn-primary w-100"><i class="fas fa-filter"></i> Filter</button>
        </div>
        <div class="col-md-2 d-flex align-items-end">
          <a class="btn btn-outline-secondary w-100" href="wishlist.php"><i class="fas fa-undo"></i> Reset</a>
        </div>
      </form>
    </div>
  </div>

  <?php if (empty($items)): ?>
    <div class="text-center py-5">
      <i class="fas fa-heart fa-3x text-muted mb-3"></i>
      <h5>No items in your wishlist</h5>
      <p class="text-muted">Explore products and add some to your wishlist.</p>
      <a href="index.php" class="btn btn-primary"><i class="fas fa-search"></i> Browse Products</a>
    </div>
  <?php else: ?>
    <div class="row">
      <?php foreach ($items as $it): if (!($it['image'] && file_exists(__DIR__.'/assets/img/'.$it['image']))) continue; ?>
        <div class="col-lg-4 col-md-6 mb-4">
          <div class="card h-100">
            <img src="assets/img/<?php echo htmlspecialchars($it['image']); ?>" class="card-img-top" style="height:200px;object-fit:cover" alt="<?php echo htmlspecialchars($it['title']); ?>">
            <div class="card-body d-flex flex-column">
              <h5 class="card-title mb-1"><?php echo htmlspecialchars($it['title']); ?></h5>
              <small class="text-muted mb-2"><i class="fas fa-user"></i> <?php echo htmlspecialchars($it['seller_name']); ?></small>
              <div class="mt-auto d-grid gap-2">
                <a href="product_details.php?id=<?php echo (int)$it['id']; ?>" class="btn btn-outline-primary"><i class="fas fa-eye"></i> View</a>
                <div class="d-flex gap-2">
                  <button class="btn btn-success flex-fill addToCartBtn" data-product-id="<?php echo (int)$it['id']; ?>"><i class="fas fa-cart-plus"></i> Move to Cart</button>
                  <button class="btn btn-outline-danger wishlistBtn" data-product-id="<?php echo (int)$it['id']; ?>" title="Remove"><i class="fas fa-times"></i></button>
                </div>
                <button class="btn btn-secondary openChatBtn"
                        data-role="buyer"
                        data-product-id="<?php echo (int)$it['id']; ?>"
                        data-seller-id="<?php echo (int)$it['seller_id']; ?>"
                        data-buyer-id="<?php echo $buyerId; ?>"
                        data-product-title="<?php echo htmlspecialchars($it['title']); ?>">
                  <i class="fas fa-comments"></i> Negotiate
                </button>
              </div>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
