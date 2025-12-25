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
    header('Location: index.php?error=missing_room_id');
    exit;
}

try {
    // Ensure room belongs to this landlord via building.owner_user_id
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
        header('Location: index.php?error=not_owner');
        exit;
    }

    // Hide room
    $stmtU = mysqli_prepare($conn, "UPDATE rooms SET publish_status='HIDDEN' WHERE room_id=? LIMIT 1");
    mysqli_stmt_bind_param($stmtU, "i", $roomId);
    mysqli_stmt_execute($stmtU);
    mysqli_stmt_close($stmtU);

    // Optional: cancel open listing invoices (UNPAID/WAITING_CONFIRM) to avoid “treo”
    if (mysqli_query($conn, "SHOW TABLES LIKE 'service_invoices'")) {
        $stmtC = mysqli_prepare($conn, "
            UPDATE service_invoices
            SET status='CANCELLED'
            WHERE room_id=? AND invoice_type='LISTING_FEE'
              AND status IN ('UNPAID','WAITING_CONFIRM')
        ");
        mysqli_stmt_bind_param($stmtC, "i", $roomId);
        mysqli_stmt_execute($stmtC);
        mysqli_stmt_close($stmtC);
    }

    header('Location: index.php?msg=hidden');
    exit;

} catch (Throwable $e) {
    header('Location: index.php?error=exception');
    exit;
}
