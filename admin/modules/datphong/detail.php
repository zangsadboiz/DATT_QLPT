<?php
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/db.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: index.php?error=invalid');
    exit;
}

$id = (int)$_GET['id'];

/* LẤY CHI TIẾT BOOKING + ẢNH */
$res = mysqli_query($conn, "
    SELECT 
        b.booking_id, b.booking_code, b.check_in, b.check_out, b.status, b.cancelled_at, b.created_at,
        r.room_id, r.room_code, r.image AS room_image, r.room_status,
        rt.type_id, rt.type_name, rt.image AS type_image, rt.price_per_day,
        t.tenant_id, t.full_name, t.phone
    FROM bookings b
    JOIN rooms r ON b.room_id = r.room_id
    JOIN room_types rt ON r.type_id = rt.type_id
    JOIN tenants t ON b.tenant_id = t.tenant_id
    WHERE b.booking_id = $id
    LIMIT 1
");

if (!$res || mysqli_num_rows($res) === 0) {
    header('Location: index.php?error=not_found');
    exit;
}

$b = mysqli_fetch_assoc($res);

/* ẢNH */
if (!empty($b['room_image'])) $img = $b['room_image'];
elseif (!empty($b['type_image'])) $img = $b['type_image'];
else $img = 'no-image.jpg';

/* TRẠNG THÁI TIẾNG VIỆT + MÀU */
function bookingBadge($status) {
    switch ($status) {
        case 'PENDING': return '<span class="badge bg-warning">Đang chờ</span>';
        case 'CONFIRMED': return '<span class="badge bg-info">Đã đặt</span>';
        case 'CHECKED_IN': return '<span class="badge bg-danger">Đang ở</span>';
        case 'CHECKED_OUT': return '<span class="badge bg-secondary">Đã trả</span>';
        case 'CANCELLED': return '<span class="badge bg-dark">Đã hủy</span>';
        default: return '<span class="badge bg-light text-dark">Không xác định</span>';
    }
}

/* KHÔI PHỤC TRONG 15 PHÚT */
$canRestore = false;
$remainMin  = 0;
if ($b['status'] === 'CANCELLED' && !empty($b['cancelled_at'])) {
    $tmp = mysqli_fetch_assoc(mysqli_query($conn, "
        SELECT 
          (NOW() <= DATE_ADD('{$b['cancelled_at']}', INTERVAL 15 MINUTE)) AS can_restore,
          TIMESTAMPDIFF(MINUTE, NOW(), DATE_ADD('{$b['cancelled_at']}', INTERVAL 15 MINUTE)) AS remain_min
    "));
    $canRestore = !empty($tmp['can_restore']);
    $remainMin  = (int)($tmp['remain_min'] ?? 0);
}
?>

<div class="pagetitle d-flex justify-content-between align-items-center">
    <h1>Chi tiết đặt phòng</h1>
    <a href="index.php" class="btn btn-secondary">
        <i class="bi bi-arrow-left"></i> Quay lại
    </a>
</div>

<?php if (isset($_GET['msg']) && $_GET['msg']=='confirmed'): ?>
<div class="alert alert-success alert-dismissible fade show">
    Thao tác thành công.
    <button class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if (isset($_GET['error']) && $_GET['error']=='conflict'): ?>
<div class="alert alert-danger alert-dismissible fade show">
    Không thể xác nhận/khôi phục: Phòng đã có đặt/chờ/đang ở trong khoảng thời gian này.
    <button class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<section class="section">
<div class="card">
<div class="card-body">

<div class="row g-3">

    <div class="col-md-4">
        <img src="/quanlyphongtro/admin/uploads/rooms/<?= htmlspecialchars($img) ?>"
             class="rounded border"
             style="width:100%;height:220px;object-fit:cover">
    </div>

    <div class="col-md-8">
        <h4 class="mb-2">
            Mã đặt: <?= htmlspecialchars($b['booking_code'] ?? '') ?>
            <?= bookingBadge($b['status']) ?>
        </h4>

        <div class="mb-2">
            <strong>Phòng:</strong> <?= htmlspecialchars($b['room_code']) ?>
            <span class="text-muted"> (<?= htmlspecialchars($b['type_name']) ?>)</span>
        </div>

        <div class="mb-2">
            <strong>Khách:</strong> <?= htmlspecialchars($b['full_name']) ?>
            <?php if (!empty($b['phone'])): ?>
                <span class="text-muted"> - <?= htmlspecialchars($b['phone']) ?></span>
            <?php endif; ?>
        </div>

        <div class="mb-2">
            <strong>Ngày nhận:</strong> <?= $b['check_in'] ?>
            &nbsp;&nbsp;|&nbsp;&nbsp;
            <strong>Ngày trả:</strong> <?= $b['check_out'] ?>
        </div>

        <div class="mb-2 text-muted">
            <small>Tạo lúc: <?= $b['created_at'] ?? '-' ?></small>
            <?php if ($b['status']==='CANCELLED'): ?>
                <br><small>Hủy lúc: <?= $b['cancelled_at'] ?? '-' ?></small>
            <?php endif; ?>
        </div>

        <!-- ACTIONS -->
        <div class="mt-3 d-flex flex-wrap gap-2">

            <!-- NÚT SỬA (CHỈ PENDING/CONFIRMED) -->
            <?php if (in_array($b['status'], ['PENDING','CONFIRMED'], true)): ?>
                <a href="edit.php?id=<?= $b['booking_id'] ?>" class="btn btn-outline-warning">
                    <i class="bi bi-pencil"></i> Sửa
                </a>
            <?php endif; ?>

            <?php if ($b['status']==='PENDING'): ?>
                <a href="confirm.php?id=<?= $b['booking_id'] ?>" class="btn btn-success">
                    <i class="bi bi-check-circle"></i> Xác nhận
                </a>
                <a href="cancel.php?id=<?= $b['booking_id'] ?>"
                   class="btn btn-danger"
                   onclick="return confirm('Hủy đặt phòng này?')">
                    <i class="bi bi-x-circle"></i> Hủy
                </a>
            <?php endif; ?>

            <?php if ($b['status']==='CONFIRMED'): ?>
                <a href="checkin.php?id=<?= $b['booking_id'] ?>" class="btn btn-primary">
                    <i class="bi bi-box-arrow-in-right"></i> Nhận phòng
                </a>
                <a href="cancel.php?id=<?= $b['booking_id'] ?>"
                   class="btn btn-danger"
                   onclick="return confirm('Hủy đặt phòng này?')">
                    <i class="bi bi-x-circle"></i> Hủy
                </a>
            <?php endif; ?>

            <?php if ($b['status']==='CHECKED_IN'): ?>
                <a href="checkout.php?id=<?= $b['booking_id'] ?>" class="btn btn-warning">
                    <i class="bi bi-box-arrow-right"></i> Trả phòng
                </a>
            <?php endif; ?>

            <?php if ($b['status']==='CANCELLED'): ?>
                <?php if ($canRestore): ?>
                    <a href="confirm.php?id=<?= $b['booking_id'] ?>&return=cancelled"
                       class="btn btn-success"
                       onclick="return confirm('Khôi phục đặt phòng này?')">
                        <i class="bi bi-arrow-counterclockwise"></i> Khôi phục (còn <?= max(0,$remainMin) ?>p)
                    </a>
                <?php else: ?>
                    <span class="badge bg-secondary p-2">Hết hạn khôi phục</span>
                <?php endif; ?>
            <?php endif; ?>

        </div>
    </div>

</div>

<hr>

<!-- THÔNG TIN PHÒNG -->
<h5 class="mb-3">Thông tin phòng</h5>

<div class="row">
    <div class="col-md-6">
        <p class="mb-1"><strong>Loại phòng:</strong> <?= htmlspecialchars($b['type_name']) ?></p>
        <p class="mb-1"><strong>Giá/ngày:</strong> <?= number_format((float)$b['price_per_day']) ?> VNĐ</p>
    </div>
    <div class="col-md-6">
        <p class="mb-1"><strong>Trạng thái quản lý phòng:</strong>
            <?php if ($b['room_status']==='MAINTENANCE'): ?>
                <span class="badge bg-warning">Bảo trì</span>
            <?php else: ?>
                <span class="badge bg-success">Sẵn sàng</span>
            <?php endif; ?>
        </p>
    </div>
</div>

</div>
</div>
</section>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
