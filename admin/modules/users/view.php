<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';

$role = (string)($_SESSION['role_name'] ?? '');
if ($role !== 'ADMIN') {
    header('Location: ' . ADMIN_BASE_PATH . '/index.php');
    exit;
}

$userId = (int)($_GET['id'] ?? 0);
if ($userId <= 0) {
    header('Location: ' . ADMIN_BASE_PATH . '/modules/users/index.php');
    exit;
}

// Get landlord info
$u = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT u.* FROM users u WHERE u.user_id = $userId AND u.role_id = 2
"));

if (!$u) {
    header('Location: ' . ADMIN_BASE_PATH . '/modules/users/index.php?msg=not_found');
    exit;
}

// Get post statistics
$postStats = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'PENDING' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'APPROVED' THEN 1 ELSE 0 END) as approved,
        SUM(CASE WHEN status = 'REJECTED' THEN 1 ELSE 0 END) as rejected,
        SUM(CASE WHEN status = 'EXPIRED' THEN 1 ELSE 0 END) as expired
    FROM posts WHERE user_id = $userId
"));

// Get transaction statistics
$transStats = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT 
        COUNT(*) as total_trans,
        SUM(CASE WHEN transaction_type = 'TOPUP' THEN amount ELSE 0 END) as total_topup,
        SUM(CASE WHEN transaction_type IN ('POST_NEW','POST_RESUBMIT','POST_EXTEND') THEN ABS(amount) ELSE 0 END) as total_spent
    FROM transactions WHERE user_id = $userId
"));

// Get ALL posts (no filter - will filter with JS)
$posts = mysqli_query($conn, "
    SELECT p.*, pk.package_name, pk.highlight_color, d.district_name, pr.province_name,
           (SELECT image_path FROM post_images WHERE post_id = p.post_id AND is_primary = 1 LIMIT 1) as primary_image
    FROM posts p
    JOIN packages pk ON pk.package_id = p.package_id
    JOIN districts d ON d.district_id = p.district_id
    JOIN provinces pr ON pr.province_id = d.province_id
    WHERE p.user_id = $userId
    ORDER BY p.created_at DESC
    LIMIT 50
");

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="pagetitle">
    <h1><i class="bi bi-person-circle me-2"></i>Chi tiết chủ trọ</h1>
    <nav>
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= ADMIN_BASE_PATH ?>/index.php">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="<?= ADMIN_BASE_PATH ?>/modules/users/index.php">Chủ trọ</a></li>
            <li class="breadcrumb-item active"><?= htmlspecialchars($u['full_name']) ?></li>
        </ol>
    </nav>
</div>

<section class="section">
    <div class="row">
        
        <!-- Left Column: Profile Info -->
        <div class="col-lg-4">
            <div class="card mb-3">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="bi bi-person me-2"></i>Thông tin tài khoản</h5>
                </div>
                <div class="card-body">
                    <div class="text-center mb-3">
                        <?php if (!empty($u['avatar'])): ?>
                            <img src="<?= ADMIN_BASE_PATH ?>/uploads/avatars/<?= htmlspecialchars($u['avatar']) ?>" 
                                 alt="" class="rounded-circle" style="width: 100px; height: 100px; object-fit: cover;">
                        <?php else: ?>
                            <div class="rounded-circle bg-secondary d-flex align-items-center justify-content-center mx-auto" 
                                 style="width: 100px; height: 100px;">
                                <i class="bi bi-person text-white" style="font-size: 40px;"></i>
                            </div>
                        <?php endif; ?>
                        <h5 class="mt-2 mb-0"><?= htmlspecialchars($u['full_name']) ?></h5>
                        <small class="text-muted">@<?= htmlspecialchars($u['username']) ?></small>
                    </div>
                    
                    <hr>
                    
                    <table class="table table-sm table-borderless">
                        <tr>
                            <td><i class="bi bi-envelope me-2"></i>Email</td>
                            <td><strong><?= htmlspecialchars($u['email']) ?></strong></td>
                        </tr>
                        <tr>
                            <td><i class="bi bi-phone me-2"></i>Điện thoại</td>
                            <td><strong><?= htmlspecialchars($u['phone'] ?? 'N/A') ?></strong></td>
                        </tr>
                        <tr>
                            <td><i class="bi bi-wallet2 me-2"></i>Số dư</td>
                            <td><strong class="text-success"><?= number_format((float)$u['balance'], 0, ',', '.') ?>đ</strong></td>
                        </tr>
                        <tr>
                            <td><i class="bi bi-calendar me-2"></i>Ngày đăng ký</td>
                            <td><?= date('d/m/Y', strtotime($u['created_at'])) ?></td>
                        </tr>
                        <tr>
                            <td><i class="bi bi-clock me-2"></i>Đăng nhập cuối</td>
                            <td><?= $u['last_login'] ? date('d/m/Y H:i', strtotime($u['last_login'])) : 'Chưa đăng nhập' ?></td>
                        </tr>
                        <tr>
                            <td><i class="bi bi-shield me-2"></i>Trạng thái</td>
                            <td>
                                <?php if ((int)$u['is_active'] === 1): ?>
                                    <span class="badge bg-success">Đang hoạt động</span>
                                <?php else: ?>
                                    <span class="badge bg-danger">Đã khóa</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
            
            <!-- Transaction Stats -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-cash-stack me-2"></i>Thống kê giao dịch</h5>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-2">
                        <span>Tổng nạp:</span>
                        <strong class="text-success">+<?= number_format((float)($transStats['total_topup'] ?? 0), 0, ',', '.') ?>đ</strong>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Tổng chi:</span>
                        <strong class="text-danger">-<?= number_format((float)($transStats['total_spent'] ?? 0), 0, ',', '.') ?>đ</strong>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span>Số giao dịch:</span>
                        <strong><?= $transStats['total_trans'] ?? 0 ?></strong>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Right Column: Posts -->
        <div class="col-lg-8">
            
            <!-- Filter Buttons (JS-based) -->
            <div class="row mb-3">
                <div class="col">
                    <button type="button" class="btn btn-primary w-100 filter-btn active" data-status="all">
                        <h5 class="mb-0"><?= (int)($postStats['total'] ?? 0) ?></h5>
                        <small>Tất cả</small>
                    </button>
                </div>
                <div class="col">
                    <button type="button" class="btn btn-outline-warning w-100 filter-btn" data-status="PENDING">
                        <h5 class="mb-0"><?= (int)($postStats['pending'] ?? 0) ?></h5>
                        <small>Chờ duyệt</small>
                    </button>
                </div>
                <div class="col">
                    <button type="button" class="btn btn-outline-success w-100 filter-btn" data-status="APPROVED">
                        <h5 class="mb-0"><?= (int)($postStats['approved'] ?? 0) ?></h5>
                        <small>Đang hiển thị</small>
                    </button>
                </div>
                <div class="col">
                    <button type="button" class="btn btn-outline-danger w-100 filter-btn" data-status="REJECTED">
                        <h5 class="mb-0"><?= (int)($postStats['rejected'] ?? 0) ?></h5>
                        <small>Từ chối</small>
                    </button>
                </div>
            </div>
            
            <!-- Posts List -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="bi bi-file-earmark-text me-2"></i>Tin đăng của <?= htmlspecialchars($u['full_name']) ?>
                        <span id="currentFilter" class="badge bg-secondary ms-2">Tất cả</span>
                    </h5>
                </div>
                <div class="card-body">
                    <?php if ($posts && mysqli_num_rows($posts) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover" id="postsTable">
                                <thead>
                                    <tr>
                                        <th style="width: 70px;">Ảnh</th>
                                        <th>Tin đăng</th>
                                        <th>Gói tin</th>
                                        <th>Giá</th>
                                        <th>Trạng thái</th>
                                        <th>Ngày tạo</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($post = mysqli_fetch_assoc($posts)): ?>
                                        <tr data-status="<?= htmlspecialchars($post['status']) ?>">
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
                                                <strong><?= htmlspecialchars(mb_substr($post['title'], 0, 40)) ?>...</strong>
                                                <br>
                                                <small class="text-muted"><?= htmlspecialchars($post['post_code']) ?></small>
                                            </td>
                                            <td>
                                                <span class="badge" style="background-color: <?= $post['highlight_color'] ?: '#6c757d' ?>;">
                                                    <?= htmlspecialchars($post['package_name']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <strong class="text-danger"><?= number_format((float)$post['price'], 0, ',', '.') ?>đ</strong>
                                            </td>
                                            <td>
                                                <?php
                                                $statusMap = [
                                                    'PENDING' => '<span class="badge bg-warning">Chờ duyệt</span>',
                                                    'APPROVED' => '<span class="badge bg-success">Đang hiển thị</span>',
                                                    'REJECTED' => '<span class="badge bg-danger">Từ chối</span>',
                                                    'EXPIRED' => '<span class="badge bg-secondary">Hết hạn</span>',
                                                    'HIDDEN' => '<span class="badge bg-dark">Bị ẩn</span>',
                                                ];
                                                echo $statusMap[$post['status']] ?? $post['status'];
                                                ?>
                                            </td>
                                            <td>
                                                <small><?= date('d/m/Y', strtotime($post['created_at'])) ?></small>
                                            </td>
                                            <td>
                                                <a href="<?= ADMIN_BASE_PATH ?>/modules/posts/review.php?id=<?= $post['post_id'] ?>" 
                                                   class="btn btn-sm btn-outline-info" title="Xem chi tiết">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                        <div id="noResults" class="text-center py-4 d-none">
                            <i class="bi bi-inbox fs-1 text-muted d-block mb-2"></i>
                            <p class="text-muted">Không có tin đăng nào ở trạng thái này</p>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="bi bi-inbox fs-1 text-muted d-block mb-2"></i>
                            <p class="text-muted">Chủ trọ này chưa có tin đăng nào</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
    </div>
    
    <div class="mt-3">
        <a href="<?= ADMIN_BASE_PATH ?>/modules/users/index.php" class="btn btn-secondary">
            <i class="bi bi-arrow-left me-1"></i>Quay lại danh sách
        </a>
    </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const filterBtns = document.querySelectorAll('.filter-btn');
    const tableRows = document.querySelectorAll('#postsTable tbody tr');
    const currentFilter = document.getElementById('currentFilter');
    const noResults = document.getElementById('noResults');
    const tableBody = document.querySelector('#postsTable tbody');
    
    const filterLabels = {
        'all': 'Tất cả',
        'PENDING': 'Chờ duyệt',
        'APPROVED': 'Đang hiển thị',
        'REJECTED': 'Từ chối'
    };
    
    filterBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const status = this.dataset.status;
            
            // Update active button
            filterBtns.forEach(b => {
                b.classList.remove('active', 'btn-primary', 'btn-warning', 'btn-success', 'btn-danger');
                b.classList.add('btn-outline-' + getColor(b.dataset.status));
            });
            this.classList.remove('btn-outline-' + getColor(status));
            this.classList.add('active', 'btn-' + getColor(status));
            
            // Update label
            currentFilter.textContent = filterLabels[status] || status;
            
            // Filter rows
            let visibleCount = 0;
            tableRows.forEach(row => {
                if (status === 'all' || row.dataset.status === status) {
                    row.style.display = '';
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                }
            });
            
            // Show/hide no results message
            if (noResults) {
                if (visibleCount === 0) {
                    noResults.classList.remove('d-none');
                    if (tableBody) tableBody.closest('table').classList.add('d-none');
                } else {
                    noResults.classList.add('d-none');
                    if (tableBody) tableBody.closest('table').classList.remove('d-none');
                }
            }
        });
    });
    
    function getColor(status) {
        const colors = {
            'all': 'primary',
            'PENDING': 'warning',
            'APPROVED': 'success',
            'REJECTED': 'danger'
        };
        return colors[status] || 'secondary';
    }
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
