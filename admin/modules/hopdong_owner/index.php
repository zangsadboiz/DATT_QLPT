<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/status_vn.php';

$role = $_SESSION['role_name'] ?? '';
$user_id = (int)($_SESSION['user_id'] ?? 0);

if ($role !== 'LANDLORD' || $user_id <= 0) {
    header('Location: /quanlyphongtro/admin/index.php?error=no_permission');
    exit;
}

function vn_contract_status(?string $s): string {
    return match($s) {
        'ACTIVE'    => 'Đang hiệu lực',
        'ENDED'     => 'Đã kết thúc',
        'CANCELLED' => 'Đã hủy',
        default     => 'Hết hiệu lực',
    };
}
function badge_contract_status(?string $s): string {
    return match($s) {
        'ACTIVE'    => '<span class="badge bg-success">'.vn_contract_status($s).'</span>',
        'ENDED'     => '<span class="badge bg-secondary">'.vn_contract_status($s).'</span>',
        'CANCELLED' => '<span class="badge bg-danger">'.vn_contract_status($s).'</span>',
        default     => '<span class="badge bg-light text-dark">'.vn_contract_status($s).'</span>',
    };
}

$qraw = trim($_GET['q'] ?? '');
$q = mysqli_real_escape_string($conn, $qraw);

$st = $_GET['st'] ?? '';
$allowedSt = ['', 'ACTIVE','ENDED','CANCELLED'];
if (!in_array($st, $allowedSt, true)) $st = '';

$where = "b.owner_id = $user_id";
if ($qraw !== '') {
    $where .= " AND (
        c.contract_code LIKE '%$q%' OR
        r.room_code LIKE '%$q%' OR
        b.building_name LIKE '%$q%' OR
        t.full_name LIKE '%$q%'
    )";
}
if ($st !== '') {
    $where .= " AND c.contract_status = '$st'";
}

/* ===== Pagination ===== */
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10;
$offset = ($page - 1) * $perPage;

$cntRs = mysqli_query($conn, "
    SELECT COUNT(DISTINCT c.contract_id) AS c
    FROM contracts c
    JOIN rooms r ON r.room_id = c.room_id
    JOIN buildings b ON b.building_id = r.building_id
    LEFT JOIN contract_tenants ct ON ct.contract_id = c.contract_id
    LEFT JOIN tenants t ON t.tenant_id = ct.tenant_id
    WHERE $where
");
$total = (int)(mysqli_fetch_assoc($cntRs)['c'] ?? 0);
$totalPages = max(1, (int)ceil($total / $perPage));
if ($page > $totalPages) { $page = $totalPages; $offset = ($page - 1) * $perPage; }

$list = mysqli_query($conn, "
    SELECT
      c.contract_id, c.contract_code, c.start_date, c.end_date, c.rent_amount, c.deposit_amount,
      c.billing_day, c.contract_status, c.created_at,
      r.room_code, r.room_status,
      b.building_name,
      GROUP_CONCAT(DISTINCT t.full_name ORDER BY ct.is_representative DESC SEPARATOR ', ') AS tenants
    FROM contracts c
    JOIN rooms r ON r.room_id = c.room_id
    JOIN buildings b ON b.building_id = r.building_id
    LEFT JOIN contract_tenants ct ON ct.contract_id = c.contract_id
    LEFT JOIN tenants t ON t.tenant_id = ct.tenant_id
    WHERE $where
    GROUP BY c.contract_id
    ORDER BY (c.contract_status='ACTIVE') DESC, c.created_at DESC
    LIMIT $perPage OFFSET $offset
");

function buildQuery(array $extra = []): string {
    $params = $_GET;
    foreach ($extra as $k => $v) $params[$k] = $v;
    return http_build_query($params);
}

require_once __DIR__ . '/../../includes/header.php';
?>


<div class="pagetitle">
  <h1><i class="bi bi-file-text me-2"></i>Hợp đồng</h1>
  <nav>
    <ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="<?= ADMIN_BASE_PATH ?>/modules/landlord/dashboard.php">Dashboard</a></li>
      <li class="breadcrumb-item active">Hợp đồng</li>
    </ol>
  </nav>
</div>

<section class="section">
  <!-- Filters in one row -->
  <div class="card mb-3">
    <div class="card-body py-2">
      <form method="get" class="d-flex gap-2 align-items-center">
        <input name="q" class="form-control form-control-sm" style="max-width: 300px;" value="<?= htmlspecialchars($qraw) ?>" placeholder="Tìm mã HĐ, phòng, dãy/tòa, người thuê...">
        
        <select name="st" class="form-select form-select-sm" style="width: 160px;">
          <option value="">Tất cả trạng thái</option>
          <option value="ACTIVE" <?= $st==='ACTIVE'?'selected':'' ?>>Đang hiệu lực</option>
          <option value="ENDED" <?= $st==='ENDED'?'selected':'' ?>>Đã kết thúc</option>
          <option value="CANCELLED" <?= $st==='CANCELLED'?'selected':'' ?>>Đã hủy</option>
        </select>
        
        <button type="submit" class="btn btn-sm btn-primary">
          <i class="bi bi-search"></i>
        </button>
        
        <?php if ($qraw !== '' || $st !== ''): ?>
        <a href="?" class="btn btn-sm btn-outline-secondary">
          <i class="bi bi-x"></i>
        </a>
        <?php endif; ?>
        
        <div class="ms-auto">
          <a class="btn btn-sm btn-success" href="add.php">
            <i class="bi bi-plus-circle"></i> Tạo hợp đồng
          </a>
        </div>
      </form>
    </div>
  </div>

<section class="section">
  <div class="card">
    <div class="card-body">

      <table class="table table-bordered table-hover align-middle">
        <thead class="table-light">
          <tr>
            <th>#</th>
            <th>Mã HĐ</th>
            <th>Dãy/Tòa</th>
            <th>Phòng</th>
            <th>Người thuê</th>
            <th>Ngày bắt đầu</th>
            <th>Giá</th>
            <th>Trạng thái</th>
            <th width="320">Hành động</th>
          </tr>
        </thead>
        <tbody>
        <?php $has=false; while($row = $list ? mysqli_fetch_assoc($list) : null): $has=true; ?>
          <tr>
            <td><?= (int)$row['contract_id'] ?></td>
            <td><?= htmlspecialchars($row['contract_code']) ?></td>
            <td><?= htmlspecialchars($row['building_name'] ?? '-') ?></td>
            <td><?= htmlspecialchars($row['room_code'] ?? '-') ?></td>
            <td><?= htmlspecialchars($row['tenants'] ?? '-') ?></td>
            <td><?= htmlspecialchars($row['start_date'] ?? '-') ?></td>
            <td><?= number_format((float)($row['rent_amount'] ?? 0)) ?></td>
            <td><?= badge_contract_status($row['contract_status'] ?? '') ?></td>
            <td>
              <a class="btn btn-sm btn-outline-info" href="view.php?id=<?= (int)$row['contract_id'] ?>">
                <i class="bi bi-eye"></i> Xem
              </a>
              <a class="btn btn-sm btn-outline-secondary" href="print.php?id=<?= (int)$row['contract_id'] ?>" target="_blank">
                <i class="bi bi-printer"></i> In
              </a>
              <?php if (($row['contract_status'] ?? '') === 'ACTIVE'): ?>
                <a class="btn btn-sm btn-outline-danger" href="end.php?id=<?= (int)$row['contract_id'] ?>">
                  <i class="bi bi-x-circle"></i> Kết thúc
                </a>
              <?php endif; ?>
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
