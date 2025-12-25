<?php
/**
 * VNPay Renewal Callback
 * Xử lý kết quả thanh toán gia hạn hợp đồng
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);

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
$vnp_TxnRef = $vnpData['vnp_TxnRef'] ?? '';
$vnp_Amount = ((float)($vnpData['vnp_Amount'] ?? 0)) / 100;
$vnp_TransactionNo = $vnpData['vnp_TransactionNo'] ?? '';
$vnp_BankCode = $vnpData['vnp_BankCode'] ?? '';

// Try to get renewal info from session first
$renewalInfo = $_SESSION['renewal_info'] ?? null;

// If not in session, try to read from JSON file
if (!$renewalInfo || ($renewalInfo['txn_ref'] ?? '') !== $vnp_TxnRef) {
    $renewalDataFile = __DIR__ . '/../../uploads/renewals/' . $vnp_TxnRef . '.json';
    if (file_exists($renewalDataFile)) {
        $renewalInfo = json_decode(file_get_contents($renewalDataFile), true);
    }
}

if (!$renewalInfo) {
    header('Location: /quanlyphongtro/client/index.php?page=lichsu_datphong&error=' . urlencode('Phiên thanh toán không hợp lệ hoặc đã hết hạn'));
    exit;
}

$contractId = (int)$renewalInfo['contract_id'];
$bookingId = (int)$renewalInfo['booking_id'];
$oldEndDate = $renewalInfo['old_end_date'];
$newEndDate = $renewalInfo['new_end_date'];
$extensionUnits = (int)$renewalInfo['extension_units'];
$amount = (float)$renewalInfo['amount'];

// Lấy contract info
$contractQuery = mysqli_query($conn, 
    "SELECT c.*, r.room_id, r.building_id, r.rental_type, b.owner_id
     FROM contracts c
     LEFT JOIN rooms r ON r.room_id = c.room_id  
     LEFT JOIN buildings b ON b.building_id = r.building_id
     WHERE c.contract_id = $contractId AND c.contract_status = 'ACTIVE'"
);

if (!$contractQuery) {
    header('Location: /quanlyphongtro/client/index.php?page=lichsu_datphong&error=' . urlencode('Lỗi truy vấn hợp đồng'));
    exit;
}

$contract = mysqli_fetch_assoc($contractQuery);

if (!$contract) {
    header('Location: /quanlyphongtro/client/index.php?page=lichsu_datphong&error=' . urlencode('Không tìm thấy hợp đồng'));
    exit;
}

$ownerId = (int)($contract['owner_id'] ?? 0);

if ($vnp_ResponseCode === '00') {
    // Thanh toán thành công
    
    mysqli_begin_transaction($conn);
    
    try {
        // 1. Cập nhật ngày kết thúc hợp đồng
        $updateContractSql = "UPDATE contracts SET 
            end_date = '" . mysqli_real_escape_string($conn, $newEndDate) . "'
            WHERE contract_id = $contractId";
        
        $updateResult = mysqli_query($conn, $updateContractSql);
        if (!$updateResult) {
            throw new Exception('Lỗi cập nhật hợp đồng: ' . mysqli_error($conn));
        }
        
        // 2. Cập nhật booking check_out
        if ($bookingId > 0) {
            mysqli_query($conn, "UPDATE bookings SET 
                check_out = '" . mysqli_real_escape_string($conn, $newEndDate) . "',
                updated_at = NOW()
                WHERE booking_id = $bookingId");
        }
        
        // 3. Tính hoa hồng và cộng tiền cho chủ trọ
        if ($ownerId > 0) {
            require_once __DIR__ . '/../../admin/includes/platform.php';
            
            // Tính hoa hồng
            $commissionData = calculate_commission($vnp_Amount);
            $commission = $commissionData['commission'];
            $netAmount = $commissionData['net'];
            
            $ownerData = mysqli_fetch_assoc(mysqli_query($conn, "SELECT balance FROM users WHERE user_id = $ownerId"));
            $currentBalance = (float)($ownerData['balance'] ?? 0);
            $newBalance = $currentBalance + $netAmount; // Cộng SAU KHI trừ hoa hồng
            
            mysqli_query($conn, "UPDATE users SET balance = $newBalance WHERE user_id = $ownerId");
            
            // Tạo transaction với loại DEPOSIT_RECEIVED
            $unitLabel = ($contract['rental_type'] ?? 'MONTHLY') === 'DAILY' ? 'ngày' : 'tháng';
            $description = "Gia hạn HĐ #{$contract['contract_code']} thêm $extensionUnits $unitLabel - VNPay: $vnp_TransactionNo (Hoa hồng " . PLATFORM_COMMISSION_RATE . "%: " . number_format($commission) . "đ)";
            
            $insertTxn = mysqli_query($conn, "INSERT INTO transactions 
                (user_id, transaction_type, amount, commission_amount, balance_before, balance_after, description, payment_method, status, transaction_ref, created_at)
                VALUES ($ownerId, 'DEPOSIT_RECEIVED', $netAmount, $commission, $currentBalance, $newBalance, 
                '" . mysqli_real_escape_string($conn, $description) . "', 'VNPAY', 'SUCCESS', 
                '" . mysqli_real_escape_string($conn, $vnp_TransactionNo) . "', NOW())");
            
            if (!$insertTxn) {
                error_log("VNPay Renewal - Transaction insert error: " . mysqli_error($conn));
            }
        }
        
        mysqli_commit($conn);
        
        // Clear session and delete temp file
        unset($_SESSION['renewal_info']);
        $renewalDataFile = __DIR__ . '/../../uploads/renewals/' . $vnp_TxnRef . '.json';
        if (file_exists($renewalDataFile)) {
            unlink($renewalDataFile);
        }
        
        header('Location: /quanlyphongtro/client/index.php?page=chitiet_datphong&id=' . $bookingId . '&success=' . urlencode('Gia hạn hợp đồng thành công! Ngày kết thúc mới: ' . date('d/m/Y', strtotime($newEndDate))));
        exit;
        
    } catch (Exception $e) {
        mysqli_rollback($conn);
        error_log("VNPay Renewal Error: " . $e->getMessage());
        header('Location: /quanlyphongtro/client/index.php?page=lichsu_datphong&error=' . urlencode('Lỗi hệ thống: ' . $e->getMessage()));
        exit;
    }
    
} else {
    // Thanh toán thất bại
    unset($_SESSION['renewal_info']);
    $renewalDataFile = __DIR__ . '/../../uploads/renewals/' . $vnp_TxnRef . '.json';
    if (file_exists($renewalDataFile)) {
        unlink($renewalDataFile);
    }
    
    $errorMessages = [
        '07' => 'Giao dịch bị nghi ngờ',
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
    
    $errorMsg = $errorMessages[$vnp_ResponseCode] ?? "Thanh toán thất bại (Mã: $vnp_ResponseCode)";
    header('Location: /quanlyphongtro/client/index.php?page=chitiet_datphong&id=' . $bookingId . '&error=' . urlencode($errorMsg));
    exit;
}
