<?php
require_once __DIR__ . '/../../includes/db.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: index.php?error=invalid');
    exit;
}

$id = (int)$_GET['id'];

try {
    mysqli_begin_transaction($conn);

    /* Lấy thông tin booking trước khi hủy */
    $bkRes = mysqli_query($conn, "
        SELECT booking_id, room_id, status
        FROM bookings
        WHERE booking_id = $id
          AND status IN ('PENDING','CONFIRMED')
        FOR UPDATE
    ");

    if (!$bkRes || mysqli_num_rows($bkRes) === 0) {
        mysqli_rollback($conn);
        header('Location: index.php?error=cannot_cancel');
        exit;
    }

    /* Hủy booking - set cancelled_at để hỗ trợ khôi phục trong 15 phút */
    mysqli_query($conn, "
        UPDATE bookings
        SET status = 'CANCELLED',
            cancelled_at = NOW()
        WHERE booking_id = $id
          AND status IN ('PENDING','CONFIRMED')
    ");

    if (mysqli_affected_rows($conn) === 0) {
        mysqli_rollback($conn);
        header('Location: index.php?error=cannot_cancel');
        exit;
    }

    /* KHÔNG cập nhật room_status - trạng thái phòng được tính động từ booking */

    mysqli_commit($conn);
    header('Location: index.php?msg=cancelled');
    exit;

} catch (Throwable $e) {
    mysqli_rollback($conn);
    header('Location: index.php?error=server_error');
    exit;
}
