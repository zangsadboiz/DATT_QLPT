<?php
// client/pages/datphong.php - Trang đặt phòng cho sinh viên
$hotelier = '/quanlyphongtro/hotelier-1.0.0';

$postId = (int)($_GET['post_id'] ?? 0);
$roomId = (int)($_GET['room_id'] ?? 0);
$preCheckIn = $_GET['check_in'] ?? ''; // Ngày từ bộ lọc
$preCheckOut = $_GET['check_out'] ?? ''; // Ngày từ bộ lọc

// Kiểm tra đăng nhập
$isLoggedIn = isset($_SESSION['user_id']) && ($_SESSION['role_name'] ?? '') === 'STUDENT';

// Lấy thông tin sinh viên đã đăng nhập
$studentInfo = null;
if ($isLoggedIn) {
    $userId = (int)$_SESSION['user_id'];
    $studentInfo = mysqli_fetch_assoc(mysqli_query($conn, "SELECT full_name, phone, email FROM users WHERE user_id = $userId"));
}

$bookingSuccess = false;
$bookingError = '';

// POST processing đã được xử lý trong client/index.php (PRE-PROCESS section)
// Phần này chỉ để hiển thị form


// Lấy thông tin phòng - ưu tiên room_id
$room = null;
$post = null;
$primaryImg = null;

if ($roomId > 0) {
    // Đặt theo room_id (không cần post)
    $sql = "
      SELECT r.*, b.building_name, b.address, b.building_status,
             d.district_name, pr.province_name,
             u.full_name as landlord_name, u.phone as landlord_phone,
             r.rental_type, r.daily_price
      FROM rooms r
      JOIN buildings b ON b.building_id = r.building_id
      LEFT JOIN districts d ON d.district_id = b.district_id
      LEFT JOIN provinces pr ON pr.province_id = d.province_id
      JOIN users u ON u.user_id = b.owner_id
      WHERE r.room_id = ? AND r.deleted_at IS NULL AND b.building_status = 'ACTIVE'
      LIMIT 1
    ";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $roomId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $room = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);
    
    // Lấy ảnh phòng
    if ($room) {
        $rsImg = mysqli_query($conn, "SELECT image_path FROM room_images WHERE room_id = $roomId ORDER BY is_primary DESC LIMIT 1");
        if ($rsImg && $img = mysqli_fetch_assoc($rsImg)) {
            $primaryImg = '/quanlyphongtro/uploads/rooms/' . $img['image_path'];
        } elseif (!empty($room['image'])) {
            $primaryImg = '/quanlyphongtro/uploads/rooms/' . $room['image'];
        }
    }
} elseif ($postId > 0) {
    // Đặt theo post_id (cách cũ)
    $sql = "
      SELECT p.*, d.district_name, pr.province_name,
             u.full_name as landlord_name, u.phone as landlord_phone
      FROM posts p
      JOIN districts d ON d.district_id = p.district_id
      JOIN provinces pr ON pr.province_id = d.province_id
      JOIN users u ON u.user_id = p.user_id
      WHERE p.post_id = ? AND p.status = 'APPROVED'
      LIMIT 1
    ";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $postId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $post = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);
    
    // Lấy ảnh tin đăng
    if ($post) {
        $rsImg = mysqli_query($conn, "SELECT image_path FROM post_images WHERE post_id = $postId ORDER BY is_primary DESC LIMIT 1");
        if ($rsImg && $img = mysqli_fetch_assoc($rsImg)) {
            $primaryImg = '/quanlyphongtro/uploads/posts/' . $img['image_path'];
        }
        $roomId = (int)($post['room_id'] ?? 0);
    }
}

if (!$room && !$post) {
    echo '<div class="container py-5"><div class="alert alert-warning">Vui lòng chọn phòng cần đặt.</div></div>';
    return;
}

// Chuẩn hóa dữ liệu để hiển thị
$displayData = [
    'title' => $room ? 'Phòng ' . ($room['room_code'] ?? '') : ($post['title'] ?? 'Phòng trọ'),
    'address' => $room ? $room['address'] : ($post['address'] ?? ''),
    'district' => $room ? ($room['district_name'] ?? '') : ($post['district_name'] ?? ''),
    'price' => $room ? ($room['base_rent'] ?? 0) : ($post['price'] ?? 0),
    'area' => $room ? ($room['area'] ?? 0) : ($post['area'] ?? 0),
    'landlord_name' => $room ? ($room['landlord_name'] ?? '') : ($post['landlord_name'] ?? ''),
    'landlord_phone' => $room ? ($room['landlord_phone'] ?? '') : ($post['landlord_phone'] ?? ''),
    'rental_type' => $room ? ($room['rental_type'] ?? 'MONTHLY') : 'MONTHLY',
    'daily_price' => $room ? ($room['daily_price'] ?? 0) : 0,
];

$postTypes = ['ROOM' => 'Phòng trọ', 'APARTMENT' => 'Căn hộ', 'HOUSE' => 'Nhà nguyên căn'];
?>

<!-- Page Header Start -->
<div class="container-fluid page-header mb-5 p-0" style="background-image: url(<?= $hotelier ?>/img/carousel-1.jpg);">
    <div class="container-fluid page-header-inner py-5">
        <div class="container text-center pb-5">
            <h1 class="display-3 text-white mb-3 animated slideInDown">Đặt phòng</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb justify-content-center text-uppercase">
                    <li class="breadcrumb-item"><a href="/quanlyphongtro/client/index.php?page=home" class="text-white">Trang chủ</a></li>
                    <?php if ($roomId): ?>
                        <li class="breadcrumb-item"><a href="/quanlyphongtro/client/index.php?page=chitiet_phong&room_id=<?= $roomId ?>" class="text-white">Chi tiết phòng</a></li>
                    <?php endif; ?>
                    <li class="breadcrumb-item text-white active">Đặt phòng</li>
                </ol>
            </nav>
        </div>
    </div>
</div>
<!-- Page Header End -->

<div class="container-xxl py-5">
    <div class="container">
        <div class="row g-5">
            <!-- Left: Thông tin phòng -->
            <div class="col-lg-5">
                <div class="wow fadeInUp" data-wow-delay="0.1s">
                    <div class="card shadow-sm">
                        <?php if ($primaryImg): ?>
                            <img src="<?= htmlspecialchars($primaryImg) ?>" class="card-img-top" alt="" style="height: 250px; object-fit: cover;">
                        <?php else: ?>
                            <div class="bg-light d-flex align-items-center justify-content-center" style="height: 250px;">
                                <i class="bi bi-image text-muted" style="font-size: 60px;"></i>
                            </div>
                        <?php endif; ?>
                        <div class="card-body">
                            <h5 class="card-title"><?= htmlspecialchars($displayData['title']) ?></h5>
                            <p class="text-muted mb-2">
                                <i class="fa fa-map-marker-alt text-primary me-2"></i>
                                <?= htmlspecialchars($displayData['address']) ?><?= $displayData['district'] ? ', ' . htmlspecialchars($displayData['district']) : '' ?>
                            </p>
                            <hr>
                            <div class="d-flex justify-content-between mb-2">
                                <span>Giá thuê:</span>
                                <?php if ($displayData['rental_type'] === 'DAILY'): ?>
                                    <strong class="text-primary"><?= number_format((float)$displayData['daily_price']) ?>đ/ngày</strong>
                                <?php else: ?>
                                    <strong class="text-primary"><?= number_format((float)$displayData['price']) ?>đ/tháng</strong>
                                <?php endif; ?>
                            </div>
                            <?php if ($displayData['rental_type'] === 'DAILY'): ?>
                            <div class="mb-2">
                                <span class="badge bg-info"><i class="bi bi-calendar-day me-1"></i>Thuê theo ngày</span>
                            </div>
                            <?php endif; ?>
                            <?php if ($displayData['area']): ?>
                            <div class="d-flex justify-content-between mb-2">
                                <span>Diện tích:</span>
                                <strong><?= $displayData['area'] ?>m²</strong>
                            </div>
                            <?php endif; ?>
                            <?php if ($room && ($room['deposit_default'] ?? 0) > 0): ?>
                                <div class="d-flex justify-content-between mb-2">
                                    <span>Tiền cọc:</span>
                                    <strong><?= number_format((float)$room['deposit_default']) ?>đ</strong>
                                </div>
                            <?php elseif ($post && ($post['deposit'] ?? 0) > 0): ?>
                                <div class="d-flex justify-content-between mb-2">
                                    <span>Tiền cọc:</span>
                                    <strong><?= number_format((float)$post['deposit']) ?>đ</strong>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="card mt-4 shadow-sm">
                        <div class="card-body">
                            <h6><i class="fa fa-user text-primary me-2"></i>Thông tin chủ trọ</h6>
                            <p class="mb-1"><strong><?= htmlspecialchars($displayData['landlord_name']) ?></strong></p>
                            <p class="mb-0">
                                <i class="fa fa-phone-alt text-primary me-2"></i>
                                <a href="tel:<?= htmlspecialchars($displayData['landlord_phone']) ?>"><?= htmlspecialchars($displayData['landlord_phone']) ?></a>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Right: Form đặt phòng hoặc yêu cầu đăng nhập -->
            <div class="col-lg-7">
                <div class="wow fadeInUp" data-wow-delay="0.2s">
                    <?php if (!$isLoggedIn): ?>
                        <!-- Yêu cầu đăng nhập -->
                        <div class="bg-light rounded p-5 text-center">
                            <i class="fa fa-lock text-primary mb-4" style="font-size: 60px;"></i>
                            <h4 class="mb-3">Vui lòng đăng nhập để đặt phòng</h4>
                            <p class="text-muted mb-4">Bạn cần có tài khoản sinh viên để gửi yêu cầu đặt phòng.</p>
                            <a href="/quanlyphongtro/client/index.php?page=login&return=<?= urlencode('/quanlyphongtro/client/index.php?page=datphong&post_id=' . $postId) ?>" class="btn btn-primary py-3 px-5">
                                <i class="fa fa-sign-in-alt me-2"></i>Đăng nhập ngay
                            </a>
                            <p class="mt-3 mb-0">
                                Chưa có tài khoản? <a href="/quanlyphongtro/client/index.php?page=register&type=student">Đăng ký</a>
                            </p>
                        </div>
                    <?php else: ?>
                        <!-- Form đặt phòng -->
                        <div class="bg-light rounded p-4 p-lg-5">
                            <?php if ($bookingSuccess): ?>
                                <div class="text-center py-4">
                                    <i class="fa fa-check-circle text-success mb-4" style="font-size: 80px;"></i>
                                    <h4 class="text-success mb-3">Đặt phòng thành công!</h4>
                                    <p class="mb-4">Yêu cầu đặt phòng của bạn đã được gửi đến chủ trọ.<br>Chủ trọ sẽ liên hệ với bạn trong thời gian sớm nhất.</p>
                                    <a href="/quanlyphongtro/client/index.php?page=lichsu_datphong" class="btn btn-primary py-2 px-4">
                                        <i class="fa fa-list me-2"></i>Xem lịch sử đặt phòng
                                    </a>
                                    <a href="/quanlyphongtro/client/index.php?page=home" class="btn btn-outline-secondary py-2 px-4 ms-2">
                                        <i class="fa fa-home me-2"></i>Về trang chủ
                                    </a>
                                </div>
                            <?php else: ?>
                                <h4 class="mb-4"><i class="fa fa-calendar-check text-primary me-2"></i>Gửi yêu cầu đặt phòng</h4>
                                
                                <?php if ($bookingError): ?>
                                    <div class="alert alert-danger"><?= $bookingError ?></div>
                                <?php endif; ?>
                                
                                <div class="alert alert-info">
                                    <i class="bi bi-info-circle me-2"></i>
                                    Sau khi gửi yêu cầu, chủ trọ sẽ liên hệ với bạn để xác nhận.
                                </div>
                                
                                <form method="POST" action="">
                                    <input type="hidden" name="post_id" value="<?= $postId ?>">
                                    <input type="hidden" name="room_id" value="<?= $roomId ?>">
                                    
                                <div class="row g-3">
                                    <div class="col-12">
                                        <label class="form-label">Họ tên <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control py-3" name="full_name" 
                                               value="<?= htmlspecialchars($studentInfo['full_name'] ?? '') ?>" required>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <label class="form-label">Số điện thoại <span class="text-danger">*</span></label>
                                        <input type="tel" class="form-control py-3" name="phone" 
                                               value="<?= htmlspecialchars($studentInfo['phone'] ?? '') ?>" required>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <label class="form-label">Email</label>
                                        <input type="email" class="form-control py-3" name="email"
                                               value="<?= htmlspecialchars($studentInfo['email'] ?? '') ?>">
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <?php if ($displayData['rental_type'] === 'DAILY'): ?>
                                            <label class="form-label">Ngày check-in <span class="text-danger">*</span></label>
                                        <?php else: ?>
                                            <label class="form-label">Dự kiến nhận phòng <span class="text-danger">*</span></label>
                                        <?php endif; ?>
                                        <input type="date" class="form-control py-3" name="move_in_date"
                                               value="<?= htmlspecialchars($preCheckIn) ?>"
                                               min="<?= date('Y-m-d') ?>" required id="move_in_date">
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <?php if ($displayData['rental_type'] === 'DAILY'): ?>
                                            <label class="form-label">Ngày check-out <span class="text-danger">*</span></label>
                                            <input type="date" class="form-control py-3" name="check_out_date"
                                                   value="<?= htmlspecialchars($preCheckOut) ?>"
                                                   min="<?= date('Y-m-d', strtotime('+1 day')) ?>" required id="check_out_date">
                                            <small class="text-muted">Tối thiểu 1 ngày</small>
                                        <?php else: ?>
                                            <label class="form-label">Dự kiến trả phòng <span class="text-danger">*</span></label>
                                            <input type="date" class="form-control py-3" name="check_out_date"
                                                   value="<?= htmlspecialchars($preCheckOut) ?>"
                                                   min="<?= date('Y-m-d', strtotime('+1 month +1 day')) ?>" required id="check_out_date">
                                            <small class="text-muted">Tối thiểu 1 tháng sau ngày nhận</small>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <?php if ($displayData['rental_type'] !== 'DAILY'): ?>
                                    <div class="col-md-6">
                                        <label class="form-label">Ngày muốn xem phòng</label>
                                        <input type="date" class="form-control py-3" name="view_date" 
                                               min="<?= date('Y-m-d') ?>" id="view_date">
                                        <small class="text-muted">Chọn ngày từ hôm nay đến ngày trả phòng</small>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <div class="col-12">
                                        <label class="form-label">Lời nhắn cho chủ trọ</label>
                                        <textarea name="message" class="form-control" rows="3" placeholder="VD: Tôi là sinh viên năm 3, muốn thuê phòng lâu dài..."></textarea>
                                    </div>
                                    
                                    <?php if ($displayData['rental_type'] === 'DAILY'): ?>
                                    <!-- Tính tiền cho thuê ngày -->
                                    <div class="col-12">
                                        <div class="card bg-light">
                                            <div class="card-body">
                                                <h6 class="mb-3"><i class="bi bi-calculator me-2"></i>Chi tiết thanh toán</h6>
                                                <div class="d-flex justify-content-between mb-2">
                                                    <span>Giá thuê:</span>
                                                    <strong><?= number_format((float)$displayData['daily_price']) ?>đ/ngày</strong>
                                                </div>
                                                <div class="d-flex justify-content-between mb-2">
                                                    <span>Số ngày:</span>
                                                    <strong id="numDays">--</strong>
                                                </div>
                                                <hr>
                                                <div class="d-flex justify-content-between">
                                                    <span class="fs-5">Tổng tiền:</span>
                                                    <strong class="fs-5 text-danger" id="totalPrice">--</strong>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="col-12">
                                        <div class="alert alert-success">
                                            <i class="bi bi-credit-card me-2"></i>
                                            Thanh toán toàn bộ qua <strong>VNPay</strong> để hoàn tất đặt phòng.
                                        </div>
                                    </div>
                                    
                                    <div class="col-12">
                                        <input type="hidden" name="rental_type" value="DAILY">
                                        <input type="hidden" name="daily_price" value="<?= $displayData['daily_price'] ?>">
                                        <button type="submit" name="submit_booking" class="btn btn-success w-100 py-3">
                                            <i class="bi bi-credit-card me-2"></i>Thanh toán đặt phòng
                                        </button>
                                    </div>
                                    <?php else: ?>
                                    <div class="col-12">
                                        <div class="alert alert-info">
                                            <i class="bi bi-info-circle me-2"></i>
                                            Sau khi gửi yêu cầu, chủ trọ sẽ liên hệ với bạn để xác nhận và hẹn xem phòng.
                                        </div>
                                    </div>
                                    
                                    <div class="col-12">
                                        <input type="hidden" name="rental_type" value="MONTHLY">
                                        <button type="submit" name="submit_booking" class="btn btn-primary w-100 py-3">
                                            <i class="bi bi-send me-2"></i>Gửi yêu cầu đặt phòng
                                        </button>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </form>
                            
                            <p class="text-muted mt-3 mb-0 small">
                                <i class="bi bi-shield-check me-1"></i>
                                Thông tin của bạn được bảo mật và chỉ chia sẻ với chủ trọ.
                            </p>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Giá theo ngày (nếu có)
const dailyPrice = <?= json_encode((float)($displayData['daily_price'] ?? 0)) ?>;
const isDaily = <?= json_encode($displayData['rental_type'] === 'DAILY') ?>;

// Hàm tính và hiển thị tổng tiền
function calculateDailyTotal() {
    if (!isDaily) return;
    
    const checkIn = document.getElementById('move_in_date');
    const checkOut = document.getElementById('check_out_date');
    const numDaysEl = document.getElementById('numDays');
    const totalPriceEl = document.getElementById('totalPrice');
    
    if (checkIn && checkOut && checkIn.value && checkOut.value) {
        const d1 = new Date(checkIn.value);
        const d2 = new Date(checkOut.value);
        const diffTime = d2 - d1;
        const numDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
        
        if (numDays > 0) {
            numDaysEl.textContent = numDays + ' ngày';
            totalPriceEl.textContent = new Intl.NumberFormat('vi-VN').format(numDays * dailyPrice) + 'đ';
        } else {
            numDaysEl.textContent = '--';
            totalPriceEl.textContent = '--';
        }
    } else {
        if (numDaysEl) numDaysEl.textContent = '--';
        if (totalPriceEl) totalPriceEl.textContent = '--';
    }
}

// Khi thay đổi ngày nhận phòng
document.getElementById('move_in_date')?.addEventListener('change', function() {
    const checkOut = document.getElementById('check_out_date');
    
    // Cập nhật min checkout
    if (this.value && checkOut) {
        const moveIn = new Date(this.value);
        
        if (isDaily) {
            moveIn.setDate(moveIn.getDate() + 1);
        } else {
            moveIn.setMonth(moveIn.getMonth() + 1);
        }
        
        const minCheckOut = moveIn.toISOString().split('T')[0];
        checkOut.min = minCheckOut;
        
        if (checkOut.value && checkOut.value < minCheckOut) {
            checkOut.value = minCheckOut;
        }
        
        // Trigger checkout change to update view_date max
        checkOut.dispatchEvent(new Event('change'));
    }
    
    calculateDailyTotal();
});

// Khi thay đổi ngày checkout - cập nhật max ngày xem = ngày trả
document.getElementById('check_out_date')?.addEventListener('change', function() {
    const viewDate = document.getElementById('view_date');
    
    if (this.value && viewDate) {
        viewDate.max = this.value;
        
        if (viewDate.value && viewDate.value > this.value) {
            viewDate.value = this.value;
        }
    }
    
    calculateDailyTotal();
});

// Trigger khi load - move_in_date trước để set max cho view_date
if (document.getElementById('move_in_date')?.value) {
    document.getElementById('move_in_date').dispatchEvent(new Event('change'));
}
calculateDailyTotal();
</script>
