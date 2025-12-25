<?php
/**
 * VNPay Booking Callback
 * Xử lý kết quả thanh toán đặt cọc cho booking
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../../admin/includes/vnpay_config.php';

$vnpData = $_GET;

// Validate checksum
if (!vnpay_validate_checksum($vnpData)) {
    header('Location: /quanlyphongtro/client/index.php?page=lichsu_datphong&error=' . urlencode('Xác thực thanh toán thất bại'));
    exit;
}

$vnp_ResponseCode = $vnpData['vnp_ResponseCode'] ?? '';
$vnp_TxnRef = $vnpData['vnp_TxnRef'] ?? ''; // Format: "BOOKING_{booking_id}"
$vnp_Amount = ((float)($vnpData['vnp_Amount'] ?? 0)) / 100;
$vnp_TransactionNo = $vnpData['vnp_TransactionNo'] ?? '';
$vnp_BankCode = $vnpData['vnp_BankCode'] ?? '';

// Lấy booking_id từ TxnRef
if (!preg_match('/^BOOKING_(\d+)$/', $vnp_TxnRef, $matches)) {
    header('Location: /quanlyphongtro/client/index.php?page=lichsu_datphong&error=' . urlencode('Mã giao dịch không hợp lệ'));
    exit;
}

$bookingId = (int)$matches[1];

// Lấy booking
$booking = mysqli_fetch_assoc(mysqli_query($conn, 
    "SELECT b.*, r.base_rent, r.building_id, bg.owner_id
     FROM bookings b
     LEFT JOIN rooms r ON r.room_id = b.room_id  
     LEFT JOIN buildings bg ON bg.building_id = r.building_id
     WHERE b.booking_id = $bookingId AND b.status = 'PENDING'"
));

if (!$booking) {
    header('Location: /quanlyphongtro/client/index.php?page=lichsu_datphong&error=' . urlencode('Không tìm thấy booking hoặc đã được thanh toán'));
    exit;
}

$ownerId = (int)($booking['owner_id'] ?? 0);

if ($vnp_ResponseCode === '00') {
    // Thanh toán thành công
    
    // 1. Cập nhật booking
    $updateBookingSql = "UPDATE bookings SET 
        status = 'DEPOSIT_PAID',
        deposit_amount = $vnp_Amount,
        transaction_ref = '" . mysqli_real_escape_string($conn, $vnp_TransactionNo) . "',
        updated_at = NOW()
        WHERE booking_id = $bookingId";
    
    $updateResult = mysqli_query($conn, $updateBookingSql);
    if (!$updateResult) {
        // Log lỗi SQL
        error_log("VNPay Callback - Booking Update Error: " . mysqli_error($conn));
        error_log("SQL: " . $updateBookingSql);
    }
    
    // 2. Tính hoa hồng và cộng tiền cho chủ trọ
    if ($ownerId > 0) {
        require_once __DIR__ . '/../../admin/includes/platform.php';
        
        // Tính hoa hồng
        $commissionData = calculate_commission($vnp_Amount);
        $commission = $commissionData['commission'];
        $netAmount = $commissionData['net'];
        
        $ownerData = mysqli_fetch_assoc(mysqli_query($conn, "SELECT balance FROM users WHERE user_id = $ownerId"));
        $currentBalance = (float)($ownerData['balance'] ?? 0);
        $newBalance = $currentBalance + $netAmount; // Cộng số tiền SAU KHI trừ hoa hồng
        
        mysqli_query($conn, "UPDATE users SET balance = $newBalance WHERE user_id = $ownerId");
        
        // 3. Tạo transaction cho chủ trọ (ghi cả hoa hồng)
        $description = "Nhận tiền đặt cọc #{$booking['booking_code']} - VNPay: $vnp_TransactionNo (Hoa hồng " . PLATFORM_COMMISSION_RATE . "%: " . number_format($commission) . "đ)";
        mysqli_query($conn, "INSERT INTO transactions 
            (user_id, transaction_type, amount, commission_amount, balance_before, balance_after, description, payment_method, status, transaction_ref, created_at)
            VALUES ($ownerId, 'DEPOSIT_RECEIVED', $netAmount, $commission, $currentBalance, $newBalance, 
            '" . mysqli_real_escape_string($conn, $description) . "', 'VNPAY', 'SUCCESS', 
            '" . mysqli_real_escape_string($conn, $vnp_TransactionNo) . "', NOW())");
    }
    
    header('Location: /quanlyphongtro/client/index.php?page=lichsu_datphong&success=' . urlencode('Đặt phòng và thanh toán thành công! Chủ trọ sẽ liên hệ với bạn sớm.'));
} else {
    // Thanh toán thất bại - Hủy booking
    mysqli_query($conn, "UPDATE bookings SET status = 'CANCELLED', updated_at = NOW() WHERE booking_id = $bookingId");
    
    $errorMsg = vnpay_get_booking_error_message($vnp_ResponseCode);
    header('Location: /quanlyphongtro/client/index.php?page=lichsu_datphong&error=' . urlencode($errorMsg));
}
exit;

/**
 * Lấy thông báo lỗi thanh toán
 */
function vnpay_get_booking_error_message(string $code): string 
{
    $messages = [
        '07' => 'Giao dịch bị nghi ngờ. Vui lòng liên hệ ngân hàng.',
        '09' => 'Thẻ chưa đăng ký Internet Banking',
        '10' => 'Xác thực sai quá 3 lần',
        '11' => 'Hết thời gian thanh toán',
        '12' => 'Thẻ bị khóa',
        '13' => 'Sai mật khẩu OTP',
        '24' => 'Bạn đã hủy giao dịch',
        '51' => 'Tài khoản không đủ số dư',
        '65' => 'Vượt hạn mức giao dịch',
        '75' => 'Ngân hàng đang bảo trì',
        '79' => 'Nhập sai mật khẩu quá nhiều lần',
    ];
    
    return $messages[$code] ?? 'Thanh toán thất bại (Mã lỗi: ' . $code . ')';
}
