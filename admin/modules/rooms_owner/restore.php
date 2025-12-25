<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../includes/db.php';

$role = $_SESSION['role_name'] ?? '';
$user_id = (int)($_SESSION['user_id'] ?? 0);

if ($role !== 'LANDLORD' || $user_id <= 0) {
    header('Location: /quanlyphongtro/admin/index.php');
    exit;
}

function hasTable(mysqli $conn, string $table): bool {
    $t = mysqli_real_escape_string($conn, $table);
    $rs = mysqli_query($conn, "
        SELECT COUNT(*) AS cnt
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = '$t'
    ");
    $row = $rs ? mysqli_fetch_assoc($rs) : null;
    return (int)($row['cnt'] ?? 0) > 0;
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

$HAS_SOFT = hasColumn($conn, 'rooms', 'deleted_at') && hasColumn($conn, 'rooms', 'deleted_by');
if (!$HAS_SOFT) {
    header('Location: trash.php?error=missing_softdelete');
    exit;
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: trash.php');
    exit;
}
$room_id = (int)$_GET['id'];

$res = mysqli_query($conn, "
    SELECT r.room_id, r.room_code, r.deleted_at
    FROM rooms r
    JOIN buildings b ON b.building_id = r.building_id
    WHERE r.room_id = $room_id
      AND b.owner_user_id = $user_id
      AND r.deleted_at IS NOT NULL
    LIMIT 1
");
if (!$res || mysqli_num_rows($res) === 0) {
    header('Location: trash.php?error=not_found');
    exit;
}

$row = mysqli_fetch_assoc($res);

/* hết hạn khôi phục sau 15 ngày */
$expired = mysqli_query($conn, "
    SELECT 1
    FROM rooms
    WHERE room_id = $room_id
      AND deleted_at < (NOW() - INTERVAL 15 DAY)
    LIMIT 1
");
if ($expired && mysqli_num_rows($expired) > 0) {
    header('Location: trash.php?error=restore_expired');
    exit;
}

/* kiểm tra xung đột: booking/hợp đồng đang hoạt động (nếu có) */
if (hasTable($conn, 'bookings') && hasColumn($conn, 'bookings', 'status')) {
    $busyBooking = mysqli_query($conn, "
        SELECT 1
        FROM bookings
        WHERE room_id = $room_id
          AND status IN ('PENDING','CONFIRMED','CHECKED_IN')
        LIMIT 1
    ");
    if ($busyBooking && mysqli_num_rows($busyBooking) > 0) {
        header('Location: trash.php?error=restore_conflict');
        exit;
    }
}

if (hasTable($conn, 'contracts')) {
    if (hasColumn($conn, 'contracts', 'contract_status')) {
        $busyContract = mysqli_query($conn, "
            SELECT 1 FROM contracts
            WHERE room_id = $room_id AND contract_status='ACTIVE'
            LIMIT 1
        ");
        if ($busyContract && mysqli_num_rows($busyContract) > 0) {
            header('Location: trash.php?error=restore_conflict');
            exit;
        }
    } elseif (hasColumn($conn, 'contracts', 'status')) {
        $busyContract = mysqli_query($conn, "
            SELECT 1 FROM contracts
            WHERE room_id = $room_id AND status='ACTIVE'
            LIMIT 1
        ");
        if ($busyContract && mysqli_num_rows($busyContract) > 0) {
            header('Location: trash.php?error=restore_conflict');
            exit;
        }
    }
}

/* restore */
mysqli_query($conn, "
    UPDATE rooms r
    JOIN buildings b ON b.building_id = r.building_id
    SET r.deleted_at = NULL,
        r.deleted_by = NULL,
        r.room_status = 'VACANT'
    WHERE r.room_id = $room_id
      AND b.owner_user_id = $user_id
    LIMIT 1
");

header('Location: trash.php?msg=restored');
exit;
