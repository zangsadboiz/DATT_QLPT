<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/alerts.php';
require_once __DIR__ . '/../../includes/form_helpers.php';

$role = (string)($_SESSION['role_name'] ?? '');
if (!in_array($role, ['ADMIN', 'STAFF'], true)) {
    admin_redirect('modules/dashboard/index.php', ['forbidden' => 1]);
}

$errors = [];
$fullName = '';
$email = '';
$phone = '';
$username = '';
$isActive = 1;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = trim((string)($_POST['full_name'] ?? ''));
    $email    = trim((string)($_POST['email'] ?? ''));
    $phone    = trim((string)($_POST['phone'] ?? ''));
    $username = trim((string)($_POST['username'] ?? ''));
    $pass     = (string)($_POST['password'] ?? '');
    $pass2    = (string)($_POST['password2'] ?? '');
    $isActive = isset($_POST['is_active']) ? 1 : 0;

    // Validation
    if ($fullName === '') $errors[] = 'Vui lòng nhập họ tên.';
    if ($email === '')    $errors[] = 'Vui lòng nhập email.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Email không hợp lệ.';
    if ($phone === '')    $errors[] = 'Vui lòng nhập số điện thoại.';
    if ($username === '') $errors[] = 'Vui lòng nhập username.';
    if (strlen($username) < 4) $errors[] = 'Username phải có ít nhất 4 ký tự.';
    if ($pass === '')     $errors[] = 'Vui lòng nhập mật khẩu.';
    if (strlen($pass) < 6) $errors[] = 'Mật khẩu phải có ít nhất 6 ký tự.';
    if ($pass !== $pass2) $errors[] = 'Mật khẩu nhập lại không khớp.';

    // Check unique username/email/phone
    if (empty($errors)) {
        $sqlChk = "SELECT user_id FROM users WHERE username = ? OR email = ? OR phone = ? LIMIT 1";
        $stmtC = mysqli_prepare($conn, $sqlChk);
        mysqli_stmt_bind_param($stmtC, "sss", $username, $email, $phone);
        mysqli_stmt_execute($stmtC);
        $rsC = mysqli_stmt_get_result($stmtC);
        $dup = $rsC && mysqli_fetch_assoc($rsC);
        mysqli_stmt_close($stmtC);
        if ($dup) $errors[] = 'Username/Email/SĐT đã tồn tại trong hệ thống.';
    }

    if (empty($errors)) {
        // role LANDLORD = role_id 3
        $roleId = 3;
        $hash = password_hash($pass, PASSWORD_DEFAULT);

        $sql = "INSERT INTO users (role_id, full_name, email, phone, username, password_hash, is_active)
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "isssssi", $roleId, $fullName, $email, $phone, $username, $hash, $isActive);

        try {
            $ok = mysqli_stmt_execute($stmt);
        } catch (mysqli_sql_exception $e) {
            $ok = false;
            if ((int)$e->getCode() === 1062) {
                $errors[] = 'Trùng username/email/phone (unique).';
            } else {
                $errors[] = 'Lỗi SQL: ' . $e->getMessage();
            }
        }
        mysqli_stmt_close($stmt);

        if ($ok) {
            set_flash('success', 'Thêm chủ trọ thành công!');
            admin_redirect('modules/chutro/index.php');
        }
    }
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="pagetitle">
  <h1><i class="bi bi-person-plus me-2"></i>Thêm chủ trọ mới</h1>
  <nav>
    <ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="<?= ADMIN_BASE_PATH ?>/modules/dashboard/index.php">Dashboard</a></li>
      <li class="breadcrumb-item"><a href="index.php">Chủ trọ</a></li>
      <li class="breadcrumb-item active">Thêm mới</li>
    </ol>
  </nav>
</div>

<section class="section">
  <div class="card">
    <div class="card-header">
      <div class="d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Thông tin chủ trọ</h5>
        <a href="index.php" class="btn btn-outline-secondary btn-sm">
          <i class="bi bi-arrow-left me-1"></i>Quay lại danh sách
        </a>
      </div>
    </div>
    <div class="card-body">

      <?php if (!empty($errors)): ?>
        <div class="alert alert-danger alert-dismissible fade show">
          <strong><i class="bi bi-exclamation-triangle me-2"></i>Có lỗi xảy ra:</strong>
          <ul class="mb-0 mt-2">
            <?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?>
          </ul>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php endif; ?>

      <form method="post" class="row g-3 needs-validation" novalidate>
        
        <!-- Thông tin cá nhân -->
        <div class="col-12">
          <h6 class="border-bottom pb-2 mb-3">
            <i class="bi bi-person-circle me-2"></i>Thông tin cá nhân
          </h6>
        </div>

        <div class="col-md-6">
          <label for="full_name" class="form-label required">Họ và tên</label>
          <input type="text" class="form-control" id="full_name" name="full_name" 
                 value="<?= htmlspecialchars($fullName) ?>" 
                 placeholder="Nguyễn Văn A" required>
          <div class="invalid-feedback">Vui lòng nhập họ tên.</div>
        </div>

        <div class="col-md-6">
          <label for="phone" class="form-label required">Số điện thoại</label>
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-telephone"></i></span>
            <input type="tel" class="form-control" id="phone" name="phone" 
                   value="<?= htmlspecialchars($phone) ?>" 
                   placeholder="0912345678" required>
            <div class="invalid-feedback">Vui lòng nhập số điện thoại.</div>
          </div>
        </div>

        <div class="col-md-6">
          <label for="email" class="form-label required">Email</label>
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-envelope"></i></span>
            <input type="email" class="form-control" id="email" name="email" 
                   value="<?= htmlspecialchars($email) ?>" 
                   placeholder="user@example.com" required>
            <div class="invalid-feedback">Vui lòng nhập email hợp lệ.</div>
          </div>
        </div>

        <!-- Thông tin đăng nhập -->
        <div class="col-12 mt-4">
          <h6 class="border-bottom pb-2 mb-3">
            <i class="bi bi-shield-lock me-2"></i>Thông tin đăng nhập
          </h6>
        </div>

        <div class="col-md-6">
          <label for="username" class="form-label required">Tên đăng nhập</label>
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-person"></i></span>
            <input type="text" class="form-control" id="username" name="username" 
                   value="<?= htmlspecialchars($username) ?>" 
                   placeholder="username" minlength="4" required>
            <div class="invalid-feedback">Username phải có ít nhất 4 ký tự.</div>
          </div>
          <div class="form-text">Tối thiểu 4 ký tự, không dấu, không khoảng trắng.</div>
        </div>

        <div class="col-md-6"></div>

        <div class="col-md-6">
          <label for="password" class="form-label required">Mật khẩu</label>
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-lock"></i></span>
            <input type="password" class="form-control" id="password" name="password" 
                   minlength="6" required>
            <button class="btn btn-outline-secondary" type="button" 
                    onclick="togglePassword('password')">
              <i class="bi bi-eye"></i>
            </button>
          </div>
          <div class="form-text">Tối thiểu 6 ký tự.</div>
        </div>

        <div class="col-md-6">
          <label for="password2" class="form-label required">Nhập lại mật khẩu</label>
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-lock"></i></span>
            <input type="password" class="form-control" id="password2" name="password2" 
                   minlength="6" required>
            <button class="btn btn-outline-secondary" type="button" 
                    onclick="togglePassword('password2')">
              <i class="bi bi-eye"></i>
            </button>
          </div>
        </div>

        <!-- Trạng thái -->
        <div class="col-12 mt-4">
          <h6 class="border-bottom pb-2 mb-3">
            <i class="bi bi-gear me-2"></i>Cài đặt
          </h6>
        </div>

        <div class="col-12">
          <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" role="switch" 
                   id="is_active" name="is_active" value="1" 
                   <?= $isActive === 1 ? 'checked' : '' ?>>
            <label class="form-check-label" for="is_active">
              <strong>Kích hoạt tài khoản ngay</strong>
              <div class="form-text">Tài khoản sẽ có thể đăng nhập ngay sau khi tạo.</div>
            </label>
          </div>
        </div>

        <!-- Action Buttons -->
        <div class="col-12">
          <hr class="my-4">
          <div class="d-flex gap-2">
            <button type="submit" class="btn btn-success">
              <i class="bi bi-check-circle me-1"></i>Lưu thông tin
            </button>
            <a href="index.php" class="btn btn-secondary">
              <i class="bi bi-x-circle me-1"></i>Hủy bỏ
            </a>
          </div>
        </div>

      </form>

    </div>
  </div>
</section>

<script>
function togglePassword(fieldId) {
  const field = document.getElementById(fieldId);
  const button = field.nextElementSibling;
  const icon = button.querySelector('i');
  
  if (field.type === 'password') {
    field.type = 'text';
    icon.classList.remove('bi-eye');
    icon.classList.add('bi-eye-slash');
  } else {
    field.type = 'password';
    icon.classList.remove('bi-eye-slash');
    icon.classList.add('bi-eye');
  }
}

// Password match validation
document.querySelector('form').addEventListener('submit', function(e) {
  const pass1 = document.getElementById('password').value;
  const pass2 = document.getElementById('password2').value;
  
  if (pass1 !== pass2) {
    e.preventDefault();
    alert('Mật khẩu nhập lại không khớp!');
    return false;
  }
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
