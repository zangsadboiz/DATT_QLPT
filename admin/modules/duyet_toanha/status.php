<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';

$role = $_SESSION['role_name'] ?? '';
if ($role !== 'ADMIN') {
    header('Location: /quanlyphongtro/admin/index.php?error=no_permission');
    exit;
}

$id = (int)($_GET['id'] ?? 0);
$s  = $_GET['s'] ?? '';

if ($id <= 0 || !in_array($s, ['PENDING','APPROVED','HIDDEN'], true)) {
    header('Location: index.php?error=invalid');
    exit;
}

mysqli_query($conn, "UPDATE buildings SET building_status='$s' WHERE building_id=$id LIMIT 1");
header('Location: index.php?msg=updated');
exit;
