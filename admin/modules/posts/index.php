<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/pagination.php';

$role = (string)($_SESSION['role_name'] ?? '');
if ($role !== 'ADMIN') {
    header('Location: ' . ADMIN_BASE_PATH . '/index.php');
    exit;
}

// Filters
$status = (string)($_GET['status'] ?? '');
$q = mysqli_real_escape_string($conn, (string)($_GET['q'] ?? ''));

// Stats
$stats = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'APPROVED' THEN 1 ELSE 0 END) as approved,
        SUM(CASE WHEN status = 'PENDING' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'REJECTED' THEN 1 ELSE 0 END) as rejected,
        SUM(CASE WHEN status = 'EXPIRED' THEN 1 ELSE 0 END) as expired,
        SUM(CASE WHEN status = 'HIDDEN' THEN 1 ELSE 0 END) as hidden
    FROM posts
"));

// Build WHERE for counting and query
$where = "1=1";
if ($status && in_array($status, ['APPROVED', 'PENDING', 'REJECTED', 'EXPIRED', 'HIDDEN'], true)) {
    $where .= " AND p.status = '$status'";
}
if ($q !== '') {
    $where .= " AND (p.title LIKE '%$q%' OR p.post_code LIKE '%$q%' OR u.full_name LIKE '%$q%')";
}

// Count for pagination
$countSql = "SELECT COUNT(*) as cnt FROM posts p JOIN users u ON u.user_id = p.user_id WHERE $where";
$countRow = mysqli_fetch_assoc(mysqli_query($conn, $countSql));
$totalItems = (int)($countRow['cnt'] ?? 0);

// Pagination
$perPage = 10;
$paging = pagination_calc($totalItems, $perPage);

// Build query with LIMIT
$sql = "SELECT p.*, u.full_name as landlord_name, u.phone as landlord_phone,
        pk.package_name, pk.highlight_color,
        d.district_name, pr.province_name,
        (SELECT image_path FROM post_images WHERE post_id = p.post_id AND is_primary = 1 LIMIT 1) as primary_image
        FROM posts p
        JOIN users u ON u.user_id = p.user_id
        JOIN packages pk ON pk.package_id = p.package_id
        JOIN districts d ON d.district_id = p.district_id
        JOIN provinces pr ON pr.province_id = d.province_id
        WHERE $where
        ORDER BY p.created_at DESC
        LIMIT {$paging['offset']}, {$paging['per_page']}";
$posts = mysqli_query($conn, $sql);

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="pagetitle">
    <h1><i class="bi bi-file-earmark-text me-2"></i>Tất cả tin đăng</h1>
    <nav>
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= ADMIN_BASE_PATH ?>/index.php">Dashboard</a></li>
            <li class="breadcrumb-item active">Tất cả tin đăng</li>
        </ol>
    </nav>
</div>

<section class="section">
    
    <!-- Stats Cards -->
    <div class="row mb-3">
        <div class="col">
            <a href="?" class="card text-center text-decoration-none <?= $status === '' ? 'border-primary border-2' : '' ?>">
                <div class="card-body py-3">
                    <h4 class="text-primary mb-0"><?= (int)($stats['total'] ?? 0) ?></h4>
                    <small>Tất cả</small>
                </div>
            </a>
        </div>
        <div class="col">
            <a href="?status=APPROVED" class="card text-center text-decoration-none <?= $status === 'APPROVED' ? 'border-success border-2' : '' ?>">
                <div class="card-body py-3">
                    <h4 class="text-success mb-0"><?= (int)($stats['approved'] ?? 0) ?></h4>
                    <small>Đang hiển thị</small>
                </div>
            </a>
        </div>
        <div class="col">
            <a href="?status=PENDING" class="card text-center text-decoration-none <?= $status === 'PENDING' ? 'border-warning border-2' : '' ?>">
                <div class="card-body py-3">
                    <h4 class="text-warning mb-0"><?= (int)($stats['pending'] ?? 0) ?></h4>
                    <small>Chờ duyệt</small>
                </div>
            </a>
        </div>
        <div class="col">
            <a href="?status=REJECTED" class="card text-center text-decoration-none <?= $status === 'REJECTED' ? 'border-danger border-2' : '' ?>">
                <div class="card-body py-3">
                    <h4 class="text-danger mb-0"><?= (int)($stats['rejected'] ?? 0) ?></h4>
                    <small>Bị từ chối</small>
                </div>
            </a>
        </div>
        <div class="col">
            <a href="?status=EXPIRED" class="card text-center text-decoration-none <?= $status === 'EXPIRED' ? 'border-secondary border-2' : '' ?>">
                <div class="card-body py-3">
                    <h4 class="text-secondary mb-0"><?= (int)($stats['expired'] ?? 0) ?></h4>
                    <small>Hết hạn</small>
                </div>
            </a>
        </div>
        <div class="col">
            <a href="?status=HIDDEN" class="card text-center text-decoration-none <?= $status === 'HIDDEN' ? 'border-dark border-2' : '' ?>">
                <div class="card-body py-3">
                    <h4 class="text-dark mb-0"><?= (int)($stats['hidden'] ?? 0) ?></h4>
                    <small>Bị ẩn</small>
                </div>
            </a>
        </div>
    </div>
    
    <!-- Filter -->
    <div class="card mb-3">
        <div class="card-body py-2">
            <form method="get" class="row g-2 align-items-center">
                <div class="col-md-3">
                    <select name="status" class="form-select form-select-sm">
                        <option value="">-- Tất cả trạng thái --</option>
                        <option value="APPROVED" <?= $status === 'APPROVED' ? 'selected' : '' ?>>Đang hiển thị</option>
                        <option value="PENDING" <?= $status === 'PENDING' ? 'selected' : '' ?>>Chờ duyệt</option>
                        <option value="REJECTED" <?= $status === 'REJECTED' ? 'selected' : '' ?>>Bị từ chối</option>
                        <option value="EXPIRED" <?= $status === 'EXPIRED' ? 'selected' : '' ?>>Hết hạn</option>
                        <option value="HIDDEN" <?= $status === 'HIDDEN' ? 'selected' : '' ?>>Bị ẩn</option>
                    </select>
                </div>
                <div class="col-md-5">
                    <input type="text" name="q" class="form-control form-control-sm" 
                           placeholder="Tìm theo tiêu đề, mã tin, tên chủ trọ..."
                           value="<?= htmlspecialchars($_GET['q'] ?? '') ?>">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary btn-sm w-100">
                        <i class="bi bi-search me-1"></i>Lọc
                    </button>
                </div>
                <?php if ($status || $q): ?>
                    <div class="col-md-2">
                        <a href="?" class="btn btn-outline-secondary btn-sm w-100">Xóa bộ lọc</a>
                    </div>
                <?php endif; ?>
            </form>
        </div>
    </div>
    
    <!-- Posts Table -->
    <div class="card">
        <div class="card-body">
            <?php if ($posts && mysqli_num_rows($posts) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th style="width: 80px;">Ảnh</th>
                                <th>Tin đăng</th>
                                <th>Chủ trọ</th>
                                <th>Gói tin</th>
                                <th>Giá</th>
                                <th>Trạng thái</th>
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
                                        <strong><?= htmlspecialchars(mb_substr($post['title'], 0, 50)) ?>...</strong>
                                        <br>
                                        <small class="text-muted">
                                            <i class="bi bi-geo-alt"></i> <?= htmlspecialchars($post['district_name']) ?>, <?= htmlspecialchars($post['province_name']) ?>
                                        </small>
                                        <br>
                                        <small class="text-muted"><?= htmlspecialchars($post['post_code']) ?></small>
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
                                    </td>
                                    <td>
                                        <strong class="text-danger"><?= number_format((float)$post['price'], 0, ',', '.') ?>đ</strong>
                                    </td>
                                    <td>
                                        <?php
                                        $statusMap = [
                                            'PENDING' => '<span class="badge bg-warning">Chờ duyệt</span>',
                                            'APPROVED' => '<span class="badge bg-success">Đang hiển thị</span>',
                                            'REJECTED' => '<span class="badge bg-danger">Bị từ chối</span>',
                                            'EXPIRED' => '<span class="badge bg-secondary">Hết hạn</span>',
                                            'HIDDEN' => '<span class="badge bg-dark">Bị ẩn</span>',
                                        ];
                                        echo $statusMap[$post['status']] ?? $post['status'];
                                        ?>
                                        <?php if ($post['status'] === 'REJECTED' && $post['rejection_reason']): ?>
                                            <br>
                                            <a href="#" class="text-danger small" data-bs-toggle="modal" 
                                               data-bs-target="#reasonModal<?= $post['post_id'] ?>">
                                                <i class="bi bi-info-circle"></i> Xem lý do
                                            </a>
                                            <!-- Modal for rejection reason -->
                                            <div class="modal fade" id="reasonModal<?= $post['post_id'] ?>" tabindex="-1">
                                                <div class="modal-dialog modal-dialog-centered">
                                                    <div class="modal-content">
                                                        <div class="modal-header bg-danger text-white">
                                                            <h5 class="modal-title"><i class="bi bi-x-circle me-2"></i>Lý do từ chối</h5>
                                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <p class="mb-2"><strong>Tin:</strong> <?= htmlspecialchars($post['title']) ?></p>
                                                            <p class="mb-2"><strong>Mã tin:</strong> <?= htmlspecialchars($post['post_code']) ?></p>
                                                            <hr>
                                                            <p class="mb-0"><strong>Lý do:</strong></p>
                                                            <p class="text-danger"><?= nl2br(htmlspecialchars($post['rejection_reason'])) ?></p>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?= date('d/m/Y', strtotime($post['created_at'])) ?>
                                        <br>
                                        <small class="text-muted"><?= date('H:i', strtotime($post['created_at'])) ?></small>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="<?= ADMIN_BASE_PATH ?>/modules/posts/review.php?id=<?= $post['post_id'] ?>" 
                                               class="btn btn-outline-info" title="Xem chi tiết">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            <?php if ($post['status'] === 'PENDING'): ?>
                                                <a href="<?= ADMIN_BASE_PATH ?>/modules/posts/approve.php?id=<?= $post['post_id'] ?>" 
                                                   class="btn btn-outline-success" title="Duyệt"
                                                   onclick="return confirm('Duyệt tin này?')">
                                                    <i class="bi bi-check-lg"></i>
                                                </a>
                                            <?php endif; ?>
                                            <?php if ($post['status'] === 'APPROVED'): ?>
                                                <a href="<?= ADMIN_BASE_PATH ?>/modules/posts/hide.php?id=<?= $post['post_id'] ?>" 
                                                   class="btn btn-outline-warning" title="Ẩn tin"
                                                   onclick="return confirm('Ẩn tin này?')">
                                                    <i class="bi bi-eye-slash"></i>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                <?php pagination_render($paging['current_page'], $paging['total_pages'], $paging['total_items'], $paging['per_page']); ?>
            <?php else: ?>
                <div class="text-center py-5">
                    <i class="bi bi-inbox fs-1 text-muted d-block mb-3"></i>
                    <p class="text-muted">Không có tin đăng nào<?= $status || $q ? ' phù hợp với bộ lọc' : '' ?></p>
                    <?php if ($status || $q): ?>
                        <a href="?" class="btn btn-outline-secondary">Xóa bộ lọc</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
</section>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
