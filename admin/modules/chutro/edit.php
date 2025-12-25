<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';

$role = (string)($_SESSION['role_name'] ?? '');
if (!in_array($role, ['ADMIN', 'STAFF'], true)) {
    admin_redirect('modules/dashboard/index.php', ['forbidden' => 1]);
}

$userId = (int)($_GET['user_id'] ?? ($_POST['user_id'] ?? 0));
if ($userId <= 0) {
    admin_redirect('modules/chutro/index.php', ['err' => 'missing_user_id']);
}

// Load user landlord
$sql = "SELECT u.*
        FROM users u
        JOIN roles r ON r.role_id = u.role_id
        WHERE u.user_id = ? AND r.role_name = 'LANDLORD'
        LIMIT 1";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $userId);
mysqli_stmt_execute($stmt);
$rs = mysqli_stmt_get_result($stmt);
$u = $rs ? mysqli_fetch_assoc($rs) : null;
mysqli_stmt_close($stmt);

if (!$u) {
    admin_redirect('modules/chutro/index.php', ['err' => 'not_found']);
}

$errors = [];
$fullName = (string)$u['full_name'];
$email = (string)$u['email'];
$phone = (string)$u['phone'];
$username = (string)$u['username'];
$isActive = (int)$u['is_active'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = trim((string)($_POST['full_name'] ?? ''));
    $email    = trim((string)($_POST['email'] ?? ''));
    $phone    = trim((string)($_POST['phone'] ?? ''));
    $username = trim((string)($_POST['username'] ?? ''));
    $isActive = isset($_POST['is_active']) ? 1 : 0;

    $pass  = (string)($_POST['password'] ?? '');
    $pass2 = (string)($_POST['password2'] ?? '');

    if ($fullName === '') $errors[] = 'Vui lòng nhập họ tên.';
    if ($email === '')    $errors[] = 'Vui lòng nhập email.';
    if ($phone === '')    $errors[] = 'Vui lòng nhập số điện thoại.';
    if ($username === '') $errors[] = 'Vui lòng nhập username.';
    if ($pass !== '' && $pass !== $pass2) $errors[] = 'Mật khẩu nhập lại không khớp.';

    // Check unique (trừ chính nó)
    if (empty($errors)) {
        // Kiểm tra từng trường riêng để thông báo rõ hơn
        $dupFields = [];
        
        // Check username
        $stmtU = mysqli_prepare($conn, "SELECT user_id FROM users WHERE user_id <> ? AND username = ? LIMIT 1");
        mysqli_stmt_bind_param($stmtU, "is", $userId, $username);
        mysqli_stmt_execute($stmtU);
        if (mysqli_fetch_assoc(mysqli_stmt_get_result($stmtU))) $dupFields[] = 'Username';
        mysqli_stmt_close($stmtU);
        
        // Check email
        $stmtE = mysqli_prepare($conn, "SELECT user_id FROM users WHERE user_id <> ? AND email = ? LIMIT 1");
        mysqli_stmt_bind_param($stmtE, "is", $userId, $email);
        mysqli_stmt_execute($stmtE);
        if (mysqli_fetch_assoc(mysqli_stmt_get_result($stmtE))) $dupFields[] = 'Email';
        mysqli_stmt_close($stmtE);
        
        // Check phone
        $stmtP = mysqli_prepare($conn, "SELECT user_id FROM users WHERE user_id <> ? AND phone = ? LIMIT 1");
        mysqli_stmt_bind_param($stmtP, "is", $userId, $phone);
        mysqli_stmt_execute($stmtP);
        if (mysqli_fetch_assoc(mysqli_stmt_get_result($stmtP))) $dupFields[] = 'SĐT';
        mysqli_stmt_close($stmtP);
        
        if (!empty($dupFields)) {
            $errors[] = implode(', ', $dupFields) . ' đã được sử dụng bởi tài khoản khác.';
        }
    }


    if (empty($errors)) {
        if ($pass !== '') {
            $hash = password_hash($pass, PASSWORD_DEFAULT);
            $sqlU = "UPDATE users
                     SET full_name=?, email=?, phone=?, username=?, is_active=?, password_hash=?
                     WHERE user_id=?";
            $stmtU = mysqli_prepare($conn, $sqlU);
            mysqli_stmt_bind_param($stmtU, "ssssisi", $fullName, $email, $phone, $username, $isActive, $hash, $userId);
        } else {
            $sqlU = "UPDATE users
                     SET full_name=?, email=?, phone=?, username=?, is_active=?
                     WHERE user_id=?";
            $stmtU = mysqli_prepare($conn, $sqlU);
            mysqli_stmt_bind_param($stmtU, "ssssii", $fullName, $email, $phone, $username, $isActive, $userId);
        }

        try {
            $ok = mysqli_stmt_execute($stmtU);
        } catch (mysqli_sql_exception $e) {
            $ok = false;
            if ((int)$e->getCode() === 1062) $errors[] = 'Trùng username/email/phone (unique).';
            else $errors[] = 'Lỗi SQL: ' . $e->getMessage();
        }
        mysqli_stmt_close($stmtU);

        if ($ok) {
            admin_redirect('modules/chutro/index.php', ['updated' => 1]);
        }
    }
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="pagetitle">
  <h1><i class="bi bi-pencil-square me-2"></i>Sửa thông tin chủ trọ</h1>
  <nav>
    <ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="<?= ADMIN_BASE_PATH ?>/modules/dashboard/index.php">Dashboard</a></li>
      <li class="breadcrumb-item"><a href="index.php">Chủ trọ</a></li>
      <li class="breadcrumb-item active">Sửa</li>
    </ol>
  </nav>
</div>

<section class="section">
  <div class="card">
    <div class="card-header">
      <div class="d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Cập nhật thông tin: <?= htmlspecialchars($fullName) ?></h5>
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

      <form method="post" class="row g-3">
        <input type="hidden" name="user_id" value="<?= (int)$userId ?>">

        <div class="col-md-6">
          <label class="form-label">Họ tên</label>
          <input class="form-control" name="full_name" value="<?= htmlspecialchars($fullName) ?>" required>
        </div>

        <div class="col-md-6">
          <label class="form-label">Username</label>
          <input class="form-control" name="username" value="<?= htmlspecialchars($username) ?>" required>
        </div>

        <div class="col-md-6">
          <label class="form-label">Email</label>
          <input class="form-control" type="email" name="email" value="<?= htmlspecialchars($email) ?>" required>
        </div>

        <div class="col-md-6">
          <label class="form-label">SĐT</label>
          <input class="form-control" name="phone" value="<?= htmlspecialchars($phone) ?>" required>
        </div>

        <div class="col-md-6">
          <label class="form-label">Mật khẩu mới (để trống nếu không đổi)</label>
          <input class="form-control" type="password" name="password">
        </div>

        <div class="col-md-6">
          <label class="form-label">Nhập lại mật khẩu mới</label>
          <input class="form-control" type="password" name="password2">
        </div>

        <div class="col-md-12">
          <div class="form-check">
            <input class="form-check-input" type="checkbox" name="is_active" value="1" id="active"
                   <?= $isActive === 1 ? 'checked' : '' ?>>
            <label class="form-check-label" for="active">Kích hoạt tài khoản</label>
          </div>
        </div>

        <div class="col-12 d-flex gap-2">
          <button class="btn btn-success" type="submit">Cập nhật</button>
          <a class="btn btn-secondary" href="index.php">Hủy</a>
        </div>
      </form>

    </div>
  </div>
</section>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
