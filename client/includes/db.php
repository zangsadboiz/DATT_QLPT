<?php
$host = 'localhost';
$user = 'root';
$pass = '';
$db   = 'quanlyphongtro';

$conn = mysqli_connect($host, $user, $pass, $db, 3307);

if (!$conn) {
    die('Lỗi kết nối CSDL');
}
