<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/alerts.php';
require_once __DIR__ . '/../../includes/form_helpers.php';
require_once __DIR__ . '/../../includes/pagination.php';

$role = (string)($_SESSION['role_name'] ?? '');
if (!in_array($role, ['ADMIN', 'STAFF'], true)) {
    admin_redirect('modules/dashboard/index.php', ['forbidden' => 1]);
}

// Get filter parameters
$q = trim((string)($_GET['q'] ?? ''));
$active = (string)($_GET['active'] ?? '');

// Build query
$sql = "SELECT u.user_id, u.full_name, u.email, u.phone, u.username, u.is_active, u.created_at
        FROM users u
        JOIN roles r ON r.role_id = u.role_id
        WHERE r.role_name = 'LANDLORD'";

$params = [];
$types = "";

if ($active === '1' || $active === '0') {
    $sql .= " AND u.is_active = ?";
    $types .= "i";
    $params[] = (int)$active;
}

if ($q !== '') {
    $like = "%{$q}%";
    $sql .= " AND (u.full_name LIKE ? OR u.username LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)";
    $types .= "ssss";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

// Count for pagination
$countSql = "SELECT COUNT(*) as cnt FROM users u JOIN roles r ON r.role_id = u.role_id WHERE r.role_name = 'LANDLORD'";
if ($active === '1' || $active === '0') {
    $countSql .= " AND u.is_active = " . (int)$active;
}
if ($q !== '') {
    $countSql .= " AND (u.full_name LIKE '%" . mysqli_real_escape_string($conn, $q) . "%')";
}
$countRow = mysqli_fetch_assoc(mysqli_query($conn, $countSql));
$totalItems = (int)($countRow['cnt'] ?? 0);

$perPage = 10;
$paging = pagination_calc($totalItems, $perPage);

$sql .= " LIMIT {$paging['offset']}, {$paging['per_page']}";

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

// Get statistics
$stats = ['total' => 0, 'active' => 0, 'locked' => 0];
$statsQuery = "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN u.is_active = 1 THEN 1 ELSE 0 END) as active,
                SUM(CASE WHEN u.is_active = 0 THEN 1 ELSE 0 END) as locked
               FROM users u
               JOIN roles r ON r.role_id = u.role_id
               WHERE r.role_name = 'LANDLORD'";
$statsResult = mysqli_query($conn, $statsQuery);
if ($statsResult && ($stat = mysqli_fetch_assoc($statsResult))) {
    $stats['total'] = (int)$stat['total'];
    $stats['active'] = (int)$stat['active'];
    $stats['locked'] = (int)$stat['locked'];
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="pagetitle">
  <h1><i class="bi bi-people me-2"></i>Quản lý Chủ trọ</h1>
  <nav>
    <ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="<?= ADMIN_BASE_PATH ?>/modules/dashboard/index.php">Dashboard</a></li>
      <li class="breadcrumb-item active">Chủ trọ</li>
    </ol>
  </nav>
</div>

<section class="section">

<!-- Statistics Cards -->
<div class="row mb-4">
  <div class="col">
    <a href="?" class="card border-0 shadow-sm text-center py-3 text-decoration-none <?= $active === '' ? 'border-primary border-2' : '' ?>">
      <div class="fs-2 fw-bold text-primary"><?= $stats['total'] ?></div>
      <div class="text-muted small">Tất cả</div>
    </a>
  </div>
  <div class="col">
    <a href="?active=1" class="card border-0 shadow-sm text-center py-3 text-decoration-none <?= $active === '1' ? 'border-success border-2' : '' ?>">
      <div class="fs-2 fw-bold text-success"><?= $stats['active'] ?></div>
      <div class="text-muted small">Đang hoạt động</div>
    </a>
  </div>
  <div class="col">
    <a href="?active=0" class="card border-0 shadow-sm text-center py-3 text-decoration-none <?= $active === '0' ? 'border-danger border-2' : '' ?>">
      <div class="fs-2 fw-bold text-danger"><?= $stats['locked'] ?></div>
      <div class="text-muted small">Đã khóa</div>
    </a>
  </div>
</div>

<!-- Filter -->
<div class="card mb-4">
  <div class="card-body py-3">
    <form class="row g-3 align-items-center" method="get">
      <div class="col-md-3">
        <select class="form-select" name="active">
          <option value="">-- Tất cả trạng thái --</option>
          <option value="1" <?= $active === '1' ? 'selected' : '' ?>>Đang hoạt động</option>
          <option value="0" <?= $active === '0' ? 'selected' : '' ?>>Đã khóa</option>
        </select>
      </div>
      <div class="col-md-6">
        <input class="form-control" name="q" placeholder="Tìm theo tên, email, SĐT..." value="<?= htmlspecialchars($q) ?>">
      </div>
      <div class="col-md-3">
        <button class="btn btn-primary w-100" type="submit"><i class="bi bi-search me-1"></i>Lọc</button>
      </div>
    </form>
  </div>
</div>

<!-- Table -->
<div class="card">
  <div class="card-header d-flex justify-content-between align-items-center">
    <h5 class="mb-0">Danh sách chủ trọ</h5>
    <a class="btn btn-success btn-sm" href="add.php"><i class="bi bi-plus-circle me-1"></i>Thêm chủ trọ</a>
  </div>
  <div class="card-body">
    <?php display_get_alerts(); ?>
    <?= get_flash(); ?>

    <div class="table-responsive">
      <table class="table table-hover align-middle">
        <thead class="table-light">
          <tr>
            <th>#</th>
            <th>Họ tên</th>
            <th>Username</th>
            <th>Email</th>
            <th>SĐT</th>
            <th>Trạng thái</th>
            <th>Ngày tạo</th>
            <th>Hành động</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($rows)): ?>
            <tr><td colspan="8" class="text-center text-muted py-4">Chưa có chủ trọ nào</td></tr>
          <?php else: ?>
            <?php foreach ($rows as $i => $u): ?>
              <tr>
                <td><?= $i + 1 ?></td>
                <td><strong><?= htmlspecialchars((string)$u['full_name']) ?></strong></td>
                <td><span class="badge bg-light text-dark"><?= htmlspecialchars((string)$u['username']) ?></span></td>
                <td><?= htmlspecialchars((string)$u['email']) ?></td>
                <td><?= htmlspecialchars((string)$u['phone']) ?></td>
                <td>
                  <?php if ((int)$u['is_active'] === 1): ?>
                    <span class="badge bg-success">Hoạt động</span>
                  <?php else: ?>
                    <span class="badge bg-secondary">Đã khóa</span>
                  <?php endif; ?>
                </td>
                <td><small class="text-muted"><?= date('d/m/Y', strtotime($u['created_at'])) ?></small></td>
                <td>
                  <div class="btn-group" role="group">
                    <a class="btn btn-sm btn-outline-info" href="detail.php?user_id=<?= (int)$u['user_id'] ?>" title="Xem"><i class="bi bi-eye"></i></a>
                    <a class="btn btn-sm btn-outline-primary" href="edit.php?user_id=<?= (int)$u['user_id'] ?>" title="Sửa"><i class="bi bi-pencil"></i></a>
                    <?php if ((int)$u['is_active'] === 1): ?>
                    <a class="btn btn-sm btn-outline-warning" href="toggle.php?user_id=<?= (int)$u['user_id'] ?>" onclick="return confirm('Khóa tài khoản này?')" title="Khóa"><i class="bi bi-lock"></i></a>
                    <?php else: ?>
                    <a class="btn btn-sm btn-outline-success" href="toggle.php?user_id=<?= (int)$u['user_id'] ?>" onclick="return confirm('Mở khóa tài khoản này?')" title="Mở khóa"><i class="bi bi-unlock"></i></a>
                    <?php endif; ?>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
    <?php if (!empty($rows)): ?>
        <?php pagination_render($paging['current_page'], $paging['total_pages'], $paging['total_items'], $paging['per_page']); ?>
    <?php endif; ?>
  </div>
</div>

</section>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
