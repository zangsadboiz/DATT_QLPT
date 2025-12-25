<?php
// client/pages/chitiet_phong.php - Chi tiết Phòng (không phụ thuộc tin đăng)
$hotelier = '/quanlyphongtro/hotelier-1.0.0';

$roomId = (int)($_GET['room_id'] ?? 0);
if ($roomId <= 0) {
    echo '<div class="container py-5"><div class="alert alert-danger">Thiếu room_id.</div></div>';
    return;
}

// Lấy thông tin phòng
$sql = "
  SELECT r.*, b.building_name, b.address, b.building_status, b.total_floors,
         d.district_name, pr.province_name,
         u.full_name as owner_name, u.phone as owner_phone, u.email as owner_email
  FROM rooms r
  JOIN buildings b ON b.building_id = r.building_id
  LEFT JOIN districts d ON d.district_id = b.district_id
  LEFT JOIN provinces pr ON pr.province_id = d.province_id
  JOIN users u ON u.user_id = b.owner_id
  WHERE r.room_id = ? AND r.deleted_at IS NULL AND b.building_status = 'ACTIVE' AND u.is_active = 1
  LIMIT 1
";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, 'i', $roomId);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$room = $res ? mysqli_fetch_assoc($res) : null;
mysqli_stmt_close($stmt);

if (!$room) {
    echo '<div class="container py-5"><div class="alert alert-warning">Phòng không tồn tại hoặc đang tạm ẩn.</div></div>';
    return;
}

// Lấy ảnh phòng từ room_images hoặc ảnh trong cột image
$images = [];
$rsImg = mysqli_query($conn, "SELECT image_path FROM room_images WHERE room_id = $roomId ORDER BY is_primary DESC, sort_order");
while ($rsImg && ($img = mysqli_fetch_assoc($rsImg))) {
    $images[] = '/quanlyphongtro/uploads/rooms/' . $img['image_path'];
}
// Nếu không có trong room_images, thử lấy từ cột image
if (empty($images) && !empty($room['image'])) {
    $img = $room['image'];
    if (file_exists($_SERVER['DOCUMENT_ROOT'] . '/quanlyphongtro/uploads/rooms/' . $img)) {
        $images[] = '/quanlyphongtro/uploads/rooms/' . $img;
    } elseif (file_exists($_SERVER['DOCUMENT_ROOT'] . '/quanlyphongtro/admin/uploads/rooms/' . $img)) {
        $images[] = '/quanlyphongtro/admin/uploads/rooms/' . $img;
    }
}

// Format amenities
$amenities = [];
$amenitiesData = json_decode($room['amenities'] ?: '[]', true) ?: [];
$labels = [
    'wifi' => ['WiFi miễn phí', 'wifi'], 'ac' => ['Điều hòa', 'snowflake'],
    'wc_rieng' => ['WC riêng', 'toilet'], 'bep' => ['Bếp nấu ăn', 'utensils'],
    'tu_lanh' => ['Tủ lạnh', 'box'], 'may_giat' => ['Máy giặt', 'tshirt'],
    'ban_cong' => ['Ban công', 'door-open'], 'gac_lung' => ['Gác lửng', 'layer-group'],
    'thang_may' => ['Thang máy', 'building'], 'camera' => ['Camera an ninh', 'video'],
    'bao_ve' => ['Bảo vệ 24/7', 'shield-alt'], 'dien' => ['Điện', 'bolt'],
    'nuoc' => ['Nước', 'tint'], 'internet' => ['Internet', 'globe']
];
foreach ($amenitiesData as $a) {
    if (isset($labels[$a])) $amenities[] = ['name' => $labels[$a][0], 'icon' => $labels[$a][1]];
}

$statusLabels = ['VACANT' => 'Còn trống', 'AVAILABLE' => 'Còn trống', 'OCCUPIED' => 'Đang thuê', 'MAINTENANCE' => 'Bảo trì', 'RESERVED' => 'Đã đặt cọc', 'PENDING' => 'Có người chờ'];
$statusColors = ['VACANT' => 'success', 'AVAILABLE' => 'success', 'OCCUPIED' => 'danger', 'MAINTENANCE' => 'warning', 'RESERVED' => 'info', 'PENDING' => 'warning'];

// Xác định trạng thái phòng ĐỘNG dựa trên booking cho ngày hiện tại
$today = date('Y-m-d');
$roomStatus = 'AVAILABLE'; // Mặc định còn trống

// Kiểm tra phòng bảo trì
if (($room['room_status'] ?? '') === 'MAINTENANCE') {
    $roomStatus = 'MAINTENANCE';
} else {
    // Kiểm tra có booking CHECKED_IN cho ngày hiện tại
    $activeBookingQuery = mysqli_query($conn, "
        SELECT booking_id FROM bookings 
        WHERE room_id = $roomId 
          AND status = 'CHECKED_IN'
          AND check_in <= '$today' 
          AND (check_out IS NULL OR check_out > '$today')
        LIMIT 1
    ");
    if ($activeBookingQuery && mysqli_num_rows($activeBookingQuery) > 0) {
        $roomStatus = 'OCCUPIED';
    } else {
        // Kiểm tra có booking CONFIRMED cho ngày hiện tại
        $reservedQuery = mysqli_query($conn, "
            SELECT booking_id FROM bookings 
            WHERE room_id = $roomId 
              AND status = 'CONFIRMED'
              AND check_in <= '$today' 
              AND (check_out IS NULL OR check_out > '$today')
            LIMIT 1
        ");
        if ($reservedQuery && mysqli_num_rows($reservedQuery) > 0) {
            $roomStatus = 'RESERVED';
        }
    }
}

// Lấy danh sách booking đang active hoặc trong tương lai
$upcomingBookings = [];
$upcomingQuery = mysqli_query($conn, "
    SELECT b.booking_id, b.booking_code, b.check_in, b.check_out, b.status,
           t.full_name as tenant_name
    FROM bookings b
    JOIN tenants t ON t.tenant_id = b.tenant_id
    WHERE b.room_id = $roomId
      AND b.status IN ('PENDING','CONFIRMED','CHECKED_IN')
      AND (b.check_out IS NULL OR b.check_out >= '$today')
    ORDER BY b.check_in ASC
");
while ($upcomingQuery && ($bk = mysqli_fetch_assoc($upcomingQuery))) {
    $upcomingBookings[] = $bk;
}

// Lấy booking đầu tiên để hiển thị cảnh báo (backward compatible)
$pendingBooking = !empty($upcomingBookings) ? $upcomingBookings[0] : null;
?>

<!-- Page Header Start -->
<div class="container-fluid page-header mb-5 p-0" style="background-image: url(<?= $hotelier ?>/img/carousel-1.jpg);">
    <div class="container-fluid page-header-inner py-5">
        <div class="container text-center pb-5">
            <h1 class="display-4 text-white mb-3 animated slideInDown">Phòng <?= htmlspecialchars($room['room_code']) ?></h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb justify-content-center text-uppercase">
                    <li class="breadcrumb-item"><a href="/quanlyphongtro/client/index.php?page=home" class="text-white">Trang chủ</a></li>
                    <li class="breadcrumb-item"><a href="/quanlyphongtro/client/index.php?page=phong" class="text-white">Phòng trọ</a></li>
                    <li class="breadcrumb-item text-white active">Chi tiết phòng</li>
                </ol>
            </nav>
        </div>
    </div>
</div>
<!-- Page Header End -->

<div class="container-xxl py-5">
    <div class="container">
        <div class="row g-5">
            <!-- Left: Images & Info -->
            <div class="col-lg-8">
                
                <!-- GALLERY ẢNH -->
                <div class="wow fadeInUp mb-4" data-wow-delay="0.1s">
                    <?php if (count($images) > 0): ?>
                        <div class="position-relative rounded overflow-hidden mb-3" style="background: #f8f9fa;">
                            <img id="mainImage" src="<?= htmlspecialchars($images[0]) ?>" alt="<?= htmlspecialchars($room['room_code']) ?>" 
                                 class="w-100" style="height: 400px; object-fit: cover;">
                            
                            <?php if (count($images) > 1): ?>
                                <button onclick="changeImage(-1)" class="btn btn-light btn-lg position-absolute top-50 start-0 translate-middle-y ms-2 rounded-circle shadow" style="width:50px;height:50px;">
                                    <i class="fa fa-chevron-left"></i>
                                </button>
                                <button onclick="changeImage(1)" class="btn btn-light btn-lg position-absolute top-50 end-0 translate-middle-y me-2 rounded-circle shadow" style="width:50px;height:50px;">
                                    <i class="fa fa-chevron-right"></i>
                                </button>
                            <?php endif; ?>
                            
                            <div class="position-absolute bottom-0 end-0 bg-dark bg-opacity-75 text-white px-3 py-1 m-2 rounded">
                                <span id="imageCounter">1</span>/<?= count($images) ?>
                            </div>
                        </div>
                        
                        <?php if (count($images) > 1): ?>
                            <div class="d-flex gap-2 overflow-auto pb-2">
                                <?php foreach ($images as $idx => $imgPath): ?>
                                    <img src="<?= htmlspecialchars($imgPath) ?>" alt="" 
                                         onclick="setImage(<?= $idx ?>)"
                                         class="rounded thumbnail-item <?= $idx === 0 ? 'active' : '' ?>" 
                                         style="height: 70px; width: 100px; object-fit: cover; cursor: pointer; flex-shrink: 0;">
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="bg-light rounded d-flex align-items-center justify-content-center" style="height: 350px;">
                            <div class="text-center text-muted">
                                <i class="bi bi-house-door" style="font-size: 80px;"></i>
                                <p class="mt-2">Chưa có hình ảnh</p>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Title & Status -->
                <div class="d-flex justify-content-between align-items-start mb-4 wow fadeInUp" data-wow-delay="0.2s">
                    <div>
                        <h2 class="mb-2">Phòng <?= htmlspecialchars($room['room_code']) ?></h2>
                        <p class="text-muted mb-0">
                            <i class="fa fa-building text-primary me-2"></i><?= htmlspecialchars($room['building_name']) ?>
                        </p>
                        <p class="text-muted mb-0">
                            <i class="fa fa-map-marker-alt text-primary me-2"></i><?= htmlspecialchars($room['address']) ?>
                            <?php if ($room['district_name']): ?>, <?= htmlspecialchars($room['district_name']) ?><?php endif; ?>
                        </p>
                    </div>
                    <span class="badge fs-6 py-2 px-3 bg-<?= $statusColors[$roomStatus] ?? 'secondary' ?>">
                        <?= $statusLabels[$roomStatus] ?? $roomStatus ?>
                    </span>
                </div>
                
                <!-- Key Info -->
                <div class="row g-3 mb-4 wow fadeInUp" data-wow-delay="0.3s">
                    <div class="col-md-3 col-6">
                        <div class="bg-light rounded p-3 text-center">
                            <i class="fa fa-money-bill-wave text-primary fa-2x mb-2"></i>
                            <div class="small text-muted">Giá thuê</div>
                            <?php 
                            $rentalType = $room['rental_type'] ?? 'MONTHLY';
                            $displayPrice = $rentalType === 'DAILY' ? (float)($room['daily_price'] ?? 0) : (float)($room['base_rent'] ?? 0);
                            $priceUnit = $rentalType === 'DAILY' ? 'ngày' : 'tháng';
                            ?>
                            <strong class="text-primary"><?= number_format($displayPrice) ?>đ/<?= $priceUnit ?></strong>
                        </div>
                    </div>
                    <?php if (($room['deposit_default'] ?? 0) > 0): ?>
                        <div class="col-md-3 col-6">
                            <div class="bg-light rounded p-3 text-center">
                                <i class="fa fa-hand-holding-usd text-primary fa-2x mb-2"></i>
                                <div class="small text-muted">Tiền cọc</div>
                                <strong><?= number_format((float)$room['deposit_default']) ?>đ</strong>
                            </div>
                        </div>
                    <?php endif; ?>
                    <?php if (($room['area'] ?? 0) > 0): ?>
                        <div class="col-md-3 col-6">
                            <div class="bg-light rounded p-3 text-center">
                                <i class="fa fa-ruler-combined text-primary fa-2x mb-2"></i>
                                <div class="small text-muted">Diện tích</div>
                                <strong><?= $room['area'] ?> m²</strong>
                            </div>
                        </div>
                    <?php endif; ?>
                    <?php if (($room['max_occupants'] ?? 0) > 0): ?>
                        <div class="col-md-3 col-6">
                            <div class="bg-light rounded p-3 text-center">
                                <i class="fa fa-users text-primary fa-2x mb-2"></i>
                                <div class="small text-muted">Số người ở</div>
                                <strong><?= $room['max_occupants'] ?> người</strong>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Amenities -->
                <?php if (count($amenities) > 0): ?>
                    <div class="mb-4 wow fadeInUp" data-wow-delay="0.4s">
                        <h5><i class="fa fa-check-circle text-primary me-2"></i>Tiện nghi</h5>
                        <div class="row g-3">
                            <?php foreach ($amenities as $a): ?>
                                <div class="col-md-4 col-6">
                                    <div class="d-flex align-items-center">
                                        <i class="fa fa-<?= $a['icon'] ?> text-primary me-2"></i>
                                        <span><?= $a['name'] ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Description -->
                <?php if (!empty($room['description'])): ?>
                    <div class="mb-4 wow fadeInUp" data-wow-delay="0.5s">
                        <h5><i class="fa fa-file-alt text-primary me-2"></i>Mô tả</h5>
                        <div class="bg-light rounded p-4">
                            <?= nl2br(htmlspecialchars($room['description'])) ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Right: Contact & Booking -->
            <div class="col-lg-4">
                <!-- Price Card -->
                <div class="bg-primary text-white rounded p-4 mb-4 wow fadeInUp" data-wow-delay="0.1s">
                    <h3 class="mb-0"><?= number_format($displayPrice) ?>đ/<?= $priceUnit ?></h3>
                    <small>Phòng <?= htmlspecialchars($room['room_code']) ?></small>
                </div>
                
                <!-- Owner Info -->
                <div class="bg-light rounded p-4 mb-4 wow fadeInUp" data-wow-delay="0.2s">
                    <h5 class="mb-3"><i class="fa fa-user text-primary me-2"></i>Thông tin liên hệ</h5>
                    
                    <div class="mb-3">
                        <div class="text-muted small">Chủ cho thuê</div>
                        <strong class="fs-5"><?= htmlspecialchars($room['owner_name']) ?></strong>
                    </div>
                    
                    <?php if ($room['owner_phone']): ?>
                        <div class="mb-3">
                            <div class="text-muted small">Số điện thoại</div>
                            <a href="tel:<?= htmlspecialchars($room['owner_phone']) ?>" class="fs-5 text-primary fw-bold">
                                <i class="fa fa-phone-alt me-2"></i><?= htmlspecialchars($room['owner_phone']) ?>
                            </a>
                        </div>
                    <?php endif; ?>
                    
                    <hr>
                    
                    <!-- CẢNH BÁO NẾU CÓ BOOKING ĐANG CHỜ -->
                    <?php if ($pendingBooking): ?>
                        <div class="alert alert-<?= $pendingBooking['status'] === 'CONFIRMED' ? 'info' : 'warning' ?> small mb-3">
                            <i class="bi bi-<?= $pendingBooking['status'] === 'CONFIRMED' ? 'check-circle' : 'clock' ?> me-1"></i>
                            <?php if ($pendingBooking['status'] === 'CONFIRMED'): ?>
                                Phòng đã được đặt cọc, dự kiến nhận phòng ngày <?= date('d/m/Y', strtotime($pendingBooking['check_in'])) ?>
                            <?php else: ?>
                                Có 1 yêu cầu đặt phòng đang chờ xác nhận
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    
                    <!-- NÚT ĐẶT PHÒNG - Luôn cho phép đặt (trừ bảo trì), việc kiểm tra trùng ngày sẽ ở form đặt -->
                    <?php if ($roomStatus === 'MAINTENANCE'): ?>
                        <button class="btn btn-secondary w-100 py-3 mb-2" disabled>
                            <i class="fa fa-tools me-2"></i>Phòng đang bảo trì
                        </button>
                    <?php else: ?>
                        <a href="/quanlyphongtro/client/index.php?page=datphong&room_id=<?= $roomId ?>" class="btn btn-success w-100 py-3 mb-2">
                            <i class="fa fa-calendar-check me-2"></i>Đặt phòng
                        </a>
                        <?php if ($roomStatus === 'OCCUPIED'): ?>
                            <p class="small text-muted text-center mb-2">
                                <i class="bi bi-info-circle"></i> Phòng đang có người thuê, bạn có thể đặt cho thời gian khác
                            </p>
                        <?php elseif ($roomStatus === 'RESERVED'): ?>
                            <p class="small text-muted text-center mb-2">
                                <i class="bi bi-info-circle"></i> Phòng đã được đặt cọc, bạn có thể đặt cho thời gian khác
                            </p>
                        <?php endif; ?>
                    <?php endif; ?>
                    
                    <a href="tel:<?= htmlspecialchars($room['owner_phone']) ?>" class="btn btn-outline-primary w-100 py-3">
                        <i class="fa fa-phone-alt me-2"></i>Gọi điện
                    </a>
                </div>
                
                <!-- LỊCH ĐẶT PHÒNG -->
                <?php if (!empty($upcomingBookings)): ?>
                <div class="bg-light rounded p-4 mt-4 wow fadeInUp" data-wow-delay="0.25s">
                    <h6 class="mb-3"><i class="fa fa-calendar text-primary me-2"></i>Lịch đặt phòng</h6>
                    <div class="list-group list-group-flush">
                        <?php foreach ($upcomingBookings as $bk): 
                            $statusBadge = match($bk['status']) {
                                'CHECKED_IN' => '<span class="badge bg-danger">Đang ở</span>',
                                'CONFIRMED' => '<span class="badge bg-info">Đã đặt</span>',
                                'PENDING' => '<span class="badge bg-warning">Chờ duyệt</span>',
                                default => ''
                            };
                            $checkOut = $bk['check_out'] ? date('d/m/Y', strtotime($bk['check_out'])) : 'Không xác định';
                        ?>
                            <div class="list-group-item px-0 d-flex justify-content-between align-items-center">
                                <div>
                                    <small class="text-muted">
                                        <?= date('d/m/Y', strtotime($bk['check_in'])) ?> - <?= $checkOut ?>
                                    </small>
                                </div>
                                <?= $statusBadge ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Room Info -->
                <div class="bg-light rounded p-4 wow fadeInUp" data-wow-delay="0.3s">
                    <h6 class="mb-3">Thông tin phòng</h6>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Mã phòng</span>
                        <strong><?= htmlspecialchars($room['room_code']) ?></strong>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Tầng</span>
                        <strong><?= $room['floor'] ?? $room['floor_no'] ?? 1 ?></strong>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Dãy trọ</span>
                        <strong><?= htmlspecialchars($room['building_name']) ?></strong>
                    </div>
                </div>
                
                <!-- Back Button -->
                <div class="mt-4">
                    <a href="/quanlyphongtro/client/index.php?page=phong" class="btn btn-outline-secondary w-100 py-3">
                        <i class="fa fa-arrow-left me-2"></i>Quay lại danh sách
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.thumbnail-item {
    border: 3px solid transparent;
    transition: all 0.2s;
    opacity: 0.6;
}
.thumbnail-item:hover,
.thumbnail-item.active {
    border-color: #FEA116;
    opacity: 1;
}
</style>

<script>
const images = <?= json_encode($images) ?>;
let currentIndex = 0;

function setImage(index) {
    if (index < 0 || index >= images.length) return;
    currentIndex = index;
    document.getElementById('mainImage').src = images[index];
    document.getElementById('imageCounter').textContent = index + 1;
    
    document.querySelectorAll('.thumbnail-item').forEach((thumb, i) => {
        thumb.classList.toggle('active', i === index);
    });
}

function changeImage(direction) {
    let newIndex = currentIndex + direction;
    if (newIndex < 0) newIndex = images.length - 1;
    if (newIndex >= images.length) newIndex = 0;
    setImage(newIndex);
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'ArrowLeft') changeImage(-1);
    if (e.key === 'ArrowRight') changeImage(1);
});
</script>
