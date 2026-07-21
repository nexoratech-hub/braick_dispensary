<?php
// ================================================================
// FILE: frontend/pages/laboratory/get_lab_tests_stats.php
// LABORATORY - GET LAB TESTS STATS (USING lab_tests TABLE)
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
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$date_filter = isset($_GET['date']) ? $_GET['date'] : '';

// ================================================================
// BUILD QUERY - Using lab_tests table
// ================================================================
$query = "
    SELECT lt.*, 
           p.full_name as patient_name, p.patient_id, p.phone,
           u.full_name as doctor_name, u.specialty,
           TIMESTAMPDIFF(MINUTE, lt.created_at, NOW()) as waiting_time
    FROM lab_tests lt
    JOIN visits v ON lt.visit_id = v.id
    JOIN patients p ON v.patient_id = p.id
    JOIN users u ON lt.doctor_id = u.id
    WHERE lt.branch_id = ?
";

$params = [$user_branch_id];

if (!empty($status_filter)) {
    // Handle NULL status as 'pending'
    if ($status_filter === 'pending') {
        $query .= " AND (lt.status IS NULL OR lt.status = 'pending')";
    } else {
        $query .= " AND lt.status = ?";
        $params[] = $status_filter;
    }
} else {
    // Default: show all except completed
    $query .= " AND (lt.status IS NULL OR lt.status = 'pending' OR lt.status = 'in_progress')";
}

if (!empty($search)) {
    $query .= " AND (p.full_name LIKE ? OR p.patient_id LIKE ? OR lt.test_name LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

if (!empty($date_filter)) {
    $query .= " AND DATE(lt.created_at) = ?";
    $params[] = $date_filter;
}

$query .= " ORDER BY 
    CASE 
        WHEN lt.status IS NULL OR lt.status = 'pending' THEN 1 
        WHEN lt.status = 'in_progress' THEN 2 
        WHEN lt.status = 'completed' THEN 3 
        ELSE 4 
    END, 
    lt.created_at ASC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$tests = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// GET COUNTS
// ================================================================

// Pending (status NULL or 'pending')
$stmt = $db->prepare("
    SELECT COUNT(*) as count FROM lab_tests 
    WHERE branch_id = ? AND (status IS NULL OR status = 'pending')
");
$stmt->execute([$user_branch_id]);
$pending_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// In Progress
$stmt = $db->prepare("
    SELECT COUNT(*) as count FROM lab_tests 
    WHERE branch_id = ? AND status = 'in_progress'
");
$stmt->execute([$user_branch_id]);
$in_progress_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// Completed
$stmt = $db->prepare("
    SELECT COUNT(*) as count FROM lab_tests 
    WHERE branch_id = ? AND status = 'completed'
");
$stmt->execute([$user_branch_id]);
$completed_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// Completed Today
$today = date('Y-m-d');
$stmt = $db->prepare("
    SELECT COUNT(*) as count FROM lab_tests 
    WHERE branch_id = ? AND status = 'completed' AND DATE(completed_at) = ?
");
$stmt->execute([$user_branch_id, $today]);
$completed_today_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// Total
$stmt = $db->prepare("SELECT COUNT(*) as count FROM lab_tests WHERE branch_id = ?");
$stmt->execute([$user_branch_id]);
$total_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// ================================================================
// CREATE HASH FOR CHANGE DETECTION
// ================================================================
$data_array = [
    'tests' => $tests,
    'pending_count' => $pending_count,
    'in_progress_count' => $in_progress_count,
    'completed_count' => $completed_count,
    'completed_today_count' => $completed_today_count,
    'total_count' => $total_count
];
$hash = md5(json_encode($data_array));

// ================================================================
// RETURN JSON
// ================================================================
header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'hash' => $hash,
    'tests' => $tests,
    'pending_count' => $pending_count,
    'in_progress_count' => $in_progress_count,
    'completed_count' => $completed_count,
    'completed_today_count' => $completed_today_count,
    'total_count' => $total_count,
    'total' => count($tests),
    'timestamp' => date('Y-m-d H:i:s')
]);
?>