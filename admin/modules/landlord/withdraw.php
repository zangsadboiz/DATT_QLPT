<?php
/**
 * Rút tiền - Chủ trọ (chọn TK ngân hàng)
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/platform.php';

$role = $_SESSION['role_name'] ?? '';
$user_id = (int)($_SESSION['user_id'] ?? 0);

if ($role !== 'LANDLORD' || $user_id <= 0) {
    header('Location: /quanlyphongtro/admin/index.php?error=no_permission');
    exit;
}

// Lấy thông tin user
$user = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM users WHERE user_id = $user_id"));
$balance = (float)($user['balance'] ?? 0);

// Lấy danh sách TK ngân hàng
$bankAccounts = mysqli_query($conn, "SELECT * FROM bank_accounts WHERE user_id = $user_id ORDER BY is_default DESC, id ASC");
$bankAccountList = [];
while ($ba = mysqli_fetch_assoc($bankAccounts)) {
    $bankAccountList[] = $ba;
}
$hasBankAccount = count($bankAccountList) > 0;

$success = '';
$error = '';

// Số tiền tối thiểu rút
$minWithdraw = 50000;
$maxWithdraw = $balance;

// Xử lý form
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $hasBankAccount) {
    $amount = (float)($_POST['amount'] ?? 0);
    $password = $_POST['password'] ?? '';
    $bankAccountId = (int)($_POST['bank_account_id'] ?? 0);
    
    // Lấy TK đã chọn
    $selectedAccount = null;
    foreach ($bankAccountList as $ba) {
        if ($ba['id'] == $bankAccountId) {
            $selectedAccount = $ba;
            break;
        }
    }
    
    // Validate
    if (!$selectedAccount) {
        $error = "Vui lòng chọn tài khoản ngân hàng";
    } elseif ($amount < $minWithdraw) {
        $error = "Số tiền rút tối thiểu là " . number_format($minWithdraw) . "đ";
    } elseif ($amount > $balance) {
        $error = "Số dư không đủ. Số dư hiện tại: " . number_format($balance) . "đ";
    } elseif (!password_verify($password, $user['password_hash'])) {
        $error = "Mật khẩu không đúng";
    } else {
        // Tính hoa hồng theo mức
        $commissionData = calculate_commission($amount);
        $commission = $commissionData['commission'];
        $netAmount = $commissionData['net'];
        
        // Tạo yêu cầu rút tiền
        $stmt = mysqli_prepare($conn, "
            INSERT INTO withdrawal_requests 
            (user_id, amount, commission_amount, net_amount, bank_name, bank_account, bank_account_name, status, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'PENDING', NOW())
        ");
        mysqli_stmt_bind_param($stmt, "idddsss", 
            $user_id, $amount, $commission, $netAmount,
            $selectedAccount['bank_name'], 
            $selectedAccount['bank_account'], 
            $selectedAccount['bank_account_name']
        );
        
        if (mysqli_stmt_execute($stmt)) {
            // Trừ tiền từ balance (đóng băng)
            $newBalance = $balance - $amount;
            mysqli_query($conn, "UPDATE users SET balance = $newBalance WHERE user_id = $user_id");
            
            // Tạo transaction
            $insertId = mysqli_insert_id($conn);
            $desc = "Yêu cầu rút tiền #$insertId - Đang xử lý";
            mysqli_query($conn, "
                INSERT INTO transactions (user_id, transaction_type, amount, commission_amount, balance_before, balance_after, description, status, created_at)
                VALUES ($user_id, 'WITHDRAWAL', -$amount, $commission, $balance, $newBalance, '$desc', 'PENDING', NOW())
            ");
            
            // Redirect để tránh resubmit khi refresh (PRG pattern)
            header('Location: withdraw.php?success=' . urlencode("Yêu cầu rút " . number_format($amount) . "đ đã được gửi! Admin sẽ xử lý trong 24h."));
            exit;
        } else {
            $error = 'Lỗi tạo yêu cầu: ' . mysqli_error($conn);
        }
    }
}

// Lấy thông báo từ redirect
if (isset($_GET['success'])) {
    $success = $_GET['success'];
}

// Lấy lịch sử rút tiền
$withdrawals = mysqli_query($conn, "
    SELECT * FROM withdrawal_requests 
    WHERE user_id = $user_id 
    ORDER BY created_at DESC 
    LIMIT 10
");

$statusLabels = [
    'PENDING' => ['Đang chờ', 'warning'],
    'APPROVED' => ['Đã duyệt', 'info'],
    'COMPLETED' => ['Hoàn thành', 'success'],
    'REJECTED' => ['Từ chối', 'danger']
];

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="pagetitle">
    <h1><i class="bi bi-cash-coin me-2"></i>Rút tiền</h1>
    <nav>
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= ADMIN_BASE_PATH ?>/modules/landlord/dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item active">Rút tiền</li>
        </ol>
    </nav>
</div>

<section class="section">
    <div class="row">
        <div class="col-lg-5">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="bi bi-wallet2 me-2"></i>Rút tiền về tài khoản</h5>
                </div>
                <div class="card-body pt-4">
                    <?php if ($success): ?>
                        <div class="alert alert-success"><i class="bi bi-check-circle me-2"></i><?= $success ?></div>
                    <?php endif; ?>
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><i class="bi bi-x-circle me-2"></i><?= $error ?></div>
                    <?php endif; ?>
                    
                    <!-- Số dư hiện tại -->
                    <div class="alert alert-info mb-4">
                        <div class="d-flex justify-content-between align-items-center">
                            <span><strong>Số dư khả dụng:</strong></span>
                            <span class="fs-4 text-success fw-bold"><?= number_format($balance) ?>đ</span>
                        </div>
                    </div>
                    
                    <?php if (!$hasBankAccount): ?>
                        <div class="alert alert-warning">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            <strong>Chưa có tài khoản ngân hàng!</strong><br>
                            Vui lòng <a href="bank_account.php">thêm tài khoản ngân hàng</a> trước khi rút tiền.
                        </div>
                    <?php elseif ($balance < $minWithdraw): ?>
                        <div class="alert alert-warning">
                            Số dư tối thiểu để rút là <?= number_format($minWithdraw) ?>đ
                        </div>
                    <?php else: ?>
                        <form method="post">
                            <!-- Chọn TK ngân hàng -->
                            <div class="mb-3">
                                <label class="form-label">Tài khoản nhận tiền <span class="text-danger">*</span></label>
                                <?php foreach ($bankAccountList as $ba): ?>
                                <div class="form-check border rounded p-3 mb-2 <?= $ba['is_default'] ? 'border-primary' : '' ?>">
                                    <input type="radio" name="bank_account_id" value="<?= $ba['id'] ?>" 
                                           class="form-check-input" id="bank<?= $ba['id'] ?>"
                                           <?= $ba['is_default'] ? 'checked' : '' ?> required>
                                    <label class="form-check-label w-100" for="bank<?= $ba['id'] ?>">
                                        <strong><?= htmlspecialchars($ba['bank_name']) ?></strong>
                                        <?php if ($ba['is_default']): ?>
                                            <span class="badge bg-primary float-end">Mặc định</span>
                                        <?php endif; ?>
                                        <br>
                                        <span class="fs-6"><?= htmlspecialchars($ba['bank_account']) ?></span>
                                        <br>
                                        <small class="text-muted"><?= htmlspecialchars($ba['bank_account_name']) ?></small>
                                    </label>
                                </div>
                                <?php endforeach; ?>
                                <a href="bank_account.php" class="btn btn-sm btn-outline-secondary mt-1">
                                    <i class="bi bi-gear me-1"></i>Quản lý TK ngân hàng
                                </a>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Số tiền muốn rút <span class="text-danger">*</span></label>
                                <div class="input-group input-group-lg">
                                    <input type="text" name="amount_display" id="amountDisplay" class="form-control" 
                                           placeholder="Nhập số tiền" autocomplete="off">
                                    <input type="hidden" name="amount" id="amountHidden">
                                    <span class="input-group-text">VNĐ</span>
                                </div>
                                <small class="text-muted">Tối thiểu <?= number_format($minWithdraw, 0, ',', '.') ?>đ - Tối đa <?= number_format($maxWithdraw, 0, ',', '.') ?>đ</small>
                            </div>
                            
                            <!-- Commission Preview -->
                            <div class="mb-3" id="commissionPreview" style="display: none;">
                                <div class="border rounded p-3 bg-light">
                                    <div class="d-flex justify-content-between mb-2">
                                        <span>Số tiền rút:</span>
                                        <strong id="previewGross">0đ</strong>
                                    </div>
                                    <div class="d-flex justify-content-between mb-2 text-danger">
                                        <span>Hoa hồng (<?= PLATFORM_COMMISSION_RATE ?>%):</span>
                                        <strong id="previewCommission">-0đ</strong>
                                    </div>
                                    <hr class="my-2">
                                    <div class="d-flex justify-content-between">
                                        <span class="fw-bold">Thực nhận:</span>
                                        <span class="fs-5 text-success fw-bold" id="previewNet">0đ</span>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Quick amounts -->
                            <div class="mb-3">
                                <label class="form-label">Hoặc chọn nhanh:</label>
                                <div class="d-flex gap-2 flex-wrap">
                                    <?php 
                                    $quickAmounts = [100000, 200000, 500000, 1000000, 2000000];
                                    foreach ($quickAmounts as $qa):
                                        if ($qa <= $balance):
                                    ?>
                                        <button type="button" class="btn btn-outline-primary btn-sm quick-amount" data-amount="<?= $qa ?>">
                                            <?= number_format($qa/1000, 0, ',', '.') ?>K
                                        </button>
                                    <?php endif; endforeach; ?>
                                    <button type="button" class="btn btn-outline-success btn-sm quick-amount" data-amount="<?= (int)$balance ?>">
                                        Tất cả
                                    </button>
                                </div>
                            </div>
                            
                            <div class="mb-4">
                                <label class="form-label">Mật khẩu xác nhận <span class="text-danger">*</span></label>
                                <input type="password" name="password" class="form-control" 
                                       placeholder="Nhập mật khẩu đăng nhập" required>
                            </div>
                            
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="bi bi-send me-2"></i>Gửi yêu cầu rút tiền
                                </button>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="card mt-3">
                <div class="card-body">
                    <h6 class="card-title"><i class="bi bi-info-circle me-2"></i>Lưu ý</h6>
                    <ul class="mb-0 small">
                        <li>Tiền sẽ được chuyển trong vòng <strong>24 giờ</strong> làm việc</li>
                        <li>Số tiền rút tối thiểu: <strong><?= number_format($minWithdraw) ?>đ</strong></li>
                        <li>Kiểm tra kỹ thông tin ngân hàng trước khi rút</li>
                        <li>Nếu bị từ chối, tiền sẽ được hoàn lại số dư</li>
                    </ul>
                </div>
            </div>
        </div>
        
        <div class="col-lg-7">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-clock-history me-2"></i>Lịch sử rút tiền</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Mã</th>
                                    <th>Số tiền</th>
                                    <th>Tài khoản</th>
                                    <th>Trạng thái</th>
                                    <th>Ngày tạo</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $hasW = false; while ($w = $withdrawals ? mysqli_fetch_assoc($withdrawals) : null): $hasW = true; 
                                    $st = $statusLabels[$w['status']] ?? ['N/A', 'secondary'];
                                ?>
                                <tr>
                                    <td>#<?= $w['id'] ?></td>
                                    <td class="fw-bold text-danger">-<?= number_format($w['amount']) ?>đ</td>
                                    <td>
                                        <small><?= htmlspecialchars($w['bank_name']) ?></small><br>
                                        <?= htmlspecialchars($w['bank_account']) ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?= $st[1] ?>"><?= $st[0] ?></span>
                                    </td>
                                    <td><small><?= date('d/m/Y H:i', strtotime($w['created_at'])) ?></small></td>
                                </tr>
                                <?php endwhile; ?>
                                <?php if (!$hasW): ?>
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-4">
                                        <i class="bi bi-inbox fs-1 d-block mb-2 opacity-50"></i>
                                        Chưa có yêu cầu rút tiền nào
                                    </td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<script>
const TIERS = <?= json_encode(get_commission_tiers()) ?>;
const MIN_COMMISSION = <?= PLATFORM_MIN_COMMISSION ?>;
const MIN_WITHDRAW = <?= $minWithdraw ?>;
const MAX_WITHDRAW = <?= $maxWithdraw ?>;

function formatNumber(num) {
    return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
}

function parseNumber(str) {
    return parseInt(str.replace(/\./g, '')) || 0;
}

function getRate(amount) {
    for (let i = TIERS.length - 1; i >= 0; i--) {
        const t = TIERS[i];
        if (amount >= t.min && (t.max === null || amount < t.max)) {
            return t.rate;
        }
    }
    return <?= PLATFORM_COMMISSION_RATE ?>;
}

function updateCommission() {
    const display = document.getElementById('amountDisplay');
    const hidden = document.getElementById('amountHidden');
    const preview = document.getElementById('commissionPreview');
    
    const amount = parseNumber(display.value);
    hidden.value = amount;
    
    if (amount >= MIN_WITHDRAW) {
        const rate = getRate(amount);
        let commission = Math.max(amount * rate / 100, MIN_COMMISSION);
        commission = Math.min(commission, amount);
        const net = amount - commission;
        
        // Update rate label
        const rateLabel = document.querySelector('#commissionPreview .text-danger span');
        if (rateLabel) rateLabel.textContent = `Hoa hồng (${rate}%):`;
        
        document.getElementById('previewGross').textContent = formatNumber(amount) + 'đ';
        document.getElementById('previewCommission').textContent = '-' + formatNumber(Math.round(commission)) + 'đ';
        document.getElementById('previewNet').textContent = formatNumber(Math.round(net)) + 'đ';
        preview.style.display = 'block';
    } else {
        preview.style.display = 'none';
    }
}

document.getElementById('amountDisplay').addEventListener('input', function(e) {
    let value = parseNumber(this.value);
    if (value > 0) {
        this.value = formatNumber(value);
    }
    updateCommission();
});

document.querySelectorAll('.quick-amount').forEach(btn => {
    btn.addEventListener('click', function() {
        const amount = parseInt(this.dataset.amount);
        document.getElementById('amountDisplay').value = formatNumber(amount);
        document.getElementById('amountHidden').value = amount;
        updateCommission();
    });
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
