<?php
// ================================================================
// FILE: frontend/pages/reception/get_sidebar_stats.php
// RETURNS JSON DATA FOR SIDEBAR AUTO-UPDATE
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
// FETCH SIDEBAR STATISTICS
// ================================================================

// 1. Total Patients (for this branch)
$stmt = $db->prepare("SELECT COUNT(*) as count FROM patients WHERE branch_id = ?");
$stmt->execute([$user_branch_id]);
$patient_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// 2. Today's Appointments (for this branch)
$stmt = $db->prepare("SELECT COUNT(*) as count FROM appointments WHERE branch_id = ? AND DATE(appointment_date) = CURDATE()");
$stmt->execute([$user_branch_id]);
$appointment_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// 3. Pending Appointments (for this branch)
$stmt = $db->prepare("SELECT COUNT(*) as count FROM appointments WHERE branch_id = ? AND status IN ('scheduled', 'pending')");
$stmt->execute([$user_branch_id]);
$pending_appointments = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// 4. Today's Visits (for this branch)
$stmt = $db->prepare("SELECT COUNT(*) as count FROM visits WHERE branch_id = ? AND DATE(created_at) = CURDATE()");
$stmt->execute([$user_branch_id]);
$today_visits = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// 5. Pending Patients (waiting for doctor)
$stmt = $db->prepare("SELECT COUNT(*) as count FROM visits WHERE branch_id = ? AND status IN ('pending', 'assigned')");
$stmt->execute([$user_branch_id]);
$pending_patients = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// ================================================================
// CREATE DATA HASH FOR CHANGE DETECTION
// ================================================================
$data_array = [
    'patients' => $patient_count,
    'appointments' => $appointment_count,
    'pending_appointments' => $pending_appointments,
    'today_visits' => $today_visits,
    'pending_patients' => $pending_patients
];
$data_hash = md5(json_encode($data_array));

// ================================================================
// RETURN JSON
// ================================================================
header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'hash' => $data_hash,
    'patients' => $patient_count,
    'appointments' => $appointment_count,
    'pending_appointments' => $pending_appointments,
    'today_visits' => $today_visits,
    'pending_patients' => $pending_patients,
    'timestamp' => date('Y-m-d H:i:s')
]);
?>