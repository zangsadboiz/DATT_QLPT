<?php
/**
 * Module Dãy trọ - Danh sách (với ảnh, xem, ẩn/hiện)
 */
require_once __DIR__ . '/../../../includes/auth.php';
require_landlord_login();

$userId = (int)($_SESSION['user_id'] ?? 0);

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $buildingId = (int)($_POST['building_id'] ?? 0);
    $action = $_POST['action'] ?? '';
    
    $rsBuilding = mysqli_query($conn, "SELECT * FROM buildings WHERE building_id = $buildingId AND owner_id = $userId");
    $building = $rsBuilding ? mysqli_fetch_assoc($rsBuilding) : null;
    
    if ($building) {
        if ($action === 'delete') {
            $rsRoomCount = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM rooms WHERE building_id = $buildingId AND deleted_at IS NULL");
            $roomCount = $rsRoomCount ? mysqli_fetch_assoc($rsRoomCount) : ['cnt' => 0];
            if (($roomCount['cnt'] ?? 0) > 0) {
                $_SESSION['alert'] = ['type' => 'danger', 'message' => 'Không thể xóa dãy trọ còn phòng!'];
            } else {
                mysqli_query($conn, "DELETE FROM buildings WHERE building_id = $buildingId");
                mysqli_query($conn, "DELETE FROM building_images WHERE building_id = $buildingId");
                $_SESSION['alert'] = ['type' => 'success', 'message' => 'Đã xóa dãy trọ!'];
            }
        } elseif ($action === 'toggle') {
            // Toggle ẩn/hiện - xử lý nhiều trạng thái
            $currentStatus = $building['building_status'] ?? 'ACTIVE';
            // Nếu đang HIDDEN -> chuyển sang ACTIVE, ngược lại chuyển sang HIDDEN
            $newStatus = ($currentStatus === 'HIDDEN') ? 'ACTIVE' : 'HIDDEN';
            $result = mysqli_query($conn, "UPDATE buildings SET building_status = '$newStatus', updated_at = NOW() WHERE building_id = $buildingId");
            if ($result) {
                $msg = ($newStatus === 'HIDDEN') ? 'Đã ẩn dãy trọ và các phòng' : 'Đã hiển thị dãy trọ';
                $_SESSION['alert'] = ['type' => 'success', 'message' => $msg];
            } else {
                $_SESSION['alert'] = ['type' => 'danger', 'message' => 'Lỗi cập nhật: ' . mysqli_error($conn)];
            }
        }
    } else {
        $_SESSION['alert'] = ['type' => 'danger', 'message' => 'Không tìm thấy dãy trọ!'];
    }
    
    header('Location: ' . ADMIN_BASE_PATH . '/modules/landlord/buildings/index.php');
    exit;
}


// Date filter
$fromDate = $_GET['from_date'] ?? '';
$toDate = $_GET['to_date'] ?? '';

// Get stats
$stats = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN building_status = 'ACTIVE' THEN 1 ELSE 0 END) as active,
        SUM(CASE WHEN building_status = 'HIDDEN' THEN 1 ELSE 0 END) as hidden
    FROM buildings WHERE owner_id = $userId
")) ?: ['total' => 0, 'active' => 0, 'hidden' => 0];

// Filter
$filter = $_GET['filter'] ?? 'all';
$regionFilter = isset($_GET['region']) && $_GET['region'] !== '' ? (int)$_GET['region'] : 0;
$provinceFilter = isset($_GET['province']) && $_GET['province'] !== '' ? (int)$_GET['province'] : 0;

$where = "b.owner_id = $userId";
if ($filter === 'active') $where .= " AND b.building_status = 'ACTIVE'";
if ($filter === 'hidden') $where .= " AND b.building_status = 'HIDDEN'";
if ($regionFilter > 0) $where .= " AND p.region_id = $regionFilter";
if ($provinceFilter > 0) $where .= " AND p.province_id = $provinceFilter";

// Get buildings with room stats
$buildings = [];

if ($fromDate && $toDate) {
    // Validate dates
    if (strtotime($toDate) < strtotime($fromDate)) {
        $toDate = $fromDate;
    }
    
    // With date filter - calculate based on bookings in period
    $fromDateEsc = mysqli_real_escape_string($conn, $fromDate);
    $toDateEsc = mysqli_real_escape_string($conn, $toDate);
    
    $sql = "
        SELECT b.*, d.district_name, p.province_name, p.region_id,
               (SELECT COUNT(*) FROM rooms WHERE building_id = b.building_id AND deleted_at IS NULL) as room_count,
               (SELECT COUNT(DISTINCT r.room_id) 
                FROM rooms r
                LEFT JOIN bookings bk ON bk.room_id = r.room_id 
                    AND bk.status IN ('DEPOSIT_PAID', 'CHECKED_IN')
                    AND bk.check_in < '$toDateEsc'
                    AND (bk.check_out IS NULL OR bk.check_out > '$fromDateEsc')
                WHERE r.building_id = b.building_id 
                    AND r.deleted_at IS NULL
                    AND bk.booking_id IS NULL
               ) as vacant_count,
               (SELECT COUNT(DISTINCT r.room_id) 
                FROM rooms r
                INNER JOIN bookings bk ON bk.room_id = r.room_id 
                    AND bk.status IN ('DEPOSIT_PAID', 'CHECKED_IN')
                    AND bk.check_in < '$toDateEsc'
                    AND (bk.check_out IS NULL OR bk.check_out > '$fromDateEsc')
                WHERE r.building_id = b.building_id 
                    AND r.deleted_at IS NULL
               ) as occupied_count,
               (SELECT image_path FROM building_images WHERE building_id = b.building_id AND is_primary = 1 LIMIT 1) as primary_image
        FROM buildings b
        LEFT JOIN districts d ON d.district_id = b.district_id
        LEFT JOIN provinces p ON p.province_id = d.province_id
        WHERE $where
        ORDER BY b.created_at DESC
    ";
} else {
    // No date filter - use current status
    $sql = "
        SELECT b.*, d.district_name, p.province_name, p.region_id,
               (SELECT COUNT(*) FROM rooms WHERE building_id = b.building_id AND deleted_at IS NULL) as room_count,
               (SELECT COUNT(*) FROM rooms WHERE building_id = b.building_id AND deleted_at IS NULL AND room_status = 'VACANT') as vacant_count,
               (SELECT COUNT(*) FROM rooms WHERE building_id = b.building_id AND deleted_at IS NULL AND room_status = 'OCCUPIED') as occupied_count,
               (SELECT image_path FROM building_images WHERE building_id = b.building_id AND is_primary = 1 LIMIT 1) as primary_image
        FROM buildings b
        LEFT JOIN districts d ON d.district_id = b.district_id
        LEFT JOIN provinces p ON p.province_id = d.province_id
        WHERE $where
        ORDER BY b.created_at DESC
    ";
}

$rs = mysqli_query($conn, $sql);
if (!$rs) {
    die("SQL Error: " . mysqli_error($conn));
}
while ($row = mysqli_fetch_assoc($rs)) {
    $buildings[] = $row;
}

require_once __DIR__ . '/../../../includes/header.php';
?>

<div class="pagetitle">
    <h1><i class="bi bi-building me-2"></i>Dãy trọ / Tòa nhà</h1>
    <nav>
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= ADMIN_BASE_PATH ?>/modules/landlord/dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item active">Dãy trọ</li>
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
        <div class="col-4">
            <a href="?" class="card text-center text-decoration-none <?= $filter === 'all' ? 'border-primary border-2' : '' ?>">
                <div class="card-body py-3">
                    <h4 class="text-primary mb-0"><?= (int)$stats['total'] ?></h4>
                    <small>Tất cả</small>
                </div>
            </a>
        </div>
        <div class="col-4">
            <a href="?filter=active" class="card text-center text-decoration-none <?= $filter === 'active' ? 'border-success border-2' : '' ?>">
                <div class="card-body py-3">
                    <h4 class="text-success mb-0"><?= (int)$stats['active'] ?></h4>
                    <small>Đang hoạt động</small>
                </div>
            </a>
        </div>
        <div class="col-4">
            <a href="?filter=hidden" class="card text-center text-decoration-none <?= $filter === 'hidden' ? 'border-secondary border-2' : '' ?>">
                <div class="card-body py-3">
                    <h4 class="text-secondary mb-0"><?= (int)$stats['hidden'] ?></h4>
                    <small>Đang ẩn</small>
                </div>
            </a>
        </div>
    </div>
    
    
    <!-- Filters in one row -->
    <div class="card mb-3">
        <div class="card-body py-2">
            <form method="get" class="d-flex gap-2 align-items-center flex-wrap">
                <input type="hidden" name="filter" value="<?= htmlspecialchars($filter) ?>">
                
                <!-- Status Filter -->
                <select name="filter" class="form-select form-select-sm" style="max-width: 180px;" onchange="this.form.submit()">
                    <option value="" <?= $filter === 'all' ? 'selected' : '' ?>>Tất cả (<?= $stats['total'] ?>)</option>
                    <option value="active" <?= $filter === 'active' ? 'selected' : '' ?>>Đang hoạt động (<?= $stats['active'] ?>)</option>
                    <option value="hidden" <?= $filter === 'hidden' ? 'selected' : '' ?>>Đang ẩn (<?= $stats['hidden'] ?>)</option>
                </select>
                
                <div class="vr"></div>
                
                <!-- Region Filter -->
                <select name="region" id="regionFilter" class="form-select form-select-sm" style="max-width: 150px;" onchange="this.form.submit()">
                    <option value="">-- Tất cả miền --</option>
                    <?php
                    $regions = mysqli_query($conn, "SELECT * FROM regions ORDER BY region_id");
                    while ($region = mysqli_fetch_assoc($regions)):
                    ?>
                        <option value="<?= $region['region_id'] ?>" <?= $regionFilter == $region['region_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($region['region_name']) ?>
                        </option>
                    <?php endwhile; ?>
                </select>
                
                <!-- Province Filter -->
                <select name="province" id="provinceFilter" class="form-select form-select-sm" style="max-width: 200px;" onchange="this.form.submit()">
                    <option value="">-- Tất cả tỉnh --</option>
                    <?php
                    $provinceQuery = "SELECT * FROM provinces";
                    if ($regionFilter > 0) $provinceQuery .= " WHERE region_id = $regionFilter";
                    $provinceQuery .= " ORDER BY province_name";
                    $provinces = mysqli_query($conn, $provinceQuery);
                    while ($province = mysqli_fetch_assoc($provinces)):
                    ?>
                        <option value="<?= $province['province_id'] ?>" <?= $provinceFilter == $province['province_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($province['province_name']) ?>
                        </option>
                    <?php endwhile; ?>
                </select>
                
                <div class="vr"></div>
                
                <!-- Date Range -->
                <input type="date" name="from_date" class="form-control form-control-sm" style="max-width: 150px;" placeholder="Từ ngày" value="<?= htmlspecialchars($fromDate) ?>">
                <span class="text-muted">→</span>
                <input type="date" name="to_date" class="form-control form-control-sm" style="max-width: 150px;" placeholder="Đến ngày" value="<?= htmlspecialchars($toDate) ?>">
                
                <button type="submit" class="btn btn-sm btn-primary">
                    <i class="bi bi-search"></i> Lọc
                </button>
                
                <?php if ($fromDate || $toDate || $filter !== 'all'): ?>
                <a href="?" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-x"></i> Xóa
                </a>
                <?php endif; ?>
                
                <div class="ms-auto">
                    <a href="<?= ADMIN_BASE_PATH ?>/modules/landlord/buildings/add.php" class="btn btn-sm btn-success">
                        <i class="bi bi-plus-circle"></i> Thêm dãy trọ
                    </a>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Buildings List -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Danh sách dãy trọ</h5>
        </div>
        <div class="card-body">
            <?php if (count($buildings) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th style="width: 70px;">Ảnh</th>
                                <th>Mã</th>
                                <th>Dãy trọ</th>
                                <th>Địa chỉ</th>
                                <th class="text-center">Phòng</th>
                                <th class="text-center">Trống</th>
                                <th class="text-center">Đã thuê</th>
                                <th class="text-center">Trạng thái</th>
                                <th class="text-center" style="width: 150px;">Hành động</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($buildings as $b): ?>
                                <tr class="<?= $b['building_status'] === 'HIDDEN' ? 'table-secondary' : '' ?>">
                                    <td>
                                        <?php if ($b['primary_image']): ?>
                                            <img src="/quanlyphongtro/uploads/buildings/<?= htmlspecialchars($b['primary_image']) ?>" 
                                                 class="rounded" style="width: 60px; height: 45px; object-fit: cover;">
                                        <?php else: ?>
                                            <div class="bg-light rounded d-flex align-items-center justify-content-center" 
                                                 style="width: 60px; height: 45px;">
                                                <i class="bi bi-building text-muted"></i>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td><code><?= htmlspecialchars($b['building_code']) ?></code></td>
                                    <td><strong><?= htmlspecialchars($b['building_name']) ?></strong></td>
                                    <td>
                                        <?= htmlspecialchars($b['address']) ?>
                                        <?php if ($b['district_name']): ?>
                                            <br><small class="text-muted"><?= htmlspecialchars($b['district_name']) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center"><span class="badge bg-secondary"><?= (int)$b['room_count'] ?></span></td>
                                    <td class="text-center"><span class="badge bg-success"><?= (int)$b['vacant_count'] ?></span></td>
                                    <td class="text-center"><span class="badge bg-warning"><?= (int)$b['occupied_count'] ?></span></td>
                                    <td class="text-center">
                                        <?php if ($b['building_status'] === 'ACTIVE'): ?>
                                            <span class="badge bg-success">Hoạt động</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Ẩn</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <div class="d-flex gap-1 justify-content-center">
                                            <!-- Xem -->
                                            <a href="<?= ADMIN_BASE_PATH ?>/modules/landlord/buildings/view.php?id=<?= $b['building_id'] ?>" 
                                               class="btn btn-outline-info btn-sm" title="Xem" style="width: 32px;">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            <!-- Xem phòng -->
                                            <a href="<?= ADMIN_BASE_PATH ?>/modules/landlord/rooms/index.php?building_id=<?= $b['building_id'] ?>" 
                                               class="btn btn-outline-primary btn-sm" title="Phòng" style="width: 32px;">
                                                <i class="bi bi-door-open"></i>
                                            </a>
                                            <!-- Sửa -->
                                            <a href="<?= ADMIN_BASE_PATH ?>/modules/landlord/buildings/edit.php?id=<?= $b['building_id'] ?>" 
                                               class="btn btn-outline-warning btn-sm" title="Sửa" style="width: 32px;">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <!-- Ẩn/Hiện -->
                                            <form method="post" class="d-inline">
                                                <input type="hidden" name="building_id" value="<?= $b['building_id'] ?>">
                                                <input type="hidden" name="action" value="toggle">
                                                <?php if ($b['building_status'] === 'HIDDEN'): ?>
                                                    <button type="submit" class="btn btn-outline-success btn-sm" title="Hiển thị" style="width: 32px;">
                                                        <i class="bi bi-eye"></i>
                                                    </button>
                                                <?php else: ?>
                                                    <button type="submit" class="btn btn-outline-secondary btn-sm" title="Ẩn" style="width: 32px;"
                                                            onclick="return confirm('Ẩn dãy trọ này? Các phòng cũng sẽ không hiển thị.')">
                                                        <i class="bi bi-eye-slash"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </form>
                                            <!-- Xóa -->
                                            <form method="post" class="d-inline" onsubmit="return confirm('Xóa dãy trọ này?');">
                                                <input type="hidden" name="building_id" value="<?= $b['building_id'] ?>">
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
                    <i class="bi bi-building fs-1 text-muted d-block mb-3"></i>
                    <?php
                    $emptyMessages = [
                        'all' => 'Bạn chưa có dãy trọ nào',
                        'active' => 'Không có dãy trọ đang hoạt động',
                        'hidden' => 'Không có dãy trọ đang ẩn'
                    ];
                    ?>
                    <p class="text-muted mb-3"><?= $emptyMessages[$filter] ?? 'Không có dữ liệu' ?></p>
                    <?php if ($filter !== 'all'): ?>
                        <a href="?" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left me-1"></i>Xem tất cả
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/../../../includes/footer.php'; ?>
