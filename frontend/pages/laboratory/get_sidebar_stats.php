<?php
// ================================================================
// FILE: frontend/pages/laboratory/get_sidebar_stats.php
// LABORATORY - SIDEBAR STATS (AJAX API)
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
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => 'Unauthorized',
        'redirect' => '../login.php'
    ]);
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
// FETCH SIDEBAR STATISTICS - FROM lab_tests ONLY
// ================================================================

// 1. PENDING: FROM lab_tests (status NULL or 'pending')
$stmt = $db->prepare("
    SELECT COUNT(*) as count FROM lab_tests 
    WHERE branch_id = ? AND (status IS NULL OR status = 'pending' OR status = '')
");
$stmt->execute([$user_branch_id]);
$pending_tests = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// 2. IN PROGRESS: FROM lab_tests (status 'in_progress')
$stmt = $db->prepare("
    SELECT COUNT(*) as count FROM lab_tests 
    WHERE branch_id = ? AND status = 'in_progress'
");
$stmt->execute([$user_branch_id]);
$in_progress = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// 3. COMPLETED TODAY: FROM lab_tests (status 'completed' AND completed_at = today)
$stmt = $db->prepare("
    SELECT COUNT(*) as count FROM lab_tests 
    WHERE branch_id = ? AND status = 'completed' AND DATE(completed_at) = CURDATE()
");
$stmt->execute([$user_branch_id]);
$completed_today = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// 4. TODAY'S TESTS (all tests created today)
$stmt = $db->prepare("
    SELECT COUNT(*) as count FROM lab_tests 
    WHERE branch_id = ? AND DATE(created_at) = CURDATE()
");
$stmt->execute([$user_branch_id]);
$today_tests = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// 5. TOTAL PENDING (for badge)
$total_pending = $pending_tests;

// 6. TOTAL COMPLETED (all time)
$stmt = $db->prepare("
    SELECT COUNT(*) as count FROM lab_tests 
    WHERE branch_id = ? AND status = 'completed'
");
$stmt->execute([$user_branch_id]);
$total_completed = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// ================================================================
// RETURN JSON
// ================================================================
header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'pending' => $pending_tests,
    'in_progress' => $in_progress,
    'completed_today' => $completed_today,
    'today_tests' => $today_tests,
    'total_pending' => $total_pending,
    'total_completed' => $total_completed,
    'timestamp' => date('Y-m-d H:i:s')
]);
?>