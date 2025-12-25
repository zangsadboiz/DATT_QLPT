<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';

$role = (string)($_SESSION['role_name'] ?? '');
if ($role !== 'ADMIN') {
    header('Location: ' . ADMIN_BASE_PATH . '/index.php');
    exit;
}

$postId = (int)($_GET['id'] ?? 0);
if ($postId <= 0) {
    header('Location: ' . ADMIN_BASE_PATH . '/modules/posts/pending.php');
    exit;
}

// Get post details
$post = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT p.*, u.full_name as landlord_name, u.phone as landlord_phone, u.email as landlord_email,
           pk.package_name, pk.highlight_color,
           d.district_name, pr.province_name
    FROM posts p
    JOIN users u ON u.user_id = p.user_id
    JOIN packages pk ON pk.package_id = p.package_id
    JOIN districts d ON d.district_id = p.district_id
    JOIN provinces pr ON pr.province_id = d.province_id
    WHERE p.post_id = $postId
"));

if (!$post) {
    header('Location: ' . ADMIN_BASE_PATH . '/modules/posts/pending.php');
    exit;
}

// Get images
$images = mysqli_query($conn, "SELECT * FROM post_images WHERE post_id = $postId ORDER BY is_primary DESC, sort_order ASC");

// Handle approval
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $adminId = (int)$_SESSION['user_id'];
    
    if ($action === 'approve') {
        $startDate = date('Y-m-d');
        $endDate = date('Y-m-d', strtotime("+{$post['days_posted']} days"));
        
        mysqli_query($conn, "UPDATE posts SET 
            status = 'APPROVED', 
            start_date = '$startDate', 
            end_date = '$endDate',
            approved_by = $adminId,
            approved_at = NOW()
            WHERE post_id = $postId");
            
        header('Location: ' . ADMIN_BASE_PATH . '/modules/posts/pending.php?msg=approved');
        exit;
        
    } elseif ($action === 'reject') {
        $reason = mysqli_real_escape_string($conn, $_POST['rejection_reason'] ?? '');
        
        // Get package price to calculate refund
        $pkgResult = mysqli_fetch_assoc(mysqli_query($conn, "
            SELECT pk.price_per_day, p.days_posted, p.user_id
            FROM posts p
            JOIN packages pk ON pk.package_id = p.package_id
            WHERE p.post_id = $postId
        "));
        
        if ($pkgResult) {
            $refundAmount = (float)$pkgResult['price_per_day'] * (int)$pkgResult['days_posted'];
            $landlordId = (int)$pkgResult['user_id'];
            
            // Get current balance
            $userResult = mysqli_fetch_assoc(mysqli_query($conn, "SELECT balance FROM users WHERE user_id = $landlordId"));
            $currentBalance = (float)($userResult['balance'] ?? 0);
            $newBalance = $currentBalance + $refundAmount;
            
            // Update user balance
            mysqli_query($conn, "UPDATE users SET balance = $newBalance WHERE user_id = $landlordId");
            
            // Create refund transaction
            mysqli_query($conn, "INSERT INTO transactions (user_id, post_id, transaction_type, amount, balance_before, balance_after, description, status, created_at)
                VALUES ($landlordId, $postId, 'REFUND', $refundAmount, $currentBalance, $newBalance, 'Hoàn tiền do tin bị từ chối', 'SUCCESS', NOW())");
        }
        
        // Update post status
        mysqli_query($conn, "UPDATE posts SET 
            status = 'REJECTED', 
            rejection_reason = '$reason',
            approved_by = $adminId,
            approved_at = NOW()
            WHERE post_id = $postId");
            
        header('Location: ' . ADMIN_BASE_PATH . '/modules/posts/pending.php?msg=rejected');
        exit;
        
    } elseif ($action === 'hide') {
        // Ẩn tin đang hiển thị
        mysqli_query($conn, "UPDATE posts SET status = 'HIDDEN', updated_at = NOW() WHERE post_id = $postId");
        $_SESSION['alert'] = ['type' => 'success', 'message' => 'Đã ẩn tin đăng!'];
        header('Location: ' . ADMIN_BASE_PATH . '/modules/posts/review.php?id=' . $postId);
        exit;
        
    } elseif ($action === 'show') {
        // Hiển thị lại tin đã ẩn
        mysqli_query($conn, "UPDATE posts SET status = 'APPROVED', updated_at = NOW() WHERE post_id = $postId");
        $_SESSION['alert'] = ['type' => 'success', 'message' => 'Đã hiển thị lại tin đăng!'];
        header('Location: ' . ADMIN_BASE_PATH . '/modules/posts/review.php?id=' . $postId);
        exit;
    }
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="pagetitle">
    <h1><i class="bi bi-file-earmark-check me-2"></i>Xem & Duyệt tin đăng</h1>
    <nav>
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= ADMIN_BASE_PATH ?>/index.php">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="<?= ADMIN_BASE_PATH ?>/modules/posts/pending.php">Tin chờ duyệt</a></li>
            <li class="breadcrumb-item active">Xem chi tiết</li>
        </ol>
    </nav>
</div>

<section class="section">
    <div class="row">
        
        <!-- Post Details -->
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Chi tiết tin đăng</h5>
                    <span class="badge" style="background-color: <?= $post['highlight_color'] ?: '#6c757d' ?>;">
                        <?= htmlspecialchars($post['package_name']) ?>
                    </span>
                </div>
                <div class="card-body">
                    
                    <!-- Images -->
                    <?php if ($images && mysqli_num_rows($images) > 0): ?>
                        <div class="row mb-4">
                            <?php while ($img = mysqli_fetch_assoc($images)): ?>
                                <div class="col-md-4 mb-3">
                                    <img src="/quanlyphongtro/uploads/posts/<?= htmlspecialchars($img['image_path']) ?>" 
                                         alt="" class="img-fluid rounded" style="height: 150px; width: 100%; object-fit: cover;">
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-warning">
                            <i class="bi bi-exclamation-triangle me-2"></i>Tin này chưa có hình ảnh
                        </div>
                    <?php endif; ?>
                    
                    <!-- Title & Description -->
                    <h4><?= htmlspecialchars($post['title']) ?></h4>
                    
                    <div class="d-flex gap-3 mb-3">
                        <span class="badge bg-primary"><?= $post['area'] ?>m²</span>
                        <span class="badge bg-info">Tối đa <?= $post['max_occupants'] ?> người</span>
                        <span class="badge bg-secondary">
                            <?php
                            $types = ['ROOM' => 'Phòng trọ', 'APARTMENT' => 'Căn hộ', 'HOUSE' => 'Nhà nguyên căn'];
                            echo $types[$post['post_type']] ?? $post['post_type'];
                            ?>
                        </span>
                    </div>
                    
                    <p class="text-muted">
                        <i class="bi bi-geo-alt me-1"></i>
                        <?= htmlspecialchars($post['address']) ?>, <?= htmlspecialchars($post['district_name']) ?>, <?= htmlspecialchars($post['province_name']) ?>
                    </p>
                    
                    <hr>
                    
                    <h5>Giá thuê</h5>
                    <p>
                        <strong class="fs-4 text-danger"><?= number_format((float)$post['price'], 0, ',', '.') ?>đ</strong>/tháng
                        <br>
                        <small class="text-muted">Tiền cọc: <?= number_format((float)$post['deposit'], 0, ',', '.') ?>đ</small>
                    </p>
                    
                    <hr>
                    
                    <h5>Mô tả</h5>
                    <div class="border rounded p-3 bg-light">
                        <?= nl2br(htmlspecialchars($post['description'] ?: 'Chưa có mô tả')) ?>
                    </div>
                    
                    <?php if ($post['amenities']): ?>
                        <hr>
                        <h5>Tiện ích</h5>
                        <div class="d-flex flex-wrap gap-2">
                            <?php
                            $amenities = json_decode($post['amenities'], true) ?: [];
                            $amenityLabels = [
                                'wifi' => 'Wifi', 'ac' => 'Điều hòa', 'wc_rieng' => 'WC riêng',
                                'bep' => 'Bếp', 'tu_lanh' => 'Tủ lạnh', 'may_giat' => 'Máy giặt',
                                'gac_lung' => 'Gác lửng', 'ban_cong' => 'Ban công', 'thang_may' => 'Thang máy',
                                'san_thuong' => 'Sân thượng', 'cho_de_oto' => 'Chỗ đỗ ô tô'
                            ];
                            foreach ($amenities as $am):
                                $label = $amenityLabels[$am] ?? $am;
                            ?>
                                <span class="badge bg-light text-dark border"><?= htmlspecialchars($label) ?></span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    
                </div>
            </div>
        </div>
        
        <!-- Sidebar: Landlord Info & Actions -->
        <div class="col-lg-4">
            
            <!-- Landlord Info -->
            <div class="card mb-3">
                <div class="card-header bg-light">
                    <h6 class="mb-0"><i class="bi bi-person me-2"></i>Thông tin chủ trọ</h6>
                </div>
                <div class="card-body py-3">
                    <div class="mb-2"><strong><?= htmlspecialchars($post['landlord_name']) ?></strong></div>
                    <div class="mb-2"><i class="bi bi-phone me-2 text-muted"></i><?= htmlspecialchars($post['landlord_phone']) ?></div>
                    <div class="mb-0"><i class="bi bi-envelope me-2 text-muted"></i><?= htmlspecialchars($post['landlord_email']) ?></div>
                </div>
            </div>
            
            <!-- Post Meta -->
            <div class="card mb-3">
                <div class="card-header bg-light">
                    <h6 class="mb-0"><i class="bi bi-info-circle me-2"></i>Thông tin đăng tin</h6>
                </div>
                <div class="card-body py-3">
                    <table class="table table-sm table-borderless mb-0">
                        <tr>
                            <td class="text-muted" style="width: 90px;">Mã tin:</td>
                            <td><strong><?= htmlspecialchars($post['post_code']) ?></strong></td>
                        </tr>
                        <tr>
                            <td class="text-muted">Ngày tạo:</td>
                            <td><?= date('d/m/Y H:i', strtotime($post['created_at'])) ?></td>
                        </tr>
                        <tr>
                            <td class="text-muted">Thời hạn:</td>
                            <td><?= $post['days_posted'] ?> ngày</td>
                        </tr>
                        <tr>
                            <td class="text-muted">Trạng thái:</td>
                            <td>
                                <?php 
                                $statusBadge = [
                                    'PENDING' => '<span class="badge bg-warning">Chờ duyệt</span>',
                                    'APPROVED' => '<span class="badge bg-success">Đang hiển thị</span>',
                                    'REJECTED' => '<span class="badge bg-danger">Đã từ chối</span>',
                                    'EXPIRED' => '<span class="badge bg-secondary">Hết hạn</span>',
                                ][$post['status']] ?? '<span class="badge bg-secondary">' . $post['status'] . '</span>';
                                echo $statusBadge;
                                ?>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
            
            <!-- Actions -->
            <?php if ($post['status'] === 'PENDING'): ?>
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h6 class="mb-0"><i class="bi bi-check2-square me-2"></i>Hành động</h6>
                </div>
                <div class="card-body">
                    
                    <!-- Approve -->
                    <form action="" method="POST" class="mb-3">
                        <input type="hidden" name="action" value="approve">
                        <button type="submit" class="btn btn-success w-100" 
                                onclick="return confirm('Xác nhận DUYỆT tin này?')">
                            <i class="bi bi-check-circle me-2"></i>DUYỆT TIN NÀY
                        </button>
                    </form>
                    
                    <hr>
                    
                    <!-- Reject -->
                    <form action="" method="POST">
                        <input type="hidden" name="action" value="reject">
                        <div class="mb-3">
                            <label class="form-label small text-muted">Lý do từ chối:</label>
                            <textarea name="rejection_reason" class="form-control form-control-sm" rows="2" 
                                      placeholder="Nhập lý do từ chối..." required></textarea>
                        </div>
                        <button type="submit" class="btn btn-outline-danger w-100"
                                onclick="return confirm('Xác nhận TỪ CHỐI tin này?')">
                            <i class="bi bi-x-circle me-2"></i>Từ chối
                        </button>
                    </form>
                    
                </div>
            </div>
            <?php elseif ($post['status'] === 'APPROVED'): ?>
            <div class="card">
                <div class="card-header bg-success text-white">
                    <h6 class="mb-0"><i class="bi bi-check-circle me-2"></i>Tin đang hiển thị</h6>
                </div>
                <div class="card-body">
                    <p class="text-muted small mb-3">Tin này đang hiển thị công khai. Admin có thể ẩn nếu vi phạm.</p>
                    <form action="" method="POST">
                        <input type="hidden" name="action" value="hide">
                        <button type="submit" class="btn btn-warning w-100"
                                onclick="return confirm('Ẩn tin đăng này?')">
                            <i class="bi bi-eye-slash me-2"></i>Ẩn tin đăng
                        </button>
                    </form>
                </div>
            </div>
            <?php elseif ($post['status'] === 'HIDDEN'): ?>
            <div class="card">
                <div class="card-header bg-secondary text-white">
                    <h6 class="mb-0"><i class="bi bi-eye-slash me-2"></i>Tin đang ẩn</h6>
                </div>
                <div class="card-body">
                    <p class="text-muted small mb-3">Tin này đang bị ẩn và không hiển thị công khai.</p>
                    <form action="" method="POST">
                        <input type="hidden" name="action" value="show">
                        <button type="submit" class="btn btn-success w-100"
                                onclick="return confirm('Hiển thị lại tin đăng này?')">
                            <i class="bi bi-eye me-2"></i>Hiển thị lại
                        </button>
                    </form>
                </div>
            </div>
            <?php else: ?>
            <div class="card">
                <div class="card-body text-center py-4">
                    <i class="bi bi-info-circle fs-1 text-muted mb-2"></i>
                    <p class="text-muted mb-0">Không có hành động khả dụng</p>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Back button -->
            <a href="<?= ADMIN_BASE_PATH ?>/modules/posts/index.php" class="btn btn-secondary w-100 mt-3">
                <i class="bi bi-arrow-left me-2"></i>Quay lại danh sách
            </a>
            
        </div>
        
    </div>
</section>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
