<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';

$role = (string)($_SESSION['role_name'] ?? '');
if ($role !== 'ADMIN') {
    header('Location: ' . ADMIN_BASE_PATH . '/index.php');
    exit;
}

// Get pending posts
$sql = "SELECT p.*, u.full_name as landlord_name, u.phone as landlord_phone,
        pk.package_name, pk.highlight_color, pk.price_per_day,
        d.district_name, pr.province_name,
        (SELECT image_path FROM post_images WHERE post_id = p.post_id AND is_primary = 1 LIMIT 1) as primary_image
        FROM posts p
        JOIN users u ON u.user_id = p.user_id
        JOIN packages pk ON pk.package_id = p.package_id
        JOIN districts d ON d.district_id = p.district_id
        JOIN provinces pr ON pr.province_id = d.province_id
        WHERE p.status = 'PENDING'
        ORDER BY p.created_at DESC";
$posts = mysqli_query($conn, $sql);

// Count separately to ensure accuracy
$countResult = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as cnt FROM posts WHERE status = 'PENDING'"));
$totalPending = (int)($countResult['cnt'] ?? 0);

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="pagetitle">
    <h1><i class="bi bi-clock-history me-2"></i>Duyệt tin đăng</h1>
    <nav>
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= ADMIN_BASE_PATH ?>/index.php">Dashboard</a></li>
            <li class="breadcrumb-item active">Tin chờ duyệt</li>
        </ol>
    </nav>
</div>

<?php if (isset($_GET['msg'])): ?>
    <?php
    $alertType = 'success';
    $msgs = [
        'approved' => 'Đã duyệt tin đăng thành công và trừ phí đăng tin!',
        'rejected' => 'Đã từ chối tin đăng',
        'insufficient_balance' => 'Không thể duyệt: Chủ trọ không đủ số dư để thanh toán phí đăng tin!'
    ];
    $msg = $msgs[$_GET['msg']] ?? 'Thao tác thành công';
    if ($_GET['msg'] === 'insufficient_balance') $alertType = 'danger';
    ?>
    <div class="alert alert-<?= $alertType ?> alert-dismissible fade show">
        <?= $msg ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<section class="section">
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">
                Có <span class="badge bg-warning"><?= (int)$totalPending ?></span> tin đang chờ duyệt
            </h5>
        </div>
        <div class="card-body">
            <?php if ($totalPending > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th style="width: 80px;">Ảnh</th>
                                <th>Tin đăng</th>
                                <th>Chủ trọ</th>
                                <th>Gói tin</th>
                                <th>Giá thuê</th>
                                <th>Ngày tạo</th>
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
                                        <strong><?= htmlspecialchars(mb_substr($post['title'], 0, 60)) ?>...</strong>
                                        <br>
                                        <small class="text-muted">
                                            <i class="bi bi-geo-alt"></i> <?= htmlspecialchars($post['district_name']) ?>, <?= htmlspecialchars($post['province_name']) ?>
                                        </small>
                                        <br>
                                        <small class="text-muted">
                                            <i class="bi bi-rulers"></i> <?= $post['area'] ?>m² | 
                                            <i class="bi bi-people"></i> Tối đa <?= $post['max_occupants'] ?> người
                                        </small>
                                    </td>
                                    <td>
                                        <strong><?= htmlspecialchars($post['landlord_name']) ?></strong>
                                        <br>
                                        <small><i class="bi bi-phone"></i> <?= htmlspecialchars($post['landlord_phone']) ?></small>
                                    </td>
                                    <td>
                                        <span class="badge" style="background-color: <?= $post['highlight_color'] ?: '#6c757d' ?>;">
                                            <?= htmlspecialchars($post['package_name']) ?>
                                        </span>
                                        <br>
                                        <small><?= number_format((float)$post['price_per_day'], 0, ',', '.') ?>đ/ngày x <?= $post['days_posted'] ?> ngày</small>
                                    </td>
                                    <td>
                                        <strong class="text-danger"><?= number_format((float)$post['price'], 0, ',', '.') ?>đ</strong>
                                        <br>
                                        <small class="text-muted">Cọc: <?= number_format((float)$post['deposit'], 0, ',', '.') ?>đ</small>
                                    </td>
                                    <td>
                                        <?= date('d/m/Y', strtotime($post['created_at'])) ?>
                                        <br>
                                        <small class="text-muted"><?= date('H:i', strtotime($post['created_at'])) ?></small>
                                    </td>
                                    <td>
                                        <a href="<?= ADMIN_BASE_PATH ?>/modules/posts/review.php?id=<?= $post['post_id'] ?>" 
                                           class="btn btn-sm btn-primary mb-1 w-100">
                                            <i class="bi bi-eye me-1"></i>Xem & Duyệt
                                        </a>
                                        <div class="btn-group w-100">
                                            <a href="<?= ADMIN_BASE_PATH ?>/modules/posts/approve.php?id=<?= $post['post_id'] ?>" 
                                               class="btn btn-sm btn-success" title="Duyệt nhanh"
                                               onclick="return confirm('Duyệt tin này?')">
                                                <i class="bi bi-check-lg"></i>
                                            </a>
                                            <a href="<?= ADMIN_BASE_PATH ?>/modules/posts/reject.php?id=<?= $post['post_id'] ?>" 
                                               class="btn btn-sm btn-danger" title="Từ chối">
                                                <i class="bi bi-x-lg"></i>
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
                    <i class="bi bi-check-circle fs-1 text-success d-block mb-3"></i>
                    <h5>Tuyệt vời! Không có tin nào cần duyệt</h5>
                    <p class="text-muted">Tất cả tin đăng đã được xử lý</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
