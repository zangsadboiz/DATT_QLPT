<?php
// admin/modules/sinhvien/edit.php

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
$TENANT_HAS_ID_NUMBER = hasColumn($conn, 'tenants', 'id_number');
$TENANT_HAS_DOB = hasColumn($conn, 'tenants', 'dob');
$TENANT_HAS_GENDER = hasColumn($conn, 'tenants', 'gender');
$TENANT_HAS_ADDRESS = hasColumn($conn, 'tenants', 'address');
$TENANT_HAS_HOMETOWN = hasColumn($conn, 'tenants', 'hometown');
$TENANT_HAS_ID_ISSUE_DATE = hasColumn($conn, 'tenants', 'id_issue_date');
$TENANT_HAS_ID_ISSUE_PLACE = hasColumn($conn, 'tenants', 'id_issue_place');
$TENANT_HAS_ID_CARD = hasColumn($conn, 'tenants', 'id_card');

// Nếu không có bảng tenants hoặc thiếu user_id thì query chỉ từ users
$hasTenantTable = $TENANT_HAS_USER;

$roleRs = mysqli_query($conn, "SELECT role_id FROM roles WHERE role_name='STUDENT' LIMIT 1");
$studentRoleId = (int)(mysqli_fetch_assoc($roleRs)['role_id'] ?? 0);
if ($studentRoleId <= 0) { die("Thiếu role STUDENT."); }

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { header('Location: index.php'); exit; }

// Build dynamic tenant columns
$tenantCols = [];
if ($hasTenantTable) {
    $tenantCols[] = "t.tenant_id";
    if ($TENANT_HAS_ID_NUMBER) $tenantCols[] = "t.id_number";
    elseif ($TENANT_HAS_ID_CARD) $tenantCols[] = "t.id_card as id_number";
    else $tenantCols[] = "NULL as id_number";
    
    $tenantCols[] = $TENANT_HAS_DOB ? "t.dob" : "NULL as dob";
    $tenantCols[] = $TENANT_HAS_GENDER ? "t.gender" : "NULL as gender";
    $tenantCols[] = $TENANT_HAS_ADDRESS ? "t.address" : "NULL as address";
    $tenantCols[] = $TENANT_HAS_HOMETOWN ? "t.hometown" : "NULL as hometown";
    $tenantCols[] = $TENANT_HAS_ID_ISSUE_DATE ? "t.id_issue_date" : "NULL as id_issue_date";
    $tenantCols[] = $TENANT_HAS_ID_ISSUE_PLACE ? "t.id_issue_place" : "NULL as id_issue_place";
    $tenantCols[] = $TENANT_HAS_STUDENT ? "t.student_code" : "NULL as student_code";
}

$tenantSelect = $hasTenantTable ? ", " . implode(", ", $tenantCols) : ", NULL as tenant_id, NULL as id_number, NULL as dob, NULL as gender, NULL as address, NULL as hometown, NULL as id_issue_date, NULL as id_issue_place, NULL as student_code";
$tenantJoin = $hasTenantTable ? "LEFT JOIN tenants t ON t.user_id=u.user_id" : "";

$res = mysqli_query($conn, "
    SELECT u.user_id, u.full_name, u.username, u.email, u.phone, u.is_active
           $tenantSelect
    FROM users u
    $tenantJoin
    WHERE u.user_id=$id AND u.role_id=$studentRoleId
    LIMIT 1
");
if (!$res || mysqli_num_rows($res) === 0) { header('Location: index.php'); exit; }
$s = mysqli_fetch_assoc($res);


$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    $phone     = trim($_POST['phone'] ?? '');
    $is_active = (int)($_POST['is_active'] ?? 1);

    $new_pass  = $_POST['new_password'] ?? '';

    $student_code = trim($_POST['student_code'] ?? '');
    $id_number    = trim($_POST['id_number'] ?? '');
    $dob          = $_POST['dob'] ?? null;
    $gender       = $_POST['gender'] ?? null;
    $address      = trim($_POST['address'] ?? '');
    $hometown     = trim($_POST['hometown'] ?? '');
    $id_issue_date  = $_POST['id_issue_date'] ?? null;
    $id_issue_place = trim($_POST['id_issue_place'] ?? '');

    if ($full_name === '') {
        $error = 'Họ tên không được rỗng.';
    } elseif ($dob && (strtotime($dob) > strtotime('-18 years'))) {
        $error = 'Sinh viên phải đủ 18 tuổi.';
    } elseif ($new_pass !== '' && strlen($new_pass) < 6) {
        $error = 'Mật khẩu mới tối thiểu 6 ký tự.';
    }

    if (!$error) {
        $fn = mysqli_real_escape_string($conn, $full_name);
        $em = mysqli_real_escape_string($conn, $email);
        $ph = mysqli_real_escape_string($conn, $phone);

        mysqli_begin_transaction($conn);

        $setU = [];
        $setU[] = "full_name='$fn'";
        $setU[] = "email=" . ($email!==''?"'$em'":"NULL");
        $setU[] = "phone=" . ($phone!==''?"'$ph'":"NULL");
        $setU[] = "is_active=" . (($is_active===1)?1:0);

        if ($new_pass !== '') {
            $hash = password_hash($new_pass, PASSWORD_DEFAULT);
            $hash_sql = mysqli_real_escape_string($conn, $hash);
            $setU[] = "password_hash='$hash_sql'";
        }

        $updateSQL = "UPDATE users SET ".implode(", ", $setU)." WHERE user_id=$id AND role_id=$studentRoleId LIMIT 1";
        $ok1 = mysqli_query($conn, $updateSQL);
        if (!$ok1) {
            mysqli_rollback($conn);
            $error = 'Lỗi cập nhật user: ' . mysqli_error($conn) . ' | SQL: ' . $updateSQL;
        } else {
            // Chỉ update tenant nếu có bảng tenants với user_id
            if ($hasTenantTable) {
                $sc = mysqli_real_escape_string($conn, $student_code);
                $idn= mysqli_real_escape_string($conn, $id_number);
                $ad = mysqli_real_escape_string($conn, $address);
                $ht = mysqli_real_escape_string($conn, $hometown);
                $ip = mysqli_real_escape_string($conn, $id_issue_place);

                // Chỉ thêm những cột thực sự tồn tại
                $setT = [];
                if (hasColumn($conn, 'tenants', 'full_name')) $setT[] = "full_name='$fn'";
                if (hasColumn($conn, 'tenants', 'phone')) $setT[] = "phone=" . ($phone!==''?"'$ph'":"NULL");
                if (hasColumn($conn, 'tenants', 'email')) $setT[] = "email=" . ($email!==''?"'$em'":"NULL");
                
                if ($TENANT_HAS_ID_NUMBER) $setT[] = "id_number=" . ($id_number!==''?"'$idn'":"NULL");
                elseif ($TENANT_HAS_ID_CARD) $setT[] = "id_card=" . ($id_number!==''?"'$idn'":"NULL");
                
                if ($TENANT_HAS_DOB) $setT[] = "dob=" . ($dob?("'".mysqli_real_escape_string($conn,$dob)."'"):"NULL");
                if ($TENANT_HAS_GENDER) $setT[] = "gender=" . ($gender?("'".mysqli_real_escape_string($conn,$gender)."'"):"NULL");
                if ($TENANT_HAS_ADDRESS) $setT[] = "address=" . ($address!==''?"'$ad'":"NULL");
                if ($TENANT_HAS_HOMETOWN) $setT[] = "hometown=" . ($hometown!==''?"'$ht'":"NULL");
                if ($TENANT_HAS_ID_ISSUE_DATE) $setT[] = "id_issue_date=" . ($id_issue_date?("'".mysqli_real_escape_string($conn,$id_issue_date)."'"):"NULL");
                if ($TENANT_HAS_ID_ISSUE_PLACE) $setT[] = "id_issue_place=" . ($id_issue_place!==''?"'$ip'":"NULL");
                if ($TENANT_HAS_STUDENT) $setT[] = "student_code=" . ($student_code!==''?"'$sc'":"NULL");

                // nếu chưa có tenant row -> tạo mới
                if ((int)($s['tenant_id'] ?? 0) > 0) {
                    if (count($setT) > 0) {
                        $ok2 = mysqli_query($conn, "UPDATE tenants SET ".implode(", ", $setT)." WHERE user_id=$id LIMIT 1");
                    } else {
                        $ok2 = true; // Không có gì để update
                    }
                } else {
                    // Tạo mới tenant - chỉ với các cột tồn tại
                    $colsList = ['user_id'];
                    $valsList = [$id];
                    
                    if (hasColumn($conn, 'tenants', 'full_name')) { $colsList[] = 'full_name'; $valsList[] = "'$fn'"; }
                    if (hasColumn($conn, 'tenants', 'phone')) { $colsList[] = 'phone'; $valsList[] = $phone!=='' ? "'$ph'" : "NULL"; }
                    if (hasColumn($conn, 'tenants', 'email')) { $colsList[] = 'email'; $valsList[] = $email!=='' ? "'$em'" : "NULL"; }
                    
                    if ($TENANT_HAS_ID_NUMBER) { $colsList[] = 'id_number'; $valsList[] = $id_number!=='' ? "'$idn'" : "NULL"; }
                    elseif ($TENANT_HAS_ID_CARD) { $colsList[] = 'id_card'; $valsList[] = $id_number!=='' ? "'$idn'" : "NULL"; }
                    
                    if ($TENANT_HAS_STUDENT) { $colsList[] = 'student_code'; $valsList[] = $student_code!=='' ? "'$sc'" : "NULL"; }
                    if ($TENANT_HAS_CREATED_BY) { $colsList[] = 'created_by'; $valsList[] = (int)($_SESSION['user_id'] ?? 0) ?: "NULL"; }
                    if (hasColumn($conn, 'tenants', 'created_at')) { $colsList[] = 'created_at'; $valsList[] = "NOW()"; }

                    $ok2 = mysqli_query($conn, "INSERT INTO tenants (".implode(", ", $colsList).") VALUES (".implode(", ", $valsList).")");
                }

                if (!$ok2) {
                    mysqli_rollback($conn);
                    $error = 'Lỗi cập nhật hồ sơ sinh viên: ' . mysqli_error($conn);
                } else {
                    mysqli_commit($conn);
                    header('Location: index.php?msg=updated');
                    exit;
                }
            } else {
                // Không có bảng tenants, chỉ update user
                mysqli_commit($conn);
                header('Location: index.php?msg=updated');
                exit;
            }
        }
    }
    
    // If we reach here after POST, something went wrong
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$error) {
        $error = 'Lỗi không xác định khi cập nhật. Vui lòng thử lại.';
    }
}


require_once __DIR__ . '/../../includes/header.php';
?>

<div class="pagetitle d-flex justify-content-between align-items-center">
  <h1>Sửa sinh viên</h1>
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
    <input name="full_name" class="form-control" required value="<?= htmlspecialchars($s['full_name']) ?>">
  </div>

  <div class="col-md-6">
    <label class="form-label">Mã sinh viên</label>
    <input name="student_code" class="form-control" value="<?= htmlspecialchars($s['student_code'] ?? '') ?>">
  </div>

  <div class="col-md-6">
    <label class="form-label">Email</label>
    <input name="email" class="form-control" value="<?= htmlspecialchars($s['email'] ?? '') ?>">
  </div>

  <div class="col-md-6">
    <label class="form-label">SĐT</label>
    <input name="phone" class="form-control" value="<?= htmlspecialchars($s['phone'] ?? '') ?>">
  </div>

  <div class="col-md-6">
    <label class="form-label">CCCD</label>
    <input name="id_number" class="form-control" value="<?= htmlspecialchars($s['id_number'] ?? '') ?>">
  </div>

  <div class="col-md-3">
    <label class="form-label">Ngày sinh</label>
    <input type="date" name="dob" class="form-control" 
           max="<?= date('Y-m-d', strtotime('-18 years')) ?>"
           value="<?= htmlspecialchars($s['dob'] ?? '') ?>">
    <small class="text-muted">Phải đủ 18 tuổi</small>
  </div>

  <div class="col-md-3">
    <label class="form-label">Giới tính</label>
    <select name="gender" class="form-select">
      <option value="">--</option>
      <option value="MALE" <?= (($s['gender']??'')==='MALE')?'selected':'' ?>>Nam</option>
      <option value="FEMALE" <?= (($s['gender']??'')==='FEMALE')?'selected':'' ?>>Nữ</option>
      <option value="OTHER" <?= (($s['gender']??'')==='OTHER')?'selected':'' ?>>Khác</option>
    </select>
  </div>

  <div class="col-md-6">
    <label class="form-label">Quê quán</label>
    <input name="hometown" class="form-control" value="<?= htmlspecialchars($s['hometown'] ?? '') ?>">
  </div>

  <div class="col-md-3">
    <label class="form-label">Ngày cấp CCCD</label>
    <input type="date" name="id_issue_date" class="form-control" 
           max="<?= date('Y-m-d') ?>"
           value="<?= htmlspecialchars($s['id_issue_date'] ?? '') ?>">
    <small class="text-muted">Không thể là ngày tương lai</small>
  </div>

  <div class="col-md-3">
    <label class="form-label">Nơi cấp CCCD</label>
    <input name="id_issue_place" class="form-control" value="<?= htmlspecialchars($s['id_issue_place'] ?? '') ?>">
  </div>

  <div class="col-12">
    <label class="form-label">Địa chỉ</label>
    <input name="address" class="form-control" value="<?= htmlspecialchars($s['address'] ?? '') ?>">
  </div>

  <div class="col-md-6">
    <label class="form-label">Trạng thái</label>
    <input type="text" class="form-control" readonly value="<?= ((int)$s['is_active']===1) ? 'Đang hoạt động' : 'Đã khóa' ?>">
    <input type="hidden" name="is_active" value="<?= (int)$s['is_active'] ?>">
    <small class="text-muted">Dùng nút Khóa/Mở khóa ở danh sách để thay đổi</small>
  </div>

  <div class="col-md-6">
    <label class="form-label">Đổi mật khẩu (tuỳ chọn)</label>
    <input name="new_password" type="password" class="form-control" placeholder="Để trống nếu không đổi">
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
