<?php
if (!isset($pdo)) { require_once __DIR__ . '/../config.php'; }
$role = getUserRole();
?>
<aside class="sidebar" id="appSidebar">
  <div class="brand">
    <i class="fas fa-layer-group"></i>
    <span>Retrade</span>
  </div>

  <div class="search">
    <input type="text" placeholder="Search..." aria-label="Search">
    <i class="fas fa-search"></i>
  </div>

  <div class="nav-section">
    <div class="nav-section-title">Main</div>
    <ul class="nav-list">
      <li><a href="index.php" class="<?php echo ($page_title ?? '') === 'Home' ? 'active' : ''; ?>"><i class="fas fa-home"></i> <span>Home</span></a></li>
      <?php if (isLoggedIn()): ?>
        <li><a href="dashboard.php" class="<?php echo ($page_title ?? '') === 'Dashboard' ? 'active' : ''; ?>"><i class="fas fa-gauge"></i> <span>Dashboard</span></a></li>
      <?php endif; ?>
    </ul>
  </div>

  <?php if (isLoggedIn() && $role === 'seller'): ?>
  <div class="nav-section">
    <div class="nav-section-title">Seller</div>
    <ul class="nav-list">
      <li><a href="add_product.php" class="<?php echo ($page_title ?? '') === 'Add Product' ? 'active' : ''; ?>"><i class="fas fa-plus"></i> <span>Add Product</span></a></li>
    </ul>
  </div>
  <?php endif; ?>

  <?php if (isLoggedIn() && $role === 'admin'): ?>
  <div class="nav-section">
    <div class="nav-section-title">Admin</div>
    <ul class="nav-list">
      <li><a href="admin_panel.php" class="<?php echo ($page_title ?? '') === 'Admin' ? 'active' : ''; ?>"><i class="fas fa-sliders-h"></i> <span>Dashboard</span></a></li>
      <li><a href="manage_users.php"><i class="fas fa-users"></i> <span>Manage Users</span></a></li>
      <li><a href="approve_product.php"><i class="fas fa-check-circle"></i> <span>Approve Products</span></a></li>
      <li><a href="manage_payments.php"><i class="fas fa-credit-card"></i> <span>Manage Payments</span></a></li>
    </ul>
  </div>
  <?php endif; ?>

  <div class="nav-section">
    <div class="nav-section-title">Account</div>
    <ul class="nav-list">
      <?php if (isLoggedIn()): ?>
        <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a></li>
      <?php else: ?>
        <li><a href="login.php"><i class="fas fa-sign-in-alt"></i> <span>Login</span></a></li>
        <li><a href="register.php"><i class="fas fa-user-plus"></i> <span>Register</span></a></li>
      <?php endif; ?>
    </ul>
  </div>
</aside>
<div class="overlay" id="sidebarOverlay"></div>
