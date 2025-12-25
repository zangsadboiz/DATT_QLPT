<?php
// client/pages/profile.php - Thông tin tài khoản sinh viên
$hotelier = '/quanlyphongtro/hotelier-1.0.0';

// Kiểm tra đăng nhập
$userId = (int)($_SESSION['user_id'] ?? 0);
if ($userId <= 0 || ($_SESSION['role_name'] ?? '') !== 'STUDENT') {
    echo '<script>window.location.href="/quanlyphongtro/client/index.php?page=login";</script>';
    return;
}

// Lấy thông tin user
$user = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM users WHERE user_id = $userId"));
if (!$user) {
    echo '<div class="container py-5"><div class="alert alert-danger">Không tìm thấy thông tin tài khoản.</div></div>';
    return;
}

// Lấy thông tin tenant (nếu có)
$tenant = null;
$tenantRs = mysqli_query($conn, "SELECT * FROM tenants WHERE user_id = $userId LIMIT 1");
if ($tenantRs && mysqli_num_rows($tenantRs) > 0) {
    $tenant = mysqli_fetch_assoc($tenantRs);
}

// Check available columns in tenants table
$hasStudentCode = false;
$hasDob = false;
$hasGender = false;
$hasAddress = false;
$hasHometown = false;
$hasIdIssueDate = false;
$hasIdIssuePlace = false;
$hasIdCard = false;

$colsRs = mysqli_query($conn, "SHOW COLUMNS FROM tenants");
if ($colsRs) {
    while ($col = mysqli_fetch_assoc($colsRs)) {
        $field = $col['Field'];
        if ($field === 'student_code') $hasStudentCode = true;
        if ($field === 'dob') $hasDob = true;
        if ($field === 'gender') $hasGender = true;
        if ($field === 'address') $hasAddress = true;
        if ($field === 'hometown') $hasHometown = true;
        if ($field === 'id_issue_date') $hasIdIssueDate = true;
        if ($field === 'id_issue_place') $hasIdIssuePlace = true;
        if ($field === 'id_card') $hasIdCard = true;
    }
}

$errors = [];
$success = '';

// Xử lý cập nhật thông tin
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $fullName = trim($_POST['full_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $studentCode = trim($_POST['student_code'] ?? '');
    $idCard = trim($_POST['id_card'] ?? '');
    $dob = $_POST['dob'] ?? null;
    $gender = $_POST['gender'] ?? null;
    $address = trim($_POST['address'] ?? '');
    $hometown = trim($_POST['hometown'] ?? '');
    $idIssueDate = $_POST['id_issue_date'] ?? null;
    $idIssuePlace = trim($_POST['id_issue_place'] ?? '');
    
    if (empty($fullName)) {
        $errors[] = 'Vui lòng nhập họ tên.';
    }
    if (empty($phone)) {
        $errors[] = 'Vui lòng nhập số điện thoại.';
    } elseif (!preg_match('/^(0[3|5|7|8|9])+([0-9]{8})$/', $phone)) {
        $errors[] = 'Số điện thoại không hợp lệ (VD: 0912345678).';
    }
    if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Email không hợp lệ.';
    }
    // Validate DOB >= 18 years old
    if ($dob && strtotime($dob) > strtotime('-18 years')) {
        $errors[] = 'Bạn phải đủ 18 tuổi.';
    }
    // Validate ID issue date <= today
    if ($idIssueDate && strtotime($idIssueDate) > time()) {
        $errors[] = 'Ngày cấp CCCD không được là ngày tương lai.';
    }
    
    // Kiểm tra email trùng
    if ($email && $email !== $user['email']) {
        $check = mysqli_fetch_assoc(mysqli_query($conn, "SELECT user_id FROM users WHERE email = '" . mysqli_real_escape_string($conn, $email) . "' AND user_id != $userId"));
        if ($check) {
            $errors[] = 'Email này đã được sử dụng bởi tài khoản khác.';
        }
    }
    
    if (empty($errors)) {
        mysqli_begin_transaction($conn);
        
        // Update users table
        $fullNameEsc = mysqli_real_escape_string($conn, $fullName);
        $phoneEsc = mysqli_real_escape_string($conn, $phone);
        $emailEsc = mysqli_real_escape_string($conn, $email);
        
        $sql = "UPDATE users SET full_name = '$fullNameEsc', phone = '$phoneEsc', email = '$emailEsc', updated_at = NOW() WHERE user_id = $userId";
        $ok1 = mysqli_query($conn, $sql);
        
        if ($ok1) {
            // Update or insert tenant record
            $studentCodeEsc = mysqli_real_escape_string($conn, $studentCode);
            $idCardEsc = mysqli_real_escape_string($conn, $idCard);
            $addressEsc = mysqli_real_escape_string($conn, $address);
            $hometownEsc = mysqli_real_escape_string($conn, $hometown);
            $idIssuePlaceEsc = mysqli_real_escape_string($conn, $idIssuePlace);
            
            if ($tenant) {
                // Update existing tenant
                $setT = ["full_name = '$fullNameEsc'", "phone = '$phoneEsc'", "email = '$emailEsc'"];
                if ($hasIdCard) $setT[] = "id_card = " . ($idCard !== '' ? "'$idCardEsc'" : "NULL");
                if ($hasStudentCode) $setT[] = "student_code = " . ($studentCode !== '' ? "'$studentCodeEsc'" : "NULL");
                if ($hasDob) $setT[] = "dob = " . ($dob ? "'".mysqli_real_escape_string($conn,$dob)."'" : "NULL");
                if ($hasGender) $setT[] = "gender = " . ($gender ? "'".mysqli_real_escape_string($conn,$gender)."'" : "NULL");
                if ($hasAddress) $setT[] = "address = " . ($address !== '' ? "'$addressEsc'" : "NULL");
                if ($hasHometown) $setT[] = "hometown = " . ($hometown !== '' ? "'$hometownEsc'" : "NULL");
                if ($hasIdIssueDate) $setT[] = "id_issue_date = " . ($idIssueDate ? "'".mysqli_real_escape_string($conn,$idIssueDate)."'" : "NULL");
                if ($hasIdIssuePlace) $setT[] = "id_issue_place = " . ($idIssuePlace !== '' ? "'$idIssuePlaceEsc'" : "NULL");
                
                $ok2 = mysqli_query($conn, "UPDATE tenants SET ".implode(", ", $setT)." WHERE user_id = $userId");
            } else {
                // Insert new tenant
                $ok2 = mysqli_query($conn, "INSERT INTO tenants (user_id, full_name, phone, email, created_at) VALUES ($userId, '$fullNameEsc', '$phoneEsc', '$emailEsc', NOW())");
            }
            
            if ($ok2) {
                mysqli_commit($conn);
                $_SESSION['full_name'] = $fullName;
                $success = 'Cập nhật thông tin thành công!';
                // Reload data
                $user = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM users WHERE user_id = $userId"));
                $tenant = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM tenants WHERE user_id = $userId LIMIT 1"));
            } else {
                mysqli_rollback($conn);
                $errors[] = 'Lỗi cập nhật hồ sơ: ' . mysqli_error($conn);
            }
        } else {
            mysqli_rollback($conn);
            $errors[] = 'Lỗi hệ thống: ' . mysqli_error($conn);
        }
    }
}

// Xử lý đổi mật khẩu
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    if (empty($currentPassword)) {
        $errors[] = 'Vui lòng nhập mật khẩu hiện tại.';
    } elseif (!password_verify($currentPassword, $user['password_hash'])) {
        $errors[] = 'Mật khẩu hiện tại không đúng.';
    }
    
    if (strlen($newPassword) < 6) {
        $errors[] = 'Mật khẩu mới phải có ít nhất 6 ký tự.';
    }
    if ($newPassword !== $confirmPassword) {
        $errors[] = 'Xác nhận mật khẩu không khớp.';
    }
    
    if (empty($errors)) {
        $hash = password_hash($newPassword, PASSWORD_DEFAULT);
        $sql = "UPDATE users SET password_hash = '$hash', updated_at = NOW() WHERE user_id = $userId";
        if (mysqli_query($conn, $sql)) {
            $success = 'Đổi mật khẩu thành công!';
        } else {
            $errors[] = 'Lỗi hệ thống.';
        }
    }
}
?>

<!-- Page Header Start -->
<div class="container-fluid page-header mb-5 p-0" style="background-image: url(<?= $hotelier ?>/img/carousel-1.jpg);">
    <div class="container-fluid page-header-inner py-5">
        <div class="container text-center pb-5">
            <h1 class="display-3 text-white mb-3 animated slideInDown">Tài khoản của tôi</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb justify-content-center text-uppercase">
                    <li class="breadcrumb-item"><a href="/quanlyphongtro/client/index.php?page=home" class="text-white">Trang chủ</a></li>
                    <li class="breadcrumb-item text-white active">Tài khoản</li>
                </ol>
            </nav>
        </div>
    </div>
</div>
<!-- Page Header End -->

<div class="container-xxl py-5">
    <div class="container">
        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show"><i class="bi bi-check-circle me-2"></i><?= htmlspecialchars($success) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <ul class="mb-0">
                    <?php foreach ($errors as $err): ?>
                        <li><?= htmlspecialchars($err) ?></li>
                    <?php endforeach; ?>
                </ul>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <div class="row g-5">
            <!-- Left: Thông tin cơ bản -->
            <div class="col-lg-8">
                <div class="wow fadeInUp" data-wow-delay="0.1s">
                    <div class="bg-light rounded p-4 p-lg-5">
                        <h4 class="mb-4"><i class="fa fa-user text-primary me-2"></i>Thông tin cá nhân</h4>
                        
                        <form method="POST">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Tên đăng nhập</label>
                                    <input type="text" class="form-control py-3 bg-white" value="<?= htmlspecialchars($user['username']) ?>" readonly>
                                    <small class="text-muted">Không thể thay đổi</small>
                                </div>
                                
                                <?php if ($hasStudentCode): ?>
                                <div class="col-md-6">
                                    <label class="form-label">Mã sinh viên</label>
                                    <input type="text" class="form-control py-3" name="student_code" value="<?= htmlspecialchars($tenant['student_code'] ?? '') ?>">
                                </div>
                                <?php endif; ?>
                                
                                <div class="col-md-6">
                                    <label class="form-label">Họ và tên <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control py-3" name="full_name" value="<?= htmlspecialchars($user['full_name']) ?>" required>
                                </div>
                                
                                <div class="col-md-6">
                                    <label class="form-label">Số điện thoại <span class="text-danger">*</span></label>
                                    <input type="tel" class="form-control py-3" name="phone" 
                                           value="<?= htmlspecialchars($user['phone'] ?? '') ?>" 
                                           pattern="0[3|5|7|8|9][0-9]{8}" 
                                           placeholder="VD: 0912345678" required>
                                    <small class="text-muted">10 số, bắt đầu bằng 03, 05, 07, 08 hoặc 09</small>
                                </div>
                                
                                <div class="col-md-6">
                                    <label class="form-label">Email</label>
                                    <input type="email" class="form-control py-3" name="email" value="<?= htmlspecialchars($user['email'] ?? '') ?>">
                                </div>
                                
                                <?php if ($hasIdCard): ?>
                                <div class="col-md-6">
                                    <label class="form-label">CCCD/CMND</label>
                                    <input type="text" class="form-control py-3" name="id_card" value="<?= htmlspecialchars($tenant['id_card'] ?? '') ?>" maxlength="12">
                                </div>
                                <?php endif; ?>
                                
                                <?php if ($hasDob): ?>
                                <div class="col-md-4">
                                    <label class="form-label">Ngày sinh</label>
                                    <input type="date" class="form-control py-3" name="dob" 
                                           max="<?= date('Y-m-d', strtotime('-18 years')) ?>"
                                           value="<?= htmlspecialchars($tenant['dob'] ?? '') ?>">
                                    <small class="text-muted">Phải đủ 18 tuổi</small>
                                </div>
                                <?php endif; ?>
                                
                                <?php if ($hasGender): ?>
                                <div class="col-md-4">
                                    <label class="form-label">Giới tính</label>
                                    <select name="gender" class="form-select py-3">
                                        <option value="">-- Chọn --</option>
                                        <option value="MALE" <?= (($tenant['gender'] ?? '') === 'MALE') ? 'selected' : '' ?>>Nam</option>
                                        <option value="FEMALE" <?= (($tenant['gender'] ?? '') === 'FEMALE') ? 'selected' : '' ?>>Nữ</option>
                                        <option value="OTHER" <?= (($tenant['gender'] ?? '') === 'OTHER') ? 'selected' : '' ?>>Khác</option>
                                    </select>
                                </div>
                                <?php endif; ?>
                                
                                <?php if ($hasHometown): ?>
                                <div class="col-md-4">
                                    <label class="form-label">Quê quán</label>
                                    <input type="text" class="form-control py-3" name="hometown" value="<?= htmlspecialchars($tenant['hometown'] ?? '') ?>">
                                </div>
                                <?php endif; ?>
                                
                                <?php if ($hasAddress): ?>
                                <div class="col-12">
                                    <label class="form-label">Địa chỉ hiện tại</label>
                                    <input type="text" class="form-control py-3" name="address" value="<?= htmlspecialchars($tenant['address'] ?? '') ?>">
                                </div>
                                <?php endif; ?>
                                
                                <?php if ($hasIdIssueDate): ?>
                                <div class="col-md-4">
                                    <label class="form-label">Ngày cấp CCCD</label>
                                    <input type="date" class="form-control py-3" name="id_issue_date" 
                                           max="<?= date('Y-m-d') ?>"
                                           value="<?= htmlspecialchars($tenant['id_issue_date'] ?? '') ?>">
                                </div>
                                <?php endif; ?>
                                
                                <?php if ($hasIdIssuePlace): ?>
                                <div class="col-md-8">
                                    <label class="form-label">Nơi cấp CCCD</label>
                                    <input type="text" class="form-control py-3" name="id_issue_place" value="<?= htmlspecialchars($tenant['id_issue_place'] ?? '') ?>">
                                </div>
                                <?php endif; ?>
                                
                                <div class="col-12">
                                    <button type="submit" name="update_profile" class="btn btn-primary py-3 px-5">
                                        <i class="fa fa-save me-2"></i>Lưu thay đổi
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Right: Đổi mật khẩu + Thống kê -->
            <div class="col-lg-4">
                <!-- Đổi mật khẩu -->
                <div class="wow fadeInUp mb-4" data-wow-delay="0.2s">
                    <div class="bg-light rounded p-4">
                        <h5 class="mb-3"><i class="fa fa-key text-primary me-2"></i>Đổi mật khẩu</h5>
                        
                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label">Mật khẩu hiện tại</label>
                                <input type="password" class="form-control" name="current_password" required>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Mật khẩu mới</label>
                                <input type="password" class="form-control" name="new_password" required minlength="6">
                                <small class="text-muted">Tối thiểu 6 ký tự</small>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Xác nhận mật khẩu</label>
                                <input type="password" class="form-control" name="confirm_password" required>
                            </div>
                            
                            <button type="submit" name="change_password" class="btn btn-outline-primary w-100">
                                <i class="fa fa-lock me-2"></i>Đổi mật khẩu
                            </button>
                        </form>
                    </div>
                </div>
                
                <!-- Thông tin tài khoản -->
                <div class="wow fadeInUp" data-wow-delay="0.3s">
                    <div class="bg-primary text-white rounded p-4">
                        <h5 class="mb-3"><i class="fa fa-info-circle me-2"></i>Thông tin tài khoản</h5>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Loại tài khoản:</span>
                            <strong>Sinh viên</strong>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Trạng thái:</span>
                            <strong><?= $user['is_active'] ? 'Hoạt động' : 'Đã khóa' ?></strong>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Ngày tạo:</span>
                            <strong><?= date('d/m/Y', strtotime($user['created_at'])) ?></strong>
                        </div>
                        <?php if ($user['last_login']): ?>
                        <div class="d-flex justify-content-between">
                            <span>Đăng nhập gần nhất:</span>
                            <strong><?= date('d/m/Y H:i', strtotime($user['last_login'])) ?></strong>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
