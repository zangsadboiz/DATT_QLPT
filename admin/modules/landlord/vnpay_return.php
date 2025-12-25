<?php
/**
 * VNPay Return URL
 * Xử lý callback sau khi thanh toán từ VNPay
 */
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/vnpay_config.php';

$vnpData = $_GET;

// Validate checksum
if (!vnpay_validate_checksum($vnpData)) {
    header('Location: ' . ADMIN_BASE_PATH . '/modules/landlord/topup.php?error=invalid_checksum');
    exit;
}

$vnp_ResponseCode = $vnpData['vnp_ResponseCode'] ?? '';
$vnp_TxnRef = (int)($vnpData['vnp_TxnRef'] ?? 0);
$vnp_Amount = ((float)($vnpData['vnp_Amount'] ?? 0)) / 100; // Chia 100 để lấy số tiền thực
$vnp_TransactionNo = $vnpData['vnp_TransactionNo'] ?? '';
$vnp_BankCode = $vnpData['vnp_BankCode'] ?? '';
$vnp_PayDate = $vnpData['vnp_PayDate'] ?? '';

// Kiểm tra giao dịch trong DB
$transaction = mysqli_fetch_assoc(mysqli_query($conn, 
    "SELECT * FROM transactions WHERE transaction_id = $vnp_TxnRef AND status = 'PENDING'"
));

if (!$transaction) {
    header('Location: ' . ADMIN_BASE_PATH . '/modules/landlord/topup.php?error=transaction_not_found');
    exit;
}

$userId = (int)$transaction['user_id'];
$amount = (float)$transaction['amount'];
$balanceBefore = (float)$transaction['balance_before'];

if ($vnp_ResponseCode === '00') {
    // Thanh toán thành công
    $newBalance = $balanceBefore + $amount;
    
    // Cập nhật balance
    mysqli_query($conn, "UPDATE users SET balance = $newBalance WHERE user_id = $userId");
    
    // Cập nhật transaction
    $updateSql = "UPDATE transactions SET 
        status = 'SUCCESS', 
        balance_after = $newBalance,
        transaction_ref = '" . mysqli_real_escape_string($conn, $vnp_TransactionNo) . "',
        description = CONCAT(description, ' - VNPay: $vnp_TransactionNo, Bank: $vnp_BankCode')
        WHERE transaction_id = $vnp_TxnRef";
    mysqli_query($conn, $updateSql);
    
    header('Location: ' . ADMIN_BASE_PATH . '/modules/landlord/topup.php?msg=success&amount=' . $amount);
} else {
    // Thanh toán thất bại
    mysqli_query($conn, "UPDATE transactions SET status = 'FAILED' WHERE transaction_id = $vnp_TxnRef");
    
    $errorMsg = vnpay_get_error_message($vnp_ResponseCode);
    header('Location: ' . ADMIN_BASE_PATH . '/modules/landlord/topup.php?error=' . urlencode($errorMsg));
}
exit;

/**
 * Lấy thông báo lỗi từ mã response VNPay
 */
function vnpay_get_error_message(string $code): string 
{
    $messages = [
        '07' => 'Trừ tiền thành công. Giao dịch bị nghi ngờ (liên quan tới lừa đảo, giao dịch bất thường)',
        '09' => 'Thẻ/Tài khoản chưa đăng ký dịch vụ InternetBanking tại ngân hàng',
        '10' => 'Xác thực không đúng quá 3 lần',
        '11' => 'Đã hết hạn chờ thanh toán',
        '12' => 'Thẻ/Tài khoản bị khóa',
        '13' => 'Bạn nhập sai mật khẩu OTP',
        '24' => 'Bạn đã hủy giao dịch',
        '51' => 'Tài khoản không đủ số dư',
        '65' => 'Tài khoản đã vượt quá hạn mức giao dịch trong ngày',
        '75' => 'Ngân hàng thanh toán đang bảo trì',
        '79' => 'Nhập sai mật khẩu quá số lần quy định',
        '99' => 'Lỗi không xác định',
    ];
    
    return $messages[$code] ?? 'Giao dịch thất bại (Mã: ' . $code . ')';
}
