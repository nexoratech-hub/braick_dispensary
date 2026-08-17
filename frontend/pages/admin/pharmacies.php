<?php
// ================================================================
// FILE: frontend/pages/admin/pharmacies.php
// SUPER ADMIN - VIEW ALL PHARMACIES
// BRAICK DISPENSARY - BLUE THEME - WITH BLUE STAT CARDS (3+3)
// FIXED: Prescription sales now showing from prescription_sales table
// FIXED: Removed invalid_id error from URL
// FIXED: Now correctly shows prescription sales data
// ================================================================

// ================================================================
// START SESSION
// ================================================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ================================================================
// LOGIN PROTECTION - CHECK IF USER IS LOGGED IN
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
// GET ADMIN DATA FROM SESSION
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
require_once '../../../backend/config/database.php';
require_once '../../../backend/helpers/functions.php';

$db = Database::getInstance()->getConnection();

// ================================================================
// GET FILTERS
// ================================================================
$selected_branch_id = $_GET['branch'] ?? 'all';
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? 'all';

// ================================================================
// IF BRANCH ID IS NOT NUMERIC OR INVALID, REDIRECT WITH ALL
// ================================================================
if ($selected_branch_id !== 'all' && !is_numeric($selected_branch_id)) {
    header('Location: pharmacies.php?branch=all');
    exit;
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
// GET PRESCRIPTION SALES DATA DIRECTLY FOR DEBUG/VERIFICATION
// ================================================================
$prescription_sales_raw = [];
try {
    $stmt = $db->query("SELECT * FROM prescription_sales ORDER BY id DESC LIMIT 5");
    $prescription_sales_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $prescription_sales_raw = [];
}

// ================================================================
// BUILD QUERY FOR PHARMACIES - FIXED PRESCRIPTION SALES
// ================================================================
$sql = "
    SELECT 
        b.*,
        (SELECT COUNT(*) FROM users WHERE branch_id = b.id AND role = 'pharmacy' AND status = 'active') as active_pharmacists,
        (SELECT COUNT(*) FROM users WHERE branch_id = b.id AND role = 'pharmacy') as total_pharmacists,
        (SELECT COUNT(*) FROM medications_inventory WHERE branch_id = b.id AND status = 'active') as total_medicines,
        (SELECT COUNT(*) FROM medications_inventory WHERE branch_id = b.id AND status = 'active' AND quantity <= reorder_level AND quantity > 0) as low_stock_items,
        (SELECT COUNT(*) FROM medications_inventory WHERE branch_id = b.id AND status = 'active' AND quantity <= 0) as out_of_stock_items,
        (SELECT COUNT(*) FROM prescriptions WHERE branch_id = b.id AND status = 'pending') as pending_prescriptions,
        (SELECT COUNT(*) FROM prescriptions WHERE branch_id = b.id AND status = 'dispensed') as dispensed_prescriptions,
        (SELECT COUNT(*) FROM prescriptions WHERE branch_id = b.id) as total_prescriptions,
        -- FIXED: Get prescription sales from prescription_sales table
        (SELECT COALESCE(SUM(net_amount), 0) FROM prescription_sales WHERE branch_id = b.id AND status = 'dispensed') as prescription_revenue,
        (SELECT COUNT(*) FROM prescription_sales WHERE branch_id = b.id AND status = 'dispensed') as prescription_sales_count,
        (SELECT COALESCE(SUM(net_amount), 0) FROM prescription_sales WHERE branch_id = b.id) as total_prescription_sales_revenue,
        (SELECT COUNT(*) FROM prescription_sales WHERE branch_id = b.id) as total_prescription_sales,
        (SELECT COUNT(*) FROM otc_sales WHERE branch_id = b.id) as total_otc_sales,
        (SELECT COALESCE(SUM(net_amount), 0) FROM otc_sales WHERE branch_id = b.id AND payment_status = 'paid') as otc_revenue,
        (SELECT COUNT(*) FROM medications_inventory WHERE branch_id = b.id AND status = 'active' AND expiry_date < CURDATE()) as expired_medicines,
        (SELECT COUNT(*) FROM medications_inventory WHERE branch_id = b.id AND status = 'active' AND expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)) as expiring_soon_medicines,
        (SELECT COUNT(*) FROM medications_inventory WHERE branch_id = b.id AND status = 'active') as total_active_medicines
    FROM branches b
    WHERE b.status = 'active'
";

// Apply filters
if ($selected_branch_id !== 'all' && is_numeric($selected_branch_id)) {
    $sql .= " AND b.id = " . (int)$selected_branch_id;
}

if ($status_filter !== 'all') {
    $sql .= " AND b.status = '" . $db->quote($status_filter) . "'";
}

if (!empty($search)) {
    $sql .= " AND (b.name LIKE '%" . $db->quote($search) . "%' OR b.location LIKE '%" . $db->quote($search) . "%')";
}

$sql .= " ORDER BY b.name ASC";

$pharmacies = [];
try {
    $stmt = $db->query($sql);
    $pharmacies = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching pharmacies: " . $e->getMessage());
    $pharmacies = [];
}

// ================================================================
// GET BRANCHES FOR FILTER
// ================================================================
$branches = [];
$stmt = $db->query("SELECT id, name FROM branches WHERE status = 'active' ORDER BY name");
$branches = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// CALCULATE TOTALS
// ================================================================
$total_pharmacies = count($pharmacies);
$total_medicines = 0;
$total_prescriptions = 0;
$total_prescription_sales = 0;
$total_otc_sales = 0;
$total_revenue = 0;
$total_out_of_stock = 0;
$total_low_stock = 0;
$total_expired = 0;
$total_expiring_soon = 0;
$total_pending = 0;
$total_dispensed = 0;

foreach ($pharmacies as $p) {
    $total_medicines += $p['total_medicines'] ?? 0;
    $total_prescriptions += $p['total_prescriptions'] ?? 0;
    $total_prescription_sales += $p['prescription_sales_count'] ?? 0;
    $total_otc_sales += $p['total_otc_sales'] ?? 0;
    $total_revenue += ($p['prescription_revenue'] ?? 0) + ($p['otc_revenue'] ?? 0);
    $total_out_of_stock += $p['out_of_stock_items'] ?? 0;
    $total_low_stock += $p['low_stock_items'] ?? 0;
    $total_expired += $p['expired_medicines'] ?? 0;
    $total_expiring_soon += $p['expiring_soon_medicines'] ?? 0;
    $total_pending += $p['pending_prescriptions'] ?? 0;
    $total_dispensed += $p['dispensed_prescriptions'] ?? 0;
}

// ================================================================
// GET TOTAL PRESCRIPTION SALES FROM DATABASE DIRECTLY (FOR VERIFICATION)
// ================================================================
$total_prescription_sales_db = 0;
try {
    $stmt = $db->query("SELECT COUNT(*) as total FROM prescription_sales WHERE status = 'dispensed'");
    $total_prescription_sales_db = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
} catch (Exception $e) {
    $total_prescription_sales_db = 0;
}

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
// PROFILE PICTURE URL
// ================================================================
$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

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
    <title>Pharmacies - Braick Dispensary</title>
    
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
            
            --teal: #0D9488;
            --teal-bg: #ECFDF5;
            
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
           STATS CARDS - BLUE THEME (3+3 GRID)
           ================================================================ */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 18px;
            margin-bottom: 24px;
        }
        
        .stat-card {
            background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
            border-radius: var(--radius);
            padding: 20px 24px;
            border: 2px solid rgba(255,255,255,0.1);
            display: flex;
            align-items: center;
            gap: 16px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(11, 94, 215, 0.2);
            text-decoration: none;
            color: white;
            position: relative;
            overflow: hidden;
            min-height: 90px;
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -20%;
            width: 150px;
            height: 150px;
            background: rgba(255,255,255,0.05);
            border-radius: 50%;
            pointer-events: none;
        }
        
        .stat-card::after {
            content: '';
            position: absolute;
            bottom: -30%;
            left: -10%;
            width: 100px;
            height: 100px;
            background: rgba(255,255,255,0.03);
            border-radius: 50%;
            pointer-events: none;
        }
        
        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 30px rgba(11, 94, 215, 0.35);
            border-color: rgba(255,255,255,0.2);
        }
        
        .stat-card .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
            flex-shrink: 0;
            background: rgba(255,255,255,0.15);
            color: white;
            border: 1px solid rgba(255,255,255,0.1);
            position: relative;
            z-index: 1;
        }
        
        .stat-card .stat-label {
            font-size: 0.7rem;
            color: rgba(255,255,255,0.8);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin: 0;
            position: relative;
            z-index: 1;
        }
        
        .stat-card .stat-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: white;
            margin: 0;
            line-height: 1.2;
            position: relative;
            z-index: 1;
        }
        
        .stat-card .stat-sub {
            font-size: 0.6rem;
            color: rgba(255,255,255,0.6);
            margin-top: 2px;
            position: relative;
            z-index: 1;
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
        .badge-blue-light { background: var(--primary-bg); color: var(--primary); }
        
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
        
        /* ================================================================
           PHARMACY CARDS
           ================================================================ */
        .pharmacy-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
            gap: 20px;
            margin-bottom: 24px;
        }
        
        .pharmacy-card {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            border: 2px solid var(--border-color);
            overflow: hidden;
            transition: all 0.3s ease;
            box-shadow: var(--shadow-sm);
            position: relative;
        }
        
        .pharmacy-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--primary-gradient);
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .pharmacy-card:hover {
            transform: translateY(-6px);
            border-color: var(--primary);
            box-shadow: var(--shadow-xl);
        }
        
        .pharmacy-card:hover::before {
            opacity: 1;
        }
        
        .pharmacy-card-header {
            padding: 16px 20px;
            background: var(--primary-gradient);
            border-bottom: 1px solid rgba(255,255,255,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .pharmacy-card-header .pharmacy-name {
            font-size: 1rem;
            font-weight: 600;
            color: white;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .pharmacy-card-header .pharmacy-name i {
            color: rgba(255,255,255,0.8);
        }
        
        .pharmacy-card-header .badge {
            font-size: 0.6rem;
            padding: 2px 12px;
        }
        
        .pharmacy-card-body {
            padding: 16px 20px;
        }
        
        /* Revenue Section - 3 columns */
        .revenue-section {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 10px;
            margin-bottom: 14px;
            padding: 12px;
            background: var(--primary-bg);
            border-radius: var(--radius);
            border: 2px solid var(--border-color);
        }
        
        [data-theme="dark"] .revenue-section {
            background: #1E3A5F;
            border-color: #334155;
        }
        
        .revenue-item {
            text-align: center;
        }
        
        .revenue-item .revenue-label {
            font-size: 0.55rem;
            color: var(--text-secondary);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }
        
        .revenue-item .revenue-amount {
            font-size: 0.9rem;
            font-weight: 700;
            color: var(--text-primary);
        }
        
        .revenue-item .revenue-amount.blue { color: var(--primary); }
        .revenue-item .revenue-amount.green { color: #059669; }
        .revenue-item .revenue-amount.purple { color: #7C3AED; }
        .revenue-item .revenue-amount.orange { color: #F59E0B; }
        .revenue-item .revenue-amount.teal { color: #0D9488; }
        
        /* Stats Grid inside card - 4 columns */
        .stats-inner-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 8px;
            margin-bottom: 12px;
        }
        
        .stats-inner-grid .stat-inner {
            text-align: center;
            padding: 8px;
            border-radius: 8px;
            border: 2px solid var(--border-color);
            background: var(--bg-body);
        }
        
        [data-theme="dark"] .stats-inner-grid .stat-inner {
            background: #0F172A;
        }
        
        .stats-inner-grid .stat-inner .stat-number {
            font-size: 1.1rem;
            font-weight: 700;
        }
        
        .stats-inner-grid .stat-inner .stat-number.primary { color: var(--primary); }
        .stats-inner-grid .stat-inner .stat-number.success { color: #059669; }
        .stats-inner-grid .stat-inner .stat-number.danger { color: #DC2626; }
        .stats-inner-grid .stat-inner .stat-number.warning { color: #D97706; }
        .stats-inner-grid .stat-inner .stat-number.purple { color: #7C3AED; }
        .stats-inner-grid .stat-inner .stat-number.teal { color: #0D9488; }
        
        .stats-inner-grid .stat-inner .stat-label {
            font-size: 0.5rem;
            color: var(--text-secondary);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }
        
        .pharmacy-card-body .info-row {
            display: flex;
            justify-content: space-between;
            padding: 6px 0;
            border-bottom: 1px solid var(--border-color);
            font-size: 0.8rem;
        }
        
        .pharmacy-card-body .info-row:last-child {
            border-bottom: none;
        }
        
        .pharmacy-card-body .info-label {
            color: var(--text-secondary);
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .pharmacy-card-body .info-label i {
            color: var(--primary);
            width: 16px;
            font-size: 0.75rem;
        }
        
        .pharmacy-card-body .info-value {
            color: var(--text-primary);
            font-weight: 600;
        }
        
        .pharmacy-card-body .info-value.text-success { color: #059669; }
        .pharmacy-card-body .info-value.text-danger { color: #DC2626; }
        .pharmacy-card-body .info-value.text-warning { color: #D97706; }
        .pharmacy-card-body .info-value.text-primary { color: var(--primary); }
        
        /* Stock & Expiry Badges */
        .stock-badges {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-top: 10px;
            padding-top: 10px;
            border-top: 2px solid var(--border-color);
        }
        
        .stock-badge {
            font-size: 0.6rem;
            font-weight: 600;
            padding: 3px 12px;
            border-radius: 20px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        
        .stock-badge.danger {
            background: #FEE2E2;
            color: #DC2626;
        }
        
        .stock-badge.warning {
            background: #FEF3C7;
            color: #D97706;
        }
        
        .stock-badge.success {
            background: #D1FAE5;
            color: #059669;
        }
        
        .stock-badge.info {
            background: #EFF6FF;
            color: #0B5ED7;
        }
        
        [data-theme="dark"] .stock-badge.danger {
            background: #3A1A1A;
            color: #F87171;
        }
        [data-theme="dark"] .stock-badge.warning {
            background: #3D2E0A;
            color: #FBBF24;
        }
        [data-theme="dark"] .stock-badge.success {
            background: #1A3A2A;
            color: #34D399;
        }
        [data-theme="dark"] .stock-badge.info {
            background: #1E3A5F;
            color: #3B82F6;
        }
        
        /* Pharmacy Card Footer */
        .pharmacy-card-footer {
            padding: 12px 20px;
            background: var(--bg-body);
            border-top: 2px solid var(--border-color);
            display: flex;
            justify-content: flex-end;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        [data-theme="dark"] .pharmacy-card-footer {
            background: #0F172A;
        }
        
        .pharmacy-card-footer .btn-sm {
            padding: 4px 12px;
            font-size: 0.7rem;
            border-radius: 6px;
            font-weight: 600;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        
        .pharmacy-card-footer .btn-sm-primary {
            background: var(--primary-gradient);
            color: white;
        }
        
        .pharmacy-card-footer .btn-sm-primary:hover {
            background: var(--primary-gradient-hover);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3);
        }
        
        .pharmacy-card-footer .btn-sm-outline {
            background: transparent;
            color: var(--text-secondary);
            border: 2px solid var(--border-color);
        }
        
        .pharmacy-card-footer .btn-sm-outline:hover {
            border-color: var(--primary);
            color: var(--primary);
            background: var(--primary-bg);
        }
        
        /* ================================================================
           EMPTY STATE
           ================================================================ */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-secondary);
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            border: 2px dashed var(--border-color);
        }
        
        .empty-state i {
            font-size: 3.5rem;
            color: var(--border-color);
            margin-bottom: 16px;
        }
        
        .empty-state h3 {
            font-size: 1.2rem;
            color: var(--text-primary);
            margin-bottom: 8px;
        }
        
        /* ================================================================
           TOAST
           ================================================================ */
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
            box-shadow: 0 8px 30px rgba(0,0,0,0.15);
        }
        .toast-custom.show {
            transform: translateY(0);
            opacity: 1;
        }
        .toast-custom.success { background: #059669; }
        .toast-custom.error { background: #DC2626; }
        .toast-custom.info { background: #0B5ED7; }
        .toast-custom.warning { background: #D97706; }
        
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
            font-weight: 600;
        }
        
        /* ================================================================
           RESPONSIVE
           ================================================================ */
        @media (max-width: 1024px) {
            .top-nav { left: 0; }
            .main-content { margin-left: 0; padding: 16px; }
            .top-nav .search-wrapper { max-width: 300px; }
            .pharmacy-grid { grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); }
            .stats-grid { grid-template-columns: repeat(3, 1fr); }
        }
        
        @media (max-width: 768px) {
            .top-nav .search-wrapper { max-width: 180px; }
            .top-nav .datetime { display: none; }
            .page-header { padding: 16px 18px; }
            .page-header .page-title { font-size: 1.3rem; }
            .stats-grid { grid-template-columns: 1fr 1fr; }
            .pharmacy-grid { grid-template-columns: 1fr; }
            .filter-bar { flex-direction: column; align-items: stretch; }
            .filter-bar select, .filter-bar input { width: 100%; min-width: unset; }
            .revenue-section { grid-template-columns: 1fr 1fr; }
            .stats-inner-grid { grid-template-columns: repeat(2, 1fr); }
        }
        
        @media (max-width: 480px) {
            .main-content { padding: 10px; }
            .stats-grid { grid-template-columns: 1fr; }
            .page-header { flex-direction: column; align-items: flex-start !important; }
            .revenue-section { grid-template-columns: 1fr; }
            .stats-inner-grid { grid-template-columns: 1fr; }
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
            .footer, #sidebarToggle, .filter-bar { display: none !important; }
            .main-content { margin: 0; padding: 20px; }
            .pharmacy-card { break-inside: avoid; box-shadow: none !important; border: 1px solid #ddd; }
            .page-header {
                background: #0B5ED7 !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            .page-title, .page-subtitle, .role-badge-display, .header-badge {
                color: white !important;
            }
            .stat-card { border: 1px solid #ddd !important; box-shadow: none !important; background: #0B5ED7 !important; color: white !important; }
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
            <input type="text" id="searchInput" placeholder="Search pharmacies..." value="<?= htmlspecialchars($search) ?>">
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

    <!-- Page Header -->
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-prescription-bottle"></i>
                Pharmacies
                <span class="role-badge-display">ADMIN</span>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-store-alt"></i>
                <strong><?= $total_pharmacies ?></strong> pharmacy branches
                <span class="header-badge" style="background:rgba(52,211,153,0.2);border-color:rgba(52,211,153,0.3);color:#34D399;">
                    <i class="fas fa-pills"></i> <?= number_format($total_medicines) ?> Total Medicines
                </span>
                <span class="header-badge" style="background:rgba(251,191,36,0.2);border-color:rgba(251,191,36,0.3);color:#FBBF24;">
                    <i class="fas fa-money-bill-wave"></i> TSh <?= number_format($total_revenue, 0) ?> Revenue
                </span>
                <span class="header-badge" style="background:rgba(124,58,237,0.2);border-color:rgba(124,58,237,0.3);color:#A78BFA;">
                    <i class="fas fa-prescription"></i> <?= number_format($total_prescription_sales) ?> Rx Sales
                </span>
                <span class="header-badge" style="background:rgba(52,211,153,0.2);border-color:rgba(52,211,153,0.3);color:#34D399;">
                    <i class="fas fa-prescription"></i> <?= number_format($total_prescriptions) ?> Total Rx
                </span>
            </p>
        </div>
        <div class="flex gap-2 flex-wrap" style="position:relative;z-index:1;">
            <?php if ($total_prescription_sales_db > 0): ?>
                <span class="header-badge" style="background:rgba(52,211,153,0.3);border-color:rgba(52,211,153,0.4);color:#34D399;">
                    <i class="fas fa-check-circle"></i> Rx Sales: <?= number_format($total_prescription_sales_db) ?>
                </span>
            <?php endif; ?>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- OVERVIEW STATS CARDS - 3+3 GRID (BLUE BACKGROUND) -->
    <!-- ================================================================ -->
    <div class="stats-grid animate-fade-in-up">
        <!-- Row 1: Cards 1-3 -->
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-store"></i>
            </div>
            <div>
                <p class="stat-label">Total Pharmacies</p>
                <p class="stat-value"><?= number_format($total_pharmacies) ?></p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-pills"></i>
            </div>
            <div>
                <p class="stat-label">Total Medicines</p>
                <p class="stat-value"><?= number_format($total_medicines) ?></p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-prescription"></i>
            </div>
            <div>
                <p class="stat-label">Total Prescriptions</p>
                <p class="stat-value"><?= number_format($total_prescriptions) ?></p>
                <p class="stat-sub"><?= number_format($total_pending) ?> pending · <?= number_format($total_dispensed) ?> dispensed</p>
            </div>
        </div>
        
        <!-- Row 2: Cards 4-6 -->
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-shopping-cart"></i>
            </div>
            <div>
                <p class="stat-label">OTC Sales</p>
                <p class="stat-value"><?= number_format($total_otc_sales) ?></p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-money-bill-wave"></i>
            </div>
            <div>
                <p class="stat-label">Total Revenue</p>
                <p class="stat-value">TSh <?= number_format($total_revenue, 0) ?></p>
                <p class="stat-sub"><?= number_format($total_prescription_sales) ?> Rx sales</p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <div>
                <p class="stat-label">Stock Alerts</p>
                <p class="stat-value"><?= number_format($total_out_of_stock + $total_low_stock) ?></p>
                <p class="stat-sub"><?= $total_out_of_stock ?> out of stock · <?= $total_low_stock ?> low stock</p>
            </div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- FILTERS -->
    <!-- ================================================================ -->
    <div class="filter-bar animate-fade-in-up" style="animation-delay:0.05s;">
        <form method="GET" class="flex flex-wrap gap-3 items-center w-full">
            <select name="branch" onchange="this.form.submit()" class="flex-1 min-w-[150px]">
                <option value="all" <?= $selected_branch_id === 'all' ? 'selected' : '' ?>>All Branches</option>
                <?php foreach ($branches as $b): ?>
                    <option value="<?= $b['id'] ?>" <?= $selected_branch_id == $b['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($b['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            
            <select name="status" onchange="this.form.submit()" class="flex-1 min-w-[150px]">
                <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>All Status</option>
                <option value="active" <?= $status_filter === 'active' ? 'selected' : '' ?>>Active</option>
                <option value="inactive" <?= $status_filter === 'inactive' ? 'selected' : '' ?>>Inactive</option>
            </select>
            
            <input type="text" name="search" placeholder="Search pharmacies..." value="<?= htmlspecialchars($search) ?>" class="flex-1 min-w-[200px]">
            
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-search"></i> Filter
            </button>
            
            <a href="pharmacies.php" class="btn btn-outline">
                <i class="fas fa-times"></i> Clear
            </a>
        </form>
    </div>

    <!-- ================================================================ -->
    <!-- PHARMACY GRID -->
    <!-- ================================================================ -->
    <?php if (count($pharmacies) > 0): ?>
        <div class="pharmacy-grid animate-fade-in-up" style="animation-delay:0.1s;">
            <?php foreach ($pharmacies as $pharmacy): 
                $prescription_revenue = $pharmacy['prescription_revenue'] ?? 0;
                $otc_revenue = $pharmacy['otc_revenue'] ?? 0;
                $total_revenue_pharmacy = $prescription_revenue + $otc_revenue;
                $total_prescriptions = $pharmacy['total_prescriptions'] ?? 0;
                $pending_prescriptions = $pharmacy['pending_prescriptions'] ?? 0;
                $dispensed_prescriptions = $pharmacy['dispensed_prescriptions'] ?? 0;
                $prescription_sales = $pharmacy['prescription_sales_count'] ?? 0;
                $out_of_stock = $pharmacy['out_of_stock_items'] ?? 0;
                $low_stock = $pharmacy['low_stock_items'] ?? 0;
                $expired = $pharmacy['expired_medicines'] ?? 0;
                $expiring_soon = $pharmacy['expiring_soon_medicines'] ?? 0;
                $total_medicines = $pharmacy['total_medicines'] ?? 0;
                $has_alerts = $out_of_stock > 0 || $low_stock > 0 || $expired > 0 || $expiring_soon > 0;
                $total_prescription_sales_count = $pharmacy['total_prescription_sales'] ?? 0;
            ?>
                <div class="pharmacy-card">
                    <!-- Card Header - Blue Gradient -->
                    <div class="pharmacy-card-header">
                        <span class="pharmacy-name">
                            <i class="fas fa-store-alt"></i>
                            <?= htmlspecialchars($pharmacy['name']) ?>
                        </span>
                        <span class="badge badge-<?= getStatusBadge($pharmacy['status'] ?? 'active') ?>" style="font-size:0.6rem;padding:2px 12px;">
                            <?= ucfirst($pharmacy['status'] ?? 'Active') ?>
                        </span>
                    </div>
                    
                    <!-- Card Body -->
                    <div class="pharmacy-card-body">
                        <!-- Revenue Section - 3 columns -->
                        <div class="revenue-section">
                            <div class="revenue-item">
                                <div class="revenue-label">💊 Prescription Revenue</div>
                                <div class="revenue-amount blue">TSh <?= number_format($prescription_revenue, 0) ?></div>
                            </div>
                            <div class="revenue-item">
                                <div class="revenue-label">🛒 OTC Revenue</div>
                                <div class="revenue-amount orange">TSh <?= number_format($otc_revenue, 0) ?></div>
                            </div>
                            <div class="revenue-item">
                                <div class="revenue-label">💰 Total Revenue</div>
                                <div class="revenue-amount green">TSh <?= number_format($total_revenue_pharmacy, 0) ?></div>
                            </div>
                        </div>
                        
                        <!-- Stats Grid - Prescriptions & Pharmacists (4 columns) -->
                        <div class="stats-inner-grid">
                            <div class="stat-inner">
                                <div class="stat-number primary"><?= number_format($total_prescriptions) ?></div>
                                <div class="stat-label">Total Rx</div>
                            </div>
                            <div class="stat-inner">
                                <div class="stat-number <?= $pending_prescriptions > 0 ? 'warning' : 'success' ?>">
                                    <?= number_format($pending_prescriptions) ?>
                                </div>
                                <div class="stat-label">Pending</div>
                            </div>
                            <div class="stat-inner">
                                <div class="stat-number purple"><?= number_format($dispensed_prescriptions) ?></div>
                                <div class="stat-label">Dispensed</div>
                            </div>
                            <div class="stat-inner">
                                <div class="stat-number teal <?= $prescription_sales > 0 ? 'teal' : '' ?>">
                                    <?= number_format($prescription_sales) ?>
                                </div>
                                <div class="stat-label">Rx Sales</div>
                            </div>
                        </div>
                        
                        <!-- Stock & Expiry Badges -->
                        <div class="stock-badges">
                            <?php if ($out_of_stock > 0): ?>
                                <span class="stock-badge danger">
                                    <i class="fas fa-times-circle"></i> <?= $out_of_stock ?> Out of Stock
                                </span>
                            <?php endif; ?>
                            
                            <?php if ($low_stock > 0): ?>
                                <span class="stock-badge warning">
                                    <i class="fas fa-exclamation-triangle"></i> <?= $low_stock ?> Low Stock
                                </span>
                            <?php endif; ?>
                            
                            <?php if ($expired > 0): ?>
                                <span class="stock-badge danger">
                                    <i class="fas fa-skull"></i> <?= $expired ?> Expired
                                </span>
                            <?php endif; ?>
                            
                            <?php if ($expiring_soon > 0): ?>
                                <span class="stock-badge warning">
                                    <i class="fas fa-clock"></i> <?= $expiring_soon ?> Expiring Soon
                                </span>
                            <?php endif; ?>
                            
                            <?php if (!$has_alerts): ?>
                                <span class="stock-badge success">
                                    <i class="fas fa-check-circle"></i> All Clear ✅
                                </span>
                            <?php endif; ?>
                            
                            <span class="stock-badge info" style="margin-left:auto;">
                                <i class="fas fa-pills"></i> <?= number_format($total_medicines) ?> Items
                            </span>
                        </div>
                        
                        <!-- Info Rows -->
                        <div class="info-row" style="margin-top:10px;">
                            <span class="info-label"><i class="fas fa-map-marker-alt"></i> Location</span>
                            <span class="info-value"><?= htmlspecialchars($pharmacy['location'] ?? 'N/A') ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label"><i class="fas fa-phone"></i> Phone</span>
                            <span class="info-value"><?= htmlspecialchars($pharmacy['phone'] ?? 'N/A') ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label"><i class="fas fa-user-md"></i> Pharmacists</span>
                            <span class="info-value"><?= $pharmacy['active_pharmacists'] ?? 0 ?> Active / <?= $pharmacy['total_pharmacists'] ?? 0 ?> Total</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label"><i class="fas fa-shopping-cart"></i> OTC Sales</span>
                            <span class="info-value"><?= number_format($pharmacy['total_otc_sales'] ?? 0) ?> transactions</span>
                        </div>
                        <div class="info-row" style="border-bottom: none;">
                            <span class="info-label"><i class="fas fa-prescription"></i> Rx Sales</span>
                            <span class="info-value text-primary"><?= number_format($prescription_sales) ?> sales</span>
                        </div>
                    </div>
                    
                    <!-- Card Footer -->
                    <div class="pharmacy-card-footer">
                        <a href="view_pharmacy.php?id=<?= $pharmacy['id'] ?>&branch=<?= $selected_branch_id ?>" class="btn-sm btn-sm-primary">
                            <i class="fas fa-eye"></i> View
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Total count -->
        <div class="text-center text-sm text-gray-500 dark:text-gray-400 py-2">
            Showing <strong><?= count($pharmacies) ?></strong> pharmacy branch<?= count($pharmacies) > 1 ? 'es' : '' ?>
            <?php if ($total_prescription_sales_db > 0): ?>
                · <span class="text-green-500">✅ <?= number_format($total_prescription_sales_db) ?> Rx sales found in database</span>
            <?php else: ?>
                · <span class="text-yellow-500">⚠️ No prescription sales found</span>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <!-- Empty State -->
        <div class="empty-state animate-fade-in-up">
            <i class="fas fa-prescription-bottle"></i>
            <h3>No Pharmacies Found</h3>
            <p class="text-gray-400"><?= !empty($search) ? 'No results match your search criteria.' : 'No pharmacy branches have been created yet.' ?></p>
            <?php if (!empty($search)): ?>
                <a href="pharmacies.php" class="btn btn-primary mt-4">
                    <i class="fas fa-times"></i> Clear Filters
                </a>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- DEBUG: Show raw prescription sales data -->
    <!-- ================================================================ -->
    <?php if (count($prescription_sales_raw) > 0): ?>
        <div class="mt-4 p-3 bg-gray-100 dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 text-sm">
            <p class="font-semibold text-primary">📋 Prescription Sales Data (Latest 5)</p>
            <div class="overflow-x-auto mt-2">
                <table class="min-w-full text-xs">
                    <thead>
                        <tr class="bg-gray-200 dark:bg-gray-700">
                            <th class="px-2 py-1 text-left">ID</th>
                            <th class="px-2 py-1 text-left">Sale Number</th>
                            <th class="px-2 py-1 text-left">Patient</th>
                            <th class="px-2 py-1 text-left">Branch</th>
                            <th class="px-2 py-1 text-left">Total</th>
                            <th class="px-2 py-1 text-left">Status</th>
                            <th class="px-2 py-1 text-left">Created At</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($prescription_sales_raw as $ps): ?>
                            <tr class="border-b border-gray-200 dark:border-gray-700">
                                <td class="px-2 py-1"><?= $ps['id'] ?></td>
                                <td class="px-2 py-1 font-mono"><?= htmlspecialchars($ps['sale_number']) ?></td>
                                <td class="px-2 py-1"><?= $ps['patient_id'] ?></td>
                                <td class="px-2 py-1"><?= $ps['branch_id'] ?> (<?= $ps['branch_id'] == 1 ? 'Dodoma' : ($ps['branch_id'] == 2 ? 'Arusha' : ($ps['branch_id'] == 3 ? 'Dar' : 'Other')) ?>)</td>
                                <td class="px-2 py-1 font-semibold">TSh <?= number_format($ps['total_amount'] ?? 0, 0) ?></td>
                                <td class="px-2 py-1"><span class="badge badge-success"><?= htmlspecialchars($ps['status'] ?? 'N/A') ?></span></td>
                                <td class="px-2 py-1 text-gray-500"><?= $ps['created_at'] ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <p class="mt-2 text-gray-500 text-xs">Total prescription sales in database: <strong><?= number_format($total_prescription_sales_db) ?></strong></p>
        </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Pharmacies - <?= $total_pharmacies ?> branches
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
            setTimeout(function() {
                toast.style.display = 'none';
            }, 400);
        }, 3500);
    }

    console.log('%c🏥 Braick Dispensary - Pharmacies', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c👤 Admin: <?= htmlspecialchars($user_full_name) ?>', 'font-size:13px; color:#059669;');
    console.log('%c🔒 Login protection: ACTIVE', 'font-size:13px; color:#0B5ED7;');
    console.log('%c📊 Total Pharmacies: <?= $total_pharmacies ?>', 'font-size:13px; color:#059669;');
    console.log('%c💊 Total Medicines: <?= number_format($total_medicines) ?>', 'font-size:13px; color:#7C3AED;');
    console.log('%c📋 Total Prescriptions: <?= number_format($total_prescriptions) ?>', 'font-size:13px; color:#7C3AED;');
    console.log('%c📋 Prescription Sales (from DB): <?= number_format($total_prescription_sales_db) ?>', 'font-size:13px; color:#34D399;');
    console.log('%c🛒 OTC Sales: <?= number_format($total_otc_sales) ?>', 'font-size:13px; color:#F59E0B;');
    console.log('%c💰 Total Revenue: TSh <?= number_format($total_revenue, 0) ?>', 'font-size:13px; color:#0D9488;');
    console.log('%c✅ FIXED: Prescription sales now correctly showing from prescription_sales table', 'font-size:13px; color:#34D399;');
    console.log('%c📊 Rx Sales count: <?= number_format($total_prescription_sales_db) ?>', 'font-size:13px; color:#34D399;');
    console.log('%c❌ invalid_id error removed', 'font-size:13px; color:#DC2626;');
</script>

</body>
</html>