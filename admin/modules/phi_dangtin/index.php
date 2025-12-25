<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$role = (string)($_SESSION['role_name'] ?? '');
if (!in_array($role, ['ADMIN','STAFF'], true)) {
    admin_redirect('modules/dashboard/index.php', ['forbidden' => 1]);
}

$status = (string)($_GET['status'] ?? '');
$q = trim((string)($_GET['q'] ?? ''));

$sql = "
  SELECT si.*,
         r.room_code,
         b.building_code, b.building_name,
         u.full_name AS owner_name, u.username AS owner_username
  FROM service_invoices si
  JOIN rooms r ON r.room_id = si.room_id
  JOIN buildings b ON b.building_id = r.building_id
  JOIN users u ON u.user_id = si.owner_user_id
  WHERE si.invoice_type='LISTING_FEE'
";
$params = [];
$types = "";

if ($status !== '' && in_array($status, ['UNPAID','WAITING_CONFIRM','PAID','REJECTED','CANCELLED'], true)) {
    $sql .= " AND si.status = ?";
    $types .= "s";
    $params[] = $status;
}

if ($q !== '') {
    $like = "%{$q}%";
    $sql .= " AND (r.room_code LIKE ? OR b.building_code LIKE ? OR b.building_name LIKE ? OR u.full_name LIKE ? OR u.username LIKE ? OR si.add_info LIKE ?)";
    $types .= "ssssss";
    array_push($params, $like,$like,$like,$like,$like,$like);
}

$sql .= " ORDER BY si.svc_invoice_id DESC";

$stmt = mysqli_prepare($conn, $sql);
if ($types !== '') {
    $bind = [];
    $bind[] = $types;
    foreach ($params as $k => $v) $bind[] = &$params[$k];
    call_user_func_array([$stmt,'bind_param'], $bind);
}
mysqli_stmt_execute($stmt);
$rs = mysqli_stmt_get_result($stmt);
$rows = [];
while ($rs && ($r = mysqli_fetch_assoc($rs))) $rows[] = $r;
mysqli_stmt_close($stmt);

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="pagetitle">
  <h1>Phí đăng tin (Theo phòng duyệt)</h1>
</div>

<section class="section">
  <div class="card">
    <div class="card-body pt-3">

      <form class="row g-2 mb-3" method="get">
        <div class="col-md-4">
          <input class="form-control" name="q" placeholder="Tìm phòng / dãy / chủ trọ / nội dung CK..." value="<?= htmlspecialchars($q) ?>">
        </div>
        <div class="col-md-3">
          <select class="form-select" name="status">
            <option value="">-- Trạng thái --</option>
            <?php foreach (['UNPAID'=>'Chưa thanh toán','WAITING_CONFIRM'=>'Chờ xác nhận','PAID'=>'Đã thanh toán','REJECTED'=>'Từ chối','CANCELLED'=>'Hủy'] as $k=>$v): ?>
              <option value="<?= $k ?>" <?= $status===$k?'selected':'' ?>><?= $v ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-3 d-flex gap-2">
          <button class="btn btn-primary" type="submit">Lọc</button>
          <a class="btn btn-secondary" href="index.php">Reset</a>
        </div>
      </form>

      <div class="table-responsive">
        <table class="table table-hover align-middle">
          <thead>
            <tr>
              <th>#</th>
              <th>Phòng</th>
              <th>Dãy/Tòa</th>
              <th>Chủ trọ</th>
              <th>Số tiền</th>
              <th>Nội dung CK</th>
              <th>Trạng thái</th>
              <th>Hiệu lực đến</th>
              <th style="width:140px;">Thao tác</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($rows)): ?>
              <tr><td colspan="9" class="text-center text-muted">Chưa có dữ liệu.</td></tr>
            <?php else: ?>
              <?php foreach ($rows as $i=>$r): ?>
                <tr>
                  <td><?= $i+1 ?></td>
                  <td><?= htmlspecialchars((string)$r['room_code']) ?></td>
                  <td><?= htmlspecialchars((string)$r['building_code'].' - '.(string)$r['building_name']) ?></td>
                  <td><?= htmlspecialchars((string)$r['owner_name'].' ('.(string)$r['owner_username'].')') ?></td>
                  <td><?= number_format((float)$r['amount'], 0, ',', '.') ?> VND</td>
                  <td><code><?= htmlspecialchars((string)$r['add_info']) ?></code></td>
                  <td><?= htmlspecialchars((string)$r['status']) ?></td>
                  <td><?= htmlspecialchars((string)($r['active_until'] ?? '')) ?></td>
                  <td>
                    <a class="btn btn-sm btn-outline-secondary" href="view.php?id=<?= (int)$r['svc_invoice_id'] ?>">Xem</a>
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
