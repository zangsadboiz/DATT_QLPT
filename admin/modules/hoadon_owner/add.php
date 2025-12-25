<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';

$role = $_SESSION['role_name'] ?? '';
$user_id = (int)($_SESSION['user_id'] ?? 0);

if ($role !== 'LANDLORD' || $user_id <= 0) {
    header('Location: /quanlyphongtro/admin/index.php?error=no_permission');
    exit;
}

function gen_invoice_code(int $contract_id): string {
    return 'INV' . date('YmdHis') . '-' . $contract_id . '-' . random_int(10, 99);
}

$errors = [];
$info = '';

/* ===== Contracts list (my ACTIVE contracts) ===== */
$contracts = mysqli_query($conn, "
    SELECT c.contract_id, c.contract_code, c.rent_amount, c.billing_day,
           r.room_id, r.room_code, b.building_name
    FROM contracts c
    JOIN rooms r ON r.room_id=c.room_id
    JOIN buildings b ON b.building_id=r.building_id
    WHERE c.contract_status='ACTIVE' AND b.owner_id=$user_id
    ORDER BY c.contract_id DESC
");

/* ===== GET selection (for UI only) ===== */
$contract_id = (int)($_GET['contract_id'] ?? 0);
$month_raw = trim($_GET['month'] ?? date('Y-m'));
if (!preg_match('/^\d{4}-\d{2}$/', $month_raw)) $month_raw = date('Y-m');
$invoice_month = $month_raw . '-01';

$previewContract = null;
if ($contract_id > 0) {
    $rs = mysqli_query($conn, "
        SELECT c.contract_id, c.contract_code, c.rent_amount, c.billing_day,
               r.room_id, r.room_code, b.building_name
        FROM contracts c
        JOIN rooms r ON r.room_id=c.room_id
        JOIN buildings b ON b.building_id=r.building_id
        WHERE c.contract_id=$contract_id AND c.contract_status='ACTIVE' AND b.owner_id=$user_id
        LIMIT 1
    ");
    $previewContract = $rs ? mysqli_fetch_assoc($rs) : null;
}

/* ===== POST create invoice ===== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create') {
    $contract_id = (int)($_POST['contract_id'] ?? 0);
    $month_raw = trim($_POST['month'] ?? '');
    $issue_date = $_POST['issue_date'] ?? date('Y-m-d');
    $discount = (float)($_POST['discount'] ?? 0);
    $note = trim($_POST['note'] ?? '');

    if ($contract_id <= 0) $errors[] = 'Vui lòng chọn hợp đồng.';
    if (!preg_match('/^\d{4}-\d{2}$/', $month_raw)) $errors[] = 'Tháng hóa đơn không hợp lệ (YYYY-MM).';

    $invoice_month = $month_raw . '-01';

    // Load contract + owner check (mandatory)
    $rs = mysqli_query($conn, "
        SELECT c.contract_id, c.contract_code, c.rent_amount, c.billing_day,
               r.room_id, r.room_code,
               b.owner_id
        FROM contracts c
        JOIN rooms r ON r.room_id=c.room_id
        JOIN buildings b ON b.building_id=r.building_id
        WHERE c.contract_id=$contract_id
          AND c.contract_status='ACTIVE'
          AND b.owner_id=$user_id
        LIMIT 1
    ");
    $ct = $rs ? mysqli_fetch_assoc($rs) : null;
    if (!$ct) $errors[] = 'Hợp đồng không tồn tại hoặc không thuộc chủ trọ này.';

    // Prevent duplicate invoice for same contract & month (unique uq_invoice_contract_month)
    if (!$errors) {
        $mEsc = mysqli_real_escape_string($conn, $invoice_month);
        $chk = mysqli_query($conn, "
            SELECT invoice_id
            FROM invoices
            WHERE contract_id=$contract_id AND invoice_month='$mEsc'
            LIMIT 1
        ");
        $exist = $chk ? mysqli_fetch_assoc($chk) : null;
        if ($exist) {
            header('Location: view.php?id='.(int)$exist['invoice_id'].'&info=exists');
            exit;
        }
    }

    // Build invoice items server-side (NEVER rely on form to include rent)
    $itemsToInsert = [];
    if (!$errors) {
        $room_id = (int)$ct['room_id'];
        $rent = (float)($ct['rent_amount'] ?? 0);

        if ($rent <= 0) {
            // vẫn cho tạo, nhưng cảnh báo để bạn nhìn ra vấn đề dữ liệu hợp đồng/phòng
            $errors[] = 'Giá thuê trong hợp đồng đang = 0. Hãy kiểm tra rent_amount của hợp đồng.';
        } else {
            // Rent item
            $itemsToInsert[] = [
                'service_id' => null,
                'item_name'  => 'Tiền phòng tháng '.$month_raw,
                'unit_name'  => 'tháng',
                'quantity'   => 1,
                'unit_price' => $rent,
            ];
        }

        // FIXED services
        $sv = mysqli_query($conn, "
            SELECT s.service_id, s.service_name, s.unit_name,
                   rs.price, rs.quantity_default
            FROM room_services rs
            JOIN services s ON s.service_id=rs.service_id
            WHERE rs.room_id=$room_id AND rs.is_active=1 AND s.is_active=1 AND s.service_type='FIXED'
            ORDER BY s.service_id ASC
        ");
        while ($row = $sv ? mysqli_fetch_assoc($sv) : null) {
            if (!$row) break;
            $price = (float)($row['price'] ?? 0);
            $qty = (float)($row['quantity_default'] ?? 1);
            if ($qty <= 0) $qty = 1;
            // Cho phép price=0 (dịch vụ miễn phí) nhưng vẫn hiển thị
            $itemsToInsert[] = [
                'service_id' => (int)$row['service_id'],
                'item_name'  => $row['service_name'],
                'unit_name'  => $row['unit_name'] ?? '',
                'quantity'   => $qty,
                'unit_price' => $price,
            ];
        }

        // Manual extra items from form (optional)
        $item_name = $_POST['item_name'] ?? [];
        $unit_name = $_POST['unit_name'] ?? [];
        $qty       = $_POST['qty'] ?? [];
        $price     = $_POST['price'] ?? [];

        if (is_array($item_name)) {
            for ($k=0; $k<count($item_name); $k++) {
                $name = trim($item_name[$k] ?? '');
                if ($name === '') continue;

                $u  = trim($unit_name[$k] ?? '');
                $qv = (float)($qty[$k] ?? 1);
                $pv = (float)($price[$k] ?? 0);
                if ($qv <= 0) $qv = 1;

                $itemsToInsert[] = [
                    'service_id' => null,
                    'item_name'  => $name,
                    'unit_name'  => $u,
                    'quantity'   => $qv,
                    'unit_price' => $pv,
                ];
            }
        }

        // If still empty => block create
        if (count($itemsToInsert) === 0) {
            $errors[] = 'Không có mục thu nào để tạo hóa đơn.';
        }
    }

    if (!$errors) {
        // due_date = first day of month + (billing_day - 1), clamp 1..28
        $bd = (int)($ct['billing_day'] ?? 1);
        if ($bd < 1) $bd = 1;
        if ($bd > 28) $bd = 28;
        $due_date = date('Y-m-d', strtotime($invoice_month . " +".($bd-1)." day"));

        mysqli_begin_transaction($conn);

        // Insert invoice
        $invoice_code = gen_invoice_code($contract_id);
        $okInvoice = false;

        $escNote  = mysqli_real_escape_string($conn, $note);
        $escMonth = mysqli_real_escape_string($conn, $invoice_month);
        $escIssue = mysqli_real_escape_string($conn, $issue_date);
        $escDue   = mysqli_real_escape_string($conn, $due_date);

        for ($i=0; $i<5; $i++) {
            $escCode = mysqli_real_escape_string($conn, $invoice_code);
            try {
                $ins = mysqli_query($conn, "
                    INSERT INTO invoices(invoice_code, contract_id, invoice_month, issue_date, due_date,
                                         subtotal, discount, total_amount, invoice_status, note)
                    VALUES('$escCode', $contract_id, '$escMonth', '$escIssue', '$escDue',
                           0, $discount, 0, 'ISSUED', '$escNote')
                ");
            } catch (mysqli_sql_exception $e) {
                $ins = false;
            }

            if ($ins) { $okInvoice = true; break; }
            $invoice_code = gen_invoice_code($contract_id);
        }

        if (!$okInvoice) {
            mysqli_rollback($conn);
            $errors[] = 'Không tạo được hóa đơn (trùng mã hoặc trùng tháng).';
        } else {
            $invoice_id = (int)mysqli_insert_id($conn);

            // Insert items (if any item fails => rollback)
            $subtotal = 0.0;
            foreach ($itemsToInsert as $it) {
                $name = trim((string)$it['item_name']);
                if ($name === '') continue;

                $u  = trim((string)($it['unit_name'] ?? ''));
                $qv = (float)($it['quantity'] ?? 1);
                $pv = (float)($it['unit_price'] ?? 0);
                if ($qv <= 0) $qv = 1;

                $amt = $qv * $pv;
                $subtotal += $amt;

                $sid = $it['service_id'];
                $sidSql = ($sid === null) ? "NULL" : (int)$sid;

                $escName = mysqli_real_escape_string($conn, $name);
                $escUnit = mysqli_real_escape_string($conn, $u);

                $okItem = mysqli_query($conn, "
                    INSERT INTO invoice_items(invoice_id, service_id, item_name, unit_name, quantity, unit_price, amount)
                    VALUES($invoice_id, $sidSql, '$escName', '$escUnit', $qv, $pv, $amt)
                ");

                if (!$okItem) {
                    $err = mysqli_error($conn);
                    mysqli_rollback($conn);
                    $errors[] = 'Lỗi tạo dòng hóa đơn: '.$err;
                    break;
                }
            }

            if (!$errors) {
                $total = max(0, $subtotal - $discount);

                $okUp = mysqli_query($conn, "
                    UPDATE invoices
                    SET subtotal=$subtotal, total_amount=$total
                    WHERE invoice_id=$invoice_id
                    LIMIT 1
                ");

                if (!$okUp) {
                    $err = mysqli_error($conn);
                    mysqli_rollback($conn);
                    $errors[] = 'Không cập nhật được tổng tiền hóa đơn: '.$err;
                } else {
                    mysqli_commit($conn);
                    header('Location: view.php?id='.$invoice_id);
                    exit;
                }
            }
        }
    }
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="pagetitle d-flex justify-content-between align-items-center">
  <h1>Tạo hóa đơn</h1>
  <a class="btn btn-outline-secondary" href="index.php"><i class="bi bi-arrow-left"></i> Quay lại</a>
</div>

<?php if (!empty($_GET['info']) && $_GET['info'] === 'exists'): ?>
  <div class="alert alert-info">Hóa đơn tháng này đã tồn tại, hệ thống đã chuyển sang hóa đơn cũ.</div>
<?php endif; ?>

<?php if ($errors): ?>
  <div class="alert alert-danger">
    <ul class="mb-0">
      <?php foreach($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

<section class="section">
  <div class="card">
    <div class="card-body pt-3">

      <form method="get" class="row g-3 mb-3">
        <div class="col-md-6">
          <label class="form-label">Hợp đồng</label>
          <select name="contract_id" class="form-select" required>
            <option value="0">-- Chọn hợp đồng --</option>
            <?php while($c = $contracts ? mysqli_fetch_assoc($contracts) : null): if(!$c) break; ?>
              <option value="<?= (int)$c['contract_id'] ?>" <?= ((int)$c['contract_id']===$contract_id?'selected':'') ?>>
                <?= htmlspecialchars($c['building_name'].' - '.$c['room_code'].' | '.$c['contract_code']) ?>
              </option>
            <?php endwhile; ?>
          </select>
          <?php if ($previewContract): ?>
            <div class="form-text">
              Giá thuê (rent_amount): <b><?= number_format((float)$previewContract['rent_amount']) ?></b>
              | Ngày chốt: <b><?= (int)$previewContract['billing_day'] ?></b>
            </div>
          <?php endif; ?>
        </div>

        <div class="col-md-3">
          <label class="form-label">Tháng (YYYY-MM)</label>
          <input name="month" class="form-control" value="<?= htmlspecialchars($month_raw) ?>" required>
        </div>

        <div class="col-md-3 d-flex align-items-end">
          <button class="btn btn-outline-primary w-100"><i class="bi bi-arrow-repeat"></i> Nạp</button>
        </div>
      </form>

      <form method="post">
        <input type="hidden" name="action" value="create">
        <input type="hidden" name="contract_id" value="<?= (int)$contract_id ?>">
        <input type="hidden" name="month" value="<?= htmlspecialchars($month_raw) ?>">

        <div class="row g-3">
          <div class="col-md-3">
            <label class="form-label">Ngày xuất</label>
            <input type="date" name="issue_date" class="form-control" value="<?= htmlspecialchars($_POST['issue_date'] ?? date('Y-m-d')) ?>">
          </div>

          <div class="col-md-3">
            <label class="form-label">Giảm giá</label>
            <input type="number" step="0.01" name="discount" class="form-control" value="<?= htmlspecialchars($_POST['discount'] ?? 0) ?>">
          </div>

          <div class="col-md-6">
            <label class="form-label">Ghi chú</label>
            <input name="note" class="form-control" value="<?= htmlspecialchars($_POST['note'] ?? '') ?>">
          </div>

          <div class="col-12">
            <label class="form-label">Mục thu thêm (điện/nước/phát sinh) (tùy chọn)</label>
            <div class="table-responsive">
              <table class="table table-bordered align-middle">
                <thead class="table-light">
                  <tr>
                    <th>Tên mục thu</th>
                    <th width="120">ĐVT</th>
                    <th width="120">Số lượng</th>
                    <th width="160">Đơn giá</th>
                  </tr>
                </thead>
                <tbody>
                  <?php for($i=0;$i<5;$i++): ?>
                    <tr>
                      <td><input name="item_name[]" class="form-control" value=""></td>
                      <td><input name="unit_name[]" class="form-control" value=""></td>
                      <td><input name="qty[]" type="number" step="0.01" class="form-control" value="1"></td>
                      <td><input name="price[]" type="number" step="0.01" class="form-control" value="0"></td>
                    </tr>
                  <?php endfor; ?>
                </tbody>
              </table>
            </div>
            <div class="form-text">
              Hệ thống sẽ tự thêm <b>Tiền phòng</b> + <b>dịch vụ FIXED</b> theo room_services khi tạo.
            </div>
          </div>

          <div class="col-12">
            <button class="btn btn-primary" <?= ($contract_id<=0?'disabled':'') ?>>
              <i class="bi bi-check2-circle"></i> Tạo hóa đơn
            </button>
          </div>
        </div>

      </form>

    </div>
  </div>
</section>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

