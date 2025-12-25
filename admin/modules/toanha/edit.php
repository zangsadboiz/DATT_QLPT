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

// accept building_id hoặc id
$buildingId = (int)($_GET['building_id'] ?? ($_GET['id'] ?? ($_POST['building_id'] ?? 0)));
if ($buildingId <= 0) {
    admin_redirect('modules/toanha/index.php', ['err' => 'missing_building_id']);
}

// Load building + check ownership nếu landlord
$sql = "SELECT b.*, u.full_name AS owner_name
        FROM buildings b
        LEFT JOIN users u ON u.user_id = b.owner_user_id
        WHERE b.building_id = ?
        LIMIT 1";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $buildingId);
mysqli_stmt_execute($stmt);
$rs = mysqli_stmt_get_result($stmt);
$building = $rs ? mysqli_fetch_assoc($rs) : null;
mysqli_stmt_close($stmt);

if (!$building) {
    admin_redirect('modules/toanha/index.php', ['err' => 'building_not_found']);
}

if ($isLandlord && (int)$building['owner_user_id'] !== $userId) {
    admin_redirect('modules/toanha/index.php', ['err' => 'not_owner']);
}

$errors = [];

// sticky
$ownerUserId   = (int)$building['owner_user_id'];
$buildingCode  = (string)$building['building_code'];
$buildingName  = (string)$building['building_name'];
$address       = (string)($building['address'] ?? '');
$note          = (string)($building['note'] ?? '');
$description   = (string)($building['description'] ?? '');
$buildingStatus = (string)$building['building_status'];

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
        $ownerUserId = (int)($_POST['owner_user_id'] ?? $ownerUserId);
    } else {
        $ownerUserId = $userId;
    }

    $buildingCode = trim((string)($_POST['building_code'] ?? ''));
    $buildingName = trim((string)($_POST['building_name'] ?? ''));
    $address      = trim((string)($_POST['address'] ?? ''));
    $note         = trim((string)($_POST['note'] ?? ''));
    $description  = trim((string)($_POST['description'] ?? ''));

    if ($isAdmin) {
        $buildingStatus = (string)($_POST['building_status'] ?? $buildingStatus);
        if (!in_array($buildingStatus, ['PENDING','APPROVED','HIDDEN'], true)) $buildingStatus = (string)$building['building_status'];
    } else {
        // Chủ trọ sửa => đưa về PENDING để admin duyệt lại (tránh sửa xong vẫn APPROVED)
        $buildingStatus = 'PENDING';
    }

    $removeThumb = isset($_POST['remove_thumbnail']) && $_POST['remove_thumbnail'] === '1';

    if ($ownerUserId <= 0) $errors[] = 'Chủ trọ không hợp lệ.';
    if ($buildingCode === '') $errors[] = 'Vui lòng nhập mã dãy/tòa.';
    if ($buildingName === '') $errors[] = 'Vui lòng nhập tên dãy/tòa.';

    // Check trùng building_code (unique), loại trừ chính nó
    if (empty($errors) && $buildingCode !== '') {
        $sqlDup = "SELECT building_id
                   FROM buildings
                   WHERE building_code = ? AND building_id <> ?
                   LIMIT 1";
        $stmtD = mysqli_prepare($conn, $sqlDup);
        mysqli_stmt_bind_param($stmtD, "si", $buildingCode, $buildingId);
        mysqli_stmt_execute($stmtD);
        $rsD = mysqli_stmt_get_result($stmtD);
        $dup = $rsD && mysqli_fetch_assoc($rsD);
        mysqli_stmt_close($stmtD);
        if ($dup) $errors[] = "Mã dãy/tòa \"$buildingCode\" đã tồn tại. Vui lòng dùng mã khác.";
    }

    // Upload thumbnail (optional)
    $newThumb = null;
    if (empty($errors) && !$removeThumb && isset($_FILES['thumbnail']) && is_array($_FILES['thumbnail'])
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

                $newThumb = 'building_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                $dest = $dir . '/' . $newThumb;

                if (!move_uploaded_file($tmp, $dest)) {
                    $errors[] = 'Không thể lưu ảnh thumbnail.';
                    $newThumb = null;
                }
            }
        } else {
            $errors[] = 'Upload thumbnail bị lỗi.';
        }
    }

    if (empty($errors)) {
        // thumbnail xử lý bằng COALESCE + removeThumb
        $thumbParam = $newThumb; // null nếu không upload
        // nếu tick xóa ảnh => set về NULL
        $sqlU = "UPDATE buildings
                 SET owner_user_id = ?,
                     building_code = ?,
                     building_name = ?,
                     address = ?,
                     note = ?,
                     description = ?,
                     thumbnail = " . ($removeThumb ? "NULL" : "COALESCE(?, thumbnail)") . ",
                     building_status = ?
                 WHERE building_id = ?";

        $stmtU = mysqli_prepare($conn, $sqlU);

        if ($removeThumb) {
            mysqli_stmt_bind_param(
                $stmtU,
                "issssssi",
                $ownerUserId,
                $buildingCode,
                $buildingName,
                $address,
                $note,
                $description,
                $buildingStatus,
                $buildingId
            );
        } else {
            mysqli_stmt_bind_param(
                $stmtU,
                "isssssssi",
                $ownerUserId,
                $buildingCode,
                $buildingName,
                $address,
                $note,
                $description,
                $thumbParam,      // có thể NULL => giữ ảnh cũ
                $buildingStatus,
                $buildingId
            );
        }

        $ok = false;
        try {
            $ok = mysqli_stmt_execute($stmtU);
        } catch (mysqli_sql_exception $e) {
            if ((int)$e->getCode() === 1062) {
                $errors[] = "Mã dãy/tòa \"$buildingCode\" đã tồn tại (trùng unique building_code).";
            } else {
                $errors[] = 'Lỗi SQL: ' . $e->getMessage();
            }
        }
        mysqli_stmt_close($stmtU);

        if ($ok) {
            // xử lý xóa file cũ nếu cần
            $oldThumb = (string)($building['thumbnail'] ?? '');

            if ($removeThumb && $oldThumb !== '') {
                $oldPath = __DIR__ . '/../../uploads/buildings/' . $oldThumb;
                if (is_file($oldPath)) @unlink($oldPath);
            }
            if ($newThumb && $oldThumb !== '') {
                $oldPath = __DIR__ . '/../../uploads/buildings/' . $oldThumb;
                if (is_file($oldPath)) @unlink($oldPath);
            }

            admin_redirect('modules/toanha/index.php', ['updated' => 1]);
        }

        if (empty($errors)) $errors[] = 'Không thể cập nhật dãy/tòa.';
    }
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="pagetitle">
  <h1>Sửa dãy / tòa</h1>
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
        <input type="hidden" name="building_id" value="<?= (int)$buildingId ?>">

        <?php if ($isAdmin): ?>
          <div class="col-md-6">
            <label class="form-label">Chủ trọ</label>
            <select class="form-select" name="owner_user_id" required>
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
              Bạn đang sửa dãy/tòa của mình. Sau khi lưu, trạng thái sẽ về <strong>Chờ duyệt</strong>.
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
          <?php if (!empty($building['thumbnail'])): ?>
            <div class="form-text">
              Ảnh hiện tại: <strong><?= htmlspecialchars((string)$building['thumbnail']) ?></strong>
            </div>
            <div class="form-check mt-2">
              <input class="form-check-input" type="checkbox" name="remove_thumbnail" value="1" id="rmthumb">
              <label class="form-check-label" for="rmthumb">Xóa thumbnail hiện tại</label>
            </div>
          <?php endif; ?>
        </div>

        <div class="col-12 d-flex gap-2">
          <button class="btn btn-success" type="submit">Cập nhật</button>
          <a class="btn btn-secondary" href="index.php">Hủy</a>
        </div>

      </form>

    </div>
  </div>
</section>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
