<?php
// ================================================================
// FILE: frontend/pages/doctor/get_patient_visits.php
// AJAX - Get visits for a specific patient (DOCTOR)
// BRAICK DISPENSARY
// ================================================================

session_start();

// ================================================================
// HEADERS - Return JSON
// ================================================================
header('Content-Type: application/json');

// ================================================================
// CHECK SESSION
// ================================================================
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$doctor_id = $_SESSION['user_id'];

// ================================================================
// GET PATIENT ID
// ================================================================
$patient_id = isset($_GET['patient_id']) ? (int)$_GET['patient_id'] : 0;

if ($patient_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid patient ID']);
    exit;
}

// ================================================================
// INCLUDE DATABASE
// ================================================================
$db_path = 'C:/xampp/htdocs/dispensary_system/backend/config/database.php';
if (file_exists($db_path)) {
    require_once $db_path;
} else {
    echo json_encode(['success' => false, 'message' => 'Database not found']);
    exit;
}

try {
    $db = Database::getInstance()->getConnection();
    
    // ================================================================
    // GET VISITS FOR THIS PATIENT (Only active visits)
    // ================================================================
    $stmt = $db->prepare("
        SELECT 
            v.id, 
            v.visit_number, 
            v.diagnosis, 
            v.created_at,
            v.status,
            p.full_name as patient_name
        FROM visits v
        JOIN patients p ON v.patient_id = p.id
        WHERE v.patient_id = ? 
        AND v.doctor_id = ?
        AND v.status IN ('pending', 'assigned', 'with_doctor', 'lab_test', 'prescribed')
        ORDER BY v.created_at DESC
    ");
    $stmt->execute([$patient_id, $doctor_id]);
    $visits = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // ================================================================
    // IF NO ACTIVE VISITS, TRY TO GET ANY VISIT
    // ================================================================
    if (empty($visits)) {
        $stmt = $db->prepare("
            SELECT 
                v.id, 
                v.visit_number, 
                v.diagnosis, 
                v.created_at,
                v.status,
                p.full_name as patient_name
            FROM visits v
            JOIN patients p ON v.patient_id = p.id
            WHERE v.patient_id = ? 
            AND v.doctor_id = ?
            ORDER BY v.created_at DESC
            LIMIT 5
        ");
        $stmt->execute([$patient_id, $doctor_id]);
        $visits = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // ================================================================
    // RETURN JSON
    // ================================================================
    echo json_encode([
        'success' => true,
        'visits' => $visits,
        'count' => count($visits),
        'patient_id' => $patient_id
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false, 
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>