<?php
/**
 * Cấu hình Hoa hồng - Admin
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/platform.php';

$role = $_SESSION['role_name'] ?? '';
if ($role !== 'ADMIN') {
    header('Location: /quanlyphongtro/admin/index.php?error=no_permission');
    exit;
}

$success = '';
$error = '';

// Xử lý form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
        $minAmount = (float)($_POST['min_amount'] ?? 0);
        $maxAmount = trim($_POST['max_amount'] ?? '') !== '' ? (float)$_POST['max_amount'] : null;
        $rate = (float)($_POST['rate'] ?? 0);
        $desc = trim($_POST['description'] ?? '');
        
        if ($rate <= 0 || $rate > 100) {
            $error = 'Tỷ lệ hoa hồng phải từ 0.01% đến 100%';
        } else {
            $maxSql = $maxAmount !== null ? $maxAmount : 'NULL';
            $descSafe = mysqli_real_escape_string($conn, $desc);
            
            mysqli_query($conn, "
                INSERT INTO commission_tiers (min_amount, max_amount, rate, description)
                VALUES ($minAmount, $maxSql, $rate, '$descSafe')
            ");
            $success = 'Đã thêm mức hoa hồng mới';
        }
    }
    
    if ($action === 'delete') {
        $id = (int)$_POST['id'];
        mysqli_query($conn, "DELETE FROM commission_tiers WHERE id = $id");
        $success = 'Đã xóa mức hoa hồng';
    }
    
    if ($action === 'toggle') {
        $id = (int)$_POST['id'];
        mysqli_query($conn, "UPDATE commission_tiers SET is_active = 1 - is_active WHERE id = $id");
        $success = 'Đã cập nhật trạng thái';
    }
}

// Lấy danh sách tiers
$tiers = mysqli_query($conn, "SELECT * FROM commission_tiers ORDER BY min_amount ASC");

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="pagetitle">
    <h1><i class="bi bi-sliders me-2"></i>Cấu hình Hoa hồng</h1>
    <nav>
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= ADMIN_BASE_PATH ?>/index.php">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="index.php">Hoa hồng</a></li>
            <li class="breadcrumb-item active">Cấu hình</li>
        </ol>
    </nav>
</div>

<?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <i class="bi bi-check-circle me-2"></i><?= $success ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <i class="bi bi-x-circle me-2"></i><?= $error ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="row">
    <!-- Form thêm -->
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0"><i class="bi bi-plus-circle me-2"></i>Thêm mức hoa hồng</h5>
            </div>
            <div class="card-body">
                <form method="post">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="mb-3">
                        <label class="form-label">Số tiền từ <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="number" name="min_amount" class="form-control" 
                                   min="0" step="100000" value="0" required>
                            <span class="input-group-text">đ</span>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Số tiền đến <small class="text-muted">(để trống = không giới hạn)</small></label>
                        <div class="input-group">
                            <input type="number" name="max_amount" class="form-control" 
                                   min="0" step="100000" placeholder="Không giới hạn">
                            <span class="input-group-text">đ</span>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Tỷ lệ hoa hồng <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="number" name="rate" class="form-control" 
                                   min="0.01" max="100" step="0.01" required>
                            <span class="input-group-text">%</span>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Mô tả</label>
                        <input type="text" name="description" class="form-control" 
                               placeholder="VD: Dưới 1 triệu">
                    </div>
                    
                    <div class="d-grid">
                        <button type="submit" class="btn btn-success">
                            <i class="bi bi-plus-lg me-2"></i>Thêm mức
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Info -->
        <div class="card mt-3">
            <div class="card-body">
                <h6><i class="bi bi-info-circle me-2"></i>Hướng dẫn</h6>
                <ul class="small mb-0">
                    <li>Mức giá <strong>cao hơn</strong> thường có tỷ lệ hoa hồng <strong>thấp hơn</strong></li>
                    <li>VD: &lt;1tr = 10%, 1-5tr = 8%, 5-10tr = 5%, &gt;10tr = 3%</li>
                    <li>Hoa hồng tối thiểu: <strong><?= number_format(PLATFORM_MIN_COMMISSION, 0, ',', '.') ?>đ</strong></li>
                </ul>
            </div>
        </div>
    </div>
    
    <!-- Danh sách -->
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-list-ol me-2"></i>Các mức hoa hồng</h5>
                <a href="index.php" class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-graph-up me-1"></i>Xem báo cáo
                </a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Mức giá</th>
                                <th class="text-center">Tỷ lệ</th>
                                <th>Mô tả</th>
                                <th class="text-center">Trạng thái</th>
                                <th class="text-center">Hành động</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $hasTier = false; while ($tiers && $t = mysqli_fetch_assoc($tiers)): $hasTier = true; ?>
                            <tr class="<?= $t['is_active'] ? '' : 'table-secondary' ?>">
                                <td>
                                    <strong><?= number_format($t['min_amount'], 0, ',', '.') ?>đ</strong>
                                    <?php if ($t['max_amount']): ?>
                                        → <?= number_format($t['max_amount'], 0, ',', '.') ?>đ
                                    <?php else: ?>
                                        <span class="text-muted">trở lên</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-success fs-6"><?= number_format($t['rate'], 2) ?>%</span>
                                </td>
                                <td><?= htmlspecialchars($t['description'] ?? '') ?></td>
                                <td class="text-center">
                                    <?php if ($t['is_active']): ?>
                                        <span class="badge bg-success">Hoạt động</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Tắt</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <form method="post" class="d-inline">
                                        <input type="hidden" name="action" value="toggle">
                                        <input type="hidden" name="id" value="<?= $t['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-<?= $t['is_active'] ? 'warning' : 'success' ?>" 
                                                title="<?= $t['is_active'] ? 'Tắt' : 'Bật' ?>">
                                            <i class="bi bi-<?= $t['is_active'] ? 'pause' : 'play' ?>"></i>
                                        </button>
                                    </form>
                                    <form method="post" class="d-inline" onsubmit="return confirm('Xóa mức hoa hồng này?')">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= $t['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                            <?php if (!$hasTier): ?>
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">
                                    <i class="bi bi-inbox fs-1 d-block mb-2 opacity-50"></i>
                                    Chưa có mức hoa hồng nào.<br>
                                    <small>Hệ thống sẽ dùng mức mặc định: <strong><?= PLATFORM_COMMISSION_RATE ?>%</strong></small>
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- Preview -->
        <div class="card mt-3">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-calculator me-2"></i>Thử tính hoa hồng</h5>
            </div>
            <div class="card-body">
                <div class="row align-items-end">
                    <div class="col-md-4">
                        <label class="form-label">Nhập số tiền</label>
                        <div class="input-group">
                            <input type="text" id="testAmount" class="form-control" placeholder="VD: 2.000.000">
                            <span class="input-group-text">đ</span>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <button type="button" id="testBtn" class="btn btn-primary w-100">Tính</button>
                    </div>
                    <div class="col-md-6">
                        <div id="testResult" class="alert alert-info mb-0" style="display: none;"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const tiers = <?= json_encode(get_commission_tiers()) ?>;
const minCommission = <?= PLATFORM_MIN_COMMISSION ?>;

function formatNumber(num) {
    return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
}

function parseNumber(str) {
    return parseInt(str.replace(/\./g, '')) || 0;
}

function getRate(amount) {
    for (let i = tiers.length - 1; i >= 0; i--) {
        const t = tiers[i];
        if (amount >= t.min && (t.max === null || amount < t.max)) {
            return t.rate;
        }
    }
    return <?= PLATFORM_COMMISSION_RATE ?>;
}

document.getElementById('testBtn').addEventListener('click', function() {
    const amount = parseNumber(document.getElementById('testAmount').value);
    if (amount <= 0) return;
    
    const rate = getRate(amount);
    let commission = Math.max(amount * rate / 100, minCommission);
    commission = Math.min(commission, amount);
    const net = amount - commission;
    
    document.getElementById('testResult').style.display = 'block';
    document.getElementById('testResult').innerHTML = `
        <strong>Số tiền:</strong> ${formatNumber(amount)}đ × <strong>${rate}%</strong> = 
        <span class="text-danger">-${formatNumber(Math.round(commission))}đ</span> → 
        <span class="text-success fw-bold">Thực nhận: ${formatNumber(Math.round(net))}đ</span>
    `;
});

document.getElementById('testAmount').addEventListener('input', function() {
    let value = parseNumber(this.value);
    if (value > 0) this.value = formatNumber(value);
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
