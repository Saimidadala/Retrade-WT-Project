<?php
require_once 'config.php';

$page_title = 'Home';

// Get search and filter parameters
$search = $_GET['search'] ?? '';
$category = $_GET['category'] ?? '';
$condition_min = $_GET['condition_min'] ?? '';
$max_price = $_GET['max_price'] ?? '';
// Sorting: newest (default), price_asc, price_desc
$sort = $_GET['sort'] ?? 'newest';
// Pagination
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = max(6, min(48, (int)($_GET['per_page'] ?? 12)));
$offset = ($page - 1) * $per_page;

// Build query
$base = " FROM products p 
          JOIN users u ON p.seller_id = u.id ";
$where = " WHERE p.status = 'approved' AND p.image IS NOT NULL AND p.image <> ''";
$params = [];
if (!empty($search)) {
    $where .= " AND (p.title LIKE ? OR p.description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($category)) {
    $where .= " AND p.category = ?";
    $params[] = $category;
}

if (!empty($max_price)) {
    $where .= " AND p.price <= ?";
    $params[] = $max_price;
}

// Condition minimum filter using grade ordering in PHP -> convert to IN list
if (!empty($condition_min)) {
    $grades = ['Poor','Fair','Good','Excellent','Like New','New'];
    $idx = array_search($condition_min, $grades, true);
    if ($idx !== false) {
        $accept = array_slice($grades, $idx); // grades >= selected
        $placeholders = implode(',', array_fill(0, count($accept), '?'));
        $where .= " AND p.condition_grade IN ($placeholders)";
        foreach ($accept as $g) { $params[] = $g; }
    }
}

// Determine ORDER BY based on sort
switch ($sort) {
    case 'price_asc':
        $orderBy = 'p.price ASC, p.created_at DESC';
        break;
    case 'price_desc':
        $orderBy = 'p.price DESC, p.created_at DESC';
        break;
    default:
        $orderBy = 'p.created_at DESC';
}

// Total count for pagination
$countSql = "SELECT COUNT(*)" . $base . $where;
$stmt = $pdo->prepare($countSql);
$stmt->execute($params);
$total_count = (int)$stmt->fetchColumn();

// Main query w/ pagination
$query = "SELECT p.*, u.name as seller_name, u.email as seller_email" . $base . $where . " ORDER BY $orderBy LIMIT $per_page OFFSET $offset";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$products = $stmt->fetchAll();

// Get categories for filter from categories table (show all available categories)
$stmt = $pdo->query("SELECT name FROM categories ORDER BY name");
$categories = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Compute Trending products using activity signals
// Score = tx(30d)*5 + cart*3 + wishlist*2 + recencyBoost
// recencyBoost = max(0, 100 - days_since_created)
$trending = [];
try {
    $trendSql = "
        SELECT p.*, u.name AS seller_name,
               COALESCE(tr.cnt,0) AS tx_cnt,
               COALESCE(ct.cnt,0) AS cart_cnt,
               COALESCE(wl.cnt,0) AS wl_cnt,
               GREATEST(0, 100 - DATEDIFF(NOW(), p.created_at)) AS recency
        FROM products p
        JOIN users u ON p.seller_id = u.id
        LEFT JOIN (
            SELECT product_id, COUNT(*) cnt
            FROM transactions
            WHERE created_at >= (NOW() - INTERVAL 30 DAY)
            GROUP BY product_id
        ) tr ON tr.product_id = p.id
        LEFT JOIN (
            SELECT product_id, COUNT(*) cnt FROM cart GROUP BY product_id
        ) ct ON ct.product_id = p.id
        LEFT JOIN (
            SELECT product_id, COUNT(*) cnt FROM wishlist GROUP BY product_id
        ) wl ON wl.product_id = p.id
        WHERE p.status = 'approved' AND p.image IS NOT NULL AND p.image <> ''
        ORDER BY (COALESCE(tr.cnt,0)*5 + COALESCE(ct.cnt,0)*3 + COALESCE(wl.cnt,0)*2 + GREATEST(0, 100 - DATEDIFF(NOW(), p.created_at))) DESC,
                 p.created_at DESC
        LIMIT 10
    ";
    $trStmt = $pdo->prepare($trendSql);
    $trStmt->execute();
    $trending = $trStmt->fetchAll();
} catch (Throwable $e) {
    // Fallback: use recent approved products
    $trending = array_slice($products, 0, min(10, count($products)));
}

// Map friendly icons for common categories to make pills familiar
$catIcons = [
    'Smartphones' => 'fa-mobile-alt',
    'Electronics' => 'fa-plug',
    'Laptops' => 'fa-laptop',
    'Headphones & Earbuds' => 'fa-headphones',
    'Wearables' => 'fa-clock',
    'Speakers' => 'fa-volume-up',
    'Cameras' => 'fa-camera',
    'Home & Kitchen' => 'fa-utensils',
    'Home Appliances' => 'fa-blender',
    'Networking' => 'fa-wifi',
    'Accessories' => 'fa-tag',
    'Bags' => 'fa-briefcase',
    'Books' => 'fa-book',
    'Clothing' => 'fa-tshirt',
    'Automotive' => 'fa-car',
    'Health & Beauty' => 'fa-heart',
    'Toys' => 'fa-puzzle-piece',
    'Sports' => 'fa-basketball-ball',
];

// Get statistics
$stmt = $pdo->query("SELECT 
    (SELECT COUNT(*) FROM users WHERE role != 'admin') as total_users,
    (SELECT COUNT(*) FROM products WHERE status = 'approved') as total_products,
    (SELECT COUNT(*) FROM transactions) as total_transactions
");
$stats = $stmt->fetch();

include 'includes/header.php';
?>

<!-- Hero Section -->
<section class="hero-section">
    <div class="container text-center">
        <h1><i class="fas fa-store me-3"></i>Welcome to Retrade</h1>
        <p class="lead">Your trusted marketplace for safe buying and selling with escrow protection</p>
        <?php if (!isLoggedIn()): ?>
            <div class="mt-4 d-flex justify-content-center gap-3 flex-wrap">
                <a href="register.php" class="btn btn-light btn-lg h-item">
                    <i class="fas fa-user-plus"></i> Join Now
                </a>
                <a href="login.php" class="btn btn-outline-light btn-lg h-item">
                    <i class="fas fa-sign-in-alt"></i> Sign In
                </a>
            </div>
        <?php endif; ?>

    <!-- Top Categories (quick filters) moved below CTA with clear spacing -->
    <?php if (!empty($categories)): ?>
    <div class="row mb-4 mt-4 mt-md-5 cat-strip">
        <div class="col-12">
            <div class="d-flex align-items-center gap-2 mb-2 text-muted small cat-strip-title">
                <i class="fas fa-tags text-warning"></i>
                <span>Browse by Category</span>
            </div>
            <div class="h-scroll">
                <?php $catIdx = 0; foreach ($categories as $cat): $catIdx++; $variant = ($catIdx % 6) + 1; $icon = $catIcons[$cat] ?? 'fa-tag'; ?>
                    <a class="cat-pill cat-variant-<?php echo $variant; ?> h-item" 
                       href="index.php?category=<?php echo urlencode($cat); ?>#products" 
                       title="Browse <?php echo htmlspecialchars($cat); ?>">
                        <span class="ci"><i class="fas <?php echo $icon; ?>"></i></span>
                        <span class="ct"><?php echo htmlspecialchars($cat); ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
    </div>
</section>

<div class="container">
    <?php if (isset($_GET['message'])): ?>
        <?php if ($_GET['message'] === 'logged_out'): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> You have been successfully logged out.
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <!-- Statistics (Prominent) -->
    <div class="row mb-5 g-4">
        <div class="col-md-4">
            <div class="home-stat hover-lift p-3 h-100">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon"><i class="fas fa-users"></i></div>
                    <div>
                        <div class="stat-value" data-count="<?php echo (int)$stats['total_users']; ?>">0</div>
                        <div class="stat-label">Active Users</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="home-stat hover-lift p-3 h-100">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon"><i class="fas fa-box"></i></div>
                    <div>
                        <div class="stat-value" data-count="<?php echo (int)$stats['total_products']; ?>">0</div>
                        <div class="stat-label">Products Listed</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="home-stat hover-lift p-3 h-100">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon"><i class="fas fa-handshake"></i></div>
                    <div>
                        <div class="stat-value" data-count="<?php echo (int)$stats['total_transactions']; ?>">0</div>
                        <div class="stat-label">Safe Transactions</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Search and Filter -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-3">
                            <label for="search" class="form-label">Search Products</label>
                            <input type="text" class="form-control" id="search" name="search" 
                                   placeholder="Search by title or description..." 
                                   value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        <div class="col-md-3">
                            <label for="category" class="form-label">Category</label>
                            <select class="form-select" id="category" name="category">
                                <option value="">All Categories</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo htmlspecialchars($cat); ?>" 
                                            <?php echo $category === $cat ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($cat); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="max_price" class="form-label">Max Price (₹)</label>
                            <input type="number" class="form-control" id="max_price" name="max_price" 
                                   placeholder="Enter max price" value="<?php echo htmlspecialchars($max_price); ?>">
                        </div>
                        <div class="col-md-3">
                            <label for="condition_min" class="form-label">Minimum Condition</label>
                            <select class="form-select" id="condition_min" name="condition_min">
                                <option value="">Any</option>
                                <?php foreach (['Poor','Fair','Good','Excellent','Like New','New'] as $g): ?>
                                    <option value="<?php echo $g; ?>" <?php echo $condition_min===$g?'selected':''; ?>><?php echo $g; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label for="sort" class="form-label">Sort By</label>
                            <select class="form-select" id="sort" name="sort">
                                <option value="newest" <?php echo $sort==='newest'?'selected':''; ?>>Newest</option>
                                <option value="price_asc" <?php echo $sort==='price_asc'?'selected':''; ?>>Price: Low to High</option>
                                <option value="price_desc" <?php echo $sort==='price_desc'?'selected':''; ?>>Price: High to Low</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">&nbsp;</label>
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-search"></i> Search
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <!-- Trending (simple: latest products subset) -->
    <?php if (!empty($trending)): ?>
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h3 class="mb-0"><i class="fas fa-fire text-warning"></i> Trending Now</h3>
            </div>
            <div class="h-scroll">
                <?php foreach ($trending as $tp): if (!($tp['image'] && file_exists("assets/img/" . $tp['image']))) continue; ?>
                    <div class="trend-card h-item hover-lift">
                        <img src="assets/img/<?php echo htmlspecialchars($tp['image']); ?>" alt="<?php echo htmlspecialchars($tp['title']); ?>" loading="lazy">
                        <div class="t-body">
                            <div class="t-title text-truncate"><?php echo htmlspecialchars($tp['title']); ?></div>
                            <div class="t-meta">
                                <span><?php echo formatPrice($tp['price']); ?></span>
                                <span class="text-truncate" style="max-width:100px"><i class="fas fa-user"></i> <?php echo htmlspecialchars($tp['seller_name']); ?></span>
                            </div>
                            <div class="mt-2 d-grid">
                                <a href="product_details.php?id=<?php echo $tp['id']; ?>" class="btn btn-ghost btn-sm">View</a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Trust & Safety band -->
    <div class="row mb-5">
        <div class="col-12">
            <div class="trust-band p-3">
                <div class="row g-3">
                    <div class="col-md-4">
                        <div class="trust-item">
                            <div class="ti-icon"><i class="fas fa-shield-alt"></i></div>
                            <div>
                                <p class="ti-title">Escrow Protection</p>
                                <p class="ti-text">Your payment is held securely until the product is delivered as promised.</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="trust-item">
                            <div class="ti-icon"><i class="fas fa-lock"></i></div>
                            <div>
                                <p class="ti-title">Secure Payments</p>
                                <p class="ti-text">Industry-standard encryption and trusted payment gateways.</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="trust-item">
                            <div class="ti-icon"><i class="fas fa-comments"></i></div>
                            <div>
                                <p class="ti-title">In-app Chat</p>
                                <p class="ti-text">Negotiate and clarify details with real-time messaging.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Products Grid -->
    <div class="row" id="products">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2><i class="fas fa-shopping-bag text-primary"></i> Featured Products</h2>
                <span class="badge bg-secondary rounded-pill"><?php echo $total_count; ?> products found</span>
            </div>
        </div>
    </div>

    <?php if (empty($products)): ?>
        <div class="row">
            <div class="col-12">
                <div class="text-center py-5">
                    <h4>No products found</h4>
                    <p class="text-muted">Try adjusting your search criteria or browse all products.</p>
                    <?php if (!empty($search) || !empty($category) || !empty($max_price)): ?>
                        <a href="index.php" class="btn btn-primary">View All Products</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="row" id="productsSection">
            <?php foreach ($products as $product): if (!($product['image'] && file_exists("assets/img/" . $product['image']))) continue; ?>
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="card product-card h-100">
                        <div class="position-relative">
                            <img src="assets/img/<?php echo htmlspecialchars($product['image']); ?>" 
                                 class="card-img-top aspect-4x3 rounded-12" 
                                 alt="<?php echo htmlspecialchars($product['title']); ?>" loading="lazy">
                            <?php if ($product['category']): ?>
                                <span class="badge bg-primary position-absolute top-0 start-0 m-2">
                                    <?php echo htmlspecialchars($product['category']); ?>
                                </span>
                            <?php endif; ?>
                            <?php if (isLoggedIn() && getUserRole()==='buyer' && ($_SESSION['user_id'] ?? 0) != $product['seller_id']): ?>
                              <div class="position-absolute top-0 end-0 m-2 quick-actions d-flex gap-1">
                                <button class="btn btn-sm btn-outline-secondary wishlistBtn" 
                                  data-product-id="<?php echo (int)$product['id']; ?>" title="Add to wishlist" aria-label="Add to wishlist">
                                  <i class="fas fa-heart"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-secondary addToCartBtn" 
                                  data-product-id="<?php echo (int)$product['id']; ?>" title="Add to cart" aria-label="Add to cart">
                                  <i class="fas fa-cart-plus"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-primary openChatBtn" 
                                  data-role="buyer"
                                  data-product-id="<?php echo (int)$product['id']; ?>"
                                  data-seller-id="<?php echo (int)$product['seller_id']; ?>"
                                  data-buyer-id="<?php echo (int)($_SESSION['user_id'] ?? 0); ?>"
                                  data-product-title="<?php echo htmlspecialchars($product['title']); ?>"
                                  title="Negotiate" aria-label="Negotiate">
                                  <i class="fas fa-comments"></i>
                                </button>
                              </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="card-body d-flex flex-column">
                            <h5 class="card-title"><?php echo htmlspecialchars($product['title']); ?></h5>
                            <?php if (!empty($product['condition_grade'])): ?>
                                <div class="mb-2"><span class="badge bg-secondary">Condition: <?php echo htmlspecialchars($product['condition_grade']); ?></span></div>
                            <?php endif; ?>
                            <p class="card-text text-muted flex-grow-1">
                                <?php echo htmlspecialchars(substr($product['description'], 0, 100)) . '...'; ?>
                            </p>
                            <div class="mt-auto">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="product-price"><?php echo formatPrice($product['price']); ?></span>
                                    <small class="text-muted">
                                        <i class="fas fa-user"></i> <?php echo htmlspecialchars($product['seller_name']); ?>
                                    </small>
                                </div>
                                <div class="d-flex justify-content-between text-muted small mb-2">
                                    <span><i class="far fa-clock"></i> <?php echo getTimeAgo($product['created_at']); ?></span>
                                    <?php if (!empty($product['category'])): ?>
                                        <span><i class="fas fa-tag"></i> <?php echo htmlspecialchars($product['category']); ?></span>
                                    <?php else: ?>
                                        <span>&nbsp;</span>
                                    <?php endif; ?>
                                </div>
                                <div class="d-grid gap-2">
                                    <a href="product_details.php?id=<?php echo $product['id']; ?>" 
                                       class="btn btn-outline-primary">
                                        <i class="fas fa-eye"></i> View Details
                                    </a>
                                    <?php if (isLoggedIn() && getUserRole()==='buyer' && ($_SESSION['user_id'] ?? 0) != $product['seller_id']): ?>
                                        <div class="d-flex gap-2">
                                            <button class="btn btn-primary openChatBtn flex-fill"
                                                data-role="buyer"
                                                data-product-id="<?php echo (int)$product['id']; ?>"
                                                data-seller-id="<?php echo (int)$product['seller_id']; ?>"
                                                data-buyer-id="<?php echo (int)($_SESSION['user_id'] ?? 0); ?>"
                                                data-product-title="<?php echo htmlspecialchars($product['title']); ?>">
                                                <i class="fas fa-comments"></i> Negotiate
                                            </button>
                                            <button class="btn btn-outline-secondary wishlistBtn" 
                                                data-product-id="<?php echo (int)$product['id']; ?>" title="Wishlist">
                                                <i class="fas fa-heart"></i>
                                            </button>
                                            <button class="btn btn-success addToCartBtn" 
                                                data-product-id="<?php echo (int)$product['id']; ?>" title="Add to Cart">
                                                <i class="fas fa-cart-plus"></i>
                                            </button>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
        // Pagination controls
        $total_pages = max(1, (int)ceil($total_count / $per_page));
        if ($total_pages > 1):
            // Build base query string preserving filters, excluding page
            $qs = $_GET; unset($qs['page']);
            $qs['per_page'] = $per_page;
            $buildLink = function($p) use ($qs) {
                $qs['page'] = $p; return 'index.php?' . http_build_query($qs) . '#products';
            };
        ?>
        <nav aria-label="Products pagination" class="mt-3">
            <ul class="pagination justify-content-center flex-wrap">
                <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                    <a class="page-link" href="<?php echo $buildLink(max(1, $page-1)); ?>" tabindex="-1">Prev</a>
                </li>
                <?php
                // windowed pages
                $start = max(1, $page - 2); $end = min($total_pages, $page + 2);
                if ($start > 1) {
                    echo '<li class="page-item"><a class="page-link" href="' . $buildLink(1) . '">1</a></li>';
                    if ($start > 2) echo '<li class="page-item disabled"><span class="page-link">…</span></li>';
                }
                for ($p=$start; $p<=$end; $p++) {
                    $active = $p === $page ? 'active' : '';
                    echo '<li class="page-item ' . $active . '"><a class="page-link" href="' . $buildLink($p) . '">' . $p . '</a></li>';
                }
                if ($end < $total_pages) {
                    if ($end < $total_pages - 1) echo '<li class="page-item disabled"><span class="page-link">…</span></li>';
                    echo '<li class="page-item"><a class="page-link" href="' . $buildLink($total_pages) . '">' . $total_pages . '</a></li>';
                }
                ?>
                <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                    <a class="page-link" href="<?php echo $buildLink(min($total_pages, $page+1)); ?>">Next</a>
                </li>
            </ul>
        </nav>
        <?php endif; ?>
    <?php endif; ?>

    <!-- Call to Action -->
    <?php if (!isLoggedIn()): ?>
        <div class="row mt-5">
            <div class="col-12">
                <div class="card cta-card">
                    <div class="card-body text-center py-5">
                        <h3>Ready to start trading?</h3>
                        <p class="lead">Join thousands of users who trust Retrade for safe transactions</p>
                        <div class="mt-4">
                            <a href="register.php?role=buyer" class="btn btn-primary btn-lg me-3">
                                <i class="fas fa-shopping-cart"></i> Start Buying
                            </a>
                            <a href="register.php?role=seller" class="btn btn-success btn-lg">
                                <i class="fas fa-store"></i> Start Selling
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php if (isLoggedIn() && getUserRole()==='buyer'): ?>
<!-- Reusable Chat Modal for quick negotiate from cards -->
<div class="modal fade chat-modal" id="chatModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-scrollable modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <div class="chat-header-title">
          <span class="presence-dot" id="chatPresence"></span>
          <strong>Negotiation Chat</strong>
          <small class="text-muted ms-2" id="chatProductTitle"></small>
        </div>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div id="chatMessages" class="chat-messages"></div>
        <div class="typing-indicator" id="typingIndicator" style="display:none;">Seller is typing…</div>
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
<?php endif; ?>

<?php include 'includes/footer.php'; ?>

<script>
// Animate stats counters when visible
(function(){
  const els = document.querySelectorAll('.home-stat .stat-value');
  if (!('IntersectionObserver' in window) || els.length === 0) return;

  const easeOutCubic = t => 1 - Math.pow(1 - t, 3);
  const animateCount = (el) => {
    const target = parseInt(el.getAttribute('data-count') || '0', 10);
    const duration = 900; // ms
    const start = performance.now();
    const step = (now) => {
      const p = Math.min(1, (now - start) / duration);
      const val = Math.floor(easeOutCubic(p) * target);
      el.textContent = new Intl.NumberFormat().format(val);
      if (p < 1) requestAnimationFrame(step);
      else el.textContent = new Intl.NumberFormat().format(target);
    };
    requestAnimationFrame(step);
  };

  const seen = new WeakSet();
  const io = new IntersectionObserver((entries)=>{
    entries.forEach(e => {
      if (e.isIntersecting && !seen.has(e.target)){
        seen.add(e.target);
        animateCount(e.target);
      }
    });
  }, { threshold: 0.3 });

  els.forEach(el => io.observe(el));
})();

// Auto-scroll to products section after search/filter
(function(){
  const target = document.getElementById('productsSection') || document.getElementById('products');
  const hasQuery = window.location.search.length > 1;
  const hasHashProducts = window.location.hash === '#products';
  if (target && (hasQuery || hasHashProducts)) {
    window.requestAnimationFrame(()=>{
      target.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
  }
})();
</script>
