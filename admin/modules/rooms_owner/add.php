<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';

$role   = (string)($_SESSION['role_name'] ?? '');
$userId = (int)($_SESSION['user_id'] ?? 0);

if ($role !== 'LANDLORD') {
    admin_redirect('modules/dashboard/index.php', ['forbidden' => 1]);
}

$errors = [];
$buildingId = (int)($_GET['building_id'] ?? ($_POST['building_id'] ?? 0));

/** sticky values */
$roomName = '';
$floorNo = '';
$areaM2 = '';
$baseRent = '0';
$deposit = '0';
$maxOcc = '2';
$roomStatus = 'VACANT';
$note = '';
$desc = '';

/** buildings của chủ trọ */
$buildings = [];
$sqlB = "SELECT building_id, building_code, building_name
         FROM buildings
         WHERE owner_user_id = ?
         ORDER BY created_at DESC, building_id DESC";
$stmtB = mysqli_prepare($conn, $sqlB);
mysqli_stmt_bind_param($stmtB, "i", $userId);
mysqli_stmt_execute($stmtB);
$rsB = mysqli_stmt_get_result($stmtB);
while ($rsB && ($r = mysqli_fetch_assoc($rsB))) $buildings[] = $r;
mysqli_stmt_close($stmtB);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // “Tên phòng” lưu vào rooms.room_code
    $roomName = trim((string)($_POST['room_code'] ?? ''));

    $floorNo = (string)($_POST['floor_no'] ?? '');
    $areaM2  = (string)($_POST['area_m2'] ?? '');

    $baseRent = (string)($_POST['base_rent'] ?? '0');
    $deposit  = (string)($_POST['deposit_default'] ?? '0');
    $maxOcc   = (string)($_POST['max_occupants'] ?? '2');

    $roomStatus = (string)($_POST['room_status'] ?? 'VACANT');
    $note = trim((string)($_POST['note'] ?? ''));
    $desc = trim((string)($_POST['description'] ?? ''));

    $floorNoVal = ($floorNo !== '') ? (int)$floorNo : -1;        // -1 => NULL
    $areaM2Val  = ($areaM2 !== '') ? (float)$areaM2 : -1;        // -1 => NULL
    $baseRentVal = (float)$baseRent;
    $depositVal  = (float)$deposit;
    $maxOccVal   = (int)$maxOcc;

    if ($buildingId <= 0) $errors[] = 'Vui lòng chọn dãy/tòa.';
    if ($roomName === '') $errors[] = 'Vui lòng nhập tên phòng.';
    if ($baseRentVal < 0) $errors[] = 'Giá thuê không hợp lệ.';
    if ($depositVal < 0)  $errors[] = 'Tiền cọc không hợp lệ.';
    if ($maxOccVal <= 0)  $errors[] = 'Số người tối đa không hợp lệ.';

    // Check building thuộc chủ trọ
    if ($buildingId > 0) {
        $sqlCheck = "SELECT building_id
                     FROM buildings
                     WHERE building_id = ? AND owner_user_id = ?
                     LIMIT 1";
        $stmtC = mysqli_prepare($conn, $sqlCheck);
        mysqli_stmt_bind_param($stmtC, "ii", $buildingId, $userId);
        mysqli_stmt_execute($stmtC);
        $rsC = mysqli_stmt_get_result($stmtC);
        $ok = $rsC && mysqli_fetch_assoc($rsC);
        mysqli_stmt_close($stmtC);
        if (!$ok) $errors[] = 'Dãy/tòa không tồn tại hoặc bạn không có quyền.';
    }

    // Check trùng phòng trong cùng building (đúng theo unique index uq_rooms_building_roomcode)
    if (empty($errors) && $buildingId > 0 && $roomName !== '') {
        $sqlDup = "SELECT room_id
                   FROM rooms
                   WHERE building_id = ? AND room_code = ?
                   LIMIT 1";
        $stmtD = mysqli_prepare($conn, $sqlDup);
        mysqli_stmt_bind_param($stmtD, "is", $buildingId, $roomName);
        mysqli_stmt_execute($stmtD);
        $rsD = mysqli_stmt_get_result($stmtD);
        $dup = $rsD && mysqli_fetch_assoc($rsD);
        mysqli_stmt_close($stmtD);

        if ($dup) {
            $errors[] = "Tên phòng \"$roomName\" đã tồn tại trong dãy/tòa này. Vui lòng đổi tên (ví dụ: $roomName-A, $roomName-1).";
        }
    }

    // Upload image (optional)
    $imageName = null;
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

                $imageName = 'room_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                $dest = $dir . '/' . $imageName;

                if (!move_uploaded_file($tmp, $dest)) {
                    $errors[] = 'Không thể lưu ảnh upload.';
                    $imageName = null;
                }
            }
        } else {
            $errors[] = 'Upload ảnh bị lỗi.';
        }
    }

    if (empty($errors)) {
        $publishStatus = 'PENDING';

        $sql = "INSERT INTO rooms
                (building_id, type_id, room_code, floor_no, area_m2, base_rent, deposit_default,
                 max_occupants, room_status, publish_status, note, description, image)
                VALUES
                (?, NULL, ?, NULLIF(?, -1), NULLIF(?, -1), ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            $errors[] = 'Không thể prepare câu lệnh thêm phòng.';
        } else {
            $types = "isidddisssss"; // 12 ký tự cho 12 biến
            $params = [
                $buildingId,
                $roomName,
                $floorNoVal,
                $areaM2Val,
                $baseRentVal,
                $depositVal,
                $maxOccVal,
                $roomStatus,
                $publishStatus,
                $note,
                $desc,
                $imageName
            ];

            $bind = [];
            $bind[] = $types;
            foreach ($params as $k => $v) $bind[] = &$params[$k];
            call_user_func_array([$stmt, 'bind_param'], $bind);

            try {
                $ok = mysqli_stmt_execute($stmt);
            } catch (mysqli_sql_exception $e) {
                $ok = false;
                // 1062 = duplicate key
                if ((int)$e->getCode() === 1062) {
                    $errors[] = "Tên phòng \"$roomName\" đã tồn tại trong dãy/tòa này (trùng khóa uq_rooms_building_roomcode). Vui lòng đổi tên.";
                } else {
                    $errors[] = 'Lỗi SQL: ' . $e->getMessage();
                }
            }

            mysqli_stmt_close($stmt);

            if ($ok) {
                admin_redirect('modules/rooms_owner/index.php', ['building_id' => $buildingId, 'created' => 1]);
            } elseif (empty($errors)) {
                $errors[] = 'Không thể thêm phòng. Vui lòng kiểm tra lại dữ liệu.';
            }
        }
    }
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="pagetitle">
  <h1>Thêm phòng</h1>
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

        <div class="col-md-6">
          <label class="form-label">Dãy/Tòa</label>
          <select class="form-select" name="building_id" required>
            <option value="0">-- Chọn dãy/tòa --</option>
            <?php foreach ($buildings as $b): ?>
              <option value="<?= (int)$b['building_id'] ?>" <?= $buildingId === (int)$b['building_id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars(($b['building_code'] ?? '') . ' - ' . ($b['building_name'] ?? '')) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-md-6">
          <label class="form-label">Tên phòng</label>
          <input class="form-control" name="room_code" required value="<?= htmlspecialchars($roomName) ?>">
        </div>

        <div class="col-md-4">
          <label class="form-label">Tầng</label>
          <input class="form-control" name="floor_no" type="number" min="0" value="<?= htmlspecialchars($floorNo) ?>">
        </div>

        <div class="col-md-4">
          <label class="form-label">Diện tích (m²)</label>
          <input class="form-control" name="area_m2" type="number" step="0.01" min="0" value="<?= htmlspecialchars($areaM2) ?>">
        </div>

        <div class="col-md-4">
          <label class="form-label">Số người tối đa</label>
          <input class="form-control" name="max_occupants" type="number" min="1" value="<?= htmlspecialchars($maxOcc) ?>">
        </div>

        <div class="col-md-4">
          <label class="form-label">Giá thuê/tháng</label>
          <input class="form-control" name="base_rent" type="number" step="0.01" min="0" value="<?= htmlspecialchars($baseRent) ?>">
        </div>

        <div class="col-md-4">
          <label class="form-label">Tiền cọc</label>
          <input class="form-control" name="deposit_default" type="number" step="0.01" min="0" value="<?= htmlspecialchars($deposit) ?>">
        </div>

        <div class="col-md-4">
          <label class="form-label">Trạng thái phòng</label>
          <select class="form-select" name="room_status">
            <?php
              $opts = ['VACANT'=>'Trống','OCCUPIED'=>'Đang ở','MAINTENANCE'=>'Bảo trì','LOCKED'=>'Khóa'];
              foreach ($opts as $k=>$v) {
                $sel = ($roomStatus === $k) ? 'selected' : '';
                echo "<option value=\"{$k}\" {$sel}>{$v}</option>";
              }
            ?>
          </select>
        </div>

        <div class="col-md-12">
          <label class="form-label">Ảnh phòng</label>
          <input class="form-control" type="file" name="image" accept=".jpg,.jpeg,.png,.webp">
        </div>

        <div class="col-md-12">
          <label class="form-label">Ghi chú</label>
          <input class="form-control" name="note" value="<?= htmlspecialchars($note) ?>">
        </div>

        <div class="col-md-12">
          <label class="form-label">Mô tả</label>
          <textarea class="form-control" name="description" rows="4"><?= htmlspecialchars($desc) ?></textarea>
        </div>

        <div class="col-12 d-flex gap-2">
          <button class="btn btn-success" type="submit">Lưu</button>
          <a class="btn btn-secondary" href="index.php<?= $buildingId > 0 ? '?building_id='.$buildingId : '' ?>">Hủy</a>
        </div>

      </form>

    </div>
  </div>
</section>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
