<?php
/**
 * Chi tiết người thuê - Chủ trọ
 */
require_once __DIR__ . '/../../includes/auth.php';
require_landlord_login();

$userId = (int)($_SESSION['user_id'] ?? 0);
$tenantId = (int)($_GET['id'] ?? 0);

if ($tenantId <= 0) {
    header('Location: index.php');
    exit;
}

// Lấy thông tin tenant
$tenant = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT t.*, u.email as user_email, u.username
    FROM tenants t
    LEFT JOIN users u ON u.user_id = t.user_id
    WHERE t.tenant_id = $tenantId
"));

if (!$tenant) {
    header('Location: index.php?error=not_found');
    exit;
}

// Kiểm tra tenant này có liên quan đến chủ trọ không (qua contract hoặc booking)
$contractCheck = mysqli_query($conn, "
    SELECT 1 as found FROM contracts c
    INNER JOIN rooms r ON r.room_id = c.room_id
    INNER JOIN buildings b ON b.building_id = r.building_id
    WHERE c.tenant_id = $tenantId AND b.owner_id = $userId
    LIMIT 1
");
$hasRelation = $contractCheck ? mysqli_fetch_assoc($contractCheck) : null;

if (!$hasRelation) {
    // Check qua bookings
    $bookingCheck = mysqli_query($conn, "
        SELECT 1 as found FROM bookings bk
        INNER JOIN rooms r ON r.room_id = bk.room_id  
        INNER JOIN buildings b ON b.building_id = r.building_id
        WHERE bk.tenant_id = $tenantId AND b.owner_id = $userId
        LIMIT 1
    ");
    $hasRelation = $bookingCheck ? mysqli_fetch_assoc($bookingCheck) : null;
}

if (!$hasRelation) {
    header('Location: index.php?error=no_permission');
    exit;
}

// Lấy lịch sử hợp đồng (join qua bookings vì contracts không có tenant_id)
$contracts = mysqli_query($conn, "
    SELECT DISTINCT c.*, r.room_code, bl.building_name
    FROM contracts c
    INNER JOIN rooms r ON r.room_id = c.room_id
    INNER JOIN buildings bl ON bl.building_id = r.building_id
    INNER JOIN bookings bk ON bk.contract_id = c.contract_id
    WHERE bk.tenant_id = $tenantId AND bl.owner_id = $userId
    ORDER BY c.created_at DESC
");

// Lấy lịch sử booking
$bookings = mysqli_query($conn, "
    SELECT bk.*, r.room_code, bl.building_name
    FROM bookings bk
    JOIN rooms r ON r.room_id = bk.room_id
    JOIN buildings bl ON bl.building_id = r.building_id
    WHERE bk.tenant_id = $tenantId AND bl.owner_id = $userId
    ORDER BY bk.created_at DESC
");

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="pagetitle">
    <h1><i class="bi bi-person me-2"></i>Chi tiết người thuê</h1>
    <nav>
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= ADMIN_BASE_PATH ?>/modules/landlord/dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="index.php">Người thuê</a></li>
            <li class="breadcrumb-item active">Chi tiết</li>
        </ol>
    </nav>
</div>

<section class="section">
    <div class="row g-4">
        <!-- Thông tin cá nhân -->
        <div class="col-lg-4">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="bi bi-person-circle me-2"></i>Thông tin cá nhân</h5>
                </div>
                <div class="card-body">
                    <div class="text-center mb-4">
                        <div class="bg-light rounded-circle d-inline-flex align-items-center justify-content-center" style="width: 80px; height: 80px;">
                            <i class="bi bi-person-fill text-primary" style="font-size: 40px;"></i>
                        </div>
                        <h4 class="mt-3 mb-0"><?= htmlspecialchars($tenant['full_name'] ?? '-') ?></h4>
                        <span class="badge bg-success">Người thuê</span>
                    </div>
                    
                    <table class="table table-borderless">
                        <tr>
                            <td class="text-muted"><i class="bi bi-telephone me-2"></i>SĐT:</td>
                            <td class="fw-bold"><?= htmlspecialchars($tenant['phone'] ?? '-') ?></td>
                        </tr>
                        <tr>
                            <td class="text-muted"><i class="bi bi-envelope me-2"></i>Email:</td>
                            <td><?= htmlspecialchars($tenant['email'] ?? $tenant['user_email'] ?? '-') ?></td>
                        </tr>
                        <tr>
                            <td class="text-muted"><i class="bi bi-credit-card me-2"></i>CCCD:</td>
                            <td><?= htmlspecialchars($tenant['id_number'] ?? '-') ?></td>
                        </tr>
                        <tr>
                            <td class="text-muted"><i class="bi bi-geo-alt me-2"></i>Địa chỉ:</td>
                            <td><?= htmlspecialchars($tenant['address'] ?? '-') ?></td>
                        </tr>
                        <tr>
                            <td class="text-muted"><i class="bi bi-calendar me-2"></i>Ngày sinh:</td>
                            <td><?= !empty($tenant['birthday']) ? date('d/m/Y', strtotime($tenant['birthday'])) : '-' ?></td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- Lịch sử hợp đồng -->
        <div class="col-lg-8">
            <div class="card shadow-sm mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-file-earmark-text me-2"></i>Lịch sử hợp đồng</h5>
                </div>
                <div class="card-body p-0">
                    <?php if ($contracts && mysqli_num_rows($contracts) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Mã HĐ</th>
                                        <th>Phòng</th>
                                        <th>Thời gian</th>
                                        <th>Trạng thái</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($c = mysqli_fetch_assoc($contracts)): ?>
                                        <tr>
                                            <td><strong><?= htmlspecialchars($c['contract_code'] ?? '#'.$c['contract_id']) ?></strong></td>
                                            <td>
                                                <?= htmlspecialchars($c['room_code']) ?>
                                                <br><small class="text-muted"><?= htmlspecialchars($c['building_name']) ?></small>
                                            </td>
                                            <td>
                                                <?= date('d/m/Y', strtotime($c['start_date'])) ?>
                                                <br><small class="text-muted">→ <?= $c['end_date'] ? date('d/m/Y', strtotime($c['end_date'])) : 'Không xác định' ?></small>
                                            </td>
                                            <td>
                                                <?php if ($c['contract_status'] === 'ACTIVE'): ?>
                                                    <span class="badge bg-success">Đang hiệu lực</span>
                                                <?php elseif ($c['contract_status'] === 'ENDED'): ?>
                                                    <span class="badge bg-secondary">Đã kết thúc</span>
                                                <?php else: ?>
                                                    <span class="badge bg-warning"><?= $c['contract_status'] ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <a href="<?= ADMIN_BASE_PATH ?>/modules/hopdong_owner/view.php?id=<?= $c['contract_id'] ?>" class="btn btn-sm btn-outline-primary">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4 text-muted">
                            <i class="bi bi-inbox fs-3 d-block mb-2"></i>Chưa có hợp đồng nào
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Lịch sử đặt phòng -->
            <div class="card shadow-sm">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-calendar-check me-2"></i>Lịch sử đặt phòng</h5>
                </div>
                <div class="card-body p-0">
                    <?php if ($bookings && mysqli_num_rows($bookings) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Mã đặt</th>
                                        <th>Phòng</th>
                                        <th>Nhận - Trả</th>
                                        <th>Trạng thái</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $statusBadges = [
                                        'PENDING' => '<span class="badge bg-warning text-dark">Chờ duyệt</span>',
                                        'CONFIRMED' => '<span class="badge bg-info">Chờ thanh toán</span>',
                                        'DEPOSIT_PAID' => '<span class="badge bg-success">Đang thuê</span>',
                                        'CHECKED_IN' => '<span class="badge bg-primary">Đang ở</span>',
                                        'CHECKED_OUT' => '<span class="badge bg-secondary">Đã trả</span>',
                                        'CANCELLED' => '<span class="badge bg-danger">Đã hủy</span>',
                                    ];
                                    while ($bk = mysqli_fetch_assoc($bookings)): ?>
                                        <tr>
                                            <td><small><?= htmlspecialchars(substr($bk['booking_code'] ?? '', -8)) ?></small></td>
                                            <td>
                                                <?= htmlspecialchars($bk['room_code']) ?>
                                                <br><small class="text-muted"><?= htmlspecialchars($bk['building_name']) ?></small>
                                            </td>
                                            <td>
                                                <?= date('d/m', strtotime($bk['check_in'])) ?>
                                                <?php if ($bk['check_out']): ?>
                                                    → <?= date('d/m/Y', strtotime($bk['check_out'])) ?>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= $statusBadges[$bk['status']] ?? $bk['status'] ?></td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4 text-muted">
                            <i class="bi bi-inbox fs-3 d-block mb-2"></i>Chưa có lịch sử đặt phòng
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <div class="mt-4">
        <a href="index.php" class="btn btn-secondary"><i class="bi bi-arrow-left me-1"></i>Quay lại</a>
    </div>
</section>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
