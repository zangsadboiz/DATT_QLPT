<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$role = (string)($_SESSION['role_name'] ?? '');
$userId = (int)($_SESSION['user_id'] ?? 0);
if ($role !== 'LANDLORD' || $userId <= 0) {
    admin_redirect('modules/dashboard/index.php', ['forbidden' => 1]);
}

$roomId = (int)($_GET['room_id'] ?? 0);
if ($roomId <= 0) {
    header('Location: hidden.php?error=missing_room_id');
    exit;
}

try {
    // Ensure room belongs to this landlord
    $sql = "
        SELECT r.room_id
        FROM rooms r
        JOIN buildings b ON b.building_id = r.building_id
        WHERE r.room_id = ? AND b.owner_user_id = ?
        LIMIT 1
    ";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "ii", $roomId, $userId);
    mysqli_stmt_execute($stmt);
    $rs = mysqli_stmt_get_result($stmt);
    $ok = $rs && mysqli_fetch_assoc($rs);
    mysqli_stmt_close($stmt);

    if (!$ok) {
        header('Location: hidden.php?error=not_owner');
        exit;
    }

    // If has PAID listing fee still active => back to APPROVED, else back to PENDING (needs admin review/fee)
    $newStatus = 'PENDING';
    $now = date('Y-m-d H:i:s');

    $chkTbl = mysqli_query($conn, "SHOW TABLES LIKE 'service_invoices'");
    if ($chkTbl && mysqli_num_rows($chkTbl) > 0) {
        $sqlPaid = "
            SELECT MAX(active_until) AS max_until
            FROM service_invoices
            WHERE room_id=? AND invoice_type='LISTING_FEE' AND status='PAID'
        ";
        $stmtP = mysqli_prepare($conn, $sqlPaid);
        mysqli_stmt_bind_param($stmtP, "i", $roomId);
        mysqli_stmt_execute($stmtP);
        $rsP = mysqli_stmt_get_result($stmtP);
        $p = $rsP ? mysqli_fetch_assoc($rsP) : null;
        mysqli_stmt_close($stmtP);

        $maxUntil = (string)($p['max_until'] ?? '');
        if ($maxUntil !== '' && $maxUntil >= $now) {
            $newStatus = 'APPROVED';
        }
    }

    $stmtU = mysqli_prepare($conn, "UPDATE rooms SET publish_status=? WHERE room_id=? LIMIT 1");
    mysqli_stmt_bind_param($stmtU, "si", $newStatus, $roomId);
    mysqli_stmt_execute($stmtU);
    mysqli_stmt_close($stmtU);

    header('Location: hidden.php?msg=unhidden');
    exit;

} catch (Throwable $e) {
    header('Location: hidden.php?error=exception');
    exit;
}
