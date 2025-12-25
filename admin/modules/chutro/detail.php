<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';

$role = (string)($_SESSION['role_name'] ?? '');
if (!in_array($role, ['ADMIN', 'STAFF'], true)) {
    header('Location: ' . ADMIN_BASE_PATH . '/index.php');
    exit;
}

$userId = (int)($_GET['user_id'] ?? 0);
if ($userId <= 0) {
    header('Location: ' . ADMIN_BASE_PATH . '/modules/chutro/index.php');
    exit;
}

// Get landlord info
$sql = "SELECT u.*, r.role_name
        FROM users u
        JOIN roles r ON r.role_id = u.role_id
        WHERE u.user_id = ?
        LIMIT 1";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $userId);
mysqli_stmt_execute($stmt);
$rs = mysqli_stmt_get_result($stmt);
$u = $rs ? mysqli_fetch_assoc($rs) : null;
mysqli_stmt_close($stmt);

if (!$u) {
    header('Location: ' . ADMIN_BASE_PATH . '/modules/chutro/index.php?err=not_found');
    exit;
}

// Get post statistics
$postStats = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'PENDING' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'APPROVED' THEN 1 ELSE 0 END) as approved,
        SUM(CASE WHEN status = 'REJECTED' THEN 1 ELSE 0 END) as rejected
    FROM posts WHERE user_id = $userId
"));

// Get transaction statistics
$transStats = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT 
        COUNT(*) as total_trans,
        SUM(CASE WHEN transaction_type IN ('TOPUP','DEPOSIT','DEPOSIT_RECEIVED') THEN amount ELSE 0 END) as total_in,
        SUM(CASE WHEN transaction_type IN ('WITHDRAWAL','POST_NEW','POST_EXTEND','POST_RESUBMIT') THEN ABS(amount) ELSE 0 END) as total_out
    FROM transactions WHERE user_id = $userId
"));

// Get posts list
$posts = mysqli_query($conn, "
    SELECT p.*, pk.package_name, pk.highlight_color,
           (SELECT image_path FROM post_images WHERE post_id = p.post_id AND is_primary = 1 LIMIT 1) as primary_image
    FROM posts p
    JOIN packages pk ON pk.package_id = p.package_id
    WHERE p.user_id = $userId
    ORDER BY p.created_at DESC
    LIMIT 10
");

// Transaction history
$transactions = mysqli_query($conn, "
    SELECT * FROM transactions 
    WHERE user_id = $userId 
    ORDER BY created_at DESC 
    LIMIT 15
");

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="pagetitle">
    <h1><i class="bi bi-person-circle me-2"></i>Chi tiết chủ trọ</h1>
    <nav>
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= ADMIN_BASE_PATH ?>/index.php">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="<?= ADMIN_BASE_PATH ?>/modules/chutro/index.php">Chủ trọ</a></li>
            <li class="breadcrumb-item active"><?= htmlspecialchars($u['full_name']) ?></li>
        </ol>
    </nav>
</div>

<section class="section">
    <div class="row">
        
        <!-- Left: Profile -->
        <div class="col-lg-4 col-md-5">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h6 class="mb-0"><i class="bi bi-person me-2"></i>Thông tin tài khoản</h6>
                </div>
                <div class="card-body text-center">
                    <?php if ($u['avatar']): ?>
                        <img src="<?= ADMIN_BASE_PATH ?>/uploads/avatars/<?= htmlspecialchars($u['avatar']) ?>" 
                             alt="" class="rounded-circle mb-2" style="width: 80px; height: 80px; object-fit: cover;">
                    <?php else: ?>
                        <div class="rounded-circle bg-secondary d-inline-flex align-items-center justify-content-center mb-2" 
                             style="width: 80px; height: 80px;">
                            <i class="bi bi-person text-white fs-1"></i>
                        </div>
                    <?php endif; ?>
                    <h5 class="mb-0"><?= htmlspecialchars($u['full_name']) ?></h5>
                    <small class="text-muted">@<?= htmlspecialchars($u['username']) ?></small>
                    
                    <hr>
                    
                    <div class="text-start">
                        <p class="mb-1"><i class="bi bi-envelope me-2 text-muted"></i><?= htmlspecialchars($u['email']) ?></p>
                        <p class="mb-1"><i class="bi bi-phone me-2 text-muted"></i><?= htmlspecialchars($u['phone'] ?? 'N/A') ?></p>
                        <p class="mb-1"><i class="bi bi-wallet2 me-2 text-muted"></i><strong class="text-success"><?= number_format((float)$u['balance'], 0, ',', '.') ?>đ</strong></p>
                        <p class="mb-1"><i class="bi bi-calendar me-2 text-muted"></i><?= date('d/m/Y', strtotime($u['created_at'])) ?></p>
                        <p class="mb-0">
                            <i class="bi bi-shield me-2 text-muted"></i>
                            <?= (int)$u['is_active'] === 1 
                                ? '<span class="badge bg-success">Hoạt động</span>' 
                                : '<span class="badge bg-danger">Đã khóa</span>' ?>
                        </p>
                    </div>
                </div>
            </div>
            
            <!-- Stats Cards -->
            <div class="row g-2 mb-3">
                <div class="col-6">
                    <div class="card bg-success text-white text-center py-2">
                        <small class="opacity-75">Tổng thu</small>
                        <strong><?= number_format((float)($transStats['total_in'] ?? 0), 0, ',', '.') ?>đ</strong>
                    </div>
                </div>
                <div class="col-6">
                    <div class="card bg-danger text-white text-center py-2">
                        <small class="opacity-75">Tổng chi</small>
                        <strong><?= number_format((float)($transStats['total_out'] ?? 0), 0, ',', '.') ?>đ</strong>
                    </div>
                </div>
            </div>
            
            <a href="<?= ADMIN_BASE_PATH ?>/modules/chutro/index.php" class="btn btn-outline-secondary w-100">
                <i class="bi bi-arrow-left me-1"></i>Quay lại
            </a>
        </div>
        
        <!-- Right: Content -->
        <div class="col-lg-8 col-md-7">
            
            <!-- Tabs -->
            <ul class="nav nav-tabs" role="tablist">
                <li class="nav-item">
                    <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-posts">
                        <i class="bi bi-file-text me-1"></i>Tin đăng
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-transactions">
                        <i class="bi bi-clock-history me-1"></i>Giao dịch
                    </button>
                </li>
            </ul>
            
            <div class="tab-content">
                <!-- Posts Tab -->
                <div class="tab-pane fade show active" id="tab-posts">
                    <div class="card border-top-0 rounded-top-0">
                        <div class="card-body p-0">
                            <?php if ($posts && mysqli_num_rows($posts) > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th style="width:60px">Ảnh</th>
                                            <th>Tin đăng</th>
                                            <th>Giá</th>
                                            <th>Trạng thái</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($post = mysqli_fetch_assoc($posts)): ?>
                                        <tr>
                                            <td>
                                                <?php if ($post['primary_image']): ?>
                                                <img src="/quanlyphongtro/uploads/posts/<?= htmlspecialchars($post['primary_image']) ?>" 
                                                     class="rounded" style="width:50px;height:38px;object-fit:cover">
                                                <?php else: ?>
                                                <div class="bg-secondary rounded d-flex align-items-center justify-content-center" style="width:50px;height:38px">
                                                    <i class="bi bi-image text-white"></i>
                                                </div>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <strong><?= htmlspecialchars(mb_substr($post['title'], 0, 30)) ?>...</strong><br>
                                                <small class="text-muted"><?= $post['post_code'] ?></small>
                                            </td>
                                            <td><strong class="text-danger"><?= number_format((float)$post['price'], 0, ',', '.') ?>đ</strong></td>
                                            <td>
                                                <?php
                                                $statusMap = [
                                                    'PENDING' => ['Chờ duyệt', 'warning'],
                                                    'APPROVED' => ['Đang hiển thị', 'success'],
                                                    'REJECTED' => ['Từ chối', 'danger'],
                                                    'EXPIRED' => ['Hết hạn', 'secondary']
                                                ];
                                                $st = $statusMap[$post['status']] ?? [$post['status'], 'secondary'];
                                                ?>
                                                <span class="badge bg-<?= $st[1] ?>"><?= $st[0] ?></span>
                                            </td>
                                            <td>
                                                <a href="<?= ADMIN_BASE_PATH ?>/modules/posts/review.php?id=<?= $post['post_id'] ?>" 
                                                   class="btn btn-sm btn-outline-info"><i class="bi bi-eye"></i></a>
                                            </td>
                                        </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php else: ?>
                            <div class="text-center py-4 text-muted">
                                <i class="bi bi-inbox fs-1 d-block mb-2"></i>Chưa có tin đăng
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Transactions Tab -->
                <div class="tab-pane fade" id="tab-transactions">
                    <div class="card border-top-0 rounded-top-0">
                        <div class="card-body p-0">
                            <?php if ($transactions && mysqli_num_rows($transactions) > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Thời gian</th>
                                            <th>Loại</th>
                                            <th>Mô tả</th>
                                            <th class="text-end">Số tiền</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $typeLabels = [
                                            'TOPUP' => ['Nạp tiền', 'success'],
                                            'DEPOSIT' => ['Nạp tiền', 'success'],
                                            'DEPOSIT_RECEIVED' => ['Thu từ SV', 'success'],
                                            'WITHDRAWAL' => ['Rút tiền', 'danger'],
                                            'REFUND' => ['Hoàn tiền', 'info'],
                                            'POST_NEW' => ['Đăng tin', 'warning'],
                                            'POST_EXTEND' => ['Gia hạn', 'primary'],
                                        ];
                                        while ($t = mysqli_fetch_assoc($transactions)): 
                                            $label = $typeLabels[$t['transaction_type']] ?? [$t['transaction_type'], 'secondary'];
                                            $amt = (float)$t['amount'];
                                        ?>
                                        <tr>
                                            <td><?= date('d/m H:i', strtotime($t['created_at'])) ?></td>
                                            <td><span class="badge bg-<?= $label[1] ?>"><?= $label[0] ?></span></td>
                                            <td><small class="text-muted"><?= htmlspecialchars(mb_substr($t['description'] ?? '', 0, 35)) ?></small></td>
                                            <td class="text-end">
                                                <strong class="<?= $amt > 0 ? 'text-success' : 'text-danger' ?>">
                                                    <?= $amt > 0 ? '+' : '' ?><?= number_format($amt, 0, ',', '.') ?>đ
                                                </strong>
                                            </td>
                                        </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php else: ?>
                            <div class="text-center py-4 text-muted">
                                <i class="bi bi-inbox fs-1 d-block mb-2"></i>Chưa có giao dịch
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
