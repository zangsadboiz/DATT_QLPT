<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';

$role = (string)($_SESSION['role_name'] ?? '');
$userId = (int)($_SESSION['user_id'] ?? 0);

if ($role !== 'LANDLORD') {
    header('Location: ' . ADMIN_BASE_PATH . '/modules/dashboard/index.php');
    exit;
}

// Check if rental_payments table exists
$tableCheck = mysqli_query($conn, "SHOW TABLES LIKE 'rental_payments'");
$hasTable = mysqli_num_rows($tableCheck) > 0;

// Filters
$filterMonth = $_GET['month'] ?? date('Y-m');
$filterStatus = $_GET['status'] ?? '';
$filterRoom = (int)($_GET['room_id'] ?? 0);

// Get landlord's rooms
$rooms = mysqli_query($conn, "
    SELECT r.room_id, r.room_code, b.building_name
    FROM rooms r
    JOIN buildings b ON b.building_id = r.building_id
    WHERE b.owner_id = $userId AND r.deleted_at IS NULL
    ORDER BY b.building_name, r.room_code
");

if ($hasTable) {
    // Build query
    $where = "WHERE b.owner_id = $userId";
    if ($filterMonth !== '') {
        $where .= " AND DATE_FORMAT(rp.period_month, '%Y-%m') = '" . mysqli_real_escape_string($conn, $filterMonth) . "'";
    }
    if ($filterStatus !== '') {
        $where .= " AND rp.status = '" . mysqli_real_escape_string($conn, $filterStatus) . "'";
    }
    if ($filterRoom > 0) {
        $where .= " AND rp.room_id = $filterRoom";
    }

    // Get payments
    $payments = mysqli_query($conn, "
        SELECT rp.*, r.room_code, b.building_name, t.full_name as tenant_name
        FROM rental_payments rp
        JOIN rooms r ON r.room_id = rp.room_id
        JOIN buildings b ON b.building_id = r.building_id
        LEFT JOIN tenants t ON t.tenant_id = rp.tenant_id
        $where
        ORDER BY rp.period_month DESC, r.room_code
    ");

    // Get stats
    $stats = mysqli_fetch_assoc(mysqli_query($conn, "
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN rp.status = 'PENDING' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN rp.status = 'PAID' THEN 1 ELSE 0 END) as paid,
            SUM(CASE WHEN rp.status = 'OVERDUE' THEN 1 ELSE 0 END) as overdue,
            SUM(rp.total_amount) as total_amount,
            SUM(rp.paid_amount) as paid_sum
        FROM rental_payments rp
        JOIN rooms r ON r.room_id = rp.room_id
        JOIN buildings b ON b.building_id = r.building_id
        WHERE b.owner_id = $userId
          AND DATE_FORMAT(rp.period_month, '%Y-%m') = '" . mysqli_real_escape_string($conn, $filterMonth ?: date('Y-m')) . "'
    "));
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="pagetitle">
  <h1><i class="bi bi-receipt me-2"></i>Thanh toán hàng tháng</h1>
  <nav>
    <ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="<?= ADMIN_BASE_PATH ?>/modules/dashboard/index.php">Dashboard</a></li>
      <li class="breadcrumb-item active">Thanh toán</li>
    </ol>
  </nav>
</div>

<section class="section">

<?php if (!$hasTable): ?>
  <div class="alert alert-warning">
    <h5><i class="bi bi-exclamation-triangle me-2"></i>Chưa cài đặt database</h5>
    <p>Bảng <code>rental_payments</code> chưa tồn tại. Vui lòng chạy file SQL:</p>
    <pre class="bg-dark text-light p-3 rounded">c:\xampp\htdocs\quanlyphongtro\database_update_payments.sql</pre>
    <p>Hoặc import qua phpMyAdmin.</p>
  </div>
<?php else: ?>

<!-- Filter -->
<div class="card mb-4">
  <div class="card-body py-3">
    <form class="row g-2 align-items-end" method="get">
      <div class="col-md-2">
        <label class="form-label small mb-1">Kỳ thanh toán</label>
        <?php 
        $months = ['01'=>'Tháng 1','02'=>'Tháng 2','03'=>'Tháng 3','04'=>'Tháng 4','05'=>'Tháng 5','06'=>'Tháng 6',
                   '07'=>'Tháng 7','08'=>'Tháng 8','09'=>'Tháng 9','10'=>'Tháng 10','11'=>'Tháng 11','12'=>'Tháng 12'];
        $curYear = date('Y');
        $curMonth = substr($filterMonth, 5, 2) ?: date('m');
        $selYear = substr($filterMonth, 0, 4) ?: $curYear;
        ?>
        <div class="d-flex gap-1">
          <select class="form-select form-select-sm" name="month" style="width:auto">
            <?php foreach ($months as $m => $label): ?>
              <option value="<?= $selYear ?>-<?= $m ?>" <?= $curMonth === $m ? 'selected' : '' ?>><?= $label ?></option>
            <?php endforeach; ?>
          </select>
          <select class="form-select form-select-sm" name="year" style="width:80px" onchange="this.form.month.value=this.value+'-'+this.form.month.value.substr(5)">
            <?php for ($y = $curYear; $y >= $curYear - 2; $y--): ?>
              <option value="<?= $y ?>" <?= $selYear == $y ? 'selected' : '' ?>><?= $y ?></option>
            <?php endfor; ?>
          </select>
        </div>
      </div>
      <div class="col-md-2">
        <label class="form-label small mb-1">Trạng thái</label>
        <select class="form-select form-select-sm" name="status">
          <option value="">Tất cả</option>
          <option value="PENDING" <?= $filterStatus === 'PENDING' ? 'selected' : '' ?>>Chờ thanh toán</option>
          <option value="PAID" <?= $filterStatus === 'PAID' ? 'selected' : '' ?>>Đã thanh toán</option>
          <option value="OVERDUE" <?= $filterStatus === 'OVERDUE' ? 'selected' : '' ?>>Quá hạn</option>
        </select>
      </div>
      <div class="col-md-3">
        <label class="form-label small mb-1">Phòng</label>
        <select class="form-select form-select-sm" name="room_id">
          <option value="">Tất cả phòng</option>
          <?php mysqli_data_seek($rooms, 0); while ($r = mysqli_fetch_assoc($rooms)): ?>
            <option value="<?= $r['room_id'] ?>" <?= $filterRoom == $r['room_id'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($r['building_name'] . ' - ' . $r['room_code']) ?>
            </option>
          <?php endwhile; ?>
        </select>
      </div>
      <div class="col-md-2">
        <button class="btn btn-primary btn-sm w-100" type="submit"><i class="bi bi-funnel me-1"></i>Lọc</button>
      </div>
      <div class="col-md-3 text-end">
        <a href="create.php" class="btn btn-success btn-sm"><i class="bi bi-plus-circle me-1"></i>Tạo phiếu thu</a>
      </div>
    </form>
  </div>
</div>

<!-- Stats -->
<div class="row mb-4">
  <div class="col">
    <div class="card border-0 shadow-sm text-center py-3">
      <div class="fs-3 fw-bold text-primary"><?= (int)($stats['total'] ?? 0) ?></div>
      <div class="text-muted small">Tổng phiếu</div>
    </div>
  </div>
  <div class="col">
    <div class="card border-0 shadow-sm text-center py-3">
      <div class="fs-3 fw-bold text-warning"><?= (int)($stats['pending'] ?? 0) ?></div>
      <div class="text-muted small">Chờ thanh toán</div>
    </div>
  </div>
  <div class="col">
    <div class="card border-0 shadow-sm text-center py-3">
      <div class="fs-3 fw-bold text-success"><?= (int)($stats['paid'] ?? 0) ?></div>
      <div class="text-muted small">Đã thanh toán</div>
    </div>
  </div>
  <div class="col">
    <div class="card border-0 shadow-sm text-center py-3">
      <div class="fs-3 fw-bold text-danger"><?= (int)($stats['overdue'] ?? 0) ?></div>
      <div class="text-muted small">Quá hạn</div>
    </div>
  </div>
  <div class="col">
    <div class="card border-0 shadow-sm text-center py-3">
      <div class="fs-4 fw-bold text-success"><?= number_format((float)($stats['paid_sum'] ?? 0), 0, ',', '.') ?>đ</div>
      <div class="text-muted small">Đã thu</div>
    </div>
  </div>
</div>

<!-- Payment List -->
<div class="card">
  <div class="card-header"><h6 class="mb-0">Danh sách phiếu thanh toán</h6></div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover table-sm align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th>Phòng</th>
            <th>Người thuê</th>
            <th>Kỳ</th>
            <th class="text-end">Tổng tiền</th>
            <th class="text-end">Đã thu</th>
            <th class="text-center">Hạn TT</th>
            <th class="text-center">Trạng thái</th>
            <th class="text-center">Hành động</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($payments && mysqli_num_rows($payments) > 0): ?>
            <?php while ($p = mysqli_fetch_assoc($payments)): ?>
            <tr>
              <td>
                <strong><?= htmlspecialchars($p['room_code']) ?></strong><br>
                <small class="text-muted"><?= htmlspecialchars($p['building_name']) ?></small>
              </td>
              <td><?= htmlspecialchars($p['tenant_name'] ?? 'N/A') ?></td>
              <td><?= date('m/Y', strtotime($p['period_month'])) ?></td>
              <td class="text-end fw-bold"><?= number_format((float)$p['total_amount'], 0, ',', '.') ?>đ</td>
              <td class="text-end text-success"><?= number_format((float)$p['paid_amount'], 0, ',', '.') ?>đ</td>
              <td class="text-center">
                <?php if ($p['due_date']): ?>
                  <small><?= date('d/m', strtotime($p['due_date'])) ?></small>
                <?php else: ?>
                  <small class="text-muted">—</small>
                <?php endif; ?>
              </td>
              <td class="text-center">
                <?php
                $statusBadges = [
                  'PENDING' => ['warning', 'Chờ TT'],
                  'PAID' => ['success', 'Đã TT'],
                  'PARTIAL' => ['info', 'Một phần'],
                  'OVERDUE' => ['danger', 'Quá hạn']
                ];
                $st = $statusBadges[$p['status']] ?? ['secondary', $p['status']];
                ?>
                <span class="badge bg-<?= $st[0] ?>"><?= $st[1] ?></span>
              </td>
              <td class="text-center">
                <div class="btn-group" role="group">
                  <a href="detail.php?id=<?= $p['payment_id'] ?>" class="btn btn-sm btn-outline-info" title="Chi tiết">
                    <i class="bi bi-eye"></i>
                  </a>
                  <?php if ($p['status'] !== 'PAID'): ?>
                  <a href="collect.php?id=<?= $p['payment_id'] ?>" class="btn btn-sm btn-outline-success" title="Thu tiền">
                    <i class="bi bi-cash"></i>
                  </a>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
            <?php endwhile; ?>
          <?php else: ?>
            <tr><td colspan="8" class="text-center text-muted py-4">Không có phiếu thanh toán nào</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php endif; ?>

</section>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
