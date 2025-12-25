<?php
require_once __DIR__ . '/../../includes/db.php';

$error = '';

/**
 * TẠO MÃ PHÒNG TỰ ĐỘNG THEO DÃY
 * Ví dụ: A101, A102 / B201...
 */
function generateRoomCode($conn, $building_id) {
    // lấy ký hiệu dãy (A, B, C...)
    $b = mysqli_fetch_assoc(
        mysqli_query($conn, "SELECT building_code FROM buildings WHERE building_id = $building_id")
    );
    $prefix = $b['building_code'];

    // lấy mã phòng lớn nhất hiện có trong dãy
    $rs = mysqli_query($conn, "
        SELECT room_code FROM rooms
        WHERE building_id = $building_id
        ORDER BY room_code DESC
        LIMIT 1
    ");

    if ($row = mysqli_fetch_assoc($rs)) {
        // tách số phía sau
        preg_match('/(\d+)$/', $row['room_code'], $m);
        $num = (int)$m[1] + 1;
    } else {
        $num = 101; // phòng đầu tiên
    }

    return $prefix . $num;
}

/* XỬ LÝ POST */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $building_id = (int)$_POST['building_id'];
    $type_id     = (int)$_POST['type_id'];
    $base_rent   = (float)$_POST['base_rent'];
    $room_status = $_POST['room_status'];
    $room_code   = generateRoomCode($conn, $building_id);

    /* UPLOAD ẢNH */
    $image = null;
    if (!empty($_FILES['image']['name'])) {
        $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
        $image = 'room_' . time() . '.' . $ext;

        move_uploaded_file(
            $_FILES['image']['tmp_name'],
            __DIR__ . '/../../uploads/rooms/' . $image
        );
    }

    mysqli_query($conn, "
        INSERT INTO rooms
        (room_code, building_id, type_id, base_rent, room_status, image)
        VALUES
        ('$room_code', $building_id, $type_id, $base_rent, '$room_status', '$image')
    ");

    header('Location: index.php');
    exit;
}

/* LOAD GIAO DIỆN */
require_once __DIR__ . '/../../includes/header.php';

$types = mysqli_query($conn, "SELECT type_id, type_name FROM room_types");
$buildings = mysqli_query($conn, "SELECT building_id, building_name FROM buildings");
?>

<div class="pagetitle d-flex justify-content-between align-items-center">
    <h1>Thêm phòng</h1>
</div>

<section class="section">
<div class="card">
<div class="card-body">

<form method="post" enctype="multipart/form-data">

<div class="alert alert-info">
    <i class="bi bi-info-circle"></i>
    Mã phòng sẽ được tạo <strong>tự động theo dãy nhà</strong>
</div>

<div class="mb-3">
    <label class="form-label">Dãy nhà</label>
    <select name="building_id" class="form-select" required>
        <?php while ($b = mysqli_fetch_assoc($buildings)): ?>
            <option value="<?= $b['building_id'] ?>">
                <?= $b['building_name'] ?>
            </option>
        <?php endwhile; ?>
    </select>
</div>

<div class="mb-3">
    <label class="form-label">Loại phòng</label>
    <select name="type_id" class="form-select" required>
        <?php while ($t = mysqli_fetch_assoc($types)): ?>
            <option value="<?= $t['type_id'] ?>">
                <?= $t['type_name'] ?>
            </option>
        <?php endwhile; ?>
    </select>
</div>

<div class="mb-3">
    <label class="form-label">Giá thuê</label>
    <input type="number" name="base_rent" class="form-control" required>
</div>

<div class="mb-3">
    <label class="form-label">Ảnh phòng</label>
    <input type="file" name="image" class="form-control" accept="image/*">
</div>

<div class="mb-3">
    <label class="form-label">Trạng thái</label>
    <select name="room_status" class="form-select">
        <option value="VACANT">Trống</option>
        <option value="MAINTENANCE">Bảo trì</option>
        <option value="LOCKED">Khóa</option>
    </select>
</div>

<button class="btn btn-primary">
    <i class="bi bi-save"></i> Lưu
</button>
<a href="index.php" class="btn btn-secondary">Quay lại</a>

</form>

</div>
</div>
</section>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
