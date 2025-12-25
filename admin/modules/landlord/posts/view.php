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

$postId = (int)($_GET['id'] ?? 0);
if ($postId <= 0) {
    header('Location: ' . ADMIN_BASE_PATH . '/modules/landlord/posts/index.php');
    exit;
}

// Get post with details
$post = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT p.*, d.district_name, pr.province_name, pk.package_name, pk.highlight_color
    FROM posts p 
    JOIN districts d ON d.district_id = p.district_id
    JOIN provinces pr ON pr.province_id = d.province_id
    JOIN packages pk ON pk.package_id = p.package_id
    WHERE p.post_id = $postId AND p.user_id = $userId
"));

if (!$post) {
    header('Location: ' . ADMIN_BASE_PATH . '/modules/landlord/posts/index.php?msg=not_found');
    exit;
}

// Get images
$images = mysqli_query($conn, "SELECT * FROM post_images WHERE post_id = $postId ORDER BY is_primary DESC, sort_order");

require_once __DIR__ . '/../../../includes/header.php';

$amenities = json_decode($post['amenities'] ?: '[]', true) ?: [];
$amenityLabels = [
    'wifi' => 'Wifi', 'ac' => 'Điều hòa', 'wc_rieng' => 'WC riêng',
    'bep' => 'Bếp', 'tu_lanh' => 'Tủ lạnh', 'may_giat' => 'Máy giặt',
    'gac_lung' => 'Gác lửng', 'ban_cong' => 'Ban công', 'thang_may' => 'Thang máy'
];
?>

<div class="pagetitle">
    <h1><i class="bi bi-eye me-2"></i>Chi tiết tin đăng</h1>
    <nav>
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= ADMIN_BASE_PATH ?>/modules/landlord/dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="<?= ADMIN_BASE_PATH ?>/modules/landlord/posts/index.php">Tin đăng</a></li>
            <li class="breadcrumb-item active">Chi tiết</li>
        </ol>
    </nav>
</div>

<section class="section">
    <div class="row">
        <div class="col-lg-8">
            
            <!-- Images -->
            <div class="card mb-3">
                <div class="card-body">
                    <?php if (mysqli_num_rows($images) > 0): ?>
                        <div class="row g-2">
                            <?php while ($img = mysqli_fetch_assoc($images)): ?>
                                <div class="col-md-4">
                                    <img src="/quanlyphongtro/uploads/posts/<?= htmlspecialchars($img['image_path']) ?>" 
                                         alt="" class="img-fluid rounded" style="height: 150px; width: 100%; object-fit: cover;">
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4 bg-light rounded">
                            <i class="bi bi-image fs-1 text-muted"></i>
                            <p class="text-muted mb-0">Chưa có hình ảnh</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Info -->
            <div class="card mb-3">
                <div class="card-header">
                    <h5 class="mb-0"><?= htmlspecialchars($post['title']) ?></h5>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <p class="mb-2"><strong><i class="bi bi-geo-alt me-1"></i>Địa chỉ:</strong></p>
                            <p><?= htmlspecialchars($post['address']) ?>, <?= htmlspecialchars($post['district_name']) ?>, <?= htmlspecialchars($post['province_name']) ?></p>
                        </div>
                        <div class="col-md-6">
                            <p class="mb-2"><strong><i class="bi bi-rulers me-1"></i>Thông tin:</strong></p>
                            <p>Diện tích: <?= $post['area'] ?>m² | Tối đa <?= $post['max_occupants'] ?> người</p>
                        </div>
                    </div>
                    
                    <h6><i class="bi bi-text-paragraph me-1"></i>Mô tả:</h6>
                    <p><?= nl2br(htmlspecialchars($post['description'] ?? 'Chưa có mô tả')) ?></p>
                    
                    <?php if (!empty($amenities)): ?>
                        <h6><i class="bi bi-check2-circle me-1"></i>Tiện ích:</h6>
                        <div class="d-flex flex-wrap gap-2">
                            <?php foreach ($amenities as $a): ?>
                                <span class="badge bg-light text-dark"><?= $amenityLabels[$a] ?? $a ?></span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
        </div>
        
        <div class="col-lg-4">
            <!-- Price & Status -->
            <div class="card mb-3">
                <div class="card-body">
                    <div class="text-center mb-3">
                        <h3 class="text-danger mb-1"><?= number_format((float)$post['price'], 0, ',', '.') ?>đ/tháng</h3>
                        <p class="text-muted mb-0">Cọc: <?= number_format((float)$post['deposit'], 0, ',', '.') ?>đ</p>
                    </div>
                    <hr>
                    <p class="mb-2"><strong>Mã tin:</strong> <?= htmlspecialchars($post['post_code']) ?></p>
                    <p class="mb-2">
                        <strong>Gói tin:</strong>
                        <span class="badge" style="background-color: <?= $post['highlight_color'] ?: '#6c757d' ?>;">
                            <?= htmlspecialchars($post['package_name']) ?>
                        </span>
                    </p>
                    <p class="mb-2">
                        <strong>Trạng thái:</strong>
                        <?php
                        $statusMap = [
                            'PENDING' => '<span class="badge bg-warning">Chờ duyệt</span>',
                            'APPROVED' => '<span class="badge bg-success">Đang hiển thị</span>',
                            'REJECTED' => '<span class="badge bg-danger">Bị từ chối</span>',
                            'EXPIRED' => '<span class="badge bg-secondary">Hết hạn</span>',
                        ];
                        echo $statusMap[$post['status']] ?? $post['status'];
                        ?>
                    </p>
                    <?php if ($post['start_date'] && $post['end_date']): ?>
                        <p class="mb-2"><strong>Thời hạn:</strong> <?= date('d/m/Y', strtotime($post['start_date'])) ?> - <?= date('d/m/Y', strtotime($post['end_date'])) ?></p>
                    <?php endif; ?>
                    <p class="mb-0"><strong>Lượt xem:</strong> <?= number_format((int)$post['view_count']) ?></p>
                </div>
            </div>
            
            <!-- Contact -->
            <div class="card mb-3">
                <div class="card-header"><h6 class="mb-0">Liên hệ</h6></div>
                <div class="card-body">
                    <p class="mb-1"><i class="bi bi-person me-2"></i><?= htmlspecialchars($post['contact_name'] ?? 'N/A') ?></p>
                    <p class="mb-0"><i class="bi bi-telephone me-2"></i><?= htmlspecialchars($post['contact_phone'] ?? 'N/A') ?></p>
                </div>
            </div>
            
            <!-- Actions -->
            <div class="d-grid gap-2">
                <a href="<?= ADMIN_BASE_PATH ?>/modules/landlord/posts/edit.php?id=<?= $postId ?>" class="btn btn-primary">
                    <i class="bi bi-pencil me-2"></i>Chỉnh sửa
                </a>
                <a href="<?= ADMIN_BASE_PATH ?>/modules/landlord/posts/index.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-2"></i>Quay lại
                </a>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/../../../includes/footer.php'; ?>
