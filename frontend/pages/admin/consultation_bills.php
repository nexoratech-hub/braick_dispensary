<?php
// ================================================================
// FILE: frontend/pages/admin/consultation_bills.php
// ADMIN - VIEW CONSULTATION BILLS ONLY
// FIXED: Uses bills table (NOT patient_bills)
// FIXED: Shows correct branch in header
// FIXED: "All Branches" when no branch selected
// FIXED: Uses patient's branch from patients table
// FIXED: Removed "New Bill" button
// BLUE THEME
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
$user_id = $_SESSION['user_id'] ?? 0;
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

$db = Database::getInstance()->getConnection();

// ================================================================
// GET BRANCH ID FROM FILTER
// ================================================================
$branch_id = isset($_GET['branch']) ? (int)$_GET['branch'] : 0;

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
// GET BRANCH NAME - ONLY if a specific branch is selected
// ================================================================
$branch_name = 'All Branches';

if ($branch_id > 0) {
    try {
        $stmt = $db->prepare("SELECT name FROM branches WHERE id = ?");
        $stmt->execute([$branch_id]);
        $branch = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($branch) {
            $branch_name = $branch['name'];
        } else {
            $branch_name = 'Unknown Branch';
        }
    } catch (Exception $e) {
        $branch_name = 'Unknown Branch';
    }
}

// ================================================================
// GET FILTER PARAMETERS
// ================================================================
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : 'all';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$from_date = isset($_GET['from_date']) ? trim($_GET['from_date']) : '';
$to_date = isset($_GET['to_date']) ? trim($_GET['to_date']) : '';

// ================================================================
// BUILD QUERY FOR CONSULTATION BILLS - USING bills TABLE
// ================================================================
$query = "
    SELECT 
        b.id,
        b.bill_number,
        b.total_amount,
        b.paid_amount,
        b.balance,
        b.status,
        b.subtotal,
        b.discount_amount,
        b.total_discount,
        b.payment_method,
        b.created_at,
        b.updated_at,
        pat.id as patient_id,
        pat.full_name as patient_name,
        pat.patient_id as patient_code,
        pat.phone as patient_phone,
        pat.email as patient_email,
        pat.branch_id as patient_branch_id,
        br.id as branch_id,
        br.name as branch_name,
        br.location as branch_location,
        (
            SELECT COUNT(*) 
            FROM bill_items bi 
            WHERE bi.bill_id = b.id 
            AND bi.item_type = 'consultation'
            AND bi.status != 'cancelled'
        ) as consultation_count,
        (
            SELECT COALESCE(SUM(bi.total_price), 0)
            FROM bill_items bi 
            WHERE bi.bill_id = b.id 
            AND bi.item_type = 'consultation'
            AND bi.status != 'cancelled'
        ) as consultation_total,
        (
            SELECT GROUP_CONCAT(DISTINCT bi.item_name SEPARATOR ', ')
            FROM bill_items bi 
            WHERE bi.bill_id = b.id 
            AND bi.item_type = 'consultation'
            AND bi.status != 'cancelled'
            LIMIT 3
        ) as consultation_items,
        (
            SELECT COUNT(*) 
            FROM bill_items bi 
            WHERE bi.bill_id = b.id 
            AND bi.status != 'cancelled'
        ) as total_items
    FROM bills b
    INNER JOIN patients pat ON b.patient_id = pat.id
    LEFT JOIN branches br ON pat.branch_id = br.id
    WHERE 1=1
    AND EXISTS (
        SELECT 1 
        FROM bill_items bi 
        WHERE bi.bill_id = b.id 
        AND bi.item_type = 'consultation'
        AND bi.status != 'cancelled'
    )
";

$params = [];

// Branch filter - using patient's branch
if ($branch_id > 0) {
    $query .= " AND pat.branch_id = ?";
    $params[] = $branch_id;
}

// Status filter
if ($status_filter !== 'all') {
    $query .= " AND b.status = ?";
    $params[] = $status_filter;
}

// Date range filter
if (!empty($from_date)) {
    $query .= " AND DATE(b.created_at) >= ?";
    $params[] = $from_date;
}
if (!empty($to_date)) {
    $query .= " AND DATE(b.created_at) <= ?";
    $params[] = $to_date;
}

// Search filter
if (!empty($search)) {
    $query .= " AND (b.bill_number LIKE ? OR pat.full_name LIKE ? OR pat.phone LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

$query .= " ORDER BY b.created_at DESC";

// Execute query
$consultation_bills = [];
try {
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $consultation_bills = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching consultation bills: " . $e->getMessage());
    $consultation_bills = [];
}

// ================================================================
// CALCULATE SUMMARY STATISTICS
// ================================================================
$total_bills = count($consultation_bills);
$total_consultation_revenue = 0;
$total_bill_amount = 0;
$total_paid = 0;
$total_pending = 0;
$total_partial = 0;
$total_cancelled = 0;
$total_paid_amount = 0;
$total_balance = 0;

// Track unique branches for display
$unique_branches = [];

foreach ($consultation_bills as $bill) {
    // Use consultation_total ONLY (not total_amount)
    $consultation_amount = $bill['consultation_total'] ?? 0;
    $total_consultation_revenue += $consultation_amount;
    $total_bill_amount += $bill['total_amount'] ?? 0;
    $total_paid_amount += $bill['paid_amount'] ?? 0;
    $total_balance += $bill['balance'] ?? 0;
    
    // Track unique branches
    if (!empty($bill['branch_name']) && !in_array($bill['branch_name'], $unique_branches)) {
        $unique_branches[] = $bill['branch_name'];
    }
    
    switch ($bill['status']) {
        case 'paid': $total_paid++; break;
        case 'pending': $total_pending++; break;
        case 'partial': $total_partial++; break;
        case 'cancelled': $total_cancelled++; break;
    }
}

// ================================================================
// DETERMINE BRANCH DISPLAY NAME
// ================================================================
$branch_display_name = 'All Branches';

if ($branch_id > 0) {
    $branch_display_name = $branch_name;
} else {
    if (count($unique_branches) == 1) {
        $branch_display_name = $unique_branches[0];
    } elseif (count($unique_branches) > 1) {
        $branch_display_name = 'Multiple Branches (' . count($unique_branches) . ')';
    } else {
        $branch_display_name = 'All Branches';
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
// STATUS BADGE CLASS
// ================================================================
function getStatusBadge($status) {
    $classes = [
        'pending' => 'warning',
        'paid' => 'success',
        'partial' => 'warning',
        'cancelled' => 'danger'
    ];
    return $classes[$status] ?? 'secondary';
}

function getStatusIcon($status) {
    $icons = [
        'pending' => 'fa-clock',
        'paid' => 'fa-check-circle',
        'partial' => 'fa-hourglass-half',
        'cancelled' => 'fa-times-circle'
    ];
    return $icons[$status] ?? 'fa-circle';
}

// ================================================================
// FORMAT CURRENCY
// ================================================================
function formatCurrency($amount) {
    return 'TSh ' . number_format($amount, 0);
}

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
    <title>Consultation Bills - <?= htmlspecialchars($branch_display_name) ?> - Braick Dispensary</title>
    
    <link rel="icon" href="<?= $logo_url ?>" type="image/png">
    <link rel="shortcut icon" href="<?= $logo_url ?>" type="image/png">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <style>
        :root {
            --primary: #0B5ED7;
            --primary-dark: #0A4CA8;
            --primary-light: #3B82F6;
            --primary-bg: #EFF6FF;
            --primary-gradient: linear-gradient(135deg, #0B5ED7, #0A4CA8);
            --primary-gradient-strong: linear-gradient(135deg, #0A4CA8, #083C8A);
            
            --blue: #0B5ED7;
            --blue-dark: #0A4CA8;
            --blue-light: #3B82F6;
            
            --danger: #DC2626;
            --danger-dark: #B91C1C;
            --danger-light: #F87171;
            --danger-bg: #FEE2E2;
            
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
            
            --bg-body: #EFF6FF;
            --bg-card: #FFFFFF;
            --bg-nav: #FFFFFF;
            --text-primary: #1E293B;
            --text-secondary: #64748B;
            --border-color: #BFDBFE;
            --radius: 12px;
            --radius-lg: 18px;
            --table-hover: #EFF6FF;
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
            --blue: #3B82F6;
            --blue-dark: #2563EB;
            --table-hover: #1A2A4A;
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
            box-shadow: 0 8px 32px rgba(11, 94, 215, 0.35);
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
        
        .page-header .header-badge.consult {
            background: rgba(147, 51, 234, 0.25);
            border-color: rgba(147, 51, 234, 0.3);
            color: #C084FC;
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
           STATS CARDS
           ================================================================ */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 14px;
            margin-bottom: 24px;
        }
        
        .stat-card {
            border-radius: var(--radius);
            padding: 16px 20px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 16px rgba(0,0,0,0.12);
            color: white !important;
            position: relative;
            overflow: hidden;
            border: none;
        }
        
        .stat-card::before {
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
        
        .stat-card::after {
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
        
        .stat-card:hover {
            transform: translateY(-4px) scale(1.01);
            box-shadow: 0 10px 32px rgba(0,0,0,0.2);
        }
        
        .stat-card:hover::before { transform: scale(1.3); right: -10%; }
        .stat-card:hover::after { transform: scale(1.4); bottom: -30%; }
        
        .stat-card .stat-label {
            font-size: 0.6rem;
            color: rgba(255,255,255,0.85);
            text-transform: uppercase;
            letter-spacing: 0.06em;
            font-weight: 600;
            margin: 0 0 2px 0;
            position: relative;
            z-index: 1;
        }
        
        .stat-card .stat-number {
            font-size: 1.8rem;
            font-weight: 800;
            color: white !important;
            line-height: 1.1;
            margin: 0;
            position: relative;
            z-index: 1;
        }
        
        .stat-card .stat-sub {
            font-size: 0.6rem;
            color: rgba(255,255,255,0.9);
            margin: 2px 0 0 0;
            position: relative;
            z-index: 1;
        }
        
        .stat-card .stat-icon-bg {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 3rem;
            color: rgba(255,255,255,0.08);
            z-index: 0;
        }
        
        /* Card Colors */
        .card-blue-dark { background: linear-gradient(135deg, #0B5ED7, #0A4CA8); }
        .card-blue-dark:hover { box-shadow: 0 10px 32px rgba(11, 94, 215, 0.4); }
        
        .card-blue-green { background: linear-gradient(135deg, #059669, #047857); }
        .card-blue-green:hover { box-shadow: 0 10px 32px rgba(5, 150, 105, 0.4); }
        
        .card-blue-orange { background: linear-gradient(135deg, #D97706, #B45309); }
        .card-blue-orange:hover { box-shadow: 0 10px 32px rgba(217, 119, 6, 0.4); }
        
        .card-blue-red { background: linear-gradient(135deg, #DC2626, #B91C1C); }
        .card-blue-red:hover { box-shadow: 0 10px 32px rgba(220, 38, 38, 0.4); }
        
        .card-blue-light { background: linear-gradient(135deg, #3B82F6, #2563EB); }
        .card-blue-light:hover { box-shadow: 0 10px 32px rgba(59, 130, 246, 0.4); }
        
        .card-purple { background: linear-gradient(135deg, #7C3AED, #6D28D9); }
        .card-purple:hover { box-shadow: 0 10px 32px rgba(124, 58, 237, 0.4); }
        
        [data-theme="dark"] .card-blue-dark { background: linear-gradient(135deg, #2563EB, #1D4ED8); }
        [data-theme="dark"] .card-blue-green { background: linear-gradient(135deg, #059669, #047857); }
        [data-theme="dark"] .card-blue-orange { background: linear-gradient(135deg, #D97706, #B45309); }
        [data-theme="dark"] .card-blue-red { background: linear-gradient(135deg, #DC2626, #B91C1C); }
        [data-theme="dark"] .card-blue-light { background: linear-gradient(135deg, #3B82F6, #2563EB); }
        [data-theme="dark"] .card-purple { background: linear-gradient(135deg, #7C3AED, #6D28D9); }
        
        .filter-bar {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            padding: 16px 20px;
            border: 2px solid var(--border-color);
            margin-bottom: 24px;
            box-shadow: var(--shadow-sm);
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
        
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 18px;
            border-radius: var(--radius);
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
        
        .table-container {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            border: 2px solid var(--border-color);
            overflow: hidden;
            box-shadow: var(--shadow-sm);
        }
        
        .table-container .card-header {
            padding: 14px 20px;
            background: var(--primary-gradient-strong);
            border-bottom: 2px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 8px;
        }
        
        .table-container .card-header .card-title {
            font-size: 0.85rem;
            font-weight: 700;
            color: white;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .table-container .card-header .card-title i {
            color: rgba(255,255,255,0.8);
        }
        
        .table-container .card-header .card-action {
            color: rgba(255,255,255,0.7);
            font-size: 0.65rem;
            text-decoration: none;
            transition: all 0.3s;
        }
        
        .table-container .card-header .card-action:hover {
            color: white;
        }
        
        .data-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            font-size: 0.78rem;
        }
        
        .data-table thead th {
            background: var(--bg-body);
            color: var(--text-secondary);
            font-weight: 700;
            padding: 10px 14px;
            font-size: 0.6rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            border-bottom: 2px solid var(--border-color);
            text-align: left;
        }
        
        [data-theme="dark"] .data-table thead th {
            background: #0F172A;
        }
        
        .data-table td {
            padding: 8px 14px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-primary);
            vertical-align: middle;
        }
        
        .data-table tbody tr:hover td {
            background: var(--table-hover);
        }
        
        .data-table tbody tr:last-child td {
            border-bottom: none;
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
        
        .badge-success { background: #059669; }
        .badge-danger { background: #DC2626; }
        .badge-warning { background: #D97706; color: #1E293B; }
        .badge-info { background: #0B5ED7; }
        .badge-secondary { background: #64748B; }
        .badge-blue { background: #0B5ED7; }
        
        [data-theme="dark"] .badge-warning { color: #1E293B; }
        
        .consult-badge {
            background: rgba(124, 58, 237, 0.12);
            color: #7C3AED;
            padding: 2px 10px;
            border-radius: 12px;
            font-size: 0.6rem;
            font-weight: 600;
            display: inline-block;
            border: 1px solid rgba(124, 58, 237, 0.2);
        }
        
        [data-theme="dark"] .consult-badge {
            background: rgba(124, 58, 237, 0.2);
            color: #C084FC;
            border-color: rgba(124, 58, 237, 0.3);
        }
        
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
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-secondary);
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
        
        .text-blue { color: #0B5ED7; }
        .bg-blue { background: #0B5ED7; }
        .border-blue { border-color: #0B5ED7; }
        
        [data-theme="dark"] .text-blue { color: #3B82F6; }
        [data-theme="dark"] .bg-blue { background: #3B82F6; }
        
        @media (max-width: 1024px) {
            .top-nav { left: 0; }
            .main-content { margin-left: 0; padding: 16px; }
            .top-nav .search-wrapper { max-width: 300px; }
            .stats-grid { grid-template-columns: repeat(3, 1fr); }
        }
        
        @media (max-width: 768px) {
            .top-nav .search-wrapper { max-width: 180px; }
            .top-nav .datetime { display: none; }
            .page-header { padding: 16px 18px; }
            .page-header .page-title { font-size: 1.3rem; }
            .stats-grid { grid-template-columns: 1fr 1fr; }
            .filter-bar .filter-label { display: none; }
            .filter-bar select, .filter-bar input { min-width: 100px; }
            .data-table { font-size: 0.65rem; }
            .data-table thead th, .data-table td { padding: 6px 8px; }
        }
        
        @media (max-width: 480px) {
            .main-content { padding: 10px; }
            .stats-grid { grid-template-columns: 1fr; }
            .page-header { flex-direction: column; align-items: flex-start !important; }
            .data-table { font-size: 0.55rem; }
            .data-table thead th, .data-table td { padding: 4px 6px; }
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
            <input type="text" id="searchInput" placeholder="Search bills..." value="<?= htmlspecialchars($search) ?>">
            <button id="searchBtn" class="search-btn">
                <i class="fas fa-search mr-1"></i> Search
            </button>
        </div>
    </div>
    
    <div class="flex items-center gap-3">
        <select id="branchSelector" class="branch-selector" onchange="switchBranch(this.value)">
            <option value="0" <?= $branch_id == 0 ? 'selected' : '' ?>>🌐 All Branches</option>
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

    <!-- ================================================================ -->
    <!-- PAGE HEADER - BLUE THEME -->
    <!-- ================================================================ -->
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-stethoscope"></i>
                Consultation Bills
                <span class="role-badge-display">ADMIN</span>
                <span class="consult-badge">
                    <i class="fas fa-filter"></i> Consultations Only
                </span>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-store-alt"></i>
                <strong><?= htmlspecialchars($branch_display_name) ?></strong>
                <span class="header-badge">
                    <i class="fas fa-file-invoice"></i> <?= number_format($total_bills) ?> Bills
                </span>
                <span class="header-badge" style="background:rgba(59,130,246,0.2);border-color:rgba(59,130,246,0.3);color:#60A5FA;">
                    <i class="fas fa-money-bill-wave"></i> <?= formatCurrency($total_consultation_revenue) ?> Revenue
                </span>
                <span class="header-badge consult" style="background:rgba(147,51,234,0.25);border-color:rgba(147,51,234,0.3);color:#C084FC;">
                    <i class="fas fa-user-md"></i> <?= number_format($total_consultation_revenue > 0 ? round(($total_consultation_revenue / max($total_bill_amount, 1)) * 100, 1) : 0) ?>% Consult
                </span>
            </p>
        </div>
        <div style="position:relative;z-index:1;">
            <a href="view_cashier.php?id=<?= $branch_id ?>&branch=<?= $branch_id ?>" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- SUMMARY STATS -->
    <!-- ================================================================ -->
    <div class="stats-grid animate-fade-in-up" style="animation-delay:0.05s;">
        
        <div class="stat-card card-blue-dark">
            <div class="stat-icon-bg"><i class="fas fa-file-invoice"></i></div>
            <p class="stat-label"><i class="fas fa-file-invoice mr-1"></i> Total Bills</p>
            <p class="stat-number"><?= number_format($total_bills) ?></p>
            <p class="stat-sub">All consultation bills</p>
        </div>
        
        <div class="stat-card card-blue-green">
            <div class="stat-icon-bg"><i class="fas fa-check-circle"></i></div>
            <p class="stat-label"><i class="fas fa-check-circle mr-1"></i> Paid</p>
            <p class="stat-number"><?= number_format($total_paid) ?></p>
            <p class="stat-sub"><?= $total_bills > 0 ? round(($total_paid / $total_bills) * 100, 1) : 0 ?>% of total</p>
        </div>
        
        <div class="stat-card card-blue-orange">
            <div class="stat-icon-bg"><i class="fas fa-clock"></i></div>
            <p class="stat-label"><i class="fas fa-clock mr-1"></i> Pending</p>
            <p class="stat-number"><?= number_format($total_pending) ?></p>
            <p class="stat-sub">Awaiting payment</p>
        </div>
        
        <div class="stat-card card-blue-red">
            <div class="stat-icon-bg"><i class="fas fa-times-circle"></i></div>
            <p class="stat-label"><i class="fas fa-times-circle mr-1"></i> Cancelled</p>
            <p class="stat-number"><?= number_format($total_cancelled) ?></p>
            <p class="stat-sub">Voided transactions</p>
        </div>
        
        <div class="stat-card card-purple">
            <div class="stat-icon-bg"><i class="fas fa-money-bill-wave"></i></div>
            <p class="stat-label"><i class="fas fa-money-bill-wave mr-1"></i> Consult Revenue</p>
            <p class="stat-number"><?= formatCurrency($total_consultation_revenue) ?></p>
            <p class="stat-sub">From consultation items only</p>
        </div>
        
    </div>

    <!-- ================================================================ -->
    <!-- FILTER BAR -->
    <!-- ================================================================ -->
    <div class="filter-bar animate-fade-in-up" style="animation-delay:0.1s;">
        <span class="filter-label"><i class="fas fa-filter"></i> Filter</span>
        <form method="GET" class="flex flex-wrap gap-3 items-center w-full">
            <input type="hidden" name="branch" value="<?= $branch_id ?>">
            
            <select name="status" class="flex-1 min-w-[150px]">
                <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>All Status</option>
                <option value="pending" <?= $status_filter === 'pending' ? 'selected' : '' ?>>Pending</option>
                <option value="paid" <?= $status_filter === 'paid' ? 'selected' : '' ?>>Paid</option>
                <option value="partial" <?= $status_filter === 'partial' ? 'selected' : '' ?>>Partial</option>
                <option value="cancelled" <?= $status_filter === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
            </select>
            
            <input type="date" name="from_date" value="<?= htmlspecialchars($from_date) ?>" class="min-w-[150px]">
            <span class="text-gray-400">to</span>
            <input type="date" name="to_date" value="<?= htmlspecialchars($to_date) ?>" class="min-w-[150px]">
            
            <input type="text" name="search" placeholder="Search bill # or patient..." value="<?= htmlspecialchars($search) ?>" class="flex-1 min-w-[200px]">
            
            <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Apply</button>
            <a href="consultation_bills.php?branch=<?= $branch_id ?>" class="btn btn-outline"><i class="fas fa-times"></i> Reset</a>
        </form>
    </div>

    <!-- ================================================================ -->
    <!-- TABLE -->
    <!-- ================================================================ -->
    <div class="table-container animate-fade-in-up" style="animation-delay:0.15s;">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-stethoscope"></i>
                Consultation Bills (<?= number_format($total_bills) ?>)
                <span style="font-size:0.6rem;opacity:0.7;font-weight:400;margin-left:4px;">
                    <i class="fas fa-filter"></i> Only consultation items
                </span>
            </h3>
            <div class="flex items-center gap-3">
                <span class="card-action">
                    <i class="fas fa-calendar-alt"></i> 
                    <?= !empty($from_date) ? date('M d, Y', strtotime($from_date)) : 'All' ?> 
                    - 
                    <?= !empty($to_date) ? date('M d, Y', strtotime($to_date)) : 'Now' ?>
                </span>
                <button onclick="window.print()" class="card-action">
                    <i class="fas fa-print"></i> Print
                </button>
            </div>
        </div>
        
        <?php if (count($consultation_bills) > 0): ?>
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Bill #</th>
                            <th>Patient</th>
                            <th>Branch</th>
                            <th>Consultation Items</th>
                            <th>Consult Total</th>
                            <th>Bill Total</th>
                            <th>Paid</th>
                            <th>Balance</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($consultation_bills as $bill): ?>
                            <tr>
                                <td class="font-mono text-xs font-semibold text-blue">
                                    <?= htmlspecialchars($bill['bill_number'] ?? 'N/A') ?>
                                </td>
                                <td>
                                    <div class="font-medium"><?= htmlspecialchars($bill['patient_name'] ?? 'N/A') ?></div>
                                    <div class="text-xs text-gray-400"><?= htmlspecialchars($bill['patient_phone'] ?? '') ?></div>
                                </td>
                                <td>
                                    <div class="font-medium text-sm"><?= htmlspecialchars($bill['branch_name'] ?? 'N/A') ?></div>
                                    <div class="text-xs text-gray-400"><?= htmlspecialchars($bill['branch_location'] ?? '') ?></div>
                                </td>
                                <td>
                                    <div class="font-medium"><?= number_format($bill['consultation_count'] ?? 0) ?> items</div>
                                    <div class="text-xs text-gray-400 truncate max-w-[150px]" title="<?= htmlspecialchars($bill['consultation_items'] ?? '') ?>">
                                        <?= htmlspecialchars($bill['consultation_items'] ?? '') ?>
                                    </div>
                                </td>
                                <td class="font-semibold text-purple-600 dark:text-purple-400">
                                    <?= formatCurrency($bill['consultation_total'] ?? 0) ?>
                                </td>
                                <td class="font-semibold"><?= formatCurrency($bill['total_amount'] ?? 0) ?></td>
                                <td class="text-green-600"><?= formatCurrency($bill['paid_amount'] ?? 0) ?></td>
                                <td>
                                    <?php if (($bill['balance'] ?? 0) > 0): ?>
                                        <span class="text-red-600 font-semibold"><?= formatCurrency($bill['balance'] ?? 0) ?></span>
                                    <?php else: ?>
                                        <span class="text-green-600"><?= formatCurrency(0) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge badge-<?= getStatusBadge($bill['status'] ?? 'pending') ?>">
                                        <i class="fas <?= getStatusIcon($bill['status'] ?? 'pending') ?>"></i>
                                        <?= ucfirst($bill['status'] ?? 'Pending') ?>
                                    </span>
                                </td>
                                <td class="text-xs">
                                    <?= date('M d, Y', strtotime($bill['created_at'] ?? 'now')) ?>
                                    <div class="text-gray-400 text-[0.5rem]">
                                        <?= date('h:i A', strtotime($bill['created_at'] ?? 'now')) ?>
                                    </div>
                                </td>
                                <td>
                                    <a href="view_bill.php?id=<?= $bill['id'] ?>&branch=<?= $branch_id ?>" 
                                       class="text-blue text-xs hover:underline font-semibold">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Summary row -->
            <div style="padding:10px 20px;background:var(--bg-body);border-top:2px solid var(--border-color);display:flex;justify-content:space-between;flex-wrap:wrap;gap:8px;font-size:0.75rem;">
                <div>
                    <span class="font-semibold">Total Bills:</span> <?= number_format($total_bills) ?>
                    <span class="mx-2 text-gray-400">|</span>
                    <span class="font-semibold">Consult Revenue:</span> <?= formatCurrency($total_consultation_revenue) ?>
                    <?php if ($branch_id > 0): ?>
                        <span class="mx-2 text-gray-400">|</span>
                        <span class="font-semibold">Branch:</span> <?= htmlspecialchars($branch_name) ?>
                    <?php endif; ?>
                </div>
                <div class="text-gray-500">
                    <i class="fas fa-info-circle"></i> Using <strong class="text-blue">bills</strong> table with <strong class="text-blue">bill_items</strong> join
                </div>
            </div>
            
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-stethoscope"></i>
                <h3>No Consultation Bills Found</h3>
                <p>Try adjusting your filters or <a href="consultation_bills.php?branch=<?= $branch_id ?>" class="text-blue hover:underline">reset all filters</a></p>
            </div>
        <?php endif; ?>
    </div>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Consultation Bills - <?= htmlspecialchars($branch_display_name) ?>
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

    function switchBranch(branchId) {
        var url = new URL(window.location.href);
        url.searchParams.set('branch', branchId);
        url.searchParams.delete('status');
        url.searchParams.delete('search');
        url.searchParams.delete('from_date');
        url.searchParams.delete('to_date');
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

    console.log('%c🔵 Braick Dispensary - Consultation Bills', 'font-size:18px; font-weight:bold; color:#7C3AED;');
    console.log('%c✅ USING: bills table (NOT patient_bills)', 'font-size:13px; color:#34D399;');
    console.log('%c✅ JOIN: bill_items WHERE item_type = "consultation"', 'font-size:13px; color:#34D399;');
    console.log('%c🏢 Branch Display: <?= htmlspecialchars($branch_display_name) ?> (Filter ID: <?= $branch_id ?>)', 'font-size:13px; color:#0B5ED7;');
    console.log('%c📊 Total Bills: <?= number_format($total_bills) ?>', 'font-size:13px; color:#0B5ED7;');
    console.log('%c💰 Consult Revenue: <?= formatCurrency($total_consultation_revenue) ?>', 'font-size:13px; color:#7C3AED;');
</script>

</body>
</html>