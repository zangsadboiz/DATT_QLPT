<?php
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
    header('Location: index.php?error=invalid');
    exit;
}

mysqli_query($conn, "
    UPDATE bookings bk
    JOIN rooms r ON r.room_id = bk.room_id
    JOIN buildings b ON b.building_id = r.building_id
    SET bk.status = 'CANCELLED',
        bk.cancelled_at = NOW()
    WHERE bk.booking_id = $id
      AND bk.status = 'PENDING'
      AND b.owner_id = $user_id
");

header('Location: index.php?status=PENDING&msg=rejected');
exit;
