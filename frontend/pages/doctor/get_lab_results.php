<?php
// ================================================================
// FILE: frontend/pages/doctor/get_lab_results.php
// DOCTOR - GET LAB RESULTS (AJAX API)
// BRAICK DISPENSARY
// ================================================================

session_start();

// ================================================================
// IF NO SESSION, USE DR. JOHN MUSHI (ID: 5) AS DEFAULT
// ================================================================
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    $_SESSION['user_id'] = 5;
    $_SESSION['doctor_id'] = 5;
    $_SESSION['full_name'] = 'Dr. John Mushi';
    $_SESSION['role'] = 'doctor';
    $_SESSION['branch_id'] = 1;
    $_SESSION['branch_name'] = 'Dodoma';
    $_SESSION['username'] = 'dr.john';
}

$doctor_id = $_SESSION['user_id'] ?? $_SESSION['doctor_id'] ?? 5;
$doctor_branch_id = $_SESSION['branch_id'] ?? 1;

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
$patient_filter = isset($_GET['patient']) ? (int)$_GET['patient'] : 0;

// ================================================================
// BUILD QUERY
// ================================================================
$query = "
    SELECT lt.*, 
           p.full_name as patient_name, p.patient_id, p.phone,
           u.full_name as doctor_name, u.specialty,
           v.visit_number,
           lab.full_name as lab_technician_name
    FROM lab_tests lt
    JOIN visits v ON lt.visit_id = v.id
    JOIN patients p ON v.patient_id = p.id
    LEFT JOIN users u ON lt.doctor_id = u.id
    LEFT JOIN users lab ON lt.lab_technician_id = lab.id
    WHERE lt.branch_id = ? AND lt.doctor_id = ?
";

$params = [$doctor_branch_id, $doctor_id];

if (!empty($status_filter)) {
    if ($status_filter === 'pending') {
        $query .= " AND (lt.status IS NULL OR lt.status = 'pending')";
    } else {
        $query .= " AND lt.status = ?";
        $params[] = $status_filter;
    }
} else {
    $query .= " AND (lt.status IS NULL OR lt.status != 'cancelled')";
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

if ($patient_filter > 0) {
    $query .= " AND p.id = ?";
    $params[] = $patient_filter;
}

$query .= " ORDER BY lt.status DESC, lt.created_at DESC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$lab_tests = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// GET STATISTICS
// ================================================================

$stmt = $db->prepare("
    SELECT COUNT(*) as count FROM lab_tests 
    WHERE branch_id = ? AND doctor_id = ? AND (status IS NULL OR status = 'pending')
");
$stmt->execute([$doctor_branch_id, $doctor_id]);
$pending_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

$stmt = $db->prepare("
    SELECT COUNT(*) as count FROM lab_tests 
    WHERE branch_id = ? AND doctor_id = ? AND status = 'in_progress'
");
$stmt->execute([$doctor_branch_id, $doctor_id]);
$in_progress_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

$stmt = $db->prepare("
    SELECT COUNT(*) as count FROM lab_tests 
    WHERE branch_id = ? AND doctor_id = ? AND status = 'completed'
");
$stmt->execute([$doctor_branch_id, $doctor_id]);
$completed_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

$today = date('Y-m-d');
$stmt = $db->prepare("
    SELECT COUNT(*) as count FROM lab_tests 
    WHERE branch_id = ? AND doctor_id = ? AND status = 'completed' AND DATE(completed_at) = ?
");
$stmt->execute([$doctor_branch_id, $doctor_id, $today]);
$completed_today_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

$stmt = $db->prepare("
    SELECT COUNT(*) as count FROM lab_tests 
    WHERE branch_id = ? AND doctor_id = ?
");
$stmt->execute([$doctor_branch_id, $doctor_id]);
$total_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// Count tests with results
$with_results = 0;
foreach ($lab_tests as $test) {
    if (!empty($test['results'])) {
        $with_results++;
    }
}

// ================================================================
// CREATE HASH
// ================================================================
$data_array = [
    'tests' => $lab_tests,
    'pending_count' => $pending_count,
    'in_progress_count' => $in_progress_count,
    'completed_count' => $completed_count,
    'completed_today_count' => $completed_today_count,
    'total_count' => $total_count,
    'with_results' => $with_results
];
$hash = md5(json_encode($data_array));

// ================================================================
// RETURN JSON
// ================================================================
header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'hash' => $hash,
    'tests' => $lab_tests,
    'pending_count' => $pending_count,
    'in_progress_count' => $in_progress_count,
    'completed_count' => $completed_count,
    'completed_today_count' => $completed_today_count,
    'total_count' => $total_count,
    'with_results' => $with_results,
    'total' => count($lab_tests),
    'timestamp' => date('Y-m-d H:i:s')
]);
?>