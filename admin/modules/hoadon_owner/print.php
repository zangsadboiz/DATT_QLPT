<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';

$role = $_SESSION['role_name'] ?? '';
$user_id = (int)($_SESSION['user_id'] ?? 0);

if ($role !== 'LANDLORD' || $user_id <= 0) exit('No permission');

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) exit('Invalid');

$rs = mysqli_query($conn, "
  SELECT i.*, c.contract_code, r.room_code, b.building_name, b.address
  FROM invoices i
  JOIN contracts c ON c.contract_id=i.contract_id
  JOIN rooms r ON r.room_id=c.room_id
  JOIN buildings b ON b.building_id=r.building_id
  WHERE i.invoice_id=$id AND b.owner_id=$user_id
  LIMIT 1
");
$inv = $rs ? mysqli_fetch_assoc($rs) : null;
if (!$inv) exit('Not found');

$items = mysqli_query($conn, "SELECT * FROM invoice_items WHERE invoice_id=$id ORDER BY item_id ASC");
$paid = (float)(mysqli_fetch_assoc(mysqli_query($conn, "SELECT COALESCE(SUM(amount),0) AS s FROM payments WHERE invoice_id=$id"))['s'] ?? 0);
$remain = max(0, (float)$inv['total_amount'] - $paid);
?>
<!doctype html>
<html lang="vi">
<head>
  <meta charset="utf-8">
  <title>In hóa đơn</title>
  <style>
    body{font-family:Arial,sans-serif;margin:24px;color:#111}
    h1{font-size:20px;margin:0 0 8px}
    .muted{color:#666}
    table{width:100%;border-collapse:collapse;margin-top:10px}
    th,td{border:1px solid #ddd;padding:8px;font-size:13px}
    th{background:#f5f5f5;text-align:left}
    .box{border:1px solid #ddd;padding:12px;border-radius:8px;margin-top:10px}
    .printbar{margin-bottom:12px}
    @media print {.printbar{display:none} body{margin:0}}
  </style>
</head>
<body>
<div class="printbar"><button onclick="window.print()">In</button></div>

<h1>HÓA ĐƠN THÁNG</h1>
<div class="muted">Mã: <b><?= htmlspecialchars($inv['invoice_code']) ?></b> | Tháng: <b><?= htmlspecialchars($inv['invoice_month']) ?></b></div>

<div class="box">
  <div><b>Dãy/Tòa:</b> <?= htmlspecialchars($inv['building_name'] ?? '-') ?></div>
  <div><b>Phòng:</b> <?= htmlspecialchars($inv['room_code'] ?? '-') ?></div>
  <div><b>Mã HĐ:</b> <?= htmlspecialchars($inv['contract_code'] ?? '-') ?></div>
  <div><b>Ngày xuất:</b> <?= htmlspecialchars($inv['issue_date'] ?? '-') ?> | <b>Hạn:</b> <?= htmlspecialchars($inv['due_date'] ?? '-') ?></div>
</div>

<table>
  <thead>
    <tr>
      <th>Tên mục</th><th>ĐVT</th><th>SL</th><th>Đơn giá</th><th>Thành tiền</th>
    </tr>
  </thead>
  <tbody>
    <?php while($it = $items ? mysqli_fetch_assoc($items) : null): if(!$it) break; ?>
      <tr>
        <td><?= htmlspecialchars($it['item_name']) ?></td>
        <td><?= htmlspecialchars($it['unit_name'] ?? '') ?></td>
        <td><?= htmlspecialchars($it['quantity']) ?></td>
        <td><?= number_format((float)$it['unit_price']) ?></td>
        <td><?= number_format((float)$it['amount']) ?></td>
      </tr>
    <?php endwhile; ?>
  </tbody>
</table>

<div class="box">
  <div><b>Tạm tính:</b> <?= number_format((float)$inv['subtotal']) ?></div>
  <div><b>Giảm giá:</b> <?= number_format((float)$inv['discount']) ?></div>
  <div><b>Tổng:</b> <?= number_format((float)$inv['total_amount']) ?></div>
  <div><b>Đã thu:</b> <?= number_format($paid) ?></div>
  <div><b>Còn lại:</b> <?= number_format($remain) ?></div>
</div>

</body>
</html>

