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

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="pagetitle">
  <h1><i class="bi bi-receipt me-2"></i>Quản lý Hóa đơn</h1>
  <nav>
    <ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="<?= ADMIN_BASE_PATH ?>/modules/dashboard/index.php">Dashboard</a></li>
      <li class="breadcrumb-item active">Hóa đơn</li>
    </ol>
  </nav>
</div>

<section class="section">
  <div class="alert alert-info">
    <h5><i class="bi bi-info-circle me-2"></i>Tính năng đang phát triển</h5>
    <p class="mb-0">Module quản lý hóa đơn sẽ được hoàn thiện trong phiên bản tiếp theo.</p>
    <hr>
    <p class="mb-0"><strong>Các tính năng dự kiến:</strong></p>
    <ul class="mb-0">
      <li>Tạo hóa đơn tự động từ phiếu thu</li>
      <li>In hóa đơn PDF</li>
      <li>Gửi hóa đơn qua email/Zalo</li>
    </ul>
  </div>
  
  <div class="card">
    <div class="card-body pt-3">
      <p><i class="bi bi-lightbulb text-warning me-2"></i>
        <strong>Gợi ý:</strong> Bạn có thể sử dụng chức năng 
        <a href="<?= ADMIN_BASE_PATH ?>/modules/payments/index.php">Thu tiền phòng</a> 
        để quản lý thanh toán hàng tháng.
      </p>
    </div>
  </div>
</section>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
