<?php
// ================================================================
// FILE: frontend/pages/admin/visits.php
// ADMIN - VIEW ALL VISITS
// ✅ Uses SHARED header & sidebar
// ✅ Full dark mode support
// ✅ Blue page header card
// ✅ 7 Stats cards
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

// RECOVER FROM DATABASE IF NEEDED
if ($user_id <= 0 && !empty($username)) {
    require_once __DIR__ . '/../../../backend/config/database.php';
    try {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT id, full_name, role, branch_id, profile_pic FROM users WHERE username = ? AND status = 'active'");
        $stmt->execute([$username]);
        $u = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($u) {
            $_SESSION['user_id'] = $u['id'];
            $_SESSION['full_name'] = $u['full_name'];
            $_SESSION['role'] = $u['role'];
            $_SESSION['branch_id'] = $u['branch_id'];
            $_SESSION['profile_pic'] = $u['profile_pic'];
            $user_id = $u['id'];
            $user_full_name = $u['full_name'];
            $user_role = $u['role'];
            $user_branch_id = $u['branch_id'];
            $profile_pic = $u['profile_pic'];
        }
    } catch (Exception $e) {}
}

if ($user_id <= 0) {
    header('Location: ../login.php');
    exit;
}

require_once '../../../backend/config/database.php';
require_once '../../../backend/helpers/functions.php';

$db = Database::getInstance()->getConnection();

// UNREAD NOTIFICATIONS
$unread_notifications = 0;
try {
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$user_id]);
    $unread_notifications = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
} catch (Exception $e) {
    $unread_notifications = 0;
}

// PARAMETERS
$branch_id = isset($_GET['branch_id']) ? (int)$_GET['branch_id'] : ($_SESSION['branch_id'] ?? 1);
$status_filter = $_GET['status'] ?? 'all';
$search = $_GET['search'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

function branchExists($db, $branch_id) {
    try {
        $stmt = $db->prepare("SELECT id FROM branches WHERE id = ?");
        $stmt->execute([$branch_id]);
        return $stmt->fetch() ? true : false;
    } catch (Exception $e) {
        return false;
    }
}

if (!branchExists($db, $branch_id)) {
    $branch_id = 1;
}

// BUILD VISITS QUERY
$sql = "
    SELECT 
        v.*,
        p.full_name as patient_name,
        p.patient_id as patient_number,
        p.phone as patient_phone,
        u.full_name as doctor_name,
        u.specialty as doctor_specialty,
        r.full_name as receptionist_name,
        b.name as branch_name,
        (SELECT COUNT(*) FROM prescriptions WHERE visit_id = v.id) as prescription_count,
        (SELECT COUNT(*) FROM lab_tests WHERE visit_id = v.id) as lab_test_count,
        (SELECT COUNT(*) FROM bills WHERE visit_id = v.id AND status = 'paid') as paid_bill_count,
        (SELECT COUNT(*) FROM bills WHERE visit_id = v.id AND status = 'pending') as pending_bill_count,
        (SELECT COUNT(*) FROM bills WHERE visit_id = v.id AND status = 'partial') as partial_bill_count
    FROM visits v
    LEFT JOIN patients p ON v.patient_id = p.id
    LEFT JOIN users u ON v.doctor_id = u.id
    LEFT JOIN users r ON v.receptionist_id = r.id
    LEFT JOIN branches b ON v.branch_id = b.id
    WHERE v.branch_id = ?
";

$params = [$branch_id];

if ($status_filter !== 'all') {
    $sql .= " AND v.status = ?";
    $params[] = $status_filter;
}

if (!empty($search)) {
    $sql .= " AND (p.full_name LIKE ? OR p.patient_id LIKE ? OR v.visit_number LIKE ? OR p.phone LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($date_from)) {
    $sql .= " AND DATE(v.visit_date) >= ?";
    $params[] = $date_from;
}

if (!empty($date_to)) {
    $sql .= " AND DATE(v.visit_date) <= ?";
    $params[] = $date_to;
}

$sql .= " ORDER BY v.visit_date DESC LIMIT 100";

$visits = [];
try {
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $visits = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $visits = [];
}

// STATISTICS
$total_visits = count($visits);
$pending_visits = 0;
$assigned_visits = 0;
$with_doctor_visits = 0;
$lab_test_visits = 0;
$completed_visits = 0;
$cancelled_visits = 0;
$total_revenue = 0;

foreach ($visits as $visit) {
    $status = $visit['status'] ?? 'pending';
    switch ($status) {
        case 'pending': $pending_visits++; break;
        case 'assigned': $assigned_visits++; break;
        case 'with_doctor': $with_doctor_visits++; break;
        case 'lab_test': $lab_test_visits++; break;
        case 'completed': $completed_visits++; break;
        case 'cancelled': $cancelled_visits++; break;
    }
    $total_revenue += ($visit['visit_total'] ?? 0) - ($visit['total_discount'] ?? 0);
}

// BRANCHES
$branches = [];
try {
    $stmt = $db->query("SELECT id, name FROM branches WHERE status = 'active' ORDER BY name");
    $branches = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $branches = [];
}

// BRANCH NAME
$branch_name = 'Unknown';
try {
    $stmt = $db->prepare("SELECT name FROM branches WHERE id = ?");
    $stmt->execute([$branch_id]);
    $result = $stmt->fetch();
    if ($result) $branch_name = $result['name'];
} catch (Exception $e) {}

function getStatusBadge($status) {
    $status = $status ?? 'pending';
    $classes = [
        'pending' => 'warning', 'assigned' => 'info', 'with_doctor' => 'primary',
        'lab_test' => 'purple', 'lab_completed' => 'info', 'prescribed' => 'info',
        'completed' => 'success', 'cancelled' => 'danger', 'active' => 'success',
        'inactive' => 'danger', 'dispensed' => 'success', 'confirmed' => 'info',
        'paid' => 'success', 'partial' => 'warning', 'scheduled' => 'info',
        'new' => 'info', 'follow-up' => 'warning', 'emergency' => 'danger'
    ];
    return $classes[$status] ?? 'secondary';
}

function getStatusIcon($status) {
    $status = $status ?? 'pending';
    $icons = [
        'pending' => 'fa-clock', 'assigned' => 'fa-user-check', 'with_doctor' => 'fa-stethoscope',
        'lab_test' => 'fa-flask', 'lab_completed' => 'fa-check-double', 'prescribed' => 'fa-prescription',
        'completed' => 'fa-check-circle', 'cancelled' => 'fa-times-circle', 'active' => 'fa-check-circle',
        'inactive' => 'fa-times-circle', 'dispensed' => 'fa-check-circle', 'confirmed' => 'fa-check-double',
        'paid' => 'fa-check-circle', 'partial' => 'fa-clock', 'scheduled' => 'fa-calendar-check',
        'new' => 'fa-user-plus', 'follow-up' => 'fa-user-check', 'emergency' => 'fa-ambulance'
    ];
    return $icons[$status] ?? 'fa-circle';
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
        --page-bg-body: #F0F4F8;
        --page-bg-card: #FFFFFF;
        --page-text-primary: #1E293B;
        --page-text-secondary: #64748B;
        --page-text-muted: #94A3B8;
        --page-border: #E2E8F0;
        --page-hover: #F8FAFC;
        --page-primary: #0B5ED7;
    }

    [data-theme="dark"] {
        --page-bg-body: #0F172A;
        --page-bg-card: #1E293B;
        --page-text-primary: #F1F5F9;
        --page-text-secondary: #94A3B8;
        --page-text-muted: #64748B;
        --page-border: #334155;
        --page-hover: #1E3A5F;
        --page-primary: #3B82F6;
    }

    body { background: var(--page-bg-body, #F0F4F8); }
    html[data-theme="dark"] body { background: #0F172A !important; }
    .main-content { background: var(--page-bg-body, #F0F4F8); }
    html[data-theme="dark"] .main-content { background: #0F172A !important; color: #F1F5F9; }

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
        box-shadow: 0 8px 32px rgba(11, 94, 215, 0.35);
        position: relative;
        overflow: hidden;
    }

    .page-header-custom::before {
        content: '';
        position: absolute;
        top: -60%; right: -10%;
        width: 400px; height: 400px;
        background: radial-gradient(circle, rgba(255,255,255,0.12) 0%, transparent 70%);
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
    }

    .page-header-custom .header-badge {
        background: rgba(255,255,255,0.15);
        color: white;
        padding: 6px 14px;
        border-radius: 20px;
        font-size: 0.72rem;
        font-weight: 600;
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

    .page-header-custom .header-badge.yellow-badge {
        background: rgba(251,191,36,0.25);
        border-color: rgba(251,191,36,0.4);
        color: #FDE68A;
    }

    .page-header-custom .btn-outline-light {
        background: rgba(255,255,255,0.15);
        color: white;
        border: 1.5px solid rgba(255,255,255,0.3);
        padding: 8px 18px;
        border-radius: 10px;
        font-weight: 600;
        font-size: 0.82rem;
        transition: all 0.3s;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        position: relative;
        z-index: 1;
    }

    .page-header-custom .btn-outline-light:hover {
        background: rgba(255,255,255,0.25);
        transform: translateY(-2px);
        color: white;
    }

    /* STATS CARDS - 7 */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(7, 1fr);
        gap: 16px;
        margin-bottom: 24px;
    }

    .stat-card-custom {
        background: var(--page-bg-card, #FFFFFF);
        border-radius: 14px;
        padding: 16px 18px;
        border: 2px solid var(--page-border, #E2E8F0);
        box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
        text-decoration: none;
        color: var(--page-text-primary, #1E293B);
        display: flex;
        align-items: center;
        gap: 12px;
    }

    html[data-theme="dark"] .stat-card-custom {
        background: #1E293B;
        border-color: #334155;
        color: #F1F5F9;
    }

    .stat-card-custom::before {
        content: '';
        position: absolute;
        top: 0; left: 0;
        width: 4px;
        height: 100%;
        background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
        border-radius: 0 3px 3px 0;
        opacity: 0.9;
    }

    .stat-card-custom:hover {
        border-color: #0B5ED7;
        transform: translateY(-4px);
        box-shadow: 0 8px 25px rgba(11, 94, 215, 0.15);
    }

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
    .stat-card-custom .stat-number.red { color: #DC2626; }

    .stat-card-custom .stat-label {
        font-size: 0.6rem;
        color: var(--page-text-secondary, #64748B);
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        margin: 0;
    }

    .stat-card-custom .stat-icon-small {
        width: 36px;
        height: 36px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.9rem;
        flex-shrink: 0;
    }

    .stat-card-custom .stat-icon-small.blue { background: #E8F0FE; color: #0B5ED7; }
    .stat-card-custom .stat-icon-small.green { background: #ECFDF5; color: #059669; }
    .stat-card-custom .stat-icon-small.orange { background: #FFFBEB; color: #F59E0B; }
    .stat-card-custom .stat-icon-small.purple { background: #F5F3FF; color: #7C3AED; }
    .stat-card-custom .stat-icon-small.teal { background: #ECFDF5; color: #0D9488; }
    .stat-card-custom .stat-icon-small.red { background: #FEF2F2; color: #DC2626; }

    html[data-theme="dark"] .stat-card-custom .stat-icon-small.blue { background: #1E3A5F; color: #6EA8FE; }
    html[data-theme="dark"] .stat-card-custom .stat-icon-small.green { background: #1A3A2A; color: #34D399; }
    html[data-theme="dark"] .stat-card-custom .stat-icon-small.orange { background: #3A2A1A; color: #FBBF24; }
    html[data-theme="dark"] .stat-card-custom .stat-icon-small.purple { background: #2D1B4E; color: #A78BFA; }
    html[data-theme="dark"] .stat-card-custom .stat-icon-small.teal { background: #0F3D3D; color: #5EEAD4; }
    html[data-theme="dark"] .stat-card-custom .stat-icon-small.red { background: #3A1A1A; color: #F87171; }

    /* FILTER BAR */
    .filter-bar-custom {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
        margin-bottom: 20px;
        align-items: center;
        background: var(--page-bg-card, #FFFFFF);
        padding: 16px 20px;
        border-radius: 12px;
        border: 2px solid var(--page-border, #E2E8F0);
        box-shadow: 0 2px 8px rgba(0,0,0,0.04);
    }

    html[data-theme="dark"] .filter-bar-custom {
        background: #1E293B;
        border-color: #334155;
    }

    .filter-bar-custom select,
    .filter-bar-custom input {
        background: var(--page-bg-body, #F0F4F8);
        border: 2px solid var(--page-border, #E2E8F0);
        border-radius: 8px;
        padding: 8px 14px;
        font-size: 0.8rem;
        color: var(--page-text-primary, #1E293B);
        outline: none;
        transition: all 0.3s;
        min-width: 150px;
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
        box-shadow: 0 0 0 4px rgba(11, 94, 215, 0.1);
    }

    .btn-custom {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 8px 18px;
        border-radius: 8px;
        font-weight: 700;
        font-size: 0.8rem;
        transition: all 0.3s ease;
        cursor: pointer;
        border: none;
        text-decoration: none;
        white-space: nowrap;
    }

    .btn-primary-custom {
        background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
        color: white;
    }
    .btn-primary-custom:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3);
        color: white;
    }

    .btn-outline-custom {
        background: transparent;
        color: var(--page-text-secondary, #64748B);
        border: 2px solid var(--page-border, #E2E8F0);
    }
    .btn-outline-custom:hover {
        background: var(--page-hover, #F1F5F9);
        border-color: #0B5ED7;
        color: #0B5ED7;
    }

    /* TABLE */
    .table-container-custom {
        background: var(--page-bg-card, #FFFFFF);
        border-radius: 16px;
        border: 2px solid var(--page-border, #E2E8F0);
        overflow: hidden;
        box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        margin-bottom: 24px;
    }

    html[data-theme="dark"] .table-container-custom {
        background: #1E293B;
        border-color: #334155;
    }

    .table-container-custom .card-header-custom {
        padding: 16px 20px;
        background: linear-gradient(135deg, #0A4CA8, #083D8A);
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 8px;
    }

    .table-container-custom .card-header-custom .card-title-custom {
        font-size: 0.9rem;
        font-weight: 700;
        color: white;
        margin: 0;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .data-table-custom {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.8rem;
    }

    .data-table-custom thead th {
        background: var(--page-bg-body, #F0F4F8);
        color: var(--page-text-secondary, #64748B);
        font-weight: 700;
        padding: 12px 14px;
        font-size: 0.65rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        border-bottom: 2px solid var(--page-border, #E2E8F0);
        text-align: left;
        white-space: nowrap;
    }

    html[data-theme="dark"] .data-table-custom thead th {
        background: #0F172A;
        border-bottom-color: #334155;
    }

    .data-table-custom td {
        padding: 12px 14px;
        border-bottom: 1px solid var(--page-border, #E2E8F0);
        color: var(--page-text-primary, #1E293B);
        vertical-align: middle;
    }

    html[data-theme="dark"] .data-table-custom td {
        color: #F1F5F9;
        border-bottom-color: #334155;
    }

    .data-table-custom tbody tr:nth-child(even) td {
        background: var(--page-hover, #F8FAFC);
    }

    html[data-theme="dark"] .data-table-custom tbody tr:nth-child(even) td {
        background: #1E3A5F;
    }

    .data-table-custom tbody tr:hover td {
        background: #E8F0FE;
    }

    html[data-theme="dark"] .data-table-custom tbody tr:hover td {
        background: #1E40AF;
    }

    /* BADGES */
    .badge-custom {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 3px 12px;
        border-radius: 20px;
        font-size: 0.62rem;
        font-weight: 700;
        color: white;
    }

    .badge-success { background: #059669; }
    .badge-danger { background: #DC2626; }
    .badge-warning { background: #D97706; }
    .badge-info { background: #0B5ED7; }
    .badge-secondary { background: #64748B; }
    .badge-purple { background: #7C3AED; }
    .badge-primary { background: #0B5ED7; }
    .badge-teal { background: #0D9488; }

    /* ACTION LINKS */
    .action-link {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        font-size: 0.7rem;
        padding: 3px 10px;
        border-radius: 12px;
        transition: all 0.3s ease;
        text-decoration: none;
        font-weight: 700;
    }

    .action-link.view {
        background: #E8F0FE;
        color: #0B5ED7;
    }
    .action-link.view:hover {
        background: #0B5ED7;
        color: white;
    }

    .action-link.assign {
        background: #ECFDF5;
        color: #059669;
    }
    .action-link.assign:hover {
        background: #059669;
        color: white;
    }

    .action-link.bill {
        background: #F5F3FF;
        color: #7C3AED;
    }
    .action-link.bill:hover {
        background: #7C3AED;
        color: white;
    }

    html[data-theme="dark"] .action-link.view { background: #1E3A5F; color: #6EA8FE; }
    html[data-theme="dark"] .action-link.view:hover { background: #0B5ED7; color: white; }

    html[data-theme="dark"] .action-link.assign { background: #1A3A2A; color: #34D399; }
    html[data-theme="dark"] .action-link.assign:hover { background: #059669; color: white; }

    html[data-theme="dark"] .action-link.bill { background: #2D1B4E; color: #A78BFA; }
    html[data-theme="dark"] .action-link.bill:hover { background: #7C3AED; color: white; }

    .action-divider { color: var(--page-border, #E2E8F0); font-size: 0.6rem; }

    /* EMPTY STATE */
    .empty-state-custom {
        text-align: center;
        padding: 40px 20px;
        color: var(--page-text-secondary, #64748B);
    }

    .empty-state-custom i {
        font-size: 2.5rem;
        color: var(--page-text-muted, #94A3B8);
        margin-bottom: 10px;
        display: block;
        opacity: 0.5;
    }

    /* RESPONSIVE */
    @media (max-width: 1200px) {
        .stats-grid { grid-template-columns: repeat(4, 1fr); }
    }

    @media (max-width: 1024px) {
        .stats-grid { grid-template-columns: repeat(3, 1fr); }
    }

    @media (max-width: 768px) {
        .page-header-custom { padding: 18px; }
        .page-header-custom .page-title { font-size: 1.15rem; }
        .stats-grid { grid-template-columns: repeat(2, 1fr); }
        .filter-bar-custom { flex-direction: column; align-items: stretch; }
        .filter-bar-custom select, .filter-bar-custom input { width: 100%; min-width: unset; }
        .data-table-custom { font-size: 0.7rem; }
        .data-table-custom td, .data-table-custom th { padding: 8px 10px; }
    }

    @media (max-width: 480px) {
        .stats-grid { grid-template-columns: 1fr; }
    }

    @media print {
        .page-header-custom { background: #0B5ED7 !important; -webkit-print-color-adjust: exact; }
        .btn-custom, .filter-bar-custom { display: none !important; }
    }
</style>

<main class="main-content">

    <!-- PAGE HEADER -->
    <div class="page-header-custom">
        <div style="position:relative;z-index:1;">
            <h1 class="page-title">
                <i class="fas fa-hospital-user"></i>
                Patient Visits
                <span class="role-badge-display">ADMIN</span>
            </h1>
            <p class="page-subtitle">
                <span><i class="fas fa-store-alt"></i> <strong><?= htmlspecialchars($branch_name) ?></strong></span>
                <span class="header-badge">
                    <i class="fas fa-calendar-day"></i> <?= date('M d, Y') ?>
                </span>
                <span class="header-badge green-badge">
                    <i class="fas fa-users"></i> <?= number_format($total_visits) ?> Visits
                </span>
                <span class="header-badge yellow-badge">
                    <i class="fas fa-money-bill-wave"></i> TSh <?= number_format($total_revenue, 0) ?> Revenue
                </span>
            </p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;position:relative;z-index:1;">
            <a href="dashboard.php" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>

    <!-- STATS CARDS - 7 -->
    <div class="stats-grid">
        <div class="stat-card-custom">
            <div class="stat-icon-small blue"><i class="fas fa-users"></i></div>
            <div>
                <p class="stat-label">Total Visits</p>
                <p class="stat-number blue"><?= number_format($total_visits) ?></p>
            </div>
        </div>
        <div class="stat-card-custom">
            <div class="stat-icon-small orange"><i class="fas fa-clock"></i></div>
            <div>
                <p class="stat-label">Pending</p>
                <p class="stat-number orange"><?= number_format($pending_visits) ?></p>
            </div>
        </div>
        <div class="stat-card-custom">
            <div class="stat-icon-small purple"><i class="fas fa-user-check"></i></div>
            <div>
                <p class="stat-label">Assigned</p>
                <p class="stat-number purple"><?= number_format($assigned_visits) ?></p>
            </div>
        </div>
        <div class="stat-card-custom">
            <div class="stat-icon-small teal"><i class="fas fa-stethoscope"></i></div>
            <div>
                <p class="stat-label">With Doctor</p>
                <p class="stat-number teal"><?= number_format($with_doctor_visits) ?></p>
            </div>
        </div>
        <div class="stat-card-custom">
            <div class="stat-icon-small purple"><i class="fas fa-flask"></i></div>
            <div>
                <p class="stat-label">Lab Test</p>
                <p class="stat-number purple"><?= number_format($lab_test_visits) ?></p>
            </div>
        </div>
        <div class="stat-card-custom">
            <div class="stat-icon-small green"><i class="fas fa-check-circle"></i></div>
            <div>
                <p class="stat-label">Completed</p>
                <p class="stat-number green"><?= number_format($completed_visits) ?></p>
            </div>
        </div>
        <div class="stat-card-custom">
            <div class="stat-icon-small red"><i class="fas fa-times-circle"></i></div>
            <div>
                <p class="stat-label">Cancelled</p>
                <p class="stat-number red"><?= number_format($cancelled_visits) ?></p>
            </div>
        </div>
    </div>

    <!-- FILTER BAR -->
    <div class="filter-bar-custom">
        <form method="GET" style="display:flex;flex-wrap:wrap;gap:12px;align-items:center;width:100%;">
            <input type="hidden" name="branch_id" value="<?= $branch_id ?>">
            
            <select name="status" onchange="this.form.submit()" style="flex:1;min-width:150px;">
                <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>📋 All Status</option>
                <option value="pending" <?= $status_filter === 'pending' ? 'selected' : '' ?>>⏳ Pending</option>
                <option value="assigned" <?= $status_filter === 'assigned' ? 'selected' : '' ?>>👨‍⚕️ Assigned</option>
                <option value="with_doctor" <?= $status_filter === 'with_doctor' ? 'selected' : '' ?>>🩺 With Doctor</option>
                <option value="lab_test" <?= $status_filter === 'lab_test' ? 'selected' : '' ?>>🧪 Lab Test</option>
                <option value="lab_completed" <?= $status_filter === 'lab_completed' ? 'selected' : '' ?>>✅ Lab Completed</option>
                <option value="prescribed" <?= $status_filter === 'prescribed' ? 'selected' : '' ?>>💊 Prescribed</option>
                <option value="completed" <?= $status_filter === 'completed' ? 'selected' : '' ?>>✅ Completed</option>
                <option value="cancelled" <?= $status_filter === 'cancelled' ? 'selected' : '' ?>>❌ Cancelled</option>
            </select>
            
            <input type="date" name="date_from" value="<?= htmlspecialchars($date_from) ?>" style="flex:1;min-width:150px;">
            <span style="color:var(--page-text-muted);font-size:0.85rem;">→</span>
            <input type="date" name="date_to" value="<?= htmlspecialchars($date_to) ?>" style="flex:1;min-width:150px;">
            
            <input type="text" name="search" placeholder="🔍 Search patient, ID or visit..." value="<?= htmlspecialchars($search) ?>" style="flex:1;min-width:200px;">
            
            <button type="submit" class="btn-custom btn-primary-custom">
                <i class="fas fa-search"></i> Filter
            </button>
            
            <a href="visits.php?branch_id=<?= $branch_id ?>" class="btn-custom btn-outline-custom">
                <i class="fas fa-times"></i> Clear
            </a>
        </form>
    </div>

    <!-- VISITS TABLE -->
    <div class="table-container-custom">
        <div class="card-header-custom">
            <h3 class="card-title-custom">
                <i class="fas fa-list"></i>
                Visit Records
                <span style="font-size:0.75rem;font-weight:400;color:rgba(255,255,255,0.85);">(<?= count($visits) ?> records)</span>
            </h3>
            <span style="font-size:0.72rem;color:rgba(255,255,255,0.85);">
                <i class="far fa-clock"></i> Showing latest 100 records
            </span>
        </div>
        <div style="overflow-x:auto;">
            <?php if (count($visits) > 0) { ?>
                <table class="data-table-custom">
                    <thead>
                        <tr>
                            <th><i class="fas fa-hashtag"></i> Visit #</th>
                            <th><i class="fas fa-user"></i> Patient</th>
                            <th><i class="fas fa-id-card"></i> Patient ID</th>
                            <th><i class="fas fa-user-md"></i> Doctor</th>
                            <th><i class="fas fa-calendar-alt"></i> Date</th>
                            <th><i class="fas fa-tag"></i> Type</th>
                            <th><i class="fas fa-circle"></i> Status</th>
                            <th><i class="fas fa-money-bill-wave"></i> Total</th>
                            <th><i class="fas fa-cogs"></i> Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($visits as $visit) { 
                            $status = $visit['status'] ?? 'pending';
                            $net_total = ($visit['visit_total'] ?? 0) - ($visit['total_discount'] ?? 0);
                            $has_pending_bills = ($visit['pending_bill_count'] ?? 0) > 0;
                            $has_paid_bills = ($visit['paid_bill_count'] ?? 0) > 0;
                            $has_partial_bills = ($visit['partial_bill_count'] ?? 0) > 0;
                            $bill_status = '';
                            if ($has_paid_bills && !$has_pending_bills && !$has_partial_bills) {
                                $bill_status = 'badge-success';
                            } elseif ($has_partial_bills) {
                                $bill_status = 'badge-warning';
                            } elseif ($has_pending_bills) {
                                $bill_status = 'badge-secondary';
                            }
                        ?>
                            <tr>
                                <td style="font-family:monospace;font-size:0.72rem;font-weight:700;">
                                    <?= htmlspecialchars($visit['visit_number'] ?? 'N/A') ?>
                                    <?php if ($bill_status) { ?>
                                        <br><span class="badge-custom <?= $bill_status ?>" style="font-size:0.55rem;">💰 <?= ucfirst(str_replace('badge-', '', $bill_status)) ?></span>
                                    <?php } ?>
                                </td>
                                <td style="font-weight:600;">
                                    <?= htmlspecialchars($visit['patient_name'] ?? 'N/A') ?>
                                    <?php if (!empty($visit['patient_phone'])) { ?>
                                        <span style="font-size:0.72rem;color:var(--page-text-muted);display:block;">📱 <?= htmlspecialchars($visit['patient_phone']) ?></span>
                                    <?php } ?>
                                </td>
                                <td style="font-family:monospace;font-size:0.72rem;">
                                    <?= htmlspecialchars($visit['patient_number'] ?? 'N/A') ?>
                                </td>
                                <td>
                                    <?php if (!empty($visit['doctor_name'])) { ?>
                                        <span class="badge-custom badge-info">
                                            <i class="fas fa-user-md"></i> <?= htmlspecialchars($visit['doctor_name']) ?>
                                        </span>
                                        <?php if (!empty($visit['doctor_specialty'])) { ?>
                                            <span style="font-size:0.68rem;color:var(--page-text-muted);display:block;"><?= htmlspecialchars($visit['doctor_specialty']) ?></span>
                                        <?php } ?>
                                    <?php } else { ?>
                                        <span style="color:var(--page-text-muted);font-size:0.72rem;">Not assigned</span>
                                    <?php } ?>
                                </td>
                                <td style="font-size:0.72rem;">
                                    <div style="font-weight:700;"><?= date('M d, Y', strtotime($visit['visit_date'] ?? 'now')) ?></div>
                                    <div style="color:var(--page-text-muted);"><?= date('h:i A', strtotime($visit['visit_date'] ?? 'now')) ?></div>
                                </td>
                                <td>
                                    <span class="badge-custom badge-<?= getStatusBadge($visit['visit_type'] ?? 'new') ?>">
                                        <?= ucfirst($visit['visit_type'] ?? 'New') ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge-custom badge-<?= getStatusBadge($status) ?>">
                                        <i class="fas <?= getStatusIcon($status) ?>"></i>
                                        <?= ucfirst(str_replace('_', ' ', $status)) ?>
                                    </span>
                                </td>
                                <td style="font-weight:700;color:<?= $net_total > 0 ? '#059669' : 'var(--page-text-muted)' ?>;">
                                    <?php if ($net_total > 0) { ?>
                                        TSh <?= number_format($net_total, 0) ?>
                                    <?php } else { ?>
                                        -
                                    <?php } ?>
                                </td>
                                <td>
                                    <div style="display:flex;flex-wrap:wrap;gap:4px;">
                                        <a href="view_visit.php?id=<?= $visit['id'] ?>&branch_id=<?= $branch_id ?>" class="action-link view">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                        <?php if ($status === 'pending' || $status === 'assigned') { ?>
                                            <span class="action-divider">|</span>
                                            <a href="assign_doctor.php?visit_id=<?= $visit['id'] ?>&branch_id=<?= $branch_id ?>" class="action-link assign">
                                                <i class="fas fa-user-md"></i> Assign
                                            </a>
                                        <?php } ?>
                                        <?php if ($status === 'completed') { ?>
                                            <span class="action-divider">|</span>
                                            <a href="view_bill.php?visit_id=<?= $visit['id'] ?>&branch_id=<?= $branch_id ?>" class="action-link bill">
                                                <i class="fas fa-receipt"></i> Bill
                                            </a>
                                        <?php } ?>
                                    </div>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            <?php } else { ?>
                <div class="empty-state-custom">
                    <i class="fas fa-hospital-user"></i>
                    <p style="font-weight:600;font-size:1rem;color:var(--page-text-primary);">No visits found</p>
                    <p style="font-size:0.85rem;">Try adjusting your filters or search terms</p>
                </div>
            <?php } ?>
        </div>
    </div>

</main>

<script>
    // DARK MODE BACKGROUND ENFORCEMENT
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

    console.log('%c🏥 Braick - Visits', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c✅ Uses SHARED header & sidebar', 'font-size:13px; color:#059669;');
    console.log('%c🌙 FULL DARK MODE inafanya kazi', 'font-size:13px; color:#7C3AED;');
    console.log('%c📊 Total: <?= number_format($total_visits) ?> | ✅ <?= $completed_visits ?> | ❌ <?= $cancelled_visits ?>', 'font-size:13px; color:#0B5ED7;');
</script>

</body>
</html>