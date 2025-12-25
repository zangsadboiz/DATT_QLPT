<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../includes/db.php';

$role = $_SESSION['role_name'] ?? 'ADMIN';
if ($role !== 'ADMIN') { header('Location: /quanlyphongtro/admin/index.php'); exit; }

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { header('Location: index.php'); exit; }

$roleRs = mysqli_query($conn, "SELECT role_id FROM roles WHERE role_name='STUDENT' LIMIT 1");
$studentRoleId = (int)(mysqli_fetch_assoc($roleRs)['role_id'] ?? 0);
if ($studentRoleId <= 0) { header('Location: index.php'); exit; }

$res = mysqli_query($conn, "SELECT is_active FROM users WHERE user_id=$id AND role_id=$studentRoleId LIMIT 1");
if (!$res || mysqli_num_rows($res) === 0) { header('Location: index.php'); exit; }
$row = mysqli_fetch_assoc($res);

$new = ((int)$row['is_active'] === 1) ? 0 : 1;

mysqli_query($conn, "UPDATE users SET is_active=$new WHERE user_id=$id AND role_id=$studentRoleId LIMIT 1");

// Redirect về trang detail nếu có tham số from=detail, hoặc về index
$from = $_GET['from'] ?? '';
if ($from === 'detail') {
    header("Location: detail.php?id=$id&msg=toggled");
} else {
    header('Location: index.php?msg=toggled');
}
exit;
