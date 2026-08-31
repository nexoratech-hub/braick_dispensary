<?php
// ================================================================
// FILE: frontend/pages/admin/inventory.php
// BRAICK DISPENSARY - INVENTORY MANAGEMENT
// COMPLETE WITH MEDICATIONS, EQUIPMENT & STOCK MOVEMENTS
// ================================================================

// Start session
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
// GET ADMIN DATA
// ================================================================
$user_id = $_SESSION['user_id'];
$user_full_name = $_SESSION['full_name'] ?? 'Admin';
$user_role = $_SESSION['role'] ?? 'admin';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$username = $_SESSION['username'] ?? '';
$profile_pic = $_SESSION['profile_pic'] ?? '';

// ================================================================
// INCLUDE DATABASE
// ================================================================
require_once __DIR__ . '/../../../backend/config/database.php';
require_once __DIR__ . '/../../../backend/helpers/functions.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database connection error: " . $e->getMessage());
}

// ================================================================
// GET BRANCHES
// ================================================================
$branches_list = [];
$stmt = $db->query("SELECT id, name, location FROM branches WHERE status = 'active' ORDER BY name");
$branches_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// GET SELECTED BRANCH
// ================================================================
$selected_branch_id = $_GET['branch'] ?? 'all';
$selected_tab = $_GET['tab'] ?? 'medications';

// ================================================================
// GET STATISTICS
// ================================================================
// Total Medications
$total_medications = 0;
$stmt = $db->query("SELECT COUNT(*) as count FROM medications_inventory WHERE status = 'active'");
$total_medications = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// Total Equipment
$total_equipment = 0;
$stmt = $db->query("SELECT COUNT(*) as count FROM medical_equipment WHERE status = 'active'");
$total_equipment = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// Low Stock Medications
$low_stock_meds = 0;
$stmt = $db->query("SELECT COUNT(*) as count FROM medications_inventory WHERE quantity <= reorder_level AND quantity > 0 AND status = 'active'");
$low_stock_meds = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// Low Stock Equipment
$low_stock_equip = 0;
$stmt = $db->query("SELECT COUNT(*) as count FROM medical_equipment WHERE quantity <= reorder_level AND quantity > 0 AND status = 'active'");
$low_stock_equip = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// Out of Stock
$out_of_stock_meds = 0;
$stmt = $db->query("SELECT COUNT(*) as count FROM medications_inventory WHERE quantity = 0 AND status = 'active'");
$out_of_stock_meds = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

$out_of_stock_equip = 0;
$stmt = $db->query("SELECT COUNT(*) as count FROM medical_equipment WHERE quantity = 0 AND status = 'active'");
$out_of_stock_equip = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// Expired items
$expired_meds = 0;
$stmt = $db->query("SELECT COUNT(*) as count FROM medications_inventory WHERE expiry_date < CURDATE() AND status = 'active'");
$expired_meds = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

$expired_equip = 0;
$stmt = $db->query("SELECT COUNT(*) as count FROM medical_equipment WHERE expiry_date < CURDATE() AND status = 'active'");
$expired_equip = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// ================================================================
// GET MEDICATIONS
// ================================================================
$medications = [];
$where_clause = "WHERE status = 'active'";
$params = [];

if ($selected_branch_id !== 'all') {
    $where_clause .= " AND branch_id = ?";
    $params[] = $selected_branch_id;
}

$stmt = $db->prepare("
    SELECT id, medication_name, category, unit, quantity, reorder_level, 
           unit_cost, selling_price, supplier, expiry_date, batch_number, 
           branch_id, status, created_at, updated_at
    FROM medications_inventory 
    $where_clause
    ORDER BY medication_name ASC
");
$stmt->execute($params);
$medications = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// GET EQUIPMENT
// ================================================================
$equipment = [];
$where_clause_equip = "WHERE status = 'active'";
$params_equip = [];

if ($selected_branch_id !== 'all') {
    $where_clause_equip .= " AND branch_id = ?";
    $params_equip[] = $selected_branch_id;
}

$stmt = $db->prepare("
    SELECT id, equipment_name, category, unit, quantity, reorder_level, 
           unit_cost, selling_price, supplier, expiry_date, batch_number, 
           branch_id, status, created_at, updated_at
    FROM medical_equipment 
    $where_clause_equip
    ORDER BY equipment_name ASC
");
$stmt->execute($params_equip);
$equipment = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// GET STOCK MOVEMENTS
// ================================================================
$stock_movements = [];
$where_clause_movement = "1=1";
$params_movement = [];

if ($selected_branch_id !== 'all') {
    $where_clause_movement .= " AND branch_id = ?";
    $params_movement[] = $selected_branch_id;
}

$stmt = $db->prepare("
    SELECT sm.*,
           m.medication_name,
           e.equipment_name,
           u.full_name as performed_by_name
    FROM stock_movements sm
    LEFT JOIN medications_inventory m ON sm.inventory_id = m.id
    LEFT JOIN medical_equipment e ON sm.equipment_id = e.id
    LEFT JOIN users u ON sm.performed_by = u.id
    WHERE $where_clause_movement
    ORDER BY sm.created_at DESC
    LIMIT 50
");
$stmt->execute($params_movement);
$stock_movements = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// BRANCH NAME LOOKUP
// ================================================================
$branch_names = [];
foreach ($branches_list as $b) {
    $branch_names[$b['id']] = $b['name'];
}

// ================================================================
// PROFILE PICTURE URL
// ================================================================
$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

$logo_url = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

// ================================================================
// INCLUDE HEADERS
// ================================================================
include_once '../../components/admin_header.php';
include_once '../../components/admin_sidebar.php';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory - Braick Dispensary</title>
    <link rel="icon" href="<?= $logo_url ?>" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        :root {
            --primary: #0B5ED7;
            --primary-dark: #0A4CA8;
            --primary-light: #6EA8FE;
            --primary-bg: #E8F0FE;
            --primary-gradient: linear-gradient(135deg, #0B5ED7, #0A4CA8);
            --success: #059669;
            --success-bg: #D1FAE5;
            --danger: #DC2626;
            --danger-bg: #FEE2E2;
            --warning: #D97706;
            --warning-bg: #FEF3C7;
            --bg-body: #F0F4F8;
            --bg-card: #FFFFFF;
            --bg-nav: #FFFFFF;
            --text-primary: #1E293B;
            --text-secondary: #64748B;
            --border-color: #E2E8F0;
            --radius: 12px;
            --radius-lg: 18px;
            --shadow-sm: 0 1px 2px rgba(0,0,0,0.05);
            --shadow-md: 0 4px 12px rgba(0,0,0,0.08);
            --shadow-lg: 0 10px 25px rgba(0,0,0,0.1);
        }
        
        [data-theme="dark"] {
            --bg-body: #0F172A;
            --bg-card: #1E293B;
            --bg-nav: #1E293B;
            --text-primary: #F1F5F9;
            --text-secondary: #94A3B8;
            --border-color: #334155;
            --primary: #3B82F6;
            --primary-bg: #1E3A5F;
            --shadow-md: 0 4px 12px rgba(0,0,0,0.3);
            --shadow-lg: 0 10px 25px rgba(0,0,0,0.4);
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Inter', 'Segoe UI', -apple-system, sans-serif;
            background: var(--bg-body);
            color: var(--text-primary);
            transition: background 0.3s ease, color 0.3s ease;
        }
        
        /* ================================================================ */
        /* TOP NAVIGATION */
        /* ================================================================ */
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
            backdrop-filter: blur(10px);
            box-shadow: var(--shadow-sm);
        }
        
        .top-nav .search-wrapper {
            display: flex;
            align-items: center;
            background: var(--bg-body);
            border-radius: var(--radius);
            border: 2px solid var(--border-color);
            flex: 1;
            max-width: 500px;
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
        
        .top-nav .search-wrapper .search-btn {
            background: var(--primary-gradient);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 0 var(--radius) var(--radius) 0;
            cursor: pointer;
            font-size: 0.85rem;
        }
        
        .top-nav .avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--border-color);
            cursor: pointer;
        }
        
        .top-nav .icon-btn {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-secondary);
            background: transparent;
            border: none;
            cursor: pointer;
            position: relative;
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
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .branch-selector {
            background: var(--bg-body);
            border: 2px solid var(--border-color);
            border-radius: var(--radius);
            padding: 6px 12px;
            font-size: 0.78rem;
            color: var(--text-primary);
            outline: none;
            cursor: pointer;
        }
        
        /* ================================================================ */
        /* MAIN CONTENT */
        /* ================================================================ */
        .main-content {
            margin-left: 270px;
            margin-top: 68px;
            padding: 28px 32px;
            min-height: calc(100vh - 68px);
        }
        
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
            box-shadow: 0 8px 32px rgba(10, 76, 168, 0.35);
            position: relative;
            overflow: hidden;
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
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
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
        
        /* ================================================================ */
        /* STATS CARDS */
        /* ================================================================ */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 28px;
        }
        
        .stat-card {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            padding: 20px 24px;
            border: 2px solid var(--border-color);
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 16px;
            box-shadow: var(--shadow-sm);
        }
        
        .stat-card:hover {
            border-color: var(--primary);
            transform: translateY(-3px);
            box-shadow: var(--shadow-md);
        }
        
        .stat-card .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.4rem;
            flex-shrink: 0;
        }
        
        .stat-card .stat-icon.blue { background: var(--primary-bg); color: var(--primary); }
        .stat-card .stat-icon.green { background: var(--success-bg); color: var(--success); }
        .stat-card .stat-icon.yellow { background: var(--warning-bg); color: var(--warning); }
        .stat-card .stat-icon.red { background: var(--danger-bg); color: var(--danger); }
        .stat-card .stat-icon.purple { background: #EDE9FE; color: #7C3AED; }
        
        [data-theme="dark"] .stat-card .stat-icon.blue { background: #1E3A5F; color: #6EA8FE; }
        [data-theme="dark"] .stat-card .stat-icon.green { background: #1A3A2A; color: #34D399; }
        [data-theme="dark"] .stat-card .stat-icon.yellow { background: #3A2A1A; color: #FBBF24; }
        [data-theme="dark"] .stat-card .stat-icon.red { background: #3A1A1A; color: #F87171; }
        [data-theme="dark"] .stat-card .stat-icon.purple { background: #2A1A3A; color: #9B4DCA; }
        
        .stat-card .stat-content { flex: 1; }
        .stat-card .stat-content .stat-number {
            font-size: 1.6rem;
            font-weight: 700;
            color: var(--text-primary);
            line-height: 1.2;
        }
        .stat-card .stat-content .stat-label {
            font-size: 0.7rem;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        /* ================================================================ */
        /* TABS */
        /* ================================================================ */
        .tabs-container {
            display: flex;
            gap: 4px;
            background: var(--bg-card);
            border-radius: var(--radius);
            padding: 4px;
            border: 2px solid var(--border-color);
            margin-bottom: 24px;
            overflow-x: auto;
        }
        
        .tab-btn {
            padding: 10px 24px;
            border: none;
            border-radius: 10px;
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            background: transparent;
            color: var(--text-secondary);
            white-space: nowrap;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .tab-btn:hover {
            background: var(--bg-body);
            color: var(--text-primary);
        }
        
        .tab-btn.active {
            background: var(--primary-gradient);
            color: white;
            box-shadow: 0 4px 12px rgba(11, 94, 215, 0.25);
        }
        
        .tab-btn .tab-badge {
            font-size: 0.6rem;
            padding: 1px 10px;
            border-radius: 12px;
            background: rgba(255,255,255,0.2);
            color: inherit;
        }
        
        .tab-btn.active .tab-badge {
            background: rgba(255,255,255,0.25);
        }
        
        .tab-content {
            display: none;
            animation: fadeIn 0.3s ease;
        }
        
        .tab-content.active { display: block; }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        /* ================================================================ */
        /* TABLE */
        /* ================================================================ */
        .table-card {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            border: 2px solid var(--border-color);
            overflow: hidden;
            box-shadow: var(--shadow-sm);
            transition: all 0.3s ease;
        }
        
        .table-card:hover {
            border-color: var(--primary-light);
            box-shadow: var(--shadow-md);
        }
        
        .table-header {
            padding: 16px 24px;
            border-bottom: 2px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
        }
        
        .table-header .table-title {
            font-size: 1rem;
            font-weight: 600;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .table-header .table-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .btn-sm {
            padding: 6px 14px;
            font-size: 0.75rem;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            text-decoration: none;
        }
        
        .btn-primary-sm {
            background: var(--primary-gradient);
            color: white;
            box-shadow: 0 2px 8px rgba(11, 94, 215, 0.2);
        }
        
        .btn-primary-sm:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(11, 94, 215, 0.3);
        }
        
        .btn-success-sm {
            background: var(--success);
            color: white;
        }
        
        .btn-success-sm:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(5, 150, 105, 0.3);
        }
        
        .btn-outline-sm {
            background: transparent;
            color: var(--text-secondary);
            border: 2px solid var(--border-color);
        }
        
        .btn-outline-sm:hover {
            border-color: var(--primary);
            color: var(--primary);
        }
        
        .table-responsive {
            overflow-x: auto;
            padding: 4px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.85rem;
        }
        
        table thead {
            background: var(--bg-body);
        }
        
        table thead th {
            padding: 12px 16px;
            text-align: left;
            font-size: 0.65rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--text-secondary);
            font-weight: 600;
            border-bottom: 2px solid var(--border-color);
            white-space: nowrap;
            position: sticky;
            top: 0;
            background: var(--bg-body);
        }
        
        table tbody td {
            padding: 10px 16px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-primary);
            vertical-align: middle;
        }
        
        table tbody tr:hover {
            background: var(--primary-bg);
        }
        
        [data-theme="dark"] table tbody tr:hover {
            background: #1E3A5F;
        }
        
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 2px 10px;
            border-radius: 20px;
            font-size: 0.6rem;
            font-weight: 600;
            color: white;
            letter-spacing: 0.02em;
        }
        
        .badge-success { background: var(--success); }
        .badge-danger { background: var(--danger); }
        .badge-warning { background: var(--warning); color: #1E293B; }
        .badge-info { background: var(--primary); }
        .badge-secondary { background: #64748B; }
        
        [data-theme="dark"] .badge-warning { color: #1E293B; }
        
        .stock-low { color: var(--warning); font-weight: 600; }
        .stock-out { color: var(--danger); font-weight: 600; }
        .stock-ok { color: var(--success); font-weight: 600; }
        
        .movement-in { color: var(--success); }
        .movement-out { color: var(--danger); }
        .movement-adjustment { color: var(--warning); }
        
        .footer {
            padding: 14px 0;
            border-top: 2px solid var(--border-color);
            margin-top: 24px;
            text-align: center;
            font-size: 0.7rem;
            color: var(--text-secondary);
        }
        
        .footer .footer-brand { color: var(--primary); font-weight: 700; }
        
        /* ================================================================ */
        /* RESPONSIVE */
        /* ================================================================ */
        @media (max-width: 1024px) {
            .top-nav { left: 0; }
            .main-content { margin-left: 0; padding: 16px; }
            .top-nav .search-wrapper { max-width: 300px; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
        }
        
        @media (max-width: 768px) {
            .top-nav .search-wrapper { max-width: 180px; }
            .top-nav .datetime { display: none; }
            .page-header { padding: 16px 18px; }
            .page-header .page-title { font-size: 1.3rem; }
            .stats-grid { grid-template-columns: 1fr 1fr; }
            .tab-btn { padding: 8px 14px; font-size: 0.75rem; }
        }
        
        @media (max-width: 480px) {
            .main-content { padding: 10px; }
            .page-header { flex-direction: column; align-items: flex-start !important; }
            .stats-grid { grid-template-columns: 1fr; }
            .table-header { flex-direction: column; align-items: stretch; }
            .table-actions { flex-wrap: wrap; }
            .table-actions .btn-sm { flex: 1; justify-content: center; }
        }
        
        @media print {
            .top-nav, .sidebar, .footer, .btn-sm, .page-header .btn-outline-light,
            .dark-toggle-btn, .icon-btn, .search-wrapper { display: none !important; }
            .main-content { margin: 0; padding: 20px; }
            .page-header { background: #0B5ED7 !important; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
            .page-title, .page-subtitle, .header-badge { color: white !important; }
        }
    </style>
</head>
<body>

<!-- ================================================================ -->
<!-- TOP NAVIGATION -->
<!-- ================================================================ -->
<nav class="top-nav">
    <div class="flex items-center gap-4 flex-1">
        <button id="sidebarToggle" class="lg:hidden icon-btn">
            <i class="fas fa-bars text-lg"></i>
        </button>
        
        <div class="search-wrapper">
            <i class="fas fa-search text-gray-400 ml-3"></i>
            <input type="text" id="searchInput" placeholder="Search inventory...">
            <button id="searchBtn" class="search-btn">
                <i class="fas fa-search mr-1"></i> Search
            </button>
        </div>
    </div>
    
    <div class="flex items-center gap-3">
        <select id="branchSelector" class="branch-selector" onchange="switchBranch(this.value)">
            <option value="all" <?= $selected_branch_id === 'all' ? 'selected' : '' ?>>🌐 All Branches</option>
            <?php foreach ($branches_list as $branch): ?>
                <option value="<?= $branch['id'] ?>" <?= $selected_branch_id == $branch['id'] ? 'selected' : '' ?>>
                    🏥 <?= htmlspecialchars($branch['name']) ?>
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
            <img src="<?= $profile_pic_url ?>" alt="Profile" class="avatar"
                 onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2240%22 height=%2240%22%3E%3Crect width=%2240%22 height=%2240%22 fill=%22%230B5ED7%22 rx=%2250%25%22/%3E%3Ctext x=%2220%22 y=%2226%22 text-anchor=%22middle%22 fill=%22white%22 font-size=%2218%22 font-weight=%22bold%22%3E<?= strtoupper(substr($user_full_name, 0, 1)) ?>%3C/text%3E%3C/svg%3E'">
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
                <i class="fas fa-boxes"></i>
                Inventory Management
                <span class="header-badge">
                    <i class="fas fa-store"></i>
                    <?= $selected_branch_id === 'all' ? 'All Branches' : ($branch_names[$selected_branch_id] ?? 'Unknown') ?>
                </span>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-cubes"></i>
                Manage medications, equipment and stock movements
                <span class="header-badge" style="background:rgba(52,211,153,0.2);border-color:rgba(52,211,153,0.3);color:#34D399;">
                    <i class="fas fa-pills"></i> <?= $total_medications ?> Medications
                </span>
                <span class="header-badge" style="background:rgba(251,191,36,0.2);border-color:rgba(251,191,36,0.3);color:#FBBF24;">
                    <i class="fas fa-tools"></i> <?= $total_equipment ?> Equipment
                </span>
            </p>
        </div>
        <div class="flex gap-2 flex-wrap" style="position:relative;z-index:1;">
            <a href="add_inventory.php?branch=<?= $selected_branch_id ?>&type=medication" class="btn-outline-light">
                <i class="fas fa-plus"></i> Add Medication
            </a>
            <a href="add_inventory.php?branch=<?= $selected_branch_id ?>&type=equipment" class="btn-outline-light">
                <i class="fas fa-plus"></i> Add Equipment
            </a>
        </div>
    </div>

    <!-- Stats -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon blue"><i class="fas fa-pills"></i></div>
            <div class="stat-content">
                <div class="stat-number"><?= $total_medications ?></div>
                <div class="stat-label">Total Medications</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon purple"><i class="fas fa-tools"></i></div>
            <div class="stat-content">
                <div class="stat-number"><?= $total_equipment ?></div>
                <div class="stat-label">Total Equipment</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon yellow"><i class="fas fa-exclamation-triangle"></i></div>
            <div class="stat-content">
                <div class="stat-number"><?= $low_stock_meds + $low_stock_equip ?></div>
                <div class="stat-label">Low Stock Items</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon red"><i class="fas fa-times-circle"></i></div>
            <div class="stat-content">
                <div class="stat-number"><?= $out_of_stock_meds + $out_of_stock_equip ?></div>
                <div class="stat-label">Out of Stock</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon green"><i class="fas fa-check-circle"></i></div>
            <div class="stat-content">
                <div class="stat-number"><?= ($total_medications + $total_equipment) - ($low_stock_meds + $low_stock_equip + $out_of_stock_meds + $out_of_stock_equip) ?></div>
                <div class="stat-label">In Stock</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon red"><i class="fas fa-calendar-times"></i></div>
            <div class="stat-content">
                <div class="stat-number"><?= $expired_meds + $expired_equip ?></div>
                <div class="stat-label">Expired Items</div>
            </div>
        </div>
    </div>

    <!-- Tabs -->
    <div class="tabs-container">
        <button class="tab-btn <?= $selected_tab === 'medications' ? 'active' : '' ?>" onclick="switchTab('medications')">
            <i class="fas fa-pills"></i> Medications
            <span class="tab-badge"><?= $total_medications ?></span>
        </button>
        <button class="tab-btn <?= $selected_tab === 'equipment' ? 'active' : '' ?>" onclick="switchTab('equipment')">
            <i class="fas fa-tools"></i> Equipment
            <span class="tab-badge"><?= $total_equipment ?></span>
        </button>
        <button class="tab-btn <?= $selected_tab === 'movements' ? 'active' : '' ?>" onclick="switchTab('movements')">
            <i class="fas fa-exchange-alt"></i> Stock Movements
            <span class="tab-badge"><?= count($stock_movements) ?></span>
        </button>
    </div>

    <!-- ================================================================ -->
    <!-- TAB 1: MEDICATIONS -->
    <!-- ================================================================ -->
    <div class="tab-content <?= $selected_tab === 'medications' ? 'active' : '' ?>" id="tab-medications">
        <div class="table-card">
            <div class="table-header">
                <div class="table-title">
                    <i class="fas fa-pills" style="color:var(--primary);"></i>
                    Medications Inventory
                    <span style="font-size:0.7rem;font-weight:400;color:var(--text-secondary);">
                        (<?= count($medications) ?> items)
                    </span>
                </div>
                <div class="table-actions">
                    <a href="add_inventory.php?branch=<?= $selected_branch_id ?>&type=medication" class="btn-sm btn-primary-sm">
                        <i class="fas fa-plus"></i> Add Medication
                    </a>
                    <button class="btn-sm btn-outline-sm" onclick="exportTable('medications')">
                        <i class="fas fa-file-export"></i> Export
                    </button>
                </div>
            </div>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Category</th>
                            <th>Branch</th>
                            <th>Stock</th>
                            <th>Reorder Level</th>
                            <th>Selling Price</th>
                            <th>Batch #</th>
                            <th>Expiry</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($medications) > 0): ?>
                            <?php foreach ($medications as $med): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($med['medication_name']) ?></strong></td>
                                    <td><?= htmlspecialchars($med['category'] ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars($branch_names[$med['branch_id']] ?? 'N/A') ?></td>
                                    <td>
                                        <?php 
                                            $stock_class = 'stock-ok';
                                            if ($med['quantity'] == 0) $stock_class = 'stock-out';
                                            elseif ($med['quantity'] <= $med['reorder_level']) $stock_class = 'stock-low';
                                        ?>
                                        <span class="<?= $stock_class ?>"><?= $med['quantity'] ?></span>
                                    </td>
                                    <td><?= $med['reorder_level'] ?></td>
                                    <td>TSh <?= number_format($med['selling_price'] ?? 0, 0) ?></td>
                                    <td><span style="font-family:monospace;font-size:0.7rem;"><?= htmlspecialchars($med['batch_number'] ?? 'N/A') ?></span></td>
                                    <td>
                                        <?php if ($med['expiry_date']): ?>
                                            <?php 
                                                $expiry = strtotime($med['expiry_date']);
                                                $today = time();
                                                $days_diff = floor(($expiry - $today) / (60 * 60 * 24));
                                            ?>
                                            <span style="color: <?= $days_diff < 30 ? 'var(--danger)' : ($days_diff < 90 ? 'var(--warning)' : 'var(--success)') ?>;">
                                                <?= date('M d, Y', strtotime($med['expiry_date'])) ?>
                                                <?php if ($days_diff < 30): ?>
                                                    <span class="badge badge-danger">Expiring Soon</span>
                                                <?php endif; ?>
                                            </span>
                                        <?php else: ?>
                                            <span style="color:var(--text-secondary);">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($med['quantity'] == 0): ?>
                                            <span class="badge badge-danger">Out of Stock</span>
                                        <?php elseif ($med['quantity'] <= $med['reorder_level']): ?>
                                            <span class="badge badge-warning">Low Stock</span>
                                        <?php else: ?>
                                            <span class="badge badge-success">In Stock</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="9" style="text-align:center;padding:30px;color:var(--text-secondary);">
                                    <i class="fas fa-inbox" style="font-size:2rem;display:block;margin-bottom:8px;opacity:0.3;"></i>
                                    No medications found
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- TAB 2: EQUIPMENT -->
    <!-- ================================================================ -->
    <div class="tab-content <?= $selected_tab === 'equipment' ? 'active' : '' ?>" id="tab-equipment">
        <div class="table-card">
            <div class="table-header">
                <div class="table-title">
                    <i class="fas fa-tools" style="color:var(--warning);"></i>
                    Equipment Inventory
                    <span style="font-size:0.7rem;font-weight:400;color:var(--text-secondary);">
                        (<?= count($equipment) ?> items)
                    </span>
                </div>
                <div class="table-actions">
                    <a href="add_inventory.php?branch=<?= $selected_branch_id ?>&type=equipment" class="btn-sm btn-primary-sm">
                        <i class="fas fa-plus"></i> Add Equipment
                    </a>
                    <button class="btn-sm btn-outline-sm" onclick="exportTable('equipment')">
                        <i class="fas fa-file-export"></i> Export
                    </button>
                </div>
            </div>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Category</th>
                            <th>Branch</th>
                            <th>Stock</th>
                            <th>Reorder Level</th>
                            <th>Selling Price</th>
                            <th>Batch #</th>
                            <th>Expiry</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($equipment) > 0): ?>
                            <?php foreach ($equipment as $eq): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($eq['equipment_name']) ?></strong></td>
                                    <td><?= htmlspecialchars($eq['category'] ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars($branch_names[$eq['branch_id']] ?? 'N/A') ?></td>
                                    <td>
                                        <?php 
                                            $stock_class = 'stock-ok';
                                            if ($eq['quantity'] == 0) $stock_class = 'stock-out';
                                            elseif ($eq['quantity'] <= $eq['reorder_level']) $stock_class = 'stock-low';
                                        ?>
                                        <span class="<?= $stock_class ?>"><?= $eq['quantity'] ?></span>
                                    </td>
                                    <td><?= $eq['reorder_level'] ?></td>
                                    <td>TSh <?= number_format($eq['selling_price'] ?? 0, 0) ?></td>
                                    <td><span style="font-family:monospace;font-size:0.7rem;"><?= htmlspecialchars($eq['batch_number'] ?? 'N/A') ?></span></td>
                                    <td>
                                        <?php if ($eq['expiry_date']): ?>
                                            <?php 
                                                $expiry = strtotime($eq['expiry_date']);
                                                $today = time();
                                                $days_diff = floor(($expiry - $today) / (60 * 60 * 24));
                                            ?>
                                            <span style="color: <?= $days_diff < 30 ? 'var(--danger)' : ($days_diff < 90 ? 'var(--warning)' : 'var(--success)') ?>;">
                                                <?= date('M d, Y', strtotime($eq['expiry_date'])) ?>
                                                <?php if ($days_diff < 30): ?>
                                                    <span class="badge badge-danger">Expiring Soon</span>
                                                <?php endif; ?>
                                            </span>
                                        <?php else: ?>
                                            <span style="color:var(--text-secondary);">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($eq['quantity'] == 0): ?>
                                            <span class="badge badge-danger">Out of Stock</span>
                                        <?php elseif ($eq['quantity'] <= $eq['reorder_level']): ?>
                                            <span class="badge badge-warning">Low Stock</span>
                                        <?php else: ?>
                                            <span class="badge badge-success">In Stock</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="9" style="text-align:center;padding:30px;color:var(--text-secondary);">
                                    <i class="fas fa-inbox" style="font-size:2rem;display:block;margin-bottom:8px;opacity:0.3;"></i>
                                    No equipment found
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- TAB 3: STOCK MOVEMENTS -->
    <!-- ================================================================ -->
    <div class="tab-content <?= $selected_tab === 'movements' ? 'active' : '' ?>" id="tab-movements">
        <div class="table-card">
            <div class="table-header">
                <div class="table-title">
                    <i class="fas fa-exchange-alt" style="color:var(--purple);"></i>
                    Stock Movements
                    <span style="font-size:0.7rem;font-weight:400;color:var(--text-secondary);">
                        (Last 50 movements)
                    </span>
                </div>
                <div class="table-actions">
                    <button class="btn-sm btn-outline-sm" onclick="exportTable('movements')">
                        <i class="fas fa-file-export"></i> Export
                    </button>
                </div>
            </div>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Item</th>
                            <th>Type</th>
                            <th>Movement</th>
                            <th>Qty</th>
                            <th>Previous</th>
                            <th>New</th>
                            <th>Reference</th>
                            <th>Performed By</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($stock_movements) > 0): ?>
                            <?php foreach ($stock_movements as $movement): ?>
                                <tr>
                                    <td style="font-size:0.75rem;white-space:nowrap;">
                                        <?= date('M d, Y H:i', strtotime($movement['created_at'])) ?>
                                    </td>
                                    <td>
                                        <?= htmlspecialchars($movement['medication_name'] ?? $movement['equipment_name'] ?? 'N/A') ?>
                                        <?php if ($movement['inventory_id']): ?>
                                            <span class="badge badge-info" style="font-size:0.5rem;">Med</span>
                                        <?php elseif ($movement['equipment_id']): ?>
                                            <span class="badge badge-warning" style="font-size:0.5rem;">Equip</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php 
                                            $type_class = 'movement-in';
                                            $type_label = 'IN';
                                            if ($movement['movement_type'] === 'out') {
                                                $type_class = 'movement-out';
                                                $type_label = 'OUT';
                                            } elseif ($movement['movement_type'] === 'adjustment') {
                                                $type_class = 'movement-adjustment';
                                                $type_label = 'ADJ';
                                            }
                                        ?>
                                        <span class="<?= $type_class ?>" style="font-weight:600;">
                                            <?= $type_label ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?= htmlspecialchars($movement['reference_type'] ?? 'N/A') ?>
                                        <?php if ($movement['reference_id']): ?>
                                            <span style="font-size:0.6rem;color:var(--text-secondary);">#<?= $movement['reference_id'] ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td><strong><?= $movement['quantity'] ?></strong></td>
                                    <td><?= $movement['previous_stock'] ?></td>
                                    <td><?= $movement['new_stock'] ?></td>
                                    <td style="max-width:150px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:0.75rem;color:var(--text-secondary);">
                                        <?= htmlspecialchars($movement['notes'] ?? 'N/A') ?>
                                    </td>
                                    <td style="font-size:0.75rem;">
                                        <?= htmlspecialchars($movement['performed_by_name'] ?? 'N/A') ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="9" style="text-align:center;padding:30px;color:var(--text-secondary);">
                                    <i class="fas fa-exchange-alt" style="font-size:2rem;display:block;margin-bottom:8px;opacity:0.3;"></i>
                                    No stock movements found
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Inventory Management
            <span class="text-gray-300 mx-2">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<!-- ================================================================ -->
<!-- TOAST -->
<!-- ================================================================ -->
<div id="toast" class="toast-custom" style="display:none;position:fixed;bottom:24px;right:24px;padding:14px 20px;border-radius:var(--radius);z-index:999;max-width:400px;transform:translateY(100px);opacity:0;transition:all 0.4s cubic-bezier(0.4,0,0.2,1);display:flex;align-items:center;gap:12px;color:white;box-shadow:var(--shadow-lg);">
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
            var tab = '<?= $selected_tab ?>';
            window.location.href = 'search.php?q=' + encodeURIComponent(query) + '&branch=' + branch + '&tab=' + tab;
        }
    }
    
    searchBtn?.addEventListener('click', performSearch);
    searchInput?.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') performSearch();
    });

    function switchBranch(branchId) {
        var url = new URL(window.location.href);
        url.searchParams.set('branch', branchId);
        url.searchParams.set('tab', '<?= $selected_tab ?>');
        window.location.href = url.toString();
    }

    function switchTab(tab) {
        var url = new URL(window.location.href);
        url.searchParams.set('tab', tab);
        window.location.href = url.toString();
    }

    function updateDateTime() {
        var now = new Date();
        var dtEl = document.getElementById('currentDateTime');
        if (dtEl) {
            dtEl.textContent = now.toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' }) + ' • ' + 
                now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true });
        }
    }
    updateDateTime();
    setInterval(updateDateTime, 1000);

    // ================================================================
    // TOAST
    // ================================================================
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
            setTimeout(function() { toast.style.display = 'none'; }, 400);
        }, 3500);
    }

    // ================================================================
    // EXPORT TABLE
    // ================================================================
    function exportTable(type) {
        var table = document.querySelector('#tab-' + type + ' table');
        if (!table) return;
        
        var csv = [];
        var rows = table.querySelectorAll('tr');
        
        for (var i = 0; i < rows.length; i++) {
            var row = [];
            var cols = rows[i].querySelectorAll('td, th');
            
            for (var j = 0; j < cols.length; j++) {
                var text = cols[j].textContent.trim().replace(/,/g, '');
                row.push('"' + text + '"');
            }
            
            csv.push(row.join(','));
        }
        
        var blob = new Blob([csv.join('\n')], { type: 'text/csv' });
        var url = window.URL.createObjectURL(blob);
        var a = document.createElement('a');
        a.href = url;
        a.download = 'inventory_' + type + '_' + new Date().toISOString().slice(0,10) + '.csv';
        a.click();
        window.URL.revokeObjectURL(url);
        
        showToast('✅ Exported', type + ' exported successfully!', 'success');
    }

    console.log('%c🏥 Braick - Inventory Management', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c📦 Medications: <?= $total_medications ?>', 'font-size:13px; color:#059669;');
    console.log('%c🔧 Equipment: <?= $total_equipment ?>', 'font-size:13px; color:#D97706;');
    console.log('%c📊 Branch: <?= $selected_branch_id === 'all' ? 'All' : $branch_names[$selected_branch_id] ?? 'Unknown' ?>', 'font-size:13px; color:#6EA8FE;');
</script>

</body>
</html>