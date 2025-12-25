<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
$host = 'localhost';
$user = 'root';
$pass = '';
$db   = 'quanlyphongtro';

$conn = mysqli_connect($host, $user, $pass, $db, 3307);


if (!$conn) {
    die("Không thể kết nối database: " . mysqli_connect_error());
}

// Set charset
mysqli_set_charset($conn, 'utf8mb4');
