<?php
/**
 * Xử lý yêu cầu rút tiền - Admin
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';

$role = $_SESSION['role_name'] ?? '';
if ($role !== 'ADMIN') {
    header('Location: /quanlyphongtro/admin/index.php?error=no_permission');
    exit;
}

$admin_id = (int)$_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$action = $_POST['action'] ?? '';
$id = (int)($_POST['id'] ?? 0);
$note = trim($_POST['note'] ?? '');

if ($id <= 0) {
    header('Location: index.php?error=invalid');
    exit;
}

// Lấy yêu cầu
$request = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM withdrawal_requests WHERE id = $id"));
if (!$request) {
    header('Location: index.php?error=not_found');
    exit;
}

$userId = $request['user_id'];
$amount = (float)$request['amount'];

switch ($action) {
    case 'approve':
        if ($request['status'] !== 'PENDING') {
            header('Location: index.php?error=already_processed');
            exit;
        }
        
        mysqli_query($conn, "
            UPDATE withdrawal_requests 
            SET status = 'COMPLETED', processed_by = $admin_id, processed_at = NOW(), admin_note = 'Đã chuyển khoản thành công'
            WHERE id = $id
        ");
        
        // Cập nhật mô tả và status trong transaction
        mysqli_query($conn, "
            UPDATE transactions 
            SET status = 'SUCCESS', description = REPLACE(description, 'Đang xử lý', 'Hoàn thành')
            WHERE user_id = $userId AND transaction_type = 'WITHDRAWAL' AND description LIKE '%#$id%'
        ");
        
        header('Location: index.php?status=all&success=completed');
        break;
        
    case 'reject':
        if ($request['status'] !== 'PENDING') {
            header('Location: index.php?error=already_processed');
            exit;
        }
        
        // Hoàn tiền về balance
        $user = mysqli_fetch_assoc(mysqli_query($conn, "SELECT balance FROM users WHERE user_id = $userId"));
        $currentBalance = (float)($user['balance'] ?? 0);
        $newBalance = $currentBalance + $amount;
        
        mysqli_query($conn, "UPDATE users SET balance = $newBalance WHERE user_id = $userId");
        
        // Cập nhật request
        $noteEsc = mysqli_real_escape_string($conn, $note ?: 'Yêu cầu bị từ chối');
        mysqli_query($conn, "
            UPDATE withdrawal_requests 
            SET status = 'REJECTED', processed_by = $admin_id, processed_at = NOW(), admin_note = '$noteEsc'
            WHERE id = $id
        ");
        
        // Tạo transaction hoàn tiền
        $desc = "Hoàn tiền rút #$id - $noteEsc";
        mysqli_query($conn, "
            INSERT INTO transactions (user_id, transaction_type, amount, balance_before, balance_after, description, status, created_at)
            VALUES ($userId, 'REFUND', $amount, $currentBalance, $newBalance, '$desc', 'SUCCESS', NOW())
        ");
        
        header('Location: index.php?status=all&success=rejected');
        break;
        
    case 'complete':
        if ($request['status'] !== 'APPROVED') {
            header('Location: index.php?error=not_approved');
            exit;
        }
        
        $noteEsc = mysqli_real_escape_string($conn, $note ?: 'Đã chuyển khoản thành công');
        mysqli_query($conn, "
            UPDATE withdrawal_requests 
            SET status = 'COMPLETED', processed_at = NOW(), admin_note = '$noteEsc'
            WHERE id = $id
        ");
        
        // Cập nhật transaction status
        mysqli_query($conn, "
            UPDATE transactions 
            SET status = 'SUCCESS', description = REPLACE(description, 'Đang xử lý', 'Hoàn thành')
            WHERE user_id = $userId AND transaction_type = 'WITHDRAWAL' AND description LIKE '%#$id%'
        ");
        
        // Cũng cập nhật nếu đang ở trạng thái Đã duyệt
        mysqli_query($conn, "
            UPDATE transactions 
            SET description = REPLACE(description, 'Đã duyệt', 'Hoàn thành')
            WHERE user_id = $userId AND transaction_type = 'WITHDRAWAL' AND description LIKE '%#$id%'
        ");
        
        header('Location: index.php?status=all&success=completed');
        break;
        
    default:
        header('Location: index.php?error=invalid_action');
}
exit;
