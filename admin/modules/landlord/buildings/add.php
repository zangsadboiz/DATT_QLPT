<?php
/**
 * Module Dãy trọ - Thêm mới (có upload ảnh)
 */
require_once __DIR__ . '/../../../includes/auth.php';
require_landlord_login();

$userId = (int)($_SESSION['user_id'] ?? 0);

// Get districts
$districts = [];
$rsD = mysqli_query($conn, "SELECT district_id, district_name FROM districts ORDER BY district_name");
while ($rsD && ($d = mysqli_fetch_assoc($rsD))) $districts[] = $d;

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $buildingName = trim($_POST['building_name'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $districtId = (int)($_POST['district_id'] ?? 0);
    $totalFloors = 1; // Default value, not used in UI
    $description = trim($_POST['description'] ?? '');
    $rules = trim($_POST['rules'] ?? '');
    $electricityPrice = (float)($_POST['electricity_price'] ?? 0);
    $waterPrice = (float)($_POST['water_price'] ?? 0);

    if ($buildingName === '' || $address === '') {
        $error = 'Vui lòng nhập tên dãy trọ và địa chỉ.';
    } else {
        $buildingCode = 'DT' . date('ymdHis') . rand(10, 99);
        $districtIdOrNull = $districtId > 0 ? $districtId : null;
        $elecVal = $electricityPrice > 0 ? $electricityPrice : null;
        $waterVal = $waterPrice > 0 ? $waterPrice : null;
        
        $stmt = mysqli_prepare($conn, "
            INSERT INTO buildings (owner_id, building_code, building_name, address, district_id, total_floors, description, rules, electricity_price, water_price, building_status, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'ACTIVE', NOW())
        ");
        mysqli_stmt_bind_param($stmt, 'issssissdd', $userId, $buildingCode, $buildingName, $address, $districtIdOrNull, $totalFloors, $description, $rules, $elecVal, $waterVal);
        
        if (mysqli_stmt_execute($stmt)) {
            $newBuildingId = mysqli_insert_id($conn);
            
            // Handle image upload
            if (!empty($_FILES['images']['name'][0])) {
                $uploadDir = __DIR__ . '/../../../../uploads/buildings/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                
                foreach ($_FILES['images']['tmp_name'] as $i => $tmpName) {
                    if ($_FILES['images']['error'][$i] === UPLOAD_ERR_OK) {
                        $ext = strtolower(pathinfo($_FILES['images']['name'][$i], PATHINFO_EXTENSION));
                        if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                            $filename = 'building_' . $newBuildingId . '_' . time() . '_' . $i . '.' . $ext;
                            if (move_uploaded_file($tmpName, $uploadDir . $filename)) {
                                $isPrimary = ($i === 0) ? 1 : 0;
                                mysqli_query($conn, "INSERT INTO building_images (building_id, image_path, is_primary, sort_order) VALUES ($newBuildingId, '$filename', $isPrimary, $i)");
                            }
                        }
                    }
                }
            }
            
            $_SESSION['alert'] = ['type' => 'success', 'message' => "Đã thêm dãy trọ '$buildingName' thành công!"];
            header('Location: ' . ADMIN_BASE_PATH . '/modules/landlord/buildings/index.php');
            exit;
        } else {
            $error = 'Lỗi tạo dãy trọ: ' . mysqli_error($conn);
        }
        mysqli_stmt_close($stmt);
    }
}

require_once __DIR__ . '/../../../includes/header.php';
?>

<div class="pagetitle">
    <h1><i class="bi bi-plus-circle me-2"></i>Thêm dãy trọ mới</h1>
    <nav>
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= ADMIN_BASE_PATH ?>/modules/landlord/dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="<?= ADMIN_BASE_PATH ?>/modules/landlord/buildings/index.php">Dãy trọ</a></li>
            <li class="breadcrumb-item active">Thêm mới</li>
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
                    <div class="card-header"><h5 class="mb-0">Thông tin cơ bản</h5></div>
                    <div class="card-body pt-4">
                        <div class="mb-3">
                            <label class="form-label">Tên dãy trọ / Tòa nhà <span class="text-danger">*</span></label>
                            <input type="text" name="building_name" class="form-control" required
                                   value="<?= htmlspecialchars($_POST['building_name'] ?? '') ?>"
                                   placeholder="VD: Dãy trọ Hưng Phú, Nhà trọ Sinh Viên...">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Địa chỉ <span class="text-danger">*</span></label>
                            <input type="text" name="address" class="form-control" required
                                   value="<?= htmlspecialchars($_POST['address'] ?? '') ?>"
                                   placeholder="VD: Số 10, Đường ABC, Ngõ 5...">
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
                                       value="<?= htmlspecialchars($_POST['electricity_price'] ?? '') ?>"
                                       placeholder="VD: 3500">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Giá nước (đ/m³)</label>
                                <input type="number" name="water_price" class="form-control" min="0" step="1000"
                                       value="<?= htmlspecialchars($_POST['water_price'] ?? '') ?>"
                                       placeholder="VD: 15000">
                            </div>
                        </div>
                        <small class="text-muted">Giá mặc định sẽ áp dụng cho các phòng nếu không nhập riêng.</small>
                    </div>
                </div>
                
                <!-- Ảnh -->
                <div class="card">
                    <div class="card-header"><h5 class="mb-0">Hình ảnh</h5></div>
                    <div class="card-body pt-4">
                        <input type="file" name="images[]" class="form-control" multiple accept="image/*">
                        <small class="text-muted">Có thể chọn nhiều ảnh. Ảnh đầu tiên sẽ là ảnh đại diện.</small>
                    </div>
                </div>
                
                <!-- Mô tả & Nội quy -->
                <div class="card">
                    <div class="card-header"><h5 class="mb-0">Mô tả & Nội quy</h5></div>
                    <div class="card-body pt-4">
                        <div class="mb-3">
                            <label class="form-label">Mô tả</label>
                            <textarea name="description" class="form-control" rows="3"
                                      placeholder="Mô tả thêm về dãy trọ (tiện ích chung, ghi chú...)"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Nội quy chung</label>
                            <textarea name="rules" class="form-control" rows="3"
                                      placeholder="Nội quy áp dụng cho toàn bộ dãy trọ..."><?= htmlspecialchars($_POST['rules'] ?? '') ?></textarea>
                        </div>
                    </div>
                </div>
                
                <div class="d-flex gap-2 mb-4">
                    <a href="<?= ADMIN_BASE_PATH ?>/modules/landlord/buildings/index.php" class="btn btn-secondary">
                        <i class="bi bi-arrow-left me-1"></i>Quay lại
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-circle me-1"></i>Thêm dãy trọ
                    </button>
                </div>
            </form>
        </div>
        
        <div class="col-lg-4">
            <div class="card bg-light">
                <div class="card-body">
                    <h6><i class="bi bi-lightbulb me-2"></i>Hướng dẫn</h6>
                    <ul class="mb-0 small">
                        <li>Sau khi tạo dãy trọ, bạn có thể thêm các phòng</li>
                        <li>Giá điện/nước mặc định sẽ được áp dụng cho phòng</li>
                        <li>Nội quy chung áp dụng cho toàn bộ dãy trọ</li>
                    </ul>
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
</script>

<?php require_once __DIR__ . '/../../../includes/footer.php'; ?>
