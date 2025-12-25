<?php
/**
 * Sửa thông tin chủ trọ
 */
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';

$role = (string)($_SESSION['role_name'] ?? '');
if (!in_array($role, ['ADMIN', 'STAFF'], true)) {
    header('Location: ' . ADMIN_BASE_PATH . '/index.php');
    exit;
}

$userId = (int)($_GET['id'] ?? 0);
if ($userId <= 0) {
    $_SESSION['alert'] = ['type' => 'danger', 'message' => 'ID không hợp lệ!'];
    header('Location: ' . ADMIN_BASE_PATH . '/modules/users/index.php');
    exit;
}

// Lấy thông tin user
$stmt = mysqli_prepare($conn, "SELECT * FROM users WHERE user_id = ? AND role_id = 2");
mysqli_stmt_bind_param($stmt, "i", $userId);
mysqli_stmt_execute($stmt);
$rs = mysqli_stmt_get_result($stmt);
$user = $rs ? mysqli_fetch_assoc($rs) : null;
mysqli_stmt_close($stmt);

if (!$user) {
    $_SESSION['alert'] = ['type' => 'danger', 'message' => 'Không tìm thấy chủ trọ!'];
    header('Location: ' . ADMIN_BASE_PATH . '/modules/users/index.php');
    exit;
}

$errors = [];
$fullName = $user['full_name'];
$email = $user['email'];
$phone = $user['phone'];
$username = $user['username'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = trim($_POST['full_name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $phone    = trim($_POST['phone'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    // Validation
    if (empty($fullName)) $errors[] = 'Vui lòng nhập họ tên';
    if (empty($email)) $errors[] = 'Vui lòng nhập email';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Email không hợp lệ';
    if (empty($phone)) $errors[] = 'Vui lòng nhập số điện thoại';
    if (empty($username)) $errors[] = 'Vui lòng nhập username';
    
    // Check unique (exclude current user)
    if (empty($errors)) {
        $chkStmt = mysqli_prepare($conn, "SELECT user_id FROM users WHERE (username = ? OR email = ? OR phone = ?) AND user_id != ?");
        mysqli_stmt_bind_param($chkStmt, "sssi", $username, $email, $phone, $userId);
        mysqli_stmt_execute($chkStmt);
        $chkRs = mysqli_stmt_get_result($chkStmt);
        if ($chkRs && mysqli_fetch_assoc($chkRs)) {
            $errors[] = 'Username, email hoặc SĐT đã tồn tại (trùng với người khác)!';
        }
        mysqli_stmt_close($chkStmt);
    }
    
    // Update
    if (empty($errors)) {
        if (!empty($password) && strlen($password) >= 6) {
            // Update với password mới
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $sql = "UPDATE users SET full_name = ?, email = ?, phone = ?, username = ?, password_hash = ?, updated_at = NOW() WHERE user_id = ?";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "sssssi", $fullName, $email, $phone, $username, $hash, $userId);
        } else {
            // Update không đổi password
            $sql = "UPDATE users SET full_name = ?, email = ?, phone = ?, username = ?, updated_at = NOW() WHERE user_id = ?";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "ssssi", $fullName, $email, $phone, $username, $userId);
        }
        
        if (mysqli_stmt_execute($stmt)) {
            $_SESSION['alert'] = ['type' => 'success', 'message' => 'Cập nhật thông tin thành công!'];
            header('Location: ' . ADMIN_BASE_PATH . '/modules/users/index.php');
            exit;
        } else {
            $errors[] = 'Lỗi khi cập nhật: ' . mysqli_error($conn);
        }
        mysqli_stmt_close($stmt);
    }
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="pagetitle">
    <h1><i class="bi bi-pencil-square me-2"></i>Sửa thông tin chủ trọ</h1>
    <nav>
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= ADMIN_BASE_PATH ?>/index.php">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="<?= ADMIN_BASE_PATH ?>/modules/users/index.php">Chủ trọ</a></li>
            <li class="breadcrumb-item active">Sửa</li>
        </ol>
    </nav>
</div>

<section class="section">
    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Sửa: <?= htmlspecialchars($user['full_name']) ?></h5>
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
                                <label class="form-label">Mật khẩu mới</label>
                                <input type="password" name="password" class="form-control" minlength="6">
                                <small class="text-muted">Để trống nếu không đổi mật khẩu</small>
                            </div>
                        </div>
                        
                        <div class="mt-4">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-circle me-1"></i>Lưu thay đổi
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
