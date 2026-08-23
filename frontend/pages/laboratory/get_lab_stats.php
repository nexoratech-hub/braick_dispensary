<?php
// ================================================================
// FILE: frontend/pages/laboratory/get_lab_stats.php
// LABORATORY STATS API - USING lab_tests ONLY
// ✅ USING NEW DATABASE: dispensary_db
// ✅ ONLY ONE TABLE: lab_tests (NO lab_requests)
// WITH FULL LOGIN SESSION PROTECTION
// BRAICK DISPENSARY
// ================================================================

// Start session
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
// CHECK IF USER IS LABORATORY OR ADMIN
// ================================================================
if ($_SESSION['role'] !== 'laboratory' && $_SESSION['role'] !== 'admin') {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => 'Unauthorized access. Laboratory role required.'
    ]);
    exit;
}

// ================================================================
// GET USER INFO FROM SESSION
// ================================================================
$user_id = $_SESSION['user_id'];
$user_full_name = $_SESSION['full_name'] ?? 'Lab Technician';
$user_role = $_SESSION['role'];
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$user_username = $_SESSION['username'] ?? 'lab.technician';

// ================================================================
// IF ADMIN VIEWING LAB STATS, USE THEIR BRANCH
// ================================================================
if ($_SESSION['role'] === 'admin') {
    $user_branch_id = isset($_GET['branch_id']) ? (int)$_GET['branch_id'] : $user_branch_id;
}

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
        'error' => 'Database connection error: ' . $e->getMessage()
    ]);
    exit;
}

// ================================================================
// TODAY'S DATE
// ================================================================
$today = date('Y-m-d');

// ================================================================
// FETCH ALL STATISTICS FROM lab_tests ONLY
// ================================================================

// 1. Pending Tests (status = 'pending')
$stmt = $db->prepare("SELECT COUNT(*) as count FROM lab_tests WHERE branch_id = ? AND status = 'pending'");
$stmt->execute([$user_branch_id]);
$pending_tests = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// 2. In Progress Tests (status = 'in_progress')
$stmt = $db->prepare("SELECT COUNT(*) as count FROM lab_tests WHERE branch_id = ? AND status = 'in_progress'");
$stmt->execute([$user_branch_id]);
$in_progress_tests = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// 3. Completed Today
$stmt = $db->prepare("SELECT COUNT(*) as count FROM lab_tests WHERE branch_id = ? AND status = 'completed' AND DATE(completed_at) = ?");
$stmt->execute([$user_branch_id, $today]);
$completed_today = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// 4. Today's Tests (all tests created today)
$stmt = $db->prepare("SELECT COUNT(*) as count FROM lab_tests WHERE branch_id = ? AND DATE(created_at) = ?");
$stmt->execute([$user_branch_id, $today]);
$today_tests = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// 5. Total Tests (all time)
$stmt = $db->prepare("SELECT COUNT(*) as count FROM lab_tests WHERE branch_id = ?");
$stmt->execute([$user_branch_id]);
$total_tests = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// 6. Total Completed Tests
$stmt = $db->prepare("SELECT COUNT(*) as count FROM lab_tests WHERE branch_id = ? AND status = 'completed'");
$stmt->execute([$user_branch_id]);
$total_completed = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// 7. Completion Rate
$total_tests_all = $total_tests;
$completion_rate = $total_tests_all > 0 ? round(($total_completed / $total_tests_all) * 100, 1) : 0;

// 8. Recent Tests (Last 10)
$stmt = $db->prepare("
    SELECT 
        lt.id as test_id,
        lt.visit_id,
        lt.doctor_id,
        lt.test_name,
        lt.test_type,
        lt.sample_type,
        lt.status,
        lt.test_date,
        lt.results,
        lt.reference_range,
        lt.notes,
        lt.created_at,
        lt.completed_at,
        lt.updated_at,
        p.full_name as patient_name,
        p.patient_id as patient_code,
        p.phone,
        p.gender,
        p.date_of_birth,
        u.full_name as doctor_name,
        u.specialty as doctor_specialty,
        v.visit_number,
        v.visit_type,
        v.diagnosis,
        v.status as visit_status
    FROM lab_tests lt
    LEFT JOIN visits v ON lt.visit_id = v.id
    LEFT JOIN patients p ON v.patient_id = p.id
    LEFT JOIN users u ON lt.doctor_id = u.id
    WHERE lt.branch_id = ?
    ORDER BY lt.created_at DESC
    LIMIT 10
");
$stmt->execute([$user_branch_id]);
$recent_tests = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 9. Daily Tests Chart (Last 7 days)
$daily_labels = [];
$daily_tests_data = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $daily_labels[] = date('D', strtotime($date));
    
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM lab_tests 
        WHERE branch_id = ? AND DATE(created_at) = ?
    ");
    $stmt->execute([$user_branch_id, $date]);
    $daily_tests_data[] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
}

// 10. Daily Completed Tests (Last 7 days)
$daily_completed_data = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM lab_tests 
        WHERE branch_id = ? AND status = 'completed' AND DATE(completed_at) = ?
    ");
    $stmt->execute([$user_branch_id, $date]);
    $daily_completed_data[] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
}

// 11. Monthly Tests Chart (Last 6 months)
$monthly_labels = [];
$monthly_tests_data = [];
for ($i = 5; $i >= 0; $i--) {
    $month = date('M', strtotime("-$i months"));
    $monthly_labels[] = $month;
    
    $start = date('Y-m-01', strtotime("-$i months"));
    $end = date('Y-m-t', strtotime("-$i months"));
    
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM lab_tests 
        WHERE branch_id = ? AND DATE(created_at) BETWEEN ? AND ?
    ");
    $stmt->execute([$user_branch_id, $start, $end]);
    $monthly_tests_data[] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
}

// 12. Most Requested Tests (by test_name)
$stmt = $db->prepare("
    SELECT test_name, COUNT(*) as count 
    FROM lab_tests 
    WHERE branch_id = ?
    GROUP BY test_name
    ORDER BY count DESC
    LIMIT 5
");
$stmt->execute([$user_branch_id]);
$most_requested = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 13. Test Status Distribution
$stmt = $db->prepare("
    SELECT status, COUNT(*) as count 
    FROM lab_tests 
    WHERE branch_id = ?
    GROUP BY status
");
$stmt->execute([$user_branch_id]);
$status_distribution = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// GET UNREAD NOTIFICATIONS COUNT
// ================================================================
$unread_notifications = 0;
try {
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$user_id]);
    $unread_notifications = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
} catch (Exception $e) {
    $unread_notifications = 0;
}

// ================================================================
// CREATE DATA HASH FOR CHANGE DETECTION
// ================================================================
$data_array = [
    'pending_tests' => $pending_tests,
    'in_progress_tests' => $in_progress_tests,
    'completed_today' => $completed_today,
    'today_tests' => $today_tests,
    'total_tests' => $total_tests,
    'total_completed' => $total_completed,
    'completion_rate' => $completion_rate,
    'daily_tests' => $daily_tests_data,
    'daily_completed' => $daily_completed_data,
    'monthly_tests' => $monthly_tests_data,
    'recent_count' => count($recent_tests),
    'most_requested_count' => count($most_requested),
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
    'user' => [
        'id' => $user_id,
        'name' => $user_full_name,
        'role' => $user_role,
        'branch_id' => $user_branch_id,
        'branch_name' => $user_branch_name,
        'username' => $user_username
    ],
    'data' => [
        'stats' => [
            'pending_tests' => $pending_tests,
            'in_progress_tests' => $in_progress_tests,
            'completed_today' => $completed_today,
            'today_tests' => $today_tests,
            'total_tests' => $total_tests,
            'total_completed' => $total_completed,
            'completion_rate' => $completion_rate,
            'unread_notifications' => $unread_notifications
        ],
        'charts' => [
            'daily_labels' => $daily_labels,
            'daily_tests' => $daily_tests_data,
            'daily_completed' => $daily_completed_data,
            'monthly_labels' => $monthly_labels,
            'monthly_tests' => $monthly_tests_data
        ],
        'lists' => [
            'recent_tests' => $recent_tests,
            'most_requested' => $most_requested,
            'status_distribution' => $status_distribution
        ],
        'timestamp' => date('Y-m-d H:i:s')
    ]
]);
?>