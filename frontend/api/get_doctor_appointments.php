<?php
// ================================================================
// FILE: frontend/api/get_doctor_appointments.php
// API - GET DOCTOR APPOINTMENTS FOR AUTO-UPDATE
// BRAICK DISPENSARY
// ================================================================

session_start();

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

// Only doctor and admin can access
if ($_SESSION['role'] !== 'doctor' && $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// Get parameters
$doctor_id = $_SESSION['user_id'];
$branch_id = isset($_GET['branch_id']) ? (int)$_GET['branch_id'] : ($_SESSION['branch_id'] ?? 1);
$status_filter = $_GET['status'] ?? '';
$date_filter = $_GET['date'] ?? '';
$search = $_GET['search'] ?? '';

// Include database
require_once __DIR__ . '/../../backend/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
    
    // ================================================================
    // BUILD QUERY
    // ================================================================
    $sql = "
        SELECT 
            a.*,
            p.full_name as patient_name,
            p.patient_id as patient_code,
            p.phone as patient_phone,
            p.email as patient_email,
            u.full_name as doctor_name,
            u.specialty as doctor_specialty,
            r.full_name as created_by_name
        FROM appointments a
        JOIN patients p ON a.patient_id = p.id
        LEFT JOIN users u ON a.doctor_id = u.id
        LEFT JOIN users r ON a.created_by = r.id
        WHERE a.doctor_id = ?
    ";
    
    $params = [$doctor_id];
    
    if (!empty($status_filter)) {
        $sql .= " AND a.status = ?";
        $params[] = $status_filter;
    }
    
    if (!empty($date_filter)) {
        $sql .= " AND DATE(a.appointment_date) = ?";
        $params[] = $date_filter;
    }
    
    if (!empty($search)) {
        $sql .= " AND (p.full_name LIKE ? OR p.patient_id LIKE ? OR p.phone LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    $sql .= " ORDER BY a.appointment_date ASC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // ================================================================
    // GET STATS
    // ================================================================
    $stats = [
        'total' => 0,
        'scheduled' => 0,
        'confirmed' => 0,
        'completed' => 0,
        'cancelled' => 0
    ];
    
    $statuses = ['scheduled', 'confirmed', 'completed', 'cancelled'];
    foreach ($statuses as $status) {
        $sql = "SELECT COUNT(*) as count FROM appointments WHERE doctor_id = ? AND status = ?";
        $stmt = $db->prepare($sql);
        $stmt->execute([$doctor_id, $status]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $stats[$status] = $row['count'] ?? 0;
        $stats['total'] += $stats[$status];
    }
    
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
        'stats' => $stats,
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