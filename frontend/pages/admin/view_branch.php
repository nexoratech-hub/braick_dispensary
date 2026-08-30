<?php
// ================================================================
// FILE: frontend/pages/admin/view_branch.php
// VIEW BRANCH DETAILS - WITH SHARED HEADER & SIDEBAR
// BRAICK DISPENSARY - USING EXISTING DATABASE TABLES
// ================================================================

// ================================================================
// START SESSION
// ================================================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ================================================================
// LOGIN PROTECTION
// ================================================================
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: ../login.php');
    exit;
}

if ($_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

// ================================================================
// GET USER DATA
// ================================================================
$user_id = $_SESSION['user_id'] ?? 0;
$user_full_name = $_SESSION['full_name'] ?? 'Admin';
$user_role = $_SESSION['role'] ?? 'admin';
$profile_pic = $_SESSION['profile_pic'] ?? '';

// ================================================================
// INCLUDE DATABASE
// ================================================================
require_once __DIR__ . '/../../../backend/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database connection error: " . $e->getMessage());
}

// ================================================================
// GET BRANCH ID - ONLY FROM URL
// ================================================================
$branch_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($branch_id <= 0) {
    header('Location: branches.php?branch=all');
    exit;
}

// ================================================================
// GET BRANCH DATA
// ================================================================
$stmt = $db->prepare("SELECT * FROM branches WHERE id = ?");
$stmt->execute([$branch_id]);
$branch = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$branch) {
    header('Location: branches.php?branch=all');
    exit;
}

// ================================================================
// EXTRACT BRANCH DATA
// ================================================================
$branch_name = $branch['name'];
$branch_status = $branch['status'] ?? 'inactive';
$branch_location = $branch['location'] ?? 'N/A';
$branch_phone = $branch['phone'] ?? 'N/A';
$branch_email = $branch['email'] ?? 'N/A';
$branch_created = $branch['created_at'] ?? date('Y-m-d H:i:s');
$branch_updated = $branch['updated_at'] ?? date('Y-m-d H:i:s');

// ================================================================
// GET STATISTICS - USING EXISTING TABLES
// ================================================================

// Total Employees
$stmt = $db->prepare("SELECT COUNT(*) as total FROM users WHERE branch_id = ? AND status = 'active'");
$stmt->execute([$branch_id]);
$total_employees = (int)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

// Staff by Role
$stmt = $db->prepare("SELECT role, COUNT(*) as count FROM users WHERE branch_id = ? AND status = 'active' GROUP BY role");
$stmt->execute([$branch_id]);
$role_counts = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total_doctors = 0;
$total_pharmacy = 0;
$total_reception = 0;
$total_laboratory = 0;
$total_cashiers = 0;
$total_admins = 0;

foreach ($role_counts as $rc) {
    switch ($rc['role']) {
        case 'admin': $total_admins = (int)$rc['count']; break;
        case 'doctor': $total_doctors = (int)$rc['count']; break;
        case 'pharmacy': $total_pharmacy = (int)$rc['count']; break;
        case 'reception': $total_reception = (int)$rc['count']; break;
        case 'laboratory': $total_laboratory = (int)$rc['count']; break;
        case 'cashier': $total_cashiers = (int)$rc['count']; break;
    }
}

// Total Patients
$stmt = $db->prepare("SELECT COUNT(*) as total FROM patients WHERE branch_id = ?");
$stmt->execute([$branch_id]);
$total_patients = (int)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

// Total Visits
$stmt = $db->prepare("SELECT COUNT(*) as total FROM visits WHERE branch_id = ? AND status != 'cancelled'");
$stmt->execute([$branch_id]);
$total_visits = (int)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

// Total Prescriptions
$stmt = $db->prepare("SELECT COUNT(*) as total FROM prescriptions WHERE branch_id = ? AND status != 'cancelled'");
$stmt->execute([$branch_id]);
$total_prescriptions = (int)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

// Total Lab Tests
$stmt = $db->prepare("SELECT COUNT(*) as total FROM lab_tests WHERE branch_id = ?");
$stmt->execute([$branch_id]);
$total_lab_tests = (int)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

// Total Bills (from bills table)
$stmt = $db->prepare("SELECT COUNT(*) as total, COALESCE(SUM(total_amount), 0) as total_amount FROM bills WHERE branch_id = ? AND status = 'paid'");
$stmt->execute([$branch_id]);
$bill_data = $stmt->fetch(PDO::FETCH_ASSOC);
$total_bills = (int)($bill_data['total'] ?? 0);
$bill_revenue = (float)($bill_data['total_amount'] ?? 0);

// Total Payments (from payments table)
$stmt = $db->prepare("SELECT COUNT(*) as total, COALESCE(SUM(amount), 0) as total_amount FROM payments WHERE branch_id = ?");
$stmt->execute([$branch_id]);
$payments_data = $stmt->fetch(PDO::FETCH_ASSOC);
$total_payments = (int)($payments_data['total'] ?? 0);
$payments_amount = (float)($payments_data['total_amount'] ?? 0);

// Pending Bills (from bills table)
$stmt = $db->prepare("SELECT COUNT(*) as total, COALESCE(SUM(total_amount), 0) as pending_amount FROM bills WHERE branch_id = ? AND status IN ('pending', 'partial')");
$stmt->execute([$branch_id]);
$pending_data = $stmt->fetch(PDO::FETCH_ASSOC);
$pending_bills = (int)($pending_data['total'] ?? 0);
$pending_revenue = (float)($pending_data['pending_amount'] ?? 0);

// Total Expenses
$stmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) as total_expenses FROM expenses WHERE branch_id = ? AND status = 'paid'");
$stmt->execute([$branch_id]);
$total_expenses = (float)($stmt->fetch(PDO::FETCH_ASSOC)['total_expenses'] ?? 0);

// Total Revenue (bills paid + payments)
$total_revenue = $bill_revenue + $payments_amount;

// Net Profit
$net_profit = $total_revenue - $total_expenses;

// ================================================================
// GET RECENT DATA
// ================================================================
$stmt = $db->prepare("SELECT id, full_name, patient_id, phone, created_at FROM patients WHERE branch_id = ? ORDER BY created_at DESC LIMIT 10");
$stmt->execute([$branch_id]);
$recent_patients = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $db->prepare("SELECT id, full_name, username, role, status, created_at FROM users WHERE branch_id = ? AND role != 'admin' ORDER BY created_at DESC LIMIT 10");
$stmt->execute([$branch_id]);
$recent_employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $db->prepare("
    SELECT p.id, p.prescription_number, p.status, p.created_at, pat.full_name as patient_name 
    FROM prescriptions p 
    JOIN patients pat ON p.patient_id = pat.id 
    WHERE p.branch_id = ? 
    ORDER BY p.created_at DESC 
    LIMIT 10
");
$stmt->execute([$branch_id]);
$recent_prescriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $db->prepare("
    SELECT b.id, b.bill_number, b.total_amount, b.status, b.created_at, p.full_name as patient_name 
    FROM bills b 
    JOIN patients p ON b.patient_id = p.id 
    WHERE b.branch_id = ? 
    ORDER BY b.created_at DESC 
    LIMIT 10
");
$stmt->execute([$branch_id]);
$recent_bills = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// UNREAD NOTIFICATIONS
// ================================================================
$unread_notifications = 0;
try {
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$user_id]);
    $unread_notifications = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
} catch (Exception $e) {
    $unread_notifications = 0;
}

// ================================================================
// INCLUDE SHARED HEADER & SIDEBAR
// ================================================================
include_once __DIR__ . '/../../components/admin_header.php';
include_once __DIR__ . '/../../components/admin_sidebar.php';

// ================================================================
// LOGO PATH
// ================================================================
$logo_url = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($branch_name) ?> - Braick Dispensary</title>
    
    <link rel="icon" href="<?= $logo_url ?>" type="image/png">
    <link rel="shortcut icon" href="<?= $logo_url ?>" type="image/png">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <style>
        :root {
            --primary: #1A56DB;
            --primary-dark: #1A3E8C;
            --primary-light: #3B82F6;
            --primary-bg: #E8EFF9;
            --primary-solid: #1A56DB;
            
            --success: #16A34A;
            --success-dark: #15803D;
            --success-bg: #DCFCE7;
            
            --danger: #DC2626;
            --danger-dark: #B91C1C;
            --danger-bg: #FEE2E2;
            
            --warning: #D97706;
            --warning-dark: #B45309;
            --warning-bg: #FEF3C7;
            
            --purple: #7C3AED;
            --purple-bg: #EDE9FE;
            
            --white: #FFFFFF;
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
            
            --shadow-sm: 0 1px 2px rgba(0,0,0,0.05);
            --shadow: 0 1px 3px rgba(0,0,0,0.08);
            --shadow-md: 0 4px 12px rgba(0,0,0,0.08);
            --shadow-lg: 0 10px 25px rgba(0,0,0,0.1);
            --shadow-xl: 0 20px 30px rgba(0,0,0,0.12);
            
            --bg-body: #F0F4F8;
            --bg-card: #FFFFFF;
            --bg-nav: #FFFFFF;
            --text-primary: #1E293B;
            --text-secondary: #64748B;
            --border-color: #E2E8F0;
            --radius: 12px;
            --radius-lg: 18px;
            --table-hover: #F8FAFC;
        }
        
        [data-theme="dark"] {
            --bg-body: #0F172A;
            --bg-card: #1E293B;
            --bg-nav: #1E293B;
            --text-primary: #F1F5F9;
            --text-secondary: #94A3B8;
            --border-color: #334155;
            --primary: #3B82F6;
            --primary-dark: #2563EB;
            --primary-light: #60A5FA;
            --primary-bg: #1E3A5F;
            --primary-solid: #2563EB;
            --shadow: 0 1px 3px rgba(0,0,0,0.3);
            --shadow-md: 0 4px 12px rgba(0,0,0,0.3);
            --shadow-lg: 0 10px 25px rgba(0,0,0,0.4);
            --success-bg: #064E3B;
            --danger-bg: #7F1D1D;
            --warning-bg: #78350F;
            --purple-bg: #2D1B5F;
            --table-hover: #1E293B;
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
        
        /* MAIN CONTENT */
        .main-content {
            margin-left: 270px;
            margin-top: 68px;
            padding: 28px 32px;
            min-height: calc(100vh - 68px);
        }
        
        /* PAGE HEADER */
        .page-header {
            background: var(--primary-solid);
            border-radius: var(--radius-lg);
            padding: 28px 36px;
            margin-bottom: 28px;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            box-shadow: 0 8px 32px rgba(26, 86, 219, 0.3);
            position: relative;
            overflow: hidden;
        }
        
        .page-header::before {
            content: '';
            position: absolute;
            top: -60%;
            right: -10%;
            width: 400px;
            height: 400px;
            background: rgba(255,255,255,0.05);
            border-radius: 50%;
            pointer-events: none;
        }
        
        .page-header::after {
            content: '';
            position: absolute;
            bottom: -40%;
            left: -5%;
            width: 300px;
            height: 300px;
            background: rgba(255,255,255,0.03);
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
        
        .page-header .header-badge {
            background: rgba(255,255,255,0.12);
            color: white;
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 500;
            backdrop-filter: blur(4px);
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border: 1px solid rgba(255,255,255,0.1);
        }
        
        .page-header .btn-outline-light {
            background: rgba(255,255,255,0.12);
            color: white;
            border: 1px solid rgba(255,255,255,0.2);
            padding: 8px 18px;
            border-radius: var(--radius);
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
            cursor: pointer;
        }
        
        .page-header .btn-outline-light:hover {
            background: rgba(255,255,255,0.25);
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(0,0,0,0.15);
        }
        
        /* STATS CARDS */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
            margin-bottom: 28px;
        }
        
        .stat-card {
            border-radius: var(--radius);
            padding: 20px 24px;
            display: flex;
            align-items: center;
            gap: 16px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 16px rgba(0,0,0,0.15);
            border: none;
            cursor: default;
        }
        
        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 32px rgba(0,0,0,0.25);
        }
        
        .stat-card .stat-icon {
            width: 52px;
            height: 52px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
            flex-shrink: 0;
            background: rgba(255,255,255,0.2);
            color: white;
        }
        
        .stat-card .stat-label {
            font-size: 0.7rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin: 0;
            color: rgba(255,255,255,0.8);
        }
        
        .stat-card .stat-value {
            font-size: 1.5rem;
            font-weight: 700;
            margin: 0;
            line-height: 1.2;
            color: white;
        }
        
        .stat-card .stat-sub {
            font-size: 0.65rem;
            color: rgba(255,255,255,0.7);
            margin-top: 2px;
        }
        
        .stat-card.blue { background: #1A56DB; }
        .stat-card.blue:hover { background: #1A3E8C; }
        .stat-card.green { background: #16A34A; }
        .stat-card.green:hover { background: #15803D; }
        .stat-card.red { background: #DC2626; }
        .stat-card.red:hover { background: #B91C1C; }
        .stat-card.orange { background: #D97706; }
        .stat-card.orange:hover { background: #B45309; }
        .stat-card.purple { background: #7C3AED; }
        .stat-card.purple:hover { background: #6D28D9; }
        
        /* DETAIL CARDS */
        .detail-card {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            border: 1px solid var(--border-color);
            padding: 20px 24px;
            transition: all 0.3s ease;
            box-shadow: var(--shadow-sm);
        }
        
        .detail-card:hover {
            border-color: var(--primary);
            box-shadow: var(--shadow-md);
        }
        
        .detail-card .card-title {
            font-size: 0.95rem;
            font-weight: 600;
            color: var(--text-primary);
            border-bottom: 2px solid var(--border-color);
            padding-bottom: 12px;
            margin-bottom: 14px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .detail-card .card-title i {
            color: var(--primary-solid);
        }
        
        .detail-card .card-title .badge-count {
            background: var(--primary-solid);
            color: white;
            padding: 1px 10px;
            border-radius: 20px;
            font-size: 0.6rem;
            font-weight: 600;
            margin-left: auto;
        }
        
        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 6px 0;
            border-bottom: 1px solid var(--border-color);
            font-size: 0.85rem;
        }
        
        .detail-row:last-child {
            border-bottom: none;
        }
        
        .detail-label {
            font-weight: 500;
            color: var(--text-secondary);
        }
        
        .detail-value {
            font-weight: 500;
            color: var(--text-primary);
        }
        
        .status-badge {
            display: inline-block;
            padding: 2px 10px;
            border-radius: 12px;
            font-size: 0.6rem;
            font-weight: 600;
        }
        
        .status-badge.active {
            background: var(--success-bg);
            color: var(--success);
        }
        
        .status-badge.inactive {
            background: var(--danger-bg);
            color: var(--danger);
        }
        
        .status-badge.pending {
            background: var(--warning-bg);
            color: var(--warning);
        }
        
        /* LIST ITEMS */
        .list-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 10px;
            border-bottom: 1px solid var(--border-color);
            transition: all 0.2s ease;
            border-radius: 6px;
        }
        
        .list-item:hover {
            background: var(--table-hover);
        }
        
        .list-item:last-child {
            border-bottom: none;
        }
        
        .list-item .item-info .name {
            font-weight: 500;
            color: var(--text-primary);
            font-size: 0.85rem;
        }
        
        .list-item .item-info .sub {
            font-size: 0.7rem;
            color: var(--text-secondary);
        }
        
        .list-item .item-info .sub i {
            margin-right: 4px;
        }
        
        .list-item .item-actions .btn-sm {
            padding: 2px 10px;
            font-size: 0.6rem;
            border-radius: 6px;
            text-decoration: none;
            background: var(--primary-solid);
            color: white;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        
        .list-item .item-actions .btn-sm:hover {
            background: var(--primary-dark);
            transform: scale(1.05);
        }
        
        .scroll-container {
            max-height: 260px;
            overflow-y: auto;
        }
        
        .scroll-container::-webkit-scrollbar {
            width: 4px;
        }
        
        .scroll-container::-webkit-scrollbar-track {
            background: var(--bg-body);
            border-radius: 4px;
        }
        
        .scroll-container::-webkit-scrollbar-thumb {
            background: var(--primary);
            border-radius: 4px;
        }
        
        /* QUICK ACTIONS */
        .quick-action {
            padding: 16px;
            border-radius: var(--radius);
            text-align: center;
            transition: all 0.3s ease;
            cursor: pointer;
            text-decoration: none;
            display: block;
            border: 2px solid var(--border-color);
            background: var(--bg-card);
        }
        
        .quick-action:hover {
            border-color: var(--primary);
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }
        
        .quick-action .icon {
            font-size: 1.6rem;
            display: block;
            margin-bottom: 4px;
        }
        
        .quick-action .label {
            font-size: 0.7rem;
            font-weight: 600;
            color: var(--text-primary);
        }
        
        /* EMPTY STATE */
        .empty-state {
            text-align: center;
            padding: 30px 20px;
            color: var(--text-secondary);
        }
        
        .empty-state i {
            font-size: 2.5rem;
            color: var(--border-color);
            display: block;
            margin-bottom: 8px;
        }
        
        .empty-state p {
            font-size: 0.85rem;
            margin: 0;
        }
        
        /* FOOTER */
        .footer {
            padding: 14px 0;
            border-top: 1px solid var(--border-color);
            margin-top: 24px;
            text-align: center;
            font-size: 0.7rem;
            color: var(--text-secondary);
        }
        
        .footer .footer-brand {
            color: var(--primary-solid);
            font-weight: 500;
        }
        
        /* RESPONSIVE */
        @media (max-width: 1024px) {
            .main-content { margin-left: 0; padding: 16px; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
        }
        
        @media (max-width: 768px) {
            .page-header { padding: 16px 18px; }
            .page-header .page-title { font-size: 1.3rem; }
            .stats-grid { grid-template-columns: 1fr 1fr; gap: 10px; }
            .stat-card { padding: 14px 16px; }
            .stat-card .stat-value { font-size: 1.2rem; }
            .stat-icon { width: 40px; height: 40px; font-size: 1rem; }
            .detail-card { padding: 14px 16px; }
            .detail-row { flex-direction: column; gap: 2px; }
        }
        
        @media (max-width: 480px) {
            .main-content { padding: 10px; }
            .stats-grid { grid-template-columns: 1fr 1fr; gap: 8px; }
            .stat-card { padding: 10px 12px; }
            .stat-card .stat-value { font-size: 1rem; }
            .stat-icon { width: 32px; height: 32px; font-size: 0.85rem; }
            .page-header .page-title { font-size: 1.1rem; }
        }
        
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .animate-fade-in-up {
            animation: fadeInUp 0.5s ease forwards;
            opacity: 0;
        }
        
        @media print {
            .top-nav, .sidebar, .btn, .dark-toggle-btn, .icon-btn,
            .search-wrapper, .quick-action, .page-header .btn-outline-light,
            .footer, #sidebarToggle { display: none !important; }
            
            .main-content { margin: 0; padding: 20px; }
            .detail-card { break-inside: avoid; box-shadow: none !important; border: 1px solid #ddd; }
            .stat-card { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
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
                <i class="fas fa-store-alt"></i>
                <?= htmlspecialchars($branch_name) ?>
                <span class="role-badge-display">BRANCH</span>
                <?php if ($branch_status === 'active'): ?>
                    <span class="header-badge" style="background:rgba(52,211,153,0.2);border-color:rgba(52,211,153,0.3);color:#34D399;">
                        <i class="fas fa-circle" style="font-size:6px;"></i> Active
                    </span>
                <?php else: ?>
                    <span class="header-badge" style="background:rgba(248,113,113,0.2);border-color:rgba(248,113,113,0.3);color:#F87171;">
                        <i class="fas fa-circle" style="font-size:6px;"></i> Inactive
                    </span>
                <?php endif; ?>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-map-marker-alt"></i>
                <?= htmlspecialchars($branch_location) ?>
                <span class="header-badge">
                    <i class="fas fa-phone"></i> <?= htmlspecialchars($branch_phone) ?>
                </span>
                <span class="header-badge">
                    <i class="fas fa-envelope"></i> <?= htmlspecialchars($branch_email) ?>
                </span>
                <span class="header-badge">
                    <i class="fas fa-id-card"></i> ID: #<?= $branch_id ?>
                </span>
            </p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;position:relative;z-index:1;">
            <a href="edit_branch.php?id=<?= $branch_id ?>" class="btn-outline-light">
                <i class="fas fa-edit"></i> Edit Branch
            </a>
            <a href="branches.php?branch=all" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- STATS CARDS - 6 CARDS -->
    <!-- ================================================================ -->
    <div class="stats-grid animate-fade-in-up">
        
        <div class="stat-card blue">
            <div class="stat-icon"><i class="fas fa-users"></i></div>
            <div>
                <p class="stat-label">Total Employees</p>
                <p class="stat-value"><?= number_format($total_employees) ?></p>
            </div>
        </div>
        
        <div class="stat-card blue">
            <div class="stat-icon"><i class="fas fa-user-injured"></i></div>
            <div>
                <p class="stat-label">Total Patients</p>
                <p class="stat-value"><?= number_format($total_patients) ?></p>
            </div>
        </div>
        
        <div class="stat-card green">
            <div class="stat-icon"><i class="fas fa-money-bill-wave"></i></div>
            <div>
                <p class="stat-label">Total Revenue</p>
                <p class="stat-value">TSh <?= number_format($total_revenue, 0) ?></p>
                <p class="stat-sub">Bills: TSh <?= number_format($bill_revenue, 0) ?></p>
            </div>
        </div>
        
        <div class="stat-card red">
            <div class="stat-icon"><i class="fas fa-arrow-up"></i></div>
            <div>
                <p class="stat-label">Total Expenses</p>
                <p class="stat-value">TSh <?= number_format($total_expenses, 0) ?></p>
            </div>
        </div>
        
        <div class="stat-card green">
            <div class="stat-icon"><i class="fas fa-chart-line"></i></div>
            <div>
                <p class="stat-label">Net Profit</p>
                <p class="stat-value">TSh <?= number_format($net_profit, 0) ?></p>
            </div>
        </div>
        
        <div class="stat-card orange">
            <div class="stat-icon"><i class="fas fa-clock"></i></div>
            <div>
                <p class="stat-label">Pending Revenue</p>
                <p class="stat-value">TSh <?= number_format($pending_revenue, 0) ?></p>
                <p class="stat-sub"><?= number_format($pending_bills) ?> pending bills</p>
            </div>
        </div>
        
    </div>

    <!-- Details & Lists -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
        
        <!-- Branch Information -->
        <div class="detail-card lg:col-span-1 animate-fade-in-up" style="animation-delay:0.05s;">
            <div class="card-title">
                <i class="fas fa-info-circle"></i>
                Branch Information
            </div>
            
            <div class="detail-row">
                <span class="detail-label">Branch Name</span>
                <span class="detail-value"><strong><?= htmlspecialchars($branch_name) ?></strong></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Location</span>
                <span class="detail-value"><?= htmlspecialchars($branch_location) ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Phone</span>
                <span class="detail-value"><?= htmlspecialchars($branch_phone) ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Email</span>
                <span class="detail-value"><?= htmlspecialchars($branch_email) ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Status</span>
                <span class="detail-value">
                    <span class="status-badge <?= $branch_status === 'active' ? 'active' : 'inactive' ?>">
                        <?= ucfirst($branch_status) ?>
                    </span>
                </span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Created</span>
                <span class="detail-value"><?= date('F d, Y', strtotime($branch_created)) ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Last Updated</span>
                <span class="detail-value"><?= date('F d, Y', strtotime($branch_updated)) ?></span>
            </div>
            
            <!-- Staff Breakdown -->
            <div style="margin-top:14px;padding-top:14px;border-top:2px solid var(--border-color);">
                <div style="font-size:0.7rem;font-weight:600;color:var(--text-secondary);text-transform:uppercase;letter-spacing:0.05em;margin-bottom:8px;">Staff Breakdown</div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:4px 16px;">
                    <div class="detail-row" style="border-bottom:none;padding:3px 0;">
                        <span class="detail-label">👨‍⚕️ Doctors</span>
                        <span class="detail-value"><strong><?= number_format($total_doctors) ?></strong></span>
                    </div>
                    <div class="detail-row" style="border-bottom:none;padding:3px 0;">
                        <span class="detail-label">💊 Pharmacy</span>
                        <span class="detail-value"><strong><?= number_format($total_pharmacy) ?></strong></span>
                    </div>
                    <div class="detail-row" style="border-bottom:none;padding:3px 0;">
                        <span class="detail-label">💉 Laboratory</span>
                        <span class="detail-value"><strong><?= number_format($total_laboratory) ?></strong></span>
                    </div>
                    <div class="detail-row" style="border-bottom:none;padding:3px 0;">
                        <span class="detail-label">📋 Reception</span>
                        <span class="detail-value"><strong><?= number_format($total_reception) ?></strong></span>
                    </div>
                    <div class="detail-row" style="border-bottom:none;padding:3px 0;">
                        <span class="detail-label">💰 Cashiers</span>
                        <span class="detail-value"><strong><?= number_format($total_cashiers) ?></strong></span>
                    </div>
                    <div class="detail-row" style="border-bottom:none;padding:3px 0;">
                        <span class="detail-label">👤 Admins</span>
                        <span class="detail-value"><strong><?= number_format($total_admins) ?></strong></span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Financial Summary -->
        <div class="detail-card lg:col-span-2 animate-fade-in-up" style="animation-delay:0.1s;">
            <div class="card-title">
                <i class="fas fa-chart-bar"></i>
                Financial Summary
                <span class="badge-count"><?= date('M Y') ?></span>
            </div>
            
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                <div style="background:var(--success-bg);border-radius:var(--radius);padding:14px 16px;">
                    <p style="font-size:0.7rem;font-weight:500;color:var(--text-secondary);margin:0;">💰 Total Revenue</p>
                    <p style="font-size:1.3rem;font-weight:700;color:var(--success);margin:0;">TSh <?= number_format($total_revenue, 0) ?></p>
                    <div style="display:flex;gap:8px;font-size:0.55rem;color:var(--text-secondary);margin-top:4px;flex-wrap:wrap;">
                        <span>📋 Bills: TSh <?= number_format($bill_revenue, 0) ?></span>
                        <span>💳 Payments: TSh <?= number_format($payments_amount, 0) ?></span>
                    </div>
                </div>
                
                <div style="background:var(--danger-bg);border-radius:var(--radius);padding:14px 16px;">
                    <p style="font-size:0.7rem;font-weight:500;color:var(--text-secondary);margin:0;">📤 Total Expenses</p>
                    <p style="font-size:1.3rem;font-weight:700;color:var(--danger);margin:0;">TSh <?= number_format($total_expenses, 0) ?></p>
                </div>
                
                <div style="background:var(--success-bg);border-radius:var(--radius);padding:14px 16px;">
                    <p style="font-size:0.7rem;font-weight:500;color:var(--text-secondary);margin:0;">📈 Net Profit</p>
                    <p style="font-size:1.3rem;font-weight:700;color:var(--success);margin:0;">TSh <?= number_format($net_profit, 0) ?></p>
                </div>
                
                <div style="background:var(--warning-bg);border-radius:var(--radius);padding:14px 16px;">
                    <p style="font-size:0.7rem;font-weight:500;color:var(--text-secondary);margin:0;">⏳ Pending Revenue</p>
                    <p style="font-size:1.3rem;font-weight:700;color:var(--warning);margin:0;">TSh <?= number_format($pending_revenue, 0) ?></p>
                    <p style="font-size:0.55rem;color:var(--text-secondary);margin:0;"><?= number_format($pending_bills) ?> pending bills</p>
                </div>
            </div>
            
            <!-- Additional Stats -->
            <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:8px;margin-top:12px;padding-top:12px;border-top:1px solid var(--border-color);">
                <div style="text-align:center;">
                    <p style="font-size:0.6rem;color:var(--text-secondary);margin:0;">💊 Prescriptions</p>
                    <p style="font-size:1.1rem;font-weight:700;color:var(--text-primary);margin:0;"><?= number_format($total_prescriptions) ?></p>
                </div>
                <div style="text-align:center;">
                    <p style="font-size:0.6rem;color:var(--text-secondary);margin:0;">🧪 Lab Tests</p>
                    <p style="font-size:1.1rem;font-weight:700;color:var(--text-primary);margin:0;"><?= number_format($total_lab_tests) ?></p>
                </div>
                <div style="text-align:center;">
                    <p style="font-size:0.6rem;color:var(--text-secondary);margin:0;">🏥 Visits</p>
                    <p style="font-size:1.1rem;font-weight:700;color:var(--text-primary);margin:0;"><?= number_format($total_visits) ?></p>
                </div>
                <div style="text-align:center;">
                    <p style="font-size:0.6rem;color:var(--text-secondary);margin:0;">💳 Payments</p>
                    <p style="font-size:1.1rem;font-weight:700;color:var(--text-primary);margin:0;"><?= number_format($total_payments) ?></p>
                </div>
            </div>
        </div>
        
    </div>

    <!-- Lists -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5 mt-5">
        
        <!-- Recent Patients -->
        <div class="detail-card animate-fade-in-up" style="animation-delay:0.2s;">
            <div class="card-title">
                <i class="fas fa-user-injured"></i>
                Recent Patients
                <span class="badge-count"><?= count($recent_patients) ?></span>
            </div>
            
            <div class="scroll-container">
                <?php if (count($recent_patients) > 0): ?>
                    <?php foreach ($recent_patients as $patient): ?>
                        <div class="list-item">
                            <div class="item-info">
                                <div class="name"><?= htmlspecialchars($patient['full_name']) ?></div>
                                <div class="sub">
                                    <i class="fas fa-id-card"></i> <?= htmlspecialchars($patient['patient_id']) ?>
                                    <i class="fas fa-phone ml-2"></i> <?= htmlspecialchars($patient['phone'] ?? 'N/A') ?>
                                </div>
                            </div>
                            <div class="item-actions">
                                <a href="view_patient.php?id=<?= $patient['id'] ?>" class="btn-sm">
                                    <i class="fas fa-eye"></i> View
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-user-injured"></i>
                        <p>No patients registered</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Recent Employees -->
        <div class="detail-card animate-fade-in-up" style="animation-delay:0.25s;">
            <div class="card-title">
                <i class="fas fa-user-tie"></i>
                Recent Employees
                <span class="badge-count"><?= count($recent_employees) ?></span>
            </div>
            
            <div class="scroll-container">
                <?php if (count($recent_employees) > 0): ?>
                    <?php foreach ($recent_employees as $employee): ?>
                        <div class="list-item">
                            <div class="item-info">
                                <div class="name"><?= htmlspecialchars($employee['full_name']) ?></div>
                                <div class="sub">
                                    <i class="fas fa-user-tag"></i> <?= ucfirst($employee['role']) ?>
                                    <span class="status-badge <?= $employee['status'] === 'active' ? 'active' : 'inactive' ?>" style="font-size:0.5rem;padding:1px 8px;margin-left:4px;">
                                        <?= ucfirst($employee['status']) ?>
                                    </span>
                                </div>
                            </div>
                            <div class="item-actions">
                                <a href="view_user.php?id=<?= $employee['id'] ?>" class="btn-sm">
                                    <i class="fas fa-eye"></i> View
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-user-tie"></i>
                        <p>No employees assigned</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Recent Prescriptions -->
        <div class="detail-card animate-fade-in-up" style="animation-delay:0.3s;">
            <div class="card-title">
                <i class="fas fa-prescription"></i>
                Recent Prescriptions
                <span class="badge-count"><?= count($recent_prescriptions) ?></span>
            </div>
            
            <div class="scroll-container">
                <?php if (count($recent_prescriptions) > 0): ?>
                    <?php foreach ($recent_prescriptions as $pres): ?>
                        <div class="list-item">
                            <div class="item-info">
                                <div class="name"><?= htmlspecialchars($pres['patient_name'] ?? 'Unknown') ?></div>
                                <div class="sub">
                                    <i class="fas fa-prescription"></i> <?= htmlspecialchars($pres['prescription_number'] ?? 'N/A') ?>
                                    <span class="status-badge <?= $pres['status'] === 'dispensed' ? 'active' : ($pres['status'] === 'confirmed' ? 'pending' : 'inactive') ?>" 
                                          style="font-size:0.5rem;padding:1px 8px;margin-left:4px;">
                                        <?= ucfirst($pres['status'] ?? 'pending') ?>
                                    </span>
                                </div>
                            </div>
                            <div class="item-actions">
                                <a href="view_prescription.php?id=<?= $pres['id'] ?>" class="btn-sm">
                                    <i class="fas fa-eye"></i> View
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-prescription"></i>
                        <p>No prescriptions available</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Recent Bills -->
        <div class="detail-card animate-fade-in-up" style="animation-delay:0.35s;">
            <div class="card-title">
                <i class="fas fa-file-invoice"></i>
                Recent Bills
                <span class="badge-count"><?= count($recent_bills) ?></span>
            </div>
            
            <div class="scroll-container">
                <?php if (count($recent_bills) > 0): ?>
                    <?php foreach ($recent_bills as $bill): ?>
                        <div class="list-item">
                            <div class="item-info">
                                <div class="name"><?= htmlspecialchars($bill['patient_name'] ?? 'Unknown') ?></div>
                                <div class="sub">
                                    <i class="fas fa-file-invoice"></i> <?= htmlspecialchars($bill['bill_number']) ?>
                                    <i class="fas fa-money-bill-wave ml-2"></i> TSh <?= number_format($bill['total_amount'] ?? 0, 0) ?>
                                    <span class="status-badge <?= $bill['status'] === 'paid' ? 'active' : ($bill['status'] === 'partial' ? 'pending' : 'inactive') ?>" 
                                          style="font-size:0.5rem;padding:1px 8px;margin-left:4px;">
                                        <?= ucfirst($bill['status'] ?? 'pending') ?>
                                    </span>
                                </div>
                            </div>
                            <div class="item-actions">
                                <a href="view_bill.php?id=<?= $bill['id'] ?>" class="btn-sm">
                                    <i class="fas fa-eye"></i> View
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-file-invoice"></i>
                        <p>No bills available</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
    </div>

    <!-- Quick Actions -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mt-5">
        <a href="add_employee.php?branch=<?= $branch_id ?>" class="quick-action">
            <span class="icon">👤</span>
            <span class="label">Add Employee</span>
        </a>
        <a href="edit_branch.php?id=<?= $branch_id ?>" class="quick-action">
            <span class="icon">✏️</span>
            <span class="label">Edit Branch</span>
        </a>
        <a href="branch_reports.php?id=<?= $branch_id ?>" class="quick-action">
            <span class="icon">📊</span>
            <span class="label">Reports</span>
        </a>
        <a href="branches.php?branch=all" class="quick-action">
            <span class="icon">🏢</span>
            <span class="label">All Branches</span>
        </a>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            <?= htmlspecialchars($branch_name) ?> - Details
            <span class="text-gray-300 mx-2">|</span>
            <span id="footerTime"><?= date('H:i:s') ?></span>
            <span class="text-gray-300 mx-2">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<script>
    // ================================================================
    // BRANCH SWITCHER
    // ================================================================
    function switchBranch(branchId) {
        if (branchId === 'all') {
            window.location.href = 'branches.php?branch=all';
        } else {
            window.location.href = 'view_branch.php?id=' + branchId;
        }
    }

    // ================================================================
    // DARK MODE
    // ================================================================
    var darkModeToggle = document.getElementById('darkModeToggle');
    var darkIcon = document.getElementById('darkIcon');
    var darkText = document.getElementById('darkText');
    var htmlElement = document.documentElement;
    
    var savedDarkMode = localStorage.getItem('darkMode');
    if (savedDarkMode === 'true') {
        htmlElement.setAttribute('data-theme', 'dark');
        darkIcon.className = 'fas fa-sun';
        darkText.textContent = 'Light';
    }
    
    darkModeToggle?.addEventListener('click', function() {
        var isDark = htmlElement.getAttribute('data-theme') === 'dark';
        if (isDark) {
            htmlElement.removeAttribute('data-theme');
            darkIcon.className = 'fas fa-moon';
            darkText.textContent = 'Dark';
            localStorage.setItem('darkMode', 'false');
            document.cookie = "dark_mode=false; path=/";
        } else {
            htmlElement.setAttribute('data-theme', 'dark');
            darkIcon.className = 'fas fa-sun';
            darkText.textContent = 'Light';
            localStorage.setItem('darkMode', 'true');
            document.cookie = "dark_mode=true; path=/";
        }
    });

    // ================================================================
    // SEARCH
    // ================================================================
    var searchBtn = document.getElementById('searchBtn');
    var searchInput = document.getElementById('searchInput');
    
    function performSearch() {
        var query = searchInput.value.trim();
        if (query.length > 0) {
            window.location.href = 'search.php?q=' + encodeURIComponent(query);
        }
    }
    
    searchBtn?.addEventListener('click', performSearch);
    searchInput?.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') performSearch();
    });

    // ================================================================
    // DATE & TIME
    // ================================================================
    function updateDateTime() {
        var now = new Date();
        var dateStr = now.toLocaleDateString('en-US', {
            weekday: 'short', month: 'short', day: 'numeric', year: 'numeric'
        });
        var timeStr = now.toLocaleTimeString('en-US', {
            hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true
        });
        var dtEl = document.getElementById('currentDateTime');
        if (dtEl) dtEl.textContent = dateStr + ' • ' + timeStr;
        
        var ftEl = document.getElementById('footerTime');
        if (ftEl) ftEl.textContent = timeStr;
    }
    updateDateTime();
    setInterval(updateDateTime, 1000);

    console.log('%c🏢 Braick Dispensary - View Branch (FIXED)', 'font-size:18px; font-weight:bold; color:#1A56DB;');
    console.log('%c🏢 Branch: <?= htmlspecialchars($branch_name) ?> (ID: <?= $branch_id ?>)', 'font-size:13px; color:#1A56DB;');
    console.log('%c👥 Employees: <?= $total_employees ?>, Patients: <?= $total_patients ?>', 'font-size:13px; color:#7B2FBE;');
    console.log('%c💰 Revenue: TSh <?= number_format($total_revenue, 0) ?>', 'font-size:13px; color:#16A34A;');
    console.log('%c📤 Expenses: TSh <?= number_format($total_expenses, 0) ?>', 'font-size:13px; color:#DC2626;');
    console.log('%c📈 Net Profit: TSh <?= number_format($net_profit, 0) ?>', 'font-size:13px; color:#16A34A;');
    console.log('%c⏳ Pending: TSh <?= number_format($pending_revenue, 0) ?>', 'font-size:13px; color:#D97706;');
    console.log('%c📊 Using tables: branches, users, patients, visits, prescriptions, lab_tests, bills, payments, expenses', 'font-size:13px; color:#34D399;');
</script>

</body>
</html>