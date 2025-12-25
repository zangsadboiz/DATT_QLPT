<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../includes/db.php';

$role = $_SESSION['role_name'] ?? '';
if ($role !== 'ADMIN') {
    header('Location: index.php?error=no_permission');
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

if (!hasColumn($conn, 'buildings', 'building_status')) {
    header('Location: index.php?error=no_permission');
    exit;
}

$id = (int)($_GET['id'] ?? 0);
$s  = $_GET['s'] ?? '';

if ($id <= 0 || !in_array($s, ['PENDING','APPROVED','HIDDEN'], true)) {
    header('Location: index.php');
    exit;
}

mysqli_query($conn, "UPDATE buildings SET building_status='$s' WHERE building_id=$id LIMIT 1");
header('Location: index.php?msg=status');
exit;
