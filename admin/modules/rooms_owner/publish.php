<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';

$role = $_SESSION['role_name'] ?? '';
$user_id = (int)($_SESSION['user_id'] ?? 0);

if ($role !== 'LANDLORD' || $user_id <= 0) {
    header('Location: /quanlyphongtro/admin/index.php?error=no_permission');
    exit;
}

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

if (!hasColumn($conn, 'rooms', 'publish_status')) {
    header('Location: index.php?error=missing_publish_status');
    exit;
}

$id = (int)($_GET['id'] ?? 0);
$s  = $_GET['s'] ?? '';

if ($id <= 0 || !in_array($s, ['PENDING','HIDDEN'], true)) {
    header('Location: index.php?error=invalid');
    exit;
}

mysqli_query($conn, "
    UPDATE rooms r
    JOIN buildings b ON b.building_id = r.building_id
    SET r.publish_status = '$s'
    WHERE r.room_id = $id
      AND b.owner_user_id = $user_id
      AND r.deleted_at IS NULL
    LIMIT 1
");

header('Location: index.php?msg=publish');
exit;
