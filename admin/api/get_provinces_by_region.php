<?php
/**
 * API: Get provinces by region
 * Returns JSON array of provinces for a specific region
 * Parameter: region_id (optional, returns all if not specified)
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../includes/db.php';

try {
    // $conn is already available from db.php
    
    $region_id = isset($_GET['region_id']) ? (int)$_GET['region_id'] : 0;
    
    if ($region_id > 0) {
        $query = "SELECT province_id, province_name, province_code, region_id 
                  FROM provinces 
                  WHERE region_id = $region_id 
                  ORDER BY province_name";
    } else {
        $query = "SELECT province_id, province_name, province_code, region_id 
                  FROM provinces 
                  ORDER BY province_name";
    }
    
    $result = mysqli_query($conn, $query);
    
    if (!$result) {
        throw new Exception(mysqli_error($conn));
    }
    
    $provinces = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $provinces[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'data' => $provinces
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
