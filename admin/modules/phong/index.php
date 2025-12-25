<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/alerts.php';
require_once __DIR__ . '/../../includes/form_helpers.php';

$role = (string)($_SESSION['role_name'] ?? '');
if (!in_array($role, ['ADMIN', 'STAFF'], true)) {
    admin_redirect('modules/dashboard/index.php', ['forbidden' => 1]);
}

// Filter parameters
$building_id = isset($_GET['building_id']) && is_numeric($_GET['building_id']) ? (int)$_GET['building_id'] : 0;
$owner_id = isset($_GET['owner_id']) && is_numeric($_GET['owner_id']) ? (int)$_GET['owner_id'] : 0;
$room_status = (string)($_GET['room_status'] ?? '');
$publish_status = (string)($_GET['publish_status'] ?? '');
$price_min = isset($_GET['price_min']) && is_numeric($_GET['price_min']) ? (int)$_GET['price_min'] : 0;
$price_max = isset($_GET['price_max']) && is_numeric($_GET['price_max']) ? (int)$_GET['price_max'] : 0;
$q = trim((string)($_GET['q'] ?? ''));

// Build query
$sql = "SELECT r.*, b.building_name, b.building_code, u.full_name as owner_name
        FROM rooms r
        JOIN buildings b ON b.building_id = r.building_id
        LEFT JOIN users u ON u.user_id = b.owner_user_id
        WHERE r.deleted_at IS NULL";

$params = [];
$types = "";

if ($building_id > 0) {
    $sql .= " AND r.building_id = ?";
    $types .= "i";
    $params[] = $building_id;
}

if ($owner_id > 0) {
    $sql .= " AND b.owner_user_id = ?";
    $types .= "i";
    $params[] = $owner_id;
}

if ($room_status !== '' && in_array($room_status, ['VACANT','OCCUPIED','MAINTENANCE','LOCKED'], true)) {
    $sql .= " AND r.room_status = ?";
    $types .= "s";
    $params[] = $room_status;
}

if ($publish_status !== '' && in_array($publish_status, ['PENDING','APPROVED','HIDDEN'], true)) {
    $sql .= " AND r.publish_status = ?";
    $types .= "s";
    $params[] = $publish_status;
}

if ($price_min > 0) {
    $sql .= " AND r.base_rent >= ?";
    $types .= "d";
    $params[] = $price_min;
}

if ($price_max > 0) {
    $sql .= " AND r.base_rent <= ?";
    $types .= "d";
    $params[] = $price_max;
}

if ($q !== '') {
    $like = "%{$q}%";
    $sql .= " AND (r.room_code LIKE ? OR b.building_name LIKE ?)";
    $types .= "ss";
    $params[] = $like;
    $params[] = $like;
}

$sql .= " ORDER BY r.room_id DESC";

$stmt = mysqli_prepare($conn, $sql);
if (!$stmt) die("SQL error: " . htmlspecialchars(mysqli_error($conn)));

if ($types !== '') {
    $bind = [$types];
    foreach ($params as $k => $v) $bind[] = &$params[$k];
    call_user_func_array([$stmt, 'bind_param'], $bind);
}
mysqli_stmt_execute($stmt);
$rs = mysqli_stmt_get_result($stmt);

$rows = [];
while ($rs && ($r = mysqli_fetch_assoc($rs))) $rows[] = $r;
mysqli_stmt_close($stmt);

// Get statistics
$stats = [
    'total' => 0,
    'vacant' => 0,
    'occupied' => 0,
    'maintenance' => 0,
    'locked' => 0
];

$statsQuery = "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN room_status = 'VACANT' THEN 1 ELSE 0 END) as vacant,
                SUM(CASE WHEN room_status = 'OCCUPIED' THEN 1 ELSE 0 END) as occupied,
                SUM(CASE WHEN room_status = 'MAINTENANCE' THEN 1 ELSE 0 END) as maintenance,
                SUM(CASE WHEN room_status = 'LOCKED' THEN 1 ELSE 0 END) as locked
               FROM rooms WHERE deleted_at IS NULL";
$statsResult = mysqli_query($conn, $statsQuery);
if ($statsResult && ($stat = mysqli_fetch_assoc($statsResult))) {
    $stats['total'] = (int)$stat['total'];
    $stats['vacant'] = (int)$stat['vacant'];
    $stats['occupied'] = (int)$stat['occupied'];
    $stats['maintenance'] = (int)$stat['maintenance'];
    $stats['locked'] = (int)$stat['locked'];
}

// Get buildings for filter
$buildings = mysqli_query($conn, "SELECT building_id, building_name FROM buildings WHERE deleted_at IS NULL ORDER BY building_name");

// Helper functions
function room_status_badge(string $status): string {
    $map = [
        'VACANT' => ['Trống', 'success', 'door-open'],
        'OCCUPIED' => ['Đang thuê', 'danger', 'person-fill'],
        'MAINTENANCE' => ['Bảo trì', 'warning', 'tools'],
        'LOCKED' => ['Khóa', 'secondary', 'lock-fill'],
    ];
    $label = $map[$status][0] ?? $status;
    $cls = $map[$status][1] ?? 'secondary';
    $icon = $map[$status][2] ?? 'info-circle';
    return '<span class="badge bg-' . $cls . '"><i class="bi bi-' . $icon . ' me-1"></i>' . htmlspecialchars($label) . '</span>';
}

function publish_status_badge(string $status): string {
    $map = [
        'APPROVED' => ['Đã duyệt', 'success', 'check-circle'],
        'PENDING' => ['Chờ duyệt', 'warning', 'clock'],
        'HIDDEN' => ['Ẩn', 'secondary', 'eye-slash'],
    ];
    $label = $map[$status][0] ?? $status;
    $cls = $map[$status][1] ?? 'secondary';
    $icon = $map[$status][2] ?? 'info-circle';
    return '<span class="badge bg-' . $cls . '"><i class="bi bi-' . $icon . ' me-1"></i>' . htmlspecialchars($label) . '</span>';
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="pagetitle">
  <h1><i class="bi bi-door-closed me-2"></i>Quản lý Phòng</h1>
  <nav>
    <ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="<?= ADMIN_BASE_PATH ?>/modules/dashboard/index.php">Dashboard</a></li>
      <li class="breadcrumb-item active">Phòng</li>
    </ol>
  </nav>
</div>

<section class="section">
  
  <!-- Statistics Cards -->
  <div class="row mb-4">
    <div class="col-lg-2 col-md-4 col-sm-6">
      <div class="card stats-card">
        <div class="card-body">
          <div class="text-center">
            <div class="stats-icon mb-2">
              <i class="bi bi-door-closed-fill"></i>
            </div>
            <div class="stats-number"><?= $stats['total'] ?></div>
            <div class="stats-label">Tổng số phòng</div>
          </div>
        </div>
      </div>
    </div>
    
    <div class="col-lg-2 col-md-4 col-sm-6">
      <div class="card stats-card success">
        <div class="card-body">
          <div class="text-center">
            <div class="stats-icon mb-2">
              <i class="bi bi-door-open-fill"></i>
            </div>
            <div class="stats-number"><?= $stats['vacant'] ?></div>
            <div class="stats-label">Phòng trống</div>
          </div>
        </div>
      </div>
    </div>
    
    <div class="col-lg-2 col-md-4 col-sm-6">
      <div class="card stats-card danger">
        <div class="card-body">
          <div class="text-center">
            <div class="stats-icon mb-2">
              <i class="bi bi-person-fill"></i>
            </div>
            <div class="stats-number"><?= $stats['occupied'] ?></div>
            <div class="stats-label">Đang thuê</div>
          </div>
        </div>
      </div>
    </div>
    
    <div class="col-lg-2 col-md-4 col-sm-6">
      <div class="card stats-card warning">
        <div class="card-body">
          <div class="text-center">
            <div class="stats-icon mb-2">
              <i class="bi bi-tools"></i>
            </div>
            <div class="stats-number"><?= $stats['maintenance'] ?></div>
            <div class="stats-label">Bảo trì</div>
          </div>
        </div>
      </div>
    </div>
    
    <div class="col-lg-2 col-md-4 col-sm-6">
      <div class="card stats-card">
        <div class="card-body" style="background: linear-gradient(135deg, #6c757d, #495057);">
          <div class="text-center">
            <div class="stats-icon mb-2">
              <i class="bi bi-lock-fill"></i>
            </div>
            <div class="stats-number"><?= $stats['locked'] ?></div>
            <div class="stats-label">Đã khóa</div>
          </div>
        </div>
      </div>
    </div>
    
    <div class="col-lg-2 col-md-4 col-sm-6">
      <div class="card stats-card" style="background: linear-gradient(135deg, #0dcaf0, #0aa2c0);">
        <div class="card-body">
          <div class="text-center">
            <div class="stats-icon mb-2">
              <i class="bi bi-percent"></i>
            </div>
            <div class="stats-number">
              <?= $stats['total'] > 0 ? round(($stats['occupied'] / $stats['total']) * 100, 1) : 0 ?>%
            </div>
            <div class="stats-label">Tỷ lệ lấp đầy</div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Main Card -->
  <div class="card">
    <div class="card-header">
      <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h5 class="mb-0">Danh sách phòng</h5>
        <div class="d-flex gap-2">
          <a class="btn btn-outline-primary btn-sm" href="<?= ADMIN_BASE_PATH ?>/modules/toanha/index.php">
            <i class="bi bi-building me-1"></i>Quản lý dãy/tòa
          </a>
          <a class="btn btn-success btn-sm" href="add.php">
            <i class="bi bi-plus-circle me-1"></i>Thêm phòng
          </a>
        </div>
      </div>
    </div>
    
    <div class="card-body">
      
      <!-- Display alerts -->
      <?php display_get_alerts(); ?>
      <?= get_flash(); ?>

      <!-- Filter Section -->
      <div class="filter-section mb-4">
        <form class="row g-3" method="get">
          <div class="col-md-3">
            <label class="form-label">Tìm kiếm</label>
            <input class="form-control" name="q" placeholder="Mã phòng, tên dãy..." 
                   value="<?= htmlspecialchars($q) ?>">
          </div>
          
          <div class="col-md-3">
            <label class="form-label">Dãy/Tòa</label>
            <select class="form-select" name="building_id">
              <option value="">-- Tất cả --</option>
              <?php while ($buildings && ($bld = mysqli_fetch_assoc($buildings))): ?>
                <option value="<?= (int)$bld['building_id'] ?>" 
                        <?= $building_id === (int)$bld['building_id'] ? 'selected' : '' ?>>
                  <?= htmlspecialchars($bld['building_name']) ?>
                </option>
              <?php endwhile; ?>
            </select>
          </div>
          
          <div class="col-md-2">
            <label class="form-label">Trạng thái phòng</label>
            <select class="form-select" name="room_status">
              <option value="">-- Tất cả --</option>
              <?php 
              $roomStatuses = [
                'VACANT' => 'Trống',
                'OCCUPIED' => 'Đang thuê',
                'MAINTENANCE' => 'Bảo trì',
                'LOCKED' => 'Khóa'
              ];
              foreach ($roomStatuses as $k => $v): ?>
                <option value="<?= $k ?>" <?= $room_status === $k ? 'selected' : '' ?>><?= $v ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          
          <div class="col-md-2">
            <label class="form-label">Trạng thái duyệt</label>
            <select class="form-select" name="publish_status">
              <option value="">-- Tất cả --</option>
              <?php 
              $pubStatuses = ['APPROVED' => 'Đã duyệt', 'PENDING' => 'Chờ duyệt', 'HIDDEN' => 'Ẩn'];
              foreach ($pubStatuses as $k => $v): ?>
                <option value="<?= $k ?>" <?= $publish_status === $k ? 'selected' : '' ?>><?= $v ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          
          <div class="col-md-2 d-flex align-items-end gap-2">
            <button class="btn btn-primary" type="submit">
              <i class="bi bi-search me-1"></i>Lọc
            </button>
            <a class="btn btn-secondary" href="index.php">
              <i class="bi bi-arrow-clockwise"></i>
            </a>
          </div>
        </form>
      </div>

      <!-- Table -->
      <div class="table-responsive">
        <table class="table table-hover align-middle">
          <thead>
            <tr>
              <th style="width: 50px;">#</th>
              <th style="width: 100px;">Hình ảnh</th>
              <th>Mã phòng</th>
              <th>Dãy/Tòa</th>
              <th>Diện tích</th>
              <th>Giá thuê</th>
              <th>Trạng thái</th>
              <th>Duyệt</th>
              <th style="width: 280px;">Thao tác</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($rows)): ?>
              <tr>
                <td colspan="9">
                  <div class="empty-state">
                    <i class="bi bi-door-closed"></i>
                    <h5>Chưa có phòng nào</h5>
                    <p>Bắt đầu bằng cách thêm phòng đầu tiên.</p>
                    <a href="add.php" class="btn btn-primary">
                      <i class="bi bi-plus-circle me-1"></i>Thêm phòng
                    </a>
                  </div>
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($rows as $i => $room): ?>
                <tr>
                  <td><?= $i + 1 ?></td>
                  <td>
                    <?php if (!empty($room['image'])): 
                      $img = '/quanlyphongtro/admin/uploads/rooms/' . $room['image'];
                    ?>
                      <img src="<?= htmlspecialchars($img) ?>" class="img-preview" alt="Room">
                    <?php else: ?>
                      <div class="bg-light rounded d-flex align-items-center justify-content-center" 
                           style="width:80px;height:80px;">
                        <i class="bi bi-door-closed text-muted" style="font-size:28px;"></i>
                      </div>
                    <?php endif; ?>
                  </td>
                  <td>
                    <strong><?= htmlspecialchars($room['room_code']) ?></strong>
                  </td>
                  <td>
                    <small><?= htmlspecialchars($room['building_name']) ?></small>
                  </td>
                  <td>
                    <?php if ($room['area_m2']): ?>
                      <span class="badge bg-light text-dark">
                        <?= number_format((float)$room['area_m2'], 0) ?> m²
span>
                    <?php else: ?>
                      <span class="text-muted">-</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <strong class="text-primary">
                      <?= number_format((float)$room['base_rent'], 0) ?>đ
                    </strong>
                    <small class="text-muted d-block">/ tháng</small>
                  </td>
                  <td><?= room_status_badge($room['room_status']) ?></td>
                  <td><?= publish_status_badge($room['publish_status']) ?></td>
                  <td>
                    <div class="action-buttons">
                      <a class="btn btn-sm btn-outline-secondary" 
                         href="detail.php?room_id=<?= (int)$room['room_id'] ?>">
                        <i class="bi bi-eye me-1"></i>Xem
                      </a>
                      <a class="btn btn-sm btn-outline-primary" 
                         href="edit.php?room_id=<?= (int)$room['room_id'] ?>">
                        <i class="bi bi-pencil me-1"></i>Sửa
                      </a>
                      <?php if ($room['room_status'] === 'VACANT'): ?>
                        <a class="btn btn-sm btn-outline-success" 
                           href="<?= ADMIN_BASE_PATH ?>/modules/datphong/add.php?room_id=<?= (int)$room['room_id'] ?>">
                          <i class="bi bi-calendar-check me-1"></i>Đặt
                        </a>
                      <?php endif; ?>
                    </div>
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
