<?php
// ================================================================
// FILE: backend/api/get_lab_sidebar_stats.php
// LABORATORY SIDEBAR STATS API - REAL-TIME UPDATES
// BRAICK DISPENSARY
// ================================================================

// ================================================================
// HEADERS
// ================================================================
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET');
header('Access-Control-Allow-Headers: Content-Type');

// ================================================================
// START SESSION
// ================================================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ================================================================
// INCLUDE DATABASE
// ================================================================
require_once __DIR__ . '/../config/database.php';

// ================================================================
// RESPONSE HELPER
// ================================================================
function sendResponse($success, $data = null, $message = null, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode([
        'success' => $success,
        'data' => $data,
        'message' => $message,
        'timestamp' => date('Y-m-d H:i:s'),
        'timestamp_iso' => date('c')
    ]);
    exit;
}

// ================================================================
// AUTHENTICATION CHECK
// ================================================================
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    sendResponse(false, null, 'Unauthorized - Please login', 401);
}

$allowed_roles = ['laboratory', 'admin'];
if (!in_array($_SESSION['role'], $allowed_roles)) {
    sendResponse(false, null, 'Forbidden - Laboratory access required', 403);
}

// ================================================================
// GET PARAMETERS
// ================================================================
$branch_id = isset($_POST['branch_id']) ? (int)$_POST['branch_id'] : 0;
$client_hash = isset($_POST['hash']) ? $_POST['hash'] : '';
$force_update = isset($_POST['force_update']) ? (bool)$_POST['force_update'] : false;

// Support GET for debugging
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $branch_id = isset($_GET['branch_id']) ? (int)$_GET['branch_id'] : 0;
    $client_hash = isset($_GET['hash']) ? $_GET['hash'] : '';
    $force_update = isset($_GET['force_update']) ? (bool)$_GET['force_update'] : false;
}

// Validate branch_id - use session if not provided
if ($branch_id <= 0) {
    $branch_id = (int)$_SESSION['branch_id'] ?? 1;
}

// ================================================================
// CONNECT TO DATABASE
// ================================================================
try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    sendResponse(false, null, 'Database connection error: ' . $e->getMessage(), 500);
}

// ================================================================
// FETCH LAB STATS
// ================================================================
function getLabStats($db, $branch_id) {
    $stats = [
        // Test counts - main badges
        'pending' => 0,
        'in_progress' => 0,
        'completed' => 0,
        'cancelled' => 0,
        'total' => 0,
        
        // Today's stats
        'today_tests' => 0,
        'today_pending' => 0,
        'today_completed' => 0,
        'today_in_progress' => 0,
        
        // Week stats
        'week_tests' => 0,
        'week_completed' => 0,
        'week_pending' => 0,
        
        // Month stats
        'month_tests' => 0,
        'month_completed' => 0,
        
        // Equipment stats
        'equipment_used' => 0,
        'equipment_low_stock' => 0,
        'equipment_total' => 0,
        
        // Branch info
        'branch_name' => '',
        'branch_id' => $branch_id,
        
        // User info
        'user_name' => '',
        'user_role' => '',
        
        // Timestamps
        'last_updated' => date('Y-m-d H:i:s'),
        
        // Hash for change detection
        '_hash' => ''
    ];
    
    try {
        // ================================================================
        // 1. GET BRANCH NAME
        // ================================================================
        $stmt = $db->prepare("SELECT name FROM branches WHERE id = ? AND status = 'active'");
        $stmt->execute([$branch_id]);
        $branch = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($branch) {
            $stats['branch_name'] = $branch['name'];
        }
        
        // ================================================================
        // 2. GET USER INFO
        // ================================================================
        if (isset($_SESSION['user_id'])) {
            $stmt = $db->prepare("SELECT full_name, role FROM users WHERE id = ? AND status = 'active'");
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($user) {
                $stats['user_name'] = $user['full_name'] ?? '';
                $stats['user_role'] = $user['role'] ?? '';
            }
        }
        
        // ================================================================
        // 3. TEST COUNTS
        // ================================================================
        
        // Pending (status NULL, '', or 'pending')
        $stmt = $db->prepare("
            SELECT COUNT(*) as count FROM lab_tests 
            WHERE branch_id = ? AND (status IS NULL OR status = '' OR status = 'pending')
        ");
        $stmt->execute([$branch_id]);
        $stats['pending'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
        
        // In Progress
        $stmt = $db->prepare("
            SELECT COUNT(*) as count FROM lab_tests 
            WHERE branch_id = ? AND status = 'in_progress'
        ");
        $stmt->execute([$branch_id]);
        $stats['in_progress'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
        
        // Completed
        $stmt = $db->prepare("
            SELECT COUNT(*) as count FROM lab_tests 
            WHERE branch_id = ? AND status = 'completed'
        ");
        $stmt->execute([$branch_id]);
        $stats['completed'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
        
        // Cancelled
        $stmt = $db->prepare("
            SELECT COUNT(*) as count FROM lab_tests 
            WHERE branch_id = ? AND status = 'cancelled'
        ");
        $stmt->execute([$branch_id]);
        $stats['cancelled'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
        
        // Total
        $stats['total'] = $stats['pending'] + $stats['in_progress'] + $stats['completed'] + $stats['cancelled'];
        
        // ================================================================
        // 4. TODAY'S STATS
        // ================================================================
        
        // Today's tests (all)
        $stmt = $db->prepare("
            SELECT COUNT(*) as count FROM lab_tests 
            WHERE branch_id = ? AND DATE(created_at) = CURDATE()
        ");
        $stmt->execute([$branch_id]);
        $stats['today_tests'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
        
        // Today's pending
        $stmt = $db->prepare("
            SELECT COUNT(*) as count FROM lab_tests 
            WHERE branch_id = ? AND DATE(created_at) = CURDATE() 
            AND (status IS NULL OR status = '' OR status = 'pending')
        ");
        $stmt->execute([$branch_id]);
        $stats['today_pending'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
        
        // Today's in progress
        $stmt = $db->prepare("
            SELECT COUNT(*) as count FROM lab_tests 
            WHERE branch_id = ? AND DATE(created_at) = CURDATE() 
            AND status = 'in_progress'
        ");
        $stmt->execute([$branch_id]);
        $stats['today_in_progress'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
        
        // Today's completed
        $stmt = $db->prepare("
            SELECT COUNT(*) as count FROM lab_tests 
            WHERE branch_id = ? AND status = 'completed' AND DATE(completed_at) = CURDATE()
        ");
        $stmt->execute([$branch_id]);
        $stats['today_completed'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
        
        // ================================================================
        // 5. WEEK STATS
        // ================================================================
        
        // This week's tests
        $stmt = $db->prepare("
            SELECT COUNT(*) as count FROM lab_tests 
            WHERE branch_id = ? AND YEARWEEK(created_at) = YEARWEEK(CURDATE())
        ");
        $stmt->execute([$branch_id]);
        $stats['week_tests'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
        
        // This week's completed
        $stmt = $db->prepare("
            SELECT COUNT(*) as count FROM lab_tests 
            WHERE branch_id = ? AND status = 'completed' 
            AND YEARWEEK(completed_at) = YEARWEEK(CURDATE())
        ");
        $stmt->execute([$branch_id]);
        $stats['week_completed'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
        
        // This week's pending
        $stmt = $db->prepare("
            SELECT COUNT(*) as count FROM lab_tests 
            WHERE branch_id = ? AND YEARWEEK(created_at) = YEARWEEK(CURDATE())
            AND (status IS NULL OR status = '' OR status = 'pending')
        ");
        $stmt->execute([$branch_id]);
        $stats['week_pending'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
        
        // ================================================================
        // 6. MONTH STATS
        // ================================================================
        
        // This month's tests
        $stmt = $db->prepare("
            SELECT COUNT(*) as count FROM lab_tests 
            WHERE branch_id = ? AND MONTH(created_at) = MONTH(CURDATE()) 
            AND YEAR(created_at) = YEAR(CURDATE())
        ");
        $stmt->execute([$branch_id]);
        $stats['month_tests'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
        
        // This month's completed
        $stmt = $db->prepare("
            SELECT COUNT(*) as count FROM lab_tests 
            WHERE branch_id = ? AND status = 'completed' 
            AND MONTH(completed_at) = MONTH(CURDATE()) 
            AND YEAR(completed_at) = YEAR(CURDATE())
        ");
        $stmt->execute([$branch_id]);
        $stats['month_completed'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
        
        // ================================================================
        // 7. EQUIPMENT STATS
        // ================================================================
        
        // Equipment used in lab tests
        try {
            $stmt = $db->prepare("
                SELECT COUNT(DISTINCT equipment_id) as count 
                FROM lab_test_equipment 
                WHERE branch_id = ?
            ");
            $stmt->execute([$branch_id]);
            $stats['equipment_used'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
        } catch (Exception $e) {
            $stats['equipment_used'] = 0;
        }
        
        // Total equipment
        try {
            $stmt = $db->prepare("
                SELECT COUNT(*) as count 
                FROM medical_equipment 
                WHERE branch_id = ? AND status = 'active'
            ");
            $stmt->execute([$branch_id]);
            $stats['equipment_total'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
        } catch (Exception $e) {
            $stats['equipment_total'] = 0;
        }
        
        // Low stock equipment
        try {
            $stmt = $db->prepare("
                SELECT COUNT(*) as count 
                FROM medical_equipment 
                WHERE branch_id = ? 
                AND status = 'active' 
                AND quantity <= reorder_level
                AND quantity > 0
            ");
            $stmt->execute([$branch_id]);
            $stats['equipment_low_stock'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
        } catch (Exception $e) {
            $stats['equipment_low_stock'] = 0;
        }
        
        // ================================================================
        // 8. GENERATE HASH FOR CHANGE DETECTION
        // ================================================================
        $hash_data = [
            'pending' => $stats['pending'],
            'in_progress' => $stats['in_progress'],
            'completed' => $stats['completed'],
            'today_tests' => $stats['today_tests'],
            'today_completed' => $stats['today_completed'],
            'week_tests' => $stats['week_tests'],
            'total' => $stats['total']
        ];
        
        $stats['_hash'] = md5(json_encode($hash_data));
        
    } catch (Exception $e) {
        error_log("Lab stats error: " . $e->getMessage());
    }
    
    return $stats;
}

// ================================================================
// PROCESS REQUEST
// ================================================================
try {
    // Get stats
    $stats = getLabStats($db, $branch_id);
    
    // Check if data has changed
    $has_changed = false;
    $data_to_send = null;
    
    if ($force_update) {
        $has_changed = true;
        $data_to_send = $stats;
    } else if (!empty($client_hash) && $client_hash !== $stats['_hash']) {
        $has_changed = true;
        $data_to_send = $stats;
    } else if (empty($client_hash)) {
        // First request - send all data
        $has_changed = true;
        $data_to_send = $stats;
    }
    
    $response = [
        'success' => true,
        'has_changed' => $has_changed,
        'hash' => $stats['_hash'],
        'data' => $data_to_send,
        'timestamp' => date('Y-m-d H:i:s'),
        'timestamp_iso' => date('c')
    ];
    
    // Add summary for quick view
    if ($has_changed && $data_to_send) {
        $response['summary'] = [
            'pending' => $data_to_send['pending'],
            'in_progress' => $data_to_send['in_progress'],
            'completed' => $data_to_send['completed'],
            'today' => $data_to_send['today_tests'],
            'total' => $data_to_send['total']
        ];
        
        // Add badge data for easy UI update
        $response['badges'] = [
            'pending' => $data_to_send['pending'],
            'in_progress' => $data_to_send['in_progress'],
            'completed' => $data_to_send['completed'],
            'today_tests' => $data_to_send['today_tests']
        ];
    }
    
    echo json_encode($response);
    
} catch (Exception $e) {
    sendResponse(false, null, 'Error fetching stats: ' . $e->getMessage(), 500);
}
?>