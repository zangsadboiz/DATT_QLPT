<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json');

$provinceId = (int)($_GET['province_id'] ?? 0);

$districts = [];
if ($provinceId > 0) {
    $result = mysqli_query($conn, "SELECT district_id, district_name FROM districts WHERE province_id = $provinceId ORDER BY district_name");
    while ($row = mysqli_fetch_assoc($result)) {
        $districts[] = $row;
    }
}

echo json_encode($districts);
