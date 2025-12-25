<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';

$role = (string)($_SESSION['role_name'] ?? '');
if ($role !== 'ADMIN') {
    header('Location: ' . ADMIN_BASE_PATH . '/modules/dashboard/index.php');
    exit;
}

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: index.php');
    exit;
}

// Get package
$pkg = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM packages WHERE package_id = $id"));
if (!$pkg) {
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['package_name'] ?? '');
    $pricePerDay = (float)($_POST['price_per_day'] ?? 0);
    $priority = (int)($_POST['priority'] ?? 0);
    $highlightColor = trim($_POST['highlight_color'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $isActive = isset($_POST['is_active']) ? 1 : 0;
    
    if ($name === '') {
        $error = 'Vui lòng nhập tên gói.';
    } elseif ($pricePerDay <= 0) {
        $error = 'Giá mỗi ngày phải lớn hơn 0.';
    } else {
        $stmt = mysqli_prepare($conn, "
            UPDATE packages SET 
                package_name = ?, 
                price_per_day = ?, 
                highlight_color = ?, 
                priority = ?, 
                is_active = ?, 
                description = ?
            WHERE package_id = ?
        ");
        mysqli_stmt_bind_param($stmt, 'sdsiisd', $name, $pricePerDay, $highlightColor, $priority, $isActive, $description, $id);
        
        if (mysqli_stmt_execute($stmt)) {
            header('Location: index.php?msg=updated');
            exit;
        } else {
            $error = 'Có lỗi xảy ra, vui lòng thử lại.';
        }
    }
} else {
    $_POST = $pkg;
}

// Count usage
$usageCount = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM posts WHERE package_id = $id"))['c'] ?? 0;

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="pagetitle">
  <h1><i class="bi bi-pencil me-2"></i>Sửa gói đăng tin</h1>
  <nav>
    <ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="<?= ADMIN_BASE_PATH ?>/modules/dashboard/index.php">Dashboard</a></li>
      <li class="breadcrumb-item"><a href="index.php">Gói đăng tin</a></li>
      <li class="breadcrumb-item active">Sửa</li>
    </ol>
  </nav>
</div>

<section class="section">
  <div class="row">
    <div class="col-lg-8">
      <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h5 class="mb-0">Thông tin gói: <?= htmlspecialchars($pkg['package_name']) ?></h5>
          <span class="badge bg-info"><?= $usageCount ?> tin đang dùng</span>
        </div>
        <div class="card-body pt-4">
          
          <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
          <?php endif; ?>
          
          <form method="post">
            <div class="row mb-3">
              <div class="col-md-8">
                <label class="form-label">Tên gói <span class="text-danger">*</span></label>
                <input type="text" name="package_name" class="form-control" required
                       value="<?= htmlspecialchars($_POST['package_name'] ?? '') ?>"
                       placeholder="VD: VIP Nổi Bật, VIP 1, Tin thường...">
              </div>
              <div class="col-md-4">
                <label class="form-label">Giá / ngày (đ) <span class="text-danger">*</span></label>
                <input type="number" name="price_per_day" class="form-control" required min="1000" step="1000"
                       value="<?= htmlspecialchars($_POST['price_per_day'] ?? '') ?>">
              </div>
            </div>
            
            <div class="row mb-3">
              <div class="col-md-4">
                <label class="form-label">Độ ưu tiên</label>
                <input type="number" name="priority" class="form-control" min="0" max="1000"
                       value="<?= htmlspecialchars($_POST['priority'] ?? '0') ?>">
                <small class="text-muted">Số càng cao, hiển thị càng trước</small>
              </div>
              <div class="col-md-4">
                <label class="form-label">Trạng thái</label>
                <div class="form-check form-switch mt-2">
                  <input class="form-check-input" type="checkbox" name="is_active" id="is_active"
                         <?= ($_POST['is_active'] ?? false) ? 'checked' : '' ?>>
                  <label class="form-check-label" for="is_active">Kích hoạt</label>
                </div>
              </div>
            </div>
            
            <div class="mb-3">
              <label class="form-label">Mô tả</label>
              <textarea name="description" class="form-control" rows="3"
                        placeholder="Mô tả ngắn về gói đăng tin..."><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
            </div>
            
            <div class="d-flex gap-2">
              <button type="submit" class="btn btn-primary">
                <i class="bi bi-check-circle me-1"></i>Cập nhật
              </button>
              <a href="index.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left me-1"></i>Quay lại
              </a>
            </div>
          </form>
          
        </div>
      </div>
    </div>
    
    <!-- Info -->
    <div class="col-lg-4">
      <div class="card" style="border-left: 4px solid <?= $pkg['highlight_color'] ?: '#6c757d' ?>;">
        <div class="card-header">
          <h5 class="mb-0" style="color: <?= $pkg['highlight_color'] ?: '#333' ?>;">
            <?= htmlspecialchars($pkg['package_name']) ?>
          </h5>
        </div>
        <div class="card-body">
          <div class="text-center mb-3">
            <span class="fs-3 fw-bold text-danger">
              <?= number_format((float)$pkg['price_per_day'], 0, ',', '.') ?>đ
            </span>
            <span class="text-muted">/ngày</span>
          </div>
          <ul class="list-unstyled small">
            <li class="mb-2"><i class="bi bi-lightning-charge text-warning me-2"></i>Ưu tiên: <strong><?= $pkg['priority'] ?></strong></li>
            <li class="mb-2"><i class="bi bi-file-earmark-text text-primary me-2"></i>Đang dùng: <strong><?= $usageCount ?></strong> tin</li>
          </ul>
        </div>
      </div>
      
      <?php if ($usageCount > 0): ?>
      <div class="alert alert-info mb-3">
        <h6 class="alert-heading"><i class="bi bi-info-circle me-2"></i>Thông tin quan trọng</h6>
        <p class="mb-2"><strong><?= $usageCount ?></strong> tin đang sử dụng gói này.</p>
        <hr>
        <p class="mb-2"><strong>Khi thay đổi GIÁ:</strong></p>
        <ul class="mb-2 small">
          <li>Giá mới chỉ áp dụng cho tin đăng <strong>mới</strong></li>
          <li>Các tin đã đăng giữ nguyên phí đã thanh toán</li>
        </ul>
        <p class="mb-2"><strong>Khi TẮT gói:</strong></p>
        <ul class="mb-0 small">
          <li>Các tin đang hoạt động vẫn hiển thị đến hết hạn</li>
          <li>Chủ trọ không thể đăng mới/gia hạn với gói này</li>
        </ul>
      </div>
      <?php endif; ?>
    </div>
  </div>
</section>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
