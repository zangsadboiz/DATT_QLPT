<?php
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/db.php';

$role = $_SESSION['role_name'] ?? '';
$user_id = (int)($_SESSION['user_id'] ?? 0);

if ($role !== 'LANDLORD' || $user_id <= 0) {
    header('Location: /quanlyphongtro/admin/index.php');
    exit;
}

function hasTable(mysqli $conn, string $table): bool {
    $t = mysqli_real_escape_string($conn, $table);
    $rs = mysqli_query($conn, "
        SELECT COUNT(*) AS cnt
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = '$t'
    ");
    $row = $rs ? mysqli_fetch_assoc($rs) : null;
    return (int)($row['cnt'] ?? 0) > 0;
}

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

$HAS_SOFT = hasColumn($conn, 'rooms', 'deleted_at') && hasColumn($conn, 'rooms', 'deleted_by');
if (!$HAS_SOFT) {
    header('Location: index.php?error=missing_softdelete');
    exit;
}

/* AUTO PURGE: xóa khỏi CSDL sau 15 ngày (an toàn: chỉ xóa khi không còn ràng buộc) */
$joinBooking = (hasTable($conn,'bookings')) ? "LEFT JOIN bookings bk ON bk.room_id=r.room_id" : "";
$joinContract = (hasTable($conn,'contracts')) ? "LEFT JOIN contracts c ON c.room_id=r.room_id" : "";
$joinMaint = (hasTable($conn,'maintenance_requests')) ? "LEFT JOIN maintenance_requests mr ON mr.room_id=r.room_id" : "";

$whereSafe = [];
$whereSafe[] = "r.deleted_at IS NOT NULL";
$whereSafe[] = "r.deleted_at < (NOW() - INTERVAL 15 DAY)";
if (hasTable($conn,'bookings')) $whereSafe[] = "bk.room_id IS NULL";
if (hasTable($conn,'contracts')) $whereSafe[] = "c.room_id IS NULL";
if (hasTable($conn,'maintenance_requests')) $whereSafe[] = "mr.room_id IS NULL";

mysqli_query($conn, "
    DELETE r
    FROM rooms r
    JOIN buildings b ON b.building_id=r.building_id
    $joinBooking
    $joinContract
    $joinMaint
    WHERE b.owner_user_id=$user_id
      AND " . implode(" AND ", $whereSafe) . "
");

/* danh sách phòng đã xóa (<=15 ngày vẫn có thể khôi phục) */
$list = mysqli_query($conn, "
    SELECT r.room_id, r.room_code, r.base_rent, r.deleted_at,
           TIMESTAMPDIFF(DAY, r.deleted_at, NOW()) AS days_passed,
           b.building_name, b.building_code
    FROM rooms r
    JOIN buildings b ON b.building_id = r.building_id
    WHERE b.owner_user_id = $user_id
      AND r.deleted_at IS NOT NULL
    ORDER BY r.deleted_at DESC
");
?>

<div class="pagetitle d-flex justify-content-between align-items-center">
    <h1>Phòng đã xóa</h1>
    <a href="index.php" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Quay lại</a>
</div>

<?php if (isset($_GET['msg']) && $_GET['msg']=='restored'): ?>
<div class="alert alert-success alert-dismissible fade show">
    Khôi phục phòng thành công.
    <button class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if (isset($_GET['error']) && $_GET['error']=='restore_expired'): ?>
<div class="alert alert-danger alert-dismissible fade show">
    Không thể khôi phục: phòng đã quá 15 ngày trong thùng rác.
    <button class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if (isset($_GET['error']) && $_GET['error']=='restore_conflict'): ?>
<div class="alert alert-danger alert-dismissible fade show">
    Không thể khôi phục: phòng đang có đặt phòng/hợp đồng đang hoạt động.
    <button class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<section class="section">
<div class="card">
<div class="card-body">
<div class="table-responsive">
<table class="table table-bordered align-middle">
    <thead class="table-light">
        <tr>
            <th>Phòng</th>
            <th>Dãy/Tòa</th>
            <th>Giá</th>
            <th>Đã xóa lúc</th>
            <th>Còn lại</th>
            <th width="160">Khôi phục</th>
        </tr>
    </thead>
    <tbody>
    <?php $has=false; while($r=mysqli_fetch_assoc($list)): $has=true; ?>
        <?php
          $days = (int)($r['days_passed'] ?? 0);
          $left = 15 - $days;
          if ($left < 0) $left = 0;
          $canRestore = ($days < 15);
        ?>
        <tr>
            <td><strong><?= htmlspecialchars($r['room_code']) ?></strong></td>
            <td><?= htmlspecialchars($r['building_name']) ?> (<?= htmlspecialchars($r['building_code']) ?>)</td>
            <td><?= number_format((float)$r['base_rent']) ?> đ</td>
            <td><?= htmlspecialchars($r['deleted_at']) ?></td>
            <td>
                <?php if ($canRestore): ?>
                    <span class="badge bg-warning text-dark">Còn <?= $left ?> ngày</span>
                <?php else: ?>
                    <span class="badge bg-secondary">Hết hạn</span>
                <?php endif; ?>
            </td>
            <td>
                <?php if ($canRestore): ?>
                <a class="btn btn-sm btn-success"
                   href="restore.php?id=<?= (int)$r['room_id'] ?>"
                   onclick="return confirm('Khôi phục phòng <?= htmlspecialchars($r['room_code']) ?>?');">
                    <i class="bi bi-arrow-counterclockwise"></i> Khôi phục
                </a>
                <?php else: ?>
                    <span class="text-muted">—</span>
                <?php endif; ?>
            </td>
        </tr>
    <?php endwhile; ?>

    <?php if(!$has): ?>
        <tr><td colspan="6" class="text-center text-muted">Không có phòng nào trong thùng rác.</td></tr>
    <?php endif; ?>
    </tbody>
</table>
</div>
</div>
</div>
</section>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
