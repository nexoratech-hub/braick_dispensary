<?php
// ================================================================
// FILE: frontend/pages/admin/view_otc_sale.php
// SUPER ADMIN - VIEW OTC SALE DETAILS WITH ITEMS
// FIXED: Properly fetches OTC sale items from database
// REMOVED: Print Receipt button & Delete button
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

// ================================================================
// CHECK IF USER IS ADMIN
// ================================================================
if ($_SESSION['role'] !== 'admin') {
    $role = $_SESSION['role'];
    switch ($role) {
        case 'doctor': header('Location: ../doctor/dashboard.php'); break;
        case 'reception': header('Location: ../reception/dashboard.php'); break;
        case 'pharmacy': header('Location: ../pharmacy/dashboard.php'); break;
        case 'laboratory': header('Location: ../laboratory/dashboard.php'); break;
        case 'cashier': header('Location: ../cashier/dashboard.php'); break;
        default: header('Location: ../login.php'); break;
    }
    exit;
}

// ================================================================
// GET SESSION DATA
// ================================================================
$user_id = $_SESSION['user_id'] ?? 0;
$user_full_name = $_SESSION['full_name'] ?? 'Admin';
$user_role = $_SESSION['role'] ?? 'admin';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$username = $_SESSION['username'] ?? '';
$profile_pic = $_SESSION['profile_pic'] ?? '';

// ================================================================
// PROFILE PICTURE URL
// ================================================================
$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

$logo_path = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';
$dark_mode = isset($_COOKIE['dark_mode']) ? $_COOKIE['dark_mode'] : 'false';

// ================================================================
// INCLUDE DATABASE
// ================================================================
require_once __DIR__ . '/../../../backend/config/database.php';
require_once __DIR__ . '/../../../backend/helpers/functions.php';

$db = Database::getInstance()->getConnection();

// ================================================================
// GET PARAMETERS
// ================================================================
$sale_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$branch_id = isset($_GET['branch']) ? (int)$_GET['branch'] : 0;

if ($sale_id <= 0) {
    header('Location: otc_sales.php?branch=' . $branch_id . '&error=invalid_id');
    exit;
}

// ================================================================
// GET OTC SALE DETAILS
// ================================================================
$sql = "
    SELECT 
        os.*,
        b.name as branch_name,
        u.full_name as sold_by_name,
        COALESCE((SELECT COUNT(*) FROM otc_sale_items WHERE sale_id = os.id), 0) as total_items
    FROM otc_sales os
    LEFT JOIN branches b ON os.branch_id = b.id
    LEFT JOIN users u ON os.sold_by = u.id
    WHERE os.id = ?
";

$otc_sale = null;
try {
    $stmt = $db->prepare($sql);
    $stmt->execute([$sale_id]);
    $otc_sale = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching OTC sale: " . $e->getMessage());
}

if (!$otc_sale) {
    header('Location: otc_sales.php?branch=' . $branch_id . '&error=notfound');
    exit;
}

// ================================================================
// GET OTC SALE ITEMS
// ================================================================
$sale_items = [];
try {
    $stmt = $db->prepare("
        SELECT 
            osi.*,
            mi.medication_name,
            mi.unit,
            mi.batch_number,
            mi.selling_price as current_price
        FROM otc_sale_items osi
        LEFT JOIN medications_inventory mi ON osi.inventory_id = mi.id
        WHERE osi.sale_id = ?
        ORDER BY osi.id ASC
    ");
    $stmt->execute([$sale_id]);
    $sale_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($sale_items)) {
        $stmt = $db->prepare("
            SELECT 
                id,
                sale_id,
                inventory_id,
                medicine_name,
                quantity,
                unit_price,
                total_price,
                created_at
            FROM otc_sale_items 
            WHERE sale_id = ?
            ORDER BY id ASC
        ");
        $stmt->execute([$sale_id]);
        $sale_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
} catch (Exception $e) {
    error_log("Error fetching OTC sale items: " . $e->getMessage());
    $sale_items = [];
}

// ================================================================
// GET UNREAD NOTIFICATIONS
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
// GET BRANCHES FOR NAV
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
        'paid' => 'success',
        'pending' => 'warning',
        'cancelled' => 'danger',
        'partial' => 'warning'
    ];
    return $classes[$status] ?? 'secondary';
}

function format_currency($amount) {
    if ($amount == 0) {
        return 'TSh 0';
    }
    return 'TSh ' . number_format($amount, 0);
}

// ================================================================
// SASA NDIO HEADER INAINGIZWA
// ================================================================
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= $dark_mode === 'true' ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OTC Sale Details - Braick Dispensary</title>
    
    <link rel="icon" href="<?= $logo_path ?>" type="image/png">
    <link rel="shortcut icon" href="<?= $logo_path ?>" type="image/png">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <style>
        :root {
            --bg-body: #F1F5F9;
            --bg-card: #FFFFFF;
            --bg-nav: #FFFFFF;
            --text-primary: #1E293B;
            --text-secondary: #64748B;
            --border-color: #E2E8F0;
            --primary: #0B5ED7;
            --primary-dark: #0A4CA8;
            --primary-gradient: linear-gradient(135deg, #0B5ED7, #0A4CA8);
            --primary-gradient-strong: linear-gradient(135deg, #0A4CA8, #083C8A);
            --success: #059669;
            --danger: #DC2626;
            --warning: #D97706;
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
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', 'Segoe UI', sans-serif;
            background: var(--bg-body);
            color: var(--text-primary);
            transition: background 0.3s ease, color 0.3s ease;
        }
        
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
            transition: background 0.3s ease, border-color 0.3s ease;
        }
        .top-nav .search-wrapper {
            display: flex;
            align-items: center;
            background: var(--bg-body);
            border-radius: 10px;
            border: 2px solid var(--border-color);
            transition: all 0.3s;
            flex: 1;
            max-width: 500px;
        }
        .top-nav .search-wrapper:focus-within {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.15);
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
            border-radius: 0 10px 10px 0;
            cursor: pointer;
            font-size: 0.85rem;
            transition: all 0.3s;
            white-space: nowrap;
        }
        .top-nav .search-wrapper .search-btn:hover {
            background: var(--primary-dark);
        }
        .top-nav .branch-selector {
            border: 2px solid var(--border-color);
            border-radius: 10px;
            padding: 6px 12px;
            background: var(--bg-card);
            font-size: 0.82rem;
            font-weight: 500;
            cursor: pointer;
            outline: none;
            min-width: 160px;
            color: var(--text-primary);
            transition: all 0.3s;
        }
        .top-nav .branch-selector:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.15);
        }
        .top-nav .datetime {
            font-size: 0.78rem;
            color: var(--text-secondary);
            font-weight: 500;
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
            background: #059669;
            border-radius: 50%;
            border: 2px solid var(--bg-nav);
            animation: pulse-dot 2s infinite;
        }
        .notif-dot.has-notif { background: #EF4444; }
        .notif-dot.no-notif { background: #94A3B8; animation: none; }
        @keyframes pulse-dot { 0%, 100% { transform: scale(1); } 50% { transform: scale(1.2); } }
        .dark-toggle-btn {
            background: var(--bg-body);
            border: 2px solid var(--border-color);
            border-radius: 10px;
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
        
        .main-content {
            margin-left: 270px;
            margin-top: 68px;
            padding: 24px 28px;
            min-height: calc(100vh - 68px);
            transition: background 0.3s ease;
        }
        
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            bottom: 0;
            width: 270px;
            background: #0B4EA8;
            color: white;
            z-index: 50;
            overflow-y: auto;
            overflow-x: hidden;
            transition: transform 0.3s ease-in-out;
            transform: translateX(0);
            box-shadow: 4px 0 20px rgba(0,0,0,0.15);
        }
        .sidebar-brand {
            padding: 18px 16px 14px;
            border-bottom: 2px solid #0B3D8A;
            background: #0B4EA8;
            position: sticky;
            top: 0;
            z-index: 5;
        }
        .sidebar-brand .logo {
            width: 42px;
            height: 42px;
            border-radius: 10px;
            object-fit: cover;
            background: white;
            padding: 4px;
            border: 2px solid rgba(255,255,255,0.1);
        }
        .sidebar-brand .brand-text { color: white; font-weight: 700; font-size: 0.95rem; line-height: 1.2; }
        .sidebar-brand .brand-sub { color: #9EC5FE; font-size: 0.65rem; font-weight: 500; }
        .sidebar-nav { padding: 10px 8px 20px; }
        .sidebar-nav .nav-label {
            font-size: 0.5rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #6EA8FE;
            padding: 0 10px;
            margin: 12px 0 4px;
            font-weight: 700;
        }
        .sidebar-link {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 12px;
            border-radius: 8px;
            color: #D2E3FC;
            text-decoration: none;
            transition: all 0.25s ease;
            font-size: 0.8rem;
            font-weight: 500;
            margin: 1px 0;
            background: transparent;
            cursor: pointer;
            border: none;
            width: 100%;
            text-align: left;
            position: relative;
        }
        .sidebar-link:hover {
            background: #0AA84F;
            color: white;
            box-shadow: 0 4px 12px rgba(10, 168, 79, 0.35);
            transform: translateX(4px);
        }
        .sidebar-link.active {
            background: #0AA84F;
            color: white;
            box-shadow: 0 4px 12px rgba(10, 168, 79, 0.35);
        }
        .sidebar-link.logout-link {
            border-top: 2px solid rgba(255,255,255,0.08);
            padding-top: 10px;
            margin-top: 6px;
            color: #FCA5A5;
        }
        .sidebar-link.logout-link:hover {
            background: #DC2626;
            color: white;
            box-shadow: 0 4px 12px rgba(220, 38, 38, 0.4);
        }
        
        .footer {
            padding: 14px 0;
            border-top: 2px solid var(--border-color);
            margin-top: 20px;
            text-align: center;
            font-size: 0.7rem;
            color: var(--text-secondary);
            transition: border-color 0.3s ease, color 0.3s ease;
        }
        .footer .footer-brand { color: var(--primary); font-weight: 600; }
        
        .page-header-box {
            background: var(--primary-gradient);
            border-radius: 16px;
            padding: 20px 28px;
            margin-bottom: 24px;
            box-shadow: 0 6px 24px rgba(11, 94, 215, 0.2);
            position: relative;
            overflow: hidden;
        }
        .page-header-box::before {
            content: '';
            position: absolute;
            top: -60%;
            right: -10%;
            width: 350px;
            height: 350px;
            background: rgba(255,255,255,0.05);
            border-radius: 50%;
            pointer-events: none;
        }
        .page-header-box .page-title {
            color: white;
            font-size: 1.6rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
        }
        .page-header-box .page-title .role-badge-display {
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 3px 12px;
            border-radius: 20px;
            font-size: 0.6rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            backdrop-filter: blur(4px);
        }
        .page-header-box .page-subtitle {
            color: rgba(255,255,255,0.85);
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
            margin-top: 4px;
        }
        .page-header-box .page-subtitle strong {
            color: white;
            font-weight: 600;
        }
        .page-header-box .header-badge {
            background: rgba(255,255,255,0.12);
            color: white;
            padding: 3px 12px;
            border-radius: 20px;
            font-size: 0.65rem;
            font-weight: 500;
            backdrop-filter: blur(4px);
            display: inline-flex;
            align-items: center;
            gap: 5px;
            border: 1px solid rgba(255,255,255,0.1);
        }
        .page-header-box .header-badge i { opacity: 0.8; }
        .page-header-box .header-badge.revenue {
            background: rgba(251, 191, 36, 0.2);
            border-color: rgba(251, 191, 36, 0.3);
            color: #FBBF24;
        }
        
        .detail-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 24px;
        }
        .detail-card {
            background: var(--bg-card);
            border-radius: 16px;
            padding: 20px 24px;
            border: 2px solid var(--border-color);
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
        }
        .detail-card:hover {
            border-color: var(--primary);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .detail-card .detail-title {
            font-size: 0.7rem;
            font-weight: 600;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 12px;
            padding-bottom: 8px;
            border-bottom: 2px solid var(--border-color);
        }
        .detail-card .detail-title i {
            margin-right: 6px;
            color: var(--primary);
        }
        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid var(--border-color);
            font-size: 0.85rem;
        }
        .detail-row:last-child {
            border-bottom: none;
        }
        .detail-row .label {
            color: var(--text-secondary);
            font-weight: 500;
        }
        .detail-row .value {
            color: var(--text-primary);
            font-weight: 600;
            text-align: right;
        }
        .detail-row .value.success { color: var(--success); }
        .detail-row .value.danger { color: var(--danger); }
        .detail-row .value.warning { color: var(--warning); }
        .detail-row .value.primary { color: var(--primary); }
        
        .items-table-wrap {
            background: var(--bg-card);
            border-radius: 16px;
            border: 2px solid var(--border-color);
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
            margin-top: 20px;
        }
        .items-table-wrap .table-header {
            padding: 14px 20px;
            background: var(--primary-gradient-strong);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 8px;
        }
        .items-table-wrap .table-header .title {
            color: white;
            font-size: 0.9rem;
            font-weight: 600;
        }
        .items-table-wrap .table-header .title i { margin-right: 8px; }
        .items-table-wrap .table-header .count {
            color: rgba(255,255,255,0.8);
            font-size: 0.75rem;
        }
        .items-table-wrap table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.78rem;
        }
        .items-table-wrap table thead {
            background: var(--bg-body);
        }
        .items-table-wrap table th {
            padding: 10px 14px;
            text-align: left;
            font-weight: 600;
            color: var(--text-secondary);
            font-size: 0.6rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            border-bottom: 2px solid var(--border-color);
            white-space: nowrap;
        }
        .items-table-wrap table td {
            padding: 10px 14px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-primary);
            vertical-align: middle;
        }
        .items-table-wrap table tr:hover td {
            background: #F8FAFC;
        }
        [data-theme="dark"] .items-table-wrap table tr:hover td {
            background: #1E293B;
        }
        .items-table-wrap .table-footer {
            text-align: center;
            padding: 10px 0;
            font-size: 0.7rem;
            color: var(--text-secondary);
            border-top: 1px solid var(--border-color);
            display: flex;
            justify-content: center;
            gap: 12px;
            flex-wrap: wrap;
        }
        
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
        
        .btn-action {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 18px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.8rem;
            transition: all 0.3s ease;
            text-decoration: none;
            border: none;
            cursor: pointer;
        }
        .btn-action i { font-size: 0.85rem; }
        .btn-action:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        .btn-action-primary {
            background: var(--primary-gradient);
            color: white;
        }
        .btn-action-primary:hover {
            background: var(--primary-gradient-strong);
        }
        .btn-action-success {
            background: var(--success);
            color: white;
        }
        .btn-action-success:hover {
            background: #047857;
        }
        .btn-action-outline {
            background: transparent;
            color: var(--text-secondary);
            border: 2px solid var(--border-color);
        }
        .btn-action-outline:hover {
            border-color: var(--primary);
            color: var(--primary);
        }
        
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: var(--text-secondary);
        }
        .empty-state i {
            font-size: 3rem;
            color: var(--border-color);
            margin-bottom: 12px;
        }
        .empty-state h4 {
            font-size: 1rem;
            color: var(--text-primary);
            margin-bottom: 4px;
        }
        
        @media (max-width: 1024px) {
            .top-nav { left: 0; }
            .main-content { margin-left: 0; padding: 16px; }
            .sidebar { transform: translateX(-100%); }
            .sidebar.open { transform: translateX(0); }
        }
        @media (max-width: 768px) {
            .detail-grid { grid-template-columns: 1fr; }
            .page-header-box .page-title { font-size: 1.3rem; }
            .page-header-box { padding: 16px 18px; }
            .btn-action { padding: 6px 12px; font-size: 0.7rem; }
        }
        @media (max-width: 480px) {
            .main-content { padding: 10px; }
            .page-header-box .page-title { font-size: 1rem; flex-direction: column; align-items: flex-start; }
            .page-header-box .page-subtitle { font-size: 0.75rem; flex-direction: column; align-items: flex-start; gap: 4px; }
            .detail-card { padding: 14px 16px; }
            .items-table-wrap table { font-size: 0.65rem; }
            .items-table-wrap table th, .items-table-wrap table td { padding: 6px 8px; }
        }
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-fade-in-up {
            animation: fadeInUp 0.5s ease forwards;
            opacity: 0;
        }
        
        #sidebarOverlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 45;
            display: none;
            backdrop-filter: blur(2px);
        }
        @media (max-width: 1024px) {
            #sidebarOverlay.show { display: block; }
        }
    </style>
</head>
<body>

<div id="sidebarOverlay"></div>

<!-- ================================================================ -->
<!-- SIDEBAR -->
<!-- ================================================================ -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <div style="display:flex;align-items:center;gap:12px;">
            <img src="<?= $logo_path ?>" alt="Braick Logo" class="logo" 
                 onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2248%22 height=%2248%22%3E%3Crect width=%2248%22 height=%2248%22 fill=%22%230B4EA8%22 rx=%2212%22/%3E%3Ctext x=%2224%22 y=%2232%22 text-anchor=%22middle%22 fill=%22white%22 font-size=%2220%22 font-weight=%22bold%22%3EB%3C/text%3E%3C/svg%3E'">
            <div>
                <p class="brand-text">Braick Dispensary</p>
                <p class="brand-sub">Super Admin</p>
            </div>
        </div>
    </div>
    
    <nav class="sidebar-nav">
        <div class="nav-label">Main Menu</div>
        <a href="/dispensary_system/frontend/pages/admin/dashboard.php" class="sidebar-link"><i class="fas fa-home"></i> Dashboard</a>
        <a href="/dispensary_system/frontend/pages/admin/employees.php" class="sidebar-link"><i class="fas fa-users"></i> Employees</a>
        <a href="/dispensary_system/frontend/pages/admin/patients.php" class="sidebar-link"><i class="fas fa-user-injured"></i> Patients</a>
        
        <div class="nav-label">Modules</div>
        <a href="/dispensary_system/frontend/pages/admin/doctors_list.php" class="sidebar-link"><i class="fas fa-user-md"></i> Doctors</a>
        <a href="/dispensary_system/frontend/pages/admin/view_pharmacy.php" class="sidebar-link"><i class="fas fa-prescription"></i> Pharmacy</a>
        <a href="/dispensary_system/frontend/pages/admin/view_reception.php" class="sidebar-link"><i class="fas fa-headset"></i> Reception</a>
        <a href="/dispensary_system/frontend/pages/admin/view_laboratory.php" class="sidebar-link"><i class="fas fa-flask"></i> Laboratory</a>
        <a href="/dispensary_system/frontend/pages/admin/view_cashier.php" class="sidebar-link"><i class="fas fa-cash-register"></i> Cashier</a>
        
        <div class="nav-label">Management</div>
        <a href="/dispensary_system/frontend/pages/admin/branches.php" class="sidebar-link"><i class="fas fa-store-alt"></i> Branches</a>
        <a href="/dispensary_system/frontend/pages/admin/departments.php" class="sidebar-link"><i class="fas fa-building"></i> Departments</a>
        <a href="/dispensary_system/frontend/pages/admin/reports.php" class="sidebar-link"><i class="fas fa-chart-bar"></i> Reports</a>
        
        <div class="nav-label">Account</div>
        <a href="/dispensary_system/frontend/pages/admin/profile.php" class="sidebar-link"><i class="fas fa-user-circle"></i> Profile</a>
        <a href="/dispensary_system/frontend/pages/logout.php" class="sidebar-link logout-link"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </nav>
</aside>

<!-- ================================================================ -->
<!-- TOP NAVIGATION -->
<!-- ================================================================ -->
<nav class="top-nav">
    <div class="flex items-center gap-4 flex-1">
        <button id="sidebarToggle" class="icon-btn lg:hidden">
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
            <option value="all" <?= $branch_id == 0 ? 'selected' : '' ?>>🌐 All Branches</option>
            <?php foreach ($branches as $b): ?>
                <option value="<?= $b['id'] ?>" <?= $branch_id == $b['id'] ? 'selected' : '' ?>>
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
            <span class="notif-dot <?= $unread_notifications > 0 ? 'has-notif' : 'no-notif' ?>"></span>
        </button>
        
        <a href="profile.php">
            <img src="<?= $profile_pic_url ?>" alt="Profile" class="avatar"
                 onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2240%22 height=%2240%22%3E%3Crect width=%2240%22 height=%2240%22 fill=%22%230B5ED7%22 rx=%2250%25%22/%3E%3Ctext x=%2220%22 y=%2226%22 text-anchor=%22middle%22 fill=%22white%22 font-size=%2218%22 font-weight=%22bold%22%3E<?= strtoupper(substr($user_full_name, 0, 1)) ?>%3C/text%3E%3C/svg%3E'">
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
    <div class="page-header-box animate-fade-in-up" style="animation-delay:0.05s;">
        <div>
            <h1 class="page-title">
                <i class="fas fa-shopping-cart"></i>
                OTC Sale Details
                <span class="role-badge-display">ADMIN</span>
                <span style="background:rgba(255,255,255,0.15);padding:3px 14px;border-radius:20px;font-size:0.75rem;font-weight:500;">
                    <i class="fas fa-hashtag"></i> <?= htmlspecialchars($otc_sale['sale_number'] ?? 'N/A') ?>
                </span>
            </h1>
            <p class="page-subtitle">
                <strong><?= htmlspecialchars($otc_sale['customer_name'] ?? 'Walk-in Customer') ?></strong>
                <span class="header-badge">
                    <i class="fas fa-store-alt"></i> <?= htmlspecialchars($otc_sale['branch_name'] ?? 'N/A') ?>
                </span>
                <span class="header-badge revenue">
                    <i class="fas fa-money-bill-wave"></i> <?= format_currency($otc_sale['net_amount'] ?? 0) ?>
                </span>
                <span class="header-badge" style="background:rgba(52,211,153,0.2);border-color:rgba(52,211,153,0.3);color:#6EE7B7;">
                    <i class="fas fa-<?= ($otc_sale['payment_status'] ?? 'pending') == 'paid' ? 'check-circle' : 'clock' ?>"></i>
                    <?= ucfirst($otc_sale['payment_status'] ?? 'Pending') ?>
                </span>
            </p>
        </div>
        <div style="position:relative;z-index:1;">
            <a href="otc_sales.php?branch=<?= $branch_id ?>" class="btn-action btn-action-outline" style="color:white;border-color:rgba(255,255,255,0.3);">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- DETAIL GRID -->
    <!-- ================================================================ -->
    <div class="detail-grid animate-fade-in-up" style="animation-delay:0.1s;">
        
        <!-- Sale Information -->
        <div class="detail-card">
            <div class="detail-title"><i class="fas fa-info-circle"></i> Sale Information</div>
            <div class="detail-row">
                <span class="label"><i class="fas fa-hashtag"></i> Sale Number</span>
                <span class="value primary"><?= htmlspecialchars($otc_sale['sale_number'] ?? 'N/A') ?></span>
            </div>
            <div class="detail-row">
                <span class="label"><i class="fas fa-user"></i> Customer</span>
                <span class="value"><?= htmlspecialchars($otc_sale['customer_name'] ?? 'Walk-in Customer') ?></span>
            </div>
            <div class="detail-row">
                <span class="label"><i class="fas fa-phone"></i> Phone</span>
                <span class="value"><?= htmlspecialchars($otc_sale['customer_phone'] ?? 'N/A') ?></span>
            </div>
            <div class="detail-row">
                <span class="label"><i class="fas fa-store-alt"></i> Branch</span>
                <span class="value"><?= htmlspecialchars($otc_sale['branch_name'] ?? 'N/A') ?></span>
            </div>
            <div class="detail-row">
                <span class="label"><i class="fas fa-user-md"></i> Sold By</span>
                <span class="value"><?= htmlspecialchars($otc_sale['sold_by_name'] ?? 'N/A') ?></span>
            </div>
            <div class="detail-row">
                <span class="label"><i class="fas fa-calendar"></i> Date</span>
                <span class="value"><?= date('F d, Y h:i A', strtotime($otc_sale['created_at'] ?? 'now')) ?></span>
            </div>
        </div>
        
        <!-- Payment Information -->
        <div class="detail-card">
            <div class="detail-title"><i class="fas fa-credit-card"></i> Payment Information</div>
            <div class="detail-row">
                <span class="label"><i class="fas fa-money-bill-wave"></i> Total Amount</span>
                <span class="value primary"><?= format_currency($otc_sale['total_amount'] ?? 0) ?></span>
            </div>
            <div class="detail-row">
                <span class="label"><i class="fas fa-percent"></i> Discount</span>
                <span class="value warning">- <?= format_currency($otc_sale['discount_amount'] ?? 0) ?></span>
            </div>
            <div class="detail-row" style="border-bottom: 2px solid var(--border-color); padding-bottom: 10px;">
                <span class="label"><i class="fas fa-coins"></i> Net Amount</span>
                <span class="value success" style="font-size:1.2rem;"><?= format_currency($otc_sale['net_amount'] ?? 0) ?></span>
            </div>
            <div class="detail-row">
                <span class="label"><i class="fas fa-credit-card"></i> Payment Method</span>
                <span class="value"><?= ucfirst(str_replace('_', ' ', $otc_sale['payment_method'] ?? 'N/A')) ?></span>
            </div>
            <div class="detail-row">
                <span class="label"><i class="fas fa-circle"></i> Status</span>
                <span class="value">
                    <span class="badge badge-<?= getStatusBadge($otc_sale['payment_status'] ?? 'pending') ?>">
                        <?= ucfirst($otc_sale['payment_status'] ?? 'Pending') ?>
                    </span>
                </span>
            </div>
            <?php if (!empty($otc_sale['notes'])): ?>
            <div class="detail-row">
                <span class="label"><i class="fas fa-sticky-note"></i> Notes</span>
                <span class="value" style="text-align:right;max-width:60%;"><?= htmlspecialchars($otc_sale['notes']) ?></span>
            </div>
            <?php endif; ?>
            <?php if (!empty($otc_sale['bill_id'])): ?>
            <div class="detail-row">
                <span class="label"><i class="fas fa-file-invoice"></i> Bill ID</span>
                <span class="value primary">#<?= htmlspecialchars($otc_sale['bill_id']) ?></span>
            </div>
            <?php endif; ?>
        </div>
        
    </div>

    <!-- ================================================================ -->
    <!-- ITEMS TABLE -->
    <!-- ================================================================ -->
    <div class="items-table-wrap animate-fade-in-up" style="animation-delay:0.15s;">
        <div class="table-header">
            <span class="title"><i class="fas fa-boxes"></i> Sale Items (<?= count($sale_items) ?>)</span>
            <span class="count">Total Items: <?= number_format($otc_sale['total_items'] ?? 0) ?></span>
        </div>
        <?php if (count($sale_items) > 0): ?>
        <div style="overflow-x:auto;padding:0;">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Item Name</th>
                        <th>Batch / Unit</th>
                        <th class="text-right">Qty</th>
                        <th class="text-right">Unit Price</th>
                        <th class="text-right">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $counter = 1; foreach ($sale_items as $item): ?>
                    <tr>
                        <td><?= $counter++ ?></td>
                        <td>
                            <strong><?= htmlspecialchars($item['medication_name'] ?? $item['medicine_name'] ?? 'Unknown Item') ?></strong>
                            <?php if (!empty($item['unit'])): ?>
                                <span class="text-xs text-gray-400"> (<?= htmlspecialchars($item['unit']) ?>)</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-xs">
                            <?php if (!empty($item['batch_number'])): ?>
                                Batch: <?= htmlspecialchars($item['batch_number']) ?>
                            <?php else: ?>
                                <span class="text-gray-400">N/A</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-right font-semibold"><?= number_format($item['quantity'] ?? 0) ?></td>
                        <td class="text-right"><?= format_currency($item['unit_price'] ?? 0) ?></td>
                        <td class="text-right font-semibold text-blue-600"><?= format_currency($item['total_price'] ?? 0) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr style="border-top:2px solid var(--border-color);font-weight:700;">
                        <td colspan="5" style="text-align:right;padding:10px 14px;">Subtotal:</td>
                        <td style="padding:10px 14px;color:var(--primary);font-size:1.1rem;">
                            <?= format_currency($otc_sale['total_amount'] ?? 0) ?>
                        </td>
                    </tr>
                    <tr style="font-weight:600;">
                        <td colspan="5" style="text-align:right;padding:6px 14px;color:var(--warning);">Discount:</td>
                        <td style="padding:6px 14px;color:var(--warning);">
                            - <?= format_currency($otc_sale['discount_amount'] ?? 0) ?>
                        </td>
                    </tr>
                    <tr style="font-weight:700;background:var(--primary-bg);">
                        <td colspan="5" style="text-align:right;padding:8px 14px;color:var(--success);font-size:1.2rem;">Net Total:</td>
                        <td style="padding:8px 14px;color:var(--success);font-size:1.2rem;">
                            <?= format_currency($otc_sale['net_amount'] ?? 0) ?>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-box-open"></i>
            <h4>No Items Found</h4>
            <p class="text-sm text-gray-400">No items were found for this OTC sale.</p>
            <p class="text-xs text-gray-400 mt-2">Sale ID: <?= $sale_id ?> | Total Items: <?= $otc_sale['total_items'] ?? 0 ?></p>
        </div>
        <?php endif; ?>
        <div class="table-footer">
            <?php if (($otc_sale['payment_status'] ?? '') == 'pending'): ?>
                <a href="process_otc_payment.php?id=<?= $otc_sale['id'] ?>&branch=<?= $branch_id ?>" 
                   class="btn-action btn-action-success">
                    <i class="fas fa-credit-card"></i> Process Payment
                </a>
            <?php endif; ?>
            <!-- PRINT RECEIPT BUTTON REMOVED -->
            <!-- DELETE BUTTON REMOVED -->
            <a href="otc_sales.php?branch=<?= $branch_id ?>" 
               class="btn-action btn-action-outline">
                <i class="fas fa-arrow-left"></i> Back to OTC Sales
            </a>
        </div>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            OTC Sale Details - <?= htmlspecialchars($otc_sale['sale_number'] ?? 'N/A') ?>
            <span class="text-gray-300 mx-2">|</span>
            <span id="footerTime"><?= date('H:i:s') ?></span>
            <span class="text-gray-300 mx-2">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

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
    // SIDEBAR TOGGLE
    // ================================================================
    var sidebar = document.getElementById('sidebar');
    var sidebarToggle = document.getElementById('sidebarToggle');
    var overlay = document.getElementById('sidebarOverlay');
    
    function toggleSidebar() {
        sidebar.classList.toggle('open');
        overlay.classList.toggle('show');
        document.body.style.overflow = sidebar.classList.contains('open') ? 'hidden' : '';
    }
    
    sidebarToggle?.addEventListener('click', function(e) {
        e.stopPropagation();
        toggleSidebar();
    });
    
    overlay?.addEventListener('click', function() {
        sidebar.classList.remove('open');
        overlay.classList.remove('show');
        document.body.style.overflow = '';
    });
    
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && sidebar.classList.contains('open')) {
            sidebar.classList.remove('open');
            overlay.classList.remove('show');
            document.body.style.overflow = '';
        }
    });
    
    window.addEventListener('resize', function() {
        if (window.innerWidth > 1024 && sidebar.classList.contains('open')) {
            sidebar.classList.remove('open');
            overlay.classList.remove('show');
            document.body.style.overflow = '';
        }
    });

    // ================================================================
    // BRANCH SWITCHER
    // ================================================================
    function switchBranch(branchId) {
        var url = new URL(window.location.href);
        url.searchParams.set('branch', branchId);
        url.searchParams.delete('error');
        window.location.href = url.toString();
    }

    // ================================================================
    // SEARCH
    // ================================================================
    var searchBtn = document.getElementById('searchBtn');
    var searchInput = document.getElementById('searchInput');
    
    function performSearch() {
        var query = searchInput.value.trim();
        if (query.length > 0) {
            var url = new URL(window.location.href);
            url.searchParams.set('search', query);
            window.location.href = url.toString();
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

    console.log('%c🏥 Braick Dispensary - View OTC Sale', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c📋 Sale: <?= htmlspecialchars($otc_sale['sale_number'] ?? 'N/A') ?>', 'font-size:13px; color:#059669;');
    console.log('%c👤 Customer: <?= htmlspecialchars($otc_sale['customer_name'] ?? 'Walk-in') ?>', 'font-size:13px; color:#059669;');
    console.log('%c💰 Net Amount: <?= format_currency($otc_sale['net_amount'] ?? 0) ?>', 'font-size:13px; color:#0B5ED7;');
    console.log('%c📊 Status: <?= ucfirst($otc_sale['payment_status'] ?? 'Pending') ?>', 'font-size:13px; color:#D97706;');
    console.log('%c📦 Items: <?= count($sale_items) ?>', 'font-size:13px; color:#7C3AED;');
    console.log('%c❌ Print Receipt Button: REMOVED', 'font-size:13px; color:#DC2626;');
    console.log('%c❌ Delete Button: REMOVED', 'font-size:13px; color:#DC2626;');
</script>

</body>
</html>