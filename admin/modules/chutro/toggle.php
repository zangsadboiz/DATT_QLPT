<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';

$role = (string)($_SESSION['role_name'] ?? '');
if (!in_array($role, ['ADMIN', 'STAFF'], true)) {
    header('Location: ' . ADMIN_BASE_PATH . '/index.php');
    exit;
}

$userId = (int)($_GET['user_id'] ?? 0);
if ($userId <= 0) {
    header('Location: ' . ADMIN_BASE_PATH . '/modules/chutro/index.php?err=missing_user_id');
    exit;
}

// Ensure target is landlord and get current status
$sql = "SELECT u.user_id, u.is_active
        FROM users u
        JOIN roles r ON r.role_id = u.role_id
        WHERE u.user_id = ? AND r.role_name='LANDLORD'
        LIMIT 1";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $userId);
mysqli_stmt_execute($stmt);
$rs = mysqli_stmt_get_result($stmt);
$u = $rs ? mysqli_fetch_assoc($rs) : null;
mysqli_stmt_close($stmt);

if (!$u) {
    header('Location: ' . ADMIN_BASE_PATH . '/modules/chutro/index.php?err=not_found');
    exit;
}

// Toggle status
$newStatus = ((int)$u['is_active'] === 1) ? 0 : 1;

$sqlUpdate = "UPDATE users SET is_active = ? WHERE user_id = ?";
$stmtU = mysqli_prepare($conn, $sqlUpdate);
mysqli_stmt_bind_param($stmtU, "ii", $newStatus, $userId);
$success = mysqli_stmt_execute($stmtU);
mysqli_stmt_close($stmtU);

// Redirect back to detail page with message
$msg = $newStatus === 0 ? 'locked' : 'unlocked';
header('Location: ' . ADMIN_BASE_PATH . '/modules/chutro/detail.php?user_id=' . $userId . '&msg=' . $msg);
exit;
