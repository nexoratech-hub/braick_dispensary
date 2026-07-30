<?php
// ================================================================
// FILE: frontend/api/get_doctor_status.php
// ================================================================
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

session_start();

require_once __DIR__ . '/../../backend/config/database.php';

try {
    $db = getDB();
    $branch_id = isset($_GET['branch_id']) ? (int)$_GET['branch_id'] : 1;
    
    // Get all doctors in this branch with their status
    $stmt = $db->prepare("
        SELECT id, full_name, specialty, is_online, profile_pic, last_online 
        FROM users 
        WHERE role = 'doctor' 
        AND status = 'active' 
        AND branch_id = ?
        ORDER BY is_online DESC, full_name
    ");
    $stmt->execute([$branch_id]);
    $doctors = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $online_doctors = 0;
    $total_doctors = count($doctors);
    
    foreach ($doctors as $doc) {
        if ($doc['is_online'] == 1) {
            $online_doctors++;
        }
    }
    
    echo json_encode([
        'success' => true,
        'online_doctors' => $online_doctors,
        'total_doctors' => $total_doctors,
        'doctors' => $doctors,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>