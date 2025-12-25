<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/vnpay_config.php';

$role = (string)($_SESSION['role_name'] ?? '');
$userId = (int)($_SESSION['user_id'] ?? 0);

if ($role !== 'LANDLORD') {
    header('Location: ' . ADMIN_BASE_PATH . '/index.php');
    exit;
}

$user = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM users WHERE user_id = $userId"));
$balance = (float)($user['balance'] ?? 0);

$errorMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $amount = (float)($_POST['amount'] ?? 0);
    
    if ($amount < 10000) {
        $errorMsg = 'Số tiền nạp tối thiểu là 10.000đ';
    } elseif ($amount > 100000000) {
        $errorMsg = 'Số tiền nạp tối đa là 100.000.000đ';
    } else {
        $insertSql = "INSERT INTO transactions (user_id, transaction_type, amount, 
            balance_before, balance_after, description, payment_method, status, created_at)
            VALUES ($userId, 'TOPUP', $amount, $balance, $balance, 
            'Nạp tiền qua VNPay', 'VNPAY', 'PENDING', NOW())";
        
        if (mysqli_query($conn, $insertSql)) {
            $transactionId = mysqli_insert_id($conn);
            $orderInfo = "Nap tien - GD $transactionId";
            $ipAddr = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
            $vnpayUrl = vnpay_create_payment_url($transactionId, $amount, $orderInfo, $ipAddr);
            header('Location: ' . $vnpayUrl);
            exit;
        } else {
            $errorMsg = 'Lỗi tạo giao dịch. Vui lòng thử lại.';
        }
    }
}

// Lấy lịch sử giao dịch
$historyQuery = "SELECT * FROM transactions 
                 WHERE user_id = $userId AND transaction_type = 'TOPUP' 
                 ORDER BY created_at DESC LIMIT 20";
$historyResult = mysqli_query($conn, $historyQuery);

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="pagetitle">
    <h1><i class="bi bi-wallet2 me-2"></i>Nạp tiền</h1>
    <nav>
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= ADMIN_BASE_PATH ?>/modules/landlord/dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item active">Nạp tiền</li>
        </ol>
    </nav>
</div>

<?php if (isset($_GET['msg']) && $_GET['msg'] === 'success'): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <i class="bi bi-check-circle-fill me-2"></i>
        <strong>Thành công!</strong> Đã nạp <?= number_format((float)($_GET['amount'] ?? 0), 0, ',', '.') ?>đ vào tài khoản.
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if (isset($_GET['error']) || $errorMsg): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <i class="bi bi-exclamation-triangle-fill me-2"></i>
        <?= htmlspecialchars($_GET['error'] ?? $errorMsg) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<section class="section">
    <div class="row">
        
        <!-- Form nạp tiền -->
        <div class="col-lg-5">
            <div class="card shadow-sm">
                <div class="card-body p-4">
                    
                    <!-- Số dư hiện tại -->
                    <div class="text-center mb-4 pb-4 border-bottom">
                        <small class="text-muted d-block mb-2">Số dư tài khoản</small>
                        <h2 class="text-primary mb-0 fw-bold"><?= number_format($balance, 0, ',', '.') ?> đ</h2>
                    </div>
                    
                    <form method="POST" id="topupForm">
                        
                        <!-- Nhập số tiền -->
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Nhập số tiền cần nạp</label>
                            <div class="input-group input-group-lg">
                                <input type="text" class="form-control" id="amountDisplay" 
                                       placeholder="0" style="font-size: 20px; text-align: right;">
                                <span class="input-group-text">VNĐ</span>
                            </div>
                            <input type="hidden" name="amount" id="amountHidden" value="200000">
                            <small class="text-muted">Tối thiểu 10.000đ - Tối đa 100.000.000đ</small>
                        </div>
                        
                        <!-- Mệnh giá nhanh -->
                        <div class="mb-4">
                            <label class="form-label fw-semibold mb-2">Hoặc chọn mệnh giá</label>
                            <div class="row g-2">
                                <?php
                                $amounts = [
                                    100000 => '100K',
                                    200000 => '200K',
                                    500000 => '500K', 
                                    1000000 => '1 Triệu',
                                    2000000 => '2 Triệu',
                                    5000000 => '5 Triệu'
                                ];
                                foreach ($amounts as $amt => $label):
                                ?>
                                    <div class="col-4">
                                        <button type="button" class="btn btn-outline-primary w-100 amount-btn"
                                                data-amount="<?= $amt ?>"><?= $label ?></button>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <!-- Phương thức -->
                        <div class="alert alert-info mb-4">
                            <i class="bi bi-info-circle me-2"></i>
                            Thanh toán qua <strong>VNPay</strong> - An toàn & Nhanh chóng
                        </div>
                        
                        <!-- Nút nạp tiền -->
                        <button type="submit" class="btn btn-primary btn-lg w-100">
                            <i class="bi bi-credit-card me-2"></i>Nạp tiền ngay
                        </button>
                        
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Lịch sử giao dịch -->
        <div class="col-lg-7">
            <div class="card shadow-sm">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0"><i class="bi bi-clock-history me-2"></i>Lịch sử nạp tiền</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="py-3">Mã GD</th>
                                    <th class="py-3">Số tiền</th>
                                    <th class="py-3">Trạng thái</th>
                                    <th class="py-3">Thời gian</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($historyResult && mysqli_num_rows($historyResult) > 0): ?>
                                    <?php while ($tx = mysqli_fetch_assoc($historyResult)): ?>
                                        <tr>
                                            <td class="py-3">
                                                <code class="text-primary">#<?= $tx['transaction_id'] ?></code>
                                            </td>
                                            <td class="py-3 fw-bold">
                                                <?= number_format((float)$tx['amount'], 0, ',', '.') ?>đ
                                            </td>
                                            <td class="py-3">
                                                <?php
                                                $statusBadges = [
                                                    'SUCCESS' => '<span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Thành công</span>',
                                                    'PENDING' => '<span class="badge bg-warning"><i class="bi bi-clock me-1"></i>Đang xử lý</span>',
                                                    'FAILED' => '<span class="badge bg-danger"><i class="bi bi-x-circle me-1"></i>Thất bại</span>'
                                                ];
                                                echo $statusBadges[$tx['status']] ?? '<span class="badge bg-secondary">' . $tx['status'] . '</span>';
                                                ?>
                                            </td>
                                            <td class="py-3 text-muted">
                                                <?= date('d/m/Y H:i', strtotime($tx['created_at'])) ?>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4" class="text-center py-5">
                                            <i class="bi bi-inbox text-muted" style="font-size: 48px;"></i>
                                            <p class="text-muted mt-3 mb-0">Chưa có giao dịch nào</p>
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

<style>
.amount-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}
.amount-btn.active {
    background-color: var(--bs-primary);
    color: white;
    border-color: var(--bs-primary);
}
.card {
    border: none;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const amountDisplay = document.getElementById('amountDisplay');
    const amountHidden = document.getElementById('amountHidden');
    const amountBtns = document.querySelectorAll('.amount-btn');
    
    function formatNumber(num) {
        return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
    }
    
    // Set giá trị mặc định
    amountDisplay.value = formatNumber(200000);
    
    // Xử lý nhập số tiền
    amountDisplay.addEventListener('input', function() {
        let value = this.value.replace(/\D/g, '');
        if (value) {
            this.value = formatNumber(parseInt(value));
            amountHidden.value = value;
        } else {
            this.value = '';
            amountHidden.value = '0';
        }
        // Bỏ active tất cả nút
        amountBtns.forEach(btn => btn.classList.remove('active'));
    });
    
    // Xử lý click mệnh giá
    amountBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const amount = this.dataset.amount;
            amountDisplay.value = formatNumber(parseInt(amount));
            amountHidden.value = amount;
            
            // Đổi trạng thái active
            amountBtns.forEach(b => b.classList.remove('active'));
            this.classList.add('active');
        });
    });
    
    // Validate form
    document.getElementById('topupForm').addEventListener('submit', function(e) {
        const amount = parseInt(amountHidden.value);
        if (amount < 10000) {
            e.preventDefault();
            alert('Số tiền nạp tối thiểu là 10.000đ');
        } else if (amount > 100000000) {
            e.preventDefault();
            alert('Số tiền nạp tối đa là 100.000.000đ');
        }
    });
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
