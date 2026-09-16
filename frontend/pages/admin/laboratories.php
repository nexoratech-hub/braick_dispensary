<?php
// ================================================================
// FILE: frontend/pages/admin/laboratories.php
// ADMIN - VIEW ALL LABORATORIES
// ✅ Uses SHARED header & sidebar
// ✅ Full dark mode support
// ✅ Blue page header card
// ================================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: ../../auth/login.php');
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
        default: header('Location: ../../auth/login.php'); break;
    }
    exit;
}

$user_id = $_SESSION['user_id'];
$user_full_name = $_SESSION['full_name'] ?? 'Admin';
$user_role = $_SESSION['role'] ?? 'admin';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$profile_pic = $_SESSION['profile_pic'] ?? '';

require_once '../../../backend/config/database.php';
require_once '../../../backend/helpers/functions.php';

$db = Database::getInstance()->getConnection();

// ================================================================
// CLEAN URL - Remove error parameter
// ================================================================
if (isset($_GET['error'])) {
    $clean_url = $_SERVER['PHP_SELF'];
    $params = [];
    if (isset($_GET['branch']) && $_GET['branch'] !== 'all') {
        $params[] = 'branch=' . urlencode($_GET['branch']);
    }
    if (isset($_GET['search']) && !empty($_GET['search'])) {
        $params[] = 'search=' . urlencode($_GET['search']);
    }
    if (isset($_GET['status']) && $_GET['status'] !== 'all') {
        $params[] = 'status=' . urlencode($_GET['status']);
    }
    if (!empty($params)) {
        $clean_url .= '?' . implode('&', $params);
    }
    header('Location: ' . $clean_url);
    exit;
}

// ================================================================
// FILTER PARAMETERS
// ================================================================
$selected_branch_id = isset($_GET['branch']) ? $_GET['branch'] : 'all';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : 'all';

// Validate branch
if ($selected_branch_id !== 'all' && is_numeric($selected_branch_id)) {
    $check_stmt = $db->prepare("SELECT id FROM branches WHERE id = ? AND status = 'active'");
    $check_stmt->execute([(int)$selected_branch_id]);
    if (!$check_stmt->fetch()) {
        $selected_branch_id = 'all';
        $clean_url = $_SERVER['PHP_SELF'];
        $params = [];
        if (!empty($search)) $params[] = 'search=' . urlencode($search);
        if ($status_filter !== 'all') $params[] = 'status=' . urlencode($status_filter);
        if (!empty($params)) $clean_url .= '?' . implode('&', $params);
        header('Location: ' . $clean_url);
        exit;
    }
}

// ================================================================
// BUILD QUERY
// ================================================================
$query = "
    SELECT 
        b.*,
        (SELECT COUNT(*) FROM users WHERE branch_id = b.id AND role = 'laboratory' AND status = 'active') as active_technicians,
        (SELECT COUNT(*) FROM users WHERE branch_id = b.id AND role = 'laboratory') as total_technicians,
        (SELECT COUNT(*) FROM lab_tests WHERE branch_id = b.id AND status = 'pending') as pending_tests,
        (SELECT COUNT(*) FROM lab_tests WHERE branch_id = b.id AND status = 'in_progress') as in_progress_tests,
        (SELECT COUNT(*) FROM lab_tests WHERE branch_id = b.id AND status = 'completed') as completed_tests,
        (SELECT COUNT(*) FROM lab_tests WHERE branch_id = b.id) as total_tests,
        (SELECT COUNT(*) FROM lab_tests_catalog WHERE branch_id = b.id AND is_active = 1) as total_test_types
    FROM branches b
    WHERE 1=1
";

$params = [];

if ($selected_branch_id !== 'all' && is_numeric($selected_branch_id)) {
    $query .= " AND b.id = ?";
    $params[] = (int)$selected_branch_id;
}

if ($status_filter !== 'all') {
    $query .= " AND b.status = ?";
    $params[] = $status_filter;
} else {
    $query .= " AND b.status = 'active'";
}

if (!empty($search)) {
    $query .= " AND (b.name LIKE ? OR b.location LIKE ? OR b.phone LIKE ? OR b.email LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

$query .= " ORDER BY b.name ASC";

$laboratories = [];
try {
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $laboratories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching laboratories: " . $e->getMessage());
    $laboratories = [];
}

// ================================================================
// STATISTICS
// ================================================================
$total_labs = count($laboratories);
$total_technicians = 0;
$total_tests = 0;
$pending_tests = 0;
$completed_tests = 0;
$in_progress_tests = 0;

foreach ($laboratories as $lab) {
    $total_technicians += ($lab['total_technicians'] ?? 0);
    $total_tests += ($lab['total_tests'] ?? 0);
    $pending_tests += ($lab['pending_tests'] ?? 0);
    $completed_tests += ($lab['completed_tests'] ?? 0);
    $in_progress_tests += ($lab['in_progress_tests'] ?? 0);
}

// ================================================================
// BRANCHES FOR FILTER
// ================================================================
$branches = [];
try {
    $stmt = $db->query("SELECT id, name FROM branches WHERE status = 'active' ORDER BY name");
    $branches = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $branches = [];
}

$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

$logo_url = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

// ================================================================
// INCLUDE SHARED HEADER & SIDEBAR
// ================================================================
include_once '../../components/admin_header.php';
include_once '../../components/admin_sidebar.php';
?>

<!-- ================================================================ -->
<!-- PAGE-SPECIFIC CSS - FULL DARK MODE -->
<!-- ================================================================ -->
<style>
    /* LIGHT MODE */
    :root {
        --page-bg-body: #F0F4F8;
        --page-bg-card: #FFFFFF;
        --page-text-primary: #1E293B;
        --page-text-secondary: #64748B;
        --page-text-muted: #94A3B8;
        --page-border: #E2E8F0;
        --page-hover: #F8FAFC;
    }

    /* DARK MODE */
    [data-theme="dark"] {
        --page-bg-body: #0F172A;
        --page-bg-card: #1E293B;
        --page-text-primary: #F1F5F9;
        --page-text-secondary: #94A3B8;
        --page-text-muted: #64748B;
        --page-border: #334155;
        --page-hover: #1E3A5F;
    }

    /* BODY & MAIN CONTENT */
    body { background: var(--page-bg-body, #F0F4F8); }
    html[data-theme="dark"] body { background: #0F172A !important; }
    .main-content { background: var(--page-bg-body, #F0F4F8); }
    html[data-theme="dark"] .main-content { background: #0F172A !important; color: #F1F5F9; }

    /* ================================================================
       BLUE PAGE HEADER
       ================================================================ */
    .page-header-custom {
        background: linear-gradient(135deg, #0B5ED7 0%, #0A4CA8 50%, #083D8A 100%);
        border-radius: 18px;
        padding: 28px 36px;
        margin-bottom: 24px;
        display: flex;
        flex-wrap: wrap;
        justify-content: space-between;
        align-items: center;
        gap: 16px;
        box-shadow: 0 8px 32px rgba(11, 94, 215, 0.35), 0 4px 12px rgba(11, 94, 215, 0.2);
        position: relative;
        overflow: hidden;
    }

    .page-header-custom::before {
        content: '';
        position: absolute;
        top: -60%;
        right: -10%;
        width: 400px;
        height: 400px;
        background: radial-gradient(circle, rgba(255,255,255,0.12) 0%, transparent 70%);
        border-radius: 50%;
        pointer-events: none;
    }

    .page-header-custom::after {
        content: '';
        position: absolute;
        bottom: -60%;
        left: -5%;
        width: 300px;
        height: 300px;
        background: radial-gradient(circle, rgba(255,255,255,0.08) 0%, transparent 70%);
        border-radius: 50%;
        pointer-events: none;
    }

    .page-header-custom .page-title {
        color: white;
        font-size: 1.8rem;
        font-weight: 800;
        display: flex;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
        position: relative;
        z-index: 1;
        margin: 0;
        letter-spacing: -0.02em;
    }

    .page-header-custom .page-title i {
        width: 48px;
        height: 48px;
        background: rgba(255,255,255,0.2);
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.4rem;
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255,255,255,0.2);
    }

    .page-header-custom .page-subtitle {
        color: rgba(255,255,255,0.9);
        font-size: 0.95rem;
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
        position: relative;
        z-index: 1;
        margin-top: 8px;
    }

    .page-header-custom .role-badge-display {
        background: rgba(255,255,255,0.25);
        color: white;
        padding: 4px 14px;
        border-radius: 20px;
        font-size: 0.65rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        backdrop-filter: blur(4px);
        border: 1px solid rgba(255,255,255,0.3);
    }

    .page-header-custom .header-badge {
        background: rgba(255,255,255,0.15);
        color: white;
        padding: 6px 14px;
        border-radius: 20px;
        font-size: 0.72rem;
        font-weight: 600;
        backdrop-filter: blur(4px);
        display: inline-flex;
        align-items: center;
        gap: 6px;
        border: 1px solid rgba(255,255,255,0.2);
    }

    .page-header-custom .header-badge.green-badge {
        background: rgba(52,211,153,0.25);
        border-color: rgba(52,211,153,0.4);
        color: #A7F3D0;
    }

    .page-header-custom .header-badge.orange-badge {
        background: rgba(251,191,36,0.25);
        border-color: rgba(251,191,36,0.4);
        color: #FDE68A;
    }

    .page-header-custom .header-badge.purple-badge {
        background: rgba(167,139,250,0.25);
        border-color: rgba(167,139,250,0.4);
        color: #DDD6FE;
    }

    /* ================================================================
       STATS ROW
       ================================================================ */
    .stats-row {
        display: grid;
        grid-template-columns: repeat(5, 1fr);
        gap: 16px;
        margin-bottom: 24px;
    }

    .stat-card-custom {
        background: var(--page-bg-card, #FFFFFF);
        border-radius: 14px;
        padding: 16px 20px;
        border: 2px solid var(--page-border, #E2E8F0);
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
    }

    html[data-theme="dark"] .stat-card-custom {
        background: #1E293B;
        border-color: #334155;
    }

    .stat-card-custom::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 4px;
        height: 100%;
        background: linear-gradient(180deg, #0B5ED7, #0A4CA8);
        opacity: 0.9;
    }

    .stat-card-custom:hover {
        border-color: #0B5ED7;
        transform: translateY(-4px);
        box-shadow: 0 8px 25px rgba(11, 94, 215, 0.15);
    }

    .stat-card-custom .stat-icon-sm {
        width: 40px;
        height: 40px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1rem;
        flex-shrink: 0;
    }

    .stat-card-custom .stat-icon-sm.blue { background: #E8F0FE; color: #0B5ED7; }
    .stat-card-custom .stat-icon-sm.green { background: #ECFDF5; color: #059669; }
    .stat-card-custom .stat-icon-sm.orange { background: #FFFBEB; color: #F59E0B; }
    .stat-card-custom .stat-icon-sm.purple { background: #F5F3FF; color: #7C3AED; }
    .stat-card-custom .stat-icon-sm.teal { background: #ECFDF5; color: #0D9488; }

    html[data-theme="dark"] .stat-card-custom .stat-icon-sm.blue { background: #1E3A5F; color: #6EA8FE; }
    html[data-theme="dark"] .stat-card-custom .stat-icon-sm.green { background: #1A3A2A; color: #34D399; }
    html[data-theme="dark"] .stat-card-custom .stat-icon-sm.orange { background: #3A2A1A; color: #FBBF24; }
    html[data-theme="dark"] .stat-card-custom .stat-icon-sm.purple { background: #2D1B4E; color: #A78BFA; }
    html[data-theme="dark"] .stat-card-custom .stat-icon-sm.teal { background: #0F3D3D; color: #5EEAD4; }

    .stat-card-custom .stat-number {
        font-size: 1.5rem;
        font-weight: 800;
        line-height: 1.1;
    }

    .stat-card-custom .stat-number.blue { color: #0B5ED7; }
    .stat-card-custom .stat-number.green { color: #059669; }
    .stat-card-custom .stat-number.orange { color: #F59E0B; }
    .stat-card-custom .stat-number.purple { color: #7C3AED; }
    .stat-card-custom .stat-number.teal { color: #0D9488; }

    html[data-theme="dark"] .stat-card-custom .stat-number.blue { color: #6EA8FE; }
    html[data-theme="dark"] .stat-card-custom .stat-number.green { color: #34D399; }
    html[data-theme="dark"] .stat-card-custom .stat-number.orange { color: #FBBF24; }
    html[data-theme="dark"] .stat-card-custom .stat-number.purple { color: #A78BFA; }
    html[data-theme="dark"] .stat-card-custom .stat-number.teal { color: #5EEAD4; }

    .stat-card-custom .stat-label {
        font-size: 0.65rem;
        color: var(--page-text-secondary, #64748B);
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    /* ================================================================
       FILTER BAR
       ================================================================ */
    .filter-bar-custom {
        background: var(--page-bg-card, #FFFFFF);
        border-radius: 14px;
        padding: 16px 20px;
        border: 2px solid var(--page-border, #E2E8F0);
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
        align-items: center;
        margin-bottom: 24px;
        position: relative;
    }

    html[data-theme="dark"] .filter-bar-custom {
        background: #1E293B;
        border-color: #334155;
    }

    .filter-bar-custom::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 3px;
        background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
        border-radius: 14px 14px 0 0;
    }

    .filter-bar-custom .filter-label {
        font-size: 0.72rem;
        font-weight: 700;
        color: var(--page-text-secondary, #64748B);
        text-transform: uppercase;
        letter-spacing: 0.04em;
    }

    .filter-bar-custom select,
    .filter-bar-custom input {
        background: var(--page-bg-body, #F0F4F8);
        border: 2px solid var(--page-border, #E2E8F0);
        border-radius: 8px;
        padding: 8px 12px;
        font-size: 0.78rem;
        color: var(--page-text-primary, #1E293B);
        outline: none;
        transition: all 0.3s;
    }

    html[data-theme="dark"] .filter-bar-custom select,
    html[data-theme="dark"] .filter-bar-custom input {
        background: #0F172A;
        color: #F1F5F9;
        border-color: #334155;
    }

    .filter-bar-custom select:focus,
    .filter-bar-custom input:focus {
        border-color: #0B5ED7;
        box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.12);
    }

    .filter-bar-custom .btn-filter {
        background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
        color: white;
        border: none;
        padding: 8px 18px;
        border-radius: 8px;
        font-weight: 700;
        font-size: 0.75rem;
        cursor: pointer;
        transition: all 0.3s;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }

    .filter-bar-custom .btn-filter:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3);
    }

    .filter-bar-custom .btn-reset {
        background: transparent;
        color: var(--page-text-secondary, #64748B);
        border: 2px solid var(--page-border, #E2E8F0);
        padding: 8px 18px;
        border-radius: 8px;
        font-weight: 700;
        font-size: 0.75rem;
        cursor: pointer;
        transition: all 0.3s;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }

    .filter-bar-custom .btn-reset:hover {
        border-color: #0B5ED7;
        color: #0B5ED7;
    }

    /* ================================================================
       LAB GRID
       ================================================================ */
    .lab-grid-custom {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
        gap: 20px;
    }

    .lab-card-custom {
        background: var(--page-bg-card, #FFFFFF);
        border-radius: 16px;
        border: 2px solid var(--page-border, #E2E8F0);
        overflow: hidden;
        transition: all 0.3s ease;
        position: relative;
    }

    html[data-theme="dark"] .lab-card-custom {
        background: #1E293B;
        border-color: #334155;
    }

    .lab-card-custom:hover {
        transform: translateY(-5px);
        box-shadow: 0 12px 35px rgba(11, 94, 215, 0.15);
        border-color: #0B5ED7;
    }

    /* BLUE CARD TOP */
    .lab-card-custom .card-top-custom {
        padding: 16px 20px;
        background: linear-gradient(135deg, #0B5ED7 0%, #0A4CA8 50%, #083D8A 100%);
        position: relative;
        overflow: hidden;
        display: flex;
        justify-content: space-between;
        align-items: start;
        gap: 12px;
    }

    .lab-card-custom .card-top-custom::after {
        content: '';
        position: absolute;
        top: -50%;
        right: -20%;
        width: 200px;
        height: 200px;
        background: radial-gradient(circle, rgba(255,255,255,0.12) 0%, transparent 70%);
        border-radius: 50%;
        pointer-events: none;
    }

    .lab-card-custom .card-top-custom .name {
        font-size: 1.05rem;
        font-weight: 700;
        color: white;
        position: relative;
        z-index: 1;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .lab-card-custom .card-top-custom .name i {
        color: rgba(255,255,255,0.85);
    }

    .lab-card-custom .card-top-custom .location-text {
        font-size: 0.72rem;
        color: rgba(255,255,255,0.8);
        margin-top: 4px;
        display: flex;
        align-items: center;
        gap: 5px;
        position: relative;
        z-index: 1;
    }

    .lab-card-custom .card-top-custom .status-badge-custom {
        padding: 4px 14px;
        border-radius: 20px;
        font-size: 0.62rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        position: relative;
        z-index: 1;
        backdrop-filter: blur(4px);
        flex-shrink: 0;
    }

    .lab-card-custom .card-top-custom .status-badge-custom.active {
        background: rgba(255,255,255,0.22);
        color: white;
        border: 1px solid rgba(255,255,255,0.35);
    }

    .lab-card-custom .card-top-custom .status-badge-custom.inactive {
        background: rgba(220, 38, 38, 0.35);
        color: #FCA5A5;
        border: 1px solid rgba(220, 38, 38, 0.4);
    }

    /* CARD BODY */
    .lab-card-custom .card-body-custom {
        padding: 16px 20px;
    }

    .lab-card-custom .card-body-custom .info-row-custom {
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 0.78rem;
        color: var(--page-text-secondary, #64748B);
        padding: 5px 0;
    }

    .lab-card-custom .card-body-custom .info-row-custom i {
        width: 18px;
        color: #0B5ED7;
        font-size: 0.8rem;
        text-align: center;
    }

    html[data-theme="dark"] .lab-card-custom .card-body-custom .info-row-custom i {
        color: #6EA8FE;
    }

    /* CARD STATS */
    .lab-card-custom .card-stats-custom {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 4px;
        padding: 12px 16px;
        background: var(--page-hover, #F8FAFC);
        border-top: 2px solid var(--page-border, #E2E8F0);
        border-bottom: 2px solid var(--page-border, #E2E8F0);
    }

    html[data-theme="dark"] .lab-card-custom .card-stats-custom {
        background: #0F172A;
        border-color: #334155;
    }

    .lab-card-custom .card-stats-custom .stat-item {
        text-align: center;
        padding: 6px 0;
        border-radius: 8px;
        transition: all 0.3s ease;
    }

    .lab-card-custom .card-stats-custom .stat-item:hover {
        background: var(--page-bg-card, #FFFFFF);
    }

    html[data-theme="dark"] .lab-card-custom .card-stats-custom .stat-item:hover {
        background: #1E3A5F;
    }

    .lab-card-custom .card-stats-custom .stat-item .num {
        font-size: 0.95rem;
        font-weight: 800;
        color: var(--page-text-primary, #1E293B);
        line-height: 1.1;
    }

    html[data-theme="dark"] .lab-card-custom .card-stats-custom .stat-item .num {
        color: #F1F5F9;
    }

    .lab-card-custom .card-stats-custom .stat-item .num.blue { color: #0B5ED7; }
    .lab-card-custom .card-stats-custom .stat-item .num.green { color: #059669; }
    .lab-card-custom .card-stats-custom .stat-item .num.orange { color: #F59E0B; }
    .lab-card-custom .card-stats-custom .stat-item .num.purple { color: #7C3AED; }
    .lab-card-custom .card-stats-custom .stat-item .num.teal { color: #0D9488; }

    html[data-theme="dark"] .lab-card-custom .card-stats-custom .stat-item .num.blue { color: #6EA8FE; }
    html[data-theme="dark"] .lab-card-custom .card-stats-custom .stat-item .num.green { color: #34D399; }
    html[data-theme="dark"] .lab-card-custom .card-stats-custom .stat-item .num.orange { color: #FBBF24; }
    html[data-theme="dark"] .lab-card-custom .card-stats-custom .stat-item .num.purple { color: #A78BFA; }
    html[data-theme="dark"] .lab-card-custom .card-stats-custom .stat-item .num.teal { color: #5EEAD4; }

    .lab-card-custom .card-stats-custom .stat-item .label {
        font-size: 0.55rem;
        color: var(--page-text-secondary, #64748B);
        text-transform: uppercase;
        letter-spacing: 0.04em;
        font-weight: 700;
        margin-top: 2px;
    }

    /* CARD ACTIONS */
    .lab-card-custom .card-actions-custom {
        padding: 12px 18px;
        display: flex;
        gap: 8px;
        justify-content: flex-end;
        flex-wrap: wrap;
    }

    .lab-card-custom .card-actions-custom .btn-action {
        padding: 6px 14px;
        border-radius: 8px;
        font-size: 0.68rem;
        font-weight: 700;
        text-decoration: none;
        transition: all 0.3s;
        border: 2px solid var(--page-border, #E2E8F0);
        color: var(--page-text-secondary, #64748B);
        background: transparent;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 5px;
        white-space: nowrap;
    }

    .lab-card-custom .card-actions-custom .btn-action:hover {
        border-color: #0B5ED7;
        color: #0B5ED7;
        transform: translateY(-2px);
    }

    .lab-card-custom .card-actions-custom .btn-action.primary {
        background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
        color: white;
        border-color: #0B5ED7;
    }

    .lab-card-custom .card-actions-custom .btn-action.primary:hover {
        box-shadow: 0 4px 16px rgba(11, 94, 215, 0.35);
        color: white;
    }

    /* ================================================================
       EMPTY STATE
       ================================================================ */
    .empty-state-custom {
        text-align: center;
        padding: 60px 20px;
        color: var(--page-text-secondary, #64748B);
        background: var(--page-bg-card, #FFFFFF);
        border-radius: 16px;
        border: 2px dashed var(--page-border, #E2E8F0);
    }

    html[data-theme="dark"] .empty-state-custom {
        background: #1E293B;
        border-color: #334155;
    }

    .empty-state-custom i {
        font-size: 4rem;
        color: var(--page-text-muted, #94A3B8);
        margin-bottom: 16px;
        display: block;
        opacity: 0.5;
    }

    .empty-state-custom h3 {
        font-size: 1.2rem;
        color: var(--page-text-primary, #1E293B);
        margin-bottom: 8px;
    }

    html[data-theme="dark"] .empty-state-custom h3 { color: #F1F5F9; }

    .empty-state-custom p {
        font-size: 0.85rem;
    }

    /* ================================================================
       RESPONSIVE
       ================================================================ */
    @media (max-width: 1200px) {
        .stats-row { grid-template-columns: repeat(3, 1fr); }
    }

    @media (max-width: 768px) {
        .page-header-custom { padding: 20px; }
        .page-header-custom .page-title { font-size: 1.25rem; }
        .page-header-custom .page-title i { width: 38px; height: 38px; font-size: 1.1rem; }
        .lab-grid-custom { grid-template-columns: 1fr; }
        .stats-row { grid-template-columns: repeat(2, 1fr); }
        .filter-bar-custom { flex-direction: column; align-items: stretch; }
        .lab-card-custom .card-stats-custom { grid-template-columns: repeat(4, 1fr); }
    }

    @media (max-width: 480px) {
        .stats-row { grid-template-columns: 1fr; }
        .lab-card-custom .card-stats-custom { grid-template-columns: repeat(2, 1fr); }
    }

    /* Print */
    @media print {
        .page-header-custom { background: #0B5ED7 !important; -webkit-print-color-adjust: exact; }
        .filter-bar-custom, .card-actions-custom { display: none !important; }
    }
</style>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <!-- BLUE PAGE HEADER -->
    <div class="page-header-custom">
        <div style="position:relative;z-index:1;">
            <h1 class="page-title">
                <i class="fas fa-flask"></i>
                Laboratories
                <span class="role-badge-display">ADMIN</span>
            </h1>
            <p class="page-subtitle">
                <span><i class="fas fa-microscope"></i> <strong><?= $total_labs ?></strong> laboratories found</span>
                <span class="header-badge green-badge">
                    <i class="fas fa-vial"></i> <?= number_format($total_tests) ?> Tests
                </span>
                <span class="header-badge orange-badge">
                    <i class="fas fa-clock"></i> <?= number_format($pending_tests) ?> Pending
                </span>
                <span class="header-badge purple-badge">
                    <i class="fas fa-user-md"></i> <?= number_format($total_technicians) ?> Technicians
                </span>
            </p>
        </div>
    </div>

    <!-- STATS ROW -->
    <div class="stats-row">
        <div class="stat-card-custom">
            <div style="display:flex;align-items:center;gap:12px;">
                <div class="stat-icon-sm blue"><i class="fas fa-flask"></i></div>
                <div>
                    <p class="stat-label">Total Labs</p>
                    <p class="stat-number blue"><?= number_format($total_labs) ?></p>
                </div>
            </div>
        </div>
        <div class="stat-card-custom">
            <div style="display:flex;align-items:center;gap:12px;">
                <div class="stat-icon-sm green"><i class="fas fa-vial"></i></div>
                <div>
                    <p class="stat-label">Total Tests</p>
                    <p class="stat-number green"><?= number_format($total_tests) ?></p>
                </div>
            </div>
        </div>
        <div class="stat-card-custom">
            <div style="display:flex;align-items:center;gap:12px;">
                <div class="stat-icon-sm orange"><i class="fas fa-clock"></i></div>
                <div>
                    <p class="stat-label">Pending Tests</p>
                    <p class="stat-number orange"><?= number_format($pending_tests) ?></p>
                </div>
            </div>
        </div>
        <div class="stat-card-custom">
            <div style="display:flex;align-items:center;gap:12px;">
                <div class="stat-icon-sm purple"><i class="fas fa-check-circle"></i></div>
                <div>
                    <p class="stat-label">Completed</p>
                    <p class="stat-number purple"><?= number_format($completed_tests) ?></p>
                </div>
            </div>
        </div>
        <div class="stat-card-custom">
            <div style="display:flex;align-items:center;gap:12px;">
                <div class="stat-icon-sm teal"><i class="fas fa-user-md"></i></div>
                <div>
                    <p class="stat-label">Technicians</p>
                    <p class="stat-number teal"><?= number_format($total_technicians) ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- FILTER BAR -->
    <div class="filter-bar-custom">
        <span class="filter-label"><i class="fas fa-filter"></i> Filter</span>
        
        <form method="GET" action="" style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;flex:1;">
            <input type="hidden" name="branch" value="<?= htmlspecialchars($selected_branch_id) ?>">
            
            <select name="status" style="flex:1;min-width:120px;">
                <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>All Status</option>
                <option value="active" <?= $status_filter === 'active' ? 'selected' : '' ?>>Active</option>
                <option value="inactive" <?= $status_filter === 'inactive' ? 'selected' : '' ?>>Inactive</option>
            </select>
            
            <input type="text" name="search" placeholder="Search by name, location..." 
                   value="<?= htmlspecialchars($search) ?>" style="flex:1;min-width:180px;">
            
            <button type="submit" class="btn-filter">
                <i class="fas fa-search"></i> Apply
            </button>
            
            <a href="laboratories.php?branch=<?= urlencode($selected_branch_id) ?>" class="btn-reset">
                <i class="fas fa-times"></i> Reset
            </a>
        </form>
    </div>

    <!-- LAB GRID -->
    <?php if (count($laboratories) > 0): ?>
        <div class="lab-grid-custom">
            <?php foreach ($laboratories as $lab): ?>
                <div class="lab-card-custom">
                    <div class="card-top-custom">
                        <div style="position:relative;z-index:1;">
                            <div class="name">
                                <i class="fas fa-flask"></i>
                                <?= htmlspecialchars($lab['name']) ?>
                            </div>
                            <div class="location-text">
                                <i class="fas fa-map-marker-alt"></i>
                                <?= htmlspecialchars($lab['location'] ?? 'N/A') ?>
                            </div>
                        </div>
                        <span class="status-badge-custom <?= $lab['status'] === 'active' ? 'active' : 'inactive' ?>">
                            <?= $lab['status'] === 'active' ? 'Active' : 'Inactive' ?>
                        </span>
                    </div>
                    
                    <div class="card-body-custom">
                        <div class="info-row-custom">
                            <i class="fas fa-phone"></i>
                            <?= htmlspecialchars($lab['phone'] ?? 'N/A') ?>
                        </div>
                        <div class="info-row-custom">
                            <i class="fas fa-envelope"></i>
                            <?= htmlspecialchars($lab['email'] ?? 'N/A') ?>
                        </div>
                        <div class="info-row-custom">
                            <i class="fas fa-user-md"></i>
                            <?= ($lab['active_technicians'] ?? 0) ?> Active / <?= ($lab['total_technicians'] ?? 0) ?> Technicians
                        </div>
                        <div class="info-row-custom">
                            <i class="fas fa-calendar-plus"></i>
                            Created: <?= date('M d, Y', strtotime($lab['created_at'] ?? 'now')) ?>
                        </div>
                    </div>
                    
                    <div class="card-stats-custom">
                        <div class="stat-item">
                            <div class="num blue"><?= number_format($lab['total_tests'] ?? 0) ?></div>
                            <div class="label">Tests</div>
                        </div>
                        <div class="stat-item">
                            <div class="num <?= ($lab['pending_tests'] ?? 0) > 0 ? 'orange' : 'green' ?>">
                                <?= number_format($lab['pending_tests'] ?? 0) ?>
                            </div>
                            <div class="label">Pending</div>
                        </div>
                        <div class="stat-item">
                            <div class="num purple"><?= number_format($lab['total_test_types'] ?? 0) ?></div>
                            <div class="label">Test Types</div>
                        </div>
                        <div class="stat-item">
                            <div class="num teal"><?= number_format($lab['in_progress_tests'] ?? 0) ?></div>
                            <div class="label">In Progress</div>
                        </div>
                    </div>
                    
                    <div class="card-actions-custom">
                        <a href="view_laboratory.php?id=<?= $lab['id'] ?>&branch=<?= $selected_branch_id ?>" 
                           class="btn-action primary">
                            <i class="fas fa-eye"></i> View
                        </a>
                        <a href="edit_laboratory.php?id=<?= $lab['id'] ?>&branch=<?= $selected_branch_id ?>" 
                           class="btn-action">
                            <i class="fas fa-edit"></i> Edit
                        </a>
                        <a href="lab_tests.php?branch=<?= $lab['id'] ?>" 
                           class="btn-action">
                            <i class="fas fa-vial"></i> Tests
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="empty-state-custom">
            <i class="fas fa-flask"></i>
            <h3>No Laboratories Found</h3>
            <p>Try adjusting your filters</p>
        </div>
    <?php endif; ?>

</main>

<!-- ================================================================ -->
<!-- PAGE-SPECIFIC JAVASCRIPT -->
<!-- ================================================================ -->
<script>
    // ================================================================
    // DARK MODE BACKGROUND ENFORCEMENT
    // ================================================================
    function enforceDarkModeBackground() {
        var isDark = document.documentElement.getAttribute('data-theme') === 'dark';
        var body = document.body;
        var mainContent = document.querySelector('.main-content');
        
        if (isDark) {
            if (body) body.style.background = '#0F172A';
            if (mainContent) mainContent.style.background = '#0F172A';
        } else {
            if (body) body.style.background = '#F0F4F8';
            if (mainContent) mainContent.style.background = '#F0F4F8';
        }
    }

    enforceDarkModeBackground();

    document.addEventListener('darkModeChanged', function(e) {
        setTimeout(enforceDarkModeBackground, 50);
    });

    var observer = new MutationObserver(function(mutations) {
        mutations.forEach(function(mutation) {
            if (mutation.attributeName === 'data-theme') {
                enforceDarkModeBackground();
            }
        });
    });
    observer.observe(document.documentElement, { attributes: true });

    console.log('%c🧪 Braick - Laboratories', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c✅ Uses SHARED header & sidebar', 'font-size:13px; color:#059669;');
    console.log('%c🌙 FULL DARK MODE inafanya kazi', 'font-size:13px; color:#7C3AED;');
    console.log('%c👤 Admin: <?= htmlspecialchars($user_full_name) ?>', 'font-size:13px; color:#059669;');
    console.log('%c🔬 Total Labs: <?= $total_labs ?>', 'font-size:13px; color:#0B5ED7;');
</script>

</body>
</html>