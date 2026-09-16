<?php
// ================================================================
// FILE: frontend/pages/admin/users.php
// SUPER ADMIN - USERS MANAGEMENT
// VIEW AND MANAGE ALL SYSTEM USERS
// BRAICK DISPENSARY
// ✅ Uses SHARED admin_header.php & admin_sidebar.php
// ✅ Page-specific CSS only (no duplicates)
// ✅ Dark mode via header toggle
// ================================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../../../backend/config/database.php';
require_once '../../../backend/helpers/functions.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database connection error: " . $e->getMessage());
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

$user_id = $_SESSION['user_id'];
$user_full_name = $_SESSION['full_name'] ?? 'Admin';
$user_role = $_SESSION['role'] ?? 'admin';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$username = $_SESSION['username'] ?? '';
$profile_pic = $_SESSION['profile_pic'] ?? '';

// Verify user exists
$stmt = $db->prepare("SELECT id, full_name, role, status FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user || $user['status'] !== 'active') {
    session_destroy();
    header('Location: ../login.php');
    exit;
}

// GET UNREAD NOTIFICATIONS
$unread_notifications = 0;
try {
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$user_id]);
    $unread_notifications = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
} catch (Exception $e) {
    $unread_notifications = 0;
}

// GET FILTERS
$selected_branch_id = $_GET['branch'] ?? 'all';
$selected_role = $_GET['role'] ?? 'all';
$selected_status = $_GET['status'] ?? 'all';
$search_term = $_GET['search'] ?? '';

// GET BRANCHES
$branches = [];
try {
    $stmt = $db->query("SELECT id, name FROM branches WHERE status = 'active' ORDER BY name");
    $branches = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $branches = [];
}

// FETCH USERS
$query = "
    SELECT 
        u.id, u.full_name, u.username, u.email, u.phone, u.role, u.status,
        u.branch_id, u.profile_pic, u.created_at, u.last_online, u.updated_at,
        b.name as branch_name,
        (SELECT COUNT(*) FROM visits WHERE doctor_id = u.id) as visit_count,
        (SELECT COUNT(*) FROM prescriptions WHERE doctor_id = u.id) as prescription_count,
        (SELECT COUNT(*) FROM bills WHERE created_by = u.id) as bill_count,
        (SELECT COUNT(*) FROM bills WHERE created_by = u.id AND status = 'paid') as paid_bill_count
    FROM users u
    LEFT JOIN branches b ON u.branch_id = b.id
    WHERE 1=1
";

$params = [];

if (!empty($search_term)) {
    $query .= " AND (u.full_name LIKE :search OR u.username LIKE :search OR u.email LIKE :search OR u.phone LIKE :search)";
    $params[':search'] = '%' . $search_term . '%';
}

if ($selected_role !== 'all') {
    $query .= " AND u.role = :role";
    $params[':role'] = $selected_role;
}

if ($selected_branch_id !== 'all') {
    $query .= " AND u.branch_id = :branch_id";
    $params[':branch_id'] = (int)$selected_branch_id;
}

if ($selected_status !== 'all') {
    $query .= " AND u.status = :status";
    $params[':status'] = $selected_status;
}

$query .= " ORDER BY u.created_at DESC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// GET STATISTICS
$total_users = count($users);
$active_users = 0;
$admin_count = 0;
$doctor_count = 0;
$receptionist_count = 0;
$pharmacist_count = 0;
$cashier_count = 0;
$laboratory_count = 0;

foreach ($users as $u) {
    if ($u['status'] === 'active') $active_users++;
    switch ($u['role']) {
        case 'admin': $admin_count++; break;
        case 'doctor': $doctor_count++; break;
        case 'reception': $receptionist_count++; break;
        case 'pharmacy': $pharmacist_count++; break;
        case 'cashier': $cashier_count++; break;
        case 'laboratory': $laboratory_count++; break;
    }
}

// HELPER FUNCTIONS
function getRoleBadge($role) {
    $classes = ['admin' => 'danger', 'doctor' => 'primary', 'reception' => 'info', 'pharmacy' => 'success', 'cashier' => 'warning', 'laboratory' => 'secondary'];
    return $classes[$role] ?? 'secondary';
}

function getRoleIcon($role) {
    $icons = ['admin' => 'fa-user-tie', 'doctor' => 'fa-user-md', 'reception' => 'fa-user-nurse', 'pharmacy' => 'fa-prescription-bottle', 'cashier' => 'fa-calculator', 'laboratory' => 'fa-microscope'];
    return $icons[$role] ?? 'fa-user';
}

function getStatusBadge($status) {
    return $status === 'active' ? 'success' : 'danger';
}

function formatLastLogin($last_online) {
    if (empty($last_online) || $last_online === '0000-00-00 00:00:00') return 'Never';
    return date('M d, Y h:i A', strtotime($last_online));
}

// PROFILE PICTURE URL
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
<!-- PAGE-SPECIFIC CSS -->
<!-- ================================================================ -->
<style>
    /* ================================================================
       PAGE VARIABLES
       ================================================================ */
    :root {
        --usr-primary: #0B5ED7;
        --usr-primary-dark: #0A4CA8;
        --usr-primary-light: #6EA8FE;
        --usr-primary-bg: #EFF6FF;
        --usr-success: #059669;
        --usr-success-bg: #D1FAE5;
        --usr-danger: #EF4444;
        --usr-danger-bg: #FEE2E2;
        --usr-warning: #F59E0B;
        --usr-purple: #7C3AED;
        --usr-bg-body: #F1F5F9;
        --usr-bg-card: #FFFFFF;
        --usr-text-primary: #0F172A;
        --usr-text-secondary: #64748B;
        --usr-border-color: #E2E8F0;
        --usr-table-hover: #F8FAFC;
        --usr-shadow-sm: 0 2px 8px rgba(0,0,0,0.06);
        --usr-shadow-md: 0 4px 20px rgba(0,0,0,0.08);
        --usr-radius: 12px;
        --usr-radius-lg: 16px;
    }

    [data-theme="dark"] {
        --usr-bg-body: #0F172A;
        --usr-bg-card: #1E293B;
        --usr-text-primary: #F1F5F9;
        --usr-text-secondary: #94A3B8;
        --usr-border-color: #334155;
        --usr-table-hover: #1E293B;
        --usr-primary-bg: #1E3A5F;
        --usr-shadow-sm: 0 2px 8px rgba(0,0,0,0.3);
        --usr-shadow-md: 0 4px 20px rgba(0,0,0,0.4);
    }

    /* ================================================================
       DARK MODE
       ================================================================ */
    html[data-theme="dark"] body {
        background: #0F172A !important;
    }

    html[data-theme="dark"] .main-content {
        background: #0F172A !important;
        color: #F1F5F9;
    }

    /* ================================================================
       PAGE HEADER
       ================================================================ */
    .page-header-usr {
        background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
        border-radius: var(--usr-radius-lg);
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

    .page-header-usr::before {
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

    .page-header-usr::after {
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

    .page-header-usr .page-title-usr {
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

    .page-header-usr .page-title-usr i {
        font-size: 1.8rem;
        opacity: 0.9;
    }

    .page-header-usr .page-subtitle-usr {
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

    .page-header-usr .page-subtitle-usr strong {
        color: white;
    }

    .user-tag-usr {
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

    .date-badge-usr {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        color: rgba(255,255,255,0.8);
        font-size: 0.75rem;
    }

    .role-badge-display-usr {
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

    .btn-header-usr {
        background: rgba(255,255,255,0.15);
        color: white;
        border: 1px solid rgba(255,255,255,0.2);
        padding: 8px 16px;
        border-radius: 8px;
        font-weight: 600;
        font-size: 0.78rem;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        transition: all 0.3s ease;
        position: relative;
        z-index: 1;
        cursor: pointer;
    }

    .btn-header-usr:hover {
        background: rgba(255,255,255,0.25);
        transform: translateY(-2px);
        color: white;
    }

    /* ================================================================
       STATS CARDS
       ================================================================ */
    .stats-summary-grid-usr {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 16px;
        margin-bottom: 20px;
    }

    .stat-card-usr {
        background: var(--usr-bg-card);
        border-radius: var(--usr-radius);
        padding: 18px 20px;
        border: 1px solid var(--usr-border-color);
        display: flex;
        align-items: center;
        gap: 14px;
        transition: all 0.3s ease;
        box-shadow: var(--usr-shadow-sm);
    }

    .stat-card-usr:hover {
        transform: translateY(-3px);
        box-shadow: var(--usr-shadow-md);
        border-color: var(--usr-primary);
    }

    .stat-icon-usr {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
        flex-shrink: 0;
        transition: all 0.3s ease;
    }

    .stat-icon-usr.blue { background: #EFF6FF; color: #0B5ED7; }
    .stat-icon-usr.green { background: #ECFDF5; color: #059669; }
    .stat-icon-usr.purple { background: #F5F3FF; color: #7C3AED; }
    .stat-icon-usr.orange { background: #FFFBEB; color: #F59E0B; }

    [data-theme="dark"] .stat-icon-usr.blue { background: #1E3A5F; color: #6EA8FE; }
    [data-theme="dark"] .stat-icon-usr.green { background: #1A3A2A; color: #34D399; }
    [data-theme="dark"] .stat-icon-usr.purple { background: #2D1B4E; color: #A78BFA; }
    [data-theme="dark"] .stat-icon-usr.orange { background: #3D2E0A; color: #FBBF24; }

    .stat-card-usr:hover .stat-icon-usr {
        transform: scale(1.05);
    }

    .stat-label-usr {
        font-size: 0.65rem;
        color: var(--usr-text-secondary);
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        margin: 0;
    }

    .stat-value-usr {
        font-size: 1.15rem;
        font-weight: 700;
        color: var(--usr-text-primary);
        margin: 2px 0 0 0;
    }

    /* ================================================================
       FILTERS CARD
       ================================================================ */
    .filters-card-usr {
        background: var(--usr-bg-card);
        border-radius: var(--usr-radius);
        border: 1px solid var(--usr-border-color);
        padding: 16px 20px;
        box-shadow: var(--usr-shadow-sm);
        margin-bottom: 20px;
    }

    .filters-form-usr {
        display: flex;
        flex-wrap: wrap;
        align-items: flex-end;
        gap: 12px;
    }

    .filter-group-usr {
        display: flex;
        flex-direction: column;
        gap: 4px;
        flex: 1;
        min-width: 140px;
    }

    .filter-group-usr label {
        font-size: 0.7rem;
        font-weight: 600;
        color: var(--usr-text-secondary);
        text-transform: uppercase;
        letter-spacing: 0.03em;
    }

    .filter-select-usr {
        padding: 8px 12px;
        border-radius: 8px;
        border: 1px solid var(--usr-border-color);
        background: var(--usr-bg-body);
        color: var(--usr-text-primary);
        font-size: 0.8rem;
        transition: all 0.3s ease;
        width: 100%;
        font-family: inherit;
        cursor: pointer;
    }

    .filter-select-usr:focus {
        outline: none;
        border-color: var(--usr-primary);
        box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.1);
    }

    [data-theme="dark"] .filter-select-usr option {
        background: #1E293B;
        color: #F1F5F9;
    }

    .filter-actions-usr {
        display: flex;
        gap: 8px;
        align-items: center;
        padding-bottom: 2px;
    }

    /* ================================================================
       BUTTONS
       ================================================================ */
    .btn-usr {
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
        border: 1px solid var(--usr-border-color);
        text-decoration: none;
        background: var(--usr-bg-card);
        color: var(--usr-text-primary);
        box-shadow: var(--usr-shadow-sm);
        font-family: inherit;
    }

    .btn-usr:hover {
        transform: translateY(-2px);
        box-shadow: var(--usr-shadow-md);
    }

    .btn-sm-usr {
        padding: 5px 10px;
        font-size: 0.65rem;
        border-radius: 6px;
    }

    .btn-primary-usr {
        background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
        color: white;
        border-color: #0B5ED7;
    }

    .btn-primary-usr:hover {
        background: linear-gradient(135deg, #0A4CA8, #083C8A);
        border-color: #0A4CA8;
        color: white;
    }

    .btn-outline-usr {
        background: transparent;
        color: var(--usr-text-secondary);
        border: 1.5px solid var(--usr-border-color);
    }

    .btn-outline-usr:hover {
        background: var(--usr-bg-body);
        border-color: var(--usr-primary);
        color: var(--usr-primary);
    }

    .btn-outline-danger-usr {
        color: #EF4444;
        border-color: #EF4444;
    }

    .btn-outline-danger-usr:hover {
        background: #EF4444;
        color: white;
    }

    .btn-outline-success-usr {
        color: #059669;
        border-color: #059669;
    }

    .btn-outline-success-usr:hover {
        background: #059669;
        color: white;
    }

    /* ================================================================
       DATA TABLE
       ================================================================ */
    .card-usr {
        background: var(--usr-bg-card);
        border-radius: var(--usr-radius);
        border: 1px solid var(--usr-border-color);
        overflow: hidden;
        transition: all 0.3s ease;
        box-shadow: var(--usr-shadow-sm);
    }

    .card-usr:hover {
        box-shadow: var(--usr-shadow-md);
    }

    .overflow-x-auto-usr {
        overflow-x: auto;
    }

    .data-table-usr {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
        font-size: 0.8rem;
    }

    .data-table-usr thead th {
        background: var(--usr-primary) !important;
        color: white !important;
        font-weight: 600;
        padding: 12px 16px;
        font-size: 0.65rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        border-bottom: none !important;
        white-space: nowrap;
        text-align: left;
    }

    .data-table-usr thead th:first-child { border-radius: 8px 0 0 0; }
    .data-table-usr thead th:last-child { border-radius: 0 8px 0 0; text-align: center; }

    .data-table-usr td {
        padding: 12px 16px;
        border-bottom: 1px solid var(--usr-border-color);
        color: var(--usr-text-primary);
        vertical-align: middle;
        transition: background 0.2s ease;
    }

    .data-table-usr tbody tr:hover td {
        background: var(--usr-table-hover);
    }

    .data-table-usr tbody tr:last-child td {
        border-bottom: none;
    }

    /* User Cell */
    .user-cell-usr {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .user-avatar-sm-usr {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        overflow: hidden;
        flex-shrink: 0;
        background: #0B5ED7;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .user-avatar-sm-usr img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .avatar-initials-sm-usr {
        color: white;
        font-weight: 700;
        font-size: 1rem;
        text-transform: uppercase;
    }

    .user-name-sm-usr {
        font-weight: 600;
        color: var(--usr-text-primary);
        font-size: 0.85rem;
    }

    .user-email-sm-usr,
    .user-phone-sm-usr {
        font-size: 0.65rem;
        color: var(--usr-text-secondary);
        display: flex;
        align-items: center;
        gap: 4px;
    }

    .user-email-sm-usr i,
    .user-phone-sm-usr i {
        font-size: 0.5rem;
        color: var(--usr-primary);
    }

    .username-cell-usr {
        font-weight: 600;
        color: var(--usr-text-primary);
        font-family: 'Courier New', monospace;
        font-size: 0.8rem;
    }

    .user-id-cell-usr {
        font-size: 0.6rem;
        color: var(--usr-text-secondary);
    }

    .branch-cell-usr {
        display: flex;
        align-items: center;
        gap: 6px;
        font-size: 0.8rem;
        color: var(--usr-text-secondary);
    }

    .branch-cell-usr i {
        color: var(--usr-primary);
        font-size: 0.7rem;
    }

    .login-cell-usr {
        display: flex;
        align-items: center;
        gap: 6px;
        font-size: 0.75rem;
        color: var(--usr-text-secondary);
    }

    .login-cell-usr i {
        color: var(--usr-primary);
        font-size: 0.65rem;
    }

    .doctor-stats-sm-usr {
        display: flex;
        gap: 8px;
        margin-top: 4px;
        font-size: 0.6rem;
        color: var(--usr-text-secondary);
    }

    .doctor-stats-sm-usr span {
        display: inline-flex;
        align-items: center;
        gap: 3px;
        background: var(--usr-bg-body);
        padding: 1px 6px;
        border-radius: 8px;
        border: 1px solid var(--usr-border-color);
    }

    .doctor-stats-sm-usr i {
        color: var(--usr-primary);
    }

    /* ================================================================
       ROLE BADGES
       ================================================================ */
    .role-badge-usr {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.03em;
        white-space: nowrap;
    }

    .role-admin-usr { background: #FEE2E2; color: #DC2626; }
    .role-doctor-usr { background: #DBEAFE; color: #2563EB; }
    .role-reception-usr { background: #E0F2FE; color: #0891B2; }
    .role-pharmacy-usr { background: #D1FAE5; color: #059669; }
    .role-cashier-usr { background: #FEF3C7; color: #D97706; }
    .role-laboratory-usr { background: #E8E8E8; color: #4B5563; }

    [data-theme="dark"] .role-admin-usr { background: #3A1A1A; color: #F87171; }
    [data-theme="dark"] .role-doctor-usr { background: #1A2A4A; color: #60A5FA; }
    [data-theme="dark"] .role-reception-usr { background: #0A2A3A; color: #22D3EE; }
    [data-theme="dark"] .role-pharmacy-usr { background: #1A3A2A; color: #34D399; }
    [data-theme="dark"] .role-cashier-usr { background: #3D2E0A; color: #FBBF24; }
    [data-theme="dark"] .role-laboratory-usr { background: #374151; color: #9CA3AF; }

    /* ================================================================
       STATUS BADGES
       ================================================================ */
    .badge-usr {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 3px 12px;
        border-radius: 20px;
        font-size: 0.65rem;
        font-weight: 600;
        color: white;
        letter-spacing: 0.02em;
        white-space: nowrap;
    }

    .badge-success-usr { background: #059669; }
    .badge-danger-usr { background: #EF4444; }
    .badge-warning-usr { background: #F59E0B; color: #1E293B; }

    .badge-usr i {
        font-size: 0.45rem;
    }

    /* ================================================================
       ACTION BUTTONS
       ================================================================ */
    .action-buttons-usr {
        display: flex;
        gap: 4px;
        justify-content: center;
        flex-wrap: wrap;
    }

    .action-buttons-usr .btn-usr {
        padding: 4px 8px;
        font-size: 0.7rem;
        border-radius: 6px;
    }

    /* ================================================================
       EMPTY STATE
       ================================================================ */
    .empty-state-usr {
        text-align: center;
        padding: 60px 20px;
        background: var(--usr-bg-card);
        border-radius: var(--usr-radius);
        border: 1px solid var(--usr-border-color);
    }

    .empty-state-usr i {
        font-size: 4rem;
        color: var(--usr-text-secondary);
        opacity: 0.3;
        margin-bottom: 16px;
        display: block;
    }

    .empty-state-usr h3 {
        font-size: 1.2rem;
        color: var(--usr-text-primary);
        margin: 0 0 8px 0;
    }

    .empty-state-usr p {
        color: var(--usr-text-secondary);
        margin: 0 0 20px 0;
        font-size: 0.9rem;
    }

    /* ================================================================
       FOOTER
       ================================================================ */
    .footer-usr {
        margin-top: 30px;
        padding: 16px 20px;
        background: var(--usr-bg-card);
        border-radius: var(--usr-radius);
        border: 1px solid var(--usr-border-color);
        text-align: center;
    }

    .footer-usr p {
        margin: 0;
        font-size: 0.8rem;
        color: var(--usr-text-secondary);
    }

    .footer-usr .footer-brand-usr {
        font-weight: 700;
        color: var(--usr-primary);
    }

    /* ================================================================
       ANIMATIONS
       ================================================================ */
    @keyframes fadeInUp {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .animate-fade-in-up-usr {
        animation: fadeInUp 0.5s ease forwards;
        opacity: 0;
    }

    /* ================================================================
       RESPONSIVE
       ================================================================ */
    @media (max-width: 768px) {
        .stats-summary-grid-usr { grid-template-columns: 1fr 1fr; }
        .filters-form-usr { flex-direction: column; align-items: stretch; }
        .filter-group-usr { min-width: 100%; }
        .filter-actions-usr { justify-content: flex-end; }
        .page-header-usr { padding: 16px 18px; }
        .page-header-usr .page-title-usr { font-size: 1.3rem; }
        .user-cell-usr { flex-direction: column; align-items: flex-start; gap: 6px; }
        .action-buttons-usr { flex-wrap: wrap; }
        .data-table-usr { font-size: 0.7rem; }
        .data-table-usr td, .data-table-usr th { padding: 8px 12px; }
    }

    @media (max-width: 480px) {
        .stats-summary-grid-usr { grid-template-columns: 1fr; }
        .page-header-usr { flex-direction: column; align-items: flex-start !important; }
        .page-title-usr { font-size: 1rem; }
        .btn-usr { font-size: 0.7rem; padding: 5px 10px; }
        .btn-sm-usr { font-size: 0.6rem; padding: 3px 6px; }
        .data-table-usr { font-size: 0.6rem; }
        .data-table-usr td, .data-table-usr th { padding: 6px 8px; }
        .data-table-usr thead th { font-size: 0.5rem; padding: 6px 8px; }
    }

    /* ================================================================
       PRINT
       ================================================================ */
    @media print {
        .btn-usr, .btn-header-usr, .filters-card-usr, .action-buttons-usr { display: none !important; }
        .card-usr { box-shadow: none !important; border: 1px solid #ddd !important; }
        .data-table-usr thead th {
            background: #0B5ED7 !important;
            color: white !important;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }
        .badge-usr, .role-badge-usr, .user-avatar-sm-usr {
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }
        .page-header-usr {
            background: #0B5ED7 !important;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }
    }
</style>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <!-- Page Header -->
    <div class="page-header-usr animate-fade-in-up-usr">
        <div>
            <h1 class="page-title-usr">
                <i class="fas fa-users-cog"></i>
                Users Management
                <span class="role-badge-display-usr">ADMIN</span>
            </h1>
            <p class="page-subtitle-usr">
                <i class="fas fa-users"></i>
                <strong><?= $total_users ?></strong> users found
                <span class="user-tag-usr">
                    <i class="fas fa-check-circle"></i> <?= $active_users ?> Active
                </span>
                <span class="date-badge-usr">
                    <i class="fas fa-calendar-day"></i> <?= date('F d, Y') ?>
                </span>
            </p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;position:relative;z-index:1;">
            <a href="add_user.php" class="btn-header-usr">
                <i class="fas fa-plus-circle"></i> Add User
            </a>
            <button onclick="window.location.reload()" class="btn-header-usr">
                <i class="fas fa-sync-alt"></i> Refresh
            </button>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- STATISTICS SUMMARY -->
    <!-- ================================================================ -->
    <div class="stats-summary-grid-usr animate-fade-in-up-usr" style="animation-delay:0.05s;">
        <div class="stat-card-usr">
            <div class="stat-icon-usr blue">
                <i class="fas fa-users"></i>
            </div>
            <div>
                <p class="stat-label-usr">Total Users</p>
                <p class="stat-value-usr"><?= $total_users ?></p>
            </div>
        </div>
        <div class="stat-card-usr">
            <div class="stat-icon-usr green">
                <i class="fas fa-check-circle"></i>
            </div>
            <div>
                <p class="stat-label-usr">Active Users</p>
                <p class="stat-value-usr"><?= $active_users ?></p>
            </div>
        </div>
        <div class="stat-card-usr">
            <div class="stat-icon-usr purple">
                <i class="fas fa-user-tie"></i>
            </div>
            <div>
                <p class="stat-label-usr">Admins</p>
                <p class="stat-value-usr"><?= $admin_count ?></p>
            </div>
        </div>
        <div class="stat-card-usr">
            <div class="stat-icon-usr orange">
                <i class="fas fa-user-md"></i>
            </div>
            <div>
                <p class="stat-label-usr">Doctors</p>
                <p class="stat-value-usr"><?= $doctor_count ?></p>
            </div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- FILTERS -->
    <!-- ================================================================ -->
    <div class="filters-card-usr animate-fade-in-up-usr" style="animation-delay:0.1s;">
        <form method="GET" action="" class="filters-form-usr">
            <div class="filter-group-usr">
                <label>Role</label>
                <select name="role" id="roleFilter" class="filter-select-usr">
                    <option value="all" <?= $selected_role === 'all' ? 'selected' : '' ?>>All Roles</option>
                    <option value="admin" <?= $selected_role === 'admin' ? 'selected' : '' ?>>Admin</option>
                    <option value="doctor" <?= $selected_role === 'doctor' ? 'selected' : '' ?>>Doctor</option>
                    <option value="reception" <?= $selected_role === 'reception' ? 'selected' : '' ?>>Reception</option>
                    <option value="pharmacy" <?= $selected_role === 'pharmacy' ? 'selected' : '' ?>>Pharmacy</option>
                    <option value="cashier" <?= $selected_role === 'cashier' ? 'selected' : '' ?>>Cashier</option>
                    <option value="laboratory" <?= $selected_role === 'laboratory' ? 'selected' : '' ?>>Laboratory</option>
                </select>
            </div>
            
            <div class="filter-group-usr">
                <label>Branch</label>
                <select name="branch" id="branchFilter" class="filter-select-usr">
                    <option value="all" <?= $selected_branch_id === 'all' ? 'selected' : '' ?>>All Branches</option>
                    <?php foreach ($branches as $branch): ?>
                        <option value="<?= $branch['id'] ?>" <?= $selected_branch_id == $branch['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($branch['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="filter-group-usr">
                <label>Status</label>
                <select name="status" id="statusFilter" class="filter-select-usr">
                    <option value="all" <?= $selected_status === 'all' ? 'selected' : '' ?>>All Status</option>
                    <option value="active" <?= $selected_status === 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="inactive" <?= $selected_status === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                </select>
            </div>
            
            <div class="filter-actions-usr">
                <button type="submit" class="btn-usr btn-primary-usr btn-sm-usr">
                    <i class="fas fa-filter"></i> Apply Filters
                </button>
                <a href="users.php" class="btn-usr btn-outline-usr btn-sm-usr">
                    <i class="fas fa-undo"></i> Reset
                </a>
            </div>
        </form>
    </div>

    <!-- ================================================================ -->
    <!-- USERS TABLE -->
    <!-- ================================================================ -->
    <?php if (count($users) > 0): ?>
        <div class="card-usr animate-fade-in-up-usr" style="animation-delay:0.15s;">
            <div class="overflow-x-auto-usr">
                <table class="data-table-usr">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Username</th>
                            <th>Role</th>
                            <th>Branch</th>
                            <th>Status</th>
                            <th>Last Online</th>
                            <th style="text-align:center;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $u): ?>
                            <tr>
                                <td>
                                    <div class="user-cell-usr">
                                        <div class="user-avatar-sm-usr">
                                            <?php if (!empty($u['profile_pic'])): ?>
                                                <img src="/dispensary_system/frontend/assets/uploads/profiles/<?= htmlspecialchars($u['profile_pic']) ?>" 
                                                     alt="<?= htmlspecialchars($u['full_name']) ?>"
                                                     onerror="this.style.display='none'; this.parentElement.innerHTML='<span class=\'avatar-initials-sm-usr\'><?= strtoupper(substr($u['full_name'], 0, 1)) ?></span>';">
                                            <?php else: ?>
                                                <span class="avatar-initials-sm-usr">
                                                    <?= strtoupper(substr($u['full_name'], 0, 1)) ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <div class="user-name-sm-usr"><?= htmlspecialchars($u['full_name']) ?></div>
                                            <div class="user-email-sm-usr">
                                                <i class="fas fa-envelope"></i> <?= htmlspecialchars($u['email'] ?? 'N/A') ?>
                                            </div>
                                            <div class="user-phone-sm-usr">
                                                <i class="fas fa-phone"></i> <?= htmlspecialchars($u['phone'] ?? 'N/A') ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="username-cell-usr">@<?= htmlspecialchars($u['username']) ?></span>
                                    <div class="user-id-cell-usr">ID: <?= $u['id'] ?></div>
                                </td>
                                <td>
                                    <span class="role-badge-usr role-<?= $u['role'] ?>-usr">
                                        <i class="fas <?= getRoleIcon($u['role']) ?>"></i>
                                        <?= ucfirst($u['role']) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="branch-cell-usr">
                                        <i class="fas fa-store-alt"></i>
                                        <?= htmlspecialchars($u['branch_name'] ?? 'No Branch') ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge-usr badge-<?= getStatusBadge($u['status']) ?>-usr">
                                        <i class="fas fa-circle"></i>
                                        <?= ucfirst($u['status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="login-cell-usr">
                                        <i class="fas fa-clock"></i>
                                        <?= formatLastLogin($u['last_online'] ?? null) ?>
                                    </div>
                                    <?php if ($u['role'] === 'doctor'): ?>
                                        <div class="doctor-stats-sm-usr">
                                            <span title="Visits"><i class="fas fa-stethoscope"></i> <?= $u['visit_count'] ?? 0 ?></span>
                                            <span title="Prescriptions"><i class="fas fa-prescription"></i> <?= $u['prescription_count'] ?? 0 ?></span>
                                            <span title="Bills"><i class="fas fa-file-invoice"></i> <?= $u['bill_count'] ?? 0 ?></span>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="action-buttons-usr">
                                        <a href="view_user.php?id=<?= $u['id'] ?>" class="btn-usr btn-sm-usr btn-outline-usr" title="View">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="edit_user.php?id=<?= $u['id'] ?>" class="btn-usr btn-sm-usr btn-outline-usr" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <?php if ($u['status'] === 'active'): ?>
                                            <button onclick="toggleUser(<?= $u['id'] ?>, 'inactive')" class="btn-usr btn-sm-usr btn-outline-danger-usr" title="Deactivate">
                                                <i class="fas fa-pause"></i>
                                            </button>
                                        <?php else: ?>
                                            <button onclick="toggleUser(<?= $u['id'] ?>, 'active')" class="btn-usr btn-sm-usr btn-outline-success-usr" title="Activate">
                                                <i class="fas fa-play"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php else: ?>
        <div class="empty-state-usr animate-fade-in-up-usr" style="animation-delay:0.15s;">
            <i class="fas fa-users-slash"></i>
            <h3>No Users Found</h3>
            <p>No users match your search criteria. Try adjusting your filters or add a new user.</p>
            <a href="add_user.php" class="btn-usr btn-primary-usr">
                <i class="fas fa-plus-circle"></i> Add User
            </a>
        </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer-usr">
        <p>
            <span class="footer-brand-usr">Braick Dispensary</span> Management System
            <span style="color:#CBD5E1;margin:0 8px;">|</span>
            Users Management
            <span style="color:#CBD5E1;margin:0 8px;">|</span>
            <span id="footerTime"><?= date('H:i:s') ?></span>
            <span style="color:#CBD5E1;margin:0 8px;">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<!-- ================================================================ -->
<!-- PAGE-SPECIFIC JAVASCRIPT -->
<!-- ================================================================ -->
<script>
    // ================================================================
    // TOGGLE USER STATUS
    // ================================================================
    function toggleUser(id, status) {
        if (confirm('Are you sure you want to ' + (status === 'active' ? 'activate' : 'deactivate') + ' this user?')) {
            fetch('ajax/toggle_user.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: id, status: status })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('Failed to update user status. Please try again.');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred. Please try again.');
            });
        }
    }

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

    console.log('%c👥 Braick Dispensary - Users Management', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c👤 Admin: <?= htmlspecialchars($user_full_name) ?> (ID: <?= $user_id ?>)', 'font-size:13px; color:#059669;');
    console.log('%c📊 Total Users: <?= $total_users ?>', 'font-size:13px; color:#059669;');
    console.log('%c✅ Active Users: <?= $active_users ?>', 'font-size:13px; color:#0B5ED7;');
    console.log('%c✅ Uses SHARED header & sidebar', 'font-size:13px; color:#34D399;');
    console.log('%c✅ No duplicate CSS/JS', 'font-size:13px; color:#34D399;');
    console.log('%c🌙 Dark mode: Handled by header (shared)', 'font-size:13px; color:#34D399;');
</script>

</body>
</html>