<?php
// ================================================================
// FILE: frontend/pages/laboratory/get_in_progress_requests.php
// LABORATORY - GET IN PROGRESS REQUESTS (ALL TESTS)
// USING NEW DATABASE: dispensary_db
// FIXED: Login session - no default user bypass
// BRAICK DISPENSARY
// ================================================================

// ================================================================
// START SESSION
// ================================================================
session_start();

// ================================================================
// CHECK SESSION - REDIRECT TO LOGIN IF NOT LABORATORY
// ================================================================
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'laboratory') {
    header('Location: ../login.php');
    exit;
}

// ================================================================
// GET USER DATA FROM SESSION
// ================================================================
$user_id = $_SESSION['user_id'];
$user_branch_id = $_SESSION['branch_id'] ?? 1;

// ================================================================
// INCLUDE DATABASE - NEW DATABASE
// ================================================================
require_once __DIR__ . '/../../../backend/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die(json_encode(['success' => false, 'error' => 'Database connection failed']));
}

// ================================================================
// GET FILTERS
// ================================================================
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$date_filter = isset($_GET['date']) ? $_GET['date'] : '';
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'oldest';

// ================================================================
// BUILD QUERY - Show ALL accepted/in_progress requests
// ================================================================
$query = "
    SELECT 
        lr.*, 
        p.full_name as patient_name, 
        p.patient_id as patient_code,
        p.phone,
        p.gender,
        COALESCE(u.full_name, 'Not Assigned') as doctor_name,
        u.specialty,
        (SELECT COUNT(*) FROM lab_request_items WHERE request_id = lr.id) as total_tests,
        (SELECT COUNT(*) FROM lab_request_items WHERE request_id = lr.id AND status = 'completed') as completed_tests,
        (SELECT COUNT(*) FROM lab_request_items WHERE request_id = lr.id AND status = 'in_progress') as in_progress_tests,
        (SELECT COUNT(*) FROM lab_request_items WHERE request_id = lr.id AND status = 'pending') as pending_tests,
        TIMESTAMPDIFF(MINUTE, lr.accepted_at, NOW()) as processing_time,
        lr.doctor_id,
        b.name as branch_name
    FROM lab_requests lr
    JOIN patients p ON lr.patient_id = p.id
    LEFT JOIN users u ON lr.doctor_id = u.id
    LEFT JOIN branches b ON lr.branch_id = b.id
    WHERE lr.branch_id = ? AND lr.status IN ('accepted', 'in_progress')
";

$params = [$user_branch_id];

if (!empty($search)) {
    $query .= " AND (p.full_name LIKE ? OR p.patient_id LIKE ? OR lr.request_number LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

if (!empty($date_filter)) {
    $query .= " AND DATE(lr.requested_at) = ?";
    $params[] = $date_filter;
}

// Sort by
switch ($sort_by) {
    case 'newest':
        $query .= " ORDER BY lr.requested_at DESC";
        break;
    case 'most_tests':
        $query .= " ORDER BY total_tests DESC";
        break;
    case 'longest_waiting':
        $query .= " ORDER BY processing_time DESC";
        break;
    default:
        $query .= " ORDER BY lr.requested_at ASC";
        break;
}

$stmt = $db->prepare($query);
$stmt->execute($params);
$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// GET COUNTS
// ================================================================

// In Progress Count
$stmt = $db->prepare("
    SELECT COUNT(*) as count 
    FROM lab_requests 
    WHERE branch_id = ? AND status IN ('accepted', 'in_progress')
");
$stmt->execute([$user_branch_id]);
$in_progress_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// Pending Count
$stmt = $db->prepare("
    SELECT COUNT(*) as count 
    FROM lab_requests 
    WHERE branch_id = ? AND status = 'pending'
");
$stmt->execute([$user_branch_id]);
$pending_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// Completed Today
$today = date('Y-m-d');
$stmt = $db->prepare("
    SELECT COUNT(*) as count 
    FROM lab_requests 
    WHERE branch_id = ? AND status = 'completed' AND DATE(completed_at) = ?
");
$stmt->execute([$user_branch_id, $today]);
$completed_today_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// Average Processing Time
$stmt = $db->prepare("
    SELECT AVG(TIMESTAMPDIFF(MINUTE, accepted_at, completed_at)) as avg_time 
    FROM lab_requests 
    WHERE branch_id = ? AND status = 'completed' AND accepted_at IS NOT NULL AND completed_at IS NOT NULL
");
$stmt->execute([$user_branch_id]);
$avg_processing_time = round($stmt->fetch(PDO::FETCH_ASSOC)['avg_time'] ?? 0);

// Calculate totals
$total_tests_all = 0;
$completed_tests_all = 0;
foreach ($requests as $req) {
    $total_tests_all += $req['total_tests'] ?? 0;
    $completed_tests_all += $req['completed_tests'] ?? 0;
}

// ================================================================
// CREATE HASH FOR AUTO-UPDATE
// ================================================================
$data_array = [
    'requests' => $requests,
    'in_progress_count' => $in_progress_count,
    'pending_count' => $pending_count,
    'completed_today_count' => $completed_today_count,
    'avg_processing_time' => $avg_processing_time,
    'total_tests_all' => $total_tests_all,
    'completed_tests_all' => $completed_tests_all
];
$hash = md5(json_encode($data_array));

// ================================================================
// RETURN JSON
// ================================================================
header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'hash' => $hash,
    'requests' => $requests,
    'in_progress_count' => $in_progress_count,
    'pending_count' => $pending_count,
    'completed_today_count' => $completed_today_count,
    'avg_processing_time' => $avg_processing_time,
    'total_tests_all' => $total_tests_all,
    'completed_tests_all' => $completed_tests_all,
    'total' => count($requests),
    'timestamp' => date('Y-m-d H:i:s'),
    'branch_id' => $user_branch_id
]);
?>