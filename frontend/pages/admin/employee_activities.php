<?php
// ================================================================
// FILE: frontend/pages/admin/employee_activities.php
// SUPER ADMIN - EMPLOYEE ACTIVITIES
// ✅ Uses SHARED header & sidebar (NO DUPLICATES)
// ✅ Global CSS variables (--page-*) + page prefix (--ea-*)
// ✅ Full dark mode support (handled by header)
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

if ($_SESSION['role'] !== 'admin') {
    $role = $_SESSION['role'];
    switch ($role) {
        case 'doctor': header('Location: ../doctor/dashboard.php'); break;
        case 'reception': header('Location: ../reception/dashboard.php'); break;
        case 'pharmacy': header('Location: ../pharmacy/dashboard.php'); break;
        case 'laboratory': header('Location: ../laboratory/dashboard.php'); break;
        case 'cashier': header('Location: ../cashier/dashboard.php'); break;
        case 'audit': header('Location: ../audit/dashboard.php'); break;
        default: header('Location: ../login.php'); break;
    }
    exit;
}

// ================================================================
// GET ADMIN DATA
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
require_once __DIR__ . '/../../../backend/helpers/functions.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database connection error: " . $e->getMessage());
}

// ================================================================
// GET EMPLOYEE ID
// ================================================================
$employee_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$selected_branch_id = isset($_GET['branch']) ? $_GET['branch'] : 'all';

if ($employee_id <= 0) {
    header('Location: employees.php?branch=' . urlencode($selected_branch_id));
    exit;
}

// ================================================================
// FETCH EMPLOYEE DETAILS
// ================================================================
$stmt = $db->prepare("
    SELECT 
        u.id, u.full_name, u.username, u.email, u.role, u.branch_id,
        u.status, u.profile_pic, b.name as branch_name
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
} catch (Exception $e) { $unread_notifications = 0; }

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
    SELECT id, action, details, ip_address, user_agent, created_at
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
// GET RECENT ACTIVITIES (Last 5)
// ================================================================
$recent_stmt = $db->prepare("
    SELECT action, details, created_at
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
    SELECT action, COUNT(*) as count
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
    SELECT DATE(created_at) as date, COUNT(*) as count
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
// GET STATISTICS FOR SIDEBAR
// ================================================================
$total_employees = 0;
$stmt = $db->query("SELECT COUNT(*) as count FROM users WHERE role != 'admin'");
$total_employees = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

$total_doctors = 0;
$stmt = $db->query("SELECT COUNT(*) as count FROM users WHERE role = 'doctor' AND status = 'active'");
$total_doctors = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

$total_branches = 0;
$stmt = $db->query("SELECT COUNT(*) as count FROM branches WHERE status = 'active'");
$total_branches = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

$pending_lab_tests = 0;
try {
    $stmt = $db->query("SELECT COUNT(*) as count FROM lab_tests WHERE status = 'pending'");
    $pending_lab_tests = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
} catch (Exception $e) { $pending_lab_tests = 0; }

$pending_prescriptions = 0;
try {
    $stmt = $db->query("SELECT COUNT(*) as count FROM prescriptions WHERE status = 'pending'");
    $pending_prescriptions = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
} catch (Exception $e) { $pending_prescriptions = 0; }

// ================================================================
// ROLE LABEL
// ================================================================
$role_labels = [
    'doctor' => 'Doctor',
    'reception' => 'Receptionist',
    'pharmacy' => 'Pharmacist',
    'laboratory' => 'Lab Technician',
    'cashier' => 'Cashier',
    'audit' => 'Auditor'
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
// ✅ SHARED HEADER & SIDEBAR
// ================================================================
include_once __DIR__ . '/../../components/admin_header.php';
include_once __DIR__ . '/../../components/admin_sidebar.php';
?>

<!-- ================================================================ -->
<!-- PAGE-SPECIFIC CSS - TUMIA VARIABLES ZA HEADER (--page-*) -->
<!-- ================================================================ -->
<style>
    /* ================================================================
       PAGE PREFIX VARIABLES - EXTEND HEADER VARIABLES
       ================================================================ */
    .employee-activities-page {
        --ea-primary: var(--page-primary, #0B5ED7);
        --ea-primary-dark: var(--page-primary-dark, #0A4CA8);
        --ea-primary-bg: var(--page-primary-bg, #E8F0FE);
        --ea-primary-gradient: linear-gradient(135deg, #0B5ED7, #0A4CA8);
        --ea-success: #059669;
        --ea-success-bg: #D1FAE5;
        --ea-danger: #DC2626;
        --ea-warning: #D97706;
        --ea-purple: #7C3AED;
        --ea-orange: #F59E0B;
        --ea-radius: 16px;
        --ea-radius-sm: 10px;
    }

    /* ================================================================
       PAGE HEADER - BLUE GRADIENT
       ================================================================ */
    .page-header-ea {
        background: linear-gradient(135deg, #0B5ED7 0%, #0A4CA8 100%);
        border-radius: 18px;
        padding: 26px 34px;
        margin-bottom: 24px;
        display: flex;
        flex-wrap: wrap;
        justify-content: space-between;
        align-items: center;
        gap: 14px;
        box-shadow: 0 8px 32px rgba(10, 76, 168, 0.35);
        position: relative;
        overflow: hidden;
    }

    .page-header-ea::before {
        content: '';
        position: absolute;
        top: -60%;
        right: -10%;
        width: 400px;
        height: 400px;
        background: rgba(255,255,255,0.06);
        border-radius: 50%;
        pointer-events: none;
    }

    .page-header-ea::after {
        content: '';
        position: absolute;
        bottom: -40%;
        left: -5%;
        width: 300px;
        height: 300px;
        background: rgba(255,255,255,0.04);
        border-radius: 50%;
        pointer-events: none;
    }

    .page-header-ea .page-title {
        color: white;
        font-size: 1.7rem;
        font-weight: 700;
        margin: 0;
        display: flex;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
        position: relative;
        z-index: 1;
    }

    .page-header-ea .page-title i {
        font-size: 1.6rem;
        opacity: 0.9;
    }

    .page-header-ea .page-subtitle {
        color: rgba(255,255,255,0.88);
        font-size: 0.9rem;
        margin: 6px 0 0 0;
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 10px;
        position: relative;
        z-index: 1;
    }

    .page-header-ea .page-subtitle strong {
        color: white;
    }

    .page-header-ea .role-badge-display {
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

    .page-header-ea .header-badge {
        background: rgba(255,255,255,0.15);
        color: white;
        padding: 4px 14px;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: 600;
        backdrop-filter: blur(4px);
        display: inline-flex;
        align-items: center;
        gap: 6px;
        border: 1px solid rgba(255,255,255,0.1);
        transition: all 0.3s ease;
    }

    .page-header-ea .header-badge:hover {
        background: rgba(255,255,255,0.25);
        transform: translateY(-1px);
    }

    .page-header-ea .btn-outline-light {
        background: rgba(255,255,255,0.12);
        color: white;
        border: 1px solid rgba(255,255,255,0.2);
        padding: 8px 18px;
        border-radius: 12px;
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

    .page-header-ea .btn-outline-light:hover {
        background: rgba(255,255,255,0.25);
        transform: translateY(-2px);
        color: white;
        box-shadow: 0 4px 16px rgba(0,0,0,0.15);
    }

    /* ================================================================
       EMPLOYEE SUMMARY CARDS
       ================================================================ */
    .employee-summary-ea {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 16px;
        margin-bottom: 20px;
    }

    .summary-card-ea {
        background: var(--page-bg-card, #FFFFFF);
        border-radius: 14px;
        padding: 16px 20px;
        border: 2px solid var(--page-border, #E2E8F0);
        display: flex;
        align-items: center;
        gap: 14px;
        transition: all 0.3s ease;
        box-shadow: var(--page-shadow-sm, 0 1px 3px rgba(0,0,0,0.06));
    }

    .summary-card-ea:hover {
        border-color: var(--page-primary, #0B5ED7);
        box-shadow: 0 8px 24px rgba(11, 94, 215, 0.12);
        transform: translateY(-3px);
    }

    .summary-icon-ea {
        width: 46px;
        height: 46px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.1rem;
        flex-shrink: 0;
        transition: transform 0.3s ease;
    }

    .summary-card-ea:hover .summary-icon-ea {
        transform: scale(1.08) rotate(-3deg);
    }

    .summary-icon-ea.blue { background: linear-gradient(135deg, #E8F0FE, #DBEAFE); color: #0B5ED7; }
    .summary-icon-ea.green { background: linear-gradient(135deg, #ECFDF5, #D1FAE5); color: #059669; }
    .summary-icon-ea.purple { background: linear-gradient(135deg, #F5F3FF, #EDE9FE); color: #7C3AED; }
    .summary-icon-ea.orange { background: linear-gradient(135deg, #FFFBEB, #FEF3C7); color: #D97706; }

    [data-theme="dark"] .summary-icon-ea.blue { background: linear-gradient(135deg, #1E3A5F, #1E40AF); color: #6EA8FE; }
    [data-theme="dark"] .summary-icon-ea.green { background: linear-gradient(135deg, #1A3A2A, #065F46); color: #34D399; }
    [data-theme="dark"] .summary-icon-ea.purple { background: linear-gradient(135deg, #2D1B4E, #4C1D95); color: #A78BFA; }
    [data-theme="dark"] .summary-icon-ea.orange { background: linear-gradient(135deg, #3D2E0A, #78350F); color: #FBBF24; }

    .summary-content-ea {
        flex: 1;
        min-width: 0;
    }

    .summary-label-ea {
        font-size: 0.68rem;
        color: var(--page-text-secondary, #64748B);
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        margin: 0 0 3px 0;
    }

    .summary-value-ea {
        font-size: 1rem;
        font-weight: 700;
        color: var(--page-text-primary, #1E293B);
        margin: 0;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .summary-sub-ea {
        font-size: 0.72rem;
        color: var(--page-text-secondary, #64748B);
        margin: 2px 0 0 0;
    }

    /* ================================================================
       CARDS
       ================================================================ */
    .card-ea {
        background: var(--page-bg-card, #FFFFFF);
        border-radius: 16px;
        border: 2px solid var(--page-border, #E2E8F0);
        padding: 18px 20px;
        transition: all 0.3s ease;
        box-shadow: var(--page-shadow-sm, 0 1px 3px rgba(0,0,0,0.06));
        margin-bottom: 18px;
    }

    .card-ea:hover {
        border-color: var(--page-primary, #0B5ED7);
        box-shadow: var(--page-shadow-md, 0 4px 12px rgba(0,0,0,0.08));
    }

    .card-header-ea {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 8px;
        margin-bottom: 14px;
        padding-bottom: 12px;
        border-bottom: 2px dashed var(--page-border, #E2E8F0);
    }

    .card-title-ea {
        font-size: 0.92rem;
        font-weight: 700;
        color: var(--page-text-primary, #1E293B);
        margin: 0;
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 6px;
    }

    .card-title-ea .icon-badge {
        width: 32px;
        height: 32px;
        border-radius: 10px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 0.9rem;
        margin-right: 4px;
    }

    .card-title-ea .icon-badge.blue { background: #E8F0FE; color: #0B5ED7; }
    .card-title-ea .icon-badge.green { background: #ECFDF5; color: #059669; }
    .card-title-ea .icon-badge.purple { background: #F5F3FF; color: #7C3AED; }
    .card-title-ea .icon-badge.orange { background: #FFFBEB; color: #D97706; }
    .card-title-ea .icon-badge.red { background: #FEE2E2; color: #DC2626; }

    [data-theme="dark"] .card-title-ea .icon-badge.blue { background: #1E3A5F; color: #6EA8FE; }
    [data-theme="dark"] .card-title-ea .icon-badge.green { background: #1A3A2A; color: #34D399; }
    [data-theme="dark"] .card-title-ea .icon-badge.purple { background: #2D1B4E; color: #A78BFA; }
    [data-theme="dark"] .card-title-ea .icon-badge.orange { background: #3D2E0A; color: #FBBF24; }
    [data-theme="dark"] .card-title-ea .icon-badge.red { background: #3A1A1A; color: #F87171; }

    .card-title-ea .count-badge {
        font-size: 0.7rem;
        font-weight: 500;
        color: var(--page-text-secondary, #64748B);
        background: var(--page-hover, #F8FAFC);
        padding: 2px 10px;
        border-radius: 12px;
        margin-left: 6px;
    }

    /* ================================================================
       RECENT ACTIVITIES
       ================================================================ */
    .recent-activities-list-ea {
        max-height: 260px;
        overflow-y: auto;
        padding-right: 4px;
    }

    .recent-activity-item-ea {
        display: flex;
        align-items: flex-start;
        gap: 12px;
        padding: 10px 12px;
        border-radius: 10px;
        transition: all 0.3s ease;
        border: 1.5px solid transparent;
        margin-bottom: 6px;
    }

    .recent-activity-item-ea:last-child {
        margin-bottom: 0;
    }

    .recent-activity-item-ea:hover {
        background: var(--page-hover, #F8FAFC);
        border-color: var(--page-primary, #0B5ED7);
        transform: translateX(3px);
    }

    [data-theme="dark"] .recent-activity-item-ea:hover {
        background: #0F172A;
        border-color: #3B82F6;
    }

    .recent-activity-icon-ea {
        width: 34px;
        height: 34px;
        border-radius: 50%;
        background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        flex-shrink: 0;
        font-size: 0.7rem;
        box-shadow: 0 3px 8px rgba(11, 94, 215, 0.3);
    }

    .recent-activity-content-ea {
        flex: 1;
        min-width: 0;
    }

    .recent-activity-action-ea {
        font-weight: 600;
        font-size: 0.82rem;
        color: var(--page-text-primary, #1E293B);
        margin: 0 0 2px 0;
        word-wrap: break-word;
    }

    .recent-activity-details-ea {
        font-size: 0.72rem;
        color: var(--page-text-secondary, #64748B);
        margin: 0;
        word-wrap: break-word;
        line-height: 1.4;
    }

    .recent-activity-time-ea {
        font-size: 0.65rem;
        color: var(--page-text-muted, #94A3B8);
        white-space: nowrap;
        flex-shrink: 0;
        font-weight: 500;
    }

    /* ================================================================
       STAT BARS
       ================================================================ */
    .stat-bar-ea {
        margin-bottom: 10px;
    }

    .stat-bar-ea:last-child {
        margin-bottom: 0;
    }

    .stat-bar-label-ea {
        font-size: 0.72rem;
        color: var(--page-text-primary, #1E293B);
        margin-bottom: 4px;
        font-weight: 600;
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 8px;
    }

    .stat-bar-label-ea .action-name-ea {
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .stat-bar-label-ea .action-count-ea {
        background: #E8F0FE;
        color: #0B5ED7;
        padding: 1px 8px;
        border-radius: 10px;
        font-size: 0.65rem;
        font-weight: 700;
        flex-shrink: 0;
    }

    [data-theme="dark"] .stat-bar-label-ea .action-count-ea {
        background: #1E3A5F;
        color: #6EA8FE;
    }

    .stat-bar-track-ea {
        background: var(--page-hover, #F8FAFC);
        border-radius: 6px;
        overflow: hidden;
        height: 22px;
        position: relative;
        border: 1px solid var(--page-border, #E2E8F0);
    }

    [data-theme="dark"] .stat-bar-track-ea {
        background: #0F172A;
    }

    .stat-bar-fill-ea {
        height: 100%;
        background: linear-gradient(90deg, #0B5ED7, #1A73E8, #3B82F6);
        border-radius: 6px;
        display: flex;
        align-items: center;
        justify-content: flex-end;
        padding-right: 8px;
        font-size: 0.65rem;
        font-weight: 700;
        color: white;
        transition: width 0.6s cubic-bezier(0.4, 0, 0.2, 1);
        min-width: 28px;
        box-shadow: 0 2px 6px rgba(11, 94, 215, 0.25);
    }

    [data-theme="dark"] .stat-bar-fill-ea {
        background: linear-gradient(90deg, #1E40AF, #2563EB, #3B82F6);
    }

    /* ================================================================
       CHART CONTAINER
       ================================================================ */
    .chart-container-ea {
        width: 100%;
        max-height: 200px;
        height: 200px;
        position: relative;
        padding: 4px;
    }

    .chart-container-ea canvas {
        width: 100% !important;
        height: 100% !important;
    }

    /* ================================================================
       FILTER SECTION
       ================================================================ */
    .filter-section-ea {
        background: var(--page-bg-card, #FFFFFF);
        border-radius: 16px;
        padding: 18px 20px;
        border: 2px solid var(--page-border, #E2E8F0);
        margin-bottom: 18px;
        box-shadow: var(--page-shadow-sm, 0 1px 3px rgba(0,0,0,0.06));
    }

    .filter-row-ea {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
        align-items: flex-end;
    }

    .filter-group-ea {
        display: flex;
        flex-direction: column;
        gap: 5px;
        flex: 1;
        min-width: 150px;
    }

    .filter-group-ea label {
        font-size: 0.68rem;
        font-weight: 700;
        color: var(--page-text-secondary, #64748B);
        text-transform: uppercase;
        letter-spacing: 0.04em;
    }

    .filter-select-ea,
    .filter-input-ea {
        padding: 9px 14px;
        border: 2px solid var(--page-border, #E2E8F0);
        border-radius: 10px;
        background: var(--page-input-bg, #FFFFFF);
        color: var(--page-text-primary, #1E293B);
        font-size: 0.82rem;
        outline: none;
        transition: all 0.3s;
        width: 100%;
        font-family: inherit;
        font-weight: 500;
    }

    .filter-select-ea:focus,
    .filter-input-ea:focus {
        border-color: var(--page-primary, #0B5ED7);
        box-shadow: 0 0 0 4px rgba(11, 94, 215, 0.12);
    }

    .filter-select-ea option {
        background: var(--page-bg-card, #FFFFFF);
        color: var(--page-text-primary, #1E293B);
    }

    [data-theme="dark"] .filter-select-ea option {
        background: #1E293B;
        color: #F1F5F9;
    }

    .filter-actions-ea {
        display: flex;
        gap: 8px;
        align-items: center;
        flex: 0 0 auto;
        min-width: auto;
    }

    /* ================================================================
       BUTTONS
       ================================================================ */
    .btn-ea {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        padding: 9px 20px;
        border-radius: 10px;
        font-weight: 600;
        font-size: 0.82rem;
        transition: all 0.3s ease;
        cursor: pointer;
        border: none;
        text-decoration: none;
        font-family: inherit;
        min-height: 42px;
        white-space: nowrap;
    }

    .btn-ea:hover {
        transform: translateY(-2px);
    }

    .btn-sm-ea {
        padding: 8px 16px;
        font-size: 0.78rem;
        min-height: 38px;
    }

    .btn-blue-ea {
        background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
        color: white;
        box-shadow: 0 4px 12px rgba(11, 94, 215, 0.25);
    }

    .btn-blue-ea:hover {
        background: linear-gradient(135deg, #0A4CA8, #083C8A);
        color: white;
        box-shadow: 0 6px 20px rgba(11, 94, 215, 0.4);
    }

    .btn-outline-ea {
        background: transparent;
        color: var(--page-text-secondary, #64748B);
        border: 2px solid var(--page-border, #E2E8F0);
    }

    .btn-outline-ea:hover {
        background: var(--page-hover, #F8FAFC);
        border-color: var(--page-primary, #0B5ED7);
        color: var(--page-primary, #0B5ED7);
    }

    [data-theme="dark"] .btn-outline-ea:hover {
        background: #0F172A;
        border-color: #6EA8FE;
        color: #6EA8FE;
    }

    /* ================================================================
       DATA TABLE
       ================================================================ */
    .data-table-ea {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
        font-size: 0.82rem;
    }

    .data-table-ea thead th {
        background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
        color: white;
        font-weight: 700;
        padding: 12px 14px;
        font-size: 0.68rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        border-bottom: none;
        text-align: left;
        white-space: nowrap;
    }

    .data-table-ea thead th:first-child {
        border-radius: 10px 0 0 0;
    }

    .data-table-ea thead th:last-child {
        border-radius: 0 10px 0 0;
    }

    .data-table-ea td {
        padding: 12px 14px;
        border-bottom: 1px solid var(--page-border, #E2E8F0);
        color: var(--page-text-primary, #1E293B);
        vertical-align: middle;
        line-height: 1.5;
    }

    .data-table-ea tbody tr {
        transition: background 0.2s ease;
    }

    .data-table-ea tbody tr:hover td {
        background: var(--page-hover, #F8FAFC);
    }

    [data-theme="dark"] .data-table-ea tbody tr:hover td {
        background: #0F172A;
    }

    .data-table-ea tbody tr:last-child td {
        border-bottom: none;
    }

    .data-table-ea tbody tr:last-child td:first-child {
        border-radius: 0 0 0 10px;
    }

    .data-table-ea tbody tr:last-child td:last-child {
        border-radius: 0 0 10px 0;
    }

    /* Action Badge */
    .action-badge-ea {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 0.68rem;
        font-weight: 600;
        background: #E8F0FE;
        color: #0B5ED7;
        border: 1px solid #BFDBFE;
        white-space: nowrap;
    }

    [data-theme="dark"] .action-badge-ea {
        background: #1E3A5F;
        color: #6EA8FE;
        border-color: #3B82F6;
    }

    .action-badge-ea i {
        font-size: 0.6rem;
    }

    /* IP Badge */
    .ip-badge-ea {
        display: inline-flex;
        align-items: center;
        padding: 3px 10px;
        border-radius: 6px;
        font-size: 0.68rem;
        font-family: 'Courier New', monospace;
        font-weight: 600;
        background: var(--page-hover, #F8FAFC);
        color: var(--page-text-secondary, #64748B);
        border: 1px solid var(--page-border, #E2E8F0);
    }

    [data-theme="dark"] .ip-badge-ea {
        background: #0F172A;
    }

    /* Date Cell */
    .date-cell-ea {
        display: flex;
        flex-direction: column;
        gap: 2px;
        font-size: 0.72rem;
    }

    .date-cell-ea .date-main-ea {
        font-weight: 600;
        color: var(--page-text-primary, #1E293B);
    }

    .date-cell-ea .date-time-ea {
        font-size: 0.68rem;
        color: var(--page-text-secondary, #64748B);
    }

    /* ================================================================
       PAGINATION
       ================================================================ */
    .pagination-container-ea {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 16px 0 4px 0;
        border-top: 2px dashed var(--page-border, #E2E8F0);
        margin-top: 16px;
        flex-wrap: wrap;
        gap: 12px;
    }

    .pagination-info-ea {
        font-size: 0.75rem;
        color: var(--page-text-secondary, #64748B);
        font-weight: 500;
    }

    .pagination-btn-ea {
        padding: 8px 16px;
        border-radius: 10px;
        font-size: 0.78rem;
        font-weight: 600;
        background: var(--page-bg-card, #FFFFFF);
        color: var(--page-text-primary, #1E293B);
        text-decoration: none;
        border: 2px solid var(--page-border, #E2E8F0);
        transition: all 0.3s;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }

    .pagination-btn-ea:hover {
        background: var(--page-primary, #0B5ED7);
        color: white;
        border-color: var(--page-primary, #0B5ED7);
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3);
    }

    .pagination-pages-ea {
        display: flex;
        gap: 4px;
    }

    .pagination-page-ea {
        width: 36px;
        height: 36px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 10px;
        font-size: 0.8rem;
        font-weight: 600;
        color: var(--page-text-secondary, #64748B);
        text-decoration: none;
        border: 2px solid var(--page-border, #E2E8F0);
        background: var(--page-bg-card, #FFFFFF);
        transition: all 0.3s;
    }

    .pagination-page-ea:hover {
        background: var(--page-hover, #F8FAFC);
        border-color: var(--page-primary, #0B5ED7);
        color: var(--page-primary, #0B5ED7);
        transform: translateY(-2px);
    }

    .pagination-page-ea.active {
        background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
        color: white;
        border-color: #0B5ED7;
        box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3);
    }

    /* ================================================================
       EMPTY STATE
       ================================================================ */
    .empty-state-ea {
        text-align: center;
        padding: 40px 20px;
        color: var(--page-text-secondary, #64748B);
    }

    .empty-state-ea i {
        font-size: 2.5rem;
        color: var(--page-border, #E2E8F0);
        margin-bottom: 12px;
        display: block;
    }

    .empty-state-ea .empty-title {
        font-size: 0.95rem;
        font-weight: 600;
        color: var(--page-text-primary, #1E293B);
        margin: 0 0 4px 0;
    }

    .empty-state-ea .empty-sub {
        font-size: 0.8rem;
        color: var(--page-text-secondary, #64748B);
        margin: 0;
    }

    /* ================================================================
       FOOTER
       ================================================================ */
    .footer-ea {
        margin-top: 24px;
        padding: 16px 20px;
        background: var(--page-bg-card, #FFFFFF);
        border-radius: 14px;
        border: 2px solid var(--page-border, #E2E8F0);
        text-align: center;
        box-shadow: var(--page-shadow-sm, 0 1px 3px rgba(0,0,0,0.06));
    }

    .footer-ea p {
        margin: 0;
        font-size: 0.75rem;
        color: var(--page-text-secondary, #64748B);
    }

    .footer-ea .footer-brand {
        font-weight: 700;
        color: var(--page-primary, #0B5ED7);
    }

    /* ================================================================
       GRID LAYOUT - 3 COLUMN
       ================================================================ */
    .grid-3-ea {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 18px;
        margin-bottom: 18px;
    }

    /* ================================================================
       ANIMATIONS
       ================================================================ */
    @keyframes fadeInUpEa {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .animate-fade-in-up-ea {
        animation: fadeInUpEa 0.4s ease forwards;
        opacity: 0;
    }

    /* ================================================================
       RESPONSIVE
       ================================================================ */
    @media (max-width: 1024px) {
        .grid-3-ea { grid-template-columns: 1fr 1fr; }
    }

    @media (max-width: 768px) {
        .page-header-ea { padding: 18px 20px; flex-direction: column; align-items: flex-start; }
        .page-header-ea .page-title { font-size: 1.3rem; }
        .employee-summary-ea { grid-template-columns: 1fr 1fr; }
        .filter-row-ea { flex-direction: column; gap: 10px; }
        .filter-group-ea { min-width: 100%; }
        .filter-actions-ea { flex-direction: row; width: 100%; }
        .filter-actions-ea .btn-ea { flex: 1; }
        .pagination-container-ea { flex-direction: column; gap: 10px; }
        .grid-3-ea { grid-template-columns: 1fr; }
        .recent-activity-item-ea { flex-wrap: wrap; }
        .recent-activity-time-ea { width: 100%; text-align: right; }
        .data-table-ea { font-size: 0.7rem; }
        .data-table-ea td, .data-table-ea th { padding: 8px 10px; }
        .pagination-page-ea { width: 32px; height: 32px; font-size: 0.75rem; }
    }

    @media (max-width: 480px) {
        .employee-summary-ea { grid-template-columns: 1fr; }
        .page-title-ea { font-size: 1rem; }
        .btn-ea { font-size: 0.75rem; padding: 7px 14px; }
        .btn-sm-ea { font-size: 0.7rem; padding: 6px 12px; }
        .summary-card-ea { padding: 12px 16px; }
        .summary-icon-ea { width: 38px; height: 38px; font-size: 0.95rem; }
        .summary-value-ea { font-size: 0.88rem; }
        .recent-activity-item-ea { padding: 8px 10px; }
    }

    /* ================================================================
       PRINT
       ================================================================ */
    @media print {
        .btn-ea, .filter-section-ea, .page-header-ea .btn-outline-light { display: none !important; }
        .card-ea { box-shadow: none !important; border: 1px solid #ddd !important; }
        .page-header-ea {
            background: #0B5ED7 !important;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }
        .page-header-ea .page-title, .page-header-ea .page-subtitle,
        .page-header-ea .header-badge, .page-header-ea .role-badge-display {
            color: white !important;
        }
    }
</style>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content employee-activities-page">

    <!-- ================================================================ -->
    <!-- PAGE HEADER -->
    <!-- ================================================================ -->
    <div class="page-header-ea animate-fade-in-up-ea">
        <div>
            <h1 class="page-title">
                <i class="fas fa-clock"></i>
                Employee Activities
                <span class="role-badge-display">ADMIN</span>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-user"></i>
                <strong><?= htmlspecialchars($employee['full_name']) ?></strong>
                <span class="header-badge">
                    <i class="fas fa-store-alt"></i> <?= htmlspecialchars($employee['branch_name'] ?? 'N/A') ?>
                </span>
                <span class="header-badge">
                    <i class="fas fa-user-tag"></i> <?= $role_display ?>
                </span>
                <span class="header-badge" style="background:rgba(251,191,36,0.2);border-color:rgba(251,191,36,0.3);color:#FBBF24;">
                    <i class="fas fa-list"></i> <?= number_format($total_activities) ?> Activities
                </span>
            </p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;position:relative;z-index:1;">
            <a href="view_employee.php?id=<?= $employee['id'] ?>&branch=<?= $selected_branch_id ?>" class="btn-outline-light">
                <i class="fas fa-user"></i> View Employee
            </a>
            <a href="employees.php?branch=<?= $selected_branch_id ?>" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- EMPLOYEE SUMMARY CARDS -->
    <!-- ================================================================ -->
    <div class="employee-summary-ea animate-fade-in-up-ea" style="animation-delay:0.05s;">
        <div class="summary-card-ea">
            <div class="summary-icon-ea blue">
                <i class="fas fa-user"></i>
            </div>
            <div class="summary-content-ea">
                <p class="summary-label-ea">Employee</p>
                <p class="summary-value-ea"><?= htmlspecialchars($employee['full_name']) ?></p>
                <p class="summary-sub-ea">@<?= htmlspecialchars($employee['username']) ?></p>
            </div>
        </div>
        <div class="summary-card-ea">
            <div class="summary-icon-ea green">
                <i class="fas fa-briefcase"></i>
            </div>
            <div class="summary-content-ea">
                <p class="summary-label-ea">Role</p>
                <p class="summary-value-ea"><?= $role_display ?></p>
            </div>
        </div>
        <div class="summary-card-ea">
            <div class="summary-icon-ea purple">
                <i class="fas fa-envelope"></i>
            </div>
            <div class="summary-content-ea">
                <p class="summary-label-ea">Email</p>
                <p class="summary-value-ea" title="<?= htmlspecialchars($employee['email']) ?>"><?= htmlspecialchars($employee['email']) ?></p>
            </div>
        </div>
        <div class="summary-card-ea">
            <div class="summary-icon-ea orange">
                <i class="fas fa-list"></i>
            </div>
            <div class="summary-content-ea">
                <p class="summary-label-ea">Total Activities</p>
                <p class="summary-value-ea"><?= number_format($total_activities) ?></p>
            </div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- 3 COLUMN - RECENT ACTIVITIES | TOP ACTIONS | CHART -->
    <!-- ================================================================ -->
    <div class="grid-3-ea animate-fade-in-up-ea" style="animation-delay:0.1s;">
        
        <!-- Recent Activities -->
        <div class="card-ea">
            <div class="card-header-ea">
                <h3 class="card-title-ea">
                    <span class="icon-badge blue"><i class="fas fa-clock"></i></span>
                    Recent Activities
                    <span class="count-badge">Last 5</span>
                </h3>
            </div>
            <?php if (count($recent_activities) > 0): ?>
                <div class="recent-activities-list-ea">
                    <?php foreach ($recent_activities as $activity): ?>
                        <div class="recent-activity-item-ea">
                            <div class="recent-activity-icon-ea">
                                <i class="fas fa-bolt"></i>
                            </div>
                            <div class="recent-activity-content-ea">
                                <p class="recent-activity-action-ea"><?= htmlspecialchars($activity['action'] ?? 'Action') ?></p>
                                <p class="recent-activity-details-ea"><?= htmlspecialchars(substr($activity['details'] ?? '', 0, 80)) ?><?= strlen($activity['details'] ?? '') > 80 ? '...' : '' ?></p>
                            </div>
                            <span class="recent-activity-time-ea">
                                <?= isset($activity['created_at']) ? time_ago($activity['created_at']) : 'Just now' ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state-ea">
                    <i class="fas fa-inbox"></i>
                    <p class="empty-title">No Recent Activities</p>
                    <p class="empty-sub">This employee has no recent activities</p>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Top Actions -->
        <div class="card-ea">
            <div class="card-header-ea">
                <h3 class="card-title-ea">
                    <span class="icon-badge green"><i class="fas fa-chart-pie"></i></span>
                    Top Actions
                    <span class="count-badge">Top 10</span>
                </h3>
            </div>
            <?php if (count($action_stats) > 0): ?>
                <div>
                    <?php $max_count = max(array_column($action_stats, 'count')); ?>
                    <?php foreach ($action_stats as $stat): ?>
                        <div class="stat-bar-ea">
                            <div class="stat-bar-label-ea">
                                <span class="action-name-ea" title="<?= htmlspecialchars($stat['action']) ?>"><?= htmlspecialchars($stat['action']) ?></span>
                                <span class="action-count-ea"><?= $stat['count'] ?></span>
                            </div>
                            <div class="stat-bar-track-ea">
                                <div class="stat-bar-fill-ea" style="width: <?= ($stat['count'] / max(1, $max_count)) * 100 ?>%;">
                                    <?= $stat['count'] ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state-ea">
                    <i class="fas fa-chart-pie"></i>
                    <p class="empty-title">No Actions Found</p>
                    <p class="empty-sub">No activity data available</p>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Daily Activity Chart -->
        <div class="card-ea">
            <div class="card-header-ea">
                <h3 class="card-title-ea">
                    <span class="icon-badge purple"><i class="fas fa-chart-line"></i></span>
                    Daily Activity
                    <span class="count-badge">Last 30 days</span>
                </h3>
            </div>
            <?php if (count($chart_labels) > 0): ?>
                <div class="chart-container-ea">
                    <canvas id="activityChart"></canvas>
                </div>
            <?php else: ?>
                <div class="empty-state-ea">
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
    <div class="filter-section-ea animate-fade-in-up-ea" style="animation-delay:0.15s;">
        <form method="GET" action="">
            <input type="hidden" name="id" value="<?= $employee['id'] ?>">
            <input type="hidden" name="branch" value="<?= $selected_branch_id ?>">
            
            <div class="filter-row-ea">
                <div class="filter-group-ea">
                    <label><i class="fas fa-filter"></i> Action Type</label>
                    <select name="action" class="filter-select-ea">
                        <option value="">All Actions</option>
                        <?php foreach ($unique_actions as $action): ?>
                            <option value="<?= htmlspecialchars($action) ?>" <?= $action_filter === $action ? 'selected' : '' ?>>
                                <?= htmlspecialchars($action) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group-ea">
                    <label><i class="fas fa-calendar"></i> Date</label>
                    <input type="date" name="date" class="filter-input-ea" value="<?= $date_filter ?>">
                </div>
                
                <div class="filter-group-ea">
                    <label><i class="fas fa-search"></i> Search</label>
                    <input type="text" name="search" class="filter-input-ea" placeholder="Search activities..." value="<?= htmlspecialchars($search_filter) ?>">
                </div>
                
                <div class="filter-actions-ea">
                    <button type="submit" class="btn-ea btn-blue-ea btn-sm-ea">
                        <i class="fas fa-filter"></i> Apply
                    </button>
                    <a href="employee_activities.php?id=<?= $employee['id'] ?>&branch=<?= $selected_branch_id ?>" class="btn-ea btn-outline-ea btn-sm-ea">
                        <i class="fas fa-undo"></i> Reset
                    </a>
                </div>
            </div>
        </form>
    </div>

    <!-- ================================================================ -->
    <!-- ACTIVITIES TABLE -->
    <!-- ================================================================ -->
    <div class="card-ea animate-fade-in-up-ea" style="animation-delay:0.2s;">
        <div class="card-header-ea">
            <h3 class="card-title-ea">
                <span class="icon-badge blue"><i class="fas fa-list"></i></span>
                Activity Log
                <span class="count-badge"><?= number_format($total_activities) ?> records</span>
            </h3>
            <span style="font-size:0.75rem;color:var(--page-text-secondary);font-weight:500;">
                Page <?= $page ?> of <?= $total_pages ?>
            </span>
        </div>
        
        <div style="overflow-x:auto;">
            <table class="data-table-ea">
                <thead>
                    <tr>
                        <th style="width: 50px;">#</th>
                        <th style="min-width: 180px;">Action</th>
                        <th style="min-width: 260px;">Details</th>
                        <th style="width: 130px;">IP Address</th>
                        <th style="width: 160px;">Date & Time</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($activities) > 0): ?>
                        <?php $i = $offset + 1; foreach ($activities as $activity): ?>
                            <tr>
                                <td style="text-align:center;color:var(--page-text-secondary);font-weight:600;"><?= $i++ ?></td>
                                <td>
                                    <span class="action-badge-ea">
                                        <i class="fas fa-bolt"></i>
                                        <?= htmlspecialchars($activity['action']) ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($activity['details'] ?? '') ?></td>
                                <td>
                                    <span class="ip-badge-ea"><?= htmlspecialchars($activity['ip_address'] ?? 'N/A') ?></span>
                                </td>
                                <td>
                                    <div class="date-cell-ea">
                                        <span class="date-main-ea"><?= date('M d, Y', strtotime($activity['created_at'])) ?></span>
                                        <span class="date-time-ea"><?= date('h:i:s A', strtotime($activity['created_at'])) ?></span>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5">
                                <div class="empty-state-ea">
                                    <i class="fas fa-inbox"></i>
                                    <p class="empty-title">No Activities Found</p>
                                    <p class="empty-sub">Try changing your filters or search criteria</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div class="pagination-container-ea">
                <div class="pagination-info-ea">
                    Showing <strong><?= $offset + 1 ?>-<?= min($offset + $per_page, $total_activities) ?></strong> of <strong><?= $total_activities ?></strong> activities
                </div>
                
                <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                    <?php if ($page > 1): ?>
                        <a href="?id=<?= $employee['id'] ?>&branch=<?= $selected_branch_id ?>&page=<?= $page - 1 ?>&action=<?= urlencode($action_filter) ?>&date=<?= urlencode($date_filter) ?>&search=<?= urlencode($search_filter) ?>" 
                           class="pagination-btn-ea">
                            <i class="fas fa-chevron-left"></i> Prev
                        </a>
                    <?php endif; ?>
                    
                    <div class="pagination-pages-ea">
                        <?php 
                        $start_page = max(1, $page - 2);
                        $end_page = min($total_pages, $page + 2);
                        for ($p = $start_page; $p <= $end_page; $p++): ?>
                            <a href="?id=<?= $employee['id'] ?>&branch=<?= $selected_branch_id ?>&page=<?= $p ?>&action=<?= urlencode($action_filter) ?>&date=<?= urlencode($date_filter) ?>&search=<?= urlencode($search_filter) ?>" 
                               class="pagination-page-ea <?= $p === $page ? 'active' : '' ?>">
                                <?= $p ?>
                            </a>
                        <?php endfor; ?>
                    </div>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="?id=<?= $employee['id'] ?>&branch=<?= $selected_branch_id ?>&page=<?= $page + 1 ?>&action=<?= urlencode($action_filter) ?>&date=<?= urlencode($date_filter) ?>&search=<?= urlencode($search_filter) ?>" 
                           class="pagination-btn-ea">
                            Next <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer-ea">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span style="color:#CBD5E1;margin:0 8px;">|</span>
            Employee Activities
            <span style="color:#CBD5E1;margin:0 8px;">|</span>
            <span id="footerTime"><?= date('H:i:s') ?></span>
            <span style="color:#CBD5E1;margin:0 8px;">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<!-- ================================================================ -->
<!-- PAGE-SPECIFIC JAVASCRIPT (NO dark mode, NO sidebar, NO date-time) -->
<!-- ================================================================ -->
<script>
    // ================================================================
    // ACTIVITY CHART
    // ================================================================
    document.addEventListener('DOMContentLoaded', function() {
        var canvas = document.getElementById('activityChart');
        if (canvas && typeof Chart !== 'undefined') {
            var labels = <?= json_encode($chart_labels) ?>;
            var values = <?= json_encode($chart_values) ?>;
            
            var isDark = document.documentElement.getAttribute('data-theme') === 'dark';
            var tickColor = isDark ? '#94A3B8' : '#64748B';
            var gridColor = isDark ? 'rgba(255,255,255,0.05)' : 'rgba(0,0,0,0.05)';
            
            if (labels.length > 0) {
                new Chart(canvas, {
                    type: 'bar',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: 'Activities',
                            data: values,
                            backgroundColor: isDark ? 'rgba(96, 165, 250, 0.7)' : 'rgba(11, 94, 215, 0.7)',
                            borderColor: isDark ? '#60A5FA' : '#0B5ED7',
                            borderWidth: 1.5,
                            borderRadius: 4,
                            barPercentage: 0.65
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                backgroundColor: isDark ? '#1E293B' : '#FFFFFF',
                                titleColor: isDark ? '#F1F5F9' : '#1E293B',
                                bodyColor: isDark ? '#94A3B8' : '#64748B',
                                borderColor: isDark ? '#334155' : '#E2E8F0',
                                borderWidth: 1,
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
                                    font: { size: 10, weight: '500' },
                                    color: tickColor
                                },
                                grid: { color: gridColor, drawBorder: false }
                            },
                            x: {
                                grid: { display: false },
                                ticks: {
                                    font: { size: 9, weight: '500' },
                                    maxRotation: 45,
                                    minRotation: 30,
                                    color: tickColor
                                }
                            }
                        },
                        animation: {
                            duration: 900,
                            easing: 'easeOutQuart'
                        }
                    }
                });
            }
        }
        
        // Listen for dark mode changes - rebuild chart
        document.addEventListener('darkModeChanged', function() {
            // Optional: rebuild chart on theme change
            setTimeout(function() {
                var existingCanvas = document.getElementById('activityChart');
                if (existingCanvas && typeof Chart !== 'undefined') {
                    var chart = Chart.getChart(existingCanvas);
                    if (chart) chart.destroy();
                    
                    var labels = <?= json_encode($chart_labels) ?>;
                    var values = <?= json_encode($chart_values) ?>;
                    
                    if (labels.length > 0) {
                        var isDark = document.documentElement.getAttribute('data-theme') === 'dark';
                        var tickColor = isDark ? '#94A3B8' : '#64748B';
                        var gridColor = isDark ? 'rgba(255,255,255,0.05)' : 'rgba(0,0,0,0.05)';
                        
                        new Chart(existingCanvas, {
                            type: 'bar',
                            data: {
                                labels: labels,
                                datasets: [{
                                    label: 'Activities',
                                    data: values,
                                    backgroundColor: isDark ? 'rgba(96, 165, 250, 0.7)' : 'rgba(11, 94, 215, 0.7)',
                                    borderColor: isDark ? '#60A5FA' : '#0B5ED7',
                                    borderWidth: 1.5,
                                    borderRadius: 4,
                                    barPercentage: 0.65
                                }]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                plugins: {
                                    legend: { display: false },
                                    tooltip: {
                                        backgroundColor: isDark ? '#1E293B' : '#FFFFFF',
                                        titleColor: isDark ? '#F1F5F9' : '#1E293B',
                                        bodyColor: isDark ? '#94A3B8' : '#64748B',
                                        borderColor: isDark ? '#334155' : '#E2E8F0',
                                        borderWidth: 1
                                    }
                                },
                                scales: {
                                    y: {
                                        beginAtZero: true,
                                        ticks: { stepSize: 1, font: { size: 10 }, color: tickColor },
                                        grid: { color: gridColor, drawBorder: false }
                                    },
                                    x: {
                                        grid: { display: false },
                                        ticks: { font: { size: 9 }, maxRotation: 45, color: tickColor }
                                    }
                                }
                            }
                        });
                    }
                }
            }, 100);
        });
    });

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

    console.log('%c🕐 Braick - Employee Activities', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c✅ Uses SHARED header & sidebar', 'font-size:13px; color:#059669;');
    console.log('%c✅ NO duplicate dark mode JavaScript', 'font-size:13px; color:#059669;');
    console.log('%c🌙 Dark mode: Handled by header', 'font-size:13px; color:#7C3AED;');
    console.log('%c👤 Employee: <?= htmlspecialchars($employee['full_name']) ?>', 'font-size:13px; color:#059669;');
    console.log('%c📊 Total Activities: <?= number_format($total_activities) ?>', 'font-size:13px; color:#0B5ED7;');
    console.log('%c📄 Page: <?= $page ?> of <?= $total_pages ?>', 'font-size:13px; color:#7C3AED;');
</script>

</body>
</html>