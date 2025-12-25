<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';

$error = '';
$type = $_GET['type'] ?? 'admin'; // admin or landlord

// Validate type
if (!in_array($type, ['admin', 'landlord'])) {
    $type = 'admin';
}

$isLandlord = ($type === 'landlord');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if ($username && $password) {
        $usernameEsc = mysqli_real_escape_string($conn, $username);
        
        $result = mysqli_query($conn, "
            SELECT u.*, r.role_name 
            FROM users u 
            JOIN roles r ON r.role_id = u.role_id
            WHERE u.username = '$usernameEsc' OR u.email = '$usernameEsc'
        ");
        
        if ($result && $user = mysqli_fetch_assoc($result)) {
            // Check role matches login type
            $roleMatch = ($isLandlord && $user['role_name'] === 'LANDLORD') || 
                         (!$isLandlord && $user['role_name'] === 'ADMIN');
            
            if (!$roleMatch) {
                $error = $isLandlord 
                    ? 'Tài khoản này không phải Chủ trọ.' 
                    : 'Tài khoản này không phải Admin.';
            } elseif (password_verify($password, $user['password_hash'])) {
                if ($user['is_active']) {
                    $_SESSION['user_id'] = $user['user_id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['full_name'] = $user['full_name'];
                    $_SESSION['role_id'] = $user['role_id'];
                    $_SESSION['role_name'] = $user['role_name'];
                    $_SESSION['admin_logged_in'] = true;
                    
                    mysqli_query($conn, "UPDATE users SET last_login = NOW() WHERE user_id = {$user['user_id']}");
                    
                    if ($user['role_name'] === 'LANDLORD') {
                        header('Location: ' . ADMIN_BASE_PATH . '/modules/landlord/dashboard.php');
                    } else {
                        header('Location: ' . ADMIN_BASE_PATH . '/index.php');
                    }
                    exit;
                } else {
                    $error = 'Tài khoản đã bị khóa.';
                }
            } else {
                $error = 'Mật khẩu không đúng';
            }
        } else {
            $error = 'Tài khoản không tồn tại';
        }
    } else {
        $error = 'Vui lòng nhập tài khoản và mật khẩu';
    }
}

$title = $isLandlord ? 'Đăng nhập Chủ trọ' : 'Đăng nhập Admin';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $title ?> | Quản lý phòng trọ</title>
    <link href="/quanlyphongtro/admin/assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="/quanlyphongtro/admin/assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background: #f5f5f5; min-height: 100vh; display: flex; align-items: center; }
        .login-card { max-width: 400px; margin: auto; }
    </style>
</head>
<body>

<div class="container">
    <div class="card login-card shadow-sm">
        <div class="card-header bg-white text-center py-3">
            <h5 class="mb-0">
                <?php if ($isLandlord): ?>
                    <i class="bi bi-house-door me-2"></i>Đăng nhập Chủ trọ
                <?php else: ?>
                    <i class="bi bi-shield-lock me-2"></i>Đăng nhập Admin
                <?php endif; ?>
            </h5>
        </div>
        <div class="card-body p-4">
            
            <?php if ($error): ?>
                <div class="alert alert-danger py-2">
                    <i class="bi bi-exclamation-triangle me-1"></i><?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>
            
            <form action="" method="POST">
                <div class="mb-3">
                    <label class="form-label">Tài khoản hoặc Email</label>
                    <input type="text" name="username" class="form-control" required autofocus
                           value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Mật khẩu</label>
                    <input type="password" name="password" class="form-control" required>
                </div>
                
                <button type="submit" class="btn btn-primary w-100">
                    <i class="bi bi-box-arrow-in-right me-1"></i>Đăng nhập
                </button>
            </form>
            
            <?php if ($isLandlord): ?>
                <hr>
                <div class="text-center">
                    <span class="text-muted">Chưa có tài khoản?</span>
                    <a href="register.php" class="ms-1">Đăng ký</a>
                </div>
            <?php endif; ?>
            
        </div>
        <div class="card-footer bg-white text-center py-2">
            <?php if ($isLandlord): ?>
                <a href="login.php?type=admin" class="text-muted small">Đăng nhập Admin</a>
            <?php else: ?>
                <a href="login.php?type=landlord" class="text-muted small">Đăng nhập Chủ trọ</a>
            <?php endif; ?>
        </div>
    </div>
</div>

</body>
</html>
