<?php
require_once __DIR__ . '/../../includes/db.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: index.php?error=invalid');
    exit;
}

$id = (int)$_GET['id'];

try {
    mysqli_begin_transaction($conn);

    /* 1) Lấy thông tin booking và khóa */
    $bkRes = mysqli_query($conn, "
        SELECT booking_id, room_id, status
        FROM bookings
        WHERE booking_id = $id
        FOR UPDATE
    ");

    if (!$bkRes || mysqli_num_rows($bkRes) === 0) {
        mysqli_rollback($conn);
        header("Location: index.php?error=not_found");
        exit;
    }

    $b = mysqli_fetch_assoc($bkRes);

    /* 2) Chỉ cho phép checkout khi status = CHECKED_IN */
    if ($b['status'] !== 'CHECKED_IN') {
        mysqli_rollback($conn);
        header("Location: index.php?error=checkout_invalid_status");
        exit;
    }

    /* 3) Cập nhật booking thành CHECKED_OUT
       KHÔNG đổi room_status - trạng thái phòng được tính động từ booking
    */
    mysqli_query($conn, "
        UPDATE bookings
        SET status = 'CHECKED_OUT',
            checked_out_at = NOW()
        WHERE booking_id = $id
          AND status = 'CHECKED_IN'
    ");

    if (mysqli_affected_rows($conn) === 0) {
        mysqli_rollback($conn);
        header("Location: index.php?error=checkout_failed");
        exit;
    }

    mysqli_commit($conn);
    header("Location: index.php?msg=checkout");
    exit;

} catch (Throwable $e) {
    mysqli_rollback($conn);
    header("Location: index.php?error=server_error");
    exit;
}
