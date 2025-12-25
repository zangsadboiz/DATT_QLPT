<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/db.php';

$role = (string)($_SESSION['role_name'] ?? '');
$userId = (int)($_SESSION['user_id'] ?? 0);

if ($role !== 'LANDLORD') {
    header('Location: ' . ADMIN_BASE_PATH . '/index.php');
    exit;
}

$postId = (int)($_GET['id'] ?? 0);
if ($postId <= 0) {
    header('Location: ' . ADMIN_BASE_PATH . '/modules/landlord/posts/index.php');
    exit;
}

// Check ownership and delete
$post = mysqli_fetch_assoc(mysqli_query($conn, "SELECT post_id FROM posts WHERE post_id = $postId AND user_id = $userId"));

if ($post) {
    // Delete images
    mysqli_query($conn, "DELETE FROM post_images WHERE post_id = $postId");
    // Delete transactions related to this post
    mysqli_query($conn, "UPDATE transactions SET post_id = NULL WHERE post_id = $postId");
    // Delete post
    mysqli_query($conn, "DELETE FROM posts WHERE post_id = $postId AND user_id = $userId");
    
    header('Location: ' . ADMIN_BASE_PATH . '/modules/landlord/posts/index.php?msg=deleted');
} else {
    header('Location: ' . ADMIN_BASE_PATH . '/modules/landlord/posts/index.php?msg=not_found');
}
exit;
