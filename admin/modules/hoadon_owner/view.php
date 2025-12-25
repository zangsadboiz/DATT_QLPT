<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';

$role = $_SESSION['role_name'] ?? '';
$user_id = (int)($_SESSION['user_id'] ?? 0);

if ($role !== 'LANDLORD' || $user_id <= 0) {
  header('Location: /quanlyphongtro/admin/index.php?error=no_permission');
  exit;
}

function badge_invoice_status(?string $s): string {
  return match($s) {
    'PAID'   => '<span class="badge bg-success">Đã thanh toán</span>',
    'ISSUED' => '<span class="badge bg-primary">Đã xuất</span>',
    'DRAFT'  => '<span class="badge bg-warning text-dark">Nháp</span>',
    'VOID'   => '<span class="badge bg-secondary">Đã hủy</span>',
    default  => '<span class="badge bg-light text-dark">Không rõ</span>',
  };
}
function vn_method(?string $m): string {
  return match($m) {
    'CASH' => 'Tiền mặt',
    'BANK_TRANSFER' => 'Chuyển khoản',
    'E_WALLET' => 'Ví điện tử',
    'OTHER' => 'Khác',
    default => 'Không rõ',
  };
}

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { header('Location: index.php?error=invalid'); exit; }

$rs = mysqli_query($conn, "
  SELECT i.*,
         c.contract_code,
         r.room_code,
         b.building_name
  FROM invoices i
  JOIN contracts c ON c.contract_id=i.contract_id
  JOIN rooms r ON r.room_id=c.room_id
  JOIN buildings b ON b.building_id=r.building_id
  WHERE i.invoice_id=$id AND b.owner_id=$user_id
  LIMIT 1
");
$inv = $rs ? mysqli_fetch_assoc($rs) : null;
if (!$inv) { header('Location: index.php?error=not_found'); exit; }

// Compute sums from items/payments (source of truth)
$sumItems = (float)(mysqli_fetch_assoc(mysqli_query($conn, "
  SELECT COALESCE(SUM(amount),0) AS s FROM invoice_items WHERE invoice_id=$id
"))['s'] ?? 0);

$paidSum = (float)(mysqli_fetch_assoc(mysqli_query($conn, "
  SELECT COALESCE(SUM(amount),0) AS s FROM payments WHERE invoice_id=$id
"))['s'] ?? 0);

$discount = (float)($inv['discount'] ?? 0);
$computedSubtotal = $sumItems;
$computedTotal = max(0, $computedSubtotal - $discount);
$remain = max(0, $computedTotal - $paidSum);

// Auto-repair header totals if mismatch (so dashboard/list also correct)
$dbSubtotal = (float)($inv['subtotal'] ?? 0);
$dbTotal = (float)($inv['total_amount'] ?? 0);
$needFix = (abs($dbSubtotal - $computedSubtotal) > 0.01) || (abs($dbTotal - $computedTotal) > 0.01);

if ($needFix && ($inv['invoice_status'] ?? '') !== 'VOID') {
  mysqli_query($conn, "
    UPDATE invoices
    SET subtotal=$computedSubtotal, total_amount=$computedTotal
    WHERE invoice_id=$id
    LIMIT 1
  ");
  // Refresh in-memory values for display
  $inv['subtotal'] = $computedSubtotal;
  $inv['total_amount'] = $computedTotal;
}

// Items + payments list
$items = mysqli_query($conn, "SELECT * FROM invoice_items WHERE invoice_id=$id ORDER BY item_id ASC");
$payments = mysqli_query($conn, "
  SELECT p.*, u.full_name AS received_name
  FROM payments p
  LEFT JOIN users u ON u.user_id=p.received_by
  WHERE p.invoice_id=$id
  ORDER BY p.paid_at DESC, p.payment_id DESC
");

/* ===== VietQR config (SỬA THEO TÀI KHOẢN NHẬN TIỀN) ===== */
$BANK_ID = 'VCB';               // VCB, ACB, TCB, BIDV...
$ACCOUNT_NO = '0123456789';     // STK nhận tiền
$ACCOUNT_NAME = 'NGUYEN VAN A'; // Tên chủ TK
$amountQr = (int)round($remain);
$addInfo = 'Thanh toan ' . ($inv['invoice_code'] ?? '');
$qrUrl = '';
if ($amountQr > 0 && $BANK_ID && $ACCOUNT_NO) {
  $qrUrl = "https://api.vietqr.io/image/{$BANK_ID}-{$ACCOUNT_NO}-compact2.png"
         . "?amount={$amountQr}"
         . "&addInfo=" . urlencode($addInfo)
         . "&accountName=" . urlencode($ACCOUNT_NAME);
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="pagetitle d-flex justify-content-between align-items-center">
  <h1>Chi tiết hóa đơn</h1>
  <div class="d-flex gap-2">
    <a class="btn btn-outline-secondary" href="index.php"><i class="bi bi-arrow-left"></i> Danh sách</a>
    <a class="btn btn-outline-secondary" href="print.php?id=<?= (int)$id ?>" target="_blank"><i class="bi bi-printer"></i> In</a>
    <?php if (($inv['invoice_status'] ?? '') !== 'VOID'): ?>
      <a class="btn btn-outline-danger" href="void.php?id=<?= (int)$id ?>" onclick="return confirm('Hủy hóa đơn này?');">
        <i class="bi bi-x-circle"></i> Hủy
      </a>
    <?php endif; ?>
  </div>
</div>

<?php if (!empty($_GET['info']) && $_GET['info'] === 'exists'): ?>
  <div class="alert alert-info">Hóa đơn tháng này đã tồn tại, bạn đang xem hóa đơn cũ.</div>
<?php endif; ?>

<?php if ($needFix): ?>
  <div class="alert alert-warning">
    Hệ thống đã tự đồng bộ lại tổng tiền theo chi tiết hóa đơn (invoice_items).
  </div>
<?php endif; ?>

<section class="section">
  <div class="row">

    <div class="col-lg-5">
      <div class="card"><div class="card-body pt-3">
        <h5 class="card-title">Thông tin</h5>
        <div><b>Mã hóa đơn:</b> <?= htmlspecialchars($inv['invoice_code'] ?? '') ?></div>
        <div><b>Tháng:</b> <?= htmlspecialchars($inv['invoice_month'] ?? '') ?></div>
        <div><b>Dãy/Tòa:</b> <?= htmlspecialchars($inv['building_name'] ?? '-') ?></div>
        <div><b>Phòng:</b> <?= htmlspecialchars($inv['room_code'] ?? '-') ?></div>
        <div><b>Mã HĐ:</b> <?= htmlspecialchars($inv['contract_code'] ?? '-') ?></div>
        <div><b>Ngày xuất:</b> <?= htmlspecialchars($inv['issue_date'] ?? '-') ?></div>
        <div><b>Hạn thanh toán:</b> <?= htmlspecialchars($inv['due_date'] ?? '-') ?></div>
        <div><b>Trạng thái:</b> <?= badge_invoice_status($inv['invoice_status'] ?? '') ?></div>
        <?php if (!empty($inv['note'])): ?>
          <div class="mt-2"><b>Ghi chú:</b> <?= htmlspecialchars($inv['note']) ?></div>
        <?php endif; ?>
      </div></div>

      <div class="card"><div class="card-body pt-3">
        <h5 class="card-title">Tổng tiền</h5>
        <div><b>Tạm tính:</b> <?= number_format((float)$inv['subtotal']) ?></div>
        <div><b>Giảm giá:</b> <?= number_format((float)$inv['discount']) ?></div>
        <div><b>Tổng:</b> <?= number_format((float)$inv['total_amount']) ?></div>
        <div><b>Đã thu:</b> <?= number_format($paidSum) ?></div>
        <div><b>Còn lại:</b> <?= number_format($remain) ?></div>

        <?php if ($remain > 0 && ($inv['invoice_status'] ?? '') !== 'VOID'): ?>
          <div class="mt-3 d-flex gap-2 flex-wrap">
            <a class="btn btn-primary" href="pay.php?id=<?= (int)$id ?>">
              <i class="bi bi-cash-coin"></i> Ghi nhận thanh toán
            </a>
          </div>
        <?php endif; ?>

        <?php if ($qrUrl): ?>
          <hr>
          <h6 class="mb-2">Quét QR để thanh toán</h6>
          <img src="<?= htmlspecialchars($qrUrl) ?>" alt="VietQR"
               style="max-width:260px;width:100%;height:auto;border:1px solid #ddd;border-radius:8px;padding:8px;">
          <div class="small text-muted mt-2">
            Số tiền: <b><?= number_format($amountQr) ?></b><br>
            Nội dung CK: <b><?= htmlspecialchars($inv['invoice_code'] ?? '') ?></b>
          </div>
        <?php endif; ?>

      </div></div>
    </div>

    <div class="col-lg-7">
      <div class="card"><div class="card-body pt-3">
        <h5 class="card-title">Chi tiết hóa đơn</h5>
        <table class="table table-bordered table-hover align-middle">
          <thead class="table-light">
            <tr>
              <th>Tên mục</th><th width="90">ĐVT</th><th width="110">SL</th><th width="140">Đơn giá</th><th width="140">Thành tiền</th>
            </tr>
          </thead>
          <tbody>
            <?php $has=false; while($it = $items ? mysqli_fetch_assoc($items) : null): $has=true; ?>
              <tr>
                <td><?= htmlspecialchars($it['item_name'] ?? '') ?></td>
                <td><?= htmlspecialchars($it['unit_name'] ?? '') ?></td>
                <td><?= htmlspecialchars($it['quantity'] ?? '') ?></td>
                <td><?= number_format((float)($it['unit_price'] ?? 0)) ?></td>
                <td><?= number_format((float)($it['amount'] ?? 0)) ?></td>
              </tr>
            <?php endwhile; ?>
            <?php if(!$has): ?><tr><td colspan="5" class="text-center text-muted">Chưa có item</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div></div>

      <div class="card"><div class="card-body pt-3">
        <h5 class="card-title">Lịch sử thanh toán</h5>
        <table class="table table-bordered table-hover align-middle">
          <thead class="table-light">
            <tr>
              <th>Ngày thu</th><th>Số tiền</th><th>Hình thức</th><th>Tham chiếu</th><th>Ghi chú</th>
            </tr>
          </thead>
          <tbody>
            <?php $has=false; while($p = $payments ? mysqli_fetch_assoc($payments) : null): $has=true; ?>
              <tr>
                <td><?= htmlspecialchars($p['paid_at'] ?? '-') ?></td>
                <td><?= number_format((float)($p['amount'] ?? 0)) ?></td>
                <td><?= htmlspecialchars(vn_method($p['method'] ?? '')) ?></td>
                <td><?= htmlspecialchars($p['reference_no'] ?? '-') ?></td>
                <td><?= htmlspecialchars($p['note'] ?? '-') ?></td>
              </tr>
            <?php endwhile; ?>
            <?php if(!$has): ?><tr><td colspan="5" class="text-center text-muted">Chưa có thanh toán</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div></div>
    </div>

  </div>
</section>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

