<?php
require_once __DIR__ . '/../../includes/db.php';

function generateContractCode($conn) {
    $date = date('Ym');
    $rs = mysqli_query($conn, "
        SELECT contract_code
        FROM contracts
        WHERE contract_code LIKE 'CT{$date}-%'
        ORDER BY contract_code DESC
        LIMIT 1
    ");
    if ($row = mysqli_fetch_assoc($rs)) {
        $num = (int)substr($row['contract_code'], -3) + 1;
    } else {
        $num = 1;
    }
    return 'CT'.$date.'-'.str_pad($num, 3, '0', STR_PAD_LEFT);
}

/* tạo từ yêu cầu thuê (booking) */
$booking_id = (isset($_GET['booking_id']) && is_numeric($_GET['booking_id'])) ? (int)$_GET['booking_id'] : 0;
$prefill = null;

if ($booking_id) {
    $bk = mysqli_query($conn, "
        SELECT booking_id, tenant_id, room_id, check_in, status
        FROM bookings
        WHERE booking_id = $booking_id
        LIMIT 1
    ");
    if ($bk && mysqli_num_rows($bk) > 0) $prefill = mysqli_fetch_assoc($bk);
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tenant_id = (int)($_POST['tenant_id'] ?? 0);
    $room_id   = (int)($_POST['room_id'] ?? 0);

    $start_date = $_POST['start_date'] ?? '';
    $months     = (int)($_POST['months'] ?? 1);

    $billing_day = (int)($_POST['billing_day'] ?? 1);
    $note = mysqli_real_escape_string($conn, trim($_POST['note'] ?? ''));

    $booking_id_post = isset($_POST['booking_id']) ? (int)$_POST['booking_id'] : 0;

    if (!$tenant_id || !$room_id || !$start_date) {
        $error = 'Vui lòng nhập đủ thông tin.';
    } elseif ($months < 1) {
        $error = 'Thời hạn thuê tối thiểu là 1 tháng.';
    } elseif ($billing_day < 1 || $billing_day > 28) {
        $error = 'Ngày thu tiền phải từ 1 đến 28.';
    } else {
        // Ngày kết thúc dự kiến: start + months tháng (ngày trả phòng)
        $end_date = date('Y-m-d', strtotime("+{$months} month", strtotime($start_date)));

        try {
            mysqli_begin_transaction($conn);

            // khóa phòng + lấy giá/tháng, cọc theo loại phòng (room_types)
            $roomRes = mysqli_query($conn, "
                SELECT r.room_id, r.room_status, r.type_id,
                       rt.price_per_month
                FROM rooms r
                JOIN room_types rt ON r.type_id = rt.type_id
                WHERE r.room_id = $room_id
                FOR UPDATE
            ");

            if (!$roomRes || mysqli_num_rows($roomRes) === 0) {
                mysqli_rollback($conn);
                $error = 'Phòng không tồn tại.';
            } else {
                $room = mysqli_fetch_assoc($roomRes);

                if ($room['room_status'] !== 'VACANT') {
                    mysqli_rollback($conn);
                    $error = 'Chỉ tạo hợp đồng khi phòng đang TRỐNG.';
                } else {
                    // chống trường hợp phòng đã có hợp đồng ACTIVE
                    $active = mysqli_query($conn, "
                        SELECT 1 FROM contracts
                        WHERE room_id = $room_id AND contract_status = 'ACTIVE'
                        LIMIT 1
                    ");
                    if ($active && mysqli_num_rows($active) > 0) {
                        mysqli_rollback($conn);
                        $error = 'Phòng đã có hợp đồng đang hiệu lực.';
                    } else {

                        $rent_amount = (float)($room['price_per_month'] ?? 0);

                        if ($rent_amount <= 0) {
                            mysqli_rollback($conn);
                            $error = 'Giá thuê/tháng chưa được cấu hình trong CSDL (room_types.price_per_month).';
                        } else {

                            // (tuỳ chọn) tiền cọc = 1 tháng tiền thuê
                            $deposit_amount = $rent_amount;

                            $contract_code = generateContractCode($conn);

                            mysqli_query($conn, "
                                INSERT INTO contracts
                                    (contract_code, room_id, start_date, end_date, rent_amount, deposit_amount, billing_day, contract_status, note)
                                VALUES
                                    ('$contract_code', $room_id, '$start_date', '$end_date', $rent_amount, $deposit_amount, $billing_day, 'ACTIVE', '$note')
                            ");

                            $contract_id = mysqli_insert_id($conn);
                            if (!$contract_id) {
                                mysqli_rollback($conn);
                                $error = 'Không tạo được hợp đồng.';
                            } else {

                                // gán khách đại diện
                                mysqli_query($conn, "
                                    INSERT INTO contract_tenants (contract_id, tenant_id, is_representative, move_in_date)
                                    VALUES ($contract_id, $tenant_id, 1, '$start_date')
                                ");

                                // set phòng OCCUPIED
                                mysqli_query($conn, "
                                    UPDATE rooms
                                    SET room_status = 'OCCUPIED'
                                    WHERE room_id = $room_id
                                ");

                                // nếu tạo từ yêu cầu thuê: chuyển booking -> CHECKED_IN (đang thuê)
                                if ($booking_id_post) {
                                    mysqli_query($conn, "
                                        UPDATE bookings
                                        SET status = 'CHECKED_IN',
                                            note = CONCAT(IFNULL(note,''), ' | Tạo HĐ: $contract_code')
                                        WHERE booking_id = $booking_id_post
                                          AND status IN ('CONFIRMED','PENDING')
                                    ");
                                }

                                mysqli_commit($conn);
                                header('Location: index.php?msg=created');
                                exit;
                            }
                        }
                    }
                }
            }
        } catch (Throwable $e) {
            mysqli_rollback($conn);
            $error = 'Có lỗi hệ thống, vui lòng thử lại.';
        }
    }
}

require_once __DIR__ . '/../../includes/header.php';

/* rooms trống + lấy giá/tháng để hiển thị */
$rooms = mysqli_query($conn, "
    SELECT r.room_id, r.room_code, b.building_name,
           rt.price_per_month
    FROM rooms r
    JOIN buildings b ON r.building_id = b.building_id
    JOIN room_types rt ON r.type_id = rt.type_id
    WHERE r.room_status = 'VACANT'
    ORDER BY b.building_name, r.room_code
");

/* tenants */
$tenants = mysqli_query($conn, "
    SELECT tenant_id, full_name, phone
    FROM tenants
    ORDER BY full_name
");

$start_default  = $prefill['check_in'] ?? date('Y-m-d');
$tenant_default = $prefill['tenant_id'] ?? 0;
$room_default   = $prefill['room_id'] ?? 0;
?>

<div class="pagetitle d-flex justify-content-between align-items-center">
    <h1>Thêm hợp đồng (thuê tối thiểu 1 tháng)</h1>
    <a href="index.php" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Quay lại</a>
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

<?php if ($booking_id && $prefill): ?>
<div class="alert alert-info">
    Tạo hợp đồng từ Yêu cầu thuê #<?= (int)$booking_id ?>.
</div>
<?php endif; ?>

<form method="post" class="row g-3" id="contractForm">
    <input type="hidden" name="booking_id" value="<?= (int)$booking_id ?>">

    <div class="col-md-6">
        <label class="form-label">Khách đại diện</label>
        <select name="tenant_id" class="form-select" required>
            <option value="">-- Chọn khách --</option>
            <?php while ($t = mysqli_fetch_assoc($tenants)): ?>
                <option value="<?= $t['tenant_id'] ?>" <?= ((int)$tenant_default===(int)$t['tenant_id'])?'selected':'' ?>>
                    <?= htmlspecialchars($t['full_name']) ?><?= $t['phone'] ? ' - '.$t['phone'] : '' ?>
                </option>
            <?php endwhile; ?>
        </select>
    </div>

    <div class="col-md-6">
        <label class="form-label">Phòng (chỉ phòng Trống)</label>
        <select name="room_id" class="form-select" required id="roomSelect">
            <option value="">-- Chọn phòng --</option>
            <?php while ($r = mysqli_fetch_assoc($rooms)): ?>
                <option value="<?= $r['room_id'] ?>"
                        data-rent="<?= (float)$r['price_per_month'] ?>"
                        <?= ((int)$room_default===(int)$r['room_id'])?'selected':'' ?>>
                    <?= htmlspecialchars($r['building_name']) ?> - Phòng <?= htmlspecialchars($r['room_code']) ?>
                    (<?= number_format((float)$r['price_per_month']) ?> đ/tháng)
                </option>
            <?php endwhile; ?>
        </select>
    </div>

    <div class="col-md-3">
        <label class="form-label">Ngày bắt đầu</label>
        <input type="date" name="start_date" class="form-control" value="<?= $start_default ?>" required id="startDate">
    </div>

    <div class="col-md-3">
        <label class="form-label">Thời hạn thuê (tháng)</label>
        <input type="number" name="months" class="form-control" min="1" value="1" required id="months">
    </div>

    <div class="col-md-3">
        <label class="form-label">Ngày thu tiền (1-28)</label>
        <input type="number" name="billing_day" min="1" max="28" class="form-control" value="1" required>
    </div>

    <div class="col-md-3">
        <label class="form-label">Ngày kết thúc dự kiến</label>
        <input type="text" class="form-control" id="endDatePreview" value="" disabled>
        <small class="text-muted">Tự tính = Ngày bắt đầu + số tháng</small>
    </div>

    <div class="col-md-6">
        <label class="form-label">Tiền thuê/tháng (tự lấy từ CSDL)</label>
        <input type="text" class="form-control" id="rentPreview" value="0" disabled>
        <small class="text-muted">Lấy từ room_types.price_per_month</small>
    </div>

    <div class="col-md-6">
        <label class="form-label">Tiền cọc (mặc định = 1 tháng)</label>
        <input type="text" class="form-control" id="depositPreview" value="0" disabled>
        <small class="text-muted">Nếu bạn muốn quy tắc khác, tôi sẽ đổi theo yêu cầu</small>
    </div>

    <div class="col-12">
        <label class="form-label">Ghi chú</label>
        <input type="text" name="note" class="form-control" placeholder="VD: SV ở 2 người, ưu tiên yên tĩnh...">
    </div>

    <div class="col-12">
        <button class="btn btn-success"><i class="bi bi-check-circle"></i> Tạo hợp đồng</button>
        <a href="index.php" class="btn btn-secondary">Hủy</a>
    </div>
</form>

</div>
</div>
</section>

<script>
function formatVND(n){
    n = Number(n || 0);
    return n.toLocaleString('vi-VN') + ' đ';
}
function addMonths(dateStr, months){
    // dateStr: YYYY-MM-DD
    const [y,m,d] = dateStr.split('-').map(Number);
    const dt = new Date(y, m-1, d);
    dt.setMonth(dt.getMonth() + Number(months || 0));
    const yy = dt.getFullYear();
    const mm = String(dt.getMonth()+1).padStart(2,'0');
    const dd = String(dt.getDate()).padStart(2,'0');
    return `${yy}-${mm}-${dd}`;
}
function refreshPreview(){
    const roomOpt = document.getElementById('roomSelect').selectedOptions[0];
    const rent = roomOpt ? (roomOpt.getAttribute('data-rent') || 0) : 0;

    const start = document.getElementById('startDate').value;
    const months = document.getElementById('months').value || 1;
    const end = start ? addMonths(start, months) : '';

    document.getElementById('rentPreview').value = formatVND(rent);
    document.getElementById('depositPreview').value = formatVND(rent); // mặc định = 1 tháng
    document.getElementById('endDatePreview').value = end;
}

document.getElementById('roomSelect').addEventListener('change', refreshPreview);
document.getElementById('startDate').addEventListener('change', refreshPreview);
document.getElementById('months').addEventListener('input', refreshPreview);
refreshPreview();
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
