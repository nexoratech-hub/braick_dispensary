<?php
// ================================================================
// FILE: frontend/pages/pharmacy/prescription_history.php
// PHARMACY - PRESCRIPTION HISTORY V8 (BILL INFO FIXED)
// ================================================================
// ✅ FIXED V8: Bill info inachukua kwa visit_id + patient_id (SI prescription_id)
// ✅ FIXED V8: Pharm Discount + Pharm Premium zinaonekana SAHIHI
// ✅ FIXED: Medication Total row + Net Total row (kama pending_prescriptions)
// ✅ FIXED: Tab filter inafanya kazi
// ================================================================

session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'pharmacy') {
    header('Location: ../login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$user_full_name = $_SESSION['full_name'] ?? 'Pharmacy Staff';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Branch';
$user_username = $_SESSION['username'] ?? 'pharmacy';
$profile_pic = $_SESSION['profile_pic'] ?? '';

require_once __DIR__ . '/../../../backend/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

$message = '';
$message_type = '';
$currency = 'TSh';

try {
    $settings = [];
    $stmt = $db->query("SELECT setting_key, setting_value FROM system_settings");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    $currency = $settings['currency'] ?? 'TSh';
} catch (Exception $e) {}

// ================================================================
// HELPER FUNCTIONS
// ================================================================
function formatDate($datetime, $showTime = true) {
    if (empty($datetime) || $datetime === '0000-00-00 00:00:00') return 'N/A';
    $timestamp = strtotime($datetime);
    if ($timestamp === false) return 'N/A';
    return $showTime ? date('d M Y, H:i', $timestamp) : date('d M Y', $timestamp);
}

function timeAgo($datetime) {
    if (empty($datetime) || $datetime === '0000-00-00 00:00:00') return 'N/A';
    $timestamp = strtotime($datetime);
    if ($timestamp === false) return 'N/A';
    $now = time();
    $diff = $now - $timestamp;
    if ($diff < 60) return 'Just now';
    elseif ($diff < 3600) { $m = floor($diff / 60); return $m . ' min' . ($m > 1 ? 's' : '') . ' ago'; }
    elseif ($diff < 86400) { $h = floor($diff / 3600); return $h . ' hour' . ($h > 1 ? 's' : '') . ' ago'; }
    elseif ($diff < 604800) { $d = floor($diff / 86400); return $d . ' day' . ($d > 1 ? 's' : '') . ' ago'; }
    else return date('d M Y', $timestamp);
}

function calculateAge($dob) {
    if (empty($dob)) return 'N/A';
    return (new DateTime($dob))->diff(new DateTime('today'))->y;
}

function getStatusBadgeClass($status) {
    $map = ['pending' => 'badge-warning', 'confirmed' => 'badge-info', 'dispensed' => 'badge-success', 'cancelled' => 'badge-danger'];
    return $map[$status] ?? 'badge-warning';
}

function getStatusLabel($status) {
    $map = ['pending' => '⏳ Pending', 'confirmed' => '✅ Confirmed', 'dispensed' => '💊 Dispensed', 'cancelled' => '❌ Cancelled'];
    return $map[$status] ?? ucfirst($status);
}

function getViewUrl($status, $patient_id, $visit_id) {
    $base_params = '?patient_id=' . urlencode($patient_id) . '&visit_id=' . urlencode($visit_id);
    switch ($status) {
        case 'confirmed': return 'view_confirmed_prescriptions.php' . $base_params;
        case 'dispensed': return 'view_dispensed_prescriptions.php' . $base_params;
        case 'pending':
        default: return 'view_patient_prescriptions.php' . $base_params;
    }
}

function getViewIcon($status) {
    $map = ['pending' => 'fa-eye', 'confirmed' => 'fa-check-circle', 'dispensed' => 'fa-pills', 'cancelled' => 'fa-ban'];
    return $map[$status] ?? 'fa-eye';
}

function getViewButtonStyle($status) {
    $map = [
        'pending' => 'background: linear-gradient(135deg, #D97706, #B45309);',
        'confirmed' => 'background: linear-gradient(135deg, #3B82F6, #0B5ED7);',
        'dispensed' => 'background: linear-gradient(135deg, #059669, #047857);',
        'cancelled' => 'background: linear-gradient(135deg, #6B7280, #4B5563); pointer-events: none; opacity: 0.7;'
    ];
    return $map[$status] ?? '';
}

function getViewTitle($status) {
    $map = [
        'pending' => 'View & Confirm Prescriptions',
        'confirmed' => 'View Confirmed Prescriptions',
        'dispensed' => 'View Dispensed Prescriptions',
        'cancelled' => 'Cancelled - No view available'
    ];
    return $map[$status] ?? 'View Prescriptions';
}

// ================================================================
// FILTER PARAMETERS
// ================================================================
$filter_status = isset($_GET['status']) ? $_GET['status'] : 'all';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

// ================================================================
// BUILD QUERY
// ================================================================
$conditions = ["p.branch_id = ?"];
$params = [$user_branch_id];

if ($filter_status === 'all') {
    $conditions[] = "p.status IN ('pending', 'confirmed', 'dispensed', 'cancelled')";
} else {
    $conditions[] = "p.status = ?";
    $params[] = $filter_status;
}

if (!empty($search)) {
    $conditions[] = "(pat.full_name LIKE ? OR pat.patient_id LIKE ? OR p.prescription_number LIKE ? OR EXISTS (SELECT 1 FROM prescription_items pi WHERE pi.prescription_id = p.id AND pi.medication_name LIKE ?))";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($date_from)) { $conditions[] = "DATE(p.created_at) >= ?"; $params[] = $date_from; }
if (!empty($date_to)) { $conditions[] = "DATE(p.created_at) <= ?"; $params[] = $date_to; }

$where_clause = implode(" AND ", $conditions);

// ================================================================
// GET PATIENTS
// ================================================================
$sql = "
    SELECT 
        pat.id as patient_id,
        pat.full_name as patient_name,
        pat.patient_id as patient_number,
        pat.phone as patient_phone,
        pat.gender as patient_gender,
        pat.date_of_birth,
        MAX(p.created_at) as last_prescription_date,
        MIN(p.created_at) as first_prescription_date,
        COUNT(DISTINCT p.id) as prescription_count,
        COUNT(DISTINCT p.visit_id) as visit_count
    FROM prescriptions p
    INNER JOIN patients pat ON p.patient_id = pat.id
    WHERE $where_clause
    AND EXISTS (
        SELECT 1 FROM prescription_items pi 
        WHERE pi.prescription_id = p.id 
        AND pi.quantity > 0
    )
    GROUP BY pat.id, pat.full_name, pat.patient_id, pat.phone, pat.gender, pat.date_of_birth
    ORDER BY last_prescription_date DESC
";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$grouped_prescriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// GET PRESCRIPTIONS + ITEMS + BILL INFO
// ================================================================
$patient_ids = array_column($grouped_prescriptions, 'patient_id');
$visits_map = [];

if (!empty($patient_ids)) {
    $placeholders = implode(',', array_fill(0, count($patient_ids), '?'));
    
    $pres_status_cond = "";
    $pres_status_params = [];
    if ($filter_status === 'all') {
        $pres_status_cond = "AND p.status IN ('pending', 'confirmed', 'dispensed', 'cancelled')";
    } else {
        $pres_status_cond = "AND p.status = ?";
        $pres_status_params[] = $filter_status;
    }
    
    $pres_sql = "
        SELECT 
            p.id as prescription_id,
            p.prescription_number,
            p.visit_id,
            p.status as prescription_status,
            p.created_at as prescription_date,
            p.updated_at as prescription_updated_at,
            p.dispensed_at as prescription_dispensed_at,
            p.patient_id,
            u.full_name as doctor_name,
            v.visit_number,
            v.visit_date,
            v.created_at as visit_created_at
        FROM prescriptions p
        LEFT JOIN users u ON p.doctor_id = u.id
        LEFT JOIN visits v ON p.visit_id = v.id
        WHERE p.patient_id IN ($placeholders)
        AND p.branch_id = ?
        $pres_status_cond
        AND EXISTS (
            SELECT 1 FROM prescription_items pi 
            WHERE pi.prescription_id = p.id 
            AND pi.quantity > 0
        )
        ORDER BY p.visit_id DESC, p.created_at DESC
    ";
    $pres_params = array_merge($patient_ids, [$user_branch_id], $pres_status_params);
    $stmt = $db->prepare($pres_sql);
    $stmt->execute($pres_params);
    $all_prescriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $items_sql = "
        SELECT 
            pi.*,
            p.prescription_number,
            p.visit_id,
            p.status as prescription_status,
            p.created_at as prescription_date,
            bi.unit_price as bill_unit_price,
            bi.discount_amount as bill_item_discount,
            (COALESCE(bi.unit_price, pi.unit_price, 0) * pi.quantity) as calculated_total_price
        FROM prescription_items pi
        INNER JOIN prescriptions p ON pi.prescription_id = p.id
        LEFT JOIN bill_items bi ON bi.reference_id = p.id 
            AND bi.reference_type = 'prescription' 
            AND bi.item_type = 'medication'
            AND bi.status != 'cancelled'
            AND (bi.item_name LIKE CONCAT('%', pi.medication_name, '%') OR bi.item_name = pi.medication_name)
        WHERE pi.patient_id IN ($placeholders)
        AND p.branch_id = ?
        $pres_status_cond
        AND pi.quantity > 0
        ORDER BY p.visit_id DESC, p.created_at DESC, pi.id DESC
    ";
    $stmt = $db->prepare($items_sql);
    $stmt->execute($pres_params);
    $all_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $items_by_prescription = [];
    foreach ($all_items as $item) {
        $items_by_prescription[$item['prescription_id']][] = $item;
    }
    
    foreach ($all_prescriptions as $pres) {
        $pid = $pres['patient_id'];
        $vid = $pres['visit_id'] ?? 0;
        $pres_status = $pres['prescription_status'] ?? 'pending';
        
        if (!isset($visits_map[$pid])) $visits_map[$pid] = [];
        
        if (!isset($visits_map[$pid][$vid])) {
            $visits_map[$pid][$vid] = [
                'visit_id' => $vid,
                'visit_number' => $pres['visit_number'] ?? ($vid > 0 ? 'VIS-' . $vid : 'NO-VISIT'),
                'visit_date' => $pres['visit_date'] ?? $pres['visit_created_at'] ?? $pres['prescription_date'],
                'doctor_names' => [],
                'prescriptions' => [],
                'items' => [],
                'statuses' => [],
                'dates' => [],
                'total_qty' => 0,
                'medication_count' => 0,
                'total_amount' => 0,
                'bill_id' => null,
                'bill_number' => null,
                'bill_status' => null,
                'bill_pharmacy_discount' => 0,
                'bill_pharmacy_premium' => 0
            ];
        }
        
        $visits_map[$pid][$vid]['prescriptions'][] = $pres;
        
        if (!empty($pres['doctor_name']) && !in_array($pres['doctor_name'], $visits_map[$pid][$vid]['doctor_names'])) {
            $visits_map[$pid][$vid]['doctor_names'][] = $pres['doctor_name'];
        }
        
        $visits_map[$pid][$vid]['statuses'][] = $pres_status;
        
        if (!empty($pres['prescription_date'])) {
            $visits_map[$pid][$vid]['dates'][] = $pres['prescription_date'];
        }
        
        if (isset($items_by_prescription[$pres['prescription_id']])) {
            foreach ($items_by_prescription[$pres['prescription_id']] as $item) {
                $visits_map[$pid][$vid]['items'][] = $item;
                $visits_map[$pid][$vid]['total_qty'] += $item['quantity'];
                $visits_map[$pid][$vid]['medication_count']++;
                $item_unit_price = (float)($item['bill_unit_price'] ?? $item['unit_price'] ?? 0);
                $visits_map[$pid][$vid]['total_amount'] += $item_unit_price * $item['quantity'];
            }
        }
    }
    
    // ================================================================
    // ✅ V8 FIX: Calculate overall_status + BILL INFO
    // ✅ Tumia visit_id + patient_id (SI prescription_id pekee)
    // ✅ Inachukua pharmacy_discount na pharmacy_premium PEKEE
    // ================================================================
    foreach ($visits_map as $pid => &$patient_visits) {
        foreach ($patient_visits as $vid => &$visit) {
            if (empty($visit['items']) || $visit['total_qty'] <= 0) {
                unset($patient_visits[$vid]);
                continue;
            }
            
            $statuses = $visit['statuses'];
            if (in_array('pending', $statuses)) $visit['overall_status'] = 'pending';
            elseif (in_array('confirmed', $statuses)) $visit['overall_status'] = 'confirmed';
            elseif (in_array('dispensed', $statuses)) $visit['overall_status'] = 'dispensed';
            elseif (in_array('cancelled', $statuses)) $visit['overall_status'] = 'cancelled';
            else $visit['overall_status'] = 'pending';
            
            rsort($visit['dates']);
            $visit['latest_date'] = !empty($visit['dates']) ? $visit['dates'][0] : null;
            
            // ================================================================
            // ✅ V8 FIX: GET BILL INFO using visit_id + patient_id
            // ✅ Inachukua pharmacy_discount na pharmacy_premium PEKEE
            // ================================================================
            if ($vid > 0) {
                $stmt_bill = $db->prepare("
                    SELECT 
                        b.id as bill_id,
                        b.bill_number,
                        b.status as bill_status,
                        b.pharmacy_discount,
                        b.pharmacy_premium
                    FROM bills b
                    WHERE b.patient_id = ? 
                      AND b.visit_id = ?
                      AND b.branch_id = ?
                      AND b.status != 'cancelled'
                    ORDER BY b.created_at DESC
                    LIMIT 1
                ");
                $stmt_bill->execute([$pid, $vid, $user_branch_id]);
                $bill_row = $stmt_bill->fetch(PDO::FETCH_ASSOC);
                
                if ($bill_row && $bill_row['bill_id']) {
                    // ✅ Verify bill ina medication items
                    $stmt_check = $db->prepare("
                        SELECT COUNT(*) as med_count 
                        FROM bill_items 
                        WHERE bill_id = ? 
                        AND item_type = 'medication' 
                        AND status != 'cancelled'
                    ");
                    $stmt_check->execute([$bill_row['bill_id']]);
                    $check = $stmt_check->fetch(PDO::FETCH_ASSOC);
                    
                    if (($check['med_count'] ?? 0) > 0) {
                        $visit['bill_id'] = $bill_row['bill_id'];
                        $visit['bill_number'] = $bill_row['bill_number'];
                        $visit['bill_status'] = $bill_row['bill_status'];
                        $visit['bill_pharmacy_discount'] = (float)($bill_row['pharmacy_discount'] ?? 0);
                        $visit['bill_pharmacy_premium'] = (float)($bill_row['pharmacy_premium'] ?? 0);
                    }
                }
            }
        }
        unset($visit);
    }
    unset($patient_visits);
}

// ================================================================
// CALCULATE TOTALS PER PATIENT
// ================================================================
foreach ($grouped_prescriptions as &$group) {
    $pid = $group['patient_id'];
    $patient_visits = $visits_map[$pid] ?? [];
    
    $total_qty = 0;
    $medication_count = 0;
    $total_amount = 0;
    $all_doctor_names = [];
    
    foreach ($patient_visits as $visit) {
        $total_qty += $visit['total_qty'];
        $medication_count += $visit['medication_count'];
        $total_amount += $visit['total_amount'];
        
        foreach ($visit['doctor_names'] as $doc) {
            if (!in_array($doc, $all_doctor_names)) $all_doctor_names[] = $doc;
        }
    }
    
    $sorted_visits = $patient_visits;
    uasort($sorted_visits, function($a, $b) {
        $date_a = strtotime($a['latest_date'] ?? '1970-01-01');
        $date_b = strtotime($b['latest_date'] ?? '1970-01-01');
        return $date_b - $date_a;
    });
    
    $group['visits'] = $sorted_visits;
    $group['total_qty'] = $total_qty;
    $group['medication_count'] = $medication_count;
    $group['total_amount'] = $total_amount;
    $group['doctor_names_array'] = $all_doctor_names;
    $group['visit_count'] = count($sorted_visits);
    $group['prescription_count'] = count($sorted_visits);
}
unset($group);

$grouped_prescriptions = array_filter($grouped_prescriptions, function($g) {
    return !empty($g['visits']) && $g['total_qty'] > 0;
});
$grouped_prescriptions = array_values($grouped_prescriptions);

// ================================================================
// STATISTICS
// ================================================================
function getStatusCount($db, $user_branch_id, $status = null, $search = '', $date_from = '', $date_to = '') {
    $conditions = ["p.branch_id = ?"];
    $params = [$user_branch_id];
    
    if ($status !== null && $status !== 'all') {
        $conditions[] = "p.status = ?";
        $params[] = $status;
    } else {
        $conditions[] = "p.status IN ('pending', 'confirmed', 'dispensed', 'cancelled')";
    }
    
    if (!empty($search)) {
        $conditions[] = "(pat.full_name LIKE ? OR pat.patient_id LIKE ? OR p.prescription_number LIKE ? OR EXISTS (SELECT 1 FROM prescription_items pi WHERE pi.prescription_id = p.id AND pi.medication_name LIKE ?))";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    if (!empty($date_from)) { $conditions[] = "DATE(p.created_at) >= ?"; $params[] = $date_from; }
    if (!empty($date_to)) { $conditions[] = "DATE(p.created_at) <= ?"; $params[] = $date_to; }
    
    $where = implode(" AND ", $conditions);
    $sql = "SELECT COUNT(DISTINCT p.patient_id) as total FROM prescriptions p INNER JOIN patients pat ON p.patient_id = pat.id WHERE $where AND EXISTS (SELECT 1 FROM prescription_items pi WHERE pi.prescription_id = p.id AND pi.quantity > 0)";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return (int)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
}

$total_all = getStatusCount($db, $user_branch_id, 'all', $search, $date_from, $date_to);
$pending_count = getStatusCount($db, $user_branch_id, 'pending', $search, $date_from, $date_to);
$confirmed_count = getStatusCount($db, $user_branch_id, 'confirmed', $search, $date_from, $date_to);
$dispensed_count = getStatusCount($db, $user_branch_id, 'dispensed', $search, $date_from, $date_to);
$cancelled_count = getStatusCount($db, $user_branch_id, 'cancelled', $search, $date_from, $date_to);

$profile_pic_url = !empty($profile_pic) ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';
$logo_path = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

include_once '../../components/pharmacy_header.php';
include_once '../../components/pharmacy_sidebar.php';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prescription History - Braick Dispensary</title>
    <link rel="icon" href="<?= $logo_path ?>" type="image/png">
    <link rel="shortcut icon" href="<?= $logo_path ?>" type="image/png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=JetBrains+Mono:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --font-primary: 'Inter', -apple-system, sans-serif;
            --font-mono: 'JetBrains Mono', 'Courier New', monospace;
            --primary: #0B5ED7;
            --primary-dark: #0A4CA8;
            --primary-light: #6EA8FE;
            --primary-bg: #E8F0FE;
            --info: #3B82F6;
            --info-bg: #DBEAFE;
            --success: #059669;
            --success-dark: #047857;
            --success-bg: #D1FAE5;
            --danger: #DC2626;
            --danger-bg: #FEE2E2;
            --warning: #D97706;
            --warning-bg: #FEF3C7;
            --purple: #7C3AED;
            --purple-bg: #EDE9FE;
            --gray-50: #F8FAFC;
            --gray-200: #E2E8F0;
            --bg-body: #F1F5F9;
            --bg-card: #FFFFFF;
            --text-primary: #1E293B;
            --text-secondary: #64748B;
            --border-color: #E2E8F0;
            --radius: 10px;
            --radius-lg: 14px;
            --shadow: 0 1px 3px rgba(0,0,0,0.06);
            --shadow-md: 0 4px 16px rgba(0,0,0,0.08);
            --shadow-lg: 0 8px 30px rgba(0,0,0,0.12);
        }
        
        [data-theme="dark"] {
            --bg-body: #0F172A;
            --bg-card: #1E293B;
            --text-primary: #F1F5F9;
            --text-secondary: #94A3B8;
            --border-color: #334155;
            --purple-bg: #2D1B4E;
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: var(--font-primary);
            background: var(--bg-body);
            color: var(--text-primary);
            -webkit-font-smoothing: antialiased;
        }
        
        .mono, .patient-id, .qty-number, .money, .date-mono, .visit-number-badge, .discount-value, .premium-value {
            font-family: var(--font-mono) !important;
            font-feature-settings: 'tnum';
            font-variant-numeric: tabular-nums;
        }
        
        ::-webkit-scrollbar { width: 5px; height: 5px; }
        ::-webkit-scrollbar-track { background: var(--bg-body); }
        ::-webkit-scrollbar-thumb { background: var(--primary); border-radius: 10px; }
        
        .main-content {
            margin-left: 270px;
            margin-top: 68px;
            padding: 24px 28px;
            min-height: calc(100vh - 68px);
        }
        
        .page-header {
            background: linear-gradient(135deg, #0B5ED7, #0A4CA8, #083C8A);
            border-radius: 16px;
            padding: 20px 28px;
            margin-bottom: 16px;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 14px;
            box-shadow: 0 4px 20px rgba(11, 94, 215, 0.3);
            position: relative;
            overflow: hidden;
        }
        
        .page-header::before {
            content: '';
            position: absolute;
            top: -50%; right: -20%;
            width: 300px; height: 300px;
            background: rgba(255,255,255,0.05);
            border-radius: 50%;
            pointer-events: none;
        }
        
        .page-header .page-title {
            color: white;
            font-size: 1.6rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
        }
        
        .page-header .page-title i { font-size: 1.8rem; opacity: 0.9; }
        
        .page-header .page-subtitle {
            color: rgba(255,255,255,0.85);
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
        }
        
        .page-header .role-badge-display {
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 3px 12px;
            border-radius: 20px;
            font-size: 0.6rem;
            font-weight: 600;
            text-transform: uppercase;
            backdrop-filter: blur(4px);
        }
        
        .page-header .branch-tag {
            background: rgba(255,255,255,0.15);
            color: white;
            padding: 2px 12px;
            border-radius: 20px;
            font-size: 0.65rem;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            backdrop-filter: blur(4px);
        }
        
        .page-header .btn-outline-light {
            background: rgba(255,255,255,0.12);
            color: white;
            border: 1px solid rgba(255,255,255,0.2);
            padding: 7px 14px;
            border-radius: 10px;
            font-weight: 500;
            font-size: 0.78rem;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            backdrop-filter: blur(4px);
            position: relative;
            z-index: 1;
        }
        
        .page-header .btn-outline-light:hover {
            background: rgba(255,255,255,0.25);
            transform: translateY(-2px);
        }
        
        .filter-tabs {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            align-items: center;
            margin-bottom: 14px;
            padding: 10px 14px;
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow);
        }
        
        .filter-tab {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 14px;
            border-radius: 10px;
            font-size: 0.72rem;
            font-weight: 700;
            text-decoration: none;
            border: 2px solid var(--border-color);
            background: transparent;
            color: var(--text-secondary);
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .filter-tab:hover {
            border-color: var(--primary);
            color: var(--primary);
            background: var(--primary-bg);
            transform: translateY(-1px);
        }
        
        .filter-tab .tab-count {
            background: var(--gray-200);
            color: var(--text-secondary);
            padding: 1px 8px;
            border-radius: 10px;
            font-size: 0.62rem;
            font-weight: 800;
            font-family: var(--font-mono);
        }
        
        .filter-tab:hover .tab-count { background: var(--primary); color: white; }
        
        .filter-tab.active {
            border-color: transparent;
            color: white;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        
        .filter-tab.active .tab-count { background: rgba(255,255,255,0.3); color: white; }
        
        .filter-tab.all.active { background: linear-gradient(135deg, #0B5ED7, #0A4CA8); }
        .filter-tab.pending.active { background: linear-gradient(135deg, #D97706, #B45309); }
        .filter-tab.confirmed.active { background: linear-gradient(135deg, #3B82F6, #0B5ED7); }
        .filter-tab.dispensed.active { background: linear-gradient(135deg, #059669, #047857); }
        .filter-tab.cancelled.active { background: linear-gradient(135deg, #DC2626, #B91C1C); }
        
        .filter-tab .tab-icon { font-size: 0.85rem; }
        
        .date-filters {
            display: flex;
            gap: 6px;
            align-items: center;
            margin-left: auto;
            flex-wrap: wrap;
        }
        
        .date-input {
            padding: 6px 10px;
            border: 2px solid var(--border-color);
            border-radius: 8px;
            font-size: 0.72rem;
            background: var(--bg-card);
            color: var(--text-primary);
            outline: none;
            height: 32px;
        }
        
        .date-input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.1);
        }
        
        .btn-date-filter {
            padding: 6px 14px;
            background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 700;
            font-size: 0.7rem;
            cursor: pointer;
            height: 32px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .btn-date-filter:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3); }
        
        .btn-clear-filter {
            padding: 6px 12px;
            background: transparent;
            color: var(--danger);
            border: 2px solid var(--danger);
            border-radius: 8px;
            font-weight: 700;
            font-size: 0.7rem;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            height: 32px;
        }
        
        .btn-clear-filter:hover { background: var(--danger); color: white; }
        
        .search-toolbar {
            background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
            border-radius: var(--radius-lg);
            padding: 10px 18px;
            margin-bottom: 14px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
            box-shadow: 0 4px 16px rgba(11, 94, 215, 0.2);
        }
        
        .search-toolbar .toolbar-title {
            color: white;
            font-size: 0.82rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .search-toolbar .search-wrapper {
            position: relative;
            display: flex;
            align-items: center;
            background: rgba(255,255,255,0.15);
            border: 2px solid rgba(255,255,255,0.25);
            border-radius: 10px;
            padding: 0 14px;
            min-width: 340px;
            height: 36px;
        }
        
        .search-toolbar .search-wrapper:focus-within {
            background: rgba(255,255,255,0.25);
            border-color: rgba(255,255,255,0.5);
        }
        
        .search-toolbar .search-wrapper .search-icon {
            color: rgba(255,255,255,0.8);
            font-size: 0.85rem;
            margin-right: 8px;
        }
        
        .search-toolbar .search-wrapper input {
            flex: 1;
            background: transparent;
            border: none;
            outline: none;
            color: white;
            font-size: 0.8rem;
            font-family: var(--font-primary);
        }
        
        .search-toolbar .search-wrapper input::placeholder { color: rgba(255,255,255,0.6); }
        
        .search-toolbar .search-wrapper .search-clear {
            background: rgba(255,255,255,0.2);
            border: none;
            color: white;
            width: 22px; height: 22px;
            border-radius: 50%;
            cursor: pointer;
            display: none;
            align-items: center;
            justify-content: center;
            font-size: 0.7rem;
            margin-left: 8px;
        }
        
        .search-toolbar .search-wrapper .search-clear.visible { display: flex; }
        
        .search-results-count {
            color: rgba(255,255,255,0.9);
            font-size: 0.68rem;
            font-weight: 600;
            background: rgba(255,255,255,0.2);
            padding: 3px 12px;
            border-radius: 12px;
            margin-left: 8px;
            display: none;
            font-family: var(--font-mono);
        }
        
        .search-info-box {
            display: none;
            margin-bottom: 14px;
            padding: 10px 16px;
            border-radius: 12px;
            background: linear-gradient(135deg, #E8F0FE, #D6E4FF);
            border: 2px solid var(--primary);
            align-items: center;
            gap: 14px;
            flex-wrap: wrap;
        }
        
        [data-theme="dark"] .search-info-box { background: linear-gradient(135deg, #1E3A5F, #16294A); }
        
        .search-info-box.show { display: flex; }
        
        .search-info-box .info-icon {
            width: 42px; height: 42px;
            border-radius: 50%;
            background: var(--primary);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            flex-shrink: 0;
        }
        
        .search-info-box .info-text { flex: 1; min-width: 160px; }
        
        .search-info-box .info-label {
            font-size: 0.6rem;
            color: var(--text-secondary);
            font-weight: 700;
            text-transform: uppercase;
        }
        
        .search-info-box .info-value {
            font-size: 1.3rem;
            font-weight: 800;
            color: var(--primary);
            font-family: var(--font-mono);
        }
        
        .search-info-box .info-value small {
            font-size: 0.65rem;
            font-weight: 500;
            color: var(--text-secondary);
            margin-left: 6px;
            font-family: var(--font-primary);
        }
        
        .search-info-box .info-divider {
            width: 2px;
            height: 38px;
            background: var(--border-color);
        }
        
        .patient-card {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            border: 2px solid var(--border-color);
            margin-bottom: 14px;
            overflow: hidden;
            box-shadow: var(--shadow);
            transition: all 0.3s ease;
        }
        
        .patient-card:hover { border-color: var(--primary); box-shadow: var(--shadow-md); }
        
        .patient-header {
            background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
            color: white;
            padding: 10px 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
            cursor: pointer;
        }
        
        .patient-header:hover { background: linear-gradient(135deg, #0A4CA8, #083C8A); }
        
        .patient-header .patient-info {
            display: flex;
            align-items: center;
            gap: 10px;
            flex: 1;
            min-width: 220px;
        }
        
        .patient-header .patient-avatar {
            width: 36px; height: 36px;
            border-radius: 50%;
            background: rgba(255,255,255,0.25);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1rem;
            color: white;
            border: 2px solid rgba(255,255,255,0.4);
            font-family: var(--font-mono);
            flex-shrink: 0;
        }
        
        .patient-header .patient-name {
            font-weight: 700;
            font-size: 0.88rem;
            display: flex;
            align-items: center;
            gap: 6px;
            flex-wrap: wrap;
        }
        
        .patient-header .patient-meta {
            display: flex;
            gap: 10px;
            font-size: 0.68rem;
            opacity: 0.9;
            flex-wrap: wrap;
            margin-top: 2px;
        }
        
        .patient-header .patient-meta span { display: flex; align-items: center; gap: 3px; }
        
        .patient-header .patient-stats {
            display: flex;
            gap: 8px;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .patient-header .patient-stats .stat-pill {
            background: rgba(255,255,255,0.2);
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.65rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            border: 1px solid rgba(255,255,255,0.15);
        }
        
        .patient-header .stat-pill.date-pill {
            background: rgba(255,255,255,0.3);
            border: 1px solid rgba(255,255,255,0.4);
            font-family: var(--font-mono);
        }
        
        .patient-header .stat-pill .qty-value,
        .patient-header .stat-pill .money-value {
            font-family: var(--font-mono);
            font-weight: 800;
        }
        
        .patient-header .chevron { font-size: 0.85rem; transition: transform 0.3s ease; }
        .patient-header .chevron.rotated { transform: rotate(180deg); }
        
        .patient-body {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.4s ease, padding 0.3s ease;
        }
        
        .patient-body.open { max-height: 20000px; padding: 12px 16px 14px; }
        
        .visit-section {
            border: 2px solid var(--border-color);
            border-radius: 12px;
            margin-bottom: 14px;
            overflow: hidden;
            transition: all 0.3s ease;
        }
        
        .visit-section:last-child { margin-bottom: 0; }
        
        .visit-section:hover {
            border-color: var(--primary);
            box-shadow: 0 4px 12px rgba(11, 94, 215, 0.1);
        }
        
        .visit-section-header {
            background: linear-gradient(135deg, #E8F0FE, #D6E4FF);
            padding: 10px 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
            border-bottom: 2px solid var(--primary-light);
        }
        
        [data-theme="dark"] .visit-section-header {
            background: linear-gradient(135deg, #1E3A5F, #16294A);
        }
        
        .visit-section-header .visit-info-left {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .visit-section-header .visit-icon-badge {
            width: 36px; height: 36px;
            border-radius: 10px;
            background: linear-gradient(135deg, #FCD34D, #F59E0B);
            color: #78350F;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            box-shadow: 0 3px 10px rgba(245, 158, 11, 0.3);
            flex-shrink: 0;
        }
        
        .visit-section-header .visit-number-display {
            font-family: var(--font-mono);
            font-weight: 800;
            font-size: 0.85rem;
            color: #78350F;
            background: rgba(255,255,255,0.5);
            padding: 3px 10px;
            border-radius: 8px;
            border: 1px solid rgba(245, 158, 11, 0.3);
        }
        
        [data-theme="dark"] .visit-section-header .visit-number-display {
            background: rgba(255,255,255,0.1);
            color: #FCD34D;
        }
        
        .visit-section-header .visit-date-display {
            font-family: var(--font-mono);
            font-size: 0.7rem;
            color: var(--text-secondary);
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-weight: 600;
        }
        
        .visit-section-header .visit-doctor-display {
            font-size: 0.72rem;
            color: var(--primary);
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            background: rgba(255,255,255,0.6);
            padding: 3px 10px;
            border-radius: 20px;
            border: 1px solid var(--primary-light);
        }
        
        [data-theme="dark"] .visit-section-header .visit-doctor-display {
            background: rgba(255,255,255,0.1);
            color: #93C5FD;
            border-color: #3B82F6;
        }
        
        .visit-section-header .visit-stats-right {
            display: flex;
            gap: 6px;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .visit-section-header .visit-mini-stat {
            background: rgba(255,255,255,0.7);
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 0.65rem;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            color: var(--text-primary);
            border: 1px solid rgba(255,255,255,0.5);
        }
        
        [data-theme="dark"] .visit-section-header .visit-mini-stat {
            background: rgba(255,255,255,0.1);
            color: var(--text-primary);
            border-color: rgba(255,255,255,0.15);
        }
        
        .visit-section-header .visit-mini-stat .stat-value {
            font-family: var(--font-mono);
            font-weight: 800;
            color: var(--primary);
        }
        
        [data-theme="dark"] .visit-section-header .visit-mini-stat .stat-value { color: var(--primary-light); }
        
        .btn-view-visit {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
            padding: 6px 14px;
            border-radius: 8px;
            font-weight: 700;
            font-size: 0.68rem;
            color: white;
            border: none;
            cursor: pointer;
            text-decoration: none;
            min-width: 70px;
            transition: all 0.3s ease;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.15);
        }
        
        .btn-view-visit:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 16px rgba(0, 0, 0, 0.3);
        }
        
        .visit-section-body {
            padding: 12px 16px 14px;
            background: var(--bg-card);
        }
        
        .bill-summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(100px, 1fr));
            gap: 6px;
            padding: 8px 12px;
            background: linear-gradient(135deg, #F8FAFC, #F1F5F9);
            border-radius: 10px;
            border: 1.5px solid var(--primary-light);
            margin-bottom: 10px;
        }
        
        [data-theme="dark"] .bill-summary {
            background: linear-gradient(135deg, #1E293B, #0F172A);
            border-color: #3B82F6;
        }
        
        .bill-summary-item {
            display: flex;
            flex-direction: column;
            gap: 1px;
            text-align: center;
            padding: 3px 6px;
            border-right: 1px solid var(--border-color);
            min-height: 42px;
            justify-content: center;
        }
        
        .bill-summary-item:last-child { border-right: none; }
        
        .bill-summary-item .label {
            font-size: 0.5rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: var(--text-secondary);
        }
        
        .bill-summary-item .value {
            font-size: 0.78rem;
            font-weight: 800;
            font-family: var(--font-mono);
            color: var(--primary);
            line-height: 1.1;
        }
        
        .bill-summary-item .value.discount { color: #D97706; }
        .bill-summary-item .value.premium { color: #7C3AED; }
        .bill-summary-item .value.total { color: #059669; }
        
        .table-scroll { overflow-x: auto; border-radius: 8px; }
        
        .data-table { width: 100%; border-collapse: collapse; font-size: 0.75rem; }
        
        .data-table thead th {
            text-align: left;
            padding: 8px 10px;
            font-weight: 700;
            font-size: 0.6rem;
            text-transform: uppercase;
            color: #ffffff;
            background: var(--primary);
            border-bottom: 3px solid var(--primary-dark);
            white-space: nowrap;
        }
        
        .data-table thead th i { margin-right: 5px; opacity: 0.7; }
        
        .data-table tbody td {
            padding: 8px 10px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-primary);
            vertical-align: middle;
            font-size: 0.72rem;
        }
        
        .data-table tbody tr:hover td { background: var(--primary-bg); }
        .data-table tbody tr:last-child td { border-bottom: none; }
        .data-table tbody tr:nth-child(even) td { background: var(--gray-50); }
        
        [data-theme="dark"] .data-table tbody tr:nth-child(even) td { background: #1A1A2E; }
        [data-theme="dark"] .data-table tbody tr:hover td { background: #1E3A5F; }
        
        .date-cell { font-size: 0.68rem; color: var(--text-secondary); white-space: nowrap; }
        .date-cell .date-main { font-weight: 600; color: var(--text-primary); font-family: var(--font-mono); }
        .date-cell .date-time { font-size: 0.6rem; color: var(--text-secondary); display: block; font-family: var(--font-mono); }
        .date-cell .date-ago { font-size: 0.55rem; color: var(--primary); display: block; font-weight: 600; }
        
        .badge-status {
            display: inline-block;
            padding: 2px 10px;
            border-radius: 20px;
            font-size: 0.6rem;
            font-weight: 600;
            white-space: nowrap;
        }
        
        .badge-warning { background: var(--warning-bg); color: var(--warning); border: 1px solid var(--warning); }
        .badge-info { background: var(--info-bg); color: var(--info); border: 1px solid var(--info); }
        .badge-success { background: var(--success-bg); color: var(--success); border: 1px solid var(--success); }
        .badge-danger { background: var(--danger-bg); color: var(--danger); border: 1px solid var(--danger); }
        
        [data-theme="dark"] .badge-warning { background: #3A2A1A; color: #F59E0B; border-color: #D97706; }
        [data-theme="dark"] .badge-info { background: #1E3A5F; color: #6EA8FE; border-color: #3B82F6; }
        [data-theme="dark"] .badge-success { background: #1A3A2A; color: #34D399; border-color: #059669; }
        [data-theme="dark"] .badge-danger { background: #3A1A1A; color: #F87171; border-color: #DC2626; }
        
        .qty-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            background: var(--primary-bg);
            color: var(--primary);
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.68rem;
            font-weight: 700;
            border: 1px solid var(--primary);
            font-family: var(--font-mono);
        }
        
        [data-theme="dark"] .qty-badge { background: #1E3A5F; color: #93C5FD; border-color: #3B82F6; }
        
        .rx-number {
            font-family: var(--font-mono);
            font-size: 0.65rem;
            background: var(--primary-bg);
            color: var(--primary);
            padding: 2px 8px;
            border-radius: 4px;
            font-weight: 600;
        }
        
        [data-theme="dark"] .rx-number { background: #1E3A5F; color: #93C5FD; }
        
        .row-number {
            font-family: var(--font-mono);
            text-align: center;
            font-weight: 700;
            color: var(--text-secondary);
            font-size: 0.7rem;
        }
        
        mark.search-highlight {
            background: #FEF08A;
            color: #713F12;
            padding: 0 2px;
            border-radius: 3px;
            font-weight: 700;
        }
        
        [data-theme="dark"] mark.search-highlight { background: #FCD34D; color: #422006; }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-secondary);
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            border: 2px dashed var(--border-color);
        }
        
        .empty-state i {
            font-size: 3.5rem;
            color: var(--success);
            display: block;
            margin-bottom: 12px;
        }
        
        .empty-state p { font-size: 1rem; font-weight: 600; color: var(--text-primary); }
        .empty-state .sub { font-size: 0.85rem; color: var(--text-secondary); margin-top: 4px; font-weight: 400; }
        
        .footer {
            padding: 14px 0;
            border-top: 1px solid var(--border-color);
            margin-top: 24px;
            text-align: center;
            font-size: 0.7rem;
            color: var(--text-secondary);
        }
        
        .footer .footer-brand { color: var(--primary); font-weight: 600; }
        
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .animate-fade-in-up { animation: fadeInUp 0.5s ease forwards; opacity: 0; }
        
        @media (max-width: 1024px) { 
            .main-content { margin-left: 0; padding: 16px; } 
        }
        
        @media (max-width: 768px) {
            .page-header { padding: 14px 18px; }
            .page-header .page-title { font-size: 1.2rem; }
            .filter-tabs { flex-direction: column; align-items: stretch; }
            .filter-tab { justify-content: center; }
            .date-filters { margin-left: 0; width: 100%; }
            .search-toolbar { flex-direction: column; align-items: stretch; }
            .search-toolbar .search-wrapper { min-width: 100%; }
            .patient-header { flex-direction: column; align-items: stretch; }
            .visit-section-header { flex-direction: column; align-items: stretch; }
            .btn-view-visit { width: 100%; }
            .bill-summary { grid-template-columns: repeat(2, 1fr); }
        }
    </style>
</head>
<body>

<main class="main-content">

    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-history"></i>
                Prescription History
                <span class="role-badge-display">PHARMACY</span>
            </h1>
            <p class="page-subtitle">
                <?php if ($filter_status === 'all'): ?>
                    All prescriptions
                <?php else: ?>
                    <?= getStatusLabel($filter_status) ?> prescriptions
                <?php endif; ?>
                <span class="branch-tag">
                    <i class="fas fa-store-alt"></i> <?= htmlspecialchars($user_branch_name) ?>
                </span>
                <span class="branch-tag">
                    <i class="fas fa-users"></i> <?= count($grouped_prescriptions) ?> Patients
                </span>
                <span class="branch-tag">
                    <i class="fas fa-calendar-alt"></i> <?= date('d M Y') ?>
                </span>
            </p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;position:relative;z-index:1;">
            <a href="pending_prescriptions.php" class="btn-outline-light">
                <i class="fas fa-clock"></i> Pending
            </a>
            <button onclick="window.location.reload()" class="btn-outline-light">
                <i class="fas fa-sync-alt"></i> Refresh
            </button>
        </div>
    </div>

    <div class="filter-tabs animate-fade-in-up">
        <a href="?status=all<?= !empty($date_from) ? '&date_from=' . urlencode($date_from) : '' ?><?= !empty($date_to) ? '&date_to=' . urlencode($date_to) : '' ?>" 
           class="filter-tab all <?= $filter_status === 'all' ? 'active' : '' ?>">
            <span class="tab-icon">📋</span> All
            <span class="tab-count"><?= $total_all ?></span>
        </a>
        
        <a href="?status=pending<?= !empty($date_from) ? '&date_from=' . urlencode($date_from) : '' ?><?= !empty($date_to) ? '&date_to=' . urlencode($date_to) : '' ?>" 
           class="filter-tab pending <?= $filter_status === 'pending' ? 'active' : '' ?>">
            <span class="tab-icon">⏳</span> Pending
            <span class="tab-count"><?= $pending_count ?></span>
        </a>
        
        <a href="?status=confirmed<?= !empty($date_from) ? '&date_from=' . urlencode($date_from) : '' ?><?= !empty($date_to) ? '&date_to=' . urlencode($date_to) : '' ?>" 
           class="filter-tab confirmed <?= $filter_status === 'confirmed' ? 'active' : '' ?>">
            <span class="tab-icon">✅</span> Confirmed
            <span class="tab-count"><?= $confirmed_count ?></span>
        </a>
        
        <a href="?status=dispensed<?= !empty($date_from) ? '&date_from=' . urlencode($date_from) : '' ?><?= !empty($date_to) ? '&date_to=' . urlencode($date_to) : '' ?>" 
           class="filter-tab dispensed <?= $filter_status === 'dispensed' ? 'active' : '' ?>">
            <span class="tab-icon">💊</span> Dispensed
            <span class="tab-count"><?= $dispensed_count ?></span>
        </a>
        
        <a href="?status=cancelled<?= !empty($date_from) ? '&date_from=' . urlencode($date_from) : '' ?><?= !empty($date_to) ? '&date_to=' . urlencode($date_to) : '' ?>" 
           class="filter-tab cancelled <?= $filter_status === 'cancelled' ? 'active' : '' ?>">
            <span class="tab-icon">❌</span> Cancelled
            <span class="tab-count"><?= $cancelled_count ?></span>
        </a>
        
        <form method="GET" class="date-filters">
            <input type="hidden" name="status" value="<?= htmlspecialchars($filter_status) ?>">
            <input type="date" name="date_from" class="date-input" value="<?= htmlspecialchars($date_from) ?>" title="From Date">
            <input type="date" name="date_to" class="date-input" value="<?= htmlspecialchars($date_to) ?>" title="To Date">
            <button type="submit" class="btn-date-filter">
                <i class="fas fa-filter"></i> Filter
            </button>
            <?php if (!empty($date_from) || !empty($date_to) || $filter_status !== 'all'): ?>
                <a href="prescription_history.php" class="btn-clear-filter">
                    <i class="fas fa-times"></i> Clear
                </a>
            <?php endif; ?>
        </form>
    </div>

    <div class="search-toolbar animate-fade-in-up">
        <div class="toolbar-title">
            <i class="fas fa-search"></i> Search Medicines
            <span style="background:rgba(255,255,255,0.2);padding:2px 10px;border-radius:12px;font-size:0.6rem;font-weight:600;" id="headerCount">
                <?= count($grouped_prescriptions) ?> Patients
            </span>
        </div>
        <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
            <div class="search-wrapper">
                <i class="fas fa-search search-icon"></i>
                <input type="text" id="tableSearch" placeholder="Search medicine name..." autocomplete="off" value="<?= htmlspecialchars($search) ?>">
                <button type="button" class="search-clear" id="searchClear" title="Clear search">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <span class="search-results-count" id="searchResultsCount"></span>
        </div>
    </div>

    <div class="search-info-box" id="searchInfoBox">
        <div class="info-icon"><i class="fas fa-pills"></i></div>
        <div class="info-text">
            <div class="info-label">Total Quantity for Search</div>
            <div style="font-size:0.72rem;color:var(--text-secondary);">"<strong id="searchTermDisplay"></strong>"</div>
        </div>
        <div class="info-divider"></div>
        <div class="info-text" style="text-align:center;min-width:100px;">
            <div class="info-label">Total Quantity</div>
            <div class="info-value" id="totalQtyDisplay">0 <small>units</small></div>
        </div>
        <div class="info-divider"></div>
        <div class="info-text" style="text-align:center;min-width:90px;">
            <div class="info-label">Patients</div>
            <div class="info-value" id="totalPatientsDisplay">0 <small>patients</small></div>
        </div>
        <div class="info-divider"></div>
        <div class="info-text" style="text-align:center;min-width:90px;">
            <div class="info-label">Visits</div>
            <div class="info-value" id="totalVisitsDisplay">0 <small>visits</small></div>
        </div>
    </div>

    <div id="patientsContainer">
        <?php if (count($grouped_prescriptions) > 0): ?>
            <?php foreach ($grouped_prescriptions as $patient): 
                $patient_id = $patient['patient_id'];
                
                $overall_status = 'pending';
                $all_statuses = [];
                foreach ($patient['visits'] as $visit) {
                    $all_statuses[] = $visit['overall_status'];
                }
                if (in_array('pending', $all_statuses)) $overall_status = 'pending';
                elseif (in_array('confirmed', $all_statuses)) $overall_status = 'confirmed';
                elseif (in_array('dispensed', $all_statuses)) $overall_status = 'dispensed';
                elseif (in_array('cancelled', $all_statuses)) $overall_status = 'cancelled';
                
                $status_class = getStatusBadgeClass($overall_status);
                $status_label = getStatusLabel($overall_status);
                $age = calculateAge($patient['date_of_birth'] ?? '');
                $total_qty = $patient['total_qty'] ?? 0;
                $medication_count = $patient['medication_count'] ?? 0;
                $visit_count = count($patient['visits']);
                $prescription_count = $patient['prescription_count'] ?? 0;
                $bill_total = $patient['total_amount'] ?? 0;
                $last_date = $patient['last_prescription_date'] ?? null;
                $doctor_names = implode(', ', array_slice($patient['doctor_names_array'] ?? [], 0, 2));
            ?>
                <div class="patient-card animate-fade-in-up" data-patient-id="<?= $patient_id ?>">
                    
                    <div class="patient-header" onclick="togglePatient(<?= $patient_id ?>)">
                        <div class="patient-info">
                            <div class="patient-avatar" style="background: <?= '#' . substr(md5($patient['patient_name']), 0, 6) ?>;">
                                <?= strtoupper(substr($patient['patient_name'], 0, 1)) ?>
                            </div>
                            <div>
                                <div class="patient-name">
                                    <?= htmlspecialchars($patient['patient_name']) ?>
                                    <span style="background:rgba(255,255,255,0.25);padding:2px 10px;border-radius:12px;font-size:0.6rem;font-weight:600;">
                                        <i class="fas fa-pills"></i> <?= $medication_count ?> Item(s)
                                    </span>
                                    <span style="background:rgba(255,255,255,0.25);padding:2px 10px;border-radius:12px;font-size:0.6rem;font-weight:600;">
                                        <i class="fas fa-calendar-check"></i> <?= $visit_count ?> Visit(s)
                                    </span>
                                    <span class="badge-status <?= $status_class ?>" style="font-size:0.5rem;padding:2px 8px;">
                                        <?= $status_label ?>
                                    </span>
                                </div>
                                <div class="patient-meta">
                                    <span><i class="fas fa-id-card"></i> <?= htmlspecialchars($patient['patient_number']) ?></span>
                                    <?php if (!empty($patient['patient_phone']) && $patient['patient_phone'] !== 'N/A'): ?>
                                        <span><i class="fas fa-phone"></i> <?= htmlspecialchars($patient['patient_phone']) ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($patient['patient_gender'])): ?>
                                        <span><i class="fas fa-<?= $patient['patient_gender'] === 'Female' ? 'venus' : 'mars' ?>"></i> <?= htmlspecialchars($patient['patient_gender']) ?> • <?= $age ?> yrs</span>
                                    <?php endif; ?>
                                    <span><i class="fas fa-prescription"></i> <?= $prescription_count ?> Rx</span>
                                </div>
                            </div>
                        </div>
                        <div class="patient-stats">
                            <?php if (!empty($doctor_names)): ?>
                                <span class="stat-pill">
                                    <i class="fas fa-user-md"></i> Dr. <?= htmlspecialchars($doctor_names) ?>
                                </span>
                            <?php endif; ?>
                            <span class="stat-pill" style="background:rgba(255,255,255,0.3);">
                                <i class="fas fa-pills"></i> Qty: <span class="qty-value"><?= $total_qty ?></span>
                            </span>
                            <span class="stat-pill" style="background:rgba(255,255,255,0.3);">
                                <i class="fas fa-money-bill-wave"></i> <span class="money-value"><?= $currency ?> <?= number_format($bill_total, 0) ?></span>
                            </span>
                            <?php if (!empty($last_date)): ?>
                                <span class="stat-pill date-pill">
                                    <i class="fas fa-calendar-check"></i> <?= formatDate($last_date, false) ?>
                                </span>
                            <?php endif; ?>
                            <i class="fas fa-chevron-down chevron" id="chevron-<?= $patient_id ?>"></i>
                        </div>
                    </div>
                    
                    <div class="patient-body" id="body-<?= $patient_id ?>">
                        
                        <?php foreach ($patient['visits'] as $visit): 
                            $visit_id = $visit['visit_id'];
                            $visit_number = $visit['visit_number'];
                            $visit_date = $visit['visit_date'];
                            $visit_doctor = !empty($visit['doctor_names']) ? implode(', ', $visit['doctor_names']) : 'N/A';
                            $visit_status = $visit['overall_status'];
                            $visit_status_class = getStatusBadgeClass($visit_status);
                            $visit_status_label = getStatusLabel($visit_status);
                            $visit_qty = $visit['total_qty'];
                            $visit_meds = $visit['medication_count'];
                            $visit_amount = $visit['total_amount'];
                            $visit_items = $visit['items'];
                            
                            // ✅ V8: Bill info - PHARMACY ONLY
                            $visit_bill_number = $visit['bill_number'] ?? null;
                            $visit_bill_pharm_discount = $visit['bill_pharmacy_discount'] ?? 0;
                            $visit_bill_pharm_premium = $visit['bill_pharmacy_premium'] ?? 0;
                            
                            // ✅ Net Total = medication_total + premium - discount
                            $visit_net_total = $visit_amount + $visit_bill_pharm_premium - $visit_bill_pharm_discount;
                            
                            $view_url = getViewUrl($visit_status, $patient_id, $visit_id);
                            $view_icon = getViewIcon($visit_status);
                            $view_title = getViewTitle($visit_status);
                            $view_style = getViewButtonStyle($visit_status);
                            $is_cancelled = ($visit_status === 'cancelled');
                        ?>
                            <div class="visit-section">
                                <div class="visit-section-header">
                                    <div class="visit-info-left">
                                        <div class="visit-icon-badge">
                                            <i class="fas fa-calendar-check"></i>
                                        </div>
                                        <span class="visit-number-display">
                                            <?= htmlspecialchars($visit_number) ?>
                                        </span>
                                        <span class="visit-date-display">
                                            <i class="fas fa-calendar-day"></i>
                                            <?= !empty($visit_date) ? date('d M Y', strtotime($visit_date)) : 'N/A' ?>
                                        </span>
                                        <span class="visit-doctor-display">
                                            <i class="fas fa-user-md"></i>
                                            Dr. <?= htmlspecialchars($visit_doctor) ?>
                                        </span>
                                        <span class="badge-status <?= $visit_status_class ?>" style="font-size:0.55rem;padding:2px 8px;">
                                            <?= $visit_status_label ?>
                                        </span>
                                    </div>
                                    <div class="visit-stats-right">
                                        <span class="visit-mini-stat">
                                            <i class="fas fa-pills"></i>
                                            Meds: <span class="stat-value"><?= $visit_meds ?></span>
                                        </span>
                                        <span class="visit-mini-stat">
                                            <i class="fas fa-sort-numeric-up"></i>
                                            Qty: <span class="stat-value"><?= $visit_qty ?></span>
                                        </span>
                                        <span class="visit-mini-stat">
                                            <i class="fas fa-money-bill-wave"></i>
                                            <span class="stat-value"><?= $currency ?> <?= number_format($visit_amount, 0) ?></span>
                                        </span>
                                        
                                        <a href="<?= $is_cancelled ? '#' : $view_url ?>" 
                                           class="btn-view-visit" 
                                           title="<?= $view_title ?>"
                                           style="<?= $view_style ?>"
                                           <?= $is_cancelled ? 'onclick="alert(\'This visit is cancelled. No view available.\'); return false;"' : '' ?>>
                                            <i class="fas <?= $view_icon ?>"></i> VIEW
                                        </a>
                                    </div>
                                </div>
                                
                                <div class="visit-section-body">
                                    
                                    <!-- ✅ BILL SUMMARY - PHARMACY ONLY -->
                                    <div class="bill-summary">
                                        <div class="bill-summary-item">
                                            <span class="label">Meds Total</span>
                                            <span class="value"><?= $currency ?> <?= number_format($visit_amount, 0) ?></span>
                                        </div>
                                        <div class="bill-summary-item">
                                            <span class="label">Pharm Discount</span>
                                            <span class="value discount">
                                                <?= $visit_bill_pharm_discount > 0 ? '-' . $currency . ' ' . number_format($visit_bill_pharm_discount, 0) : '—' ?>
                                            </span>
                                        </div>
                                        <div class="bill-summary-item">
                                            <span class="label">Pharm Premium</span>
                                            <span class="value premium">
                                                <?= $visit_bill_pharm_premium > 0 ? '+' . $currency . ' ' . number_format($visit_bill_pharm_premium, 0) : '—' ?>
                                            </span>
                                        </div>
                                        <div class="bill-summary-item">
                                            <span class="label">Net Total</span>
                                            <span class="value total"><?= $currency ?> <?= number_format($visit_net_total, 0) ?></span>
                                        </div>
                                        <?php if ($visit_bill_number): ?>
                                        <div class="bill-summary-item">
                                            <span class="label">Bill #</span>
                                            <span class="value" style="font-size:0.6rem;color:var(--text-secondary);"><?= htmlspecialchars($visit_bill_number) ?></span>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="table-scroll">
                                        <table class="data-table">
                                            <thead>
                                                <tr>
                                                    <th style="width:40px;text-align:center;">#</th>
                                                    <th><i class="fas fa-pills"></i> Medication</th>
                                                    <th style="text-align:center;"><i class="fas fa-sort-numeric-up"></i> Qty</th>
                                                    <th style="text-align:right;"><i class="fas fa-coins"></i> Unit Price</th>
                                                    <th style="text-align:right;"><i class="fas fa-money-bill"></i> Amount</th>
                                                    <th><i class="fas fa-prescription"></i> Dosage</th>
                                                    <th><i class="fas fa-prescription"></i> Rx #</th>
                                                    <th><i class="fas fa-calendar-alt"></i> Date</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php 
                                                $i = 1; 
                                                $visit_med_total_calc = 0;
                                                foreach ($visit_items as $item): 
                                                    $med_name = $item['medication_name'] ?? 'N/A';
                                                    $med_qty = $item['quantity'] ?? 0;
                                                    $pres_num = $item['prescription_number'] ?? 'N/A';
                                                    $item_date = $item['prescription_date'] ?? ($item['created_at'] ?? null);
                                                    
                                                    $item_unit_price = (float)($item['bill_unit_price'] ?? $item['unit_price'] ?? 0);
                                                    $item_total_price = $item_unit_price * $med_qty;
                                                    
                                                    $visit_med_total_calc += $item_total_price;
                                                ?>
                                                    <tr class="med-row"
                                                        data-search="<?= htmlspecialchars(strtolower($med_name . ' ' . $patient['patient_name'] . ' ' . $patient['patient_number'] . ' ' . $pres_num . ' ' . $patient['patient_phone'] . ' ' . $visit_number . ' ' . $visit_doctor . ' ' . ($item_date ? date('d M Y', strtotime($item_date)) : ''))) ?>"
                                                        data-med-name="<?= htmlspecialchars(strtolower($med_name)) ?>"
                                                        data-qty="<?= $med_qty ?>"
                                                        data-visit-id="<?= $visit_id ?>"
                                                        data-patient-id="<?= $patient_id ?>">
                                                        <td class="row-number"><?= $i++ ?></td>
                                                        <td>
                                                            <div style="font-weight:600;font-size:0.78rem;" data-searchable>
                                                                <?= htmlspecialchars($med_name) ?>
                                                            </div>
                                                        </td>
                                                        <td style="text-align:center;">
                                                            <span class="qty-badge" data-searchable>
                                                                <?= number_format($med_qty) ?>
                                                            </span>
                                                        </td>
                                                        <td style="text-align:right;font-size:0.72rem;color:var(--text-secondary);font-family:var(--font-mono);" data-searchable>
                                                            <?= $currency ?> <?= number_format($item_unit_price, 0) ?>
                                                        </td>
                                                        <td style="text-align:right;font-weight:700;font-family:var(--font-mono);color:var(--primary);font-size:0.75rem;" data-searchable>
                                                            <?= $currency ?> <?= number_format($item_total_price, 0) ?>
                                                        </td>
                                                        <td style="font-size:0.7rem;" data-searchable><?= htmlspecialchars($item['dosage'] ?? '—') ?></td>
                                                        <td>
                                                            <span class="rx-number" data-searchable><?= htmlspecialchars($pres_num) ?></span>
                                                        </td>
                                                        <td class="date-cell" data-searchable>
                                                            <?php if (!empty($item_date)): ?>
                                                                <span class="date-main"><?= date('d M Y', strtotime($item_date)) ?></span>
                                                                <span class="date-time"><?= date('H:i', strtotime($item_date)) ?></span>
                                                                <span class="date-ago"><?= timeAgo($item_date) ?></span>
                                                            <?php else: ?>
                                                                <span style="color:var(--text-secondary);">N/A</span>
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                                
                                                <!-- ✅ MEDICATION TOTAL ROW -->
                                                <tr style="background:linear-gradient(135deg, #E8F0FE, #D6E4FF);font-weight:700;border-top:2px solid var(--primary);">
                                                    <td colspan="4" style="text-align:right;padding:10px 12px;font-size:0.75rem;color:var(--primary);">
                                                        <i class="fas fa-pills"></i> MEDICATION TOTAL:
                                                    </td>
                                                    <td style="text-align:right;padding:10px 12px;font-size:0.88rem;color:var(--primary);font-family:var(--font-mono);font-weight:800;">
                                                        <?= $currency ?> <?= number_format($visit_med_total_calc, 0) ?>
                                                    </td>
                                                    <td colspan="3"></td>
                                                </tr>
                                                
                                                <!-- ✅ NET TOTAL ROW -->
                                                <tr style="background:linear-gradient(135deg, #F0FDF4, #DCFCE7);font-weight:700;border-top:2px solid var(--success);">
                                                    <td colspan="4" style="text-align:right;padding:10px 12px;font-size:0.72rem;">
                                                        <span style="color:#D97706;">
                                                            <i class="fas fa-minus-circle"></i> Pharm Discount: -<?= $currency ?> <?= number_format($visit_bill_pharm_discount, 0) ?>
                                                        </span>
                                                        <span style="color:var(--text-secondary);margin:0 6px;">•</span>
                                                        <span style="color:#7C3AED;">
                                                            <i class="fas fa-plus-circle"></i> Pharm Premium: +<?= $currency ?> <?= number_format($visit_bill_pharm_premium, 0) ?>
                                                        </span>
                                                        <span style="color:var(--text-secondary);margin:0 6px;">=</span>
                                                        <strong style="color:var(--success);font-size:0.8rem;">
                                                            <i class="fas fa-calculator"></i> NET TOTAL:
                                                        </strong>
                                                    </td>
                                                    <td style="text-align:right;padding:10px 12px;font-size:0.95rem;color:var(--success);font-family:var(--font-mono);font-weight:800;background:linear-gradient(135deg, #D1FAE5, #A7F3D0);">
                                                        <?= $currency ?> <?= number_format($visit_net_total, 0) ?>
                                                    </td>
                                                    <td colspan="3"></td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        
                    </div>
                </div>
            <?php endforeach; ?>
            
            <div id="noSearchResults" style="display:none;">
                <div class="empty-state">
                    <i class="fas fa-search" style="color: var(--primary);"></i>
                    <p>No patients match your search</p>
                    <p class="sub">Try a different keyword</p>
                </div>
            </div>
            
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-check-circle"></i>
                <p>No <?= $filter_status === 'all' ? '' : getStatusLabel($filter_status) . ' ' ?>prescriptions found</p>
                <p class="sub">
                    <?php if (!empty($search)): ?>
                        No results for "<strong><?= htmlspecialchars($search) ?></strong>"
                    <?php elseif (!empty($date_from) || !empty($date_to)): ?>
                        No prescriptions in this date range
                    <?php elseif ($filter_status === 'pending'): ?>
                        Hakuna pending prescriptions ✅
                    <?php elseif ($filter_status === 'confirmed'): ?>
                        Hakuna confirmed prescriptions
                    <?php elseif ($filter_status === 'dispensed'): ?>
                        Hakuna dispensed prescriptions
                    <?php elseif ($filter_status === 'cancelled'): ?>
                        Hakuna cancelled prescriptions
                    <?php else: ?>
                        No prescriptions have been created yet
                    <?php endif; ?>
                </p>
            </div>
        <?php endif; ?>
    </div>

    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Prescription History V8 (Medication + Pharmacy Only - FIXED)
            <span class="text-gray-300 mx-2">|</span>
            <span id="footerTimestamp">Last updated: <?= date('H:i:s') ?></span>
        </p>
    </footer>

</main>

<script>
    var htmlElement = document.documentElement;
    var savedDarkMode = localStorage.getItem('darkMode');
    if (savedDarkMode === 'true') htmlElement.setAttribute('data-theme', 'dark');
    else if (savedDarkMode === 'false') htmlElement.removeAttribute('data-theme');
    else {
        var cookieDark = document.cookie.match(/dark_mode=([^;]+)/);
        if (cookieDark && cookieDark[1] === 'true') htmlElement.setAttribute('data-theme', 'dark');
    }
    
    var sidebar = document.getElementById('sidebar');
    var sidebarToggle = document.getElementById('sidebarToggle');
    if (sidebarToggle && sidebar) sidebarToggle.addEventListener('click', function() { sidebar.classList.toggle('open'); });
    
    document.addEventListener('click', function(e) {
        if (window.innerWidth <= 1024) {
            if (sidebar && !sidebar.contains(e.target) && e.target !== sidebarToggle) sidebar.classList.remove('open');
        }
    });
    
    function togglePatient(patientId) {
        var body = document.getElementById('body-' + patientId);
        var chevron = document.getElementById('chevron-' + patientId);
        if (body) body.classList.toggle('open');
        if (chevron) chevron.classList.toggle('rotated');
    }
    
    document.addEventListener('DOMContentLoaded', function() {
        var firstBody = document.querySelector('.patient-body');
        var firstChevron = document.querySelector('.chevron');
        if (firstBody) {
            setTimeout(function() {
                firstBody.classList.add('open');
                if (firstChevron) firstChevron.classList.add('rotated');
            }, 300);
        }
    });
    
    var tableSearch = document.getElementById('tableSearch');
    var searchClear = document.getElementById('searchClear');
    var searchResultsCount = document.getElementById('searchResultsCount');
    var searchInfoBox = document.getElementById('searchInfoBox');
    var searchTermDisplay = document.getElementById('searchTermDisplay');
    var totalQtyDisplay = document.getElementById('totalQtyDisplay');
    var totalPatientsDisplay = document.getElementById('totalPatientsDisplay');
    var totalVisitsDisplay = document.getElementById('totalVisitsDisplay');
    var patientCards = document.querySelectorAll('.patient-card');
    var noSearchResults = document.getElementById('noSearchResults');
    var headerCount = document.getElementById('headerCount');
    
    if (headerCount) headerCount.textContent = patientCards.length + ' Patients';
    
    var originalHTMLMap = new WeakMap();
    
    function escapeHtml(text) { var div = document.createElement('div'); div.textContent = text; return div.innerHTML; }
    function escapeRegex(text) { return text.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'); }
    function numberFormat(num) { return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ','); }
    
    function highlightText(element, query) {
        if (!element) return;
        if (!originalHTMLMap.has(element)) originalHTMLMap.set(element, element.innerHTML);
        var originalHTML = originalHTMLMap.get(element);
        if (!query || query.trim() === '') { element.innerHTML = originalHTML; return; }
        var tempDiv = document.createElement('div');
        tempDiv.innerHTML = originalHTML;
        var textContent = tempDiv.textContent || tempDiv.innerText || '';
        if (!textContent.trim()) { element.innerHTML = originalHTML; return; }
        var regex = new RegExp('(' + escapeRegex(query) + ')', 'gi');
        if (!textContent.match(regex)) { element.innerHTML = originalHTML; return; }
        element.innerHTML = escapeHtml(textContent).replace(regex, '<mark class="search-highlight">$1</mark>');
    }
    
    function removeAllHighlights() {
        document.querySelectorAll('[data-searchable]').forEach(function(el) {
            if (originalHTMLMap.has(el)) el.innerHTML = originalHTMLMap.get(el);
        });
    }
    
    function calculateSearchTotals(query) {
        if (!query || query.trim() === '') { if (searchInfoBox) searchInfoBox.classList.remove('show'); return; }
        var lowerQuery = query.toLowerCase().trim();
        var allRows = document.querySelectorAll('.med-row');
        var totalQty = 0;
        var totalPatientsSet = new Set();
        var totalVisitsSet = new Set();
        allRows.forEach(function(row) {
            var medName = row.dataset.medName || '';
            var qty = parseInt(row.dataset.qty) || 0;
            var visitId = row.dataset.visitId || '';
            var patientId = row.dataset.patientId || '';
            if (medName.indexOf(lowerQuery) !== -1) {
                totalQty += qty;
                if (patientId) totalPatientsSet.add(patientId);
                if (visitId && patientId) totalVisitsSet.add(patientId + '-' + visitId);
            }
        });
        var totalPatients = totalPatientsSet.size;
        var totalVisits = totalVisitsSet.size;
        if (searchInfoBox && (totalPatients > 0 || totalQty > 0)) {
            searchTermDisplay.textContent = query;
            totalQtyDisplay.innerHTML = numberFormat(totalQty) + ' <small>units</small>';
            totalPatientsDisplay.innerHTML = numberFormat(totalPatients) + ' <small>patients</small>';
            totalVisitsDisplay.innerHTML = numberFormat(totalVisits) + ' <small>visits</small>';
            searchInfoBox.classList.add('show');
        } else if (searchInfoBox) searchInfoBox.classList.remove('show');
    }
    
    function performLiveSearch() {
        var query = tableSearch.value.toLowerCase().trim();
        if (query.length > 0) searchClear.classList.add('visible');
        else searchClear.classList.remove('visible');
        removeAllHighlights();
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
                card.querySelectorAll('.visit-section').forEach(function(vs) {
                    var vsRows = vs.querySelectorAll('.med-row');
                    var vsHasMatch = false;
                    vsRows.forEach(function(row) {
                        var searchData = row.getAttribute('data-search') || '';
                        if (query === '' || searchData.includes(query)) vsHasMatch = true;
                    });
                    if (vsHasMatch || query === '') {
                        vs.style.display = '';
                        var visibleRows = 0;
                        vsRows.forEach(function(row) {
                            var searchData = row.getAttribute('data-search') || '';
                            if (query === '' || searchData.includes(query)) {
                                row.style.display = '';
                                visibleRows++;
                                var rowNum = row.querySelector('.row-number');
                                if (rowNum) rowNum.textContent = visibleRows;
                                if (query !== '') {
                                    row.querySelectorAll('[data-searchable]').forEach(function(el) {
                                        highlightText(el, query);
                                    });
                                }
                            } else {
                                row.style.display = 'none';
                            }
                        });
                    } else {
                        vs.style.display = 'none';
                    }
                });
            } else {
                card.style.display = 'none';
            }
        });
        calculateSearchTotals(query);
        if (visiblePatients === 0 && query !== '') noSearchResults.style.display = '';
        else noSearchResults.style.display = 'none';
        if (headerCount) headerCount.textContent = visiblePatients + ' Patients';
        if (query !== '') {
            searchResultsCount.textContent = visiblePatients + ' patients found';
            searchResultsCount.style.display = 'inline-block';
        } else {
            searchResultsCount.style.display = 'none';
        }
    }
    
    if (tableSearch) {
        tableSearch.addEventListener('input', performLiveSearch);
        tableSearch.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') { tableSearch.value = ''; performLiveSearch(); tableSearch.blur(); }
        });
    }
    
    if (searchClear) {
        searchClear.addEventListener('click', function() {
            tableSearch.value = '';
            performLiveSearch();
            tableSearch.focus();
        });
    }
    
    if (tableSearch && tableSearch.value.trim() !== '') performLiveSearch();
    
    document.addEventListener('keydown', function(e) {
        if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
            e.preventDefault();
            if (tableSearch) { tableSearch.focus(); tableSearch.select(); }
        }
    });
    
    function updateDateTime() {
        var now = new Date();
        var timeStr = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true });
        var footerTimestamp = document.getElementById('footerTimestamp');
        if (footerTimestamp) footerTimestamp.textContent = 'Last updated: ' + timeStr;
    }
    updateDateTime();
    setInterval(updateDateTime, 1000);
    
    console.log('%c📋 Braick - Prescription History V8 (BILL INFO FIXED)', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c✅ Bill query inatumia visit_id + patient_id (SI prescription_id)', 'font-size:13px; color:#059669; font-weight:bold;');
    console.log('%c✅ Pharm Discount + Pharm Premium zinaonekana SAHIHI', 'font-size:13px; color:#7C3AED;');
</script>

</body>
</html>