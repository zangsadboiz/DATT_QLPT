<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/vietqr.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$role = (string)($_SESSION['role_name'] ?? '');
if (!in_array($role, ['ADMIN','STAFF'], true)) {
    admin_redirect('modules/dashboard/index.php', ['forbidden' => 1]);
}

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) admin_redirect('modules/phi_dangtin/index.php', ['err'=>'missing_id']);

/**
 * Re-calc publish_status của phòng theo tình trạng phí:
 * - Nếu phòng đang HIDDEN => không tự động đổi (tôn trọng lệnh ẩn).
 * - Nếu building chưa APPROVED => luôn PENDING.
 * - Nếu có PAID còn hạn => APPROVED.
 * - Ngược lại => PENDING.
 */
function recalc_room_publish_status(mysqli $conn, int $roomId): void
{
    $now = date('Y-m-d H:i:s');

    $sql = "
        SELECT r.publish_status,
               b.building_status,
               MAX(CASE WHEN si.invoice_type='LISTING_FEE' AND si.status='PAID' THEN si.active_until ELSE NULL END) AS max_until
        FROM rooms r
        JOIN buildings b ON b.building_id = r.building_id
        LEFT JOIN service_invoices si ON si.room_id = r.room_id
        WHERE r.room_id = ?
        GROUP BY r.room_id
        LIMIT 1
    ";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $roomId);
    mysqli_stmt_execute($stmt);
    $rs = mysqli_stmt_get_result($stmt);
    $row = $rs ? mysqli_fetch_assoc($rs) : null;
    mysqli_stmt_close($stmt);

    if (!$row) return;

    $cur = (string)($row['publish_status'] ?? '');
    if ($cur === 'HIDDEN') {
        // phòng bị ẩn thì không tự bật lại
        return;
    }

    $buildingStatus = (string)($row['building_status'] ?? '');
    $maxUntil = (string)($row['max_until'] ?? '');

    $newStatus = 'PENDING';
    if ($buildingStatus === 'APPROVED' && $maxUntil !== '' && $maxUntil >= $now) {
        $newStatus = 'APPROVED';
    }

    $stmtU = mysqli_prepare($conn, "UPDATE rooms SET publish_status=? WHERE room_id=? LIMIT 1");
    mysqli_stmt_bind_param($stmtU, "si", $newStatus, $roomId);
    mysqli_stmt_execute($stmtU);
    mysqli_stmt_close($stmtU);
}

$sql = "
  SELECT si.*,
         r.room_code, r.room_id, r.publish_status,
         b.building_code, b.building_name, b.building_id, b.building_status,
         u.full_name AS owner_name, u.username AS owner_username
  FROM service_invoices si
  JOIN rooms r ON r.room_id = si.room_id
  JOIN buildings b ON b.building_id = r.building_id
  JOIN users u ON u.user_id = si.owner_user_id
  WHERE si.svc_invoice_id = ? AND si.invoice_type='LISTING_FEE'
  LIMIT 1
";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$rs = mysqli_stmt_get_result($stmt);
$inv = $rs ? mysqli_fetch_assoc($rs) : null;
mysqli_stmt_close($stmt);

if (!$inv) admin_redirect('modules/phi_dangtin/index.php', ['err'=>'not_found']);

$errors = [];
$msg = (string)($_GET['msg'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');
    $adminId = (int)($_SESSION['user_id'] ?? 0);
    $roomId = (int)$inv['room_id'];

    if ($action === 'mark_paid') {
        if (!in_array($inv['status'], ['WAITING_CONFIRM','UNPAID'], true)) {
            $errors[] = 'Trạng thái không hợp lệ để xác nhận.';
        } else {
            $days = (int)($inv['period_days'] ?? 30);

            $sqlU = "
              UPDATE service_invoices
              SET status='PAID',
                  confirmed_by=?,
                  confirmed_at=NOW(),
                  paid_at=COALESCE(paid_at, NOW()),
                  active_until=DATE_ADD(COALESCE(paid_at, NOW()), INTERVAL ? DAY)
              WHERE svc_invoice_id=?
              LIMIT 1
            ";
            $stmtU = mysqli_prepare($conn, $sqlU);
            mysqli_stmt_bind_param($stmtU, "iii", $adminId, $days, $id);
            mysqli_stmt_execute($stmtU);
            mysqli_stmt_close($stmtU);

            // Quan trọng: cập nhật publish_status của phòng theo phí
            recalc_room_publish_status($conn, $roomId);

            admin_redirect('modules/phi_dangtin/view.php', ['id'=>$id, 'msg'=>'paid']);
        }
    }

    if ($action === 'reject') {
        if (!in_array($inv['status'], ['WAITING_CONFIRM','UNPAID'], true)) {
            $errors[] = 'Trạng thái không hợp lệ để từ chối.';
        } else {
            $note = trim((string)($_POST['reject_note'] ?? ''));

            $sqlU = "
                UPDATE service_invoices
                SET status='REJECTED',
                    confirmed_by=?,
                    confirmed_at=NOW(),
                    payer_note=?
                WHERE svc_invoice_id=?
                LIMIT 1
            ";
            $stmtU = mysqli_prepare($conn, $sqlU);
            mysqli_stmt_bind_param($stmtU, "isi", $adminId, $note, $id);
            mysqli_stmt_execute($stmtU);
            mysqli_stmt_close($stmtU);

            // Quan trọng: nếu bị từ chối phí => phòng quay về PENDING (nếu không HIDDEN)
            recalc_room_publish_status($conn, $roomId);

            admin_redirect('modules/phi_dangtin/view.php', ['id'=>$id, 'msg'=>'rejected']);
        }
    }

    // Thêm nút "Hủy" cho admin (khác REJECT: hủy nghiệp vụ)
    if ($action === 'cancel') {
        if (!in_array($inv['status'], ['UNPAID','WAITING_CONFIRM'], true)) {
            $errors[] = 'Trạng thái không hợp lệ để hủy.';
        } else {
            $note = trim((string)($_POST['cancel_note'] ?? ''));

            $sqlU = "
                UPDATE service_invoices
                SET status='CANCELLED',
                    confirmed_by=?,
                    confirmed_at=NOW(),
                    payer_note=?
                WHERE svc_invoice_id=?
                LIMIT 1
            ";
            $stmtU = mysqli_prepare($conn, $sqlU);
            mysqli_stmt_bind_param($stmtU, "isi", $adminId, $note, $id);
            mysqli_stmt_execute($stmtU);
            mysqli_stmt_close($stmtU);

            // Hủy phí => phòng quay về PENDING (nếu không HIDDEN)
            recalc_room_publish_status($conn, $roomId);

            admin_redirect('modules/phi_dangtin/view.php', ['id'=>$id, 'msg'=>'cancelled']);
        }
    }
}

// reload invoice để hiển thị mới nhất
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$rs = mysqli_stmt_get_result($stmt);
$inv = $rs ? mysqli_fetch_assoc($rs) : $inv;
mysqli_stmt_close($stmt);

$qrUrl = vietqr_image_url((int)$inv['amount'], (string)$inv['add_info']);

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="pagetitle">
  <h1>Chi tiết phí đăng tin</h1>
</div>

<section class="section">
  <div class="card">
    <div class="card-body pt-3">

      <?php if ($msg === 'paid'): ?>
        <div class="alert alert-success">Đã xác nhận thanh toán. Phòng được bật trạng thái theo phí.</div>
      <?php elseif ($msg === 'rejected'): ?>
        <div class="alert alert-warning">Đã từ chối. Phòng đã chuyển về trạng thái chờ duyệt (PENDING) nếu không bị ẩn.</div>
      <?php elseif ($msg === 'cancelled'): ?>
        <div class="alert alert-secondary">Đã hủy hóa đơn. Phòng đã chuyển về trạng thái chờ duyệt (PENDING) nếu không bị ẩn.</div>
      <?php endif; ?>

      <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
          <ul class="mb-0"><?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul>
        </div>
      <?php endif; ?>

      <div class="row g-3">
        <div class="col-md-6">
          <div><strong>Phòng:</strong> <?= htmlspecialchars((string)$inv['room_code']) ?></div>
          <div><strong>Dãy/Tòa:</strong> <?= htmlspecialchars((string)$inv['building_code'].' - '.(string)$inv['building_name']) ?></div>
          <div><strong>Chủ trọ:</strong> <?= htmlspecialchars((string)$inv['owner_name'].' ('.(string)$inv['owner_username'].')') ?></div>
          <div><strong>Số tiền:</strong> <?= number_format((float)$inv['amount'],0,',','.') ?> VND</div>
          <div><strong>Nội dung CK:</strong> <code><?= htmlspecialchars((string)$inv['add_info']) ?></code></div>
          <div><strong>Trạng thái hóa đơn:</strong> <?= htmlspecialchars((string)$inv['status']) ?></div>
          <div><strong>Hiệu lực đến:</strong> <?= htmlspecialchars((string)($inv['active_until'] ?? '')) ?></div>
          <div><strong>Trạng thái phòng hiện tại:</strong> <?= htmlspecialchars((string)($inv['publish_status'] ?? '')) ?></div>
        </div>

        <div class="col-md-6">
          <div class="mb-2"><strong>VietQR</strong></div>
          <img src="<?= htmlspecialchars($qrUrl) ?>" alt="VietQR" style="max-width:260px;border:1px solid #ddd;border-radius:8px;">
          <div class="text-muted mt-2">
            Nếu không hiện QR: chuyển khoản thủ công theo thông tin + nội dung CK.
          </div>
        </div>

        <div class="col-md-12">
          <hr>
          <h5>Minh chứng thanh toán</h5>
          <?php if (!empty($inv['proof_image'])): ?>
            <div class="mb-2">
              <img src="<?= (defined('ADMIN_BASE_PATH') ? ADMIN_BASE_PATH : '/quanlyphongtro/admin') ?>/uploads/payments/<?= htmlspecialchars((string)$inv['proof_image']) ?>"
                   style="max-width:420px;border:1px solid #ddd;border-radius:8px;" alt="Proof">
            </div>
          <?php else: ?>
            <div class="text-muted">Chủ trọ chưa upload minh chứng.</div>
          <?php endif; ?>
        </div>

        <div class="col-md-12 d-flex gap-2 flex-wrap">
          <?php if (in_array($inv['status'], ['UNPAID','WAITING_CONFIRM'], true)): ?>
            <form method="post">
              <input type="hidden" name="action" value="mark_paid">
              <button class="btn btn-success" type="submit" onclick="return confirm('Xác nhận đã thanh toán?');">Xác nhận PAID</button>
            </form>

            <form method="post" class="d-flex gap-2">
              <input type="hidden" name="action" value="reject">
              <input class="form-control" name="reject_note" placeholder="Lý do từ chối (tuỳ chọn)" style="max-width:360px;">
              <button class="btn btn-warning" type="submit" onclick="return confirm('Từ chối minh chứng?');">Từ chối</button>
            </form>

            <form method="post" class="d-flex gap-2">
              <input type="hidden" name="action" value="cancel">
              <input class="form-control" name="cancel_note" placeholder="Lý do hủy (tuỳ chọn)" style="max-width:360px;">
              <button class="btn btn-secondary" type="submit" onclick="return confirm('Hủy hóa đơn này?');">Hủy</button>
            </form>
          <?php endif; ?>

          <a class="btn btn-outline-secondary" href="index.php">Quay lại</a>
        </div>

      </div>
    </div>
  </div>
</section>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
