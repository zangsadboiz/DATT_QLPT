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
if ($id <= 0) { header('Location: index.php?error=invalid'); exit; }

// owner check
$rs = mysqli_query($conn, "
  SELECT i.invoice_id
  FROM invoices i
  JOIN contracts c ON c.contract_id=i.contract_id
  JOIN rooms r ON r.room_id=c.room_id
  JOIN buildings b ON b.building_id=r.building_id
  WHERE i.invoice_id=$id AND b.owner_id=$user_id
  LIMIT 1
");
if (!($rs && mysqli_fetch_assoc($rs))) {
  header('Location: index.php?error=not_found');
  exit;
}

mysqli_query($conn, "UPDATE invoices SET invoice_status='VOID' WHERE invoice_id=$id LIMIT 1");
header('Location: view.php?id='.$id);
exit;

