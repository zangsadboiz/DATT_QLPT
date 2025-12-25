<?php
/**
 * Module Dãy trọ - Xem chi tiết
 */
require_once __DIR__ . '/../../../includes/auth.php';
require_landlord_login();

$userId = (int)($_SESSION['user_id'] ?? 0);
$buildingId = (int)($_GET['id'] ?? 0);

if ($buildingId <= 0) {
    header('Location: ' . ADMIN_BASE_PATH . '/modules/landlord/buildings/index.php');
    exit;
}

$building = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT b.*, d.district_name
    FROM buildings b
    LEFT JOIN districts d ON d.district_id = b.district_id
    WHERE b.building_id = $buildingId AND b.owner_id = $userId
"));

if (!$building) {
    $_SESSION['alert'] = ['type' => 'danger', 'message' => 'Không tìm thấy dãy trọ!'];
    header('Location: ' . ADMIN_BASE_PATH . '/modules/landlord/buildings/index.php');
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
$roomStats = [
    'total' => count($rooms),
    'vacant' => 0,
    'occupied' => 0,
    'maintenance' => 0
];
foreach ($rooms as $r) {
    if ($r['room_status'] === 'VACANT') $roomStats['vacant']++;
    elseif ($r['room_status'] === 'OCCUPIED') $roomStats['occupied']++;
    else $roomStats['maintenance']++;
}

require_once __DIR__ . '/../../../includes/header.php';
?>

<div class="pagetitle">
    <h1><i class="bi bi-building me-2"></i><?= htmlspecialchars($building['building_name']) ?></h1>
    <nav>
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= ADMIN_BASE_PATH ?>/modules/landlord/dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="<?= ADMIN_BASE_PATH ?>/modules/landlord/buildings/index.php">Dãy trọ</a></li>
            <li class="breadcrumb-item active"><?= htmlspecialchars($building['building_name']) ?></li>
        </ol>
    </nav>
</div>

<section class="section">
    <div class="row">
        <!-- Cột trái: Ảnh -->
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
                                             class="d-block w-100 rounded" style="height: 300px; object-fit: cover;">
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <?php if (count($images) > 1): ?>
                                <button class="carousel-control-prev" type="button" data-bs-target="#buildingCarousel" data-bs-slide="prev">
                                    <span class="carousel-control-prev-icon"></span>
                                </button>
                                <button class="carousel-control-next" type="button" data-bs-target="#buildingCarousel" data-bs-slide="next">
                                    <span class="carousel-control-next-icon"></span>
                                </button>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5 bg-light rounded">
                            <i class="bi bi-building" style="font-size: 48px; color: #ddd;"></i>
                            <p class="text-muted mt-2">Chưa có ảnh</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Thống kê phòng -->
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
                            <small>Đang thuê</small>
                        </div>
                        <div class="col-3">
                            <h4 class="text-warning"><?= $roomStats['maintenance'] ?></h4>
                            <small>Bảo trì</small>
                        </div>
                    </div>
                    <hr>
                    <a href="<?= ADMIN_BASE_PATH ?>/modules/landlord/rooms/index.php?building_id=<?= $buildingId ?>" class="btn btn-primary w-100">
                        <i class="bi bi-door-open me-1"></i>Xem danh sách phòng
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Cột phải: Thông tin -->
        <div class="col-lg-7">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Thông tin dãy trọ</h5>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="text-muted small">Mã dãy trọ</label>
                            <p class="mb-0 fw-bold"><code><?= htmlspecialchars($building['building_code']) ?></code></p>
                        </div>
                        <div class="col-md-6">
                            <label class="text-muted small">Tên dãy trọ</label>
                            <p class="mb-0 fw-bold"><?= htmlspecialchars($building['building_name']) ?></p>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-8">
                            <label class="text-muted small">Địa chỉ</label>
                            <p class="mb-0"><?= htmlspecialchars($building['address']) ?></p>
                            <?php if ($building['district_name']): ?>
                                <small class="text-muted"><?= htmlspecialchars($building['district_name']) ?></small>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-4">
                            <label class="text-muted small">Số tầng</label>
                            <p class="mb-0 fw-bold"><?= (int)$building['total_floors'] ?> tầng</p>
                        </div>
                    </div>
                    
                    <?php if ($building['electricity_price'] || $building['water_price']): ?>
                    <hr>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="text-muted small">Giá điện mặc định</label>
                            <p class="mb-0"><?= $building['electricity_price'] ? number_format((float)$building['electricity_price']) . 'đ/kWh' : '-' ?></p>
                        </div>
                        <div class="col-md-6">
                            <label class="text-muted small">Giá nước mặc định</label>
                            <p class="mb-0"><?= $building['water_price'] ? number_format((float)$building['water_price']) . 'đ/m³' : '-' ?></p>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($building['description']): ?>
                    <hr>
                    <div class="mb-3">
                        <label class="text-muted small">Mô tả</label>
                        <p class="mb-0"><?= nl2br(htmlspecialchars($building['description'])) ?></p>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($building['rules']): ?>
                    <hr>
                    <div class="mb-3">
                        <label class="text-muted small">Nội quy chung</label>
                        <div class="alert alert-warning mb-0">
                            <?= nl2br(htmlspecialchars($building['rules'])) ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Danh sách phòng -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Phòng (<?= count($rooms) ?>)</h5>
                    <a href="<?= ADMIN_BASE_PATH ?>/modules/landlord/rooms/add.php?building_id=<?= $buildingId ?>" class="btn btn-sm btn-primary">
                        <i class="bi bi-plus"></i> Thêm phòng
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
                                    <?php foreach ($rooms as $r): ?>
                                        <tr>
                                            <td>
                                                <a href="<?= ADMIN_BASE_PATH ?>/modules/landlord/rooms/view.php?id=<?= $r['room_id'] ?>">
                                                    <strong><?= htmlspecialchars($r['room_code']) ?></strong>
                                                </a>
                                            </td>
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
                        <div class="text-center py-4">
                            <p class="text-muted mb-0">Chưa có phòng nào</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Actions -->
            <div class="d-flex gap-2">
                <a href="<?= ADMIN_BASE_PATH ?>/modules/landlord/buildings/index.php" class="btn btn-secondary">
                    <i class="bi bi-arrow-left me-1"></i>Quay lại
                </a>
                <a href="<?= ADMIN_BASE_PATH ?>/modules/landlord/buildings/edit.php?id=<?= $buildingId ?>" class="btn btn-primary">
                    <i class="bi bi-pencil me-1"></i>Chỉnh sửa
                </a>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/../../../includes/footer.php'; ?>
