<?php
// ================================================================
// FILE: frontend/pages/pharmacy/notifications.php
// PHARMACY - VIEW NOTIFICATIONS
// BRAICK DISPENSARY - BLUE THEME
// ================================================================

session_start();

// ================================================================
// FORCE SESSION - Default to Pharmacy Dodoma (ID: 9)
// ================================================================
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'pharmacy') {
    $_SESSION['user_id'] = 9;
    $_SESSION['full_name'] = 'Pharmacy Dodoma';
    $_SESSION['role'] = 'pharmacy';
    $_SESSION['branch_id'] = 1;
    $_SESSION['branch_name'] = 'Dodoma';
    $_SESSION['username'] = 'pharm.dodoma';
    $_SESSION['email'] = 'pharm.dodoma@braick.com';
    $_SESSION['phone'] = '+255 700 000 015';
    $_SESSION['is_admin'] = false;
}

// Include database
require_once __DIR__ . '/../../../backend/config/config.php';
require_once __DIR__ . '/../../../backend/config/database.php';

$user_id = $_SESSION['user_id'] ?? 9;
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$user_full_name = $_SESSION['full_name'] ?? 'Pharmacy Dodoma';

$db = Database::getInstance()->getConnection();

// ================================================================
// GET PARAMETERS
// ================================================================
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all'; // all, unread, read
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
$offset = isset($_GET['page']) ? ((int)$_GET['page'] - 1) * $limit : 0;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;

// ================================================================
// GET NOTIFICATIONS COUNT
// ================================================================
$count_query = "
    SELECT COUNT(*) as total 
    FROM notifications 
    WHERE user_id = ?
";
$count_params = [$user_id];

if ($filter === 'unread') {
    $count_query .= " AND is_read = 0";
} elseif ($filter === 'read') {
    $count_query .= " AND is_read = 1";
}

$stmt = $db->prepare($count_query);
$stmt->execute($count_params);
$total_notifications = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
$total_pages = ceil($total_notifications / $limit);

// ================================================================
// GET NOTIFICATIONS
// ================================================================
$query = "
    SELECT 
        id,
        title,
        message,
        type,
        link,
        is_read,
        created_at
    FROM notifications 
    WHERE user_id = ?
";

if ($filter === 'unread') {
    $query .= " AND is_read = 0";
} elseif ($filter === 'read') {
    $query .= " AND is_read = 1";
}

$query .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";

$stmt = $db->prepare($query);
$stmt->execute([$user_id, $limit, $offset]);
$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// GET UNREAD COUNT
// ================================================================
$stmt = $db->prepare("
    SELECT COUNT(*) as unread 
    FROM notifications 
    WHERE user_id = ? AND is_read = 0
");
$stmt->execute([$user_id]);
$unread_count = $stmt->fetch(PDO::FETCH_ASSOC)['unread'] ?? 0;

// ================================================================
// MARK ALL AS READ
// ================================================================
if (isset($_GET['mark_all_read'])) {
    $stmt = $db->prepare("
        UPDATE notifications 
        SET is_read = 1 
        WHERE user_id = ? AND is_read = 0
    ");
    $stmt->execute([$user_id]);
    
    header('Location: notifications.php');
    exit;
}

// ================================================================
// MARK SINGLE AS READ (AJAX)
// ================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'mark_read') {
    $notification_id = isset($_POST['notification_id']) ? (int)$_POST['notification_id'] : 0;
    
    if ($notification_id > 0) {
        $stmt = $db->prepare("
            UPDATE notifications 
            SET is_read = 1 
            WHERE id = ? AND user_id = ?
        ");
        $stmt->execute([$notification_id, $user_id]);
        
        echo json_encode(['success' => true, 'message' => 'Notification marked as read']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid notification ID']);
    }
    exit;
}

// ================================================================
// DELETE NOTIFICATION (AJAX)
// ================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $notification_id = isset($_POST['notification_id']) ? (int)$_POST['notification_id'] : 0;
    
    if ($notification_id > 0) {
        $stmt = $db->prepare("
            DELETE FROM notifications 
            WHERE id = ? AND user_id = ?
        ");
        $stmt->execute([$notification_id, $user_id]);
        
        echo json_encode(['success' => true, 'message' => 'Notification deleted']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid notification ID']);
    }
    exit;
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
// GET NOTIFICATION TYPE ICON
// ================================================================
function getNotificationIcon($type) {
    $icons = [
        'info' => 'fa-info-circle',
        'success' => 'fa-check-circle',
        'warning' => 'fa-exclamation-triangle',
        'danger' => 'fa-times-circle',
        'bill' => 'fa-file-invoice',
        'prescription' => 'fa-prescription',
        'lab' => 'fa-flask',
        'payment' => 'fa-money-bill-wave'
    ];
    return $icons[$type] ?? 'fa-bell';
}

function getNotificationColor($type) {
    $colors = [
        'info' => 'blue',
        'success' => 'green',
        'warning' => 'orange',
        'danger' => 'red',
        'bill' => 'blue',
        'prescription' => 'purple',
        'lab' => 'teal',
        'payment' => 'green'
    ];
    return $colors[$type] ?? 'gray';
}

function getTimeAgo($datetime) {
    $timestamp = strtotime($datetime);
    $diff = time() - $timestamp;
    
    if ($diff < 60) {
        return $diff . ' seconds ago';
    } elseif ($diff < 3600) {
        return floor($diff / 60) . ' minutes ago';
    } elseif ($diff < 86400) {
        return floor($diff / 3600) . ' hours ago';
    } elseif ($diff < 604800) {
        return floor($diff / 86400) . ' days ago';
    } else {
        return date('M d, Y', $timestamp);
    }
}

// ================================================================
// LOGO PATH
// ================================================================
$logo_url = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

// ================================================================
// INCLUDE SHARED HEADER & SIDEBAR
// ================================================================
include_once __DIR__ . '/../../components/pharmacy_header.php';
include_once __DIR__ . '/../../components/pharmacy_sidebar.php';
?>

<!DOCTYPE html>
<html lang="en" data-theme="<?= isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - Braick Dispensary</title>
    
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
            --primary-gradient-strong: linear-gradient(135deg, #0A4CA8, #073B8A);
            
            --success: #059669;
            --success-bg: #D1FAE5;
            --danger: #DC2626;
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
            --primary-dark: #2563EB;
            --primary-light: #60A5FA;
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
           FILTER TABS
           ================================================================ */
        .filter-tabs {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-bottom: 20px;
            align-items: center;
        }
        
        .filter-tab {
            padding: 6px 18px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--text-secondary);
            text-decoration: none;
            transition: all 0.3s ease;
            background: var(--bg-card);
            border: 2px solid var(--border-color);
        }
        
        .filter-tab:hover {
            border-color: var(--primary);
            color: var(--primary);
        }
        
        .filter-tab.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
            box-shadow: 0 2px 8px rgba(11, 94, 215, 0.25);
        }
        
        .filter-tab .tab-badge {
            font-size: 0.6rem;
            padding: 1px 8px;
            border-radius: 20px;
            background: rgba(255,255,255,0.2);
            color: white;
            margin-left: 4px;
        }
        
        .filter-tab:not(.active) .tab-badge {
            background: var(--gray-200);
            color: var(--gray-500);
        }
        
        .filter-tab .tab-badge.unread-badge {
            background: #DC2626;
            color: white;
        }
        
        /* ================================================================
           NOTIFICATION LIST
           ================================================================ */
        .notification-list {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            border: 1px solid var(--border-color);
            overflow: hidden;
            box-shadow: var(--shadow-sm);
        }
        
        .notification-item {
            display: flex;
            align-items: flex-start;
            gap: 16px;
            padding: 16px 20px;
            border-bottom: 1px solid var(--border-color);
            transition: all 0.3s ease;
            position: relative;
        }
        
        .notification-item:hover {
            background: var(--gray-50);
        }
        
        [data-theme="dark"] .notification-item:hover {
            background: var(--gray-700);
        }
        
        .notification-item:last-child {
            border-bottom: none;
        }
        
        .notification-item.unread {
            background: var(--primary-bg);
            border-left: 4px solid var(--primary);
        }
        
        [data-theme="dark"] .notification-item.unread {
            background: #1E3A5F;
            border-left-color: #3B82F6;
        }
        
        .notification-item .notif-icon {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            flex-shrink: 0;
            margin-top: 2px;
        }
        
        .notification-item .notif-icon.blue { background: var(--primary-bg); color: var(--primary); }
        .notification-item .notif-icon.green { background: var(--success-bg); color: var(--success); }
        .notification-item .notif-icon.orange { background: var(--warning-bg); color: var(--warning); }
        .notification-item .notif-icon.red { background: var(--danger-bg); color: var(--danger); }
        .notification-item .notif-icon.purple { background: var(--purple-bg); color: var(--purple); }
        .notification-item .notif-icon.teal { background: var(--teal-bg); color: var(--teal); }
        
        [data-theme="dark"] .notification-item .notif-icon.blue { background: #1E3A5F; color: #3B82F6; }
        [data-theme="dark"] .notification-item .notif-icon.green { background: #1A3A2A; color: #34D399; }
        [data-theme="dark"] .notification-item .notif-icon.orange { background: #3D2E0A; color: #FBBF24; }
        [data-theme="dark"] .notification-item .notif-icon.red { background: #3A1A1A; color: #F87171; }
        [data-theme="dark"] .notification-item .notif-icon.purple { background: #2D1B4E; color: #A78BFA; }
        [data-theme="dark"] .notification-item .notif-icon.teal { background: #0F3D3D; color: #5EEAD4; }
        
        .notification-item .notif-content {
            flex: 1;
            min-width: 0;
        }
        
        .notification-item .notif-title {
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 2px;
        }
        
        .notification-item .notif-message {
            font-size: 0.82rem;
            color: var(--text-secondary);
            line-height: 1.5;
            word-wrap: break-word;
        }
        
        .notification-item .notif-time {
            font-size: 0.65rem;
            color: var(--text-secondary);
            margin-top: 4px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .notification-item .notif-actions {
            display: flex;
            gap: 6px;
            margin-top: 6px;
            flex-wrap: wrap;
        }
        
        .notification-item .notif-actions .btn-sm {
            padding: 2px 10px;
            font-size: 0.6rem;
            border-radius: 4px;
            border: 1px solid var(--border-color);
            background: transparent;
            color: var(--text-secondary);
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
        }
        
        .notification-item .notif-actions .btn-sm:hover {
            border-color: var(--primary);
            color: var(--primary);
        }
        
        .notification-item .notif-actions .btn-sm.primary {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }
        
        .notification-item .notif-actions .btn-sm.primary:hover {
            background: var(--primary-dark);
            border-color: var(--primary-dark);
        }
        
        .notification-item .notif-actions .btn-sm.danger:hover {
            border-color: var(--danger);
            color: var(--danger);
        }
        
        .notification-item .notif-unread-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: var(--primary);
            flex-shrink: 0;
            margin-top: 2px;
            animation: pulse-dot 2s infinite;
        }
        
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
            display: block;
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
           PAGINATION
           ================================================================ */
        .pagination {
            display: flex;
            gap: 4px;
            justify-content: center;
            padding: 16px 0;
            flex-wrap: wrap;
        }
        
        .pagination .page-link {
            padding: 6px 14px;
            border-radius: 6px;
            border: 1px solid var(--border-color);
            color: var(--text-primary);
            text-decoration: none;
            font-size: 0.8rem;
            transition: all 0.3s;
            background: var(--bg-card);
        }
        
        .pagination .page-link:hover {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }
        
        .pagination .page-link.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }
        
        .pagination .page-link.disabled {
            opacity: 0.5;
            cursor: not-allowed;
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
            box-shadow: 0 10px 40px rgba(0,0,0,0.15);
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
            .notification-item { padding: 12px 14px; }
            .notification-item .notif-icon { width: 36px; height: 36px; font-size: 0.9rem; }
        }
        
        @media (max-width: 480px) {
            .main-content { padding: 10px; }
            .page-header { flex-direction: column; align-items: flex-start !important; }
            .filter-tabs { gap: 4px; }
            .filter-tab { padding: 4px 12px; font-size: 0.7rem; }
            .notification-item { flex-direction: column; }
            .notification-item .notif-unread-dot { align-self: flex-start; }
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
            .filter-tabs, .footer, #sidebarToggle { display: none !important; }
            .main-content { margin: 0; padding: 20px; }
            .notification-list { break-inside: avoid; box-shadow: none !important; border: 1px solid #ddd; }
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
            <input type="text" id="searchInput" placeholder="Search notifications...">
            <button id="searchBtn" class="search-btn">
                <i class="fas fa-search mr-1"></i> Search
            </button>
        </div>
    </div>
    
    <div class="flex items-center gap-3">
        <span class="branch-badge-display" style="background:var(--primary-bg);color:var(--primary);padding:4px 14px;border-radius:20px;font-size:0.78rem;font-weight:500;">
            <i class="fas fa-store-alt mr-1"></i> <?= htmlspecialchars($branch_name) ?>
        </span>
        
        <span class="datetime" id="currentDateTime"></span>
        
        <button id="darkModeToggle" class="dark-toggle-btn" title="Toggle Dark Mode">
            <i id="darkIcon" class="fas fa-moon"></i>
            <span id="darkText">Dark</span>
        </button>
        
        <button class="icon-btn" id="notifBell">
            <i class="fas fa-bell text-lg"></i>
            <span class="notif-dot <?= $unread_count > 0 ? 'has-notif' : 'no-notif' ?>"></span>
        </button>
        
        <a href="profile.php">
            <img src="<?= $logo_url ?>" alt="Profile" class="avatar"
                 onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2240%22 height=%2240%22%3E%3Crect width=%2240%22 height=%2240%22 fill=%22%230B5ED7%22 rx=%2250%25%22/%3E%3Ctext x=%2220%22 y=%2226%22 text-anchor=%22middle%22 fill=%22white%22 font-size=%2218%22 font-weight=%22bold%22%3EA%3C/text%3E%3C/svg%3E'">
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
                <i class="fas fa-bell"></i>
                Notifications
                <span class="role-badge-display">PHARMACY</span>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-envelope"></i>
                <strong><?= $total_notifications ?></strong> notifications
                <?php if ($unread_count > 0): ?>
                    <span class="header-badge" style="background:rgba(220,38,38,0.2);border-color:rgba(220,38,38,0.3);color:#F87171;">
                        <i class="fas fa-circle"></i> <?= $unread_count ?> unread
                    </span>
                <?php endif; ?>
                <span class="header-badge" style="background:rgba(52,211,153,0.2);border-color:rgba(52,211,153,0.3);color:#34D399;">
                    <i class="fas fa-check-circle"></i> <?= $total_notifications - $unread_count ?> read
                </span>
            </p>
        </div>
        <div class="flex gap-2 flex-wrap" style="position:relative;z-index:1;">
            <?php if ($unread_count > 0): ?>
                <a href="?mark_all_read=1" class="btn-outline-light" onclick="return confirm('Mark all notifications as read?')">
                    <i class="fas fa-check-double"></i> Mark All Read
                </a>
            <?php endif; ?>
            <a href="dashboard.php" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Dashboard
            </a>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- FILTER TABS -->
    <!-- ================================================================ -->
    <div class="filter-tabs">
        <a href="notifications.php?filter=all" class="filter-tab <?= $filter === 'all' ? 'active' : '' ?>">
            <i class="fas fa-list"></i> All
            <span class="tab-badge"><?= $total_notifications ?></span>
        </a>
        <a href="notifications.php?filter=unread" class="filter-tab <?= $filter === 'unread' ? 'active' : '' ?>">
            <i class="fas fa-circle" style="color:#DC2626;font-size:0.6rem;"></i> Unread
            <span class="tab-badge unread-badge"><?= $unread_count ?></span>
        </a>
        <a href="notifications.php?filter=read" class="filter-tab <?= $filter === 'read' ? 'active' : '' ?>">
            <i class="fas fa-check-circle"></i> Read
            <span class="tab-badge"><?= $total_notifications - $unread_count ?></span>
        </a>
    </div>

    <!-- ================================================================ -->
    <!-- NOTIFICATION LIST -->
    <!-- ================================================================ -->
    <div class="notification-list animate-fade-in-up" id="notificationList">
        <?php if (count($notifications) > 0): ?>
            <?php foreach ($notifications as $notif): 
                $is_unread = $notif['is_read'] == 0;
                $type = $notif['type'] ?? 'info';
                $icon = getNotificationIcon($type);
                $color = getNotificationColor($type);
                $time_ago = getTimeAgo($notif['created_at']);
                $link = $notif['link'] ?? '#';
            ?>
                <div class="notification-item <?= $is_unread ? 'unread' : '' ?>" data-id="<?= $notif['id'] ?>">
                    <div class="notif-icon <?= $color ?>">
                        <i class="fas <?= $icon ?>"></i>
                    </div>
                    
                    <div class="notif-content">
                        <div class="notif-title">
                            <?= htmlspecialchars($notif['title'] ?? 'Notification') ?>
                            <?php if ($is_unread): ?>
                                <span class="notif-unread-dot"></span>
                            <?php endif; ?>
                        </div>
                        <div class="notif-message">
                            <?= htmlspecialchars($notif['message'] ?? '') ?>
                        </div>
                        <div class="notif-time">
                            <span><i class="far fa-clock"></i> <?= $time_ago ?></span>
                            <?php if (!empty($link) && $link !== '#'): ?>
                                <span>|</span>
                                <a href="<?= htmlspecialchars($link) ?>" class="text-blue-600 hover:underline text-xs">View Details</a>
                            <?php endif; ?>
                        </div>
                        <div class="notif-actions">
                            <?php if ($is_unread): ?>
                                <button class="btn-sm primary" onclick="markRead(<?= $notif['id'] ?>)">
                                    <i class="fas fa-check"></i> Mark as Read
                                </button>
                            <?php endif; ?>
                            <button class="btn-sm danger" onclick="deleteNotification(<?= $notif['id'] ?>)">
                                <i class="fas fa-trash"></i> Delete
                            </button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-bell-slash"></i>
                <h3>No Notifications</h3>
                <p>
                    <?php if ($filter === 'unread'): ?>
                        You have no unread notifications. Great job! 🎉
                    <?php elseif ($filter === 'read'): ?>
                        You have no read notifications yet.
                    <?php else: ?>
                        No notifications found. You're all caught up! 🎉
                    <?php endif; ?>
                </p>
            </div>
        <?php endif; ?>
    </div>

    <!-- ================================================================ -->
    <!-- PAGINATION -->
    <!-- ================================================================ -->
    <?php if ($total_pages > 1): ?>
        <div class="pagination">
            <?php if ($page > 1): ?>
                <a href="?page=<?= $page - 1 ?>&filter=<?= $filter ?>" class="page-link">
                    <i class="fas fa-chevron-left"></i>
                </a>
            <?php else: ?>
                <span class="page-link disabled">
                    <i class="fas fa-chevron-left"></i>
                </span>
            <?php endif; ?>
            
            <?php for ($p = 1; $p <= $total_pages; $p++): ?>
                <a href="?page=<?= $p ?>&filter=<?= $filter ?>" 
                   class="page-link <?= $p === $page ? 'active' : '' ?>">
                    <?= $p ?>
                </a>
            <?php endfor; ?>
            
            <?php if ($page < $total_pages): ?>
                <a href="?page=<?= $page + 1 ?>&filter=<?= $filter ?>" class="page-link">
                    <i class="fas fa-chevron-right"></i>
                </a>
            <?php else: ?>
                <span class="page-link disabled">
                    <i class="fas fa-chevron-right"></i>
                </span>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Notifications
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
        if (query.length > 0) {
            window.location.href = 'notifications.php?search=' + encodeURIComponent(query);
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

    // ================================================================
    // MARK AS READ
    // ================================================================
    function markRead(notificationId) {
        var formData = new FormData();
        formData.append('action', 'mark_read');
        formData.append('notification_id', notificationId);
        
        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(function(response) { return response.json(); })
        .then(function(data) {
            if (data.success) {
                var item = document.querySelector('.notification-item[data-id="' + notificationId + '"]');
                if (item) {
                    item.classList.remove('unread');
                    var dot = item.querySelector('.notif-unread-dot');
                    if (dot) dot.remove();
                    
                    // Update counts
                    updateCounts();
                    showToast('✅ Success', data.message, 'success');
                }
            } else {
                showToast('❌ Error', data.message, 'error');
            }
        })
        .catch(function(error) {
            showToast('❌ Error', 'Network error: ' + error.message, 'error');
        });
    }

    // ================================================================
    // DELETE NOTIFICATION
    // ================================================================
    function deleteNotification(notificationId) {
        if (!confirm('Delete this notification?')) return;
        
        var formData = new FormData();
        formData.append('action', 'delete');
        formData.append('notification_id', notificationId);
        
        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(function(response) { return response.json(); })
        .then(function(data) {
            if (data.success) {
                var item = document.querySelector('.notification-item[data-id="' + notificationId + '"]');
                if (item) {
                    item.style.transition = 'all 0.3s ease';
                    item.style.opacity = '0';
                    item.style.transform = 'translateX(-20px)';
                    setTimeout(function() {
                        item.remove();
                        // Check if list is empty
                        var list = document.getElementById('notificationList');
                        if (list.children.length === 0) {
                            list.innerHTML = `
                                <div class="empty-state">
                                    <i class="fas fa-bell-slash"></i>
                                    <h3>No Notifications</h3>
                                    <p>All notifications have been cleared. 🎉</p>
                                </div>
                            `;
                        }
                        updateCounts();
                    }, 300);
                    showToast('✅ Success', data.message, 'success');
                }
            } else {
                showToast('❌ Error', data.message, 'error');
            }
        })
        .catch(function(error) {
            showToast('❌ Error', 'Network error: ' + error.message, 'error');
        });
    }

    // ================================================================
    // UPDATE COUNTS
    // ================================================================
    function updateCounts() {
        var items = document.querySelectorAll('.notification-item');
        var unreadItems = document.querySelectorAll('.notification-item.unread');
        var total = items.length;
        var unread = unreadItems.length;
        var read = total - unread;
        
        // Update header
        var headerBadges = document.querySelectorAll('.header-badge');
        headerBadges.forEach(function(badge) {
            var text = badge.textContent.trim();
            if (text.includes('unread')) {
                badge.innerHTML = '<i class="fas fa-circle"></i> ' + unread + ' unread';
            }
            if (text.includes('read')) {
                badge.innerHTML = '<i class="fas fa-check-circle"></i> ' + read + ' read';
            }
        });
        
        // Update filter tabs
        var tabs = document.querySelectorAll('.filter-tab');
        tabs.forEach(function(tab) {
            var badge = tab.querySelector('.tab-badge');
            if (badge) {
                if (tab.href.includes('filter=unread')) {
                    badge.textContent = unread;
                } else if (tab.href.includes('filter=read')) {
                    badge.textContent = read;
                } else {
                    badge.textContent = total;
                }
            }
        });
        
        // Update notification dot
        var dot = document.querySelector('.notif-dot');
        if (dot) {
            if (unread > 0) {
                dot.className = 'notif-dot has-notif';
            } else {
                dot.className = 'notif-dot no-notif';
            }
        }
    }

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

    // ================================================================
    // KEYBOARD SHORTCUTS
    // ================================================================
    document.addEventListener('keydown', function(e) {
        if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
            e.preventDefault();
            if (searchInput) {
                searchInput.focus();
                searchInput.select();
            }
        }
        if (e.key === 'Escape') {
            window.location.href = 'dashboard.php';
        }
    });

    console.log('%c🔔 Braick Dispensary - Notifications', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c📬 Total: <?= $total_notifications ?> | Unread: <?= $unread_count ?>', 'font-size:13px; color:#64748B;');
    console.log('%c👤 User: <?= htmlspecialchars($user_full_name) ?> (ID: <?= $user_id ?>)', 'font-size:13px; color:#059669;');
    console.log('%c🏢 Branch: <?= htmlspecialchars($branch_name) ?>', 'font-size:13px; color:#7C3AED;');
    console.log('%c🔵 Blue Theme Applied', 'font-size:13px; color:#0B5ED7;');
</script>

</body>
</html>