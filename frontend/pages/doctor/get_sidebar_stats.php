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
    'todayVisits' => 0,
    'pendingAppointments' => 0,
    'is_admin' => $is_admin,
    'doctor_id' => $doctor_id,
    'branch_id' => $branch_id,
    'timestamp' => date('Y-m-d H:i:s')
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
        SELECT full_name, is_online, status, phone, email, specialty 
        FROM users 
        WHERE id = ? AND (role = 'doctor' OR ? = 1)
    ");
    $stmt->execute([$doctor_id, $is_admin ? 1 : 0]);
    $doctor = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($doctor) {
        $response['doctorName'] = $doctor['full_name'] ?? '';
        $response['doctorStatus'] = ($doctor['is_online'] ?? 0) ? 'online' : 'offline';
        $response['doctorPhone'] = $doctor['phone'] ?? '';
        $response['doctorEmail'] = $doctor['email'] ?? '';
        $response['doctorSpecialty'] = $doctor['specialty'] ?? 'General Medicine';
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
    // 3. PENDING LAB TESTS (Lab tests with pending/in_progress status)
    // ================================================================
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM lab_tests 
        WHERE doctor_id = ? AND status IN ('pending', 'in_progress')
    ");
    $stmt->execute([$doctor_id]);
    $response['labCount'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
    
    // ================================================================
    // 4. PENDING CONSULTATIONS
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
    // 5. COMPLETED CONSULTATIONS
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
    // 6. CANCELLED CONSULTATIONS
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
    // 7. TOTAL CONSULTATIONS
    // ================================================================
    $response['totalConsultations'] = $response['pendingConsultations'] + 
                                       $response['completedConsultations'] + 
                                       $response['cancelledConsultations'];
    
    // ================================================================
    // 8. PENDING PRESCRIPTIONS
    // ================================================================
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM prescriptions 
        WHERE doctor_id = ? AND status = 'pending'
    ");
    $stmt->execute([$doctor_id]);
    $response['pendingPrescriptions'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
    
    // ================================================================
    // 9. TOTAL PATIENTS (All patients assigned to this doctor)
    // ================================================================
    $stmt = $db->prepare("
        SELECT COUNT(DISTINCT p.id) as count 
        FROM patients p
        WHERE p.assigned_doctor_id = ?
    ");
    $stmt->execute([$doctor_id]);
    $response['totalPatients'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
    
    // ================================================================
    // 10. TODAY'S APPOINTMENTS
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
    // 11. PENDING APPOINTMENTS
    // ================================================================
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM appointments 
        WHERE doctor_id = ? 
        AND status = 'scheduled'
    ");
    $stmt->execute([$doctor_id]);
    $response['pendingAppointments'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
    
    // ================================================================
    // 12. TODAY'S VISITS
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
    // 13. PENDING REFERRALS (Check if referrals table exists)
    // ================================================================
    try {
        $stmt = $db->prepare("
            SELECT COUNT(*) as count 
            FROM referrals 
            WHERE from_doctor_id = ? AND status = 'pending'
        ");
        $stmt->execute([$doctor_id]);
        $response['referralCount'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
    } catch (Exception $e) {
        $response['referralCount'] = 0;
    }
    
    // ================================================================
    // 14. SERVICES COUNTS (Branch specific)
    // ================================================================
    // Procedures catalog count
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM procedures_catalog 
        WHERE (branch_id IS NULL OR branch_id = ?) AND is_active = 1
    ");
    $stmt->execute([$branch_id]);
    $response['proceduresCount'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
    
    // Lab Tests catalog count
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM lab_tests_catalog 
        WHERE (branch_id IS NULL OR branch_id = ?) AND is_active = 1
    ");
    $stmt->execute([$branch_id]);
    $response['labTestsCount'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
    
    // ================================================================
    // 15. EXPIRING MEDICINES (Medications expiring within 30 days)
    // ================================================================
    try {
        $stmt = $db->prepare("
            SELECT COUNT(*) as count 
            FROM medications_inventory 
            WHERE branch_id = ? 
            AND status = 'active' 
            AND quantity > 0
            AND expiry_date IS NOT NULL
            AND expiry_date < DATE_ADD(CURDATE(), INTERVAL 30 DAY)
            AND expiry_date >= CURDATE()
        ");
        $stmt->execute([$branch_id]);
        $response['expiringMedicines'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
    } catch (Exception $e) {
        $response['expiringMedicines'] = 0;
    }
    
    // ================================================================
    // 16. ACTIVE LAB TESTS (Detailed breakdown)
    // ================================================================
    // Pending lab tests
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM lab_tests 
        WHERE doctor_id = ? AND status = 'pending'
    ");
    $stmt->execute([$doctor_id]);
    $response['pendingLabTests'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
    
    // In progress lab tests
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM lab_tests 
        WHERE doctor_id = ? AND status = 'in_progress'
    ");
    $stmt->execute([$doctor_id]);
    $response['inProgressLabTests'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
    
    // Completed lab tests
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM lab_tests 
        WHERE doctor_id = ? AND status = 'completed'
    ");
    $stmt->execute([$doctor_id]);
    $response['completedLabTests'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
    
    // ================================================================
    // 17. BILL SUMMARY
    // ================================================================
    // Total bills for this doctor's patients
    $stmt = $db->prepare("
        SELECT 
            COUNT(DISTINCT b.id) as total_bills,
            SUM(b.total_amount) as total_amount,
            SUM(b.paid_amount) as total_paid,
            SUM(b.balance) as total_balance
        FROM bills b
        JOIN visits v ON b.visit_id = v.id
        WHERE v.doctor_id = ?
    ");
    $stmt->execute([$doctor_id]);
    $bill_stats = $stmt->fetch(PDO::FETCH_ASSOC);
    $response['totalBills'] = (int)($bill_stats['total_bills'] ?? 0);
    $response['totalBillAmount'] = (float)($bill_stats['total_amount'] ?? 0);
    $response['totalPaidAmount'] = (float)($bill_stats['total_paid'] ?? 0);
    $response['totalBalanceAmount'] = (float)($bill_stats['total_balance'] ?? 0);
    
    // ================================================================
    // 18. PRESCRIPTION SUMMARY
    // ================================================================
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = 'dispensed' THEN 1 ELSE 0 END) as dispensed,
            SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed
        FROM prescriptions 
        WHERE doctor_id = ?
    ");
    $stmt->execute([$doctor_id]);
    $pres_stats = $stmt->fetch(PDO::FETCH_ASSOC);
    $response['totalPrescriptions'] = (int)($pres_stats['total'] ?? 0);
    $response['pendingPrescriptions'] = (int)($pres_stats['pending'] ?? 0);
    $response['dispensedPrescriptions'] = (int)($pres_stats['dispensed'] ?? 0);
    $response['confirmedPrescriptions'] = (int)($pres_stats['confirmed'] ?? 0);
    
    $response['success'] = true;
    $response['timestamp'] = date('Y-m-d H:i:s');
    
} catch (Exception $e) {
    $response['success'] = false;
    $response['error'] = $e->getMessage();
    error_log("Sidebar stats error: " . $e->getMessage());
}

// ================================================================
// RETURN JSON
// ================================================================
header('Content-Type: application/json');
echo json_encode($response);
exit;
?>