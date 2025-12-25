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
if (!$HAS_CONTRACT_ID) {
    die('Thiếu bookings.contract_id. Hãy chạy SQL bổ sung để liên kết yêu cầu thuê với hợp đồng.');
}

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: index.php?error=invalid');
    exit;
}

$bkRs = mysqli_query($conn, "
    SELECT
      bk.booking_id, bk.booking_code, bk.status, bk.created_at, bk.note, bk.contract_id,
      bk.check_in, bk.check_out,
      r.room_id, r.room_code, r.base_rent, r.daily_price, r.rental_type, r.room_status,
      b.building_id, b.building_name, b.address AS building_address, b.owner_id,
      t.tenant_id, t.user_id AS tenant_user_id, t.full_name AS tenant_name, t.phone AS tenant_phone, t.email AS tenant_email,
      u.full_name AS owner_name, u.phone AS owner_phone
    FROM bookings bk
    JOIN rooms r ON r.room_id = bk.room_id
    JOIN buildings b ON b.building_id = r.building_id
    LEFT JOIN tenants t ON t.tenant_id = bk.tenant_id
    JOIN users u ON u.user_id = b.owner_id
    WHERE bk.booking_id = $id
      AND b.owner_id = $user_id
    LIMIT 1
");

if (!$bkRs || mysqli_num_rows($bkRs) === 0) {
    header('Location: index.php?error=not_found_or_forbidden');
    exit;
}
$bk = mysqli_fetch_assoc($bkRs);

if (($bk['status'] ?? '') !== 'DEPOSIT_PAID') {
    header('Location: detail.php?id='.$id.'&error=must_pay_first');
    exit;
}
if (!empty($bk['contract_id'])) {
    header('Location: detail.php?id='.$id.'&error=already_has_contract');
    exit;
}

// đảm bảo tenant là STUDENT
$tenant_user_id = (int)($bk['tenant_user_id'] ?? 0);
$roleRs = mysqli_query($conn, "
    SELECT r.role_name
    FROM users uu
    JOIN roles r ON r.role_id = uu.role_id
    WHERE uu.user_id = $tenant_user_id
    LIMIT 1
");
$tenantRole = ($roleRs && mysqli_num_rows($roleRs) > 0) ? (mysqli_fetch_assoc($roleRs)['role_name'] ?? '') : '';
if ($tenantRole !== 'STUDENT') {
    header('Location: detail.php?id='.$id.'&error=tenant_not_student');
    exit;
}

$room_id = (int)$bk['room_id'];
$bookingStart = mysqli_real_escape_string($conn, $bk['check_in'] ?? date('Y-m-d'));
$bookingEnd = $bk['check_out'] ? mysqli_real_escape_string($conn, $bk['check_out']) : null;

// Chặn tạo nếu phòng đã có ACTIVE contract TRÙNG THỜI GIAN
// Nếu HĐ cũ không có end_date (vô thời hạn), cho phép đặt trước - chủ trọ sẽ kết thúc HĐ cũ trước khi bàn giao
$overlapSql = "
    SELECT c.contract_id, c.contract_code, c.start_date, c.end_date
    FROM contracts c
    WHERE c.room_id = $room_id 
      AND c.contract_status = 'ACTIVE'
      AND c.end_date IS NOT NULL
      AND '$bookingStart' <= c.end_date 
      AND " . ($bookingEnd ? "'$bookingEnd' >= c.start_date" : "'$bookingStart' >= c.start_date") . "
    LIMIT 1
";
$activeRs = mysqli_query($conn, $overlapSql);
if ($activeRs && mysqli_num_rows($activeRs) > 0) {
    $existingContract = mysqli_fetch_assoc($activeRs);
    header('Location: detail.php?id='.$id.'&error=room_has_active_contract&contract_code=' . urlencode($existingContract['contract_code']));
    exit;
}

// default
$defaultStart = !empty($bk['check_in']) ? $bk['check_in'] : date('Y-m-d');
$defaultEnd   = !empty($bk['check_out']) ? $bk['check_out'] : ''; // Lấy từ check_out của booking
$rentalType   = $bk['rental_type'] ?? 'MONTHLY';
$defaultRent  = $rentalType === 'DAILY' ? (float)($bk['daily_price'] ?? 0) : (float)($bk['base_rent'] ?? 0);
$defaultDep   = 0;
$defaultBill  = 1;

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $start_date = $_POST['start_date'] ?? $defaultStart;
    $end_date   = trim($_POST['end_date'] ?? '');
    $rent_amount= (float)($_POST['rent_amount'] ?? 0);
    $deposit    = (float)($_POST['deposit_amount'] ?? 0);
    $billing_day= (int)($_POST['billing_day'] ?? $defaultBill);
    $note       = trim($_POST['note'] ?? '');
    $terms      = trim($_POST['terms_text'] ?? '');

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start_date)) {
        $error = 'Ngày bắt đầu không hợp lệ.';
    } elseif ($end_date !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end_date)) {
        $error = 'Ngày kết thúc không hợp lệ.';
    } elseif ($rent_amount <= 0) {
        $error = 'Tiền thuê phải > 0.';
    } elseif ($billing_day < 1 || $billing_day > 28) {
        $error = 'Ngày thu tiền nên trong 1..28 để tránh lỗi tháng.';
    }

    if (!$error) {
        try {
            mysqli_begin_transaction($conn);

            // contract_code UNIQUE
            $contract_code = 'HD' . date('YmdHis') . '-' . $bk['booking_id'];
            $contract_code_sql = mysqli_real_escape_string($conn, $contract_code);

            $start_sql = mysqli_real_escape_string($conn, $start_date);
            $end_sql   = $end_date !== '' ? ("'".mysqli_real_escape_string($conn, $end_date)."'") : "NULL";
            $note_sql  = $note !== '' ? ("'".mysqli_real_escape_string($conn, $note)."'") : "NULL";
            $terms_sql = $terms !== '' ? ("'".mysqli_real_escape_string($conn, $terms)."'") : "NULL";

            $lname_sql = mysqli_real_escape_string($conn, $bk['owner_name'] ?? '');
            $lphone_sql= mysqli_real_escape_string($conn, $bk['owner_phone'] ?? '');
            $laddr_sql = mysqli_real_escape_string($conn, $bk['building_address'] ?? '');

            // insert contracts
            $ok1 = mysqli_query($conn, "
                INSERT INTO contracts(
                    contract_code, room_id, start_date, end_date,
                    rent_amount, deposit_amount, billing_day,
                    contract_status, created_by, created_at,
                    note, landlord_name, landlord_phone, landlord_address, terms_text
                ) VALUES (
                    '$contract_code_sql', $room_id, '$start_sql', $end_sql,
                    ".(float)$rent_amount.", ".(float)$deposit.", ".(int)$billing_day.",
                    'ACTIVE', $user_id, NOW(),
                    $note_sql, '$lname_sql', '$lphone_sql', '$laddr_sql', $terms_sql
                )
            ");
            if (!$ok1) {
                throw new Exception('Lỗi tạo hợp đồng: ' . mysqli_error($conn));
            }

            $contract_id = (int)mysqli_insert_id($conn);

            // insert contract_tenants (đại diện)
            $tenant_id = (int)$bk['tenant_id'];
            $ok2 = mysqli_query($conn, "
                INSERT INTO contract_tenants(contract_id, tenant_id, is_representative, move_in_date, note)
                VALUES ($contract_id, $tenant_id, 1, '$start_sql', NULL)
            ");
            if (!$ok2) {
                throw new Exception('Lỗi gắn người thuê vào hợp đồng: ' . mysqli_error($conn));
            }

            // cập nhật phòng
            $ok3 = mysqli_query($conn, "
                UPDATE rooms
                SET room_status='OCCUPIED'
                WHERE room_id=$room_id
                LIMIT 1
            ");
            if (!$ok3) {
                throw new Exception('Lỗi cập nhật trạng thái phòng: ' . mysqli_error($conn));
            }

            // link booking -> contract AND update status to CHECKED_IN
            $ok4 = mysqli_query($conn, "
                UPDATE bookings
                SET contract_id = $contract_id, status = 'CHECKED_IN'
                WHERE booking_id = $id
                LIMIT 1
            ");
            if (!$ok4) {
                throw new Exception('Lỗi liên kết yêu cầu thuê với hợp đồng: ' . mysqli_error($conn));
            }

            // tự động hủy các yêu cầu PENDING khác của cùng phòng (tránh nhiều người đặt cùng lúc)
            mysqli_query($conn, "
                UPDATE bookings
                SET status='CANCELLED', cancelled_at=NOW()
                WHERE room_id = $room_id
                  AND booking_id <> $id
                  AND status = 'PENDING'
            ");

            mysqli_commit($conn);

            header('Location: /quanlyphongtro/admin/modules/hopdong_owner/view.php?id='.$contract_id);
            exit;

        } catch (Throwable $e) {
            mysqli_rollback($conn);
            $error = $e->getMessage();
        }
    }
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="pagetitle d-flex justify-content-between align-items-center">
  <h1>Lập hợp đồng từ yêu cầu</h1>
  <a href="detail.php?id=<?= (int)$bk['booking_id'] ?>" class="btn btn-secondary">
    <i class="bi bi-arrow-left"></i> Quay lại
  </a>
</div>

<?php if ($error): ?>
  <div class="alert alert-danger alert-dismissible fade show">
    <?= htmlspecialchars($error) ?>
    <button class="btn-close" data-bs-dismiss="alert"></button>
  </div>
<?php endif; ?>

<section class="section">
  <div class="row">
    <!-- Info Cards -->
    <div class="col-12 mb-4">
      <div class="row g-4">
        <!-- Tenant Card -->
        <div class="col-md-6">
          <div class="card border-primary h-100">
            <div class="card-header bg-primary text-white">
              <i class="bi bi-person-fill me-2"></i>Thông tin Người thuê
            </div>
            <div class="card-body">
              <h5 class="card-title mb-1"><?= htmlspecialchars($bk['tenant_name'] ?? '-') ?></h5>
              <p class="card-text mb-0">
                <i class="bi bi-telephone text-muted me-2"></i><?= htmlspecialchars($bk['tenant_phone'] ?? '-') ?>
              </p>
              <p class="card-text mb-0">
                <i class="bi bi-envelope text-muted me-2"></i><?= htmlspecialchars($bk['tenant_email'] ?? '-') ?>
              </p>
            </div>
          </div>
        </div>
        
        <!-- Room Card -->
        <div class="col-md-6">
          <div class="card border-success h-100">
            <div class="card-header bg-success text-white">
              <i class="bi bi-house-door-fill me-2"></i>Thông tin Phòng
            </div>
            <div class="card-body">
              <h5 class="card-title mb-1"><?= htmlspecialchars($bk['building_name'] ?? '-') ?> — <?= htmlspecialchars($bk['room_code'] ?? '-') ?></h5>
              <p class="card-text mb-0">
                <i class="bi bi-geo-alt text-muted me-2"></i><?= htmlspecialchars($bk['building_address'] ?? '-') ?>
              </p>
              <p class="card-text mb-0">
                <i class="bi bi-cash-coin text-muted me-2"></i>
                <strong class="text-success"><?= number_format($defaultRent, 0, ',', '.') ?>đ / <?= $rentalType === 'DAILY' ? 'ngày' : 'tháng' ?></strong>
              </p>
            </div>
          </div>
        </div>
      </div>
    </div>
    
    <!-- Contract Form -->
    <div class="col-12">
      <div class="card">
        <div class="card-header">
          <h5 class="card-title mb-0"><i class="bi bi-file-earmark-text me-2"></i>Thông tin Hợp đồng</h5>
        </div>
        <div class="card-body pt-4">
          <form method="post" class="row g-4">
            <!-- Row 1: Dates -->
            <div class="col-md-3">
              <label class="form-label fw-semibold">Ngày bắt đầu</label>
              <input type="date" class="form-control bg-light" readonly
                     value="<?= htmlspecialchars($defaultStart) ?>">
              <input type="hidden" name="start_date" value="<?= htmlspecialchars($defaultStart) ?>">
              <small class="text-muted">Theo yêu cầu thuê</small>
            </div>
            
            <div class="col-md-3">
              <label class="form-label fw-semibold">Ngày kết thúc</label>
              <input type="date" class="form-control bg-light" readonly
                     value="<?= htmlspecialchars($defaultEnd) ?>">
              <input type="hidden" name="end_date" value="<?= htmlspecialchars($defaultEnd) ?>">
              <small class="text-muted"><?= $defaultEnd ? 'Theo yêu cầu thuê' : 'Vô thời hạn' ?></small>
            </div>
            
            <div class="col-md-3">
              <label class="form-label fw-semibold">
                Tiền thuê / <?= $rentalType === 'DAILY' ? 'ngày' : 'tháng' ?>
              </label>
              <div class="input-group">
                <input type="text" class="form-control bg-light" readonly
                       value="<?= number_format($defaultRent, 0, ',', '.') ?>">
                <span class="input-group-text">đ</span>
              </div>
              <input type="hidden" name="rent_amount" value="<?= $defaultRent ?>">
              <small class="text-muted">Theo giá phòng đã thiết lập</small>
            </div>
            
            <!-- Row 2: Billing Day -->
            <div class="col-md-3">
              <label class="form-label fw-semibold">Ngày thu tiền hàng tháng <span class="text-danger">*</span></label>
              <input type="number" name="billing_day" class="form-control" required
                     min="1" max="28" placeholder="1-28"
                     value="<?= htmlspecialchars($_POST['billing_day'] ?? (string)$defaultBill) ?>">
              <small class="text-muted">Từ 1 đến 28</small>
            </div>
            
            <!-- Row 3: Notes -->
            <div class="col-12">
              <label class="form-label fw-semibold">Ghi chú</label>
              <textarea name="note" class="form-control" rows="2" placeholder="Ghi chú về hợp đồng (tuỳ chọn)"><?= htmlspecialchars($_POST['note'] ?? ($bk['note'] ?? '')) ?></textarea>
            </div>
            
            <!-- Row 4: Terms -->
            <div class="col-12">
              <label class="form-label fw-semibold">Điều khoản hợp đồng</label>
              <textarea name="terms_text" class="form-control" rows="5" placeholder="Nhập các điều khoản hợp đồng (tuỳ chọn)"><?= htmlspecialchars($_POST['terms_text'] ?? '') ?></textarea>
            </div>
            
            <!-- Buttons -->
            <div class="col-12 d-flex gap-2">
              <button type="submit" class="btn btn-success btn-lg">
                <i class="bi bi-check-circle me-2"></i>Tạo hợp đồng
              </button>
              <a class="btn btn-secondary btn-lg" href="detail.php?id=<?= (int)$bk['booking_id'] ?>">
                <i class="bi bi-x-circle me-2"></i>Hủy
              </a>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</section>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
