<?php
// ================================================================
// FILE: backend/ajax/get_doctor_status.php
// AJAX ENDPOINT - GET DOCTOR STATUS
// BRAICK DISPENSARY
// ================================================================

// Allow cross-origin requests from frontend
header('Content-Type: application/json');

// Start session to get user info
session_start();

// Include database
require_once '../config/database.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database connection failed'
    ]);
    exit;
}

// Get branch ID from request
$branch_id = isset($_GET['branch_id']) ? (int)$_GET['branch_id'] : 1;

// Get current timestamp
$current_time = date('Y-m-d H:i:s');

// First, update doctors who haven't updated their status in 5 minutes to offline
try {
    $stmt = $db->prepare("
        UPDATE users 
        SET is_online = 0 
        WHERE role = 'doctor' 
        AND branch_id = ? 
        AND is_online = 1 
        AND last_online < DATE_SUB(NOW(), INTERVAL 5 MINUTE)
    ");
    $stmt->execute([$branch_id]);
} catch (Exception $e) {
    // Silent fail - continue
}

// Fetch all doctors for this branch
try {
    $stmt = $db->prepare("
        SELECT 
            id, 
            full_name, 
            specialty, 
            is_online, 
            last_online,
            profile_pic
        FROM users 
        WHERE branch_id = ? 
        AND role = 'doctor' 
        AND status = 'active'
        ORDER BY is_online DESC, full_name ASC
    ");
    $stmt->execute([$branch_id]);
    $doctors = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format data
    $formatted_doctors = [];
    foreach ($doctors as $doctor) {
        // Generate avatar initial
        $name = $doctor['full_name'] ?? 'Doctor';
        $initial = strtoupper(substr($name, 0, 1));
        
        $formatted_doctors[] = [
            'id' => (int)$doctor['id'],
            'full_name' => $name,
            'specialty' => $doctor['specialty'] ?? 'General',
            'is_online' => (int)$doctor['is_online'],
            'last_online' => $doctor['last_online'],
            'initial' => $initial,
            'profile_pic' => $doctor['profile_pic'] ?? null
        ];
    }
    
    echo json_encode([
        'success' => true,
        'doctors' => $formatted_doctors,
        'total_doctors' => count($formatted_doctors),
        'online_count' => count(array_filter($formatted_doctors, function($d) { return $d['is_online'] == 1; })),
        'timestamp' => date('Y-m-d H:i:s'),
        'branch_id' => $branch_id
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching doctors: ' . $e->getMessage()
    ]);
}
?>