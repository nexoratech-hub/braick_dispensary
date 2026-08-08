<?php
// ================================================================
// FILE: frontend/pages/admin/view_bill_item.php
// ADMIN - VIEW BILL ITEM DETAILS WITH ALL ITEMS TABLE
// BRAICK DISPENSARY - BLUE THEME
// ================================================================

session_start();

// ================================================================
// FORCE SESSION
// ================================================================
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    $_SESSION['user_id'] = 1;
    $_SESSION['full_name'] = 'Admin John';
    $_SESSION['role'] = 'admin';
    $_SESSION['branch_id'] = 1;
}

// Include database
require_once '../../../backend/config/database.php';
require_once '../../../backend/helpers/functions.php';

$db = Database::getInstance()->getConnection();

// ================================================================
// GET PARAMETERS
// ================================================================
$item_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$selected_branch_id = $_GET['branch_id'] ?? $_GET['branch'] ?? 'all';

if ($item_id <= 0) {
    header('Location: bills.php?branch=' . urlencode($selected_branch_id) . '&error=invalid_id');
    exit;
}

// ================================================================
// FETCH BILL ITEM DETAILS
// ================================================================
try {
    $stmt = $db->prepare("
        SELECT 
            bi.*,
            pb.bill_number,
            pb.patient_id,
            pb.total_amount as bill_total,
            pb.status as bill_status,
            pb.created_at as bill_created_at,
            pb.paid_amount,
            pb.balance,
            pb.subtotal,
            pb.discount_amount,
            pb.discount_percent,
            p.full_name as patient_name,
            p.patient_id as patient_code,
            p.phone as patient_phone,
            u.full_name as created_by_name,
            b.name as branch_name
        FROM bill_items bi
        LEFT JOIN patient_bills pb ON bi.bill_id = pb.id
        LEFT JOIN patients p ON pb.patient_id = p.id
        LEFT JOIN users u ON pb.created_by = u.id
        LEFT JOIN branches b ON bi.branch_id = b.id
        WHERE bi.id = ?
    ");
    $stmt->execute([$item_id]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$item) {
        header('Location: bills.php?branch=' . urlencode($selected_branch_id) . '&error=notfound');
        exit;
    }
} catch (Exception $e) {
    error_log("Error fetching bill item: " . $e->getMessage());
    header('Location: bills.php?branch=' . urlencode($selected_branch_id) . '&error=database_error');
    exit;
}

// ================================================================
// FETCH ALL ITEMS FOR THIS BILL
// ================================================================
$all_items = [];
try {
    $stmt = $db->prepare("
        SELECT 
            bi.*,
            pb.bill_number
        FROM bill_items bi
        LEFT JOIN patient_bills pb ON bi.bill_id = pb.id
        WHERE bi.bill_id = ?
        ORDER BY bi.created_at DESC
    ");
    $stmt->execute([$item['bill_id']]);
    $all_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching all bill items: " . $e->getMessage());
    $all_items = [];
}

// ================================================================
// CALCULATE TOTALS FOR ALL ITEMS
// ================================================================
$subtotal = 0;
$total_items = count($all_items);
foreach ($all_items as $it) {
    $subtotal += ($it['total_price'] ?? 0);
}
$discount_amount = $item['discount_amount'] ?? 0;
$grand_total = $subtotal - $discount_amount;

// ================================================================
// GET BRANCHES FOR FILTER
// ================================================================
$branches = [];
try {
    $stmt = $db->query("SELECT id, name FROM branches WHERE status = 'active' ORDER BY name");
    $branches = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $branches = [];
}

// ================================================================
// STATUS BADGE CLASS
// ================================================================
function getStatusBadge($status) {
    $classes = [
        'active' => 'success',
        'inactive' => 'danger',
        'pending' => 'warning',
        'paid' => 'success',
        'partial' => 'warning',
        'cancelled' => 'danger',
        'completed' => 'success'
    ];
    return $classes[$status] ?? 'secondary';
}

function getItemTypeLabel($type) {
    $labels = [
        'registration' => 'Registration',
        'consultation' => 'Consultation',
        'lab_test' => 'Lab Test',
        'medication' => 'Medication',
        'procedure' => 'Procedure',
        'tool' => 'Tool/Supply',
        'other' => 'Other'
    ];
    return $labels[$type] ?? ucfirst($type);
}

function getItemTypeIcon($type) {
    $icons = [
        'registration' => 'fa-file-medical',
        'consultation' => 'fa-stethoscope',
        'lab_test' => 'fa-flask',
        'medication' => 'fa-pills',
        'procedure' => 'fa-syringe',
        'tool' => 'fa-tools',
        'other' => 'fa-cube'
    ];
    return $icons[$type] ?? 'fa-cube';
}

function getItemTypeColor($type) {
    $colors = [
        'registration' => 'blue',
        'consultation' => 'purple',
        'lab_test' => 'orange',
        'medication' => 'green',
        'procedure' => 'red',
        'tool' => 'teal',
        'other' => 'gray'
    ];
    return $colors[$type] ?? 'gray';
}

// ================================================================
// LOGO PATH
// ================================================================
$logo_url = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

// ================================================================
// INCLUDE SHARED HEADER
// ================================================================
include_once '../../components/admin_header.php';

// ================================================================
// INCLUDE SHARED SIDEBAR
// ================================================================
include_once '../../components/admin_sidebar.php';
?>

<!DOCTYPE html>
<html lang="en" data-theme="<?= isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bill Item Details - Braick Dispensary</title>
    
    <link rel="icon" href="<?= $logo_url ?>" type="image/png">
    <link rel="shortcut icon" href="<?= $logo_url ?>" type="image/png">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <style>
        /* ================================================================
           ROOT VARIABLES - BOLDER BLUE THEME
           ================================================================ */
        :root {
            --primary: #0B5ED7;
            --primary-dark: #0A4CA8;
            --primary-light: #3B82F6;
            --primary-bg: #EFF6FF;
            --primary-gradient: linear-gradient(135deg, #0B5ED7, #0A4CA8);
            --primary-gradient-strong: linear-gradient(135deg, #0A4CA8, #073B8A);
            
            --success: #059669;
            --success-dark: #047857;
            --success-light: #34D399;
            --success-bg: #D1FAE5;
            
            --danger: #DC2626;
            --danger-dark: #B91C1C;
            --danger-light: #F87171;
            --danger-bg: #FEE2E2;
            
            --warning: #D97706;
            --warning-bg: #FEF3C7;
            
            --purple: #7C3AED;
            --purple-bg: #EDE9FE;
            
            --teal: #0D9488;
            --teal-bg: #ECFDF5;
            
            --orange: #F59E0B;
            --orange-bg: #FFFBEB;
            
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
            --primary-gradient: linear-gradient(135deg, #2563EB, #1D4ED8);
            --primary-gradient-strong: linear-gradient(135deg, #1D4ED8, #1E40AF);
            --shadow: 0 1px 3px rgba(0,0,0,0.3);
            --shadow-md: 0 4px 12px rgba(0,0,0,0.3);
            --shadow-lg: 0 10px 25px rgba(0,0,0,0.4);
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
        
        .top-nav {
            position: fixed;
            top: 0;
            left: 270px;
            right: 0;
            height: 68px;
            background: var(--bg-nav);
            z-index: 40;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 24px;
            border-bottom: 2px solid var(--border-color);
            transition: all 0.3s ease;
            backdrop-filter: blur(10px);
            box-shadow: var(--shadow-sm);
        }
        
        .top-nav .search-wrapper {
            display: flex;
            align-items: center;
            background: var(--bg-body);
            border-radius: var(--radius);
            border: 2px solid var(--border-color);
            transition: all 0.3s;
            flex: 1;
            max-width: 500px;
        }
        
        .top-nav .search-wrapper:focus-within {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(11, 94, 215, 0.12);
        }
        
        .top-nav .search-wrapper input {
            border: none;
            background: transparent;
            padding: 8px 14px;
            width: 100%;
            font-size: 0.85rem;
            outline: none;
            color: var(--text-primary);
        }
        
        .top-nav .search-wrapper input::placeholder {
            color: var(--text-secondary);
        }
        
        .top-nav .search-wrapper .search-btn {
            background: var(--primary-gradient);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 0 var(--radius) var(--radius) 0;
            cursor: pointer;
            font-size: 0.85rem;
            transition: all 0.3s;
            white-space: nowrap;
        }
        
        .top-nav .search-wrapper .search-btn:hover {
            transform: scale(1.02);
        }
        
        .top-nav .datetime {
            font-size: 0.78rem;
            color: var(--text-secondary);
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .top-nav .datetime i {
            color: var(--primary-light);
        }
        
        .top-nav .avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--border-color);
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .top-nav .avatar:hover {
            border-color: var(--primary);
            transform: scale(1.05);
        }
        
        .top-nav .icon-btn {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-secondary);
            transition: all 0.3s;
            background: transparent;
            border: none;
            cursor: pointer;
            position: relative;
        }
        
        .top-nav .icon-btn:hover {
            background: var(--bg-body);
            color: var(--primary);
        }
        
        .notif-dot {
            position: absolute;
            top: 6px;
            right: 6px;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            border: 2px solid var(--bg-nav);
            animation: pulse-dot 2s infinite;
        }
        
        .notif-dot.has-notif { background: var(--danger); }
        .notif-dot.no-notif { background: var(--gray-400); animation: none; }
        
        @keyframes pulse-dot {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.2); }
        }
        
        .dark-toggle-btn {
            background: var(--bg-body);
            border: 2px solid var(--border-color);
            border-radius: var(--radius);
            padding: 6px 12px;
            cursor: pointer;
            font-size: 0.82rem;
            color: var(--text-primary);
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .dark-toggle-btn:hover {
            border-color: var(--primary);
            background: var(--bg-card);
        }
        
        .dark-toggle-btn i { font-size: 0.9rem; }
        
        .branch-selector {
            background: var(--bg-body);
            border: 2px solid var(--border-color);
            border-radius: var(--radius);
            padding: 6px 12px;
            font-size: 0.78rem;
            color: var(--text-primary);
            outline: none;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .branch-selector:focus {
            border-color: var(--primary);
        }
        
        .main-content {
            margin-left: 270px;
            margin-top: 68px;
            padding: 28px 32px;
            min-height: calc(100vh - 68px);
        }
        
        .page-header {
            background: var(--primary-gradient-strong);
            border-radius: var(--radius-lg);
            padding: 28px 36px;
            margin-bottom: 28px;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            box-shadow: 0 8px 32px rgba(10, 76, 168, 0.35);
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
        
        .page-header .page-subtitle strong {
            color: white;
            font-weight: 600;
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
            transition: all 0.3s ease;
        }
        
        .page-header .header-badge:hover {
            background: rgba(255,255,255,0.2);
            transform: translateY(-1px);
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
        }
        
        .page-header .btn-outline-light:hover {
            background: rgba(255,255,255,0.25);
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(0,0,0,0.15);
        }
        
        .detail-card {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            padding: 24px 28px;
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
            box-shadow: var(--shadow-sm);
            margin-bottom: 24px;
        }
        
        .detail-card:hover {
            border-color: var(--primary);
            box-shadow: var(--shadow-md);
        }
        
        .detail-label {
            font-size: 0.7rem;
            color: var(--text-secondary);
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        
        .detail-value {
            font-size: 0.95rem;
            font-weight: 600;
            color: var(--text-primary);
        }
        
        .item-type-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 16px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .item-type-badge.blue { background: var(--primary-bg); color: var(--primary); }
        .item-type-badge.purple { background: var(--purple-bg); color: var(--purple); }
        .item-type-badge.orange { background: var(--orange-bg); color: var(--orange); }
        .item-type-badge.green { background: var(--success-bg); color: var(--success); }
        .item-type-badge.red { background: var(--danger-bg); color: var(--danger); }
        .item-type-badge.teal { background: var(--teal-bg); color: var(--teal); }
        .item-type-badge.gray { background: var(--gray-100); color: var(--gray-600); }
        
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 3px 12px;
            border-radius: 20px;
            font-size: 0.6rem;
            font-weight: 600;
            color: white;
            letter-spacing: 0.02em;
        }
        
        .badge-success { background: #059669; }
        .badge-danger { background: #DC2626; }
        .badge-warning { background: #D97706; color: #1E293B; }
        .badge-info { background: #0B5ED7; }
        .badge-secondary { background: #64748B; }
        
        [data-theme="dark"] .badge-warning { color: #1E293B; }
        
        .table-container {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            border: 1px solid var(--border-color);
            overflow: hidden;
            box-shadow: var(--shadow-sm);
            margin-bottom: 24px;
        }
        
        .table-container .card-header {
            padding: 14px 20px;
            background: var(--primary-gradient-strong);
            border-bottom: 2px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 8px;
        }
        
        .table-container .card-header .card-title {
            font-size: 0.85rem;
            font-weight: 700;
            color: white;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .table-container .card-header .card-title i {
            color: rgba(255,255,255,0.8);
        }
        
        .data-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            font-size: 0.78rem;
        }
        
        .data-table thead th {
            background: var(--bg-body);
            color: var(--text-secondary);
            font-weight: 700;
            padding: 10px 14px;
            font-size: 0.6rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            border-bottom: 2px solid var(--border-color);
            text-align: left;
        }
        
        [data-theme="dark"] .data-table thead th {
            background: #0F172A;
        }
        
        .data-table td {
            padding: 8px 14px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-primary);
            vertical-align: middle;
        }
        
        .data-table tbody tr:hover td {
            background: var(--table-hover);
        }
        
        .data-table tbody tr:last-child td {
            border-bottom: none;
        }
        
        .amount-display {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text-primary);
        }
        
        .amount-display.blue { color: var(--primary); }
        .amount-display.green { color: var(--success); }
        .amount-display.red { color: var(--danger); }
        .amount-display.purple { color: var(--purple); }
        
        .footer {
            padding: 14px 0;
            border-top: 2px solid var(--border-color);
            margin-top: 24px;
            text-align: center;
            font-size: 0.7rem;
            color: var(--text-secondary);
        }
        
        .footer .footer-brand {
            color: var(--primary);
            font-weight: 700;
        }
        
        @media (max-width: 1024px) {
            .top-nav { left: 0; }
            .main-content { margin-left: 0; padding: 16px; }
            .top-nav .search-wrapper { max-width: 300px; }
        }
        
        @media (max-width: 768px) {
            .top-nav .search-wrapper { max-width: 180px; }
            .top-nav .datetime { display: none; }
            .page-header { padding: 16px 18px; }
            .page-header .page-title { font-size: 1.3rem; }
            .detail-card { padding: 16px; }
            .data-table { font-size: 0.65rem; }
            .data-table thead th, .data-table td { padding: 6px 8px; }
        }
        
        @media (max-width: 480px) {
            .main-content { padding: 10px; }
            .page-header { flex-direction: column; align-items: flex-start !important; }
            .data-table { font-size: 0.55rem; }
            .data-table thead th, .data-table td { padding: 4px 6px; }
        }
        
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .animate-fade-in-up {
            animation: fadeInUp 0.5s ease forwards;
            opacity: 0;
        }
        
        .toast-custom {
            position: fixed;
            bottom: 24px;
            right: 24px;
            padding: 14px 20px;
            border-radius: 12px;
            z-index: 999;
            max-width: 400px;
            transform: translateY(100px);
            opacity: 0;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            align-items: center;
            gap: 12px;
            color: white;
            box-shadow: 0 10px 40px rgba(0,0,0,0.15);
        }
        .toast-custom.show {
            transform: translateY(0);
            opacity: 1;
        }
        .toast-custom.success { background: var(--success); }
        .toast-custom.error { background: var(--danger); }
        .toast-custom.info { background: var(--primary); }
        .toast-custom.warning { background: var(--warning); }
        
        @media print {
            .top-nav, .sidebar, .btn, .dark-toggle-btn, .icon-btn,
            .search-wrapper, .page-header .btn-outline-light,
            .footer, #sidebarToggle { display: none !important; }
            .main-content { margin: 0; padding: 20px; }
            .detail-card { break-inside: avoid; box-shadow: none !important; border: 1px solid #ddd; }
            .table-container { break-inside: avoid; box-shadow: none !important; border: 1px solid #ddd; }
            .page-header {
                background: #0B5ED7 !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            .page-title, .page-subtitle, .header-badge, .role-badge-display {
                color: white !important;
            }
        }
    </style>
</head>
<body>

<!-- ================================================================ -->
<!-- TOP NAVIGATION - SHARED HEADER -->
<!-- ================================================================ -->
<nav class="top-nav">
    <div class="flex items-center gap-4 flex-1">
        <button id="sidebarToggle" class="lg:hidden icon-btn">
            <i class="fas fa-bars text-lg"></i>
        </button>
        
        <div class="search-wrapper">
            <i class="fas fa-search text-gray-400 ml-3"></i>
            <input type="text" id="searchInput" placeholder="Search...">
            <button id="searchBtn" class="search-btn">
                <i class="fas fa-search mr-1"></i> Search
            </button>
        </div>
    </div>
    
    <div class="flex items-center gap-3">
        <select id="branchSelector" class="branch-selector" onchange="switchBranch(this.value)">
            <option value="all" <?= $selected_branch_id === 'all' ? 'selected' : '' ?>>🌐 All Branches</option>
            <?php foreach ($branches as $b): ?>
                <option value="<?= $b['id'] ?>" <?= $selected_branch_id == $b['id'] ? 'selected' : '' ?>>
                    🏥 <?= htmlspecialchars($b['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        
        <span class="datetime" id="currentDateTime"></span>
        
        <button id="darkModeToggle" class="dark-toggle-btn" title="Toggle Dark Mode">
            <i id="darkIcon" class="fas fa-moon"></i>
            <span id="darkText">Dark</span>
        </button>
        
        <button class="icon-btn">
            <i class="fas fa-bell text-lg"></i>
            <span class="notif-dot"></span>
        </button>
        
        <a href="profile.php">
            <img src="<?= $logo_url ?>" alt="Profile" class="avatar"
                 onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2240%22 height=%2240%22%3E%3Crect width=%2240%22 height=%2240%22 fill=%22%230B5ED7%22 rx=%2250%25%22/%3E%3Ctext x=%2220%22 y=%2226%22 text-anchor=%22middle%22 fill=%22white%22 font-size=%2218%22 font-weight=%22bold%22%3EA%3C/text%3E%3C/svg%3E'">
        </a>
    </div>
</nav>

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
                <i class="fas fa-receipt"></i>
                Bill Item Details
                <span class="role-badge-display">ADMIN</span>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-file-invoice"></i>
                <strong><?= htmlspecialchars($item['item_name'] ?? 'N/A') ?></strong>
                <span class="header-badge">
                    <i class="fas fa-hashtag"></i>
                    #<?= $item_id ?>
                </span>
                <span class="header-badge">
                    <?php if ($item['payment_status'] === 'paid'): ?>
                        <i class="fas fa-check-circle"></i> Paid
                    <?php else: ?>
                        <i class="fas fa-clock"></i> <?= ucfirst($item['payment_status'] ?? 'Pending') ?>
                    <?php endif; ?>
                </span>
                <span class="header-badge" style="background:rgba(52,211,153,0.2);border-color:rgba(52,211,153,0.3);color:#34D399;">
                    <i class="fas fa-money-bill-wave"></i>
                    TSh <?= number_format($item['total_price'] ?? 0, 0) ?>
                </span>
            </p>
        </div>
        <div class="flex gap-2 flex-wrap" style="position:relative;z-index:1;">
            <a href="view_bill.php?id=<?= $item['bill_id'] ?>&branch=<?= $selected_branch_id ?>" class="btn-outline-light">
                <i class="fas fa-file-invoice"></i> View Bill
            </a>
            <a href="bills.php?branch=<?= $selected_branch_id ?>" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- BILL ITEM DETAILS -->
    <!-- ================================================================ -->
    <div class="detail-card animate-fade-in-up">
        <!-- Item Type Badge -->
        <div class="flex justify-between items-start mb-4 flex-wrap gap-4">
            <div>
                <span class="item-type-badge <?= getItemTypeColor($item['item_type'] ?? 'other') ?>">
                    <i class="fas <?= getItemTypeIcon($item['item_type'] ?? 'other') ?>"></i>
                    <?= getItemTypeLabel($item['item_type'] ?? 'other') ?>
                </span>
            </div>
            <div>
                <span class="badge badge-<?= getStatusBadge($item['payment_status'] ?? 'pending') ?>">
                    <?php if ($item['payment_status'] === 'paid'): ?>
                        <i class="fas fa-check-circle"></i> Paid
                    <?php else: ?>
                        <i class="fas fa-clock"></i> <?= ucfirst($item['payment_status'] ?? 'Pending') ?>
                    <?php endif; ?>
                </span>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <p class="detail-label"><i class="fas fa-tag mr-1"></i> Item Name</p>
                <p class="detail-value"><?= htmlspecialchars($item['item_name'] ?? 'N/A') ?></p>
            </div>
            <div>
                <p class="detail-label"><i class="fas fa-cubes mr-1"></i> Quantity</p>
                <p class="detail-value"><?= number_format($item['quantity'] ?? 0) ?></p>
            </div>
            <div>
                <p class="detail-label"><i class="fas fa-money-bill-wave mr-1"></i> Unit Price</p>
                <p class="detail-value">TSh <?= number_format($item['unit_price'] ?? 0, 0) ?></p>
            </div>
            <div>
                <p class="detail-label"><i class="fas fa-calculator mr-1"></i> Total Price</p>
                <p class="detail-value amount-display blue">TSh <?= number_format($item['total_price'] ?? 0, 0) ?></p>
            </div>
            <div>
                <p class="detail-label"><i class="fas fa-file-invoice mr-1"></i> Bill Number</p>
                <p class="detail-value">
                    <a href="view_bill.php?id=<?= $item['bill_id'] ?>&branch=<?= $selected_branch_id ?>" class="text-blue-600 hover:underline">
                        <?= htmlspecialchars($item['bill_number'] ?? 'N/A') ?>
                    </a>
                </p>
            </div>
            <div>
                <p class="detail-label"><i class="fas fa-user mr-1"></i> Patient</p>
                <p class="detail-value">
                    <a href="view_patient.php?id=<?= $item['patient_id'] ?>&branch=<?= $selected_branch_id ?>" class="text-blue-600 hover:underline">
                        <?= htmlspecialchars($item['patient_name'] ?? 'N/A') ?>
                    </a>
                    <span class="text-xs text-gray-400 block"><?= htmlspecialchars($item['patient_code'] ?? '') ?></span>
                </p>
            </div>
            <div>
                <p class="detail-label"><i class="fas fa-user-plus mr-1"></i> Created By</p>
                <p class="detail-value"><?= htmlspecialchars($item['created_by_name'] ?? 'N/A') ?></p>
            </div>
            <div>
                <p class="detail-label"><i class="fas fa-store mr-1"></i> Branch</p>
                <p class="detail-value"><?= htmlspecialchars($item['branch_name'] ?? 'N/A') ?></p>
            </div>
            <div>
                <p class="detail-label"><i class="fas fa-clock mr-1"></i> Created At</p>
                <p class="detail-value"><?= date('M d, Y h:i A', strtotime($item['created_at'] ?? 'now')) ?></p>
            </div>
        </div>

        <?php if (!empty($item['description'])): ?>
        <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
            <p class="detail-label"><i class="fas fa-align-left mr-1"></i> Description</p>
            <p class="detail-value"><?= htmlspecialchars($item['description']) ?></p>
        </div>
        <?php endif; ?>

        <?php if (!empty($item['department']) || !empty($item['service_type'])): ?>
        <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700 grid grid-cols-1 md:grid-cols-2 gap-4">
            <?php if (!empty($item['department'])): ?>
            <div>
                <p class="detail-label"><i class="fas fa-building mr-1"></i> Department</p>
                <p class="detail-value"><?= htmlspecialchars($item['department']) ?></p>
            </div>
            <?php endif; ?>
            <?php if (!empty($item['service_type'])): ?>
            <div>
                <p class="detail-label"><i class="fas fa-tag mr-1"></i> Service Type</p>
                <p class="detail-value"><?= htmlspecialchars($item['service_type']) ?></p>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- ================================================================ -->
    <!-- ALL BILL ITEMS TABLE -->
    <!-- ================================================================ -->
    <div class="table-container animate-fade-in-up" style="animation-delay:0.05s;">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-list"></i>
                All Bill Items (<?= $total_items ?>)
            </h3>
            <span style="font-size:0.65rem;color:rgba(255,255,255,0.7);">
                Bill #<?= htmlspecialchars($item['bill_number'] ?? 'N/A') ?>
            </span>
        </div>
        <div class="overflow-x-auto">
            <?php if (count($all_items) > 0): ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Item Name</th>
                            <th>Type</th>
                            <th>Qty</th>
                            <th>Unit Price</th>
                            <th>Total</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $counter = 1;
                        foreach ($all_items as $it): 
                        ?>
                            <tr>
                                <td style="font-size:0.7rem;color:var(--text-secondary);"><?= $counter++ ?></td>
                                <td class="font-medium"><?= htmlspecialchars($it['item_name'] ?? 'N/A') ?></td>
                                <td>
                                    <span class="item-type-badge <?= getItemTypeColor($it['item_type'] ?? 'other') ?>" style="font-size:0.55rem;padding:2px 10px;">
                                        <i class="fas <?= getItemTypeIcon($it['item_type'] ?? 'other') ?>" style="font-size:0.5rem;"></i>
                                        <?= getItemTypeLabel($it['item_type'] ?? 'other') ?>
                                    </span>
                                </td>
                                <td><?= number_format($it['quantity'] ?? 0) ?></td>
                                <td>TSh <?= number_format($it['unit_price'] ?? 0, 0) ?></td>
                                <td class="font-semibold text-blue-600">TSh <?= number_format($it['total_price'] ?? 0, 0) ?></td>
                                <td>
                                    <span class="badge badge-<?= getStatusBadge($it['payment_status'] ?? 'pending') ?>" style="font-size:0.5rem;padding:1px 8px;">
                                        <?= ucfirst($it['payment_status'] ?? 'Pending') ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="view_bill_item.php?id=<?= $it['id'] ?>&branch=<?= $selected_branch_id ?>" class="text-blue-600 text-xs hover:underline <?= $it['id'] == $item_id ? 'font-bold text-blue-800' : '' ?>">
                                        <?= $it['id'] == $item_id ? '📌 View' : 'View' ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot style="background:var(--primary-bg);font-weight:700;">
                        <tr>
                            <td colspan="5" style="text-align:right;padding:10px 14px;">
                                <strong>Subtotal:</strong>
                            </td>
                            <td style="padding:10px 14px;color:var(--primary);">
                                TSh <?= number_format($subtotal, 0) ?>
                            </td>
                            <td colspan="2"></td>
                        </tr>
                        <?php if ($discount_amount > 0): ?>
                        <tr style="background:var(--warning-bg);">
                            <td colspan="5" style="text-align:right;padding:8px 14px;">
                                <strong>Discount (<?= $item['discount_percent'] ?? 0 ?>%):</strong>
                            </td>
                            <td style="padding:8px 14px;color:var(--warning);">
                                - TSh <?= number_format($discount_amount, 0) ?>
                            </td>
                            <td colspan="2"></td>
                        </tr>
                        <?php endif; ?>
                        <tr style="background:var(--success-bg);font-size:1rem;">
                            <td colspan="5" style="text-align:right;padding:10px 14px;">
                                <strong>Grand Total:</strong>
                            </td>
                            <td style="padding:10px 14px;color:var(--success);font-size:1.1rem;">
                                TSh <?= number_format($grand_total, 0) ?>
                            </td>
                            <td colspan="2"></td>
                        </tr>
                    </tfoot>
                </table>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-file-invoice"></i>
                    <p>No items found for this bill</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- BILL SUMMARY -->
    <!-- ================================================================ -->
    <div class="detail-card animate-fade-in-up" style="animation-delay:0.1s;">
        <h3 class="text-sm font-bold text-primary mb-4">
            <i class="fas fa-file-invoice"></i> Bill Summary
        </h3>
        <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
            <div>
                <p class="detail-label">Subtotal</p>
                <p class="detail-value amount-display blue">TSh <?= number_format($subtotal, 0) ?></p>
            </div>
            <div>
                <p class="detail-label">Discount</p>
                <p class="detail-value amount-display red">- TSh <?= number_format($discount_amount, 0) ?></p>
                <?php if (!empty($item['discount_percent'])): ?>
                    <span class="text-xs text-gray-400">(<?= $item['discount_percent'] ?>%)</span>
                <?php endif; ?>
            </div>
            <div>
                <p class="detail-label">Grand Total</p>
                <p class="detail-value amount-display green">TSh <?= number_format($grand_total, 0) ?></p>
            </div>
            <div>
                <p class="detail-label">Paid Amount</p>
                <p class="detail-value amount-display green">TSh <?= number_format($item['paid_amount'] ?? 0, 0) ?></p>
            </div>
            <div>
                <p class="detail-label">Balance</p>
                <p class="detail-value amount-display <?= ($item['balance'] ?? 0) > 0 ? 'red' : 'green' ?>">
                    TSh <?= number_format($item['balance'] ?? 0, 0) ?>
                </p>
            </div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- QUICK ACTIONS -->
    <!-- ================================================================ -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 animate-fade-in-up" style="animation-delay:0.15s;">
        <?php if ($item['payment_status'] !== 'paid' && $item['payment_status'] !== 'cancelled'): ?>
        <a href="add_payment.php?bill_item_id=<?= $item_id ?>&bill_id=<?= $item['bill_id'] ?>&branch=<?= $selected_branch_id ?>" 
           class="bg-card border-2 border-border rounded-lg p-4 text-center hover:border-green-500 transition-all hover:shadow-md text-decoration-none">
            <i class="fas fa-money-bill-wave text-2xl text-green-600 block mb-2"></i>
            <span class="text-sm font-medium text-text-primary">Record Payment</span>
        </a>
        <?php endif; ?>
        
        <a href="edit_bill_item.php?id=<?= $item_id ?>&branch=<?= $selected_branch_id ?>" 
           class="bg-card border-2 border-border rounded-lg p-4 text-center hover:border-primary transition-all hover:shadow-md text-decoration-none">
            <i class="fas fa-edit text-2xl text-blue-600 block mb-2"></i>
            <span class="text-sm font-medium text-text-primary">Edit Item</span>
        </a>
        
        <a href="view_bill.php?id=<?= $item['bill_id'] ?>&branch=<?= $selected_branch_id ?>" 
           class="bg-card border-2 border-border rounded-lg p-4 text-center hover:border-purple-500 transition-all hover:shadow-md text-decoration-none">
            <i class="fas fa-file-invoice text-2xl text-purple-600 block mb-2"></i>
            <span class="text-sm font-medium text-text-primary">View Full Bill</span>
        </a>
    </div>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Bill Item Details - <?= htmlspecialchars($item['item_name'] ?? 'Item') ?>
            <span class="text-gray-300 mx-2">|</span>
            <span id="footerTime"><?= date('H:i:s') ?></span>
            <span class="text-gray-300 mx-2">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<!-- ================================================================ -->
<!-- TOAST -->
<!-- ================================================================ -->
<div id="toast" class="toast-custom" style="display:none;">
    <i class="fas fa-info-circle" style="font-size:1.1rem;"></i>
    <div>
        <p style="font-weight:600;font-size:0.85rem;margin:0;" id="toastTitle">Notification</p>
        <p style="font-size:0.75rem;opacity:0.9;margin:0;" id="toastMessage"></p>
    </div>
</div>

<!-- ================================================================ -->
<!-- JAVASCRIPT -->
<!-- ================================================================ -->
<script>
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
    // DOM ELEMENTS
    // ================================================================
    var sidebar = document.getElementById('sidebar');
    var sidebarToggle = document.getElementById('sidebarToggle');
    var searchBtn = document.getElementById('searchBtn');
    var searchInput = document.getElementById('searchInput');

    sidebarToggle?.addEventListener('click', function() {
        sidebar.classList.toggle('open');
    });
    
    document.addEventListener('click', function(e) {
        if (window.innerWidth <= 1024) {
            if (!sidebar.contains(e.target) && e.target !== sidebarToggle) {
                sidebar.classList.remove('open');
            }
        }
    });

    function performSearch() {
        var query = searchInput.value.trim();
        if (query.length > 0) {
            var branch = '<?= $selected_branch_id ?>';
            window.location.href = 'search.php?q=' + encodeURIComponent(query) + '&branch=' + branch;
        }
    }
    
    searchBtn?.addEventListener('click', performSearch);
    searchInput?.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') performSearch();
    });

    function switchBranch(branchId) {
        var url = new URL(window.location.href);
        url.searchParams.set('branch', branchId);
        window.location.href = url.toString();
    }

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

    function showToast(title, message, type) {
        var toast = document.getElementById('toast');
        var toastTitle = document.getElementById('toastTitle');
        var toastMessage = document.getElementById('toastMessage');
        
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

    console.log('%c📄 Braick Dispensary - View Bill Item', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c📋 Item: <?= htmlspecialchars($item['item_name'] ?? 'N/A') ?> (ID: <?= $item_id ?>)', 'font-size:13px; color:#059669;');
    console.log('%c💰 Amount: TSh <?= number_format($item['total_price'] ?? 0, 0) ?>', 'font-size:13px; color:#7C3AED;');
    console.log('%c📊 Status: <?= ucfirst($item['payment_status'] ?? 'Pending') ?>', 'font-size:13px; color:#F59E0B;');
    console.log('%c📋 Total Items in Bill: <?= $total_items ?>', 'font-size:13px; color:#7C3AED;');
</script>

</body>
</html>