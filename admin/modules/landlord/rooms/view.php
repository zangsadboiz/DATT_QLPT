<?php
/**
 * Module Phòng - Xem chi tiết
 */
require_once __DIR__ . '/../../../includes/auth.php';
require_landlord_login();

$userId = (int)($_SESSION['user_id'] ?? 0);
$roomId = (int)($_GET['id'] ?? 0);

if ($roomId <= 0) {
    header('Location: ' . ADMIN_BASE_PATH . '/modules/landlord/rooms/index.php');
    exit;
}

$room = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT r.*, b.owner_id, b.building_name, b.building_status, b.address as building_address, d.district_name
    FROM rooms r
    JOIN buildings b ON b.building_id = r.building_id
    LEFT JOIN districts d ON d.district_id = b.district_id
    WHERE r.room_id = $roomId AND b.owner_id = $userId AND r.deleted_at IS NULL
"));

if (!$room) {
    $_SESSION['alert'] = ['type' => 'danger', 'message' => 'Không tìm thấy phòng!'];
    header('Location: ' . ADMIN_BASE_PATH . '/modules/landlord/rooms/index.php');
    exit;
}

// Get images
$images = [];
$rsImg = mysqli_query($conn, "SELECT * FROM room_images WHERE room_id = $roomId ORDER BY is_primary DESC, sort_order");
while ($rsImg && ($img = mysqli_fetch_assoc($rsImg))) $images[] = $img;

// Get amenities
$amenities = json_decode($room['amenities'] ?: '[]', true) ?: [];
$amenityLabels = [
    'wifi' => 'WiFi miễn phí',
    'ac' => 'Điều hòa',
    'wc_rieng' => 'WC riêng',
    'bep' => 'Bếp nấu ăn',
    'tu_lanh' => 'Tủ lạnh',
    'may_giat' => 'Máy giặt',
    'ban_cong' => 'Ban công',
    'gac_lung' => 'Gác lửng',
    'nong_lanh' => 'Nóng lạnh',
    'giuong' => 'Giường',
    'tu_quan_ao' => 'Tủ quần áo',
    'ke_bep' => 'Kệ bếp',
    'ban_ghe' => 'Bàn ghế'
];

// Get current active contract
$currentContract = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT c.*, t.full_name as tenant_name, t.phone as tenant_phone, t.email as tenant_email
    FROM contracts c
    LEFT JOIN contract_tenants ct ON ct.contract_id = c.contract_id AND ct.is_representative = 1
    LEFT JOIN tenants t ON t.tenant_id = ct.tenant_id
    WHERE c.room_id = $roomId AND c.contract_status = 'ACTIVE'
    ORDER BY c.start_date DESC
    LIMIT 1
"));

// Get recent bookings for this room
$recentBookings = mysqli_query($conn, "
    SELECT bk.*, t.full_name as tenant_name, t.phone as tenant_phone
    FROM bookings bk
    LEFT JOIN tenants t ON t.tenant_id = bk.tenant_id
    WHERE bk.room_id = $roomId
    ORDER BY bk.created_at DESC
    LIMIT 5
");

// Determine dynamic room status
$today = date('Y-m-d');
$dynamicStatus = $room['room_status']; // Default to DB status

// Check for active contract
if ($currentContract) {
    $dynamicStatus = 'OCCUPIED';
}

// Check for pending/confirmed bookings
$pendingBooking = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT bk.*, t.full_name
    FROM bookings bk
    LEFT JOIN tenants t ON t.tenant_id = bk.tenant_id
    WHERE bk.room_id = $roomId 
      AND bk.status IN ('CONFIRMED', 'DEPOSIT_PAID')
      AND bk.check_in >= '$today'
    ORDER BY bk.check_in ASC
    LIMIT 1
"));

// Status badge helper
function getRoomStatusBadge($status, $pendingBooking = null, $currentContract = null) {
    if ($status === 'MAINTENANCE') {
        return '<span class="badge bg-warning text-dark fs-6">Bảo trì</span>';
    }
    if ($currentContract) {
        return '<span class="badge bg-primary fs-6">Đang thuê</span>';
    }
    if ($pendingBooking) {
        if ($pendingBooking['status'] === 'DEPOSIT_PAID') {
            return '<span class="badge bg-info fs-6">Đã thanh toán - chờ nhận</span>';
        }
        return '<span class="badge bg-warning text-dark fs-6">Chờ thanh toán</span>';
    }
    return '<span class="badge bg-success fs-6">Còn trống</span>';
}


require_once __DIR__ . '/../../../includes/header.php';
?>

<div class="pagetitle">
    <h1><i class="bi bi-door-open me-2"></i>Chi tiết phòng: <?= htmlspecialchars($room['room_code']) ?></h1>
    <nav>
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= ADMIN_BASE_PATH ?>/modules/landlord/dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="<?= ADMIN_BASE_PATH ?>/modules/landlord/rooms/index.php?building_id=<?= $room['building_id'] ?>">Phòng</a></li>
            <li class="breadcrumb-item active"><?= htmlspecialchars($room['room_code']) ?></li>
        </ol>
    </nav>
</div>

<section class="section">
    <div class="row">
        <!-- Cột trái: Ảnh -->
        <div class="col-lg-5">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Ảnh phòng</h5>
                </div>
                <div class="card-body">
                    <?php if (count($images) > 0): ?>
                        <div id="roomCarousel" class="carousel slide" data-bs-ride="carousel">
                            <div class="carousel-inner">
                                <?php foreach ($images as $i => $img): ?>
                                    <div class="carousel-item <?= $i === 0 ? 'active' : '' ?>">
                                        <img src="/quanlyphongtro/uploads/rooms/<?= htmlspecialchars($img['image_path']) ?>" 
                                             class="d-block w-100 rounded" style="height: 300px; object-fit: cover;">
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <?php if (count($images) > 1): ?>
                                <button class="carousel-control-prev" type="button" data-bs-target="#roomCarousel" data-bs-slide="prev">
                                    <span class="carousel-control-prev-icon"></span>
                                </button>
                                <button class="carousel-control-next" type="button" data-bs-target="#roomCarousel" data-bs-slide="next">
                                    <span class="carousel-control-next-icon"></span>
                                </button>
                            <?php endif; ?>
                        </div>
                        <div class="row g-2 mt-2">
                            <?php foreach ($images as $i => $img): ?>
                                <div class="col-3">
                                    <img src="/quanlyphongtro/uploads/rooms/<?= htmlspecialchars($img['image_path']) ?>" 
                                         class="img-fluid rounded cursor-pointer" 
                                         style="height: 60px; width: 100%; object-fit: cover; cursor: pointer;"
                                         onclick="$('#roomCarousel').carousel(<?= $i ?>)">
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5 bg-light rounded">
                            <i class="bi bi-image" style="font-size: 48px; color: #ddd;"></i>
                            <p class="text-muted mt-2">Chưa có ảnh</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Tiện nghi -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Tiện nghi</h5>
                </div>
                <div class="card-body">
                    <?php if (count($amenities) > 0): ?>
                        <div class="row">
                            <?php foreach ($amenities as $a): ?>
                                <div class="col-6 mb-2">
                                    <i class="bi bi-check-circle-fill text-success me-1"></i>
                                    <?= $amenityLabels[$a] ?? $a ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-muted mb-0">Chưa có tiện nghi</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Cột phải: Thông tin -->
        <div class="col-lg-7">
            <!-- Thông tin cơ bản -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Thông tin phòng</h5>
                    <div>
                        <?php if (($room['building_status'] ?? '') === 'HIDDEN'): ?>
                            <span class="badge bg-secondary fs-6 me-1">Dãy trọ ẩn</span>
                        <?php endif; ?>
                        <?= getRoomStatusBadge($room['room_status'], $pendingBooking, $currentContract) ?>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="text-muted small">Dãy trọ</label>
                            <p class="mb-0 fw-bold"><?= htmlspecialchars($room['building_name']) ?></p>
                            <small class="text-muted"><?= htmlspecialchars($room['building_address']) ?></small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="text-muted small">Mã phòng</label>
                            <p class="mb-0 fw-bold"><?= htmlspecialchars($room['room_code']) ?></p>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-4 mb-3">
                            <label class="text-muted small">Tầng</label>
                            <p class="mb-0 fw-bold">Tầng <?= (int)$room['floor'] ?></p>
                        </div>
                        <div class="col-4 mb-3">
                            <label class="text-muted small">Diện tích</label>
                            <p class="mb-0 fw-bold"><?= $room['area'] ? $room['area'] . ' m²' : '-' ?></p>
                        </div>
                        <div class="col-4 mb-3">
                            <label class="text-muted small">Số người tối đa</label>
                            <p class="mb-0 fw-bold"><?= (int)$room['max_occupants'] ?> người</p>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <div class="row">
                        <?php 
                        $rentalType = $room['rental_type'] ?? 'MONTHLY';
                        $dailyPrice = (float)($room['daily_price'] ?? 0);
                        $baseRent = (float)($room['base_rent'] ?? 0);
                        ?>
                        <?php if ($rentalType === 'DAILY'): ?>
                        <div class="col-md-6 mb-3">
                            <label class="text-muted small">Giá thuê ngày</label>
                            <p class="mb-0 fs-4 fw-bold text-primary"><?= number_format($dailyPrice) ?>đ<small class="text-muted">/ngày</small></p>
                        </div>
                        <?php else: ?>
                        <div class="col-md-6 mb-3">
                            <label class="text-muted small">Giá thuê tháng</label>
                            <p class="mb-0 fs-4 fw-bold text-danger"><?= number_format($baseRent) ?>đ<small class="text-muted">/tháng</small></p>
                        </div>
                        <?php endif; ?>
                        <div class="col-md-6 mb-3">
                            <label class="text-muted small">Tiền đặt cọc</label>
                            <p class="mb-0 fw-bold"><?= $room['deposit'] ? number_format((float)$room['deposit']) . 'đ' : 'Không yêu cầu' ?></p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Phí dịch vụ -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Phí dịch vụ</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-6 col-md-3 mb-3">
                            <label class="text-muted small">Giá điện</label>
                            <p class="mb-0 fw-bold"><?= $room['electricity_price'] ? number_format((float)$room['electricity_price']) . 'đ/kWh' : '-' ?></p>
                        </div>
                        <div class="col-6 col-md-3 mb-3">
                            <label class="text-muted small">Giá nước</label>
                            <p class="mb-0 fw-bold"><?= $room['water_price'] ? number_format((float)$room['water_price']) . 'đ/m³' : '-' ?></p>
                        </div>
                        <div class="col-6 col-md-3 mb-3">
                            <label class="text-muted small">Internet</label>
                            <p class="mb-0 fw-bold"><?= $room['internet_price'] ? number_format((float)$room['internet_price']) . 'đ/tháng' : '-' ?></p>
                        </div>
                        <div class="col-6 col-md-3 mb-3">
                            <label class="text-muted small">Gửi xe</label>
                            <p class="mb-0 fw-bold"><?= $room['parking_price'] ? number_format((float)$room['parking_price']) . 'đ/tháng' : '-' ?></p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Mô tả & Nội quy -->
            <?php if ($room['description'] || $room['rules']): ?>
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Mô tả & Nội quy</h5>
                </div>
                <div class="card-body">
                    <?php if ($room['description']): ?>
                        <h6>Mô tả phòng</h6>
                        <p><?= nl2br(htmlspecialchars($room['description'])) ?></p>
                    <?php endif; ?>
                    
                    <?php if ($room['rules']): ?>
                        <h6>Nội quy</h6>
                        <div class="alert alert-warning mb-0">
                            <?= nl2br(htmlspecialchars($room['rules'])) ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Người thuê hiện tại / Booking sắp tới -->
            <?php if ($currentContract || $pendingBooking): ?>
            <div class="card border-primary">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="bi bi-person-check me-2"></i>
                        <?= $currentContract ? 'Người đang thuê' : 'Đặt phòng sắp tới' ?>
                    </h5>
                </div>
                <div class="card-body">
                    <?php if ($currentContract): ?>
                        <div class="row">
                            <div class="col-md-6">
                                <label class="text-muted small">Người thuê</label>
                                <p class="mb-1 fw-bold fs-5"><?= htmlspecialchars($currentContract['tenant_name'] ?? 'Chưa có') ?></p>
                            </div>
                            <div class="col-md-6">
                                <label class="text-muted small">SĐT</label>
                                <p class="mb-1">
                                    <a href="tel:<?= htmlspecialchars($currentContract['tenant_phone'] ?? '') ?>">
                                        <i class="bi bi-telephone me-1"></i><?= htmlspecialchars($currentContract['tenant_phone'] ?? '-') ?>
                                    </a>
                                </p>
                            </div>
                            <div class="col-md-6">
                                <label class="text-muted small">Mã hợp đồng</label>
                                <p class="mb-1">
                                    <a href="<?= ADMIN_BASE_PATH ?>/modules/hopdong_owner/view.php?id=<?= $currentContract['contract_id'] ?>">
                                        <?= htmlspecialchars($currentContract['contract_code']) ?>
                                    </a>
                                </p>
                            </div>
                            <div class="col-md-6">
                                <label class="text-muted small">Thời hạn</label>
                                <p class="mb-1">
                                    <?= date('d/m/Y', strtotime($currentContract['start_date'])) ?>
                                    → <?= $currentContract['end_date'] ? date('d/m/Y', strtotime($currentContract['end_date'])) : 'Vô thời hạn' ?>
                                </p>
                            </div>
                        </div>
                    <?php elseif ($pendingBooking): ?>
                        <div class="row">
                            <div class="col-md-6">
                                <label class="text-muted small">Người đặt</label>
                                <p class="mb-1 fw-bold"><?= htmlspecialchars($pendingBooking['full_name'] ?? 'N/A') ?></p>
                            </div>
                            <div class="col-md-6">
                                <label class="text-muted small">Trạng thái</label>
                                <p class="mb-1">
                                    <?php if ($pendingBooking['status'] === 'DEPOSIT_PAID'): ?>
                                        <span class="badge bg-success">Đang thuê</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning text-dark">Chờ thanh toán</span>
                                    <?php endif; ?>
                                </p>
                            </div>
                            <div class="col-md-6">
                                <label class="text-muted small">Ngày nhận phòng</label>
                                <p class="mb-1"><?= date('d/m/Y', strtotime($pendingBooking['check_in'])) ?></p>
                            </div>
                            <div class="col-md-6">
                                <label class="text-muted small">Mã đặt phòng</label>
                                <p class="mb-1">
                                    <a href="<?= ADMIN_BASE_PATH ?>/modules/yeucau_thue_owner/detail.php?id=<?= $pendingBooking['booking_id'] ?>">
                                        <?= htmlspecialchars($pendingBooking['booking_code']) ?>
                                    </a>
                                </p>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Lịch sử đặt phòng -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-clock-history me-2"></i>Yêu cầu đặt phòng gần đây</h5>
                </div>
                <div class="card-body p-0">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Mã</th>
                                <th>Người đặt</th>
                                <th>Nhận</th>
                                <th>Trả</th>
                                <th>Trạng thái</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $hasBookings = false;
                            while ($bk = mysqli_fetch_assoc($recentBookings)): 
                                $hasBookings = true;
                                $bkStatus = $bk['status'] ?? 'PENDING';
                                $bkBadges = [
                                    'PENDING' => '<span class="badge bg-warning text-dark">Chờ duyệt</span>',
                                    'CONFIRMED' => '<span class="badge bg-info">Chờ thanh toán</span>',
                                    'DEPOSIT_PAID' => '<span class="badge bg-success">Đang thuê</span>',
                                    'CHECKED_IN' => '<span class="badge bg-primary">Đang ở</span>',
                                    'CHECKED_OUT' => '<span class="badge bg-secondary">Đã trả</span>',
                                    'CANCELLED' => '<span class="badge bg-danger">Đã hủy</span>',
                                ];
                            ?>
                                <tr>
                                    <td>
                                        <a href="<?= ADMIN_BASE_PATH ?>/modules/yeucau_thue_owner/detail.php?id=<?= $bk['booking_id'] ?>">
                                            <?= htmlspecialchars(substr($bk['booking_code'] ?? '', -8)) ?>
                                        </a>
                                    </td>
                                    <td><?= htmlspecialchars($bk['tenant_name'] ?? '-') ?></td>
                                    <td><?= $bk['check_in'] ? date('d/m', strtotime($bk['check_in'])) : '-' ?></td>
                                    <td><?= $bk['check_out'] ? date('d/m', strtotime($bk['check_out'])) : '-' ?></td>
                                    <td><?= $bkBadges[$bkStatus] ?? $bkStatus ?></td>
                                </tr>
                            <?php endwhile; ?>
                            <?php if (!$hasBookings): ?>
                                <tr><td colspan="5" class="text-center text-muted py-3">Chưa có yêu cầu đặt phòng</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Actions -->
            <div class="d-flex gap-2">
                <a href="<?= ADMIN_BASE_PATH ?>/modules/landlord/rooms/index.php?building_id=<?= $room['building_id'] ?>" class="btn btn-secondary">
                    <i class="bi bi-arrow-left me-1"></i>Quay lại
                </a>
                <a href="<?= ADMIN_BASE_PATH ?>/modules/landlord/rooms/edit.php?id=<?= $roomId ?>" class="btn btn-primary">
                    <i class="bi bi-pencil me-1"></i>Chỉnh sửa
                </a>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/../../../includes/footer.php'; ?>
