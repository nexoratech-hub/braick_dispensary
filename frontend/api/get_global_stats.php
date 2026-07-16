<?php
// ================================================================
// FILE: frontend/api/get_global_stats.php
// RETURNS ALL STATS FOR ALL ROLES - AUTO UPDATE EVERY 3 SECONDS
// SUPPORTS: admin, reception, doctor, pharmacy, laboratory, cashier
// BRAICK DISPENSARY
// ================================================================

session_start();

// ================================================================
// IF NO SESSION, USE DEFAULT (Reception)
// ================================================================
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 6;
    $_SESSION['full_name'] = 'Rose Mwangi';
    $_SESSION['role'] = 'reception';
    $_SESSION['branch_id'] = 1;
    $_SESSION['branch_name'] = 'Dodoma';
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'] ?? 'reception';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$is_admin = ($user_role === 'admin');

// ================================================================
// INCLUDE DATABASE
// ================================================================
require_once __DIR__ . '/../../backend/config/database.php';
$db = Database::getInstance()->getConnection();

// ================================================================
// TODAY'S DATE
// ================================================================
$today = date('Y-m-d');
$now = date('Y-m-d H:i:s');

// ================================================================
// BASE PARAMS
// ================================================================
$branch_filter = $is_admin ? '' : ' AND branch_id = ' . (int)$user_branch_id;
$branch_params = $is_admin ? [] : [$user_branch_id];

// ================================================================
// FETCH ALL STATISTICS BY ROLE
// ================================================================

// ================================================================
// 1. COMMON STATS (All Roles)
// ================================================================

// 1a. Total Patients
$stmt = $db->prepare("SELECT COUNT(*) as count FROM patients WHERE 1=1 " . ($is_admin ? '' : ' AND branch_id = ?'));
$stmt->execute($branch_params);
$total_patients = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// 1b. Today's Patients
$stmt = $db->prepare("
    SELECT COUNT(DISTINCT patient_id) as count 
    FROM visits 
    WHERE DATE(created_at) = ? " . ($is_admin ? '' : ' AND branch_id = ?')
);
$params = [$today];
if (!$is_admin) $params[] = $user_branch_id;
$stmt->execute($params);
$today_patients = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// 1c. Total Visits
$stmt = $db->prepare("SELECT COUNT(*) as count FROM visits WHERE 1=1 " . ($is_admin ? '' : ' AND branch_id = ?'));
$stmt->execute($branch_params);
$total_visits = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// 1d. Today's Visits
$stmt = $db->prepare("
    SELECT COUNT(*) as count 
    FROM visits 
    WHERE DATE(created_at) = ? " . ($is_admin ? '' : ' AND branch_id = ?')
);
$params = [$today];
if (!$is_admin) $params[] = $user_branch_id;
$stmt->execute($params);
$today_visits = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// 1e. Total Appointments
$stmt = $db->prepare("SELECT COUNT(*) as count FROM appointments WHERE 1=1 " . ($is_admin ? '' : ' AND branch_id = ?'));
$stmt->execute($branch_params);
$total_appointments = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// 1f. Today's Appointments
$stmt = $db->prepare("
    SELECT COUNT(*) as count 
    FROM appointments 
    WHERE DATE(appointment_date) = ? " . ($is_admin ? '' : ' AND branch_id = ?')
);
$params = [$today];
if (!$is_admin) $params[] = $user_branch_id;
$stmt->execute($params);
$today_appointments = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// 1g. Pending Appointments
$stmt = $db->prepare("
    SELECT COUNT(*) as count 
    FROM appointments 
    WHERE status IN ('scheduled', 'pending') " . ($is_admin ? '' : ' AND branch_id = ?')
);
$stmt->execute($branch_params);
$pending_appointments = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// 1h. Pending Visits
$stmt = $db->prepare("
    SELECT COUNT(*) as count 
    FROM visits 
    WHERE status IN ('pending', 'assigned') " . ($is_admin ? '' : ' AND branch_id = ?')
);
$stmt->execute($branch_params);
$pending_visits = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// 1i. Completed Visits Today
$stmt = $db->prepare("
    SELECT COUNT(*) as count 
    FROM visits 
    WHERE DATE(created_at) = ? AND status = 'completed' " . ($is_admin ? '' : ' AND branch_id = ?')
);
$params = [$today];
if (!$is_admin) $params[] = $user_branch_id;
$stmt->execute($params);
$completed_visits_today = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// 1j. Unread Notifications
$stmt = $db->prepare("
    SELECT COUNT(*) as count 
    FROM notifications 
    WHERE user_id = ? AND is_read = 0
");
$stmt->execute([$user_id]);
$unread_notifications = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// 1k. Online Doctors
$stmt = $db->prepare("
    SELECT COUNT(*) as count 
    FROM users 
    WHERE role = 'doctor' AND status = 'active' AND is_online = 1 " . ($is_admin ? '' : ' AND branch_id = ?')
);
$stmt->execute($branch_params);
$online_doctors = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// 1l. Total Doctors
$stmt = $db->prepare("
    SELECT COUNT(*) as count 
    FROM users 
    WHERE role = 'doctor' AND status = 'active' " . ($is_admin ? '' : ' AND branch_id = ?')
);
$stmt->execute($branch_params);
$total_doctors = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// ================================================================
// 2. ROLE-SPECIFIC STATS
// ================================================================

$role_stats = [];

// 2a. Reception Stats
if ($user_role === 'reception' || $is_admin) {
    $role_stats['reception'] = [
        'today_registrations' => $today_patients,
        'pending_appointments' => $pending_appointments,
        'today_appointments' => $today_appointments
    ];
}

// 2b. Doctor Stats
if ($user_role === 'doctor' || $is_admin) {
    // Pending patients for this doctor
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM visits 
        WHERE doctor_id = ? AND status IN ('pending', 'assigned')
    ");
    $stmt->execute([$user_id]);
    $my_pending_patients = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    
    // Today's patients for this doctor
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM visits 
        WHERE doctor_id = ? AND DATE(created_at) = ?
    ");
    $stmt->execute([$user_id, $today]);
    $my_today_patients = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    
    $role_stats['doctor'] = [
        'my_pending_patients' => $my_pending_patients,
        'my_today_patients' => $my_today_patients,
        'my_total_patients' => $total_patients,
        'my_appointments' => $today_appointments
    ];
}

// 2c. Pharmacy Stats
if ($user_role === 'pharmacy' || $is_admin) {
    // Pending prescriptions
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM prescriptions 
        WHERE status = 'pending' " . ($is_admin ? '' : ' AND branch_id = ?')
    );
    $stmt->execute($branch_params);
    $pending_prescriptions = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    
    // Today's dispensed
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM prescriptions 
        WHERE DATE(dispensed_at) = ? AND status = 'dispensed' " . ($is_admin ? '' : ' AND branch_id = ?')
    );
    $params = [$today];
    if (!$is_admin) $params[] = $user_branch_id;
    $stmt->execute($params);
    $today_dispensed = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    
    // Low stock items
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM medications_inventory 
        WHERE quantity <= reorder_level " . ($is_admin ? '' : ' AND branch_id = ?')
    );
    $stmt->execute($branch_params);
    $low_stock = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    
    $role_stats['pharmacy'] = [
        'pending_prescriptions' => $pending_prescriptions,
        'today_dispensed' => $today_dispensed,
        'low_stock' => $low_stock,
        'total_medications' => 0 // You can add this
    ];
}

// 2d. Laboratory Stats
if ($user_role === 'laboratory' || $is_admin) {
    // Pending lab tests
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM lab_tests 
        WHERE status IN ('pending', 'in_progress') " . ($is_admin ? '' : ' AND branch_id = ?')
    );
    $stmt->execute($branch_params);
    $pending_lab_tests = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    
    // Today's completed tests
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM lab_tests 
        WHERE DATE(completed_at) = ? AND status = 'completed' " . ($is_admin ? '' : ' AND branch_id = ?')
    );
    $params = [$today];
    if (!$is_admin) $params[] = $user_branch_id;
    $stmt->execute($params);
    $today_completed = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    
    $role_stats['laboratory'] = [
        'pending_lab_tests' => $pending_lab_tests,
        'today_completed' => $today_completed,
        'total_lab_tests' => 0 // You can add this
    ];
}

// 2e. Cashier Stats
if ($user_role === 'cashier' || $is_admin) {
    // Today's revenue
    $stmt = $db->prepare("
        SELECT COALESCE(SUM(total_amount), 0) as total 
        FROM patient_bills 
        WHERE DATE(created_at) = ? AND status = 'paid' " . ($is_admin ? '' : ' AND branch_id = ?')
    );
    $params = [$today];
    if (!$is_admin) $params[] = $user_branch_id;
    $stmt->execute($params);
    $today_revenue = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    // Pending bills
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM patient_bills 
        WHERE status IN ('pending', 'partial') " . ($is_admin ? '' : ' AND branch_id = ?')
    );
    $stmt->execute($branch_params);
    $pending_bills = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    
    // Today's payments
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM payments 
        WHERE DATE(received_at) = ? " . ($is_admin ? '' : ' AND branch_id = ?')
    );
    $params = [$today];
    if (!$is_admin) $params[] = $user_branch_id;
    $stmt->execute($params);
    $today_payments = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    
    $role_stats['cashier'] = [
        'today_revenue' => (float)$today_revenue,
        'pending_bills' => $pending_bills,
        'today_payments' => $today_payments,
        'total_patients' => $total_patients
    ];
}

// 2f. Admin Stats
if ($is_admin) {
    // Total branches
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM branches WHERE status = 'active'");
    $stmt->execute();
    $total_branches = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    
    // Total users
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM users WHERE status = 'active'");
    $stmt->execute();
    $total_users = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    
    // Today's revenue across all branches
    $stmt = $db->prepare("
        SELECT COALESCE(SUM(total_amount), 0) as total 
        FROM patient_bills 
        WHERE DATE(created_at) = ? AND status = 'paid'
    ");
    $stmt->execute([$today]);
    $global_revenue = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    $role_stats['admin'] = [
        'total_branches' => $total_branches,
        'total_users' => $total_users,
        'global_revenue' => (float)$global_revenue,
        'total_patients' => $total_patients,
        'total_visits' => $total_visits,
        'total_appointments' => $total_appointments
    ];
}

// ================================================================
// 3. LISTS FOR DISPLAY
// ================================================================

// 3a. Online Doctors List
$stmt = $db->prepare("
    SELECT id, full_name, specialty, is_online 
    FROM users 
    WHERE role = 'doctor' AND status = 'active' AND is_online = 1 " . ($is_admin ? '' : ' AND branch_id = ?') . "
    ORDER BY full_name
    LIMIT 10
");
$stmt->execute($branch_params);
$online_doctors_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 3b. Today's Appointments List
$stmt = $db->prepare("
    SELECT a.*, p.full_name as patient_name, u.full_name as doctor_name 
    FROM appointments a
    JOIN patients p ON a.patient_id = p.id
    JOIN users u ON a.doctor_id = u.id
    WHERE DATE(a.appointment_date) = ? " . ($is_admin ? '' : ' AND a.branch_id = ?') . "
    ORDER BY a.appointment_date
    LIMIT 10
");
$params = [$today];
if (!$is_admin) $params[] = $user_branch_id;
$stmt->execute($params);
$today_appointments_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 3c. Recent Activities
try {
    $stmt = $db->prepare("
        SELECT action, details, created_at 
        FROM activity_logs 
        ORDER BY created_at DESC 
        LIMIT 5
    ");
    $stmt->execute();
    $recent_activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $recent_activities = [];
}

// ================================================================
// 4. CREATE DATA HASH FOR CHANGE DETECTION
// ================================================================
$data_array = array_merge(
    [
        'online_doctors' => $online_doctors,
        'total_doctors' => $total_doctors,
        'total_patients' => $total_patients,
        'today_patients' => $today_patients,
        'today_visits' => $today_visits,
        'total_appointments' => $total_appointments,
        'today_appointments' => $today_appointments,
        'pending_appointments' => $pending_appointments,
        'pending_visits' => $pending_visits,
        'completed_visits_today' => $completed_visits_today,
        'unread_notifications' => $unread_notifications
    ],
    $role_stats
);
$data_hash = md5(json_encode($data_array));

// ================================================================
// 5. RETURN JSON
// ================================================================
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');
header('X-Content-Type-Options: nosniff');

echo json_encode([
    'success' => true,
    'hash' => $data_hash,
    'timestamp' => date('Y-m-d H:i:s'),
    'user' => [
        'id' => $user_id,
        'role' => $user_role,
        'branch_id' => $user_branch_id,
        'branch_name' => $user_branch_name,
        'is_admin' => $is_admin
    ],
    'stats' => [
        // Common stats
        'online_doctors' => (int)$online_doctors,
        'total_doctors' => (int)$total_doctors,
        'total_patients' => (int)$total_patients,
        'today_patients' => (int)$today_patients,
        'today_visits' => (int)$today_visits,
        'total_visits' => (int)$total_visits,
        'total_appointments' => (int)$total_appointments,
        'today_appointments' => (int)$today_appointments,
        'pending_appointments' => (int)$pending_appointments,
        'pending_visits' => (int)$pending_visits,
        'completed_visits_today' => (int)$completed_visits_today,
        'unread_notifications' => (int)$unread_notifications,
        
        // Role-specific stats
        'role_stats' => $role_stats
    ],
    'lists' => [
        'online_doctors' => $online_doctors_list,
        'today_appointments' => $today_appointments_list,
        'recent_activities' => $recent_activities
    ]
]);
?>