<?php
require_once __DIR__ . '/../../includes/db.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die('Thiếu id hợp đồng');
}
$id = (int)$_GET['id'];

/* LẤY HỢP ĐỒNG + PHÒNG + TÒA + KHÁCH ĐẠI DIỆN */
$res = mysqli_query($conn, "
    SELECT
        c.contract_id, c.contract_code, c.start_date, c.end_date, c.rent_amount, c.billing_day, c.note,
        c.landlord_name, c.landlord_phone, c.landlord_address, c.terms_text,
        r.room_code,
        b.building_name, b.address AS building_address,
        t.full_name, t.phone
    FROM contracts c
    JOIN rooms r ON c.room_id = r.room_id
    JOIN buildings b ON r.building_id = b.building_id
    LEFT JOIN contract_tenants ct ON ct.contract_id = c.contract_id AND ct.is_representative = 1
    LEFT JOIN tenants t ON t.tenant_id = ct.tenant_id
    WHERE c.contract_id = $id
    LIMIT 1
");
if (!$res || mysqli_num_rows($res) === 0) {
    die('Không tìm thấy hợp đồng');
}
$c = mysqli_fetch_assoc($res);

/* ĐIỀU KHOẢN MẶC ĐỊNH (nếu chưa lưu terms_text) */
$defaultTerms = <<<TXT
1. Thời hạn thuê: Bên B thuê phòng theo thời hạn ghi trong hợp đồng. Khi hết hạn, hai bên có thể gia hạn bằng thỏa thuận.
2. Tiền thuê: Bên B thanh toán tiền thuê theo tháng. Ngày thu tiền: ngày {$c['billing_day']} hàng tháng.
3. Tiền điện, nước, internet và các dịch vụ: tính theo thực tế/thoả thuận của khu trọ và được thông báo rõ cho Bên B.
4. Đặt cọc: Bên B tự thỏa thuận và thanh toán trực tiếp với Bên A (nếu có). Việc hoàn cọc theo quy định nội bộ và biên nhận.
5. Quy định sử dụng phòng: Bên B giữ gìn tài sản, không tự ý sửa chữa/kết cấu khi chưa có sự đồng ý của Bên A.
6. An ninh trật tự: Bên B chấp hành nội quy khu trọ; không gây mất trật tự, không chứa chấp hành vi vi phạm pháp luật.
7. Chấm dứt hợp đồng: Hai bên thông báo trước và thanh toán đầy đủ công nợ, bàn giao phòng, tài sản.
8. Hiệu lực: Hợp đồng có hiệu lực kể từ ngày ký. Hai bên đã đọc, hiểu và tự nguyện ký.
TXT;

$termsText = trim($c['terms_text'] ?? '');
if ($termsText === '') $termsText = $defaultTerms;

/* TÍNH TỔNG TIỀN (từ start_date -> end_date) */
$months = 0;
$total = 0;
if (!empty($c['start_date']) && !empty($c['end_date'])) {
    $d1 = new DateTime($c['start_date']);
    $d2 = new DateTime($c['end_date']);
    $diff = $d1->diff($d2);
    $months = ($diff->y * 12) + $diff->m;
    if ($months <= 0) $months = 1;
    $total = ((float)$c['rent_amount']) * $months;
}

/* THÔNG TIN CHỦ TRỌ (nếu DB chưa nhập) */
$landlordName = $c['landlord_name'] ?: 'CHỦ TRỌ';
$landlordPhone = $c['landlord_phone'] ?: '';
$landlordAddress = $c['landlord_address'] ?: ($c['building_address'] ?? '');

date_default_timezone_set('Asia/Ho_Chi_Minh');
$today = date('d/m/Y');
?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <title>In hợp đồng <?= htmlspecialchars($c['contract_code']) ?></title>
  <style>
    body { font-family: DejaVu Sans, Arial, sans-serif; color:#111; }
    .wrap { width: 800px; margin: 0 auto; }
    .center { text-align:center; }
    .right { text-align:right; }
    .small { font-size: 12px; }
    h1 { font-size: 18px; margin: 8px 0; text-transform: uppercase; }
    h2 { font-size: 14px; margin: 10px 0 6px; text-transform: uppercase; }
    .line { border-top: 1px solid #000; margin: 10px 0; }
    .row { display:flex; gap:20px; }
    .col { flex:1; }
    table { width:100%; border-collapse: collapse; }
    td { padding: 4px 0; vertical-align: top; }
    .box { border:1px solid #000; padding:10px; border-radius: 6px; }
    .sign { margin-top: 24px; }
    .sign .col { text-align:center; }
    .sign .name { margin-top: 70px; font-weight: bold; text-transform: uppercase; }
    .btns { display:flex; gap:10px; justify-content:flex-end; margin: 10px 0; }
    .btn { padding: 8px 12px; border:1px solid #333; background:#f7f7f7; cursor:pointer; border-radius: 6px; }
    .muted { color:#555; }
    pre { white-space: pre-wrap; font-family: inherit; margin:0; }

    @media print {
      .btns { display:none; }
      .wrap { width: auto; margin: 0; }
      body { margin: 0; }
    }
  </style>
</head>
<body>
<div class="wrap">

  <div class="btns">
    <button class="btn" onclick="window.print()">In hợp đồng</button>
    <button class="btn" onclick="window.close()">Đóng</button>
  </div>

  <div class="center small">
    <div><strong>CỘNG HÒA XÃ HỘI CHỦ NGHĨA VIỆT NAM</strong></div>
    <div><strong>Độc lập - Tự do - Hạnh phúc</strong></div>
  </div>

  <div class="line"></div>

  <div class="center">
    <h1>HỢP ĐỒNG THUÊ PHÒNG TRỌ</h1>
    <div class="small muted">Mã hợp đồng: <strong><?= htmlspecialchars($c['contract_code']) ?></strong></div>
  </div>

  <div class="right small">Ngày <?= $today ?></div>

  <h2>I. THÔNG TIN CÁC BÊN</h2>
  <div class="box">
    <table>
      <tr>
        <td style="width:120px;"><strong>Bên A</strong></td>
        <td> (Chủ trọ): <strong><?= htmlspecialchars($landlordName) ?></strong></td>
      </tr>
      <tr>
        <td></td>
        <td>Điện thoại: <?= htmlspecialchars($landlordPhone ?: '-') ?></td>
      </tr>
      <tr>
        <td></td>
        <td>Địa chỉ: <?= htmlspecialchars($landlordAddress ?: '-') ?></td>
      </tr>

      <tr><td colspan="2" style="padding-top:10px;"></td></tr>

      <tr>
        <td><strong>Bên B</strong></td>
        <td>(Sinh viên/Người thuê): <strong><?= htmlspecialchars($c['full_name'] ?? '-') ?></strong></td>
      </tr>
      <tr>
        <td></td>
        <td>Điện thoại: <?= htmlspecialchars($c['phone'] ?? '-') ?></td>
      </tr>
    </table>
  </div>

  <h2>II. THÔNG TIN PHÒNG THUÊ</h2>
  <div class="box">
    <table>
      <tr>
        <td style="width:140px;"><strong>Khu/Tòa/Dãy</strong></td>
        <td><?= htmlspecialchars($c['building_name'] ?? '-') ?></td>
      </tr>
      <tr>
        <td><strong>Phòng</strong></td>
        <td><?= htmlspecialchars($c['room_code'] ?? '-') ?></td>
      </tr>
      <tr>
        <td><strong>Địa chỉ</strong></td>
        <td><?= htmlspecialchars($c['building_address'] ?? '-') ?></td>
      </tr>
    </table>
  </div>

  <h2>III. THỜI HẠN - GIÁ THUÊ</h2>
  <div class="box">
    <table>
      <tr>
        <td style="width:140px;"><strong>Ngày bắt đầu</strong></td>
        <td><?= htmlspecialchars($c['start_date'] ?? '-') ?></td>
      </tr>
      <tr>
        <td><strong>Ngày kết thúc</strong></td>
        <td><?= htmlspecialchars($c['end_date'] ?? '-') ?></td>
      </tr>
      <tr>
        <td><strong>Giá thuê/tháng</strong></td>
        <td><strong><?= number_format((float)$c['rent_amount']) ?> đ</strong></td>
      </tr>
      <tr>
        <td><strong>Số tháng</strong></td>
        <td><?= (int)$months ?> tháng</td>
      </tr>
      <tr>
        <td><strong>Tổng tiền</strong></td>
        <td><strong><?= number_format((float)$total) ?> đ</strong> <span class="small muted">(không bao gồm cọc, điện nước)</span></td>
      </tr>
      <tr>
        <td><strong>Ngày thu tiền</strong></td>
        <td>Ngày <?= (int)$c['billing_day'] ?> hàng tháng</td>
      </tr>
    </table>
  </div>

  <h2>IV. ĐIỀU KHOẢN</h2>
  <div class="box">
    <pre><?= htmlspecialchars($termsText) ?></pre>
  </div>

  <?php if (!empty($c['note'])): ?>
    <h2>V. GHI CHÚ</h2>
    <div class="box">
      <pre><?= htmlspecialchars($c['note']) ?></pre>
    </div>
  <?php endif; ?>

  <div class="sign">
    <div class="row">
      <div class="col">
        <div><strong>ĐẠI DIỆN BÊN A</strong></div>
        <div class="small muted">(Ký và ghi rõ họ tên)</div>
        <div class="name"><?= htmlspecialchars($landlordName) ?></div>
      </div>
      <div class="col">
        <div><strong>ĐẠI DIỆN BÊN B</strong></div>
        <div class="small muted">(Ký và ghi rõ họ tên)</div>
        <div class="name"><?= htmlspecialchars($c['full_name'] ?? 'NGƯỜI THUÊ') ?></div>
      </div>
    </div>
  </div>

</div>
</body>
</html>
