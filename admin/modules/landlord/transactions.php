<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';

$role = (string)($_SESSION['role_name'] ?? '');
$userId = (int)($_SESSION['user_id'] ?? 0);

if ($role !== 'LANDLORD') {
    header('Location: ' . ADMIN_BASE_PATH . '/index.php');
    exit;
}

// Filter
$type = (string)($_GET['type'] ?? '');
$month = (string)($_GET['month'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 15;
$offset = ($page - 1) * $perPage;

// Build base WHERE
$where = "t.user_id = $userId";

if ($type && in_array($type, ['TOPUP','POST_NEW','POST_EXTEND','REFUND','POST_RESUBMIT','POST','DEPOSIT','DEPOSIT_RECEIVED','WITHDRAWAL'], true)) {
    $where .= " AND t.transaction_type = '$type'";
}

if ($month && preg_match('/^\d{4}-\d{2}$/', $month)) {
    $where .= " AND DATE_FORMAT(t.created_at, '%Y-%m') = '$month'";
}

// Count total for pagination
$countResult = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as cnt FROM transactions t WHERE $where"));
$totalRecords = (int)($countResult['cnt'] ?? 0);
$totalPages = max(1, ceil($totalRecords / $perPage));

// Get transactions with pagination
$sql = "SELECT t.*, p.post_code, p.title as post_title
        FROM transactions t
        LEFT JOIN posts p ON p.post_id = t.post_id
        WHERE $where
        ORDER BY t.created_at DESC
        LIMIT $perPage OFFSET $offset";
$transactions = mysqli_query($conn, $sql);

// Stats (all time)
$stats = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN transaction_type IN ('TOPUP','DEPOSIT','REFUND','DEPOSIT_RECEIVED') THEN amount ELSE 0 END) as total_topup,
        SUM(CASE WHEN transaction_type IN ('POST','POST_NEW','POST_EXTEND','POST_RESUBMIT') THEN ABS(amount) ELSE 0 END) as total_spent
    FROM transactions WHERE user_id = $userId
"));

// Get current balance
$userBalance = mysqli_fetch_assoc(mysqli_query($conn, "SELECT balance FROM users WHERE user_id = $userId"));
$balance = (float)($userBalance['balance'] ?? 0);

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="pagetitle">
    <h1><i class="bi bi-clock-history me-2"></i>Lịch sử giao dịch</h1>
    <nav>
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= ADMIN_BASE_PATH ?>/modules/landlord/dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item active">Lịch sử giao dịch</li>
        </ol>
    </nav>
</div>

<section class="section">
    
    <!-- Stats -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card text-center border-primary">
                <div class="card-body">
                    <h4 class="text-primary fw-bold"><?= number_format($balance, 0, ',', '.') ?>đ</h4>
                    <small>Số dư hiện tại</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <h4 class="text-secondary"><?= $stats['total'] ?? 0 ?></h4>
                    <small>Tổng giao dịch</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <h4 class="text-success">+<?= number_format((float)($stats['total_topup'] ?? 0), 0, ',', '.') ?>đ</h4>
                    <small>Tổng nạp</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <h4 class="text-danger">-<?= number_format((float)($stats['total_spent'] ?? 0), 0, ',', '.') ?>đ</h4>
                    <small>Tổng chi</small>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Filter -->
    <div class="card mb-3">
        <div class="card-body py-2">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <div class="d-flex gap-2 flex-wrap">
                        <a href="?<?= $month ? 'month='.$month : '' ?>" class="btn btn-sm <?= $type === '' ? 'btn-primary' : 'btn-outline-primary' ?>">Tất cả</a>
                        <a href="?type=TOPUP<?= $month ? '&month='.$month : '' ?>" class="btn btn-sm <?= $type === 'TOPUP' ? 'btn-success' : 'btn-outline-success' ?>">Nạp tiền</a>
                        <a href="?type=DEPOSIT_RECEIVED<?= $month ? '&month='.$month : '' ?>" class="btn btn-sm <?= $type === 'DEPOSIT_RECEIVED' ? 'btn-info' : 'btn-outline-info' ?>">Thanh toán</a>
                        <a href="?type=POST_NEW<?= $month ? '&month='.$month : '' ?>" class="btn btn-sm <?= $type === 'POST_NEW' ? 'btn-warning' : 'btn-outline-warning' ?>">Đăng tin</a>
                        <a href="?type=WITHDRAWAL<?= $month ? '&month='.$month : '' ?>" class="btn btn-sm <?= $type === 'WITHDRAWAL' ? 'btn-danger' : 'btn-outline-danger' ?>">Rút tiền</a>
                        <a href="?type=REFUND<?= $month ? '&month='.$month : '' ?>" class="btn btn-sm <?= $type === 'REFUND' ? 'btn-secondary' : 'btn-outline-secondary' ?>">Hoàn tiền</a>
                    </div>
                </div>
                <div class="col-md-4">
                    <form method="get" class="d-flex gap-2">
                        <?php if ($type): ?><input type="hidden" name="type" value="<?= htmlspecialchars($type) ?>"><?php endif; ?>
                        <input type="month" name="month" class="form-control form-control-sm" 
                               value="<?= htmlspecialchars($month) ?>"
                               max="<?= date('Y-m') ?>">
                        <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-filter"></i></button>
                        <?php if ($month): ?>
                            <a href="?<?= $type ? 'type='.$type : '' ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-x"></i></a>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Transactions -->
    <div class="card">
        <div class="card-body">
            <?php if ($transactions && mysqli_num_rows($transactions) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Thời gian</th>
                                <th>Loại giao dịch</th>
                                <th>Mô tả</th>
                                <th>Số tiền</th>
                                <th>Số dư</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($trans = mysqli_fetch_assoc($transactions)): ?>
                                <tr>
                                    <td>
                                        <?= date('d/m/Y', strtotime($trans['created_at'])) ?>
                                        <br>
                                        <small class="text-muted"><?= date('H:i:s', strtotime($trans['created_at'])) ?></small>
                                    </td>
                                    <td>
                                        <?php
                                        $typeLabels = [
                                            'TOPUP' => '<span class="badge bg-success">Nạp tiền</span>',
                                            'DEPOSIT' => '<span class="badge bg-success">Nạp tiền</span>',
                                            'DEPOSIT_RECEIVED' => '<span class="badge bg-info">Thanh toán</span>',
                                            'POST' => '<span class="badge bg-warning">Đăng tin</span>',
                                            'POST_NEW' => '<span class="badge bg-warning">Đăng tin</span>',
                                            'POST_EXTEND' => '<span class="badge bg-info">Gia hạn</span>',
                                            'REFUND' => '<span class="badge bg-primary">Hoàn tiền</span>',
                                            'POST_RESUBMIT' => '<span class="badge bg-secondary">Đăng lại</span>',
                                            'WITHDRAWAL' => '<span class="badge bg-danger">Rút tiền</span>'
                                        ];
                                        echo $typeLabels[$trans['transaction_type']] ?? '<span class="badge bg-secondary">' . htmlspecialchars($trans['transaction_type'] ?? 'N/A') . '</span>';
                                        ?>
                                    </td>
                                    <td>
                                        <?= htmlspecialchars($trans['description'] ?: 'N/A') ?>
                                        <?php if ($trans['post_code']): ?>
                                            <br><small class="text-muted"><?= htmlspecialchars($trans['post_code']) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td class="<?= (float)$trans['amount'] > 0 ? 'text-success' : 'text-danger' ?> fw-bold">
                                        <?= (float)$trans['amount'] > 0 ? '+' : '' ?><?= number_format((float)$trans['amount'], 0, ',', '.') ?>đ
                                    </td>
                                    <td>
                                        <small class="text-muted"><?= number_format((float)$trans['balance_before'], 0, ',', '.') ?>đ</small>
                                        → 
                                        <strong><?= number_format((float)$trans['balance_after'], 0, ',', '.') ?>đ</strong>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center py-5">
                    <i class="bi bi-inbox fs-1 text-muted d-block mb-3"></i>
                    <p class="text-muted">Chưa có giao dịch nào<?= $month ? ' trong tháng này' : '' ?></p>
                </div>
            <?php endif; ?>
            
            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <?php 
                $queryParams = [];
                if ($type) $queryParams['type'] = $type;
                if ($month) $queryParams['month'] = $month;
                $baseUrl = '?' . http_build_query($queryParams) . ($queryParams ? '&' : '');
                ?>
                <div class="d-flex justify-content-between align-items-center mt-3">
                    <small class="text-muted">Hiển thị <?= $offset + 1 ?>-<?= min($offset + $perPage, $totalRecords) ?> / <?= $totalRecords ?> giao dịch</small>
                    <nav>
                        <ul class="pagination pagination-sm mb-0">
                            <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="<?= $baseUrl ?>page=<?= $page - 1 ?>">‹</a>
                                </li>
                            <?php endif; ?>
                            
                            <?php 
                            $start = max(1, $page - 2);
                            $end = min($totalPages, $page + 2);
                            for ($i = $start; $i <= $end; $i++): 
                            ?>
                                <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                    <a class="page-link" href="<?= $baseUrl ?>page=<?= $i ?>"><?= $i ?></a>
                                </li>
                            <?php endfor; ?>
                            
                            <?php if ($page < $totalPages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="<?= $baseUrl ?>page=<?= $page + 1 ?>">›</a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
</section>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
