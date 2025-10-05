<?php
require_once __DIR__ . '/../config.php';
header('Content-Type: application/json');

try {
    if (!isLoggedIn()) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }

    $user_id = (int)($_SESSION['user_id'] ?? 0);
    $role = getUserRole();

    // Basic user info
    $stmt = $pdo->prepare("SELECT id, name, email, phone, address, role, balance, created_at FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        http_response_code(404);
        echo json_encode(['error' => 'User not found']);
        exit;
    }

    $stats = [];

    if ($role === 'buyer') {
        // Purchases & delivery stats
        $q = $pdo->prepare("SELECT 
                COUNT(*) AS total_purchases,
                COALESCE(SUM(amount),0) AS total_spent,
                SUM(CASE WHEN delivery_status='pending' THEN 1 ELSE 0 END) AS pending_deliveries,
                SUM(CASE WHEN delivery_status='confirmed' THEN 1 ELSE 0 END) AS confirmed_deliveries
            FROM transactions WHERE buyer_id = ?");
        $q->execute([$user_id]);
        $stats = $q->fetch(PDO::FETCH_ASSOC) ?: [];
        // Wishlist & Cart counts (best-effort)
        try {
            $stats['wishlist_count'] = (int)$pdo->query("SELECT COUNT(*) FROM wishlist WHERE buyer_id=".$user_id)->fetchColumn();
        } catch (Throwable $e) { $stats['wishlist_count'] = 0; }
        try {
            $stats['cart_count'] = (int)$pdo->query("SELECT COUNT(*) FROM cart WHERE buyer_id=".$user_id)->fetchColumn();
        } catch (Throwable $e) { $stats['cart_count'] = 0; }
    } elseif ($role === 'seller') {
        // Product stats
        $q1 = $pdo->prepare("SELECT 
                COUNT(*) AS total_products,
                SUM(CASE WHEN status='approved' THEN 1 ELSE 0 END) AS approved_products,
                SUM(CASE WHEN status='pending' THEN 1 ELSE 0 END) AS pending_products
            FROM products WHERE seller_id = ?");
        $q1->execute([$user_id]);
        $pstats = $q1->fetch(PDO::FETCH_ASSOC) ?: [];

        // Sales stats
        $q2 = $pdo->prepare("SELECT 
                COUNT(*) AS total_sales,
                COALESCE(SUM(seller_amount),0) AS total_earnings,
                COALESCE(SUM(CASE WHEN status='released' THEN seller_amount ELSE 0 END),0) AS released_earnings,
                COALESCE(SUM(CASE WHEN status IN ('pending','approved') THEN seller_amount ELSE 0 END),0) AS pending_earnings
            FROM transactions WHERE seller_id = ?");
        $q2->execute([$user_id]);
        $sstats = $q2->fetch(PDO::FETCH_ASSOC) ?: [];

        $stats = array_merge($pstats, $sstats);
    }

    $resp = [
        'id' => (int)$user['id'],
        'name' => $user['name'],
        'email' => $user['email'],
        'phone' => $user['phone'] ?? '',
        'address' => $user['address'] ?? '',
        'role' => $user['role'],
        'balance' => (float)($user['balance'] ?? 0),
        'created_at' => $user['created_at'],
        'avatar' => 'https://ui-avatars.com/api/?name=' . urlencode($user['name']) . '&background=3f51b5&color=fff',
        'stats' => $stats,
    ];

    echo json_encode($resp);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error']);
}
