<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/status_vn.php';

$role = $_SESSION['role_name'] ?? '';
if ($role !== 'ADMIN') {
    header('Location: /quanlyphongtro/admin/index.php?error=no_permission');
    exit;
}

function hasColumn(mysqli $conn, string $table, string $col): bool {
    $t = mysqli_real_escape_string($conn, $table);
    $c = mysqli_real_escape_string($conn, $col);
    $rs = mysqli_query($conn, "
        SELECT COUNT(*) AS cnt
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = '$t'
          AND COLUMN_NAME = '$c'
    ");
    $row = $rs ? mysqli_fetch_assoc($rs) : null;
    return (int)($row['cnt'] ?? 0) > 0;
}

if (!hasColumn($conn, 'rooms', 'publish_status')) {
    die('Thiếu rooms.publish_status. Hãy chạy SQL thêm cột publish_status trước.');
}

function pickRoomImageUrl(?string $filename): string {
    if (!$filename) return '';
    $filename = basename($filename);

    $doc = rtrim($_SERVER['DOCUMENT_ROOT'], '/\\');
    $candidates = [
        ['/quanlyphongtro/admin/uploads/rooms/', '/quanlyphongtro/admin/uploads/rooms/'],
        ['/quanlyphongtro/admin/uploads/', '/quanlyphongtro/admin/uploads/'],
        ['/quanlyphongtro/uploads/', '/quanlyphongtro/uploads/'],
    ];

    foreach ($candidates as [$fsBase, $urlBase]) {
        $full = $doc . $fsBase . $filename;
        if (file_exists($full)) return $urlBase . $filename;
    }
    return '';
}

$qraw = trim($_GET['q'] ?? '');
$q = mysqli_real_escape_string($conn, $qraw);

$ps = $_GET['ps'] ?? '';
$allowedPS = ['', 'PENDING','APPROVED','HIDDEN'];
if (!in_array($ps, $allowedPS, true)) $ps = '';

$where = "r.deleted_at IS NULL";
if ($qraw !== '') {
    $where .= " AND (
      r.room_code LIKE '%$q%' OR
      b.building_name LIKE '%$q%' OR
      u.full_name LIKE '%$q%'
    )";
}
if ($ps !== '') {
    $where .= " AND r.publish_status = '$ps'";
}

/* ===== Pagination ===== */
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10;
$offset = ($page - 1) * $perPage;

$cntRs = mysqli_query($conn, "
    SELECT COUNT(*) AS c
    FROM rooms r
    JOIN buildings b ON b.building_id = r.building_id
    LEFT JOIN users u ON u.user_id = b.owner_user_id
    WHERE $where
");
$total = (int)(mysqli_fetch_assoc($cntRs)['c'] ?? 0);
$totalPages = max(1, (int)ceil($total / $perPage));
if ($page > $totalPages) { $page = $totalPages; $offset = ($page - 1) * $perPage; }

$list = mysqli_query($conn, "
  SELECT
    r.room_id, r.room_code, r.base_rent, r.room_status, r.publish_status, r.image,
    b.building_id, b.building_name, b.building_status,
    u.full_name AS owner_name
  FROM rooms r
  JOIN buildings b ON b.building_id = r.building_id
  LEFT JOIN users u ON u.user_id = b.owner_user_id
  WHERE $where
  ORDER BY (r.publish_status='PENDING') DESC, r.room_id DESC
  LIMIT $perPage OFFSET $offset
");

function buildQuery(array $extra = []): string {
    $params = $_GET;
    foreach ($extra as $k => $v) $params[$k] = $v;
    return http_build_query($params);
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="pagetitle d-flex justify-content-between align-items-center">
  <h1>Duyệt phòng</h1>
  <div class="text-muted">Tổng: <?= $total ?></div>
</div>

<form method="get" class="row g-3 mb-3">
  <div class="col-md-6">
    <input name="q" class="form-control" value="<?= htmlspecialchars($qraw) ?>"
           placeholder="Tìm mã phòng, dãy/tòa, chủ trọ...">
  </div>
  <div class="col-md-3">
    <select name="ps" class="form-select">
      <option value="" <?= $ps===''?'selected':'' ?>>Tất cả trạng thái</option>
      <option value="PENDING" <?= $ps==='PENDING'?'selected':'' ?>>Chờ duyệt</option>
      <option value="APPROVED" <?= $ps==='APPROVED'?'selected':'' ?>>Đã duyệt</option>
      <option value="HIDDEN" <?= $ps==='HIDDEN'?'selected':'' ?>>Đang ẩn</option>
    </select>
  </div>
  <div class="col-md-3">
    <button class="btn btn-primary w-100"><i class="bi bi-search"></i> Lọc</button>
  </div>
</form>

<section class="section">
  <div class="card">
    <div class="card-body">

      <table class="table table-bordered table-hover align-middle">
        <thead class="table-light">
          <tr>
            <th width="80">Ảnh</th>
            <th>#</th>
            <th>Dãy/Tòa</th>
            <th>Chủ trọ</th>
            <th>Phòng</th>
            <th>Giá</th>
            <th>Trạng thái phòng</th>
            <th>Duyệt</th>
            <th width="360">Hành động</th>
          </tr>
        </thead>
        <tbody>
        <?php $has=false; while($r = $list ? mysqli_fetch_assoc($list) : null): $has=true; ?>
          <?php
            $img = pickRoomImageUrl($r['image'] ?? '');
            $buildingApproved = (($r['building_status'] ?? '') === 'APPROVED');
          ?>
          <tr>
            <td>
              <?php if ($img): ?>
                <img src="<?= htmlspecialchars($img) ?>" class="img-thumbnail" style="width:64px;height:64px;object-fit:cover;">
              <?php else: ?>
                <div class="text-muted small">Không có</div>
              <?php endif; ?>
            </td>

            <td><?= (int)$r['room_id'] ?></td>
            <td>
              <?= htmlspecialchars($r['building_name'] ?? '-') ?>
              <div class="small text-muted">Dãy: <?= vn_building_status($r['building_status'] ?? '') ?></div>
            </td>
            <td><?= htmlspecialchars($r['owner_name'] ?? '-') ?></td>
            <td><?= htmlspecialchars($r['room_code'] ?? '-') ?></td>
            <td><?= number_format((float)($r['base_rent'] ?? 0)) ?></td>
            <td><?= badge_room_status($r['room_status'] ?? '') ?></td>
            <td><?= badge_publish_status($r['publish_status'] ?? '') ?></td>

            <td>
              <a class="btn btn-sm btn-outline-secondary"
                 href="status.php?id=<?= (int)$r['room_id'] ?>&s=PENDING">Chờ duyệt</a>

              <?php if ($buildingApproved): ?>
                <a class="btn btn-sm btn-outline-success"
                   href="status.php?id=<?= (int)$r['room_id'] ?>&s=APPROVED">Duyệt</a>
              <?php else: ?>
                <button class="btn btn-sm btn-outline-success" disabled title="Dãy/Tòa chưa được duyệt">Duyệt</button>
              <?php endif; ?>

              <a class="btn btn-sm btn-outline-dark"
                 href="status.php?id=<?= (int)$r['room_id'] ?>&s=HIDDEN">Ẩn</a>
            </td>
          </tr>
        <?php endwhile; ?>

        <?php if(!$has): ?>
          <tr><td colspan="9" class="text-center text-muted">Không có dữ liệu</td></tr>
        <?php endif; ?>
        </tbody>
      </table>

      <?php if ($totalPages > 1): ?>
        <?php
          $prev = max(1, $page-1);
          $next = min($totalPages, $page+1);
          $from = max(1, $page-2);
          $to   = min($totalPages, $page+2);
        ?>
        <nav>
          <ul class="pagination">
            <li class="page-item <?= $page<=1?'disabled':'' ?>">
              <a class="page-link" href="?<?= buildQuery(['page'=>$prev]) ?>">«</a>
            </li>
            <?php for($p=$from; $p<=$to; $p++): ?>
              <li class="page-item <?= $p===$page?'active':'' ?>">
                <a class="page-link" href="?<?= buildQuery(['page'=>$p]) ?>"><?= $p ?></a>
              </li>
            <?php endfor; ?>
            <li class="page-item <?= $page>=$totalPages?'disabled':'' ?>">
              <a class="page-link" href="?<?= buildQuery(['page'=>$next]) ?>">»</a>
            </li>
          </ul>
        </nav>
      <?php endif; ?>

    </div>
  </div>
</section>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
