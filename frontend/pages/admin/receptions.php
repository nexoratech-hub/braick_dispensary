<?php
// ================================================================
// FILE: frontend/pages/admin/receptions.php
// SUPER ADMIN - VIEW ALL RECEPTIONS (BRANCHES)
// BRAICK DISPENSARY - BLUE THEME
// ✅ V3: View Reception + Edit Reception
// ✅ V3: Stats Cards 3 juu, 3 chini (grid 3x2)
// ✅ V3: CSS nzuri kwa stats cards
// ✅ V3: Bila Dashboard button, bila Total Revenue
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

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database connection error: " . $e->getMessage());
}

// UNREAD NOTIFICATIONS
$unread_notifications = 0;
try {
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$user_id]);
    $unread_notifications = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
} catch (Exception $e) { $unread_notifications = 0; }

// FILTERS
$selected_branch_id = isset($_GET['branch']) ? $_GET['branch'] : 'all';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';

if (isset($_GET['error']) || isset($_GET['invalid_id'])) {
    $clean_url = $_SERVER['PHP_SELF'] . '?branch=all';
    if (!empty($search)) $clean_url .= '&search=' . urlencode($search);
    if ($status_filter !== 'all') $clean_url .= '&status=' . urlencode($status_filter);
    header('Location: ' . $clean_url);
    exit;
}

$branch_id_for_query = null;
$error_msg = '';

if ($selected_branch_id !== 'all' && is_numeric($selected_branch_id)) {
    $branch_id_for_query = (int)$selected_branch_id;
    try {
        $stmt = $db->prepare("SELECT id, status FROM branches WHERE id = ?");
        $stmt->execute([$branch_id_for_query]);
        $branch_exists = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$branch_exists) {
            $error_msg = 'Branch not found. Showing all branches.';
            $selected_branch_id = 'all';
            $branch_id_for_query = null;
        }
    } catch (Exception $e) {
        $error_msg = 'Error validating branch. Showing all branches.';
        $selected_branch_id = 'all';
        $branch_id_for_query = null;
    }
}

// BUILD QUERY
$query = "
    SELECT 
        b.id, b.name, b.location, b.phone, b.email, b.logo, b.status, b.created_at, b.updated_at,
        COALESCE((SELECT COUNT(*) FROM users WHERE branch_id = b.id AND role = 'reception' AND status = 'active'), 0) as active_receptionists,
        COALESCE((SELECT COUNT(*) FROM users WHERE branch_id = b.id AND role = 'reception'), 0) as total_receptionists,
        COALESCE((SELECT COUNT(*) FROM patients WHERE branch_id = b.id), 0) as total_patients,
        COALESCE((SELECT COUNT(*) FROM patients WHERE branch_id = b.id AND DATE(created_at) = CURDATE()), 0) as today_patients,
        COALESCE((SELECT COUNT(*) FROM patients WHERE branch_id = b.id AND assigned_doctor_id IS NOT NULL), 0) as assigned_patients,
        COALESCE((SELECT COUNT(*) FROM visits WHERE branch_id = b.id), 0) as total_visits,
        COALESCE((SELECT COUNT(*) FROM visits WHERE branch_id = b.id AND status = 'pending'), 0) as pending_visits,
        COALESCE((SELECT COUNT(*) FROM visits WHERE branch_id = b.id AND DATE(visit_date) = CURDATE()), 0) as today_visits,
        COALESCE((SELECT COUNT(*) FROM appointments WHERE branch_id = b.id AND status = 'scheduled'), 0) as scheduled_appointments,
        COALESCE((SELECT COUNT(*) FROM appointments WHERE branch_id = b.id AND status = 'confirmed'), 0) as confirmed_appointments,
        COALESCE((SELECT COUNT(*) FROM appointments WHERE branch_id = b.id), 0) as total_appointments
    FROM branches b
    WHERE 1=1
";

$params = [];

if ($branch_id_for_query !== null && is_numeric($branch_id_for_query)) {
    $query .= " AND b.id = ?";
    $params[] = $branch_id_for_query;
}
if ($status_filter !== 'all' && in_array($status_filter, ['active', 'inactive'])) {
    $query .= " AND b.status = ?";
    $params[] = $status_filter;
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

$receptions = [];
try {
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $receptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching receptions: " . $e->getMessage());
    $receptions = [];
}

// BRANCHES FOR FILTER
$branches = [];
try {
    $stmt = $db->query("SELECT id, name FROM branches WHERE status = 'active' ORDER BY name");
    $branches = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $branches = []; }

// SUMMARY STATS
$total_receptions = count($receptions);
$total_patients = 0;
$total_visits = 0;
$total_appointments = 0;
$pending_visits = 0;
$today_visits = 0;
$assigned_patients = 0;

foreach ($receptions as $r) {
    $total_patients += ($r['total_patients'] ?? 0);
    $total_visits += ($r['total_visits'] ?? 0);
    $total_appointments += ($r['total_appointments'] ?? 0);
    $pending_visits += ($r['pending_visits'] ?? 0);
    $today_visits += ($r['today_visits'] ?? 0);
    $assigned_patients += ($r['assigned_patients'] ?? 0);
}

$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';
$logo_url = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

include_once '../../components/admin_header.php';
include_once '../../components/admin_sidebar.php';
?>

<style>
    :root {
        --rec-primary: #0B5ED7;
        --rec-primary-dark: #0A4CA8;
        --rec-primary-light: #3B82F6;
        --rec-primary-bg: #EFF6FF;
        --rec-primary-gradient: linear-gradient(135deg, #0B5ED7, #0A4CA8);
        --rec-primary-gradient-strong: linear-gradient(135deg, #0A4CA8, #073B8A);
        --rec-success: #059669;
        --rec-success-bg: #D1FAE5;
        --rec-danger: #DC2626;
        --rec-danger-bg: #FEE2E2;
        --rec-warning: #D97706;
        --rec-warning-bg: #FEF3C7;
        --rec-purple: #7C3AED;
        --rec-purple-bg: #EDE9FE;
        --rec-teal: #0D9488;
        --rec-teal-bg: #CCFBF1;
        --rec-gray-50: #F8FAFC;
        --rec-gray-100: #F1F5F9;
        --rec-gray-200: #E2E8F0;
        --rec-gray-300: #CBD5E1;
        --rec-gray-400: #94A3B8;
        --rec-gray-500: #64748B;
        --rec-gray-600: #475569;
        --rec-gray-700: #334155;
        --rec-gray-800: #1E293B;
        --rec-gray-900: #0F172A;
        --rec-bg-body: #F0F4F8;
        --rec-bg-card: #FFFFFF;
        --rec-text-primary: #1E293B;
        --rec-text-secondary: #64748B;
        --rec-border-color: #E2E8F0;
        --rec-radius: 12px;
        --rec-radius-lg: 18px;
        --rec-shadow-sm: 0 1px 2px rgba(0,0,0,0.05);
        --rec-shadow: 0 1px 3px rgba(0,0,0,0.08);
        --rec-shadow-md: 0 4px 12px rgba(0,0,0,0.08);
        --rec-shadow-lg: 0 10px 25px rgba(0,0,0,0.1);
    }

    [data-theme="dark"] {
        --rec-bg-body: #0F172A;
        --rec-bg-card: #1E293B;
        --rec-text-primary: #F1F5F9;
        --rec-text-secondary: #94A3B8;
        --rec-border-color: #334155;
        --rec-primary-bg: #1E3A5F;
        --rec-shadow-sm: 0 1px 3px rgba(0,0,0,0.3);
        --rec-shadow-md: 0 4px 12px rgba(0,0,0,0.3);
    }

    html[data-theme="dark"] body { background: #0F172A !important; }
    html[data-theme="dark"] .main-content { background: #0F172A !important; color: #F1F5F9; }

    /* PAGE HEADER */
    .page-header-rec {
        background: var(--rec-primary-gradient-strong);
        border-radius: var(--rec-radius-lg);
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
    .page-header-rec::before {
        content: '';
        position: absolute;
        top: -60%; right: -10%;
        width: 400px; height: 400px;
        background: rgba(255,255,255,0.05);
        border-radius: 50%;
        pointer-events: none;
    }
    .page-header-rec::after {
        content: '';
        position: absolute;
        bottom: -40%; left: -5%;
        width: 300px; height: 300px;
        background: rgba(255,255,255,0.03);
        border-radius: 50%;
        pointer-events: none;
    }
    .page-header-rec .page-title-rec {
        color: white;
        font-size: 1.8rem;
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
        position: relative;
        z-index: 1;
        margin: 0;
    }
    .page-header-rec .page-title-rec i { font-size: 2rem; opacity: 0.9; }
    .page-header-rec .page-subtitle-rec {
        color: rgba(255,255,255,0.85);
        font-size: 0.95rem;
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
        position: relative;
        z-index: 1;
        margin-top: 6px;
    }
    .page-header-rec .page-subtitle-rec strong { color: white; font-weight: 600; }
    .page-header-rec .role-badge-display-rec {
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
    .page-header-rec .header-badge-rec {
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
    .page-header-rec .header-badge-rec:hover {
        background: rgba(255,255,255,0.2);
        transform: translateY(-1px);
    }

    /* ALERT */
    .alert-rec {
        padding: 14px 20px;
        border-radius: var(--rec-radius);
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 12px;
        font-size: 0.9rem;
        border: 1px solid transparent;
        animation: slideDown 0.3s ease;
    }
    @keyframes slideDown {
        from { opacity: 0; transform: translateY(-10px); }
        to { opacity: 1; transform: translateY(0); }
    }
    .alert-warning-rec {
        background: var(--rec-warning-bg);
        color: var(--rec-warning);
        border-color: var(--rec-warning);
    }
    [data-theme="dark"] .alert-warning-rec { background: #3D2E0A; color: #FBBF24; }

    /* ================================================================
       STATS GRID - 3 JUU, 3 CHINI (GRID 3x2)
       ================================================================ */
    .stats-grid-rec {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 18px;
        margin-bottom: 28px;
    }

    .stat-card-rec {
        background: var(--rec-bg-card);
        border-radius: 16px;
        padding: 22px 24px;
        border: 2px solid var(--rec-border-color);
        box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        transition: all 0.35s cubic-bezier(0.4, 0, 0.2, 1);
        position: relative;
        overflow: hidden;
        display: flex;
        align-items: center;
        gap: 18px;
    }

    .stat-card-rec::before {
        content: '';
        position: absolute;
        top: 0; left: 0;
        width: 6px; height: 100%;
        border-radius: 0 4px 4px 0;
        transition: all 0.3s ease;
    }

    .stat-card-rec:hover {
        transform: translateY(-5px);
        box-shadow: 0 12px 35px rgba(11, 94, 215, 0.15);
        border-color: var(--rec-primary);
    }
    .stat-card-rec:hover::before { width: 8px; }

    /* COLORS */
    .stat-card-rec.blue::before { background: linear-gradient(180deg, #0B5ED7, #3B82F6); }
    .stat-card-rec.blue .stat-icon-rec { background: linear-gradient(135deg, #EFF6FF, #DBEAFE); color: #0B5ED7; border-color: rgba(11, 94, 215, 0.2); }
    .stat-card-rec.blue .stat-value-rec { color: #0B5ED7; }

    .stat-card-rec.green::before { background: linear-gradient(180deg, #059669, #34D399); }
    .stat-card-rec.green .stat-icon-rec { background: linear-gradient(135deg, #ECFDF5, #D1FAE5); color: #059669; border-color: rgba(5, 150, 105, 0.2); }
    .stat-card-rec.green .stat-value-rec { color: #059669; }

    .stat-card-rec.teal::before { background: linear-gradient(180deg, #0D9488, #14B8A6); }
    .stat-card-rec.teal .stat-icon-rec { background: linear-gradient(135deg, #F0FDFA, #CCFBF1); color: #0D9488; border-color: rgba(13, 148, 136, 0.2); }
    .stat-card-rec.teal .stat-value-rec { color: #0D9488; }

    .stat-card-rec.purple::before { background: linear-gradient(180deg, #7C3AED, #A78BFA); }
    .stat-card-rec.purple .stat-icon-rec { background: linear-gradient(135deg, #F5F3FF, #EDE9FE); color: #7C3AED; border-color: rgba(124, 58, 237, 0.2); }
    .stat-card-rec.purple .stat-value-rec { color: #7C3AED; }

    .stat-card-rec.orange::before { background: linear-gradient(180deg, #D97706, #F59E0B); }
    .stat-card-rec.orange .stat-icon-rec { background: linear-gradient(135deg, #FFFBEB, #FEF3C7); color: #D97706; border-color: rgba(217, 119, 6, 0.2); }
    .stat-card-rec.orange .stat-value-rec { color: #D97706; }

    .stat-card-rec.indigo::before { background: linear-gradient(180deg, #4F46E5, #818CF8); }
    .stat-card-rec.indigo .stat-icon-rec { background: linear-gradient(135deg, #EEF2FF, #E0E7FF); color: #4F46E5; border-color: rgba(79, 70, 229, 0.2); }
    .stat-card-rec.indigo .stat-value-rec { color: #4F46E5; }

    /* DARK MODE ICONS */
    [data-theme="dark"] .stat-card-rec.blue .stat-icon-rec { background: #1E3A5F; color: #3B82F6; }
    [data-theme="dark"] .stat-card-rec.green .stat-icon-rec { background: #1A3A2A; color: #34D399; }
    [data-theme="dark"] .stat-card-rec.teal .stat-icon-rec { background: #0F3D3D; color: #5EEAD4; }
    [data-theme="dark"] .stat-card-rec.purple .stat-icon-rec { background: #2D1B4E; color: #A78BFA; }
    [data-theme="dark"] .stat-card-rec.orange .stat-icon-rec { background: #3D2E0A; color: #FBBF24; }
    [data-theme="dark"] .stat-card-rec.indigo .stat-icon-rec { background: #1E1B4B; color: #818CF8; }

    .stat-card-rec .stat-icon-rec {
        width: 58px; height: 58px;
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        flex-shrink: 0;
        border: 2px solid transparent;
        transition: all 0.3s ease;
    }

    .stat-card-rec:hover .stat-icon-rec { transform: scale(1.08) rotate(-5deg); }

    .stat-card-rec .stat-content-rec {
        flex: 1;
        min-width: 0;
    }

    .stat-card-rec .stat-label-rec {
        font-size: 0.68rem;
        color: var(--rec-text-secondary);
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        margin: 0 0 4px 0;
    }

    .stat-card-rec .stat-value-rec {
        font-size: 1.75rem;
        font-weight: 900;
        color: var(--rec-text-primary);
        margin: 0;
        line-height: 1.1;
        letter-spacing: -0.02em;
        font-family: 'JetBrains Mono', 'Courier New', monospace;
    }

    .stat-card-rec .stat-sub-rec {
        font-size: 0.62rem;
        color: var(--rec-text-secondary);
        font-weight: 600;
        margin-top: 4px;
        display: flex;
        align-items: center;
        gap: 4px;
    }

    .stat-card-rec .stat-sub-rec .up { color: #059669; }
    .stat-card-rec .stat-sub-rec .warn { color: #D97706; }
    .stat-card-rec .stat-sub-rec .neutral { color: var(--rec-text-secondary); }

    /* FILTER BAR */
    .filter-bar-rec {
        background: var(--rec-bg-card);
        border-radius: var(--rec-radius);
        padding: 14px 18px;
        border: 2px solid var(--rec-border-color);
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        align-items: center;
        margin-bottom: 24px;
        box-shadow: var(--rec-shadow-sm);
        position: relative;
        overflow: hidden;
    }
    .filter-bar-rec::before {
        content: '';
        position: absolute;
        top: 0; left: 0; right: 0;
        height: 4px;
        background: var(--rec-primary-gradient-strong);
    }
    .filter-bar-rec .filter-label-rec {
        font-size: 0.7rem;
        font-weight: 700;
        color: var(--rec-primary);
        text-transform: uppercase;
        letter-spacing: 0.06em;
    }
    .filter-bar-rec select,
    .filter-bar-rec input {
        background: var(--rec-bg-body);
        border: 2px solid var(--rec-border-color);
        border-radius: 8px;
        padding: 5px 10px;
        font-size: 0.75rem;
        color: var(--rec-text-primary);
        outline: none;
        transition: all 0.3s;
        font-family: inherit;
    }
    .filter-bar-rec select:focus,
    .filter-bar-rec input:focus {
        border-color: var(--rec-primary);
        box-shadow: 0 0 0 4px rgba(11, 94, 215, 0.15);
    }
    .filter-bar-rec .btn-filter-rec {
        background: var(--rec-primary-gradient-strong);
        color: white;
        border: none;
        padding: 6px 16px;
        border-radius: 8px;
        font-weight: 700;
        font-size: 0.7rem;
        cursor: pointer;
        transition: all 0.3s;
        font-family: inherit;
    }
    .filter-bar-rec .btn-filter-rec:hover {
        transform: scale(1.03);
        box-shadow: 0 4px 16px rgba(10, 76, 168, 0.35);
    }
    .filter-bar-rec .btn-reset-rec {
        background: transparent;
        color: var(--rec-text-secondary);
        border: 2px solid var(--rec-border-color);
        padding: 6px 16px;
        border-radius: 8px;
        font-weight: 600;
        font-size: 0.7rem;
        cursor: pointer;
        transition: all 0.3s;
        text-decoration: none;
    }
    .filter-bar-rec .btn-reset-rec:hover {
        border-color: var(--rec-primary);
        color: var(--rec-primary);
    }

    /* RECEPTION CARDS */
    .reception-grid-rec {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
        gap: 20px;
    }
    .reception-card-rec {
        background: var(--rec-bg-card);
        border-radius: var(--rec-radius-lg);
        border: 2px solid var(--rec-border-color);
        overflow: hidden;
        transition: all 0.3s ease;
        box-shadow: var(--rec-shadow-sm);
        position: relative;
    }
    .reception-card-rec:hover {
        transform: translateY(-5px);
        box-shadow: 0 12px 35px rgba(11, 94, 215, 0.15);
        border-color: var(--rec-primary);
    }
    .reception-card-rec .card-top-rec {
        padding: 16px 18px 12px 18px;
        border-bottom: 2px solid var(--rec-border-color);
        display: flex;
        justify-content: space-between;
        align-items: start;
        background: var(--rec-primary-gradient-strong);
        position: relative;
        overflow: hidden;
    }
    .reception-card-rec .card-top-rec::after {
        content: '';
        position: absolute;
        top: -50%; right: -20%;
        width: 200px; height: 200px;
        background: rgba(255,255,255,0.05);
        border-radius: 50%;
        pointer-events: none;
    }
    .reception-card-rec .card-top-rec .name-rec {
        font-size: 1.05rem;
        font-weight: 800;
        color: white;
        position: relative;
        z-index: 1;
    }
    .reception-card-rec .card-top-rec .name-rec i {
        color: rgba(255,255,255,0.85);
        margin-right: 8px;
    }
    .reception-card-rec .card-top-rec .location-text-rec {
        font-size: 0.7rem;
        color: rgba(255,255,255,0.75);
        margin-top: 2px;
        display: flex;
        align-items: center;
        gap: 4px;
        position: relative;
        z-index: 1;
    }
    .reception-card-rec .card-top-rec .location-text-rec i {
        color: rgba(255,255,255,0.6);
        font-size: 0.65rem;
    }
    .reception-card-rec .card-top-rec .status-badge-rec {
        padding: 4px 14px;
        border-radius: 20px;
        font-size: 0.6rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        position: relative;
        z-index: 1;
        backdrop-filter: blur(4px);
    }
    .reception-card-rec .card-top-rec .status-badge-rec.active {
        background: rgba(255,255,255,0.2);
        color: white;
        border: 1px solid rgba(255,255,255,0.3);
    }
    .reception-card-rec .card-top-rec .status-badge-rec.inactive {
        background: rgba(220, 38, 38, 0.3);
        color: #FCA5A5;
        border: 1px solid rgba(220, 38, 38, 0.3);
    }
    .reception-card-rec .card-body-rec { padding: 14px 18px 16px 18px; }
    .reception-card-rec .card-body-rec .info-row-rec {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 0.75rem;
        color: var(--rec-text-secondary);
        padding: 4px 0;
    }
    .reception-card-rec .card-body-rec .info-row-rec i {
        width: 18px;
        color: var(--rec-primary);
        font-size: 0.75rem;
    }
    .reception-card-rec .card-stats-rec {
        display: grid;
        grid-template-columns: repeat(5, 1fr);
        gap: 4px;
        padding: 10px 14px;
        background: var(--rec-bg-body);
        border-top: 2px solid var(--rec-border-color);
        border-bottom: 2px solid var(--rec-border-color);
    }
    [data-theme="dark"] .reception-card-rec .card-stats-rec { background: #0F172A; }
    .reception-card-rec .card-stats-rec .stat-item-rec {
        text-align: center;
        padding: 6px 2px;
        border-radius: 8px;
        transition: all 0.3s ease;
    }
    .reception-card-rec .card-stats-rec .stat-item-rec:hover {
        background: var(--rec-primary-bg);
        transform: scale(1.05);
    }
    [data-theme="dark"] .reception-card-rec .card-stats-rec .stat-item-rec:hover { background: #1E3A5F; }
    .reception-card-rec .card-stats-rec .stat-item-rec .num-rec {
        font-size: 1rem;
        font-weight: 800;
        color: var(--rec-text-primary);
        font-family: 'JetBrains Mono', monospace;
    }
    .reception-card-rec .card-stats-rec .stat-item-rec .num-rec.blue { color: var(--rec-primary); }
    .reception-card-rec .card-stats-rec .stat-item-rec .num-rec.green { color: #059669; }
    .reception-card-rec .card-stats-rec .stat-item-rec .num-rec.orange { color: #F59E0B; }
    .reception-card-rec .card-stats-rec .stat-item-rec .num-rec.purple { color: #7C3AED; }
    .reception-card-rec .card-stats-rec .stat-item-rec .num-rec.teal { color: #0D9488; }
    .reception-card-rec .card-stats-rec .stat-item-rec .label-rec {
        font-size: 0.5rem;
        color: var(--rec-text-secondary);
        text-transform: uppercase;
        letter-spacing: 0.04em;
        font-weight: 700;
    }
    .reception-card-rec .card-actions-rec {
        padding: 10px 18px;
        display: flex;
        gap: 8px;
        justify-content: flex-end;
        flex-wrap: wrap;
    }
    .reception-card-rec .card-actions-rec .btn-action-rec {
        padding: 6px 16px;
        border-radius: 8px;
        font-size: 0.68rem;
        font-weight: 700;
        text-decoration: none;
        transition: all 0.3s;
        border: 2px solid var(--rec-border-color);
        color: var(--rec-text-secondary);
        background: transparent;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 5px;
    }
    .reception-card-rec .card-actions-rec .btn-action-rec:hover {
        border-color: var(--rec-primary);
        color: var(--rec-primary);
        transform: translateY(-2px);
    }
    .reception-card-rec .card-actions-rec .btn-action-rec.primary {
        background: var(--rec-primary-gradient-strong);
        color: white;
        border-color: var(--rec-primary);
    }
    .reception-card-rec .card-actions-rec .btn-action-rec.primary:hover {
        background: var(--rec-primary-dark);
        border-color: var(--rec-primary-dark);
        transform: translateY(-2px);
        box-shadow: 0 4px 16px rgba(10, 76, 168, 0.35);
        color: white;
    }

    /* EMPTY STATE */
    .empty-state-rec {
        text-align: center;
        padding: 60px 20px;
        color: var(--rec-text-secondary);
    }
    .empty-state-rec i {
        font-size: 4rem;
        color: var(--rec-border-color);
        margin-bottom: 16px;
    }
    .empty-state-rec h3 {
        font-size: 1.3rem;
        color: var(--rec-text-primary);
        margin-bottom: 8px;
    }
    .empty-state-rec p { font-size: 0.9rem; }

    /* FOOTER */
    .footer-rec {
        padding: 14px 0;
        border-top: 2px solid var(--rec-border-color);
        margin-top: 24px;
        text-align: center;
        font-size: 0.7rem;
        color: var(--rec-text-secondary);
    }
    .footer-rec .footer-brand-rec { color: var(--rec-primary); font-weight: 700; }

    /* ANIMATIONS */
    @keyframes fadeInUp {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }
    .animate-fade-in-up-rec {
        animation: fadeInUp 0.5s ease forwards;
        opacity: 0;
    }

    /* RESPONSIVE */
    @media (max-width: 1200px) {
        .stats-grid-rec { grid-template-columns: repeat(3, 1fr); }
    }
    @media (max-width: 900px) {
        .stats-grid-rec { grid-template-columns: repeat(2, 1fr); }
    }
    @media (max-width: 768px) {
        .page-header-rec { padding: 16px 18px; }
        .page-header-rec .page-title-rec { font-size: 1.3rem; }
        .reception-grid-rec { grid-template-columns: 1fr; }
        .stats-grid-rec { grid-template-columns: 1fr 1fr; gap: 12px; }
        .stat-card-rec { padding: 16px 18px; gap: 12px; }
        .stat-card-rec .stat-icon-rec { width: 48px; height: 48px; font-size: 1.2rem; }
        .stat-card-rec .stat-value-rec { font-size: 1.4rem; }
        .filter-bar-rec { flex-direction: column; align-items: stretch; }
        .reception-card-rec .card-stats-rec { grid-template-columns: repeat(3, 1fr); }
    }
    @media (max-width: 480px) {
        .stats-grid-rec { grid-template-columns: 1fr; }
        .page-header-rec { flex-direction: column; align-items: flex-start !important; }
        .reception-card-rec .card-stats-rec { grid-template-columns: repeat(2, 1fr); }
    }

    @media print {
        .btn-filter-rec, .btn-reset-rec, .filter-bar-rec, .card-actions-rec { display: none !important; }
        .reception-card-rec { break-inside: avoid; box-shadow: none !important; border: 1px solid #ddd; }
        .page-header-rec {
            background: #0B5ED7 !important;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }
    }
</style>

<main class="main-content">

    <!-- Page Header -->
    <div class="page-header-rec animate-fade-in-up-rec">
        <div>
            <h1 class="page-title-rec">
                <i class="fas fa-headset"></i>
                Receptions
                <span class="role-badge-display-rec">ADMIN</span>
            </h1>
            <p class="page-subtitle-rec">
                <i class="fas fa-store-alt"></i>
                <strong><?= $total_receptions ?></strong> reception desks found
                <span class="header-badge-rec">
                    <i class="fas fa-users"></i> <?= number_format($total_patients) ?> Patients
                </span>
                <span class="header-badge-rec">
                    <i class="fas fa-user-check"></i> <?= number_format($assigned_patients) ?> Assigned
                </span>
                <span class="header-badge-rec">
                    <i class="fas fa-clipboard-list"></i> <?= number_format($total_visits) ?> Visits
                </span>
                <span class="header-badge-rec">
                    <i class="fas fa-calendar-check"></i> <?= number_format($total_appointments) ?> Appointments
                </span>
                <span class="header-badge-rec">
                    <i class="fas fa-clock"></i> <?= number_format($pending_visits) ?> Pending
                </span>
            </p>
        </div>
    </div>

    <!-- Error Message -->
    <?php if (!empty($error_msg)): ?>
        <div class="alert-rec alert-warning-rec">
            <i class="fas fa-exclamation-triangle"></i>
            <?= htmlspecialchars($error_msg) ?>
        </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- STATS GRID - 3 JUU, 3 CHINI -->
    <!-- ================================================================ -->
    <div class="stats-grid-rec animate-fade-in-up-rec" style="animation-delay:0.05s;">

        <!-- ROW 1 -->
        <div class="stat-card-rec blue">
            <div class="stat-icon-rec"><i class="fas fa-headset"></i></div>
            <div class="stat-content-rec">
                <p class="stat-label-rec">Total Receptions</p>
                <p class="stat-value-rec"><?= $total_receptions ?></p>
                <p class="stat-sub-rec"><i class="fas fa-check-circle up"></i> All active branches</p>
            </div>
        </div>

        <div class="stat-card-rec green">
            <div class="stat-icon-rec"><i class="fas fa-users"></i></div>
            <div class="stat-content-rec">
                <p class="stat-label-rec">Total Patients</p>
                <p class="stat-value-rec"><?= number_format($total_patients) ?></p>
                <p class="stat-sub-rec"><i class="fas fa-arrow-up up"></i> Registered patients</p>
            </div>
        </div>

        <div class="stat-card-rec teal">
            <div class="stat-icon-rec"><i class="fas fa-user-check"></i></div>
            <div class="stat-content-rec">
                <p class="stat-label-rec">Assigned Patients</p>
                <p class="stat-value-rec"><?= number_format($assigned_patients) ?></p>
                <p class="stat-sub-rec"><i class="fas fa-user-md up"></i> With doctor assigned</p>
            </div>
        </div>

        <!-- ROW 2 -->
        <div class="stat-card-rec purple">
            <div class="stat-icon-rec"><i class="fas fa-clipboard-list"></i></div>
            <div class="stat-content-rec">
                <p class="stat-label-rec">Total Visits</p>
                <p class="stat-value-rec"><?= number_format($total_visits) ?></p>
                <p class="stat-sub-rec"><i class="fas fa-calendar neutral"></i> All time visits</p>
            </div>
        </div>

        <div class="stat-card-rec indigo">
            <div class="stat-icon-rec"><i class="fas fa-calendar-check"></i></div>
            <div class="stat-content-rec">
                <p class="stat-label-rec">Total Appointments</p>
                <p class="stat-value-rec"><?= number_format($total_appointments) ?></p>
                <p class="stat-sub-rec"><i class="fas fa-clock neutral"></i> Scheduled & confirmed</p>
            </div>
        </div>

        <div class="stat-card-rec orange">
            <div class="stat-icon-rec"><i class="fas fa-clock"></i></div>
            <div class="stat-content-rec">
                <p class="stat-label-rec">Pending Visits</p>
                <p class="stat-value-rec"><?= number_format($pending_visits) ?></p>
                <p class="stat-sub-rec">
                    <?php if ($pending_visits > 0): ?>
                        <i class="fas fa-exclamation-triangle warn"></i> Needs attention
                    <?php else: ?>
                        <i class="fas fa-check-circle up"></i> All clear
                    <?php endif; ?>
                </p>
            </div>
        </div>

    </div>

    <!-- ================================================================ -->
    <!-- FILTER BAR -->
    <!-- ================================================================ -->
    <div class="filter-bar-rec animate-fade-in-up-rec" style="animation-delay:0.1s;">
        <span class="filter-label-rec"><i class="fas fa-filter"></i> Filter</span>
        
        <form method="GET" action="" style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;flex:1;">
            <select name="branch" style="flex:1;min-width:120px;">
                <option value="all" <?= $selected_branch_id === 'all' ? 'selected' : '' ?>>🌐 All Branches</option>
                <?php foreach ($branches as $b): ?>
                    <option value="<?= $b['id'] ?>" <?= $selected_branch_id == $b['id'] ? 'selected' : '' ?>>
                        🏥 <?= htmlspecialchars($b['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            
            <select name="status" style="flex:1;min-width:120px;">
                <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>All Status</option>
                <option value="active" <?= $status_filter === 'active' ? 'selected' : '' ?>>Active</option>
                <option value="inactive" <?= $status_filter === 'inactive' ? 'selected' : '' ?>>Inactive</option>
            </select>
            
            <input type="text" name="search" placeholder="Search by name, location..." 
                   value="<?= htmlspecialchars($search) ?>" style="flex:1;min-width:180px;">
            
            <button type="submit" class="btn-filter-rec">
                <i class="fas fa-search"></i> Apply
            </button>
            
            <a href="receptions.php" class="btn-reset-rec">
                <i class="fas fa-times"></i> Reset
            </a>
        </form>
    </div>

    <!-- ================================================================ -->
    <!-- RECEPTION GRID -->
    <!-- ================================================================ -->
    <?php if (count($receptions) > 0): ?>
        <div class="reception-grid-rec animate-fade-in-up-rec" style="animation-delay:0.15s;">
            <?php foreach ($receptions as $reception): ?>
                <div class="reception-card-rec">
                    <div class="card-top-rec">
                        <div>
                            <div class="name-rec">
                                <i class="fas fa-headset"></i>
                                <?= htmlspecialchars($reception['name']) ?>
                            </div>
                            <div class="location-text-rec">
                                <i class="fas fa-map-marker-alt"></i>
                                <?= htmlspecialchars($reception['location'] ?? 'N/A') ?>
                            </div>
                        </div>
                        <span class="status-badge-rec <?= ($reception['status'] ?? 'active') === 'active' ? 'active' : 'inactive' ?>">
                            <?= ($reception['status'] ?? 'active') === 'active' ? 'Active' : 'Inactive' ?>
                        </span>
                    </div>
                    
                    <div class="card-body-rec">
                        <div class="info-row-rec">
                            <i class="fas fa-phone"></i>
                            <?= htmlspecialchars($reception['phone'] ?? 'N/A') ?>
                        </div>
                        <div class="info-row-rec">
                            <i class="fas fa-envelope"></i>
                            <?= htmlspecialchars($reception['email'] ?? 'N/A') ?>
                        </div>
                        <div class="info-row-rec">
                            <i class="fas fa-user-tie"></i>
                            <?= ($reception['active_receptionists'] ?? 0) ?> Active / <?= ($reception['total_receptionists'] ?? 0) ?> Receptionists
                        </div>
                        <div class="info-row-rec">
                            <i class="fas fa-calendar-plus"></i>
                            Created: <?= date('M d, Y', strtotime($reception['created_at'] ?? 'now')) ?>
                        </div>
                    </div>
                    
                    <div class="card-stats-rec">
                        <div class="stat-item-rec">
                            <div class="num-rec blue"><?= number_format($reception['total_patients'] ?? 0) ?></div>
                            <div class="label-rec">Patients</div>
                        </div>
                        <div class="stat-item-rec">
                            <div class="num-rec teal"><?= number_format($reception['assigned_patients'] ?? 0) ?></div>
                            <div class="label-rec">Assigned</div>
                        </div>
                        <div class="stat-item-rec">
                            <div class="num-rec green"><?= number_format($reception['total_visits'] ?? 0) ?></div>
                            <div class="label-rec">Visits</div>
                        </div>
                        <div class="stat-item-rec">
                            <div class="num-rec purple"><?= number_format(($reception['scheduled_appointments'] ?? 0) + ($reception['confirmed_appointments'] ?? 0)) ?></div>
                            <div class="label-rec">Appts</div>
                        </div>
                        <div class="stat-item-rec">
                            <div class="num-rec <?= ($reception['pending_visits'] ?? 0) > 0 ? 'orange' : 'green' ?>">
                                <?= number_format($reception['pending_visits'] ?? 0) ?>
                            </div>
                            <div class="label-rec">Pending</div>
                        </div>
                    </div>
                    
                    <div class="card-actions-rec">
                        <a href="view_reception.php?id=<?= $reception['id'] ?>&branch=<?= urlencode($selected_branch_id) ?>" 
                           class="btn-action-rec primary">
                            <i class="fas fa-eye"></i> View Reception
                        </a>
                        <a href="edit_reception.php?id=<?= $reception['id'] ?>&branch=<?= urlencode($selected_branch_id) ?>" 
                           class="btn-action-rec">
                            <i class="fas fa-edit"></i> Edit Reception
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="empty-state-rec animate-fade-in-up-rec" style="animation-delay:0.15s;">
            <i class="fas fa-headset"></i>
            <h3>No Receptions Found</h3>
            <p>Try adjusting your filters</p>
        </div>
    <?php endif; ?>

    <footer class="footer-rec">
        <p>
            <span class="footer-brand-rec">Braick Dispensary</span> Management System
            <span style="color:#CBD5E1;margin:0 8px;">|</span>
            Receptions
            <span style="color:#CBD5E1;margin:0 8px;">|</span>
            <span id="footerTime"><?= date('H:i:s') ?></span>
            <span style="color:#CBD5E1;margin:0 8px;">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<script>
    setInterval(function() {
        var now = new Date();
        var timeStr = now.toLocaleTimeString('en-US', {
            hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true
        });
        var ftEl = document.getElementById('footerTime');
        if (ftEl) ftEl.textContent = timeStr;
    }, 1000);

    console.log('%c📋 Braick Dispensary - Receptions V3', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c✅ View Reception + Edit Reception', 'font-size:13px; color:#34D399;');
    console.log('%c✅ Stats Cards 3 juu, 3 chini', 'font-size:13px; color:#34D399;');
</script>

</body>
</html>