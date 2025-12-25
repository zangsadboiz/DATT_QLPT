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
  <h1><i class="bi bi-file-earmark-text me-2"></i>Quản lý Hợp đồng</h1>
  <nav>
    <ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="<?= ADMIN_BASE_PATH ?>/modules/dashboard/index.php">Dashboard</a></li>
      <li class="breadcrumb-item active">Hợp đồng</li>
    </ol>
  </nav>
</div>

<section class="section">
  <div class="alert alert-info">
    <h5><i class="bi bi-info-circle me-2"></i>Tính năng đang phát triển</h5>
    <p class="mb-0">Module quản lý hợp đồng thuê phòng sẽ được hoàn thiện trong phiên bản tiếp theo.</p>
    <hr>
    <p class="mb-0"><strong>Các tính năng dự kiến:</strong></p>
    <ul class="mb-0">
      <li>Tạo hợp đồng thuê mới</li>
      <li>In hợp đồng PDF</li>
      <li>Quản lý thời hạn hợp đồng</li>
      <li>Gia hạn / Thanh lý hợp đồng</li>
    </ul>
  </div>
</section>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
