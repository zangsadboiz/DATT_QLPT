<?php
/**
 * Thêm chủ trọ mới
 */
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';

$role = (string)($_SESSION['role_name'] ?? '');
if (!in_array($role, ['ADMIN', 'STAFF'], true)) {
    header('Location: ' . ADMIN_BASE_PATH . '/index.php');
    exit;
}

$errors = [];
$fullName = '';
$email = '';
$phone = '';
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = trim($_POST['full_name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $phone    = trim($_POST['phone'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    // Validation
    if (empty($fullName)) $errors[] = 'Vui lòng nhập họ tên';
    if (empty($email)) $errors[] = 'Vui lòng nhập email';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Email không hợp lệ';
    if (empty($phone)) $errors[] = 'Vui lòng nhập số điện thoại';
    if (empty($username)) $errors[] = 'Vui lòng nhập username';
    if (strlen($password) < 6) $errors[] = 'Mật khẩu phải có ít nhất 6 ký tự';
    if ($password !== $confirmPassword) $errors[] = 'Mật khẩu xác nhận không khớp';
    
    // Check unique
    if (empty($errors)) {
        $chkStmt = mysqli_prepare($conn, "SELECT user_id FROM users WHERE username = ? OR email = ? OR phone = ?");
        mysqli_stmt_bind_param($chkStmt, "sss", $username, $email, $phone);
        mysqli_stmt_execute($chkStmt);
        $chkRs = mysqli_stmt_get_result($chkStmt);
        if ($chkRs && mysqli_fetch_assoc($chkRs)) {
            $errors[] = 'Username, email hoặc SĐT đã tồn tại trong hệ thống!';
        }
        mysqli_stmt_close($chkStmt);
    }
    
    // Insert
    if (empty($errors)) {
        $roleId = 2; // LANDLORD - phải khớp với role_id trong index.php
        $hash = password_hash($password, PASSWORD_DEFAULT);
        
        $sql = "INSERT INTO users (role_id, full_name, email, phone, username, password_hash, is_active, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, 1, NOW())";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "isssss", $roleId, $fullName, $email, $phone, $username, $hash);
        
        if (mysqli_stmt_execute($stmt)) {
            $_SESSION['alert'] = ['type' => 'success', 'message' => 'Thêm chủ trọ thành công!'];
            header('Location: ' . ADMIN_BASE_PATH . '/modules/users/index.php');
            exit;
        } else {
            $errors[] = 'Lỗi khi thêm: ' . mysqli_error($conn);
        }
        mysqli_stmt_close($stmt);
    }
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="pagetitle">
    <h1><i class="bi bi-person-plus me-2"></i>Thêm chủ trọ mới</h1>
    <nav>
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= ADMIN_BASE_PATH ?>/index.php">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="<?= ADMIN_BASE_PATH ?>/modules/users/index.php">Chủ trọ</a></li>
            <li class="breadcrumb-item active">Thêm mới</li>
        </ol>
    </nav>
</div>

<section class="section">
    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Thông tin chủ trọ</h5>
                    <a href="<?= ADMIN_BASE_PATH ?>/modules/users/index.php" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-arrow-left me-1"></i>Quay lại
                    </a>
                </div>
                <div class="card-body pt-4">
                    
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                <?php foreach ($errors as $e): ?>
                                    <li><?= htmlspecialchars($e) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Họ tên <span class="text-danger">*</span></label>
                                <input type="text" name="full_name" class="form-control" required
                                       value="<?= htmlspecialchars($fullName) ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Username <span class="text-danger">*</span></label>
                                <input type="text" name="username" class="form-control" required
                                       value="<?= htmlspecialchars($username) ?>" pattern="[a-zA-Z0-9_]+">
                                <small class="text-muted">Chỉ chữ, số và dấu gạch dưới</small>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Email <span class="text-danger">*</span></label>
                                <input type="email" name="email" class="form-control" required
                                       value="<?= htmlspecialchars($email) ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Số điện thoại <span class="text-danger">*</span></label>
                                <input type="tel" name="phone" class="form-control" required
                                       value="<?= htmlspecialchars($phone) ?>">
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Mật khẩu <span class="text-danger">*</span></label>
                                <input type="password" name="password" class="form-control" required minlength="6">
                                <small class="text-muted">Ít nhất 6 ký tự</small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Xác nhận mật khẩu <span class="text-danger">*</span></label>
                                <input type="password" name="confirm_password" class="form-control" required>
                            </div>
                        </div>
                        
                        <div class="mt-4">
                            <button type="submit" class="btn btn-success">
                                <i class="bi bi-check-circle me-1"></i>Thêm chủ trọ
                            </button>
                            <a href="<?= ADMIN_BASE_PATH ?>/modules/users/index.php" class="btn btn-secondary ms-2">Hủy</a>
                        </div>
                    </form>
                    
                </div>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
