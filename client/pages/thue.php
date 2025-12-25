<?php
// client/pages/thue.php - Redirect to chitiet (không cần vì liên hệ trực tiếp)
$postId = (int)($_GET['post_id'] ?? $_GET['room_id'] ?? 0);
if ($postId > 0) {
    header('Location: /quanlyphongtro/client/index.php?page=chitiet&post_id=' . $postId);
} else {
    header('Location: /quanlyphongtro/client/index.php?page=phong');
}
exit;
