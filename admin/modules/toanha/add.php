<?php
declare(strict_types=1);

// Xử lý trước khi xuất HTML
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/session.php';

$role   = (string)($_SESSION['role_name'] ?? '');
$userId = (int)($_SESSION['user_id'] ?? 0);

$isAdmin = in_array($role, ['ADMIN', 'STAFF'], true);
$isLandlord = ($role === 'LANDLORD');

if (!$isAdmin && !$isLandlord) {
    admin_redirect('modules/dashboard/index.php', ['forbidden' => 1]);
}

$errors = [];
// sticky values
$ownerUserId = $isLandlord ? $userId : 0;
$buildingCode = '';
$buildingName = '';
$address = '';
$note = '';
$description = '';
$buildingStatus = $isAdmin ? 'APPROVED' : 'PENDING';

$landlords = [];
if ($isAdmin) {
    $sqlL = "SELECT u.user_id, u.full_name, u.username
             FROM users u
             JOIN roles r ON r.role_id = u.role_id
             WHERE r.role_name = 'LANDLORD' AND u.is_active = 1
             ORDER BY u.full_name ASC, u.user_id DESC";
    $rsL = mysqli_query($conn, $sqlL);
    while ($rsL && ($r = mysqli_fetch_assoc($rsL))) $landlords[] = $r;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($isAdmin) {
        $ownerUserId = (int)($_POST['owner_user_id'] ?? 0);
    } else {
        $ownerUserId = $userId;
    }

    $buildingCode = trim((string)($_POST['building_code'] ?? ''));
    $buildingName = trim((string)($_POST['building_name'] ?? ''));
    $address      = trim((string)($_POST['address'] ?? ''));
    $note         = trim((string)($_POST['note'] ?? ''));
    $description  = trim((string)($_POST['description'] ?? ''));

    if ($isAdmin) {
        $buildingStatus = (string)($_POST['building_status'] ?? 'APPROVED');
        if (!in_array($buildingStatus, ['PENDING','APPROVED','HIDDEN'], true)) $buildingStatus = 'APPROVED';
    } else {
        $buildingStatus = 'PENDING';
    }

    if ($ownerUserId <= 0) $errors[] = 'Vui lòng chọn chủ trọ.';
    if ($buildingCode === '') $errors[] = 'Vui lòng nhập mã dãy/tòa (building_code).';
    if ($buildingName === '') $errors[] = 'Vui lòng nhập tên dãy/tòa (building_name).';

    // Check owner_user_id hợp lệ (chỉ khi admin chọn)
    if (empty($errors) && $isAdmin) {
        $sqlOC = "SELECT u.user_id
                  FROM users u
                  JOIN roles r ON r.role_id = u.role_id
                  WHERE u.user_id = ? AND r.role_name = 'LANDLORD'
                  LIMIT 1";
        $stmtOC = mysqli_prepare($conn, $sqlOC);
        mysqli_stmt_bind_param($stmtOC, "i", $ownerUserId);
        mysqli_stmt_execute($stmtOC);
        $rsOC = mysqli_stmt_get_result($stmtOC);
        $ok = $rsOC && mysqli_fetch_assoc($rsOC);
        mysqli_stmt_close($stmtOC);
        if (!$ok) $errors[] = 'Chủ trọ không hợp lệ.';
    }

    // Check trùng building_code (unique)
    if (empty($errors) && $buildingCode !== '') {
        $sqlDup = "SELECT building_id FROM buildings WHERE building_code = ? LIMIT 1";
        $stmtD = mysqli_prepare($conn, $sqlDup);
        mysqli_stmt_bind_param($stmtD, "s", $buildingCode);
        mysqli_stmt_execute($stmtD);
        $rsD = mysqli_stmt_get_result($stmtD);
        $dup = $rsD && mysqli_fetch_assoc($rsD);
        mysqli_stmt_close($stmtD);
        if ($dup) $errors[] = "Mã dãy/tòa \"$buildingCode\" đã tồn tại. Vui lòng dùng mã khác.";
    }

    // Upload thumbnail (optional)
    $thumbName = null;
    if (empty($errors) && isset($_FILES['thumbnail']) && is_array($_FILES['thumbnail'])
        && ($_FILES['thumbnail']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {

        if (($_FILES['thumbnail']['error'] ?? UPLOAD_ERR_OK) === UPLOAD_ERR_OK) {
            $tmp  = (string)$_FILES['thumbnail']['tmp_name'];
            $orig = (string)$_FILES['thumbnail']['name'];
            $ext  = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
            $allow = ['jpg','jpeg','png','webp'];

            if (!in_array($ext, $allow, true)) {
                $errors[] = 'Ảnh thumbnail chỉ hỗ trợ jpg/jpeg/png/webp.';
            } else {
                $dir = __DIR__ . '/../../uploads/buildings';
                if (!is_dir($dir)) @mkdir($dir, 0777, true);

                $thumbName = 'building_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                $dest = $dir . '/' . $thumbName;

                if (!move_uploaded_file($tmp, $dest)) {
                    $errors[] = 'Không thể lưu ảnh thumbnail.';
                    $thumbName = null;
                }
            }
        } else {
            $errors[] = 'Upload thumbnail bị lỗi.';
        }
    }

    if (empty($errors)) {
        $sql = "INSERT INTO buildings
                (owner_user_id, building_code, building_name, address, note, description, thumbnail, building_status)
                VALUES (?,?,?,?,?,?,?,?)";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param(
            $stmt,
            "isssssss",
            $ownerUserId,
            $buildingCode,
            $buildingName,
            $address,
            $note,
            $description,
            $thumbName,
            $buildingStatus
        );

        $ok = false;
        try {
            $ok = mysqli_stmt_execute($stmt);
        } catch (mysqli_sql_exception $e) {
            // 1062 duplicate
            if ((int)$e->getCode() === 1062) {
                $errors[] = "Mã dãy/tòa \"$buildingCode\" đã tồn tại (trùng unique building_code).";
            } else {
                $errors[] = 'Lỗi SQL: ' . $e->getMessage();
            }
        }
        mysqli_stmt_close($stmt);

        if ($ok) {
            admin_redirect('modules/toanha/index.php', ['created' => 1]);
        }
        if (empty($errors)) $errors[] = 'Không thể thêm dãy/tòa.';
    }
}

// Render HTML sau cùng
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="pagetitle">
  <h1><i class="bi bi-building-add me-2"></i>Thêm dãy / tòa mới</h1>
  <nav>
    <ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="<?= ADMIN_BASE_PATH ?>/modules/dashboard/index.php">Dashboard</a></li>
      <li class="breadcrumb-item"><a href="index.php">Dãy / Tòa</a></li>
      <li class="breadcrumb-item active">Thêm mới</li>
    </ol>
  </nav>
</div>

<section class="section">
  <div class="card">
    <div class="card-header">
      <div class="d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Thông tin dãy/tòa</h5>
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

        <?php if ($isAdmin): ?>
          <div class="col-md-6">
            <label class="form-label">Chủ trọ</label>
            <select class="form-select" name="owner_user_id" required>
              <option value="0">-- Chọn chủ trọ --</option>
              <?php foreach ($landlords as $l): ?>
                <option value="<?= (int)$l['user_id'] ?>" <?= $ownerUserId === (int)$l['user_id'] ? 'selected' : '' ?>>
                  <?= htmlspecialchars($l['full_name'] . ' (' . $l['username'] . ')') ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="col-md-6">
            <label class="form-label">Trạng thái</label>
            <select class="form-select" name="building_status">
              <?php foreach (['APPROVED'=>'Đã duyệt','PENDING'=>'Chờ duyệt','HIDDEN'=>'Ẩn'] as $k=>$v): ?>
                <option value="<?= $k ?>" <?= $buildingStatus === $k ? 'selected' : '' ?>><?= $v ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        <?php else: ?>
          <div class="col-md-12">
            <div class="alert alert-info mb-0">
              Dãy/tòa mới của bạn sẽ ở trạng thái <strong>Chờ duyệt</strong>.
            </div>
          </div>
        <?php endif; ?>

        <div class="col-md-6">
          <label class="form-label">Mã dãy/tòa</label>
          <input class="form-control" name="building_code" required value="<?= htmlspecialchars($buildingCode) ?>">
        </div>

        <div class="col-md-6">
          <label class="form-label">Tên dãy/tòa</label>
          <input class="form-control" name="building_name" required value="<?= htmlspecialchars($buildingName) ?>">
        </div>

        <div class="col-md-12">
          <label class="form-label">Địa chỉ</label>
          <input class="form-control" name="address" value="<?= htmlspecialchars($address) ?>">
        </div>

        <div class="col-md-12">
          <label class="form-label">Ghi chú</label>
          <input class="form-control" name="note" value="<?= htmlspecialchars($note) ?>">
        </div>

        <div class="col-md-12">
          <label class="form-label">Mô tả</label>
          <textarea class="form-control" name="description" rows="4"><?= htmlspecialchars($description) ?></textarea>
        </div>

        <div class="col-md-12">
          <label class="form-label">Thumbnail</label>
          <input class="form-control" type="file" name="thumbnail" accept=".jpg,.jpeg,.png,.webp">
          <div class="form-text">Ảnh lưu vào <code>admin/uploads/buildings</code>, tên file lưu trong <code>buildings.thumbnail</code>.</div>
        </div>

        <div class="col-12 d-flex gap-2">
          <button class="btn btn-success" type="submit">Lưu</button>
          <a class="btn btn-secondary" href="index.php">Hủy</a>
        </div>

      </form>

    </div>
  </div>
</section>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
