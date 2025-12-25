<?php
/**
 * API: Lấy danh sách phòng trống của chủ trọ
 * Params: building_id (optional) - lọc theo dãy trọ
 */
header('Content-Type: application/json');

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

$userId = (int)($_SESSION['user_id'] ?? 0);
$role = $_SESSION['role_name'] ?? '';

if ($role !== 'LANDLORD' || $userId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$buildingId = (int)($_GET['building_id'] ?? 0);

// Lấy danh sách dãy trọ của chủ trọ
$buildings = [];
$rsBuildings = mysqli_query($conn, "
    SELECT b.building_id, b.building_name, b.building_code, b.address
    FROM buildings b
    WHERE b.owner_id = $userId AND b.building_status = 'ACTIVE'
    ORDER BY b.building_name
");
while ($rsBuildings && ($b = mysqli_fetch_assoc($rsBuildings))) {
    $buildings[] = $b;
}

// Lấy danh sách phòng trống
$rooms = [];
$sql = "
    SELECT r.room_id, r.room_code, r.floor, r.area, r.base_rent, r.daily_price, r.rental_type, r.deposit, r.max_occupants,
           r.amenities, r.building_id, b.building_name, b.address as building_address,
           d.district_id, d.district_name, p.province_id, p.province_name, p.region_id
    FROM rooms r
    JOIN buildings b ON b.building_id = r.building_id
    LEFT JOIN districts d ON d.district_id = b.district_id
    LEFT JOIN provinces p ON p.province_id = d.province_id
    WHERE b.owner_id = $userId 
      AND r.room_status = 'VACANT' 
      AND r.deleted_at IS NULL
      AND b.building_status = 'ACTIVE'
";

if ($buildingId > 0) {
    $sql .= " AND r.building_id = $buildingId";
}

$sql .= " ORDER BY b.building_name, r.floor, r.room_code";

$rsRooms = mysqli_query($conn, $sql);
while ($rsRooms && ($r = mysqli_fetch_assoc($rsRooms))) {
    $r['amenities'] = json_decode($r['amenities'] ?? '[]', true);
    $rooms[] = $r;
}

echo json_encode([
    'success' => true,
    'buildings' => $buildings,
    'rooms' => $rooms
]);
