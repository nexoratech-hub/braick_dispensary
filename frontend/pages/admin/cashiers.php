<?php
// ================================================================
// FILE: frontend/pages/admin/cashiers.php
// SUPER ADMIN - VIEW ALL CASHIERS
// WITH SESSION MANAGEMENT & LOGIN PROTECTION
// BRAICK DISPENSARY - BEAUTIFUL CARDS DESIGN
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
// CHECK IF USER HAS ADMIN ACCESS
// ================================================================
if ($_SESSION['role'] !== 'admin') {
    $role = $_SESSION['role'];
    switch ($role) {
        case 'doctor': header('Location: ../doctor/dashboard.php'); break;
        case 'pharmacy': header('Location: ../pharmacy/dashboard.php'); break;
        case 'laboratory': header('Location: ../laboratory/dashboard.php'); break;
        case 'cashier': header('Location: ../cashier/dashboard.php'); break;
        case 'reception': header('Location: ../reception/dashboard.php'); break;
        default: header('Location: ../login.php'); break;
    }
    exit;
}

// ================================================================
// GET USER DATA FROM SESSION
// ================================================================
$user_id = $_SESSION['user_id'] ?? 0;
$user_full_name = $_SESSION['full_name'] ?? 'Admin';
$user_role = $_SESSION['role'] ?? 'admin';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$username = $_SESSION['username'] ?? '';
$profile_pic = $_SESSION['profile_pic'] ?? '';

// ================================================================
// IF SESSION IS INCOMPLETE, TRY TO RECOVER FROM DATABASE
// ================================================================
if ($user_id <= 0) {
    if (isset($username) && !empty($username)) {
        require_once __DIR__ . '/../../../backend/config/database.php';
        try {
            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare("SELECT id, full_name, role, branch_id, profile_pic FROM users WHERE username = ? AND status = 'active'");
            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($user) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['branch_id'] = $user['branch_id'];
                $_SESSION['profile_pic'] = $user['profile_pic'];
                $user_id = $user['id'];
                $user_full_name = $user['full_name'];
                $user_role = $user['role'];
                $user_branch_id = $user['branch_id'];
                $profile_pic = $user['profile_pic'];
            }
        } catch (Exception $e) {
            // Fallback to session values
        }
    }
}

// If still no user_id, redirect to login
if ($user_id <= 0) {
    header('Location: ../login.php');
    exit;
}

// ================================================================
// INCLUDE DATABASE
// ================================================================
require_once __DIR__ . '/../../../backend/config/database.php';
require_once __DIR__ . '/../../../backend/helpers/functions.php';

// ================================================================
// GET DATABASE CONNECTION
// ================================================================
try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database connection error: " . $e->getMessage());
}

// ================================================================
// GET FILTER PARAMETERS
// ================================================================
$selected_branch_id = $_GET['branch'] ?? 'all';
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? 'all';

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
// ✅ FIXED: GET FINANCIAL SUMMARY - WITH REAL EXPENSES
// ================================================================
function getFinancialSummary($db, $branch_id = 'all') {
    $results = [];
    
    $branch_condition = "";
    $params = [];
    
    if ($branch_id !== 'all' && is_numeric($branch_id)) {
        $branch_condition = " AND pb.branch_id = ?";
        $params[] = (int)$branch_id;
    }
    
    $params_otc = $params;
    $branch_condition_otc = str_replace('pb.branch_id', 'os.branch_id', $branch_condition);
    
    $params_expenses = [];
    $branch_condition_expenses = "";
    if ($branch_id !== 'all' && is_numeric($branch_id)) {
        $branch_condition_expenses = " AND branch_id = ?";
        $params_expenses[] = (int)$branch_id;
    }
    
    // 1. PATIENT BILLS REVENUE
    $sql_patient = "
        SELECT COALESCE(SUM(pb.total_amount), 0) as patient_revenue
        FROM patient_bills pb
        INNER JOIN patients p ON pb.patient_id = p.id
        WHERE pb.status = 'paid' $branch_condition
    ";
    $stmt = $db->prepare($sql_patient);
    $stmt->execute($params);
    $patient_revenue = $stmt->fetch(PDO::FETCH_ASSOC)['patient_revenue'] ?? 0;
    $results['patient_revenue'] = $patient_revenue;
    
    // 2. OTC REVENUE
    $sql_otc = "
        SELECT COALESCE(SUM(os.net_amount), 0) as otc_revenue
        FROM otc_sales os
        WHERE os.payment_status = 'paid' $branch_condition_otc
    ";
    $stmt = $db->prepare($sql_otc);
    $stmt->execute($params_otc);
    $otc_revenue = $stmt->fetch(PDO::FETCH_ASSOC)['otc_revenue'] ?? 0;
    $results['otc_revenue'] = $otc_revenue;
    
    // 3. TOTAL REVENUE
    $results['total_revenue'] = $patient_revenue + $otc_revenue;
    
    // 4. REAL EXPENSES FROM DATABASE
    $sql_expenses = "
        SELECT COALESCE(SUM(amount), 0) as total_expenses
        FROM expenses
        WHERE status = 'paid' $branch_condition_expenses
    ";
    $stmt = $db->prepare($sql_expenses);
    $stmt->execute($params_expenses);
    $results['total_expenses'] = $stmt->fetch(PDO::FETCH_ASSOC)['total_expenses'] ?? 0;
    
    // 5. NET PROFIT
    $results['net_profit'] = $results['total_revenue'] - $results['total_expenses'];
    
    // 6. PRESCRIPTION REVENUE
    $sql_prescription = "
        SELECT COALESCE(SUM(bi.total_price), 0) as prescription_revenue
        FROM bill_items bi
        INNER JOIN patient_bills pb ON bi.bill_id = pb.id
        INNER JOIN patients p ON pb.patient_id = p.id
        WHERE bi.item_type = 'medication' 
        AND pb.status = 'paid' 
        $branch_condition
    ";
    $stmt = $db->prepare($sql_prescription);
    $stmt->execute($params);
    $results['prescription_revenue'] = $stmt->fetch(PDO::FETCH_ASSOC)['prescription_revenue'] ?? 0;
    
    // 7. PAID BILLS
    $sql_paid = "
        SELECT COUNT(*) as paid_bills
        FROM patient_bills pb
        INNER JOIN patients p ON pb.patient_id = p.id
        WHERE pb.status = 'paid' $branch_condition
    ";
    $stmt = $db->prepare($sql_paid);
    $stmt->execute($params);
    $results['paid_bills'] = $stmt->fetch(PDO::FETCH_ASSOC)['paid_bills'] ?? 0;
    
    // 8. PENDING BILLS
    $sql_pending = "
        SELECT COUNT(*) as pending_bills
        FROM patient_bills pb
        INNER JOIN patients p ON pb.patient_id = p.id
        WHERE pb.status = 'pending' $branch_condition
    ";
    $stmt = $db->prepare($sql_pending);
    $stmt->execute($params);
    $results['pending_bills'] = $stmt->fetch(PDO::FETCH_ASSOC)['pending_bills'] ?? 0;
    
    // 9. CANCELLED BILLS
    $sql_cancelled = "
        SELECT COUNT(*) as cancelled_bills
        FROM patient_bills pb
        INNER JOIN patients p ON pb.patient_id = p.id
        WHERE pb.status = 'cancelled' $branch_condition
    ";
    $stmt = $db->prepare($sql_cancelled);
    $stmt->execute($params);
    $results['cancelled_bills'] = $stmt->fetch(PDO::FETCH_ASSOC)['cancelled_bills'] ?? 0;
    
    // 10. TOTAL BILLS
    $sql_total = "
        SELECT COUNT(*) as total_bills
        FROM patient_bills pb
        INNER JOIN patients p ON pb.patient_id = p.id
        WHERE 1=1 $branch_condition
    ";
    $stmt = $db->prepare($sql_total);
    $stmt->execute($params);
    $results['total_bills'] = $stmt->fetch(PDO::FETCH_ASSOC)['total_bills'] ?? 0;
    
    return $results;
}

// Get financial summary
$financial = getFinancialSummary($db, $selected_branch_id);

// ================================================================
// BUILD QUERY FOR CASHIER BRANCHES
// ================================================================
$query = "
    SELECT 
        b.*,
        (SELECT COUNT(*) FROM users WHERE branch_id = b.id AND role = 'cashier' AND status = 'active') as active_cashiers,
        (SELECT COUNT(*) FROM users WHERE branch_id = b.id AND role = 'cashier') as total_cashiers,
        (SELECT COUNT(*) 
         FROM patient_bills pb
         INNER JOIN patients p ON pb.patient_id = p.id
         WHERE pb.branch_id = b.id) as total_bills,
        (SELECT COUNT(*) 
         FROM patient_bills pb
         INNER JOIN patients p ON pb.patient_id = p.id
         WHERE pb.branch_id = b.id AND pb.status = 'pending') as pending_bills,
        (SELECT COUNT(*) 
         FROM patient_bills pb
         INNER JOIN patients p ON pb.patient_id = p.id
         WHERE pb.branch_id = b.id AND pb.status = 'paid') as paid_bills,
        (SELECT COUNT(*) 
         FROM patient_bills pb
         INNER JOIN patients p ON pb.patient_id = p.id
         WHERE pb.branch_id = b.id AND pb.status = 'partial') as partial_bills,
        (SELECT COUNT(*) 
         FROM patient_bills pb
         INNER JOIN patients p ON pb.patient_id = p.id
         WHERE pb.branch_id = b.id AND pb.status = 'cancelled') as cancelled_bills,
        (SELECT COALESCE(SUM(pb.total_amount), 0) 
         FROM patient_bills pb
         INNER JOIN patients p ON pb.patient_id = p.id
         WHERE pb.branch_id = b.id AND pb.status = 'paid') as patient_bills_revenue,
        (SELECT COALESCE(SUM(os.net_amount), 0) 
         FROM otc_sales os
         WHERE os.branch_id = b.id AND os.payment_status = 'paid') as otc_revenue,
        (SELECT COALESCE(SUM(bi.total_price), 0) 
         FROM bill_items bi
         INNER JOIN patient_bills pb ON bi.bill_id = pb.id
         INNER JOIN patients p ON pb.patient_id = p.id
         WHERE bi.item_type = 'medication' 
         AND pb.status = 'paid' 
         AND pb.branch_id = b.id) as prescription_revenue,
        (SELECT COALESCE(SUM(amount), 0) 
         FROM expenses 
         WHERE branch_id = b.id AND status = 'paid') as branch_expenses,
        (
            (SELECT COALESCE(SUM(pb.total_amount), 0) 
             FROM patient_bills pb
             INNER JOIN patients p ON pb.patient_id = p.id
             WHERE pb.branch_id = b.id AND pb.status = 'paid') 
            + 
            (SELECT COALESCE(SUM(os.net_amount), 0) 
             FROM otc_sales os
             WHERE os.branch_id = b.id AND os.payment_status = 'paid')
        ) as total_revenue
    FROM branches b
    WHERE 1=1
";

$params = [];

// Branch filter
if ($selected_branch_id !== 'all' && is_numeric($selected_branch_id)) {
    $query .= " AND b.id = ?";
    $params[] = (int)$selected_branch_id;
}

// Status filter
if ($status_filter !== 'all') {
    $query .= " AND b.status = ?";
    $params[] = $status_filter;
}

// Search filter
if (!empty($search)) {
    $query .= " AND (b.name LIKE ? OR b.location LIKE ? OR b.phone LIKE ? OR b.email LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

$query .= " ORDER BY b.name ASC";

// Execute query
$cashiers = [];
try {
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $cashiers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching cashiers: " . $e->getMessage());
    $cashiers = [];
}

// ================================================================
// GET SUMMARY STATISTICS
// ================================================================
$total_cashiers = count($cashiers);
$total_cashiers_count = 0;
$total_bills = 0;
$total_pending = 0;
$total_paid = 0;
$total_partial = 0;
$total_cancelled = 0;
$total_revenue = 0;
$total_patient_bills_revenue = 0;
$total_otc_revenue = 0;
$total_prescription_revenue = 0;
$total_expenses = 0;

foreach ($cashiers as $c) {
    $total_cashiers_count += ($c['total_cashiers'] ?? 0);
    $total_bills += ($c['total_bills'] ?? 0);
    $total_pending += ($c['pending_bills'] ?? 0);
    $total_paid += ($c['paid_bills'] ?? 0);
    $total_partial += ($c['partial_bills'] ?? 0);
    $total_cancelled += ($c['cancelled_bills'] ?? 0);
    $total_patient_bills_revenue += ($c['patient_bills_revenue'] ?? 0);
    $total_otc_revenue += ($c['otc_revenue'] ?? 0);
    $total_prescription_revenue += ($c['prescription_revenue'] ?? 0);
    $total_expenses += ($c['branch_expenses'] ?? 0);
    $total_revenue += ($c['total_revenue'] ?? 0);
}

$net_profit = $total_revenue - $total_expenses;

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
// INCLUDE SHARED HEADER & SIDEBAR
// ================================================================
include_once __DIR__ . '/../../components/admin_header.php';
include_once __DIR__ . '/../../components/admin_sidebar.php';
?>

<!DOCTYPE html>
<html lang="en" data-theme="<?= isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cashiers - Braick Dispensary</title>
    
    <link rel="icon" href="<?= $logo_url ?>" type="image/png">
    <link rel="shortcut icon" href="<?= $logo_url ?>" type="image/png">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <style>
        /* ================================================================
           ROOT VARIABLES - GREEN THEME
           ================================================================ */
        :root {
            --primary: #059669;
            --primary-dark: #047857;
            --primary-light: #34D399;
            --primary-bg: #D1FAE5;
            --primary-gradient: linear-gradient(135deg, #059669, #047857);
            --primary-gradient-strong: linear-gradient(135deg, #047857, #065F46);
            
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
            
            --blue: #0B5ED7;
            --blue-bg: #E8F0FE;
            
            --pink: #EC4899;
            --pink-bg: #FCE7F3;
            
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
            --shadow-xl: 0 15px 40px rgba(0,0,0,0.12);
            
            --bg-body: #F0FDF4;
            --bg-card: #FFFFFF;
            --bg-nav: #FFFFFF;
            --text-primary: #1E293B;
            --text-secondary: #64748B;
            --border-color: #D1FAE5;
            --radius: 12px;
            --radius-lg: 18px;
            --table-hover: #ECFDF5;
        }
        
        [data-theme="dark"] {
            --bg-body: #0F172A;
            --bg-card: #1E293B;
            --bg-nav: #1E293B;
            --text-primary: #F1F5F9;
            --text-secondary: #94A3B8;
            --border-color: #334155;
            --primary: #34D399;
            --primary-dark: #059669;
            --primary-light: #6EE7B7;
            --primary-bg: #1A3A2A;
            --primary-gradient: linear-gradient(135deg, #059669, #047857);
            --primary-gradient-strong: linear-gradient(135deg, #047857, #065F46);
            --shadow: 0 1px 3px rgba(0,0,0,0.3);
            --shadow-md: 0 4px 12px rgba(0,0,0,0.3);
            --shadow-lg: 0 10px 25px rgba(0,0,0,0.4);
            --shadow-xl: 0 15px 40px rgba(0,0,0,0.4);
            --table-hover: #1A3A2A;
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
            box-shadow: 0 0 0 4px rgba(5, 150, 105, 0.12);
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
           PAGE HEADER
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
            box-shadow: 0 8px 32px rgba(4, 120, 87, 0.35);
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
           ✅ 8 CARDS - BEAUTIFUL DESIGN
           ================================================================ */
        .cards-8-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 18px;
            margin-bottom: 28px;
        }
        
        .stat-card-8 {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            padding: 20px 24px;
            border: 2px solid var(--border-color);
            box-shadow: var(--shadow-sm);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            cursor: default;
        }
        
        .stat-card-8::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            border-radius: 0 0 3px 3px;
            opacity: 0.9;
        }
        
        .stat-card-8.green::before { background: linear-gradient(90deg, #059669, #34D399); }
        .stat-card-8.blue::before { background: linear-gradient(90deg, #0B5ED7, #6EA8FE); }
        .stat-card-8.purple::before { background: linear-gradient(90deg, #7C3AED, #A78BFA); }
        .stat-card-8.orange::before { background: linear-gradient(90deg, #F59E0B, #FBBF24); }
        .stat-card-8.teal::before { background: linear-gradient(90deg, #0D9488, #5EEAD4); }
        .stat-card-8.pink::before { background: linear-gradient(90deg, #EC4899, #F472B6); }
        .stat-card-8.red::before { background: linear-gradient(90deg, #EF4444, #F87171); }
        .stat-card-8.indigo::before { background: linear-gradient(90deg, #4F46E5, #818CF8); }
        
        .stat-card-8:hover {
            transform: translateY(-6px);
            box-shadow: var(--shadow-xl);
            border-color: var(--primary);
        }
        
        .stat-card-8 .stat-icon-large {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
            flex-shrink: 0;
            transition: all 0.3s ease;
        }
        
        .stat-card-8:hover .stat-icon-large {
            transform: scale(1.05) rotate(-5deg);
        }
        
        .stat-card-8 .stat-icon-large.green { background: var(--primary-bg); color: #059669; }
        .stat-card-8 .stat-icon-large.blue { background: var(--blue-bg); color: #0B5ED7; }
        .stat-card-8 .stat-icon-large.purple { background: var(--purple-bg); color: #7C3AED; }
        .stat-card-8 .stat-icon-large.orange { background: #FFFBEB; color: #F59E0B; }
        .stat-card-8 .stat-icon-large.teal { background: var(--teal-bg); color: #0D9488; }
        .stat-card-8 .stat-icon-large.pink { background: var(--pink-bg); color: #EC4899; }
        .stat-card-8 .stat-icon-large.red { background: #FEF2F2; color: #EF4444; }
        .stat-card-8 .stat-icon-large.indigo { background: #EEF2FF; color: #4F46E5; }
        
        [data-theme="dark"] .stat-card-8 .stat-icon-large.green { background: #1A3A2A; color: #34D399; }
        [data-theme="dark"] .stat-card-8 .stat-icon-large.blue { background: #1E3A5F; color: #6EA8FE; }
        [data-theme="dark"] .stat-card-8 .stat-icon-large.purple { background: #2D1B4E; color: #A78BFA; }
        [data-theme="dark"] .stat-card-8 .stat-icon-large.orange { background: #3D2E0A; color: #FBBF24; }
        [data-theme="dark"] .stat-card-8 .stat-icon-large.teal { background: #0F3D3D; color: #5EEAD4; }
        [data-theme="dark"] .stat-card-8 .stat-icon-large.pink { background: #3A1A2A; color: #F472B6; }
        [data-theme="dark"] .stat-card-8 .stat-icon-large.red { background: #3A1A1A; color: #F87171; }
        [data-theme="dark"] .stat-card-8 .stat-icon-large.indigo { background: #1E1B4B; color: #818CF8; }
        
        .stat-card-8 .stat-number {
            font-size: 1.8rem;
            font-weight: 800;
            color: var(--text-primary);
            line-height: 1.1;
            font-family: 'Inter', monospace;
        }
        
        .stat-card-8 .stat-number.green { color: #059669; }
        .stat-card-8 .stat-number.blue { color: #0B5ED7; }
        .stat-card-8 .stat-number.purple { color: #7C3AED; }
        .stat-card-8 .stat-number.orange { color: #F59E0B; }
        .stat-card-8 .stat-number.teal { color: #0D9488; }
        .stat-card-8 .stat-number.pink { color: #EC4899; }
        .stat-card-8 .stat-number.red { color: #EF4444; }
        .stat-card-8 .stat-number.indigo { color: #4F46E5; }
        
        .stat-card-8 .stat-label {
            font-size: 0.6rem;
            color: var(--text-secondary);
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin: 0;
        }
        
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
        }
        
        .filter-bar::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: var(--primary-gradient);
            border-radius: 3px 3px 0 0;
        }
        
        .filter-bar .filter-label {
            font-size: 0.7rem;
            font-weight: 600;
            color: var(--primary);
            text-transform: uppercase;
            letter-spacing: 0.04em;
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
            box-shadow: 0 0 0 3px rgba(5, 150, 105, 0.12);
        }
        
        .filter-bar .btn-filter {
            background: var(--primary-gradient);
            color: white;
            border: none;
            padding: 5px 14px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.7rem;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .filter-bar .btn-filter:hover {
            transform: scale(1.02);
            box-shadow: 0 4px 12px rgba(5, 150, 105, 0.3);
        }
        
        .filter-bar .btn-reset {
            background: transparent;
            color: var(--text-secondary);
            border: 2px solid var(--border-color);
            padding: 5px 14px;
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
           CASHIER CARDS - BEAUTIFUL DESIGN WITH GREEN HEADER
           ================================================================ */
        .cashier-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
            gap: 24px;
        }
        
        .cashier-card {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            border: 2px solid var(--border-color);
            overflow: hidden;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: var(--shadow-sm);
            position: relative;
        }
        
        .cashier-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 6px;
            background: var(--primary-gradient-strong);
            opacity: 0.8;
            transition: all 0.3s ease;
            z-index: 2;
        }
        
        .cashier-card:hover {
            transform: translateY(-8px);
            box-shadow: var(--shadow-xl);
            border-color: var(--primary);
        }
        
        .cashier-card:hover::before {
            opacity: 1;
            height: 8px;
        }
        
        /* ✅ GREEN HEADER BACKGROUND */
        .cashier-card .card-top {
            padding: 18px 22px 14px;
            border-bottom: 2px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: start;
            background: var(--primary-gradient-strong);
            position: relative;
            overflow: hidden;
            min-height: 80px;
        }
        
        .cashier-card .card-top::after {
            content: '';
            position: absolute;
            top: -50%;
            right: -20%;
            width: 200px;
            height: 200px;
            background: rgba(255,255,255,0.05);
            border-radius: 50%;
            pointer-events: none;
        }
        
        .cashier-card .card-top .name {
            font-size: 1.1rem;
            font-weight: 700;
            color: white;
            position: relative;
            z-index: 1;
            text-shadow: 0 2px 4px rgba(0,0,0,0.15);
        }
        
        .cashier-card .card-top .name i {
            color: rgba(255,255,255,0.8);
            margin-right: 8px;
        }
        
        .cashier-card .card-top .location-text {
            font-size: 0.7rem;
            color: rgba(255,255,255,0.85);
            margin-top: 4px;
            display: flex;
            align-items: center;
            gap: 4px;
            position: relative;
            z-index: 1;
        }
        
        .cashier-card .card-top .location-text i {
            color: rgba(255,255,255,0.6);
            font-size: 0.65rem;
        }
        
        .cashier-card .card-top .status-badge {
            padding: 4px 16px;
            border-radius: 20px;
            font-size: 0.6rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            position: relative;
            z-index: 1;
            backdrop-filter: blur(4px);
            border: 1px solid rgba(255,255,255,0.2);
        }
        
        .cashier-card .card-top .status-badge.active {
            background: rgba(52, 211, 153, 0.25);
            color: #34D399;
        }
        
        .cashier-card .card-top .status-badge.inactive {
            background: rgba(248, 113, 113, 0.25);
            color: #F87171;
        }
        
        .cashier-card .card-body {
            padding: 16px 22px 18px;
        }
        
        .cashier-card .card-body .info-row {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.75rem;
            color: var(--text-secondary);
            padding: 4px 0;
        }
        
        .cashier-card .card-body .info-row i {
            width: 18px;
            color: var(--primary);
            font-size: 0.75rem;
        }
        
        .cashier-card .card-stats {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 4px;
            padding: 10px 18px;
            background: var(--bg-body);
            border-top: 2px solid var(--border-color);
            border-bottom: 2px solid var(--border-color);
        }
        
        [data-theme="dark"] .cashier-card .card-stats {
            background: #0F172A;
        }
        
        .cashier-card .card-stats .stat-item {
            text-align: center;
            padding: 4px 0;
            border-radius: 6px;
            transition: all 0.3s ease;
        }
        
        .cashier-card .card-stats .stat-item:hover {
            background: var(--primary-bg);
        }
        
        [data-theme="dark"] .cashier-card .card-stats .stat-item:hover {
            background: #1A3A2A;
        }
        
        .cashier-card .card-stats .stat-item .num {
            font-size: 0.9rem;
            font-weight: 700;
            color: var(--text-primary);
        }
        
        .cashier-card .card-stats .stat-item .num.green { color: var(--primary); }
        .cashier-card .card-stats .stat-item .num.orange { color: #F59E0B; }
        .cashier-card .card-stats .stat-item .num.purple { color: #7C3AED; }
        .cashier-card .card-stats .stat-item .num.teal { color: #0D9488; }
        .cashier-card .card-stats .stat-item .num.red { color: #DC2626; }
        .cashier-card .card-stats .stat-item .num.blue { color: #0B5ED7; }
        
        .cashier-card .card-stats .stat-item .label {
            font-size: 0.5rem;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.04em;
            font-weight: 700;
        }
        
        .cashier-card .card-actions {
            padding: 12px 18px;
            display: flex;
            gap: 8px;
            justify-content: flex-end;
            flex-wrap: wrap;
        }
        
        .cashier-card .card-actions .btn-action {
            padding: 5px 16px;
            border-radius: 8px;
            font-size: 0.65rem;
            font-weight: 700;
            text-decoration: none;
            transition: all 0.3s;
            border: 2px solid var(--border-color);
            color: var(--text-secondary);
            background: transparent;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        
        .cashier-card .card-actions .btn-action:hover {
            border-color: var(--primary);
            color: var(--primary);
            transform: translateY(-2px);
        }
        
        .cashier-card .card-actions .btn-action.primary {
            background: var(--primary-gradient-strong);
            color: white;
            border-color: var(--primary);
        }
        
        .cashier-card .card-actions .btn-action.primary:hover {
            background: var(--primary-dark);
            border-color: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(4, 120, 87, 0.35);
        }
        
        /* Revenue breakdown */
        .revenue-breakdown {
            display: flex;
            gap: 12px;
            justify-content: center;
            padding: 8px 0 2px;
            border-top: 1px solid var(--border-color);
            margin-top: 8px;
            font-size: 0.6rem;
            color: var(--text-secondary);
            flex-wrap: wrap;
        }
        
        .revenue-breakdown .item {
            display: flex;
            align-items: center;
            gap: 4px;
        }
        
        .revenue-breakdown .item .label { font-weight: 500; }
        .revenue-breakdown .item .amount { font-weight: 700; }
        .revenue-breakdown .item .amount.blue { color: #0B5ED7; }
        .revenue-breakdown .item .amount.green { color: var(--primary); }
        .revenue-breakdown .item .amount.red { color: #EF4444; }
        .revenue-breakdown .item .amount.teal { color: #0D9488; }
        
        /* ================================================================
           EMPTY STATE
           ================================================================ */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-secondary);
        }
        
        .empty-state i {
            font-size: 4rem;
            color: var(--border-color);
            margin-bottom: 16px;
        }
        
        .empty-state h3 {
            font-size: 1.3rem;
            color: var(--text-primary);
            margin-bottom: 8px;
        }
        
        .empty-state p {
            font-size: 0.9rem;
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
            font-weight: 700;
        }
        
        /* ================================================================
           RESPONSIVE
           ================================================================ */
        @media (max-width: 1024px) {
            .top-nav { left: 0; }
            .main-content { margin-left: 0; padding: 16px; }
            .top-nav .search-wrapper { max-width: 300px; }
            .cashier-grid { grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); }
            .cards-8-grid { grid-template-columns: repeat(2, 1fr); }
        }
        
        @media (max-width: 768px) {
            .top-nav .search-wrapper { max-width: 180px; }
            .top-nav .datetime { display: none; }
            .page-header { padding: 16px 18px; }
            .page-header .page-title { font-size: 1.3rem; }
            .cashier-grid { grid-template-columns: 1fr; }
            .cards-8-grid { grid-template-columns: 1fr 1fr; gap: 12px; }
            .filter-bar { flex-direction: column; align-items: stretch; }
            .cashier-card .card-stats { grid-template-columns: repeat(4, 1fr); }
            .stat-card-8 .stat-number { font-size: 1.3rem; }
            .stat-card-8 { padding: 14px 16px; }
            .stat-card-8 .stat-icon-large { width: 38px; height: 38px; font-size: 1rem; }
        }
        
        @media (max-width: 480px) {
            .main-content { padding: 10px; }
            .cards-8-grid { grid-template-columns: 1fr; }
            .page-header { flex-direction: column; align-items: flex-start !important; }
            .cashier-card .card-stats { grid-template-columns: repeat(2, 1fr); }
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
            box-shadow: var(--shadow-lg);
        }
        
        .toast-custom.show {
            transform: translateY(0);
            opacity: 1;
        }
        
        .toast-custom.success { background: var(--success); }
        .toast-custom.error { background: var(--danger); }
        .toast-custom.info { background: var(--primary); }
        .toast-custom.warning { background: var(--warning); }
        
        /* ================================================================
           PRINT STYLES
           ================================================================ */
        @media print {
            .top-nav, .sidebar, .btn, .dark-toggle-btn, .icon-btn,
            .search-wrapper, .page-header .btn-outline-light,
            .filter-bar, .footer, #sidebarToggle { display: none !important; }
            .main-content { margin: 0; padding: 20px; }
            .cashier-card { break-inside: avoid; box-shadow: none !important; border: 1px solid #ddd; }
            .page-header {
                background: #059669 !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            .page-title, .page-subtitle, .header-badge, .role-badge-display {
                color: white !important;
            }
            .stat-card-8 { break-inside: avoid; }
            .cards-8-grid { grid-template-columns: repeat(4, 1fr); }
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
            <input type="text" id="searchInput" placeholder="Search cashiers..." value="<?= htmlspecialchars($search) ?>">
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
    <!-- PAGE HEADER -->
    <!-- ================================================================ -->
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-cash-register"></i>
                Cashiers Dashboard
                <span class="role-badge-display">ADMIN</span>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-store-alt"></i>
                <strong><?= $total_cashiers ?></strong> cashier branches found
                <span class="header-badge" style="background:rgba(52,211,153,0.2);border-color:rgba(52,211,153,0.3);color:#34D399;">
                    <i class="fas fa-money-bill-wave"></i> TSh <?= number_format($financial['total_revenue'], 0) ?> Revenue
                </span>
                <span class="header-badge" style="background:rgba(251,191,36,0.2);border-color:rgba(251,191,36,0.3);color:#FBBF24;">
                    <i class="fas fa-file-invoice"></i> <?= number_format($financial['total_bills']) ?> Bills
                </span>
                <span class="header-badge" style="background:rgba(239,68,68,0.2);border-color:rgba(239,68,68,0.3);color:#F87171;">
                    <i class="fas fa-arrow-up"></i> Expenses: TSh <?= number_format($financial['total_expenses'], 0) ?>
                </span>
            </p>
        </div>
        <div class="flex gap-2 flex-wrap" style="position:relative;z-index:1;">
            <a href="add_cashier.php" class="btn-outline-light">
                <i class="fas fa-plus"></i> Add Cashier
            </a>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- ✅ 8 CARDS - FINANCIAL SUMMARY -->
    <!-- ================================================================ -->
    <div class="cards-8-grid animate-fade-in-up">
        
        <!-- 1. TOTAL REVENUE -->
        <div class="stat-card-8 green">
            <div class="flex items-center gap-3">
                <div class="stat-icon-large green"><i class="fas fa-money-bill-wave"></i></div>
                <div>
                    <p class="stat-label">Total Revenue</p>
                    <p class="stat-number green">TSh <?= number_format($financial['total_revenue'], 0) ?></p>
                </div>
            </div>
        </div>
        
        <!-- 2. TOTAL EXPENSES -->
        <div class="stat-card-8 red">
            <div class="flex items-center gap-3">
                <div class="stat-icon-large red"><i class="fas fa-arrow-up"></i></div>
                <div>
                    <p class="stat-label">Total Expenses</p>
                    <p class="stat-number red">TSh <?= number_format($financial['total_expenses'], 0) ?></p>
                </div>
            </div>
        </div>
        
        <!-- 3. NET PROFIT -->
        <div class="stat-card-8 blue">
            <div class="flex items-center gap-3">
                <div class="stat-icon-large blue"><i class="fas fa-chart-line"></i></div>
                <div>
                    <p class="stat-label">Net Profit</p>
                    <p class="stat-number blue">TSh <?= number_format($financial['net_profit'], 0) ?></p>
                </div>
            </div>
        </div>
        
        <!-- 4. PRESCRIPTION REVENUE -->
        <div class="stat-card-8 purple">
            <div class="flex items-center gap-3">
                <div class="stat-icon-large purple"><i class="fas fa-prescription"></i></div>
                <div>
                    <p class="stat-label">Prescription Revenue</p>
                    <p class="stat-number purple">TSh <?= number_format($financial['prescription_revenue'], 0) ?></p>
                </div>
            </div>
        </div>
        
        <!-- 5. OTC REVENUE -->
        <div class="stat-card-8 orange">
            <div class="flex items-center gap-3">
                <div class="stat-icon-large orange"><i class="fas fa-cash-register"></i></div>
                <div>
                    <p class="stat-label">OTC Revenue</p>
                    <p class="stat-number orange">TSh <?= number_format($financial['otc_revenue'], 0) ?></p>
                </div>
            </div>
        </div>
        
        <!-- 6. PAID BILLS -->
        <div class="stat-card-8 teal">
            <div class="flex items-center gap-3">
                <div class="stat-icon-large teal"><i class="fas fa-check-circle"></i></div>
                <div>
                    <p class="stat-label">Paid Bills</p>
                    <p class="stat-number teal"><?= number_format($financial['paid_bills']) ?></p>
                </div>
            </div>
        </div>
        
        <!-- 7. PENDING BILLS -->
        <div class="stat-card-8 orange">
            <div class="flex items-center gap-3">
                <div class="stat-icon-large orange"><i class="fas fa-clock"></i></div>
                <div>
                    <p class="stat-label">Pending Bills</p>
                    <p class="stat-number orange"><?= number_format($financial['pending_bills']) ?></p>
                </div>
            </div>
        </div>
        
        <!-- 8. CANCELLED BILLS -->
        <div class="stat-card-8 red">
            <div class="flex items-center gap-3">
                <div class="stat-icon-large red"><i class="fas fa-times-circle"></i></div>
                <div>
                    <p class="stat-label">Cancelled Bills</p>
                    <p class="stat-number red"><?= number_format($financial['cancelled_bills']) ?></p>
                </div>
            </div>
        </div>
        
    </div>

    <!-- ================================================================ -->
    <!-- FILTER BAR -->
    <!-- ================================================================ -->
    <div class="filter-bar animate-fade-in-up" style="animation-delay:0.05s;">
        <span class="filter-label"><i class="fas fa-filter"></i> Filter</span>
        
        <form method="GET" action="" class="flex flex-wrap gap-2 items-center w-full">
            <input type="hidden" name="branch" value="<?= htmlspecialchars($selected_branch_id) ?>">
            
            <select name="status" class="flex-1 min-w-[120px]">
                <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>All Status</option>
                <option value="active" <?= $status_filter === 'active' ? 'selected' : '' ?>>Active</option>
                <option value="inactive" <?= $status_filter === 'inactive' ? 'selected' : '' ?>>Inactive</option>
            </select>
            
            <input type="text" name="search" placeholder="Search by name, location..." 
                   value="<?= htmlspecialchars($search) ?>" class="flex-1 min-w-[180px]">
            
            <button type="submit" class="btn-filter">
                <i class="fas fa-search"></i> Apply
            </button>
            
            <a href="cashiers.php?branch=<?= urlencode($selected_branch_id) ?>" class="btn-reset">
                <i class="fas fa-times"></i> Reset
            </a>
        </form>
    </div>

    <!-- ================================================================ -->
    <!-- CASHIER GRID -->
    <!-- ================================================================ -->
    <?php if (count($cashiers) > 0): ?>
        <div class="cashier-grid animate-fade-in-up" style="animation-delay:0.1s;">
            <?php foreach ($cashiers as $cashier): ?>
                <div class="cashier-card">
                    <!-- ✅ GREEN HEADER BACKGROUND -->
                    <div class="card-top">
                        <div>
                            <div class="name">
                                <i class="fas fa-cash-register"></i>
                                <?= htmlspecialchars($cashier['name']) ?>
                            </div>
                            <div class="location-text">
                                <i class="fas fa-map-marker-alt"></i>
                                <?= htmlspecialchars($cashier['location'] ?? 'N/A') ?>
                            </div>
                        </div>
                        <span class="status-badge <?= $cashier['status'] === 'active' ? 'active' : 'inactive' ?>">
                            <?= $cashier['status'] === 'active' ? 'Active' : 'Inactive' ?>
                        </span>
                    </div>
                    
                    <div class="card-body">
                        <div class="info-row">
                            <i class="fas fa-phone"></i>
                            <?= htmlspecialchars($cashier['phone'] ?? 'N/A') ?>
                        </div>
                        <div class="info-row">
                            <i class="fas fa-envelope"></i>
                            <?= htmlspecialchars($cashier['email'] ?? 'N/A') ?>
                        </div>
                        <div class="info-row">
                            <i class="fas fa-user-tie"></i>
                            <?= ($cashier['active_cashiers'] ?? 0) ?> Active / <?= ($cashier['total_cashiers'] ?? 0) ?> Cashiers
                        </div>
                        <div class="info-row">
                            <i class="fas fa-arrow-up" style="color:#EF4444;"></i>
                            Expenses: TSh <?= number_format($cashier['branch_expenses'] ?? 0, 0) ?>
                        </div>
                        
                        <!-- Revenue Breakdown -->
                        <div class="revenue-breakdown">
                            <span class="item">
                                <span class="label">Bills:</span>
                                <span class="amount blue">TSh <?= number_format($cashier['patient_bills_revenue'] ?? 0, 0) ?></span>
                            </span>
                            <span class="item">
                                <span class="label">OTC:</span>
                                <span class="amount green">TSh <?= number_format($cashier['otc_revenue'] ?? 0, 0) ?></span>
                            </span>
                            <span class="item">
                                <span class="label">Exp:</span>
                                <span class="amount red">TSh <?= number_format($cashier['branch_expenses'] ?? 0, 0) ?></span>
                            </span>
                            <span class="item" style="font-weight:700;color:var(--primary);">
                                <span class="label">Net:</span>
                                <span class="amount teal">TSh <?= number_format(($cashier['total_revenue'] ?? 0) - ($cashier['branch_expenses'] ?? 0), 0) ?></span>
                            </span>
                        </div>
                    </div>
                    
                    <div class="card-stats">
                        <div class="stat-item">
                            <div class="num green"><?= number_format($cashier['total_bills'] ?? 0) ?></div>
                            <div class="label">Bills</div>
                        </div>
                        <div class="stat-item">
                            <div class="num <?= ($cashier['pending_bills'] ?? 0) > 0 ? 'orange' : 'green' ?>">
                                <?= number_format($cashier['pending_bills'] ?? 0) ?>
                            </div>
                            <div class="label">Pending</div>
                        </div>
                        <div class="stat-item">
                            <div class="num purple"><?= number_format($cashier['paid_bills'] ?? 0) ?></div>
                            <div class="label">Paid</div>
                        </div>
                        <div class="stat-item">
                            <div class="num teal">TSh <?= number_format($cashier['total_revenue'] ?? 0, 0) ?></div>
                            <div class="label">Revenue</div>
                        </div>
                    </div>
                    
                    <div class="card-actions">
                        <a href="view_cashier.php?id=<?= $cashier['id'] ?>&branch=<?= $selected_branch_id ?>" 
                           class="btn-action primary">
                            <i class="fas fa-eye"></i> View
                        </a>
                        <a href="edit_cashier.php?id=<?= $cashier['id'] ?>&branch=<?= $selected_branch_id ?>" 
                           class="btn-action">
                            <i class="fas fa-edit"></i> Edit
                        </a>
                        <a href="cashier_dashboard.php?id=<?= $cashier['id'] ?>" 
                           class="btn-action">
                            <i class="fas fa-chart-bar"></i> Dashboard
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <div class="text-center text-sm text-gray-500 dark:text-gray-400 py-3">
            Showing <strong><?= count($cashiers) ?></strong> cashier branch<?= count($cashiers) > 1 ? 'es' : '' ?>
        </div>
    <?php else: ?>
        <div class="empty-state animate-fade-in-up" style="animation-delay:0.1s;">
            <i class="fas fa-cash-register"></i>
            <h3>No Cashiers Found</h3>
            <p>Try adjusting your filters or <a href="add_cashier.php" class="text-green-600 hover:underline">add a new cashier</a></p>
        </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Cashiers
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
        var branch = '<?= $selected_branch_id ?>';
        var status = '<?= $status_filter ?>';
        var url = 'cashiers.php?branch=' + encodeURIComponent(branch) + '&status=' + encodeURIComponent(status);
        if (query.length > 0) {
            url += '&search=' + encodeURIComponent(query);
        }
        window.location.href = url;
    }
    
    searchBtn?.addEventListener('click', performSearch);
    searchInput?.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') performSearch();
    });

    function switchBranch(branchId) {
        var url = new URL(window.location.href);
        url.searchParams.set('branch', branchId);
        url.searchParams.delete('branch_id');
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

    // Check if there was an error in URL
    <?php if (isset($_GET['error']) && $_GET['error'] === 'invalid_id'): ?>
        showToast('⚠️ Error', 'Invalid cashier ID provided', 'error');
        // Clean URL
        var cleanUrl = window.location.href.split('?')[0];
        if (window.history && window.history.replaceState) {
            window.history.replaceState({}, document.title, cleanUrl);
        }
    <?php endif; ?>

    console.log('%c💰 Braick Dispensary - Cashiers (GREEN HEADER)', 'font-size:18px; font-weight:bold; color:#059669;');
    console.log('%c👤 User: <?= htmlspecialchars($user_full_name) ?> (<?= htmlspecialchars($user_role) ?>)', 'font-size:13px; color:#059669;');
    console.log('%c🏢 Total Cashiers: <?= $total_cashiers ?>', 'font-size:13px; color:#059669;');
    console.log('%c✅ 8 CARDS:', 'font-size:13px; font-weight:bold; color:#0B5ED7;');
    console.log('  1. Total Revenue: TSh <?= number_format($financial['total_revenue'], 0) ?>', 'font-size:12px; color:#059669;');
    console.log('  2. Total Expenses: TSh <?= number_format($financial['total_expenses'], 0) ?> (FROM expenses TABLE)', 'font-size:12px; color:#EF4444;');
    console.log('  3. Net Profit: TSh <?= number_format($financial['net_profit'], 0) ?>', 'font-size:12px; color:#0B5ED7;');
    console.log('  4. Prescription Revenue: TSh <?= number_format($financial['prescription_revenue'], 0) ?>', 'font-size:12px; color:#7C3AED;');
    console.log('  5. OTC Revenue: TSh <?= number_format($financial['otc_revenue'], 0) ?>', 'font-size:12px; color:#F59E0B;');
    console.log('  6. Paid Bills: <?= number_format($financial['paid_bills']) ?>', 'font-size:12px; color:#0D9488;');
    console.log('  7. Pending Bills: <?= number_format($financial['pending_bills']) ?>', 'font-size:12px; color:#F59E0B;');
    console.log('  8. Cancelled Bills: <?= number_format($financial['cancelled_bills']) ?>', 'font-size:12px; color:#EF4444;');
    console.log('%c✅ GREEN HEADER BACKGROUND on cashier cards', 'font-size:13px; color:#34D399;');
    console.log('%c✅ FIXED: error=invalid_id handled with toast', 'font-size:13px; color:#34D399;');
    console.log('%c🔒 Login protection: Active', 'font-size:13px; color:#34D399;');
</script>

</body>
</html>