<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';

// Tự động hủy các booking PENDING quá 30 phút
require_once __DIR__ . '/../../../includes/booking_helpers.php';
auto_cancel_expired_bookings($conn);

$role = $_SESSION['role_name'] ?? '';
$user_id = (int)($_SESSION['user_id'] ?? 0);

if ($role !== 'LANDLORD' || $user_id <= 0) {
    header('Location: /quanlyphongtro/admin/index.php?error=no_permission');
    exit;
}

$status = $_GET['status'] ?? '';
$allowed = ['','PENDING','DEPOSIT_PAID','CANCELLED'];
if (!in_array($status, $allowed, true)) $status = '';

$qraw = trim($_GET['q'] ?? '');
$q = mysqli_real_escape_string($conn, $qraw);

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10;
$offset = ($page - 1) * $perPage;

$where = "b.owner_id = $user_id";
if ($status !== '') {
    $where .= " AND bk.status = '$status'";
}
if ($qraw !== '') {
    $where .= " AND (
        bk.booking_code LIKE '%$q%' OR
        r.room_code LIKE '%$q%' OR
        b.building_name LIKE '%$q%' OR
        t.full_name LIKE '%$q%' OR
        t.phone LIKE '%$q%' OR
        t.id_number LIKE '%$q%'
    )";
}

$totalRs = mysqli_query($conn, "
    SELECT COUNT(*) AS c
    FROM bookings bk
    LEFT JOIN rooms r ON r.room_id = bk.room_id
    LEFT JOIN buildings b ON b.building_id = r.building_id
    LEFT JOIN tenants t ON t.tenant_id = bk.tenant_id
    WHERE b.owner_id = $user_id" . ($status !== '' ? " AND bk.status = '$status'" : "") . "
");
$total = (int)(mysqli_fetch_assoc($totalRs)['c'] ?? 0);
$totalPages = max(1, (int)ceil($total / $perPage));

$listSql = "
    SELECT
        bk.booking_id, bk.booking_code, bk.status, bk.created_at, bk.note,
        bk.check_in, bk.check_out, bk.contract_id,
        r.room_id, r.room_code,
        b.building_name,
        t.tenant_id, t.full_name AS tenant_name, t.phone AS tenant_phone
    FROM bookings bk
    LEFT JOIN rooms r ON r.room_id = bk.room_id
    LEFT JOIN buildings b ON b.building_id = r.building_id
    LEFT JOIN tenants t ON t.tenant_id = bk.tenant_id
    WHERE b.owner_id = $user_id" . ($status !== '' ? " AND bk.status = '$status'" : "") . "
    ORDER BY bk.booking_id DESC
    LIMIT $perPage OFFSET $offset
";
$list = mysqli_query($conn, $listSql);
$listError = mysqli_error($conn);


require_once __DIR__ . '/../../includes/header.php';
?>

<div class="pagetitle d-flex justify-content-between align-items-center">
  <h1>Yêu cầu thuê</h1>
  <div class="text-muted">Chỉ hiển thị yêu cầu thuộc phòng của bạn</div>
</div>

<form method="get" class="row g-3 mb-3">
  <div class="col-md-3">
    <label class="form-label">Trạng thái</label>
    <select name="status" class="form-select">
      <option value="" <?= $status===''?'selected':'' ?>>Tất cả</option>
      <option value="PENDING" <?= $status==='PENDING'?'selected':'' ?>>Chờ thanh toán</option>
      <option value="DEPOSIT_PAID" <?= $status==='DEPOSIT_PAID'?'selected':'' ?>>Đã thanh toán</option>
      <option value="CANCELLED" <?= $status==='CANCELLED'?'selected':'' ?>>Đã từ chối/hủy</option>
    </select>
  </div>

  <div class="col-md-6">
    <label class="form-label">Tìm kiếm</label>
    <input name="q" class="form-control" value="<?= htmlspecialchars($qraw) ?>"
           placeholder="Mã YC / phòng / dãy / tên / sđt / cccd">
  </div>

  <div class="col-md-3 d-flex align-items-end">
    <button class="btn btn-primary w-100"><i class="bi bi-search"></i> Lọc</button>
  </div>
</form>

<?php
// Thông báo
$msg = $_GET['msg'] ?? '';
$error = $_GET['error'] ?? '';
if ($msg === 'approved'): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <i class="bi bi-check-circle me-2"></i>Đã chấp nhận yêu cầu thuê thành công!
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php elseif ($msg === 'rejected'): ?>
    <div class="alert alert-warning alert-dismissible fade show">
        <i class="bi bi-x-circle me-2"></i>Đã từ chối yêu cầu thuê.
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php elseif ($error === 'conflict'): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <i class="bi bi-exclamation-triangle me-2"></i>Không thể duyệt! Phòng đã có người đặt/thuê trong khoảng thời gian này.
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php elseif ($error === 'maintenance'): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <i class="bi bi-tools me-2"></i>Không thể duyệt! Phòng đang bảo trì.
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php elseif ($error === 'not_found'): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <i class="bi bi-question-circle me-2"></i>Không tìm thấy yêu cầu hoặc không có quyền.
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php elseif ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <i class="bi bi-exclamation-triangle me-2"></i>Có lỗi xảy ra: <?= htmlspecialchars($error) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<section class="section">
  <div class="card">
    <div class="card-body">

      <table class="table table-bordered table-hover align-middle">
        <thead class="table-light">
          <tr>
            <th>#</th>
            <th>Mã YC</th>
            <th>Người thuê</th>
            <th>SĐT</th>
            <th>Dãy/Tòa</th>
            <th>Phòng</th>
            <th>Nhận</th>
            <th>Trả</th>
            <th>Trạng thái</th>
            <th>HĐ</th>
            <th width="260">Hành động</th>
          </tr>
        </thead>
        <tbody>
        <?php $has=false; while($r = $list ? mysqli_fetch_assoc($list) : null): $has=true; ?>
          <tr>
            <td><?= (int)$r['booking_id'] ?></td>
            <td><?= htmlspecialchars($r['booking_code'] ?? '-') ?></td>
            <td><?= htmlspecialchars($r['tenant_name'] ?? '-') ?></td>
            <td><?= htmlspecialchars($r['tenant_phone'] ?? '-') ?></td>
            <td><?= htmlspecialchars($r['building_name'] ?? '-') ?></td>
            <td><?= htmlspecialchars($r['room_code'] ?? '-') ?></td>
            <td><?= $r['check_in'] ?? '-' ?></td>
            <td><?= $r['check_out'] ?? '-' ?></td>
            <td>
              <?php 
              $rowStatus = $r['status'] ?? 'PENDING';
              $statusBadges = [
                  'PENDING' => '<span class="badge bg-warning">Chờ thanh toán</span>',
                  'DEPOSIT_PAID' => '<span class="badge bg-success">Đã thanh toán</span>',
                  'CHECKED_IN' => '<span class="badge bg-primary">Đang ở</span>',
                  'CHECKED_OUT' => '<span class="badge bg-secondary">Đã trả</span>',
                  'CANCELLED' => '<span class="badge bg-danger">Đã hủy</span>',
              ];
              echo $statusBadges[$rowStatus] ?? '<span class="badge bg-light text-dark">?</span>';
              ?>
            </td>
            <td>
              <?php if (!empty($r['contract_id'])): ?>
                <span class="badge bg-success">Có</span>
              <?php else: ?>
                <span class="badge bg-secondary">Chưa</span>
              <?php endif; ?>
            </td>
            <td>
              <a class="btn btn-sm btn-outline-info" href="detail.php?id=<?= (int)$r['booking_id'] ?>">Chi tiết</a>

              <?php if ($rowStatus === 'PENDING'): ?>
                <a class="btn btn-sm btn-success"
                   href="approve.php?id=<?= (int)$r['booking_id'] ?>"
                   onclick="return confirm('Chấp nhận yêu cầu này?');"><i class="bi bi-check"></i> Duyệt</a>

                <a class="btn btn-sm btn-danger"
                   href="reject.php?id=<?= (int)$r['booking_id'] ?>"
                   onclick="return confirm('Từ chối yêu cầu này?');"><i class="bi bi-x"></i> Từ chối</a>
              <?php endif; ?>

              <?php if ($rowStatus === 'DEPOSIT_PAID' && empty($r['contract_id'])): ?>
                <a class="btn btn-sm btn-primary"
                   href="create_contract.php?id=<?= (int)$r['booking_id'] ?>">Lập HĐ</a>
              <?php endif; ?>

              <?php if (!empty($r['contract_id'])): ?>
                <a class="btn btn-sm btn-outline-primary"
                   href="/quanlyphongtro/admin/modules/hopdong_owner/view.php?id=<?= (int)$r['contract_id'] ?>">Xem HĐ</a>
              <?php endif; ?>
            </td>
          </tr>
        <?php endwhile; ?>

        <?php if(!$has): ?>
          <tr><td colspan="11" class="text-center text-muted py-4">
            <i class="bi bi-inbox fs-1 d-block mb-2"></i>
            Không có yêu cầu nào <?= $status !== '' ? 'với trạng thái này' : '' ?>.
          </td></tr>
        <?php endif; ?>
        </tbody>
      </table>

      <?php if ($totalPages > 1): ?>
        <?php
          $base = '?status=' . urlencode($status) . '&q=' . urlencode($qraw) . '&page=';
          $prev = max(1, $page-1);
          $next = min($totalPages, $page+1);
        ?>
        <nav>
          <ul class="pagination">
            <li class="page-item <?= $page<=1?'disabled':'' ?>"><a class="page-link" href="<?= $base.$prev ?>">«</a></li>
            <?php for($p=max(1,$page-2); $p<=min($totalPages,$page+2); $p++): ?>
              <li class="page-item <?= $p===$page?'active':'' ?>"><a class="page-link" href="<?= $base.$p ?>"><?= $p ?></a></li>
            <?php endfor; ?>
            <li class="page-item <?= $page>=$totalPages?'disabled':'' ?>"><a class="page-link" href="<?= $base.$next ?>">»</a></li>
          </ul>
        </nav>
      <?php endif; ?>

    </div>
  </div>
</section>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
