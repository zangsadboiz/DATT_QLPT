<?php
// client/pages/huy_datphong.php - Hủy yêu cầu đặt phòng

// Kiểm tra đăng nhập
if (!isset($_SESSION['user_id']) || ($_SESSION['role_name'] ?? '') !== 'STUDENT') {
    echo '<script>window.location.href="/quanlyphongtro/client/index.php?page=login&type=student";</script>';
    return;
}

$userId = (int)$_SESSION['user_id'];
$bookingId = (int)($_GET['id'] ?? 0);

if ($bookingId <= 0) {
    echo '<script>window.location.href="/quanlyphongtro/client/index.php?page=lichsu_datphong";</script>';
    return;
}

// Kiểm tra booking thuộc về user này và đang PENDING
$check = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT b.booking_id, b.status, b.booking_code
    FROM bookings b
    JOIN tenants t ON t.tenant_id = b.tenant_id
    WHERE b.booking_id = $bookingId AND t.user_id = $userId
"));

if (!$check) {
    echo '<div class="container py-5"><div class="alert alert-danger">Không tìm thấy yêu cầu đặt phòng!</div>
          <a href="/quanlyphongtro/client/index.php?page=lichsu_datphong" class="btn btn-primary">Quay lại</a></div>';
    return;
}

if ($check['status'] !== 'PENDING') {
    echo '<div class="container py-5"><div class="alert alert-warning">Không thể hủy yêu cầu này! Chỉ có thể hủy yêu cầu đang chờ xác nhận.</div>
          <a href="/quanlyphongtro/client/index.php?page=lichsu_datphong" class="btn btn-primary">Quay lại</a></div>';
    return;
}

// Thực hiện hủy
$result = mysqli_query($conn, "UPDATE bookings SET status = 'CANCELLED' WHERE booking_id = $bookingId");

if ($result) {
    echo '<div class="container py-5 text-center">
            <i class="fa fa-check-circle text-success" style="font-size: 80px;"></i>
            <h3 class="mt-4 text-success">Đã hủy yêu cầu đặt phòng!</h3>
            <p class="text-muted">Mã: <strong>' . htmlspecialchars($check['booking_code']) . '</strong></p>
            <a href="/quanlyphongtro/client/index.php?page=lichsu_datphong" class="btn btn-primary mt-3">
                <i class="fa fa-arrow-left me-2"></i>Xem lịch sử đặt phòng
            </a>
          </div>';
} else {
    echo '<div class="container py-5"><div class="alert alert-danger">Có lỗi xảy ra, vui lòng thử lại!</div>
          <a href="/quanlyphongtro/client/index.php?page=lichsu_datphong" class="btn btn-primary">Quay lại</a></div>';
}
