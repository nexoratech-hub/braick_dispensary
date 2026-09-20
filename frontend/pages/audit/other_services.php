<?php
// ================================================================
// FILE: frontend/pages/audit/other_services.php
// AUDIT - OTHER SERVICES (V5 - VIEW + EDIT + DELETE UNIFIED)
// ✅ Tabs: Procedures & Equipments | Consultations | All Bills | OTC Bills
// ✅ View + Edit + Delete buttons kwenye KILA tab
// ✅ ALL ACTION BUTTONS SIZE SAWA (unified)
// ✅ CSS iliyoboreshwa
// ================================================================

if (session_status() === PHP_SESSION_NONE) session_start();

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

$user_id = $_SESSION['user_id'];
$user_full_name = $_SESSION['full_name'] ?? 'Audit User';
$user_role = $_SESSION['role'] ?? 'audit';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$profile_pic = $_SESSION['profile_pic'] ?? '';

require_once __DIR__ . '/../../../backend/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database connection error: " . $e->getMessage());
}

// ================================================================
// HELPERS
// ================================================================
function calculateAge($dob) {
    if (empty($dob)) return 'N/A';
    return (new DateTime($dob))->diff(new DateTime('today'))->y;
}

function buildFilterUrl($params_to_update = []) {
    $current = $_GET;
    foreach ($params_to_update as $key => $value) {
        if ($value === null || $value === '') unset($current[$key]);
        else $current[$key] = $value;
    }
    return '?' . http_build_query($current);
}

function getStatusBadge($status) {
    $map = [
        'pending'    => ['class' => 'warning', 'icon' => '⏳', 'label' => 'Pending'],
        'confirmed'  => ['class' => 'info',    'icon' => '🔵', 'label' => 'Confirmed'],
        'paid'       => ['class' => 'success', 'icon' => '✅', 'label' => 'Paid'],
        'dispensed'  => ['class' => 'cyan',    'icon' => '📦', 'label' => 'Dispensed'],
        'completed'  => ['class' => 'success', 'icon' => '✅', 'label' => 'Completed'],
        'cancelled'  => ['class' => 'danger',  'icon' => '❌', 'label' => 'Cancelled'],
        'partial'    => ['class' => 'purple',  'icon' => '⚡', 'label' => 'Partial'],
    ];
    return $map[$status] ?? ['class' => 'warning', 'icon' => '❓', 'label' => ucfirst($status)];
}

function formatTsh($amount) {
    return 'TSh ' . number_format((float)$amount, 0);
}

function getInitials($name) {
    if (empty($name)) return 'NA';
    $parts = preg_split('/\s+/', trim($name));
    if (count($parts) >= 2) {
        return strtoupper(substr($parts[0], 0, 1) . substr($parts[1], 0, 1));
    }
    return strtoupper(substr($name, 0, 2));
}

// ================================================================
// FILTERS
// ================================================================
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'procedures';
if (!in_array($active_tab, ['procedures', 'consultations', 'all_bills', 'otc_bills'])) {
    $active_tab = 'procedures';
}

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : '';
$selected_branch_id = isset($_GET['branch']) ? trim($_GET['branch']) : 'all';
$quick_filter = isset($_GET['quick']) ? $_GET['quick'] : 'all';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

$quick_date_from = '';
$quick_date_to = date('Y-m-d');
switch ($quick_filter) {
    case 'today':
        $quick_date_from = date('Y-m-d'); $quick_date_to = date('Y-m-d'); break;
    case '1w':
        $quick_date_from = date('Y-m-d', strtotime('-7 days')); break;
    case '1m':
        $quick_date_from = date('Y-m-d', strtotime('-1 month')); break;
    case '3m':
        $quick_date_from = date('Y-m-d', strtotime('-3 months')); break;
    case '1y':
        $quick_date_from = date('Y-m-d', strtotime('-1 year')); break;
    case 'custom':
        $quick_date_from = $date_from; $quick_date_to = $date_to; break;
    default:
        $quick_date_from = ''; $quick_date_to = ''; break;
}

$branches_list = [];
try {
    $stmt = $db->query("SELECT id, name FROM branches WHERE status = 'active' ORDER BY name");
    $branches_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

$selected_branch_name = 'All Branches';
if ($selected_branch_id !== 'all') {
    foreach ($branches_list as $b) {
        if ($b['id'] == $selected_branch_id) { $selected_branch_name = $b['name']; break; }
    }
}

$profile_pic_url = !empty($profile_pic)
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

// ================================================================
// TAB 1: PROCEDURES & EQUIPMENTS
// ================================================================
$procedures_data = [];
$procedures_array = [];
$procedures_stats = ['total'=>0, 'pending'=>0, 'completed'=>0, 'amount'=>0, 'equipment_count'=>0, 'procedure_count'=>0];

if ($active_tab === 'procedures') {
    $where = " WHERE bi.item_type IN ('procedure', 'equipment') AND bi.status != 'cancelled'";
    $params = [];
    
    if (!empty($search)) {
        $where .= " AND (bi.item_name LIKE ? OR pat.full_name LIKE ? OR pat.patient_id LIKE ?)";
        $sp = "%$search%";
        $params[] = $sp; $params[] = $sp; $params[] = $sp;
    }
    if ($selected_branch_id !== 'all') { 
        $where .= " AND bi.branch_id = ?"; 
        $params[] = (int)$selected_branch_id; 
    }
    if (!empty($quick_date_from)) { 
        $where .= " AND DATE(bi.created_at) >= ?"; 
        $params[] = $quick_date_from; 
    }
    if (!empty($quick_date_to)) { 
        $where .= " AND DATE(bi.created_at) <= ?"; 
        $params[] = $quick_date_to; 
    }
    if (!empty($status_filter)) {
        if (in_array($status_filter, ['pending','partial','paid','cancelled'])) {
            $where .= " AND b.status = ?";
            $params[] = $status_filter;
        } else {
            $where .= " AND bi.status = ?";
            $params[] = $status_filter;
        }
    }
    
    $sql = "
        SELECT bi.*, 
               bi.id as item_row_id,
               bi.item_name as procedure_name,
               bi.item_type,
               bi.unit_price as procedure_price,
               bi.status as item_status,
               bi.created_at as item_created_at,
               bi.item_id as catalog_id,
               bi.reference_id,
               bi.reference_type,
               b.id as bill_id, b.bill_number, b.status as bill_status,
               pat.id as patient_db_id, pat.full_name as patient_name, 
               pat.patient_id as patient_number,
               pat.phone as patient_phone, pat.gender as patient_gender, 
               pat.date_of_birth,
               v.id as visit_db_id, v.visit_number, v.visit_date, v.status as visit_status,
               doc.full_name as doctor_name, 
               br.name as branch_name
        FROM bill_items bi
        INNER JOIN bills b ON bi.bill_id = b.id
        LEFT JOIN patients pat ON bi.patient_id = pat.id
        LEFT JOIN visits v ON b.visit_id = v.id
        LEFT JOIN users doc ON v.doctor_id = doc.id
        LEFT JOIN branches br ON bi.branch_id = br.id
        $where
        ORDER BY b.created_at DESC, bi.id ASC
    ";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $procedures_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($procedures_rows as $row) {
        $pid = $row['patient_db_id'] ?? 0;
        $vid = $row['visit_db_id'] ?? 0;
        
        $row['category'] = ($row['item_type'] === 'equipment') ? 'Equipment' : 'Procedure';
        
        if ($row['item_type'] === 'equipment') {
            $procedures_stats['equipment_count']++;
        } else {
            $procedures_stats['procedure_count']++;
        }
        
        if (!isset($procedures_data[$pid])) {
            $procedures_data[$pid] = [
                'patient_id' => $pid, 
                'patient_name' => $row['patient_name'] ?? 'Unknown',
                'patient_number' => $row['patient_number'] ?? 'N/A', 
                'patient_phone' => $row['patient_phone'] ?? '',
                'patient_gender' => $row['patient_gender'] ?? '', 
                'date_of_birth' => $row['date_of_birth'] ?? '',
                'visits' => [], 'total_amount' => 0, 'total_items' => 0,
                'partial_bills' => 0,
                'bills_with_partial' => []
            ];
        }
        
        if ($row['bill_status'] === 'partial' && !in_array($row['bill_id'], $procedures_data[$pid]['bills_with_partial'])) {
            $procedures_data[$pid]['bills_with_partial'][] = $row['bill_id'];
            $procedures_data[$pid]['partial_bills']++;
        }
        
        if (!isset($procedures_data[$pid]['visits'][$vid])) {
            $procedures_data[$pid]['visits'][$vid] = [
                'visit_id' => $vid, 
                'visit_number' => $row['visit_number'] ?? 'N/A',
                'visit_date' => $row['visit_date'] ?? $row['item_created_at'], 
                'doctor_name' => $row['doctor_name'] ?? 'N/A',
                'visit_status' => $row['visit_status'] ?? 'N/A', 
                'items' => [], 'total_amount' => 0
            ];
        }
        $procedures_data[$pid]['visits'][$vid]['items'][] = $row;
        $procedures_data[$pid]['visits'][$vid]['total_amount'] += $row['procedure_price'] ?? 0;
        $procedures_data[$pid]['total_amount'] += $row['procedure_price'] ?? 0;
        $procedures_data[$pid]['total_items']++;
    }
    
    $procedures_array = array_values($procedures_data);
    foreach ($procedures_array as &$p) {
        $p['visit_count'] = count($p['visits']);
        $p['visits'] = array_values($p['visits']);
    }
    unset($p);
    
    $stmt = $db->prepare("SELECT COUNT(*) as c, COALESCE(SUM(bi.total_price),0) as s 
                          FROM bill_items bi 
                          INNER JOIN bills b ON bi.bill_id = b.id 
                          LEFT JOIN patients pat ON bi.patient_id = pat.id 
                          $where");
    $stmt->execute($params);
    $r = $stmt->fetch(PDO::FETCH_ASSOC);
    $procedures_stats['total'] = $r['c'] ?? 0;
    $procedures_stats['amount'] = $r['s'] ?? 0;
    
    $stmt = $db->prepare("SELECT COUNT(*) as c FROM bill_items bi 
                          INNER JOIN bills b ON bi.bill_id = b.id 
                          LEFT JOIN patients pat ON bi.patient_id = pat.id 
                          $where AND bi.status = 'pending'");
    $stmt->execute($params);
    $procedures_stats['pending'] = $stmt->fetch(PDO::FETCH_ASSOC)['c'] ?? 0;
    
    $stmt = $db->prepare("SELECT COUNT(*) as c FROM bill_items bi 
                          INNER JOIN bills b ON bi.bill_id = b.id 
                          LEFT JOIN patients pat ON bi.patient_id = pat.id 
                          $where AND bi.status = 'paid'");
    $stmt->execute($params);
    $procedures_stats['completed'] = $stmt->fetch(PDO::FETCH_ASSOC)['c'] ?? 0;
}

// ================================================================
// TAB 2: CONSULTATIONS
// ================================================================
$consultations_data = [];
$consultations_array = [];
$consultations_stats = ['total'=>0, 'pending'=>0, 'paid'=>0, 'partial'=>0, 'amount'=>0];

if ($active_tab === 'consultations') {
    $where = " WHERE bi.item_type = 'consultation' AND bi.status != 'cancelled'";
    $params = [];
    
    if (!empty($search)) {
        $where .= " AND (v.visit_number LIKE ? OR pat.full_name LIKE ? OR pat.patient_id LIKE ?)";
        $sp = "%$search%";
        $params[] = $sp; $params[] = $sp; $params[] = $sp;
    }
    if ($selected_branch_id !== 'all') { 
        $where .= " AND bi.branch_id = ?"; 
        $params[] = (int)$selected_branch_id; 
    }
    if (!empty($quick_date_from)) { 
        $where .= " AND DATE(v.visit_date) >= ?"; 
        $params[] = $quick_date_from; 
    }
    if (!empty($quick_date_to)) { 
        $where .= " AND DATE(v.visit_date) <= ?"; 
        $params[] = $quick_date_to; 
    }
    if (!empty($status_filter)) {
        if (in_array($status_filter, ['pending','partial','paid','cancelled'])) {
            $where .= " AND bi.status = ?";
            $params[] = $status_filter;
        } else {
            $where .= " AND v.status = ?";
            $params[] = $status_filter;
        }
    }
    
    $sql = "
        SELECT bi.*,
               bi.id as item_row_id,
               b.id as bill_id, b.bill_number, b.status as bill_status,
               v.id as visit_db_id, v.visit_number, v.visit_date, 
               v.visit_type, v.consultation_fee, v.diagnosis, v.symptoms,
               v.status as visit_status, v.payment_status, v.visit_total,
               pat.id as patient_db_id, pat.full_name as patient_name, 
               pat.patient_id as patient_number,
               pat.phone as patient_phone, pat.gender as patient_gender, 
               pat.date_of_birth,
               doc.full_name as doctor_name, 
               rec.full_name as receptionist_name,
               br.name as branch_name
        FROM bill_items bi
        INNER JOIN bills b ON bi.bill_id = b.id
        INNER JOIN visits v ON b.visit_id = v.id
        LEFT JOIN patients pat ON bi.patient_id = pat.id
        LEFT JOIN users doc ON v.doctor_id = doc.id
        LEFT JOIN users rec ON v.receptionist_id = rec.id
        LEFT JOIN branches br ON bi.branch_id = br.id
        $where
        ORDER BY v.created_at DESC, bi.id ASC
    ";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $consultations_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($consultations_rows as $row) {
        $pid = $row['patient_db_id'] ?? 0;
        $vid = $row['visit_db_id'] ?? 0;
        
        if (!isset($consultations_data[$pid])) {
            $consultations_data[$pid] = [
                'patient_id' => $pid, 'patient_name' => $row['patient_name'] ?? 'Unknown',
                'patient_number' => $row['patient_number'] ?? 'N/A', 
                'patient_phone' => $row['patient_phone'] ?? '',
                'patient_gender' => $row['patient_gender'] ?? '', 
                'date_of_birth' => $row['date_of_birth'] ?? '',
                'visits' => [], 'total_amount' => 0, 'total_items' => 0,
                'partial_bills' => 0,
                'bills_with_partial' => []
            ];
        }
        
        if ($row['bill_status'] === 'partial' && !in_array($row['bill_id'], $consultations_data[$pid]['bills_with_partial'])) {
            $consultations_data[$pid]['bills_with_partial'][] = $row['bill_id'];
            $consultations_data[$pid]['partial_bills']++;
        }
        
        $consultations_data[$pid]['visits'][$vid] = [
            'visit_id' => $vid, 'visit_number' => $row['visit_number'] ?? 'N/A',
            'visit_date' => $row['visit_date'] ?? $row['created_at'], 
            'doctor_name' => $row['doctor_name'] ?? 'N/A',
            'receptionist_name' => $row['receptionist_name'] ?? 'N/A', 
            'visit_type' => $row['visit_type'] ?? 'N/A',
            'consultation_fee' => $row['consultation_fee'] ?? 0, 
            'diagnosis' => $row['diagnosis'] ?? '',
            'symptoms' => $row['symptoms'] ?? '', 
            'status' => $row['visit_status'] ?? 'N/A',
            'payment_status' => $row['payment_status'] ?? 'N/A', 
            'visit_total' => $row['visit_total'] ?? 0,
            'bill_status' => $row['bill_status'] ?? 'pending',
            'bill_number' => $row['bill_number'] ?? null,
            'bill_id' => $row['bill_id'] ?? null,
            'item_id' => $row['item_row_id'] ?? null,
            'item_status' => $row['status'] ?? 'pending',
        ];
        $consultations_data[$pid]['total_amount'] += $row['consultation_fee'] ?? 0;
        $consultations_data[$pid]['total_items']++;
    }
    
    $consultations_array = array_values($consultations_data);
    foreach ($consultations_array as &$p) {
        $p['visit_count'] = count($p['visits']);
        $p['visits'] = array_values($p['visits']);
    }
    unset($p);
    
    $stmt = $db->prepare("SELECT COUNT(*) as c, COALESCE(SUM(v.consultation_fee),0) as s 
                          FROM bill_items bi
                          INNER JOIN bills b ON bi.bill_id = b.id
                          INNER JOIN visits v ON b.visit_id = v.id
                          LEFT JOIN patients pat ON bi.patient_id = pat.id
                          $where");
    $stmt->execute($params);
    $r = $stmt->fetch(PDO::FETCH_ASSOC);
    $consultations_stats['total'] = $r['c'] ?? 0;
    $consultations_stats['amount'] = $r['s'] ?? 0;
    
    $stmt = $db->prepare("SELECT COUNT(*) as c FROM bill_items bi
                          INNER JOIN bills b ON bi.bill_id = b.id
                          INNER JOIN visits v ON b.visit_id = v.id
                          LEFT JOIN patients pat ON bi.patient_id = pat.id
                          $where AND bi.status = 'pending'");
    $stmt->execute($params);
    $consultations_stats['pending'] = $stmt->fetch(PDO::FETCH_ASSOC)['c'] ?? 0;
    
    $stmt = $db->prepare("SELECT COUNT(*) as c FROM bill_items bi
                          INNER JOIN bills b ON bi.bill_id = b.id
                          INNER JOIN visits v ON b.visit_id = v.id
                          LEFT JOIN patients pat ON bi.patient_id = pat.id
                          $where AND bi.status = 'paid'");
    $stmt->execute($params);
    $consultations_stats['paid'] = $stmt->fetch(PDO::FETCH_ASSOC)['c'] ?? 0;
    
    $stmt = $db->prepare("SELECT COUNT(*) as c FROM bill_items bi
                          INNER JOIN bills b ON bi.bill_id = b.id
                          INNER JOIN visits v ON b.visit_id = v.id
                          LEFT JOIN patients pat ON bi.patient_id = pat.id
                          $where AND bi.status = 'partial'");
    $stmt->execute($params);
    $consultations_stats['partial'] = $stmt->fetch(PDO::FETCH_ASSOC)['c'] ?? 0;
}

// ================================================================
// TAB 3: ALL BILLS
// ================================================================
$bills_data = [];
$bills_array = [];
$bills_stats = ['total_bills'=>0, 'paid'=>0, 'pending'=>0, 'partial'=>0, 
                'total_paid_amt'=>0, 'total_pending_amt'=>0, 
                'total_premium'=>0, 'total_discount'=>0];

if ($active_tab === 'all_bills') {
    $where = " WHERE 1=1";
    $params = [];
    if (!empty($search)) {
        $where .= " AND (b.bill_number LIKE ? OR pat.full_name LIKE ? OR pat.patient_id LIKE ?)";
        $sp = "%$search%";
        $params[] = $sp; $params[] = $sp; $params[] = $sp;
    }
    if (!empty($status_filter)) { $where .= " AND b.status = ?"; $params[] = $status_filter; }
    if ($selected_branch_id !== 'all') { $where .= " AND b.branch_id = ?"; $params[] = (int)$selected_branch_id; }
    if (!empty($quick_date_from)) { $where .= " AND DATE(b.created_at) >= ?"; $params[] = $quick_date_from; }
    if (!empty($quick_date_to)) { $where .= " AND DATE(b.created_at) <= ?"; $params[] = $quick_date_to; }
    
    $sql = "
        SELECT b.*, pat.full_name as patient_name, pat.patient_id as patient_number,
               pat.phone as patient_phone, pat.gender as patient_gender, 
               pat.date_of_birth,
               br.name as branch_name, v.visit_number, v.visit_date, v.diagnosis,
               doc.full_name as doctor_name, rec.full_name as receptionist_name
        FROM bills b
        LEFT JOIN patients pat ON b.patient_id = pat.id
        LEFT JOIN branches br ON b.branch_id = br.id
        LEFT JOIN visits v ON b.visit_id = v.id
        LEFT JOIN users doc ON v.doctor_id = doc.id
        LEFT JOIN users rec ON v.receptionist_id = rec.id
        $where ORDER BY b.created_at DESC
    ";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $bills_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($bills_rows as $bill) {
        $pid = $bill['patient_id'] ?? 0;
        $vid = $bill['visit_id'] ?? 0;
        
        $received_by = null;
        $payments_count = 0;
        $all_payments = [];
        if (($bill['paid_amount'] ?? 0) > 0) {
            $stmt_pay = $db->prepare("
                SELECT p.amount, p.received_at, p.payment_method, p.receipt_number,
                       u.full_name as received_by_name, u.role as received_by_role
                FROM payments p
                LEFT JOIN users u ON p.received_by = u.id
                WHERE p.bill_id = ?
                ORDER BY p.received_at DESC
            ");
            $stmt_pay->execute([$bill['id']]);
            $all_payments = $stmt_pay->fetchAll(PDO::FETCH_ASSOC);
            $payments_count = count($all_payments);
            $received_by = $all_payments[0] ?? null;
        }
        $bill['received_by'] = $received_by;
        $bill['payments_count'] = $payments_count;
        $bill['all_payments'] = $all_payments;
        
        if (!isset($bills_data[$pid])) {
            $bills_data[$pid] = [
                'patient_id' => $pid, 'patient_name' => $bill['patient_name'] ?? 'Unknown',
                'patient_number' => $bill['patient_number'] ?? 'N/A', 
                'patient_phone' => $bill['patient_phone'] ?? '',
                'patient_gender' => $bill['patient_gender'] ?? '', 
                'date_of_birth' => $bill['date_of_birth'] ?? '',
                'visits' => [], 'total_bills' => 0, 'total_paid' => 0, 'total_pending' => 0,
                'total_partial' => 0, 'total_premium' => 0, 'total_discount' => 0, 
                'total_amount' => 0, 'total_paid_amt' => 0, 'total_balance' => 0
            ];
        }
        
        if (!isset($bills_data[$pid]['visits'][$vid])) {
            $bills_data[$pid]['visits'][$vid] = [
                'visit_id' => $vid, 'visit_number' => $bill['visit_number'] ?? 'N/A',
                'visit_date' => $bill['visit_date'] ?? $bill['created_at'],
                'doctor_name' => $bill['doctor_name'] ?? 'N/A',
                'receptionist_name' => $bill['receptionist_name'] ?? 'N/A',
                'diagnosis' => $bill['diagnosis'] ?? '',
                'bills' => [], 'total_amount' => 0,
                'sum_total' => 0, 'sum_paid' => 0, 'sum_balance' => 0,
                'sum_discount' => 0, 'sum_premium' => 0
            ];
        }
        
        $stmtItems = $db->prepare("
            SELECT * FROM bill_items 
            WHERE bill_id = ? 
            ORDER BY 
                CASE item_type 
                    WHEN 'consultation' THEN 1
                    WHEN 'lab_test' THEN 2
                    WHEN 'medication' THEN 3
                    WHEN 'procedure' THEN 4
                    WHEN 'equipment' THEN 5
                    ELSE 6
                END, id ASC
        ");
        $stmtItems->execute([$bill['id']]);
        $bill['items'] = $stmtItems->fetchAll(PDO::FETCH_ASSOC);
        
        $bills_data[$pid]['visits'][$vid]['bills'][] = $bill;
        $bills_data[$pid]['visits'][$vid]['total_amount'] += $bill['total_amount'];
        $bills_data[$pid]['visits'][$vid]['sum_total'] += $bill['total_amount'] ?? 0;
        $bills_data[$pid]['visits'][$vid]['sum_paid'] += $bill['paid_amount'] ?? 0;
        $bills_data[$pid]['visits'][$vid]['sum_balance'] += $bill['balance'] ?? 0;
        $bills_data[$pid]['visits'][$vid]['sum_discount'] += $bill['total_discount'] ?? 0;
        $bills_data[$pid]['visits'][$vid]['sum_premium'] += $bill['premium_amount'] ?? 0;
        
        $bills_data[$pid]['total_bills']++;
        $bills_data[$pid]['total_amount'] += $bill['total_amount'];
        $bills_data[$pid]['total_paid_amt'] += $bill['paid_amount'] ?? 0;
        $bills_data[$pid]['total_balance'] += $bill['balance'] ?? 0;
        
        if ($bill['status'] === 'paid') $bills_data[$pid]['total_paid']++;
        elseif ($bill['status'] === 'pending') $bills_data[$pid]['total_pending']++;
        elseif ($bill['status'] === 'partial') $bills_data[$pid]['total_partial']++;
        
        $bills_data[$pid]['total_premium'] += $bill['premium_amount'] ?? 0;
        $bills_data[$pid]['total_discount'] += $bill['total_discount'] ?? 0;
    }
    
    $bills_array = array_values($bills_data);
    foreach ($bills_array as &$p) {
        $p['visit_count'] = count($p['visits']);
        $p['visits'] = array_values($p['visits']);
        $p['partial_bills'] = $p['total_partial'];
    }
    unset($p);
    
    $stmt = $db->prepare("SELECT COUNT(*) as c FROM bills b LEFT JOIN patients pat ON b.patient_id = pat.id " . $where);
    $stmt->execute($params);
    $bills_stats['total_bills'] = $stmt->fetch(PDO::FETCH_ASSOC)['c'] ?? 0;
    
    $stmt = $db->prepare("SELECT COUNT(*) as c, COALESCE(SUM(paid_amount),0) as s FROM bills b LEFT JOIN patients pat ON b.patient_id = pat.id " . $where . " AND b.status = 'paid'");
    $stmt->execute($params);
    $r = $stmt->fetch(PDO::FETCH_ASSOC);
    $bills_stats['paid'] = $r['c'] ?? 0;
    $bills_stats['total_paid_amt'] = $r['s'] ?? 0;
    
    $stmt = $db->prepare("SELECT COUNT(*) as c, COALESCE(SUM(total_amount),0) as s FROM bills b LEFT JOIN patients pat ON b.patient_id = pat.id " . $where . " AND b.status = 'pending'");
    $stmt->execute($params);
    $r = $stmt->fetch(PDO::FETCH_ASSOC);
    $bills_stats['pending'] = $r['c'] ?? 0;
    $bills_stats['total_pending_amt'] = $r['s'] ?? 0;
    
    $stmt = $db->prepare("SELECT COUNT(*) as c FROM bills b LEFT JOIN patients pat ON b.patient_id = pat.id " . $where . " AND b.status = 'partial'");
    $stmt->execute($params);
    $bills_stats['partial'] = $stmt->fetch(PDO::FETCH_ASSOC)['c'] ?? 0;
    
    $stmt = $db->prepare("SELECT COALESCE(SUM(premium_amount),0) as s FROM bills b LEFT JOIN patients pat ON b.patient_id = pat.id " . $where);
    $stmt->execute($params);
    $bills_stats['total_premium'] = $stmt->fetch(PDO::FETCH_ASSOC)['s'] ?? 0;
    
    $stmt = $db->prepare("SELECT COALESCE(SUM(total_discount),0) as s FROM bills b LEFT JOIN patients pat ON b.patient_id = pat.id " . $where);
    $stmt->execute($params);
    $bills_stats['total_discount'] = $stmt->fetch(PDO::FETCH_ASSOC)['s'] ?? 0;
}

// ================================================================
// TAB 4: OTC BILLS
// ================================================================
$otc_data = [];
$otc_array = [];
$otc_stats = ['total'=>0, 'paid'=>0, 'pending'=>0, 'partial'=>0, 'amount'=>0];

if ($active_tab === 'otc_bills') {
    $where = " WHERE 1=1";
    $params = [];
    if (!empty($search)) {
        $where .= " AND (s.sale_number LIKE ? OR s.customer_name LIKE ? OR s.customer_phone LIKE ?)";
        $sp = "%$search%";
        $params[] = $sp; $params[] = $sp; $params[] = $sp;
    }
    if (!empty($status_filter)) { $where .= " AND s.payment_status = ?"; $params[] = $status_filter; }
    if ($selected_branch_id !== 'all') { $where .= " AND s.branch_id = ?"; $params[] = (int)$selected_branch_id; }
    if (!empty($quick_date_from)) { $where .= " AND DATE(s.created_at) >= ?"; $params[] = $quick_date_from; }
    if (!empty($quick_date_to)) { $where .= " AND DATE(s.created_at) <= ?"; $params[] = $quick_date_to; }
    
    $sql = "
        SELECT s.*, u.full_name as sold_by_name, u.role as sold_by_role, b.name as branch_name
        FROM otc_sales s
        LEFT JOIN users u ON s.sold_by = u.id
        LEFT JOIN branches b ON s.branch_id = b.id
        $where ORDER BY s.created_at DESC
    ";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $otc_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($otc_rows as $sale) {
        $key = !empty($sale['customer_phone']) ? $sale['customer_phone'] : strtolower(trim($sale['customer_name'] ?? 'walk-in'));
        if (!isset($otc_data[$key])) {
            $otc_data[$key] = [
                'customer_key' => $key, 'customer_name' => $sale['customer_name'] ?? 'Walk-in Customer',
                'customer_phone' => $sale['customer_phone'] ?? '', 
                'patient_id' => $sale['patient_id'] ?? null,
                'sales' => [], 'total_sales' => 0, 'total_items' => 0, 'total_amount' => 0,
                'total_paid' => 0, 'total_pending' => 0, 'total_partial' => 0
            ];
        }
        
        $stmtItems = $db->prepare("SELECT * FROM otc_sale_items WHERE sale_id = ? ORDER BY id ASC");
        $stmtItems->execute([$sale['id']]);
        $sale['items'] = $stmtItems->fetchAll(PDO::FETCH_ASSOC);
        
        $otc_data[$key]['sales'][] = $sale;
        $otc_data[$key]['total_sales']++;
        $otc_data[$key]['total_items'] += count($sale['items']);
        $otc_data[$key]['total_amount'] += $sale['total_amount'] ?? 0;
        if (($sale['payment_status'] ?? '') === 'paid') $otc_data[$key]['total_paid']++;
        if (($sale['payment_status'] ?? '') === 'pending') $otc_data[$key]['total_pending']++;
        if (($sale['payment_status'] ?? '') === 'partial') $otc_data[$key]['total_partial']++;
    }
    
    $otc_array = array_values($otc_data);
    foreach ($otc_array as &$c) {
        $c['partial_bills'] = $c['total_partial'];
    }
    unset($c);
    
    $stmt = $db->prepare("SELECT COUNT(*) as c, COALESCE(SUM(total_amount),0) as s FROM otc_sales s " . $where);
    $stmt->execute($params);
    $r = $stmt->fetch(PDO::FETCH_ASSOC);
    $otc_stats['total'] = $r['c'] ?? 0;
    $otc_stats['amount'] = $r['s'] ?? 0;
    
    $stmt = $db->prepare("SELECT COUNT(*) as c FROM otc_sales s " . $where . " AND s.payment_status = 'paid'");
    $stmt->execute($params);
    $otc_stats['paid'] = $stmt->fetch(PDO::FETCH_ASSOC)['c'] ?? 0;
    
    $stmt = $db->prepare("SELECT COUNT(*) as c FROM otc_sales s " . $where . " AND s.payment_status = 'pending'");
    $stmt->execute($params);
    $otc_stats['pending'] = $stmt->fetch(PDO::FETCH_ASSOC)['c'] ?? 0;
    
    $stmt = $db->prepare("SELECT COUNT(*) as c FROM otc_sales s " . $where . " AND s.payment_status = 'partial'");
    $stmt->execute($params);
    $otc_stats['partial'] = $stmt->fetch(PDO::FETCH_ASSOC)['c'] ?? 0;
}

include_once __DIR__ . '/../../components/audit_header.php';
include_once __DIR__ . '/../../components/audit_sidebar.php';
?>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<style>
:root {
    --font-mono: 'JetBrains Mono', 'Courier New', monospace;
    --font-main: 'Inter', -apple-system, sans-serif;
    --primary: #0B5ED7;
    --primary-dark: #0A4CA8;
    --primary-light: #6EA8FE;
    --primary-bg: #E8F0FE;
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
    --gray-50: #F8FAFC;
    --bg-body: #F1F5F9;
    --bg-card: #FFFFFF;
    --text-primary: #1E293B;
    --text-secondary: #64748B;
    --border-color: #E2E8F0;
    --shadow: 0 1px 3px rgba(0,0,0,0.06);
}
[data-theme="dark"] {
    --bg-body: #0F172A; --bg-card: #1E293B;
    --text-primary: #F1F5F9; --text-secondary: #94A3B8;
    --border-color: #334155;
}
body { font-family: var(--font-main) !important; }
.stat-number, .stat-amount, .stat-value, .stat-pill,
.patient-avatar, .amount-cell, .data-table td:first-child,
.visit-number-display, .visit-date-display {
    font-family: var(--font-mono) !important;
    font-variant-numeric: tabular-nums;
}

/* PAGE HEADER */
.page-header-custom {
    background: linear-gradient(135deg, #0B5ED7, #0A4CA8, #083C8A);
    border-radius: 16px; padding: 20px 28px; margin-bottom: 20px;
    display: flex; flex-wrap: wrap; justify-content: space-between;
    align-items: center; gap: 16px;
    box-shadow: 0 4px 20px rgba(11, 94, 215, 0.3);
    position: relative; overflow: hidden;
}
.page-header-custom::before {
    content: ''; position: absolute; top: -50%; right: -20%;
    width: 300px; height: 300px;
    background: rgba(255,255,255,0.05);
    border-radius: 50%; pointer-events: none;
}
.page-header-custom .page-title {
    color: white; font-size: 1.4rem; font-weight: 700;
    display: flex; align-items: center; gap: 12px;
    flex-wrap: wrap; position: relative; z-index: 1; margin: 0;
}
.page-header-custom .page-title i { font-size: 1.6rem; opacity: 0.9; }
.page-header-custom .page-subtitle {
    color: rgba(255,255,255,0.85); font-size: 0.82rem;
    display: flex; align-items: center; gap: 8px;
    flex-wrap: wrap; position: relative; z-index: 1; margin-top: 4px;
}
.page-header-custom .role-badge-display {
    background: rgba(255,255,255,0.2); color: white;
    padding: 3px 12px; border-radius: 20px;
    font-size: 0.6rem; font-weight: 600; text-transform: uppercase;
}
.page-header-custom .role-badge-display.full-access {
    background: linear-gradient(135deg, #10B981, #059669);
}
.page-header-custom .branch-tag {
    background: rgba(255,255,255,0.15); color: white;
    padding: 2px 10px; border-radius: 20px;
    font-size: 0.65rem; font-weight: 500;
    display: inline-flex; align-items: center; gap: 4px;
}
.page-header-custom .btn-outline-light {
    background: rgba(255,255,255,0.12); color: white;
    border: 1px solid rgba(255,255,255,0.2);
    padding: 6px 14px; border-radius: 8px;
    font-weight: 500; font-size: 0.75rem;
    text-decoration: none;
    display: inline-flex; align-items: center; gap: 6px;
    position: relative; z-index: 1;
}
.page-header-custom .btn-outline-light:hover {
    background: rgba(255,255,255,0.25);
    transform: translateY(-2px); color: white;
}

/* TABS */
.tabs-container {
    display: flex; gap: 6px; background: var(--bg-card);
    border-radius: 12px; padding: 6px; margin-bottom: 18px;
    border: 2px solid var(--border-color); overflow-x: auto;
}
.tab-btn {
    padding: 10px 20px; border-radius: 8px;
    font-weight: 700; font-size: 0.78rem; text-decoration: none;
    color: var(--text-secondary); background: transparent;
    border: none; cursor: pointer;
    display: inline-flex; align-items: center; gap: 6px;
    white-space: nowrap; transition: all 0.2s ease;
    flex: 1; justify-content: center;
}
.tab-btn:hover { background: var(--primary-bg); color: var(--primary); }
.tab-btn.active {
    background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
    color: white; box-shadow: 0 3px 10px rgba(11, 94, 215, 0.3);
}
.tab-btn .tab-count {
    background: rgba(255,255,255,0.25); padding: 2px 8px;
    border-radius: 10px; font-size: 0.6rem; font-weight: 800;
}
.tab-btn:not(.active) .tab-count {
    background: var(--primary-bg); color: var(--primary);
}

/* STATS GRID */
.stats-grid-5 {
    display: grid; grid-template-columns: repeat(5, 1fr);
    gap: 12px; margin-bottom: 20px;
}
.stats-grid-4 { grid-template-columns: repeat(4, 1fr); }
.stat-card-custom {
    border-radius: 12px; padding: 14px 16px;
    display: flex; flex-direction: column;
    transition: all 0.4s ease;
    box-shadow: 0 4px 16px rgba(0,0,0,0.12);
    color: white; position: relative; overflow: hidden;
    min-height: 95px; text-decoration: none;
}
.stat-card-custom::before {
    content: ''; position: absolute;
    top: -50%; right: -20%; width: 140px; height: 140px;
    background: rgba(255,255,255,0.06); border-radius: 50%;
}
.stat-card-custom:hover { transform: translateY(-4px) scale(1.01); }
.stat-card-custom .stat-icon {
    width: 38px; height: 38px; border-radius: 9px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1rem; background: rgba(255,255,255,0.18); color: white;
    border: 1px solid rgba(255,255,255,0.12); margin-bottom: 4px;
}
.stat-card-custom .stat-label {
    font-size: 0.55rem; color: rgba(255,255,255,0.85);
    font-weight: 600; text-transform: uppercase;
    letter-spacing: 0.06em; margin: 0;
}
.stat-card-custom .stat-number {
    font-size: 1.7rem; font-weight: 800; color: white;
    margin: 0; line-height: 1.1;
}
.stat-card-custom .stat-amount {
    font-size: 0.75rem; font-weight: 600;
    color: rgba(255,255,255,0.9); margin-top: 2px;
}
.card-blue-1 { background: linear-gradient(135deg, #3B82F6, #0B5ED7, #0A4CA8); }
.card-green  { background: linear-gradient(135deg, #10B981, #059669, #047857); }
.card-red    { background: linear-gradient(135deg, #DC2626, #B91C1C, #991B1B); }
.card-purple { background: linear-gradient(135deg, #7C3AED, #6D28D9, #5B21B6); }
.card-orange { background: linear-gradient(135deg, #F59E0B, #D97706, #B45309); }
.card-cyan   { background: linear-gradient(135deg, #06B6D4, #0891B2, #0E7490); }

/* FULL ACCESS BANNER */
.full-access-banner {
    background: linear-gradient(135deg, #D1FAE5, #A7F3D0);
    border: 2px solid #10B981;
    border-radius: 12px;
    padding: 14px 20px;
    margin-bottom: 18px;
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.2);
}
[data-theme="dark"] .full-access-banner {
    background: linear-gradient(135deg, #0F2E22, #0A1E16);
    border-color: #059669;
}
.full-access-banner .fa-icon {
    width: 44px; height: 44px; border-radius: 12px;
    background: linear-gradient(135deg, #10B981, #059669);
    color: white; display: flex; align-items: center; justify-content: center;
    font-size: 1.2rem; flex-shrink: 0;
}
.full-access-banner .fa-content { flex: 1; min-width: 200px; }
.full-access-banner .fa-title {
    font-size: 0.9rem; font-weight: 800;
    color: #065F46; display: flex; align-items: center;
    gap: 6px; margin-bottom: 2px;
}
[data-theme="dark"] .full-access-banner .fa-title { color: #34D399; }
.full-access-banner .fa-sub {
    font-size: 0.72rem; color: #047857; font-weight: 600;
}
[data-theme="dark"] .full-access-banner .fa-sub { color: #6EE7B7; }

/* SEARCH */
.med-search-panel {
    background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
    border-radius: 12px; padding: 12px 16px; margin-bottom: 14px;
    box-shadow: 0 3px 12px rgba(11, 94, 215, 0.2);
    display: flex; align-items: center; gap: 12px; flex-wrap: wrap;
}
.med-search-panel .search-label {
    color: white; font-size: 0.72rem; font-weight: 700;
    display: flex; align-items: center; gap: 6px; white-space: nowrap;
}
.med-search-panel .search-box {
    position: relative; display: flex; align-items: center;
    background: rgba(255,255,255,0.18);
    border: 2px solid rgba(255,255,255,0.3);
    border-radius: 8px; padding: 0 12px;
    height: 38px; flex: 1; min-width: 200px;
}
.med-search-panel .search-box input {
    flex: 1; background: transparent; border: none;
    outline: none; color: white; font-size: 0.82rem;
    font-weight: 500; font-family: var(--font-mono);
}
.med-search-panel .search-box input::placeholder { color: rgba(255,255,255,0.65); }

/* QUICK FILTERS */
.quick-filters {
    display: flex; gap: 6px; flex-wrap: wrap; align-items: center;
    background: var(--bg-card); border-radius: 12px;
    padding: 10px 16px; border: 1px solid var(--border-color);
    margin-bottom: 14px;
}
.quick-filter-btn {
    padding: 5px 12px; border-radius: 18px;
    font-size: 0.68rem; font-weight: 700;
    border: 2px solid var(--border-color);
    background: var(--bg-card); color: var(--text-secondary);
    cursor: pointer; text-decoration: none;
    display: inline-flex; align-items: center; gap: 4px;
    white-space: nowrap;
}
.quick-filter-btn:hover {
    border-color: var(--primary); color: var(--primary);
    background: var(--primary-bg);
}
.quick-filter-btn.active {
    background: linear-gradient(135deg, var(--primary), var(--primary-dark));
    border-color: var(--primary); color: white;
}

/* PATIENT CARD */
.patient-card {
    background: var(--bg-card); border-radius: 14px;
    border: 2px solid var(--border-color);
    margin-bottom: 16px; overflow: hidden;
    box-shadow: var(--shadow);
}
.patient-card:hover { border-color: var(--primary); }
.patient-card.has-partial { border-color: #7C3AED; }
.patient-card.filtered-out { display: none; }

.patient-header {
    background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
    color: white; padding: 12px 20px;
    display: flex; justify-content: space-between;
    align-items: center; flex-wrap: wrap;
    gap: 12px; cursor: pointer;
}
.patient-header:hover { background: linear-gradient(135deg, #0A4CA8, #083C8A); }
.patient-header.has-partial { 
    background: linear-gradient(135deg, #7C3AED, #6D28D9);
}
.patient-header.has-partial:hover { 
    background: linear-gradient(135deg, #6D28D9, #5B21B6);
}
.patient-header .patient-info {
    display: flex; align-items: center; gap: 12px;
    flex: 1; min-width: 250px;
}
.patient-header .patient-avatar {
    width: 44px; height: 44px; border-radius: 50%;
    background: rgba(255,255,255,0.25);
    display: flex; align-items: center; justify-content: center;
    font-weight: 700; font-size: 1.2rem; color: white;
    border: 2px solid rgba(255,255,255,0.4);
}
.patient-header .patient-name {
    font-weight: 700; font-size: 1rem;
    display: flex; align-items: center; gap: 8px; flex-wrap: wrap;
}
.patient-header .patient-meta {
    display: flex; gap: 12px; font-size: 0.72rem;
    opacity: 0.9; flex-wrap: wrap; margin-top: 2px;
}
.patient-header .patient-meta span {
    display: flex; align-items: center; gap: 4px;
}
.patient-header .patient-stats {
    display: flex; gap: 8px; align-items: center; flex-wrap: wrap;
}
.patient-header .patient-stats .stat-pill {
    background: rgba(255,255,255,0.2);
    padding: 4px 12px; border-radius: 18px;
    font-size: 0.68rem; font-weight: 600;
    display: inline-flex; align-items: center; gap: 5px;
    border: 1px solid rgba(255,255,255,0.15);
}
.patient-header .chevron {
    font-size: 0.85rem; transition: transform 0.3s ease;
}
.patient-header .chevron.rotated { transform: rotate(180deg); }

/* PARTIAL BADGE */
.partial-badge {
    background: linear-gradient(135deg, #FCD34D, #F59E0B);
    color: #78350F;
    padding: 3px 10px;
    border-radius: 12px;
    font-size: 0.6rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    box-shadow: 0 2px 8px rgba(245, 158, 11, 0.5);
    animation: pulse-partial 2s infinite;
    border: 1px solid rgba(255,255,255,0.3);
}
@keyframes pulse-partial {
    0%, 100% { transform: scale(1); box-shadow: 0 2px 8px rgba(245, 158, 11, 0.5); }
    50% { transform: scale(1.05); box-shadow: 0 3px 12px rgba(245, 158, 11, 0.8); }
}

.patient-body {
    max-height: 0; overflow: hidden;
    transition: max-height 0.4s ease, padding 0.3s ease;
}
.patient-body.open { max-height: 20000px; padding: 14px 20px 18px; }

.patient-actions {
    display: flex; justify-content: space-between;
    align-items: center; gap: 12px;
    padding-bottom: 12px;
    border-bottom: 2px dashed var(--border-color);
    margin-bottom: 16px; flex-wrap: wrap;
}
.patient-actions-info {
    font-size: 0.75rem; color: var(--text-secondary);
    display: flex; align-items: center; gap: 6px; flex-wrap: wrap;
}
.patient-actions-info strong { font-weight: 800; color: var(--primary); }
.btn-patient-view {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 8px 18px; border-radius: 8px;
    font-weight: 700; font-size: 0.75rem;
    background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
    color: white; text-decoration: none;
    border: none; cursor: pointer;
    box-shadow: 0 3px 10px rgba(11, 94, 215, 0.3);
}
.btn-patient-view:hover { transform: translateY(-2px); color: white; }

/* VISIT SECTION */
.visit-section {
    border: 2px solid var(--border-color);
    border-radius: 12px; margin-bottom: 16px;
    overflow: hidden; transition: all 0.3s ease;
}
.visit-section:last-child { margin-bottom: 0; }
.visit-section:hover { border-color: var(--primary); }

.visit-section-header {
    background: linear-gradient(135deg, #E8F0FE, #D6E4FF);
    padding: 10px 16px;
    display: flex; justify-content: space-between;
    align-items: center; flex-wrap: wrap; gap: 10px;
    border-bottom: 2px solid var(--primary-light);
}
[data-theme="dark"] .visit-section-header { background: linear-gradient(135deg, #1E3A5F, #16294A); }
.visit-info-left {
    display: flex; align-items: center; gap: 10px;
    flex-wrap: wrap; flex: 1;
}
.visit-icon-badge {
    width: 36px; height: 36px; border-radius: 9px;
    background: linear-gradient(135deg, #FCD34D, #F59E0B);
    color: #78350F; display: flex;
    align-items: center; justify-content: center;
    font-size: 1rem;
    box-shadow: 0 3px 10px rgba(245, 158, 11, 0.3);
    flex-shrink: 0;
}
.visit-number-display {
    font-weight: 800; font-size: 0.85rem;
    color: #78350F; background: rgba(255,255,255,0.6);
    padding: 3px 10px; border-radius: 7px;
    border: 1px solid rgba(245, 158, 11, 0.3);
}
[data-theme="dark"] .visit-number-display { color: #FCD34D; background: rgba(255,255,255,0.1); }
.visit-date-display {
    font-size: 0.72rem; color: var(--text-secondary);
    display: inline-flex; align-items: center; gap: 4px; font-weight: 600;
}
.visit-doctor-display {
    font-size: 0.72rem; color: var(--primary); font-weight: 700;
    display: inline-flex; align-items: center; gap: 4px;
    background: rgba(255,255,255,0.6);
    padding: 3px 10px; border-radius: 20px;
    border: 1px solid var(--primary-light);
}
[data-theme="dark"] .visit-doctor-display { background: rgba(255,255,255,0.1); color: #93C5FD; }
.visit-stats-right {
    display: flex; gap: 6px; align-items: center; flex-wrap: wrap;
}
.visit-mini-stat {
    background: rgba(255,255,255,0.7);
    padding: 3px 10px; border-radius: 16px;
    font-size: 0.65rem; font-weight: 700;
    display: inline-flex; align-items: center; gap: 4px;
}
[data-theme="dark"] .visit-mini-stat { background: rgba(255,255,255,0.1); }

/* TABLE NAV */
.table-nav-group {
    display: inline-flex; align-items: center; gap: 2px;
    background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
    border: 1px solid rgba(255,255,255,0.3);
    border-radius: 8px; padding: 2px;
    box-shadow: 0 2px 8px rgba(11, 94, 215, 0.25);
}
.table-nav-btn {
    background: rgba(255,255,255,0.15); border: none;
    color: white; width: 28px; height: 28px;
    border-radius: 6px; cursor: pointer;
    display: inline-flex; align-items: center;
    justify-content: center; font-size: 0.72rem;
    transition: all 0.2s ease;
}
.table-nav-btn:hover:not(:disabled) {
    background: rgba(255,255,255,0.35); transform: scale(1.1);
}
.table-nav-btn:disabled { opacity: 0.35; cursor: not-allowed; }
.table-nav-indicator {
    font-size: 0.6rem; font-weight: 800;
    font-family: var(--font-mono);
    color: white; padding: 0 6px;
    min-width: 38px; text-align: center;
    background: rgba(255,255,255,0.15);
    border-radius: 5px; height: 24px;
    line-height: 24px; letter-spacing: 0.02em;
}

/* TABLE */
.visit-section-body {
    padding: 12px 16px 14px; background: var(--bg-card);
}
.table-scroll-wrapper { position: relative; }
.table-scroll {
    overflow-x: auto; scroll-behavior: smooth;
    border-radius: 10px;
}
.table-scroll::-webkit-scrollbar { height: 8px; }
.table-scroll::-webkit-scrollbar-track {
    background: var(--bg-body); border-radius: 10px;
}
.table-scroll::-webkit-scrollbar-thumb {
    background: var(--primary); border-radius: 10px;
}
.data-table {
    width: 100%; border-collapse: collapse;
    font-size: 0.78rem; min-width: 900px;
}
.data-table thead th {
    text-align: left; padding: 8px 12px;
    font-weight: 700; font-size: 0.6rem;
    text-transform: uppercase; color: white;
    background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
    border-bottom: 3px solid #083C8A;
    white-space: nowrap;
}
.data-table tbody td {
    padding: 8px 12px;
    border-bottom: 1px solid var(--border-color);
    color: var(--text-primary);
    vertical-align: middle;
}
.data-table tbody tr:hover td { background: var(--primary-bg); }
.data-table tbody tr:nth-child(even) td { background: var(--gray-50); }

/* BADGES */
.status-badge {
    padding: 3px 10px; border-radius: 18px;
    font-size: 0.6rem; font-weight: 600;
    display: inline-flex; align-items: center; gap: 3px;
    white-space: nowrap;
}
.status-badge.warning { background: #FEF3C7; color: #D97706; }
.status-badge.success { background: #D1FAE5; color: #059669; }
.status-badge.danger  { background: #FEE2E2; color: #EF4444; }
.status-badge.info    { background: #E8F0FE; color: #0B5ED7; }
.status-badge.purple  { background: #EDE9FE; color: #7C3AED; }
.status-badge.cyan    { background: #CFFAFE; color: #0891B2; }

.amount-cell {
    font-weight: 700; color: var(--primary); font-size: 0.8rem;
}

/* ================================================================
   ✅ UNIFIED ACTION BUTTONS (View + Edit + Delete - SAME SIZE)
   ================================================================ */
.action-buttons-group {
    display: flex;
    gap: 5px;
    justify-content: center;
    align-items: center;
    flex-wrap: nowrap;
}
.btn-action-sm {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 4px;
    min-width: 62px;
    height: 28px;
    padding: 0 10px;
    border-radius: 6px;
    font-weight: 700;
    font-size: 0.62rem;
    line-height: 1;
    cursor: pointer;
    border: none;
    text-decoration: none;
    transition: all 0.2s ease;
    font-family: var(--font-main);
    white-space: nowrap;
    letter-spacing: 0.02em;
    text-transform: uppercase;
    box-shadow: 0 2px 4px rgba(0,0,0,0.08);
}
.btn-action-sm i {
    font-size: 0.65rem;
}
.btn-action-sm.view {
    background: linear-gradient(135deg, #3B82F6, #0B5ED7);
    color: white;
}
.btn-action-sm.view:hover {
    background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
    transform: translateY(-2px);
    box-shadow: 0 4px 10px rgba(11, 94, 215, 0.4);
    color: white;
}
.btn-action-sm.edit {
    background: linear-gradient(135deg, #FBBF24, #D97706);
    color: white;
}
.btn-action-sm.edit:hover {
    background: linear-gradient(135deg, #F59E0B, #B45309);
    transform: translateY(-2px);
    box-shadow: 0 4px 10px rgba(217, 119, 6, 0.4);
    color: white;
}
.btn-action-sm.delete {
    background: linear-gradient(135deg, #F87171, #DC2626);
    color: white;
}
.btn-action-sm.delete:hover {
    background: linear-gradient(135deg, #DC2626, #B91C1C);
    transform: translateY(-2px);
    box-shadow: 0 4px 10px rgba(220, 38, 38, 0.4);
    color: white;
}
/* Icon-only variant kwa space ndogo */
.btn-action-sm.icon-only {
    min-width: 28px;
    padding: 0;
    width: 28px;
}

/* BILL GROUP */
.bill-group {
    margin-bottom: 14px;
    border: 1px solid var(--border-color);
    border-radius: 10px; overflow: hidden;
}
.bill-group-header {
    background: linear-gradient(135deg, #F1F5F9, #E2E8F0);
    padding: 8px 14px;
    display: flex; justify-content: space-between;
    align-items: center; flex-wrap: wrap; gap: 8px;
    font-size: 0.72rem; font-weight: 700;
}
[data-theme="dark"] .bill-group-header { background: linear-gradient(135deg, #1E293B, #0F172A); }
.bill-group-header .bill-num {
    font-family: var(--font-mono);
    color: var(--primary); font-weight: 800;
}
.bill-group-body { padding: 12px; }

/* ITEM TYPE SECTION */
.item-type-section { margin-bottom: 14px; }
.item-type-section:last-child { margin-bottom: 0; }
.item-type-header {
    font-size: 0.65rem; font-weight: 800;
    color: var(--primary); text-transform: uppercase;
    letter-spacing: 0.05em; margin-bottom: 6px;
    padding: 4px 10px;
    background: linear-gradient(135deg, #E8F0FE, #D6E4FF);
    border-radius: 6px;
    display: flex; justify-content: space-between;
    align-items: center; flex-wrap: wrap; gap: 6px;
    border-left: 3px solid var(--primary);
}
[data-theme="dark"] .item-type-header { background: linear-gradient(135deg, #1E3A5F, #16294A); }
.item-type-header .title-group {
    display: inline-flex; align-items: center; gap: 6px;
}
.item-type-header .item-count {
    background: rgba(11,94,215,0.15);
    color: var(--primary);
    padding: 2px 8px; border-radius: 10px;
    font-size: 0.6rem; font-weight: 800;
}

/* EMPTY STATE */
.empty-state {
    text-align: center; padding: 60px 20px;
    color: var(--text-secondary); background: var(--bg-card);
    border-radius: 14px; border: 2px dashed var(--border-color);
}
.empty-state i {
    font-size: 3.5rem; color: var(--primary);
    display: block; margin-bottom: 12px;
}
.empty-state p {
    font-size: 1rem; font-weight: 600; color: var(--text-primary);
}
.empty-state .sub {
    font-size: 0.85rem; color: var(--text-secondary);
    margin-top: 4px; font-weight: 400;
}

/* FOOTER */
.footer {
    padding: 14px 0; border-top: 1px solid var(--border-color);
    margin-top: 24px; text-align: center;
    font-size: 0.7rem; color: var(--text-secondary);
}
.footer .footer-brand { color: var(--primary); font-weight: 600; }

/* RESPONSIVE */
@media (max-width: 1200px) { .stats-grid-5 { grid-template-columns: repeat(3, 1fr); } }
@media (max-width: 1024px) { .stats-grid-5, .stats-grid-4 { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 768px) {
    .page-header-custom { padding: 14px 16px; }
    .stats-grid-5, .stats-grid-4 { grid-template-columns: 1fr 1fr; gap: 8px; }
    .tabs-container { flex-direction: column; }
    .tab-btn { justify-content: flex-start; }
    .patient-header { flex-direction: column; align-items: stretch; }
    .visit-section-header { flex-direction: column; align-items: stretch; }
    .action-buttons-group { flex-wrap: wrap; }
}

@media print {
    .action-buttons-group { display: none !important; }
    .full-access-banner { display: none !important; }
    .page-header-custom { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
}
</style>

<main class="main-content">

    <!-- PAGE HEADER -->
    <div class="page-header-custom">
        <div>
            <h1 class="page-title">
                <i class="fas fa-concierge-bell"></i>
                Other Services
                <span class="role-badge-display full-access">
                    <i class="fas fa-shield-alt"></i> FULL ACCESS
                </span>
                <?php if ($selected_branch_id !== 'all'): ?>
                    <span class="branch-tag"><i class="fas fa-store-alt"></i> <?= htmlspecialchars($selected_branch_name) ?></span>
                <?php endif; ?>
            </h1>
            <p class="page-subtitle">
                <span class="branch-tag"><i class="fas fa-th-large"></i> Procedures • Consultations • Bills • OTC</span>
            </p>
        </div>
        <div style="display:flex;gap:6px;flex-wrap:wrap;position:relative;z-index:1;">
            <button onclick="window.print()" class="btn-outline-light">
                <i class="fas fa-print"></i> Print
            </button>
            <a href="dashboard.php?branch=<?= $selected_branch_id ?>" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Dashboard
            </a>
        </div>
    </div>

    <!-- FULL ACCESS BANNER -->
    <div class="full-access-banner">
        <div class="fa-icon"><i class="fas fa-shield-alt"></i></div>
        <div class="fa-content">
            <div class="fa-title"><i class="fas fa-check-circle"></i> Full Access Mode</div>
            <div class="fa-sub">Audit access — You can <strong>View</strong>, <strong>Edit</strong>, and <strong>Delete</strong> records on all tabs.</div>
        </div>
    </div>

    <!-- TABS -->
    <div class="tabs-container">
        <a href="<?= buildFilterUrl(['tab' => 'procedures']) ?>" 
           class="tab-btn <?= $active_tab === 'procedures' ? 'active' : '' ?>">
            <i class="fas fa-syringe"></i> Procedures & Equipments
            <?php if ($active_tab === 'procedures'): ?>
                <span class="tab-count"><?= $procedures_stats['total'] ?></span>
            <?php endif; ?>
        </a>
        <a href="<?= buildFilterUrl(['tab' => 'consultations']) ?>" 
           class="tab-btn <?= $active_tab === 'consultations' ? 'active' : '' ?>">
            <i class="fas fa-stethoscope"></i> Consultations
            <?php if ($active_tab === 'consultations'): ?>
                <span class="tab-count"><?= $consultations_stats['total'] ?></span>
            <?php endif; ?>
        </a>
        <a href="<?= buildFilterUrl(['tab' => 'all_bills']) ?>" 
           class="tab-btn <?= $active_tab === 'all_bills' ? 'active' : '' ?>">
            <i class="fas fa-file-invoice-dollar"></i> All Bills
            <?php if ($active_tab === 'all_bills'): ?>
                <span class="tab-count"><?= $bills_stats['total_bills'] ?></span>
            <?php endif; ?>
        </a>
        <a href="<?= buildFilterUrl(['tab' => 'otc_bills']) ?>" 
           class="tab-btn <?= $active_tab === 'otc_bills' ? 'active' : '' ?>">
            <i class="fas fa-shopping-cart"></i> OTC Bills
            <?php if ($active_tab === 'otc_bills'): ?>
                <span class="tab-count"><?= $otc_stats['total'] ?></span>
            <?php endif; ?>
        </a>
    </div>

    <!-- QUICK FILTERS -->
    <div class="quick-filters">
        <span style="font-size:0.68rem;font-weight:700;color:var(--text-secondary);">
            <i class="fas fa-bolt"></i> Quick:
        </span>
        <a href="<?= buildFilterUrl(['quick' => 'all', 'date_from' => null, 'date_to' => null]) ?>" 
           class="quick-filter-btn <?= $quick_filter === 'all' ? 'active' : '' ?>">
            <i class="fas fa-infinity"></i> All
        </a>
        <a href="<?= buildFilterUrl(['quick' => 'today', 'date_from' => null, 'date_to' => null]) ?>" 
           class="quick-filter-btn <?= $quick_filter === 'today' ? 'active' : '' ?>">
            <i class="fas fa-calendar-day"></i> Today
        </a>
        <a href="<?= buildFilterUrl(['quick' => '1w', 'date_from' => null, 'date_to' => null]) ?>" 
           class="quick-filter-btn <?= $quick_filter === '1w' ? 'active' : '' ?>">
            <i class="fas fa-calendar-week"></i> 1 Week
        </a>
        <a href="<?= buildFilterUrl(['quick' => '1m', 'date_from' => null, 'date_to' => null]) ?>" 
           class="quick-filter-btn <?= $quick_filter === '1m' ? 'active' : '' ?>">
            <i class="fas fa-calendar-alt"></i> 1 Month
        </a>
        <a href="<?= buildFilterUrl(['quick' => '3m', 'date_from' => null, 'date_to' => null]) ?>" 
           class="quick-filter-btn <?= $quick_filter === '3m' ? 'active' : '' ?>">
            <i class="fas fa-calendar-alt"></i> 3 Months
        </a>
        <a href="<?= buildFilterUrl(['quick' => '1y', 'date_from' => null, 'date_to' => null]) ?>" 
           class="quick-filter-btn <?= $quick_filter === '1y' ? 'active' : '' ?>">
            <i class="fas fa-calendar"></i> 1 Year
        </a>
    </div>

    <!-- SEARCH -->
    <div class="med-search-panel">
        <div class="search-label">
            <i class="fas fa-search"></i>
            <span>Search</span>
        </div>
        <div class="search-box">
            <i class="fas fa-search" style="color:white;margin-right:8px;"></i>
            <input type="text" id="pageSearchInput" 
                   placeholder="Search patient, bill, item..." autocomplete="off">
        </div>
    </div>

    <!-- ============================================================
         TAB 1: PROCEDURES & EQUIPMENTS
         ✅ View + Edit + Delete
         ============================================================ -->
    <?php if ($active_tab === 'procedures'): ?>
        
        <div class="stats-grid-5">
            <div class="stat-card-custom card-blue-1">
                <div class="stat-icon"><i class="fas fa-syringe"></i></div>
                <p class="stat-label">Total Items</p>
                <p class="stat-number"><?= $procedures_stats['total'] ?></p>
                <p class="stat-amount"><?= formatTsh($procedures_stats['amount']) ?></p>
            </div>
            <div class="stat-card-custom card-purple">
                <div class="stat-icon"><i class="fas fa-tools"></i></div>
                <p class="stat-label">Equipments</p>
                <p class="stat-number"><?= $procedures_stats['equipment_count'] ?></p>
                <p class="stat-amount">Equipment Items</p>
            </div>
            <div class="stat-card-custom card-orange">
                <div class="stat-icon"><i class="fas fa-clock"></i></div>
                <p class="stat-label">Pending</p>
                <p class="stat-number"><?= $procedures_stats['pending'] ?></p>
                <p class="stat-amount">Waiting</p>
            </div>
            <div class="stat-card-custom card-green">
                <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                <p class="stat-label">Completed</p>
                <p class="stat-number"><?= $procedures_stats['completed'] ?></p>
                <p class="stat-amount">Done</p>
            </div>
            <div class="stat-card-custom card-cyan">
                <div class="stat-icon"><i class="fas fa-users"></i></div>
                <p class="stat-label">Patients</p>
                <p class="stat-number"><?= count($procedures_array ?? []) ?></p>
                <p class="stat-amount">With Procedures</p>
            </div>
        </div>
        
        <?php if (!empty($procedures_array)): ?>
            <?php foreach ($procedures_array as $patient): 
                $pid = $patient['patient_id'];
                $age = calculateAge($patient['date_of_birth']);
                $has_partial = ($patient['partial_bills'] ?? 0) > 0;
            ?>
                <div class="patient-card <?= $has_partial ? 'has-partial' : '' ?>" 
                     data-patient-search="<?= htmlspecialchars(strtolower($patient['patient_name'] . ' ' . $patient['patient_number'] . ' ' . $patient['patient_phone'])) ?>">
                    
                    <div class="patient-header <?= $has_partial ? 'has-partial' : '' ?>" onclick="togglePatient(<?= $pid ?>)">
                        <div class="patient-info">
                            <div class="patient-avatar" style="background:<?= '#' . substr(md5($patient['patient_name']), 0, 6) ?>;">
                                <?= strtoupper(substr($patient['patient_name'], 0, 1)) ?>
                            </div>
                            <div>
                                <div class="patient-name">
                                    <span class="patient-name-text"><?= htmlspecialchars($patient['patient_name']) ?></span>
                                    
                                    <?php if ($has_partial): ?>
                                        <span class="partial-badge">
                                            <i class="fas fa-hourglass-half"></i> PARTIAL
                                            (<?= $patient['partial_bills'] ?>)
                                        </span>
                                    <?php endif; ?>
                                    
                                    <span style="background:rgba(255,255,255,0.25);padding:1px 8px;border-radius:10px;font-size:0.58rem;font-weight:600;">
                                        <i class="fas fa-calendar-check"></i> <?= $patient['visit_count'] ?> visit(s)
                                    </span>
                                    <span style="background:rgba(255,255,255,0.25);padding:1px 8px;border-radius:10px;font-size:0.58rem;font-weight:600;">
                                        <i class="fas fa-syringe"></i> <?= $patient['total_items'] ?> item(s)
                                    </span>
                                </div>
                                <div class="patient-meta">
                                    <span><i class="fas fa-id-card"></i> <?= htmlspecialchars($patient['patient_number']) ?></span>
                                    <?php if (!empty($patient['patient_phone'])): ?>
                                        <span><i class="fas fa-phone"></i> <?= htmlspecialchars($patient['patient_phone']) ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($patient['patient_gender'])): ?>
                                        <span><i class="fas fa-<?= $patient['patient_gender'] === 'Female' ? 'venus' : 'mars' ?>"></i> <?= htmlspecialchars($patient['patient_gender']) ?> • <?= $age ?> yrs</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="patient-stats">
                            <span class="stat-pill">
                                <i class="fas fa-money-bill-wave"></i> <?= formatTsh($patient['total_amount']) ?>
                            </span>
                            <i class="fas fa-chevron-down chevron" id="chevron-<?= $pid ?>"></i>
                        </div>
                    </div>
                    
                    <div class="patient-body" id="body-<?= $pid ?>">
                        <div class="patient-actions">
                            <div class="patient-actions-info">
                                <i class="fas fa-info-circle" style="color:var(--primary);"></i>
                                <strong><?= $patient['visit_count'] ?></strong> visit(s) • 
                                <strong><?= $patient['total_items'] ?></strong> procedure/equipment item(s) •
                                Total: <strong><?= formatTsh($patient['total_amount']) ?></strong>
                            </div>
                            <div>
                                <a href="patient_other_services.php?patient_id=<?= $pid ?>&type=procedures&branch=<?= $selected_branch_id ?>" 
                                   class="btn-patient-view">
                                    <i class="fas fa-eye"></i> VIEW ALL
                                </a>
                            </div>
                        </div>
                        
                        <?php foreach ($patient['visits'] as $visit): 
                            $uid = $pid . '-' . $visit['visit_id'];
                        ?>
                            <div class="visit-section">
                                <div class="visit-section-header">
                                    <div class="visit-info-left">
                                        <div class="visit-icon-badge"><i class="fas fa-syringe"></i></div>
                                        <span class="visit-number-display"><?= htmlspecialchars($visit['visit_number']) ?></span>
                                        <span class="visit-date-display">
                                            <i class="fas fa-calendar-day"></i>
                                            <?= date('d M Y', strtotime($visit['visit_date'])) ?>
                                        </span>
                                        <span class="visit-doctor-display">
                                            <i class="fas fa-user-md"></i> Dr. <?= htmlspecialchars($visit['doctor_name']) ?>
                                        </span>
                                    </div>
                                    <div class="visit-stats-right">
                                        <span class="visit-mini-stat">
                                            <i class="fas fa-syringe"></i>
                                            Items: <strong style="color:var(--primary);"><?= count($visit['items']) ?></strong>
                                        </span>
                                        <span class="visit-mini-stat">
                                            <i class="fas fa-money-bill-wave"></i>
                                            <strong style="color:var(--primary);"><?= formatTsh($visit['total_amount']) ?></strong>
                                        </span>
                                        <div class="table-nav-group">
                                            <button type="button" class="table-nav-btn" onclick="scrollTable('table-<?= $uid ?>', -1)">
                                                <i class="fas fa-chevron-left"></i>
                                            </button>
                                            <span class="table-nav-indicator" id="indicator-<?= $uid ?>">0%</span>
                                            <button type="button" class="table-nav-btn" onclick="scrollTable('table-<?= $uid ?>', 1)">
                                                <i class="fas fa-chevron-right"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <div class="visit-section-body">
                                    <div class="table-scroll-wrapper">
                                        <div class="table-scroll" id="table-<?= $uid ?>">
                                            <table class="data-table">
                                                <thead>
                                                    <tr>
                                                        <th style="width:45px;">#</th>
                                                        <th>Procedure / Equipment</th>
                                                        <th>Category</th>
                                                        <th>Doctor</th>
                                                        <th>Status</th>
                                                        <th>Price</th>
                                                        <th>Date</th>
                                                        <th style="text-align:center;min-width:200px;">Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php $i = 1; foreach ($visit['items'] as $item): 
                                                        $s = getStatusBadge($item['status']);
                                                    ?>
                                                        <tr data-search="<?= htmlspecialchars(strtolower($item['procedure_name'] . ' ' . $item['category'])) ?>">
                                                            <td style="text-align:center;font-weight:700;"><?= $i++ ?></td>
                                                            <td>
                                                                <span style="font-weight:700;color:var(--primary);">
                                                                    <?= htmlspecialchars($item['procedure_name']) ?>
                                                                </span>
                                                            </td>
                                                            <td>
                                                                <span class="status-badge purple" style="font-size:0.55rem;">
                                                                    <?= htmlspecialchars($item['category'] ?? 'N/A') ?>
                                                                </span>
                                                            </td>
                                                            <td>
                                                                <span style="font-size:0.72rem;">
                                                                    <i class="fas fa-user-md" style="color:var(--cyan);"></i>
                                                                    <?= htmlspecialchars($item['doctor_name'] ?? 'N/A') ?>
                                                                </span>
                                                            </td>
                                                            <td>
                                                                <span class="status-badge <?= $s['class'] ?>">
                                                                    <?= $s['icon'] ?> <?= $s['label'] ?>
                                                                </span>
                                                            </td>
                                                            <td class="amount-cell"><?= formatTsh($item['procedure_price']) ?></td>
                                                            <td style="font-size:0.7rem;"><?= date('d M Y', strtotime($item['created_at'])) ?></td>
                                                            <td style="text-align:center;">
                                                                <!-- ✅ VIEW + EDIT + DELETE (UNIFIED) -->
                                                                <div class="action-buttons-group">
                                                                    <a href="view_procedure.php?id=<?= $item['id'] ?>" class="btn-action-sm view" title="View">
                                                                        <i class="fas fa-eye"></i> View
                                                                    </a>
                                                                    <a href="edit_procedure.php?id=<?= $item['id'] ?>" class="btn-action-sm edit" title="Edit">
                                                                        <i class="fas fa-edit"></i> Edit
                                                                    </a>
                                                                    <a href="delete_procedure.php?id=<?= $item['id'] ?>" 
                                                                       class="btn-action-sm delete" 
                                                                       title="Delete"
                                                                       onclick="return confirm('Are you sure you want to delete this procedure? This action cannot be undone!');">
                                                                        <i class="fas fa-trash"></i> Delete
                                                                    </a>
                                                                </div>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-syringe"></i>
                <p>No procedures found</p>
                <p class="sub">Try adjusting your filters</p>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <!-- ============================================================
         TAB 2: CONSULTATIONS
         ✅ View + Edit + Delete (NEW!)
         ============================================================ -->
    <?php if ($active_tab === 'consultations'): ?>
        
        <div class="stats-grid-5 stats-grid-4">
            <div class="stat-card-custom card-blue-1">
                <div class="stat-icon"><i class="fas fa-stethoscope"></i></div>
                <p class="stat-label">Total Consultations</p>
                <p class="stat-number"><?= $consultations_stats['total'] ?></p>
                <p class="stat-amount"><?= formatTsh($consultations_stats['amount']) ?></p>
            </div>
            <div class="stat-card-custom card-orange">
                <div class="stat-icon"><i class="fas fa-clock"></i></div>
                <p class="stat-label">Pending Payment</p>
                <p class="stat-number"><?= $consultations_stats['pending'] ?></p>
                <p class="stat-amount">Unpaid</p>
            </div>
            <div class="stat-card-custom card-green">
                <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                <p class="stat-label">Paid</p>
                <p class="stat-number"><?= $consultations_stats['paid'] ?></p>
                <p class="stat-amount">Completed</p>
            </div>
            <div class="stat-card-custom card-purple">
                <div class="stat-icon"><i class="fas fa-users"></i></div>
                <p class="stat-label">Patients</p>
                <p class="stat-number"><?= count($consultations_array ?? []) ?></p>
                <p class="stat-amount">With Consultations</p>
            </div>
        </div>
        
        <?php if (!empty($consultations_array)): ?>
            <?php foreach ($consultations_array as $patient): 
                $pid = $patient['patient_id'];
                $age = calculateAge($patient['date_of_birth']);
                $has_partial = ($patient['partial_bills'] ?? 0) > 0;
            ?>
                <div class="patient-card <?= $has_partial ? 'has-partial' : '' ?>" 
                     data-patient-search="<?= htmlspecialchars(strtolower($patient['patient_name'] . ' ' . $patient['patient_number'])) ?>">
                    
                    <div class="patient-header <?= $has_partial ? 'has-partial' : '' ?>" onclick="togglePatient(<?= $pid ?>)">
                        <div class="patient-info">
                            <div class="patient-avatar" style="background:<?= '#' . substr(md5($patient['patient_name']), 0, 6) ?>;">
                                <?= strtoupper(substr($patient['patient_name'], 0, 1)) ?>
                            </div>
                            <div>
                                <div class="patient-name">
                                    <span class="patient-name-text"><?= htmlspecialchars($patient['patient_name']) ?></span>
                                    
                                    <?php if ($has_partial): ?>
                                        <span class="partial-badge">
                                            <i class="fas fa-hourglass-half"></i> PARTIAL
                                            (<?= $patient['partial_bills'] ?>)
                                        </span>
                                    <?php endif; ?>
                                    
                                    <span style="background:rgba(255,255,255,0.25);padding:1px 8px;border-radius:10px;font-size:0.58rem;font-weight:600;">
                                        <i class="fas fa-stethoscope"></i> <?= $patient['visit_count'] ?> consultation(s)
                                    </span>
                                </div>
                                <div class="patient-meta">
                                    <span><i class="fas fa-id-card"></i> <?= htmlspecialchars($patient['patient_number']) ?></span>
                                    <?php if (!empty($patient['patient_phone'])): ?>
                                        <span><i class="fas fa-phone"></i> <?= htmlspecialchars($patient['patient_phone']) ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($patient['patient_gender'])): ?>
                                        <span><i class="fas fa-<?= $patient['patient_gender'] === 'Female' ? 'venus' : 'mars' ?>"></i> <?= htmlspecialchars($patient['patient_gender']) ?> • <?= $age ?> yrs</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="patient-stats">
                            <span class="stat-pill">
                                <i class="fas fa-money-bill-wave"></i> <?= formatTsh($patient['total_amount']) ?>
                            </span>
                            <i class="fas fa-chevron-down chevron" id="chevron-<?= $pid ?>"></i>
                        </div>
                    </div>
                    
                    <div class="patient-body" id="body-<?= $pid ?>">
                        <div class="patient-actions">
                            <div class="patient-actions-info">
                                <i class="fas fa-info-circle" style="color:var(--primary);"></i>
                                <strong><?= $patient['visit_count'] ?></strong> consultation(s) •
                                Total Fees: <strong><?= formatTsh($patient['total_amount']) ?></strong>
                            </div>
                            <div>
                                <a href="patient_other_services.php?patient_id=<?= $pid ?>&type=consultations&branch=<?= $selected_branch_id ?>" 
                                   class="btn-patient-view">
                                    <i class="fas fa-eye"></i> VIEW ALL
                                </a>
                            </div>
                        </div>
                        
                        <?php foreach ($patient['visits'] as $visit): 
                            $uid = $pid . '-v' . $visit['visit_id'];
                            $vs = getStatusBadge($visit['payment_status']);
                        ?>
                            <div class="visit-section">
                                <div class="visit-section-header">
                                    <div class="visit-info-left">
                                        <div class="visit-icon-badge"><i class="fas fa-stethoscope"></i></div>
                                        <span class="visit-number-display"><?= htmlspecialchars($visit['visit_number']) ?></span>
                                        <span class="visit-date-display">
                                            <i class="fas fa-calendar-day"></i>
                                            <?= date('d M Y', strtotime($visit['visit_date'])) ?>
                                        </span>
                                        <span class="visit-doctor-display">
                                            <i class="fas fa-user-md"></i> Dr. <?= htmlspecialchars($visit['doctor_name']) ?>
                                        </span>
                                        <span class="status-badge <?= $vs['class'] ?>" style="font-size:0.58rem;">
                                            <?= $vs['icon'] ?> <?= $vs['label'] ?>
                                        </span>
                                    </div>
                                    <div class="visit-stats-right">
                                        <span class="visit-mini-stat">
                                            <i class="fas fa-money-bill-wave"></i>
                                            <strong style="color:var(--primary);"><?= formatTsh($visit['consultation_fee']) ?></strong>
                                        </span>
                                        <div class="table-nav-group">
                                            <button type="button" class="table-nav-btn" onclick="scrollTable('table-<?= $uid ?>', -1)">
                                                <i class="fas fa-chevron-left"></i>
                                            </button>
                                            <span class="table-nav-indicator" id="indicator-<?= $uid ?>">0%</span>
                                            <button type="button" class="table-nav-btn" onclick="scrollTable('table-<?= $uid ?>', 1)">
                                                <i class="fas fa-chevron-right"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <div class="visit-section-body">
                                    <div class="table-scroll-wrapper">
                                        <div class="table-scroll" id="table-<?= $uid ?>">
                                            <table class="data-table">
                                                <thead>
                                                    <tr>
                                                        <th>Visit Number</th>
                                                        <th>Visit Type</th>
                                                        <th>Doctor</th>
                                                        <th>Receptionist</th>
                                                        <th>Diagnosis</th>
                                                        <th>Fee</th>
                                                        <th>Payment</th>
                                                        <th>Visit Status</th>
                                                        <th style="text-align:center;min-width:200px;">Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <tr data-search="<?= htmlspecialchars(strtolower($visit['visit_number'] . ' ' . $visit['doctor_name'])) ?>">
                                                        <td style="font-weight:700;color:var(--primary);"><?= htmlspecialchars($visit['visit_number']) ?></td>
                                                        <td><?= htmlspecialchars($visit['visit_type']) ?></td>
                                                        <td><i class="fas fa-user-md" style="color:var(--cyan);"></i> <?= htmlspecialchars($visit['doctor_name']) ?></td>
                                                        <td><i class="fas fa-user-tie" style="color:var(--purple);"></i> <?= htmlspecialchars($visit['receptionist_name']) ?></td>
                                                        <td style="font-size:0.72rem;color:var(--text-secondary);">
                                                            <?= htmlspecialchars($visit['diagnosis'] ?: '—') ?>
                                                        </td>
                                                        <td class="amount-cell"><?= formatTsh($visit['consultation_fee']) ?></td>
                                                        <td>
                                                            <span class="status-badge <?= $vs['class'] ?>">
                                                                <?= $vs['icon'] ?> <?= $vs['label'] ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <span class="status-badge info" style="font-size:0.58rem;">
                                                                <?= htmlspecialchars($visit['status']) ?>
                                                            </span>
                                                        </td>
                                                        <td style="text-align:center;">
                                                            <!-- ✅ VIEW + EDIT + DELETE (UNIFIED) -->
                                                            <div class="action-buttons-group">
                                                                <a href="view_consultation.php?id=<?= $visit['visit_id'] ?>" class="btn-action-sm view" title="View">
                                                                    <i class="fas fa-eye"></i> View
                                                                </a>
                                                                <a href="edit_consultation.php?id=<?= $visit['visit_id'] ?>" class="btn-action-sm edit" title="Edit">
                                                                    <i class="fas fa-edit"></i> Edit
                                                                </a>
                                                                <a href="delete_consultation.php?id=<?= $visit['visit_id'] ?>" 
                                                                   class="btn-action-sm delete" 
                                                                   title="Delete"
                                                                   onclick="return confirm('Are you sure you want to delete this consultation? This action cannot be undone!');">
                                                                    <i class="fas fa-trash"></i> Delete
                                                                </a>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-stethoscope"></i>
                <p>No consultations found</p>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <!-- ============================================================
         TAB 3: ALL BILLS
         ✅ View + Edit + Delete
         ============================================================ -->
    <?php if ($active_tab === 'all_bills'): ?>
        
        <div class="stats-grid-5">
            <div class="stat-card-custom card-green">
                <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                <p class="stat-label">All Paid Bills</p>
                <p class="stat-number"><?= $bills_stats['paid'] ?></p>
                <p class="stat-amount"><?= formatTsh($bills_stats['total_paid_amt']) ?></p>
            </div>
            <div class="stat-card-custom card-orange">
                <div class="stat-icon"><i class="fas fa-hourglass-half"></i></div>
                <p class="stat-label">All Pending Bills</p>
                <p class="stat-number"><?= $bills_stats['pending'] ?></p>
                <p class="stat-amount"><?= formatTsh($bills_stats['total_pending_amt']) ?></p>
            </div>
            <div class="stat-card-custom card-purple">
                <div class="stat-icon"><i class="fas fa-spinner"></i></div>
                <p class="stat-label">Partial Bills</p>
                <p class="stat-number"><?= $bills_stats['partial'] ?></p>
                <p class="stat-amount">Partially Paid</p>
            </div>
            <div class="stat-card-custom card-cyan">
                <div class="stat-icon"><i class="fas fa-star"></i></div>
                <p class="stat-label">All Premiums</p>
                <p class="stat-number"><?= formatTsh($bills_stats['total_premium']) ?></p>
                <p class="stat-amount">Total Premium Added</p>
            </div>
            <div class="stat-card-custom card-red">
                <div class="stat-icon"><i class="fas fa-percent"></i></div>
                <p class="stat-label">All Discounts</p>
                <p class="stat-number"><?= formatTsh($bills_stats['total_discount']) ?></p>
                <p class="stat-amount">Total Discounts Given</p>
            </div>
        </div>
        
        <?php if (!empty($bills_array)): ?>
            <?php foreach ($bills_array as $patient): 
                $pid = $patient['patient_id'];
                $age = calculateAge($patient['date_of_birth']);
                $has_partial = ($patient['total_partial'] ?? 0) > 0;
            ?>
                <div class="patient-card <?= $has_partial ? 'has-partial' : '' ?>" 
                     data-patient-search="<?= htmlspecialchars(strtolower($patient['patient_name'] . ' ' . $patient['patient_number'])) ?>">
                    
                    <div class="patient-header <?= $has_partial ? 'has-partial' : '' ?>" onclick="togglePatient(<?= $pid ?>)">
                        <div class="patient-info">
                            <div class="patient-avatar" style="background:<?= '#' . substr(md5($patient['patient_name']), 0, 6) ?>;">
                                <?= strtoupper(substr($patient['patient_name'], 0, 1)) ?>
                            </div>
                            <div>
                                <div class="patient-name">
                                    <span class="patient-name-text"><?= htmlspecialchars($patient['patient_name']) ?></span>
                                    
                                    <?php if ($has_partial): ?>
                                        <span class="partial-badge">
                                            <i class="fas fa-hourglass-half"></i> PARTIAL
                                            (<?= $patient['total_partial'] ?>)
                                        </span>
                                    <?php endif; ?>
                                    
                                    <span style="background:rgba(255,255,255,0.25);padding:1px 8px;border-radius:10px;font-size:0.58rem;font-weight:600;">
                                        <i class="fas fa-calendar-check"></i> <?= $patient['visit_count'] ?> visit(s)
                                    </span>
                                    <span style="background:rgba(255,255,255,0.25);padding:1px 8px;border-radius:10px;font-size:0.58rem;font-weight:600;">
                                        <i class="fas fa-file-invoice"></i> <?= $patient['total_bills'] ?> bill(s)
                                    </span>
                                </div>
                                <div class="patient-meta">
                                    <span><i class="fas fa-id-card"></i> <?= htmlspecialchars($patient['patient_number']) ?></span>
                                    <?php if (!empty($patient['patient_phone'])): ?>
                                        <span><i class="fas fa-phone"></i> <?= htmlspecialchars($patient['patient_phone']) ?></span>
                                    <?php endif; ?>
                                    <span><i class="fas fa-venus-mars"></i> <?= $age ?> yrs</span>
                                </div>
                            </div>
                        </div>
                        <div class="patient-stats">
                            <span class="stat-pill">
                                <i class="fas fa-money-bill-wave"></i> <?= formatTsh($patient['total_amount']) ?>
                            </span>
                            <i class="fas fa-chevron-down chevron" id="chevron-<?= $pid ?>"></i>
                        </div>
                    </div>
                    
                    <div class="patient-body" id="body-<?= $pid ?>">
                        
                        <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin-bottom:14px;">
                            <div style="background:linear-gradient(135deg,#D1FAE5,#A7F3D0);border-radius:10px;padding:10px 14px;border-left:4px solid #059669;">
                                <div style="font-size:0.6rem;font-weight:700;color:#065F46;text-transform:uppercase;">Paid Bills</div>
                                <div style="font-size:1.1rem;font-weight:800;color:#065F46;font-family:var(--font-mono);"><?= $patient['total_paid'] ?></div>
                            </div>
                            <div style="background:linear-gradient(135deg,#FEF3C7,#FDE68A);border-radius:10px;padding:10px 14px;border-left:4px solid #D97706;">
                                <div style="font-size:0.6rem;font-weight:700;color:#78350F;text-transform:uppercase;">Pending Bills</div>
                                <div style="font-size:1.1rem;font-weight:800;color:#78350F;font-family:var(--font-mono);"><?= $patient['total_pending'] ?></div>
                            </div>
                            <div style="background:linear-gradient(135deg,#EDE9FE,#DDD6FE);border-radius:10px;padding:10px 14px;border-left:4px solid #7C3AED;">
                                <div style="font-size:0.6rem;font-weight:700;color:#5B21B6;text-transform:uppercase;">Partial Bills</div>
                                <div style="font-size:1.1rem;font-weight:800;color:#5B21B6;font-family:var(--font-mono);"><?= $patient['total_partial'] ?></div>
                            </div>
                            <div style="background:linear-gradient(135deg,#CFFAFE,#A5F3FC);border-radius:10px;padding:10px 14px;border-left:4px solid #0891B2;">
                                <div style="font-size:0.6rem;font-weight:700;color:#0E7490;text-transform:uppercase;">Premium</div>
                                <div style="font-size:1.1rem;font-weight:800;color:#0E7490;font-family:var(--font-mono);"><?= formatTsh($patient['total_premium']) ?></div>
                            </div>
                        </div>
                        
                        <?php foreach ($patient['visits'] as $visit): 
                            $uid = $pid . '-' . $visit['visit_id'];
                        ?>
                            <div class="visit-section">
                                <div class="visit-section-header">
                                    <div class="visit-info-left">
                                        <div class="visit-icon-badge"><i class="fas fa-calendar-check"></i></div>
                                        <span class="visit-number-display"><?= htmlspecialchars($visit['visit_number']) ?></span>
                                        <span class="visit-date-display">
                                            <i class="fas fa-calendar-day"></i>
                                            <?= date('d M Y', strtotime($visit['visit_date'])) ?>
                                        </span>
                                        <span class="visit-doctor-display">
                                            <i class="fas fa-user-md"></i> Dr. <?= htmlspecialchars($visit['doctor_name']) ?>
                                        </span>
                                    </div>
                                    <div class="visit-stats-right">
                                        <span class="visit-mini-stat">
                                            <i class="fas fa-file-invoice"></i>
                                            Bills: <strong style="color:var(--primary);"><?= count($visit['bills']) ?></strong>
                                        </span>
                                    </div>
                                </div>
                                <div class="visit-section-body">
                                    
                                    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(120px,1fr));gap:8px;background:linear-gradient(135deg,#F8FAFC,#F1F5F9);border-radius:10px;padding:10px 14px;margin-bottom:12px;border:2px solid var(--primary-light);">
                                        <div style="display:flex;flex-direction:column;gap:2px;text-align:center;padding:6px 8px;border-right:1px solid var(--border-color);">
                                            <span style="font-size:0.55rem;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;color:var(--text-secondary);">Total Bill</span>
                                            <span style="font-size:0.95rem;font-weight:800;font-family:var(--font-mono);color:var(--primary);"><?= formatTsh($visit['sum_total']) ?></span>
                                        </div>
                                        <div style="display:flex;flex-direction:column;gap:2px;text-align:center;padding:6px 8px;border-right:1px solid var(--border-color);">
                                            <span style="font-size:0.55rem;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;color:var(--text-secondary);">Total Paid</span>
                                            <span style="font-size:0.95rem;font-weight:800;font-family:var(--font-mono);color:var(--success);"><?= formatTsh($visit['sum_paid']) ?></span>
                                        </div>
                                        <div style="display:flex;flex-direction:column;gap:2px;text-align:center;padding:6px 8px;border-right:1px solid var(--border-color);">
                                            <span style="font-size:0.55rem;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;color:var(--text-secondary);">Balance</span>
                                            <span style="font-size:0.95rem;font-weight:800;font-family:var(--font-mono);color:<?= $visit['sum_balance'] > 0 ? 'var(--danger)' : 'var(--success)' ?>;"><?= formatTsh($visit['sum_balance']) ?></span>
                                        </div>
                                        <div style="display:flex;flex-direction:column;gap:2px;text-align:center;padding:6px 8px;border-right:1px solid var(--border-color);">
                                            <span style="font-size:0.55rem;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;color:var(--text-secondary);">Discount</span>
                                            <span style="font-size:0.95rem;font-weight:800;font-family:var(--font-mono);color:var(--cyan);"><?= $visit['sum_discount'] > 0 ? '-' . formatTsh($visit['sum_discount']) : '—' ?></span>
                                        </div>
                                        <div style="display:flex;flex-direction:column;gap:2px;text-align:center;padding:6px 8px;">
                                            <span style="font-size:0.55rem;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;color:var(--text-secondary);">Premium</span>
                                            <span style="font-size:0.95rem;font-weight:800;font-family:var(--font-mono);color:var(--purple);"><?= $visit['sum_premium'] > 0 ? '+' . formatTsh($visit['sum_premium']) : '—' ?></span>
                                        </div>
                                    </div>
                                    
                                    <?php foreach ($visit['bills'] as $bill): 
                                        $bs = getStatusBadge($bill['status']);
                                        $items_by_type = [];
                                        foreach ($bill['items'] as $item) {
                                            $items_by_type[$item['item_type']][] = $item;
                                        }
                                    ?>
                                        <div class="bill-group">
                                            <div class="bill-group-header">
                                                <span style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;">
                                                    <i class="fas fa-file-invoice-dollar" style="color:var(--primary);"></i>
                                                    <span class="bill-num"><?= htmlspecialchars($bill['bill_number']) ?></span>
                                                    <span>•</span>
                                                    <span><?= date('d M Y, H:i', strtotime($bill['created_at'])) ?></span>
                                                </span>
                                                <span style="display:flex;gap:6px;align-items:center;flex-wrap:wrap;">
                                                    <span class="status-badge <?= $bs['class'] ?>"><?= $bs['icon'] ?> <?= $bs['label'] ?></span>
                                                    <span class="visit-mini-stat" style="background:rgba(11,94,215,0.15);color:var(--primary);">
                                                        <i class="fas fa-money-bill"></i> <strong><?= formatTsh($bill['total_amount']) ?></strong>
                                                    </span>
                                                    <!-- ✅ BILL ACTIONS -->
                                                    <div class="action-buttons-group">
                                                        <a href="view_bill.php?id=<?= $bill['id'] ?>" class="btn-action-sm view" title="View Bill">
                                                            <i class="fas fa-eye"></i> View
                                                        </a>
                                                        <a href="edit_bill.php?id=<?= $bill['id'] ?>" class="btn-action-sm edit" title="Edit Bill">
                                                            <i class="fas fa-edit"></i> Edit
                                                        </a>
                                                        <a href="delete_bill.php?id=<?= $bill['id'] ?>" 
                                                           class="btn-action-sm delete" 
                                                           title="Delete Bill"
                                                           onclick="return confirm('Are you sure you want to delete this bill? This action cannot be undone!');">
                                                            <i class="fas fa-trash"></i> Delete
                                                        </a>
                                                    </div>
                                                </span>
                                            </div>
                                            
                                            <div class="bill-group-body">
                                                <?php foreach ($items_by_type as $item_type => $items): 
                                                    $type_meta = [
                                                        'consultation' => ['icon' => 'fa-stethoscope', 'label' => 'Consultation'],
                                                        'lab_test' => ['icon' => 'fa-flask', 'label' => 'Lab Tests'],
                                                        'medication' => ['icon' => 'fa-pills', 'label' => 'Medications'],
                                                        'procedure' => ['icon' => 'fa-syringe', 'label' => 'Procedures'],
                                                        'equipment' => ['icon' => 'fa-tools', 'label' => 'Equipments'],
                                                    ];
                                                    $tm = $type_meta[$item_type] ?? ['icon' => 'fa-file', 'label' => ucfirst($item_type)];
                                                ?>
                                                    <div class="item-type-section">
                                                        <div class="item-type-header">
                                                            <span class="title-group">
                                                                <i class="fas <?= $tm['icon'] ?>"></i> <?= $tm['label'] ?>
                                                                <span class="item-count"><?= count($items) ?></span>
                                                            </span>
                                                        </div>
                                                        <div class="table-scroll-wrapper">
                                                            <div class="table-scroll">
                                                                <table class="data-table" style="min-width:auto;">
                                                                    <thead>
                                                                        <tr>
                                                                            <th>Item</th>
                                                                            <th>Qty</th>
                                                                            <th>Unit Price</th>
                                                                            <th>Discount</th>
                                                                            <th>Total</th>
                                                                            <th>Status</th>
                                                                            <th style="text-align:center;min-width:200px;">Actions</th>
                                                                        </tr>
                                                                    </thead>
                                                                    <tbody>
                                                                        <?php foreach ($items as $it): 
                                                                            $cs = getStatusBadge($it['status']);
                                                                        ?>
                                                                            <tr>
                                                                                <td style="font-weight:700;color:var(--primary);">
                                                                                    <?= htmlspecialchars($it['item_name']) ?>
                                                                                </td>
                                                                                <td><?= $it['quantity'] ?></td>
                                                                                <td><?= formatTsh($it['unit_price']) ?></td>
                                                                                <td><?= ($it['discount_amount'] ?? 0) > 0 ? '-' . formatTsh($it['discount_amount']) : '—' ?></td>
                                                                                <td class="amount-cell"><?= formatTsh($it['total_price']) ?></td>
                                                                                <td>
                                                                                    <span class="status-badge <?= $cs['class'] ?>" style="font-size:0.55rem;">
                                                                                        <?= $cs['icon'] ?> <?= $cs['label'] ?>
                                                                                    </span>
                                                                                </td>
                                                                                <td style="text-align:center;">
                                                                                    <!-- ✅ ITEM ACTIONS (VIEW + EDIT + DELETE) -->
                                                                                    <div class="action-buttons-group">
                                                                                        <?php if (in_array($item_type, ['procedure', 'equipment'])): ?>
                                                                                            <a href="view_procedure.php?id=<?= $it['reference_id'] ?? 0 ?>" class="btn-action-sm view" title="View">
                                                                                                <i class="fas fa-eye"></i> View
                                                                                            </a>
                                                                                            <a href="edit_procedure.php?id=<?= $it['id'] ?>" class="btn-action-sm edit" title="Edit">
                                                                                                <i class="fas fa-edit"></i> Edit
                                                                                            </a>
                                                                                            <a href="delete_procedure.php?id=<?= $it['id'] ?>" 
                                                                                               class="btn-action-sm delete" 
                                                                                               title="Delete"
                                                                                               onclick="return confirm('Delete this item? This cannot be undone!');">
                                                                                                <i class="fas fa-trash"></i> Delete
                                                                                            </a>
                                                                                        <?php else: ?>
                                                                                            <a href="view_bill_item.php?id=<?= $it['id'] ?>&type=<?= $item_type ?>" class="btn-action-sm view" title="View">
                                                                                                <i class="fas fa-eye"></i> View
                                                                                            </a>
                                                                                            <a href="edit_bill_item.php?id=<?= $it['id'] ?>&type=<?= $item_type ?>" class="btn-action-sm edit" title="Edit">
                                                                                                <i class="fas fa-edit"></i> Edit
                                                                                            </a>
                                                                                            <a href="delete_bill_item.php?id=<?= $it['id'] ?>&type=<?= $item_type ?>" 
                                                                                               class="btn-action-sm delete" 
                                                                                               title="Delete"
                                                                                               onclick="return confirm('Delete this item? This cannot be undone!');">
                                                                                                <i class="fas fa-trash"></i> Delete
                                                                                            </a>
                                                                                        <?php endif; ?>
                                                                                    </div>
                                                                                </td>
                                                                            </tr>
                                                                        <?php endforeach; ?>
                                                                    </tbody>
                                                                </table>
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-file-invoice"></i>
                <p>No bills found</p>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <!-- ============================================================
         TAB 4: OTC BILLS
         ✅ View + Edit + Delete
         ============================================================ -->
    <?php if ($active_tab === 'otc_bills'): ?>
        
        <div class="stats-grid-5 stats-grid-4">
            <div class="stat-card-custom card-blue-1">
                <div class="stat-icon"><i class="fas fa-shopping-cart"></i></div>
                <p class="stat-label">Total OTC Sales</p>
                <p class="stat-number"><?= $otc_stats['total'] ?></p>
                <p class="stat-amount"><?= formatTsh($otc_stats['amount']) ?></p>
            </div>
            <div class="stat-card-custom card-green">
                <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                <p class="stat-label">Paid</p>
                <p class="stat-number"><?= $otc_stats['paid'] ?></p>
                <p class="stat-amount">Completed</p>
            </div>
            <div class="stat-card-custom card-orange">
                <div class="stat-icon"><i class="fas fa-clock"></i></div>
                <p class="stat-label">Pending</p>
                <p class="stat-number"><?= $otc_stats['pending'] ?></p>
                <p class="stat-amount">Unpaid</p>
            </div>
            <div class="stat-card-custom card-purple">
                <div class="stat-icon"><i class="fas fa-users"></i></div>
                <p class="stat-label">Customers</p>
                <p class="stat-number"><?= count($otc_array ?? []) ?></p>
                <p class="stat-amount">Unique</p>
            </div>
        </div>
        
        <?php if (!empty($otc_array)): ?>
            <?php foreach ($otc_array as $customer): 
                $ck = md5($customer['customer_key']);
                $has_partial = ($customer['total_partial'] ?? 0) > 0;
            ?>
                <div class="patient-card <?= $has_partial ? 'has-partial' : '' ?>" 
                     data-patient-search="<?= htmlspecialchars(strtolower($customer['customer_name'] . ' ' . $customer['customer_phone'])) ?>">
                    
                    <div class="patient-header <?= $has_partial ? 'has-partial' : '' ?>" onclick="togglePatient('otc-<?= $ck ?>')">
                        <div class="patient-info">
                            <div class="patient-avatar" style="background:<?= '#' . substr(md5($customer['customer_name']), 0, 6) ?>;">
                                <?= strtoupper(substr($customer['customer_name'], 0, 1)) ?>
                            </div>
                            <div>
                                <div class="patient-name">
                                    <span class="patient-name-text"><?= htmlspecialchars($customer['customer_name']) ?></span>
                                    
                                    <?php if ($has_partial): ?>
                                        <span class="partial-badge">
                                            <i class="fas fa-hourglass-half"></i> PARTIAL
                                            (<?= $customer['total_partial'] ?>)
                                        </span>
                                    <?php endif; ?>
                                    
                                    <span style="background:rgba(255,255,255,0.25);padding:1px 8px;border-radius:10px;font-size:0.58rem;font-weight:600;">
                                        <i class="fas fa-shopping-bag"></i> <?= $customer['total_sales'] ?> sale(s)
                                    </span>
                                    <span style="background:rgba(255,255,255,0.25);padding:1px 8px;border-radius:10px;font-size:0.58rem;font-weight:600;">
                                        <i class="fas fa-pills"></i> <?= $customer['total_items'] ?> item(s)
                                    </span>
                                </div>
                                <div class="patient-meta">
                                    <?php if (!empty($customer['customer_phone'])): ?>
                                        <span><i class="fas fa-phone"></i> <?= htmlspecialchars($customer['customer_phone']) ?></span>
                                    <?php else: ?>
                                        <span><i class="fas fa-user"></i> Walk-in Customer</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="patient-stats">
                            <span class="stat-pill">
                                <i class="fas fa-money-bill-wave"></i> <?= formatTsh($customer['total_amount']) ?>
                            </span>
                            <i class="fas fa-chevron-down chevron" id="chevron-otc-<?= $ck ?>"></i>
                        </div>
                    </div>
                    
                    <div class="patient-body" id="body-otc-<?= $ck ?>">
                        <div class="patient-actions">
                            <div class="patient-actions-info">
                                <i class="fas fa-info-circle" style="color:var(--primary);"></i>
                                <strong><?= $customer['total_sales'] ?></strong> sale(s) •
                                <strong><?= $customer['total_items'] ?></strong> item(s) •
                                Total: <strong><?= formatTsh($customer['total_amount']) ?></strong>
                            </div>
                        </div>
                        
                        <?php foreach ($customer['sales'] as $sale): 
                            $uid = $ck . '-' . $sale['id'];
                            $ss = getStatusBadge($sale['payment_status']);
                        ?>
                            <div class="visit-section">
                                <div class="visit-section-header">
                                    <div class="visit-info-left">
                                        <div class="visit-icon-badge"><i class="fas fa-shopping-cart"></i></div>
                                        <span class="visit-number-display"><?= htmlspecialchars($sale['sale_number']) ?></span>
                                        <span class="visit-date-display">
                                            <i class="fas fa-calendar-day"></i>
                                            <?= date('d M Y, H:i', strtotime($sale['created_at'])) ?>
                                        </span>
                                        <span class="visit-doctor-display">
                                            <i class="fas fa-user-tie"></i> Sold by: <?= htmlspecialchars($sale['sold_by_name'] ?? 'N/A') ?>
                                        </span>
                                        <span class="status-badge <?= $ss['class'] ?>" style="font-size:0.58rem;">
                                            <?= $ss['icon'] ?> <?= $ss['label'] ?>
                                        </span>
                                    </div>
                                    <div class="visit-stats-right">
                                        <span class="visit-mini-stat">
                                            <i class="fas fa-pills"></i>
                                            Items: <strong style="color:var(--primary);"><?= count($sale['items']) ?></strong>
                                        </span>
                                        <span class="visit-mini-stat">
                                            <i class="fas fa-money-bill-wave"></i>
                                            <strong style="color:var(--primary);"><?= formatTsh($sale['total_amount']) ?></strong>
                                        </span>
                                        <!-- ✅ SALE ACTIONS -->
                                        <div class="action-buttons-group">
                                            <a href="view_otc_sale.php?id=<?= $sale['id'] ?>" class="btn-action-sm view" title="View Sale">
                                                <i class="fas fa-eye"></i> View
                                            </a>
                                            <a href="edit_otc_sale.php?id=<?= $sale['id'] ?>" class="btn-action-sm edit" title="Edit Sale">
                                                <i class="fas fa-edit"></i> Edit
                                            </a>
                                            <a href="delete_otc_sale.php?id=<?= $sale['id'] ?>" 
                                               class="btn-action-sm delete" 
                                               title="Delete Sale"
                                               onclick="return confirm('Delete this OTC sale? This cannot be undone!');">
                                                <i class="fas fa-trash"></i> Delete
                                            </a>
                                        </div>
                                        <div class="table-nav-group">
                                            <button type="button" class="table-nav-btn" onclick="scrollTable('table-<?= $uid ?>', -1)">
                                                <i class="fas fa-chevron-left"></i>
                                            </button>
                                            <span class="table-nav-indicator" id="indicator-<?= $uid ?>">0%</span>
                                            <button type="button" class="table-nav-btn" onclick="scrollTable('table-<?= $uid ?>', 1)">
                                                <i class="fas fa-chevron-right"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <div class="visit-section-body">
                                    <div class="table-scroll-wrapper">
                                        <div class="table-scroll" id="table-<?= $uid ?>">
                                            <table class="data-table">
                                                <thead>
                                                    <tr>
                                                        <th style="width:45px;">#</th>
                                                        <th>Medication</th>
                                                        <th>Qty</th>
                                                        <th>Unit Price</th>
                                                        <th>Total Price</th>
                                                        <th>Dosage</th>
                                                        <th>Instructions</th>
                                                        <th>Status</th>
                                                        <th>Received By</th>
                                                        <th style="text-align:center;min-width:200px;">Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php $i = 1; foreach ($sale['items'] as $item): ?>
                                                        <tr data-search="<?= htmlspecialchars(strtolower($item['item_name'])) ?>">
                                                            <td style="text-align:center;font-weight:700;"><?= $i++ ?></td>
                                                            <td>
                                                                <span style="font-weight:700;color:var(--primary);">
                                                                    <?= htmlspecialchars($item['item_name']) ?>
                                                                </span>
                                                            </td>
                                                            <td><?= $item['quantity'] ?></td>
                                                            <td class="amount-cell"><?= formatTsh($item['unit_price']) ?></td>
                                                            <td class="amount-cell"><?= formatTsh($item['total_price']) ?></td>
                                                            <td style="font-size:0.7rem;"><?= htmlspecialchars($item['dosage'] ?? '—') ?></td>
                                                            <td style="font-size:0.7rem;"><?= htmlspecialchars($item['instructions'] ?? '—') ?></td>
                                                            <td>
                                                                <span class="status-badge <?= $ss['class'] ?>" style="font-size:0.55rem;">
                                                                    <?= $ss['icon'] ?> <?= $ss['label'] ?>
                                                                </span>
                                                            </td>
                                                            <td style="font-size:0.7rem;">
                                                                <i class="fas fa-user-check" style="color:var(--success);"></i>
                                                                <?= htmlspecialchars($sale['sold_by_name'] ?? 'N/A') ?>
                                                            </td>
                                                            <td style="text-align:center;">
                                                                <!-- ✅ ITEM ACTIONS -->
                                                                <div class="action-buttons-group">
                                                                    <a href="view_otc_item.php?id=<?= $item['id'] ?>" class="btn-action-sm view" title="View Item">
                                                                        <i class="fas fa-eye"></i> View
                                                                    </a>
                                                                    <a href="edit_otc_item.php?id=<?= $item['id'] ?>" class="btn-action-sm edit" title="Edit Item">
                                                                        <i class="fas fa-edit"></i> Edit
                                                                    </a>
                                                                    <a href="delete_otc_item.php?id=<?= $item['id'] ?>" 
                                                                       class="btn-action-sm delete" 
                                                                       title="Delete Item"
                                                                       onclick="return confirm('Delete this item? This cannot be undone!');">
                                                                        <i class="fas fa-trash"></i> Delete
                                                                    </a>
                                                                </div>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-shopping-cart"></i>
                <p>No OTC sales found</p>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span style="margin:0 8px;">|</span>
            Other Services (Procedures • Consultations • Bills • OTC)
            <span style="margin:0 8px;">|</span>
            <span style="color:var(--success);font-weight:700;font-size:0.65rem;">
                <i class="fas fa-shield-alt"></i> FULL ACCESS (VIEW / EDIT / DELETE)
            </span>
            <span style="margin:0 8px;">|</span>
            <span id="footerTimestamp">Last updated: <?= date('H:i:s') ?></span>
        </p>
    </footer>

</main>

<script>
function togglePatient(patientId) {
    var body = document.getElementById('body-' + patientId);
    var chevron = document.getElementById('chevron-' + patientId);
    if (body) body.classList.toggle('open');
    if (chevron) chevron.classList.toggle('rotated');
    
    setTimeout(function() {
        if (body) {
            body.querySelectorAll('.table-scroll').forEach(function(tbl) {
                if (tbl.id) updateTableNav(tbl.id);
            });
        }
    }, 450);
}

document.addEventListener('DOMContentLoaded', function() {
    var firstBody = document.querySelector('.patient-body');
    var firstChevron = document.querySelector('.chevron');
    if (firstBody) {
        setTimeout(function() {
            firstBody.classList.add('open');
            if (firstChevron) firstChevron.classList.add('rotated');
            firstBody.querySelectorAll('.table-scroll').forEach(function(tbl) {
                if (tbl.id) updateTableNav(tbl.id);
            });
        }, 300);
    }
    
    document.querySelectorAll('.table-scroll').forEach(function(tbl) {
        if (!tbl.id) return;
        tbl.addEventListener('scroll', function() { updateTableNav(tbl.id); });
        updateTableNav(tbl.id);
    });
});

function scrollTable(tableId, direction) {
    var table = document.getElementById(tableId);
    if (!table) return;
    
    var scrollAmount = 300;
    var currentScroll = table.scrollLeft;
    var maxScroll = table.scrollWidth - table.clientWidth;
    
    var newScroll = currentScroll + (direction * scrollAmount);
    if (newScroll < 0) newScroll = 0;
    if (newScroll > maxScroll) newScroll = maxScroll;
    
    table.scrollTo({ left: newScroll, behavior: 'smooth' });
    setTimeout(function() { updateTableNav(tableId); }, 350);
}

function updateTableNav(tableId) {
    var table = document.getElementById(tableId);
    if (!table) return;
    
    var uniqueId = tableId.replace('table-', '');
    var indicator = document.getElementById('indicator-' + uniqueId);
    
    var currentScroll = table.scrollLeft;
    var maxScroll = table.scrollWidth - table.clientWidth;
    
    var percent = 0;
    if (maxScroll > 0) percent = Math.round((currentScroll / maxScroll) * 100);
    if (indicator) indicator.textContent = percent + '%';
    
    var navGroup = null;
    var section = table.closest('.visit-section');
    if (section) {
        navGroup = section.querySelector('.visit-section-header .table-nav-group');
    }
    
    if (navGroup) {
        var btns = navGroup.querySelectorAll('.table-nav-btn');
        if (btns.length >= 2) {
            btns[0].disabled = (currentScroll <= 1);
            btns[1].disabled = (currentScroll >= maxScroll - 1);
        }
    }
}

(function() {
    var input = document.getElementById('pageSearchInput');
    if (!input) return;
    
    input.addEventListener('input', function() {
        var q = this.value.toLowerCase().trim();
        var cards = document.querySelectorAll('.patient-card');
        
        cards.forEach(function(card) {
            var data = card.getAttribute('data-patient-search') || '';
            var rows = card.querySelectorAll('tbody tr[data-search]');
            var hasMatch = false;
            
            if (q === '') hasMatch = true;
            else if (data.includes(q)) hasMatch = true;
            
            rows.forEach(function(row) {
                var rd = row.getAttribute('data-search') || '';
                if (q === '' || rd.includes(q) || data.includes(q)) {
                    hasMatch = true;
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
            
            if (hasMatch) card.classList.remove('filtered-out');
            else card.classList.add('filtered-out');
        });
    });
})();

setInterval(function() {
    var now = new Date();
    var t = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true });
    var ft = document.getElementById('footerTimestamp');
    if (ft) ft.textContent = 'Last updated: ' + t;
}, 1000);

console.log('%c🔍 Audit - Other Services (V5 - FULL ACCESS)', 'font-size:16px;font-weight:bold;color:#0B5ED7;');
console.log('%c✅ View + Edit + Delete kwenye KILA tab', 'font-size:12px;color:#34D399;font-weight:bold;');
console.log('%c✅ Consultation tab sasa ina Edit + Delete', 'font-size:12px;color:#34D399;font-weight:bold;');
console.log('%c✅ All Bills items wana Edit + Delete', 'font-size:12px;color:#34D399;');
console.log('%c✅ OTC items wana Edit + Delete', 'font-size:12px;color:#34D399;');
console.log('%c✅ All action buttons size sawa (unified)', 'font-size:12px;color:#34D399;');
console.log('%c✅ 4 Tabs: Procedures | Consultations | All Bills | OTC', 'font-size:12px;color:#34D399;');
</script>

</body>
</html>