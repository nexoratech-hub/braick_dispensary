<?php
// ================================================================
// FILE: frontend/pages/laboratory/get_sidebar_stats.php
// LABORATORY - SIDEBAR STATS (AJAX API)
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
// FETCH SIDEBAR STATISTICS
// ================================================================

// 1. PENDING: FROM lab_tests (status NULL or 'pending') + lab_requests (status 'pending')
$stmt = $db->prepare("
    SELECT COUNT(*) as count FROM lab_tests 
    WHERE branch_id = ? AND (status IS NULL OR status = 'pending' OR status = '')
");
$stmt->execute([$user_branch_id]);
$pending_tests = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

$stmt = $db->prepare("
    SELECT COUNT(*) as count FROM lab_requests 
    WHERE branch_id = ? AND status = 'pending'
");
$stmt->execute([$user_branch_id]);
$pending_requests = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

$pending = $pending_tests + $pending_requests;

// 2. IN PROGRESS: FROM lab_requests (status 'accepted' or 'in_progress')
$stmt = $db->prepare("
    SELECT COUNT(*) as count FROM lab_requests 
    WHERE branch_id = ? AND status IN ('accepted', 'in_progress')
");
$stmt->execute([$user_branch_id]);
$in_progress = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// 3. COMPLETED: FROM lab_requests (completed today)
$stmt = $db->prepare("
    SELECT COUNT(*) as count FROM lab_requests 
    WHERE branch_id = ? AND status = 'completed' AND DATE(completed_at) = CURDATE()
");
$stmt->execute([$user_branch_id]);
$completed = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// 4. TODAY'S TESTS: FROM lab_tests + lab_request_items
$stmt = $db->prepare("
    SELECT COUNT(*) as count FROM lab_tests 
    WHERE branch_id = ? AND status = 'completed' AND DATE(completed_at) = CURDATE()
");
$stmt->execute([$user_branch_id]);
$tests_today = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

$stmt = $db->prepare("
    SELECT COUNT(*) as count 
    FROM lab_request_items lri
    JOIN lab_requests lr ON lri.request_id = lr.id
    WHERE lr.branch_id = ? AND lri.status = 'completed' AND DATE(lri.completed_at) = CURDATE()
");
$stmt->execute([$user_branch_id]);
$items_today = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

$today_tests = $tests_today + $items_today;

// ================================================================
// RETURN JSON
// ================================================================
header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'pending' => $pending,
    'in_progress' => $in_progress,
    'completed' => $completed,
    'today_tests' => $today_tests,
    'timestamp' => date('Y-m-d H:i:s')
]);
?>