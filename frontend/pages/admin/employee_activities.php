<?php
// ================================================================
// FILE: frontend/pages/admin/employee_activities.php
// SUPER ADMIN - EMPLOYEE ACTIVITIES
// VIEW ALL ACTIVITIES FOR A SPECIFIC EMPLOYEE
// WITH RECENT ACTIVITIES SECTION
// BRAICK DISPENSARY - WITH LOGIN SESSION
// FIXED: Chart size reduced, activities display improved
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
// GET EMPLOYEE ID
// ================================================================
$employee_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$selected_branch_id = $_GET['branch'] ?? 'all';

if ($employee_id <= 0) {
    header('Location: employees.php?branch=' . urlencode($selected_branch_id));
    exit;
}

// ================================================================
// FETCH EMPLOYEE DETAILS
// ================================================================
$stmt = $db->prepare("
    SELECT 
        u.id,
        u.full_name,
        u.username,
        u.email,
        u.role,
        u.branch_id,
        u.status,
        u.profile_pic,
        b.name as branch_name
    FROM users u
    LEFT JOIN branches b ON u.branch_id = b.id
    WHERE u.id = ? AND u.role != 'admin'
");
$stmt->execute([$employee_id]);
$employee = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$employee) {
    header('Location: employees.php?branch=' . urlencode($selected_branch_id) . '&error=notfound');
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
// GET FILTERS
// ================================================================
$action_filter = isset($_GET['action']) ? $_GET['action'] : '';
$date_filter = isset($_GET['date']) ? $_GET['date'] : '';
$search_filter = isset($_GET['search']) ? trim($_GET['search']) : '';

// ================================================================
// BUILD ACTIVITIES QUERY
// ================================================================
$conditions = ["user_id = ?"];
$params = [$employee_id];

if (!empty($action_filter)) {
    $conditions[] = "action LIKE ?";
    $params[] = "%$action_filter%";
}

if (!empty($date_filter)) {
    $conditions[] = "DATE(created_at) = ?";
    $params[] = $date_filter;
}

if (!empty($search_filter)) {
    $conditions[] = "(action LIKE ? OR details LIKE ?)";
    $params[] = "%$search_filter%";
    $params[] = "%$search_filter%";
}

$where_clause = implode(" AND ", $conditions);

// ================================================================
// GET TOTAL ACTIVITIES COUNT
// ================================================================
$count_stmt = $db->prepare("SELECT COUNT(*) as total FROM activity_logs WHERE $where_clause");
$count_stmt->execute($params);
$total_activities = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// ================================================================
// GET ACTIVITIES WITH PAGINATION
// ================================================================
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;
$total_pages = $total_activities > 0 ? ceil($total_activities / $per_page) : 1;

$stmt = $db->prepare("
    SELECT 
        id,
        action,
        details,
        ip_address,
        user_agent,
        created_at
    FROM activity_logs
    WHERE $where_clause
    ORDER BY created_at DESC
    LIMIT ? OFFSET ?
");
$params[] = $per_page;
$params[] = $offset;
$stmt->execute($params);
$activities = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// GET RECENT ACTIVITIES (Last 5 - for summary)
// ================================================================
$recent_stmt = $db->prepare("
    SELECT 
        action,
        details,
        created_at
    FROM activity_logs
    WHERE user_id = ?
    ORDER BY created_at DESC
    LIMIT 5
");
$recent_stmt->execute([$employee_id]);
$recent_activities = $recent_stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// GET ACTIVITY STATISTICS
// ================================================================
$stmt = $db->prepare("
    SELECT 
        action,
        COUNT(*) as count
    FROM activity_logs
    WHERE user_id = ?
    GROUP BY action
    ORDER BY count DESC
    LIMIT 10
");
$stmt->execute([$employee_id]);
$action_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// GET DAILY ACTIVITY CHART DATA (LAST 30 DAYS)
// ================================================================
$stmt = $db->prepare("
    SELECT 
        DATE(created_at) as date,
        COUNT(*) as count
    FROM activity_logs
    WHERE user_id = ?
    AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY DATE(created_at)
    ORDER BY date ASC
");
$stmt->execute([$employee_id]);
$daily_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

$chart_labels = [];
$chart_values = [];
foreach ($daily_data as $data) {
    $chart_labels[] = date('M d', strtotime($data['date']));
    $chart_values[] = (int)$data['count'];
}

// ================================================================
// GET UNIQUE ACTIONS FOR FILTER
// ================================================================
$stmt = $db->prepare("
    SELECT DISTINCT action 
    FROM activity_logs 
    WHERE user_id = ?
    ORDER BY action ASC
");
$stmt->execute([$employee_id]);
$unique_actions = $stmt->fetchAll(PDO::FETCH_COLUMN);

// ================================================================
// GET BRANCHES FOR FILTER
// ================================================================
$branches = [];
$stmt = $db->query("SELECT id, name, location FROM branches WHERE status = 'active'");
$branches = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// ROLE LABEL
// ================================================================
$role_labels = [
    'doctor' => 'Doctor',
    'reception' => 'Receptionist',
    'pharmacy' => 'Pharmacist',
    'laboratory' => 'Lab Technician',
    'cashier' => 'Cashier'
];
$role_display = $role_labels[$employee['role']] ?? ucfirst($employee['role']);

// ================================================================
// PROFILE PICTURE URL
// ================================================================
$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

$logo_url = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

// ================================================================
// TIME AGO FUNCTION
// ================================================================
function time_ago($timestamp) {
    if (empty($timestamp)) return 'Just now';
    $time = strtotime($timestamp);
    $now = time();
    $diff = $now - $time;
    
    if ($diff < 60) return 'Just now';
    if ($diff < 3600) return floor($diff / 60) . 'm ago';
    if ($diff < 86400) return floor($diff / 3600) . 'h ago';
    if ($diff < 604800) return floor($diff / 86400) . 'd ago';
    return date('M d, Y', $time);
}

// ================================================================
// INCLUDE SHARED HEADER
// ================================================================
include_once __DIR__ . '/../../components/admin_header.php';

// ================================================================
// INCLUDE SHARED SIDEBAR
// ================================================================
include_once __DIR__ . '/../../components/admin_sidebar.php';
?>

<!DOCTYPE html>
<html lang="en" data-theme="<?= isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Activities - Braick Dispensary</title>
    
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
            --bg-body: #F1F5F9;
            --bg-card: #FFFFFF;
            --bg-nav: #FFFFFF;
            --text-primary: #0F172A;
            --text-secondary: #64748B;
            --border-color: #E2E8F0;
            --shadow-sm: 0 2px 8px rgba(0,0,0,0.06);
            --shadow-md: 0 4px 20px rgba(0,0,0,0.08);
            --radius: 16px;
            --radius-sm: 10px;
            --table-hover: #F8FAFC;
        }

        [data-theme="dark"] {
            --bg-body: #0F172A;
            --bg-card: #1E293B;
            --bg-nav: #1E293B;
            --text-primary: #F1F5F9;
            --text-secondary: #94A3B8;
            --border-color: #334155;
            --shadow-sm: 0 2px 8px rgba(0,0,0,0.3);
            --shadow-md: 0 4px 20px rgba(0,0,0,0.4);
            --table-hover: #1E293B;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Inter', 'Segoe UI', -apple-system, sans-serif;
            background: var(--bg-body);
            color: var(--text-primary);
            transition: background 0.3s ease, color 0.3s ease;
        }

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
            border-radius: 10px;
            border: 2px solid var(--border-color);
            transition: all 0.3s;
            flex: 1;
            max-width: 500px;
        }

        .top-nav .search-wrapper:focus-within {
            border-color: #0B5ED7;
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
            background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
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
            color: #3B82F6;
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
            border-color: #0B5ED7;
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
            color: #0B5ED7;
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

        .notif-dot.has-notif { background: #DC2626; }
        .notif-dot.no-notif { background: #94A3B8; animation: none; }

        @keyframes pulse-dot {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.2); }
        }

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
            border-color: #0B5ED7;
            background: var(--bg-card);
        }

        .dark-toggle-btn i { font-size: 0.9rem; }

        /* ================================================================
           MAIN CONTENT
           ================================================================ */
        .main-content {
            margin-left: 270px;
            margin-top: 68px;
            padding: 20px 24px;
            background: var(--bg-body);
            min-height: 100vh;
            transition: all 0.3s ease;
        }

        /* ================================================================
           PAGE HEADER
           ================================================================ */
        .page-header {
            background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
            border-radius: 16px;
            padding: 24px 32px;
            margin-bottom: 24px;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            box-shadow: 0 4px 20px rgba(11, 94, 215, 0.25);
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

        .page-title {
            color: white;
            font-size: 1.5rem;
            font-weight: 700;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
            position: relative;
            z-index: 1;
        }

        .page-title i {
            font-size: 1.8rem;
            opacity: 0.9;
        }

        .page-subtitle {
            color: rgba(255,255,255,0.85);
            font-size: 0.85rem;
            margin: 4px 0 0 0;
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 8px;
            position: relative;
            z-index: 1;
        }

        .page-subtitle strong {
            color: white;
        }

        .branch-tag {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 2px 12px;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: 500;
        }

        .date-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            color: rgba(255,255,255,0.8);
            font-size: 0.75rem;
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

        /* ================================================================
           BUTTONS
           ================================================================ */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            padding: 8px 16px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.8rem;
            transition: all 0.3s ease;
            cursor: pointer;
            border: none;
            text-decoration: none;
            box-shadow: var(--shadow-sm);
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .btn-sm {
            padding: 5px 10px;
            font-size: 0.65rem;
            border-radius: 6px;
        }

        .btn-view {
            background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
            color: white;
        }

        .btn-view:hover {
            background: linear-gradient(135deg, #0A4CA8, #083C8A);
        }

        .btn-outline {
            background: transparent;
            color: var(--text-secondary);
            border: 1.5px solid var(--border-color);
        }

        .btn-outline:hover {
            background: var(--bg-body);
            border-color: #0B5ED7;
            color: #0B5ED7;
        }

        .btn-blue {
            background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
            color: white;
        }

        .btn-blue:hover {
            background: linear-gradient(135deg, #0A4CA8, #083C8A);
        }

        /* ================================================================
           EMPLOYEE SUMMARY
           ================================================================ */
        .employee-summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 16px;
            margin-bottom: 20px;
        }
        
        .summary-card {
            background: var(--bg-card);
            border-radius: 12px;
            padding: 14px 18px;
            border: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            gap: 12px;
            transition: all 0.3s ease;
            box-shadow: var(--shadow-sm);
        }
        
        .summary-card:hover {
            border-color: #0B5ED7;
            box-shadow: var(--shadow-md);
        }
        
        .summary-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            flex-shrink: 0;
        }
        
        .summary-icon.blue { background: #EFF6FF; color: #0B5ED7; }
        .summary-icon.green { background: #ECFDF5; color: #059669; }
        .summary-icon.purple { background: #F5F3FF; color: #7B2FBE; }
        .summary-icon.orange { background: #FFFBEB; color: #F59E0B; }
        
        [data-theme="dark"] .summary-icon.blue { background: #1E3A5F; color: #6EA8FE; }
        [data-theme="dark"] .summary-icon.green { background: #1A3A2A; color: #34D399; }
        [data-theme="dark"] .summary-icon.purple { background: #2D1B4E; color: #A78BFA; }
        [data-theme="dark"] .summary-icon.orange { background: #3D2E0A; color: #FBBF24; }
        
        .summary-label {
            font-size: 0.6rem;
            color: var(--text-secondary);
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            margin: 0;
        }
        
        .summary-value {
            font-size: 0.95rem;
            font-weight: 600;
            color: var(--text-primary);
            margin: 2px 0;
        }
        
        .summary-sub {
            font-size: 0.7rem;
            color: var(--text-secondary);
            margin: 0;
        }

        /* ================================================================
           CARDS
           ================================================================ */
        .card {
            background: var(--bg-card);
            border-radius: 12px;
            border: 1px solid var(--border-color);
            padding: 16px 18px;
            transition: all 0.3s ease;
            box-shadow: var(--shadow-sm);
            margin-bottom: 16px;
        }

        .card:hover {
            border-color: #0B5ED7;
            box-shadow: var(--shadow-md);
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 10px;
        }

        .card-title {
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--text-primary);
            margin: 0;
            display: flex;
            align-items: center;
        }

        .title-blue { color: #0B5ED7; }
        .title-green { color: #059669; }

        /* ================================================================
           RECENT ACTIVITIES
           ================================================================ */
        .recent-activity-item {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            padding: 8px 12px;
            border-radius: 8px;
            transition: all 0.3s ease;
            border: 1px solid transparent;
        }

        .recent-activity-item:hover {
            background: var(--bg-body);
            border-color: var(--border-color);
        }

        .recent-activity-icon {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: #0B5ED7;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            flex-shrink: 0;
            font-size: 0.65rem;
        }

        .recent-activity-content {
            flex: 1;
            min-width: 0;
        }

        .recent-activity-action {
            font-weight: 600;
            font-size: 0.8rem;
            color: var(--text-primary);
        }

        .recent-activity-details {
            font-size: 0.7rem;
            color: var(--text-secondary);
            word-wrap: break-word;
        }

        .recent-activity-time {
            font-size: 0.6rem;
            color: var(--text-secondary);
            opacity: 0.7;
            white-space: nowrap;
        }

        /* ================================================================
           STAT BARS
           ================================================================ */
        .stat-bar {
            margin-bottom: 6px;
        }
        
        .stat-bar:last-child {
            margin-bottom: 0;
        }
        
        .stat-bar-label {
            font-size: 0.65rem;
            color: var(--text-secondary);
            margin-bottom: 2px;
            font-weight: 500;
            display: flex;
            justify-content: space-between;
        }
        
        .stat-bar-track {
            background: var(--bg-body);
            border-radius: 4px;
            overflow: hidden;
            height: 18px;
            position: relative;
        }
        
        .stat-bar-fill {
            height: 100%;
            background: linear-gradient(90deg, #0B5ED7, #1A73E8);
            border-radius: 4px;
            display: flex;
            align-items: center;
            justify-content: flex-end;
            padding-right: 6px;
            font-size: 0.55rem;
            font-weight: 600;
            color: white;
            transition: width 0.5s ease;
            min-width: 20px;
        }
        
        [data-theme="dark"] .stat-bar-fill {
            background: linear-gradient(90deg, #0A4CA8, #0B5ED7);
        }

        /* ================================================================
           CHART CONTAINER - SMALLER
           ================================================================ */
        .chart-container {
            width: 100%;
            max-height: 160px;
            height: 160px;
            position: relative;
        }

        .chart-container canvas {
            width: 100% !important;
            height: 100% !important;
        }

        /* ================================================================
           FILTER SECTION
           ================================================================ */
        .filter-section {
            background: var(--bg-card);
            border-radius: 12px;
            padding: 14px 18px;
            border: 1px solid var(--border-color);
            margin-bottom: 16px;
        }
        
        .filter-row {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: flex-end;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 3px;
            flex: 1;
            min-width: 130px;
        }
        
        .filter-group label {
            font-size: 0.65rem;
            font-weight: 600;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }
        
        .filter-select,
        .filter-input {
            padding: 5px 10px;
            border: 2px solid var(--border-color);
            border-radius: 6px;
            background: var(--bg-body);
            color: var(--text-primary);
            font-size: 0.75rem;
            outline: none;
            transition: all 0.3s;
            width: 100%;
        }
        
        .filter-select:focus,
        .filter-input:focus {
            border-color: #0B5ED7;
            box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.1);
        }
        
        .filter-actions {
            flex: 0 0 auto;
            flex-direction: row;
            align-items: center;
            gap: 5px;
            min-width: auto;
        }
        
        .filter-actions .btn {
            white-space: nowrap;
        }

        /* ================================================================
           DATA TABLE
           ================================================================ */
        .data-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            font-size: 0.75rem;
        }

        .data-table thead th {
            background: #0B5ED7 !important;
            color: white !important;
            font-weight: 600;
            padding: 8px 12px;
            font-size: 0.6rem;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            border-bottom: none !important;
            text-align: left;
        }

        .data-table thead th:first-child {
            border-radius: 6px 0 0 0;
        }

        .data-table thead th:last-child {
            border-radius: 0 6px 0 0;
        }

        .data-table td {
            padding: 8px 12px;
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

        .action-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 0.6rem;
            font-weight: 500;
            background: #E8F0FE;
            color: #0B5ED7;
        }

        [data-theme="dark"] .action-badge {
            background: #1E3A5F;
            color: #6EA8FE;
        }

        /* ================================================================
           PAGINATION
           ================================================================ */
        .pagination-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0 4px 0;
            border-top: 1px solid var(--border-color);
            margin-top: 10px;
            flex-wrap: wrap;
            gap: 8px;
        }
        
        .pagination-btn {
            padding: 4px 12px;
            border-radius: 6px;
            font-size: 0.7rem;
            font-weight: 500;
            background: var(--bg-body);
            color: var(--text-secondary);
            text-decoration: none;
            border: 1px solid var(--border-color);
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        
        .pagination-btn:hover {
            background: #0B5ED7;
            color: white;
            border-color: #0B5ED7;
        }
        
        .pagination-pages {
            display: flex;
            gap: 3px;
        }
        
        .pagination-page {
            width: 28px;
            height: 28px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 6px;
            font-size: 0.7rem;
            font-weight: 500;
            color: var(--text-secondary);
            text-decoration: none;
            border: 1px solid transparent;
            transition: all 0.3s;
        }
        
        .pagination-page:hover {
            background: var(--bg-body);
            border-color: var(--border-color);
        }
        
        .pagination-page.active {
            background: #0B5ED7;
            color: white;
            border-color: #0B5ED7;
        }

        /* ================================================================
           EMPTY STATE
           ================================================================ */
        .empty-state {
            text-align: center;
            padding: 30px 20px;
            color: var(--text-secondary);
        }

        .empty-state i {
            font-size: 2rem;
            color: var(--border-color);
            margin-bottom: 8px;
        }

        .empty-state .empty-title {
            font-size: 0.95rem;
            font-weight: 600;
            color: var(--text-primary);
            margin: 0 0 4px 0;
        }

        .empty-state .empty-sub {
            font-size: 0.8rem;
            color: var(--text-secondary);
            margin: 0;
        }

        /* ================================================================
           FOOTER
           ================================================================ */
        .footer {
            margin-top: 24px;
            padding: 14px 20px;
            background: var(--bg-card);
            border-radius: 12px;
            border: 1px solid var(--border-color);
            text-align: center;
        }

        .footer p {
            margin: 0;
            font-size: 0.75rem;
            color: var(--text-secondary);
        }

        .footer-brand {
            font-weight: 700;
            color: #0B5ED7;
        }

        /* ================================================================
           RESPONSIVE
           ================================================================ */
        @media (max-width: 1024px) {
            .top-nav { left: 0; }
            .main-content { margin-left: 0; padding: 16px; }
            .top-nav .search-wrapper { max-width: 300px; }
            .grid-cols-3 { grid-template-columns: 1fr 1fr !important; }
        }

        @media (max-width: 768px) {
            .top-nav .search-wrapper { max-width: 180px; }
            .top-nav .datetime { display: none; }
            .main-content { padding: 12px; }
            .page-header { padding: 16px 18px; }
            .page-title { font-size: 1.2rem; }
            .employee-summary { grid-template-columns: 1fr 1fr; }
            .filter-row { flex-direction: column; gap: 8px; }
            .filter-group { min-width: 100%; }
            .filter-actions { flex-direction: row; width: 100%; }
            .filter-actions .btn { flex: 1; }
            .pagination-container { flex-direction: column; gap: 8px; }
            .page-header { flex-direction: column; align-items: flex-start !important; }
            .data-table { font-size: 0.65rem; }
            .data-table td, .data-table th { padding: 5px 8px; }
            .grid-cols-3 { grid-template-columns: 1fr !important; }
            .recent-activity-item { flex-wrap: wrap; }
            .recent-activity-time { width: 100%; text-align: right; }
        }

        @media (max-width: 480px) {
            .main-content { padding: 10px; }
            .employee-summary { grid-template-columns: 1fr; }
            .page-title { font-size: 1rem; }
            .btn { font-size: 0.7rem; padding: 4px 10px; }
            .btn-sm { font-size: 0.6rem; padding: 3px 6px; }
            .summary-card { padding: 10px 14px; }
            .summary-icon { width: 34px; height: 34px; font-size: 0.85rem; }
            .summary-value { font-size: 0.85rem; }
            .recent-activity-item { padding: 6px 10px; }
        }

        /* ================================================================
           ANIMATIONS
           ================================================================ */
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .animate-fade-in-up {
            animation: fadeInUp 0.4s ease forwards;
            opacity: 0;
        }

        /* ================================================================
           TOAST
           ================================================================ */
        .toast-custom {
            position: fixed;
            bottom: 24px;
            right: 24px;
            padding: 12px 18px;
            border-radius: 12px;
            z-index: 999;
            max-width: 400px;
            transform: translateY(100px);
            opacity: 0;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            align-items: center;
            gap: 10px;
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
           PRINT STYLES
           ================================================================ */
        @media print {
            .top-nav, .sidebar, #sidebarToggle, .btn, .dark-toggle-btn,
            .icon-btn, .search-wrapper, .footer, .filter-section,
            .page-header .flex.gap-2 { display: none !important; }
            .main-content { padding: 0 !important; background: white !important; }
            .card { box-shadow: none !important; border: 1px solid #ddd !important; }
            .page-header {
                background: #0B5ED7 !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            .page-title, .page-subtitle, .branch-tag, .date-badge {
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
                <i class="fas fa-clock"></i>
                Employee Activities
                <span class="role-badge-display">ADMIN</span>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-user"></i>
                <strong><?= htmlspecialchars($employee['full_name']) ?></strong>
                <span class="branch-tag">
                    <i class="fas fa-store-alt"></i> <?= htmlspecialchars($employee['branch_name'] ?? 'N/A') ?>
                </span>
                <span class="date-badge">
                    <i class="fas fa-calendar-day mr-1"></i> <?= date('F d, Y') ?>
                </span>
                <span class="branch-tag" style="background:rgba(255,255,255,0.15);">
                    <i class="fas fa-user"></i> <?= htmlspecialchars($user_full_name) ?>
                </span>
            </p>
        </div>
        <div class="flex gap-2 flex-wrap" style="position:relative;z-index:1;">
            <a href="view_employee.php?id=<?= $employee['id'] ?>&branch=<?= $selected_branch_id ?>" class="btn btn-view btn-sm">
                <i class="fas fa-user"></i> View Employee
            </a>
            <a href="employees.php?branch=<?= $selected_branch_id ?>" class="btn btn-sm" style="background:rgba(255,255,255,0.15);color:white;border:1px solid rgba(255,255,255,0.2);">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- EMPLOYEE SUMMARY -->
    <!-- ================================================================ -->
    <div class="employee-summary animate-fade-in-up">
        <div class="summary-card">
            <div class="summary-icon blue">
                <i class="fas fa-user"></i>
            </div>
            <div>
                <p class="summary-label">Employee</p>
                <p class="summary-value"><?= htmlspecialchars($employee['full_name']) ?></p>
                <p class="summary-sub">@<?= htmlspecialchars($employee['username']) ?></p>
            </div>
        </div>
        <div class="summary-card">
            <div class="summary-icon green">
                <i class="fas fa-briefcase"></i>
            </div>
            <div>
                <p class="summary-label">Role</p>
                <p class="summary-value"><?= $role_display ?></p>
            </div>
        </div>
        <div class="summary-card">
            <div class="summary-icon purple">
                <i class="fas fa-envelope"></i>
            </div>
            <div>
                <p class="summary-label">Email</p>
                <p class="summary-value"><?= htmlspecialchars($employee['email']) ?></p>
            </div>
        </div>
        <div class="summary-card">
            <div class="summary-icon orange">
                <i class="fas fa-list"></i>
            </div>
            <div>
                <p class="summary-label">Total Activities</p>
                <p class="summary-value"><?= number_format($total_activities) ?></p>
            </div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- RECENT ACTIVITIES & STATS & CHART - 3 COLUMN -->
    <!-- ================================================================ -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-4 animate-fade-in-up" style="animation-delay:0.05s;">
        
        <!-- Recent Activities -->
        <div class="card lg:col-span-1">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-clock title-blue mr-2"></i> Recent Activities
                    <span class="text-xs text-gray-400 font-normal">(Last 5)</span>
                </h3>
            </div>
            <?php if (count($recent_activities) > 0): ?>
                <div class="space-y-1 max-h-60 overflow-y-auto">
                    <?php foreach ($recent_activities as $activity): ?>
                        <div class="recent-activity-item">
                            <div class="recent-activity-icon">
                                <i class="fas fa-circle text-[5px]"></i>
                            </div>
                            <div class="recent-activity-content">
                                <div class="recent-activity-action"><?= htmlspecialchars($activity['action'] ?? 'Action') ?></div>
                                <div class="recent-activity-details"><?= htmlspecialchars($activity['details'] ?? '') ?></div>
                            </div>
                            <div class="recent-activity-time">
                                <?= isset($activity['created_at']) ? time_ago($activity['created_at']) : 'Just now' ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <p class="empty-title">No Recent Activities</p>
                    <p class="empty-sub">This employee has no recent activities</p>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Top Actions Stats -->
        <div class="card lg:col-span-1">
            <h3 class="card-title mb-2">
                <i class="fas fa-chart-pie title-blue mr-2"></i> Top Actions
            </h3>
            <?php if (count($action_stats) > 0): ?>
                <div class="space-y-1">
                    <?php $max_count = max(array_column($action_stats, 'count')); ?>
                    <?php foreach ($action_stats as $stat): ?>
                        <div class="stat-bar">
                            <div class="stat-bar-label">
                                <span><?= htmlspecialchars($stat['action']) ?></span>
                                <span><?= $stat['count'] ?></span>
                            </div>
                            <div class="stat-bar-track">
                                <div class="stat-bar-fill" style="width: <?= ($stat['count'] / max(1, $max_count)) * 100 ?>%;">
                                    <?= $stat['count'] ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-chart-pie"></i>
                    <p class="empty-title">No Actions Found</p>
                    <p class="empty-sub">No activity data available</p>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Daily Activity Chart -->
        <div class="card lg:col-span-1">
            <h3 class="card-title mb-2">
                <i class="fas fa-chart-line title-green mr-2"></i> Daily Activity
                <span class="text-xs text-gray-400 font-normal">(Last 30 days)</span>
            </h3>
            <?php if (count($chart_labels) > 0): ?>
                <div class="chart-container">
                    <canvas id="activityChart"></canvas>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-chart-line"></i>
                    <p class="empty-title">No Chart Data</p>
                    <p class="empty-sub">No activity data for the last 30 days</p>
                </div>
            <?php endif; ?>
        </div>
        
    </div>

    <!-- ================================================================ -->
    <!-- FILTERS -->
    <!-- ================================================================ -->
    <div class="filter-section animate-fade-in-up" style="animation-delay:0.1s;">
        <form method="GET" action="" class="filter-form">
            <input type="hidden" name="id" value="<?= $employee['id'] ?>">
            <input type="hidden" name="branch" value="<?= $selected_branch_id ?>">
            
            <div class="filter-row">
                <div class="filter-group">
                    <label>Action Type</label>
                    <select name="action" class="filter-select">
                        <option value="">All Actions</option>
                        <?php foreach ($unique_actions as $action): ?>
                            <option value="<?= htmlspecialchars($action) ?>" <?= $action_filter === $action ? 'selected' : '' ?>>
                                <?= htmlspecialchars($action) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label>Date</label>
                    <input type="date" name="date" class="filter-input" value="<?= $date_filter ?>">
                </div>
                
                <div class="filter-group">
                    <label>Search</label>
                    <input type="text" name="search" class="filter-input" placeholder="Search activities..." value="<?= htmlspecialchars($search_filter) ?>">
                </div>
                
                <div class="filter-group filter-actions">
                    <button type="submit" class="btn btn-blue btn-sm">
                        <i class="fas fa-filter"></i> Apply
                    </button>
                    <a href="employee_activities.php?id=<?= $employee['id'] ?>&branch=<?= $selected_branch_id ?>" class="btn btn-outline btn-sm">
                        <i class="fas fa-undo"></i> Reset
                    </a>
                </div>
            </div>
        </form>
    </div>

    <!-- ================================================================ -->
    <!-- ACTIVITIES TABLE -->
    <!-- ================================================================ -->
    <div class="card animate-fade-in-up" style="animation-delay:0.15s;">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-list title-blue mr-2"></i> Activity Log
                <span class="text-xs text-gray-400 font-normal">(<?= number_format($total_activities) ?> records)</span>
            </h3>
            <span class="text-xs text-gray-400">
                Page <?= $page ?> of <?= $total_pages ?>
            </span>
        </div>
        
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width: 35px;">#</th>
                        <th>Action</th>
                        <th>Details</th>
                        <th style="width: 110px;">IP Address</th>
                        <th style="width: 150px;">Date & Time</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($activities) > 0): ?>
                        <?php $i = $offset + 1; foreach ($activities as $activity): ?>
                            <tr>
                                <td class="text-center text-gray-400"><?= $i++ ?></td>
                                <td>
                                    <span class="action-badge">
                                        <?= htmlspecialchars($activity['action']) ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($activity['details'] ?? '') ?></td>
                                <td class="text-xs font-mono"><?= htmlspecialchars($activity['ip_address'] ?? 'N/A') ?></td>
                                <td class="text-xs">
                                    <?= date('M d, Y', strtotime($activity['created_at'])) ?>
                                    <br>
                                    <span class="text-gray-400"><?= date('h:i:s A', strtotime($activity['created_at'])) ?></span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="text-center text-gray-400 text-sm py-4">
                                <i class="fas fa-inbox text-xl block mb-2"></i>
                                No activities found for this employee
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div class="pagination-container">
                <?php if ($page > 1): ?>
                    <a href="?id=<?= $employee['id'] ?>&branch=<?= $selected_branch_id ?>&page=<?= $page - 1 ?>&action=<?= urlencode($action_filter) ?>&date=<?= urlencode($date_filter) ?>&search=<?= urlencode($search_filter) ?>" 
                       class="pagination-btn">
                        <i class="fas fa-chevron-left"></i> Previous
                    </a>
                <?php endif; ?>
                
                <div class="pagination-pages">
                    <?php for ($p = 1; $p <= $total_pages; $p++): ?>
                        <a href="?id=<?= $employee['id'] ?>&branch=<?= $selected_branch_id ?>&page=<?= $p ?>&action=<?= urlencode($action_filter) ?>&date=<?= urlencode($date_filter) ?>&search=<?= urlencode($search_filter) ?>" 
                           class="pagination-page <?= $p === $page ? 'active' : '' ?>">
                            <?= $p ?>
                        </a>
                    <?php endfor; ?>
                </div>
                
                <?php if ($page < $total_pages): ?>
                    <a href="?id=<?= $employee['id'] ?>&branch=<?= $selected_branch_id ?>&page=<?= $page + 1 ?>&action=<?= urlencode($action_filter) ?>&date=<?= urlencode($date_filter) ?>&search=<?= urlencode($search_filter) ?>" 
                       class="pagination-btn">
                        Next <i class="fas fa-chevron-right"></i>
                    </a>
                <?php endif; ?>
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
            Employee Activities
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
    <i class="fas fa-info-circle" style="font-size:1rem;"></i>
    <div>
        <p style="font-weight:600;font-size:0.8rem;margin:0;" id="toastTitle">Notification</p>
        <p style="font-size:0.7rem;opacity:0.9;margin:0;" id="toastMessage"></p>
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
        }, 3000);
    }

    // ================================================================
    // ACTIVITY CHART - SMALLER SIZE
    // ================================================================
    document.addEventListener('DOMContentLoaded', function() {
        var canvas = document.getElementById('activityChart');
        if (canvas && typeof Chart !== 'undefined') {
            var labels = <?= json_encode($chart_labels) ?>;
            var values = <?= json_encode($chart_values) ?>;
            
            if (labels.length > 0) {
                new Chart(canvas, {
                    type: 'bar',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: 'Activities',
                            data: values,
                            backgroundColor: 'rgba(11, 94, 215, 0.7)',
                            borderColor: '#0B5ED7',
                            borderWidth: 1.5,
                            borderRadius: 3,
                            barPercentage: 0.6
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: false
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        return context.raw + ' activities';
                                    }
                                }
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    stepSize: 1,
                                    font: { size: 9 }
                                },
                                grid: { 
                                    color: 'rgba(0,0,0,0.05)',
                                    drawBorder: false
                                }
                            },
                            x: {
                                grid: { display: false },
                                ticks: { 
                                    font: { size: 8 },
                                    maxRotation: 45,
                                    minRotation: 30
                                }
                            }
                        },
                        animation: {
                            duration: 750
                        }
                    }
                });
            }
        }
    });

    console.log('%c🕐 Braick Dispensary - Employee Activities', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c👤 Admin: <?= htmlspecialchars($user_full_name) ?> (ID: <?= $user_id ?>)', 'font-size:13px; color:#059669;');
    console.log('%c👤 Employee: <?= htmlspecialchars($employee['full_name']) ?>', 'font-size:13px; color:#059669;');
    console.log('%c📊 Total Activities: <?= number_format($total_activities) ?>', 'font-size:13px; color:#0B5ED7;');
    console.log('%c📄 Page: <?= $page ?> of <?= $total_pages ?>', 'font-size:13px; color:#7B2FBE;');
    console.log('%c🔒 Login session: ACTIVE', 'font-size:13px; color:#34D399;');
    console.log('%c📊 Chart size reduced to 160px max-height', 'font-size:13px; color:#F59E0B;');
</script>

</body>
</html>