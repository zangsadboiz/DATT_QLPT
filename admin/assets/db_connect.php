<?php
// admin/includes/db_connect.php

$servername = "localhost"; // Hoặc 127.0.0.1
$username = "root";        // Username MySQL của bạn
$password = "";            // Password MySQL của bạn
$dbname = "quanlydatphong"; // Tên database bạn đã import

// Tạo kết nối
$conn = new mysqli($servername, $username, $password, $dbname, 3307);

// Kiểm tra kết nối
if ($conn->connect_error) {
  die("Kết nối thất bại: " . $conn->connect_error);
}

// Set charset UTF-8 để hiển thị tiếng Việt
$conn->set_charset("utf8mb4");

// Bạn có thể muốn bắt đầu session ở đây để dùng chung
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
?>  