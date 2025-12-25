<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';

$role = (string)($_SESSION['role_name'] ?? '');
$userId = (int)($_SESSION['user_id'] ?? 0);

// Landlord only
if ($role !== 'LANDLORD') {
    header('Location: /quanlyphongtro/admin/index.php');
    exit;
}

// ========== LANDLORD DASHBOARD STATISTICS ==========

// User info
$userInfo = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM users WHERE user_id = $userId"));
$balance = (float)($userInfo['balance'] ?? 0);

// Posts stats
$postStats = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'PENDING' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'APPROVED' THEN 1 ELSE 0 END) as approved,
        SUM(CASE WHEN status = 'REJECTED' THEN 1 ELSE 0 END) as rejected,
        SUM(CASE WHEN status = 'EXPIRED' THEN 1 ELSE 0 END) as expired,
        SUM(view_count) as total_views
    FROM posts WHERE user_id = $userId
"));

// My posts
$myPosts = mysqli_query($conn, "
    SELECT p.*, pk.package_name, pk.highlight_color, d.district_name, pr.province_name,
           (SELECT image_path FROM post_images WHERE post_id = p.post_id AND is_primary = 1 LIMIT 1) as primary_image
    FROM posts p
    JOIN packages pk ON pk.package_id = p.package_id
    JOIN districts d ON d.district_id = p.district_id
    JOIN provinces pr ON pr.province_id = d.province_id
    WHERE p.user_id = $userId
    ORDER BY p.created_at DESC
    LIMIT 5
");

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="pagetitle">
    <h1><i class="bi bi-house-heart me-2"></i>Dashboard Chủ trọ</h1>
    <nav>
        <ol class="breadcrumb">
            <li class="breadcrumb-item active">Dashboard</li>
        </ol>
    </nav>
</div>

<section class="section dashboard">
    
    <!-- Stats Cards -->
    <div class="row mb-4">
        
        <!-- Balance -->
        <div class="col-xl-3 col-md-6">
            <div class="card info-card">
                <div class="card-body">
                    <h5 class="card-title">Số dư <span>| Tài khoản</span></h5>
                    <div class="d-flex align-items-center">
                        <div class="card-icon rounded-circle d-flex align-items-center justify-content-center"
                             style="background: #e8f5e9;">
                            <i class="bi bi-wallet2" style="color: #2e7d32;"></i>
                        </div>
                        <div class="ps-3">
                            <h6><?= number_format($balance, 0, ',', '.') ?>đ</h6>
                            <a href="<?= ADMIN_BASE_PATH ?>/modules/landlord/topup.php" class="text-primary small">
                                <i class="bi bi-plus-circle"></i> Nạp tiền
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Active Posts -->
        <div class="col-xl-3 col-md-6">
            <div class="card info-card">
                <div class="card-body">
                    <h5 class="card-title">Đang hiển thị <span>| Tin đăng</span></h5>
                    <div class="d-flex align-items-center">
                        <div class="card-icon rounded-circle d-flex align-items-center justify-content-center"
                             style="background: #e3f2fd;">
                            <i class="bi bi-check-circle" style="color: #1565c0;"></i>
                        </div>
                        <div class="ps-3">
                            <h6><?= $postStats['approved'] ?? 0 ?></h6>
                            <span class="text-success small">Đang hoạt động</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Pending Posts -->
        <div class="col-xl-3 col-md-6">
            <div class="card info-card">
                <div class="card-body">
                    <h5 class="card-title">Chờ duyệt <span>| Tin đăng</span></h5>
                    <div class="d-flex align-items-center">
                        <div class="card-icon rounded-circle d-flex align-items-center justify-content-center"
                             style="background: #fff3e0;">
                            <i class="bi bi-hourglass-split" style="color: #e65100;"></i>
                        </div>
                        <div class="ps-3">
                            <h6><?= $postStats['pending'] ?? 0 ?></h6>
                            <span class="text-warning small">Đang chờ Admin duyệt</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Total Views -->
        <div class="col-xl-3 col-md-6">
            <div class="card info-card">
                <div class="card-body">
                    <h5 class="card-title">Lượt xem <span>| Tổng cộng</span></h5>
                    <div class="d-flex align-items-center">
                        <div class="card-icon rounded-circle d-flex align-items-center justify-content-center"
                             style="background: #fce4ec;">
                            <i class="bi bi-eye" style="color: #c2185b;"></i>
                        </div>
                        <div class="ps-3">
                            <h6><?= number_format((float)($postStats['total_views'] ?? 0), 0, ',', '.') ?></h6>
                            <span class="text-muted small">Lượt xem tin</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
    </div>

    <!-- Quick Actions -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-body py-3">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <a href="<?= ADMIN_BASE_PATH ?>/modules/landlord/posts/add.php" class="btn btn-primary w-100 py-3">
                                <i class="bi bi-plus-circle fs-4 d-block mb-1"></i>
                                Đăng tin mới
                            </a>
                        </div>
                        <div class="col-md-3">
                            <a href="<?= ADMIN_BASE_PATH ?>/modules/landlord/posts/index.php" class="btn btn-outline-primary w-100 py-3">
                                <i class="bi bi-list-ul fs-4 d-block mb-1"></i>
                                Quản lý tin đăng
                            </a>
                        </div>
                        <div class="col-md-3">
                            <a href="<?= ADMIN_BASE_PATH ?>/modules/landlord/topup.php" class="btn btn-outline-success w-100 py-3">
                                <i class="bi bi-wallet-fill fs-4 d-block mb-1"></i>
                                Nạp tiền
                            </a>
                        </div>
                        <div class="col-md-3">
                            <a href="<?= ADMIN_BASE_PATH ?>/modules/landlord/transactions.php" class="btn btn-outline-secondary w-100 py-3">
                                <i class="bi bi-clock-history fs-4 d-block mb-1"></i>
                                Lịch sử giao dịch
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- My Recent Posts -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-file-earmark-text me-2"></i>Tin đăng của tôi</h5>
                    <a href="<?= ADMIN_BASE_PATH ?>/modules/landlord/posts/index.php" class="btn btn-sm btn-outline-primary">
                        Xem tất cả
                    </a>
                </div>
                <div class="card-body">
                    <?php if ($myPosts && mysqli_num_rows($myPosts) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th style="width: 80px;">Ảnh</th>
                                        <th>Tiêu đề</th>
                                        <th>Giá</th>
                                        <th>Gói tin</th>
                                        <th>Trạng thái</th>
                                        <th>Lượt xem</th>
                                        <th>Hành động</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($post = mysqli_fetch_assoc($myPosts)): ?>
                                        <tr>
                                            <td>
                                                <?php if ($post['primary_image']): ?>
                                                    <img src="/quanlyphongtro/uploads/posts/<?= htmlspecialchars($post['primary_image']) ?>" 
                                                         alt="" class="rounded" style="width: 60px; height: 45px; object-fit: cover;">
                                                <?php else: ?>
                                                    <div class="bg-secondary rounded d-flex align-items-center justify-content-center" 
                                                         style="width: 60px; height: 45px;">
                                                        <i class="bi bi-image text-white"></i>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <strong><?= htmlspecialchars(mb_substr($post['title'], 0, 50)) ?>...</strong>
                                                <br>
                                                <small class="text-muted"><?= htmlspecialchars($post['district_name']) ?></small>
                                            </td>
                                            <td><?= number_format((float)$post['price'], 0, ',', '.') ?>đ</td>
                                            <td>
                                                <span class="badge" style="background-color: <?= $post['highlight_color'] ?: '#6c757d' ?>;">
                                                    <?= htmlspecialchars($post['package_name']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php
                                                $statusMap = [
                                                    'PENDING' => '<span class="badge bg-warning">Chờ duyệt</span>',
                                                    'APPROVED' => '<span class="badge bg-success">Đang hiển thị</span>',
                                                    'REJECTED' => '<span class="badge bg-danger">Bị từ chối</span>',
                                                    'EXPIRED' => '<span class="badge bg-secondary">Hết hạn</span>',
                                                    'HIDDEN' => '<span class="badge bg-dark">Đã ẩn</span>',
                                                ];
                                                echo $statusMap[$post['status']] ?? $post['status'];
                                                ?>
                                            </td>
                                            <td><?= number_format((int)$post['view_count']) ?></td>
                                            <td>
                                                <a href="<?= ADMIN_BASE_PATH ?>/modules/landlord/posts/edit.php?id=<?= $post['post_id'] ?>" 
                                                   class="btn btn-sm btn-outline-primary" title="Sửa">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                                <?php if ($post['status'] === 'EXPIRED'): ?>
                                                    <a href="<?= ADMIN_BASE_PATH ?>/modules/landlord/posts/extend.php?id=<?= $post['post_id'] ?>" 
                                                       class="btn btn-sm btn-outline-success" title="Gia hạn">
                                                        <i class="bi bi-arrow-repeat"></i>
                                                    </a>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <i class="bi bi-inbox fs-1 text-muted d-block mb-3"></i>
                            <p class="text-muted mb-3">Bạn chưa có tin đăng nào</p>
                            <a href="<?= ADMIN_BASE_PATH ?>/modules/landlord/posts/add.php" class="btn btn-primary">
                                <i class="bi bi-plus-circle me-2"></i>Đăng tin đầu tiên
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

</section>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
