<?php
// ================================================================
// FILE: frontend/pages/admin/pharmacy_inventory.php
// SUPER ADMIN - VIEW PHARMACY INVENTORY
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
$pharmacy_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$selected_branch_id = $_GET['branch'] ?? 'all';
$filter = $_GET['filter'] ?? 'all'; // all, outofstock, lowstock, expired, expiring
$search = $_GET['search'] ?? '';

if ($pharmacy_id <= 0) {
    header('Location: pharmacies.php?branch=' . $selected_branch_id . '&error=invalid_id');
    exit;
}

// ================================================================
// FETCH PHARMACY DETAILS
// ================================================================
$stmt = $db->prepare("SELECT * FROM branches WHERE id = ?");
$stmt->execute([$pharmacy_id]);
$pharmacy = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$pharmacy) {
    header('Location: pharmacies.php?branch=' . $selected_branch_id . '&error=notfound');
    exit;
}

// ================================================================
// BUILD INVENTORY QUERY
// ================================================================
$sql = "
    SELECT 
        id,
        medication_name,
        category,
        unit,
        quantity,
        reorder_level,
        unit_cost,
        selling_price,
        supplier,
        expiry_date,
        batch_number,
        status,
        created_at,
        updated_at
    FROM medications_inventory
    WHERE branch_id = ?
";

// Apply filters
$params = [$pharmacy_id];

if ($filter === 'outofstock') {
    $sql .= " AND quantity <= 0";
} elseif ($filter === 'lowstock') {
    $sql .= " AND quantity <= reorder_level AND quantity > 0";
} elseif ($filter === 'expired') {
    $sql .= " AND expiry_date < CURDATE()";
} elseif ($filter === 'expiring') {
    $sql .= " AND expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)";
}

if (!empty($search)) {
    $sql .= " AND (medication_name LIKE ? OR category LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$sql .= " ORDER BY medication_name ASC";

$inventory_items = [];
try {
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $inventory_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $inventory_items = [];
}

// ================================================================
// CALCULATE STATISTICS
// ================================================================
$total_items = count($inventory_items);
$total_quantity = 0;
$total_value = 0;
$out_of_stock = 0;
$low_stock = 0;
$expired = 0;
$expiring_soon = 0;
$in_stock = 0;

foreach ($inventory_items as $item) {
    $qty = $item['quantity'] ?? 0;
    $total_quantity += $qty;
    $total_value += ($qty * ($item['selling_price'] ?? 0));
    
    if ($qty <= 0) {
        $out_of_stock++;
    } elseif ($qty <= ($item['reorder_level'] ?? 0)) {
        $low_stock++;
    } else {
        $in_stock++;
    }
    
    $expiry = $item['expiry_date'] ?? null;
    if ($expiry) {
        $expiry_time = strtotime($expiry);
        if ($expiry_time < time()) {
            $expired++;
        } elseif ($expiry_time < strtotime('+30 days')) {
            $expiring_soon++;
        }
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
        'active' => 'success',
        'inactive' => 'danger',
        'pending' => 'warning',
        'dispensed' => 'success',
        'confirmed' => 'info',
        'cancelled' => 'danger',
        'paid' => 'success',
        'partial' => 'warning'
    ];
    return $classes[$status] ?? 'secondary';
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
    <title>Pharmacy Inventory - Braick Dispensary</title>
    
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
            --primary-gradient-hover: linear-gradient(135deg, #0A4CA8, #083C8A);
            
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
            --shadow-xl: 0 20px 40px rgba(0,0,0,0.12);
            
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
            --shadow-xl: 0 20px 40px rgba(0,0,0,0.5);
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
           STATS CARDS - 6 BLUE THEME CARDS
           ================================================================ */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            gap: 16px;
            margin-bottom: 24px;
        }
        
        .stat-card {
            background: var(--bg-card);
            border-radius: var(--radius);
            padding: 16px 18px;
            border: 2px solid var(--border-color);
            display: flex;
            align-items: center;
            gap: 12px;
            transition: all 0.3s ease;
            box-shadow: var(--shadow-sm);
            text-decoration: none;
            color: inherit;
            position: relative;
            overflow: hidden;
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: var(--primary-gradient);
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .stat-card:hover {
            border-color: var(--primary);
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
        }
        
        .stat-card:hover::before {
            opacity: 1;
        }
        
        .stat-card .stat-content {
            flex: 1;
        }
        
        .stat-icon {
            width: 44px;
            height: 44px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            flex-shrink: 0;
            transition: all 0.3s ease;
        }
        
        .stat-card:hover .stat-icon {
            transform: scale(1.05);
        }
        
        .stat-icon.blue { background: var(--primary-bg); color: var(--primary); }
        .stat-icon.green { background: #ECFDF5; color: #059669; }
        .stat-icon.orange { background: #FFFBEB; color: #F59E0B; }
        .stat-icon.red { background: #FEF2F2; color: #DC2626; }
        .stat-icon.purple { background: #F5F3FF; color: #7C3AED; }
        .stat-icon.teal { background: #ECFDF5; color: #0D9488; }
        .stat-icon.amber { background: #FFFBEB; color: #D97706; }
        .stat-icon.rose { background: #FFF1F2; color: #E11D48; }
        .stat-icon.cyan { background: #ECFEFF; color: #0891B2; }
        
        [data-theme="dark"] .stat-icon.blue { background: #1E3A5F; color: #3B82F6; }
        [data-theme="dark"] .stat-icon.green { background: #1A3A2A; color: #34D399; }
        [data-theme="dark"] .stat-icon.orange { background: #3D2E0A; color: #FBBF24; }
        [data-theme="dark"] .stat-icon.red { background: #3A1A1A; color: #F87171; }
        [data-theme="dark"] .stat-icon.purple { background: #2D1B4E; color: #A78BFA; }
        [data-theme="dark"] .stat-icon.teal { background: #1A3A2A; color: #2DD4BF; }
        [data-theme="dark"] .stat-icon.amber { background: #3D2E0A; color: #FBBF24; }
        [data-theme="dark"] .stat-icon.rose { background: #3A1A1A; color: #FB7185; }
        [data-theme="dark"] .stat-icon.cyan { background: #0A2A3A; color: #22D3EE; }
        
        .stat-label {
            font-size: 0.6rem;
            color: var(--text-secondary);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin: 0;
        }
        
        .stat-value {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--text-primary);
            margin: 0;
            line-height: 1.2;
        }
        
        .stat-value.blue-text { color: var(--primary); }
        .stat-value.green-text { color: #059669; }
        .stat-value.orange-text { color: #F59E0B; }
        .stat-value.red-text { color: #DC2626; }
        .stat-value.purple-text { color: #7C3AED; }
        .stat-value.teal-text { color: #0D9488; }
        .stat-value.amber-text { color: #D97706; }
        .stat-value.rose-text { color: #E11D48; }
        .stat-value.cyan-text { color: #0891B2; }
        
        .stat-sub {
            font-size: 0.55rem;
            color: var(--text-secondary);
            margin-top: 1px;
        }
        
        .stat-arrow {
            opacity: 0;
            transition: all 0.3s ease;
            color: var(--primary);
            font-size: 0.7rem;
            flex-shrink: 0;
        }
        
        .stat-card:hover .stat-arrow {
            opacity: 1;
            transform: translateX(4px);
        }
        
        /* ================================================================
           BADGES
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
        .badge-outline {
            background: transparent;
            border: 2px solid var(--border-color);
            color: var(--text-secondary);
        }
        
        [data-theme="dark"] .badge-warning { color: #1E293B; }
        
        /* ================================================================
           FILTERS
           ================================================================ */
        .filter-bar {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 20px;
            align-items: center;
            background: var(--bg-card);
            padding: 16px 20px;
            border-radius: var(--radius);
            border: 2px solid var(--border-color);
            box-shadow: var(--shadow-sm);
        }
        
        .filter-bar select, .filter-bar input {
            background: var(--bg-body);
            border: 2px solid var(--border-color);
            border-radius: var(--radius);
            padding: 8px 14px;
            font-size: 0.8rem;
            color: var(--text-primary);
            outline: none;
            transition: all 0.3s;
            min-width: 150px;
        }
        
        .filter-bar select:focus, .filter-bar input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(11, 94, 215, 0.1);
        }
        
        .filter-bar .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 18px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.8rem;
            transition: all 0.3s ease;
            cursor: pointer;
            border: none;
            text-decoration: none;
        }
        
        .btn-primary {
            background: var(--primary-gradient);
            color: white;
        }
        
        .btn-primary:hover {
            background: var(--primary-gradient-hover);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3);
        }
        
        .btn-outline {
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
            padding: 4px 12px;
            font-size: 0.7rem;
            border-radius: 6px;
        }
        
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
            border: 2px solid var(--border-color);
            overflow: hidden;
            transition: all 0.3s ease;
            box-shadow: var(--shadow-sm);
            margin-bottom: 24px;
        }
        
        .card:hover {
            border-color: var(--primary);
            box-shadow: var(--shadow-md);
        }
        
        .card-header {
            padding: 16px 24px;
            background: var(--bg-body);
            border-bottom: 2px solid var(--border-color);
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
           EMPTY STATE
           ================================================================ */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-secondary);
        }
        
        .empty-state i {
            font-size: 3rem;
            color: var(--border-color);
            margin-bottom: 16px;
        }
        
        .empty-state h3 {
            font-size: 1.2rem;
            color: var(--text-primary);
            margin-bottom: 8px;
        }
        
        /* ================================================================
           FOOTER
           ================================================================ */
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
            font-weight: 500;
        }
        
        /* ================================================================
           RESPONSIVE
           ================================================================ */
        @media (max-width: 1200px) {
            .stats-grid { grid-template-columns: repeat(3, 1fr); }
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
            .stats-grid { grid-template-columns: 1fr 1fr; }
            .filter-bar { flex-direction: column; align-items: stretch; }
            .filter-bar select, .filter-bar input { width: 100%; min-width: unset; }
        }
        
        @media (max-width: 480px) {
            .main-content { padding: 10px; }
            .stats-grid { grid-template-columns: 1fr; }
            .page-header { flex-direction: column; align-items: flex-start !important; }
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
        
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }
        
        .stat-card:hover .stat-icon {
            animation: pulse 0.5s ease;
        }
        
        /* ================================================================
           PRINT STYLES
           ================================================================ */
        @media print {
            .top-nav, .sidebar, .btn, .dark-toggle-btn, .icon-btn,
            .search-wrapper, .page-header .btn-outline-light,
            .footer, #sidebarToggle, .filter-bar { display: none !important; }
            .main-content { margin: 0; padding: 20px; }
            .card { break-inside: avoid; box-shadow: none !important; border: 1px solid #ddd; }
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
            .stat-card { border: 1px solid #ddd !important; box-shadow: none !important; }
            .stat-card::before { display: none !important; }
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
            <input type="text" id="searchInput" placeholder="Search inventory..." value="<?= htmlspecialchars($search) ?>">
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
                <i class="fas fa-boxes"></i>
                Pharmacy Inventory
                <span class="role-badge-display">ADMIN</span>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-store-alt"></i>
                <strong><?= htmlspecialchars($pharmacy['name']) ?></strong>
                <span class="header-badge" style="background:rgba(52,211,153,0.2);border-color:rgba(52,211,153,0.3);color:#34D399;">
                    <i class="fas fa-pills"></i> <?= number_format($total_items) ?> Items
                </span>
                <span class="header-badge" style="background:rgba(251,191,36,0.2);border-color:rgba(251,191,36,0.3);color:#FBBF24;">
                    <i class="fas fa-money-bill-wave"></i> TSh <?= number_format($total_value, 0) ?> Stock Value
                </span>
                <?php if ($filter !== 'all'): ?>
                    <span class="header-badge" style="background:rgba(96,165,250,0.2);border-color:rgba(96,165,250,0.3);color:#60A5FA;">
                        <i class="fas fa-filter"></i> Filter: <?= ucfirst(str_replace('_', ' ', $filter)) ?>
                    </span>
                <?php endif; ?>
            </p>
        </div>
        <div class="flex gap-2 flex-wrap" style="position:relative;z-index:1;">
            <a href="add_inventory.php?id=<?= $pharmacy['id'] ?>&branch=<?= $selected_branch_id ?>" class="btn-outline-light">
                <i class="fas fa-plus"></i> Add Item
            </a>
            <a href="view_pharmacy.php?id=<?= $pharmacy['id'] ?>&branch=<?= $selected_branch_id ?>" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- 6 STATISTICS CARDS - BLUE THEME -->
    <!-- ================================================================ -->
    <div class="stats-grid animate-fade-in-up" style="animation-delay:0.05s;">
        
        <!-- Card 1: Total Items -->
        <a href="pharmacy_inventory.php?id=<?= $pharmacy['id'] ?>&branch=<?= $selected_branch_id ?>&filter=all" class="stat-card">
            <div class="stat-icon blue">
                <i class="fas fa-boxes"></i>
            </div>
            <div class="stat-content">
                <p class="stat-label">Total Items</p>
                <p class="stat-value blue-text"><?= number_format($total_items) ?></p>
                <p class="stat-sub">Inventory items</p>
            </div>
            <i class="fas fa-chevron-right stat-arrow"></i>
        </a>
        
        <!-- Card 2: In Stock -->
        <a href="pharmacy_inventory.php?id=<?= $pharmacy['id'] ?>&branch=<?= $selected_branch_id ?>&filter=instock" class="stat-card">
            <div class="stat-icon green">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="stat-content">
                <p class="stat-label">In Stock</p>
                <p class="stat-value green-text"><?= number_format($in_stock) ?></p>
                <p class="stat-sub">Available items</p>
            </div>
            <i class="fas fa-chevron-right stat-arrow"></i>
        </a>
        
        <!-- Card 3: Low Stock -->
        <a href="pharmacy_inventory.php?id=<?= $pharmacy['id'] ?>&branch=<?= $selected_branch_id ?>&filter=lowstock" class="stat-card">
            <div class="stat-icon amber">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <div class="stat-content">
                <p class="stat-label">Low Stock</p>
                <p class="stat-value amber-text"><?= number_format($low_stock) ?></p>
                <p class="stat-sub">Below reorder level</p>
            </div>
            <i class="fas fa-chevron-right stat-arrow"></i>
        </a>
        
        <!-- Card 4: Out of Stock -->
        <a href="pharmacy_inventory.php?id=<?= $pharmacy['id'] ?>&branch=<?= $selected_branch_id ?>&filter=outofstock" class="stat-card">
            <div class="stat-icon red">
                <i class="fas fa-times-circle"></i>
            </div>
            <div class="stat-content">
                <p class="stat-label">Out of Stock</p>
                <p class="stat-value red-text"><?= number_format($out_of_stock) ?></p>
                <p class="stat-sub">Zero quantity</p>
            </div>
            <i class="fas fa-chevron-right stat-arrow"></i>
        </a>
        
        <!-- Card 5: Expired -->
        <a href="pharmacy_inventory.php?id=<?= $pharmacy['id'] ?>&branch=<?= $selected_branch_id ?>&filter=expired" class="stat-card">
            <div class="stat-icon rose">
                <i class="fas fa-skull"></i>
            </div>
            <div class="stat-content">
                <p class="stat-label">Expired</p>
                <p class="stat-value rose-text"><?= number_format($expired) ?></p>
                <p class="stat-sub">Past expiry date</p>
            </div>
            <i class="fas fa-chevron-right stat-arrow"></i>
        </a>
        
        <!-- Card 6: Expiring Soon -->
        <a href="pharmacy_inventory.php?id=<?= $pharmacy['id'] ?>&branch=<?= $selected_branch_id ?>&filter=expiring" class="stat-card">
            <div class="stat-icon cyan">
                <i class="fas fa-hourglass-half"></i>
            </div>
            <div class="stat-content">
                <p class="stat-label">Expiring Soon</p>
                <p class="stat-value cyan-text"><?= number_format($expiring_soon) ?></p>
                <p class="stat-sub">Next 30 days</p>
            </div>
            <i class="fas fa-chevron-right stat-arrow"></i>
        </a>
        
    </div>

    <!-- ================================================================ -->
    <!-- FILTERS -->
    <!-- ================================================================ -->
    <div class="filter-bar animate-fade-in-up" style="animation-delay:0.1s;">
        <form method="GET" class="flex flex-wrap gap-3 items-center w-full">
            <input type="hidden" name="id" value="<?= $pharmacy['id'] ?>">
            <input type="hidden" name="branch" value="<?= $selected_branch_id ?>">
            
            <select name="filter" onchange="this.form.submit()" class="flex-1 min-w-[150px]">
                <option value="all" <?= $filter === 'all' ? 'selected' : '' ?>>All Items</option>
                <option value="instock" <?= $filter === 'instock' ? 'selected' : '' ?>>In Stock</option>
                <option value="lowstock" <?= $filter === 'lowstock' ? 'selected' : '' ?>>Low Stock</option>
                <option value="outofstock" <?= $filter === 'outofstock' ? 'selected' : '' ?>>Out of Stock</option>
                <option value="expired" <?= $filter === 'expired' ? 'selected' : '' ?>>Expired</option>
                <option value="expiring" <?= $filter === 'expiring' ? 'selected' : '' ?>>Expiring Soon</option>
            </select>
            
            <input type="text" name="search" placeholder="Search by name or category..." value="<?= htmlspecialchars($search) ?>" class="flex-1 min-w-[200px]">
            
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-search"></i> Filter
            </button>
            
            <a href="pharmacy_inventory.php?id=<?= $pharmacy['id'] ?>&branch=<?= $selected_branch_id ?>" class="btn btn-outline">
                <i class="fas fa-times"></i> Clear
            </a>
        </form>
    </div>

    <!-- ================================================================ -->
    <!-- INVENTORY TABLE -->
    <!-- ================================================================ -->
    <div class="card animate-fade-in-up" style="animation-delay:0.15s;">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-list text-blue-600"></i>
                Inventory Items (<?= count($inventory_items) ?>)
            </h3>
            <div class="flex gap-2">
                <a href="add_inventory.php?id=<?= $pharmacy['id'] ?>&branch=<?= $selected_branch_id ?>" class="btn btn-sm btn-primary">
                    <i class="fas fa-plus"></i> Add Item
                </a>
                <a href="export_inventory.php?id=<?= $pharmacy['id'] ?>&branch=<?= $selected_branch_id ?>&filter=<?= $filter ?>" class="btn btn-sm btn-outline">
                    <i class="fas fa-file-export"></i> Export
                </a>
            </div>
        </div>
        <div class="overflow-x-auto">
            <?php if (count($inventory_items) > 0): ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Medicine</th>
                            <th>Category</th>
                            <th>Qty</th>
                            <th>Reorder Level</th>
                            <th>Selling Price</th>
                            <th>Expiry Date</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($inventory_items as $item): 
                            $is_expired = !empty($item['expiry_date']) && strtotime($item['expiry_date']) < time();
                            $is_expiring_soon = !empty($item['expiry_date']) && strtotime($item['expiry_date']) > time() && strtotime($item['expiry_date']) < strtotime('+30 days');
                            $is_low_stock = ($item['quantity'] ?? 0) <= ($item['reorder_level'] ?? 0) && ($item['quantity'] ?? 0) > 0;
                            $is_out_of_stock = ($item['quantity'] ?? 0) <= 0;
                            $is_healthy = !$is_expired && !$is_expiring_soon && !$is_low_stock && !$is_out_of_stock;
                        ?>
                            <tr>
                                <td class="font-medium"><?= htmlspecialchars($item['medication_name'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($item['category'] ?? 'N/A') ?></td>
                                <td class="font-semibold <?= $is_out_of_stock ? 'text-red-600' : ($is_low_stock ? 'text-amber-600' : 'text-green-600') ?>">
                                    <?= number_format($item['quantity'] ?? 0) ?>
                                </td>
                                <td><?= number_format($item['reorder_level'] ?? 0) ?></td>
                                <td>TSh <?= number_format($item['selling_price'] ?? 0, 0) ?></td>
                                <td class="<?= $is_expired ? 'text-red-600 font-bold' : ($is_expiring_soon ? 'text-amber-600' : 'text-gray-500') ?>">
                                    <?= !empty($item['expiry_date']) ? date('M d, Y', strtotime($item['expiry_date'])) : 'N/A' ?>
                                    <?php if ($is_expired): ?>
                                        <span class="text-red-600 text-xs block">(Expired)</span>
                                    <?php elseif ($is_expiring_soon): ?>
                                        <span class="text-amber-600 text-xs block">(Soon)</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($is_out_of_stock): ?>
                                        <span class="badge badge-danger" style="font-size:0.6rem;padding:2px 10px;">Out of Stock</span>
                                    <?php elseif ($is_low_stock): ?>
                                        <span class="badge badge-warning" style="font-size:0.6rem;padding:2px 10px;">Low Stock</span>
                                    <?php elseif ($is_expired): ?>
                                        <span class="badge badge-danger" style="font-size:0.6rem;padding:2px 10px;">Expired</span>
                                    <?php elseif ($is_expiring_soon): ?>
                                        <span class="badge badge-warning" style="font-size:0.6rem;padding:2px 10px;">Expiring Soon</span>
                                    <?php elseif ($is_healthy): ?>
                                        <span class="badge badge-success" style="font-size:0.6rem;padding:2px 10px;">In Stock</span>
                                    <?php else: ?>
                                        <span class="badge badge-secondary" style="font-size:0.6rem;padding:2px 10px;">Unknown</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="flex gap-1">
                                        <a href="edit_inventory.php?id=<?= $item['id'] ?>&branch=<?= $pharmacy['id'] ?>" class="text-blue-600 text-xs hover:underline">Edit</a>
                                        <span class="text-gray-300">|</span>
                                        <a href="view_inventory.php?id=<?= $item['id'] ?>&branch=<?= $pharmacy['id'] ?>" class="text-blue-600 text-xs hover:underline">View</a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-boxes"></i>
                    <h3>No Inventory Items Found</h3>
                    <p class="text-gray-400">
                        <?= !empty($search) || $filter !== 'all' ? 'No items match your search criteria.' : 'This pharmacy has no inventory items yet.' ?>
                    </p>
                    <?php if (!empty($search) || $filter !== 'all'): ?>
                        <a href="pharmacy_inventory.php?id=<?= $pharmacy['id'] ?>&branch=<?= $selected_branch_id ?>" class="btn btn-primary mt-4">
                            <i class="fas fa-times"></i> Clear Filters
                        </a>
                    <?php else: ?>
                        <a href="add_inventory.php?id=<?= $pharmacy['id'] ?>&branch=<?= $selected_branch_id ?>" class="btn btn-primary mt-4">
                            <i class="fas fa-plus"></i> Add Item
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Pharmacy Inventory - <?= htmlspecialchars($pharmacy['name']) ?>
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
        var url = new URL(window.location.href);
        if (query.length > 0) {
            url.searchParams.set('search', query);
        } else {
            url.searchParams.delete('search');
        }
        window.location.href = url.toString();
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

    console.log('%c📦 Braick Dispensary - Pharmacy Inventory (BLUE THEME)', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c🏥 Pharmacy: <?= htmlspecialchars($pharmacy['name']) ?> (ID: <?= $pharmacy['id'] ?>)', 'font-size:13px; color:#059669;');
    console.log('%c📦 Total Items: <?= number_format($total_items) ?>', 'font-size:13px; color:#7C3AED;');
    console.log('%c💰 Total Value: TSh <?= number_format($total_value, 0) ?>', 'font-size:13px; color:#0B5ED7;');
    console.log('%c✅ In Stock: <?= $in_stock ?> | ⚠️ Low Stock: <?= $low_stock ?> | ❌ Out of Stock: <?= $out_of_stock ?>', 'font-size:13px; color:#059669;');
    console.log('%c⏰ Expired: <?= $expired ?> | Expiring Soon: <?= $expiring_soon ?>', 'font-size:13px; color:#DC2626;');
</script>

</body>
</html>