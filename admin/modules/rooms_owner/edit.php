<?php
declare(strict_types=1);

// Xử lý trước khi xuất HTML
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';

$role   = (string)($_SESSION['role_name'] ?? '');
$userId = (int)($_SESSION['user_id'] ?? 0);

if ($role !== 'LANDLORD') {
    admin_redirect('modules/dashboard/index.php', ['forbidden' => 1]);
}

$roomId = (int)($_GET['room_id'] ?? ($_POST['room_id'] ?? 0));
if ($roomId <= 0) {
    admin_redirect('modules/rooms_owner/index.php', ['err' => 'missing_room_id']);
}

/** Load room và check ownership */
$sql = "SELECT r.*, b.owner_user_id, b.building_name
        FROM rooms r
        JOIN buildings b ON b.building_id = r.building_id
        WHERE r.room_id = ?
          AND (r.deleted_at IS NULL OR r.deleted_at = '0000-00-00 00:00:00')
        LIMIT 1";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $roomId);
mysqli_stmt_execute($stmt);
$rs   = mysqli_stmt_get_result($stmt);
$room = $rs ? mysqli_fetch_assoc($rs) : null;
mysqli_stmt_close($stmt);

if (!$room) {
    admin_redirect('modules/rooms_owner/index.php', ['err' => 'room_not_found']);
}
if ((int)$room['owner_user_id'] !== $userId) {
    admin_redirect('modules/rooms_owner/index.php', ['err' => 'not_owner']);
}

$errors = [];
$buildingId = (int)$room['building_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // “Tên phòng” lưu vào rooms.room_code (vì DB không có room_name)
    $roomName = trim((string)($_POST['room_code'] ?? ''));

    $floorNo = ($_POST['floor_no'] ?? '') !== '' ? (int)$_POST['floor_no'] : -1;   // -1 => NULL
    $areaM2  = ($_POST['area_m2'] ?? '') !== '' ? (float)$_POST['area_m2'] : -1;   // -1 => NULL

    $baseRent = (float)($_POST['base_rent'] ?? 0);
    $deposit  = (float)($_POST['deposit_default'] ?? 0);
    $maxOcc   = (int)($_POST['max_occupants'] ?? 2);

    $roomStatus = (string)($_POST['room_status'] ?? 'VACANT');
    $note = trim((string)($_POST['note'] ?? ''));
    $desc = trim((string)($_POST['description'] ?? ''));

    if ($roomName === '') $errors[] = 'Vui lòng nhập tên phòng.';
    if ($baseRent < 0)   $errors[] = 'Giá thuê không hợp lệ.';
    if ($deposit < 0)    $errors[] = 'Tiền cọc không hợp lệ.';
    if ($maxOcc <= 0)    $errors[] = 'Số người tối đa không hợp lệ.';

    // Upload image (optional)
    $newImage = null;
    if (empty($errors) && isset($_FILES['image']) && is_array($_FILES['image'])
        && ($_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {

        if (($_FILES['image']['error'] ?? UPLOAD_ERR_OK) === UPLOAD_ERR_OK) {
            $tmp  = (string)$_FILES['image']['tmp_name'];
            $orig = (string)$_FILES['image']['name'];
            $ext  = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
            $allow = ['jpg','jpeg','png','webp'];

            if (!in_array($ext, $allow, true)) {
                $errors[] = 'Ảnh phòng chỉ hỗ trợ jpg/jpeg/png/webp.';
            } else {
                $dir = __DIR__ . '/../../uploads/rooms';
                if (!is_dir($dir)) @mkdir($dir, 0777, true);

                $newImage = 'room_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                $dest = $dir . '/' . $newImage;

                if (!move_uploaded_file($tmp, $dest)) {
                    $errors[] = 'Không thể lưu ảnh upload.';
                    $newImage = null;
                }
            }
        } else {
            $errors[] = 'Upload ảnh bị lỗi.';
        }
    }

    if (empty($errors)) {
        // Chủ trọ sửa => chờ admin duyệt lại
        $publishStatus = 'PENDING';

        // FIX: luôn có 12 placeholder, không còn mismatch bind_param
        // image = COALESCE(?, image): nếu ? là NULL => giữ ảnh cũ
        $sqlU = "UPDATE rooms
                 SET type_id = NULL,
                     room_code = ?,
                     floor_no  = NULLIF(?, -1),
                     area_m2   = NULLIF(?, -1),
                     base_rent = ?,
                     deposit_default = ?,
                     max_occupants = ?,
                     room_status = ?,
                     publish_status = ?,
                     note = ?,
                     description = ?,
                     image = COALESCE(?, image)
                 WHERE room_id = ?";

        $stmtU = mysqli_prepare($conn, $sqlU);

        // Rất quan trọng: nếu không upload ảnh mới thì truyền NULL (không truyền chuỗi rỗng)
        $imageParam = $newImage;
        if ($imageParam === '' || $imageParam === false) {
            $imageParam = null;
        }

        mysqli_stmt_bind_param(
            $stmtU,
            "sidddisssssi",
            $roomName,       // s
            $floorNo,        // i
            $areaM2,         // d
            $baseRent,       // d
            $deposit,        // d
            $maxOcc,         // i
            $roomStatus,     // s
            $publishStatus,  // s
            $note,           // s
            $desc,           // s
            $imageParam,     // s (NULL => giữ ảnh cũ)
            $roomId          // i
        );

        $ok = mysqli_stmt_execute($stmtU);
        mysqli_stmt_close($stmtU);

        if ($ok) {
            // Nếu có upload ảnh mới => xóa ảnh cũ sau khi update thành công
            if ($newImage && !empty($room['image'])) {
                $oldPath = __DIR__ . '/../../uploads/rooms/' . $room['image'];
                if (is_file($oldPath)) @unlink($oldPath);
            }
            admin_redirect('modules/rooms_owner/index.php', ['building_id' => $buildingId, 'updated' => 1]);
        }

        $errors[] = 'Không thể cập nhật phòng.';
    }
}

// Render HTML sau cùng
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="pagetitle">
  <h1>Sửa phòng: <?= htmlspecialchars((string)$room['room_code']) ?></h1>
</div>

<section class="section">
  <div class="card">
    <div class="card-body pt-3">

      <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
          <ul class="mb-0">
            <?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <form method="post" enctype="multipart/form-data" class="row g-3">
        <input type="hidden" name="room_id" value="<?= $roomId ?>">

        <div class="col-md-6">
          <label class="form-label">Dãy/Tòa</label>
          <input class="form-control" value="<?= htmlspecialchars((string)($room['building_name'] ?? '')) ?>" disabled>
        </div>

        <div class="col-md-6">
          <label class="form-label">Tên phòng</label>
          <input class="form-control" name="room_code" value="<?= htmlspecialchars((string)($room['room_code'] ?? '')) ?>" required>
          <div class="form-text">Tên phòng sẽ lưu vào cột <code>rooms.room_code</code>.</div>
        </div>

        <div class="col-md-4">
          <label class="form-label">Tầng</label>
          <input class="form-control" name="floor_no" type="number" min="0" value="<?= htmlspecialchars((string)($room['floor_no'] ?? '')) ?>">
        </div>

        <div class="col-md-4">
          <label class="form-label">Diện tích (m²)</label>
          <input class="form-control" name="area_m2" type="number" step="0.01" min="0" value="<?= htmlspecialchars((string)($room['area_m2'] ?? '')) ?>">
        </div>

        <div class="col-md-4">
          <label class="form-label">Số người tối đa</label>
          <input class="form-control" name="max_occupants" type="number" min="1" value="<?= htmlspecialchars((string)($room['max_occupants'] ?? '2')) ?>">
        </div>

        <div class="col-md-4">
          <label class="form-label">Giá thuê/tháng</label>
          <input class="form-control" name="base_rent" type="number" step="0.01" min="0" value="<?= htmlspecialchars((string)($room['base_rent'] ?? '0')) ?>">
        </div>

        <div class="col-md-4">
          <label class="form-label">Tiền cọc</label>
          <input class="form-control" name="deposit_default" type="number" step="0.01" min="0" value="<?= htmlspecialchars((string)($room['deposit_default'] ?? '0')) ?>">
        </div>

        <div class="col-md-4">
          <label class="form-label">Trạng thái phòng</label>
          <select class="form-select" name="room_status">
            <?php
            $opts = ['VACANT'=>'Trống','OCCUPIED'=>'Đang ở','MAINTENANCE'=>'Bảo trì','LOCKED'=>'Khóa'];
            foreach ($opts as $k=>$v) {
              $sel = ((string)$room['room_status'] === $k) ? 'selected' : '';
              echo "<option value=\"{$k}\" {$sel}>{$v}</option>";
            }
            ?>
          </select>
        </div>

        <div class="col-md-12">
          <label class="form-label">Ảnh phòng</label>
          <input class="form-control" type="file" name="image" accept=".jpg,.jpeg,.png,.webp">
          <?php if (!empty($room['image'])): ?>
            <div class="form-text">Ảnh hiện tại: <?= htmlspecialchars((string)$room['image']) ?></div>
          <?php endif; ?>
        </div>

        <div class="col-md-12">
          <label class="form-label">Ghi chú</label>
          <input class="form-control" name="note" value="<?= htmlspecialchars((string)($room['note'] ?? '')) ?>">
        </div>

        <div class="col-md-12">
          <label class="form-label">Mô tả</label>
          <textarea class="form-control" name="description" rows="4"><?= htmlspecialchars((string)($room['description'] ?? '')) ?></textarea>
        </div>

        <div class="col-12 d-flex gap-2">
          <button class="btn btn-success" type="submit">Cập nhật</button>
          <a class="btn btn-secondary" href="index.php?building_id=<?= $buildingId ?>">Hủy</a>
        </div>
      </form>

    </div>
  </div>
</section>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
