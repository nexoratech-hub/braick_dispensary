<?php
// ================================================================
// FILE: frontend/pages/doctor/get_doctor_stats.php
// DOCTOR STATS API - RETURNS JSON FOR AUTO-UPDATE
// BRAICK DISPENSARY
// ================================================================

session_start();

// ================================================================
// IF NO SESSION, USE DR. JOHN MUSHI (ID: 5) AS DEFAULT
// ================================================================
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    $_SESSION['user_id'] = 5;
    $_SESSION['doctor_id'] = 5;
    $_SESSION['full_name'] = 'Dr. John Mushi';
    $_SESSION['role'] = 'doctor';
    $_SESSION['branch_id'] = 1;
}

$doctor_id = $_SESSION['user_id'] ?? $_SESSION['doctor_id'] ?? 5;

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
// FETCH ALL STATISTICS FOR DOCTOR
// ================================================================

// 1. Today's Patients - Pending
$stmt = $db->prepare("
    SELECT COUNT(DISTINCT CASE WHEN status IN ('pending', 'assigned') THEN patient_id END) as pending,
           COUNT(DISTINCT CASE WHEN status = 'completed' THEN patient_id END) as completed
    FROM visits 
    WHERE doctor_id = ? AND DATE(created_at) = ?
");
$stmt->execute([$doctor_id, $today]);
$today_patients = $stmt->fetch(PDO::FETCH_ASSOC);
$today_patients_pending = $today_patients['pending'] ?? 0;
$today_patients_completed = $today_patients['completed'] ?? 0;
$today_patients_total = $today_patients_pending + $today_patients_completed;

// 2. Today's Visits
$stmt = $db->prepare("
    SELECT 
        COUNT(CASE WHEN status IN ('pending', 'assigned') THEN 1 END) as pending,
        COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed
    FROM visits 
    WHERE doctor_id = ? AND DATE(created_at) = ?
");
$stmt->execute([$doctor_id, $today]);
$today_visits = $stmt->fetch(PDO::FETCH_ASSOC);
$today_visits_pending = $today_visits['pending'] ?? 0;
$today_visits_completed = $today_visits['completed'] ?? 0;
$today_visits_total = $today_visits_pending + $today_visits_completed;

// 3. Total Patients
$stmt = $db->prepare("SELECT COUNT(DISTINCT patient_id) as total FROM visits WHERE doctor_id = ?");
$stmt->execute([$doctor_id]);
$total_patients = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// 4. Total Visits
$stmt = $db->prepare("SELECT COUNT(*) as total FROM visits WHERE doctor_id = ?");
$stmt->execute([$doctor_id]);
$total_visits = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// 5. Today's Appointments
$stmt = $db->prepare("
    SELECT 
        COUNT(CASE WHEN status IN ('scheduled', 'pending', 'confirmed') THEN 1 END) as pending,
        COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed
    FROM appointments 
    WHERE doctor_id = ? AND DATE(appointment_date) = ?
");
$stmt->execute([$doctor_id, $today]);
$today_appointments = $stmt->fetch(PDO::FETCH_ASSOC);
$today_appointments_pending = $today_appointments['pending'] ?? 0;
$today_appointments_completed = $today_appointments['completed'] ?? 0;
$today_appointments_total = $today_appointments_pending + $today_appointments_completed;

// 6. Total Appointments
$stmt = $db->prepare("SELECT COUNT(*) as total FROM appointments WHERE doctor_id = ?");
$stmt->execute([$doctor_id]);
$total_appointments = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// 7. Total Prescriptions
$stmt = $db->prepare("SELECT COUNT(*) as total FROM prescriptions WHERE doctor_id = ?");
$stmt->execute([$doctor_id]);
$total_prescriptions = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// 8. Lab Tests
$stmt = $db->prepare("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status IN ('pending', 'in_progress') THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed
    FROM lab_tests 
    WHERE doctor_id = ?
");
$stmt->execute([$doctor_id]);
$lab_tests = $stmt->fetch(PDO::FETCH_ASSOC);
$lab_tests_total = $lab_tests['total'] ?? 0;
$lab_tests_pending = $lab_tests['pending'] ?? 0;
$lab_tests_completed = $lab_tests['completed'] ?? 0;

// 9. Pending Visits (Queue)
$stmt = $db->prepare("
    SELECT COUNT(*) as count 
    FROM visits 
    WHERE doctor_id = ? AND status IN ('pending', 'assigned')
");
$stmt->execute([$doctor_id]);
$pending_visits = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// 10. Today's Appointments List
$stmt = $db->prepare("
    SELECT a.*, p.full_name as patient_name, p.patient_id, p.phone 
    FROM appointments a
    JOIN patients p ON a.patient_id = p.id
    WHERE a.doctor_id = ? AND DATE(a.appointment_date) = ?
    AND a.status NOT IN ('cancelled')
    ORDER BY a.appointment_date ASC
    LIMIT 10
");
$stmt->execute([$doctor_id, $today]);
$today_appointments_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 11. Pending Patients Queue
$stmt = $db->prepare("
    SELECT v.id, v.patient_id, v.status, v.created_at,
           p.full_name as patient_name, p.patient_id as patient_number, p.phone,
           TIMESTAMPDIFF(MINUTE, v.created_at, NOW()) as waiting_time
    FROM visits v
    JOIN patients p ON v.patient_id = p.id
    WHERE v.doctor_id = ? AND v.status IN ('pending', 'assigned')
    ORDER BY v.created_at ASC
    LIMIT 10
");
$stmt->execute([$doctor_id]);
$pending_patients = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
    'total_patients' => $total_patients,
    'total_visits' => $total_visits,
    'today_appointments_total' => $today_appointments_total,
    'today_appointments_pending' => $today_appointments_pending,
    'today_appointments_completed' => $today_appointments_completed,
    'total_appointments' => $total_appointments,
    'total_prescriptions' => $total_prescriptions,
    'lab_tests_total' => $lab_tests_total,
    'lab_tests_pending' => $lab_tests_pending,
    'lab_tests_completed' => $lab_tests_completed,
    'pending_visits' => $pending_visits,
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
        'total_patients' => $total_patients,
        'total_visits' => $total_visits,
        'today_appointments' => [
            'total' => $today_appointments_total,
            'pending' => $today_appointments_pending,
            'completed' => $today_appointments_completed,
            'list' => $today_appointments_list
        ],
        'total_appointments' => $total_appointments,
        'total_prescriptions' => $total_prescriptions,
        'lab_tests' => [
            'total' => $lab_tests_total,
            'pending' => $lab_tests_pending,
            'completed' => $lab_tests_completed
        ],
        'pending_visits' => $pending_visits,
        'pending_patients' => $pending_patients,
        'timestamp' => date('Y-m-d H:i:s')
    ]
]);
?>