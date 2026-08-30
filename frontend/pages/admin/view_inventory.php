<?php
// ================================================================
// FILE: frontend/pages/admin/view_inventory.php
// ADMIN - VIEW INVENTORY ITEM DETAILS
// BRAICK DISPENSARY - BLUE THEME
// WITH SHARED HEADER & SIDEBAR
// ================================================================

// ================================================================
// START SESSION
// ================================================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ================================================================
// INCLUDE DATABASE
// ================================================================
require_once '../../../backend/config/database.php';
require_once '../../../backend/helpers/functions.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
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
// GET PARAMETERS
// ================================================================
$inventory_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$selected_branch_id = isset($_GET['branch']) ? $_GET['branch'] : 'all';

if ($inventory_id <= 0) {
    header('Location: pharmacy_inventory.php?branch=' . urlencode($selected_branch_id) . '&error=invalid_id');
    exit;
}

// ================================================================
// GET BRANCHES FOR FILTER
// ================================================================
$branches = [];
try {
    $stmt = $db->query("SELECT id, name, location FROM branches WHERE status = 'active' ORDER BY name");
    $branches = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $branches = [];
}

// ================================================================
// FETCH INVENTORY ITEM DETAILS
// ================================================================
$stmt = $db->prepare("
    SELECT 
        mi.*,
        b.name as branch_name,
        b.location as branch_location,
        b.phone as branch_phone,
        b.email as branch_email,
        (SELECT COUNT(*) FROM stock_movements WHERE inventory_id = mi.id) as total_movements,
        (SELECT COUNT(*) FROM otc_sale_items WHERE inventory_id = mi.id) as total_otc_sales,
        (SELECT COUNT(*) FROM prescription_items WHERE inventory_id = mi.id) as total_prescriptions
    FROM medications_inventory mi
    LEFT JOIN branches b ON mi.branch_id = b.id
    WHERE mi.id = ?
");
$stmt->execute([$inventory_id]);
$item = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$item) {
    header('Location: pharmacy_inventory.php?branch=' . urlencode($selected_branch_id) . '&error=notfound');
    exit;
}

// ================================================================
// GET STOCK MOVEMENT HISTORY
// ================================================================
$movements = [];
try {
    $stmt = $db->prepare("
        SELECT 
            sm.*,
            u.full_name as performed_by_name
        FROM stock_movements sm
        LEFT JOIN users u ON sm.performed_by = u.id
        WHERE sm.inventory_id = ?
        ORDER BY sm.created_at DESC
        LIMIT 20
    ");
    $stmt->execute([$inventory_id]);
    $movements = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $movements = [];
}

// ================================================================
// GET RELATED SALES (OTC)
// ================================================================
$related_sales = [];
try {
    // OTC Sales
    $stmt = $db->prepare("
        SELECT 
            os.sale_number,
            os.customer_name,
            os.total_amount,
            os.discount_amount,
            os.payment_method,
            os.payment_status,
            os.created_at,
            osi.quantity,
            osi.unit_price,
            osi.total_price,
            'OTC' as sale_type,
            osi.instructions
        FROM otc_sale_items osi
        LEFT JOIN otc_sales os ON osi.sale_id = os.id
        WHERE osi.inventory_id = ?
        ORDER BY os.created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$inventory_id]);
    $otc_sales = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $related_sales = $otc_sales;
} catch (Exception $e) {
    $related_sales = [];
}

// ================================================================
// CALCULATE ITEM STATUS
// ================================================================
$quantity = $item['quantity'] ?? 0;
$reorder_level = $item['reorder_level'] ?? 0;
$expiry_date = $item['expiry_date'] ?? null;

$is_out_of_stock = $quantity <= 0;
$is_low_stock = $quantity > 0 && $quantity <= $reorder_level;
$is_in_stock = $quantity > $reorder_level;
$is_expired = !empty($expiry_date) && $expiry_date !== '0000-00-00' && strtotime($expiry_date) < time();
$is_expiring_soon = !empty($expiry_date) && $expiry_date !== '0000-00-00' && strtotime($expiry_date) > time() && strtotime($expiry_date) < strtotime('+30 days');
$is_healthy = $is_in_stock && !$is_expired && !$is_expiring_soon;

// ================================================================
// FUNCTION: GET STATUS BADGE
// ================================================================
function getStatusBadge($status) {
    $classes = [
        'active' => 'success',
        'inactive' => 'danger',
        'pending' => 'warning',
        'paid' => 'success',
        'partial' => 'warning',
        'cancelled' => 'danger',
        'otc' => 'info',
        'prescription' => 'purple'
    ];
    return $classes[$status] ?? 'secondary';
}

function getStatusIcon($status) {
    $icons = [
        'active' => 'fa-check-circle',
        'inactive' => 'fa-times-circle',
        'pending' => 'fa-clock',
        'paid' => 'fa-check-circle',
        'partial' => 'fa-clock',
        'cancelled' => 'fa-times-circle',
        'otc' => 'fa-shopping-cart',
        'prescription' => 'fa-prescription'
    ];
    return $icons[$status] ?? 'fa-circle';
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
    <title>View Inventory - Braick Dispensary</title>
    
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
        
        .role-badge-display {
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
           DETAIL CARDS
           ================================================================ */
        .detail-card {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            padding: 24px 28px;
            border: 2px solid var(--border-color);
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
           STATUS BADGE LARGE
           ================================================================ */
        .status-badge-large {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 20px;
            border-radius: 50px;
            font-weight: 600;
            font-size: 0.85rem;
            color: white;
        }
        
        .status-badge-large.success { background: #059669; }
        .status-badge-large.danger { background: #DC2626; }
        .status-badge-large.warning { background: #D97706; color: #1E293B; }
        .status-badge-large.info { background: #0B5ED7; }
        .status-badge-large.secondary { background: #64748B; }
        
        [data-theme="dark"] .status-badge-large.warning { color: #1E293B; }
        
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
        .badge-purple { background: #7C3AED; }
        
        [data-theme="dark"] .badge-warning { color: #1E293B; }
        
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
            border: 2px solid var(--border-color);
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
            background: var(--primary-gradient-hover);
            border-color: var(--primary-dark);
            color: white;
        }
        
        .btn-success {
            background: linear-gradient(135deg, #059669, #047857);
            color: white;
            border-color: #059669;
        }
        
        .btn-success:hover {
            background: linear-gradient(135deg, #047857, #065F46);
            border-color: #047857;
            color: white;
        }
        
        .btn-danger {
            background: linear-gradient(135deg, #DC2626, #B91C1C);
            color: white;
            border-color: #DC2626;
        }
        
        .btn-danger:hover {
            background: linear-gradient(135deg, #B91C1C, #991B1B);
            border-color: #B91C1C;
            color: white;
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
           EMPTY STATE
           ================================================================ */
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: var(--text-secondary);
        }
        
        .empty-state i {
            font-size: 2.5rem;
            color: var(--border-color);
            margin-bottom: 12px;
        }
        
        .empty-state h4 {
            font-size: 1rem;
            color: var(--text-primary);
            margin-bottom: 4px;
        }
        
        .empty-state p {
            font-size: 0.85rem;
            color: var(--text-secondary);
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
            .detail-card .grid { grid-template-columns: 1fr 1fr; }
            .data-table { font-size: 0.7rem; }
            .data-table thead th, .data-table td { padding: 6px 8px; }
        }
        
        @media (max-width: 480px) {
            .main-content { padding: 10px; }
            .page-header { flex-direction: column; align-items: flex-start !important; }
            .detail-card .grid { grid-template-columns: 1fr; }
            .card-header { flex-direction: column; align-items: flex-start; }
            .btn { width: 100%; justify-content: center; }
            .flex-wrap.gap-3 .btn { width: 100%; }
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
                <i class="fas fa-capsules"></i>
                Inventory Item Details
                <span class="role-badge-display">ADMIN</span>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-pills"></i>
                <strong><?= htmlspecialchars($item['medication_name'] ?? 'N/A') ?></strong>
                <span class="header-badge" style="background:rgba(52,211,153,0.2);border-color:rgba(52,211,153,0.3);color:#34D399;">
                    <i class="fas fa-hashtag"></i> ID: #<?= $item['id'] ?>
                </span>
                <span class="header-badge" style="background:rgba(251,191,36,0.2);border-color:rgba(251,191,36,0.3);color:#FBBF24;">
                    <i class="fas fa-store"></i> <?= htmlspecialchars($item['branch_name'] ?? 'N/A') ?>
                </span>
                <span class="header-badge" style="background:rgba(255,255,255,0.15);">
                    <i class="fas fa-user"></i> <?= htmlspecialchars($user_full_name) ?>
                </span>
            </p>
        </div>
        <div class="flex gap-2 flex-wrap" style="position:relative;z-index:1;">
            <a href="edit_inventory.php?id=<?= $item['id'] ?>&branch=<?= $selected_branch_id ?>" class="btn-outline-light">
                <i class="fas fa-edit"></i> Edit
            </a>
            <a href="pharmacy_inventory.php?id=<?= $item['branch_id'] ?>&branch=<?= $selected_branch_id ?>" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- ITEM STATUS SUMMARY -->
    <!-- ================================================================ -->
    <div class="detail-card animate-fade-in-up" style="animation-delay:0.05s;">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h2 class="text-xl font-bold" style="color:var(--primary);"><?= htmlspecialchars($item['medication_name'] ?? 'N/A') ?></h2>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    <i class="fas fa-tag mr-1"></i> <?= htmlspecialchars($item['category'] ?? 'Uncategorized') ?>
                    <span class="mx-2">|</span>
                    <i class="fas fa-box mr-1"></i> <?= htmlspecialchars($item['unit'] ?? 'Unit') ?>
                </p>
            </div>
            <div>
                <?php if ($is_out_of_stock): ?>
                    <span class="status-badge-large danger">
                        <i class="fas fa-times-circle"></i> Out of Stock
                    </span>
                <?php elseif ($is_low_stock): ?>
                    <span class="status-badge-large warning">
                        <i class="fas fa-exclamation-triangle"></i> Low Stock
                    </span>
                <?php elseif ($is_expired): ?>
                    <span class="status-badge-large danger">
                        <i class="fas fa-skull"></i> Expired
                    </span>
                <?php elseif ($is_expiring_soon): ?>
                    <span class="status-badge-large warning">
                        <i class="fas fa-hourglass-half"></i> Expiring Soon
                    </span>
                <?php elseif ($is_healthy): ?>
                    <span class="status-badge-large success">
                        <i class="fas fa-check-circle"></i> In Stock
                    </span>
                <?php else: ?>
                    <span class="status-badge-large secondary">
                        <i class="fas fa-circle"></i> Unknown
                    </span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- ITEM DETAILS -->
    <!-- ================================================================ -->
    <div class="detail-card animate-fade-in-up" style="animation-delay:0.1s;">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <div>
                <p class="detail-label"><i class="fas fa-hashtag mr-1"></i> Item ID</p>
                <p class="detail-value">#<?= $item['id'] ?></p>
            </div>
            <div>
                <p class="detail-label"><i class="fas fa-store mr-1"></i> Branch</p>
                <p class="detail-value"><?= htmlspecialchars($item['branch_name'] ?? 'N/A') ?></p>
            </div>
            <div>
                <p class="detail-label"><i class="fas fa-tag mr-1"></i> Category</p>
                <p class="detail-value"><?= htmlspecialchars($item['category'] ?? 'N/A') ?></p>
            </div>
            <div>
                <p class="detail-label"><i class="fas fa-cubes mr-1"></i> Quantity</p>
                <p class="detail-value <?= $is_out_of_stock ? 'text-red-600' : ($is_low_stock ? 'text-amber-600' : 'text-green-600') ?>">
                    <?= number_format($item['quantity'] ?? 0) ?> <?= htmlspecialchars($item['unit'] ?? '') ?>
                </p>
            </div>
            <div>
                <p class="detail-label"><i class="fas fa-flag mr-1"></i> Reorder Level</p>
                <p class="detail-value"><?= number_format($item['reorder_level'] ?? 0) ?> <?= htmlspecialchars($item['unit'] ?? '') ?></p>
            </div>
            <div>
                <p class="detail-label"><i class="fas fa-money-bill-wave mr-1"></i> Selling Price</p>
                <p class="detail-value" style="color:var(--primary);">TSh <?= number_format($item['selling_price'] ?? 0, 0) ?></p>
            </div>
            <div>
                <p class="detail-label"><i class="fas fa-coins mr-1"></i> Unit Cost</p>
                <p class="detail-value">TSh <?= number_format($item['unit_cost'] ?? 0, 0) ?></p>
            </div>
            <div>
                <p class="detail-label"><i class="fas fa-calendar-alt mr-1"></i> Expiry Date</p>
                <p class="detail-value <?= $is_expired ? 'text-red-600' : ($is_expiring_soon ? 'text-amber-600' : '') ?>">
                    <?php 
                    if (!empty($item['expiry_date']) && $item['expiry_date'] !== '0000-00-00') {
                        echo date('M d, Y', strtotime($item['expiry_date']));
                    } else {
                        echo 'N/A';
                    }
                    ?>
                    <?php if ($is_expired): ?>
                        <span class="text-red-600 text-xs block">(Expired)</span>
                    <?php elseif ($is_expiring_soon): ?>
                        <span class="text-amber-600 text-xs block">(Expiring soon)</span>
                    <?php endif; ?>
                </p>
            </div>
            <div>
                <p class="detail-label"><i class="fas fa-barcode mr-1"></i> Batch Number</p>
                <p class="detail-value"><?= htmlspecialchars($item['batch_number'] ?? 'N/A') ?></p>
            </div>
            <div>
                <p class="detail-label"><i class="fas fa-truck mr-1"></i> Supplier</p>
                <p class="detail-value"><?= htmlspecialchars($item['supplier'] ?? 'N/A') ?></p>
            </div>
            <div>
                <p class="detail-label"><i class="fas fa-calendar-plus mr-1"></i> Created</p>
                <p class="detail-value"><?= date('M d, Y h:i A', strtotime($item['created_at'] ?? 'now')) ?></p>
            </div>
            <div>
                <p class="detail-label"><i class="fas fa-clock mr-1"></i> Last Updated</p>
                <p class="detail-value"><?= date('M d, Y h:i A', strtotime($item['updated_at'] ?? 'now')) ?></p>
            </div>
            <div>
                <p class="detail-label"><i class="fas fa-exchange-alt mr-1"></i> Total Movements</p>
                <p class="detail-value"><?= number_format($item['total_movements'] ?? 0) ?></p>
            </div>
            <div>
                <p class="detail-label"><i class="fas fa-shopping-cart mr-1"></i> OTC Sales</p>
                <p class="detail-value"><?= number_format($item['total_otc_sales'] ?? 0) ?></p>
            </div>
            <div>
                <p class="detail-label"><i class="fas fa-prescription mr-1"></i> Prescriptions</p>
                <p class="detail-value"><?= number_format($item['total_prescriptions'] ?? 0) ?></p>
            </div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- STOCK MOVEMENT HISTORY -->
    <!-- ================================================================ -->
    <div class="card animate-fade-in-up" style="animation-delay:0.15s;">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-history" style="color:var(--primary);"></i>
                Stock Movement History
            </h3>
            <span class="text-xs text-gray-500">Last 20 movements</span>
        </div>
        <div class="overflow-x-auto">
            <?php if (count($movements) > 0): ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Type</th>
                            <th>Quantity</th>
                            <th>Previous Stock</th>
                            <th>New Stock</th>
                            <th>Performed By</th>
                            <th>Notes</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($movements as $movement): ?>
                            <tr>
                                <td>
                                    <span class="badge <?= ($movement['movement_type'] ?? 'out') === 'in' ? 'badge-success' : 'badge-danger' ?>" style="font-size:0.6rem;padding:2px 10px;">
                                        <?= ucfirst($movement['movement_type'] ?? 'N/A') ?>
                                    </span>
                                </td>
                                <td class="font-semibold <?= ($movement['movement_type'] ?? 'out') === 'in' ? 'text-green-600' : 'text-red-600' ?>">
                                    <?= ($movement['movement_type'] ?? 'out') === 'in' ? '+' : '-' ?>
                                    <?= number_format($movement['quantity'] ?? 0) ?>
                                </td>
                                <td><?= number_format($movement['previous_stock'] ?? 0) ?></td>
                                <td><?= number_format($movement['new_stock'] ?? 0) ?></td>
                                <td><?= htmlspecialchars($movement['performed_by_name'] ?? 'System') ?></td>
                                <td class="text-xs"><?= htmlspecialchars(substr($movement['notes'] ?? '', 0, 50)) ?></td>
                                <td class="text-xs"><?= date('M d, Y h:i A', strtotime($movement['created_at'] ?? 'now')) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-history"></i>
                    <h4>No Stock Movements</h4>
                    <p>This item has no stock movement history yet.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- RELATED SALES (OTC) -->
    <!-- ================================================================ -->
    <div class="card animate-fade-in-up" style="animation-delay:0.2s;">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-receipt" style="color:var(--success);"></i>
                Related OTC Sales
            </h3>
            <span class="text-xs text-gray-500">Last 10 sales</span>
        </div>
        <div class="overflow-x-auto">
            <?php if (count($related_sales) > 0): ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Sale #</th>
                            <th>Customer</th>
                            <th>Qty</th>
                            <th>Unit Price</th>
                            <th>Total</th>
                            <th>Payment</th>
                            <th>Status</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($related_sales as $sale): ?>
                            <tr>
                                <td class="font-mono text-xs"><?= htmlspecialchars($sale['sale_number'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($sale['customer_name'] ?? 'Walk-in') ?></td>
                                <td><?= number_format($sale['quantity'] ?? 0) ?></td>
                                <td>TSh <?= number_format($sale['unit_price'] ?? 0, 0) ?></td>
                                <td class="font-semibold">TSh <?= number_format($sale['total_price'] ?? 0, 0) ?></td>
                                <td class="text-xs"><?= ucfirst($sale['payment_method'] ?? 'N/A') ?></td>
                                <td>
                                    <span class="badge badge-<?= getStatusBadge($sale['payment_status'] ?? 'pending') ?>" style="font-size:0.6rem;padding:2px 10px;">
                                        <?= ucfirst($sale['payment_status'] ?? 'Pending') ?>
                                    </span>
                                </td>
                                <td class="text-xs"><?= date('M d, Y', strtotime($sale['created_at'] ?? 'now')) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-receipt"></i>
                    <h4>No Related Sales</h4>
                    <p>This item has no OTC sales records yet.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- QUICK ACTIONS -->
    <!-- ================================================================ -->
    <div class="detail-card animate-fade-in-up" style="animation-delay:0.25s;">
        <h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-3">
            <i class="fas fa-bolt" style="color:var(--primary);"></i> Quick Actions
        </h3>
        <div class="flex flex-wrap gap-3">
            <a href="edit_inventory.php?id=<?= $item['id'] ?>&branch=<?= $selected_branch_id ?>" class="btn btn-primary">
                <i class="fas fa-edit"></i> Edit Item
            </a>
            <a href="add_stock.php?id=<?= $item['id'] ?>&branch=<?= $selected_branch_id ?>" class="btn btn-success">
                <i class="fas fa-plus-circle"></i> Add Stock
            </a>
            <a href="remove_stock.php?id=<?= $item['id'] ?>&branch=<?= $selected_branch_id ?>" class="btn btn-danger">
                <i class="fas fa-minus-circle"></i> Remove Stock
            </a>
            <a href="pharmacy_inventory.php?id=<?= $item['branch_id'] ?>&branch=<?= $selected_branch_id ?>" class="btn btn-outline">
                <i class="fas fa-arrow-left"></i> Back to Inventory
            </a>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Inventory Item - <?= htmlspecialchars($item['medication_name'] ?? 'N/A') ?>
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

    console.log('%c💊 Braick Dispensary - View Inventory Item (BLUE THEME)', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c👤 Admin: <?= htmlspecialchars($user_full_name) ?> (ID: <?= $user_id ?>)', 'font-size:13px; color:#059669;');
    console.log('%c📦 Item: <?= htmlspecialchars($item['medication_name'] ?? 'N/A') ?> (ID: <?= $item['id'] ?>)', 'font-size:13px; color:#059669;');
    console.log('%c🏥 Branch: <?= htmlspecialchars($item['branch_name'] ?? 'N/A') ?>', 'font-size:13px; color:#7C3AED;');
    console.log('%c📊 Quantity: <?= number_format($item['quantity'] ?? 0) ?> <?= htmlspecialchars($item['unit'] ?? '') ?>', 'font-size:13px; color:#0B5ED7;');
    console.log('%c💰 Price: TSh <?= number_format($item['selling_price'] ?? 0, 0) ?>', 'font-size:13px; color:#059669;');
    console.log('%c⏰ Expiry: <?= !empty($item['expiry_date']) && $item['expiry_date'] !== '0000-00-00' ? date('M d, Y', strtotime($item['expiry_date'])) : 'N/A' ?>', 'font-size:13px; color:#D97706;');
    console.log('%c📋 Total Movements: <?= number_format($item['total_movements'] ?? 0) ?>', 'font-size:13px; color:#7C3AED;');
    console.log('%c🔒 Login session: ACTIVE', 'font-size:13px; color:#34D399;');
    console.log('%c🔑 Role: <?= $_SESSION['role'] ?>', 'font-size:13px; color:#7C3AED;');
</script>

</body>
</html>