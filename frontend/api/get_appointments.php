<?php
// ================================================================
// FILE: frontend/api/get_appointments.php
// API - GET APPOINTMENTS DATA FOR AUTO-UPDATE
// BRAICK DISPENSARY
// ================================================================

session_start();

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

// Only reception and admin can access
if ($_SESSION['role'] !== 'reception' && $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// Get parameters
$branch_id = isset($_GET['branch_id']) ? (int)$_GET['branch_id'] : ($_SESSION['branch_id'] ?? 1);
$status_filter = $_GET['status'] ?? '';
$period_filter = $_GET['period'] ?? '';
$date_filter = $_GET['date'] ?? '';
$search = $_GET['search'] ?? '';

// Include database
require_once __DIR__ . '/../../backend/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
    
    // ================================================================
    // BUILD QUERY
    // ================================================================
    $query = "
        SELECT a.*, 
               p.full_name as patient_name, 
               p.patient_id,
               p.id as patient_id,
               u.full_name as doctor_name,
               (
                   SELECT COUNT(*) 
                   FROM appointments 
                   WHERE patient_id = a.patient_id 
                   AND branch_id = ?
               ) as patient_appointment_count
        FROM appointments a
        JOIN patients p ON a.patient_id = p.id
        JOIN users u ON a.doctor_id = u.id
        WHERE a.branch_id = ?
    ";
    $params = [$branch_id, $branch_id];
    
    if (!empty($status_filter)) {
        $query .= " AND a.status = ?";
        $params[] = $status_filter;
    }
    
    // Date range filter
    if (!empty($date_filter) && $period_filter !== 'all') {
        $query .= " AND DATE(a.appointment_date) >= ?";
        $params[] = $date_filter;
    }
    
    if (!empty($search)) {
        $query .= " AND (p.full_name LIKE ? OR p.patient_id LIKE ? OR u.full_name LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    $query .= " ORDER BY a.appointment_date";
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // ================================================================
    // GET STATUS COUNTS
    // ================================================================
    $status_counts = [];
    $statuses = ['scheduled', 'confirmed', 'in-progress', 'completed', 'cancelled'];
    
    foreach ($statuses as $status) {
        $sql = "SELECT COUNT(*) as total FROM appointments WHERE status = ? AND branch_id = ?";
        $params_status = [$status, $branch_id];
        
        if (!empty($date_filter) && $period_filter !== 'all') {
            $sql .= " AND DATE(appointment_date) >= ?";
            $params_status[] = $date_filter;
        }
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params_status);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $status_counts[$status] = $row['total'] ?? 0;
    }
    
    // ================================================================
    // GET ONLINE DOCTORS
    // ================================================================
    $stmt = $db->prepare("
        SELECT COUNT(*) as total 
        FROM users 
        WHERE role = 'doctor' AND is_online = 1 AND status = 'active' AND branch_id = ?
    ");
    $stmt->execute([$branch_id]);
    $online_doctors = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    // ================================================================
    // CHECK FOR NOTIFICATIONS
    // ================================================================
    $notification = null;
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM notifications 
        WHERE user_id = ? AND is_read = 0 AND created_at > DATE_SUB(NOW(), INTERVAL 10 SECOND)
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $new_notif = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    
    if ($new_notif > 0) {
        $notification = $new_notif . ' new notification(s)';
    }
    
    // ================================================================
    // RESPONSE
    // ================================================================
    echo json_encode([
        'success' => true,
        'appointments' => $appointments,
        'status_counts' => $status_counts,
        'online_doctors' => $online_doctors,
        'total_records' => count($appointments),
        'notification' => $notification,
        'timestamp' => date('H:i:s')
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>