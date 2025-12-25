<?php
/**
 * Helper functions cho booking/contract
 * Include file này ở đầu các trang cần kiểm tra booking quá hạn
 */

/**
 * Tự động hủy các booking PENDING quá 30 phút
 * @param mysqli $conn
 * @return int Số booking đã hủy
 */
function auto_cancel_expired_bookings($conn) {
    // Hủy các booking PENDING quá 30 phút
    $result = mysqli_query($conn, "
        UPDATE bookings 
        SET status = 'CANCELLED', 
            cancelled_at = NOW(),
            cancel_reason = 'Tự động hủy do quá 30 phút không được duyệt'
        WHERE status = 'PENDING' 
          AND created_at < DATE_SUB(NOW(), INTERVAL 30 MINUTE)
    ");
    
    return $result ? mysqli_affected_rows($conn) : 0;
}

/**
 * Lấy thời gian còn lại trước khi booking bị hủy
 * @param string $createdAt Thời gian tạo booking
 * @return int Số phút còn lại (0 nếu đã quá hạn)
 */
function get_booking_remaining_minutes($createdAt) {
    $created = strtotime($createdAt);
    $expireAt = $created + (30 * 60); // 30 phút
    $remaining = $expireAt - time();
    
    return max(0, (int)ceil($remaining / 60));
}

/**
 * Kiểm tra xem booking có đang trong thời gian chờ duyệt không
 * @param string $status
 * @param string $createdAt
 * @return bool
 */
function is_booking_pending_valid($status, $createdAt) {
    if ($status !== 'PENDING') return false;
    return get_booking_remaining_minutes($createdAt) > 0;
}
