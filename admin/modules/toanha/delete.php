<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../includes/db.php';

$role = $_SESSION['role_name'] ?? 'ADMIN';
$user_id = (int)($_SESSION['user_id'] ?? 0);

function hasColumn(mysqli $conn, string $table, string $col): bool {
    $t = mysqli_real_escape_string($conn, $table);
    $c = mysqli_real_escape_string($conn, $col);
    $rs = mysqli_query($conn, "
        SELECT COUNT(*) AS cnt
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = '$t'
          AND COLUMN_NAME = '$c'
    ");
    $row = $rs ? mysqli_fetch_assoc($rs) : null;
    return (int)($row['cnt'] ?? 0) > 0;
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: index.php');
    exit;
}
$id = (int)$_GET['id'];

$whereOwner = "";
if ($role === 'LANDLORD') $whereOwner = "AND owner_user_id=$user_id";

/* verify permission */
$chk = mysqli_query($conn, "SELECT building_id FROM buildings WHERE building_id=$id $whereOwner LIMIT 1");
if (!$chk || mysqli_num_rows($chk) === 0) {
    header('Location: index.php?error=no_permission');
    exit;
}

/* check rooms tồn tại (chỉ tính phòng chưa xóa mềm nếu rooms có deleted_at) */
$HAS_ROOM_SOFT = hasColumn($conn, 'rooms', 'deleted_at');
$roomWhere = $HAS_ROOM_SOFT ? "AND deleted_at IS NULL" : "";

$c = mysqli_query($conn, "SELECT COUNT(*) AS c FROM rooms WHERE building_id=$id $roomWhere");
$cnt = $c ? (int)(mysqli_fetch_assoc($c)['c'] ?? 0) : 0;

if ($cnt > 0) {
    header('Location: index.php?error=has_rooms');
    exit;
}

mysqli_query($conn, "DELETE FROM buildings WHERE building_id=$id $whereOwner LIMIT 1");

header('Location: index.php?msg=deleted');
exit;
