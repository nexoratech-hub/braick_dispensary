<?php
// ================================================================
// FILE: frontend/pages/pharmacy/prescription_history.php
// PHARMACY - PRESCRIPTION HISTORY (GROUPED BY PATIENT)
// Shows all prescriptions grouped by patient - one row per patient
// ================================================================

session_start();

// ================================================================
// CHECK SESSION - REDIRECT TO LOGIN IF NOT PHARMACY
// ================================================================
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'pharmacy') {
    header('Location: ../login.php');
    exit;
}

// ================================================================
// GET USER DATA FROM SESSION
// ================================================================
$user_id = $_SESSION['user_id'];
$user_full_name = $_SESSION['full_name'] ?? 'Pharmacy Staff';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Branch';
$user_username = $_SESSION['username'] ?? 'pharmacy';
$profile_pic = $_SESSION['profile_pic'] ?? '';

// ================================================================
// DATABASE CONNECTION
// ================================================================
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

// Show all statuses
if ($filter_status === 'all') {
    $conditions[] = "p.status IN ('pending', 'confirmed', 'dispensed', 'cancelled')";
} else {
    $conditions[] = "p.status = ?";
    $params[] = $filter_status;
}

if (!empty($search)) {
    $conditions[] = "(pat.full_name LIKE ? OR pat.patient_id LIKE ? OR p.prescription_number LIKE ?)";
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

// Get total count - DISTINCT patients
$count_sql = "
    SELECT COUNT(DISTINCT pat.id) as total 
    FROM prescriptions p
    LEFT JOIN patients pat ON p.patient_id = pat.id
    WHERE $where_clause
";
$stmt = $db->prepare($count_sql);
$stmt->execute($params);
$total_patients = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// Get prescriptions grouped by patient
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
    
    // Determine overall status color
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
// GET STATISTICS - GROUPED BY PATIENT
// ================================================================

$stats_where = " WHERE 1=1";
$stats_params = [];

if ($user_branch_id) {
    $stats_where .= " AND p.branch_id = ?";
    $stats_params[] = $user_branch_id;
}

// Total patients with prescriptions
$stmt = $db->prepare("SELECT COUNT(DISTINCT patient_id) as total FROM prescriptions p $stats_where");
$stmt->execute($stats_params);
$total_all = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// Pending patients
$pending_where = $stats_where . " AND p.status = 'pending'";
$stmt = $db->prepare("SELECT COUNT(DISTINCT patient_id) as total FROM prescriptions p $pending_where");
$stmt->execute($stats_params);
$pending_count = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// Confirmed patients
$confirmed_where = $stats_where . " AND p.status = 'confirmed'";
$stmt = $db->prepare("SELECT COUNT(DISTINCT patient_id) as total FROM prescriptions p $confirmed_where");
$stmt->execute($stats_params);
$confirmed_count = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// Dispensed patients
$dispensed_where = $stats_where . " AND p.status = 'dispensed'";
$stmt = $db->prepare("SELECT COUNT(DISTINCT patient_id) as total FROM prescriptions p $dispensed_where");
$stmt->execute($stats_params);
$dispensed_count = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// Cancelled patients
$cancelled_where = $stats_where . " AND p.status = 'cancelled'";
$stmt = $db->prepare("SELECT COUNT(DISTINCT patient_id) as total FROM prescriptions p $cancelled_where");
$stmt->execute($stats_params);
$cancelled_count = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// Total amount from all prescriptions (dispensed)
$amount_sql = "
    SELECT COALESCE(SUM(pi.total_price), 0) as total_amount 
    FROM prescription_items pi
    INNER JOIN prescriptions p ON pi.prescription_id = p.id
    $stats_where AND p.status = 'dispensed'
";
$stmt = $db->prepare($amount_sql);
$stmt->execute($stats_params);
$total_amount_all = $stmt->fetch(PDO::FETCH_ASSOC)['total_amount'] ?? 0;

// ================================================================
// HELPERS
// ================================================================
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

function formatDate($datetime) {
    if (empty($datetime)) return 'N/A';
    return date('d/m/Y h:i A', strtotime($datetime));
}

function formatMoney($amount) {
    if ($amount === null || $amount === '') {
        return '0.00';
    }
    return number_format((float)$amount, 2, '.', ',');
}

// ================================================================
// PROFILE PICTURE URL
// ================================================================
$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

// ================================================================
// INCLUDE SHARED HEADER & SIDEBAR
// ================================================================
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
            padding: 24px 32px;
            margin-bottom: 28px;
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
            font-size: 1.8rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
        }
        
        .page-header .page-title i {
            font-size: 2rem;
            opacity: 0.9;
        }
        
        .page-header .page-subtitle {
            color: rgba(255,255,255,0.85);
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
        }
        
        .page-header .page-subtitle strong {
            color: white;
            font-weight: 600;
        }
        
        .page-header .badge-display {
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 4px 14px;
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
            padding: 8px 18px;
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
            box-shadow: 0 4px 16px rgba(0,0,0,0.15);
        }
        
        /* STATS ROW */
        .stats-row {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 24px;
        }
        
        .stat-card {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            padding: 20px 24px;
            border: 2px solid var(--border-color);
            transition: var(--transition);
            text-align: center;
        }
        
        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-md);
        }
        
        .stat-card .stat-icon {
            font-size: 2rem;
            display: block;
            margin-bottom: 4px;
        }
        
        .stat-card .stat-number {
            font-size: 2.2rem;
            font-weight: 700;
            line-height: 1.2;
        }
        
        .stat-card .stat-number.green { color: #059669; }
        .stat-card .stat-number.red { color: #DC2626; }
        .stat-card .stat-number.orange { color: #D97706; }
        .stat-card .stat-number.blue { color: #0B5ED7; }
        
        .stat-card .stat-label {
            font-size: 0.75rem;
            color: var(--text-secondary);
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-top: 2px;
        }
        
        .stat-card .stat-sub {
            font-size: 0.6rem;
            color: var(--text-muted);
            margin-top: 2px;
        }
        
        .stat-card.pending {
            border-color: #D97706;
            background: #FEF3C7;
        }
        .stat-card.confirmed {
            border-color: #0B5ED7;
            background: #E8F0FE;
        }
        .stat-card.dispensed {
            border-color: #059669;
            background: #D1FAE5;
        }
        .stat-card.cancelled {
            border-color: #DC2626;
            background: #FEE2E2;
        }
        
        [data-theme="dark"] .stat-card.pending {
            background: #3D2E0A;
            border-color: #FBBF24;
        }
        [data-theme="dark"] .stat-card.pending .stat-number { color: #FBBF24; }
        [data-theme="dark"] .stat-card.confirmed {
            background: #1A2A4A;
            border-color: #6EA8FE;
        }
        [data-theme="dark"] .stat-card.confirmed .stat-number { color: #6EA8FE; }
        [data-theme="dark"] .stat-card.dispensed {
            background: #1A3A2A;
            border-color: #34D399;
        }
        [data-theme="dark"] .stat-card.dispensed .stat-number { color: #34D399; }
        [data-theme="dark"] .stat-card.cancelled {
            background: #3A1A1A;
            border-color: #F87171;
        }
        [data-theme="dark"] .stat-card.cancelled .stat-number { color: #F87171; }
        
        /* FILTER SECTION */
        .filter-section {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            padding: 16px 20px;
            border: 1px solid var(--border-color);
            margin-bottom: 24px;
        }
        
        .filter-row {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
        }
        
        .filter-btn {
            padding: 5px 14px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
            border: 2px solid var(--border-color);
            background: transparent;
            color: var(--text-secondary);
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
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
            padding: 7px 12px;
            border: 2px solid var(--border-color);
            border-radius: var(--radius);
            font-size: 0.8rem;
            background: var(--bg-card);
            color: var(--text-primary);
            outline: none;
            transition: var(--transition);
        }
        
        .filter-input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.12);
        }
        
        .filter-input[type="date"] {
            width: 150px;
        }
        
        .btn-search {
            padding: 7px 18px;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: var(--radius);
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
        }
        
        .btn-search:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
        }
        
        /* TABLE */
        .table-container {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            border: 1px solid var(--border-color);
            overflow: hidden;
            box-shadow: var(--shadow);
        }
        
        .table-scroll {
            overflow-x: auto;
            overflow-y: visible;
            scroll-behavior: smooth;
            -webkit-overflow-scrolling: touch;
            max-width: 100%;
        }
        
        .table-scroll::-webkit-scrollbar { height: 6px; }
        .table-scroll::-webkit-scrollbar-track { background: var(--bg-body); border-radius: 10px; }
        .table-scroll::-webkit-scrollbar-thumb { background: #0B5ED7; border-radius: 10px; }
        
        .scroll-controls {
            display: flex;
            gap: 6px;
            align-items: center;
            flex-shrink: 0;
        }
        
        .scroll-btn {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            border: 2px solid var(--border-color);
            background: var(--bg-card);
            color: var(--text-secondary);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            font-size: 0.7rem;
        }
        
        .scroll-btn:hover {
            border-color: #0B5ED7;
            color: #0B5ED7;
            background: #E8F0FE;
            transform: scale(1.05);
        }
        
        .data-table {
            width: 100%;
            min-width: 1000px;
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
        
        .data-table thead th i {
            margin-right: 5px;
            opacity: 0.7;
        }
        
        .data-table tbody td {
            padding: 8px 14px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-primary);
            vertical-align: middle;
        }
        
        .data-table tbody tr:hover td {
            background: var(--primary-bg);
        }
        
        .data-table tbody tr:last-child td {
            border-bottom: none;
        }
        
        .data-table tbody tr:nth-child(even) td {
            background: var(--gray-50);
        }
        
        [data-theme="dark"] .data-table tbody tr:nth-child(even) td {
            background: #1A1A2E;
        }
        
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
        
        @media (max-width: 1024px) {
            .main-content { margin-left: 0; padding: 16px; }
            .stats-row { grid-template-columns: repeat(2, 1fr); }
        }
        
        @media (max-width: 768px) {
            .page-header { padding: 16px 18px; }
            .page-header .page-title { font-size: 1.3rem; }
            .filter-row { flex-direction: column; align-items: stretch; }
            .filter-input { width: 100%; }
            .filter-input[type="date"] { width: 100%; }
            .stats-row { grid-template-columns: repeat(2, 1fr); }
            .data-table { font-size: 0.7rem; min-width: 750px; }
            .data-table thead th, .data-table tbody td { padding: 5px 8px; }
            .col-medications { min-width: 120px; }
            .col-prescriptions { min-width: 120px; }
            .scroll-controls { margin-top: 4px; }
            .modal-content { padding: 16px; }
        }
        
        @media (max-width: 480px) {
            .main-content { padding: 10px; }
            .stats-row { grid-template-columns: 1fr 1fr; gap: 10px; }
            .stat-card { padding: 14px 16px; }
            .stat-card .stat-number { font-size: 1.6rem; }
            .page-title { font-size: 1.1rem; }
            .data-table { font-size: 0.65rem; min-width: 650px; }
        }
        
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-fade-in-up { animation: fadeInUp 0.5s ease forwards; opacity: 0; }
    </style>
</head>
<body>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <!-- ================================================================ -->
    <!-- PAGE HEADER -->
    <!-- ================================================================ -->
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-history"></i>
                Prescription History
                <span class="badge-display"><?= count($grouped_prescriptions) ?> Patients</span>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-users"></i>
                Grouped by patient - <strong><?= count($grouped_prescriptions) ?></strong> patients with prescriptions
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
    <!-- STATS ROW -->
    <!-- ================================================================ -->
    <div class="stats-row animate-fade-in-up">
        <div class="stat-card pending">
            <span class="stat-icon">⏳</span>
            <p class="stat-number orange"><?= $pending_count ?></p>
            <p class="stat-label">Pending</p>
            <p class="stat-sub">Patients with pending</p>
        </div>
        <div class="stat-card confirmed">
            <span class="stat-icon">✅</span>
            <p class="stat-number blue"><?= $confirmed_count ?></p>
            <p class="stat-label">Confirmed</p>
            <p class="stat-sub">Patients with confirmed</p>
        </div>
        <div class="stat-card dispensed">
            <span class="stat-icon">💊</span>
            <p class="stat-number green"><?= $dispensed_count ?></p>
            <p class="stat-label">Dispensed</p>
            <p class="stat-sub">Patients with dispensed</p>
        </div>
        <div class="stat-card cancelled">
            <span class="stat-icon">❌</span>
            <p class="stat-number red"><?= $cancelled_count ?></p>
            <p class="stat-label">Cancelled</p>
            <p class="stat-sub">Patients with cancelled</p>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- FILTERS -->
    <!-- ================================================================ -->
    <div class="filter-section animate-fade-in-up" style="animation-delay:0.05s;">
        <div class="filter-row">
            <a href="?status=all" class="filter-btn <?= $filter_status === 'all' ? 'active' : '' ?>">📋 All</a>
            <a href="?status=pending" class="filter-btn <?= $filter_status === 'pending' ? 'active' : '' ?>">⏳ Pending</a>
            <a href="?status=confirmed" class="filter-btn <?= $filter_status === 'confirmed' ? 'active' : '' ?>">✅ Confirmed</a>
            <a href="?status=dispensed" class="filter-btn <?= $filter_status === 'dispensed' ? 'active' : '' ?>">💊 Dispensed</a>
            <a href="?status=cancelled" class="filter-btn <?= $filter_status === 'cancelled' ? 'active' : '' ?>">❌ Cancelled</a>
            
            <div style="flex:1;"></div>
            
            <form method="GET" class="filter-row" style="flex:1;gap:8px;">
                <input type="hidden" name="status" value="<?= htmlspecialchars($filter_status) ?>">
                <input type="text" name="search" class="filter-input" placeholder="Search patient or prescription..." value="<?= htmlspecialchars($search) ?>" style="flex:1;min-width:150px;">
                <input type="date" name="date_from" class="filter-input" value="<?= htmlspecialchars($date_from) ?>" placeholder="From">
                <input type="date" name="date_to" class="filter-input" value="<?= htmlspecialchars($date_to) ?>" placeholder="To">
                <button type="submit" class="btn-search">
                    <i class="fas fa-search"></i> Filter
                </button>
                <?php if (!empty($search) || !empty($date_from) || !empty($date_to)): ?>
                    <a href="prescription_history.php" class="btn-outline" style="border-color:#EF4444;color:#EF4444;padding:6px 12px;font-size:0.7rem;">
                        <i class="fas fa-times"></i> Clear
                    </a>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- TABLE - GROUPED BY PATIENT -->
    <!-- ================================================================ -->
    <div class="table-container animate-fade-in-up" style="animation-delay:0.1s;">
        <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;padding:12px 16px;border-bottom:1px solid var(--border-color);flex-wrap:wrap;gap:8px;">
            <h3 style="font-size:0.9rem;font-weight:600;color:var(--text-primary);">
                <i class="fas fa-users" style="color:#0B5ED7;"></i> 
                Patients with Prescriptions
                <span style="font-size:0.7rem;font-weight:400;color:var(--text-muted);">(<?= $total_patients ?> patients)</span>
            </h3>
            <div class="scroll-controls">
                <button class="scroll-btn" onclick="scrollTable('left')" title="Scroll Left" id="scrollLeftBtn">
                    <i class="fas fa-chevron-left"></i>
                </button>
                <button class="scroll-btn" onclick="scrollTable('right')" title="Scroll Right" id="scrollRightBtn">
                    <i class="fas fa-chevron-right"></i>
                </button>
            </div>
        </div>
        
        <div class="table-scroll" id="tableScrollWrapper">
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
                <tbody>
                    <?php if (count($grouped_prescriptions) > 0): ?>
                        <?php $i = 1; foreach ($grouped_prescriptions as $group): ?>
                            <tr>
                                <td class="font-bold" style="color:#0B5ED7;"><?= $i++ ?></td>
                                <td>
                                    <div class="font-semibold"><?= htmlspecialchars($group['patient_name'] ?? 'Unknown') ?></div>
                                    <div class="text-xs text-gray-400"><?= htmlspecialchars($group['patient_gender'] ?? 'N/A') ?></div>
                                    <?php if (!empty($group['date_of_birth'])): ?>
                                        <div class="text-xs text-gray-400">🎂 <?= date('d/m/Y', strtotime($group['date_of_birth'])) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="font-mono text-xs font-bold"><?= htmlspecialchars($group['patient_number'] ?? 'N/A') ?></div>
                                    <div class="text-xs text-gray-400"><?= htmlspecialchars($group['patient_phone'] ?? '') ?></div>
                                </td>
                                <td>
                                    <?php 
                                    $status_colors = ['pending' => 'pending', 'confirmed' => 'confirmed', 'dispensed' => 'dispensed', 'cancelled' => 'cancelled'];
                                    foreach (array_slice($group['prescriptions'], 0, 3) as $prescription): 
                                        $color = $status_colors[$prescription['status']] ?? 'secondary';
                                    ?>
                                        <span class="prescription-tag">
                                            <span class="status-dot <?= $color ?>"></span>
                                            <?= htmlspecialchars($prescription['number']) ?>
                                        </span>
                                    <?php endforeach; ?>
                                    <?php if (count($group['prescriptions']) > 3): ?>
                                        <span class="prescription-tag" style="background:var(--primary-bg);color:var(--primary);">
                                            +<?= count($group['prescriptions']) - 3 ?> more
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="medication-list">
                                        <?php 
                                        $meds_shown = 0;
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
                                            if (preg_match('/\((\d+)\s*([^)]*)\)/', $med, $matches)) {
                                                $qty_display = ' <span class="qty">' . $matches[1] . '</span>';
                                                $med_name = trim(str_replace('(' . $matches[1] . $matches[2] . ')', '', $med));
                                            } else {
                                                $med_name = $med;
                                            }
                                        ?>
                                            <span class="medication-item">
                                                <?= htmlspecialchars(substr($med_name, 0, 25)) . (strlen($med_name) > 25 ? '...' : '') ?>
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
                                <td>
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
                                        <button class="btn-view" onclick="openViewModal(<?= htmlspecialchars(json_encode($group)) ?>)" title="View All Prescriptions">
                                            <i class="fas fa-eye"></i> View
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
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
                <i class="fas fa-list"></i> Showing <strong><?= count($grouped_prescriptions) ?></strong> patients
            </span>
            <span>
                <span class="count-badge <?= $filter_status === 'pending' ? 'orange' : ($filter_status === 'confirmed' ? 'blue' : ($filter_status === 'dispensed' ? 'green' : ($filter_status === 'cancelled' ? 'red' : ''))) ?>">
                    <?= count($grouped_prescriptions) ?>
                </span>
                <?= $filter_status !== 'all' ? 'Filtered: ' . ucfirst($filter_status) : 'All' ?>
            </span>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
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

<!-- ================================================================ -->
<!-- VIEW PRESCRIPTIONS MODAL -->
<!-- ================================================================ -->
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

<!-- ================================================================ -->
<!-- JAVASCRIPT -->
<!-- ================================================================ -->
<script>
    // ================================================================
    // SIDEBAR TOGGLE
    // ================================================================
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
    // TABLE SCROLL
    // ================================================================
    function scrollTable(direction) {
        var container = document.getElementById('tableScrollWrapper');
        if (!container) return;
        var scrollAmount = 300;
        if (direction === 'left') {
            container.scrollLeft -= scrollAmount;
        } else {
            container.scrollLeft += scrollAmount;
        }
    }

    // ================================================================
    // VIEW MODAL - SHOW ALL PRESCRIPTIONS WITH ITEMS & QUANTITY
    // ================================================================
    function openViewModal(data) {
        var modal = document.getElementById('viewModal');
        if (!modal) return;
        
        document.getElementById('modalPatientName').textContent = data.patient_name || 'Unknown';
        document.getElementById('modalPatientId').textContent = data.patient_number || 'N/A';
        
        var html = '';
        
        // Patient info
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
        
        // Prescriptions list with items
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
                            ${prescription.medications && prescription.medications !== 'No medications' ? 
                                prescription.medications.split(', ').map(function(med) {
                                    var display = med;
                                    var qtyMatch = med.match(/\((\d+)\s*pcs\)/);
                                    if (qtyMatch) {
                                        var name = med.replace(/\(\d+\s*pcs\)/, '').trim();
                                        display = name + ' <span style="font-weight:700;color:#0B5ED7;font-size:0.7rem;">(' + qtyMatch[1] + ' pcs)</span>';
                                    }
                                    return '<span class="item">' + display + '</span>';
                                }).join('') 
                            : '<span style="color:var(--text-muted);font-size:0.75rem;">No medications</span>'}
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

    // ================================================================
    // CLOSE MODAL
    // ================================================================
    function closeModal() {
        var modal = document.getElementById('viewModal');
        if (modal) {
            modal.classList.remove('show');
            document.body.style.overflow = 'auto';
        }
    }
    
    // Close modal on outside click
    document.getElementById('viewModal')?.addEventListener('click', function(e) {
        if (e.target === this) {
            closeModal();
        }
    });
    
    // Close modal on escape
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeModal();
        }
    });

    // ================================================================
    // ESCAPE HTML
    // ================================================================
    function escapeHtml(text) {
        if (!text) return '';
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // ================================================================
    // FOOTER TIME
    // ================================================================
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

    // ================================================================
    // CONSOLE
    // ================================================================
    console.log('%c📋 Braick - Prescription History (Grouped by Patient)', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c👥 Total Patients: <?= $total_patients ?>', 'font-size:13px; color:#059669;');
    console.log('%c✅ Grouped by patient - one row per patient', 'font-size:13px; color:#34D399;');
    console.log('%c✅ Medications displayed with quantities (pcs unit)', 'font-size:13px; color:#34D399;');
    console.log('%c✅ View modal shows all prescriptions with items and quantities', 'font-size:13px; color:#34D399;');
    console.log('%c✅ Scroll buttons in header for table scrolling', 'font-size:13px; color:#34D399;');
    console.log('%c⏳ Pending: <?= $pending_count ?> | ✅ Confirmed: <?= $confirmed_count ?> | 💊 Dispensed: <?= $dispensed_count ?> | ❌ Cancelled: <?= $cancelled_count ?>', 'font-size:13px; color:#D97706;');
</script>

</body>
</html>