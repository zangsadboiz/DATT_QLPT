<?php
/**
 * Admin - Ẩn tin đăng (chuyển sang HIDDEN)
 */
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';

$role = (string)($_SESSION['role_name'] ?? '');
if ($role !== 'ADMIN') {
    header('Location: ' . ADMIN_BASE_PATH . '/index.php');
    exit;
}

$postId = (int)($_GET['id'] ?? 0);

if ($postId <= 0) {
    $_SESSION['alert'] = ['type' => 'danger', 'message' => 'ID tin không hợp lệ!'];
    header('Location: ' . ADMIN_BASE_PATH . '/modules/posts/index.php');
    exit;
}

// Kiểm tra tin tồn tại và đang APPROVED
$rsPost = mysqli_query($conn, "SELECT * FROM posts WHERE post_id = $postId");
$post = $rsPost ? mysqli_fetch_assoc($rsPost) : null;

if (!$post) {
    $_SESSION['alert'] = ['type' => 'danger', 'message' => 'Không tìm thấy tin đăng!'];
    header('Location: ' . ADMIN_BASE_PATH . '/modules/posts/index.php');
    exit;
}

if ($post['status'] !== 'APPROVED') {
    $_SESSION['alert'] = ['type' => 'warning', 'message' => 'Chỉ có thể ẩn tin đang hiển thị!'];
    header('Location: ' . ADMIN_BASE_PATH . '/modules/posts/index.php');
    exit;
}

// Cập nhật trạng thái tin thành HIDDEN
$result = mysqli_query($conn, "UPDATE posts SET status = 'HIDDEN', updated_at = NOW() WHERE post_id = $postId");

if ($result) {
    $_SESSION['alert'] = ['type' => 'success', 'message' => 'Đã ẩn tin đăng thành công!'];
} else {
    $_SESSION['alert'] = ['type' => 'danger', 'message' => 'Lỗi khi ẩn tin: ' . mysqli_error($conn)];
}

header('Location: ' . ADMIN_BASE_PATH . '/modules/posts/index.php');
exit;
