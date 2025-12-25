<?php
/**
 * Admin - Xem chi tiết Dãy trọ (Read-only)
 */
require_once __DIR__ . '/../../includes/auth.php';
require_auth();

$buildingId = (int)($_GET['id'] ?? 0);

if ($buildingId <= 0) {
    header('Location: ' . ADMIN_BASE_PATH . '/modules/admin_buildings/index.php');
    exit;
}

$rsBuilding = mysqli_query($conn, "
    SELECT b.*, u.fullname as owner_name, u.phone as owner_phone, u.email as owner_email, d.district_name
    FROM buildings b
    LEFT JOIN users u ON u.user_id = b.owner_id
    LEFT JOIN districts d ON d.district_id = b.district_id
    WHERE b.building_id = $buildingId
");
$building = $rsBuilding ? mysqli_fetch_assoc($rsBuilding) : null;

if (!$building) {
    $_SESSION['alert'] = ['type' => 'danger', 'message' => 'Không tìm thấy dãy trọ!'];
    header('Location: ' . ADMIN_BASE_PATH . '/modules/admin_buildings/index.php');
    exit;
}

// Get images
$images = [];
$rsImg = mysqli_query($conn, "SELECT * FROM building_images WHERE building_id = $buildingId ORDER BY is_primary DESC, sort_order");
while ($rsImg && ($img = mysqli_fetch_assoc($rsImg))) $images[] = $img;

// Get rooms
$rooms = [];
$rsRooms = mysqli_query($conn, "SELECT * FROM rooms WHERE building_id = $buildingId AND deleted_at IS NULL ORDER BY floor, room_code");
while ($rsRooms && ($r = mysqli_fetch_assoc($rsRooms))) $rooms[] = $r;

// Stats
$roomStats = ['total' => count($rooms), 'vacant' => 0, 'occupied' => 0, 'maintenance' => 0];
foreach ($rooms as $r) {
    if ($r['room_status'] === 'VACANT') $roomStats['vacant']++;
    elseif ($r['room_status'] === 'OCCUPIED') $roomStats['occupied']++;
    else $roomStats['maintenance']++;
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="pagetitle">
    <h1><i class="bi bi-building me-2"></i><?= htmlspecialchars($building['building_name']) ?></h1>
    <nav>
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= ADMIN_BASE_PATH ?>/index.php">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="<?= ADMIN_BASE_PATH ?>/modules/admin_buildings/index.php">Dãy trọ</a></li>
            <li class="breadcrumb-item active"><?= htmlspecialchars($building['building_name']) ?></li>
        </ol>
    </nav>
</div>

<div class="alert alert-info">
    <i class="bi bi-info-circle me-2"></i>Chế độ xem Admin (read-only). Chủ trọ quản lý dữ liệu này.
</div>

<section class="section">
    <div class="row">
        <!-- Cột trái -->
        <div class="col-lg-5">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Hình ảnh</h5>
                    <?php if ($building['building_status'] === 'ACTIVE'): ?>
                        <span class="badge bg-success">Hoạt động</span>
                    <?php else: ?>
                        <span class="badge bg-secondary">Đang ẩn</span>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <?php if (count($images) > 0): ?>
                        <div id="buildingCarousel" class="carousel slide" data-bs-ride="carousel">
                            <div class="carousel-inner">
                                <?php foreach ($images as $i => $img): ?>
                                    <div class="carousel-item <?= $i === 0 ? 'active' : '' ?>">
                                        <img src="/quanlyphongtro/uploads/buildings/<?= htmlspecialchars($img['image_path']) ?>" 
                                             class="d-block w-100 rounded" style="height: 250px; object-fit: cover;">
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4 bg-light rounded">
                            <i class="bi bi-building" style="font-size: 48px; color: #ddd;"></i>
                            <p class="text-muted mt-2 mb-0">Chưa có ảnh</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Thống kê -->
            <div class="card">
                <div class="card-header"><h5 class="mb-0">Thống kê phòng</h5></div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-3">
                            <h4 class="text-primary"><?= $roomStats['total'] ?></h4>
                            <small>Tổng</small>
                        </div>
                        <div class="col-3">
                            <h4 class="text-success"><?= $roomStats['vacant'] ?></h4>
                            <small>Trống</small>
                        </div>
                        <div class="col-3">
                            <h4 class="text-info"><?= $roomStats['occupied'] ?></h4>
                            <small>Thuê</small>
                        </div>
                        <div class="col-3">
                            <h4 class="text-warning"><?= $roomStats['maintenance'] ?></h4>
                            <small>Bảo trì</small>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Chủ trọ -->
            <div class="card">
                <div class="card-header"><h5 class="mb-0">Thông tin chủ trọ</h5></div>
                <div class="card-body">
                    <p><strong>Họ tên:</strong> <?= htmlspecialchars($building['owner_name'] ?: 'N/A') ?></p>
                    <p><strong>SĐT:</strong> <?= htmlspecialchars($building['owner_phone'] ?: 'N/A') ?></p>
                    <p class="mb-0"><strong>Email:</strong> <?= htmlspecialchars($building['owner_email'] ?: 'N/A') ?></p>
                </div>
            </div>
        </div>
        
        <!-- Cột phải -->
        <div class="col-lg-7">
            <div class="card">
                <div class="card-header"><h5 class="mb-0">Thông tin dãy trọ</h5></div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="text-muted small">Mã</label>
                            <p class="mb-0"><code><?= htmlspecialchars($building['building_code']) ?></code></p>
                        </div>
                        <div class="col-md-6">
                            <label class="text-muted small">Số tầng</label>
                            <p class="mb-0"><?= (int)$building['total_floors'] ?> tầng</p>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="text-muted small">Địa chỉ</label>
                        <p class="mb-0"><?= htmlspecialchars($building['address']) ?><?= $building['district_name'] ? ', ' . htmlspecialchars($building['district_name']) : '' ?></p>
                    </div>
                    <?php if ($building['electricity_price'] || $building['water_price']): ?>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="text-muted small">Giá điện</label>
                            <p class="mb-0"><?= $building['electricity_price'] ? number_format((float)$building['electricity_price']) . 'đ/kWh' : '-' ?></p>
                        </div>
                        <div class="col-md-6">
                            <label class="text-muted small">Giá nước</label>
                            <p class="mb-0"><?= $building['water_price'] ? number_format((float)$building['water_price']) . 'đ/m³' : '-' ?></p>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php if ($building['description']): ?>
                        <label class="text-muted small">Mô tả</label>
                        <p class="mb-0"><?= nl2br(htmlspecialchars($building['description'])) ?></p>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Danh sách phòng -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Phòng (<?= count($rooms) ?>)</h5>
                    <a href="<?= ADMIN_BASE_PATH ?>/modules/admin_rooms/index.php?building_id=<?= $buildingId ?>" class="btn btn-sm btn-outline-primary">
                        Xem tất cả
                    </a>
                </div>
                <div class="card-body p-0">
                    <?php if (count($rooms) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-sm table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Phòng</th>
                                        <th>Tầng</th>
                                        <th>Giá thuê</th>
                                        <th>Trạng thái</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach (array_slice($rooms, 0, 10) as $r): ?>
                                        <tr>
                                            <td><strong><?= htmlspecialchars($r['room_code']) ?></strong></td>
                                            <td>Tầng <?= (int)$r['floor'] ?></td>
                                            <td><?= number_format((float)$r['base_rent']) ?>đ</td>
                                            <td>
                                                <?php if ($r['room_status'] === 'VACANT'): ?>
                                                    <span class="badge bg-success">Trống</span>
                                                <?php elseif ($r['room_status'] === 'OCCUPIED'): ?>
                                                    <span class="badge bg-primary">Đang thuê</span>
                                                <?php else: ?>
                                                    <span class="badge bg-warning">Bảo trì</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4 text-muted">Chưa có phòng</div>
                    <?php endif; ?>
                </div>
            </div>
            
            <a href="<?= ADMIN_BASE_PATH ?>/modules/admin_buildings/index.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left me-1"></i>Quay lại
            </a>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
