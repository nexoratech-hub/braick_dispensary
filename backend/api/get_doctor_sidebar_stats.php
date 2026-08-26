<?php
// ================================================================
// FILE: backend/api/get_doctor_sidebar_stats.php
// DOCTOR SIDEBAR STATS API - REAL-TIME UPDATES
// FIXED: Referrals query includes 'referred' status
// BRAICK DISPENSARY
// ================================================================

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET');
header('Access-Control-Allow-Headers: Content-Type');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';

function sendResponse($success, $data = null, $message = null, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode([
        'success' => $success,
        'data' => $data,
        'message' => $message,
        'timestamp' => date('Y-m-d H:i:s'),
        'timestamp_iso' => date('c')
    ]);
    exit;
}

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    sendResponse(false, null, 'Unauthorized - Please login', 401);
}

if ($_SESSION['role'] !== 'doctor' && $_SESSION['role'] !== 'admin') {
    sendResponse(false, null, 'Forbidden - Doctor access required', 403);
}

$doctor_id = isset($_POST['doctor_id']) ? (int)$_POST['doctor_id'] : 0;
$branch_id = isset($_POST['branch_id']) ? (int)$_POST['branch_id'] : 0;
$client_hash = isset($_POST['hash']) ? $_POST['hash'] : '';
$force_update = isset($_POST['force_update']) ? (bool)$_POST['force_update'] : false;

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $doctor_id = isset($_GET['doctor_id']) ? (int)$_GET['doctor_id'] : 0;
    $branch_id = isset($_GET['branch_id']) ? (int)$_GET['branch_id'] : 0;
    $client_hash = isset($_GET['hash']) ? $_GET['hash'] : '';
    $force_update = isset($_GET['force_update']) ? (bool)$_GET['force_update'] : false;
}

if ($doctor_id <= 0) {
    $doctor_id = (int)$_SESSION['user_id'];
}

if ($branch_id <= 0) {
    $branch_id = (int)$_SESSION['branch_id'] ?? 1;
}

if ($doctor_id !== (int)$_SESSION['user_id'] && $_SESSION['role'] !== 'admin') {
    sendResponse(false, null, 'Unauthorized - Invalid doctor ID', 403);
}

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    sendResponse(false, null, 'Database connection error: ' . $e->getMessage(), 500);
}

function getDoctorStats($db, $doctor_id, $branch_id) {
    $stats = [
        'patientCount' => 0,
        'patientCountToday' => 0,
        'patientCountWeek' => 0,
        'patientCountMonth' => 0,
        'pendingConsultations' => 0,
        'completedConsultations' => 0,
        'cancelledConsultations' => 0,
        'totalConsultations' => 0,
        'consultationsToday' => 0,
        'consultationsWeek' => 0,
        'labCount' => 0,
        'labCompleted' => 0,
        'labPending' => 0,
        'labInProgress' => 0,
        'pendingPrescriptions' => 0,
        'dispensedPrescriptions' => 0,
        'totalPrescriptions' => 0,
        'appointmentCount' => 0,
        'appointmentsToday' => 0,
        'appointmentsWeek' => 0,
        // ================================================================
        // REFERRAL STATS - FIXED: includes 'referred' status
        // ================================================================
        'referralCount' => 0,
        'referralsPending' => 0,
        'referralsReferred' => 0,
        'referralsProcessed' => 0,
        'referralsCompleted' => 0,
        'proceduresCount' => 0,
        'labTestsCount' => 0,
        'totalServices' => 0,
        'expiringMedicines' => 0,
        'lowStockMedicines' => 0,
        'expiringEquipment' => 0,
        'doctorName' => '',
        'doctorStatus' => 'offline',
        'doctorSpecialty' => '',
        'doctorBranch' => '',
        'lastActivity' => null,
        'todayDate' => date('Y-m-d'),
        'currentTime' => date('H:i:s'),
        '_hash' => ''
    ];
    
    try {
        // 1. DOCTOR INFO
        $stmt = $db->prepare("
            SELECT 
                u.full_name, 
                u.is_online, 
                u.specialty,
                u.last_online,
                b.name as branch_name
            FROM users u
            LEFT JOIN branches b ON u.branch_id = b.id
            WHERE u.id = ? AND u.status = 'active'
        ");
        $stmt->execute([$doctor_id]);
        $doctor = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($doctor) {
            $stats['doctorName'] = $doctor['full_name'] ?? '';
            $stats['doctorStatus'] = ($doctor['is_online'] ?? 0) ? 'online' : 'offline';
            $stats['doctorSpecialty'] = $doctor['specialty'] ?? 'General Medicine';
            $stats['doctorBranch'] = $doctor['branch_name'] ?? '';
            $stats['lastActivity'] = $doctor['last_online'] ?? null;
        }
        
        // 2. PATIENT STATS
        $stmt = $db->prepare("SELECT COUNT(DISTINCT patient_id) as count FROM visits WHERE doctor_id = ?");
        $stmt->execute([$doctor_id]);
        $stats['patientCount'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
        
        $stmt = $db->prepare("SELECT COUNT(DISTINCT patient_id) as count FROM visits WHERE doctor_id = ? AND DATE(visit_date) = CURDATE()");
        $stmt->execute([$doctor_id]);
        $stats['patientCountToday'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
        
        $stmt = $db->prepare("SELECT COUNT(DISTINCT patient_id) as count FROM visits WHERE doctor_id = ? AND YEARWEEK(visit_date) = YEARWEEK(CURDATE())");
        $stmt->execute([$doctor_id]);
        $stats['patientCountWeek'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
        
        $stmt = $db->prepare("SELECT COUNT(DISTINCT patient_id) as count FROM visits WHERE doctor_id = ? AND MONTH(visit_date) = MONTH(CURDATE()) AND YEAR(visit_date) = YEAR(CURDATE())");
        $stmt->execute([$doctor_id]);
        $stats['patientCountMonth'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
        
        // 3. CONSULTATION STATS
        $stmt = $db->prepare("
            SELECT COUNT(*) as count 
            FROM visits 
            WHERE doctor_id = ? 
            AND status IN ('pending', 'assigned', 'with_doctor', 'lab_test', 'prescribed')
            AND is_completed = 0
        ");
        $stmt->execute([$doctor_id]);
        $stats['pendingConsultations'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
        
        $stmt = $db->prepare("
            SELECT COUNT(*) as count 
            FROM visits 
            WHERE doctor_id = ? 
            AND status = 'completed'
            AND is_completed = 1
        ");
        $stmt->execute([$doctor_id]);
        $stats['completedConsultations'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
        
        $stmt = $db->prepare("
            SELECT COUNT(*) as count 
            FROM visits 
            WHERE doctor_id = ? 
            AND status = 'cancelled'
        ");
        $stmt->execute([$doctor_id]);
        $stats['cancelledConsultations'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
        
        $stats['totalConsultations'] = $stats['pendingConsultations'] + $stats['completedConsultations'] + $stats['cancelledConsultations'];
        
        $stmt = $db->prepare("
            SELECT COUNT(*) as count 
            FROM visits 
            WHERE doctor_id = ? 
            AND DATE(visit_date) = CURDATE()
            AND status != 'cancelled'
        ");
        $stmt->execute([$doctor_id]);
        $stats['consultationsToday'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
        
        $stmt = $db->prepare("
            SELECT COUNT(*) as count 
            FROM visits 
            WHERE doctor_id = ? 
            AND YEARWEEK(visit_date) = YEARWEEK(CURDATE())
            AND status != 'cancelled'
        ");
        $stmt->execute([$doctor_id]);
        $stats['consultationsWeek'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
        
        // 4. LAB STATS
        try {
            $stmt = $db->prepare("
                SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN status IN ('pending', 'in_progress') THEN 1 ELSE 0 END) as active,
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                    SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
                    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed
                FROM lab_tests 
                WHERE doctor_id = ?
            ");
            $stmt->execute([$doctor_id]);
            $lab = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $stats['labCount'] = (int)($lab['active'] ?? 0);
            $stats['labPending'] = (int)($lab['pending'] ?? 0);
            $stats['labInProgress'] = (int)($lab['in_progress'] ?? 0);
            $stats['labCompleted'] = (int)($lab['completed'] ?? 0);
        } catch (Exception $e) {
            // Table might not exist
        }
        
        // 5. PRESCRIPTION STATS
        try {
            $stmt = $db->prepare("
                SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                    SUM(CASE WHEN status = 'dispensed' THEN 1 ELSE 0 END) as dispensed
                FROM prescriptions 
                WHERE doctor_id = ?
            ");
            $stmt->execute([$doctor_id]);
            $presc = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $stats['pendingPrescriptions'] = (int)($presc['pending'] ?? 0);
            $stats['dispensedPrescriptions'] = (int)($presc['dispensed'] ?? 0);
            $stats['totalPrescriptions'] = (int)($presc['total'] ?? 0);
        } catch (Exception $e) {
            // Table might not exist
        }
        
        // 6. APPOINTMENT STATS
        try {
            $stmt = $db->prepare("
                SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN DATE(appointment_date) = CURDATE() THEN 1 ELSE 0 END) as today,
                    SUM(CASE WHEN YEARWEEK(appointment_date) = YEARWEEK(CURDATE()) THEN 1 ELSE 0 END) as week
                FROM appointments 
                WHERE doctor_id = ? 
                AND status IN ('scheduled', 'confirmed')
            ");
            $stmt->execute([$doctor_id]);
            $app = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $stats['appointmentCount'] = (int)($app['total'] ?? 0);
            $stats['appointmentsToday'] = (int)($app['today'] ?? 0);
            $stats['appointmentsWeek'] = (int)($app['week'] ?? 0);
        } catch (Exception $e) {
            // Table might not exist
        }
        
        // ================================================================
        // 7. REFERRAL STATS - FIXED: includes 'referred' status
        // ================================================================
        try {
            $stmt = $db->prepare("
                SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                    SUM(CASE WHEN status = 'referred' THEN 1 ELSE 0 END) as referred,
                    SUM(CASE WHEN status IN ('accepted', 'rejected') THEN 1 ELSE 0 END) as processed,
                    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed
                FROM referrals 
                WHERE from_doctor_id = ?
            ");
            $stmt->execute([$doctor_id]);
            $ref = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Show referrals with status 'referred' AND 'pending'
            $stats['referralCount'] = (int)($ref['referred'] ?? 0) + (int)($ref['pending'] ?? 0);
            $stats['referralsPending'] = (int)($ref['pending'] ?? 0);
            $stats['referralsReferred'] = (int)($ref['referred'] ?? 0);
            $stats['referralsProcessed'] = (int)($ref['processed'] ?? 0);
            $stats['referralsCompleted'] = (int)($ref['completed'] ?? 0);
        } catch (Exception $e) {
            $stats['referralCount'] = 0;
            $stats['referralsPending'] = 0;
            $stats['referralsReferred'] = 0;
            $stats['referralsProcessed'] = 0;
            $stats['referralsCompleted'] = 0;
        }
        
        // 8. SERVICES STATS
        try {
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM procedures_catalog WHERE (branch_id IS NULL OR branch_id = ?) AND is_active = 1");
            $stmt->execute([$branch_id]);
            $stats['proceduresCount'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
        } catch (Exception $e) {
            $stats['proceduresCount'] = 0;
        }
        
        try {
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM lab_tests_catalog WHERE (branch_id IS NULL OR branch_id = ?) AND is_active = 1");
            $stmt->execute([$branch_id]);
            $stats['labTestsCount'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
        } catch (Exception $e) {
            $stats['labTestsCount'] = 0;
        }
        
        $stats['totalServices'] = $stats['proceduresCount'] + $stats['labTestsCount'];
        
        // 9. INVENTORY ALERTS
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
            $stats['expiringMedicines'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
        } catch (Exception $e) {
            $stats['expiringMedicines'] = 0;
        }
        
        try {
            $stmt = $db->prepare("
                SELECT COUNT(*) as count 
                FROM medications_inventory 
                WHERE branch_id = ? 
                AND status = 'active' 
                AND quantity <= reorder_level
                AND quantity > 0
            ");
            $stmt->execute([$branch_id]);
            $stats['lowStockMedicines'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
        } catch (Exception $e) {
            $stats['lowStockMedicines'] = 0;
        }
        
        try {
            $stmt = $db->prepare("
                SELECT COUNT(*) as count 
                FROM medical_equipment 
                WHERE branch_id = ? 
                AND status = 'active' 
                AND expiry_date IS NOT NULL
                AND expiry_date < DATE_ADD(CURDATE(), INTERVAL 30 DAY)
                AND expiry_date >= CURDATE()
            ");
            $stmt->execute([$branch_id]);
            $stats['expiringEquipment'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
        } catch (Exception $e) {
            $stats['expiringEquipment'] = 0;
        }
        
        // 10. GENERATE HASH FOR CHANGE DETECTION
        $hash_data = [
            'patientCount' => $stats['patientCount'],
            'pendingConsultations' => $stats['pendingConsultations'],
            'labCount' => $stats['labCount'],
            'appointmentCount' => $stats['appointmentCount'],
            'referralCount' => $stats['referralCount'],
            'pendingPrescriptions' => $stats['pendingPrescriptions'],
            'proceduresCount' => $stats['proceduresCount'],
            'labTestsCount' => $stats['labTestsCount'],
            'expiringMedicines' => $stats['expiringMedicines'],
            'doctorStatus' => $stats['doctorStatus'],
            'consultationsToday' => $stats['consultationsToday'],
            'patientsToday' => $stats['patientCountToday'],
            'labPending' => $stats['labPending'],
            'labInProgress' => $stats['labInProgress'],
            'labCompleted' => $stats['labCompleted']
        ];
        
        $stats['_hash'] = md5(json_encode($hash_data));
        
    } catch (Exception $e) {
        error_log("Doctor stats error: " . $e->getMessage());
    }
    
    return $stats;
}

try {
    $stats = getDoctorStats($db, $doctor_id, $branch_id);
    
    $has_changed = false;
    $data_to_send = null;
    
    if ($force_update) {
        $has_changed = true;
        $data_to_send = $stats;
    } else if (!empty($client_hash) && $client_hash !== $stats['_hash']) {
        $has_changed = true;
        $data_to_send = $stats;
    } else if (empty($client_hash)) {
        $has_changed = true;
        $data_to_send = $stats;
    }
    
    $response = [
        'success' => true,
        'has_changed' => $has_changed,
        'hash' => $stats['_hash'],
        'data' => $data_to_send,
        'timestamp' => date('Y-m-d H:i:s'),
        'timestamp_iso' => date('c')
    ];
    
    if ($has_changed && $data_to_send) {
        $response['summary'] = [
            'patients' => $data_to_send['patientCount'],
            'pending' => $data_to_send['pendingConsultations'],
            'lab' => $data_to_send['labCount'],
            'appointments' => $data_to_send['appointmentCount'],
            'prescriptions' => $data_to_send['pendingPrescriptions'],
            'referrals' => $data_to_send['referralCount'],
            'services' => $data_to_send['totalServices']
        ];
    }
    
    try {
        $stmt = $db->prepare("UPDATE users SET last_online = NOW(), is_online = 1 WHERE id = ?");
        $stmt->execute([$doctor_id]);
    } catch (Exception $e) {
        // Ignore update errors
    }
    
    echo json_encode($response);
    
} catch (Exception $e) {
    sendResponse(false, null, 'Error fetching stats: ' . $e->getMessage(), 500);
}
?>