<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/status_vn.php';

$role = $_SESSION['role_name'] ?? '';
$user_id = (int)($_SESSION['user_id'] ?? 0);

if ($role !== 'LANDLORD' || $user_id <= 0) {
    header('Location: /quanlyphongtro/admin/index.php?error=no_permission');
    exit;
}

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: index.php?error=invalid');
    exit;
}

$rs = mysqli_query($conn, "
  SELECT
    c.*,
    r.room_code, r.room_status, r.base_rent,
    b.building_name, b.address,
    u.full_name AS owner_full_name
  FROM contracts c
  JOIN rooms r ON r.room_id = c.room_id
  JOIN buildings b ON b.building_id = r.building_id
  LEFT JOIN users u ON u.user_id = b.owner_id
  WHERE c.contract_id = $id AND b.owner_id = $user_id
  LIMIT 1
");
$contract = $rs ? mysqli_fetch_assoc($rs) : null;
if (!$contract) {
    header('Location: index.php?error=not_found');
    exit;
}

$tenants = mysqli_query($conn, "
    SELECT t.*, ct.is_representative, ct.move_in_date, ct.move_out_date
    FROM contract_tenants ct
    JOIN tenants t ON t.tenant_id = ct.tenant_id
    WHERE ct.contract_id = $id
    ORDER BY ct.is_representative DESC, t.tenant_id ASC
");

function vn_contract_status(?string $s): string {
    return match($s) {
        'ACTIVE'    => 'Đang hiệu lực',
        'ENDED'     => 'Đã kết thúc',
        'CANCELLED' => 'Đã hủy',
        default     => 'Hết hiệu lực',
    };
}

// Lấy lịch sử gia hạn
$contractCode = mysqli_real_escape_string($conn, $contract['contract_code'] ?? '');
$renewalHistory = [];
if (!empty($contractCode)) {
    $renewalRs = mysqli_query($conn, "
        SELECT transaction_id, description, amount, created_at
        FROM transactions 
        WHERE description LIKE '%$contractCode%'
          AND transaction_type = 'DEPOSIT_RECEIVED'
        ORDER BY created_at DESC
    ");
    if ($renewalRs) {
        while ($row = mysqli_fetch_assoc($renewalRs)) {
            $renewalHistory[] = $row;
        }
    }
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="pagetitle d-flex justify-content-between align-items-center">
  <h1>Chi tiết hợp đồng</h1>
  <div class="d-flex gap-2">
    <a class="btn btn-outline-secondary" href="index.php"><i class="bi bi-arrow-left"></i> Danh sách</a>
    <a class="btn btn-outline-secondary" href="print.php?id=<?= (int)$contract['contract_id'] ?>" target="_blank">
      <i class="bi bi-printer"></i> In
    </a>
    <?php if (($contract['contract_status'] ?? '') === 'ACTIVE'): ?>
      <a class="btn btn-outline-danger" href="end.php?id=<?= (int)$contract['contract_id'] ?>">
        <i class="bi bi-x-circle"></i> Kết thúc
      </a>
    <?php endif; ?>
  </div>
</div>

<section class="section">
  <div class="row">

    <div class="col-lg-6">
      <div class="card">
        <div class="card-body pt-3">
          <h5 class="card-title">Thông tin hợp đồng</h5>
          <p class="mb-1"><b>Mã HĐ:</b> <?= htmlspecialchars($contract['contract_code']) ?></p>
          <p class="mb-1"><b>Trạng thái:</b> <?= htmlspecialchars(vn_contract_status($contract['contract_status'] ?? '')) ?></p>
          <p class="mb-1"><b>Ngày bắt đầu:</b> <?= htmlspecialchars($contract['start_date'] ?? '-') ?></p>
          <p class="mb-1"><b>Ngày kết thúc:</b> <?= htmlspecialchars($contract['end_date'] ?? '-') ?></p>
          <p class="mb-1"><b>Giá thuê:</b> <?= number_format((float)$contract['rent_amount']) ?></p>
          <p class="mb-1"><b>Tiền cọc:</b> <?= number_format((float)$contract['deposit_amount']) ?></p>
          <p class="mb-1"><b>Ngày chốt:</b> <?= (int)$contract['billing_day'] ?></p>
          <?php if (!empty($contract['note'])): ?>
            <p class="mb-0"><b>Ghi chú:</b> <?= htmlspecialchars($contract['note']) ?></p>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <div class="col-lg-6">
      <div class="card">
        <div class="card-body pt-3">
          <h5 class="card-title">Thông tin phòng</h5>
          <p class="mb-1"><b>Dãy/Tòa:</b> <?= htmlspecialchars($contract['building_name'] ?? '-') ?></p>
          <p class="mb-1"><b>Địa chỉ:</b> <?= htmlspecialchars($contract['address'] ?? '-') ?></p>
          <p class="mb-1"><b>Phòng:</b> <?= htmlspecialchars($contract['room_code'] ?? '-') ?></p>
          <p class="mb-0"><b>Trạng thái phòng:</b> <?= htmlspecialchars(vn_room_status($contract['room_status'] ?? '')) ?></p>
        </div>
      </div>
    </div>

    <div class="col-12">
      <div class="card">
        <div class="card-body pt-3">
          <h5 class="card-title">Người thuê</h5>

          <table class="table table-bordered table-hover align-middle">
            <thead class="table-light">
              <tr>
                <th>Họ tên</th>
                <th>SĐT</th>
                <th>Email</th>
                <th>Đại diện</th>
                <th>Ngày vào</th>
                <th>Ngày ra</th>
              </tr>
            </thead>
            <tbody>
              <?php $has=false; while($t = $tenants ? mysqli_fetch_assoc($tenants) : null): $has=true; ?>
                <tr>
                  <td><?= htmlspecialchars($t['full_name'] ?? '-') ?></td>
                  <td><?= htmlspecialchars($t['phone'] ?? '-') ?></td>
                  <td><?= htmlspecialchars($t['email'] ?? '-') ?></td>
                  <td><?= ((int)($t['is_representative'] ?? 0) === 1) ? '<span class="badge bg-primary">Có</span>' : '-' ?></td>
                  <td><?= htmlspecialchars($t['move_in_date'] ?? '-') ?></td>
                  <td><?= htmlspecialchars($t['move_out_date'] ?? $contract['end_date'] ?? '-') ?></td>
                </tr>
              <?php endwhile; ?>
              <?php if(!$has): ?>
                <tr><td colspan="6" class="text-center text-muted">Chưa có người thuê trong hợp đồng</td></tr>
              <?php endif; ?>
            </tbody>
          </table>

        </div>
      </div>
    </div>

  </div>

    <!-- Lịch sử gia hạn -->
    <div class="col-12">
      <div class="card">
        <div class="card-body pt-3">
          <h5 class="card-title"><i class="bi bi-clock-history me-2"></i>Lịch sử gia hạn</h5>
          <?php if (empty($renewalHistory)): ?>
            <p class="text-muted mb-0">Chưa có gia hạn nào cho hợp đồng này.</p>
          <?php else: ?>
            <table class="table table-bordered table-hover align-middle">
              <thead class="table-light">
                <tr>
                  <th style="width:180px">Thời gian</th>
                  <th>Mô tả</th>
                  <th class="text-end" style="width:150px">Số tiền</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach($renewalHistory as $rh): ?>
                <tr>
                  <td><?= date('d/m/Y H:i', strtotime($rh['created_at'])) ?></td>
                  <td><?= htmlspecialchars($rh['description']) ?></td>
                  <td class="text-end text-success fw-bold">+<?= number_format($rh['amount']) ?>đ</td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          <?php endif; ?>
        </div>
      </div>
    </div>

  </div>
</section>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
