<?php
// admin/modules/sinhvien/add.php

require_once __DIR__ . '/../../includes/auth.php'; // BẮT BUỘC: chặn truy cập khi chưa đăng nhập
require_once __DIR__ . '/../../includes/db.php';

$role = $_SESSION['role_name'] ?? '';
if ($role !== 'ADMIN') {
    header('Location: /quanlyphongtro/admin/index.php?error=no_permission');
    exit;
}

if (!function_exists('hasColumn')) {
    function hasColumn(mysqli $conn, string $table, string $col): bool {
        $t = mysqli_real_escape_string($conn, $table);
        $c = mysqli_real_escape_string($conn, $col);
        $rs = mysqli_query($conn, "
            SELECT COUNT(*) AS cnt
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = '$t'
              AND COLUMN_NAME = '$c'
        ");
        $row = $rs ? mysqli_fetch_assoc($rs) : null;
        return (int)($row['cnt'] ?? 0) > 0;
    }
}

$TENANT_HAS_USER = hasColumn($conn, 'tenants', 'user_id');
$TENANT_HAS_STUDENT = hasColumn($conn, 'tenants', 'student_code');
$TENANT_HAS_CREATED_BY = hasColumn($conn, 'tenants', 'created_by');

if (!$TENANT_HAS_USER) {
    die('Thiếu tenants.user_id. Hãy chạy SQL bước 1.2 trước.');
}

$roleRs = mysqli_query($conn, "SELECT role_id FROM roles WHERE role_name='STUDENT' LIMIT 1");
$studentRoleId = (int)(mysqli_fetch_assoc($roleRs)['role_id'] ?? 0);
if ($studentRoleId <= 0) {
    die("Thiếu role STUDENT. Hãy chạy SQL bước 1.1.");
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $username  = trim($_POST['username'] ?? '');
    $password  = $_POST['password'] ?? '';
    $email     = trim($_POST['email'] ?? '');
    $phone     = trim($_POST['phone'] ?? '');

    // hồ sơ sinh viên
    $student_code = trim($_POST['student_code'] ?? '');
    $id_number    = trim($_POST['id_number'] ?? '');
    $dob          = $_POST['dob'] ?? null;
    $gender       = $_POST['gender'] ?? null;
    $address      = trim($_POST['address'] ?? '');
    $hometown     = trim($_POST['hometown'] ?? '');
    $id_issue_date  = $_POST['id_issue_date'] ?? null;
    $id_issue_place = trim($_POST['id_issue_place'] ?? '');

    if ($full_name === '' || $username === '' || $password === '') {
        $error = 'Vui lòng nhập đầy đủ: Họ tên, Username, Mật khẩu.';
    } elseif (strlen($password) < 6) {
        $error = 'Mật khẩu tối thiểu 6 ký tự.';
    } else {
        $u_sql = mysqli_real_escape_string($conn, $username);
        $chkU = mysqli_query($conn, "SELECT 1 FROM users WHERE username='$u_sql' LIMIT 1");
        if ($chkU && mysqli_num_rows($chkU) > 0) {
            $error = 'Username đã tồn tại.';
        }
    }

    if (!$error) {
        $fn = mysqli_real_escape_string($conn, $full_name);
        $em = mysqli_real_escape_string($conn, $email);
        $ph = mysqli_real_escape_string($conn, $phone);

        $hash = password_hash($password, PASSWORD_DEFAULT);
        $hash_sql = mysqli_real_escape_string($conn, $hash);

        mysqli_begin_transaction($conn);

        $ok1 = mysqli_query($conn, "
            INSERT INTO users (role_id, full_name, email, phone, username, password_hash, is_active, created_at)
            VALUES (
                $studentRoleId,
                '$fn',
                " . ($email!==''?"'$em'":"NULL") . ",
                " . ($phone!==''?"'$ph'":"NULL") . ",
                '$u_sql',
                '$hash_sql',
                1,
                NOW()
            )
        ");

        if (!$ok1) {
            mysqli_rollback($conn);
            $error = 'Lỗi tạo user: ' . mysqli_error($conn);
        } else {
            $newUserId = (int)mysqli_insert_id($conn);

            $sc = mysqli_real_escape_string($conn, $student_code);
            $id = mysqli_real_escape_string($conn, $id_number);
            $ad = mysqli_real_escape_string($conn, $address);
            $ht = mysqli_real_escape_string($conn, $hometown);
            $ip = mysqli_real_escape_string($conn, $id_issue_place);

            $cols = "user_id, full_name, phone, email, id_number, dob, gender, address, hometown, id_issue_date, id_issue_place, created_at";
            $vals = "$newUserId, '$fn',
                    " . ($phone!==''?"'$ph'":"NULL") . ",
                    " . ($email!==''?"'$em'":"NULL") . ",
                    " . ($id_number!==''?"'$id'":"NULL") . ",
                    " . ($dob?("'".mysqli_real_escape_string($conn,$dob)."'"):"NULL") . ",
                    " . ($gender?("'".mysqli_real_escape_string($conn,$gender)."'"):"NULL") . ",
                    " . ($address!==''?"'$ad'":"NULL") . ",
                    " . ($hometown!==''?"'$ht'":"NULL") . ",
                    " . ($id_issue_date?("'".mysqli_real_escape_string($conn,$id_issue_date)."'"):"NULL") . ",
                    " . ($id_issue_place!==''?"'$ip'":"NULL") . ",
                    NOW()";

            if ($TENANT_HAS_STUDENT) {
                $cols .= ", student_code";
                $vals .= ", " . ($student_code!==''?"'$sc'":"NULL");
            }

            // GẮN created_by để đúng nghiệp vụ admin quản lý hồ sơ
            if ($TENANT_HAS_CREATED_BY) {
                $admin_id = (int)($_SESSION['user_id'] ?? 0);
                $cols .= ", created_by";
                $vals .= ", " . ($admin_id > 0 ? $admin_id : "NULL");
            }

            $ok2 = mysqli_query($conn, "INSERT INTO tenants ($cols) VALUES ($vals)");
            if (!$ok2) {
                mysqli_rollback($conn);
                $error = 'Lỗi tạo hồ sơ sinh viên: ' . mysqli_error($conn);
            } else {
                mysqli_commit($conn);
                header('Location: index.php?msg=created');
                exit;
            }
        }
    }
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="pagetitle d-flex justify-content-between align-items-center">
  <h1>Thêm sinh viên</h1>
  <a href="index.php" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Quay lại</a>
</div>

<?php if ($error): ?>
<div class="alert alert-danger alert-dismissible fade show">
  <?= htmlspecialchars($error) ?>
  <button class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<section class="section">
<div class="card">
<div class="card-body">

<form method="post" class="row g-3">
  <div class="col-md-6">
    <label class="form-label">Họ tên</label>
    <input name="full_name" class="form-control" required>
  </div>

  <div class="col-md-6">
    <label class="form-label">Mã sinh viên</label>
    <input name="student_code" class="form-control">
  </div>

  <div class="col-md-6">
    <label class="form-label">Username</label>
    <input name="username" class="form-control" required>
  </div>

  <div class="col-md-6">
    <label class="form-label">Mật khẩu</label>
    <input name="password" type="password" class="form-control" required>
  </div>

  <div class="col-md-6">
    <label class="form-label">Email</label>
    <input name="email" class="form-control">
  </div>

  <div class="col-md-6">
    <label class="form-label">SĐT</label>
    <input name="phone" class="form-control">
  </div>

  <div class="col-md-6">
    <label class="form-label">CCCD</label>
    <input name="id_number" class="form-control">
  </div>

  <div class="col-md-3">
    <label class="form-label">Ngày sinh</label>
    <input type="date" name="dob" class="form-control">
  </div>

  <div class="col-md-3">
    <label class="form-label">Giới tính</label>
    <select name="gender" class="form-select">
      <option value="">--</option>
      <option value="MALE">Nam</option>
      <option value="FEMALE">Nữ</option>
      <option value="OTHER">Khác</option>
    </select>
  </div>

  <div class="col-md-6">
    <label class="form-label">Quê quán</label>
    <input name="hometown" class="form-control">
  </div>

  <div class="col-md-3">
    <label class="form-label">Ngày cấp CCCD</label>
    <input type="date" name="id_issue_date" class="form-control">
  </div>

  <div class="col-md-3">
    <label class="form-label">Nơi cấp CCCD</label>
    <input name="id_issue_place" class="form-control">
  </div>

  <div class="col-12">
    <label class="form-label">Địa chỉ</label>
    <input name="address" class="form-control">
  </div>

  <div class="col-12">
    <button class="btn btn-success"><i class="bi bi-check-circle"></i> Lưu</button>
    <a href="index.php" class="btn btn-secondary">Hủy</a>
  </div>
</form>

</div>
</div>
</section>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
