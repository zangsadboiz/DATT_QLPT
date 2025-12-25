<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/alerts.php';
require_once __DIR__ . '/../../includes/form_helpers.php';

$role = (string)($_SESSION['role_name'] ?? '');
if (!in_array($role, ['ADMIN', 'STAFF'], true)) {
    admin_redirect('modules/dashboard/index.php', ['forbidden' => 1]);
}

$roomId = (int)($_GET['room_id'] ?? ($_POST['room_id'] ?? 0));
if ($roomId <= 0) {
    admin_redirect('modules/phong/index.php', ['error' => 'ID phòng không hợp lệ']);
}

// Load room data
$sql = "SELECT r.*, b.building_name
        FROM rooms r
        LEFT JOIN buildings b ON b.building_id = r.building_id
        WHERE r.room_id = ?
        LIMIT 1";
$stmt = mysqli_prepare($conn, $sql);
if (!$stmt) {
    die("SQL Prepare Error: " . htmlspecialchars(mysqli_error($conn)));
}
mysqli_stmt_bind_param($stmt, "i", $roomId);
mysqli_stmt_execute($stmt);
$rs = mysqli_stmt_get_result($stmt);
$room = $rs ? mysqli_fetch_assoc($rs) : null;
mysqli_stmt_close($stmt);

if (!$room) {
    admin_redirect('modules/phong/index.php', ['error' => 'Không tìm thấy phòng']);
}

$errors = [];
$buildingId = (int)($room['building_id'] ?? 0);
$roomCode = (string)($room['room_code'] ?? '');
$floorNo = (int)($room['floor_no'] ?? 0);
$areaM2 = (float)($room['area_m2'] ?? 0);
$baseRent = (float)($room['base_rent'] ?? 0);
$depositDefault = (float)($room['deposit_default'] ?? 0);
$maxOccupants = (int)($room['max_occupants'] ?? 1);
$roomStatus = (string)($room['room_status'] ?? 'VACANT');
$publishStatus = (string)($room['publish_status'] ?? 'PENDING');
$description = (string)($room['description'] ?? '');
$currentImage = (string)($room['image'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $buildingId = (int)($_POST['building_id'] ?? 0);
    $roomCode = trim((string)($_POST['room_code'] ?? ''));
    $floorNo = (int)($_POST['floor_no'] ?? 0);
    $areaM2 = (float)($_POST['area_m2'] ?? 0);
    $baseRent = (float)($_POST['base_rent'] ?? 0);
    $depositDefault = (float)($_POST['deposit_default'] ?? 0);
    $maxOccupants = (int)($_POST['max_occupants'] ?? 1);
    $roomStatus = (string)($_POST['room_status'] ?? 'VACANT');
    $publishStatus = (string)($_POST['publish_status'] ?? 'PENDING');
    $description = trim((string)($_POST['description'] ?? ''));

    // Validation
    if ($buildingId <= 0) $errors[] = 'Vui lòng chọn dãy/tòa.';
    if ($roomCode === '') $errors[] = 'Vui lòng nhập mã phòng.';
    if ($baseRent <= 0) $errors[] = 'Giá thuê phải lớn hơn 0.';

    // Check duplicate room_code (exclude current room)
    if (empty($errors) && $roomCode !== '') {
        $sqlDup = "SELECT room_id FROM rooms WHERE room_code = ? AND room_id != ? LIMIT 1";
        $stmtD = mysqli_prepare($conn, $sqlDup);
        mysqli_stmt_bind_param($stmtD, "si", $roomCode, $roomId);
        mysqli_stmt_execute($stmtD);
        $rsD = mysqli_stmt_get_result($stmtD);
        $dup = $rsD && mysqli_fetch_assoc($rsD);
        mysqli_stmt_close($stmtD);
        if ($dup) $errors[] = "Mã phòng \"$roomCode\" đã tồn tại.";
    }

    // Check if can change to MAINTENANCE (no active bookings)
    if (empty($errors) && $roomStatus === 'MAINTENANCE' && $room['room_status'] !== 'MAINTENANCE') {
        $sqlChk = "SELECT 1 FROM bookings WHERE room_id = ? AND status IN ('CONFIRMED','CHECKED_IN') LIMIT 1";
        $stmtC = mysqli_prepare($conn, $sqlChk);
        mysqli_stmt_bind_param($stmtC, "i", $roomId);
        mysqli_stmt_execute($stmtC);
        $rsC = mysqli_stmt_get_result($stmtC);
        $hasBooking = $rsC && mysqli_fetch_assoc($rsC);
        mysqli_stmt_close($stmtC);
        if ($hasBooking) {
            $errors[] = 'Không thể chuyển sang trạng thái bảo trì vì phòng đang có người đặt/thuê.';
        }
    }

    // Handle image upload
    $imageName = $currentImage;
    if (empty($errors) && isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $tmp = (string)$_FILES['image']['tmp_name'];
        $orig = (string)$_FILES['image']['name'];
        $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
        $allow = ['jpg','jpeg','png','webp'];

        if (!in_array($ext, $allow, true)) {
            $errors[] = 'Ảnh chỉ hỗ trợ jpg/jpeg/png/webp.';
        } else {
            $dir = __DIR__ . '/../../uploads/rooms';
            if (!is_dir($dir)) @mkdir($dir, 0777, true);

            $imageName = 'room_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            $dest = $dir . '/' . $imageName;

            if (!move_uploaded_file($tmp, $dest)) {
                $errors[] = 'Không thể lưu ảnh.';
                $imageName = $currentImage;
            }
        }
    }

    if (empty($errors)) {
        $sqlU = "UPDATE rooms SET
                    building_id = ?,
                    room_code = ?,
                    floor_no = ?,
                    area_m2 = ?,
                    base_rent = ?,
                    deposit_default = ?,
                    max_occupants = ?,
                    room_status = ?,
                    publish_status = ?,
                    description = ?,
                    image = ?
                 WHERE room_id = ?";
        $stmtU = mysqli_prepare($conn, $sqlU);
        mysqli_stmt_bind_param(
            $stmtU,
            "isiddiisssi",
            $buildingId,
            $roomCode,
            $floorNo,
            $areaM2,
            $baseRent,
            $depositDefault,
            $maxOccupants,
            $roomStatus,
            $publishStatus,
            $description,
            $imageName,
            $roomId
        );

        $ok = false;
        try {
            $ok = mysqli_stmt_execute($stmtU);
        } catch (mysqli_sql_exception $e) {
            if ((int)$e->getCode() === 1062) {
                $errors[] = "Mã phòng \"$roomCode\" đã tồn tại (trùng).";
            } else {
                $errors[] = 'Lỗi SQL: ' . $e->getMessage();
            }
        }
        mysqli_stmt_close($stmtU);

        if ($ok) {
            set_flash('success', 'Cập nhật phòng thành công!');
            admin_redirect('modules/phong/index.php');
        }
    }
}

// Get buildings
$buildings = [];
$rsB = mysqli_query($conn, "SELECT building_id, building_name FROM buildings WHERE deleted_at IS NULL ORDER BY building_name");
while ($rsB && ($b = mysqli_fetch_assoc($rsB))) $buildings[] = $b;

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="pagetitle">
  <h1><i class="bi bi-pencil-square me-2"></i>Sửa thông tin phòng</h1>
  <nav>
    <ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="<?= ADMIN_BASE_PATH ?>/modules/dashboard/index.php">Dashboard</a></li>
      <li class="breadcrumb-item"><a href="index.php">Phòng</a></li>
      <li class="breadcrumb-item active">Sửa</li>
    </ol>
  </nav>
</div>

<section class="section">
  <div class="card">
    <div class="card-header">
      <div class="d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Cập nhật: <?= htmlspecialchars($roomCode) ?></h5>
        <a href="index.php" class="btn btn-outline-secondary btn-sm">
          <i class="bi bi-arrow-left me-1"></i>Quay lại danh sách
        </a>
      </div>
    </div>
    <div class="card-body">

      <?php if (!empty($errors)): ?>
        <div class="alert alert-danger alert-dismissible fade show">
          <strong><i class="bi bi-exclamation-triangle me-2"></i>Có lỗi xảy ra:</strong>
          <ul class="mb-0 mt-2">
            <?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?>
          </ul>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php endif; ?>

      <form method="post" enctype="multipart/form-data" class="row g-3">
        <input type="hidden" name="room_id" value="<?= $roomId ?>">

        <!-- Thông tin cơ bản -->
        <div class="col-12">
          <h6 class="border-bottom pb-2 mb-3">
            <i class="bi bi-info-circle me-2"></i>Thông tin cơ bản
          </h6>
        </div>

        <div class="col-md-6">
          <label for="building_id" class="form-label required">Dãy/Tòa</label>
          <select class="form-select" id="building_id" name="building_id" required>
            <option value="">-- Chọn dãy/tòa --</option>
            <?php foreach ($buildings as $b): ?>
              <option value="<?= (int)$b['building_id'] ?>" 
                      <?= $buildingId === (int)$b['building_id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($b['building_name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-md-4">
          <label for="room_code" class="form-label required">Mã phòng</label>
          <input type="text" class="form-control" id="room_code" name="room_code" 
                 value="<?= htmlspecialchars($roomCode) ?>" required>
        </div>

        <div class="col-md-4">
          <label for="floor_no" class="form-label">Tầng</label>
          <input type="number" class="form-control" id="floor_no" name="floor_no" 
                 value="<?= $floorNo ?>" min="0">
        </div>

        <div class="col-md-4">
          <label for="area_m2" class="form-label">Diện tích (m²)</label>
          <input type="number" step="0.1" class="form-control" id="area_m2" name="area_m2" 
                 value="<?= $areaM2 ?>" min="0">
        </div>

        <!-- Giá cả -->
        <div class="col-12 mt-4">
          <h6 class="border-bottom pb-2 mb-3">
            <i class="bi bi-cash me-2"></i>Giá cả
          </h6>
        </div>

        <div class="col-md-4">
          <label for="base_rent" class="form-label required">Giá thuê/tháng (VNĐ)</label>
          <input type="number" class="form-control" id="base_rent" name="base_rent" 
                 value="<?= $baseRent ?>" min="0" required>
        </div>

        <div class="col-md-4">
          <label for="deposit_default" class="form-label">Tiền cọc (VNĐ)</label>
          <input type="number" class="form-control" id="deposit_default" name="deposit_default" 
                 value="<?= $depositDefault ?>" min="0">
        </div>

        <div class="col-md-4">
          <label for="max_occupants" class="form-label">Số người tối đa</label>
          <input type="number" class="form-control" id="max_occupants" name="max_occupants" 
                 value="<?= $maxOccupants ?>" min="1">
        </div>

        <!-- Trạng thái -->
        <div class="col-12 mt-4">
          <h6 class="border-bottom pb-2 mb-3">
            <i class="bi bi-gear me-2"></i>Trạng thái & Xuất bản
          </h6>
        </div>

        <div class="col-md-6">
          <label for="room_status" class="form-label required">Trạng thái phòng</label>
          <select class="form-select" id="room_status" name="room_status" required>
            <?php 
            $statuses = [
              'VACANT' => 'Trống',
              'OCCUPIED' => 'Đang thuê',
              'MAINTENANCE' => 'Bảo trì',
              'LOCKED' => 'Khóa'
            ];
            foreach ($statuses as $k => $v): ?>
              <option value="<?= $k ?>" <?= $roomStatus === $k ? 'selected' : '' ?>>
                <?= $v ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-md-6">
          <label for="publish_status" class="form-label required">Trạng thái duyệt</label>
          <select class="form-select" id="publish_status" name="publish_status" required>
            <?php 
            $pubStatuses = [
              'PENDING' => 'Chờ duyệt',
              'APPROVED' => 'Đã duyệt',
              'HIDDEN' => 'Ẩn'
            ];
            foreach ($pubStatuses as $k => $v): ?>
              <option value="<?= $k ?>" <?= $publishStatus === $k ? 'selected' : '' ?>>
                <?= $v ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <!-- Mô tả & Hình ảnh -->
        <div class="col-12 mt-4">
          <h6 class="border-bottom pb-2 mb-3">
            <i class="bi bi-image me-2"></i>Mô tả & Hình ảnh
          </h6>
        </div>

        <div class="col-12">
          <label for="description" class="form-label">Mô tả phòng</label>
          <textarea class="form-control" id="description" name="description" rows="4"><?= htmlspecialchars($description) ?></textarea>
        </div>

        <div class="col-12">
          <label for="image" class="form-label">Ảnh phòng</label>
          <?php if ($currentImage): ?>
            <div class="mb-2">
              <img src="/quanlyphongtro/admin/uploads/rooms/<?= htmlspecialchars($currentImage) ?>" 
                   class="img-preview" alt="Current image">
            </div>
          <?php endif; ?>
          <input type="file" class="form-control" id="image" name="image" 
                 accept=".jpg,.jpeg,.png,.webp" data-preview="imagePreview">
          <div class="form-text">Chọn file mới để thay thế ảnh hiện tại</div>
        </div>

        <!-- Actions -->
        <div class="col-12">
          <hr class="my-4">
          <div class="d-flex gap-2">
            <button type="submit" class="btn btn-success">
              <i class="bi bi-check-circle me-1"></i>Cập nhật
            </button>
            <a href="index.php" class="btn btn-secondary">
              <i class="bi bi-x-circle me-1"></i>Hủy
            </a>
          </div>
        </div>

      </form>

    </div>
  </div>
</section>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
