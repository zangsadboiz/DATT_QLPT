<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';

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

// load contract (and owner check)
$rs = mysqli_query($conn, "
  SELECT c.contract_id, c.room_id, c.contract_status, c.contract_code
  FROM contracts c
  JOIN rooms r ON r.room_id = c.room_id
  JOIN buildings b ON b.building_id = r.building_id
  WHERE c.contract_id = $id AND b.owner_id = $user_id
  LIMIT 1
");
$contract = $rs ? mysqli_fetch_assoc($rs) : null;
if (!$contract) {
    header('Location: index.php?error=not_found');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (($contract['contract_status'] ?? '') !== 'ACTIVE') {
        header('Location: view.php?id='.$id);
        exit;
    }

    $end_date = $_POST['end_date'] ?? date('Y-m-d');
    $reason = trim($_POST['reason'] ?? '');

    // end contract
    mysqli_query($conn, "
        UPDATE contracts
        SET contract_status='ENDED',
            end_date='".mysqli_real_escape_string($conn, $end_date)."',
            note=CONCAT(IFNULL(note,''), IF(IFNULL(note,'')='', '', ' | '), 'Kết thúc: ".mysqli_real_escape_string($conn, $reason)."')
        WHERE contract_id=$id
        LIMIT 1
    ");

    // update tenants move_out_date
    mysqli_query($conn, "
        UPDATE contract_tenants
        SET move_out_date='".mysqli_real_escape_string($conn, $end_date)."'
        WHERE contract_id=$id
    ");

    // free room
    $room_id = (int)$contract['room_id'];
    mysqli_query($conn, "UPDATE rooms SET room_status='VACANT' WHERE room_id=$room_id LIMIT 1");

    // Cập nhật trạng thái booking liên quan
    mysqli_query($conn, "
        UPDATE bookings 
        SET status = 'CHECKED_OUT', checked_out_at = NOW()
        WHERE contract_id = $id
    ");

    header('Location: view.php?id='.$id);
    exit;
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="pagetitle d-flex justify-content-between align-items-center">
  <h1>Kết thúc hợp đồng</h1>
  <a class="btn btn-outline-secondary" href="view.php?id=<?= (int)$id ?>"><i class="bi bi-arrow-left"></i> Quay lại</a>
</div>

<section class="section">
  <div class="card">
    <div class="card-body pt-3">

      <div class="alert alert-warning">
        Bạn đang kết thúc hợp đồng: <b><?= htmlspecialchars($contract['contract_code'] ?? '') ?></b>
      </div>

      <form method="post">
        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label">Ngày kết thúc</label>
            <input type="date" name="end_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
          </div>
          <div class="col-md-8">
            <label class="form-label">Lý do (tùy chọn)</label>
            <input type="text" name="reason" class="form-control" placeholder="Ví dụ: trả phòng, chuyển trọ...">
          </div>
          <div class="col-12">
            <button class="btn btn-danger" onclick="return confirm('Xác nhận kết thúc hợp đồng?');">
              <i class="bi bi-x-circle"></i> Xác nhận kết thúc
            </button>
          </div>
        </div>
      </form>

    </div>
  </div>
</section>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
