<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/status_vn.php';

$role = (string)($_SESSION['role_name'] ?? '');
$userId = (int)($_SESSION['user_id'] ?? 0);
$isLandlord = ($role === 'LANDLORD');

$roomId = (int)($_GET['room_id'] ?? ($_GET['id'] ?? 0));
$buildingId = (int)($_GET['building_id'] ?? 0);

if ($roomId <= 0) {
    echo '<div class="alert alert-warning">Thiếu room_id. Vui lòng quay lại danh sách phòng.</div>';
    echo '<a class="btn btn-secondary" href="index.php' . ($buildingId ? '?building_id='.$buildingId : '') . '">Quay lại</a>';
    require_once __DIR__ . '/../../includes/footer.php';
    exit;
}

$sql = "SELECT r.*, b.building_name, b.owner_user_id
        FROM rooms r
        JOIN buildings b ON b.building_id = r.building_id
        WHERE r.room_id = ?
        LIMIT 1";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $roomId);
mysqli_stmt_execute($stmt);
$rs = mysqli_stmt_get_result($stmt);
$room = $rs ? mysqli_fetch_assoc($rs) : null;
mysqli_stmt_close($stmt);

if (!$room) {
    echo '<div class="alert alert-danger">Không tìm thấy phòng.</div>';
    echo '<a class="btn btn-secondary" href="index.php' . ($buildingId ? '?building_id='.$buildingId : '') . '">Quay lại</a>';
    require_once __DIR__ . '/../../includes/footer.php';
    exit;
}

if ($isLandlord && (int)$room['owner_user_id'] !== $userId) {
    echo '<div class="alert alert-danger">Bạn không có quyền xem phòng này.</div>';
    echo '<a class="btn btn-secondary" href="' . ADMIN_BASE_PATH . '/modules/toanha/index.php">Quay lại dãy/tòa</a>';
    require_once __DIR__ . '/../../includes/footer.php';
    exit;
}

$buildingId = (int)$room['building_id'];
?>

<div class="pagetitle">
  <h1>Chi tiết phòng - <?= htmlspecialchars((string)$room['room_code']) ?></h1>
  <nav>
    <ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="<?= ADMIN_BASE_PATH ?>/modules/toanha/index.php">Dãy / Tòa</a></li>
      <li class="breadcrumb-item"><a href="index.php?building_id=<?= $buildingId ?>">Phòng</a></li>
      <li class="breadcrumb-item active">Chi tiết</li>
    </ol>
  </nav>
</div>

<section class="section">
  <div class="card">
    <div class="card-body pt-3">

      <div class="mb-3 d-flex gap-2">
        <a class="btn btn-secondary" href="index.php?building_id=<?= $buildingId ?>">Quay lại</a>
        <a class="btn btn-warning" href="edit.php?room_id=<?= (int)$room['room_id'] ?>&building_id=<?= $buildingId ?>">Sửa</a>
      </div>

      <div class="row g-3">
        <div class="col-md-6">
          <div class="border rounded p-3">
            <div><strong>Dãy/Tòa:</strong> <?= htmlspecialchars((string)$room['building_name']) ?></div>
            <div><strong>Mã phòng:</strong> <?= htmlspecialchars((string)$room['room_code']) ?></div>
            <div><strong>Tầng:</strong> <?= htmlspecialchars((string)($room['floor'] ?? '')) ?></div>
            <div><strong>Giá:</strong> <?= number_format((float)($room['price'] ?? 0), 0, ',', '.') ?></div>
            <div><strong>Tình trạng:</strong> <?= badge_room_status((string)($room['room_status'] ?? '')) ?></div>
            <div><strong>Duyệt/Hiển thị:</strong> <?= badge_publish_status((string)($room['publish_status'] ?? '')) ?></div>
          </div>
        </div>

        <div class="col-md-6">
          <div class="border rounded p-3">
            <div><strong>Diện tích:</strong> <?= htmlspecialchars((string)($room['area'] ?? '')) ?></div>
            <div><strong>Sức chứa:</strong> <?= htmlspecialchars((string)($room['max_occupants'] ?? '')) ?></div>
            <div><strong>Tiền cọc:</strong> <?= number_format((float)($room['deposit'] ?? 0), 0, ',', '.') ?></div>
            <div><strong>Mô tả:</strong><br><?= nl2br(htmlspecialchars((string)($room['description'] ?? ''))) ?></div>
          </div>
        </div>
      </div>

    </div>
  </div>
</section>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
