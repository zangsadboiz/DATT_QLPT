<?php
/**
 * API: Get all regions
 * Returns JSON array of all regions
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../includes/db.php';

try {
    // $conn is already available from db.php
    
    $query = "SELECT region_id, region_name, region_code 
              FROM regions 
              ORDER BY region_id";
    
    $result = mysqli_query($conn, $query);
    
    if (!$result) {
        throw new Exception(mysqli_error($conn));
    }
    
    $regions = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $regions[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'data' => $regions
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
