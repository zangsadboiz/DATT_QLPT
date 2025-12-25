<?php
// client/pages/chitiet_datphong.php - Chi tiết yêu cầu đặt phòng

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
           r.room_code, r.base_rent, r.daily_price, r.rental_type, r.area, r.floor,
           bl.building_name, bl.address,
           u.full_name as landlord_name, u.phone as landlord_phone, u.email as landlord_email,
           t.full_name as tenant_name, t.phone as tenant_phone, t.email as tenant_email,
           c.contract_id, c.contract_code, c.start_date as contract_start, c.end_date as contract_end, c.contract_status
    FROM bookings b
    JOIN tenants t ON t.tenant_id = b.tenant_id
    LEFT JOIN rooms r ON r.room_id = b.room_id
    LEFT JOIN buildings bl ON bl.building_id = r.building_id
    LEFT JOIN users u ON u.user_id = bl.owner_id
    LEFT JOIN contracts c ON c.contract_id = b.contract_id
    WHERE b.booking_id = $bookingId AND t.user_id = $userId
    LIMIT 1
";
$result = mysqli_query($conn, $sql);
$booking = $result ? mysqli_fetch_assoc($result) : null;

if (!$booking) {
    // Kiểm tra xem booking có tồn tại không
    $checkBooking = mysqli_fetch_assoc(mysqli_query($conn, "SELECT booking_id, tenant_id FROM bookings WHERE booking_id = $bookingId"));
    if (!$checkBooking) {
        echo '<div class="container py-5"><div class="alert alert-warning">Yêu cầu đặt phòng không tồn tại.</div>
              <a href="/quanlyphongtro/client/index.php?page=lichsu_datphong" class="btn btn-primary">Quay lại</a></div>';
    } else {
        echo '<div class="container py-5"><div class="alert alert-danger">Bạn không có quyền xem yêu cầu này.</div>
              <a href="/quanlyphongtro/client/index.php?page=lichsu_datphong" class="btn btn-primary">Quay lại</a></div>';
    }
    return;
}

$statusLabels = [
    'PENDING' => ['Chờ thanh toán', 'warning', 'clock'],
    'DEPOSIT_PAID' => ['Đã thanh toán', 'success', 'check-circle'],
    'CHECKED_IN' => ['Đang ở', 'primary', 'house-door'],
    'CHECKED_OUT' => ['Đã trả phòng', 'secondary', 'box-arrow-right'],
    'CANCELLED' => ['Đã hủy', 'danger', 'x-circle']
];
$status = $booking['status'] ?? 'PENDING';
$label = $statusLabels[$status] ?? ['Không xác định', 'secondary', 'question'];
$hotelier = '/quanlyphongtro/hotelier-1.0.0';

// Lấy lịch sử gia hạn
$renewalHistory = [];
if (!empty($booking['contract_id'])) {
    $contractCode = mysqli_real_escape_string($conn, $booking['contract_code'] ?? '');
    $renewalRs = mysqli_query($conn, "
        SELECT transaction_id, description, amount, created_at
        FROM transactions 
        WHERE description LIKE '%$contractCode%'
          AND transaction_type = 'DEPOSIT_RECEIVED'
        ORDER BY created_at DESC
    ");
    if ($renewalRs) {
        while ($row = mysqli_fetch_assoc($renewalRs)) {
            $renewalHistory[] = $row;
        }
    }
}
?>

<!-- Page Header -->
<div class="container-fluid page-header mb-5 p-0" style="background-image: url(<?= $hotelier ?>/img/carousel-1.jpg);">
    <div class="container-fluid page-header-inner py-5">
        <div class="container text-center pb-5">
            <h1 class="display-3 text-white mb-3 animated slideInDown">Chi tiết đặt phòng</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb justify-content-center text-uppercase">
                    <li class="breadcrumb-item"><a href="/quanlyphongtro/client/index.php?page=home" class="text-white">Trang chủ</a></li>
                    <li class="breadcrumb-item"><a href="/quanlyphongtro/client/index.php?page=lichsu_datphong" class="text-white">Lịch sử đặt phòng</a></li>
                    <li class="breadcrumb-item text-white active">Chi tiết</li>
                </ol>
            </nav>
        </div>
    </div>
</div>

<div class="container-xxl py-5">
    <div class="container">
        <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show mb-4">
            <i class="bi bi-check-circle me-2"></i><?= htmlspecialchars($_GET['success']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        <?php if (isset($_GET['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show mb-4">
            <i class="bi bi-exclamation-triangle me-2"></i><?= htmlspecialchars($_GET['error']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        
        <div class="row g-4">
            <!-- Thông tin đặt phòng -->
            <div class="col-lg-8">
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="bi bi-receipt me-2"></i>Mã: <?= htmlspecialchars($booking['booking_code'] ?? 'N/A') ?></h5>
                        <span class="badge bg-<?= $label[1] ?> fs-6">
                            <i class="bi bi-<?= $label[2] ?> me-1"></i><?= $label[0] ?>
                        </span>
                    </div>
                    <div class="card-body">
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <h6 class="text-muted">Thông tin phòng</h6>
                                <table class="table table-sm">
                                    <tr><td width="40%">Phòng:</td><td><strong><?= htmlspecialchars($booking['room_code'] ?? 'N/A') ?></strong></td></tr>
                                    <tr><td>Dãy/Tòa:</td><td><?= htmlspecialchars($booking['building_name'] ?? 'N/A') ?></td></tr>
                                    <tr><td>Địa chỉ:</td><td><?= htmlspecialchars($booking['address'] ?? 'N/A') ?></td></tr>
                                    <tr><td>Diện tích:</td><td><?= $booking['area'] ?? '—' ?> m²</td></tr>
                                    <tr><td>Tầng:</td><td><?= $booking['floor'] ? 'Tầng ' . $booking['floor'] : '—' ?></td></tr>
                                    <tr><td>Giá thuê:</td><td class="text-primary fw-bold">
                                        <?php 
                                        $rentalType = $booking['rental_type'] ?? 'MONTHLY';
                                        $displayPrice = $rentalType === 'DAILY' ? (float)($booking['daily_price'] ?? 0) : (float)($booking['base_rent'] ?? 0);
                                        $priceUnit = $rentalType === 'DAILY' ? 'ngày' : 'tháng';
                                        echo number_format($displayPrice) . 'đ/' . $priceUnit;
                                        ?>
                                    </td></tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <h6 class="text-muted">Thời gian</h6>
                                <table class="table table-sm">
                                    <tr><td width="40%">Ngày đặt:</td><td><?= date('d/m/Y H:i', strtotime($booking['created_at'])) ?></td></tr>
                                    <tr><td>Ngày nhận dự kiến:</td><td><strong><?= $booking['check_in'] ? date('d/m/Y', strtotime($booking['check_in'])) : '—' ?></strong></td></tr>
                                    <tr><td>Ngày trả dự kiến:</td><td><?= $booking['check_out'] ? date('d/m/Y', strtotime($booking['check_out'])) : '—' ?></td></tr>
                                    <tr><td>Số người:</td><td><?= $booking['adults'] ?? 1 ?> người</td></tr>
                                </table>
                            </div>
                        </div>
                        
                        <?php if (!empty($booking['note'])): ?>
                        <div class="mb-3">
                            <h6 class="text-muted">Ghi chú của bạn</h6>
                            <div class="bg-light p-3 rounded"><?= nl2br(htmlspecialchars($booking['note'])) ?></div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($status === 'PENDING'): ?>
                        <?php 
                        // Calculate remaining time using database time (避免 timezone issues)
                        $timeQuery = mysqli_fetch_assoc(mysqli_query($conn, "
                            SELECT TIMESTAMPDIFF(SECOND, NOW(), DATE_ADD(created_at, INTERVAL 15 MINUTE)) as remaining_seconds 
                            FROM bookings WHERE booking_id = $bookingId
                        "));
                        $remainingSeconds = (int)($timeQuery['remaining_seconds'] ?? 0);
                        $isExpired = $remainingSeconds <= 0;
                        
                        // Auto-expire if time is up
                        if ($isExpired) {
                            mysqli_query($conn, "UPDATE bookings SET status = 'CANCELLED', cancelled_at = NOW() WHERE booking_id = $bookingId AND status = 'PENDING'");
                            echo '<meta http-equiv="refresh" content="0">';
                        }
                        ?>
                        
                        <?php if (!$isExpired): ?>
                        <!-- Countdown Timer -->
                        <div class="alert alert-warning mb-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <i class="bi bi-clock me-2"></i>
                                    <strong>Phòng đang được giữ!</strong>
                                </div>
                                <div class="text-end">
                                    <span class="fs-5 fw-bold" id="countdown">--:--</span>
                                </div>
                            </div>
                            <hr class="my-2">
                            <small>Vui lòng hoàn tất thanh toán trước khi hết thời gian để giữ phòng.</small>
                        </div>
                        
                        <div class="d-flex gap-2">
                            <a href="/quanlyphongtro/client/index.php?page=huy_datphong&id=<?= $bookingId ?>" 
                               class="btn btn-outline-danger flex-fill"
                               onclick="return confirm('Bạn có chắc muốn hủy yêu cầu này?');">
                                <i class="bi bi-x-circle me-1"></i>Hủy yêu cầu
                            </a>
                            <!-- Direct payment button - redirect to VNPay -->
                            <form action="/quanlyphongtro/client/index.php" method="POST" class="flex-fill">
                                <input type="hidden" name="resume_payment" value="1">
                                <input type="hidden" name="booking_id" value="<?= $bookingId ?>">
                                <button type="submit" class="btn btn-success w-100">
                                    <i class="bi bi-credit-card me-1"></i>Thanh toán ngay
                                </button>
                            </form>
                        </div>
                        
                        <script>
                        // Countdown timer - uses remaining seconds calculated from database
                        (function() {
                            let remainingMs = <?= $remainingSeconds ?> * 1000;
                            const countdownEl = document.getElementById('countdown');
                            const startTime = Date.now();
                            
                            function updateCountdown() {
                                const elapsed = Date.now() - startTime;
                                const remaining = Math.max(0, remainingMs - elapsed);
                                
                                if (remaining <= 0) {
                                    countdownEl.innerHTML = '<span class="text-danger">Hết thời gian!</span>';
                                    setTimeout(() => location.reload(), 2000);
                                    return;
                                }
                                
                                const minutes = Math.floor(remaining / 60000);
                                const seconds = Math.floor((remaining % 60000) / 1000);
                                countdownEl.textContent = String(minutes).padStart(2, '0') + ':' + String(seconds).padStart(2, '0');
                                
                                // Change color when < 5 minutes
                                if (minutes < 5) {
                                    countdownEl.classList.add('text-danger');
                                } else if (minutes < 10) {
                                    countdownEl.classList.add('text-warning');
                                }
                            }
                            
                            updateCountdown();
                            setInterval(updateCountdown, 1000);
                        })();
                        </script>
                        <?php else: ?>
                        <div class="alert alert-danger">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            <strong>Hết thời gian thanh toán!</strong> Yêu cầu đặt phòng đã bị hủy.
                        </div>
                        <?php endif; ?>
                        <?php elseif ($status === 'DEPOSIT_PAID' || $status === 'CHECKED_IN'): ?>
                        <?php if (!empty($booking['contract_id'])): ?>
                        <!-- Đã có hợp đồng -->
                        <div class="alert alert-success border-success">
                            <div class="d-flex align-items-center mb-2">
                                <i class="bi bi-file-earmark-check fs-3 me-3"></i>
                                <div>
                                    <strong class="fs-5">Đã lập hợp đồng thuê phòng!</strong>
                                    <div class="text-muted">Mã HĐ: <strong><?= htmlspecialchars($booking['contract_code'] ?? 'N/A') ?></strong></div>
                                </div>
                            </div>
                            <hr>
                            <div class="row">
                                <div class="col-6">
                                    <small class="text-muted">Ngày bắt đầu:</small><br>
                                    <strong><?= $booking['contract_start'] ? date('d/m/Y', strtotime($booking['contract_start'])) : '—' ?></strong>
                                </div>
                                <div class="col-6">
                                    <small class="text-muted">Ngày kết thúc:</small><br>
                                    <strong><?= $booking['contract_end'] ? date('d/m/Y', strtotime($booking['contract_end'])) : 'Vô thời hạn' ?></strong>
                                </div>
                            </div>
                        </div>
                        <?php else: ?>
                        <!-- Chưa có hợp đồng -->
                        <div class="alert alert-info">
                            <i class="bi bi-hourglass-split me-2"></i>
                            <strong>Đã thanh toán!</strong> Chủ trọ sẽ liên hệ để lập hợp đồng và bàn giao phòng.
                        </div>
                        <?php endif; ?>
                        <div class="d-flex gap-2 flex-wrap">
                            <?php if (!empty($booking['contract_id']) && $booking['contract_status'] === 'ACTIVE'): ?>
                            <a href="/quanlyphongtro/client/index.php?page=giahan&id=<?= $bookingId ?>" 
                               class="btn btn-success">
                                <i class="bi bi-calendar-plus me-1"></i>Gia hạn hợp đồng
                            </a>
                            <?php endif; ?>
                            <a href="/quanlyphongtro/client/index.php?page=traphong&id=<?= $bookingId ?>" 
                               class="btn btn-warning"
                               onclick="return confirm('Bạn có chắc muốn yêu cầu trả phòng? Chủ trọ sẽ xác nhận sau.');">
                                <i class="bi bi-box-arrow-right me-1"></i>Yêu cầu trả phòng
                            </a>
                        </div>
                        <?php elseif ($status === 'CHECKED_OUT'): ?>
                        <div class="alert alert-secondary">
                            <i class="bi bi-box-arrow-right me-2"></i>
                            Bạn đã trả phòng. Cảm ơn bạn đã sử dụng dịch vụ!
                        </div>
                        <?php elseif ($status === 'CANCELLED'): ?>
                        <div class="alert alert-secondary">
                            <i class="bi bi-x-circle me-2"></i>
                            Yêu cầu này đã bị hủy.
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <?php if (!empty($renewalHistory)): ?>
                <!-- Lịch sử gia hạn -->
                <div class="card shadow-sm mt-4">
                    <div class="card-header bg-info text-white">
                        <h6 class="mb-0"><i class="bi bi-clock-history me-2"></i>Lịch sử gia hạn</h6>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Ngày</th>
                                        <th>Mô tả</th>
                                        <th class="text-end">Số tiền</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($renewalHistory as $rh): ?>
                                    <tr>
                                        <td><?= date('d/m/Y H:i', strtotime($rh['created_at'])) ?></td>
                                        <td><?= htmlspecialchars($rh['description']) ?></td>
                                        <td class="text-end text-success fw-bold">
                                            <?= number_format($rh['amount']) ?>đ
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Thông tin chủ trọ -->
            <div class="col-lg-4">
                <div class="card shadow-sm">
                    <div class="card-header bg-success text-white">
                        <h6 class="mb-0"><i class="bi bi-person me-2"></i>Thông tin chủ trọ</h6>
                    </div>
                    <div class="card-body">
                        <p class="mb-2"><strong><?= htmlspecialchars($booking['landlord_name'] ?? 'N/A') ?></strong></p>
                        <?php if ($booking['landlord_phone']): ?>
                        <p class="mb-2">
                            <i class="bi bi-telephone text-primary me-2"></i>
                            <a href="tel:<?= htmlspecialchars($booking['landlord_phone']) ?>"><?= htmlspecialchars($booking['landlord_phone']) ?></a>
                        </p>
                        <?php endif; ?>
                        <?php if ($booking['landlord_email']): ?>
                        <p class="mb-3">
                            <i class="bi bi-envelope text-primary me-2"></i>
                            <a href="mailto:<?= htmlspecialchars($booking['landlord_email']) ?>"><?= htmlspecialchars($booking['landlord_email']) ?></a>
                        </p>
                        <?php endif; ?>
                        
                        <?php if ($status !== 'CANCELLED'): ?>
                        <a href="tel:<?= htmlspecialchars($booking['landlord_phone']) ?>" class="btn btn-success w-100 mb-2">
                            <i class="bi bi-telephone me-1"></i>Gọi ngay
                        </a>
                        <?php endif; ?>
                        
                        <?php if ($booking['room_id']): ?>
                        <a href="/quanlyphongtro/client/index.php?page=chitiet_phong&room_id=<?= $booking['room_id'] ?>" class="btn btn-outline-primary w-100">
                            <i class="bi bi-eye me-1"></i>Xem phòng
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="mt-4">
                    <a href="/quanlyphongtro/client/index.php?page=lichsu_datphong" class="btn btn-outline-secondary w-100">
                        <i class="bi bi-arrow-left me-1"></i>Quay lại danh sách
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
