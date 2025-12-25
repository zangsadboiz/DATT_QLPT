<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';

$role = (string)($_SESSION['role_name'] ?? '');
$userId = (int)($_SESSION['user_id'] ?? 0);
$fullName = (string)($_SESSION['full_name'] ?? 'User');

// Redirect based on role
if ($role === 'LANDLORD') {
    header('Location: /quanlyphongtro/admin/modules/landlord/dashboard.php');
    exit;
} elseif ($role !== 'ADMIN') {
    header('Location: /quanlyphongtro/admin/login.php');
    exit;
}

// ========== ADMIN DASHBOARD STATISTICS ==========

// Landlords count
$landlordStats = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active,
        SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) as locked
    FROM users WHERE role_id = 2
"));

// Posts count
$postStats = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'PENDING' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'APPROVED' THEN 1 ELSE 0 END) as approved,
        SUM(CASE WHEN status = 'REJECTED' THEN 1 ELSE 0 END) as rejected,
        SUM(CASE WHEN status = 'EXPIRED' THEN 1 ELSE 0 END) as expired
    FROM posts
"));

// Revenue this month
$revenueResult = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT COALESCE(SUM(amount), 0) as revenue
    FROM transactions 
    WHERE transaction_type = 'TOPUP' 
    AND status = 'SUCCESS'
    AND MONTH(created_at) = MONTH(CURDATE())
    AND YEAR(created_at) = YEAR(CURDATE())
"));
$monthlyRevenue = (float)($revenueResult['revenue'] ?? 0);

// Recent approved posts (instead of pending)
$recentPosts = mysqli_query($conn, "
    SELECT p.*, u.full_name as landlord_name, d.district_name, pr.province_name,
           pk.package_name, pk.highlight_color
    FROM posts p
    JOIN users u ON u.user_id = p.user_id
    JOIN districts d ON d.district_id = p.district_id
    JOIN provinces pr ON pr.province_id = d.province_id
    JOIN packages pk ON pk.package_id = p.package_id
    WHERE p.status = 'APPROVED'
    ORDER BY p.created_at DESC
    LIMIT 5
");

// Recent transactions
$recentTransactions = mysqli_query($conn, "
    SELECT t.*, u.full_name, u.username
    FROM transactions t
    JOIN users u ON u.user_id = t.user_id
    ORDER BY t.created_at DESC
    LIMIT 5
");

require_once __DIR__ . '/includes/header.php';
?>

<div class="pagetitle">
    <h1><i class="bi bi-speedometer2 me-2"></i>Dashboard Admin</h1>
    <nav>
        <ol class="breadcrumb">
            <li class="breadcrumb-item active">Dashboard</li>
        </ol>
    </nav>
</div>

<section class="section dashboard">
    
    <!-- Stats Cards -->
    <div class="row mb-4">
        
        <!-- Landlords -->
        <div class="col-xl-3 col-md-6">
            <div class="card info-card">
                <div class="card-body">
                    <h5 class="card-title">Chủ trọ <span>| Tổng cộng</span></h5>
                    <div class="d-flex align-items-center">
                        <div class="card-icon rounded-circle d-flex align-items-center justify-content-center" 
                             style="background: #e0f7fa;">
                            <i class="bi bi-people" style="color: #00838f;"></i>
                        </div>
                        <div class="ps-3">
                            <h6><?= $landlordStats['total'] ?? 0 ?></h6>
                            <span class="text-success small pt-1 fw-bold"><?= $landlordStats['active'] ?? 0 ?> hoạt động</span>
                            <span class="text-muted small pt-2 ps-1">| <?= $landlordStats['locked'] ?? 0 ?> bị khóa</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Posts -->
        <div class="col-xl-3 col-md-6">
            <div class="card info-card">
                <div class="card-body">
                    <h5 class="card-title">Tin đăng <span>| Tổng cộng</span></h5>
                    <div class="d-flex align-items-center">
                        <div class="card-icon rounded-circle d-flex align-items-center justify-content-center"
                             style="background: #fff3e0;">
                            <i class="bi bi-file-earmark-text" style="color: #e65100;"></i>
                        </div>
                        <div class="ps-3">
                            <h6><?= $postStats['total'] ?? 0 ?></h6>
                            <span class="text-success small"><?= $postStats['approved'] ?? 0 ?> đang hiển thị</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Pending Posts -->
        <div class="col-xl-3 col-md-6">
            <div class="card info-card">
                <div class="card-body">
                    <h5 class="card-title">Chờ duyệt <span>| Tin mới</span></h5>
                    <div class="d-flex align-items-center">
                        <div class="card-icon rounded-circle d-flex align-items-center justify-content-center"
                             style="background: #fce4ec;">
                            <i class="bi bi-clock-history" style="color: #c2185b;"></i>
                        </div>
                        <div class="ps-3">
                            <h6><?= $postStats['pending'] ?? 0 ?></h6>
                            <span class="text-warning small fw-bold">Cần xử lý</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Revenue -->
        <div class="col-xl-3 col-md-6">
            <div class="card info-card">
                <div class="card-body">
                    <h5 class="card-title">Doanh thu <span>| Tháng này</span></h5>
                    <div class="d-flex align-items-center">
                        <div class="card-icon rounded-circle d-flex align-items-center justify-content-center"
                             style="background: #e8f5e9;">
                            <i class="bi bi-currency-dollar" style="color: #2e7d32;"></i>
                        </div>
                        <div class="ps-3">
                            <h6><?= number_format($monthlyRevenue, 0, ',', '.') ?>đ</h6>
                            <span class="text-muted small">Từ nạp tiền</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
    </div>

    <div class="row">
        
        <!-- Recent Approved Posts Table -->
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-file-earmark-check me-2"></i>Tin đăng mới nhất</h5>
                    <a href="<?= ADMIN_BASE_PATH ?>/modules/posts/index.php" class="btn btn-sm btn-outline-primary">
                        Xem tất cả
                    </a>
                </div>
                <div class="card-body">
                    <?php if ($recentPosts instanceof mysqli_result && mysqli_num_rows($recentPosts) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Tin đăng</th>
                                        <th>Chủ trọ</th>
                                        <th>Gói tin</th>
                                        <th>Giá thuê</th>
                                        <th>Hành động</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($post = mysqli_fetch_assoc($recentPosts)): ?>
                                        <tr>
                                            <td>
                                                <strong><?= htmlspecialchars(mb_substr($post['title'], 0, 40)) ?>...</strong>
                                                <br>
                                                <small class="text-muted"><?= htmlspecialchars($post['district_name']) ?>, <?= htmlspecialchars($post['province_name']) ?></small>
                                            </td>
                                            <td><?= htmlspecialchars($post['landlord_name']) ?></td>
                                            <td>
                                                <span class="badge" style="background-color: <?= $post['highlight_color'] ?: '#6c757d' ?>;">
                                                    <?= htmlspecialchars($post['package_name']) ?>
                                                </span>
                                            </td>
                                            <td><?= number_format((float)$post['price'], 0, ',', '.') ?>đ</td>
                                            <td>
                                                <a href="<?= ADMIN_BASE_PATH ?>/modules/posts/view.php?id=<?= $post['post_id'] ?>" 
                                                   class="btn btn-sm btn-outline-info"><i class="bi bi-eye"></i></a>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-center text-muted py-4">
                            <i class="bi bi-file-earmark-x fs-1 d-block mb-2"></i>
                            Chưa có tin đăng nào
                        </p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Recent Transactions -->
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-cash-stack me-2"></i>Giao dịch gần đây</h5>
                </div>
                <div class="card-body">
                    <?php if ($recentTransactions instanceof mysqli_result && mysqli_num_rows($recentTransactions) > 0): ?>
                        <ul class="list-group list-group-flush">
                            <?php while ($trans = mysqli_fetch_assoc($recentTransactions)): ?>
                                <li class="list-group-item px-0">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <strong><?= htmlspecialchars($trans['full_name']) ?></strong>
                                            <br>
                                            <small class="text-muted">
                                                <?php
                                                $types = [
                                                    'TOPUP' => 'Nạp tiền',
                                                    'POST_NEW' => 'Đăng tin mới',
                                                    'POST_EXTEND' => 'Gia hạn tin',
                                                    'REFUND' => 'Hoàn tiền',
                                                    'POST' => 'Đăng tin',
                                                    'DEPOSIT' => 'Nạp tiền',
                                                    'POST_RESUBMIT' => 'Đăng lại tin',
                                                    'WITHDRAWAL' => 'Rút tiền',
                                                    'DEPOSIT_RECEIVED' => 'Cọc thuê phòng'
                                                ];
                                                echo $types[$trans['transaction_type']] ?? $trans['transaction_type'];
                                                ?>
                                            </small>
                                        </div>
                                        <div class="text-end">
                                            <span class="<?= (float)$trans['amount'] > 0 ? 'text-success' : 'text-danger' ?>">
                                                <?= (float)$trans['amount'] > 0 ? '+' : '' ?><?= number_format((float)$trans['amount'], 0, ',', '.') ?>đ
                                            </span>
                                            <br>
                                            <small class="text-muted"><?= date('d/m H:i', strtotime($trans['created_at'])) ?></small>
                                        </div>
                                    </div>
                                </li>
                            <?php endwhile; ?>
                        </ul>
                    <?php else: ?>
                        <p class="text-center text-muted py-3">Chưa có giao dịch</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
    </div>

</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
