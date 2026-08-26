<?php
// ================================================================
// FILE: backend/api/get_pharmacy_sidebar_stats.php
// PHARMACY SIDEBAR STATS API - REAL-TIME UPDATES
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

$allowed_roles = ['pharmacy', 'admin'];
if (!in_array($_SESSION['role'], $allowed_roles)) {
    sendResponse(false, null, 'Forbidden - Pharmacy access required', 403);
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
// FETCH PHARMACY STATS
// ================================================================
function getPharmacyStats($db, $branch_id) {
    $stats = [
        // ================================================================
        // PRESCRIPTION STATS
        // ================================================================
        'pending_prescriptions' => 0,
        'total_prescriptions' => 0,
        'dispensed_prescriptions' => 0,
        'cancelled_prescriptions' => 0,
        
        // ================================================================
        // TODAY'S STATS
        // ================================================================
        'today_prescriptions' => 0,
        'today_dispensed' => 0,
        'today_otc' => 0,
        'today_otc_total' => 0,
        'today_revenue' => 0,
        
        // ================================================================
        // WEEK STATS
        // ================================================================
        'week_prescriptions' => 0,
        'week_dispensed' => 0,
        'week_otc' => 0,
        'week_revenue' => 0,
        
        // ================================================================
        // MONTH STATS
        // ================================================================
        'month_prescriptions' => 0,
        'month_revenue' => 0,
        
        // ================================================================
        // INVENTORY STATS
        // ================================================================
        'low_stock' => 0,
        'expired' => 0,
        'total_medicines' => 0,
        'expiring_soon' => 0,
        
        // ================================================================
        // BRANCH INFO
        // ================================================================
        'branch_name' => '',
        'branch_id' => $branch_id,
        
        // ================================================================
        // USER INFO
        // ================================================================
        'user_name' => '',
        'user_role' => '',
        
        // ================================================================
        // TIMESTAMPS
        // ================================================================
        'last_updated' => date('Y-m-d H:i:s'),
        
        // ================================================================
        // HASH FOR CHANGE DETECTION
        // ================================================================
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
        // 3. PRESCRIPTION STATS
        // ================================================================
        
        // Pending prescriptions
        $stmt = $db->prepare("
            SELECT COUNT(*) as count FROM prescriptions 
            WHERE branch_id = ? AND status = 'pending'
        ");
        $stmt->execute([$branch_id]);
        $stats['pending_prescriptions'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
        
        // Total prescriptions
        $stmt = $db->prepare("
            SELECT COUNT(*) as count FROM prescriptions 
            WHERE branch_id = ? 
        ");
        $stmt->execute([$branch_id]);
        $stats['total_prescriptions'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
        
        // Dispensed prescriptions
        $stmt = $db->prepare("
            SELECT COUNT(*) as count FROM prescriptions 
            WHERE branch_id = ? AND status = 'dispensed'
        ");
        $stmt->execute([$branch_id]);
        $stats['dispensed_prescriptions'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
        
        // Cancelled prescriptions
        $stmt = $db->prepare("
            SELECT COUNT(*) as count FROM prescriptions 
            WHERE branch_id = ? AND status = 'cancelled'
        ");
        $stmt->execute([$branch_id]);
        $stats['cancelled_prescriptions'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
        
        // ================================================================
        // 4. TODAY'S STATS
        // ================================================================
        
        // Today's prescriptions (all)
        $stmt = $db->prepare("
            SELECT COUNT(*) as count FROM prescriptions 
            WHERE branch_id = ? AND DATE(created_at) = CURDATE()
        ");
        $stmt->execute([$branch_id]);
        $stats['today_prescriptions'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
        
        // Today's dispensed
        $stmt = $db->prepare("
            SELECT COUNT(*) as count FROM prescriptions 
            WHERE branch_id = ? AND status = 'dispensed' AND DATE(dispensed_at) = CURDATE()
        ");
        $stmt->execute([$branch_id]);
        $stats['today_dispensed'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
        
        // Today's OTC sales
        $stmt = $db->prepare("
            SELECT 
                COUNT(*) as count,
                COALESCE(SUM(total_amount), 0) as total
            FROM otc_sales 
            WHERE branch_id = ? AND DATE(created_at) = CURDATE() AND payment_status = 'paid'
        ");
        $stmt->execute([$branch_id]);
        $otc = $stmt->fetch(PDO::FETCH_ASSOC);
        $stats['today_otc'] = (int)($otc['count'] ?? 0);
        $stats['today_otc_total'] = (float)($otc['total'] ?? 0);
        
        // Today's revenue (prescriptions + OTC)
        $stmt = $db->prepare("
            SELECT COALESCE(SUM(pi.total_price), 0) as total
            FROM prescriptions p
            JOIN prescription_items pi ON p.id = pi.prescription_id
            WHERE p.branch_id = ? AND p.status = 'dispensed' AND DATE(p.dispensed_at) = CURDATE()
        ");
        $stmt->execute([$branch_id]);
        $presc_revenue = (float)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
        $stats['today_revenue'] = $presc_revenue + $stats['today_otc_total'];
        
        // ================================================================
        // 5. WEEK STATS
        // ================================================================
        
        // Week prescriptions
        $stmt = $db->prepare("
            SELECT COUNT(*) as count FROM prescriptions 
            WHERE branch_id = ? AND YEARWEEK(created_at) = YEARWEEK(CURDATE())
        ");
        $stmt->execute([$branch_id]);
        $stats['week_prescriptions'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
        
        // Week dispensed
        $stmt = $db->prepare("
            SELECT COUNT(*) as count FROM prescriptions 
            WHERE branch_id = ? AND status = 'dispensed' AND YEARWEEK(dispensed_at) = YEARWEEK(CURDATE())
        ");
        $stmt->execute([$branch_id]);
        $stats['week_dispensed'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
        
        // Week OTC
        $stmt = $db->prepare("
            SELECT COUNT(*) as count FROM otc_sales 
            WHERE branch_id = ? AND YEARWEEK(created_at) = YEARWEEK(CURDATE()) AND payment_status = 'paid'
        ");
        $stmt->execute([$branch_id]);
        $stats['week_otc'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
        
        // Week revenue
        $stmt = $db->prepare("
            SELECT COALESCE(SUM(pi.total_price), 0) as total
            FROM prescriptions p
            JOIN prescription_items pi ON p.id = pi.prescription_id
            WHERE p.branch_id = ? AND p.status = 'dispensed' AND YEARWEEK(p.dispensed_at) = YEARWEEK(CURDATE())
        ");
        $stmt->execute([$branch_id]);
        $week_presc_revenue = (float)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
        
        $stmt = $db->prepare("
            SELECT COALESCE(SUM(total_amount), 0) as total
            FROM otc_sales 
            WHERE branch_id = ? AND YEARWEEK(created_at) = YEARWEEK(CURDATE()) AND payment_status = 'paid'
        ");
        $stmt->execute([$branch_id]);
        $week_otc_revenue = (float)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
        $stats['week_revenue'] = $week_presc_revenue + $week_otc_revenue;
        
        // ================================================================
        // 6. MONTH STATS
        // ================================================================
        
        // Month prescriptions
        $stmt = $db->prepare("
            SELECT COUNT(*) as count FROM prescriptions 
            WHERE branch_id = ? AND MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())
        ");
        $stmt->execute([$branch_id]);
        $stats['month_prescriptions'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
        
        // Month revenue
        $stmt = $db->prepare("
            SELECT COALESCE(SUM(pi.total_price), 0) as total
            FROM prescriptions p
            JOIN prescription_items pi ON p.id = pi.prescription_id
            WHERE p.branch_id = ? AND p.status = 'dispensed' 
            AND MONTH(p.dispensed_at) = MONTH(CURDATE()) AND YEAR(p.dispensed_at) = YEAR(CURDATE())
        ");
        $stmt->execute([$branch_id]);
        $month_presc_revenue = (float)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
        
        $stmt = $db->prepare("
            SELECT COALESCE(SUM(total_amount), 0) as total
            FROM otc_sales 
            WHERE branch_id = ? AND MONTH(created_at) = MONTH(CURDATE()) 
            AND YEAR(created_at) = YEAR(CURDATE()) AND payment_status = 'paid'
        ");
        $stmt->execute([$branch_id]);
        $month_otc_revenue = (float)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
        $stats['month_revenue'] = $month_presc_revenue + $month_otc_revenue;
        
        // ================================================================
        // 7. INVENTORY STATS
        // ================================================================
        
        // Low stock (quantity <= reorder_level)
        $stmt = $db->prepare("
            SELECT COUNT(*) as count 
            FROM medications_inventory 
            WHERE branch_id = ? AND quantity <= reorder_level AND quantity > 0 AND status = 'active'
        ");
        $stmt->execute([$branch_id]);
        $stats['low_stock'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
        
        // Expired (expiry_date < CURDATE())
        $stmt = $db->prepare("
            SELECT COUNT(*) as count 
            FROM medications_inventory 
            WHERE branch_id = ? 
            AND expiry_date IS NOT NULL 
            AND expiry_date < CURDATE()
        ");
        $stmt->execute([$branch_id]);
        $stats['expired'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
        
        // Expiring soon (within 30 days)
        $stmt = $db->prepare("
            SELECT COUNT(*) as count 
            FROM medications_inventory 
            WHERE branch_id = ? 
            AND status = 'active' 
            AND expiry_date IS NOT NULL
            AND expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
        ");
        $stmt->execute([$branch_id]);
        $stats['expiring_soon'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
        
        // Total medicines
        $stmt = $db->prepare("
            SELECT COUNT(*) as count 
            FROM medications_inventory 
            WHERE branch_id = ? AND status = 'active'
        ");
        $stmt->execute([$branch_id]);
        $stats['total_medicines'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
        
        // ================================================================
        // 8. GENERATE HASH FOR CHANGE DETECTION
        // ================================================================
        $hash_data = [
            'pending_prescriptions' => $stats['pending_prescriptions'],
            'low_stock' => $stats['low_stock'],
            'expired' => $stats['expired'],
            'today_prescriptions' => $stats['today_prescriptions'],
            'today_otc' => $stats['today_otc'],
            'today_revenue' => $stats['today_revenue']
        ];
        
        $stats['_hash'] = md5(json_encode($hash_data));
        
    } catch (Exception $e) {
        error_log("Pharmacy stats error: " . $e->getMessage());
    }
    
    return $stats;
}

// ================================================================
// PROCESS REQUEST
// ================================================================
try {
    // Get stats
    $stats = getPharmacyStats($db, $branch_id);
    
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
            'pending' => $data_to_send['pending_prescriptions'],
            'low_stock' => $data_to_send['low_stock'],
            'expired' => $data_to_send['expired'],
            'today' => $data_to_send['today_prescriptions'],
            'today_otc' => $data_to_send['today_otc'],
            'revenue' => $data_to_send['today_revenue']
        ];
        
        // Badge data for easy UI update
        $response['badges'] = [
            'pending_prescriptions' => $data_to_send['pending_prescriptions'],
            'low_stock' => $data_to_send['low_stock'],
            'expired' => $data_to_send['expired'],
            'today_prescriptions' => $data_to_send['today_prescriptions'],
            'today_otc' => $data_to_send['today_otc']
        ];
    }
    
    echo json_encode($response);
    
} catch (Exception $e) {
    sendResponse(false, null, 'Error fetching stats: ' . $e->getMessage(), 500);
}
?>