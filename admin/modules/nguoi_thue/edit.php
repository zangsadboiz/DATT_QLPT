<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$role   = (string)($_SESSION['role_name'] ?? '');
$userId = (int)($_SESSION['user_id'] ?? 0);

if ($role !== 'LANDLORD' || $userId <= 0) {
    header('Location: /quanlyphongtro/admin/index.php?error=no_permission');
    exit;
}

$tenantId = (int)($_GET['tenant_id'] ?? 0);
if ($tenantId <= 0) {
    header('Location: nguoithue.php?error=missing_tenant_id');
    exit;
}

function fetch_all(mysqli $conn, string $sql, string $types = '', array $params = []): array {
    $stmt = mysqli_prepare($conn, $sql);
    if ($types !== '') {
        $bind = [];
        $bind[] = $types;
        foreach ($params as $k => $v) $bind[] = &$params[$k];
        call_user_func_array([$stmt, 'bind_param'], $bind);
    }
    mysqli_stmt_execute($stmt);
    $rs = mysqli_stmt_get_result($stmt);
    $rows = [];
    while ($rs && ($r = mysqli_fetch_assoc($rs))) $rows[] = $r;
    mysqli_stmt_close($stmt);
    return $rows;
}

function fetch_one(mysqli $conn, string $sql, string $types = '', array $params = []): array {
    $rows = fetch_all($conn, $sql, $types, $params);
    return $rows[0] ?? [];
}

// Verify tenant belongs to landlord (exists in any contract of landlord)
$check = fetch_one($conn, "
    SELECT t.*
    FROM tenants t
    WHERE t.tenant_id = ?
      AND EXISTS (
        SELECT 1
        FROM contract_tenants ct
        JOIN contracts c ON c.contract_id = ct.contract_id
        JOIN rooms r ON r.room_id = c.room_id
        JOIN buildings b ON b.building_id = r.building_id
        WHERE ct.tenant_id = t.tenant_id
          AND b.owner_user_id = ?
      )
    LIMIT 1
", "ii", [$tenantId, $userId]);

if (empty($check)) {
    header('Location: nguoithue.php?error=not_owner');
    exit;
}

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // allowed fields
    $fullName     = trim((string)($_POST['full_name'] ?? ''));
    $studentCode  = trim((string)($_POST['student_code'] ?? ''));
    $phone        = trim((string)($_POST['phone'] ?? ''));
    $email        = trim((string)($_POST['email'] ?? ''));
    $idNumber     = trim((string)($_POST['id_number'] ?? ''));
    $dob          = trim((string)($_POST['dob'] ?? ''));
    $gender       = trim((string)($_POST['gender'] ?? ''));
    $idIssueDate  = trim((string)($_POST['id_issue_date'] ?? ''));
    $idIssuePlace = trim((string)($_POST['id_issue_place'] ?? ''));
    $hometown     = trim((string)($_POST['hometown'] ?? ''));
    $address      = trim((string)($_POST['address'] ?? ''));
    $note         = trim((string)($_POST['note'] ?? ''));

    if ($fullName === '') $errors[] = 'Họ tên không được để trống.';
    if ($gender !== '' && !in_array($gender, ['MALE','FEMALE','OTHER'], true)) $errors[] = 'Giới tính không hợp lệ.';

    if (empty($errors)) {
        try {
            $stmt = mysqli_prepare($conn, "
                UPDATE tenants
                SET full_name=?,
                    student_code=?,
                    phone=?,
                    email=?,
                    id_number=?,
                    dob=?,
                    gender=?,
                    id_issue_date=?,
                    id_issue_place=?,
                    hometown=?,
                    address=?,
                    note=?
                WHERE tenant_id=?
                LIMIT 1
            ");

            $dobVal = ($dob !== '') ? $dob : null;
            $genderVal = ($gender !== '') ? $gender : null;
            $idIssueDateVal = ($idIssueDate !== '') ? $idIssueDate : null;

            mysqli_stmt_bind_param(
                $stmt,
                "ssssssssssssi",
                $fullName,
                $studentCode,
                $phone,
                $email,
                $idNumber,
                $dobVal,
                $genderVal,
                $idIssueDateVal,
                $idIssuePlace,
                $hometown,
                $address,
                $note,
                $tenantId
            );
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            $success = true;

            // reload
            $check = fetch_one($conn, "
                SELECT t.*
                FROM tenants t
                WHERE t.tenant_id = ?
                LIMIT 1
            ", "i", [$tenantId]);

        } catch (Throwable $e) {
            $errors[] = 'Lỗi khi cập nhật: ' . $e->getMessage();
        }
    }
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="pagetitle d-flex justify-content-between align-items-center">
  <h1>Sửa người thuê</h1>
  <div class="d-flex gap-2">
    <a class="btn btn-outline-secondary" href="nguoithue.php">Quay lại</a>
  </div>
</div>

<section class="section">
  <div class="card">
    <div class="card-body pt-3">

      <?php if ($success): ?>
        <div class="alert alert-success">Cập nhật thành công.</div>
      <?php endif; ?>

      <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
          <ul class="mb-0"><?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul>
        </div>
      <?php endif; ?>

      <form method="post" class="row g-3">
        <div class="col-md-6">
          <label class="form-label">Họ tên</label>
          <input name="full_name" class="form-control" required value="<?= htmlspecialchars((string)($check['full_name'] ?? '')) ?>">
        </div>

        <div class="col-md-6">
          <label class="form-label">Mã sinh viên</label>
          <input name="student_code" class="form-control" value="<?= htmlspecialchars((string)($check['student_code'] ?? '')) ?>">
        </div>

        <div class="col-md-4">
          <label class="form-label">SĐT</label>
          <input name="phone" class="form-control" value="<?= htmlspecialchars((string)($check['phone'] ?? '')) ?>">
        </div>

        <div class="col-md-4">
          <label class="form-label">Email</label>
          <input name="email" class="form-control" value="<?= htmlspecialchars((string)($check['email'] ?? '')) ?>">
        </div>

        <div class="col-md-4">
          <label class="form-label">CCCD/CMND</label>
          <input name="id_number" class="form-control" value="<?= htmlspecialchars((string)($check['id_number'] ?? '')) ?>">
        </div>

        <div class="col-md-4">
          <label class="form-label">Ngày sinh</label>
          <input type="date" name="dob" class="form-control" value="<?= htmlspecialchars((string)($check['dob'] ?? '')) ?>">
        </div>

        <div class="col-md-4">
          <label class="form-label">Giới tính</label>
          <?php $g = (string)($check['gender'] ?? ''); ?>
          <select name="gender" class="form-select">
            <option value="" <?= $g===''?'selected':'' ?>>-- Không chọn --</option>
            <option value="MALE" <?= $g==='MALE'?'selected':'' ?>>Nam</option>
            <option value="FEMALE" <?= $g==='FEMALE'?'selected':'' ?>>Nữ</option>
            <option value="OTHER" <?= $g==='OTHER'?'selected':'' ?>>Khác</option>
          </select>
        </div>

        <div class="col-md-4">
          <label class="form-label">Ngày cấp CCCD</label>
          <input type="date" name="id_issue_date" class="form-control" value="<?= htmlspecialchars((string)($check['id_issue_date'] ?? '')) ?>">
        </div>

        <div class="col-md-6">
          <label class="form-label">Nơi cấp CCCD</label>
          <input name="id_issue_place" class="form-control" value="<?= htmlspecialchars((string)($check['id_issue_place'] ?? '')) ?>">
        </div>

        <div class="col-md-6">
          <label class="form-label">Quê quán</label>
          <input name="hometown" class="form-control" value="<?= htmlspecialchars((string)($check['hometown'] ?? '')) ?>">
        </div>

        <div class="col-md-12">
          <label class="form-label">Địa chỉ</label>
          <input name="address" class="form-control" value="<?= htmlspecialchars((string)($check['address'] ?? '')) ?>">
        </div>

        <div class="col-md-12">
          <label class="form-label">Ghi chú</label>
          <input name="note" class="form-control" value="<?= htmlspecialchars((string)($check['note'] ?? '')) ?>">
        </div>

        <div class="col-md-12 d-flex gap-2">
          <button class="btn btn-primary" type="submit"><i class="bi bi-save"></i> Lưu</button>
          <a class="btn btn-outline-secondary" href="nguoithue.php">Hủy</a>
        </div>
      </form>

    </div>
  </div>
</section>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
