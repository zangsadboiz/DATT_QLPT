<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/alerts.php';

$role = (string)($_SESSION['role_name'] ?? '');
$userId = (int)($_SESSION['user_id'] ?? 0);

if (!in_array($role, ['ADMIN', 'STAFF'], true)) {
    admin_redirect('index.php', ['forbidden' => 1]);
}

// Filters
$status = (string)($_GET['status'] ?? '');
$from = (string)($_GET['from'] ?? '');
$to = (string)($_GET['to'] ?? '');
$qraw = (string)($_GET['q'] ?? '');
$q = mysqli_real_escape_string($conn, $qraw);

// Statistics
$statsQuery = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'PENDING' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN status = 'CONFIRMED' THEN 1 ELSE 0 END) as confirmed,
    SUM(CASE WHEN status = 'CHECKED_IN' THEN 1 ELSE 0 END) as checked_in,
    SUM(CASE WHEN status = 'CHECKED_OUT' THEN 1 ELSE 0 END) as checked_out,
    SUM(CASE WHEN status = 'CANCELLED' THEN 1 ELSE 0 END) as cancelled
    FROM bookings";
$statsResult = mysqli_query($conn, $statsQuery);
$stats = $statsResult ? mysqli_fetch_assoc($statsResult) : [
    'total' => 0,
    'pending' => 0,
    'confirmed' => 0,
    'checked_in' => 0,
    'checked_out' => 0,
    'cancelled' => 0
];

/* ===== PHÂN TRANG ===== */
$limit = 10; // Keep original limit for display
$page  = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $limit;

/* ===== WHERE CHUNG (KHÔNG LẤY CANCELLED) ===== */
$where = "
    b.status != 'CANCELLED'
    AND b.check_in < '$to'
    AND b.check_out > '$from'
";

/* tìm kiếm: mã đặt, phòng, khách, loại phòng */
if ($qraw !== '') {
    $where .= " AND (
        b.booking_code LIKE '%$q%' OR
        r.room_code LIKE '%$q%' OR
        t.full_name LIKE '%$q%' OR
        rt.type_name LIKE '%$q%'
    )";
}

// Count total
$totalRes = mysqli_query($conn, "
    SELECT COUNT(*) AS total
    FROM bookings b
    JOIN rooms r ON b.room_id = r.room_id
    JOIN tenants t ON b.tenant_id = t.tenant_id
");
if ($totalRes) {
    $totalRow = mysqli_fetch_assoc($totalRes);
    $total = (int)($totalRow['total'] ?? 0);
} else {
    $total = 0;
}
$totalPages = max(1, (int)ceil($total / 50)); // Changed to 50 as per instruction

// Query bookings
$sql = "SELECT b.*, 
        r.room_code, 
        bd.building_name,
        t.full_name as tenant_name,
        t.phone as tenant_phone,
        t.student_code
        FROM bookings b
        JOIN rooms r ON r.room_id = b.room_id
        JOIN buildings bd ON bd.building_id = r.building_id
        JOIN tenants t ON t.tenant_id = b.tenant_id
        WHERE 1=1";

if ($status && in_array($status, ['PENDING','CONFIRMED','CHECKED_IN','CHECKED_OUT','CANCELLED'], true)) {
    $sql .= " AND b.status = '" . mysqli_real_escape_string($conn, $status) . "'";
}

$sql .= " ORDER BY b.created_at DESC";

$result = mysqli_query($conn, $sql);
if (!$result) {
    die("SQL Error: " . htmlspecialchars(mysqli_error($conn)));
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="pagetitle">
  <h1><i class="bi bi-calendar-check me-2"></i>Quản lý đặt phòng</h1>
  <nav>
    <ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="<?= ADMIN_BASE_PATH ?>/index.php">Dashboard</a></li>
      <li class="breadcrumb-item active">Đặt phòng</li>
    </ol>
  </nav>
</div>

<section class="section">

  <!-- Statistics Cards -->
  <div class="row mb-4">
    <div class="col-xl-2 col-md-4 col-6">
      <div class="card stats-card" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
        <div class="card-body">
          <div class="stats-label">Tổng số</div>
          <div class="stats-number"><?= $stats['total'] ?? 0 ?></div>
        </div>
      </div>
    </div>
    
    <div class="col-xl-2 col-md-4 col-6">
      <div class="card stats-card" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
        <div class="card-body">
          <div class="stats-label">Chờ duyệt</div>
          <div class="stats-number"><?= $stats['pending'] ?? 0 ?></div>
        </div>
      </div>
    </div>
    
    <div class="col-xl-2 col-md-4 col-6">
      <div class="card stats-card" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
        <div class="card-body">
          <div class="stats-label">Đã duyệt</div>
          <div class="stats-number"><?= $stats['confirmed'] ?? 0 ?></div>
        </div>
      </div>
    </div>
    
    <div class="col-xl-2 col-md-4 col-6">
      <div class="card stats-card" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);">
        <div class="card-body">
          <div class="stats-label">Đang thuê</div>
          <div class="stats-number"><?= $stats['checked_in'] ?? 0 ?></div>
        </div>
      </div>
    </div>
    
    <div class="col-xl-2 col-md-4 col-6">
      <div class="card stats-card" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);">
        <div class="card-body">
          <div class="stats-label">Đã trả</div>
          <div class="stats-number"><?= $stats['checked_out'] ?? 0 ?></div>
        </div>
      </div>
    </div>
    
    <div class="col-xl-2 col-md-4 col-6">
      <div class="card stats-card" style="background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%);">
        <div class="card-body">
          <div class="stats-label">Đã hủy</div>
          <div class="stats-number text-dark"><?= $stats['cancelled'] ?? 0 ?></div>
        </div>
      </div>
    </div>
  </div>

  <!-- Filters -->
  <div class="card mb-3">
    <div class="card-body">
      <form method="get" class="row g-3">
        <div class="col-md-3">
          <label class="form-label">Trạng thái</label>
          <select name="status" class="form-select">
            <option value="">-- Tất cả --</option>
            <option value="PENDING" <?= $status === 'PENDING' ? 'selected' : '' ?>>Chờ duyệt</option>
            <option value="CONFIRMED" <?= $status === 'CONFIRMED' ? 'selected' : '' ?>>Đã duyệt</option>
            <option value="CHECKED_IN" <?= $status === 'CHECKED_IN' ? 'selected' : '' ?>>Đang thuê</option>
            <option value="CHECKED_OUT" <?= $status === 'CHECKED_OUT' ? 'selected' : '' ?>>Đã trả</option>
            <option value="CANCELLED" <?= $status === 'CANCELLED' ? 'selected' : '' ?>>Đã hủy</option>
          </select>
        </div>
        
        <div class="col-md-3">
          <label class="form-label">Từ ngày</label>
          <input type="date" name="from" class="form-control" value="<?= htmlspecialchars($from) ?>">
        </div>
        
        <div class="col-md-3">
          <label class="form-label">Đến ngày</label>
          <input type="date" name="to" class="form-control" value="<?= htmlspecialchars($to) ?>">
        </div>
        
        <div class="col-md-3 d-flex align-items-end">
          <button type="submit" class="btn btn-primary w-100">
            <i class="bi bi-search me-1"></i>Lọc
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- Data Table -->

<?php if (isset($_GET['msg']) && $_GET['msg']=='confirmed'): ?>
<div class="alert alert-success alert-dismissible fade show">
    Xác nhận / Khôi phục đặt phòng thành công
    <button class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if (isset($_GET['msg']) && $_GET['msg']=='created'): ?>
<div class="alert alert-success alert-dismissible fade show">
    Tạo đặt phòng thành công (Trạng thái: Đang chờ)
    <button class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if (isset($_GET['error']) && $_GET['error']=='cannot_cancel'): ?>
<div class="alert alert-danger alert-dismissible fade show">
    Không thể hủy (chỉ hủy được khi Đang chờ hoặc Đã đặt)
    <button class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if (isset($_GET['error']) && $_GET['error']=='cannot_confirm'): ?>
<div class="alert alert-danger alert-dismissible fade show">
    Không thể xác nhận / khôi phục đặt phòng này
    <button class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if (isset($_GET['msg']) && $_GET['msg']=='checkin'): ?>
<div class="alert alert-success alert-dismissible fade show">
    Nhận phòng thành công (Trạng thái: Đang ở)
    <button class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if (isset($_GET['error']) && $_GET['error']=='checkin_invalid_status'): ?>
<div class="alert alert-danger alert-dismissible fade show">
    Không thể nhận phòng: Chỉ nhận phòng khi trạng thái là "Đã đặt".
    <button class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if (isset($_GET['error']) && $_GET['error']=='checkin_date'): ?>
<div class="alert alert-warning alert-dismissible fade show">
    Không thể nhận phòng: Hôm nay không nằm trong khoảng ngày nhận/trả của đặt phòng.
    <button class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>


<!-- FILTER -->
<form method="get" class="row g-3 mb-3">
    <div class="col-md-3">
        <label class="form-label">Từ ngày</label>
        <input type="date" name="from" class="form-control" value="<?= $from ?>">
    </div>

    <div class="col-md-3">
        <label class="form-label">Đến ngày</label>
        <input type="date" name="to" class="form-control" value="<?= $to ?>">
    </div>

    <div class="col-md-4">
        <label class="form-label">Tìm kiếm</label>
        <input type="text" name="q" class="form-control"
               placeholder="Mã đặt / phòng / khách / loại phòng"
               value="<?= htmlspecialchars($qraw) ?>">
    </div>

    <div class="col-md-2 d-flex align-items-end">
        <button class="btn btn-primary w-100">
            <i class="bi bi-search"></i> Lọc
        </button>
    </div>
</form>

<section class="section">
<div class="card">
<div class="card-body">

<table class="table table-hover table-bordered align-middle">
<thead class="table-light">
<tr>
    <th>#</th>
    <th>Mã đặt phòng</th>
    <th>Sinh viên</th>
    <th>Phòng</th>
    <th>Dãy/Tòa</th>
    <th>Ngày thuê</th>
    <th>Trạng thái</th>
    <th>Hành động</th>
</tr>
</thead>
<tbody>
<?php $i = 0; while ($result && ($row = mysqli_fetch_assoc($result))): $i++; ?>
<tr>
    <td><?= $i ?></td>
    <td><strong><?= htmlspecialchars($row['booking_code']) ?></strong></td>
    <td><?= htmlspecialchars($row['tenant_name']) ?></td>
    <td><?= htmlspecialchars($row['room_code']) ?></td>
    <td><?= htmlspecialchars($row['building_name']) ?></td>
    <td>
        <?= date('d/m/Y', strtotime($row['check_in'])) ?> 
        <small>→</small>
        <?= date('d/m/Y', strtotime($row['check_out'])) ?>
    </td>
    <td>
        <?php
        switch ($row['status']) {
        case 'PENDING': echo '<span class="badge bg-warning">Đang chờ duyệt</span>'; break;
        case 'CONFIRMED': echo '<span class="badge bg-info">Đã duyệt</span>'; break;
        case 'CHECKED_IN': echo '<span class="badge bg-success">Đang thuê</span>'; break;
        case 'CHECKED_OUT': echo '<span class="badge bg-secondary">Đã kết thúc</span>'; break;
        case 'CANCELLED': echo '<span class="badge bg-dark">Đã hủy</span>'; break;
        }
        ?>
    </td>
    <td>
        <a href="detail.php?id=<?= $row['booking_id'] ?>" class="btn btn-sm btn-outline-primary">
            <i class="bi bi-eye"></i> Xem
        </a>
    </td>
</tr>
<?php endwhile; ?>

<?php if (!$result || mysqli_num_rows($result) == 0): ?>
<tr>
    <td colspan="8" class="text-center text-muted py-4">Chưa có yêu cầu đặt phòng nào</td>
</tr>
<?php endif; ?>

</tbody>
</table>

<!-- PHÂN TRANG -->
<nav>
<ul class="pagination justify-content-center">
<?php for ($p=1; $p<=$totalPages; $p++): ?>
    <li class="page-item <?= $p==$page?'active':'' ?>">
        <a class="page-link"
           href="?page=<?= $p ?>&from=<?= $from ?>&to=<?= $to ?>&q=<?= urlencode($qraw) ?>">
           <?= $p ?>
        </a>
    </li>
<?php endfor; ?>
</ul>
</nav>

</div>
</div>
</section>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
