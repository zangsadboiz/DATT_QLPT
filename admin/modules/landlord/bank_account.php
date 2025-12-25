<?php
/**
 * Quản lý tài khoản ngân hàng cho chủ trọ (tối đa 3 TK)
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';

$role = $_SESSION['role_name'] ?? '';
$user_id = (int)($_SESSION['user_id'] ?? 0);

if ($role !== 'LANDLORD' || $user_id <= 0) {
    header('Location: /quanlyphongtro/admin/index.php?error=no_permission');
    exit;
}

$success = '';
$error = '';
$maxAccounts = 3;

// Lấy danh sách TK hiện có (check if table exists)
$accounts = @mysqli_query($conn, "SELECT * FROM bank_accounts WHERE user_id = $user_id ORDER BY is_default DESC, id ASC");
$accountCount = $accounts ? mysqli_num_rows($accounts) : 0;

// Xử lý xóa
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $deleteId = (int)$_POST['delete_id'];
    mysqli_query($conn, "DELETE FROM bank_accounts WHERE id = $deleteId AND user_id = $user_id");
    header('Location: bank_account.php?success=deleted');
    exit;
}

// Xử lý đặt mặc định
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['set_default_id'])) {
    $defaultId = (int)$_POST['set_default_id'];
    mysqli_query($conn, "UPDATE bank_accounts SET is_default = 0 WHERE user_id = $user_id");
    mysqli_query($conn, "UPDATE bank_accounts SET is_default = 1 WHERE id = $defaultId AND user_id = $user_id");
    header('Location: bank_account.php?success=default');
    exit;
}

// Xử lý thêm mới
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bank_name'])) {
    if ($accountCount >= $maxAccounts) {
        $error = "Bạn chỉ được thêm tối đa $maxAccounts tài khoản ngân hàng";
    } else {
        $bank_name = trim($_POST['bank_name'] ?? '');
        $bank_account = trim($_POST['bank_account'] ?? '');
        $bank_account_name = strtoupper(trim($_POST['bank_account_name'] ?? ''));
        
        if (empty($bank_name)) {
            $error = 'Vui lòng chọn ngân hàng';
        } elseif (empty($bank_account) || !preg_match('/^\d{6,20}$/', $bank_account)) {
            $error = 'Số tài khoản không hợp lệ (6-20 chữ số)';
        } elseif (empty($bank_account_name)) {
            $error = 'Vui lòng nhập tên chủ tài khoản';
        } else {
            // Kiểm tra trùng
            $check = mysqli_fetch_assoc(mysqli_query($conn, "
                SELECT id FROM bank_accounts 
                WHERE user_id = $user_id AND bank_account = '" . mysqli_real_escape_string($conn, $bank_account) . "'
            "));
            if ($check) {
                $error = 'Số tài khoản này đã tồn tại';
            } else {
                $isDefault = $accountCount === 0 ? 1 : 0; // TK đầu tiên là mặc định
                $stmt = mysqli_prepare($conn, "
                    INSERT INTO bank_accounts (user_id, bank_name, bank_account, bank_account_name, is_default, created_at)
                    VALUES (?, ?, ?, ?, ?, NOW())
                ");
                mysqli_stmt_bind_param($stmt, "isssi", $user_id, $bank_name, $bank_account, $bank_account_name, $isDefault);
                
                if (mysqli_stmt_execute($stmt)) {
                    header('Location: bank_account.php?success=added');
                    exit;
                } else {
                    $error = 'Lỗi thêm tài khoản: ' . mysqli_error($conn);
                }
            }
        }
    }
}

// Reload accounts
$accounts = @mysqli_query($conn, "SELECT * FROM bank_accounts WHERE user_id = $user_id ORDER BY is_default DESC, id ASC");
$accountCount = $accounts ? mysqli_num_rows($accounts) : 0;

if (isset($_GET['success'])) {
    $success = match($_GET['success']) {
        'added' => 'Thêm tài khoản ngân hàng thành công!',
        'deleted' => 'Đã xóa tài khoản ngân hàng',
        'default' => 'Đã đặt làm tài khoản mặc định',
        default => ''
    };
}

// Danh sách ngân hàng phổ biến
$banks = [
    'Vietcombank' => 'Vietcombank - Ngân hàng Ngoại thương',
    'VietinBank' => 'VietinBank - Ngân hàng Công thương',
    'BIDV' => 'BIDV - Ngân hàng Đầu tư & Phát triển',
    'Agribank' => 'Agribank - Ngân hàng Nông nghiệp',
    'Techcombank' => 'Techcombank - Ngân hàng Kỹ thương',
    'MBBank' => 'MBBank - Ngân hàng Quân đội',
    'ACB' => 'ACB - Ngân hàng Á Châu',
    'VPBank' => 'VPBank - Ngân hàng Việt Nam Thịnh Vượng',
    'TPBank' => 'TPBank - Ngân hàng Tiên Phong',
    'Sacombank' => 'Sacombank - Ngân hàng Sài Gòn Thương Tín',
    'HDBank' => 'HDBank - Ngân hàng Phát triển TP.HCM',
    'OCB' => 'OCB - Ngân hàng Phương Đông',
    'SHB' => 'SHB - Ngân hàng Sài Gòn - Hà Nội',
    'MSB' => 'MSB - Ngân hàng Hàng Hải',
    'SeABank' => 'SeABank - Ngân hàng Đông Nam Á',
    'Eximbank' => 'Eximbank - Ngân hàng Xuất Nhập Khẩu',
    'LienVietPostBank' => 'LienVietPostBank - Ngân hàng Bưu điện Liên Việt',
    'VIB' => 'VIB - Ngân hàng Quốc tế',
    'NamABank' => 'NamABank - Ngân hàng Nam Á',
    'Khác' => 'Ngân hàng khác'
];

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="pagetitle">
    <h1><i class="bi bi-bank me-2"></i>Tài khoản ngân hàng</h1>
    <nav>
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= ADMIN_BASE_PATH ?>/modules/landlord/dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item active">Tài khoản ngân hàng</li>
        </ol>
    </nav>
</div>

<section class="section">
    <div class="row">
        <!-- Danh sách TK -->
        <div class="col-lg-7">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-credit-card-2-front me-2"></i>Danh sách tài khoản (<?= $accountCount ?>/<?= $maxAccounts ?>)</h5>
                </div>
                <div class="card-body">
                    <?php if ($success): ?>
                        <div class="alert alert-success"><i class="bi bi-check-circle me-2"></i><?= $success ?></div>
                    <?php endif; ?>
                    
                    <?php if ($accountCount === 0): ?>
                        <div class="text-center py-5 text-muted">
                            <i class="bi bi-bank fs-1 d-block mb-3 opacity-50"></i>
                            <p>Chưa có tài khoản ngân hàng nào</p>
                            <p>Thêm tài khoản để nhận tiền khi rút về</p>
                        </div>
                    <?php else: ?>
                        <?php mysqli_data_seek($accounts, 0); while ($acc = mysqli_fetch_assoc($accounts)): ?>
                        <div class="border rounded p-3 mb-3 <?= $acc['is_default'] ? 'border-primary bg-light' : '' ?>">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="mb-1">
                                        <?= htmlspecialchars($acc['bank_name']) ?>
                                        <?php if ($acc['is_default']): ?>
                                            <span class="badge bg-primary ms-1">Mặc định</span>
                                        <?php endif; ?>
                                    </h6>
                                    <p class="mb-1 fs-5 fw-bold"><?= htmlspecialchars($acc['bank_account']) ?></p>
                                    <p class="mb-0 text-muted"><?= htmlspecialchars($acc['bank_account_name']) ?></p>
                                </div>
                                <div class="d-flex gap-1">
                                    <?php if (!$acc['is_default']): ?>
                                    <form method="post" class="d-inline">
                                        <input type="hidden" name="set_default_id" value="<?= $acc['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-primary" title="Đặt mặc định">
                                            <i class="bi bi-star"></i>
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                    <form method="post" class="d-inline" onsubmit="return confirm('Xóa tài khoản này?')">
                                        <input type="hidden" name="delete_id" value="<?= $acc['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Xóa">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Form thêm mới -->
        <div class="col-lg-5">
            <?php if ($accountCount < $maxAccounts): ?>
            <div class="card">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0"><i class="bi bi-plus-circle me-2"></i>Thêm tài khoản mới</h5>
                </div>
                <div class="card-body pt-4">
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><i class="bi bi-x-circle me-2"></i><?= $error ?></div>
                    <?php endif; ?>
                    
                    <form method="post">
                        <div class="mb-3">
                            <label class="form-label">Ngân hàng <span class="text-danger">*</span></label>
                            <select name="bank_name" class="form-select" required>
                                <option value="">-- Chọn ngân hàng --</option>
                                <?php foreach ($banks as $code => $name): ?>
                                    <option value="<?= $code ?>"><?= htmlspecialchars($name) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Số tài khoản <span class="text-danger">*</span></label>
                            <input type="text" name="bank_account" class="form-control" 
                                   placeholder="Nhập số tài khoản (6-20 số)"
                                   pattern="\d{6,20}" required>
                        </div>
                        
                        <div class="mb-4">
                            <label class="form-label">Tên chủ tài khoản <span class="text-danger">*</span></label>
                            <input type="text" name="bank_account_name" class="form-control text-uppercase" 
                                   placeholder="VD: NGUYEN VAN A" required>
                            <small class="text-muted">Nhập không dấu, viết hoa</small>
                        </div>
                        
                        <div class="d-grid">
                            <button type="submit" class="btn btn-success">
                                <i class="bi bi-plus-lg me-2"></i>Thêm tài khoản
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            <?php else: ?>
            <div class="card">
                <div class="card-body text-center py-5">
                    <i class="bi bi-exclamation-circle fs-1 text-warning d-block mb-3"></i>
                    <p class="mb-0">Bạn đã thêm tối đa <strong><?= $maxAccounts ?></strong> tài khoản.<br>Xóa bớt để thêm mới.</p>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="card mt-3">
                <div class="card-body">
                    <h6 class="card-title"><i class="bi bi-shield-check me-2"></i>Lưu ý</h6>
                    <ul class="mb-0 small">
                        <li>Tối đa <strong><?= $maxAccounts ?></strong> tài khoản ngân hàng</li>
                        <li>Tài khoản <strong>mặc định</strong> sẽ được chọn sẵn khi rút tiền</li>
                        <li>Tên chủ TK phải trùng với tên đăng ký tại ngân hàng</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
