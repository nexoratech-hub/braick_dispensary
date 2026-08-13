<?php
// ================================================================
// FILE: frontend/pages/reception/get_sidebar_stats.php
// RETURNS JSON DATA FOR SIDEBAR AUTO-UPDATE
// BRAICK DISPENSARY
// ================================================================

session_start();

// ================================================================
// CHECK SESSION - REDIRECT TO LOGIN IF NOT RECEPTION
// ================================================================
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'reception') {
    header('Location: /dispensary_system/frontend/pages/login.php');
    exit;
}

// ================================================================
// GET SESSION DATA
// ================================================================
$user_id = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'] ?? 'Receptionist';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$username = $_SESSION['username'] ?? 'reception';

// ================================================================
// INCLUDE DATABASE - CORRECT PATH
// ================================================================
require_once __DIR__ . '/../../../backend/config/database.php';

try {
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
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM appointments WHERE branch_id = ? AND status IN ('scheduled', 'pending', 'confirmed')");
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
    
    // 6. Online Doctors (for this branch)
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM users WHERE role = 'doctor' AND is_online = 1 AND status = 'active' AND branch_id = ?");
    $stmt->execute([$user_branch_id]);
    $online_doctors = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    
    // 7. Total Doctors (for this branch)
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM users WHERE role = 'doctor' AND status = 'active' AND branch_id = ?");
    $stmt->execute([$user_branch_id]);
    $total_doctors = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    
    // 8. Unread Notifications
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$user_id]);
    $unread_notifications = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    
    // ================================================================
    // CREATE DATA HASH FOR CHANGE DETECTION
    // ================================================================
    $data_array = [
        'patients' => $patient_count,
        'appointments' => $appointment_count,
        'pending_appointments' => $pending_appointments,
        'today_visits' => $today_visits,
        'pending_patients' => $pending_patients,
        'online_doctors' => $online_doctors,
        'total_doctors' => $total_doctors,
        'unread_notifications' => $unread_notifications
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
        'online_doctors' => $online_doctors,
        'total_doctors' => $total_doctors,
        'unread_notifications' => $unread_notifications,
        'branch_id' => $user_branch_id,
        'branch_name' => $branch_name,
        'user_id' => $user_id,
        'full_name' => $full_name,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    // Return error response
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}
?>