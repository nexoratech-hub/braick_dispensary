<?php
// ================================================================
// FILE: frontend/pages/cashier/notifications.php
// CASHIER / RECEPTION - NOTIFICATIONS
// SHOWS: Notifications based on logged-in user (user_id)
// ================================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: ../login.php');
    exit;
}

$allowed_roles = ['cashier', 'reception', 'admin', 'doctor', 'pharmacy', 'laboratory'];
if (!in_array($_SESSION['role'], $allowed_roles)) {
    header('Location: ../login.php');
    exit;
}

$user_id = $_SESSION['user_id'] ?? 0;
$user_full_name = $_SESSION['full_name'] ?? 'User';
$user_role = $_SESSION['role'] ?? 'cashier';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$profile_pic = $_SESSION['profile_pic'] ?? '';

$is_admin = ($user_role === 'admin');
$is_reception = ($user_role === 'reception');

require_once __DIR__ . '/../../../backend/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

$message = '';
$message_type = '';
$currency = 'TSh';

// ================================================================
// GET FILTER PARAMETERS
// ================================================================
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all'; // all, unread, read
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// ================================================================
// GET SYSTEM SETTINGS
// ================================================================
try {
    $settings = [];
    $stmt = $db->query("SELECT setting_key, setting_value FROM system_settings");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    $currency = $settings['currency'] ?? 'TSh';
} catch (Exception $e) {
    $currency = 'TSh';
}

// ================================================================
// HANDLE ACTIONS
// ================================================================

// Mark single notification as read
if (isset($_GET['mark_read']) && is_numeric($_GET['mark_read'])) {
    $notif_id = (int)$_GET['mark_read'];
    try {
        $stmt = $db->prepare("UPDATE notifications SET is_read = 1, updated_at = NOW() WHERE id = ? AND user_id = ?");
        $stmt->execute([$notif_id, $user_id]);
        $_SESSION['flash_message'] = "✅ Notification marked as read!";
        $_SESSION['flash_type'] = 'success';
    } catch (Exception $e) {
        $_SESSION['flash_message'] = "❌ Error: " . $e->getMessage();
        $_SESSION['flash_type'] = 'error';
    }
    header('Location: notifications.php' . (!empty($_GET['filter']) ? '?filter=' . $_GET['filter'] : ''));
    exit;
}

// Mark all as read
if (isset($_GET['mark_all_read'])) {
    try {
        $stmt = $db->prepare("UPDATE notifications SET is_read = 1, updated_at = NOW() WHERE user_id = ? AND is_read = 0");
        $stmt->execute([$user_id]);
        $count = $stmt->rowCount();
        $_SESSION['flash_message'] = "✅ All notifications marked as read! ({$count} notifications)";
        $_SESSION['flash_type'] = 'success';
    } catch (Exception $e) {
        $_SESSION['flash_message'] = "❌ Error: " . $e->getMessage();
        $_SESSION['flash_type'] = 'error';
    }
    header('Location: notifications.php' . (!empty($_GET['filter']) ? '?filter=' . $_GET['filter'] : ''));
    exit;
}

// Delete notification
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $notif_id = (int)$_GET['delete'];
    try {
        $stmt = $db->prepare("DELETE FROM notifications WHERE id = ? AND user_id = ?");
        $stmt->execute([$notif_id, $user_id]);
        $_SESSION['flash_message'] = "✅ Notification deleted!";
        $_SESSION['flash_type'] = 'success';
    } catch (Exception $e) {
        $_SESSION['flash_message'] = "❌ Error: " . $e->getMessage();
        $_SESSION['flash_type'] = 'error';
    }
    header('Location: notifications.php' . (!empty($_GET['filter']) ? '?filter=' . $_GET['filter'] : ''));
    exit;
}

// Delete all
if (isset($_GET['delete_all'])) {
    try {
        $stmt = $db->prepare("DELETE FROM notifications WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $count = $stmt->rowCount();
        $_SESSION['flash_message'] = "✅ All notifications deleted! ({$count} notifications removed)";
        $_SESSION['flash_type'] = 'success';
    } catch (Exception $e) {
        $_SESSION['flash_message'] = "❌ Error: " . $e->getMessage();
        $_SESSION['flash_type'] = 'error';
    }
    header('Location: notifications.php');
    exit;
}

// ================================================================
// GET FLASH MESSAGES
// ================================================================
if (isset($_SESSION['flash_message'])) {
    $message = $_SESSION['flash_message'];
    $message_type = $_SESSION['flash_type'] ?? 'info';
    unset($_SESSION['flash_message']);
    unset($_SESSION['flash_type']);
}

// ================================================================
// BUILD QUERY CONDITIONS - Based on logged-in user
// ================================================================
$conditions = [];
$params = [];

// Show notifications for the logged-in user only
$conditions[] = "user_id = ?";
$params[] = $user_id;

if ($filter === 'unread') {
    $conditions[] = "is_read = 0";
} elseif ($filter === 'read') {
    $conditions[] = "is_read = 1";
}

if (!empty($search)) {
    $conditions[] = "(title LIKE ? OR message LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$where_clause = "WHERE " . implode(" AND ", $conditions);

// ================================================================
// GET NOTIFICATIONS
// ================================================================
try {
    $sql = "SELECT * FROM notifications $where_clause ORDER BY created_at DESC";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Count unread for this user
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$user_id]);
    $unread_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    
} catch (Exception $e) {
    $message = "Database error: " . $e->getMessage();
    $message_type = 'error';
    $notifications = [];
    $unread_count = 0;
}

// ================================================================
// GET USER INFO FOR DISPLAY
// ================================================================
$user_display_name = $user_full_name;
$user_role_display = ucfirst($user_role);

$logo_path = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';
$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

include_once '../../components/cashier_header.php';
include_once '../../components/cashier_sidebar.php';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - Braick Dispensary</title>
    
    <link rel="icon" href="<?= $logo_path ?>" type="image/png">
    <link rel="shortcut icon" href="<?= $logo_path ?>" type="image/png">
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <style>
        :root {
            --primary: #0B5ED7;
            --primary-dark: #0A4CA8;
            --primary-light: #6EA8FE;
            --primary-bg: #E8F0FE;
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
            --info: #0B5ED7;
            --info-bg: #E8F0FE;
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
            --shadow-md: 0 4px 6px rgba(0,0,0,0.07);
            --shadow-lg: 0 10px 15px rgba(0,0,0,0.1);
            --bg-body: #F1F5F9;
            --bg-card: #FFFFFF;
            --bg-nav: #FFFFFF;
            --text-primary: #1E293B;
            --text-secondary: #64748B;
            --border-color: #E2E8F0;
            --table-hover: #D1FAE5;
        }
        
        [data-theme="dark"] {
            --bg-body: #0F172A;
            --bg-card: #1E293B;
            --bg-nav: #1E293B;
            --text-primary: #F1F5F9;
            --text-secondary: #94A3B8;
            --border-color: #334155;
            --shadow: 0 1px 3px rgba(0,0,0,0.3);
            --shadow-md: 0 4px 12px rgba(0,0,0,0.3);
            --shadow-lg: 0 10px 25px rgba(0,0,0,0.4);
            --table-hover: #1A3A2A;
            --shadow-sm: 0 1px 2px rgba(0,0,0,0.3);
        }

        [data-theme="dark"] .bg-white { background-color: #1E293B !important; }
        [data-theme="dark"] .text-gray-700 { color: #CBD5E1 !important; }
        [data-theme="dark"] .text-gray-800 { color: #E2E8F0 !important; }
        [data-theme="dark"] .text-gray-900 { color: #F1F5F9 !important; }
        [data-theme="dark"] .border-gray-200 { border-color: #334155 !important; }
        [data-theme="dark"] .bg-gray-50 { background-color: #1E293B !important; }
        [data-theme="dark"] .bg-gray-100 { background-color: #2D3748 !important; }
        [data-theme="dark"] .shadow { box-shadow: 0 1px 3px rgba(0,0,0,0.3) !important; }
        [data-theme="dark"] .shadow-md { box-shadow: 0 4px 12px rgba(0,0,0,0.3) !important; }
        [data-theme="dark"] .shadow-lg { box-shadow: 0 10px 25px rgba(0,0,0,0.4) !important; }
        [data-theme="dark"] .filter-btn { border-color: #334155; color: #94A3B8; }
        [data-theme="dark"] .filter-btn:hover { border-color: #34D399; color: #34D399; background: rgba(5, 150, 105, 0.15); }
        [data-theme="dark"] .filter-btn.active { background: #059669; color: white; border-color: #059669; }
        [data-theme="dark"] .filter-btn.active:hover { background: #047857; border-color: #047857; }
        [data-theme="dark"] .card { background: #1E293B; border-color: #334155; }
        [data-theme="dark"] .card:hover { border-color: #34D399; }
        [data-theme="dark"] .page-header { background: linear-gradient(135deg, #0B5ED7, #0A4CA8) !important; }
        [data-theme="dark"] .footer { border-top-color: #334155; }
        [data-theme="dark"] .notification-item:hover { background: #1A3A2A !important; }
        [data-theme="dark"] .notification-item.unread { background: #1E3A5F; border-left-color: #6EA8FE; }
        [data-theme="dark"] .btn-action-primary { background: #0B5ED7; color: white; }
        [data-theme="dark"] .btn-action-primary:hover { background: #1E6AE8; }
        [data-theme="dark"] .btn-action-success { background: #059669; color: white; }
        [data-theme="dark"] .btn-action-success:hover { background: #047857; }
        [data-theme="dark"] .btn-action-danger { background: #DC2626; color: white; }
        [data-theme="dark"] .btn-action-danger:hover { background: #B91C1C; }
        [data-theme="dark"] .btn-action-outline { background: transparent; color: #94A3B8; border-color: #334155; }
        [data-theme="dark"] .btn-action-outline:hover { background: #2D3748; border-color: #34D399; color: #34D399; }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Inter', 'Segoe UI', -apple-system, sans-serif;
            background: var(--bg-body);
            color: var(--text-primary);
            transition: background 0.3s ease, color 0.3s ease;
        }
        
        ::-webkit-scrollbar { width: 5px; height: 5px; }
        ::-webkit-scrollbar-track { background: var(--bg-body); }
        ::-webkit-scrollbar-thumb { background: var(--success); border-radius: 10px; }
        
        .main-content {
            margin-left: 270px;
            margin-top: 68px;
            padding: 28px 32px;
            min-height: calc(100vh - 68px);
        }
        
        .page-header {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border-radius: 16px;
            padding: 24px 32px;
            margin-bottom: 24px;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            box-shadow: 0 4px 20px rgba(11, 94, 215, 0.25);
            position: relative;
            overflow: hidden;
        }
        
        .page-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -20%;
            width: 300px;
            height: 300px;
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
            background: rgba(255,255,255,0.15);
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
        }
        
        .page-header .btn-outline-light {
            background: rgba(255,255,255,0.15);
            color: white;
            border: 1px solid rgba(255,255,255,0.2);
            padding: 8px 18px;
            border-radius: 10px;
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
           ACTION BUTTONS IN HEADER
           ================================================================ */
        .header-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
        }
        
        .btn-action {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            border-radius: 10px;
            font-weight: 500;
            font-size: 0.78rem;
            transition: all 0.3s;
            cursor: pointer;
            border: 1px solid transparent;
            text-decoration: none;
            white-space: nowrap;
        }
        
        .btn-action i {
            font-size: 0.85rem;
        }
        
        .btn-action-primary {
            background: rgba(255,255,255,0.2);
            color: white;
            border-color: rgba(255,255,255,0.2);
        }
        
        .btn-action-primary:hover {
            background: rgba(255,255,255,0.3);
            transform: translateY(-2px);
        }
        
        .btn-action-success {
            background: rgba(5, 150, 105, 0.3);
            color: #34D399;
            border-color: rgba(5, 150, 105, 0.3);
        }
        
        .btn-action-success:hover {
            background: rgba(5, 150, 105, 0.4);
            transform: translateY(-2px);
        }
        
        .btn-action-danger {
            background: rgba(220, 38, 38, 0.25);
            color: #F87171;
            border-color: rgba(220, 38, 38, 0.25);
        }
        
        .btn-action-danger:hover {
            background: rgba(220, 38, 38, 0.35);
            transform: translateY(-2px);
        }
        
        .btn-action-outline {
            background: transparent;
            color: white;
            border-color: rgba(255,255,255,0.2);
        }
        
        .btn-action-outline:hover {
            background: rgba(255,255,255,0.1);
            border-color: rgba(255,255,255,0.3);
            transform: translateY(-2px);
        }
        
        .btn-action:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none !important;
        }
        
        /* ================================================================
           FILTER SECTION
           ================================================================ */
        .filter-section {
            background: var(--bg-card);
            border-radius: 14px;
            padding: 16px 20px;
            border: 1px solid var(--border-color);
            margin-bottom: 20px;
            max-width: 1000px;
            margin-left: auto;
            margin-right: auto;
            box-shadow: var(--shadow-sm);
        }
        
        .filter-section:hover {
            border-color: var(--primary);
            box-shadow: var(--shadow-md);
        }
        
        .filter-btn {
            padding: 5px 14px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 500;
            border: 2px solid var(--border-color);
            background: transparent;
            color: var(--text-secondary);
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }
        
        .filter-btn:hover {
            border-color: var(--primary);
            color: var(--primary);
            background: var(--primary-bg);
        }
        
        .filter-btn.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }
        
        .filter-btn.active:hover {
            background: var(--primary-dark);
            border-color: var(--primary-dark);
        }
        
        .filter-btn i {
            margin-right: 4px;
        }
        
        .filter-group {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 6px;
        }
        
        .filter-group .filter-label {
            font-size: 0.7rem;
            font-weight: 600;
            color: var(--text-secondary);
            margin-right: 4px;
        }
        
        .filter-search-input {
            padding: 4px 10px;
            border: 2px solid var(--border-color);
            border-radius: 8px;
            font-size: 0.75rem;
            background: var(--bg-card);
            color: var(--text-primary);
            outline: none;
            width: 180px;
            transition: all 0.3s;
        }
        
        .filter-search-input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.1);
        }
        
        .btn-apply {
            padding: 4px 14px;
            border-radius: 8px;
            font-size: 0.75rem;
            font-weight: 600;
            background: var(--primary);
            color: white;
            border: none;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .btn-apply:hover {
            background: var(--primary-dark);
            transform: translateY(-1px);
        }
        
        .btn-apply-danger {
            background: var(--danger);
            color: white;
        }
        
        .btn-apply-danger:hover {
            background: var(--danger-dark);
        }
        
        /* ================================================================
           CARD
           ================================================================ */
        .card {
            background: var(--bg-card);
            border-radius: 16px;
            padding: 0;
            border: 1px solid var(--border-color);
            transition: all 0.3s;
            box-shadow: var(--shadow-sm);
            max-width: 1000px;
            margin: 0 auto;
            overflow: hidden;
        }
        
        .card:hover {
            border-color: var(--primary);
            box-shadow: var(--shadow-md);
        }
        
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 16px 24px;
            border-bottom: 1px solid var(--border-color);
            flex-wrap: wrap;
            gap: 8px;
            background: var(--bg-body);
        }
        
        .card-title {
            font-size: 0.95rem;
            font-weight: 600;
            color: var(--text-primary);
        }
        
        .card-body {
            padding: 0;
        }
        
        /* ================================================================
           NOTIFICATION ITEMS
           ================================================================ */
        .notification-item {
            display: flex;
            align-items: flex-start;
            gap: 16px;
            padding: 14px 24px;
            border-bottom: 1px solid var(--border-color);
            transition: background 0.2s ease;
            cursor: default;
            position: relative;
        }
        
        .notification-item:last-child {
            border-bottom: none;
        }
        
        .notification-item:hover {
            background: var(--table-hover);
        }
        
        .notification-item.unread {
            background: var(--primary-bg);
            border-left: 4px solid var(--primary);
        }
        
        .notification-item .notif-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            font-size: 1.1rem;
            color: white;
        }
        
        .notification-item .notif-icon.info { background: var(--primary); }
        .notification-item .notif-icon.success { background: var(--success); }
        .notification-item .notif-icon.warning { background: var(--warning); }
        .notification-item .notif-icon.danger { background: var(--danger); }
        
        .notification-item .notif-content {
            flex: 1;
            min-width: 0;
        }
        
        .notification-item .notif-content .notif-title {
            font-weight: 600;
            font-size: 0.85rem;
            color: var(--text-primary);
            margin-bottom: 2px;
        }
        
        .notification-item .notif-content .notif-message {
            font-size: 0.75rem;
            color: var(--text-secondary);
            word-wrap: break-word;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .notification-item .notif-content .notif-message.expanded {
            -webkit-line-clamp: unset;
            overflow: visible;
        }
        
        .notification-item .notif-content .notif-time {
            font-size: 0.6rem;
            color: var(--text-secondary);
            opacity: 0.6;
            margin-top: 2px;
        }
        
        .notification-item .notif-actions {
            display: flex;
            gap: 4px;
            flex-shrink: 0;
            margin-top: 2px;
        }
        
        .notification-item .notif-actions .btn-icon {
            width: 28px;
            height: 28px;
            border-radius: 6px;
            border: none;
            background: transparent;
            color: var(--text-secondary);
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.7rem;
        }
        
        .notification-item .notif-actions .btn-icon:hover {
            background: var(--bg-body);
            color: var(--primary);
        }
        
        .notification-item .notif-actions .btn-icon.danger:hover {
            background: var(--danger-bg);
            color: var(--danger);
        }
        
        .notification-item .notif-link {
            color: var(--primary);
            text-decoration: none;
            font-size: 0.7rem;
            font-weight: 500;
            display: inline-block;
            margin-top: 2px;
        }
        
        .notification-item .notif-link:hover {
            text-decoration: underline;
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
            font-size: 3rem;
            color: var(--border-color);
            display: block;
            margin-bottom: 12px;
        }
        
        .empty-state h3 {
            font-size: 1.1rem;
            color: var(--text-primary);
            margin-bottom: 6px;
        }
        
        .empty-state p {
            font-size: 0.85rem;
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
           FOOTER
           ================================================================ */
        .footer {
            padding: 14px 0;
            border-top: 1px solid var(--border-color);
            margin-top: 24px;
            text-align: center;
            font-size: 0.7rem;
            color: var(--text-secondary);
        }
        
        .footer .footer-brand { 
            color: var(--success); 
            font-weight: 600; 
        }
        
        .role-badge-display {
            display: inline-block;
            font-size: 0.6rem;
            font-weight: 600;
            padding: 2px 10px;
            border-radius: 20px;
            background: var(--primary-bg);
            color: var(--primary);
            text-transform: uppercase;
        }
        
        .branch-badge-display {
            display: inline-block;
            font-size: 0.6rem;
            font-weight: 600;
            padding: 2px 10px;
            border-radius: 20px;
            background: var(--success-bg);
            color: var(--success);
        }
        
        /* ================================================================
           RESPONSIVE
           ================================================================ */
        @media (max-width: 1024px) {
            .main-content { margin-left: 0; padding: 16px; }
        }
        
        @media (max-width: 768px) {
            .page-header { padding: 16px 18px; }
            .page-header .page-title { font-size: 1.3rem; }
            .filter-section { padding: 12px 14px; }
            .filter-btn { font-size: 0.6rem; padding: 3px 10px; }
            .card-header { padding: 12px 16px; }
            .notification-item { padding: 12px 16px; }
            .header-actions { width: 100%; }
            .btn-action { font-size: 0.7rem; padding: 6px 12px; }
            .filter-search-input { width: 120px; }
        }
        
        @media (max-width: 640px) {
            .main-content { padding: 10px; }
            .filter-btn { font-size: 0.55rem; padding: 2px 8px; }
            .notification-item { flex-wrap: wrap; }
            .notification-item .notif-actions { width: 100%; justify-content: flex-end; margin-top: 6px; }
            .filter-search-input { width: 100px; }
            .btn-action { font-size: 0.65rem; padding: 4px 10px; }
            .btn-action span { display: none; }
            .btn-action i { font-size: 0.8rem; }
        }
    </style>
    
    <script>
        (function() {
            var darkMode = localStorage.getItem('darkMode');
            if (darkMode === 'true') {
                document.documentElement.setAttribute('data-theme', 'dark');
            }
        })();
    </script>
</head>
<body>

<main class="main-content">

    <!-- PAGE HEADER -->
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-bell"></i>
                Notifications
                <span class="role-badge-display" style="background:rgba(255,255,255,0.2);color:white;"><?= strtoupper($user_role) ?></span>
                <?php if ($unread_count > 0): ?>
                    <span class="role-badge-display" style="background:rgba(220,38,38,0.3);color:#F87171;border-color:rgba(220,38,38,0.3);">
                        <i class="fas fa-circle" style="font-size:0.4rem;color:#F87171;"></i>
                        <?= $unread_count ?> Unread
                    </span>
                <?php endif; ?>
                <span style="font-size:0.7rem;color:rgba(255,255,255,0.6);font-weight:400;margin-left:4px;">
                    <i class="fas fa-user"></i> <?= htmlspecialchars($user_full_name) ?>
                </span>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-bell"></i>
                Your notifications in <strong><?= htmlspecialchars($user_branch_name) ?></strong>
                
                <span class="header-badge">
                    <i class="fas fa-bell"></i>
                    <?= count($notifications) ?> Notifications
                </span>
            </p>
        </div>
        
        <!-- ================================================================ -->
        <!-- ACTION BUTTONS - MARK ALL READ & DELETE ALL -->
        <!-- ================================================================ -->
        <div class="header-actions">
            <a href="dashboard.php" class="btn-action btn-action-outline">
                <i class="fas fa-arrow-left"></i>
                <span>Back</span>
            </a>
            
            <?php if ($unread_count > 0): ?>
                <a href="?mark_all_read=1&filter=<?= $filter ?>" 
                   class="btn-action btn-action-success" 
                   onclick="return confirm('✅ Mark all notifications as read?')">
                    <i class="fas fa-check-double"></i>
                    <span>Mark All Read</span>
                </a>
            <?php endif; ?>
            
            <?php if (count($notifications) > 0): ?>
                <a href="?delete_all=1" 
                   class="btn-action btn-action-danger" 
                   onclick="return confirm('⚠️ Delete all notifications? This action cannot be undone!')">
                    <i class="fas fa-trash-alt"></i>
                    <span>Delete All</span>
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- FLASH MESSAGE -->
    <?php if ($message): ?>
        <div class="p-4 rounded-xl mb-4 <?= $message_type === 'success' ? 'bg-green-100 text-green-700 border border-green-200' : ($message_type === 'error' ? 'bg-red-100 text-red-700 border border-red-200' : 'bg-blue-100 text-blue-700 border border-blue-200') ?>" style="max-width:1000px;margin:0 auto 16px;">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : ($message_type === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle') ?> mr-2"></i>
            <?= $message ?>
        </div>
    <?php endif; ?>

    <!-- FILTERS -->
    <div class="filter-section">
        <div class="filter-group">
            <span class="filter-label"><i class="fas fa-filter"></i> Show:</span>
            
            <a href="?filter=all&search=<?= urlencode($search) ?>" class="filter-btn <?= $filter === 'all' ? 'active' : '' ?>">
                <i class="fas fa-globe"></i> All
            </a>
            <a href="?filter=unread&search=<?= urlencode($search) ?>" class="filter-btn <?= $filter === 'unread' ? 'active' : '' ?>">
                <i class="fas fa-circle" style="color:#F87171;font-size:0.5rem;"></i> Unread
                <?php if ($unread_count > 0): ?>
                    <span style="background:#F87171;color:white;border-radius:10px;padding:0 6px;font-size:0.55rem;"><?= $unread_count ?></span>
                <?php endif; ?>
            </a>
            <a href="?filter=read&search=<?= urlencode($search) ?>" class="filter-btn <?= $filter === 'read' ? 'active' : '' ?>">
                <i class="fas fa-check-circle"></i> Read
            </a>
            
            <span class="filter-label" style="margin-left:10px;"><i class="fas fa-search"></i> Search:</span>
            <input type="text" id="searchInput" class="filter-search-input" 
                   placeholder="Search notifications..." 
                   value="<?= htmlspecialchars($search) ?>"
                   onkeypress="if(event.key==='Enter'){var s=this.value;var f='<?= $filter ?>';window.location.href='notifications.php?filter='+f+'&search='+encodeURIComponent(s);}">
            <button onclick="var s=document.getElementById('searchInput').value;var f='<?= $filter ?>';window.location.href='notifications.php?filter='+f+'&search='+encodeURIComponent(s);" class="btn-apply">
                <i class="fas fa-search"></i>
            </button>
            <?php if (!empty($search)): ?>
                <a href="?filter=<?= $filter ?>" class="btn-apply btn-apply-danger">
                    <i class="fas fa-times"></i> Clear
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- NOTIFICATIONS LIST -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-bell" style="color:var(--primary);"></i> 
                <?php if ($filter === 'unread'): ?>
                    Unread Notifications
                <?php elseif ($filter === 'read'): ?>
                    Read Notifications
                <?php else: ?>
                    All Notifications
                <?php endif; ?>
                <span class="text-sm font-normal text-gray-400">(<?= count($notifications) ?>)</span>
            </h3>
            <span class="text-xs text-gray-400">
                <i class="fas fa-clock"></i> Updated: <?= date('h:i:s A') ?>
            </span>
        </div>
        
        <div class="card-body">
            <?php if (is_array($notifications) && count($notifications) > 0): ?>
                <?php foreach ($notifications as $notif): 
                    $is_unread = ($notif['is_read'] == 0);
                    $icon_class = $notif['type'] ?? 'info';
                    $icon_map = [
                        'info' => 'fa-info-circle',
                        'success' => 'fa-check-circle',
                        'warning' => 'fa-exclamation-triangle',
                        'danger' => 'fa-exclamation-circle'
                    ];
                    $icon = $icon_map[$icon_class] ?? 'fa-bell';
                ?>
                    <div class="notification-item <?= $is_unread ? 'unread' : '' ?>">
                        <div class="notif-icon <?= $icon_class ?>">
                            <i class="fas <?= $icon ?>"></i>
                        </div>
                        
                        <div class="notif-content">
                            <div class="notif-title">
                                <?= htmlspecialchars($notif['title'] ?? 'Notification') ?>
                                <?php if ($is_unread): ?>
                                    <span style="font-size:0.5rem;background:#F87171;color:white;padding:1px 8px;border-radius:10px;margin-left:6px;">NEW</span>
                                <?php endif; ?>
                            </div>
                            <div class="notif-message" id="msg_<?= $notif['id'] ?>">
                                <?= nl2br(htmlspecialchars($notif['message'] ?? '')) ?>
                            </div>
                            <?php if (!empty($notif['link'])): ?>
                                <a href="<?= htmlspecialchars($notif['link']) ?>" class="notif-link">
                                    <i class="fas fa-arrow-right"></i> View Details
                                </a>
                            <?php endif; ?>
                            <div class="notif-time">
                                <i class="far fa-clock"></i> 
                                <?= date('d M Y h:i A', strtotime($notif['created_at'] ?? 'now')) ?>
                                <?php if (!empty($notif['branch_id'])): ?>
                                    <span class="mx-1">•</span>
                                    <i class="fas fa-store-alt"></i> Branch #<?= $notif['branch_id'] ?>
                                <?php endif; ?>
                                <?php if (!empty($notif['patient_id'])): ?>
                                    <span class="mx-1">•</span>
                                    <i class="fas fa-user"></i> Patient #<?= $notif['patient_id'] ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="notif-actions">
                            <?php if ($is_unread): ?>
                                <a href="?mark_read=<?= $notif['id'] ?>&filter=<?= $filter ?>" class="btn-icon" title="Mark as read">
                                    <i class="fas fa-check"></i>
                                </a>
                            <?php endif; ?>
                            <a href="?delete=<?= $notif['id'] ?>&filter=<?= $filter ?>" class="btn-icon danger" title="Delete" onclick="return confirm('Delete this notification?')">
                                <i class="fas fa-times"></i>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-bell-slash"></i>
                    <h3>No Notifications</h3>
                    <p>
                        <?php if ($filter === 'unread'): ?>
                            You have no unread notifications. All caught up! 🎉
                        <?php elseif ($filter === 'read'): ?>
                            You have no read notifications yet.
                        <?php else: ?>
                            You don't have any notifications at the moment. Check back later!
                        <?php endif; ?>
                    </p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- FOOTER -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Notifications
            <span class="text-gray-300 mx-2">|</span>
            <span class="text-gray-400">👤 <?= htmlspecialchars($user_full_name) ?></span>
            <?php if ($is_reception): ?>
                <span class="text-gray-300 mx-2">|</span>
                <span style="color:#34D399;">👀 Reception</span>
            <?php endif; ?>
            <?php if ($is_admin): ?>
                <span class="text-gray-300 mx-2">|</span>
                <span style="color:#FFD700;">⭐ Admin</span>
            <?php endif; ?>
            <span class="text-gray-300 mx-2">|</span>
            <span id="footerTimestamp">Last updated: <?= date('H:i:s') ?></span>
            <span class="text-gray-300 mx-2">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<!-- TOAST -->
<div id="toast" class="toast-custom" style="display:none;">
    <i class="fas fa-info-circle" style="font-size:1.1rem;"></i>
    <div>
        <p style="font-weight:600;font-size:0.85rem;margin:0;" id="toastTitle">Notification</p>
        <p style="font-size:0.75rem;opacity:0.9;margin:0;" id="toastMessage"></p>
    </div>
</div>

<script>
    function showToast(title, message, type) {
        var toast = document.getElementById('toast');
        var toastTitle = document.getElementById('toastTitle');
        var toastMessage = document.getElementById('toastMessage');
        
        toast.className = 'toast-custom ' + (type || 'info');
        toastTitle.textContent = title || 'Notification';
        toastMessage.textContent = message || '';
        toast.style.display = 'flex';
        
        setTimeout(function() {
            toast.classList.add('show');
        }, 50);
        
        clearTimeout(toast.timeout);
        toast.timeout = setTimeout(function() {
            toast.classList.remove('show');
            setTimeout(function() {
                toast.style.display = 'none';
            }, 400);
        }, 3500);
    }

    // SIDEBAR TOGGLE
    var sidebar = document.getElementById('sidebar');
    var sidebarToggle = document.getElementById('sidebarToggle');
    
    if (sidebarToggle && sidebar) {
        sidebarToggle.addEventListener('click', function() {
            sidebar.classList.toggle('open');
        });
        
        document.addEventListener('click', function(e) {
            if (window.innerWidth <= 1024) {
                if (!sidebar.contains(e.target) && e.target !== sidebarToggle) {
                    sidebar.classList.remove('open');
                }
            }
        });
    }

    // Toggle message expansion on click
    document.querySelectorAll('.notification-item .notif-message').forEach(function(el) {
        el.addEventListener('click', function() {
            this.classList.toggle('expanded');
        });
    });

    console.log('%c🔔 Braick - Notifications', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c✅ Shows notifications for: <?= htmlspecialchars($user_full_name) ?> (ID: <?= $user_id ?>)', 'font-size:13px; color:#34D399;');
    console.log('%c👤 Role: <?= htmlspecialchars($user_role) ?>', 'font-size:13px; color:#0B5ED7;');
    console.log('%c📋 Total: <?= count($notifications) ?> notifications', 'font-size:13px; color:#64748B;');
    console.log('%c🔴 Unread: <?= $unread_count ?>', 'font-size:13px; color:#DC2626;');
    console.log('%c📌 Filter: <?= $filter ?>', 'font-size:13px; color:#0B5ED7;');
    console.log('%c✅ Mark All Read button available', 'font-size:13px; color:#34D399;');
    console.log('%c🗑️ Delete All button available', 'font-size:13px; color:#DC2626;');
</script>

</body>
</html>