<?php
/**
 * Danh sách người thuê - Chủ trọ
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';

$role = $_SESSION['role_name'] ?? '';
$user_id = (int)($_SESSION['user_id'] ?? 0);

if ($role !== 'LANDLORD' || $user_id <= 0) {
    header('Location: /quanlyphongtro/admin/index.php?error=no_permission');
    exit;
}

// Filter
$qraw = trim($_GET['q'] ?? '');
$q = mysqli_real_escape_string($conn, $qraw);
$buildingFilter = (int)($_GET['building_id'] ?? 0);
$statusFilter = $_GET['status'] ?? 'active'; // active, all, ended

// Pagination
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 15;
$offset = ($page - 1) * $perPage;

// Get buildings for filter
$buildings = mysqli_query($conn, "SELECT building_id, building_name FROM buildings WHERE owner_id = $user_id ORDER BY building_name");

// Base query - Lấy người thuê có hợp đồng HOẶC booking DEPOSIT_PAID/CHECKED_IN
$where = "b.owner_id = $user_id";
if ($statusFilter === 'active') {
    $where .= " AND c.contract_status = 'ACTIVE'";
} elseif ($statusFilter === 'ended') {
    $where .= " AND c.contract_status = 'ENDED'";
}
if ($buildingFilter > 0) {
    $where .= " AND b.building_id = $buildingFilter";
}
if ($qraw !== '') {
    $where .= " AND (t.full_name LIKE '%$q%' OR t.phone LIKE '%$q%' OR t.email LIKE '%$q%' OR r.room_code LIKE '%$q%')";
}

// Count total (contracts only for now - will add booking count later if needed)
$countRs = mysqli_query($conn, "
    SELECT COUNT(DISTINCT ct.id) AS c
    FROM contract_tenants ct
    JOIN contracts c ON c.contract_id = ct.contract_id
    JOIN rooms r ON r.room_id = c.room_id
    JOIN buildings b ON b.building_id = r.building_id
    LEFT JOIN tenants t ON t.tenant_id = ct.tenant_id
    WHERE $where
");
$contractCount = (int)(mysqli_fetch_assoc($countRs)['c'] ?? 0);

// Count bookings (DEPOSIT_PAID/CHECKED_IN)
$bookingWhere = "b.owner_id = $user_id AND bk.status IN ('DEPOSIT_PAID', 'CHECKED_IN')";
if ($buildingFilter > 0) {
    $bookingWhere .= " AND b.building_id = $buildingFilter";
}
if ($qraw !== '') {
    $bookingWhere .= " AND (t.full_name LIKE '%$q%' OR t.phone LIKE '%$q%' OR t.email LIKE '%$q%' OR r.room_code LIKE '%$q%')";
}
$bookingCountRs = mysqli_query($conn, "
    SELECT COUNT(*) AS c
    FROM bookings bk
    JOIN rooms r ON r.room_id = bk.room_id
    JOIN buildings b ON b.building_id = r.building_id
    LEFT JOIN tenants t ON t.tenant_id = bk.tenant_id
    WHERE $bookingWhere AND NOT EXISTS (
        SELECT 1 FROM contracts c WHERE c.room_id = r.room_id AND c.contract_status = 'ACTIVE'
    )
");
$bookingCount = (int)(mysqli_fetch_assoc($bookingCountRs)['c'] ?? 0);

$total = $contractCount + $bookingCount;
$totalPages = max(1, (int)ceil($total / $perPage));

// Get list - Lấy từ contract_tenants (đã đủ data)
$list = mysqli_query($conn, "
    SELECT 
        'contract' as source_type,
        ct.id as source_id, ct.is_representative, ct.move_in_date, ct.move_out_date,
        t.tenant_id, t.full_name, t.phone, t.email,
        c.contract_id, c.contract_code, c.contract_status, c.start_date, c.end_date,
        NULL as booking_id, NULL as booking_status,
        r.room_id, r.room_code,
        b.building_name
    FROM contract_tenants ct
    JOIN contracts c ON c.contract_id = ct.contract_id
    JOIN rooms r ON r.room_id = c.room_id
    JOIN buildings b ON b.building_id = r.building_id
    LEFT JOIN tenants t ON t.tenant_id = ct.tenant_id
    WHERE $where
    ORDER BY ct.move_in_date DESC
    LIMIT $perPage OFFSET $offset
");

// Stats
$activeCount = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT COUNT(DISTINCT ct.id) AS c
    FROM contract_tenants ct
    JOIN contracts c ON c.contract_id = ct.contract_id
    JOIN rooms r ON r.room_id = c.room_id
    JOIN buildings b ON b.building_id = r.building_id
    WHERE b.owner_id = $user_id AND c.contract_status = 'ACTIVE'
"))['c'] ?? 0;

// Add booking count to active count
$activeBookingCount = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT COUNT(*) AS c
    FROM bookings bk
    JOIN rooms r ON r.room_id = bk.room_id
    JOIN buildings b ON b.building_id = r.building_id
    WHERE b.owner_id = $user_id AND bk.status IN ('DEPOSIT_PAID', 'CHECKED_IN')
      AND NOT EXISTS (
        SELECT 1 FROM contracts c WHERE c.room_id = r.room_id AND c.contract_status = 'ACTIVE'
    )
"))['c'] ?? 0;

$activeCount += $activeBookingCount;

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0"><i class="bi bi-people-fill me-2"></i>Người thuê</h1>
    <span class="badge bg-primary" style="font-size: 1rem; padding: 0.5rem 1rem;">
        <i class="bi bi-person-check me-1"></i><?= (int)$activeCount ?>
    </span>
</div>

<!-- Inline Filter -->
<form method="get" class="d-flex gap-2 mb-3 flex-wrap">
    <input name="q" class="form-control" placeholder="Tìm tên, SĐT, CCCD..." 
           value="<?= htmlspecialchars($qraw) ?>" style="max-width: 300px;">
    <select name="building_id" class="form-select" style="max-width: 200px;">
        <option value="0">Tất cả dãy trọ</option>
        <?php mysqli_data_seek($buildings, 0); while($b = mysqli_fetch_assoc($buildings)): ?>
            <option value="<?= (int)$b['building_id'] ?>" <?= $buildingFilter === (int)$b['building_id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($b['building_name']) ?>
            </option>
        <?php endwhile; ?>
    </select>
    <select name="status" class="form-select" style="max-width: 150px;">
        <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Đang thuê</option>
        <option value="ended" <?= $statusFilter === 'ended' ? 'selected' : '' ?>>Đã trả</option>
        <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>Tất cả</option>
    </select>
    <button class="btn btn-primary">
        <i class="bi bi-search"></i> Lọc
    </button>
</form>

<section class="section">
    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="px-3">Họ tên</th>
                            <th>SĐT</th>
                            <th>Phòng</th>
                            <th>Dãy trọ</th>
                            <th>Ngày vào</th>
                            <th>Trạng thái</th>
                            <th class="text-center">Hành động</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $has = false; while ($r = $list ? mysqli_fetch_assoc($list) : null): $has = true; ?>
                            <tr>
                                <td class="px-3">
                                    <strong><?= htmlspecialchars($r['full_name'] ?? '-') ?></strong>
                                    <?php if ($r['is_representative']): ?>
                                        <span class="badge bg-info ms-1" style="font-size: 0.7rem;">Đại diện</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="tel:<?= htmlspecialchars($r['phone'] ?? '') ?>" class="text-decoration-none">
                                        <?= htmlspecialchars($r['phone'] ?? '-') ?>
                                    </a>
                                </td>
                                <td>
                                    <a href="/quanlyphongtro/admin/modules/landlord/rooms/view.php?id=<?= (int)$r['room_id'] ?>" 
                                       class="text-decoration-none fw-bold">
                                        <?= htmlspecialchars($r['room_code'] ?? '-') ?>
                                    </a>
                                </td>
                                <td><small class="text-muted"><?= htmlspecialchars($r['building_name'] ?? '-') ?></small></td>
                                <td><small><?= $r['move_in_date'] ? date('d/m/Y', strtotime($r['move_in_date'])) : '-' ?></small></td>
                                <td>
                                    <?php if ($r['source_type'] === 'contract' && $r['contract_status'] === 'ACTIVE'): ?>
                                        <span class="badge bg-success">Đang thuê</span>
                                    <?php elseif ($r['source_type'] === 'booking' && $r['booking_status'] === 'DEPOSIT_PAID'): ?>
                                        <span class="badge bg-info">Đã TT - chờ nhận</span>
                                    <?php elseif ($r['source_type'] === 'booking' && $r['booking_status'] === 'CHECKED_IN'): ?>
                                        <span class="badge bg-success">Đang thuê</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Đã trả</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <a class="btn btn-sm btn-outline-primary" href="view.php?id=<?= (int)$r['tenant_id'] ?>" title="Xem">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <?php if ($r['source_type'] === 'contract'): ?>
                                    <a class="btn btn-sm btn-outline-success" 
                                       href="/quanlyphongtro/admin/modules/hopdong_owner/view.php?id=<?= (int)$r['contract_id'] ?>" 
                                       title="Xem hợp đồng">
                                        <i class="bi bi-file-text"></i>
                                    </a>
                                    <?php else: ?>
                                    <a class="btn btn-sm btn-outline-info" 
                                       href="/quanlyphongtro/admin/modules/yeucau_thue_owner/detail.php?id=<?= (int)$r['booking_id'] ?>"
                                       title="Xem booking">
                                        <i class="bi bi-calendar-check"></i>
                                    </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                        <?php if (!$has): ?>
                            <tr>
                                <td colspan="7" class="text-center text-muted py-5">
                                    <i class="bi bi-people fs-1 d-block mb-2 opacity-50"></i>
                                    <p class="mb-0">Chưa có người thuê</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <nav class="px-3 py-3 border-top">
                    <ul class="pagination pagination-sm mb-0 justify-content-center">
                        <?php for ($pg = 1; $pg <= $totalPages; $pg++): ?>
                            <li class="page-item <?= $pg === $page ? 'active' : '' ?>">
                                <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $pg])) ?>"><?= $pg ?></a>
                            </li>
                        <?php endfor; ?>
                    </ul>
                </nav>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
