<?php
// ================================================================
// FILE: frontend/pages/doctor/get_sidebar_stats.php
// AJAX ENDPOINT - GET DOCTOR SIDEBAR STATISTICS
// FOR DOCTOR SIDEBAR - REAL-TIME DATA
// BRAICK DISPENSARY
// ================================================================

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ================================================================
// LOGIN PROTECTION - CHECK IF USER IS LOGGED IN
// ================================================================
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => 'Not logged in',
        'redirect' => '../login.php'
    ]);
    exit;
}

// ================================================================
// CHECK IF USER IS DOCTOR OR ADMIN
// ================================================================
if ($_SESSION['role'] !== 'doctor' && $_SESSION['role'] !== 'admin') {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => 'Unauthorized access'
    ]);
    exit;
}

// ================================================================
// GET USER INFO FROM SESSION
// ================================================================
$user_id = $_SESSION['user_id'];
$user_full_name = $_SESSION['full_name'] ?? 'Doctor';
$user_role = $_SESSION['role'];
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$is_admin = ($_SESSION['role'] === 'admin');

// ================================================================
// INCLUDE DATABASE - CORRECT PATH
// ================================================================
require_once __DIR__ . '/../../../backend/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => 'Database connection error: ' . $e->getMessage()
    ]);
    exit;
}

// ================================================================
// GET PARAMETERS
// ================================================================
$doctor_id = isset($_POST['doctor_id']) ? (int)$_POST['doctor_id'] : 0;
$branch_id = isset($_POST['branch_id']) ? (int)$_POST['branch_id'] : $user_branch_id;

// If doctor_id not provided in POST, try to get from session
if ($doctor_id <= 0) {
    $doctor_id = $user_id;
}

// ================================================================
// IF ADMIN, ALLOW VIEWING OTHER DOCTOR'S STATS
// ================================================================
if ($is_admin && isset($_POST['target_doctor_id'])) {
    $doctor_id = (int)$_POST['target_doctor_id'];
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
    'doctorStatus' => 'offline',
    'expiringMedicines' => 0,
    'totalPatients' => 0,
    'is_admin' => $is_admin
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
    } else {
        // If no doctor found, try to get the user as admin viewing
        if ($is_admin) {
            $stmt = $db->prepare("
                SELECT full_name, is_online, status 
                FROM users 
                WHERE id = ?
            ");
            $stmt->execute([$doctor_id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($user) {
                $response['doctorName'] = $user['full_name'] ?? '';
                $response['doctorStatus'] = ($user['is_online'] ?? 0) ? 'online' : 'offline';
            }
        }
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
    // 11. TOTAL PATIENTS (All patients assigned to this doctor)
    // ================================================================
    $stmt = $db->prepare("
        SELECT COUNT(DISTINCT p.id) as count 
        FROM patients p
        WHERE p.assigned_doctor_id = ?
    ");
    $stmt->execute([$doctor_id]);
    $response['totalPatients'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
    
    // ================================================================
    // 12. SERVICES COUNTS (Branch specific)
    // ================================================================
    // Procedures count
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM procedures 
        WHERE (branch_id IS NULL OR branch_id = ?) AND is_active = 1
    ");
    $stmt->execute([$branch_id]);
    $response['proceduresCount'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
    
    // Tools count
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM procedure_tools 
        WHERE (branch_id IS NULL OR branch_id = ?) AND is_active = 1
    ");
    $stmt->execute([$branch_id]);
    $response['toolsCount'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
    
    // Lab Tests count
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM lab_tests_catalog 
        WHERE (branch_id IS NULL OR branch_id = ?) AND is_active = 1
    ");
    $stmt->execute([$branch_id]);
    $response['labTestsCount'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
    
    // ================================================================
    // 13. EXPIRING MEDICINES (if pharmacy table exists)
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
    
    // ================================================================
    // 14. TODAY'S VISITS
    // ================================================================
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM visits 
        WHERE doctor_id = ? 
        AND DATE(created_at) = CURDATE()
    ");
    $stmt->execute([$doctor_id]);
    $response['todayVisits'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
    
    // ================================================================
    // 15. PENDING APPOINTMENTS
    // ================================================================
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM appointments 
        WHERE doctor_id = ? 
        AND status = 'scheduled'
    ");
    $stmt->execute([$doctor_id]);
    $response['pendingAppointments'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
    
    $response['success'] = true;
    $response['timestamp'] = date('Y-m-d H:i:s');
    
} catch (Exception $e) {
    $response['success'] = false;
    $response['error'] = $e->getMessage();
}

// ================================================================
// RETURN JSON
// ================================================================
header('Content-Type: application/json');
echo json_encode($response);
?>