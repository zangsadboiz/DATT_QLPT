<?php
require_once __DIR__ . '/../../includes/db.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: index.php?error=invalid');
    exit;
}

$id = (int)$_GET['id'];

/* điều hướng quay lại đúng trang (nếu cần) */
$return = $_GET['return'] ?? '';
$redirectBack = 'index.php';
if ($return === 'cancelled') $redirectBack = 'cancelled.php';

$today = date('Y-m-d');

try {
    mysqli_begin_transaction($conn);

    /* 1) Khóa booking */
    $bkRes = mysqli_query($conn, "
        SELECT booking_id, room_id, check_in, check_out, status
        FROM bookings
        WHERE booking_id = $id
        FOR UPDATE
    ");

    if (!$bkRes || mysqli_num_rows($bkRes) === 0) {
        mysqli_rollback($conn);
        header("Location: $redirectBack?error=not_found");
        exit;
    }

    $b = mysqli_fetch_assoc($bkRes);

    /* 2) Chỉ được nhận phòng khi booking đã xác nhận */
    if ($b['status'] !== 'CONFIRMED') {
        mysqli_rollback($conn);
        header("Location: $redirectBack?error=checkin_invalid_status");
        exit;
    }

    $room_id   = (int)$b['room_id'];
    $check_in  = $b['check_in'];
    $check_out = $b['check_out'] ?: '2099-12-31';

    /* 3) Cho phép nhận phòng từ ngày check_in trở đi (linh hoạt hơn)
       Trước đây: chỉ cho nhận khi today nằm trong [check_in, check_out)
       Bây giờ: cho nhận sớm hơn 3 ngày hoặc đúng ngày
    */
    $earliestCheckin = date('Y-m-d', strtotime($check_in . ' -3 days'));
    if ($today < $earliestCheckin) {
        mysqli_rollback($conn);
        header("Location: $redirectBack?error=checkin_too_early");
        exit;
    }

    /* 4) Khóa phòng + không cho nhận nếu phòng bảo trì */
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

    /* 5) Chặn nếu đã có booking CHECKED_IN khác trùng ngày */
    $conflict = mysqli_query($conn, "
        SELECT 1
        FROM bookings
        WHERE room_id = $room_id
          AND booking_id <> $id
          AND status = 'CHECKED_IN'
          AND check_in < '$check_out'
          AND (check_out IS NULL OR check_out > '$check_in')
        LIMIT 1
    ");

    if ($conflict && mysqli_num_rows($conflict) > 0) {
        mysqli_rollback($conn);
        header("Location: $redirectBack?error=checkin_conflict");
        exit;
    }

    /* 6) OK -> chuyển trạng thái sang CHECKED_IN
       KHÔNG đổi room_status - trạng thái phòng được tính động từ booking
    */
    mysqli_query($conn, "
        UPDATE bookings
        SET status = 'CHECKED_IN',
            checked_in_at = NOW()
        WHERE booking_id = $id
          AND status = 'CONFIRMED'
    ");

    if (mysqli_affected_rows($conn) === 0) {
        mysqli_rollback($conn);
        header("Location: $redirectBack?error=checkin_failed");
        exit;
    }

    mysqli_commit($conn);
    header("Location: $redirectBack?msg=checkin");
    exit;

} catch (Throwable $e) {
    mysqli_rollback($conn);
    header("Location: $redirectBack?error=server_error");
    exit;
}
