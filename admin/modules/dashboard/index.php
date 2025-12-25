<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$role   = (string)($_SESSION['role_name'] ?? '');
$userId = (int)($_SESSION['user_id'] ?? 0);

function table_exists(mysqli $conn, string $table): bool {
    $t = mysqli_real_escape_string($conn, $table);
    $rs = mysqli_query($conn, "SHOW TABLES LIKE '{$t}'");
    return $rs && mysqli_num_rows($rs) > 0;
}

function db_fetch_one(mysqli $conn, string $sql, string $types = '', array $params = []): array {
    $stmt = mysqli_prepare($conn, $sql);
    if ($types !== '') {
        $bind = [];
        $bind[] = $types;
        foreach ($params as $k => $v) $bind[] = &$params[$k];
        call_user_func_array([$stmt, 'bind_param'], $bind);
    }
    mysqli_stmt_execute($stmt);
    $rs = mysqli_stmt_get_result($stmt);
    $row = $rs ? (mysqli_fetch_assoc($rs) ?: []) : [];
    mysqli_stmt_close($stmt);
    return $row;
}

function db_fetch_all(mysqli $conn, string $sql, string $types = '', array $params = []): array {
    $stmt = mysqli_prepare($conn, $sql);
    if ($types !== '') {
        $bind = [];
        $bind[] = $types;
        foreach ($params as $k => $v) $bind[] = &$params[$k];
        call_user_func_array([$stmt, 'bind_param'], $bind);
    }
    mysqli_stmt_execute($stmt);
    $rs = mysqli_stmt_get_result($stmt);
    $rows = [];
    while ($rs && ($r = mysqli_fetch_assoc($rs))) $rows[] = $r;
    mysqli_stmt_close($stmt);
    return $rows;
}

function month_list(int $n = 6): array {
    // trả về mảng 6 tháng gần nhất (tính cả tháng hiện tại)
    $out = [];
    for ($i = $n - 1; $i >= 0; $i--) {
        $ts = strtotime(date('Y-m-01') . " -{$i} month");
        $ym = date('Y-m', $ts);
        $out[] = [
            'ym' => $ym,
            'label' => date('m/Y', $ts),
            'start' => $ym . '-01 00:00:00',
            'end' => date('Y-m-01 00:00:00', strtotime($ym . '-01 +1 month')),
            'date_start' => $ym . '-01',
            'date_end' => date('Y-m-01', strtotime($ym . '-01 +1 month')),
        ];
    }
    return $out;
}

$months = month_list(6);
$fromDT = $months[0]['start']; // datetime start of oldest month

$hasSvcInvoices = table_exists($conn, 'service_invoices');
$hasPayments    = table_exists($conn, 'payments');
$hasInvoices    = table_exists($conn, 'invoices');
$hasContracts   = table_exists($conn, 'contracts');
$hasBuildings   = table_exists($conn, 'buildings');
$hasRooms       = table_exists($conn, 'rooms');

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="pagetitle">
  <h1>Dashboard</h1>
</div>

<section class="section">

<?php if (in_array($role, ['ADMIN','STAFF'], true)): ?>

  <?php
  // ===== ADMIN KPIs =====
  $roomAgg = $hasRooms ? db_fetch_one($conn, "
      SELECT
        SUM(CASE WHEN publish_status='APPROVED' THEN 1 ELSE 0 END) AS approved,
        SUM(CASE WHEN publish_status='PENDING'  THEN 1 ELSE 0 END) AS pending,
        SUM(CASE WHEN publish_status='HIDDEN'   THEN 1 ELSE 0 END) AS hidden,
        COUNT(*) AS total
      FROM rooms
      WHERE deleted_at IS NULL
  ") : ['approved'=>0,'pending'=>0,'hidden'=>0,'total'=>0];

  $buildingAgg = $hasBuildings ? db_fetch_one($conn, "
      SELECT
        SUM(CASE WHEN building_status='APPROVED' THEN 1 ELSE 0 END) AS approved,
        SUM(CASE WHEN building_status='PENDING'  THEN 1 ELSE 0 END) AS pending,
        SUM(CASE WHEN building_status='HIDDEN'   THEN 1 ELSE 0 END) AS hidden,
        COUNT(*) AS total
      FROM buildings
  ") : ['approved'=>0,'pending'=>0,'hidden'=>0,'total'=>0];

  $svcRevenueTotal = 0;
  $svcRevenueMonth = 0;
  $svcWaiting = 0;
  $svcUnpaid = 0;

  if ($hasSvcInvoices) {
      $svcRevenueTotal = (float)(db_fetch_one($conn, "
          SELECT COALESCE(SUM(amount),0) AS v
          FROM service_invoices
          WHERE invoice_type='LISTING_FEE' AND status='PAID'
      ")['v'] ?? 0);

      // tháng hiện tại
      $m0 = $months[count($months)-1];
      $svcRevenueMonth = (float)(db_fetch_one($conn, "
          SELECT COALESCE(SUM(amount),0) AS v
          FROM service_invoices
          WHERE invoice_type='LISTING_FEE' AND status='PAID'
            AND paid_at >= ? AND paid_at < ?
      ", "ss", [$m0['start'], $m0['end']])['v'] ?? 0);

      $tmp = db_fetch_one($conn, "
          SELECT
            SUM(CASE WHEN status='WAITING_CONFIRM' THEN 1 ELSE 0 END) AS waiting,
            SUM(CASE WHEN status='UNPAID' THEN 1 ELSE 0 END) AS unpaid
          FROM service_invoices
          WHERE invoice_type='LISTING_FEE'
      ");
      $svcWaiting = (int)($tmp['waiting'] ?? 0);
      $svcUnpaid  = (int)($tmp['unpaid'] ?? 0);
  }

  // ===== ADMIN Charts data =====
  // 1) approvals per month (rooms)
  $approvedMap = [];
  if ($hasRooms) {
      $rows = db_fetch_all($conn, "
        SELECT DATE_FORMAT(COALESCE(updated_at, created_at), '%Y-%m') AS ym, COUNT(*) AS c
        FROM rooms
        WHERE deleted_at IS NULL
          AND publish_status='APPROVED'
          AND COALESCE(updated_at, created_at) >= ?
        GROUP BY ym
      ", "s", [$fromDT]);
      foreach ($rows as $r) $approvedMap[(string)$r['ym']] = (int)$r['c'];
  }

  $approvedSeries = [];
  $monthLabels = [];
  foreach ($months as $m) {
      $monthLabels[] = $m['label'];
      $approvedSeries[] = $approvedMap[$m['ym']] ?? 0;
  }

  // 2) listing fee revenue per month
  $feeMap = [];
  if ($hasSvcInvoices) {
      $rows = db_fetch_all($conn, "
        SELECT DATE_FORMAT(paid_at, '%Y-%m') AS ym, COALESCE(SUM(amount),0) AS v
        FROM service_invoices
        WHERE invoice_type='LISTING_FEE' AND status='PAID'
          AND paid_at >= ?
        GROUP BY ym
      ", "s", [$fromDT]);
      foreach ($rows as $r) $feeMap[(string)$r['ym']] = (float)$r['v'];
  }

  $feeSeries = [];
  foreach ($months as $m) $feeSeries[] = (float)($feeMap[$m['ym']] ?? 0);

  // 3) room publish donut
  $donutRooms = [
      (int)($roomAgg['approved'] ?? 0),
      (int)($roomAgg['pending'] ?? 0),
      (int)($roomAgg['hidden'] ?? 0),
  ];

  // Recent approved (top 8)
  $recentApproved = $hasRooms ? db_fetch_all($conn, "
      SELECT r.room_code, r.updated_at, r.created_at,
             b.building_code, b.building_name,
             u.full_name AS owner_name
      FROM rooms r
      JOIN buildings b ON b.building_id = r.building_id
      JOIN users u ON u.user_id = b.owner_user_id
      WHERE r.deleted_at IS NULL AND r.publish_status='APPROVED'
      ORDER BY COALESCE(r.updated_at, r.created_at) DESC
      LIMIT 8
  ") : [];
  ?>

  <!-- KPI Cards -->
  <div class="row g-3">
    <div class="col-md-3">
      <div class="card info-card sales-card">
        <div class="card-body pt-3">
          <h5 class="card-title">Phòng đã duyệt</h5>
          <div class="d-flex align-items-center">
            <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
              <i class="bi bi-house-check"></i>
            </div>
            <div class="ps-3">
              <h6><?= (int)($roomAgg['approved'] ?? 0) ?></h6>
              <span class="text-muted small">Tổng: <?= (int)($roomAgg['total'] ?? 0) ?></span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="col-md-3">
      <div class="card info-card revenue-card">
        <div class="card-body pt-3">
          <h5 class="card-title">Chờ duyệt</h5>
          <div class="d-flex align-items-center">
            <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
              <i class="bi bi-hourglass-split"></i>
            </div>
            <div class="ps-3">
              <h6><?= (int)($roomAgg['pending'] ?? 0) ?></h6>
              <span class="text-muted small">Ẩn: <?= (int)($roomAgg['hidden'] ?? 0) ?></span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="col-md-3">
      <div class="card info-card customers-card">
        <div class="card-body pt-3">
          <h5 class="card-title">Dãy/Tòa</h5>
          <div class="d-flex align-items-center">
            <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
              <i class="bi bi-buildings"></i>
            </div>
            <div class="ps-3">
              <h6><?= (int)($buildingAgg['total'] ?? 0) ?></h6>
              <span class="text-muted small">
                Duyệt: <?= (int)($buildingAgg['approved'] ?? 0) ?> |
                Chờ: <?= (int)($buildingAgg['pending'] ?? 0) ?>
              </span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="col-md-3">
      <div class="card info-card revenue-card">
        <div class="card-body pt-3">
          <h5 class="card-title">Phí đăng tin (tháng)</h5>
          <div class="d-flex align-items-center">
            <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
              <i class="bi bi-qr-code"></i>
            </div>
            <div class="ps-3">
              <h6><?= number_format($svcRevenueMonth, 0, ',', '.') ?> VND</h6>
              <span class="text-muted small">
                Tổng: <?= number_format($svcRevenueTotal, 0, ',', '.') ?> |
                Chờ xác nhận: <?= (int)$svcWaiting ?>
              </span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Charts -->
  <div class="row g-3 mt-1">
    <div class="col-md-4">
      <div class="card">
        <div class="card-body pt-3">
          <h5 class="card-title">Phân bố trạng thái phòng</h5>
          <div id="adminRoomDonut" style="min-height: 300px;"></div>
          <div class="text-muted small mt-2">
            APPROVED / PENDING / HIDDEN
          </div>
        </div>
      </div>
    </div>

    <div class="col-md-8">
      <div class="card">
        <div class="card-body pt-3">
          <h5 class="card-title">Số phòng được duyệt theo tháng</h5>
          <div id="adminApproveBar" style="min-height: 320px;"></div>
        </div>
      </div>
    </div>
  </div>

  <div class="row g-3 mt-1">
    <div class="col-md-8">
      <div class="card">
        <div class="card-body pt-3">
          <h5 class="card-title">Doanh thu phí đăng tin theo tháng</h5>
          <?php if (!$hasSvcInvoices): ?>
            <div class="alert alert-warning mb-0">Chưa có bảng <code>service_invoices</code> để vẽ biểu đồ phí đăng tin.</div>
          <?php else: ?>
            <div id="adminFeeLine" style="min-height: 320px;"></div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <div class="col-md-4">
      <div class="card">
        <div class="card-body pt-3">
          <h5 class="card-title">Phòng duyệt gần đây</h5>
          <div class="table-responsive">
            <table class="table table-sm table-hover align-middle">
              <thead>
                <tr>
                  <th>Phòng</th>
                  <th>Dãy/Tòa</th>
                  <th>Chủ trọ</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($recentApproved)): ?>
                  <tr><td colspan="3" class="text-muted">Chưa có dữ liệu.</td></tr>
                <?php else: ?>
                  <?php foreach ($recentApproved as $r): ?>
                    <tr>
                      <td><?= htmlspecialchars((string)$r['room_code']) ?></td>
                      <td><?= htmlspecialchars((string)$r['building_code'].' - '.(string)$r['building_name']) ?></td>
                      <td><?= htmlspecialchars((string)$r['owner_name']) ?></td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>

          <div class="mt-2 text-muted small">
            Phí UNPAID: <strong><?= (int)$svcUnpaid ?></strong> |
            WAITING_CONFIRM: <strong><?= (int)$svcWaiting ?></strong>
          </div>
        </div>
      </div>
    </div>
  </div>

<?php elseif ($role === 'LANDLORD'): ?>

  <?php
  // ===== OWNER KPIs =====
  $myRooms = $hasRooms ? db_fetch_one($conn, "
      SELECT
        SUM(CASE WHEN r.publish_status='APPROVED' THEN 1 ELSE 0 END) AS approved,
        SUM(CASE WHEN r.publish_status='PENDING'  THEN 1 ELSE 0 END) AS pending,
        SUM(CASE WHEN r.publish_status='HIDDEN'   THEN 1 ELSE 0 END) AS hidden,
        SUM(CASE WHEN r.room_status='VACANT' THEN 1 ELSE 0 END) AS vacant,
        SUM(CASE WHEN r.room_status='OCCUPIED' THEN 1 ELSE 0 END) AS occupied,
        SUM(CASE WHEN r.room_status='MAINTENANCE' THEN 1 ELSE 0 END) AS maintenance,
        SUM(CASE WHEN r.room_status='LOCKED' THEN 1 ELSE 0 END) AS locked,
        COUNT(*) AS total
      FROM rooms r
      JOIN buildings b ON b.building_id = r.building_id
      WHERE b.owner_user_id = ?
        AND r.deleted_at IS NULL
  ", "i", [$userId]) : [];

  $myActiveContracts = ($hasContracts && $hasBuildings && $hasRooms) ? (int)(db_fetch_one($conn, "
      SELECT COUNT(*) AS c
      FROM contracts c
      JOIN rooms r ON r.room_id = c.room_id
      JOIN buildings b ON b.building_id = r.building_id
      WHERE b.owner_user_id = ?
        AND c.contract_status='ACTIVE'
  ", "i", [$userId])['c'] ?? 0) : 0;

  $myUnpaidInvoices = ($hasInvoices && $hasContracts && $hasRooms && $hasBuildings) ? (int)(db_fetch_one($conn, "
      SELECT COUNT(*) AS c
      FROM invoices i
      JOIN contracts c ON c.contract_id = i.contract_id
      JOIN rooms r ON r.room_id = c.room_id
      JOIN buildings b ON b.building_id = r.building_id
      WHERE b.owner_user_id = ?
        AND i.invoice_status='ISSUED'
  ", "i", [$userId])['c'] ?? 0) : 0;

  // Doanh thu chủ trọ: ưu tiên theo payments (tiền thực nhận)
  $myRevenueThisMonth = 0.0;
  if ($hasPayments && $hasInvoices && $hasContracts && $hasRooms && $hasBuildings) {
      $m0 = $months[count($months)-1];
      $myRevenueThisMonth = (float)(db_fetch_one($conn, "
          SELECT COALESCE(SUM(p.amount),0) AS v
          FROM payments p
          JOIN invoices i ON i.invoice_id = p.invoice_id
          JOIN contracts c ON c.contract_id = i.contract_id
          JOIN rooms r ON r.room_id = c.room_id
          JOIN buildings b ON b.building_id = r.building_id
          WHERE b.owner_user_id = ?
            AND p.paid_at >= ? AND p.paid_at < ?
      ", "iss", [$userId, $m0['start'], $m0['end']])['v'] ?? 0);
  }

  // ===== OWNER Charts data =====
  $roomStatusDonut = [
      (int)($myRooms['vacant'] ?? 0),
      (int)($myRooms['occupied'] ?? 0),
      (int)($myRooms['maintenance'] ?? 0),
      (int)($myRooms['locked'] ?? 0),
  ];

  $publishDonut = [
      (int)($myRooms['approved'] ?? 0),
      (int)($myRooms['pending'] ?? 0),
      (int)($myRooms['hidden'] ?? 0),
  ];

  // Revenue by month (payments)
  $revMap = [];
  if ($hasPayments && $hasInvoices && $hasContracts && $hasRooms && $hasBuildings) {
      $rows = db_fetch_all($conn, "
          SELECT DATE_FORMAT(p.paid_at,'%Y-%m') AS ym, COALESCE(SUM(p.amount),0) AS v
          FROM payments p
          JOIN invoices i ON i.invoice_id = p.invoice_id
          JOIN contracts c ON c.contract_id = i.contract_id
          JOIN rooms r ON r.room_id = c.room_id
          JOIN buildings b ON b.building_id = r.building_id
          WHERE b.owner_user_id = ?
            AND p.paid_at >= ?
          GROUP BY ym
      ", "is", [$userId, $fromDT]);
      foreach ($rows as $r) $revMap[(string)$r['ym']] = (float)$r['v'];
  }

  $ownerRevSeries = [];
  $ownerMonthLabels = [];
  foreach ($months as $m) {
      $ownerMonthLabels[] = $m['label'];
      $ownerRevSeries[] = (float)($revMap[$m['ym']] ?? 0);
  }

  // Unpaid invoices by month (ISSUED)
  $unpaidMap = [];
  if ($hasInvoices && $hasContracts && $hasRooms && $hasBuildings) {
      $rows = db_fetch_all($conn, "
          SELECT DATE_FORMAT(i.invoice_month,'%Y-%m') AS ym, COUNT(*) AS c
          FROM invoices i
          JOIN contracts c ON c.contract_id = i.contract_id
          JOIN rooms r ON r.room_id = c.room_id
          JOIN buildings b ON b.building_id = r.building_id
          WHERE b.owner_user_id = ?
            AND i.invoice_status='ISSUED'
            AND i.invoice_month >= ?
          GROUP BY ym
      ", "is", [$userId, $months[0]['date_start']]);
      foreach ($rows as $r) $unpaidMap[(string)$r['ym']] = (int)$r['c'];
  }

  $unpaidSeries = [];
  foreach ($months as $m) $unpaidSeries[] = (int)($unpaidMap[$m['ym']] ?? 0);
  ?>

  <!-- KPI Cards (Owner) -->
  <div class="row g-3">
    <div class="col-md-3">
      <div class="card info-card revenue-card">
        <div class="card-body pt-3">
          <h5 class="card-title">Doanh thu thu tiền (tháng)</h5>
          <div class="d-flex align-items-center">
            <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
              <i class="bi bi-cash-stack"></i>
            </div>
            <div class="ps-3">
              <h6><?= number_format($myRevenueThisMonth, 0, ',', '.') ?> VND</h6>
              <span class="text-muted small">Theo payments</span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="col-md-3">
      <div class="card info-card customers-card">
        <div class="card-body pt-3">
          <h5 class="card-title">Hóa đơn chưa thu</h5>
          <div class="d-flex align-items-center">
            <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
              <i class="bi bi-receipt"></i>
            </div>
            <div class="ps-3">
              <h6><?= (int)$myUnpaidInvoices ?></h6>
              <span class="text-muted small">Trạng thái ISSUED</span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="col-md-3">
      <div class="card info-card sales-card">
        <div class="card-body pt-3">
          <h5 class="card-title">Phòng của tôi</h5>
          <div class="d-flex align-items-center">
            <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
              <i class="bi bi-house"></i>
            </div>
            <div class="ps-3">
              <h6><?= (int)($myRooms['total'] ?? 0) ?></h6>
              <span class="text-muted small">
                Duyệt: <?= (int)($myRooms['approved'] ?? 0) ?> |
                Chờ: <?= (int)($myRooms['pending'] ?? 0) ?> |
                Ẩn: <?= (int)($myRooms['hidden'] ?? 0) ?>
              </span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="col-md-3">
      <div class="card info-card sales-card">
        <div class="card-body pt-3">
          <h5 class="card-title">Hợp đồng ACTIVE</h5>
          <div class="d-flex align-items-center">
            <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
              <i class="bi bi-file-earmark-text"></i>
            </div>
            <div class="ps-3">
              <h6><?= (int)$myActiveContracts ?></h6>
              <span class="text-muted small">Đang hiệu lực</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Charts (Owner) -->
  <div class="row g-3 mt-1">
    <div class="col-md-4">
      <div class="card">
        <div class="card-body pt-3">
          <h5 class="card-title">Trạng thái phòng (VACANT/OCCUPIED...)</h5>
          <div id="ownerRoomStatusDonut" style="min-height: 300px;"></div>
        </div>
      </div>
    </div>

    <div class="col-md-4">
      <div class="card">
        <div class="card-body pt-3">
          <h5 class="card-title">Duyệt phòng (APPROVED/PENDING/HIDDEN)</h5>
          <div id="ownerPublishDonut" style="min-height: 300px;"></div>
        </div>
      </div>
    </div>

    <div class="col-md-4">
      <div class="card">
        <div class="card-body pt-3">
          <h5 class="card-title">Hóa đơn ISSUED theo tháng</h5>
          <div id="ownerUnpaidBar" style="min-height: 300px;"></div>
        </div>
      </div>
    </div>
  </div>

  <div class="row g-3 mt-1">
    <div class="col-md-12">
      <div class="card">
        <div class="card-body pt-3">
          <h5 class="card-title">Doanh thu thu tiền theo tháng</h5>
          <div id="ownerRevenueLine" style="min-height: 320px;"></div>
          <div class="text-muted small mt-2">Nguồn dữ liệu: bảng <code>payments</code>.</div>
        </div>
      </div>
    </div>
  </div>

<?php else: ?>

  <div class="alert alert-warning mb-0">
    Tài khoản hiện tại chưa có quyền xem thống kê Dashboard.
  </div>

<?php endif; ?>

</section>

<!-- ApexCharts (NiceAdmin thường có sẵn, nhưng include thêm để chắc chắn) -->
<script src="/quanlyphongtro/admin/assets/vendor/apexcharts/apexcharts.min.js"></script>

<script>
(function () {
  // ---------- Admin charts ----------
  const isAdmin = <?= json_encode(in_array($role, ['ADMIN','STAFF'], true)) ?>;
  const isOwner = <?= json_encode($role === 'LANDLORD') ?>;

  const monthLabels = <?= json_encode($monthLabels ?? []) ?>;

  if (isAdmin) {
    const donutRooms = <?= json_encode($donutRooms ?? [0,0,0]) ?>;
    const approvedSeries = <?= json_encode($approvedSeries ?? []) ?>;
    const feeSeries = <?= json_encode($feeSeries ?? []) ?>;
    const hasSvc = <?= json_encode($hasSvcInvoices) ?>;

    const elDonut = document.querySelector("#adminRoomDonut");
    if (elDonut) {
      new ApexCharts(elDonut, {
        chart: { type: 'donut', height: 300 },
        labels: ['APPROVED','PENDING','HIDDEN'],
        series: donutRooms,
        legend: { position: 'bottom' }
      }).render();
    }

    const elBar = document.querySelector("#adminApproveBar");
    if (elBar) {
      new ApexCharts(elBar, {
        chart: { type: 'bar', height: 320, toolbar: { show: false } },
        series: [{ name: 'Đã duyệt', data: approvedSeries }],
        xaxis: { categories: monthLabels },
        dataLabels: { enabled: false }
      }).render();
    }

    const elFee = document.querySelector("#adminFeeLine");
    if (elFee && hasSvc) {
      new ApexCharts(elFee, {
        chart: { type: 'line', height: 320, toolbar: { show: false } },
        series: [{ name: 'Doanh thu phí', data: feeSeries }],
        xaxis: { categories: monthLabels },
        stroke: { width: 3 },
        dataLabels: { enabled: false }
      }).render();
    }
  }

  // ---------- Owner charts ----------
  if (isOwner) {
    const roomStatusDonut = <?= json_encode($roomStatusDonut ?? [0,0,0,0]) ?>;
    const publishDonut = <?= json_encode($publishDonut ?? [0,0,0]) ?>;
    const unpaidSeries = <?= json_encode($unpaidSeries ?? []) ?>;
    const ownerRevSeries = <?= json_encode($ownerRevSeries ?? []) ?>;
    const ownerLabels = <?= json_encode($ownerMonthLabels ?? []) ?>;

    const elA = document.querySelector("#ownerRoomStatusDonut");
    if (elA) {
      new ApexCharts(elA, {
        chart: { type: 'donut', height: 300 },
        labels: ['VACANT','OCCUPIED','MAINTENANCE','LOCKED'],
        series: roomStatusDonut,
        legend: { position: 'bottom' }
      }).render();
    }

    const elB = document.querySelector("#ownerPublishDonut");
    if (elB) {
      new ApexCharts(elB, {
        chart: { type: 'donut', height: 300 },
        labels: ['APPROVED','PENDING','HIDDEN'],
        series: publishDonut,
        legend: { position: 'bottom' }
      }).render();
    }

    const elC = document.querySelector("#ownerUnpaidBar");
    if (elC) {
      new ApexCharts(elC, {
        chart: { type: 'bar', height: 300, toolbar: { show: false } },
        series: [{ name: 'ISSUED', data: unpaidSeries }],
        xaxis: { categories: ownerLabels },
        dataLabels: { enabled: false }
      }).render();
    }

    const elD = document.querySelector("#ownerRevenueLine");
    if (elD) {
      new ApexCharts(elD, {
        chart: { type: 'area', height: 320, toolbar: { show: false } },
        series: [{ name: 'Doanh thu', data: ownerRevSeries }],
        xaxis: { categories: ownerLabels },
        dataLabels: { enabled: false }
      }).render();
    }
  }
})();
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
