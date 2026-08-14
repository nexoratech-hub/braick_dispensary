<?php
// ================================================================
// FILE: frontend/pages/doctor/get_lab_results.php
// DOCTOR - GET LAB RESULTS (AJAX API)
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
    // Return JSON error for AJAX requests
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => 'Not logged in',
        'redirect' => '../login.php'
    ]);
    exit;
}

// ================================================================
// CHECK IF USER IS DOCTOR OR ADMIN
// ================================================================
if ($_SESSION['role'] !== 'doctor' && $_SESSION['role'] !== 'admin') {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => 'Unauthorized access'
    ]);
    exit;
}

// ================================================================
// GET USER INFO FROM SESSION
// ================================================================
$user_id = $_SESSION['user_id'];
$user_full_name = $_SESSION['full_name'] ?? 'Doctor';
$user_role = $_SESSION['role'];
$doctor_branch_id = $_SESSION['branch_id'] ?? 1;
$is_admin = ($_SESSION['role'] === 'admin');

// ================================================================
// GET FILTERS
// ================================================================
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$date_filter = isset($_GET['date']) ? $_GET['date'] : '';
$patient_filter = isset($_GET['patient']) ? (int)$_GET['patient'] : 0;

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
// BUILD QUERY
// ================================================================
if ($is_admin) {
    // Admin can see all lab tests for all doctors
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
        WHERE lt.branch_id = ?
    ";
    $params = [$doctor_branch_id];
} else {
    // Doctor can only see their own lab tests
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
    $params = [$doctor_branch_id, $user_id];
}

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

if ($is_admin) {
    // Admin statistics - all doctors
    $stmt = $db->prepare("
        SELECT COUNT(*) as count FROM lab_tests 
        WHERE branch_id = ? AND (status IS NULL OR status = 'pending')
    ");
    $stmt->execute([$doctor_branch_id]);
    $pending_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

    $stmt = $db->prepare("
        SELECT COUNT(*) as count FROM lab_tests 
        WHERE branch_id = ? AND status = 'in_progress'
    ");
    $stmt->execute([$doctor_branch_id]);
    $in_progress_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

    $stmt = $db->prepare("
        SELECT COUNT(*) as count FROM lab_tests 
        WHERE branch_id = ? AND status = 'completed'
    ");
    $stmt->execute([$doctor_branch_id]);
    $completed_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

    $today = date('Y-m-d');
    $stmt = $db->prepare("
        SELECT COUNT(*) as count FROM lab_tests 
        WHERE branch_id = ? AND status = 'completed' AND DATE(completed_at) = ?
    ");
    $stmt->execute([$doctor_branch_id, $today]);
    $completed_today_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

    $stmt = $db->prepare("
        SELECT COUNT(*) as count FROM lab_tests 
        WHERE branch_id = ?
    ");
    $stmt->execute([$doctor_branch_id]);
    $total_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
} else {
    // Doctor statistics - only their tests
    $stmt = $db->prepare("
        SELECT COUNT(*) as count FROM lab_tests 
        WHERE branch_id = ? AND doctor_id = ? AND (status IS NULL OR status = 'pending')
    ");
    $stmt->execute([$doctor_branch_id, $user_id]);
    $pending_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

    $stmt = $db->prepare("
        SELECT COUNT(*) as count FROM lab_tests 
        WHERE branch_id = ? AND doctor_id = ? AND status = 'in_progress'
    ");
    $stmt->execute([$doctor_branch_id, $user_id]);
    $in_progress_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

    $stmt = $db->prepare("
        SELECT COUNT(*) as count FROM lab_tests 
        WHERE branch_id = ? AND doctor_id = ? AND status = 'completed'
    ");
    $stmt->execute([$doctor_branch_id, $user_id]);
    $completed_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

    $today = date('Y-m-d');
    $stmt = $db->prepare("
        SELECT COUNT(*) as count FROM lab_tests 
        WHERE branch_id = ? AND doctor_id = ? AND status = 'completed' AND DATE(completed_at) = ?
    ");
    $stmt->execute([$doctor_branch_id, $user_id, $today]);
    $completed_today_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

    $stmt = $db->prepare("
        SELECT COUNT(*) as count FROM lab_tests 
        WHERE branch_id = ? AND doctor_id = ?
    ");
    $stmt->execute([$doctor_branch_id, $user_id]);
    $total_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
}

// Count tests with results
$with_results = 0;
foreach ($lab_tests as $test) {
    if (!empty($test['results'])) {
        $with_results++;
    }
}

// ================================================================
// CREATE HASH FOR CHANGE DETECTION
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
    'timestamp' => date('Y-m-d H:i:s'),
    'user_role' => $user_role,
    'is_admin' => $is_admin
]);
?>