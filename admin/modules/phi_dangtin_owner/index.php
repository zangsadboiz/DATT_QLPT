<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$role = (string)($_SESSION['role_name'] ?? '');
$userId = (int)($_SESSION['user_id'] ?? 0);

if ($role !== 'LANDLORD') {
    admin_redirect('modules/dashboard/index.php', ['forbidden'=>1]);
}

$sql = "
  SELECT si.*,
         r.room_code,
         b.building_code, b.building_name
  FROM service_invoices si
  JOIN rooms r ON r.room_id = si.room_id
  JOIN buildings b ON b.building_id = r.building_id
  WHERE si.invoice_type='LISTING_FEE'
    AND si.owner_user_id = ?
  ORDER BY si.svc_invoice_id DESC
";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $userId);
mysqli_stmt_execute($stmt);
$rs = mysqli_stmt_get_result($stmt);
$rows = [];
while ($rs && ($r = mysqli_fetch_assoc($rs))) $rows[] = $r;
mysqli_stmt_close($stmt);

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="pagetitle">
  <h1>Phí đăng tin</h1>
</div>

<section class="section">
  <div class="card">
    <div class="card-body pt-3">
      <div class="table-responsive">
        <table class="table table-hover align-middle">
          <thead>
            <tr>
              <th>#</th>
              <th>Phòng</th>
              <th>Dãy/Tòa</th>
              <th>Số tiền</th>
              <th>Nội dung CK</th>
              <th>Trạng thái</th>
              <th>Hiệu lực đến</th>
              <th style="width:140px;">Thao tác</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($rows)): ?>
              <tr><td colspan="8" class="text-center text-muted">Chưa có hóa đơn phí đăng tin.</td></tr>
            <?php else: ?>
              <?php foreach ($rows as $i=>$r): ?>
                <tr>
                  <td><?= $i+1 ?></td>
                  <td><?= htmlspecialchars((string)$r['room_code']) ?></td>
                  <td><?= htmlspecialchars((string)$r['building_code'].' - '.(string)$r['building_name']) ?></td>
                  <td><?= number_format((float)$r['amount'],0,',','.') ?> VND</td>
                  <td><code><?= htmlspecialchars((string)$r['add_info']) ?></code></td>
                  <td><?= htmlspecialchars((string)$r['status']) ?></td>
                  <td><?= htmlspecialchars((string)($r['active_until'] ?? '')) ?></td>
                  <td>
                    <a class="btn btn-sm btn-outline-secondary" href="view.php?id=<?= (int)$r['svc_invoice_id'] ?>">Xem/Thanh toán</a>
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
