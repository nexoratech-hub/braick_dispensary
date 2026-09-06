<?php
// ================================================================
// FILE: backend/api/doctor_sidebar_ajax.php
// DOCTOR SIDEBAR - DIRECT AJAX (NO EXTERNAL API)
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
if ($_SESSION['role'] !== 'doctor') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized role']);
    exit;
}

// Get parameters
$doctor_id = isset($_POST['doctor_id']) ? (int)$_POST['doctor_id'] : 0;
$branch_id = isset($_POST['branch_id']) ? (int)$_POST['branch_id'] : 1;
$hash = isset($_POST['hash']) ? $_POST['hash'] : '';
$force_update = isset($_POST['force_update']) && $_POST['force_update'] == '1';

// Validate doctor ID matches session
if ($doctor_id !== (int)$_SESSION['user_id']) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid doctor ID']);
    exit;
}

if ($doctor_id <= 0) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid doctor ID']);
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
$lab_count = 0;
$referral_count = 0;
$appointment_count = 0;
$pending_consultations = 0;
$completed_consultations = 0;
$cancelled_consultations = 0;
$pending_prescriptions = 0;
$total_consultations = 0;
$procedures_count = 0;
$lab_tests_count = 0;
$expiring_medicines = 0;
$doctor_name = '';
$doctor_status = 'offline';

try {
    // 1. Doctor info
    $stmt = $db->prepare("SELECT full_name, is_online FROM users WHERE id = ? AND role = 'doctor' AND status = 'active'");
    $stmt->execute([$doctor_id]);
    $doctor = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($doctor) {
        $doctor_name = $doctor['full_name'] ?? '';
        $doctor_status = ($doctor['is_online'] ?? 0) ? 'online' : 'offline';
    }
    
    // 2. Total Patients
    $stmt = $db->prepare("SELECT COUNT(DISTINCT patient_id) as count FROM visits WHERE doctor_id = ?");
    $stmt->execute([$doctor_id]);
    $patient_count = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
    
    // 3. Pending Lab Tests
    try {
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM lab_tests WHERE doctor_id = ? AND status IN ('pending', 'in_progress')");
        $stmt->execute([$doctor_id]);
        $lab_count = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
    } catch (Exception $e) { $lab_count = 0; }
    
    // 4. Pending Referrals
    try {
        $stmt = $db->prepare("
            SELECT COUNT(*) as count 
            FROM referrals 
            WHERE from_doctor_id = ? 
            AND status IN ('pending', 'referred')
        ");
        $stmt->execute([$doctor_id]);
        $referral_count = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
    } catch (Exception $e) { $referral_count = 0; }
    
    // 5. Today's Appointments
    try {
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM appointments WHERE doctor_id = ? AND DATE(appointment_date) = CURDATE() AND status IN ('scheduled', 'confirmed')");
        $stmt->execute([$doctor_id]);
        $appointment_count = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
    } catch (Exception $e) { $appointment_count = 0; }
    
    // 6. Pending Consultations
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM visits 
        WHERE doctor_id = ? 
        AND status IN ('pending', 'assigned', 'with_doctor', 'lab_test', 'prescribed')
        AND is_completed = 0
    ");
    $stmt->execute([$doctor_id]);
    $pending_consultations = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
    
    // 7. Completed Consultations
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM visits 
        WHERE doctor_id = ? 
        AND status = 'completed'
        AND is_completed = 1
    ");
    $stmt->execute([$doctor_id]);
    $completed_consultations = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
    
    // 8. Cancelled Consultations
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM visits 
        WHERE doctor_id = ? 
        AND status = 'cancelled'
    ");
    $stmt->execute([$doctor_id]);
    $cancelled_consultations = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
    
    // 9. Total Consultations
    $total_consultations = $pending_consultations + $completed_consultations + $cancelled_consultations;
    
    // 10. Pending Prescriptions
    try {
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM prescriptions WHERE doctor_id = ? AND status = 'pending'");
        $stmt->execute([$doctor_id]);
        $pending_prescriptions = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
    } catch (Exception $e) { $pending_prescriptions = 0; }
    
    // 11. Procedures Count
    try {
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM procedures_catalog WHERE (branch_id IS NULL OR branch_id = ?) AND is_active = 1");
        $stmt->execute([$branch_id]);
        $procedures_count = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
    } catch (Exception $e) { $procedures_count = 0; }
    
    // 12. Lab Tests Count
    try {
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM lab_tests_catalog WHERE (branch_id IS NULL OR branch_id = ?) AND is_active = 1");
        $stmt->execute([$branch_id]);
        $lab_tests_count = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
    } catch (Exception $e) { $lab_tests_count = 0; }
    
    // 13. Expiring Medicines
    try {
        $stmt = $db->prepare("
            SELECT COUNT(*) as count 
            FROM medications_inventory 
            WHERE branch_id = ? 
            AND status = 'active' 
            AND expiry_date IS NOT NULL
            AND expiry_date < DATE_ADD(CURDATE(), INTERVAL 30 DAY)
            AND expiry_date >= CURDATE()
        ");
        $stmt->execute([$branch_id]);
        $expiring_medicines = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
    } catch (Exception $e) { $expiring_medicines = 0; }
    
} catch (Exception $e) {
    error_log("Doctor sidebar stats error: " . $e->getMessage());
}

// ================================================================
// GENERATE HASH AND CHECK CHANGES
// ================================================================
$data = [
    'patientCount' => $patient_count,
    'labCount' => $lab_count,
    'referralCount' => $referral_count,
    'appointmentCount' => $appointment_count,
    'pendingConsultations' => $pending_consultations,
    'completedConsultations' => $completed_consultations,
    'cancelledConsultations' => $cancelled_consultations,
    'pendingPrescriptions' => $pending_prescriptions,
    'totalConsultations' => $total_consultations,
    'proceduresCount' => $procedures_count,
    'labTestsCount' => $lab_tests_count,
    'expiringMedicines' => $expiring_medicines,
    'doctorName' => $doctor_name,
    'doctorStatus' => $doctor_status
];

$new_hash = md5(json_encode([
    'patient_count' => $data['patientCount'],
    'pending_consultations' => $data['pendingConsultations'],
    'lab_count' => $data['labCount'],
    'appointment_count' => $data['appointmentCount'],
    'referral_count' => $data['referralCount'],
    'pending_prescriptions' => $data['pendingPrescriptions'],
    'procedures_count' => $data['proceduresCount'],
    'lab_tests_count' => $data['labTestsCount'],
    'expiring_medicines' => $data['expiringMedicines'],
    'doctor_status' => $data['doctorStatus']
]));

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
        'pending_consultations' => $pending_consultations,
        'lab' => $lab_count,
        'appointments' => $appointment_count,
        'referrals' => $referral_count,
        'pending_prescriptions' => $pending_prescriptions,
        'total_consultations' => $total_consultations,
        'procedures' => $procedures_count,
        'lab_tests' => $lab_tests_count,
        'expiring_medicines' => $expiring_medicines
    ],
    'timestamp' => date('H:i:s'),
    'doctor_id' => $doctor_id,
    'branch_id' => $branch_id
]);
exit;
?>