<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/db.php';

$role = (string)($_SESSION['role_name'] ?? '');
$userId = (int)($_SESSION['user_id'] ?? 0);

if ($role !== 'LANDLORD') {
    header('Location: ' . ADMIN_BASE_PATH . '/index.php');
    exit;
}

// User info
$user = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM users WHERE user_id = $userId"));
$balance = (float)($user['balance'] ?? 0);

// Get provinces and packages
$provinces = mysqli_query($conn, "SELECT * FROM provinces ORDER BY province_name");
$packages = mysqli_query($conn, "SELECT * FROM packages WHERE is_active = 1 ORDER BY priority DESC");

// Process form
$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate inputs
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $postType = $_POST['post_type'] ?? 'ROOM';
    $area = (float)($_POST['area'] ?? 0);
    $price = (float)($_POST['price'] ?? 0);
    $deposit = (float)($_POST['deposit'] ?? 0);
    $maxOccupants = (int)($_POST['max_occupants'] ?? 2);
    $districtId = (int)($_POST['district_id'] ?? 0);
    $address = trim($_POST['address'] ?? '');
    $contactName = trim($_POST['contact_name'] ?? $user['full_name']);
    $contactPhone = trim($_POST['contact_phone'] ?? $user['phone']);
    $packageId = (int)($_POST['package_id'] ?? 4);
    $daysPosted = (int)($_POST['days_posted'] ?? 7);
    $amenities = $_POST['amenities'] ?? [];
    $roomId = (int)($_POST['room_id'] ?? 0); // Liên kết phòng (tùy chọn)
    
    // Validation
    if (empty($title)) $errors[] = 'Vui lòng nhập tiêu đề tin';
    if ($price <= 0) $errors[] = 'Giá thuê phải lớn hơn 0';
    if ($districtId <= 0) $errors[] = 'Vui lòng chọn Quận/Huyện';
    if (empty($address)) $errors[] = 'Vui lòng nhập địa chỉ chi tiết';
    
    // Calculate cost
    $package = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM packages WHERE package_id = $packageId"));
    $totalCost = ($package['price_per_day'] ?? 0) * $daysPosted;
    
    if ($totalCost > $balance) {
        $errors[] = "Số dư không đủ. Cần " . number_format($totalCost, 0, ',', '.') . "đ, hiện có " . number_format($balance, 0, ',', '.') . "đ";
    }
    
    if (empty($errors)) {
        // Generate post code
        $postCode = 'PT' . date('ymd') . str_pad((string)rand(1, 999), 3, '0', STR_PAD_LEFT);
        
        // Prepare data
        $titleEsc = mysqli_real_escape_string($conn, $title);
        $descEsc = mysqli_real_escape_string($conn, $description);
        $addressEsc = mysqli_real_escape_string($conn, $address);
        $contactNameEsc = mysqli_real_escape_string($conn, $contactName);
        $contactPhoneEsc = mysqli_real_escape_string($conn, $contactPhone);
        $amenitiesJson = mysqli_real_escape_string($conn, json_encode($amenities));
        $roomIdValue = $roomId > 0 ? $roomId : 'NULL';
        
        // Insert post
        $sql = "INSERT INTO posts (user_id, room_id, package_id, district_id, post_code, title, description, post_type, 
                area, price, deposit, max_occupants, address, amenities, contact_name, contact_phone, 
                status, days_posted, created_at)
                VALUES ($userId, $roomIdValue, $packageId, $districtId, '$postCode', '$titleEsc', '$descEsc', '$postType',
                $area, $price, $deposit, $maxOccupants, '$addressEsc', '$amenitiesJson', 
                '$contactNameEsc', '$contactPhoneEsc', 'PENDING', $daysPosted, NOW())";
        
        if (mysqli_query($conn, $sql)) {
            $newPostId = mysqli_insert_id($conn);
            
            // NOTE: Tiền sẽ được trừ khi Admin duyệt tin (approve.php), không trừ lúc tạo
            // Lưu lại chi phí vào post để dùng khi duyệt
            mysqli_query($conn, "UPDATE posts SET total_cost = $totalCost WHERE post_id = $newPostId");
            
            // Handle image uploads
            if (!empty($_FILES['images']['name'][0])) {
                $uploadDir = __DIR__ . '/../../../../uploads/posts/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                
                $isFirst = true;
                foreach ($_FILES['images']['name'] as $i => $name) {
                    if ($_FILES['images']['error'][$i] === UPLOAD_ERR_OK) {
                        $ext = pathinfo($name, PATHINFO_EXTENSION);
                        $newName = $postCode . '_' . ($i + 1) . '_' . time() . '.' . $ext;
                        $destPath = $uploadDir . $newName;
                        
                        if (move_uploaded_file($_FILES['images']['tmp_name'][$i], $destPath)) {
                            $isPrimary = $isFirst ? 1 : 0;
                            $isFirst = false;
                            mysqli_query($conn, "INSERT INTO post_images (post_id, image_path, is_primary, sort_order) 
                                VALUES ($newPostId, '$newName', $isPrimary, $i)");
                        }
                    }
                }
            }
            
            header('Location: ' . ADMIN_BASE_PATH . '/modules/landlord/posts/index.php?msg=created');
            exit;
        } else {
            $errors[] = 'Lỗi hệ thống: ' . mysqli_error($conn);
        }
    }
}

require_once __DIR__ . '/../../../includes/header.php';
?>

<div class="pagetitle">
    <h1><i class="bi bi-plus-circle me-2"></i>Đăng tin mới</h1>
    <nav>
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= ADMIN_BASE_PATH ?>/modules/landlord/dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item active">Đăng tin mới</li>
        </ol>
    </nav>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <ul class="mb-0">
            <?php foreach ($errors as $err): ?>
                <li><?= htmlspecialchars($err) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<section class="section">
    <form action="" method="POST" enctype="multipart/form-data">
        <div class="row">
            
            <!-- Main Content -->
            <div class="col-lg-8">
                <div class="card mb-3">
                    <div class="card-header"><h5 class="mb-0">Thông tin cơ bản</h5></div>
                    <div class="card-body">
                        
                        <div class="mb-3">
                            <label class="form-label">Loại tin <span class="text-danger">*</span></label>
                            <select name="post_type" class="form-select" required>
                                <option value="ROOM">Phòng trọ</option>
                                <option value="APARTMENT">Căn hộ / Chung cư mini</option>
                                <option value="HOUSE">Nhà nguyên căn</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Tiêu đề tin <span class="text-danger">*</span></label>
                            <input type="text" name="title" class="form-control" required
                                   placeholder="VD: Phòng trọ cao cấp Quận 1, đầy đủ nội thất"
                                   value="<?= htmlspecialchars($_POST['title'] ?? '') ?>">
                            <small class="text-muted">Tiêu đề ngắn gọn, hấp dẫn, nêu bật điểm mạnh</small>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Mô tả chi tiết</label>
                            <textarea name="description" id="description" class="form-control" rows="5"
                                      placeholder="Mô tả chi tiết về phòng, tiện ích, môi trường xung quanh..."><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                        </div>
                        
                    </div>
                </div>
                
                <!-- Chọn phòng từ hệ thống -->
                <div class="card mb-3 border-primary">
                    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="bi bi-door-open me-2"></i>Chọn phòng có sẵn</h5>
                    </div>
                    <div class="card-body">
                        <p class="text-muted small mb-3">
                            <i class="bi bi-info-circle me-1"></i>
                            Chọn phòng để tự động điền thông tin.
                        </p>
                        
                        <input type="hidden" name="room_id" id="room_id" value="">
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Dãy trọ</label>
                                <select id="select_building" class="form-select">
                                    <option value="">-- Không chọn phòng --</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Phòng trống</label>
                                <select id="select_room" class="form-select" disabled>
                                    <option value="">-- Chọn dãy trọ trước --</option>
                                </select>
                            </div>
                        </div>
                        
                        <div id="room_preview" class="alert alert-success d-none">
                            <strong><i class="bi bi-check-circle me-1"></i>Đã chọn phòng:</strong>
                            <span id="room_preview_text"></span>
                            <button type="button" class="btn btn-sm btn-outline-danger float-end" onclick="clearRoomSelection()">
                                <i class="bi bi-x"></i> Bỏ chọn
                            </button>
                        </div>
                    </div>
                </div>
                
                <div class="card mb-3">
                    <div class="card-header"><h5 class="mb-0">Thông tin phòng</h5></div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Diện tích (m²)</label>
                                <input type="number" name="area" class="form-control" step="0.1" min="0"
                                       value="<?= $_POST['area'] ?? '20' ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Số người ở tối đa</label>
                                <input type="number" name="max_occupants" class="form-control" min="1" max="10"
                                       value="<?= $_POST['max_occupants'] ?? '2' ?>">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Giá thuê/tháng (VNĐ) <span class="text-danger">*</span></label>
                                <input type="number" name="price" class="form-control" min="100000" step="50000" required
                                       value="<?= $_POST['price'] ?? '1500000' ?>" placeholder="1500000">
                                <small class="text-muted">VD: 1500000 = 1.5 triệu</small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Tiền cọc (VNĐ)</label>
                                <input type="number" name="deposit" class="form-control" min="0" step="50000"
                                       value="<?= $_POST['deposit'] ?? '1500000' ?>" placeholder="1500000">
                                <small class="text-muted">Thường bằng 1-2 tháng tiền thuê</small>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="card mb-3">
                    <div class="card-header"><h5 class="mb-0">Địa chỉ</h5></div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Miền <span class="text-danger">*</span></label>
                                <select name="region_id" id="region" class="form-select" required>
                                    <option value="">-- Chọn miền --</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Tỉnh/Thành phố <span class="text-danger">*</span></label>
                                <select name="province_id" id="province" class="form-select" disabled required>
                                    <option value="">-- Chọn tỉnh/thành phố --</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Quận/Huyện <span class="text-danger">*</span></label>
                                <select name="district_id" id="district" class="form-select" disabled required>
                                    <option value="">-- Chọn quận/huyện --</option>
                                </select>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Địa chỉ chi tiết <span class="text-danger">*</span></label>
                            <input type="text" name="address" class="form-control" required
                                   placeholder="Số nhà, tên đường..."
                                   value="<?= htmlspecialchars($_POST['address'] ?? '') ?>">
                        </div>
                    </div>
                </div>
                
                <div class="card mb-3">
                    <div class="card-header"><h5 class="mb-0">Tiện ích</h5></div>
                    <div class="card-body">
                        <div class="row">
                            <?php
                            $allAmenities = [
                                'wifi' => 'Wifi', 'ac' => 'Điều hòa', 'wc_rieng' => 'WC riêng',
                                'bep' => 'Bếp', 'tu_lanh' => 'Tủ lạnh', 'may_giat' => 'Máy giặt',
                                'gac_lung' => 'Gác lửng', 'ban_cong' => 'Ban công', 'thang_may' => 'Thang máy'
                            ];
                            foreach ($allAmenities as $key => $label):
                            ?>
                                <div class="col-md-4 mb-2">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" 
                                               name="amenities[]" value="<?= $key ?>" id="am_<?= $key ?>">
                                        <label class="form-check-label" for="am_<?= $key ?>"><?= $label ?></label>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                
                <div class="card mb-3">
                    <div class="card-header"><h5 class="mb-0">Hình ảnh</h5></div>
                    <div class="card-body">
                        <input type="file" name="images[]" class="form-control" multiple accept="image/*">
                        <small class="text-muted">Tải lên tối đa 8 ảnh. Ảnh đầu tiên sẽ là ảnh đại diện.</small>
                    </div>
                </div>
            </div>
            
            <!-- Sidebar -->
            <div class="col-lg-4">
                
                <div class="card mb-3">
                    <div class="card-header"><h5 class="mb-0">Thông tin liên hệ</h5></div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">Tên liên hệ</label>
                            <input type="text" name="contact_name" class="form-control bg-light"
                                   value="<?= htmlspecialchars($user['full_name']) ?>" readonly>
                            <small class="text-muted"><i class="bi bi-info-circle me-1"></i>Lấy từ thông tin tài khoản</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Số điện thoại</label>
                            <input type="text" name="contact_phone" class="form-control bg-light"
                                   value="<?= htmlspecialchars($user['phone'] ?? '') ?>" readonly>
                            <small class="text-muted"><i class="bi bi-info-circle me-1"></i>Cập nhật trong <a href="<?= ADMIN_BASE_PATH ?>/modules/landlord/profile.php">Thông tin cá nhân</a></small>
                        </div>
                    </div>
                </div>
                
                <div class="card mb-3">
                    <div class="card-header bg-primary text-white"><h5 class="mb-0">Chọn gói đăng tin</h5></div>
                    <div class="card-body">
                        <?php 
                        mysqli_data_seek($packages, 0);
                        while ($pkg = mysqli_fetch_assoc($packages)): 
                        ?>
                            <div class="form-check mb-2 p-2 border rounded" 
                                 style="border-left: 4px solid <?= $pkg['highlight_color'] ?: '#ccc' ?> !important;">
                                <input class="form-check-input" type="radio" name="package_id" 
                                       value="<?= $pkg['package_id'] ?>" id="pkg_<?= $pkg['package_id'] ?>"
                                       data-price="<?= $pkg['price_per_day'] ?>"
                                       <?= $pkg['package_id'] == 4 ? 'checked' : '' ?>>
                                <label class="form-check-label w-100" for="pkg_<?= $pkg['package_id'] ?>">
                                    <strong style="color: <?= $pkg['highlight_color'] ?: '#333' ?>;">
                                        <?= htmlspecialchars($pkg['package_name']) ?>
                                    </strong>
                                    <br>
                                    <small><?= number_format((float)$pkg['price_per_day'], 0, ',', '.') ?>đ/ngày</small>
                                </label>
                            </div>
                        <?php endwhile; ?>
                        
                        <hr>
                        
                        <div class="mb-3">
                            <label class="form-label">Số ngày đăng</label>
                            <select name="days_posted" id="days_posted" class="form-select">
                                <option value="7">7 ngày</option>
                                <option value="15">15 ngày</option>
                                <option value="30" selected>30 ngày</option>
                                <option value="60">60 ngày</option>
                                <option value="90">90 ngày</option>
                            </select>
                        </div>
                        
                        <div class="bg-light p-3 rounded">
                            <div class="d-flex justify-content-between mb-2">
                                <span>Số dư hiện tại:</span>
                                <strong class="text-success"><?= number_format($balance, 0, ',', '.') ?>đ</strong>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span>Chi phí dự tính:</span>
                                <strong class="text-danger" id="estimated_cost">0đ</strong>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary w-100 py-3 mt-3">
                            <i class="bi bi-send me-2"></i>ĐĂNG TIN NGAY
                        </button>
                        
                    </div>
                </div>
                
            </div>
            
        </div>
        
        <!-- Fixed Submit Button at bottom for mobile -->
        <div class="d-lg-none position-fixed bottom-0 start-0 end-0 p-3 bg-white border-top" style="z-index: 1000;">
            <button type="submit" class="btn btn-primary w-100 py-2">
                <i class="bi bi-send me-2"></i>ĐĂNG TIN
            </button>
        </div>
    </form>
</section>

<script>
// ===== ROOM SELECTION & AUTO-FILL =====
let allBuildings = [];
let allRooms = [];

// Load buildings and rooms on page load
document.addEventListener('DOMContentLoaded', function() {
    fetch('<?= ADMIN_BASE_PATH ?>/api/get_rooms.php')
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                allBuildings = data.buildings;
                allRooms = data.rooms;
                
                // Populate building dropdown
                const buildingSelect = document.getElementById('select_building');
                allBuildings.forEach(b => {
                    buildingSelect.innerHTML += `<option value="${b.building_id}">${b.building_name} (${b.building_code})</option>`;
                });
            }
        })
        .catch(err => console.error('Error loading rooms:', err));
});

// When building changes, filter rooms
document.getElementById('select_building').addEventListener('change', function() {
    const buildingId = this.value;
    const roomSelect = document.getElementById('select_room');
    
    if (!buildingId) {
        roomSelect.innerHTML = '<option value="">-- Chọn dãy trọ trước --</option>';
        roomSelect.disabled = true;
        clearRoomSelection();
        return;
    }
    
    // Filter rooms by building
    const filteredRooms = allRooms.filter(r => r.building_id == buildingId);
    
    roomSelect.innerHTML = '<option value="">-- Chọn phòng --</option>';
    if (filteredRooms.length === 0) {
        roomSelect.innerHTML = '<option value="">Không có phòng trống</option>';
        roomSelect.disabled = true;
    } else {
        filteredRooms.forEach(r => {
            const isDaily = r.rental_type === 'DAILY';
            const price = isDaily ? r.daily_price : r.base_rent;
            const priceFormatted = new Intl.NumberFormat('vi-VN').format(price);
            const priceLabel = isDaily ? 'đ/ngày' : 'đ';
            roomSelect.innerHTML += `<option value="${r.room_id}">${r.room_code} - Tầng ${r.floor} - ${priceFormatted}${priceLabel}</option>`;
        });
        roomSelect.disabled = false;
    }
});

// When room changes, auto-fill form
document.getElementById('select_room').addEventListener('change', function() {
    const roomId = this.value;
    
    if (!roomId) {
        clearRoomSelection();
        return;
    }
    
    // Find room in allRooms
    const room = allRooms.find(r => r.room_id == roomId);
    if (!room) return;
    
    // Set hidden room_id
    document.getElementById('room_id').value = roomId;
    
    // Auto-fill form fields
    const isDaily = room.rental_type === 'DAILY';
    const roomPrice = isDaily ? (room.daily_price || 0) : (room.base_rent || 1500000);
    document.querySelector('[name="area"]').value = room.area || 20;
    document.querySelector('[name="max_occupants"]').value = room.max_occupants || 2;
    document.querySelector('[name="price"]').value = roomPrice;
    document.querySelector('[name="deposit"]').value = room.deposit || roomPrice;
    
    // Update price label
    const priceLabel = document.querySelector('label[for="price"], [name="price"]')?.closest('.mb-3')?.querySelector('.form-label');
    if (priceLabel) {
        priceLabel.innerHTML = isDaily ? 'Giá thuê/ngày (VNĐ) <span class="text-danger">*</span>' : 'Giá thuê/tháng (VNĐ) <span class="text-danger">*</span>';
    }
    
    // Auto-fill address from room's building using addressSelector
    if (room.region_id && room.province_id && room.district_id) {
        addressSelector.setValues(
            room.region_id,
            room.province_id,
            room.district_id
        );
        document.querySelector('[name="address"]').value = room.building_address || '';
    }
    
    // Auto-fill amenities checkboxes
    if (room.amenities && Array.isArray(room.amenities)) {
        // First uncheck all
        document.querySelectorAll('[name="amenities[]"]').forEach(cb => cb.checked = false);
        // Check matching amenities
        room.amenities.forEach(am => {
            const cb = document.querySelector(`[name="amenities[]"][value="${am}"]`);
            if (cb) cb.checked = true;
        });
    }
    
    // Show preview
    const preview = document.getElementById('room_preview');
    const previewText = document.getElementById('room_preview_text');
    preview.classList.remove('d-none');
    const priceFormatted = new Intl.NumberFormat('vi-VN').format(roomPrice);
    const priceUnit = isDaily ? 'đ/ngày' : 'đ/tháng';
    previewText.textContent = `${room.room_code} - ${room.building_name} - ${priceFormatted}${priceUnit}`;
    
    // Auto-generate title suggestion
    const titleField = document.querySelector('[name="title"]');
    if (!titleField.value) {
        titleField.value = `Cho thuê ${room.room_code} - ${room.building_name}, ${room.area}m², ${priceFormatted}${priceUnit}`;
    }
});

// Clear room selection
function clearRoomSelection() {
    document.getElementById('room_id').value = '';
    document.getElementById('room_preview').classList.add('d-none');
}

// ===== EXISTING SCRIPTS =====
// Load districts when province changes
document.getElementById('province_id').addEventListener('change', function() {
    const provinceId = this.value;
    const districtSelect = document.getElementById('district_id');
    districtSelect.innerHTML = '<option value="">Đang tải...</option>';
    
    if (provinceId) {
        fetch('<?= ADMIN_BASE_PATH ?>/api/districts.php?province_id=' + provinceId)
            .then(r => r.json())
            .then(data => {
                districtSelect.innerHTML = '<option value="">-- Chọn Quận/Huyện --</option>';
                data.forEach(d => {
                    districtSelect.innerHTML += `<option value="${d.district_id}">${d.district_name}</option>`;
                });
            });
    }
});

// Calculate estimated cost
function updateCost() {
    const packagePrice = document.querySelector('input[name="package_id"]:checked')?.dataset.price || 0;
    const days = document.getElementById('days_posted').value;
    const cost = packagePrice * days;
    document.getElementById('estimated_cost').textContent = new Intl.NumberFormat('vi-VN').format(cost) + 'đ';
}
document.querySelectorAll('input[name="package_id"]').forEach(r => r.addEventListener('change', updateCost));
document.getElementById('days_posted').addEventListener('change', updateCost);
updateCost();
</script>

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
