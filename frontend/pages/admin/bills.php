<?php
// ================================================================
// FILE: frontend/pages/admin/bills.php
// ADMIN - VIEW ALL BILLS
// BRAICK DISPENSARY - GREEN THEME
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
// GET FILTER PARAMETERS
// ================================================================
$selected_branch_id = $_GET['branch'] ?? $_GET['branch_id'] ?? 'all';
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? 'all';
$patient_filter = isset($_GET['patient_id']) ? (int)$_GET['patient_id'] : 0;

// ================================================================
// BUILD QUERY WITH FILTERS
// ================================================================
$query = "
    SELECT 
        pb.*,
        p.full_name as patient_name,
        p.patient_id as patient_code,
        p.phone as patient_phone,
        u.full_name as created_by_name,
        b.name as branch_name,
        (SELECT COUNT(*) FROM bill_items WHERE bill_id = pb.id AND status != 'cancelled') as items_count,
        (SELECT COUNT(*) FROM bill_items WHERE bill_id = pb.id AND is_paid = 1 AND status != 'cancelled') as paid_items_count,
        (SELECT COUNT(*) FROM bill_items WHERE bill_id = pb.id AND (is_paid = 0 OR is_paid IS NULL) AND status != 'cancelled') as pending_items_count,
        v.visit_number,
        v.visit_type
    FROM patient_bills pb
    LEFT JOIN patients p ON pb.patient_id = p.id
    LEFT JOIN users u ON pb.created_by = u.id
    LEFT JOIN branches b ON pb.branch_id = b.id
    LEFT JOIN visits v ON pb.visit_id = v.id
    WHERE 1=1
";

$params = [];

// Branch filter
if ($selected_branch_id !== 'all' && is_numeric($selected_branch_id)) {
    $query .= " AND pb.branch_id = ?";
    $params[] = (int)$selected_branch_id;
}

// Status filter
if ($status_filter !== 'all') {
    $query .= " AND pb.status = ?";
    $params[] = $status_filter;
}

// Patient filter
if ($patient_filter > 0) {
    $query .= " AND pb.patient_id = ?";
    $params[] = $patient_filter;
}

// Search filter
if (!empty($search)) {
    $query .= " AND (pb.bill_number LIKE ? OR p.full_name LIKE ? OR p.patient_id LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

$query .= " ORDER BY pb.created_at DESC";

// ================================================================
// EXECUTE QUERY
// ================================================================
$bills = [];
try {
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $bills = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching bills: " . $e->getMessage());
    $bills = [];
}

// ================================================================
// CALCULATE SUMMARY
// ================================================================
$total_bills = count($bills);
$total_amount = 0;
$total_paid = 0;
$total_balance = 0;
$total_pending = 0;
$total_partial = 0;
$total_paid_bills = 0;

foreach ($bills as $bill) {
    $total_amount += (float)$bill['total_amount'];
    $total_paid += (float)$bill['paid_amount'];
    $total_balance += (float)$bill['balance'];
    
    if ($bill['status'] === 'paid') {
        $total_paid_bills++;
    } elseif ($bill['status'] === 'partial') {
        $total_partial++;
    } elseif ($bill['status'] === 'pending') {
        $total_pending++;
    }
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
// STATUS BADGE CLASS
// ================================================================
function getStatusBadge($status) {
    $classes = [
        'active' => 'success',
        'inactive' => 'danger',
        'pending' => 'warning',
        'paid' => 'success',
        'partial' => 'warning',
        'cancelled' => 'danger',
        'completed' => 'success'
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
        'completed' => 'fa-check-circle'
    ];
    return $icons[$status] ?? 'fa-circle';
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
    <title>Bills - Braick Dispensary</title>
    
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
           PAGE HEADER - GREEN THEME
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
           STATS ROW - GREEN THEME
           ================================================================ */
        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }
        
        .stat-card {
            background: var(--bg-card);
            border-radius: var(--radius);
            padding: 16px 20px;
            border: 2px solid var(--border-color);
            box-shadow: var(--shadow-sm);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 5px;
            height: 100%;
            background: var(--primary-gradient-strong);
            border-radius: 0 3px 3px 0;
            opacity: 0.8;
        }
        
        .stat-card:hover {
            border-color: var(--primary);
            transform: translateY(-4px);
            box-shadow: 0 8px 25px rgba(5, 150, 105, 0.15);
        }
        
        .stat-card .stat-number {
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--text-primary);
            line-height: 1.1;
        }
        
        .stat-card .stat-number.green { color: var(--primary); }
        .stat-card .stat-number.orange { color: #F59E0B; }
        .stat-card .stat-number.purple { color: #7C3AED; }
        .stat-card .stat-number.teal { color: #0D9488; }
        .stat-card .stat-number.red { color: #DC2626; }
        .stat-card .stat-number.blue { color: #0B5ED7; }
        
        .stat-card .stat-label {
            font-size: 0.6rem;
            color: var(--text-secondary);
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin: 0;
        }
        
        .stat-card .stat-icon-small {
            width: 36px;
            height: 36px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.9rem;
        }
        
        .stat-card .stat-icon-small.green { background: var(--primary-bg); color: var(--primary); }
        .stat-card .stat-icon-small.orange { background: #FFFBEB; color: #F59E0B; }
        .stat-card .stat-icon-small.purple { background: #F5F3FF; color: #7C3AED; }
        .stat-card .stat-icon-small.teal { background: #ECFDF5; color: #0D9488; }
        .stat-card .stat-icon-small.red { background: #FEF2F2; color: #DC2626; }
        .stat-card .stat-icon-small.blue { background: #EFF6FF; color: #0B5ED7; }
        
        [data-theme="dark"] .stat-card .stat-icon-small.green { background: #1A3A2A; color: #34D399; }
        [data-theme="dark"] .stat-card .stat-icon-small.orange { background: #3D2E0A; color: #FBBF24; }
        [data-theme="dark"] .stat-card .stat-icon-small.purple { background: #2D1B4E; color: #A78BFA; }
        [data-theme="dark"] .stat-card .stat-icon-small.teal { background: #0F3D3D; color: #5EEAD4; }
        [data-theme="dark"] .stat-card .stat-icon-small.red { background: #3A1A1A; color: #F87171; }
        [data-theme="dark"] .stat-card .stat-icon-small.blue { background: #1E3A5F; color: #3B82F6; }
        
        /* ================================================================
           FILTER BAR - GREEN THEME
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
           TABLE CONTAINER - GREEN THEME
           ================================================================ */
        .table-container {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            border: 2px solid var(--border-color);
            overflow: hidden;
            box-shadow: var(--shadow-sm);
            margin-bottom: 24px;
        }
        
        .table-container:hover {
            box-shadow: var(--shadow-md);
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
        
        .table-container .card-header .card-badge {
            background: rgba(255,255,255,0.15);
            color: white;
            padding: 2px 12px;
            border-radius: 20px;
            font-size: 0.65rem;
        }
        
        /* ================================================================
           DATA TABLE - GREEN THEME
           ================================================================ */
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
            padding: 12px 16px;
            font-size: 0.6rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            border-bottom: 2px solid var(--border-color);
            text-align: left;
            position: sticky;
            top: 0;
            z-index: 5;
        }
        
        [data-theme="dark"] .data-table thead th {
            background: #0F172A;
        }
        
        .data-table tbody td {
            padding: 10px 16px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-primary);
            vertical-align: middle;
            transition: background 0.2s ease;
        }
        
        .data-table tbody tr {
            transition: all 0.2s ease;
        }
        
        .data-table tbody tr:hover td {
            background: var(--table-hover);
        }
        
        [data-theme="dark"] .data-table tbody tr:hover td {
            background: #1A3A2A;
        }
        
        .data-table tbody tr:last-child td {
            border-bottom: none;
        }
        
        .data-table tbody tr:nth-child(even) {
            background: var(--gray-50);
        }
        
        [data-theme="dark"] .data-table tbody tr:nth-child(even) {
            background: #1A3A2A;
        }
        
        .data-table tfoot {
            background: var(--primary-bg);
            font-weight: 700;
            border-top: 3px solid var(--primary);
        }
        
        .data-table tfoot td {
            padding: 10px 16px;
            color: var(--text-primary);
        }
        
        .data-table tfoot .total-label {
            text-align: right;
            color: var(--text-secondary);
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.7rem;
            letter-spacing: 0.05em;
        }
        
        .data-table tfoot .total-amount {
            font-family: monospace;
            font-size: 0.95rem;
            font-weight: 700;
        }
        
        .data-table tfoot .total-amount.green { color: var(--primary); }
        .data-table tfoot .total-amount.red { color: var(--danger); }
        .data-table tfoot .total-amount.blue { color: #0B5ED7; }
        
        /* ================================================================
           BADGES - GREEN THEME
           ================================================================ */
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 3px 12px;
            border-radius: 20px;
            font-size: 0.6rem;
            font-weight: 600;
            color: white;
            letter-spacing: 0.02em;
            transition: all 0.3s ease;
        }
        
        .badge:hover {
            transform: scale(1.05);
        }
        
        .badge-success { background: #059669; }
        .badge-danger { background: #DC2626; }
        .badge-warning { background: #D97706; color: #1E293B; }
        .badge-info { background: #0B5ED7; }
        .badge-secondary { background: #64748B; }
        .badge-purple { background: #7C3AED; }
        .badge-teal { background: #0D9488; }
        
        [data-theme="dark"] .badge-warning { color: #1E293B; }
        
        /* ================================================================
           ACTION BUTTONS - NO PAY BUTTON
           ================================================================ */
        .action-buttons {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
            align-items: center;
        }
        
        .btn-action {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 12px;
            border-radius: 6px;
            font-weight: 600;
            font-size: 0.65rem;
            transition: all 0.3s ease;
            cursor: pointer;
            border: none;
            text-decoration: none;
            white-space: nowrap;
            font-family: inherit;
            position: relative;
            overflow: hidden;
        }
        
        .btn-action::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            border-radius: 50%;
            background: rgba(255,255,255,0.2);
            transform: translate(-50%, -50%);
            transition: width 0.6s, height 0.6s;
        }
        
        .btn-action:active::after {
            width: 200px;
            height: 200px;
        }
        
        .btn-action i {
            font-size: 0.6rem;
        }
        
        .btn-view {
            background: #E8F0FE;
            color: #0B5ED7;
            border: 1px solid rgba(11, 94, 215, 0.15);
        }
        
        .btn-view:hover {
            background: #0B5ED7;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3);
        }
        
        .btn-print {
            background: #64748B;
            color: white;
            border: 1px solid rgba(100, 116, 139, 0.15);
        }
        
        .btn-print:hover {
            background: #475569;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(100, 116, 139, 0.3);
        }
        
        .btn-edit {
            background: #FFFBEB;
            color: #D97706;
            border: 1px solid rgba(217, 119, 6, 0.15);
        }
        
        .btn-edit:hover {
            background: #D97706;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(217, 119, 6, 0.3);
        }
        
        /* btn-pay imeondolewa kabisa */
        
        [data-theme="dark"] .btn-view {
            background: #1E3A5F;
            color: #6EA8FE;
            border-color: rgba(110, 168, 254, 0.15);
        }
        
        [data-theme="dark"] .btn-view:hover {
            background: #0B5ED7;
            color: white;
        }
        
        [data-theme="dark"] .btn-edit {
            background: #3D2E0A;
            color: #FBBF24;
            border-color: rgba(251, 191, 36, 0.15);
        }
        
        [data-theme="dark"] .btn-edit:hover {
            background: #D97706;
            color: white;
        }
        
        /* ================================================================
           AMOUNT DISPLAY
           ================================================================ */
        .amount-total { 
            font-weight: 700; 
            font-family: monospace;
            color: var(--primary);
        }
        
        .amount-balance { 
            font-weight: 600; 
            font-family: monospace;
        }
        
        .amount-balance.positive { color: var(--danger); }
        .amount-balance.zero { color: var(--success); }
        .amount-paid { color: var(--success); font-weight: 600; font-family: monospace; }
        
        /* ================================================================
           PATIENT CELL
           ================================================================ */
        .patient-cell .patient-name {
            font-weight: 600;
            color: var(--text-primary);
        }
        
        .patient-cell .patient-id {
            font-size: 0.65rem;
            color: var(--text-secondary);
            font-family: monospace;
        }
        
        /* ================================================================
           VISIT CELL
           ================================================================ */
        .visit-cell .visit-number {
            font-size: 0.65rem;
            color: var(--text-secondary);
            font-family: monospace;
        }
        
        /* ================================================================
           ITEMS CELL
           ================================================================ */
        .items-cell .items-count {
            font-weight: 700;
            color: var(--text-primary);
        }
        
        .items-cell .items-detail {
            font-size: 0.55rem;
            display: block;
        }
        
        .items-cell .items-pending { color: var(--warning); }
        .items-cell .items-paid { color: var(--success); }
        
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
            display: block;
            margin-bottom: 12px;
        }
        
        .empty-state .empty-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--text-primary);
        }
        
        .empty-state .empty-sub {
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
            font-weight: 700;
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
            .stats-row { grid-template-columns: 1fr 1fr; }
            .filter-bar { flex-direction: column; align-items: stretch; }
            .data-table { font-size: 0.65rem; }
            .data-table thead th, .data-table tbody td { padding: 6px 8px; }
            .action-buttons { gap: 4px; }
            .btn-action { padding: 3px 8px; font-size: 0.55rem; }
            .btn-action i { font-size: 0.5rem; }
        }
        
        @media (max-width: 480px) {
            .main-content { padding: 10px; }
            .stats-row { grid-template-columns: 1fr; }
            .page-header { flex-direction: column; align-items: flex-start !important; }
            .data-table { font-size: 0.55rem; }
            .data-table thead th, .data-table tbody td { padding: 4px 6px; }
            .action-buttons { flex-direction: column; gap: 3px; }
            .btn-action { width: 100%; justify-content: center; padding: 3px 6px; font-size: 0.5rem; }
            .btn-action i { font-size: 0.45rem; }
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
            .filter-bar, .footer, #sidebarToggle { display: none !important; }
            .main-content { margin: 0; padding: 20px; }
            .table-container { break-inside: avoid; box-shadow: none !important; border: 1px solid #ddd; }
            .page-header {
                background: #059669 !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            .page-title, .page-subtitle, .header-badge, .role-badge-display {
                color: white !important;
            }
            .badge { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
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
            <input type="text" id="searchInput" placeholder="Search bills by #, patient..." value="<?= htmlspecialchars($search) ?>">
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
    <!-- PAGE HEADER - GREEN THEME -->
    <!-- ================================================================ -->
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-file-invoice"></i>
                Bills
                <span class="role-badge-display">ADMIN</span>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-credit-card"></i>
                <strong><?= $total_bills ?></strong> bills found
                <span class="header-badge" style="background:rgba(52,211,153,0.2);border-color:rgba(52,211,153,0.3);color:#34D399;">
                    <i class="fas fa-check-circle"></i> <?= $total_paid_bills ?> Paid
                </span>
                <span class="header-badge" style="background:rgba(217,119,6,0.2);border-color:rgba(217,119,6,0.3);color:#FBBF24;">
                    <i class="fas fa-clock"></i> <?= $total_pending ?> Pending
                </span>
                <span class="header-badge" style="background:rgba(124,58,237,0.2);border-color:rgba(124,58,237,0.3);color:#A78BFA;">
                    <i class="fas fa-hourglass-half"></i> <?= $total_partial ?> Partial
                </span>
            </p>
        </div>
        <div class="flex gap-2 flex-wrap" style="position:relative;z-index:1;">
            <a href="add_bill.php?branch=<?= $selected_branch_id ?>" class="btn-outline-light">
                <i class="fas fa-plus"></i> Add Bill
            </a>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- STATISTICS ROW - GREEN THEME -->
    <!-- ================================================================ -->
    <div class="stats-row animate-fade-in-up">
        <div class="stat-card">
            <div class="flex items-center gap-3">
                <div class="stat-icon-small green"><i class="fas fa-file-invoice"></i></div>
                <div>
                    <p class="stat-label">Total Bills</p>
                    <p class="stat-number green"><?= number_format($total_bills) ?></p>
                </div>
            </div>
        </div>
        <div class="stat-card">
            <div class="flex items-center gap-3">
                <div class="stat-icon-small blue"><i class="fas fa-money-bill-wave"></i></div>
                <div>
                    <p class="stat-label">Total Amount</p>
                    <p class="stat-number blue">TSh <?= number_format($total_amount, 0) ?></p>
                </div>
            </div>
        </div>
        <div class="stat-card">
            <div class="flex items-center gap-3">
                <div class="stat-icon-small purple"><i class="fas fa-hand-holding-usd"></i></div>
                <div>
                    <p class="stat-label">Total Paid</p>
                    <p class="stat-number purple">TSh <?= number_format($total_paid, 0) ?></p>
                </div>
            </div>
        </div>
        <div class="stat-card">
            <div class="flex items-center gap-3">
                <div class="stat-icon-small red"><i class="fas fa-exclamation-triangle"></i></div>
                <div>
                    <p class="stat-label">Total Balance</p>
                    <p class="stat-number red">TSh <?= number_format($total_balance, 0) ?></p>
                </div>
            </div>
        </div>
        <div class="stat-card">
            <div class="flex items-center gap-3">
                <div class="stat-icon-small orange"><i class="fas fa-receipt"></i></div>
                <div>
                    <p class="stat-label">Items</p>
                    <p class="stat-number orange"><?= number_format(array_sum(array_column($bills, 'items_count'))) ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- FILTER BAR - GREEN THEME -->
    <!-- ================================================================ -->
    <div class="filter-bar animate-fade-in-up" style="animation-delay:0.05s;">
        <span class="filter-label"><i class="fas fa-filter"></i> Filter</span>
        
        <form method="GET" action="" class="flex flex-wrap gap-2 items-center w-full">
            <input type="hidden" name="branch" value="<?= htmlspecialchars($selected_branch_id) ?>">
            
            <select name="status" class="flex-1 min-w-[120px]">
                <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>All Status</option>
                <option value="pending" <?= $status_filter === 'pending' ? 'selected' : '' ?>>Pending</option>
                <option value="partial" <?= $status_filter === 'partial' ? 'selected' : '' ?>>Partial</option>
                <option value="paid" <?= $status_filter === 'paid' ? 'selected' : '' ?>>Paid</option>
                <option value="cancelled" <?= $status_filter === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
            </select>
            
            <input type="text" name="search" placeholder="Search by bill #, patient..." 
                   value="<?= htmlspecialchars($search) ?>" class="flex-1 min-w-[180px]">
            
            <button type="submit" class="btn-filter">
                <i class="fas fa-search"></i> Apply
            </button>
            
            <a href="bills.php?branch=<?= urlencode($selected_branch_id) ?>" class="btn-reset">
                <i class="fas fa-times"></i> Reset
            </a>
        </form>
    </div>

    <!-- ================================================================ -->
    <!-- BILLS TABLE - GREEN THEME (NO PAY BUTTON) -->
    <!-- ================================================================ -->
    <div class="table-container animate-fade-in-up" style="animation-delay:0.1s;">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-list"></i>
                Bills
                <span class="card-badge"><?= $total_bills ?></span>
            </h3>
            <span style="font-size:0.65rem;color:rgba(255,255,255,0.7);">
                Showing <?= $total_bills ?> bills
            </span>
        </div>
        <?php if (count($bills) > 0): ?>
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Bill #</th>
                            <th>Patient</th>
                            <th>Visit</th>
                            <th>Items</th>
                            <th>Total</th>
                            <th>Paid</th>
                            <th>Balance</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($bills as $bill): 
                            $balance = (float)$bill['balance'];
                            $total = (float)$bill['total_amount'];
                            $paid = (float)$bill['paid_amount'];
                        ?>
                            <tr>
                                <td class="font-mono text-xs font-semibold text-green-600">
                                    <?= htmlspecialchars($bill['bill_number'] ?? 'N/A') ?>
                                </td>
                                <td class="patient-cell">
                                    <div class="patient-name">
                                        <a href="view_patient.php?id=<?= $bill['patient_id'] ?>&branch=<?= $selected_branch_id ?>" class="text-green-600 hover:underline">
                                            <?= htmlspecialchars($bill['patient_name'] ?? 'N/A') ?>
                                        </a>
                                    </div>
                                    <div class="patient-id"><?= htmlspecialchars($bill['patient_code'] ?? '') ?></div>
                                </td>
                                <td class="visit-cell">
                                    <?php if (!empty($bill['visit_number'])): ?>
                                        <div class="visit-number"><?= htmlspecialchars($bill['visit_number']) ?></div>
                                    <?php else: ?>
                                        <span class="text-xs text-gray-400">—</span>
                                    <?php endif; ?>
                                    <?php if (!empty($bill['visit_type'])): ?>
                                        <span class="badge badge-info" style="font-size:0.5rem;padding:1px 8px;">
                                            <?= ucfirst($bill['visit_type']) ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="items-cell">
                                    <span class="items-count"><?= number_format($bill['items_count'] ?? 0) ?></span>
                                    <?php if (($bill['pending_items_count'] ?? 0) > 0): ?>
                                        <span class="items-detail items-pending">⏳ <?= $bill['pending_items_count'] ?> pending</span>
                                    <?php endif; ?>
                                    <?php if (($bill['paid_items_count'] ?? 0) > 0): ?>
                                        <span class="items-detail items-paid">✅ <?= $bill['paid_items_count'] ?> paid</span>
                                    <?php endif; ?>
                                </td>
                                <td class="amount-total">TSh <?= number_format($total, 0) ?></td>
                                <td class="amount-paid">TSh <?= number_format($paid, 0) ?></td>
                                <td>
                                    <?php if ($balance > 0): ?>
                                        <span class="amount-balance positive">TSh <?= number_format($balance, 0) ?></span>
                                    <?php else: ?>
                                        <span class="amount-balance zero">TSh 0</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge badge-<?= getStatusBadge($bill['status'] ?? 'pending') ?>">
                                        <i class="fas <?= getStatusIcon($bill['status'] ?? 'pending') ?>"></i>
                                        <?= ucfirst($bill['status'] ?? 'Pending') ?>
                                    </span>
                                </td>
                                <td class="text-xs text-gray-500">
                                    <?= date('M d, Y', strtotime($bill['created_at'] ?? 'now')) ?>
                                    <span class="block text-[10px]"><?= date('h:i A', strtotime($bill['created_at'] ?? 'now')) ?></span>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="view_bill.php?id=<?= $bill['id'] ?>&branch=<?= $selected_branch_id ?>" class="btn-action btn-view" title="View Bill">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                        <?php if ($bill['status'] === 'paid'): ?>
                                            <a href="print_bill.php?id=<?= $bill['id'] ?>&branch=<?= $selected_branch_id ?>" target="_blank" class="btn-action btn-print" title="Print Bill">
                                                <i class="fas fa-print"></i> Print
                                            </a>
                                        <?php endif; ?>
                                        <a href="edit_bill.php?id=<?= $bill['id'] ?>&branch=<?= $selected_branch_id ?>" class="btn-action btn-edit" title="Edit Bill">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <!-- Pay button imetolewa -->
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="4" class="total-label">TOTAL:</td>
                            <td class="total-amount green">TSh <?= number_format($total_amount, 0) ?></td>
                            <td class="total-amount blue">TSh <?= number_format($total_paid, 0) ?></td>
                            <td class="total-amount red">TSh <?= number_format($total_balance, 0) ?></td>
                            <td colspan="3"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-file-invoice"></i>
                <div class="empty-title">No Bills Found</div>
                <div class="empty-sub">
                    <?php if (!empty($search)): ?>
                        No bills match your search criteria
                    <?php elseif ($status_filter !== 'all'): ?>
                        No bills with status "<?= ucfirst($status_filter) ?>"
                    <?php else: ?>
                        Try adjusting your filters
                    <?php endif; ?>
                </div>
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
            Bills
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
        var url = 'bills.php?branch=' + encodeURIComponent(branch) + '&status=' + encodeURIComponent(status);
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

    console.log('%c📄 Braick Dispensary - Bills (GREEN THEME)', 'font-size:18px; font-weight:bold; color:#059669;');
    console.log('%c👤 Admin: <?= htmlspecialchars($user_full_name) ?>', 'font-size:13px; color:#059669;');
    console.log('%c🔒 Login protection: ACTIVE', 'font-size:13px; color:#0B5ED7;');
    console.log('%c💰 Total Bills: <?= $total_bills ?>', 'font-size:13px; color:#059669;');
    console.log('%c💵 Total Amount: TSh <?= number_format($total_amount, 0) ?>', 'font-size:13px; color:#0B5ED7;');
    console.log('%c📊 Total Balance: TSh <?= number_format($total_balance, 0) ?>', 'font-size:13px; color:#DC2626;');
    console.log('%c✅ Paid: <?= $total_paid_bills ?> | ⏳ Pending: <?= $total_pending ?> | 🔄 Partial: <?= $total_partial ?>', 'font-size:13px; color:#64748B;');
    console.log('%c🟢 Green Theme Applied', 'font-size:13px; color:#059669;');
    console.log('%c❌ Pay button removed - Only View, Print, Edit', 'font-size:13px; color:#DC2626;');
</script>

</body>
</html>