<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'retrade_db');
define('PEXELS_API_KEY', 'yzCDMU4dIp1bLxi3i97HsIMptIbEA5vDnzqsW9jVcyWAejxVkndAuoCK');

// Create database connection
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Start session if not already started with safer cookie params
if (session_status() == PHP_SESSION_NONE) {
    // Ensure cookies are scoped to the whole app and are httpOnly
    $params = session_get_cookie_params();
    $cookieOptions = [
        'lifetime' => 0,
        'path' => '/',
        'domain' => $params['domain'] ?: '',
        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on', // false on localhost
        'httponly' => true,
        'samesite' => 'Lax',
    ];
    if (PHP_VERSION_ID >= 70300) {
        session_set_cookie_params($cookieOptions);
    } else {
        // Fallback for older PHP (without samesite support)
        session_set_cookie_params($cookieOptions['lifetime'], $cookieOptions['path'], $cookieOptions['domain'], $cookieOptions['secure'], $cookieOptions['httponly']);
    }
    session_start();
}

// WebSocket configuration
// Change the secret in production. Keep it in sync with ws-server/server.js WS_SHARED_SECRET.
if (!defined('WS_SERVER_URL')) {
    define('WS_SERVER_URL', 'http://localhost:3001');
}
if (!defined('WS_SHARED_SECRET')) {
    define('WS_SHARED_SECRET', 'change_this_ws_secret');
}

// External photo provider API keys (optional)
// Prefer setting via environment variables PEXELS_API_KEY / UNSPLASH_ACCESS_KEY
if (!defined('PEXELS_API_KEY')) {
    define('PEXELS_API_KEY', getenv('PEXELS_API_KEY') ?: '');
}
if (!defined('UNSPLASH_ACCESS_KEY')) {
    define('UNSPLASH_ACCESS_KEY', getenv('UNSPLASH_ACCESS_KEY') ?: '');
}

// Helper functions
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function getUserRole() {
    return $_SESSION['user_role'] ?? null;
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit();
    }
}

function requireRole($role) {
    requireLogin();
    if (getUserRole() !== $role) {
        header('Location: dashboard.php');
        exit();
    }
}

function sanitizeInput($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

function formatPrice($price) {
    return '₹' . number_format($price, 2);
}

function getTimeAgo($datetime) {
    $time = time() - strtotime($datetime);
    
    if ($time < 60) return 'just now';
    if ($time < 3600) return floor($time/60) . ' minutes ago';
    if ($time < 86400) return floor($time/3600) . ' hours ago';
    if ($time < 2592000) return floor($time/86400) . ' days ago';
    if ($time < 31536000) return floor($time/2592000) . ' months ago';
    return floor($time/31536000) . ' years ago';
}
?>
