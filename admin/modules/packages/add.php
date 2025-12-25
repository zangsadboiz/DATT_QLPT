<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';

$role = (string)($_SESSION['role_name'] ?? '');
if ($role !== 'ADMIN') {
    header('Location: ' . ADMIN_BASE_PATH . '/modules/dashboard/index.php');
    exit;
}

$error = '';
$success = false;

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
            INSERT INTO packages (package_name, price_per_day, highlight_color, priority, is_active, description)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        mysqli_stmt_bind_param($stmt, 'sdsiis', $name, $pricePerDay, $highlightColor, $priority, $isActive, $description);
        
        if (mysqli_stmt_execute($stmt)) {
            header('Location: index.php?msg=created');
            exit;
        } else {
            $error = 'Có lỗi xảy ra, vui lòng thử lại.';
        }
    }
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="pagetitle">
  <h1><i class="bi bi-plus-circle me-2"></i>Thêm gói đăng tin</h1>
  <nav>
    <ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="<?= ADMIN_BASE_PATH ?>/modules/dashboard/index.php">Dashboard</a></li>
      <li class="breadcrumb-item"><a href="index.php">Gói đăng tin</a></li>
      <li class="breadcrumb-item active">Thêm mới</li>
    </ol>
  </nav>
</div>

<section class="section">
  <div class="row">
    <div class="col-lg-8">
      <div class="card">
        <div class="card-header">
          <h5 class="mb-0">Thông tin gói đăng tin</h5>
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
                       value="<?= htmlspecialchars($_POST['price_per_day'] ?? '10000') ?>"
                       placeholder="VD: 50000">
              </div>
            </div>
            
            <div class="row mb-3">
              <div class="col-md-6">
                <label class="form-label">Độ ưu tiên</label>
                <input type="number" name="priority" class="form-control" min="0" max="1000"
                       value="<?= htmlspecialchars($_POST['priority'] ?? '0') ?>">
                <small class="text-muted">Số càng cao, hiển thị càng trước</small>
              </div>
              <div class="col-md-6">
                <label class="form-label">Trạng thái</label>
                <div class="form-check form-switch mt-2">
                  <input class="form-check-input" type="checkbox" name="is_active" id="is_active"
                         <?= ($_POST['is_active'] ?? true) ? 'checked' : '' ?>>
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
              <button type="submit" class="btn btn-success">
                <i class="bi bi-check-circle me-1"></i>Lưu gói
              </button>
              <a href="index.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left me-1"></i>Quay lại
              </a>
            </div>
          </form>
          
        </div>
      </div>
    </div>
    
    <!-- Preview -->
    <div class="col-lg-4">
      <div class="card">
        <div class="card-header bg-primary text-white">
          <h5 class="mb-0">Xem trước</h5>
        </div>
        <div class="card-body">
          <p class="text-muted small">Gói đăng tin sẽ hiển thị cho chủ trọ khi đăng tin mới.</p>
          <div class="border rounded p-3" style="border-left: 4px solid #e74c3c !important;">
            <strong style="color: #e74c3c;">VIP Nổi Bật</strong><br>
            <small>50.000đ/ngày</small>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
