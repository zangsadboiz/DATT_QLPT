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

$errors = [];
$msg = (string)($_GET['msg'] ?? '');

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

// Load contracts that belong to this landlord (ACTIVE ưu tiên)
$contracts = fetch_all($conn, "
    SELECT c.contract_id, c.contract_code, c.contract_status, c.start_date, c.end_date,
           r.room_code, b.building_code, b.building_name
    FROM contracts c
    JOIN rooms r ON r.room_id = c.room_id
    JOIN buildings b ON b.building_id = r.building_id
    WHERE b.owner_user_id = ?
    ORDER BY (c.contract_status='ACTIVE') DESC, c.contract_id DESC
", "i", [$userId]);

// Default selected contract
$contractId = (int)($_GET['contract_id'] ?? 0);
if ($contractId <= 0 && !empty($contracts)) {
    $contractId = (int)$contracts[0]['contract_id'];
}

// POST create/link tenant
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $contractId = (int)($_POST['contract_id'] ?? 0);
    $studentUsername = trim((string)($_POST['student_username'] ?? ''));

    // Optional info to update tenant profile
    $studentCode   = trim((string)($_POST['student_code'] ?? ''));
    $phone         = trim((string)($_POST['phone'] ?? ''));
    $email         = trim((string)($_POST['email'] ?? ''));
    $idNumber      = trim((string)($_POST['id_number'] ?? ''));
    $dob           = trim((string)($_POST['dob'] ?? ''));
    $gender        = trim((string)($_POST['gender'] ?? ''));
    $address       = trim((string)($_POST['address'] ?? ''));
    $note          = trim((string)($_POST['note'] ?? ''));

    if ($contractId <= 0) $errors[] = 'Vui lòng chọn hợp đồng.';
    if ($studentUsername === '') $errors[] = 'Vui lòng nhập username sinh viên.';

    // Validate contract belongs to landlord
    if (empty($errors)) {
        $checkContract = fetch_one($conn, "
            SELECT c.contract_id
            FROM contracts c
            JOIN rooms r ON r.room_id = c.room_id
            JOIN buildings b ON b.building_id = r.building_id
            WHERE c.contract_id = ? AND b.owner_user_id = ?
            LIMIT 1
        ", "ii", [$contractId, $userId]);

        if (empty($checkContract)) {
            $errors[] = 'Hợp đồng không thuộc quyền của bạn.';
        }
    }

    // Find student user (role STUDENT)
    $studentUser = [];
    if (empty($errors)) {
        $studentUser = fetch_one($conn, "
            SELECT u.user_id, u.full_name, u.email, u.phone, u.username
            FROM users u
            JOIN roles r ON r.role_id = u.role_id
            WHERE r.role_name='STUDENT' AND u.username = ?
            LIMIT 1
        ", "s", [$studentUsername]);

        if (empty($studentUser)) {
            $errors[] = 'Không tìm thấy sinh viên (role STUDENT) với username đã nhập.';
        }
    }

    if (empty($errors)) {
        mysqli_begin_transaction($conn);

        try {
            $studentUserId = (int)$studentUser['user_id'];

            // Ensure tenant exists (unique by user_id)
            $tenant = fetch_one($conn, "
                SELECT tenant_id, full_name, student_code
                FROM tenants
                WHERE user_id = ?
                LIMIT 1
            ", "i", [$studentUserId]);

            if (empty($tenant)) {
                // Create tenant from users info + form
                $fullName = (string)$studentUser['full_name'];
                $ins = mysqli_prepare($conn, "
                    INSERT INTO tenants
                    (user_id, full_name, dob, gender, phone, email, id_number, student_code, address, note, created_by, created_at)
                    VALUES
                    (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ");

                $dobVal = ($dob !== '') ? $dob : null;
                $genderVal = ($gender !== '') ? $gender : null;
                $phoneVal = ($phone !== '') ? $phone : ((string)$studentUser['phone'] ?: null);
                $emailVal = ($email !== '') ? $email : ((string)$studentUser['email'] ?: null);
                $idVal = ($idNumber !== '') ? $idNumber : null;
                $scodeVal = ($studentCode !== '') ? $studentCode : null;
                $addrVal = ($address !== '') ? $address : null;
                $noteVal = ($note !== '') ? $note : null;

                mysqli_stmt_bind_param(
                    $ins,
                    "isssssssssi",
                    $studentUserId,
                    $fullName,
                    $dobVal,
                    $genderVal,
                    $phoneVal,
                    $emailVal,
                    $idVal,
                    $scodeVal,
                    $addrVal,
                    $noteVal,
                    $userId
                );
                mysqli_stmt_execute($ins);
                mysqli_stmt_close($ins);

                $tenantId = (int)mysqli_insert_id($conn);
            } else {
                $tenantId = (int)$tenant['tenant_id'];

                // Update tenant fields if provided (không bắt buộc)
                $upd = mysqli_prepare($conn, "
                    UPDATE tenants
                    SET
                      student_code = CASE WHEN ? <> '' THEN ? ELSE student_code END,
                      phone        = CASE WHEN ? <> '' THEN ? ELSE phone END,
                      email        = CASE WHEN ? <> '' THEN ? ELSE email END,
                      id_number    = CASE WHEN ? <> '' THEN ? ELSE id_number END,
                      dob          = CASE WHEN ? <> '' THEN ? ELSE dob END,
                      gender       = CASE WHEN ? <> '' THEN ? ELSE gender END,
                      address      = CASE WHEN ? <> '' THEN ? ELSE address END,
                      note         = CASE WHEN ? <> '' THEN ? ELSE note END
                    WHERE tenant_id = ?
                    LIMIT 1
                ");
                mysqli_stmt_bind_param(
                    $upd,
                    "sssssssssssssssssi",
                    $studentCode, $studentCode,
                    $phone, $phone,
                    $email, $email,
                    $idNumber, $idNumber,
                    $dob, $dob,
                    $gender, $gender,
                    $address, $address,
                    $note, $note,
                    $tenantId
                );
                mysqli_stmt_execute($upd);
                mysqli_stmt_close($upd);
            }

            // Link tenant to contract (avoid duplicate)
            $link = mysqli_prepare($conn, "
                INSERT INTO contract_tenants (contract_id, tenant_id, is_representative, move_in_date, note)
                VALUES (?, ?, 0, NULL, NULL)
                ON DUPLICATE KEY UPDATE tenant_id = tenant_id
            ");
            mysqli_stmt_bind_param($link, "ii", $contractId, $tenantId);
            mysqli_stmt_execute($link);
            mysqli_stmt_close($link);

            mysqli_commit($conn);

            header('Location: nguoithue.php?msg=added');
            exit;

        } catch (Throwable $e) {
            mysqli_rollback($conn);
            $errors[] = 'Lỗi khi thêm người thuê: ' . $e->getMessage();
        }
    }
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="pagetitle d-flex justify-content-between align-items-center">
  <h1>Thêm người thuê</h1>
  <a class="btn btn-outline-secondary" href="nguoithue.php">Quay lại</a>
</div>

<section class="section">
  <div class="card">
    <div class="card-body pt-3">

      <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
          <ul class="mb-0"><?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul>
        </div>
      <?php endif; ?>

      <form method="post" class="row g-3">
        <div class="col-md-12">
          <label class="form-label">Chọn hợp đồng (thuộc phòng của bạn)</label>
          <select name="contract_id" class="form-select" required>
            <option value="">-- Chọn hợp đồng --</option>
            <?php foreach ($contracts as $c): ?>
              <?php
                $cid = (int)$c['contract_id'];
                $label = (string)$c['contract_code'] . ' | ' . (string)$c['building_code'] . ' - ' . (string)$c['building_name']
                       . ' | Phòng: ' . (string)$c['room_code']
                       . ' | ' . (string)$c['contract_status'];
              ?>
              <option value="<?= $cid ?>" <?= $cid === $contractId ? 'selected' : '' ?>>
                <?= htmlspecialchars($label) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <div class="text-muted small mt-1">Nếu sinh viên đã có hợp đồng ở phòng này, hệ thống sẽ không tạo liên kết trùng.</div>
        </div>

        <div class="col-md-6">
          <label class="form-label">Username sinh viên (role STUDENT)</label>
          <input name="student_username" class="form-control" required
                 value="<?= htmlspecialchars((string)($_POST['student_username'] ?? '')) ?>"
                 placeholder="Ví dụ: sv001">
        </div>

        <div class="col-md-6">
          <label class="form-label">Mã sinh viên (tuỳ chọn)</label>
          <input name="student_code" class="form-control"
                 value="<?= htmlspecialchars((string)($_POST['student_code'] ?? '')) ?>"
                 placeholder="Ví dụ: 20123456">
        </div>

        <div class="col-md-4">
          <label class="form-label">SĐT (tuỳ chọn)</label>
          <input name="phone" class="form-control" value="<?= htmlspecialchars((string)($_POST['phone'] ?? '')) ?>">
        </div>

        <div class="col-md-4">
          <label class="form-label">Email (tuỳ chọn)</label>
          <input name="email" class="form-control" value="<?= htmlspecialchars((string)($_POST['email'] ?? '')) ?>">
        </div>

        <div class="col-md-4">
          <label class="form-label">CCCD/CMND (tuỳ chọn)</label>
          <input name="id_number" class="form-control" value="<?= htmlspecialchars((string)($_POST['id_number'] ?? '')) ?>">
        </div>

        <div class="col-md-4">
          <label class="form-label">Ngày sinh (tuỳ chọn)</label>
          <input type="date" name="dob" class="form-control" value="<?= htmlspecialchars((string)($_POST['dob'] ?? '')) ?>">
        </div>

        <div class="col-md-4">
          <label class="form-label">Giới tính (tuỳ chọn)</label>
          <select name="gender" class="form-select">
            <?php $g = (string)($_POST['gender'] ?? ''); ?>
            <option value="">-- Không chọn --</option>
            <option value="MALE" <?= $g==='MALE'?'selected':'' ?>>Nam</option>
            <option value="FEMALE" <?= $g==='FEMALE'?'selected':'' ?>>Nữ</option>
            <option value="OTHER" <?= $g==='OTHER'?'selected':'' ?>>Khác</option>
          </select>
        </div>

        <div class="col-md-4">
          <label class="form-label">Địa chỉ (tuỳ chọn)</label>
          <input name="address" class="form-control" value="<?= htmlspecialchars((string)($_POST['address'] ?? '')) ?>">
        </div>

        <div class="col-md-12">
          <label class="form-label">Ghi chú (tuỳ chọn)</label>
          <input name="note" class="form-control" value="<?= htmlspecialchars((string)($_POST['note'] ?? '')) ?>">
        </div>

        <div class="col-md-12 d-flex gap-2">
          <button class="btn btn-primary" type="submit">
            <i class="bi bi-plus-circle"></i> Thêm / Gắn vào hợp đồng
          </button>
          <a class="btn btn-outline-secondary" href="nguoithue.php">Hủy</a>
        </div>
      </form>

    </div>
  </div>
</section>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
