<?php
// ================================================================
// FILE: frontend/pages/audit/stock_movement.php
// AUDIT - STOCK MOVEMENT REPORT V6.3 (BRANCH LOCKED - FULLY FIXED)
// ================================================================
// ✅ V6.3: Branch filter INAFANYA KAZI kwenye queries zote
// ✅ V6.2: Period Overview + Last Added Stock + Expiry + Stockout
// ✅ V6: Export CSV + Top 5 + Remaining Stock
// ✅ V5: Stock Health Bar
// ✅ Branch LOCKED - Audit anaona branch yake pekee
// ================================================================

date_default_timezone_set('Africa/Dar_es_Salaam');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: /dispensary_system/frontend/pages/login.php');
    exit;
}

if ($_SESSION['role'] !== 'audit') {
    $role = $_SESSION['role'];
    switch ($role) {
        case 'admin': header('Location: /dispensary_system/frontend/pages/admin/dashboard.php'); break;
        case 'doctor': header('Location: /dispensary_system/frontend/pages/doctor/dashboard.php'); break;
        case 'reception': header('Location: /dispensary_system/frontend/pages/reception/dashboard.php'); break;
        case 'pharmacy': header('Location: /dispensary_system/frontend/pages/pharmacy/dashboard.php'); break;
        case 'laboratory': header('Location: /dispensary_system/frontend/pages/laboratory/dashboard.php'); break;
        case 'cashier': header('Location: /dispensary_system/frontend/pages/cashier/dashboard.php'); break;
        default: header('Location: /dispensary_system/frontend/pages/login.php'); break;
    }
    exit;
}

$user_id = $_SESSION['user_id'] ?? 0;
$user_full_name = $_SESSION['full_name'] ?? 'Audit User';
$user_role = $_SESSION['role'] ?? 'audit';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$username = $_SESSION['username'] ?? '';
$profile_pic = $_SESSION['profile_pic'] ?? '';

require_once __DIR__ . '/../../../backend/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
    $db->exec("SET time_zone = '+03:00'");
} catch (Exception $e) {
    die("Database connection error: " . $e->getMessage());
}

$currency = 'TSh';
try {
    $stmt = $db->query("SELECT setting_value FROM system_settings WHERE setting_key = 'currency'");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row && !empty($row['setting_value'])) $currency = $row['setting_value'];
} catch (Exception $e) {}

// ✅ AUDIT: Branch LOCKED
$selected_branch_id = (int)$user_branch_id;
$branch_name_display = $user_branch_name;
try {
    $stmt = $db->prepare("SELECT name FROM branches WHERE id = ? AND status = 'active'");
    $stmt->execute([$selected_branch_id]);
    $branch_data = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($branch_data) $branch_name_display = $branch_data['name'];
} catch (Exception $e) {}

// Filters
$active_tab = $_GET['tab'] ?? 'medicine';
$quick_filter = $_GET['quick'] ?? 'all';
$search = trim($_GET['search'] ?? '');
$selected_item_id = (int)($_GET['item_id'] ?? 0);
$date_from = $_GET['date_from'] ?? date('Y-m-d');
$date_to = $_GET['date_to'] ?? date('Y-m-d');

// Date conditions
$date_cond_purchases = ""; $date_cond_otc = ""; 
$date_cond_prescriptions = ""; $date_cond_lab = "";
$date_params = []; $date_label = "";

switch ($quick_filter) {
    case 'today':
        $date_cond_purchases = " AND DATE(p.created_at) = CURDATE()";
        $date_cond_otc = " AND DATE(os.created_at) = CURDATE()";
        $date_cond_prescriptions = " AND DATE(pr.created_at) = CURDATE()";
        $date_cond_lab = " AND DATE(lt.created_at) = CURDATE()";
        $date_label = "Today • " . date('d M Y');
        break;
    case '1w':
        $date_cond_purchases = " AND p.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
        $date_cond_otc = " AND os.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
        $date_cond_prescriptions = " AND pr.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
        $date_cond_lab = " AND lt.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
        $date_label = "Last 7 Days";
        break;
    case '1m':
        $date_cond_purchases = " AND p.created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
        $date_cond_otc = " AND os.created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
        $date_cond_prescriptions = " AND pr.created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
        $date_cond_lab = " AND lt.created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
        $date_label = "Last 1 Month";
        break;
    case '3m':
        $date_cond_purchases = " AND p.created_at >= DATE_SUB(NOW(), INTERVAL 3 MONTH)";
        $date_cond_otc = " AND os.created_at >= DATE_SUB(NOW(), INTERVAL 3 MONTH)";
        $date_cond_prescriptions = " AND pr.created_at >= DATE_SUB(NOW(), INTERVAL 3 MONTH)";
        $date_cond_lab = " AND lt.created_at >= DATE_SUB(NOW(), INTERVAL 3 MONTH)";
        $date_label = "Last 3 Months";
        break;
    case '6m':
        $date_cond_purchases = " AND p.created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)";
        $date_cond_otc = " AND os.created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)";
        $date_cond_prescriptions = " AND pr.created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)";
        $date_cond_lab = " AND lt.created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)";
        $date_label = "Last 6 Months";
        break;
    case '1y':
        $date_cond_purchases = " AND p.created_at >= DATE_SUB(NOW(), INTERVAL 1 YEAR)";
        $date_cond_otc = " AND os.created_at >= DATE_SUB(NOW(), INTERVAL 1 YEAR)";
        $date_cond_prescriptions = " AND pr.created_at >= DATE_SUB(NOW(), INTERVAL 1 YEAR)";
        $date_cond_lab = " AND lt.created_at >= DATE_SUB(NOW(), INTERVAL 1 YEAR)";
        $date_label = "Last 1 Year";
        break;
    case 'custom':
        $date_cond_purchases = " AND DATE(p.created_at) BETWEEN ? AND ?";
        $date_cond_otc = " AND DATE(os.created_at) BETWEEN ? AND ?";
        $date_cond_prescriptions = " AND DATE(pr.created_at) BETWEEN ? AND ?";
        $date_cond_lab = " AND DATE(lt.created_at) BETWEEN ? AND ?";
        $date_params = [$date_from, $date_to];
        $date_label = date('d M Y', strtotime($date_from)) . ' → ' . date('d M Y', strtotime($date_to));
        break;
    case 'all':
    default:
        $date_label = "All Time";
        break;
}

// ✅ V6.3: Branch conditions - LAZIMA kwenye queries ZOTE
$branch_cond_p = " AND p.branch_id = ?";
$branch_cond_o = " AND os.branch_id = ?";
$branch_cond_pr = " AND pr.branch_id = ?";
$branch_cond_lt = " AND lt.branch_id = ?";
$branch_cond_bi = " AND bi.branch_id = ?";
$branch_cond_mi = " AND mi.branch_id = ?"; // Medications inventory direct
$branch_cond_ei = " AND ei.branch_id = ?"; // Equipment inventory direct
$branch_params_p = [$selected_branch_id];
$branch_params_o = [$selected_branch_id];
$branch_params_pr = [$selected_branch_id];
$branch_params_lt = [$selected_branch_id];
$branch_params_bi = [$selected_branch_id];

// ================================================================
// AUTOCOMPLETE API
// ================================================================
if (isset($_GET['ajax']) && $_GET['ajax'] === 'search') {
    header('Content-Type: application/json');
    $q = trim($_GET['q'] ?? '');
    $type = $_GET['type'] ?? 'medicine';
    
    $results = [];
    
    if ($type === 'medicine') {
        $sql = "SELECT medication_name as name, 
                    SUM(quantity) as total_stock,
                    COUNT(DISTINCT batch_number) as batches,
                    MAX(id) as id_sample
                FROM medications_inventory
                WHERE status = 'active' AND medication_name LIKE ? AND branch_id = ?
                GROUP BY medication_name
                ORDER BY medication_name ASC LIMIT 5";
        $stmt = $db->prepare($sql);
        $stmt->execute(["%$q%", $selected_branch_id]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($results as &$r) { $r['id'] = $r['id_sample']; }
        unset($r);
    } else {
        $sql = "SELECT equipment_name as name, 
                    SUM(quantity) as total_stock,
                    MAX(id) as id_sample
                FROM medical_equipment
                WHERE status = 'active' AND equipment_name LIKE ? AND branch_id = ?
                GROUP BY equipment_name
                ORDER BY equipment_name ASC LIMIT 5";
        $stmt = $db->prepare($sql);
        $stmt->execute(["%$q%", $selected_branch_id]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($results as &$r) { $r['id'] = $r['id_sample']; }
        unset($r);
    }
    
    echo json_encode(['results' => $results]);
    exit;
}

// ================================================================
// ✅ V6.3: TOP 5 MOST USED - BRANCH LOCKED
// ================================================================
$top5_medicines = [];
$top5_equipment = [];

try {
    $sql = "SELECT 
                combined.med_name as name,
                SUM(combined.total_qty) as total_qty,
                (SELECT id FROM medications_inventory 
                    WHERE medication_name = combined.med_name AND status='active' AND branch_id = ?
                    LIMIT 1) as item_id
            FROM (
                SELECT pi.medication_name as med_name, SUM(pi.quantity) as total_qty
                FROM prescription_items pi
                INNER JOIN prescriptions pr ON pi.prescription_id = pr.id
                WHERE 1=1
                $date_cond_prescriptions
                $branch_cond_pr
                GROUP BY pi.medication_name
                
                UNION ALL
                
                SELECT osi.item_name as med_name, SUM(osi.quantity) as total_qty
                FROM otc_sale_items osi
                INNER JOIN otc_sales os ON osi.sale_id = os.id
                WHERE 1=1
                $date_cond_otc
                $branch_cond_o
                GROUP BY osi.item_name
            ) as combined
            GROUP BY combined.med_name
            HAVING item_id IS NOT NULL
            ORDER BY total_qty DESC
            LIMIT 5";
    
    $params = array_merge([$selected_branch_id], $date_params, $branch_params_pr, $date_params, $branch_params_o);
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $top5_medicines = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { error_log("Top 5 medicines: " . $e->getMessage()); }

try {
    $sql = "SELECT 
                combined.eq_name as name,
                SUM(combined.total_qty) as total_qty,
                (SELECT id FROM medical_equipment 
                    WHERE equipment_name = combined.eq_name AND status='active' AND branch_id = ?
                    LIMIT 1) as item_id
            FROM (
                SELECT me.equipment_name as eq_name, COUNT(lt.id) as total_qty
                FROM lab_tests lt
                INNER JOIN lab_test_equipment lte ON lt.test_id = lte.lab_test_id
                INNER JOIN medical_equipment me ON lte.equipment_id = me.id
                WHERE 1=1
                $date_cond_lab
                $branch_cond_lt
                GROUP BY me.equipment_name
                
                UNION ALL
                
                SELECT bi.item_name as eq_name, SUM(bi.quantity) as total_qty
                FROM bill_items bi
                INNER JOIN bills b ON bi.bill_id = b.id
                WHERE bi.item_type = 'equipment'
                $date_cond_prescriptions
                $branch_cond_bi
                GROUP BY bi.item_name
            ) as combined
            GROUP BY combined.eq_name
            HAVING item_id IS NOT NULL
            ORDER BY total_qty DESC
            LIMIT 5";
    
    $params = array_merge([$selected_branch_id], $date_params, $branch_params_lt, $date_params, $branch_params_bi);
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $top5_equipment = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { error_log("Top 5 equipment: " . $e->getMessage()); }

// ================================================================
// HELPER FUNCTIONS
// ================================================================
function getPeriodStartDate($quick_filter, $date_from = null) {
    switch ($quick_filter) {
        case 'today': return date('Y-m-d 00:00:00');
        case '1w': return date('Y-m-d H:i:s', strtotime('-7 days'));
        case '1m': return date('Y-m-d H:i:s', strtotime('-1 month'));
        case '3m': return date('Y-m-d H:i:s', strtotime('-3 months'));
        case '6m': return date('Y-m-d H:i:s', strtotime('-6 months'));
        case '1y': return date('Y-m-d H:i:s', strtotime('-1 year'));
        case 'custom': return $date_from . ' 00:00:00';
        case 'all':
        default: return null;
    }
}

function isInPeriod($date_str, $period_start, $period_end = null) {
    if ($period_start === null) return true;
    $ts = strtotime($date_str);
    if ($ts < strtotime($period_start)) return false;
    if ($period_end !== null && $ts > strtotime($period_end)) return false;
    return true;
}

// ================================================================
// LOAD DETAILS
// ================================================================
$medicine_details = null;
$equipment_details = null;

// ================ MEDICINE DETAILS ================
if ($active_tab === 'medicine' && $selected_item_id > 0) {
    // ✅ V6.3: Verify item belongs to user's branch
    $stmt = $db->prepare("SELECT medication_name FROM medications_inventory WHERE id = ? AND branch_id = ?");
    $stmt->execute([$selected_item_id, $selected_branch_id]);
    $med_row = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($med_row) {
        $med_name = $med_row['medication_name'];
        
        // ✅ V6.3: Get inventory IDs - BRANCH FILTERED
        $inventory_ids = [];
        try {
            $stmt = $db->prepare("SELECT id FROM medications_inventory WHERE medication_name = ? AND branch_id = ?");
            $stmt->execute([$med_name, $selected_branch_id]);
            $inventory_ids = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'id');
        } catch (Exception $e) {}
        
        // CURRENT STOCK - Branch locked
        $current_stock = [];
        try {
            $sql = "SELECT id, medication_name, category, unit, quantity, reorder_level, 
                        unit_cost, selling_price, supplier, expiry_date, batch_number, 
                        branch_id, created_at, updated_at, added_by_name
                    FROM medications_inventory
                    WHERE medication_name = ? AND status = 'active' AND branch_id = ?
                    ORDER BY expiry_date ASC, id ASC";
            $stmt = $db->prepare($sql);
            $stmt->execute([$med_name, $selected_branch_id]);
            $current_stock = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {}
        
        $total_current_stock = 0;
        foreach ($current_stock as $cs) { $total_current_stock += (int)$cs['quantity']; }
        
        $medicine_info = !empty($current_stock) ? $current_stock[0] : [
            'medication_name' => $med_name, 'category' => 'N/A', 'unit' => 'N/A', 'selling_price' => 0
        ];
        
        // ✅ V6.3: PURCHASE HISTORY - BRANCH FILTERED
        $purchase_history_all = [];
        if (!empty($inventory_ids)) {
            try {
                $placeholders = implode(',', array_fill(0, count($inventory_ids), '?'));
                $sql = "SELECT pi.id, pi.quantity, pi.buying_price, pi.selling_price,
                            pi.total_buying_cost, pi.added_by, pi.added_by_name, pi.added_at,
                            p.invoice_number, p.created_at as purchase_created_at,
                            br.name as branch_name
                        FROM purchase_items pi
                        INNER JOIN purchases p ON pi.purchase_id = p.id
                        LEFT JOIN branches br ON p.branch_id = br.id
                        WHERE pi.item_type = 'medicine' 
                        AND pi.item_id IN ($placeholders)
                        AND p.status = 'COMPLETED'
                        $branch_cond_p
                        ORDER BY p.created_at DESC";
                $stmt = $db->prepare($sql);
                $stmt->execute(array_merge($inventory_ids, $branch_params_p));
                $purchase_history_all = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (Exception $e) {}
        }
        
        // Period filter
        $period_start = getPeriodStartDate($quick_filter, $date_from);
        $period_end = ($quick_filter === 'custom') ? $date_to . ' 23:59:59' : null;
        
        $purchase_history = [];
        foreach ($purchase_history_all as $ph) {
            if (isInPeriod($ph['added_at'], $period_start, $period_end)) {
                $purchase_history[] = $ph;
            }
        }
        
        // ✅ PRESCRIPTIONS - BRANCH FILTERED
        $prescriptions_all = [];
        try {
            $sql = "SELECT pi.id as item_id, pi.quantity, pi.dosage, pi.frequency, pi.route,
                        pi.unit_price, pi.total_price, pi.dispensed_at, pi.dispensed_by,
                        pr.id as prescription_id, pr.prescription_number, pr.status as prescription_status,
                        pr.created_at as prescribed_at,
                        v.id as visit_id, v.visit_number, v.visit_date, v.diagnosis, v.disease_code,
                        pat.full_name as patient_name, pat.patient_id as patient_code, pat.phone as patient_phone,
                        u_doctor.full_name as doctor_name,
                        u_pharmacy.full_name as dispensed_by_name,
                        br.name as branch_name
                    FROM prescription_items pi
                    INNER JOIN prescriptions pr ON pi.prescription_id = pr.id
                    LEFT JOIN visits v ON pr.visit_id = v.id
                    LEFT JOIN patients pat ON pr.patient_id = pat.id
                    LEFT JOIN users u_doctor ON pr.doctor_id = u_doctor.id
                    LEFT JOIN users u_pharmacy ON pi.dispensed_by = u_pharmacy.id
                    LEFT JOIN branches br ON pr.branch_id = br.id
                    WHERE pi.medication_name = ?
                    AND pr.branch_id = ?
                    ORDER BY pr.created_at DESC";
            $stmt = $db->prepare($sql);
            $stmt->execute([$med_name, $selected_branch_id]);
            $prescriptions_all = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {}
        
        $prescriptions = [];
        foreach ($prescriptions_all as $p) {
            if (isInPeriod($p['prescribed_at'], $period_start, $period_end)) {
                $prescriptions[] = $p;
            }
        }
        
        // ✅ OTC SALES - BRANCH FILTERED
        $otc_sales_all = [];
        try {
            $sql = "SELECT osi.id as item_id, osi.quantity, osi.unit_price, osi.total_price,
                        osi.item_name, os.id as sale_id, os.sale_number, os.customer_name, os.customer_phone,
                        os.payment_status, os.payment_method, os.created_at as sold_at,
                        u_seller.full_name as sold_by_name,
                        br.name as branch_name
                    FROM otc_sale_items osi
                    INNER JOIN otc_sales os ON osi.sale_id = os.id
                    LEFT JOIN users u_seller ON os.sold_by = u_seller.id
                    LEFT JOIN branches br ON os.branch_id = br.id
                    WHERE osi.item_name LIKE ?
                    AND os.branch_id = ?
                    ORDER BY os.created_at DESC";
            $stmt = $db->prepare($sql);
            $stmt->execute(["%$med_name%", $selected_branch_id]);
            $otc_sales_all = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {}
        
        $otc_sales = [];
        foreach ($otc_sales_all as $os) {
            if (isInPeriod($os['sold_at'], $period_start, $period_end)) {
                $otc_sales[] = $os;
            }
        }
        
        // ✅ BILL ITEMS - BRANCH FILTERED
        $bill_items = [];
        try {
            $sql = "SELECT bi.id, bi.bill_id, bi.item_name, bi.quantity, bi.unit_price, 
                        bi.total_price, bi.status as item_status, bi.created_at as item_created_at,
                        b.bill_number, b.visit_id,
                        v.visit_number, v.diagnosis,
                        pat.full_name as patient_name, pat.patient_id as patient_code,
                        u_doctor.full_name as doctor_name,
                        br.name as branch_name
                    FROM bill_items bi
                    INNER JOIN bills b ON bi.bill_id = b.id
                    LEFT JOIN visits v ON b.visit_id = v.id
                    LEFT JOIN patients pat ON b.patient_id = pat.id
                    LEFT JOIN users u_doctor ON v.doctor_id = u_doctor.id
                    LEFT JOIN branches br ON b.branch_id = br.id
                    WHERE bi.item_type = 'medication'
                    AND bi.item_name LIKE ?
                    AND bi.branch_id = ?
                    ORDER BY b.created_at DESC
                    LIMIT 300";
            $stmt = $db->prepare($sql);
            $stmt->execute(["%$med_name%", $selected_branch_id]);
            $bill_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {}
        
        // SUMMARIES
        $summary = [
            'purchase_qty' => 0, 'purchase_count' => 0,
            'pending_qty' => 0, 'pending_count' => 0,
            'confirmed_qty' => 0, 'confirmed_count' => 0,
            'dispensed_qty' => 0, 'dispensed_count' => 0,
            'otc_qty' => 0, 'otc_count' => 0,
            'total_movement' => 0,
            'unique_visits' => 0, 'unique_patients' => 0
        ];
        
        foreach ($purchase_history as $ph) { 
            $summary['purchase_qty'] += (int)$ph['quantity']; 
            $summary['purchase_count']++;
        }
        
        $visit_keys = []; $patient_keys = [];
        foreach ($prescriptions as $p) {
            $qty = (int)$p['quantity'];
            $status = strtolower($p['prescription_status'] ?? 'pending');
            
            if ($status === 'pending') { $summary['pending_qty'] += $qty; $summary['pending_count']++; }
            elseif ($status === 'confirmed') { $summary['confirmed_qty'] += $qty; $summary['confirmed_count']++; }
            elseif ($status === 'dispensed') { $summary['dispensed_qty'] += $qty; $summary['dispensed_count']++; }
            
            if (!empty($p['visit_id'])) $visit_keys[$p['visit_id']] = true;
            if (!empty($p['patient_code'])) $patient_keys[$p['patient_code']] = true;
        }
        
        foreach ($otc_sales as $os) { 
            $summary['otc_qty'] += (int)$os['quantity']; 
            $summary['otc_count']++;
        }
        
        foreach ($bill_items as $bi) {
            if (!empty($bi['visit_id'])) $visit_keys[$bi['visit_id']] = true;
            if (!empty($bi['patient_code'])) $patient_keys[$bi['patient_code']] = true;
        }
        
        $summary['total_movement'] = $summary['pending_qty'] + $summary['confirmed_qty'] + $summary['dispensed_qty'] + $summary['otc_qty'];
        $summary['unique_visits'] = count($visit_keys);
        $summary['unique_patients'] = count($patient_keys);
        
        // REMAINING STOCK
        $stock_value = 0; $average_selling_price = 0; $price_count = 0;
        foreach ($current_stock as $cs) {
            $stock_value += (int)$cs['quantity'] * (float)($cs['selling_price'] ?? 0);
            if ((float)($cs['selling_price'] ?? 0) > 0) {
                $average_selling_price += (float)$cs['selling_price'];
                $price_count++;
            }
        }
        if ($price_count > 0) $average_selling_price = $average_selling_price / $price_count;
        
        $reorder_level = !empty($current_stock) ? (int)$current_stock[0]['reorder_level'] : 0;
        
        $remaining_stock = [
            'current_stock' => $total_current_stock,
            'total_used' => $summary['total_movement'],
            'stock_value' => $stock_value,
            'average_selling_price' => $average_selling_price,
            'reorder_level' => $reorder_level,
            'status' => 'sufficient',
            'status_label' => 'Sufficient',
            'status_color' => 'success',
            'status_icon' => 'fa-check-circle'
        ];
        
        if ($total_current_stock == 0) {
            $remaining_stock['status'] = 'out_of_stock';
            $remaining_stock['status_label'] = 'Out of Stock';
            $remaining_stock['status_color'] = 'danger';
            $remaining_stock['status_icon'] = 'fa-times-circle';
        } elseif ($total_current_stock <= $reorder_level) {
            $remaining_stock['status'] = 'critical';
            $remaining_stock['status_label'] = 'Critical - Reorder';
            $remaining_stock['status_color'] = 'danger';
            $remaining_stock['status_icon'] = 'fa-exclamation-triangle';
        } elseif ($total_current_stock <= ($reorder_level * 2)) {
            $remaining_stock['status'] = 'low';
            $remaining_stock['status_label'] = 'Low Stock';
            $remaining_stock['status_color'] = 'warning';
            $remaining_stock['status_icon'] = 'fa-exclamation-circle';
        }
        
        // PERIOD OVERVIEW
        $total_purchased_before = 0;
        foreach ($purchase_history_all as $ph) {
            if ($period_start === null || strtotime($ph['added_at']) < strtotime($period_start)) {
                $total_purchased_before += (int)$ph['quantity'];
            }
        }
        
        $total_movements_before = 0;
        foreach ($prescriptions_all as $p) {
            if ($period_start === null || strtotime($p['prescribed_at']) < strtotime($period_start)) {
                $total_movements_before += (int)$p['quantity'];
            }
        }
        foreach ($otc_sales_all as $os) {
            if ($period_start === null || strtotime($os['sold_at']) < strtotime($period_start)) {
                $total_movements_before += (int)$os['quantity'];
            }
        }
        
        $stock_at_period_start = max(0, $total_purchased_before - $total_movements_before);
        
        $period_purchases_total = 0;
        $period_purchases_users = [];
        foreach ($purchase_history as $ph) {
            $period_purchases_total += (int)$ph['quantity'];
            if (!empty($ph['added_by_name'])) $period_purchases_users[$ph['added_by_name']] = true;
        }
        
        $period_prescriptions_qty = 0;
        $period_otc_qty = 0;
        foreach ($prescriptions as $p) $period_prescriptions_qty += (int)$p['quantity'];
        foreach ($otc_sales as $os) $period_otc_qty += (int)$os['quantity'];
        
        $period_info = [
            'filter_label' => $date_label,
            'period_start' => $period_start,
            'stock_at_period_start' => $stock_at_period_start,
            'purchases_count' => count($purchase_history),
            'purchases_total_qty' => $period_purchases_total,
            'purchases_unique_users' => array_keys($period_purchases_users),
            'movements_prescriptions' => $period_prescriptions_qty,
            'movements_otc' => $period_otc_qty,
            'movements_total' => $period_prescriptions_qty + $period_otc_qty,
            'total_purchased_before_period' => $total_purchased_before,
            'total_movements_before_period' => $total_movements_before
        ];
        
        // LAST ADDED STOCK
        if (count($purchase_history_all) > 0) {
            $last_purchase = $purchase_history_all[0];
            $last_added_qty = (int)$last_purchase['quantity'];
            $last_added_date = $last_purchase['added_at'];
            $last_date_ts = strtotime($last_added_date);
            
            $purchased_before_last = 0;
            for ($i = 1; $i < count($purchase_history_all); $i++) {
                $purchased_before_last += (int)$purchase_history_all[$i]['quantity'];
            }
            
            $movements_before_last = 0;
            foreach ($prescriptions_all as $p) {
                if (strtotime($p['prescribed_at']) < $last_date_ts) {
                    $movements_before_last += (int)$p['quantity'];
                }
            }
            foreach ($otc_sales_all as $os) {
                if (strtotime($os['sold_at']) < $last_date_ts) {
                    $movements_before_last += (int)$os['quantity'];
                }
            }
            
            $previous_stock_before_last = max(0, $purchased_before_last - $movements_before_last);
            $available_after_last_add = $previous_stock_before_last + $last_added_qty;
            
            $movements_after_last = 0;
            foreach ($prescriptions_all as $p) {
                if (strtotime($p['prescribed_at']) >= $last_date_ts) {
                    $movements_after_last += (int)$p['quantity'];
                }
            }
            foreach ($otc_sales_all as $os) {
                if (strtotime($os['sold_at']) >= $last_date_ts) {
                    $movements_after_last += (int)$os['quantity'];
                }
            }
            
            $expected_remaining = $available_after_last_add - $movements_after_last;
            $actual_remaining = $total_current_stock;
            $variance = $actual_remaining - $expected_remaining;
            
            $last_stock_info = [
                'has_last_purchase' => true,
                'last_added_qty' => $last_added_qty,
                'last_added_date' => $last_added_date,
                'last_added_by' => $last_purchase['added_by_name'],
                'last_added_invoice' => $last_purchase['invoice_number'],
                'total_purchased_before_last' => $purchased_before_last,
                'movements_before_last' => $movements_before_last,
                'previous_stock' => $previous_stock_before_last,
                'available_after_last_add' => $available_after_last_add,
                'movements_after_last' => $movements_after_last,
                'expected_remaining' => $expected_remaining,
                'actual_remaining' => $actual_remaining,
                'variance' => $variance,
                'is_accurate' => ($variance == 0)
            ];
        } else {
            $last_stock_info = [
                'has_last_purchase' => false,
                'last_added_qty' => 0, 'last_added_date' => null,
                'last_added_by' => 'N/A', 'last_added_invoice' => 'N/A',
                'total_purchased_before_last' => 0, 'movements_before_last' => 0,
                'previous_stock' => 0, 'available_after_last_add' => 0,
                'movements_after_last' => 0, 'expected_remaining' => 0,
                'actual_remaining' => $total_current_stock,
                'variance' => $total_current_stock, 'is_accurate' => false
            ];
        }
        
        // DAYS UNTIL STOCKOUT
        $days_in_period = 30;
        if ($quick_filter === 'custom') $days_in_period = max(1, (strtotime($date_to) - strtotime($date_from)) / 86400);
        elseif ($quick_filter === '1w') $days_in_period = 7;
        elseif ($quick_filter === '1m') $days_in_period = 30;
        elseif ($quick_filter === '3m') $days_in_period = 90;
        elseif ($quick_filter === '6m') $days_in_period = 180;
        elseif ($quick_filter === '1y') $days_in_period = 365;
        elseif ($quick_filter === 'today') $days_in_period = 1;
        
        $avg_daily_usage = $days_in_period > 0 ? $summary['total_movement'] / $days_in_period : 0;
        $days_until_stockout = $avg_daily_usage > 0 ? round($total_current_stock / $avg_daily_usage) : 999;
        
        $medicine_details = [
            'name' => $med_name,
            'info' => $medicine_info,
            'purchase_history' => $purchase_history,
            'current_stock' => $current_stock,
            'total_current_stock' => $total_current_stock,
            'prescriptions' => $prescriptions,
            'otc_sales' => $otc_sales,
            'bill_items' => $bill_items,
            'summary' => $summary,
            'remaining_stock' => $remaining_stock,
            'last_stock_info' => $last_stock_info,
            'period_info' => $period_info,
            'days_until_stockout' => $days_until_stockout,
            'avg_daily_usage' => $avg_daily_usage
        ];
    }
}

// ================ EQUIPMENT DETAILS ================
if ($active_tab === 'equipment' && $selected_item_id > 0) {
    // ✅ V6.3: Verify item belongs to user's branch
    $stmt = $db->prepare("SELECT equipment_name FROM medical_equipment WHERE id = ? AND branch_id = ?");
    $stmt->execute([$selected_item_id, $selected_branch_id]);
    $eq_row = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($eq_row) {
        $eq_name = $eq_row['equipment_name'];
        
        // ✅ V6.3: Equipment IDs - BRANCH FILTERED
        $equipment_ids = [];
        try {
            $stmt = $db->prepare("SELECT id FROM medical_equipment WHERE equipment_name = ? AND branch_id = ?");
            $stmt->execute([$eq_name, $selected_branch_id]);
            $equipment_ids = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'id');
        } catch (Exception $e) {}
        
        // CURRENT STOCK
        $current_stock = [];
        try {
            $sql = "SELECT id, equipment_name, category, unit, quantity, reorder_level,
                        unit_cost, selling_price, supplier, created_at, added_by_name
                    FROM medical_equipment
                    WHERE equipment_name = ? AND status = 'active' AND branch_id = ?
                    ORDER BY id ASC";
            $stmt = $db->prepare($sql);
            $stmt->execute([$eq_name, $selected_branch_id]);
            $current_stock = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {}
        
        $total_current_stock = 0;
        foreach ($current_stock as $cs) { $total_current_stock += (int)$cs['quantity']; }
        
        $equipment_info = !empty($current_stock) ? $current_stock[0] : [
            'equipment_name' => $eq_name, 'category' => 'N/A', 'unit' => 'N/A', 'selling_price' => 0
        ];
        
        $period_start = getPeriodStartDate($quick_filter, $date_from);
        $period_end = ($quick_filter === 'custom') ? $date_to . ' 23:59:59' : null;
        
        // ✅ PURCHASE HISTORY - BRANCH FILTERED
        $purchase_history_all = [];
        if (!empty($equipment_ids)) {
            try {
                $placeholders = implode(',', array_fill(0, count($equipment_ids), '?'));
                $sql = "SELECT pi.id, pi.quantity, pi.buying_price, pi.selling_price,
                            pi.total_buying_cost, pi.added_by_name, pi.added_at,
                            p.invoice_number, br.name as branch_name
                        FROM purchase_items pi
                        INNER JOIN purchases p ON pi.purchase_id = p.id
                        LEFT JOIN branches br ON p.branch_id = br.id
                        WHERE pi.item_type = 'equipment'
                        AND pi.item_id IN ($placeholders)
                        AND p.status = 'COMPLETED'
                        $branch_cond_p
                        ORDER BY p.created_at DESC";
                $stmt = $db->prepare($sql);
                $stmt->execute(array_merge($equipment_ids, $branch_params_p));
                $purchase_history_all = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (Exception $e) {}
        }
        
        $purchase_history = [];
        foreach ($purchase_history_all as $ph) {
            if (isInPeriod($ph['added_at'], $period_start, $period_end)) {
                $purchase_history[] = $ph;
            }
        }
        
        // ✅ BILL ITEMS - BRANCH FILTERED
        $bill_items_all = [];
        if (!empty($equipment_ids)) {
            try {
                $sql = "SELECT bi.id, bi.bill_id, bi.item_name, bi.item_id, bi.quantity, 
                            bi.unit_price, bi.total_price, bi.status as item_status, 
                            bi.created_at as item_created_at,
                            b.bill_number, b.visit_id,
                            v.visit_number, v.diagnosis,
                            pat.full_name as patient_name, pat.patient_id as patient_code,
                            u_doctor.full_name as doctor_name,
                            br.name as branch_name
                        FROM bill_items bi
                        INNER JOIN bills b ON bi.bill_id = b.id
                        LEFT JOIN visits v ON b.visit_id = v.id
                        LEFT JOIN patients pat ON b.patient_id = pat.id
                        LEFT JOIN users u_doctor ON v.doctor_id = u_doctor.id
                        LEFT JOIN branches br ON b.branch_id = br.id
                        WHERE bi.item_type = 'equipment'
                        AND bi.item_id IN (" . implode(',', array_fill(0, count($equipment_ids), '?')) . ")
                        $branch_cond_bi
                        ORDER BY b.created_at DESC";
                $stmt = $db->prepare($sql);
                $stmt->execute(array_merge($equipment_ids, $branch_params_bi));
                $bill_items_all = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (Exception $e) {}
        }
        
        $bill_items = [];
        foreach ($bill_items_all as $bi) {
            if (isInPeriod($bi['item_created_at'], $period_start, $period_end)) {
                $bill_items[] = $bi;
            }
        }
        
        // ✅ LAB TESTS - BRANCH FILTERED
        $lab_tests_all = [];
        if (!empty($equipment_ids)) {
            try {
                $sql = "SELECT lt.id as lab_test_id, lt.test_name, lt.status as test_status,
                            lt.created_at as test_created_at, lt.visit_id,
                            v.visit_number, v.diagnosis,
                            pat.full_name as patient_name, pat.patient_id as patient_code,
                            u_doctor.full_name as doctor_name,
                            u_lab.full_name as lab_tech_name,
                            br.name as branch_name
                        FROM lab_tests lt
                        INNER JOIN lab_test_equipment lte ON lt.test_id = lte.lab_test_id
                        LEFT JOIN visits v ON lt.visit_id = v.id
                        LEFT JOIN patients pat ON lt.patient_id = pat.id
                        LEFT JOIN users u_doctor ON lt.doctor_id = u_doctor.id
                        LEFT JOIN users u_lab ON lt.lab_technician_id = u_lab.id
                        LEFT JOIN branches br ON lt.branch_id = br.id
                        WHERE lte.equipment_id IN (" . implode(',', array_fill(0, count($equipment_ids), '?')) . ")
                        $branch_cond_lt
                        ORDER BY lt.created_at DESC";
                $stmt = $db->prepare($sql);
                $stmt->execute(array_merge($equipment_ids, $branch_params_lt));
                $lab_tests_all = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (Exception $e) {}
        }
        
        $lab_tests = [];
        foreach ($lab_tests_all as $lt) {
            if (isInPeriod($lt['test_created_at'], $period_start, $period_end)) {
                $lab_tests[] = $lt;
            }
        }
        
        // SUMMARIES
        $summary = [
            'purchase_qty' => 0, 'purchase_count' => 0,
            'pending_qty' => 0, 'pending_count' => 0,
            'in_progress_qty' => 0, 'in_progress_count' => 0,
            'completed_qty' => 0, 'completed_count' => 0,
            'bill_qty' => 0, 'bill_count' => 0,
            'lab_qty' => 0, 'lab_count' => 0,
            'total_movement' => 0,
            'unique_visits' => 0, 'unique_patients' => 0
        ];
        
        foreach ($purchase_history as $ph) {
            $summary['purchase_qty'] += (int)$ph['quantity'];
            $summary['purchase_count']++;
        }
        
        foreach ($bill_items as $bi) {
            $summary['bill_qty'] += (int)$bi['quantity'];
            $summary['bill_count']++;
        }
        
        foreach ($lab_tests as $lt) {
            $status = strtolower($lt['test_status'] ?? 'pending');
            $summary['lab_qty']++;
            $summary['lab_count']++;
            
            if ($status === 'pending') { $summary['pending_qty']++; $summary['pending_count']++; }
            elseif ($status === 'in_progress') { $summary['in_progress_qty']++; $summary['in_progress_count']++; }
            elseif ($status === 'completed') { $summary['completed_qty']++; $summary['completed_count']++; }
        }
        
        $visit_keys = []; $patient_keys = [];
        foreach ($bill_items as $bi) {
            if (!empty($bi['visit_id'])) $visit_keys[$bi['visit_id']] = true;
            if (!empty($bi['patient_code'])) $patient_keys[$bi['patient_code']] = true;
        }
        foreach ($lab_tests as $lt) {
            if (!empty($lt['visit_id'])) $visit_keys[$lt['visit_id']] = true;
            if (!empty($lt['patient_code'])) $patient_keys[$lt['patient_code']] = true;
        }
        
        $summary['total_movement'] = $summary['bill_qty'] + $summary['lab_qty'];
        $summary['unique_visits'] = count($visit_keys);
        $summary['unique_patients'] = count($patient_keys);
        
        // REMAINING STOCK
        $stock_value = 0; $average_selling_price = 0; $price_count = 0;
        foreach ($current_stock as $cs) {
            $stock_value += (int)$cs['quantity'] * (float)($cs['selling_price'] ?? 0);
            if ((float)($cs['selling_price'] ?? 0) > 0) {
                $average_selling_price += (float)$cs['selling_price'];
                $price_count++;
            }
        }
        if ($price_count > 0) $average_selling_price = $average_selling_price / $price_count;
        
        $reorder_level = !empty($current_stock) ? (int)$current_stock[0]['reorder_level'] : 0;
        
        $remaining_stock = [
            'current_stock' => $total_current_stock,
            'total_used' => $summary['total_movement'],
            'stock_value' => $stock_value,
            'average_selling_price' => $average_selling_price,
            'reorder_level' => $reorder_level,
            'status' => 'sufficient',
            'status_label' => 'Sufficient',
            'status_color' => 'success',
            'status_icon' => 'fa-check-circle'
        ];
        
        if ($total_current_stock == 0) {
            $remaining_stock['status'] = 'out_of_stock';
            $remaining_stock['status_label'] = 'Out of Stock';
            $remaining_stock['status_color'] = 'danger';
            $remaining_stock['status_icon'] = 'fa-times-circle';
        } elseif ($total_current_stock <= $reorder_level) {
            $remaining_stock['status'] = 'critical';
            $remaining_stock['status_label'] = 'Critical - Reorder';
            $remaining_stock['status_color'] = 'danger';
            $remaining_stock['status_icon'] = 'fa-exclamation-triangle';
        } elseif ($total_current_stock <= ($reorder_level * 2)) {
            $remaining_stock['status'] = 'low';
            $remaining_stock['status_label'] = 'Low Stock';
            $remaining_stock['status_color'] = 'warning';
            $remaining_stock['status_icon'] = 'fa-exclamation-circle';
        }
        
        // PERIOD OVERVIEW
        $total_purchased_before = 0;
        foreach ($purchase_history_all as $ph) {
            if ($period_start === null || strtotime($ph['added_at']) < strtotime($period_start)) {
                $total_purchased_before += (int)$ph['quantity'];
            }
        }
        
        $total_movements_before = 0;
        foreach ($bill_items_all as $bi) {
            if ($period_start === null || strtotime($bi['item_created_at']) < strtotime($period_start)) {
                $total_movements_before += (int)$bi['quantity'];
            }
        }
        foreach ($lab_tests_all as $lt) {
            if ($period_start === null || strtotime($lt['test_created_at']) < strtotime($period_start)) {
                $total_movements_before += 1;
            }
        }
        
        $stock_at_period_start = max(0, $total_purchased_before - $total_movements_before);
        
        $period_purchases_total = 0;
        $period_purchases_users = [];
        foreach ($purchase_history as $ph) {
            $period_purchases_total += (int)$ph['quantity'];
            if (!empty($ph['added_by_name'])) $period_purchases_users[$ph['added_by_name']] = true;
        }
        
        $period_bill_qty = 0;
        $period_lab_qty = 0;
        foreach ($bill_items as $bi) $period_bill_qty += (int)$bi['quantity'];
        foreach ($lab_tests as $lt) $period_lab_qty += 1;
        
        $period_info = [
            'filter_label' => $date_label,
            'period_start' => $period_start,
            'stock_at_period_start' => $stock_at_period_start,
            'purchases_count' => count($purchase_history),
            'purchases_total_qty' => $period_purchases_total,
            'purchases_unique_users' => array_keys($period_purchases_users),
            'movements_bill' => $period_bill_qty,
            'movements_lab' => $period_lab_qty,
            'movements_total' => $period_bill_qty + $period_lab_qty,
            'total_purchased_before_period' => $total_purchased_before,
            'total_movements_before_period' => $total_movements_before
        ];
        
        // LAST ADDED STOCK
        if (count($purchase_history_all) > 0) {
            $last_purchase = $purchase_history_all[0];
            $last_added_qty = (int)$last_purchase['quantity'];
            $last_added_date = $last_purchase['added_at'];
            $last_date_ts = strtotime($last_added_date);
            
            $purchased_before_last = 0;
            for ($i = 1; $i < count($purchase_history_all); $i++) {
                $purchased_before_last += (int)$purchase_history_all[$i]['quantity'];
            }
            
            $movements_before_last = 0;
            foreach ($bill_items_all as $bi) {
                if (strtotime($bi['item_created_at']) < $last_date_ts) $movements_before_last += (int)$bi['quantity'];
            }
            foreach ($lab_tests_all as $lt) {
                if (strtotime($lt['test_created_at']) < $last_date_ts) $movements_before_last += 1;
            }
            
            $previous_stock_before_last = max(0, $purchased_before_last - $movements_before_last);
            $available_after_last_add = $previous_stock_before_last + $last_added_qty;
            
            $movements_after_last = 0;
            foreach ($bill_items_all as $bi) {
                if (strtotime($bi['item_created_at']) >= $last_date_ts) $movements_after_last += (int)$bi['quantity'];
            }
            foreach ($lab_tests_all as $lt) {
                if (strtotime($lt['test_created_at']) >= $last_date_ts) $movements_after_last += 1;
            }
            
            $expected_remaining = $available_after_last_add - $movements_after_last;
            $actual_remaining = $total_current_stock;
            $variance = $actual_remaining - $expected_remaining;
            
            $last_stock_info = [
                'has_last_purchase' => true,
                'last_added_qty' => $last_added_qty,
                'last_added_date' => $last_added_date,
                'last_added_by' => $last_purchase['added_by_name'],
                'last_added_invoice' => $last_purchase['invoice_number'],
                'total_purchased_before_last' => $purchased_before_last,
                'movements_before_last' => $movements_before_last,
                'previous_stock' => $previous_stock_before_last,
                'available_after_last_add' => $available_after_last_add,
                'movements_after_last' => $movements_after_last,
                'expected_remaining' => $expected_remaining,
                'actual_remaining' => $actual_remaining,
                'variance' => $variance,
                'is_accurate' => ($variance == 0)
            ];
        } else {
            $last_stock_info = [
                'has_last_purchase' => false,
                'last_added_qty' => 0, 'last_added_date' => null,
                'last_added_by' => 'N/A', 'last_added_invoice' => 'N/A',
                'total_purchased_before_last' => 0, 'movements_before_last' => 0,
                'previous_stock' => 0, 'available_after_last_add' => 0,
                'movements_after_last' => 0, 'expected_remaining' => 0,
                'actual_remaining' => $total_current_stock,
                'variance' => $total_current_stock, 'is_accurate' => false
            ];
        }
        
        // DAYS UNTIL STOCKOUT
        $days_in_period = 30;
        if ($quick_filter === 'custom') $days_in_period = max(1, (strtotime($date_to) - strtotime($date_from)) / 86400);
        elseif ($quick_filter === '1w') $days_in_period = 7;
        elseif ($quick_filter === '1m') $days_in_period = 30;
        elseif ($quick_filter === '3m') $days_in_period = 90;
        elseif ($quick_filter === '6m') $days_in_period = 180;
        elseif ($quick_filter === '1y') $days_in_period = 365;
        elseif ($quick_filter === 'today') $days_in_period = 1;
        
        $avg_daily_usage = $days_in_period > 0 ? $summary['total_movement'] / $days_in_period : 0;
        $days_until_stockout = $avg_daily_usage > 0 ? round($total_current_stock / $avg_daily_usage) : 999;
        
        $equipment_details = [
            'name' => $eq_name,
            'info' => $equipment_info,
            'purchase_history' => $purchase_history,
            'current_stock' => $current_stock,
            'total_current_stock' => $total_current_stock,
            'bill_items' => $bill_items,
            'lab_tests' => $lab_tests,
            'summary' => $summary,
            'remaining_stock' => $remaining_stock,
            'last_stock_info' => $last_stock_info,
            'period_info' => $period_info,
            'days_until_stockout' => $days_until_stockout,
            'avg_daily_usage' => $avg_daily_usage
        ];
    }
}

// ================================================================
// EXPORT CSV
// ================================================================
if (isset($_GET['export']) && $_GET['export'] === 'csv' && $selected_item_id > 0) {
    $details = $active_tab === 'medicine' ? $medicine_details : $equipment_details;
    
    if ($details) {
        $filename = 'stock_movement_' . preg_replace('/[^a-zA-Z0-9]/', '_', $details['name']) . '_' . date('Y-m-d') . '.csv';
        
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        $output = fopen('php://output', 'w');
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        fputcsv($output, ['STOCK MOVEMENT REPORT']);
        fputcsv($output, ['Item', $details['name']]);
        fputcsv($output, ['Type', strtoupper($active_tab)]);
        fputcsv($output, ['Branch', $branch_name_display]);
        fputcsv($output, ['Period', $date_label]);
        fputcsv($output, ['Generated', date('Y-m-d H:i:s')]);
        fputcsv($output, []);
        
        $pi = $details['period_info'];
        fputcsv($output, ['PERIOD OVERVIEW']);
        fputcsv($output, ['Stock at Period Start', $pi['stock_at_period_start']]);
        fputcsv($output, ['Added in Period', $pi['purchases_total_qty']]);
        fputcsv($output, ['Movements in Period', $pi['movements_total']]);
        fputcsv($output, ['Current Stock', $details['total_current_stock']]);
        fputcsv($output, []);
        
        $lsi = $details['last_stock_info'];
        fputcsv($output, ['LAST ADDED STOCK']);
        fputcsv($output, ['Last Added Qty', $lsi['last_added_qty']]);
        fputcsv($output, ['Last Added Date', $lsi['last_added_date']]);
        fputcsv($output, ['Last Added By', $lsi['last_added_by']]);
        fputcsv($output, ['Previous Stock', $lsi['previous_stock']]);
        fputcsv($output, ['Available After Last Add', $lsi['available_after_last_add']]);
        fputcsv($output, ['Movements After Last', $lsi['movements_after_last']]);
        fputcsv($output, ['Expected Remaining', $lsi['expected_remaining']]);
        fputcsv($output, ['Actual Inventory', $lsi['actual_remaining']]);
        fputcsv($output, ['Variance', $lsi['variance']]);
        fputcsv($output, ['Status', $lsi['is_accurate'] ? 'ACCURATE' : 'VARIANCE']);
        fputcsv($output, []);
        
        fputcsv($output, ['PURCHASE HISTORY']);
        fputcsv($output, ['#', 'Invoice', 'Qty', 'Buying', 'Selling', 'Total Cost', 'Added By', 'Date']);
        $i = 1;
        foreach ($details['purchase_history'] as $ph) {
            fputcsv($output, [$i++, $ph['invoice_number'], $ph['quantity'], $ph['buying_price'], $ph['selling_price'], $ph['total_buying_cost'] ?? 0, $ph['added_by_name'], $ph['added_at']]);
        }
        fputcsv($output, []);
        
        fputcsv($output, ['CURRENT STOCK']);
        fputcsv($output, ['#', 'Batch', 'Qty', 'Reorder', 'Selling', 'Expiry', 'Supplier']);
        $i = 1;
        foreach ($details['current_stock'] as $cs) {
            fputcsv($output, [$i++, $cs['batch_number'] ?? '', $cs['quantity'], $cs['reorder_level'], $cs['selling_price'], $cs['expiry_date'] ?? 'N/A', $cs['supplier'] ?? '']);
        }
        
        fclose($output);
        exit;
    }
}

$logo_path = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

include_once __DIR__ . '/../../components/audit_header.php';
include_once __DIR__ . '/../../components/audit_sidebar.php';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark' : 'light' ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Stock Movement Report • Braick Audit</title>
<link rel="icon" href="<?= $logo_path ?>" type="image/png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=JetBrains+Mono:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
:root {
    --font-primary: 'Inter', sans-serif;
    --font-mono: 'JetBrains Mono', monospace;
    --primary: #0B5ED7; --primary-dark: #0A4CA8; --primary-light: #3B82F6; --primary-bg: #E8F0FE;
    --success: #059669; --success-bg: #D1FAE5;
    --danger: #DC2626; --danger-bg: #FEE2E2;
    --warning: #D97706; --warning-bg: #FEF3C7;
    --purple: #7C3AED; --purple-bg: #EDE9FE;
    --cyan: #0891B2; --cyan-bg: #CFFAFE;
    --slate: #94A3B8; --slate-bg: #F1F5F9;
    --bg-body: #F1F5F9; --bg-card: #FFFFFF;
    --text-primary: #1E293B; --text-secondary: #64748B; --text-muted: #94A3B8;
    --border-color: #E2E8F0; --border-strong: #CBD5E1;
    --shadow-sm: 0 1px 3px rgba(0,0,0,0.06);
    --shadow-md: 0 4px 12px rgba(0,0,0,0.08);
    --shadow-lg: 0 10px 25px rgba(0,0,0,0.1);
    --radius-sm: 8px; --radius-md: 12px; --radius-lg: 16px; --radius-full: 9999px;
}
[data-theme="dark"] {
    --bg-body: #0B1220; --bg-card: #111C33;
    --text-primary: #F1F5F9; --text-secondary: #94A3B8; --text-muted: #64748B;
    --border-color: #1E2E4A; --border-strong: #2A3E5F;
    --primary-bg: #12294A; --success-bg: #0F2E22; --danger-bg: #3A1414;
    --warning-bg: #3A2A0F; --purple-bg: #2A1A4A; --cyan-bg: #0A2E3A;
    --slate-bg: #1E2A3D;
}
* { margin: 0; padding: 0; box-sizing: border-box; }
body { font-family: var(--font-primary); background: var(--bg-body); color: var(--text-primary); -webkit-font-smoothing: antialiased; line-height: 1.5; min-height: 100vh; }
.money-cell, .font-mono { font-family: var(--font-mono) !important; font-variant-numeric: tabular-nums; }

.page-header { background: linear-gradient(135deg, #0B5ED7 0%, #0A4CA8 50%, #7C3AED 100%); border-radius: var(--radius-lg); padding: 24px 28px; margin-bottom: 20px; display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 16px; box-shadow: 0 10px 40px rgba(11, 94, 215, 0.35); position: relative; overflow: hidden; }
.page-header::before { content: ''; position: absolute; top: -50%; right: -10%; width: 400px; height: 400px; background: radial-gradient(circle, rgba(255,255,255,0.12) 0%, transparent 70%); border-radius: 50%; pointer-events: none; }
.page-header .page-title { color: white; font-size: 1.5rem; font-weight: 900; display: flex; align-items: center; gap: 12px; flex-wrap: wrap; position: relative; z-index: 1; }
.page-header .page-title i { font-size: 1.6rem; color: #93C5FD; }
.branch-tag { background: rgba(255,255,255,0.15); color: white; padding: 4px 12px; border-radius: var(--radius-full); font-size: 0.68rem; font-weight: 600; display: inline-flex; align-items: center; gap: 5px; backdrop-filter: blur(10px); border: 1px solid rgba(255,255,255,0.15); }
.branch-tag.locked { background: linear-gradient(135deg, #FCD34D, #F59E0B); color: #78350F; font-weight: 800; box-shadow: 0 2px 8px rgba(252, 211, 77, 0.4); }
.btn-header { background: rgba(255,255,255,0.15); color: white; border: 1px solid rgba(255,255,255,0.25); padding: 10px 16px; border-radius: var(--radius-sm); font-weight: 700; font-size: 0.75rem; transition: all 0.25s; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; backdrop-filter: blur(10px); cursor: pointer; }
.btn-header:hover { background: rgba(255,255,255,0.3); transform: translateY(-2px); }
.btn-header.export { background: linear-gradient(135deg, #10B981, #059669); border-color: rgba(255,255,255,0.3); }

.tabs-container { background: var(--bg-card); border-radius: var(--radius-lg); padding: 6px; margin-bottom: 20px; display: flex; gap: 6px; box-shadow: var(--shadow-sm); border: 1px solid var(--border-color); }
.tab-btn { flex: 1; padding: 14px 24px; border-radius: var(--radius-md); border: none; background: transparent; color: var(--text-secondary); font-weight: 800; font-size: 0.85rem; cursor: pointer; transition: all 0.3s ease; display: flex; align-items: center; justify-content: center; gap: 10px; text-decoration: none; }
.tab-btn:hover { background: var(--bg-body); color: var(--primary); }
.tab-btn.active { background: linear-gradient(135deg, var(--primary), var(--primary-dark)); color: white; box-shadow: 0 6px 16px rgba(11, 94, 215, 0.35); }
.tab-btn.active i { color: #93C5FD; }

.filter-card { background: var(--bg-card); border-radius: var(--radius-lg); padding: 18px 20px; border: 1px solid var(--border-color); margin-bottom: 20px; box-shadow: var(--shadow-sm); }
.filter-section-title { font-size: 0.68rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.08em; color: var(--text-secondary); margin-bottom: 10px; display: flex; align-items: center; gap: 6px; }
.quick-filters { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 16px; }
.quick-btn { padding: 8px 14px; border-radius: var(--radius-sm); border: 1.5px solid var(--border-color); background: var(--bg-card); color: var(--text-secondary); font-weight: 700; font-size: 0.72rem; cursor: pointer; transition: all 0.25s; display: inline-flex; align-items: center; gap: 6px; text-decoration: none; white-space: nowrap; }
.quick-btn:hover { border-color: var(--primary); color: var(--primary); transform: translateY(-2px); }
.quick-btn.active { background: linear-gradient(135deg, var(--primary), var(--primary-dark)); color: white; border-color: transparent; box-shadow: 0 6px 16px rgba(11, 94, 215, 0.35); }

.search-wrapper { position: relative; max-width: 700px; }
.search-input-group { position: relative; display: flex; align-items: center; }
.search-input-group i.search-icon { position: absolute; left: 16px; color: var(--text-secondary); font-size: 1rem; pointer-events: none; z-index: 2; }
.search-input-group input { width: 100%; padding: 14px 130px 14px 46px; border-radius: 14px; border: 2px solid var(--border-color); background: var(--bg-card); color: var(--text-primary); font-size: 0.92rem; font-weight: 600; transition: all 0.25s; }
.search-input-group input:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 4px rgba(11, 94, 215, 0.15); }
.search-input-group .search-btn { position: absolute; right: 6px; padding: 9px 18px; border-radius: 10px; background: linear-gradient(135deg, var(--primary), var(--primary-dark)); color: white; border: none; font-weight: 800; font-size: 0.78rem; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; }

.autocomplete-box { position: absolute; top: 100%; left: 0; right: 0; background: var(--bg-card); border: 2px solid var(--primary); border-radius: 14px; margin-top: 6px; max-height: 400px; overflow-y: auto; z-index: 999; box-shadow: 0 10px 30px rgba(0,0,0,0.15); display: none; }
.autocomplete-box.active { display: block; }
.autocomplete-item { padding: 12px 16px; border-bottom: 1px solid var(--border-color); cursor: pointer; transition: all 0.2s; display: flex; align-items: center; justify-content: space-between; gap: 12px; }
.autocomplete-item:hover { background: var(--primary-bg); }
.autocomplete-item .item-main { display: flex; align-items: center; gap: 10px; flex: 1; }
.autocomplete-item .item-icon { width: 34px; height: 34px; border-radius: 9px; background: linear-gradient(135deg, var(--primary), var(--primary-light)); color: white; display: flex; align-items: center; justify-content: center; font-size: 0.85rem; flex-shrink: 0; }
.autocomplete-item .item-name { font-weight: 800; font-size: 0.88rem; }
.autocomplete-item .item-meta { font-size: 0.68rem; color: var(--text-secondary); font-weight: 600; }
.autocomplete-item .item-stock { background: var(--success-bg); color: var(--success); padding: 4px 10px; border-radius: 8px; font-family: var(--font-mono); font-weight: 800; font-size: 0.75rem; }
.autocomplete-item .item-stock.low { background: var(--danger-bg); color: var(--danger); }
.autocomplete-empty { padding: 20px; text-align: center; color: var(--text-secondary); font-size: 0.85rem; }

.period-overview-card { background: linear-gradient(135deg, #EFF6FF, #DBEAFE); border: 2px solid #93C5FD; border-radius: var(--radius-lg); padding: 20px 24px; margin-bottom: 20px; box-shadow: 0 4px 20px rgba(11, 94, 215, 0.12); position: relative; overflow: hidden; }
[data-theme="dark"] .period-overview-card { background: linear-gradient(135deg, #12294A, #1E3A5F); border-color: #1E40AF; }
.period-overview-card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px; background: linear-gradient(90deg, #0B5ED7, #3B82F6, #7C3AED, #3B82F6, #0B5ED7); background-size: 200% 100%; animation: shimmer 3s infinite linear; }
@keyframes shimmer { 0% { background-position: 200% 0; } 100% { background-position: -200% 0; } }
.po-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 18px; padding-bottom: 14px; border-bottom: 2px dashed rgba(11, 94, 215, 0.3); flex-wrap: wrap; gap: 10px; }
.po-title { display: flex; align-items: center; gap: 10px; font-size: 1.05rem; font-weight: 900; color: #1E40AF; text-transform: uppercase; letter-spacing: 0.03em; }
[data-theme="dark"] .po-title { color: #93C5FD; }
.po-title i { font-size: 1.2rem; }
.po-badge { display: inline-flex; align-items: center; gap: 6px; background: #0B5ED7; color: white; padding: 6px 14px; border-radius: var(--radius-full); font-size: 0.72rem; font-weight: 800; text-transform: uppercase; box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3); }
.po-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; margin-bottom: 16px; }
.po-item { background: var(--bg-card); border-radius: var(--radius-md); padding: 14px 16px; border: 2px solid var(--border-color); display: flex; align-items: center; gap: 12px; transition: all 0.25s; position: relative; overflow: hidden; }
.po-item::before { content: ''; position: absolute; left: 0; top: 0; bottom: 0; width: 4px; }
.po-item:hover { transform: translateY(-3px); box-shadow: 0 8px 20px rgba(0,0,0,0.1); }
.po-item.po-start::before { background: linear-gradient(180deg, #64748B, #94A3B8); }
.po-item.po-start .po-icon { background: linear-gradient(135deg, #64748B, #94A3B8); }
.po-item.po-start .po-value { color: #64748B; }
.po-item.po-added::before { background: linear-gradient(180deg, #059669, #34D399); }
.po-item.po-added .po-icon { background: linear-gradient(135deg, #059669, #34D399); }
.po-item.po-added .po-value { color: #059669; }
.po-item.po-movements::before { background: linear-gradient(180deg, #DC2626, #F87171); }
.po-item.po-movements .po-icon { background: linear-gradient(135deg, #DC2626, #F87171); }
.po-item.po-movements .po-value { color: #DC2626; }
.po-item.po-current::before { background: linear-gradient(180deg, #0B5ED7, #3B82F6); }
.po-item.po-current .po-icon { background: linear-gradient(135deg, #0B5ED7, #3B82F6); }
.po-item.po-current .po-value { color: #0B5ED7; }
.po-icon { width: 44px; height: 44px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; color: white; flex-shrink: 0; }
.po-content { flex: 1; min-width: 0; }
.po-label { font-size: 0.62rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.06em; color: var(--text-secondary); margin-bottom: 4px; }
.po-value { font-family: var(--font-mono); font-size: 1.3rem; font-weight: 900; line-height: 1.1; display: flex; align-items: baseline; gap: 4px; }
.po-value span { font-size: 0.7rem; font-weight: 700; color: var(--text-secondary); font-family: var(--font-primary); }
.po-sub { font-size: 0.65rem; font-weight: 600; color: var(--text-muted); margin-top: 4px; }
.po-users { background: var(--bg-card); border-radius: var(--radius-md); padding: 12px 16px; border: 1.5px dashed #93C5FD; }
.po-users-label { font-size: 0.68rem; font-weight: 800; color: #1E40AF; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 8px; display: flex; align-items: center; gap: 6px; }
[data-theme="dark"] .po-users-label { color: #93C5FD; }
.po-users-list { display: flex; flex-wrap: wrap; gap: 6px; }
.po-user-tag { display: inline-flex; align-items: center; gap: 5px; background: linear-gradient(135deg, #0B5ED7, #3B82F6); color: white; padding: 5px 12px; border-radius: var(--radius-full); font-size: 0.7rem; font-weight: 700; }

.last-stock-card { background: var(--bg-card); border-radius: var(--radius-lg); padding: 20px 24px; margin-bottom: 20px; border: 2px solid var(--border-color); box-shadow: 0 4px 20px rgba(0,0,0,0.08); position: relative; overflow: hidden; }
.last-stock-card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px; }
.last-stock-card.accurate { border-color: #34D399; background: linear-gradient(135deg, #ECFDF5, var(--bg-card) 60%); }
.last-stock-card.accurate::before { background: linear-gradient(90deg, #059669, #34D399, #059669); }
.last-stock-card.warning { border-color: #FBBF24; background: linear-gradient(135deg, #FFFBEB, var(--bg-card) 60%); }
.last-stock-card.warning::before { background: linear-gradient(90deg, #D97706, #FBBF24, #D97706); }
.last-stock-card.no-purchase { border-color: var(--border-color); }
.ls-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 18px; padding-bottom: 14px; border-bottom: 2px dashed var(--border-color); flex-wrap: wrap; gap: 10px; }
.ls-title { display: flex; align-items: center; gap: 10px; font-size: 1.05rem; font-weight: 900; text-transform: uppercase; letter-spacing: 0.03em; }
.ls-title i { color: var(--primary); font-size: 1.2rem; }
.last-stock-card.accurate .ls-title i { color: #059669; }
.last-stock-card.warning .ls-title i { color: #D97706; }
.ls-status { display: inline-flex; align-items: center; gap: 6px; padding: 6px 14px; border-radius: var(--radius-full); font-size: 0.72rem; font-weight: 800; text-transform: uppercase; border: 2px solid; }
.ls-status.accurate { background: #D1FAE5; color: #059669; border-color: #34D399; }
.ls-status.warning { background: #FEF3C7; color: #B45309; border-color: #FBBF24; animation: blinkStatus 1.5s infinite; }
@keyframes blinkStatus { 0%, 100% { opacity: 1; } 50% { opacity: 0.6; } }
.ls-timeline { display: flex; align-items: stretch; gap: 8px; flex-wrap: wrap; margin-bottom: 20px; padding: 16px; background: var(--bg-body); border-radius: var(--radius-md); overflow-x: auto; }
.ls-tl-item { flex: 1; min-width: 140px; background: var(--bg-card); border: 2px solid var(--border-color); border-radius: 12px; padding: 12px 14px; display: flex; align-items: center; gap: 10px; transition: all 0.25s; }
.ls-tl-item:hover { transform: translateY(-3px); box-shadow: 0 8px 20px rgba(0,0,0,0.1); }
.ls-tl-item.purchase { border-color: #34D399; background: linear-gradient(135deg, #ECFDF5, #D1FAE5); }
.ls-tl-item.previous { border-color: #93C5FD; background: linear-gradient(135deg, #EFF6FF, #DBEAFE); }
.ls-tl-item.total { border-color: #A78BFA; background: linear-gradient(135deg, #F5F3FF, #EDE9FE); }
.ls-tl-item.used { border-color: #FCA5A5; background: linear-gradient(135deg, #FEF2F2, #FEE2E2); }
.ls-tl-item.expected { border-color: #FBBF24; background: linear-gradient(135deg, #FFFBEB, #FEF3C7); }
.ls-tl-icon { width: 36px; height: 36px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 0.95rem; color: white; flex-shrink: 0; }
.ls-tl-item.purchase .ls-tl-icon { background: linear-gradient(135deg, #059669, #34D399); }
.ls-tl-item.previous .ls-tl-icon { background: linear-gradient(135deg, #0B5ED7, #3B82F6); }
.ls-tl-item.total .ls-tl-icon { background: linear-gradient(135deg, #7C3AED, #A78BFA); }
.ls-tl-item.used .ls-tl-icon { background: linear-gradient(135deg, #DC2626, #F87171); }
.ls-tl-item.expected .ls-tl-icon { background: linear-gradient(135deg, #D97706, #FBBF24); }
.ls-tl-content { flex: 1; min-width: 0; }
.ls-tl-label { font-size: 0.6rem; font-weight: 800; text-transform: uppercase; color: var(--text-secondary); letter-spacing: 0.05em; margin-bottom: 3px; }
.ls-tl-value { font-family: var(--font-mono); font-size: 1.1rem; font-weight: 900; line-height: 1.1; }
.ls-tl-item.purchase .ls-tl-value { color: #059669; }
.ls-tl-item.previous .ls-tl-value { color: #0B5ED7; }
.ls-tl-item.total .ls-tl-value { color: #7C3AED; }
.ls-tl-item.used .ls-tl-value { color: #DC2626; }
.ls-tl-item.expected .ls-tl-value { color: #D97706; }
.ls-tl-meta { font-size: 0.62rem; color: var(--text-secondary); font-weight: 600; margin-top: 3px; display: flex; align-items: center; gap: 4px; flex-wrap: wrap; }
.ls-tl-arrow { display: flex; align-items: center; justify-content: center; font-size: 1.2rem; color: var(--text-secondary); flex-shrink: 0; min-width: 24px; }
.ls-tl-arrow.minus i { color: #DC2626; }
.ls-tl-arrow.equal i { color: #0B5ED7; }
.ls-verification { display: grid; grid-template-columns: 1fr auto 1fr auto 1fr; gap: 12px; align-items: center; padding: 16px; background: var(--bg-body); border-radius: var(--radius-md); border: 2px dashed var(--border-color); }
.ls-verification.accurate { background: linear-gradient(135deg, #ECFDF5, #D1FAE5); border-color: #34D399; }
.ls-verification.warning { background: linear-gradient(135deg, #FFFBEB, #FEF3C7); border-color: #FBBF24; }
.ls-v-item { background: var(--bg-card); border-radius: 10px; padding: 12px 14px; border: 2px solid var(--border-color); text-align: center; }
.ls-v-item.success { border-color: #34D399; background: linear-gradient(135deg, #ECFDF5, #D1FAE5); }
.ls-v-item.danger { border-color: #F87171; background: linear-gradient(135deg, #FEF2F2, #FEE2E2); }
.ls-v-label { font-size: 0.6rem; font-weight: 800; text-transform: uppercase; color: var(--text-secondary); letter-spacing: 0.05em; margin-bottom: 4px; display: flex; align-items: center; justify-content: center; gap: 4px; }
.ls-v-value { font-family: var(--font-mono); font-size: 1.15rem; font-weight: 900; }
.ls-v-vs { font-size: 1.3rem; color: var(--text-secondary); text-align: center; }
.ls-note { margin-top: 14px; padding: 14px 18px; background: linear-gradient(135deg, #FEF3C7, #FDE68A); border-left: 4px solid #D97706; border-radius: var(--radius-md); font-size: 0.78rem; color: #78350F; line-height: 1.6; }

.remaining-stock-card { background: var(--bg-card); border-radius: var(--radius-lg); padding: 20px 24px; margin-bottom: 20px; border: 2px solid var(--border-color); box-shadow: 0 4px 20px rgba(0,0,0,0.08); position: relative; overflow: hidden; }
.remaining-stock-card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px; }
.remaining-stock-card.success { border-color: #34D399; background: linear-gradient(135deg, #ECFDF5, var(--bg-card) 70%); }
.remaining-stock-card.success::before { background: linear-gradient(90deg, #059669, #34D399, #059669); }
.remaining-stock-card.warning { border-color: #FBBF24; background: linear-gradient(135deg, #FFFBEB, var(--bg-card) 70%); }
.remaining-stock-card.warning::before { background: linear-gradient(90deg, #D97706, #FBBF24, #D97706); }
.remaining-stock-card.danger { border-color: #F87171; background: linear-gradient(135deg, #FEF2F2, var(--bg-card) 70%); animation: pulseDanger 2s infinite; }
.remaining-stock-card.danger::before { background: linear-gradient(90deg, #DC2626, #F87171, #DC2626); }
@keyframes pulseDanger { 0%, 100% { box-shadow: 0 4px 20px rgba(220, 38, 38, 0.15); } 50% { box-shadow: 0 4px 30px rgba(220, 38, 38, 0.35); } }
.rs-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 18px; padding-bottom: 14px; border-bottom: 2px dashed var(--border-color); flex-wrap: wrap; gap: 10px; }
.rs-title { display: flex; align-items: center; gap: 10px; font-size: 1.05rem; font-weight: 900; text-transform: uppercase; }
.rs-title i { color: var(--primary); font-size: 1.2rem; }
.remaining-stock-card.success .rs-title i { color: #059669; }
.remaining-stock-card.warning .rs-title i { color: #D97706; }
.remaining-stock-card.danger .rs-title i { color: #DC2626; }
.rs-status-badge { display: inline-flex; align-items: center; gap: 6px; padding: 6px 14px; border-radius: var(--radius-full); font-size: 0.72rem; font-weight: 800; text-transform: uppercase; border: 2px solid; }
.rs-status-badge.success { background: #D1FAE5; color: #059669; border-color: #34D399; }
.rs-status-badge.warning { background: #FEF3C7; color: #B45309; border-color: #FBBF24; }
.rs-status-badge.danger { background: #FEE2E2; color: #DC2626; border-color: #F87171; animation: blinkStatus 1.5s infinite; }
.rs-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: 12px; margin-bottom: 16px; }
.rs-item { background: var(--bg-card); border-radius: var(--radius-md); padding: 14px 16px; border: 2px solid var(--border-color); display: flex; align-items: center; gap: 12px; transition: all 0.25s; position: relative; overflow: hidden; }
.rs-item::before { content: ''; position: absolute; left: 0; top: 0; bottom: 0; width: 4px; }
.rs-item:hover { transform: translateY(-3px); box-shadow: 0 8px 20px rgba(0,0,0,0.1); }
.rs-item.stock-balance::before { background: linear-gradient(180deg, #0B5ED7, #3B82F6); }
.rs-item.stock-balance .rs-item-icon { background: linear-gradient(135deg, #0B5ED7, #3B82F6); }
.rs-item.stock-balance .rs-item-value { color: #0B5ED7; }
.rs-item.stock-usage::before { background: linear-gradient(180deg, #7C3AED, #A78BFA); }
.rs-item.stock-usage .rs-item-icon { background: linear-gradient(135deg, #7C3AED, #A78BFA); }
.rs-item.stock-usage .rs-item-value { color: #7C3AED; }
.rs-item.stock-value::before { background: linear-gradient(180deg, #0D9488, #14B8A6); }
.rs-item.stock-value .rs-item-icon { background: linear-gradient(135deg, #0D9488, #14B8A6); }
.rs-item.stock-value .rs-item-value { color: #0D9488; }
.rs-item.stock-reorder::before { background: linear-gradient(180deg, #D97706, #FBBF24); }
.rs-item.stock-reorder .rs-item-icon { background: linear-gradient(135deg, #D97706, #FBBF24); }
.rs-item.stock-reorder .rs-item-value { color: #D97706; }
.rs-item.stock-stockout::before { background: linear-gradient(180deg, #DC2626, #F87171); }
.rs-item.stock-stockout .rs-item-icon { background: linear-gradient(135deg, #DC2626, #F87171); }
.rs-item.stock-stockout .rs-item-value { color: #DC2626; }
.rs-item-icon { width: 44px; height: 44px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; color: white; flex-shrink: 0; }
.rs-item-content { flex: 1; min-width: 0; }
.rs-item-label { font-size: 0.62rem; font-weight: 800; text-transform: uppercase; color: var(--text-secondary); margin-bottom: 4px; }
.rs-item-value { font-family: var(--font-mono); font-size: 1.3rem; font-weight: 900; line-height: 1.1; display: flex; align-items: baseline; gap: 4px; }
.rs-item-value .rs-unit { font-size: 0.7rem; font-weight: 700; color: var(--text-secondary); font-family: var(--font-primary); }
.rs-item-sub { font-size: 0.65rem; font-weight: 600; color: var(--text-muted); margin-top: 4px; }
.rs-health-bar { background: var(--bg-body); border-radius: 12px; padding: 12px 16px; border: 1.5px solid var(--border-color); }
.rs-health-label { display: flex; justify-content: space-between; align-items: center; font-size: 0.7rem; font-weight: 800; text-transform: uppercase; color: var(--text-secondary); margin-bottom: 8px; }
.rs-health-percent { font-family: var(--font-mono); font-weight: 900; font-size: 0.75rem; padding: 2px 10px; border-radius: 8px; background: var(--bg-card); }
.rs-bar-track { height: 10px; background: var(--border-color); border-radius: 10px; overflow: hidden; position: relative; }
.rs-bar-fill { height: 100%; border-radius: 10px; transition: width 0.8s ease; position: relative; overflow: hidden; }
.rs-bar-fill::after { content: ''; position: absolute; inset: 0; background: linear-gradient(90deg, transparent, rgba(255,255,255,0.4), transparent); animation: shimmerBar 2s infinite; }
@keyframes shimmerBar { 0% { transform: translateX(-100%); } 100% { transform: translateX(100%); } }
.rs-bar-fill.success { background: linear-gradient(90deg, #059669, #34D399); }
.rs-bar-fill.warning { background: linear-gradient(90deg, #D97706, #FBBF24); }
.rs-bar-fill.danger { background: linear-gradient(90deg, #DC2626, #F87171); }

.top5-section { background: linear-gradient(135deg, #FFFBEB, #FEF3C7); border: 2px solid #FCD34D; border-radius: var(--radius-lg); padding: 16px 20px; margin-top: 16px; box-shadow: 0 4px 16px rgba(217, 119, 6, 0.15); }
[data-theme="dark"] .top5-section { background: linear-gradient(135deg, #3A2A0F, #4A3A12); border-color: #78350F; }
.top5-section.equipment { background: linear-gradient(135deg, #EFF6FF, #DBEAFE); border-color: #93C5FD; }
[data-theme="dark"] .top5-section.equipment { background: linear-gradient(135deg, #12294A, #1E3A5F); border-color: #1E40AF; }
.top5-header { display: flex; align-items: center; gap: 10px; font-size: 0.85rem; font-weight: 900; color: #92400E; margin-bottom: 14px; text-transform: uppercase; letter-spacing: 0.04em; }
.top5-section.equipment .top5-header { color: #1E40AF; }
.top5-header i { font-size: 1.1rem; color: #D97706; animation: fireFlicker 1.5s infinite alternate; }
.top5-section.equipment .top5-header i { color: #0B5ED7; }
@keyframes fireFlicker { from { transform: scale(1) rotate(-3deg); } to { transform: scale(1.15) rotate(3deg); } }
.top5-badge { margin-left: auto; background: rgba(217, 119, 6, 0.15); color: #92400E; padding: 3px 10px; border-radius: 12px; font-size: 0.62rem; font-weight: 800; border: 1px solid rgba(217, 119, 6, 0.3); }
.top5-section.equipment .top5-badge { background: rgba(11, 94, 215, 0.15); color: #1E40AF; border-color: rgba(11, 94, 215, 0.3); }
.top5-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 10px; }
.top5-card { background: var(--bg-card); border: 2px solid #FCD34D; border-radius: 12px; padding: 12px 14px; display: flex; align-items: center; gap: 10px; text-decoration: none; color: var(--text-primary); transition: all 0.25s; position: relative; overflow: hidden; }
.top5-card:hover { transform: translateY(-3px); box-shadow: 0 8px 20px rgba(217, 119, 6, 0.25); border-color: #D97706; }
.top5-card.equipment:hover { box-shadow: 0 8px 20px rgba(11, 94, 215, 0.25); border-color: #0B5ED7; }
.top5-card.equipment { border-color: #93C5FD; }
.top5-rank { position: absolute; top: 4px; right: 8px; font-size: 0.6rem; font-weight: 900; color: #D97706; opacity: 0.5; font-family: var(--font-mono); }
.top5-icon { width: 38px; height: 38px; border-radius: 10px; background: linear-gradient(135deg, #D97706, #F59E0B); color: white; display: flex; align-items: center; justify-content: center; font-size: 0.95rem; flex-shrink: 0; }
.top5-card.equipment .top5-icon { background: linear-gradient(135deg, #0B5ED7, #3B82F6); }
.top5-info { flex: 1; min-width: 0; }
.top5-name { font-weight: 800; font-size: 0.82rem; margin-bottom: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.top5-meta { font-size: 0.65rem; color: var(--text-secondary); font-weight: 700; display: flex; align-items: center; gap: 4px; }

.details-container { animation: fadeInUp 0.5s ease; }
@keyframes fadeInUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }

.summary-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: 14px; margin-bottom: 20px; }
.summary-card { background: var(--bg-card); border-radius: var(--radius-lg); padding: 18px 20px; border: 2px solid var(--border-color); position: relative; overflow: hidden; transition: all 0.3s; }
.summary-card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px; }
.summary-card:hover { transform: translateY(-4px); box-shadow: var(--shadow-lg); }
.summary-card .sc-icon { width: 40px; height: 40px; border-radius: 11px; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; color: white; margin-bottom: 10px; }
.summary-card .sc-label { font-size: 0.68rem; font-weight: 800; color: var(--text-secondary); text-transform: uppercase; margin-bottom: 4px; }
.summary-card .sc-value { font-size: 1.5rem; font-weight: 900; font-family: var(--font-mono); line-height: 1.1; }
.summary-card .sc-sub { font-size: 0.68rem; color: var(--text-secondary); font-weight: 600; margin-top: 4px; }
.summary-card.purchase::before { background: linear-gradient(90deg, #0B5ED7, #3B82F6); }
.summary-card.purchase .sc-icon { background: linear-gradient(135deg, #0B5ED7, #3B82F6); }
.summary-card.purchase .sc-value { color: #0B5ED7; }
.summary-card.pending::before { background: linear-gradient(90deg, #D97706, #FBBF24); }
.summary-card.pending .sc-icon { background: linear-gradient(135deg, #D97706, #FBBF24); }
.summary-card.pending .sc-value { color: #D97706; }
.summary-card.confirmed::before { background: linear-gradient(90deg, #0891B2, #22D3EE); }
.summary-card.confirmed .sc-icon { background: linear-gradient(135deg, #0891B2, #22D3EE); }
.summary-card.confirmed .sc-value { color: #0891B2; }
.summary-card.dispensed::before { background: linear-gradient(90deg, #059669, #34D399); }
.summary-card.dispensed .sc-icon { background: linear-gradient(135deg, #059669, #34D399); }
.summary-card.dispensed .sc-value { color: #059669; }
.summary-card.otc::before { background: linear-gradient(90deg, #7C3AED, #A78BFA); }
.summary-card.otc .sc-icon { background: linear-gradient(135deg, #7C3AED, #A78BFA); }
.summary-card.otc .sc-value { color: #7C3AED; }
.summary-card.movement::before { background: linear-gradient(90deg, #DC2626, #F87171); }
.summary-card.movement .sc-icon { background: linear-gradient(135deg, #DC2626, #F87171); }
.summary-card.movement .sc-value { color: #DC2626; }

.table-card { background: var(--bg-card); border-radius: var(--radius-lg); border: 1px solid var(--border-color); overflow: hidden; box-shadow: var(--shadow-sm); margin-bottom: 20px; }
.table-card .table-header { padding: 14px 20px; background: linear-gradient(135deg, #0B5ED7, #0A4CA8); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; }
.table-card .table-header.green { background: linear-gradient(135deg, #059669, #047857); }
.table-card .table-header.cyan { background: linear-gradient(135deg, #0891B2, #0E7490); }
.table-card .table-header.purple { background: linear-gradient(135deg, #7C3AED, #6D28D9); }
.table-card .table-header .title { color: white; font-size: 0.88rem; font-weight: 800; display: flex; align-items: center; gap: 10px; }
.table-card .table-header .count { color: rgba(255,255,255,0.95); font-size: 0.7rem; font-weight: 700; background: rgba(255,255,255,0.18); padding: 5px 12px; border-radius: var(--radius-full); }
.table-scroll-wrapper { overflow-x: auto; }
.data-table { width: 100%; border-collapse: collapse; font-size: 0.78rem; }
.data-table thead th { text-align: left; padding: 11px 14px; font-weight: 800; font-size: 0.62rem; text-transform: uppercase; color: white; background: linear-gradient(135deg, #0B5ED7, #0A4CA8); white-space: nowrap; }
.data-table thead.green th { background: linear-gradient(135deg, #059669, #047857); }
.data-table thead.cyan th { background: linear-gradient(135deg, #0891B2, #0E7490); }
.data-table thead.purple th { background: linear-gradient(135deg, #7C3AED, #6D28D9); }
.data-table tbody td { padding: 11px 14px; border-bottom: 1px solid var(--border-color); vertical-align: middle; }
.data-table tbody tr:hover td { background: var(--primary-bg); }
.money-cell { font-family: var(--font-mono); font-weight: 800; color: var(--success); text-align: right; white-space: nowrap; }
.status-badge { display: inline-flex; align-items: center; gap: 4px; padding: 4px 10px; border-radius: var(--radius-full); font-size: 0.62rem; font-weight: 800; text-transform: uppercase; }
.status-badge.pending { background: var(--warning-bg); color: var(--warning); }
.status-badge.confirmed { background: var(--cyan-bg); color: var(--cyan); }
.status-badge.dispensed, .status-badge.paid, .status-badge.completed { background: var(--success-bg); color: var(--success); }
.status-badge.in_progress { background: var(--cyan-bg); color: var(--cyan); }
.status-badge.partial { background: var(--warning-bg); color: var(--warning); }
.status-badge.expired { background: var(--danger-bg); color: var(--danger); }
.status-badge.expiring { background: var(--warning-bg); color: var(--warning); }
.status-badge.ok { background: var(--success-bg); color: var(--success); }

.empty-state { padding: 60px 20px; text-align: center; color: var(--text-secondary); }
.empty-state i { font-size: 3rem; opacity: 0.3; display: block; margin-bottom: 14px; color: var(--primary); }

.info-banner { background: linear-gradient(135deg, var(--primary-bg), transparent); border-left: 4px solid var(--primary); border-radius: var(--radius-md); padding: 16px 20px; margin-bottom: 20px; display: flex; align-items: center; gap: 14px; }
.info-banner .ib-icon { width: 52px; height: 52px; border-radius: 14px; background: linear-gradient(135deg, var(--primary), var(--primary-dark)); color: white; display: flex; align-items: center; justify-content: center; font-size: 1.4rem; flex-shrink: 0; }
.info-banner .ib-content { flex: 1; }
.info-banner .ib-title { font-size: 1.1rem; font-weight: 900; margin-bottom: 4px; }
.info-banner .ib-meta { font-size: 0.78rem; color: var(--text-secondary); display: flex; gap: 16px; flex-wrap: wrap; }
.info-banner .ib-meta span { display: inline-flex; align-items: center; gap: 5px; }

@media (max-width: 1024px) {
    .ls-timeline { flex-direction: column; }
    .ls-tl-arrow { transform: rotate(90deg); }
    .ls-verification { grid-template-columns: 1fr; }
    .ls-v-vs { transform: rotate(90deg); }
}
@media (max-width: 768px) {
    .summary-grid, .po-grid, .rs-grid { grid-template-columns: 1fr 1fr; }
    .page-header .page-title { font-size: 1.2rem; }
    .tabs-container { flex-direction: column; }
    .top5-grid { grid-template-columns: 1fr; }
}
@media (max-width: 480px) {
    .summary-grid, .po-grid, .rs-grid { grid-template-columns: 1fr; }
    .rs-header, .ls-header, .po-header { flex-direction: column; align-items: stretch; }
}
@media print {
    .btn-header, .quick-btn, .tabs-container, .filter-card { display: none !important; }
    .page-header { background: white !important; color: black !important; }
}
</style>
</head>
<body>

<main class="main-content">

    <!-- PAGE HEADER -->
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-boxes-stacked"></i>
                Stock Movement Report
                <span class="branch-tag"><i class="fas fa-shield-alt"></i> AUDIT</span>
                <span class="branch-tag locked"><i class="fas fa-lock"></i> <?= htmlspecialchars($branch_name_display) ?></span>
                <span class="branch-tag"><i class="fas fa-calendar"></i> <?= htmlspecialchars($date_label) ?></span>
            </h1>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;position:relative;z-index:1;">
            <?php if ($selected_item_id > 0): ?>
            <a href="?tab=<?= $active_tab ?>&quick=<?= $quick_filter ?>&item_id=<?= $selected_item_id ?>&export=csv" class="btn-header export">
                <i class="fas fa-file-csv"></i> Export CSV
            </a>
            <?php endif; ?>
            <button onclick="window.print()" class="btn-header"><i class="fas fa-print"></i> Print</button>
            <a href="dashboard.php" class="btn-header">
                <i class="fas fa-arrow-left"></i> Dashboard
            </a>
        </div>
    </div>

    <!-- TABS -->
    <div class="tabs-container">
        <a href="?tab=medicine&quick=<?= $quick_filter ?>" class="tab-btn <?= $active_tab === 'medicine' ? 'active' : '' ?>">
            <i class="fas fa-pills"></i> MEDICINE TRACKING
        </a>
        <a href="?tab=equipment&quick=<?= $quick_filter ?>" class="tab-btn <?= $active_tab === 'equipment' ? 'active' : '' ?>">
            <i class="fas fa-tools"></i> EQUIPMENT TRACKING
        </a>
    </div>

    <!-- FILTER CARD -->
    <div class="filter-card">
        <div class="filter-section-title"><i class="fas fa-bolt"></i> Quick Filters</div>
        <div class="quick-filters">
            <a href="?tab=<?= $active_tab ?>&quick=today<?= $selected_item_id ? '&item_id=' . $selected_item_id : '' ?>" class="quick-btn <?= $quick_filter === 'today' ? 'active' : '' ?>"><i class="fas fa-calendar-day"></i> Today</a>
            <a href="?tab=<?= $active_tab ?>&quick=1w<?= $selected_item_id ? '&item_id=' . $selected_item_id : '' ?>" class="quick-btn <?= $quick_filter === '1w' ? 'active' : '' ?>"><i class="fas fa-calendar-week"></i> 1W</a>
            <a href="?tab=<?= $active_tab ?>&quick=1m<?= $selected_item_id ? '&item_id=' . $selected_item_id : '' ?>" class="quick-btn <?= $quick_filter === '1m' ? 'active' : '' ?>"><i class="fas fa-calendar-alt"></i> 1M</a>
            <a href="?tab=<?= $active_tab ?>&quick=3m<?= $selected_item_id ? '&item_id=' . $selected_item_id : '' ?>" class="quick-btn <?= $quick_filter === '3m' ? 'active' : '' ?>"><i class="fas fa-calendar-alt"></i> 3M</a>
            <a href="?tab=<?= $active_tab ?>&quick=6m<?= $selected_item_id ? '&item_id=' . $selected_item_id : '' ?>" class="quick-btn <?= $quick_filter === '6m' ? 'active' : '' ?>"><i class="fas fa-calendar-alt"></i> 6M</a>
            <a href="?tab=<?= $active_tab ?>&quick=1y<?= $selected_item_id ? '&item_id=' . $selected_item_id : '' ?>" class="quick-btn <?= $quick_filter === '1y' ? 'active' : '' ?>"><i class="fas fa-calendar"></i> 1Y</a>
            <a href="?tab=<?= $active_tab ?>&quick=all<?= $selected_item_id ? '&item_id=' . $selected_item_id : '' ?>" class="quick-btn <?= $quick_filter === 'all' ? 'active' : '' ?>"><i class="fas fa-infinity"></i> All</a>
            <a href="#" onclick="toggleCustomDate(); return false;" class="quick-btn <?= $quick_filter === 'custom' ? 'active' : '' ?>"><i class="fas fa-calendar-check"></i> Custom</a>
        </div>

        <div id="customDateSection" style="display: <?= $quick_filter === 'custom' ? 'block' : 'none' ?>; margin-bottom: 16px; padding: 14px; background: var(--bg-body); border-radius: 12px;">
            <form method="GET" style="display: flex; gap: 12px; flex-wrap: wrap; align-items: flex-end;">
                <input type="hidden" name="tab" value="<?= htmlspecialchars($active_tab) ?>">
                <input type="hidden" name="quick" value="custom">
                <input type="hidden" name="item_id" value="<?= $selected_item_id ?>">
                <div>
                    <label style="font-size: 0.65rem; font-weight: 800; color: var(--text-secondary); display: block; margin-bottom: 4px;">FROM</label>
                    <input type="date" name="date_from" value="<?= htmlspecialchars($date_from) ?>" style="padding: 9px 12px; border-radius: 8px; border: 1.5px solid var(--border-color); background: var(--bg-card); color: var(--text-primary); font-size: 0.82rem;">
                </div>
                <div>
                    <label style="font-size: 0.65rem; font-weight: 800; color: var(--text-secondary); display: block; margin-bottom: 4px;">TO</label>
                    <input type="date" name="date_to" value="<?= htmlspecialchars($date_to) ?>" style="padding: 9px 12px; border-radius: 8px; border: 1.5px solid var(--border-color); background: var(--bg-card); color: var(--text-primary); font-size: 0.82rem;">
                </div>
                <button type="submit" class="quick-btn active"><i class="fas fa-filter"></i> Apply</button>
            </form>
        </div>

        <div class="filter-section-title" style="margin-top: 10px;">
            <i class="fas fa-search"></i> Search <?= $active_tab === 'medicine' ? 'Medicine' : 'Equipment' ?>
        </div>
        <div class="search-wrapper">
            <form method="GET" id="searchForm">
                <input type="hidden" name="tab" value="<?= htmlspecialchars($active_tab) ?>">
                <input type="hidden" name="quick" value="<?= htmlspecialchars($quick_filter) ?>">
                <input type="hidden" name="item_id" id="itemIdInput" value="<?= $selected_item_id ?>">
                <div class="search-input-group">
                    <i class="fas fa-search search-icon"></i>
                    <input type="text" id="searchInput" name="search" 
                           value="<?= htmlspecialchars($search) ?>" 
                           placeholder="<?= $active_tab === 'medicine' ? 'Search medicine... (e.g. ALBENDAZOLE, Paracetamol)' : 'Search equipment... (e.g. ECG Machine, Blood Pressure Monitor)' ?>"
                           autocomplete="off">
                    <button type="submit" class="search-btn"><i class="fas fa-arrow-right"></i> Search</button>
                </div>
                <div class="autocomplete-box" id="autocompleteBox"></div>
            </form>
        </div>
        
        <?php if ($selected_item_id > 0): ?>
            <div style="margin-top: 12px;">
                <a href="?tab=<?= $active_tab ?>&quick=<?= $quick_filter ?>" class="quick-btn" style="background: var(--danger-bg); color: var(--danger); border-color: var(--danger);">
                    <i class="fas fa-times"></i> Clear Selection
                </a>
            </div>
        <?php endif; ?>
    </div>

    <!-- TOP 5 -->
    <?php if ($active_tab === 'medicine' && count($top5_medicines) > 0 && $selected_item_id === 0): ?>
    <div class="top5-section">
        <div class="top5-header">
            <i class="fas fa-fire"></i> Top 5 Most Used Medicines
            <span class="top5-badge">
                <?= htmlspecialchars($date_label) ?> • <?= htmlspecialchars($branch_name_display) ?>
            </span>
        </div>
        <div class="top5-grid">
            <?php foreach ($top5_medicines as $idx => $tm): ?>
                <a href="?tab=medicine&quick=<?= $quick_filter ?>&item_id=<?= (int)$tm['item_id'] ?>" class="top5-card">
                    <div class="top5-rank">#<?= $idx + 1 ?></div>
                    <div class="top5-icon"><i class="fas fa-pills"></i></div>
                    <div class="top5-info">
                        <div class="top5-name"><?= htmlspecialchars($tm['name']) ?></div>
                        <div class="top5-meta"><i class="fas fa-chart-line"></i> <?= number_format($tm['total_qty']) ?> units used</div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($active_tab === 'equipment' && count($top5_equipment) > 0 && $selected_item_id === 0): ?>
    <div class="top5-section equipment">
        <div class="top5-header">
            <i class="fas fa-fire"></i> Top 5 Most Used Equipment
            <span class="top5-badge">
                <?= htmlspecialchars($date_label) ?> • <?= htmlspecialchars($branch_name_display) ?>
            </span>
        </div>
        <div class="top5-grid">
            <?php foreach ($top5_equipment as $idx => $te): ?>
                <a href="?tab=equipment&quick=<?= $quick_filter ?>&item_id=<?= (int)$te['item_id'] ?>" class="top5-card equipment">
                    <div class="top5-rank">#<?= $idx + 1 ?></div>
                    <div class="top5-icon"><i class="fas fa-tools"></i></div>
                    <div class="top5-info">
                        <div class="top5-name"><?= htmlspecialchars($te['name']) ?></div>
                        <div class="top5-meta"><i class="fas fa-chart-line"></i> <?= number_format($te['total_qty']) ?> uses</div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- MEDICINE TAB -->
    <?php if ($active_tab === 'medicine'): ?>
        
        <?php if ($medicine_details): ?>
            <?php 
            $med = $medicine_details['info'];
            $sm = $medicine_details['summary'];
            $rs = $medicine_details['remaining_stock'];
            $lsi = $medicine_details['last_stock_info'];
            $pi = $medicine_details['period_info'];
            $dus = $medicine_details['days_until_stockout'];
            ?>
            
            <div class="details-container">
                
                <div class="info-banner">
                    <div class="ib-icon"><i class="fas fa-pills"></i></div>
                    <div class="ib-content">
                        <div class="ib-title"><?= htmlspecialchars($medicine_details['name']) ?></div>
                        <div class="ib-meta">
                            <span><i class="fas fa-tag"></i> <?= htmlspecialchars($med['category'] ?? 'N/A') ?></span>
                            <span><i class="fas fa-flask"></i> Unit: <?= htmlspecialchars($med['unit'] ?? 'N/A') ?></span>
                            <span><i class="fas fa-money-bill"></i> Selling: <?= $currency ?> <?= number_format($med['selling_price'] ?? 0, 0) ?></span>
                            <span style="color:var(--warning);font-weight:800;"><i class="fas fa-lock"></i> <?= htmlspecialchars($branch_name_display) ?></span>
                        </div>
                    </div>
                </div>

                <!-- PERIOD OVERVIEW -->
                <div class="period-overview-card">
                    <div class="po-header">
                        <div class="po-title"><i class="fas fa-calendar-check"></i> Period Overview</div>
                        <div class="po-badge"><i class="fas fa-filter"></i> <?= htmlspecialchars($pi['filter_label']) ?> • <?= htmlspecialchars($branch_name_display) ?></div>
                    </div>
                    
                    <div class="po-grid">
                        <div class="po-item po-start">
                            <div class="po-icon"><i class="fas fa-warehouse"></i></div>
                            <div class="po-content">
                                <div class="po-label">Stock at Start</div>
                                <div class="po-value"><?= number_format($pi['stock_at_period_start']) ?> <span>units</span></div>
                                <div class="po-sub">When period began</div>
                            </div>
                        </div>
                        <div class="po-item po-added">
                            <div class="po-icon"><i class="fas fa-cart-plus"></i></div>
                            <div class="po-content">
                                <div class="po-label">Added in Period</div>
                                <div class="po-value">+<?= number_format($pi['purchases_total_qty']) ?> <span>units</span></div>
                                <div class="po-sub">
                                    <?= $pi['purchases_count'] ?> purchase<?= $pi['purchases_count'] != 1 ? 's' : '' ?>
                                    <?php if (count($pi['purchases_unique_users']) > 0): ?>
                                        • <?= count($pi['purchases_unique_users']) ?> user<?= count($pi['purchases_unique_users']) != 1 ? 's' : '' ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="po-item po-movements">
                            <div class="po-icon"><i class="fas fa-arrow-right-arrow-left"></i></div>
                            <div class="po-content">
                                <div class="po-label">Movements</div>
                                <div class="po-value">-<?= number_format($pi['movements_total']) ?> <span>units</span></div>
                                <div class="po-sub">Rx: <?= number_format($pi['movements_prescriptions']) ?> • OTC: <?= number_format($pi['movements_otc']) ?></div>
                            </div>
                        </div>
                        <div class="po-item po-current">
                            <div class="po-icon"><i class="fas fa-boxes-packing"></i></div>
                            <div class="po-content">
                                <div class="po-label">Current Stock</div>
                                <div class="po-value"><?= number_format($medicine_details['total_current_stock']) ?> <span>units</span></div>
                                <div class="po-sub">Now in inventory</div>
                            </div>
                        </div>
                    </div>
                    
                    <?php if (count($pi['purchases_unique_users']) > 0): ?>
                    <div class="po-users">
                        <div class="po-users-label"><i class="fas fa-users"></i> Users who added stock:</div>
                        <div class="po-users-list">
                            <?php foreach ($pi['purchases_unique_users'] as $user): ?>
                                <span class="po-user-tag"><i class="fas fa-user-circle"></i> <?= htmlspecialchars($user) ?></span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- LAST ADDED STOCK -->
                <?php if ($lsi['has_last_purchase']): ?>
                <div class="last-stock-card <?= $lsi['is_accurate'] ? 'accurate' : 'warning' ?>">
                    <div class="ls-header">
                        <div class="ls-title"><i class="fas fa-box-open"></i> Last Added Stock</div>
                        <div class="ls-status <?= $lsi['is_accurate'] ? 'accurate' : 'warning' ?>">
                            <?php if ($lsi['is_accurate']): ?>
                                <i class="fas fa-check-circle"></i> STOCK ACCURATE
                            <?php else: ?>
                                <i class="fas fa-exclamation-triangle"></i> VARIANCE: <?= $lsi['variance'] > 0 ? '+' : '' ?><?= number_format($lsi['variance']) ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="ls-timeline">
                        <div class="ls-tl-item purchase">
                            <div class="ls-tl-icon"><i class="fas fa-truck-loading"></i></div>
                            <div class="ls-tl-content">
                                <div class="ls-tl-label">Last Added</div>
                                <div class="ls-tl-value">+<?= number_format($lsi['last_added_qty']) ?></div>
                                <div class="ls-tl-meta"><i class="fas fa-user"></i> <?= htmlspecialchars($lsi['last_added_by']) ?></div>
                                <div class="ls-tl-meta"><i class="fas fa-calendar"></i> <?= date('d M Y', strtotime($lsi['last_added_date'])) ?></div>
                                <div class="ls-tl-meta" style="font-family:var(--font-mono);font-size:0.6rem;"><?= htmlspecialchars($lsi['last_added_invoice']) ?></div>
                            </div>
                        </div>
                        <div class="ls-tl-arrow"><i class="fas fa-plus"></i></div>
                        <div class="ls-tl-item previous">
                            <div class="ls-tl-icon"><i class="fas fa-history"></i></div>
                            <div class="ls-tl-content">
                                <div class="ls-tl-label">Previous Stock</div>
                                <div class="ls-tl-value"><?= number_format($lsi['previous_stock']) ?></div>
                                <div class="ls-tl-meta">Available before</div>
                                <div class="ls-tl-meta" style="font-size:0.6rem;color:var(--text-muted);">(<?= number_format($lsi['total_purchased_before_last']) ?> - <?= number_format($lsi['movements_before_last']) ?>)</div>
                            </div>
                        </div>
                        <div class="ls-tl-arrow equal"><i class="fas fa-equals"></i></div>
                        <div class="ls-tl-item total">
                            <div class="ls-tl-icon"><i class="fas fa-calculator"></i></div>
                            <div class="ls-tl-content">
                                <div class="ls-tl-label">Available Now</div>
                                <div class="ls-tl-value"><?= number_format($lsi['available_after_last_add']) ?></div>
                                <div class="ls-tl-meta">Prev + Last Added</div>
                            </div>
                        </div>
                        <div class="ls-tl-arrow minus"><i class="fas fa-minus"></i></div>
                        <div class="ls-tl-item used">
                            <div class="ls-tl-icon"><i class="fas fa-arrow-right-from-bracket"></i></div>
                            <div class="ls-tl-content">
                                <div class="ls-tl-label">Movements</div>
                                <div class="ls-tl-value">-<?= number_format($lsi['movements_after_last']) ?></div>
                                <div class="ls-tl-meta">Since last purchase</div>
                            </div>
                        </div>
                        <div class="ls-tl-arrow equal"><i class="fas fa-equals"></i></div>
                        <div class="ls-tl-item expected">
                            <div class="ls-tl-icon"><i class="fas fa-calculator"></i></div>
                            <div class="ls-tl-content">
                                <div class="ls-tl-label">Expected</div>
                                <div class="ls-tl-value"><?= number_format($lsi['expected_remaining']) ?></div>
                                <div class="ls-tl-meta">After movements</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="ls-verification <?= $lsi['is_accurate'] ? 'accurate' : 'warning' ?>">
                        <div class="ls-v-item">
                            <div class="ls-v-label"><i class="fas fa-calculator"></i> Expected</div>
                            <div class="ls-v-value"><?= number_format($lsi['expected_remaining']) ?></div>
                        </div>
                        <div class="ls-v-vs"><?= $lsi['is_accurate'] ? '<i class="fas fa-equals"></i>' : '<i class="fas fa-not-equal"></i>' ?></div>
                        <div class="ls-v-item">
                            <div class="ls-v-label"><i class="fas fa-warehouse"></i> Actual</div>
                            <div class="ls-v-value"><?= number_format($lsi['actual_remaining']) ?></div>
                        </div>
                        <div class="ls-v-vs"><i class="fas fa-equals"></i></div>
                        <div class="ls-v-item <?= $lsi['is_accurate'] ? 'success' : 'danger' ?>">
                            <div class="ls-v-label"><?= $lsi['is_accurate'] ? '<i class="fas fa-check-circle"></i> Match' : '<i class="fas fa-exclamation-triangle"></i> Variance' ?></div>
                            <div class="ls-v-value" style="color: <?= $lsi['variance'] >= 0 ? 'var(--success)' : 'var(--danger)' ?>;">
                                <?= $lsi['variance'] > 0 ? '+' : '' ?><?= number_format($lsi['variance']) ?>
                            </div>
                        </div>
                    </div>
                    
                    <?php if (!$lsi['is_accurate']): ?>
                    <div class="ls-note">
                        <i class="fas fa-info-circle"></i>
                        <strong>Kumbuka:</strong> Tofauti ya <strong><?= number_format(abs($lsi['variance'])) ?> units</strong> inaweza kuwa:
                        <ul style="margin: 8px 0 0 20px; font-size: 0.72rem;">
                            <li>Stock iliyoexpire au disposed</li>
                            <li>Stock returns kwa supplier</li>
                            <li>Data entry errors</li>
                            <li>Stock transfers between branches</li>
                        </ul>
                    </div>
                    <?php endif; ?>
                </div>
                <?php else: ?>
                <div class="last-stock-card no-purchase">
                    <div class="ls-header">
                        <div class="ls-title"><i class="fas fa-box-open"></i> Last Added Stock</div>
                        <div class="ls-status warning"><i class="fas fa-info-circle"></i> NO PURCHASE RECORD</div>
                    </div>
                    <div style="padding: 20px; text-align: center; color: var(--text-secondary);">
                        <i class="fas fa-truck" style="font-size: 2rem; opacity: 0.3; margin-bottom: 10px; display: block;"></i>
                        <p style="font-weight: 700;">Hakuna purchase records kwa kipindi hiki</p>
                    </div>
                </div>
                <?php endif; ?>

                <!-- REMAINING STOCK -->
                <div class="remaining-stock-card <?= $rs['status_color'] ?>">
                    <div class="rs-header">
                        <div class="rs-title"><i class="fas fa-boxes-packing"></i> Remaining Stock</div>
                        <div class="rs-status-badge <?= $rs['status_color'] ?>">
                            <i class="fas <?= $rs['status_icon'] ?>"></i> <?= $rs['status_label'] ?>
                        </div>
                    </div>
                    <div class="rs-grid">
                        <div class="rs-item stock-balance">
                            <div class="rs-item-icon"><i class="fas fa-warehouse"></i></div>
                            <div class="rs-item-content">
                                <div class="rs-item-label">Stock Balance</div>
                                <div class="rs-item-value"><?= number_format($rs['current_stock']) ?> <span class="rs-unit">units</span></div>
                                <div class="rs-item-sub">Available now</div>
                            </div>
                        </div>
                        <div class="rs-item stock-usage">
                            <div class="rs-item-icon"><i class="fas fa-arrow-right-from-bracket"></i></div>
                            <div class="rs-item-content">
                                <div class="rs-item-label">Total Used</div>
                                <div class="rs-item-value"><?= number_format($rs['total_used']) ?> <span class="rs-unit">units</span></div>
                                <div class="rs-item-sub">Rx + OTC in period</div>
                            </div>
                        </div>
                        <div class="rs-item stock-value">
                            <div class="rs-item-icon"><i class="fas fa-money-bill-trend-up"></i></div>
                            <div class="rs-item-content">
                                <div class="rs-item-label">Stock Value</div>
                                <div class="rs-item-value"><?= $currency ?> <?= number_format($rs['stock_value'], 0) ?></div>
                                <div class="rs-item-sub">@ <?= $currency ?> <?= number_format($rs['average_selling_price'], 0) ?> avg</div>
                            </div>
                        </div>
                        <div class="rs-item stock-reorder">
                            <div class="rs-item-icon"><i class="fas fa-triangle-exclamation"></i></div>
                            <div class="rs-item-content">
                                <div class="rs-item-label">Reorder Level</div>
                                <div class="rs-item-value"><?= number_format($rs['reorder_level']) ?> <span class="rs-unit">units</span></div>
                                <div class="rs-item-sub"><?= $rs['current_stock'] > $rs['reorder_level'] ? '✅ Above' : '⚠️ Below' ?></div>
                            </div>
                        </div>
                        <div class="rs-item stock-stockout">
                            <div class="rs-item-icon"><i class="fas fa-hourglass-end"></i></div>
                            <div class="rs-item-content">
                                <div class="rs-item-label">Days to Stockout</div>
                                <div class="rs-item-value"><?= $dus >= 999 ? '∞' : $dus ?> <span class="rs-unit">days</span></div>
                                <div class="rs-item-sub">
                                    <?php if ($dus <= 7 && $dus < 999): ?>
                                        🔴 Urgent!
                                    <?php elseif ($dus <= 14 && $dus < 999): ?>
                                        🟡 Soon
                                    <?php else: ?>
                                        ✅ Sufficient
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="rs-health-bar">
                        <div class="rs-health-label">
                            <span><i class="fas fa-heartbeat"></i> Stock Health</span>
                            <span class="rs-health-percent"><?= $rs['current_stock'] > 0 ? 'OK' : 'EMPTY' ?></span>
                        </div>
                        <div class="rs-bar-track">
                            <?php 
                            $bar_width = 0;
                            if ($rs['reorder_level'] > 0) {
                                $bar_width = min(100, ($rs['current_stock'] / ($rs['reorder_level'] * 3)) * 100);
                            }
                            ?>
                            <div class="rs-bar-fill <?= $rs['status_color'] ?>" style="width: <?= $bar_width ?>%;"></div>
                        </div>
                    </div>
                </div>

                <!-- SUMMARY STATS -->
                <div class="summary-grid">
                    <div class="summary-card purchase">
                        <div class="sc-icon"><i class="fas fa-cart-plus"></i></div>
                        <div class="sc-label">Purchased (Period)</div>
                        <div class="sc-value"><?= number_format($sm['purchase_qty']) ?></div>
                        <div class="sc-sub"><?= $sm['purchase_count'] ?> records</div>
                    </div>
                    <div class="summary-card pending">
                        <div class="sc-icon"><i class="fas fa-clock"></i></div>
                        <div class="sc-label">Pending Rx</div>
                        <div class="sc-value"><?= number_format($sm['pending_qty']) ?></div>
                        <div class="sc-sub"><?= $sm['pending_count'] ?> prescriptions</div>
                    </div>
                    <div class="summary-card confirmed">
                        <div class="sc-icon"><i class="fas fa-check"></i></div>
                        <div class="sc-label">Confirmed Rx</div>
                        <div class="sc-value"><?= number_format($sm['confirmed_qty']) ?></div>
                        <div class="sc-sub"><?= $sm['confirmed_count'] ?> prescriptions</div>
                    </div>
                    <div class="summary-card dispensed">
                        <div class="sc-icon"><i class="fas fa-hand-holding-medical"></i></div>
                        <div class="sc-label">Dispensed Rx</div>
                        <div class="sc-value"><?= number_format($sm['dispensed_qty']) ?></div>
                        <div class="sc-sub"><?= $sm['dispensed_count'] ?> prescriptions</div>
                    </div>
                    <div class="summary-card otc">
                        <div class="sc-icon"><i class="fas fa-cash-register"></i></div>
                        <div class="sc-label">OTC Sold</div>
                        <div class="sc-value"><?= number_format($sm['otc_qty']) ?></div>
                        <div class="sc-sub"><?= $sm['otc_count'] ?> OTC sales</div>
                    </div>
                    <div class="summary-card movement">
                        <div class="sc-icon"><i class="fas fa-arrow-right-arrow-left"></i></div>
                        <div class="sc-label">Total Movement</div>
                        <div class="sc-value"><?= number_format($sm['total_movement']) ?></div>
                        <div class="sc-sub"><?= $sm['unique_visits'] ?> visits • <?= $sm['unique_patients'] ?> patients</div>
                    </div>
                </div>

                <!-- PURCHASE HISTORY -->
                <div class="table-card">
                    <div class="table-header">
                        <span class="title"><i class="fas fa-cart-plus"></i> Purchase History (Stock In)</span>
                        <span class="count"><?= count($medicine_details['purchase_history']) ?> records • <?= number_format($sm['purchase_qty']) ?> units</span>
                    </div>
                    <?php if (count($medicine_details['purchase_history']) > 0): ?>
                    <div class="table-scroll-wrapper">
                        <table class="data-table">
                            <thead>
                                <tr><th>#</th><th>Invoice #</th><th>Qty Added</th><th style="text-align:right;">Buying</th><th style="text-align:right;">Selling</th><th style="text-align:right;">Total Cost</th><th>Added By</th><th>Date</th></tr>
                            </thead>
                            <tbody>
                                <?php $i=1; foreach ($medicine_details['purchase_history'] as $ph): ?>
                                <tr>
                                    <td><?= $i++ ?></td>
                                    <td><span class="font-mono" style="font-weight:800;color:var(--primary);"><?= htmlspecialchars($ph['invoice_number']) ?></span></td>
                                    <td><span style="background:var(--success-bg);color:var(--success);padding:3px 10px;border-radius:6px;font-weight:800;font-family:var(--font-mono);">+<?= number_format($ph['quantity']) ?></span></td>
                                    <td class="money-cell"><?= $currency ?> <?= number_format($ph['buying_price'] ?? 0, 0) ?></td>
                                    <td class="money-cell"><?= $currency ?> <?= number_format($ph['selling_price'] ?? 0, 0) ?></td>
                                    <td class="money-cell"><?= $currency ?> <?= number_format($ph['total_buying_cost'] ?? 0, 0) ?></td>
                                    <td><?= htmlspecialchars($ph['added_by_name'] ?? 'N/A') ?></td>
                                    <td><?= date('d M Y, H:i', strtotime($ph['added_at'])) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="empty-state"><i class="fas fa-cart-plus"></i><p>No purchase records for this period</p></div>
                    <?php endif; ?>
                </div>

                <!-- CURRENT STOCK -->
                <div class="table-card">
                    <div class="table-header cyan">
                        <span class="title"><i class="fas fa-warehouse"></i> Current Stock (Batches with Expiry Alerts)</span>
                        <span class="count"><?= number_format($medicine_details['total_current_stock']) ?> units in <?= count($medicine_details['current_stock']) ?> batches</span>
                    </div>
                    <?php if (count($medicine_details['current_stock']) > 0): ?>
                    <div class="table-scroll-wrapper">
                        <table class="data-table">
                            <thead class="cyan">
                                <tr><th>#</th><th>Batch #</th><th>Qty</th><th>Reorder</th><th style="text-align:right;">Selling</th><th>Expiry</th><th>Supplier</th><th>Added</th></tr>
                            </thead>
                            <tbody>
                                <?php 
                                $today = time();
                                $i=1; 
                                foreach ($medicine_details['current_stock'] as $cs): 
                                    $is_low = (int)$cs['quantity'] <= (int)$cs['reorder_level'];
                                    
                                    $expiry_badge = '';
                                    if (!empty($cs['expiry_date']) && $cs['expiry_date'] !== '0000-00-00') {
                                        $days_to_expiry = (strtotime($cs['expiry_date']) - $today) / 86400;
                                        if ($days_to_expiry < 0) {
                                            $expiry_badge = '<span class="status-badge expired" style="font-size:0.55rem;margin-top:3px;"><i class="fas fa-times-circle"></i> EXPIRED</span>';
                                        } elseif ($days_to_expiry <= 30) {
                                            $expiry_badge = '<span class="status-badge expired" style="font-size:0.55rem;margin-top:3px;"><i class="fas fa-exclamation-triangle"></i> ' . round($days_to_expiry) . 'd</span>';
                                        } elseif ($days_to_expiry <= 90) {
                                            $expiry_badge = '<span class="status-badge expiring" style="font-size:0.55rem;margin-top:3px;"><i class="fas fa-clock"></i> ' . round($days_to_expiry) . 'd</span>';
                                        } else {
                                            $expiry_badge = '<span class="status-badge ok" style="font-size:0.55rem;margin-top:3px;"><i class="fas fa-check"></i> ' . round($days_to_expiry) . 'd</span>';
                                        }
                                    }
                                ?>
                                <tr>
                                    <td><?= $i++ ?></td>
                                    <td><span class="font-mono" style="font-weight:700;font-size:0.72rem;"><?= htmlspecialchars($cs['batch_number'] ?? 'N/A') ?></span></td>
                                    <td><span style="background:<?= $is_low ? 'var(--danger-bg)' : 'var(--success-bg)' ?>;color:<?= $is_low ? 'var(--danger)' : 'var(--success)' ?>;padding:3px 10px;border-radius:6px;font-weight:800;font-family:var(--font-mono);"><?= number_format($cs['quantity']) ?></span></td>
                                    <td style="font-family:var(--font-mono);font-weight:700;"><?= number_format($cs['reorder_level']) ?></td>
                                    <td class="money-cell"><?= $currency ?> <?= number_format($cs['selling_price'] ?? 0, 0) ?></td>
                                    <td>
                                        <div style="font-weight:700;font-size:0.72rem;"><?= !empty($cs['expiry_date']) && $cs['expiry_date'] !== '0000-00-00' ? date('d M Y', strtotime($cs['expiry_date'])) : 'N/A' ?></div>
                                        <?= $expiry_badge ?>
                                    </td>
                                    <td><?= htmlspecialchars($cs['supplier'] ?? 'N/A') ?></td>
                                    <td><?= date('d M Y', strtotime($cs['created_at'])) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="empty-state"><i class="fas fa-warehouse"></i><p>No stock available</p></div>
                    <?php endif; ?>
                </div>

                <!-- PRESCRIPTIONS -->
                <div class="table-card">
                    <div class="table-header purple">
                        <span class="title"><i class="fas fa-prescription"></i> Prescriptions</span>
                        <span class="count"><?= count($medicine_details['prescriptions']) ?> prescriptions • <?= number_format($sm['pending_qty'] + $sm['confirmed_qty'] + $sm['dispensed_qty']) ?> units</span>
                    </div>
                    <?php if (count($medicine_details['prescriptions']) > 0): ?>
                    <div class="table-scroll-wrapper">
                        <table class="data-table">
                            <thead class="purple">
                                <tr><th>#</th><th>Visit #</th><th>Patient</th><th>Doctor</th><th>Qty</th><th>Diagnosis</th><th>Status</th><th>Date</th></tr>
                            </thead>
                            <tbody>
                                <?php $i=1; foreach ($medicine_details['prescriptions'] as $pr): 
                                    $pr_status = strtolower($pr['prescription_status'] ?? 'pending');
                                ?>
                                <tr>
                                    <td><?= $i++ ?></td>
                                    <td><span class="font-mono" style="font-weight:700;color:var(--primary);"><?= htmlspecialchars($pr['visit_number'] ?? 'N/A') ?></span></td>
                                    <td>
                                        <div style="font-weight:700;"><?= htmlspecialchars($pr['patient_name'] ?? 'N/A') ?></div>
                                        <div style="font-size:0.68rem;color:var(--text-secondary);"><?= htmlspecialchars($pr['patient_code'] ?? '') ?> • <?= htmlspecialchars($pr['patient_phone'] ?? '') ?></div>
                                    </td>
                                    <td><?= htmlspecialchars($pr['doctor_name'] ?? 'N/A') ?></td>
                                    <td><span style="background:var(--purple-bg);color:var(--purple);padding:3px 10px;border-radius:6px;font-weight:800;font-family:var(--font-mono);"><?= number_format($pr['quantity']) ?></span></td>
                                    <td style="font-size:0.72rem;"><?= htmlspecialchars($pr['diagnosis'] ?? 'N/A') ?></td>
                                    <td>
                                        <?php if ($pr_status === 'dispensed'): ?>
                                            <span class="status-badge dispensed"><i class="fas fa-check-circle"></i> DISPENSED</span>
                                        <?php elseif ($pr_status === 'confirmed'): ?>
                                            <span class="status-badge confirmed"><i class="fas fa-check"></i> CONFIRMED</span>
                                        <?php else: ?>
                                            <span class="status-badge pending"><i class="fas fa-clock"></i> PENDING</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= date('d M Y', strtotime($pr['prescribed_at'])) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="empty-state"><i class="fas fa-prescription"></i><p>No prescriptions for this period</p></div>
                    <?php endif; ?>
                </div>

                <!-- OTC SALES -->
                <div class="table-card">
                    <div class="table-header green">
                        <span class="title"><i class="fas fa-cash-register"></i> OTC Sales</span>
                        <span class="count"><?= count($medicine_details['otc_sales']) ?> sales • <?= number_format($sm['otc_qty']) ?> units</span>
                    </div>
                    <?php if (count($medicine_details['otc_sales']) > 0): ?>
                    <div class="table-scroll-wrapper">
                        <table class="data-table">
                            <thead class="green">
                                <tr><th>#</th><th>Sale #</th><th>Customer</th><th>Qty</th><th style="text-align:right;">Total</th><th>Sold By</th><th>Status</th><th>Date</th></tr>
                            </thead>
                            <tbody>
                                <?php $i=1; foreach ($medicine_details['otc_sales'] as $os): 
                                    $os_status = strtolower($os['payment_status'] ?? 'pending');
                                ?>
                                <tr>
                                    <td><?= $i++ ?></td>
                                    <td><span class="font-mono" style="font-weight:800;color:var(--cyan);"><?= htmlspecialchars($os['sale_number'] ?? 'N/A') ?></span></td>
                                    <td>
                                        <div style="font-weight:700;"><?= htmlspecialchars($os['customer_name'] ?? 'Walk-in Customer') ?></div>
                                        <div style="font-size:0.68rem;color:var(--text-secondary);"><?= htmlspecialchars($os['customer_phone'] ?? '') ?></div>
                                    </td>
                                    <td><span style="background:var(--cyan-bg);color:var(--cyan);padding:3px 10px;border-radius:6px;font-weight:800;font-family:var(--font-mono);"><?= number_format($os['quantity']) ?></span></td>
                                    <td class="money-cell"><?= $currency ?> <?= number_format($os['total_price'] ?? 0, 0) ?></td>
                                    <td><?= htmlspecialchars($os['sold_by_name'] ?? 'N/A') ?></td>
                                    <td>
                                        <?php if ($os_status === 'paid'): ?>
                                            <span class="status-badge paid"><i class="fas fa-check-circle"></i> PAID</span>
                                        <?php elseif ($os_status === 'partial'): ?>
                                            <span class="status-badge partial"><i class="fas fa-hourglass-half"></i> PARTIAL</span>
                                        <?php else: ?>
                                            <span class="status-badge pending"><i class="fas fa-clock"></i> PENDING</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= date('d M Y, H:i', strtotime($os['sold_at'])) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="empty-state"><i class="fas fa-cash-register"></i><p>No OTC sales for this period</p></div>
                    <?php endif; ?>
                </div>
                
            </div>
            
        <?php else: ?>
            <div class="table-card">
                <div class="table-header">
                    <span class="title"><i class="fas fa-pills"></i> Medicine Tracking</span>
                    <span class="count">Search to view details</span>
                </div>
                <div class="empty-state">
                    <i class="fas fa-search"></i>
                    <p>Search medicine above or click on Top 5 cards</p>
                    <p style="font-size:0.78rem;margin-top:8px;color:var(--text-muted);">Period Overview • Last Added Stock • Remaining Stock • Purchase History • Prescriptions • OTC Sales</p>
                </div>
            </div>
        <?php endif; ?>

    <!-- EQUIPMENT TAB -->
    <?php else: ?>
        
        <?php if ($equipment_details): ?>
            <?php 
            $eq = $equipment_details['info'];
            $sm = $equipment_details['summary'];
            $rs = $equipment_details['remaining_stock'];
            $lsi = $equipment_details['last_stock_info'];
            $pi = $equipment_details['period_info'];
            $dus = $equipment_details['days_until_stockout'];
            ?>
            
            <div class="details-container">
                
                <div class="info-banner">
                    <div class="ib-icon"><i class="fas fa-tools"></i></div>
                    <div class="ib-content">
                        <div class="ib-title"><?= htmlspecialchars($equipment_details['name']) ?></div>
                        <div class="ib-meta">
                            <span><i class="fas fa-tag"></i> <?= htmlspecialchars($eq['category'] ?? 'N/A') ?></span>
                            <span><i class="fas fa-box"></i> Unit: <?= htmlspecialchars($eq['unit'] ?? 'N/A') ?></span>
                            <span><i class="fas fa-money-bill"></i> Selling: <?= $currency ?> <?= number_format($eq['selling_price'] ?? 0, 0) ?></span>
                            <span style="color:var(--warning);font-weight:800;"><i class="fas fa-lock"></i> <?= htmlspecialchars($branch_name_display) ?></span>
                        </div>
                    </div>
                </div>

                <!-- PERIOD OVERVIEW -->
                <div class="period-overview-card">
                    <div class="po-header">
                        <div class="po-title"><i class="fas fa-calendar-check"></i> Period Overview</div>
                        <div class="po-badge"><i class="fas fa-filter"></i> <?= htmlspecialchars($pi['filter_label']) ?> • <?= htmlspecialchars($branch_name_display) ?></div>
                    </div>
                    <div class="po-grid">
                        <div class="po-item po-start">
                            <div class="po-icon"><i class="fas fa-warehouse"></i></div>
                            <div class="po-content">
                                <div class="po-label">Stock at Start</div>
                                <div class="po-value"><?= number_format($pi['stock_at_period_start']) ?> <span>units</span></div>
                                <div class="po-sub">When period began</div>
                            </div>
                        </div>
                        <div class="po-item po-added">
                            <div class="po-icon"><i class="fas fa-cart-plus"></i></div>
                            <div class="po-content">
                                <div class="po-label">Added in Period</div>
                                <div class="po-value">+<?= number_format($pi['purchases_total_qty']) ?> <span>units</span></div>
                                <div class="po-sub"><?= $pi['purchases_count'] ?> purchases</div>
                            </div>
                        </div>
                        <div class="po-item po-movements">
                            <div class="po-icon"><i class="fas fa-arrow-right-arrow-left"></i></div>
                            <div class="po-content">
                                <div class="po-label">Movements</div>
                                <div class="po-value">-<?= number_format($pi['movements_total']) ?> <span>uses</span></div>
                                <div class="po-sub">Bills: <?= number_format($pi['movements_bill']) ?> • Lab: <?= number_format($pi['movements_lab']) ?></div>
                            </div>
                        </div>
                        <div class="po-item po-current">
                            <div class="po-icon"><i class="fas fa-boxes-packing"></i></div>
                            <div class="po-content">
                                <div class="po-label">Current Stock</div>
                                <div class="po-value"><?= number_format($equipment_details['total_current_stock']) ?> <span>units</span></div>
                                <div class="po-sub">Now in inventory</div>
                            </div>
                        </div>
                    </div>
                    <?php if (count($pi['purchases_unique_users']) > 0): ?>
                    <div class="po-users">
                        <div class="po-users-label"><i class="fas fa-users"></i> Users who added stock:</div>
                        <div class="po-users-list">
                            <?php foreach ($pi['purchases_unique_users'] as $user): ?>
                                <span class="po-user-tag"><i class="fas fa-user-circle"></i> <?= htmlspecialchars($user) ?></span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- LAST ADDED STOCK -->
                <?php if ($lsi['has_last_purchase']): ?>
                <div class="last-stock-card <?= $lsi['is_accurate'] ? 'accurate' : 'warning' ?>">
                    <div class="ls-header">
                        <div class="ls-title"><i class="fas fa-box-open"></i> Last Added Stock</div>
                        <div class="ls-status <?= $lsi['is_accurate'] ? 'accurate' : 'warning' ?>">
                            <?php if ($lsi['is_accurate']): ?>
                                <i class="fas fa-check-circle"></i> STOCK ACCURATE
                            <?php else: ?>
                                <i class="fas fa-exclamation-triangle"></i> VARIANCE: <?= $lsi['variance'] > 0 ? '+' : '' ?><?= number_format($lsi['variance']) ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="ls-timeline">
                        <div class="ls-tl-item purchase">
                            <div class="ls-tl-icon"><i class="fas fa-truck-loading"></i></div>
                            <div class="ls-tl-content">
                                <div class="ls-tl-label">Last Added</div>
                                <div class="ls-tl-value">+<?= number_format($lsi['last_added_qty']) ?></div>
                                <div class="ls-tl-meta"><i class="fas fa-user"></i> <?= htmlspecialchars($lsi['last_added_by']) ?></div>
                                <div class="ls-tl-meta"><i class="fas fa-calendar"></i> <?= date('d M Y', strtotime($lsi['last_added_date'])) ?></div>
                            </div>
                        </div>
                        <div class="ls-tl-arrow"><i class="fas fa-plus"></i></div>
                        <div class="ls-tl-item previous">
                            <div class="ls-tl-icon"><i class="fas fa-history"></i></div>
                            <div class="ls-tl-content">
                                <div class="ls-tl-label">Previous Stock</div>
                                <div class="ls-tl-value"><?= number_format($lsi['previous_stock']) ?></div>
                                <div class="ls-tl-meta">Before last add</div>
                            </div>
                        </div>
                        <div class="ls-tl-arrow equal"><i class="fas fa-equals"></i></div>
                        <div class="ls-tl-item total">
                            <div class="ls-tl-icon"><i class="fas fa-calculator"></i></div>
                            <div class="ls-tl-content">
                                <div class="ls-tl-label">Available Now</div>
                                <div class="ls-tl-value"><?= number_format($lsi['available_after_last_add']) ?></div>
                                <div class="ls-tl-meta">Prev + Last Added</div>
                            </div>
                        </div>
                        <div class="ls-tl-arrow minus"><i class="fas fa-minus"></i></div>
                        <div class="ls-tl-item used">
                            <div class="ls-tl-icon"><i class="fas fa-arrow-right-from-bracket"></i></div>
                            <div class="ls-tl-content">
                                <div class="ls-tl-label">Movements</div>
                                <div class="ls-tl-value">-<?= number_format($lsi['movements_after_last']) ?></div>
                                <div class="ls-tl-meta">Since last add</div>
                            </div>
                        </div>
                        <div class="ls-tl-arrow equal"><i class="fas fa-equals"></i></div>
                        <div class="ls-tl-item expected">
                            <div class="ls-tl-icon"><i class="fas fa-calculator"></i></div>
                            <div class="ls-tl-content">
                                <div class="ls-tl-label">Expected</div>
                                <div class="ls-tl-value"><?= number_format($lsi['expected_remaining']) ?></div>
                                <div class="ls-tl-meta">After movements</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="ls-verification <?= $lsi['is_accurate'] ? 'accurate' : 'warning' ?>">
                        <div class="ls-v-item">
                            <div class="ls-v-label"><i class="fas fa-calculator"></i> Expected</div>
                            <div class="ls-v-value"><?= number_format($lsi['expected_remaining']) ?></div>
                        </div>
                        <div class="ls-v-vs"><?= $lsi['is_accurate'] ? '<i class="fas fa-equals"></i>' : '<i class="fas fa-not-equal"></i>' ?></div>
                        <div class="ls-v-item">
                            <div class="ls-v-label"><i class="fas fa-warehouse"></i> Actual</div>
                            <div class="ls-v-value"><?= number_format($lsi['actual_remaining']) ?></div>
                        </div>
                        <div class="ls-v-vs"><i class="fas fa-equals"></i></div>
                        <div class="ls-v-item <?= $lsi['is_accurate'] ? 'success' : 'danger' ?>">
                            <div class="ls-v-label"><?= $lsi['is_accurate'] ? '<i class="fas fa-check-circle"></i> Match' : '<i class="fas fa-exclamation-triangle"></i> Variance' ?></div>
                            <div class="ls-v-value" style="color: <?= $lsi['variance'] >= 0 ? 'var(--success)' : 'var(--danger)' ?>;">
                                <?= $lsi['variance'] > 0 ? '+' : '' ?><?= number_format($lsi['variance']) ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- REMAINING STOCK -->
                <div class="remaining-stock-card <?= $rs['status_color'] ?>">
                    <div class="rs-header">
                        <div class="rs-title"><i class="fas fa-boxes-packing"></i> Remaining Stock</div>
                        <div class="rs-status-badge <?= $rs['status_color'] ?>">
                            <i class="fas <?= $rs['status_icon'] ?>"></i> <?= $rs['status_label'] ?>
                        </div>
                    </div>
                    <div class="rs-grid">
                        <div class="rs-item stock-balance">
                            <div class="rs-item-icon"><i class="fas fa-warehouse"></i></div>
                            <div class="rs-item-content">
                                <div class="rs-item-label">Stock Balance</div>
                                <div class="rs-item-value"><?= number_format($rs['current_stock']) ?> <span class="rs-unit">units</span></div>
                                <div class="rs-item-sub">Available now</div>
                            </div>
                        </div>
                        <div class="rs-item stock-usage">
                            <div class="rs-item-icon"><i class="fas fa-arrow-right-from-bracket"></i></div>
                            <div class="rs-item-content">
                                <div class="rs-item-label">Total Used</div>
                                <div class="rs-item-value"><?= number_format($rs['total_used']) ?> <span class="rs-unit">uses</span></div>
                                <div class="rs-item-sub">Lab + Bills in period</div>
                            </div>
                        </div>
                        <div class="rs-item stock-value">
                            <div class="rs-item-icon"><i class="fas fa-money-bill-trend-up"></i></div>
                            <div class="rs-item-content">
                                <div class="rs-item-label">Stock Value</div>
                                <div class="rs-item-value"><?= $currency ?> <?= number_format($rs['stock_value'], 0) ?></div>
                                <div class="rs-item-sub">@ <?= $currency ?> <?= number_format($rs['average_selling_price'], 0) ?></div>
                            </div>
                        </div>
                        <div class="rs-item stock-reorder">
                            <div class="rs-item-icon"><i class="fas fa-triangle-exclamation"></i></div>
                            <div class="rs-item-content">
                                <div class="rs-item-label">Reorder Level</div>
                                <div class="rs-item-value"><?= number_format($rs['reorder_level']) ?> <span class="rs-unit">units</span></div>
                                <div class="rs-item-sub"><?= $rs['current_stock'] > $rs['reorder_level'] ? '✅ Above' : '⚠️ Below' ?></div>
                            </div>
                        </div>
                        <div class="rs-item stock-stockout">
                            <div class="rs-item-icon"><i class="fas fa-hourglass-end"></i></div>
                            <div class="rs-item-content">
                                <div class="rs-item-label">Days to Stockout</div>
                                <div class="rs-item-value"><?= $dus >= 999 ? '∞' : $dus ?> <span class="rs-unit">days</span></div>
                                <div class="rs-item-sub">
                                    <?php if ($dus <= 7 && $dus < 999): ?>
                                        🔴 Urgent!
                                    <?php elseif ($dus <= 14 && $dus < 999): ?>
                                        🟡 Soon
                                    <?php else: ?>
                                        ✅ Sufficient
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="rs-health-bar">
                        <div class="rs-health-label">
                            <span><i class="fas fa-heartbeat"></i> Stock Health</span>
                            <span class="rs-health-percent"><?= $rs['current_stock'] > 0 ? 'OK' : 'EMPTY' ?></span>
                        </div>
                        <div class="rs-bar-track">
                            <?php 
                            $bar_width = 0;
                            if ($rs['reorder_level'] > 0) {
                                $bar_width = min(100, ($rs['current_stock'] / ($rs['reorder_level'] * 3)) * 100);
                            }
                            ?>
                            <div class="rs-bar-fill <?= $rs['status_color'] ?>" style="width: <?= $bar_width ?>%;"></div>
                        </div>
                    </div>
                </div>

                <!-- SUMMARY STATS -->
                <div class="summary-grid">
                    <div class="summary-card purchase">
                        <div class="sc-icon"><i class="fas fa-cart-plus"></i></div>
                        <div class="sc-label">Purchased (Period)</div>
                        <div class="sc-value"><?= number_format($sm['purchase_qty']) ?></div>
                        <div class="sc-sub"><?= $sm['purchase_count'] ?> records</div>
                    </div>
                    <div class="summary-card pending">
                        <div class="sc-icon"><i class="fas fa-clock"></i></div>
                        <div class="sc-label">Pending Tests</div>
                        <div class="sc-value"><?= number_format($sm['pending_qty']) ?></div>
                        <div class="sc-sub"><?= $sm['pending_count'] ?> tests</div>
                    </div>
                    <div class="summary-card confirmed">
                        <div class="sc-icon"><i class="fas fa-spinner"></i></div>
                        <div class="sc-label">In Progress</div>
                        <div class="sc-value"><?= number_format($sm['in_progress_qty']) ?></div>
                        <div class="sc-sub"><?= $sm['in_progress_count'] ?> tests</div>
                    </div>
                    <div class="summary-card dispensed">
                        <div class="sc-icon"><i class="fas fa-check-circle"></i></div>
                        <div class="sc-label">Completed</div>
                        <div class="sc-value"><?= number_format($sm['completed_qty']) ?></div>
                        <div class="sc-sub"><?= $sm['completed_count'] ?> tests</div>
                    </div>
                    <div class="summary-card otc">
                        <div class="sc-icon"><i class="fas fa-prescription-bottle"></i></div>
                        <div class="sc-label">Used in Visits</div>
                        <div class="sc-value"><?= number_format($sm['bill_qty']) ?></div>
                        <div class="sc-sub"><?= $sm['bill_count'] ?> bill items</div>
                    </div>
                    <div class="summary-card movement">
                        <div class="sc-icon"><i class="fas fa-arrow-right-arrow-left"></i></div>
                        <div class="sc-label">Total Movement</div>
                        <div class="sc-value"><?= number_format($sm['total_movement']) ?></div>
                        <div class="sc-sub"><?= $sm['unique_visits'] ?> visits • <?= $sm['unique_patients'] ?> patients</div>
                    </div>
                </div>

                <!-- PURCHASE HISTORY -->
                <div class="table-card">
                    <div class="table-header">
                        <span class="title"><i class="fas fa-cart-plus"></i> Purchase History</span>
                        <span class="count"><?= count($equipment_details['purchase_history']) ?> records • <?= number_format($sm['purchase_qty']) ?> units</span>
                    </div>
                    <?php if (count($equipment_details['purchase_history']) > 0): ?>
                    <div class="table-scroll-wrapper">
                        <table class="data-table">
                            <thead>
                                <tr><th>#</th><th>Invoice #</th><th>Qty Added</th><th style="text-align:right;">Buying</th><th style="text-align:right;">Selling</th><th style="text-align:right;">Total Cost</th><th>Added By</th><th>Date</th></tr>
                            </thead>
                            <tbody>
                                <?php $i=1; foreach ($equipment_details['purchase_history'] as $ph): ?>
                                <tr>
                                    <td><?= $i++ ?></td>
                                    <td><span class="font-mono" style="font-weight:800;color:var(--primary);"><?= htmlspecialchars($ph['invoice_number']) ?></span></td>
                                    <td><span style="background:var(--success-bg);color:var(--success);padding:3px 10px;border-radius:6px;font-weight:800;font-family:var(--font-mono);">+<?= number_format($ph['quantity']) ?></span></td>
                                    <td class="money-cell"><?= $currency ?> <?= number_format($ph['buying_price'] ?? 0, 0) ?></td>
                                    <td class="money-cell"><?= $currency ?> <?= number_format($ph['selling_price'] ?? 0, 0) ?></td>
                                    <td class="money-cell"><?= $currency ?> <?= number_format($ph['total_buying_cost'] ?? 0, 0) ?></td>
                                    <td><?= htmlspecialchars($ph['added_by_name'] ?? 'N/A') ?></td>
                                    <td><?= date('d M Y, H:i', strtotime($ph['added_at'])) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="empty-state"><i class="fas fa-cart-plus"></i><p>No purchase records for this period</p></div>
                    <?php endif; ?>
                </div>

                <!-- CURRENT STOCK -->
                <div class="table-card">
                    <div class="table-header cyan">
                        <span class="title"><i class="fas fa-warehouse"></i> Current Stock</span>
                        <span class="count"><?= number_format($equipment_details['total_current_stock']) ?> units</span>
                    </div>
                    <?php if (count($equipment_details['current_stock']) > 0): ?>
                    <div class="table-scroll-wrapper">
                        <table class="data-table">
                            <thead class="cyan">
                                <tr><th>#</th><th>Equipment</th><th>Qty</th><th>Reorder</th><th style="text-align:right;">Selling</th><th>Supplier</th><th>Added</th></tr>
                            </thead>
                            <tbody>
                                <?php $i=1; foreach ($equipment_details['current_stock'] as $cs): 
                                    $is_low = (int)$cs['quantity'] <= (int)$cs['reorder_level'];
                                ?>
                                <tr>
                                    <td><?= $i++ ?></td>
                                    <td><?= htmlspecialchars($cs['equipment_name']) ?></td>
                                    <td><span style="background:<?= $is_low ? 'var(--danger-bg)' : 'var(--success-bg)' ?>;color:<?= $is_low ? 'var(--danger)' : 'var(--success)' ?>;padding:3px 10px;border-radius:6px;font-weight:800;font-family:var(--font-mono);"><?= number_format($cs['quantity']) ?></span></td>
                                    <td style="font-family:var(--font-mono);font-weight:700;"><?= number_format($cs['reorder_level']) ?></td>
                                    <td class="money-cell"><?= $currency ?> <?= number_format($cs['selling_price'] ?? 0, 0) ?></td>
                                    <td><?= htmlspecialchars($cs['supplier'] ?? 'N/A') ?></td>
                                    <td><?= date('d M Y', strtotime($cs['created_at'])) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="empty-state"><i class="fas fa-warehouse"></i><p>No stock available</p></div>
                    <?php endif; ?>
                </div>

                <!-- BILL ITEMS -->
                <div class="table-card">
                    <div class="table-header green">
                        <span class="title"><i class="fas fa-prescription-bottle"></i> Used in Visits</span>
                        <span class="count"><?= count($equipment_details['bill_items']) ?> items • <?= number_format($sm['bill_qty']) ?> units</span>
                    </div>
                    <?php if (count($equipment_details['bill_items']) > 0): ?>
                    <div class="table-scroll-wrapper">
                        <table class="data-table">
                            <thead class="green">
                                <tr><th>#</th><th>Bill #</th><th>Visit #</th><th>Patient</th><th>Doctor</th><th>Qty</th><th>Status</th><th>Date</th></tr>
                            </thead>
                            <tbody>
                                <?php $i=1; foreach ($equipment_details['bill_items'] as $bi): 
                                    $bi_status = strtolower($bi['item_status'] ?? 'pending');
                                ?>
                                <tr>
                                    <td><?= $i++ ?></td>
                                    <td><span class="font-mono" style="font-weight:700;font-size:0.7rem;color:var(--cyan);"><?= htmlspecialchars($bi['bill_number'] ?? 'N/A') ?></span></td>
                                    <td><span class="font-mono" style="font-weight:700;color:var(--primary);"><?= htmlspecialchars($bi['visit_number'] ?? 'N/A') ?></span></td>
                                    <td><?= htmlspecialchars($bi['patient_name'] ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars($bi['doctor_name'] ?? 'N/A') ?></td>
                                    <td><span style="background:var(--cyan-bg);color:var(--cyan);padding:3px 10px;border-radius:6px;font-weight:800;font-family:var(--font-mono);"><?= number_format($bi['quantity']) ?></span></td>
                                    <td>
                                        <?php if ($bi_status === 'paid'): ?>
                                            <span class="status-badge paid"><i class="fas fa-check-circle"></i> PAID</span>
                                        <?php else: ?>
                                            <span class="status-badge pending"><i class="fas fa-clock"></i> PENDING</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= date('d M Y', strtotime($bi['item_created_at'])) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="empty-state"><i class="fas fa-prescription-bottle"></i><p>No bill items for this period</p></div>
                    <?php endif; ?>
                </div>

                <!-- LAB TESTS -->
                <div class="table-card">
                    <div class="table-header purple">
                        <span class="title"><i class="fas fa-flask"></i> Lab Tests using this Equipment</span>
                        <span class="count"><?= count($equipment_details['lab_tests']) ?> tests</span>
                    </div>
                    <?php if (count($equipment_details['lab_tests']) > 0): ?>
                    <div class="table-scroll-wrapper">
                        <table class="data-table">
                            <thead class="purple">
                                <tr><th>#</th><th>Visit #</th><th>Patient</th><th>Test Name</th><th>Doctor</th><th>Lab Tech</th><th>Status</th><th>Date</th></tr>
                            </thead>
                            <tbody>
                                <?php $i=1; foreach ($equipment_details['lab_tests'] as $lt): 
                                    $lt_status = strtolower($lt['test_status'] ?? 'pending');
                                ?>
                                <tr>
                                    <td><?= $i++ ?></td>
                                    <td><span class="font-mono" style="font-weight:700;color:var(--primary);"><?= htmlspecialchars($lt['visit_number'] ?? 'N/A') ?></span></td>
                                    <td><?= htmlspecialchars($lt['patient_name'] ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars($lt['test_name'] ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars($lt['doctor_name'] ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars($lt['lab_tech_name'] ?? 'N/A') ?></td>
                                    <td>
                                        <?php if ($lt_status === 'completed'): ?>
                                            <span class="status-badge completed"><i class="fas fa-check-circle"></i> COMPLETED</span>
                                        <?php elseif ($lt_status === 'in_progress'): ?>
                                            <span class="status-badge in_progress"><i class="fas fa-spinner"></i> IN PROGRESS</span>
                                        <?php else: ?>
                                            <span class="status-badge pending"><i class="fas fa-clock"></i> PENDING</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= date('d M Y', strtotime($lt['test_created_at'] ?? 'now')) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="empty-state"><i class="fas fa-flask"></i><p>No lab tests for this period</p></div>
                    <?php endif; ?>
                </div>
                
            </div>
            
        <?php else: ?>
            <div class="table-card">
                <div class="table-header">
                    <span class="title"><i class="fas fa-tools"></i> Equipment Tracking</span>
                    <span class="count">Search to view details</span>
                </div>
                <div class="empty-state">
                    <i class="fas fa-search"></i>
                    <p>Search equipment above or click on Top 5 cards</p>
                    <p style="font-size:0.78rem;margin-top:8px;color:var(--text-muted);">Period Overview • Last Added Stock • Remaining Stock • Purchase History • Bill Items • Lab Tests</p>
                </div>
            </div>
        <?php endif; ?>

    <?php endif; ?>

</main>

<script>
function toggleCustomDate() {
    var section = document.getElementById('customDateSection');
    section.style.display = section.style.display === 'none' ? 'block' : 'none';
}

(function() {
    var searchInput = document.getElementById('searchInput');
    var autocompleteBox = document.getElementById('autocompleteBox');
    var searchForm = document.getElementById('searchForm');
    var activeTab = '<?= $active_tab ?>';
    
    if (!searchInput) return;
    
    var debounceTimer;
    
    searchInput.addEventListener('input', function() {
        clearTimeout(debounceTimer);
        var q = this.value.trim();
        
        if (q.length < 2) {
            autocompleteBox.classList.remove('active');
            return;
        }
        
        debounceTimer = setTimeout(function() {
            fetch('?ajax=search&type=' + activeTab + '&q=' + encodeURIComponent(q))
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data.results && data.results.length > 0) {
                        var html = '';
                        data.results.slice(0, 5).forEach(function(item) {
                            var stock = parseInt(item.total_stock) || 0;
                            var lowClass = stock <= 10 ? ' low' : '';
                            var safeName = (item.name || '').replace(/\\/g, '\\\\').replace(/'/g, "\\'").replace(/"/g, '&quot;');
                            html += '<div class="autocomplete-item" onclick="selectItem(' + item.id + ', \'' + safeName + '\')">';
                            html += '<div class="item-main">';
                            html += '<div class="item-icon"><i class="fas ' + (activeTab === 'medicine' ? 'fa-pills' : 'fa-tools') + '"></i></div>';
                            html += '<div>';
                            html += '<div class="item-name">' + (item.name || '') + '</div>';
                            html += '<div class="item-meta">' + (item.batches ? item.batches + ' batches' : 'ID: #' + item.id) + '</div>';
                            html += '</div>';
                            html += '</div>';
                            html += '<div class="item-stock' + lowClass + '">' + stock + ' in stock</div>';
                            html += '</div>';
                        });
                        autocompleteBox.innerHTML = html;
                        autocompleteBox.classList.add('active');
                    } else {
                        autocompleteBox.innerHTML = '<div class="autocomplete-empty"><i class="fas fa-search-minus"></i><br>No results for "' + q + '"</div>';
                        autocompleteBox.classList.add('active');
                    }
                })
                .catch(function(err) { console.error('Search error:', err); });
        }, 250);
    });
    
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.search-wrapper')) {
            autocompleteBox.classList.remove('active');
        }
    });
})();

function selectItem(id, name) {
    document.getElementById('itemIdInput').value = id;
    document.getElementById('searchInput').value = name;
    document.getElementById('autocompleteBox').classList.remove('active');
    document.getElementById('searchForm').submit();
}
</script>

</body>
</html>