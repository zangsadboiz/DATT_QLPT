<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';

$role = $_SESSION['role_name'] ?? '';
$user_id = (int)($_SESSION['user_id'] ?? 0);

if ($role !== 'LANDLORD' || $user_id <= 0) {
    header('Location: /quanlyphongtro/admin/index.php?error=no_permission');
    exit;
}

$id = (int)($_GET['id'] ?? 0);
$s  = $_GET['s'] ?? '';

if ($id <= 0 || !in_array($s, ['PENDING','HIDDEN'], true)) {
    header('Location: index.php?error=invalid');
    exit;
}

mysqli_query($conn, "
    UPDATE buildings
    SET building_status = '$s'
    WHERE building_id = $id AND owner_user_id = $user_id
    LIMIT 1
");

header('Location: index.php?msg=owner_status');
exit;
