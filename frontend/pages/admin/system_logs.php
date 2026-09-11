<?php
// ================================================================
// FILE: frontend/pages/admin/system_logs.php
// ADMIN - SYSTEM ACTIVITY LOGS
// With embedded header (like admin) + auto-search filter
// Smaller header search bar
// ================================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: ../login.php');
    exit;
}

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

$user_id = $_SESSION['user_id'] ?? 0;
$user_full_name = $_SESSION['full_name'] ?? 'Admin';
$user_role = $_SESSION['role'] ?? 'admin';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$user_username = $_SESSION['username'] ?? 'admin';
$profile_pic = $_SESSION['profile_pic'] ?? '';
$user_is_online = $_SESSION['is_online'] ?? 1;

$selected_branch_id = isset($_GET['branch']) ? trim($_GET['branch']) : 'all';
if ($selected_branch_id === 'all') {
    $branch_id_for_query = null;
} else {
    $branch_id_for_query = (int)$selected_branch_id;
}

require_once __DIR__ . '/../../../backend/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

// ================================================================
// GET BRANCHES
// ================================================================
$branches = [];
try {
    $stmt = $db->query("SELECT id, name FROM branches WHERE status = 'active' ORDER BY name");
    $branches = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $branches = []; }

// ================================================================
// SIDEBAR STATISTICS
// ================================================================
$total_employees_sidebar = 0;
$total_doctors_sidebar = 0;
$total_branches_sidebar = 0;
$module_counts = ['pharmacy' => 0, 'reception' => 0, 'laboratory' => 0, 'cashier' => 0];
$total_patients_sidebar = 0;
$today_patients_sidebar = 0;
$total_services_sidebar = 0;
$today_services_sidebar = 0;
$pending_prescriptions_sidebar = 0;
$pending_lab_tests_sidebar = 0;

try {
    $stats_branch = $selected_branch_id;
    
    if ($stats_branch === 'all') {
        $stmt = $db->query("SELECT COUNT(*) as count FROM users WHERE role != 'admin' AND status = 'active'");
    } else {
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM users WHERE role != 'admin' AND status = 'active' AND branch_id = ?");
        $stmt->execute([(int)$stats_branch]);
    }
    $total_employees_sidebar = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
    
    if ($stats_branch === 'all') {
        $stmt = $db->query("SELECT COUNT(*) as count FROM users WHERE role = 'doctor' AND status = 'active'");
    } else {
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM users WHERE role = 'doctor' AND status = 'active' AND branch_id = ?");
        $stmt->execute([(int)$stats_branch]);
    }
    $total_doctors_sidebar = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
    
    $modules = ['pharmacy', 'reception', 'laboratory', 'cashier'];
    foreach ($modules as $module) {
        if ($stats_branch === 'all') {
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM users WHERE role = ? AND status = 'active'");
            $stmt->execute([$module]);
        } else {
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM users WHERE role = ? AND status = 'active' AND branch_id = ?");
            $stmt->execute([$module, (int)$stats_branch]);
        }
        $module_counts[$module] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
    }
    
    if ($stats_branch === 'all') {
        $stmt = $db->query("SELECT COUNT(*) as count FROM patients");
    } else {
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM patients WHERE branch_id = ?");
        $stmt->execute([(int)$stats_branch]);
    }
    $total_patients_sidebar = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
    
    if ($stats_branch === 'all') {
        $stmt = $db->query("SELECT COUNT(*) as count FROM patients WHERE DATE(created_at) = CURDATE()");
    } else {
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM patients WHERE branch_id = ? AND DATE(created_at) = CURDATE()");
        $stmt->execute([(int)$stats_branch]);
    }
    $today_patients_sidebar = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
    
    if ($stats_branch === 'all') {
        $stmt = $db->query("SELECT COUNT(*) as count FROM bill_items WHERE status != 'cancelled'");
    } else {
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM bill_items WHERE branch_id = ? AND status != 'cancelled'");
        $stmt->execute([(int)$stats_branch]);
    }
    $total_services_sidebar = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
    
    if ($stats_branch === 'all') {
        $stmt = $db->query("SELECT COUNT(*) as count FROM bill_items WHERE status != 'cancelled' AND DATE(created_at) = CURDATE()");
    } else {
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM bill_items WHERE branch_id = ? AND status != 'cancelled' AND DATE(created_at) = CURDATE()");
        $stmt->execute([(int)$stats_branch]);
    }
    $today_services_sidebar = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
    
    if ($stats_branch === 'all') {
        $stmt = $db->query("SELECT COUNT(*) as count FROM prescriptions WHERE status IN ('pending', 'confirmed')");
    } else {
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM prescriptions WHERE branch_id = ? AND status IN ('pending', 'confirmed')");
        $stmt->execute([(int)$stats_branch]);
    }
    $pending_prescriptions_sidebar = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
    
    if ($stats_branch === 'all') {
        $stmt = $db->query("SELECT COUNT(*) as count FROM lab_tests WHERE status IN ('pending', 'in_progress')");
    } else {
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM lab_tests WHERE branch_id = ? AND status IN ('pending', 'in_progress')");
        $stmt->execute([(int)$stats_branch]);
    }
    $pending_lab_tests_sidebar = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
    
    $stmt = $db->query("SELECT COUNT(*) as count FROM branches WHERE status = 'active'");
    $total_branches_sidebar = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
} catch (Exception $e) {}

// ================================================================
// FILTERS
// ================================================================
$action_filter = isset($_GET['action_filter']) ? trim($_GET['action_filter']) : '';
$user_filter = isset($_GET['user_filter']) ? (int)$_GET['user_filter'] : 0;
$date_from = isset($_GET['date_from']) ? trim($_GET['date_from']) : '';
$date_to = isset($_GET['date_to']) ? trim($_GET['date_to']) : '';

// ================================================================
// BUILD WHERE
// ================================================================
$where = "1=1";
$params = [];

if (!empty($action_filter)) {
    $where .= " AND al.action = ?";
    $params[] = $action_filter;
}

if ($user_filter > 0) {
    $where .= " AND al.user_id = ?";
    $params[] = $user_filter;
}

if (!empty($date_from)) {
    $where .= " AND DATE(al.created_at) >= ?";
    $params[] = $date_from;
}

if (!empty($date_to)) {
    $where .= " AND DATE(al.created_at) <= ?";
    $params[] = $date_to;
}

if ($selected_branch_id !== 'all' && is_numeric($selected_branch_id)) {
    $where .= " AND al.branch_id = ?";
    $params[] = (int)$selected_branch_id;
}

// ================================================================
// FETCH ALL LOGS (NO PAGINATION)
// ================================================================
$logs = [];
try {
    $sql = "
        SELECT al.*, 
               u.full_name as user_full_name, 
               u.username as user_username,
               u.role as user_role,
               b.name as branch_name,
               p.full_name as patient_name
        FROM activity_logs al
        LEFT JOIN users u ON al.user_id = u.id
        LEFT JOIN branches b ON al.branch_id = b.id
        LEFT JOIN patients p ON al.patient_id = p.id
        WHERE $where
        ORDER BY al.created_at DESC
        LIMIT 1000
    ";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Logs query error: " . $e->getMessage());
}

$total_logs = count($logs);

// ================================================================
// GET UNIQUE ACTIONS AND USERS
// ================================================================
$unique_actions = [];
try {
    $stmt = $db->query("SELECT DISTINCT action FROM activity_logs WHERE action IS NOT NULL AND action != '' ORDER BY action");
    $unique_actions = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {}

$unique_users = [];
try {
    $stmt = $db->query("
        SELECT DISTINCT u.id, u.full_name, u.username 
        FROM activity_logs al 
        JOIN users u ON al.user_id = u.id 
        ORDER BY u.full_name
    ");
    $unique_users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// ================================================================
// STATISTICS
// ================================================================
$today_logs_count = 0;
$week_logs_count = 0;
$month_logs_count = 0;
$total_activity_count = 0;

try {
    $branch_cond = ($selected_branch_id !== 'all' && is_numeric($selected_branch_id)) ? " AND branch_id = " . (int)$selected_branch_id : "";
    
    $stmt = $db->query("SELECT COUNT(*) as c FROM activity_logs WHERE DATE(created_at) = CURDATE()" . $branch_cond);
    $today_logs_count = (int)($stmt->fetch(PDO::FETCH_ASSOC)['c'] ?? 0);
    
    $stmt = $db->query("SELECT COUNT(*) as c FROM activity_logs WHERE YEARWEEK(created_at) = YEARWEEK(CURDATE())" . $branch_cond);
    $week_logs_count = (int)($stmt->fetch(PDO::FETCH_ASSOC)['c'] ?? 0);
    
    $stmt = $db->query("SELECT COUNT(*) as c FROM activity_logs WHERE MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())" . $branch_cond);
    $month_logs_count = (int)($stmt->fetch(PDO::FETCH_ASSOC)['c'] ?? 0);
    
    $stmt = $db->query("SELECT COUNT(*) as c FROM activity_logs WHERE 1=1" . $branch_cond);
    $total_activity_count = (int)($stmt->fetch(PDO::FETCH_ASSOC)['c'] ?? 0);
} catch (Exception $e) {}

// ================================================================
// PROFILE & LOGO
// ================================================================
$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';
$logo_path = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.PNG';

$display_branch_name = 'All Branches';
if ($selected_branch_id !== 'all' && is_numeric($selected_branch_id)) {
    foreach ($branches as $b) {
        if ($b['id'] == $selected_branch_id) {
            $display_branch_name = $b['name'];
            break;
        }
    }
}

$unread_notifications = 0;
try {
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$user_id]);
    $unread_notifications = (int)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
} catch (Exception $e) {}

$is_dark_mode = isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= $is_dark_mode ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Logs - Braick Dispensary</title>
    
    <link rel="icon" href="<?= $logo_path ?>" type="image/png">
    <link rel="shortcut icon" href="<?= $logo_path ?>" type="image/png">
    <link rel="apple-touch-icon" href="<?= $logo_path ?>">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <style>
        :root {
            --primary: #0B5ED7;
            --primary-dark: #0A4CA8;
            --primary-light: #E8F0FE;
            --success: #059669;
            --success-light: #D1FAE5;
            --warning: #D97706;
            --warning-light: #FEF3C7;
            --danger: #DC2626;
            --danger-light: #FEE2E2;
            --purple: #7C3AED;
            --purple-light: #EDE9FE;
            --bg-body: #F1F5F9;
            --bg-card: #FFFFFF;
            --bg-nav: #FFFFFF;
            --border-color: #E2E8F0;
            --text-primary: #1E293B;
            --text-secondary: #64748B;
            --text-muted: #94A3B8;
        }
        
        [data-theme="dark"] {
            --bg-body: #0F172A;
            --bg-card: #1E293B;
            --bg-nav: #1E293B;
            --border-color: #334155;
            --text-primary: #F1F5F9;
            --text-secondary: #94A3B8;
            --text-muted: #64748B;
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Inter', 'Segoe UI', -apple-system, sans-serif;
            background: var(--bg-body);
            color: var(--text-primary);
            transition: background 0.3s, color 0.3s;
        }
        
        ::-webkit-scrollbar { width: 5px; height: 5px; }
        ::-webkit-scrollbar-track { background: var(--bg-body); }
        ::-webkit-scrollbar-thumb { background: var(--primary); border-radius: 10px; }
        
        /* ============================================================
           SIDEBAR
           ============================================================ */
        .sidebar {
            position: fixed; top: 0; left: 0; bottom: 0;
            width: 270px;
            background: linear-gradient(180deg, #0B4EA8 0%, #0A3D7A 100%);
            color: white; z-index: 50; overflow-y: auto;
        }
        
        .sidebar-brand {
            padding: 18px 16px 14px;
            border-bottom: 2px solid rgba(255,255,255,0.08);
            background: rgba(0,0,0,0.1);
            position: sticky; top: 0; z-index: 5;
        }
        
        .sidebar-brand .logo {
            width: 42px; height: 42px; border-radius: 10px;
            background: white; padding: 4px;
        }
        
        .sidebar-brand .brand-text { color: white; font-weight: 700; font-size: 0.95rem; }
        .sidebar-brand .brand-sub { color: #9EC5FE; font-size: 0.65rem; font-weight: 500; }
        
        .sidebar-branch-selector {
            padding: 10px 14px;
            border-bottom: 2px solid rgba(255,255,255,0.06);
            background: rgba(0,0,0,0.05);
        }
        
        .sidebar-branch-selector select {
            width: 100%; padding: 7px 10px; border-radius: 8px; border: none;
            background: rgba(255,255,255,0.12); color: white; font-size: 0.75rem;
            outline: none; cursor: pointer;
        }
        
        .sidebar-branch-selector select option { background: #0B4EA8; color: white; }
        
        .sidebar-nav { padding: 10px 8px 20px; }
        
        .sidebar-nav .nav-label {
            font-size: 0.5rem; text-transform: uppercase;
            letter-spacing: 0.08em; color: #6EA8FE;
            padding: 8px 10px 4px; margin: 8px 0 2px;
            font-weight: 700;
        }
        
        .sidebar-link {
            display: flex; align-items: center; gap: 10px;
            padding: 8px 12px; border-radius: 8px;
            color: #D2E3FC; text-decoration: none;
            font-size: 0.8rem; font-weight: 500;
            margin: 1px 0;
        }
        
        .sidebar-link:hover {
            background: rgba(10, 168, 79, 0.4); color: white;
            transform: translateX(4px);
        }
        
        .sidebar-link.active {
            background: rgba(10, 168, 79, 0.5); color: white;
        }
        
        .sidebar-link i { width: 20px; text-align: center; font-size: 0.9rem; }
        
        .sidebar-link .badge {
            margin-left: auto; background: rgba(255,255,255,0.12);
            padding: 1px 8px; border-radius: 20px;
            font-size: 0.6rem; font-weight: 600;
        }
        
        .sidebar-status {
            padding: 10px 16px; border-top: 2px solid rgba(255,255,255,0.06);
            display: flex; align-items: center; gap: 10px;
            background: rgba(0,0,0,0.1); position: sticky; bottom: 0;
        }
        
        .sidebar-status .status-dot { width: 8px; height: 8px; border-radius: 50%; }
        .sidebar-status .status-dot.online { background: #34D399; }
        .sidebar-status .status-dot.offline { background: #94A3B8; }
        .sidebar-status .status-text { font-size: 0.65rem; color: #D2E3FC; }
        
        @media (max-width: 1024px) {
            .sidebar { width: 280px; transform: translateX(-100%); z-index: 9999; transition: transform 0.3s; }
            .sidebar.open { transform: translateX(0) !important; }
        }
        
        /* ============================================================
           EMBEDDED HEADER (SMALLER SEARCH BAR)
           ============================================================ */
        .embedded-header {
            position: fixed; top: 0; left: 270px; right: 0;
            height: 68px; background: var(--bg-nav); z-index: 40;
            display: flex; align-items: center; justify-content: space-between;
            padding: 0 24px; border-bottom: 2px solid var(--border-color);
        }
        
        /* ✅ SMALLER SEARCH BAR */
        .search-wrapper {
            display: flex; 
            align-items: center;
            background: var(--bg-body);
            border-radius: 8px;
            border: 2px solid var(--border-color);
            flex: 0 1 auto;
            max-width: 260px;
            min-width: 160px;
            height: 36px;
            transition: border-color 0.3s;
        }
        
        .search-wrapper:focus-within {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.12);
        }
        
        .search-wrapper i {
            color: var(--text-muted);
            margin-left: 10px;
            font-size: 0.75rem;
            pointer-events: none;
        }
        
        .search-wrapper input {
            border: none; 
            background: transparent; 
            padding: 4px 8px;
            width: 100%; 
            font-size: 0.75rem; 
            outline: none;
            color: var(--text-primary);
            height: 100%;
        }
        
        .search-wrapper input::placeholder {
            font-size: 0.72rem;
            color: var(--text-muted);
        }
        
        .search-wrapper .search-btn {
            background: var(--primary); 
            color: white; 
            border: none;
            padding: 0 12px;
            border-radius: 0 6px 6px 0;
            cursor: pointer; 
            font-size: 0.72rem;
            height: 100%;
            display: flex; 
            align-items: center;
            transition: background 0.3s;
        }
        
        .search-wrapper .search-btn:hover {
            background: var(--primary-dark);
        }
        
        .datetime { 
            font-size: 0.75rem; 
            color: var(--text-secondary); 
            display: flex; 
            align-items: center; 
            gap: 6px; 
            white-space: nowrap;
        }
        
        .avatar { 
            width: 38px; 
            height: 38px; 
            border-radius: 50%; 
            object-fit: cover; 
            border: 2px solid var(--border-color); 
        }
        
        .icon-btn {
            width: 36px; 
            height: 36px; 
            border-radius: 50%;
            display: flex; 
            align-items: center; 
            justify-content: center;
            color: var(--text-secondary); 
            background: transparent;
            border: none; 
            cursor: pointer; 
            position: relative;
        }
        
        .icon-btn:hover { background: var(--bg-body); color: var(--primary); }
        
        .notif-dot {
            position: absolute; top: 4px; right: 4px;
            width: 8px; height: 8px; border-radius: 50%;
            border: 2px solid var(--bg-nav);
        }
        
        .notif-dot.has-notif { background: var(--danger); }
        .notif-dot.no-notif { background: var(--text-muted); }
        
        .dark-toggle-btn {
            background: var(--bg-body); 
            border: 2px solid var(--border-color);
            border-radius: 8px; 
            padding: 5px 10px;
            cursor: pointer; 
            font-size: 0.75rem; 
            color: var(--text-primary);
            display: flex; 
            align-items: center; 
            gap: 5px;
            transition: border-color 0.3s;
        }
        
        .dark-toggle-btn:hover {
            border-color: var(--primary);
        }
        
        .branch-selector {
            background: var(--bg-body); 
            border: 2px solid var(--border-color);
            border-radius: 8px; 
            padding: 5px 10px;
            font-size: 0.75rem; 
            color: var(--text-primary);
            outline: none; 
            cursor: pointer;
            max-width: 150px;
        }
        
        /* ============================================================
           MAIN CONTENT
           ============================================================ */
        .main-content {
            margin-left: 270px; margin-top: 68px;
            padding: 28px 32px; min-height: calc(100vh - 68px);
        }
        
        /* ============================================================
           PAGE HEADER
           ============================================================ */
        .page-header-box {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border-radius: 16px; padding: 18px 24px;
            margin-bottom: 20px;
            display: flex; justify-content: space-between; align-items: center;
            flex-wrap: wrap; gap: 10px;
        }
        
        .page-header-box .page-title {
            color: white; font-size: 1.4rem; font-weight: 700;
            display: flex; align-items: center; gap: 8px; flex-wrap: wrap;
        }
        
        .page-header-box .role-badge {
            background: rgba(255,255,255,0.2); color: white;
            padding: 2px 10px; border-radius: 20px;
            font-size: 0.55rem; font-weight: 600; text-transform: uppercase;
        }
        
        .page-header-box .branch-name {
            background: rgba(255,255,255,0.15);
            padding: 2px 12px; border-radius: 20px;
            font-size: 0.7rem; font-weight: 500; color: white;
        }
        
        .page-header-box .btn-back {
            background: var(--success); color: white;
            padding: 5px 16px; border-radius: 20px;
            font-size: 0.7rem; font-weight: 600;
            text-decoration: none;
            display: inline-flex; align-items: center; gap: 6px;
        }
        
        .page-header-box .page-subtitle {
            color: rgba(255,255,255,0.85); font-size: 0.8rem;
            display: flex; align-items: center; gap: 6px;
            flex-wrap: wrap; margin-top: 4px;
        }
        
        /* ============================================================
           STATS
           ============================================================ */
        .stats-grid {
            display: grid; grid-template-columns: repeat(4, 1fr);
            gap: 14px; margin-bottom: 20px;
        }
        
        .stat-card {
            border-radius: 12px; padding: 16px 18px;
            color: white; min-height: 85px;
            text-decoration: none;
            transition: transform 0.3s;
        }
        
        .stat-card:hover { transform: translateY(-4px); }
        
        .stat-card .stat-number { font-size: 1.6rem; font-weight: 700; }
        .stat-card .stat-label {
            font-size: 0.6rem; color: rgba(255,255,255,0.9);
            font-weight: 500; text-transform: uppercase; margin-top: 2px;
        }
        .stat-card .stat-icon { font-size: 1.3rem; opacity: 0.7; float: right; margin-top: -5px; }
        
        .stat-card.blue { background: linear-gradient(135deg, #0B5ED7, #0A4CA8); }
        .stat-card.green { background: linear-gradient(135deg, #059669, #047857); }
        .stat-card.orange { background: linear-gradient(135deg, #D97706, #B45309); }
        .stat-card.purple { background: linear-gradient(135deg, #7C3AED, #6D28D9); }
        
        /* ============================================================
           CARD
           ============================================================ */
        .card {
            background: var(--bg-card); border-radius: 12px;
            padding: 14px 18px;
            border: 2px solid var(--border-color);
            margin-bottom: 20px;
        }
        
        /* ============================================================
           FILTER FORM
           ============================================================ */
        .filter-form {
            display: flex; gap: 8px; flex-wrap: wrap; align-items: center;
        }
        
        .filter-form select,
        .filter-form input[type="date"] {
            padding: 8px 12px;
            border: 2px solid var(--border-color);
            border-radius: 8px; font-size: 0.8rem;
            background: var(--bg-card); color: var(--text-primary);
            outline: none;
        }
        
        .filter-form select:focus,
        .filter-form input:focus {
            border-color: var(--primary);
        }
        
        .btn-search {
            padding: 8px 20px; border-radius: 8px;
            font-weight: 600; font-size: 0.8rem;
            border: none; background: var(--primary);
            color: white; cursor: pointer;
            display: inline-flex; align-items: center; gap: 6px;
        }
        
        .btn-search:hover { background: var(--primary-dark); }
        
        .btn-reset {
            padding: 8px 16px; border-radius: 8px;
            font-weight: 600; font-size: 0.8rem;
            border: 2px solid var(--border-color);
            background: transparent; color: var(--text-secondary);
            text-decoration: none;
            display: inline-flex; align-items: center; gap: 6px;
        }
        
        .btn-reset:hover { border-color: var(--danger); color: var(--danger); }
        
        /* ============================================================
           TABLE HEADER BAR
           ============================================================ */
        .table-header-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 12px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--border-color);
        }
        
        .table-header-left {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            flex: 1;
            min-width: 0;
        }
        
        .table-header-right {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-shrink: 0;
        }
        
        .table-search-box {
            position: relative;
            min-width: 280px;
            flex: 1;
            max-width: 400px;
        }
        
        .table-search-box i {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: rgba(255,255,255,0.9);
            font-size: 0.85rem;
            pointer-events: none;
            z-index: 1;
        }
        
        .table-search-box input {
            width: 100%;
            padding: 10px 14px 10px 40px;
            border: 2px solid var(--primary);
            border-radius: 10px;
            font-size: 0.85rem;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            outline: none;
            font-weight: 500;
            height: 42px;
            box-shadow: 0 4px 12px rgba(11, 94, 215, 0.25);
        }
        
        .table-search-box input::placeholder { color: rgba(255,255,255,0.85); }
        
        .table-search-box input:focus {
            box-shadow: 0 0 0 4px rgba(11, 94, 215, 0.2);
        }
        
        .scroll-btn-header {
            width: 38px; height: 38px; border-radius: 8px;
            border: 2px solid var(--border-color);
            background: var(--bg-card); color: var(--text-primary);
            cursor: pointer;
            display: inline-flex; align-items: center; justify-content: center;
            transition: all 0.3s;
        }
        
        .scroll-btn-header:hover {
            background: var(--primary); border-color: var(--primary); color: white;
        }
        
        .scroll-btn-header:disabled { opacity: 0.35; cursor: not-allowed; }
        
        .search-results-info {
            font-size: 0.7rem; color: var(--primary);
            padding: 6px 12px; background: var(--primary-light);
            border-radius: 8px; display: none;
            font-weight: 600; border: 1px solid var(--primary);
        }
        
        .result-count { font-size: 0.75rem; color: var(--text-secondary); }
        .result-count strong { color: var(--primary); }
        
        /* ============================================================
           TABLE
           ============================================================ */
        .table-scroll-container {
            overflow-x: auto; overflow-y: auto;
            max-height: 600px;
        }
        
        .table-scroll-container::-webkit-scrollbar { height: 8px; width: 8px; }
        .table-scroll-container::-webkit-scrollbar-thumb { background: var(--primary); border-radius: 4px; }
        
        .data-table {
            width: 100%; min-width: 1100px;
            border-collapse: separate; border-spacing: 0;
            font-size: 0.78rem;
        }
        
        .data-table thead th {
            position: sticky; top: 0; z-index: 10;
            background: var(--primary); color: white;
            padding: 10px 12px;
            font-size: 0.62rem; text-transform: uppercase;
            font-weight: 700; white-space: nowrap; text-align: left;
        }
        
        .data-table thead th:first-child { border-radius: 8px 0 0 0; }
        .data-table thead th:last-child { border-radius: 0 8px 0 0; }
        
        .data-table tbody tr:nth-child(even) { background: var(--primary-light); }
        .data-table tbody tr:hover td { background: var(--success-light); }
        
        [data-theme="dark"] .data-table tbody tr:nth-child(even) { background: #1E293B; }
        [data-theme="dark"] .data-table tbody tr:hover td { background: #1A3A2A; }
        
        .data-table td {
            padding: 10px 12px;
            border-bottom: 1px solid var(--border-color);
            vertical-align: middle;
        }
        
        .badge {
            display: inline-flex; align-items: center; gap: 4px;
            padding: 2px 10px; border-radius: 12px;
            font-size: 0.6rem; font-weight: 600;
        }
        
        .badge-blue { background: var(--primary-light); color: var(--primary); }
        .badge-green { background: var(--success-light); color: var(--success); }
        .badge-orange { background: var(--warning-light); color: var(--warning); }
        .badge-red { background: var(--danger-light); color: var(--danger); }
        .badge-purple { background: var(--purple-light); color: var(--purple); }
        .badge-gray { background: #F1F5F9; color: var(--text-secondary); }
        
        [data-theme="dark"] .badge-gray { background: #334155; color: #94A3B8; }
        
        .no-results-row td {
            text-align: center;
            padding: 40px 20px !important;
            color: var(--text-secondary);
        }
        
        .no-results-row i {
            font-size: 2.5rem; color: var(--border-color);
            display: block; margin-bottom: 10px;
        }
        
        /* ============================================================
           FOOTER
           ============================================================ */
        .footer {
            padding: 10px 0;
            border-top: 1px solid var(--border-color);
            margin-top: 16px; text-align: center;
            font-size: 0.6rem; color: var(--text-secondary);
        }
        
        .footer .footer-brand { color: var(--primary); font-weight: 600; }
        
        /* ============================================================
           RESPONSIVE
           ============================================================ */
        @media (max-width: 1024px) {
            .main-content { margin-left: 0; padding: 14px; }
            .embedded-header { left: 0; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
        }
        
        @media (max-width: 768px) {
            .stats-grid { grid-template-columns: 1fr 1fr; }
            .page-header-box { flex-direction: column; align-items: stretch; }
            .filter-form { flex-direction: column; align-items: stretch; }
            .table-header-bar { flex-direction: column; align-items: stretch; }
            .table-search-box { min-width: 100%; max-width: 100%; }
            
            /* ✅ Smaller header search on mobile */
            .search-wrapper {
                max-width: 180px;
                min-width: 120px;
                height: 34px;
            }
            
            .search-wrapper input {
                font-size: 0.7rem;
                padding: 4px 6px;
            }
            
            .search-wrapper .search-btn {
                padding: 0 10px;
                font-size: 0.68rem;
            }
            
            .datetime { display: none; }
        }
        
        @media (max-width: 480px) {
            .search-wrapper {
                max-width: 140px;
                min-width: 100px;
                height: 32px;
            }
            
            .search-wrapper input {
                font-size: 0.65rem;
            }
            
            .search-wrapper i {
                font-size: 0.65rem;
                margin-left: 8px;
            }
            
            .search-wrapper .search-btn {
                padding: 0 8px;
                font-size: 0.65rem;
            }
        }
    </style>
</head>
<body>

<!-- SIDEBAR OVERLAY -->
<div id="sidebarOverlay" style="position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.5);z-index:45;display:none;"></div>

<!-- SIDEBAR -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <div style="display:flex;align-items:center;gap:10px;">
            <img src="<?= $logo_path ?>" alt="Braick Logo" class="logo" onerror="this.style.display='none'">
            <div>
                <p class="brand-text">Braick Dispensary</p>
                <p class="brand-sub">👑 Super Admin</p>
            </div>
        </div>
    </div>
    
    <div class="sidebar-branch-selector">
        <select onchange="switchBranch(this.value)">
            <option value="all" <?= $selected_branch_id === 'all' ? 'selected' : '' ?>>🌐 All Branches</option>
            <?php foreach ($branches as $b): ?>
                <option value="<?= $b['id'] ?>" <?= $selected_branch_id == $b['id'] ? 'selected' : '' ?>>
                    🏥 <?= htmlspecialchars($b['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    
    <nav class="sidebar-nav">
        <div class="nav-label">📋 Main Menu</div>
        <a href="dashboard.php?branch=<?= $selected_branch_id ?>" class="sidebar-link"><i class="fas fa-home"></i> <span>Dashboard</span></a>
        <a href="employees.php?branch=<?= $selected_branch_id ?>" class="sidebar-link"><i class="fas fa-users"></i> <span>Employees</span><span class="badge"><?= $total_employees_sidebar ?></span></a>
        <a href="patients.php?branch=<?= $selected_branch_id ?>" class="sidebar-link"><i class="fas fa-user-injured"></i> <span>Patients</span><span class="badge"><?= $total_patients_sidebar ?></span></a>
        <a href="inventory.php?branch=<?= $selected_branch_id ?>" class="sidebar-link"><i class="fas fa-warehouse"></i> <span>Inventory</span></a>
        
        <div class="nav-label">⚙️ Modules</div>
        <a href="doctors_list.php?branch=<?= $selected_branch_id ?>" class="sidebar-link"><i class="fas fa-user-md"></i> <span>Doctors</span><span class="badge"><?= $total_doctors_sidebar ?></span></a>
        <a href="view_pharmacy.php?branch=<?= $selected_branch_id ?>" class="sidebar-link"><i class="fas fa-prescription"></i> <span>Pharmacy</span><span class="badge"><?= $module_counts['pharmacy'] ?? 0 ?></span></a>
        <a href="view_reception.php?branch=<?= $selected_branch_id ?>" class="sidebar-link"><i class="fas fa-headset"></i> <span>Reception</span><span class="badge"><?= $module_counts['reception'] ?? 0 ?></span></a>
        <a href="view_laboratory.php?branch=<?= $selected_branch_id ?>" class="sidebar-link"><i class="fas fa-flask"></i> <span>Laboratory</span><span class="badge"><?= $module_counts['laboratory'] ?? 0 ?></span></a>
        <a href="view_cashier.php?branch=<?= $selected_branch_id ?>" class="sidebar-link"><i class="fas fa-cash-register"></i> <span>Cashier</span><span class="badge"><?= $module_counts['cashier'] ?? 0 ?></span></a>
        
        <div class="nav-label">💼 Management</div>
        <a href="branches.php?branch=<?= $selected_branch_id ?>" class="sidebar-link"><i class="fas fa-store-alt"></i> <span>Branches</span><span class="badge"><?= $total_branches_sidebar ?></span></a>
        <a href="departments.php?branch=<?= $selected_branch_id ?>" class="sidebar-link"><i class="fas fa-building"></i> <span>Departments</span></a>
        <a href="reports.php?branch=<?= $selected_branch_id ?>" class="sidebar-link"><i class="fas fa-chart-bar"></i> <span>Reports</span></a>
        
        <div class="nav-label">🔧 System</div>
        <a href="settings.php?branch=<?= $selected_branch_id ?>" class="sidebar-link"><i class="fas fa-cog"></i> <span>Settings</span></a>
        <a href="system_logs.php?branch=<?= $selected_branch_id ?>" class="sidebar-link active"><i class="fas fa-history"></i> <span>System Logs</span></a>
        
        <div class="nav-label">👤 Account</div>
        <a href="profile.php" class="sidebar-link"><i class="fas fa-user-circle"></i> <span>Profile</span></a>
        <a href="/dispensary_system/frontend/pages/logout.php" class="sidebar-link" style="color:#FCA5A5;"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a>
    </nav>
    
    <div class="sidebar-status">
        <span class="status-dot <?= $user_is_online ? 'online' : 'offline' ?>"></span>
        <span class="status-text"><?= $user_is_online ? 'Online' : 'Offline' ?></span>
    </div>
</aside>

<!-- ============================================================
     EMBEDDED HEADER (SMALLER SEARCH BAR)
     ============================================================ -->
<header class="embedded-header">
    <div style="display:flex;align-items:center;gap:12px;flex:1;">
        <button id="sidebarToggle" class="icon-btn" style="display:none;">
            <i class="fas fa-bars"></i>
        </button>
        
        <!-- ✅ SMALLER SEARCH BAR -->
        <div class="search-wrapper">
            <i class="fas fa-search"></i>
            <input type="text" id="globalSearchInput" placeholder="Search...">
            <button class="search-btn" onclick="globalSearch()"><i class="fas fa-search"></i></button>
        </div>
    </div>
    
    <div style="display:flex;align-items:center;gap:10px;">
        <select class="branch-selector" onchange="switchBranch(this.value)">
            <option value="all" <?= $selected_branch_id === 'all' ? 'selected' : '' ?>>🌐 All</option>
            <?php foreach ($branches as $b): ?>
                <option value="<?= $b['id'] ?>" <?= $selected_branch_id == $b['id'] ? 'selected' : '' ?>>
                    🏥 <?= htmlspecialchars($b['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        
        <span class="datetime">
            <i class="fas fa-clock" style="color:#059669;"></i>
            <span id="clockDisplay"><?= date('d M Y • H:i:s') ?></span>
        </span>
        
        <button id="darkModeToggle" class="dark-toggle-btn">
            <i id="darkIcon" class="fas fa-moon"></i>
            <span id="darkText">Dark</span>
        </button>
        
        <button class="icon-btn" onclick="window.location.href='notifications.php'">
            <i class="fas fa-bell"></i>
            <span class="notif-dot <?= $unread_notifications > 0 ? 'has-notif' : 'no-notif' ?>"></span>
        </button>
        
        <a href="profile.php">
            <img src="<?= $profile_pic_url ?>" alt="Profile" class="avatar" onerror="this.style.display='none'">
        </a>
    </div>
</header>

<!-- ============================================================
     MAIN CONTENT
     ============================================================ -->
<main class="main-content">

    <!-- PAGE HEADER -->
    <div class="page-header-box">
        <div>
            <h1 class="page-title">
                <i class="fas fa-history"></i> System Activity Logs
                <span class="role-badge">ADMIN</span>
                <span class="branch-name">
                    <i class="fas fa-store-alt"></i> <?= htmlspecialchars($display_branch_name) ?>
                </span>
                <a href="dashboard.php?branch=<?= $selected_branch_id ?>" class="btn-back">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
            </h1>
            <p class="page-subtitle">
                <strong><?= number_format($total_logs) ?></strong> log entries loaded
                <?php if (!empty($action_filter)): ?> · Action: <strong><?= htmlspecialchars($action_filter) ?></strong><?php endif; ?>
                <?php if (!empty($date_from) || !empty($date_to)): ?>
                    · Date: <strong><?= htmlspecialchars($date_from ?: 'Any') ?> → <?= htmlspecialchars($date_to ?: 'Any') ?></strong>
                <?php endif; ?>
            </p>
        </div>
    </div>

    <!-- STATS -->
    <div class="stats-grid">
        <a href="system_logs.php?branch=<?= $selected_branch_id ?>" class="stat-card blue">
            <span class="stat-icon"><i class="fas fa-list"></i></span>
            <div class="stat-number"><?= number_format($total_activity_count) ?></div>
            <div class="stat-label">Total Logs</div>
        </a>
        <a href="system_logs.php?branch=<?= $selected_branch_id ?>&date_from=<?= date('Y-m-d') ?>&date_to=<?= date('Y-m-d') ?>" class="stat-card green">
            <span class="stat-icon"><i class="fas fa-calendar-day"></i></span>
            <div class="stat-number"><?= number_format($today_logs_count) ?></div>
            <div class="stat-label">Today</div>
        </a>
        <a href="system_logs.php?branch=<?= $selected_branch_id ?>" class="stat-card orange">
            <span class="stat-icon"><i class="fas fa-calendar-week"></i></span>
            <div class="stat-number"><?= number_format($week_logs_count) ?></div>
            <div class="stat-label">This Week</div>
        </a>
        <a href="system_logs.php?branch=<?= $selected_branch_id ?>" class="stat-card purple">
            <span class="stat-icon"><i class="fas fa-calendar-alt"></i></span>
            <div class="stat-number"><?= number_format($month_logs_count) ?></div>
            <div class="stat-label">This Month</div>
        </a>
    </div>

    <!-- FILTERS -->
    <div class="card">
        <form method="GET" class="filter-form">
            <input type="hidden" name="branch" value="<?= $selected_branch_id ?>">
            
            <select name="action_filter">
                <option value="">📋 All Actions</option>
                <?php foreach ($unique_actions as $act): ?>
                    <option value="<?= htmlspecialchars($act) ?>" <?= $action_filter === $act ? 'selected' : '' ?>>
                        <?= htmlspecialchars($act) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            
            <select name="user_filter">
                <option value="">👤 All Users</option>
                <?php foreach ($unique_users as $u): ?>
                    <option value="<?= $u['id'] ?>" <?= $user_filter == $u['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($u['full_name'] ?? $u['username']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            
            <input type="date" name="date_from" value="<?= htmlspecialchars($date_from) ?>" placeholder="From" title="From date">
            <input type="date" name="date_to" value="<?= htmlspecialchars($date_to) ?>" placeholder="To" title="To date">
            
            <button type="submit" class="btn-search">
                <i class="fas fa-filter"></i> Filter
            </button>
            
            <a href="system_logs.php?branch=<?= $selected_branch_id ?>" class="btn-reset">
                <i class="fas fa-times"></i> Reset
            </a>
        </form>
    </div>

    <!-- LOGS TABLE -->
    <div class="card">
        <div class="table-header-bar">
            <div class="table-header-left">
                <!-- BLUE SEARCH BOX - AUTO FILTER -->
                <div class="table-search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" id="tableSearchInput" placeholder="🔍 Auto-search logs..." autocomplete="off">
                </div>
                
                <h3 class="card-title" style="margin:0;">
                    <i class="fas fa-list" style="color:var(--primary);"></i> 
                    <span class="result-count" id="countDisplay">(<strong><?= count($logs) ?></strong> entries)</span>
                </h3>
                
                <span class="search-results-info" id="searchInfo">
                    <i class="fas fa-filter"></i> <strong id="searchCount">0</strong> match
                </span>
            </div>
            
            <div class="table-header-right">
                <button type="button" class="scroll-btn-header" id="scrollBtnLeft" onclick="scrollTable('left')" title="Scroll Left">
                    <i class="fas fa-chevron-left"></i>
                </button>
                <button type="button" class="scroll-btn-header" id="scrollBtnRight" onclick="scrollTable('right')" title="Scroll Right">
                    <i class="fas fa-chevron-right"></i>
                </button>
            </div>
        </div>

        <?php if (count($logs) > 0): ?>
            <div class="table-scroll-container" id="tableWrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th style="width:40px;text-align:center;">#</th>
                            <th style="min-width:130px;">Date/Time</th>
                            <th style="min-width:150px;">User</th>
                            <th style="min-width:140px;">Action</th>
                            <th style="min-width:300px;">Details</th>
                            <th style="min-width:120px;">Branch</th>
                            <th style="min-width:120px;">IP Address</th>
                        </tr>
                    </thead>
                    <tbody id="tableBody">
                        <?php 
                        $num = 1;
                        foreach ($logs as $log): 
                            $badge_class = 'badge-blue';
                            $action_lower = strtolower($log['action'] ?? '');
                            if (strpos($action_lower, 'login') !== false) $badge_class = 'badge-green';
                            elseif (strpos($action_lower, 'logout') !== false) $badge_class = 'badge-gray';
                            elseif (strpos($action_lower, 'delete') !== false || strpos($action_lower, 'deactivate') !== false) $badge_class = 'badge-red';
                            elseif (strpos($action_lower, 'update') !== false || strpos($action_lower, 'edit') !== false) $badge_class = 'badge-orange';
                            elseif (strpos($action_lower, 'create') !== false || strpos($action_lower, 'add') !== false) $badge_class = 'badge-purple';
                            
                            $search_text = strtolower(
                                ($log['action'] ?? '') . ' ' .
                                ($log['details'] ?? '') . ' ' .
                                ($log['user_full_name'] ?? '') . ' ' .
                                ($log['user_username'] ?? '') . ' ' .
                                ($log['user_role'] ?? '') . ' ' .
                                ($log['branch_name'] ?? '') . ' ' .
                                ($log['ip_address'] ?? '')
                            );
                        ?>
                            <tr class="log-row" data-search="<?= htmlspecialchars($search_text) ?>">
                                <td style="text-align:center;"><?= $num++ ?></td>
                                <td>
                                    <strong><?= date('d/m/Y', strtotime($log['created_at'])) ?></strong>
                                    <div style="font-size:0.65rem;color:var(--text-secondary);">
                                        <?= date('H:i:s', strtotime($log['created_at'])) ?>
                                    </div>
                                </td>
                                <td>
                                    <?php if (!empty($log['user_full_name'])): ?>
                                        <strong><?= htmlspecialchars($log['user_full_name']) ?></strong>
                                        <div style="font-size:0.6rem;color:var(--text-secondary);">
                                            <?= htmlspecialchars($log['user_role'] ?? '') ?>
                                        </div>
                                    <?php else: ?>
                                        <span style="color:var(--text-muted);">System</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge <?= $badge_class ?>">
                                        <?= htmlspecialchars($log['action'] ?? 'N/A') ?>
                                    </span>
                                </td>
                                <td style="font-size:0.75rem;max-width:500px;">
                                    <?= htmlspecialchars($log['details'] ?? '-') ?>
                                </td>
                                <td>
                                    <?php if (!empty($log['branch_name'])): ?>
                                        <span class="badge badge-blue">
                                            🏥 <?= htmlspecialchars($log['branch_name']) ?>
                                        </span>
                                    <?php else: ?>
                                        <span style="color:var(--text-muted);">-</span>
                                    <?php endif; ?>
                                </td>
                                <td style="font-size:0.7rem;color:var(--text-secondary);font-family:monospace;">
                                    <?= htmlspecialchars($log['ip_address'] ?? '-') ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        
                        <tr class="no-results-row" id="noResults" style="display:none;">
                            <td colspan="7">
                                <i class="fas fa-search-minus"></i>
                                <p>No logs match your search</p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div style="text-align:center;padding:40px 20px;color:var(--text-secondary);">
                <i class="fas fa-history" style="font-size:3rem;color:var(--border-color);display:block;margin-bottom:12px;"></i>
                <p style="font-size:0.95rem;font-weight:600;">No logs found</p>
                <p style="font-size:0.8rem;margin-top:4px;">Try adjusting your filters.</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- FOOTER -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span>|</span> System Logs
            <span>|</span> <?= number_format($total_logs) ?> entries shown
            <span>|</span> &copy; <?= date('Y') ?>
        </p>
    </footer>

</main>

<script>
// ================================================================
// GLOBAL SEARCH (header)
// ================================================================
function globalSearch() {
    var q = document.getElementById('globalSearchInput').value.trim();
    if (q) {
        // Filter the table directly instead of navigating
        var tableInput = document.getElementById('tableSearchInput');
        if (tableInput) {
            tableInput.value = q;
            tableInput.dispatchEvent(new Event('input'));
            // Scroll to table
            document.querySelector('.table-scroll-container')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    }
}

document.getElementById('globalSearchInput')?.addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        e.preventDefault();
        globalSearch();
    }
});

// ================================================================
// AUTO SEARCH - TABLE FILTER
// ================================================================
(function() {
    var input = document.getElementById('tableSearchInput');
    var noResults = document.getElementById('noResults');
    var countDisplay = document.getElementById('countDisplay');
    var searchInfo = document.getElementById('searchInfo');
    var searchCount = document.getElementById('searchCount');
    
    if (!input) return;
    
    var totalRows = document.querySelectorAll('.log-row').length;
    
    input.addEventListener('input', function() {
        var query = this.value.toLowerCase().trim();
        var rows = document.querySelectorAll('.log-row');
        var visibleCount = 0;
        
        rows.forEach(function(row) {
            var searchData = row.getAttribute('data-search') || '';
            if (query === '' || searchData.indexOf(query) !== -1) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        });
        
        if (countDisplay) {
            countDisplay.innerHTML = query === '' 
                ? '(<strong>' + totalRows + '</strong> entries)' 
                : '(<strong>' + visibleCount + '</strong> of ' + totalRows + ')';
        }
        
        if (searchInfo && searchCount) {
            if (query === '') {
                searchInfo.style.display = 'none';
            } else {
                searchInfo.style.display = 'inline-flex';
                searchCount.textContent = visibleCount;
            }
        }
        
        if (noResults) {
            noResults.style.display = (visibleCount === 0 && query !== '') ? '' : 'none';
        }
    });
    
    // ESC to clear
    input.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            this.value = '';
            this.dispatchEvent(new Event('input'));
            this.blur();
        }
    });
})();

// ================================================================
// SCROLL FUNCTIONS
// ================================================================
function scrollTable(dir) {
    var wrap = document.getElementById('tableWrap');
    if (wrap) wrap.scrollBy({ left: dir === 'left' ? -400 : 400, behavior: 'smooth' });
}

function updateScrollButtons() {
    var wrap = document.getElementById('tableWrap');
    var btnLeft = document.getElementById('scrollBtnLeft');
    var btnRight = document.getElementById('scrollBtnRight');
    if (!wrap || !btnLeft || !btnRight) return;
    
    var scrollLeft = wrap.scrollLeft;
    var maxScroll = wrap.scrollWidth - wrap.clientWidth;
    btnLeft.disabled = (scrollLeft <= 5);
    btnRight.disabled = (scrollLeft >= maxScroll - 5 || maxScroll <= 0);
}

document.addEventListener('DOMContentLoaded', function() {
    var wrap = document.getElementById('tableWrap');
    if (wrap) {
        wrap.addEventListener('scroll', updateScrollButtons);
        setTimeout(updateScrollButtons, 200);
    }
    window.addEventListener('resize', function() {
        setTimeout(updateScrollButtons, 200);
    });
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
// DARK MODE
// ================================================================
(function() {
    var t = document.getElementById('darkModeToggle');
    var icon = document.getElementById('darkIcon');
    var text = document.getElementById('darkText');
    var html = document.documentElement;
    
    if (localStorage.getItem('darkMode') === 'true') {
        html.setAttribute('data-theme', 'dark');
        if (icon) icon.className = 'fas fa-sun';
        if (text) text.textContent = 'Light';
    }
    
    t?.addEventListener('click', function() {
        if (html.getAttribute('data-theme') === 'dark') {
            html.removeAttribute('data-theme');
            if (icon) icon.className = 'fas fa-moon';
            if (text) text.textContent = 'Dark';
            localStorage.setItem('darkMode', 'false');
            document.cookie = "dark_mode=false; path=/";
        } else {
            html.setAttribute('data-theme', 'dark');
            if (icon) icon.className = 'fas fa-sun';
            if (text) text.textContent = 'Light';
            localStorage.setItem('darkMode', 'true');
            document.cookie = "dark_mode=true; path=/";
        }
    });
})();

// ================================================================
// CLOCK
// ================================================================
setInterval(function() {
    var now = new Date();
    var d = now.toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' });
    var tm = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true });
    var el = document.getElementById('clockDisplay');
    if (el) el.textContent = d + ' • ' + tm;
}, 1000);

// ================================================================
// SIDEBAR TOGGLE (mobile)
// ================================================================
(function() {
    var sidebar = document.getElementById('sidebar');
    var toggleBtn = document.getElementById('sidebarToggle');
    var overlay = document.getElementById('sidebarOverlay');
    
    function checkMobile() {
        if (window.innerWidth <= 1024 && toggleBtn) {
            toggleBtn.style.display = 'flex';
        } else if (toggleBtn) {
            toggleBtn.style.display = 'none';
        }
    }
    
    checkMobile();
    window.addEventListener('resize', checkMobile);
    
    toggleBtn?.addEventListener('click', function(e) {
        e.preventDefault();
        sidebar.classList.toggle('open');
        if (sidebar.classList.contains('open')) {
            overlay.style.display = 'block';
            document.body.style.overflow = 'hidden';
        } else {
            overlay.style.display = 'none';
            document.body.style.overflow = '';
        }
    });
    
    overlay?.addEventListener('click', function() {
        sidebar.classList.remove('open');
        overlay.style.display = 'none';
        document.body.style.overflow = '';
    });
})();

console.log('%c📋 System Logs - Braick Dispensary', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
console.log('%c✅ Total logs: <?= $total_logs ?>', 'font-size:13px; color:#059669;');
console.log('%c✅ Smaller header search bar (260px max)', 'font-size:13px; color:#FBBF24;');
console.log('%c✅ Auto-search in table header (blue box)', 'font-size:13px; color:#FBBF24;');
</script>

</body>
</html>