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

$adminId = (int)$_SESSION['user_id'];

// Get post info
$post = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT p.*, pk.price_per_day
    FROM posts p
    JOIN packages pk ON pk.package_id = p.package_id
    WHERE p.post_id = $postId AND p.status = 'PENDING'
"));

if (!$post) {
    header('Location: ' . ADMIN_BASE_PATH . '/modules/posts/pending.php');
    exit;
}

// Process rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $reason = mysqli_real_escape_string($conn, $_POST['reason'] ?? 'Không đạt yêu cầu');
    
    // Calculate refund
    $refundAmount = (float)$post['price_per_day'] * (int)$post['days_posted'];
    $landlordId = (int)$post['user_id'];
    
    // Get current balance
    $userResult = mysqli_fetch_assoc(mysqli_query($conn, "SELECT balance FROM users WHERE user_id = $landlordId"));
    $currentBalance = (float)($userResult['balance'] ?? 0);
    $newBalance = $currentBalance + $refundAmount;
    
    // Update user balance
    mysqli_query($conn, "UPDATE users SET balance = $newBalance WHERE user_id = $landlordId");
    
    // Create refund transaction
    mysqli_query($conn, "INSERT INTO transactions (user_id, post_id, transaction_type, amount, balance_before, balance_after, description, status, created_at)
        VALUES ($landlordId, $postId, 'REFUND', $refundAmount, $currentBalance, $newBalance, 'Hoàn tiền do tin bị từ chối: $reason', 'SUCCESS', NOW())");
    
    // Update post status
    mysqli_query($conn, "UPDATE posts SET 
        status = 'REJECTED', 
        rejection_reason = '$reason',
        approved_by = $adminId,
        approved_at = NOW()
        WHERE post_id = $postId");
    
    header('Location: ' . ADMIN_BASE_PATH . '/modules/posts/pending.php?msg=rejected');
    exit;
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="pagetitle">
    <h1><i class="bi bi-x-circle me-2"></i>Từ chối tin đăng</h1>
    <nav>
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= ADMIN_BASE_PATH ?>/modules/posts/pending.php">Tin chờ duyệt</a></li>
            <li class="breadcrumb-item active">Từ chối</li>
        </ol>
    </nav>
</div>

<section class="section">
    <div class="row justify-content-center">
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header bg-danger text-white">
                    <h5 class="mb-0"><i class="bi bi-exclamation-triangle me-2"></i>Xác nhận từ chối tin</h5>
                </div>
                <div class="card-body">
                    <div class="alert alert-info">
                        <strong>Tin đăng:</strong> <?= htmlspecialchars(mb_substr($post['title'], 0, 60)) ?>...
                        <br>
                        <strong>Phí đăng tin:</strong> <?= number_format((float)$post['price_per_day'] * (int)$post['days_posted'], 0, ',', '.') ?>đ
                        <br>
                        <small class="text-success"><i class="bi bi-arrow-return-left me-1"></i>Số tiền này sẽ được hoàn lại cho chủ trọ</small>
                    </div>
                    
                    <form action="" method="POST">
                        <div class="mb-3">
                            <label class="form-label">Lý do từ chối <span class="text-danger">*</span></label>
                            <textarea name="reason" class="form-control" rows="4" required
                                      placeholder="Nhập lý do từ chối..."></textarea>
                            <small class="text-muted">Lý do sẽ được hiển thị cho chủ trọ</small>
                        </div>
                        
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-danger" onclick="return confirm('Xác nhận TỪ CHỐI và HOÀN TIỀN cho chủ trọ?')">
                                <i class="bi bi-x-circle me-2"></i>Từ chối & Hoàn tiền
                            </button>
                            <a href="<?= ADMIN_BASE_PATH ?>/modules/posts/pending.php" class="btn btn-secondary">
                                <i class="bi bi-arrow-left me-2"></i>Quay lại
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
