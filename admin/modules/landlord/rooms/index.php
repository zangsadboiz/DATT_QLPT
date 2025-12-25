<?php
/**
 * Module Phòng - Danh sách (với ảnh, xem chi tiết, khóa phòng)
 */
require_once __DIR__ . '/../../../includes/auth.php';
require_landlord_login();

$userId = (int)($_SESSION['user_id'] ?? 0);
$buildingId = (int)($_GET['building_id'] ?? 0);

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $roomId = (int)($_POST['room_id'] ?? 0);
    $action = $_POST['action'] ?? '';
    
    // Verify ownership
    $room = mysqli_fetch_assoc(mysqli_query($conn, "
        SELECT r.room_id, r.room_status FROM rooms r
        JOIN buildings b ON b.building_id = r.building_id
        WHERE r.room_id = $roomId AND b.owner_id = $userId AND r.deleted_at IS NULL
    "));
    
    if ($room) {
        if ($action === 'delete') {
            // Không cho xóa phòng đang thuê
            if ($room['room_status'] === 'OCCUPIED') {
                $_SESSION['alert'] = ['type' => 'danger', 'message' => 'Không thể xóa phòng đang có khách thuê!'];
            } else {
                mysqli_query($conn, "UPDATE rooms SET deleted_at = NOW() WHERE room_id = $roomId");
                $_SESSION['alert'] = ['type' => 'success', 'message' => 'Đã xóa phòng thành công!'];
            }
        } elseif ($action === 'lock') {
            // Không cho khóa phòng đang thuê
            if ($room['room_status'] === 'OCCUPIED') {
                $_SESSION['alert'] = ['type' => 'danger', 'message' => 'Không thể khóa phòng đang có khách thuê!'];
            } else {
                $newStatus = ($room['room_status'] === 'MAINTENANCE') ? 'VACANT' : 'MAINTENANCE';
                mysqli_query($conn, "UPDATE rooms SET room_status = '$newStatus', updated_at = NOW() WHERE room_id = $roomId");
                $msg = ($newStatus === 'MAINTENANCE') ? 'Đã khóa phòng (bảo trì)' : 'Đã mở khóa phòng';
                $_SESSION['alert'] = ['type' => 'success', 'message' => $msg];
            }
        }
    }
    
    $redirect = ADMIN_BASE_PATH . '/modules/landlord/rooms/index.php';
    if ($buildingId > 0) $redirect .= '?building_id=' . $buildingId;
    header('Location: ' . $redirect);
    exit;
}

// Get buildings for filter (tất cả, bao gồm cả đã ẩn để bảo trì)
$buildings = [];
$rsB = mysqli_query($conn, "SELECT building_id, building_name, building_status FROM buildings WHERE owner_id = $userId ORDER BY building_name");
while ($rsB && ($b = mysqli_fetch_assoc($rsB))) $buildings[] = $b;

// Get stats - Dựa trên booking trong khoảng thời gian
$statsWhere = "r.deleted_at IS NULL AND b.owner_id = $userId";
if ($buildingId > 0) $statsWhere .= " AND r.building_id = $buildingId";

// Lấy date filter trước
$filterCheckIn = $_GET['check_in'] ?? '';
$filterCheckOut = $_GET['check_out'] ?? '';
$tempCheckIn = $filterCheckIn ?: date('Y-m-d');
$tempCheckOut = $filterCheckOut ?: date('Y-m-d', strtotime('+1 month'));

$stats = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT 
        COUNT(*) as total,
        SUM(CASE 
            WHEN EXISTS (
                SELECT 1 FROM bookings bk
                WHERE bk.room_id = r.room_id
                  AND bk.status IN ('DEPOSIT_PAID', 'CHECKED_IN')
                  AND bk.check_in < '$tempCheckOut'
                  AND (bk.check_out IS NULL OR bk.check_out > '$tempCheckIn')
            ) THEN 1 ELSE 0 
        END) as rented,
        SUM(CASE 
            WHEN NOT EXISTS (
                SELECT 1 FROM bookings bk
                WHERE bk.room_id = r.room_id
                  AND bk.status IN ('DEPOSIT_PAID', 'CHECKED_IN')
                  AND bk.check_in < '$tempCheckOut'
                  AND (bk.check_out IS NULL OR bk.check_out > '$tempCheckIn')
            ) AND r.room_status != 'MAINTENANCE' THEN 1 ELSE 0 
        END) as available,
        SUM(CASE WHEN r.room_status = 'MAINTENANCE' THEN 1 ELSE 0 END) as maintenance
    FROM rooms r
    JOIN buildings b ON b.building_id = r.building_id
    WHERE $statsWhere
")) ?: ['total' => 0, 'rented' => 0, 'available' => 0, 'maintenance' => 0];

// Filter by status
$filter = $_GET['filter'] ?? 'all';
$where = "b.owner_id = $userId AND r.deleted_at IS NULL";
if ($buildingId > 0) $where .= " AND r.building_id = $buildingId";

// Filter by room_status (maintenance only)
if ($filter === 'maintenance') $where .= " AND r.room_status = 'MAINTENANCE'";

// Filter based on booking status in date range
if ($filter === 'rented') {
    // Phòng đã thuê trong khoảng thời gian
    $where .= " AND EXISTS (
        SELECT 1 FROM bookings bk
        WHERE bk.room_id = r.room_id
          AND bk.status IN ('DEPOSIT_PAID', 'CHECKED_IN')
          AND bk.check_in < '$filterCheckOut'
          AND (bk.check_out IS NULL OR bk.check_out > '$filterCheckIn')
    )";
} elseif ($filter === 'available') {
    // Phòng còn trống trong khoảng thời gian
    $where .= " AND NOT EXISTS (
        SELECT 1 FROM bookings bk
        WHERE bk.room_id = r.room_id
          AND bk.status IN ('DEPOSIT_PAID', 'CHECKED_IN')
          AND bk.check_in < '$filterCheckOut'
          AND (bk.check_out IS NULL OR bk.check_out > '$filterCheckIn')
    ) AND r.room_status != 'MAINTENANCE'";
}

// Filter by rental type
$filterRentalType = $_GET['rental_type'] ?? '';
if ($filterRentalType === 'DAILY') {
    $where .= " AND r.rental_type = 'DAILY'";
} elseif ($filterRentalType === 'MONTHLY') {
    $where .= " AND r.rental_type = 'MONTHLY'";
}

// Date filter - lọc theo khoảng thời gian
$filterCheckIn = $_GET['check_in'] ?? date('Y-m-d');
$filterCheckOut = $_GET['check_out'] ?? date('Y-m-d', strtotime('+1 month'));

// Validate dates
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $filterCheckIn)) $filterCheckIn = date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $filterCheckOut)) $filterCheckOut = date('Y-m-d', strtotime('+1 month'));
if ($filterCheckOut < $filterCheckIn) $filterCheckOut = $filterCheckIn;

// Get rooms with primary image and dynamic booking status based on date range
$rooms = [];
$sql = "
    SELECT r.*, b.building_name, b.address, b.building_status,
           (SELECT image_path FROM room_images WHERE room_id = r.room_id AND is_primary = 1 LIMIT 1) as primary_image,
           -- Trạng thái động từ booking TRONG KHOẢNG NGÀY LỌC
           (SELECT status FROM bookings 
            WHERE room_id = r.room_id 
              AND status IN ('CHECKED_IN', 'DEPOSIT_PAID', 'PENDING')
              AND check_in < '$filterCheckOut'
              AND (check_out IS NULL OR check_out > '$filterCheckIn')
            ORDER BY FIELD(status, 'CHECKED_IN', 'DEPOSIT_PAID', 'PENDING')
            LIMIT 1) as active_booking_status,
           -- Hợp đồng active trong khoảng ngày
           (SELECT contract_id FROM contracts 
            WHERE room_id = r.room_id 
              AND contract_status = 'ACTIVE'
              AND start_date < '$filterCheckOut'
              AND (end_date IS NULL OR end_date > '$filterCheckIn')
            LIMIT 1) as active_contract_id
    FROM rooms r
    JOIN buildings b ON b.building_id = r.building_id
    WHERE $where
    ORDER BY b.building_name, r.floor, r.room_code
";
$rs = mysqli_query($conn, $sql);
while ($rs && ($row = mysqli_fetch_assoc($rs))) {
    $rooms[] = $row;
}

// Get current building
$currentBuilding = null;
if ($buildingId > 0) {
    $currentBuilding = mysqli_fetch_assoc(mysqli_query($conn, "SELECT building_name, building_status FROM buildings WHERE building_id = $buildingId AND owner_id = $userId"));
}

// Build filter URL - preserve all current filters
function buildFilterUrl2($params) {
    global $buildingId, $filterCheckIn, $filterCheckOut, $filterRentalType;
    $base = [];
    if ($buildingId > 0) $base['building_id'] = $buildingId;
    if (!empty($filterCheckIn) && $filterCheckIn !== date('Y-m-d')) $base['check_in'] = $filterCheckIn;
    if (!empty($filterCheckOut) && $filterCheckOut !== date('Y-m-d', strtotime('+1 month'))) $base['check_out'] = $filterCheckOut;
    if (!empty($filterRentalType)) $base['rental_type'] = $filterRentalType;
    return '?' . http_build_query(array_merge($base, $params));
}

require_once __DIR__ . '/../../../includes/header.php';
?>

<div class="pagetitle">
    <h1>
        <i class="bi bi-door-open me-2"></i>
        <?= $currentBuilding ? 'Phòng: ' . htmlspecialchars($currentBuilding['building_name']) : 'Tất cả phòng' ?>
        <?php if ($currentBuilding && ($currentBuilding['building_status'] ?? '') === 'HIDDEN'): ?>
            <span class="badge bg-secondary fs-6">Dãy trọ đang ẩn</span>
        <?php endif; ?>
    </h1>
    <nav>
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= ADMIN_BASE_PATH ?>/modules/landlord/dashboard.php">Dashboard</a></li>
            <?php if ($buildingId > 0): ?>
                <li class="breadcrumb-item"><a href="<?= ADMIN_BASE_PATH ?>/modules/landlord/buildings/index.php">Dãy trọ</a></li>
            <?php endif; ?>
            <li class="breadcrumb-item active">Phòng</li>
        </ol>
    </nav>
</div>

<?php if ($alert = $_SESSION['alert'] ?? null): unset($_SESSION['alert']); ?>
    <div class="alert alert-<?= $alert['type'] ?> alert-dismissible fade show">
        <?= htmlspecialchars($alert['message']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<section class="section">
    <!-- Stats Cards -->
    <div class="row mb-3">
        <div class="col">
            <a href="<?= buildFilterUrl2(['filter' => 'all']) ?>" class="card text-center text-decoration-none <?= $filter === 'all' ? 'border-primary border-2' : '' ?>">
                <div class="card-body py-3">
                    <h4 class="text-primary mb-0"><?= (int)$stats['total'] ?></h4>
                    <small>Tất cả</small>
                </div>
            </a>
        </div>
        <div class="col">
            <a href="<?= buildFilterUrl2(['filter' => 'rented']) ?>" class="card text-center text-decoration-none <?= $filter === 'rented' ? 'border-danger border-2' : '' ?>">
                <div class="card-body py-3">
                    <h4 class="text-danger mb-0"><?= (int)$stats['rented'] ?></h4>
                    <small>Đã thuê</small>
                </div>
            </a>
        </div>
        <div class="col">
            <a href="<?= buildFilterUrl2(['filter' => 'available']) ?>" class="card text-center text-decoration-none <?= $filter === 'available' ? 'border-success border-2' : '' ?>">
                <div class="card-body py-3">
                    <h4 class="text-success mb-0"><?= (int)$stats['available'] ?></h4>
                    <small>Còn trống</small>
                </div>
            </a>
        </div>
        <div class="col">
            <a href="<?= buildFilterUrl2(['filter' => 'maintenance']) ?>" class="card text-center text-decoration-none <?= $filter === 'maintenance' ? 'border-warning border-2' : '' ?>">
                <div class="card-body py-3">
                    <h4 class="text-warning mb-0"><?= (int)$stats['maintenance'] ?></h4>
                    <small>Bảo trì</small>
                </div>
            </a>
        </div>
    </div>
    
    
    <!-- Filters in one row -->
    <div class="card mb-3">
        <div class="card-body py-2">
            <form method="get" class="d-flex gap-2 align-items-center">
                <?php if ($buildingId > 0): ?><input type="hidden" name="building_id" value="<?= $buildingId ?>"><?php endif; ?>
                
                <!-- Building Filter -->
                <select class="form-select form-select-sm" style="width: 160px;" onchange="window.location.href='?building_id='+this.value">
                    <option value="">Tất cả dãy trọ</option>
                    <?php foreach ($buildings as $b): ?>
                        <option value="<?= $b['building_id'] ?>" <?= $buildingId == $b['building_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($b['building_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                
                <!-- Status Filter -->
                <select class="form-select form-select-sm" style="width: 140px;" name="filter" onchange="this.form.submit()">
                    <option value="">Tất cả (<?= $stats['total'] ?>)</option>
                    <option value="rented" <?= $filter === 'rented' ? 'selected' : '' ?>>Đã thuê (<?= $stats['rented'] ?>)</option>
                    <option value="available" <?= $filter === 'available' ? 'selected' : '' ?>>Còn trống (<?= $stats['available'] ?>)</option>
                    <option value="maintenance" <?= $filter === 'maintenance' ? 'selected' : '' ?>>Bảo trì (<?= $stats['maintenance'] ?>)</option>
                </select>
                
                <!-- Rental Type Filter -->
                <select class="form-select form-select-sm" style="width: 130px;" name="rental_type" onchange="this.form.submit()">
                    <option value="">Loại thuê</option>
                    <option value="MONTHLY" <?= $filterRentalType === 'MONTHLY' ? 'selected' : '' ?>>Theo tháng</option>
                    <option value="DAILY" <?= $filterRentalType === 'DAILY' ? 'selected' : '' ?>>Theo ngày</option>
                </select>
                
                <div class="vr"></div>
                
                <!-- Date Range -->
                <input type="date" class="form-control form-control-sm" name="check_in" value="<?= htmlspecialchars($filterCheckIn) ?>" style="width: 135px;">
                <span class="text-muted small">→</span>
                <input type="date" class="form-control form-control-sm" name="check_out" value="<?= htmlspecialchars($filterCheckOut) ?>" style="width: 135px;">
                
                <button type="submit" class="btn btn-sm btn-primary">
                    <i class="bi bi-search"></i>
                </button>
                
                <?php if ($filterCheckIn || $filterCheckOut || $filter !== 'all'): ?>
                <a href="?<?= $buildingId > 0 ? 'building_id='.$buildingId : '' ?>" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-x"></i>
                </a>
                <?php endif; ?>
            </form>
        </div>
    </div>
    
    <!-- Rooms List -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Danh sách phòng</h5>
            <?php if (count($buildings) > 0): ?>
                <a href="<?= ADMIN_BASE_PATH ?>/modules/landlord/rooms/add.php<?= $buildingId > 0 ? '?building_id='.$buildingId : '' ?>" class="btn btn-primary">
                    <i class="bi bi-plus-circle me-1"></i>Thêm phòng
                </a>
            <?php else: ?>
                <a href="<?= ADMIN_BASE_PATH ?>/modules/landlord/buildings/add.php" class="btn btn-warning">
                    <i class="bi bi-building me-1"></i>Tạo dãy trọ trước
                </a>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <?php if (count($rooms) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th style="width: 70px;">Ảnh</th>
                                <th>Mã phòng</th>
                                <th>Dãy trọ</th>
                                <th>Tầng</th>
                                <th>Diện tích</th>
                                <th>Giá thuê</th>
                                <th>Số người</th>
                                <th>Trạng thái</th>
                                <th class="text-center" style="width: 160px;">Hành động</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rooms as $r): 
                                $isHiddenBuilding = ($r['building_status'] ?? '') === 'HIDDEN';
                            ?>
                                <tr class="<?= $isHiddenBuilding ? 'table-secondary' : '' ?>">
                                    <td>
                                        <?php if ($r['primary_image']): ?>
                                            <img src="/quanlyphongtro/uploads/rooms/<?= htmlspecialchars($r['primary_image']) ?>" 
                                                 class="rounded" style="width: 60px; height: 45px; object-fit: cover;">
                                        <?php else: ?>
                                            <div class="bg-light rounded d-flex align-items-center justify-content-center" 
                                                 style="width: 60px; height: 45px;">
                                                <i class="bi bi-image text-muted"></i>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td><strong><?= htmlspecialchars($r['room_code']) ?></strong></td>
                                    <td>
                                        <?= htmlspecialchars($r['building_name']) ?>
                                        <?php if ($isHiddenBuilding): ?><span class="badge bg-secondary">Ẩn</span><?php endif; ?>
                                        <br><small class="text-muted"><?= htmlspecialchars($r['address']) ?></small>
                                    </td>
                                    <td>Tầng <?= (int)$r['floor'] ?></td>
                                    <td><?= $r['area'] ? $r['area'] . ' m²' : '-' ?></td>
                                    <td>
                                        <?php if (($r['rental_type'] ?? 'MONTHLY') === 'DAILY'): ?>
                                            <strong class="text-danger"><?= number_format((float)$r['daily_price']) ?>đ</strong>
                                            <br><small class="text-info"><i class="bi bi-calendar-day"></i> /ngày</small>
                                        <?php else: ?>
                                            <strong class="text-danger"><?= number_format((float)$r['base_rent']) ?>đ</strong>
                                            <br><small class="text-muted">/tháng</small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= (int)$r['max_occupants'] ?> người</td>
                                    <td>
                                        <?php 
                                        // Hiển thị trạng thái động dựa trên booking/contract
                                        $dynamicStatus = 'VACANT';
                                        $statusBadge = '<span class="badge bg-success">Còn trống</span>';
                                        
                                        if ($r['room_status'] === 'MAINTENANCE') {
                                            $statusBadge = '<span class="badge bg-warning text-dark">Bảo trì</span>';
                                        } elseif (!empty($r['active_contract_id'])) {
                                            $statusBadge = '<span class="badge bg-primary">Đang thuê</span>';
                                        } elseif (!empty($r['active_booking_status'])) {
                                            switch ($r['active_booking_status']) {
                                                case 'CHECKED_IN':
                                                    $statusBadge = '<span class="badge bg-primary">Đang thuê</span>';
                                                    break;
                                                case 'DEPOSIT_PAID':
                                                    $statusBadge = '<span class="badge bg-danger">Đã thuê</span>';
                                                    break;
                                                case 'PENDING':
                                                    $statusBadge = '<span class="badge bg-warning">Chờ thanh toán</span>';
                                                    break;
                                            }
                                        }
                                        echo $statusBadge;
                                        ?>
                                    </td>
                                    <td class="text-center">
                                        <div class="d-flex gap-1 justify-content-center">
                                            <a href="<?= ADMIN_BASE_PATH ?>/modules/landlord/rooms/view.php?id=<?= $r['room_id'] ?>" 
                                               class="btn btn-outline-info btn-sm" title="Xem" style="width: 32px;">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            <a href="<?= ADMIN_BASE_PATH ?>/modules/landlord/rooms/edit.php?id=<?= $r['room_id'] ?>" 
                                               class="btn btn-outline-primary btn-sm" title="Sửa" style="width: 32px;">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <form method="post" class="d-inline">
                                                <input type="hidden" name="room_id" value="<?= $r['room_id'] ?>">
                                                <input type="hidden" name="action" value="lock">
                                                <?php if ($r['room_status'] === 'MAINTENANCE'): ?>
                                                    <button type="submit" class="btn btn-outline-success btn-sm" title="Mở khóa" style="width: 32px;">
                                                        <i class="bi bi-unlock"></i>
                                                    </button>
                                                <?php else: ?>
                                                    <button type="submit" class="btn btn-outline-warning btn-sm" title="Khóa" style="width: 32px;"
                                                            onclick="return confirm('Khóa phòng này?')">
                                                        <i class="bi bi-lock"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </form>
                                            <form method="post" class="d-inline" onsubmit="return confirm('Xóa phòng này?');">
                                                <input type="hidden" name="room_id" value="<?= $r['room_id'] ?>">
                                                <input type="hidden" name="action" value="delete">
                                                <button type="submit" class="btn btn-outline-danger btn-sm" title="Xóa" style="width: 32px;">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center py-5">
                    <i class="bi bi-inbox fs-1 text-muted d-block mb-3"></i>
                    <?php
                    // Hiển thị message phù hợp với filter
                    $emptyMessages = [
                        'all' => 'Chưa có phòng nào',
                        'vacant' => 'Không có phòng còn trống',
                        'occupied' => 'Không có phòng đang cho thuê',
                        'maintenance' => 'Không có phòng đang bảo trì'
                    ];
                    $emptyMsg = $emptyMessages[$filter] ?? 'Chưa có phòng nào';
                    if ($buildingId > 0 && $currentBuilding) {
                        $emptyMsg .= ' trong ' . htmlspecialchars($currentBuilding['building_name']);
                    }
                    ?>
                    <p class="text-muted mb-3"><?= $emptyMsg ?></p>
                    <?php if ($filter !== 'all'): ?>
                        <a href="<?= buildFilterUrl2(['filter' => 'all']) ?>" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left me-1"></i>Xem tất cả phòng
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- Flatpickr Vietnamese -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<style>
.flatpickr-calendar {
    width: 320px !important;
    font-size: 14px;
}
.flatpickr-months {
    padding: 8px 0;
}
.flatpickr-current-month {
    font-size: 16px !important;
    padding-top: 0;
}
.flatpickr-current-month .cur-month {
    font-weight: 600;
}
.flatpickr-current-month input.cur-year {
    font-weight: 600;
    font-size: 16px;
}
.flatpickr-day {
    max-width: 40px;
    height: 40px;
    line-height: 40px;
}
</style>
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/vn.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var checkOutPicker;
    
    var checkInPicker = flatpickr('input[name="check_in"]', { 
        locale: 'vn', 
        dateFormat: 'Y-m-d',
        altInput: true,
        altFormat: 'd/m/Y',
        allowInput: true,
        disableMobile: true,
        minDate: 'today',
        onChange: function(selectedDates) {
            if (selectedDates.length > 0 && checkOutPicker) {
                checkOutPicker.set('minDate', selectedDates[0]);
            }
        }
    });
    
    checkOutPicker = flatpickr('input[name="check_out"]', { 
        locale: 'vn', 
        dateFormat: 'Y-m-d',
        altInput: true,
        altFormat: 'd/m/Y',
        allowInput: true,
        disableMobile: true,
        minDate: 'today'
    });
});
</script>

<?php require_once __DIR__ . '/../../../includes/footer.php'; ?>
