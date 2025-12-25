<?php
declare(strict_types=1);

require_once __DIR__ . '/platform.php';

/**
 * VietQR image API (demo nhanh). Cần internet để hiển thị ảnh QR.
 * Nếu không có internet, vẫn hiển thị đủ thông tin chuyển khoản + nội dung.
 */
function vietqr_image_url(int $amount, string $addInfo): string
{
    $bin = PLATFORM_BANK_BIN;
    $acc = PLATFORM_BANK_ACCOUNT;

    $qs = http_build_query([
        'amount' => $amount,
        'addInfo' => $addInfo,
        'accountName' => PLATFORM_BANK_ACCOUNT_NAME,
    ]);

    return "https://api.vietqr.io/image/{$bin}-{$acc}-compact2.png?{$qs}";
}

/** Nội dung chuyển khoản chuẩn để đối soát thủ công */
function vietqr_transfer_content(string $addInfo): string
{
    // Bạn có thể thêm prefix để dễ lọc sao kê
    return $addInfo;
}
