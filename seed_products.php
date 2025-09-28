<?php
require_once __DIR__ . '/config.php';

// Safety: allow only admins to run this
if (!isLoggedIn() || getUserRole() !== 'admin') {
    http_response_code(403);
    echo 'Forbidden: Admin only.';
    exit;
}

$page_title = 'Seed Demo Products';

// Helper: get or create a demo seller
function getDemoSellerId(PDO $pdo): int {
    // Try to find any seller
    $stmt = $pdo->query("SELECT id FROM users WHERE role = 'seller' ORDER BY id LIMIT 1");
    $sid = (int)($stmt->fetchColumn() ?: 0);
    if ($sid > 0) return $sid;

    // Create a seller if none exists
    $name = 'Demo Seller';
    $email = 'demo_seller_' . time() . '@retrade.com';
    $password = password_hash('password', PASSWORD_BCRYPT);
    $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role, balance) VALUES (?, ?, ?, 'seller', 0)");
    $stmt->execute([$name, $email, $password]);
    return (int)$pdo->lastInsertId();
}

$messages = [];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $count = max(1, min(200, (int)($_POST['count'] ?? 50)));
    $status = $_POST['status'] ?? 'approved'; // approved | pending | mixed

    try {
        $pdo->beginTransaction();

        // Categories pool
        $cats = [];
        $q = $pdo->query("SELECT name FROM categories ORDER BY name");
        $cats = $q->fetchAll(PDO::FETCH_COLUMN);
        if (empty($cats)) {
            $cats = ['Electronics','Clothing','Books','Home & Garden','Sports','Toys','Automotive','Health & Beauty'];
        }

        $sellerId = getDemoSellerId($pdo);

        // Title fragments
        $brands = ['Acme','Nova','Zenith','Pulse','Vertex','Nimbus','Orion','Quantum','Aero','Vivid'];
        $items  = ['Headphones','Smartwatch','Backpack','Coffee Maker','LED Monitor','Wireless Mouse','Keyboard','Running Shoes','Bluetooth Speaker','DSLR Lens','Air Purifier','Gaming Chair','Portable SSD','Action Camera','E-book Reader'];
        $adjs   = ['Pro','Max','Lite','Ultra','Plus','Mini','X','S','Prime','Edge'];

        $insert = $pdo->prepare("INSERT INTO products (seller_id, title, description, price, image, category, status) VALUES (?,?,?,?,?,?,?)");

        for ($i=0; $i<$count; $i++) {
            $title = $brands[array_rand($brands)] . ' ' . $items[array_rand($items)] . ' ' . $adjs[array_rand($adjs)];
            $cat   = $cats[array_rand($cats)];
            $price = rand(900, 95000) + (rand(0,99)/100);
            $desc  = "Brand new/like new $title with warranty. Lorem ipsum dolor sit amet, consectetur adipiscing elit. ";
            $img   = null; // Optional: place filenames if you upload images to assets/img

            $st = $status === 'mixed' ? (rand(0, 100) < 70 ? 'approved' : (rand(0,1) ? 'pending' : 'rejected')) : $status;

            $insert->execute([$sellerId, $title, $desc, $price, $img, $cat, $st]);
        }

        $pdo->commit();
        $messages[] = "Inserted $count demo products successfully.";
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $errors[] = 'Seeding failed: ' . $e->getMessage();
    }
}

include __DIR__ . '/includes/header.php';
?>
<div class="container my-4">
  <div class="row justify-content-center">
    <div class="col-lg-7">
      <div class="card">
        <div class="card-header d-flex align-items-center justify-content-between">
          <h5 class="mb-0"><i class="fas fa-seedling me-2 text-success"></i>Seed Demo Products</h5>
        </div>
        <div class="card-body">
          <?php foreach ($messages as $m): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle me-1"></i><?php echo htmlspecialchars($m); ?></div>
          <?php endforeach; ?>
          <?php foreach ($errors as $er): ?>
            <div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-1"></i><?php echo htmlspecialchars($er); ?></div>
          <?php endforeach; ?>

          <form method="post" class="row g-3">
            <div class="col-md-6">
              <label for="count" class="form-label">Number of products</label>
              <input type="number" min="1" max="200" class="form-control" id="count" name="count" value="60" required>
            </div>
            <div class="col-md-6">
              <label for="status" class="form-label">Default status</label>
              <select id="status" name="status" class="form-select">
                <option value="approved">Approved</option>
                <option value="pending">Pending</option>
                <option value="mixed" selected>Mixed</option>
              </select>
            </div>
            <div class="col-12 d-flex justify-content-between">
              <a href="index.php" class="btn btn-secondary"><i class="fas fa-arrow-left me-1"></i>Back</a>
              <button class="btn btn-success"><i class="fas fa-rocket me-1"></i>Seed Now</button>
            </div>
          </form>

          <div class="mt-3 text-muted-2 small">
            Tip: Upload image files to <code>assets/img/</code> and update the seeder to assign filenames if you want images.
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
