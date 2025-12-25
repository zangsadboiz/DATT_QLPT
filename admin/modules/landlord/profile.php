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

// Get user info
$user = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM users WHERE user_id = $userId"));

$errors = [];
$success = false;

// Process form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_info') {
        $fullName = trim($_POST['full_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        
        if (empty($fullName)) $errors[] = 'Vui lòng nhập họ tên';
        if (empty($email)) $errors[] = 'Vui lòng nhập email';
        
        // Check email unique
        if ($email !== $user['email']) {
            $emailCheck = mysqli_fetch_assoc(mysqli_query($conn, "SELECT user_id FROM users WHERE email = '" . mysqli_real_escape_string($conn, $email) . "' AND user_id != $userId"));
            if ($emailCheck) $errors[] = 'Email đã được sử dụng';
        }
        
        if (empty($errors)) {
            $fullNameEsc = mysqli_real_escape_string($conn, $fullName);
            $phoneEsc = mysqli_real_escape_string($conn, $phone);
            $emailEsc = mysqli_real_escape_string($conn, $email);
            
            mysqli_query($conn, "UPDATE users SET full_name = '$fullNameEsc', phone = '$phoneEsc', email = '$emailEsc', updated_at = NOW() WHERE user_id = $userId");
            
            $_SESSION['full_name'] = $fullName;
            $user['full_name'] = $fullName;
            $user['phone'] = $phone;
            $user['email'] = $email;
            $success = 'Cập nhật thông tin thành công!';
        }
    }
    
    if ($action === 'change_password') {
        $currentPass = $_POST['current_password'] ?? '';
        $newPass = $_POST['new_password'] ?? '';
        $confirmPass = $_POST['confirm_password'] ?? '';
        
        if (empty($currentPass)) $errors[] = 'Vui lòng nhập mật khẩu hiện tại';
        if (empty($newPass)) $errors[] = 'Vui lòng nhập mật khẩu mới';
        if (strlen($newPass) < 6) $errors[] = 'Mật khẩu mới phải có ít nhất 6 ký tự';
        if ($newPass !== $confirmPass) $errors[] = 'Xác nhận mật khẩu không khớp';
        
        if (empty($errors)) {
            if (!password_verify($currentPass, $user['password_hash'])) {
                $errors[] = 'Mật khẩu hiện tại không đúng';
            } else {
                $newHash = password_hash($newPass, PASSWORD_DEFAULT);
                mysqli_query($conn, "UPDATE users SET password_hash = '$newHash', updated_at = NOW() WHERE user_id = $userId");
                $success = 'Đổi mật khẩu thành công!';
            }
        }
    }
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="pagetitle">
    <h1><i class="bi bi-person me-2"></i>Thông tin cá nhân</h1>
    <nav>
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= ADMIN_BASE_PATH ?>/modules/landlord/dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item active">Thông tin cá nhân</li>
        </ol>
    </nav>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <ul class="mb-0">
            <?php foreach ($errors as $err): ?>
                <li><?= htmlspecialchars($err) ?></li>
            <?php endforeach; ?>
        </ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <?= htmlspecialchars($success) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<section class="section">
    <div class="row">
        
        <!-- Profile Info -->
        <div class="col-lg-8">
            <div class="card mb-3">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-person-badge me-2"></i>Thông tin tài khoản</h5>
                </div>
                <div class="card-body">
                    <form action="" method="POST">
                        <input type="hidden" name="action" value="update_info">
                        
                        <div class="row mb-3">
                            <label class="col-sm-3 col-form-label">Tên đăng nhập</label>
                            <div class="col-sm-9">
                                <input type="text" class="form-control" value="<?= htmlspecialchars($user['username']) ?>" disabled>
                                <small class="text-muted">Không thể thay đổi</small>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <label class="col-sm-3 col-form-label">Họ và tên <span class="text-danger">*</span></label>
                            <div class="col-sm-9">
                                <input type="text" name="full_name" class="form-control" required
                                       value="<?= htmlspecialchars($user['full_name']) ?>">
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <label class="col-sm-3 col-form-label">Email <span class="text-danger">*</span></label>
                            <div class="col-sm-9">
                                <input type="email" name="email" class="form-control" required
                                       value="<?= htmlspecialchars($user['email']) ?>">
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <label class="col-sm-3 col-form-label">Số điện thoại</label>
                            <div class="col-sm-9">
                                <input type="text" name="phone" class="form-control"
                                       value="<?= htmlspecialchars($user['phone'] ?? '') ?>" placeholder="0901234567">
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-sm-9 offset-sm-3">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-check-circle me-2"></i>Lưu thay đổi
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Change Password -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-key me-2"></i>Đổi mật khẩu</h5>
                </div>
                <div class="card-body">
                    <form action="" method="POST">
                        <input type="hidden" name="action" value="change_password">
                        
                        <div class="row mb-3">
                            <label class="col-sm-3 col-form-label">Mật khẩu hiện tại</label>
                            <div class="col-sm-9">
                                <input type="password" name="current_password" class="form-control" required>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <label class="col-sm-3 col-form-label">Mật khẩu mới</label>
                            <div class="col-sm-9">
                                <input type="password" name="new_password" class="form-control" required minlength="6">
                                <small class="text-muted">Ít nhất 6 ký tự</small>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <label class="col-sm-3 col-form-label">Xác nhận mật khẩu</label>
                            <div class="col-sm-9">
                                <input type="password" name="confirm_password" class="form-control" required>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-sm-9 offset-sm-3">
                                <button type="submit" class="btn btn-warning">
                                    <i class="bi bi-key me-2"></i>Đổi mật khẩu
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Sidebar -->
        <div class="col-lg-4">
            <div class="card mb-3">
                <div class="card-body text-center py-4">
                    <div class="d-inline-flex align-items-center justify-content-center bg-primary text-white rounded-circle mb-3" 
                         style="width: 80px; height: 80px; font-size: 32px;">
                        <?= strtoupper(mb_substr($user['full_name'], 0, 1)) ?>
                    </div>
                    <h5><?= htmlspecialchars($user['full_name']) ?></h5>
                    <p class="text-muted mb-0">@<?= htmlspecialchars($user['username']) ?></p>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header"><h6 class="mb-0">Thông tin tài khoản</h6></div>
                <ul class="list-group list-group-flush">
                    <li class="list-group-item d-flex justify-content-between">
                        <span>Số dư</span>
                        <strong class="text-success"><?= number_format((float)$user['balance'], 0, ',', '.') ?>đ</strong>
                    </li>
                    <li class="list-group-item d-flex justify-content-between">
                        <span>Trạng thái</span>
                        <?php if ($user['is_active']): ?>
                            <span class="badge bg-success">Hoạt động</span>
                        <?php else: ?>
                            <span class="badge bg-danger">Bị khóa</span>
                        <?php endif; ?>
                    </li>
                    <li class="list-group-item d-flex justify-content-between">
                        <span>Ngày tham gia</span>
                        <span><?= date('d/m/Y', strtotime($user['created_at'])) ?></span>
                    </li>
                </ul>
            </div>
        </div>
        
    </div>
</section>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
