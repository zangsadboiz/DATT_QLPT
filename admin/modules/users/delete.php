<?php
/**
 * Xóa chủ trọ
 */
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';

$role = (string)($_SESSION['role_name'] ?? '');
if ($role !== 'ADMIN') {
    $_SESSION['alert'] = ['type' => 'danger', 'message' => 'Bạn không có quyền xóa!'];
    header('Location: index.php');
    exit;
}

$userId = (int)($_GET['id'] ?? 0);

if ($userId <= 0) {
    $_SESSION['alert'] = ['type' => 'danger', 'message' => 'ID không hợp lệ!'];
    header('Location: index.php');
    exit;
}

// Lấy thông tin user
$stmt = mysqli_prepare($conn, "SELECT * FROM users WHERE user_id = ? AND role_id = 2");
mysqli_stmt_bind_param($stmt, "i", $userId);
mysqli_stmt_execute($stmt);
$rs = mysqli_stmt_get_result($stmt);
$user = $rs ? mysqli_fetch_assoc($rs) : null;
mysqli_stmt_close($stmt);

if (!$user) {
    $_SESSION['alert'] = ['type' => 'danger', 'message' => 'Không tìm thấy chủ trọ!'];
    header('Location: index.php');
    exit;
}

// Kiểm tra có dữ liệu liên quan không
$hasData = false;
$reasons = [];

// Check buildings
$rs = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM buildings WHERE owner_id = $userId");
if ($rs && ($row = mysqli_fetch_assoc($rs)) && $row['cnt'] > 0) {
    $hasData = true;
    $reasons[] = $row['cnt'] . ' dãy trọ';
}

// Check posts
$rs = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM posts WHERE user_id = $userId");
if ($rs && ($row = mysqli_fetch_assoc($rs)) && $row['cnt'] > 0) {
    $hasData = true;
    $reasons[] = $row['cnt'] . ' tin đăng';
}

// Check transactions
$rs = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM transactions WHERE user_id = $userId");
if ($rs && ($row = mysqli_fetch_assoc($rs)) && $row['cnt'] > 0) {
    $hasData = true;
    $reasons[] = $row['cnt'] . ' giao dịch';
}

if ($hasData) {
    $_SESSION['alert'] = [
        'type' => 'warning', 
        'message' => 'Không thể xóa chủ trọ "' . htmlspecialchars($user['full_name']) . '" vì đã có: ' . implode(', ', $reasons) . '. Hãy khóa tài khoản thay vì xóa.'
    ];
    header('Location: index.php');
    exit;
}

// Xóa user
$delStmt = mysqli_prepare($conn, "DELETE FROM users WHERE user_id = ? AND role_id = 3");
mysqli_stmt_bind_param($delStmt, "i", $userId);
$result = mysqli_stmt_execute($delStmt);
mysqli_stmt_close($delStmt);

if ($result) {
    $_SESSION['alert'] = ['type' => 'success', 'message' => 'Đã xóa chủ trọ "' . htmlspecialchars($user['full_name']) . '" thành công!'];
} else {
    $_SESSION['alert'] = ['type' => 'danger', 'message' => 'Lỗi khi xóa: ' . mysqli_error($conn)];
}

header('Location: index.php');
exit;
