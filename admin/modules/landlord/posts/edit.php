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

$postId = (int)($_GET['id'] ?? 0);
if ($postId <= 0) {
    header('Location: ' . ADMIN_BASE_PATH . '/modules/landlord/posts/index.php');
    exit;
}

// Check ownership and get package info
$post = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT p.*, d.province_id, pk.price_per_day
    FROM posts p 
    JOIN districts d ON d.district_id = p.district_id
    JOIN packages pk ON pk.package_id = p.package_id
    WHERE p.post_id = $postId AND p.user_id = $userId
"));

if (!$post) {
    header('Location: ' . ADMIN_BASE_PATH . '/modules/landlord/posts/index.php?msg=not_found');
    exit;
}

// Get provinces, districts, packages
$provinces = mysqli_query($conn, "SELECT * FROM provinces ORDER BY province_name");
$districts = mysqli_query($conn, "SELECT * FROM districts WHERE province_id = {$post['province_id']} ORDER BY district_name");
$packages = mysqli_query($conn, "SELECT * FROM packages WHERE is_active = 1 ORDER BY priority DESC");

// Get current images
$images = mysqli_query($conn, "SELECT * FROM post_images WHERE post_id = $postId ORDER BY is_primary DESC, sort_order");

// Get user info for contact
$user = mysqli_fetch_assoc(mysqli_query($conn, "SELECT full_name, phone FROM users WHERE user_id = $userId"));

$errors = [];
$success = '';

// HANDLE DELETE IMAGE
if (isset($_GET['delete_image'])) {
    $imageId = (int)$_GET['delete_image'];
    $img = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM post_images WHERE image_id = $imageId AND post_id = $postId"));
    if ($img) {
        // Delete file
        $filePath = __DIR__ . '/../../../../uploads/posts/' . $img['image_path'];
        if (file_exists($filePath)) unlink($filePath);
        // Delete from DB
        mysqli_query($conn, "DELETE FROM post_images WHERE image_id = $imageId");
        // If was primary, set another as primary
        if ($img['is_primary']) {
            mysqli_query($conn, "UPDATE post_images SET is_primary = 1 WHERE post_id = $postId ORDER BY sort_order LIMIT 1");
        }
        header('Location: edit.php?id=' . $postId . '&msg=image_deleted');
        exit;
    }
}

// HANDLE UPLOAD NEW IMAGES
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_images'])) {
    if (!empty($_FILES['new_images']['name'][0])) {
        $uploadDir = __DIR__ . '/../../../../uploads/posts/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
        
        // Check if post already has images
        $existingCount = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as cnt FROM post_images WHERE post_id = $postId"))['cnt'];
        $isFirst = ($existingCount == 0);
        $uploaded = 0;
        
        foreach ($_FILES['new_images']['name'] as $i => $name) {
            if ($_FILES['new_images']['error'][$i] === UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) continue;
                
                $newName = $post['post_code'] . '_' . time() . '_' . ($i + 1) . '.' . $ext;
                $destPath = $uploadDir . $newName;
                
                if (move_uploaded_file($_FILES['new_images']['tmp_name'][$i], $destPath)) {
                    $isPrimary = $isFirst ? 1 : 0;
                    $isFirst = false;
                    mysqli_query($conn, "INSERT INTO post_images (post_id, image_path, is_primary, sort_order) VALUES ($postId, '$newName', $isPrimary, " . ($existingCount + $i) . ")");
                    $uploaded++;
                }
            }
        }
        
        // Redirect after upload
        header('Location: edit.php?id=' . $postId . '&msg=uploaded&count=' . $uploaded);
        exit;
    }
}

// HANDLE SET PRIMARY
if (isset($_GET['set_primary'])) {
    $imageId = (int)$_GET['set_primary'];
    mysqli_query($conn, "UPDATE post_images SET is_primary = 0 WHERE post_id = $postId");
    mysqli_query($conn, "UPDATE post_images SET is_primary = 1 WHERE image_id = $imageId AND post_id = $postId");
    header('Location: edit.php?id=' . $postId . '&msg=primary_set');
    exit;
}
$isResubmit = ($post['status'] === 'REJECTED');

// Process form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $postType = $_POST['post_type'] ?? 'ROOM';
    $area = (float)($_POST['area'] ?? 0);
    $price = (float)($_POST['price'] ?? 0);
    $deposit = (float)($_POST['deposit'] ?? 0);
    $maxOccupants = (int)($_POST['max_occupants'] ?? 2);
    $districtId = (int)($_POST['district_id'] ?? 0);
    $address = trim($_POST['address'] ?? '');
    $contactName = trim($_POST['contact_name'] ?? '');
    $contactPhone = trim($_POST['contact_phone'] ?? '');
    $amenities = $_POST['amenities'] ?? [];
    $resubmit = isset($_POST['resubmit']) && $_POST['resubmit'] === '1';
    
    if (empty($title)) $errors[] = 'Vui lòng nhập tiêu đề';
    if ($price <= 0) $errors[] = 'Vui lòng chọn giá thuê';
    if ($districtId <= 0) $errors[] = 'Vui lòng chọn Quận/Huyện';
    
    // Check balance if resubmitting rejected post
    $postingFee = 0;
    $currentBalance = 0;
    if ($resubmit && $isResubmit) {
        $postingFee = (float)$post['price_per_day'] * (int)$post['days_posted'];
        $userResult = mysqli_fetch_assoc(mysqli_query($conn, "SELECT balance FROM users WHERE user_id = $userId"));
        $currentBalance = (float)($userResult['balance'] ?? 0);
        
        if ($currentBalance < $postingFee) {
            $errors[] = "Số dư không đủ. Cần " . number_format($postingFee, 0, ',', '.') . "đ để đăng lại";
        }
    }
    
    if (empty($errors)) {
        $titleEsc = mysqli_real_escape_string($conn, $title);
        $descEsc = mysqli_real_escape_string($conn, $description);
        $addressEsc = mysqli_real_escape_string($conn, $address);
        $contactNameEsc = mysqli_real_escape_string($conn, $contactName);
        $contactPhoneEsc = mysqli_real_escape_string($conn, $contactPhone);
        $amenitiesJson = mysqli_real_escape_string($conn, json_encode($amenities));
        
        // If resubmitting rejected post - charge and change status
        if ($resubmit && $isResubmit) {
            $newBalance = $currentBalance - $postingFee;
            
            // Charge the landlord
            mysqli_query($conn, "UPDATE users SET balance = $newBalance WHERE user_id = $userId");
            
            // Create transaction (negative amount for deduction)
            mysqli_query($conn, "INSERT INTO transactions (user_id, post_id, transaction_type, amount, balance_before, balance_after, description, status, created_at)
                VALUES ($userId, $postId, 'POST_RESUBMIT', -$postingFee, $currentBalance, $newBalance, 'Đăng lại tin {$post['post_code']} sau khi chỉnh sửa', 'SUCCESS', NOW())");
            
            // Update post with PENDING status
            $sql = "UPDATE posts SET 
                title = '$titleEsc',
                description = '$descEsc',
                post_type = '$postType',
                area = $area,
                price = $price,
                deposit = $deposit,
                max_occupants = $maxOccupants,
                district_id = $districtId,
                address = '$addressEsc',
                amenities = '$amenitiesJson',
                contact_name = '$contactNameEsc',
                contact_phone = '$contactPhoneEsc',
                status = 'PENDING',
                rejection_reason = NULL,
                updated_at = NOW()
                WHERE post_id = $postId AND user_id = $userId";
        } else {
            // Normal update
            $sql = "UPDATE posts SET 
                title = '$titleEsc',
                description = '$descEsc',
                post_type = '$postType',
                area = $area,
                price = $price,
                deposit = $deposit,
                max_occupants = $maxOccupants,
                district_id = $districtId,
                address = '$addressEsc',
                amenities = '$amenitiesJson',
                contact_name = '$contactNameEsc',
                contact_phone = '$contactPhoneEsc',
                updated_at = NOW()
                WHERE post_id = $postId AND user_id = $userId";
        }
        
        if (mysqli_query($conn, $sql)) {
            $msg = ($resubmit && $isResubmit) ? 'resubmitted' : 'updated';
            header('Location: ' . ADMIN_BASE_PATH . '/modules/landlord/posts/index.php?msg=' . $msg);
            exit;
        } else {
            $errors[] = 'Lỗi: ' . mysqli_error($conn);
        }
    }
}

require_once __DIR__ . '/../../../includes/header.php';

$amenitiesList = json_decode($post['amenities'] ?: '[]', true) ?: [];
?>

<div class="pagetitle">
    <h1><i class="bi bi-pencil me-2"></i>Chỉnh sửa tin đăng</h1>
    <nav>
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= ADMIN_BASE_PATH ?>/modules/landlord/dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="<?= ADMIN_BASE_PATH ?>/modules/landlord/posts/index.php">Tin đăng</a></li>
            <li class="breadcrumb-item active">Chỉnh sửa</li>
        </ol>
    </nav>
</div>

<?php if ($isResubmit): ?>
    <?php $resubmitFee = (float)$post['price_per_day'] * (int)$post['days_posted']; ?>
    <div class="alert alert-warning">
        <h5><i class="bi bi-exclamation-triangle me-2"></i>Tin này đã bị từ chối</h5>
        <p class="mb-2"><strong>Lý do:</strong> <?= htmlspecialchars($post['rejection_reason'] ?? 'Không có lý do cụ thể') ?></p>
        <hr>
        <p class="mb-0">
            <i class="bi bi-info-circle me-1"></i>
            Sau khi chỉnh sửa, bạn có thể đăng lại tin này. 
            <strong>Phí đăng lại: <?= number_format($resubmitFee, 0, ',', '.') ?>đ</strong>
        </p>
    </div>
<?php endif; ?>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <ul class="mb-0">
            <?php foreach ($errors as $err): ?>
                <li><?= htmlspecialchars($err) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<?php if (isset($_GET['msg'])): ?>
    <?php if ($_GET['msg'] === 'uploaded'): ?>
        <div class="alert alert-success"><i class="bi bi-check-circle me-2"></i>Đã tải lên <?= (int)($_GET['count'] ?? 0) ?> ảnh thành công!</div>
    <?php elseif ($_GET['msg'] === 'image_deleted'): ?>
        <div class="alert alert-info"><i class="bi bi-trash me-2"></i>Đã xóa ảnh thành công!</div>
    <?php elseif ($_GET['msg'] === 'primary_set'): ?>
        <div class="alert alert-info"><i class="bi bi-star me-2"></i>Đã đặt ảnh chính thành công!</div>
    <?php endif; ?>
<?php endif; ?>

<section class="section">
    <form action="" method="POST" enctype="multipart/form-data">
        <div class="row">
            <div class="col-lg-8">
                
                <div class="card mb-3">
                    <div class="card-header"><h5 class="mb-0">Thông tin cơ bản</h5></div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">Loại tin</label>
                            <select name="post_type" class="form-select">
                                <option value="ROOM" <?= $post['post_type'] == 'ROOM' ? 'selected' : '' ?>>Phòng trọ</option>
                                <option value="APARTMENT" <?= $post['post_type'] == 'APARTMENT' ? 'selected' : '' ?>>Căn hộ</option>
                                <option value="HOUSE" <?= $post['post_type'] == 'HOUSE' ? 'selected' : '' ?>>Nhà nguyên căn</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Tiêu đề <span class="text-danger">*</span></label>
                            <input type="text" name="title" class="form-control" required
                                   value="<?= htmlspecialchars($post['title']) ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Mô tả</label>
                            <textarea name="description" class="form-control" rows="4"><?= htmlspecialchars($post['description'] ?? '') ?></textarea>
                        </div>
                    </div>
                </div>
                
                <div class="card mb-3">
                    <div class="card-header"><h5 class="mb-0">Thông tin phòng</h5></div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Diện tích (m²)</label>
                                <input type="number" name="area" class="form-control" step="0.1"
                                       value="<?= $post['area'] ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Số người tối đa</label>
                                <input type="number" name="max_occupants" class="form-control"
                                       value="<?= $post['max_occupants'] ?>">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Giá thuê/tháng (VNĐ) <span class="text-danger">*</span></label>
                                <input type="number" name="price" class="form-control" min="100000" step="50000" required
                                       value="<?= (int)$post['price'] ?>">
                                <small class="text-muted">VD: 1500000 = 1.5 triệu</small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Tiền cọc (VNĐ)</label>
                                <input type="number" name="deposit" class="form-control" min="0" step="50000"
                                       value="<?= (int)$post['deposit'] ?>">
                                <small class="text-muted">Thường bằng 1-2 tháng tiền thuê</small>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="card mb-3">
                    <div class="card-header"><h5 class="mb-0">Địa chỉ</h5></div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Tỉnh/TP</label>
                                <select name="province_id" id="province_id" class="form-select">
                                    <?php while ($prov = mysqli_fetch_assoc($provinces)): ?>
                                        <option value="<?= $prov['province_id'] ?>" 
                                                <?= $prov['province_id'] == $post['province_id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($prov['province_name']) ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Quận/Huyện</label>
                                <select name="district_id" id="district_id" class="form-select">
                                    <?php while ($dist = mysqli_fetch_assoc($districts)): ?>
                                        <option value="<?= $dist['district_id'] ?>"
                                                <?= $dist['district_id'] == $post['district_id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($dist['district_name']) ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Địa chỉ chi tiết</label>
                            <input type="text" name="address" class="form-control"
                                   value="<?= htmlspecialchars($post['address']) ?>">
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
                                               name="amenities[]" value="<?= $key ?>" id="am_<?= $key ?>"
                                               <?= in_array($key, $amenitiesList) ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="am_<?= $key ?>"><?= $label ?></label>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                
            </div>
            
            <div class="col-lg-4">
                <div class="card mb-3">
                    <div class="card-header"><h5 class="mb-0">Liên hệ</h5></div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">Tên liên hệ</label>
                            <input type="text" name="contact_name" class="form-control bg-light"
                                   value="<?= htmlspecialchars($user['full_name'] ?? '') ?>" readonly>
                            <small class="text-muted"><i class="bi bi-info-circle me-1"></i>Từ tài khoản</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Số điện thoại</label>
                            <input type="text" name="contact_phone" class="form-control bg-light"
                                   value="<?= htmlspecialchars($user['phone'] ?? '') ?>" readonly>
                            <small class="text-muted"><i class="bi bi-info-circle me-1"></i><a href="<?= ADMIN_BASE_PATH ?>/modules/landlord/profile.php">Cập nhật</a></small>
                        </div>
                    </div>
                </div>
                
                <div class="card mb-3">
                    <div class="card-body text-center">
                        <p class="mb-2"><strong>Mã tin:</strong> <?= htmlspecialchars($post['post_code']) ?></p>
                        <p class="mb-0">
                            <strong>Trạng thái:</strong>
                            <?php
                            $statusMap = [
                                'PENDING' => '<span class="badge bg-warning">Chờ duyệt</span>',
                                'APPROVED' => '<span class="badge bg-success">Đang hiển thị</span>',
                                'REJECTED' => '<span class="badge bg-danger">Bị từ chối</span>',
                                'EXPIRED' => '<span class="badge bg-secondary">Hết hạn</span>',
                            ];
                            echo $statusMap[$post['status']] ?? $post['status'];
                            ?>
                        </p>
                    </div>
                </div>
                
                <?php if ($isResubmit): ?>
                    <button type="submit" class="btn btn-primary w-100 py-2 mb-2">
                        <i class="bi bi-check-circle me-2"></i>LƯU THAY ĐỔI
                    </button>
                    <button type="submit" name="resubmit" value="1" class="btn btn-success w-100 py-2 mb-2"
                            onclick="return confirm('Xác nhận đăng lại tin? Bạn sẽ bị trừ <?= number_format($resubmitFee, 0, ',', '.') ?>đ')">
                        <i class="bi bi-arrow-repeat me-2"></i>LƯU & ĐĂNG LẠI
                    </button>
                <?php else: ?>
                    <button type="submit" class="btn btn-primary w-100 py-2 mb-2">
                        <i class="bi bi-check-circle me-2"></i>LƯU THAY ĐỔI
                    </button>
                <?php endif; ?>
                <a href="<?= ADMIN_BASE_PATH ?>/modules/landlord/posts/index.php" class="btn btn-outline-secondary w-100">
                    Hủy bỏ
                </a>
            </div>
        </div>
    </form>
</section>

<!-- SECTION QUẢN LÝ ẢNH - RIÊNG BIỆT -->
<section class="section pt-0">
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0"><i class="bi bi-images me-2"></i>Quản lý hình ảnh</h5>
        </div>
        <div class="card-body">
            <!-- Ảnh hiện tại -->
            <div class="row g-3 mb-4">
                <?php 
                mysqli_data_seek($images, 0);
                $hasImages = false;
                while ($img = mysqli_fetch_assoc($images)): 
                    $hasImages = true;
                    $imgPath = '/quanlyphongtro/uploads/posts/' . $img['image_path'];
                ?>
                    <div class="col-md-3 col-6">
                        <div class="position-relative border rounded overflow-hidden">
                            <img src="<?= htmlspecialchars($imgPath) ?>" alt="" class="img-fluid" style="height: 140px; width: 100%; object-fit: cover;">
                            <?php if ($img['is_primary']): ?>
                                <span class="position-absolute top-0 start-0 badge bg-primary m-1">Ảnh chính</span>
                            <?php endif; ?>
                            <div class="position-absolute bottom-0 start-0 end-0 p-2 bg-dark bg-opacity-75 text-center">
                                <?php if (!$img['is_primary']): ?>
                                    <a href="?id=<?= $postId ?>&set_primary=<?= $img['image_id'] ?>" class="btn btn-sm btn-warning py-0 px-2 me-1" title="Đặt làm ảnh chính">
                                        <i class="bi bi-star-fill"></i>
                                    </a>
                                <?php endif; ?>
                                <a href="?id=<?= $postId ?>&delete_image=<?= $img['image_id'] ?>" class="btn btn-sm btn-danger py-0 px-2" title="Xóa ảnh" onclick="return confirm('Xóa ảnh này?')">
                                    <i class="bi bi-trash-fill"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
                <?php if (!$hasImages): ?>
                    <div class="col-12">
                        <div class="alert alert-info mb-0">
                            <i class="bi bi-info-circle me-2"></i>Chưa có ảnh nào. Thêm ảnh để tin nổi bật hơn!
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Form upload ảnh mới -->
            <form action="" method="POST" enctype="multipart/form-data" class="border-top pt-3">
                <div class="row align-items-end">
                    <div class="col-md-8">
                        <label class="form-label"><i class="bi bi-upload me-1"></i>Thêm ảnh mới</label>
                        <input type="file" name="new_images[]" class="form-control" multiple accept="image/*">
                        <small class="text-muted">Có thể chọn nhiều ảnh cùng lúc. Định dạng: JPG, PNG, GIF, WebP</small>
                    </div>
                    <div class="col-md-4 mt-2 mt-md-0">
                        <button type="submit" name="upload_images" value="1" class="btn btn-primary w-100">
                            <i class="bi bi-cloud-upload me-1"></i>Tải ảnh lên
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</section>

<script>
document.getElementById('province_id').addEventListener('change', function() {
    const provinceId = this.value;
    fetch('<?= ADMIN_BASE_PATH ?>/api/districts.php?province_id=' + provinceId)
        .then(r => r.json())
        .then(data => {
            const sel = document.getElementById('district_id');
            sel.innerHTML = '';
            data.forEach(d => {
                sel.innerHTML += `<option value="${d.district_id}">${d.district_name}</option>`;
            });
        });
});
</script>

<?php require_once __DIR__ . '/../../../includes/footer.php'; ?>
