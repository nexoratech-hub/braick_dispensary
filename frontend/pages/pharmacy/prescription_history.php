<?php
// ================================================================
// FILE: frontend/pages/pharmacy/prescription_history.php
// PHARMACY - PRESCRIPTION HISTORY (GROUPED BY PATIENT)
// FIXED: Smaller filter cards + All filters in ONE ROW
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
        END) as total_amount,
        GROUP_CONCAT(
            CONCAT(
                p.id, ':', 
                p.prescription_number, ':', 
                p.status, ':',
                COALESCE((SELECT SUM(pi.total_price) FROM prescription_items pi WHERE pi.prescription_id = p.id), 0), ':',
                COALESCE((SELECT GROUP_CONCAT(CONCAT(pi.medication_name, ' (', pi.quantity, ' pcs)') SEPARATOR ', ') 
                          FROM prescription_items pi WHERE pi.prescription_id = p.id LIMIT 20), 'No medications')
            ) 
            ORDER BY p.id DESC SEPARATOR '||'
        ) as prescription_items_data
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

// Parse prescription data for each patient
foreach ($grouped_prescriptions as &$group) {
    $group['prescriptions'] = [];
    if (!empty($group['prescription_items_data'])) {
        $items = explode('||', $group['prescription_items_data']);
        foreach ($items as $item) {
            $parts = explode(':', $item);
            if (count($parts) >= 5) {
                $group['prescriptions'][] = [
                    'id' => $parts[0],
                    'number' => $parts[1],
                    'status' => $parts[2],
                    'amount' => $parts[3],
                    'medications' => $parts[4]
                ];
            }
        }
    }
    
    $search_parts = [
        $group['patient_name'] ?? '',
        $group['patient_number'] ?? '',
        $group['patient_phone'] ?? '',
        $group['patient_gender'] ?? ''
    ];
    
    $all_meds_text = '';
    foreach ($group['prescriptions'] as $presc) {
        if (!empty($presc['medications']) && $presc['medications'] !== 'No medications') {
            $all_meds_text .= ' ' . $presc['medications'];
        }
    }
    $search_parts[] = $all_meds_text;
    
    $group['search_data'] = strtolower(implode(' ', $search_parts));
    
    $status = $group['overall_status'] ?? 'pending';
    $status_colors = [
        'pending' => 'warning',
        'confirmed' => 'info',
        'dispensed' => 'success',
        'cancelled' => 'danger'
    ];
    $group['status_color'] = $status_colors[$status] ?? 'secondary';
    $group['status_label'] = ucfirst($status);
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

$amount_sql = "
    SELECT COALESCE(SUM(pi.total_price), 0) as total_amount 
    FROM prescription_items pi
    INNER JOIN prescriptions p ON pi.prescription_id = p.id
    $stats_where AND p.status = 'dispensed'
";
$stmt = $db->prepare($amount_sql);
$stmt->execute($stats_params);
$total_amount_all = $stmt->fetch(PDO::FETCH_ASSOC)['total_amount'] ?? 0;

function formatDate($datetime) {
    if (empty($datetime)) return 'N/A';
    return date('d/m/Y h:i A', strtotime($datetime));
}

$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

include_once '../../components/pharmacy_header.php';
include_once '../../components/pharmacy_sidebar.php';
?>

<!DOCTYPE html>
<html lang="en" data-theme="<?= isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prescription History - Braick Dispensary</title>
    
    <link rel="icon" href="/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <style>
        :root {
            --primary: #0B5ED7;
            --primary-dark: #0A4CA8;
            --primary-light: #6EA8FE;
            --primary-bg: #E8F0FE;
            --success: #059669;
            --success-dark: #047857;
            --success-bg: #D1FAE5;
            --danger: #DC2626;
            --danger-bg: #FEE2E2;
            --warning: #D97706;
            --warning-bg: #FEF3C7;
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
            --radius: 10px;
            --radius-lg: 14px;
            --transition: all 0.3s ease;
            --bg-body: #F1F5F9;
            --bg-card: #FFFFFF;
            --text-primary: #1E293B;
            --text-secondary: #64748B;
            --text-muted: #94A3B8;
            --border-color: #E2E8F0;
            --shadow: 0 1px 3px rgba(0,0,0,0.06);
            --shadow-md: 0 4px 16px rgba(0,0,0,0.08);
        }
        
        [data-theme="dark"] {
            --bg-body: #0F172A;
            --bg-card: #1E293B;
            --text-primary: #F1F5F9;
            --text-secondary: #94A3B8;
            --text-muted: #64748B;
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
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border-radius: 16px;
            padding: 20px 28px;
            margin-bottom: 20px;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            box-shadow: 0 4px 20px rgba(11, 94, 215, 0.25);
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
            gap: 10px;
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
            margin-top: 4px;
        }
        
        .page-header .page-subtitle strong { color: white; font-weight: 600; }
        
        .page-header .badge-display {
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 3px 12px;
            border-radius: 20px;
            font-size: 0.65rem;
            font-weight: 600;
            backdrop-filter: blur(4px);
            border: 1px solid rgba(255,255,255,0.1);
        }
        
        .page-header .btn-outline-light {
            background: rgba(255,255,255,0.15);
            color: white;
            border: 1px solid rgba(255,255,255,0.2);
            padding: 6px 14px;
            border-radius: 8px;
            font-weight: 500;
            font-size: 0.75rem;
            transition: all 0.3s;
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
            box-shadow: 0 4px 16px rgba(0,0,0,0.15);
        }
        
        /* ================================================================ */
        /* ✅ SMALLER STATS CARDS */
        /* ================================================================ */
        .stats-row {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 12px;
            margin-bottom: 16px;
        }
        
        .stat-card {
            background: var(--bg-card);
            border-radius: var(--radius);
            padding: 12px 16px;
            border: 2px solid var(--border-color);
            transition: var(--transition);
            text-align: center;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
        }
        
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }
        
        .stat-card .stat-icon { font-size: 1.4rem; line-height: 1; }
        
        .stat-card .stat-content {
            display: flex;
            flex-direction: column;
            text-align: left;
            line-height: 1.2;
        }
        
        .stat-card .stat-number { font-size: 1.4rem; font-weight: 700; line-height: 1; }
        .stat-card .stat-number.green { color: #059669; }
        .stat-card .stat-number.red { color: #DC2626; }
        .stat-card .stat-number.orange { color: #D97706; }
        .stat-card .stat-number.blue { color: #0B5ED7; }
        
        .stat-card .stat-label {
            font-size: 0.65rem;
            color: var(--text-secondary);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            margin-top: 2px;
        }
        
        .stat-card.pending { border-color: #D97706; background: #FEF3C7; }
        .stat-card.confirmed { border-color: #0B5ED7; background: #E8F0FE; }
        .stat-card.dispensed { border-color: #059669; background: #D1FAE5; }
        .stat-card.cancelled { border-color: #DC2626; background: #FEE2E2; }
        
        [data-theme="dark"] .stat-card.pending { background: #3D2E0A; border-color: #FBBF24; }
        [data-theme="dark"] .stat-card.pending .stat-number { color: #FBBF24; }
        [data-theme="dark"] .stat-card.confirmed { background: #1A2A4A; border-color: #6EA8FE; }
        [data-theme="dark"] .stat-card.confirmed .stat-number { color: #6EA8FE; }
        [data-theme="dark"] .stat-card.dispensed { background: #1A3A2A; border-color: #34D399; }
        [data-theme="dark"] .stat-card.dispensed .stat-number { color: #34D399; }
        [data-theme="dark"] .stat-card.cancelled { background: #3A1A1A; border-color: #F87171; }
        [data-theme="dark"] .stat-card.cancelled .stat-number { color: #F87171; }
        
        /* ================================================================ */
        /* ✅ ALL FILTERS IN ONE ROW */
        /* ================================================================ */
        .filter-section {
            background: var(--bg-card);
            border-radius: var(--radius);
            padding: 10px 14px;
            border: 1px solid var(--border-color);
            margin-bottom: 16px;
        }
        
        .filter-row {
            display: flex;
            flex-wrap: nowrap;
            gap: 6px;
            align-items: center;
            overflow-x: auto;
            padding-bottom: 2px;
        }
        
        .filter-row::-webkit-scrollbar {
            height: 4px;
        }
        
        .filter-row::-webkit-scrollbar-thumb {
            background: var(--primary);
            border-radius: 10px;
        }
        
        .filter-btn {
            padding: 4px 10px;
            border-radius: 16px;
            font-size: 0.65rem;
            font-weight: 600;
            border: 1.5px solid var(--border-color);
            background: transparent;
            color: var(--text-secondary);
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
            white-space: nowrap;
            flex-shrink: 0;
        }
        
        .filter-btn:hover {
            border-color: var(--primary);
            color: var(--primary);
            background: var(--primary-bg);
        }
        
        .filter-btn.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }
        
        .filter-input {
            padding: 4px 8px;
            border: 1.5px solid var(--border-color);
            border-radius: 8px;
            font-size: 0.68rem;
            background: var(--bg-card);
            color: var(--text-primary);
            outline: none;
            transition: var(--transition);
            height: 28px;
            flex-shrink: 0;
        }
        
        .filter-input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 2px rgba(11, 94, 215, 0.12);
        }
        
        .filter-input[type="date"] { 
            width: 130px;
            font-size: 0.65rem;
        }
        
        .filter-divider {
            width: 1px;
            height: 20px;
            background: var(--border-color);
            margin: 0 4px;
            flex-shrink: 0;
        }
        
        .btn-filter {
            padding: 4px 14px;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.68rem;
            cursor: pointer;
            transition: var(--transition);
            height: 28px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            flex-shrink: 0;
        }
        
        .btn-filter:hover {
            background: var(--primary-dark);
            transform: translateY(-1px);
        }
        
        .btn-clear-filter {
            padding: 4px 10px;
            background: transparent;
            color: var(--danger);
            border: 1.5px solid var(--danger);
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.68rem;
            cursor: pointer;
            transition: var(--transition);
            height: 28px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            flex-shrink: 0;
        }
        
        .btn-clear-filter:hover {
            background: var(--danger);
            color: white;
        }
        
        /* TABLE */
        .table-container {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            border: 1px solid var(--border-color);
            overflow: hidden;
            box-shadow: var(--shadow);
        }
        
        .table-header-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            padding: 10px 14px;
            border-bottom: 2px solid var(--border-color);
            background: var(--bg-card);
        }
        
        .table-header-left {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            flex: 1;
            min-width: 0;
        }
        
        .table-header-right {
            display: flex;
            align-items: center;
            gap: 6px;
            flex-wrap: wrap;
            flex-shrink: 0;
        }
        
        /* SMALL BLUE SEARCH BOX - LEFT SIDE */
        .table-search-box {
            position: relative;
            min-width: 220px;
            flex: 0 1 auto;
            max-width: 300px;
        }
        
        .table-search-box i {
            position: absolute;
            left: 11px;
            top: 50%;
            transform: translateY(-50%);
            color: rgba(255,255,255,0.95);
            font-size: 0.72rem;
            pointer-events: none;
            z-index: 1;
        }
        
        .table-search-box input {
            width: 100%;
            padding: 7px 12px 7px 32px;
            border: 2px solid var(--primary-dark);
            border-radius: 8px;
            font-size: 0.72rem;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            outline: none;
            transition: all 0.3s ease;
            font-weight: 500;
            height: 34px;
            box-shadow: 0 3px 10px rgba(11, 94, 215, 0.25);
        }
        
        .table-search-box input::placeholder {
            color: rgba(255,255,255,0.85);
            font-weight: 400;
            font-size: 0.7rem;
        }
        
        .table-search-box input:focus {
            box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.25), 0 4px 14px rgba(11, 94, 215, 0.35);
            transform: translateY(-1px);
        }
        
        .table-search-box input:hover {
            box-shadow: 0 5px 16px rgba(11, 94, 215, 0.35);
        }
        
        .table-title-inline {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 0.82rem;
            font-weight: 600;
            color: var(--text-primary);
            white-space: nowrap;
        }
        
        .table-title-inline i { color: var(--primary); }
        
        .search-results-info {
            font-size: 0.65rem;
            color: var(--primary);
            padding: 4px 10px;
            background: var(--primary-bg);
            border-radius: 6px;
            white-space: nowrap;
            display: none;
            font-weight: 600;
            border: 1px solid var(--primary);
        }
        
        .search-results-info.show { display: inline-flex; align-items: center; gap: 4px; }
        .search-results-info strong { color: var(--primary); font-size: 0.75rem; }
        
        .scroll-btn-header {
            width: 34px;
            height: 34px;
            border-radius: 8px;
            border: 2px solid var(--border-color);
            background: var(--bg-card);
            color: var(--text-primary);
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            font-size: 0.75rem;
        }
        
        .scroll-btn-header:hover {
            background: var(--primary);
            border-color: var(--primary);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3);
        }
        
        .scroll-btn-header:disabled {
            opacity: 0.35;
            cursor: not-allowed;
            transform: none;
        }
        
        .scroll-btn-header:disabled:hover {
            background: var(--bg-card);
            border-color: var(--border-color);
            color: var(--text-primary);
            box-shadow: none;
        }
        
        .table-scroll {
            overflow-x: auto;
            overflow-y: visible;
            scroll-behavior: smooth;
            -webkit-overflow-scrolling: touch;
            max-width: 100%;
        }
        
        .table-scroll::-webkit-scrollbar { height: 8px; }
        .table-scroll::-webkit-scrollbar-track { background: var(--bg-body); border-radius: 10px; }
        .table-scroll::-webkit-scrollbar-thumb { background: #0B5ED7; border-radius: 10px; }
        .table-scroll::-webkit-scrollbar-thumb:hover { background: #0A4CA8; }
        
        .data-table {
            width: 100%;
            min-width: 1100px;
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
            position: sticky;
            top: 0;
            z-index: 5;
        }
        
        .data-table thead th i { margin-right: 5px; opacity: 0.7; }
        
        .data-table tbody td {
            padding: 8px 14px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-primary);
            vertical-align: middle;
        }
        
        .data-table tbody tr:hover td { background: var(--primary-bg); }
        .data-table tbody tr:last-child td { border-bottom: none; }
        .data-table tbody tr:nth-child(even) td { background: var(--gray-50); }
        
        [data-theme="dark"] .data-table tbody tr:nth-child(even) td { background: #1A1A2E; }
        
        .col-sno { width: 45px; min-width: 45px; text-align: center; }
        .col-patient { min-width: 160px; }
        .col-patient-id { min-width: 100px; }
        .col-prescriptions { min-width: 180px; max-width: 250px; }
        .col-medications { min-width: 200px; max-width: 350px; }
        .col-amount { min-width: 120px; text-align: right; }
        .col-status { min-width: 100px; text-align: center; }
        .col-count { min-width: 70px; text-align: center; }
        .col-date { min-width: 130px; }
        .col-actions { min-width: 140px; text-align: center; }
        
        .badge-status {
            display: inline-block;
            padding: 2px 12px;
            border-radius: 20px;
            font-size: 0.6rem;
            font-weight: 600;
        }
        
        .badge-success { background: var(--success-bg); color: var(--success); border: 1px solid var(--success); }
        .badge-warning { background: var(--warning-bg); color: var(--warning); border: 1px solid var(--warning); }
        .badge-info { background: var(--primary-bg); color: var(--primary); border: 1px solid var(--primary); }
        .badge-danger { background: var(--danger-bg); color: var(--danger); border: 1px solid var(--danger); }
        
        .amount-cell { font-weight: 700; color: #0B5ED7; font-family: 'Courier New', monospace; font-size: 0.85rem; }
        [data-theme="dark"] .amount-cell { color: #60A5FA; }
        
        .prescription-tag {
            display: inline-block;
            font-size: 0.6rem;
            padding: 1px 8px;
            border-radius: 10px;
            background: var(--bg-body);
            color: var(--text-secondary);
            border: 1px solid var(--border-color);
            margin: 1px 2px;
            font-family: monospace;
            white-space: nowrap;
        }
        
        .prescription-tag .status-dot {
            display: inline-block;
            width: 6px;
            height: 6px;
            border-radius: 50%;
            margin-right: 3px;
        }
        .prescription-tag .status-dot.pending { background: #D97706; }
        .prescription-tag .status-dot.confirmed { background: #0B5ED7; }
        .prescription-tag .status-dot.dispensed { background: #059669; }
        .prescription-tag .status-dot.cancelled { background: #EF4444; }
        
        .medication-list {
            font-size: 0.7rem;
            color: var(--text-secondary);
            line-height: 1.4;
            max-height: 60px;
            overflow-y: auto;
            padding-right: 4px;
        }
        .medication-list::-webkit-scrollbar { width: 3px; }
        .medication-list::-webkit-scrollbar-thumb { background: #0B5ED7; border-radius: 4px; }
        
        .medication-item {
            display: inline-block;
            background: var(--bg-body);
            padding: 1px 6px;
            border-radius: 4px;
            margin: 1px 2px 1px 0;
            font-size: 0.6rem;
            border: 1px solid var(--border-color);
        }
        .medication-item .qty { font-weight: 600; color: #0B5ED7; }
        [data-theme="dark"] .medication-item .qty { color: #60A5FA; }
        
        /* HIGHLIGHT STYLES */
        mark.highlight {
            background: #FEF08A;
            color: #854D0E;
            padding: 1px 3px;
            border-radius: 3px;
            font-weight: 700;
            box-shadow: 0 0 0 1px #FDE047;
        }
        
        [data-theme="dark"] mark.highlight {
            background: #854D0E;
            color: #FEF08A;
            box-shadow: 0 0 0 1px #A16207;
        }
        
        mark.highlight-med {
            background: #FED7AA;
            color: #9A3412;
            padding: 1px 4px;
            border-radius: 4px;
            font-weight: 700;
            box-shadow: 0 0 0 1px #FDBA74;
        }
        
        [data-theme="dark"] mark.highlight-med {
            background: #7C2D12;
            color: #FED7AA;
            box-shadow: 0 0 0 1px #9A3412;
        }
        
        tr.presc-row.highlighted td:first-child {
            box-shadow: inset 4px 0 0 0 #FBBF24;
        }
        
        tr.presc-row.highlighted {
            background: rgba(254, 240, 138, 0.15) !important;
        }
        
        [data-theme="dark"] tr.presc-row.highlighted {
            background: rgba(133, 77, 14, 0.15) !important;
        }
        
        .btn-view {
            background: var(--primary);
            color: white;
            padding: 3px 10px;
            border-radius: 4px;
            font-size: 0.6rem;
            transition: var(--transition);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            border: none;
            cursor: pointer;
        }
        
        .btn-view:hover {
            background: var(--primary-dark);
            transform: translateY(-1px);
        }
        
        .btn-outline {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 5px 12px;
            border-radius: 6px;
            font-weight: 600;
            font-size: 0.7rem;
            transition: var(--transition);
            cursor: pointer;
            text-decoration: none;
            background: transparent;
            color: var(--text-secondary);
            border: 2px solid var(--border-color);
        }
        
        .btn-outline:hover {
            background: var(--bg-body);
            border-color: var(--primary);
            color: var(--primary);
        }
        
        .btn-primary {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 5px 12px;
            border-radius: 6px;
            font-weight: 600;
            font-size: 0.7rem;
            transition: var(--transition);
            cursor: pointer;
            text-decoration: none;
            background: var(--primary);
            color: white;
            border: none;
        }
        
        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3);
        }
        
        .table-footer {
            padding: 10px 16px;
            border-top: 1px solid var(--border-color);
            font-size: 0.7rem;
            color: var(--text-secondary);
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 8px;
            background: var(--gray-50);
        }
        
        [data-theme="dark"] .table-footer {
            border-color: var(--gray-700);
            color: var(--gray-400);
            background: var(--gray-800);
        }
        
        .count-badge {
            background: var(--primary);
            color: white;
            padding: 2px 12px;
            border-radius: 20px;
            font-size: 0.65rem;
            font-weight: 600;
        }
        
        .count-badge.green { background: var(--success); }
        .count-badge.red { background: var(--danger); }
        .count-badge.orange { background: var(--warning); }
        .count-badge.blue { background: var(--primary); }
        
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .py-8 { padding-top: 40px; padding-bottom: 40px; }
        .text-gray-400 { color: var(--text-muted); }
        .text-3xl { font-size: 2.5rem; }
        .block { display: block; }
        .mb-2 { margin-bottom: 8px; }
        .mt-1 { margin-top: 4px; }
        .mt-3 { margin-top: 12px; }
        .text-sm { font-size: 0.8rem; }
        .font-mono { font-family: monospace; }
        .font-medium { font-weight: 500; }
        .font-semibold { font-weight: 600; }
        .font-bold { font-weight: 700; }
        .text-xs { font-size: 0.7rem; }
        
        .flex { display: flex; }
        .flex-wrap { flex-wrap: wrap; }
        .gap-1 { gap: 4px; }
        .gap-2 { gap: 8px; }
        .items-center { align-items: center; }
        .ml-2 { margin-left: 8px; }
        
        .footer {
            padding: 14px 0;
            border-top: 1px solid var(--border-color);
            margin-top: 24px;
            text-align: center;
            font-size: 0.7rem;
            color: var(--text-secondary);
        }
        
        .footer .footer-brand { color: var(--primary); font-weight: 600; }
        .text-gray-300 { color: var(--gray-300); }
        .mx-2 { margin-left: 8px; margin-right: 8px; }
        
        /* Modal */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.6);
            z-index: 9999;
            justify-content: center;
            align-items: center;
            padding: 20px;
            backdrop-filter: blur(4px);
        }
        .modal-overlay.show { display: flex; }
        
        .modal-content {
            background: var(--bg-card);
            border-radius: 16px;
            max-width: 800px;
            width: 100%;
            max-height: 85vh;
            overflow-y: auto;
            padding: 24px 28px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            border: 2px solid var(--border-color);
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-bottom: 12px;
            border-bottom: 2px solid var(--border-color);
            margin-bottom: 16px;
        }
        
        .modal-header .modal-title { font-size: 1.1rem; font-weight: 700; color: var(--text-primary); }
        .modal-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--text-secondary);
            transition: all 0.3s ease;
        }
        .modal-close:hover { color: #EF4444; transform: rotate(90deg); }
        
        .modal-actions {
            display: flex;
            gap: 10px;
            padding-top: 14px;
            border-top: 2px solid var(--border-color);
            margin-top: 16px;
            flex-wrap: wrap;
        }
        
        .btn-close-modal {
            background: transparent;
            color: var(--text-secondary);
            border: 2px solid var(--border-color);
            padding: 8px 24px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.85rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .btn-close-modal:hover { border-color: #EF4444; color: #EF4444; }
        
        .prescription-detail-card {
            background: var(--bg-body);
            border-radius: 8px;
            padding: 12px 16px;
            margin-bottom: 10px;
            border-left: 4px solid #0B5ED7;
        }
        
        .prescription-detail-card .pd-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 6px;
        }
        
        .prescription-detail-card .pd-number {
            font-family: monospace;
            font-weight: 700;
            color: #0B5ED7;
            font-size: 0.85rem;
        }
        
        .prescription-detail-card .pd-items {
            margin-top: 6px;
            font-size: 0.8rem;
            color: var(--text-secondary);
        }
        
        .prescription-detail-card .pd-items .item {
            display: inline-block;
            background: var(--bg-card);
            padding: 2px 10px;
            border-radius: 6px;
            margin: 2px 4px 2px 0;
            border: 1px solid var(--border-color);
            font-size: 0.75rem;
        }
        
        .prescription-detail-card .pd-items .item .qty {
            font-weight: 700;
            color: #0B5ED7;
        }
        
        .no-results-row td {
            text-align: center;
            padding: 30px 20px !important;
            color: var(--text-secondary);
        }
        
        .no-results-row i {
            font-size: 2rem;
            color: var(--border-color);
            display: block;
            margin-bottom: 8px;
        }
        
        @media (max-width: 1024px) {
            .main-content { margin-left: 0; padding: 16px; }
            .stats-row { grid-template-columns: repeat(4, 1fr); gap: 8px; }
            .stat-card { padding: 10px 12px; gap: 8px; }
            .stat-card .stat-icon { font-size: 1.2rem; }
            .stat-card .stat-number { font-size: 1.2rem; }
        }
        
        @media (max-width: 768px) {
            .page-header { padding: 16px 18px; }
            .page-header .page-title { font-size: 1.3rem; }
            .stats-row { grid-template-columns: repeat(4, 1fr); gap: 6px; }
            .stat-card { 
                flex-direction: column;
                gap: 2px;
                padding: 8px 6px;
                text-align: center;
            }
            .stat-card .stat-content { text-align: center; }
            .stat-card .stat-icon { font-size: 1rem; }
            .stat-card .stat-number { font-size: 1.1rem; }
            .stat-card .stat-label { font-size: 0.55rem; }
            
            .filter-row { 
                flex-wrap: nowrap;
                overflow-x: auto;
                padding-bottom: 4px;
            }
            .filter-btn { padding: 3px 8px; font-size: 0.6rem; }
            .filter-input[type="date"] { width: 110px; font-size: 0.6rem; }
            .btn-filter { padding: 3px 10px; font-size: 0.6rem; }
            
            .data-table { font-size: 0.7rem; min-width: 850px; }
            .data-table thead th, .data-table tbody td { padding: 5px 8px; }
            .col-medications { min-width: 120px; }
            .col-prescriptions { min-width: 120px; }
            .modal-content { padding: 16px; }
            .table-header-bar { flex-direction: column; align-items: stretch; }
            .table-header-left { width: 100%; flex-direction: column; align-items: stretch; }
            .table-search-box { min-width: 100%; max-width: 100%; }
            .table-header-right { width: 100%; justify-content: flex-end; }
        }
        
        @media (max-width: 480px) {
            .main-content { padding: 10px; }
            .stats-row { grid-template-columns: repeat(4, 1fr); gap: 4px; }
            .stat-card { padding: 6px 4px; }
            .stat-card .stat-icon { font-size: 0.9rem; }
            .stat-card .stat-number { font-size: 0.95rem; }
            .stat-card .stat-label { font-size: 0.5rem; }
            .page-title { font-size: 1.1rem; }
            .data-table { font-size: 0.65rem; min-width: 750px; }
            .filter-btn { padding: 3px 6px; font-size: 0.55rem; }
            .filter-input[type="date"] { width: 100px; font-size: 0.55rem; }
        }
        
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-fade-in-up { animation: fadeInUp 0.5s ease forwards; opacity: 0; }
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
                <span class="badge-display"><?= count($grouped_prescriptions) ?> Patients</span>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-users"></i>
                Grouped by patient - <strong><?= count($grouped_prescriptions) ?></strong> patients
                <span class="badge-display" style="background:rgba(255,255,255,0.15);">
                    <i class="fas fa-money-bill-wave"></i> TSh <?= number_format($total_amount_all) ?>
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
    <!-- STATS ROW - SMALLER -->
    <!-- ================================================================ -->
    <div class="stats-row animate-fade-in-up">
        <div class="stat-card pending">
            <span class="stat-icon">⏳</span>
            <div class="stat-content">
                <span class="stat-number orange"><?= $pending_count ?></span>
                <span class="stat-label">Pending</span>
            </div>
        </div>
        <div class="stat-card confirmed">
            <span class="stat-icon">✅</span>
            <div class="stat-content">
                <span class="stat-number blue"><?= $confirmed_count ?></span>
                <span class="stat-label">Confirmed</span>
            </div>
        </div>
        <div class="stat-card dispensed">
            <span class="stat-icon">💊</span>
            <div class="stat-content">
                <span class="stat-number green"><?= $dispensed_count ?></span>
                <span class="stat-label">Dispensed</span>
            </div>
        </div>
        <div class="stat-card cancelled">
            <span class="stat-icon">❌</span>
            <div class="stat-content">
                <span class="stat-number red"><?= $cancelled_count ?></span>
                <span class="stat-label">Cancelled</span>
            </div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- FILTERS - ALL IN ONE ROW -->
    <!-- ================================================================ -->
    <div class="filter-section animate-fade-in-up" style="animation-delay:0.05s;">
        <div class="filter-row">
            <!-- Status filter buttons -->
            <a href="?status=all<?= !empty($date_from) ? '&date_from=' . urlencode($date_from) : '' ?><?= !empty($date_to) ? '&date_to=' . urlencode($date_to) : '' ?>" 
               class="filter-btn <?= $filter_status === 'all' ? 'active' : '' ?>">📋 All</a>
            <a href="?status=pending<?= !empty($date_from) ? '&date_from=' . urlencode($date_from) : '' ?><?= !empty($date_to) ? '&date_to=' . urlencode($date_to) : '' ?>" 
               class="filter-btn <?= $filter_status === 'pending' ? 'active' : '' ?>">⏳ Pending</a>
            <a href="?status=confirmed<?= !empty($date_from) ? '&date_from=' . urlencode($date_from) : '' ?><?= !empty($date_to) ? '&date_to=' . urlencode($date_to) : '' ?>" 
               class="filter-btn <?= $filter_status === 'confirmed' ? 'active' : '' ?>">✅ Confirmed</a>
            <a href="?status=dispensed<?= !empty($date_from) ? '&date_from=' . urlencode($date_from) : '' ?><?= !empty($date_to) ? '&date_to=' . urlencode($date_to) : '' ?>" 
               class="filter-btn <?= $filter_status === 'dispensed' ? 'active' : '' ?>">💊 Dispensed</a>
            <a href="?status=cancelled<?= !empty($date_from) ? '&date_from=' . urlencode($date_from) : '' ?><?= !empty($date_to) ? '&date_to=' . urlencode($date_to) : '' ?>" 
               class="filter-btn <?= $filter_status === 'cancelled' ? 'active' : '' ?>">❌ Cancelled</a>
            
            <div class="filter-divider"></div>
            
            <!-- Date range form -->
            <form method="GET" style="display:flex;gap:6px;align-items:center;flex-shrink:0;">
                <input type="hidden" name="status" value="<?= htmlspecialchars($filter_status) ?>">
                <input type="date" name="date_from" class="filter-input" value="<?= htmlspecialchars($date_from) ?>" placeholder="From">
                <input type="date" name="date_to" class="filter-input" value="<?= htmlspecialchars($date_to) ?>" placeholder="To">
                <button type="submit" class="btn-filter">
                    <i class="fas fa-filter"></i> Filter
                </button>
                <?php if (!empty($date_from) || !empty($date_to) || $filter_status !== 'all'): ?>
                    <a href="prescription_history.php" class="btn-clear-filter">
                        <i class="fas fa-times"></i> Clear
                    </a>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <!-- TABLE -->
    <div class="table-container animate-fade-in-up" style="animation-delay:0.1s;">
        
        <!-- TABLE HEADER BAR: SMALL SEARCH LEFT (BLUE) + SCROLL RIGHT -->
        <div class="table-header-bar">
            <div class="table-header-left">
                <div class="table-search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" id="prescSearchInput" placeholder="🔍 Search..." autocomplete="off">
                </div>
                
                <div class="table-title-inline">
                    <i class="fas fa-users"></i>
                    <span>Patients</span>
                    <span class="result-count" id="prescCountDisplay" style="font-size:0.75rem;color:var(--text-secondary);">
                        (<strong style="color:var(--primary);"><?= count($grouped_prescriptions) ?></strong>)
                    </span>
                </div>
                
                <span class="search-results-info" id="prescSearchInfo">
                    <i class="fas fa-filter"></i> <strong id="prescSearchCount">0</strong> match
                </span>
            </div>
            
            <div class="table-header-right">
                <button type="button" class="scroll-btn-header" id="prescScrollBtnLeft" onclick="scrollPrescTable('left')" title="Scroll Left">
                    <i class="fas fa-chevron-left"></i>
                </button>
                <button type="button" class="scroll-btn-header" id="prescScrollBtnRight" onclick="scrollPrescTable('right')" title="Scroll Right">
                    <i class="fas fa-chevron-right"></i>
                </button>
            </div>
        </div>
        
        <div class="table-scroll" id="prescTableWrapper">
            <table class="data-table" id="prescriptionTable">
                <thead>
                    <tr>
                        <th class="col-sno">#</th>
                        <th class="col-patient">Patient</th>
                        <th class="col-patient-id">ID / Phone</th>
                        <th class="col-prescriptions">Prescriptions</th>
                        <th class="col-medications">Medications</th>
                        <th class="col-amount">Amount</th>
                        <th class="col-status">Status</th>
                        <th class="col-count">Count</th>
                        <th class="col-date">Last Date</th>
                        <th class="col-actions">Actions</th>
                    </tr>
                </thead>
                <tbody id="prescTableBody">
                    <?php if (count($grouped_prescriptions) > 0): ?>
                        <?php $i = 1; foreach ($grouped_prescriptions as $group): ?>
                            <tr class="presc-row" data-search="<?= htmlspecialchars($group['search_data']) ?>">
                                <td class="font-bold presc-sno" style="color:#0B5ED7;"><?= $i++ ?></td>
                                <td class="cell-patient">
                                    <div class="font-semibold patient-name"><?= htmlspecialchars($group['patient_name'] ?? 'Unknown') ?></div>
                                    <div class="text-xs text-gray-400"><?= htmlspecialchars($group['patient_gender'] ?? 'N/A') ?></div>
                                    <?php if (!empty($group['date_of_birth'])): ?>
                                        <div class="text-xs text-gray-400">🎂 <?= date('d/m/Y', strtotime($group['date_of_birth'])) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="cell-patient-id">
                                    <div class="font-mono text-xs font-bold patient-id"><?= htmlspecialchars($group['patient_number'] ?? 'N/A') ?></div>
                                    <div class="text-xs text-gray-400 patient-phone"><?= htmlspecialchars($group['patient_phone'] ?? '') ?></div>
                                </td>
                                <td class="cell-prescriptions">
                                    <?php 
                                    $status_colors = ['pending' => 'pending', 'confirmed' => 'confirmed', 'dispensed' => 'dispensed', 'cancelled' => 'cancelled'];
                                    foreach (array_slice($group['prescriptions'], 0, 3) as $prescription): 
                                        $color = $status_colors[$prescription['status']] ?? 'secondary';
                                    ?>
                                        <span class="prescription-tag">
                                            <span class="status-dot <?= $color ?>"></span>
                                            <span class="prescription-number"><?= htmlspecialchars($prescription['number']) ?></span>
                                        </span>
                                    <?php endforeach; ?>
                                    <?php if (count($group['prescriptions']) > 3): ?>
                                        <span class="prescription-tag" style="background:var(--primary-bg);color:var(--primary);">
                                            +<?= count($group['prescriptions']) - 3 ?> more
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="cell-medications">
                                    <div class="medication-list">
                                        <?php 
                                        $all_meds = [];
                                        foreach ($group['prescriptions'] as $prescription) {
                                            if (!empty($prescription['medications']) && $prescription['medications'] !== 'No medications') {
                                                $meds = explode(', ', $prescription['medications']);
                                                foreach ($meds as $med) {
                                                    if (!empty($med) && $med !== 'No medications') {
                                                        $all_meds[] = $med;
                                                    }
                                                }
                                            }
                                        }
                                        $unique_meds = array_unique($all_meds);
                                        $display_meds = array_slice($unique_meds, 0, 5);
                                        foreach ($display_meds as $med): 
                                            $qty_display = '';
                                            $med_name = $med;
                                            if (preg_match('/\((\d+)\s*([^)]*)\)/', $med, $matches)) {
                                                $qty_display = ' <span class="qty">' . $matches[1] . '</span>';
                                                $med_name = trim(str_replace('(' . $matches[1] . $matches[2] . ')', '', $med));
                                            }
                                        ?>
                                            <span class="medication-item" data-med-name="<?= htmlspecialchars($med_name) ?>">
                                                <span class="med-name-text"><?= htmlspecialchars(substr($med_name, 0, 25)) . (strlen($med_name) > 25 ? '...' : '') ?></span>
                                                <?= $qty_display ?>
                                            </span>
                                        <?php endforeach; ?>
                                        <?php if (count($unique_meds) > 5): ?>
                                            <span class="medication-item" style="background:var(--primary-bg);color:var(--primary);">
                                                +<?= count($unique_meds) - 5 ?> more
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="text-right amount-cell">
                                    TSh <?= number_format($group['total_amount'] ?? 0, 0) ?>
                                </td>
                                <td class="cell-status">
                                    <?php 
                                    $status = $group['overall_status'] ?? 'pending';
                                    $color = $group['status_color'] ?? 'secondary';
                                    $label = $group['status_label'] ?? 'Unknown';
                                    ?>
                                    <span class="badge-status <?= $color ?>">
                                        <?php if ($status === 'pending'): ?>⏳<?php elseif ($status === 'confirmed'): ?>✅<?php elseif ($status === 'dispensed'): ?>💊<?php else: ?>❌<?php endif; ?>
                                        <?= $label ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <span class="font-bold" style="color:#0B5ED7;">
                                        <?= count($group['prescriptions']) ?>
                                    </span>
                                </td>
                                <td class="text-xs">
                                    <?= date('M d, Y', strtotime($group['last_prescription_date'] ?? 'now')) ?>
                                </td>
                                <td>
                                    <div class="flex flex-wrap gap-1" style="justify-content:center;">
                                        <button class="btn-view" onclick='openViewModal(<?= json_encode($group, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)' title="View All Prescriptions">
                                            <i class="fas fa-eye"></i> View
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        
                        <tr class="no-results-row" id="prescNoResults" style="display:none;">
                            <td colspan="10">
                                <i class="fas fa-search-minus"></i>
                                <p>No patients match your search</p>
                                <p style="font-size:0.75rem;margin-top:4px;color:var(--text-muted);">Try searching for patient name, ID, prescription number, or medicine name (e.g. "Albendazole")</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <tr>
                            <td colspan="10">
                                <div class="text-center py-8 text-gray-400">
                                    <i class="fas fa-prescription text-3xl block mb-2"></i>
                                    <p style="color:var(--text-primary);font-weight:500;">No prescriptions found</p>
                                    <p class="text-sm mt-1">
                                        <?php if (!empty($search)): ?>
                                            No results for "<strong><?= htmlspecialchars($search) ?></strong>"
                                        <?php elseif (!empty($date_from) || !empty($date_to)): ?>
                                            No prescriptions in this date range
                                        <?php else: ?>
                                            No prescriptions have been created yet
                                        <?php endif; ?>
                                    </p>
                                    <a href="pending_prescriptions.php" class="btn-primary mt-3">
                                        <i class="fas fa-clock"></i> Go to Pending Prescriptions
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Table Footer -->
        <div class="table-footer">
            <span>
                <i class="fas fa-list"></i> Showing <strong id="prescFooterCount"><?= count($grouped_prescriptions) ?></strong> patients
            </span>
            <span>
                <span class="count-badge <?= $filter_status === 'pending' ? 'orange' : ($filter_status === 'confirmed' ? 'blue' : ($filter_status === 'dispensed' ? 'green' : ($filter_status === 'cancelled' ? 'red' : ''))) ?>">
                    <?= count($grouped_prescriptions) ?>
                </span>
                <?= $filter_status !== 'all' ? 'Filtered: ' . ucfirst($filter_status) : 'All' ?>
            </span>
        </div>
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

<!-- VIEW PRESCRIPTIONS MODAL -->
<div class="modal-overlay" id="viewModal">
    <div class="modal-content">
        <div class="modal-header">
            <div class="modal-title">
                <i class="fas fa-prescription" style="color:#0B5ED7;"></i> 
                Prescriptions - <span id="modalPatientName"></span>
                <span style="font-size:0.7rem;font-weight:400;color:var(--text-secondary);margin-left:8px;">
                    (ID: <span id="modalPatientId"></span>)
                </span>
            </div>
            <button class="modal-close" onclick="closeModal()">&times;</button>
        </div>
        
        <div id="modalBody"></div>
        
        <div class="modal-actions">
            <button class="btn-close-modal" onclick="closeModal()">
                <i class="fas fa-times"></i> Close
            </button>
        </div>
    </div>
</div>

<script>
// SIDEBAR TOGGLE
var sidebar = document.getElementById('sidebar');
var sidebarToggle = document.getElementById('sidebarToggle');

if (sidebarToggle) {
    sidebarToggle.addEventListener('click', function() {
        if (sidebar) sidebar.classList.toggle('open');
    });
}

document.addEventListener('click', function(e) {
    if (window.innerWidth <= 1024) {
        if (sidebar && !sidebar.contains(e.target) && e.target !== sidebarToggle) {
            sidebar.classList.remove('open');
        }
    }
});

// ================================================================
// AUTO SEARCH WITH HIGHLIGHT
// ================================================================
(function() {
    var searchInput = document.getElementById('prescSearchInput');
    var tableBody = document.getElementById('prescTableBody');
    var noResultsRow = document.getElementById('prescNoResults');
    var countDisplay = document.getElementById('prescCountDisplay');
    var searchInfo = document.getElementById('prescSearchInfo');
    var searchCount = document.getElementById('prescSearchCount');
    var footerCount = document.getElementById('prescFooterCount');
    
    if (!searchInput || !tableBody) return;
    var totalRows = document.querySelectorAll('.presc-row').length;
    
    function escapeRegExp(string) {
        return string.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    }
    
    function highlightText(text, query) {
        if (!query || !text) return text;
        var escaped = escapeRegExp(query);
        var regex = new RegExp('(' + escaped + ')', 'gi');
        return text.replace(regex, '<mark class="highlight">$1</mark>');
    }
    
    function applyHighlights(row, query) {
        if (!query) return;
        
        row.querySelectorAll('.patient-name, .patient-id, .patient-phone').forEach(function(el) {
            var original = el.getAttribute('data-original') || el.textContent;
            el.setAttribute('data-original', original);
            el.innerHTML = highlightText(original, query);
        });
        
        row.querySelectorAll('.prescription-number').forEach(function(el) {
            var original = el.getAttribute('data-original') || el.textContent;
            el.setAttribute('data-original', original);
            el.innerHTML = highlightText(original, query);
        });
        
        row.querySelectorAll('.med-name-text').forEach(function(el) {
            var original = el.getAttribute('data-original') || el.textContent;
            el.setAttribute('data-original', original);
            if (original.toLowerCase().includes(query.toLowerCase())) {
                el.innerHTML = original.replace(
                    new RegExp('(' + escapeRegExp(query) + ')', 'gi'),
                    '<mark class="highlight-med">$1</mark>'
                );
            } else {
                el.innerHTML = original;
            }
        });
        
        row.querySelectorAll('.medication-item').forEach(function(item) {
            var medName = item.getAttribute('data-med-name') || '';
            if (medName.toLowerCase().includes(query.toLowerCase())) {
                item.classList.add('highlighted-med');
                item.style.background = '#FED7AA';
                item.style.borderColor = '#FDBA74';
                item.style.boxShadow = '0 0 0 2px #FED7AA';
            } else {
                item.classList.remove('highlighted-med');
                item.style.background = '';
                item.style.borderColor = '';
                item.style.boxShadow = '';
            }
        });
    }
    
    function removeAllHighlights(row) {
        row.querySelectorAll('.patient-name, .patient-id, .patient-phone, .prescription-number, .med-name-text').forEach(function(el) {
            var original = el.getAttribute('data-original');
            if (original !== null) {
                el.innerHTML = original;
            }
        });
        
        row.querySelectorAll('.medication-item').forEach(function(item) {
            item.classList.remove('highlighted-med');
            item.style.background = '';
            item.style.borderColor = '';
            item.style.boxShadow = '';
        });
        
        row.classList.remove('highlighted');
    }
    
    function filterTable() {
        var query = searchInput.value.trim();
        var queryLower = query.toLowerCase();
        var rows = document.querySelectorAll('.presc-row');
        var visibleCount = 0;
        
        rows.forEach(function(row) {
            var searchData = row.getAttribute('data-search') || '';
            
            removeAllHighlights(row);
            
            if (query === '' || searchData.includes(queryLower)) {
                row.style.display = '';
                visibleCount++;
                
                var snoCell = row.querySelector('.presc-sno');
                if (snoCell) snoCell.textContent = visibleCount;
                
                if (query !== '') {
                    applyHighlights(row, query);
                    
                    var medMatch = false;
                    row.querySelectorAll('.medication-item').forEach(function(item) {
                        if (item.classList.contains('highlighted-med')) {
                            medMatch = true;
                        }
                    });
                    if (medMatch) {
                        row.classList.add('highlighted');
                    }
                }
            } else {
                row.style.display = 'none';
            }
        });
        
        if (countDisplay) {
            countDisplay.innerHTML = query === '' 
                ? '(<strong>' + totalRows + '</strong>)' 
                : '(<strong>' + visibleCount + '</strong> of ' + totalRows + ')';
        }
        
        if (searchInfo && searchCount) {
            if (query === '') {
                searchInfo.classList.remove('show');
            } else {
                searchInfo.classList.add('show');
                searchCount.textContent = visibleCount;
            }
        }
        
        if (footerCount) {
            footerCount.textContent = visibleCount;
        }
        
        if (noResultsRow) {
            noResultsRow.style.display = (visibleCount === 0 && query !== '') ? '' : 'none';
        }
        
        setTimeout(updatePrescScrollButtons, 100);
    }
    
    searchInput.addEventListener('input', filterTable);
    searchInput.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            this.value = '';
            filterTable();
            this.blur();
        }
    });
})();

// ================================================================
// TABLE SCROLL
// ================================================================
var scrollAmount = 400;

function scrollPrescTable(direction) {
    var wrap = document.getElementById('prescTableWrapper');
    if (wrap) {
        wrap.scrollBy({ 
            left: direction === 'left' ? -scrollAmount : scrollAmount, 
            behavior: 'smooth' 
        });
    }
}

function updatePrescScrollButtons() {
    var wrap = document.getElementById('prescTableWrapper');
    var btnLeft = document.getElementById('prescScrollBtnLeft');
    var btnRight = document.getElementById('prescScrollBtnRight');
    if (!wrap || !btnLeft || !btnRight) return;
    
    var scrollLeft = wrap.scrollLeft;
    var maxScroll = wrap.scrollWidth - wrap.clientWidth;
    
    btnLeft.disabled = (scrollLeft <= 5);
    btnRight.disabled = (scrollLeft >= maxScroll - 5 || maxScroll <= 0);
}

document.addEventListener('DOMContentLoaded', function() {
    var wrap = document.getElementById('prescTableWrapper');
    if (wrap) {
        wrap.addEventListener('scroll', updatePrescScrollButtons);
        setTimeout(updatePrescScrollButtons, 200);
    }
    
    window.addEventListener('resize', function() {
        setTimeout(updatePrescScrollButtons, 200);
    });
});

// ================================================================
// VIEW MODAL
// ================================================================
function openViewModal(data) {
    var modal = document.getElementById('viewModal');
    if (!modal) return;
    
    document.getElementById('modalPatientName').textContent = data.patient_name || 'Unknown';
    document.getElementById('modalPatientId').textContent = data.patient_number || 'N/A';
    
    var currentQuery = document.getElementById('prescSearchInput').value.trim();
    
    var html = '';
    
    html += `
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:16px;padding:12px;background:var(--bg-body);border-radius:8px;border:1px solid var(--border-color);">
            <div>
                <div style="font-size:0.55rem;text-transform:uppercase;color:var(--text-secondary);font-weight:600;">Patient</div>
                <div style="font-size:0.9rem;font-weight:600;">${escapeHtml(data.patient_name || 'Unknown')}</div>
            </div>
            <div>
                <div style="font-size:0.55rem;text-transform:uppercase;color:var(--text-secondary);font-weight:600;">Patient ID</div>
                <div style="font-size:0.9rem;font-weight:600;font-family:monospace;">${escapeHtml(data.patient_number || 'N/A')}</div>
            </div>
            <div>
                <div style="font-size:0.55rem;text-transform:uppercase;color:var(--text-secondary);font-weight:600;">Phone</div>
                <div style="font-size:0.9rem;">${escapeHtml(data.patient_phone || 'N/A')}</div>
            </div>
            <div>
                <div style="font-size:0.55rem;text-transform:uppercase;color:var(--text-secondary);font-weight:600;">Gender</div>
                <div style="font-size:0.9rem;">${escapeHtml(data.patient_gender || 'N/A')}</div>
            </div>
            <div>
                <div style="font-size:0.55rem;text-transform:uppercase;color:var(--text-secondary);font-weight:600;">Total Amount</div>
                <div style="font-size:0.9rem;font-weight:700;color:#0B5ED7;">TSh ${Number(data.total_amount || 0).toLocaleString()}</div>
            </div>
            <div>
                <div style="font-size:0.55rem;text-transform:uppercase;color:var(--text-secondary);font-weight:600;">Prescriptions</div>
                <div style="font-size:0.9rem;font-weight:600;">${data.prescription_count || 0}</div>
            </div>
        </div>
    `;
    
    if (data.prescriptions && data.prescriptions.length > 0) {
        html += `<div style="font-size:0.8rem;font-weight:600;margin-bottom:8px;color:var(--text-primary);">
            <i class="fas fa-list" style="color:#0B5ED7;"></i> Prescriptions (${data.prescriptions.length})
        </div>`;
        
        var statusColors = {
            'pending': 'warning',
            'confirmed': 'info',
            'dispensed': 'success',
            'cancelled': 'danger'
        };
        var statusLabels = {
            'pending': 'Pending',
            'confirmed': 'Confirmed',
            'dispensed': 'Dispensed',
            'cancelled': 'Cancelled'
        };
        
        data.prescriptions.forEach(function(prescription, index) {
            var color = statusColors[prescription.status] || 'secondary';
            var label = statusLabels[prescription.status] || prescription.status;
            
            var medsHtml = '';
            if (prescription.medications && prescription.medications !== 'No medications') {
                var meds = prescription.medications.split(', ');
                meds.forEach(function(med) {
                    var display = escapeHtml(med);
                    var qtyMatch = med.match(/\((\d+)\s*pcs\)/);
                    if (qtyMatch) {
                        var name = med.replace(/\(\d+\s*pcs\)/, '').trim();
                        var escapedName = escapeHtml(name);
                        var escapedQty = qtyMatch[1];
                        
                        if (currentQuery && name.toLowerCase().includes(currentQuery.toLowerCase())) {
                            var regex = new RegExp('(' + escapeRegExp(currentQuery) + ')', 'gi');
                            escapedName = escapedName.replace(regex, '<mark class="highlight-med">$1</mark>');
                        }
                        
                        display = escapedName + ' <span style="font-weight:700;color:#0B5ED7;font-size:0.7rem;">(' + escapedQty + ' pcs)</span>';
                    } else {
                        if (currentQuery && med.toLowerCase().includes(currentQuery.toLowerCase())) {
                            var regex = new RegExp('(' + escapeRegExp(currentQuery) + ')', 'gi');
                            display = display.replace(regex, '<mark class="highlight-med">$1</mark>');
                        }
                    }
                    medsHtml += '<span class="item">' + display + '</span>';
                });
            } else {
                medsHtml = '<span style="color:var(--text-muted);font-size:0.75rem;">No medications</span>';
            }
            
            html += `
                <div class="prescription-detail-card" style="border-left-color: ${prescription.status === 'pending' ? '#D97706' : (prescription.status === 'confirmed' ? '#0B5ED7' : (prescription.status === 'dispensed' ? '#059669' : '#EF4444'))};">
                    <div class="pd-header">
                        <div>
                            <span class="pd-number">#${index + 1}: ${escapeHtml(prescription.number)}</span>
                            <span class="badge-status ${color}" style="margin-left:8px;">${label}</span>
                        </div>
                        <div style="font-weight:700;color:#0B5ED7;font-size:0.85rem;">
                            TSh ${Number(prescription.amount || 0).toLocaleString()}
                        </div>
                    </div>
                    <div class="pd-items">
                        <strong style="font-size:0.7rem;color:var(--text-secondary);">Medications:</strong>
                        ${medsHtml}
                    </div>
                </div>
            `;
        });
    } else {
        html += `<div style="text-align:center;padding:20px;color:var(--text-secondary);">
            <i class="fas fa-prescription" style="font-size:1.5rem;display:block;margin-bottom:8px;"></i>
            <p>No prescriptions found for this patient</p>
        </div>`;
    }
    
    document.getElementById('modalBody').innerHTML = html;
    modal.classList.add('show');
    document.body.style.overflow = 'hidden';
}

function closeModal() {
    var modal = document.getElementById('viewModal');
    if (modal) {
        modal.classList.remove('show');
        document.body.style.overflow = 'auto';
    }
}

document.getElementById('viewModal')?.addEventListener('click', function(e) {
    if (e.target === this) closeModal();
});

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeModal();
});

function escapeHtml(text) {
    if (!text) return '';
    var div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function escapeRegExp(string) {
    return string.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

// FOOTER TIME
function updateFooterTime() {
    var now = new Date();
    var timeStr = now.toLocaleTimeString('en-US', {
        hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true
    });
    var ftEl = document.getElementById('footerTimestamp');
    if (ftEl) ftEl.textContent = 'Last updated: ' + timeStr;
}
updateFooterTime();
setInterval(updateFooterTime, 1000);

console.log('%c📋 Braick - Prescription History', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
console.log('%c✅ Stat cards PUNGUFU (smaller)', 'font-size:13px; color:#0B5ED7; font-weight:bold;');
console.log('%c✅ Filters ZOTE kwenye ROW MOJA', 'font-size:13px; color:#059669; font-weight:bold;');
console.log('%c✅ Search bar ndogo (220-300px)', 'font-size:13px; color:#0B5ED7;');
console.log('%c✨ HIGHLIGHT: Search medicine name', 'font-size:13px; color:#D97706;');
console.log('%c👥 Total Patients: <?= count($grouped_prescriptions) ?>', 'font-size:13px; color:#059669;');
</script>

</body>
</html>