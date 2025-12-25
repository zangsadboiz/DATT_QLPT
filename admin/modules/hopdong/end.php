<?php
require_once __DIR__ . '/../../includes/db.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
  header('Location: index.php?error=invalid');
  exit;
}
$id = (int)$_GET['id'];
$today = date('Y-m-d');

try {
  mysqli_begin_transaction($conn);

  $cRes = mysqli_query($conn, "
    SELECT contract_id, room_id, contract_status
    FROM contracts
    WHERE contract_id = $id
    FOR UPDATE
  ");
  if (!$cRes || mysqli_num_rows($cRes)===0) {
    mysqli_rollback($conn);
    header('Location: index.php?error=not_found');
    exit;
  }
  $c = mysqli_fetch_assoc($cRes);

  if ($c['contract_status'] !== 'ACTIVE') {
    mysqli_rollback($conn);
    header('Location: index.php?error=invalid_status');
    exit;
  }

  $room_id = (int)$c['room_id'];

  // lock room
  mysqli_query($conn, "
    SELECT room_status
    FROM rooms
    WHERE room_id = $room_id
    FOR UPDATE
  ");

  // end contract
  mysqli_query($conn, "
    UPDATE contracts
    SET contract_status = 'ENDED',
        end_date = '$today'
    WHERE contract_id = $id
      AND contract_status = 'ACTIVE'
  ");

  // set move_out_date for tenants (optional)
  mysqli_query($conn, "
    UPDATE contract_tenants
    SET move_out_date = '$today'
    WHERE contract_id = $id AND move_out_date IS NULL
  ");

  // room back to VACANT
  mysqli_query($conn, "
    UPDATE rooms
    SET room_status = 'VACANT'
    WHERE room_id = $room_id
  ");

  mysqli_commit($conn);
  header('Location: index.php?msg=ended');
  exit;

} catch (Throwable $e) {
  mysqli_rollback($conn);
  header('Location: index.php?error=server_error');
  exit;
}
