<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/vietqr.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$role = (string)($_SESSION['role_name'] ?? '');
$userId = (int)($_SESSION['user_id'] ?? 0);

if ($role !== 'LANDLORD') {
    admin_redirect('modules/dashboard/index.php', ['forbidden'=>1]);
}

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) admin_redirect('modules/phi_dangtin_owner/index.php', ['err'=>'missing_id']);

$sql = "
  SELECT si.*,
         r.room_code,
         b.building_code, b.building_name
  FROM service_invoices si
  JOIN rooms r ON r.room_id = si.room_id
  JOIN buildings b ON b.building_id = r.building_id
  WHERE si.svc_invoice_id = ?
    AND si.invoice_type='LISTING_FEE'
    AND si.owner_user_id = ?
  LIMIT 1
";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "ii", $id, $userId);
mysqli_stmt_execute($stmt);
$rs = mysqli_stmt_get_result($stmt);
$inv = $rs ? mysqli_fetch_assoc($rs) : null;
mysqli_stmt_close($stmt);

if (!$inv) admin_redirect('modules/phi_dangtin_owner/index.php', ['err'=>'not_found']);

$errors = [];
$msg = (string)($_GET['msg'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!in_array($inv['status'], ['UNPAID','REJECTED'], true)) {
        $errors[] = 'Hóa đơn không ở trạng thái cho phép gửi minh chứng.';
    } else {
        $payerNote = trim((string)($_POST['payer_note'] ?? ''));

        $proofName = null;
        if (isset($_FILES['proof']) && is_array($_FILES['proof']) && ($_FILES['proof']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            if (($_FILES['proof']['error'] ?? UPLOAD_ERR_OK) === UPLOAD_ERR_OK) {
                $tmp  = (string)$_FILES['proof']['tmp_name'];
                $orig = (string)$_FILES['proof']['name'];
                $ext  = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
                $allow = ['jpg','jpeg','png','webp'];

                if (!in_array($ext, $allow, true)) {
                    $errors[] = 'Minh chứng chỉ hỗ trợ jpg/jpeg/png/webp.';
                } else {
                    $dir = __DIR__ . '/../../uploads/payments';
                    if (!is_dir($dir)) @mkdir($dir, 0777, true);

                    $proofName = 'proof_' . $id . '_' . time() . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
                    $dest = $dir . '/' . $proofName;

                    if (!move_uploaded_file($tmp, $dest)) {
                        $errors[] = 'Không thể lưu minh chứng.';
                        $proofName = null;
                    }
                }
            } else {
                $errors[] = 'Upload minh chứng bị lỗi.';
            }
        } else {
            $errors[] = 'Vui lòng chọn file minh chứng.';
        }

        if (empty($errors)) {
            $sqlU = "UPDATE service_invoices
                     SET status='WAITING_CONFIRM', proof_image=?, payer_note=?
                     WHERE svc_invoice_id=? AND owner_user_id=? LIMIT 1";
            $stmtU = mysqli_prepare($conn, $sqlU);
            mysqli_stmt_bind_param($stmtU, "ssii", $proofName, $payerNote, $id, $userId);
            mysqli_stmt_execute($stmtU);
            mysqli_stmt_close($stmtU);

            admin_redirect('modules/phi_dangtin_owner/view.php', ['id'=>$id, 'msg'=>'sent']);
        }
    }
}

$qrUrl = vietqr_image_url((int)$inv['amount'], (string)$inv['add_info']);

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="pagetitle">
  <h1>Thanh toán phí đăng tin</h1>
</div>

<section class="section">
  <div class="card">
    <div class="card-body pt-3">

      <?php if ($msg === 'sent'): ?>
        <div class="alert alert-success">Đã gửi minh chứng. Vui lòng chờ Admin xác nhận.</div>
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
          <div><strong>Số tiền:</strong> <?= number_format((float)$inv['amount'],0,',','.') ?> VND</div>
          <div><strong>Nội dung CK:</strong> <code><?= htmlspecialchars((string)$inv['add_info']) ?></code></div>
          <div><strong>Trạng thái:</strong> <?= htmlspecialchars((string)$inv['status']) ?></div>
          <div class="mt-2">
            <strong>Thông tin nhận tiền:</strong><br>
            BIN: <?= htmlspecialchars((string)$inv['bank_bin']) ?><br>
            STK: <?= htmlspecialchars((string)$inv['bank_account']) ?><br>
            Tên: <?= htmlspecialchars((string)$inv['bank_account_name']) ?>
          </div>
        </div>

        <div class="col-md-6">
          <div class="mb-2"><strong>Quét VietQR để chuyển khoản</strong></div>
          <img src="<?= htmlspecialchars($qrUrl) ?>" alt="VietQR" style="max-width:280px;border:1px solid #ddd;border-radius:8px;">
          <div class="text-muted mt-2">
            Nếu không hiện QR, bạn chuyển khoản thủ công và nhập đúng nội dung CK.
          </div>
        </div>

        <div class="col-md-12">
          <hr>
          <h5>Gửi minh chứng thanh toán</h5>

          <?php if (in_array($inv['status'], ['UNPAID','REJECTED'], true)): ?>
            <form method="post" enctype="multipart/form-data" class="row g-3">
              <div class="col-md-6">
                <label class="form-label">Ảnh minh chứng</label>
                <input class="form-control" type="file" name="proof" accept=".jpg,.jpeg,.png,.webp" required>
              </div>
              <div class="col-md-6">
                <label class="form-label">Ghi chú (mã GD, thời gian...)</label>
                <input class="form-control" name="payer_note" value="<?= htmlspecialchars((string)($inv['payer_note'] ?? '')) ?>">
              </div>
              <div class="col-12 d-flex gap-2">
                <button class="btn btn-primary" type="submit">Gửi minh chứng</button>
                <a class="btn btn-secondary" href="index.php">Quay lại</a>
              </div>
            </form>
          <?php else: ?>
            <div class="text-muted">Hóa đơn đang chờ xác nhận hoặc đã thanh toán.</div>
            <div class="mt-2">
              <a class="btn btn-secondary" href="index.php">Quay lại</a>
            </div>
          <?php endif; ?>

        </div>

      </div>

    </div>
  </div>
</section>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
