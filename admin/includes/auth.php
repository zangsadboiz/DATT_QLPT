<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/session.php';

/**
 * Check if user is authenticated
 */
function is_authenticated(): bool {
    return isset($_SESSION['user_id']) && $_SESSION['user_id'] > 0;
}

/**
 * Require authentication - redirect to login if not logged in
 */
function require_auth(): void {
    if (!is_authenticated()) {
        header('Location: ' . ADMIN_BASE_PATH . '/login.php');
        exit;
    }
}

/**
 * Require landlord login - redirect if not landlord
 */
function require_landlord_login(): void {
    require_auth();
    $role = $_SESSION['role_name'] ?? '';
    if ($role !== 'LANDLORD' && $role !== 'ADMIN') {
        header('Location: ' . ADMIN_BASE_PATH . '/login.php?error=unauthorized');
        exit;
    }
}

/**
 * Redirect helper for admin
 */
function admin_redirect(string $path, array $params = []): void {
    $url = ADMIN_BASE_PATH . '/' . ltrim($path, '/');
    if (!empty($params)) {
        $url .= '?' . http_build_query($params);
    }
    header('Location: ' . $url);
    exit;
}

// Auto-require auth for admin pages (except login/logout)
$currentFile = basename($_SERVER['PHP_SELF'] ?? '');
if (!in_array($currentFile, ['login.php', 'logout.php', 'register.php'])) {
    require_auth();
}
