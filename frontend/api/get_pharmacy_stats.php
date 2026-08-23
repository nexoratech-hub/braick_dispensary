<?php
// ================================================================
// FILE: frontend/api/get_pharmacy_stats.php
// PHARMACY STATS API - RETURNS JSON FOR AUTO-UPDATE
// BRAICK DISPENSARY
// ================================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ================================================================
// IF NO SESSION, USE PHARMACY DODOMA (ID: 7) AS DEFAULT
// ================================================================
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'pharmacy') {
    $_SESSION['user_id'] = 7;
    $_SESSION['full_name'] = 'GRACE MUSSA';
    $_SESSION['role'] = 'pharmacy';
    $_SESSION['branch_id'] = 1;
    $_SESSION['branch_name'] = 'Dodoma';
}

$user_branch_id = $_SESSION['branch_id'] ?? 1;

// ================================================================
// INCLUDE DATABASE
// ================================================================
require_once __DIR__ . '/../../../backend/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}

$today = date('Y-m-d');
$thirty_days_later = date('Y-m-d', strtotime('+30 days'));

// ================================================================
// CARD 1: TOTAL STOCK ITEMS (Medicine + Equipment)
// ================================================================

// Medicines
$stmt = $db->prepare("
    SELECT COUNT(DISTINCT medication_name) as count, SUM(quantity) as total_quantity
    FROM medications_inventory 
    WHERE branch_id = ? 
    AND status = 'active'
    AND (expiry_date IS NULL OR expiry_date >= CURDATE())
");
$stmt->execute([$user_branch_id]);
$med_data = $stmt->fetch(PDO::FETCH_ASSOC);
$total_medicines = $med_data['count'] ?? 0;
$total_med_quantity = $med_data['total_quantity'] ?? 0;

// Equipment
$stmt = $db->prepare("
    SELECT COUNT(DISTINCT equipment_name) as count, SUM(quantity) as total_quantity
    FROM medical_equipment 
    WHERE branch_id = ? 
    AND status = 'active'
    AND (expiry_date IS NULL OR expiry_date >= CURDATE())
");
$stmt->execute([$user_branch_id]);
$equip_data = $stmt->fetch(PDO::FETCH_ASSOC);
$total_equipment = $equip_data['count'] ?? 0;
$total_equip_quantity = $equip_data['total_quantity'] ?? 0;

$total_stock_items = $total_medicines + $total_equipment;
$total_stock_quantity = $total_med_quantity + $total_equip_quantity;

// ================================================================
// CARD 2: EXPIRED (Medicine + Equipment)
// ================================================================

// Expired Medicines
$stmt = $db->prepare("
    SELECT COUNT(*) as count, SUM(quantity) as total_quantity
    FROM medications_inventory 
    WHERE branch_id = ? 
    AND expiry_date IS NOT NULL 
    AND expiry_date < CURDATE()
");
$stmt->execute([$user_branch_id]);
$expired_med = $stmt->fetch(PDO::FETCH_ASSOC);
$expired_med_count = $expired_med['count'] ?? 0;
$expired_med_quantity = $expired_med['total_quantity'] ?? 0;

// Expired Equipment
$stmt = $db->prepare("
    SELECT COUNT(*) as count, SUM(quantity) as total_quantity
    FROM medical_equipment 
    WHERE branch_id = ? 
    AND expiry_date IS NOT NULL 
    AND expiry_date < CURDATE()
");
$stmt->execute([$user_branch_id]);
$expired_equip = $stmt->fetch(PDO::FETCH_ASSOC);
$expired_equip_count = $expired_equip['count'] ?? 0;
$expired_equip_quantity = $expired_equip['total_quantity'] ?? 0;

$expired_count = $expired_med_count + $expired_equip_count;
$expired_quantity = $expired_med_quantity + $expired_equip_quantity;

// ================================================================
// CARD 3: EXPIRE SOON (Medicine + Equipment)
// ================================================================

// Medicines expiring soon
$stmt = $db->prepare("
    SELECT COUNT(*) as count 
    FROM medications_inventory 
    WHERE branch_id = ? 
    AND status = 'active'
    AND expiry_date IS NOT NULL 
    AND expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
");
$stmt->execute([$user_branch_id]);
$expire_soon_med = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// Equipment expiring soon
$stmt = $db->prepare("
    SELECT COUNT(*) as count 
    FROM medical_equipment 
    WHERE branch_id = ? 
    AND status = 'active'
    AND expiry_date IS NOT NULL 
    AND expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
");
$stmt->execute([$user_branch_id]);
$expire_soon_equip = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

$expire_soon_count = $expire_soon_med + $expire_soon_equip;

// ================================================================
// CARD 4: TOTAL PRESCRIPTIONS
// ================================================================

// Total
$stmt = $db->prepare("SELECT COUNT(*) as count FROM prescriptions WHERE branch_id = ?");
$stmt->execute([$user_branch_id]);
$total_prescriptions = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// Pending
$stmt = $db->prepare("SELECT COUNT(*) as count FROM prescriptions WHERE branch_id = ? AND status = 'pending'");
$stmt->execute([$user_branch_id]);
$pending_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// Completed/Dispensed
$stmt = $db->prepare("SELECT COUNT(*) as count FROM prescriptions WHERE branch_id = ? AND status = 'dispensed'");
$stmt->execute([$user_branch_id]);
$completed_prescriptions = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// ================================================================
// CARD 5: OTC SALES
// ================================================================

// Total OTC
$stmt = $db->prepare("SELECT COUNT(*) as count FROM otc_sales WHERE branch_id = ?");
$stmt->execute([$user_branch_id]);
$otc_sales_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// Today's OTC
$stmt = $db->prepare("
    SELECT COUNT(*) as count 
    FROM otc_sales 
    WHERE branch_id = ? AND DATE(created_at) = CURDATE()
");
$stmt->execute([$user_branch_id]);
$otc_today_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// ================================================================
// CARD 6: DISPENSED MEDICINES (OTC + Prescription)
// ================================================================

// OTC paid
$stmt = $db->prepare("SELECT COUNT(*) as count FROM otc_sales WHERE branch_id = ? AND payment_status = 'paid'");
$stmt->execute([$user_branch_id]);
$otc_dispensed = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// Prescription dispensed
$stmt = $db->prepare("SELECT COUNT(*) as count FROM prescriptions WHERE branch_id = ? AND status = 'dispensed'");
$stmt->execute([$user_branch_id]);
$prescription_dispensed = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

$total_dispensed = $otc_dispensed + $prescription_dispensed;

// ================================================================
// CARD 7: LOW STOCK
// ================================================================

// Low Stock Medicines
$stmt = $db->prepare("
    SELECT COUNT(*) as count 
    FROM medications_inventory 
    WHERE branch_id = ? 
    AND status = 'active'
    AND quantity > 0 
    AND quantity <= reorder_level
    AND (expiry_date IS NULL OR expiry_date >= CURDATE())
");
$stmt->execute([$user_branch_id]);
$low_stock_med = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// Low Stock Equipment
$stmt = $db->prepare("
    SELECT COUNT(*) as count 
    FROM medical_equipment 
    WHERE branch_id = ? 
    AND status = 'active'
    AND quantity > 0 
    AND quantity <= reorder_level
    AND (expiry_date IS NULL OR expiry_date >= CURDATE())
");
$stmt->execute([$user_branch_id]);
$low_stock_equip = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

$low_stock_count = $low_stock_med + $low_stock_equip;

// ================================================================
// CARD 8: OUT OF STOCK
// ================================================================

// Out of Stock Medicines
$stmt = $db->prepare("
    SELECT COUNT(*) as count 
    FROM medications_inventory 
    WHERE branch_id = ? 
    AND status = 'active'
    AND quantity = 0
    AND (expiry_date IS NULL OR expiry_date >= CURDATE())
");
$stmt->execute([$user_branch_id]);
$out_of_stock_med = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// Out of Stock Equipment
$stmt = $db->prepare("
    SELECT COUNT(*) as count 
    FROM medical_equipment 
    WHERE branch_id = ? 
    AND status = 'active'
    AND quantity = 0
    AND (expiry_date IS NULL OR expiry_date >= CURDATE())
");
$stmt->execute([$user_branch_id]);
$out_of_stock_equip = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

$out_of_stock_count = $out_of_stock_med + $out_of_stock_equip;

// ================================================================
// LISTS FOR DISPLAY
// ================================================================

// Expired Medicines List
$stmt = $db->prepare("
    SELECT id, medication_name as name, quantity, expiry_date, batch_number, 'medicine' as type, status
    FROM medications_inventory 
    WHERE branch_id = ? 
    AND expiry_date IS NOT NULL 
    AND expiry_date < CURDATE()
    ORDER BY expiry_date ASC
    LIMIT 10
");
$stmt->execute([$user_branch_id]);
$expired_med_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Expired Equipment List
$stmt = $db->prepare("
    SELECT id, equipment_name as name, quantity, expiry_date, batch_number, 'equipment' as type, status
    FROM medical_equipment 
    WHERE branch_id = ? 
    AND expiry_date IS NOT NULL 
    AND expiry_date < CURDATE()
    ORDER BY expiry_date ASC
    LIMIT 10
");
$stmt->execute([$user_branch_id]);
$expired_equip_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

$expired_all = array_merge($expired_med_list, $expired_equip_list);
usort($expired_all, function($a, $b) {
    return strtotime($a['expiry_date']) - strtotime($b['expiry_date']);
});
$expired_all = array_slice($expired_all, 0, 10);

// Expire Soon Medicines List
$stmt = $db->prepare("
    SELECT id, medication_name as name, quantity, expiry_date, batch_number, 'medicine' as type
    FROM medications_inventory 
    WHERE branch_id = ? 
    AND status = 'active'
    AND expiry_date IS NOT NULL 
    AND expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
    ORDER BY expiry_date ASC
    LIMIT 10
");
$stmt->execute([$user_branch_id]);
$expire_soon_med_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Expire Soon Equipment List
$stmt = $db->prepare("
    SELECT id, equipment_name as name, quantity, expiry_date, batch_number, 'equipment' as type
    FROM medical_equipment 
    WHERE branch_id = ? 
    AND status = 'active'
    AND expiry_date IS NOT NULL 
    AND expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
    ORDER BY expiry_date ASC
    LIMIT 10
");
$stmt->execute([$user_branch_id]);
$expire_soon_equip_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

$expire_soon_all = array_merge($expire_soon_med_list, $expire_soon_equip_list);
usort($expire_soon_all, function($a, $b) {
    return strtotime($a['expiry_date']) - strtotime($b['expiry_date']);
});
$expire_soon_all = array_slice($expire_soon_all, 0, 10);

// Low Stock Medicines List
$stmt = $db->prepare("
    SELECT id, medication_name as name, quantity, reorder_level, unit, 'medicine' as type
    FROM medications_inventory 
    WHERE branch_id = ? 
    AND status = 'active'
    AND quantity > 0 
    AND quantity <= reorder_level
    AND (expiry_date IS NULL OR expiry_date >= CURDATE())
    ORDER BY quantity ASC
    LIMIT 10
");
$stmt->execute([$user_branch_id]);
$low_stock_med_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Low Stock Equipment List
$stmt = $db->prepare("
    SELECT id, equipment_name as name, quantity, reorder_level, unit, 'equipment' as type
    FROM medical_equipment 
    WHERE branch_id = ? 
    AND status = 'active'
    AND quantity > 0 
    AND quantity <= reorder_level
    AND (expiry_date IS NULL OR expiry_date >= CURDATE())
    ORDER BY quantity ASC
    LIMIT 10
");
$stmt->execute([$user_branch_id]);
$low_stock_equip_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

$low_stock_all = array_merge($low_stock_med_list, $low_stock_equip_list);
usort($low_stock_all, function($a, $b) {
    return $a['quantity'] - $b['quantity'];
});
$low_stock_all = array_slice($low_stock_all, 0, 10);

// Out of Stock Medicines List
$stmt = $db->prepare("
    SELECT id, medication_name as name, quantity, reorder_level, unit, 'medicine' as type
    FROM medications_inventory 
    WHERE branch_id = ? 
    AND status = 'active'
    AND quantity = 0
    AND (expiry_date IS NULL OR expiry_date >= CURDATE())
    ORDER BY medication_name ASC
    LIMIT 10
");
$stmt->execute([$user_branch_id]);
$out_of_stock_med_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Out of Stock Equipment List
$stmt = $db->prepare("
    SELECT id, equipment_name as name, quantity, reorder_level, unit, 'equipment' as type
    FROM medical_equipment 
    WHERE branch_id = ? 
    AND status = 'active'
    AND quantity = 0
    AND (expiry_date IS NULL OR expiry_date >= CURDATE())
    ORDER BY equipment_name ASC
    LIMIT 10
");
$stmt->execute([$user_branch_id]);
$out_of_stock_equip_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

$out_of_stock_all = array_merge($out_of_stock_med_list, $out_of_stock_equip_list);
$out_of_stock_all = array_slice($out_of_stock_all, 0, 10);

// Pending Prescriptions List
$stmt = $db->prepare("
    SELECT p.*, pat.full_name as patient_name, pat.patient_id as patient_code
    FROM prescriptions p
    JOIN patients pat ON p.patient_id = pat.id
    WHERE p.branch_id = ? AND p.status = 'pending'
    ORDER BY p.created_at ASC
    LIMIT 10
");
$stmt->execute([$user_branch_id]);
$pending_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// CREATE DATA HASH FOR CHANGE DETECTION
// ================================================================
$data_array = [
    'total_stock_items' => $total_stock_items,
    'total_medicines' => $total_medicines,
    'total_equipment' => $total_equipment,
    'total_stock_quantity' => $total_stock_quantity,
    'expired_count' => $expired_count,
    'expired_med_count' => $expired_med_count,
    'expired_equip_count' => $expired_equip_count,
    'expired_quantity' => $expired_quantity,
    'expire_soon_count' => $expire_soon_count,
    'expire_soon_med' => $expire_soon_med,
    'expire_soon_equip' => $expire_soon_equip,
    'total_prescriptions' => $total_prescriptions,
    'pending_count' => $pending_count,
    'completed_prescriptions' => $completed_prescriptions,
    'otc_sales_count' => $otc_sales_count,
    'otc_today_count' => $otc_today_count,
    'total_dispensed' => $total_dispensed,
    'otc_dispensed' => $otc_dispensed,
    'prescription_dispensed' => $prescription_dispensed,
    'low_stock_count' => $low_stock_count,
    'low_stock_med' => $low_stock_med,
    'low_stock_equip' => $low_stock_equip,
    'out_of_stock_count' => $out_of_stock_count,
    'out_of_stock_med' => $out_of_stock_med,
    'out_of_stock_equip' => $out_of_stock_equip,
    'expired_all_count' => count($expired_all),
    'expire_soon_all_count' => count($expire_soon_all),
    'low_stock_all_count' => count($low_stock_all),
    'out_of_stock_all_count' => count($out_of_stock_all),
    'pending_list_count' => count($pending_list)
];

$data_hash = md5(json_encode($data_array));

// ================================================================
// RETURN JSON
// ================================================================
header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'hash' => $data_hash,
    'stats' => [
        'total_stock_items' => $total_stock_items,
        'total_medicines' => $total_medicines,
        'total_equipment' => $total_equipment,
        'total_stock_quantity' => $total_stock_quantity,
        'expired_count' => $expired_count,
        'expired_med_count' => $expired_med_count,
        'expired_equip_count' => $expired_equip_count,
        'expired_quantity' => $expired_quantity,
        'expire_soon_count' => $expire_soon_count,
        'expire_soon_med' => $expire_soon_med,
        'expire_soon_equip' => $expire_soon_equip,
        'total_prescriptions' => $total_prescriptions,
        'pending_count' => $pending_count,
        'completed_prescriptions' => $completed_prescriptions,
        'otc_sales_count' => $otc_sales_count,
        'otc_today_count' => $otc_today_count,
        'total_dispensed' => $total_dispensed,
        'otc_dispensed' => $otc_dispensed,
        'prescription_dispensed' => $prescription_dispensed,
        'low_stock_count' => $low_stock_count,
        'low_stock_med' => $low_stock_med,
        'low_stock_equip' => $low_stock_equip,
        'out_of_stock_count' => $out_of_stock_count,
        'out_of_stock_med' => $out_of_stock_med,
        'out_of_stock_equip' => $out_of_stock_equip
    ],
    'lists' => [
        'expired' => $expired_all,
        'expire_soon' => $expire_soon_all,
        'low_stock' => $low_stock_all,
        'out_of_stock' => $out_of_stock_all,
        'pending' => $pending_list
    ],
    'timestamp' => date('Y-m-d H:i:s')
]);
?>