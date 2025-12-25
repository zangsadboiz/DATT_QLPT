<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';

$role = (string)($_SESSION['role_name'] ?? '');
if ($role !== 'ADMIN') {
    header('Location: ' . ADMIN_BASE_PATH . '/modules/dashboard/index.php');
    exit;
}

$userId = (int)$_SESSION['user_id'];
$success = '';
$error = '';

// Get current user data
$user = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM users WHERE user_id = $userId"));
if (!$user) {
    header('Location: ' . ADMIN_BASE_PATH . '/modules/dashboard/index.php');
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_profile') {
        $fullName = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        
        if ($fullName === '' || $email === '') {
            $error = 'Vui lòng nhập đầy đủ họ tên và email.';
        } else {
            // Check email unique
            $checkEmail = mysqli_fetch_assoc(mysqli_query($conn, 
                "SELECT user_id FROM users WHERE email = '" . mysqli_real_escape_string($conn, $email) . "' AND user_id != $userId"));
            if ($checkEmail) {
                $error = 'Email này đã được sử dụng bởi tài khoản khác.';
            } else {
                $stmt = mysqli_prepare($conn, "UPDATE users SET full_name = ?, email = ?, phone = ? WHERE user_id = ?");
                mysqli_stmt_bind_param($stmt, 'sssi', $fullName, $email, $phone, $userId);
                if (mysqli_stmt_execute($stmt)) {
                    $success = 'Cập nhật thông tin thành công!';
                    // Refresh user data
                    $user = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM users WHERE user_id = $userId"));
                } else {
                    $error = 'Có lỗi xảy ra, vui lòng thử lại.';
                }
            }
        }
    }
    
    if ($action === 'change_password') {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        
        if ($currentPassword === '' || $newPassword === '') {
            $error = 'Vui lòng nhập đầy đủ mật khẩu.';
        } elseif (!password_verify($currentPassword, $user['password_hash'])) {
            $error = 'Mật khẩu hiện tại không đúng.';
        } elseif (strlen($newPassword) < 6) {
            $error = 'Mật khẩu mới phải có ít nhất 6 ký tự.';
        } elseif ($newPassword !== $confirmPassword) {
            $error = 'Mật khẩu xác nhận không khớp.';
        } else {
            $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = mysqli_prepare($conn, "UPDATE users SET password_hash = ? WHERE user_id = ?");
            mysqli_stmt_bind_param($stmt, 'si', $newHash, $userId);
            if (mysqli_stmt_execute($stmt)) {
                $success = 'Đổi mật khẩu thành công!';
            } else {
                $error = 'Có lỗi xảy ra, vui lòng thử lại.';
            }
        }
    }
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="pagetitle">
  <h1><i class="bi bi-person-gear me-2"></i>Tài khoản Admin</h1>
  <nav>
    <ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="<?= ADMIN_BASE_PATH ?>/modules/dashboard/index.php">Dashboard</a></li>
      <li class="breadcrumb-item active">Tài khoản</li>
    </ol>
  </nav>
</div>

<section class="section profile">
  <div class="row">
    
    <!-- Profile Card -->
    <div class="col-xl-4">
      <div class="card">
        <div class="card-body profile-card pt-4 d-flex flex-column align-items-center">
          <i class="bi bi-person-circle" style="font-size: 5rem; color: #4154f1;"></i>
          <h2 class="mt-3"><?= htmlspecialchars($user['full_name']) ?></h2>
          <h3 class="text-muted">Quản trị viên</h3>
          <div class="mt-3">
            <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Đang hoạt động</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Edit Forms -->
    <div class="col-xl-8">
      
      <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show">
          <i class="bi bi-check-circle me-2"></i><?= htmlspecialchars($success) ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php endif; ?>
      
      <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show">
          <i class="bi bi-exclamation-circle me-2"></i><?= htmlspecialchars($error) ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php endif; ?>
      
      <div class="card">
        <div class="card-body pt-3">
          
          <ul class="nav nav-tabs nav-tabs-bordered">
            <li class="nav-item">
              <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#profile-overview">Tổng quan</button>
            </li>
            <li class="nav-item">
              <button class="nav-link" data-bs-toggle="tab" data-bs-target="#profile-edit">Sửa thông tin</button>
            </li>
            <li class="nav-item">
              <button class="nav-link" data-bs-toggle="tab" data-bs-target="#profile-change-password">Đổi mật khẩu</button>
            </li>
          </ul>
          
          <div class="tab-content pt-3">
            
            <!-- Overview Tab -->
            <div class="tab-pane fade show active profile-overview" id="profile-overview">
              <h5 class="card-title">Thông tin tài khoản</h5>
              
              <div class="row mb-3">
                <div class="col-lg-3 col-md-4 label">Họ tên</div>
                <div class="col-lg-9 col-md-8"><?= htmlspecialchars($user['full_name']) ?></div>
              </div>
              
              <div class="row mb-3">
                <div class="col-lg-3 col-md-4 label">Tên đăng nhập</div>
                <div class="col-lg-9 col-md-8"><code><?= htmlspecialchars($user['username']) ?></code></div>
              </div>
              
              <div class="row mb-3">
                <div class="col-lg-3 col-md-4 label">Email</div>
                <div class="col-lg-9 col-md-8"><?= htmlspecialchars($user['email']) ?></div>
              </div>
              
              <div class="row mb-3">
                <div class="col-lg-3 col-md-4 label">Số điện thoại</div>
                <div class="col-lg-9 col-md-8"><?= htmlspecialchars($user['phone'] ?? 'Chưa cập nhật') ?></div>
              </div>
              
              <div class="row mb-3">
                <div class="col-lg-3 col-md-4 label">Đăng nhập gần nhất</div>
                <div class="col-lg-9 col-md-8"><?= $user['last_login'] ? date('d/m/Y H:i', strtotime($user['last_login'])) : 'Chưa có' ?></div>
              </div>
              
              <div class="row mb-3">
                <div class="col-lg-3 col-md-4 label">Ngày tạo</div>
                <div class="col-lg-9 col-md-8"><?= date('d/m/Y', strtotime($user['created_at'])) ?></div>
              </div>
            </div>
            
            <!-- Edit Profile Tab -->
            <div class="tab-pane fade profile-edit pt-3" id="profile-edit">
              <form method="post">
                <input type="hidden" name="action" value="update_profile">
                
                <div class="row mb-3">
                  <label class="col-md-4 col-lg-3 col-form-label">Họ tên</label>
                  <div class="col-md-8 col-lg-9">
                    <input type="text" name="full_name" class="form-control" required
                           value="<?= htmlspecialchars($user['full_name']) ?>">
                  </div>
                </div>
                
                <div class="row mb-3">
                  <label class="col-md-4 col-lg-3 col-form-label">Tên đăng nhập</label>
                  <div class="col-md-8 col-lg-9">
                    <input type="text" class="form-control" value="<?= htmlspecialchars($user['username']) ?>" disabled>
                    <small class="text-muted">Không thể thay đổi tên đăng nhập</small>
                  </div>
                </div>
                
                <div class="row mb-3">
                  <label class="col-md-4 col-lg-3 col-form-label">Email</label>
                  <div class="col-md-8 col-lg-9">
                    <input type="email" name="email" class="form-control" required
                           value="<?= htmlspecialchars($user['email']) ?>">
                  </div>
                </div>
                
                <div class="row mb-3">
                  <label class="col-md-4 col-lg-3 col-form-label">Số điện thoại</label>
                  <div class="col-md-8 col-lg-9">
                    <input type="text" name="phone" class="form-control"
                           value="<?= htmlspecialchars($user['phone'] ?? '') ?>"
                           placeholder="VD: 0901234567">
                  </div>
                </div>
                
                <div class="text-center">
                  <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-circle me-1"></i>Lưu thay đổi
                  </button>
                </div>
              </form>
            </div>
            
            <!-- Change Password Tab -->
            <div class="tab-pane fade pt-3" id="profile-change-password">
              <form method="post">
                <input type="hidden" name="action" value="change_password">
                
                <div class="row mb-3">
                  <label class="col-md-4 col-lg-3 col-form-label">Mật khẩu hiện tại</label>
                  <div class="col-md-8 col-lg-9">
                    <input type="password" name="current_password" class="form-control" required>
                  </div>
                </div>
                
                <div class="row mb-3">
                  <label class="col-md-4 col-lg-3 col-form-label">Mật khẩu mới</label>
                  <div class="col-md-8 col-lg-9">
                    <input type="password" name="new_password" class="form-control" required minlength="6">
                    <small class="text-muted">Tối thiểu 6 ký tự</small>
                  </div>
                </div>
                
                <div class="row mb-3">
                  <label class="col-md-4 col-lg-3 col-form-label">Xác nhận mật khẩu</label>
                  <div class="col-md-8 col-lg-9">
                    <input type="password" name="confirm_password" class="form-control" required minlength="6">
                  </div>
                </div>
                
                <div class="text-center">
                  <button type="submit" class="btn btn-warning">
                    <i class="bi bi-key me-1"></i>Đổi mật khẩu
                  </button>
                </div>
              </form>
            </div>
            
          </div>
        </div>
      </div>
      
    </div>
  </div>
</section>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
