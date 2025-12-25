<?php
// client/pages/giahan.php - Gia hạn hợp đồng thuê phòng
$hotelier = '/quanlyphongtro/hotelier-1.0.0';

// Kiểm tra đăng nhập
if (!isset($_SESSION['user_id']) || ($_SESSION['role_name'] ?? '') !== 'STUDENT') {
    echo '<script>window.location.href="/quanlyphongtro/client/index.php?page=login&type=student";</script>';
    return;
}

$userId = (int)$_SESSION['user_id'];
$bookingId = (int)($_GET['id'] ?? 0);

if ($bookingId <= 0) {
    echo '<div class="container py-5"><div class="alert alert-danger">Thiếu mã đặt phòng.</div></div>';
    return;
}

// Lấy thông tin booking + contract
$sql = "
    SELECT b.*, 
           r.room_id, r.room_code, r.base_rent, r.daily_price, r.rental_type,
           bl.building_name, bl.address,
           c.contract_id, c.contract_code, c.start_date, c.end_date, c.rent_amount, c.contract_status,
           u.full_name as landlord_name, u.phone as landlord_phone
    FROM bookings b
    JOIN tenants t ON t.tenant_id = b.tenant_id
    LEFT JOIN rooms r ON r.room_id = b.room_id
    LEFT JOIN buildings bl ON bl.building_id = r.building_id
    LEFT JOIN users u ON u.user_id = bl.owner_id
    LEFT JOIN contracts c ON c.contract_id = b.contract_id
    WHERE b.booking_id = ? AND t.user_id = ?
    LIMIT 1
";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, 'ii', $bookingId, $userId);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$booking = $result ? mysqli_fetch_assoc($result) : null;
mysqli_stmt_close($stmt);

if (!$booking) {
    echo '<div class="container py-5"><div class="alert alert-danger">Không tìm thấy thông tin đặt phòng.</div></div>';
    return;
}

if (empty($booking['contract_id']) || $booking['contract_status'] !== 'ACTIVE') {
    echo '<div class="container py-5"><div class="alert alert-warning">Hợp đồng không tồn tại hoặc đã kết thúc.</div></div>';
    return;
}

$contractId = (int)$booking['contract_id'];
$rentalType = $booking['rental_type'] ?? 'MONTHLY';
$currentEndDate = $booking['end_date'] ?? date('Y-m-d', strtotime('+1 month'));
$pricePerUnit = $rentalType === 'DAILY' ? (float)($booking['daily_price'] ?? 0) : (float)($booking['base_rent'] ?? 0);
$unitLabel = $rentalType === 'DAILY' ? 'ngày' : 'tháng';

$error = '';
$conflictWarning = '';

// Xử lý form gia hạn
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $extensionUnits = (int)($_POST['extension_units'] ?? 0);
    
    if ($extensionUnits <= 0) {
        $error = 'Vui lòng nhập số ' . $unitLabel . ' muốn gia hạn.';
    } elseif ($extensionUnits > 12 && $rentalType === 'MONTHLY') {
        $error = 'Chỉ được gia hạn tối đa 12 tháng mỗi lần.';
    } elseif ($extensionUnits > 365 && $rentalType === 'DAILY') {
        $error = 'Chỉ được gia hạn tối đa 365 ngày mỗi lần.';
    }
    
    if (!$error) {
        // Tính ngày kết thúc mới
        if ($rentalType === 'DAILY') {
            $newEndDate = date('Y-m-d', strtotime($currentEndDate . ' + ' . $extensionUnits . ' days'));
        } else {
            $daysToAdd = $extensionUnits * 30;
            $newEndDate = date('Y-m-d', strtotime($currentEndDate . ' + ' . $daysToAdd . ' days'));
        }
        
        // Kiểm tra xung đột
        $roomId = (int)$booking['room_id'];
        $checkStart = $currentEndDate;
        $checkEnd = $newEndDate;
        
        $conflictSql = "
            SELECT bk.booking_id, bk.booking_code, bk.check_in, bk.check_out
            FROM bookings bk
            WHERE bk.room_id = $roomId
              AND bk.booking_id != $bookingId
              AND bk.status IN ('DEPOSIT_PAID', 'CHECKED_IN', 'CONFIRMED')
              AND bk.check_in < '$checkEnd'
              AND (bk.check_out IS NULL OR bk.check_out > '$checkStart')
            LIMIT 1
        ";
        $conflictRs = mysqli_query($conn, $conflictSql);
        
        if ($conflictRs && mysqli_num_rows($conflictRs) > 0) {
            $conflict = mysqli_fetch_assoc($conflictRs);
            $conflictWarning = 'Không thể gia hạn! Phòng đã có người đặt từ ' . date('d/m/Y', strtotime($conflict['check_in'])) . 
                               ($conflict['check_out'] ? ' đến ' . date('d/m/Y', strtotime($conflict['check_out'])) : '') . '.';
        } else {
            // Tính tiền
            $totalAmount = $extensionUnits * $pricePerUnit;
            
            require_once __DIR__ . '/../../admin/includes/vnpay_config.php';
            date_default_timezone_set('Asia/Ho_Chi_Minh');
            
            // Tạo TxnRef với đầy đủ thông tin để có thể parse lại
            // Format: GH_{contractId}_{bookingId}_{extensionUnits}_{newEndDate}_{timestamp}
            $vnp_TxnRef = 'GH' . $contractId . 'B' . $bookingId . 'E' . $extensionUnits . 'T' . time();
            $vnp_OrderInfo = 'Gia han hop dong ' . $booking['contract_code'] . ' them ' . $extensionUnits . ' ' . $unitLabel;
            $vnp_Amount = (int)($totalAmount * 100);
            
            // Lưu vào session VÀ database để backup
            $_SESSION['renewal_info'] = [
                'contract_id' => $contractId,
                'booking_id' => $bookingId,
                'old_end_date' => $currentEndDate,
                'new_end_date' => $newEndDate,
                'extension_units' => $extensionUnits,
                'amount' => $totalAmount,
                'txn_ref' => $vnp_TxnRef
            ];
            
            // Lưu vào bảng tạm (dùng bookings.note hoặc tạo logic riêng)
            // Tạo file tạm với thông tin gia hạn
            $renewalDataFile = __DIR__ . '/../../uploads/renewals/' . $vnp_TxnRef . '.json';
            $renewalDir = dirname($renewalDataFile);
            if (!is_dir($renewalDir)) {
                mkdir($renewalDir, 0755, true);
            }
            file_put_contents($renewalDataFile, json_encode([
                'contract_id' => $contractId,
                'booking_id' => $bookingId,
                'old_end_date' => $currentEndDate,
                'new_end_date' => $newEndDate,
                'extension_units' => $extensionUnits,
                'amount' => $totalAmount,
                'txn_ref' => $vnp_TxnRef,
                'created_at' => date('Y-m-d H:i:s')
            ]));
            
            $inputData = [
                "vnp_Version" => "2.1.0",
                "vnp_TmnCode" => VNPAY_TMN_CODE,
                "vnp_Amount" => $vnp_Amount,
                "vnp_Command" => "pay",
                "vnp_CreateDate" => date('YmdHis'),
                "vnp_CurrCode" => "VND",
                "vnp_IpAddr" => $_SERVER['REMOTE_ADDR'],
                "vnp_Locale" => "vn",
                "vnp_OrderInfo" => $vnp_OrderInfo,
                "vnp_OrderType" => "billpayment",
                "vnp_ReturnUrl" => VNPAY_RENEWAL_RETURN_URL,
                "vnp_TxnRef" => $vnp_TxnRef,
                "vnp_ExpireDate" => date('YmdHis', strtotime('+15 minutes'))
            ];
            
            ksort($inputData);
            $query = "";
            $i = 0;
            $hashdata = "";
            foreach ($inputData as $key => $value) {
                if ($i == 1) {
                    $hashdata .= '&' . urlencode($key) . "=" . urlencode($value);
                } else {
                    $hashdata .= urlencode($key) . "=" . urlencode($value);
                    $i = 1;
                }
                $query .= urlencode($key) . "=" . urlencode($value) . '&';
            }
            
            $vnp_Url = VNPAY_URL . "?" . $query;
            $vnpSecureHash = hash_hmac('sha512', $hashdata, VNPAY_HASH_SECRET);
            $vnp_Url .= 'vnp_SecureHash=' . $vnpSecureHash;
            
            echo '<script>window.location.href = "' . addslashes($vnp_Url) . '";</script>';
            exit;
        }
    }
}

// Preview calculation
$previewUnits = (int)($_POST['extension_units'] ?? 1);
if ($rentalType === 'DAILY') {
    $previewEndDate = date('Y-m-d', strtotime($currentEndDate . ' + ' . $previewUnits . ' days'));
} else {
    $previewEndDate = date('Y-m-d', strtotime($currentEndDate . ' + ' . ($previewUnits * 30) . ' days'));
}
$previewAmount = $previewUnits * $pricePerUnit;
?>

<!-- Page Header -->
<div class="container-fluid page-header mb-5 p-0" style="background-image: url(<?= $hotelier ?>/img/carousel-1.jpg);">
    <div class="container-fluid page-header-inner py-5">
        <div class="container text-center pb-5">
            <h1 class="display-3 text-white mb-3 animated slideInDown">Gia hạn hợp đồng</h1>
        </div>
    </div>
</div>

<div class="container-xxl py-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <?php if ($error): ?>
                <div class="alert alert-danger"><i class="bi bi-exclamation-triangle me-2"></i><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>
                
                <?php if ($conflictWarning): ?>
                <div class="alert alert-warning"><i class="bi bi-exclamation-circle me-2"></i><?= htmlspecialchars($conflictWarning) ?></div>
                <?php endif; ?>
                
                <!-- Contract Info -->
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="bi bi-file-earmark-text me-2"></i>Thông tin hợp đồng hiện tại</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>Mã HĐ:</strong> <?= htmlspecialchars($booking['contract_code']) ?></p>
                                <p><strong>Phòng:</strong> <?= htmlspecialchars($booking['room_code']) ?> - <?= htmlspecialchars($booking['building_name']) ?></p>
                                <p><strong>Loại thuê:</strong> 
                                    <span class="badge bg-<?= $rentalType === 'DAILY' ? 'info' : 'success' ?>">
                                        <?= $rentalType === 'DAILY' ? 'Theo ngày' : 'Theo tháng' ?>
                                    </span>
                                </p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Ngày bắt đầu:</strong> <?= date('d/m/Y', strtotime($booking['start_date'])) ?></p>
                                <p><strong>Ngày kết thúc hiện tại:</strong> 
                                    <span class="text-danger fw-bold"><?= date('d/m/Y', strtotime($currentEndDate)) ?></span>
                                </p>
                                <p><strong>Giá thuê:</strong> 
                                    <span class="text-primary fw-bold"><?= number_format($pricePerUnit) ?>đ/<?= $unitLabel ?></span>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Extension Form -->
                <div class="card">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0"><i class="bi bi-calendar-plus me-2"></i>Gia hạn thêm</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="row g-4">
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Số <?= $unitLabel ?> muốn gia hạn <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <input type="number" name="extension_units" id="extensionUnits" 
                                               class="form-control form-control-lg" 
                                               min="1" max="<?= $rentalType === 'DAILY' ? 365 : 12 ?>" 
                                               value="<?= $previewUnits ?>" required>
                                        <span class="input-group-text"><?= $unitLabel ?></span>
                                    </div>
                                    <small class="text-muted">Tối đa: <?= $rentalType === 'DAILY' ? '365 ngày' : '12 tháng' ?></small>
                                </div>
                                
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Ngày kết thúc mới</label>
                                    <input type="text" id="newEndDate" class="form-control form-control-lg bg-light" 
                                           value="<?= date('d/m/Y', strtotime($previewEndDate)) ?>" readonly>
                                </div>
                                
                                <div class="col-12">
                                    <div class="bg-light rounded p-4 text-center">
                                        <h4 class="text-muted mb-2">Tổng tiền thanh toán</h4>
                                        <h2 class="text-success mb-0" id="totalAmount"><?= number_format($previewAmount) ?>đ</h2>
                                        <small class="text-muted">(<span id="calcDetail"><?= $previewUnits ?> <?= $unitLabel ?> × <?= number_format($pricePerUnit) ?>đ</span>)</small>
                                    </div>
                                </div>
                                
                                <div class="col-12 d-flex gap-2">
                                    <button type="submit" class="btn btn-success btn-lg flex-grow-1">
                                        <i class="bi bi-credit-card me-2"></i>Thanh toán & Gia hạn
                                    </button>
                                    <a href="/quanlyphongtro/client/index.php?page=chitiet_datphong&id=<?= $bookingId ?>" class="btn btn-secondary btn-lg">
                                        <i class="bi bi-arrow-left me-2"></i>Quay lại
                                    </a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const extensionInput = document.getElementById('extensionUnits');
    const newEndDateEl = document.getElementById('newEndDate');
    const totalAmountEl = document.getElementById('totalAmount');
    const calcDetailEl = document.getElementById('calcDetail');
    
    const pricePerUnit = <?= $pricePerUnit ?>;
    const rentalType = '<?= $rentalType ?>';
    const currentEndDate = new Date('<?= $currentEndDate ?>');
    const unitLabel = '<?= $unitLabel ?>';
    
    function formatDate(date) {
        return date.toLocaleDateString('vi-VN');
    }
    
    function formatMoney(amount) {
        return new Intl.NumberFormat('vi-VN').format(amount) + 'đ';
    }
    
    extensionInput.addEventListener('input', function() {
        const units = parseInt(this.value) || 0;
        
        let newDate = new Date(currentEndDate);
        if (rentalType === 'DAILY') {
            newDate.setDate(newDate.getDate() + units);
        } else {
            newDate.setDate(newDate.getDate() + (units * 30));
        }
        
        const total = units * pricePerUnit;
        
        newEndDateEl.value = formatDate(newDate);
        totalAmountEl.textContent = formatMoney(total);
        calcDetailEl.textContent = units + ' ' + unitLabel + ' × ' + formatMoney(pricePerUnit);
    });
});
</script>
