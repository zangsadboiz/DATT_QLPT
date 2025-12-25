<?php
declare(strict_types=1);

// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'quanlyphongtro');

// Paths
define('ADMIN_BASE_PATH', '/quanlyphongtro/admin');
define('CLIENT_BASE_PATH', '/quanlyphongtro/client');
define('UPLOAD_PATH', __DIR__ . '/../../uploads');

// Error reporting (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', '1');
