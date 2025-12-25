<?php
// client/pages/register.php - Đăng ký Sinh viên (Template Hotelier)

$type = $_GET['type'] ?? 'student';
if ($type !== 'student') {
    header('Location: /quanlyphongtro/admin/login.php?type=landlord');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = (string)($_POST['password'] ?? '');
    $confirmPassword = (string)($_POST['confirm_password'] ?? '');

    // Validation
    if ($fullName === '' || $email === '' || $username === '' || $password === '') {
        $error = 'Vui lòng điền đầy đủ thông tin bắt buộc.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Email không hợp lệ.';
    } elseif (strlen($password) < 6) {
        $error = 'Mật khẩu phải có ít nhất 6 ký tự.';
    } elseif ($password !== $confirmPassword) {
        $error = 'Mật khẩu xác nhận không khớp.';
    } else {
        // Check if username or email exists
        $usernameEsc = mysqli_real_escape_string($conn, $username);
        $emailEsc = mysqli_real_escape_string($conn, $email);
        
        $checkSql = "SELECT user_id FROM users WHERE username='$usernameEsc' OR email='$emailEsc' LIMIT 1";
        $checkRes = mysqli_query($conn, $checkSql);
        
        if ($checkRes && mysqli_fetch_assoc($checkRes)) {
            $error = 'Tên đăng nhập hoặc email đã tồn tại.';
        } else {
            // Get STUDENT role_id
            $roleRes = mysqli_query($conn, "SELECT role_id FROM roles WHERE role_name='STUDENT' LIMIT 1");
            $role = $roleRes ? mysqli_fetch_assoc($roleRes) : null;
            $roleId = $role ? (int)$role['role_id'] : 3; // default 3 for STUDENT
            
            // Insert new user
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            $fullNameEsc = mysqli_real_escape_string($conn, $fullName);
            $phoneEsc = mysqli_real_escape_string($conn, $phone);
            
            $insertSql = "
                INSERT INTO users (username, email, password_hash, full_name, phone, role_id, is_active, created_at)
                VALUES ('$usernameEsc', '$emailEsc', '$passwordHash', '$fullNameEsc', " . 
                ($phone !== '' ? "'$phoneEsc'" : "NULL") . ", $roleId, 1, NOW())
            ";
            
            if (mysqli_query($conn, $insertSql)) {
                $success = 'Đăng ký thành công! Bạn có thể đăng nhập ngay bây giờ.';
            } else {
                $error = 'Lỗi tạo tài khoản: ' . mysqli_error($conn);
            }
        }
    }
}
?>

<!-- Page Header Start -->
<div class="container-fluid page-header mb-5 p-0" style="background-image: url(/quanlyphongtro/hotelier-1.0.0/img/carousel-2.jpg);">
    <div class="container-fluid page-header-inner py-5">
        <div class="container text-center pb-5">
            <h1 class="display-3 text-white mb-3 animated slideInDown">Đăng ký tài khoản</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb justify-content-center text-uppercase">
                    <li class="breadcrumb-item"><a href="/quanlyphongtro/client/index.php?page=home" class="text-white">Trang chủ</a></li>
                    <li class="breadcrumb-item text-white active" aria-current="page">Đăng ký</li>
                </ol>
            </nav>
        </div>
    </div>
</div>
<!-- Page Header End -->

<div class="container-xxl py-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-6">
                <div class="auth-card bg-white rounded overflow-hidden shadow wow fadeInUp" data-wow-delay="0.1s">
                    <!-- Card Header -->
                    <div class="bg-primary text-white text-center py-4">
                        <i class="fas fa-user-plus fa-3x mb-3"></i>
                        <h3 class="mb-0">Đăng ký Sinh viên</h3>
                    </div>
                    
                    <!-- Card Body -->
                    <div class="p-4 p-md-5">
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($error) ?></div>
                        <?php endif; ?>
                        
                        <?php if ($success): ?>
                            <div class="alert alert-success"><i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($success) ?></div>
                            <div class="text-center">
                                <a href="/quanlyphongtro/client/index.php?page=login&type=student" class="btn btn-primary btn-lg">
                                    <i class="fas fa-sign-in-alt me-2"></i>Đăng nhập ngay
                                </a>
                            </div>
                        <?php else: ?>

                        <form method="post" action="/quanlyphongtro/client/index.php?page=register&type=student">
                            <div class="mb-3">
                                <label class="form-label"><i class="fas fa-user me-2"></i>Họ và tên <span class="text-danger">*</span></label>
                                <input name="full_name" type="text" class="form-control form-control-lg" 
                                       value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>" 
                                       placeholder="Nhập họ và tên" required>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label"><i class="fas fa-envelope me-2"></i>Email <span class="text-danger">*</span></label>
                                    <input name="email" type="email" class="form-control form-control-lg" 
                                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" 
                                           placeholder="email@example.com" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label"><i class="fas fa-phone me-2"></i>Số điện thoại</label>
                                    <input name="phone" type="tel" class="form-control form-control-lg" 
                                           value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>" 
                                           placeholder="0123 456 789">
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label"><i class="fas fa-at me-2"></i>Tên đăng nhập <span class="text-danger">*</span></label>
                                <input name="username" type="text" class="form-control form-control-lg" 
                                       value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" 
                                       placeholder="Tên đăng nhập (không dấu, không khoảng trắng)" required>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label"><i class="fas fa-lock me-2"></i>Mật khẩu <span class="text-danger">*</span></label>
                                    <input name="password" type="password" class="form-control form-control-lg" 
                                           placeholder="Ít nhất 6 ký tự" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label"><i class="fas fa-lock me-2"></i>Xác nhận mật khẩu <span class="text-danger">*</span></label>
                                    <input name="confirm_password" type="password" class="form-control form-control-lg" 
                                           placeholder="Nhập lại mật khẩu" required>
                                </div>
                            </div>

                            <button class="btn btn-primary w-100 py-3" type="submit">
                                <i class="fas fa-user-plus me-2"></i>Đăng ký
                            </button>
                        </form>

                        <hr class="my-4">

                        <div class="text-center">
                            <p class="mb-0">Đã có tài khoản? 
                                <a href="/quanlyphongtro/client/index.php?page=login&type=student" class="text-primary fw-bold">Đăng nhập</a>
                            </p>
                        </div>
                        
                        <div class="text-center mt-3">
                            <p class="mb-0 text-muted small">Bạn là chủ trọ? 
                                <a href="/quanlyphongtro/admin/login.php?type=landlord" class="text-dark">Đăng ký/đăng nhập chủ trọ</a>
                            </p>
                        </div>
                        
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
