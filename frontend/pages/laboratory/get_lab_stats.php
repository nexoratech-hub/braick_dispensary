<?php
// ================================================================
// FILE: frontend/pages/laboratory/get_lab_stats.php
// LABORATORY STATS API - RETURNS JSON FOR AUTO-UPDATE
// BRAICK DISPENSARY
// ================================================================

session_start();

// ================================================================
// IF NO SESSION, USE LAB.DODOMA (ID: 8) AS DEFAULT
// ================================================================
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'laboratory') {
    $_SESSION['user_id'] = 8;
    $_SESSION['full_name'] = 'Lab Technician Dodoma';
    $_SESSION['role'] = 'laboratory';
    $_SESSION['branch_id'] = 1;
    $_SESSION['branch_name'] = 'Dodoma';
    $_SESSION['username'] = 'lab.dodoma';
}

$user_id = $_SESSION['user_id'] ?? 8;
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
$start_of_month = date('Y-m-01');

// ================================================================
// FETCH ALL STATISTICS
// ================================================================

// 1. Pending Requests
$stmt = $db->prepare("SELECT COUNT(*) as count FROM lab_requests WHERE branch_id = ? AND status = 'pending'");
$stmt->execute([$user_branch_id]);
$pending = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// 2. In Progress Requests
$stmt = $db->prepare("SELECT COUNT(*) as count FROM lab_requests WHERE branch_id = ? AND status = 'in_progress'");
$stmt->execute([$user_branch_id]);
$in_progress = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// 3. Completed Today
$stmt = $db->prepare("SELECT COUNT(*) as count FROM lab_requests WHERE branch_id = ? AND status = 'completed' AND DATE(completed_at) = ?");
$stmt->execute([$user_branch_id, $today]);
$completed_today = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// 4. Today's Tests
$stmt = $db->prepare("
    SELECT COUNT(*) as count 
    FROM lab_request_items lri
    JOIN lab_requests lr ON lri.request_id = lr.id
    WHERE lr.branch_id = ? AND DATE(lri.completed_at) = ?
");
$stmt->execute([$user_branch_id, $today]);
$today_tests = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// 5. Total Tests (All Time)
$stmt = $db->prepare("
    SELECT COUNT(*) as count 
    FROM lab_request_items lri
    JOIN lab_requests lr ON lri.request_id = lr.id
    WHERE lr.branch_id = ?
");
$stmt->execute([$user_branch_id]);
$total_tests = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// 6. Total Requests
$stmt = $db->prepare("SELECT COUNT(*) as count FROM lab_requests WHERE branch_id = ?");
$stmt->execute([$user_branch_id]);
$total_requests = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// 7. Completion Rate
$stmt = $db->prepare("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed
    FROM lab_requests 
    WHERE branch_id = ?
");
$stmt->execute([$user_branch_id]);
$rate_data = $stmt->fetch(PDO::FETCH_ASSOC);
$total_requests_all = $rate_data['total'] ?? 0;
$completed_requests = $rate_data['completed'] ?? 0;
$completion_rate = $total_requests_all > 0 ? round(($completed_requests / $total_requests_all) * 100, 1) : 0;

// 8. Recent Requests (Last 10)
$stmt = $db->prepare("
    SELECT lr.*, 
           p.full_name as patient_name, p.patient_id,
           u.full_name as doctor_name,
           (SELECT COUNT(*) FROM lab_request_items WHERE request_id = lr.id) as test_count,
           (SELECT COUNT(*) FROM lab_request_items WHERE request_id = lr.id AND status = 'completed') as completed_count
    FROM lab_requests lr
    JOIN patients p ON lr.patient_id = p.id
    JOIN users u ON lr.doctor_id = u.id
    WHERE lr.branch_id = ?
    ORDER BY lr.requested_at DESC
    LIMIT 10
");
$stmt->execute([$user_branch_id]);
$recent_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 9. Daily Tests Chart (Last 7 days)
$daily_labels = [];
$daily_tests = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $daily_labels[] = date('D', strtotime($date));
    
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM lab_request_items lri
        JOIN lab_requests lr ON lri.request_id = lr.id
        WHERE lr.branch_id = ? AND DATE(lri.completed_at) = ?
    ");
    $stmt->execute([$user_branch_id, $date]);
    $daily_tests[] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
}

// 10. Monthly Tests Chart (Last 6 months)
$monthly_labels = [];
$monthly_tests = [];
for ($i = 5; $i >= 0; $i--) {
    $month = date('M', strtotime("-$i months"));
    $monthly_labels[] = $month;
    
    $start = date('Y-m-01', strtotime("-$i months"));
    $end = date('Y-m-t', strtotime("-$i months"));
    
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM lab_request_items lri
        JOIN lab_requests lr ON lri.request_id = lr.id
        WHERE lr.branch_id = ? AND DATE(lri.completed_at) BETWEEN ? AND ?
    ");
    $stmt->execute([$user_branch_id, $start, $end]);
    $monthly_tests[] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
}

// 11. Most Requested Tests
$stmt = $db->prepare("
    SELECT lri.test_name, COUNT(*) as count 
    FROM lab_request_items lri
    JOIN lab_requests lr ON lri.request_id = lr.id
    WHERE lr.branch_id = ? AND lri.status = 'completed'
    GROUP BY lri.test_name
    ORDER BY count DESC
    LIMIT 5
");
$stmt->execute([$user_branch_id]);
$most_requested = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// CREATE DATA HASH FOR CHANGE DETECTION
// ================================================================
$data_array = [
    'pending' => $pending,
    'in_progress' => $in_progress,
    'completed_today' => $completed_today,
    'today_tests' => $today_tests,
    'total_tests' => $total_tests,
    'total_requests' => $total_requests,
    'completion_rate' => $completion_rate,
    'daily_tests' => $daily_tests,
    'monthly_tests' => $monthly_tests,
    'recent_count' => count($recent_requests),
    'most_requested_count' => count($most_requested)
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
        'stats' => [
            'pending' => $pending,
            'in_progress' => $in_progress,
            'completed_today' => $completed_today,
            'today_tests' => $today_tests,
            'total_tests' => $total_tests,
            'total_requests' => $total_requests,
            'completion_rate' => $completion_rate
        ],
        'charts' => [
            'daily_labels' => $daily_labels,
            'daily_tests' => $daily_tests,
            'monthly_labels' => $monthly_labels,
            'monthly_tests' => $monthly_tests
        ],
        'lists' => [
            'recent_requests' => $recent_requests,
            'most_requested' => $most_requested
        ],
        'timestamp' => date('Y-m-d H:i:s')
    ]
]);
?>