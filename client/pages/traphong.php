<?php
/**
 * Yêu cầu trả phòng - Sinh viên
 */
$userId = $_SESSION['user_id'] ?? 0;
if (!$userId) {
    header('Location: /quanlyphongtro/client/index.php?page=login');
    exit;
}

$bookingId = (int)($_GET['id'] ?? 0);
if ($bookingId <= 0) {
    header('Location: /quanlyphongtro/client/index.php?page=lichsu_datphong');
    exit;
}

// Lấy thông tin booking của SV
$sql = "
    SELECT b.*, r.room_code, bl.building_name, c.contract_id, c.contract_code
    FROM bookings b
    JOIN tenants t ON t.tenant_id = b.tenant_id
    JOIN rooms r ON r.room_id = b.room_id
    JOIN buildings bl ON bl.building_id = r.building_id
    LEFT JOIN contracts c ON c.contract_id = b.contract_id
    WHERE b.booking_id = $bookingId 
      AND t.user_id = $userId
      AND b.status IN ('DEPOSIT_PAID', 'CHECKED_IN')
    LIMIT 1
";
$result = mysqli_query($conn, $sql);
$booking = $result ? mysqli_fetch_assoc($result) : null;

if (!$booking) {
    echo '<div class="container py-5"><div class="alert alert-warning">Không tìm thấy thông tin đặt phòng hoặc phòng chưa được thuê.</div>
          <a href="/quanlyphongtro/client/index.php?page=lichsu_datphong" class="btn btn-primary">Quay lại</a></div>';
    return;
}

$success = false;
$error = '';

// Xử lý yêu cầu trả phòng
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_checkout'])) {
    $reason = trim($_POST['reason'] ?? '');
    $checkoutDate = $_POST['checkout_date'] ?? date('Y-m-d');
    
    // Validate ngày trả
    if ($checkoutDate < date('Y-m-d')) {
        $error = 'Ngày trả phòng không thể là ngày trong quá khứ.';
    } else {
        // Cập nhật booking - đánh dấu SV đã yêu cầu trả phòng
        $reasonEsc = mysqli_real_escape_string($conn, $reason);
        $result = mysqli_query($conn, "
            UPDATE bookings 
            SET status = 'CHECKED_OUT',
                check_out = '$checkoutDate',
                checked_out_at = NOW(),
                note = CONCAT(IFNULL(note,''), '\n[SV yêu cầu trả phòng lúc " . date('d/m/Y H:i') . ": $reasonEsc]')
            WHERE booking_id = $bookingId
        ");
        
        if ($result) {
            // Cập nhật hợp đồng nếu có
            if (!empty($booking['contract_id'])) {
                mysqli_query($conn, "
                    UPDATE contracts 
                    SET contract_status = 'ENDED',
                        end_date = '$checkoutDate',
                        note = CONCAT(IFNULL(note,''), ' | Trả phòng theo yêu cầu SV: $reasonEsc')
                    WHERE contract_id = " . (int)$booking['contract_id']
                );
            }
            
            // Cập nhật phòng thành trống
            mysqli_query($conn, "UPDATE rooms SET room_status = 'VACANT' WHERE room_id = " . (int)$booking['room_id']);
            
            $success = true;
        } else {
            $error = 'Có lỗi xảy ra, vui lòng thử lại.';
        }
    }
}
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow">
                <div class="card-header bg-warning text-dark">
                    <h5 class="mb-0"><i class="bi bi-box-arrow-right me-2"></i>Yêu cầu trả phòng</h5>
                </div>
                <div class="card-body">
                    <?php if ($success): ?>
                        <div class="alert alert-success">
                            <i class="bi bi-check-circle me-2"></i>
                            <strong>Đã gửi yêu cầu trả phòng thành công!</strong><br>
                            Cảm ơn bạn đã sử dụng dịch vụ. Chủ trọ sẽ liên hệ để hoàn tất thủ tục.
                        </div>
                        <a href="/quanlyphongtro/client/index.php?page=lichsu_datphong" class="btn btn-primary">
                            <i class="bi bi-arrow-left me-1"></i>Quay lại lịch sử
                        </a>
                    <?php else: ?>
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                        <?php endif; ?>
                        
                        <div class="alert alert-info">
                            <strong>Thông tin phòng đang thuê:</strong><br>
                            <i class="bi bi-door-open me-1"></i>Phòng: <strong><?= htmlspecialchars($booking['room_code']) ?></strong><br>
                            <i class="bi bi-building me-1"></i>Dãy: <?= htmlspecialchars($booking['building_name']) ?><br>
                            <?php if (!empty($booking['contract_code'])): ?>
                                <i class="bi bi-file-text me-1"></i>Hợp đồng: <?= htmlspecialchars($booking['contract_code']) ?>
                            <?php endif; ?>
                        </div>
                        
                        <form method="post">
                            <div class="mb-3">
                                <label class="form-label">Ngày trả phòng <span class="text-danger">*</span></label>
                                <input type="date" name="checkout_date" class="form-control" 
                                       value="<?= date('Y-m-d') ?>" min="<?= date('Y-m-d') ?>" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Lý do trả phòng (tùy chọn)</label>
                                <textarea name="reason" class="form-control" rows="3" 
                                          placeholder="Ví dụ: Hết hợp đồng, chuyển trọ, về quê..."></textarea>
                            </div>
                            <div class="d-flex gap-2">
                                <button type="submit" name="request_checkout" class="btn btn-warning"
                                        onclick="return confirm('Xác nhận yêu cầu trả phòng?');">
                                    <i class="bi bi-box-arrow-right me-1"></i>Xác nhận trả phòng
                                </button>
                                <a href="/quanlyphongtro/client/index.php?page=chitiet_datphong&id=<?= $bookingId ?>" 
                                   class="btn btn-secondary">
                                    <i class="bi bi-arrow-left me-1"></i>Quay lại
                                </a>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
