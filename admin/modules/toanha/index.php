<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/alerts.php';
require_once __DIR__ . '/../../includes/form_helpers.php';

$role   = (string)($_SESSION['role_name'] ?? '');
$userId = (int)($_SESSION['user_id'] ?? 0);

$isAdmin    = in_array($role, ['ADMIN', 'STAFF'], true);
$isLandlord = ($role === 'LANDLORD');

if (!$isAdmin && !$isLandlord) {
    admin_redirect('modules/dashboard/index.php', ['forbidden' => 1]);
}

function badge_building_status(string $st): string {
    $st = strtoupper(trim($st));
    $map = [
        'APPROVED' => ['Đã duyệt', 'success', 'check-circle'],
        'PENDING' => ['Chờ duyệt', 'warning', 'clock'],
        'HIDDEN' => ['Ẩn', 'secondary', 'eye-slash'],
    ];
    $label = $map[$st][0] ?? $st;
    $cls   = $map[$st][1] ?? 'secondary';
    $icon  = $map[$st][2] ?? 'info-circle';
    return '<span class="badge bg-' . $cls . '"><i class="bi bi-' . $icon . ' me-1"></i>' . htmlspecialchars($label) . '</span>';
}

// Filter parameters
$q = trim((string)($_GET['q'] ?? ''));
$status = (string)($_GET['building_status'] ?? '');

// Build query
$sql = "SELECT b.*, u.full_name as owner_name,
        (SELECT COUNT(*) FROM rooms r WHERE r.building_id = b.building_id AND r.deleted_at IS NULL) as room_count
        FROM buildings b
        LEFT JOIN users u ON u.user_id = b.owner_user_id
        WHERE b.deleted_at IS NULL";

$params = [];
$types = "";

if ($isLandlord) {
    $sql .= " AND b.owner_user_id = ?";
    $types .= "i";
    $params[] = $userId;
}

if ($status !== '' && in_array($status, ['PENDING','APPROVED','HIDDEN'], true)) {
    $sql .= " AND b.building_status = ?";
    $types .= "s";
    $params[] = $status;
}

if ($q !== '') {
    $like = "%{$q}%";
    $sql .= " AND (b.building_code LIKE ? OR b.building_name LIKE ? OR b.address LIKE ? OR u.full_name LIKE ?)";
    $types .= "ssss";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

$sql .= " ORDER BY b.building_id DESC";

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
$stats = ['total' => 0, 'approved' => 0, 'pending' => 0, 'hidden' => 0];
$statsWhere = "deleted_at IS NULL" . ($isLandlord ? " AND owner_user_id = $userId" : "");
$statsQuery = "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN building_status = 'APPROVED' THEN 1 ELSE 0 END) as approved,
                SUM(CASE WHEN building_status = 'PENDING' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN building_status = 'HIDDEN' THEN 1 ELSE 0 END) as hidden
               FROM buildings WHERE $statsWhere";
$statsResult = mysqli_query($conn, $statsQuery);
if ($statsResult && ($stat = mysqli_fetch_assoc($statsResult))) {
    $stats['total'] = (int)$stat['total'];
    $stats['approved'] = (int)$stat['approved'];
    $stats['pending'] = (int)$stat['pending'];
    $stats['hidden'] = (int)$stat['hidden'];
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="pagetitle">
  <h1><i class="bi bi-building me-2"></i>Quản lý Dãy / Tòa</h1>
  <nav>
    <ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="<?= ADMIN_BASE_PATH ?>/modules/dashboard/index.php">Dashboard</a></li>
      <li class="breadcrumb-item active">Dãy / Tòa</li>
    </ol>
  </nav>
</div>

<section class="section">
  
  <!-- Statistics Cards -->
  <div class="row mb-4">
    <div class="col-lg-3 col-md-6">
      <div class="card stats-card">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-center">
            <div>
              <div class="stats-label">Tổng số dãy/tòa</div>
              <div class="stats-number"><?= $stats['total'] ?></div>
            </div>
            <div class="stats-icon">
              <i class="bi bi-building"></i>
            </div>
          </div>
        </div>
      </div>
    </div>
    
    <div class="col-lg-3 col-md-6">
      <div class="card stats-card success">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-center">
            <div>
              <div class="stats-label">Đã duyệt</div>
              <div class="stats-number"><?= $stats['approved'] ?></div>
            </div>
            <div class="stats-icon">
              <i class="bi bi-check-circle-fill"></i>
            </div>
          </div>
        </div>
      </div>
    </div>
    
    <div class="col-lg-3 col-md-6">
      <div class="card stats-card warning">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-center">
            <div>
              <div class="stats-label">Chờ duyệt</div>
              <div class="stats-number"><?= $stats['pending'] ?></div>
            </div>
            <div class="stats-icon">
              <i class="bi bi-clock-fill"></i>
            </div>
          </div>
        </div>
      </div>
    </div>
    
    <div class="col-lg-3 col-md-6">
      <div class="card stats-card">
        <div class="card-body" style="background: linear-gradient(135deg, #6c757d, #495057);">
          <div class="d-flex justify-content-between align-items-center">
            <div>
              <div class="stats-label">Đã ẩn</div>
              <div class="stats-number"><?= $stats['hidden'] ?></div>
            </div>
            <div class="stats-icon">
              <i class="bi bi-eye-slash-fill"></i>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Main Card -->
  <div class="card">
    <div class="card-header">
      <div class="d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Danh sách dãy/tòa nhà</h5>
        <a class="btn btn-success btn-sm" href="add.php">
          <i class="bi bi-plus-circle me-1"></i>Thêm dãy/tòa
        </a>
      </div>
    </div>
    
    <div class="card-body">
      
      <!-- Display alerts -->
      <?php display_get_alerts(); ?>
      <?= get_flash(); ?>

      <!-- Filter Section -->
      <div class="filter-section mb-4">
        <form class="row g-3" method="get">
          <div class="col-md-5">
            <label class="form-label">Tìm kiếm</label>
            <input class="form-control" name="q" placeholder="Mã, tên, địa chỉ, chủ trọ..." 
                   value="<?= htmlspecialchars($q) ?>">
          </div>
          <div class="col-md-3">
            <label class="form-label">Trạng thái</label>
            <select class="form-select" name="building_status">
              <option value="">-- Tất cả --</option>
              <?php foreach (['APPROVED'=>'Đã duyệt','PENDING'=>'Chờ duyệt','HIDDEN'=>'Ẩn'] as $k=>$v): ?>
                <option value="<?= $k ?>" <?= $status === $k ? 'selected' : '' ?>><?= $v ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-4 d-flex align-items-end gap-2">
            <button class="btn btn-primary" type="submit">
              <i class="bi bi-search me-1"></i>Lọc
            </button>
            <a class="btn btn-secondary" href="index.php">
              <i class="bi bi-arrow-clockwise me-1"></i>Reset
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
              <th>Mã</th>
              <th>Tên dãy/tòa</th>
              <th>Địa chỉ</th>
              <?php if ($isAdmin): ?><th>Chủ trọ</th><?php endif; ?>
              <th>Số phòng</th>
              <th>Trạng thái</th>
              <th style="width: 300px;">Thao tác</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($rows)): ?>
              <tr>
                <td colspan="<?= $isAdmin ? 9 : 8 ?>">
                  <div class="empty-state">
                    <i class="bi bi-building"></i>
                    <h5>Chưa có dãy/tòa nào</h5>
                    <p>Bắt đầu bằng cách thêm dãy/tòa đầu tiên.</p>
                    <a href="add.php" class="btn btn-primary">
                      <i class="bi bi-plus-circle me-1"></i>Thêm dãy/tòa
                    </a>
                  </div>
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($rows as $i => $b): ?>
                <tr>
                  <td><?= $i + 1 ?></td>
                  <td>
                    <?php if (!empty($b['thumbnail'])): 
                      $thumb = '/quanlyphongtro/admin/uploads/buildings/' . $b['thumbnail'];
                    ?>
                      <img src="<?= htmlspecialchars($thumb) ?>" class="img-preview" alt="Building">
                    <?php else: ?>
                      <div class="bg-light rounded d-flex align-items-center justify-content-center" 
                           style="width:80px;height:80px;">
                        <i class="bi bi-building text-muted" style="font-size:32px;"></i>
                      </div>
                    <?php endif; ?>
                  </td>
                  <td>
                    <span class="badge bg-light text-dark">
                      <?= htmlspecialchars((string)$b['building_code']) ?>
                    </span>
                  </td>
                  <td><strong><?= htmlspecialchars((string)$b['building_name']) ?></strong></td>
                  <td>
                    <small>
                      <i class="bi bi-geo-alt me-1"></i><?= htmlspecialchars((string)($b['address'] ?? '-')) ?>
                    </small>
                  </td>
                  <?php if ($isAdmin): ?>
                    <td><?= htmlspecialchars((string)($b['owner_name'] ?? '-')) ?></td>
                  <?php endif; ?>
                  <td>
                    <span class="badge bg-info">
                      <i class="bi bi-door-closed me-1"></i><?= (int)$b['room_count'] ?> phòng
                    </span>
                  </td>
                  <td><?= badge_building_status((string)($b['building_status'] ?? '')) ?></td>
                  <td>
                    <div class="action-buttons">
                      <?php if ($isLandlord): ?>
                        <a class="btn btn-sm btn-outline-secondary"
                           href="<?= ADMIN_BASE_PATH ?>/modules/rooms_owner/index.php?building_id=<?= (int)$b['building_id'] ?>">
                          <i class="bi bi-door-closed me-1"></i>Phòng
                        </a>
                      <?php else: ?>
                        <a class="btn btn-sm btn-outline-secondary"
                           href="<?= ADMIN_BASE_PATH ?>/modules/phong/index.php?building_id=<?= (int)$b['building_id'] ?>">
                          <i class="bi bi-door-closed me-1"></i>Phòng
                        </a>
                      <?php endif; ?>

                      <a class="btn btn-sm btn-outline-primary"
                         href="edit.php?building_id=<?= (int)$b['building_id'] ?>">
                        <i class="bi bi-pencil me-1"></i>Sửa
                      </a>
                      
                      <?php if ($isAdmin && $b['building_status'] === 'PENDING'): ?>
                        <a class="btn btn-sm btn-outline-success"
                           href="approve.php?building_id=<?= (int)$b['building_id'] ?>"
                           data-confirm="Duyệt dãy/tòa này?">
                          <i class="bi bi-check-circle me-1"></i>Duyệt
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
