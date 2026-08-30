<?php
// ================================================================
// FILE: frontend/pages/admin/equipment_inventory.php
// ADMIN - EQUIPMENT INVENTORY MANAGEMENT
// WITH CARDS: All, Low Stock, Out of Stock, Expire Soon, Expired, Linked
// CARDS ACT AS FILTERS - CLICK TO FILTER
// WITH TABLE SCROLL ARROWS IN HEADER (◀ ▶)
// BLUE HEADER BACKGROUND
// ALL COLUMNS RESTORED
// BRAICK DISPENSARY - BLUE THEME
// WITH SESSION MANAGEMENT & LOGIN PROTECTION
// ================================================================

// ================================================================
// SESSION START
// ================================================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ================================================================
// LOGIN PROTECTION
// ================================================================
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: ../../auth/login.php');
    exit;
}

// ================================================================
// ROLE CHECK - ONLY ADMIN CAN ACCESS
// ================================================================
if ($_SESSION['role'] !== 'admin') {
    $role = $_SESSION['role'];
    switch ($role) {
        case 'doctor': header('Location: ../doctor/dashboard.php'); break;
        case 'reception': header('Location: ../reception/dashboard.php'); break;
        case 'pharmacy': header('Location: ../pharmacy/dashboard.php'); break;
        case 'laboratory': header('Location: ../laboratory/dashboard.php'); break;
        case 'cashier': header('Location: ../cashier/dashboard.php'); break;
        default: header('Location: ../../auth/login.php'); break;
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
$profile_pic = $_SESSION['profile_pic'] ?? '';

// Include database
require_once '../../../backend/config/database.php';
require_once '../../../backend/helpers/functions.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database connection error: " . $e->getMessage());
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
// GET FILTER PARAMETERS
// ================================================================
$selected_branch_id = isset($_GET['branch']) ? $_GET['branch'] : 'all';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';

// ================================================================
// VALIDATE BRANCH ID
// ================================================================
$branch_id_for_query = null;
if ($selected_branch_id !== 'all' && is_numeric($selected_branch_id)) {
    $branch_id_for_query = (int)$selected_branch_id;
}

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
// GET ALL EQUIPMENT WITH BATCH DETAILS
// ================================================================
$equipment_items = [];
$total_items = 0;
$low_stock_count = 0;
$out_of_stock_count = 0;
$expire_soon_count = 0;
$expired_count = 0;
$linked_count = 0;

try {
    $query = "
        SELECT 
            e.id,
            e.equipment_name,
            e.category,
            e.unit,
            e.quantity,
            e.reorder_level,
            e.selling_price,
            e.supplier,
            e.expiry_date,
            e.batch_number,
            e.branch_id,
            e.status,
            e.created_at,
            e.updated_at,
            b.name as branch_name,
            (SELECT COUNT(*) FROM lab_test_equipment WHERE equipment_id = e.id) as linked_count
        FROM medical_equipment e
        LEFT JOIN branches b ON e.branch_id = b.id
        WHERE 1=1
    ";
    
    $params = [];
    
    if ($branch_id_for_query !== null && is_numeric($branch_id_for_query)) {
        $query .= " AND (e.branch_id = ? OR e.branch_id IS NULL)";
        $params[] = $branch_id_for_query;
    }
    
    if ($status_filter !== 'all' && in_array($status_filter, ['active', 'inactive'])) {
        $query .= " AND e.status = ?";
        $params[] = $status_filter;
    }
    
    if (!empty($search)) {
        $query .= " AND (e.equipment_name LIKE ? OR e.category LIKE ? OR e.batch_number LIKE ?)";
        $search_term = "%$search%";
        $params[] = $search_term;
        $params[] = $search_term;
        $params[] = $search_term;
    }
    
    $query .= " ORDER BY e.equipment_name ASC";
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $equipment_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $today = date('Y-m-d');
    $thirty_days = date('Y-m-d', strtotime('+30 days'));
    
    foreach ($equipment_items as $item) {
        $total_items++;
        
        if ($item['quantity'] <= 0) {
            $out_of_stock_count++;
        } elseif ($item['quantity'] <= $item['reorder_level']) {
            $low_stock_count++;
        }
        
        if (!empty($item['expiry_date']) && $item['expiry_date'] !== '0000-00-00') {
            if ($item['expiry_date'] < $today) {
                $expired_count++;
            } elseif ($item['expiry_date'] <= $thirty_days) {
                $expire_soon_count++;
            }
        }
        
        if ($item['linked_count'] > 0) {
            $linked_count++;
        }
    }
} catch (Exception $e) {
    error_log("Error fetching equipment: " . $e->getMessage());
    $equipment_items = [];
}

// ================================================================
// APPLY FILTER TO DATA
// ================================================================
$filtered_items = [];
$today = date('Y-m-d');
$thirty_days = date('Y-m-d', strtotime('+30 days'));

foreach ($equipment_items as $item) {
    $include = false;
    
    switch ($filter) {
        case 'all':
            $include = true;
            break;
        case 'low_stock':
            $include = ($item['quantity'] > 0 && $item['quantity'] <= $item['reorder_level']);
            break;
        case 'out_of_stock':
            $include = ($item['quantity'] <= 0);
            break;
        case 'expire_soon':
            $include = (!empty($item['expiry_date']) && $item['expiry_date'] !== '0000-00-00' && 
                       $item['expiry_date'] >= $today && $item['expiry_date'] <= $thirty_days);
            break;
        case 'expired':
            $include = (!empty($item['expiry_date']) && $item['expiry_date'] !== '0000-00-00' && 
                       $item['expiry_date'] < $today);
            break;
        case 'linked':
            $include = ($item['linked_count'] > 0);
            break;
        default:
            $include = true;
    }
    
    if ($include) {
        $filtered_items[] = $item;
    }
}

// ================================================================
// PROFILE PICTURE URL
// ================================================================
$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

$logo_url = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

// ================================================================
// INCLUDE SHARED HEADER & SIDEBAR
// ================================================================
include_once '../../components/admin_header.php';
include_once '../../components/admin_sidebar.php';
?>

<!DOCTYPE html>
<html lang="en" data-theme="<?= isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Equipment Inventory - Braick Dispensary</title>
    
    <link rel="icon" href="<?= $logo_url ?>" type="image/png">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <style>
        :root {
            --primary: #0B5ED7;
            --primary-dark: #0A4CA8;
            --primary-light: #3B82F6;
            --primary-bg: #EFF6FF;
            --primary-gradient: linear-gradient(135deg, #0B5ED7, #0A4CA8);
            --primary-gradient-strong: linear-gradient(135deg, #0A4CA8, #073B8A);
            
            --success: #059669;
            --success-bg: #D1FAE5;
            --danger: #DC2626;
            --danger-bg: #FEE2E2;
            --warning: #D97706;
            --warning-bg: #FEF3C7;
            --purple: #7C3AED;
            --purple-bg: #EDE9FE;
            
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
            --primary-gradient: linear-gradient(135deg, #2563EB, #1D4ED8);
            --primary-gradient-strong: linear-gradient(135deg, #1D4ED8, #1E40AF);
            --shadow: 0 1px 3px rgba(0,0,0,0.3);
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
        
        /* ================================================================
           PAGE HEADER - BLUE BACKGROUND
           ================================================================ */
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
           FILTER CARDS - CLICKABLE
           ================================================================ */
        .filter-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 14px;
            margin-bottom: 24px;
        }
        
        .filter-card {
            background: var(--bg-card);
            border-radius: var(--radius);
            padding: 14px 16px;
            border: 3px solid var(--border-color);
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: var(--shadow-sm);
            position: relative;
            overflow: hidden;
            text-align: center;
        }
        
        .filter-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            transition: height 0.3s ease;
        }
        
        .filter-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-md);
        }
        
        .filter-card.active {
            border-color: var(--primary);
            box-shadow: 0 4px 20px rgba(11, 94, 215, 0.2);
            transform: translateY(-4px);
        }
        
        .filter-card.active::before {
            height: 5px;
        }
        
        .filter-card .card-icon {
            font-size: 1.3rem;
            margin-bottom: 2px;
            display: block;
        }
        
        .filter-card .card-count {
            font-size: 1.4rem;
            font-weight: 800;
            color: var(--text-primary);
            line-height: 1.2;
        }
        
        .filter-card .card-label {
            font-size: 0.6rem;
            color: var(--text-secondary);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        
        .filter-card.all { border-color: var(--primary); }
        .filter-card.all::before { background: var(--primary-gradient-strong); }
        .filter-card.all .card-icon { color: var(--primary); }
        .filter-card.all.active { border-color: var(--primary); }
        
        .filter-card.low_stock { border-color: var(--warning); }
        .filter-card.low_stock::before { background: var(--warning); }
        .filter-card.low_stock .card-icon { color: var(--warning); }
        .filter-card.low_stock.active { border-color: var(--warning); }
        
        .filter-card.out_of_stock { border-color: var(--danger); }
        .filter-card.out_of_stock::before { background: var(--danger); }
        .filter-card.out_of_stock .card-icon { color: var(--danger); }
        .filter-card.out_of_stock.active { border-color: var(--danger); }
        
        .filter-card.expire_soon { border-color: var(--warning); }
        .filter-card.expire_soon::before { background: var(--warning); }
        .filter-card.expire_soon .card-icon { color: var(--warning); }
        .filter-card.expire_soon.active { border-color: var(--warning); }
        
        .filter-card.expired { border-color: var(--danger); }
        .filter-card.expired::before { background: var(--danger); }
        .filter-card.expired .card-icon { color: var(--danger); }
        .filter-card.expired.active { border-color: var(--danger); }
        
        .filter-card.linked { border-color: var(--purple); }
        .filter-card.linked::before { background: var(--purple); }
        .filter-card.linked .card-icon { color: var(--purple); }
        .filter-card.linked.active { border-color: var(--purple); }
        
        /* ================================================================
           FILTER BAR
           ================================================================ */
        .filter-bar {
            background: var(--bg-card);
            border-radius: var(--radius);
            padding: 14px 18px;
            border: 2px solid var(--border-color);
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
            margin-bottom: 24px;
            box-shadow: var(--shadow-sm);
            position: relative;
            overflow: hidden;
        }
        
        .filter-bar::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--primary-gradient-strong);
        }
        
        .filter-bar .filter-label {
            font-size: 0.7rem;
            font-weight: 700;
            color: var(--primary);
            text-transform: uppercase;
            letter-spacing: 0.06em;
        }
        
        .filter-bar select,
        .filter-bar input {
            background: var(--bg-body);
            border: 2px solid var(--border-color);
            border-radius: 8px;
            padding: 5px 10px;
            font-size: 0.75rem;
            color: var(--text-primary);
            outline: none;
            transition: all 0.3s;
        }
        
        .filter-bar select:focus,
        .filter-bar input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(11, 94, 215, 0.15);
        }
        
        .filter-bar .btn-filter {
            background: var(--primary-gradient-strong);
            color: white;
            border: none;
            padding: 6px 16px;
            border-radius: 8px;
            font-weight: 700;
            font-size: 0.7rem;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .filter-bar .btn-filter:hover {
            transform: scale(1.03);
            box-shadow: 0 4px 16px rgba(10, 76, 168, 0.35);
        }
        
        .filter-bar .btn-reset {
            background: transparent;
            color: var(--text-secondary);
            border: 2px solid var(--border-color);
            padding: 6px 16px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.7rem;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
        }
        
        .filter-bar .btn-reset:hover {
            border-color: var(--primary);
            color: var(--primary);
        }
        
        /* ================================================================
           TABLE WITH SCROLL ARROWS IN HEADER
           ================================================================ */
        .card {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            padding: 20px 24px;
            border: 2px solid var(--border-color);
            transition: all 0.3s ease;
            box-shadow: var(--shadow-sm);
            margin-bottom: 24px;
        }
        
        .card:hover {
            border-color: var(--primary);
            box-shadow: var(--shadow-md);
        }
        
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .card-header-left {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }
        
        .card-header-right {
            display: flex;
            align-items: center;
            gap: 6px;
            flex-shrink: 0;
        }
        
        .card-title {
            font-size: 1rem;
            font-weight: 600;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .card-title i { color: var(--primary); }
        
        /* Scroll Arrow Buttons in Header */
        .scroll-arrow-btn {
            width: 34px;
            height: 34px;
            border-radius: 8px;
            border: 2px solid var(--border-color);
            background: var(--bg-card);
            color: var(--text-secondary);
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
        }
        
        .scroll-arrow-btn:hover {
            border-color: var(--primary);
            color: var(--primary);
            background: var(--primary-bg);
            transform: scale(1.05);
        }
        
        .scroll-arrow-btn:active {
            transform: scale(0.95);
        }
        
        .scroll-arrow-btn i {
            font-size: 0.8rem;
        }
        
        /* Table Container */
        .table-scroll-container {
            overflow-x: auto;
            border-radius: var(--radius);
            border: 1px solid var(--border-color);
            scroll-behavior: smooth;
            -webkit-overflow-scrolling: touch;
        }
        
        .table-scroll-container::-webkit-scrollbar {
            height: 6px;
        }
        
        .table-scroll-container::-webkit-scrollbar-track {
            background: var(--bg-body);
            border-radius: 10px;
        }
        
        .table-scroll-container::-webkit-scrollbar-thumb {
            background: var(--primary);
            border-radius: 10px;
        }
        
        .table-scroll-container table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.85rem;
            min-width: 1100px;
            white-space: nowrap;
        }
        
        /* Table Header - BLUE BACKGROUND */
        .table-scroll-container thead {
            background: var(--primary-gradient-strong);
            color: #ffffff;
        }
        
        .table-scroll-container thead th {
            padding: 12px 16px;
            text-align: left;
            font-weight: 600;
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            white-space: nowrap;
        }
        
        .table-scroll-container thead th i { margin-right: 6px; opacity: 0.8; }
        
        .table-scroll-container tbody tr {
            transition: all 0.3s ease;
            border-bottom: 1px solid var(--border-color);
        }
        
        .table-scroll-container tbody tr:last-child { border-bottom: none; }
        .table-scroll-container tbody tr:hover { background: var(--primary-bg); }
        [data-theme="dark"] .table-scroll-container tbody tr:hover { background: #1E3A5F; }
        
        .table-scroll-container tbody td {
            padding: 10px 16px;
            vertical-align: middle;
            color: var(--text-primary);
            white-space: nowrap;
        }
        
        /* ================================================================
           BADGES AND STYLES
           ================================================================ */
        .status-badge {
            display: inline-block;
            padding: 3px 14px;
            border-radius: 20px;
            font-size: 0.65rem;
            font-weight: 600;
        }
        
        .status-badge.active { background: var(--success-bg); color: var(--success); }
        .status-badge.inactive { background: var(--danger-bg); color: var(--danger); }
        
        .stock-badge {
            padding: 2px 10px;
            border-radius: 12px;
            font-size: 0.6rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .stock-badge.ok { background: var(--success-bg); color: var(--success); }
        .stock-badge.low { background: var(--warning-bg); color: var(--warning); }
        .stock-badge.out { background: var(--danger-bg); color: var(--danger); }
        
        .expiry-badge {
            padding: 2px 10px;
            border-radius: 12px;
            font-size: 0.6rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .expiry-badge.valid { background: var(--success-bg); color: var(--success); }
        .expiry-badge.expiring { background: var(--warning-bg); color: var(--warning); }
        .expiry-badge.expired { background: var(--danger-bg); color: var(--danger); }
        .expiry-badge.no-expiry { background: var(--gray-200); color: var(--gray-500); }
        
        .branch-tag {
            display: inline-block;
            background: var(--primary-bg);
            color: var(--primary);
            padding: 1px 10px;
            border-radius: 12px;
            font-size: 0.65rem;
            font-weight: 600;
        }
        
        .branch-tag.all-branches {
            background: #FEF3C7;
            color: #D97706;
        }
        
        [data-theme="dark"] .branch-tag.all-branches {
            background: #3D2E0A;
            color: #FBBF24;
        }
        
        .batch-number {
            font-family: monospace;
            font-size: 0.65rem;
            font-weight: 600;
            padding: 1px 8px;
            border-radius: 4px;
            background: var(--primary-bg);
            color: var(--primary);
        }
        
        [data-theme="dark"] .batch-number {
            background: #1E3A5F;
            color: #6EA8FE;
        }
        
        .price-display {
            font-weight: 600;
            color: var(--primary);
        }
        
        [data-theme="dark"] .price-display { color: var(--primary-light); }
        
        .linked-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 2px 10px;
            border-radius: 12px;
            font-size: 0.6rem;
            font-weight: 600;
            background: var(--purple-bg);
            color: var(--purple);
        }
        
        .action-btns {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
        }
        
        .btn-icon {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            text-decoration: none;
        }
        
        .btn-icon:hover { transform: scale(1.1); }
        
        .btn-icon.view { background: var(--primary-bg); color: var(--primary); }
        .btn-icon.view:hover { background: var(--primary); color: white; }
        .btn-icon.edit { background: var(--warning-bg); color: var(--warning); }
        .btn-icon.edit:hover { background: var(--warning); color: white; }
        .btn-icon.delete { background: var(--danger-bg); color: var(--danger); }
        .btn-icon.delete:hover { background: var(--danger); color: white; }
        
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: var(--text-secondary);
        }
        
        .empty-state i {
            font-size: 3rem;
            color: var(--gray-300);
            display: block;
            margin-bottom: 12px;
        }
        
        [data-theme="dark"] .empty-state i { color: var(--gray-600); }
        
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
        
        /* ================================================================
           MODAL
           ================================================================ */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            backdrop-filter: blur(4px);
            align-items: center;
            justify-content: center;
        }
        
        .modal-overlay.show { display: flex; }
        
        .modal-content {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            max-width: 450px;
            width: 95%;
            padding: 30px 35px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            animation: modalSlideIn 0.3s ease;
        }
        
        @keyframes modalSlideIn {
            from { transform: translateY(-30px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 2px solid var(--border-color);
            padding-bottom: 16px;
            margin-bottom: 20px;
        }
        
        .modal-header h2 {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .modal-header h2 i { color: var(--danger); }
        
        .modal-close {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            border: none;
            background: var(--danger-bg);
            color: var(--danger);
            cursor: pointer;
            font-size: 1rem;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .modal-close:hover {
            background: var(--danger);
            color: white;
            transform: rotate(90deg);
        }
        
        .modal-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
            justify-content: flex-end;
            border-top: 2px solid var(--border-color);
            padding-top: 16px;
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 20px;
            border-radius: var(--radius);
            font-weight: 600;
            font-size: 0.8rem;
            transition: all 0.3s ease;
            cursor: pointer;
            border: none;
            text-decoration: none;
            font-family: inherit;
        }
        
        .btn-outline {
            background: transparent;
            color: var(--text-secondary);
            border: 2px solid var(--border-color);
        }
        
        .btn-outline:hover {
            background: var(--gray-50);
            border-color: var(--primary);
            color: var(--primary);
        }
        
        .btn-danger {
            background: var(--danger);
            color: white;
            box-shadow: 0 2px 8px rgba(220, 38, 38, 0.2);
        }
        
        .btn-danger:hover {
            background: var(--danger-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(220, 38, 38, 0.3);
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
            .filter-cards { grid-template-columns: repeat(2, 1fr); }
            .filter-bar { flex-direction: column; align-items: stretch; }
            .card-header { flex-direction: column; align-items: flex-start !important; }
            .card-header-right { width: 100%; justify-content: flex-start; }
            .table-scroll-container table { min-width: 900px; font-size: 0.75rem; }
            .table-scroll-container thead th,
            .table-scroll-container tbody td { padding: 8px 10px; }
        }
        
        @media (max-width: 480px) {
            .main-content { padding: 10px; }
            .filter-cards { grid-template-columns: 1fr; }
            .page-header { flex-direction: column; align-items: flex-start !important; }
            .table-scroll-container table { min-width: 750px; font-size: 0.7rem; }
            .table-scroll-container thead th,
            .table-scroll-container tbody td { padding: 6px 8px; }
        }
        
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .animate-fade-in-up {
            animation: fadeInUp 0.5s ease forwards;
            opacity: 0;
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
            <input type="text" id="searchInput" placeholder="Search equipment..." value="<?= htmlspecialchars($search) ?>">
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

    <!-- ================================================================ -->
    <!-- PAGE HEADER - BLUE BACKGROUND -->
    <!-- ================================================================ -->
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-tools"></i>
                Equipment Inventory
                <span class="role-badge-display">ADMIN</span>
                <?php if ($selected_branch_id !== 'all'): ?>
                    <span class="role-badge-display" style="background:rgba(52,211,153,0.3);color:#34D399;">
                        <i class="fas fa-store-alt"></i> <?= htmlspecialchars($selected_branch_id == $user_branch_id ? $user_branch_name : 'Selected Branch') ?>
                    </span>
                <?php endif; ?>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-list"></i>
                <strong><?= count($filtered_items) ?></strong> equipment items found
                <span class="header-badge">
                    <i class="fas fa-boxes"></i> <?= number_format($total_items) ?> Total
                </span>
                <?php if ($filter !== 'all'): ?>
                    <span class="header-badge" style="background:rgba(251,191,36,0.2);border-color:rgba(251,191,36,0.3);color:#FBBF24;">
                        <i class="fas fa-filter"></i> <?= ucfirst(str_replace('_', ' ', $filter)) ?>
                    </span>
                <?php endif; ?>
            </p>
        </div>
        <div class="flex gap-2 flex-wrap" style="position:relative;z-index:1;">
            <a href="add_equipment.php?branch=<?= urlencode($selected_branch_id) ?>" class="btn-outline-light">
                <i class="fas fa-plus"></i> Add Equipment
            </a>
            <a href="services.php?branch=<?= urlencode($selected_branch_id) ?>&tab=equipment" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Back to Services
            </a>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- FILTER CARDS - CLICKABLE -->
    <!-- ================================================================ -->
    <div class="filter-cards animate-fade-in-up">
        <div class="filter-card all <?= $filter === 'all' ? 'active' : '' ?>" onclick="applyFilter('all')">
            <span class="card-icon"><i class="fas fa-boxes"></i></span>
            <div class="card-count"><?= $total_items ?></div>
            <div class="card-label">All Equipment</div>
        </div>
        
        <div class="filter-card low_stock <?= $filter === 'low_stock' ? 'active' : '' ?>" onclick="applyFilter('low_stock')">
            <span class="card-icon"><i class="fas fa-exclamation-triangle"></i></span>
            <div class="card-count"><?= $low_stock_count ?></div>
            <div class="card-label">Low Stock</div>
        </div>
        
        <div class="filter-card out_of_stock <?= $filter === 'out_of_stock' ? 'active' : '' ?>" onclick="applyFilter('out_of_stock')">
            <span class="card-icon"><i class="fas fa-times-circle"></i></span>
            <div class="card-count"><?= $out_of_stock_count ?></div>
            <div class="card-label">Out of Stock</div>
        </div>
        
        <div class="filter-card expire_soon <?= $filter === 'expire_soon' ? 'active' : '' ?>" onclick="applyFilter('expire_soon')">
            <span class="card-icon"><i class="fas fa-clock"></i></span>
            <div class="card-count"><?= $expire_soon_count ?></div>
            <div class="card-label">Expire Soon</div>
        </div>
        
        <div class="filter-card expired <?= $filter === 'expired' ? 'active' : '' ?>" onclick="applyFilter('expired')">
            <span class="card-icon"><i class="fas fa-skull-crossbones"></i></span>
            <div class="card-count"><?= $expired_count ?></div>
            <div class="card-label">Expired</div>
        </div>
        
        <div class="filter-card linked <?= $filter === 'linked' ? 'active' : '' ?>" onclick="applyFilter('linked')">
            <span class="card-icon"><i class="fas fa-link"></i></span>
            <div class="card-count"><?= $linked_count ?></div>
            <div class="card-label">Linked to Lab Tests</div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- FILTER BAR -->
    <!-- ================================================================ -->
    <div class="filter-bar animate-fade-in-up" style="animation-delay:0.05s;">
        <span class="filter-label"><i class="fas fa-filter"></i> Filter</span>
        
        <form method="GET" action="" class="flex flex-wrap gap-2 items-center w-full">
            <input type="hidden" name="branch" value="<?= htmlspecialchars($selected_branch_id) ?>">
            <input type="hidden" name="filter" value="<?= htmlspecialchars($filter) ?>">
            
            <select name="status" class="flex-1 min-w-[120px]">
                <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>All Status</option>
                <option value="active" <?= $status_filter === 'active' ? 'selected' : '' ?>>Active</option>
                <option value="inactive" <?= $status_filter === 'inactive' ? 'selected' : '' ?>>Inactive</option>
            </select>
            
            <input type="text" name="search" placeholder="Search equipment..." 
                   value="<?= htmlspecialchars($search) ?>" class="flex-1 min-w-[180px]">
            
            <button type="submit" class="btn-filter">
                <i class="fas fa-search"></i> Apply
            </button>
            
            <a href="equipment_inventory.php?branch=<?= urlencode($selected_branch_id) ?>" class="btn-reset">
                <i class="fas fa-times"></i> Reset
            </a>
        </form>
    </div>

    <!-- ================================================================ -->
    <!-- EQUIPMENT TABLE - WITH SCROLL ARROWS IN HEADER -->
    <!-- ================================================================ -->
    <div class="card animate-fade-in-up" style="animation-delay:0.1s;">
        <div class="card-header">
            <div class="card-header-left">
                <h3 class="card-title">
                    <i class="fas fa-list"></i> 
                    Equipment List
                    <span style="font-size:0.7rem;font-weight:400;color:var(--text-secondary);">
                        (<?= count($filtered_items) ?> items)
                    </span>
                </h3>
                <div style="font-size:0.7rem;color:var(--text-secondary);margin-top:2px;">
                    <i class="fas fa-info-circle"></i> Click cards above to filter 
                    <span style="margin:0 6px;">|</span> 
                    <i class="fas fa-arrows-alt-h"></i> Use arrows to scroll table
                </div>
            </div>
            <div class="card-header-right">
                <button onclick="scrollTable('left')" class="scroll-arrow-btn" title="Scroll Left (←)">
                    <i class="fas fa-chevron-left"></i>
                </button>
                <button onclick="scrollTable('right')" class="scroll-arrow-btn" title="Scroll Right (→)">
                    <i class="fas fa-chevron-right"></i>
                </button>
                <span style="font-size:0.6rem;color:var(--text-secondary);margin-left:4px;">
                    <i class="fas fa-arrows-alt-h"></i>
                </span>
            </div>
        </div>
        
        <!-- Table Container -->
        <div class="table-scroll-container" id="equipmentTableContainer">
            <?php if (count($filtered_items) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th><i class="fas fa-tools"></i> Equipment</th>
                            <th><i class="fas fa-tag"></i> Category</th>
                            <th><i class="fas fa-cube"></i> Unit</th>
                            <th><i class="fas fa-store-alt"></i> Branch</th>
                            <th><i class="fas fa-boxes"></i> Qty</th>
                            <th><i class="fas fa-chart-line"></i> Stock</th>
                            <th><i class="fas fa-tag"></i> Price</th>
                            <th><i class="fas fa-truck"></i> Supplier</th>
                            <th><i class="fas fa-calendar"></i> Expiry</th>
                            <th><i class="fas fa-link"></i> Linked</th>
                            <th><i class="fas fa-circle"></i> Status</th>
                            <th><i class="fas fa-cogs"></i> Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $i = 1; foreach ($filtered_items as $item): 
                            $stock_status = 'ok';
                            if ($item['quantity'] <= 0) {
                                $stock_status = 'out';
                            } elseif ($item['quantity'] <= $item['reorder_level']) {
                                $stock_status = 'low';
                            }
                            
                            $expiry_status = 'no-expiry';
                            $expiry_label = 'No Expiry';
                            if (!empty($item['expiry_date']) && $item['expiry_date'] !== '0000-00-00') {
                                $days_left = floor((strtotime($item['expiry_date']) - time()) / 86400);
                                if ($days_left < 0) {
                                    $expiry_status = 'expired';
                                    $expiry_label = 'Expired';
                                } elseif ($days_left <= 30) {
                                    $expiry_status = 'expiring';
                                    $expiry_label = 'Expiring Soon';
                                } else {
                                    $expiry_status = 'valid';
                                    $expiry_label = 'Valid';
                                }
                            }
                        ?>
                            <tr>
                                <td><?= $i++ ?></td>
                                <td>
                                    <strong><?= htmlspecialchars($item['equipment_name']) ?></strong>
                                    <div style="font-size:0.6rem;color:var(--text-secondary);">
                                        Batch: <span class="batch-number"><?= htmlspecialchars($item['batch_number']) ?></span>
                                    </div>
                                </td>
                                <td><?= htmlspecialchars($item['category'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($item['unit'] ?? 'pcs') ?></td>
                                <td>
                                    <?php if ($item['branch_id'] === null): ?>
                                        <span class="branch-tag all-branches">🌐 All Branches</span>
                                    <?php else: ?>
                                        <span class="branch-tag"><?= htmlspecialchars($item['branch_name'] ?? 'N/A') ?></span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align:center;font-weight:700;">
                                    <?= number_format($item['quantity']) ?>
                                </td>
                                <td>
                                    <span class="stock-badge <?= $stock_status ?>">
                                        <i class="fas <?= $stock_status === 'ok' ? 'fa-check-circle' : ($stock_status === 'low' ? 'fa-exclamation-triangle' : 'fa-times-circle') ?>"></i>
                                        <?= ucfirst(str_replace('_', ' ', $stock_status)) ?>
                                    </span>
                                </td>
                                <td class="price-display">
                                    <?= ($item['selling_price'] ?? 0) > 0 ? 'TSh ' . number_format($item['selling_price'], 0) : 'FREE' ?>
                                </td>
                                <td><?= htmlspecialchars($item['supplier'] ?? 'N/A') ?></td>
                                <td>
                                    <span class="expiry-badge <?= $expiry_status ?>">
                                        <i class="fas <?= $expiry_status === 'valid' ? 'fa-check' : ($expiry_status === 'expiring' ? 'fa-clock' : ($expiry_status === 'expired' ? 'fa-skull' : 'fa-infinity')) ?>"></i>
                                        <?= $expiry_label ?>
                                        <?php if ($expiry_status !== 'no-expiry' && !empty($item['expiry_date'])): ?>
                                            <br><span style="font-size:0.5rem;"><?= date('M d, Y', strtotime($item['expiry_date'])) ?></span>
                                        <?php endif; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($item['linked_count'] > 0): ?>
                                        <span class="linked-badge">
                                            <i class="fas fa-link"></i> <?= $item['linked_count'] ?>
                                        </span>
                                    <?php else: ?>
                                        <span style="font-size:0.6rem;color:var(--text-secondary);">Not linked</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="status-badge <?= $item['status'] === 'active' ? 'active' : 'inactive' ?>">
                                        <?= ucfirst($item['status'] ?? 'active') ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-btns">
                                        <a href="view_equipment.php?id=<?= $item['id'] ?>&branch=<?= urlencode($selected_branch_id) ?>" class="btn-icon view">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="edit_equipment.php?id=<?= $item['id'] ?>&branch=<?= urlencode($selected_branch_id) ?>" class="btn-icon edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <button class="btn-icon delete" onclick="deleteEquipment(<?= $item['id'] ?>, '<?= addslashes($item['equipment_name']) ?>')">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-tools"></i>
                    <p>No equipment items found matching your filters.</p>
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
            Equipment Inventory
            <span class="text-gray-300 mx-2">|</span>
            <span id="footerTime"><?= date('H:i:s') ?></span>
            <span class="text-gray-300 mx-2">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<!-- ================================================================ -->
<!-- DELETE CONFIRM MODAL -->
<!-- ================================================================ -->
<div class="modal-overlay" id="deleteModal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>
                <i class="fas fa-trash"></i> Confirm Delete
            </h2>
            <button class="modal-close" onclick="closeDeleteModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form method="POST" action="delete_equipment.php">
            <input type="hidden" name="delete_id" id="deleteId" value="">
            <p id="deleteMessage" style="margin-bottom:20px;font-size:1rem;">Are you sure you want to delete this equipment?</p>
            <div class="modal-actions">
                <button type="button" class="btn btn-outline" onclick="closeDeleteModal()">Cancel</button>
                <button type="submit" class="btn btn-danger">
                    <i class="fas fa-trash"></i> Delete
                </button>
            </div>
        </form>
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
        var branch = '<?= $selected_branch_id ?>';
        var filter = '<?= $filter ?>';
        var status = '<?= $status_filter ?>';
        var url = 'equipment_inventory.php?branch=' + encodeURIComponent(branch) + '&filter=' + encodeURIComponent(filter) + '&status=' + encodeURIComponent(status);
        if (query.length > 0) {
            url += '&search=' + encodeURIComponent(query);
        }
        window.location.href = url;
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
    // APPLY FILTER - CLICK CARD
    // ================================================================
    function applyFilter(filter) {
        var branch = '<?= $selected_branch_id ?>';
        var status = '<?= $status_filter ?>';
        var search = '<?= htmlspecialchars($search) ?>';
        var url = 'equipment_inventory.php?branch=' + encodeURIComponent(branch) + '&filter=' + encodeURIComponent(filter) + '&status=' + encodeURIComponent(status);
        if (search.length > 0) {
            url += '&search=' + encodeURIComponent(search);
        }
        window.location.href = url;
    }

    // ================================================================
    // TABLE SCROLL FUNCTIONS
    // ================================================================
    function scrollTable(direction) {
        var container = document.getElementById('equipmentTableContainer');
        if (!container) return;
        
        var scrollAmount = container.clientWidth * 0.7;
        if (direction === 'left') {
            container.scrollBy({ left: -scrollAmount, behavior: 'smooth' });
        } else {
            container.scrollBy({ left: scrollAmount, behavior: 'smooth' });
        }
    }

    // ================================================================
    // KEYBOARD SHORTCUTS - Arrow keys
    // ================================================================
    document.addEventListener('keydown', function(e) {
        var container = document.getElementById('equipmentTableContainer');
        if (!container) return;
        
        var isFocused = container.matches(':hover') || container.matches(':focus-within');
        if (!isFocused) return;
        
        if (e.key === 'ArrowLeft') {
            e.preventDefault();
            scrollTable('left');
        } else if (e.key === 'ArrowRight') {
            e.preventDefault();
            scrollTable('right');
        }
    });

    // ================================================================
    // DELETE EQUIPMENT
    // ================================================================
    function deleteEquipment(id, name) {
        document.getElementById('deleteId').value = id;
        document.getElementById('deleteMessage').textContent = 'Are you sure you want to delete "' + name + '"? This action cannot be undone.';
        document.getElementById('deleteModal').classList.add('show');
    }
    
    function closeDeleteModal() {
        document.getElementById('deleteModal').classList.remove('show');
    }
    
    document.getElementById('deleteModal')?.addEventListener('click', function(e) {
        if (e.target === this) {
            this.classList.remove('show');
        }
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

    console.log('%c🔧 Braick Dispensary - Equipment Inventory', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c👤 Admin: <?= htmlspecialchars($user_full_name) ?>', 'font-size:13px; color:#059669;');
    console.log('%c📦 Total Equipment: <?= $total_items ?>', 'font-size:13px; color:#0B5ED7;');
    console.log('%c⚠️ Low Stock: <?= $low_stock_count ?> | Out of Stock: <?= $out_of_stock_count ?>', 'font-size:13px; color:#D97706;');
    console.log('%c⏰ Expire Soon: <?= $expire_soon_count ?> | Expired: <?= $expired_count ?>', 'font-size:13px; color:#DC2626;');
    console.log('%c🔗 Linked to Lab Tests: <?= $linked_count ?>', 'font-size:13px; color:#7C3AED;');
    console.log('%c✅ Cards are clickable filters!', 'font-size:13px; color:#34D399;');
    console.log('%c◀ ▶ Scroll arrows in header!', 'font-size:13px; color:#34D399;');
    console.log('%c🔵 Table header background: BLUE!', 'font-size:13px; color:#34D399;');
    console.log('%c✅ All columns restored!', 'font-size:13px; color:#34D399;');
    console.log('%c🔒 Login protection: ACTIVE', 'font-size:13px; color:#34D399;');
</script>

</body>
</html>