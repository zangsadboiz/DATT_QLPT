<?php
/**
 * Admin - Xem tất cả Phòng (Read-only)
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/pagination.php';
require_auth();

$buildingId = (int)($_GET['building_id'] ?? 0);
$ownerId = (int)($_GET['owner_id'] ?? 0);
$filter = $_GET['filter'] ?? 'all';
$search = trim($_GET['search'] ?? '');
$filterRentalType = $_GET['rental_type'] ?? '';

// Date filter
$filterCheckIn = $_GET['check_in'] ?? date('Y-m-d');
$filterCheckOut = $_GET['check_out'] ?? date('Y-m-d', strtotime('+1 month'));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $filterCheckIn)) $filterCheckIn = date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $filterCheckOut)) $filterCheckOut = date('Y-m-d', strtotime('+1 month'));
if ($filterCheckOut < $filterCheckIn) $filterCheckOut = $filterCheckIn;

// Lấy thông tin owner nếu có
$ownerName = '';
if ($ownerId > 0) {
    $rsOwner = mysqli_query($conn, "SELECT full_name FROM users WHERE user_id = $ownerId");
    if ($rsOwner && ($ownerRow = mysqli_fetch_assoc($rsOwner))) {
        $ownerName = $ownerRow['full_name'];
    }
}

// Get rooms with dynamic booking status based on date range
$rooms = [];

// Build where
$where = "r.deleted_at IS NULL";
if ($buildingId > 0) $where .= " AND r.building_id = $buildingId";
if ($ownerId > 0) $where .= " AND b.owner_id = $ownerId";

$sql = "
    SELECT r.*, r.rental_type, r.daily_price, b.building_name, b.address, b.owner_id,
           (SELECT image_path FROM room_images WHERE room_id = r.room_id AND is_primary = 1 LIMIT 1) as primary_image,
           -- Booking status trong khoảng ngày lọc
           (SELECT status FROM bookings 
            WHERE room_id = r.room_id 
              AND status IN ('CHECKED_IN', 'DEPOSIT_PAID', 'CONFIRMED', 'PENDING')
              AND check_in < '$filterCheckOut'
              AND (check_out IS NULL OR check_out > '$filterCheckIn')
            ORDER BY FIELD(status, 'CHECKED_IN', 'DEPOSIT_PAID', 'CONFIRMED', 'PENDING')
            LIMIT 1) as active_booking_status,
           -- Contract active trong khoảng ngày
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

// Dynamic stats - sẽ tính dựa trên khoảng thời gian lọc
$dynamicStats = ['total' => 0, 'vacant' => 0, 'occupied' => 0, 'maintenance' => 0];

$rs = mysqli_query($conn, $sql);
if ($rs) {
    while ($row = mysqli_fetch_assoc($rs)) {
        // Lấy owner name
        $rsOwner = mysqli_query($conn, "SELECT full_name FROM users WHERE user_id = " . (int)($row['owner_id'] ?? 0));
        $owner = $rsOwner ? mysqli_fetch_assoc($rsOwner) : null;
        $row['owner_name'] = $owner['full_name'] ?? 'N/A';
        
        // Tính trạng thái động
        if ($row['room_status'] === 'MAINTENANCE') {
            $row['dynamic_status'] = 'MAINTENANCE';
        } elseif (!empty($row['active_contract_id']) || !empty($row['active_booking_status'])) {
            $row['dynamic_status'] = 'OCCUPIED';
        } else {
            $row['dynamic_status'] = 'VACANT';
        }
        
        // Cập nhật thống kê động (trước khi lọc theo filter)
        $dynamicStats['total']++;
        if ($row['dynamic_status'] === 'VACANT') $dynamicStats['vacant']++;
        elseif ($row['dynamic_status'] === 'OCCUPIED') $dynamicStats['occupied']++;
        elseif ($row['dynamic_status'] === 'MAINTENANCE') $dynamicStats['maintenance']++;
        
        // Filter by status
        if ($filter === 'vacant' && $row['dynamic_status'] !== 'VACANT') continue;
        if ($filter === 'occupied' && $row['dynamic_status'] !== 'OCCUPIED') continue;
        if ($filter === 'maintenance' && $row['dynamic_status'] !== 'MAINTENANCE') continue;
        
        // Filter by rental type
        if ($filterRentalType !== '' && ($row['rental_type'] ?? 'MONTHLY') !== $filterRentalType) continue;
        
        // Filter by search
        if ($search !== '') {
            $match = stripos($row['room_code'], $search) !== false 
                  || stripos($row['building_name'], $search) !== false 
                  || stripos($row['address'], $search) !== false
                  || stripos($row['owner_name'], $search) !== false;
            if (!$match) continue;
        }
        
        $rooms[] = $row;
    }
}

// Sử dụng dynamic stats cho hiển thị
$stats = $dynamicStats;

// Pagination - apply to filtered rooms array
$totalRooms = count($rooms);
$perPage = 10;
$paging = pagination_calc($totalRooms, $perPage);
$pagedRooms = array_slice($rooms, $paging['offset'], $paging['per_page']);


// Buildings for filter
$buildings = [];
$rsB = mysqli_query($conn, "SELECT building_id, building_name FROM buildings ORDER BY building_name");
while ($rsB && ($b = mysqli_fetch_assoc($rsB))) $buildings[] = $b;

// Current building
$currentBuilding = null;
if ($buildingId > 0) {
    $currentBuilding = mysqli_fetch_assoc(mysqli_query($conn, "SELECT building_name FROM buildings WHERE building_id = $buildingId"));
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="pagetitle">
    <h1>
        <i class="bi bi-door-open me-2"></i>
        <?php if ($ownerName): ?>
            Phòng của <?= htmlspecialchars($ownerName) ?>
        <?php elseif ($currentBuilding): ?>
            Phòng: <?= htmlspecialchars($currentBuilding['building_name']) ?>
        <?php else: ?>
            Tất cả Phòng (Admin)
        <?php endif; ?>
    </h1>
    <nav>
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= ADMIN_BASE_PATH ?>/index.php">Dashboard</a></li>
            <?php if ($ownerName): ?>
                <li class="breadcrumb-item"><a href="<?= ADMIN_BASE_PATH ?>/modules/users/index.php">Chủ trọ</a></li>
            <?php endif; ?>
            <li class="breadcrumb-item"><a href="<?= ADMIN_BASE_PATH ?>/modules/admin_buildings/index.php">Dãy trọ</a></li>
            <li class="breadcrumb-item active">Phòng</li>
        </ol>
    </nav>
</div>

<section class="section">
    <!-- Stats -->
    <div class="row mb-3">
        <div class="col">
            <a href="?building_id=<?= $buildingId ?>&search=<?= urlencode($search) ?>&filter=all&check_in=<?= urlencode($filterCheckIn) ?>&check_out=<?= urlencode($filterCheckOut) ?>" 
               class="card text-center text-decoration-none <?= $filter === 'all' ? 'border-primary border-2' : '' ?>">
                <div class="card-body py-3">
                    <h4 class="text-primary mb-0"><?= (int)$stats['total'] ?></h4>
                    <small>Tất cả</small>
                </div>
            </a>
        </div>
        <div class="col">
            <a href="?building_id=<?= $buildingId ?>&search=<?= urlencode($search) ?>&filter=vacant&check_in=<?= urlencode($filterCheckIn) ?>&check_out=<?= urlencode($filterCheckOut) ?>" 
               class="card text-center text-decoration-none <?= $filter === 'vacant' ? 'border-success border-2' : '' ?>">
                <div class="card-body py-3">
                    <h4 class="text-success mb-0"><?= (int)$stats['vacant'] ?></h4>
                    <small>Còn trống</small>
                </div>
            </a>
        </div>
        <div class="col">
            <a href="?building_id=<?= $buildingId ?>&search=<?= urlencode($search) ?>&filter=occupied&check_in=<?= urlencode($filterCheckIn) ?>&check_out=<?= urlencode($filterCheckOut) ?>" 
               class="card text-center text-decoration-none <?= $filter === 'occupied' ? 'border-info border-2' : '' ?>">
                <div class="card-body py-3">
                    <h4 class="text-info mb-0"><?= (int)$stats['occupied'] ?></h4>
                    <small>Đang thuê</small>
                </div>
            </a>
        </div>
        <div class="col">
            <a href="?building_id=<?= $buildingId ?>&search=<?= urlencode($search) ?>&filter=maintenance&check_in=<?= urlencode($filterCheckIn) ?>&check_out=<?= urlencode($filterCheckOut) ?>" 
               class="card text-center text-decoration-none <?= $filter === 'maintenance' ? 'border-warning border-2' : '' ?>">
                <div class="card-body py-3">
                    <h4 class="text-warning mb-0"><?= (int)$stats['maintenance'] ?></h4>

                    <small>Bảo trì</small>
                </div>
            </a>
        </div>
    </div>
    
    <!-- Lọc & Tìm kiếm -->
    <div class="card mb-3">
        <div class="card-body py-3">
            <form method="get" class="row g-2 align-items-end">
                <div class="col-md-2">
                    <label class="form-label small mb-1">Từ ngày</label>
                    <input type="date" name="check_in" class="form-control form-control-sm" 
                           value="<?= htmlspecialchars($filterCheckIn) ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label small mb-1">Đến ngày</label>
                    <input type="date" name="check_out" class="form-control form-control-sm" 
                           value="<?= htmlspecialchars($filterCheckOut) ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label small mb-1">Dãy trọ</label>
                    <select name="building_id" class="form-select form-select-sm">
                        <option value="">Tất cả dãy trọ</option>
                        <?php foreach ($buildings as $b): ?>
                            <option value="<?= $b['building_id'] ?>" <?= $buildingId == $b['building_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($b['building_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small mb-1">Loại thuê</label>
                    <select name="rental_type" class="form-select form-select-sm">
                        <option value="">Tất cả</option>
                        <option value="MONTHLY" <?= $filterRentalType === 'MONTHLY' ? 'selected' : '' ?>>Theo tháng</option>
                        <option value="DAILY" <?= $filterRentalType === 'DAILY' ? 'selected' : '' ?>>Theo ngày</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small mb-1">Tìm kiếm</label>
                    <input type="text" name="search" class="form-control form-control-sm"
                           placeholder="Mã phòng, dãy trọ..." value="<?= htmlspecialchars($search) ?>">
                </div>
                <input type="hidden" name="filter" value="<?= $filter ?>">
                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="bi bi-search me-1"></i>Lọc
                    </button>
                    <a href="?" class="btn btn-secondary btn-sm">Xóa lọc</a>
                </div>
            </form>
        </div>
    </div>
    
    <p class="text-muted small mb-2">
        <i class="bi bi-calendar-range me-1"></i>
        Trạng thái phòng từ <strong><?= date('d/m/Y', strtotime($filterCheckIn)) ?></strong> 
        đến <strong><?= date('d/m/Y', strtotime($filterCheckOut)) ?></strong>
    </p>
    
    <!-- List -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Danh sách Phòng</h5>
            <span class="badge bg-secondary"><?= count($rooms) ?> phòng</span>
        </div>
        <div class="card-body">
            <?php if (count($pagedRooms) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th style="width: 70px;">Ảnh</th>
                                <th>Phòng</th>
                                <th>Dãy trọ</th>
                                <th>Chủ trọ</th>
                                <th class="text-center">Tầng</th>
                                <th class="text-center">Diện tích</th>
                                <th class="text-end">Giá thuê</th>
                                <th class="text-center">Trạng thái</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pagedRooms as $r): ?>
                                <tr>
                                    <td>
                                        <?php if (!empty($r['primary_image'])): ?>
                                            <img src="/quanlyphongtro/uploads/rooms/<?= htmlspecialchars($r['primary_image']) ?>" 
                                                 class="rounded" style="width: 60px; height: 45px; object-fit: cover;">
                                        <?php else: ?>
                                            <div class="bg-light rounded d-flex align-items-center justify-content-center" style="width: 60px; height: 45px;">
                                                <i class="bi bi-image text-muted"></i>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong><?= htmlspecialchars($r['room_code']) ?></strong>
                                    </td>
                                    <td>
                                        <?= htmlspecialchars($r['building_name']) ?>
                                        <br><small class="text-muted"><?= htmlspecialchars($r['address']) ?></small>
                                    </td>
                                    <td><?= htmlspecialchars($r['owner_name']) ?></td>
                                    <td class="text-center"><?= (int)$r['floor'] ?></td>
                                    <td class="text-center"><?= $r['area'] ? $r['area'] . ' m²' : '-' ?></td>
                                    <td class="text-end">
                                        <?php if (($r['rental_type'] ?? 'MONTHLY') === 'DAILY'): ?>
                                            <strong class="text-danger"><?= number_format((float)$r['daily_price']) ?>đ</strong>
                                            <br><small class="text-info"><i class="bi bi-calendar-day"></i> /ngày</small>
                                        <?php else: ?>
                                            <strong class="text-danger"><?= number_format((float)$r['base_rent']) ?>đ</strong>
                                            <br><small class="text-muted">/tháng</small>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($r['dynamic_status'] === 'VACANT'): ?>
                                            <span class="badge bg-success">Còn trống</span>
                                        <?php elseif ($r['dynamic_status'] === 'OCCUPIED'): ?>
                                            <span class="badge bg-danger">Đang thuê</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning text-dark">Bảo trì</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php pagination_render($paging['current_page'], $paging['total_pages'], $paging['total_items'], $paging['per_page']); ?>
            <?php else: ?>
                <div class="text-center py-5">
                    <i class="bi bi-door-open fs-1 text-muted"></i>
                    <p class="text-muted mt-2">
                        <?php if ($search): ?>
                            Không tìm thấy phòng phù hợp với "<?= htmlspecialchars($search) ?>"
                        <?php else: ?>
                            Không có phòng nào
                        <?php endif; ?>
                    </p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <a href="<?= ADMIN_BASE_PATH ?>/modules/admin_buildings/index.php" class="btn btn-secondary">
        <i class="bi bi-arrow-left me-1"></i>Quay lại Dãy trọ
    </a>
</section>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
