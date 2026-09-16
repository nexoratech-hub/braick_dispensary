<?php
// ================================================================
// FILE: frontend/pages/admin/system_logs.php
// ADMIN - SYSTEM ACTIVITY LOGS
// ✅ Uses SHARED header & sidebar (NO DUPLICATES)
// ✅ Blue theme + full dark mode support via --page-* variables
// ✅ Auto-search filter + scroll buttons
// ✅ DELETE ALL + DELETE ROW functionality
// ✅ Removed IP Address column
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
        case 'audit': header('Location: ../audit/dashboard.php'); break;
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

require_once __DIR__ . '/../../../backend/config/database.php';
require_once __DIR__ . '/../../../backend/helpers/functions.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

// ================================================================
// HANDLE DELETE ACTIONS
// ================================================================
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    // DELETE SINGLE ROW
    if ($action === 'delete_log') {
        $log_id = (int)($_POST['log_id'] ?? 0);
        
        if ($log_id > 0) {
            try {
                $stmt = $db->prepare("DELETE FROM activity_logs WHERE id = ?");
                $stmt->execute([$log_id]);
                
                $_SESSION['syslogs_message'] = "✅ Log entry #$log_id deleted successfully!";
                $_SESSION['syslogs_message_type'] = 'success';
            } catch (Exception $e) {
                $_SESSION['syslogs_message'] = "❌ Error deleting log: " . $e->getMessage();
                $_SESSION['syslogs_message_type'] = 'error';
            }
        }
        
        header('Location: system_logs.php?branch=' . urlencode($selected_branch_id));
        exit;
    }
    
    // DELETE ALL LOGS (with optional filters)
    if ($action === 'delete_all_logs') {
        try {
            $delete_where = "1=1";
            $delete_params = [];
            
            // Respect current filters when deleting all
            if (!empty($_POST['filter_action'])) { 
                $delete_where .= " AND action = ?"; 
                $delete_params[] = $_POST['filter_action']; 
            }
            if (!empty($_POST['filter_user']) && (int)$_POST['filter_user'] > 0) { 
                $delete_where .= " AND user_id = ?"; 
                $delete_params[] = (int)$_POST['filter_user']; 
            }
            if (!empty($_POST['filter_date_from'])) { 
                $delete_where .= " AND DATE(created_at) >= ?"; 
                $delete_params[] = $_POST['filter_date_from']; 
            }
            if (!empty($_POST['filter_date_to'])) { 
                $delete_where .= " AND DATE(created_at) <= ?"; 
                $delete_params[] = $_POST['filter_date_to']; 
            }
            if (isset($_POST['filter_branch']) && $_POST['filter_branch'] !== 'all' && is_numeric($_POST['filter_branch'])) {
                $delete_where .= " AND branch_id = ?";
                $delete_params[] = (int)$_POST['filter_branch'];
            }
            
            // Count first
            $count_stmt = $db->prepare("SELECT COUNT(*) as c FROM activity_logs WHERE $delete_where");
            $count_stmt->execute($delete_params);
            $delete_count = (int)($count_stmt->fetch(PDO::FETCH_ASSOC)['c'] ?? 0);
            
            if ($delete_count > 0) {
                $stmt = $db->prepare("DELETE FROM activity_logs WHERE $delete_where");
                $stmt->execute($delete_params);
                
                $_SESSION['syslogs_message'] = "✅ <strong>$delete_count</strong> log entries deleted successfully!";
                $_SESSION['syslogs_message_type'] = 'success';
            } else {
                $_SESSION['syslogs_message'] = "ℹ️ No logs found to delete with current filters.";
                $_SESSION['syslogs_message_type'] = 'info';
            }
        } catch (Exception $e) {
            $_SESSION['syslogs_message'] = "❌ Error deleting logs: " . $e->getMessage();
            $_SESSION['syslogs_message_type'] = 'error';
        }
        
        header('Location: system_logs.php?branch=' . urlencode($selected_branch_id));
        exit;
    }
}

// Get flash message
if (isset($_SESSION['syslogs_message'])) {
    $message = $_SESSION['syslogs_message'];
    $message_type = $_SESSION['syslogs_message_type'] ?? 'info';
    unset($_SESSION['syslogs_message']);
    unset($_SESSION['syslogs_message_type']);
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

if (!empty($action_filter)) { $where .= " AND al.action = ?"; $params[] = $action_filter; }
if ($user_filter > 0) { $where .= " AND al.user_id = ?"; $params[] = $user_filter; }
if (!empty($date_from)) { $where .= " AND DATE(al.created_at) >= ?"; $params[] = $date_from; }
if (!empty($date_to)) { $where .= " AND DATE(al.created_at) <= ?"; $params[] = $date_to; }
if ($selected_branch_id !== 'all' && is_numeric($selected_branch_id)) {
    $where .= " AND al.branch_id = ?";
    $params[] = (int)$selected_branch_id;
}

// ================================================================
// FETCH LOGS
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

// ================================================================
// INCLUDE SHARED HEADER & SIDEBAR
// ================================================================
include_once '../../components/admin_header.php';
include_once '../../components/admin_sidebar.php';
?>

<!-- ================================================================
     PAGE-SPECIFIC CSS
     ================================================================ -->
<style>
    /* PAGE HEADER */
    .page-header-syslogs {
        background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
        border-radius: 16px;
        padding: 18px 24px;
        margin-bottom: 20px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 10px;
        box-shadow: 0 4px 20px rgba(11, 94, 215, 0.25);
        position: relative;
        overflow: hidden;
    }

    .page-header-syslogs::before {
        content: '';
        position: absolute;
        top: -60%; right: -10%;
        width: 400px; height: 400px;
        background: rgba(255,255,255,0.05);
        border-radius: 50%;
        pointer-events: none;
    }

    .page-header-syslogs .page-title-syslogs {
        color: white;
        font-size: 1.4rem;
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
        position: relative;
        z-index: 1;
        margin: 0;
    }

    .page-header-syslogs .role-badge-syslogs {
        background: rgba(255,255,255,0.2);
        color: white;
        padding: 2px 10px;
        border-radius: 20px;
        font-size: 0.55rem;
        font-weight: 600;
        text-transform: uppercase;
    }

    .page-header-syslogs .branch-name-syslogs {
        background: rgba(255,255,255,0.15);
        padding: 2px 12px;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: 500;
        color: white;
    }

    .page-header-syslogs .btn-back-syslogs {
        background: #059669;
        color: white;
        padding: 5px 16px;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: 600;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        position: relative;
        z-index: 1;
    }

    .page-header-syslogs .btn-back-syslogs:hover {
        background: #047857;
        transform: translateY(-1px);
        color: white;
    }

    .page-header-syslogs .page-subtitle-syslogs {
        color: rgba(255,255,255,0.85);
        font-size: 0.8rem;
        display: flex;
        align-items: center;
        gap: 6px;
        flex-wrap: wrap;
        position: relative;
        z-index: 1;
        margin-top: 4px;
    }

    /* STATS */
    .stats-grid-syslogs {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 14px;
        margin-bottom: 20px;
    }

    .stat-card-syslogs {
        border-radius: 12px;
        padding: 16px 18px;
        color: white;
        min-height: 85px;
        text-decoration: none;
        transition: transform 0.3s;
    }

    .stat-card-syslogs:hover {
        transform: translateY(-4px);
        color: white;
    }

    .stat-card-syslogs .stat-number-syslogs {
        font-size: 1.6rem;
        font-weight: 700;
    }

    .stat-card-syslogs .stat-label-syslogs {
        font-size: 0.6rem;
        color: rgba(255,255,255,0.9);
        font-weight: 500;
        text-transform: uppercase;
        margin-top: 2px;
    }

    .stat-card-syslogs .stat-icon-syslogs {
        font-size: 1.3rem;
        opacity: 0.7;
        float: right;
        margin-top: -5px;
    }

    .stat-card-syslogs.blue { background: linear-gradient(135deg, #0B5ED7, #0A4CA8); }
    .stat-card-syslogs.green { background: linear-gradient(135deg, #059669, #047857); }
    .stat-card-syslogs.orange { background: linear-gradient(135deg, #D97706, #B45309); }
    .stat-card-syslogs.purple { background: linear-gradient(135deg, #7C3AED, #6D28D9); }

    /* MESSAGE BOX */
    .message-box-syslogs {
        padding: 14px 20px;
        border-radius: 12px;
        margin-bottom: 20px;
        display: flex;
        align-items: flex-start;
        gap: 12px;
        font-weight: 500;
        font-size: 0.9rem;
        animation: slideDown 0.3s ease;
        max-width: 1200px;
        margin-left: auto;
        margin-right: auto;
    }

    @keyframes slideDown {
        from { opacity: 0; transform: translateY(-10px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .message-box-syslogs.success { background: #D1FAE5; color: #065F46; border: 2px solid #6EE7B7; }
    .message-box-syslogs.error { background: #FEE2E2; color: #991B1B; border: 2px solid #FCA5A5; }
    .message-box-syslogs.info { background: #E8F0FE; color: #0B5ED7; border: 2px solid #6EA8FE; }

    [data-theme="dark"] .message-box-syslogs.success { background: #1A3A2A; color: #34D399; border-color: #34D399; }
    [data-theme="dark"] .message-box-syslogs.error { background: #3A1A1A; color: #F87171; border-color: #F87171; }
    [data-theme="dark"] .message-box-syslogs.info { background: #1E3A5F; color: #6EA8FE; border-color: #6EA8FE; }

    /* CARD */
    .card-syslogs {
        background: var(--page-bg-card, #FFFFFF);
        border-radius: 12px;
        padding: 14px 18px;
        border: 2px solid var(--page-border, #E2E8F0);
        margin-bottom: 20px;
    }

    [data-theme="dark"] .card-syslogs { background: #1E293B; border-color: #334155; }

    /* FILTER FORM */
    .filter-form-syslogs {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
        align-items: center;
    }

    .filter-form-syslogs select,
    .filter-form-syslogs input[type="date"] {
        padding: 8px 12px;
        border: 2px solid var(--page-border, #E2E8F0);
        border-radius: 8px;
        font-size: 0.8rem;
        background: var(--page-input-bg, #FFFFFF);
        color: var(--page-text-primary, #1E293B);
        outline: none;
        font-family: inherit;
    }

    .filter-form-syslogs select:focus,
    .filter-form-syslogs input:focus {
        border-color: var(--page-primary, #0B5ED7);
    }

    .btn-search-syslogs {
        padding: 8px 20px;
        border-radius: 8px;
        font-weight: 600;
        font-size: 0.8rem;
        border: none;
        background: var(--page-primary, #0B5ED7);
        color: white;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        font-family: inherit;
    }

    .btn-search-syslogs:hover { background: #0A4CA8; }

    .btn-reset-syslogs {
        padding: 8px 16px;
        border-radius: 8px;
        font-weight: 600;
        font-size: 0.8rem;
        border: 2px solid var(--page-border, #E2E8F0);
        background: transparent;
        color: var(--page-text-secondary, #64748B);
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }

    .btn-reset-syslogs:hover { border-color: #DC2626; color: #DC2626; }

    /* DELETE ALL BUTTON */
    .btn-delete-all-syslogs {
        padding: 8px 18px;
        border-radius: 8px;
        font-weight: 700;
        font-size: 0.78rem;
        border: 2px solid #DC2626;
        background: #DC2626;
        color: white;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        transition: all 0.3s;
        font-family: inherit;
        box-shadow: 0 2px 8px rgba(220, 38, 38, 0.25);
    }

    .btn-delete-all-syslogs:hover {
        background: #B91C1C;
        border-color: #B91C1C;
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(220, 38, 38, 0.4);
    }

    /* TABLE HEADER BAR */
    .table-header-bar-syslogs {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 12px;
        margin-bottom: 12px;
        padding-bottom: 10px;
        border-bottom: 2px solid var(--page-border, #E2E8F0);
    }

    .table-search-box-syslogs {
        position: relative;
        min-width: 280px;
        flex: 1;
        max-width: 400px;
    }

    .table-search-box-syslogs i {
        position: absolute;
        left: 14px;
        top: 50%;
        transform: translateY(-50%);
        color: rgba(255,255,255,0.9);
        font-size: 0.85rem;
        pointer-events: none;
        z-index: 1;
    }

    .table-search-box-syslogs input {
        width: 100%;
        padding: 10px 14px 10px 40px;
        border: 2px solid var(--page-primary, #0B5ED7);
        border-radius: 10px;
        font-size: 0.85rem;
        background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
        color: white;
        outline: none;
        font-weight: 500;
        height: 42px;
        box-shadow: 0 4px 12px rgba(11, 94, 215, 0.25);
        font-family: inherit;
    }

    .table-search-box-syslogs input::placeholder { color: rgba(255,255,255,0.85); }

    .table-search-box-syslogs input:focus {
        box-shadow: 0 0 0 4px rgba(11, 94, 215, 0.2);
    }

    .scroll-btn-header-syslogs {
        width: 38px;
        height: 38px;
        border-radius: 8px;
        border: 2px solid var(--page-border, #E2E8F0);
        background: var(--page-bg-card, #FFFFFF);
        color: var(--page-text-primary, #1E293B);
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        transition: all 0.3s;
    }

    .scroll-btn-header-syslogs:hover {
        background: var(--page-primary, #0B5ED7);
        border-color: var(--page-primary, #0B5ED7);
        color: white;
    }

    .scroll-btn-header-syslogs:disabled { opacity: 0.35; cursor: not-allowed; }

    .search-results-info-syslogs {
        font-size: 0.7rem;
        color: var(--page-primary, #0B5ED7);
        padding: 6px 12px;
        background: #E8F0FE;
        border-radius: 8px;
        display: none;
        font-weight: 600;
        border: 1px solid var(--page-primary, #0B5ED7);
    }

    .result-count-syslogs {
        font-size: 0.75rem;
        color: var(--page-text-secondary, #64748B);
    }

    .result-count-syslogs strong { color: var(--page-primary, #0B5ED7); }

    /* TABLE */
    .table-scroll-syslogs {
        overflow-x: auto;
        overflow-y: auto;
        max-height: 600px;
    }

    .table-scroll-syslogs::-webkit-scrollbar { height: 8px; width: 8px; }
    .table-scroll-syslogs::-webkit-scrollbar-thumb { background: #0B5ED7; border-radius: 4px; }

    .data-table-syslogs {
        width: 100%;
        min-width: 1000px;
        border-collapse: separate;
        border-spacing: 0;
        font-size: 0.78rem;
    }

    .data-table-syslogs thead th {
        position: sticky;
        top: 0;
        z-index: 10;
        background: #0B5ED7;
        color: white;
        padding: 10px 12px;
        font-size: 0.62rem;
        text-transform: uppercase;
        font-weight: 700;
        white-space: nowrap;
        text-align: left;
    }

    .data-table-syslogs thead th:first-child { border-radius: 8px 0 0 0; }
    .data-table-syslogs thead th:last-child { border-radius: 0 8px 0 0; }

    .data-table-syslogs tbody tr:nth-child(even) { background: #E8F0FE; }
    .data-table-syslogs tbody tr:hover td { background: #D1FAE5; }

    [data-theme="dark"] .data-table-syslogs tbody tr:nth-child(even) { background: #1E293B; }
    [data-theme="dark"] .data-table-syslogs tbody tr:hover td { background: #1A3A2A; }

    .data-table-syslogs td {
        padding: 10px 12px;
        border-bottom: 1px solid var(--page-border, #E2E8F0);
        vertical-align: middle;
        color: var(--page-text-primary, #1E293B);
    }

    /* BADGES */
    .badge-syslogs {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 2px 10px;
        border-radius: 12px;
        font-size: 0.6rem;
        font-weight: 600;
    }

    .badge-blue-syslogs { background: #E8F0FE; color: #0B5ED7; }
    .badge-green-syslogs { background: #D1FAE5; color: #059669; }
    .badge-orange-syslogs { background: #FEF3C7; color: #D97706; }
    .badge-red-syslogs { background: #FEE2E2; color: #DC2626; }
    .badge-purple-syslogs { background: #EDE9FE; color: #7C3AED; }
    .badge-gray-syslogs { background: #F1F5F9; color: #64748B; }

    [data-theme="dark"] .badge-gray-syslogs { background: #334155; color: #94A3B8; }
    [data-theme="dark"] .badge-blue-syslogs { background: #1E3A5F; color: #6EA8FE; }
    [data-theme="dark"] .badge-green-syslogs { background: #1A3A2A; color: #34D399; }
    [data-theme="dark"] .badge-orange-syslogs { background: #3A2A1A; color: #FBBF24; }
    [data-theme="dark"] .badge-red-syslogs { background: #3A1A1A; color: #F87171; }
    [data-theme="dark"] .badge-purple-syslogs { background: #2D1B4E; color: #A78BFA; }

    /* DELETE ROW BUTTON */
    .btn-delete-row-syslogs {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 30px;
        height: 30px;
        border-radius: 6px;
        border: none;
        background: #FEE2E2;
        color: #DC2626;
        cursor: pointer;
        transition: all 0.3s;
        font-size: 0.75rem;
        padding: 0;
    }

    .btn-delete-row-syslogs:hover {
        background: #DC2626;
        color: white;
        transform: scale(1.1);
        box-shadow: 0 2px 8px rgba(220, 38, 38, 0.4);
    }

    [data-theme="dark"] .btn-delete-row-syslogs {
        background: #3A1A1A;
        color: #F87171;
    }

    [data-theme="dark"] .btn-delete-row-syslogs:hover {
        background: #DC2626;
        color: white;
    }

    .no-results-row-syslogs td {
        text-align: center;
        padding: 40px 20px !important;
        color: var(--page-text-secondary, #64748B);
    }

    .no-results-row-syslogs i {
        font-size: 2.5rem;
        color: var(--page-text-muted, #94A3B8);
        display: block;
        margin-bottom: 10px;
    }

    /* FOOTER */
    .footer-syslogs {
        padding: 10px 0;
        border-top: 1px solid var(--page-border, #E2E8F0);
        margin-top: 16px;
        text-align: center;
        font-size: 0.6rem;
        color: var(--page-text-secondary, #64748B);
    }

    .footer-syslogs .footer-brand-syslogs { color: #0B5ED7; font-weight: 600; }

    /* RESPONSIVE */
    @media (max-width: 1024px) {
        .stats-grid-syslogs { grid-template-columns: repeat(2, 1fr); }
    }

    @media (max-width: 768px) {
        .stats-grid-syslogs { grid-template-columns: 1fr 1fr; }
        .page-header-syslogs { flex-direction: column; align-items: stretch; }
        .filter-form-syslogs { flex-direction: column; align-items: stretch; }
        .table-header-bar-syslogs { flex-direction: column; align-items: stretch; }
        .table-search-box-syslogs { min-width: 100%; max-width: 100%; }
    }

    @media (max-width: 480px) {
        .stats-grid-syslogs { grid-template-columns: 1fr; }
    }
</style>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <!-- PAGE HEADER -->
    <div class="page-header-syslogs">
        <div>
            <h1 class="page-title-syslogs">
                <i class="fas fa-history"></i> System Activity Logs
                <span class="role-badge-syslogs">ADMIN</span>
                <span class="branch-name-syslogs">
                    <i class="fas fa-store-alt"></i> <?= htmlspecialchars($display_branch_name) ?>
                </span>
                <a href="dashboard.php?branch=<?= $selected_branch_id ?>" class="btn-back-syslogs">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
            </h1>
            <p class="page-subtitle-syslogs">
                <strong><?= number_format($total_logs) ?></strong> log entries loaded
                <?php if (!empty($action_filter)): ?> · Action: <strong><?= htmlspecialchars($action_filter) ?></strong><?php endif; ?>
                <?php if (!empty($date_from) || !empty($date_to)): ?>
                    · Date: <strong><?= htmlspecialchars($date_from ?: 'Any') ?> → <?= htmlspecialchars($date_to ?: 'Any') ?></strong>
                <?php endif; ?>
            </p>
        </div>
    </div>

    <!-- MESSAGE -->
    <?php if ($message): ?>
        <div class="message-box-syslogs <?= $message_type ?>">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : ($message_type === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle') ?>" style="font-size:1.2rem;flex-shrink:0;"></i>
            <div><?= $message ?></div>
        </div>
    <?php endif; ?>

    <!-- STATS -->
    <div class="stats-grid-syslogs">
        <a href="system_logs.php?branch=<?= $selected_branch_id ?>" class="stat-card-syslogs blue">
            <span class="stat-icon-syslogs"><i class="fas fa-list"></i></span>
            <div class="stat-number-syslogs"><?= number_format($total_activity_count) ?></div>
            <div class="stat-label-syslogs">Total Logs</div>
        </a>
        <a href="system_logs.php?branch=<?= $selected_branch_id ?>&date_from=<?= date('Y-m-d') ?>&date_to=<?= date('Y-m-d') ?>" class="stat-card-syslogs green">
            <span class="stat-icon-syslogs"><i class="fas fa-calendar-day"></i></span>
            <div class="stat-number-syslogs"><?= number_format($today_logs_count) ?></div>
            <div class="stat-label-syslogs">Today</div>
        </a>
        <a href="system_logs.php?branch=<?= $selected_branch_id ?>" class="stat-card-syslogs orange">
            <span class="stat-icon-syslogs"><i class="fas fa-calendar-week"></i></span>
            <div class="stat-number-syslogs"><?= number_format($week_logs_count) ?></div>
            <div class="stat-label-syslogs">This Week</div>
        </a>
        <a href="system_logs.php?branch=<?= $selected_branch_id ?>" class="stat-card-syslogs purple">
            <span class="stat-icon-syslogs"><i class="fas fa-calendar-alt"></i></span>
            <div class="stat-number-syslogs"><?= number_format($month_logs_count) ?></div>
            <div class="stat-label-syslogs">This Month</div>
        </a>
    </div>

    <!-- FILTERS -->
    <div class="card-syslogs">
        <form method="GET" class="filter-form-syslogs">
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
            
            <input type="date" name="date_from" value="<?= htmlspecialchars($date_from) ?>" title="From date">
            <input type="date" name="date_to" value="<?= htmlspecialchars($date_to) ?>" title="To date">
            
            <button type="submit" class="btn-search-syslogs">
                <i class="fas fa-filter"></i> Filter
            </button>
            
            <a href="system_logs.php?branch=<?= $selected_branch_id ?>" class="btn-reset-syslogs">
                <i class="fas fa-times"></i> Reset
            </a>
        </form>
    </div>

    <!-- LOGS TABLE -->
    <div class="card-syslogs">
        <div class="table-header-bar-syslogs">
            <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;flex:1;min-width:0;">
                <!-- BLUE SEARCH BOX - AUTO FILTER -->
                <div class="table-search-box-syslogs">
                    <i class="fas fa-search"></i>
                    <input type="text" id="tableSearchInputSyslogs" placeholder="🔍 Auto-search logs..." autocomplete="off">
                </div>
                
                <h3 style="margin:0;font-size:1rem;font-weight:600;color:var(--page-text-primary);">
                    <i class="fas fa-list" style="color:var(--page-primary);"></i> 
                    <span class="result-count-syslogs" id="countDisplaySyslogs">(<strong><?= count($logs) ?></strong> entries)</span>
                </h3>
                
                <span class="search-results-info-syslogs" id="searchInfoSyslogs">
                    <i class="fas fa-filter"></i> <strong id="searchCountSyslogs">0</strong> match
                </span>
            </div>
            
            <div style="display:flex;align-items:center;gap:8px;flex-shrink:0;flex-wrap:wrap;">
                <!-- DELETE ALL BUTTON -->
                <?php if (count($logs) > 0): ?>
                <button type="button" class="btn-delete-all-syslogs" onclick="confirmDeleteAll()" title="Delete All Logs (respecting filters)">
                    <i class="fas fa-trash-alt"></i> Delete All
                </button>
                <?php endif; ?>
                
                <button type="button" class="scroll-btn-header-syslogs" id="scrollBtnLeftSyslogs" onclick="scrollTableSyslogs('left')" title="Scroll Left">
                    <i class="fas fa-chevron-left"></i>
                </button>
                <button type="button" class="scroll-btn-header-syslogs" id="scrollBtnRightSyslogs" onclick="scrollTableSyslogs('right')" title="Scroll Right">
                    <i class="fas fa-chevron-right"></i>
                </button>
            </div>
        </div>

        <?php if (count($logs) > 0): ?>
            <div class="table-scroll-syslogs" id="tableWrapSyslogs">
                <table class="data-table-syslogs">
                    <thead>
                        <tr>
                            <th style="width:40px;text-align:center;">#</th>
                            <th style="min-width:130px;">Date/Time</th>
                            <th style="min-width:150px;">User</th>
                            <th style="min-width:140px;">Action</th>
                            <th style="min-width:300px;">Details</th>
                            <th style="min-width:120px;">Branch</th>
                            <th style="width:80px;text-align:center;">Action</th>
                        </tr>
                    </thead>
                    <tbody id="tableBodySyslogs">
                        <?php 
                        $num = 1;
                        foreach ($logs as $log): 
                            $badge_class = 'badge-blue-syslogs';
                            $action_lower = strtolower($log['action'] ?? '');
                            if (strpos($action_lower, 'login') !== false) $badge_class = 'badge-green-syslogs';
                            elseif (strpos($action_lower, 'logout') !== false) $badge_class = 'badge-gray-syslogs';
                            elseif (strpos($action_lower, 'delete') !== false || strpos($action_lower, 'deactivate') !== false) $badge_class = 'badge-red-syslogs';
                            elseif (strpos($action_lower, 'update') !== false || strpos($action_lower, 'edit') !== false) $badge_class = 'badge-orange-syslogs';
                            elseif (strpos($action_lower, 'create') !== false || strpos($action_lower, 'add') !== false) $badge_class = 'badge-purple-syslogs';
                            
                            $search_text = strtolower(
                                ($log['action'] ?? '') . ' ' .
                                ($log['details'] ?? '') . ' ' .
                                ($log['user_full_name'] ?? '') . ' ' .
                                ($log['user_username'] ?? '') . ' ' .
                                ($log['user_role'] ?? '') . ' ' .
                                ($log['branch_name'] ?? '')
                            );
                        ?>
                            <tr class="log-row-syslogs" data-search="<?= htmlspecialchars($search_text) ?>" data-log-id="<?= (int)$log['id'] ?>">
                                <td style="text-align:center;"><?= $num++ ?></td>
                                <td>
                                    <strong><?= date('d/m/Y', strtotime($log['created_at'])) ?></strong>
                                    <div style="font-size:0.65rem;color:var(--page-text-secondary);">
                                        <?= date('H:i:s', strtotime($log['created_at'])) ?>
                                    </div>
                                </td>
                                <td>
                                    <?php if (!empty($log['user_full_name'])): ?>
                                        <strong><?= htmlspecialchars($log['user_full_name']) ?></strong>
                                        <div style="font-size:0.6rem;color:var(--page-text-secondary);">
                                            <?= htmlspecialchars($log['user_role'] ?? '') ?>
                                        </div>
                                    <?php else: ?>
                                        <span style="color:var(--page-text-muted);">System</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge-syslogs <?= $badge_class ?>">
                                        <?= htmlspecialchars($log['action'] ?? 'N/A') ?>
                                    </span>
                                </td>
                                <td style="font-size:0.75rem;max-width:500px;">
                                    <?= htmlspecialchars($log['details'] ?? '-') ?>
                                </td>
                                <td>
                                    <?php if (!empty($log['branch_name'])): ?>
                                        <span class="badge-syslogs badge-blue-syslogs">
                                            🏥 <?= htmlspecialchars($log['branch_name']) ?>
                                        </span>
                                    <?php else: ?>
                                        <span style="color:var(--page-text-muted);">-</span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align:center;">
                                    <button type="button" 
                                            class="btn-delete-row-syslogs" 
                                            onclick="confirmDeleteLog(<?= (int)$log['id'] ?>, '<?= htmlspecialchars(addslashes($log['action'] ?? 'N/A')) ?>')" 
                                            title="Delete this log">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        
                        <tr class="no-results-row-syslogs" id="noResultsSyslogs" style="display:none;">
                            <td colspan="7">
                                <i class="fas fa-search-minus"></i>
                                <p>No logs match your search</p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div style="text-align:center;padding:40px 20px;color:var(--page-text-secondary);">
                <i class="fas fa-history" style="font-size:3rem;color:var(--page-text-muted);display:block;margin-bottom:12px;"></i>
                <p style="font-size:0.95rem;font-weight:600;">No logs found</p>
                <p style="font-size:0.8rem;margin-top:4px;">Try adjusting your filters.</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- FOOTER -->
    <footer class="footer-syslogs">
        <p>
            <span class="footer-brand-syslogs">Braick Dispensary</span> Management System
            <span style="color:#CBD5E1;margin:0 8px;">|</span> System Logs
            <span style="color:#CBD5E1;margin:0 8px;">|</span> <?= number_format($total_logs) ?> entries shown
            <span style="color:#CBD5E1;margin:0 8px;">|</span> <span id="footerTime"><?= date('H:i:s') ?></span>
            <span style="color:#CBD5E1;margin:0 8px;">|</span> &copy; <?= date('Y') ?>
        </p>
    </footer>

</main>

<!-- ================================================================ -->
<!-- DELETE SINGLE LOG MODAL -->
<!-- ================================================================ -->
<div id="deleteLogModal" class="modal-syslogs" style="display:none;">
    <div class="modal-overlay-syslogs" onclick="closeDeleteLogModal()"></div>
    <div class="modal-content-syslogs">
        <div class="modal-header-syslogs">
            <h3><i class="fas fa-exclamation-triangle" style="color:#DC2626;"></i> Delete Log Entry</h3>
            <button onclick="closeDeleteLogModal()" class="modal-close-syslogs">&times;</button>
        </div>
        <div class="modal-body-syslogs">
            <p style="color:var(--page-text-primary);">Are you sure you want to delete this log entry?</p>
            <p style="margin-top:8px;font-size:0.85rem;">
                <strong>Action:</strong> <span id="deleteLogAction" style="color:#DC2626;"></span>
            </p>
            <p style="margin-top:8px;font-size:0.8rem;color:#DC2626;font-weight:600;">
                ⚠️ This action cannot be undone!
            </p>
        </div>
        <form method="POST" style="margin-top:16px;">
            <input type="hidden" name="action" value="delete_log">
            <input type="hidden" name="log_id" id="deleteLogId" value="">
            <div class="modal-actions-syslogs">
                <button type="button" class="btn-cancel-syslogs" onclick="closeDeleteLogModal()">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button type="submit" class="btn-confirm-delete-syslogs">
                    <i class="fas fa-trash"></i> Yes, Delete
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ================================================================ -->
<!-- DELETE ALL LOGS MODAL -->
<!-- ================================================================ -->
<div id="deleteAllModal" class="modal-syslogs" style="display:none;">
    <div class="modal-overlay-syslogs" onclick="closeDeleteAllModal()"></div>
    <div class="modal-content-syslogs" style="max-width:520px;">
        <div class="modal-header-syslogs">
            <h3><i class="fas fa-exclamation-triangle" style="color:#DC2626;"></i> Delete All Logs</h3>
            <button onclick="closeDeleteAllModal()" class="modal-close-syslogs">&times;</button>
        </div>
        <div class="modal-body-syslogs">
            <div style="padding:14px 18px;background:#FEE2E2;border:2px solid #DC2626;border-radius:10px;margin-bottom:16px;">
                <p style="font-weight:700;color:#991B1B;font-size:0.95rem;margin:0;">
                    <i class="fas fa-exclamation-circle"></i> WARNING!
                </p>
                <p style="color:#991B1B;font-size:0.85rem;margin-top:6px;">
                    You are about to permanently delete <strong>all activity logs</strong> that match your current filters.
                </p>
            </div>
            
            <p style="color:var(--page-text-primary);font-size:0.9rem;font-weight:600;">Current filters being applied:</p>
            <ul style="margin-top:8px;margin-left:20px;font-size:0.85rem;color:var(--page-text-secondary);">
                <li><strong>Action:</strong> <?= !empty($action_filter) ? htmlspecialchars($action_filter) : 'All' ?></li>
                <li><strong>User:</strong> <?= $user_filter > 0 ? 'Selected user' : 'All users' ?></li>
                <li><strong>Date Range:</strong> <?= (!empty($date_from) || !empty($date_to)) ? (htmlspecialchars($date_from ?: 'Any') . ' → ' . htmlspecialchars($date_to ?: 'Any')) : 'All dates' ?></li>
                <li><strong>Branch:</strong> <?= htmlspecialchars($display_branch_name) ?></li>
            </ul>
            
            <p style="margin-top:16px;font-size:0.8rem;color:#DC2626;font-weight:600;">
                ⚠️ This action cannot be undone!
            </p>
        </div>
        <form method="POST" style="margin-top:16px;">
            <input type="hidden" name="action" value="delete_all_logs">
            <input type="hidden" name="filter_action" value="<?= htmlspecialchars($action_filter) ?>">
            <input type="hidden" name="filter_user" value="<?= $user_filter ?>">
            <input type="hidden" name="filter_date_from" value="<?= htmlspecialchars($date_from) ?>">
            <input type="hidden" name="filter_date_to" value="<?= htmlspecialchars($date_to) ?>">
            <input type="hidden" name="filter_branch" value="<?= htmlspecialchars($selected_branch_id) ?>">
            <div class="modal-actions-syslogs">
                <button type="button" class="btn-cancel-syslogs" onclick="closeDeleteAllModal()">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button type="submit" class="btn-confirm-delete-syslogs">
                    <i class="fas fa-trash-alt"></i> Delete All Logs
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ================================================================ -->
<!-- MODAL CSS -->
<!-- ================================================================ -->
<style>
    .modal-syslogs {
        position: fixed;
        top: 0; left: 0; right: 0; bottom: 0;
        z-index: 99999;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 20px;
    }

    .modal-overlay-syslogs {
        position: absolute;
        top: 0; left: 0; right: 0; bottom: 0;
        background: rgba(0,0,0,0.7);
        backdrop-filter: blur(4px);
        cursor: pointer;
    }

    .modal-content-syslogs {
        position: relative;
        background: var(--page-bg-card, #FFFFFF);
        border-radius: 16px;
        padding: 24px 28px;
        max-width: 500px;
        width: 100%;
        box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        border: 2px solid var(--page-border, #E2E8F0);
        animation: modalSlide 0.3s ease;
    }

    [data-theme="dark"] .modal-content-syslogs {
        background: #1E293B;
        border-color: #334155;
    }

    @keyframes modalSlide {
        from { opacity: 0; transform: translateY(30px) scale(0.95); }
        to { opacity: 1; transform: translateY(0) scale(1); }
    }

    .modal-header-syslogs {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding-bottom: 12px;
        border-bottom: 2px solid var(--page-border, #E2E8F0);
        margin-bottom: 16px;
    }

    [data-theme="dark"] .modal-header-syslogs {
        border-bottom-color: #334155;
    }

    .modal-header-syslogs h3 {
        font-size: 1.1rem;
        font-weight: 700;
        color: var(--page-text-primary, #1E293B);
        display: flex;
        align-items: center;
        gap: 8px;
        margin: 0;
    }

    [data-theme="dark"] .modal-header-syslogs h3 {
        color: #F1F5F9;
    }

    .modal-close-syslogs {
        background: none;
        border: none;
        font-size: 1.5rem;
        cursor: pointer;
        color: var(--page-text-secondary, #64748B);
        padding: 0 4px;
        line-height: 1;
        transition: color 0.3s;
    }

    .modal-close-syslogs:hover {
        color: #DC2626;
    }

    .modal-body-syslogs {
        color: var(--page-text-primary, #1E293B);
        font-size: 0.9rem;
        line-height: 1.6;
    }

    [data-theme="dark"] .modal-body-syslogs {
        color: #F1F5F9;
    }

    [data-theme="dark"] .modal-body-syslogs div[style*="FEE2E2"] {
        background: #3A1A1A !important;
        border-color: #DC2626 !important;
    }

    [data-theme="dark"] .modal-body-syslogs div[style*="FEE2E2"] p {
        color: #F87171 !important;
    }

    .modal-actions-syslogs {
        display: flex;
        gap: 10px;
        justify-content: flex-end;
        padding-top: 16px;
        margin-top: 16px;
        border-top: 2px solid var(--page-border, #E2E8F0);
        flex-wrap: wrap;
    }

    [data-theme="dark"] .modal-actions-syslogs {
        border-top-color: #334155;
    }

    .btn-cancel-syslogs {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 10px 22px;
        border-radius: 10px;
        font-weight: 600;
        font-size: 0.85rem;
        border: 2px solid var(--page-border, #E2E8F0);
        background: transparent;
        color: var(--page-text-secondary, #64748B);
        cursor: pointer;
        transition: all 0.3s;
        font-family: inherit;
    }

    [data-theme="dark"] .btn-cancel-syslogs {
        border-color: #334155;
        color: #94A3B8;
    }

    .btn-cancel-syslogs:hover {
        border-color: #0B5ED7;
        color: #0B5ED7;
    }

    .btn-confirm-delete-syslogs {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 10px 24px;
        border-radius: 10px;
        font-weight: 700;
        font-size: 0.85rem;
        border: none;
        background: linear-gradient(135deg, #DC2626, #B91C1C);
        color: white;
        cursor: pointer;
        transition: all 0.3s;
        box-shadow: 0 4px 12px rgba(220, 38, 38, 0.3);
        font-family: inherit;
    }

    .btn-confirm-delete-syslogs:hover {
        background: linear-gradient(135deg, #B91C1C, #991B1B);
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(220, 38, 38, 0.4);
    }
</style>

<!-- ================================================================ -->
<!-- PAGE-SPECIFIC JAVASCRIPT -->
<!-- ================================================================ -->
<script>
    // ================================================================
    // FOOTER TIME
    // ================================================================
    setInterval(function() {
        var now = new Date();
        var timeStr = now.toLocaleTimeString('en-US', {
            hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true
        });
        var ftEl = document.getElementById('footerTime');
        if (ftEl) ftEl.textContent = timeStr;
    }, 1000);

    // ================================================================
    // AUTO SEARCH - TABLE FILTER
    // ================================================================
    (function() {
        var input = document.getElementById('tableSearchInputSyslogs');
        var noResults = document.getElementById('noResultsSyslogs');
        var countDisplay = document.getElementById('countDisplaySyslogs');
        var searchInfo = document.getElementById('searchInfoSyslogs');
        var searchCount = document.getElementById('searchCountSyslogs');
        
        if (!input) return;
        
        var totalRows = document.querySelectorAll('.log-row-syslogs').length;
        
        input.addEventListener('input', function() {
            var query = this.value.toLowerCase().trim();
            var rows = document.querySelectorAll('.log-row-syslogs');
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
    function scrollTableSyslogs(dir) {
        var wrap = document.getElementById('tableWrapSyslogs');
        if (wrap) wrap.scrollBy({ left: dir === 'left' ? -400 : 400, behavior: 'smooth' });
    }

    function updateScrollButtonsSyslogs() {
        var wrap = document.getElementById('tableWrapSyslogs');
        var btnLeft = document.getElementById('scrollBtnLeftSyslogs');
        var btnRight = document.getElementById('scrollBtnRightSyslogs');
        if (!wrap || !btnLeft || !btnRight) return;
        
        var scrollLeft = wrap.scrollLeft;
        var maxScroll = wrap.scrollWidth - wrap.clientWidth;
        btnLeft.disabled = (scrollLeft <= 5);
        btnRight.disabled = (scrollLeft >= maxScroll - 5 || maxScroll <= 0);
    }

    document.addEventListener('DOMContentLoaded', function() {
        var wrap = document.getElementById('tableWrapSyslogs');
        if (wrap) {
            wrap.addEventListener('scroll', updateScrollButtonsSyslogs);
            setTimeout(updateScrollButtonsSyslogs, 200);
        }
        window.addEventListener('resize', function() {
            setTimeout(updateScrollButtonsSyslogs, 200);
        });
    });

    // ================================================================
    // DELETE SINGLE LOG MODAL
    // ================================================================
    function confirmDeleteLog(logId, action) {
        document.getElementById('deleteLogId').value = logId;
        document.getElementById('deleteLogAction').textContent = action;
        document.getElementById('deleteLogModal').style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }

    function closeDeleteLogModal() {
        document.getElementById('deleteLogModal').style.display = 'none';
        document.body.style.overflow = '';
    }

    // ================================================================
    // DELETE ALL LOGS MODAL
    // ================================================================
    function confirmDeleteAll() {
        document.getElementById('deleteAllModal').style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }

    function closeDeleteAllModal() {
        document.getElementById('deleteAllModal').style.display = 'none';
        document.body.style.overflow = '';
    }

    // ================================================================
    // ESC KEY TO CLOSE MODALS
    // ================================================================
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeDeleteLogModal();
            closeDeleteAllModal();
        }
    });

    console.log('%c📋 Braick - System Logs', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c✅ Uses SHARED header & sidebar', 'font-size:13px; color:#34D399;');
    console.log('%c✅ DELETE ALL + DELETE ROW buttons added', 'font-size:13px; color:#34D399;');
    console.log('%c✅ IP Address column removed', 'font-size:13px; color:#34D399;');
    console.log('%c🌙 Dark mode: Handled by header', 'font-size:13px; color:#7C3AED;');
</script>

</body>
</html>