<?php
// ================================================================
// FILE: backend/api/get_cashier_sidebar_stats.php
// CASHIER SIDEBAR STATS API - REAL-TIME UPDATES
// BRAICK DISPENSARY
// FIXED: Proper change detection and real-time updates
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

$allowed_roles = ['cashier', 'reception', 'admin'];
if (!in_array($_SESSION['role'], $allowed_roles)) {
    sendResponse(false, null, 'Forbidden - Cashier access required', 403);
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
// FETCH CASHIER STATS - FIXED: Better hash generation
// ================================================================
function getCashierStats($db, $branch_id) {
    $stats = [
        // Bill Stats
        'pending_bills' => 0,
        'partial_payments' => 0,
        'paid_bills' => 0,
        'cancelled_bills' => 0,
        'total_bills' => 0,
        
        // Today's Stats
        'today_paid' => 0,
        'today_partial' => 0,
        'today_pending' => 0,
        'today_total_amount' => 0,
        'today_paid_amount' => 0,
        
        // Week Stats
        'week_paid' => 0,
        'week_pending' => 0,
        'week_total_amount' => 0,
        'week_paid_amount' => 0,
        
        // Month Stats
        'month_paid' => 0,
        'month_total_amount' => 0,
        'month_paid_amount' => 0,
        
        // Patient Stats
        'patients_waiting' => 0,
        'patients_today' => 0,
        'total_patients' => 0,
        
        // Expense Stats
        'total_expenses' => 0,
        'today_expenses' => 0,
        'week_expenses' => 0,
        'month_expenses' => 0,
        
        // Branch Info
        'branch_name' => '',
        'branch_id' => $branch_id,
        
        // User Info
        'user_name' => '',
        'user_role' => '',
        
        // Timestamps
        'last_updated' => date('Y-m-d H:i:s'),
        
        // FIXED: Better hash with all numeric values
        '_hash' => '',
        '_hash_components' => []
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
        // 3. BILL STATS
        // ================================================================
        
        // Pending Bills
        $stmt = $db->prepare("
            SELECT COUNT(*) as count 
            FROM bills 
            WHERE branch_id = ? AND status = 'pending'
        ");
        $stmt->execute([$branch_id]);
        $stats['pending_bills'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
        
        // Partial Payments
        $stmt = $db->prepare("
            SELECT COUNT(*) as count 
            FROM bills 
            WHERE branch_id = ? AND status = 'partial'
        ");
        $stmt->execute([$branch_id]);
        $stats['partial_payments'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
        
        // Paid Bills
        $stmt = $db->prepare("
            SELECT COUNT(*) as count 
            FROM bills 
            WHERE branch_id = ? AND status = 'paid'
        ");
        $stmt->execute([$branch_id]);
        $stats['paid_bills'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
        
        // Cancelled Bills
        $stmt = $db->prepare("
            SELECT COUNT(*) as count 
            FROM bills 
            WHERE branch_id = ? AND status = 'cancelled'
        ");
        $stmt->execute([$branch_id]);
        $stats['cancelled_bills'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
        
        // Total Bills
        $stats['total_bills'] = $stats['pending_bills'] + $stats['partial_payments'] + 
                               $stats['paid_bills'] + $stats['cancelled_bills'];
        
        // ================================================================
        // 4. TODAY'S STATS
        // ================================================================
        
        // Today's Paid
        $stmt = $db->prepare("
            SELECT COUNT(*) as count, COALESCE(SUM(total_amount), 0) as total
            FROM bills 
            WHERE branch_id = ? AND status = 'paid' AND DATE(updated_at) = CURDATE()
        ");
        $stmt->execute([$branch_id]);
        $today = $stmt->fetch(PDO::FETCH_ASSOC);
        $stats['today_paid'] = (int)($today['count'] ?? 0);
        $stats['today_paid_amount'] = (float)($today['total'] ?? 0);
        
        // Today's Partial
        $stmt = $db->prepare("
            SELECT COUNT(*) as count 
            FROM bills 
            WHERE branch_id = ? AND status = 'partial' AND DATE(updated_at) = CURDATE()
        ");
        $stmt->execute([$branch_id]);
        $stats['today_partial'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
        
        // Today's Pending
        $stmt = $db->prepare("
            SELECT COUNT(*) as count, COALESCE(SUM(total_amount), 0) as total
            FROM bills 
            WHERE branch_id = ? AND status = 'pending' AND DATE(created_at) = CURDATE()
        ");
        $stmt->execute([$branch_id]);
        $today_pending = $stmt->fetch(PDO::FETCH_ASSOC);
        $stats['today_pending'] = (int)($today_pending['count'] ?? 0);
        $stats['today_total_amount'] = (float)($today_pending['total'] ?? 0) + $stats['today_paid_amount'];
        
        // ================================================================
        // 5. WEEK STATS
        // ================================================================
        
        // Week Paid
        $stmt = $db->prepare("
            SELECT COUNT(*) as count, COALESCE(SUM(total_amount), 0) as total
            FROM bills 
            WHERE branch_id = ? AND status = 'paid' AND YEARWEEK(updated_at) = YEARWEEK(CURDATE())
        ");
        $stmt->execute([$branch_id]);
        $week = $stmt->fetch(PDO::FETCH_ASSOC);
        $stats['week_paid'] = (int)($week['count'] ?? 0);
        $stats['week_paid_amount'] = (float)($week['total'] ?? 0);
        
        // Week Pending
        $stmt = $db->prepare("
            SELECT COUNT(*) as count, COALESCE(SUM(total_amount), 0) as total
            FROM bills 
            WHERE branch_id = ? AND status IN ('pending', 'partial') AND YEARWEEK(created_at) = YEARWEEK(CURDATE())
        ");
        $stmt->execute([$branch_id]);
        $week_pending = $stmt->fetch(PDO::FETCH_ASSOC);
        $stats['week_pending'] = (int)($week_pending['count'] ?? 0);
        $stats['week_total_amount'] = (float)($week_pending['total'] ?? 0) + $stats['week_paid_amount'];
        
        // ================================================================
        // 6. MONTH STATS
        // ================================================================
        
        // Month Paid
        $stmt = $db->prepare("
            SELECT COUNT(*) as count, COALESCE(SUM(total_amount), 0) as total
            FROM bills 
            WHERE branch_id = ? AND status = 'paid' 
            AND MONTH(updated_at) = MONTH(CURDATE()) AND YEAR(updated_at) = YEAR(CURDATE())
        ");
        $stmt->execute([$branch_id]);
        $month = $stmt->fetch(PDO::FETCH_ASSOC);
        $stats['month_paid'] = (int)($month['count'] ?? 0);
        $stats['month_paid_amount'] = (float)($month['total'] ?? 0);
        
        // Month Total
        $stmt = $db->prepare("
            SELECT COALESCE(SUM(total_amount), 0) as total
            FROM bills 
            WHERE branch_id = ? AND status = 'paid' 
            AND MONTH(updated_at) = MONTH(CURDATE()) AND YEAR(updated_at) = YEAR(CURDATE())
        ");
        $stmt->execute([$branch_id]);
        $stats['month_total_amount'] = (float)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
        
        // ================================================================
        // 7. PATIENT STATS
        // ================================================================
        
        // Patients Waiting (with pending or partial bills)
        $stmt = $db->prepare("
            SELECT COUNT(DISTINCT patient_id) as count 
            FROM bills 
            WHERE branch_id = ? AND status IN ('pending', 'partial')
        ");
        $stmt->execute([$branch_id]);
        $stats['patients_waiting'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
        
        // Patients Today
        $stmt = $db->prepare("
            SELECT COUNT(DISTINCT patient_id) as count 
            FROM bills 
            WHERE branch_id = ? AND DATE(created_at) = CURDATE()
        ");
        $stmt->execute([$branch_id]);
        $stats['patients_today'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
        
        // Total Patients
        $stmt = $db->prepare("
            SELECT COUNT(DISTINCT patient_id) as count 
            FROM bills 
            WHERE branch_id = ?
        ");
        $stmt->execute([$branch_id]);
        $stats['total_patients'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
        
        // ================================================================
        // 8. EXPENSE STATS
        // ================================================================
        try {
            // Total Expenses
            $stmt = $db->prepare("
                SELECT COALESCE(SUM(amount), 0) as total 
                FROM expenses 
                WHERE branch_id = ? AND status = 'paid'
            ");
            $stmt->execute([$branch_id]);
            $stats['total_expenses'] = (float)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
            
            // Today's Expenses
            $stmt = $db->prepare("
                SELECT COALESCE(SUM(amount), 0) as total 
                FROM expenses 
                WHERE branch_id = ? AND status = 'paid' AND DATE(paid_at) = CURDATE()
            ");
            $stmt->execute([$branch_id]);
            $stats['today_expenses'] = (float)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
            
            // Week Expenses
            $stmt = $db->prepare("
                SELECT COALESCE(SUM(amount), 0) as total 
                FROM expenses 
                WHERE branch_id = ? AND status = 'paid' AND YEARWEEK(paid_at) = YEARWEEK(CURDATE())
            ");
            $stmt->execute([$branch_id]);
            $stats['week_expenses'] = (float)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
            
            // Month Expenses
            $stmt = $db->prepare("
                SELECT COALESCE(SUM(amount), 0) as total 
                FROM expenses 
                WHERE branch_id = ? AND status = 'paid' 
                AND MONTH(paid_at) = MONTH(CURDATE()) AND YEAR(paid_at) = YEAR(CURDATE())
            ");
            $stmt->execute([$branch_id]);
            $stats['month_expenses'] = (float)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
            
        } catch (Exception $e) {
            // Table might not exist, keep as 0
        }
        
        // ================================================================
        // 9. FIXED: GENERATE HASH - Include ALL numeric values
        // ================================================================
        $hash_components = [
            'pending_bills' => $stats['pending_bills'],
            'partial_payments' => $stats['partial_payments'],
            'paid_bills' => $stats['paid_bills'],
            'cancelled_bills' => $stats['cancelled_bills'],
            'today_paid' => $stats['today_paid'],
            'today_paid_amount' => round($stats['today_paid_amount'], 2),
            'patients_waiting' => $stats['patients_waiting'],
            'total_expenses' => round($stats['total_expenses'], 2),
            'today_expenses' => round($stats['today_expenses'], 2),
            'week_paid' => $stats['week_paid'],
            'month_paid' => $stats['month_paid']
        ];
        
        $stats['_hash_components'] = $hash_components;
        $stats['_hash'] = md5(json_encode($hash_components));
        
        // Also log the hash for debugging
        error_log("Cashier Sidebar Hash: " . $stats['_hash']);
        error_log("Hash Components: " . json_encode($hash_components));
        
    } catch (Exception $e) {
        error_log("Cashier stats error: " . $e->getMessage());
    }
    
    return $stats;
}

// ================================================================
// PROCESS REQUEST
// ================================================================
try {
    // Get stats
    $stats = getCashierStats($db, $branch_id);
    
    // Check if data has changed - FIXED: Always check with force_update
    $has_changed = false;
    $data_to_send = null;
    
    // Log the request
    error_log("=== Cashier API Request ===");
    error_log("Client Hash: " . ($client_hash ?: 'empty'));
    error_log("Server Hash: " . $stats['_hash']);
    error_log("Force Update: " . ($force_update ? 'YES' : 'NO'));
    
    if ($force_update) {
        $has_changed = true;
        $data_to_send = $stats;
        error_log("Force update - sending full data");
    } else if (!empty($client_hash) && $client_hash !== $stats['_hash']) {
        $has_changed = true;
        $data_to_send = $stats;
        error_log("Hash mismatch - sending full data");
        error_log("  Client: " . $client_hash);
        error_log("  Server: " . $stats['_hash']);
    } else if (empty($client_hash)) {
        // First request - send all data
        $has_changed = true;
        $data_to_send = $stats;
        error_log("First request - sending full data");
    } else {
        error_log("No changes detected - sending empty data");
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
            'pending' => $data_to_send['pending_bills'],
            'partial' => $data_to_send['partial_payments'],
            'paid' => $data_to_send['paid_bills'],
            'cancelled' => $data_to_send['cancelled_bills'],
            'today' => $data_to_send['today_paid'],
            'today_amount' => $data_to_send['today_paid_amount'],
            'expenses' => $data_to_send['total_expenses'],
            'patients_waiting' => $data_to_send['patients_waiting']
        ];
        
        // Badge data for easy UI update
        $response['badges'] = [
            'pending_bills' => $data_to_send['pending_bills'],
            'partial_payments' => $data_to_send['partial_payments'],
            'paid_bills' => $data_to_send['paid_bills'],
            'cancelled_bills' => $data_to_send['cancelled_bills'],
            'today_paid' => $data_to_send['today_paid'],
            'total_expenses' => $data_to_send['total_expenses'],
            'patients_waiting' => $data_to_send['patients_waiting'],
            'today_paid_amount' => $data_to_send['today_paid_amount']
        ];
    }
    
    echo json_encode($response);
    
} catch (Exception $e) {
    sendResponse(false, null, 'Error fetching stats: ' . $e->getMessage(), 500);
}
?>