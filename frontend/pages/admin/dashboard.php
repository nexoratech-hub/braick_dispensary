<?php
// ================================================================
// FILE: frontend/pages/admin/dashboard.php
// SUPER ADMIN DASHBOARD - MODERN DESIGN
// 8 CARDS: Revenue, Expenses, Profit, Prescriptions, OTC, Stock, Expiry, Equipment
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

$db = Database::getInstance()->getConnection();

// ================================================================
// GET UNREAD NOTIFICATIONS COUNT
// ================================================================
$unread_notifications = 0;
try {
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$user_id]);
    $unread_notifications = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
} catch (Exception $e) {
    $unread_notifications = 0;
}

// ================================================================
// BRANCH SELECTION
// ================================================================
$selected_branch_id = $_GET['branch'] ?? 'all';
$branch_name_display = 'All Branches';

// ================================================================
// BRANCH NAME
// ================================================================
if ($selected_branch_id !== 'all' && is_numeric($selected_branch_id)) {
    $branch_id_param = (int)$selected_branch_id;
    $stmt = $db->prepare("SELECT name FROM branches WHERE id = ? AND status = 'active'");
    $stmt->execute([$branch_id_param]);
    $branch_data = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($branch_data) {
        $branch_name_display = $branch_data['name'];
    }
} else {
    $selected_branch_id = 'all';
}

// ================================================================
// BRANCH FILTERS
// ================================================================

// For bills table (b)
$branch_filter_b = "";
if ($selected_branch_id !== 'all') {
    $branch_filter_b = " AND b.branch_id = " . (int)$selected_branch_id;
}

// For prescriptions table (p)
$branch_filter_p = "";
if ($selected_branch_id !== 'all') {
    $branch_filter_p = " AND p.branch_id = " . (int)$selected_branch_id;
}

// For other tables (single table)
$branch_filter = "";
if ($selected_branch_id !== 'all') {
    $branch_filter = " AND branch_id = " . (int)$selected_branch_id;
}

$today = date('Y-m-d');

// ================================================================
// ✅ 1. PATIENT BILLS REVENUE - Uses paid_amount from bills
//    Only bills with visit_id NOT NULL (consultation bills)
//    Bills with status = 'paid'
//    Ignores lab test status (pending or paid) because patient has already paid
//    THIS INCLUDES: Consultation fees + Prescription items + Lab tests
// ================================================================
$stmt = $db->query("
    SELECT COALESCE(SUM(b.paid_amount), 0) as total 
    FROM bills b
    WHERE b.status = 'paid'
    AND b.patient_id IS NOT NULL
    AND b.visit_id IS NOT NULL
    $branch_filter_b
");
$patient_bills_revenue = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// ================================================================
// ✅ 2. OTC REVENUE - All paid OTC sales
// ================================================================
$stmt = $db->query("
    SELECT COALESCE(SUM(total_amount), 0) as total 
    FROM otc_sales 
    WHERE payment_status = 'paid'
    $branch_filter
");
$otc_revenue = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// ================================================================
// ✅ 3. PRESCRIPTION REVENUE - FOR DISPLAY ONLY (NOT ADDED TO TOTAL)
//    This is already included in patient_bills_revenue
//    We keep this for display purposes only
// ================================================================
$stmt = $db->query("
    SELECT COALESCE(SUM(pi.total_price), 0) as total 
    FROM prescription_items pi
    INNER JOIN prescriptions p ON pi.prescription_id = p.id
    WHERE p.status = 'dispensed'
    $branch_filter_p
");
$prescription_revenue = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// ================================================================
// ✅ 4. TOTAL REVENUE = Patient Bills (paid_amount) + OTC
//     PRESCRIPTIONS ARE ALREADY INCLUDED IN PATIENT BILLS
//     DO NOT ADD prescription_revenue HERE - IT CAUSES DOUBLE COUNTING!
// ================================================================
$total_revenue = $patient_bills_revenue + $otc_revenue;

// ================================================================
// ✅ 5. TOTAL EXPENSES
// ================================================================
$expenses_table_exists = false;
try {
    $stmt = $db->query("SHOW TABLES LIKE 'expenses'");
    if ($stmt->rowCount() > 0) {
        $expenses_table_exists = true;
    }
} catch (Exception $e) {
    $expenses_table_exists = false;
}

$total_expenses = 0;
if ($expenses_table_exists) {
    $stmt = $db->query("
        SELECT COALESCE(SUM(amount), 0) as total 
        FROM expenses 
        WHERE status = 'paid'
        $branch_filter
    ");
    $total_expenses = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
}

// ================================================================
// ✅ 6. NET PROFIT
// ================================================================
$net_profit = $total_revenue - $total_expenses;
$profit_percentage = ($total_revenue > 0) ? round(($net_profit / $total_revenue) * 100, 1) : 0;

// ================================================================
// ✅ 7. PRESCRIPTION COUNT
// ================================================================
$stmt = $db->query("
    SELECT COUNT(*) as count 
    FROM prescriptions p
    WHERE p.status = 'dispensed'
    $branch_filter_p
");
$prescription_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// ================================================================
// ✅ 8. OTC SALES DETAILS
// ================================================================
$stmt = $db->query("
    SELECT COUNT(*) as count, 
           COALESCE(SUM(total_amount), 0) as total
    FROM otc_sales 
    WHERE payment_status = 'paid'
    $branch_filter
");
$otc_data = $stmt->fetch(PDO::FETCH_ASSOC);
$otc_count = $otc_data['count'] ?? 0;
$otc_total = $otc_data['total'] ?? 0;

// ================================================================
// ✅ 9. MEDICATION STOCK
// ================================================================
$stmt = $db->query("
    SELECT 
        SUM(CASE WHEN quantity <= 0 THEN 1 ELSE 0 END) as out_of_stock,
        SUM(CASE WHEN quantity > 0 AND quantity <= reorder_level THEN 1 ELSE 0 END) as low_stock
    FROM medications_inventory 
    WHERE status = 'active' 
    $branch_filter
");
$stock_data = $stmt->fetch(PDO::FETCH_ASSOC);
$med_out_of_stock = $stock_data['out_of_stock'] ?? 0;
$med_low_stock = $stock_data['low_stock'] ?? 0;

// ================================================================
// ✅ 10. MEDICATION EXPIRY
// ================================================================
$today_date = date('Y-m-d');
$stmt = $db->query("
    SELECT 
        SUM(CASE WHEN expiry_date < '$today_date' AND expiry_date IS NOT NULL AND expiry_date != '0000-00-00' THEN 1 ELSE 0 END) as expired,
        SUM(CASE WHEN expiry_date BETWEEN '$today_date' AND DATE_ADD('$today_date', INTERVAL 30 DAY) AND expiry_date IS NOT NULL AND expiry_date != '0000-00-00' THEN 1 ELSE 0 END) as expiring_soon
    FROM medications_inventory 
    WHERE status = 'active' 
    $branch_filter
");
$expiry_data = $stmt->fetch(PDO::FETCH_ASSOC);
$med_expired = $expiry_data['expired'] ?? 0;
$med_expiring_soon = $expiry_data['expiring_soon'] ?? 0;

// ================================================================
// ✅ 11. MEDICAL EQUIPMENT - Shows ALL equipment
// ================================================================
$stmt = $db->query("
    SELECT 
        COUNT(*) as total_equipment,
        SUM(CASE WHEN quantity <= 0 THEN 1 ELSE 0 END) as out_of_stock,
        SUM(CASE WHEN quantity > 0 AND quantity <= reorder_level THEN 1 ELSE 0 END) as low_stock,
        SUM(CASE WHEN expiry_date < '$today_date' AND expiry_date IS NOT NULL AND expiry_date != '0000-00-00' THEN 1 ELSE 0 END) as expired,
        SUM(CASE WHEN expiry_date BETWEEN '$today_date' AND DATE_ADD('$today_date', INTERVAL 30 DAY) AND expiry_date IS NOT NULL AND expiry_date != '0000-00-00' THEN 1 ELSE 0 END) as expiring_soon
    FROM medical_equipment 
    WHERE status = 'active' 
    $branch_filter
");
$equipment_data = $stmt->fetch(PDO::FETCH_ASSOC);
$total_equipment = $equipment_data['total_equipment'] ?? 0;
$equip_out_of_stock = $equipment_data['out_of_stock'] ?? 0;
$equip_low_stock = $equipment_data['low_stock'] ?? 0;
$equip_expired = $equipment_data['expired'] ?? 0;
$equip_expiring_soon = $equipment_data['expiring_soon'] ?? 0;

// ================================================================
// CHART DATA - Last 7 Days Revenue (uses paid_amount)
// ================================================================
$chart_labels = [];
$chart_values = [];

for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $chart_labels[] = date('D', strtotime($date));
    
    $daily_total = 0;
    
    // Bills (patient bills with visit_id) - uses paid_amount
    // This already includes prescriptions
    $stmt = $db->prepare("
        SELECT COALESCE(SUM(b.paid_amount), 0) as total 
        FROM bills b
        WHERE DATE(b.created_at) = ? 
        AND b.status = 'paid'
        AND b.patient_id IS NOT NULL
        AND b.visit_id IS NOT NULL
        $branch_filter_b
    ");
    $stmt->execute([$date]);
    $daily_total += $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    // OTC Sales
    $stmt = $db->prepare("
        SELECT COALESCE(SUM(total_amount), 0) as total 
        FROM otc_sales 
        WHERE DATE(created_at) = ? 
        AND payment_status = 'paid'
        $branch_filter
    ");
    $stmt->execute([$date]);
    $daily_total += $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    // DO NOT add prescription sales here separately
    // They are already included in bills paid_amount above
    
    $chart_values[] = (float)$daily_total;
}

// ================================================================
// GET BRANCHES
// ================================================================
$branches = [];
$stmt = $db->query("SELECT id, name, location FROM branches WHERE status = 'active'");
$branches = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// GET RECENT ACTIVITIES
// ================================================================
$recent_activities = [];
try {
    $stmt = $db->query("SELECT * FROM activity_logs ORDER BY created_at DESC LIMIT 5");
    $recent_activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $recent_activities = [
        ['action' => 'System Started', 'details' => 'Super Admin logged in', 'created_at' => date('Y-m-d H:i:s')],
    ];
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
    <title>Super Admin Dashboard - Braick Dispensary</title>
    
    <link rel="icon" href="<?= $logo_url ?>" type="image/png">
    <link rel="shortcut icon" href="<?= $logo_url ?>" type="image/png">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    
    <style>
        /* ================================================================
           ROOT VARIABLES
           ================================================================ */
        :root {
            --primary: #0B5ED7;
            --primary-dark: #0A4CA8;
            --primary-light: #6EA8FE;
            --primary-bg: #E8F0FE;
            --bg-body: #F1F5F9;
            --bg-card: #FFFFFF;
            --bg-nav: #FFFFFF;
            --text-primary: #1E293B;
            --text-secondary: #64748B;
            --border-color: #E2E8F0;
            --shadow-sm: 0 1px 3px rgba(0,0,0,0.06);
            --shadow-md: 0 4px 12px rgba(0,0,0,0.08);
            --shadow-lg: 0 8px 25px rgba(0,0,0,0.12);
            --radius: 12px;
            --radius-lg: 16px;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        [data-theme="dark"] {
            --bg-body: #0F172A;
            --bg-card: #1E293B;
            --bg-nav: #1E293B;
            --text-primary: #F1F5F9;
            --text-secondary: #94A3B8;
            --border-color: #334155;
            --shadow-sm: 0 1px 3px rgba(0,0,0,0.3);
            --shadow-md: 0 4px 12px rgba(0,0,0,0.3);
            --shadow-lg: 0 8px 25px rgba(0,0,0,0.4);
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
           TOP NAV
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
            background: var(--primary);
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
            background: var(--primary-dark);
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
        
        .notif-dot.has-notif { background: #EF4444; }
        .notif-dot.no-notif { background: var(--text-secondary); animation: none; }
        
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
           PAGE HEADER
           ================================================================ */
        .page-header {
            background: var(--primary);
            border-radius: var(--radius-lg);
            padding: 24px 32px;
            margin-bottom: 24px;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            box-shadow: 0 4px 24px rgba(11, 94, 215, 0.2);
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
            background: rgba(255,255,255,0.04);
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
            background: rgba(255,255,255,0.02);
            border-radius: 50%;
            pointer-events: none;
        }
        
        .page-header .page-title {
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
        
        .page-header .page-title i {
            font-size: 1.8rem;
            opacity: 0.9;
        }
        
        .page-header .page-subtitle {
            color: rgba(255,255,255,0.85);
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
        }
        
        .page-header .page-subtitle strong {
            color: white;
            font-weight: 600;
        }
        
        .page-header .header-badge {
            background: rgba(255,255,255,0.1);
            color: white;
            padding: 3px 12px;
            border-radius: 20px;
            font-size: 0.65rem;
            font-weight: 500;
            backdrop-filter: blur(4px);
            display: inline-flex;
            align-items: center;
            gap: 4px;
            border: 1px solid rgba(255,255,255,0.1);
        }
        
        .page-header .btn-outline-light {
            background: rgba(255,255,255,0.1);
            color: white;
            border: 1px solid rgba(255,255,255,0.15);
            padding: 6px 16px;
            border-radius: var(--radius);
            font-weight: 500;
            font-size: 0.8rem;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            backdrop-filter: blur(4px);
            position: relative;
            z-index: 1;
        }
        
        .page-header .btn-outline-light:hover {
            background: rgba(255,255,255,0.2);
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(0,0,0,0.15);
        }
        
        /* ================================================================
           STAT CARDS - 8 CARDS - SOLID COLORS
           ================================================================ */
        .stat-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 24px;
        }
        
        .stat-card {
            position: relative;
            border-radius: var(--radius-lg);
            padding: 20px 22px;
            color: white;
            text-decoration: none;
            display: block;
            overflow: hidden;
            transition: var(--transition);
            box-shadow: var(--shadow-sm);
            min-height: 120px;
            height: 100%;
            cursor: pointer;
            border: none;
        }
        
        /* SOLID COLORS */
        .stat-card.card-revenue { background: #0B5ED7; }
        .stat-card.card-revenue:hover { background: #0A4CA8; transform: translateY(-6px); box-shadow: 0 8px 35px rgba(11,94,215,0.35); }
        
        .stat-card.card-expenses { background: #E11D48; }
        .stat-card.card-expenses:hover { background: #BE123C; transform: translateY(-6px); box-shadow: 0 8px 35px rgba(225,29,72,0.35); }
        
        .stat-card.card-profit { background: #059669; }
        .stat-card.card-profit:hover { background: #047857; transform: translateY(-6px); box-shadow: 0 8px 35px rgba(5,150,105,0.35); }
        
        .stat-card.card-prescription { background: #7C3AED; }
        .stat-card.card-prescription:hover { background: #6D28D9; transform: translateY(-6px); box-shadow: 0 8px 35px rgba(124,58,237,0.35); }
        
        .stat-card.card-otc { background: #D97706; }
        .stat-card.card-otc:hover { background: #B45309; transform: translateY(-6px); box-shadow: 0 8px 35px rgba(217,119,6,0.35); }
        
        .stat-card.card-stock { background: #0891B2; }
        .stat-card.card-stock:hover { background: #0E7490; transform: translateY(-6px); box-shadow: 0 8px 35px rgba(8,145,178,0.35); }
        
        .stat-card.card-expiry { background: #DC2626; }
        .stat-card.card-expiry:hover { background: #B91C1C; transform: translateY(-6px); box-shadow: 0 8px 35px rgba(220,38,38,0.35); }
        
        .stat-card.card-equipment { background: #4F46E5; }
        .stat-card.card-equipment:hover { background: #4338CA; transform: translateY(-6px); box-shadow: 0 8px 35px rgba(79,70,229,0.35); }
        
        /* Subtle decorative circles */
        .stat-card::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -30%;
            width: 200px;
            height: 200px;
            border-radius: 50%;
            background: rgba(255,255,255,0.04);
            pointer-events: none;
            transition: var(--transition);
        }
        
        .stat-card::after {
            content: '';
            position: absolute;
            bottom: -40%;
            left: -20%;
            width: 150px;
            height: 150px;
            border-radius: 50%;
            background: rgba(255,255,255,0.02);
            pointer-events: none;
            transition: var(--transition);
        }
        
        .stat-card:hover::before { transform: scale(1.2); right: -20%; }
        .stat-card:hover::after { transform: scale(1.3); bottom: -30%; }
        .stat-card:active { transform: scale(0.97); }
        
        .stat-card .card-content {
            position: relative;
            z-index: 1;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            height: 100%;
        }
        
        .stat-card .card-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }
        
        .stat-card .stat-icon {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            background: rgba(255,255,255,0.12);
            color: white;
            flex-shrink: 0;
            backdrop-filter: blur(4px);
            transition: var(--transition);
        }
        
        .stat-card:hover .stat-icon {
            transform: scale(1.1) rotate(-5deg);
            background: rgba(255,255,255,0.2);
        }
        
        .stat-card .stat-number {
            font-size: 1.7rem;
            font-weight: 700;
            color: white;
            line-height: 1.2;
            margin-top: 2px;
            letter-spacing: -0.02em;
        }
        
        .stat-card .stat-label {
            font-size: 0.7rem;
            color: rgba(255,255,255,0.9);
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        
        .stat-card .stat-sub {
            font-size: 0.65rem;
            color: rgba(255,255,255,0.8);
            margin-top: 2px;
        }
        
        .stat-card .stat-trend {
            font-size: 0.55rem;
            font-weight: 500;
            padding: 2px 10px;
            border-radius: 20px;
            background: rgba(255,255,255,0.1);
            color: white;
            display: inline-block;
            margin-top: 4px;
            backdrop-filter: blur(4px);
        }
        
        .stat-card .stat-badge-row {
            display: flex;
            gap: 4px;
            margin-top: 4px;
            flex-wrap: wrap;
        }
        
        .stat-card .stat-badge {
            font-size: 0.55rem;
            font-weight: 600;
            padding: 2px 10px;
            border-radius: 20px;
            background: rgba(255,255,255,0.1);
            color: white;
            backdrop-filter: blur(4px);
            display: inline-flex;
            align-items: center;
            gap: 3px;
        }
        
        .stat-card .stat-badge.danger {
            background: rgba(239, 68, 68, 0.2);
            color: #FCA5A5;
        }
        
        .stat-card .stat-badge.warning {
            background: rgba(245, 158, 11, 0.2);
            color: #FCD34D;
        }
        
        .stat-card .stat-badge.success {
            background: rgba(52, 211, 153, 0.2);
            color: #6EE7B7;
        }
        
        .stat-card .stat-arrow {
            position: absolute;
            right: 16px;
            bottom: 16px;
            color: rgba(255,255,255,0.2);
            font-size: 0.75rem;
            transition: var(--transition);
            z-index: 1;
        }
        
        .stat-card:hover .stat-arrow {
            transform: translateX(4px);
            color: rgba(255,255,255,0.6);
        }
        
        /* ================================================================
           CARD
           ================================================================ */
        .card {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            border: 1px solid var(--border-color);
            overflow: hidden;
            transition: var(--transition);
            box-shadow: var(--shadow-sm);
        }
        
        .card:hover { box-shadow: var(--shadow-md); }
        
        .card-header {
            padding: 10px 16px;
            background: var(--bg-body);
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 4px;
        }
        
        [data-theme="dark"] .card-header { background: #0F172A; }
        
        .card-title {
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--text-primary);
            margin: 0;
            display: flex;
            align-items: center;
        }
        
        .card-title i { margin-right: 6px; }
        .title-blue { color: #0B5ED7; }
        .title-green { color: #059669; }
        
        .data-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            font-size: 0.75rem;
        }
        
        .data-table thead th {
            background: var(--primary);
            color: white;
            font-weight: 600;
            padding: 6px 10px;
            font-size: 0.6rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            border-bottom: none;
            white-space: nowrap;
        }
        
        .data-table thead th:first-child { border-radius: 6px 0 0 0; }
        .data-table thead th:last-child { border-radius: 0 6px 0 0; }
        
        .data-table td {
            padding: 5px 10px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-primary);
            vertical-align: middle;
        }
        
        .data-table tbody tr:last-child td { border-bottom: none; }
        
        .max-h-50 { max-height: 160px; overflow-y: auto; }
        .overflow-x-auto { overflow-x: auto; }
        
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 5px 12px;
            border-radius: 6px;
            font-weight: 600;
            font-size: 0.7rem;
            transition: var(--transition);
            cursor: pointer;
            border: none;
            text-decoration: none;
        }
        
        .btn-sm {
            padding: 3px 10px;
            font-size: 0.65rem;
            border-radius: 4px;
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
        
        .footer {
            margin-top: 16px;
            padding: 10px 0;
            border-top: 1px solid var(--border-color);
            text-align: center;
            font-size: 0.7rem;
            color: var(--text-secondary);
        }
        
        .footer .footer-brand {
            color: var(--primary);
            font-weight: 600;
        }
        
        /* Utilities */
        .mb-4 { margin-bottom: 16px; }
        .py-2 { padding-top: 8px; padding-bottom: 8px; }
        .px-1 { padding-left: 4px; padding-right: 4px; }
        .pb-2 { padding-bottom: 8px; }
        .text-sm { font-size: 0.85rem; }
        .text-xs { font-size: 0.7rem; }
        .text-gray-400 { color: var(--text-secondary); }
        .text-blue-600 { color: #0B5ED7; }
        .font-medium { font-weight: 500; }
        .font-normal { font-weight: 400; }
        .hover\:underline:hover { text-decoration: underline; }
        .space-y-1 > * + * { margin-top: 4px; }
        .gap-2 { gap: 8px; }
        .gap-4 { gap: 16px; }
        .gap-1\.5 { gap: 6px; }
        .flex { display: flex; }
        .flex-wrap { flex-wrap: wrap; }
        .items-center { align-items: center; }
        .justify-between { justify-content: space-between; }
        .flex-1 { flex: 1; }
        .grid { display: grid; }
        .grid-cols-1 { grid-template-columns: 1fr; }
        .lg\:grid-cols-2 { grid-template-columns: 1fr 1fr; }
        .p-1\.5 { padding: 6px; }
        .w-6 { width: 24px; }
        .h-6 { height: 24px; }
        .rounded-full { border-radius: 50%; }
        .rounded-lg { border-radius: 8px; }
        .mt-0\.5 { margin-top: 2px; }
        .text-\[5px\] { font-size: 5px; }
        .text-\[10px\] { font-size: 10px; }
        .text-\[9px\] { font-size: 9px; }
        .bg-blue-600 { background: #0B5ED7; }
        .bg-blue-50 { background: #E8F0FE; }
        .dark\:bg-blue-900\/20 { background: rgba(30, 58, 95, 0.2); }
        .text-gray-800 { color: #1E293B; }
        .text-gray-500 { color: #64748B; }
        .dark\:text-gray-200 { color: #E2E8F0; }
        .dark\:text-gray-400 { color: #94A3B8; }
        .dark\:text-gray-500 { color: #64748B; }
        .transition { transition: var(--transition); }
        .hover\:bg-blue-50:hover { background: #E8F0FE; }
        .dark\:hover\:bg-blue-900\/20:hover { background: rgba(30, 58, 95, 0.2); }
        .mt-1 { margin-top: 4px; }
        .ml-2 { margin-left: 8px; }
        .mx-2 { margin-left: 8px; margin-right: 8px; }
        .mr-1 { margin-right: 4px; }
        .text-left { text-align: left; }
        .text-center { text-align: center; }
        .font-mono { font-family: monospace; }
        .block { display: block; }
        
        /* ================================================================
           RESPONSIVE
           ================================================================ */
        @media (max-width: 1200px) {
            .stat-grid { grid-template-columns: repeat(4, 1fr); gap: 12px; }
            .stat-card .stat-number { font-size: 1.4rem; }
        }
        
        @media (max-width: 992px) {
            .stat-grid { grid-template-columns: repeat(2, 1fr); gap: 12px; }
            .stat-card { min-height: 110px; padding: 16px 18px; }
            .stat-card .stat-number { font-size: 1.3rem; }
            .stat-card .stat-icon { width: 38px; height: 38px; font-size: 1rem; }
            .main-content { margin-left: 0; padding: 16px; }
            .top-nav { left: 0; }
            .top-nav .search-wrapper { max-width: 300px; }
        }
        
        @media (max-width: 768px) {
            .stat-grid { grid-template-columns: 1fr 1fr; gap: 10px; }
            .stat-card { min-height: 95px; padding: 14px 16px; }
            .stat-card .stat-number { font-size: 1.1rem; }
            .stat-card .stat-label { font-size: 0.6rem; }
            .stat-card .stat-sub { font-size: 0.55rem; }
            .stat-card .stat-icon { width: 32px; height: 32px; font-size: 0.85rem; }
            .stat-card .stat-arrow { display: none; }
            .stat-card .stat-badge { font-size: 0.5rem; padding: 1px 8px; }
            .page-header { padding: 14px 18px; }
            .page-header .page-title { font-size: 1.1rem; }
            .page-header .page-subtitle { font-size: 0.7rem; }
            .top-nav .search-wrapper { max-width: 180px; }
            .top-nav .datetime { display: none; }
            .lg\:grid-cols-2 { grid-template-columns: 1fr; }
        }
        
        @media (max-width: 480px) {
            .stat-grid { grid-template-columns: 1fr 1fr; gap: 8px; }
            .stat-card { min-height: 80px; padding: 10px 12px; border-radius: 12px; }
            .stat-card .stat-number { font-size: 0.95rem; }
            .stat-card .stat-label { font-size: 0.5rem; }
            .stat-card .stat-sub { font-size: 0.45rem; }
            .stat-card .stat-icon { width: 26px; height: 26px; font-size: 0.7rem; }
            .main-content { padding: 10px; }
            .page-header { padding: 10px 14px; flex-direction: column; align-items: flex-start !important; }
            .page-header .page-title { font-size: 0.95rem; }
            .page-header .page-subtitle { font-size: 0.6rem; }
        }
        
        @media print {
            .top-nav, .sidebar, .btn, .dark-toggle-btn, .icon-btn,
            .search-wrapper, .page-header .btn-outline-light,
            .footer, #sidebarToggle { display: none !important; }
            
            .main-content { margin: 0; padding: 20px; }
            
            .stat-card {
                border: 1px solid #ddd !important;
                box-shadow: none !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            .stat-card.card-revenue { background: #0B5ED7 !important; }
            .stat-card.card-expenses { background: #E11D48 !important; }
            .stat-card.card-profit { background: #059669 !important; }
            .stat-card.card-prescription { background: #7C3AED !important; }
            .stat-card.card-otc { background: #D97706 !important; }
            .stat-card.card-stock { background: #0891B2 !important; }
            .stat-card.card-expiry { background: #DC2626 !important; }
            .stat-card.card-equipment { background: #4F46E5 !important; }
            
            .stat-card .stat-number,
            .stat-card .stat-label,
            .stat-card .stat-sub { color: white !important; }
            .stat-card .stat-icon { background: rgba(255,255,255,0.2) !important; }
            
            .page-header {
                background: #0B5ED7 !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            .page-title, .page-subtitle { color: white !important; }
            
            .card { border: 1px solid #ddd !important; box-shadow: none !important; }
            .data-table thead th {
                background: #0B5ED7 !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
                color: white !important;
            }
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
            <input type="text" id="searchInput" placeholder="Search patients, doctors, medicines...">
            <button id="searchBtn" class="search-btn">
                <i class="fas fa-search mr-1"></i> Search
            </button>
        </div>
    </div>
    
    <div class="flex items-center gap-3">
        <select id="branchSelector" class="branch-selector" onchange="switchBranch(this.value)">
            <option value="all" <?= $selected_branch_id === 'all' ? 'selected' : '' ?>>🌐 All Branches</option>
            <?php foreach ($branches as $branch): ?>
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
                <i class="fas fa-home"></i> Super Admin Dashboard
            </h1>
            <p class="page-subtitle">
                Welcome back, <strong><?= htmlspecialchars($user_full_name) ?></strong>!
                <span class="header-badge"><i class="fas fa-store-alt"></i> <?= htmlspecialchars($user_branch_name) ?></span>
                <span class="header-badge"><i class="fas fa-calendar-day"></i> <?= date('F d, Y') ?></span>
            </p>
        </div>
        <div class="flex gap-2 flex-wrap">
            <a href="reports.php?branch=<?= $selected_branch_id ?>" class="btn-outline-light">
                <i class="fas fa-file-export"></i> Report
            </a>
            <button onclick="location.reload()" class="btn-outline-light">
                <i class="fas fa-sync-alt"></i> Refresh
            </button>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- 8 CARDS - SOLID COLORS WITH CORRECT NAVIGATION LINKS -->
    <!-- ================================================================ -->
    <div class="stat-grid">
        
        <!-- 1. TOTAL REVENUE -> revenue.php -->
        <a href="revenue.php?branch=<?= $selected_branch_id ?>" class="stat-card card-revenue">
            <div class="card-content">
                <div class="card-top">
                    <div>
                        <p class="stat-label">Total Revenue</p>
                        <p class="stat-number">TSh <?= number_format($total_revenue) ?></p>
                        <p class="stat-sub">Bills + OTC (Prescriptions included in Bills)</p>
                    </div>
                    <div class="stat-icon"><i class="fas fa-money-bill-wave"></i></div>
                </div>
                <div class="stat-trend"><i class="fas fa-arrow-up"></i> Paid amounts only</div>
            </div>
            <i class="fas fa-arrow-right stat-arrow"></i>
        </a>
        
        <!-- 2. TOTAL EXPENSES -> expenses.php -->
        <a href="expenses.php?branch=<?= $selected_branch_id ?>" class="stat-card card-expenses">
            <div class="card-content">
                <div class="card-top">
                    <div>
                        <p class="stat-label">Total Expenses</p>
                        <p class="stat-number">TSh <?= number_format($total_expenses) ?></p>
                        <p class="stat-sub">Paid expenses</p>
                    </div>
                    <div class="stat-icon"><i class="fas fa-coins"></i></div>
                </div>
                <div class="stat-trend"><i class="fas fa-arrow-down"></i> All time</div>
            </div>
            <i class="fas fa-arrow-right stat-arrow"></i>
        </a>
        
        <!-- 3. NET PROFIT -> profit.php -->
        <a href="profit.php?branch=<?= $selected_branch_id ?>" class="stat-card card-profit">
            <div class="card-content">
                <div class="card-top">
                    <div>
                        <p class="stat-label"><?= $net_profit >= 0 ? '💰 Net Profit' : '📉 Net Loss' ?></p>
                        <p class="stat-number">TSh <?= number_format(abs($net_profit)) ?></p>
                        <p class="stat-sub">
                            <?php if ($total_revenue > 0): ?>
                                <?= $profit_percentage ?>% margin
                            <?php else: ?>
                                No revenue yet
                            <?php endif; ?>
                        </p>
                    </div>
                    <div class="stat-icon"><i class="fas <?= $net_profit >= 0 ? 'fa-chart-line' : 'fa-exclamation-triangle' ?>"></i></div>
                </div>
                <div class="stat-trend">
                    <?php if ($net_profit >= 0): ?>
                        <i class="fas fa-arrow-up"></i> Revenue - Expenses
                    <?php else: ?>
                        <i class="fas fa-arrow-down"></i> Expenses exceed revenue
                    <?php endif; ?>
                </div>
            </div>
            <i class="fas fa-arrow-right stat-arrow"></i>
        </a>
        
        <!-- 4. PRESCRIPTION SALES -> prescriptions.php -->
        <a href="prescriptions.php?branch=<?= $selected_branch_id ?>" class="stat-card card-prescription">
            <div class="card-content">
                <div class="card-top">
                    <div>
                        <p class="stat-label">Prescription Sales</p>
                        <p class="stat-number">TSh <?= number_format($prescription_revenue) ?></p>
                        <p class="stat-sub"><?= $prescription_count ?> prescriptions</p>
                        <p class="stat-sub" style="font-size: 0.5rem; opacity: 0.7;">* Included in Bills Revenue</p>
                    </div>
                    <div class="stat-icon"><i class="fas fa-prescription"></i></div>
                </div>
                <div class="stat-trend"><i class="fas fa-pills"></i> Dispensed (Display Only)</div>
            </div>
            <i class="fas fa-arrow-right stat-arrow"></i>
        </a>
        
        <!-- 5. OTC SALES -> otc_sales.php -->
        <a href="../admin/otc_sales.php?branch=<?= $selected_branch_id ?>" class="stat-card card-otc">
            <div class="card-content">
                <div class="card-top">
                    <div>
                        <p class="stat-label">OTC Sales</p>
                        <p class="stat-number">TSh <?= number_format($otc_total) ?></p>
                        <p class="stat-sub"><?= $otc_count ?> transactions</p>
                    </div>
                    <div class="stat-icon"><i class="fas fa-cash-register"></i></div>
                </div>
                <div class="stat-trend"><i class="fas fa-store"></i> Over the counter</div>
            </div>
            <i class="fas fa-arrow-right stat-arrow"></i>
        </a>
        
        <!-- 6. MEDICATION STOCK -> inventory.php -->
        <a href="inventory.php?branch=<?= $selected_branch_id ?>" class="stat-card card-stock">
            <div class="card-content">
                <div class="card-top">
                    <div>
                        <p class="stat-label">Medication Stock</p>
                        <p class="stat-number">
                            <?php 
                                $total_med_stock_issues = $med_out_of_stock + $med_low_stock;
                                echo number_format($total_med_stock_issues);
                            ?>
                        </p>
                        <div class="stat-badge-row">
                            <span class="stat-badge danger"><i class="fas fa-times-circle"></i> <?= $med_out_of_stock ?> Out</span>
                            <span class="stat-badge warning"><i class="fas fa-exclamation-triangle"></i> <?= $med_low_stock ?> Low</span>
                        </div>
                    </div>
                    <div class="stat-icon"><i class="fas fa-pills"></i></div>
                </div>
                <div class="stat-trend"><i class="fas fa-warehouse"></i> Needs attention</div>
            </div>
            <i class="fas fa-arrow-right stat-arrow"></i>
        </a>
        
        <!-- 7. MEDICATION EXPIRY -> inventory.php?filter=expired -->
        <a href="inventory.php?filter=expired&branch=<?= $selected_branch_id ?>" class="stat-card card-expiry">
            <div class="card-content">
                <div class="card-top">
                    <div>
                        <p class="stat-label">Medication Expiry</p>
                        <p class="stat-number">
                            <?php 
                                $total_med_expiry_issues = $med_expired + $med_expiring_soon;
                                echo number_format($total_med_expiry_issues);
                            ?>
                        </p>
                        <div class="stat-badge-row">
                            <span class="stat-badge danger"><i class="fas fa-skull"></i> <?= $med_expired ?> Expired</span>
                            <span class="stat-badge warning"><i class="fas fa-clock"></i> <?= $med_expiring_soon ?> Soon</span>
                        </div>
                    </div>
                    <div class="stat-icon"><i class="fas fa-calendar-times"></i></div>
                </div>
                <div class="stat-trend"><i class="fas fa-clock"></i> Needs disposal</div>
            </div>
            <i class="fas fa-arrow-right stat-arrow"></i>
        </a>
        
        <!-- 8. MEDICAL EQUIPMENT -> equipment_inventory.php -->
        <a href="equipment_inventory.php?branch=<?= $selected_branch_id ?>" class="stat-card card-equipment">
            <div class="card-content">
                <div class="card-top">
                    <div>
                        <p class="stat-label">Medical Equipment</p>
                        <p class="stat-number">
                            <?= number_format($total_equipment) ?>
                        </p>
                        <div class="stat-badge-row" style="flex-wrap: wrap; gap: 3px;">
                            <span class="stat-badge" style="background: rgba(255,255,255,0.15);"><i class="fas fa-boxes"></i> Total: <?= $total_equipment ?></span>
                            <span class="stat-badge danger"><i class="fas fa-times-circle"></i> <?= $equip_out_of_stock ?> Out</span>
                            <span class="stat-badge warning"><i class="fas fa-exclamation-triangle"></i> <?= $equip_low_stock ?> Low</span>
                            <span class="stat-badge danger"><i class="fas fa-calendar-times"></i> <?= $equip_expired ?> Expired</span>
                            <span class="stat-badge warning"><i class="fas fa-clock"></i> <?= $equip_expiring_soon ?> Soon</span>
                        </div>
                        <p class="stat-sub" style="margin-top: 3px;">All equipment inventory</p>
                    </div>
                    <div class="stat-icon"><i class="fas fa-microscope"></i></div>
                </div>
                <div class="stat-trend"><i class="fas fa-tools"></i> <?= $total_equipment ?> items total</div>
            </div>
            <i class="fas fa-arrow-right stat-arrow"></i>
        </a>
        
    </div>

    <!-- ================================================================ -->
    <!-- CHART - Revenue -->
    <!-- ================================================================ -->
    <div class="card mb-4">
        <div class="card-header py-2">
            <h3 class="card-title text-sm">
                <i class="fas fa-chart-line title-blue mr-2"></i> Revenue Overview (Last 7 Days)
                <span class="text-xs text-gray-400 font-normal">TSh <?= number_format(array_sum($chart_values)) ?> total</span>
            </h3>
        </div>
        <div style="height: 150px; padding: 8px 12px;">
            <canvas id="revenueChart"></canvas>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- RECENT ACTIVITIES -->
    <!-- ================================================================ -->
    <div class="grid grid-cols-1 gap-4 mb-4">
        
        <div class="card">
            <div class="card-header py-2">
                <h3 class="card-title text-sm">
                    <i class="fas fa-clock title-green mr-2"></i> Recent Activities
                </h3>
                <a href="system_logs.php" class="text-xs text-blue-600 font-medium hover:underline">View All →</a>
            </div>
            <div class="space-y-1 max-h-50 overflow-y-auto px-2">
                <?php foreach ($recent_activities as $activity): ?>
                    <div class="flex items-start gap-2 p-1.5 rounded-lg hover:bg-blue-50 dark:hover:bg-blue-900/20 transition">
                        <div class="w-6 h-6 rounded-full bg-blue-600 flex items-center justify-center flex-shrink-0 mt-0.5 text-white text-xs">
                            <i class="fas fa-circle text-[5px]"></i>
                        </div>
                        <div>
                            <p class="font-medium text-xs text-gray-800 dark:text-gray-200"><?= htmlspecialchars($activity['action'] ?? 'Action') ?></p>
                            <p class="text-[10px] text-gray-500 dark:text-gray-400"><?= htmlspecialchars($activity['details'] ?? '') ?></p>
                            <p class="text-[9px] text-gray-400 dark:text-gray-500 mt-0.5">
                                <?= isset($activity['created_at']) ? time_ago($activity['created_at']) : 'Just now' ?>
                            </p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        
    </div>

    <!-- ================================================================ -->
    <!-- QUICK REPORTS -->
    <!-- ================================================================ -->
    <div class="card">
        <div class="card-header py-2">
            <h3 class="card-title text-sm">
                <i class="fas fa-file-alt title-blue mr-2"></i> Quick Reports
            </h3>
        </div>
        <div class="flex flex-wrap gap-1.5 px-1 pb-2">
            <a href="reports.php?type=daily&branch=<?= $selected_branch_id ?>" class="btn btn-outline btn-sm text-xs">Daily</a>
            <a href="reports.php?type=weekly&branch=<?= $selected_branch_id ?>" class="btn btn-outline btn-sm text-xs">Weekly</a>
            <a href="reports.php?type=monthly&branch=<?= $selected_branch_id ?>" class="btn btn-outline btn-sm text-xs">Monthly</a>
            <a href="reports.php?type=revenue&branch=<?= $selected_branch_id ?>" class="btn btn-outline btn-sm text-xs">Revenue</a>
            <a href="reports.php?type=profit&branch=<?= $selected_branch_id ?>" class="btn btn-outline btn-sm text-xs">Profit/Loss</a>
            <a href="reports.php?type=medicine&branch=<?= $selected_branch_id ?>" class="btn btn-outline btn-sm text-xs">Medicine</a>
            <a href="reports.php?type=laboratory&branch=<?= $selected_branch_id ?>" class="btn btn-outline btn-sm text-xs">Laboratory</a>
            <a href="reports.php?type=expenses&branch=<?= $selected_branch_id ?>" class="btn btn-outline btn-sm text-xs">Expenses</a>
            <a href="equipment_inventory.php?branch=<?= $selected_branch_id ?>" class="btn btn-outline btn-sm text-xs">Equipment</a>
            <div class="flex-1"></div>
            <button onclick="window.print()" class="btn btn-outline btn-sm text-xs">
                <i class="fas fa-print"></i> Print
            </button>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="mx-2">|</span>
            Super Admin Dashboard
            <span class="mx-2">|</span>
            <span id="footerTime"><?= date('H:i:s') ?></span>
            <span class="mx-2">|</span>
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

    // ================================================================
    // REVENUE CHART
    // ================================================================
    document.addEventListener('DOMContentLoaded', function() {
        var ctx = document.getElementById('revenueChart')?.getContext('2d');
        if (ctx) {
            if (typeof Chart !== 'undefined') {
                var labels = <?= json_encode($chart_labels) ?>;
                var values = <?= json_encode($chart_values) ?>;
                
                new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: 'Revenue (TSh)',
                            data: values,
                            borderColor: '#0B5ED7',
                            backgroundColor: 'rgba(11, 94, 215, 0.08)',
                            fill: true,
                            tension: 0.4,
                            pointBackgroundColor: '#0B5ED7',
                            pointBorderColor: '#ffffff',
                            pointBorderWidth: 1.5,
                            pointRadius: 4,
                            pointHoverRadius: 6,
                            borderWidth: 2
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { 
                                display: true,
                                labels: {
                                    font: { size: 9, weight: '600' },
                                    boxWidth: 10,
                                    padding: 8,
                                    color: '#64748B'
                                }
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        return 'TSh ' + context.raw.toLocaleString();
                                    }
                                }
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    callback: function(value) {
                                        return 'TSh ' + value.toLocaleString();
                                    },
                                    font: { size: 8 }
                                },
                                grid: { color: 'rgba(0,0,0,0.05)' }
                            },
                            x: { 
                                grid: { display: false },
                                ticks: { font: { size: 8 } }
                            }
                        },
                        interaction: { intersect: false, mode: 'index' }
                    }
                });
            }
        }
    });

    // ================================================================
    // KEYBOARD SHORTCUTS
    // ================================================================
    document.addEventListener('keydown', function(e) {
        if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
            e.preventDefault();
            searchInput?.focus();
        }
    });

    console.log('%c🏥 Braick Dispensary - Super Admin Dashboard', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c👤 Admin: <?= htmlspecialchars($user_full_name) ?>', 'font-size:13px; color:#059669;');
    console.log('%c💰 Total Revenue: TSh <?= number_format($total_revenue) ?>', 'font-size:13px; color:#0B5ED7;');
    console.log('%c   ├─ Patient Bills (paid_amount): TSh <?= number_format($patient_bills_revenue) ?>', 'font-size:12px; color:#0B5ED7;');
    console.log('%c   │  └─ Includes: Consultation + Prescriptions + Lab Tests', 'font-size:11px; color:#64748B;');
    console.log('%c   └─ OTC Sales: TSh <?= number_format($otc_revenue) ?>', 'font-size:12px; color:#D97706;');
    console.log('%c💊 Prescription Revenue (Display Only - Already in Bills): TSh <?= number_format($prescription_revenue) ?>', 'font-size:12px; color:#7C3AED;');
    console.log('%c💸 Total Expenses: TSh <?= number_format($total_expenses) ?>', 'font-size:13px; color:#E11D48;');
    console.log('%c📈 Net Profit: TSh <?= number_format($net_profit) ?> (<?= $profit_percentage ?>%)', 'font-size:13px; color:<?= $net_profit >= 0 ? '#059669' : '#EF4444' ?>;');
    console.log('%c🔬 Medical Equipment: <?= $total_equipment ?> total (Out: <?= $equip_out_of_stock ?>, Low: <?= $equip_low_stock ?>, Expired: <?= $equip_expired ?>, Soon: <?= $equip_expiring_soon ?>)', 'font-size:13px; color:#4F46E5;');
    console.log('%c✅ FIXED: Revenue uses paid_amount from bills (not final_price)', 'font-size:13px; color:#059669;');
    console.log('%c✅ FIXED: Bills with visit_id NOT NULL (consultation bills)', 'font-size:13px; color:#059669;');
    console.log('%c✅ FIXED: Lab test status ignored - patient already paid', 'font-size:13px; color:#059669;');
    console.log('%c✅ FIXED: OTC from otc_sales table', 'font-size:13px; color:#059669;');
    console.log('%c✅ FIXED: Equipment shows TOTAL <?= $total_equipment ?> items (not issues)', 'font-size:13px; color:#059669;');
    console.log('%c✅ FIXED: Prescription NOT double-counted in total revenue', 'font-size:13px; color:#059669; font-weight:bold;');
    console.log('%c📊 Revenue Formula: Total = Bills(paid_amount) + OTC', 'font-size:13px; color:#0B5ED7; font-weight:bold;');
    console.log('%c🔗 Navigation Links Updated:', 'font-size:13px; color:#4F46E5; font-weight:bold;');
    console.log('%c   ├─ Revenue → revenue.php', 'font-size:11px; color:#4F46E5;');
    console.log('%c   ├─ Expenses → expenses.php', 'font-size:11px; color:#4F46E5;');
    console.log('%c   ├─ Profit → profit.php', 'font-size:11px; color:#4F46E5;');
    console.log('%c   ├─ Prescriptions → prescriptions.php', 'font-size:11px; color:#4F46E5;');
    console.log('%c   ├─ OTC → ../pharmacy/otc_sales.php', 'font-size:11px; color:#4F46E5;');
    console.log('%c   ├─ Stock → inventory.php', 'font-size:11px; color:#4F46E5;');
    console.log('%c   ├─ Expiry → inventory.php?filter=expired', 'font-size:11px; color:#4F46E5;');
    console.log('%c   └─ Equipment → equipment_inventory.php', 'font-size:11px; color:#4F46E5;');
</script>

</body>
</html>