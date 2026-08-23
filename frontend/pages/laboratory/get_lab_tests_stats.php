<?php
// ================================================================
// FILE: frontend/pages/laboratory/get_lab_tests_stats.php
// LABORATORY - GET LAB TESTS STATS (USING lab_tests TABLE)
// ✅ USING NEW DATABASE: dispensary_db
// ✅ ONLY ONE TABLE: lab_tests
// FIXED: Login session - no default user bypass
// BRAICK DISPENSARY
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
$user_branch_id = $_SESSION['branch_id'] ?? 1;

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
// GET FILTERS
// ================================================================
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$date_filter = isset($_GET['date']) ? $_GET['date'] : '';

// ================================================================
// BUILD QUERY - Using lab_tests table
// ================================================================
$query = "
    SELECT 
        lt.id as test_id,
        lt.visit_id,
        lt.doctor_id,
        lt.lab_technician_id,
        lt.test_name,
        lt.test_price,
        lt.test_type,
        lt.sample_type,
        lt.test_date,
        lt.results,
        lt.reference_range,
        lt.status,
        lt.bill_created,
        lt.notes,
        lt.technician_id,
        lt.branch_id,
        lt.created_at,
        lt.completed_at,
        lt.updated_at,
        lt.formatted_result,
        lt.printed_at,
        lt.printed_by,
        p.full_name as patient_name,
        p.patient_id as patient_code,
        p.phone,
        p.gender,
        p.date_of_birth,
        p.blood_group,
        p.address,
        p.allergies,
        u.full_name as doctor_name,
        u.specialty as doctor_specialty,
        v.visit_number,
        v.visit_type,
        v.diagnosis,
        v.symptoms,
        v.status as visit_status,
        v.is_completed,
        TIMESTAMPDIFF(MINUTE, lt.created_at, NOW()) as waiting_time
    FROM lab_tests lt
    LEFT JOIN visits v ON lt.visit_id = v.id
    LEFT JOIN patients p ON v.patient_id = p.id
    LEFT JOIN users u ON lt.doctor_id = u.id
    WHERE lt.branch_id = ?
";

$params = [$user_branch_id];

if (!empty($status_filter)) {
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

// Status Distribution
$stmt = $db->prepare("
    SELECT status, COUNT(*) as count 
    FROM lab_tests 
    WHERE branch_id = ?
    GROUP BY status
");
$stmt->execute([$user_branch_id]);
$status_distribution = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
    'status_distribution' => $status_distribution,
    'timestamp' => date('Y-m-d H:i:s')
]);
?>