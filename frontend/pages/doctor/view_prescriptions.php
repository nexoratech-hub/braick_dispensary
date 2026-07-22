<?php
// ================================================================
// FILE: frontend/pages/doctor/view_prescriptions.php
// DOCTOR - VIEW PRESCRIPTIONS
// SHOWS ALL PRESCRIPTIONS FOR THE LOGGED IN DOCTOR
// WITH FILTERS AND AUTO-UPDATE
// BRAICK DISPENSARY
// ================================================================

session_start();

// ================================================================
// IF NO SESSION, USE DR. JOHN MUSHI (ID: 5) AS DEFAULT
// ================================================================
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    $_SESSION['user_id'] = 5;
    $_SESSION['doctor_id'] = 5;
    $_SESSION['full_name'] = 'Dr. John Mushi';
    $_SESSION['username'] = 'dr.john';
    $_SESSION['email'] = 'john@braick.com';
    $_SESSION['phone'] = '+255 700 000 011';
    $_SESSION['role'] = 'doctor';
    $_SESSION['branch_id'] = 1;
    $_SESSION['specialty'] = 'General Medicine';
    $_SESSION['profile_pic'] = '';
    $_SESSION['is_online'] = 1;
}

$doctor_id = $_SESSION['user_id'] ?? 5;
$doctor_name = $_SESSION['full_name'] ?? 'Dr. John Mushi';
$doctor_branch_id = $_SESSION['branch_id'] ?? 1;

// ================================================================
// INCLUDE DATABASE
// ================================================================
require_once 'C:/xampp/htdocs/dispensary_system/backend/config/database.php';
$db = Database::getInstance()->getConnection();

// ================================================================
// GET FILTER PARAMETERS
// ================================================================
$filter_status = isset($_GET['status']) ? $_GET['status'] : 'all';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

// ================================================================
// BUILD QUERY
// ================================================================
$conditions = ["p.doctor_id = ?"];
$params = [$doctor_id];

if ($filter_status !== 'all') {
    $conditions[] = "p.status = ?";
    $params[] = $filter_status;
}

if (!empty($search)) {
    $conditions[] = "(pat.full_name LIKE ? OR pat.patient_id LIKE ? OR p.prescription_number LIKE ? OR p.medication LIKE ?)";
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
// GET PRESCRIPTIONS
// ================================================================
$sql = "
    SELECT 
        p.*,
        pat.full_name as patient_name,
        pat.patient_id as patient_code,
        pat.phone,
        u.full_name as doctor_name,
        ph.full_name as pharmacy_name,
        v.visit_number,
        (SELECT COUNT(*) FROM prescription_items WHERE prescription_id = p.id) as item_count
    FROM prescriptions p
    LEFT JOIN patients pat ON p.patient_id = pat.id
    LEFT JOIN users u ON p.doctor_id = u.id
    LEFT JOIN users ph ON p.pharmacy_id = ph.id
    LEFT JOIN visits v ON p.visit_id = v.id
    WHERE $where_clause
    ORDER BY p.created_at DESC
";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$prescriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// GET STATUS COUNTS
// ================================================================
$status_counts = [];
$statuses = ['pending', 'dispensed', 'cancelled'];
foreach ($statuses as $status) {
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM prescriptions 
        WHERE doctor_id = ? AND status = ?
    ");
    $stmt->execute([$doctor_id, $status]);
    $status_counts[$status] = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
}

// Total prescriptions
$stmt = $db->prepare("SELECT COUNT(*) as count FROM prescriptions WHERE doctor_id = ?");
$stmt->execute([$doctor_id]);
$total_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// ================================================================
// GET PATIENT LIST FOR FILTER
// ================================================================
$patients_list = [];
$stmt = $db->prepare("
    SELECT DISTINCT p.patient_id, pat.full_name, pat.patient_id as patient_code
    FROM prescriptions p
    JOIN patients pat ON p.patient_id = pat.id
    WHERE p.doctor_id = ?
    ORDER BY pat.full_name ASC
");
$stmt->execute([$doctor_id]);
$patients_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// HELPER FUNCTIONS
// ================================================================
function getStatusBadgeClass($status) {
    $map = [
        'pending' => 'badge-warning',
        'dispensed' => 'badge-success',
        'cancelled' => 'badge-danger',
        'pending_pharmacy' => 'badge-warning'
    ];
    return $map[$status] ?? 'badge-info';
}

function getStatusLabel($status) {
    $map = [
        'pending' => '⏳ Pending',
        'dispensed' => '✅ Dispensed',
        'cancelled' => '❌ Cancelled',
        'pending_pharmacy' => '⏳ Pending Pharmacy'
    ];
    return $map[$status] ?? ucfirst($status);
}

function formatDate($datetime) {
    if (empty($datetime)) return 'N/A';
    return date('d/m/Y h:i A', strtotime($datetime));
}

// ================================================================
// INCLUDE HEADER & SIDEBAR
// ================================================================
include_once 'C:/xampp/htdocs/dispensary_system/frontend/components/doctor_header.php';
include_once 'C:/xampp/htdocs/dispensary_system/frontend/components/doctor_sidebar.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Prescriptions - Braick Dispensary</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <style>
        /* ================================================================
           ROOT VARIABLES
           ================================================================ */
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
            
            /* Summary Card Colors */
            --card-total: #0B5ED7;
            --card-total-bg: #E8F0FE;
            --card-pending: #D97706;
            --card-pending-bg: #FEF3C7;
            --card-dispensed: #059669;
            --card-dispensed-bg: #D1FAE5;
            --card-cancelled: #DC2626;
            --card-cancelled-bg: #FEE2E2;
        }
        
        [data-theme="dark"] {
            --card-total: #6EA8FE;
            --card-total-bg: #1E3A5F;
            --card-pending: #FBBF24;
            --card-pending-bg: #3D2E0A;
            --card-dispensed: #34D399;
            --card-dispensed-bg: #1A3A2A;
            --card-cancelled: #F87171;
            --card-cancelled-bg: #3A1A1A;
        }
        
        * { box-sizing: border-box; }
        
        body {
            background: var(--gray-50);
            color: var(--gray-800);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        [data-theme="dark"] body {
            background: var(--gray-900);
            color: var(--gray-100);
        }
        
        .main-content {
            margin-left: 270px;
            margin-top: 68px;
            padding: 28px 32px;
            min-height: calc(100vh - 68px);
        }
        
        /* ================================================================
           PAGE HEADER
           ================================================================ */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            flex-wrap: wrap;
            gap: 16px;
            margin-bottom: 24px;
            padding: 20px 24px;
            background: #ffffff;
            border-radius: var(--radius-lg);
            border-bottom: 3px solid var(--primary);
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
        }
        [data-theme="dark"] .page-header { background: var(--gray-800); }
        
        .page-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--gray-800);
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }
        [data-theme="dark"] .page-title { color: var(--gray-100); }
        .page-title i { color: var(--primary); }
        .page-badge {
            font-size: 0.7rem;
            font-weight: 600;
            background: var(--primary-bg);
            color: var(--primary);
            padding: 4px 16px;
            border-radius: 20px;
            font-family: monospace;
        }
        .page-subtitle {
            font-size: 0.9rem;
            color: var(--gray-500);
            margin-top: 6px;
        }
        
        /* ================================================================
           STATS CARDS - COLORED
           ================================================================ */
        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }
        
        .stat-card {
            border-radius: var(--radius-lg);
            padding: 20px 24px;
            border: none;
            transition: var(--transition);
            position: relative;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        }
        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.12);
        }
        
        .stat-card .stat-icon {
            font-size: 1.6rem;
            margin-bottom: 6px;
            opacity: 0.8;
        }
        
        .stat-card .stat-number {
            font-size: 2rem;
            font-weight: 700;
            line-height: 1.2;
        }
        
        .stat-card .stat-label {
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            opacity: 0.8;
            margin-top: 2px;
        }
        
        .stat-card .stat-sub {
            font-size: 0.65rem;
            opacity: 0.6;
            margin-top: 4px;
        }
        
        /* Total Card - Blue */
        .stat-card.total {
            background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
            color: white;
        }
        [data-theme="dark"] .stat-card.total {
            background: linear-gradient(135deg, #1A3A5F, #0A3D7A);
        }
        .stat-card.total .stat-icon { color: rgba(255,255,255,0.9); }
        
        /* Pending Card - Yellow/Orange */
        .stat-card.pending {
            background: linear-gradient(135deg, #D97706, #B45309);
            color: white;
        }
        [data-theme="dark"] .stat-card.pending {
            background: linear-gradient(135deg, #3D2E0A, #5C3D0A);
        }
        .stat-card.pending .stat-icon { color: rgba(255,255,255,0.9); }
        
        /* Dispensed Card - Green */
        .stat-card.dispensed {
            background: linear-gradient(135deg, #059669, #047857);
            color: white;
        }
        [data-theme="dark"] .stat-card.dispensed {
            background: linear-gradient(135deg, #1A3A2A, #0D3D2A);
        }
        .stat-card.dispensed .stat-icon { color: rgba(255,255,255,0.9); }
        
        /* Cancelled Card - Red */
        .stat-card.cancelled {
            background: linear-gradient(135deg, #DC2626, #B91C1C);
            color: white;
        }
        [data-theme="dark"] .stat-card.cancelled {
            background: linear-gradient(135deg, #3A1A1A, #5C1A1A);
        }
        .stat-card.cancelled .stat-icon { color: rgba(255,255,255,0.9); }
        
        /* ================================================================
           FILTERS
           ================================================================ */
        .filter-section {
            background: #ffffff;
            border-radius: var(--radius-lg);
            padding: 16px 20px;
            border: 1px solid var(--gray-200);
            margin-bottom: 24px;
        }
        [data-theme="dark"] .filter-section { background: var(--gray-800); border-color: var(--gray-700); }
        
        .filter-row {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            align-items: center;
        }
        
        .filter-btn {
            padding: 6px 16px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            border: 2px solid var(--gray-200);
            background: transparent;
            color: var(--gray-600);
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
        .filter-btn.active:hover {
            background: var(--primary-dark);
            border-color: var(--primary-dark);
        }
        
        .filter-input {
            padding: 8px 14px;
            border: 2px solid var(--gray-200);
            border-radius: var(--radius);
            font-size: 0.8rem;
            background: #ffffff;
            color: var(--gray-800);
            outline: none;
            transition: var(--transition);
        }
        [data-theme="dark"] .filter-input { background: var(--gray-700); color: var(--gray-100); border-color: var(--gray-600); }
        .filter-input:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(11,94,215,0.12); }
        
        .btn-search {
            padding: 8px 20px;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: var(--radius);
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
        }
        .btn-search:hover { background: var(--primary-dark); transform: translateY(-2px); }
        
        /* ================================================================
           TABLE - BLUE HEADER
           ================================================================ */
        .table-container {
            background: #ffffff;
            border-radius: var(--radius-lg);
            border: 1px solid var(--gray-200);
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        }
        [data-theme="dark"] .table-container { background: var(--gray-800); border-color: var(--gray-700); }
        
        .table-scroll {
            overflow-x: auto;
        }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.85rem;
        }
        
        /* TABLE HEADER - BLUE BACKGROUND */
        .data-table thead th {
            text-align: left;
            padding: 14px 18px;
            font-weight: 700;
            font-size: 0.7rem;
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
        
        [data-theme="dark"] .data-table thead th {
            background: #0A3D7A;
            border-bottom-color: #0B4EA8;
        }
        
        .data-table thead th:first-child {
            border-radius: 0;
        }
        
        .data-table thead th i {
            margin-right: 6px;
            opacity: 0.7;
        }
        
        .data-table tbody td {
            padding: 12px 18px;
            border-bottom: 1px solid var(--gray-100);
            color: var(--gray-700);
            vertical-align: middle;
        }
        [data-theme="dark"] .data-table tbody td {
            border-color: var(--gray-700);
            color: var(--gray-300);
        }
        
        .data-table tbody tr {
            transition: var(--transition);
        }
        
        .data-table tbody tr:hover td {
            background: var(--primary-bg);
        }
        [data-theme="dark"] .data-table tbody tr:hover td {
            background: #1A3A5F;
        }
        
        .data-table tbody tr:last-child td {
            border-bottom: none;
        }
        
        /* Zebra striping - light */
        .data-table tbody tr:nth-child(even) td {
            background: var(--gray-50);
        }
        [data-theme="dark"] .data-table tbody tr:nth-child(even) td {
            background: #1A1A2E;
        }
        
        .data-table tbody tr:nth-child(even):hover td {
            background: var(--primary-bg);
        }
        [data-theme="dark"] .data-table tbody tr:nth-child(even):hover td {
            background: #1A3A5F;
        }
        
        /* ================================================================
           STATUS BADGES
           ================================================================ */
        .badge-status {
            display: inline-block;
            padding: 4px 16px;
            border-radius: 20px;
            font-size: 0.65rem;
            font-weight: 600;
            text-transform: capitalize;
        }
        .badge-warning { 
            background: var(--warning-bg); 
            color: var(--warning);
            border: 1px solid var(--warning);
        }
        .badge-success { 
            background: var(--success-bg); 
            color: var(--success);
            border: 1px solid var(--success);
        }
        .badge-danger { 
            background: var(--danger-bg); 
            color: var(--danger);
            border: 1px solid var(--danger);
        }
        .badge-info { 
            background: var(--primary-bg); 
            color: var(--primary);
            border: 1px solid var(--primary);
        }
        
        /* ================================================================
           BUTTONS
           ================================================================ */
        .btn-view {
            padding: 4px 12px;
            border-radius: 6px;
            background: var(--primary);
            color: white;
            border: none;
            font-size: 0.7rem;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            transition: var(--transition);
        }
        .btn-view:hover { background: var(--primary-dark); transform: translateY(-1px); }
        
        .btn-print {
            padding: 4px 12px;
            border-radius: 6px;
            background: var(--success);
            color: white;
            border: none;
            font-size: 0.7rem;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            transition: var(--transition);
        }
        .btn-print:hover { background: var(--success-dark); transform: translateY(-1px); }
        
        .btn-primary {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            background: var(--success);
            color: white;
            border-radius: var(--radius);
            text-decoration: none;
            font-weight: 600;
            font-size: 0.85rem;
            border: none;
            cursor: pointer;
            transition: var(--transition);
        }
        .btn-primary:hover { 
            background: var(--success-dark); 
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(5,150,105,0.3);
        }
        
        .btn-outline {
            padding: 8px 14px;
            border: 2px solid var(--gray-200);
            border-radius: var(--radius);
            text-decoration: none;
            color: var(--gray-600);
            font-size: 0.75rem;
            background: transparent;
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .btn-outline:hover {
            border-color: var(--primary);
            color: var(--primary);
            background: var(--primary-bg);
        }
        
        /* ================================================================
           EMPTY STATE
           ================================================================ */
        .empty-state {
            text-align: center;
            padding: 50px 20px;
            color: var(--gray-500);
        }
        .empty-state i {
            font-size: 3.5rem;
            color: var(--gray-300);
            display: block;
            margin-bottom: 16px;
        }
        .empty-state p { font-size: 1rem; }
        .empty-state .sub-text {
            font-size: 0.85rem;
            color: var(--gray-400);
            margin-top: 4px;
        }
        
        /* ================================================================
           TABLE FOOTER
           ================================================================ */
        .table-footer {
            padding: 12px 18px;
            border-top: 1px solid var(--gray-200);
            font-size: 0.75rem;
            color: var(--gray-500);
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
        
        .table-footer .count-badge {
            background: var(--primary);
            color: white;
            padding: 2px 12px;
            border-radius: 20px;
            font-size: 0.65rem;
            font-weight: 600;
        }
        
        /* ================================================================
           RESPONSIVE
           ================================================================ */
        @media (max-width: 768px) {
            .main-content { margin-left: 0; padding: 16px; }
            .filter-row { flex-direction: column; align-items: stretch; }
            .filter-input { width: 100%; }
            .stats-row { grid-template-columns: 1fr 1fr; }
            .page-header { flex-direction: column; }
            .stat-card .stat-number { font-size: 1.5rem; }
            .stat-card { padding: 14px 18px; }
        }
        
        @media (max-width: 480px) {
            .main-content { padding: 12px; }
            .stats-row { grid-template-columns: 1fr; }
            .page-title { font-size: 1.1rem; }
            .data-table { font-size: 0.75rem; }
            .data-table thead th, .data-table tbody td { padding: 8px 10px; }
            .data-table thead th { font-size: 0.6rem; }
        }
    </style>
</head>
<body>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <!-- Page Header -->
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-prescription"></i> My Prescriptions
                <span class="page-badge"><?= $total_count ?> Total</span>
            </h1>
            <p class="page-subtitle">
                View all prescriptions you have written
                <span class="text-xs text-gray-400 ml-2"><?= date('F d, Y') ?></span>
            </p>
        </div>
        <div>
            <a href="prescribe.php" class="btn-primary">
                <i class="fas fa-plus"></i> New Prescription
            </a>
        </div>
    </div>

    <!-- Stats Cards - Colored -->
    <div class="stats-row">
        <!-- Total Card - Blue -->
        <div class="stat-card total">
            <div class="stat-icon"><i class="fas fa-prescription"></i></div>
            <div class="stat-number"><?= $total_count ?></div>
            <div class="stat-label">Total Prescriptions</div>
            <div class="stat-sub"><i class="fas fa-clock"></i> All time</div>
        </div>
        
        <!-- Pending Card - Yellow/Orange -->
        <div class="stat-card pending">
            <div class="stat-icon"><i class="fas fa-clock"></i></div>
            <div class="stat-number"><?= $status_counts['pending'] ?? 0 ?></div>
            <div class="stat-label">Pending</div>
            <div class="stat-sub"><i class="fas fa-hourglass-half"></i> Awaiting pharmacy</div>
        </div>
        
        <!-- Dispensed Card - Green -->
        <div class="stat-card dispensed">
            <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
            <div class="stat-number"><?= $status_counts['dispensed'] ?? 0 ?></div>
            <div class="stat-label">Dispensed</div>
            <div class="stat-sub"><i class="fas fa-check"></i> Completed</div>
        </div>
        
        <!-- Cancelled Card - Red -->
        <div class="stat-card cancelled">
            <div class="stat-icon"><i class="fas fa-times-circle"></i></div>
            <div class="stat-number"><?= $status_counts['cancelled'] ?? 0 ?></div>
            <div class="stat-label">Cancelled</div>
            <div class="stat-sub"><i class="fas fa-ban"></i> Not dispensed</div>
        </div>
    </div>

    <!-- Filters -->
    <div class="filter-section">
        <div class="filter-row">
            <a href="?status=all" class="filter-btn <?= $filter_status === 'all' ? 'active' : '' ?>">📋 All</a>
            <a href="?status=pending" class="filter-btn <?= $filter_status === 'pending' ? 'active' : '' ?>">⏳ Pending</a>
            <a href="?status=dispensed" class="filter-btn <?= $filter_status === 'dispensed' ? 'active' : '' ?>">✅ Dispensed</a>
            <a href="?status=cancelled" class="filter-btn <?= $filter_status === 'cancelled' ? 'active' : '' ?>">❌ Cancelled</a>
            
            <div style="flex:1;"></div>
            
            <form method="GET" class="filter-row" style="flex:1;gap:8px;">
                <input type="hidden" name="status" value="<?= htmlspecialchars($filter_status) ?>">
                <input type="text" name="search" class="filter-input" placeholder="Search patient, medication..." value="<?= htmlspecialchars($search) ?>" style="flex:1;min-width:150px;">
                <button type="submit" class="btn-search">
                    <i class="fas fa-search"></i> Search
                </button>
                <?php if (!empty($search) || $filter_status !== 'all'): ?>
                    <a href="view_prescriptions.php" class="btn-outline">
                        <i class="fas fa-times"></i> Clear
                    </a>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <!-- Table - Blue Header -->
    <div class="table-container">
        <div class="table-scroll">
            <table class="data-table">
                <thead>
                    <tr>
                        <th><i class="fas fa-hashtag"></i> #</th>
                        <th><i class="fas fa-receipt"></i> Prescription #</th>
                        <th><i class="fas fa-user"></i> Patient</th>
                        <th><i class="fas fa-pills"></i> Medication</th>
                        <th><i class="fas fa-cubes"></i> Qty</th>
                        <th><i class="fas fa-info-circle"></i> Status</th>
                        <th><i class="fas fa-calendar"></i> Date</th>
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
                                    <?php if (($pres['item_count'] ?? 0) > 0): ?>
                                        <span class="text-xs text-gray-400 block">(<?= $pres['item_count'] ?> items)</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="font-medium text-sm"><?= htmlspecialchars($pres['patient_name'] ?? 'Unknown') ?></div>
                                    <div class="text-xs text-gray-400"><?= htmlspecialchars($pres['patient_code'] ?? 'N/A') ?></div>
                                </td>
                                <td>
                                    <span class="text-sm"><?= htmlspecialchars($pres['medication'] ?? 'N/A') ?></span>
                                    <?php if (!empty($pres['dosage'])): ?>
                                        <span class="text-xs text-gray-400 block"><?= htmlspecialchars($pres['dosage']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="text-sm font-semibold"><?= $pres['quantity'] ?? 0 ?></span>
                                    <?php if (!empty($pres['frequency'])): ?>
                                        <span class="text-xs text-gray-400 block"><?= htmlspecialchars($pres['frequency']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge-status <?= getStatusBadgeClass($pres['status'] ?? 'pending') ?>">
                                        <?= getStatusLabel($pres['status'] ?? 'pending') ?>
                                    </span>
                                    <?php if (!empty($pres['dispensed_at'])): ?>
                                        <span class="text-xs text-gray-400 block">
                                            <?= date('d/m/Y', strtotime($pres['dispensed_at'])) ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="text-xs"><?= formatDate($pres['created_at'] ?? '') ?></span>
                                    <?php if (!empty($pres['visit_number'])): ?>
                                        <span class="text-xs text-gray-400 block">Visit: <?= htmlspecialchars($pres['visit_number']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="flex flex-wrap gap-1">
                                        <a href="view_prescription.php?id=<?= $pres['id'] ?>" class="btn-view" title="View Details">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                        <?php if (($pres['status'] ?? '') === 'dispensed'): ?>
                                            <a href="print_prescription.php?id=<?= $pres['id'] ?>" class="btn-print" title="Print Prescription" target="_blank">
                                                <i class="fas fa-print"></i> Print
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8">
                                <div class="empty-state">
                                    <i class="fas fa-prescription"></i>
                                    <p>No prescriptions found</p>
                                    <p class="sub-text">
                                        <?php if (!empty($search)): ?>
                                            No results for "<strong><?= htmlspecialchars($search) ?></strong>"
                                        <?php elseif ($filter_status !== 'all'): ?>
                                            No <?= ucfirst($filter_status) ?> prescriptions
                                        <?php else: ?>
                                            You haven't written any prescriptions yet.
                                            <br><a href="prescribe.php" style="color:var(--primary);text-decoration:underline;">Write your first prescription</a>
                                        <?php endif; ?>
                                    </p>
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
                <i class="fas fa-list"></i> Showing <strong><?= count($prescriptions) ?></strong> prescriptions
            </span>
            <span>
                <span class="count-badge"><?= $total_count ?></span> Total prescriptions
            </span>
        </div>
    </div>

    <!-- Footer -->
    <footer class="footer" style="padding:16px 0;border-top:2px solid var(--gray-200);margin-top:24px;text-align:center;font-size:0.7rem;color:var(--gray-500);">
        <p>
            <span class="footer-brand" style="color:var(--primary);font-weight:600;">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            My Prescriptions
            <span class="text-gray-300 mx-2">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<!-- ================================================================ -->
<!-- TOAST -->
<!-- ================================================================ -->
<div id="toast" class="toast-custom" style="position:fixed;bottom:30px;right:30px;padding:14px 22px;border-radius:10px;z-index:9999;max-width:380px;transform:translateY(100px);opacity:0;transition:all 0.4s ease;display:flex;align-items:center;gap:12px;color:#ffffff;box-shadow:0 10px 40px rgba(0,0,0,0.15);display:none;">
    <i class="fas fa-info-circle"></i>
    <div>
        <p id="toastTitle" style="font-weight:600;font-size:0.85rem;margin:0;">Notification</p>
        <p id="toastMessage" style="font-size:0.75rem;opacity:0.9;margin:0;"></p>
    </div>
</div>

<!-- ================================================================ -->
<!-- JAVASCRIPT -->
<!-- ================================================================ -->
<script>
    // ================================================================
    // DARK MODE
    // ================================================================
    if (localStorage.getItem('darkMode') === 'true') {
        document.documentElement.setAttribute('data-theme', 'dark');
    }

    // ================================================================
    // TOAST
    // ================================================================
    function showToast(title, message, type) {
        var toast = document.getElementById('toast');
        var toastTitle = document.getElementById('toastTitle');
        var toastMessage = document.getElementById('toastMessage');
        if (!toast) return;
        toast.className = 'toast-custom';
        if (type === 'success') { toast.style.background = '#059669'; }
        else if (type === 'error') { toast.style.background = '#DC2626'; }
        else { toast.style.background = '#0B5ED7'; }
        toastTitle.textContent = title;
        toastMessage.textContent = message;
        toast.style.display = 'flex';
        setTimeout(function() { toast.style.transform = 'translateY(0)'; toast.style.opacity = '1'; }, 50);
        clearTimeout(toast.timeout);
        toast.timeout = setTimeout(function() {
            toast.style.transform = 'translateY(100px)';
            toast.style.opacity = '0';
            setTimeout(function() { toast.style.display = 'none'; }, 400);
        }, 4000);
    }

    console.log('%c💊 View Prescriptions - With Colors', 'font-size:16px; font-weight:bold; color:#0B5ED7;');
    console.log('%c📊 Summary Cards: Blue (Total), Yellow (Pending), Green (Dispensed), Red (Cancelled)', 'font-size:12px; color:#6EA8FE;');
    console.log('%c📋 Table Header: Blue Background', 'font-size:12px; color:#0B5ED7;');
    console.log('%c📋 Total Prescriptions: <?= $total_count ?>', 'font-size:12px; color:#059669;');
    console.log('%c⏳ Pending: <?= $status_counts['pending'] ?? 0 ?>', 'font-size:12px; color:#D97706;');
    console.log('%c✅ Dispensed: <?= $status_counts['dispensed'] ?? 0 ?>', 'font-size:12px; color:#059669;');
    console.log('%c❌ Cancelled: <?= $status_counts['cancelled'] ?? 0 ?>', 'font-size:12px; color:#DC2626;');
</script>

</body>
</html>