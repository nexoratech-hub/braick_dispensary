<?php
// ================================================================
// FILE: frontend/pages/doctor/get_sidebar_stats.php
// AJAX ENDPOINT - GET DOCTOR SIDEBAR STATISTICS
// FOR DOCTOR SIDEBAR - REAL-TIME DATA
// BRAICK DISPENSARY
// ================================================================

session_start();

// ================================================================
// FORCE SESSION
// ================================================================
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    // If not logged in as doctor, try to use session data
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'error' => 'Not logged in']);
        exit;
    }
}

// Include database
require_once '../../../backend/config/database.php';

$db = Database::getInstance()->getConnection();

// ================================================================
// GET PARAMETERS
// ================================================================
$doctor_id = isset($_POST['doctor_id']) ? (int)$_POST['doctor_id'] : 0;
$branch_id = isset($_POST['branch_id']) ? (int)$_POST['branch_id'] : 1;

// If doctor_id not provided in POST, try to get from session
if ($doctor_id <= 0) {
    $doctor_id = $_SESSION['user_id'] ?? 0;
    $branch_id = $_SESSION['branch_id'] ?? 1;
}

$response = [
    'success' => false,
    'patientCount' => 0,
    'labCount' => 0,
    'referralCount' => 0,
    'appointmentCount' => 0,
    'pendingConsultations' => 0,
    'completedConsultations' => 0,
    'cancelledConsultations' => 0,
    'pendingPrescriptions' => 0,
    'totalConsultations' => 0,
    'proceduresCount' => 0,
    'toolsCount' => 0,
    'labTestsCount' => 0,
    'doctorName' => '',
    'doctorStatus' => 'offline'
];

if ($doctor_id <= 0) {
    echo json_encode($response);
    exit;
}

try {
    // ================================================================
    // 1. GET DOCTOR INFO
    // ================================================================
    $stmt = $db->prepare("
        SELECT full_name, is_online, status 
        FROM users 
        WHERE id = ? AND role = 'doctor'
    ");
    $stmt->execute([$doctor_id]);
    $doctor = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($doctor) {
        $response['doctorName'] = $doctor['full_name'] ?? '';
        $response['doctorStatus'] = ($doctor['is_online'] ?? 0) ? 'online' : 'offline';
    }
    
    // ================================================================
    // 2. TOTAL PATIENTS (Distinct patients assigned to this doctor)
    // ================================================================
    $stmt = $db->prepare("
        SELECT COUNT(DISTINCT patient_id) as count 
        FROM visits 
        WHERE doctor_id = ?
    ");
    $stmt->execute([$doctor_id]);
    $response['patientCount'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
    
    // ================================================================
    // 3. PENDING LAB TESTS
    // ================================================================
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM lab_tests 
        WHERE doctor_id = ? AND status IN ('pending', 'in_progress')
    ");
    $stmt->execute([$doctor_id]);
    $response['labCount'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
    
    // ================================================================
    // 4. PENDING REFERRALS
    // ================================================================
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM referrals 
        WHERE from_doctor_id = ? AND status = 'pending'
    ");
    $stmt->execute([$doctor_id]);
    $response['referralCount'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
    
    // ================================================================
    // 5. TODAY'S APPOINTMENTS
    // ================================================================
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM appointments 
        WHERE doctor_id = ? 
        AND DATE(appointment_date) = CURDATE() 
        AND status IN ('scheduled', 'confirmed')
    ");
    $stmt->execute([$doctor_id]);
    $response['appointmentCount'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
    
    // ================================================================
    // 6. PENDING CONSULTATIONS
    // ================================================================
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM visits 
        WHERE doctor_id = ? 
        AND status IN ('pending', 'assigned', 'with_doctor', 'lab_test')
        AND is_completed = 0
    ");
    $stmt->execute([$doctor_id]);
    $response['pendingConsultations'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
    
    // ================================================================
    // 7. COMPLETED CONSULTATIONS
    // ================================================================
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM visits 
        WHERE doctor_id = ? 
        AND status = 'completed'
        AND is_completed = 1
    ");
    $stmt->execute([$doctor_id]);
    $response['completedConsultations'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
    
    // ================================================================
    // 8. CANCELLED CONSULTATIONS
    // ================================================================
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM visits 
        WHERE doctor_id = ? 
        AND status = 'cancelled'
    ");
    $stmt->execute([$doctor_id]);
    $response['cancelledConsultations'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
    
    // ================================================================
    // 9. TOTAL CONSULTATIONS
    // ================================================================
    $response['totalConsultations'] = $response['pendingConsultations'] + 
                                       $response['completedConsultations'] + 
                                       $response['cancelledConsultations'];
    
    // ================================================================
    // 10. PENDING PRESCRIPTIONS
    // ================================================================
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM prescriptions 
        WHERE doctor_id = ? AND status = 'pending'
    ");
    $stmt->execute([$doctor_id]);
    $response['pendingPrescriptions'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
    
    // ================================================================
    // 11. SERVICES COUNTS
    // ================================================================
    // Procedures count
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM procedures 
        WHERE branch_id = ? AND is_active = 1
    ");
    $stmt->execute([$branch_id]);
    $response['proceduresCount'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
    
    // Tools count
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM procedure_tools 
        WHERE branch_id = ? AND is_active = 1
    ");
    $stmt->execute([$branch_id]);
    $response['toolsCount'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
    
    // Lab Tests count
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM lab_tests_catalog 
        WHERE branch_id = ? AND is_active = 1
    ");
    $stmt->execute([$branch_id]);
    $response['labTestsCount'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
    
    // ================================================================
    // 12. EXPIRING MEDICINES (if pharmacy table exists)
    // ================================================================
    try {
        $stmt = $db->prepare("
            SELECT COUNT(*) as count 
            FROM medications_inventory 
            WHERE branch_id = ? 
            AND status = 'active' 
            AND expiry_date < DATE_ADD(CURDATE(), INTERVAL 30 DAY)
            AND expiry_date >= CURDATE()
        ");
        $stmt->execute([$branch_id]);
        $response['expiringMedicines'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
    } catch (Exception $e) {
        $response['expiringMedicines'] = 0;
    }
    
    $response['success'] = true;
    
} catch (Exception $e) {
    $response['success'] = false;
    $response['error'] = $e->getMessage();
}

// ================================================================
// RETURN JSON
// ================================================================
header('Content-Type: application/json');
echo json_encode($response);