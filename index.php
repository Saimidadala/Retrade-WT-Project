<?php
require_once 'config.php';

$page_title = 'Home';

// Get search and filter parameters
$search = $_GET['search'] ?? '';
$category = $_GET['category'] ?? '';
$max_price = $_GET['max_price'] ?? '';
// Sorting: newest (default), price_asc, price_desc
$sort = $_GET['sort'] ?? 'newest';

// Build query
$query = "SELECT p.*, u.name as seller_name, u.email as seller_email 
          FROM products p 
          JOIN users u ON p.seller_id = u.id 
          WHERE p.status = 'approved'";
$params = [];

if (!empty($search)) {
    $query .= " AND (p.title LIKE ? OR p.description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($category)) {
    $query .= " AND p.category = ?";
    $params[] = $category;
}

if (!empty($max_price)) {
    $query .= " AND p.price <= ?";
    $params[] = $max_price;
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

$query .= " ORDER BY $orderBy";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$products = $stmt->fetchAll();

// Get categories for filter from categories table (show all available categories)
$stmt = $pdo->query("SELECT name FROM categories ORDER BY name");
$categories = $stmt->fetchAll(PDO::FETCH_COLUMN);

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
            <div class="mt-4">
                <a href="register.php" class="btn btn-light btn-lg me-3">
                    <i class="fas fa-user-plus"></i> Join Now
                </a>
                <a href="login.php" class="btn btn-outline-light btn-lg">
                    <i class="fas fa-sign-in-alt"></i> Sign In
                </a>
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

    <!-- Statistics -->
    <div class="row mb-5">
        <div class="col-md-4">
            <div class="card stat-card text-center">
                <div class="card-body">
                    <i class="fas fa-users fa-2x mb-3"></i>
                    <h3><?php echo number_format($stats['total_users']); ?></h3>
                    <p class="mb-0">Active Users</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card stat-card text-center">
                <div class="card-body">
                    <i class="fas fa-box fa-2x mb-3"></i>
                    <h3><?php echo number_format($stats['total_products']); ?></h3>
                    <p class="mb-0">Products Listed</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card stat-card text-center">
                <div class="card-body">
                    <i class="fas fa-handshake fa-2x mb-3"></i>
                    <h3><?php echo number_format($stats['total_transactions']); ?></h3>
                    <p class="mb-0">Safe Transactions</p>
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

    <!-- Products Grid -->
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2><i class="fas fa-shopping-bag text-primary"></i> Featured Products</h2>
                <span class="badge bg-secondary"><?php echo count($products); ?> products found</span>
            </div>
        </div>
    </div>

    <?php if (empty($products)): ?>
        <div class="row">
            <div class="col-12">
                <div class="text-center py-5">
                    <i class="fas fa-search fa-3x text-muted mb-3"></i>
                    <h4>No products found</h4>
                    <p class="text-muted">Try adjusting your search criteria or browse all products.</p>
                    <?php if (!empty($search) || !empty($category) || !empty($max_price)): ?>
                        <a href="index.php" class="btn btn-primary">View All Products</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="row">
            <?php foreach ($products as $product): ?>
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="card product-card h-100">
                        <div class="position-relative">
                            <?php if ($product['image'] && file_exists("assets/img/" . $product['image'])): ?>
                                <img src="assets/img/<?php echo htmlspecialchars($product['image']); ?>" 
                                     class="card-img-top" style="height: 200px; object-fit: cover;" 
                                     alt="<?php echo htmlspecialchars($product['title']); ?>" loading="lazy">
                            <?php else: ?>
                                <div class="product-image">
                                    <i class="fas fa-image"></i>
                                </div>
                            <?php endif; ?>
                            <?php if ($product['category']): ?>
                                <span class="badge bg-primary position-absolute top-0 start-0 m-2">
                                    <?php echo htmlspecialchars($product['category']); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="card-body d-flex flex-column">
                            <h5 class="card-title"><?php echo htmlspecialchars($product['title']); ?></h5>
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
                                <div class="d-grid">
                                    <a href="product_details.php?id=<?php echo $product['id']; ?>" 
                                       class="btn btn-primary">
                                        <i class="fas fa-eye"></i> View Details
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- Call to Action -->
    <?php if (!isLoggedIn()): ?>
        <div class="row mt-5">
            <div class="col-12">
                <div class="card bg-light">
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

<?php include 'includes/footer.php'; ?>
