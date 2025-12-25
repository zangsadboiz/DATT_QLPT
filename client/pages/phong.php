<?php
// client/pages/phong.php - Danh sách Phòng Trọ theo Phường TP Vinh
$hotelier = '/quanlyphongtro/hotelier-1.0.0';

// Tự động hủy các booking PENDING quá 30 phút
require_once __DIR__ . '/../../includes/booking_helpers.php';
auto_cancel_expired_bookings($conn);

// Params
$q = trim($_GET['q'] ?? '');
$regionId = (int)($_GET['region'] ?? 0);
$provinceId = (int)($_GET['province'] ?? 0);
$districtId = (int)($_GET['district'] ?? 0);
$phuong = $_GET['phuong'] ?? '';
$min = $_GET['min'] ?? '';
$max = $_GET['max'] ?? '';
$sort = $_GET['sort'] ?? 'new';
$status = $_GET['status'] ?? 'AVAILABLE'; // AVAILABLE, OCCUPIED
$checkInDate = $_GET['check_in'] ?? ''; // Ngày nhận phòng
$checkOutDate = $_GET['check_out'] ?? ''; // Ngày trả phòng
$rentalType = $_GET['rental_type'] ?? ''; // DAILY or MONTHLY
$p = max(1, (int)($_GET['p'] ?? 1));

$limit = 8;
$offset = ($p - 1) * $limit;

// Danh sách phường TP Vinh
$phuongList = [
    '' => 'Tất cả phường',
    'Phường Bến Thủy' => 'Phường Bến Thủy',
    'Phường Cửa Nam' => 'Phường Cửa Nam',
    'Phường Đội Cung' => 'Phường Đội Cung',
    'Phường Đông Vĩnh' => 'Phường Đông Vĩnh',
    'Phường Hà Huy Tập' => 'Phường Hà Huy Tập',
    'Phường Hồng Sơn' => 'Phường Hồng Sơn',
    'Phường Hưng Bình' => 'Phường Hưng Bình',
    'Phường Hưng Dũng' => 'Phường Hưng Dũng',
    'Phường Hưng Phúc' => 'Phường Hưng Phúc',
    'Phường Lê Lợi' => 'Phường Lê Lợi',
    'Phường Lê Mao' => 'Phường Lê Mao',
    'Phường Quán Bàu' => 'Phường Quán Bàu',
    'Phường Quang Trung' => 'Phường Quang Trung',
    'Phường Trung Đô' => 'Phường Trung Đô',
    'Phường Trường Thi' => 'Phường Trường Thi',
    'Phường Vinh Tân' => 'Phường Vinh Tân',
    'Xã Hưng Chính' => 'Xã Hưng Chính',
    'Xã Hưng Đông' => 'Xã Hưng Đông',
    'Xã Hưng Hòa' => 'Xã Hưng Hòa',
    'Xã Hưng Lộc' => 'Xã Hưng Lộc',
    'Xã Nghi Ân' => 'Xã Nghi Ân',
    'Xã Nghi Đức' => 'Xã Nghi Đức',
    'Xã Nghi Kim' => 'Xã Nghi Kim',
    'Xã Nghi Liên' => 'Xã Nghi Liên',
    'Xã Nghi Phú' => 'Xã Nghi Phú',
];

// Build WHERE - Lấy TẤT CẢ phòng từ rooms + buildings + user active
$where = ["r.deleted_at IS NULL", "b.building_status = 'ACTIVE'", "u.is_active = 1"];
$params = [];
$types = '';

// Xác định khoảng thời gian để kiểm tra booking
// Mặc định nếu không nhập: từ hôm nay đến ngày mai
$filterCheckIn = $checkInDate ?: date('Y-m-d');
$filterCheckOut = $checkOutDate ?: date('Y-m-d', strtotime('+1 day'));

// Swap nếu user nhập sai thứ tự (Từ ngày > Đến ngày)
if ($filterCheckIn > $filterCheckOut) {
    $temp = $filterCheckIn;
    $filterCheckIn = $filterCheckOut;
    $filterCheckOut = $temp;
    // Cập nhật lại biến hiển thị
    $checkInDate = $filterCheckIn;
    $checkOutDate = $filterCheckOut;
}

// ẨN phòng đã đặt/thuê (DEPOSIT_PAID, CHECKED_IN) - không hiển thị trong danh sách
$where[] = "NOT EXISTS (
    SELECT 1 FROM bookings bk 
    WHERE bk.room_id = r.room_id 
      AND bk.status IN ('DEPOSIT_PAID', 'CHECKED_IN')
      AND bk.check_in < '$filterCheckOut'
      AND (bk.check_out IS NULL OR bk.check_out > '$filterCheckIn')
)";

// Lọc từ khóa
if ($q !== '') {
    $where[] = "(r.room_name LIKE ? OR b.building_name LIKE ? OR b.address LIKE ?)";
    $like = '%' . $q . '%';
    $params[] = $like; $params[] = $like; $params[] = $like;
    $types .= 'sss';
}

// Lọc theo miền (region)
if ($regionId > 0) {
    $where[] = "EXISTS (SELECT 1 FROM districts d JOIN provinces p ON p.province_id = d.province_id WHERE d.district_id = b.district_id AND p.region_id = ?)";
    $params[] = $regionId;
    $types .= 'i';
}

// Lọc theo tỉnh (province)
if ($provinceId > 0) {
    $where[] = "EXISTS (SELECT 1 FROM districts d WHERE d.district_id = b.district_id AND d.province_id = ?)";
    $params[] = $provinceId;
    $types .= 'i';
}

// Lọc theo quận/huyện (district)
if ($districtId > 0) {
    $where[] = "b.district_id = ?";
    $params[] = $districtId;
    $types .= 'i';
}

// Lọc theo phường (legacy - address text search)
if ($phuong !== '') {
    $where[] = "b.address LIKE ?";
    $params[] = '%' . $phuong . '%';
    $types .= 's';
}

// Lọc giá
if ($min !== '' && is_numeric($min)) {
    $where[] = "r.base_rent >= ?";
    $params[] = (float)$min;
    $types .= 'd';
}
if ($max !== '' && is_numeric($max)) {
    $where[] = "r.base_rent <= ?";
    $params[] = (float)$max;
    $types .= 'd';
}

// Lọc theo loại thuê
if ($rentalType === 'DAILY') {
    $where[] = "r.rental_type = 'DAILY'";
} elseif ($rentalType === 'MONTHLY') {
    $where[] = "r.rental_type = 'MONTHLY'";
}

// Order
$orderBy = "r.created_at DESC";
if ($sort === 'price_asc') $orderBy = "r.base_rent ASC";
if ($sort === 'price_desc') $orderBy = "r.base_rent DESC";
if ($sort === 'area') $orderBy = "r.area DESC";

$whereSql = implode(' AND ', $where);

// Count
$sqlCount = "SELECT COUNT(*) total FROM rooms r 
             JOIN buildings b ON b.building_id = r.building_id 
             JOIN users u ON u.user_id = b.owner_id
             WHERE $whereSql";
$stmt = mysqli_prepare($conn, $sqlCount);
if ($stmt && $types !== '') mysqli_stmt_bind_param($stmt, $types, ...$params);

$total = 0;
if ($stmt) {
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    $total = (int)($row['total'] ?? 0);
    mysqli_stmt_close($stmt);
}

$totalPages = max(1, (int)ceil($total / $limit));

// Data - Query với kiểm tra booking theo khoảng ngày user chọn
$sqlData = "
  SELECT r.*, b.building_name, b.address, b.building_status,
         u.full_name as owner_name, u.phone as owner_phone,
         COALESCE(
           (SELECT image_path FROM room_images WHERE room_id = r.room_id AND is_primary = 1 LIMIT 1),
           r.image,
           (SELECT pi.image_path FROM post_images pi JOIN posts p ON p.post_id = pi.post_id WHERE p.room_id = r.room_id AND pi.is_primary = 1 LIMIT 1)
         ) as room_image,
         (SELECT post_id FROM posts WHERE room_id = r.room_id AND status = 'APPROVED' LIMIT 1) as post_id,
         -- Kiểm tra có booking CHECKED_IN trong khoảng ngày lọc
         (SELECT booking_id FROM bookings 
          WHERE room_id = r.room_id 
            AND status = 'CHECKED_IN'
            AND check_in < '$filterCheckOut'
            AND (check_out IS NULL OR check_out > '$filterCheckIn')
          LIMIT 1) as active_booking_id,
         -- Kiểm tra có booking CONFIRMED trong khoảng ngày lọc
         (SELECT booking_id FROM bookings 
          WHERE room_id = r.room_id 
            AND status = 'CONFIRMED'
            AND check_in < '$filterCheckOut'
            AND (check_out IS NULL OR check_out > '$filterCheckIn')
          LIMIT 1) as reserved_booking_id,
         -- Kiểm tra có booking DEPOSIT_PAID trong khoảng ngày lọc
         (SELECT booking_id FROM bookings 
          WHERE room_id = r.room_id 
            AND status = 'DEPOSIT_PAID'
            AND check_in < '$filterCheckOut'
            AND (check_out IS NULL OR check_out > '$filterCheckIn')
          LIMIT 1) as deposit_paid_booking_id,
         -- Kiểm tra có booking PENDING trong khoảng ngày lọc
         (SELECT booking_id FROM bookings 
          WHERE room_id = r.room_id 
            AND status = 'PENDING'
            AND check_in < '$filterCheckOut'
            AND (check_out IS NULL OR check_out > '$filterCheckIn')
          LIMIT 1) as pending_booking_id
  FROM rooms r
  JOIN buildings b ON b.building_id = r.building_id
  JOIN users u ON u.user_id = b.owner_id
  WHERE $whereSql
  ORDER BY $orderBy
  LIMIT $limit OFFSET $offset
";
$stmt2 = mysqli_prepare($conn, $sqlData);
if ($stmt2 && $types !== '') mysqli_stmt_bind_param($stmt2, $types, ...$params);

$rooms = [];
if ($stmt2) {
    mysqli_stmt_execute($stmt2);
    $res2 = mysqli_stmt_get_result($stmt2);
    while ($res2 && ($r = mysqli_fetch_assoc($res2))) $rooms[] = $r;
    mysqli_stmt_close($stmt2);
}

// Helper
function formatAmenitiesRoom($json): array {
    $amenities = json_decode($json ?: '[]', true) ?: [];
    $labels = [
        'wifi' => 'WiFi', 'ac' => 'Điều hòa', 'wc_rieng' => 'WC riêng',
        'bep' => 'Bếp', 'tu_lanh' => 'Tủ lạnh', 'may_giat' => 'Máy giặt',
        'ban_cong' => 'Ban công', 'gac_lung' => 'Gác lửng', 'thang_may' => 'Thang máy',
        'dien' => 'Điện', 'nuoc' => 'Nước', 'internet' => 'Internet'
    ];
    $result = [];
    foreach ($amenities as $a) {
        if (isset($labels[$a])) $result[] = $labels[$a];
    }
    return $result;
}

$keep = ['page' => 'phong', 'q' => $q, 'region' => $regionId, 'province' => $provinceId, 'district' => $districtId, 'phuong' => $phuong, 'min' => $min, 'max' => $max, 'sort' => $sort, 'check_in' => $checkInDate, 'check_out' => $checkOutDate, 'rental_type' => $rentalType];

$statusLabels = ['AVAILABLE' => 'Còn trống', 'VACANT' => 'Còn trống', 'OCCUPIED' => 'Đã thuê', 'DEPOSIT_PAID' => 'Đã thuê', 'RESERVED' => 'Chờ thanh toán', 'PENDING' => 'Có người chờ', 'MAINTENANCE' => 'Bảo trì'];
$statusColors = ['AVAILABLE' => 'success', 'VACANT' => 'success', 'OCCUPIED' => 'danger', 'DEPOSIT_PAID' => 'danger', 'RESERVED' => 'info', 'PENDING' => 'warning', 'MAINTENANCE' => 'secondary'];

// Hàm xác định trạng thái hiển thị của phòng dựa trên booking
function getRoomDisplayStatus($room) {
    // Ưu tiên 1: Đang có người ở (CHECKED_IN)
    if (!empty($room['active_booking_id'])) {
        return 'OCCUPIED';
    }
    // Ưu tiên 2: Phòng đang bảo trì
    if (($room['room_status'] ?? '') === 'MAINTENANCE') {
        return 'MAINTENANCE';
    }
    // Ưu tiên 3: Đã thanh toán đặt cọc (DEPOSIT_PAID) - phòng đã bị giữ
    if (!empty($room['deposit_paid_booking_id'])) {
        return 'DEPOSIT_PAID';
    }
    // Ưu tiên 4: Đã confirm chờ thanh toán (CONFIRMED)
    if (!empty($room['reserved_booking_id'])) {
        return 'RESERVED';
    }
    // Ưu tiên 5: Có người đang chờ duyệt
    if (!empty($room['pending_booking_id'])) {
        return 'PENDING';
    }
    // Mặc định: Còn trống
    return 'AVAILABLE';
}
?>

<!-- Page Header Start -->
<div class="container-fluid page-header mb-5 p-0" style="background-image: url(<?= $hotelier ?>/img/carousel-2.jpg);">
    <div class="container-fluid page-header-inner py-5">
        <div class="container text-center pb-5">
            <h1 class="display-3 text-white mb-3 animated slideInDown">Phòng Trọ TP Vinh</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb justify-content-center text-uppercase">
                    <li class="breadcrumb-item"><a href="/quanlyphongtro/client/index.php?page=home" class="text-white">Trang chủ</a></li>
                    <li class="breadcrumb-item text-white active">Phòng trọ</li>
                </ol>
            </nav>
        </div>
    </div>
</div>
<!-- Page Header End -->

<div class="container-xxl py-5">
    <div class="container">
        
        <!-- Filter -->
        <div class="bg-white shadow rounded p-4 mb-5 wow fadeInUp" data-wow-delay="0.1s">
            <form class="row g-3 align-items-end" method="get" action="/quanlyphongtro/client/index.php">
                <input type="hidden" name="page" value="phong">
                
                <!-- Row 1: Miền, Tỉnh, Quận/Huyện -->
                <?php
                // Load regions and provinces for filter
                $regions = mysqli_query($conn, "SELECT * FROM regions ORDER BY region_id");
                $provinces = [];
                if ($regionId > 0) {
                    $provRs = mysqli_query($conn, "SELECT * FROM provinces WHERE region_id = $regionId ORDER BY province_name");
                    while ($prov = mysqli_fetch_assoc($provRs)) $provinces[] = $prov;
                }
                $districts = [];
                if ($provinceId > 0) {
                    $distRs = mysqli_query($conn, "SELECT * FROM districts WHERE province_id = $provinceId ORDER BY district_name");
                    while ($dist = mysqli_fetch_assoc($distRs)) $districts[] = $dist;
                }
                ?>
                <div class="col-lg-2 col-md-4 col-6">
                    <label class="form-label"><i class="fa fa-globe text-primary me-1"></i>Miền</label>
                    <select name="region" id="filterRegion" class="form-select">
                        <option value="">-- Tất cả --</option>
                        <?php while ($regions && ($reg = mysqli_fetch_assoc($regions))): ?>
                            <option value="<?= $reg['region_id'] ?>" <?= $regionId == $reg['region_id'] ? 'selected' : '' ?>><?= htmlspecialchars($reg['region_name']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <div class="col-lg-2 col-md-4 col-6">
                    <label class="form-label"><i class="fa fa-city text-primary me-1"></i>Tỉnh/TP</label>
                    <select name="province" id="filterProvince" class="form-select">
                        <option value="">-- Tất cả --</option>
                        <?php foreach ($provinces as $prov): ?>
                            <option value="<?= $prov['province_id'] ?>" <?= $provinceId == $prov['province_id'] ? 'selected' : '' ?>><?= htmlspecialchars($prov['province_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-lg-2 col-md-4 col-6">
                    <label class="form-label"><i class="fa fa-map-marker-alt text-primary me-1"></i>Quận/Huyện</label>
                    <select name="district" id="filterDistrict" class="form-select">
                        <option value="">-- Tất cả --</option>
                        <?php foreach ($districts as $dist): ?>
                            <option value="<?= $dist['district_id'] ?>" <?= $districtId == $dist['district_id'] ? 'selected' : '' ?>><?= htmlspecialchars($dist['district_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-lg-2 col-md-3 col-6">
                    <label class="form-label">Giá từ</label>
                    <input name="min" type="number" class="form-control" value="<?= htmlspecialchars((string)$min) ?>" placeholder="500.000">
                </div>
                
                <div class="col-lg-2 col-md-3 col-6">
                    <label class="form-label">Giá đến</label>
                    <input name="max" type="number" class="form-control" value="<?= htmlspecialchars((string)$max) ?>" placeholder="3.000.000">
                </div>
                
                <!-- Ngày nhận phòng -->
                <div class="col-lg-2 col-md-3 col-6">
                    <label class="form-label"><i class="fa fa-calendar-check text-primary me-1"></i>Từ ngày</label>
                    <input name="check_in" type="date" class="form-control" value="<?= htmlspecialchars($checkInDate) ?>" min="<?= date('Y-m-d') ?>">
                </div>
                
                <!-- Ngày trả phòng -->
                <div class="col-lg-2 col-md-3 col-6">
                    <label class="form-label"><i class="fa fa-calendar-minus text-primary me-1"></i>Đến ngày</label>
                    <input name="check_out" type="date" class="form-control" value="<?= htmlspecialchars($checkOutDate) ?>" min="<?= date('Y-m-d', strtotime('+1 day')) ?>">
                </div>
                
                <div class="col-lg-2 col-md-3 col-6">
                    <label class="form-label">Sắp xếp</label>
                    <select name="sort" class="form-select">
                        <option value="new" <?= $sort==='new'?'selected':'' ?>>Mới nhất</option>
                        <option value="price_asc" <?= $sort==='price_asc'?'selected':'' ?>>Giá tăng</option>
                        <option value="price_desc" <?= $sort==='price_desc'?'selected':'' ?>>Giá giảm</option>
                        <option value="area" <?= $sort==='area'?'selected':'' ?>>Diện tích</option>
                    </select>
                </div>
                
                <div class="col-lg-2 col-md-3 col-6">
                    <label class="form-label"><i class="fa fa-clock text-primary me-1"></i>Loại thuê</label>
                    <select name="rental_type" class="form-select">
                        <option value="" <?= $rentalType===''?'selected':'' ?>>Tất cả</option>
                        <option value="MONTHLY" <?= $rentalType==='MONTHLY'?'selected':'' ?>>Theo tháng</option>
                        <option value="DAILY" <?= $rentalType==='DAILY'?'selected':'' ?>>Theo ngày</option>
                    </select>
                </div>
                
                <div class="col-lg-1 col-md-2 col-6">
                    <button class="btn btn-primary w-100" type="submit">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
            </form>
        </div>
        
        <!-- Kết quả -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h6 class="section-title text-start text-primary text-uppercase">Kết quả</h6>
                <h2 class="mb-0">Tìm thấy <span class="text-primary"><?= number_format($total) ?></span> phòng</h2>
                <?php if ($checkInDate || $checkOutDate): ?>
                    <small class="text-muted">
                        <i class="fa fa-calendar me-1"></i>
                        Trạng thái cho: 
                        <?= $checkInDate ? date('d/m/Y', strtotime($checkInDate)) : 'Hôm nay' ?> 
                        - 
                        <?= $checkOutDate ? date('d/m/Y', strtotime($checkOutDate)) : 'Không xác định' ?>
                    </small>
                <?php endif; ?>
            </div>
            <div>
                <?php if ($phuong): ?>
                    <span class="badge bg-primary fs-6 py-2 px-3 me-2">
                        <i class="fa fa-map-marker-alt me-1"></i><?= htmlspecialchars($phuong) ?>
                    </span>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Room List -->
        <div class="row g-4">
            <?php if (count($rooms) > 0): ?>
                <?php foreach ($rooms as $room): ?>
                    <?php
                    $amenities = formatAmenitiesRoom($room['amenities'] ?? '');
                    // Xác định đường dẫn ảnh - thử nhiều thư mục
                    $imgPath = null;
                    if (!empty($room['room_image'])) {
                        $img = $room['room_image'];
                        // Thử các đường dẫn
                        if (file_exists($_SERVER['DOCUMENT_ROOT'] . '/quanlyphongtro/uploads/rooms/' . $img)) {
                            $imgPath = '/quanlyphongtro/uploads/rooms/' . $img;
                        } elseif (file_exists($_SERVER['DOCUMENT_ROOT'] . '/quanlyphongtro/uploads/posts/' . $img)) {
                            $imgPath = '/quanlyphongtro/uploads/posts/' . $img;
                        } elseif (file_exists($_SERVER['DOCUMENT_ROOT'] . '/quanlyphongtro/admin/uploads/rooms/' . $img)) {
                            $imgPath = '/quanlyphongtro/admin/uploads/rooms/' . $img;
                        } else {
                            $imgPath = '/quanlyphongtro/uploads/rooms/' . $img;
                        }
                    }
                    // Xác định trạng thái thực tế dựa trên booking
                    $roomStatus = getRoomDisplayStatus($room);
                    $postId = $room['post_id'] ?? null;
                    ?>
                    <div class="col-lg-3 col-md-4 col-6 wow fadeInUp" data-wow-delay="0.1s">
                        <div class="room-item shadow rounded overflow-hidden h-100">
                            <div class="position-relative">
                                <?php if ($imgPath): ?>
                                    <img class="img-fluid" src="<?= htmlspecialchars($imgPath) ?>" alt="" style="height: 180px; width: 100%; object-fit: cover;">
                                <?php else: ?>
                                    <div class="bg-light d-flex align-items-center justify-content-center" style="height: 180px;">
                                        <i class="bi bi-house-door text-muted" style="font-size: 48px;"></i>
                                    </div>
                                <?php endif; ?>
                                
                                <!-- Giá -->
                                <?php if (($room['rental_type'] ?? 'MONTHLY') === 'DAILY'): ?>
                                <small class="position-absolute start-0 top-100 translate-middle-y bg-info text-white rounded py-1 px-2 ms-2" style="font-size: 11px;">
                                    <?= number_format((float)($room['daily_price'] ?? 0)) ?>đ/ngày
                                </small>
                                <?php else: ?>
                                <small class="position-absolute start-0 top-100 translate-middle-y bg-primary text-white rounded py-1 px-2 ms-2" style="font-size: 11px;">
                                    <?= number_format((float)($room['base_rent'] ?? 0)) ?>đ/tháng
                                </small>
                                <?php endif; ?>
                                
                                <!-- Status -->
                                <span class="position-absolute top-0 end-0 m-2 badge bg-<?= $statusColors[$roomStatus] ?? 'secondary' ?>">
                                    <?= $statusLabels[$roomStatus] ?? $roomStatus ?>
                                </span>
                            </div>
                            
                            <div class="p-3 mt-2 d-flex flex-column" style="min-height: 140px;">
                                <h6 class="mb-2"><?= htmlspecialchars(mb_substr($room['room_code'] ?? '', 0, 20)) ?></h6>
                                
                                <div class="d-flex mb-2 small text-muted">
                                    <?php if ($room['area']): ?>
                                        <span class="me-2"><i class="fa fa-ruler-combined text-primary me-1"></i><?= $room['area'] ?>m²</span>
                                    <?php endif; ?>
                                    <?php if ($room['max_occupants']): ?>
                                        <span><i class="fa fa-users text-primary me-1"></i><?= $room['max_occupants'] ?> người</span>
                                    <?php endif; ?>
                                </div>
                                
                                <p class="text-muted mb-2 small" style="font-size: 11px;">
                                    <i class="fa fa-map-marker-alt text-primary me-1"></i>
                                    <?= htmlspecialchars(mb_substr($room['address'], 0, 30)) ?>...
                                </p>
                                
                                <!-- Nút XEM PHÒNG + ĐẶT PHÒNG - luôn ở dưới cùng -->
                                <div class="d-flex gap-1 mt-auto">
                                    <!-- Xem chi tiết phòng -->
                                    <a class="btn btn-sm btn-outline-primary flex-grow-1" href="/quanlyphongtro/client/index.php?page=chitiet_phong&room_id=<?= $room['room_id'] ?>" title="Xem chi tiết phòng">
                                        <i class="fa fa-eye"></i>
                                    </a>
                                    <!-- Đặt phòng - disable nếu phòng đã thuê hoặc có người chờ -->
                                    <?php 
                                    // Không cho đặt nếu: đang thuê, đã đặt cọc, hoặc có người chờ
                                    $isRented = !empty($room['active_booking_id']) || !empty($room['deposit_paid_booking_id']);
                                    $hasWaiting = !empty($room['reserved_booking_id']) || !empty($room['pending_booking_id']);
                                    
                                    if ($isRented): 
                                    ?>
                                        <button class="btn btn-sm btn-danger flex-grow-1" disabled title="Phòng đã được thuê trong khoảng thời gian này">
                                            <i class="fa fa-ban"></i> Đã thuê
                                        </button>
                                    <?php elseif ($hasWaiting): ?>
                                        <button class="btn btn-sm btn-outline-secondary flex-grow-1" disabled title="Có người đang giữ phòng">
                                            <i class="fa fa-lock"></i> Đã giữ
                                        </button>
                                    <?php else: ?>
                                        <?php 
                                        $datphongUrl = "/quanlyphongtro/client/index.php?page=datphong&room_id={$room['room_id']}";
                                        if ($checkInDate) $datphongUrl .= "&check_in=" . urlencode($checkInDate);
                                        if ($checkOutDate) $datphongUrl .= "&check_out=" . urlencode($checkOutDate);
                                        ?>
                                        <a class="btn btn-sm btn-success flex-grow-1" href="<?= $datphongUrl ?>" title="Đặt phòng">
                                            <i class="fa fa-calendar-check"></i> Đặt
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-12">
                    <div class="text-center py-5">
                        <i class="bi bi-house" style="font-size: 80px; color: #ddd;"></i>
                        <h4 class="mt-4 text-muted">Không tìm thấy phòng trọ phù hợp</h4>
                        <p class="text-muted">Thử điều chỉnh bộ lọc hoặc <a href="/quanlyphongtro/client/index.php?page=phong">xem tất cả</a></p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
            <nav class="mt-5 wow fadeInUp" data-wow-delay="0.1s">
                <ul class="pagination justify-content-center">
                    <?php $prev = max(1, $p-1); $next = min($totalPages, $p+1); ?>
                    <li class="page-item <?= $p<=1?'disabled':'' ?>">
                        <a class="page-link" href="/quanlyphongtro/client/index.php?<?= http_build_query(array_merge($keep, ['p'=>$prev])) ?>">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    </li>
                    <?php
                    $start = max(1, $p-2);
                    $end = min($totalPages, $p+2);
                    for ($i=$start; $i<=$end; $i++):
                    ?>
                        <li class="page-item <?= $i===$p?'active':'' ?>">
                            <a class="page-link" href="/quanlyphongtro/client/index.php?<?= http_build_query(array_merge($keep, ['p'=>$i])) ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?= $p>=$totalPages?'disabled':'' ?>">
                        <a class="page-link" href="/quanlyphongtro/client/index.php?<?= http_build_query(array_merge($keep, ['p'=>$next])) ?>">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    </li>
                </ul>
            </nav>
        <?php endif; ?>
        
    </div>
</div>

<script>
// Tự động cập nhật min của "Đến ngày" khi chọn "Từ ngày"
document.addEventListener('DOMContentLoaded', function() {
    const checkInInput = document.querySelector('input[name="check_in"]');
    const checkOutInput = document.querySelector('input[name="check_out"]');
    
    if (checkInInput && checkOutInput) {
        checkInInput.addEventListener('change', function() {
            // Set min của check_out = check_in + 1 ngày
            if (this.value) {
                const nextDay = new Date(this.value);
                nextDay.setDate(nextDay.getDate() + 1);
                checkOutInput.min = nextDay.toISOString().split('T')[0];
                
                // Clear check_out nếu nó nhỏ hơn check_in mới
                if (checkOutInput.value && checkOutInput.value <= this.value) {
                    checkOutInput.value = '';
                }
            }
        });
    }
    
    // Cascading dropdowns for Region -> Province -> District
    const regionSelect = document.getElementById('filterRegion');
    const provinceSelect = document.getElementById('filterProvince');
    const districtSelect = document.getElementById('filterDistrict');
    
    if (regionSelect && provinceSelect) {
        regionSelect.addEventListener('change', function() {
            const regionId = this.value;
            provinceSelect.innerHTML = '<option value="">-- Tất cả --</option>';
            districtSelect.innerHTML = '<option value="">-- Tất cả --</option>';
            
            if (regionId) {
                fetch('/quanlyphongtro/admin/api/get_provinces_by_region.php?region_id=' + regionId)
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            data.data.forEach(prov => {
                                provinceSelect.innerHTML += `<option value="${prov.province_id}">${prov.province_name}</option>`;
                            });
                        }
                    });
            }
        });
    }
    
    if (provinceSelect && districtSelect) {
        provinceSelect.addEventListener('change', function() {
            const provinceId = this.value;
            districtSelect.innerHTML = '<option value="">-- Tất cả --</option>';
            
            if (provinceId) {
                fetch('/quanlyphongtro/admin/api/get_districts_by_province.php?province_id=' + provinceId)
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            data.data.forEach(dist => {
                                districtSelect.innerHTML += `<option value="${dist.district_id}">${dist.district_name}</option>`;
                            });
                        }
                    });
            }
        });
    }
});
</script>
