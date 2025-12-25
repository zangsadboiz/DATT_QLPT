<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/platform.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$role = (string)($_SESSION['role_name'] ?? '');
if (!in_array($role, ['ADMIN', 'STAFF'], true)) {
    admin_redirect('modules/dashboard/index.php', ['forbidden' => 1]);
}

$roomId = (int)($_GET['id'] ?? ($_GET['room_id'] ?? 0));
$status = (string)($_GET['s'] ?? ($_GET['status'] ?? ''));

$status = strtoupper(trim($status));
$allow = ['PENDING', 'APPROVED', 'HIDDEN'];
if ($roomId <= 0 || !in_array($status, $allow, true)) {
    header('Location: index.php?error=invalid');
    exit;
}

try {
    // Load room + building + owner
    $sqlCheck = "
        SELECT r.room_id, r.publish_status, r.room_code,
               b.building_id, b.building_status, b.owner_user_id
        FROM rooms r
        JOIN buildings b ON b.building_id = r.building_id
        WHERE r.room_id = ?
        LIMIT 1
    ";
    $stmt = mysqli_prepare($conn, $sqlCheck);
    mysqli_stmt_bind_param($stmt, "i", $roomId);
    mysqli_stmt_execute($stmt);
    $rs = mysqli_stmt_get_result($stmt);
    $row = $rs ? mysqli_fetch_assoc($rs) : null;
    mysqli_stmt_close($stmt);

    if (!$row) {
        header('Location: index.php?error=not_found');
        exit;
    }

    // Nếu duyệt phòng thì building phải APPROVED
    if ($status === 'APPROVED' && (string)$row['building_status'] !== 'APPROVED') {
        header('Location: index.php?error=building_not_approved');
        exit;
    }

    // Update publish_status
    $stmtU = mysqli_prepare($conn, "UPDATE rooms SET publish_status=? WHERE room_id=? LIMIT 1");
    mysqli_stmt_bind_param($stmtU, "si", $status, $roomId);
    mysqli_stmt_execute($stmtU);
    mysqli_stmt_close($stmtU);

    // Nếu APPROVED => tạo invoice phí đăng tin nếu cần
    if ($status === 'APPROVED') {
        // Check tồn tại bảng service_invoices (đỡ “làm mà không thấy”)
        $chkTbl = mysqli_query($conn, "SHOW TABLES LIKE 'service_invoices'");
        if (!$chkTbl || mysqli_num_rows($chkTbl) === 0) {
            header('Location: index.php?error=missing_service_invoices_table');
            exit;
        }

        $ownerUserId = (int)$row['owner_user_id'];
        $now = date('Y-m-d H:i:s');

        // Có PAID còn hạn?
        $sqlPaid = "
            SELECT svc_invoice_id
            FROM service_invoices
            WHERE room_id = ?
              AND invoice_type='LISTING_FEE'
              AND status='PAID'
              AND active_until IS NOT NULL
              AND active_until >= ?
            ORDER BY svc_invoice_id DESC
            LIMIT 1
        ";
        $stmtP = mysqli_prepare($conn, $sqlPaid);
        mysqli_stmt_bind_param($stmtP, "is", $roomId, $now);
        mysqli_stmt_execute($stmtP);
        $rsP = mysqli_stmt_get_result($stmtP);
        $paid = $rsP ? mysqli_fetch_assoc($rsP) : null;
        mysqli_stmt_close($stmtP);

        if (!$paid) {
            // Có invoice mở chưa?
            $sqlOpen = "
                SELECT svc_invoice_id
                FROM service_invoices
                WHERE room_id = ?
                  AND invoice_type='LISTING_FEE'
                  AND status IN ('UNPAID','WAITING_CONFIRM')
                ORDER BY svc_invoice_id DESC
                LIMIT 1
            ";
            $stmtO = mysqli_prepare($conn, $sqlOpen);
            mysqli_stmt_bind_param($stmtO, "i", $roomId);
            mysqli_stmt_execute($stmtO);
            $rsO = mysqli_stmt_get_result($stmtO);
            $open = $rsO ? mysqli_fetch_assoc($rsO) : null;
            mysqli_stmt_close($stmtO);

            if (!$open) {
                $addInfo = 'QPT-ROOM' . $roomId . '-' . date('YmdHis');

                $amount = (float)LISTING_FEE_AMOUNT;
                $days   = (int)LISTING_FEE_PERIOD_DAYS;
                $createdBy = (int)($_SESSION['user_id'] ?? 0);

                $bin = PLATFORM_BANK_BIN;
                $acc = PLATFORM_BANK_ACCOUNT;
                $accName = PLATFORM_BANK_ACCOUNT_NAME;

                $sqlIns = "
                    INSERT INTO service_invoices
                    (invoice_type, owner_user_id, room_id, amount, currency, status, period_days, add_info,
                     bank_bin, bank_account, bank_account_name, created_by, created_at)
                    VALUES
                    ('LISTING_FEE', ?, ?, ?, 'VND', 'UNPAID', ?, ?,
                     ?, ?, ?, ?, NOW())
                ";
                $stmtI = mysqli_prepare($conn, $sqlIns);

                // 9 placeholders => 9 types
                $types = "iidissssi"; // i(owner) i(room) d(amount) i(days) s(addInfo) s(bin) s(acc) s(name) i(created_by)

                mysqli_stmt_bind_param(
                    $stmtI,
                    $types,
                    $ownerUserId,
                    $roomId,
                    $amount,
                    $days,
                    $addInfo,
                    $bin,
                    $acc,
                    $accName,
                    $createdBy
                );
                mysqli_stmt_execute($stmtI);
                mysqli_stmt_close($stmtI);
            }
        }

        header('Location: index.php?msg=approved_and_invoiced');
        exit;
    }

    // Nếu HIDDEN thì hủy invoice mở (tuỳ chọn)
    if ($status === 'HIDDEN') {
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

    header('Location: index.php?msg=updated');
    exit;

} catch (Throwable $e) {
    // Nếu có lỗi thì quay về index với message để bạn biết ngay
    $m = urlencode($e->getMessage());
    header("Location: index.php?error=exception&message={$m}");
    exit;
}
