<?php
// ================================================================
// FILE: backend/api/laboratory_sidebar_ajax.php
// LABORATORY SIDEBAR - DIRECT AJAX (NO EXTERNAL API)
// Gets data directly from database with AJAX
// ================================================================

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check login
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Check role
if (!in_array($_SESSION['role'], ['laboratory', 'admin'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized role']);
    exit;
}

// Get branch ID
$branch_id = isset($_POST['branch_id']) ? (int)$_POST['branch_id'] : $_SESSION['branch_id'] ?? 0;
$hash = isset($_POST['hash']) ? $_POST['hash'] : '';
$force_update = isset($_POST['force_update']) && $_POST['force_update'] == '1';

if ($branch_id <= 0) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid branch ID']);
    exit;
}

// Include database
require_once __DIR__ . '/../config/database.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    exit;
}

// ================================================================
// GET ALL STATISTICS - DIRECT FROM DATABASE
// ================================================================
$pending_count = 0;
$in_progress_count = 0;
$completed_count = 0;
$today_tests = 0;
$total_tests = 0;

try {
    // Pending tests
    $stmt = $db->prepare("
        SELECT COUNT(*) as count FROM lab_tests 
        WHERE branch_id = ? AND (status IS NULL OR status = '' OR status = 'pending')
    ");
    $stmt->execute([$branch_id]);
    $pending_count = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
    
    // In Progress tests
    $stmt = $db->prepare("
        SELECT COUNT(*) as count FROM lab_tests 
        WHERE branch_id = ? AND status = 'in_progress'
    ");
    $stmt->execute([$branch_id]);
    $in_progress_count = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
    
    // Completed tests
    $stmt = $db->prepare("
        SELECT COUNT(*) as count FROM lab_tests 
        WHERE branch_id = ? AND status = 'completed'
    ");
    $stmt->execute([$branch_id]);
    $completed_count = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
    
    // Today's completed tests
    $stmt = $db->prepare("
        SELECT COUNT(*) as count FROM lab_tests 
        WHERE branch_id = ? AND status = 'completed' AND DATE(completed_at) = CURDATE()
    ");
    $stmt->execute([$branch_id]);
    $today_tests = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
    
    // Total tests
    $total_tests = $pending_count + $in_progress_count + $completed_count;
    
} catch (Exception $e) {
    error_log("Laboratory sidebar stats error: " . $e->getMessage());
}

// ================================================================
// GENERATE HASH AND CHECK CHANGES
// ================================================================
$data = [
    'pending' => $pending_count,
    'in_progress' => $in_progress_count,
    'completed' => $completed_count,
    'today_tests' => $today_tests,
    'total' => $total_tests
];

$new_hash = md5(json_encode($data));
$has_changed = ($hash !== $new_hash || $force_update);

// ================================================================
// RESPONSE
// ================================================================
header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'has_changed' => $has_changed,
    'hash' => $new_hash,
    'data' => $data,
    'summary' => [
        'pending' => $pending_count,
        'in_progress' => $in_progress_count,
        'completed' => $completed_count,
        'today_tests' => $today_tests,
        'total' => $total_tests
    ],
    'timestamp' => date('H:i:s'),
    'branch_id' => $branch_id
]);
exit;
?>