<?php
declare(strict_types=1);

// PHÍ ĐĂNG TIN / PHÒNG ĐƯỢC DUYỆT
define('LISTING_FEE_AMOUNT', 20000);       // bạn đổi số tiền tùy ý
define('LISTING_FEE_PERIOD_DAYS', 30);     // hiệu lực hiển thị sau khi PAID

// THÔNG TIN NHẬN TIỀN (VietQR)
define('PLATFORM_BANK_BIN', '970415');     // ví dụ: VietinBank = 970415 (bạn đổi theo ngân hàng)
define('PLATFORM_BANK_ACCOUNT', '0123456789'); // số tài khoản nhận
define('PLATFORM_BANK_ACCOUNT_NAME', 'QUAN LY PHONG TRO'); // tên chủ TK (không dấu càng tốt)

// HOA HỒNG PLATFORM (mặc định nếu không có tiers trong DB)
define('PLATFORM_COMMISSION_RATE', 5);     // % hoa hồng mặc định
define('PLATFORM_MIN_COMMISSION', 5000);   // Hoa hồng tối thiểu (VNĐ)

/**
 * Lấy tỷ lệ hoa hồng theo mức giá từ database
 */
function get_commission_rate(float $amount): float {
    global $conn;
    
    // Thử lấy từ database
    $amountSafe = (float)$amount;
    $result = @mysqli_query($conn, "
        SELECT rate FROM commission_tiers 
        WHERE is_active = 1 
        AND min_amount <= $amountSafe 
        AND (max_amount IS NULL OR max_amount > $amountSafe)
        ORDER BY min_amount DESC
        LIMIT 1
    ");
    
    if ($result && $row = mysqli_fetch_assoc($result)) {
        return (float)$row['rate'];
    }
    
    // Fallback về mặc định
    return (float)PLATFORM_COMMISSION_RATE;
}

/**
 * Lấy tất cả mức hoa hồng (cho JS)
 */
function get_commission_tiers(): array {
    global $conn;
    
    $tiers = [];
    $result = @mysqli_query($conn, "
        SELECT min_amount, max_amount, rate, description 
        FROM commission_tiers 
        WHERE is_active = 1 
        ORDER BY min_amount ASC
    ");
    
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $tiers[] = [
                'min' => (float)$row['min_amount'],
                'max' => $row['max_amount'] ? (float)$row['max_amount'] : null,
                'rate' => (float)$row['rate'],
                'desc' => $row['description']
            ];
        }
    }
    
    // Fallback nếu không có tiers
    if (empty($tiers)) {
        $tiers[] = ['min' => 0, 'max' => null, 'rate' => (float)PLATFORM_COMMISSION_RATE, 'desc' => 'Mặc định'];
    }
    
    return $tiers;
}

/**
 * Tính hoa hồng theo mức giá
 */
function calculate_commission(float $amount): array {
    $rate = get_commission_rate($amount);
    $commission = max($amount * $rate / 100, PLATFORM_MIN_COMMISSION);
    $commission = min($commission, $amount); // Không vượt quá số tiền gốc
    $netAmount = $amount - $commission;
    
    return [
        'gross' => $amount,
        'commission' => $commission,
        'net' => $netAmount,
        'rate' => $rate
    ];
}
