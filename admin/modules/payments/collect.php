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

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: index.php');
    exit;
}

// Get payment with authorization check
$payment = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT rp.*, r.room_code, b.building_name, t.full_name as tenant_name, t.phone as tenant_phone
    FROM rental_payments rp
    JOIN rooms r ON r.room_id = rp.room_id
    JOIN buildings b ON b.building_id = r.building_id
    LEFT JOIN tenants t ON t.tenant_id = rp.tenant_id
    WHERE rp.payment_id = $id AND b.owner_id = $userId
"));

if (!$payment) {
    header('Location: index.php');
    exit;
}

// Handle collect payment
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $paidAmount = (float)($_POST['paid_amount'] ?? 0);
    $paymentMethod = trim($_POST['payment_method'] ?? '');
    $paidDate = $_POST['paid_date'] ?? date('Y-m-d');
    
    if ($paidAmount <= 0) {
        $error = 'Số tiền thu phải lớn hơn 0.';
    } else {
        $newPaidAmount = (float)$payment['paid_amount'] + $paidAmount;
        $newStatus = 'PARTIAL';
        if ($newPaidAmount >= (float)$payment['total_amount']) {
            $newStatus = 'PAID';
            $newPaidAmount = (float)$payment['total_amount'];
        }
        
        $stmt = mysqli_prepare($conn, "
            UPDATE rental_payments 
            SET paid_amount = ?, paid_date = ?, payment_method = ?, status = ?
            WHERE payment_id = ?
        ");
        mysqli_stmt_bind_param($stmt, 'dsssi', $newPaidAmount, $paidDate, $paymentMethod, $newStatus, $id);
        
        if (mysqli_stmt_execute($stmt)) {
            header('Location: index.php?msg=collected');
            exit;
        } else {
            $error = 'Có lỗi xảy ra.';
        }
    }
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="pagetitle">
  <h1><i class="bi bi-cash me-2"></i>Thu tiền phòng</h1>
  <nav>
    <ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="<?= ADMIN_BASE_PATH ?>/modules/dashboard/index.php">Dashboard</a></li>
      <li class="breadcrumb-item"><a href="index.php">Thanh toán</a></li>
      <li class="breadcrumb-item active">Thu tiền</li>
    </ol>
  </nav>
</div>

<section class="section">
<div class="row">
  
  <!-- Payment Info -->
  <div class="col-lg-6">
    <div class="card">
      <div class="card-header"><h5 class="mb-0">Thông tin phiếu thu</h5></div>
      <div class="card-body pt-3">
        <table class="table table-borderless">
          <tr>
            <th width="40%">Phòng:</th>
            <td><strong><?= htmlspecialchars($payment['building_name'] . ' - ' . $payment['room_code']) ?></strong></td>
          </tr>
          <tr>
            <th>Người thuê:</th>
            <td><?= htmlspecialchars($payment['tenant_name'] ?? 'N/A') ?></td>
          </tr>
          <tr>
            <th>Kỳ thanh toán:</th>
            <td><strong>Tháng <?= date('m/Y', strtotime($payment['period_month'])) ?></strong></td>
          </tr>
          <tr><td colspan="2"><hr class="my-2"></td></tr>
          <tr>
            <th>Tiền phòng:</th>
            <td><?= number_format((float)$payment['rent_amount'], 0, ',', '.') ?> đ</td>
          </tr>
          <tr>
            <th>Tiền điện:</th>
            <td>
              <?= number_format((float)$payment['electricity_amount'], 0, ',', '.') ?> đ
              <small class="text-muted">(<?= $payment['electricity_new'] - $payment['electricity_old'] ?> kWh)</small>
            </td>
          </tr>
          <tr>
            <th>Tiền nước:</th>
            <td>
              <?= number_format((float)$payment['water_amount'], 0, ',', '.') ?> đ
              <small class="text-muted">(<?= $payment['water_new'] - $payment['water_old'] ?> m³)</small>
            </td>
          </tr>
          <?php if ($payment['internet_amount'] > 0): ?>
          <tr>
            <th>Internet:</th>
            <td><?= number_format((float)$payment['internet_amount'], 0, ',', '.') ?> đ</td>
          </tr>
          <?php endif; ?>
          <?php if ($payment['parking_amount'] > 0): ?>
          <tr>
            <th>Gửi xe:</th>
            <td><?= number_format((float)$payment['parking_amount'], 0, ',', '.') ?> đ</td>
          </tr>
          <?php endif; ?>
          <?php if ($payment['other_amount'] > 0): ?>
          <tr>
            <th>Phí khác:</th>
            <td><?= number_format((float)$payment['other_amount'], 0, ',', '.') ?> đ 
              <?php if ($payment['other_note']): ?><small>(<?= htmlspecialchars($payment['other_note']) ?>)</small><?php endif; ?>
            </td>
          </tr>
          <?php endif; ?>
          <tr><td colspan="2"><hr class="my-2"></td></tr>
          <tr class="table-primary">
            <th>TỔNG CỘNG:</th>
            <td><strong class="fs-5"><?= number_format((float)$payment['total_amount'], 0, ',', '.') ?> đ</strong></td>
          </tr>
          <tr>
            <th>Đã thu:</th>
            <td class="text-success"><?= number_format((float)$payment['paid_amount'], 0, ',', '.') ?> đ</td>
          </tr>
          <tr class="table-warning">
            <th>CÒN NỢ:</th>
            <td><strong class="text-danger fs-5"><?= number_format((float)$payment['total_amount'] - (float)$payment['paid_amount'], 0, ',', '.') ?> đ</strong></td>
          </tr>
        </table>
      </div>
    </div>
  </div>
  
  <!-- Collect Form -->
  <div class="col-lg-6">
    <div class="card">
      <div class="card-header bg-success text-white"><h5 class="mb-0"><i class="bi bi-cash-coin me-2"></i>Thu tiền</h5></div>
      <div class="card-body pt-4">
        
        <?php if ($error): ?>
          <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <?php if ($payment['status'] === 'PAID'): ?>
          <div class="alert alert-success">
            <i class="bi bi-check-circle me-2"></i>Phiếu này đã được thanh toán đầy đủ vào ngày <?= date('d/m/Y', strtotime($payment['paid_date'])) ?>.
          </div>
          <a href="index.php" class="btn btn-secondary"><i class="bi bi-arrow-left me-1"></i>Quay lại</a>
        <?php else: ?>
          <form method="post">
            <div class="mb-3">
              <label class="form-label">Số tiền thu <span class="text-danger">*</span></label>
              <div class="input-group">
                <input type="number" class="form-control form-control-lg" name="paid_amount" 
                       value="<?= (float)$payment['total_amount'] - (float)$payment['paid_amount'] ?>" required min="1">
                <span class="input-group-text">đ</span>
              </div>
            </div>
            
            <div class="mb-3">
              <label class="form-label">Phương thức</label>
              <select class="form-select" name="payment_method">
                <option value="CASH">Tiền mặt</option>
                <option value="BANK">Chuyển khoản</option>
                <option value="MOMO">MoMo</option>
              </select>
            </div>
            
            <div class="mb-3">
              <label class="form-label">Ngày thu</label>
              <input type="date" class="form-control" name="paid_date" value="<?= date('Y-m-d') ?>">
            </div>
            
            <div class="d-flex gap-2">
              <button type="submit" class="btn btn-success btn-lg">
                <i class="bi bi-check-circle me-1"></i>Xác nhận thu tiền
              </button>
              <a href="index.php" class="btn btn-secondary"><i class="bi bi-arrow-left me-1"></i>Quay lại</a>
            </div>
          </form>
        <?php endif; ?>
        
      </div>
    </div>
  </div>
  
</div>
</section>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
