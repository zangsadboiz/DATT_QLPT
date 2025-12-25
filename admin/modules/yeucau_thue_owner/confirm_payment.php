<?php
/**
 * Xác nhận thanh toán đặt cọc - Chủ trọ
 * Chuyển booking từ CONFIRMED → DEPOSIT_PAID
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';

$role = $_SESSION['role_name'] ?? '';
$user_id = (int)($_SESSION['user_id'] ?? 0);

if ($role !== 'LANDLORD' || $user_id <= 0) {
    header('Location: /quanlyphongtro/admin/index.php?error=no_permission');
    exit;
}

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: index.php');
    exit;
}

// Kiểm tra booking thuộc về chủ trọ này
$bk = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT bk.*, b.owner_id
    FROM bookings bk
    LEFT JOIN rooms r ON r.room_id = bk.room_id
    LEFT JOIN buildings b ON b.building_id = r.building_id
    WHERE bk.booking_id = $id AND b.owner_id = $user_id
    LIMIT 1
"));

if (!$bk) {
    header('Location: index.php?error=not_found');
    exit;
}

if ($bk['status'] !== 'CONFIRMED') {
    header('Location: detail.php?id=' . $id . '&error=invalid_status');
    exit;
}

// Cập nhật trạng thái booking
$depositAmount = (float)($bk['deposit_amount'] ?? 0);
$result = mysqli_query($conn, "
    UPDATE bookings 
    SET status = 'DEPOSIT_PAID', 
        deposit_paid_at = NOW()
    WHERE booking_id = $id
");

if ($result) {
    header('Location: detail.php?id=' . $id . '&success=payment_confirmed');
} else {
    header('Location: detail.php?id=' . $id . '&error=update_failed');
}
exit;
