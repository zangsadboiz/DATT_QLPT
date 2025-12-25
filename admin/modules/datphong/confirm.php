<?php
require_once __DIR__ . '/../../includes/db.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: index.php?error=invalid');
    exit;
}

$id = (int)$_GET['id'];

/* điều hướng quay lại đúng trang */
$return = $_GET['return'] ?? '';
$redirectBack = 'index.php';
if ($return === 'cancelled') $redirectBack = 'cancelled.php';

try {
    mysqli_begin_transaction($conn);

    /* 1) Khoá booking */
    $bookingRes = mysqli_query($conn, "
        SELECT booking_id, room_id, check_in, check_out, status, cancelled_at
        FROM bookings
        WHERE booking_id = $id
        FOR UPDATE
    ");

    if (!$bookingRes || mysqli_num_rows($bookingRes) === 0) {
        mysqli_rollback($conn);
        header("Location: $redirectBack?error=not_found");
        exit;
    }

    $b = mysqli_fetch_assoc($bookingRes);

    /* 2) Chỉ cho:
          - PENDING -> CONFIRMED
          - CANCELLED -> CONFIRMED (khôi phục) nhưng phải trong 15 phút
    */
    if (!in_array($b['status'], ['PENDING', 'CANCELLED'], true)) {
        mysqli_rollback($conn);
        header("Location: $redirectBack?error=invalid_status");
        exit;
    }

    $room_id   = (int)$b['room_id'];
    $check_in  = $b['check_in'];
    $check_out = $b['check_out'] ?: '2099-12-31'; // Mặc định xa nếu NULL (thuê vô thời hạn)

    /* 3) Nếu là KHÔI PHỤC thì kiểm tra còn trong 15 phút hay không */
    if ($b['status'] === 'CANCELLED') {
        if (empty($b['cancelled_at'])) {
            mysqli_rollback($conn);
            header("Location: $redirectBack?error=restore_no_time");
            exit;
        }

        $expiredRes = mysqli_query($conn, "
            SELECT (NOW() > DATE_ADD('{$b['cancelled_at']}', INTERVAL 15 MINUTE)) AS expired
        ");
        $expiredRow = mysqli_fetch_assoc($expiredRes);
        if (!empty($expiredRow['expired'])) {
            mysqli_rollback($conn);
            header("Location: $redirectBack?error=restore_expired");
            exit;
        }
    }

    /* 4) Khoá phòng + chặn phòng bảo trì */
    $roomRes = mysqli_query($conn, "
        SELECT room_status
        FROM rooms
        WHERE room_id = $room_id
        FOR UPDATE
    ");

    if (!$roomRes || mysqli_num_rows($roomRes) === 0) {
        mysqli_rollback($conn);
        header("Location: $redirectBack?error=room_not_found");
        exit;
    }

    $room = mysqli_fetch_assoc($roomRes);
    if ($room['room_status'] === 'MAINTENANCE') {
        mysqli_rollback($conn);
        header("Location: $redirectBack?error=room_maintenance");
        exit;
    }

    /* 5) Kiểm tra xung đột: có booking khác trùng khoảng thời gian không?
       Logic: 2 khoảng thời gian [A, B) và [C, D) trùng nhau nếu A < D AND C < B
       Xử lý NULL check_out = thuê vô thời hạn
       Chỉ chặn CONFIRMED/CHECKED_IN, không chặn PENDING (cho phép nhiều người đặt cùng lúc)
    */
    $conflictRes = mysqli_query($conn, "
        SELECT 1
        FROM bookings
        WHERE room_id = $room_id
          AND booking_id <> $id
          AND status IN ('CONFIRMED','CHECKED_IN')
          AND check_in < '$check_out'
          AND (check_out IS NULL OR check_out > '$check_in')
        LIMIT 1
    ");

    if ($conflictRes && mysqli_num_rows($conflictRes) > 0) {
        mysqli_rollback($conn);
        header("Location: $redirectBack?error=conflict");
        exit;
    }

    /* 6) OK -> xác nhận / khôi phục
       KHÔNG đổi room_status - trạng thái phòng được tính động từ booking
    */
    mysqli_query($conn, "
        UPDATE bookings
        SET status = 'CONFIRMED',
            cancelled_at = NULL,
            confirmed_at = NOW()
        WHERE booking_id = $id
          AND status IN ('PENDING','CANCELLED')
    ");

    if (mysqli_affected_rows($conn) === 0) {
        mysqli_rollback($conn);
        header("Location: $redirectBack?error=cannot_confirm");
        exit;
    }

    mysqli_commit($conn);
    header("Location: $redirectBack?msg=confirmed");
    exit;

} catch (Throwable $e) {
    mysqli_rollback($conn);
    header("Location: $redirectBack?error=server_error");
    exit;
}
