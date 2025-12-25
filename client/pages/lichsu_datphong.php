<?php
// client/pages/lichsu_datphong.php - Lịch sử đặt phòng của sinh viên
$hotelier = '/quanlyphongtro/hotelier-1.0.0';

// Kiểm tra đăng nhập
if (!isset($_SESSION['user_id']) || ($_SESSION['role_name'] ?? '') !== 'STUDENT') {
    header('Location: /quanlyphongtro/client/index.php?page=login&type=student&redirect=lichsu_datphong');
    exit;
}

$userId = (int)$_SESSION['user_id'];

// Filter params
$filterStatus = $_GET['status'] ?? '';
$filterRentalType = $_GET['rental_type'] ?? '';

// Build query with filter
$whereClauses = ["t.user_id = $userId"];
if ($filterStatus !== '' && in_array($filterStatus, ['PENDING', 'DEPOSIT_PAID', 'CHECKED_IN', 'CHECKED_OUT', 'CANCELLED'])) {
    $whereClauses[] = "b.status = '" . mysqli_real_escape_string($conn, $filterStatus) . "'";
}
if ($filterRentalType !== '' && in_array($filterRentalType, ['DAILY', 'MONTHLY'])) {
    $whereClauses[] = "r.rental_type = '" . mysqli_real_escape_string($conn, $filterRentalType) . "'";
}
$whereSQL = implode(' AND ', $whereClauses);

// Lấy danh sách đặt phòng của sinh viên
$sql = "
    SELECT b.*, 
           r.room_code, r.base_rent, r.rental_type, r.daily_price,
           bl.building_name, bl.address,
           u.full_name as landlord_name, u.phone as landlord_phone
    FROM bookings b
    JOIN tenants t ON t.tenant_id = b.tenant_id
    LEFT JOIN rooms r ON r.room_id = b.room_id
    LEFT JOIN buildings bl ON bl.building_id = r.building_id
    LEFT JOIN users u ON u.user_id = bl.owner_id
    WHERE $whereSQL
    ORDER BY b.created_at DESC
";
$bookings = mysqli_query($conn, $sql);

$statusLabels = [
    'PENDING' => ['Chờ thanh toán', 'warning'],
    'DEPOSIT_PAID' => ['Đã thanh toán', 'success'],
    'CHECKED_IN' => ['Đang ở', 'primary'],
    'CHECKED_OUT' => ['Đã trả phòng', 'secondary'],
    'CANCELLED' => ['Đã hủy', 'danger']
];
?>

<!-- Page Header Start -->
<div class="container-fluid page-header mb-5 p-0" style="background-image: url(<?= $hotelier ?>/img/carousel-1.jpg);">
    <div class="container-fluid page-header-inner py-5">
        <div class="container text-center pb-5">
            <h1 class="display-3 text-white mb-3 animated slideInDown">Lịch sử đặt phòng</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb justify-content-center text-uppercase">
                    <li class="breadcrumb-item"><a href="/quanlyphongtro/client/index.php?page=home" class="text-white">Trang chủ</a></li>
                    <li class="breadcrumb-item text-white active">Lịch sử đặt phòng</li>
                </ol>
            </nav>
        </div>
    </div>
</div>
<!-- Page Header End -->

<div class="container-xxl py-5">
    <div class="container">
        <div class="text-center mb-4">
            <h6 class="section-title text-center text-primary text-uppercase">Đặt phòng của tôi</h6>
            <h1 class="mb-0">Lịch sử <span class="text-primary">Đặt phòng</span></h1>
        </div>
        
        <!-- Filter Form -->
        <div class="bg-light rounded p-3 mb-4">
            <form method="get" action="/quanlyphongtro/client/index.php" class="row g-2 align-items-end">
                <input type="hidden" name="page" value="lichsu_datphong">
                <div class="col-md-4">
                    <label class="form-label small fw-bold">Trạng thái</label>
                    <select name="status" class="form-select">
                        <option value="">-- Tất cả --</option>
                        <option value="PENDING" <?= $filterStatus === 'PENDING' ? 'selected' : '' ?>>Chờ thanh toán</option>
                        <option value="DEPOSIT_PAID" <?= $filterStatus === 'DEPOSIT_PAID' ? 'selected' : '' ?>>Đã thanh toán</option>
                        <option value="CHECKED_IN" <?= $filterStatus === 'CHECKED_IN' ? 'selected' : '' ?>>Đang ở</option>
                        <option value="CHECKED_OUT" <?= $filterStatus === 'CHECKED_OUT' ? 'selected' : '' ?>>Đã trả phòng</option>
                        <option value="CANCELLED" <?= $filterStatus === 'CANCELLED' ? 'selected' : '' ?>>Đã hủy</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label small fw-bold">Loại thuê</label>
                    <select name="rental_type" class="form-select">
                        <option value="">-- Tất cả --</option>
                        <option value="DAILY" <?= $filterRentalType === 'DAILY' ? 'selected' : '' ?>>Theo ngày</option>
                        <option value="MONTHLY" <?= $filterRentalType === 'MONTHLY' ? 'selected' : '' ?>>Theo tháng</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <button type="submit" class="btn btn-primary w-100"><i class="fa fa-search me-1"></i>Lọc</button>
                </div>
            </form>
        </div>
        
        <?php if ($bookings && mysqli_num_rows($bookings) > 0): ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-dark">
                        <tr>
                            <th>Mã đặt phòng</th>
                            <th>Phòng</th>
                            <th>Địa chỉ</th>
                            <th>Ngày nhận</th>
                            <th>Ngày trả</th>
                            <th>Trạng thái</th>
                            <th>Hành động</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($b = mysqli_fetch_assoc($bookings)): ?>
                            <?php
                            $status = $b['status'] ?? 'PENDING';
                            $label = $statusLabels[$status] ?? ['Không xác định', 'secondary'];
                            ?>
                            <tr>
                                <td>
                                    <strong class="text-primary"><?= htmlspecialchars($b['booking_code'] ?? 'N/A') ?></strong>
                                </td>
                                <td>
                                    <strong><?= htmlspecialchars($b['room_code'] ?? 'N/A') ?></strong>
                                    <br>
                                    <small class="text-muted"><?= htmlspecialchars($b['building_name'] ?? '') ?></small>
                                </td>
                                <td>
                                    <small><?= htmlspecialchars($b['address'] ?? '') ?></small>
                                </td>
                                <td><?= $b['check_in'] ? date('d/m/Y', strtotime($b['check_in'])) : '-' ?></td>
                                <td><?= $b['check_out'] ? date('d/m/Y', strtotime($b['check_out'])) : '-' ?></td>
                                <td>
                                    <span class="badge bg-<?= $label[1] ?>"><?= $label[0] ?></span>
                                </td>
                                <td>
                                    <a href="/quanlyphongtro/client/index.php?page=chitiet_datphong&id=<?= (int)$b['booking_id'] ?>" 
                                       class="btn btn-sm btn-outline-primary" title="Xem chi tiết">
                                        <i class="fa fa-eye"></i>
                                    </a>
                                    
                                    <?php if ($status === 'PENDING'): ?>
                                        <a href="/quanlyphongtro/client/index.php?page=huy_datphong&id=<?= (int)$b['booking_id'] ?>" 
                                           class="btn btn-sm btn-outline-danger" title="Hủy đặt phòng"
                                           onclick="return confirm('Bạn có chắc muốn hủy yêu cầu đặt phòng này?');">
                                            <i class="fa fa-times"></i>
                                        </a>
                                    <?php elseif ($status === 'DEPOSIT_PAID' || $status === 'CHECKED_IN'): ?>
                                        <?php if ($b['landlord_phone']): ?>
                                        <a href="tel:<?= htmlspecialchars($b['landlord_phone']) ?>" 
                                           class="btn btn-sm btn-outline-success" title="Gọi chủ trọ">
                                            <i class="fa fa-phone"></i>
                                        </a>
                                        <?php endif; ?>
                                    <?php elseif ($status === 'CANCELLED'): ?>
                                        <span class="text-muted small">Đã hủy</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="text-center py-5">
                <i class="bi bi-calendar-x" style="font-size: 80px; color: #ddd;"></i>
                <h4 class="mt-4 text-muted">Bạn chưa có yêu cầu đặt phòng nào</h4>
                <p class="text-muted mb-4">Hãy tìm phòng trọ phù hợp và đặt ngay!</p>
                <a href="/quanlyphongtro/client/index.php?page=phong" class="btn btn-primary py-3 px-5">
                    <i class="fa fa-search me-2"></i>Tìm phòng trọ
                </a>
            </div>
        <?php endif; ?>
        
        <!-- Thông tin liên hệ -->
        <div class="bg-light rounded p-4 mt-5">
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <h5><i class="fa fa-info-circle text-primary me-2"></i>Cần hỗ trợ?</h5>
                    <p class="mb-0 text-muted">Nếu có vấn đề với đặt phòng, vui lòng liên hệ chủ trọ hoặc quản trị viên.</p>
                </div>
                <div class="col-lg-4 text-lg-end mt-3 mt-lg-0">
                    <a href="/quanlyphongtro/client/index.php?page=lienhe" class="btn btn-outline-primary">
                        <i class="fa fa-envelope me-2"></i>Liên hệ hỗ trợ
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
