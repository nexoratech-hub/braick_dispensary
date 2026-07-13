<?php
// ================================================================
// FILE: frontend/pages/reception/get_reception_stats.php
// RETURNS JSON DATA FOR RECEPTION DASHBOARD AUTO-UPDATE
// BRAICK DISPENSARY
// ================================================================

session_start();

// ================================================================
// IF NO SESSION, USE RECEPTION.ROSE (ID: 6) AS DEFAULT
// ================================================================
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'reception') {
    $_SESSION['user_id'] = 6;
    $_SESSION['full_name'] = 'Rose Mwangi';
    $_SESSION['role'] = 'reception';
    $_SESSION['branch_id'] = 1;
    $_SESSION['branch_name'] = 'Dodoma';
}

$user_id = $_SESSION['user_id'];
$user_branch_id = $_SESSION['branch_id'] ?? 1;

// ================================================================
// INCLUDE DATABASE
// ================================================================
require_once 'C:/xampp/htdocs/dispensary_system/backend/config/database.php';
$db = Database::getInstance()->getConnection();

// ================================================================
// TODAY'S DATE
// ================================================================
$today = date('Y-m-d');

// ================================================================
// FETCH ALL STATISTICS
// ================================================================

// 1. Today's Patients - Pending
$stmt = $db->prepare("
    SELECT COUNT(DISTINCT patient_id) as count 
    FROM visits 
    WHERE branch_id = ? AND DATE(created_at) = ? AND status IN ('pending', 'assigned')
");
$stmt->execute([$user_branch_id, $today]);
$today_patients_pending = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// 2. Today's Patients - Completed
$stmt = $db->prepare("
    SELECT COUNT(DISTINCT patient_id) as count 
    FROM visits 
    WHERE branch_id = ? AND DATE(created_at) = ? AND status = 'completed'
");
$stmt->execute([$user_branch_id, $today]);
$today_patients_completed = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
$today_patients_total = $today_patients_pending + $today_patients_completed;

// 3. Today's Visits - Pending
$stmt = $db->prepare("
    SELECT COUNT(*) as count 
    FROM visits 
    WHERE branch_id = ? AND DATE(created_at) = ? AND status IN ('pending', 'assigned')
");
$stmt->execute([$user_branch_id, $today]);
$today_visits_pending = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// 4. Today's Visits - Completed
$stmt = $db->prepare("
    SELECT COUNT(*) as count 
    FROM visits 
    WHERE branch_id = ? AND DATE(created_at) = ? AND status = 'completed'
");
$stmt->execute([$user_branch_id, $today]);
$today_visits_completed = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
$today_visits_total = $today_visits_pending + $today_visits_completed;

// 5. Today's Appointments - Pending
$stmt = $db->prepare("
    SELECT COUNT(*) as count 
    FROM appointments 
    WHERE branch_id = ? AND DATE(appointment_date) = ? 
    AND status IN ('scheduled', 'pending', 'confirmed')
");
$stmt->execute([$user_branch_id, $today]);
$today_appointments_pending = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// 6. Today's Appointments - Completed
$stmt = $db->prepare("
    SELECT COUNT(*) as count 
    FROM appointments 
    WHERE branch_id = ? AND DATE(appointment_date) = ? AND status = 'completed'
");
$stmt->execute([$user_branch_id, $today]);
$today_appointments_completed = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
$today_appointments_total = $today_appointments_pending + $today_appointments_completed;

// 7. Total Appointments
$stmt = $db->prepare("SELECT COUNT(*) as count FROM appointments WHERE branch_id = ?");
$stmt->execute([$user_branch_id]);
$total_appointments = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// 8. Total Patients
$stmt = $db->prepare("SELECT COUNT(*) as count FROM patients WHERE branch_id = ?");
$stmt->execute([$user_branch_id]);
$total_patients = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// 9. Total Visits
$stmt = $db->prepare("SELECT COUNT(*) as count FROM visits WHERE branch_id = ?");
$stmt->execute([$user_branch_id]);
$total_visits = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// 10. Pending Appointments
$stmt = $db->prepare("
    SELECT COUNT(*) as count 
    FROM appointments 
    WHERE branch_id = ? AND status IN ('scheduled', 'pending')
");
$stmt->execute([$user_branch_id]);
$pending_appointments = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// 11. Online Doctors
$stmt = $db->prepare("
    SELECT COUNT(*) as count 
    FROM users 
    WHERE role = 'doctor' AND is_online = 1 AND status = 'active' AND branch_id = ?
");
$stmt->execute([$user_branch_id]);
$online_doctors = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// 12. Online Doctors List
$stmt = $db->prepare("
    SELECT id, full_name, specialty 
    FROM users 
    WHERE role = 'doctor' AND is_online = 1 AND status = 'active' AND branch_id = ?
    ORDER BY full_name
");
$stmt->execute([$user_branch_id]);
$online_doctors_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 13. Today's Appointments List
$stmt = $db->prepare("
    SELECT a.*, p.full_name as patient_name, p.patient_id, u.full_name as doctor_name 
    FROM appointments a
    JOIN patients p ON a.patient_id = p.id
    JOIN users u ON a.doctor_id = u.id
    WHERE a.branch_id = ? AND DATE(a.appointment_date) = ?
    ORDER BY a.appointment_date
    LIMIT 10
");
$stmt->execute([$user_branch_id, $today]);
$today_appointments_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 14. Recent Patients
$stmt = $db->prepare("
    SELECT * FROM patients 
    WHERE branch_id = ?
    ORDER BY created_at DESC 
    LIMIT 8
");
$stmt->execute([$user_branch_id]);
$recent_patients = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 15. Recent Activities
try {
    $stmt = $db->query("
        SELECT action, details, created_at 
        FROM activity_logs 
        ORDER BY created_at DESC 
        LIMIT 5
    ");
    $recent_activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $recent_activities = [];
}

// ================================================================
// CREATE DATA HASH FOR CHANGE DETECTION
// ================================================================
$data_array = [
    'today_patients_total' => $today_patients_total,
    'today_patients_pending' => $today_patients_pending,
    'today_patients_completed' => $today_patients_completed,
    'today_visits_total' => $today_visits_total,
    'today_visits_pending' => $today_visits_pending,
    'today_visits_completed' => $today_visits_completed,
    'today_appointments_total' => $today_appointments_total,
    'today_appointments_pending' => $today_appointments_pending,
    'today_appointments_completed' => $today_appointments_completed,
    'total_appointments' => $total_appointments,
    'total_patients' => $total_patients,
    'total_visits' => $total_visits,
    'pending_appointments' => $pending_appointments,
    'online_doctors' => $online_doctors,
    'appointments_count' => count($today_appointments_list)
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
            'total' => $today_patients_total,
            'pending' => $today_patients_pending,
            'completed' => $today_patients_completed
        ],
        'today_visits' => [
            'total' => $today_visits_total,
            'pending' => $today_visits_pending,
            'completed' => $today_visits_completed
        ],
        'today_appointments' => [
            'total' => $today_appointments_total,
            'pending' => $today_appointments_pending,
            'completed' => $today_appointments_completed,
            'list' => $today_appointments_list
        ],
        'total_appointments' => $total_appointments,
        'total_patients' => $total_patients,
        'total_visits' => $total_visits,
        'pending_appointments' => $pending_appointments,
        'online_doctors' => $online_doctors,
        'online_doctors_list' => $online_doctors_list,
        'recent_patients' => $recent_patients,
        'recent_activities' => $recent_activities,
        'timestamp' => date('Y-m-d H:i:s')
    ]
]);
?>