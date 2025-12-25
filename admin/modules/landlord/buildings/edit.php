<?php
/**
 * Module Dãy trọ - Sửa (có upload ảnh)
 */
require_once __DIR__ . '/../../../includes/auth.php';
require_landlord_login();

$userId = (int)($_SESSION['user_id'] ?? 0);
$buildingId = (int)($_GET['id'] ?? 0);

if ($buildingId <= 0) {
    header('Location: ' . ADMIN_BASE_PATH . '/modules/landlord/buildings/index.php');
    exit;
}

$building = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM buildings WHERE building_id = $buildingId AND owner_id = $userId"));
if (!$building) {
    $_SESSION['alert'] = ['type' => 'danger', 'message' => 'Không tìm thấy dãy trọ!'];
    header('Location: ' . ADMIN_BASE_PATH . '/modules/landlord/buildings/index.php');
    exit;
}

// Get current district's province and region
$currentRegion = 0;
$currentProvince = 0;
if ($building['district_id']) {
    $districtInfo = mysqli_fetch_assoc(mysqli_query($conn, "
        SELECT d.district_id, d.province_id, p.region_id 
        FROM districts d 
        LEFT JOIN provinces p ON p.province_id = d.province_id 
        WHERE d.district_id = {$building['district_id']}
    "));
    if ($districtInfo) {
        $currentProvince = (int)$districtInfo['province_id'];
        $currentRegion = (int)$districtInfo['region_id'];
    }
}

// Get current images
$images = [];
$rsImg = mysqli_query($conn, "SELECT * FROM building_images WHERE building_id = $buildingId ORDER BY is_primary DESC, sort_order");
while ($rsImg && ($img = mysqli_fetch_assoc($rsImg))) $images[] = $img;

$error = '';

// Handle delete image
if (isset($_GET['delete_image'])) {
    $imgId = (int)$_GET['delete_image'];
    $img = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM building_images WHERE image_id = $imgId AND building_id = $buildingId"));
    if ($img) {
        @unlink(__DIR__ . '/../../../../uploads/buildings/' . $img['image_path']);
        mysqli_query($conn, "DELETE FROM building_images WHERE image_id = $imgId");
    }
    header('Location: ' . ADMIN_BASE_PATH . '/modules/landlord/buildings/edit.php?id=' . $buildingId);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $buildingName = trim($_POST['building_name'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $districtId = (int)($_POST['district_id'] ?? 0);
    $totalFloors = $building['total_floors'] ?? 1; // Keep existing value, not editable in UI
    $description = trim($_POST['description'] ?? '');
    $rules = trim($_POST['rules'] ?? '');
    $electricityPrice = (float)($_POST['electricity_price'] ?? 0);
    $waterPrice = (float)($_POST['water_price'] ?? 0);

    if ($buildingName === '' || $address === '') {
        $error = 'Vui lòng nhập tên dãy trọ và địa chỉ.';
    } else {
        $districtIdOrNull = $districtId > 0 ? $districtId : null;
        $elecVal = $electricityPrice > 0 ? $electricityPrice : null;
        $waterVal = $waterPrice > 0 ? $waterPrice : null;
        
        $stmt = mysqli_prepare($conn, "
            UPDATE buildings 
            SET building_name = ?, address = ?, district_id = ?, total_floors = ?, description = ?, rules = ?, electricity_price = ?, water_price = ?, updated_at = NOW()
            WHERE building_id = ? AND owner_id = ?
        ");
        mysqli_stmt_bind_param($stmt, 'ssisssddii', $buildingName, $address, $districtIdOrNull, $totalFloors, $description, $rules, $elecVal, $waterVal, $buildingId, $userId);
        
        if (mysqli_stmt_execute($stmt)) {
            // Handle new image upload
            if (!empty($_FILES['images']['name'][0])) {
                $uploadDir = __DIR__ . '/../../../../uploads/buildings/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                
                $existingCount = count($images);
                foreach ($_FILES['images']['tmp_name'] as $i => $tmpName) {
                    if ($_FILES['images']['error'][$i] === UPLOAD_ERR_OK) {
                        $ext = strtolower(pathinfo($_FILES['images']['name'][$i], PATHINFO_EXTENSION));
                        if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                            $filename = 'building_' . $buildingId . '_' . time() . '_' . $i . '.' . $ext;
                            if (move_uploaded_file($tmpName, $uploadDir . $filename)) {
                                $isPrimary = ($existingCount === 0 && $i === 0) ? 1 : 0;
                                mysqli_query($conn, "INSERT INTO building_images (building_id, image_path, is_primary, sort_order) VALUES ($buildingId, '$filename', $isPrimary, " . ($existingCount + $i) . ")");
                            }
                        }
                    }
                }
            }
            
            $_SESSION['alert'] = ['type' => 'success', 'message' => 'Đã cập nhật dãy trọ thành công!'];
            header('Location: ' . ADMIN_BASE_PATH . '/modules/landlord/buildings/index.php');
            exit;
        } else {
            $error = 'Lỗi cập nhật: ' . mysqli_error($conn);
        }
        mysqli_stmt_close($stmt);
    }
}

require_once __DIR__ . '/../../../includes/header.php';
?>

<div class="pagetitle">
    <h1><i class="bi bi-pencil me-2"></i>Sửa dãy trọ</h1>
    <nav>
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= ADMIN_BASE_PATH ?>/modules/landlord/dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="<?= ADMIN_BASE_PATH ?>/modules/landlord/buildings/index.php">Dãy trọ</a></li>
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
                        <code><?= htmlspecialchars($building['building_code']) ?></code>
                    </div>
                    <div class="card-body pt-4">
                        <div class="mb-3">
                            <label class="form-label">Tên dãy trọ / Tòa nhà <span class="text-danger">*</span></label>
                            <input type="text" name="building_name" class="form-control" required
                                   value="<?= htmlspecialchars($_POST['building_name'] ?? $building['building_name']) ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Địa chỉ <span class="text-danger">*</span></label>
                            <input type="text" name="address" class="form-control" required
                                   value="<?= htmlspecialchars($_POST['address'] ?? $building['address']) ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Miền <span class="text-danger">*</span></label>
                            <select name="region_id" id="region" class="form-select" required>
                                <option value="">-- Chọn miền --</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Tỉnh/Thành phố <span class="text-danger">*</span></label>
                            <select name="province_id" id="province" class="form-select" disabled required>
                                <option value="">-- Chọn tỉnh/thành phố --</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Quận/Huyện <span class="text-danger">*</span></label>
                            <select name="district_id" id="district" class="form-select" disabled required>
                                <option value="">-- Chọn quận/huyện --</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <!-- Giá dịch vụ mặc định -->
                <div class="card">
                    <div class="card-header"><h5 class="mb-0">Giá dịch vụ mặc định</h5></div>
                    <div class="card-body pt-4">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Giá điện (đ/kWh)</label>
                                <input type="number" name="electricity_price" class="form-control" min="0" step="100"
                                       value="<?= htmlspecialchars($_POST['electricity_price'] ?? $building['electricity_price']) ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Giá nước (đ/m³)</label>
                                <input type="number" name="water_price" class="form-control" min="0" step="1000"
                                       value="<?= htmlspecialchars($_POST['water_price'] ?? $building['water_price']) ?>">
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Ảnh -->
                <div class="card">
                    <div class="card-header"><h5 class="mb-0">Hình ảnh</h5></div>
                    <div class="card-body pt-4">
                        <?php if (count($images) > 0): ?>
                            <div class="row g-2 mb-3">
                                <?php foreach ($images as $img): ?>
                                    <div class="col-md-3 col-4">
                                        <div class="position-relative">
                                            <img src="/quanlyphongtro/uploads/buildings/<?= htmlspecialchars($img['image_path']) ?>" 
                                                 class="img-fluid rounded" style="height: 100px; width: 100%; object-fit: cover;">
                                            <?php if ($img['is_primary']): ?>
                                                <span class="badge bg-primary position-absolute top-0 start-0 m-1">Chính</span>
                                            <?php endif; ?>
                                            <a href="?id=<?= $buildingId ?>&delete_image=<?= $img['image_id'] ?>" 
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
                            <label class="form-label">Mô tả</label>
                            <textarea name="description" class="form-control" rows="3"><?= htmlspecialchars($_POST['description'] ?? $building['description']) ?></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Nội quy chung</label>
                            <textarea name="rules" class="form-control" rows="3"><?= htmlspecialchars($_POST['rules'] ?? $building['rules']) ?></textarea>
                        </div>
                    </div>
                </div>
                
                <div class="d-flex gap-2 mb-4">
                    <a href="<?= ADMIN_BASE_PATH ?>/modules/landlord/buildings/index.php" class="btn btn-secondary">
                        <i class="bi bi-arrow-left me-1"></i>Quay lại
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-circle me-1"></i>Lưu thay đổi
                    </button>
                    <a href="<?= ADMIN_BASE_PATH ?>/modules/landlord/rooms/index.php?building_id=<?= $buildingId ?>" class="btn btn-success ms-auto">
                        <i class="bi bi-door-open me-1"></i>Quản lý phòng
                    </a>
                </div>
            </form>
        </div>
        
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header"><h6 class="mb-0">Thông tin</h6></div>
                <div class="card-body">
                    <p><strong>Mã:</strong> <?= htmlspecialchars($building['building_code']) ?></p>
                    <p><strong>Trạng thái:</strong> 
                        <?php if ($building['building_status'] === 'ACTIVE'): ?>
                            <span class="badge bg-success">Hoạt động</span>
                        <?php else: ?>
                            <span class="badge bg-secondary">Ẩn</span>
                        <?php endif; ?>
                    </p>
                    <p><strong>Ngày tạo:</strong> <?= date('d/m/Y', strtotime($building['created_at'])) ?></p>
                </div>
            </div>
        </div>
    </div>
</section>

<script src="/quanlyphongtro/admin/assets/js/address-selector.js"></script>
<script>
// Initialize address selector
const addressSelector = new AddressSelector({
    regionSelect: '#region',
    provinceSelect: '#province',
    districtSelect: '#district',
    apiBasePath: '/quanlyphongtro/admin/api'
});

// Set current values after page load
window.addEventListener('load', function() {
    <?php if ($currentRegion && $currentProvince && $building['district_id']): ?>
    addressSelector.setValues(
        <?= $currentRegion ?>,
        <?= $currentProvince ?>,
        <?= $building['district_id'] ?>
    );
    <?php endif; ?>
});
</script>

<?php require_once __DIR__ . '/../../../includes/footer.php'; ?>
