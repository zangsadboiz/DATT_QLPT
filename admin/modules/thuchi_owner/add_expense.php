<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';

$role = $_SESSION['role_name'] ?? '';
$user_id = (int)($_SESSION['user_id'] ?? 0);

if ($role !== 'LANDLORD' || $user_id <= 0) {
    header('Location: /quanlyphongtro/admin/index.php?error=no_permission');
    exit;
}

$errors = [];
$success = '';

// Lấy danh sách buildings
$buildings = mysqli_query($conn, "SELECT building_id, building_name FROM buildings WHERE owner_id = $user_id ORDER BY building_name");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $building_id = (int)($_POST['building_id'] ?? 0);
    $expense_type = $_POST['expense_type'] ?? 'OTHER';
    $amount = (float)($_POST['amount'] ?? 0);
    $expense_date = $_POST['expense_date'] ?? date('Y-m-d');
    $description = trim($_POST['description'] ?? '');
    $note = trim($_POST['note'] ?? '');

    // Validate
    if ($amount <= 0) $errors[] = 'Số tiền phải lớn hơn 0.';
    if (!$description) $errors[] = 'Vui lòng nhập diễn giải.';
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $expense_date)) $errors[] = 'Ngày không hợp lệ.';

    // Check building thuộc chủ trọ (nếu chọn)
    if ($building_id > 0) {
        $checkBuilding = mysqli_query($conn, "SELECT 1 FROM buildings WHERE building_id = $building_id AND owner_id = $user_id LIMIT 1");
        if (!$checkBuilding || mysqli_num_rows($checkBuilding) === 0) {
            $errors[] = 'Dãy/Tòa không hợp lệ.';
        }
    }

    if (empty($errors)) {
        $escType = mysqli_real_escape_string($conn, $expense_type);
        $escDate = mysqli_real_escape_string($conn, $expense_date);
        $escDesc = mysqli_real_escape_string($conn, $description);
        $escNote = mysqli_real_escape_string($conn, $note);
        $buildingVal = $building_id > 0 ? $building_id : 'NULL';

        $ok = mysqli_query($conn, "
            INSERT INTO expenses (building_id, expense_type, amount, expense_date, description, note, created_by)
            VALUES ($buildingVal, '$escType', $amount, '$escDate', '$escDesc', '$escNote', $user_id)
        ");

        if ($ok) {
            header('Location: index.php?msg=added');
            exit;
        } else {
            $errors[] = 'Lỗi thêm khoản chi: ' . mysqli_error($conn);
        }
    }
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="pagetitle d-flex justify-content-between align-items-center">
    <h1>Thêm khoản chi</h1>
    <a class="btn btn-secondary" href="index.php"><i class="bi bi-arrow-left"></i> Quay lại</a>
</div>

<?php if ($errors): ?>
    <div class="alert alert-danger">
        <ul class="mb-0">
            <?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<section class="section">
    <div class="card">
        <div class="card-body pt-3">
            <form method="post" class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Dãy/Tòa (tùy chọn)</label>
                    <select name="building_id" class="form-select">
                        <option value="0">-- Chung / Không chọn --</option>
                        <?php mysqli_data_seek($buildings, 0); while($b = mysqli_fetch_assoc($buildings)): ?>
                            <option value="<?= (int)$b['building_id'] ?>" <?= isset($_POST['building_id']) && (int)$_POST['building_id'] === (int)$b['building_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($b['building_name']) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Loại chi</label>
                    <select name="expense_type" class="form-select" required>
                        <option value="REPAIR" <?= ($_POST['expense_type'] ?? '') === 'REPAIR' ? 'selected' : '' ?>>Sửa chữa</option>
                        <option value="MAINTENANCE" <?= ($_POST['expense_type'] ?? '') === 'MAINTENANCE' ? 'selected' : '' ?>>Bảo trì</option>
                        <option value="UTILITIES" <?= ($_POST['expense_type'] ?? '') === 'UTILITIES' ? 'selected' : '' ?>>Điện/Nước</option>
                        <option value="OTHER" <?= ($_POST['expense_type'] ?? 'OTHER') === 'OTHER' ? 'selected' : '' ?>>Khác</option>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Số tiền</label>
                    <input type="number" name="amount" class="form-control" required min="1" value="<?= htmlspecialchars($_POST['amount'] ?? '') ?>">
                </div>

                <div class="col-md-4">
                    <label class="form-label">Ngày chi</label>
                    <input type="date" name="expense_date" class="form-control" required value="<?= htmlspecialchars($_POST['expense_date'] ?? date('Y-m-d')) ?>">
                </div>

                <div class="col-md-4">
                    <label class="form-label">&nbsp;</label>
                </div>

                <div class="col-md-12">
                    <label class="form-label">Diễn giải</label>
                    <input type="text" name="description" class="form-control" required placeholder="Ví dụ: Sửa ống nước phòng 202" value="<?= htmlspecialchars($_POST['description'] ?? '') ?>">
                </div>

                <div class="col-md-12">
                    <label class="form-label">Ghi chú (tùy chọn)</label>
                    <textarea name="note" class="form-control" rows="2"><?= htmlspecialchars($_POST['note'] ?? '') ?></textarea>
                </div>

                <div class="col-12">
                    <button class="btn btn-primary"><i class="bi bi-check-circle"></i> Lưu khoản chi</button>
                </div>
            </form>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
