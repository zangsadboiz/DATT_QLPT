<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/alerts.php';
require_once __DIR__ . '/../../includes/form_helpers.php';

$role = (string)($_SESSION['role_name'] ?? '');
if ($role !== 'ADMIN') {
    admin_redirect('modules/dashboard/index.php', ['forbidden' => 1]);
}

// Handle toggle active
if (isset($_GET['toggle'])) {
    $id = (int)$_GET['toggle'];
    $pkg = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM packages WHERE package_id = $id"));
    if ($pkg) {
        mysqli_query($conn, "UPDATE packages SET is_active = NOT is_active WHERE package_id = $id");
        $action = $pkg['is_active'] ? 'disabled' : 'enabled';
        header('Location: index.php?msg=toggled&action=' . $action);
        exit;
    }
    header('Location: index.php');
    exit;
}

// Filter params
$filterActive = (string)($_GET['active'] ?? '');
$filterQ = trim((string)($_GET['q'] ?? ''));

// Build query with filter
$sql = "SELECT * FROM packages WHERE 1=1";
if ($filterActive === '1') $sql .= " AND is_active = 1";
if ($filterActive === '0') $sql .= " AND is_active = 0";
if ($filterQ !== '') $sql .= " AND package_name LIKE '%" . mysqli_real_escape_string($conn, $filterQ) . "%'";
$sql .= " ORDER BY priority DESC, package_id ASC";
$packages = mysqli_query($conn, $sql);

// Get stats
$stats = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active,
        SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) as inactive
    FROM packages
"));

// Get usage count for each package
function getPackageUsage(mysqli $conn, int $packageId): int {
    $rs = mysqli_query($conn, "SELECT COUNT(*) as c FROM posts WHERE package_id = $packageId");
    return $rs ? (int)mysqli_fetch_assoc($rs)['c'] : 0;
}

// Get count of ACTIVE posts using this package
function getActivePostsCount(mysqli $conn, int $packageId): int {
    $rs = mysqli_query($conn, "SELECT COUNT(*) as c FROM posts WHERE package_id = $packageId AND status IN ('PENDING', 'APPROVED')");
    return $rs ? (int)mysqli_fetch_assoc($rs)['c'] : 0;
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="pagetitle">
  <h1><i class="bi bi-box-seam me-2"></i>Quản lý Gói đăng tin</h1>
  <nav>
    <ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="<?= ADMIN_BASE_PATH ?>/modules/dashboard/index.php">Dashboard</a></li>
      <li class="breadcrumb-item active">Gói đăng tin</li>
    </ol>
  </nav>
</div>

<section class="section">

<!-- Statistics -->
<div class="row mb-4">
  <div class="col">
    <div class="card border-0 shadow-sm text-center py-3">
      <div class="fs-2 fw-bold text-primary"><?= $stats['total'] ?></div>
      <div class="text-muted small">Tổng số gói</div>
    </div>
  </div>
  <div class="col">
    <div class="card border-0 shadow-sm text-center py-3">
      <div class="fs-2 fw-bold text-success"><?= $stats['active'] ?></div>
      <div class="text-muted small">Đang hoạt động</div>
    </div>
  </div>
  <div class="col">
    <div class="card border-0 shadow-sm text-center py-3">
      <div class="fs-2 fw-bold text-secondary"><?= $stats['inactive'] ?></div>
      <div class="text-muted small">Đã tắt</div>
    </div>
  </div>
</div>

<?php if (isset($_GET['msg']) && $_GET['msg'] === 'toggled'): ?>
  <?php $action = $_GET['action'] ?? ''; ?>
  <div class="alert alert-<?= $action === 'disabled' ? 'warning' : 'success' ?> alert-dismissible fade show">
    <?php if ($action === 'disabled'): ?>
      <i class="bi bi-pause-circle me-2"></i><strong>Đã TẮT gói!</strong> Các tin đang sử dụng vẫn hoạt động đến khi hết hạn.
    <?php else: ?>
      <i class="bi bi-play-circle me-2"></i><strong>Đã BẬT gói!</strong> Chủ trọ có thể chọn gói này khi đăng tin.
    <?php endif; ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
<?php endif; ?>

<!-- Filter -->
<div class="card mb-4">
  <div class="card-body py-3">
    <form class="row g-3 align-items-center" method="get">
      <div class="col-md-3">
        <select class="form-select" name="active">
          <option value="">-- Tất cả trạng thái --</option>
          <option value="1" <?= ($_GET['active'] ?? '') === '1' ? 'selected' : '' ?>>Đang hoạt động</option>
          <option value="0" <?= ($_GET['active'] ?? '') === '0' ? 'selected' : '' ?>>Đã tắt</option>
        </select>
      </div>
      <div class="col-md-6">
        <input class="form-control" name="q" placeholder="Tìm theo tên gói..." value="<?= htmlspecialchars($_GET['q'] ?? '') ?>">
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
    <h5 class="mb-0">Danh sách gói đăng tin</h5>
    <a href="add.php" class="btn btn-success btn-sm"><i class="bi bi-plus-circle me-1"></i>Thêm gói</a>
  </div>
  <div class="card-body">
    <div class="table-responsive">
      <table class="table table-hover align-middle">
        <thead class="table-light">
          <tr>
            <th>Tên gói</th>
            <th class="text-end">Giá/ngày</th>
            <th class="text-center">Ưu tiên</th>
            <th class="text-center">Tin đang dùng</th>
            <th class="text-center">Trạng thái</th>
            <th class="text-center">Hành động</th>
          </tr>
        </thead>
        <tbody>
          <?php while ($pkg = mysqli_fetch_assoc($packages)): ?>
            <?php 
              $usage = getPackageUsage($conn, (int)$pkg['package_id']); 
              $activePosts = getActivePostsCount($conn, (int)$pkg['package_id']);
            ?>
            <tr class="<?= !$pkg['is_active'] ? 'table-secondary' : '' ?>">
              <td>
                <strong><?= htmlspecialchars($pkg['package_name']) ?></strong>
                <?php if ($pkg['description']): ?>
                  <br><small class="text-muted"><?= htmlspecialchars($pkg['description']) ?></small>
                <?php endif; ?>
              </td>
              <td class="text-end">
                <span class="fw-bold text-danger"><?= number_format((float)$pkg['price_per_day'], 0, ',', '.') ?>đ</span>
              </td>
              <td class="text-center">
                <span class="badge bg-light text-dark"><?= (int)$pkg['priority'] ?></span>
              </td>
              <td class="text-center">
                <?php if ($usage > 0): ?>
                  <span class="badge bg-info"><?= $usage ?> tin</span>
                <?php else: ?>
                  <span class="text-muted">—</span>
                <?php endif; ?>
              </td>
              <td class="text-center">
                <?php if ($pkg['is_active']): ?>
                  <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Hoạt động</span>
                <?php else: ?>
                  <span class="badge bg-secondary"><i class="bi bi-pause-circle me-1"></i>Đã tắt</span>
                <?php endif; ?>
              </td>
              <td class="text-center">
                <div class="btn-group" role="group">
                  <a href="edit.php?id=<?= $pkg['package_id'] ?>" class="btn btn-sm btn-outline-primary" title="Sửa">
                    <i class="bi bi-pencil"></i>
                  </a>
                  <?php if ($pkg['is_active'] && $activePosts > 0): ?>
                  <a href="index.php?toggle=<?= $pkg['package_id'] ?>" 
                     class="btn btn-sm btn-outline-warning"
                     onclick="return confirm('⚠️ Gói này đang có <?= $activePosts ?> tin hoạt động!\n\nKhi tắt: Tin đã đăng vẫn hiển thị đến hết hạn, nhưng không thể đăng mới.\n\nBạn có chắc muốn TẮT?')"
                     title="Tắt">
                    <i class="bi bi-pause"></i>
                  </a>
                  <?php elseif ($pkg['is_active']): ?>
                  <a href="index.php?toggle=<?= $pkg['package_id'] ?>" 
                     class="btn btn-sm btn-outline-warning"
                     onclick="return confirm('Tắt gói này?')"
                     title="Tắt">
                    <i class="bi bi-pause"></i>
                  </a>
                  <?php else: ?>
                  <a href="index.php?toggle=<?= $pkg['package_id'] ?>" 
                     class="btn btn-sm btn-outline-success"
                     onclick="return confirm('Bật gói này?')"
                     title="Bật">
                    <i class="bi bi-play"></i>
                  </a>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

</section>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
