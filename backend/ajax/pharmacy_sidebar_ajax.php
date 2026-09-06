<?php
// ================================================================
// FILE: backend/api/pharmacy_sidebar_ajax.php
// PHARMACY SIDEBAR - DIRECT AJAX (NO EXTERNAL API)
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
if (!in_array($_SESSION['role'], ['pharmacy', 'admin'])) {
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
$pending_prescriptions = 0;
$low_stock = 0;
$expired = 0;
$today_prescriptions = 0;
$today_otc = 0;
$total_prescriptions = 0;
$total_dispensed = 0;
$total_otc = 0;

try {
    // 1. Pending Prescriptions (pending + confirmed)
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM prescriptions 
        WHERE branch_id = ? 
        AND status IN ('pending', 'confirmed')
    ");
    $stmt->execute([$branch_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $pending_prescriptions = (int)($result['count'] ?? 0);
    
    // 2. Total Prescriptions
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM prescriptions 
        WHERE branch_id = ?
    ");
    $stmt->execute([$branch_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $total_prescriptions = (int)($result['count'] ?? 0);
    
    // 3. Total Dispensed
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM prescriptions 
        WHERE branch_id = ? 
        AND status = 'dispensed'
    ");
    $stmt->execute([$branch_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $total_dispensed = (int)($result['count'] ?? 0);
    
    // 4. Low Stock
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM medications_inventory 
        WHERE branch_id = ? 
        AND quantity <= IFNULL(reorder_level, 10)
        AND quantity > 0 
        AND status = 'active'
    ");
    $stmt->execute([$branch_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $low_stock = (int)($result['count'] ?? 0);
    
    // 5. Expired Stock
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM medications_inventory 
        WHERE branch_id = ? 
        AND expiry_date IS NOT NULL 
        AND expiry_date < CURDATE()
        AND status = 'active'
    ");
    $stmt->execute([$branch_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $expired = (int)($result['count'] ?? 0);
    
    // 6. Today Prescriptions Dispensed
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM prescriptions 
        WHERE branch_id = ? 
        AND status = 'dispensed' 
        AND DATE(dispensed_at) = CURDATE()
    ");
    $stmt->execute([$branch_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $today_prescriptions = (int)($result['count'] ?? 0);
    
    // 7. Today OTC Sales
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM otc_sales 
        WHERE branch_id = ? 
        AND DATE(created_at) = CURDATE()
    ");
    $stmt->execute([$branch_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $today_otc = (int)($result['count'] ?? 0);
    
    // 8. TOTAL OTC Sales (FIXED - shows all OTC)
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM otc_sales 
        WHERE branch_id = ?
    ");
    $stmt->execute([$branch_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $total_otc = (int)($result['count'] ?? 0);
    
} catch (Exception $e) {
    error_log("Pharmacy sidebar stats error: " . $e->getMessage());
}

// ================================================================
// GENERATE HASH AND CHECK CHANGES
// ================================================================
$data = [
    'pending_prescriptions' => $pending_prescriptions,
    'low_stock' => $low_stock,
    'expired' => $expired,
    'today_prescriptions' => $today_prescriptions,
    'today_otc' => $today_otc,
    'total_prescriptions' => $total_prescriptions,
    'total_dispensed' => $total_dispensed,
    'total_otc' => $total_otc
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
        'pending' => $pending_prescriptions,
        'low_stock' => $low_stock,
        'expired' => $expired,
        'today' => $today_prescriptions,
        'otc' => $today_otc,
        'total' => $total_prescriptions,
        'dispensed' => $total_dispensed,
        'total_otc' => $total_otc
    ],
    'timestamp' => date('H:i:s'),
    'branch_id' => $branch_id
]);
exit;
?>