<?php
/**
 * Quản lý yêu cầu rút tiền - Admin
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/platform.php';

$role = $_SESSION['role_name'] ?? '';
if ($role !== 'ADMIN') {
    header('Location: /quanlyphongtro/admin/index.php?error=no_permission');
    exit;
}

$admin_id = (int)$_SESSION['user_id'];

// Filters
$statusFilter = $_GET['status'] ?? 'PENDING';
$search = trim($_GET['q'] ?? '');
$dateFrom = $_GET['from'] ?? '';
$dateTo = $_GET['to'] ?? '';

// Pagination
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 15;
$offset = ($page - 1) * $perPage;

// Build WHERE clause
$where = "1=1";
if ($statusFilter && strtoupper($statusFilter) !== 'ALL') {
    $where .= " AND w.status = '" . mysqli_real_escape_string($conn, $statusFilter) . "'";
}
if ($search) {
    $s = mysqli_real_escape_string($conn, $search);
    $where .= " AND (u.full_name LIKE '%$s%' OR u.email LIKE '%$s%' OR w.bank_account LIKE '%$s%')";
}
if ($dateFrom) {
    $where .= " AND DATE(w.created_at) >= '" . mysqli_real_escape_string($conn, $dateFrom) . "'";
}
if ($dateTo) {
    $where .= " AND DATE(w.created_at) <= '" . mysqli_real_escape_string($conn, $dateTo) . "'";
}

// Count pending
$pendingCount = 0;
$pendingRs = @mysqli_query($conn, "SELECT COUNT(*) c FROM withdrawal_requests WHERE status = 'PENDING'");
if ($pendingRs) {
    $row = mysqli_fetch_assoc($pendingRs);
    $pendingCount = (int)($row['c'] ?? 0);
}

// Count total for pagination
$totalRs = @mysqli_query($conn, "
    SELECT COUNT(*) c FROM withdrawal_requests w 
    JOIN users u ON u.user_id = w.user_id
    WHERE $where
");
$total = $totalRs ? (int)(mysqli_fetch_assoc($totalRs)['c'] ?? 0) : 0;
$totalPages = max(1, ceil($total / $perPage));

// Get list
$list = @mysqli_query($conn, "
    SELECT w.*, u.full_name, u.email, u.phone
    FROM withdrawal_requests w
    JOIN users u ON u.user_id = w.user_id
    WHERE $where
    ORDER BY 
        CASE w.status WHEN 'PENDING' THEN 0 WHEN 'APPROVED' THEN 1 ELSE 2 END,
        w.created_at DESC
    LIMIT $perPage OFFSET $offset
");

// Stats
$stats = [];
$statsRs = @mysqli_query($conn, "SELECT status, COUNT(*) c, SUM(amount) total FROM withdrawal_requests GROUP BY status");
if ($statsRs) {
    while ($st = mysqli_fetch_assoc($statsRs)) {
        $stats[$st['status']] = ['count' => $st['c'], 'total' => $st['total']];
    }
}

$statusLabels = [
    'PENDING' => ['Chờ duyệt', 'warning', 'clock'],
    'APPROVED' => ['Đã duyệt', 'info', 'check'],
    'COMPLETED' => ['Hoàn thành', 'success', 'check-circle'],
    'REJECTED' => ['Từ chối', 'danger', 'x-circle']
];

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="pagetitle d-flex justify-content-between align-items-center">
    <div>
        <h1><i class="bi bi-cash-coin me-2"></i>Yêu cầu rút tiền</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= ADMIN_BASE_PATH ?>/index.php">Dashboard</a></li>
                <li class="breadcrumb-item active">Rút tiền</li>
            </ol>
        </nav>
    </div>
    <?php if ($pendingCount > 0): ?>
    <span class="badge bg-warning text-dark fs-6">
        <i class="bi bi-clock me-1"></i><?= $pendingCount ?> chờ xử lý
    </span>
    <?php endif; ?>
</div>

<!-- Stats Cards -->
<div class="row mb-4">
    <?php foreach (['PENDING' => 'warning', 'APPROVED' => 'info', 'COMPLETED' => 'success', 'REJECTED' => 'danger'] as $st => $color): ?>
    <div class="col-md-3 col-6">
        <div class="card border-<?= $color ?>">
            <div class="card-body py-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-<?= $color ?> fw-bold"><?= $statusLabels[$st][0] ?></div>
                        <div class="fs-4 fw-bold"><?= (int)($stats[$st]['count'] ?? 0) ?></div>
                    </div>
                    <div class="text-muted small text-end">
                        <?= number_format((float)($stats[$st]['total'] ?? 0)) ?>đ
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Filters -->
<div class="card mb-3">
    <div class="card-body py-3">
        <form method="get" class="row g-2 align-items-end">
            <div class="col-auto">
                <select name="status" class="form-select">
                    <option value="ALL" <?= $statusFilter === 'ALL' ? 'selected' : '' ?>>Tất cả</option>
                    <option value="PENDING" <?= $statusFilter === 'PENDING' ? 'selected' : '' ?>>Chờ duyệt</option>
                    <option value="APPROVED" <?= $statusFilter === 'APPROVED' ? 'selected' : '' ?>>Đã duyệt</option>
                    <option value="COMPLETED" <?= $statusFilter === 'COMPLETED' ? 'selected' : '' ?>>Hoàn thành</option>
                    <option value="REJECTED" <?= $statusFilter === 'REJECTED' ? 'selected' : '' ?>>Từ chối</option>
                </select>
            </div>
            <div class="col-auto">
                <input type="text" name="q" class="form-control" placeholder="Tìm tên, email, STK..." 
                       value="<?= htmlspecialchars($search) ?>">
            </div>
            <div class="col-auto">
                <input type="date" name="from" class="form-control" value="<?= $dateFrom ?>">
            </div>
            <div class="col-auto">
                <span class="text-muted">→</span>
            </div>
            <div class="col-auto">
                <input type="date" name="to" class="form-control" value="<?= $dateTo ?>">
            </div>
            <div class="col-auto">
                <button class="btn btn-primary"><i class="bi bi-search me-1"></i>Lọc</button>
                <a href="index.php" class="btn btn-outline-secondary"><i class="bi bi-x"></i></a>
            </div>
        </form>
    </div>
</div>

<!-- List -->
<section class="section">
    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Chủ trọ</th>
                            <th>Tài khoản nhận</th>
                            <th class="text-end">Số tiền</th>
                            <th>Trạng thái</th>
                            <th>Ngày tạo</th>
                            <th class="text-center">Hành động</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $has = false; while ($w = $list ? mysqli_fetch_assoc($list) : null): $has = true; 
                            $st = $statusLabels[$w['status']] ?? ['N/A', 'secondary', 'question'];
                        ?>
                        <tr>
                            <td><strong>#<?= $w['id'] ?></strong></td>
                            <td>
                                <strong><?= htmlspecialchars($w['full_name']) ?></strong><br>
                                <small class="text-muted"><?= htmlspecialchars($w['email']) ?></small>
                            </td>
                            <td>
                                <strong><?= htmlspecialchars($w['bank_name']) ?></strong><br>
                                <span class="font-monospace"><?= htmlspecialchars($w['bank_account']) ?></span><br>
                                <small class="text-muted"><?= htmlspecialchars($w['bank_account_name']) ?></small>
                            </td>
                            <td class="text-end fw-bold text-danger"><?= number_format($w['amount']) ?>đ</td>
                            <td>
                                <span class="badge bg-<?= $st[1] ?>">
                                    <i class="bi bi-<?= $st[2] ?> me-1"></i><?= $st[0] ?>
                                </span>
                            </td>
                            <td>
                                <?= date('d/m/Y', strtotime($w['created_at'])) ?><br>
                                <small class="text-muted"><?= date('H:i', strtotime($w['created_at'])) ?></small>
                            </td>
                            <td class="text-center">
                                <?php if ($w['status'] === 'PENDING'): 
                                    $commData = calculate_commission((float)$w['amount']);
                                    $netAmt = isset($w['net_amount']) && $w['net_amount'] > 0 
                                        ? $w['net_amount'] 
                                        : $commData['net'];
                                    $commAmt = isset($w['commission_amount']) && $w['commission_amount'] > 0 
                                        ? $w['commission_amount'] 
                                        : $commData['commission'];
                                ?>
                                    <button class="btn btn-sm btn-success me-1" data-bs-toggle="modal" 
                                            data-bs-target="#approveModal" 
                                            data-id="<?= $w['id'] ?>" 
                                            data-amount="<?= $w['amount'] ?>"
                                            data-net="<?= $netAmt ?>"
                                            data-commission="<?= $commAmt ?>"
                                            data-bank="<?= htmlspecialchars($w['bank_name']) ?>"
                                            data-account="<?= htmlspecialchars($w['bank_account']) ?>"
                                            data-name="<?= htmlspecialchars($w['bank_account_name']) ?>">
                                        <i class="bi bi-check-lg"></i> Duyệt
                                    </button>
                                    <button class="btn btn-sm btn-danger" data-bs-toggle="modal" 
                                            data-bs-target="#rejectModal" data-id="<?= $w['id'] ?>">
                                        <i class="bi bi-x-lg"></i>
                                    </button>
                                <?php elseif ($w['status'] === 'COMPLETED' || $w['status'] === 'APPROVED'): ?>
                                    <button class="btn btn-sm btn-outline-info" data-bs-toggle="modal" 
                                            data-bs-target="#viewModal" 
                                            data-id="<?= $w['id'] ?>"
                                            data-amount="<?= $w['amount'] ?>"
                                            data-net="<?= $w['net_amount'] ?? $w['amount'] ?>"
                                            data-commission="<?= $w['commission_amount'] ?? 0 ?>"
                                            data-bank="<?= htmlspecialchars($w['bank_name']) ?>"
                                            data-account="<?= htmlspecialchars($w['bank_account']) ?>"
                                            data-name="<?= htmlspecialchars($w['bank_account_name']) ?>">
                                        <i class="bi bi-eye"></i> Xem
                                    </button>
                                <?php elseif ($w['status'] === 'REJECTED'): ?>
                                    <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" 
                                            data-bs-target="#viewRejectModal" 
                                            data-id="<?= $w['id'] ?>"
                                            data-amount="<?= $w['amount'] ?>"
                                            data-reason="<?= htmlspecialchars($w['admin_note'] ?? 'Không có lý do') ?>">
                                        <i class="bi bi-eye"></i> Xem
                                    </button>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                        <?php if (!$has): ?>
                        <tr>
                            <td colspan="7" class="text-center text-muted py-5">
                                <i class="bi bi-inbox fs-1 d-block mb-2 opacity-50"></i>
                                Không có yêu cầu rút tiền nào
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
            <nav class="px-3 py-3 border-top">
                <ul class="pagination pagination-sm mb-0 justify-content-center">
                    <?php for ($pg = 1; $pg <= $totalPages; $pg++): ?>
                        <li class="page-item <?= $pg === $page ? 'active' : '' ?>">
                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $pg])) ?>"><?= $pg ?></a>
                        </li>
                    <?php endfor; ?>
                </ul>
            </nav>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- Approve Modal -->
<div class="modal fade" id="approveModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="post" action="process.php">
                <input type="hidden" name="action" value="approve">
                <input type="hidden" name="id" id="approveId">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title"><i class="bi bi-check-circle me-2"></i>Duyệt & Chuyển khoản</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="text-center">
                                <img id="approveQR" src="" alt="QR Code" class="img-fluid border rounded" style="max-width: 250px;">
                                <p class="mt-2 mb-0 small text-muted">Quét mã để chuyển khoản nhanh</p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <h6 class="text-primary"><i class="bi bi-bank me-2"></i>Thông tin chuyển khoản</h6>
                            <table class="table table-sm">
                                <tr>
                                    <td class="text-muted">Ngân hàng:</td>
                                    <td><strong id="infoBank">-</strong></td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Số TK:</td>
                                    <td><strong id="infoAccount" class="font-monospace fs-5">-</strong></td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Chủ TK:</td>
                                    <td><strong id="infoName">-</strong></td>
                                </tr>
                                <tr class="table-light">
                                    <td class="text-muted">Yêu cầu rút:</td>
                                    <td id="infoGross">-</td>
                                </tr>
                                <tr class="text-danger">
                                    <td>Hoa hồng:</td>
                                    <td id="infoCommission">-</td>
                                </tr>
                                <tr class="table-success">
                                    <td class="fw-bold">Thực chuyển:</td>
                                    <td><span class="fs-4 fw-bold text-success" id="infoAmount">-</span></td>
                                </tr>
                            </table>
                            <div class="alert alert-warning py-2 mb-0">
                                <small><i class="bi bi-info-circle me-1"></i>Sau khi CK, nhấn <strong>Duyệt</strong> → vào <strong>Hoàn thành</strong></small>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn btn-success"><i class="bi bi-check-lg me-1"></i>Duyệt yêu cầu</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Reject Modal -->
<div class="modal fade" id="rejectModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" action="process.php">
                <input type="hidden" name="action" value="reject">
                <input type="hidden" name="id" id="rejectId">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title"><i class="bi bi-x-circle me-2"></i>Từ chối yêu cầu</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-danger"><strong>Tiền sẽ được hoàn lại vào số dư chủ trọ.</strong></p>
                    <div class="mb-3">
                        <label class="form-label">Lý do từ chối</label>
                        <textarea name="note" class="form-control" rows="3" placeholder="Nhập lý do..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn btn-danger"><i class="bi bi-x-lg me-1"></i>Từ chối</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Complete Modal -->
<div class="modal fade" id="completeModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" action="process.php">
                <input type="hidden" name="action" value="complete">
                <input type="hidden" name="id" id="completeId">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="bi bi-check-circle me-2"></i>Xác nhận hoàn thành</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Bạn đã chuyển khoản cho chủ trọ và muốn đánh dấu yêu cầu này là <strong>Hoàn thành</strong>?</p>
                    <div class="mb-3">
                        <label class="form-label">Ghi chú (tùy chọn)</label>
                        <input type="text" name="note" class="form-control" placeholder="VD: Đã CK lúc 14:30">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-circle me-1"></i>Hoàn thành</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Modal (Read-only with QR) -->
<div class="modal fade" id="viewModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title"><i class="bi bi-eye me-2"></i>Chi tiết yêu cầu rút tiền</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-5 text-center">
                        <img id="viewQrCode" src="" alt="QR Code" class="img-fluid mb-2" style="max-width: 200px;">
                        <small class="text-muted d-block">Quét mã để chuyển khoản</small>
                    </div>
                    <div class="col-md-7">
                        <h6 class="text-info"><i class="bi bi-bank me-1"></i>Thông tin chuyển khoản</h6>
                        <table class="table table-sm">
                            <tr><th class="text-muted" style="width:40%">Ngân hàng:</th><td id="viewBankName"></td></tr>
                            <tr><th class="text-muted">Số TK:</th><td id="viewAccount" class="fw-bold"></td></tr>
                            <tr><th class="text-muted">Chủ TK:</th><td id="viewAccountName"></td></tr>
                            <tr><th class="text-muted">Yêu cầu rút:</th><td id="viewAmount" class="text-danger fw-bold"></td></tr>
                            <tr><th class="text-muted">Hoa hồng:</th><td id="viewCommission" class="text-warning"></td></tr>
                            <tr><th class="text-muted">Thực chuyển:</th><td id="viewNet" class="text-success fs-5 fw-bold"></td></tr>
                        </table>
                        <div class="alert alert-success mb-0">
                            <i class="bi bi-check-circle me-1"></i>Đã chuyển khoản thành công
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
            </div>
        </div>
    </div>
</div>

<!-- View Reject Modal (reason only, no QR) -->
<div class="modal fade" id="viewRejectModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="bi bi-x-circle me-2"></i>Yêu cầu bị từ chối</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="text-center mb-3">
                    <i class="bi bi-x-octagon text-danger" style="font-size: 4rem;"></i>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Số tiền yêu cầu:</label>
                    <div id="rejectViewAmount" class="fs-5 text-danger fw-bold"></div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Lý do từ chối:</label>
                    <div id="rejectViewReason" class="alert alert-danger"></div>
                </div>
                <div class="alert alert-info mb-0">
                    <i class="bi bi-info-circle me-1"></i>Tiền đã được hoàn lại vào số dư của bạn
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
            </div>
        </div>
    </div>
</div>

<script>
// Mapping bank names to BIN codes for VietQR
const BANK_BINS = {
    'Vietcombank': '970436', 'VCB': '970436',
    'Techcombank': '970407', 'TCB': '970407',
    'BIDV': '970418',
    'VietinBank': '970415', 'CTG': '970415',
    'Agribank': '970405', 'AGR': '970405',
    'MBBank': '970422', 'MB': '970422',
    'ACB': '970416',
    'VPBank': '970432',
    'TPBank': '970423',
    'Sacombank': '970403', 'STB': '970403',
    'HDBank': '970437',
    'VIB': '970441',
    'SHB': '970443',
    'SeABank': '970440',
    'OCB': '970448',
    'MSB': '970426',
    'Eximbank': '970431',
    'LienVietPostBank': '970449', 'LPB': '970449',
    'ABBank': '970425',
    'BacABank': '970409',
    'NCB': '970419',
    'PVcomBank': '970412',
    'VietABank': '970427',
    'NamABank': '970428',
    'GPBank': '970406',
    'BaoVietBank': '970438',
    'VietBank': '970433',
    'Kienlongbank': '970452',
};

function getBankBin(bankName) {
    for (const [key, bin] of Object.entries(BANK_BINS)) {
        if (bankName.toLowerCase().includes(key.toLowerCase())) {
            return bin;
        }
    }
    return null;
}

document.getElementById('approveModal').addEventListener('show.bs.modal', function(e) {
    const btn = e.relatedTarget;
    const bank = btn.dataset.bank;
    const account = btn.dataset.account;
    const name = btn.dataset.name;
    const amount = Number(btn.dataset.amount);
    
    document.getElementById('approveId').value = btn.dataset.id;
    document.getElementById('infoBank').textContent = bank;
    document.getElementById('infoAccount').textContent = account;
    document.getElementById('infoName').textContent = name;
    
    const gross = Number(btn.dataset.amount);
    const commission = Number(btn.dataset.commission || 0);
    const net = Number(btn.dataset.net || gross);
    
    document.getElementById('infoGross').textContent = gross.toLocaleString() + 'đ';
    document.getElementById('infoCommission').textContent = '-' + commission.toLocaleString() + 'đ';
    document.getElementById('infoAmount').textContent = net.toLocaleString() + 'đ';
    
    // Generate VietQR with NET amount (what admin should transfer)
    const bin = getBankBin(bank);
    if (bin) {
        const addInfo = 'RUTWD' + btn.dataset.id;
        const qrUrl = `https://api.vietqr.io/image/${bin}-${account}-compact2.png?amount=${net}&addInfo=${encodeURIComponent(addInfo)}&accountName=${encodeURIComponent(name)}`;
        document.getElementById('approveQR').src = qrUrl;
    } else {
        document.getElementById('approveQR').src = 'https://via.placeholder.com/250x250?text=QR+kh%C3%B4ng+h%E1%BB%97+tr%E1%BB%A3';
    }
});

document.getElementById('rejectModal').addEventListener('show.bs.modal', function(e) {
    document.getElementById('rejectId').value = e.relatedTarget.dataset.id;
});
document.getElementById('completeModal').addEventListener('show.bs.modal', function(e) {
    document.getElementById('completeId').value = e.relatedTarget.dataset.id;
});

// View Modal - show QR code for completed withdrawals
document.getElementById('viewModal').addEventListener('show.bs.modal', function(e) {
    const btn = e.relatedTarget;
    const bank = btn.dataset.bank;
    const account = btn.dataset.account;
    const name = btn.dataset.name;
    const amount = Number(btn.dataset.amount);
    const net = Number(btn.dataset.net || btn.dataset.amount);
    const commission = Number(btn.dataset.commission || 0);
    
    document.getElementById('viewBankName').textContent = bank;
    document.getElementById('viewAccount').textContent = account;
    document.getElementById('viewAccountName').textContent = name;
    document.getElementById('viewAmount').textContent = amount.toLocaleString('vi-VN') + 'đ';
    document.getElementById('viewCommission').textContent = '-' + commission.toLocaleString('vi-VN') + 'đ';
    document.getElementById('viewNet').textContent = net.toLocaleString('vi-VN') + 'đ';
    
    // Generate QR
    const bin = getBankBin(bank);
    if (bin) {
        const addInfo = 'RUTWD' + btn.dataset.id;
        const qrUrl = `https://api.vietqr.io/image/${bin}-${account}-compact2.png?amount=${net}&addInfo=${encodeURIComponent(addInfo)}&accountName=${encodeURIComponent(name)}`;
        document.getElementById('viewQrCode').src = qrUrl;
    } else {
        document.getElementById('viewQrCode').src = 'https://via.placeholder.com/200x200?text=QR';
    }
});

// View Reject Modal - show rejection reason
document.getElementById('viewRejectModal').addEventListener('show.bs.modal', function(e) {
    const btn = e.relatedTarget;
    const amount = Number(btn.dataset.amount);
    const reason = btn.dataset.reason;
    
    document.getElementById('rejectViewAmount').textContent = amount.toLocaleString('vi-VN') + 'đ';
    document.getElementById('rejectViewReason').textContent = reason;
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
