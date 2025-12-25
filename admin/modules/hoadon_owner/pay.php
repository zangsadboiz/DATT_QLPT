<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';

$role = $_SESSION['role_name'] ?? '';
$user_id = (int)($_SESSION['user_id'] ?? 0);

if ($role !== 'LANDLORD' || $user_id <= 0) {
  header('Location: /quanlyphongtro/admin/index.php?error=no_permission');
  exit;
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
  SELECT i.invoice_id, i.total_amount, i.invoice_status
  FROM invoices i
  JOIN contracts c ON c.contract_id=i.contract_id
  JOIN rooms r ON r.room_id=c.room_id
  JOIN buildings b ON b.building_id=r.building_id
  WHERE i.invoice_id=$id AND b.owner_id=$user_id
  LIMIT 1
");
$inv = $rs ? mysqli_fetch_assoc($rs) : null;
if (!$inv) { header('Location: index.php?error=not_found'); exit; }
if (($inv['invoice_status'] ?? '') === 'VOID') { header('Location: view.php?id='.$id); exit; }

$errors = [];

$paidSum = (float)(mysqli_fetch_assoc(mysqli_query($conn, "SELECT COALESCE(SUM(amount),0) AS s FROM payments WHERE invoice_id=$id"))['s'] ?? 0);
$totalAmount = (float)($inv['total_amount'] ?? 0);
$remain = max(0, $totalAmount - $paidSum);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $amount = (float)($_POST['amount'] ?? 0);
  $method = $_POST['method'] ?? 'CASH';
  $ref = trim($_POST['reference_no'] ?? '');
  $note = trim($_POST['note'] ?? '');

  $allowed = ['CASH','BANK_TRANSFER','E_WALLET','OTHER'];
  if (!in_array($method, $allowed, true)) $method = 'CASH';
  if ($amount <= 0) $errors[] = 'Số tiền phải > 0.';
  if ($amount > $remain) $errors[] = 'Số tiền thu lớn hơn số còn lại.';

  if (!$errors) {
    $escRef = mysqli_real_escape_string($conn, $ref);
    $escNote = mysqli_real_escape_string($conn, $note);

    mysqli_begin_transaction($conn);

    mysqli_query($conn, "
      INSERT INTO payments(invoice_id, paid_at, amount, method, received_by, reference_no, note)
      VALUES($id, NOW(), $amount, '$method', $user_id, ".($ref!==''?"'$escRef'":"NULL").", ".($note!==''?"'$escNote'":"NULL").")
    ");

    $paid2 = (float)(mysqli_fetch_assoc(mysqli_query($conn, "SELECT COALESCE(SUM(amount),0) AS s FROM payments WHERE invoice_id=$id"))['s'] ?? 0);
    $status = ($paid2 + 0.00001 >= $totalAmount) ? 'PAID' : 'ISSUED';

    mysqli_query($conn, "UPDATE invoices SET invoice_status='$status' WHERE invoice_id=$id LIMIT 1");

    mysqli_commit($conn);

    header('Location: view.php?id='.$id);
    exit;
  }
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="pagetitle d-flex justify-content-between align-items-center">
  <h1>Ghi nhận thanh toán</h1>
  <a class="btn btn-outline-secondary" href="view.php?id=<?= (int)$id ?>"><i class="bi bi-arrow-left"></i> Quay lại</a>
</div>

<?php if ($errors): ?>
  <div class="alert alert-danger">
    <ul class="mb-0"><?php foreach($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul>
  </div>
<?php endif; ?>

<section class="section">
  <div class="card">
    <div class="card-body pt-3">

      <div class="alert alert-info">
        Còn lại cần thu: <b><?= number_format($remain) ?></b>
      </div>

      <form method="post" class="row g-3">
        <div class="col-md-4">
          <label class="form-label">Số tiền thu</label>
          <input type="number" step="0.01" name="amount" class="form-control" required value="<?= htmlspecialchars($_POST['amount'] ?? $remain) ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label">Hình thức</label>
          <select name="method" class="form-select">
            <option value="CASH">Tiền mặt</option>
            <option value="BANK_TRANSFER">Chuyển khoản</option>
            <option value="E_WALLET">Ví điện tử</option>
            <option value="OTHER">Khác</option>
          </select>
        </div>
        <div class="col-md-4">
          <label class="form-label">Mã tham chiếu (tùy chọn)</label>
          <input name="reference_no" class="form-control" value="<?= htmlspecialchars($_POST['reference_no'] ?? '') ?>" placeholder="VD: FTxxxx, nội dung CK...">
        </div>
        <div class="col-12">
          <label class="form-label">Ghi chú</label>
          <input name="note" class="form-control" value="<?= htmlspecialchars($_POST['note'] ?? '') ?>" placeholder="VD: Thu tiền mặt trực tiếp...">
        </div>
        <div class="col-12">
          <button class="btn btn-primary"><i class="bi bi-check2-circle"></i> Lưu thanh toán</button>
        </div>
      </form>

    </div>
  </div>
</section>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

