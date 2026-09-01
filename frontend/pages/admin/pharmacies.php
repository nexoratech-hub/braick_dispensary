<?php
// ================================================================
// FILE: frontend/pages/admin/pharmacies.php
// SUPER ADMIN - VIEW ALL PHARMACIES WITH BRANCH FILTERING
// 8 CARDS WITH CUSTOM COLORS: BLUE, GREEN, ORANGE, RED
// DATA FILTERED BY SELECTED BRANCH
// FIXED: Total Revenue = Prescription Revenue + OTC Revenue ONLY
// ADDED: Inventory Table showing all medicines per branch
// ADDED: ONE Inventory button on top header (GREEN color)
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
// REMOVE error=invalid_id FROM URL
// ================================================================
if (isset($_GET['error']) && $_GET['error'] === 'invalid_id') {
    $params = $_GET;
    unset($params['error']);
    $new_url = 'pharmacies.php?' . http_build_query($params);
    header('Location: ' . $new_url);
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
// GET FILTERS
// ================================================================
$selected_branch_id = isset($_GET['branch']) ? trim($_GET['branch']) : 'all';

if ($selected_branch_id !== 'all' && !is_numeric($selected_branch_id)) {
    $selected_branch_id = 'all';
}

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
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
// BUILD BRANCH FILTER CONDITION
// ================================================================
$branch_condition = "";
$branch_params = [];

if ($selected_branch_id !== 'all' && is_numeric($selected_branch_id)) {
    $branch_condition = " AND branch_id = ?";
    $branch_params[] = (int)$selected_branch_id;
}

// ================================================================
// GET OTC SALES DATA (FILTERED BY BRANCH) - FIXED: net_amount removed
// ================================================================
$total_otc_sales_db = 0;
$total_otc_revenue_db = 0;

try {
    $sql_otc = "
        SELECT 
            COUNT(*) as total_sales,
            COALESCE(SUM(total_amount), 0) as total_revenue
        FROM otc_sales 
        WHERE payment_status = 'paid'
    ";
    if ($selected_branch_id !== 'all' && is_numeric($selected_branch_id)) {
        $sql_otc .= " AND branch_id = " . (int)$selected_branch_id;
    }
    
    $stmt = $db->query($sql_otc);
    $otc_totals = $stmt->fetch(PDO::FETCH_ASSOC);
    $total_otc_sales_db = $otc_totals['total_sales'] ?? 0;
    $total_otc_revenue_db = $otc_totals['total_revenue'] ?? 0;
} catch (Exception $e) {
    $total_otc_sales_db = 0;
    $total_otc_revenue_db = 0;
}

// ================================================================
// GET TOTAL PRESCRIPTIONS COUNT (FILTERED BY BRANCH)
// ================================================================
$total_prescriptions_count = 0;
$total_dispensed_count = 0;
$total_pending_count = 0;
$total_prescription_revenue = 0;

try {
    $sql = "SELECT COUNT(*) as total FROM prescriptions WHERE 1=1";
    if ($selected_branch_id !== 'all' && is_numeric($selected_branch_id)) {
        $sql .= " AND branch_id = " . (int)$selected_branch_id;
    }
    $stmt = $db->query($sql);
    $total_prescriptions_count = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    $sql = "SELECT COUNT(*) as total FROM prescriptions WHERE status = 'dispensed'";
    if ($selected_branch_id !== 'all' && is_numeric($selected_branch_id)) {
        $sql .= " AND branch_id = " . (int)$selected_branch_id;
    }
    $stmt = $db->query($sql);
    $total_dispensed_count = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    $sql = "SELECT COUNT(*) as total FROM prescriptions WHERE status = 'pending'";
    if ($selected_branch_id !== 'all' && is_numeric($selected_branch_id)) {
        $sql .= " AND branch_id = " . (int)$selected_branch_id;
    }
    $stmt = $db->query($sql);
    $total_pending_count = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    // Get prescription revenue from prescription_items (via prescriptions)
    $sql = "
        SELECT COALESCE(SUM(pi.total_price), 0) as total_revenue
        FROM prescription_items pi
        INNER JOIN prescriptions p ON pi.prescription_id = p.id
        WHERE p.status = 'dispensed'
    ";
    if ($selected_branch_id !== 'all' && is_numeric($selected_branch_id)) {
        $sql .= " AND p.branch_id = " . (int)$selected_branch_id;
    }
    $stmt = $db->query($sql);
    $total_prescription_revenue = $stmt->fetch(PDO::FETCH_ASSOC)['total_revenue'] ?? 0;
    
} catch (Exception $e) {
    $total_prescriptions_count = 0;
    $total_dispensed_count = 0;
    $total_pending_count = 0;
    $total_prescription_revenue = 0;
}

// ================================================================
// FIXED: TOTAL REVENUE = Prescription Revenue + OTC Revenue ONLY
// ================================================================
$total_revenue = $total_prescription_revenue + $total_otc_revenue_db;

// ================================================================
// BUILD QUERY FOR PHARMACIES
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
        (SELECT COUNT(*) FROM otc_sales WHERE branch_id = b.id) as total_otc_sales,
        (SELECT COALESCE(SUM(total_amount), 0) FROM otc_sales WHERE branch_id = b.id AND payment_status = 'paid') as otc_revenue,
        (SELECT COUNT(*) FROM medications_inventory WHERE branch_id = b.id AND status = 'active' AND expiry_date < CURDATE()) as expired_medicines,
        (SELECT COUNT(*) FROM medications_inventory WHERE branch_id = b.id AND status = 'active' AND expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)) as expiring_soon_medicines,
        (SELECT COUNT(*) FROM medications_inventory WHERE branch_id = b.id AND status = 'active') as total_active_medicines,
        (SELECT COALESCE(SUM(pi.total_price), 0) 
         FROM prescription_items pi
         INNER JOIN prescriptions p ON pi.prescription_id = p.id
         WHERE p.branch_id = b.id AND p.status = 'dispensed') as prescription_revenue,
        (SELECT COUNT(*) FROM prescriptions WHERE branch_id = b.id AND status = 'dispensed') as prescription_sales_count
    FROM branches b
    WHERE 1=1
";

if ($selected_branch_id !== 'all' && is_numeric($selected_branch_id)) {
    $sql .= " AND b.id = " . (int)$selected_branch_id;
}

if ($status_filter !== 'all') {
    $sql .= " AND b.status = '" . addslashes($status_filter) . "'";
}

if (!empty($search)) {
    $sql .= " AND (b.name LIKE '%" . addslashes($search) . "%' OR b.location LIKE '%" . addslashes($search) . "%')";
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
// CALCULATE TOTALS FOR 8 CARDS (FILTERED BY BRANCH)
// ================================================================
$total_pharmacies = count($pharmacies);
$total_medicines = 0;
$total_prescriptions = 0;
$total_dispensed = 0;
$total_pending = 0;
$total_otc_sales = 0;
$total_otc_revenue = 0;
$total_prescription_revenue_sum = 0;
$total_revenue_sum = 0;
$total_out_of_stock = 0;
$total_low_stock = 0;
$total_expired = 0;
$total_expiring_soon = 0;

foreach ($pharmacies as $p) {
    $total_medicines += $p['total_medicines'] ?? 0;
    $total_prescriptions += $p['total_prescriptions'] ?? 0;
    $total_dispensed += $p['dispensed_prescriptions'] ?? 0;
    $total_pending += $p['pending_prescriptions'] ?? 0;
    $total_otc_sales += $p['total_otc_sales'] ?? 0;
    $total_otc_revenue += $p['otc_revenue'] ?? 0;
    $total_prescription_revenue_sum += $p['prescription_revenue'] ?? 0;
    $total_revenue_sum += ($p['prescription_revenue'] ?? 0) + ($p['otc_revenue'] ?? 0);
    $total_out_of_stock += $p['out_of_stock_items'] ?? 0;
    $total_low_stock += $p['low_stock_items'] ?? 0;
    $total_expired += $p['expired_medicines'] ?? 0;
    $total_expiring_soon += $p['expiring_soon_medicines'] ?? 0;
}

// ================================================================
// GET INVENTORY DATA FOR TABLE (Filtered by selected branch)
// ================================================================
$inventory_items = [];
try {
    $sql_inventory = "
        SELECT 
            mi.id,
            mi.medication_name,
            mi.category,
            mi.unit,
            mi.quantity,
            mi.reorder_level,
            mi.unit_cost,
            mi.selling_price,
            mi.supplier,
            mi.expiry_date,
            mi.batch_number,
            mi.branch_id,
            mi.status,
            b.name as branch_name
        FROM medications_inventory mi
        INNER JOIN branches b ON mi.branch_id = b.id
        WHERE mi.status = 'active'
    ";
    
    if ($selected_branch_id !== 'all' && is_numeric($selected_branch_id)) {
        $sql_inventory .= " AND mi.branch_id = " . (int)$selected_branch_id;
    }
    
    $sql_inventory .= " ORDER BY b.name, mi.medication_name ASC";
    
    $stmt = $db->query($sql_inventory);
    $inventory_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching inventory: " . $e->getMessage());
    $inventory_items = [];
}

// ================================================================
// GET BRANCH NAME FOR DISPLAY
// ================================================================
$display_branch_name = 'All Branches';
if ($selected_branch_id !== 'all' && is_numeric($selected_branch_id)) {
    foreach ($branches as $b) {
        if ($b['id'] == $selected_branch_id) {
            $display_branch_name = $b['name'];
            break;
        }
    }
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

function getInventoryStatusBadge($quantity, $reorder_level) {
    if ($quantity <= 0) {
        return '<span class="badge badge-danger" style="font-size:0.6rem;"><i class="fas fa-times-circle"></i> Out of Stock</span>';
    } elseif ($quantity <= $reorder_level) {
        return '<span class="badge badge-warning" style="font-size:0.6rem;"><i class="fas fa-exclamation-triangle"></i> Low Stock</span>';
    } else {
        return '<span class="badge badge-success" style="font-size:0.6rem;"><i class="fas fa-check-circle"></i> In Stock</span>';
    }
}

function getExpiryBadge($expiry_date) {
    if (empty($expiry_date)) {
        return '<span class="badge badge-secondary" style="font-size:0.6rem;">N/A</span>';
    }
    $today = new DateTime();
    $expiry = new DateTime($expiry_date);
    $diff = $today->diff($expiry)->days;
    
    if ($expiry < $today) {
        return '<span class="badge badge-danger" style="font-size:0.6rem;"><i class="fas fa-skull"></i> Expired</span>';
    } elseif ($diff <= 30) {
        return '<span class="badge badge-warning" style="font-size:0.6rem;"><i class="fas fa-clock"></i> ' . $diff . ' days</span>';
    } else {
        return '<span class="badge badge-success" style="font-size:0.6rem;"><i class="fas fa-check"></i> ' . $diff . ' days</span>';
    }
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
    <title>Braick Dispensary - Pharmacies</title>
    
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
            --table-hover: #F1F5F9;
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
            --table-hover: #1E293B;
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
            font-size: 1.6rem;
            font-weight: 800;
            color: white;
            margin: 0;
            line-height: 1.2;
            letter-spacing: -0.02em;
        }
        .stat-card-8 .stat-currency {
            font-size: 0.8rem;
            font-weight: 600;
            color: rgba(255,255,255,0.9);
            margin-right: 3px;
        }
        .stat-card-8 .stat-sub {
            font-size: 0.6rem;
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
            font-size: 0.5rem;
            font-weight: 600;
            padding: 1px 8px;
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
        .stat-card-8 .flex-row {
            display: flex;
            align-items: baseline;
            gap: 10px;
            flex-wrap: wrap;
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
        
        .pharmacy-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
            gap: 20px;
            margin-bottom: 24px;
        }
        .pharmacy-card {
            background: var(--bg-card);
            border-radius: 18px;
            border: 2px solid var(--border-color);
            overflow: hidden;
            transition: all 0.3s ease;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
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
            box-shadow: 0 20px 40px rgba(0,0,0,0.12);
        }
        .pharmacy-card:hover::before { opacity: 1; }
        
        .pharmacy-card-header {
            padding: 16px 20px;
            background: var(--primary-gradient-strong);
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
        
        .pharmacy-card-body { padding: 16px 20px; }
        .revenue-section {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 10px;
            margin-bottom: 14px;
            padding: 12px;
            background: var(--primary-bg);
            border-radius: 12px;
            border: 2px solid var(--border-color);
        }
        [data-theme="dark"] .revenue-section {
            background: #1E3A5F;
            border-color: #334155;
        }
        .revenue-item { text-align: center; }
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
        .revenue-item .revenue-amount.orange { color: #F59E0B; }
        
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
        [data-theme="dark"] .stats-inner-grid .stat-inner { background: #0F172A; }
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
        .stock-badge.danger { background: #FEE2E2; color: #DC2626; }
        .stock-badge.warning { background: #FEF3C7; color: #D97706; }
        .stock-badge.success { background: #D1FAE5; color: #059669; }
        .stock-badge.info { background: #EFF6FF; color: #0B5ED7; }
        [data-theme="dark"] .stock-badge.danger { background: #3A1A1A; color: #F87171; }
        [data-theme="dark"] .stock-badge.warning { background: #3D2E0A; color: #FBBF24; }
        [data-theme="dark"] .stock-badge.success { background: #1A3A2A; color: #34D399; }
        [data-theme="dark"] .stock-badge.info { background: #1E3A5F; color: #3B82F6; }
        
        .pharmacy-card-footer {
            padding: 12px 20px;
            background: var(--bg-body);
            border-top: 2px solid var(--border-color);
            display: flex;
            justify-content: flex-end;
            gap: 8px;
            flex-wrap: wrap;
        }
        [data-theme="dark"] .pharmacy-card-footer { background: #0F172A; }
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
            background: var(--primary-gradient-strong);
            color: white;
        }
        .pharmacy-card-footer .btn-sm-primary:hover {
            background: var(--primary-gradient-strong);
            transform: translateY(-2px);
        }
        .pharmacy-card-footer .btn-sm-outline {
            background: transparent;
            color: var(--text-secondary);
            border: 2px solid var(--border-color);
        }
        .pharmacy-card-footer .btn-sm-outline:hover {
            border-color: var(--primary);
            color: var(--primary);
        }
        
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 6px 0;
            border-bottom: 1px solid var(--border-color);
            font-size: 0.8rem;
        }
        .info-row:last-child { border-bottom: none; }
        .info-label {
            color: var(--text-secondary);
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .info-label i {
            color: var(--primary);
            width: 16px;
            font-size: 0.75rem;
        }
        .info-value {
            color: var(--text-primary);
            font-weight: 600;
        }
        
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
        .page-header-box .page-title .branch-name-display {
            background: rgba(255,255,255,0.15);
            padding: 2px 14px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 500;
            color: white;
        }
        /* GREEN INVENTORY BUTTON - TOP HEADER */
        .page-header-box .page-title .btn-inventory-top-green {
            background: #059669;
            color: white;
            border: none;
            padding: 6px 20px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 12px rgba(5, 150, 105, 0.35);
            margin-left: 4px;
        }
        .page-header-box .page-title .btn-inventory-top-green:hover {
            background: #047857;
            transform: translateY(-2px) scale(1.03);
            box-shadow: 0 6px 20px rgba(5, 150, 105, 0.5);
        }
        .page-header-box .page-title .btn-inventory-top-green i {
            font-size: 0.9rem;
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
        .page-header-box .header-badge.medicines {
            background: rgba(52, 211, 153, 0.2);
            border-color: rgba(52, 211, 153, 0.3);
            color: #6EE7B7;
        }
        .page-header-box .header-badge.revenue {
            background: rgba(251, 191, 36, 0.2);
            border-color: rgba(251, 191, 36, 0.3);
            color: #FBBF24;
        }
        .page-header-box .header-badge.rx {
            background: rgba(124, 58, 237, 0.2);
            border-color: rgba(124, 58, 237, 0.3);
            color: #A78BFA;
        }
        .page-header-box .header-badge.otc {
            background: rgba(251, 146, 60, 0.2);
            border-color: rgba(251, 146, 60, 0.3);
            color: #FDBA74;
        }
        
        /* Inventory Table Styles */
        .inventory-table-container {
            background: var(--bg-card);
            border-radius: 18px;
            border: 2px solid var(--border-color);
            overflow: hidden;
            margin-bottom: 24px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
        }
        .inventory-table-container .table-header {
            padding: 16px 20px;
            background: var(--primary-gradient-strong);
            border-bottom: 2px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 8px;
        }
        .inventory-table-container .table-header .table-title {
            font-size: 0.95rem;
            font-weight: 700;
            color: white;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .inventory-table-container .table-header .table-title i {
            color: rgba(255,255,255,0.8);
        }
        .inventory-table-container .table-header .table-badge {
            background: rgba(255,255,255,0.15);
            color: white;
            padding: 3px 14px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 500;
        }
        .inventory-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.78rem;
        }
        .inventory-table thead th {
            background: var(--bg-body);
            color: var(--text-secondary);
            font-weight: 700;
            padding: 10px 14px;
            font-size: 0.6rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            border-bottom: 2px solid var(--border-color);
            text-align: left;
            position: sticky;
            top: 0;
            z-index: 2;
        }
        [data-theme="dark"] .inventory-table thead th {
            background: #0F172A;
        }
        .inventory-table td {
            padding: 8px 14px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-primary);
            vertical-align: middle;
        }
        .inventory-table tbody tr:hover td {
            background: var(--table-hover);
        }
        .inventory-table tbody tr:last-child td {
            border-bottom: none;
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
            .top-nav { left: 0; }
            .main-content { margin-left: 0; padding: 16px; }
            .stats-grid-8 { grid-template-columns: repeat(4, 1fr); }
            .sidebar { transform: translateX(-100%); }
            .sidebar.open { transform: translateX(0); }
        }
        @media (max-width: 768px) {
            .stats-grid-8 { grid-template-columns: 1fr 1fr; }
            .pharmacy-grid { grid-template-columns: 1fr; }
            .page-header-box .page-title { font-size: 1.3rem; }
            .page-header-box { padding: 16px 18px; }
            .revenue-section { grid-template-columns: 1fr 1fr; }
            .stats-inner-grid { grid-template-columns: repeat(2, 1fr); }
            .stat-card-8 { padding: 14px 16px; min-height: 90px; }
            .stat-card-8 .stat-number-small { font-size: 1.8rem; }
            .stat-card-8 .stat-amount-large { font-size: 1.4rem; }
            .stat-card-8 .stat-icon { width: 38px; height: 38px; font-size: 1rem; }
            .inventory-table { font-size: 0.65rem; }
            .inventory-table thead th, .inventory-table td { padding: 6px 8px; }
        }
        @media (max-width: 480px) {
            .stats-grid-8 { grid-template-columns: 1fr; }
            .stat-card-8 { padding: 12px 14px; min-height: 80px; }
            .stat-card-8 .stat-number-small { font-size: 1.6rem; }
            .stat-card-8 .stat-amount-large { font-size: 1.2rem; }
            .stat-card-8 .stat-icon { width: 34px; height: 34px; font-size: 0.85rem; }
            .page-header-box .page-title { font-size: 1rem; flex-direction: column; align-items: flex-start; }
            .page-header-box .page-subtitle { font-size: 0.75rem; flex-direction: column; align-items: flex-start; gap: 4px; }
            .inventory-table { font-size: 0.55rem; }
            .inventory-table thead th, .inventory-table td { padding: 4px 6px; }
        }
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-fade-in-up {
            animation: fadeInUp 0.5s ease forwards;
            opacity: 0;
        }
        
        /* Scrollable table container */
        .table-scroll {
            overflow-x: auto;
            max-height: 500px;
            overflow-y: auto;
        }
        .text-danger { color: #DC2626; }
        .text-warning { color: #D97706; }
        .text-success { color: #059669; }
    </style>
</head>
<body>

<!-- ================================================================ -->
<!-- SIDEBAR OVERLAY -->
<!-- ================================================================ -->
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

    <!-- Page Header with GREEN Inventory Button -->
    <div class="page-header-box animate-fade-in-up">
        <div>
            <h1 class="page-title">
                <i class="fas fa-prescription-bottle"></i>
                Pharmacies
                <span class="role-badge-display">ADMIN</span>
                <span class="branch-name-display">
                    <i class="fas fa-store-alt"></i> <?= htmlspecialchars($display_branch_name) ?>
                </span>
                <!-- GREEN INVENTORY BUTTON - ONLY ONE ON TOP HEADER -->
                <a href="inventory.php?branch=<?= $selected_branch_id ?>" class="btn-inventory-top-green">
                    <i class="fas fa-boxes"></i> 📦 Inventory
                </a>
            </h1>
            <p class="page-subtitle">
                <strong><?= $total_pharmacies ?></strong> pharmacy branches
                <span class="header-badge medicines">
                    <i class="fas fa-pills"></i> <?= number_format($total_medicines) ?> Medicines
                </span>
                <span class="header-badge revenue">
                    <i class="fas fa-money-bill-wave"></i> TSh <?= number_format($total_revenue, 0) ?> Revenue
                </span>
                <span class="header-badge rx">
                    <i class="fas fa-prescription"></i> <?= number_format($total_prescriptions_count) ?> Rx
                </span>
                <span class="header-badge otc">
                    <i class="fas fa-shopping-cart"></i> <?= number_format($total_otc_sales_db) ?> OTC Sales
                </span>
            </p>
        </div>
    </div>

    <!-- 8 CARDS -->
    <div class="stats-grid-8 animate-fade-in-up" style="animation-delay:0.05s;">
        
        <!-- 1. Total Pharmacies - BLUE -->
        <div class="stat-card-8 card-blue">
            <div class="stat-icon"><i class="fas fa-store"></i></div>
            <div class="stat-content">
                <p class="stat-label">Total Pharmacies</p>
                <p class="stat-number-small"><?= number_format($total_pharmacies) ?></p>
                <p class="stat-sub">Active branches</p>
            </div>
            <i class="fas fa-arrow-right stat-arrow"></i>
        </div>
        
        <!-- 2. Total Medicines - BLUE -->
        <div class="stat-card-8 card-blue">
            <div class="stat-icon"><i class="fas fa-pills"></i></div>
            <div class="stat-content">
                <p class="stat-label">Total Medicines</p>
                <p class="stat-number-small"><?= number_format($total_medicines) ?></p>
                <p class="stat-sub">Active inventory items</p>
            </div>
            <i class="fas fa-arrow-right stat-arrow"></i>
        </div>
        
        <!-- 3. Total Revenue - GREEN (FIXED: Prescription + OTC) -->
        <div class="stat-card-8 card-green">
            <div class="stat-icon"><i class="fas fa-money-bill-wave"></i></div>
            <div class="stat-content">
                <p class="stat-label">Total Revenue</p>
                <p class="stat-amount-large"><span class="stat-currency">TSh</span> <?= number_format($total_revenue, 0) ?></p>
                <p class="stat-sub">
                    <span class="highlight">💊 Rx: TSh <?= number_format($total_prescription_revenue, 0) ?></span>
                    <span class="highlight">🛒 OTC: TSh <?= number_format($total_otc_revenue_db, 0) ?></span>
                </p>
            </div>
            <i class="fas fa-arrow-right stat-arrow"></i>
        </div>
        
        <!-- 4. Prescriptions - PURPLE -->
        <div class="stat-card-8" style="background:linear-gradient(135deg, #7C3AED, #6D28D9);">
            <div class="stat-icon"><i class="fas fa-prescription"></i></div>
            <div class="stat-content">
                <p class="stat-label">Prescriptions</p>
                <div class="flex-row">
                    <span class="stat-number-small"><?= number_format($total_prescriptions_count) ?></span>
                    <span class="stat-amount-large" style="font-size:1.4rem;">TSh <?= number_format($total_prescription_revenue, 0) ?></span>
                </div>
                <p class="stat-sub">
                    <span class="badge-mini success">✅ <?= number_format($total_dispensed_count) ?> dispensed</span>
                    <span class="badge-mini warning">⏳ <?= number_format($total_pending_count) ?> pending</span>
                    <span class="badge-mini" style="background:rgba(255,255,255,0.15);">Included in Total Revenue</span>
                </p>
            </div>
            <i class="fas fa-arrow-right stat-arrow"></i>
        </div>
        
        <!-- 5. OTC Sales - ORANGE -->
        <div class="stat-card-8 card-orange">
            <div class="stat-icon"><i class="fas fa-shopping-cart"></i></div>
            <div class="stat-content">
                <p class="stat-label">OTC Sales</p>
                <div class="flex-row">
                    <span class="stat-number-small"><?= number_format($total_otc_sales_db) ?></span>
                    <span class="stat-amount-large" style="font-size:1.4rem;">TSh <?= number_format($total_otc_revenue_db, 0) ?></span>
                </div>
                <p class="stat-sub">💰 Revenue from OTC sales</p>
            </div>
            <i class="fas fa-arrow-right stat-arrow"></i>
        </div>
        
        <!-- 6. Stock Alerts - ORANGE -->
        <div class="stat-card-8 card-orange">
            <div class="stat-icon"><i class="fas fa-exclamation-triangle"></i></div>
            <div class="stat-content">
                <p class="stat-label">Stock Alerts</p>
                <p class="stat-number-small"><?= number_format($total_out_of_stock + $total_low_stock) ?></p>
                <p class="stat-sub">
                    <span class="badge-mini danger">❌ <?= $total_out_of_stock ?> out of stock</span>
                    <span class="badge-mini warning">⚠️ <?= $total_low_stock ?> low stock</span>
                </p>
            </div>
            <i class="fas fa-arrow-right stat-arrow"></i>
        </div>
        
        <!-- 7. Expiry Alerts - RED -->
        <div class="stat-card-8 card-red">
            <div class="stat-icon"><i class="fas fa-calendar-times"></i></div>
            <div class="stat-content">
                <p class="stat-label">Expiry Alerts</p>
                <p class="stat-number-small"><?= number_format($total_expired + $total_expiring_soon) ?></p>
                <p class="stat-sub">
                    <span class="badge-mini danger">💀 <?= $total_expired ?> expired</span>
                    <span class="badge-mini warning">⏰ <?= $total_expiring_soon ?> expiring soon</span>
                </p>
            </div>
            <i class="fas fa-arrow-right stat-arrow"></i>
        </div>
        
        <!-- 8. Pending Prescriptions - ORANGE -->
        <div class="stat-card-8 card-orange">
            <div class="stat-icon"><i class="fas fa-clock"></i></div>
            <div class="stat-content">
                <p class="stat-label">Pending Prescriptions</p>
                <p class="stat-number-small"><?= number_format($total_pending_count) ?></p>
                <p class="stat-sub">Awaiting approval/dispensing</p>
            </div>
            <i class="fas fa-arrow-right stat-arrow"></i>
        </div>
        
    </div>

    <!-- FILTERS -->
    <div class="filter-bar animate-fade-in-up" style="animation-delay:0.1s;">
        <span class="filter-label"><i class="fas fa-filter"></i> Filter</span>
        <form method="GET" class="flex flex-wrap gap-3 items-center w-full">
            <select name="branch" onchange="this.form.submit()" class="flex-1 min-w-[150px]">
                <option value="all" <?= $selected_branch_id === 'all' ? 'selected' : '' ?>>🌐 All Branches</option>
                <?php foreach ($branches as $b): ?>
                    <option value="<?= $b['id'] ?>" <?= $selected_branch_id == $b['id'] ? 'selected' : '' ?>>
                        🏥 <?= htmlspecialchars($b['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <select name="status" onchange="this.form.submit()" class="flex-1 min-w-[150px]">
                <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>All Status</option>
                <option value="active" <?= $status_filter === 'active' ? 'selected' : '' ?>>Active</option>
                <option value="inactive" <?= $status_filter === 'inactive' ? 'selected' : '' ?>>Inactive</option>
            </select>
            <input type="text" name="search" placeholder="Search pharmacies..." value="<?= htmlspecialchars($search) ?>" class="flex-1 min-w-[200px]">
            <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Filter</button>
            <a href="pharmacies.php" class="btn btn-outline"><i class="fas fa-times"></i> Clear</a>
        </form>
    </div>

    <!-- PHARMACY GRID - NO INVENTORY BUTTONS ON CARDS -->
    <?php if (count($pharmacies) > 0): ?>
        <div class="pharmacy-grid animate-fade-in-up" style="animation-delay:0.15s;">
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
            ?>
                <div class="pharmacy-card">
                    <div class="pharmacy-card-header">
                        <span class="pharmacy-name"><i class="fas fa-store-alt"></i> <?= htmlspecialchars($pharmacy['name']) ?></span>
                        <span class="badge badge-<?= getStatusBadge($pharmacy['status'] ?? 'active') ?>" style="font-size:0.6rem;padding:2px 12px;">
                            <?= ucfirst($pharmacy['status'] ?? 'Active') ?>
                        </span>
                    </div>
                    
                    <div class="pharmacy-card-body">
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
                                <div class="stat-number teal"><?= number_format($prescription_sales) ?></div>
                                <div class="stat-label">Rx Sales</div>
                            </div>
                        </div>
                        
                        <div class="stock-badges">
                            <?php if ($out_of_stock > 0): ?>
                                <span class="stock-badge danger"><i class="fas fa-times-circle"></i> <?= $out_of_stock ?> Out of Stock</span>
                            <?php endif; ?>
                            <?php if ($low_stock > 0): ?>
                                <span class="stock-badge warning"><i class="fas fa-exclamation-triangle"></i> <?= $low_stock ?> Low Stock</span>
                            <?php endif; ?>
                            <?php if ($expired > 0): ?>
                                <span class="stock-badge danger"><i class="fas fa-skull"></i> <?= $expired ?> Expired</span>
                            <?php endif; ?>
                            <?php if ($expiring_soon > 0): ?>
                                <span class="stock-badge warning"><i class="fas fa-clock"></i> <?= $expiring_soon ?> Expiring Soon</span>
                            <?php endif; ?>
                            <?php if (!$has_alerts): ?>
                                <span class="stock-badge success"><i class="fas fa-check-circle"></i> All Clear ✅</span>
                            <?php endif; ?>
                            <span class="stock-badge info" style="margin-left:auto;">
                                <i class="fas fa-pills"></i> <?= number_format($total_medicines) ?> Items
                            </span>
                        </div>
                        
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
                    </div>
                    
                    <!-- FOOTER - NO INVENTORY BUTTON -->
                    <div class="pharmacy-card-footer">
                        <a href="view_pharmacy.php?id=<?= $pharmacy['id'] ?>&branch=<?= $selected_branch_id ?>" class="btn-sm btn-sm-primary">
                            <i class="fas fa-eye"></i> View
                        </a>
                        <a href="otc_sales.php?branch=<?= $pharmacy['id'] ?>" class="btn-sm btn-sm-outline">
                            <i class="fas fa-shopping-cart"></i> OTC
                        </a>
                        <a href="prescriptions.php?branch=<?= $pharmacy['id'] ?>" class="btn-sm btn-sm-outline">
                            <i class="fas fa-prescription"></i> Rx
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <div class="text-center text-sm text-gray-500 dark:text-gray-400 py-2">
            Showing <strong><?= count($pharmacies) ?></strong> pharmacy branch<?= count($pharmacies) > 1 ? 'es' : '' ?>
            <?php if ($total_prescriptions_count > 0): ?>
                · <span class="text-purple-500">💊 <?= number_format($total_prescriptions_count) ?> Rx</span>
                · <span class="text-green-500">💰 TSh <?= number_format($total_prescription_revenue, 0) ?></span>
            <?php endif; ?>
            <?php if ($total_otc_sales_db > 0): ?>
                · <span class="text-orange-500">🛒 <?= number_format($total_otc_sales_db) ?> OTC · TSh <?= number_format($total_otc_revenue_db, 0) ?></span>
            <?php endif; ?>
            · <span class="text-blue-500">📊 Total Revenue: TSh <?= number_format($total_revenue, 0) ?></span>
        </div>
        
    <?php else: ?>
        <div class="empty-state animate-fade-in-up">
            <i class="fas fa-prescription-bottle"></i>
            <h3>No Pharmacies Found</h3>
            <p class="text-gray-400"><?= !empty($search) ? 'No results match your search criteria.' : 'No pharmacy branches have been created yet.' ?></p>
            <?php if (!empty($search)): ?>
                <a href="pharmacies.php" class="btn btn-primary mt-4"><i class="fas fa-times"></i> Clear Filters</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- INVENTORY TABLE - Shows all medicines based on selected branch -->
    <!-- ================================================================ -->
    <div id="inventory-table" class="inventory-table-container animate-fade-in-up" style="animation-delay:0.25s;">
        <div class="table-header">
            <h3 class="table-title">
                <i class="fas fa-boxes"></i>
                Medicine Inventory
                <span class="table-badge">
                    <i class="fas fa-pills"></i> <?= count($inventory_items) ?> Medicines
                </span>
                <?php if ($selected_branch_id !== 'all' && is_numeric($selected_branch_id)): ?>
                    <span class="table-badge" style="background:rgba(255,255,255,0.1);">
                        <i class="fas fa-store-alt"></i> <?= htmlspecialchars($display_branch_name) ?>
                    </span>
                <?php else: ?>
                    <span class="table-badge" style="background:rgba(255,255,255,0.1);">
                        <i class="fas fa-globe"></i> All Branches
                    </span>
                <?php endif; ?>
            </h3>
            <div>
                <a href="inventory.php?branch=<?= $selected_branch_id ?>" class="btn-sm btn-sm-primary" style="display:inline-flex;align-items:center;gap:4px;padding:6px 14px;border-radius:6px;background:rgba(255,255,255,0.15);color:white;text-decoration:none;font-size:0.7rem;font-weight:600;">
                    <i class="fas fa-arrow-right"></i> View All
                </a>
            </div>
        </div>
        
        <?php if (count($inventory_items) > 0): ?>
            <div class="table-scroll">
                <table class="inventory-table" id="inventoryTable">
                    <thead>
                        <tr>
                            <th>Branch</th>
                            <th>Medication Name</th>
                            <th>Category</th>
                            <th>Unit</th>
                            <th>Quantity</th>
                            <th>Selling Price</th>
                            <th>Stock Status</th>
                            <th>Expiry</th>
                            <th>Batch #</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($inventory_items as $item): 
                            $quantity = (int)$item['quantity'];
                            $reorder_level = (int)$item['reorder_level'];
                            $selling_price = (float)($item['selling_price'] ?? $item['unit_cost'] ?? 0);
                            $unit = htmlspecialchars($item['unit'] ?? 'pcs');
                        ?>
                            <tr>
                                <td>
                                    <span class="font-semibold text-xs" style="color:var(--primary);">
                                        <i class="fas fa-store-alt"></i> <?= htmlspecialchars($item['branch_name'] ?? 'N/A') ?>
                                    </span>
                                </td>
                                <td class="font-medium"><?= htmlspecialchars($item['medication_name'] ?? 'N/A') ?></td>
                                <td>
                                    <span class="badge badge-info" style="font-size:0.55rem;padding:1px 8px;">
                                        <?= htmlspecialchars($item['category'] ?? 'N/A') ?>
                                    </span>
                                </td>
                                <td><?= $unit ?></td>
                                <td class="font-semibold <?= $quantity <= 0 ? 'text-danger' : ($quantity <= $reorder_level ? 'text-warning' : 'text-success') ?>">
                                    <?= number_format($quantity) ?>
                                </td>
                                <td>TSh <?= number_format($selling_price, 0) ?></td>
                                <td><?= getInventoryStatusBadge($quantity, $reorder_level) ?></td>
                                <td><?= getExpiryBadge($item['expiry_date'] ?? null) ?></td>
                                <td class="font-mono text-xs"><?= htmlspecialchars($item['batch_number'] ?? 'N/A') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="text-center py-8 text-gray-400">
                <i class="fas fa-boxes text-3xl block mb-3" style="color:var(--border-color);"></i>
                <p>No medicines found in inventory</p>
                <?php if ($selected_branch_id !== 'all'): ?>
                    <p class="text-xs">No inventory items for <?= htmlspecialchars($display_branch_name) ?></p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Pharmacies - <?= $total_pharmacies ?> branches
            <span class="text-gray-300 mx-2">|</span>
            Inventory - <?= count($inventory_items) ?> medicines
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

    function switchBranch(branchId) {
        var url = new URL(window.location.href);
        url.searchParams.set('branch', branchId);
        url.searchParams.delete('error');
        window.location.href = url.toString();
    }

    var searchBtn = document.getElementById('searchBtn');
    var searchInput = document.getElementById('searchInput');
    
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

    console.log('%c🏥 Braick Dispensary - Pharmacies', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c👤 Admin: <?= htmlspecialchars($user_full_name) ?>', 'font-size:13px; color:#059669;');
    console.log('%c🏪 Branch: <?= htmlspecialchars($display_branch_name) ?>', 'font-size:13px; color:#0B5ED7;');
    console.log('%c📊 Total Pharmacies: <?= $total_pharmacies ?>', 'font-size:13px; color:#0B5ED7;');
    console.log('%c💊 Total Medicines: <?= number_format($total_medicines) ?>', 'font-size:13px; color:#059669;');
    console.log('%c💰 Total Revenue: TSh <?= number_format($total_revenue, 0) ?> (Rx + OTC)', 'font-size:13px; color:#0B5ED7;');
    console.log('%c📋 Prescriptions: <?= number_format($total_prescriptions_count) ?> (TSh <?= number_format($total_prescription_revenue, 0) ?>)', 'font-size:13px; color:#7C3AED;');
    console.log('%c🛒 OTC Sales: <?= number_format($total_otc_sales_db) ?> (TSh <?= number_format($total_otc_revenue_db, 0) ?>)', 'font-size:13px; color:#D97706;');
    console.log('%c📦 Inventory Items: <?= count($inventory_items) ?>', 'font-size:13px; color:#8B5CF6;');
    console.log('%c✅ ONE GREEN Inventory button on top header only', 'font-size:13px; color:#34D399;');
    console.log('%c✅ Inventory buttons removed from branch cards', 'font-size:13px; color:#34D399;');
    console.log('%c✅ Total Revenue = Prescription Revenue + OTC Revenue (No double counting)', 'font-size:13px; color:#34D399;');
</script>

</body>
</html>