<?php
// ================================================================
// FILE: frontend/pages/admin/audit/stock_movement.php
// ADMIN - STOCK MOVEMENT REPORT V8.6 (FULLY CORRECTED)
// ================================================================
// ✅ V8.6 FIX #1: Formula inajumuisha cancelled_out kwenye OUT
// ✅ V8.6 FIX #2: getStockBefore() inatumia item-level (all batches)
// ✅ V8.6 FIX #3: stock_after = total_current_stock (consistent)
// ✅ V8.6 FIX #4: Summary cards zinaonyesha cancelled_out
// ✅ V8.6 FIX #5: Cancellation verification kwa DB
// ✅ V8.6 FIX #6: No double-counting
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

// ================================================================
// BRANCHES
// ================================================================
$selected_branch_id = $_GET['branch'] ?? 'all';
$branch_name_display = 'All Branches';
if ($selected_branch_id !== 'all' && is_numeric($selected_branch_id)) {
    $stmt = $db->prepare("SELECT name FROM branches WHERE id = ? AND status = 'active'");
    $stmt->execute([(int)$selected_branch_id]);
    $branch_data = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($branch_data) $branch_name_display = $branch_data['name'];
}

$branches = [];
try {
    $stmt = $db->query("SELECT id, name FROM branches WHERE status = 'active' ORDER BY name");
    $branches = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// FILTERS
$active_tab = $_GET['tab'] ?? 'medicine';
$quick_filter = $_GET['quick'] ?? 'today';
$search = trim($_GET['search'] ?? '');
$selected_item_id = (int)($_GET['item_id'] ?? 0);
$date_from = $_GET['date_from'] ?? date('Y-m-d');
$date_to = $_GET['date_to'] ?? date('Y-m-d');

// DATE RANGE
$filter_date_from = date('Y-m-d');
$filter_date_to = date('Y-m-d');
$date_label = "Today • " . date('d M Y');

switch ($quick_filter) {
    case 'today':
        $filter_date_from = date('Y-m-d');
        $filter_date_to = date('Y-m-d');
        $date_label = "Today • " . date('d M Y');
        break;
    case 'yesterday':
        $filter_date_from = date('Y-m-d', strtotime('-1 day'));
        $filter_date_to = date('Y-m-d', strtotime('-1 day'));
        $date_label = "Yesterday • " . date('d M Y', strtotime('-1 day'));
        break;
    case '1w':
        $filter_date_from = date('Y-m-d', strtotime('-7 days'));
        $filter_date_to = date('Y-m-d');
        $date_label = "Last 7 Days";
        break;
    case '1m':
        $filter_date_from = date('Y-m-d', strtotime('-1 month'));
        $filter_date_to = date('Y-m-d');
        $date_label = "Last 1 Month";
        break;
    case '3m':
        $filter_date_from = date('Y-m-d', strtotime('-3 months'));
        $filter_date_to = date('Y-m-d');
        $date_label = "Last 3 Months";
        break;
    case '1y':
        $filter_date_from = date('Y-m-d', strtotime('-1 year'));
        $filter_date_to = date('Y-m-d');
        $date_label = "Last 1 Year";
        break;
    case 'custom':
        $filter_date_from = $date_from;
        $filter_date_to = $date_to;
        $date_label = date('d M Y', strtotime($date_from)) . ' → ' . date('d M Y', strtotime($date_to));
        break;
    case 'all':
    default:
        try {
            if ($selected_branch_id !== 'all') {
                $stmt = $db->prepare("SELECT MIN(DATE(created_at)) as min_date FROM stock_movements WHERE branch_id = ?");
                $stmt->execute([(int)$selected_branch_id]);
            } else {
                $stmt = $db->query("SELECT MIN(DATE(created_at)) as min_date FROM stock_movements");
            }
            $min_row = $stmt->fetch(PDO::FETCH_ASSOC);
            $filter_date_from = $min_row['min_date'] ?? date('Y-m-d', strtotime('-1 year'));
        } catch (Exception $e) {
            $filter_date_from = date('Y-m-d', strtotime('-1 year'));
        }
        $filter_date_to = date('Y-m-d');
        $date_label = "All Time";
        break;
}

$date_from_sql = $filter_date_from . ' 00:00:00';
$date_to_sql = $filter_date_to . ' 23:59:59';

// Branch condition
$branch_cond_sm = "";
$branch_params_sm = [];
if ($selected_branch_id !== 'all') {
    $branch_cond_sm = " AND sm.branch_id = ?";
    $branch_params_sm = [(int)$selected_branch_id];
}

// ================================================================
// HELPERS
// ================================================================
function formatDate($datetime) {
    if (empty($datetime)) return 'N/A';
    return date('d M Y, H:i', strtotime($datetime));
}

/**
 * ✅ V8.6: Verify kama movement ni "cancelled return" ya kweli
 */
function verifyCancelledReturn($db, $reference_id, $patient_id, $medication_name = '') {
    if (empty($reference_id) || empty($patient_id)) {
        return false;
    }
    
    try {
        $sql = "SELECT COUNT(*) as cnt 
                FROM prescription_items 
                WHERE prescription_id = ? 
                  AND patient_id = ? 
                  AND cancelled_at IS NOT NULL";
        $params = [$reference_id, $patient_id];
        
        if (!empty($medication_name)) {
            $sql .= " AND medication_name = ?";
            $params[] = $medication_name;
        }
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return ((int)($row['cnt'] ?? 0) > 0);
    } catch (Exception $e) {
        error_log("verifyCancelledReturn: " . $e->getMessage());
        return false;
    }
}

/**
 * ✅ V8.6: Verify kama OUT movement ni prescription iliyocancelled
 */
function isOutCancelled($db, $reference_id, $patient_id, $medication_name = '') {
    if (empty($reference_id) || empty($patient_id)) {
        return false;
    }
    
    try {
        $sql = "SELECT COUNT(*) as cnt 
                FROM prescription_items 
                WHERE prescription_id = ? 
                  AND patient_id = ? 
                  AND cancelled_at IS NOT NULL";
        $params = [$reference_id, $patient_id];
        
        if (!empty($medication_name)) {
            $sql .= " AND medication_name = ?";
            $params[] = $medication_name;
        }
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return ((int)($row['cnt'] ?? 0) > 0);
    } catch (Exception $e) {
        error_log("isOutCancelled: " . $e->getMessage());
        return false;
    }
}

/**
 * ✅ V8.6: Category detection — inaverify kwa DB
 */
function getMovementCategory($notes, $movement_type, $reference_type = '', $is_verified_cancel = false, $is_out_cancelled = false) {
    $mt = strtolower($movement_type);
    
    $ref_map = [
        'prescription' => 'prescription',
        'otc' => 'otc',
        'lab_test' => 'lab_test',
        'procedure' => 'procedure',
        'doctor_use' => 'doctor_use',
        'equipment' => 'equipment',
        'cancelled_item' => 'cancel',
        'prescription_cancel' => 'cancel',
    ];
    
    if (!empty($reference_type) && isset($ref_map[$reference_type])) {
        if ($mt === 'in' && $reference_type === 'cancelled_item') {
            return $is_verified_cancel ? 'cancel' : 'unverified_return';
        }
        return $ref_map[$reference_type];
    }
    
    if ($mt === 'out' && $is_out_cancelled) {
        return 'cancelled_out';
    }
    
    if (!empty($notes) && stripos($notes, 'Stock returned') === 0) {
        if ($mt === 'in') {
            return $is_verified_cancel ? 'cancel' : 'unverified_return';
        }
    }
    
    if (empty($notes)) return $mt;
    
    if (stripos($notes, 'Prescribed by Dr.') === 0) return 'prescription';
    if (stripos($notes, 'Prescription:') === 0) return 'prescription';
    if (stripos($notes, 'Auto-dispensed') === 0) return 'prescription';
    if (stripos($notes, 'Doctor used') === 0) return 'doctor_use';
    if (stripos($notes, 'Lab test') === 0) return 'lab_test';
    if (stripos($notes, 'OTC Sale') === 0) return 'otc';
    if (stripos($notes, 'OTC Equipment Sale') === 0) return 'otc';
    if (stripos($notes, 'Procedure:') === 0) return 'procedure';
    if (stripos($notes, 'Equipment:') === 0) return 'equipment';
    
    return $mt;
}

function getTopLabel($quick_filter) {
    switch ($quick_filter) {
        case 'today': return 'Today • ' . date('d M Y');
        case 'yesterday': return 'Yesterday • ' . date('d M Y', strtotime('-1 day'));
        case '1w': return 'Last 7 Days';
        case '1m': return 'Last 30 Days';
        case '3m': return 'Last 3 Months';
        case '1y': return 'Last 1 Year';
        case 'all': return 'All Time';
        case 'custom': return 'Custom Period';
        default: return 'Today';
    }
}

/**
 * ✅ V8.6: Stock Before = Current - IN + OUT (item-level, all batches combined)
 */
function getStockBefore($db, $item_type, $item_ids, $date_from_sql, $branch_cond_sm, $branch_params_sm) {
    if (empty($item_ids)) return 0;
    
    $id_field = $item_type === 'medicine' ? 'inventory_id' : 'equipment_id';
    $placeholders = implode(',', array_fill(0, count($item_ids), '?'));
    
    try {
        // STEP #1: Current stock (SUM ya batches zote)
        if ($item_type === 'medicine') {
            $sql_current = "SELECT COALESCE(SUM(quantity), 0) as current_stock 
                            FROM medications_inventory 
                            WHERE id IN ($placeholders) AND status = 'active'";
        } else {
            $sql_current = "SELECT COALESCE(SUM(quantity), 0) as current_stock 
                            FROM medical_equipment 
                            WHERE id IN ($placeholders) AND status = 'active'";
        }
        $stmt = $db->prepare($sql_current);
        $stmt->execute($item_ids);
        $current_stock = (int)($stmt->fetch(PDO::FETCH_ASSOC)['current_stock'] ?? 0);
        
        // STEP #2: Total IN/OUT tangu period start
        $sql_flow = "SELECT 
                        COALESCE(SUM(CASE WHEN sm.movement_type = 'in' THEN sm.quantity ELSE 0 END), 0) as total_in,
                        COALESCE(SUM(CASE WHEN sm.movement_type = 'out' THEN sm.quantity ELSE 0 END), 0) as total_out
                    FROM stock_movements sm
                    WHERE sm.$id_field IN ($placeholders)
                      AND sm.created_at >= ?
                      $branch_cond_sm";
        $params = array_merge($item_ids, [$date_from_sql], $branch_params_sm);
        $stmt = $db->prepare($sql_flow);
        $stmt->execute($params);
        $flow = $stmt->fetch(PDO::FETCH_ASSOC);
        $total_in = (int)($flow['total_in'] ?? 0);
        $total_out = (int)($flow['total_out'] ?? 0);
        
        // ✅ Stock Before = Current - IN + OUT
        $stock_before = $current_stock - $total_in + $total_out;
        
        return max(0, $stock_before);
        
    } catch (Exception $e) {
        error_log("getStockBefore: " . $e->getMessage());
        return 0;
    }
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
// TOP 5
// ================================================================
$top5_medicines = [];
$top5_equipment = [];
$top5_label = getTopLabel($quick_filter);

// TOP 5 MEDICINES
try {
    $sql = "SELECT 
                sm.inventory_id as item_id,
                SUM(sm.quantity) as total_qty
            FROM stock_movements sm
            WHERE sm.movement_type = 'out'
              AND sm.inventory_id IS NOT NULL
              AND sm.created_at BETWEEN ? AND ?
              $branch_cond_sm
              AND (sm.reference_type = 'prescription' 
                   OR sm.reference_type = 'otc'
                   OR sm.notes LIKE 'Prescribed by Dr.%'
                   OR sm.notes LIKE 'Auto-dispensed%'
                   OR sm.notes LIKE 'Prescription:%'
                   OR sm.notes LIKE 'OTC Sale%')
            GROUP BY sm.inventory_id
            ORDER BY total_qty DESC
            LIMIT 5";

    $params = [$date_from_sql, $date_to_sql];
    $params = array_merge($params, $branch_params_sm);
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $raw = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($raw as $r) {
        $stmt2 = $db->prepare("SELECT medication_name FROM medications_inventory WHERE id = ? LIMIT 1");
        $stmt2->execute([$r['item_id']]);
        $inv_row = $stmt2->fetch(PDO::FETCH_ASSOC);

        if ($inv_row) {
            $top5_medicines[] = [
                'name' => $inv_row['medication_name'],
                'total_qty' => $r['total_qty'],
                'item_id' => $r['item_id']
            ];
        }
    }
} catch (Exception $e) { error_log("Top 5 med: " . $e->getMessage()); }

// TOP 5 EQUIPMENT
try {
    $sql = "SELECT 
                sm.equipment_id,
                SUM(sm.quantity) as total_qty,
                me.equipment_name
            FROM stock_movements sm
            INNER JOIN medical_equipment me ON sm.equipment_id = me.id
            WHERE sm.movement_type = 'out'
              AND sm.equipment_id IS NOT NULL
              AND sm.created_at BETWEEN ? AND ?
              $branch_cond_sm
              AND (sm.notes NOT LIKE 'Transferred%' OR sm.notes IS NULL)
            GROUP BY sm.equipment_id
            ORDER BY total_qty DESC
            LIMIT 5";

    $params = [$date_from_sql, $date_to_sql];
    $params = array_merge($params, $branch_params_sm);
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $raw = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($raw as $r) {
        $top5_equipment[] = [
            'name' => $r['equipment_name'],
            'total_qty' => $r['total_qty'],
            'item_id' => $r['equipment_id']
        ];
    }
} catch (Exception $e) { error_log("Top 5 eq: " . $e->getMessage()); }

// ================================================================
// MEDICINE DETAILS V8.6
// ================================================================
$medicine_details = null;

if ($active_tab === 'medicine' && $selected_item_id > 0) {
    $stmt = $db->prepare("
        SELECT id, medication_name, category, unit, selling_price, quantity as current_qty
        FROM medications_inventory 
        WHERE id = ?
        LIMIT 1
    ");
    $stmt->execute([$selected_item_id]);
    $med_info = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($med_info) {
        $med_name = $med_info['medication_name'];

        $inventory_ids = [];
        try {
            $sql_inv = "SELECT id FROM medications_inventory WHERE medication_name = ?";
            $params_inv = [$med_name];
            if ($selected_branch_id !== 'all') {
                $sql_inv .= " AND branch_id = ?";
                $params_inv[] = (int)$selected_branch_id;
            }
            $stmt = $db->prepare($sql_inv);
            $stmt->execute($params_inv);
            $inventory_ids = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'id');
        } catch (Exception $e) {}

        $total_current_stock = 0;
        if (!empty($inventory_ids)) {
            $placeholders = implode(',', array_fill(0, count($inventory_ids), '?'));
            $stmt = $db->prepare("
                SELECT COALESCE(SUM(quantity), 0) as total 
                FROM medications_inventory 
                WHERE id IN ($placeholders) AND status = 'active'
            ");
            $stmt->execute($inventory_ids);
            $total_current_stock = (int)$stmt->fetch(PDO::FETCH_ASSOC)['total'];
        }

        $movements = [];
        if (!empty($inventory_ids)) {
            try {
                $placeholders = implode(',', array_fill(0, count($inventory_ids), '?'));
                $sql = "
                    SELECT 
                        sm.*,
                        u.full_name as performed_by_name,
                        u.username as performed_by_username
                    FROM stock_movements sm
                    LEFT JOIN users u ON sm.performed_by = u.id
                    WHERE sm.inventory_id IN ($placeholders)
                      AND sm.created_at BETWEEN ? AND ?
                      $branch_cond_sm
                    ORDER BY sm.created_at DESC, sm.id DESC
                ";

                $params = $inventory_ids;
                $params[] = $date_from_sql;
                $params[] = $date_to_sql;
                $params = array_merge($params, $branch_params_sm);

                $stmt = $db->prepare($sql);
                $stmt->execute($params);
                $movements = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (Exception $e) { error_log("Movements: " . $e->getMessage()); }
        }

        $stock_before = getStockBefore($db, 'medicine', $inventory_ids, $date_from_sql, $branch_cond_sm, $branch_params_sm);
        
        // ✅ V8.6: stock_after = current stock (item-level)
        $stock_after = $total_current_stock;

        // ============================================================
        // ✅ V8.6: SUMMARY CALCULATION
        // ============================================================
        $summary = [
            'added_qty' => 0, 'added_count' => 0,
            'out_qty' => 0, 'out_count' => 0,
            'prescription_qty' => 0, 'prescription_count' => 0,
            'otc_qty' => 0, 'otc_count' => 0,
            'cancelled_returned_qty' => 0, 'cancelled_returned_count' => 0,
            'unverified_return_qty' => 0, 'unverified_return_count' => 0,
            'cancelled_out_qty' => 0, 'cancelled_out_count' => 0,
            'total_movement' => 0,
            'unique_patients' => 0
        ];

        $patient_ids = [];

        foreach ($movements as $m) {
            $qty = (int)$m['quantity'];
            $mt = strtolower($m['movement_type'] ?? '');
            $notes = $m['notes'] ?? '';
            $ref_type = $m['reference_type'] ?? '';
            $ref_id = $m['reference_id'] ?? null;
            $patient_id = $m['patient_id'] ?? null;

            $is_verified_cancel = false;
            if ($mt === 'in' && (stripos($notes, 'Stock returned') === 0 || $ref_type === 'cancelled_item')) {
                $is_verified_cancel = verifyCancelledReturn($db, $ref_id, $patient_id, $med_name);
            }

            $is_out_cancelled = false;
            if ($mt === 'out' && $ref_type === 'prescription') {
                $is_out_cancelled = isOutCancelled($db, $ref_id, $patient_id, $med_name);
            }

            $category = getMovementCategory($notes, $mt, $ref_type, $is_verified_cancel, $is_out_cancelled);

            if ($mt === 'in') {
                if ($category === 'cancel') {
                    $summary['cancelled_returned_qty'] += $qty;
                    $summary['cancelled_returned_count']++;
                } elseif ($category === 'unverified_return') {
                    $summary['unverified_return_qty'] += $qty;
                    $summary['unverified_return_count']++;
                } else {
                    $summary['added_qty'] += $qty;
                    $summary['added_count']++;
                }
            } else {
                if ($is_out_cancelled) {
                    $summary['cancelled_out_qty'] += $qty;
                    $summary['cancelled_out_count']++;
                } elseif ($category === 'prescription') {
                    $summary['prescription_qty'] += $qty;
                    $summary['prescription_count']++;
                    $summary['out_qty'] += $qty;
                    $summary['out_count']++;
                } elseif ($category === 'otc') {
                    $summary['otc_qty'] += $qty;
                    $summary['otc_count']++;
                    $summary['out_qty'] += $qty;
                    $summary['out_count']++;
                } else {
                    $summary['out_qty'] += $qty;
                    $summary['out_count']++;
                }
            }

            if (!empty($m['patient_id'])) {
                $patient_ids[$m['patient_id']] = true;
            }
        }

        $summary['total_movement'] = $summary['out_qty'];
        $summary['unique_patients'] = count($patient_ids);

        // ✅ V8.6 FIX: Formula jumuisha cancelled_out
        $total_in_calc = $summary['added_qty'] + $summary['cancelled_returned_qty'];
        $total_out_calc = $summary['out_qty'] + $summary['cancelled_out_qty'];
        $calculated_stock_after = $stock_before + $total_in_calc - $total_out_calc;

        // ADDITIONS
        $additions = [];
        foreach ($movements as $m) {
            if (strtolower($m['movement_type']) !== 'in') continue;
            if (stripos($m['notes'] ?? '', 'Stock returned') === 0) continue;

            $additions[] = [
                'added_at' => $m['created_at'],
                'quantity' => (int)$m['quantity'],
                'added_by_name' => $m['performed_by_name'] ?? 'System',
                'previous_stock' => (int)$m['previous_stock'],
                'new_stock' => (int)$m['new_stock'],
                'notes' => $m['notes'] ?? '',
            ];
        }

        $medicine_details = [
            'name' => $med_name,
            'info' => $med_info,
            'inventory_ids' => $inventory_ids,
            'total_current_stock' => $total_current_stock,
            'movements' => $movements,
            'summary' => $summary,
            'period_info' => [
                'filter_label' => $date_label,
                'stock_before' => $stock_before,
                'stock_after' => $stock_after,
                'stock_after_calculated' => $calculated_stock_after,
                'total_in_calc' => $total_in_calc,
                'total_out_calc' => $total_out_calc,
            ],
            'additions' => $additions,
        ];
    }
}

// ================================================================
// EQUIPMENT DETAILS V8.6
// ================================================================
$equipment_details = null;

if ($active_tab === 'equipment' && $selected_item_id > 0) {
    $stmt = $db->prepare("
        SELECT id, equipment_name, category, unit, selling_price, quantity as current_qty
        FROM medical_equipment 
        WHERE id = ?
        LIMIT 1
    ");
    $stmt->execute([$selected_item_id]);
    $eq_info = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($eq_info) {
        $eq_name = $eq_info['equipment_name'];

        $equipment_ids = [];
        try {
            $sql_eq = "SELECT id FROM medical_equipment WHERE equipment_name = ?";
            $params_eq = [$eq_name];
            if ($selected_branch_id !== 'all') {
                $sql_eq .= " AND branch_id = ?";
                $params_eq[] = (int)$selected_branch_id;
            }
            $stmt = $db->prepare($sql_eq);
            $stmt->execute($params_eq);
            $equipment_ids = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'id');
        } catch (Exception $e) {}

        $total_current_stock = 0;
        if (!empty($equipment_ids)) {
            $placeholders = implode(',', array_fill(0, count($equipment_ids), '?'));
            $stmt = $db->prepare("
                SELECT COALESCE(SUM(quantity), 0) as total 
                FROM medical_equipment 
                WHERE id IN ($placeholders) AND status = 'active'
            ");
            $stmt->execute($equipment_ids);
            $total_current_stock = (int)$stmt->fetch(PDO::FETCH_ASSOC)['total'];
        }

        $movements = [];
        if (!empty($equipment_ids)) {
            try {
                $placeholders = implode(',', array_fill(0, count($equipment_ids), '?'));
                $sql = "
                    SELECT 
                        sm.*,
                        u.full_name as performed_by_name,
                        u.username as performed_by_username
                    FROM stock_movements sm
                    LEFT JOIN users u ON sm.performed_by = u.id
                    WHERE sm.equipment_id IN ($placeholders)
                      AND sm.created_at BETWEEN ? AND ?
                      $branch_cond_sm
                    ORDER BY sm.created_at DESC, sm.id DESC
                ";

                $params = $equipment_ids;
                $params[] = $date_from_sql;
                $params[] = $date_to_sql;
                $params = array_merge($params, $branch_params_sm);

                $stmt = $db->prepare($sql);
                $stmt->execute($params);
                $movements = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (Exception $e) { error_log("Eq movements: " . $e->getMessage()); }
        }

        $stock_before = getStockBefore($db, 'equipment', $equipment_ids, $date_from_sql, $branch_cond_sm, $branch_params_sm);
        $stock_after = $total_current_stock;

        $summary = [
            'added_qty' => 0, 'added_count' => 0,
            'out_qty' => 0, 'out_count' => 0,
            'lab_test_qty' => 0, 'lab_test_count' => 0,
            'otc_qty' => 0, 'otc_count' => 0,
            'doctor_qty' => 0, 'doctor_count' => 0,
            'procedure_qty' => 0, 'procedure_count' => 0,
            'returned_qty' => 0, 'returned_count' => 0,
            'unverified_return_qty' => 0, 'unverified_return_count' => 0,
            'cancelled_out_qty' => 0, 'cancelled_out_count' => 0,
            'total_movement' => 0,
            'unique_patients' => 0
        ];

        $patient_ids = [];

        foreach ($movements as $m) {
            $qty = (int)$m['quantity'];
            $mt = strtolower($m['movement_type'] ?? '');
            $notes = $m['notes'] ?? '';
            $ref_type = $m['reference_type'] ?? '';
            $ref_id = $m['reference_id'] ?? null;
            $patient_id = $m['patient_id'] ?? null;

            $is_verified_cancel = false;
            if ($mt === 'in' && (stripos($notes, 'Stock returned') === 0 || $ref_type === 'cancelled_item')) {
                $is_verified_cancel = verifyCancelledReturn($db, $ref_id, $patient_id, $eq_name);
            }

            $is_out_cancelled = false;
            if ($mt === 'out' && $ref_type === 'prescription') {
                $is_out_cancelled = isOutCancelled($db, $ref_id, $patient_id, $eq_name);
            }

            $category = getMovementCategory($notes, $mt, $ref_type, $is_verified_cancel, $is_out_cancelled);

            if ($mt === 'in') {
                if ($category === 'cancel') {
                    $summary['returned_qty'] += $qty;
                    $summary['returned_count']++;
                } elseif ($category === 'unverified_return') {
                    $summary['unverified_return_qty'] += $qty;
                    $summary['unverified_return_count']++;
                } else {
                    $summary['added_qty'] += $qty;
                    $summary['added_count']++;
                }
            } else {
                if ($is_out_cancelled) {
                    $summary['cancelled_out_qty'] += $qty;
                    $summary['cancelled_out_count']++;
                } elseif ($category === 'lab_test') {
                    $summary['lab_test_qty'] += $qty;
                    $summary['lab_test_count']++;
                    $summary['out_qty'] += $qty;
                    $summary['out_count']++;
                } elseif ($category === 'otc') {
                    $summary['otc_qty'] += $qty;
                    $summary['otc_count']++;
                    $summary['out_qty'] += $qty;
                    $summary['out_count']++;
                } elseif ($category === 'doctor_use') {
                    $summary['doctor_qty'] += $qty;
                    $summary['doctor_count']++;
                    $summary['out_qty'] += $qty;
                    $summary['out_count']++;
                } elseif ($category === 'procedure' || $category === 'equipment') {
                    $summary['procedure_qty'] += $qty;
                    $summary['procedure_count']++;
                    $summary['out_qty'] += $qty;
                    $summary['out_count']++;
                } else {
                    $summary['out_qty'] += $qty;
                    $summary['out_count']++;
                }
            }

            if (!empty($m['patient_id'])) {
                $patient_ids[$m['patient_id']] = true;
            }
        }

        $summary['total_movement'] = $summary['out_qty'];
        $summary['unique_patients'] = count($patient_ids);

        $total_in_calc = $summary['added_qty'] + $summary['returned_qty'];
        $total_out_calc = $summary['out_qty'] + $summary['cancelled_out_qty'];
        $calculated_stock_after = $stock_before + $total_in_calc - $total_out_calc;

        $additions = [];
        foreach ($movements as $m) {
            if (strtolower($m['movement_type']) !== 'in') continue;
            if (stripos($m['notes'] ?? '', 'Stock returned') === 0) continue;

            $additions[] = [
                'added_at' => $m['created_at'],
                'quantity' => (int)$m['quantity'],
                'added_by_name' => $m['performed_by_name'] ?? 'System',
                'previous_stock' => (int)$m['previous_stock'],
                'new_stock' => (int)$m['new_stock'],
                'notes' => $m['notes'] ?? '',
            ];
        }

        // Pre-fetch patients
        $patient_ids_list = array_unique(array_filter(array_column($movements, 'patient_id')));
        $patients_map = [];
        if (!empty($patient_ids_list)) {
            $placeholders_p = implode(',', array_fill(0, count($patient_ids_list), '?'));
            $stmt_p = $db->prepare("SELECT id, full_name, patient_id FROM patients WHERE id IN ($placeholders_p)");
            $stmt_p->execute(array_values($patient_ids_list));
            while ($p_row = $stmt_p->fetch(PDO::FETCH_ASSOC)) {
                $patients_map[$p_row['id']] = $p_row;
            }
        }

        $equipment_details = [
            'name' => $eq_name,
            'info' => $eq_info,
            'equipment_ids' => $equipment_ids,
            'total_current_stock' => $total_current_stock,
            'movements' => $movements,
            'summary' => $summary,
            'period_info' => [
                'filter_label' => $date_label,
                'stock_before' => $stock_before,
                'stock_after' => $stock_after,
                'stock_after_calculated' => $calculated_stock_after,
                'total_in_calc' => $total_in_calc,
                'total_out_calc' => $total_out_calc,
            ],
            'additions' => $additions,
            'patients_map' => $patients_map,
        ];
    }
}

// ================================================================
// EXPORT CSV
// ================================================================
if (isset($_GET['export']) && $_GET['export'] === 'csv' && $selected_item_id > 0) {
    $details = $active_tab === 'medicine' ? $medicine_details : $equipment_details;

    if ($details) {
        $filename = 'stock_movement_' . preg_replace('/[^a-zA-Z0-9]/', '_', $details['name']) . '_' . $filter_date_from . '_to_' . $filter_date_to . '.csv';

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $output = fopen('php://output', 'w');
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

        fputcsv($output, ['STOCK MOVEMENT REPORT V8.6 - ADMIN']);
        fputcsv($output, ['Item', $details['name']]);
        fputcsv($output, ['Type', strtoupper($active_tab)]);
        fputcsv($output, ['Branch', $branch_name_display]);
        fputcsv($output, ['Period', $date_label]);
        fputcsv($output, ['Generated', date('Y-m-d H:i:s')]);
        fputcsv($output, []);

        $pi = $details['period_info'];
        $sm = $details['summary'];
        
        fputcsv($output, ['STOCK FLOW']);
        fputcsv($output, ['Stock Before', $pi['stock_before']]);
        fputcsv($output, ['Added (verified)', $sm['added_qty']]);
        fputcsv($output, ['Cancelled Returned (verified)', $sm['cancelled_returned_qty'] ?? $sm['returned_qty'] ?? 0]);
        if (($sm['unverified_return_qty'] ?? 0) > 0) {
            fputcsv($output, ['Unverified Returns', $sm['unverified_return_qty']]);
        }
        fputcsv($output, ['Out (verified)', $sm['out_qty']]);
        fputcsv($output, ['Cancelled Out', $sm['cancelled_out_qty'] ?? 0]);
        fputcsv($output, ['Total IN', $pi['total_in_calc']]);
        fputcsv($output, ['Total OUT', $pi['total_out_calc']]);
        fputcsv($output, ['Stock After (calculated)', $pi['stock_after_calculated']]);
        fputcsv($output, ['Stock After (current live)', $pi['stock_after']]);
        fputcsv($output, []);

        fputcsv($output, ['MOVEMENTS DETAIL']);
        fputcsv($output, ['#', 'Date', 'Type', 'Qty', 'Prev', 'New', 'By', 'Category', 'Notes']);
        $i = 1;
        foreach ($details['movements'] as $m) {
            $mt = strtolower($m['movement_type'] ?? '');
            $ref_type = $m['reference_type'] ?? '';
            $ref_id = $m['reference_id'] ?? null;
            $patient_id = $m['patient_id'] ?? null;
            $notes = $m['notes'] ?? '';
            
            $is_verified = false;
            if ($mt === 'in' && (stripos($notes, 'Stock returned') === 0 || $ref_type === 'cancelled_item')) {
                $is_verified = verifyCancelledReturn($db, $ref_id, $patient_id, $details['name']);
            }
            
            $is_out_cancelled = false;
            if ($mt === 'out' && $ref_type === 'prescription') {
                $is_out_cancelled = isOutCancelled($db, $ref_id, $patient_id, $details['name']);
            }
            
            $cat = getMovementCategory($notes, $mt, $ref_type, $is_verified, $is_out_cancelled);
            
            fputcsv($output, [
                $i++,
                $m['created_at'],
                $m['movement_type'],
                $m['quantity'],
                $m['previous_stock'],
                $m['new_stock'],
                $m['performed_by_name'] ?? 'N/A',
                $cat,
                $notes
            ]);
        }

        fclose($output);
        exit;
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
<title>Stock Movement Report V8.6 • Braick Admin Audit</title>
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
    --slate: #64748B; --slate-bg: #F1F5F9;
    --bg-body: #F1F5F9; --bg-card: #FFFFFF;
    --text-primary: #1E293B; --text-secondary: #64748B; --text-muted: #94A3B8;
    --border-color: #E2E8F0;
    --shadow-sm: 0 1px 3px rgba(0,0,0,0.06);
    --shadow-md: 0 4px 12px rgba(0,0,0,0.08);
    --shadow-lg: 0 10px 25px rgba(0,0,0,0.1);
    --radius-sm: 8px; --radius-md: 12px; --radius-lg: 16px; --radius-full: 9999px;
}
[data-theme="dark"] {
    --bg-body: #0B1220; --bg-card: #111C33;
    --text-primary: #F1F5F9; --text-secondary: #94A3B8; --text-muted: #64748B;
    --border-color: #1E2E4A;
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
.branch-tag.filter-active { background: linear-gradient(135deg, #FCD34D, #F59E0B); color: #78350F; font-weight: 800; box-shadow: 0 2px 8px rgba(252, 211, 77, 0.4); }
.btn-header { background: rgba(255,255,255,0.15); color: white; border: 1px solid rgba(255,255,255,0.25); padding: 10px 16px; border-radius: var(--radius-sm); font-weight: 700; font-size: 0.75rem; transition: all 0.25s; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; backdrop-filter: blur(10px); cursor: pointer; }
.btn-header:hover { background: rgba(255,255,255,0.3); transform: translateY(-2px); }
.btn-header.export { background: linear-gradient(135deg, #10B981, #059669); border-color: rgba(255,255,255,0.3); }

.tabs-container { background: var(--bg-card); border-radius: var(--radius-lg); padding: 6px; margin-bottom: 20px; display: flex; gap: 6px; box-shadow: var(--shadow-sm); border: 1px solid var(--border-color); }
.tab-btn { flex: 1; padding: 14px 24px; border-radius: var(--radius-md); border: none; background: transparent; color: var(--text-secondary); font-weight: 800; font-size: 0.85rem; cursor: pointer; transition: all 0.3s ease; display: flex; align-items: center; justify-content: center; gap: 10px; text-decoration: none; }
.tab-btn:hover { background: var(--bg-body); color: var(--primary); }
.tab-btn.active { background: linear-gradient(135deg, var(--primary), var(--primary-dark)); color: white; box-shadow: 0 6px 16px rgba(11, 94, 215, 0.35); }

.filter-card { background: var(--bg-card); border-radius: var(--radius-lg); padding: 18px 20px; border: 1px solid var(--border-color); margin-bottom: 20px; box-shadow: var(--shadow-sm); }
.filter-section-title { font-size: 0.68rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.08em; color: var(--text-secondary); margin-bottom: 10px; display: flex; align-items: center; gap: 6px; }
.quick-filters { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 16px; }
.quick-btn { padding: 8px 14px; border-radius: var(--radius-sm); border: 1.5px solid var(--border-color); background: var(--bg-card); color: var(--text-secondary); font-weight: 700; font-size: 0.72rem; cursor: pointer; transition: all 0.25s; display: inline-flex; align-items: center; gap: 6px; text-decoration: none; white-space: nowrap; }
.quick-btn:hover { border-color: var(--primary); color: var(--primary); transform: translateY(-2px); }
.quick-btn.active { background: linear-gradient(135deg, var(--primary), var(--primary-dark)); color: white; border-color: transparent; box-shadow: 0 6px 16px rgba(11, 94, 215, 0.35); }
.quick-btn.branch-active { background: linear-gradient(135deg, #FCD34D, #F59E0B); color: #78350F; border-color: transparent; box-shadow: 0 6px 16px rgba(252, 211, 77, 0.4); }

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

.top5-card { background: var(--bg-card); border-radius: var(--radius-lg); padding: 18px 20px; margin-bottom: 20px; box-shadow: var(--shadow-md); position: relative; overflow: hidden; border: 2px solid; }
.top5-card.medicine { border-color: #FCD34D; background: linear-gradient(135deg, #FFFBEB 0%, #FEF3C7 100%); }
.top5-card.equipment { border-color: #93C5FD; background: linear-gradient(135deg, #EFF6FF 0%, #DBEAFE 100%); }
[data-theme="dark"] .top5-card.medicine { background: linear-gradient(135deg, #3A2A0F 0%, #4A3A12 100%); }
[data-theme="dark"] .top5-card.equipment { background: linear-gradient(135deg, #1A2A4A 0%, #12294A 100%); }
.top5-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 14px; padding-bottom: 10px; border-bottom: 2px dashed; flex-wrap: wrap; gap: 10px; }
.top5-card.medicine .top5-header { border-bottom-color: rgba(217, 119, 6, 0.3); }
.top5-card.equipment .top5-header { border-bottom-color: rgba(11, 94, 215, 0.3); }
.top5-title { display: flex; align-items: center; gap: 10px; font-size: 0.9rem; font-weight: 900; text-transform: uppercase; letter-spacing: 0.03em; }
.top5-card.medicine .top5-title { color: #92400E; }
.top5-card.equipment .top5-title { color: #1E40AF; }
.top5-card.medicine .top5-title i { color: #D97706; }
.top5-card.equipment .top5-title i { color: #0B5ED7; }
.top5-period-badge { display: inline-flex; align-items: center; gap: 6px; padding: 5px 14px; border-radius: 20px; font-size: 0.68rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em; border: 1.5px solid; }
.top5-card.medicine .top5-period-badge { background: rgba(217, 119, 6, 0.15); color: #92400E; border-color: rgba(217, 119, 6, 0.4); }
.top5-card.equipment .top5-period-badge { background: rgba(11, 94, 215, 0.15); color: #1E40AF; border-color: rgba(11, 94, 215, 0.4); }
.top5-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 10px; }
.top5-item { background: var(--bg-card); border: 2px solid; border-radius: 12px; padding: 12px 14px; display: flex; align-items: center; gap: 10px; text-decoration: none; color: var(--text-primary); transition: all 0.3s ease; position: relative; overflow: hidden; }
.top5-item.medicine { border-color: #FCD34D; }
.top5-item.equipment { border-color: #93C5FD; }
.top5-item:hover { transform: translateY(-4px); box-shadow: var(--shadow-lg); }
.top5-item.medicine:hover { border-color: #D97706; }
.top5-item.equipment:hover { border-color: #0B5ED7; }
.top5-rank { position: absolute; top: 4px; right: 8px; font-size: 0.65rem; font-weight: 900; opacity: 0.5; font-family: var(--font-mono); }
.top5-item.medicine .top5-rank { color: #D97706; }
.top5-item.equipment .top5-rank { color: #0B5ED7; }
.top5-icon { width: 38px; height: 38px; border-radius: 10px; color: white; display: flex; align-items: center; justify-content: center; font-size: 0.9rem; flex-shrink: 0; box-shadow: 0 3px 10px rgba(0,0,0,0.15); }
.top5-item.medicine .top5-icon { background: linear-gradient(135deg, #D97706, #F59E0B); }
.top5-item.equipment .top5-icon { background: linear-gradient(135deg, #0B5ED7, #3B82F6); }
.top5-info { flex: 1; min-width: 0; }
.top5-name { font-weight: 800; font-size: 0.82rem; margin-bottom: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.top5-meta { font-size: 0.65rem; font-weight: 700; color: var(--text-secondary); display: flex; align-items: center; gap: 4px; }
.top5-meta strong { font-family: var(--font-mono); font-weight: 900; }
.top5-item.medicine .top5-meta strong { color: #D97706; }
.top5-item.equipment .top5-meta strong { color: #0B5ED7; }
.top5-empty { padding: 30px 20px; text-align: center; color: var(--text-secondary); font-size: 0.8rem; font-weight: 600; }
.top5-empty i { font-size: 2rem; opacity: 0.3; display: block; margin-bottom: 8px; }

.stock-flow-card { background: var(--bg-card); border-radius: var(--radius-lg); padding: 24px 28px; margin-bottom: 20px; border: 2px solid var(--border-color); box-shadow: 0 8px 30px rgba(0,0,0,0.08); position: relative; overflow: hidden; }
.stock-flow-card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 5px; background: linear-gradient(90deg, #64748B, #059669, #DC2626, #059669); }
.sf-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 22px; padding-bottom: 16px; border-bottom: 2px dashed var(--border-color); flex-wrap: wrap; gap: 12px; }
.sf-title { display: flex; align-items: center; gap: 12px; font-size: 1.1rem; font-weight: 900; text-transform: uppercase; color: var(--primary); }
.sf-badge { background: linear-gradient(135deg, var(--primary), var(--primary-dark)); color: white; padding: 6px 16px; border-radius: var(--radius-full); font-size: 0.72rem; font-weight: 800; text-transform: uppercase; }
.sf-flow { display: grid; grid-template-columns: 1fr auto 1fr auto 1fr; gap: 12px; align-items: stretch; margin-bottom: 20px; }
.sf-block { background: var(--bg-body); border-radius: var(--radius-md); padding: 18px 20px; border: 2px solid var(--border-color); display: flex; flex-direction: column; justify-content: center; position: relative; min-height: 120px; }
.sf-block::before { content: ''; position: absolute; left: 0; top: 0; bottom: 0; width: 5px; border-radius: var(--radius-md) 0 0 var(--radius-md); }
.sf-block.before::before { background: linear-gradient(180deg, #64748B, #94A3B8); }
.sf-block.movement::before { background: linear-gradient(180deg, #DC2626, #F87171); }
.sf-block.remaining::before { background: linear-gradient(180deg, #059669, #34D399); }
.sf-block .sf-block-label { font-size: 0.65rem; font-weight: 800; text-transform: uppercase; color: var(--text-secondary); letter-spacing: 0.06em; margin-bottom: 8px; display: flex; align-items: center; gap: 6px; }
.sf-block .sf-block-value { font-family: var(--font-mono); font-size: 2.4rem; font-weight: 900; line-height: 1; display: flex; align-items: baseline; gap: 6px; }
.sf-block.before .sf-block-value { color: #64748B; }
.sf-block.movement .sf-block-value { color: #DC2626; }
.sf-block.remaining .sf-block-value { color: #059669; }
.sf-block .sf-block-value .unit { font-size: 0.85rem; font-weight: 700; color: var(--text-secondary); }
.sf-block .sf-block-meta { font-size: 0.68rem; font-weight: 600; color: var(--text-muted); margin-top: 8px; display: flex; flex-direction: column; gap: 2px; }
.sf-arrow { display: flex; align-items: center; justify-content: center; font-size: 1.8rem; color: var(--text-muted); min-width: 40px; }
.sf-movements-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; margin-top: 6px; }
.sf-mov-item { text-align: center; padding: 8px 4px; border-radius: 8px; background: var(--bg-card); border: 1.5px solid var(--border-color); }
.sf-mov-item .sf-mov-label { font-size: 0.55rem; font-weight: 800; text-transform: uppercase; margin-bottom: 3px; }
.sf-mov-item .sf-mov-qty { font-family: var(--font-mono); font-size: 1.1rem; font-weight: 900; }
.sf-mov-item.added .sf-mov-label, .sf-mov-item.added .sf-mov-qty { color: #059669; }
.sf-mov-item.out .sf-mov-label, .sf-mov-item.out .sf-mov-qty { color: #DC2626; }
.sf-mov-item.returned { background: rgba(5,150,105,0.06); border-color: rgba(5,150,105,0.3); border-style: dashed; }
.sf-mov-item.returned .sf-mov-label, .sf-mov-item.returned .sf-mov-qty { color: #059669; }
.sf-mov-item.doctor .sf-mov-label, .sf-mov-item.doctor .sf-mov-qty { color: #8B5CF6; }

.data-warning-banner { padding: 16px 20px; background: linear-gradient(135deg, #FEF3C7 0%, #FDE68A 100%); border: 2px dashed #F59E0B; border-radius: var(--radius-md); margin-bottom: 20px; display: flex; align-items: flex-start; gap: 14px; }
[data-theme="dark"] .data-warning-banner { background: linear-gradient(135deg, #3A2A0F 0%, #4A3A12 100%); border-color: #D97706; }
.data-warning-banner .dw-icon { width: 48px; height: 48px; border-radius: 12px; background: linear-gradient(135deg, #F59E0B, #D97706); color: white; display: flex; align-items: center; justify-content: center; font-size: 1.4rem; flex-shrink: 0; }
.data-warning-banner .dw-content { flex: 1; }
.data-warning-banner .dw-title { font-weight: 900; color: #78350F; font-size: 0.95rem; margin-bottom: 6px; display: flex; align-items: center; gap: 6px; }
[data-theme="dark"] .data-warning-banner .dw-title { color: #FCD34D; }
.data-warning-banner .dw-text { font-size: 0.8rem; color: #92400E; line-height: 1.6; }
[data-theme="dark"] .data-warning-banner .dw-text { color: #FDE68A; }

.summary-grid-v8 { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 14px; margin-bottom: 20px; }
.sum-card-v8 { background: var(--bg-card); border-radius: var(--radius-lg); padding: 18px 20px; border: 2px solid var(--border-color); position: relative; overflow: hidden; transition: all 0.3s; display: flex; align-items: center; gap: 14px; }
.sum-card-v8::before { content: ''; position: absolute; left: 0; top: 0; bottom: 0; width: 5px; }
.sum-card-v8:hover { transform: translateY(-4px); box-shadow: var(--shadow-lg); }
.sum-card-v8 .sc-icon-v8 { width: 52px; height: 52px; border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 1.4rem; color: white; flex-shrink: 0; }
.sum-card-v8 .sc-content { flex: 1; }
.sum-card-v8 .sc-label-v8 { font-size: 0.65rem; font-weight: 800; text-transform: uppercase; color: var(--text-secondary); margin-bottom: 4px; }
.sum-card-v8 .sc-value-v8 { font-family: var(--font-mono); font-size: 1.9rem; font-weight: 900; line-height: 1; display: flex; align-items: baseline; gap: 5px; }
.sum-card-v8 .sc-value-v8 .unit-v8 { font-size: 0.72rem; font-weight: 700; color: var(--text-secondary); }
.sum-card-v8 .sc-sub-v8 { font-size: 0.65rem; color: var(--text-muted); margin-top: 4px; font-weight: 600; }
.sum-card-v8.added::before { background: linear-gradient(180deg, #059669, #34D399); }
.sum-card-v8.added .sc-icon-v8 { background: linear-gradient(135deg, #059669, #34D399); }
.sum-card-v8.added .sc-value-v8 { color: #059669; }
.sum-card-v8.out::before { background: linear-gradient(180deg, #DC2626, #F87171); }
.sum-card-v8.out .sc-icon-v8 { background: linear-gradient(135deg, #DC2626, #F87171); }
.sum-card-v8.out .sc-value-v8 { color: #DC2626; }
.sum-card-v8.returned::before { background: linear-gradient(180deg, #0891B2, #22D3EE); }
.sum-card-v8.returned .sc-icon-v8 { background: linear-gradient(135deg, #0891B2, #22D3EE); }
.sum-card-v8.returned .sc-value-v8 { color: #0891B2; }
.sum-card-v8.prescription::before { background: linear-gradient(180deg, #7C3AED, #A78BFA); }
.sum-card-v8.prescription .sc-icon-v8 { background: linear-gradient(135deg, #7C3AED, #A78BFA); }
.sum-card-v8.prescription .sc-value-v8 { color: #7C3AED; }
.sum-card-v8.otc::before { background: linear-gradient(180deg, #0EA5E9, #38BDF8); }
.sum-card-v8.otc .sc-icon-v8 { background: linear-gradient(135deg, #0EA5E9, #38BDF8); }
.sum-card-v8.otc .sc-value-v8 { color: #0EA5E9; }
.sum-card-v8.lab::before { background: linear-gradient(180deg, #D97706, #FBBF24); }
.sum-card-v8.lab .sc-icon-v8 { background: linear-gradient(135deg, #D97706, #FBBF24); }
.sum-card-v8.lab .sc-value-v8 { color: #D97706; }
.sum-card-v8.doctor::before { background: linear-gradient(180deg, #8B5CF6, #A78BFA); }
.sum-card-v8.doctor .sc-icon-v8 { background: linear-gradient(135deg, #8B5CF6, #A78BFA); }
.sum-card-v8.doctor .sc-value-v8 { color: #8B5CF6; }
.sum-card-v8.procedure::before { background: linear-gradient(180deg, #8B5CF6, #A78BFA); }
.sum-card-v8.procedure .sc-icon-v8 { background: linear-gradient(135deg, #8B5CF6, #A78BFA); }
.sum-card-v8.procedure .sc-value-v8 { color: #8B5CF6; }
.sum-card-v8.unverified::before { background: linear-gradient(180deg, #D97706, #FBBF24); }
.sum-card-v8.unverified .sc-icon-v8 { background: linear-gradient(135deg, #D97706, #FBBF24); }
.sum-card-v8.unverified .sc-value-v8 { color: #D97706; }

.table-card { background: var(--bg-card); border-radius: var(--radius-lg); border: 1px solid var(--border-color); overflow: hidden; box-shadow: var(--shadow-sm); margin-bottom: 20px; }
.table-card .table-header { padding: 14px 20px; background: linear-gradient(135deg, #0B5ED7, #0A4CA8); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; }
.table-card .table-header.green { background: linear-gradient(135deg, #059669, #047857); }
.table-card .table-header.red { background: linear-gradient(135deg, #DC2626, #B91C1C); }
.table-card .table-header.purple { background: linear-gradient(135deg, #7C3AED, #6D28D9); }
.table-card .table-header.cyan { background: linear-gradient(135deg, #0891B2, #0E7490); }
.table-card .table-header.blue { background: linear-gradient(135deg, #0891B2, #0E7490, #155E75); }
.table-card .table-header .title { color: white; font-size: 0.88rem; font-weight: 800; display: flex; align-items: center; gap: 10px; }
.table-card .table-header .count { color: rgba(255,255,255,0.95); font-size: 0.7rem; font-weight: 700; background: rgba(255,255,255,0.18); padding: 5px 12px; border-radius: var(--radius-full); }
.table-scroll-wrapper { overflow-x: auto; }
.data-table { width: 100%; border-collapse: collapse; font-size: 0.78rem; }
.data-table thead th { text-align: left; padding: 11px 14px; font-weight: 800; font-size: 0.62rem; text-transform: uppercase; color: white; background: linear-gradient(135deg, #0B5ED7, #0A4CA8); white-space: nowrap; }
.data-table thead.green th { background: linear-gradient(135deg, #059669, #047857); }
.data-table thead.red th { background: linear-gradient(135deg, #DC2626, #B91C1C); }
.data-table thead.purple th { background: linear-gradient(135deg, #7C3AED, #6D28D9); }
.data-table tbody td { padding: 11px 14px; border-bottom: 1px solid var(--border-color); vertical-align: middle; }
.data-table tbody tr:hover td { background: var(--primary-bg); }
.data-table tbody tr:last-child td { border-bottom: none; }
.data-table tbody tr.in-row { background: rgba(5,150,105,0.04); }
.data-table tbody tr.out-row { background: rgba(220,38,38,0.03); }
.data-table tbody tr.return-row { background: rgba(8,145,178,0.04); }
.data-table tbody tr.unverified-row { background: rgba(217,119,6,0.06); }
.data-table tbody tr.cancelled-row { background: rgba(220,38,38,0.05); opacity: 0.75; }
.data-table tbody tr.doctor-row { background: rgba(139,92,246,0.05); }

.status-badge { display: inline-flex; align-items: center; gap: 4px; padding: 4px 10px; border-radius: var(--radius-full); font-size: 0.62rem; font-weight: 800; text-transform: uppercase; }
.status-badge.in { background: var(--success-bg); color: var(--success); border: 1px solid var(--success); }
.status-badge.out { background: var(--danger-bg); color: var(--danger); border: 1px solid var(--danger); }
.status-badge.prescription { background: var(--purple-bg); color: var(--purple); border: 1px solid var(--purple); }
.status-badge.otc { background: var(--cyan-bg); color: var(--cyan); border: 1px solid var(--cyan); }
.status-badge.lab_test { background: var(--warning-bg); color: var(--warning); border: 1px solid var(--warning); }
.status-badge.cancel { background: var(--cyan-bg); color: var(--cyan); border: 1px solid var(--cyan); }
.status-badge.unverified_return { background: var(--warning-bg); color: var(--warning); border: 1px solid var(--warning); border-style: dashed; }
.status-badge.cancelled_out { background: var(--danger-bg); color: var(--danger); border: 1px solid var(--danger); border-style: dashed; }
.status-badge.auto_dispense { background: var(--primary-bg); color: var(--primary); border: 1px solid var(--primary); }
.status-badge.equipment { background: var(--slate-bg); color: var(--slate); border: 1px solid var(--slate); }
.status-badge.doctor_use { background: #EDE9FE; color: #8B5CF6; border: 1px solid #8B5CF6; }
.status-badge.procedure { background: #EDE9FE; color: #8B5CF6; border: 1px solid #8B5CF6; }

.empty-state { padding: 60px 20px; text-align: center; color: var(--text-secondary); }
.empty-state i { font-size: 3rem; opacity: 0.3; display: block; margin-bottom: 14px; color: var(--primary); }

.info-banner { background: linear-gradient(135deg, var(--primary-bg), transparent); border-left: 4px solid var(--primary); border-radius: var(--radius-md); padding: 16px 20px; margin-bottom: 20px; display: flex; align-items: center; gap: 14px; }
.info-banner .ib-icon { width: 52px; height: 52px; border-radius: 14px; background: linear-gradient(135deg, var(--primary), var(--primary-dark)); color: white; display: flex; align-items: center; justify-content: center; font-size: 1.4rem; flex-shrink: 0; }
.info-banner .ib-content { flex: 1; }
.info-banner .ib-title { font-size: 1.1rem; font-weight: 900; margin-bottom: 4px; }
.info-banner .ib-meta { font-size: 0.78rem; color: var(--text-secondary); display: flex; gap: 16px; flex-wrap: wrap; }
.info-banner .ib-meta span { display: inline-flex; align-items: center; gap: 5px; }

@media (max-width: 1024px) { .sf-flow { grid-template-columns: 1fr; } .sf-arrow { transform: rotate(90deg); } }
@media (max-width: 768px) { .summary-grid-v8 { grid-template-columns: 1fr; } .tabs-container { flex-direction: column; } .top5-grid { grid-template-columns: 1fr; } }
@media print { .btn-header, .quick-btn, .tabs-container, .filter-card { display: none !important; } }
</style>
</head>
<body>

<main class="main-content">

    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-boxes-stacked"></i>
                Stock Movement Report
                <span class="branch-tag"><i class="fas fa-shield-alt"></i> ADMIN V8.6</span>
                <span class="branch-tag <?= $selected_branch_id !== 'all' ? 'filter-active' : '' ?>">
                    <i class="fas <?= $selected_branch_id !== 'all' ? 'fa-lock' : 'fa-globe' ?>"></i>
                    <?= htmlspecialchars($branch_name_display) ?>
                </span>
                <span class="branch-tag"><i class="fas fa-calendar"></i> <?= htmlspecialchars($date_label) ?></span>
            </h1>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;position:relative;z-index:1;">
            <?php if ($selected_item_id > 0): ?>
            <a href="?branch=<?= $selected_branch_id ?>&tab=<?= $active_tab ?>&quick=<?= $quick_filter ?>&item_id=<?= $selected_item_id ?>&date_from=<?= $date_from ?>&date_to=<?= $date_to ?>&export=csv" class="btn-header export">
                <i class="fas fa-file-csv"></i> Export CSV
            </a>
            <?php endif; ?>
            <button onclick="window.print()" class="btn-header"><i class="fas fa-print"></i> Print</button>
            <a href="dashboard.php?branch=<?= $selected_branch_id ?>" class="btn-header">
                <i class="fas fa-arrow-left"></i> Dashboard
            </a>
        </div>
    </div>

    <div class="tabs-container">
        <a href="?branch=<?= $selected_branch_id ?>&tab=medicine&quick=<?= $quick_filter ?>&date_from=<?= $date_from ?>&date_to=<?= $date_to ?>" class="tab-btn <?= $active_tab === 'medicine' ? 'active' : '' ?>">
            <i class="fas fa-pills"></i> MEDICINE TRACKING
        </a>
        <a href="?branch=<?= $selected_branch_id ?>&tab=equipment&quick=<?= $quick_filter ?>&date_from=<?= $date_from ?>&date_to=<?= $date_to ?>" class="tab-btn <?= $active_tab === 'equipment' ? 'active' : '' ?>">
            <i class="fas fa-tools"></i> EQUIPMENT TRACKING
        </a>
    </div>

    <div class="filter-card">
        <div class="filter-section-title"><i class="fas fa-store-alt"></i> Branch Filter</div>
        <div class="quick-filters" style="margin-bottom: 16px;">
            <a href="?branch=all&tab=<?= $active_tab ?>&quick=<?= $quick_filter ?><?= $selected_item_id ? '&item_id=' . $selected_item_id : '' ?>&date_from=<?= $date_from ?>&date_to=<?= $date_to ?>"
               class="quick-btn <?= $selected_branch_id === 'all' ? 'active' : '' ?>">
                <i class="fas fa-globe"></i> All Branches
            </a>
            <?php foreach ($branches as $br): ?>
                <a href="?branch=<?= $br['id'] ?>&tab=<?= $active_tab ?>&quick=<?= $quick_filter ?><?= $selected_item_id ? '&item_id=' . $selected_item_id : '' ?>&date_from=<?= $date_from ?>&date_to=<?= $date_to ?>"
                   class="quick-btn <?= (string)$selected_branch_id === (string)$br['id'] ? 'branch-active' : '' ?>">
                    <i class="fas fa-store"></i> <?= htmlspecialchars($br['name']) ?>
                </a>
            <?php endforeach; ?>
        </div>

        <div class="filter-section-title"><i class="fas fa-bolt"></i> Date Filters</div>
        <div class="quick-filters">
            <a href="?branch=<?= $selected_branch_id ?>&tab=<?= $active_tab ?>&quick=today<?= $selected_item_id ? '&item_id=' . $selected_item_id : '' ?>" class="quick-btn <?= $quick_filter === 'today' ? 'active' : '' ?>"><i class="fas fa-calendar-day"></i> Today</a>
            <a href="?branch=<?= $selected_branch_id ?>&tab=<?= $active_tab ?>&quick=yesterday<?= $selected_item_id ? '&item_id=' . $selected_item_id : '' ?>" class="quick-btn <?= $quick_filter === 'yesterday' ? 'active' : '' ?>"><i class="fas fa-calendar-minus"></i> Yesterday</a>
            <a href="?branch=<?= $selected_branch_id ?>&tab=<?= $active_tab ?>&quick=1w<?= $selected_item_id ? '&item_id=' . $selected_item_id : '' ?>" class="quick-btn <?= $quick_filter === '1w' ? 'active' : '' ?>"><i class="fas fa-calendar-week"></i> 1W</a>
            <a href="?branch=<?= $selected_branch_id ?>&tab=<?= $active_tab ?>&quick=1m<?= $selected_item_id ? '&item_id=' . $selected_item_id : '' ?>" class="quick-btn <?= $quick_filter === '1m' ? 'active' : '' ?>"><i class="fas fa-calendar-alt"></i> 1M</a>
            <a href="?branch=<?= $selected_branch_id ?>&tab=<?= $active_tab ?>&quick=3m<?= $selected_item_id ? '&item_id=' . $selected_item_id : '' ?>" class="quick-btn <?= $quick_filter === '3m' ? 'active' : '' ?>"><i class="fas fa-calendar-alt"></i> 3M</a>
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

        <div class="filter-section-title" style="margin-top: 10px;">
            <i class="fas fa-search"></i> Search <?= $active_tab === 'medicine' ? 'Medicine' : 'Equipment' ?>
        </div>
        <div class="search-wrapper">
            <form method="GET" id="searchForm">
                <input type="hidden" name="branch" value="<?= htmlspecialchars($selected_branch_id) ?>">
                <input type="hidden" name="tab" value="<?= htmlspecialchars($active_tab) ?>">
                <input type="hidden" name="quick" value="<?= htmlspecialchars($quick_filter) ?>">
                <input type="hidden" name="date_from" value="<?= htmlspecialchars($date_from) ?>">
                <input type="hidden" name="date_to" value="<?= htmlspecialchars($date_to) ?>">
                <input type="hidden" name="item_id" id="itemIdInput" value="<?= $selected_item_id ?>">
                <div class="search-input-group">
                    <i class="fas fa-search search-icon"></i>
                    <input type="text" id="searchInput" name="search"
                           value="<?= htmlspecialchars($search) ?>"
                           placeholder="<?= $active_tab === 'medicine' ? 'Search medicine...' : 'Search equipment...' ?>"
                           autocomplete="off">
                    <button type="submit" class="search-btn"><i class="fas fa-arrow-right"></i> Search</button>
                </div>
                <div class="autocomplete-box" id="autocompleteBox"></div>
            </form>
        </div>

        <?php if ($selected_item_id > 0): ?>
            <div style="margin-top: 12px;">
                <a href="?branch=<?= $selected_branch_id ?>&tab=<?= $active_tab ?>&quick=<?= $quick_filter ?>" class="quick-btn" style="background: var(--danger-bg); color: var(--danger); border-color: var(--danger);">
                    <i class="fas fa-times"></i> Clear Selection
                </a>
            </div>
        <?php endif; ?>
    </div>

    <!-- TOP 5 MEDICINES -->
    <?php if ($active_tab === 'medicine' && $selected_item_id === 0): ?>
    <div class="top5-card medicine">
        <div class="top5-header">
            <div class="top5-title"><i class="fas fa-fire"></i> Top 5 Most Used Medicines</div>
            <span class="top5-period-badge"><i class="fas fa-calendar"></i> <?= htmlspecialchars($top5_label) ?></span>
        </div>
        <?php if (count($top5_medicines) > 0): ?>
        <div class="top5-grid">
            <?php foreach ($top5_medicines as $idx => $tm): ?>
                <a href="?branch=<?= $selected_branch_id ?>&tab=medicine&quick=<?= $quick_filter ?>&item_id=<?= (int)$tm['item_id'] ?>" class="top5-item medicine">
                    <div class="top5-rank">#<?= $idx + 1 ?></div>
                    <div class="top5-icon"><i class="fas fa-pills"></i></div>
                    <div class="top5-info">
                        <div class="top5-name"><?= htmlspecialchars($tm['name']) ?></div>
                        <div class="top5-meta"><i class="fas fa-chart-line"></i> <strong><?= number_format($tm['total_qty']) ?></strong> units</div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="top5-empty"><i class="fas fa-inbox"></i><p>No medicine movements in this period</p></div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- TOP 5 EQUIPMENT -->
    <?php if ($active_tab === 'equipment' && $selected_item_id === 0): ?>
    <div class="top5-card equipment">
        <div class="top5-header">
            <div class="top5-title"><i class="fas fa-fire"></i> Top 5 Most Used Equipment</div>
            <span class="top5-period-badge"><i class="fas fa-calendar"></i> <?= htmlspecialchars($top5_label) ?></span>
        </div>
        <?php if (count($top5_equipment) > 0): ?>
        <div class="top5-grid">
            <?php foreach ($top5_equipment as $idx => $te): ?>
                <a href="?branch=<?= $selected_branch_id ?>&tab=equipment&quick=<?= $quick_filter ?>&item_id=<?= (int)$te['item_id'] ?>" class="top5-item equipment">
                    <div class="top5-rank">#<?= $idx + 1 ?></div>
                    <div class="top5-icon"><i class="fas fa-tools"></i></div>
                    <div class="top5-info">
                        <div class="top5-name"><?= htmlspecialchars($te['name']) ?></div>
                        <div class="top5-meta"><i class="fas fa-chart-line"></i> <strong><?= number_format($te['total_qty']) ?></strong> units</div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="top5-empty"><i class="fas fa-inbox"></i><p>No equipment movements in this period</p></div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- MEDICINE TAB -->
    <?php if ($active_tab === 'medicine'): ?>
        <?php if ($medicine_details): ?>
            <?php 
                $med = $medicine_details['info']; 
                $sm = $medicine_details['summary']; 
                $pi = $medicine_details['period_info']; 
                $movs = $medicine_details['movements']; 
                $additions = $medicine_details['additions'];
                
                // ✅ V8.6: Formula with cancelled_out included
                $total_in_display = $pi['total_in_calc'];
                $total_out_display = $pi['total_out_calc'];
                $final_calculated = $pi['stock_after_calculated'];
            ?>

            <div class="info-banner">
                <div class="ib-icon"><i class="fas fa-pills"></i></div>
                <div class="ib-content">
                    <div class="ib-title"><?= htmlspecialchars($medicine_details['name']) ?></div>
                    <div class="ib-meta">
                        <span><i class="fas fa-tag"></i> <?= htmlspecialchars($med['category'] ?? 'N/A') ?></span>
                        <span><i class="fas fa-flask"></i> Unit: <?= htmlspecialchars($med['unit'] ?? 'N/A') ?></span>
                        <span><i class="fas fa-money-bill"></i> Selling: <?= $currency ?> <?= number_format($med['selling_price'] ?? 0, 0) ?></span>
                        <span style="color:var(--success);font-weight:800;"><i class="fas fa-warehouse"></i> Current Stock: <?= number_format($medicine_details['total_current_stock']) ?></span>
                        <span style="color:var(--primary);font-weight:800;"><i class="fas fa-store"></i> <?= htmlspecialchars($branch_name_display) ?></span>
                    </div>
                </div>
            </div>

            <!-- Warning kama kuna unverified returns -->
            <?php if ($sm['unverified_return_qty'] > 0): ?>
            <div class="data-warning-banner">
                <div class="dw-icon"><i class="fas fa-exclamation-triangle"></i></div>
                <div class="dw-content">
                    <div class="dw-title">
                        <i class="fas fa-database"></i> Data Integrity Warning
                    </div>
                    <div class="dw-text">
                        Kuna <strong><?= number_format($sm['unverified_return_qty']) ?> units</strong> za "Stock returned" kwenye 
                        <code>stock_movements</code> lakini <strong>hakuna cancellation record</strong> kwenye 
                        <code>prescription_items.cancelled_at</code>.
                        <br>
                        <strong>V8.6 Decision:</strong> Returns hizi <strong>HAZIJUMLISHWI</strong> kwenye "Cancelled Returned" kwa usalama.
                        Zimehesabiwa kama <strong>"Unverified Returns"</strong>.
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <div class="stock-flow-card">
                <div class="sf-header">
                    <div class="sf-title"><i class="fas fa-water"></i> Stock Flow — All Batches Combined</div>
                    <div class="sf-badge"><i class="fas fa-calendar"></i> <?= htmlspecialchars($pi['filter_label']) ?></div>
                </div>
                <div class="sf-flow">
                    <div class="sf-block before">
                        <div class="sf-block-label"><i class="fas fa-warehouse"></i> Stock Before Period</div>
                        <div class="sf-block-value"><?= number_format($pi['stock_before']) ?> <span class="unit">units</span></div>
                        <div class="sf-block-meta">
                            <span><i class="fas fa-calendar"></i> As of <?= date('d/m/Y', strtotime($filter_date_from)) ?> 00:00</span>
                            <span style="color:var(--primary);"><i class="fas fa-layer-group"></i> All batches combined</span>
                        </div>
                    </div>
                    <div class="sf-arrow"><i class="fas fa-arrows-left-right"></i></div>
                    <div class="sf-block movement">
                        <div class="sf-block-label"><i class="fas fa-exchange-alt"></i> Movements in Period</div>
                        <div class="sf-block-value"><?= number_format($total_in_display + $total_out_display) ?> <span class="unit">records</span></div>
                        <div class="sf-movements-grid">
                            <div class="sf-mov-item added"><div class="sf-mov-label">Added</div><div class="sf-mov-qty">+<?= number_format($sm['added_qty']) ?></div></div>
                            <div class="sf-mov-item out"><div class="sf-mov-label">Out</div><div class="sf-mov-qty">−<?= number_format($sm['out_qty'] + $sm['cancelled_out_qty']) ?></div></div>
                            <div class="sf-mov-item returned"><div class="sf-mov-label">Returned</div><div class="sf-mov-qty">+<?= number_format($sm['cancelled_returned_qty']) ?></div></div>
                        </div>
                    </div>
                    <div class="sf-arrow"><i class="fas fa-equals"></i></div>
                    <div class="sf-block remaining">
                        <div class="sf-block-label"><i class="fas fa-boxes-packing"></i> Stock After Period</div>
                        <div class="sf-block-value"><?= number_format($pi['stock_after']) ?> <span class="unit">units</span></div>
                        <div class="sf-block-meta">
                            <span><i class="fas fa-database"></i> Current live: <?= number_format($medicine_details['total_current_stock']) ?></span>
                            <span style="color:var(--success);"><i class="fas fa-check-circle"></i> All batches combined</span>
                        </div>
                    </div>
                </div>
                
                <!-- ✅ V8.6: Formula sahihi -->
                <div style="text-align: center; padding: 14px; background: var(--bg-body); border-radius: var(--radius-md); font-family: var(--font-mono); font-weight: 800; border: 2px dashed var(--border-color); font-size: 0.95rem;">
                    <span style="color:#64748B;font-size:1.15rem;" title="Stock before period"><?= number_format($pi['stock_before']) ?></span>
                    <span style="color:var(--text-secondary);margin:0 8px;">+</span>
                    <span style="color:#059669;font-size:1.15rem;" title="Total IN (added + returned)"><?= number_format($total_in_display) ?></span>
                    <span style="color:var(--text-secondary);margin:0 8px;">−</span>
                    <span style="color:#DC2626;font-size:1.15rem;" title="Total OUT (out + cancelled out)"><?= number_format($total_out_display) ?></span>
                    <span style="color:var(--text-secondary);margin:0 8px;">=</span>
                    <span style="color:#059669;font-size:1.3rem;" title="Stock after period"><?= number_format($final_calculated) ?></span>
                    <div style="font-size:0.68rem;color:var(--text-muted);margin-top:6px;font-family:var(--font-primary);font-weight:600;">
                        IN = Added (<?= number_format($sm['added_qty']) ?>) + Returned (<?= number_format($sm['cancelled_returned_qty']) ?>)
                        &nbsp;•&nbsp;
                        OUT = Out (<?= number_format($sm['out_qty']) ?>) + Cancelled Out (<?= number_format($sm['cancelled_out_qty']) ?>)
                    </div>
                </div>
            </div>

            <div class="summary-grid-v8">
                <div class="sum-card-v8 added">
                    <div class="sc-icon-v8"><i class="fas fa-plus-circle"></i></div>
                    <div class="sc-content">
                        <div class="sc-label-v8">Stock Added</div>
                        <div class="sc-value-v8">+<?= number_format($sm['added_qty']) ?> <span class="unit-v8">units</span></div>
                        <div class="sc-sub-v8"><?= $sm['added_count'] ?> verified addition(s)</div>
                    </div>
                </div>
                
                <div class="sum-card-v8 out">
                    <div class="sc-icon-v8"><i class="fas fa-minus-circle"></i></div>
                    <div class="sc-content">
                        <div class="sc-label-v8">Stock Out</div>
                        <div class="sc-value-v8">−<?= number_format($sm['out_qty'] + $sm['cancelled_out_qty']) ?> <span class="unit-v8">units</span></div>
                        <div class="sc-sub-v8">
                            <?= $sm['out_count'] + $sm['cancelled_out_count'] ?> movement(s)
                            <?php if ($sm['cancelled_out_qty'] > 0): ?>
                                <br><small style="color:var(--warning);font-weight:700;">
                                    (incl. <?= number_format($sm['cancelled_out_qty']) ?> cancelled OUT)
                                </small>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <?php if ($sm['cancelled_returned_qty'] > 0): ?>
                <div class="sum-card-v8 returned">
                    <div class="sc-icon-v8"><i class="fas fa-undo"></i></div>
                    <div class="sc-content">
                        <div class="sc-label-v8">Cancelled Returned</div>
                        <div class="sc-value-v8">+<?= number_format($sm['cancelled_returned_qty']) ?> <span class="unit-v8">units</span></div>
                        <div class="sc-sub-v8"><?= $sm['cancelled_returned_count'] ?> verified return(s)</div>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if ($sm['unverified_return_qty'] > 0): ?>
                <div class="sum-card-v8 unverified">
                    <div class="sc-icon-v8"><i class="fas fa-question-circle"></i></div>
                    <div class="sc-content">
                        <div class="sc-label-v8">Unverified Returns</div>
                        <div class="sc-value-v8">+<?= number_format($sm['unverified_return_qty']) ?> <span class="unit-v8">units</span></div>
                        <div class="sc-sub-v8"><?= $sm['unverified_return_count'] ?> unverified</div>
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="sum-card-v8 prescription">
                    <div class="sc-icon-v8"><i class="fas fa-prescription"></i></div>
                    <div class="sc-content">
                        <div class="sc-label-v8">Prescription Out</div>
                        <div class="sc-value-v8"><?= number_format($sm['prescription_qty']) ?> <span class="unit-v8">units</span></div>
                        <div class="sc-sub-v8"><?= $sm['prescription_count'] ?> Rx movement(s)</div>
                    </div>
                </div>
                
                <div class="sum-card-v8 otc">
                    <div class="sc-icon-v8"><i class="fas fa-cash-register"></i></div>
                    <div class="sc-content">
                        <div class="sc-label-v8">OTC Sold</div>
                        <div class="sc-value-v8"><?= number_format($sm['otc_qty']) ?> <span class="unit-v8">units</span></div>
                        <div class="sc-sub-v8"><?= $sm['otc_count'] ?> OTC sale(s)</div>
                    </div>
                </div>
            </div>

            <?php if (count($additions) > 0): ?>
            <div class="table-card">
                <div class="table-header green">
                    <span class="title"><i class="fas fa-plus-circle"></i> Stock Added (IN Movements)</span>
                    <span class="count"><?= count($additions) ?> addition(s) • +<?= number_format($sm['added_qty']) ?> units</span>
                </div>
                <div class="table-scroll-wrapper">
                    <table class="data-table">
                        <thead class="green">
                            <tr><th>#</th><th>Date</th><th>Qty Added</th><th>Before</th><th>After</th><th>Added By</th><th>Notes</th></tr>
                        </thead>
                        <tbody>
                            <?php $i=1; foreach ($additions as $a): ?>
                            <tr class="in-row">
                                <td style="font-family:var(--font-mono);font-weight:700;color:var(--text-secondary);"><?= $i++ ?></td>
                                <td style="font-size:0.72rem;"><?= date('d M Y, H:i', strtotime($a['added_at'])) ?></td>
                                <td><span style="background:var(--success-bg);color:var(--success);padding:3px 10px;border-radius:6px;font-weight:800;font-family:var(--font-mono);">+<?= number_format($a['quantity']) ?></span></td>
                                <td style="font-family:var(--font-mono);color:var(--text-secondary);"><?= number_format($a['previous_stock']) ?></td>
                                <td style="font-family:var(--font-mono);font-weight:700;"><?= number_format($a['new_stock']) ?></td>
                                <td><div style="font-weight:700;font-size:0.78rem;"><i class="fas fa-user-plus" style="color:var(--success);"></i> <?= htmlspecialchars($a['added_by_name']) ?></div></td>
                                <td style="font-size:0.68rem;color:var(--text-secondary);max-width:300px;"><?= htmlspecialchars(substr($a['notes'], 0, 120)) ?><?= strlen($a['notes']) > 120 ? '...' : '' ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>

            <div class="table-card">
                <div class="table-header">
                    <span class="title"><i class="fas fa-list"></i> All Movements — <?= htmlspecialchars($medicine_details['name']) ?></span>
                    <span class="count"><?= count($movs) ?> movement(s)</span>
                </div>
                <?php if (count($movs) > 0): ?>
                <div class="table-scroll-wrapper">
                    <table class="data-table">
                        <thead>
                            <tr><th>#</th><th>Date</th><th>Type</th><th>Category</th><th style="text-align:center;">Qty</th><th style="text-align:right;">Before</th><th style="text-align:right;">After</th><th>Performed By</th><th>Notes</th></tr>
                        </thead>
                        <tbody>
                            <?php $i = 1; foreach ($movs as $m):
                                $mt = strtolower($m['movement_type']);
                                $ref_type = $m['reference_type'] ?? '';
                                $ref_id = $m['reference_id'] ?? null;
                                $patient_id = $m['patient_id'] ?? null;
                                $notes_m = $m['notes'] ?? '';
                                
                                $is_verified = false;
                                if ($mt === 'in' && (stripos($notes_m, 'Stock returned') === 0 || $ref_type === 'cancelled_item')) {
                                    $is_verified = verifyCancelledReturn($db, $ref_id, $patient_id, $medicine_details['name']);
                                }
                                
                                $is_out_cancelled = false;
                                if ($mt === 'out' && $ref_type === 'prescription') {
                                    $is_out_cancelled = isOutCancelled($db, $ref_id, $patient_id, $medicine_details['name']);
                                }
                                
                                $category = getMovementCategory($notes_m, $mt, $ref_type, $is_verified, $is_out_cancelled);
                                
                                $row_class = $mt === 'in' ? 'in-row' : 'out-row';
                                if ($category === 'cancel') $row_class = 'return-row';
                                if ($category === 'unverified_return') $row_class = 'unverified-row';
                                if ($category === 'cancelled_out') $row_class = 'cancelled-row';
                            ?>
                            <tr class="<?= $row_class ?>">
                                <td style="font-family:var(--font-mono);font-weight:700;color:var(--text-secondary);"><?= $i++ ?></td>
                                <td style="font-size:0.72rem;"><?= date('d M Y, H:i', strtotime($m['created_at'])) ?></td>
                                <td><span class="status-badge <?= $mt ?>"><i class="fas fa-<?= $mt === 'in' ? 'arrow-down' : 'arrow-up' ?>"></i> <?= strtoupper($mt) ?></span></td>
                                <td>
                                    <?php 
                                    $cat_icons = [
                                        'prescription' => 'fa-prescription',
                                        'auto_dispense' => 'fa-robot',
                                        'otc' => 'fa-cash-register',
                                        'cancel' => 'fa-undo',
                                        'unverified_return' => 'fa-question-circle',
                                        'cancelled_out' => 'fa-ban',
                                        'lab_test' => 'fa-flask',
                                        'equipment' => 'fa-tools',
                                        'doctor_use' => 'fa-user-md'
                                    ];
                                    $icon = $cat_icons[$category] ?? 'fa-circle';
                                    $badge_class = in_array($category, array_keys($cat_icons)) ? $category : 'out'; 
                                    ?>
                                    <span class="status-badge <?= $badge_class ?>"><i class="fas <?= $icon ?>"></i> <?= strtoupper(str_replace('_', ' ', $category)) ?></span>
                                </td>
                                <td style="text-align:center;"><span style="font-family:var(--font-mono);font-weight:800;font-size:0.85rem;color:<?= $mt === 'in' ? 'var(--success)' : 'var(--danger)' ?>;"><?= $mt === 'in' ? '+' : '−' ?><?= number_format($m['quantity']) ?></span></td>
                                <td style="text-align:right;font-family:var(--font-mono);color:var(--text-secondary);"><?= number_format($m['previous_stock']) ?></td>
                                <td style="text-align:right;font-family:var(--font-mono);font-weight:700;"><?= number_format($m['new_stock']) ?></td>
                                <td><div style="font-weight:700;font-size:0.75rem;"><?= htmlspecialchars($m['performed_by_name'] ?? 'System') ?></div><?php if (!empty($m['performed_by_username'])): ?><div style="font-size:0.62rem;color:var(--text-secondary);">@<?= htmlspecialchars($m['performed_by_username']) ?></div><?php endif; ?></td>
                                <td style="font-size:0.68rem;color:var(--text-secondary);max-width:400px;"><?= htmlspecialchars(substr($m['notes'] ?? '', 0, 150)) ?><?= strlen($m['notes'] ?? '') > 150 ? '...' : '' ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="empty-state"><i class="fas fa-inbox"></i><p>No movements found for this period</p></div>
                <?php endif; ?>
            </div>

        <?php else: ?>
            <div class="table-card">
                <div class="table-header"><span class="title"><i class="fas fa-pills"></i> Medicine Tracking</span><span class="count">Search to view details</span></div>
                <div class="empty-state"><i class="fas fa-search"></i><p>Search medicine above or click on Top 5 cards</p></div>
            </div>
        <?php endif; ?>

    <?php else: ?>

        <!-- EQUIPMENT TAB -->
        <?php if ($equipment_details): ?>
            <?php 
                $eq = $equipment_details['info']; 
                $sm = $equipment_details['summary']; 
                $pi = $equipment_details['period_info']; 
                $movs = $equipment_details['movements']; 
                $additions = $equipment_details['additions']; 
                $patients_map = $equipment_details['patients_map'];
                
                $total_in_display = $pi['total_in_calc'];
                $total_out_display = $pi['total_out_calc'];
                $final_calculated = $pi['stock_after_calculated'];
            ?>

            <div class="info-banner" style="border-left-color: var(--cyan);">
                <div class="ib-icon" style="background: linear-gradient(135deg, #0891B2, #0E7490);"><i class="fas fa-tools"></i></div>
                <div class="ib-content">
                    <div class="ib-title"><?= htmlspecialchars($equipment_details['name']) ?></div>
                    <div class="ib-meta">
                        <span><i class="fas fa-tag"></i> <?= htmlspecialchars($eq['category'] ?? 'N/A') ?></span>
                        <span><i class="fas fa-box"></i> Unit: <?= htmlspecialchars($eq['unit'] ?? 'N/A') ?></span>
                        <span style="color:var(--success);font-weight:800;"><i class="fas fa-warehouse"></i> Current Stock: <?= number_format($equipment_details['total_current_stock']) ?></span>
                        <span style="color:var(--primary);font-weight:800;"><i class="fas fa-store"></i> <?= htmlspecialchars($branch_name_display) ?></span>
                    </div>
                </div>
            </div>

            <?php if ($sm['unverified_return_qty'] > 0): ?>
            <div class="data-warning-banner">
                <div class="dw-icon"><i class="fas fa-exclamation-triangle"></i></div>
                <div class="dw-content">
                    <div class="dw-title"><i class="fas fa-database"></i> Data Integrity Warning</div>
                    <div class="dw-text">
                        Kuna <strong><?= number_format($sm['unverified_return_qty']) ?> units</strong> za "Stock returned" 
                        lakini hakuna cancellation record.
                        <strong>V8.6:</strong> Zimehesabiwa kama <strong>"Unverified Returns"</strong>.
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <div class="stock-flow-card">
                <div class="sf-header">
                    <div class="sf-title"><i class="fas fa-water"></i> Equipment Flow — All Items Combined</div>
                    <div class="sf-badge"><i class="fas fa-calendar"></i> <?= htmlspecialchars($pi['filter_label']) ?></div>
                </div>
                <div class="sf-flow">
                    <div class="sf-block before">
                        <div class="sf-block-label"><i class="fas fa-warehouse"></i> Before Period</div>
                        <div class="sf-block-value"><?= number_format($pi['stock_before']) ?> <span class="unit">units</span></div>
                    </div>
                    <div class="sf-arrow"><i class="fas fa-arrows-left-right"></i></div>
                    <div class="sf-block movement">
                        <div class="sf-block-label"><i class="fas fa-exchange-alt"></i> Movements</div>
                        <div class="sf-block-value"><?= number_format($total_in_display + $total_out_display) ?> <span class="unit">records</span></div>
                        <div class="sf-movements-grid">
                            <div class="sf-mov-item added"><div class="sf-mov-label">Added</div><div class="sf-mov-qty">+<?= number_format($sm['added_qty']) ?></div></div>
                            <div class="sf-mov-item out"><div class="sf-mov-label">Used</div><div class="sf-mov-qty">−<?= number_format($sm['out_qty'] + $sm['cancelled_out_qty']) ?></div></div>
                            <div class="sf-mov-item doctor"><div class="sf-mov-label">Doctor</div><div class="sf-mov-qty"><?= number_format($sm['doctor_qty']) ?></div></div>
                        </div>
                    </div>
                    <div class="sf-arrow"><i class="fas fa-equals"></i></div>
                    <div class="sf-block remaining">
                        <div class="sf-block-label"><i class="fas fa-boxes-packing"></i> After Period</div>
                        <div class="sf-block-value"><?= number_format($pi['stock_after']) ?> <span class="unit">units</span></div>
                        <div class="sf-block-meta"><span><i class="fas fa-database"></i> Current live: <?= number_format($equipment_details['total_current_stock']) ?></span></div>
                    </div>
                </div>
                
                <div style="text-align: center; padding: 14px; background: var(--bg-body); border-radius: var(--radius-md); font-family: var(--font-mono); font-weight: 800; border: 2px dashed var(--border-color); font-size: 0.95rem;">
                    <span style="color:#64748B;font-size:1.15rem;"><?= number_format($pi['stock_before']) ?></span>
                    <span style="color:var(--text-secondary);margin:0 8px;">+</span>
                    <span style="color:#059669;font-size:1.15rem;"><?= number_format($total_in_display) ?></span>
                    <span style="color:var(--text-secondary);margin:0 8px;">−</span>
                    <span style="color:#DC2626;font-size:1.15rem;"><?= number_format($total_out_display) ?></span>
                    <span style="color:var(--text-secondary);margin:0 8px;">=</span>
                    <span style="color:#059669;font-size:1.3rem;"><?= number_format($final_calculated) ?></span>
                    <div style="font-size:0.68rem;color:var(--text-muted);margin-top:6px;font-family:var(--font-primary);font-weight:600;">
                        IN = Added (<?= number_format($sm['added_qty']) ?>) + Returned (<?= number_format($sm['returned_qty']) ?>)
                        &nbsp;•&nbsp;
                        OUT = Out (<?= number_format($sm['out_qty']) ?>) + Cancelled Out (<?= number_format($sm['cancelled_out_qty']) ?>)
                    </div>
                </div>
            </div>

            <div class="summary-grid-v8">
                <div class="sum-card-v8 added">
                    <div class="sc-icon-v8"><i class="fas fa-plus-circle"></i></div>
                    <div class="sc-content">
                        <div class="sc-label-v8">Stock Added</div>
                        <div class="sc-value-v8">+<?= number_format($sm['added_qty']) ?></div>
                        <div class="sc-sub-v8"><?= $sm['added_count'] ?> record(s)</div>
                    </div>
                </div>
                
                <div class="sum-card-v8 out">
                    <div class="sc-icon-v8"><i class="fas fa-minus-circle"></i></div>
                    <div class="sc-content">
                        <div class="sc-label-v8">Total Used</div>
                        <div class="sc-value-v8">−<?= number_format($sm['out_qty'] + $sm['cancelled_out_qty']) ?></div>
                        <div class="sc-sub-v8">
                            <?= $sm['out_count'] + $sm['cancelled_out_count'] ?> use(s)
                            <?php if ($sm['cancelled_out_qty'] > 0): ?>
                                <br><small style="color:var(--warning);">(incl. <?= number_format($sm['cancelled_out_qty']) ?> cancelled)</small>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="sum-card-v8 doctor">
                    <div class="sc-icon-v8"><i class="fas fa-user-md"></i></div>
                    <div class="sc-content">
                        <div class="sc-label-v8">Doctor Used</div>
                        <div class="sc-value-v8"><?= number_format($sm['doctor_qty']) ?></div>
                        <div class="sc-sub-v8"><?= $sm['doctor_count'] ?> use(s)</div>
                    </div>
                </div>
                
                <div class="sum-card-v8 lab">
                    <div class="sc-icon-v8"><i class="fas fa-flask"></i></div>
                    <div class="sc-content">
                        <div class="sc-label-v8">Lab Tests</div>
                        <div class="sc-value-v8"><?= number_format($sm['lab_test_qty']) ?></div>
                        <div class="sc-sub-v8"><?= $sm['lab_test_count'] ?> test(s)</div>
                    </div>
                </div>
                
                <div class="sum-card-v8 otc">
                    <div class="sc-icon-v8"><i class="fas fa-cash-register"></i></div>
                    <div class="sc-content">
                        <div class="sc-label-v8">OTC Sales</div>
                        <div class="sc-value-v8"><?= number_format($sm['otc_qty']) ?></div>
                        <div class="sc-sub-v8"><?= $sm['otc_count'] ?> sale(s)</div>
                    </div>
                </div>
                
                <?php if ($sm['procedure_qty'] > 0): ?>
                <div class="sum-card-v8 procedure">
                    <div class="sc-icon-v8"><i class="fas fa-procedures"></i></div>
                    <div class="sc-content">
                        <div class="sc-label-v8">Procedures</div>
                        <div class="sc-value-v8"><?= number_format($sm['procedure_qty']) ?></div>
                        <div class="sc-sub-v8"><?= $sm['procedure_count'] ?> procedure(s)</div>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <div class="table-card">
                <div class="table-header blue">
                    <span class="title"><i class="fas fa-list"></i> All Movements — <?= htmlspecialchars($equipment_details['name']) ?></span>
                    <span class="count"><?= count($movs) ?> movement(s)</span>
                </div>
                <?php if (count($movs) > 0): ?>
                <div class="table-scroll-wrapper">
                    <table class="data-table">
                        <thead>
                            <tr><th>#</th><th>Date</th><th>Type</th><th>Category</th><th style="text-align:center;">Qty</th><th style="text-align:right;">Before</th><th style="text-align:right;">After</th><th>Performed By</th><th>Patient</th><th>Notes</th></tr>
                        </thead>
                        <tbody>
                            <?php $i=1; foreach ($movs as $m):
                                $mt = strtolower($m['movement_type']);
                                $ref_type = $m['reference_type'] ?? '';
                                $ref_id = $m['reference_id'] ?? null;
                                $patient_id_m = $m['patient_id'] ?? null;
                                $notes_m = $m['notes'] ?? '';
                                
                                $is_verified = false;
                                if ($mt === 'in' && (stripos($notes_m, 'Stock returned') === 0 || $ref_type === 'cancelled_item')) {
                                    $is_verified = verifyCancelledReturn($db, $ref_id, $patient_id_m, $equipment_details['name']);
                                }
                                
                                $is_out_cancelled = false;
                                if ($mt === 'out' && $ref_type === 'prescription') {
                                    $is_out_cancelled = isOutCancelled($db, $ref_id, $patient_id_m, $equipment_details['name']);
                                }
                                
                                $category = getMovementCategory($notes_m, $mt, $ref_type, $is_verified, $is_out_cancelled);
                                
                                $row_class = $mt === 'in' ? 'in-row' : 'out-row';
                                if ($category === 'cancel') $row_class = 'return-row';
                                if ($category === 'unverified_return') $row_class = 'unverified-row';
                                if ($category === 'cancelled_out') $row_class = 'cancelled-row';
                                elseif ($category === 'doctor_use') $row_class = 'doctor-row';
                            ?>
                            <tr class="<?= $row_class ?>">
                                <td style="font-family:var(--font-mono);font-weight:700;color:var(--text-secondary);"><?= $i++ ?></td>
                                <td style="font-size:0.72rem;"><?= date('d M Y, H:i', strtotime($m['created_at'])) ?></td>
                                <td><span class="status-badge <?= $mt ?>"><i class="fas fa-<?= $mt === 'in' ? 'arrow-down' : 'arrow-up' ?>"></i> <?= strtoupper($mt) ?></span></td>
                                <td>
                                    <?php $cat_icons = ['lab_test' => 'fa-flask','equipment' => 'fa-tools','otc' => 'fa-cash-register','procedure' => 'fa-procedures','prescription' => 'fa-prescription','doctor_use' => 'fa-user-md','cancel' => 'fa-undo','unverified_return' => 'fa-question-circle','cancelled_out' => 'fa-ban'];
                                    $icon = $cat_icons[$category] ?? 'fa-circle';
                                    $badge_class = in_array($category, array_keys($cat_icons)) ? $category : 'out'; ?>
                                    <span class="status-badge <?= $badge_class ?>"><i class="fas <?= $icon ?>"></i> <?= strtoupper(str_replace('_',' ',$category)) ?></span>
                                </td>
                                <td style="text-align:center;font-family:var(--font-mono);font-weight:800;color:<?= $mt === 'in' ? 'var(--success)' : 'var(--danger)' ?>;">
                                    <?= $mt === 'in' ? '+' : '−' ?><?= number_format($m['quantity']) ?>
                                </td>
                                <td style="text-align:right;font-family:var(--font-mono);color:var(--text-secondary);"><?= number_format($m['previous_stock']) ?></td>
                                <td style="text-align:right;font-family:var(--font-mono);font-weight:700;"><?= number_format($m['new_stock']) ?></td>
                                <td style="font-weight:700;font-size:0.75rem;">
                                    <?= htmlspecialchars($m['performed_by_name'] ?? 'System') ?>
                                    <?php if (!empty($m['performed_by_username'])): ?>
                                        <div style="font-size:0.6rem;color:var(--text-secondary);font-weight:500;">@<?= htmlspecialchars($m['performed_by_username']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td style="font-size:0.72rem;">
                                    <?php if (!empty($m['patient_id'])):
                                        $p_info = $patients_map[$m['patient_id']] ?? null;
                                        if ($p_info):
                                    ?>
                                        <div style="font-weight:700;"><?= htmlspecialchars($p_info['full_name']) ?></div>
                                        <div style="font-size:0.62rem;color:var(--text-secondary);"><?= htmlspecialchars($p_info['patient_id']) ?></div>
                                    <?php else: ?>
                                        <span style="color:var(--text-muted);">—</span>
                                    <?php endif; else: ?>
                                        <span style="color:var(--text-muted);">—</span>
                                    <?php endif; ?>
                                </td>
                                <td style="font-size:0.68rem;color:var(--text-secondary);max-width:400px;"><?= htmlspecialchars(substr($m['notes'] ?? '', 0, 150)) ?><?= strlen($m['notes'] ?? '') > 150 ? '...' : '' ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="empty-state"><i class="fas fa-inbox"></i><p>No movements found for this period</p></div>
                <?php endif; ?>
            </div>

        <?php else: ?>
            <div class="table-card">
                <div class="table-header"><span class="title"><i class="fas fa-tools"></i> Equipment Tracking</span><span class="count">Search to view details</span></div>
                <div class="empty-state"><i class="fas fa-search"></i><p>Search equipment above or click on Top 5 cards</p></div>
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
                            var safeName = (item.name || '').replace(/\\/g, '\\\\').replace(/'/g, "\\'").replace(/"/g, '&quot;');
                            html += '<div class="autocomplete-item" onclick="selectItem(' + item.id + ', \'' + safeName + '\')">';
                            html += '<div class="item-main">';
                            html += '<div class="item-icon"><i class="fas ' + (activeTab === 'medicine' ? 'fa-pills' : 'fa-tools') + '"></i></div>';
                            html += '<div>';
                            html += '<div class="item-name">' + (item.name || '') + '</div>';
                            html += '<div class="item-meta">ID: #' + item.id + '</div>';
                            html += '</div></div>';
                            html += '<div class="item-stock">' + stock + '</div></div>';
                        });
                        autocompleteBox.innerHTML = html;
                        autocompleteBox.classList.add('active');
                    } else {
                        autocompleteBox.innerHTML = '<div style="padding:16px;text-align:center;color:var(--text-secondary);">No results for "' + q + '"</div>';
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