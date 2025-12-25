<?php
/**
 * VNPay Configuration
 * Môi trường: Sandbox (Test)
 */

// VNPay Sandbox credentials
define('VNPAY_TMN_CODE', '28LTL1N9');
define('VNPAY_HASH_SECRET', 'J9L5V3F6WF26XVXT3D7BIMM4F70JOCY6');
define('VNPAY_URL', 'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html');

// URLs - Đổi localhost thành domain thực khi deploy
define('VNPAY_RETURN_URL', 'http://localhost/quanlyphongtro/admin/modules/landlord/vnpay_return.php');
define('VNPAY_BOOKING_RETURN_URL', 'http://localhost/quanlyphongtro/client/pages/vnpay_booking_return.php');
define('VNPAY_RENEWAL_RETURN_URL', 'http://localhost/quanlyphongtro/client/pages/vnpay_renewal_return.php');

/**
 * Tạo URL thanh toán VNPay (Chuẩn VNPay 2.1.0)
 */
function vnpay_create_payment_url(int $orderId, float $amount, string $orderInfo, string $ipAddr): string 
{
    // Đặt timezone Việt Nam
    date_default_timezone_set('Asia/Ho_Chi_Minh');
    
    $vnp_TmnCode = VNPAY_TMN_CODE;
    $vnp_HashSecret = VNPAY_HASH_SECRET;
    $vnp_Url = VNPAY_URL;
    $vnp_ReturnUrl = VNPAY_RETURN_URL;
    
    $inputData = array(
        "vnp_Version" => "2.1.0",
        "vnp_TmnCode" => $vnp_TmnCode,
        "vnp_Amount" => (int)($amount * 100),
        "vnp_Command" => "pay",
        "vnp_CreateDate" => date('YmdHis'),
        "vnp_CurrCode" => "VND",
        "vnp_IpAddr" => $ipAddr,
        "vnp_Locale" => "vn",
        "vnp_OrderInfo" => $orderInfo,
        "vnp_OrderType" => "other",
        "vnp_ReturnUrl" => $vnp_ReturnUrl,
        "vnp_TxnRef" => (string)$orderId,
        "vnp_ExpireDate" => date('YmdHis', strtotime('+15 minutes'))
    );

    ksort($inputData);
    
    $query = "";
    $i = 0;
    $hashdata = "";
    foreach ($inputData as $key => $value) {
        if ($i == 1) {
            $hashdata .= '&' . urlencode($key) . "=" . urlencode((string)$value);
        } else {
            $hashdata .= urlencode($key) . "=" . urlencode((string)$value);
            $i = 1;
        }
        $query .= urlencode($key) . "=" . urlencode((string)$value) . '&';
    }

    $vnp_Url = $vnp_Url . "?" . $query;
    $vnpSecureHash = hash_hmac('sha512', $hashdata, $vnp_HashSecret);
    $vnp_Url .= 'vnp_SecureHash=' . $vnpSecureHash;
    
    return $vnp_Url;
}

/**
 * Xác thực checksum từ VNPay
 */
function vnpay_validate_checksum(array $vnpData): bool 
{
    $vnp_HashSecret = VNPAY_HASH_SECRET;
    
    $vnp_SecureHash = $vnpData['vnp_SecureHash'] ?? '';
    unset($vnpData['vnp_SecureHash']);
    unset($vnpData['vnp_SecureHashType']);
    
    ksort($vnpData);
    
    $hashData = "";
    $i = 0;
    foreach ($vnpData as $key => $value) {
        if ($i == 1) {
            $hashData .= '&' . urlencode($key) . "=" . urlencode((string)$value);
        } else {
            $hashData .= urlencode($key) . "=" . urlencode((string)$value);
            $i = 1;
        }
    }
    
    $secureHash = hash_hmac('sha512', $hashData, $vnp_HashSecret);
    
    return hash_equals($secureHash, $vnp_SecureHash);
}

/**
 * Tạo URL thanh toán VNPay cho booking (với custom return URL)
 */
function vnpay_create_booking_payment_url(string $txnRef, float $amount, string $orderInfo, string $ipAddr): string 
{
    date_default_timezone_set('Asia/Ho_Chi_Minh');
    
    $vnp_TmnCode = VNPAY_TMN_CODE;
    $vnp_HashSecret = VNPAY_HASH_SECRET;
    $vnp_Url = VNPAY_URL;
    $vnp_ReturnUrl = defined('VNPAY_BOOKING_RETURN_URL') ? VNPAY_BOOKING_RETURN_URL : VNPAY_RETURN_URL;
    
    $inputData = array(
        "vnp_Version" => "2.1.0",
        "vnp_TmnCode" => $vnp_TmnCode,
        "vnp_Amount" => (int)($amount * 100),
        "vnp_Command" => "pay",
        "vnp_CreateDate" => date('YmdHis'),
        "vnp_CurrCode" => "VND",
        "vnp_IpAddr" => $ipAddr,
        "vnp_Locale" => "vn",
        "vnp_OrderInfo" => $orderInfo,
        "vnp_OrderType" => "other",
        "vnp_ReturnUrl" => $vnp_ReturnUrl,
        "vnp_TxnRef" => $txnRef,
        "vnp_ExpireDate" => date('YmdHis', strtotime('+15 minutes'))
    );

    ksort($inputData);
    
    $query = "";
    $i = 0;
    $hashdata = "";
    foreach ($inputData as $key => $value) {
        if ($i == 1) {
            $hashdata .= '&' . urlencode($key) . "=" . urlencode((string)$value);
        } else {
            $hashdata .= urlencode($key) . "=" . urlencode((string)$value);
            $i = 1;
        }
        $query .= urlencode($key) . "=" . urlencode((string)$value) . '&';
    }

    $vnp_Url = $vnp_Url . "?" . $query;
    $vnpSecureHash = hash_hmac('sha512', $hashdata, $vnp_HashSecret);
    $vnp_Url .= 'vnp_SecureHash=' . $vnpSecureHash;
    
    return $vnp_Url;
}
