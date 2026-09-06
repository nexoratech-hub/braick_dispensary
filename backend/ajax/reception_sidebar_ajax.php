<?php
// ================================================================
// FILE: backend/api/reception_sidebar_ajax.php
// RECEPTION SIDEBAR - DIRECT AJAX (NO EXTERNAL API)
// Gets data directly from database with AJAX
// ================================================================

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check login
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Check role
if (!in_array($_SESSION['role'], ['reception', 'admin'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized role']);
    exit;
}

// Get branch ID
$branch_id = isset($_POST['branch_id']) ? (int)$_POST['branch_id'] : $_SESSION['branch_id'] ?? 0;
$hash = isset($_POST['hash']) ? $_POST['hash'] : '';
$force_update = isset($_POST['force_update']) && $_POST['force_update'] == '1';

if ($branch_id <= 0) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid branch ID']);
    exit;
}

// Include database
require_once __DIR__ . '/../config/database.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    exit;
}

// ================================================================
// GET ALL STATISTICS - DIRECT FROM DATABASE
// ================================================================
$patient_count = 0;
$appointment_count = 0;
$pending_appointments = 0;
$today_visits = 0;
$pending_patients = 0;
$services_count = 0;

try {
    // 1. Total Patients
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM patients WHERE branch_id = ?");
    $stmt->execute([$branch_id]);
    $patient_count = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
    
    // 2. Today's Appointments
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM appointments WHERE branch_id = ? AND DATE(appointment_date) = CURDATE()");
    $stmt->execute([$branch_id]);
    $appointment_count = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
    
    // 3. Pending Appointments
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM appointments WHERE branch_id = ? AND status IN ('scheduled', 'pending')");
    $stmt->execute([$branch_id]);
    $pending_appointments = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
    
    // 4. Today's Visits
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM visits WHERE branch_id = ? AND DATE(created_at) = CURDATE()");
    $stmt->execute([$branch_id]);
    $today_visits = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
    
    // 5. Pending Patients (needs doctor assignment)
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM visits WHERE branch_id = ? AND status IN ('pending', 'assigned')");
    $stmt->execute([$branch_id]);
    $pending_patients = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
    
    // 6. Services Count
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM services WHERE branch_id = ? OR branch_id IS NULL");
    $stmt->execute([$branch_id]);
    $services_count = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
    
} catch (Exception $e) {
    error_log("Reception sidebar stats error: " . $e->getMessage());
}

// ================================================================
// GENERATE HASH AND CHECK CHANGES
// ================================================================
$data = [
    'patients' => $patient_count,
    'appointments' => $appointment_count,
    'pending_appointments' => $pending_appointments,
    'today_visits' => $today_visits,
    'pending_patients' => $pending_patients,
    'services_count' => $services_count
];

$new_hash = md5(json_encode($data));
$has_changed = ($hash !== $new_hash || $force_update);

// ================================================================
// RESPONSE
// ================================================================
header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'has_changed' => $has_changed,
    'hash' => $new_hash,
    'data' => $data,
    'summary' => [
        'patients' => $patient_count,
        'appointments' => $appointment_count,
        'pending_appointments' => $pending_appointments,
        'today_visits' => $today_visits,
        'pending_patients' => $pending_patients,
        'services_count' => $services_count
    ],
    'timestamp' => date('H:i:s'),
    'branch_id' => $branch_id
]);
exit;
?>