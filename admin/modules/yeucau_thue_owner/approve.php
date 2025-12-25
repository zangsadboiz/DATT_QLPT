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
if ($id <= 0) {
    header('Location: index.php?error=invalid');
    exit;
}

try {
    mysqli_begin_transaction($conn);
    
    // Lấy thông tin booking
    $bookingRes = mysqli_query($conn, "
        SELECT bk.booking_id, bk.room_id, bk.check_in, bk.check_out, bk.status,
               b.owner_id
        FROM bookings bk
        JOIN rooms r ON r.room_id = bk.room_id
        JOIN buildings b ON b.building_id = r.building_id
        WHERE bk.booking_id = $id
          AND bk.status = 'PENDING'
          AND b.owner_id = $user_id
        FOR UPDATE
    ");
    
    if (!$bookingRes || mysqli_num_rows($bookingRes) === 0) {
        mysqli_rollback($conn);
        header('Location: index.php?status=PENDING&error=not_found');
        exit;
    }
    
    $b = mysqli_fetch_assoc($bookingRes);
    $room_id = (int)$b['room_id'];
    $check_in = $b['check_in'];
    $check_out = $b['check_out'] ?: '2099-12-31';
    
    // Kiểm tra xung đột với booking CONFIRMED/CHECKED_IN khác
    $conflictRes = mysqli_query($conn, "
        SELECT 1 FROM bookings
        WHERE room_id = $room_id
          AND booking_id <> $id
          AND status IN ('CONFIRMED','CHECKED_IN')
          AND check_in < '$check_out'
          AND (check_out IS NULL OR check_out > '$check_in')
        LIMIT 1
    ");
    
    if ($conflictRes && mysqli_num_rows($conflictRes) > 0) {
        mysqli_rollback($conn);
        header('Location: index.php?status=PENDING&error=conflict');
        exit;
    }
    
    // Kiểm tra phòng không bảo trì
    $roomRes = mysqli_query($conn, "SELECT room_status FROM rooms WHERE room_id = $room_id");
    $room = mysqli_fetch_assoc($roomRes);
    if ($room && $room['room_status'] === 'MAINTENANCE') {
        mysqli_rollback($conn);
        header('Location: index.php?status=PENDING&error=maintenance');
        exit;
    }
    
    // OK -> Duyệt booking
    mysqli_query($conn, "
        UPDATE bookings
        SET status = 'CONFIRMED',
            cancelled_at = NULL,
            confirmed_at = NOW()
        WHERE booking_id = $id
          AND status = 'PENDING'
    ");
    
    if (mysqli_affected_rows($conn) === 0) {
        mysqli_rollback($conn);
        header('Location: index.php?status=PENDING&error=cannot_approve');
        exit;
    }
    
    mysqli_commit($conn);
    header('Location: index.php?status=PENDING&msg=approved');
    exit;

} catch (Throwable $e) {
    mysqli_rollback($conn);
    header('Location: index.php?status=PENDING&error=server_error');
    exit;
}
