<?php
// ================================================================
// FILE: frontend/pages/audit/inventory.php
// AUDIT - INVENTORY, EQUIPMENT & PHARMACY SALES
// ✅ Inaonyesha data za branch ya mtumiaji aliye login TU
// ✅ Jina la mtumiaji aliye login linaonekana kwenye header
// ✅ Prescription = GROSS (sawa na revenue.php)
// ✅ OTC section - CARD per Sale
// ✅ Premium + Discount per patient + per visit
// ✅ Tab + Sub-tab zinabaki baada ya filter
// ✅ Received By = ONLY shown when PAID
// ================================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: /dispensary_system/frontend/pages/login.php');
    exit;
}

// AUDIT ROLE ONLY
if ($_SESSION['role'] !== 'audit') {
    $role = $_SESSION['role'];
    switch ($role) {
        case 'admin': header('Location: /dispensary_system/frontend/pages/admin/dashboard.php'); break;
        case 'doctor': header('Location: /dispensary_system/frontend/pages/doctor/dashboard.php'); break;
        case 'pharmacy': header('Location: /dispensary_system/frontend/pages/pharmacy/dashboard.php'); break;
        case 'laboratory': header('Location: /dispensary_system/frontend/pages/laboratory/dashboard.php'); break;
        case 'cashier': header('Location: /dispensary_system/frontend/pages/cashier/dashboard.php'); break;
        case 'reception': header('Location: /dispensary_system/frontend/pages/reception/dashboard.php'); break;
        default: header('Location: /dispensary_system/frontend/pages/login.php'); break;
    }
    exit;
}

$user_id = $_SESSION['user_id'] ?? 0;
$user_full_name = $_SESSION['full_name'] ?? 'Audit User';
$user_role = $_SESSION['role'] ?? 'audit';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$user_username = $_SESSION['username'] ?? 'audit';
$profile_pic = $_SESSION['profile_pic'] ?? '';

// ================================================================
// ✅ LAZIMISHA branch ya mtumiaji aliye login TU
// ================================================================
$selected_branch_id = (int)$user_branch_id;

$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'medicines';
if (!in_array($active_tab, ['medicines', 'equipment', 'pharmacy'])) {
    $active_tab = 'medicines';
}

$active_sub_tab = isset($_GET['sub_tab']) ? $_GET['sub_tab'] : 'prescriptions';
if (!in_array($active_sub_tab, ['prescriptions', 'otc'])) {
    $active_sub_tab = 'prescriptions';
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/dispensary_system/backend/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database connection error: " . $e->getMessage());
}

// CURRENCY
$currency = 'TSh';
try {
    $stmt = $db->query("SELECT setting_value FROM system_settings WHERE setting_key = 'currency'");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row && !empty($row['setting_value'])) $currency = $row['setting_value'];
} catch (Exception $e) {}

function formatMoneyShort($amount) {
    if ($amount === null || $amount === '') return '0';
    $amount = (float)$amount;
    if ($amount >= 1000000000) return number_format($amount / 1000000000, 1) . 'B';
    if ($amount >= 1000000) return number_format($amount / 1000000, 1) . 'M';
    if ($amount >= 1000) return number_format($amount / 1000, 1) . 'K';
    return number_format($amount, 0);
}

function calculateAge($dob) {
    if (empty($dob) || $dob === '0000-00-00') return 'N/A';
    try {
        return (new DateTime($dob))->diff(new DateTime('today'))->y . ' yrs';
    } catch (Exception $e) { return 'N/A'; }
}

// ================================================================
// ✅ BRANCH FILTER - KILA KITU KINATUMIA BRANCH YA MTUMIAJI
// ================================================================
$branch_cond_med = " AND m.branch_id = ?";
$branch_cond_eq  = " AND e.branch_id = ?";
$branch_cond_bi  = " AND bi.branch_id = ?";
$branch_cond_p   = " AND p.branch_id = ?";
$branch_cond_b   = " AND b.branch_id = ?";
$branch_cond_os  = " AND os.branch_id = ?";
$branch_params   = [$selected_branch_id];

// Branch info (kwa display)
$branches = [];
try {
    $stmt = $db->query("SELECT id, name FROM branches WHERE status = 'active' ORDER BY name");
    $branches = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

$display_branch_name = $user_branch_name;
foreach ($branches as $b) {
    if ($b['id'] == $selected_branch_id) { $display_branch_name = $b['name']; break; }
}

// DATE FILTERS
$quick_filter = $_GET['quick'] ?? '1m';
$date_from = $_GET['date_from'] ?? date('Y-m-d');
$date_to = $_GET['date_to'] ?? date('Y-m-d');
$date_label = "";
$date_cond_os = "";
$date_cond_bi = "";
$date_cond_b  = "";
$date_cond_p  = "";

switch ($quick_filter) {
    case 'today':
        $date_cond_os = " AND DATE(os.created_at) = CURDATE()";
        $date_cond_bi = " AND DATE(bi.created_at) = CURDATE()";
        $date_cond_b  = " AND DATE(b.created_at) = CURDATE()";
        $date_cond_p  = " AND DATE(p.received_at) = CURDATE()";
        $date_label = "Today"; break;
    case '1w':
        $date_cond_os = " AND os.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
        $date_cond_bi = " AND bi.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
        $date_cond_b  = " AND b.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
        $date_cond_p  = " AND p.received_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
        $date_label = "Last 1 Week"; break;
    case '1m':
        $date_cond_os = " AND os.created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
        $date_cond_bi = " AND bi.created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
        $date_cond_b  = " AND b.created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
        $date_cond_p  = " AND p.received_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
        $date_label = "Last 1 Month"; break;
    case '3m':
        $date_cond_os = " AND os.created_at >= DATE_SUB(NOW(), INTERVAL 3 MONTH)";
        $date_cond_bi = " AND bi.created_at >= DATE_SUB(NOW(), INTERVAL 3 MONTH)";
        $date_cond_b  = " AND b.created_at >= DATE_SUB(NOW(), INTERVAL 3 MONTH)";
        $date_cond_p  = " AND p.received_at >= DATE_SUB(NOW(), INTERVAL 3 MONTH)";
        $date_label = "Last 3 Months"; break;
    case '6m':
        $date_cond_os = " AND os.created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)";
        $date_cond_bi = " AND bi.created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)";
        $date_cond_b  = " AND b.created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)";
        $date_cond_p  = " AND p.received_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)";
        $date_label = "Last 6 Months"; break;
    case '1y':
        $date_cond_os = " AND os.created_at >= DATE_SUB(NOW(), INTERVAL 1 YEAR)";
        $date_cond_bi = " AND bi.created_at >= DATE_SUB(NOW(), INTERVAL 1 YEAR)";
        $date_cond_b  = " AND b.created_at >= DATE_SUB(NOW(), INTERVAL 1 YEAR)";
        $date_cond_p  = " AND p.received_at >= DATE_SUB(NOW(), INTERVAL 1 YEAR)";
        $date_label = "Last 1 Year"; break;
    case 'all':
        $date_label = "All Time"; break;
    case 'custom':
        $date_cond_os = " AND DATE(os.created_at) BETWEEN ? AND ?";
        $date_cond_bi = " AND DATE(bi.created_at) BETWEEN ? AND ?";
        $date_cond_b  = " AND DATE(b.created_at) BETWEEN ? AND ?";
        $date_cond_p  = " AND DATE(p.received_at) BETWEEN ? AND ?";
        $date_label = date('d M Y', strtotime($date_from)) . ' - ' . date('d M Y', strtotime($date_to)); break;
    default:
        $date_cond_os = " AND os.created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
        $date_cond_bi = " AND bi.created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
        $date_cond_b  = " AND b.created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
        $date_cond_p  = " AND p.received_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
        $date_label = "Last 1 Month";
}

$date_params = [];
if ($quick_filter === 'custom') $date_params = [$date_from, $date_to];

// ================================================================
// TAB 1: MEDICINES
// ================================================================
$med_query = "
    SELECT 
        MIN(m.id) as id,
        m.medication_name, m.category, m.unit, m.branch_id,
        u.full_name as added_by_full_name, b.name as branch_name,
        SUM(CASE WHEN m.status = 'active' AND (m.expiry_date IS NULL OR m.expiry_date >= CURDATE() OR m.expiry_date = '0000-00-00') THEN m.quantity ELSE 0 END) as total_quantity,
        MIN(m.reorder_level) as reorder_level,
        MIN(m.unit_cost) as unit_cost,
        MIN(m.selling_price) as selling_price,
        MIN(m.expiry_date) as expiry_date,
        GROUP_CONCAT(m.id) as batch_ids,
        GROUP_CONCAT(m.batch_number SEPARATOR '|') as batch_numbers,
        MIN(DATEDIFF(CASE WHEN m.expiry_date = '0000-00-00' THEN NULL ELSE m.expiry_date END, CURDATE())) as days_remaining,
        CASE 
            WHEN SUM(CASE WHEN m.status = 'active' AND (m.expiry_date IS NULL OR m.expiry_date >= CURDATE() OR m.expiry_date = '0000-00-00') THEN 1 ELSE 0 END) > 0 
            THEN 'active' ELSE 'inactive' 
        END as computed_status
    FROM medications_inventory m
    LEFT JOIN users u ON m.added_by = u.id
    LEFT JOIN branches b ON m.branch_id = b.id
    WHERE 1=1 $branch_cond_med
    GROUP BY m.medication_name, m.category, m.unit, m.branch_id
    ORDER BY m.medication_name ASC
";
$stmt = $db->prepare($med_query);
$stmt->execute($branch_params);
$medicines = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// TAB 2: EQUIPMENT
// ================================================================
$equip_query = "
    SELECT 
        MIN(e.id) as id,
        e.equipment_name, e.category, e.unit, e.branch_id,
        u.full_name as added_by_full_name, b.name as branch_name,
        SUM(CASE WHEN e.status = 'active' AND (e.expiry_date IS NULL OR e.expiry_date >= CURDATE() OR e.expiry_date = '0000-00-00') THEN e.quantity ELSE 0 END) as total_quantity,
        MIN(e.reorder_level) as reorder_level,
        MIN(e.unit_cost) as unit_cost,
        MIN(e.selling_price) as selling_price,
        MIN(e.expiry_date) as expiry_date,
        GROUP_CONCAT(e.id) as batch_ids,
        GROUP_CONCAT(e.batch_number SEPARATOR '|') as batch_numbers,
        MIN(DATEDIFF(CASE WHEN e.expiry_date = '0000-00-00' THEN NULL ELSE e.expiry_date END, CURDATE())) as days_remaining,
        CASE 
            WHEN SUM(CASE WHEN e.status = 'active' AND (e.expiry_date IS NULL OR e.expiry_date >= CURDATE() OR e.expiry_date = '0000-00-00') THEN 1 ELSE 0 END) > 0 
            THEN 'active' ELSE 'inactive' 
        END as computed_status
    FROM medical_equipment e
    LEFT JOIN users u ON e.added_by = u.id
    LEFT JOIN branches b ON e.branch_id = b.id
    WHERE 1=1 $branch_cond_eq
    GROUP BY e.equipment_name, e.category, e.unit, e.branch_id
    ORDER BY e.equipment_name ASC
";
$stmt = $db->prepare($equip_query);
if (!$stmt) {
    $equipment = [];
} else {
    $stmt->execute($branch_params);
    $equipment = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// STATS - MEDICINES
$med_total = 0; $med_in_stock = 0; $med_out_stock = 0; $med_low_stock = 0;
$med_value = 0; $med_total_qty = 0;

foreach ($medicines as $m) {
    $qty = (int)($m['total_quantity'] ?? 0);
    $med_total++;
    $med_total_qty += $qty;
    $med_value += $qty * (float)($m['selling_price'] ?? 0);
    if ($qty > 0) $med_in_stock++;
    else $med_out_stock++;
    if ($qty > 0 && $qty <= (int)($m['reorder_level'] ?? 0)) $med_low_stock++;
}

// STATS - EQUIPMENT
$eq_total = 0; $eq_in_stock = 0; $eq_out_stock = 0; $eq_low_stock = 0;
$eq_value = 0; $eq_total_qty = 0;

foreach ($equipment as $e) {
    $qty = (int)($e['total_quantity'] ?? 0);
    $eq_total++;
    $eq_total_qty += $qty;
    $eq_value += $qty * (float)($e['selling_price'] ?? 0);
    if ($qty > 0) $eq_in_stock++;
    else $eq_out_stock++;
    if ($qty > 0 && $qty <= (int)($e['reorder_level'] ?? 0)) $eq_low_stock++;
}

$total_inventory_value = $med_value + $eq_value;

// ================================================================
// PRESCRIPTIONS - GROSS PEKEE
// ================================================================
$presc_total_items_amount = 0;
$presc_total_items_count = 0;

try {
    $sql = "
        SELECT 
            COALESCE(SUM(bi.total_price), 0) as total_amount,
            COUNT(DISTINCT bi.id) as total_items
        FROM bill_items bi 
        INNER JOIN bills b ON bi.bill_id = b.id
        WHERE bi.reference_type = 'prescription'
        AND bi.item_type = 'medication'
        AND bi.status != 'cancelled'
        AND b.patient_id IS NOT NULL 
        AND b.visit_id IS NOT NULL 
        AND b.bill_number NOT LIKE 'BILL-OTC-%' 
        AND b.status IN ('paid', 'partial')
        AND b.id IN (
            SELECT DISTINCT p.bill_id 
            FROM payments p 
            WHERE p.bill_id IS NOT NULL
            $branch_cond_p $date_cond_p
        )
        $branch_cond_bi $date_cond_bi
    ";
    $stmt = $db->prepare($sql);
    $stmt->execute(array_merge(
        $branch_params, $date_params,
        $branch_params, $date_params
    ));
    $presc_result = $stmt->fetch(PDO::FETCH_ASSOC);
    $presc_total_items_amount = (float)($presc_result['total_amount'] ?? 0);
    $presc_total_items_count = (int)($presc_result['total_items'] ?? 0);
} catch (Exception $e) {}

// Pharmacy discount & premium (for display - si kwa hesabu ya GROSS)
$presc_pharmacy_discount = 0;
$presc_pharmacy_premium = 0;

try {
    $sql = "
        SELECT 
            COALESCE(SUM(b.pharmacy_discount), 0) as pharmacy_discount,
            COALESCE(SUM(b.pharmacy_premium), 0) as pharmacy_premium
        FROM bills b
        WHERE b.patient_id IS NOT NULL 
        AND b.visit_id IS NOT NULL
        AND b.bill_number NOT LIKE 'BILL-OTC-%'
        AND b.status IN ('paid', 'partial')
        AND b.id IN (
            SELECT DISTINCT p.bill_id 
            FROM payments p 
            WHERE p.bill_id IS NOT NULL
            $branch_cond_p $date_cond_p
        )
        $branch_cond_b $date_cond_b
    ";
    $stmt = $db->prepare($sql);
    $stmt->execute(array_merge(
        $branch_params, $date_params,
        $branch_params, $date_params
    ));
    $presc_disc_result = $stmt->fetch(PDO::FETCH_ASSOC);
    $presc_pharmacy_discount = (float)($presc_disc_result['pharmacy_discount'] ?? 0);
    $presc_pharmacy_premium = (float)($presc_disc_result['pharmacy_premium'] ?? 0);
} catch (Exception $e) {}

// PRESCRIPTION = GROSS PEKEE
$presc_gross = $presc_total_items_amount;
$presc_discount = $presc_pharmacy_discount;
$presc_premium = $presc_pharmacy_premium;
$presc_final_revenue = $presc_gross;

// Get patient-level data for prescriptions
$grouped_prescriptions = [];
$visits_map = [];

try {
    $sql = "
        SELECT DISTINCT
            pat.id as patient_id,
            pat.full_name as patient_name,
            pat.patient_id as patient_number,
            pat.phone as patient_phone,
            pat.gender as patient_gender,
            pat.date_of_birth,
            MAX(bi.created_at) as last_prescription_date
        FROM bill_items bi
        INNER JOIN bills b ON bi.bill_id = b.id
        INNER JOIN patients pat ON b.patient_id = pat.id
        WHERE bi.reference_type = 'prescription'
        AND bi.item_type = 'medication'
        AND bi.status != 'cancelled'
        AND b.patient_id IS NOT NULL
        AND b.visit_id IS NOT NULL
        AND b.bill_number NOT LIKE 'BILL-OTC-%'
        AND b.status IN ('paid', 'partial')
        AND b.id IN (
            SELECT DISTINCT p.bill_id 
            FROM payments p 
            WHERE p.bill_id IS NOT NULL
            $branch_cond_p $date_cond_p
        )
        $branch_cond_bi $date_cond_bi
        GROUP BY pat.id, pat.full_name, pat.patient_id, pat.phone, pat.gender, pat.date_of_birth
        ORDER BY last_prescription_date DESC
        LIMIT 200
    ";
    $stmt = $db->prepare($sql);
    $stmt->execute(array_merge(
        $branch_params, $date_params,
        $branch_params, $date_params
    ));
    $grouped_prescriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $patient_ids = array_column($grouped_prescriptions, 'patient_id');
    
    if (!empty($patient_ids)) {
        $placeholders = implode(',', array_fill(0, count($patient_ids), '?'));
        
        $stmt = $db->prepare("
            SELECT 
                bi.id as item_id, bi.bill_id, bi.item_name, bi.quantity, bi.unit_price,
                bi.total_price, bi.discount_amount, bi.final_price, bi.status as item_status,
                bi.reference_id as prescription_id, bi.created_at as item_created_at,
                b.id as bill_id, b.bill_number, b.visit_id, b.patient_id, 
                b.created_by as bill_created_by, b.status as bill_status,
                b.updated_at as bill_updated_at, b.paid_amount as bill_paid,
                b.premium_amount, b.total_discount, b.pharmacy_discount, b.pharmacy_premium,
                v.visit_number, v.visit_date,
                u_doctor.full_name as doctor_name,
                u_cashier.full_name as cashier_name, u_cashier.role as cashier_role,
                u_cashier.profile_pic as cashier_pic,
                p.prescription_number, p.status as prescription_status
            FROM bill_items bi
            INNER JOIN bills b ON bi.bill_id = b.id
            LEFT JOIN visits v ON b.visit_id = v.id
            LEFT JOIN users u_doctor ON v.doctor_id = u_doctor.id
            LEFT JOIN users u_cashier ON b.created_by = u_cashier.id
            LEFT JOIN prescriptions p ON bi.reference_id = p.id
            WHERE bi.reference_type = 'prescription'
            AND bi.item_type = 'medication'
            AND bi.status != 'cancelled'
            AND b.patient_id IN ($placeholders)
            AND b.status IN ('paid', 'partial')
            ORDER BY b.visit_id DESC, bi.created_at DESC, bi.id ASC
        ");
        $stmt->execute($patient_ids);
        $all_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($all_items as $item) {
            $pid = $item['patient_id'];
            $vid = $item['visit_id'] ?? 0;
            $bill_id = $item['bill_id'];
            $item_status = strtolower($item['item_status'] ?? 'pending');
            $bill_status = strtolower($item['bill_status'] ?? 'pending');
            
            if (!isset($visits_map[$pid])) $visits_map[$pid] = [];
            
            if (!isset($visits_map[$pid][$vid])) {
                $visits_map[$pid][$vid] = [
                    'visit_id' => $vid,
                    'visit_number' => $item['visit_number'] ?? ($vid > 0 ? 'VIS-' . $vid : 'NO-VISIT'),
                    'visit_date' => $item['visit_date'] ?? $item['item_created_at'],
                    'doctor_names' => [], 'cashier_names' => [],
                    'items' => [], 'statuses' => [], 'bill_statuses' => [], 'dates' => [],
                    'total_qty' => 0, 'medication_count' => 0,
                    'total_amount' => 0, 'paid_amount' => 0, 'pending_amount' => 0,
                    'premium_amount' => 0, 'discount_amount' => 0,
                    'bill_ids' => [],
                    'received_by' => null, 'received_by_role' => null,
                    'received_by_pic' => null, 'payment_received_at' => null,
                ];
            }
            
            if (!empty($item['doctor_name']) && !in_array($item['doctor_name'], $visits_map[$pid][$vid]['doctor_names'])) {
                $visits_map[$pid][$vid]['doctor_names'][] = $item['doctor_name'];
            }
            
            if ($bill_status === 'paid' || $item_status === 'paid') {
                if (!empty($item['cashier_name']) && !in_array($item['cashier_name'], $visits_map[$pid][$vid]['cashier_names'])) {
                    $visits_map[$pid][$vid]['cashier_names'][] = $item['cashier_name'];
                    $visits_map[$pid][$vid]['received_by'] = $item['cashier_name'];
                    $visits_map[$pid][$vid]['received_by_role'] = $item['cashier_role'];
                    $visits_map[$pid][$vid]['received_by_pic'] = $item['cashier_pic'];
                    $visits_map[$pid][$vid]['payment_received_at'] = $item['bill_updated_at'];
                }
            }
            
            $visits_map[$pid][$vid]['statuses'][] = $item_status;
            $visits_map[$pid][$vid]['bill_statuses'][] = $bill_status;
            
            if (!isset($visits_map[$pid][$vid]['bill_ids'][$bill_id])) {
                $visits_map[$pid][$vid]['bill_ids'][$bill_id] = [
                    'total' => 0,
                    'paid' => (float)($item['bill_paid'] ?? 0),
                    'status' => $bill_status,
                    'premium' => (float)($item['pharmacy_premium'] ?? 0),
                    'discount' => (float)($item['pharmacy_discount'] ?? 0),
                ];
            }
            
            if (!empty($item['item_created_at'])) {
                $visits_map[$pid][$vid]['dates'][] = $item['item_created_at'];
            }
            
            // GROSS price (bila discount kwenye item)
            $item_total_price = (float)($item['total_price'] ?? 0);
            $item_discount = (float)($item['discount_amount'] ?? 0);
            $item_final = $item_total_price;
            
            $visits_map[$pid][$vid]['items'][] = [
                'item_id' => (int)($item['item_id'] ?? 0),
                'bill_id' => (int)($item['bill_id'] ?? 0),
                'medication_name' => $item['item_name'],
                'quantity' => (int)($item['quantity'] ?? 0),
                'unit_price' => (float)($item['unit_price'] ?? 0),
                'total_price' => $item_total_price,
                'discount_amount' => $item_discount,
                'final_price' => $item_final,
                'status' => $item_status,
                'bill_status' => $bill_status,
                'prescription_id' => (int)($item['prescription_id'] ?? 0),
                'prescription_number' => $item['prescription_number'] ?? 'N/A',
                'prescription_date' => $item['item_created_at'],
            ];
            
            $visits_map[$pid][$vid]['total_qty'] += (int)($item['quantity'] ?? 0);
            $visits_map[$pid][$vid]['medication_count']++;
            $visits_map[$pid][$vid]['total_amount'] += $item_final;
            $visits_map[$pid][$vid]['bill_ids'][$bill_id]['total'] += $item_final;
        }
        
        foreach ($visits_map as $pid => $patient_visits) {
            if (!is_array($patient_visits)) continue;
            foreach ($patient_visits as $vid => $visit) {
                if (!is_array($visit)) continue;
                
                $statuses = (isset($visit['statuses']) && is_array($visit['statuses'])) ? $visit['statuses'] : [];
                $bill_statuses = (isset($visit['bill_statuses']) && is_array($visit['bill_statuses'])) ? $visit['bill_statuses'] : [];
                
                $overall = 'pending';
                if (in_array('pending', $statuses)) $overall = 'pending';
                elseif (in_array('confirmed', $statuses)) $overall = 'confirmed';
                elseif (in_array('dispensed', $statuses)) $overall = 'dispensed';
                elseif (in_array('cancelled', $statuses)) $overall = 'cancelled';
                elseif (in_array('paid', $statuses)) $overall = 'paid';
                
                $all_paid = !empty($bill_statuses) && !in_array('pending', $bill_statuses) && !in_array('partial', $bill_statuses);
                if ($all_paid && !in_array('cancelled', $statuses)) {
                    $overall = 'paid';
                }
                
                $visits_map[$pid][$vid]['overall_status'] = $overall;
                
                $total_bill = 0; $paid_bill = 0;
                $visit_premium = 0; $visit_discount = 0;
                if (isset($visit['bill_ids']) && is_array($visit['bill_ids'])) {
                    foreach ($visit['bill_ids'] as $bill_data) {
                        $total_bill += (float)($bill_data['total'] ?? 0);
                        $paid_bill += (float)($bill_data['paid'] ?? 0);
                        $visit_premium += (float)($bill_data['premium'] ?? 0);
                        $visit_discount += (float)($bill_data['discount'] ?? 0);
                    }
                }
                
                if ($overall === 'paid') $paid_bill = $total_bill;
                
                $visits_map[$pid][$vid]['paid_amount'] = $paid_bill;
                $visits_map[$pid][$vid]['pending_amount'] = max(0, $total_bill - $paid_bill);
                $visits_map[$pid][$vid]['premium_amount'] = $visit_premium;
                $visits_map[$pid][$vid]['discount_amount'] = $visit_discount;
                
                $dates = (isset($visit['dates']) && is_array($visit['dates'])) ? $visit['dates'] : [];
                rsort($dates);
                $visits_map[$pid][$vid]['dates'] = $dates;
                $visits_map[$pid][$vid]['latest_date'] = !empty($dates) ? $dates[0] : null;
            }
        }
    }
    
    foreach ($grouped_prescriptions as &$group) {
        $pid = $group['patient_id'];
        $patient_visits = isset($visits_map[$pid]) ? $visits_map[$pid] : [];
        
        $total_qty = 0; $medication_count = 0; $total_amount = 0;
        $paid_amount = 0; $pending_amount = 0;
        $patient_premium = 0; $patient_discount = 0;
        $all_doctors = [];
        
        foreach ($patient_visits as $visit) {
            if (!is_array($visit)) continue;
            $total_qty += (int)($visit['total_qty'] ?? 0);
            $medication_count += (int)($visit['medication_count'] ?? 0);
            $total_amount += (float)($visit['total_amount'] ?? 0);
            $paid_amount += (float)($visit['paid_amount'] ?? 0);
            $pending_amount += (float)($visit['pending_amount'] ?? 0);
            $patient_premium += (float)($visit['premium_amount'] ?? 0);
            $patient_discount += (float)($visit['discount_amount'] ?? 0);
            
            if (isset($visit['doctor_names']) && is_array($visit['doctor_names'])) {
                foreach ($visit['doctor_names'] as $doc) {
                    if (!in_array($doc, $all_doctors)) $all_doctors[] = $doc;
                }
            }
        }
        
        $group['visits'] = $patient_visits;
        $group['total_qty'] = $total_qty;
        $group['medication_count'] = $medication_count;
        $group['total_amount'] = $total_amount;
        $group['paid_amount'] = $paid_amount;
        $group['pending_amount'] = $pending_amount;
        $group['patient_premium'] = $patient_premium;
        $group['patient_discount'] = $patient_discount;
        $group['doctor_names_array'] = $all_doctors;
    }
    unset($group);
    
} catch (Exception $e) {
    error_log("Prescriptions error: " . $e->getMessage());
}

// ================================================================
// OTC SALES
// ================================================================
$otc_sales = [];
try {
    $sql = "SELECT 
        os.id as sale_id, os.sale_number, os.customer_name, os.customer_phone,
        os.subtotal, os.discount_amount, os.premium_amount, os.premium_note, 
        os.total_amount, os.payment_method, os.payment_status, os.created_at, 
        os.updated_at, os.notes, os.bill_id,
        COALESCE(u.full_name, 'N/A') as sold_by_name,
        COALESCE(u.role, 'user') as sold_by_role,
        COALESCE(b.name, 'N/A') as branch_name,
        (SELECT COUNT(*) FROM otc_sale_items WHERE sale_id = os.id) as item_count,
        (SELECT COALESCE(SUM(quantity), 0) FROM otc_sale_items WHERE sale_id = os.id) as total_qty
    FROM otc_sales os
    LEFT JOIN users u ON os.sold_by = u.id
    LEFT JOIN branches b ON os.branch_id = b.id
    WHERE 1=1 $branch_cond_os $date_cond_os
    ORDER BY os.created_at DESC
    LIMIT 500";
    
    $stmt = $db->prepare($sql);
    $stmt->execute(array_merge($branch_params, $date_params));
    $otc_sales = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("OTC error: " . $e->getMessage());
}

// Fetch items for each OTC sale
$otc_items_by_sale = [];
if (!empty($otc_sales)) {
    try {
        $sale_ids = array_column($otc_sales, 'sale_id');
        $placeholders = implode(',', array_fill(0, count($sale_ids), '?'));
        $stmt = $db->prepare("SELECT * FROM otc_sale_items WHERE sale_id IN ($placeholders) ORDER BY id ASC");
        $stmt->execute($sale_ids);
        $all_otc_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($all_otc_items as $item) {
            $otc_items_by_sale[$item['sale_id']][] = $item;
        }
        
        foreach ($otc_sales as &$sale) {
            $sale['items'] = $otc_items_by_sale[$sale['sale_id']] ?? [];
        }
        unset($sale);
    } catch (Exception $e) {}
}

// ================================================================
// STATS - PRESCRIPTIONS (GROSS PEKEE)
// ================================================================
$presc_total_amount = $presc_gross;
$presc_paid_amount = 0;
$presc_pending_amount = 0;
$presc_premium = 0;
$presc_discount = 0;

foreach ($grouped_prescriptions as $group) {
    $presc_paid_amount += (float)($group['paid_amount'] ?? 0);
    $presc_pending_amount += (float)($group['pending_amount'] ?? 0);
}

// STATS - OTC
$otc_total = 0; $otc_total_amount = 0; $otc_paid_amount = 0;
$otc_pending_amount = 0; $otc_premium = 0; $otc_discount = 0; $otc_items_total = 0;

foreach ($otc_sales as $sale) {
    $otc_total++;
    $amount = (float)($sale['total_amount'] ?? 0);
    $status = strtolower($sale['payment_status'] ?? 'pending');
    $otc_total_amount += $amount;
    $otc_items_total += (int)($sale['total_qty'] ?? 0);
    $otc_premium += (float)($sale['premium_amount'] ?? 0);
    $otc_discount += (float)($sale['discount_amount'] ?? 0);
    
    if ($status === 'paid') $otc_paid_amount += $amount;
    else $otc_pending_amount += $amount;
}

$total_paid = $presc_paid_amount + $otc_paid_amount;
$total_discount = $otc_discount;
$total_premium = $otc_premium;

$logo_path = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';
$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

include_once $_SERVER['DOCUMENT_ROOT'] . '/dispensary_system/frontend/components/audit_header.php';
include_once $_SERVER['DOCUMENT_ROOT'] . '/dispensary_system/frontend/components/audit_sidebar.php';
?>

<!DOCTYPE html>
<html lang="en" data-theme="<?= isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory & Pharmacy Sales - Braick Audit</title>
    <link rel="icon" href="<?= $logo_path ?>" type="image/png">
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=JetBrains+Mono:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
:root {
    --font-primary: 'Inter', -apple-system, sans-serif;
    --font-mono: 'JetBrains Mono', 'Courier New', monospace;
    --primary: #0B5ED7;
    --primary-dark: #0A4CA8;
    --primary-light: #3B82F6;
    --primary-bg: #E8F0FE;
    --bg-body: #F1F5F9;
    --bg-card: #FFFFFF;
    --text-primary: #1E293B;
    --text-secondary: #64748B;
    --border-color: #E2E8F0;
    --success: #059669;
    --success-bg: #D1FAE5;
    --danger: #DC2626;
    --danger-bg: #FEE2E2;
    --warning: #D97706;
    --warning-bg: #FEF3C7;
    --purple: #7C3AED;
    --purple-bg: #EDE9FE;
    --cyan: #0891B2;
    --cyan-bg: #CFFAFE;
    --teal: #0D9488;
    --teal-bg: #CCFBF1;
    --shadow-sm: 0 1px 3px rgba(0,0,0,0.06);
    --shadow-md: 0 4px 12px rgba(0,0,0,0.08);
    --shadow-lg: 0 10px 25px rgba(0,0,0,0.1);
    --shadow-xl: 0 20px 50px rgba(0,0,0,0.15);
    --radius-sm: 8px;
    --radius-md: 12px;
    --radius-lg: 16px;
    --radius-xl: 20px;
    --radius-full: 9999px;
}
[data-theme="dark"] {
    --bg-body: #0B1220;
    --bg-card: #111C33;
    --text-primary: #F1F5F9;
    --text-secondary: #94A3B8;
    --border-color: #1E2E4A;
    --primary: #3B82F6;
    --primary-dark: #2563EB;
    --primary-light: #60A5FA;
    --primary-bg: #12294A;
    --success-bg: #0F2E22;
    --danger-bg: #3A1414;
    --warning-bg: #3A2A0F;
    --purple-bg: #2A1A4A;
    --cyan-bg: #0A2E3A;
    --teal-bg: #0A2E2A;
}
* { font-family: var(--font-primary); -webkit-font-smoothing: antialiased; margin: 0; padding: 0; box-sizing: border-box; }
html, body { font-family: var(--font-primary); background: var(--bg-body); color: var(--text-primary); }
.money-number, .stat-value, .money-cell, .font-mono, .mono, .rx-number, .items-count-badge {
    font-family: var(--font-mono) !important;
    font-feature-settings: 'tnum';
    font-variant-numeric: tabular-nums;
    letter-spacing: -0.02em;
}

.page-header {
    background: linear-gradient(135deg, #0B5ED7 0%, #0A4CA8 100%);
    border-radius: 16px; padding: 20px 24px; margin-bottom: 20px;
    display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center;
    gap: 14px; box-shadow: 0 6px 20px rgba(11, 94, 215, 0.25);
    position: relative; overflow: hidden;
}
.page-header::before {
    content: ''; position: absolute; top: -50%; right: -20%;
    width: 350px; height: 350px;
    background: radial-gradient(circle, rgba(255,255,255,0.08) 0%, transparent 70%);
    border-radius: 50%; pointer-events: none;
}
.page-header .page-title {
    color: white; font-size: 1.4rem; font-weight: 800;
    display: flex; align-items: center; gap: 10px; flex-wrap: wrap;
    position: relative; z-index: 1; letter-spacing: -0.02em;
}
.page-header .page-title i { font-size: 1.5rem; color: #93C5FD; }
.page-header .page-subtitle {
    color: rgba(255,255,255,0.85); font-size: 0.78rem;
    display: flex; align-items: center; gap: 8px; flex-wrap: wrap;
    margin-top: 4px; position: relative; z-index: 1;
}
.branch-tag {
    background: rgba(255,255,255,0.15); color: white;
    padding: 3px 10px; border-radius: 16px; font-size: 0.65rem;
    font-weight: 500; display: inline-flex; align-items: center; gap: 4px;
    backdrop-filter: blur(4px); border: 1px solid rgba(255,255,255,0.1);
}
.branch-tag.filter-tag { background: linear-gradient(135deg, #10B981, #059669); font-weight: 700; }
.branch-tag.user-tag {
    background: linear-gradient(135deg, #FCD34D, #F59E0B);
    color: #78350F;
    font-weight: 800;
    box-shadow: 0 2px 8px rgba(252, 211, 77, 0.3);
}

.btn-header {
    background: rgba(255,255,255,0.15); color: white;
    border: 1px solid rgba(255,255,255,0.25);
    padding: 8px 14px; border-radius: 9px;
    font-weight: 600; font-size: 0.75rem;
    transition: all 0.3s ease; text-decoration: none;
    display: inline-flex; align-items: center; gap: 6px;
    backdrop-filter: blur(4px); position: relative; z-index: 1; cursor: pointer;
}
.btn-header:hover { background: rgba(255,255,255,0.28); transform: translateY(-2px); }

.filter-card {
    background: var(--bg-card); border-radius: 14px; padding: 16px 18px;
    border: 2px solid var(--border-color); margin-bottom: 18px;
    box-shadow: var(--shadow-sm);
}
.filter-section-title {
    font-size: 0.68rem; font-weight: 800; text-transform: uppercase;
    letter-spacing: 0.06em; color: var(--text-secondary);
    margin-bottom: 8px; display: flex; align-items: center; gap: 5px;
}
.quick-filters { display: flex; gap: 6px; flex-wrap: wrap; margin-bottom: 14px; }
.quick-btn {
    padding: 6px 12px; border-radius: 8px;
    border: 2px solid var(--border-color); background: var(--bg-card);
    color: var(--text-secondary); font-weight: 700; font-size: 0.7rem;
    cursor: pointer; transition: all 0.3s ease;
    display: inline-flex; align-items: center; gap: 5px; text-decoration: none;
}
.quick-btn:hover { border-color: var(--primary); color: var(--primary); transform: translateY(-2px); }
.quick-btn.active {
    background: linear-gradient(135deg, var(--primary), var(--primary-dark));
    color: white; border-color: transparent;
    box-shadow: 0 4px 10px rgba(11, 94, 215, 0.3);
}
.filter-form {
    display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 10px; align-items: end;
    padding-top: 12px; border-top: 2px dashed var(--border-color);
}
.filter-group { display: flex; flex-direction: column; gap: 4px; }
.filter-group label {
    font-size: 0.65rem; font-weight: 700;
    text-transform: uppercase; letter-spacing: 0.05em;
    color: var(--text-secondary);
    display: flex; align-items: center; gap: 4px;
}
.filter-group input, .filter-group select {
    padding: 8px 12px; border-radius: 8px;
    border: 2px solid var(--border-color); background: var(--bg-card);
    color: var(--text-primary); font-size: 0.78rem; font-weight: 600;
    outline: none; height: 36px;
}
.filter-btn-primary {
    padding: 8px 16px; border-radius: 8px;
    background: linear-gradient(135deg, var(--primary), var(--primary-dark));
    color: white; border: none; font-weight: 700; font-size: 0.75rem;
    cursor: pointer; height: 36px;
    display: inline-flex; align-items: center; gap: 6px;
    justify-content: center;
}
.filter-btn-secondary {
    padding: 8px 16px; border-radius: 8px;
    background: transparent; color: var(--text-secondary);
    border: 2px solid var(--border-color);
    font-weight: 700; font-size: 0.75rem; cursor: pointer;
    height: 36px; text-decoration: none;
    display: inline-flex; align-items: center; gap: 6px;
    justify-content: center;
}

.stats-grid {
    display: grid; grid-template-columns: repeat(4, 1fr);
    gap: 14px; margin-bottom: 20px;
}
.stats-grid.grid-7 { grid-template-columns: repeat(7, 1fr); }

.stat-card {
    background: var(--bg-card); border-radius: 14px; padding: 14px 16px;
    border: 2px solid var(--border-color);
    transition: all 0.35s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: var(--shadow-sm); position: relative; overflow: hidden;
    min-height: 130px; display: flex; flex-direction: column;
    justify-content: space-between;
}
.stat-card::before {
    content: ''; position: absolute; top: 0; left: 0; right: 0;
    height: 3px;
}
.stat-card:hover { transform: translateY(-4px); box-shadow: var(--shadow-lg); }
.stat-card .stat-icon {
    width: 36px; height: 36px; border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1rem; color: white; margin-bottom: 8px;
}
.stat-card .stat-label {
    font-size: 0.62rem; color: var(--text-secondary);
    font-weight: 700; text-transform: uppercase;
    letter-spacing: 0.06em; margin-bottom: 4px;
}
.stat-card .stat-value {
    font-size: 1.25rem; font-weight: 900; color: var(--text-primary);
    line-height: 1.1; letter-spacing: -0.03em;
    display: flex; align-items: baseline; gap: 4px; flex-wrap: wrap;
}
.stat-card .stat-value .currency-symbol {
    font-size: 0.72rem; font-weight: 700;
    color: var(--text-secondary); font-family: var(--font-primary);
}
.stat-card .stat-sub {
    font-size: 0.6rem; color: var(--text-secondary);
    margin-top: 8px; padding-top: 8px;
    border-top: 1px dashed var(--border-color);
    display: flex; align-items: center; gap: 4px; font-weight: 600;
}
.stat-card.blue::before { background: linear-gradient(90deg, #0B5ED7, #3B82F6); }
.stat-card.blue .stat-icon { background: linear-gradient(135deg, #0B5ED7, #3B82F6); }
.stat-card.blue .stat-value .money-number { color: var(--primary); }
.stat-card.green::before { background: linear-gradient(90deg, #059669, #34D399); }
.stat-card.green .stat-icon { background: linear-gradient(135deg, #059669, #34D399); }
.stat-card.green .stat-value .money-number { color: var(--success); }
.stat-card.orange::before { background: linear-gradient(90deg, #D97706, #FBBF24); }
.stat-card.orange .stat-icon { background: linear-gradient(135deg, #D97706, #FBBF24); }
.stat-card.orange .stat-value .money-number { color: var(--warning); }
.stat-card.red::before { background: linear-gradient(90deg, #DC2626, #F87171); }
.stat-card.red .stat-icon { background: linear-gradient(135deg, #DC2626, #F87171); }
.stat-card.red .stat-value .money-number { color: var(--danger); }
.stat-card.purple::before { background: linear-gradient(90deg, #7C3AED, #A78BFA); }
.stat-card.purple .stat-icon { background: linear-gradient(135deg, #7C3AED, #A78BFA); }
.stat-card.purple .stat-value .money-number { color: var(--purple); }
.stat-card.cyan::before { background: linear-gradient(90deg, #0891B2, #06B6D4); }
.stat-card.cyan .stat-icon { background: linear-gradient(135deg, #0891B2, #06B6D4); }
.stat-card.cyan .stat-value .money-number { color: var(--cyan); }

.stat-card.paid-total {
    background: linear-gradient(135deg, #059669 0%, #047857 100%);
    border-color: #059669; color: white;
}
.stat-card.paid-total .stat-icon { background: rgba(255,255,255,0.25); }
.stat-card.paid-total .stat-label { color: rgba(255,255,255,0.9); }
.stat-card.paid-total .stat-value { color: white; }
.stat-card.paid-total .stat-value .currency-symbol { color: rgba(255,255,255,0.8); }
.stat-card.paid-total .stat-value .money-number { color: white !important; }
.stat-card.paid-total .stat-sub { color: rgba(255,255,255,0.85); border-top-color: rgba(255,255,255,0.25); }

.section-label {
    font-size: 0.75rem; font-weight: 800; color: var(--text-secondary);
    text-transform: uppercase; letter-spacing: 0.06em;
    margin-bottom: 10px; margin-top: 8px;
    display: flex; align-items: center; gap: 6px;
}

.tabs-container {
    display: flex; gap: 4px; background: var(--bg-card);
    border-radius: 12px; padding: 4px;
    border: 2px solid var(--border-color); margin-bottom: 20px;
    flex-wrap: wrap;
}
.tab-btn {
    padding: 10px 24px; border-radius: 10px;
    font-weight: 700; font-size: 0.85rem;
    border: none; cursor: pointer;
    background: transparent; color: var(--text-secondary);
    display: inline-flex; align-items: center; gap: 8px;
    flex: 1; justify-content: center; transition: all 0.3s ease;
    min-width: 200px;
}
.tab-btn:hover { background: var(--primary-bg); color: var(--primary); }
.tab-btn.active { background: var(--primary); color: white; }
.tab-btn .badge {
    background: rgba(255,255,255,0.2); color: white;
    padding: 1px 8px; border-radius: 10px; font-size: 0.65rem;
    font-family: var(--font-mono); font-weight: 800;
}
.tab-btn:not(.active) .badge { background: var(--border-color); color: var(--text-secondary); }
.tab-content { display: none; }
.tab-content.active { display: block; }

.sub-tabs {
    display: flex; gap: 6px; margin-bottom: 16px;
    padding: 8px; background: var(--bg-body);
    border-radius: 12px; border: 2px solid var(--border-color);
    flex-wrap: wrap;
}
.sub-tab-btn {
    padding: 8px 20px; border-radius: 8px;
    font-weight: 700; font-size: 0.78rem;
    border: 2px solid transparent; cursor: pointer;
    background: transparent; color: var(--text-secondary);
    display: inline-flex; align-items: center; gap: 6px;
    transition: all 0.3s ease;
}
.sub-tab-btn:hover { background: var(--bg-card); color: var(--primary); }
.sub-tab-btn.active {
    background: linear-gradient(135deg, var(--primary), var(--primary-dark));
    color: white; box-shadow: 0 4px 10px rgba(11, 94, 215, 0.3);
}
.sub-tab-content { display: none; }
.sub-tab-content.active { display: block; }

.table-card {
    background: var(--bg-card); border-radius: 14px;
    border: 2px solid var(--border-color); overflow: hidden;
    box-shadow: var(--shadow-sm); margin-bottom: 18px;
}
.table-card .table-header {
    padding: 12px 18px;
    background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
    display: flex; justify-content: space-between; align-items: center;
    flex-wrap: wrap; gap: 8px;
}
.table-card .table-header .title {
    color: white; font-size: 0.85rem; font-weight: 800;
    display: flex; align-items: center; gap: 8px;
}
.table-card .table-header .title i { color: #93C5FD; }
.table-card .table-header .count {
    color: rgba(255,255,255,0.9); font-size: 0.68rem; font-weight: 700;
    background: rgba(255,255,255,0.15); padding: 3px 10px;
    border-radius: 10px;
}

.table-toolbar {
    display: flex; justify-content: space-between; align-items: center;
    gap: 10px; flex-wrap: wrap; padding: 10px 16px;
    background: var(--primary-bg); border-bottom: 2px solid var(--border-color);
}
.table-toolbar-left { display: flex; align-items: center; gap: 8px; flex: 1; min-width: 200px; }
.table-toolbar-right { display: flex; align-items: center; gap: 6px; }
.search-box { position: relative; flex: 1; max-width: 450px; }
.search-box input {
    width: 100%; padding: 8px 32px 8px 32px;
    border-radius: 8px; border: 2px solid var(--border-color);
    background: var(--bg-card); color: var(--text-primary);
    font-size: 0.78rem; font-weight: 500; outline: none;
    height: 36px;
}
.search-box input:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.15); }
.search-box .search-icon {
    position: absolute; left: 10px; top: 50%;
    transform: translateY(-50%); color: var(--text-secondary);
    font-size: 0.75rem; pointer-events: none;
}
.search-box .search-clear {
    position: absolute; right: 6px; top: 50%;
    transform: translateY(-50%); background: transparent;
    border: none; color: var(--text-secondary); cursor: pointer;
    font-size: 0.75rem; padding: 4px 6px; border-radius: 5px;
    display: none;
}
.search-box.has-value .search-clear { display: block; }
.search-count {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 4px 10px; border-radius: 8px; font-size: 0.65rem;
    font-weight: 700; background: var(--primary); color: white;
    white-space: nowrap; height: 28px;
}
.search-count.has-results { background: var(--success); }
.search-count.no-results { background: var(--danger); }
.scroll-btn {
    width: 32px; height: 32px; border-radius: 8px;
    border: 2px solid var(--border-color); background: var(--bg-card);
    color: var(--text-primary); cursor: pointer;
    display: inline-flex; align-items: center; justify-content: center;
    font-size: 0.75rem; font-weight: 700;
    flex-shrink: 0;
}
.scroll-btn:hover { background: var(--primary); color: white; border-color: var(--primary); }
.table-scroll-wrapper { overflow-x: auto; }
.table-scroll-wrapper::-webkit-scrollbar { height: 6px; }
.table-scroll-wrapper::-webkit-scrollbar-track { background: var(--border-color); border-radius: 10px; }
.table-scroll-wrapper::-webkit-scrollbar-thumb { background: var(--primary); border-radius: 10px; }

.data-table { width: 100%; border-collapse: collapse; font-size: 0.78rem; }
.data-table thead th {
    text-align: left; padding: 9px 12px;
    font-weight: 800; font-size: 0.6rem; text-transform: uppercase;
    letter-spacing: 0.06em; color: white;
    background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
    white-space: nowrap;
}
.data-table tbody td {
    padding: 9px 12px; border-bottom: 1px solid var(--border-color);
    color: var(--text-primary); vertical-align: middle; font-weight: 500;
}
.data-table tbody tr:hover td { background: var(--primary-bg); }
.data-table tbody tr:last-child td { border-bottom: none; }
.data-table tbody tr.hidden-row { display: none !important; }

.medication-name-cell {
    font-weight: 700; color: var(--primary);
    display: flex; align-items: center; gap: 6px; padding: 4px 0;
}
.medication-name-cell i { color: var(--primary); font-size: 0.7rem; }
.items-count-badge {
    display: inline-flex; align-items: center;
    justify-content: center; gap: 4px;
    padding: 4px 10px; border-radius: 8px;
    background: linear-gradient(135deg, #0B5ED7, #3B82F6);
    color: white; font-weight: 800; font-size: 0.72rem;
    font-family: var(--font-mono); min-width: 36px;
}
.money-cell {
    font-family: var(--font-mono); font-weight: 800;
    font-size: 0.8rem; color: var(--success);
    text-align: right; letter-spacing: -0.02em;
}
.money-cell .currency-prefix {
    font-size: 0.65rem; color: var(--text-secondary);
    margin-right: 2px; font-family: var(--font-primary); font-weight: 600;
}
.stock-badge, .status-badge, .expiry-badge {
    display: inline-flex; align-items: center; gap: 3px;
    padding: 3px 8px; border-radius: 8px;
    font-size: 0.6rem; font-weight: 800;
    text-transform: uppercase; white-space: nowrap;
}
.stock-badge.ok { background: var(--success-bg); color: var(--success); border: 1px solid var(--success); }
.stock-badge.low { background: var(--warning-bg); color: var(--warning); border: 1px solid var(--warning); }
.stock-badge.out { background: var(--danger-bg); color: var(--danger); border: 1px solid var(--danger); }
.status-badge.active, .status-badge.paid, .status-badge.dispensed { background: var(--success-bg); color: var(--success); border: 1px solid var(--success); }
.status-badge.inactive, .status-badge.cancelled { background: var(--danger-bg); color: var(--danger); border: 1px solid var(--danger); }
.status-badge.pending { background: var(--warning-bg); color: var(--warning); border: 1px solid var(--warning); }
.status-badge.confirmed { background: var(--primary-bg); color: var(--primary); border: 1px solid var(--primary); }
.status-badge.partial { background: var(--cyan-bg); color: var(--cyan); border: 1px solid var(--cyan); }
.expiry-badge.valid { background: var(--success-bg); color: var(--success); }
.expiry-badge.expiring { background: var(--warning-bg); color: var(--warning); }
.expiry-badge.expired { background: var(--danger-bg); color: var(--danger); }
.expiry-badge.no-expiry { background: var(--border-color); color: var(--text-secondary); }
.batch-number, .rx-number {
    font-family: var(--font-mono); font-size: 0.6rem;
    font-weight: 700; padding: 2px 6px; border-radius: 4px;
    background: var(--primary-bg); color: var(--primary);
}
.added-by-tag, .payment-badge {
    display: inline-flex; align-items: center; gap: 3px;
    padding: 3px 8px; border-radius: 8px;
    font-size: 0.62rem; font-weight: 700;
    background: var(--purple-bg); color: var(--purple);
}
.payment-badge { background: var(--primary-bg); color: var(--primary); }

.received-by-badge {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 3px 9px; border-radius: 8px;
    font-size: 0.65rem; font-weight: 700;
    background: var(--success-bg); color: var(--success);
    border: 1px solid var(--success);
}
.received-by-empty {
    display: inline-flex; align-items: center; gap: 3px;
    font-size: 0.65rem; color: var(--text-secondary);
    font-style: italic;
    padding: 3px 8px; border-radius: 6px;
    background: var(--bg-body);
}
.premium-badge {
    display: inline-flex; align-items: center; gap: 3px;
    padding: 3px 9px; border-radius: 8px;
    font-size: 0.62rem; font-weight: 800;
    background: var(--purple-bg); color: var(--purple);
    border: 1px solid rgba(124, 58, 237, 0.3);
    font-family: var(--font-mono);
}
.discount-badge {
    display: inline-flex; align-items: center; gap: 3px;
    padding: 3px 9px; border-radius: 8px;
    font-size: 0.62rem; font-weight: 800;
    background: var(--warning-bg); color: var(--warning);
    border: 1px solid rgba(217, 119, 6, 0.3);
    font-family: var(--font-mono);
}

/* OTC SALE CARD */
.otc-sale-card {
    background: var(--bg-card);
    border: 2px solid var(--cyan);
    border-radius: var(--radius-lg);
    margin: 16px;
    overflow: hidden;
    box-shadow: 0 4px 16px rgba(8, 145, 178, 0.1);
    transition: all 0.3s ease;
}
.otc-sale-card:hover {
    box-shadow: 0 8px 28px rgba(8, 145, 178, 0.2);
    border-color: #22D3EE;
}

.otc-sale-header {
    background: linear-gradient(135deg, #0891B2, #0E7490);
    padding: 14px 20px;
    display: flex;
    flex-wrap: wrap;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    color: white;
}

.otc-header-left {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
    flex: 1;
    min-width: 300px;
}

.otc-sale-id-badge {
    background: rgba(255,255,255,0.2);
    backdrop-filter: blur(10px);
    border: 1.5px solid rgba(255,255,255,0.4);
    color: white;
    padding: 6px 14px;
    border-radius: var(--radius-sm);
    font-size: 0.78rem;
    font-weight: 900;
    font-family: var(--font-mono);
    display: inline-flex;
    align-items: center;
    gap: 6px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.15);
}

.otc-customer-info {
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.otc-customer-name {
    font-size: 0.88rem;
    font-weight: 900;
    display: flex;
    align-items: center;
    gap: 6px;
    color: white;
}

.otc-customer-phone {
    font-size: 0.68rem;
    color: rgba(255,255,255,0.85);
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 4px;
}

.otc-header-middle {
    display: flex;
    gap: 10px;
    align-items: center;
    flex-wrap: wrap;
}

.otc-stat-chip {
    display: flex;
    flex-direction: column;
    gap: 2px;
    padding: 8px 14px;
    border-radius: var(--radius-md);
    min-width: 110px;
    border: 1.5px solid rgba(255,255,255,0.3);
    background: rgba(255,255,255,0.15);
    backdrop-filter: blur(10px);
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}
.otc-stat-chip .stat-chip-label {
    font-size: 0.55rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: rgba(255,255,255,0.85);
    display: flex;
    align-items: center;
    gap: 4px;
}
.otc-stat-chip .stat-chip-value {
    font-size: 0.95rem;
    font-weight: 900;
    font-family: var(--font-mono);
    color: white;
}
.otc-stat-chip.discount {
    background: linear-gradient(135deg, rgba(217, 119, 6, 0.4), rgba(217, 119, 6, 0.2));
    border-color: rgba(251, 191, 36, 0.6);
}
.otc-stat-chip.discount .stat-chip-value { color: #FEF3C7; }
.otc-stat-chip.premium {
    background: linear-gradient(135deg, rgba(124, 58, 237, 0.4), rgba(124, 58, 237, 0.2));
    border-color: rgba(167, 139, 250, 0.6);
}
.otc-stat-chip.premium .stat-chip-value { color: #EDE9FE; }
.otc-stat-chip.grand-total {
    background: linear-gradient(135deg, rgba(5, 150, 105, 0.5), rgba(5, 150, 105, 0.3));
    border-color: rgba(52, 211, 153, 0.7);
    box-shadow: 0 4px 12px rgba(5, 150, 105, 0.3);
}
.otc-stat-chip.grand-total .stat-chip-value { color: #D1FAE5; font-size: 1.05rem; }

.otc-header-right {
    display: flex;
    gap: 8px;
    align-items: center;
    flex-wrap: wrap;
}

.otc-action-btn {
    padding: 8px 14px;
    border-radius: var(--radius-sm);
    font-weight: 800;
    font-size: 0.68rem;
    border: 1.5px solid rgba(255,255,255,0.3);
    cursor: pointer;
    transition: all 0.25s;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    text-decoration: none;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    white-space: nowrap;
    backdrop-filter: blur(10px);
    box-shadow: 0 2px 6px rgba(0,0,0,0.15);
}
.otc-action-btn:hover { transform: translateY(-2px); box-shadow: 0 6px 16px rgba(0,0,0,0.25); }
.otc-action-btn.view { background: rgba(255,255,255,0.2); color: white; }
.otc-action-btn.view:hover { background: rgba(255,255,255,0.35); }

.otc-scroll-buttons {
    display: flex;
    gap: 5px;
    margin-left: 8px;
}
.otc-scroll-btn {
    width: 32px;
    height: 32px;
    border-radius: var(--radius-sm);
    border: 1.5px solid rgba(255,255,255,0.4);
    background: rgba(255,255,255,0.2);
    color: white;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 0.72rem;
    font-weight: 700;
    transition: all 0.25s;
    backdrop-filter: blur(10px);
    flex-shrink: 0;
}
.otc-scroll-btn:hover { background: rgba(255,255,255,0.4); transform: translateY(-2px); border-color: rgba(255,255,255,0.6); }

.otc-items-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.78rem;
}
.otc-items-table thead th {
    text-align: left;
    padding: 10px 14px;
    font-weight: 800;
    font-size: 0.6rem;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: white;
    background: linear-gradient(135deg, #0891B2, #0E7490);
    white-space: nowrap;
}
.otc-items-table tbody td {
    padding: 10px 14px;
    border-bottom: 1px solid var(--border-color);
    color: var(--text-primary);
    vertical-align: middle;
    font-weight: 500;
}
.otc-items-table tbody tr:hover td { background: var(--cyan-bg); }
.otc-items-table tbody tr:last-child td { border-bottom: none; }

.otc-item-name-cell {
    display: flex;
    align-items: center;
    gap: 8px;
    font-weight: 700;
    color: var(--text-primary);
}
.otc-item-name-cell .item-icon {
    width: 26px;
    height: 26px;
    border-radius: 6px;
    background: linear-gradient(135deg, #0891B2, #22D3EE);
    color: white;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 0.7rem;
    flex-shrink: 0;
}

.otc-qty-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 36px;
    padding: 4px 10px;
    border-radius: 6px;
    background: var(--cyan-bg);
    color: var(--cyan);
    font-family: var(--font-mono);
    font-weight: 800;
    font-size: 0.78rem;
    border: 1.5px solid rgba(8, 145, 178, 0.3);
}

.otc-sale-footer {
    background: linear-gradient(135deg, rgba(8, 145, 178, 0.08), rgba(8, 145, 178, 0.03));
    padding: 10px 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
    border-top: 2px dashed var(--cyan);
}
.otc-footer-info {
    display: flex;
    gap: 14px;
    align-items: center;
    flex-wrap: wrap;
}
.otc-footer-stat {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    font-size: 0.65rem;
    font-weight: 700;
    color: var(--text-secondary);
    background: var(--bg-card);
    padding: 4px 10px;
    border-radius: 6px;
    border: 1px solid var(--border-color);
}
.otc-footer-stat strong {
    font-family: var(--font-mono);
    color: var(--text-primary);
    font-weight: 900;
}

.otc-cards-container {
    overflow-x: auto;
    scroll-behavior: smooth;
    padding: 8px 0;
    min-width: 100%;
}

/* Patient Cards */
.patient-card {
    background: var(--bg-card); border-radius: 14px;
    border: 2px solid var(--border-color); margin-bottom: 20px;
    overflow: hidden; box-shadow: var(--shadow-sm);
}
.patient-card:hover { border-color: var(--primary); box-shadow: var(--shadow-md); }
.patient-header {
    background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
    color: white; padding: 14px 22px;
    display: flex; justify-content: space-between; align-items: center;
    flex-wrap: wrap; gap: 12px; cursor: pointer;
}
.patient-header:hover { background: linear-gradient(135deg, #0A4CA8, #083C8A); }
.patient-header .patient-info {
    display: flex; align-items: center; gap: 14px;
    flex: 1; min-width: 250px;
}
.patient-header .patient-avatar {
    width: 48px; height: 48px; border-radius: 50%;
    background: rgba(255,255,255,0.25);
    display: flex; align-items: center; justify-content: center;
    font-weight: 700; font-size: 1.3rem; color: white;
    border: 2px solid rgba(255,255,255,0.4);
    font-family: var(--font-mono);
}
.patient-header .patient-name {
    font-weight: 700; font-size: 1.05rem;
    display: flex; align-items: center; gap: 8px; flex-wrap: wrap;
}
.patient-header .patient-meta {
    display: flex; gap: 14px; font-size: 0.75rem;
    opacity: 0.9; flex-wrap: wrap; margin-top: 3px;
}
.patient-header .patient-meta span { display: flex; align-items: center; gap: 4px; }
.patient-header .patient-stats {
    display: flex; gap: 10px; align-items: center; flex-wrap: wrap;
}
.patient-header .patient-stats .stat-pill {
    background: rgba(255,255,255,0.2);
    padding: 5px 14px; border-radius: 20px;
    font-size: 0.7rem; font-weight: 600;
    display: inline-flex; align-items: center; gap: 5px;
}
.patient-header .chevron {
    font-size: 0.9rem; transition: transform 0.3s ease;
}
.patient-header .chevron.rotated { transform: rotate(180deg); }
.patient-body {
    max-height: 0; overflow: hidden;
    transition: max-height 0.4s ease, padding 0.3s ease;
}
.patient-body.open { max-height: 20000px; padding: 16px 22px 20px; }

.visit-section {
    border: 2px solid var(--border-color);
    border-radius: 12px; margin-bottom: 16px; overflow: hidden;
}
.visit-section:last-child { margin-bottom: 0; }
.visit-section:hover { border-color: var(--primary); box-shadow: 0 4px 12px rgba(11, 94, 215, 0.1); }
.visit-section-header {
    background: linear-gradient(135deg, #E8F0FE, #D6E4FF);
    padding: 12px 18px;
    display: flex; justify-content: space-between; align-items: center;
    flex-wrap: wrap; gap: 12px;
    border-bottom: 2px solid var(--primary-light);
}
[data-theme="dark"] .visit-section-header { background: linear-gradient(135deg, #1E3A5F, #16294A); }
.visit-section-header .visit-info-left {
    display: flex; align-items: center; gap: 12px; flex-wrap: wrap;
}
.visit-section-header .visit-icon-badge {
    width: 40px; height: 40px; border-radius: 10px;
    background: linear-gradient(135deg, #FCD34D, #F59E0B);
    color: #78350F;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.1rem;
    box-shadow: 0 3px 10px rgba(245, 158, 11, 0.3);
}
.visit-section-header .visit-number-display {
    font-family: var(--font-mono); font-weight: 800;
    font-size: 0.95rem; color: #78350F;
    background: rgba(255,255,255,0.5);
    padding: 3px 12px; border-radius: 8px;
}
[data-theme="dark"] .visit-section-header .visit-number-display {
    background: rgba(255,255,255,0.1); color: #FCD34D;
}
.visit-section-header .visit-date-display {
    font-family: var(--font-mono); font-size: 0.75rem;
    color: var(--text-secondary); font-weight: 600;
}
.visit-section-header .visit-doctor-display {
    font-size: 0.78rem; color: var(--primary); font-weight: 700;
    background: rgba(255,255,255,0.6);
    padding: 3px 12px; border-radius: 20px;
    border: 1px solid var(--primary-light);
}
[data-theme="dark"] .visit-section-header .visit-doctor-display {
    background: rgba(255,255,255,0.1);
    color: #93C5FD;
    border-color: #3B82F6;
}
.visit-section-header .visit-stats-right {
    display: flex; gap: 8px; align-items: center; flex-wrap: wrap;
}
.visit-section-header .visit-mini-stat {
    background: rgba(255,255,255,0.7);
    padding: 4px 12px; border-radius: 20px;
    font-size: 0.7rem; font-weight: 700;
    display: inline-flex; align-items: center; gap: 5px;
    color: var(--text-primary);
}
[data-theme="dark"] .visit-section-header .visit-mini-stat {
    background: rgba(255,255,255,0.1);
}
.visit-section-header .visit-mini-stat .stat-value {
    font-family: var(--font-mono); font-weight: 800;
    color: var(--primary);
}
.visit-header-actions {
    display: inline-flex; gap: 6px; align-items: center;
    margin-left: 4px; padding-left: 10px;
    border-left: 2px solid rgba(11, 94, 215, 0.2);
}
.visit-action-btn {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 6px 14px; border-radius: 20px;
    font-size: 0.72rem; font-weight: 800;
    text-decoration: none;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    border: 2px solid rgba(255,255,255,0.25);
    white-space: nowrap; cursor: pointer;
    position: relative; overflow: hidden;
    font-family: var(--font-primary);
}
.visit-action-btn:hover { transform: translateY(-2px) scale(1.05); }
.visit-action-btn i { font-size: 0.75rem; }
.visit-action-btn.view {
    background: linear-gradient(135deg, #7C3AED, #6D28D9);
    color: white !important;
    box-shadow: 0 3px 10px rgba(124, 58, 237, 0.4);
}
.visit-action-btn.view:hover {
    box-shadow: 0 6px 20px rgba(124, 58, 237, 0.6);
    background: linear-gradient(135deg, #6D28D9, #5B21B6);
}
.visit-section-body {
    padding: 14px 18px 16px;
    background: var(--bg-card);
}

.empty-state {
    text-align: center; padding: 50px 20px;
    color: var(--text-secondary);
}
.empty-state i {
    font-size: 2.5rem; opacity: 0.3;
    display: block; margin-bottom: 12px;
    color: var(--primary);
}
.empty-state p { font-weight: 600; }

@media (max-width: 1400px) { .stats-grid.grid-7 { grid-template-columns: repeat(4, 1fr); } }
@media (max-width: 1200px) {
    .stats-grid { grid-template-columns: repeat(3, 1fr); }
    .stats-grid.grid-7 { grid-template-columns: repeat(3, 1fr); }
}
@media (max-width: 1024px) {
    .stats-grid { grid-template-columns: repeat(2, 1fr); }
    .stats-grid.grid-7 { grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 768px) {
    .page-header { padding: 16px 18px; }
    .page-header .page-title { font-size: 1.15rem; }
    .stats-grid { grid-template-columns: 1fr 1fr; gap: 10px; }
    .stats-grid.grid-7 { grid-template-columns: 1fr; }
    .stat-card { padding: 12px; min-height: 115px; }
    .data-table { font-size: 0.7rem; }
    .data-table thead th, .data-table tbody td { padding: 7px 8px; }
    .tabs-container { flex-direction: column; }
    .tab-btn { min-width: 100%; }
    .visit-action-btn { padding: 5px 10px; font-size: 0.65rem; }
    .visit-action-btn span { display: none; }
    .visit-action-btn i { font-size: 0.85rem; }
    .visit-header-actions { padding-left: 6px; }
    .otc-sale-header { flex-direction: column; align-items: stretch; }
    .otc-stat-chip { min-width: auto; flex: 1; }
    .otc-header-right { justify-content: space-between; }
}
@media (max-width: 480px) { .stats-grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>

<main class="main-content">

    <!-- PAGE HEADER -->
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-warehouse"></i>
                Inventory & Pharmacy Sales
                <span class="branch-tag"><i class="fas fa-user-shield"></i> AUDIT</span>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-user-circle"></i>
                Karibu, <span class="branch-tag user-tag"><i class="fas fa-user"></i> <?= htmlspecialchars($user_full_name) ?></span>
                <span class="branch-tag">
                    <i class="fas fa-store-alt"></i> 
                    <strong><?= htmlspecialchars($display_branch_name) ?></strong>
                </span>
                <span class="branch-tag">
                    <i class="fas fa-pills"></i> <?= number_format($med_total) ?> Medicines
                </span>
                <span class="branch-tag">
                    <i class="fas fa-tools"></i> <?= number_format($eq_total) ?> Equipment
                </span>
                <span class="branch-tag">
                    <i class="fas fa-shopping-cart"></i> <?= number_format($otc_total) ?> OTC Sales
                </span>
                <span class="branch-tag filter-tag">
                    <i class="fas fa-filter"></i> <?= htmlspecialchars($date_label) ?>
                </span>
            </p>
        </div>
        <div style="display:flex;gap:6px;flex-wrap:wrap;position:relative;z-index:1;">
            <button onclick="window.print()" class="btn-header">
                <i class="fas fa-print"></i> Print
            </button>
            <a href="/dispensary_system/frontend/pages/audit/dashboard.php" 
               class="btn-header">
                <i class="fas fa-arrow-left"></i> Dashboard
            </a>
        </div>
    </div>

    <!-- FILTER CARD -->
    <div class="filter-card">
        <div class="filter-section-title">
            <i class="fas fa-bolt"></i> Quick Filters
        </div>
        <div class="quick-filters">
            <a href="#" onclick="applyQuickFilter('today'); return false;" class="quick-btn <?= $quick_filter === 'today' ? 'active' : '' ?>">
                <i class="fas fa-calendar-day"></i> Today
            </a>
            <a href="#" onclick="applyQuickFilter('1w'); return false;" class="quick-btn <?= $quick_filter === '1w' ? 'active' : '' ?>">
                <i class="fas fa-calendar-week"></i> 1W
            </a>
            <a href="#" onclick="applyQuickFilter('1m'); return false;" class="quick-btn <?= $quick_filter === '1m' ? 'active' : '' ?>">
                <i class="fas fa-calendar-alt"></i> 1M
            </a>
            <a href="#" onclick="applyQuickFilter('3m'); return false;" class="quick-btn <?= $quick_filter === '3m' ? 'active' : '' ?>">
                <i class="fas fa-calendar-alt"></i> 3M
            </a>
            <a href="#" onclick="applyQuickFilter('6m'); return false;" class="quick-btn <?= $quick_filter === '6m' ? 'active' : '' ?>">
                <i class="fas fa-calendar-alt"></i> 6M
            </a>
            <a href="#" onclick="applyQuickFilter('1y'); return false;" class="quick-btn <?= $quick_filter === '1y' ? 'active' : '' ?>">
                <i class="fas fa-calendar"></i> 1Y
            </a>
            <a href="#" onclick="applyQuickFilter('all'); return false;" class="quick-btn <?= $quick_filter === 'all' ? 'active' : '' ?>">
                <i class="fas fa-infinity"></i> All
            </a>
        </div>

        <form method="GET" id="filterForm">
            <input type="hidden" name="tab" id="filterTabInput" value="<?= htmlspecialchars($active_tab) ?>">
            <input type="hidden" name="sub_tab" id="filterSubTabInput" value="<?= htmlspecialchars($active_sub_tab) ?>">
            
            <div class="filter-form">
                <div class="filter-group">
                    <label><i class="fas fa-filter"></i> Date Range</label>
                    <select name="quick" onchange="this.form.submit()">
                        <option value="today" <?= $quick_filter === 'today' ? 'selected' : '' ?>>Today</option>
                        <option value="1w" <?= $quick_filter === '1w' ? 'selected' : '' ?>>Last 1 Week</option>
                        <option value="1m" <?= $quick_filter === '1m' ? 'selected' : '' ?>>Last 1 Month</option>
                        <option value="3m" <?= $quick_filter === '3m' ? 'selected' : '' ?>>Last 3 Months</option>
                        <option value="6m" <?= $quick_filter === '6m' ? 'selected' : '' ?>>Last 6 Months</option>
                        <option value="1y" <?= $quick_filter === '1y' ? 'selected' : '' ?>>Last 1 Year</option>
                        <option value="all" <?= $quick_filter === 'all' ? 'selected' : '' ?>>All Time</option>
                        <option value="custom" <?= $quick_filter === 'custom' ? 'selected' : '' ?>>Custom Range</option>
                    </select>
                </div>
                
                <?php if ($quick_filter === 'custom'): ?>
                    <div class="filter-group">
                        <label><i class="fas fa-calendar"></i> From</label>
                        <input type="date" name="date_from" value="<?= htmlspecialchars($date_from) ?>">
                    </div>
                    <div class="filter-group">
                        <label><i class="fas fa-calendar"></i> To</label>
                        <input type="date" name="date_to" value="<?= htmlspecialchars($date_to) ?>">
                    </div>
                <?php endif; ?>
                
                <button type="submit" class="filter-btn-primary">
                    <i class="fas fa-filter"></i> Apply
                </button>
                
                <a href="?" class="filter-btn-secondary">
                    <i class="fas fa-redo"></i> Reset
                </a>
            </div>
        </form>
    </div>

    <!-- MAIN TABS -->
    <div class="tabs-container">
        <button class="tab-btn <?= $active_tab === 'medicines' ? 'active' : '' ?>" 
                onclick="switchTab('medicines')" id="tabBtnMed">
            <i class="fas fa-pills"></i> Medicines <span class="badge"><?= $med_total ?></span>
        </button>
        <button class="tab-btn <?= $active_tab === 'equipment' ? 'active' : '' ?>" 
                onclick="switchTab('equipment')" id="tabBtnEq">
            <i class="fas fa-tools"></i> Equipment <span class="badge"><?= $eq_total ?></span>
        </button>
        <button class="tab-btn <?= $active_tab === 'pharmacy' ? 'active' : '' ?>" 
                onclick="switchTab('pharmacy')" id="tabBtnPharm">
            <i class="fas fa-shopping-cart"></i> Pharmacy Sales <span class="badge"><?= count($grouped_prescriptions) + $otc_total ?></span>
        </button>
    </div>

    <!-- ================================================================ -->
    <!-- TAB 1: MEDICINES -->
    <!-- ================================================================ -->
    <div id="tab-medicines" class="tab-content <?= $active_tab === 'medicines' ? 'active' : '' ?>">
        <div class="stats-grid">
            <div class="stat-card blue">
                <div class="stat-icon"><i class="fas fa-pills"></i></div>
                <div class="stat-label"><i class="fas fa-boxes"></i> Total Medicines</div>
                <div class="stat-value"><span class="money-number"><?= number_format($med_total) ?></span></div>
                <div class="stat-sub"><i class="fas fa-cubes"></i> <?= number_format($med_total_qty) ?> units</div>
            </div>
            <div class="stat-card green">
                <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                <div class="stat-label"><i class="fas fa-warehouse"></i> In Stock</div>
                <div class="stat-value"><span class="money-number"><?= number_format($med_in_stock) ?></span></div>
                <div class="stat-sub"><i class="fas fa-coins"></i> <?= $currency ?> <?= formatMoneyShort($med_value) ?></div>
            </div>
            <div class="stat-card orange">
                <div class="stat-icon"><i class="fas fa-exclamation-triangle"></i></div>
                <div class="stat-label"><i class="fas fa-boxes"></i> Low Stock</div>
                <div class="stat-value"><span class="money-number"><?= number_format($med_low_stock) ?></span></div>
                <div class="stat-sub"><i class="fas fa-arrow-down"></i> Needs reorder</div>
            </div>
            <div class="stat-card red">
                <div class="stat-icon"><i class="fas fa-times-circle"></i></div>
                <div class="stat-label"><i class="fas fa-exclamation-circle"></i> Out of Stock</div>
                <div class="stat-value"><span class="money-number"><?= number_format($med_out_stock) ?></span></div>
                <div class="stat-sub"><i class="fas fa-ban"></i> Unavailable</div>
            </div>
        </div>
        
        <div class="table-card">
            <div class="table-header">
                <span class="title"><i class="fas fa-pills"></i> Medicines Inventory Report</span>
                <span class="count"><?= count($medicines) ?> items</span>
            </div>
            
            <div class="table-toolbar">
                <div class="table-toolbar-left">
                    <div class="search-box" id="medSearchBox">
                        <i class="fas fa-search search-icon"></i>
                        <input type="text" id="medSearch" 
                               placeholder="Search medicine, category, batch, added by..."
                               oninput="filterTable('medTable', this.value, 'medCount')">
                        <button type="button" class="search-clear" onclick="clearSearch('medTable', 'medSearch', 'medCount')">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <span class="search-count" id="medCount">
                        <i class="fas fa-list"></i>
                        <span class="count-text"><?= count($medicines) ?> records</span>
                    </span>
                </div>
                <div class="table-toolbar-right">
                    <button type="button" class="scroll-btn" onclick="scrollTable('medWrapper', 'left')">
                        <i class="fas fa-chevron-left"></i>
                    </button>
                    <button type="button" class="scroll-btn" onclick="scrollTable('medWrapper', 'right')">
                        <i class="fas fa-chevron-right"></i>
                    </button>
                </div>
            </div>
            
            <div class="table-scroll-wrapper" id="medWrapper">
                <table class="data-table" id="medTable" style="min-width:1250px;">
                    <thead>
                        <tr>
                            <th style="width:40px;">#</th>
                            <th>Medicine Name</th>
                            <th>Category</th>
                            <th>Branch</th>
                            <th style="text-align:center;">Qty</th>
                            <th style="text-align:center;">Reorder</th>
                            <th style="text-align:center;">Stock</th>
                            <th style="text-align:right;">Price</th>
                            <th>Expiry</th>
                            <th style="text-align:center;">Days</th>
                            <th>Batch</th>
                            <th style="text-align:center;">Status</th>
                            <th>Added By</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($medicines) > 0): ?>
                            <?php $row_num = 1; foreach ($medicines as $m): 
                                $qty = (int)($m['total_quantity'] ?? 0);
                                $reorder = (int)($m['reorder_level'] ?? 0);
                                $stock_class = 'ok'; $stock_label = 'In Stock';
                                if ($qty <= 0) { $stock_class = 'out'; $stock_label = 'Out'; }
                                elseif ($qty <= $reorder) { $stock_class = 'low'; $stock_label = 'Low'; }
                                
                                $exp = $m['expiry_date'] ?? '';
                                $exp_class = 'no-expiry'; $exp_display = 'No Expiry';
                                $days_display = '∞';
                                
                                if ($exp && $exp !== '0000-00-00') {
                                    $days = (int)($m['days_remaining'] ?? 0);
                                    $exp_display = date('d/m/Y', strtotime($exp));
                                    if ($days < 0) { $exp_class = 'expired'; $days_display = 'EXP'; }
                                    elseif ($days <= 30) { $exp_class = 'expiring'; $days_display = $days . 'd'; }
                                    else { $exp_class = 'valid'; $days_display = $days . 'd'; }
                                }
                                
                                $batches = $m['batch_numbers'] ?? '';
                                $first_batch = $batches ? explode('|', $batches)[0] : '-';
                                $batch_count = $batches ? count(explode('|', $batches)) : 0;
                                
                                $status = $m['computed_status'] ?? 'active';
                                $added_by = $m['added_by_full_name'] ?? 'System';
                                $med_name = $m['medication_name'] ?? 'N/A';
                            ?>
                                <tr class="searchable-row">
                                    <td style="text-align:center;font-weight:700;color:var(--text-secondary);"><?= $row_num++ ?></td>
                                    <td class="searchable-cell">
                                        <strong><?= htmlspecialchars($med_name) ?></strong>
                                        <?php if ($batch_count > 1): ?>
                                            <span style="font-size:0.55rem;background:var(--primary-bg);color:var(--primary);padding:1px 6px;border-radius:6px;margin-left:4px;font-family:var(--font-mono);font-weight:700;"><?= $batch_count ?> batches</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="searchable-cell"><?= htmlspecialchars($m['category'] ?? 'N/A') ?></td>
                                    <td class="searchable-cell">
                                        <span style="font-size:0.7rem;color:var(--primary);font-weight:600;">
                                            <i class="fas fa-store-alt"></i> <?= htmlspecialchars($m['branch_name'] ?? 'N/A') ?>
                                        </span>
                                    </td>
                                    <td style="text-align:center;font-weight:800;color:var(--primary);" class="searchable-cell"><?= number_format($qty) ?></td>
                                    <td style="text-align:center;color:var(--text-secondary);"><?= number_format($reorder) ?></td>
                                    <td style="text-align:center;">
                                        <span class="stock-badge <?= $stock_class ?>">
                                            <i class="fas <?= $stock_class === 'ok' ? 'fa-check-circle' : ($stock_class === 'low' ? 'fa-exclamation-triangle' : 'fa-times-circle') ?>"></i>
                                            <?= $stock_label ?>
                                        </span>
                                    </td>
                                    <td class="money-cell">
                                        <span class="currency-prefix"><?= $currency ?></span><?= number_format((float)($m['selling_price'] ?? 0), 0) ?>
                                    </td>
                                    <td style="text-align:center;"><span class="expiry-badge <?= $exp_class ?>"><?= $exp_display ?></span></td>
                                    <td style="text-align:center;"><span class="expiry-badge <?= $exp_class ?>"><?= $days_display ?></span></td>
                                    <td class="searchable-cell">
                                        <?php if ($first_batch !== '-'): ?>
                                            <span class="batch-number"><?= htmlspecialchars($first_batch) ?></span>
                                        <?php else: ?>
                                            <span style="color:var(--text-secondary);font-size:0.65rem;">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align:center;"><span class="status-badge <?= $status ?>"><?= strtoupper($status) ?></span></td>
                                    <td class="searchable-cell">
                                        <span class="added-by-tag">
                                            <i class="fas fa-user-circle"></i> <?= htmlspecialchars($added_by) ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="13" class="empty-state"><i class="fas fa-pills"></i><p>No medicines found</p></td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- TAB 2: EQUIPMENT -->
    <!-- ================================================================ -->
    <div id="tab-equipment" class="tab-content <?= $active_tab === 'equipment' ? 'active' : '' ?>">
        <div class="stats-grid">
            <div class="stat-card purple">
                <div class="stat-icon"><i class="fas fa-tools"></i></div>
                <div class="stat-label"><i class="fas fa-cog"></i> Total Equipment</div>
                <div class="stat-value"><span class="money-number"><?= number_format($eq_total) ?></span></div>
                <div class="stat-sub"><i class="fas fa-cubes"></i> <?= number_format($eq_total_qty) ?> units</div>
            </div>
            <div class="stat-card green">
                <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                <div class="stat-label"><i class="fas fa-warehouse"></i> In Stock</div>
                <div class="stat-value"><span class="money-number"><?= number_format($eq_in_stock) ?></span></div>
                <div class="stat-sub"><i class="fas fa-coins"></i> <?= $currency ?> <?= formatMoneyShort($eq_value) ?></div>
            </div>
            <div class="stat-card orange">
                <div class="stat-icon"><i class="fas fa-exclamation-triangle"></i></div>
                <div class="stat-label"><i class="fas fa-tools"></i> Low Stock</div>
                <div class="stat-value"><span class="money-number"><?= number_format($eq_low_stock) ?></span></div>
                <div class="stat-sub"><i class="fas fa-arrow-down"></i> Needs reorder</div>
            </div>
            <div class="stat-card red">
                <div class="stat-icon"><i class="fas fa-times-circle"></i></div>
                <div class="stat-label"><i class="fas fa-exclamation-circle"></i> Out of Stock</div>
                <div class="stat-value"><span class="money-number"><?= number_format($eq_out_stock) ?></span></div>
                <div class="stat-sub"><i class="fas fa-ban"></i> Unavailable</div>
            </div>
        </div>
        
        <div class="table-card">
            <div class="table-header">
                <span class="title"><i class="fas fa-tools"></i> Equipment Inventory Report</span>
                <span class="count"><?= count($equipment) ?> items</span>
            </div>
            
            <div class="table-toolbar">
                <div class="table-toolbar-left">
                    <div class="search-box" id="eqSearchBox">
                        <i class="fas fa-search search-icon"></i>
                        <input type="text" id="eqSearch" 
                               placeholder="Search equipment, category, batch..."
                               oninput="filterTable('eqTable', this.value, 'eqCount')">
                        <button type="button" class="search-clear" onclick="clearSearch('eqTable', 'eqSearch', 'eqCount')">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <span class="search-count" id="eqCount">
                        <i class="fas fa-list"></i>
                        <span class="count-text"><?= count($equipment) ?> records</span>
                    </span>
                </div>
                <div class="table-toolbar-right">
                    <button type="button" class="scroll-btn" onclick="scrollTable('eqWrapper', 'left')">
                        <i class="fas fa-chevron-left"></i>
                    </button>
                    <button type="button" class="scroll-btn" onclick="scrollTable('eqWrapper', 'right')">
                        <i class="fas fa-chevron-right"></i>
                    </button>
                </div>
            </div>
            
            <div class="table-scroll-wrapper" id="eqWrapper">
                <table class="data-table" id="eqTable" style="min-width:1250px;">
                    <thead>
                        <tr>
                            <th style="width:40px;">#</th>
                            <th>Equipment Name</th>
                            <th>Category</th>
                            <th>Branch</th>
                            <th style="text-align:center;">Qty</th>
                            <th style="text-align:center;">Reorder</th>
                            <th style="text-align:center;">Stock</th>
                            <th style="text-align:right;">Price</th>
                            <th>Expiry</th>
                            <th style="text-align:center;">Days</th>
                            <th>Batch</th>
                            <th style="text-align:center;">Status</th>
                            <th>Added By</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($equipment) > 0): ?>
                            <?php $row_num = 1; foreach ($equipment as $e): 
                                $qty = (int)($e['total_quantity'] ?? 0);
                                $reorder = (int)($e['reorder_level'] ?? 0);
                                $stock_class = 'ok'; $stock_label = 'In Stock';
                                if ($qty <= 0) { $stock_class = 'out'; $stock_label = 'Out'; }
                                elseif ($qty <= $reorder) { $stock_class = 'low'; $stock_label = 'Low'; }
                                
                                $exp = $e['expiry_date'] ?? '';
                                $exp_class = 'no-expiry'; $exp_display = 'No Expiry';
                                $days_display = '∞';
                                
                                if ($exp && $exp !== '0000-00-00') {
                                    $days = (int)($e['days_remaining'] ?? 0);
                                    $exp_display = date('d/m/Y', strtotime($exp));
                                    if ($days < 0) { $exp_class = 'expired'; $days_display = 'EXP'; }
                                    elseif ($days <= 30) { $exp_class = 'expiring'; $days_display = $days . 'd'; }
                                    else { $exp_class = 'valid'; $days_display = $days . 'd'; }
                                }
                                
                                $batches = $e['batch_numbers'] ?? '';
                                $first_batch = $batches ? explode('|', $batches)[0] : '-';
                                $batch_count = $batches ? count(explode('|', $batches)) : 0;
                                
                                $status = $e['computed_status'] ?? 'active';
                                $added_by = $e['added_by_full_name'] ?? 'System';
                                $eq_name = $e['equipment_name'] ?? 'N/A';
                            ?>
                                <tr class="searchable-row">
                                    <td style="text-align:center;font-weight:700;color:var(--text-secondary);"><?= $row_num++ ?></td>
                                    <td class="searchable-cell">
                                        <strong><?= htmlspecialchars($eq_name) ?></strong>
                                        <?php if ($batch_count > 1): ?>
                                            <span style="font-size:0.55rem;background:var(--purple-bg);color:var(--purple);padding:1px 6px;border-radius:6px;margin-left:4px;font-family:var(--font-mono);font-weight:700;"><?= $batch_count ?> batches</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="searchable-cell"><?= htmlspecialchars($e['category'] ?? 'N/A') ?></td>
                                    <td class="searchable-cell">
                                        <span style="font-size:0.7rem;color:var(--primary);font-weight:600;">
                                            <i class="fas fa-store-alt"></i> <?= htmlspecialchars($e['branch_name'] ?? 'N/A') ?>
                                        </span>
                                    </td>
                                    <td style="text-align:center;font-weight:800;color:var(--purple);" class="searchable-cell"><?= number_format($qty) ?></td>
                                    <td style="text-align:center;color:var(--text-secondary);"><?= number_format($reorder) ?></td>
                                    <td style="text-align:center;">
                                        <span class="stock-badge <?= $stock_class ?>">
                                            <i class="fas <?= $stock_class === 'ok' ? 'fa-check-circle' : ($stock_class === 'low' ? 'fa-exclamation-triangle' : 'fa-times-circle') ?>"></i>
                                            <?= $stock_label ?>
                                        </span>
                                    </td>
                                    <td class="money-cell">
                                        <span class="currency-prefix"><?= $currency ?></span><?= number_format((float)($e['selling_price'] ?? 0), 0) ?>
                                    </td>
                                    <td style="text-align:center;"><span class="expiry-badge <?= $exp_class ?>"><?= $exp_display ?></span></td>
                                    <td style="text-align:center;"><span class="expiry-badge <?= $exp_class ?>"><?= $days_display ?></span></td>
                                    <td class="searchable-cell">
                                        <?php if ($first_batch !== '-'): ?>
                                            <span class="batch-number"><?= htmlspecialchars($first_batch) ?></span>
                                        <?php else: ?>
                                            <span style="color:var(--text-secondary);font-size:0.65rem;">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align:center;"><span class="status-badge <?= $status ?>"><?= strtoupper($status) ?></span></td>
                                    <td class="searchable-cell">
                                        <span class="added-by-tag">
                                            <i class="fas fa-user-circle"></i> <?= htmlspecialchars($added_by) ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="13" class="empty-state"><i class="fas fa-tools"></i><p>No equipment found</p></td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- TAB 3: PHARMACY SALES -->
    <!-- ================================================================ -->
    <div id="tab-pharmacy" class="tab-content <?= $active_tab === 'pharmacy' ? 'active' : '' ?>">
        
        <div class="section-label">
            <i class="fas fa-chart-pie" style="color:var(--primary);"></i> Sales Summary
        </div>
        <div class="stats-grid grid-7">
            
            <div class="stat-card cyan">
                <div class="stat-icon"><i class="fas fa-shopping-cart"></i></div>
                <div class="stat-label">OTC Sales</div>
                <div class="stat-value">
                    <span class="currency-symbol"><?= $currency ?></span>
                    <span class="money-number"><?= number_format($otc_paid_amount, 0) ?></span>
                </div>
                <div class="stat-sub"><i class="fas fa-check"></i> Paid only</div>
            </div>
            
            <div class="stat-card purple">
                <div class="stat-icon"><i class="fas fa-prescription"></i></div>
                <div class="stat-label">Prescription (GROSS)</div>
                <div class="stat-value">
                    <span class="currency-symbol"><?= $currency ?></span>
                    <span class="money-number"><?= number_format($presc_total_amount, 0) ?></span>
                </div>
                <div class="stat-sub"><i class="fas fa-cube"></i> Gross Only (bila disc/prem)</div>
            </div>
            
            <div class="stat-card orange">
                <div class="stat-icon"><i class="fas fa-hourglass-half"></i></div>
                <div class="stat-label">Prescriptions Pending</div>
                <div class="stat-value">
                    <span class="currency-symbol"><?= $currency ?></span>
                    <span class="money-number"><?= number_format($presc_pending_amount, 0) ?></span>
                </div>
                <div class="stat-sub"><i class="fas fa-clock"></i> Awaiting payment</div>
            </div>
            
            <div class="stat-card orange">
                <div class="stat-icon"><i class="fas fa-clock"></i></div>
                <div class="stat-label">OTC Pending</div>
                <div class="stat-value">
                    <span class="currency-symbol"><?= $currency ?></span>
                    <span class="money-number"><?= number_format($otc_pending_amount, 0) ?></span>
                </div>
                <div class="stat-sub"><i class="fas fa-clock"></i> Awaiting payment</div>
            </div>
            
            <div class="stat-card red">
                <div class="stat-icon"><i class="fas fa-percent"></i></div>
                <div class="stat-label">OTC Discount</div>
                <div class="stat-value">
                    <span class="currency-symbol"><?= $currency ?></span>
                    <span class="money-number"><?= number_format($otc_discount, 0) ?></span>
                </div>
                <div class="stat-sub"><i class="fas fa-arrow-down"></i> OTC only</div>
            </div>
            
            <div class="stat-card purple">
                <div class="stat-icon"><i class="fas fa-star"></i></div>
                <div class="stat-label">OTC Premium</div>
                <div class="stat-value">
                    <span class="currency-symbol"><?= $currency ?></span>
                    <span class="money-number"><?= number_format($otc_premium, 0) ?></span>
                </div>
                <div class="stat-sub"><i class="fas fa-arrow-up"></i> OTC only</div>
            </div>
            
            <div class="stat-card paid-total">
                <div class="stat-icon"><i class="fas fa-check-double"></i></div>
                <div class="stat-label">Total Paid</div>
                <div class="stat-value">
                    <span class="currency-symbol"><?= $currency ?></span>
                    <span class="money-number"><?= number_format($total_paid, 0) ?></span>
                </div>
                <div class="stat-sub"><i class="fas fa-check"></i> Rx + OTC Paid</div>
            </div>
            
        </div>

        <div class="sub-tabs">
            <button class="sub-tab-btn <?= $active_sub_tab === 'prescriptions' ? 'active' : '' ?>" 
                    onclick="switchSubTab('prescriptions')" id="subTabBtnPresc">
                <i class="fas fa-prescription"></i> Prescriptions (<?= count($grouped_prescriptions) ?> patients)
            </button>
            <button class="sub-tab-btn <?= $active_sub_tab === 'otc' ? 'active' : '' ?>" 
                    onclick="switchSubTab('otc')" id="subTabBtnOtc">
                <i class="fas fa-shopping-cart"></i> OTC Sales (<?= $otc_total ?>)
            </button>
        </div>

        <!-- SUB-TAB: PRESCRIPTIONS -->
        <div id="subtab-prescriptions" class="sub-tab-content <?= $active_sub_tab === 'prescriptions' ? 'active' : '' ?>">
            <div class="table-toolbar" style="border-radius:14px 14px 0 0;background:linear-gradient(135deg,#0B5ED7,#0A4CA8);">
                <div class="table-toolbar-left">
                    <div class="search-box" style="max-width:450px;">
                        <i class="fas fa-search search-icon" style="color:rgba(255,255,255,0.8);"></i>
                        <input type="text" id="prescSearch" 
                               placeholder="Search medicine, patient, visit #, doctor..."
                               style="background:rgba(255,255,255,0.15);border-color:rgba(255,255,255,0.25);color:white;"
                               autocomplete="off">
                        <button type="button" class="search-clear" onclick="clearPrescSearch()">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <span class="search-count" id="prescCount">
                        <i class="fas fa-users"></i>
                        <span class="count-text"><?= count($grouped_prescriptions) ?> patients</span>
                    </span>
                </div>
            </div>

            <div id="patientsContainer">
                <?php if (count($grouped_prescriptions) > 0): ?>
                    <?php foreach ($grouped_prescriptions as $patient): 
                        $patient_id = $patient['patient_id'] ?? 0;
                        $total_qty = (int)($patient['total_qty'] ?? 0);
                        $medication_count = (int)($patient['medication_count'] ?? 0);
                        $total_amount = (float)($patient['total_amount'] ?? 0);
                        $patient_premium = (float)($patient['patient_premium'] ?? 0);
                        $patient_discount = (float)($patient['patient_discount'] ?? 0);
                        $doctor_names = (isset($patient['doctor_names_array']) && is_array($patient['doctor_names_array'])) 
                            ? implode(', ', array_slice($patient['doctor_names_array'], 0, 2)) : '';
                    ?>
                        <div class="patient-card" data-patient-id="<?= $patient_id ?>">
                            <div class="patient-header" onclick="togglePatient(<?= $patient_id ?>)">
                                <div class="patient-info">
                                    <div class="patient-avatar" style="background: <?= '#' . substr(md5($patient['patient_name'] ?? 'X'), 0, 6) ?>;">
                                        <?= strtoupper(substr($patient['patient_name'] ?? 'P', 0, 1)) ?>
                                    </div>
                                    <div>
                                        <div class="patient-name">
                                            <?= htmlspecialchars($patient['patient_name'] ?? 'N/A') ?>
                                            <span style="background:rgba(255,255,255,0.25);padding:2px 10px;border-radius:12px;font-size:0.6rem;font-weight:600;">
                                                <i class="fas fa-pills"></i> <?= $medication_count ?> Item(s)
                                            </span>
                                        </div>
                                        <div class="patient-meta">
                                            <span><i class="fas fa-id-card"></i> <?= htmlspecialchars($patient['patient_number'] ?? 'N/A') ?></span>
                                            <?php if (!empty($patient['patient_phone']) && $patient['patient_phone'] !== 'N/A'): ?>
                                                <span><i class="fas fa-phone"></i> <?= htmlspecialchars($patient['patient_phone']) ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="patient-stats">
                                    <?php if (!empty($doctor_names)): ?>
                                        <span class="stat-pill"><i class="fas fa-user-md"></i> Dr. <?= htmlspecialchars($doctor_names) ?></span>
                                    <?php endif; ?>
                                    <span class="stat-pill" style="background:rgba(255,255,255,0.3);">
                                        <i class="fas fa-pills"></i> Qty: <span class="mono"><?= $total_qty ?></span>
                                    </span>
                                    <span class="stat-pill" style="background:rgba(255,255,255,0.3);">
                                        <i class="fas fa-money-bill-wave"></i> <span class="mono"><?= $currency ?> <?= number_format($total_amount, 0) ?></span>
                                    </span>
                                    
                                    <?php if ($patient_premium > 0): ?>
                                        <span class="premium-badge" style="background:rgba(255,255,255,0.25);color:#FCD34D;border-color:rgba(252,211,77,0.4);">
                                            <i class="fas fa-star"></i> Prem: <?= number_format($patient_premium, 0) ?>
                                        </span>
                                    <?php endif; ?>
                                    <?php if ($patient_discount > 0): ?>
                                        <span class="discount-badge" style="background:rgba(255,255,255,0.25);color:#FEF08A;border-color:rgba(254,240,138,0.4);">
                                            <i class="fas fa-tag"></i> Disc: <?= number_format($patient_discount, 0) ?>
                                        </span>
                                    <?php endif; ?>
                                    
                                    <i class="fas fa-chevron-down chevron" id="chevron-<?= $patient_id ?>"></i>
                                </div>
                            </div>
                            
                            <div class="patient-body" id="body-<?= $patient_id ?>">
                                <?php 
                                $patient_visits = (isset($patient['visits']) && is_array($patient['visits'])) ? $patient['visits'] : [];
                                if (!empty($patient_visits)): 
                                    foreach ($patient_visits as $visit): 
                                        if (!is_array($visit)) continue;
                                        
                                        $visit_id = isset($visit['visit_id']) ? (int)$visit['visit_id'] : 0;
                                        $visit_number = isset($visit['visit_number']) ? $visit['visit_number'] : 'N/A';
                                        $visit_date = isset($visit['visit_date']) ? $visit['visit_date'] : null;
                                        $visit_doctor = (isset($visit['doctor_names']) && is_array($visit['doctor_names']) && !empty($visit['doctor_names'])) 
                                            ? implode(', ', $visit['doctor_names']) : 'N/A';
                                        $visit_status = isset($visit['overall_status']) ? $visit['overall_status'] : 'pending';
                                        $visit_qty = isset($visit['total_qty']) ? (int)$visit['total_qty'] : 0;
                                        $visit_meds = isset($visit['medication_count']) ? (int)$visit['medication_count'] : 0;
                                        $visit_amount = isset($visit['total_amount']) ? (float)$visit['total_amount'] : 0;
                                        $visit_premium = isset($visit['premium_amount']) ? (float)$visit['premium_amount'] : 0;
                                        $visit_discount = isset($visit['discount_amount']) ? (float)$visit['discount_amount'] : 0;
                                        $visit_items = (isset($visit['items']) && is_array($visit['items'])) ? $visit['items'] : [];
                                        
                                        $visit_received_by = null;
                                        if ($visit_status === 'paid' && isset($visit['received_by']) && !empty($visit['received_by'])) {
                                            $visit_received_by = $visit['received_by'];
                                        }
                                        
                                        $visit_primary_prescription_id = 0;
                                        foreach ($visit_items as $vitem) {
                                            if (!empty($vitem['prescription_id']) && (int)$vitem['prescription_id'] > 0) {
                                                $visit_primary_prescription_id = (int)$vitem['prescription_id'];
                                                break;
                                            }
                                        }
                                ?>
                                    <div class="visit-section" data-visit-id="<?= $visit_id ?>" data-patient-id="<?= $patient_id ?>">
                                        <div class="visit-section-header">
                                            <div class="visit-info-left">
                                                <div class="visit-icon-badge"><i class="fas fa-calendar-check"></i></div>
                                                <span class="visit-number-display"><?= htmlspecialchars($visit_number) ?></span>
                                                <span class="visit-date-display">
                                                    <i class="fas fa-calendar-day"></i>
                                                    <?= !empty($visit_date) ? date('d M Y', strtotime($visit_date)) : 'N/A' ?>
                                                </span>
                                                <span class="visit-doctor-display">
                                                    <i class="fas fa-user-md"></i> Dr. <?= htmlspecialchars($visit_doctor) ?>
                                                </span>
                                                <span class="status-badge <?= htmlspecialchars($visit_status) ?>" style="font-size:0.6rem;padding:3px 10px;">
                                                    <?= strtoupper(htmlspecialchars($visit_status)) ?>
                                                </span>
                                            </div>
                                            <div class="visit-stats-right">
                                                <span class="visit-mini-stat"><i class="fas fa-pills"></i> Meds: <span class="stat-value"><?= $visit_meds ?></span></span>
                                                <span class="visit-mini-stat"><i class="fas fa-sort-numeric-up"></i> Qty: <span class="stat-value"><?= $visit_qty ?></span></span>
                                                <span class="visit-mini-stat"><i class="fas fa-money-bill-wave"></i> <span class="stat-value"><?= $currency ?> <?= number_format($visit_amount, 0) ?></span></span>
                                                
                                                <?php if ($visit_premium > 0): ?>
                                                    <span class="visit-mini-stat" style="background:rgba(124,58,237,0.15);color:#7C3AED;border:1px solid rgba(124,58,237,0.3);">
                                                        <i class="fas fa-star"></i> Prem: <span class="stat-value" style="color:#7C3AED;"><?= number_format($visit_premium, 0) ?></span>
                                                    </span>
                                                <?php endif; ?>
                                                <?php if ($visit_discount > 0): ?>
                                                    <span class="visit-mini-stat" style="background:rgba(217,119,6,0.15);color:#D97706;border:1px solid rgba(217,119,6,0.3);">
                                                        <i class="fas fa-tag"></i> Disc: <span class="stat-value" style="color:#D97706;"><?= number_format($visit_discount, 0) ?></span>
                                                    </span>
                                                <?php endif; ?>
                                                
                                                <?php if ($visit_primary_prescription_id > 0): ?>
                                                    <div class="visit-header-actions">
                                                        <a href="/dispensary_system/frontend/pages/audit/view_prescription.php?id=<?= $visit_primary_prescription_id ?>&visit_id=<?= $visit_id ?>&patient_id=<?= $patient_id ?>" 
                                                           class="visit-action-btn view" 
                                                           title="View Prescription Details"
                                                           target="_blank"
                                                           onclick="event.stopPropagation();">
                                                            <i class="fas fa-eye"></i>
                                                            <span>View</span>
                                                        </a>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        
                                        <div class="visit-section-body">
                                            <div class="table-scroll-wrapper">
                                                <table class="data-table">
                                                    <thead>
                                                        <tr>
                                                            <th style="width:50px;">#</th>
                                                            <th><i class="fas fa-pills"></i> Medication</th>
                                                            <th style="text-align:center;"><i class="fas fa-sort-numeric-up"></i> Qty</th>
                                                            <th><i class="fas fa-prescription"></i> Rx #</th>
                                                            <th style="text-align:right;"><i class="fas fa-money-bill"></i> Price</th>
                                                            <th style="text-align:center;"><i class="fas fa-flag"></i> Status</th>
                                                            <th><i class="fas fa-user-check"></i> Received By</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php if (!empty($visit_items)): 
                                                            $i = 1; 
                                                            foreach ($visit_items as $item): 
                                                                if (!is_array($item)) continue;
                                                                $med_name = $item['medication_name'] ?? 'N/A';
                                                                $med_qty = (int)($item['quantity'] ?? 0);
                                                                $med_price = (float)($item['final_price'] ?? 0);
                                                                $pres_num = $item['prescription_number'] ?? 'N/A';
                                                                $item_status = strtolower($item['status'] ?? $visit_status);
                                                                $item_bill_status = strtolower($item['bill_status'] ?? '');
                                                                
                                                                $item_is_paid = ($item_status === 'paid' || $item_status === 'dispensed' || $item_bill_status === 'paid');
                                                                $item_received_by = $item_is_paid ? $visit_received_by : null;
                                                        ?>
                                                            <tr class="med-row"
                                                                data-search="<?= htmlspecialchars(strtolower($med_name . ' ' . $patient['patient_name'] . ' ' . $patient['patient_number'] . ' ' . $pres_num . ' ' . $visit_number . ' ' . $visit_doctor)) ?>"
                                                                data-med-name="<?= htmlspecialchars(strtolower($med_name)) ?>"
                                                                data-qty="<?= $med_qty ?>">
                                                                <td style="text-align:center;font-weight:700;color:var(--text-secondary);font-family:var(--font-mono);font-size:0.72rem;"><?= $i++ ?></td>
                                                                <td style="font-weight:600;font-size:0.82rem;" data-searchable><?= htmlspecialchars($med_name) ?></td>
                                                                <td style="text-align:center;" data-searchable>
                                                                    <span class="rx-number"><i class="fas fa-pills"></i> <?= number_format($med_qty) ?></span>
                                                                </td>
                                                                <td data-searchable><span class="rx-number"><?= htmlspecialchars($pres_num) ?></span></td>
                                                                <td class="money-cell" data-searchable>
                                                                    <span class="currency-prefix"><?= $currency ?></span><?= number_format($med_price, 0) ?>
                                                                </td>
                                                                <td style="text-align:center;">
                                                                    <span class="status-badge <?= htmlspecialchars($item_status) ?>">
                                                                        <?= strtoupper(htmlspecialchars($item_status)) ?>
                                                                    </span>
                                                                </td>
                                                                <td data-searchable>
                                                                    <?php if ($item_received_by): ?>
                                                                        <span class="received-by-badge">
                                                                            <i class="fas fa-user-check"></i>
                                                                            <?= htmlspecialchars($item_received_by) ?>
                                                                        </span>
                                                                    <?php else: ?>
                                                                        <span class="received-by-empty">
                                                                            <i class="fas fa-clock" style="font-size:0.6rem;"></i>
                                                                            Not paid yet
                                                                        </span>
                                                                    <?php endif; ?>
                                                                </td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                        <?php endif; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    
                    <div id="noPrescResults" style="display:none;">
                        <div class="empty-state">
                            <i class="fas fa-search"></i>
                            <p>No patients match your search</p>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-prescription"></i>
                        <p>No prescriptions found for this period</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- SUB-TAB: OTC SALES -->
        <div id="subtab-otc" class="sub-tab-content <?= $active_sub_tab === 'otc' ? 'active' : '' ?>">
            <div class="table-card" style="background:var(--bg-body);">
                <div class="table-header" style="background:linear-gradient(135deg, #0891B2, #0E7490);">
                    <div style="display:flex; align-items:center; gap:12px; flex-wrap:wrap;">
                        <span class="title"><i class="fas fa-cash-register" style="color:#67E8F9;"></i> OTC Sales (Over-The-Counter)</span>
                        <span class="count"><i class="fas fa-list"></i> <?= count($otc_sales) ?> sales • Total: <?= $currency ?> <?= number_format($otc_paid_amount, 0) ?></span>
                    </div>
                    <div style="display:flex; align-items:center; gap:8px;">
                        <div class="otc-scroll-buttons">
                            <button type="button" class="otc-scroll-btn" onclick="scrollOtcContainer('left')" title="Scroll Left"><i class="fas fa-chevron-left"></i></button>
                            <button type="button" class="otc-scroll-btn" onclick="scrollOtcContainer('right')" title="Scroll Right"><i class="fas fa-chevron-right"></i></button>
                        </div>
                    </div>
                </div>
                
                <?php if (count($otc_sales) > 0): ?>
                    <div id="otcCardsContainer" class="otc-cards-container">
                        <?php foreach ($otc_sales as $sale): 
                            $status = strtolower($sale['payment_status'] ?? 'pending');
                            $status_class = ($status === 'paid') ? 'paid' : (($status === 'cancelled') ? 'cancelled' : 'pending');
                            
                            $sale_id = (int)($sale['sale_id'] ?? 0);
                            $customer = !empty($sale['customer_name']) ? $sale['customer_name'] : 'Walk-in Customer';
                            $sale_number = $sale['sale_number'] ?? 'N/A';
                            $items = $sale['items'] ?? [];
                            $item_count = count($items);
                            
                            $sold_by_name = $sale['sold_by_name'] ?? 'N/A';
                            $sold_by_role = strtolower($sale['sold_by_role'] ?? 'user');
                            $name_parts = explode(' ', trim($sold_by_name));
                            $initials = count($name_parts) >= 2 
                                ? strtoupper(substr($name_parts[0], 0, 1) . substr($name_parts[1], 0, 1)) 
                                : strtoupper(substr($sold_by_name, 0, 2));
                        ?>
                            <div class="otc-sale-card">
                                
                                <div class="otc-sale-header">
                                    <div class="otc-header-left">
                                        <span class="otc-sale-id-badge">
                                            <i class="fas fa-receipt"></i> <?= htmlspecialchars($sale_number) ?>
                                        </span>
                                        <div class="otc-customer-info">
                                            <div class="otc-customer-name">
                                                <i class="fas fa-user-circle"></i>
                                                <?= htmlspecialchars($customer) ?>
                                            </div>
                                            <?php if (!empty($sale['customer_phone'])): ?>
                                                <div class="otc-customer-phone">
                                                    <i class="fas fa-phone"></i> <?= htmlspecialchars($sale['customer_phone']) ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <div class="otc-header-middle">
                                        <div class="otc-stat-chip discount">
                                            <span class="stat-chip-label"><i class="fas fa-tag"></i> Discount</span>
                                            <span class="stat-chip-value">
                                                <?php if ((float)($sale['discount_amount'] ?? 0) > 0): ?>
                                                    -<?= $currency ?> <?= number_format((float)$sale['discount_amount'], 0) ?>
                                                <?php else: ?>
                                                    <?= $currency ?> 0
                                                <?php endif; ?>
                                            </span>
                                        </div>
                                        
                                        <div class="otc-stat-chip premium">
                                            <span class="stat-chip-label"><i class="fas fa-star"></i> Premium</span>
                                            <span class="stat-chip-value">
                                                <?php if ((float)($sale['premium_amount'] ?? 0) > 0): ?>
                                                    +<?= $currency ?> <?= number_format((float)$sale['premium_amount'], 0) ?>
                                                <?php else: ?>
                                                    <?= $currency ?> 0
                                                <?php endif; ?>
                                            </span>
                                        </div>
                                        
                                        <div class="otc-stat-chip grand-total">
                                            <span class="stat-chip-label"><i class="fas fa-calculator"></i> Grand Total</span>
                                            <span class="stat-chip-value"><?= $currency ?> <?= number_format((float)($sale['total_amount'] ?? 0), 0) ?></span>
                                        </div>
                                    </div>
                                    
                                    <div class="otc-header-right">
                                        <a href="/dispensary_system/frontend/pages/audit/view_otc.php?id=<?= $sale_id ?>" 
                                           class="otc-action-btn view" title="View OTC Sale" target="_blank">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                        <div class="otc-scroll-buttons">
                                            <button type="button" class="otc-scroll-btn" onclick="scrollOtcCard(this, 'left')" title="Scroll Left"><i class="fas fa-chevron-left"></i></button>
                                            <button type="button" class="otc-scroll-btn" onclick="scrollOtcCard(this, 'right')" title="Scroll Right"><i class="fas fa-chevron-right"></i></button>
                                        </div>
                                    </div>
                                </div>
                                
                                <div style="overflow-x:auto; scroll-behavior:smooth;" class="otc-items-wrapper">
                                    <table class="otc-items-table" style="min-width:1100px;">
                                        <thead>
                                            <tr>
                                                <th style="width:40px;">#</th>
                                                <th>Item Name</th>
                                                <th style="text-align:center;">Qty</th>
                                                <th style="text-align:right;">Unit Price</th>
                                                <th style="text-align:right;">Total Price</th>
                                                <th>Sold By</th>
                                                <th>Payment Method</th>
                                                <th>Date & Time</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if ($item_count > 0): ?>
                                                <?php $item_num = 1; foreach ($items as $item): ?>
                                                    <tr>
                                                        <td style="text-align:center;font-weight:700;color:var(--text-secondary);"><?= $item_num++ ?></td>
                                                        <td>
                                                            <div class="otc-item-name-cell">
                                                                <span class="item-icon"><i class="fas fa-capsules"></i></span>
                                                                <?= htmlspecialchars($item['item_name'] ?? 'N/A') ?>
                                                            </div>
                                                        </td>
                                                        <td style="text-align:center;">
                                                            <span class="otc-qty-badge"><?= (int)($item['quantity'] ?? 0) ?></span>
                                                        </td>
                                                        <td class="money-cell" style="color:#0891B2;font-size:0.78rem;">
                                                            <span class="currency-prefix"><?= $currency ?></span><?= number_format((float)($item['unit_price'] ?? 0), 0) ?>
                                                        </td>
                                                        <td class="money-cell" style="font-size:0.78rem;font-weight:900;">
                                                            <span class="currency-prefix"><?= $currency ?></span><?= number_format((float)($item['total_price'] ?? 0), 0) ?>
                                                        </td>
                                                        <td>
                                                            <div style="display:flex; align-items:center; gap:8px;">
                                                                <div style="width:30px;height:30px;border-radius:50%;background:linear-gradient(135deg,#0891B2,#22D3EE);color:white;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:0.68rem;text-transform:uppercase;">
                                                                    <?= htmlspecialchars($initials) ?>
                                                                </div>
                                                                <div style="display:flex; flex-direction:column; gap:2px;">
                                                                    <span style="font-size:0.72rem; font-weight:700; color:var(--text-primary);">
                                                                        <?= htmlspecialchars($sold_by_name) ?>
                                                                    </span>
                                                                    <span style="font-size:0.55rem; font-weight:800; color:var(--text-secondary); text-transform:uppercase;">
                                                                        <?= htmlspecialchars(strtoupper($sold_by_role)) ?>
                                                                    </span>
                                                                </div>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <span class="payment-badge"><i class="fas fa-credit-card"></i> <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $sale['payment_method'] ?? 'Cash'))) ?></span>
                                                        </td>
                                                        <td>
                                                            <div style="font-size:0.68rem;font-weight:600;"><?= date('d M Y', strtotime($sale['created_at'] ?? 'now')) ?></div>
                                                            <div style="font-size:0.58rem;color:var(--text-secondary);"><?= date('H:i', strtotime($sale['created_at'] ?? 'now')) ?></div>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="8" style="text-align:center;color:var(--text-secondary);font-style:italic;padding:20px;">No items recorded for this sale</td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                                
                                <div class="otc-sale-footer">
                                    <div class="otc-footer-info">
                                        <span class="otc-footer-stat">
                                            <i class="fas fa-list-ul" style="color:#0891B2;"></i>
                                            Items: <strong><?= $item_count ?></strong>
                                        </span>
                                        <span class="otc-footer-stat">
                                            <i class="fas fa-store-alt" style="color:var(--primary);"></i>
                                            Branch: <strong><?= htmlspecialchars($sale['branch_name'] ?? 'N/A') ?></strong>
                                        </span>
                                        <span class="otc-footer-stat">
                                            <i class="fas fa-credit-card" style="color:var(--primary);"></i>
                                            Subtotal: <strong><?= $currency ?> <?= number_format((float)($sale['subtotal'] ?? 0), 0) ?></strong>
                                        </span>
                                    </div>
                                    <div class="otc-footer-info">
                                        <span class="status-badge <?= $status_class ?>"><i class="fas fa-<?= $status_class === 'paid' ? 'check-circle' : ($status_class === 'cancelled' ? 'times-circle' : 'clock') ?>"></i> <?= strtoupper($status) ?></span>
                                    </div>
                                </div>
                                
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-cash-register"></i>
                        <p>No OTC sales found for this period</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    </div>

</main>

<script>
// ================================================================
// TAB STATE MANAGEMENT
// ================================================================
function updateTabState(key, value) {
    var url = new URL(window.location.href);
    url.searchParams.set(key, value);
    window.history.replaceState({}, '', url.toString());
    
    var inputId = (key === 'tab') ? 'filterTabInput' : 'filterSubTabInput';
    var input = document.getElementById(inputId);
    if (input) input.value = value;
}

function switchTab(tab) {
    updateTabState('tab', tab);
    
    document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));
    
    document.getElementById('tab-' + tab)?.classList.add('active');
    
    if (tab === 'medicines') document.getElementById('tabBtnMed')?.classList.add('active');
    else if (tab === 'equipment') document.getElementById('tabBtnEq')?.classList.add('active');
    else if (tab === 'pharmacy') document.getElementById('tabBtnPharm')?.classList.add('active');
}

function switchSubTab(tab) {
    updateTabState('sub_tab', tab);
    
    document.querySelectorAll('.sub-tab-content').forEach(el => el.classList.remove('active'));
    document.querySelectorAll('.sub-tab-btn').forEach(el => el.classList.remove('active'));
    
    document.getElementById('subtab-' + tab)?.classList.add('active');
    
    if (tab === 'prescriptions') document.getElementById('subTabBtnPresc')?.classList.add('active');
    else if (tab === 'otc') document.getElementById('subTabBtnOtc')?.classList.add('active');
}

function applyQuickFilter(quickValue) {
    var url = new URL(window.location.href);
    url.searchParams.set('quick', quickValue);
    url.searchParams.delete('date_from');
    url.searchParams.delete('date_to');
    
    if (!url.searchParams.has('tab')) {
        url.searchParams.set('tab', '<?= htmlspecialchars($active_tab) ?>');
    }
    if (!url.searchParams.has('sub_tab')) {
        url.searchParams.set('sub_tab', '<?= htmlspecialchars($active_sub_tab) ?>');
    }
    
    window.location.href = url.toString();
}

// ================================================================
// OTHER FUNCTIONS
// ================================================================

function togglePatient(patientId) {
    var body = document.getElementById('body-' + patientId);
    var chevron = document.getElementById('chevron-' + patientId);
    if (body) body.classList.toggle('open');
    if (chevron) chevron.classList.toggle('rotated');
}

function filterTable(tableId, searchTerm, countId) {
    var table = document.getElementById(tableId);
    if (!table) return;
    var tbody = table.querySelector('tbody');
    if (!tbody) return;
    var rows = tbody.querySelectorAll('tr');
    var term = searchTerm.trim().toLowerCase();
    var visibleCount = 0;
    
    var searchBox = document.getElementById(tableId.replace('Table', 'SearchBox'));
    if (searchBox) searchBox.classList.toggle('has-value', term.length > 0);
    
    rows.forEach(function(row) {
        if (row.querySelector('td[colspan]')) return;
        var rowText = '';
        var cells = row.querySelectorAll('.searchable-cell');
        if (cells.length > 0) {
            cells.forEach(c => rowText += ' ' + c.textContent);
        } else {
            rowText = row.textContent;
        }
        rowText = rowText.toLowerCase();
        
        if (term === '' || rowText.indexOf(term) !== -1) {
            row.classList.remove('hidden-row');
            visibleCount++;
        } else {
            row.classList.add('hidden-row');
        }
    });
    
    var countEl = document.getElementById(countId);
    if (countEl) {
        var countText = countEl.querySelector('.count-text');
        if (term === '') {
            countEl.className = 'search-count';
            if (countText) countText.textContent = visibleCount + ' records';
        } else if (visibleCount > 0) {
            countEl.className = 'search-count has-results';
            if (countText) countText.textContent = visibleCount + ' found';
        } else {
            countEl.className = 'search-count no-results';
            if (countText) countText.textContent = 'No results';
        }
    }
}

function clearSearch(tableId, inputId, countId) {
    var input = document.getElementById(inputId);
    if (input) {
        input.value = '';
        filterTable(tableId, '', countId);
    }
}

function scrollTable(wrapperId, direction) {
    var wrapper = document.getElementById(wrapperId);
    if (!wrapper) return;
    wrapper.scrollBy({ left: direction === 'left' ? -300 : 300, behavior: 'smooth' });
}

function scrollOtcContainer(direction) {
    var container = document.getElementById('otcCardsContainer');
    if (!container) return;
    var amount = 500;
    container.scrollBy({ left: direction === 'left' ? -amount : amount, behavior: 'smooth' });
}

function scrollOtcCard(btn, direction) {
    var card = btn.closest('.otc-sale-card');
    if (!card) return;
    var wrapper = card.querySelector('.otc-items-wrapper');
    if (!wrapper) return;
    var amount = 400;
    wrapper.scrollBy({ left: direction === 'left' ? -amount : amount, behavior: 'smooth' });
}

function performPrescSearch() {
    var input = document.getElementById('prescSearch');
    if (!input) return;
    var query = input.value.toLowerCase().trim();
    
    var parentBox = input.closest('.search-box');
    if (parentBox) parentBox.classList.toggle('has-value', query.length > 0);
    
    var patientCards = document.querySelectorAll('#patientsContainer .patient-card');
    var visiblePatients = 0;
    
    patientCards.forEach(function(card) {
        var medRows = card.querySelectorAll('.med-row');
        var hasMatch = false;
        
        medRows.forEach(function(row) {
            var searchData = row.getAttribute('data-search') || '';
            if (query === '' || searchData.includes(query)) hasMatch = true;
        });
        
        if (hasMatch) {
            card.style.display = '';
            visiblePatients++;
            
            if (query !== '') {
                var body = card.querySelector('.patient-body');
                var chevron = card.querySelector('.chevron');
                if (body && !body.classList.contains('open')) {
                    body.classList.add('open');
                    if (chevron) chevron.classList.add('rotated');
                }
            }
            
            card.querySelectorAll('.med-row').forEach(function(row) {
                var searchData = row.getAttribute('data-search') || '';
                if (query === '' || searchData.includes(query)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        } else {
            card.style.display = 'none';
        }
    });
    
    var countEl = document.getElementById('prescCount');
    if (countEl) {
        var countText = countEl.querySelector('.count-text');
        if (query === '') {
            countEl.className = 'search-count';
            if (countText) countText.textContent = visiblePatients + ' patients';
        } else {
            countEl.className = visiblePatients > 0 ? 'search-count has-results' : 'search-count no-results';
            if (countText) countText.textContent = visiblePatients > 0 ? visiblePatients + ' found' : 'No results';
        }
    }
    
    var noRes = document.getElementById('noPrescResults');
    if (noRes) noRes.style.display = (visiblePatients === 0 && query !== '') ? '' : 'none';
}

function clearPrescSearch() {
    var input = document.getElementById('prescSearch');
    if (input) {
        input.value = '';
        performPrescSearch();
    }
}

document.addEventListener('DOMContentLoaded', function() {
    var prescSearch = document.getElementById('prescSearch');
    if (prescSearch) {
        prescSearch.addEventListener('input', performPrescSearch);
    }
    
    var firstBody = document.querySelector('#patientsContainer .patient-body');
    var firstChevron = document.querySelector('#patientsContainer .chevron');
    if (firstBody) {
        setTimeout(function() {
            firstBody.classList.add('open');
            if (firstChevron) firstChevron.classList.add('rotated');
        }, 200);
    }
});

console.log('%c📦 Audit Inventory - BRANCH LOCKED', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
console.log('%c👤 User: <?= htmlspecialchars($user_full_name) ?>', 'font-size:13px; color:#F59E0B; font-weight:bold;');
console.log('%c✅ Inaonyesha data za branch: <?= htmlspecialchars($display_branch_name) ?> (ID: <?= $selected_branch_id ?>)', 'font-size:13px; color:#34D399; font-weight:bold;');
console.log('%c✅ Prescription: GROSS pekee', 'font-size:13px; color:#34D399; font-weight:bold;');
console.log('%c✅ OTC: CARD per Sale', 'font-size:13px; color:#34D399; font-weight:bold;');
console.log('%c✅ Received By = ONLY shown when PAID', 'font-size:13px; color:#34D399; font-weight:bold;');
console.log('%c✅ Tab + Sub-tab persist after filter', 'font-size:13px; color:#34D399; font-weight:bold;');
</script>

</body>
</html>