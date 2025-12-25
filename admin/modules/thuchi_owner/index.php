<?php
// Thu chi - Báo cáo thu chi cho chủ trọ
// Thu: Từ bookings đã thanh toán (DEPOSIT_PAID, CHECKED_IN, CHECKED_OUT)
// Chi: Tiền đăng tin (POST, REPOST từ transactions)
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';

$role = $_SESSION['role_name'] ?? '';
$user_id = (int)($_SESSION['user_id'] ?? 0);

if ($role !== 'LANDLORD' || $user_id <= 0) {
    header('Location: /quanlyphongtro/admin/index.php?error=no_permission');
    exit;
}

// Lấy danh sách buildings của chủ trọ
$buildingsRs = mysqli_query($conn, "SELECT building_id, building_name FROM buildings WHERE owner_id = $user_id ORDER BY building_name");
$buildings = [];
while ($b = mysqli_fetch_assoc($buildingsRs)) {
    $buildings[] = $b;
}

// Filter
$filterMonth = trim($_GET['month'] ?? date('Y-m'));
$filterBuilding = (int)($_GET['building_id'] ?? 0);

// Date range
$monthStart = $filterMonth . '-01';
$monthEnd = date('Y-m-t', strtotime($monthStart));
$monthLabel = date('m/Y', strtotime($monthStart));

$buildingFilter = $filterBuilding ? "AND b.building_id = $filterBuilding" : "";

// =====================================================
// TỔNG THU: Từ bookings đã thanh toán trong tháng
// (Khách hàng đã chuyển tiền cọc/thuê)
// =====================================================

// Debug mode - set to false for production
$debugMode = false;
$debugInfo = [];

// Cột giá phòng trong database là base_rent (không phải price)
$priceField = 'r.base_rent';
$debugInfo['price_field'] = $priceField;

// Query thu - dùng deposit_amount (số tiền thực thanh toán)
$incomeQuery = "
    SELECT COALESCE(SUM(bk.deposit_amount), 0) AS total, COUNT(*) AS cnt
    FROM bookings bk
    JOIN rooms r ON r.room_id = bk.room_id
    JOIN buildings b ON b.building_id = r.building_id
    WHERE b.owner_id = $user_id
      AND bk.status IN ('DEPOSIT_PAID', 'CHECKED_IN', 'CHECKED_OUT')
      AND DATE(bk.created_at) BETWEEN '$monthStart' AND '$monthEnd'
      $buildingFilter
";
$debugInfo['income_query'] = $incomeQuery;

$incomeRs = mysqli_query($conn, $incomeQuery);
$debugInfo['income_error'] = mysqli_error($conn);
$incomeRow = $incomeRs ? mysqli_fetch_assoc($incomeRs) : null;
$bookingIncome = (float)($incomeRow['total'] ?? 0);
$bookingCount = (int)($incomeRow['cnt'] ?? 0);

// Thu từ gia hạn HĐ
$renewalIncomeQuery = "
    SELECT COALESCE(SUM(t.amount), 0) AS total, COUNT(*) AS cnt
    FROM transactions t
    WHERE t.user_id = $user_id
      AND t.transaction_type = 'DEPOSIT_RECEIVED'
      AND t.description LIKE '%Gia hạn HĐ%'
      AND DATE(t.created_at) BETWEEN '$monthStart' AND '$monthEnd'
";
$renewalIncomeRs = mysqli_query($conn, $renewalIncomeQuery);
$renewalIncomeRow = $renewalIncomeRs ? mysqli_fetch_assoc($renewalIncomeRs) : null;
$renewalIncome = (float)($renewalIncomeRow['total'] ?? 0);
$renewalCount = (int)($renewalIncomeRow['cnt'] ?? 0);

$totalIncome = $bookingIncome + $renewalIncome;
$debugInfo['total_income'] = $totalIncome;
$debugInfo['booking_count'] = $bookingCount;
$debugInfo['renewal_income'] = $renewalIncome;
$debugInfo['renewal_count'] = $renewalCount;




// =====================================================
// TỔNG CHI: Tiền đăng tin trong tháng (trừ từ ví)
// =====================================================

// Debug: Kiểm tra các loại transaction của user này
$typeCheckQuery = "SELECT DISTINCT transaction_type FROM transactions WHERE user_id = $user_id";
$typeCheckRs = @mysqli_query($conn, $typeCheckQuery);
$transTypes = [];
if ($typeCheckRs) {
    while ($row = mysqli_fetch_assoc($typeCheckRs)) {
        $transTypes[] = $row['transaction_type'];
    }
}
$debugInfo['trans_types'] = implode(', ', $transTypes) ?: 'NONE';

// Debug: Đếm tất cả transaction trong tháng này
$allTransQuery = "SELECT COUNT(*) AS cnt FROM transactions WHERE user_id = $user_id AND DATE(created_at) BETWEEN '$monthStart' AND '$monthEnd'";
$allTransRs = @mysqli_query($conn, $allTransQuery);
$allTransRow = $allTransRs ? mysqli_fetch_assoc($allTransRs) : null;
$debugInfo['all_trans_in_month'] = (int)($allTransRow['cnt'] ?? 0);

// Query chi - dùng transaction_type và các loại POST, POST_NEW, POST_EXTEND, POST_RESUBMIT
$expenseQuery = "
    SELECT COALESCE(SUM(ABS(t.amount)), 0) AS total, COUNT(*) AS cnt
    FROM transactions t
    WHERE t.user_id = $user_id
      AND t.transaction_type IN ('POST', 'POST_NEW', 'POST_EXTEND', 'POST_RESUBMIT', 'REPOST')
      AND DATE(t.created_at) BETWEEN '$monthStart' AND '$monthEnd'
";
$debugInfo['expense_query'] = $expenseQuery;
$expenseRs = mysqli_query($conn, $expenseQuery);
$debugInfo['expense_error'] = mysqli_error($conn);
$expenseRow = $expenseRs ? mysqli_fetch_assoc($expenseRs) : null;
$totalExpense = (float)($expenseRow['total'] ?? 0);
$postCount = (int)($expenseRow['cnt'] ?? 0);
$debugInfo['total_expense'] = $totalExpense;
$debugInfo['post_count'] = $postCount;



// =====================================================
// LỢI NHUẬN = Thu - Chi
// =====================================================
$profit = $totalIncome - $totalExpense;

// =====================================================
// DANH SÁCH GIAO DỊCH
// =====================================================
$transactions = [];

// Thu: Từ bookings đã thanh toán trong tháng
$incomeListQuery = "
    SELECT 
        'INCOME' AS type,
        bk.booking_id AS id,
        DATE(bk.created_at) AS trans_date,
        CONCAT('Khách thuê phòng ', r.room_code) AS description,
        bk.deposit_amount AS amount,
        b.building_name,
        r.room_code,
        r.rental_type,
        CONCAT(COALESCE(t.full_name, '-'), ' - ', COALESCE(t.phone, 'N/A')) AS note
    FROM bookings bk
    JOIN rooms r ON r.room_id = bk.room_id
    JOIN buildings b ON b.building_id = r.building_id
    LEFT JOIN tenants t ON t.tenant_id = bk.tenant_id
    WHERE b.owner_id = $user_id
      AND bk.status IN ('DEPOSIT_PAID', 'CHECKED_IN', 'CHECKED_OUT')
      AND DATE(bk.created_at) BETWEEN '$monthStart' AND '$monthEnd'
      $buildingFilter
    ORDER BY bk.created_at DESC
";
$incomeListRs = @mysqli_query($conn, $incomeListQuery);
if ($incomeListRs) {
    while ($row = mysqli_fetch_assoc($incomeListRs)) {
        $transactions[] = $row;
    }
}

// Thu từ gia hạn HĐ
$renewalListQuery = "
    SELECT 
        'INCOME' AS type,
        t.transaction_id AS id,
        DATE(t.created_at) AS trans_date,
        'Gia hạn hợp đồng' AS description,
        t.amount AS amount,
        NULL AS building_name,
        NULL AS room_code,
        NULL AS rental_type,
        t.description AS note
    FROM transactions t
    WHERE t.user_id = $user_id
      AND t.transaction_type = 'DEPOSIT_RECEIVED'
      AND t.description LIKE '%Gia hạn HĐ%'
      AND DATE(t.created_at) BETWEEN '$monthStart' AND '$monthEnd'
    ORDER BY t.created_at DESC
";
$renewalListRs = @mysqli_query($conn, $renewalListQuery);
if ($renewalListRs) {
    while ($row = mysqli_fetch_assoc($renewalListRs)) {
        $transactions[] = $row;
    }
}


// Chi: Tiền đăng tin trong tháng
$expenseListQuery = "
    SELECT 
        'EXPENSE' AS type,
        t.transaction_id AS id,
        DATE(t.created_at) AS trans_date,
        CASE t.transaction_type 
            WHEN 'POST' THEN 'Đăng tin'
            WHEN 'POST_NEW' THEN 'Đăng tin mới'
            WHEN 'POST_EXTEND' THEN 'Gia hạn tin'
            WHEN 'POST_RESUBMIT' THEN 'Đăng lại tin'
            WHEN 'REPOST' THEN 'Đăng lại tin'
            ELSE 'Chi phí đăng tin'
        END AS description,
        ABS(t.amount) AS amount,
        NULL AS building_name,
        NULL AS room_code,
        t.description AS note
    FROM transactions t
    WHERE t.user_id = $user_id
      AND t.transaction_type IN ('POST', 'POST_NEW', 'POST_EXTEND', 'POST_RESUBMIT', 'REPOST')
      AND DATE(t.created_at) BETWEEN '$monthStart' AND '$monthEnd'
    ORDER BY t.created_at DESC
";

$expenseListRs = @mysqli_query($conn, $expenseListQuery);
if ($expenseListRs) {
    while ($row = mysqli_fetch_assoc($expenseListRs)) {
        $transactions[] = $row;
    }
}

// Sắp xếp theo ngày mới nhất
usort($transactions, function($a, $b) {
    return strtotime($b['trans_date']) - strtotime($a['trans_date']);
});

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="pagetitle">
    <h1><i class="bi bi-cash-stack me-2"></i>Thu chi tháng <?= $monthLabel ?></h1>
    <nav>
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= ADMIN_BASE_PATH ?>/modules/landlord/dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item active">Thu chi</li>
        </ol>
    </nav>
</div>

<?php if ($debugMode && !empty($debugInfo)): ?>
<div class="alert alert-secondary mb-3">
    <strong>🔧 Debug Info:</strong>
    <div class="row mt-2">
        <div class="col-md-6">
            <strong>Thu (Income):</strong>
            <ul class="mb-0">
                <li>Price field used: <code><?= $debugInfo['price_field'] ?? 'N/A' ?></code></li>
                <li>Booking count: <strong><?= $debugInfo['booking_count'] ?? 0 ?></strong></li>
                <li>Total income: <strong><?= number_format($debugInfo['total_income'] ?? 0) ?>đ</strong></li>
            </ul>
        </div>
        <div class="col-md-6">
            <strong>Chi (Expense):</strong>
            <ul class="mb-0">
                <li>User transaction types: <code><?= $debugInfo['trans_types'] ?? 'N/A' ?></code></li>
                <li>All trans in month: <strong><?= $debugInfo['all_trans_in_month'] ?? 0 ?></strong></li>
                <li>POST/REPOST count: <strong><?= $debugInfo['post_count'] ?? 0 ?></strong></li>
                <li>Total expense: <strong><?= number_format($debugInfo['total_expense'] ?? 0) ?>đ</strong></li>
            </ul>
        </div>
    </div>
    <?php if (!empty($debugInfo['income_error']) || !empty($debugInfo['expense_error'])): ?>
    <div class="text-danger mt-2">
        SQL Errors: <?= htmlspecialchars(($debugInfo['income_error'] ?? '') . ' | ' . ($debugInfo['expense_error'] ?? '')) ?>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>



<section class="section">
    <!-- Filter -->
    <div class="card mb-3">
        <div class="card-body py-3">
            <form method="get" class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label class="form-label">Tháng</label>
                    <input type="month" name="month" class="form-control" value="<?= htmlspecialchars($filterMonth) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Dãy/Tòa</label>
                    <select name="building_id" class="form-select">
                        <option value="0">Tất cả</option>
                        <?php foreach($buildings as $b): ?>
                            <option value="<?= (int)$b['building_id'] ?>" <?= $filterBuilding === (int)$b['building_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($b['building_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <button class="btn btn-primary w-100"><i class="bi bi-search me-1"></i>Lọc</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Summary Cards - Basic -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-body text-center py-3">
                    <div class="small text-muted mb-1">Tổng thu</div>
                    <h4 class="text-success mb-1"><?= number_format($totalIncome) ?>đ</h4>
                    <small class="text-muted"><?= $bookingCount ?> khách + <?= $renewalCount ?> gia hạn</small>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-body text-center py-3">
                    <div class="small text-muted mb-1">Tổng chi</div>
                    <h4 class="text-danger mb-1"><?= number_format($totalExpense) ?>đ</h4>
                    <small class="text-muted"><?= $postCount ?> lượt đăng tin</small>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-body text-center py-3">
                    <div class="small text-muted mb-1">Lợi nhuận (<?= $monthLabel ?>)</div>
                    <h4 class="<?= $profit >= 0 ? 'text-success' : 'text-danger' ?> mb-1"><?= $profit >= 0 ? '+' : '' ?><?= number_format($profit) ?>đ</h4>
                    <small class="text-muted">Thu - Chi</small>
                </div>
            </div>
        </div>
    </div>


    <!-- Transactions Table -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="bi bi-list-ul me-2"></i>Chi tiết giao dịch tháng <?= $monthLabel ?></h5>
            <span class="badge bg-secondary"><?= count($transactions) ?> giao dịch</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 110px;">Ngày</th>
                            <th style="width: 80px;">Loại</th>
                            <th>Nội dung</th>
                            <th class="text-end" style="width: 130px;">Số tiền</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($transactions)): ?>
                            <tr>
                                <td colspan="4" class="text-center text-muted py-5">
                                    <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                                    Không có giao dịch trong tháng <?= $monthLabel ?>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach($transactions as $t): ?>
                                <tr>
                                    <td class="text-muted"><?= date('d/m/Y', strtotime($t['trans_date'])) ?></td>
                                    <td>
                                        <?php if ($t['type'] === 'INCOME'): ?>
                                            <span class="badge bg-success"><i class="bi bi-arrow-down me-1"></i>Thu</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger"><i class="bi bi-arrow-up me-1"></i>Chi</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($t['type'] === 'INCOME'): ?>
                                            <strong><?= htmlspecialchars($t['description'] ?? '-') ?></strong>
                                            <?php if (!empty($t['building_name']) && !empty($t['room_code'])): ?>
                                                <br><small class="text-muted"><?= htmlspecialchars($t['building_name']) ?> - <?= htmlspecialchars($t['room_code']) ?></small>
                                            <?php endif; ?>
                                            <?php if (!empty($t['note'])): ?>
                                                <br><small class="text-primary"><?= htmlspecialchars($t['note']) ?></small>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <strong><?= htmlspecialchars($t['description'] ?? 'Chi phí đăng tin') ?></strong>
                                            <?php if (!empty($t['note'])): ?>
                                                <br><small class="text-muted"><?= htmlspecialchars($t['note']) ?></small>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <span class="fw-bold <?= $t['type'] === 'INCOME' ? 'text-success' : 'text-danger' ?>">
                                            <?= $t['type'] === 'INCOME' ? '+' : '-' ?><?= number_format((float)$t['amount']) ?>đ
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</section>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
