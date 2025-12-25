<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';

// Kiểm tra quyền: chỉ ADMIN/STAFF mới được truy cập
$role = $_SESSION['role_name'] ?? '';
if (!in_array($role, ['ADMIN', 'STAFF'], true)) {
    header('Location: /quanlyphongtro/admin/index.php?error=no_permission');
    exit;
}

$user_id = (int)($_SESSION['user_id'] ?? 0);

/* ===== Hàm sinh mã đặt phòng (booking_code) ===== */
function generateBookingCode($conn) {
    $date = date('Ymd');

    $rs = mysqli_query($conn, "
        SELECT booking_code
        FROM bookings
        WHERE booking_code LIKE 'BK{$date}-%'
        ORDER BY booking_code DESC
        LIMIT 1
    ");

    if ($row = mysqli_fetch_assoc($rs)) {
        $last = $row['booking_code'];
        $num = (int)substr($last, -3) + 1;
    } else {
        $num = 1;
    }

    return 'BK' . $date . '-' . str_pad($num, 3, '0', STR_PAD_LEFT);
}

$today = date('Y-m-d');

$check_in  = $_GET['check_in']  ?? $today;
$check_out = $_GET['check_out'] ?? date('Y-m-d', strtotime('+1 day'));
$type_id   = $_GET['type_id']   ?? '';

/* ===== Thêm khách nhanh ===== */
if (isset($_POST['add_tenant'])) {
    $full_name = mysqli_real_escape_string($conn, trim($_POST['full_name'] ?? ''));
    $phone     = mysqli_real_escape_string($conn, trim($_POST['phone'] ?? ''));

    if ($full_name !== '' && $phone !== '') {
        mysqli_query($conn, "
            INSERT INTO tenants (full_name, phone)
            VALUES ('$full_name', '$phone')
        ");
    }

    header("Location: add.php?check_in={$check_in}&check_out={$check_out}&type_id={$type_id}");
    exit;
}

/* ===== Đặt phòng (tạo booking) ===== */
if (isset($_POST['book_room'])) {

    $tenant_id = (int)($_POST['tenant_id'] ?? 0);
    $room_id   = (int)($_POST['room_id'] ?? 0);
    $check_in  = $_POST['check_in'] ?? $today;
    $check_out = $_POST['check_out'] ?? date('Y-m-d', strtotime('+1 day'));

    // Sinh booking_code để tránh lỗi UNIQUE booking_code
    $booking_code = generateBookingCode($conn);

    // TẠO BOOKING Ở TRẠNG THÁI CHỜ XÁC NHẬN
    mysqli_query($conn, "
        INSERT INTO bookings (booking_code, room_id, tenant_id, check_in, check_out, status, created_by)
        VALUES ('$booking_code', $room_id, $tenant_id, '$check_in', '$check_out', 'PENDING', $user_id)
    ");

    header('Location: index.php?msg=created');
    exit;
}

require_once __DIR__ . '/../../includes/header.php';

/* ===== Danh sách loại phòng ===== */
$types = mysqli_query($conn, "
    SELECT type_id, type_name
    FROM room_types
    ORDER BY type_name
");

/* ===== Danh sách khách ===== */
$tenants = mysqli_query($conn, "
    SELECT tenant_id, full_name
    FROM tenants
    ORDER BY full_name
");
?>

<div class="pagetitle">
    <h1>Thêm đặt phòng</h1>
</div>

<!-- FORM LỌC -->
<form method="get" class="row g-3 mb-4">

    <div class="col-md-3">
        <label class="form-label">Ngày nhận</label>
        <input type="date"
               name="check_in"
               class="form-control"
               min="<?= $today ?>"
               value="<?= $check_in ?>"
               required>
    </div>

    <div class="col-md-3">
        <label class="form-label">Ngày trả</label>
        <input type="date"
               name="check_out"
               class="form-control"
               min="<?= $check_in ?>"
               value="<?= $check_out ?>"
               required>
    </div>

    <div class="col-md-4">
        <label class="form-label">Loại phòng</label>
        <select name="type_id" class="form-select" required>
            <option value="">-- Chọn loại phòng --</option>
            <?php while ($t = mysqli_fetch_assoc($types)): ?>
                <option value="<?= $t['type_id'] ?>"
                    <?= ((string)$type_id === (string)$t['type_id']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($t['type_name']) ?>
                </option>
            <?php endwhile; ?>
        </select>
    </div>

    <div class="col-md-2 d-flex align-items-end">
        <button class="btn btn-primary w-100">
            <i class="bi bi-search"></i> Tìm phòng
        </button>
    </div>

</form>

<?php if ($type_id): ?>

<?php
/*
 * ===== TÌM PHÒNG TRỐNG =====
 * Yêu cầu của bạn:
 * - Nếu phòng có booking PENDING hoặc CONFIRMED trong khoảng ngày => KHÔNG hiện
 * - (tôi chặn luôn CHECKED_IN cho an toàn)
 * - Nếu booking CANCELLED => KHÔNG chặn (tức phòng hiện lại)
 * - Không lấy phòng bảo trì (MAINTENANCE)
 *
 * Điều kiện giao nhau ngày:
 *    booking.check_in < check_out
 *    booking.check_out > check_in
 */

$rooms = mysqli_query($conn, "
    SELECT r.room_id, r.room_code, r.image, r.base_rent, r.area_m2
    FROM rooms r
    WHERE r.type_id = " . (int)$type_id . "
      AND r.room_status = 'VACANT'
      AND r.room_id NOT IN (
          SELECT b.room_id
          FROM bookings b
          WHERE b.status IN ('PENDING','CONFIRMED','CHECKED_IN')
            AND b.check_in < '$check_out'
            AND b.check_out > '$check_in'
      )
    ORDER BY r.room_code
");
?>

<section class="section">
<div class="card">
<div class="card-body">

<h5 class="mb-3">Phòng trống trong khoảng đã chọn</h5>

<?php if (mysqli_num_rows($rooms) === 0): ?>
    <div class="alert alert-warning">
        Không có phòng trống (phòng đang chờ/đã đặt/đang ở trong khoảng ngày này sẽ không hiển thị).
    </div>
<?php else: ?>

<form method="post">

<input type="hidden" name="check_in" value="<?= $check_in ?>">
<input type="hidden" name="check_out" value="<?= $check_out ?>">

<div class="mb-3">
    <label class="form-label">Chọn phòng</label>
    <select name="room_id" class="form-select" required onchange="showRoomImage(this)">
        <option value="">-- Chọn phòng --</option>
        <?php while ($r = mysqli_fetch_assoc($rooms)): ?>
            <option value="<?= $r['room_id'] ?>"
                    data-image="<?= htmlspecialchars($r['image'] ?? '') ?>">
                Phòng <?= htmlspecialchars($r['room_code']) ?>
            </option>
        <?php endwhile; ?>
    </select>
</div>

<!-- ẢNH PHÒNG -->
<div class="mb-3">
    <img id="roomImage"
         src="/quanlyphongtro/admin/uploads/rooms/no-image.jpg"
         style="width:220px; display:none"
         class="rounded border">
</div>

<div class="mb-3">
    <label class="form-label">Khách thuê</label>
    <select name="tenant_id" class="form-select" required>
        <?php while ($t = mysqli_fetch_assoc($tenants)): ?>
            <option value="<?= $t['tenant_id'] ?>">
                <?= htmlspecialchars($t['full_name']) ?>
            </option>
        <?php endwhile; ?>
    </select>
</div>

<button class="btn btn-success" name="book_room">
    <i class="bi bi-check-circle"></i> Tạo đặt phòng (Chờ xác nhận)
</button>

<a href="index.php" class="btn btn-secondary">Quay lại</a>

</form>

<hr>

<!-- THÊM KHÁCH NHANH -->
<h5 class="mt-4">Thêm khách mới</h5>

<form method="post" class="row g-3">
    <div class="col-md-6">
        <input type="text" name="full_name" class="form-control" placeholder="Họ tên" required>
    </div>
    <div class="col-md-4">
        <input type="text" name="phone" class="form-control" placeholder="Số điện thoại" required>
    </div>
    <div class="col-md-2">
        <button class="btn btn-outline-primary w-100" name="add_tenant">
            <i class="bi bi-person-plus"></i> Thêm
        </button>
    </div>
</form>

<?php endif; ?>

</div>
</div>
</section>

<?php endif; ?>

<script>
function showRoomImage(select) {
    const img = document.getElementById('roomImage');
    const opt = select.options[select.selectedIndex];
    const image = opt ? opt.getAttribute('data-image') : '';

    if (image && image.trim() !== '') {
        img.src = '/quanlyphongtro/admin/uploads/rooms/' + image;
        img.style.display = 'block';
    } else {
        img.src = '/quanlyphongtro/admin/uploads/rooms/no-image.jpg';
        img.style.display = 'block';
    }
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
