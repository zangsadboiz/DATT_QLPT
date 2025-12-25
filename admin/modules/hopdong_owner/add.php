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

$hasTerms = hasColumn($conn, 'contracts', 'terms_text');

function gen_contract_code(int $room_id): string {
    return 'HD' . date('YmdHis') . '-' . $room_id . '-' . random_int(10, 99);
}

/**
 * Nếu sau này bạn nối luồng sinh viên thuê ngoài frontend, bạn có thể gọi:
 * add.php?booking_id=...
 * Hiện tại bạn hoàn toàn có thể bỏ qua, tạo trực tiếp.
 */
$booking_id = (int)($_GET['booking_id'] ?? 0);
$prefill = null;

if ($booking_id > 0) {
    $rs = mysqli_query($conn, "
        SELECT bk.booking_id, bk.tenant_id, bk.room_id, bk.check_in, bk.deposit_amount,
               r.base_rent, r.room_code,
               b.building_name
        FROM bookings bk
        JOIN rooms r ON r.room_id = bk.room_id
        JOIN buildings b ON b.building_id = r.building_id
        WHERE bk.booking_id = $booking_id
          AND b.owner_id = $user_id
        LIMIT 1
    ");
    $prefill = $rs ? mysqli_fetch_assoc($rs) : null;
    if (!$prefill) {
        header('Location: index.php?error=booking_not_found');
        exit;
    }
}

/* ===== Handle POST (no output before redirect) ===== */
$errors = [];

$defaultTerms = "1) Bên thuê thanh toán đúng hạn theo thỏa thuận.\n"
              . "2) Bên thuê giữ gìn tài sản, không tự ý sửa chữa/cơi nới khi chưa có sự đồng ý.\n"
              . "3) Không gây mất trật tự, tuân thủ nội quy khu trọ.\n"
              . "4) Hai bên tự thỏa thuận các nội dung còn lại.";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $room_id = (int)($_POST['room_id'] ?? 0);
    $tenant_id = (int)($_POST['tenant_id'] ?? 0);
    $start_date = $_POST['start_date'] ?? '';
    $rent_amount_input = $_POST['rent_amount'] ?? '';
    $rent_amount = (float)$rent_amount_input;
    $deposit_amount = (float)($_POST['deposit_amount'] ?? 0);
    $billing_day = (int)($_POST['billing_day'] ?? 1);
    $note = trim($_POST['note'] ?? '');
    $terms_text = trim($_POST['terms_text'] ?? $defaultTerms);

    if ($room_id <= 0) $errors[] = 'Vui lòng chọn phòng.';
    if ($tenant_id <= 0) $errors[] = 'Vui lòng chọn người thuê.';
    if (!$start_date) $errors[] = 'Vui lòng chọn ngày bắt đầu.';
    if ($billing_day < 1 || $billing_day > 28) $errors[] = 'Ngày chốt hóa đơn nên từ 1 đến 28.';

    // Check room belongs to landlord + get base_rent
    $checkRoom = mysqli_query($conn, "
        SELECT r.room_id, r.base_rent, r.room_status
        FROM rooms r
        JOIN buildings b ON b.building_id = r.building_id
        WHERE r.room_id = $room_id
          AND b.owner_id = $user_id
          AND r.deleted_at IS NULL
        LIMIT 1
    ");
    $roomRow = $checkRoom ? mysqli_fetch_assoc($checkRoom) : null;
    if (!$roomRow) $errors[] = 'Phòng không hợp lệ hoặc không thuộc chủ trọ này.';

    // Nếu user chưa nhập giá (trống/0) => lấy theo giá phòng
    if ($roomRow && ((string)$rent_amount_input === '' || (float)$rent_amount <= 0)) {
        $rent_amount = (float)($roomRow['base_rent'] ?? 0);
    }

    // Check no ACTIVE contract for this room
    $checkActive = mysqli_query($conn, "
        SELECT contract_id
        FROM contracts
        WHERE room_id = $room_id AND contract_status='ACTIVE'
        LIMIT 1
    ");
    if ($checkActive && mysqli_fetch_assoc($checkActive)) {
        $errors[] = 'Phòng này đang có hợp đồng hiệu lực.';
    }

    if (!$errors) {
        $contract_code = gen_contract_code($room_id);
        $ins = false;

        $escNote = mysqli_real_escape_string($conn, $note);
        $escTerms = mysqli_real_escape_string($conn, $terms_text);
        $escStart = mysqli_real_escape_string($conn, $start_date);

        for ($i = 0; $i < 5; $i++) {
            $escCode = mysqli_real_escape_string($conn, $contract_code);

            if ($hasTerms) {
                $ins = mysqli_query($conn, "
                    INSERT INTO contracts
                        (contract_code, room_id, start_date, rent_amount, deposit_amount, billing_day,
                         contract_status, created_by, note, terms_text)
                    VALUES
                        ('$escCode', $room_id, '$escStart', $rent_amount, $deposit_amount, $billing_day,
                         'ACTIVE', $user_id, '$escNote', '$escTerms')
                ");
            } else {
                // Nếu DB chưa có terms_text thì gộp điều khoản vào note để không mất dữ liệu
                $note2 = $note;
                if ($terms_text !== '') {
                    $note2 = trim($note2 . "\n--- ĐIỀU KHOẢN ---\n" . $terms_text);
                }
                $escNote2 = mysqli_real_escape_string($conn, $note2);

                $ins = mysqli_query($conn, "
                    INSERT INTO contracts
                        (contract_code, room_id, start_date, rent_amount, deposit_amount, billing_day,
                         contract_status, created_by, note)
                    VALUES
                        ('$escCode', $room_id, '$escStart', $rent_amount, $deposit_amount, $billing_day,
                         'ACTIVE', $user_id, '$escNote2')
                ");
            }

            if ($ins) break;
            $contract_code = gen_contract_code($room_id);
        }

        if (!$ins) {
            $errors[] = 'Không tạo được hợp đồng (có thể trùng mã hoặc thiếu cột trong DB).';
        } else {
            $contract_id = (int)mysqli_insert_id($conn);

            // attach tenant
            mysqli_query($conn, "
                INSERT INTO contract_tenants(contract_id, tenant_id, is_representative, move_in_date)
                VALUES($contract_id, $tenant_id, 1, '$escStart')
            ");

            // update room status -> OCCUPIED
            mysqli_query($conn, "UPDATE rooms SET room_status='OCCUPIED' WHERE room_id=$room_id LIMIT 1");

            // if created from booking => bind contract_id
            $posted_booking_id = (int)($_POST['booking_id'] ?? 0);
            if ($posted_booking_id > 0) {
                mysqli_query($conn, "
                    UPDATE bookings bk
                    JOIN rooms r ON r.room_id = bk.room_id
                    JOIN buildings b ON b.building_id = r.building_id
                    SET bk.contract_id = $contract_id, bk.status='CONFIRMED', bk.accepted_at = NOW()
                    WHERE bk.booking_id = $posted_booking_id AND b.owner_id = $user_id
                    LIMIT 1
                ");
            }

            header('Location: view.php?id=' . $contract_id);
            exit;
        }
    }
}

/* ===== Data for form ===== */
$tenants = mysqli_query($conn, "
    SELECT tenant_id, full_name, phone
    FROM tenants
    ORDER BY tenant_id DESC
    LIMIT 200
");

$rooms = mysqli_query($conn, "
    SELECT r.room_id, r.room_code, r.base_rent, r.room_status, b.building_id, b.building_name
    FROM rooms r
    JOIN buildings b ON b.building_id = r.building_id
    WHERE b.owner_id = $user_id AND r.deleted_at IS NULL
    ORDER BY b.building_id DESC, r.room_id DESC
");

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="pagetitle d-flex justify-content-between align-items-center">
  <h1>Tạo hợp đồng</h1>
  <a class="btn btn-outline-secondary" href="index.php"><i class="bi bi-arrow-left"></i> Quay lại</a>
</div>

<?php if (!$hasTerms): ?>
  <div class="alert alert-warning">
    Hệ thống chưa có cột <b>contracts.terms_text</b>. Điều khoản vẫn nhập được nhưng sẽ được gộp vào <b>Ghi chú</b>.
    Bạn nên chạy SQL thêm cột để lưu điều khoản chuẩn.
  </div>
<?php endif; ?>

<?php if ($errors): ?>
  <div class="alert alert-danger">
    <ul class="mb-0">
      <?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

<section class="section">
  <div class="card">
    <div class="card-body pt-3">

      <form method="post">
        <?php if ($prefill): ?>
          <input type="hidden" name="booking_id" value="<?= (int)$prefill['booking_id'] ?>">
        <?php endif; ?>

        <div class="row g-3">

          <div class="col-md-6">
            <label class="form-label">Phòng</label>

            <select name="room_id" id="room_id" class="form-select" required <?= $prefill ? 'disabled' : '' ?>>
              <option value="0">-- Chọn phòng --</option>
              <?php if ($rooms): while ($r = mysqli_fetch_assoc($rooms)): ?>
                <?php
                  $roomId = (int)$r['room_id'];
                  $selected = '';
                  if ($prefill && (int)$prefill['room_id'] === $roomId) $selected = 'selected';
                  if (!$prefill && isset($_POST['room_id']) && (int)$_POST['room_id'] === $roomId) $selected = 'selected';
                ?>
                <option
                  value="<?= $roomId ?>"
                  data-rent="<?= (float)($r['base_rent'] ?? 0) ?>"
                  <?= $selected ?>
                >
                  <?= htmlspecialchars($r['building_name'] . ' - ' . $r['room_code']) ?>
                  (<?= vn_room_status($r['room_status'] ?? '') ?>)
                </option>
              <?php endwhile; endif; ?>
            </select>

            <?php if ($prefill): ?>
              <input type="hidden" name="room_id" value="<?= (int)$prefill['room_id'] ?>">
            <?php endif; ?>

            <div class="form-text">Giá thuê sẽ tự lấy theo phòng khi bạn chọn.</div>
          </div>

          <div class="col-md-6">
            <label class="form-label">Người thuê</label>
            <select name="tenant_id" class="form-select" required <?= $prefill ? 'disabled' : '' ?>>
              <option value="0">-- Chọn người thuê --</option>
              <?php if ($tenants): while ($t = mysqli_fetch_assoc($tenants)): ?>
                <?php
                  $tid = (int)$t['tenant_id'];
                  $selected = '';
                  if ($prefill && (int)$prefill['tenant_id'] === $tid) $selected = 'selected';
                  if (!$prefill && isset($_POST['tenant_id']) && (int)$_POST['tenant_id'] === $tid) $selected = 'selected';
                ?>
                <option value="<?= $tid ?>" <?= $selected ?>>
                  <?= htmlspecialchars($t['full_name']) ?><?= !empty($t['phone']) ? (' - ' . $t['phone']) : '' ?>
                </option>
              <?php endwhile; endif; ?>
            </select>

            <?php if ($prefill): ?>
              <input type="hidden" name="tenant_id" value="<?= (int)$prefill['tenant_id'] ?>">
            <?php endif; ?>
          </div>

          <div class="col-md-4">
            <label class="form-label">Ngày bắt đầu</label>
            <input type="date" name="start_date" class="form-control" required
                   value="<?= htmlspecialchars($_POST['start_date'] ?? ($prefill['check_in'] ?? date('Y-m-d'))) ?>">
          </div>

          <div class="col-md-4">
            <label class="form-label">Giá thuê / tháng</label>
            <input type="number" step="0.01" name="rent_amount" id="rent_amount" class="form-control" required
                   value="<?= htmlspecialchars($_POST['rent_amount'] ?? ($prefill['base_rent'] ?? 0)) ?>">
            <div class="form-text">Mặc định lấy theo giá phòng. Bạn vẫn có thể chỉnh nếu muốn.</div>
          </div>

          <div class="col-md-4">
            <label class="form-label">Tiền cọc</label>
            <input type="number" step="0.01" name="deposit_amount" class="form-control"
                   value="<?= htmlspecialchars($_POST['deposit_amount'] ?? ($prefill['deposit_amount'] ?? 0)) ?>">
          </div>

          <div class="col-md-4">
            <label class="form-label">Ngày chốt hóa đơn (1–28)</label>
            <input type="number" name="billing_day" min="1" max="28" class="form-control"
                   value="<?= htmlspecialchars($_POST['billing_day'] ?? 1) ?>">
            <div class="form-text">
              Đây là ngày cố định mỗi tháng để chốt tiền phòng/điện nước và lập hóa đơn (ví dụ chọn 5 → ngày 5 hàng tháng).
            </div>
          </div>

          <div class="col-md-8">
            <label class="form-label">Ghi chú</label>
            <input type="text" name="note" class="form-control"
                   value="<?= htmlspecialchars($_POST['note'] ?? '') ?>">
          </div>

          <div class="col-12">
            <label class="form-label">Điều khoản hợp đồng</label>
            <textarea name="terms_text" class="form-control" rows="7"
              placeholder="Nhập điều khoản..."><?= htmlspecialchars($_POST['terms_text'] ?? $defaultTerms) ?></textarea>
          </div>

          <div class="col-12">
            <button class="btn btn-primary">
              <i class="bi bi-check2-circle"></i> Tạo hợp đồng
            </button>
          </div>

        </div>
      </form>

    </div>
  </div>
</section>

<script>
(function(){
  const roomSel = document.getElementById('room_id');
  const rentInp = document.getElementById('rent_amount');
  if(!roomSel || !rentInp) return;

  function applyRent(){
    const opt = roomSel.options[roomSel.selectedIndex];
    const rent = opt?.dataset?.rent;
    if (rent !== undefined && (rentInp.value === '' || Number(rentInp.value) === 0)) {
      rentInp.value = rent;
    }
  }

  roomSel.addEventListener('change', applyRent);
  applyRent();
})();
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
