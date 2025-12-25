<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';

$role = (string)($_SESSION['role_name'] ?? '');
if ($role !== 'ADMIN') {
    header('Location: ' . ADMIN_BASE_PATH . '/modules/dashboard/index.php');
    exit;
}

// Filter by date range
$fromDate = $_GET['from'] ?? date('Y-m-01');
$toDate = $_GET['to'] ?? date('Y-m-d');
$filterType = $_GET['type'] ?? '';
$filterUser = (int)($_GET['user_id'] ?? 0);
$filterMethod = $_GET['method'] ?? '';

// Build where clause
$where = "WHERE t.created_at BETWEEN '$fromDate 00:00:00' AND '$toDate 23:59:59'";
if ($filterType !== '') {
    $where .= " AND t.transaction_type = '" . mysqli_real_escape_string($conn, $filterType) . "'";
}
if ($filterUser > 0) {
    $where .= " AND t.user_id = $filterUser";
}
if ($filterMethod !== '') {
    $where .= " AND t.payment_method = '" . mysqli_real_escape_string($conn, $filterMethod) . "'";
}

// Get summary statistics
$summaryQuery = @mysqli_query($conn, "
    SELECT 
        SUM(CASE WHEN transaction_type = 'DEPOSIT_RECEIVED' THEN amount ELSE 0 END) as total_deposit,
        SUM(IFNULL(commission_amount, 0)) as total_commission,
        SUM(CASE WHEN transaction_type = 'WITHDRAWAL' THEN ABS(amount) ELSE 0 END) as total_withdrawal,
        SUM(CASE WHEN transaction_type = 'REFUND' THEN amount ELSE 0 END) as total_refund,
        COUNT(CASE WHEN transaction_type = 'DEPOSIT_RECEIVED' THEN 1 END) as count_deposit,
        COUNT(CASE WHEN transaction_type = 'WITHDRAWAL' THEN 1 END) as count_withdrawal,
        COUNT(CASE WHEN transaction_type = 'REFUND' THEN 1 END) as count_refund
    FROM transactions t $where
");
$summary = $summaryQuery ? mysqli_fetch_assoc($summaryQuery) : [];

// Revenue by package
$revenueByPackage = mysqli_query($conn, "
    SELECT pk.package_name, pk.highlight_color, COUNT(*) as post_count, SUM(ABS(t.amount)) as total_amount
    FROM transactions t
    JOIN posts p ON p.post_id = t.post_id
    JOIN packages pk ON pk.package_id = p.package_id
    $where AND t.transaction_type IN ('POST_NEW', 'POST_EXTEND', 'POST_RESUBMIT')
    GROUP BY pk.package_id ORDER BY total_amount DESC
");
$pkgData = [];
while ($row = mysqli_fetch_assoc($revenueByPackage)) $pkgData[] = $row;

// Revenue by day (for chart)
$revenueByDay = @mysqli_query($conn, "
    SELECT DATE(t.created_at) as date,
           SUM(CASE WHEN transaction_type = 'DEPOSIT_RECEIVED' THEN amount ELSE 0 END) as deposit,
           SUM(CASE WHEN transaction_type = 'DEPOSIT_RECEIVED' THEN IFNULL(commission_amount, 0) ELSE 0 END) as commission,
           SUM(CASE WHEN transaction_type = 'WITHDRAWAL' THEN ABS(amount) ELSE 0 END) as withdrawal
    FROM transactions t $where GROUP BY DATE(t.created_at) ORDER BY date ASC
");
$chartData = [];
if ($revenueByDay) while ($row = mysqli_fetch_assoc($revenueByDay)) $chartData[] = $row;

// Transaction type breakdown
$byType = mysqli_query($conn, "
    SELECT transaction_type as type, COUNT(*) as count, SUM(ABS(amount)) as total
    FROM transactions t $where
    GROUP BY transaction_type ORDER BY total DESC
");
$typeData = [];
while ($row = mysqli_fetch_assoc($byType)) $typeData[] = $row;

// Top landlords
$topLandlords = mysqli_query($conn, "
    SELECT u.user_id, u.full_name,
           SUM(CASE WHEN t.transaction_type = 'TOPUP' THEN t.amount ELSE 0 END) as total_topup,
           SUM(CASE WHEN t.transaction_type IN ('POST_NEW', 'POST_EXTEND', 'POST_RESUBMIT') THEN ABS(t.amount) ELSE 0 END) as total_spent,
           COUNT(CASE WHEN t.transaction_type IN ('POST_NEW', 'POST_EXTEND', 'POST_RESUBMIT') THEN 1 END) as post_count
    FROM transactions t JOIN users u ON u.user_id = t.user_id
    $where GROUP BY u.user_id ORDER BY total_spent DESC LIMIT 10
");

// All landlords for filter dropdown
$allUsers = mysqli_query($conn, "SELECT user_id, full_name FROM users WHERE role_id = 2 ORDER BY full_name");

// Pagination for transactions
$perPage = 20;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;

// Count total transactions
$totalCount = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as cnt FROM transactions t $where"))['cnt'] ?? 0;
$totalPages = max(1, ceil($totalCount / $perPage));

// Transactions list with pagination
$transactions = mysqli_query($conn, "
    SELECT t.*, u.full_name as user_name, p.title as post_title
    FROM transactions t JOIN users u ON u.user_id = t.user_id LEFT JOIN posts p ON p.post_id = t.post_id
    $where ORDER BY t.created_at DESC LIMIT $perPage OFFSET $offset
");

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="pagetitle">
  <h1><i class="bi bi-bar-chart me-2"></i>Báo cáo Doanh thu</h1>
  <nav>
    <ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="<?= ADMIN_BASE_PATH ?>/modules/dashboard/index.php">Dashboard</a></li>
      <li class="breadcrumb-item active">Báo cáo doanh thu</li>
    </ol>
  </nav>
</div>

<section class="section">

<!-- Filter -->
<div class="card mb-4">
  <div class="card-body py-3">
    <form class="row g-2 align-items-end" method="get">
      <div class="col-md-2">
        <label class="form-label small text-muted mb-1">Từ ngày</label>
        <input type="date" class="form-control form-control-sm" name="from" value="<?= $fromDate ?>">
      </div>
      <div class="col-md-2">
        <label class="form-label small text-muted mb-1">Đến ngày</label>
        <input type="date" class="form-control form-control-sm" name="to" value="<?= $toDate ?>">
      </div>
      <div class="col-md-2">
        <label class="form-label small text-muted mb-1">Loại GD</label>
        <select class="form-select form-select-sm" name="type">
          <option value="">Tất cả</option>
          <option value="TOPUP" <?= $filterType === 'TOPUP' ? 'selected' : '' ?>>Nạp tiền</option>
          <option value="DEPOSIT_RECEIVED" <?= $filterType === 'DEPOSIT_RECEIVED' ? 'selected' : '' ?>>Thanh toán</option>
          <option value="WITHDRAWAL" <?= $filterType === 'WITHDRAWAL' ? 'selected' : '' ?>>Rút tiền</option>
          <option value="POST_NEW" <?= $filterType === 'POST_NEW' ? 'selected' : '' ?>>Đăng tin</option>
          <option value="POST_EXTEND" <?= $filterType === 'POST_EXTEND' ? 'selected' : '' ?>>Gia hạn</option>
          <option value="POST_RESUBMIT" <?= $filterType === 'POST_RESUBMIT' ? 'selected' : '' ?>>Đăng lại</option>
          <option value="REFUND" <?= $filterType === 'REFUND' ? 'selected' : '' ?>>Hoàn tiền</option>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label small text-muted mb-1">Phương thức</label>
        <select class="form-select form-select-sm" name="method">
          <option value="">Tất cả</option>
          <option value="VNPAY" <?= $filterMethod === 'VNPAY' ? 'selected' : '' ?>>VNPAY</option>
          <option value="BALANCE" <?= $filterMethod === 'BALANCE' ? 'selected' : '' ?>>Số dư</option>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label small text-muted mb-1">Người dùng</label>
        <select class="form-select form-select-sm" name="user_id">
          <option value="">Tất cả</option>
          <?php while ($u = mysqli_fetch_assoc($allUsers)): ?>
            <option value="<?= $u['user_id'] ?>" <?= $filterUser == $u['user_id'] ? 'selected' : '' ?>><?= htmlspecialchars($u['full_name']) ?></option>
          <?php endwhile; ?>
        </select>
      </div>
      <div class="col-md-1">
        <button class="btn btn-primary btn-sm w-100" type="submit"><i class="bi bi-funnel me-1"></i>Lọc</button>
      </div>
    </form>
    <div class="mt-2">
      <a href="?from=<?= date('Y-m-d') ?>&to=<?= date('Y-m-d') ?>" class="btn btn-outline-secondary btn-sm">Hôm nay</a>
      <a href="?from=<?= date('Y-m-d', strtotime('-7 days')) ?>&to=<?= date('Y-m-d') ?>" class="btn btn-outline-secondary btn-sm">7 ngày</a>
      <a href="?from=<?= date('Y-m-01') ?>&to=<?= date('Y-m-d') ?>" class="btn btn-outline-secondary btn-sm">Tháng này</a>
      <a href="?from=<?= date('Y-01-01') ?>&to=<?= date('Y-m-d') ?>" class="btn btn-outline-secondary btn-sm">Năm nay</a>
    </div>
  </div>
</div>

<!-- Summary Cards -->
<div class="row mb-4">
  <div class="col-md-3">
    <div class="card border-0 shadow-sm text-center py-3">
      <div class="text-success mb-1"><i class="bi bi-arrow-down-circle fs-2"></i></div>
      <div class="fs-4 fw-bold text-success"><?= number_format((float)($summary['total_deposit'] ?? 0), 0, ',', '.') ?>đ</div>
      <div class="text-muted small">Thu từ SV (<?= $summary['count_deposit'] ?? 0 ?> lượt)</div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card border-0 shadow-sm text-center py-3">
      <div class="text-primary mb-1"><i class="bi bi-percent fs-2"></i></div>
      <div class="fs-4 fw-bold text-primary"><?= number_format((float)($summary['total_commission'] ?? 0), 0, ',', '.') ?>đ</div>
      <div class="text-muted small">Hoa hồng nền tảng</div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card border-0 shadow-sm text-center py-3">
      <div class="text-danger mb-1"><i class="bi bi-arrow-up-circle fs-2"></i></div>
      <div class="fs-4 fw-bold text-danger"><?= number_format((float)($summary['total_withdrawal'] ?? 0), 0, ',', '.') ?>đ</div>
      <div class="text-muted small">Rút tiền (<?= $summary['count_withdrawal'] ?? 0 ?> lượt)</div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card border-0 shadow-sm text-center py-3">
      <div class="text-warning mb-1"><i class="bi bi-arrow-return-left fs-2"></i></div>
      <div class="fs-4 fw-bold text-warning"><?= number_format((float)($summary['total_refund'] ?? 0), 0, ',', '.') ?>đ</div>
      <div class="text-muted small">Hoàn tiền (<?= $summary['count_refund'] ?? 0 ?> lượt)</div>
    </div>
  </div>
</div>

<!-- Charts Row -->
<div class="row mb-4">
  <div class="col-md-8">
    <div class="card">
      <div class="card-header"><h6 class="mb-0">Biểu đồ giao dịch theo ngày</h6></div>
      <div class="card-body">
        <canvas id="revenueChart" height="100"></canvas>
      </div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card">
      <div class="card-header"><h6 class="mb-0">Phân loại giao dịch</h6></div>
      <div class="card-body">
        <canvas id="methodChart" height="150"></canvas>
      </div>
    </div>
  </div>
</div>

<div class="row mb-4">
  <!-- Revenue by Package -->
  <div class="col-md-6">
    <div class="card h-100">
      <div class="card-header"><h6 class="mb-0">Doanh thu theo gói tin</h6></div>
      <div class="card-body">
        <?php if (empty($pkgData)): ?>
          <p class="text-muted text-center py-3">Không có dữ liệu</p>
        <?php else: ?>
          <table class="table table-sm mb-0">
            <thead class="table-light"><tr><th>Gói</th><th class="text-center">Số tin</th><th class="text-end">Doanh thu</th></tr></thead>
            <tbody>
              <?php foreach ($pkgData as $pkg): ?>
              <tr>
                <td><span class="badge" style="background-color: <?= $pkg['highlight_color'] ?: '#6c757d' ?>"><?= htmlspecialchars($pkg['package_name']) ?></span></td>
                <td class="text-center"><?= $pkg['post_count'] ?></td>
                <td class="text-end fw-bold"><?= number_format((float)$pkg['total_amount'], 0, ',', '.') ?>đ</td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>
    </div>
  </div>
  
  <!-- Top Landlords -->
  <div class="col-md-6">
    <div class="card h-100">
      <div class="card-header"><h6 class="mb-0">Top chủ trọ chi tiêu</h6></div>
      <div class="card-body">
        <table class="table table-sm mb-0">
          <thead class="table-light"><tr><th>Chủ trọ</th><th class="text-center">Tin</th><th class="text-end">Chi tiêu</th></tr></thead>
          <tbody>
            <?php while ($u = mysqli_fetch_assoc($topLandlords)): ?>
            <tr>
              <td><a href="<?= ADMIN_BASE_PATH ?>/modules/chutro/detail.php?user_id=<?= $u['user_id'] ?>"><?= htmlspecialchars($u['full_name']) ?></a></td>
              <td class="text-center"><?= $u['post_count'] ?></td>
              <td class="text-end fw-bold text-danger"><?= number_format((float)$u['total_spent'], 0, ',', '.') ?>đ</td>
            </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- Transactions -->
<div class="card">
  <div class="card-header d-flex justify-content-between align-items-center">
    <h6 class="mb-0">Lịch sử giao dịch (<?= (int)$totalCount ?> giao dịch)</h6>
    <small class="text-muted">Trang <?= $page ?>/<?= $totalPages ?></small>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover table-sm align-middle mb-0">
        <thead class="table-light">
          <tr><th>ID</th><th>Thời gian</th><th>Người dùng</th><th>Loại</th><th>PT</th><th>Mô tả</th><th class="text-end">Số tiền</th></tr>
        </thead>
        <tbody>
          <?php while ($t = mysqli_fetch_assoc($transactions)): ?>
          <tr>
            <td><small class="text-muted">#<?= $t['transaction_id'] ?></small></td>
            <td><small><?= date('d/m H:i', strtotime($t['created_at'])) ?></small></td>
            <td><small><?= htmlspecialchars($t['user_name']) ?></small></td>
            <td>
              <?php
              $badges = [
                  'TOPUP'=>['success','Nạp'],
                  'DEPOSIT_RECEIVED'=>['info','Thanh toán'],
                  'WITHDRAWAL'=>['danger','Rút tiền'],
                  'POST_NEW'=>['primary','Đăng'],
                  'POST_EXTEND'=>['info','GH'],
                  'POST_RESUBMIT'=>['warning text-dark','Lại'],
                  'REFUND'=>['secondary','Hoàn']
              ];
              $b = $badges[$t['transaction_type']] ?? ['secondary',$t['transaction_type']];
              ?>
              <span class="badge bg-<?= $b[0] ?>"><?= $b[1] ?></span>
            </td>
            <td>
              <?php
              $methodBadges = [
                  'VNPAY' => '<span class="badge bg-primary">VNPAY</span>',
                  'BALANCE' => '<span class="badge bg-secondary">Số dư</span>',
                  'BANK' => '<span class="badge bg-info">Ngân hàng</span>',
              ];
              echo $methodBadges[$t['payment_method']] ?? ($t['payment_method'] ? '<small>'.$t['payment_method'].'</small>' : '—');
              ?>
            </td>
            <td><small class="text-truncate d-inline-block" style="max-width:180px"><?= htmlspecialchars($t['description'] ?? '') ?></small></td>
            <td class="text-end">
              <strong class="<?= (float)$t['amount'] > 0 ? 'text-success' : 'text-danger' ?>">
                <?= (float)$t['amount'] > 0 ? '+' : '' ?><?= number_format((float)$t['amount'], 0, ',', '.') ?>đ
              </strong>
            </td>
          </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php if ($totalPages > 1): ?>
  <div class="card-footer">
    <nav>
      <ul class="pagination pagination-sm justify-content-center mb-0">
        <?php 
        $queryParams = $_GET;
        unset($queryParams['page']);
        $baseUrl = '?' . http_build_query($queryParams) . '&page=';
        ?>
        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
          <a class="page-link" href="<?= $baseUrl . ($page - 1) ?>">«</a>
        </li>
        <?php 
        $start = max(1, $page - 2);
        $end = min($totalPages, $page + 2);
        for ($i = $start; $i <= $end; $i++): 
        ?>
          <li class="page-item <?= $i == $page ? 'active' : '' ?>">
            <a class="page-link" href="<?= $baseUrl . $i ?>"><?= $i ?></a>
          </li>
        <?php endfor; ?>
        <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
          <a class="page-link" href="<?= $baseUrl . ($page + 1) ?>">»</a>
        </li>
      </ul>
    </nav>
  </div>
  <?php endif; ?>
</div>

</section>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Revenue by Day Chart
const chartData = <?= json_encode($chartData) ?>;
const labels = chartData.map(d => d.date.substring(5)); // MM-DD
const depositData = chartData.map(d => parseFloat(d.deposit || 0));
const withdrawalData = chartData.map(d => parseFloat(d.withdrawal || 0));
const commissionData = chartData.map(d => parseFloat(d.commission || 0));

new Chart(document.getElementById('revenueChart'), {
  type: 'line',
  data: {
    labels: labels,
    datasets: [
      { label: 'Thu từ SV', data: depositData, borderColor: '#2eca6a', backgroundColor: 'rgba(46,202,106,0.1)', fill: true, tension: 0.3 },
      { label: 'Rút tiền', data: withdrawalData, borderColor: '#e74c3c', backgroundColor: 'rgba(231,76,60,0.1)', fill: true, tension: 0.3 },
      { label: 'Hoa hồng', data: commissionData, borderColor: '#4154f1', backgroundColor: 'rgba(65,84,241,0.1)', fill: true, tension: 0.3 }
    ]
  },
  options: {
    responsive: true,
    plugins: { legend: { position: 'bottom' } },
    scales: { y: { beginAtZero: true, ticks: { callback: v => (v/1000000).toFixed(1) + 'M' } } }
  }
});

// Transaction Type Chart
const typeData = <?= json_encode($typeData) ?>;
const typeLabels = {
    'DEPOSIT_RECEIVED': 'Thanh toán SV',
    'WITHDRAWAL': 'Rút tiền',
    'REFUND': 'Hoàn tiền',
    'TOPUP': 'Nạp tiền',
    'POST_NEW': 'Đăng tin mới',
    'POST_EXTEND': 'Gia hạn tin',
    'POST_RESUBMIT': 'Đăng lại tin',
    'DEPOSIT': 'Nạp tiền',
    'POST': 'Đăng tin'
};
const typeNames = typeData.map(d => typeLabels[d.type] || d.type);
const typeValues = typeData.map(d => parseFloat(d.total));
const typeColors = ['#2eca6a', '#e74c3c', '#f39c12', '#4154f1', '#9b59b6', '#17a2b8', '#6c757d'];

new Chart(document.getElementById('methodChart'), {
  type: 'doughnut',
  data: {
    labels: typeNames,
    datasets: [{ data: typeValues, backgroundColor: typeColors.slice(0, typeNames.length) }]
  },
  options: {
    responsive: true,
    plugins: { legend: { position: 'bottom', labels: { boxWidth: 12 } } }
  }
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
