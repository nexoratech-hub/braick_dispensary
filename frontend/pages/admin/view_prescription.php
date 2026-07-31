<?php
// ================================================================
// FILE: frontend/pages/admin/view_prescription.php
// SUPER ADMIN - VIEW PRESCRIPTION DETAILS
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
// GET PRESCRIPTION ID
// ================================================================
$prescription_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$selected_branch_id = $_GET['branch'] ?? 'all';

if ($prescription_id <= 0) {
    header('Location: prescriptions.php?branch=' . $selected_branch_id . '&error=invalid_id');
    exit;
}

// ================================================================
// FETCH PRESCRIPTION DETAILS
// ================================================================
$stmt = $db->prepare("
    SELECT 
        p.*,
        pat.full_name as patient_name,
        pat.patient_id as patient_number,
        pat.phone as patient_phone,
        pat.gender as patient_gender,
        pat.date_of_birth as patient_dob,
        u.full_name as doctor_name,
        u.specialty as doctor_specialty,
        u.phone as doctor_phone,
        ph.full_name as pharmacy_name,
        ph.id as pharmacy_id,
        b.name as branch_name,
        v.visit_number,
        v.visit_date,
        v.diagnosis as visit_diagnosis,
        v.symptoms,
        v.treatment as visit_treatment,
        (SELECT COUNT(*) FROM prescription_items WHERE prescription_id = p.id) as total_items
    FROM prescriptions p
    LEFT JOIN patients pat ON p.patient_id = pat.id
    LEFT JOIN users u ON p.doctor_id = u.id
    LEFT JOIN users ph ON p.pharmacy_id = ph.id
    LEFT JOIN branches b ON p.branch_id = b.id
    LEFT JOIN visits v ON p.visit_id = v.id
    WHERE p.id = ?
");
$stmt->execute([$prescription_id]);
$prescription = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$prescription) {
    header('Location: prescriptions.php?branch=' . $selected_branch_id . '&error=notfound');
    exit;
}

// ================================================================
// FETCH PRESCRIPTION ITEMS
// ================================================================
$stmt = $db->prepare("
    SELECT 
        pi.*,
        mi.medication_name as inventory_name,
        mi.category,
        mi.unit,
        mi.selling_price
    FROM prescription_items pi
    LEFT JOIN medications_inventory mi ON pi.inventory_id = mi.id
    WHERE pi.prescription_id = ?
    ORDER BY pi.id ASC
");
$stmt->execute([$prescription_id]);
$prescription_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// FETCH BILL INFORMATION
// ================================================================
$bill = null;
try {
    $stmt = $db->prepare("
        SELECT 
            id,
            bill_number,
            total_amount,
            paid_amount,
            balance,
            status as bill_status,
            created_at as bill_created_at,
            (SELECT full_name FROM users WHERE id = patient_bills.created_by) as created_by_name
        FROM patient_bills
        WHERE prescription_id = ?
    ");
    $stmt->execute([$prescription_id]);
    $bill = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $bill = null;
}

// ================================================================
// FETCH PAYMENTS
// ================================================================
$payments = [];
if ($bill) {
    try {
        $stmt = $db->prepare("
            SELECT 
                receipt_number,
                amount,
                payment_method,
                received_at
            FROM payments
            WHERE bill_id = ?
            ORDER BY received_at DESC
        ");
        $stmt->execute([$bill['id']]);
        $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $payments = [];
    }
}

// ================================================================
// GET BRANCHES FOR FILTER
// ================================================================
$branches = [];
$stmt = $db->query("SELECT id, name FROM branches WHERE status = 'active' ORDER BY name");
$branches = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// STATUS BADGE CLASS
// ================================================================
function getStatusBadge($status) {
    $classes = [
        'pending' => 'warning',
        'confirmed' => 'info',
        'dispensed' => 'success',
        'cancelled' => 'danger'
    ];
    return $classes[$status] ?? 'secondary';
}

function getStatusIcon($status) {
    $icons = [
        'pending' => 'fa-clock',
        'confirmed' => 'fa-check-double',
        'dispensed' => 'fa-check-circle',
        'cancelled' => 'fa-times-circle'
    ];
    return $icons[$status] ?? 'fa-circle';
}

function getStatusLabel($status) {
    $labels = [
        'pending' => 'Pending',
        'confirmed' => 'Confirmed',
        'dispensed' => 'Dispensed',
        'cancelled' => 'Cancelled'
    ];
    return $labels[$status] ?? ucfirst($status);
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
    <title>View Prescription - Braick Dispensary</title>
    
    <link rel="icon" href="<?= $logo_url ?>" type="image/png">
    <link rel="shortcut icon" href="<?= $logo_url ?>" type="image/png">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <style>
        /* ================================================================
           ROOT VARIABLES - BLUE THEME
           ================================================================ */
        :root {
            --primary: #0B5ED7;
            --primary-dark: #0A4CA8;
            --primary-light: #3B82F6;
            --primary-bg: #EFF6FF;
            --primary-gradient: linear-gradient(135deg, #0B5ED7, #0A4CA8);
            
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
        
        /* ================================================================
           TOP NAV - SHARED HEADER
           ================================================================ */
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
            background: var(--primary-gradient);
            border-radius: var(--radius-lg);
            padding: 28px 36px;
            margin-bottom: 28px;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            box-shadow: 0 8px 32px rgba(11, 94, 215, 0.25);
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
        
        /* ================================================================
           DETAIL CARD
           ================================================================ */
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
        
        /* ================================================================
           STATUS BADGE
           ================================================================ */
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 0.7rem;
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
        
        /* ================================================================
           DATA TABLE
           ================================================================ */
        .data-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            font-size: 0.8rem;
        }
        
        .data-table thead th {
            background: var(--primary-gradient);
            color: white;
            font-weight: 600;
            padding: 10px 12px;
            font-size: 0.65rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            border-bottom: none;
            white-space: nowrap;
        }
        
        .data-table thead th:first-child {
            border-radius: 8px 0 0 0;
        }
        
        .data-table thead th:last-child {
            border-radius: 0 8px 0 0;
        }
        
        .data-table td {
            padding: 10px 12px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-primary);
            vertical-align: middle;
            transition: background 0.2s ease;
        }
        
        .data-table tbody tr:hover td {
            background: var(--table-hover);
        }
        
        .data-table tbody tr:last-child td {
            border-bottom: none;
        }
        
        /* ================================================================
           CARD
           ================================================================ */
        .card {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            border: 1px solid var(--border-color);
            overflow: hidden;
            transition: all 0.3s ease;
            box-shadow: var(--shadow-sm);
            margin-bottom: 24px;
        }
        
        .card:hover {
            box-shadow: var(--shadow-md);
        }
        
        .card-header {
            padding: 16px 24px;
            background: var(--bg-body);
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 8px;
        }
        
        [data-theme="dark"] .card-header {
            background: #0F172A;
        }
        
        .card-title {
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--text-primary);
            margin: 0;
            display: flex;
            align-items: center;
        }
        
        .card-title i {
            margin-right: 8px;
        }
        
        /* ================================================================
           BUTTONS
           ================================================================ */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 16px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.8rem;
            transition: all 0.3s ease;
            cursor: pointer;
            border: none;
            text-decoration: none;
            background: var(--bg-card);
            color: var(--text-primary);
            border: 1.5px solid var(--border-color);
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }
        
        .btn-sm {
            padding: 4px 10px;
            font-size: 0.65rem;
            border-radius: 6px;
        }
        
        .btn-primary {
            background: var(--primary-gradient);
            color: white;
            border-color: var(--primary);
        }
        
        .btn-primary:hover {
            background: var(--primary-dark);
            border-color: var(--primary-dark);
            color: white;
        }
        
        .btn-outline {
            background: transparent;
            color: var(--text-secondary);
            border: 1.5px solid var(--border-color);
        }
        
        .btn-outline:hover {
            background: var(--bg-body);
            border-color: var(--primary);
            color: var(--primary);
        }
        
        .btn-outline-primary {
            background: transparent;
            color: var(--primary);
            border: 1.5px solid var(--primary);
        }
        
        .btn-outline-primary:hover {
            background: var(--primary);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3);
        }
        
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
        
        .footer .footer-brand {
            color: var(--primary);
            font-weight: 500;
        }
        
        /* ================================================================
           RESPONSIVE
           ================================================================ */
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
        }
        
        @media (max-width: 480px) {
            .main-content { padding: 10px; }
            .page-header { flex-direction: column; align-items: flex-start !important; }
            .data-table { font-size: 0.7rem; }
            .data-table td, .data-table th { padding: 6px 8px; }
        }
        
        /* ================================================================
           ANIMATIONS
           ================================================================ */
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .animate-fade-in-up {
            animation: fadeInUp 0.5s ease forwards;
            opacity: 0;
        }
        
        /* ================================================================
           PRINT STYLES
           ================================================================ */
        @media print {
            .top-nav, .sidebar, .btn, .dark-toggle-btn, .icon-btn,
            .search-wrapper, .page-header .btn-outline-light,
            .footer, #sidebarToggle { display: none !important; }
            .main-content { margin: 0; padding: 20px; }
            .card { break-inside: avoid; box-shadow: none !important; border: 1px solid #ddd; }
            .detail-card { box-shadow: none !important; border: 1px solid #ddd; }
            .data-table thead th {
                background: #0B5ED7 !important;
                color: white !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
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

    <!-- Page Header -->
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-prescription"></i>
                Prescription Details
                <span class="role-badge-display">ADMIN</span>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-file-prescription"></i>
                <strong><?= htmlspecialchars($prescription['prescription_number'] ?? 'N/A') ?></strong>
                <span class="header-badge">
                    <i class="fas <?= getStatusIcon($prescription['status'] ?? 'pending') ?>"></i>
                    <?= getStatusLabel($prescription['status'] ?? 'pending') ?>
                </span>
                <span class="header-badge" style="background:rgba(59,130,246,0.2);border-color:rgba(59,130,246,0.3);color:#60A5FA;">
                    <i class="fas fa-user"></i> <?= htmlspecialchars($prescription['patient_name'] ?? 'N/A') ?>
                </span>
                <span class="header-badge" style="background:rgba(52,211,153,0.2);border-color:rgba(52,211,153,0.3);color:#34D399;">
                    <i class="fas fa-user-md"></i> <?= htmlspecialchars($prescription['doctor_name'] ?? 'N/A') ?>
                </span>
            </p>
        </div>
        <div class="flex gap-2 flex-wrap" style="position:relative;z-index:1;">
            <a href="print_prescription.php?id=<?= $prescription['id'] ?>&branch=<?= $selected_branch_id ?>" target="_blank" class="btn-outline-light">
                <i class="fas fa-print"></i> Print
            </a>
            <a href="prescriptions.php?branch=<?= $selected_branch_id ?>" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Back to List
            </a>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- PRESCRIPTION SUMMARY -->
    <!-- ================================================================ -->
    <div class="detail-card animate-fade-in-up">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <p class="detail-label"><i class="fas fa-prescription mr-1"></i> Prescription #</p>
                <p class="detail-value font-mono"><?= htmlspecialchars($prescription['prescription_number'] ?? 'N/A') ?></p>
            </div>
            <div>
                <p class="detail-label"><i class="fas fa-user mr-1"></i> Patient</p>
                <p class="detail-value">
                    <?= htmlspecialchars($prescription['patient_name'] ?? 'N/A') ?>
                    <span class="text-xs text-gray-400 block">ID: <?= htmlspecialchars($prescription['patient_number'] ?? 'N/A') ?></span>
                </p>
            </div>
            <div>
                <p class="detail-label"><i class="fas fa-user-md mr-1"></i> Doctor</p>
                <p class="detail-value">
                    <?= htmlspecialchars($prescription['doctor_name'] ?? 'N/A') ?>
                    <span class="text-xs text-gray-400 block"><?= htmlspecialchars($prescription['doctor_specialty'] ?? '') ?></span>
                </p>
            </div>
            <div>
                <p class="detail-label"><i class="fas fa-store-alt mr-1"></i> Pharmacy</p>
                <p class="detail-value">
                    <?= htmlspecialchars($prescription['pharmacy_name'] ?? 'Not Assigned') ?>
                    <span class="text-xs text-gray-400 block">Branch: <?= htmlspecialchars($prescription['branch_name'] ?? 'N/A') ?></span>
                </p>
            </div>
            <div>
                <p class="detail-label"><i class="fas fa-calendar-plus mr-1"></i> Created</p>
                <p class="detail-value"><?= date('M d, Y h:i A', strtotime($prescription['created_at'] ?? 'now')) ?></p>
            </div>
            <div>
                <p class="detail-label"><i class="fas fa-clock mr-1"></i> Status</p>
                <p class="detail-value">
                    <span class="badge badge-<?= getStatusBadge($prescription['status'] ?? 'pending') ?>">
                        <i class="fas <?= getStatusIcon($prescription['status'] ?? 'pending') ?>"></i>
                        <?= getStatusLabel($prescription['status'] ?? 'pending') ?>
                    </span>
                </p>
            </div>
            <?php if ($prescription['dispensed_at']): ?>
                <div>
                    <p class="detail-label"><i class="fas fa-check-circle mr-1"></i> Dispensed</p>
                    <p class="detail-value text-green-600"><?= date('M d, Y h:i A', strtotime($prescription['dispensed_at'])) ?></p>
                </div>
            <?php endif; ?>
            <?php if ($prescription['total_items']): ?>
                <div>
                    <p class="detail-label"><i class="fas fa-list mr-1"></i> Total Items</p>
                    <p class="detail-value"><?= $prescription['total_items'] ?> item(s)</p>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Diagnosis & Notes -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
            <div>
                <p class="detail-label"><i class="fas fa-stethoscope mr-1"></i> Diagnosis</p>
                <p class="detail-value text-sm font-normal"><?= nl2br(htmlspecialchars($prescription['diagnosis'] ?? 'N/A')) ?></p>
            </div>
            <div>
                <p class="detail-label"><i class="fas fa-notes-medical mr-1"></i> Notes / Instructions</p>
                <p class="detail-value text-sm font-normal"><?= nl2br(htmlspecialchars($prescription['notes'] ?? 'No notes')) ?></p>
            </div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- PRESCRIPTION ITEMS -->
    <!-- ================================================================ -->
    <div class="card animate-fade-in-up" style="animation-delay:0.05s;">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-list-ul text-blue-600"></i>
                Prescription Items (<?= count($prescription_items) ?>)
            </h3>
        </div>
        <div class="overflow-x-auto">
            <?php if (count($prescription_items) > 0): ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Medication</th>
                            <th>Dosage</th>
                            <th>Frequency</th>
                            <th>Quantity</th>
                            <th>Duration</th>
                            <th>Route</th>
                            <th>Instructions</th>
                            <?php if ($prescription['status'] === 'dispensed'): ?>
                                <th>Dispensed</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $i = 1; foreach ($prescription_items as $item): ?>
                            <tr>
                                <td><?= $i++ ?></td>
                                <td>
                                    <span class="font-medium"><?= htmlspecialchars($item['medication_name'] ?? $item['inventory_name'] ?? 'N/A') ?></span>
                                    <?php if (!empty($item['category'])): ?>
                                        <br><span class="text-xs text-gray-400"><?= htmlspecialchars($item['category']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($item['dosage'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($item['frequency'] ?? 'N/A') ?></td>
                                <td class="font-semibold"><?= $item['quantity'] ?? 0 ?></td>
                                <td><?= htmlspecialchars($item['duration'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($item['route'] ?? 'N/A') ?></td>
                                <td class="text-sm"><?= htmlspecialchars($item['instructions'] ?? '') ?></td>
                                <?php if ($prescription['status'] === 'dispensed'): ?>
                                    <td>
                                        <span class="badge badge-success" style="font-size:0.6rem;padding:2px 10px;">
                                            <i class="fas fa-check-circle"></i> Yes
                                        </span>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="text-center py-6 text-gray-400">
                    <i class="fas fa-prescription text-2xl block mb-2"></i>
                    <p>No items in this prescription</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- BILL & PAYMENT INFORMATION -->
    <!-- ================================================================ -->
    <?php if ($bill): ?>
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <!-- Bill Information -->
        <div class="card animate-fade-in-up" style="animation-delay:0.1s;">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-file-invoice text-blue-600"></i>
                    Bill Information
                </h3>
                <a href="bill_details.php?id=<?= $bill['id'] ?>&branch=<?= $selected_branch_id ?>" class="text-xs text-blue-600 hover:underline">View Bill →</a>
            </div>
            <div class="p-4 space-y-2">
                <div class="flex justify-between">
                    <span class="text-gray-500">Bill Number</span>
                    <span class="font-mono font-semibold"><?= htmlspecialchars($bill['bill_number'] ?? 'N/A') ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-500">Total Amount</span>
                    <span class="font-semibold text-blue-600">TSh <?= number_format($bill['total_amount'] ?? 0, 0) ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-500">Paid Amount</span>
                    <span class="font-semibold text-green-600">TSh <?= number_format($bill['paid_amount'] ?? 0, 0) ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-500">Balance</span>
                    <span class="font-semibold <?= ($bill['balance'] ?? 0) > 0 ? 'text-red-600' : 'text-green-600' ?>">
                        TSh <?= number_format($bill['balance'] ?? 0, 0) ?>
                    </span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-500">Status</span>
                    <span>
                        <span class="badge badge-<?= getStatusBadge($bill['bill_status'] ?? 'pending') ?>" style="font-size:0.6rem;padding:2px 10px;">
                            <?= ucfirst($bill['bill_status'] ?? 'Pending') ?>
                        </span>
                    </span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-500">Created By</span>
                    <span><?= htmlspecialchars($bill['created_by_name'] ?? 'N/A') ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-500">Created At</span>
                    <span><?= date('M d, Y h:i A', strtotime($bill['bill_created_at'] ?? 'now')) ?></span>
                </div>
            </div>
        </div>

        <!-- Payments -->
        <div class="card animate-fade-in-up" style="animation-delay:0.15s;">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-credit-card text-green-600"></i>
                    Payments (<?= count($payments) ?>)
                </h3>
            </div>
            <div class="p-4">
                <?php if (count($payments) > 0): ?>
                    <?php foreach ($payments as $payment): ?>
                        <div class="flex items-center justify-between py-2 border-b border-gray-100 dark:border-gray-700 last:border-0">
                            <div>
                                <p class="font-mono text-sm"><?= htmlspecialchars($payment['receipt_number'] ?? 'N/A') ?></p>
                                <p class="text-xs text-gray-400">
                                    <?= ucfirst($payment['payment_method'] ?? 'Cash') ?> • 
                                    <?= date('M d, Y h:i A', strtotime($payment['received_at'] ?? 'now')) ?>
                                </p>
                            </div>
                            <span class="font-semibold text-green-600">TSh <?= number_format($payment['amount'] ?? 0, 0) ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="text-center py-4 text-gray-400">
                        <i class="fas fa-credit-card text-2xl block mb-2"></i>
                        <p>No payments recorded</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- VISIT INFORMATION -->
    <!-- ================================================================ -->
    <?php if ($prescription['visit_number']): ?>
    <div class="card animate-fade-in-up" style="animation-delay:0.2s;">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-stethoscope text-purple-600"></i>
                Visit Information
            </h3>
            <a href="view_visit.php?id=<?= $prescription['visit_id'] ?>&branch=<?= $selected_branch_id ?>" class="text-xs text-blue-600 hover:underline">View Visit →</a>
        </div>
        <div class="p-4 grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <p class="text-xs text-gray-500 uppercase">Visit Number</p>
                <p class="font-semibold"><?= htmlspecialchars($prescription['visit_number'] ?? 'N/A') ?></p>
            </div>
            <div>
                <p class="text-xs text-gray-500 uppercase">Visit Date</p>
                <p class="font-semibold"><?= date('M d, Y h:i A', strtotime($prescription['visit_date'] ?? 'now')) ?></p>
            </div>
            <div>
                <p class="text-xs text-gray-500 uppercase">Visit Diagnosis</p>
                <p class="font-semibold"><?= htmlspecialchars($prescription['visit_diagnosis'] ?? 'N/A') ?></p>
            </div>
            <?php if ($prescription['symptoms']): ?>
                <div class="md:col-span-3">
                    <p class="text-xs text-gray-500 uppercase">Symptoms</p>
                    <p class="text-sm"><?= nl2br(htmlspecialchars($prescription['symptoms'] ?? 'N/A')) ?></p>
                </div>
            <?php endif; ?>
            <?php if ($prescription['visit_treatment']): ?>
                <div class="md:col-span-3">
                    <p class="text-xs text-gray-500 uppercase">Treatment Given</p>
                    <p class="text-sm"><?= nl2br(htmlspecialchars($prescription['visit_treatment'] ?? 'N/A')) ?></p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Prescription Details - <?= htmlspecialchars($prescription['prescription_number'] ?? 'N/A') ?>
            <span class="text-gray-300 mx-2">|</span>
            <span id="footerTime"><?= date('H:i:s') ?></span>
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

    // ================================================================
    // SIDEBAR TOGGLE
    // ================================================================
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

    // ================================================================
    // SEARCH
    // ================================================================
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

    // ================================================================
    // BRANCH SWITCHER
    // ================================================================
    function switchBranch(branchId) {
        var url = new URL(window.location.href);
        url.searchParams.set('branch', branchId);
        window.location.href = url.toString();
    }

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

    console.log('%c💊 Braick Dispensary - View Prescription (BLUE THEME)', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c📋 Prescription: <?= htmlspecialchars($prescription['prescription_number'] ?? 'N/A') ?>', 'font-size:13px; color:#059669;');
    console.log('%c👤 Patient: <?= htmlspecialchars($prescription['patient_name'] ?? 'N/A') ?>', 'font-size:13px; color:#0B5ED7;');
    console.log('%c👨‍⚕️ Doctor: <?= htmlspecialchars($prescription['doctor_name'] ?? 'N/A') ?>', 'font-size:13px; color:#7C3AED;');
    console.log('%c📦 Items: <?= count($prescription_items) ?>', 'font-size:13px; color:#F59E0B;');
</script>

</body>
</html>