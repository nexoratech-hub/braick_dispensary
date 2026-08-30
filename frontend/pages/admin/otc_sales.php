<?php
// ================================================================
// FILE: frontend/pages/admin/otc_sales.php
// SUPER ADMIN - VIEW OTC SALES BY BRANCH
// FIXED: $db variable defined before use
// FIXED: net_amount column removed (uses total_amount)
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

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database connection error: " . $e->getMessage());
}

// ================================================================
// GET BRANCH ID FROM URL
// ================================================================
$branch_id = isset($_GET['branch']) ? (int)$_GET['branch'] : 0;

// ================================================================
// HANDLE DELETE OTC SALE
// ================================================================
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $delete_id = (int)$_GET['delete'];
    try {
        // First delete items from otc_sale_items
        $stmt = $db->prepare("DELETE FROM otc_sale_items WHERE sale_id = ?");
        $stmt->execute([$delete_id]);
        
        // Then delete from otc_sales
        $stmt = $db->prepare("DELETE FROM otc_sales WHERE id = ?");
        $stmt->execute([$delete_id]);
        
        header('Location: otc_sales.php?branch=' . $branch_id . '&deleted=1');
        exit;
    } catch (Exception $e) {
        error_log("Delete error: " . $e->getMessage());
        $delete_error = "Error deleting sale: " . $e->getMessage();
    }
}

// ================================================================
// GET BRANCH NAME
// ================================================================
$branch_name = 'All Branches';
$branch_filter = 'all';
if ($branch_id > 0) {
    try {
        $stmt = $db->prepare("SELECT name FROM branches WHERE id = ?");
        $stmt->execute([$branch_id]);
        $branch = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($branch) {
            $branch_name = $branch['name'];
            $branch_filter = $branch_id;
        } else {
            $branch_id = 0;
            $branch_name = 'All Branches';
            $branch_filter = 'all';
        }
    } catch (Exception $e) {
        $branch_id = 0;
        $branch_name = 'All Branches';
        $branch_filter = 'all';
    }
}

// ================================================================
// GET FILTERS
// ================================================================
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$date_from = isset($_GET['date_from']) ? trim($_GET['date_from']) : '';
$date_to = isset($_GET['date_to']) ? trim($_GET['date_to']) : '';
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : 'all';

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
// BUILD QUERY FOR OTC SALES - FIXED: net_amount removed
// ================================================================
$sql_display = "
    SELECT 
        os.*,
        b.name as branch_name,
        u.full_name as sold_by_name,
        COALESCE((SELECT COUNT(*) FROM otc_sale_items WHERE sale_id = os.id), 0) as total_items
    FROM otc_sales os
    LEFT JOIN branches b ON os.branch_id = b.id
    LEFT JOIN users u ON os.sold_by = u.id
    WHERE 1=1
";

if ($branch_id > 0) {
    $sql_display .= " AND os.branch_id = " . (int)$branch_id;
}

if (!empty($search)) {
    $sql_display .= " AND (os.sale_number LIKE '%" . addslashes($search) . "%' 
              OR os.customer_name LIKE '%" . addslashes($search) . "%'
              OR os.customer_phone LIKE '%" . addslashes($search) . "%')";
}

if (!empty($date_from)) {
    $sql_display .= " AND DATE(os.created_at) >= '" . addslashes($date_from) . "'";
}

if (!empty($date_to)) {
    $sql_display .= " AND DATE(os.created_at) <= '" . addslashes($date_to) . "'";
}

if ($status_filter !== 'all') {
    $sql_display .= " AND os.payment_status = '" . addslashes($status_filter) . "'";
}

$sql_display .= " ORDER BY os.id DESC";

$otc_sales = [];
try {
    $stmt = $db->query($sql_display);
    $otc_sales = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching OTC sales: " . $e->getMessage());
    $otc_sales = [];
}

// ================================================================
// CALCULATE TOTALS - FIXED: net_amount removed
// ================================================================
$total_sales = count($otc_sales);
$total_revenue_paid = 0;
$total_gross = 0;
$total_discount = 0;
$total_paid = 0;
$total_pending = 0;
$total_cancelled = 0;
$total_items = 0;
$pending_amount = 0;

foreach ($otc_sales as $sale) {
    $total_gross += (float)($sale['total_amount'] ?? 0);
    $total_discount += (float)($sale['discount_amount'] ?? 0);
    $total_items += (int)($sale['total_items'] ?? 0);
    
    $status = $sale['payment_status'] ?? '';
    if ($status == 'paid') {
        $total_paid++;
        $total_revenue_paid += (float)($sale['total_amount'] ?? 0);
    } elseif ($status == 'pending') {
        $total_pending++;
        $pending_amount += (float)($sale['total_amount'] ?? 0);
    } elseif ($status == 'cancelled') {
        $total_cancelled++;
    }
}

// Net total is total_gross - total_discount
$total_net_all = $total_gross - $total_discount;

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
?>

<!DOCTYPE html>
<html lang="en" data-theme="<?= $dark_mode === 'true' ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Braick Dispensary - OTC Sales</title>
    
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
        
        .stats-grid-8 {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 14px;
            margin-bottom: 24px;
        }
        
        .stat-card-8 {
            border-radius: 14px;
            padding: 16px 18px;
            border: none;
            display: flex;
            flex-direction: column;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 4px 16px rgba(0,0,0,0.12);
            color: white;
            position: relative;
            overflow: hidden;
            min-height: 100px;
            cursor: default;
        }
        
        .stat-card-8::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -20%;
            width: 160px;
            height: 160px;
            background: rgba(255,255,255,0.06);
            border-radius: 50%;
            pointer-events: none;
            transition: all 0.5s ease;
        }
        .stat-card-8::after {
            content: '';
            position: absolute;
            bottom: -40%;
            left: -10%;
            width: 120px;
            height: 120px;
            background: rgba(255,255,255,0.04);
            border-radius: 50%;
            pointer-events: none;
            transition: all 0.5s ease;
        }
        .stat-card-8:hover {
            transform: translateY(-4px) scale(1.01);
            box-shadow: 0 10px 32px rgba(0,0,0,0.2);
        }
        .stat-card-8:hover::before { transform: scale(1.3); right: -10%; }
        .stat-card-8:hover::after { transform: scale(1.4); bottom: -30%; }
        
        .stat-card-8 .stat-icon {
            width: 44px;
            height: 44px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            flex-shrink: 0;
            background: rgba(255,255,255,0.18);
            color: white;
            border: 1px solid rgba(255,255,255,0.12);
            backdrop-filter: blur(8px);
            transition: all 0.3s ease;
            position: relative;
            z-index: 1;
            margin-bottom: 4px;
        }
        .stat-card-8:hover .stat-icon {
            transform: scale(1.05) rotate(-2deg);
            background: rgba(255,255,255,0.3);
        }
        .stat-card-8 .stat-content {
            position: relative;
            z-index: 1;
            flex: 1;
            display: flex;
            flex-direction: column;
        }
        .stat-card-8 .stat-label {
            font-size: 0.6rem;
            color: rgba(255,255,255,0.85);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            margin: 0 0 1px 0;
        }
        .stat-card-8 .stat-number-small {
            font-size: 2.2rem;
            font-weight: 800;
            color: white;
            margin: 0;
            line-height: 1.1;
            letter-spacing: -0.02em;
        }
        .stat-card-8 .stat-amount-large {
            font-size: 2rem;
            font-weight: 800;
            color: white;
            margin: 0;
            line-height: 1.2;
            letter-spacing: -0.02em;
        }
        .stat-card-8 .stat-currency {
            font-size: 1rem;
            font-weight: 600;
            color: rgba(255,255,255,0.9);
            margin-right: 3px;
        }
        .stat-card-8 .stat-sub {
            font-size: 0.7rem;
            color: rgba(255,255,255,0.9);
            margin-top: 3px;
            display: flex;
            align-items: center;
            gap: 6px;
            flex-wrap: wrap;
        }
        .stat-card-8 .stat-sub .highlight {
            color: rgba(255,255,255,0.95);
            font-weight: 600;
        }
        .stat-card-8 .stat-sub .badge-mini {
            font-size: 0.6rem;
            font-weight: 600;
            padding: 2px 10px;
            border-radius: 10px;
            background: rgba(255,255,255,0.2);
            color: white;
            display: inline-flex;
            align-items: center;
            gap: 3px;
            backdrop-filter: blur(4px);
        }
        .stat-card-8 .stat-sub .badge-mini.danger {
            background: rgba(239, 68, 68, 0.4);
            color: #FCA5A5;
        }
        .stat-card-8 .stat-sub .badge-mini.warning {
            background: rgba(245, 158, 11, 0.4);
            color: #FCD34D;
        }
        .stat-card-8 .stat-sub .badge-mini.success {
            background: rgba(52, 211, 153, 0.4);
            color: #6EE7B7;
        }
        .stat-card-8 .stat-arrow {
            position: absolute;
            right: 12px;
            bottom: 12px;
            color: rgba(255,255,255,0.12);
            font-size: 0.7rem;
            transition: all 0.3s ease;
            z-index: 1;
        }
        .stat-card-8:hover .stat-arrow {
            transform: translateX(6px);
            color: rgba(255,255,255,0.4);
        }
        
        .card-blue { background: linear-gradient(135deg, #0B5ED7, #0A4CA8); }
        .card-blue:hover { box-shadow: 0 10px 32px rgba(11, 94, 215, 0.4); }
        .card-red { background: linear-gradient(135deg, #DC2626, #B91C1C); }
        .card-red:hover { box-shadow: 0 10px 32px rgba(220, 38, 38, 0.4); }
        .card-green { background: linear-gradient(135deg, #059669, #047857); }
        .card-green:hover { box-shadow: 0 10px 32px rgba(5, 150, 105, 0.4); }
        .card-orange { background: linear-gradient(135deg, #D97706, #B45309); }
        .card-orange:hover { box-shadow: 0 10px 32px rgba(217, 119, 6, 0.4); }
        
        .filter-bar {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 20px;
            align-items: center;
            background: var(--bg-card);
            padding: 14px 18px;
            border-radius: 12px;
            border: 2px solid var(--border-color);
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
        }
        .filter-bar .filter-label {
            font-size: 0.7rem;
            font-weight: 600;
            color: var(--primary);
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        .filter-bar select, .filter-bar input {
            background: var(--bg-body);
            border: 2px solid var(--border-color);
            border-radius: 12px;
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
        .filter-bar input[type="date"] {
            min-width: 150px;
        }
        .btn {
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
            background: var(--primary-gradient-strong);
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
        
        .action-buttons {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
            justify-content: center;
        }
        .action-btn {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 5px 12px;
            border-radius: 6px;
            font-size: 0.6rem;
            font-weight: 600;
            transition: all 0.3s ease;
            text-decoration: none;
            border: none;
            cursor: pointer;
        }
        .action-btn i {
            font-size: 0.7rem;
        }
        .action-btn-view {
            background: #EFF6FF;
            color: #0B5ED7;
            border: 1px solid #0B5ED7;
        }
        .action-btn-view:hover {
            background: #0B5ED7;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3);
        }
        .action-btn-print {
            background: #F0FDF4;
            color: #059669;
            border: 1px solid #059669;
        }
        .action-btn-print:hover {
            background: #059669;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(5, 150, 105, 0.3);
        }
        .action-btn-delete {
            background: #FEF2F2;
            color: #DC2626;
            border: 1px solid #DC2626;
        }
        .action-btn-delete:hover {
            background: #DC2626;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(220, 38, 38, 0.3);
        }
        .action-btn-pay {
            background: #FFFBEB;
            color: #D97706;
            border: 1px solid #D97706;
        }
        .action-btn-pay:hover {
            background: #D97706;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(217, 119, 6, 0.3);
        }
        
        .sales-table-wrap {
            background: var(--bg-card);
            border-radius: 18px;
            border: 2px solid var(--border-color);
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
            margin-top: 24px;
        }
        .sales-table-wrap .table-header {
            padding: 14px 20px;
            background: var(--primary-gradient-strong);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 8px;
        }
        .sales-table-wrap .table-header .title {
            color: white;
            font-size: 0.9rem;
            font-weight: 600;
        }
        .sales-table-wrap .table-header .title i { margin-right: 8px; }
        .sales-table-wrap .table-header .count {
            color: rgba(255,255,255,0.8);
            font-size: 0.75rem;
        }
        .sales-table-wrap table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.78rem;
        }
        .sales-table-wrap table thead {
            background: var(--bg-body);
        }
        .sales-table-wrap table th {
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
        .sales-table-wrap table td {
            padding: 10px 14px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-primary);
            vertical-align: middle;
        }
        .sales-table-wrap table tr:hover td {
            background: #F8FAFC;
        }
        [data-theme="dark"] .sales-table-wrap table tr:hover td {
            background: #1E293B;
        }
        .sales-table-wrap .table-footer {
            text-align: center;
            padding: 10px 0;
            font-size: 0.7rem;
            color: var(--text-secondary);
            border-top: 1px solid var(--border-color);
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
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-secondary);
            background: var(--bg-card);
            border-radius: 18px;
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
        .page-header-box .header-badge.sales {
            background: rgba(52, 211, 153, 0.2);
            border-color: rgba(52, 211, 153, 0.3);
            color: #6EE7B7;
        }
        .page-header-box .header-badge.items {
            background: rgba(124, 58, 237, 0.2);
            border-color: rgba(124, 58, 237, 0.3);
            color: #A78BFA;
        }
        .page-header-box .header-badge.pending {
            background: rgba(251, 146, 60, 0.2);
            border-color: rgba(251, 146, 60, 0.3);
            color: #FDBA74;
        }
        
        @media (max-width: 1024px) {
            .top-nav { left: 0; }
            .main-content { margin-left: 0; padding: 16px; }
            .stats-grid-8 { grid-template-columns: repeat(4, 1fr); }
            .sidebar { transform: translateX(-100%); }
            .sidebar.open { transform: translateX(0); }
        }
        @media (max-width: 768px) {
            .stats-grid-8 { grid-template-columns: 1fr 1fr; }
            .page-header-box .page-title { font-size: 1.3rem; }
            .page-header-box { padding: 16px 18px; }
            .stat-card-8 { padding: 14px 16px; min-height: 90px; }
            .stat-card-8 .stat-number-small { font-size: 1.8rem; }
            .stat-card-8 .stat-amount-large { font-size: 1.6rem; }
            .stat-card-8 .stat-icon { width: 38px; height: 38px; font-size: 1rem; }
            .filter-bar { flex-direction: column; align-items: stretch; }
            .filter-bar select, .filter-bar input { width: 100%; min-width: unset; }
            .action-buttons { flex-wrap: wrap; justify-content: center; }
            .action-btn { font-size: 0.5rem; padding: 3px 8px; }
            .action-btn i { font-size: 0.6rem; }
        }
        @media (max-width: 480px) {
            .stats-grid-8 { grid-template-columns: 1fr; }
            .stat-card-8 { padding: 12px 14px; min-height: 80px; }
            .stat-card-8 .stat-number-small { font-size: 1.6rem; }
            .stat-card-8 .stat-amount-large { font-size: 1.4rem; }
            .stat-card-8 .stat-icon { width: 34px; height: 34px; font-size: 0.85rem; }
            .page-header-box .page-title { font-size: 1rem; flex-direction: column; align-items: flex-start; }
            .page-header-box .page-subtitle { font-size: 0.75rem; flex-direction: column; align-items: flex-start; gap: 4px; }
            .sales-table-wrap table { font-size: 0.65rem; }
            .sales-table-wrap table th, .sales-table-wrap table td { padding: 6px 8px; }
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
            <input type="text" id="searchInput" placeholder="Search OTC sales...">
            <button id="searchBtn" class="search-btn">
                <i class="fas fa-search mr-1"></i> Search
            </button>
        </div>
    </div>
    
    <div class="flex items-center gap-3">
        <select id="branchSelector" class="branch-selector" onchange="switchBranch(this.value)">
            <option value="all" <?= $branch_filter === 'all' ? 'selected' : '' ?>>🌐 All Branches</option>
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

    <!-- Page Header -->
    <div class="page-header-box animate-fade-in-up" style="animation-delay:0.05s;">
        <div>
            <h1 class="page-title">
                <i class="fas fa-shopping-cart"></i>
                OTC Sales
                <span class="role-badge-display">ADMIN</span>
                <?php if ($branch_id > 0): ?>
                    <span style="background:rgba(255,255,255,0.15);padding:3px 14px;border-radius:20px;font-size:0.75rem;font-weight:500;">
                        <i class="fas fa-store-alt"></i> <?= htmlspecialchars($branch_name) ?>
                    </span>
                <?php endif; ?>
            </h1>
            <p class="page-subtitle">
                <strong><?= number_format($total_sales) ?></strong> OTC transactions
                <span class="header-badge revenue">
                    <i class="fas fa-money-bill-wave"></i> TSh <?= number_format($total_revenue_paid, 0) ?> Revenue
                </span>
                <span class="header-badge pending">
                    <i class="fas fa-clock"></i> TSh <?= number_format($pending_amount, 0) ?> Pending
                </span>
                <span class="header-badge items">
                    <i class="fas fa-box"></i> <?= number_format($total_items) ?> Items
                </span>
            </p>
        </div>
        <div style="position:relative;z-index:1;">
            <a href="pharmacies.php" class="btn btn-outline" style="background:rgba(255,255,255,0.15);color:white;border-color:rgba(255,255,255,0.2);">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>

    <!-- 8 CARDS -->
    <div class="stats-grid-8 animate-fade-in-up" style="animation-delay:0.1s;">
        
        <!-- 1. Total Sales - BLUE -->
        <div class="stat-card-8 card-blue">
            <div class="stat-icon"><i class="fas fa-shopping-cart"></i></div>
            <div class="stat-content">
                <p class="stat-label">Total Sales</p>
                <p class="stat-number-small"><?= number_format($total_sales) ?></p>
                <p class="stat-sub">OTC transactions</p>
            </div>
            <i class="fas fa-arrow-right stat-arrow"></i>
        </div>
        
        <!-- 2. Total Revenue (PAID only) - BLUE -->
        <div class="stat-card-8 card-blue">
            <div class="stat-icon"><i class="fas fa-money-bill-wave"></i></div>
            <div class="stat-content">
                <p class="stat-label">Total Revenue</p>
                <p class="stat-amount-large"><span class="stat-currency">TSh</span> <?= number_format($total_revenue_paid, 0) ?></p>
                <p class="stat-sub">✅ From paid sales only</p>
            </div>
            <i class="fas fa-arrow-right stat-arrow"></i>
        </div>
        
        <!-- 3. Pending - ORANGE -->
        <div class="stat-card-8 card-orange">
            <div class="stat-icon"><i class="fas fa-clock"></i></div>
            <div class="stat-content">
                <p class="stat-label">Pending</p>
                <p class="stat-number-small"><?= number_format($total_pending) ?></p>
                <p class="stat-sub">
                    <span class="badge-mini warning">⏳ TSh <?= number_format($pending_amount, 0) ?></span>
                </p>
            </div>
            <i class="fas fa-arrow-right stat-arrow"></i>
        </div>
        
        <!-- 4. Net Amount - GREEN -->
        <div class="stat-card-8 card-green">
            <div class="stat-icon"><i class="fas fa-coins"></i></div>
            <div class="stat-content">
                <p class="stat-label">Net Amount</p>
                <p class="stat-amount-large"><span class="stat-currency">TSh</span> <?= number_format($total_net_all, 0) ?></p>
                <p class="stat-sub">All sales after discount</p>
            </div>
            <i class="fas fa-arrow-right stat-arrow"></i>
        </div>
        
        <!-- 5. Total Discount - GREEN -->
        <div class="stat-card-8 card-green">
            <div class="stat-icon"><i class="fas fa-percent"></i></div>
            <div class="stat-content">
                <p class="stat-label">Total Discount</p>
                <p class="stat-amount-large"><span class="stat-currency">TSh</span> <?= number_format($total_discount, 0) ?></p>
                <p class="stat-sub">Discount given</p>
            </div>
            <i class="fas fa-arrow-right stat-arrow"></i>
        </div>
        
        <!-- 6. Paid Sales - BLUE -->
        <div class="stat-card-8 card-blue">
            <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
            <div class="stat-content">
                <p class="stat-label">Paid Sales</p>
                <p class="stat-number-small"><?= number_format($total_paid) ?></p>
                <p class="stat-sub">✅ <?= format_currency($total_revenue_paid) ?></p>
            </div>
            <i class="fas fa-arrow-right stat-arrow"></i>
        </div>
        
        <!-- 7. Cancelled - RED -->
        <div class="stat-card-8 card-red">
            <div class="stat-icon"><i class="fas fa-times-circle"></i></div>
            <div class="stat-content">
                <p class="stat-label">Cancelled</p>
                <p class="stat-number-small"><?= number_format($total_cancelled) ?></p>
                <p class="stat-sub">
                    <span class="badge-mini danger">❌ Voided transactions</span>
                </p>
            </div>
            <i class="fas fa-arrow-right stat-arrow"></i>
        </div>
        
        <!-- 8. Items Sold - ORANGE -->
        <div class="stat-card-8 card-orange">
            <div class="stat-icon"><i class="fas fa-box"></i></div>
            <div class="stat-content">
                <p class="stat-label">Items Sold</p>
                <p class="stat-number-small"><?= number_format($total_items) ?></p>
                <p class="stat-sub">Total products sold</p>
            </div>
            <i class="fas fa-arrow-right stat-arrow"></i>
        </div>
        
    </div>

    <!-- Filters -->
    <div class="filter-bar animate-fade-in-up" style="animation-delay:0.15s;">
        <span class="filter-label"><i class="fas fa-filter"></i> Filter</span>
        <form method="GET" class="flex flex-wrap gap-3 items-center w-full">
            <input type="hidden" name="branch" value="<?= $branch_id ?>">
            
            <input type="date" name="date_from" value="<?= htmlspecialchars($date_from) ?>" placeholder="Date From" class="flex-1 min-w-[150px]">
            <input type="date" name="date_to" value="<?= htmlspecialchars($date_to) ?>" placeholder="Date To" class="flex-1 min-w-[150px]">
            
            <select name="status" class="flex-1 min-w-[150px]">
                <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>All Status</option>
                <option value="paid" <?= $status_filter === 'paid' ? 'selected' : '' ?>>Paid</option>
                <option value="pending" <?= $status_filter === 'pending' ? 'selected' : '' ?>>Pending</option>
                <option value="cancelled" <?= $status_filter === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
            </select>
            
            <input type="text" name="search" placeholder="Search by sale #, customer..." value="<?= htmlspecialchars($search) ?>" class="flex-1 min-w-[200px]">
            
            <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Filter</button>
            <a href="otc_sales.php?branch=<?= $branch_id ?>" class="btn btn-outline"><i class="fas fa-times"></i> Clear</a>
        </form>
    </div>

    <!-- OTC SALES TABLE -->
    <?php if (count($otc_sales) > 0): ?>
        <div class="sales-table-wrap animate-fade-in-up" style="animation-delay:0.2s;">
            <div class="table-header">
                <span class="title"><i class="fas fa-shopping-cart"></i> OTC Sales (<?= number_format($total_sales) ?> transactions)</span>
                <span class="count">
                    Paid: <?= format_currency($total_revenue_paid) ?> · 
                    Pending: <?= format_currency($pending_amount) ?> · 
                    Discount: <?= format_currency($total_discount) ?>
                </span>
            </div>
            <div style="overflow-x:auto;padding:0;">
                <table>
                    <thead>
                        <tr>
                            <th>Sale #</th>
                            <th>Customer</th>
                            <th>Branch</th>
                            <th>Sold By</th>
                            <th class="text-right">Items</th>
                            <th class="text-right">Total</th>
                            <th class="text-right">Discount</th>
                            <th class="text-right">Net</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($otc_sales as $sale): ?>
                            <tr>
                                <td class="font-mono text-xs font-semibold text-primary">
                                    <?= htmlspecialchars($sale['sale_number'] ?? 'N/A') ?>
                                </td>
                                <td>
                                    <?php if (!empty($sale['customer_name']) && $sale['customer_name'] !== 'Walk-in Customer'): ?>
                                        <?= htmlspecialchars($sale['customer_name']) ?>
                                        <?php if (!empty($sale['customer_phone'])): ?>
                                            <br><span class="text-xs text-gray-400"><?= htmlspecialchars($sale['customer_phone']) ?></span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-gray-400 text-xs">Walk-in</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge badge-info" style="font-size:0.55rem;padding:2px 10px;">
                                        <?= htmlspecialchars($sale['branch_name'] ?? 'N/A') ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($sale['sold_by_name'] ?? 'N/A') ?></td>
                                <td class="text-right font-semibold"><?= number_format($sale['total_items'] ?? 0) ?></td>
                                <td class="text-right font-semibold text-blue-600">TSh <?= number_format($sale['total_amount'] ?? 0, 0) ?></td>
                                <td class="text-right text-orange-500">TSh <?= number_format($sale['discount_amount'] ?? 0, 0) ?></td>
                                <td class="text-right font-semibold text-success">TSh <?= number_format(($sale['total_amount'] ?? 0) - ($sale['discount_amount'] ?? 0), 0) ?></td>
                                <td>
                                    <span class="badge badge-<?= getStatusBadge($sale['payment_status'] ?? 'pending') ?>" style="font-size:0.55rem;padding:2px 10px;">
                                        <?= ucfirst($sale['payment_status'] ?? 'Pending') ?>
                                    </span>
                                </td>
                                <td class="text-xs"><?= date('M d, Y H:i', strtotime($sale['created_at'] ?? 'now')) ?></td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="view_otc_sale.php?id=<?= $sale['id'] ?>&branch=<?= $branch_id ?>" 
                                           class="action-btn action-btn-view" title="View">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                        <?php if (($sale['payment_status'] ?? '') == 'pending'): ?>
                                            <a href="process_otc_payment.php?id=<?= $sale['id'] ?>&branch=<?= $branch_id ?>" 
                                               class="action-btn action-btn-pay" title="Process Payment">
                                                <i class="fas fa-credit-card"></i> Pay
                                            </a>
                                        <?php endif; ?>
                                        <?php if (($sale['payment_status'] ?? '') == 'paid'): ?>
                                            <a href="print_otc_receipt.php?id=<?= $sale['id'] ?>&branch=<?= $branch_id ?>" 
                                               class="action-btn action-btn-print" title="Print Receipt">
                                                <i class="fas fa-print"></i> Print
                                            </a>
                                        <?php endif; ?>
                                        <a href="?delete=<?= $sale['id'] ?>&branch=<?= $branch_id ?>" 
                                           class="action-btn action-btn-delete" 
                                           title="Delete"
                                           onclick="return confirm('Are you sure you want to delete OTC sale <?= htmlspecialchars($sale['sale_number'] ?? '') ?>? This action cannot be undone!')">
                                            <i class="fas fa-trash"></i> Delete
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <div class="table-footer">
                    <a href="reports.php?type=otc&branch=<?= $branch_id ?>" style="color:var(--primary);text-decoration:none;font-weight:600;">
                        <i class="fas fa-chart-bar"></i> View OTC Reports
                    </a>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="empty-state animate-fade-in-up">
            <i class="fas fa-shopping-cart"></i>
            <h3>No OTC Sales Found</h3>
            <p class="text-gray-400">
                <?php if (!empty($search) || !empty($date_from) || !empty($date_to) || $status_filter !== 'all'): ?>
                    No results match your search criteria.
                <?php else: ?>
                    No OTC sales have been recorded yet.
                <?php endif; ?>
            </p>
            <?php if (!empty($search) || !empty($date_from) || !empty($date_to) || $status_filter !== 'all'): ?>
                <a href="otc_sales.php?branch=<?= $branch_id ?>" class="btn btn-primary mt-4"><i class="fas fa-times"></i> Clear Filters</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- Footer -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            OTC Sales - <?= number_format($total_sales) ?> transactions
            <span class="text-gray-300 mx-2">|</span>
            <span id="footerTime"><?= date('H:i:s') ?></span>
            <span class="text-gray-300 mx-2">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<script>
    // Dark Mode
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

    // Sidebar Toggle
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

    // Branch Switcher
    function switchBranch(branchId) {
        var url = new URL(window.location.href);
        url.searchParams.set('branch', branchId);
        url.searchParams.delete('error');
        window.location.href = url.toString();
    }

    // Search
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

    // Date & Time
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

    console.log('%c🏥 Braick Dispensary - OTC Sales', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c👤 Admin: <?= htmlspecialchars($user_full_name) ?>', 'font-size:13px; color:#059669;');
    console.log('%c💰 Revenue (PAID): TSh <?= number_format($total_revenue_paid, 0) ?>', 'font-size:13px; color:#0B5ED7;');
    console.log('%c⏳ Pending: TSh <?= number_format($pending_amount, 0) ?>', 'font-size:13px; color:#D97706;');
    console.log('%c🔵🔵🟠🟢🟢🔵🔴🟠 CUSTOM COLORS', 'font-size:13px; color:#34D399;');
    console.log('%c✅ DELETE FIXED: $db defined before use', 'font-size:13px; color:#34D399;');
    console.log('%c✅ net_amount removed - using total_amount - discount_amount', 'font-size:13px; color:#34D399;');
    console.log('%c✅ View, Print, Delete buttons working', 'font-size:13px; color:#34D399;');
</script>

</body>
</html>