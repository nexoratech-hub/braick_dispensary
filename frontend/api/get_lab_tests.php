<?php
// ================================================================
// FILE: frontend/api/get_lab_tests.php
// API - GET LAB TESTS CATALOG
// BRAICK DISPENSARY
// ================================================================

// ================================================================
// HEADERS
// ================================================================
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// ================================================================
// INCLUDE DATABASE
// ================================================================
require_once __DIR__ . '/../../backend/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
    
    // Get branch_id from request (optional)
    $branch_id = isset($_GET['branch_id']) ? (int)$_GET['branch_id'] : 1;
    
    // Get all active lab tests
    $stmt = $db->prepare("
        SELECT id, test_name, test_code, category, price, description, reference_range, is_active
        FROM lab_tests_catalog 
        WHERE is_active = 1
        ORDER BY category, test_name
    ");
    $stmt->execute();
    $tests = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'tests' => $tests,
        'total' => count($tests)
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'tests' => []
    ]);
}
?>