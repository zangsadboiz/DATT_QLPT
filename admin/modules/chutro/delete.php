<?php
/**
 * Xóa chủ trọ (soft delete hoặc hard delete nếu không có dữ liệu)
 */
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/alerts.php';

$role = (string)($_SESSION['role_name'] ?? '');
if (!in_array($role, ['ADMIN'], true)) {
    admin_redirect('modules/dashboard/index.php', ['forbidden' => 1]);
}

$userId = (int)($_GET['user_id'] ?? 0);

if ($userId <= 0) {
    set_flash('danger', 'ID chủ trọ không hợp lệ!');
    admin_redirect('modules/chutro/index.php');
}

// Kiểm tra chủ trọ tồn tại
$stmt = mysqli_prepare($conn, "SELECT * FROM users WHERE user_id = ? AND role_id = 3");
mysqli_stmt_bind_param($stmt, "i", $userId);
mysqli_stmt_execute($stmt);
$rs = mysqli_stmt_get_result($stmt);
$user = $rs ? mysqli_fetch_assoc($rs) : null;
mysqli_stmt_close($stmt);

if (!$user) {
    set_flash('danger', 'Không tìm thấy chủ trọ!');
    admin_redirect('modules/chutro/index.php');
}

// Kiểm tra có dữ liệu liên quan không
$hasBuildings = false;
$hasPosts = false;

$rsBuilding = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM buildings WHERE owner_id = $userId");
if ($rsBuilding && ($row = mysqli_fetch_assoc($rsBuilding))) {
    $hasBuildings = $row['cnt'] > 0;
}

$rsPost = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM posts WHERE user_id = $userId");
if ($rsPost && ($row = mysqli_fetch_assoc($rsPost))) {
    $hasPosts = $row['cnt'] > 0;
}

if ($hasBuildings || $hasPosts) {
    // Không cho xóa nếu có dữ liệu, chỉ khuyên khóa tài khoản
    set_flash('warning', 'Không thể xóa chủ trọ này vì đã có dãy trọ hoặc tin đăng. Hãy khóa tài khoản thay vì xóa.');
    admin_redirect('modules/chutro/index.php');
}

// Xóa chủ trọ (hard delete vì không có dữ liệu liên quan)
$delStmt = mysqli_prepare($conn, "DELETE FROM users WHERE user_id = ? AND role_id = 3");
mysqli_stmt_bind_param($delStmt, "i", $userId);
$result = mysqli_stmt_execute($delStmt);
mysqli_stmt_close($delStmt);

if ($result) {
    set_flash('success', 'Đã xóa chủ trọ "' . htmlspecialchars($user['full_name']) . '" thành công!');
} else {
    set_flash('danger', 'Lỗi khi xóa chủ trọ: ' . mysqli_error($conn));
}

admin_redirect('modules/chutro/index.php');
