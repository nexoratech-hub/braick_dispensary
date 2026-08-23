<?php
// ================================================================
// FILE: frontend/pages/reception/get_reception_stats.php
// RETURNS JSON DATA FOR RECEPTION DASHBOARD AUTO-UPDATE
// USING NEW DATABASE: dispensary_db
// BRAICK DISPENSARY
// ================================================================

// ================================================================
// START SESSION
// ================================================================
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
// CHECK IF USER HAS ACCESS (Reception or Admin)
// ================================================================
$allowed_roles = ['reception', 'admin'];
if (!in_array($_SESSION['role'], $allowed_roles)) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => 'Access denied',
        'redirect' => '../' . $_SESSION['role'] . '/dashboard.php'
    ]);
    exit;
}

// ================================================================
// GET USER DATA FROM SESSION
// ================================================================
$user_id = $_SESSION['user_id'];
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_role = $_SESSION['role'] ?? 'reception';

// ================================================================
// INCLUDE DATABASE - NEW DATABASE
// ================================================================
require_once __DIR__ . '/../../../backend/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => 'Database connection failed: ' . $e->getMessage()
    ]);
    exit;
}

// ================================================================
// TODAY'S DATE
// ================================================================
$today = date('Y-m-d');

// ================================================================
// FETCH ALL STATISTICS - NEW DATABASE
// ================================================================

// ================================================================
// 1. Today's Patients - Pending (from visits table)
// ================================================================
$stmt = $db->prepare("
    SELECT COUNT(DISTINCT patient_id) as count 
    FROM visits 
    WHERE branch_id = ? AND DATE(created_at) = ? AND status IN ('pending', 'assigned', 'with_doctor')
");
$stmt->execute([$user_branch_id, $today]);
$today_patients_pending = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// ================================================================
// 2. Today's Patients - Completed (from visits table)
// ================================================================
$stmt = $db->prepare("
    SELECT COUNT(DISTINCT patient_id) as count 
    FROM visits 
    WHERE branch_id = ? AND DATE(created_at) = ? AND status = 'completed'
");
$stmt->execute([$user_branch_id, $today]);
$today_patients_completed = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// ================================================================
// 3. Today's Visits - Pending
// ================================================================
$stmt = $db->prepare("
    SELECT COUNT(*) as count 
    FROM visits 
    WHERE branch_id = ? AND DATE(created_at) = ? AND status IN ('pending', 'assigned', 'with_doctor', 'lab_test', 'lab_completed', 'prescribed')
");
$stmt->execute([$user_branch_id, $today]);
$today_visits_pending = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// ================================================================
// 4. Today's Visits - Completed
// ================================================================
$stmt = $db->prepare("
    SELECT COUNT(*) as count 
    FROM visits 
    WHERE branch_id = ? AND DATE(created_at) = ? AND status = 'completed'
");
$stmt->execute([$user_branch_id, $today]);
$today_visits_completed = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// ================================================================
// 5. Today's Appointments - Pending (scheduled, confirmed)
// ================================================================
$stmt = $db->prepare("
    SELECT COUNT(*) as count 
    FROM appointments 
    WHERE branch_id = ? AND DATE(appointment_date) = ? 
    AND status IN ('scheduled', 'confirmed', 'pending')
");
$stmt->execute([$user_branch_id, $today]);
$today_appointments_pending = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// ================================================================
// 6. Today's Appointments - Completed
// ================================================================
$stmt = $db->prepare("
    SELECT COUNT(*) as count 
    FROM appointments 
    WHERE branch_id = ? AND DATE(appointment_date) = ? AND status = 'completed'
");
$stmt->execute([$user_branch_id, $today]);
$today_appointments_completed = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// ================================================================
// 7. Today's Appointments - Cancelled
// ================================================================
$stmt = $db->prepare("
    SELECT COUNT(*) as count 
    FROM appointments 
    WHERE branch_id = ? AND DATE(appointment_date) = ? AND status = 'cancelled'
");
$stmt->execute([$user_branch_id, $today]);
$today_appointments_cancelled = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// ================================================================
// 8. Total Appointments
// ================================================================
$stmt = $db->prepare("SELECT COUNT(*) as count FROM appointments WHERE branch_id = ?");
$stmt->execute([$user_branch_id]);
$total_appointments = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// ================================================================
// 9. Total Patients
// ================================================================
$stmt = $db->prepare("SELECT COUNT(*) as count FROM patients WHERE branch_id = ?");
$stmt->execute([$user_branch_id]);
$total_patients = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// ================================================================
// 10. Total Visits
// ================================================================
$stmt = $db->prepare("SELECT COUNT(*) as count FROM visits WHERE branch_id = ?");
$stmt->execute([$user_branch_id]);
$total_visits = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// ================================================================
// 11. Pending Appointments (all time)
// ================================================================
$stmt = $db->prepare("
    SELECT COUNT(*) as count 
    FROM appointments 
    WHERE branch_id = ? AND status IN ('scheduled', 'confirmed', 'pending')
");
$stmt->execute([$user_branch_id]);
$pending_appointments = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// ================================================================
// 12. Online Doctors
// ================================================================
$stmt = $db->prepare("
    SELECT COUNT(*) as count 
    FROM users 
    WHERE role = 'doctor' AND is_online = 1 AND status = 'active' AND branch_id = ?
");
$stmt->execute([$user_branch_id]);
$online_doctors = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// ================================================================
// 13. Online Doctors List
// ================================================================
$stmt = $db->prepare("
    SELECT id, full_name, specialty 
    FROM users 
    WHERE role = 'doctor' AND is_online = 1 AND status = 'active' AND branch_id = ?
    ORDER BY full_name
");
$stmt->execute([$user_branch_id]);
$online_doctors_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// 14. Today's Appointments List
// ================================================================
$stmt = $db->prepare("
    SELECT 
        a.*,
        p.full_name as patient_name,
        p.patient_id,
        u.full_name as doctor_name,
        u.specialty as doctor_specialty
    FROM appointments a
    JOIN patients p ON a.patient_id = p.id
    LEFT JOIN users u ON a.doctor_id = u.id
    WHERE a.branch_id = ? AND DATE(a.appointment_date) = ?
    ORDER BY a.appointment_date ASC
    LIMIT 15
");
$stmt->execute([$user_branch_id, $today]);
$today_appointments_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// 15. Recent Patients
// ================================================================
$stmt = $db->prepare("
    SELECT id, full_name, patient_id, phone, created_at 
    FROM patients 
    WHERE branch_id = ?
    ORDER BY created_at DESC 
    LIMIT 8
");
$stmt->execute([$user_branch_id]);
$recent_patients = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// 16. Today's Visits List
// ================================================================
$stmt = $db->prepare("
    SELECT 
        v.*,
        p.full_name as patient_name,
        p.patient_id,
        u.full_name as doctor_name
    FROM visits v
    JOIN patients p ON v.patient_id = p.id
    LEFT JOIN users u ON v.doctor_id = u.id
    WHERE v.branch_id = ? AND DATE(v.created_at) = ?
    ORDER BY v.created_at DESC
    LIMIT 10
");
$stmt->execute([$user_branch_id, $today]);
$today_visits_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// 17. Recent Activities - NEW DATABASE (activity_logs)
// ================================================================
$recent_activities = [];
try {
    $stmt = $db->prepare("
        SELECT action, details, created_at 
        FROM activity_logs 
        WHERE branch_id = ? OR branch_id IS NULL
        ORDER BY created_at DESC 
        LIMIT 5
    ");
    $stmt->execute([$user_branch_id]);
    $recent_activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $recent_activities = [];
}

// ================================================================
// 18. Unread Notifications
// ================================================================
$unread_notifications = 0;
try {
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$user_id]);
    $unread_notifications = $stmt->fetch()['total'] ?? 0;
} catch (Exception $e) {
    $unread_notifications = 0;
}

// ================================================================
// 19. Pending Prescriptions (for pharmacy reference)
// ================================================================
$pending_prescriptions = 0;
try {
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM prescriptions WHERE branch_id = ? AND status = 'pending'");
    $stmt->execute([$user_branch_id]);
    $pending_prescriptions = $stmt->fetch()['count'] ?? 0;
} catch (Exception $e) {
    $pending_prescriptions = 0;
}

// ================================================================
// CREATE DATA HASH FOR CHANGE DETECTION
// ================================================================
$data_array = [
    'today_patients_total' => $today_patients_pending + $today_patients_completed,
    'today_patients_pending' => $today_patients_pending,
    'today_patients_completed' => $today_patients_completed,
    'today_visits_total' => $today_visits_pending + $today_visits_completed,
    'today_visits_pending' => $today_visits_pending,
    'today_visits_completed' => $today_visits_completed,
    'today_appointments_total' => $today_appointments_pending + $today_appointments_completed,
    'today_appointments_pending' => $today_appointments_pending,
    'today_appointments_completed' => $today_appointments_completed,
    'total_appointments' => $total_appointments,
    'total_patients' => $total_patients,
    'total_visits' => $total_visits,
    'pending_appointments' => $pending_appointments,
    'online_doctors' => $online_doctors,
    'appointments_count' => count($today_appointments_list),
    'unread_notifications' => $unread_notifications,
    'pending_prescriptions' => $pending_prescriptions
];

$data_hash = md5(json_encode($data_array));

// ================================================================
// RETURN JSON
// ================================================================
header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'hash' => $data_hash,
    'data' => [
        'today_patients' => [
            'total' => $today_patients_pending + $today_patients_completed,
            'pending' => $today_patients_pending,
            'completed' => $today_patients_completed
        ],
        'today_visits' => [
            'total' => $today_visits_pending + $today_visits_completed,
            'pending' => $today_visits_pending,
            'completed' => $today_visits_completed
        ],
        'today_appointments' => [
            'total' => $today_appointments_pending + $today_appointments_completed,
            'pending' => $today_appointments_pending,
            'completed' => $today_appointments_completed,
            'cancelled' => $today_appointments_cancelled,
            'list' => $today_appointments_list
        ],
        'total_appointments' => $total_appointments,
        'total_patients' => $total_patients,
        'total_visits' => $total_visits,
        'pending_appointments' => $pending_appointments,
        'online_doctors' => $online_doctors,
        'online_doctors_list' => $online_doctors_list,
        'recent_patients' => $recent_patients,
        'today_visits_list' => $today_visits_list,
        'recent_activities' => $recent_activities,
        'unread_notifications' => $unread_notifications,
        'pending_prescriptions' => $pending_prescriptions,
        'timestamp' => date('Y-m-d H:i:s')
    ]
]);
?>