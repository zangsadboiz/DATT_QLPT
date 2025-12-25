<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';

$role = (string)($_SESSION['role_name'] ?? '');
if ($role !== 'ADMIN') {
    header('Location: ' . ADMIN_BASE_PATH . '/index.php');
    exit;
}

// Filters
$status = (string)($_GET['status'] ?? '');
$q = mysqli_real_escape_string($conn, (string)($_GET['q'] ?? ''));

// Build query
$sql = "SELECT u.*, 
        (SELECT COUNT(*) FROM posts WHERE user_id = u.user_id) as post_count,
        (SELECT COUNT(*) FROM posts WHERE user_id = u.user_id AND status = 'APPROVED') as active_posts,
        (SELECT COUNT(*) FROM buildings WHERE owner_id = u.user_id) as building_count,
        (SELECT COUNT(*) FROM rooms r JOIN buildings b ON b.building_id = r.building_id WHERE b.owner_id = u.user_id AND r.deleted_at IS NULL) as room_count
        FROM users u 
        WHERE u.role_id = 2";

if ($status === 'active') {
    $sql .= " AND u.is_active = 1";
} elseif ($status === 'locked') {
    $sql .= " AND u.is_active = 0";
}

if ($q !== '') {
    $sql .= " AND (u.full_name LIKE '%$q%' OR u.email LIKE '%$q%' OR u.phone LIKE '%$q%')";
}

$sql .= " ORDER BY u.created_at DESC";
$users = mysqli_query($conn, $sql);

// Stats
$stats = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active,
        SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) as locked
    FROM users WHERE role_id = 2
"));

// Handle toggle action
if (isset($_GET['toggle']) && isset($_GET['id'])) {
    $toggleId = (int)$_GET['id'];
    $action = $_GET['toggle'];
    
    if ($action === 'lock') {
        // Khóa tài khoản
        mysqli_query($conn, "UPDATE users SET is_active = 0 WHERE user_id = $toggleId AND role_id = 2");
        
        // Lưu trạng thái hiện tại của buildings để khôi phục sau
        // Ẩn tất cả dãy trọ
        mysqli_query($conn, "UPDATE buildings SET building_status = 'HIDDEN' WHERE owner_id = $toggleId AND building_status = 'ACTIVE'");
        
        // Ẩn tất cả tin đăng đang hiển thị
        mysqli_query($conn, "UPDATE posts SET status = 'HIDDEN' WHERE user_id = $toggleId AND status = 'APPROVED'");
        
        $_SESSION['alert'] = ['type' => 'warning', 'message' => 'Đã khóa tài khoản và ẩn tất cả dãy trọ, tin đăng!'];
        
    } else {
        // Mở khóa tài khoản
        mysqli_query($conn, "UPDATE users SET is_active = 1 WHERE user_id = $toggleId AND role_id = 2");
        
        // Mở lại dãy trọ
        mysqli_query($conn, "UPDATE buildings SET building_status = 'ACTIVE' WHERE owner_id = $toggleId AND building_status = 'HIDDEN'");
        
        // Mở lại tin đăng (chỉ những tin chưa hết hạn)
        mysqli_query($conn, "UPDATE posts SET status = 'APPROVED' WHERE user_id = $toggleId AND status = 'HIDDEN'");
        
        $_SESSION['alert'] = ['type' => 'success', 'message' => 'Đã mở khóa tài khoản và khôi phục dãy trọ, tin đăng!'];
    }
    
    header('Location: ' . ADMIN_BASE_PATH . '/modules/users/index.php');
    exit;
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="pagetitle">
    <h1><i class="bi bi-people me-2"></i>Quản lý Chủ trọ</h1>
    <nav>
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= ADMIN_BASE_PATH ?>/index.php">Dashboard</a></li>
            <li class="breadcrumb-item active">Chủ trọ</li>
        </ol>
    </nav>
</div>

<section class="section">

    <!-- Stats -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card text-center">
                <div class="card-body">
                    <h3 class="text-primary"><?= $stats['total'] ?? 0 ?></h3>
                    <p class="mb-0 text-muted">Tổng số chủ trọ</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-center">
                <div class="card-body">
                    <h3 class="text-success"><?= $stats['active'] ?? 0 ?></h3>
                    <p class="mb-0 text-muted">Đang hoạt động</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-center">
                <div class="card-body">
                    <h3 class="text-danger"><?= $stats['locked'] ?? 0 ?></h3>
                    <p class="mb-0 text-muted">Đã khóa</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Alerts -->
    <?php if (isset($_GET['msg'])): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <?php
            $msgs = [
                'locked' => 'Đã khóa tài khoản chủ trọ',
                'unlocked' => 'Đã mở khóa tài khoản chủ trọ'
            ];
            echo $msgs[$_GET['msg']] ?? 'Thao tác thành công';
            ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Filters -->
    <div class="card mb-3">
        <div class="card-body">
            <form method="get" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Trạng thái</label>
                    <select name="status" class="form-select">
                        <option value="">-- Tất cả --</option>
                        <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Đang hoạt động</option>
                        <option value="locked" <?= $status === 'locked' ? 'selected' : '' ?>>Đã khóa</option>
                    </select>
                </div>
                <div class="col-md-5">
                    <label class="form-label">Tìm kiếm</label>
                    <input type="text" name="q" class="form-control" placeholder="Tên, email, SĐT..."
                           value="<?= htmlspecialchars($_GET['q'] ?? '') ?>">
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-search me-1"></i>Lọc
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Users Table -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Danh sách chủ trọ</h5>
            <a href="<?= ADMIN_BASE_PATH ?>/modules/users/add.php" class="btn btn-success btn-sm">
                <i class="bi bi-plus-circle me-1"></i>Thêm chủ trọ
            </a>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Chủ trọ</th>
                            <th>Liên hệ</th>
                            <th>Số dư</th>
                            <th>Dãy trọ / Phòng</th>
                            <th>Tin đăng</th>
                            <th>Ngày đăng ký</th>
                            <th>Trạng thái</th>
                            <th>Hành động</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $i = 0; while ($users && ($user = mysqli_fetch_assoc($users))): $i++; ?>
                            <tr>
                                <td><?= $i ?></td>
                                <td>
                                    <strong><?= htmlspecialchars($user['full_name']) ?></strong>
                                    <br>
                                    <small class="text-muted">@<?= htmlspecialchars($user['username']) ?></small>
                                </td>
                                <td>
                                    <i class="bi bi-envelope me-1"></i><?= htmlspecialchars($user['email']) ?>
                                    <br>
                                    <i class="bi bi-phone me-1"></i><?= htmlspecialchars($user['phone'] ?: 'N/A') ?>
                                </td>
                                <td class="text-success fw-bold">
                                    <?= number_format((float)$user['balance'], 0, ',', '.') ?>đ
                                </td>
                                <td>
                                    <a href="<?= ADMIN_BASE_PATH ?>/modules/admin_buildings/index.php?owner_id=<?= $user['user_id'] ?>" 
                                       class="badge bg-info text-decoration-none" title="Xem dãy trọ">
                                        <?= (int)$user['building_count'] ?> dãy
                                    </a>
                                    <a href="<?= ADMIN_BASE_PATH ?>/modules/admin_rooms/index.php?owner_id=<?= $user['user_id'] ?>"
                                       class="badge bg-secondary text-decoration-none" title="Xem phòng">
                                        <?= (int)$user['room_count'] ?> phòng
                                    </a>
                                </td>
                                <td>
                                    <span class="badge bg-primary"><?= $user['active_posts'] ?> đang hiện</span>
                                    <span class="badge bg-secondary"><?= $user['post_count'] ?> tổng</span>
                                </td>
                                <td><?= date('d/m/Y', strtotime($user['created_at'])) ?></td>
                                <td>
                                    <?php if ($user['is_active']): ?>
                                        <span class="badge bg-success">Hoạt động</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Đã khóa</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="<?= ADMIN_BASE_PATH ?>/modules/users/view.php?id=<?= $user['user_id'] ?>" 
                                       class="btn btn-sm btn-outline-info" title="Xem chi tiết">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <a href="<?= ADMIN_BASE_PATH ?>/modules/users/edit.php?id=<?= $user['user_id'] ?>" 
                                       class="btn btn-sm btn-outline-primary" title="Sửa">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <?php if ($user['is_active']): ?>
                                        <a href="?toggle=lock&id=<?= $user['user_id'] ?>" 
                                           class="btn btn-sm btn-outline-warning" title="Khóa tài khoản"
                                           onclick="return confirm('Khóa tài khoản này? Chủ trọ sẽ không thể đăng nhập.')">
                                            <i class="bi bi-lock"></i>
                                        </a>
                                    <?php else: ?>
                                        <a href="?toggle=unlock&id=<?= $user['user_id'] ?>" 
                                           class="btn btn-sm btn-outline-success" title="Mở khóa tài khoản"
                                           onclick="return confirm('Mở khóa tài khoản này?')">
                                            <i class="bi bi-unlock"></i>
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                        <?php if (!$users || mysqli_num_rows($users) == 0): ?>
                            <tr>
                                <td colspan="8" class="text-center text-muted py-4">Không có dữ liệu</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</section>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
