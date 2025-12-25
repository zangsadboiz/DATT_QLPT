<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';

$role = $_SESSION['role_name'] ?? '';
$user_id = (int)($_SESSION['user_id'] ?? 0);

if ($role !== 'LANDLORD' || $user_id <= 0) {
    header('Location: /quanlyphongtro/admin/index.php?error=no_permission');
    exit;
}

if (!function_exists('hasColumn')) {
    function hasColumn(mysqli $conn, string $table, string $col): bool {
        $t = mysqli_real_escape_string($conn, $table);
        $c = mysqli_real_escape_string($conn, $col);
        $rs = mysqli_query($conn, "
            SELECT COUNT(*) AS cnt
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = '$t'
              AND COLUMN_NAME = '$c'
        ");
        $row = $rs ? mysqli_fetch_assoc($rs) : null;
        return (int)($row['cnt'] ?? 0) > 0;
    }
}

$HAS_CONTRACT_ID = hasColumn($conn, 'bookings', 'contract_id');

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: index.php?error=invalid');
    exit;
}

$selectContract = $HAS_CONTRACT_ID ? "bk.contract_id" : "NULL AS contract_id";

// Kiểm tra cột deposit_amount và total_estimate có tồn tại không
$HAS_DEPOSIT_AMOUNT = hasColumn($conn, 'bookings', 'deposit_amount');
$HAS_TOTAL_ESTIMATE = hasColumn($conn, 'bookings', 'total_estimate');

$selectDeposit = $HAS_DEPOSIT_AMOUNT ? "bk.deposit_amount" : "NULL AS deposit_amount";
$selectTotal = $HAS_TOTAL_ESTIMATE ? "bk.total_estimate" : "NULL AS total_estimate";

// Check if tenants table has id_card column
$HAS_ID_CARD = hasColumn($conn, 'tenants', 'id_card');
$selectIdCard = $HAS_ID_CARD ? "t.id_card" : "NULL AS id_card";

$sql = "
    SELECT
      bk.booking_id, bk.booking_code, bk.status, bk.created_at, bk.cancelled_at,
      bk.check_in, bk.check_out, 
      $selectDeposit, $selectTotal,
      bk.note,
      $selectContract,
      r.room_id, r.room_code, r.base_rent, r.daily_price, r.rental_type, r.room_status,
      b.building_id, b.building_name, b.address AS building_address,
      t.tenant_id, t.user_id AS tenant_user_id, t.full_name AS tenant_name, t.phone AS tenant_phone,
      t.email AS tenant_email, $selectIdCard
    FROM bookings bk
    JOIN rooms r ON r.room_id = bk.room_id
    JOIN buildings b ON b.building_id = r.building_id
    LEFT JOIN tenants t ON t.tenant_id = bk.tenant_id
    WHERE bk.booking_id = $id
      AND b.owner_id = $user_id
    LIMIT 1
";
$rs = mysqli_query($conn, $sql);

if (!$rs || mysqli_num_rows($rs) === 0) {
    // Debug: hiển thị lỗi
    $sqlError = mysqli_error($conn);
    header('Location: index.php?error=not_found_or_forbidden&debug=' . urlencode($sqlError));
    exit;
}

$bk = mysqli_fetch_assoc($rs);

$badge = match($bk['status']) {
    'PENDING' => '<span class="badge bg-warning text-dark fs-6">Chờ thanh toán</span>',
    'DEPOSIT_PAID' => '<span class="badge bg-success fs-6">Đã thanh toán cọc</span>',
    'CANCELLED' => '<span class="badge bg-danger fs-6">Đã hủy</span>',
    'CHECKED_IN' => '<span class="badge bg-info text-dark fs-6">Đang thuê</span>',
    'CHECKED_OUT' => '<span class="badge bg-secondary fs-6">Đã trả phòng</span>',
    default => '<span class="badge bg-light text-dark fs-6">Không xác định</span>',
};

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="pagetitle d-flex justify-content-between align-items-center">
  <h1>Chi tiết yêu cầu thuê</h1>
  <a href="index.php" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Quay lại</a>
</div>

<?php if (!$HAS_CONTRACT_ID): ?>
  <div class="alert alert-warning">
    Bảng <b>bookings</b> của bạn chưa có cột <b>contract_id</b>. Hãy chạy SQL bổ sung để liên kết yêu cầu thuê với hợp đồng.
  </div>
<?php endif; ?>

<?php 
$error = $_GET['error'] ?? '';
$errorMessages = [
    'room_has_active_contract' => '<i class="bi bi-exclamation-triangle me-2"></i><strong>Phòng này đã có hợp đồng đang hiệu lực!</strong> Bạn cần kết thúc hợp đồng cũ trước khi lập hợp đồng mới.',
    'must_pay_first' => '<i class="bi bi-credit-card me-2"></i>Sinh viên phải <strong>thanh toán đặt cọc</strong> trước khi lập hợp đồng.',
    'must_confirm' => '<i class="bi bi-info-circle me-2"></i>Yêu cầu phải được duyệt trước.',
    'already_has_contract' => '<i class="bi bi-check-circle me-2"></i>Yêu cầu này đã được lập hợp đồng rồi.',
    'tenant_not_student' => '<i class="bi bi-person-x me-2"></i>Người thuê không phải sinh viên.',
];
if ($error && isset($errorMessages[$error])): ?>
  <div class="alert alert-danger alert-dismissible fade show">
    <?= $errorMessages[$error] ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
<?php endif; ?>

<section class="section">
  <div class="card">
    <div class="card-body">

      <div class="d-flex justify-content-between align-items-start mb-3">
        <div>
          <h4 class="mb-1"><?= htmlspecialchars($bk['booking_code'] ?? ('#'.$bk['booking_id'])) ?></h4>
          <div class="text-muted">Tạo lúc: <?= htmlspecialchars($bk['created_at'] ?? '-') ?></div>
        </div>
        <div><?= $badge ?></div>
      </div>

      <div class="row g-4">
        <div class="col-md-6">
          <div class="card border-primary h-100">
            <div class="card-header bg-primary text-white">
              <i class="bi bi-person-circle me-2"></i>Thông tin người thuê
            </div>
            <div class="card-body">
              <p class="mb-2"><i class="bi bi-person me-2 text-primary"></i><strong>Họ tên:</strong> <?= htmlspecialchars($bk['tenant_name'] ?? '-') ?></p>
              <p class="mb-2"><i class="bi bi-telephone me-2 text-primary"></i><strong>SĐT:</strong> <a href="tel:<?= htmlspecialchars($bk['tenant_phone'] ?? '') ?>"><?= htmlspecialchars($bk['tenant_phone'] ?? '-') ?></a></p>
              <p class="mb-2"><i class="bi bi-envelope me-2 text-primary"></i><strong>Email:</strong> <?= htmlspecialchars($bk['tenant_email'] ?? '-') ?></p>
              <p class="mb-0"><i class="bi bi-credit-card me-2 text-primary"></i><strong>CCCD/CMND:</strong> <?= htmlspecialchars($bk['id_card'] ?? '-') ?></p>
            </div>
          </div>
        </div>

        <div class="col-md-6">
          <div class="card border-success h-100">
            <div class="card-header bg-success text-white">
              <i class="bi bi-house-door me-2"></i>Thông tin phòng
            </div>
            <div class="card-body">
              <p class="mb-2"><i class="bi bi-building me-2 text-success"></i><strong>Dãy/Tòa:</strong> <?= htmlspecialchars($bk['building_name'] ?? '-') ?></p>
              <p class="mb-2"><i class="bi bi-geo-alt me-2 text-success"></i><strong>Địa chỉ:</strong> <?= htmlspecialchars($bk['building_address'] ?? '-') ?></p>
              <p class="mb-2"><i class="bi bi-door-open me-2 text-success"></i><strong>Phòng:</strong> <?= htmlspecialchars($bk['room_code'] ?? '-') ?></p>
              <?php 
              $isDaily = ($bk['rental_type'] ?? 'MONTHLY') === 'DAILY';
              $roomPrice = $isDaily ? ($bk['daily_price'] ?? 0) : ($bk['base_rent'] ?? 0);
              $priceUnit = $isDaily ? 'đ/ngày' : 'đ/tháng';
              ?>
              <p class="mb-2"><i class="bi bi-calendar-range me-2 text-success"></i><strong>Loại thuê:</strong> 
                <?php if ($isDaily): ?>
                  <span class="badge bg-info">Theo ngày</span>
                <?php else: ?>
                  <span class="badge bg-primary">Theo tháng</span>
                <?php endif; ?>
              </p>
              <p class="mb-0"><i class="bi bi-cash me-2 text-success"></i><strong>Giá thuê:</strong> <span class="text-danger fw-bold"><?= number_format((float)$roomPrice) ?><?= $priceUnit ?></span></p>
            </div>
          </div>
        </div>
      </div>

      <hr>

      <div class="row g-3 mt-3">
        <div class="col-md-4">
          <div class="alert alert-info mb-0 text-center">
            <i class="bi bi-calendar-check me-2"></i><strong>Ngày nhận phòng</strong><br>
            <span class="fs-5"><?= $bk['check_in'] ? date('d/m/Y', strtotime($bk['check_in'])) : '-' ?></span>
          </div>
        </div>
        <div class="col-md-4">
          <div class="alert alert-warning mb-0 text-center">
            <i class="bi bi-calendar-x me-2"></i><strong>Ngày trả phòng</strong><br>
            <span class="fs-5"><?= $bk['check_out'] ? date('d/m/Y', strtotime($bk['check_out'])) : 'Không xác định' ?></span>
          </div>
        </div>
        <div class="col-md-4">
          <?php 
          $bookingStatus = $bk['status'] ?? 'PENDING';
          if (!empty($bk['contract_id'])): ?>
            <div class="alert alert-success mb-0 text-center">
              <i class="bi bi-file-earmark-check me-2"></i><strong>Hợp đồng</strong><br>
              <span class="badge bg-success fs-6">#<?= (int)$bk['contract_id'] ?></span>
            </div>
          <?php elseif ($bookingStatus === 'DEPOSIT_PAID'): ?>
            <div class="alert alert-success mb-0 text-center">
              <i class="bi bi-check-circle me-2"></i><strong>Thanh toán</strong><br>
              <span class="badge bg-success fs-6">Đã thanh toán</span>
            </div>
          <?php elseif ($bookingStatus === 'CANCELLED'): ?>
            <div class="alert alert-danger mb-0 text-center">
              <i class="bi bi-x-circle me-2"></i><strong>Trạng thái</strong><br>
              <span class="badge bg-danger fs-6">Đã hủy</span>
            </div>
          <?php else: ?>
            <div class="alert alert-secondary mb-0 text-center">
              <i class="bi bi-hourglass-split me-2"></i><strong>Quy trình</strong><br>
              <span class="badge bg-secondary fs-6">Chờ duyệt</span>
            </div>
          <?php endif; ?>
        </div>
        </div>
      </div>

      <h5>Lời nhắn</h5>
      <div class="alert alert-light mb-0">
        <?= nl2br(htmlspecialchars($bk['note'] ?? '')) ?>
      </div>

      <hr>

      <div class="d-flex gap-2 flex-wrap">
        <?php if (($bk['status'] ?? '') === 'DEPOSIT_PAID'): ?>
          <div class="alert alert-success py-2 mb-2 w-100">
            <i class="bi bi-check-circle me-1"></i>
            <strong>Sinh viên đã thanh toán đặt cọc thành công!</strong> 
            Số tiền: <strong><?= number_format((float)($bk['deposit_amount'] ?? 0)) ?>đ</strong>
          </div>
        <?php endif; ?>

        <?php if (($bk['status'] ?? '') === 'DEPOSIT_PAID' && empty($bk['contract_id']) && $HAS_CONTRACT_ID): ?>
          <a class="btn btn-primary" href="create_contract.php?id=<?= (int)$bk['booking_id'] ?>">
            <i class="bi bi-file-earmark-text"></i> Lập hợp đồng
          </a>
        <?php endif; ?>

        <?php if (!empty($bk['contract_id'])): ?>
          <a class="btn btn-outline-primary" href="/quanlyphongtro/admin/modules/hopdong_owner/view.php?id=<?= (int)$bk['contract_id'] ?>">
            Xem hợp đồng
          </a>
        <?php endif; ?>
      </div>

    </div>
  </div>
</section>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
