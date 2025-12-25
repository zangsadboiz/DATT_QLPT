<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';

$error = '';
$success = '';

// Lấy role_id của LANDLORD
$roleResult = mysqli_query($conn, "SELECT role_id FROM roles WHERE role_name = 'LANDLORD' LIMIT 1");
$landlordRole = mysqli_fetch_assoc($roleResult);
$landlordRoleId = $landlordRole['role_id'] ?? 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    $errors = [];
    
    // Validation
    if (mb_strlen($fullName) < 3) {
        $errors[] = 'Họ tên phải có ít nhất 3 ký tự';
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Email không hợp lệ';
    }
    
    if ($phone !== '' && !preg_match('/^(0|\+84)[0-9]{9,10}$/', $phone)) {
        $errors[] = 'Số điện thoại không hợp lệ';
    }
    
    if (mb_strlen($username) < 4) {
        $errors[] = 'Tên đăng nhập phải có ít nhất 4 ký tự';
    } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        $errors[] = 'Tên đăng nhập chỉ được chứa chữ cái, số và dấu gạch dưới';
    }
    
    if (mb_strlen($password) < 6) {
        $errors[] = 'Mật khẩu phải có ít nhất 6 ký tự';
    }
    
    if ($password !== $confirmPassword) {
        $errors[] = 'Mật khẩu xác nhận không khớp';
    }
    
    // Check duplicate username/email
    if (empty($errors)) {
        $usernameEsc = mysqli_real_escape_string($conn, $username);
        $emailEsc = mysqli_real_escape_string($conn, $email);
        
        $checkResult = mysqli_query($conn, "SELECT user_id FROM users WHERE username = '$usernameEsc' OR email = '$emailEsc'");
        if ($checkResult && mysqli_num_rows($checkResult) > 0) {
            $errors[] = 'Tên đăng nhập hoặc email đã tồn tại';
        }
    }
    
    if (!empty($errors)) {
        $error = implode('<br>', $errors);
    } else {
        // Create user
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        
        $stmt = mysqli_prepare($conn, "INSERT INTO users (username, email, password_hash, full_name, phone, role_id, is_active, created_at) VALUES (?, ?, ?, ?, ?, ?, 1, NOW())");
        mysqli_stmt_bind_param($stmt, 'sssssi', $username, $email, $passwordHash, $fullName, $phone, $landlordRoleId);
        
        if (mysqli_stmt_execute($stmt)) {
            $success = 'Đăng ký thành công! Bạn có thể đăng nhập ngay.';
        } else {
            $error = 'Có lỗi xảy ra, vui lòng thử lại.';
        }
        mysqli_stmt_close($stmt);
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng ký Chủ trọ | Quản lý phòng trọ</title>
    <link href="/quanlyphongtro/admin/assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="/quanlyphongtro/admin/assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background: #f5f5f5; min-height: 100vh; display: flex; align-items: center; padding: 20px 0; }
        .register-card { max-width: 450px; margin: auto; }
    </style>
</head>
<body>

<div class="container">
    <div class="card register-card shadow-sm">
        <div class="card-header bg-white text-center py-3">
            <h5 class="mb-0">
                <i class="bi bi-person-plus me-2"></i>Đăng ký Chủ trọ
            </h5>
        </div>
        <div class="card-body p-4">
            
            <?php if ($error): ?>
                <div class="alert alert-danger py-2"><?= $error ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success py-2">
                    <?= htmlspecialchars($success) ?>
                    <br><a href="login.php?type=landlord">Đăng nhập ngay</a>
                </div>
            <?php else: ?>
            
            <form action="" method="POST">
                <div class="mb-3">
                    <label class="form-label">Họ tên <span class="text-danger">*</span></label>
                    <input type="text" name="full_name" class="form-control" required
                           value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>">
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Email <span class="text-danger">*</span></label>
                    <input type="email" name="email" class="form-control" required
                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Số điện thoại</label>
                    <input type="tel" name="phone" class="form-control"
                           value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Tên đăng nhập <span class="text-danger">*</span></label>
                    <input type="text" name="username" class="form-control" required
                           value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
                    <small class="text-muted">Chỉ chữ cái, số và dấu gạch dưới</small>
                </div>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Mật khẩu <span class="text-danger">*</span></label>
                        <input type="password" name="password" class="form-control" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Xác nhận <span class="text-danger">*</span></label>
                        <input type="password" name="confirm_password" class="form-control" required>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary w-100">
                    <i class="bi bi-person-plus me-1"></i>Đăng ký
                </button>
            </form>
            
            <?php endif; ?>
            
        </div>
        <div class="card-footer bg-white text-center py-2">
            <span class="text-muted">Đã có tài khoản?</span>
            <a href="login.php?type=landlord" class="ms-1">Đăng nhập</a>
        </div>
    </div>
</div>

</body>
</html>
