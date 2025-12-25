<?php
require_once __DIR__ . '/../../includes/db.php';

$id = (int)$_GET['id'];

/* KHÔNG CHO XÓA PHÒNG ĐANG THUÊ */
$check = mysqli_query($conn, "
    SELECT 1 FROM rooms
    WHERE room_id = $id
    AND room_status = 'OCCUPIED'
");

if (mysqli_num_rows($check) > 0) {
    header('Location: index.php?error=occupied');
    exit;
}

/* XÓA PHÒNG */
mysqli_query($conn, "DELETE FROM rooms WHERE room_id = $id");

header('Location: index.php');
exit;
