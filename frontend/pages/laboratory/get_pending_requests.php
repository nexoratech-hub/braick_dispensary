<?php
// ================================================================
// FILE: frontend/pages/laboratory/get_pending_requests.php
// LABORATORY - GET PENDING ITEMS (AJAX API)
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
    // User is not logged in - return error JSON
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
// INCLUDE DATABASE - CORRECT PATH
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
// GET FILTERS
// ================================================================
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$date_filter = isset($_GET['date']) ? $_GET['date'] : '';

// ================================================================
// GET PENDING TESTS FROM lab_tests
// ================================================================
$pending_tests_query = "
    SELECT 
        lt.id,
        lt.visit_id,
        lt.test_name,
        lt.test_type,
        lt.status,
        lt.created_at,
        lt.branch_id,
        p.full_name as patient_name,
        p.patient_id,
        COALESCE(u.full_name, 'Not Assigned') as doctor_name,
        v.visit_number,
        'test' as source_type
    FROM lab_tests lt
    JOIN visits v ON lt.visit_id = v.id
    JOIN patients p ON v.patient_id = p.id
    LEFT JOIN users u ON lt.doctor_id = u.id
    WHERE lt.branch_id = ? AND (lt.status IS NULL OR lt.status = 'pending' OR lt.status = '')
";

$params = [$user_branch_id];

if (!empty($search)) {
    $pending_tests_query .= " AND (p.full_name LIKE ? OR p.patient_id LIKE ? OR lt.test_name LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

if (!empty($date_filter)) {
    $pending_tests_query .= " AND DATE(lt.created_at) = ?";
    $params[] = $date_filter;
}

$pending_tests_query .= " ORDER BY lt.created_at ASC";

$stmt = $db->prepare($pending_tests_query);
$stmt->execute($params);
$pending_tests = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// GET PENDING REQUESTS FROM lab_requests
// ================================================================
$pending_requests_query = "
    SELECT 
        lr.id,
        lr.request_number,
        lr.visit_id,
        lr.patient_id,
        lr.status,
        lr.requested_at,
        lr.branch_id,
        p.full_name as patient_name,
        p.patient_id,
        COALESCE(u.full_name, 'Not Assigned') as doctor_name,
        v.visit_number,
        (SELECT COUNT(*) FROM lab_request_items WHERE request_id = lr.id) as total_tests,
        'request' as source_type
    FROM lab_requests lr
    JOIN patients p ON lr.patient_id = p.id
    LEFT JOIN visits v ON lr.visit_id = v.id
    LEFT JOIN users u ON lr.doctor_id = u.id
    WHERE lr.branch_id = ? AND lr.status = 'pending'
";

$params2 = [$user_branch_id];

if (!empty($search)) {
    $pending_requests_query .= " AND (p.full_name LIKE ? OR p.patient_id LIKE ? OR lr.request_number LIKE ?)";
    $search_term = "%$search%";
    $params2[] = $search_term;
    $params2[] = $search_term;
    $params2[] = $search_term;
}

if (!empty($date_filter)) {
    $pending_requests_query .= " AND DATE(lr.requested_at) = ?";
    $params2[] = $date_filter;
}

$pending_requests_query .= " ORDER BY lr.requested_at ASC";

$stmt = $db->prepare($pending_requests_query);
$stmt->execute($params2);
$pending_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// MERGE
// ================================================================
$items = array_merge($pending_tests, $pending_requests);
$total_pending = count($items);

// ================================================================
// GET COUNTS
// ================================================================
$stmt = $db->prepare("SELECT COUNT(*) as count FROM lab_requests WHERE branch_id = ? AND status IN ('accepted', 'in_progress')");
$stmt->execute([$user_branch_id]);
$in_progress_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

$today = date('Y-m-d');
$stmt = $db->prepare("SELECT COUNT(*) as count FROM lab_requests WHERE branch_id = ? AND status = 'completed' AND DATE(completed_at) = ?");
$stmt->execute([$user_branch_id, $today]);
$completed_today_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

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
// CREATE HASH
// ================================================================
$data_array = [
    'items' => $items,
    'total_pending' => $total_pending,
    'in_progress_count' => $in_progress_count,
    'completed_today_count' => $completed_today_count,
    'unread_notifications' => $unread_notifications
];
$hash = md5(json_encode($data_array));

// ================================================================
// RETURN JSON
// ================================================================
header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'hash' => $hash,
    'user' => [
        'id' => $user_id,
        'name' => $user_full_name,
        'role' => $user_role,
        'branch_id' => $user_branch_id,
        'branch_name' => $user_branch_name,
        'username' => $user_username
    ],
    'items' => $items,
    'total_pending' => $total_pending,
    'in_progress_count' => $in_progress_count,
    'completed_today_count' => $completed_today_count,
    'unread_notifications' => $unread_notifications,
    'total' => count($items),
    'timestamp' => date('Y-m-d H:i:s')
]);
?>