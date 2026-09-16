<?php
// ================================================================
// FILE: frontend/pages/pharmacy/prescription_history.php
// PHARMACY - PRESCRIPTION HISTORY (GROUPED BY PATIENT)
// ✅ FILTERS FIRST (All, Pending, Confirmed, Dispensed, Cancelled)
// ✅ THEN SEARCH BAR - Inatafuta dawa na inaonyesha total qty
// ✅ THEN PATIENT TABLES - Kila patient ana card yake
// ✅ VIEW BUTTON - Small width, "VIEW" text only
// ✅ BLUE THEME
// ✅ TAREHE INAONYESHA - Prescription dates included
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

// ================================================================
// GET SYSTEM SETTINGS
// ================================================================
try {
    $settings = [];
    $stmt = $db->query("SELECT setting_key, setting_value FROM system_settings");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    $currency = $settings['currency'] ?? 'TSh';
} catch (Exception $e) {}

// ================================================================
// HELPER FUNCTIONS - DATE FORMATTING
// ================================================================
function formatDate($datetime, $showTime = true) {
    if (empty($datetime) || $datetime === '0000-00-00 00:00:00') {
        return 'N/A';
    }
    $timestamp = strtotime($datetime);
    if ($timestamp === false) return 'N/A';
    return $showTime ? date('d M Y, H:i', $timestamp) : date('d M Y', $timestamp);
}

function formatDateShort($datetime) {
    if (empty($datetime) || $datetime === '0000-00-00 00:00:00') {
        return 'N/A';
    }
    $timestamp = strtotime($datetime);
    if ($timestamp === false) return 'N/A';
    return date('d/m/Y', $timestamp);
}

function timeAgo($datetime) {
    if (empty($datetime) || $datetime === '0000-00-00 00:00:00') {
        return 'N/A';
    }
    $timestamp = strtotime($datetime);
    if ($timestamp === false) return 'N/A';
    
    $now = time();
    $diff = $now - $timestamp;
    
    if ($diff < 60) {
        return 'Just now';
    } elseif ($diff < 3600) {
        $mins = floor($diff / 60);
        return $mins . ' min' . ($mins > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 604800) {
        $days = floor($diff / 86400);
        return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
    } else {
        return date('d M Y', $timestamp);
    }
}

// ================================================================
// GET FILTER PARAMETERS
// ================================================================
$filter_status = isset($_GET['status']) ? $_GET['status'] : 'all';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

// ================================================================
// BUILD QUERY - GROUPED BY PATIENT
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

if (!empty($date_from)) {
    $conditions[] = "DATE(p.created_at) >= ?";
    $params[] = $date_from;
}

if (!empty($date_to)) {
    $conditions[] = "DATE(p.created_at) <= ?";
    $params[] = $date_to;
}

$where_clause = implode(" AND ", $conditions);

// ================================================================
// GET PRESCRIPTIONS - GROUPED BY PATIENT
// ================================================================
$count_sql = "
    SELECT COUNT(DISTINCT pat.id) as total 
    FROM prescriptions p
    LEFT JOIN patients pat ON p.patient_id = pat.id
    WHERE $where_clause
";
$stmt = $db->prepare($count_sql);
$stmt->execute($params);
$total_patients = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

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
        COUNT(p.id) as prescription_count,
        GROUP_CONCAT(DISTINCT p.id ORDER BY p.id DESC) as prescription_ids,
        GROUP_CONCAT(DISTINCT p.prescription_number ORDER BY p.id DESC SEPARATOR '|') as prescription_numbers,
        GROUP_CONCAT(DISTINCT p.status ORDER BY p.id DESC SEPARATOR '|') as prescription_statuses,
        GROUP_CONCAT(DISTINCT u.full_name ORDER BY p.id DESC SEPARATOR '|') as doctor_names,
        GROUP_CONCAT(DISTINCT p.created_at ORDER BY p.id DESC SEPARATOR '|') as created_dates,
        MAX(CASE WHEN p.status != 'cancelled' THEN p.status END) as overall_status,
        SUM(CASE 
            WHEN p.status != 'cancelled' 
            THEN COALESCE((SELECT SUM(pi.total_price) FROM prescription_items pi WHERE pi.prescription_id = p.id), 0)
            ELSE 0 
        END) as total_amount
    FROM prescriptions p
    LEFT JOIN patients pat ON p.patient_id = pat.id
    LEFT JOIN users u ON p.doctor_id = u.id
    WHERE $where_clause
    GROUP BY pat.id, pat.full_name, pat.patient_id, pat.phone, pat.gender, pat.date_of_birth
    ORDER BY last_prescription_date DESC
";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$grouped_prescriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// GET ALL ITEMS FOR EACH PATIENT
// ================================================================
$patient_ids = array_column($grouped_prescriptions, 'patient_id');
$patient_items_map = [];

if (!empty($patient_ids)) {
    $placeholders = implode(',', array_fill(0, count($patient_ids), '?'));
    $stmt = $db->prepare("
        SELECT 
            pi.*,
            p.prescription_number,
            p.status as prescription_status,
            p.created_at as prescription_date,
            p.dispensed_at as prescription_dispensed_at,
            p.updated_at as prescription_updated_at
        FROM prescription_items pi
        INNER JOIN prescriptions p ON pi.prescription_id = p.id
        WHERE pi.patient_id IN ($placeholders)
        AND p.branch_id = ?
        AND p.status IN ('pending', 'confirmed', 'dispensed', 'cancelled')
        ORDER BY p.created_at DESC, pi.id DESC
    ");
    $item_params = array_merge($patient_ids, [$user_branch_id]);
    $stmt->execute($item_params);
    $all_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($all_items as $item) {
        $patient_items_map[$item['patient_id']][] = $item;
    }
}

// Process each patient
foreach ($grouped_prescriptions as &$group) {
    $pid = $group['patient_id'];
    $group['items'] = $patient_items_map[$pid] ?? [];
    
    // Calculate totals
    $total_qty = 0;
    $medication_count = 0;
    $unique_meds = [];
    
    foreach ($group['items'] as $item) {
        $total_qty += $item['quantity'];
        $medication_count++;
        
        $med_name = $item['medication_name'];
        if (!isset($unique_meds[$med_name])) {
            $unique_meds[$med_name] = 0;
        }
        $unique_meds[$med_name] += $item['quantity'];
    }
    
    $group['total_qty'] = $total_qty;
    $group['medication_count'] = $medication_count;
    $group['unique_medications'] = $unique_meds;
    
    // Search data
    $search_parts = [
        $group['patient_name'] ?? '',
        $group['patient_number'] ?? '',
        $group['patient_phone'] ?? '',
        $group['patient_gender'] ?? ''
    ];
    
    foreach ($group['items'] as $item) {
        $search_parts[] = $item['medication_name'];
        $search_parts[] = $item['prescription_number'];
        if (!empty($item['prescription_date'])) {
            $search_parts[] = date('d M Y', strtotime($item['prescription_date']));
            $search_parts[] = date('d/m/Y', strtotime($item['prescription_date']));
        }
    }
    
    $group['search_data'] = strtolower(implode(' ', $search_parts));
    
    // Status
    $status = $group['overall_status'] ?? 'pending';
    $group['status_label'] = ucfirst($status);
    
    // Prescription numbers
    $group['prescription_numbers_array'] = !empty($group['prescription_numbers']) 
        ? explode('|', $group['prescription_numbers']) 
        : [];
    
    // Doctor names
    $group['doctor_names_array'] = !empty($group['doctor_names']) 
        ? array_unique(explode('|', $group['doctor_names'])) 
        : [];
    
    // Created dates array
    $group['created_dates_array'] = !empty($group['created_dates']) 
        ? explode('|', $group['created_dates']) 
        : [];
}
unset($group);

// ================================================================
// STATISTICS
// ================================================================
$stats_where = " WHERE 1=1";
$stats_params = [];

if ($user_branch_id) {
    $stats_where .= " AND p.branch_id = ?";
    $stats_params[] = $user_branch_id;
}

$stmt = $db->prepare("SELECT COUNT(DISTINCT patient_id) as total FROM prescriptions p $stats_where");
$stmt->execute($stats_params);
$total_all = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

$pending_where = $stats_where . " AND p.status = 'pending'";
$stmt = $db->prepare("SELECT COUNT(DISTINCT patient_id) as total FROM prescriptions p $pending_where");
$stmt->execute($stats_params);
$pending_count = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

$confirmed_where = $stats_where . " AND p.status = 'confirmed'";
$stmt = $db->prepare("SELECT COUNT(DISTINCT patient_id) as total FROM prescriptions p $confirmed_where");
$stmt->execute($stats_params);
$confirmed_count = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

$dispensed_where = $stats_where . " AND p.status = 'dispensed'";
$stmt = $db->prepare("SELECT COUNT(DISTINCT patient_id) as total FROM prescriptions p $dispensed_where");
$stmt->execute($stats_params);
$dispensed_count = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

$cancelled_where = $stats_where . " AND p.status = 'cancelled'";
$stmt = $db->prepare("SELECT COUNT(DISTINCT patient_id) as total FROM prescriptions p $cancelled_where");
$stmt->execute($stats_params);
$cancelled_count = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

function calculateAge($dob) {
    if (empty($dob)) return 'N/A';
    $birthDate = new DateTime($dob);
    $today = new DateTime('today');
    return $birthDate->diff($today)->y;
}

function getStatusBadgeClass($status) {
    $map = [
        'pending' => 'badge-warning',
        'confirmed' => 'badge-info',
        'dispensed' => 'badge-success',
        'cancelled' => 'badge-danger'
    ];
    return $map[$status] ?? 'badge-warning';
}

function getStatusLabel($status) {
    $map = [
        'pending' => '⏳ Pending',
        'confirmed' => '✅ Confirmed',
        'dispensed' => '💊 Dispensed',
        'cancelled' => '❌ Cancelled'
    ];
    return $map[$status] ?? ucfirst($status);
}

$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

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
    
    <style>
        :root {
            --primary: #0B5ED7;
            --primary-dark: #0A4CA8;
            --primary-darker: #083C8A;
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
            --gray-100: #F1F5F9;
            --gray-200: #E2E8F0;
            --gray-300: #CBD5E1;
            --gray-400: #94A3B8;
            --gray-500: #64748B;
            --gray-600: #475569;
            --gray-700: #334155;
            --gray-800: #1E293B;
            --gray-900: #0F172A;
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
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Inter', 'Segoe UI', -apple-system, sans-serif;
            background: var(--bg-body);
            color: var(--text-primary);
            transition: background 0.3s ease, color 0.3s ease;
        }
        
        ::-webkit-scrollbar { width: 5px; height: 5px; }
        ::-webkit-scrollbar-track { background: var(--bg-body); }
        ::-webkit-scrollbar-thumb { background: var(--primary); border-radius: 10px; }
        
        .main-content {
            margin-left: 270px;
            margin-top: 68px;
            padding: 28px 32px;
            min-height: calc(100vh - 68px);
        }
        
        /* PAGE HEADER */
        .page-header {
            background: linear-gradient(135deg, #0B5ED7, #0A4CA8, #083C8A);
            border-radius: 16px;
            padding: 24px 32px;
            margin-bottom: 20px;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            box-shadow: 0 4px 20px rgba(11, 94, 215, 0.3);
            position: relative;
            overflow: hidden;
        }
        
        .page-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -20%;
            width: 300px;
            height: 300px;
            background: rgba(255,255,255,0.05);
            border-radius: 50%;
            pointer-events: none;
        }
        
        .page-header .page-title {
            color: white;
            font-size: 1.8rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
        }
        
        .page-header .page-title i { font-size: 2rem; opacity: 0.9; }
        
        .page-header .page-subtitle {
            color: rgba(255,255,255,0.85);
            font-size: 0.9rem;
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
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 0.65rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            backdrop-filter: blur(4px);
        }
        
        .page-header .branch-tag {
            background: rgba(255,255,255,0.15);
            color: white;
            padding: 2px 12px;
            border-radius: 20px;
            font-size: 0.7rem;
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
            padding: 8px 16px;
            border-radius: 10px;
            font-weight: 500;
            font-size: 0.82rem;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            backdrop-filter: blur(4px);
            position: relative;
            z-index: 1;
        }
        
        .page-header .btn-outline-light:hover {
            background: rgba(255,255,255,0.25);
            transform: translateY(-2px);
        }
        
        .update-badge-light {
            background: rgba(255,255,255,0.12);
            color: rgba(255,255,255,0.8);
            padding: 3px 12px;
            border-radius: 20px;
            font-size: 0.6rem;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            backdrop-filter: blur(4px);
        }
        
        /* ================================================================ */
        /* FILTER TABS */
        /* ================================================================ */
        .filter-tabs {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            align-items: center;
            margin-bottom: 16px;
            padding: 12px 16px;
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow);
        }
        
        .filter-tab {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 18px;
            border-radius: 10px;
            font-size: 0.78rem;
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
            font-size: 0.65rem;
            font-weight: 800;
        }
        
        .filter-tab:hover .tab-count {
            background: var(--primary);
            color: white;
        }
        
        .filter-tab.active {
            border-color: transparent;
            color: white;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        
        .filter-tab.active .tab-count {
            background: rgba(255,255,255,0.3);
            color: white;
        }
        
        .filter-tab.all.active {
            background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
        }
        
        .filter-tab.pending.active {
            background: linear-gradient(135deg, #D97706, #B45309);
        }
        
        .filter-tab.confirmed.active {
            background: linear-gradient(135deg, #3B82F6, #0B5ED7);
        }
        
        .filter-tab.dispensed.active {
            background: linear-gradient(135deg, #059669, #047857);
        }
        
        .filter-tab.cancelled.active {
            background: linear-gradient(135deg, #DC2626, #B91C1C);
        }
        
        .filter-tab .tab-icon {
            font-size: 0.9rem;
        }
        
        /* DATE RANGE FILTERS */
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
            transition: all 0.3s ease;
            height: 34px;
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
            font-size: 0.72rem;
            cursor: pointer;
            transition: all 0.3s ease;
            height: 34px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .btn-date-filter:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3);
        }
        
        .btn-clear-filter {
            padding: 6px 12px;
            background: transparent;
            color: var(--danger);
            border: 2px solid var(--danger);
            border-radius: 8px;
            font-weight: 700;
            font-size: 0.72rem;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            height: 34px;
            transition: all 0.3s ease;
        }
        
        .btn-clear-filter:hover {
            background: var(--danger);
            color: white;
        }
        
        /* SEARCH TOOLBAR */
        .search-toolbar {
            background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
            border-radius: var(--radius-lg);
            padding: 12px 20px;
            margin-bottom: 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            box-shadow: 0 4px 16px rgba(11, 94, 215, 0.2);
        }
        
        .search-toolbar .toolbar-title {
            color: white;
            font-size: 0.9rem;
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
            transition: all 0.3s ease;
            min-width: 380px;
            height: 40px;
        }
        
        .search-toolbar .search-wrapper:focus-within {
            background: rgba(255,255,255,0.25);
            border-color: rgba(255,255,255,0.5);
            box-shadow: 0 0 0 3px rgba(255,255,255,0.1);
        }
        
        .search-toolbar .search-wrapper .search-icon {
            color: rgba(255,255,255,0.8);
            font-size: 0.9rem;
            margin-right: 8px;
        }
        
        .search-toolbar .search-wrapper input {
            flex: 1;
            background: transparent;
            border: none;
            outline: none;
            color: white;
            font-size: 0.85rem;
            padding: 0;
            font-weight: 500;
        }
        
        .search-toolbar .search-wrapper input::placeholder {
            color: rgba(255,255,255,0.6);
        }
        
        .search-toolbar .search-wrapper .search-clear {
            background: rgba(255,255,255,0.2);
            border: none;
            color: white;
            width: 22px;
            height: 22px;
            border-radius: 50%;
            cursor: pointer;
            display: none;
            align-items: center;
            justify-content: center;
            font-size: 0.7rem;
            transition: all 0.2s ease;
            margin-left: 8px;
        }
        
        .search-toolbar .search-wrapper .search-clear.visible { display: flex; }
        .search-toolbar .search-wrapper .search-clear:hover { background: rgba(255,255,255,0.35); }
        
        .search-results-count {
            color: rgba(255,255,255,0.9);
            font-size: 0.7rem;
            font-weight: 600;
            background: rgba(255,255,255,0.2);
            padding: 3px 12px;
            border-radius: 12px;
            margin-left: 8px;
            white-space: nowrap;
            display: none;
        }
        
        /* SEARCH INFO BOX */
        .search-info-box {
            display: none;
            margin-bottom: 16px;
            padding: 14px 20px;
            border-radius: 12px;
            background: linear-gradient(135deg, #E8F0FE, #D6E4FF);
            border: 2px solid var(--primary);
            align-items: center;
            gap: 16px;
            flex-wrap: wrap;
            animation: slideDown 0.4s ease;
        }
        
        [data-theme="dark"] .search-info-box {
            background: linear-gradient(135deg, #1E3A5F, #16294A);
        }
        
        .search-info-box.show { display: flex; }
        
        .search-info-box .info-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: var(--primary);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
            flex-shrink: 0;
            box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3);
        }
        
        .search-info-box .info-text {
            flex: 1;
            min-width: 180px;
        }
        
        .search-info-box .info-label {
            font-size: 0.65rem;
            color: var(--text-secondary);
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        .search-info-box .info-value {
            font-size: 1.6rem;
            font-weight: 800;
            color: var(--primary);
            line-height: 1.1;
        }
        
        .search-info-box .info-value small {
            font-size: 0.7rem;
            font-weight: 500;
            color: var(--text-secondary);
            margin-left: 6px;
        }
        
        .search-info-box .info-divider {
            width: 2px;
            height: 45px;
            background: var(--border-color);
        }
        
        .search-info-box .info-search-term {
            font-size: 0.9rem;
            color: var(--text-secondary);
            font-weight: 500;
        }
        
        .search-info-box .info-search-term strong {
            color: var(--primary);
            font-weight: 700;
        }
        
        /* PATIENT CARD */
        .patient-card {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            border: 2px solid var(--border-color);
            margin-bottom: 20px;
            overflow: hidden;
            box-shadow: var(--shadow);
            transition: all 0.3s ease;
        }
        
        .patient-card:hover {
            border-color: var(--primary);
            box-shadow: var(--shadow-md);
        }
        
        .patient-header {
            background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
            color: white;
            padding: 14px 22px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .patient-header:hover {
            background: linear-gradient(135deg, #0A4CA8, #083C8A);
        }
        
        .patient-header .patient-info {
            display: flex;
            align-items: center;
            gap: 14px;
            flex: 1;
            min-width: 250px;
        }
        
        .patient-header .patient-avatar {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: rgba(255,255,255,0.25);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1.3rem;
            color: white;
            flex-shrink: 0;
            border: 2px solid rgba(255,255,255,0.4);
            backdrop-filter: blur(4px);
        }
        
        .patient-header .patient-name {
            font-weight: 700;
            font-size: 1.05rem;
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .patient-header .patient-meta {
            display: flex;
            gap: 14px;
            font-size: 0.75rem;
            opacity: 0.9;
            flex-wrap: wrap;
            margin-top: 3px;
        }
        
        .patient-header .patient-meta span {
            display: flex;
            align-items: center;
            gap: 4px;
        }
        
        .patient-header .patient-stats {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .patient-header .patient-stats .stat-pill {
            background: rgba(255,255,255,0.2);
            padding: 5px 14px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            backdrop-filter: blur(4px);
            border: 1px solid rgba(255,255,255,0.15);
        }
        
        /* DATE PILL IN HEADER */
        .patient-header .stat-pill.date-pill {
            background: rgba(255,255,255,0.3);
            border: 1px solid rgba(255,255,255,0.4);
            font-size: 0.65rem;
        }
        
        .patient-header .chevron {
            font-size: 0.9rem;
            transition: transform 0.3s ease;
        }
        
        .patient-header .chevron.rotated {
            transform: rotate(180deg);
        }
        
        .patient-body {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.4s ease, padding 0.3s ease;
            background: var(--bg-card);
        }
        
        .patient-body.open {
            max-height: 5000px;
            padding: 16px 22px 20px;
        }
        
        /* PATIENT ACTIONS */
        .patient-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            padding-bottom: 14px;
            border-bottom: 2px dashed var(--border-color);
            margin-bottom: 14px;
            flex-wrap: wrap;
        }
        
        .patient-actions .patient-actions-info {
            font-size: 0.8rem;
            color: var(--text-secondary);
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        /* VIEW BUTTON */
        .btn-view-patient {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            padding: 7px 18px;
            border-radius: 8px;
            font-weight: 700;
            font-size: 0.72rem;
            background: linear-gradient(135deg, #059669, #047857);
            color: white;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 3px 10px rgba(5, 150, 105, 0.3);
            text-decoration: none;
            width: auto;
            min-width: 80px;
        }
        
        .btn-view-patient:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 16px rgba(5, 150, 105, 0.5);
        }
        
        /* TABLE */
        .table-scroll { overflow-x: auto; border-radius: 10px; }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.82rem;
        }
        
        .data-table thead th {
            text-align: left;
            padding: 10px 14px;
            font-weight: 700;
            font-size: 0.65rem;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: #ffffff;
            background: var(--primary);
            border-bottom: 3px solid var(--primary-dark);
            white-space: nowrap;
        }
        
        .data-table thead th i { margin-right: 5px; opacity: 0.7; }
        
        .data-table tbody td {
            padding: 10px 14px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-primary);
            vertical-align: middle;
        }
        
        .data-table tbody tr:hover td { background: var(--primary-bg); }
        .data-table tbody tr:last-child td { border-bottom: none; }
        .data-table tbody tr:nth-child(even) td { background: var(--gray-50); }
        
        [data-theme="dark"] .data-table tbody tr:nth-child(even) td { background: #1A1A2E; }
        [data-theme="dark"] .data-table tbody tr:hover td { background: #1E3A5F; }
        
        /* DATE CELL STYLING */
        .date-cell {
            font-size: 0.7rem;
            white-space: nowrap;
        }
        
        .date-cell .date-main {
            font-weight: 600;
            color: var(--text-primary);
            display: block;
        }
        
        .date-cell .date-time {
            font-size: 0.65rem;
            color: var(--text-secondary);
            display: block;
            margin-top: 1px;
        }
        
        .date-cell .date-ago {
            font-size: 0.6rem;
            color: var(--primary);
            display: block;
            margin-top: 1px;
            font-weight: 600;
        }
        
        /* BADGES */
        .badge-status {
            display: inline-block;
            padding: 3px 14px;
            border-radius: 20px;
            font-size: 0.65rem;
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
        
        /* QTY BADGE */
        .qty-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            background: var(--primary-bg);
            color: var(--primary);
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 700;
            border: 1px solid var(--primary);
        }
        
        [data-theme="dark"] .qty-badge {
            background: #1E3A5F;
            color: #93C5FD;
            border-color: #3B82F6;
        }
        
        /* SEARCH HIGHLIGHT */
        mark.search-highlight {
            background: #FEF08A;
            color: #713F12;
            padding: 0 2px;
            border-radius: 3px;
            font-weight: 700;
            box-shadow: 0 0 0 1px rgba(245, 158, 11, 0.3);
            animation: highlight-pulse 0.6s ease;
        }
        
        [data-theme="dark"] mark.search-highlight {
            background: #FCD34D;
            color: #422006;
        }
        
        @keyframes highlight-pulse {
            0% { background-color: #FDE047; transform: scale(1.05); }
            50% { background-color: #FCD34D; transform: scale(1.1); }
            100% { background-color: #FEF08A; transform: scale(1); }
        }
        
        /* EMPTY STATE */
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
        .empty-state.no-results i { color: var(--primary); }
        
        /* TOAST */
        .toast-custom {
            position: fixed;
            bottom: 24px;
            right: 24px;
            padding: 14px 20px;
            border-radius: 12px;
            z-index: 9999;
            max-width: 400px;
            transform: translateY(100px);
            opacity: 0;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            align-items: center;
            gap: 12px;
            color: white;
            box-shadow: var(--shadow-lg);
            font-size: 0.85rem;
        }
        
        .toast-custom.show { transform: translateY(0); opacity: 1; }
        .toast-custom.success { background: linear-gradient(135deg, #059669, #047857); }
        .toast-custom.error { background: linear-gradient(135deg, #DC2626, #B91C1C); }
        .toast-custom.info { background: linear-gradient(135deg, #0B5ED7, #0A4CA8); }
        .toast-custom.warning { background: linear-gradient(135deg, #D97706, #B45309); }
        
        /* FOOTER */
        .footer {
            padding: 14px 0;
            border-top: 1px solid var(--border-color);
            margin-top: 24px;
            text-align: center;
            font-size: 0.7rem;
            color: var(--text-secondary);
        }
        
        .footer .footer-brand { color: var(--primary); font-weight: 600; }
        
        /* ANIMATIONS */
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .animate-fade-in-up { animation: fadeInUp 0.5s ease forwards; opacity: 0; }
        
        /* RESPONSIVE */
        @media (max-width: 1024px) {
            .main-content { margin-left: 0; padding: 16px; }
        }
        
        @media (max-width: 768px) {
            .page-header { padding: 16px 18px; }
            .page-header .page-title { font-size: 1.3rem; }
            .filter-tabs { flex-direction: column; align-items: stretch; }
            .filter-tab { justify-content: center; }
            .date-filters { margin-left: 0; width: 100%; }
            .search-toolbar { flex-direction: column; align-items: stretch; }
            .search-toolbar .search-wrapper { min-width: 100%; }
            .patient-header { flex-direction: column; align-items: stretch; }
            .patient-actions { flex-direction: column; align-items: stretch; }
            .btn-view-patient { width: 100%; justify-content: center; }
        }
        
        @media (max-width: 480px) {
            .main-content { padding: 10px; }
            .page-header .page-title { font-size: 1.1rem; }
            .data-table td { padding: 6px 8px; font-size: 0.7rem; }
            .filter-tab { font-size: 0.7rem; padding: 6px 12px; }
        }
    </style>
</head>
<body>

<main class="main-content">

    <!-- PAGE HEADER -->
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-history"></i>
                Prescription History
                <span class="role-badge-display">PHARMACY</span>
                <span class="update-badge-light">
                    <i class="fas fa-sync-alt fa-spin"></i> Live
                </span>
            </h1>
            <p class="page-subtitle">
                All prescriptions (Grouped by Patient)
                <span class="branch-tag">
                    <i class="fas fa-store-alt"></i> <?= htmlspecialchars($user_branch_name) ?>
                </span>
                <span class="branch-tag" style="background:rgba(255,255,255,0.15);">
                    <i class="fas fa-users"></i> <?= count($grouped_prescriptions) ?> Patients
                </span>
                <span class="branch-tag" style="background:rgba(255,255,255,0.15);">
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

    <!-- ================================================================ -->
    <!-- 1. FILTER TABS -->
    <!-- ================================================================ -->
    <div class="filter-tabs animate-fade-in-up">
        <a href="?status=all<?= !empty($date_from) ? '&date_from=' . urlencode($date_from) : '' ?><?= !empty($date_to) ? '&date_to=' . urlencode($date_to) : '' ?>" 
           class="filter-tab all <?= $filter_status === 'all' ? 'active' : '' ?>">
            <span class="tab-icon">📋</span>
            All
            <span class="tab-count"><?= $total_all ?></span>
        </a>
        
        <a href="?status=pending<?= !empty($date_from) ? '&date_from=' . urlencode($date_from) : '' ?><?= !empty($date_to) ? '&date_to=' . urlencode($date_to) : '' ?>" 
           class="filter-tab pending <?= $filter_status === 'pending' ? 'active' : '' ?>">
            <span class="tab-icon">⏳</span>
            Pending
            <span class="tab-count"><?= $pending_count ?></span>
        </a>
        
        <a href="?status=confirmed<?= !empty($date_from) ? '&date_from=' . urlencode($date_from) : '' ?><?= !empty($date_to) ? '&date_to=' . urlencode($date_to) : '' ?>" 
           class="filter-tab confirmed <?= $filter_status === 'confirmed' ? 'active' : '' ?>">
            <span class="tab-icon">✅</span>
            Confirmed
            <span class="tab-count"><?= $confirmed_count ?></span>
        </a>
        
        <a href="?status=dispensed<?= !empty($date_from) ? '&date_from=' . urlencode($date_from) : '' ?><?= !empty($date_to) ? '&date_to=' . urlencode($date_to) : '' ?>" 
           class="filter-tab dispensed <?= $filter_status === 'dispensed' ? 'active' : '' ?>">
            <span class="tab-icon">💊</span>
            Dispensed
            <span class="tab-count"><?= $dispensed_count ?></span>
        </a>
        
        <a href="?status=cancelled<?= !empty($date_from) ? '&date_from=' . urlencode($date_from) : '' ?><?= !empty($date_to) ? '&date_to=' . urlencode($date_to) : '' ?>" 
           class="filter-tab cancelled <?= $filter_status === 'cancelled' ? 'active' : '' ?>">
            <span class="tab-icon">❌</span>
            Cancelled
            <span class="tab-count"><?= $cancelled_count ?></span>
        </a>
        
        <!-- DATE RANGE FILTERS -->
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

    <!-- ================================================================ -->
    <!-- 2. SEARCH TOOLBAR -->
    <!-- ================================================================ -->
    <div class="search-toolbar animate-fade-in-up">
        <div class="toolbar-title">
            <i class="fas fa-search"></i>
            Search Medicines
            <span style="background:rgba(255,255,255,0.2);padding:2px 10px;border-radius:12px;font-size:0.65rem;font-weight:600;" id="headerCount">
                <?= count($grouped_prescriptions) ?> Patients
            </span>
        </div>
        <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
            <div class="search-wrapper">
                <i class="fas fa-search search-icon"></i>
                <input type="text" 
                       id="tableSearch" 
                       placeholder="Search medicine name to see total qty..." 
                       autocomplete="off"
                       value="<?= htmlspecialchars($search) ?>">
                <button type="button" class="search-clear" id="searchClear" title="Clear search">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <span class="search-results-count" id="searchResultsCount"></span>
        </div>
    </div>

    <!-- SEARCH INFO BOX -->
    <div class="search-info-box" id="searchInfoBox">
        <div class="info-icon">
            <i class="fas fa-pills"></i>
        </div>
        <div class="info-text">
            <div class="info-label">Total Quantity for Search</div>
            <div class="info-search-term">
                "<strong id="searchTermDisplay"></strong>"
            </div>
        </div>
        <div class="info-divider"></div>
        <div class="info-text" style="text-align:center;min-width:120px;">
            <div class="info-label">Total Quantity</div>
            <div class="info-value" id="totalQtyDisplay">0 <small>units</small></div>
        </div>
        <div class="info-divider"></div>
        <div class="info-text" style="text-align:center;min-width:100px;">
            <div class="info-label">Patients</div>
            <div class="info-value" id="totalPatientsDisplay">0 <small>patients</small></div>
        </div>
        <div class="info-divider"></div>
        <div class="info-text" style="text-align:center;min-width:100px;">
            <div class="info-label">Prescriptions</div>
            <div class="info-value" id="totalPrescriptionsDisplay">0 <small>items</small></div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- 3. PATIENTS LIST -->
    <!-- ================================================================ -->
    <div id="patientsContainer">
        <?php if (count($grouped_prescriptions) > 0): ?>
            <?php foreach ($grouped_prescriptions as $patient): 
                $patient_id = $patient['patient_id'];
                $status = $patient['overall_status'] ?? 'pending';
                $status_class = getStatusBadgeClass($status);
                $status_label = getStatusLabel($status);
                $age = calculateAge($patient['date_of_birth'] ?? '');
                $total_qty = $patient['total_qty'] ?? 0;
                $medication_count = $patient['medication_count'] ?? 0;
                $doctor_names = implode(', ', array_slice($patient['doctor_names_array'] ?? [], 0, 2));
                $prescription_count = $patient['prescription_count'] ?? 0;
                $bill_total = $patient['total_amount'] ?? 0;
                $items = $patient['items'] ?? [];
                $last_date = $patient['last_prescription_date'] ?? null;
                $first_date = $patient['first_prescription_date'] ?? null;
            ?>
                <div class="patient-card animate-fade-in-up" data-patient-id="<?= $patient_id ?>">
                    <!-- PATIENT HEADER -->
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
                                    <span class="badge-status <?= $status_class ?>" style="font-size:0.55rem;padding:2px 8px;">
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
                                    <?php if (!empty($last_date)): ?>
                                        <span><i class="fas fa-calendar-alt"></i> <?= formatDateShort($last_date) ?></span>
                                    <?php endif; ?>
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
                                <i class="fas fa-pills"></i> Qty: <?= $total_qty ?>
                            </span>
                            <span class="stat-pill" style="background:rgba(255,255,255,0.3);">
                                <i class="fas fa-money-bill-wave"></i> <?= $currency ?> <?= number_format($bill_total, 0) ?>
                            </span>
                            <?php if (!empty($last_date)): ?>
                                <span class="stat-pill date-pill">
                                    <i class="fas fa-calendar-check"></i> <?= formatDate($last_date, false) ?>
                                </span>
                            <?php endif; ?>
                            <i class="fas fa-chevron-down chevron" id="chevron-<?= $patient_id ?>"></i>
                        </div>
                    </div>
                    
                    <!-- PATIENT BODY -->
                    <div class="patient-body" id="body-<?= $patient_id ?>">
                        
                        <!-- PATIENT ACTIONS -->
                        <div class="patient-actions">
                            <div class="patient-actions-info">
                                <i class="fas fa-info-circle" style="color:var(--primary);"></i>
                                <strong><?= $medication_count ?></strong> medication(s) • Total Qty: <strong><?= $total_qty ?></strong>
                                • <strong><?= $prescription_count ?></strong> prescription(s)
                                <?php if (!empty($first_date) && !empty($last_date)): ?>
                                    • <i class="fas fa-calendar-alt"></i> From: <strong><?= formatDate($first_date, false) ?></strong> 
                                    To: <strong><?= formatDate($last_date, false) ?></strong>
                                <?php endif; ?>
                            </div>
                            <!-- VIEW BUTTON -->
                            <a href="view_patient_prescriptions.php?patient_id=<?= $patient_id ?>" class="btn-view-patient">
                                <i class="fas fa-eye"></i> VIEW
                            </a>
                        </div>
                        
                        <!-- PATIENT MEDICATIONS TABLE -->
                        <div class="table-scroll">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th style="width:50px;">#</th>
                                        <th><i class="fas fa-pills"></i> Medication</th>
                                        <th><i class="fas fa-sort-numeric-up"></i> Qty</th>
                                        <th><i class="fas fa-prescription"></i> Dosage</th>
                                        <th><i class="fas fa-clock"></i> Frequency</th>
                                        <th><i class="fas fa-route"></i> Route</th>
                                        <th><i class="fas fa-prescription"></i> Rx #</th>
                                        <th><i class="fas fa-calendar-alt"></i> Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $i = 1; foreach ($items as $item): 
                                        $med_name = $item['medication_name'] ?? 'N/A';
                                        $med_qty = $item['quantity'] ?? 0;
                                        $pres_num = $item['prescription_number'] ?? 'N/A';
                                        $item_date = $item['prescription_date'] ?? ($item['created_at'] ?? null);
                                    ?>
                                        <tr class="med-row"
                                            data-search="<?= htmlspecialchars(strtolower($med_name . ' ' . $patient['patient_name'] . ' ' . $patient['patient_number'] . ' ' . $pres_num . ' ' . $patient['patient_phone'] . ' ' . ($item_date ? date('d M Y', strtotime($item_date)) : '') . ' ' . ($item_date ? date('d/m/Y', strtotime($item_date)) : ''))) ?>"
                                            data-med-name="<?= htmlspecialchars(strtolower($med_name)) ?>"
                                            data-qty="<?= $med_qty ?>"
                                            data-date="<?= $item_date ?>">
                                            <td class="row-number"><?= $i++ ?></td>
                                            <td>
                                                <div style="font-weight:600;font-size:0.85rem;" data-searchable>
                                                    <?= htmlspecialchars($med_name) ?>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="qty-badge" data-searchable>
                                                    <i class="fas fa-pills"></i> <?= number_format($med_qty) ?>
                                                </span>
                                            </td>
                                            <td style="font-size:0.75rem;" data-searchable><?= htmlspecialchars($item['dosage'] ?? '—') ?></td>
                                            <td style="font-size:0.75rem;" data-searchable><?= htmlspecialchars($item['frequency'] ?? '—') ?></td>
                                            <td style="font-size:0.75rem;" data-searchable><?= htmlspecialchars($item['route'] ?? '—') ?></td>
                                            <td>
                                                <span style="font-family:'Courier New',monospace;font-size:0.7rem;background:var(--primary-bg);color:var(--primary);padding:2px 8px;border-radius:4px;font-weight:600;" data-searchable>
                                                    <?= htmlspecialchars($pres_num) ?>
                                                </span>
                                            </td>
                                            <!-- DATE COLUMN -->
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
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
            
            <!-- NO SEARCH RESULTS -->
            <div id="noSearchResults" style="display:none;">
                <div class="empty-state no-results">
                    <i class="fas fa-search"></i>
                    <p>No patients match your search</p>
                    <p class="sub">Try a different keyword</p>
                </div>
            </div>
            
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-check-circle"></i>
                <p>No prescriptions found</p>
                <p class="sub">
                    <?php if (!empty($search)): ?>
                        No results for "<strong><?= htmlspecialchars($search) ?></strong>"
                    <?php elseif (!empty($date_from) || !empty($date_to)): ?>
                        No prescriptions in this date range
                    <?php elseif ($filter_status !== 'all'): ?>
                        No <?= ucfirst($filter_status) ?> prescriptions
                    <?php else: ?>
                        No prescriptions have been created yet
                    <?php endif; ?>
                </p>
            </div>
        <?php endif; ?>
    </div>

    <!-- FOOTER -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Prescription History (Grouped by Patient)
            <span class="text-gray-300 mx-2">|</span>
            <span id="footerTimestamp">Last updated: <?= date('H:i:s') ?></span>
            <span class="text-gray-300 mx-2">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<!-- TOAST -->
<div id="toast" class="toast-custom" style="display:none;">
    <i class="fas fa-info-circle"></i>
    <div>
        <p style="font-weight:600;font-size:0.8rem;margin:0;" id="toastTitle">Notification</p>
        <p style="font-size:0.7rem;opacity:0.9;margin:0;" id="toastMessage"></p>
    </div>
</div>

<script>
    // DARK MODE
    var htmlElement = document.documentElement;
    var savedDarkMode = localStorage.getItem('darkMode');
    if (savedDarkMode === 'true') {
        htmlElement.setAttribute('data-theme', 'dark');
    } else if (savedDarkMode === 'false') {
        htmlElement.removeAttribute('data-theme');
    } else {
        var cookieDark = document.cookie.match(/dark_mode=([^;]+)/);
        if (cookieDark && cookieDark[1] === 'true') {
            htmlElement.setAttribute('data-theme', 'dark');
        }
    }

    // SIDEBAR
    var sidebar = document.getElementById('sidebar');
    var sidebarToggle = document.getElementById('sidebarToggle');
    
    if (sidebarToggle && sidebar) {
        sidebarToggle.addEventListener('click', function() {
            sidebar.classList.toggle('open');
        });
    }
    
    document.addEventListener('click', function(e) {
        if (window.innerWidth <= 1024) {
            if (sidebar && !sidebar.contains(e.target) && e.target !== sidebarToggle) {
                sidebar.classList.remove('open');
            }
        }
    });

    // TOGGLE PATIENT CARD
    function togglePatient(patientId) {
        var body = document.getElementById('body-' + patientId);
        var chevron = document.getElementById('chevron-' + patientId);
        
        if (body) {
            body.classList.toggle('open');
        }
        if (chevron) {
            chevron.classList.toggle('rotated');
        }
    }
    
    // Auto-open first patient card
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

    // ================================================================
    // LIVE SEARCH WITH TOTAL QTY FOR SPECIFIC MEDICINE
    // ================================================================
    var tableSearch = document.getElementById('tableSearch');
    var searchClear = document.getElementById('searchClear');
    var searchResultsCount = document.getElementById('searchResultsCount');
    var searchInfoBox = document.getElementById('searchInfoBox');
    var searchTermDisplay = document.getElementById('searchTermDisplay');
    var totalQtyDisplay = document.getElementById('totalQtyDisplay');
    var totalPatientsDisplay = document.getElementById('totalPatientsDisplay');
    var totalPrescriptionsDisplay = document.getElementById('totalPrescriptionsDisplay');
    var patientCards = document.querySelectorAll('.patient-card');
    var noSearchResults = document.getElementById('noSearchResults');
    var headerCount = document.getElementById('headerCount');
    
    var totalPatients = patientCards.length;
    if (headerCount) headerCount.textContent = totalPatients + ' Patients';
    
    var originalHTMLMap = new WeakMap();
    
    function escapeHtml(text) {
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    function escapeRegex(text) {
        return text.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    }
    
    function numberFormat(num) {
        return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    }
    
    function highlightText(element, query) {
        if (!element) return 0;
        
        if (!originalHTMLMap.has(element)) {
            originalHTMLMap.set(element, element.innerHTML);
        }
        
        var originalHTML = originalHTMLMap.get(element);
        
        if (!query || query.trim() === '') {
            element.innerHTML = originalHTML;
            return 0;
        }
        
        var tempDiv = document.createElement('div');
        tempDiv.innerHTML = originalHTML;
        var textContent = tempDiv.textContent || tempDiv.innerText || '';
        
        if (!textContent.trim()) {
            element.innerHTML = originalHTML;
            return 0;
        }
        
        var escapedQuery = escapeRegex(query);
        var regex = new RegExp('(' + escapedQuery + ')', 'gi');
        
        var matches = textContent.match(regex);
        var matchCount = matches ? matches.length : 0;
        
        if (matchCount === 0) {
            element.innerHTML = originalHTML;
            return 0;
        }
        
        var escapedText = escapeHtml(textContent);
        var highlightedHTML = escapedText.replace(regex, '<mark class="search-highlight">$1</mark>');
        
        element.innerHTML = highlightedHTML;
        return matchCount;
    }
    
    function removeAllHighlights() {
        document.querySelectorAll('[data-searchable]').forEach(function(el) {
            if (originalHTMLMap.has(el)) {
                el.innerHTML = originalHTMLMap.get(el);
            }
        });
    }
    
    function calculateSearchTotals(query) {
        if (!query || query.trim() === '') {
            if (searchInfoBox) searchInfoBox.classList.remove('show');
            return { totalQty: 0, totalPatients: 0, totalPrescriptions: 0 };
        }
        
        var lowerQuery = query.toLowerCase().trim();
        var allRows = document.querySelectorAll('.med-row');
        var totalQty = 0;
        var totalPatientsSet = new Set();
        var totalPrescriptions = 0;
        
        allRows.forEach(function(row) {
            var medName = row.dataset.medName || '';
            var qty = parseInt(row.dataset.qty) || 0;
            
            if (medName.indexOf(lowerQuery) !== -1) {
                totalQty += qty;
                totalPrescriptions++;
                
                var patientCard = row.closest('.patient-card');
                if (patientCard) {
                    totalPatientsSet.add(patientCard.dataset.patientId);
                }
            }
        });
        
        var totalPatients = totalPatientsSet.size;
        
        if (searchInfoBox && totalPrescriptions > 0) {
            searchTermDisplay.textContent = query;
            totalQtyDisplay.innerHTML = numberFormat(totalQty) + ' <small>units</small>';
            totalPatientsDisplay.innerHTML = numberFormat(totalPatients) + ' <small>patients</small>';
            totalPrescriptionsDisplay.innerHTML = numberFormat(totalPrescriptions) + ' <small>items</small>';
            searchInfoBox.classList.add('show');
        } else if (searchInfoBox) {
            searchInfoBox.classList.remove('show');
        }
        
        return { totalQty: totalQty, totalPatients: totalPatients, totalPrescriptions: totalPrescriptions };
    }
    
    function performLiveSearch() {
        var query = tableSearch.value.toLowerCase().trim();
        
        if (query.length > 0) {
            searchClear.classList.add('visible');
        } else {
            searchClear.classList.remove('visible');
        }
        
        removeAllHighlights();
        
        var visiblePatients = 0;
        
        patientCards.forEach(function(card) {
            var medRows = card.querySelectorAll('.med-row');
            var hasMatch = false;
            
            medRows.forEach(function(row) {
                var searchData = row.getAttribute('data-search') || '';
                if (query === '' || searchData.includes(query)) {
                    hasMatch = true;
                }
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
                
                var visibleRows = 0;
                medRows.forEach(function(row) {
                    var searchData = row.getAttribute('data-search') || '';
                    if (query === '' || searchData.includes(query)) {
                        row.style.display = '';
                        visibleRows++;
                        var rowNum = row.querySelector('.row-number');
                        if (rowNum) rowNum.textContent = visibleRows;
                        
                        if (query !== '') {
                            var searchableElements = row.querySelectorAll('[data-searchable]');
                            searchableElements.forEach(function(el) {
                                highlightText(el, query);
                            });
                        }
                    } else {
                        row.style.display = 'none';
                    }
                });
            } else {
                card.style.display = 'none';
            }
        });
        
        calculateSearchTotals(query);
        
        if (visiblePatients === 0 && query !== '') {
            noSearchResults.style.display = '';
        } else {
            noSearchResults.style.display = 'none';
        }
        
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
            if (e.key === 'Escape') {
                tableSearch.value = '';
                performLiveSearch();
                tableSearch.blur();
            }
        });
    }
    
    if (searchClear) {
        searchClear.addEventListener('click', function() {
            tableSearch.value = '';
            performLiveSearch();
            tableSearch.focus();
        });
    }
    
    if (tableSearch && tableSearch.value.trim() !== '') {
        performLiveSearch();
    }
    
    document.addEventListener('keydown', function(e) {
        if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
            e.preventDefault();
            if (tableSearch) {
                tableSearch.focus();
                tableSearch.select();
            }
        }
    });

    // DATE & TIME
    function updateDateTime() {
        var now = new Date();
        var timeStr = now.toLocaleTimeString('en-US', {
            hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true
        });
        
        var footerTimestamp = document.getElementById('footerTimestamp');
        if (footerTimestamp) footerTimestamp.textContent = 'Last updated: ' + timeStr;
    }
    updateDateTime();
    setInterval(updateDateTime, 1000);

    // TOAST
    function showToast(title, message, type) {
        var toast = document.getElementById('toast');
        var toastTitle = document.getElementById('toastTitle');
        var toastMessage = document.getElementById('toastMessage');
        
        if (!toast) return;
        
        toast.className = 'toast-custom ' + type;
        toastTitle.textContent = title;
        toastMessage.textContent = message;
        toast.style.display = 'flex';
        
        toast.classList.add('show');
        clearTimeout(toast.timeout);
        toast.timeout = setTimeout(function() {
            toast.classList.remove('show');
            setTimeout(function() {
                toast.style.display = 'none';
            }, 400);
        }, 3500);
    }

    console.log('%c📋 Braick - Prescription History (Grouped by Patient)', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c✅ 1. Filters (All, Pending, Confirmed, Dispensed, Cancelled)', 'font-size:13px; color:#34D399;');
    console.log('%c✅ 2. Search bar - inatafuta dawa na total qty', 'font-size:13px; color:#FCD34D;');
    console.log('%c✅ 3. Patient tables with DATE display', 'font-size:13px; color:#34D399;');
    console.log('%c✅ VIEW button - small width', 'font-size:13px; color:#34D399;');
    console.log('%c📊 Total Patients: <?= count($grouped_prescriptions) ?>', 'font-size:13px; color:#64748B;');
</script>

</body>
</html>