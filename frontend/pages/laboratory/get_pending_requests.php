<?php
// ================================================================
// FILE: frontend/pages/laboratory/get_pending_requests.php
// LABORATORY - GET PENDING ITEMS (AJAX API)
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

$user_branch_id = $_SESSION['branch_id'] ?? 1;

// ================================================================
// INCLUDE DATABASE
// ================================================================
require_once 'C:/xampp/htdocs/dispensary_system/backend/config/database.php';
$db = Database::getInstance()->getConnection();

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
// CREATE HASH
// ================================================================
$data_array = [
    'items' => $items,
    'total_pending' => $total_pending,
    'in_progress_count' => $in_progress_count,
    'completed_today_count' => $completed_today_count
];
$hash = md5(json_encode($data_array));

// ================================================================
// RETURN JSON
// ================================================================
header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'hash' => $hash,
    'items' => $items,
    'total_pending' => $total_pending,
    'in_progress_count' => $in_progress_count,
    'completed_today_count' => $completed_today_count,
    'total' => count($items),
    'timestamp' => date('Y-m-d H:i:s')
]);
?>