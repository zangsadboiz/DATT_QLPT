<?php
/**
 * Admin - Xem tất cả Dãy trọ (Read-only)
 */
require_once __DIR__ . '/../../includes/auth.php';
require_auth();

// Lọc
$search = trim($_GET['search'] ?? '');
$ownerId = (int)($_GET['owner_id'] ?? 0);
$regionFilter = isset($_GET['region']) && $_GET['region'] !== '' ? (int)$_GET['region'] : 0;
$provinceFilter = isset($_GET['province']) && $_GET['province'] !== '' ? (int)$_GET['province'] : 0;

// Lấy thông tin owner nếu có
$ownerName = '';
if ($ownerId > 0) {
    $rsOwner = mysqli_query($conn, "SELECT full_name FROM users WHERE user_id = $ownerId");
    if ($rsOwner && ($ownerRow = mysqli_fetch_assoc($rsOwner))) {
        $ownerName = $ownerRow['full_name'];
    }
}

// Lấy danh sách buildings - Query đơn giản
$buildings = [];
$where = [];
if ($ownerId > 0) $where[] = "b.owner_id = $ownerId";
if ($regionFilter > 0) $where[] = "p.region_id = $regionFilter";
if ($provinceFilter > 0) $where[] = "p.province_id = $provinceFilter";
$whereClause = count($where) > 0 ? "WHERE " . implode(" AND ", $where) : "";
$sql = "SELECT b.*, d.district_name, p.province_name, p.region_id 
        FROM buildings b 
        LEFT JOIN districts d ON d.district_id = b.district_id
        LEFT JOIN provinces p ON p.province_id = d.province_id
        $whereClause 
        ORDER BY b.created_at DESC";
$rs = mysqli_query($conn, $sql);
if ($rs) {
    while ($row = mysqli_fetch_assoc($rs)) {
        // Lấy thêm thông tin
        $bid = $row['building_id'];
        $rsRoom = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM rooms WHERE building_id = $bid AND deleted_at IS NULL");
        $row['room_count'] = $rsRoom ? (mysqli_fetch_assoc($rsRoom)['cnt'] ?? 0) : 0;
        
        $rsVacant = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM rooms WHERE building_id = $bid AND deleted_at IS NULL AND room_status = 'VACANT'");
        $row['vacant_count'] = $rsVacant ? (mysqli_fetch_assoc($rsVacant)['cnt'] ?? 0) : 0;
        
        $rsOcc = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM rooms WHERE building_id = $bid AND deleted_at IS NULL AND room_status = 'OCCUPIED'");
        $row['occupied_count'] = $rsOcc ? (mysqli_fetch_assoc($rsOcc)['cnt'] ?? 0) : 0;
        
        // Lấy ảnh chính
        $rsImg = mysqli_query($conn, "SELECT image_path FROM building_images WHERE building_id = $bid AND is_primary = 1 LIMIT 1");
        $row['primary_image'] = $rsImg ? (mysqli_fetch_assoc($rsImg)['image_path'] ?? null) : null;
        
        $rsOwner = mysqli_query($conn, "SELECT full_name, phone FROM users WHERE user_id = " . (int)$row['owner_id']);
        $owner = $rsOwner ? mysqli_fetch_assoc($rsOwner) : null;
        $row['owner_name'] = $owner['full_name'] ?? 'N/A';
        $row['owner_phone'] = $owner['phone'] ?? '';
        
        // Filter by search
        if ($search !== '') {
            $match = stripos($row['building_name'], $search) !== false 
                  || stripos($row['building_code'], $search) !== false 
                  || stripos($row['address'], $search) !== false
                  || stripos($row['owner_name'], $search) !== false;
            if (!$match) continue;
        }
        
        $buildings[] = $row;
    }
}

// Pagination
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10;
$total = count($buildings);
$totalPages = max(1, (int)ceil($total / $perPage));
if ($page > $totalPages) $page = $totalPages;
$offset = ($page - 1) * $perPage;
$buildingsPage = array_slice($buildings, $offset, $perPage);

// Lấy danh sách chủ trọ để filter
$owners = [];
$rsOwners = mysqli_query($conn, "SELECT DISTINCT u.user_id, u.full_name FROM users u JOIN buildings b ON b.owner_id = u.user_id ORDER BY u.full_name");
while ($rsOwners && ($o = mysqli_fetch_assoc($rsOwners))) $owners[] = $o;

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="pagetitle">
    <h1>
        <i class="bi bi-building me-2"></i>
        <?php if ($ownerName): ?>
            Dãy trọ của <?= htmlspecialchars($ownerName) ?>
        <?php else: ?>
            Quản lý Dãy trọ (Admin)
        <?php endif; ?>
    </h1>
    <nav>
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= ADMIN_BASE_PATH ?>/index.php">Dashboard</a></li>
            <?php if ($ownerName): ?>
                <li class="breadcrumb-item"><a href="<?= ADMIN_BASE_PATH ?>/modules/users/index.php">Chủ trọ</a></li>
            <?php endif; ?>
            <li class="breadcrumb-item active">Dãy trọ</li>
        </ol>
    </nav>
</div>

<section class="section">
    <?php 
    // Tính stats từ buildings đã lấy
    $activeCount = 0;
    $hiddenCount = 0;
    foreach ($buildings as $b) {
        if ($b['building_status'] === 'HIDDEN') $hiddenCount++;
        elseif ($b['building_status'] === 'ACTIVE') $activeCount++;
        else $activeCount++; // Other statuses count as active
    }
    ?>
    <!-- Thống kê dãy trọ -->
    <div class="row mb-3">
        <div class="col">
            <div class="card text-center">
                <div class="card-body py-3">
                    <h4 class="text-primary mb-0"><?= $total ?></h4>
                    <small>Tất cả</small>
                </div>
            </div>
        </div>
        <div class="col">
            <div class="card text-center">
                <div class="card-body py-3">
                    <h4 class="text-success mb-0"><?= $activeCount ?></h4>
                    <small>Hoạt động</small>
                </div>
            </div>
        </div>
        <div class="col">
            <div class="card text-center">
                <div class="card-body py-3">
                    <h4 class="text-secondary mb-0"><?= $hiddenCount ?></h4>
                    <small>Đang ẩn</small>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Tìm kiếm và Lọc -->
    <div class="card mb-3">
        <div class="card-body py-2">
            <form class="d-flex gap-2 align-items-center flex-wrap" method="get">
                <!-- Search -->
                <input type="text" name="search" class="form-control form-control-sm" style="max-width: 250px;"
                       placeholder="Tìm theo tên, mã, địa chỉ..." value="<?= htmlspecialchars($search) ?>">
                
                <div class="vr"></div>
                
                <!-- Region Filter -->
                <select name="region" class="form-select form-select-sm" style="max-width: 150px;" onchange="this.form.submit()">
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
                <select name="province" class="form-select form-select-sm" style="max-width: 200px;" onchange="this.form.submit()">
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
                
                <button type="submit" class="btn btn-primary btn-sm">
                    <i class="bi bi-search"></i> Tìm
                </button>
                
                <?php if ($search || $regionFilter || $provinceFilter): ?>
                    <a href="?" class="btn btn-secondary btn-sm">
                        <i class="bi bi-x"></i> Xóa
                    </a>
                <?php endif; ?>
            </form>
        </div>
    </div>
    
    <!-- Danh sách -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Danh sách Dãy trọ</h5>
            <span class="badge bg-secondary"><?= $total ?> dãy trọ</span>
        </div>
        <div class="card-body">
            <?php if (count($buildingsPage) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th style="width: 70px;">Ảnh</th>
                                <th>Dãy trọ</th>
                                <th>Chủ trọ</th>
                                <th>Địa chỉ</th>
                                <th class="text-center">Tầng</th>
                                <th class="text-center">Phòng</th>
                                <th class="text-center">Trống</th>
                                <th class="text-center">Đang thuê</th>
                                <th class="text-center">Trạng thái</th>
                                <th class="text-center">Hành động</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($buildingsPage as $b): ?>
                                <tr class="<?= $b['building_status'] === 'HIDDEN' ? 'table-secondary' : '' ?>">
                                    <td>
                                        <?php if (!empty($b['primary_image'])): ?>
                                            <img src="/quanlyphongtro/uploads/buildings/<?= htmlspecialchars($b['primary_image']) ?>" 
                                                 class="rounded" style="width: 60px; height: 45px; object-fit: cover;">
                                        <?php elseif (!empty($b['thumbnail'])): ?>
                                            <img src="/quanlyphongtro/uploads/buildings/<?= htmlspecialchars($b['thumbnail']) ?>" 
                                                 class="rounded" style="width: 60px; height: 45px; object-fit: cover;">
                                        <?php else: ?>
                                            <div class="bg-light rounded d-flex align-items-center justify-content-center" style="width: 60px; height: 45px;">
                                                <i class="bi bi-building text-muted"></i>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong><?= htmlspecialchars($b['building_name']) ?></strong>
                                        <br><code class="small"><?= htmlspecialchars($b['building_code']) ?></code>
                                    </td>
                                    <td>
                                        <?= htmlspecialchars($b['owner_name']) ?>
                                        <?php if ($b['owner_phone']): ?>
                                            <br><small class="text-muted"><?= htmlspecialchars($b['owner_phone']) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?= htmlspecialchars($b['address']) ?>
                                        <?php if ($b['district_name']): ?>
                                            <br><small class="text-muted"><?= htmlspecialchars($b['district_name']) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center"><?= (int)$b['total_floors'] ?></td>
                                    <td class="text-center"><span class="badge bg-secondary"><?= (int)$b['room_count'] ?></span></td>
                                    <td class="text-center"><span class="badge bg-success"><?= (int)$b['vacant_count'] ?></span></td>
                                    <td class="text-center"><span class="badge bg-primary"><?= (int)$b['occupied_count'] ?></span></td>
                                    <td class="text-center">
                                        <?php if ($b['building_status'] === 'HIDDEN'): ?>
                                            <span class="badge bg-secondary">Đang ẩn</span>
                                        <?php elseif ($b['building_status'] === 'ACTIVE'): ?>
                                            <span class="badge bg-success">Hoạt động</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning text-dark"><?= htmlspecialchars($b['building_status'] ?? 'N/A') ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <a href="<?= ADMIN_BASE_PATH ?>/modules/admin_rooms/index.php?building_id=<?= $b['building_id'] ?>" 
                                           class="btn btn-outline-primary btn-sm" title="Xem phòng">
                                            <i class="bi bi-door-open"></i> Phòng
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    
                    <?php if ($totalPages > 1): ?>
                    <nav class="mt-3">
                        <ul class="pagination pagination-sm justify-content-center mb-0">
                            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">«</a>
                            </li>
                            <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                            <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"><?= $i ?></a>
                            </li>
                            <?php endfor; ?>
                            <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                                <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">»</a>
                            </li>
                        </ul>
                    </nav>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-5">
                    <i class="bi bi-building fs-1 text-muted"></i>
                    <p class="text-muted mt-2">
                        <?php if ($search): ?>
                            Không tìm thấy dãy trọ phù hợp với "<?= htmlspecialchars($search) ?>"
                        <?php else: ?>
                            Không có dãy trọ nào trong hệ thống
                        <?php endif; ?>
                    </p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
