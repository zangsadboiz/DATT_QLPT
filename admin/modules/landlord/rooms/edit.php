<?php
/**
 * Module Phòng - Sửa (Chi tiết đầy đủ)
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
    SELECT r.*, b.owner_id, b.building_name
    FROM rooms r
    JOIN buildings b ON b.building_id = r.building_id
    WHERE r.room_id = $roomId AND b.owner_id = $userId AND r.deleted_at IS NULL
"));
if (!$room) {
    $_SESSION['alert'] = ['type' => 'danger', 'message' => 'Không tìm thấy phòng!'];
    header('Location: ' . ADMIN_BASE_PATH . '/modules/landlord/rooms/index.php');
    exit;
}

// Get buildings
$buildings = [];
$rsB = mysqli_query($conn, "SELECT building_id, building_name FROM buildings WHERE owner_id = $userId ORDER BY building_name");
while ($rsB && ($b = mysqli_fetch_assoc($rsB))) $buildings[] = $b;

// Get current images
$images = [];
$rsImg = mysqli_query($conn, "SELECT * FROM room_images WHERE room_id = $roomId ORDER BY is_primary DESC, sort_order");
while ($rsImg && ($img = mysqli_fetch_assoc($rsImg))) $images[] = $img;

$currentAmenities = json_decode($room['amenities'] ?: '[]', true) ?: [];

$amenityOptions = [
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

$error = '';

// Handle delete image
if (isset($_GET['delete_image'])) {
    $imgId = (int)$_GET['delete_image'];
    $img = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM room_images WHERE image_id = $imgId AND room_id = $roomId"));
    if ($img) {
        @unlink(__DIR__ . '/../../../../uploads/rooms/' . $img['image_path']);
        mysqli_query($conn, "DELETE FROM room_images WHERE image_id = $imgId");
    }
    header('Location: ' . ADMIN_BASE_PATH . '/modules/landlord/rooms/edit.php?id=' . $roomId);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $buildingId = (int)($_POST['building_id'] ?? 0);
    $roomCode = trim($_POST['room_code'] ?? '');
    $floor = max(1, (int)($_POST['floor'] ?? 1));
    $area = (float)($_POST['area'] ?? 0);
    $baseRent = (float)($_POST['base_rent'] ?? 0);
    $deposit = (float)($_POST['deposit'] ?? 0);
    $electricityPrice = (float)($_POST['electricity_price'] ?? 0);
    $waterPrice = (float)($_POST['water_price'] ?? 0);
    $internetPrice = (float)($_POST['internet_price'] ?? 0);
    $parkingPrice = (float)($_POST['parking_price'] ?? 0);
    $maxOccupants = max(1, (int)($_POST['max_occupants'] ?? 2));
    $roomStatus = $_POST['room_status'] ?? 'VACANT';
    $description = trim($_POST['description'] ?? '');
    $rules = trim($_POST['rules'] ?? '');
    $amenities = $_POST['amenities'] ?? [];
    $rentalType = ($_POST['rental_type'] ?? 'MONTHLY') === 'DAILY' ? 'DAILY' : 'MONTHLY';
    $dailyPrice = $rentalType === 'DAILY' ? (float)($_POST['daily_price'] ?? 0) : null;

    $building = mysqli_fetch_assoc(mysqli_query($conn, "SELECT building_id FROM buildings WHERE building_id = $buildingId AND owner_id = $userId"));
    if (!$building) {
        $error = 'Dãy trọ không hợp lệ!';
    } elseif ($roomCode === '') {
        $error = 'Vui lòng nhập mã phòng.';
    } elseif ($area <= 0) {
        $error = 'Vui lòng nhập diện tích phòng.';
    } elseif ($rentalType === 'DAILY' && $dailyPrice <= 0) {
        $error = 'Vui lòng nhập giá thuê theo ngày.';
    } elseif ($rentalType === 'MONTHLY' && $baseRent <= 0) {
        $error = 'Vui lòng nhập giá thuê theo tháng.';
    } else {
        $dup = mysqli_fetch_assoc(mysqli_query($conn, "SELECT room_id FROM rooms WHERE building_id = $buildingId AND room_code = '" . mysqli_real_escape_string($conn, $roomCode) . "' AND room_id != $roomId AND deleted_at IS NULL"));
        if ($dup) {
            $error = 'Mã phòng đã tồn tại trong dãy trọ này!';
        } else {
            $amenitiesJson = json_encode($amenities);
            
            $areaVal = $area > 0 ? $area : null;
            $depositVal = $deposit > 0 ? $deposit : null;
            $elecVal = $electricityPrice > 0 ? $electricityPrice : null;
            $waterVal = $waterPrice > 0 ? $waterPrice : null;
            $netVal = $internetPrice > 0 ? $internetPrice : null;
            $parkVal = $parkingPrice > 0 ? $parkingPrice : null;
            
            $stmt = mysqli_prepare($conn, "
                UPDATE rooms SET 
                    building_id = ?, room_code = ?, floor = ?, area = ?, base_rent = ?, daily_price = ?, rental_type = ?, deposit = ?,
                    electricity_price = ?, water_price = ?, internet_price = ?, parking_price = ?,
                    max_occupants = ?, room_status = ?, description = ?, rules = ?, amenities = ?, updated_at = NOW()
                WHERE room_id = ?
            ");
            mysqli_stmt_bind_param($stmt, 'isidddsdddddissssi', 
                $buildingId, $roomCode, $floor, $areaVal, $baseRent, $dailyPrice, $rentalType, $depositVal,
                $elecVal, $waterVal, $netVal, $parkVal,
                $maxOccupants, $roomStatus, $description, $rules, $amenitiesJson, $roomId);
            
            if (mysqli_stmt_execute($stmt)) {
                // Handle new image upload
                if (!empty($_FILES['images']['name'][0])) {
                    $uploadDir = __DIR__ . '/../../../../uploads/rooms/';
                    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                    
                    $existingCount = count($images);
                    foreach ($_FILES['images']['tmp_name'] as $i => $tmpName) {
                        if ($_FILES['images']['error'][$i] === UPLOAD_ERR_OK) {
                            $ext = pathinfo($_FILES['images']['name'][$i], PATHINFO_EXTENSION);
                            $filename = 'room_' . $roomId . '_' . time() . '_' . $i . '.' . $ext;
                            if (move_uploaded_file($tmpName, $uploadDir . $filename)) {
                                $isPrimary = ($existingCount === 0 && $i === 0) ? 1 : 0;
                                mysqli_query($conn, "INSERT INTO room_images (room_id, image_path, is_primary, sort_order) VALUES ($roomId, '$filename', $isPrimary, " . ($existingCount + $i) . ")");
                            }
                        }
                    }
                }
                
                $_SESSION['alert'] = ['type' => 'success', 'message' => 'Đã cập nhật phòng thành công!'];
                header('Location: ' . ADMIN_BASE_PATH . '/modules/landlord/rooms/index.php?building_id=' . $buildingId);
                exit;
            } else {
                $error = 'Lỗi cập nhật: ' . mysqli_error($conn);
            }
            mysqli_stmt_close($stmt);
        }
    }
}

require_once __DIR__ . '/../../../includes/header.php';
?>

<div class="pagetitle">
    <h1><i class="bi bi-pencil me-2"></i>Sửa phòng: <?= htmlspecialchars($room['room_code']) ?></h1>
    <nav>
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= ADMIN_BASE_PATH ?>/modules/landlord/dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="<?= ADMIN_BASE_PATH ?>/modules/landlord/rooms/index.php">Phòng</a></li>
            <li class="breadcrumb-item active">Sửa</li>
        </ol>
    </nav>
</div>

<section class="section">
    <div class="row">
        <div class="col-lg-8">
            <?php if ($error): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            
            <form method="post" action="" enctype="multipart/form-data">
                <!-- Thông tin cơ bản -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Thông tin cơ bản</h5>
                        <span class="text-muted"><?= htmlspecialchars($room['building_name']) ?></span>
                    </div>
                    <div class="card-body pt-4">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Dãy trọ <span class="text-danger">*</span></label>
                                <select name="building_id" class="form-select" required>
                                    <?php foreach ($buildings as $b): ?>
                                        <option value="<?= $b['building_id'] ?>" 
                                            <?= (($_POST['building_id'] ?? $room['building_id']) == $b['building_id']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($b['building_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Mã phòng <span class="text-danger">*</span></label>
                                <input type="text" name="room_code" class="form-control" required
                                       value="<?= htmlspecialchars($_POST['room_code'] ?? $room['room_code']) ?>">
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Tầng</label>
                                <input type="number" name="floor" class="form-control" min="1" max="50"
                                       value="<?= htmlspecialchars($_POST['floor'] ?? $room['floor']) ?>">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Diện tích (m²)</label>
                                <input type="number" name="area" class="form-control" step="0.1" min="0"
                                       value="<?= htmlspecialchars($_POST['area'] ?? $room['area']) ?>">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Số người</label>
                                <input type="number" name="max_occupants" class="form-control" min="1" max="10"
                                       value="<?= htmlspecialchars($_POST['max_occupants'] ?? $room['max_occupants']) ?>">
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-12">
                                <label class="form-label">Loại cho thuê <span class="text-danger">*</span></label>
                                <div class="d-flex gap-4">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="rental_type" id="rentalMonthly" value="MONTHLY" 
                                            <?= ($_POST['rental_type'] ?? $room['rental_type'] ?? 'MONTHLY') === 'MONTHLY' ? 'checked' : '' ?> onchange="toggleRentalType()">
                                        <label class="form-check-label" for="rentalMonthly">
                                            <i class="bi bi-calendar-month me-1"></i>Theo tháng
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="rental_type" id="rentalDaily" value="DAILY"
                                            <?= ($_POST['rental_type'] ?? $room['rental_type'] ?? '') === 'DAILY' ? 'checked' : '' ?> onchange="toggleRentalType()">
                                        <label class="form-check-label" for="rentalDaily">
                                            <i class="bi bi-calendar-day me-1"></i>Theo ngày
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3" id="monthlyPriceGroup">
                                <label class="form-label">Giá thuê (VNĐ/tháng) <span class="text-danger">*</span></label>
                                <input type="text" class="form-control price-format" id="base_rent_display"
                                       value="<?= number_format((float)($_POST['base_rent'] ?? $room['base_rent'] ?? 0)) ?>"
                                       placeholder="VD: 1,500,000" inputmode="numeric">
                                <input type="hidden" name="base_rent" id="base_rent_value"
                                       value="<?= htmlspecialchars($_POST['base_rent'] ?? $room['base_rent'] ?? '') ?>">
                            </div>
                            <div class="col-md-6 mb-3" id="dailyPriceGroup" style="display: none;">
                                <label class="form-label">Giá thuê (VNĐ/ngày) <span class="text-danger">*</span></label>
                                <input type="text" class="form-control price-format" id="daily_price_display"
                                       value="<?= number_format((float)($_POST['daily_price'] ?? $room['daily_price'] ?? 0)) ?>"
                                       placeholder="VD: 200,000" inputmode="numeric">
                                <input type="hidden" name="daily_price" id="daily_price_value"
                                       value="<?= htmlspecialchars($_POST['daily_price'] ?? $room['daily_price'] ?? '') ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Tiền cọc</label>
                                <input type="number" name="deposit" class="form-control" min="0" step="100000"
                                       value="<?= htmlspecialchars($_POST['deposit'] ?? $room['deposit']) ?>">
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Phí dịch vụ -->
                <div class="card">
                    <div class="card-header"><h5 class="mb-0">Phí dịch vụ</h5></div>
                    <div class="card-body pt-4">
                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Giá điện (đ/kWh)</label>
                                <input type="number" name="electricity_price" class="form-control" min="0" step="100"
                                       value="<?= htmlspecialchars($_POST['electricity_price'] ?? $room['electricity_price']) ?>">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Giá nước (đ/m³)</label>
                                <input type="number" name="water_price" class="form-control" min="0" step="1000"
                                       value="<?= htmlspecialchars($_POST['water_price'] ?? $room['water_price']) ?>">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Internet (đ/tháng)</label>
                                <input type="number" name="internet_price" class="form-control" min="0" step="10000"
                                       value="<?= htmlspecialchars($_POST['internet_price'] ?? $room['internet_price']) ?>">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Gửi xe (đ/tháng)</label>
                                <input type="number" name="parking_price" class="form-control" min="0" step="10000"
                                       value="<?= htmlspecialchars($_POST['parking_price'] ?? $room['parking_price']) ?>">
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Tiện nghi -->
                <div class="card">
                    <div class="card-header"><h5 class="mb-0">Tiện nghi</h5></div>
                    <div class="card-body pt-4">
                        <div class="row">
                            <?php 
                            $selectedAmenities = $_POST['amenities'] ?? $currentAmenities;
                            foreach ($amenityOptions as $key => $label): 
                            ?>
                                <div class="col-md-4 col-6 mb-2">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="amenities[]" 
                                               value="<?= $key ?>" id="am_<?= $key ?>"
                                               <?= in_array($key, $selectedAmenities) ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="am_<?= $key ?>"><?= $label ?></label>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Ảnh phòng -->
                <div class="card">
                    <div class="card-header"><h5 class="mb-0">Ảnh phòng</h5></div>
                    <div class="card-body pt-4">
                        <?php if (count($images) > 0): ?>
                            <div class="row g-2 mb-3">
                                <?php foreach ($images as $img): ?>
                                    <div class="col-md-3 col-4">
                                        <div class="position-relative">
                                            <img src="/quanlyphongtro/uploads/rooms/<?= htmlspecialchars($img['image_path']) ?>" 
                                                 class="img-fluid rounded" style="height: 100px; width: 100%; object-fit: cover;">
                                            <?php if ($img['is_primary']): ?>
                                                <span class="badge bg-primary position-absolute top-0 start-0 m-1">Chính</span>
                                            <?php endif; ?>
                                            <a href="?id=<?= $roomId ?>&delete_image=<?= $img['image_id'] ?>" 
                                               class="btn btn-danger btn-sm position-absolute top-0 end-0 m-1"
                                               onclick="return confirm('Xóa ảnh này?')">
                                                <i class="bi bi-trash"></i>
                                            </a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        <input type="file" name="images[]" class="form-control" multiple accept="image/*">
                        <small class="text-muted">Thêm ảnh mới</small>
                    </div>
                </div>
                
                <!-- Mô tả & Nội quy -->
                <div class="card">
                    <div class="card-header"><h5 class="mb-0">Mô tả & Nội quy</h5></div>
                    <div class="card-body pt-4">
                        <div class="mb-3">
                            <label class="form-label">Mô tả phòng</label>
                            <textarea name="description" class="form-control" rows="3"><?= htmlspecialchars($_POST['description'] ?? $room['description']) ?></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Nội quy phòng</label>
                            <textarea name="rules" class="form-control" rows="4"
                                      placeholder="VD: Không nuôi thú cưng, không hút thuốc trong phòng..."><?= htmlspecialchars($_POST['rules'] ?? $room['rules']) ?></textarea>
                        </div>
                    </div>
                </div>
                
                <div class="d-flex gap-2 mb-4">
                    <a href="<?= ADMIN_BASE_PATH ?>/modules/landlord/rooms/index.php?building_id=<?= $room['building_id'] ?>" class="btn btn-secondary">
                        <i class="bi bi-arrow-left me-1"></i>Quay lại
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-circle me-1"></i>Lưu thay đổi
                    </button>
                </div>
            </form>
        </div>
        
        <!-- Sidebar info -->
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header"><h6 class="mb-0">Thông tin phòng</h6></div>
                <div class="card-body">
                    <p><strong>Dãy trọ:</strong> <?= htmlspecialchars($room['building_name']) ?></p>
                    <p><strong>Mã phòng:</strong> <?= htmlspecialchars($room['room_code']) ?></p>
                    <p><strong>Trạng thái:</strong> 
                        <?php if ($room['room_status'] === 'VACANT'): ?>
                            <span class="badge bg-success">Còn trống</span>
                        <?php elseif ($room['room_status'] === 'OCCUPIED'): ?>
                            <span class="badge bg-primary">Đang thuê</span>
                        <?php else: ?>
                            <span class="badge bg-warning">Bảo trì</span>
                        <?php endif; ?>
                    </p>
                    <p><strong>Ngày tạo:</strong> <?= date('d/m/Y', strtotime($room['created_at'])) ?></p>
                </div>
            </div>
        </div>
    </div>
</section>

<script>
// Toggle rental type price fields
function toggleRentalType() {
    var isDaily = document.getElementById('rentalDaily').checked;
    var monthlyGroup = document.getElementById('monthlyPriceGroup');
    var dailyGroup = document.getElementById('dailyPriceGroup');
    
    if (isDaily) {
        monthlyGroup.style.display = 'none';
        dailyGroup.style.display = 'block';
    } else {
        monthlyGroup.style.display = 'block';
        dailyGroup.style.display = 'none';
    }
}

// Price formatting with commas
function formatNumber(num) {
    return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
}

function unformatNumber(str) {
    return str.replace(/,/g, '');
}

// Format price inputs
document.querySelectorAll('.price-format').forEach(function(input) {
    input.addEventListener('input', function(e) {
        var value = unformatNumber(this.value);
        value = value.replace(/[^\d]/g, '');
        
        if (value) {
            this.value = formatNumber(value);
            var hiddenId = this.id.replace('_display', '_value');
            document.getElementById(hiddenId).value = value;
        } else {
            this.value = '';
            var hiddenId = this.id.replace('_display', '_value');
            document.getElementById(hiddenId).value = '';
        }
    });
    
    // Format initial value on load
    var value = unformatNumber(input.value);
    if (value && value !== '0') {
        input.value = formatNumber(value);
        var hiddenId = input.id.replace('_display', '_value');
        document.getElementById(hiddenId).value = value;
    } else {
        input.value = '';
    }
});

// Run on page load
document.addEventListener('DOMContentLoaded', function() {
    toggleRentalType();
});
</script>

<?php require_once __DIR__ . '/../../../includes/footer.php'; ?>
