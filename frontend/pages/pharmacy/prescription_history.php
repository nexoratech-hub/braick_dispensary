<?php
// ================================================================
// FILE: frontend/pages/pharmacy/prescription_history.php
// PHARMACY - PRESCRIPTION HISTORY (USING NEW DATABASE)
// Shows all dispensed and cancelled prescriptions
// TABLE: Blue theme | Dispensed: Green | Cancelled: Red
// NO VIEW BILL BUTTON
// USES: dispensary_db (NEW DATABASE)
// BRAICK DISPENSARY
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
// DATABASE CONNECTION - NEW DATABASE
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
// BUILD QUERY FOR PRESCRIPTION HISTORY - NEW DATABASE
// ================================================================
$conditions = ["p.branch_id = ?"];
$params = [$user_branch_id];

// Show dispensed and cancelled only
if ($filter_status === 'all') {
    $conditions[] = "p.status IN ('dispensed', 'cancelled')";
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
    $conditions[] = "DATE(p.dispensed_at) >= ?";
    $params[] = $date_from;
}

if (!empty($date_to)) {
    $conditions[] = "DATE(p.dispensed_at) <= ?";
    $params[] = $date_to;
}

$where_clause = implode(" AND ", $conditions);

// ================================================================
// GET PRESCRIPTIONS FROM NEW DATABASE
// ================================================================
$sql = "
    SELECT 
        p.*,
        pat.full_name as patient_name,
        pat.patient_id as patient_code,
        pat.phone,
        pat.email,
        pat.gender,
        pat.date_of_birth,
        u.full_name as doctor_name,
        u.specialty,
        v.visit_number,
        v.visit_type,
        ph.full_name as pharmacy_name,
        -- Get bill information from bills table
        b.id as bill_id,
        b.bill_number,
        b.total_amount as bill_total,
        b.discount_amount as bill_discount,
        b.balance as bill_balance,
        b.status as bill_status,
        -- Get total from bill_items
        (SELECT SUM(total_price) FROM bill_items WHERE bill_id = b.id AND status = 'paid') as paid_amount
    FROM prescriptions p
    JOIN patients pat ON p.patient_id = pat.id
    LEFT JOIN users u ON p.doctor_id = u.id
    LEFT JOIN visits v ON p.visit_id = v.id
    LEFT JOIN users ph ON p.pharmacy_id = ph.id
    LEFT JOIN bills b ON b.visit_id = p.visit_id AND b.patient_id = p.patient_id
    WHERE $where_clause
    GROUP BY p.id
    ORDER BY 
        CASE 
            WHEN p.status = 'dispensed' THEN 0 
            WHEN p.status = 'cancelled' THEN 1 
            ELSE 2 
        END,
        p.dispensed_at DESC,
        p.updated_at DESC
";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$prescriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// GET STATISTICS - Only Dispensed and Cancelled
// ================================================================
$total_dispensed = 0;
$total_cancelled = 0;

foreach ($prescriptions as $pres) {
    if ($pres['status'] === 'dispensed') {
        $total_dispensed++;
    } elseif ($pres['status'] === 'cancelled') {
        $total_cancelled++;
    }
}

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

function getBillStatusLabel($status) {
    $map = [
        'pending' => '⏳ Pending',
        'partial' => '🔶 Partial',
        'paid' => '✅ Paid',
        'cancelled' => '❌ Cancelled'
    ];
    return $map[$status] ?? ucfirst($status);
}

function getBillStatusBadgeClass($status) {
    $map = [
        'pending' => 'badge-warning',
        'partial' => 'badge-warning',
        'paid' => 'badge-success',
        'cancelled' => 'badge-danger'
    ];
    return $map[$status] ?? 'badge-warning';
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
        
        /* ================================================================
           MAIN CONTENT
           ================================================================ */
        .main-content {
            margin-left: 270px;
            margin-top: 68px;
            padding: 28px 32px;
            min-height: calc(100vh - 68px);
        }
        
        /* ================================================================
           PAGE HEADER - BLUE THEME
           ================================================================ */
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
        
        /* ================================================================
           STATS ROW
           ================================================================ */
        .stats-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            margin-bottom: 24px;
            max-width: 500px;
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
        
        .stat-card.dispensed {
            border-color: #059669;
            background: #D1FAE5;
        }
        
        .stat-card.dispensed .stat-number { color: #059669; }
        
        .stat-card.cancelled {
            border-color: #DC2626;
            background: #FEE2E2;
        }
        
        .stat-card.cancelled .stat-number { color: #DC2626; }
        
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
        
        /* ================================================================
           FILTER SECTION
           ================================================================ */
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
        
        /* ================================================================
           TABLE - BLUE THEME
           ================================================================ */
        .table-container {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            border: 1px solid var(--border-color);
            overflow: hidden;
            box-shadow: var(--shadow);
        }
        
        .table-scroll {
            overflow-x: auto;
        }
        
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
        
        .btn-sm {
            padding: 3px 8px;
            font-size: 0.6rem;
            border-radius: 4px;
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
        
        /* ================================================================
           TABLE FOOTER
           ================================================================ */
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
        
        /* ================================================================
           EMPTY STATE
           ================================================================ */
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
        .text-gray-400 { color: var(--text-muted); }
        
        .flex { display: flex; }
        .flex-wrap { flex-wrap: wrap; }
        .gap-1 { gap: 4px; }
        .gap-2 { gap: 8px; }
        .items-center { align-items: center; }
        
        .ml-2 { margin-left: 8px; }
        
        /* ================================================================
           FOOTER
           ================================================================ */
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
        
        /* ================================================================
           RESPONSIVE
           ================================================================ */
        @media (max-width: 1024px) {
            .main-content { margin-left: 0; padding: 16px; }
        }
        
        @media (max-width: 768px) {
            .page-header { padding: 16px 18px; }
            .page-header .page-title { font-size: 1.3rem; }
            .filter-row { flex-direction: column; align-items: stretch; }
            .filter-input { width: 100%; }
            .filter-input[type="date"] { width: 100%; }
            .stats-row { max-width: 100%; }
            .data-table { font-size: 0.7rem; }
            .data-table thead th, .data-table tbody td { padding: 5px 8px; }
        }
        
        @media (max-width: 480px) {
            .main-content { padding: 10px; }
            .stats-row { grid-template-columns: 1fr 1fr; gap: 10px; }
            .stat-card { padding: 14px 16px; }
            .stat-card .stat-number { font-size: 1.6rem; }
            .page-title { font-size: 1.1rem; }
        }
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
                <span class="badge-display"><?= count($prescriptions) ?> Records</span>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-clock"></i>
                All dispensed and cancelled prescriptions in <strong><?= htmlspecialchars($user_branch_name) ?></strong>
                <span class="text-xs" style="color:rgba(255,255,255,0.5);margin-left:8px;">
                    <i class="fas fa-info-circle"></i> History of all completed prescriptions
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
    <!-- STATS ROW - Green for Dispensed, Red for Cancelled -->
    <!-- ================================================================ -->
    <div class="stats-row">
        <div class="stat-card dispensed">
            <span class="stat-icon">💊</span>
            <p class="stat-number green"><?= $total_dispensed ?></p>
            <p class="stat-label">Dispensed</p>
            <p class="stat-sub">Prescriptions dispensed</p>
        </div>
        <div class="stat-card cancelled">
            <span class="stat-icon">❌</span>
            <p class="stat-number red"><?= $total_cancelled ?></p>
            <p class="stat-label">Cancelled</p>
            <p class="stat-sub">Prescriptions cancelled</p>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- FILTERS - BLUE THEME -->
    <!-- ================================================================ -->
    <div class="filter-section">
        <div class="filter-row">
            <a href="?status=all" class="filter-btn <?= $filter_status === 'all' ? 'active' : '' ?>">📋 All</a>
            <a href="?status=dispensed" class="filter-btn <?= $filter_status === 'dispensed' ? 'active' : '' ?>">💊 Dispensed</a>
            <a href="?status=cancelled" class="filter-btn <?= $filter_status === 'cancelled' ? 'active' : '' ?>">❌ Cancelled</a>
            
            <div style="flex:1;"></div>
            
            <form method="GET" class="filter-row" style="flex:1;gap:8px;">
                <input type="hidden" name="status" value="<?= htmlspecialchars($filter_status) ?>">
                <input type="text" name="search" class="filter-input" placeholder="Search patient, prescription..." value="<?= htmlspecialchars($search) ?>" style="flex:1;min-width:150px;">
                <input type="date" name="date_from" class="filter-input" value="<?= htmlspecialchars($date_from) ?>" placeholder="From">
                <input type="date" name="date_to" class="filter-input" value="<?= htmlspecialchars($date_to) ?>" placeholder="To">
                <button type="submit" class="btn-search">
                    <i class="fas fa-search"></i> Filter
                </button>
                <?php if (!empty($search) || !empty($date_from) || !empty($date_to)): ?>
                    <a href="prescription_history.php" class="btn-outline btn-sm">
                        <i class="fas fa-times"></i> Clear
                    </a>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- TABLE - BLUE THEME, NO VIEW BILL BUTTON -->
    <!-- ================================================================ -->
    <div class="table-container">
        <div class="table-scroll">
            <table class="data-table">
                <thead>
                    <tr>
                        <th><i class="fas fa-hashtag"></i> #</th>
                        <th><i class="fas fa-receipt"></i> Prescription</th>
                        <th><i class="fas fa-user"></i> Patient</th>
                        <th><i class="fas fa-pills"></i> Medication</th>
                        <th><i class="fas fa-cubes"></i> Qty</th>
                        <th><i class="fas fa-info-circle"></i> Status</th>
                        <th><i class="fas fa-calendar-check"></i> Date</th>
                        <th><i class="fas fa-cog"></i> Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($prescriptions) > 0): ?>
                        <?php $i = 1; foreach ($prescriptions as $pres): ?>
                            <tr>
                                <td><?= $i++ ?></td>
                                <td>
                                    <span class="font-mono text-xs font-semibold" style="color:var(--primary);">
                                        <?= htmlspecialchars($pres['prescription_number'] ?? 'N/A') ?>
                                    </span>
                                    <?php if (!empty($pres['visit_number'])): ?>
                                        <span class="text-xs text-gray-400 block">Visit: <?= htmlspecialchars($pres['visit_number']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="font-medium text-sm"><?= htmlspecialchars($pres['patient_name'] ?? 'Unknown') ?></div>
                                    <div class="text-xs text-gray-400"><?= htmlspecialchars($pres['patient_code'] ?? 'N/A') ?></div>
                                    <?php if (!empty($pres['phone'])): ?>
                                        <div class="text-xs text-gray-400">📱 <?= htmlspecialchars($pres['phone']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php 
                                        // Get prescription items from prescription_items table
                                        $stmt_items = $db->prepare("
                                            SELECT medication_name, dosage, quantity, unit_price 
                                            FROM prescription_items 
                                            WHERE prescription_id = ? 
                                            ORDER BY id ASC
                                            LIMIT 1
                                        ");
                                        $stmt_items->execute([$pres['id']]);
                                        $first_item = $stmt_items->fetch(PDO::FETCH_ASSOC);
                                        
                                        $medication_name = $first_item['medication_name'] ?? 'N/A';
                                        $dosage = $first_item['dosage'] ?? '';
                                        $qty = $first_item['quantity'] ?? $pres['quantity'] ?? 0;
                                        
                                        // Count total items
                                        $stmt_count = $db->prepare("SELECT COUNT(*) as total FROM prescription_items WHERE prescription_id = ?");
                                        $stmt_count->execute([$pres['id']]);
                                        $total_items = $stmt_count->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
                                    ?>
                                    <span class="text-sm font-medium"><?= htmlspecialchars($medication_name) ?></span>
                                    <?php if (!empty($dosage)): ?>
                                        <span class="text-xs text-gray-400 block"><?= htmlspecialchars($dosage) ?></span>
                                    <?php endif; ?>
                                    <?php if ($total_items > 1): ?>
                                        <span class="text-xs text-gray-400 block">+ <?= $total_items - 1 ?> more item(s)</span>
                                    <?php endif; ?>
                                    <?php if (!empty($pres['doctor_name'])): ?>
                                        <span class="text-xs text-gray-400 block">
                                            <i class="fas fa-user-md"></i> <?= htmlspecialchars($pres['doctor_name']) ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="text-sm font-semibold"><?= $qty ?></span>
                                    <?php if (!empty($pres['duration'])): ?>
                                        <span class="text-xs text-gray-400 block"><?= $pres['duration'] ?> days</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge-status <?= getStatusBadgeClass($pres['status'] ?? 'pending') ?>">
                                        <?= getStatusLabel($pres['status'] ?? 'pending') ?>
                                    </span>
                                    <?php if ($pres['status'] === 'dispensed' && !empty($pres['pharmacy_name'])): ?>
                                        <span class="text-xs text-gray-400 block">by <?= htmlspecialchars($pres['pharmacy_name']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="text-xs"><?= formatDate($pres['dispensed_at'] ?? $pres['updated_at'] ?? '') ?></span>
                                </td>
                                <td>
                                    <div class="flex flex-wrap gap-1">
                                        <a href="view_prescription.php?id=<?= $pres['id'] ?>" class="btn-view" title="View Details">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8">
                                <div class="text-center py-8 text-gray-400">
                                    <i class="fas fa-history text-3xl block mb-2"></i>
                                    <p>No prescription history found</p>
                                    <p class="text-sm mt-1">
                                        <?php if (!empty($search)): ?>
                                            No results for "<strong><?= htmlspecialchars($search) ?></strong>"
                                        <?php elseif (!empty($date_from) || !empty($date_to)): ?>
                                            No prescriptions in this date range
                                        <?php else: ?>
                                            No prescriptions have been dispensed or cancelled yet
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
                <i class="fas fa-list"></i> Showing <strong><?= count($prescriptions) ?></strong> records
            </span>
            <span>
                <span class="count-badge"><?= count($prescriptions) ?></span> Total records
                <?php if ($filter_status !== 'all'): ?>
                    <span class="text-xs text-gray-400 ml-2">(Filtered: <?= ucfirst($filter_status) ?>)</span>
                <?php endif; ?>
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
            Prescription History
            <span class="text-gray-300 mx-2">|</span>
            <span id="footerTimestamp">Last updated: <?= date('H:i:s') ?></span>
            <span class="text-gray-300 mx-2">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

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
    // SEARCH
    // ================================================================
    function performSearch() {
        var form = document.querySelector('.filter-row form');
        if (form) form.submit();
    }

    // ================================================================
    // UPDATE FOOTER TIME
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
    // DARK MODE SYNC
    // ================================================================
    document.addEventListener('darkModeChanged', function(e) {
        var isDark = e.detail && e.detail.isDark;
        var html = document.documentElement;
        
        if (isDark) {
            html.setAttribute('data-theme', 'dark');
        } else {
            html.removeAttribute('data-theme');
        }
    });

    console.log('%c📋 Braick - Prescription History (NEW DATABASE)', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c✅ Using NEW DATABASE: dispensary_db', 'font-size:13px; color:#34D399;');
    console.log('%c✅ Using shared pharmacy_header.php & pharmacy_sidebar.php', 'font-size:13px; color:#34D399;');
    console.log('%c👤 User: <?= htmlspecialchars($user_full_name) ?>', 'font-size:13px; color:#059669;');
    console.log('%c💊 Dispensed (Green): <?= $total_dispensed ?>', 'font-size:13px; color:#059669;');
    console.log('%c❌ Cancelled (Red): <?= $total_cancelled ?>', 'font-size:13px; color:#DC2626;');
    console.log('%c📋 Using prescription_items for medication details', 'font-size:13px; color:#0B5ED7;');
    console.log('%c🚫 Bill button removed from history', 'font-size:13px; color:#DC2626;');
</script>

</body>
</html>