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

$error = '';
$success = '';

// Get landlord's rooms with tenants
$roomsQuery = "
    SELECT r.room_id, r.room_code, r.base_rent, 
           r.electricity_price, r.water_price, r.internet_price, r.parking_price,
           b.building_name, b.electricity_price as bld_elec, b.water_price as bld_water
    FROM rooms r
    JOIN buildings b ON b.building_id = r.building_id
    WHERE b.owner_id = $userId 
      AND r.deleted_at IS NULL
      AND r.room_status = 'OCCUPIED'
    ORDER BY b.building_name, r.room_code
";
$rooms = mysqli_query($conn, $roomsQuery);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $roomId = (int)($_POST['room_id'] ?? 0);
    $tenantId = (int)($_POST['tenant_id'] ?? 0);
    $periodMonth = $_POST['period_month'] ?? '';
    $rentAmount = (float)($_POST['rent_amount'] ?? 0);
    
    $electricityOld = (int)($_POST['electricity_old'] ?? 0);
    $electricityNew = (int)($_POST['electricity_new'] ?? 0);
    $electricityPrice = (float)($_POST['electricity_price'] ?? 0);
    $electricityAmount = ($electricityNew - $electricityOld) * $electricityPrice;
    
    $waterOld = (int)($_POST['water_old'] ?? 0);
    $waterNew = (int)($_POST['water_new'] ?? 0);
    $waterPrice = (float)($_POST['water_price'] ?? 0);
    $waterAmount = ($waterNew - $waterOld) * $waterPrice;
    
    $internetAmount = (float)($_POST['internet_amount'] ?? 0);
    $parkingAmount = (float)($_POST['parking_amount'] ?? 0);
    $otherAmount = (float)($_POST['other_amount'] ?? 0);
    $otherNote = trim($_POST['other_note'] ?? '');
    
    $totalAmount = $rentAmount + $electricityAmount + $waterAmount + $internetAmount + $parkingAmount + $otherAmount;
    $dueDate = $_POST['due_date'] ?? null;
    $note = trim($_POST['note'] ?? '');
    
    if ($roomId <= 0 || $periodMonth === '') {
        $error = 'Vui lòng chọn phòng và kỳ thanh toán.';
    } else {
        // Check duplicate
        $check = mysqli_fetch_assoc(mysqli_query($conn, 
            "SELECT payment_id FROM rental_payments WHERE room_id = $roomId AND period_month = '$periodMonth-01'"));
        if ($check) {
            $error = 'Phiếu thanh toán cho phòng này trong kỳ này đã tồn tại.';
        } else {
            $stmt = mysqli_prepare($conn, "
                INSERT INTO rental_payments (
                    room_id, tenant_id, period_month, rent_amount,
                    electricity_old, electricity_new, electricity_price, electricity_amount,
                    water_old, water_new, water_price, water_amount,
                    internet_amount, parking_amount, other_amount, other_note,
                    total_amount, due_date, note, status, created_by
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'PENDING', ?)
            ");
            $periodDate = $periodMonth . '-01';
            mysqli_stmt_bind_param($stmt, 'iisdiiddiidddddsdssi',
                $roomId, $tenantId, $periodDate, $rentAmount,
                $electricityOld, $electricityNew, $electricityPrice, $electricityAmount,
                $waterOld, $waterNew, $waterPrice, $waterAmount,
                $internetAmount, $parkingAmount, $otherAmount, $otherNote,
                $totalAmount, $dueDate, $note, $userId
            );
            
            if (mysqli_stmt_execute($stmt)) {
                header('Location: index.php?msg=created');
                exit;
            } else {
                $error = 'Có lỗi xảy ra: ' . mysqli_error($conn);
            }
        }
    }
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="pagetitle">
  <h1><i class="bi bi-plus-circle me-2"></i>Tạo phiếu thu tiền</h1>
  <nav>
    <ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="<?= ADMIN_BASE_PATH ?>/modules/dashboard/index.php">Dashboard</a></li>
      <li class="breadcrumb-item"><a href="index.php">Thanh toán</a></li>
      <li class="breadcrumb-item active">Tạo phiếu</li>
    </ol>
  </nav>
</div>

<section class="section">

<?php if ($error): ?>
  <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="card">
  <div class="card-header"><h5 class="mb-0">Thông tin phiếu thu</h5></div>
  <div class="card-body pt-4">
    <form method="post" id="paymentForm">
      
      <div class="row mb-3">
        <div class="col-md-4">
          <label class="form-label">Chọn phòng <span class="text-danger">*</span></label>
          <select class="form-select" name="room_id" id="roomSelect" required>
            <option value="">-- Chọn phòng đang có người thuê --</option>
            <?php while ($r = mysqli_fetch_assoc($rooms)): ?>
              <option value="<?= $r['room_id'] ?>" 
                      data-rent="<?= $r['base_rent'] ?>"
                      data-elec="<?= $r['electricity_price'] ?: $r['bld_elec'] ?: 3500 ?>"
                      data-water="<?= $r['water_price'] ?: $r['bld_water'] ?: 20000 ?>"
                      data-internet="<?= $r['internet_price'] ?: 0 ?>"
                      data-parking="<?= $r['parking_price'] ?: 0 ?>">
                <?= htmlspecialchars($r['building_name'] . ' - ' . $r['room_code']) ?>
              </option>
            <?php endwhile; ?>
          </select>
        </div>
        <div class="col-md-4">
          <label class="form-label">Kỳ thanh toán <span class="text-danger">*</span></label>
          <input type="month" class="form-control" name="period_month" value="<?= date('Y-m') ?>" required>
        </div>
        <div class="col-md-4">
          <label class="form-label">Hạn thanh toán</label>
          <input type="date" class="form-control" name="due_date" value="<?= date('Y-m-d', strtotime('+7 days')) ?>">
        </div>
      </div>
      
      <input type="hidden" name="tenant_id" value="0">
      
      <hr>
      <h6 class="text-muted mb-3">Chi phí</h6>
      
      <div class="row mb-3">
        <div class="col-md-4">
          <label class="form-label">Tiền phòng</label>
          <div class="input-group">
            <input type="number" class="form-control" name="rent_amount" id="rentAmount" value="0">
            <span class="input-group-text">đ</span>
          </div>
        </div>
      </div>
      
      <div class="row mb-3">
        <div class="col-md-3">
          <label class="form-label">Điện cũ (kWh)</label>
          <input type="number" class="form-control" name="electricity_old" id="elecOld" value="0">
        </div>
        <div class="col-md-3">
          <label class="form-label">Điện mới (kWh)</label>
          <input type="number" class="form-control" name="electricity_new" id="elecNew" value="0">
        </div>
        <div class="col-md-3">
          <label class="form-label">Đơn giá điện</label>
          <input type="number" class="form-control" name="electricity_price" id="elecPrice" value="3500">
        </div>
        <div class="col-md-3">
          <label class="form-label">Tiền điện</label>
          <input type="text" class="form-control bg-light" id="elecTotal" readonly value="0 đ">
        </div>
      </div>
      
      <div class="row mb-3">
        <div class="col-md-3">
          <label class="form-label">Nước cũ (m³)</label>
          <input type="number" class="form-control" name="water_old" id="waterOld" value="0">
        </div>
        <div class="col-md-3">
          <label class="form-label">Nước mới (m³)</label>
          <input type="number" class="form-control" name="water_new" id="waterNew" value="0">
        </div>
        <div class="col-md-3">
          <label class="form-label">Đơn giá nước</label>
          <input type="number" class="form-control" name="water_price" id="waterPrice" value="20000">
        </div>
        <div class="col-md-3">
          <label class="form-label">Tiền nước</label>
          <input type="text" class="form-control bg-light" id="waterTotal" readonly value="0 đ">
        </div>
      </div>
      
      <div class="row mb-3">
        <div class="col-md-3">
          <label class="form-label">Tiền Internet</label>
          <input type="number" class="form-control" name="internet_amount" id="internetAmount" value="0">
        </div>
        <div class="col-md-3">
          <label class="form-label">Tiền gửi xe</label>
          <input type="number" class="form-control" name="parking_amount" id="parkingAmount" value="0">
        </div>
        <div class="col-md-3">
          <label class="form-label">Phí khác</label>
          <input type="number" class="form-control" name="other_amount" id="otherAmount" value="0">
        </div>
        <div class="col-md-3">
          <label class="form-label">Ghi chú phí khác</label>
          <input type="text" class="form-control" name="other_note" placeholder="VD: Sửa quạt...">
        </div>
      </div>
      
      <hr>
      
      <div class="row mb-3">
        <div class="col-md-4">
          <h4 class="text-primary">Tổng cộng: <span id="grandTotal">0</span> đ</h4>
        </div>
      </div>
      
      <div class="mb-3">
        <label class="form-label">Ghi chú</label>
        <textarea class="form-control" name="note" rows="2" placeholder="Ghi chú thêm..."></textarea>
      </div>
      
      <div class="d-flex gap-2">
        <button type="submit" class="btn btn-success"><i class="bi bi-check-circle me-1"></i>Tạo phiếu</button>
        <a href="index.php" class="btn btn-secondary"><i class="bi bi-arrow-left me-1"></i>Quay lại</a>
      </div>
    </form>
  </div>
</div>

</section>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const roomSelect = document.getElementById('roomSelect');
    const rentAmount = document.getElementById('rentAmount');
    const elecPrice = document.getElementById('elecPrice');
    const waterPrice = document.getElementById('waterPrice');
    const internetAmount = document.getElementById('internetAmount');
    const parkingAmount = document.getElementById('parkingAmount');
    
    // Auto-fill when room selected
    roomSelect.addEventListener('change', function() {
        const opt = this.options[this.selectedIndex];
        if (opt.value) {
            rentAmount.value = opt.dataset.rent || 0;
            elecPrice.value = opt.dataset.elec || 3500;
            waterPrice.value = opt.dataset.water || 20000;
            internetAmount.value = opt.dataset.internet || 0;
            parkingAmount.value = opt.dataset.parking || 0;
            calcTotal();
        }
    });
    
    // Calculate totals
    function calcTotal() {
        const elecOld = parseInt(document.getElementById('elecOld').value) || 0;
        const elecNew = parseInt(document.getElementById('elecNew').value) || 0;
        const elecP = parseFloat(elecPrice.value) || 0;
        const elecT = (elecNew - elecOld) * elecP;
        document.getElementById('elecTotal').value = elecT.toLocaleString() + ' đ';
        
        const waterOld = parseInt(document.getElementById('waterOld').value) || 0;
        const waterNew = parseInt(document.getElementById('waterNew').value) || 0;
        const waterP = parseFloat(waterPrice.value) || 0;
        const waterT = (waterNew - waterOld) * waterP;
        document.getElementById('waterTotal').value = waterT.toLocaleString() + ' đ';
        
        const rent = parseFloat(rentAmount.value) || 0;
        const internet = parseFloat(internetAmount.value) || 0;
        const parking = parseFloat(parkingAmount.value) || 0;
        const other = parseFloat(document.getElementById('otherAmount').value) || 0;
        
        const total = rent + elecT + waterT + internet + parking + other;
        document.getElementById('grandTotal').textContent = total.toLocaleString();
    }
    
    // Add event listeners
    ['elecOld', 'elecNew', 'elecPrice', 'waterOld', 'waterNew', 'waterPrice', 
     'rentAmount', 'internetAmount', 'parkingAmount', 'otherAmount'].forEach(id => {
        document.getElementById(id).addEventListener('input', calcTotal);
    });
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
