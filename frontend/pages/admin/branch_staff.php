<?php
// ================================================================
// FILE: frontend/pages/admin/branch_staff.php
// SUPER ADMIN - BRANCH STAFF MANAGEMENT
// ✅ Uses SHARED header & sidebar
// ✅ Beautiful blue stat cards + blue table header
// ✅ Full dark mode support
// ================================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit();
}

if ($_SESSION['role'] !== 'admin') {
    header('Location: ../../dashboard.php');
    exit();
}

require_once '../../../backend/config/database.php';
require_once '../../../backend/helpers/functions.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

// ================================================================
// GET BRANCH ID
// ================================================================
$branch_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($branch_id <= 0) {
    header('Location: branches.php');
    exit();
}

// ================================================================
// GET BRANCH INFO
// ================================================================
$branch_stmt = $db->prepare("SELECT * FROM branches WHERE id = ?");
$branch_stmt->execute([$branch_id]);
$branch = $branch_stmt->fetch(PDO::FETCH_ASSOC);

if (!$branch) {
    header('Location: branches.php');
    exit();
}

// ================================================================
// SEARCH
// ================================================================
$search_term = isset($_GET['search']) ? trim($_GET['search']) : '';

// ================================================================
// GET STAFF
// ================================================================
$staff_query = "
    SELECT u.*, b.name as branch_name, b.location as branch_location
    FROM users u
    LEFT JOIN branches b ON u.branch_id = b.id
    WHERE u.branch_id = ?
";

if (!empty($search_term)) {
    $staff_query .= " AND (u.full_name LIKE ? OR u.email LIKE ? OR u.phone LIKE ? OR u.username LIKE ? OR u.role LIKE ?)";
}

$staff_query .= " ORDER BY u.role, u.full_name";

$staff_stmt = $db->prepare($staff_query);

if (!empty($search_term)) {
    $search_pattern = '%' . $search_term . '%';
    $staff_stmt->execute([$branch_id, $search_pattern, $search_pattern, $search_pattern, $search_pattern, $search_pattern]);
} else {
    $staff_stmt->execute([$branch_id]);
}

$staff_list = $staff_stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// COUNTS BY ROLE
// ================================================================
$count_stmt = $db->prepare("
    SELECT role, COUNT(*) as count 
    FROM users 
    WHERE branch_id = ? AND status = 'active'
    GROUP BY role
");
$count_stmt->execute([$branch_id]);
$role_counts = $count_stmt->fetchAll(PDO::FETCH_ASSOC);

$role_count_map = [];
foreach ($role_counts as $rc) {
    $role_count_map[$rc['role']] = $rc['count'];
}

$total_staff = count($staff_list);
$active_staff = 0;
$inactive_staff = 0;

foreach ($staff_list as $staff) {
    if ($staff['status'] === 'active') $active_staff++;
    else $inactive_staff++;
}

// ================================================================
// HANDLE POST ACTIONS
// ================================================================
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'toggle_status') {
        $staff_id = (int)($_POST['staff_id'] ?? 0);
        $status = $_POST['status'] ?? 'active';

        if ($staff_id > 0 && in_array($status, ['active', 'inactive'])) {
            try {
                $stmt = $db->prepare("UPDATE users SET status = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$status, $staff_id]);
                $message = "✅ Staff status updated to <strong>" . ucfirst($status) . "</strong>";
                $message_type = 'success';

                if (!empty($search_term)) {
                    $staff_stmt->execute([$branch_id, $search_pattern, $search_pattern, $search_pattern, $search_pattern, $search_pattern]);
                } else {
                    $staff_stmt->execute([$branch_id]);
                }
                $staff_list = $staff_stmt->fetchAll(PDO::FETCH_ASSOC);

                $active_staff = 0;
                $inactive_staff = 0;
                foreach ($staff_list as $staff) {
                    if ($staff['status'] === 'active') $active_staff++;
                    else $inactive_staff++;
                }
                $total_staff = count($staff_list);

            } catch (PDOException $e) {
                $message = "❌ Error: " . $e->getMessage();
                $message_type = 'error';
            }
        }
    }

    if ($action === 'delete_staff') {
        $staff_id = (int)($_POST['staff_id'] ?? 0);

        if ($staff_id > 0) {
            try {
                $dependencies = 0;
                $dep_details = [];

                $checks = [
                    ['visits', 'doctor_id', 'associated visits'],
                    ['lab_tests', 'doctor_id', 'associated lab tests'],
                    ['prescriptions', 'doctor_id', 'associated prescriptions'],
                    ['bills', 'created_by', 'associated bills'],
                    ['payments', 'received_by', 'associated payments'],
                    ['activity_logs', 'user_id', 'activity logs']
                ];

                foreach ($checks as $check) {
                    $check_stmt = $db->prepare("SELECT COUNT(*) as count FROM {$check[0]} WHERE {$check[1]} = ?");
                    $check_stmt->execute([$staff_id]);
                    $count = $check_stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
                    if ($count > 0) {
                        $dependencies += $count;
                        $dep_details[] = "$count {$check[2]}";
                    }
                }

                if ($dependencies > 0) {
                    $message = "❌ Cannot delete this staff member. They have:<br>• " . implode('<br>• ', $dep_details) . "<br>Please deactivate instead of deleting.";
                    $message_type = 'error';
                } else {
                    $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
                    $stmt->execute([$staff_id]);
                    $message = "✅ Staff member removed successfully.";
                    $message_type = 'success';

                    if (!empty($search_term)) {
                        $staff_stmt->execute([$branch_id, $search_pattern, $search_pattern, $search_pattern, $search_pattern, $search_pattern]);
                    } else {
                        $staff_stmt->execute([$branch_id]);
                    }
                    $staff_list = $staff_stmt->fetchAll(PDO::FETCH_ASSOC);

                    $active_staff = 0;
                    $inactive_staff = 0;
                    foreach ($staff_list as $staff) {
                        if ($staff['status'] === 'active') $active_staff++;
                        else $inactive_staff++;
                    }
                    $total_staff = count($staff_list);
                }
            } catch (PDOException $e) {
                $message = "❌ Error: " . $e->getMessage();
                $message_type = 'error';
            }
        }
    }
}

// ================================================================
// USER DATA
// ================================================================
$logo_url = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';
$user_name = $_SESSION['full_name'] ?? 'Admin';
$user_role = $_SESSION['role'] ?? 'admin';
$selected_branch_id = $branch_id;
$profile_pic = $_SESSION['profile_pic'] ?? '';
$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

// ================================================================
// ✅ INCLUDE SHARED HEADER & SIDEBAR
// ================================================================
include_once __DIR__ . '/../../components/admin_header.php';
include_once __DIR__ . '/../../components/admin_sidebar.php';
?>

<!-- ================================================================
     PAGE-SPECIFIC CSS
     ================================================================ -->
<style>
    :root {
        --page-primary: #1A56DB;
        --page-primary-dark: #1A3E8C;
        --page-primary-bg: #E8EFF9;
        --page-primary-light: #3B82F6;
        --page-success: #059669;
        --page-success-bg: #D1FAE5;
        --page-danger: #DC2626;
        --page-danger-bg: #FEE2E2;
        --page-warning: #D97706;
        --page-warning-bg: #FEF3C7;
        --page-purple: #7C3AED;
        --page-bg-body: #F0F4F8;
        --page-bg-card: #FFFFFF;
        --page-text-primary: #1E293B;
        --page-text-secondary: #64748B;
        --page-border: #E2E8F0;
        --page-table-hover: #F8FAFC;
        --page-shadow-sm: 0 1px 2px rgba(0,0,0,0.05);
        --page-shadow-md: 0 4px 12px rgba(0,0,0,0.08);
        --page-shadow-lg: 0 10px 25px rgba(0,0,0,0.1);
    }

    [data-theme="dark"] {
        --page-bg-body: #0F172A;
        --page-bg-card: #1E293B;
        --page-text-primary: #F1F5F9;
        --page-text-secondary: #94A3B8;
        --page-border: #334155;
        --page-table-hover: #1E293B;
        --page-primary-bg: #1E3A5F;
        --page-success-bg: #1A3A2A;
        --page-danger-bg: #3A1A1A;
        --page-warning-bg: #3D2E0A;
        --page-primary: #3B82F6;
        --page-primary-dark: #2563EB;
        --page-shadow-sm: 0 1px 2px rgba(0,0,0,0.3);
        --page-shadow-md: 0 4px 12px rgba(0,0,0,0.3);
        --page-shadow-lg: 0 10px 25px rgba(0,0,0,0.4);
    }

    /* BODY & MAIN CONTENT - DARK MODE */
    body { background: var(--page-bg-body); }
    html[data-theme="dark"] body { background: #0F172A !important; }

    .main-content { background: var(--page-bg-body); }
    html[data-theme="dark"] .main-content { background: #0F172A !important; }

    /* ================================================================
       BLUE PAGE HEADER CARD
       ================================================================ */
    .page-header-card {
        background: linear-gradient(135deg, #1A56DB 0%, #1A3E8C 50%, #163278 100%);
        border-radius: 20px;
        padding: 24px 32px;
        margin-bottom: 24px;
        display: flex;
        flex-wrap: wrap;
        justify-content: space-between;
        align-items: center;
        gap: 16px;
        color: white;
        box-shadow: 0 8px 32px rgba(26, 86, 219, 0.3), 0 4px 12px rgba(26, 86, 219, 0.2);
        position: relative;
        overflow: hidden;
        transition: all 0.3s ease;
    }

    .page-header-card::before {
        content: '';
        position: absolute;
        top: -50%; right: -10%;
        width: 400px; height: 400px;
        background: radial-gradient(circle, rgba(255,255,255,0.12) 0%, transparent 70%);
        border-radius: 50%;
        pointer-events: none;
    }

    .page-header-title {
        font-size: 1.5rem;
        font-weight: 800;
        margin: 0 0 8px 0;
        display: flex;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
        color: white;
        position: relative;
        z-index: 2;
    }

    .page-header-title i {
        width: 44px; height: 44px;
        background: rgba(255,255,255,0.2);
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255,255,255,0.2);
    }

    .page-header-subtitle {
        font-size: 0.9rem;
        color: rgba(255,255,255,0.9);
        margin: 0;
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
        position: relative;
        z-index: 2;
    }

    .role-badge-display {
        background: rgba(255,255,255,0.25);
        color: white;
        padding: 4px 14px;
        border-radius: 20px;
        font-size: 0.65rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    .page-header-badge {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 4px 12px;
        background: rgba(255,255,255,0.15);
        border: 1px solid rgba(255,255,255,0.25);
        border-radius: 20px;
        font-size: 0.72rem;
        font-weight: 600;
        backdrop-filter: blur(10px);
    }

    .page-header-actions {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
        position: relative;
        z-index: 2;
    }

    .btn-outline-light {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 10px 20px;
        background: rgba(255,255,255,0.15);
        border: 1.5px solid rgba(255,255,255,0.3);
        border-radius: 12px;
        color: white;
        text-decoration: none;
        font-weight: 600;
        font-size: 0.85rem;
        transition: all 0.3s ease;
        backdrop-filter: blur(10px);
        white-space: nowrap;
        cursor: pointer;
    }

    .btn-outline-light:hover {
        background: rgba(255,255,255,0.25);
        transform: translateY(-2px);
        color: white;
        box-shadow: 0 4px 16px rgba(0,0,0,0.15);
    }

    /* ================================================================
       STAT CARDS - BLUE BACKGROUND
       ================================================================ */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 16px;
        margin-bottom: 24px;
    }

    .stat-card {
        background: linear-gradient(135deg, #1A56DB 0%, #1A3E8C 100%);
        border-radius: 16px;
        padding: 20px 24px;
        display: flex;
        align-items: center;
        gap: 16px;
        transition: all 0.3s ease;
        box-shadow: 0 6px 20px rgba(26, 86, 219, 0.25);
        color: white;
        position: relative;
        overflow: hidden;
    }

    .stat-card::before {
        content: '';
        position: absolute;
        top: -40%; right: -20%;
        width: 180px; height: 180px;
        background: radial-gradient(circle, rgba(255,255,255,0.12) 0%, transparent 70%);
        border-radius: 50%;
        pointer-events: none;
    }

    .stat-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 12px 32px rgba(26, 86, 219, 0.4);
    }

    .stat-icon {
        width: 52px; height: 52px;
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.3rem;
        flex-shrink: 0;
        background: rgba(255,255,255,0.2);
        color: white;
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255,255,255,0.15);
        position: relative;
        z-index: 1;
    }

    .stat-label {
        font-size: 0.7rem;
        color: rgba(255,255,255,0.85);
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        margin: 0;
        position: relative;
        z-index: 1;
    }

    .stat-value {
        font-size: 1.6rem;
        font-weight: 800;
        color: white;
        margin: 4px 0 0 0;
        line-height: 1.1;
        position: relative;
        z-index: 1;
    }

    /* ================================================================
       TABLE CARD
       ================================================================ */
    .table-card {
        background: var(--page-bg-card);
        border-radius: 18px;
        border: 1px solid var(--page-border);
        overflow: hidden;
        box-shadow: var(--page-shadow-md);
        margin-bottom: 20px;
    }

    .table-card-header {
        padding: 18px 24px;
        border-bottom: 1px solid var(--page-border);
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 12px;
        background: var(--page-bg-body);
    }

    html[data-theme="dark"] .table-card-header { background: #0F172A; }

    .table-card-title {
        font-size: 0.95rem;
        font-weight: 700;
        color: var(--page-text-primary);
        display: flex;
        align-items: center;
        gap: 10px;
        margin: 0;
    }

    .table-card-title i { color: var(--page-primary); }

    .btn-add {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        background: linear-gradient(135deg, #1A56DB, #1A3E8C);
        color: white;
        border: none;
        padding: 10px 20px;
        border-radius: 10px;
        font-size: 0.82rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        text-decoration: none;
        box-shadow: 0 4px 12px rgba(26, 86, 219, 0.25);
    }

    .btn-add:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(26, 86, 219, 0.35);
        color: white;
    }

    /* ================================================================
       TABLE
       ================================================================ */
    .table-scroll { overflow-x: auto; }

    .data-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.85rem;
    }

    .data-table thead th {
        background: linear-gradient(135deg, #1A56DB, #1A3E8C);
        padding: 14px 16px;
        text-align: left;
        font-size: 0.7rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: white;
        border-bottom: none;
        white-space: nowrap;
    }

    .data-table thead th i { margin-right: 6px; opacity: 0.85; }

    .data-table tbody td {
        padding: 12px 16px;
        font-size: 0.82rem;
        border-bottom: 1px solid var(--page-border);
        color: var(--page-text-primary);
        vertical-align: middle;
    }

    .data-table tbody tr { transition: background 0.2s ease; }
    .data-table tbody tr:hover { background: var(--page-table-hover); }
    .data-table tbody tr:last-child td { border-bottom: none; }

    /* Staff avatar */
    .staff-avatar {
        width: 40px; height: 40px;
        border-radius: 50%;
        object-fit: cover;
        border: 2px solid var(--page-border);
    }

    .staff-avatar-placeholder {
        width: 40px; height: 40px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        background: linear-gradient(135deg, #1A56DB, #1A3E8C);
        color: white;
        font-weight: 700;
        font-size: 0.95rem;
        border: 2px solid var(--page-border);
    }

    /* Role badges */
    .badge-role {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 0.65rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.03em;
        color: white;
    }

    .badge-role.admin { background: linear-gradient(135deg, #DC2626, #B91C1C); }
    .badge-role.doctor { background: linear-gradient(135deg, #1A56DB, #1A3E8C); }
    .badge-role.reception { background: linear-gradient(135deg, #7C3AED, #5B21B6); }
    .badge-role.pharmacy { background: linear-gradient(135deg, #D97706, #B45309); }
    .badge-role.cashier { background: linear-gradient(135deg, #059669, #047857); }
    .badge-role.laboratory { background: linear-gradient(135deg, #0D9488, #0F766E); }
    .badge-role.audit { background: linear-gradient(135deg, #DC2626, #991B1B); }

    /* Status badges */
    .badge-status {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 0.65rem;
        font-weight: 700;
    }

    .badge-status.active { background: #D1FAE5; color: #065F46; }
    .badge-status.inactive { background: #FEE2E2; color: #991B1B; }

    html[data-theme="dark"] .badge-status.active { background: #064E3B; color: #34D399; }
    html[data-theme="dark"] .badge-status.inactive { background: #7F1D1D; color: #FCA5A5; }

    /* Action buttons */
    .actions-cell {
        display: flex;
        gap: 6px;
        align-items: center;
        flex-wrap: wrap;
        justify-content: center;
    }

    .btn-action {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 4px;
        padding: 6px 12px;
        border-radius: 8px;
        border: 1.5px solid;
        cursor: pointer;
        font-size: 0.7rem;
        font-weight: 600;
        transition: all 0.3s ease;
        text-decoration: none;
        white-space: nowrap;
    }

    .btn-action i { font-size: 0.75rem; }

    .btn-action.edit {
        background: var(--page-primary-bg);
        color: var(--page-primary);
        border-color: var(--page-primary);
    }
    html[data-theme="dark"] .btn-action.edit {
        background: #1E3A5F;
        color: #6EA8FE;
        border-color: #3B82F6;
    }
    .btn-action.edit:hover {
        background: var(--page-primary);
        color: white;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(26, 86, 219, 0.3);
    }

    .btn-action.activate {
        background: #D1FAE5;
        color: #065F46;
        border-color: #059669;
    }
    html[data-theme="dark"] .btn-action.activate {
        background: #1A3A2A;
        color: #34D399;
        border-color: #34D399;
    }
    .btn-action.activate:hover {
        background: #059669;
        color: white;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(5, 150, 105, 0.3);
    }

    .btn-action.deactivate {
        background: #FEF3C7;
        color: #92400E;
        border-color: #D97706;
    }
    html[data-theme="dark"] .btn-action.deactivate {
        background: #3D2E0A;
        color: #FBBF24;
        border-color: #D97706;
    }
    .btn-action.deactivate:hover {
        background: #D97706;
        color: white;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(217, 119, 6, 0.3);
    }

    .btn-action.delete {
        background: #FEE2E2;
        color: #991B1B;
        border-color: #DC2626;
    }
    html[data-theme="dark"] .btn-action.delete {
        background: #3A1A1A;
        color: #F87171;
        border-color: #F87171;
    }
    .btn-action.delete:hover {
        background: #DC2626;
        color: white;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(220, 38, 38, 0.3);
    }

    /* ================================================================
       EMPTY STATE
       ================================================================ */
    .empty-state {
        text-align: center;
        padding: 60px 20px;
        color: var(--page-text-secondary);
    }

    .empty-state i {
        font-size: 4rem;
        color: var(--page-border);
        opacity: 0.7;
        margin-bottom: 16px;
        display: block;
    }

    .empty-state h3 {
        font-size: 1.15rem;
        color: var(--page-text-primary);
        margin: 0 0 8px 0;
    }

    .empty-state p {
        margin: 0 0 20px 0;
        font-size: 0.85rem;
    }

    /* ================================================================
       ALERT
       ================================================================ */
    .alert-modern {
        padding: 16px 20px;
        border-radius: 14px;
        margin-bottom: 20px;
        display: flex;
        align-items: flex-start;
        gap: 12px;
        max-width: 1200px;
        margin-left: auto;
        margin-right: auto;
        animation: slideDown 0.4s ease;
    }

    @keyframes slideDown {
        from { opacity: 0; transform: translateY(-10px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .alert-modern-success { background: #D1FAE5; color: #047857; border: 2px solid #059669; }
    .alert-modern-error { background: #FEE2E2; color: #B91C1C; border: 2px solid #DC2626; }

    html[data-theme="dark"] .alert-modern-success { background: #1A3A2A; color: #34D399; border-color: #34D399; }
    html[data-theme="dark"] .alert-modern-error { background: #3A1A1A; color: #F87171; border-color: #F87171; }

    .alert-modern i { font-size: 1.2rem; margin-top: 2px; flex-shrink: 0; }

    /* ================================================================
       ANIMATIONS
       ================================================================ */
    @keyframes fadeInUp {
        from { opacity: 0; transform: translateY(12px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .animate-fade-in-up {
        animation: fadeInUp 0.4s ease forwards;
        opacity: 0;
    }

    /* ================================================================
       RESPONSIVE
       ================================================================ */
    @media (max-width: 1024px) {
        .stats-grid { grid-template-columns: repeat(2, 1fr); }
    }

    @media (max-width: 768px) {
        .page-header-card { padding: 18px 20px; }
        .page-header-title { font-size: 1.2rem; }
        .page-header-title i { width: 36px; height: 36px; font-size: 1rem; }
        .stats-grid { grid-template-columns: 1fr 1fr; gap: 10px; }
        .stat-card { padding: 14px 16px; gap: 10px; }
        .stat-icon { width: 42px; height: 42px; font-size: 1rem; }
        .stat-value { font-size: 1.3rem; }

        /* Mobile table */
        .data-table thead { display: none; }
        .data-table tbody tr {
            display: block;
            border-bottom: 2px solid var(--page-border);
            padding: 12px 0;
        }
        .data-table tbody tr:last-child { border-bottom: none; }
        .data-table tbody td {
            display: block;
            padding: 8px 16px;
            border-bottom: none;
        }
        .data-table tbody td::before {
            content: attr(data-label);
            display: inline-block;
            font-weight: 700;
            font-size: 0.65rem;
            text-transform: uppercase;
            color: var(--page-text-secondary);
            width: 90px;
            letter-spacing: 0.03em;
        }
        .actions-cell { justify-content: flex-start; }
    }

    @media (max-width: 480px) {
        .stats-grid { grid-template-columns: 1fr; }
    }

    /* Print */
    @media print {
        .page-header-actions, .btn, .btn-outline-light, .btn-add, .btn-action, .actions-cell { display: none !important; }
        .page-header-card { background: #1A56DB !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .data-table thead th { background: #1A56DB !important; color: white !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .stat-card, .badge-role, .badge-status { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    }
</style>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <!-- Blue Page Header Card -->
    <div class="page-header-card animate-fade-in-up">
        <div>
            <h1 class="page-header-title">
                <i class="fas fa-users-cog"></i>
                Staff — <?= htmlspecialchars($branch['name']) ?>
                <span class="role-badge-display"><?= strtoupper($user_role) ?></span>
            </h1>
            <p class="page-header-subtitle">
                <i class="fas fa-user-md"></i>
                Manage staff members for this branch
                <span class="page-header-badge">
                    <i class="fas fa-store"></i> Branch #<?= $branch_id ?>
                </span>
                <span class="page-header-badge">
                    <i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($branch['location'] ?? 'N/A') ?>
                </span>
                <?php if (!empty($search_term)): ?>
                    <span class="page-header-badge" style="background:rgba(251,191,36,0.25);">
                        <i class="fas fa-search"></i> "<?= htmlspecialchars($search_term) ?>"
                    </span>
                <?php endif; ?>
            </p>
        </div>
        <div class="page-header-actions">
            <a href="branches.php" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Back to Branches
            </a>
            <a href="add_employee.php?branch=<?= $branch_id ?>" class="btn-outline-light">
                <i class="fas fa-user-plus"></i> Add Staff
            </a>
        </div>
    </div>

    <!-- Message -->
    <?php if ($message): ?>
        <div class="alert-modern alert-modern-<?= $message_type === 'success' ? 'success' : 'error' ?>">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
            <div><?= $message ?></div>
        </div>
    <?php endif; ?>

    <!-- ================================================================
         STAT CARDS - BLUE BACKGROUND
         ================================================================ -->
    <div class="stats-grid animate-fade-in-up" style="animation-delay:0.05s;">
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-users"></i>
            </div>
            <div>
                <p class="stat-label">Total Staff</p>
                <p class="stat-value"><?= $total_staff ?></p>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <div>
                <p class="stat-label">Active</p>
                <p class="stat-value"><?= $active_staff ?></p>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-times-circle"></i>
            </div>
            <div>
                <p class="stat-label">Inactive</p>
                <p class="stat-value"><?= $inactive_staff ?></p>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-user-md"></i>
            </div>
            <div>
                <p class="stat-label">Doctors</p>
                <p class="stat-value"><?= $role_count_map['doctor'] ?? 0 ?></p>
            </div>
        </div>
    </div>

    <!-- ================================================================
         STAFF TABLE
         ================================================================ -->
    <div class="table-card animate-fade-in-up" style="animation-delay:0.1s;">
        <div class="table-card-header">
            <h3 class="table-card-title">
                <i class="fas fa-users"></i>
                Staff Members (<?= $total_staff ?>)
                <?php if (!empty($search_term)): ?>
                    <span style="font-weight:400;font-size:0.75rem;color:var(--page-text-secondary);">
                        — filtered by "<?= htmlspecialchars($search_term) ?>"
                        <a href="?id=<?= $branch_id ?>" style="color:var(--page-primary);text-decoration:none;margin-left:8px;font-weight:700;">
                            <i class="fas fa-times"></i> Clear
                        </a>
                    </span>
                <?php endif; ?>
            </h3>
            <a href="add_employee.php?branch=<?= $branch_id ?>" class="btn-add">
                <i class="fas fa-user-plus"></i> Add Staff
            </a>
        </div>

        <?php if (count($staff_list) > 0): ?>
            <div class="table-scroll">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th style="width:50px;"><i class="fas fa-hashtag"></i> #</th>
                            <th><i class="fas fa-user"></i> Staff</th>
                            <th><i class="fas fa-briefcase"></i> Role</th>
                            <th><i class="fas fa-envelope"></i> Email</th>
                            <th><i class="fas fa-phone"></i> Phone</th>
                            <th><i class="fas fa-circle"></i> Status</th>
                            <th style="text-align:center;"><i class="fas fa-cog"></i> Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $counter = 1; foreach ($staff_list as $staff): 
                            $staff_id = $staff['id'] ?? 0;
                            $staff_name = htmlspecialchars($staff['full_name'] ?? 'Unknown');
                            $staff_role = $staff['role'] ?? 'unknown';
                            $staff_email = htmlspecialchars($staff['email'] ?? 'N/A');
                            $staff_phone = htmlspecialchars($staff['phone'] ?? 'N/A');
                            $staff_status = $staff['status'] ?? 'inactive';
                            $staff_pic = $staff['profile_pic'] ?? '';

                            $role_badge_class = 'badge-role ' . $staff_role;
                            $role_icons = [
                                'admin' => 'fa-user-tie',
                                'doctor' => 'fa-user-md',
                                'reception' => 'fa-headset',
                                'pharmacy' => 'fa-prescription-bottle',
                                'cashier' => 'fa-cash-register',
                                'laboratory' => 'fa-flask',
                                'audit' => 'fa-clipboard-check'
                            ];
                            $role_icon = $role_icons[$staff_role] ?? 'fa-user';
                        ?>
                            <tr>
                                <td data-label="#"><?= $counter++ ?></td>
                                <td data-label="Staff">
                                    <div style="display:flex;align-items:center;gap:12px;">
                                        <?php if (!empty($staff_pic)): ?>
                                            <img src="/dispensary_system/frontend/assets/uploads/profiles/<?= $staff_pic ?>" 
                                                 alt="<?= $staff_name ?>" 
                                                 class="staff-avatar"
                                                 onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                            <div class="staff-avatar-placeholder" style="display:none;"><?= substr($staff_name, 0, 1) ?></div>
                                        <?php else: ?>
                                            <div class="staff-avatar-placeholder"><?= substr($staff_name, 0, 1) ?></div>
                                        <?php endif; ?>
                                        <div>
                                            <div style="font-weight:600;color:var(--page-text-primary);"><?= $staff_name ?></div>
                                            <div style="font-size:0.68rem;color:var(--page-text-secondary);">ID: #<?= $staff_id ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td data-label="Role">
                                    <span class="<?= $role_badge_class ?>">
                                        <i class="fas <?= $role_icon ?>"></i>
                                        <?= ucfirst($staff_role) ?>
                                    </span>
                                </td>
                                <td data-label="Email">
                                    <a href="mailto:<?= $staff_email ?>" style="color:var(--page-primary);text-decoration:none;font-weight:500;">
                                        <?= $staff_email ?>
                                    </a>
                                </td>
                                <td data-label="Phone"><?= $staff_phone ?></td>
                                <td data-label="Status">
                                    <span class="badge-status <?= $staff_status === 'active' ? 'active' : 'inactive' ?>">
                                        <i class="fas fa-<?= $staff_status === 'active' ? 'circle' : 'times-circle' ?>"></i>
                                        <?= ucfirst($staff_status) ?>
                                    </span>
                                </td>
                                <td data-label="Actions">
                                    <div class="actions-cell">
                                        <a href="edit_staff.php?id=<?= $staff_id ?>&branch_id=<?= $branch_id ?>" 
                                           class="btn-action edit" title="Edit Staff">
                                            <i class="fas fa-edit"></i> Edit
                                        </a>
                                        <?php if ($staff_status === 'active'): ?>
                                            <button onclick="toggleStaffStatus(<?= $staff_id ?>, 'inactive')" 
                                                    class="btn-action deactivate" title="Deactivate">
                                                <i class="fas fa-pause"></i> Deactivate
                                            </button>
                                        <?php else: ?>
                                            <button onclick="toggleStaffStatus(<?= $staff_id ?>, 'active')" 
                                                    class="btn-action activate" title="Activate">
                                                <i class="fas fa-play"></i> Activate
                                            </button>
                                        <?php endif; ?>
                                        <button onclick="deleteStaff(<?= $staff_id ?>, '<?= addslashes($staff_name) ?>')" 
                                                class="btn-action delete" title="Delete">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-user-slash"></i>
                <h3>No Staff Found</h3>
                <p><?= !empty($search_term) ? 'No staff members match your search criteria.' : 'This branch currently has no staff members assigned.' ?></p>
                <?php if (!empty($search_term)): ?>
                    <a href="?id=<?= $branch_id ?>" class="btn-add" style="display:inline-flex;">
                        <i class="fas fa-times"></i> Clear Search
                    </a>
                <?php else: ?>
                    <a href="add_employee.php?branch=<?= $branch_id ?>" class="btn-add" style="display:inline-flex;">
                        <i class="fas fa-user-plus"></i> Add First Staff
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

</main>

<!-- Hidden forms for POST actions -->
<form id="toggleStatusForm" method="POST" action="" style="display:none;">
    <input type="hidden" name="action" value="toggle_status">
    <input type="hidden" name="staff_id" id="toggleStaffId" value="0">
    <input type="hidden" name="status" id="toggleStaffStatus" value="">
</form>

<form id="deleteStaffForm" method="POST" action="" style="display:none;">
    <input type="hidden" name="action" value="delete_staff">
    <input type="hidden" name="staff_id" id="deleteStaffId" value="0">
</form>

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
    document.addEventListener('darkModeChanged', function() { setTimeout(enforceDarkModeBackground, 50); });
    new MutationObserver(function(mutations) {
        mutations.forEach(function(m) {
            if (m.attributeName === 'data-theme') enforceDarkModeBackground();
        });
    }).observe(document.documentElement, { attributes: true });

    // ================================================================
    // TOGGLE STAFF STATUS
    // ================================================================
    function toggleStaffStatus(staffId, status) {
        var action = status === 'active' ? 'activate' : 'deactivate';
        if (confirm('Are you sure you want to ' + action + ' this staff member?')) {
            document.getElementById('toggleStaffId').value = staffId;
            document.getElementById('toggleStaffStatus').value = status;
            document.getElementById('toggleStatusForm').submit();
        }
    }

    // ================================================================
    // DELETE STAFF
    // ================================================================
    function deleteStaff(staffId, staffName) {
        if (confirm('Are you sure you want to delete staff member: "' + staffName + '"?\n\nThis action cannot be undone.')) {
            document.getElementById('deleteStaffId').value = staffId;
            document.getElementById('deleteStaffForm').submit();
        }
    }

    console.log('%c👥 Braick - Branch Staff', 'font-size:18px; font-weight:bold; color:#1A56DB;');
    console.log('%c✅ Uses SHARED header & sidebar', 'font-size:13px; color:#059669;');
    console.log('%c🎨 Blue stat cards + table header', 'font-size:13px; color:#1A56DB;');
    console.log('%c🌙 FULL DARK MODE works', 'font-size:13px; color:#7C3AED;');
</script>

</body>
</html>