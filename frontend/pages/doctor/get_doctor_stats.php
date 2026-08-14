<?php
// ================================================================
// FILE: frontend/pages/doctor/get_doctor_stats.php
// DOCTOR STATS API - RETURNS JSON FOR AUTO-UPDATE
// BRAICK DISPENSARY
// ================================================================

session_start();

// ================================================================
// CHECK SESSION - REDIRECT TO LOGIN IF NOT DOCTOR
// ================================================================
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    // Return JSON error for AJAX requests
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => 'Unauthorized - Please login first',
            'redirect' => '/dispensary_system/frontend/pages/login.php'
        ]);
        exit;
    }
    header('Location: /dispensary_system/frontend/pages/login.php');
    exit;
}

// ================================================================
// GET DOCTOR DATA FROM SESSION
// ================================================================
$doctor_id = $_SESSION['user_id'];
$doctor_name = $_SESSION['full_name'] ?? 'Dr. Unknown';
$doctor_branch_id = $_SESSION['branch_id'] ?? 1;

// ================================================================
// INCLUDE DATABASE
// ================================================================
require_once 'C:/xampp/htdocs/dispensary_system/backend/config/database.php';

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
// VERIFY DOCTOR EXISTS AND IS ACTIVE
// ================================================================
try {
    $stmt = $db->prepare("SELECT id, full_name, branch_id, status, is_online FROM users WHERE id = ? AND role = 'doctor'");
    $stmt->execute([$doctor_id]);
    $doctor_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$doctor_data || $doctor_data['status'] !== 'active') {
        session_destroy();
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => 'Doctor account inactive',
                'redirect' => '/dispensary_system/frontend/pages/login.php'
            ]);
            exit;
        }
        header('Location: /dispensary_system/frontend/pages/login.php');
        exit;
    }
    
    $doctor_name = $doctor_data['full_name'];
    $doctor_is_online = $doctor_data['is_online'] ?? 0;
    $_SESSION['full_name'] = $doctor_name;
    $_SESSION['is_online'] = $doctor_is_online;
    
} catch (Exception $e) {
    error_log("get_doctor_stats verification error: " . $e->getMessage());
}

// ================================================================
// TODAY'S DATE
// ================================================================
$today = date('Y-m-d');

// ================================================================
// FETCH ALL STATISTICS FOR DOCTOR
// ================================================================

// 1. Today's Patients - Pending
try {
    $stmt = $db->prepare("
        SELECT COUNT(DISTINCT CASE WHEN status IN ('pending', 'assigned') THEN patient_id END) as pending,
               COUNT(DISTINCT CASE WHEN status = 'completed' THEN patient_id END) as completed
        FROM visits 
        WHERE doctor_id = ? AND DATE(created_at) = ?
    ");
    $stmt->execute([$doctor_id, $today]);
    $today_patients = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $today_patients = ['pending' => 0, 'completed' => 0];
}
$today_patients_pending = $today_patients['pending'] ?? 0;
$today_patients_completed = $today_patients['completed'] ?? 0;
$today_patients_total = $today_patients_pending + $today_patients_completed;

// 2. Today's Visits
try {
    $stmt = $db->prepare("
        SELECT 
            COUNT(CASE WHEN status IN ('pending', 'assigned') THEN 1 END) as pending,
            COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed
        FROM visits 
        WHERE doctor_id = ? AND DATE(created_at) = ?
    ");
    $stmt->execute([$doctor_id, $today]);
    $today_visits = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $today_visits = ['pending' => 0, 'completed' => 0];
}
$today_visits_pending = $today_visits['pending'] ?? 0;
$today_visits_completed = $today_visits['completed'] ?? 0;
$today_visits_total = $today_visits_pending + $today_visits_completed;

// 3. Total Patients
try {
    $stmt = $db->prepare("SELECT COUNT(DISTINCT patient_id) as total FROM visits WHERE doctor_id = ?");
    $stmt->execute([$doctor_id]);
    $total_patients = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
} catch (Exception $e) {
    $total_patients = 0;
}

// 4. Total Visits
try {
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM visits WHERE doctor_id = ?");
    $stmt->execute([$doctor_id]);
    $total_visits = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
} catch (Exception $e) {
    $total_visits = 0;
}

// 5. Today's Appointments
try {
    $stmt = $db->prepare("
        SELECT 
            COUNT(CASE WHEN status IN ('scheduled', 'pending', 'confirmed') THEN 1 END) as pending,
            COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed
        FROM appointments 
        WHERE doctor_id = ? AND DATE(appointment_date) = ?
    ");
    $stmt->execute([$doctor_id, $today]);
    $today_appointments = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $today_appointments = ['pending' => 0, 'completed' => 0];
}
$today_appointments_pending = $today_appointments['pending'] ?? 0;
$today_appointments_completed = $today_appointments['completed'] ?? 0;
$today_appointments_total = $today_appointments_pending + $today_appointments_completed;

// 6. Total Appointments
try {
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM appointments WHERE doctor_id = ?");
    $stmt->execute([$doctor_id]);
    $total_appointments = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
} catch (Exception $e) {
    $total_appointments = 0;
}

// 7. Total Prescriptions
try {
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM prescriptions WHERE doctor_id = ?");
    $stmt->execute([$doctor_id]);
    $total_prescriptions = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
} catch (Exception $e) {
    $total_prescriptions = 0;
}

// 8. Lab Tests
try {
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
} catch (Exception $e) {
    $lab_tests = ['total' => 0, 'pending' => 0, 'completed' => 0];
}
$lab_tests_total = $lab_tests['total'] ?? 0;
$lab_tests_pending = $lab_tests['pending'] ?? 0;
$lab_tests_completed = $lab_tests['completed'] ?? 0;

// 9. Pending Visits (Queue)
try {
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM visits 
        WHERE doctor_id = ? AND status IN ('pending', 'assigned')
    ");
    $stmt->execute([$doctor_id]);
    $pending_visits = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
} catch (Exception $e) {
    $pending_visits = 0;
}

// 10. Today's Appointments List
try {
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
} catch (Exception $e) {
    $today_appointments_list = [];
}

// 11. Pending Patients Queue
try {
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
} catch (Exception $e) {
    $pending_patients = [];
}

// 12. Weekly Appointments Chart (for dashboard)
try {
    $stmt = $db->prepare("
        SELECT DATE(appointment_date) as date, COUNT(*) as count 
        FROM appointments 
        WHERE doctor_id = ? AND appointment_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        AND status NOT IN ('cancelled')
        GROUP BY DATE(appointment_date)
        ORDER BY date
    ");
    $stmt->execute([$doctor_id]);
    $weekly_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $weekly_data = [];
}

// Build weekly chart data
$chart_labels = [];
$chart_values = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $chart_labels[] = date('D', strtotime($date));
    $found = false;
    foreach ($weekly_data as $data) {
        if ($data['date'] == $date) {
            $chart_values[] = (int)$data['count'];
            $found = true;
            break;
        }
    }
    if (!$found) $chart_values[] = 0;
}

// 13. Recent Activities
try {
    $stmt = $db->prepare("
        (SELECT 'visit' as type, v.id, v.created_at, p.full_name as patient_name, 
                v.status, 'visit' as action_type
         FROM visits v
         JOIN patients p ON v.patient_id = p.id
         WHERE v.doctor_id = ?
         ORDER BY v.created_at DESC
         LIMIT 5)
        UNION ALL
        (SELECT 'appointment' as type, a.id, a.created_at, p.full_name as patient_name,
                a.status, 'appointment' as action_type
         FROM appointments a
         JOIN patients p ON a.patient_id = p.id
         WHERE a.doctor_id = ?
         ORDER BY a.created_at DESC
         LIMIT 5)
        ORDER BY created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$doctor_id, $doctor_id]);
    $recent_activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $recent_activities = [];
}

// 14. Unread Notifications
try {
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$doctor_id]);
    $unread_notifications = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
} catch (Exception $e) {
    $unread_notifications = 0;
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
    'appointments_count' => count($today_appointments_list),
    'unread_notifications' => $unread_notifications,
    'doctor_is_online' => $doctor_is_online ?? 0
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
        'chart' => [
            'labels' => $chart_labels,
            'values' => $chart_values
        ],
        'recent_activities' => $recent_activities,
        'unread_notifications' => $unread_notifications,
        'doctor_is_online' => $doctor_is_online ?? 0,
        'doctor_name' => $doctor_name,
        'timestamp' => date('Y-m-d H:i:s')
    ]
]);
exit;