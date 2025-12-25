<?php
/**
 * Booking Helper Functions
 * Các hàm hỗ trợ cho module đặt phòng
 */

/**
 * Kiểm tra phòng có sẵn trong khoảng thời gian không
 * 
 * @param mysqli $conn Database connection
 * @param int $room_id ID phòng cần kiểm tra
 * @param string $check_in Ngày nhận phòng (Y-m-d)
 * @param string $check_out Ngày trả phòng (Y-m-d)
 * @param int|null $exclude_booking_id Loại trừ booking ID (dùng khi edit)
 * @return bool True nếu phòng trống, False nếu đã có booking
 */
function isRoomAvailable($conn, $room_id, $check_in, $check_out, $exclude_booking_id = null) {
    $room_id = (int)$room_id;
    $check_in = mysqli_real_escape_string($conn, $check_in);
    $check_out = mysqli_real_escape_string($conn, $check_out);
    
    $sql = "
        SELECT COUNT(*) as count
        FROM bookings
        WHERE room_id = $room_id
          AND status IN ('PENDING', 'DEPOSIT_PAID', 'CHECKED_IN')
          AND check_in < '$check_out'
          AND check_out > '$check_in'
    ";
    
    if ($exclude_booking_id !== null) {
        $exclude_booking_id = (int)$exclude_booking_id;
        $sql .= " AND booking_id != $exclude_booking_id";
    }
    
    $result = mysqli_query($conn, $sql);
    if (!$result) return false;
    
    $row = mysqli_fetch_assoc($result);
    return (int)($row['count'] ?? 0) === 0;
}

/**
 * Kiểm tra có thể hủy booking không
 * Chỉ cho phép hủy khi status là PENDING (chưa thanh toán)
 * 
 * @param array $booking Booking data
 * @return bool
 */
function canCancelBooking($booking) {
    $status = $booking['status'] ?? '';
    return $status === 'PENDING';
}

/**
 * Kiểm tra có thể xác nhận booking không
 * Chỉ cho phép confirm khi status là PENDING
 * 
 * @param array $booking Booking data
 * @return bool
 */
function canConfirmBooking($booking) {
    $status = $booking['status'] ?? '';
    return $status === 'PENDING';
}

/**
 * Kiểm tra có thể check-in không
 * Chỉ cho phép check-in khi status là CONFIRMED
 * 
 * @param array $booking Booking data
 * @return bool
 */
function canCheckinBooking($booking) {
    $status = $booking['status'] ?? '';
    return $status === 'CONFIRMED';
}

/**
 * Kiểm tra có thể checkout không
 * Chỉ cho phép checkout khi status là CHECKED_IN
 * 
 * @param array $booking Booking data
 * @return bool
 */
function canCheckoutBooking($booking) {
    $status = $booking['status'] ?? '';
    return $status === 'CHECKED_IN';
}

/**
 * Cập nhật trạng thái phòng
 * 
 * @param mysqli $conn Database connection
 * @param int $room_id ID phòng
 * @param string $new_status Trạng thái mới (VACANT, OCCUPIED, MAINTENANCE, LOCKED)
 * @return bool True nếu thành công
 */
function updateRoomStatus($conn, $room_id, $new_status) {
    $room_id = (int)$room_id;
    $allowed_statuses = ['VACANT', 'OCCUPIED', 'MAINTENANCE', 'LOCKED'];
    
    if (!in_array($new_status, $allowed_statuses, true)) {
        return false;
    }
    
    $new_status = mysqli_real_escape_string($conn, $new_status);
    
    $sql = "UPDATE rooms SET room_status = '$new_status' WHERE room_id = $room_id";
    return mysqli_query($conn, $sql) !== false;
}

/**
 * Lấy thông tin booking theo ID
 * 
 * @param mysqli $conn Database connection
 * @param int $booking_id ID booking
 * @return array|null Booking data hoặc null nếu không tìm thấy
 */
function getBookingById($conn, $booking_id) {
    $booking_id = (int)$booking_id;
    
    $sql = "
        SELECT b.*, r.room_code, r.room_status, t.full_name as tenant_name
        FROM bookings b
        JOIN rooms r ON r.room_id = b.room_id
        JOIN tenants t ON t.tenant_id = b.tenant_id
        WHERE b.booking_id = $booking_id
        LIMIT 1
    ";
    
    $result = mysqli_query($conn, $sql);
    if (!$result) return null;
    
    return mysqli_fetch_assoc($result);
}

/**
 * Kiểm tra phòng có đang được thuê không (có booking CHECKED_IN)
 * 
 * @param mysqli $conn Database connection
 * @param int $room_id ID phòng
 * @return bool
 */
function isRoomCurrentlyOccupied($conn, $room_id) {
    $room_id = (int)$room_id;
    
    $sql = "
        SELECT COUNT(*) as count
        FROM bookings
        WHERE room_id = $room_id
          AND status = 'CHECKED_IN'
    ";
    
    $result = mysqli_query($conn, $sql);
    if (!$result) return false;
    
    $row = mysqli_fetch_assoc($result);
    return (int)($row['count'] ?? 0) > 0;
}

/**
 * Lấy booking status label (tiếng Việt)
 * 
 * @param string $status Booking status
 * @return string
 */
function getBookingStatusLabel($status) {
    $labels = [
        'PENDING' => 'Đang chờ duyệt',
        'CONFIRMED' => 'Đã duyệt',
        'CHECKED_IN' => 'Đang thuê',
        'CHECKED_OUT' => 'Đã kết thúc',
        'CANCELLED' => 'Đã hủy'
    ];
    
    return $labels[$status] ?? $status;
}

/**
 * Lấy booking status badge class
 * 
 * @param string $status Booking status
 * @return string Bootstrap badge class
 */
function getBookingStatusBadge($status) {
    $badges = [
        'PENDING' => 'bg-warning',
        'CONFIRMED' => 'bg-info',
        'CHECKED_IN' => 'bg-success',
        'CHECKED_OUT' => 'bg-secondary',
        'CANCELLED' => 'bg-dark'
    ];
    
    return $badges[$status] ?? 'bg-secondary';
}
