<?php
require_once __DIR__ . '/../config.php';
header('Content-Type: application/json');

if (!isLoggedIn() || getUserRole() !== 'buyer') {
  http_response_code(401);
  echo json_encode(['error' => 'Login as buyer required']);
  exit;
}

$buyerId = (int)($_SESSION['user_id'] ?? 0);
$pid = (int)($_POST['product_id'] ?? 0);
if ($pid <= 0) { http_response_code(400); echo json_encode(['error' => 'Invalid product_id']); exit; }

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

// Toggle
$stmt = $pdo->prepare('SELECT id FROM wishlist WHERE buyer_id = ? AND product_id = ?');
$stmt->execute([$buyerId, $pid]);
$row = $stmt->fetch();

if ($row) {
  $del = $pdo->prepare('DELETE FROM wishlist WHERE id = ?');
  $del->execute([$row['id']]);
  echo json_encode(['status' => 'removed']);
} else {
  $ins = $pdo->prepare('INSERT INTO wishlist (buyer_id, product_id) VALUES (?, ?)');
  $ins->execute([$buyerId, $pid]);
  echo json_encode(['status' => 'added']);
}
