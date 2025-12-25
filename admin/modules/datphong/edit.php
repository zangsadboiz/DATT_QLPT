<?php
require_once __DIR__ . '/../../includes/db.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: index.php?error=invalid');
    exit;
}

$id = (int)$_GET['id'];
$today = date('Y-m-d');

$error = '';

/* ===== LẤY BOOKING HIỆN TẠI ===== */
$bkRes = mysqli_query($conn, "
    SELECT 
        b.booking_id, b.booking_code, b.room_id, b.tenant_id, b.check_in, b.check_out, b.status,
        r.room_code, r.image AS room_image, r.type_id,
        rt.type_name, rt.image AS type_image
    FROM bookings b
    JOIN rooms r ON b.room_id = r.room_id
    JOIN room_types rt ON r.type_id = rt.type_id
    WHERE b.booking_id = $id
    LIMIT 1
");

if (!$bkRes || mysqli_num_rows($bkRes) === 0) {
    header('Location: index.php?error=not_found');
    exit;
}

$bk = mysqli_fetch_assoc($bkRes);

/* ===== CHỈ CHO SỬA PENDING/CONFIRMED ===== */
if (!in_array($bk['status'], ['PENDING','CONFIRMED'], true)) {
    header('Location: index.php?error=cannot_edit');
    exit;
}

/* ===== GIÁ TRỊ FORM (cho phép đổi theo GET để lọc phòng trống) ===== */
$check_in  = $_GET['check_in']  ?? $bk['check_in'];
$check_out = $_GET['check_out'] ?? $bk['check_out'];
$type_id   = $_GET['type_id']   ?? $bk['type_id'];
$tenant_id = $_GET['tenant_id'] ?? $bk['tenant_id']; // chỉ dùng để giữ UI, POST mới là cập nhật

/* ===== DANH SÁCH LOẠI PHÒNG / KHÁCH ===== */
$types = mysqli_query($conn, "SELECT type_id, type_name FROM room_types ORDER BY type_name");
$tenants = mysqli_query($conn, "SELECT tenant_id, full_name FROM tenants ORDER BY full_name");

/*
 * ===== DANH SÁCH PHÒNG TRỐNG THEO NGÀY + LOẠI =====
 * - chỉ lấy phòng VACANT (không lấy MAINTENANCE)
 * - chặn các booking khác có status: PENDING/CONFIRMED/CHECKED_IN
 * - loại trừ booking hiện tại (booking_id <> $id)
 * - luôn cho phép chọn phòng hiện tại của booking (để không bị mất option)
 */
$type_id_int = (int)$type_id;

$rooms = mysqli_query($conn, "
    SELECT r.room_id, r.room_code, r.image
    FROM rooms r
    WHERE r.type_id = $type_id_int
      AND r.room_status = 'VACANT'
      AND r.room_id NOT IN (
          SELECT b2.room_id
          FROM bookings b2
          WHERE b2.booking_id <> $id
            AND b2.status IN ('PENDING','CONFIRMED','CHECKED_IN')
            AND b2.check_in < '$check_out'
            AND b2.check_out > '$check_in'
      )

    UNION

    SELECT r.room_id, r.room_code, r.image
    FROM rooms r
    WHERE r.room_id = {$bk['room_id']}

    ORDER BY room_code
");

/* ===== XỬ LÝ CẬP NHẬT ===== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $new_tenant_id = (int)($_POST['tenant_id'] ?? 0);
    $new_room_id   = (int)($_POST['room_id'] ?? 0);
    $new_check_in  = $_POST['check_in'] ?? '';
    $new_check_out = $_POST['check_out'] ?? '';
    $new_type_id   = (int)($_POST['type_id'] ?? 0);

    // validate ngày
    if (!$new_check_in || !$new_check_out || $new_check_out <= $new_check_in) {
        $error = 'Ngày trả phải lớn hơn ngày nhận.';
    } else {
        try {
            mysqli_begin_transaction($conn);

            // khóa booking
            $lockBk = mysqli_query($conn, "
                SELECT booking_id, status
                FROM bookings
                WHERE booking_id = $id
                FOR UPDATE
            ");
            $locked = mysqli_fetch_assoc($lockBk);
            if (!$locked || !in_array($locked['status'], ['PENDING','CONFIRMED'], true)) {
                mysqli_rollback($conn);
                header('Location: index.php?error=cannot_edit');
                exit;
            }

            // khóa phòng mới
            $roomRes = mysqli_query($conn, "
                SELECT r.room_id, r.room_status, r.type_id
                FROM rooms r
                WHERE r.room_id = $new_room_id
                FOR UPDATE
            ");
            if (!$roomRes || mysqli_num_rows($roomRes) === 0) {
                mysqli_rollback($conn);
                $error = 'Phòng không tồn tại.';
            } else {
                $room = mysqli_fetch_assoc($roomRes);

                // không cho chọn phòng bảo trì
                if ($room['room_status'] === 'MAINTENANCE') {
                    mysqli_rollback($conn);
                    $error = 'Không thể chọn phòng đang bảo trì.';
                } elseif ((int)$room['type_id'] !== $new_type_id) {
                    mysqli_rollback($conn);
                    $error = 'Phòng không thuộc loại phòng đã chọn.';
                } else {
                    // kiểm tra trùng lịch với booking khác (PENDING/CONFIRMED/CHECKED_IN)
                    $conflict = mysqli_query($conn, "
                        SELECT 1
                        FROM bookings b2
                        WHERE b2.booking_id <> $id
                          AND b2.room_id = $new_room_id
                          AND b2.status IN ('PENDING','CONFIRMED','CHECKED_IN')
                          AND b2.check_in < '$new_check_out'
                          AND b2.check_out > '$new_check_in'
                        LIMIT 1
                    ");

                    if ($conflict && mysqli_num_rows($conflict) > 0) {
                        mysqli_rollback($conn);
                        $error = 'Phòng đã có đặt/chờ/đang ở trong khoảng thời gian này.';
                    } else {
                        // cập nhật booking
                        mysqli_query($conn, "
                            UPDATE bookings
                            SET tenant_id = $new_tenant_id,
                                room_id   = $new_room_id,
                                check_in  = '$new_check_in',
                                check_out = '$new_check_out'
                            WHERE booking_id = $id
                        ");

                        mysqli_commit($conn);
                        header("Location: index.php?msg=updated");
                        exit;
                    }
                }
            }

        } catch (Throwable $e) {
            mysqli_rollback($conn);
            $error = 'Có lỗi hệ thống khi cập nhật. Vui lòng thử lại.';
        }
    }
}

require_once __DIR__ . '/../../includes/header.php';

/* ảnh mặc định cho UI */
$defaultImg = 'no-image.jpg';
$initImg = $bk['room_image'] ?: ($bk['type_image'] ?: $defaultImg);
?>

<div class="pagetitle d-flex justify-content-between align-items-center">
    <h1>Sửa đặt phòng</h1>
    <a href="index.php" class="btn btn-secondary">
        <i class="bi bi-arrow-left"></i> Quay lại
    </a>
</div>

<?php if ($error): ?>
<div class="alert alert-danger alert-dismissible fade show">
    <?= htmlspecialchars($error) ?>
    <button class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<section class="section">
<div class="card">
<div class="card-body">

<div class="mb-3">
    <strong>Mã đặt:</strong> <?= htmlspecialchars($bk['booking_code'] ?? '') ?> |
    <strong>Trạng thái:</strong>
    <?php
    if ($bk['status']=='PENDING') echo '<span class="badge bg-warning">Đang chờ</span>';
    if ($bk['status']=='CONFIRMED') echo '<span class="badge bg-info">Đã đặt</span>';
    ?>
</div>

<!-- FORM LỌC PHÒNG TRỐNG (GET) -->
<form method="get" class="row g-3 mb-4">
    <input type="hidden" name="id" value="<?= $id ?>">

    <div class="col-md-3">
        <label class="form-label">Ngày nhận</label>
        <input type="date" name="check_in" class="form-control" min="<?= $today ?>" value="<?= $check_in ?>" required>
    </div>

    <div class="col-md-3">
        <label class="form-label">Ngày trả</label>
        <input type="date" name="check_out" class="form-control" min="<?= $check_in ?>" value="<?= $check_out ?>" required>
    </div>

    <div class="col-md-4">
        <label class="form-label">Loại phòng</label>
        <select name="type_id" class="form-select" required>
            <?php while ($t = mysqli_fetch_assoc($types)): ?>
                <option value="<?= $t['type_id'] ?>" <?= ((string)$type_id === (string)$t['type_id'])?'selected':'' ?>>
                    <?= htmlspecialchars($t['type_name']) ?>
                </option>
            <?php endwhile; ?>
        </select>
    </div>

    <div class="col-md-2 d-flex align-items-end">
        <button class="btn btn-primary w-100">
            <i class="bi bi-search"></i> Lọc phòng
        </button>
    </div>
</form>

<!-- FORM CẬP NHẬT (POST) -->
<form method="post" class="row g-3">

    <div class="col-md-6">
        <label class="form-label">Chọn phòng (trống theo khoảng ngày)</label>
        <select name="room_id" class="form-select" required onchange="showRoomImage(this)">
            <?php while ($r = mysqli_fetch_assoc($rooms)): ?>
                <option value="<?= $r['room_id'] ?>"
                        data-image="<?= htmlspecialchars($r['image'] ?? '') ?>"
                        <?= ((int)$bk['room_id'] === (int)$r['room_id']) ? 'selected' : '' ?>>
                    Phòng <?= htmlspecialchars($r['room_code']) ?>
                </option>
            <?php endwhile; ?>
        </select>
    </div>

    <div class="col-md-6">
        <label class="form-label">Khách thuê</label>
        <select name="tenant_id" class="form-select" required>
            <?php while ($tn = mysqli_fetch_assoc($tenants)): ?>
                <option value="<?= $tn['tenant_id'] ?>" <?= ((int)$bk['tenant_id'] === (int)$tn['tenant_id'])?'selected':'' ?>>
                    <?= htmlspecialchars($tn['full_name']) ?>
                </option>
            <?php endwhile; ?>
        </select>
    </div>

    <div class="col-md-3">
        <label class="form-label">Ngày nhận</label>
        <input type="date" name="check_in" class="form-control" min="<?= $today ?>" value="<?= $check_in ?>" required>
    </div>

    <div class="col-md-3">
        <label class="form-label">Ngày trả</label>
        <input type="date" name="check_out" class="form-control" min="<?= $check_in ?>" value="<?= $check_out ?>" required>
    </div>

    <div class="col-md-3">
        <label class="form-label">Loại phòng</label>
        <input type="text" class="form-control" value="<?= htmlspecialchars($bk['type_name']) ?>" disabled>
        <input type="hidden" name="type_id" value="<?= (int)$type_id ?>">
    </div>

    <div class="col-md-3">
        <label class="form-label">Ảnh phòng</label><br>
        <img id="roomImage"
             src="/quanlyphongtro/admin/uploads/rooms/<?= htmlspecialchars($initImg) ?>"
             style="width:220px;height:140px;object-fit:cover"
             class="rounded border">
    </div>

    <div class="col-12">
        <button class="btn btn-success">
            <i class="bi bi-check-circle"></i> Lưu thay đổi
        </button>
        <a href="index.php" class="btn btn-secondary">Hủy</a>
    </div>

</form>

</div>
</div>
</section>

<script>
function showRoomImage(select) {
    const img = document.getElementById('roomImage');
    const opt = select.options[select.selectedIndex];
    const image = opt ? opt.getAttribute('data-image') : '';

    if (image && image.trim() !== '') {
        img.src = '/quanlyphongtro/admin/uploads/rooms/' + image;
    } else {
        img.src = '/quanlyphongtro/admin/uploads/rooms/no-image.jpg';
    }
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
