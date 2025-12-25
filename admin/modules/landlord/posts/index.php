<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/db.php';

$role = (string)($_SESSION['role_name'] ?? '');
$userId = (int)($_SESSION['user_id'] ?? 0);

if ($role !== 'LANDLORD') {
    header('Location: ' . ADMIN_BASE_PATH . '/index.php');
    exit;
}

// Filters
$status = (string)($_GET['status'] ?? '');

// Get my posts
$sql = "SELECT p.*, pk.package_name, pk.highlight_color, pk.price_per_day, d.district_name, pr.province_name,
        (SELECT image_path FROM post_images WHERE post_id = p.post_id AND is_primary = 1 LIMIT 1) as primary_image
        FROM posts p
        JOIN packages pk ON pk.package_id = p.package_id
        JOIN districts d ON d.district_id = p.district_id
        JOIN provinces pr ON pr.province_id = d.province_id
        WHERE p.user_id = $userId";

if ($status && in_array($status, ['PENDING','APPROVED','REJECTED','EXPIRED','HIDDEN'], true)) {
    $sql .= " AND p.status = '$status'";
}

$sql .= " ORDER BY p.created_at DESC";
$posts = mysqli_query($conn, $sql);

// Stats
$stats = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'PENDING' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'APPROVED' THEN 1 ELSE 0 END) as approved,
        SUM(CASE WHEN status = 'REJECTED' THEN 1 ELSE 0 END) as rejected,
        SUM(CASE WHEN status = 'EXPIRED' THEN 1 ELSE 0 END) as expired
    FROM posts WHERE user_id = $userId
"));

require_once __DIR__ . '/../../../includes/header.php';
?>

<div class="pagetitle">
    <h1><i class="bi bi-list-ul me-2"></i>Tin đăng của tôi</h1>
    <nav>
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= ADMIN_BASE_PATH ?>/modules/landlord/dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item active">Tin đăng của tôi</li>
        </ol>
    </nav>
</div>

<?php if (isset($_GET['msg'])): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <?php
        $msgs = [
            'created' => 'Đăng tin thành công! Tin đang chờ Admin duyệt.',
            'updated' => 'Cập nhật tin thành công!',
            'deleted' => 'Đã xóa tin đăng'
        ];
        echo $msgs[$_GET['msg']] ?? 'Thao tác thành công';
        ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<section class="section">
    
    <!-- Stats Cards (clickable links) -->
    <div class="row mb-3">
        <div class="col">
            <a href="?" class="card text-center text-decoration-none <?= $status === '' ? 'border-primary border-2' : '' ?>" style="cursor:pointer;">
                <div class="card-body py-3">
                    <h4 class="text-primary mb-0"><?= $stats['total'] ?? 0 ?></h4>
                    <small>Tất cả</small>
                </div>
            </a>
        </div>
        <div class="col">
            <a href="?status=APPROVED" class="card text-center text-decoration-none <?= $status === 'APPROVED' ? 'border-success border-2' : '' ?>" style="cursor:pointer;">
                <div class="card-body py-3">
                    <h4 class="text-success mb-0"><?= $stats['approved'] ?? 0 ?></h4>
                    <small>Đang hiển thị</small>
                </div>
            </a>
        </div>
        <div class="col">
            <a href="?status=PENDING" class="card text-center text-decoration-none <?= $status === 'PENDING' ? 'border-warning border-2' : '' ?>" style="cursor:pointer;">
                <div class="card-body py-3">
                    <h4 class="text-warning mb-0"><?= $stats['pending'] ?? 0 ?></h4>
                    <small>Chờ duyệt</small>
                </div>
            </a>
        </div>
        <div class="col">
            <a href="?status=REJECTED" class="card text-center text-decoration-none <?= $status === 'REJECTED' ? 'border-danger border-2' : '' ?>" style="cursor:pointer;">
                <div class="card-body py-3">
                    <h4 class="text-danger mb-0"><?= $stats['rejected'] ?? 0 ?></h4>
                    <small>Bị từ chối</small>
                </div>
            </a>
        </div>
        <div class="col">
            <a href="?status=EXPIRED" class="card text-center text-decoration-none <?= $status === 'EXPIRED' ? 'border-secondary border-2' : '' ?>" style="cursor:pointer;">
                <div class="card-body py-3">
                    <h4 class="text-secondary mb-0"><?= $stats['expired'] ?? 0 ?></h4>
                    <small>Hết hạn</small>
                </div>
            </a>
        </div>
    </div>
    
    <!-- Select Filter (alternative) -->
    <div class="mb-3">
        <select class="form-select form-select-sm w-auto" onchange="window.location.href=this.value">
            <option value="?" <?= $status === '' ? 'selected' : '' ?>>Tất cả (<?= $stats['total'] ?? 0 ?>)</option>
            <option value="?status=APPROVED" <?= $status === 'APPROVED' ? 'selected' : '' ?>>Đang hiển thị (<?= $stats['approved'] ?? 0 ?>)</option>
            <option value="?status=PENDING" <?= $status === 'PENDING' ? 'selected' : '' ?>>Chờ duyệt (<?= $stats['pending'] ?? 0 ?>)</option>
            <option value="?status=REJECTED" <?= $status === 'REJECTED' ? 'selected' : '' ?>>Bị từ chối (<?= $stats['rejected'] ?? 0 ?>)</option>
            <option value="?status=EXPIRED" <?= $status === 'EXPIRED' ? 'selected' : '' ?>>Hết hạn (<?= $stats['expired'] ?? 0 ?>)</option>
        </select>
    </div>
    
    <!-- Posts List -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Danh sách tin đăng</h5>
            <a href="<?= ADMIN_BASE_PATH ?>/modules/landlord/posts/add.php" class="btn btn-primary">
                <i class="bi bi-plus-circle me-1"></i>Đăng tin mới
            </a>
        </div>
        <div class="card-body">
            <?php if ($posts && mysqli_num_rows($posts) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th style="width: 80px;">Ảnh</th>
                                <th>Tin đăng</th>
                                <th>Giá</th>
                                <th>Gói tin</th>
                                <th>Thời hạn</th>
                                <th>Trạng thái</th>
                                <th>Lượt xem</th>
                                <th>Hành động</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($post = mysqli_fetch_assoc($posts)): ?>
                                <tr>
                                    <td>
                                        <?php if ($post['primary_image']): ?>
                                            <img src="/quanlyphongtro/uploads/posts/<?= htmlspecialchars($post['primary_image']) ?>" 
                                                 alt="" class="rounded" style="width: 70px; height: 50px; object-fit: cover;">
                                        <?php else: ?>
                                            <div class="bg-secondary rounded d-flex align-items-center justify-content-center" 
                                                 style="width: 70px; height: 50px;">
                                                <i class="bi bi-image text-white"></i>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong><?= htmlspecialchars(mb_substr($post['title'], 0, 50)) ?>...</strong>
                                        <br>
                                        <small class="text-muted"><?= htmlspecialchars($post['district_name']) ?>, <?= htmlspecialchars($post['province_name']) ?></small>
                                    </td>
                                    <td>
                                        <strong class="text-danger"><?= number_format((float)$post['price'], 0, ',', '.') ?>đ</strong>
                                    </td>
                                    <td>
                                        <span class="badge" style="background-color: <?= $post['highlight_color'] ?: '#6c757d' ?>;">
                                            <?= htmlspecialchars($post['package_name']) ?>
                                        </span>
                                        <br>
                                        <small class="text-success">
                                            <?php 
                                            $fee = (float)($post['total_cost'] ?? 0);
                                            if ($fee <= 0) $fee = (float)($post['price_per_day'] ?? 0) * (int)($post['days_posted'] ?? 0);
                                            echo number_format($fee, 0, ',', '.') . 'đ';
                                            ?>
                                        </small>
                                    </td>
                                    <td>
                                        <?php if ($post['start_date'] && $post['end_date']): ?>
                                            <?= date('d/m', strtotime($post['start_date'])) ?> - <?= date('d/m/Y', strtotime($post['end_date'])) ?>
                                            <?php
                                            $daysLeft = (strtotime($post['end_date']) - time()) / 86400;
                                            if ($daysLeft > 0 && $daysLeft <= 3):
                                            ?>
                                                <br><span class="badge bg-warning">Còn <?= ceil($daysLeft) ?> ngày</span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="badge bg-secondary"><?= (int)$post['days_posted'] ?> ngày</span>
                                        <?php endif; ?>
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
                                        <?php if ($post['status'] === 'REJECTED' && $post['rejection_reason']): ?>
                                            <br>
                                            <button type="button" class="btn btn-link btn-sm text-danger p-0" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#reasonModal<?= $post['post_id'] ?>">
                                                <i class="bi bi-info-circle"></i> Xem lý do
                                            </button>
                                            
                                            <!-- Modal -->
                                            <div class="modal fade" id="reasonModal<?= $post['post_id'] ?>" tabindex="-1">
                                                <div class="modal-dialog modal-dialog-centered">
                                                    <div class="modal-content">
                                                        <div class="modal-header bg-danger text-white">
                                                            <h5 class="modal-title"><i class="bi bi-exclamation-triangle me-2"></i>Lý do từ chối</h5>
                                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <p class="mb-2"><strong>Tin đăng:</strong></p>
                                                            <p class="text-muted"><?= htmlspecialchars($post['title']) ?></p>
                                                            <hr>
                                                            <p class="mb-2"><strong>Lý do từ chối:</strong></p>
                                                            <div class="alert alert-danger mb-0">
                                                                <?= nl2br(htmlspecialchars($post['rejection_reason'])) ?>
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
                                                            <a href="<?= ADMIN_BASE_PATH ?>/modules/landlord/posts/edit.php?id=<?= $post['post_id'] ?>" 
                                                               class="btn btn-primary">
                                                                <i class="bi bi-pencil me-1"></i>Chỉnh sửa & Đăng lại
                                                            </a>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= number_format((int)$post['view_count']) ?></td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="<?= ADMIN_BASE_PATH ?>/modules/landlord/posts/view.php?id=<?= $post['post_id'] ?>" 
                                               class="btn btn-outline-info" title="Xem">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            <a href="<?= ADMIN_BASE_PATH ?>/modules/landlord/posts/edit.php?id=<?= $post['post_id'] ?>" 
                                               class="btn btn-outline-primary" title="Sửa">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <?php if ($post['status'] === 'EXPIRED'): ?>
                                                <a href="<?= ADMIN_BASE_PATH ?>/modules/landlord/posts/extend.php?id=<?= $post['post_id'] ?>" 
                                                   class="btn btn-outline-success" title="Gia hạn">
                                                    <i class="bi bi-arrow-repeat"></i>
                                                </a>
                                            <?php endif; ?>
                                            <a href="<?= ADMIN_BASE_PATH ?>/modules/landlord/posts/delete.php?id=<?= $post['post_id'] ?>" 
                                               class="btn btn-outline-danger" title="Xóa"
                                               onclick="return confirm('Xóa tin này?')">
                                                <i class="bi bi-trash"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center py-5">
                    <i class="bi bi-inbox fs-1 text-muted d-block mb-3"></i>
                    <?php if ($status === 'REJECTED'): ?>
                        <p class="text-muted mb-3">Không có tin đăng nào bị từ chối</p>
                        <a href="?" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left me-2"></i>Xem tất cả
                        </a>
                    <?php elseif ($status === 'EXPIRED'): ?>
                        <p class="text-muted mb-3">Không có tin đăng nào hết hạn</p>
                        <a href="?" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left me-2"></i>Xem tất cả
                        </a>
                    <?php elseif ($status): ?>
                        <p class="text-muted mb-3">Không có tin đăng nào ở trạng thái này</p>
                        <a href="?" class="btn btn-outline-secondary me-2">
                            <i class="bi bi-arrow-left me-2"></i>Xem tất cả
                        </a>
                        <a href="<?= ADMIN_BASE_PATH ?>/modules/landlord/posts/add.php" class="btn btn-primary">
                            <i class="bi bi-plus-circle me-2"></i>Đăng tin mới
                        </a>
                    <?php else: ?>
                        <p class="text-muted mb-3">Bạn chưa có tin đăng nào</p>
                        <a href="<?= ADMIN_BASE_PATH ?>/modules/landlord/posts/add.php" class="btn btn-primary">
                            <i class="bi bi-plus-circle me-2"></i>Đăng tin đầu tiên
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
</section>

<?php require_once __DIR__ . '/../../../includes/footer.php'; ?>
