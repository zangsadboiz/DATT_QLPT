<?php
// client/pages/login.php - Đăng nhập Sinh viên (Template Hotelier)
// Lưu ý: Logic POST và redirect đã được xử lý ở index.php

$type = $_GET['type'] ?? 'student';
$return = $_GET['return'] ?? '/quanlyphongtro/client/index.php?page=home';

// Xử lý lỗi hiển thị khi login thất bại
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = (string)($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        $error = 'Vui lòng nhập tài khoản và mật khẩu.';
    } else {
        // Kiểm tra lỗi cụ thể
        $sql = "
          SELECT u.user_id, u.full_name, u.username, u.password_hash, u.is_active, r.role_name
          FROM users u
          JOIN roles r ON r.role_id=u.role_id
          WHERE u.username=? OR u.email=?
          LIMIT 1
        ";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 'ss', $username, $username);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $u = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);

        if (!$u) {
            $error = 'Tài khoản không tồn tại.';
        } elseif ((int)$u['is_active'] !== 1) {
            $error = 'Tài khoản đang bị khóa hoặc chưa kích hoạt.';
        } elseif (($u['role_name'] ?? '') !== 'STUDENT') {
            $error = 'Trang này chỉ dành cho tài khoản Sinh viên.';
        } elseif (!password_verify($password, $u['password_hash'])) {
            $error = 'Sai mật khẩu.';
        }
        // Nếu không có lỗi, đã redirect ở index.php
    }
}
?>

<!-- Page Header Start -->
<div class="container-fluid page-header mb-5 p-0" style="background-image: url(/quanlyphongtro/hotelier-1.0.0/img/carousel-1.jpg);">
    <div class="container-fluid page-header-inner py-5">
        <div class="container text-center pb-5">
            <h1 class="display-3 text-white mb-3 animated slideInDown">Đăng nhập</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb justify-content-center text-uppercase">
                    <li class="breadcrumb-item"><a href="/quanlyphongtro/client/index.php?page=home" class="text-white">Trang chủ</a></li>
                    <li class="breadcrumb-item text-white active" aria-current="page">Đăng nhập</li>
                </ol>
            </nav>
        </div>
    </div>
</div>
<!-- Page Header End -->

<div class="container-xxl py-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-5 col-md-7">
                <div class="wow fadeInUp" data-wow-delay="0.1s">
                    <div class="bg-light rounded p-4 p-sm-5">
                        <h3 class="mb-4 text-center"><i class="fa fa-user-graduate text-primary me-2"></i>Dành cho Sinh viên</h3>

                        <?php if ($error): ?>
                            <div class="alert alert-danger"><i class="bi bi-exclamation-triangle-fill me-2"></i><?= htmlspecialchars($error) ?></div>
                        <?php endif; ?>

                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label">Tài khoản / Email</label>
                                <input type="text" class="form-control py-3" name="username" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required autofocus>
                            </div>
                            <div class="mb-4">
                                <label class="form-label">Mật khẩu</label>
                                <input type="password" class="form-control py-3" name="password" required>
                            </div>
                            <button type="submit" class="btn btn-primary w-100 py-3">
                                <i class="fa fa-sign-in-alt me-2"></i>Đăng nhập
                            </button>
                        </form>

                        <div class="mt-4 text-center">
                            <p class="mb-2">Chưa có tài khoản?
                                <a href="/quanlyphongtro/client/index.php?page=register&type=student" class="text-primary">Đăng ký ngay</a>
                            </p>
                            <p class="mb-0">
                                <a href="/quanlyphongtro/admin/login.php?type=landlord" class="text-muted">
                                    <i class="fa fa-home me-1"></i>Đăng nhập cho Chủ trọ
                                </a>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
