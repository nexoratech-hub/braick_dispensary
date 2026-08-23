<?php
// ================================================================
// FILE: frontend/pages/reception/get_sidebar_stats.php
// RETURNS JSON DATA FOR SIDEBAR AUTO-UPDATE
// USING NEW DATABASE: dispensary_db
// BRAICK DISPENSARY
// ================================================================

// ================================================================
// SESSION START
// ================================================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ================================================================
// CHECK SESSION - REDIRECT TO LOGIN IF NOT RECEPTION
// ================================================================
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'reception') {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => 'Not logged in or invalid role',
        'redirect' => '/dispensary_system/frontend/pages/login.php'
    ]);
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
// INCLUDE DATABASE - NEW DATABASE
// ================================================================
require_once __DIR__ . '/../../../backend/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
    
    // ================================================================
    // FETCH SIDEBAR STATISTICS - NEW DATABASE
    // ================================================================
    
    // ================================================================
    // 1. Total Patients (for this branch)
    // ================================================================
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM patients WHERE branch_id = ?");
    $stmt->execute([$user_branch_id]);
    $patient_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    
    // ================================================================
    // 2. Today's Appointments (for this branch)
    // ================================================================
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM appointments 
        WHERE branch_id = ? AND DATE(appointment_date) = CURDATE()
    ");
    $stmt->execute([$user_branch_id]);
    $appointment_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    
    // ================================================================
    // 3. Pending Appointments (scheduled, confirmed, pending)
    // ================================================================
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM appointments 
        WHERE branch_id = ? AND status IN ('scheduled', 'confirmed', 'pending')
    ");
    $stmt->execute([$user_branch_id]);
    $pending_appointments = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    
    // ================================================================
    // 4. Today's Visits
    // ================================================================
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM visits 
        WHERE branch_id = ? AND DATE(created_at) = CURDATE()
    ");
    $stmt->execute([$user_branch_id]);
    $today_visits = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    
    // ================================================================
    // 5. Pending Patients (waiting for doctor - visits in progress)
    // ================================================================
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM visits 
        WHERE branch_id = ? AND status IN ('pending', 'assigned', 'with_doctor')
    ");
    $stmt->execute([$user_branch_id]);
    $pending_patients = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    
    // ================================================================
    // 6. Online Doctors (for this branch)
    // ================================================================
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM users 
        WHERE role = 'doctor' AND is_online = 1 AND status = 'active' AND branch_id = ?
    ");
    $stmt->execute([$user_branch_id]);
    $online_doctors = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    
    // ================================================================
    // 7. Total Doctors (for this branch)
    // ================================================================
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM users 
        WHERE role = 'doctor' AND status = 'active' AND branch_id = ?
    ");
    $stmt->execute([$user_branch_id]);
    $total_doctors = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    
    // ================================================================
    // 8. Unread Notifications
    // ================================================================
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM notifications 
        WHERE user_id = ? AND is_read = 0
    ");
    $stmt->execute([$user_id]);
    $unread_notifications = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    
    // ================================================================
    // 9. Today's Completed Visits
    // ================================================================
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM visits 
        WHERE branch_id = ? AND DATE(created_at) = CURDATE() AND status = 'completed'
    ");
    $stmt->execute([$user_branch_id]);
    $today_completed = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    
    // ================================================================
    // 10. Total Appointments (all time)
    // ================================================================
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM appointments 
        WHERE branch_id = ?
    ");
    $stmt->execute([$user_branch_id]);
    $total_appointments = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    
    // ================================================================
    // 11. Pending Prescriptions (for reference)
    // ================================================================
    $pending_prescriptions = 0;
    try {
        $stmt = $db->prepare("
            SELECT COUNT(*) as count 
            FROM prescriptions 
            WHERE branch_id = ? AND status = 'pending'
        ");
        $stmt->execute([$user_branch_id]);
        $pending_prescriptions = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    } catch (Exception $e) {
        $pending_prescriptions = 0;
    }
    
    // ================================================================
    // 12. Today's Appointments - Pending
    // ================================================================
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM appointments 
        WHERE branch_id = ? AND DATE(appointment_date) = CURDATE() 
        AND status IN ('scheduled', 'confirmed', 'pending')
    ");
    $stmt->execute([$user_branch_id]);
    $today_appointments_pending = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    
    // ================================================================
    // 13. Today's Appointments - Completed
    // ================================================================
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM appointments 
        WHERE branch_id = ? AND DATE(appointment_date) = CURDATE() AND status = 'completed'
    ");
    $stmt->execute([$user_branch_id]);
    $today_appointments_completed = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    
    // ================================================================
    // 14. Today's Appointments - Cancelled
    // ================================================================
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM appointments 
        WHERE branch_id = ? AND DATE(appointment_date) = CURDATE() AND status = 'cancelled'
    ");
    $stmt->execute([$user_branch_id]);
    $today_appointments_cancelled = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    
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
        'unread_notifications' => $unread_notifications,
        'today_completed' => $today_completed,
        'total_appointments' => $total_appointments,
        'pending_prescriptions' => $pending_prescriptions,
        'today_appointments_pending' => $today_appointments_pending,
        'today_appointments_completed' => $today_appointments_completed,
        'today_appointments_cancelled' => $today_appointments_cancelled
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
        'today_completed' => $today_completed,
        'total_appointments' => $total_appointments,
        'pending_prescriptions' => $pending_prescriptions,
        'today_appointments_pending' => $today_appointments_pending,
        'today_appointments_completed' => $today_appointments_completed,
        'today_appointments_cancelled' => $today_appointments_cancelled,
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