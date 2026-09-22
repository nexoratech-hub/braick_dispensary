<?php
// ================================================================
// FILE: frontend/pages/admin/audit/stock_movement.php
// STOCK MOVEMENT REPORT V5 - COMPLETE TRACKING + TOP 5 + REMAINING STOCK
// ================================================================
// ✅ V5: Remaining Stock Card (Stock Balance, Usage, Value, Reorder Level)
// ✅ V5: Stock Health Bar with Status (Sufficient/Low/Critical/Out)
// ✅ V4: Search placeholder in English
// ✅ V4: Top 5 Most Used Medicines & Equipment (clickable)
// ✅ V3: Medicine & Equipment Tracking (Purchases + Inventory + Rx + OTC)
// ✅ V3: Live Search with Top 5 Suggestions
// ================================================================

date_default_timezone_set('Africa/Dar_es_Salaam');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: /dispensary_system/frontend/pages/login.php');
    exit;
}

if ($_SESSION['role'] !== 'admin') {
    header('Location: /dispensary_system/frontend/pages/login.php');
    exit;
}

$user_id = $_SESSION['user_id'] ?? 0;
$user_full_name = $_SESSION['full_name'] ?? 'Admin';
$user_role = $_SESSION['role'] ?? 'admin';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$username = $_SESSION['username'] ?? '';
$profile_pic = $_SESSION['profile_pic'] ?? '';

require_once __DIR__ . '/../../../../backend/config/database.php';

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

// Branches
$selected_branch_id = $_GET['branch'] ?? 'all';
$branch_name_display = 'All Branches';
if ($selected_branch_id !== 'all' && is_numeric($selected_branch_id)) {
    $stmt = $db->prepare("SELECT name FROM branches WHERE id = ? AND status = 'active'");
    $stmt->execute([(int)$selected_branch_id]);
    $branch_data = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($branch_data) $branch_name_display = $branch_data['name'];
}

// Filters
$active_tab = $_GET['tab'] ?? 'medicine';
$quick_filter = $_GET['quick'] ?? 'all';
$search = trim($_GET['search'] ?? '');
$selected_item_id = (int)($_GET['item_id'] ?? 0);
$date_from = $_GET['date_from'] ?? date('Y-m-d');
$date_to = $_GET['date_to'] ?? date('Y-m-d');

// Build date conditions
$date_cond_purchases = "";
$date_cond_otc = "";
$date_cond_prescriptions = "";
$date_cond_lab = "";
$date_params = [];
$date_label = "";

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

$branch_cond_p = ""; $branch_cond_o = ""; $branch_cond_pr = ""; $branch_cond_lt = "";
$branch_cond_bi = "";
$branch_params_p = []; $branch_params_o = []; $branch_params_pr = []; $branch_params_lt = []; $branch_params_bi = [];

if ($selected_branch_id !== 'all') {
    $branch_cond_p = " AND p.branch_id = ?";
    $branch_cond_o = " AND os.branch_id = ?";
    $branch_cond_pr = " AND pr.branch_id = ?";
    $branch_cond_lt = " AND lt.branch_id = ?";
    $branch_cond_bi = " AND bi.branch_id = ?";
    $branch_params_p = [(int)$selected_branch_id];
    $branch_params_o = [(int)$selected_branch_id];
    $branch_params_pr = [(int)$selected_branch_id];
    $branch_params_lt = [(int)$selected_branch_id];
    $branch_params_bi = [(int)$selected_branch_id];
}

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
                WHERE status = 'active' AND medication_name LIKE ?
                " . ($selected_branch_id !== 'all' ? " AND branch_id = ?" : "") . "
                GROUP BY medication_name
                ORDER BY medication_name ASC LIMIT 5";
        $params = ["%$q%"];
        if ($selected_branch_id !== 'all') $params[] = (int)$selected_branch_id;
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($results as &$r) { $r['id'] = $r['id_sample']; }
        unset($r);
    } else {
        $sql = "SELECT equipment_name as name, 
                    SUM(quantity) as total_stock,
                    MAX(id) as id_sample
                FROM medical_equipment
                WHERE status = 'active' AND equipment_name LIKE ?
                " . ($selected_branch_id !== 'all' ? " AND branch_id = ?" : "") . "
                GROUP BY equipment_name
                ORDER BY equipment_name ASC LIMIT 5";
        $params = ["%$q%"];
        if ($selected_branch_id !== 'all') $params[] = (int)$selected_branch_id;
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($results as &$r) { $r['id'] = $r['id_sample']; }
        unset($r);
    }
    
    echo json_encode(['results' => $results]);
    exit;
}

// ================================================================
// TOP 5 MOST USED ITEMS
// ================================================================
$top5_medicines = [];
$top5_equipment = [];

// Top 5 Medicines
try {
    $sql = "SELECT 
                combined.med_name as name,
                SUM(combined.total_qty) as total_qty,
                (SELECT id FROM medications_inventory WHERE medication_name = combined.med_name AND status='active' LIMIT 1) as item_id
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
            ORDER BY total_qty DESC
            LIMIT 5";
    
    $params = array_merge($date_params, $branch_params_pr, $date_params, $branch_params_o);
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $top5_medicines = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { 
    error_log("Top 5 medicines error: " . $e->getMessage());
}

// Top 5 Equipment
try {
    $sql = "SELECT 
                combined.eq_name as name,
                SUM(combined.total_qty) as total_qty,
                (SELECT id FROM medical_equipment WHERE equipment_name = combined.eq_name AND status='active' LIMIT 1) as item_id
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
            ORDER BY total_qty DESC
            LIMIT 5";
    
    $params = array_merge($date_params, $branch_params_lt, $date_params, $branch_params_bi);
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $top5_equipment = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { 
    error_log("Top 5 equipment error: " . $e->getMessage());
}

// ================================================================
// LOAD DETAILS
// ================================================================
$medicine_details = null;
$equipment_details = null;

// ================ MEDICINE DETAILS ================
if ($active_tab === 'medicine' && $selected_item_id > 0) {
    $stmt = $db->prepare("SELECT medication_name FROM medications_inventory WHERE id = ?");
    $stmt->execute([$selected_item_id]);
    $med_row = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($med_row) {
        $med_name = $med_row['medication_name'];
        
        // CURRENT STOCK
        $current_stock = [];
        try {
            $sql = "SELECT id, medication_name, category, unit, quantity, reorder_level, 
                        unit_cost, selling_price, supplier, expiry_date, batch_number, 
                        branch_id, created_at, updated_at, added_by_name
                    FROM medications_inventory
                    WHERE medication_name = ? AND status = 'active'
                    " . ($selected_branch_id !== 'all' ? " AND branch_id = ?" : "") . "
                    ORDER BY expiry_date ASC, id ASC";
            $params = [$med_name];
            if ($selected_branch_id !== 'all') $params[] = (int)$selected_branch_id;
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $current_stock = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {}
        
        $total_current_stock = 0;
        foreach ($current_stock as $cs) { $total_current_stock += (int)$cs['quantity']; }
        
        $medicine_info = !empty($current_stock) ? $current_stock[0] : [
            'medication_name' => $med_name,
            'category' => 'N/A', 'unit' => 'N/A', 'selling_price' => 0
        ];
        
        // PURCHASE HISTORY
        $inventory_ids = [];
        try {
            $stmt = $db->prepare("SELECT id FROM medications_inventory WHERE medication_name = ?");
            $stmt->execute([$med_name]);
            $inventory_ids = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'id');
        } catch (Exception $e) {}
        
        $purchase_history = [];
        if (!empty($inventory_ids)) {
            try {
                $placeholders = implode(',', array_fill(0, count($inventory_ids), '?'));
                $sql = "SELECT pi.id, pi.quantity, pi.buying_price, pi.selling_price,
                            pi.total_buying_cost, pi.total_selling_value, 
                            pi.added_by, pi.added_by_name, pi.added_at,
                            p.invoice_number, p.purchase_type, p.status as purchase_status, 
                            p.created_at as purchase_created_at,
                            br.name as branch_name
                        FROM purchase_items pi
                        INNER JOIN purchases p ON pi.purchase_id = p.id
                        LEFT JOIN branches br ON p.branch_id = br.id
                        WHERE pi.item_type = 'medicine' 
                        AND pi.item_id IN ($placeholders)
                        AND p.status = 'COMPLETED'
                        $date_cond_purchases
                        ORDER BY p.created_at DESC";
                $stmt = $db->prepare($sql);
                $stmt->execute(array_merge($inventory_ids, $date_params));
                $purchase_history = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (Exception $e) { error_log("Purchase history error: " . $e->getMessage()); }
        }
        
        // PRESCRIPTIONS
        $prescriptions = [];
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
                    $date_cond_prescriptions
                    $branch_cond_pr
                    ORDER BY pr.created_at DESC
                    LIMIT 300";
            $stmt = $db->prepare($sql);
            $stmt->execute(array_merge([$med_name], $date_params, $branch_params_pr));
            $prescriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) { error_log("Prescriptions error: " . $e->getMessage()); }
        
        // OTC SALES
        $otc_sales = [];
        try {
            $sql = "SELECT osi.id as item_id, osi.quantity, osi.unit_price, osi.total_price,
                        osi.item_name, osi.dosage, osi.frequency, osi.route,
                        os.id as sale_id, os.sale_number, os.customer_name, os.customer_phone,
                        os.payment_status, os.payment_method, os.created_at as sold_at,
                        u_seller.full_name as sold_by_name, u_seller.role as sold_by_role,
                        br.name as branch_name
                    FROM otc_sale_items osi
                    INNER JOIN otc_sales os ON osi.sale_id = os.id
                    LEFT JOIN users u_seller ON os.sold_by = u_seller.id
                    LEFT JOIN branches br ON os.branch_id = br.id
                    WHERE osi.item_name LIKE ?
                    $date_cond_otc
                    $branch_cond_o
                    ORDER BY os.created_at DESC
                    LIMIT 300";
            $stmt = $db->prepare($sql);
            $stmt->execute(array_merge(["%$med_name%"], $date_params, $branch_params_o));
            $otc_sales = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) { error_log("OTC error: " . $e->getMessage()); }
        
        // BILL ITEMS
        $bill_items = [];
        try {
            $sql = "SELECT bi.id, bi.bill_id, bi.item_name, bi.quantity, bi.unit_price, 
                        bi.total_price, bi.discount_amount, bi.status as item_status,
                        bi.created_at as item_created_at, bi.updated_at as item_updated_at,
                        b.bill_number, b.status as bill_status, b.payment_method, b.created_at as bill_created_at,
                        b.visit_id,
                        v.visit_number, v.visit_date, v.diagnosis, v.disease_code,
                        pat.full_name as patient_name, pat.patient_id as patient_code, pat.phone as patient_phone,
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
                    $date_cond_prescriptions
                    $branch_cond_bi
                    ORDER BY b.created_at DESC
                    LIMIT 300";
            $stmt = $db->prepare($sql);
            $stmt->execute(array_merge(["%$med_name%"], $date_params, $branch_params_bi));
            $bill_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) { error_log("Bill items error: " . $e->getMessage()); }
        
        // SUMMARIES
        $summary = [
            'purchase_qty' => 0, 'purchase_count' => 0,
            'pending_qty' => 0, 'pending_count' => 0,
            'confirmed_qty' => 0, 'confirmed_count' => 0,
            'dispensed_qty' => 0, 'dispensed_count' => 0,
            'otc_qty' => 0, 'otc_count' => 0,
            'paid_qty' => 0, 'paid_count' => 0,
            'total_movement' => 0,
            'unique_visits' => 0, 'unique_patients' => 0
        ];
        
        foreach ($purchase_history as $ph) { 
            $summary['purchase_qty'] += (int)$ph['quantity']; 
            $summary['purchase_count']++;
        }
        
        $visit_keys = [];
        $patient_keys = [];
        
        foreach ($prescriptions as $p) {
            $qty = (int)$p['quantity'];
            $status = strtolower($p['prescription_status'] ?? 'pending');
            
            if ($status === 'pending') {
                $summary['pending_qty'] += $qty;
                $summary['pending_count']++;
            } elseif ($status === 'confirmed') {
                $summary['confirmed_qty'] += $qty;
                $summary['confirmed_count']++;
            } elseif ($status === 'dispensed') {
                $summary['dispensed_qty'] += $qty;
                $summary['dispensed_count']++;
            }
            
            if (!empty($p['visit_id'])) $visit_keys[$p['visit_id']] = true;
            if (!empty($p['patient_code'])) $patient_keys[$p['patient_code']] = true;
        }
        
        foreach ($otc_sales as $os) { 
            $summary['otc_qty'] += (int)$os['quantity']; 
            $summary['otc_count']++;
        }
        
        foreach ($bill_items as $bi) {
            if ($bi['item_status'] === 'paid') {
                $summary['paid_qty'] += (int)$bi['quantity'];
                $summary['paid_count']++;
            }
            if (!empty($bi['visit_id'])) $visit_keys[$bi['visit_id']] = true;
            if (!empty($bi['patient_code'])) $patient_keys[$bi['patient_code']] = true;
        }
        
        $summary['total_movement'] = $summary['pending_qty'] + $summary['confirmed_qty'] + $summary['dispensed_qty'] + $summary['otc_qty'];
        $summary['unique_visits'] = count($visit_keys);
        $summary['unique_patients'] = count($patient_keys);
        
        // ✅ V5: REMAINING STOCK CALCULATIONS
        $stock_value = 0;
        $average_selling_price = 0;
        $price_count = 0;
        foreach ($current_stock as $cs) {
            $stock_value += (int)$cs['quantity'] * (float)($cs['selling_price'] ?? 0);
            if ((float)($cs['selling_price'] ?? 0) > 0) {
                $average_selling_price += (float)$cs['selling_price'];
                $price_count++;
            }
        }
        if ($price_count > 0) $average_selling_price = $average_selling_price / $price_count;
        
        $reorder_level = 0;
        if (!empty($current_stock)) {
            $reorder_level = (int)$current_stock[0]['reorder_level'];
        }
        
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
            'remaining_stock' => $remaining_stock
        ];
    }
}

// ================ EQUIPMENT DETAILS ================
if ($active_tab === 'equipment' && $selected_item_id > 0) {
    $stmt = $db->prepare("SELECT equipment_name FROM medical_equipment WHERE id = ?");
    $stmt->execute([$selected_item_id]);
    $eq_row = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($eq_row) {
        $eq_name = $eq_row['equipment_name'];
        
        // CURRENT STOCK
        $current_stock = [];
        try {
            $sql = "SELECT id, equipment_name, category, unit, quantity, reorder_level,
                        unit_cost, selling_price, supplier, expiry_date, batch_number,
                        branch_id, created_at, updated_at, added_by_name
                    FROM medical_equipment
                    WHERE equipment_name = ? AND status = 'active'
                    " . ($selected_branch_id !== 'all' ? " AND branch_id = ?" : "") . "
                    ORDER BY id ASC";
            $params = [$eq_name];
            if ($selected_branch_id !== 'all') $params[] = (int)$selected_branch_id;
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $current_stock = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {}
        
        $total_current_stock = 0;
        foreach ($current_stock as $cs) { $total_current_stock += (int)$cs['quantity']; }
        
        $equipment_info = !empty($current_stock) ? $current_stock[0] : [
            'equipment_name' => $eq_name,
            'category' => 'N/A', 'unit' => 'N/A', 'selling_price' => 0
        ];
        
        // PURCHASE HISTORY
        $equipment_ids = [];
        try {
            $stmt = $db->prepare("SELECT id FROM medical_equipment WHERE equipment_name = ?");
            $stmt->execute([$eq_name]);
            $equipment_ids = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'id');
        } catch (Exception $e) {}
        
        $purchase_history = [];
        if (!empty($equipment_ids)) {
            try {
                $placeholders = implode(',', array_fill(0, count($equipment_ids), '?'));
                $sql = "SELECT pi.id, pi.quantity, pi.buying_price, pi.selling_price,
                            pi.total_buying_cost, pi.total_selling_value,
                            pi.added_by, pi.added_by_name, pi.added_at,
                            p.invoice_number, p.purchase_type, p.created_at as purchase_created_at,
                            br.name as branch_name
                        FROM purchase_items pi
                        INNER JOIN purchases p ON pi.purchase_id = p.id
                        LEFT JOIN branches br ON p.branch_id = br.id
                        WHERE pi.item_type = 'equipment'
                        AND pi.item_id IN ($placeholders)
                        AND p.status = 'COMPLETED'
                        $date_cond_purchases
                        ORDER BY p.created_at DESC";
                $stmt = $db->prepare($sql);
                $stmt->execute(array_merge($equipment_ids, $date_params));
                $purchase_history = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (Exception $e) {}
        }
        
        // BILL ITEMS
        $bill_items = [];
        if (!empty($equipment_ids)) {
            try {
                $sql = "SELECT bi.id, bi.bill_id, bi.item_name, bi.item_id, bi.quantity, 
                            bi.unit_price, bi.total_price, bi.discount_amount, 
                            bi.status as item_status, bi.created_at as item_created_at,
                            b.bill_number, b.status as bill_status, b.payment_method,
                            b.visit_id,
                            v.visit_number, v.visit_date, v.diagnosis, v.disease_code,
                            pat.full_name as patient_name, pat.patient_id as patient_code, pat.phone as patient_phone,
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
                        $date_cond_prescriptions
                        $branch_cond_bi
                        ORDER BY b.created_at DESC
                        LIMIT 300";
                $stmt = $db->prepare($sql);
                $stmt->execute(array_merge($equipment_ids, $date_params, $branch_params_bi));
                $bill_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (Exception $e) {}
        }
        
        // LAB TESTS
        $lab_tests = [];
        if (!empty($equipment_ids)) {
            try {
                $sql = "SELECT lt.id as lab_test_id, lt.test_name, lt.test_price, lt.status as test_status,
                            lt.test_date, lt.created_at as test_created_at, lt.completed_at,
                            lt.started_at, lt.results, lt.branch_id, lt.visit_id,
                            v.visit_number, v.visit_date, v.diagnosis, v.disease_code,
                            pat.full_name as patient_name, pat.patient_id as patient_code, pat.phone as patient_phone,
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
                        $date_cond_lab
                        $branch_cond_lt
                        ORDER BY lt.created_at DESC
                        LIMIT 300";
                $stmt = $db->prepare($sql);
                $stmt->execute(array_merge($equipment_ids, $date_params, $branch_params_lt));
                $lab_tests = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (Exception $e) {}
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
            
            if ($status === 'pending') {
                $summary['pending_qty']++;
                $summary['pending_count']++;
            } elseif ($status === 'in_progress') {
                $summary['in_progress_qty']++;
                $summary['in_progress_count']++;
            } elseif ($status === 'completed') {
                $summary['completed_qty']++;
                $summary['completed_count']++;
            }
        }
        
        $visit_keys = [];
        $patient_keys = [];
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
        
        // ✅ V5: REMAINING STOCK CALCULATIONS
        $stock_value = 0;
        $average_selling_price = 0;
        $price_count = 0;
        foreach ($current_stock as $cs) {
            $stock_value += (int)$cs['quantity'] * (float)($cs['selling_price'] ?? 0);
            if ((float)($cs['selling_price'] ?? 0) > 0) {
                $average_selling_price += (float)$cs['selling_price'];
                $price_count++;
            }
        }
        if ($price_count > 0) $average_selling_price = $average_selling_price / $price_count;
        
        $reorder_level = 0;
        if (!empty($current_stock)) {
            $reorder_level = (int)$current_stock[0]['reorder_level'];
        }
        
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
        
        $equipment_details = [
            'name' => $eq_name,
            'info' => $equipment_info,
            'purchase_history' => $purchase_history,
            'current_stock' => $current_stock,
            'total_current_stock' => $total_current_stock,
            'bill_items' => $bill_items,
            'lab_tests' => $lab_tests,
            'summary' => $summary,
            'remaining_stock' => $remaining_stock
        ];
    }
}

$logo_path = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

include_once __DIR__ . '/../../../components/admin_audit_header.php';
include_once __DIR__ . '/../../../components/admin_audit_sidebar.php';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark' : 'light' ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Stock Movement Report • Braick Admin Audit</title>
<link rel="icon" href="<?= $logo_path ?>" type="image/png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=JetBrains+Mono:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
:root {
    --font-primary: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
    --font-mono: 'JetBrains Mono', 'Courier New', monospace;
    --primary: #0B5ED7; --primary-dark: #0A4CA8; --primary-light: #3B82F6; --primary-bg: #E8F0FE;
    --success: #059669; --success-bg: #D1FAE5;
    --danger: #DC2626; --danger-bg: #FEE2E2;
    --warning: #D97706; --warning-bg: #FEF3C7;
    --purple: #7C3AED; --purple-bg: #EDE9FE;
    --cyan: #0891B2; --cyan-bg: #CFFAFE;
    --teal: #0D9488; --teal-bg: #CCFBF1;
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
    --teal-bg: #0A2E2A; --slate-bg: #1E2A3D;
}
* { margin: 0; padding: 0; box-sizing: border-box; }
body { font-family: var(--font-primary); background: var(--bg-body); color: var(--text-primary); -webkit-font-smoothing: antialiased; line-height: 1.5; min-height: 100vh; }
.money-cell, .font-mono { font-family: var(--font-mono) !important; font-variant-numeric: tabular-nums; }

.page-header { background: linear-gradient(135deg, #0B5ED7 0%, #0A4CA8 50%, #7C3AED 100%); border-radius: var(--radius-lg); padding: 24px 28px; margin-bottom: 20px; display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 16px; box-shadow: 0 10px 40px rgba(11, 94, 215, 0.35); position: relative; overflow: hidden; }
.page-header::before { content: ''; position: absolute; top: -50%; right: -10%; width: 400px; height: 400px; background: radial-gradient(circle, rgba(255,255,255,0.12) 0%, transparent 70%); border-radius: 50%; pointer-events: none; }
.page-header .page-title { color: white; font-size: 1.5rem; font-weight: 900; display: flex; align-items: center; gap: 12px; flex-wrap: wrap; position: relative; z-index: 1; }
.page-header .page-title i { font-size: 1.6rem; color: #93C5FD; }
.branch-tag { background: rgba(255,255,255,0.15); color: white; padding: 4px 12px; border-radius: var(--radius-full); font-size: 0.68rem; font-weight: 600; display: inline-flex; align-items: center; gap: 5px; backdrop-filter: blur(10px); border: 1px solid rgba(255,255,255,0.15); }
.btn-header { background: rgba(255,255,255,0.15); color: white; border: 1px solid rgba(255,255,255,0.25); padding: 10px 16px; border-radius: var(--radius-sm); font-weight: 700; font-size: 0.75rem; transition: all 0.25s; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; backdrop-filter: blur(10px); cursor: pointer; }
.btn-header:hover { background: rgba(255,255,255,0.3); transform: translateY(-2px); }

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
.autocomplete-item:last-child { border-bottom: none; }
.autocomplete-item:hover { background: var(--primary-bg); }
.autocomplete-item .item-main { display: flex; align-items: center; gap: 10px; flex: 1; }
.autocomplete-item .item-icon { width: 34px; height: 34px; border-radius: 9px; background: linear-gradient(135deg, var(--primary), var(--primary-light)); color: white; display: flex; align-items: center; justify-content: center; font-size: 0.85rem; flex-shrink: 0; }
.autocomplete-item .item-name { font-weight: 800; font-size: 0.88rem; color: var(--text-primary); }
.autocomplete-item .item-meta { font-size: 0.68rem; color: var(--text-secondary); font-weight: 600; }
.autocomplete-item .item-stock { background: var(--success-bg); color: var(--success); padding: 4px 10px; border-radius: 8px; font-family: var(--font-mono); font-weight: 800; font-size: 0.75rem; white-space: nowrap; }
.autocomplete-item .item-stock.low { background: var(--danger-bg); color: var(--danger); }
.autocomplete-empty { padding: 20px; text-align: center; color: var(--text-secondary); font-size: 0.85rem; }

/* TOP 5 MOST USED */
.top5-section {
    background: linear-gradient(135deg, #FFFBEB, #FEF3C7);
    border: 2px solid #FCD34D;
    border-radius: var(--radius-lg);
    padding: 16px 20px;
    margin-top: 16px;
    box-shadow: 0 4px 16px rgba(217, 119, 6, 0.15);
}
[data-theme="dark"] .top5-section {
    background: linear-gradient(135deg, #3A2A0F, #4A3A12);
    border-color: #78350F;
}
.top5-section.equipment {
    background: linear-gradient(135deg, #EFF6FF, #DBEAFE);
    border-color: #93C5FD;
}
[data-theme="dark"] .top5-section.equipment {
    background: linear-gradient(135deg, #12294A, #1E3A5F);
    border-color: #1E40AF;
}
.top5-header { display: flex; align-items: center; gap: 10px; font-size: 0.85rem; font-weight: 900; color: #92400E; margin-bottom: 14px; text-transform: uppercase; letter-spacing: 0.04em; }
.top5-section.equipment .top5-header { color: #1E40AF; }
.top5-header i { font-size: 1.1rem; color: #D97706; animation: fireFlicker 1.5s infinite alternate; }
.top5-section.equipment .top5-header i { color: #0B5ED7; }
@keyframes fireFlicker { from { transform: scale(1) rotate(-3deg); } to { transform: scale(1.15) rotate(3deg); } }
.top5-badge { margin-left: auto; background: rgba(217, 119, 6, 0.15); color: #92400E; padding: 3px 10px; border-radius: 12px; font-size: 0.62rem; font-weight: 800; border: 1px solid rgba(217, 119, 6, 0.3); }
.top5-section.equipment .top5-badge { background: rgba(11, 94, 215, 0.15); color: #1E40AF; border-color: rgba(11, 94, 215, 0.3); }
.top5-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 10px; }
.top5-card { background: var(--bg-card); border: 2px solid #FCD34D; border-radius: 12px; padding: 12px 14px; display: flex; align-items: center; gap: 10px; text-decoration: none; color: var(--text-primary); transition: all 0.25s ease; position: relative; overflow: hidden; }
.top5-card:hover { transform: translateY(-3px); box-shadow: 0 8px 20px rgba(217, 119, 6, 0.25); border-color: #D97706; }
.top5-card.equipment:hover { box-shadow: 0 8px 20px rgba(11, 94, 215, 0.25); border-color: #0B5ED7; }
.top5-card.equipment { border-color: #93C5FD; }
.top5-card.active { background: linear-gradient(135deg, #FEF3C7, #FDE68A); border-color: #D97706; box-shadow: 0 4px 12px rgba(217, 119, 6, 0.3); }
.top5-card.equipment.active { background: linear-gradient(135deg, #DBEAFE, #BFDBFE); border-color: #0B5ED7; }
.top5-rank { position: absolute; top: 4px; right: 8px; font-size: 0.6rem; font-weight: 900; color: #D97706; opacity: 0.5; font-family: var(--font-mono); }
.top5-section.equipment .top5-rank { color: #0B5ED7; }
.top5-icon { width: 38px; height: 38px; border-radius: 10px; background: linear-gradient(135deg, #D97706, #F59E0B); color: white; display: flex; align-items: center; justify-content: center; font-size: 0.95rem; flex-shrink: 0; box-shadow: 0 3px 8px rgba(217, 119, 6, 0.3); }
.top5-card.equipment .top5-icon { background: linear-gradient(135deg, #0B5ED7, #3B82F6); box-shadow: 0 3px 8px rgba(11, 94, 215, 0.3); }
.top5-info { flex: 1; min-width: 0; }
.top5-name { font-weight: 800; font-size: 0.82rem; color: var(--text-primary); margin-bottom: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.top5-meta { font-size: 0.65rem; color: var(--text-secondary); font-weight: 700; display: flex; align-items: center; gap: 4px; }
.top5-meta i { color: #D97706; font-size: 0.6rem; }
.top5-card.equipment .top5-meta i { color: #0B5ED7; }

/* ✅ V5: REMAINING STOCK CARD */
.remaining-stock-card {
    background: var(--bg-card);
    border-radius: var(--radius-lg);
    padding: 20px 24px;
    margin-bottom: 20px;
    border: 2px solid var(--border-color);
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
    position: relative;
    overflow: hidden;
    transition: all 0.3s ease;
}
.remaining-stock-card::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 4px;
}
.remaining-stock-card.success {
    border-color: #34D399;
    background: linear-gradient(135deg, #ECFDF5, var(--bg-card) 70%);
}
.remaining-stock-card.success::before { background: linear-gradient(90deg, #059669, #34D399, #059669); }
.remaining-stock-card.warning {
    border-color: #FBBF24;
    background: linear-gradient(135deg, #FFFBEB, var(--bg-card) 70%);
}
.remaining-stock-card.warning::before { background: linear-gradient(90deg, #D97706, #FBBF24, #D97706); }
.remaining-stock-card.danger {
    border-color: #F87171;
    background: linear-gradient(135deg, #FEF2F2, var(--bg-card) 70%);
    animation: pulseDanger 2s infinite;
}
.remaining-stock-card.danger::before { background: linear-gradient(90deg, #DC2626, #F87171, #DC2626); }
@keyframes pulseDanger {
    0%, 100% { box-shadow: 0 4px 20px rgba(220, 38, 38, 0.15); }
    50% { box-shadow: 0 4px 30px rgba(220, 38, 38, 0.35); }
}

.rs-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 18px;
    padding-bottom: 14px;
    border-bottom: 2px dashed var(--border-color);
    flex-wrap: wrap;
    gap: 10px;
}
.rs-title {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 1.05rem;
    font-weight: 900;
    color: var(--text-primary);
    text-transform: uppercase;
    letter-spacing: 0.03em;
}
.rs-title i { color: var(--primary); font-size: 1.2rem; }
.remaining-stock-card.success .rs-title i { color: #059669; }
.remaining-stock-card.warning .rs-title i { color: #D97706; }
.remaining-stock-card.danger .rs-title i { color: #DC2626; }

.rs-status-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 14px;
    border-radius: var(--radius-full);
    font-size: 0.72rem;
    font-weight: 800;
    text-transform: uppercase;
    border: 2px solid;
}
.rs-status-badge.success { background: #D1FAE5; color: #059669; border-color: #34D399; }
.rs-status-badge.warning { background: #FEF3C7; color: #B45309; border-color: #FBBF24; }
.rs-status-badge.danger { background: #FEE2E2; color: #DC2626; border-color: #F87171; animation: blinkStatus 1.5s infinite; }
@keyframes blinkStatus { 0%, 100% { opacity: 1; } 50% { opacity: 0.6; } }

.rs-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 12px;
    margin-bottom: 16px;
}
.rs-item {
    background: var(--bg-card);
    border-radius: var(--radius-md);
    padding: 14px 16px;
    border: 2px solid var(--border-color);
    display: flex;
    align-items: center;
    gap: 12px;
    transition: all 0.25s ease;
    position: relative;
    overflow: hidden;
}
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

.rs-item-icon { width: 44px; height: 44px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; color: white; flex-shrink: 0; box-shadow: 0 4px 10px rgba(0,0,0,0.15); }
.rs-item-content { flex: 1; min-width: 0; }
.rs-item-label { font-size: 0.62rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.06em; color: var(--text-secondary); margin-bottom: 4px; }
.rs-item-value { font-family: var(--font-mono); font-size: 1.3rem; font-weight: 900; line-height: 1.1; display: flex; align-items: baseline; gap: 4px; }
.rs-item-value .rs-unit { font-size: 0.7rem; font-weight: 700; color: var(--text-secondary); font-family: var(--font-primary); }
.rs-item-sub { font-size: 0.65rem; font-weight: 600; color: var(--text-muted); margin-top: 4px; }

.rs-health-bar { background: var(--bg-body); border-radius: 12px; padding: 12px 16px; border: 1.5px solid var(--border-color); }
.rs-health-label { display: flex; justify-content: space-between; align-items: center; font-size: 0.7rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-secondary); margin-bottom: 8px; }
.rs-health-label i { color: var(--primary); margin-right: 4px; }
.rs-health-percent { font-family: var(--font-mono); font-weight: 900; font-size: 0.75rem; padding: 2px 10px; border-radius: 8px; background: var(--bg-card); color: var(--text-primary); }
.rs-bar-track { height: 10px; background: var(--border-color); border-radius: 10px; overflow: hidden; position: relative; }
.rs-bar-fill { height: 100%; border-radius: 10px; transition: width 0.8s ease; position: relative; overflow: hidden; }
.rs-bar-fill::after { content: ''; position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: linear-gradient(90deg, transparent, rgba(255,255,255,0.4), transparent); animation: shimmerBar 2s infinite; }
@keyframes shimmerBar { 0% { transform: translateX(-100%); } 100% { transform: translateX(100%); } }
.rs-bar-fill.success { background: linear-gradient(90deg, #059669, #34D399); }
.rs-bar-fill.warning { background: linear-gradient(90deg, #D97706, #FBBF24); }
.rs-bar-fill.danger { background: linear-gradient(90deg, #DC2626, #F87171); }

.details-container { animation: fadeInUp 0.5s ease; }
@keyframes fadeInUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }

.summary-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: 14px; margin-bottom: 20px; }
.summary-card { background: var(--bg-card); border-radius: var(--radius-lg); padding: 18px 20px; border: 2px solid var(--border-color); position: relative; overflow: hidden; transition: all 0.3s ease; }
.summary-card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px; }
.summary-card:hover { transform: translateY(-4px); box-shadow: var(--shadow-lg); }
.summary-card .sc-icon { width: 40px; height: 40px; border-radius: 11px; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; color: white; margin-bottom: 10px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); }
.summary-card .sc-label { font-size: 0.68rem; font-weight: 800; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.06em; margin-bottom: 4px; }
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
.summary-card.stock::before { background: linear-gradient(90deg, #0D9488, #14B8A6); }
.summary-card.stock .sc-icon { background: linear-gradient(135deg, #0D9488, #14B8A6); }
.summary-card.stock .sc-value { color: #0D9488; }
.summary-card.movement::before { background: linear-gradient(90deg, #DC2626, #F87171); }
.summary-card.movement .sc-icon { background: linear-gradient(135deg, #DC2626, #F87171); }
.summary-card.movement .sc-value { color: #DC2626; }

.table-card { background: var(--bg-card); border-radius: var(--radius-lg); border: 1px solid var(--border-color); overflow: hidden; box-shadow: var(--shadow-sm); margin-bottom: 20px; }
.table-card .table-header { padding: 14px 20px; background: linear-gradient(135deg, #0B5ED7, #0A4CA8); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; }
.table-card .table-header.green { background: linear-gradient(135deg, #059669, #047857); }
.table-card .table-header.cyan { background: linear-gradient(135deg, #0891B2, #0E7490); }
.table-card .table-header.purple { background: linear-gradient(135deg, #7C3AED, #6D28D9); }
.table-card .table-header.warning { background: linear-gradient(135deg, #D97706, #B45309); }
.table-card .table-header .title { color: white; font-size: 0.88rem; font-weight: 800; display: flex; align-items: center; gap: 10px; }
.table-card .table-header .count { color: rgba(255,255,255,0.95); font-size: 0.7rem; font-weight: 700; background: rgba(255,255,255,0.18); padding: 5px 12px; border-radius: var(--radius-full); }
.table-scroll-wrapper { overflow-x: auto; }
.data-table { width: 100%; border-collapse: collapse; font-size: 0.78rem; }
.data-table thead th { text-align: left; padding: 11px 14px; font-weight: 800; font-size: 0.62rem; text-transform: uppercase; letter-spacing: 0.08em; color: white; background: linear-gradient(135deg, #0B5ED7, #0A4CA8); white-space: nowrap; }
.data-table thead.green th { background: linear-gradient(135deg, #059669, #047857); }
.data-table thead.cyan th { background: linear-gradient(135deg, #0891B2, #0E7490); }
.data-table thead.purple th { background: linear-gradient(135deg, #7C3AED, #6D28D9); }
.data-table thead.warning th { background: linear-gradient(135deg, #D97706, #B45309); }
.data-table tbody td { padding: 11px 14px; border-bottom: 1px solid var(--border-color); color: var(--text-primary); vertical-align: middle; }
.data-table tbody tr:hover td { background: var(--primary-bg); }
.money-cell { font-family: var(--font-mono); font-weight: 800; color: var(--success); text-align: right; white-space: nowrap; }
.status-badge { display: inline-flex; align-items: center; gap: 4px; padding: 4px 10px; border-radius: var(--radius-full); font-size: 0.62rem; font-weight: 800; text-transform: uppercase; }
.status-badge.pending { background: var(--warning-bg); color: var(--warning); }
.status-badge.confirmed { background: var(--cyan-bg); color: var(--cyan); }
.status-badge.dispensed { background: var(--success-bg); color: var(--success); }
.status-badge.paid { background: var(--success-bg); color: var(--success); }
.status-badge.in_progress { background: var(--cyan-bg); color: var(--cyan); }
.status-badge.completed { background: var(--success-bg); color: var(--success); }
.status-badge.partial { background: var(--warning-bg); color: var(--warning); }

.empty-state { padding: 60px 20px; text-align: center; color: var(--text-secondary); }
.empty-state i { font-size: 3rem; opacity: 0.3; display: block; margin-bottom: 14px; color: var(--primary); }
.empty-state p { font-weight: 600; font-size: 0.9rem; }

.info-banner { background: linear-gradient(135deg, var(--primary-bg), transparent); border-left: 4px solid var(--primary); border-radius: var(--radius-md); padding: 16px 20px; margin-bottom: 20px; display: flex; align-items: center; gap: 14px; }
.info-banner .ib-icon { width: 52px; height: 52px; border-radius: 14px; background: linear-gradient(135deg, var(--primary), var(--primary-dark)); color: white; display: flex; align-items: center; justify-content: center; font-size: 1.4rem; flex-shrink: 0; }
.info-banner .ib-content { flex: 1; }
.info-banner .ib-title { font-size: 1.1rem; font-weight: 900; color: var(--text-primary); margin-bottom: 4px; }
.info-banner .ib-meta { font-size: 0.78rem; color: var(--text-secondary); display: flex; gap: 16px; flex-wrap: wrap; }
.info-banner .ib-meta span { display: inline-flex; align-items: center; gap: 5px; }

@media (max-width: 768px) {
    .summary-grid { grid-template-columns: 1fr 1fr; }
    .page-header .page-title { font-size: 1.2rem; }
    .tabs-container { flex-direction: column; }
    .top5-grid { grid-template-columns: 1fr; }
    .rs-grid { grid-template-columns: 1fr 1fr; }
    .rs-item-value { font-size: 1.1rem; }
}
@media (max-width: 480px) {
    .summary-grid { grid-template-columns: 1fr; }
    .rs-grid { grid-template-columns: 1fr; }
    .rs-header { flex-direction: column; align-items: stretch; }
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
                <span class="branch-tag"><i class="fas fa-store-alt"></i> <?= htmlspecialchars($branch_name_display) ?></span>
                <span class="branch-tag"><i class="fas fa-calendar"></i> <?= htmlspecialchars($date_label) ?></span>
            </h1>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;position:relative;z-index:1;">
            <button onclick="window.print()" class="btn-header"><i class="fas fa-print"></i> Print</button>
            <a href="dashboard.php?branch=<?= $selected_branch_id ?>" class="btn-header">
                <i class="fas fa-arrow-left"></i> Dashboard
            </a>
        </div>
    </div>

    <!-- TABS -->
    <div class="tabs-container">
        <a href="?branch=<?= $selected_branch_id ?>&tab=medicine&quick=<?= $quick_filter ?>" 
           class="tab-btn <?= $active_tab === 'medicine' ? 'active' : '' ?>">
            <i class="fas fa-pills"></i> MEDICINE TRACKING
        </a>
        <a href="?branch=<?= $selected_branch_id ?>&tab=equipment&quick=<?= $quick_filter ?>" 
           class="tab-btn <?= $active_tab === 'equipment' ? 'active' : '' ?>">
            <i class="fas fa-tools"></i> EQUIPMENT TRACKING
        </a>
    </div>

    <!-- FILTER CARD -->
    <div class="filter-card">
        <div class="filter-section-title"><i class="fas fa-bolt"></i> Quick Filters</div>
        <div class="quick-filters">
            <a href="?branch=<?= $selected_branch_id ?>&tab=<?= $active_tab ?>&quick=today<?= $selected_item_id ? '&item_id=' . $selected_item_id : '' ?>" class="quick-btn <?= $quick_filter === 'today' ? 'active' : '' ?>"><i class="fas fa-calendar-day"></i> Today</a>
            <a href="?branch=<?= $selected_branch_id ?>&tab=<?= $active_tab ?>&quick=1w<?= $selected_item_id ? '&item_id=' . $selected_item_id : '' ?>" class="quick-btn <?= $quick_filter === '1w' ? 'active' : '' ?>"><i class="fas fa-calendar-week"></i> 1W</a>
            <a href="?branch=<?= $selected_branch_id ?>&tab=<?= $active_tab ?>&quick=1m<?= $selected_item_id ? '&item_id=' . $selected_item_id : '' ?>" class="quick-btn <?= $quick_filter === '1m' ? 'active' : '' ?>"><i class="fas fa-calendar-alt"></i> 1M</a>
            <a href="?branch=<?= $selected_branch_id ?>&tab=<?= $active_tab ?>&quick=3m<?= $selected_item_id ? '&item_id=' . $selected_item_id : '' ?>" class="quick-btn <?= $quick_filter === '3m' ? 'active' : '' ?>"><i class="fas fa-calendar-alt"></i> 3M</a>
            <a href="?branch=<?= $selected_branch_id ?>&tab=<?= $active_tab ?>&quick=6m<?= $selected_item_id ? '&item_id=' . $selected_item_id : '' ?>" class="quick-btn <?= $quick_filter === '6m' ? 'active' : '' ?>"><i class="fas fa-calendar-alt"></i> 6M</a>
            <a href="?branch=<?= $selected_branch_id ?>&tab=<?= $active_tab ?>&quick=1y<?= $selected_item_id ? '&item_id=' . $selected_item_id : '' ?>" class="quick-btn <?= $quick_filter === '1y' ? 'active' : '' ?>"><i class="fas fa-calendar"></i> 1Y</a>
            <a href="?branch=<?= $selected_branch_id ?>&tab=<?= $active_tab ?>&quick=all<?= $selected_item_id ? '&item_id=' . $selected_item_id : '' ?>" class="quick-btn <?= $quick_filter === 'all' ? 'active' : '' ?>"><i class="fas fa-infinity"></i> All</a>
            <a href="#" onclick="toggleCustomDate(); return false;" class="quick-btn <?= $quick_filter === 'custom' ? 'active' : '' ?>"><i class="fas fa-calendar-check"></i> Custom</a>
        </div>

        <div id="customDateSection" style="display: <?= $quick_filter === 'custom' ? 'block' : 'none' ?>; margin-bottom: 16px; padding: 14px; background: var(--bg-body); border-radius: 12px;">
            <form method="GET" style="display: flex; gap: 12px; flex-wrap: wrap; align-items: flex-end;">
                <input type="hidden" name="branch" value="<?= htmlspecialchars($selected_branch_id) ?>">
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

        <!-- SEARCH BOX -->
        <div class="filter-section-title" style="margin-top: 10px;">
            <i class="fas fa-search"></i> Search <?= $active_tab === 'medicine' ? 'Medicine' : 'Equipment' ?>
        </div>
        <div class="search-wrapper">
            <form method="GET" id="searchForm">
                <input type="hidden" name="branch" value="<?= htmlspecialchars($selected_branch_id) ?>">
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
            <div style="margin-top: 12px; display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
                <a href="?branch=<?= $selected_branch_id ?>&tab=<?= $active_tab ?>&quick=<?= $quick_filter ?>" class="quick-btn" style="background: var(--danger-bg); color: var(--danger); border-color: var(--danger);">
                    <i class="fas fa-times"></i> Clear Selection
                </a>
            </div>
        <?php endif; ?>
    </div>

    <!-- ================================================================ -->
    <!-- TOP 5 MOST USED SECTION -->
    <!-- ================================================================ -->
    <?php if ($active_tab === 'medicine' && count($top5_medicines) > 0 && $selected_item_id === 0): ?>
    <div class="top5-section">
        <div class="top5-header">
            <i class="fas fa-fire"></i> Top 5 Most Used Medicines
            <span class="top5-badge"><?= htmlspecialchars($date_label) ?></span>
        </div>
        <div class="top5-grid">
            <?php foreach ($top5_medicines as $idx => $tm): ?>
                <a href="?branch=<?= $selected_branch_id ?>&tab=medicine&quick=<?= $quick_filter ?>&item_id=<?= (int)$tm['item_id'] ?>" 
                   class="top5-card">
                    <div class="top5-rank">#<?= $idx + 1 ?></div>
                    <div class="top5-icon"><i class="fas fa-pills"></i></div>
                    <div class="top5-info">
                        <div class="top5-name"><?= htmlspecialchars($tm['name']) ?></div>
                        <div class="top5-meta">
                            <i class="fas fa-chart-line"></i> <?= number_format($tm['total_qty']) ?> units used
                        </div>
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
            <span class="top5-badge"><?= htmlspecialchars($date_label) ?></span>
        </div>
        <div class="top5-grid">
            <?php foreach ($top5_equipment as $idx => $te): ?>
                <a href="?branch=<?= $selected_branch_id ?>&tab=equipment&quick=<?= $quick_filter ?>&item_id=<?= (int)$te['item_id'] ?>" 
                   class="top5-card equipment">
                    <div class="top5-rank">#<?= $idx + 1 ?></div>
                    <div class="top5-icon"><i class="fas fa-tools"></i></div>
                    <div class="top5-info">
                        <div class="top5-name"><?= htmlspecialchars($te['name']) ?></div>
                        <div class="top5-meta">
                            <i class="fas fa-chart-line"></i> <?= number_format($te['total_qty']) ?> uses
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- MEDICINE TAB -->
    <!-- ================================================================ -->
    <?php if ($active_tab === 'medicine'): ?>
        
        <?php if ($medicine_details): ?>
            <?php 
            $med = $medicine_details['info'];
            $sm = $medicine_details['summary'];
            $rs = $medicine_details['remaining_stock'];
            ?>
            
            <div class="details-container">
                
                <!-- INFO BANNER -->
                <div class="info-banner">
                    <div class="ib-icon"><i class="fas fa-pills"></i></div>
                    <div class="ib-content">
                        <div class="ib-title"><?= htmlspecialchars($medicine_details['name']) ?></div>
                        <div class="ib-meta">
                            <span><i class="fas fa-tag"></i> <?= htmlspecialchars($med['category'] ?? 'N/A') ?></span>
                            <span><i class="fas fa-flask"></i> Unit: <?= htmlspecialchars($med['unit'] ?? 'N/A') ?></span>
                            <span><i class="fas fa-money-bill"></i> Selling: <?= $currency ?> <?= number_format($med['selling_price'] ?? 0, 0) ?></span>
                        </div>
                    </div>
                </div>

                <!-- ✅ V5: REMAINING STOCK CARD -->
                <div class="remaining-stock-card <?= $rs['status_color'] ?>">
                    <div class="rs-header">
                        <div class="rs-title">
                            <i class="fas fa-boxes-packing"></i>
                            Remaining Stock
                        </div>
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
                                <div class="rs-item-sub">Rx + OTC</div>
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
                                <div class="rs-item-sub">
                                    <?= $rs['current_stock'] > $rs['reorder_level'] ? '✅ Above threshold' : '⚠️ Below threshold' ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Stock Health Bar -->
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
                        <div class="sc-label">Purchased (Added)</div>
                        <div class="sc-value"><?= number_format($sm['purchase_qty']) ?></div>
                        <div class="sc-sub"><?= $sm['purchase_count'] ?> purchase records</div>
                    </div>
                    
                    <div class="summary-card stock">
                        <div class="sc-icon"><i class="fas fa-warehouse"></i></div>
                        <div class="sc-label">Current Stock</div>
                        <div class="sc-value"><?= number_format($medicine_details['total_current_stock']) ?></div>
                        <div class="sc-sub"><?= count($medicine_details['current_stock']) ?> batches</div>
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
                                <tr>
                                    <th>#</th>
                                    <th>Invoice #</th>
                                    <th>Qty Added</th>
                                    <th style="text-align:right;">Buying Price</th>
                                    <th style="text-align:right;">Selling Price</th>
                                    <th style="text-align:right;">Total Cost</th>
                                    <th>Added By</th>
                                    <th>Branch</th>
                                    <th>Date</th>
                                </tr>
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
                                    <td><?= htmlspecialchars($ph['branch_name'] ?? 'N/A') ?></td>
                                    <td><?= date('d M Y, H:i', strtotime($ph['added_at'])) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="empty-state"><i class="fas fa-cart-plus"></i><p>No purchase records found for this period</p></div>
                    <?php endif; ?>
                </div>

                <!-- CURRENT STOCK -->
                <div class="table-card">
                    <div class="table-header cyan">
                        <span class="title"><i class="fas fa-warehouse"></i> Current Stock (Batches)</span>
                        <span class="count"><?= number_format($medicine_details['total_current_stock']) ?> units in <?= count($medicine_details['current_stock']) ?> batches</span>
                    </div>
                    <?php if (count($medicine_details['current_stock']) > 0): ?>
                    <div class="table-scroll-wrapper">
                        <table class="data-table">
                            <thead class="cyan">
                                <tr>
                                    <th>#</th>
                                    <th>Batch #</th>
                                    <th>Qty</th>
                                    <th>Reorder Lvl</th>
                                    <th style="text-align:right;">Selling Price</th>
                                    <th>Expiry</th>
                                    <th>Supplier</th>
                                    <th>Added</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $i=1; foreach ($medicine_details['current_stock'] as $cs): 
                                    $is_low = (int)$cs['quantity'] <= (int)$cs['reorder_level'];
                                ?>
                                <tr>
                                    <td><?= $i++ ?></td>
                                    <td><span class="font-mono" style="font-weight:700;font-size:0.72rem;"><?= htmlspecialchars($cs['batch_number'] ?? 'N/A') ?></span></td>
                                    <td><span style="background:<?= $is_low ? 'var(--danger-bg)' : 'var(--success-bg)' ?>;color:<?= $is_low ? 'var(--danger)' : 'var(--success)' ?>;padding:3px 10px;border-radius:6px;font-weight:800;font-family:var(--font-mono);"><?= number_format($cs['quantity']) ?></span></td>
                                    <td style="font-family:var(--font-mono);font-weight:700;"><?= number_format($cs['reorder_level']) ?></td>
                                    <td class="money-cell"><?= $currency ?> <?= number_format($cs['selling_price'] ?? 0, 0) ?></td>
                                    <td><?= !empty($cs['expiry_date']) && $cs['expiry_date'] !== '0000-00-00' ? date('d M Y', strtotime($cs['expiry_date'])) : 'N/A' ?></td>
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
                        <span class="title"><i class="fas fa-prescription"></i> Prescriptions (Visit-based)</span>
                        <span class="count"><?= count($medicine_details['prescriptions']) ?> prescriptions • Total: <?= number_format($sm['pending_qty'] + $sm['confirmed_qty'] + $sm['dispensed_qty']) ?> units</span>
                    </div>
                    <?php if (count($medicine_details['prescriptions']) > 0): ?>
                    <div class="table-scroll-wrapper">
                        <table class="data-table">
                            <thead class="purple">
                                <tr>
                                    <th>#</th>
                                    <th>Prescription #</th>
                                    <th>Visit #</th>
                                    <th>Patient</th>
                                    <th>Doctor</th>
                                    <th>Qty</th>
                                    <th>Diagnosis</th>
                                    <th>Branch</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $i=1; foreach ($medicine_details['prescriptions'] as $pr): 
                                    $pr_status = strtolower($pr['prescription_status'] ?? 'pending');
                                ?>
                                <tr>
                                    <td><?= $i++ ?></td>
                                    <td><span class="font-mono" style="font-weight:800;font-size:0.7rem;color:var(--purple);"><?= htmlspecialchars($pr['prescription_number'] ?? 'N/A') ?></span></td>
                                    <td><span class="font-mono" style="font-weight:700;color:var(--primary);"><?= htmlspecialchars($pr['visit_number'] ?? 'N/A') ?></span></td>
                                    <td>
                                        <div style="font-weight:700;"><?= htmlspecialchars($pr['patient_name'] ?? 'N/A') ?></div>
                                        <div style="font-size:0.68rem;color:var(--text-secondary);"><?= htmlspecialchars($pr['patient_code'] ?? '') ?> • <?= htmlspecialchars($pr['patient_phone'] ?? '') ?></div>
                                    </td>
                                    <td><?= htmlspecialchars($pr['doctor_name'] ?? 'N/A') ?></td>
                                    <td><span style="background:var(--purple-bg);color:var(--purple);padding:3px 10px;border-radius:6px;font-weight:800;font-family:var(--font-mono);"><?= number_format($pr['quantity']) ?></span></td>
                                    <td style="font-size:0.72rem;"><?= htmlspecialchars($pr['diagnosis'] ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars($pr['branch_name'] ?? 'N/A') ?></td>
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
                    <div class="empty-state"><i class="fas fa-prescription"></i><p>No prescriptions found for this period</p></div>
                    <?php endif; ?>
                </div>

                <!-- OTC SALES -->
                <div class="table-card">
                    <div class="table-header green">
                        <span class="title"><i class="fas fa-cash-register"></i> OTC Sales (Over-The-Counter)</span>
                        <span class="count"><?= count($medicine_details['otc_sales']) ?> sales • Total: <?= number_format($sm['otc_qty']) ?> units</span>
                    </div>
                    <?php if (count($medicine_details['otc_sales']) > 0): ?>
                    <div class="table-scroll-wrapper">
                        <table class="data-table">
                            <thead class="green">
                                <tr>
                                    <th>#</th>
                                    <th>Sale #</th>
                                    <th>Customer</th>
                                    <th>Qty</th>
                                    <th style="text-align:right;">Unit Price</th>
                                    <th style="text-align:right;">Total</th>
                                    <th>Sold By</th>
                                    <th>Branch</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                </tr>
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
                                    <td class="money-cell"><?= $currency ?> <?= number_format($os['unit_price'] ?? 0, 0) ?></td>
                                    <td class="money-cell"><?= $currency ?> <?= number_format($os['total_price'] ?? 0, 0) ?></td>
                                    <td><?= htmlspecialchars($os['sold_by_name'] ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars($os['branch_name'] ?? 'N/A') ?></td>
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
                    <div class="empty-state"><i class="fas fa-cash-register"></i><p>No OTC sales found for this period</p></div>
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
                    <p>Search medicine above (e.g. ALBENDAZOLE, Paracetamol) or click on Top 5 cards</p>
                    <p style="font-size:0.78rem;margin-top:8px;color:var(--text-muted);">You will see: Remaining Stock • Purchase History • Current Stock • Prescriptions • OTC Sales</p>
                </div>
            </div>
        <?php endif; ?>

    <!-- ================================================================ -->
    <!-- EQUIPMENT TAB -->
    <!-- ================================================================ -->
    <?php else: ?>
        
        <?php if ($equipment_details): ?>
            <?php 
            $eq = $equipment_details['info'];
            $sm = $equipment_details['summary'];
            $rs = $equipment_details['remaining_stock'];
            ?>
            
            <div class="details-container">
                
                <!-- INFO BANNER -->
                <div class="info-banner">
                    <div class="ib-icon"><i class="fas fa-tools"></i></div>
                    <div class="ib-content">
                        <div class="ib-title"><?= htmlspecialchars($equipment_details['name']) ?></div>
                        <div class="ib-meta">
                            <span><i class="fas fa-tag"></i> <?= htmlspecialchars($eq['category'] ?? 'N/A') ?></span>
                            <span><i class="fas fa-box"></i> Unit: <?= htmlspecialchars($eq['unit'] ?? 'N/A') ?></span>
                            <span><i class="fas fa-money-bill"></i> Selling: <?= $currency ?> <?= number_format($eq['selling_price'] ?? 0, 0) ?></span>
                        </div>
                    </div>
                </div>

                <!-- ✅ V5: REMAINING STOCK CARD -->
                <div class="remaining-stock-card <?= $rs['status_color'] ?>">
                    <div class="rs-header">
                        <div class="rs-title">
                            <i class="fas fa-boxes-packing"></i>
                            Remaining Stock
                        </div>
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
                                <div class="rs-item-sub">Lab Tests + Visits</div>
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
                                <div class="rs-item-sub">
                                    <?= $rs['current_stock'] > $rs['reorder_level'] ? '✅ Above threshold' : '⚠️ Below threshold' ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Stock Health Bar -->
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
                        <div class="sc-label">Purchased (Added)</div>
                        <div class="sc-value"><?= number_format($sm['purchase_qty']) ?></div>
                        <div class="sc-sub"><?= $sm['purchase_count'] ?> purchase records</div>
                    </div>
                    
                    <div class="summary-card stock">
                        <div class="sc-icon"><i class="fas fa-warehouse"></i></div>
                        <div class="sc-label">Current Stock</div>
                        <div class="sc-value"><?= number_format($equipment_details['total_current_stock']) ?></div>
                        <div class="sc-sub"><?= count($equipment_details['current_stock']) ?> batches</div>
                    </div>
                    
                    <div class="summary-card pending">
                        <div class="sc-icon"><i class="fas fa-clock"></i></div>
                        <div class="sc-label">Pending Lab Tests</div>
                        <div class="sc-value"><?= number_format($sm['pending_qty']) ?></div>
                        <div class="sc-sub"><?= $sm['pending_count'] ?> tests</div>
                    </div>
                    
                    <div class="summary-card confirmed">
                        <div class="sc-icon"><i class="fas fa-spinner"></i></div>
                        <div class="sc-label">In Progress Tests</div>
                        <div class="sc-value"><?= number_format($sm['in_progress_qty']) ?></div>
                        <div class="sc-sub"><?= $sm['in_progress_count'] ?> tests</div>
                    </div>
                    
                    <div class="summary-card dispensed">
                        <div class="sc-icon"><i class="fas fa-check-circle"></i></div>
                        <div class="sc-label">Completed Tests</div>
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
                        <span class="title"><i class="fas fa-cart-plus"></i> Purchase History (Stock In)</span>
                        <span class="count"><?= count($equipment_details['purchase_history']) ?> records • <?= number_format($sm['purchase_qty']) ?> units</span>
                    </div>
                    <?php if (count($equipment_details['purchase_history']) > 0): ?>
                    <div class="table-scroll-wrapper">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Invoice #</th>
                                    <th>Qty Added</th>
                                    <th style="text-align:right;">Buying Price</th>
                                    <th style="text-align:right;">Selling Price</th>
                                    <th style="text-align:right;">Total Cost</th>
                                    <th>Added By</th>
                                    <th>Branch</th>
                                    <th>Date</th>
                                </tr>
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
                                    <td><?= htmlspecialchars($ph['branch_name'] ?? 'N/A') ?></td>
                                    <td><?= date('d M Y, H:i', strtotime($ph['added_at'])) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="empty-state"><i class="fas fa-cart-plus"></i><p>No purchase records found for this period</p></div>
                    <?php endif; ?>
                </div>

                <!-- CURRENT STOCK -->
                <div class="table-card">
                    <div class="table-header cyan">
                        <span class="title"><i class="fas fa-warehouse"></i> Current Stock</span>
                        <span class="count"><?= number_format($equipment_details['total_current_stock']) ?> units available</span>
                    </div>
                    <?php if (count($equipment_details['current_stock']) > 0): ?>
                    <div class="table-scroll-wrapper">
                        <table class="data-table">
                            <thead class="cyan">
                                <tr>
                                    <th>#</th>
                                    <th>Equipment</th>
                                    <th>Qty</th>
                                    <th>Reorder Lvl</th>
                                    <th style="text-align:right;">Selling Price</th>
                                    <th>Supplier</th>
                                    <th>Added</th>
                                </tr>
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
                        <span class="title"><i class="fas fa-prescription-bottle"></i> Used in Visits (Bill Items)</span>
                        <span class="count"><?= count($equipment_details['bill_items']) ?> bill items • <?= number_format($sm['bill_qty']) ?> units</span>
                    </div>
                    <?php if (count($equipment_details['bill_items']) > 0): ?>
                    <div class="table-scroll-wrapper">
                        <table class="data-table">
                            <thead class="green">
                                <tr>
                                    <th>#</th>
                                    <th>Bill #</th>
                                    <th>Visit #</th>
                                    <th>Patient</th>
                                    <th>Doctor</th>
                                    <th>Qty</th>
                                    <th>Diagnosis</th>
                                    <th>Branch</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $i=1; foreach ($equipment_details['bill_items'] as $bi): 
                                    $bi_status = strtolower($bi['item_status'] ?? 'pending');
                                ?>
                                <tr>
                                    <td><?= $i++ ?></td>
                                    <td><span class="font-mono" style="font-weight:700;font-size:0.7rem;color:var(--cyan);"><?= htmlspecialchars($bi['bill_number'] ?? 'N/A') ?></span></td>
                                    <td><span class="font-mono" style="font-weight:700;color:var(--primary);"><?= htmlspecialchars($bi['visit_number'] ?? 'N/A') ?></span></td>
                                    <td>
                                        <div style="font-weight:700;"><?= htmlspecialchars($bi['patient_name'] ?? 'N/A') ?></div>
                                        <div style="font-size:0.68rem;color:var(--text-secondary);"><?= htmlspecialchars($bi['patient_code'] ?? '') ?> • <?= htmlspecialchars($bi['patient_phone'] ?? '') ?></div>
                                    </td>
                                    <td><?= htmlspecialchars($bi['doctor_name'] ?? 'N/A') ?></td>
                                    <td><span style="background:var(--cyan-bg);color:var(--cyan);padding:3px 10px;border-radius:6px;font-weight:800;font-family:var(--font-mono);"><?= number_format($bi['quantity']) ?></span></td>
                                    <td style="font-size:0.72rem;"><?= htmlspecialchars($bi['diagnosis'] ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars($bi['branch_name'] ?? 'N/A') ?></td>
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
                    <div class="empty-state"><i class="fas fa-prescription-bottle"></i><p>No bill items found for this period</p></div>
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
                                <tr>
                                    <th>#</th>
                                    <th>Visit #</th>
                                    <th>Patient</th>
                                    <th>Test Name</th>
                                    <th>Doctor</th>
                                    <th>Lab Tech</th>
                                    <th>Diagnosis</th>
                                    <th>Branch</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $i=1; foreach ($equipment_details['lab_tests'] as $lt): 
                                    $lt_status = strtolower($lt['test_status'] ?? 'pending');
                                ?>
                                <tr>
                                    <td><?= $i++ ?></td>
                                    <td><span class="font-mono" style="font-weight:700;color:var(--primary);"><?= htmlspecialchars($lt['visit_number'] ?? 'N/A') ?></span></td>
                                    <td>
                                        <div style="font-weight:700;"><?= htmlspecialchars($lt['patient_name'] ?? 'N/A') ?></div>
                                        <div style="font-size:0.68rem;color:var(--text-secondary);"><?= htmlspecialchars($lt['patient_code'] ?? '') ?> • <?= htmlspecialchars($lt['patient_phone'] ?? '') ?></div>
                                    </td>
                                    <td><?= htmlspecialchars($lt['test_name'] ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars($lt['doctor_name'] ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars($lt['lab_tech_name'] ?? 'N/A') ?></td>
                                    <td style="font-size:0.72rem;"><?= htmlspecialchars($lt['diagnosis'] ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars($lt['branch_name'] ?? 'N/A') ?></td>
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
                    <div class="empty-state"><i class="fas fa-flask"></i><p>No lab tests linked to this equipment</p></div>
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
                    <p>Search equipment above (e.g. ECG Machine, Blood Pressure Monitor, SINDANO) or click on Top 5 cards</p>
                    <p style="font-size:0.78rem;margin-top:8px;color:var(--text-muted);">You will see: Remaining Stock • Purchase History • Current Stock • Bill Items • Lab Tests</p>
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

// Live Search Autocomplete
(function() {
    var searchInput = document.getElementById('searchInput');
    var autocompleteBox = document.getElementById('autocompleteBox');
    var itemIdInput = document.getElementById('itemIdInput');
    var searchForm = document.getElementById('searchForm');
    var activeTab = '<?= $active_tab ?>';
    var branchId = '<?= $selected_branch_id ?>';
    
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
            fetch('?ajax=search&type=' + activeTab + '&q=' + encodeURIComponent(q) + '&branch=' + branchId)
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