<?php
// admin/modules/hopdong/hopdong.php

// 1) Tuyệt đối KHÔNG include header.php trước khi redirect
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';

// 2) Check quyền trước khi xuất HTML
$role = $_SESSION['role_name'] ?? '';
if ($role !== 'ADMIN') {
    header('Location: /quanlyphongtro/admin/index.php?error=no_permission');
    exit;
}

// 3) Từ đây mới được include header để render HTML
require_once __DIR__ . '/../../includes/header.php';

$status = $_GET['status'] ?? 'ACTIVE';
$qraw   = trim($_GET['q'] ?? '');
$q      = mysqli_real_escape_string($conn, $qraw);

$limit  = 10;
$page   = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $limit;

$where = "1=1";
if (in_array($status, ['ACTIVE','ENDED','CANCELLED'], true)) {
    $where .= " AND c.contract_status = '$status'";
}

if ($qraw !== '') {
    $where .= " AND (
        c.contract_code LIKE '%$q%' OR
        r.room_code LIKE '%$q%' OR
        b.building_name LIKE '%$q%' OR
        ow.full_name LIKE '%$q%' OR
        t.full_name LIKE '%$q%'
    )";
}

$totalRow = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT COUNT(*) AS total
    FROM contracts c
    JOIN rooms r ON c.room_id = r.room_id
    JOIN buildings b ON r.building_id = b.building_id
    JOIN users ow ON ow.user_id = b.owner_user_id
    LEFT JOIN contract_tenants ct ON ct.contract_id = c.contract_id AND ct.is_representative = 1
    LEFT JOIN tenants t ON t.tenant_id = ct.tenant_id
    WHERE $where
"));
$total = (int)($totalRow['total'] ?? 0);
$totalPages = max(1, (int)ceil($total / $limit));

$rows = mysqli_query($conn, "
    SELECT
      c.contract_id, c.contract_code, c.start_date, c.end_date,
      c.rent_amount, c.deposit_amount, c.billing_day, c.contract_status,
      r.room_code,
      b.building_name,
      ow.full_name AS owner_name,
      t.full_name  AS representative_name
    FROM contracts c
    JOIN rooms r ON c.room_id = r.room_id
    JOIN buildings b ON r.building_id = b.building_id
    JOIN users ow ON ow.user_id = b.owner_user_id
    LEFT JOIN contract_tenants ct ON ct.contract_id = c.contract_id AND ct.is_representative = 1
    LEFT JOIN tenants t ON t.tenant_id = ct.tenant_id
    WHERE $where
    ORDER BY c.contract_id DESC
    LIMIT $limit OFFSET $offset
");
?>

<div class="pagetitle d-flex justify-content-between align-items-center">
  <h1>Hợp đồng</h1>
</div>

<form method="get" class="row g-3 mb-3">
  <div class="col-md-3">
    <label class="form-label">Trạng thái</label>
    <select name="status" class="form-select">
      <option value="ACTIVE" <?= $status==='ACTIVE'?'selected':'' ?>>Đang hiệu lực</option>
      <option value="ENDED" <?= $status==='ENDED'?'selected':'' ?>>Đã kết thúc</option>
      <option value="CANCELLED" <?= $status==='CANCELLED'?'selected':'' ?>>Đã hủy</option>
    </select>
  </div>

  <div class="col-md-6">
    <label class="form-label">Tìm kiếm</label>
    <input name="q" class="form-control"
           value="<?= htmlspecialchars($qraw) ?>"
           placeholder="Mã HĐ / Phòng / Dãy / Chủ trọ / Khách đại diện">
  </div>

  <div class="col-md-3 d-flex align-items-end">
    <button class="btn btn-primary w-100"><i class="bi bi-search"></i> Lọc</button>
  </div>
</form>

<section class="section">
  <div class="card">
    <div class="card-body">
      <table class="table table-bordered table-hover align-middle">
        <thead class="table-light">
          <tr>
            <th>#</th>
            <th>Mã HĐ</th>
            <th>Chủ trọ</th>
            <th>Tòa/Dãy</th>
            <th>Phòng</th>
            <th>Khách đại diện</th>
            <th>Bắt đầu</th>
            <th>Tiền thuê</th>
            <th>Trạng thái</th>
            <th width="170">Hành động</th>
          </tr>
        </thead>
        <tbody>
        <?php $has=false; while($r = $rows ? mysqli_fetch_assoc($rows) : null): $has=true; ?>
          <?php
            $badge = match($r['contract_status']) {
              'ACTIVE' => '<span class="badge bg-success">ACTIVE</span>',
              'ENDED' => '<span class="badge bg-secondary">ENDED</span>',
              'CANCELLED' => '<span class="badge bg-dark">CANCELLED</span>',
              default => '<span class="badge bg-light text-dark">?</span>'
            };
          ?>
          <tr>
            <td><?= (int)$r['contract_id'] ?></td>
            <td><?= htmlspecialchars($r['contract_code']) ?></td>
            <td><?= htmlspecialchars($r['owner_name'] ?? '-') ?></td>
            <td><?= htmlspecialchars($r['building_name']) ?></td>
            <td><?= htmlspecialchars($r['room_code']) ?></td>
            <td><?= htmlspecialchars($r['representative_name'] ?? '-') ?></td>
            <td><?= htmlspecialchars($r['start_date']) ?></td>
            <td><?= number_format((float)$r['rent_amount']) ?></td>
            <td><?= $badge ?></td>
            <td>
              <a class="btn btn-sm btn-outline-info" href="detail.php?id=<?= (int)$r['contract_id'] ?>">Chi tiết</a>
              <a class="btn btn-sm btn-outline-primary" target="_blank" href="print.php?id=<?= (int)$r['contract_id'] ?>">In</a>
            </td>
          </tr>
        <?php endwhile; ?>
        <?php if(!$has): ?>
          <tr><td colspan="10" class="text-center text-muted">Không có dữ liệu</td></tr>
        <?php endif; ?>
        </tbody>
      </table>

      <?php if ($totalPages > 1): ?>
      <nav>
        <ul class="pagination">
          <?php
            $prev = max(1, $page-1);
            $next = min($totalPages, $page+1);
          ?>
          <li class="page-item <?= $page<=1?'disabled':'' ?>">
            <a class="page-link" href="?page=<?= $prev ?>&status=<?= urlencode($status) ?>&q=<?= urlencode($qraw) ?>">«</a>
          </li>

          <?php for ($p=1; $p<=$totalPages; $p++): ?>
            <li class="page-item <?= $p==$page?'active':'' ?>">
              <a class="page-link" href="?page=<?= $p ?>&status=<?= urlencode($status) ?>&q=<?= urlencode($qraw) ?>"><?= $p ?></a>
            </li>
          <?php endfor; ?>

          <li class="page-item <?= $page>=$totalPages?'disabled':'' ?>">
            <a class="page-link" href="?page=<?= $next ?>&status=<?= urlencode($status) ?>&q=<?= urlencode($qraw) ?>">»</a>
          </li>
        </ul>
      </nav>
      <?php endif; ?>
    </div>
  </div>
</section>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
