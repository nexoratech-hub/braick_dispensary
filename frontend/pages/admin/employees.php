<?php
// ================================================================
// FILE: frontend/pages/admin/employees.php
// SUPER ADMIN - EMPLOYEES MANAGEMENT
// ✅ Uses SHARED header & sidebar (NO DUPLICATES)
// ✅ Global CSS variables (--page-*) za header
// ✅ Search bar + scroll buttons < > kwenye TABLE HEADER
// ✅ Table MOJA TU - hakuna table ndani ya table
// ✅ Full dark mode support (handled by header)
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
        case 'reception': header('Location: ../reception/dashboard.php'); break;
        case 'pharmacy': header('Location: ../pharmacy/dashboard.php'); break;
        case 'laboratory': header('Location: ../laboratory/dashboard.php'); break;
        case 'cashier': header('Location: ../cashier/dashboard.php'); break;
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
$username = $_SESSION['username'] ?? '';
$profile_pic = $_SESSION['profile_pic'] ?? '';

require_once __DIR__ . '/../../../backend/config/database.php';
require_once __DIR__ . '/../../../backend/helpers/functions.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database connection error: " . $e->getMessage());
}

// ================================================================
// BRANCH SELECTION
// ================================================================
$selected_branch_id = $_GET['branch'] ?? 'all';
$current_branch_name_display = 'All Branches';

if ($selected_branch_id !== 'all' && is_numeric($selected_branch_id)) {
    $branch_id = (int)$selected_branch_id;
    $stmt = $db->prepare("SELECT id, name FROM branches WHERE id = ?");
    $stmt->execute([$branch_id]);
    $branch_data = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($branch_data) {
        $current_branch_name_display = $branch_data['name'];
    }
} else {
    $selected_branch_id = 'all';
}

$branches = [];
$stmt = $db->query("SELECT id, name, location FROM branches WHERE status = 'active' ORDER BY name");
$branches = $stmt->fetchAll(PDO::FETCH_ASSOC);

$unread_notifications = 0;
try {
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$user_id]);
    $unread_notifications = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
} catch (Exception $e) { $unread_notifications = 0; }

$message = '';
$message_type = '';

// ================================================================
// HANDLE DEACTIVATE
// ================================================================
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $user_id_to_delete = (int)$_GET['delete'];
    $stmt = $db->prepare("SELECT id, role, full_name, branch_id FROM users WHERE id = ? AND role != 'admin'");
    $stmt->execute([$user_id_to_delete]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        $stmt = $db->prepare("UPDATE users SET status = 'inactive', updated_at = NOW() WHERE id = ?");
        if ($stmt->execute([$user_id_to_delete])) {
            $message = "✅ Employee '" . htmlspecialchars($user['full_name']) . "' has been deactivated.";
            $message_type = 'success';
            try {
                $log_stmt = $db->prepare("INSERT INTO activity_logs (user_id, branch_id, action, details, created_at) VALUES (?, ?, 'employee_deactivated', ?, NOW())");
                $log_stmt->execute([$user_id, $user['branch_id'] ?? 1, "Deactivated: " . $user['full_name'] . " by " . $user_full_name]);
            } catch (Exception $e) {}
        }
    }
}

// ================================================================
// HANDLE REACTIVATE
// ================================================================
if (isset($_GET['reactivate']) && is_numeric($_GET['reactivate'])) {
    $user_id_to_reactivate = (int)$_GET['reactivate'];
    $stmt = $db->prepare("SELECT id, role, full_name, branch_id FROM users WHERE id = ? AND role != 'admin'");
    $stmt->execute([$user_id_to_reactivate]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        $stmt = $db->prepare("UPDATE users SET status = 'active', updated_at = NOW() WHERE id = ?");
        if ($stmt->execute([$user_id_to_reactivate])) {
            $message = "✅ Employee '" . htmlspecialchars($user['full_name']) . "' has been reactivated.";
            $message_type = 'success';
            try {
                $log_stmt = $db->prepare("INSERT INTO activity_logs (user_id, branch_id, action, details, created_at) VALUES (?, ?, 'employee_reactivated', ?, NOW())");
                $log_stmt->execute([$user_id, $user['branch_id'] ?? 1, "Reactivated: " . $user['full_name'] . " by " . $user_full_name]);
            } catch (Exception $e) {}
        }
    }
}

// ================================================================
// FETCH EMPLOYEES
// ================================================================
$employees = [];
$filter = '';

if ($selected_branch_id !== 'all' && is_numeric($selected_branch_id)) {
    $filter = " AND u.branch_id = " . (int)$selected_branch_id;
}

$stmt = $db->query("
    SELECT 
        u.id, u.username, u.full_name, u.email, u.phone, u.role,
        u.branch_id, u.status, u.profile_pic, u.created_at, u.last_online,
        b.name as branch_name, b.location as branch_location
    FROM users u
    LEFT JOIN branches b ON u.branch_id = b.id
    WHERE u.role != 'admin'
    $filter
    ORDER BY u.status DESC, u.full_name ASC
");
$employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total_active = 0;
$total_inactive = 0;
$doctors = 0;
$receptionists = 0;
$pharmacists = 0;
$lab_technicians = 0;
$cashiers = 0;
$auditors = 0;

foreach ($employees as $emp) {
    if ($emp['status'] === 'active') {
        $total_active++;
        switch ($emp['role']) {
            case 'doctor': $doctors++; break;
            case 'reception': $receptionists++; break;
            case 'pharmacy': $pharmacists++; break;
            case 'laboratory': $lab_technicians++; break;
            case 'cashier': $cashiers++; break;
            case 'audit': $auditors++; break;
        }
    } else {
        $total_inactive++;
    }
}

$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

$logo_url = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

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
       PAGE HEADER - BLUE GRADIENT
       ================================================================ */
    .page-header-emp {
        background: linear-gradient(135deg, #0B5ED7 0%, #0A4CA8 100%);
        border-radius: 18px;
        padding: 24px 32px;
        margin-bottom: 24px;
        display: flex;
        flex-wrap: wrap;
        justify-content: space-between;
        align-items: center;
        gap: 16px;
        box-shadow: 0 8px 32px rgba(10, 76, 168, 0.3);
        position: relative;
        overflow: hidden;
    }

    .page-header-emp::before {
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

    .page-header-emp .page-title {
        color: white;
        font-size: 1.6rem;
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
        position: relative;
        z-index: 1;
        margin: 0;
    }

    .page-header-emp .page-subtitle {
        color: rgba(255,255,255,0.88);
        font-size: 0.9rem;
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
        position: relative;
        z-index: 1;
        margin-top: 6px;
    }

    .page-header-emp .role-badge-display {
        background: rgba(255,255,255,0.2);
        color: white;
        padding: 4px 14px;
        border-radius: 20px;
        font-size: 0.65rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    .page-header-emp .header-badge {
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
    }

    .page-header-emp .btn-outline-light {
        background: rgba(255,255,255,0.12);
        color: white;
        border: 1px solid rgba(255,255,255,0.2);
        padding: 8px 18px;
        border-radius: 12px;
        font-weight: 600;
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

    .page-header-emp .btn-outline-light:hover {
        background: rgba(255,255,255,0.25);
        transform: translateY(-2px);
        color: white;
    }

    .page-header-emp .btn-outline-light.primary {
        background: rgba(255,255,255,0.95);
        color: #0B5ED7;
        border-color: white;
        font-weight: 700;
    }

    .page-header-emp .btn-outline-light.primary:hover {
        background: white;
        color: #0A4CA8;
    }

    /* ================================================================
       STATS CARDS
       ================================================================ */
    .stats-grid-emp {
        display: grid;
        grid-template-columns: repeat(6, 1fr);
        gap: 14px;
        margin-bottom: 20px;
    }

    .stat-card-emp {
        background: var(--page-bg-card, #FFFFFF);
        border-radius: 12px;
        padding: 16px 18px;
        border: 2px solid var(--page-border, #E2E8F0);
        transition: all 0.3s ease;
        text-align: center;
        box-shadow: var(--page-shadow-sm, 0 1px 3px rgba(0,0,0,0.06));
    }

    .stat-card-emp:hover {
        transform: translateY(-3px);
        box-shadow: var(--page-shadow-md, 0 4px 12px rgba(0,0,0,0.08));
        border-color: var(--page-primary, #0B5ED7);
    }

    .stat-card-emp .stat-icon-wrap {
        width: 44px;
        height: 44px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.1rem;
        margin: 0 auto 8px;
    }

    .stat-card-emp .stat-icon-wrap.blue { background: #E8F0FE; color: #0B5ED7; }
    .stat-card-emp .stat-icon-wrap.gray { background: #F1F5F9; color: #64748B; }
    .stat-card-emp .stat-icon-wrap.green { background: #D1FAE5; color: #059669; }
    .stat-card-emp .stat-icon-wrap.dark-blue { background: #DBEAFE; color: #0A4CA8; }
    .stat-card-emp .stat-icon-wrap.purple { background: #EDE9FE; color: #7C3AED; }
    .stat-card-emp .stat-icon-wrap.orange { background: #FEF3C7; color: #D97706; }

    [data-theme="dark"] .stat-card-emp .stat-icon-wrap.blue { background: #1E3A5F; color: #6EA8FE; }
    [data-theme="dark"] .stat-card-emp .stat-icon-wrap.gray { background: #334155; color: #94A3B8; }
    [data-theme="dark"] .stat-card-emp .stat-icon-wrap.green { background: #1A3A2A; color: #34D399; }
    [data-theme="dark"] .stat-card-emp .stat-icon-wrap.dark-blue { background: #1E3A5F; color: #60A5FA; }
    [data-theme="dark"] .stat-card-emp .stat-icon-wrap.purple { background: #2D1B4E; color: #A78BFA; }
    [data-theme="dark"] .stat-card-emp .stat-icon-wrap.orange { background: #3D2E0A; color: #FBBF24; }

    .stat-card-emp .stat-number-emp {
        font-size: 1.6rem;
        font-weight: 800;
        color: var(--page-text-primary, #1E293B);
        line-height: 1.1;
    }

    .stat-card-emp .stat-number-emp.blue { color: #0B5ED7; }
    .stat-card-emp .stat-number-emp.green { color: #059669; }
    .stat-card-emp .stat-number-emp.purple { color: #7C3AED; }
    .stat-card-emp .stat-number-emp.orange { color: #D97706; }
    .stat-card-emp .stat-number-emp.gray { color: #64748B; }

    .stat-card-emp .stat-label-emp {
        font-size: 0.68rem;
        color: var(--page-text-secondary, #64748B);
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        margin-top: 6px;
    }

    /* ================================================================
       TABLE CARD
       ================================================================ */
    .table-card-emp {
        background: var(--page-bg-card, #FFFFFF);
        border-radius: 18px;
        padding: 22px 24px;
        border: 2px solid var(--page-border, #E2E8F0);
        box-shadow: var(--page-shadow-md, 0 4px 12px rgba(0,0,0,0.08));
    }

    .table-card-emp .card-header-emp {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 12px;
        margin-bottom: 16px;
        padding-bottom: 14px;
        border-bottom: 2px solid var(--page-border, #E2E8F0);
    }

    .table-card-emp .card-title-emp {
        font-size: 1rem;
        font-weight: 700;
        color: var(--page-text-primary, #1E293B);
        display: flex;
        align-items: center;
        gap: 10px;
        margin: 0;
    }

    .table-card-emp .card-title-emp i {
        color: var(--page-primary, #0B5ED7);
    }

    .count-badge-emp {
        background: var(--page-primary-bg, #E8F0FE);
        color: var(--page-primary, #0B5ED7);
        padding: 3px 12px;
        border-radius: 20px;
        font-size: 0.72rem;
        font-weight: 600;
    }

    /* ================================================================
       ✅ FILTER BAR - SEARCH + SCROLL BUTTONS (KAMA PATIENTS.PHP)
       ================================================================ */
    .filter-bar-emp {
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
        width: 100%;
        justify-content: space-between;
        padding: 12px 16px;
        background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
        border-radius: 12px;
        box-shadow: 0 4px 14px rgba(11, 94, 215, 0.25);
        margin-bottom: 16px;
    }

    .filter-bar-left-emp {
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
        flex: 1;
    }

    .filter-bar-right-emp {
        display: flex;
        align-items: center;
        gap: 6px;
        flex-shrink: 0;
    }

    .filter-input-emp {
        padding: 8px 14px;
        border: 1.5px solid rgba(255,255,255,0.3);
        border-radius: 9px;
        font-size: 0.78rem;
        background: rgba(255,255,255,0.95);
        color: #1E293B;
        outline: none;
        font-weight: 500;
        height: 38px;
        font-family: inherit;
    }

    .filter-input-emp:focus {
        border-color: white;
        box-shadow: 0 0 0 3px rgba(255,255,255,0.25);
    }

    select.filter-input-emp {
        min-width: 140px;
        cursor: pointer;
    }

    .search-input-wrapper-emp {
        position: relative;
        display: flex;
        align-items: center;
        flex: 1;
        max-width: 380px;
        min-width: 200px;
    }

    .search-input-wrapper-emp i {
        position: absolute;
        left: 12px;
        color: var(--page-primary, #0B5ED7);
        font-size: 0.8rem;
        pointer-events: none;
        z-index: 1;
    }

    .search-input-wrapper-emp input {
        padding-left: 34px;
        padding-right: 34px;
        width: 100%;
        font-size: 0.8rem;
    }

    .search-input-wrapper-emp .clear-search-emp {
        position: absolute;
        right: 8px;
        top: 50%;
        transform: translateY(-50%);
        background: transparent;
        border: none;
        color: #64748B;
        cursor: pointer;
        padding: 4px 8px;
        font-size: 0.75rem;
        display: none;
        border-radius: 6px;
        transition: all 0.2s;
        z-index: 1;
    }

    .search-input-wrapper-emp .clear-search-emp:hover {
        background: rgba(11, 94, 215, 0.1);
        color: #0B5ED7;
    }

    .search-input-wrapper-emp .clear-search-emp.visible {
        display: block;
    }

    .filter-status-emp {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        font-size: 0.7rem;
        color: white;
        padding: 6px 12px;
        background: rgba(255,255,255,0.15);
        border-radius: 20px;
        border: 1px solid rgba(255,255,255,0.2);
        height: 38px;
        font-weight: 600;
        white-space: nowrap;
    }

    /* ✅ SCROLL BUTTONS < > */
    .scroll-controls-emp {
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .scroll-btn-emp {
        width: 38px;
        height: 38px;
        border-radius: 9px;
        border: 1.5px solid rgba(255,255,255,0.3);
        background: rgba(255,255,255,0.95);
        color: #0B5ED7;
        font-size: 0.85rem;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        transition: all 0.3s ease;
    }

    .scroll-btn-emp:hover:not(:disabled) {
        background: white;
        transform: scale(1.08);
        box-shadow: 0 3px 10px rgba(0,0,0,0.15);
    }

    .scroll-btn-emp:disabled {
        opacity: 0.4;
        cursor: not-allowed;
        transform: none !important;
    }

    /* ================================================================
       TABLE
       ================================================================ */
    .table-scroll-wrapper-emp {
        overflow-x: auto;
        overflow-y: auto;
        max-height: 620px;
        scroll-behavior: smooth;
        border-radius: 12px;
        border: 1px solid var(--page-border, #E2E8F0);
    }

    .table-scroll-wrapper-emp::-webkit-scrollbar {
        height: 8px;
        width: 8px;
    }

    .table-scroll-wrapper-emp::-webkit-scrollbar-track {
        background: var(--page-hover, #F8FAFC);
        border-radius: 10px;
    }

    .table-scroll-wrapper-emp::-webkit-scrollbar-thumb {
        background: var(--page-primary, #0B5ED7);
        border-radius: 10px;
    }

    .employee-table-emp {
        width: 100%;
        min-width: 1200px;
        border-collapse: collapse;
        font-size: 0.82rem;
    }

    .employee-table-emp thead {
        background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
        color: white;
        position: sticky;
        top: 0;
        z-index: 5;
    }

    .employee-table-emp thead th {
        padding: 14px 16px;
        text-align: left;
        font-weight: 700;
        font-size: 0.68rem;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        color: white;
        white-space: nowrap;
    }

    .employee-table-emp tbody td {
        padding: 12px 16px;
        border-bottom: 1px solid var(--page-border, #E2E8F0);
        color: var(--page-text-primary, #1E293B);
        vertical-align: middle;
    }

    .employee-table-emp tbody tr:nth-child(even) {
        background: #E8F0FE;
    }

    [data-theme="dark"] .employee-table-emp tbody tr:nth-child(even) {
        background: #1E3A5F;
    }

    .employee-table-emp tbody tr:hover td {
        background: #D1FAE5;
    }

    [data-theme="dark"] .employee-table-emp tbody tr:hover td {
        background: #1A3A2A;
    }

    .employee-table-emp .col-sno-emp {
        width: 50px;
        text-align: center;
        font-weight: 700;
        color: var(--page-primary, #0B5ED7);
        font-size: 0.9rem;
    }

    .inactive-row-emp {
        opacity: 0.65;
    }

    /* Employee Info */
    .emp-info-cell-emp {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .emp-avatar-emp {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.85rem;
        font-weight: 700;
        color: white;
        flex-shrink: 0;
        background: linear-gradient(135deg, #0B5ED7, #1A73E8);
    }

    .emp-avatar-emp img {
        width: 100%;
        height: 100%;
        border-radius: 50%;
        object-fit: cover;
    }

    .emp-name-emp {
        font-weight: 600;
        color: var(--page-text-primary, #1E293B);
        font-size: 0.85rem;
        margin: 0;
    }

    .emp-username-emp {
        font-size: 0.68rem;
        color: var(--page-text-secondary, #64748B);
        margin: 2px 0 0 0;
    }

    /* Badges */
    .role-badge-emp {
        display: inline-block;
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 0.62rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.03em;
    }

    .role-badge-emp.role-doctor { background: #E8F0FE; color: #0B5ED7; }
    .role-badge-emp.role-reception { background: #D1FAE5; color: #059669; }
    .role-badge-emp.role-pharmacy { background: #FEF3C7; color: #D97706; }
    .role-badge-emp.role-laboratory { background: #EDE9FE; color: #7C3AED; }
    .role-badge-emp.role-cashier { background: #FCE7F3; color: #DB2777; }
    .role-badge-emp.role-audit { background: #FEE2E2; color: #DC2626; }

    [data-theme="dark"] .role-badge-emp.role-doctor { background: #1E3A5F; color: #6EA8FE; }
    [data-theme="dark"] .role-badge-emp.role-reception { background: #1A3A2A; color: #34D399; }
    [data-theme="dark"] .role-badge-emp.role-pharmacy { background: #3D2E0A; color: #FBBF24; }
    [data-theme="dark"] .role-badge-emp.role-laboratory { background: #2D1B4E; color: #A78BFA; }
    [data-theme="dark"] .role-badge-emp.role-cashier { background: #4A1D3D; color: #F472B6; }
    [data-theme="dark"] .role-badge-emp.role-audit { background: #3A1A1A; color: #F87171; }

    .branch-badge-emp {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 4px 12px;
        border-radius: 14px;
        font-size: 0.68rem;
        font-weight: 600;
        background: linear-gradient(135deg, #E8F0FE, #D4E4FF);
        color: #0B5ED7;
        border: 1.5px solid #BFDBFE;
        white-space: nowrap;
    }

    [data-theme="dark"] .branch-badge-emp {
        background: linear-gradient(135deg, #1E3A5F, #2A4A6F);
        color: #6EA8FE;
        border-color: #6EA8FE;
    }

    .status-badge-emp {
        display: inline-block;
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 0.62rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.03em;
    }

    .status-badge-emp.active { background: #D1FAE5; color: #059669; }
    .status-badge-emp.inactive { background: #FEE2E2; color: #DC2626; }

    [data-theme="dark"] .status-badge-emp.active { background: #1A3A2A; color: #34D399; }
    [data-theme="dark"] .status-badge-emp.inactive { background: #3A1A1A; color: #F87171; }

    /* ================================================================
       ACTION BUTTONS
       ================================================================ */
    .action-buttons-emp {
        display: flex;
        gap: 5px;
        align-items: center;
        justify-content: center;
        flex-wrap: nowrap;
    }

    .action-btn-emp {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 5px;
        padding: 6px 12px;
        border-radius: 7px;
        font-size: 0.7rem;
        font-weight: 600;
        transition: all 0.3s;
        cursor: pointer;
        border: none;
        text-decoration: none;
        white-space: nowrap;
        font-family: inherit;
    }

    .action-btn-emp i {
        font-size: 0.72rem;
    }

    .action-btn-emp:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 10px rgba(0,0,0,0.15);
    }

    .action-btn-emp.btn-view-emp { background: #0B5ED7; color: white; }
    .action-btn-emp.btn-view-emp:hover { background: #0A4CA8; color: white; }

    .action-btn-emp.btn-edit-emp { background: #F59E0B; color: white; }
    .action-btn-emp.btn-edit-emp:hover { background: #D97706; color: white; }

    .action-btn-emp.btn-delete-emp { background: #EF4444; color: white; }
    .action-btn-emp.btn-delete-emp:hover { background: #DC2626; color: white; }

    .action-btn-emp.btn-reactivate-emp { background: #059669; color: white; }
    .action-btn-emp.btn-reactivate-emp:hover { background: #047857; color: white; }

    /* ================================================================
       BUTTONS
       ================================================================ */
    .btn-emp {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 8px 16px;
        border-radius: 8px;
        font-weight: 600;
        font-size: 0.78rem;
        transition: all 0.3s;
        cursor: pointer;
        border: none;
        text-decoration: none;
        font-family: inherit;
    }

    .btn-emp.btn-outline-emp {
        background: transparent;
        color: var(--page-text-secondary, #64748B);
        border: 2px solid var(--page-border, #E2E8F0);
    }

    .btn-emp.btn-outline-emp:hover {
        background: var(--page-hover, #F8FAFC);
        border-color: var(--page-primary, #0B5ED7);
        color: var(--page-primary, #0B5ED7);
    }

    .btn-emp.btn-primary-emp {
        background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
        color: white;
    }

    .btn-emp.btn-primary-emp:hover {
        background: linear-gradient(135deg, #0A4CA8, #083C8A);
        color: white;
        transform: translateY(-2px);
    }

    .btn-emp.btn-danger-emp {
        background: #DC2626;
        color: white;
    }

    .btn-emp.btn-danger-emp:hover {
        background: #B91C1C;
        color: white;
    }

    .btn-emp.btn-success-emp {
        background: #059669;
        color: white;
    }

    .btn-emp.btn-success-emp:hover {
        background: #047857;
        color: white;
    }

    /* ================================================================
       MESSAGE BOX
       ================================================================ */
    .message-box-emp {
        padding: 14px 20px;
        border-radius: 12px;
        margin-bottom: 18px;
        display: flex;
        align-items: center;
        gap: 12px;
        font-weight: 500;
        animation: slideDownEmp 0.4s ease;
    }

    @keyframes slideDownEmp {
        from { opacity: 0; transform: translateY(-10px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .message-box-emp.success {
        background: #D1FAE5;
        color: #065F46;
        border: 2px solid #6EE7B7;
    }

    .message-box-emp.error {
        background: #FEE2E2;
        color: #991B1B;
        border: 2px solid #FCA5A5;
    }

    [data-theme="dark"] .message-box-emp.success {
        background: #1A3A2A;
        color: #34D399;
        border-color: #34D399;
    }

    [data-theme="dark"] .message-box-emp.error {
        background: #3A1A1A;
        color: #F87171;
        border-color: #F87171;
    }

    /* ================================================================
       MODAL
       ================================================================ */
    .modal-emp {
        position: fixed;
        top: 0; left: 0; right: 0; bottom: 0;
        z-index: 99999;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 20px;
    }

    .modal-overlay-emp {
        position: absolute;
        top: 0; left: 0; right: 0; bottom: 0;
        background: rgba(0,0,0,0.6);
        backdrop-filter: blur(4px);
        cursor: pointer;
    }

    .modal-content-emp {
        background: var(--page-bg-card, #FFFFFF);
        border-radius: 18px;
        max-width: 500px;
        width: 100%;
        position: relative;
        z-index: 100000;
        box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        border: 2px solid var(--page-border, #E2E8F0);
        overflow: hidden;
    }

    .modal-header-emp {
        padding: 18px 22px;
        border-bottom: 2px solid var(--page-border, #E2E8F0);
        display: flex;
        justify-content: space-between;
        align-items: center;
        background: var(--page-hover, #F8FAFC);
    }

    [data-theme="dark"] .modal-header-emp {
        background: #0F172A;
    }

    .modal-header-emp h3 {
        font-size: 1.05rem;
        font-weight: 700;
        color: var(--page-text-primary, #1E293B);
        margin: 0;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .modal-close-emp {
        background: transparent;
        border: none;
        font-size: 1.6rem;
        cursor: pointer;
        color: var(--page-text-secondary, #64748B);
        padding: 0 4px;
        line-height: 1;
        transition: color 0.3s;
    }

    .modal-close-emp:hover {
        color: var(--page-danger, #DC2626);
    }

    .modal-body-emp {
        padding: 22px;
        color: var(--page-text-primary, #1E293B);
    }

    .modal-footer-emp {
        padding: 16px 22px;
        border-top: 2px solid var(--page-border, #E2E8F0);
        display: flex;
        justify-content: flex-end;
        gap: 10px;
    }

    /* ================================================================
       FOOTER
       ================================================================ */
    .footer-emp {
        padding: 14px 0;
        border-top: 2px solid var(--page-border, #E2E8F0);
        margin-top: 24px;
        text-align: center;
        font-size: 0.72rem;
        color: var(--page-text-secondary, #64748B);
    }

    .footer-emp .footer-brand-emp {
        color: var(--page-primary, #0B5ED7);
        font-weight: 700;
    }

    /* ================================================================
       EMPTY STATE
       ================================================================ */
    .empty-state-emp {
        text-align: center;
        padding: 40px 20px;
        color: var(--page-text-secondary, #64748B);
    }

    .empty-state-emp i {
        font-size: 2.5rem;
        color: var(--page-border, #E2E8F0);
        margin-bottom: 12px;
        display: block;
    }

    /* ================================================================
       ANIMATIONS
       ================================================================ */
    @keyframes fadeInUpEmp {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .animate-fade-in-up-emp {
        animation: fadeInUpEmp 0.4s ease forwards;
        opacity: 0;
    }

    /* ================================================================
       RESPONSIVE
       ================================================================ */
    @media (max-width: 1200px) {
        .stats-grid-emp { grid-template-columns: repeat(3, 1fr); }
    }

    @media (max-width: 992px) {
        .filter-bar-emp { flex-direction: column; align-items: stretch; }
        .filter-bar-left-emp { width: 100%; }
        .filter-bar-right-emp { width: 100%; justify-content: flex-end; }
        .search-input-wrapper-emp { max-width: 100%; }
    }

    @media (max-width: 768px) {
        .page-header-emp { padding: 18px 20px; flex-direction: column; align-items: flex-start; }
        .page-header-emp .page-title { font-size: 1.3rem; }
        .stats-grid-emp { grid-template-columns: repeat(2, 1fr); }
        .filter-bar-emp { padding: 10px 12px; }
        .employee-table-emp { font-size: 0.72rem; }
        .employee-table-emp thead th,
        .employee-table-emp tbody td { padding: 8px 10px; }
    }

    @media (max-width: 480px) {
        .stats-grid-emp { grid-template-columns: 1fr 1fr; }
        .filter-bar-left-emp { flex-direction: column; align-items: stretch; }
        .filter-status-emp { display: none; }
        .search-input-wrapper-emp { min-width: 100%; }
    }
</style>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <!-- ================================================================ -->
    <!-- PAGE HEADER -->
    <!-- ================================================================ -->
    <div class="page-header-emp animate-fade-in-up-emp">
        <div>
            <h1 class="page-title">
                <i class="fas fa-users-cog"></i>
                Employees Management
                <span class="role-badge-display">ADMIN</span>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-store-alt"></i>
                <span class="header-badge">
                    <i class="fas fa-store-alt"></i>
                    <?= htmlspecialchars($current_branch_name_display) ?>
                </span>
                <span class="header-badge" style="background:rgba(52,211,153,0.2);border-color:rgba(52,211,153,0.3);color:#34D399;">
                    <i class="fas fa-user-check"></i> <?= number_format($total_active) ?> Active
                </span>
                <span class="header-badge" style="background:rgba(255,255,255,0.15);">
                    <i class="fas fa-users"></i> <?= number_format(count($employees)) ?> Total
                </span>
            </p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;position:relative;z-index:1;">
            <a href="add_employee.php?branch=<?= $selected_branch_id ?>" class="btn-outline-light primary">
                <i class="fas fa-user-plus"></i> Add Employee
            </a>
            <button onclick="location.reload()" class="btn-outline-light">
                <i class="fas fa-sync-alt"></i> Refresh
            </button>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- MESSAGE -->
    <!-- ================================================================ -->
    <?php if ($message): ?>
        <div class="message-box-emp <?= $message_type === 'success' ? 'success' : 'error' ?>">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>" style="font-size:1.2rem;"></i>
            <div><?= $message ?></div>
        </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- STATS CARDS -->
    <!-- ================================================================ -->
    <div class="stats-grid-emp animate-fade-in-up-emp" style="animation-delay:0.05s;">
        <div class="stat-card-emp">
            <div class="stat-icon-wrap blue"><i class="fas fa-user-check"></i></div>
            <div class="stat-number-emp blue"><?= number_format($total_active) ?></div>
            <div class="stat-label-emp">Total Active</div>
        </div>
        <div class="stat-card-emp">
            <div class="stat-icon-wrap gray"><i class="fas fa-user-slash"></i></div>
            <div class="stat-number-emp gray"><?= number_format($total_inactive) ?></div>
            <div class="stat-label-emp">Inactive</div>
        </div>
        <div class="stat-card-emp">
            <div class="stat-icon-wrap green"><i class="fas fa-user-md"></i></div>
            <div class="stat-number-emp green"><?= number_format($doctors) ?></div>
            <div class="stat-label-emp">Doctors</div>
        </div>
        <div class="stat-card-emp">
            <div class="stat-icon-wrap dark-blue"><i class="fas fa-headset"></i></div>
            <div class="stat-number-emp blue"><?= number_format($receptionists) ?></div>
            <div class="stat-label-emp">Reception</div>
        </div>
        <div class="stat-card-emp">
            <div class="stat-icon-wrap purple"><i class="fas fa-pills"></i></div>
            <div class="stat-number-emp purple"><?= number_format($pharmacists) ?></div>
            <div class="stat-label-emp">Pharmacy</div>
        </div>
        <div class="stat-card-emp">
            <div class="stat-icon-wrap orange"><i class="fas fa-flask"></i></div>
            <div class="stat-number-emp orange"><?= number_format($lab_technicians + $cashiers + $auditors) ?></div>
            <div class="stat-label-emp">Lab/Cash/Audit</div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- TABLE CARD - SEARCH BAR + SCROLL BUTTONS KWENYE TABLE HEADER -->
    <!-- ================================================================ -->
    <div class="table-card-emp animate-fade-in-up-emp" style="animation-delay:0.1s;">
        
        <!-- Card Header -->
        <div class="card-header-emp">
            <h3 class="card-title-emp">
                <i class="fas fa-list"></i>
                Employee List
                <span class="count-badge-emp" id="employeeCountBadge"><?= number_format(count($employees)) ?> employees</span>
            </h3>
        </div>
        
        <!-- ✅ FILTER BAR - SEARCH + SCROLL BUTTONS (KAMA PATIENTS.PHP) -->
        <div class="filter-bar-emp">
            <div class="filter-bar-left-emp">
                
                <div class="search-input-wrapper-emp">
                    <i class="fas fa-search"></i>
                    <input type="text" 
                           id="tableSearch" 
                           class="filter-input-emp" 
                           placeholder="🔍 Search employees by name, email, phone, role..."
                           autocomplete="off">
                    <button type="button" class="clear-search-emp" id="clearSearchBtn" title="Clear">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                
                <select id="roleFilter" class="filter-input-emp" onchange="autoFilter()">
                    <option value="all">👥 All Roles</option>
                    <option value="doctor">🩺 Doctors</option>
                    <option value="reception">🎧 Reception</option>
                    <option value="pharmacy">💊 Pharmacy</option>
                    <option value="laboratory">🔬 Lab</option>
                    <option value="cashier">💰 Cashier</option>
                    <option value="audit">📋 Audit</option>
                </select>
                
                <select id="statusFilter" class="filter-input-emp" onchange="autoFilter()">
                    <option value="all">🟢 All Status</option>
                    <option value="active">✅ Active</option>
                    <option value="inactive">⛔ Inactive</option>
                </select>
                
                <span class="filter-status-emp" id="filterStatus">
                    <i class="fas fa-bolt"></i>
                    <span id="filterStatusText">Auto-filter</span>
                </span>
            </div>
            
            <!-- ✅ SCROLL BUTTONS < > -->
            <div class="filter-bar-right-emp">
                <div class="scroll-controls-emp">
                    <button type="button" 
                            class="scroll-btn-emp" 
                            id="scrollLeftBtn" 
                            onclick="scrollTable('left')" 
                            title="Scroll Left"
                            disabled>
                        <i class="fas fa-chevron-left"></i>
                    </button>
                    <button type="button" 
                            class="scroll-btn-emp" 
                            id="scrollRightBtn" 
                            onclick="scrollTable('right')" 
                            title="Scroll Right">
                        <i class="fas fa-chevron-right"></i>
                    </button>
                </div>
            </div>
        </div>
        
        <!-- ✅ TABLE MOJA TU -->
        <div class="table-scroll-wrapper-emp" id="tableScrollWrapper">
            <table class="employee-table-emp" id="employeesTable">
                <thead>
                    <tr>
                        <th class="col-sno-emp">#</th>
                        <th style="min-width: 220px;"><i class="fas fa-user"></i> Employee</th>
                        <th style="min-width: 120px;"><i class="fas fa-user-tag"></i> Role</th>
                        <th style="min-width: 200px;"><i class="fas fa-envelope"></i> Email</th>
                        <th style="min-width: 140px;"><i class="fas fa-phone"></i> Phone</th>
                        <th style="min-width: 140px;"><i class="fas fa-store-alt"></i> Branch</th>
                        <th style="min-width: 110px;"><i class="fas fa-circle"></i> Status</th>
                        <th style="min-width: 180px;text-align:center;"><i class="fas fa-cog"></i> Actions</th>
                    </tr>
                </thead>
                <tbody id="tableBody">
                    <?php if (count($employees) > 0): ?>
                        <?php $i = 1; foreach ($employees as $emp): 
                            $initials = strtoupper(substr($emp['full_name'], 0, 1));
                        ?>
                            <tr class="emp-row-emp <?= $emp['status'] === 'inactive' ? 'inactive-row-emp' : '' ?>" 
                                data-search="<?= strtolower(htmlspecialchars($emp['full_name'] . ' ' . $emp['username'] . ' ' . $emp['email'] . ' ' . ($emp['phone'] ?? '') . ' ' . ($emp['branch_name'] ?? '') . ' ' . $emp['role'])) ?>"
                                data-role="<?= htmlspecialchars($emp['role']) ?>"
                                data-status="<?= htmlspecialchars($emp['status']) ?>">
                                <td class="col-sno-emp"><?= $i++ ?></td>
                                <td>
                                    <div class="emp-info-cell-emp">
                                        <?php if (!empty($emp['profile_pic'])): ?>
                                            <div class="emp-avatar-emp">
                                                <img src="/dispensary_system/frontend/assets/uploads/profiles/<?= $emp['profile_pic'] ?>" 
                                                     alt="<?= htmlspecialchars($emp['full_name']) ?>"
                                                     onerror="this.parentElement.innerHTML='<?= $initials ?>'">
                                            </div>
                                        <?php else: ?>
                                            <div class="emp-avatar-emp"><?= $initials ?></div>
                                        <?php endif; ?>
                                        <div>
                                            <p class="emp-name-emp"><?= htmlspecialchars($emp['full_name']) ?></p>
                                            <p class="emp-username-emp">@<?= htmlspecialchars($emp['username']) ?></p>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="role-badge-emp role-<?= $emp['role'] ?>">
                                        <?php
                                            $role_labels = [
                                                'doctor' => '🩺 Doctor',
                                                'reception' => '🎧 Reception',
                                                'pharmacy' => '💊 Pharmacy',
                                                'laboratory' => '🔬 Lab Tech',
                                                'cashier' => '💰 Cashier',
                                                'audit' => '📋 Audit'
                                            ];
                                            echo $role_labels[$emp['role']] ?? ucfirst($emp['role']);
                                        ?>
                                    </span>
                                </td>
                                <td style="font-size:0.78rem;"><?= htmlspecialchars($emp['email']) ?></td>
                                <td style="font-size:0.78rem;"><?= htmlspecialchars($emp['phone'] ?? 'N/A') ?></td>
                                <td>
                                    <span class="branch-badge-emp">
                                        <i class="fas fa-store-alt"></i>
                                        <?= htmlspecialchars($emp['branch_name'] ?? 'N/A') ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="status-badge-emp <?= $emp['status'] === 'active' ? 'active' : 'inactive' ?>">
                                        <?= $emp['status'] === 'active' ? '✅ Active' : '⛔ Inactive' ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-buttons-emp">
                                        <a href="view_employee.php?id=<?= $emp['id'] ?>&branch=<?= $selected_branch_id ?>" 
                                           class="action-btn-emp btn-view-emp" title="View Employee">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                        <a href="edit_employee.php?id=<?= $emp['id'] ?>&branch=<?= $selected_branch_id ?>" 
                                           class="action-btn-emp btn-edit-emp" title="Edit Employee">
                                            <i class="fas fa-edit"></i> Edit
                                        </a>
                                        <?php if ($emp['status'] === 'active'): ?>
                                            <button onclick="confirmDelete(<?= $emp['id'] ?>, '<?= htmlspecialchars(addslashes($emp['full_name'])) ?>')" 
                                                    class="action-btn-emp btn-delete-emp" title="Deactivate">
                                                <i class="fas fa-user-slash"></i>
                                            </button>
                                        <?php else: ?>
                                            <button onclick="confirmReactivate(<?= $emp['id'] ?>, '<?= htmlspecialchars(addslashes($emp['full_name'])) ?>')" 
                                                    class="action-btn-emp btn-reactivate-emp" title="Reactivate">
                                                <i class="fas fa-undo"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <tr class="no-results-row-emp" id="noResults" style="display:none;">
                            <td colspan="8">
                                <div class="empty-state-emp">
                                    <i class="fas fa-search-minus"></i>
                                    <p style="font-size:0.9rem;font-weight:600;margin:0;">No employees match your search</p>
                                    <p style="font-size:0.8rem;margin:4px 0 0 0;">Try adjusting your filters</p>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <tr>
                            <td colspan="8">
                                <div class="empty-state-emp">
                                    <i class="fas fa-users"></i>
                                    <p style="font-size:0.9rem;font-weight:600;margin:0;">No employees found</p>
                                    <p style="font-size:0.8rem;margin:4px 0 0 0;">in <?= htmlspecialchars($current_branch_name_display) ?></p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer-emp">
        <p>
            <span class="footer-brand-emp">Braick Dispensary</span> Management System
            <span style="color:#CBD5E1;margin:0 8px;">|</span>
            Employees Management
            <span style="color:#CBD5E1;margin:0 8px;">|</span>
            <span id="footerTime"><?= date('H:i:s') ?></span>
            <span style="color:#CBD5E1;margin:0 8px;">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<!-- ================================================================ -->
<!-- DELETE MODAL -->
<!-- ================================================================ -->
<div id="deleteModal" class="modal-emp" style="display:none;">
    <div class="modal-overlay-emp" onclick="closeModal()"></div>
    <div class="modal-content-emp">
        <div class="modal-header-emp">
            <h3><i class="fas fa-exclamation-triangle" style="color:#DC2626;"></i> Deactivate Employee</h3>
            <button onclick="closeModal()" class="modal-close-emp">&times;</button>
        </div>
        <div class="modal-body-emp">
            <p style="font-size:0.9rem;margin:0 0 8px 0;">Are you sure you want to deactivate <strong id="deleteName" style="color:#DC2626;"></strong>?</p>
            <p style="font-size:0.78rem;color:var(--page-text-secondary);margin:0;">
                <i class="fas fa-info-circle"></i> 
                This employee will no longer be able to login until reactivated.
            </p>
        </div>
        <div class="modal-footer-emp">
            <button onclick="closeModal()" class="btn-emp btn-outline-emp">Cancel</button>
            <a href="#" id="deleteLink" class="btn-emp btn-danger-emp">
                <i class="fas fa-user-slash"></i> Deactivate
            </a>
        </div>
    </div>
</div>

<!-- ================================================================ -->
<!-- REACTIVATE MODAL -->
<!-- ================================================================ -->
<div id="reactivateModal" class="modal-emp" style="display:none;">
    <div class="modal-overlay-emp" onclick="closeReactivateModal()"></div>
    <div class="modal-content-emp">
        <div class="modal-header-emp">
            <h3><i class="fas fa-undo" style="color:#059669;"></i> Reactivate Employee</h3>
            <button onclick="closeReactivateModal()" class="modal-close-emp">&times;</button>
        </div>
        <div class="modal-body-emp">
            <p style="font-size:0.9rem;margin:0;">Are you sure you want to reactivate <strong id="reactivateName" style="color:#059669;"></strong>?</p>
        </div>
        <div class="modal-footer-emp">
            <button onclick="closeReactivateModal()" class="btn-emp btn-outline-emp">Cancel</button>
            <a href="#" id="reactivateLink" class="btn-emp btn-success-emp">
                <i class="fas fa-undo"></i> Reactivate
            </a>
        </div>
    </div>
</div>

<!-- ================================================================ -->
<!-- PAGE-SPECIFIC JAVASCRIPT (NO dark mode, NO sidebar, NO date-time) -->
<!-- ================================================================ -->
<script>
// ================================================================
// ✅ TABLE SCROLL FUNCTIONS
// ================================================================
function scrollTable(direction) {
    var wrapper = document.getElementById('tableScrollWrapper');
    if (!wrapper) return;
    
    var scrollAmount = 400;
    
    if (direction === 'left') {
        wrapper.scrollBy({ left: -scrollAmount, behavior: 'smooth' });
    } else {
        wrapper.scrollBy({ left: scrollAmount, behavior: 'smooth' });
    }
    
    setTimeout(updateScrollButtons, 350);
}

function updateScrollButtons() {
    var wrapper = document.getElementById('tableScrollWrapper');
    var leftBtn = document.getElementById('scrollLeftBtn');
    var rightBtn = document.getElementById('scrollRightBtn');
    
    if (!wrapper || !leftBtn || !rightBtn) return;
    
    var scrollLeft = wrapper.scrollLeft;
    var scrollWidth = wrapper.scrollWidth;
    var clientWidth = wrapper.clientWidth;
    var maxScroll = scrollWidth - clientWidth;
    
    if (maxScroll <= 5) {
        leftBtn.disabled = true;
        rightBtn.disabled = true;
        return;
    }
    
    leftBtn.disabled = (scrollLeft <= 5);
    rightBtn.disabled = (scrollLeft >= maxScroll - 5);
}

(function() {
    var wrapper = document.getElementById('tableScrollWrapper');
    if (!wrapper) return;
    
    wrapper.addEventListener('scroll', updateScrollButtons);
    window.addEventListener('resize', function() {
        setTimeout(updateScrollButtons, 100);
    });
    
    setTimeout(updateScrollButtons, 200);
    setTimeout(updateScrollButtons, 600);
    
    // Drag to scroll
    var isDown = false;
    var startX = 0;
    var scrollLeftStart = 0;
    
    wrapper.addEventListener('mousedown', function(e) {
        if (e.target.closest('a, button, input, select')) return;
        isDown = true;
        wrapper.style.cursor = 'grabbing';
        startX = e.pageX - wrapper.offsetLeft;
        scrollLeftStart = wrapper.scrollLeft;
    });
    
    wrapper.addEventListener('mouseleave', function() {
        isDown = false;
        wrapper.style.cursor = '';
    });
    
    wrapper.addEventListener('mouseup', function() {
        isDown = false;
        wrapper.style.cursor = '';
    });
    
    wrapper.addEventListener('mousemove', function(e) {
        if (!isDown) return;
        e.preventDefault();
        var x = e.pageX - wrapper.offsetLeft;
        var walk = (x - startX) * 1.5;
        wrapper.scrollLeft = scrollLeftStart - walk;
    });
})();

// ================================================================
// ✅ AUTO SEARCH + FILTER
// ================================================================
var filterTimeout = null;

function autoFilter() {
    if (filterTimeout) clearTimeout(filterTimeout);
    
    filterTimeout = setTimeout(function() {
        filterTableRows();
    }, 200);
}

function filterTableRows() {
    var searchInput = document.getElementById('tableSearch');
    var roleFilter = document.getElementById('roleFilter');
    var statusFilter = document.getElementById('statusFilter');
    var clearBtn = document.getElementById('clearSearchBtn');
    var noResultsRow = document.getElementById('noResults');
    var countBadge = document.getElementById('employeeCountBadge');
    var statusText = document.getElementById('filterStatusText');
    
    if (!searchInput) return;
    
    var query = searchInput.value.toLowerCase().trim();
    var role = roleFilter ? roleFilter.value : 'all';
    var status = statusFilter ? statusFilter.value : 'all';
    
    var rows = document.querySelectorAll('.emp-row-emp');
    var totalRows = rows.length;
    var visibleCount = 0;
    
    rows.forEach(function(row) {
        var searchData = row.getAttribute('data-search') || '';
        var rowRole = row.getAttribute('data-role') || '';
        var rowStatus = row.getAttribute('data-status') || '';
        
        var matches = true;
        
        // Search
        if (query !== '' && !searchData.includes(query)) {
            matches = false;
        }
        
        // Role filter
        if (role !== 'all' && rowRole !== role) {
            matches = false;
        }
        
        // Status filter
        if (status !== 'all' && rowStatus !== status) {
            matches = false;
        }
        
        if (matches) {
            row.style.display = '';
            visibleCount++;
        } else {
            row.style.display = 'none';
        }
    });
    
    // Update row numbers
    updateRowNumbers();
    
    // Update count badge
    if (countBadge) {
        if (query === '' && role === 'all' && status === 'all') {
            countBadge.textContent = totalRows + ' employees';
        } else {
            countBadge.textContent = visibleCount + ' of ' + totalRows + ' found';
        }
    }
    
    // Update clear button
    if (clearBtn) {
        if (query !== '' || role !== 'all' || status !== 'all') {
            clearBtn.classList.add('visible');
        } else {
            clearBtn.classList.remove('visible');
        }
    }
    
    // Update status text
    if (statusText) {
        if (query !== '' || role !== 'all' || status !== 'all') {
            statusText.textContent = 'Filtered: ' + visibleCount;
        } else {
            statusText.textContent = 'Auto-filter';
        }
    }
    
    // Show/hide no results
    if (noResultsRow) {
        if (visibleCount === 0 && totalRows > 0) {
            noResultsRow.style.display = '';
        } else {
            noResultsRow.style.display = 'none';
        }
    }
    
    setTimeout(updateScrollButtons, 100);
}

function updateRowNumbers() {
    var rows = document.querySelectorAll('.emp-row-emp');
    var counter = 1;
    rows.forEach(function(row) {
        if (row.style.display !== 'none') {
            var snoCell = row.querySelector('.col-sno-emp');
            if (snoCell) snoCell.textContent = counter;
            counter++;
        }
    });
}

// Search input event
document.getElementById('tableSearch')?.addEventListener('input', autoFilter);

// Clear search
document.getElementById('clearSearchBtn')?.addEventListener('click', function() {
    var searchInput = document.getElementById('tableSearch');
    var roleFilter = document.getElementById('roleFilter');
    var statusFilter = document.getElementById('statusFilter');
    
    if (searchInput) searchInput.value = '';
    if (roleFilter) roleFilter.value = 'all';
    if (statusFilter) statusFilter.value = 'all';
    
    filterTableRows();
    if (searchInput) searchInput.focus();
});

// ESC key to clear search
document.getElementById('tableSearch')?.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        this.value = '';
        autoFilter();
        this.blur();
    }
});

// ================================================================
// ✅ DELETE MODAL
// ================================================================
function confirmDelete(id, name) {
    document.getElementById('deleteName').textContent = name;
    document.getElementById('deleteLink').href = 'employees.php?delete=' + id + '&branch=<?= $selected_branch_id ?>';
    document.getElementById('deleteModal').style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function closeModal() {
    document.getElementById('deleteModal').style.display = 'none';
    document.body.style.overflow = '';
}

// ================================================================
// ✅ REACTIVATE MODAL
// ================================================================
function confirmReactivate(id, name) {
    document.getElementById('reactivateName').textContent = name;
    document.getElementById('reactivateLink').href = 'employees.php?reactivate=' + id + '&branch=<?= $selected_branch_id ?>';
    document.getElementById('reactivateModal').style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function closeReactivateModal() {
    document.getElementById('reactivateModal').style.display = 'none';
    document.body.style.overflow = '';
}

// ESC key closes modals
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeModal();
        closeReactivateModal();
    }
});

// ================================================================
// ✅ FOOTER TIME
// ================================================================
setInterval(function() {
    var now = new Date();
    var timeStr = now.toLocaleTimeString('en-US', {
        hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true
    });
    var ftEl = document.getElementById('footerTime');
    if (ftEl) ftEl.textContent = timeStr;
}, 1000);

console.log('%c👥 Braick - Employees Management', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
console.log('%c✅ Uses SHARED header & sidebar', 'font-size:13px; color:#059669;');
console.log('%c✅ Search bar ipo kwenye TABLE HEADER (kama patients.php)', 'font-size:13px; color:#059669;');
console.log('%c✅ Scroll buttons < > zipo kwenye TABLE HEADER', 'font-size:13px; color:#059669;');
console.log('%c✅ Table MOJA TU - hakuna table ndani ya table', 'font-size:13px; color:#059669;');
console.log('%c🌙 Dark mode: Handled by header', 'font-size:13px; color:#7C3AED;');
</script>

</body>
</html>