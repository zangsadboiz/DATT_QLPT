<?php
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
    header('Location: ' . ADMIN_BASE_PATH . '/modules/posts/pending.php');
    exit;
}

// Get post with package info
$post = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT p.*, pk.package_name, pk.price_per_day
    FROM posts p
    JOIN packages pk ON pk.package_id = p.package_id
    WHERE p.post_id = $postId
"));
if (!$post) {
    header('Location: ' . ADMIN_BASE_PATH . '/modules/posts/pending.php');
    exit;
}

$landlordId = (int)$post['user_id'];
$adminId = (int)$_SESSION['user_id'];
$startDate = date('Y-m-d');
$endDate = date('Y-m-d', strtotime("+{$post['days_posted']} days"));

// Tính chi phí (dùng total_cost nếu có, hoặc tính lại)
$totalCost = (float)($post['total_cost'] ?? 0);
if ($totalCost <= 0) {
    $totalCost = (float)($post['price_per_day'] ?? 0) * (int)$post['days_posted'];
}

// Lấy số dư chủ trọ
$landlord = mysqli_fetch_assoc(mysqli_query($conn, "SELECT balance FROM users WHERE user_id = $landlordId"));
$balance = (float)($landlord['balance'] ?? 0);

// Kiểm tra số dư đủ không
if ($balance < $totalCost) {
    header('Location: ' . ADMIN_BASE_PATH . '/modules/posts/pending.php?msg=insufficient_balance');
    exit;
}

// Trừ số dư
$newBalance = $balance - $totalCost;
mysqli_query($conn, "UPDATE users SET balance = $newBalance WHERE user_id = $landlordId");

// Ghi transaction
$postCode = mysqli_real_escape_string($conn, $post['post_code'] ?? 'N/A');
$pkgName = mysqli_real_escape_string($conn, $post['package_name'] ?? '');
$days = (int)$post['days_posted'];
mysqli_query($conn, "INSERT INTO transactions (user_id, post_id, transaction_type, amount, 
    balance_before, balance_after, description, status, created_at)
    VALUES ($landlordId, $postId, 'POST_NEW', -$totalCost, $balance, $newBalance,
    'Đăng tin $postCode - $pkgName x $days ngày (đã duyệt)', 'SUCCESS', NOW())");

// Duyệt tin
mysqli_query($conn, "UPDATE posts SET 
    status = 'APPROVED', 
    start_date = '$startDate', 
    end_date = '$endDate',
    approved_by = $adminId,
    approved_at = NOW()
    WHERE post_id = $postId");

header('Location: ' . ADMIN_BASE_PATH . '/modules/posts/pending.php?msg=approved');
exit;
