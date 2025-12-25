<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config.php';

/**
 * Boot admin session - check if logged in
 */
function admin_session_boot(): void {
    // If not logged in, redirect to login
    if (!isset($_SESSION['user_id']) && !isset($_SESSION['admin_logged_in'])) {
        $loginUrl = ADMIN_BASE_PATH . '/login.php';
        $currentPath = $_SERVER['REQUEST_URI'] ?? '';
        
        // Don't redirect if already on login page
        if (strpos($currentPath, 'login.php') === false) {
            header('Location: ' . $loginUrl);
            exit;
        }
    }
}

/**
 * Check if user has specific role
 */
function has_role(string $role): bool {
    return ($_SESSION['role_name'] ?? '') === $role;
}

/**
 * Get current user ID
 */
function current_user_id(): int {
    return (int)($_SESSION['user_id'] ?? 0);
}
