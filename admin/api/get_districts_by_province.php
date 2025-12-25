<?php
/**
 * API: Get districts by province
 * Returns JSON array of districts for a specific province
 * Parameter: province_id (required)
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../includes/db.php';

try {
    // $conn is already available from db.php
    
    $province_id = isset($_GET['province_id']) ? (int)$_GET['province_id'] : 0;
    
    if ($province_id <= 0) {
        throw new Exception('province_id is required');
    }
    
    $query = "SELECT district_id, district_name, province_id 
              FROM districts 
              WHERE province_id = $province_id 
              ORDER BY district_name";
    
    $result = mysqli_query($conn, $query);
    
    if (!$result) {
        throw new Exception(mysqli_error($conn));
    }
    
    $districts = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $districts[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'data' => $districts
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
