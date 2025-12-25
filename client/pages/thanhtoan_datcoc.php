<?php
// client/pages/thanhtoan_datcoc.php - Thanh toán đặt cọc với QR Demo

if (!isset($_SESSION['user_id']) || ($_SESSION['role_name'] ?? '') !== 'STUDENT') {
    echo '<script>window.location.href="/quanlyphongtro/client/index.php?page=login";</script>';
    return;
}

$userId = (int)$_SESSION['user_id'];
$bookingId = (int)($_GET['id'] ?? 0);

if ($bookingId <= 0) {
    echo '<div class="container py-5"><div class="alert alert-warning">Không tìm thấy yêu cầu đặt phòng.</div></div>';
    return;
}

// Lấy thông tin booking
$sql = "
    SELECT b.*, 
           r.room_code, r.base_rent, r.deposit as room_deposit,
           bl.building_name, bl.address,
           u.full_name as landlord_name, u.phone as landlord_phone, u.email as landlord_email
    FROM bookings b
    JOIN tenants t ON t.tenant_id = b.tenant_id
    LEFT JOIN rooms r ON r.room_id = b.room_id
    LEFT JOIN buildings bl ON bl.building_id = r.building_id
    LEFT JOIN users u ON u.user_id = bl.owner_id
    WHERE b.booking_id = $bookingId 
      AND t.user_id = $userId
      AND b.status = 'CONFIRMED'
    LIMIT 1
";
$result = mysqli_query($conn, $sql);
$booking = $result ? mysqli_fetch_assoc($result) : null;

if (!$booking) {
    echo '<div class="container py-5"><div class="alert alert-warning">Yêu cầu không hợp lệ hoặc chưa được duyệt.</div>
          <a href="/quanlyphongtro/client/index.php?page=lichsu_datphong" class="btn btn-primary">Quay lại</a></div>';
    return;
}

$depositAmount = (float)($booking['room_deposit'] ?: $booking['base_rent'] ?? 0);
$landlordName = $booking['landlord_name'] ?? 'Chủ trọ';
$landlordPhone = $booking['landlord_phone'] ?? '';
$roomCode = $booking['room_code'] ?? '';
$bookingCode = $booking['booking_code'] ?? '#'.$bookingId;

// Nội dung chuyển khoản cho QR
$transferContent = "DATCOC $bookingCode $roomCode";
$qrData = urlencode("Chuyen khoan: $landlordName\nSo tien: " . number_format($depositAmount) . "d\nNoi dung: $transferContent\nSDT: $landlordPhone");

$success = false;
$error = '';

// Xử lý khi SV xác nhận đã chuyển tiền
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_transfer'])) {
    // Cập nhật booking để đánh dấu SV đã xác nhận chuyển tiền (chờ chủ trọ xác nhận)
    // Không đổi status, chỉ ghi nhận thời gian SV xác nhận
    $result = mysqli_query($conn, "
        UPDATE bookings 
        SET deposit_amount = $depositAmount,
            note = CONCAT(IFNULL(note,''), '\n[SV xác nhận đã chuyển tiền lúc " . date('d/m/Y H:i') . "]')
        WHERE booking_id = $bookingId
    ");
    
    if ($result) {
        $success = true;
    } else {
        $error = 'Có lỗi xảy ra, vui lòng thử lại.';
    }
}

$hotelier = '/quanlyphongtro/hotelier-1.0.0';
?>

<!-- Page Header -->
<div class="container-fluid page-header mb-5 p-0" style="background-image: url(<?= $hotelier ?>/img/carousel-2.jpg);">
    <div class="container-fluid page-header-inner py-5">
        <div class="container text-center pb-5">
            <h1 class="display-3 text-white mb-3 animated slideInDown">Thanh toán đặt cọc</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb justify-content-center text-uppercase">
                    <li class="breadcrumb-item"><a href="/quanlyphongtro/client/index.php?page=home" class="text-white">Trang chủ</a></li>
                    <li class="breadcrumb-item text-white active">Thanh toán</li>
                </ol>
            </nav>
        </div>
    </div>
</div>

<div class="container-xxl py-5">
    <div class="container">
        <?php if ($success): ?>
            <div class="row justify-content-center">
                <div class="col-lg-6">
                    <div class="card shadow-lg border-warning">
                        <div class="card-body text-center py-5">
                            <i class="bi bi-hourglass-split text-warning" style="font-size: 80px;"></i>
                            <h2 class="mt-4 text-warning">Đang chờ xác nhận!</h2>
                            <p class="lead mb-4">Bạn đã xác nhận chuyển tiền <strong class="text-danger"><?= number_format($depositAmount) ?>đ</strong></p>
                            <div class="alert alert-info text-start">
                                <i class="bi bi-info-circle me-2"></i>
                                <strong>Bước tiếp theo:</strong> Chủ trọ sẽ kiểm tra và xác nhận nhận được tiền. 
                                Sau khi xác nhận, bạn sẽ được lập hợp đồng.
                            </div>
                            <hr>
                            <a href="/quanlyphongtro/client/index.php?page=chitiet_datphong&id=<?= $bookingId ?>" class="btn btn-primary btn-lg">
                                <i class="bi bi-eye me-2"></i>Xem chi tiết đặt phòng
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="row g-4 justify-content-center">
                <!-- QR Code -->
                <div class="col-lg-5">
                    <div class="card shadow-lg h-100">
                        <div class="card-header bg-primary text-white text-center">
                            <h5 class="mb-0"><i class="bi bi-qr-code me-2"></i>Quét mã để chuyển khoản</h5>
                        </div>
                        <div class="card-body text-center py-4">
                            <!-- QR Code Demo using Google Chart API -->
                            <div class="bg-white p-3 rounded border mb-3" style="display: inline-block;">
                                <img src="https://chart.googleapis.com/chart?chs=250x250&cht=qr&chl=<?= $qrData ?>&choe=UTF-8" 
                                     alt="QR Code thanh toán" class="img-fluid">
                            </div>
                            
                            <div class="alert alert-light border text-start">
                                <div class="mb-2">
                                    <span class="text-muted">Người nhận:</span><br>
                                    <strong class="fs-5"><?= htmlspecialchars($landlordName) ?></strong>
                                </div>
                                <div class="mb-2">
                                    <span class="text-muted">Số điện thoại:</span><br>
                                    <strong><?= htmlspecialchars($landlordPhone) ?></strong>
                                </div>
                                <div class="mb-2">
                                    <span class="text-muted">Số tiền:</span><br>
                                    <strong class="fs-4 text-danger"><?= number_format($depositAmount) ?>đ</strong>
                                </div>
                                <div>
                                    <span class="text-muted">Nội dung chuyển khoản:</span><br>
                                    <code class="fs-6"><?= htmlspecialchars($transferContent) ?></code>
                                </div>
                            </div>
                            
                            <div class="alert alert-warning small mb-0">
                                <i class="bi bi-exclamation-triangle me-1"></i>
                                <strong>Lưu ý:</strong> Nhập đúng nội dung chuyển khoản để chủ trọ dễ xác nhận!
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Thông tin & Xác nhận -->
                <div class="col-lg-5">
                    <div class="card shadow-lg h-100">
                        <div class="card-header bg-success text-white">
                            <h5 class="mb-0"><i class="bi bi-receipt me-2"></i>Thông tin đặt phòng</h5>
                        </div>
                        <div class="card-body">
                            <?php if ($error): ?>
                                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                            <?php endif; ?>
                            
                            <table class="table table-borderless">
                                <tr>
                                    <td class="text-muted">Mã đặt phòng:</td>
                                    <td class="fw-bold text-end"><?= htmlspecialchars($bookingCode) ?></td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Phòng:</td>
                                    <td class="fw-bold text-end"><?= htmlspecialchars($roomCode) ?></td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Dãy trọ:</td>
                                    <td class="text-end"><?= htmlspecialchars($booking['building_name'] ?? '-') ?></td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Ngày nhận phòng:</td>
                                    <td class="text-end"><?= $booking['check_in'] ? date('d/m/Y', strtotime($booking['check_in'])) : '-' ?></td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Chủ trọ:</td>
                                    <td class="text-end"><?= htmlspecialchars($landlordName) ?></td>
                                </tr>
                            </table>
                            
                            <hr>
                            
                            <div class="d-flex justify-content-between align-items-center mb-4">
                                <span class="fs-5">Tiền cọc cần thanh toán:</span>
                                <span class="fs-3 fw-bold text-danger"><?= number_format($depositAmount) ?>đ</span>
                            </div>
                            
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle me-2"></i>
                                <strong>Hướng dẫn:</strong>
                                <ol class="mb-0 mt-2 ps-3">
                                    <li>Quét mã QR hoặc chuyển khoản thủ công</li>
                                    <li>Nhập đúng số tiền và nội dung</li>
                                    <li>Bấm <strong>"Tôi đã chuyển tiền"</strong> bên dưới</li>
                                    <li>Đợi chủ trọ xác nhận nhận được tiền</li>
                                </ol>
                            </div>
                            
                            <form method="post">
                                <div class="d-grid">
                                    <button type="submit" name="confirm_transfer" class="btn btn-success btn-lg"
                                            onclick="return confirm('Bạn xác nhận đã chuyển khoản <?= number_format($depositAmount) ?>đ cho chủ trọ?');">
                                        <i class="bi bi-check-circle me-2"></i>Tôi đã chuyển tiền
                                    </button>
                                </div>
                            </form>
                            
                            <div class="text-center mt-3">
                                <a href="/quanlyphongtro/client/index.php?page=chitiet_datphong&id=<?= $bookingId ?>" class="text-muted">
                                    <i class="bi bi-arrow-left me-1"></i>Quay lại
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
