<?php
/**
 * Báo cáo Hoa hồng Platform - Admin
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/platform.php';

$role = $_SESSION['role_name'] ?? '';
if ($role !== 'ADMIN') {
    header('Location: /quanlyphongtro/admin/index.php?error=no_permission');
    exit;
}

// Filters
$dateFrom = $_GET['from'] ?? date('Y-m-01'); // Đầu tháng
$dateTo = $_GET['to'] ?? date('Y-m-d'); // Hôm nay

// Thống kê tổng quan
$totalStats = @mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT 
        COALESCE(SUM(commission_amount), 0) as total_commission,
        COALESCE(SUM(ABS(amount)), 0) as total_net,
        COALESCE(SUM(ABS(amount) + commission_amount), 0) as total_gross,
        COUNT(*) as total_transactions
    FROM transactions 
    WHERE commission_amount > 0 
    AND DATE(created_at) BETWEEN '$dateFrom' AND '$dateTo'
"));

// Thống kê theo ngày
$dailyStats = @mysqli_query($conn, "
    SELECT 
        DATE(created_at) as date,
        SUM(commission_amount) as commission,
        SUM(amount) as net_amount,
        COUNT(*) as count
    FROM transactions 
    WHERE commission_amount > 0 
    AND DATE(created_at) BETWEEN '$dateFrom' AND '$dateTo'
    GROUP BY DATE(created_at)
    ORDER BY date DESC
");

// Chi tiết giao dịch
$transactions = @mysqli_query($conn, "
    SELECT t.*, u.full_name
    FROM transactions t
    LEFT JOIN users u ON u.user_id = t.user_id
    WHERE t.commission_amount > 0 
    AND DATE(t.created_at) BETWEEN '$dateFrom' AND '$dateTo'
    ORDER BY t.created_at DESC
    LIMIT 50
");

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="pagetitle">
    <h1><i class="bi bi-percent me-2"></i>Báo cáo Hoa hồng</h1>
    <nav>
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= ADMIN_BASE_PATH ?>/index.php">Dashboard</a></li>
            <li class="breadcrumb-item active">Hoa hồng</li>
        </ol>
    </nav>
</div>

<!-- Filter -->
<div class="card mb-3">
    <div class="card-body py-3">
        <form method="get" class="row g-2 align-items-end">
            <div class="col-auto">
                <label class="form-label small">Từ ngày</label>
                <input type="date" name="from" class="form-control" value="<?= $dateFrom ?>">
            </div>
            <div class="col-auto">
                <label class="form-label small">Đến ngày</label>
                <input type="date" name="to" class="form-control" value="<?= $dateTo ?>">
            </div>
            <div class="col-auto">
                <button class="btn btn-primary"><i class="bi bi-search me-1"></i>Xem</button>
            </div>
            <div class="col-auto">
                <a href="?from=<?= date('Y-m-01') ?>&to=<?= date('Y-m-d') ?>" class="btn btn-outline-secondary">Tháng này</a>
            </div>
            <div class="col-auto">
                <a href="?from=<?= date('Y-m-d', strtotime('-7 days')) ?>&to=<?= date('Y-m-d') ?>" class="btn btn-outline-secondary">7 ngày</a>
            </div>
            <div class="col-auto">
                <a href="?" class="btn btn-outline-danger"><i class="bi bi-x-circle me-1"></i>Xóa lọc</a>
            </div>
        </form>
    </div>
</div>

<!-- Stats Cards -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card bg-success text-white">
            <div class="card-body py-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="opacity-75 small">Hoa hồng thu được</div>
                        <div class="fs-3 fw-bold"><?= number_format((float)($totalStats['total_commission'] ?? 0)) ?>đ</div>
                    </div>
                    <i class="bi bi-currency-dollar fs-1 opacity-50"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-primary text-white">
            <div class="card-body py-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="opacity-75 small">Tổng giao dịch</div>
                        <div class="fs-3 fw-bold"><?= number_format((float)($totalStats['total_gross'] ?? 0)) ?>đ</div>
                    </div>
                    <i class="bi bi-graph-up-arrow fs-1 opacity-50"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-info text-white">
            <div class="card-body py-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="opacity-75 small">Chủ trọ nhận</div>
                        <div class="fs-3 fw-bold"><?= number_format((float)($totalStats['total_net'] ?? 0)) ?>đ</div>
                    </div>
                    <i class="bi bi-people fs-1 opacity-50"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-warning text-dark">
            <div class="card-body py-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="small">Số giao dịch</div>
                        <div class="fs-3 fw-bold"><?= (int)($totalStats['total_transactions'] ?? 0) ?></div>
                    </div>
                    <i class="bi bi-receipt fs-1 opacity-50"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Thống kê theo ngày -->
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-calendar3 me-2"></i>Theo ngày</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive" style="max-height: 400px;">
                    <table class="table table-sm table-hover mb-0">
                        <thead class="table-light sticky-top">
                            <tr>
                                <th>Ngày</th>
                                <th class="text-end">Hoa hồng</th>
                                <th class="text-center">SL</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $hasDaily = false; while ($dailyStats && $d = mysqli_fetch_assoc($dailyStats)): $hasDaily = true; ?>
                            <tr>
                                <td><?= date('d/m', strtotime($d['date'])) ?></td>
                                <td class="text-end text-success fw-bold"><?= number_format($d['commission']) ?>đ</td>
                                <td class="text-center"><span class="badge bg-secondary"><?= $d['count'] ?></span></td>
                            </tr>
                            <?php endwhile; ?>
                            <?php if (!$hasDaily): ?>
                            <tr><td colspan="3" class="text-center text-muted py-3">Không có dữ liệu</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Chi tiết giao dịch -->
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-list-ul me-2"></i>Chi tiết giao dịch</h5>
                <span class="badge bg-success"><?= PLATFORM_COMMISSION_RATE ?>% hoa hồng</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive" style="max-height: 400px;">
                    <table class="table table-sm table-hover mb-0">
                        <thead class="table-light sticky-top">
                            <tr>
                                <th>Thời gian</th>
                                <th>Chủ trọ</th>
                                <th>Mô tả</th>
                                <th class="text-end">Gốc</th>
                                <th class="text-end">Hoa hồng</th>
                                <th class="text-end">Thực nhận</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $hasTxn = false; while ($transactions && $t = mysqli_fetch_assoc($transactions)): $hasTxn = true; 
                                $gross = $t['amount'] + $t['commission_amount'];
                            ?>
                            <tr>
                                <td>
                                    <small><?= date('d/m H:i', strtotime($t['created_at'])) ?></small>
                                </td>
                                <td><small><?= htmlspecialchars($t['full_name'] ?? 'N/A') ?></small></td>
                                <td>
                                    <small class="text-muted" style="max-width: 200px; display: inline-block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                        <?= htmlspecialchars($t['description'] ?? '') ?>
                                    </small>
                                </td>
                                <td class="text-end"><?= number_format($gross) ?>đ</td>
                                <td class="text-end text-success fw-bold">+<?= number_format($t['commission_amount']) ?>đ</td>
                                <td class="text-end"><?= number_format($t['amount']) ?>đ</td>
                            </tr>
                            <?php endwhile; ?>
                            <?php if (!$hasTxn): ?>
                            <tr><td colspan="6" class="text-center text-muted py-4">
                                <i class="bi bi-inbox fs-1 d-block mb-2 opacity-50"></i>
                                Chưa có giao dịch nào có hoa hồng
                            </td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Info -->
<div class="card mt-3">
    <div class="card-body">
        <h6 class="card-title"><i class="bi bi-info-circle me-2"></i>Thông tin hoa hồng</h6>
        <div class="row">
            <div class="col-md-4">
                <p class="mb-1"><strong>Tỷ lệ hoa hồng:</strong> <?= PLATFORM_COMMISSION_RATE ?>%</p>
            </div>
            <div class="col-md-4">
                <p class="mb-1"><strong>Hoa hồng tối thiểu:</strong> <?= number_format(PLATFORM_MIN_COMMISSION) ?>đ</p>
            </div>
            <div class="col-md-4">
                <p class="mb-1"><strong>Áp dụng:</strong> Booking mới, Gia hạn hợp đồng</p>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
