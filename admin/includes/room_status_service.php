<?php
// admin/includes/room_status_service.php
if (!isset($conn) || !($conn instanceof mysqli)) {
    die('DB connection not found');
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

/**
 * Set room to OCCUPIED if contract active
 */
function setRoomOccupied(mysqli $conn, int $room_id): void {
    if ($room_id <= 0) return;
    mysqli_query($conn, "UPDATE rooms SET room_status='OCCUPIED' WHERE room_id=$room_id LIMIT 1");
}

/**
 * Set room to VACANT if no active contract
 * (tránh trả phòng nhầm khi còn hợp đồng khác)
 */
function setRoomVacantIfNoActiveContract(mysqli $conn, int $room_id): void {
    if ($room_id <= 0) return;

    if (!hasTable($conn, 'contracts')) {
        // nếu không có contracts table, cứ về vacant
        mysqli_query($conn, "UPDATE rooms SET room_status='VACANT' WHERE room_id=$room_id LIMIT 1");
        return;
    }

    // xác định cột trạng thái hợp đồng
    $colStatus = null;
    if (hasColumn($conn, 'contracts', 'contract_status')) $colStatus = 'contract_status';
    else if (hasColumn($conn, 'contracts', 'status')) $colStatus = 'status';

    if (!$colStatus) {
        mysqli_query($conn, "UPDATE rooms SET room_status='VACANT' WHERE room_id=$room_id LIMIT 1");
        return;
    }

    $rs = mysqli_query($conn, "
        SELECT 1 FROM contracts
        WHERE room_id=$room_id AND $colStatus='ACTIVE'
        LIMIT 1
    ");

    if (!$rs || mysqli_num_rows($rs) === 0) {
        mysqli_query($conn, "UPDATE rooms SET room_status='VACANT' WHERE room_id=$room_id LIMIT 1");
    }
}
