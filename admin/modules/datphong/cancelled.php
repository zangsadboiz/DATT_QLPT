<?php
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/db.php';

/* ===== FILTER ===== */
$today = date('Y-m-d');
$from = $_GET['from'] ?? date('Y-m-d', strtotime('-30 day'));
$to   = $_GET['to']   ?? date('Y-m-d', strtotime('+1 day'));
$qraw = trim($_GET['q'] ?? '');
$q    = mysqli_real_escape_string($conn, $qraw);

/* ===== PHÂN TRANG ===== */
$limit  = 10;
$page   = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $limit;

/* ===== WHERE ===== */
$where = "
    b.status = 'CANCELLED'
    AND b.check_in < '$to'
    AND b.check_out > '$from'
";

if ($qraw !== '') {
    $where .= " AND (
        b.booking_code LIKE '%$q%' OR
        r.room_code LIKE '%$q%' OR
        t.full_name LIKE '%$q%' OR
        rt.type_name LIKE '%$q%'
    )";
}

/* ===== ĐẾM TỔNG ===== */
$totalRes = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT COUNT(*) AS total
    FROM bookings b
    JOIN rooms r ON b.room_id = r.room_id
    JOIN room_types rt ON r.type_id = rt.type_id
    JOIN tenants t ON b.tenant_id = t.tenant_id
    WHERE $where
"));
$total = (int)($totalRes['total'] ?? 0);
$totalPages = max(1, (int)ceil($total / $limit));

/* ===== QUERY CHÍNH (THÊM cancelled_at + expired) ===== */
$bookings = mysqli_query($conn, "
    SELECT 
        b.booking_id, b.booking_code, b.check_in, b.check_out, b.cancelled_at,
        (NOW() <= DATE_ADD(b.cancelled_at, INTERVAL 15 MINUTE)) AS can_restore,
        TIMESTAMPDIFF(MINUTE, NOW(), DATE_ADD(b.cancelled_at, INTERVAL 15 MINUTE)) AS remain_min,
        r.room_code, r.image AS room_image,
        rt.type_name, rt.image AS type_image,
        t.full_name
    FROM bookings b
    JOIN rooms r ON b.room_id = r.room_id
    JOIN room_types rt ON r.type_id = rt.type_id
    JOIN tenants t ON b.tenant_id = t.tenant_id
    WHERE $where
    ORDER BY b.booking_id DESC
    LIMIT $limit OFFSET $offset
");
?>

<div class="pagetitle d-flex justify-content-between align-items-center">
    <h1>Đặt phòng đã hủy</h1>
    <a href="index.php" class="btn btn-secondary">
        <i class="bi bi-arrow-left"></i> Quay lại
    </a>
</div>

<?php if (isset($_GET['error']) && $_GET['error']=='restore_expired'): ?>
<div class="alert alert-warning alert-dismissible fade show">
    Hết thời gian khôi phục (quá 15 phút kể từ lúc hủy).
    <button class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if (isset($_GET['error']) && $_GET['error']=='conflict'): ?>
<div class="alert alert-danger alert-dismissible fade show">
    Không thể khôi phục: Phòng đã có đặt phòng/đang chờ/đang ở trong khoảng thời gian này.
    <button class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<form method="get" class="row g-3 mb-3">
    <div class="col-md-3">
        <label class="form-label">Từ ngày</label>
        <input type="date" name="from" class="form-control" value="<?= $from ?>">
    </div>

    <div class="col-md-3">
        <label class="form-label">Đến ngày</label>
        <input type="date" name="to" class="form-control" value="<?= $to ?>">
    </div>

    <div class="col-md-4">
        <label class="form-label">Tìm kiếm</label>
        <input type="text" name="q" class="form-control"
               placeholder="Mã đặt / phòng / khách / loại phòng"
               value="<?= htmlspecialchars($qraw) ?>">
    </div>

    <div class="col-md-2 d-flex align-items-end">
        <button class="btn btn-primary w-100">
            <i class="bi bi-search"></i> Lọc
        </button>
    </div>
</form>

<section class="section">
<div class="card">
<div class="card-body">

<table class="table table-bordered table-hover align-middle">
<thead class="table-light">
<tr>
    <th>#</th>
    <th>Ảnh</th>
    <th>Mã đặt</th>
    <th>Phòng</th>
    <th>Loại phòng</th>
    <th>Khách</th>
    <th>Nhận</th>
    <th>Trả</th>
    <th>Hủy lúc</th>
    <th width="220">Hành động</th>
</tr>
</thead>
<tbody>

<?php
$idx = $offset;
$hasRows = false;
while ($b = mysqli_fetch_assoc($bookings)):
    $hasRows = true;
    $idx++;

    if (!empty($b['room_image'])) $img = $b['room_image'];
    elseif (!empty($b['type_image'])) $img = $b['type_image'];
    else $img = 'no-image.jpg';

    $canRestore = (int)$b['can_restore'] === 1;
    $remainMin  = (int)($b['remain_min'] ?? 0);
?>
<tr>
    <td><?= $idx ?></td>
    <td style="width:90px;">
        <img src="/quanlyphongtro/admin/uploads/rooms/<?= htmlspecialchars($img) ?>"
             style="width:70px;height:50px;object-fit:cover"
             class="rounded border">
    </td>
    <td><?= htmlspecialchars($b['booking_code'] ?? '') ?></td>
    <td><?= htmlspecialchars($b['room_code']) ?></td>
    <td><?= htmlspecialchars($b['type_name']) ?></td>
    <td><?= htmlspecialchars($b['full_name']) ?></td>
    <td><?= $b['check_in'] ?></td>
    <td><?= $b['check_out'] ?></td>
    <td><?= $b['cancelled_at'] ? $b['cancelled_at'] : '-' ?></td>
    <td>
        <a href="detail.php?id=<?= $b['booking_id'] ?>"
           class="btn btn-sm btn-outline-info">Chi tiết</a>

        <?php if ($canRestore): ?>
            <a href="confirm.php?id=<?= $b['booking_id'] ?>&return=cancelled"
               class="btn btn-sm btn-success"
               onclick="return confirm('Khôi phục đặt phòng này?')">
               Khôi phục (còn <?= max(0,$remainMin) ?>p)
            </a>
        <?php else: ?>
            <span class="badge bg-secondary">Hết hạn khôi phục</span>
        <?php endif; ?>
    </td>
</tr>
<?php endwhile; ?>

<?php if (!$hasRows): ?>
<tr>
    <td colspan="10" class="text-center text-muted">
        Không có đặt phòng đã hủy trong khoảng ngày đang lọc.
    </td>
</tr>
<?php endif; ?>

</tbody>
</table>

<nav>
<ul class="pagination justify-content-center">
<?php for ($p=1; $p<=$totalPages; $p++): ?>
    <li class="page-item <?= $p==$page?'active':'' ?>">
        <a class="page-link"
           href="?page=<?= $p ?>&from=<?= $from ?>&to=<?= $to ?>&q=<?= urlencode($qraw) ?>">
           <?= $p ?>
        </a>
    </li>
<?php endfor; ?>
</ul>
</nav>

</div>
</div>
</section>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
