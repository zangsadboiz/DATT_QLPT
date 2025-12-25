<?php
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/db.php';

$role = $_SESSION['role_name'] ?? '';
if ($role !== 'ADMIN') {
    header('Location: /quanlyphongtro/admin/index.php?error=no_permission');
    exit;
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: index.php?error=invalid');
    exit;
}
$id = (int)$_GET['id'];

$res = mysqli_query($conn, "
    SELECT
        c.*,
        r.room_code, r.room_status,
        b.building_name, b.address
    FROM contracts c
    JOIN rooms r ON c.room_id = r.room_id
    JOIN buildings b ON r.building_id = b.building_id
    WHERE c.contract_id = $id
    LIMIT 1
");
if (!$res || mysqli_num_rows($res)===0) {
    header('Location: index.php?error=not_found');
    exit;
}
$c = mysqli_fetch_assoc($res);

$tenants = mysqli_query($conn, "
    SELECT t.full_name, t.phone, ct.is_representative, ct.move_in_date, ct.move_out_date
    FROM contract_tenants ct
    JOIN tenants t ON t.tenant_id = ct.tenant_id
    WHERE ct.contract_id = $id
    ORDER BY ct.is_representative DESC, t.full_name
");

$badge = match($c['contract_status']) {
    'ACTIVE' => '<span class="badge bg-success">Đang hiệu lực</span>',
    'ENDED' => '<span class="badge bg-secondary">Đã kết thúc</span>',
    'CANCELLED' => '<span class="badge bg-dark">Đã hủy</span>',
    default => '<span class="badge bg-light text-dark">?</span>',
};

/* Tính số tháng + tổng tiền */
$months = 0;
$total = 0;
if (!empty($c['start_date']) && !empty($c['end_date'])) {
    $d1 = new DateTime($c['start_date']);
    $d2 = new DateTime($c['end_date']);
    $diff = $d1->diff($d2);
    $months = ($diff->y * 12) + $diff->m;
    if ($months <= 0) $months = 1; // fallback
    $total = ((float)$c['rent_amount']) * $months;
}
?>

<div class="pagetitle d-flex justify-content-between align-items-center">
    <h1>Chi tiết hợp đồng</h1>
    <a href="index.php" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Quay lại</a>
</div>

<section class="section">
<div class="card">
<div class="card-body">

    <h4 class="mb-3">
        <?= htmlspecialchars($c['contract_code']) ?> <?= $badge ?>
    </h4>

    <div class="row">
        <div class="col-md-6">
            <p><strong>Tòa/Dãy:</strong> <?= htmlspecialchars($c['building_name']) ?></p>
            <p><strong>Phòng:</strong> <?= htmlspecialchars($c['room_code']) ?></p>
            <p><strong>Địa chỉ:</strong> <?= htmlspecialchars($c['address'] ?? '-') ?></p>
        </div>

        <div class="col-md-6">
            <p><strong>Bắt đầu:</strong> <?= htmlspecialchars($c['start_date'] ?? '-') ?></p>
            <p><strong>Kết thúc:</strong> <?= htmlspecialchars($c['end_date'] ?? '-') ?></p>
            <p><strong>Thu tiền ngày:</strong> <?= (int)($c['billing_day'] ?? 0) ?></p>
            <p><strong>Giá thuê:</strong> <?= number_format((float)($c['rent_amount'] ?? 0)) ?> đ/tháng</p>
            <p><strong>Thời hạn:</strong> <?= $months ? $months.' tháng' : '-' ?></p>
            <p><strong>Tổng tiền:</strong> <?= $total ? number_format($total) : '0' ?> đ</p>
        </div>
    </div>

    <?php if (!empty($c['note'])): ?>
        <hr>
        <h5>Ghi chú</h5>
        <div class="alert alert-light mb-0"><?= nl2br(htmlspecialchars($c['note'])) ?></div>
    <?php endif; ?>

    <hr>

    <h5>Danh sách người ở</h5>
    <table class="table table-bordered align-middle">
        <thead class="table-light">
            <tr>
                <th>Họ tên</th>
                <th>SĐT</th>
                <th>Đại diện</th>
                <th>Vào ở</th>
                <th>Rời đi</th>
            </tr>
        </thead>
        <tbody>
        <?php $has=false; while($t=mysqli_fetch_assoc($tenants)): $has=true; ?>
            <tr>
                <td><?= htmlspecialchars($t['full_name']) ?></td>
                <td><?= htmlspecialchars($t['phone'] ?? '-') ?></td>
                <td><?= $t['is_representative'] ? '<span class="badge bg-primary">Đại diện</span>' : '' ?></td>
                <td><?= htmlspecialchars($t['move_in_date'] ?? '-') ?></td>
                <td><?= htmlspecialchars($t['move_out_date'] ?? '-') ?></td>
            </tr>
        <?php endwhile; ?>
        <?php if(!$has): ?>
            <tr><td colspan="5" class="text-center text-muted">Chưa có dữ liệu</td></tr>
        <?php endif; ?>
        </tbody>
    </table>

    <a class="btn btn-primary" target="_blank"
        href="print.php?id=<?= (int)$c['contract_id'] ?>">
        <i class="bi bi-printer"></i> In hợp đồng
    </a>

</div>
</div>
</section>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
