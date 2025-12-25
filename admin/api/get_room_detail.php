<?php
/**
 * API: Lấy chi tiết một phòng cụ thể
 * Params: room_id (required)
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

$roomId = (int)($_GET['room_id'] ?? 0);

if ($roomId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid room_id']);
    exit;
}

// Lấy thông tin phòng
$sql = "
    SELECT r.*, b.building_name, b.building_code, b.address as building_address,
           b.electricity_price, b.water_price,
           d.district_id, d.district_name, p.province_id, p.province_name
    FROM rooms r
    JOIN buildings b ON b.building_id = r.building_id
    LEFT JOIN districts d ON d.district_id = b.district_id
    LEFT JOIN provinces p ON p.province_id = d.province_id
    WHERE r.room_id = $roomId 
      AND b.owner_id = $userId
      AND r.deleted_at IS NULL
";

$result = mysqli_query($conn, $sql);
$room = $result ? mysqli_fetch_assoc($result) : null;

if (!$room) {
    echo json_encode(['success' => false, 'message' => 'Room not found']);
    exit;
}

// Parse amenities
$room['amenities'] = json_decode($room['amenities'] ?? '[]', true);

// Get room images
$images = [];
$rsImages = mysqli_query($conn, "SELECT * FROM room_images WHERE room_id = $roomId ORDER BY is_primary DESC, sort_order");
while ($rsImages && ($img = mysqli_fetch_assoc($rsImages))) {
    $images[] = $img;
}
$room['images'] = $images;

echo json_encode([
    'success' => true,
    'room' => $room
]);
