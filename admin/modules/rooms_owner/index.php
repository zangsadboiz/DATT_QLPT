<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/status_vn.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$role = (string)($_SESSION['role_name'] ?? '');
$userId = (int)($_SESSION['user_id'] ?? 0);
if ($role !== 'LANDLORD' || $userId <= 0) {
    admin_redirect('modules/dashboard/index.php', ['forbidden' => 1]);
}

// Nếu ảnh phòng của bạn lưu chỗ khác, đổi base URL này:
$ROOM_IMG_BASE_URL = (defined('ADMIN_BASE_PATH') ? ADMIN_BASE_PATH : '/quanlyphongtro/admin') . '/uploads/rooms/';

$buildingId = (int)($_GET['building_id'] ?? 0);
$q = trim((string)($_GET['q'] ?? ''));

$buildings = [];
$stmtB = mysqli_prepare($conn, "SELECT building_id, building_code, building_name FROM buildings WHERE owner_user_id=? ORDER BY building_id DESC");
mysqli_stmt_bind_param($stmtB, "i", $userId);
mysqli_stmt_execute($stmtB);
$rsB = mysqli_stmt_get_result($stmtB);
while ($rsB && ($r = mysqli_fetch_assoc($rsB))) $buildings[] = $r;
mysqli_stmt_close($stmtB);

$sql = "
    SELECT r.room_id, r.room_code, r.image, r.floor_no, r.area_m2, r.base_rent, r.room_status, r.publish_status,
           b.building_code, b.building_name,
           si.max_until AS fee_until
    FROM rooms r
    JOIN buildings b ON b.building_id = r.building_id
    LEFT JOIN (
        SELECT room_id, MAX(active_until) AS max_until
        FROM service_invoices
        WHERE invoice_type='LISTING_FEE' AND status='PAID'
        GROUP BY room_id
    ) si ON si.room_id = r.room_id
    WHERE b.owner_user_id = ?
      AND (r.publish_status IS NULL OR r.publish_status <> 'HIDDEN')
";

$params = [$userId];
$types = "i";

if ($buildingId > 0) {
    $sql .= " AND r.building_id = ?";
    $types .= "i";
    $params[] = $buildingId;
}

if ($q !== '') {
    $like = "%{$q}%";
    $sql .= " AND (r.room_code LIKE ? OR b.building_code LIKE ? OR b.building_name LIKE ?)";
    $types .= "sss";
    $params[] = $like; $params[] = $like; $params[] = $like;
}

$sql .= " ORDER BY r.room_id DESC";

$stmt = mysqli_prepare($conn, $sql);
$bind = [];
$bind[] = $types;
foreach ($params as $k => $v) $bind[] = &$params[$k];
call_user_func_array([$stmt, 'bind_param'], $bind);

mysqli_stmt_execute($stmt);
$rs = mysqli_stmt_get_result($stmt);
$rows = [];
while ($rs && ($r = mysqli_fetch_assoc($rs))) $rows[] = $r;
mysqli_stmt_close($stmt);

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="pagetitle">
  <h1>Phòng của tôi</h1>
</div>

<section class="section">
  <div class="card">
    <div class="card-body pt-3">

      <div class="d-flex justify-content-between flex-wrap gap-2 mb-3">
        <form class="row g-2" method="get" style="max-width: 980px;">
          <div class="col-md-4">
            <input class="form-control" name="q" placeholder="Tìm theo tên phòng / dãy..." value="<?= htmlspecialchars($q) ?>">
          </div>
          <div class="col-md-4">
            <select class="form-select" name="building_id">
              <option value="0">-- Tất cả dãy/tòa --</option>
              <?php foreach ($buildings as $b): ?>
                <option value="<?= (int)$b['building_id'] ?>" <?= $buildingId === (int)$b['building_id'] ? 'selected' : '' ?>>
                  <?= htmlspecialchars(($b['building_code'] ?? '').' - '.($b['building_name'] ?? '')) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-4 d-flex gap-2">
            <button class="btn btn-primary" type="submit">Lọc</button>
            <a class="btn btn-secondary" href="index.php">Reset</a>
            <a class="btn btn-outline-secondary" href="hidden.php">Phòng đã ẩn</a>
          </div>
        </form>

        <div class="d-flex gap-2">
          <a class="btn btn-success" href="add.php">Thêm phòng</a>
        </div>
      </div>

      <div class="table-responsive">
        <table class="table table-hover align-middle">
          <thead>
            <tr>
              <th>#</th>
              <th>Ảnh</th>
              <th>Dãy/Tòa</th>
              <th>Tên phòng</th>
              <th>Tầng</th>
              <th>Giá</th>
              <th>Trạng thái phòng</th>
              <th>Duyệt</th>
              <th>Phí còn hạn tới</th>
              <th style="width:260px;">Thao tác</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($rows)): ?>
              <tr><td colspan="10" class="text-center text-muted">Chưa có phòng.</td></tr>
            <?php else: ?>
              <?php $now = date('Y-m-d H:i:s'); ?>
              <?php foreach ($rows as $i => $r): ?>
                <?php
                  $img = trim((string)($r['image'] ?? ''));
                  $imgUrl = $img !== '' ? ($ROOM_IMG_BASE_URL . rawurlencode($img)) : '';
                ?>
                <tr>
                  <td><?= $i+1 ?></td>
                  <td style="width:90px;">
                    <?php if ($imgUrl !== ''): ?>
                      <img src="<?= htmlspecialchars($imgUrl) ?>"
                           alt="room"
                           style="width:72px;height:52px;object-fit:cover;border-radius:8px;border:1px solid #ddd;">
                    <?php else: ?>
                      <span class="text-muted">-</span>
                    <?php endif; ?>
                  </td>
                  <td><?= htmlspecialchars((string)$r['building_code'].' - '.(string)$r['building_name']) ?></td>
                  <td><?= htmlspecialchars((string)$r['room_code']) ?></td>
                  <td><?= htmlspecialchars((string)$r['floor_no']) ?></td>
                  <td><?= number_format((float)$r['base_rent'], 0, ',', '.') ?> VND</td>
                  <td><?= badge_room_status((string)$r['room_status']) ?></td>
                  <td><?= badge_publish_status((string)$r['publish_status']) ?></td>
                  <td>
                    <?php
                      $until = (string)($r['fee_until'] ?? '');
                      if ($until !== '' && $until >= $now) echo htmlspecialchars($until);
                      else echo '<span class="text-muted">Chưa có/ hết hạn</span>';
                    ?>
                  </td>
                  <td class="d-flex gap-2 flex-wrap">
                    <a class="btn btn-sm btn-outline-secondary" href="detail.php?room_id=<?= (int)$r['room_id'] ?>">Chi tiết</a>
                    <a class="btn btn-sm btn-outline-primary" href="edit.php?room_id=<?= (int)$r['room_id'] ?>">Sửa</a>
                    <a class="btn btn-sm btn-outline-warning"
                       href="hide.php?room_id=<?= (int)$r['room_id'] ?>"
                       onclick="return confirm('Ẩn phòng này?');">
                      Ẩn
                    </a>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

    </div>
  </div>
</section>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
