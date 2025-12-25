<?php
// admin/modules/sinhvien/detail.php - Xem chi tiết sinh viên

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';

$role = $_SESSION['role_name'] ?? '';
if ($role !== 'ADMIN') {
    header('Location: /quanlyphongtro/admin/index.php?error=no_permission');
    exit;
}

$roleRs = mysqli_query($conn, "SELECT role_id FROM roles WHERE role_name='STUDENT' LIMIT 1");
$studentRoleId = (int)(mysqli_fetch_assoc($roleRs)['role_id'] ?? 0);
if ($studentRoleId <= 0) { die("Thiếu role STUDENT."); }

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { header('Location: index.php'); exit; }

// Get student info - simple query from users only
$res = mysqli_query($conn, "
    SELECT u.user_id, u.full_name, u.username, u.email, u.phone, u.is_active, u.created_at, u.last_login
    FROM users u
    WHERE u.user_id = $id AND u.role_id = $studentRoleId
    LIMIT 1
");

if (!$res || mysqli_num_rows($res) === 0) { 
    header('Location: index.php?error=not_found'); 
    exit; 
}
$s = mysqli_fetch_assoc($res);

// Try to get tenant info separately (if exists)
$tenantInfo = null;
$tenantRs = mysqli_query($conn, "SELECT * FROM tenants WHERE user_id = $id LIMIT 1");
if ($tenantRs && mysqli_num_rows($tenantRs) > 0) {
    $tenantInfo = mysqli_fetch_assoc($tenantRs);
}

// Get booking history
$bookings = [];
$rsBookings = mysqli_query($conn, "
    SELECT b.*, r.room_code, bld.building_name
    FROM bookings b
    LEFT JOIN rooms r ON r.room_id = b.room_id
    LEFT JOIN buildings bld ON bld.building_id = r.building_id
    LEFT JOIN tenants t ON t.tenant_id = b.tenant_id
    WHERE t.user_id = $id
    ORDER BY b.created_at DESC
    LIMIT 10
");
if ($rsBookings) {
    while ($bk = mysqli_fetch_assoc($rsBookings)) {
        $bookings[] = $bk;
    }
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="pagetitle">
    <h1><i class="bi bi-person-badge me-2"></i>Chi tiết Sinh viên</h1>
    <nav>
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= ADMIN_BASE_PATH ?>/index.php">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="index.php">Sinh viên</a></li>
            <li class="breadcrumb-item active">Chi tiết</li>
        </ol>
    </nav>
</div>

<!-- Actions -->
<div class="mb-3">
    <a href="edit.php?id=<?= $id ?>" class="btn btn-primary btn-sm">
        <i class="bi bi-pencil me-1"></i>Chỉnh sửa thông tin
    </a>
    <?php if ((int)$s['is_active'] === 1): ?>
        <a href="toggle.php?id=<?= $id ?>&from=detail" class="btn btn-warning btn-sm" onclick="return confirm('Khóa tài khoản này?')">
            <i class="bi bi-lock me-1"></i>Khóa tài khoản
        </a>
    <?php else: ?>
        <a href="toggle.php?id=<?= $id ?>&from=detail" class="btn btn-success btn-sm" onclick="return confirm('Mở khóa tài khoản này?')">
            <i class="bi bi-unlock me-1"></i>Mở khóa
        </a>
    <?php endif; ?>

    <a href="index.php" class="btn btn-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i>Quay lại danh sách
    </a>
</div>


<section class="section">
    <div class="row">
        <!-- Student Info -->
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-person me-2"></i>Thông tin cá nhân</h5>
                    <?php if ((int)$s['is_active'] === 1): ?>
                        <span class="badge bg-success">Hoạt động</span>
                    <?php else: ?>
                        <span class="badge bg-secondary">Đã khóa</span>
                    <?php endif; ?>
                </div>
                <div class="card-body pt-4">
                    <table class="table table-borderless">
                        <tr>
                            <th width="35%" class="text-muted">Họ tên:</th>
                            <td><strong><?= htmlspecialchars($s['full_name'] ?? '') ?></strong></td>
                        </tr>
                        <tr>
                            <th class="text-muted">Username:</th>
                            <td><span class="badge bg-light text-dark"><?= htmlspecialchars($s['username'] ?? '') ?></span></td>
                        </tr>
                        <tr>
                            <th class="text-muted">Email:</th>
                            <td>
                                <?php if ($s['email']): ?>
                                    <a href="mailto:<?= htmlspecialchars($s['email']) ?>"><?= htmlspecialchars($s['email']) ?></a>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th class="text-muted">Số điện thoại:</th>
                            <td>
                                <?php if ($s['phone']): ?>
                                    <a href="tel:<?= htmlspecialchars($s['phone']) ?>"><?= htmlspecialchars($s['phone']) ?></a>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php if (isset($s['id_number']) && $s['id_number']): ?>
                        <tr>
                            <th class="text-muted">CCCD:</th>
                            <td><?= htmlspecialchars($s['id_number']) ?></td>
                        </tr>
                        <?php endif; ?>
                        <tr>
                            <th class="text-muted">Ngày tạo TK:</th>
                            <td><?= date('d/m/Y H:i', strtotime($s['created_at'])) ?></td>
                        </tr>
                        <tr>
                            <th class="text-muted">Đăng nhập gần nhất:</th>
                            <td>
                                <?php if ($s['last_login']): ?>
                                    <?= date('d/m/Y H:i', strtotime($s['last_login'])) ?>
                                <?php else: ?>
                                    <span class="text-muted">Chưa đăng nhập</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- Booking History -->
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-calendar-check me-2"></i>Lịch sử đặt phòng</h5>
                </div>
                <div class="card-body">
                    <?php if (count($bookings) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover table-sm">
                                <thead>
                                    <tr>
                                        <th>Mã</th>
                                        <th>Phòng</th>
                                        <th>Check-in</th>
                                        <th>Trạng thái</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($bookings as $bk): ?>
                                        <tr>
                                            <td><small><?= htmlspecialchars($bk['booking_code'] ?? '') ?></small></td>
                                            <td>
                                                <?= htmlspecialchars($bk['room_code'] ?? '-') ?>
                                                <?php if ($bk['building_name']): ?>
                                                    <br><small class="text-muted"><?= htmlspecialchars($bk['building_name']) ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td><small><?= date('d/m/Y', strtotime($bk['check_in'])) ?></small></td>
                                            <td>
                                                <?php
                                                $statusColors = [
                                                    'PENDING' => 'warning',
                                                    'CONFIRMED' => 'info',
                                                    'DEPOSIT_PAID' => 'primary',
                                                    'CHECKED_IN' => 'success',
                                                    'CHECKED_OUT' => 'secondary',
                                                    'CANCELLED' => 'danger'
                                                ];
                                                $statusLabels = [
                                                    'PENDING' => 'Chờ duyệt',
                                                    'CONFIRMED' => 'Đã duyệt',
                                                    'DEPOSIT_PAID' => 'Đã thanh toán',
                                                    'CHECKED_IN' => 'Đang thuê',
                                                    'CHECKED_OUT' => 'Đã trả phòng',
                                                    'CANCELLED' => 'Đã hủy'
                                                ];

                                                $st = $bk['status'] ?? 'PENDING';
                                                ?>
                                                <span class="badge bg-<?= $statusColors[$st] ?? 'secondary' ?>">
                                                    <?= $statusLabels[$st] ?? $st ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center text-muted py-4">
                            <i class="bi bi-calendar-x fs-1"></i>
                            <p class="mt-2">Chưa có lịch sử đặt phòng</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
