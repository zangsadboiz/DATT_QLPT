<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';

$role = $_SESSION['role_name'] ?? '';
$user_id = (int)($_SESSION['user_id'] ?? 0);

if ($role !== 'LANDLORD' || $user_id <= 0) {
  header('Location: /quanlyphongtro/admin/index.php?error=no_permission');
  exit;
}

function vn_invoice_status(?string $s): string {
  return match($s) {
    'DRAFT'  => 'Nháp',
    'ISSUED' => 'Đã xuất',
    'PAID'   => 'Đã thanh toán',
    'VOID'   => 'Đã hủy',
    default  => 'Không rõ',
  };
}
function badge_invoice_status(?string $s): string {
  return match($s) {
    'PAID'   => '<span class="badge bg-success">Đã thanh toán</span>',
    'ISSUED' => '<span class="badge bg-primary">Đã xuất</span>',
    'DRAFT'  => '<span class="badge bg-warning text-dark">Nháp</span>',
    'VOID'   => '<span class="badge bg-secondary">Đã hủy</span>',
    default  => '<span class="badge bg-light text-dark">Không rõ</span>',
  };
}

$qraw = trim($_GET['q'] ?? '');
$q = mysqli_real_escape_string($conn, $qraw);

$st = $_GET['st'] ?? '';
$allowed = ['', 'DRAFT','ISSUED','PAID','VOID'];
if (!in_array($st, $allowed, true)) $st = '';

$mraw = trim($_GET['m'] ?? ''); // YYYY-MM
$monthDate = null;
if (preg_match('/^\d{4}-\d{2}$/', $mraw)) $monthDate = $mraw . '-01';

$where = "b.owner_id = $user_id";
if ($qraw !== '') {
  $where .= " AND (
    i.invoice_code LIKE '%$q%' OR
    c.contract_code LIKE '%$q%' OR
    r.room_code LIKE '%$q%' OR
    b.building_name LIKE '%$q%'
  )";
}
if ($st !== '') $where .= " AND i.invoice_status='$st'";
if ($monthDate) $where .= " AND i.invoice_month='$monthDate'";

/* Pagination */
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10;
$offset = ($page - 1) * $perPage;

$cntRs = mysqli_query($conn, "
  SELECT COUNT(*) AS c
  FROM invoices i
  JOIN contracts c ON c.contract_id=i.contract_id
  JOIN rooms r ON r.room_id=c.room_id
  JOIN buildings b ON b.building_id=r.building_id
  WHERE $where
");
$total = (int)(mysqli_fetch_assoc($cntRs)['c'] ?? 0);
$totalPages = max(1, (int)ceil($total / $perPage));
if ($page > $totalPages) { $page = $totalPages; $offset = ($page - 1) * $perPage; }

$list = mysqli_query($conn, "
  SELECT
    i.invoice_id, i.invoice_code, i.invoice_month, i.issue_date, i.due_date,
    i.total_amount, i.invoice_status, i.created_at,
    c.contract_code,
    r.room_code,
    b.building_name,
    COALESCE(p.paid,0) AS paid
  FROM invoices i
  JOIN contracts c ON c.contract_id=i.contract_id
  JOIN rooms r ON r.room_id=c.room_id
  JOIN buildings b ON b.building_id=r.building_id
  LEFT JOIN (
    SELECT invoice_id, SUM(amount) AS paid
    FROM payments
    GROUP BY invoice_id
  ) p ON p.invoice_id=i.invoice_id
  WHERE $where
  ORDER BY (i.invoice_status IN ('ISSUED','DRAFT')) DESC, i.invoice_month DESC, i.invoice_id DESC
  LIMIT $perPage OFFSET $offset
");

function buildQuery(array $extra=[]): string {
  $params = $_GET;
  foreach($extra as $k=>$v) $params[$k]=$v;
  return http_build_query($params);
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="pagetitle d-flex justify-content-between align-items-center">
  <h1>Hóa đơn</h1>
  <a class="btn btn-primary" href="add.php"><i class="bi bi-plus-lg"></i> Tạo hóa đơn</a>
</div>

<form method="get" class="row g-3 mb-3">
  <div class="col-md-5">
    <input name="q" class="form-control" value="<?= htmlspecialchars($qraw) ?>"
           placeholder="Tìm mã hóa đơn, mã HĐ, phòng, dãy...">
  </div>
  <div class="col-md-3">
    <input name="m" class="form-control" value="<?= htmlspecialchars($mraw) ?>" placeholder="Tháng (YYYY-MM)">
  </div>
  <div class="col-md-2">
    <select name="st" class="form-select">
      <option value="" <?= $st===''?'selected':'' ?>>Tất cả</option>
      <option value="ISSUED" <?= $st==='ISSUED'?'selected':'' ?>>Đã xuất</option>
      <option value="PAID" <?= $st==='PAID'?'selected':'' ?>>Đã thanh toán</option>
      <option value="DRAFT" <?= $st==='DRAFT'?'selected':'' ?>>Nháp</option>
      <option value="VOID" <?= $st==='VOID'?'selected':'' ?>>Đã hủy</option>
    </select>
  </div>
  <div class="col-md-2">
    <button class="btn btn-primary w-100"><i class="bi bi-search"></i> Lọc</button>
  </div>
</form>

<section class="section">
  <div class="card">
    <div class="card-body pt-3">

      <table class="table table-bordered table-hover align-middle">
        <thead class="table-light">
          <tr>
            <th>#</th>
            <th>Mã hóa đơn</th>
            <th>Tháng</th>
            <th>Dãy/Tòa</th>
            <th>Phòng</th>
            <th>Mã HĐ</th>
            <th>Tổng</th>
            <th>Đã thu</th>
            <th>Còn lại</th>
            <th>Trạng thái</th>
            <th width="260">Hành động</th>
          </tr>
        </thead>
        <tbody>
          <?php $has=false; while($r = $list ? mysqli_fetch_assoc($list) : null): $has=true; ?>
            <?php
              $totalAmount = (float)($r['total_amount'] ?? 0);
              $paid = (float)($r['paid'] ?? 0);
              $remain = max(0, $totalAmount - $paid);
            ?>
            <tr>
              <td><?= (int)$r['invoice_id'] ?></td>
              <td><?= htmlspecialchars($r['invoice_code']) ?></td>
              <td><?= htmlspecialchars($r['invoice_month']) ?></td>
              <td><?= htmlspecialchars($r['building_name'] ?? '-') ?></td>
              <td><?= htmlspecialchars($r['room_code'] ?? '-') ?></td>
              <td><?= htmlspecialchars($r['contract_code'] ?? '-') ?></td>
              <td><?= number_format($totalAmount) ?></td>
              <td><?= number_format($paid) ?></td>
              <td><?= number_format($remain) ?></td>
              <td><?= badge_invoice_status($r['invoice_status'] ?? '') ?></td>
              <td>
                <a class="btn btn-sm btn-outline-info" href="view.php?id=<?= (int)$r['invoice_id'] ?>">
                  <i class="bi bi-eye"></i> Xem
                </a>
                <a class="btn btn-sm btn-outline-secondary" href="print.php?id=<?= (int)$r['invoice_id'] ?>" target="_blank">
                  <i class="bi bi-printer"></i> In
                </a>
              </td>
            </tr>
          <?php endwhile; ?>

          <?php if(!$has): ?>
            <tr><td colspan="11" class="text-center text-muted">Không có dữ liệu</td></tr>
          <?php endif; ?>
        </tbody>
      </table>

      <?php if ($totalPages > 1): ?>
        <?php
          $prev=max(1,$page-1); $next=min($totalPages,$page+1);
          $from=max(1,$page-2); $to=min($totalPages,$page+2);
        ?>
        <nav>
          <ul class="pagination">
            <li class="page-item <?= $page<=1?'disabled':'' ?>">
              <a class="page-link" href="?<?= buildQuery(['page'=>$prev]) ?>">«</a>
            </li>
            <?php for($p=$from;$p<=$to;$p++): ?>
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
