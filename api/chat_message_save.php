<?php
require_once __DIR__ . '/../config.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$negotiationId = (int)($_POST['negotiation_id'] ?? 0);
$senderId = (int)($_POST['sender_id'] ?? 0);
$message = trim((string)($_POST['message'] ?? ''));

if (!$negotiationId || !$senderId || $message === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Missing fields']);
    exit;
}

// Auth: allow either session user or internal shared-secret header
$internal = false;
$authHeader = $_SERVER['HTTP_X_WS_INTERNAL'] ?? '';
if ($authHeader && hash_equals($authHeader, WS_SHARED_SECRET)) {
    $internal = true;
} else {
    if (!isLoggedIn() || (int)($_SESSION['user_id'] ?? 0) !== $senderId) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
}

try {
    // Verify membership
    $stmt = $pdo->prepare('SELECT product_id, seller_id, buyer_id FROM negotiations WHERE id = ?');
    $stmt->execute([$negotiationId]);
    $n = $stmt->fetch();
    if (!$n) {
        http_response_code(404);
        echo json_encode(['error' => 'Negotiation not found']);
        exit;
    }
    if ($senderId !== (int)$n['buyer_id'] && $senderId !== (int)$n['seller_id']) {
        http_response_code(403);
        echo json_encode(['error' => 'Not a participant']);
        exit;
    }

    // Insert message
    $stmt = $pdo->prepare('INSERT INTO messages (negotiation_id, sender_id, message) VALUES (?,?,?)');
    $stmt->execute([$negotiationId, $senderId, $message]);

    echo json_encode(['ok' => true]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'DB error']);
}
