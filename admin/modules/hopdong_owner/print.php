<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';

$role = $_SESSION['role_name'] ?? '';
$user_id = (int)($_SESSION['user_id'] ?? 0);

if ($role !== 'LANDLORD' || $user_id <= 0) {
    exit('No permission');
}

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) exit('Invalid');

$rs = mysqli_query($conn, "
  SELECT
    c.*,
    r.room_code,
    b.building_name, b.address,
    u.full_name AS owner_full_name
  FROM contracts c
  JOIN rooms r ON r.room_id = c.room_id
  JOIN buildings b ON b.building_id = r.building_id
  LEFT JOIN users u ON u.user_id = b.owner_id
  WHERE c.contract_id = $id AND b.owner_id = $user_id
  LIMIT 1
");
$contract = $rs ? mysqli_fetch_assoc($rs) : null;
if (!$contract) exit('Not found');

$tenants = mysqli_query($conn, "
    SELECT t.*, ct.is_representative
    FROM contract_tenants ct
    JOIN tenants t ON t.tenant_id = ct.tenant_id
    WHERE ct.contract_id = $id
    ORDER BY ct.is_representative DESC, t.tenant_id ASC
");

?>
<!doctype html>
<html lang="vi">
<head>
  <meta charset="utf-8">
  <title>In hợp đồng</title>
  <style>
    body{font-family:Arial, sans-serif; margin:32px; color:#111; font-size:16px; line-height:1.6;}
    .row{display:flex; justify-content:space-between; gap:20px;}
    .box{border:2px solid #333; padding:16px; border-radius:8px;}
    h1{font-size:28px; margin:0 0 16px; text-align:center; text-transform:uppercase;}
    h2{font-size:20px; margin:0 0 12px; border-bottom:1px solid #333; padding-bottom:8px;}
    table{width:100%; border-collapse:collapse; margin-top:12px;}
    th,td{border:1px solid #333; padding:12px; font-size:15px;}
    th{background:#f5f5f5; text-align:left; font-weight:bold;}
    .muted{color:#555; font-size:14px;}
    .printbar{margin-bottom:16px;}
    @media print {.printbar{display:none;} body{margin:15mm;}}
  </style>
</head>
<body>

<div class="printbar">
  <button onclick="window.print()">In</button>
</div>

<h1>HỢP ĐỒNG THUÊ PHÒNG</h1>
<div class="muted">Mã hợp đồng: <b><?= htmlspecialchars($contract['contract_code']) ?></b></div>

<br>

<div class="row">
  <div class="box" style="flex:1">
    <h2>Bên cho thuê (Chủ trọ)</h2>
    <div>Họ tên: <b><?= htmlspecialchars($contract['landlord_name'] ?? ($contract['owner_full_name'] ?? '')) ?></b></div>
    <div>SĐT: <?= htmlspecialchars($contract['landlord_phone'] ?? '-') ?></div>
    <div>Địa chỉ: <?= htmlspecialchars($contract['landlord_address'] ?? ($contract['address'] ?? '-')) ?></div>
  </div>

  <div class="box" style="flex:1">
    <h2>Thông tin phòng</h2>
    <div>Dãy/Tòa: <b><?= htmlspecialchars($contract['building_name'] ?? '-') ?></b></div>
    <div>Phòng: <b><?= htmlspecialchars($contract['room_code'] ?? '-') ?></b></div>
    <div>Ngày bắt đầu: <b><?= htmlspecialchars($contract['start_date'] ?? '-') ?></b></div>
    <div>Giá thuê/tháng: <b><?= number_format((float)$contract['rent_amount']) ?></b></div>
    <div>Tiền cọc: <b><?= number_format((float)$contract['deposit_amount']) ?></b></div>
  </div>
</div>

<br>

<div class="box">
  <h2>Bên thuê (Người thuê)</h2>
  <table>
    <thead>
      <tr>
        <th>Họ tên</th>
        <th>SĐT</th>
        <th>Email</th>
        <th>Đại diện</th>
        <th>CCCD/CMND</th>
        <th>MSSV</th>
      </tr>
    </thead>
    <tbody>
      <?php $has=false; while($t = $tenants ? mysqli_fetch_assoc($tenants) : null): $has=true; ?>
        <tr>
          <td><?= htmlspecialchars($t['full_name'] ?? '-') ?></td>
          <td><?= htmlspecialchars($t['phone'] ?? '-') ?></td>
          <td><?= htmlspecialchars($t['email'] ?? '-') ?></td>
          <td><?= ((int)($t['is_representative'] ?? 0) === 1) ? 'Có' : '' ?></td>
          <td><?= htmlspecialchars($t['id_card'] ?? '-') ?></td>
          <td>-</td>
        </tr>
      <?php endwhile; ?>
      <?php if(!$has): ?>
        <tr><td colspan="6" class="muted">Chưa có người thuê.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<br>

<div class="box">
  <h2>Điều khoản</h2>
  <div style="white-space:pre-wrap; font-size:13px;">
<?= htmlspecialchars($contract['terms_text'] ?? "1) Bên thuê thanh toán đúng hạn.\n2) Giữ gìn tài sản, không gây mất trật tự.\n3) Các thỏa thuận khác theo hai bên thống nhất.") ?>
  </div>
</div>

<br><br>

<div class="row">
  <div style="flex:1; text-align:center">
    <b>BÊN CHO THUÊ</b><br><br><br><br>
    (Ký, ghi rõ họ tên)
  </div>
  <div style="flex:1; text-align:center">
    <b>BÊN THUÊ</b><br><br><br><br>
    (Ký, ghi rõ họ tên)
  </div>
</div>

</body>
</html>
