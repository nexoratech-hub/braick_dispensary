<?php
// ================================================================
// FILE: frontend/pages/admin/employees.php
// SUPER ADMIN - EMPLOYEES MANAGEMENT
// FIXED: Branch column standard size + Branch name updates
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
// BRANCH SELECTION WITH PROPER NAME
// ================================================================
$selected_branch_id = $_GET['branch'] ?? 'all';
$branch_name = 'All Branches';
$current_branch_name_display = 'All Branches';

if ($selected_branch_id !== 'all' && is_numeric($selected_branch_id)) {
    $branch_id = (int)$selected_branch_id;
    $stmt = $db->prepare("SELECT id, name FROM branches WHERE id = ?");
    $stmt->execute([$branch_id]);
    $branch_data = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($branch_data) {
        $branch_name = $branch_data['name'];
        $current_branch_name_display = $branch_data['name'];
    }
} else {
    $selected_branch_id = 'all';
    $current_branch_name_display = 'All Branches';
}

// Get branches
$branches = [];
$stmt = $db->query("SELECT id, name, location FROM branches WHERE status = 'active' ORDER BY name");
$branches = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Unread notifications
$unread_notifications = 0;
try {
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$user_id]);
    $unread_notifications = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
} catch (Exception $e) {
    $unread_notifications = 0;
}

$message = '';
$message_type = '';

// HANDLE DEACTIVATE
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

// HANDLE REACTIVATE
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
// FETCH EMPLOYEES WITH BRANCH FILTER
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

// COUNT BY ROLE
$total_active = 0;
$total_inactive = 0;
$doctors = 0;
$receptionists = 0;
$pharmacists = 0;
$lab_technicians = 0;
$cashiers = 0;

foreach ($employees as $emp) {
    if ($emp['status'] === 'active') {
        $total_active++;
        switch ($emp['role']) {
            case 'doctor': $doctors++; break;
            case 'reception': $receptionists++; break;
            case 'pharmacy': $pharmacists++; break;
            case 'laboratory': $lab_technicians++; break;
            case 'cashier': $cashiers++; break;
        }
    } else {
        $total_inactive++;
    }
}

$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

$logo_url = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

include_once __DIR__ . '/../../components/admin_header.php';
include_once __DIR__ . '/../../components/admin_sidebar.php';
?>

<style>
/* ================================================================ */
/* SIDEBAR - Full width on mobile */
/* ================================================================ */
.sidebar {
    position: fixed;
    top: 0;
    left: 0;
    bottom: 0;
    width: 270px;
    transform: translateX(0);
    transition: transform 0.35s cubic-bezier(0.4, 0, 0.2, 1);
    z-index: 50;
    overflow-y: auto;
}

.main-content {
    margin-left: 270px;
    margin-top: 68px;
    padding: 24px 28px;
    min-height: calc(100vh - 68px);
    transition: margin-left 0.35s cubic-bezier(0.4, 0, 0.2, 1);
}

.top-nav {
    position: fixed;
    top: 0;
    left: 270px;
    right: 0;
    height: 68px;
    transition: left 0.35s cubic-bezier(0.4, 0, 0.2, 1);
    z-index: 40;
}

@media (max-width: 1024px) {
    .sidebar {
        transform: translateX(-100%);
        width: 280px;
        border-radius: 0 12px 12px 0;
        box-shadow: 4px 0 20px rgba(0,0,0,0.3);
        z-index: 9999;
    }
    
    .sidebar.open { transform: translateX(0) !important; }
    
    .main-content {
        margin-left: 0 !important;
        padding: 16px;
    }
    
    .top-nav { left: 0 !important; }
    
    #sidebarOverlay {
        position: fixed;
        top: 0; left: 0; right: 0; bottom: 0;
        background: rgba(0,0,0,0.5);
        z-index: 9998;
        display: none;
        backdrop-filter: blur(4px);
    }
    
    #sidebarOverlay.active { display: block !important; }
}

/* ================================================================ */
/* TABLE HEADER - SEARCH BOX SMALL + BLUE BACKGROUND */
/* ================================================================ */
.table-header-wrapper {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    margin-bottom: 14px;
    padding-bottom: 12px;
    border-bottom: 2px solid var(--border-color);
}

.table-header-left {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}

/* SEARCH BOX - SMALL with BLUE BACKGROUND */
.table-search-box {
    position: relative;
    width: 220px;
    flex-shrink: 0;
}

.table-search-box i {
    position: absolute;
    left: 12px;
    top: 50%;
    transform: translateY(-50%);
    color: rgba(255,255,255,0.9);
    font-size: 0.75rem;
    pointer-events: none;
}

.table-search-box input {
    width: 100%;
    padding: 8px 12px 8px 34px;
    border: 2px solid #0A4CA8;
    border-radius: 8px;
    font-size: 0.75rem;
    background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
    color: white;
    outline: none;
    transition: all 0.3s ease;
    font-weight: 500;
    height: 36px;
    box-shadow: 0 3px 10px rgba(11, 94, 215, 0.25);
}

.table-search-box input::placeholder {
    color: rgba(255,255,255,0.85);
    font-size: 0.72rem;
}

.table-search-box input:focus {
    box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.25), 0 4px 14px rgba(11, 94, 215, 0.35);
    transform: translateY(-1px);
}

.table-search-box input:hover {
    box-shadow: 0 5px 16px rgba(11, 94, 215, 0.35);
}

.table-title-inline {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 0.8rem;
    font-weight: 600;
    color: var(--text-primary);
    white-space: nowrap;
}

.search-results-info {
    font-size: 0.68rem;
    color: #0B5ED7;
    padding: 5px 10px;
    background: #E8F0FE;
    border-radius: 8px;
    white-space: nowrap;
    display: none;
    font-weight: 600;
    border: 1px solid #0B5ED7;
}

[data-theme="dark"] .search-results-info {
    background: #1E3A5F;
    color: #6EA8FE;
    border-color: #6EA8FE;
}

.search-results-info strong { font-size: 0.75rem; }

/* ================================================================ */
/* ✅ BRANCH BADGE - STANDARD SIZE */
/* ================================================================ */
.branch-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 3px 10px;
    border-radius: 14px;
    font-size: 0.65rem;
    font-weight: 600;
    background: linear-gradient(135deg, #E8F0FE, #D4E4FF);
    color: #0B5ED7;
    border: 1.5px solid #0B5ED7;
    box-shadow: 0 1px 3px rgba(11, 94, 215, 0.12);
    white-space: nowrap;
    transition: all 0.3s ease;
    max-width: 120px;
}

.branch-badge i {
    font-size: 0.7rem;
    color: #0B5ED7;
    flex-shrink: 0;
}

.branch-badge:hover {
    transform: translateY(-1px);
    box-shadow: 0 2px 6px rgba(11, 94, 215, 0.2);
    background: linear-gradient(135deg, #D4E4FF, #C4D9FF);
}

[data-theme="dark"] .branch-badge {
    background: linear-gradient(135deg, #1E3A5F, #2A4A6F);
    color: #6EA8FE;
    border-color: #6EA8FE;
}

[data-theme="dark"] .branch-badge i {
    color: #6EA8FE;
}

/* ================================================================ */
/* HEADER BRANCH TAG */
/* ================================================================ */
.branch-header-tag {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 6px 18px;
    border-radius: 24px;
    font-size: 0.9rem;
    font-weight: 700;
    background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
    color: white !important;
    border: 2px solid rgba(255,255,255,0.2);
    box-shadow: 0 3px 10px rgba(11, 94, 215, 0.3);
    margin-left: 8px;
    white-space: nowrap;
}

.branch-header-tag i {
    font-size: 1rem;
    opacity: 0.9;
}

[data-theme="dark"] .branch-header-tag {
    background: linear-gradient(135deg, #1A73E8, #0B5ED7);
}

/* ================================================================ */
/* STAT CARDS */
/* ================================================================ */
.stat-card {
    border-radius: 12px;
    padding: 16px 20px;
    border: none;
    transition: all 0.3s ease;
    color: white;
    min-height: 100px;
    display: block;
    position: relative;
    overflow: hidden;
}

.stat-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.15);
}

.stat-card.solid-blue { background: #0B5ED7; }
.stat-card.solid-green { background: #059669; }
.stat-card.solid-dark-blue { background: #0A4CA8; }
.stat-card.solid-purple { background: #7B2FBE; }
.stat-card.solid-orange { background: #F59E0B; }
.stat-card.solid-gray { background: #64748B; }

.stat-card .stat-icon {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
    background: rgba(255,255,255,0.2);
    color: white;
    flex-shrink: 0;
}

.stat-card .stat-number {
    font-size: 1.6rem;
    font-weight: 700;
    color: white;
    line-height: 1.2;
}

.stat-card .stat-label {
    font-size: 0.75rem;
    color: rgba(255,255,255,0.9);
    font-weight: 500;
    margin-bottom: 2px;
}

/* TABLE HEADER */
.data-table thead th {
    background: #0B5ED7 !important;
    color: white !important;
    font-weight: 600;
    padding: 10px 12px;
    font-size: 0.6rem;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    border-bottom: none !important;
}

.data-table thead th:first-child { border-radius: 8px 0 0 0; }
.data-table thead th:last-child { border-radius: 0 8px 0 0; }

.inactive-row {
    opacity: 0.6;
    background: #F8FAFC !important;
}

.inactive-row td { border-bottom-color: #E2E8F0 !important; }

[data-theme="dark"] .inactive-row {
    opacity: 0.5;
    background: #1E293B !important;
}

[data-theme="dark"] .inactive-row td { border-bottom-color: #334155 !important; }

.action-buttons {
    display: flex;
    flex-direction: row;
    gap: 4px;
    align-items: center;
    justify-content: center;
    flex-wrap: nowrap;
}

.action-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 30px;
    height: 30px;
    padding: 0;
    border-radius: 6px;
    font-size: 0.7rem;
    transition: all 0.3s;
    cursor: pointer;
    border: none;
    text-decoration: none;
    flex-shrink: 0;
}

.action-btn i { font-size: 0.8rem; }

.action-btn:hover {
    transform: scale(1.1);
    box-shadow: 0 2px 8px rgba(0,0,0,0.2);
}

.action-btn:active { transform: scale(0.95); }

.role-badge {
    display: inline-block;
    padding: 2px 10px;
    border-radius: 20px;
    font-size: 0.6rem;
    font-weight: 600;
    text-transform: uppercase;
}

.role-badge.role-doctor { background: #E8F0FE; color: #0B5ED7; }
.role-badge.role-reception { background: #D1FAE5; color: #059669; }
.role-badge.role-pharmacy { background: #FEF3C7; color: #D97706; }
.role-badge.role-laboratory { background: #EDE9FE; color: #7B2FBE; }
.role-badge.role-cashier { background: #FCE4EC; color: #DC2626; }

[data-theme="dark"] .role-badge.role-doctor { background: #1E3A5F; color: #6EA8FE; }
[data-theme="dark"] .role-badge.role-reception { background: #1A3A2A; color: #34D399; }
[data-theme="dark"] .role-badge.role-pharmacy { background: #3D2E0A; color: #FBBF24; }
[data-theme="dark"] .role-badge.role-laboratory { background: #2D1B4E; color: #A78BFA; }
[data-theme="dark"] .role-badge.role-cashier { background: #3A1A1A; color: #F87171; }

.status-badge {
    display: inline-block;
    padding: 2px 10px;
    border-radius: 20px;
    font-size: 0.55rem;
    font-weight: 600;
    text-transform: uppercase;
}

.status-badge.active { background: #D1FAE5; color: #059669; }
.status-badge.inactive { background: #FEE2E2; color: #DC2626; }

[data-theme="dark"] .status-badge.active { background: #1A3A2A; color: #34D399; }
[data-theme="dark"] .status-badge.inactive { background: #3A1A1A; color: #F87171; }

.btn {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 4px 10px;
    border-radius: 6px;
    font-weight: 600;
    font-size: 0.65rem;
    transition: all 0.3s;
    cursor: pointer;
    border: none;
    text-decoration: none;
}

.btn-sm { padding: 2px 6px; font-size: 0.6rem; }
.btn-view { background: #0B5ED7; color: white; }
.btn-view:hover { background: #0A4CA8; transform: scale(1.05); }
.btn-edit { background: #F59E0B; color: white; }
.btn-edit:hover { background: #D97706; transform: scale(1.05); }
.btn-delete { background: #EF4444; color: white; }
.btn-delete:hover { background: #DC2626; transform: scale(1.05); }
.btn-reactivate { background: #059669; color: white; }
.btn-reactivate:hover { background: #047857; transform: scale(1.05); }
.btn-danger { background: #EF4444; color: white; }
.btn-danger:hover { background: #DC2626; }
.btn-success { background: #059669; color: white; }
.btn-success:hover { background: #047857; }

.btn-blue { background: #0B5ED7; color: white; }
.btn-blue:hover {
    background: #0A4CA8;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3);
}

.btn-outline {
    background: transparent;
    color: var(--text-secondary);
    border: 2px solid var(--border-color);
}
.btn-outline:hover {
    background: var(--bg-body);
    border-color: #0B5ED7;
    color: #0B5ED7;
}

.message-box {
    padding: 12px 16px;
    border-radius: 10px;
    margin-bottom: 16px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.message-box.success {
    background: #D1FAE5;
    color: #059669;
    border: 1px solid #059669;
}

.message-box.error {
    background: #FEE2E2;
    color: #DC2626;
    border: 1px solid #DC2626;
}

[data-theme="dark"] .message-box.success {
    background: #1A3A2A;
    color: #34D399;
    border-color: #34D399;
}

[data-theme="dark"] .message-box.error {
    background: #3A1A1A;
    color: #F87171;
    border-color: #F87171;
}

.modal {
    position: fixed;
    top: 0; left: 0; right: 0; bottom: 0;
    z-index: 99999;
    display: flex;
    align-items: center;
    justify-content: center;
}

.modal-overlay {
    position: absolute;
    top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(0,0,0,0.5);
    cursor: pointer;
}

.modal-content {
    background: var(--bg-card);
    border-radius: 16px;
    max-width: 480px;
    width: 90%;
    position: relative;
    z-index: 100000;
    box-shadow: 0 20px 60px rgba(0,0,0,0.3);
    border: 1px solid var(--border-color);
}

.modal-header {
    padding: 16px 20px;
    border-bottom: 1px solid var(--border-color);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-header h3 {
    font-size: 1rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0;
}

.modal-close {
    background: none;
    border: none;
    font-size: 1.5rem;
    cursor: pointer;
    color: var(--text-secondary);
    padding: 0 4px;
}

.modal-close:hover { color: var(--text-primary); }
.modal-body { padding: 20px; color: var(--text-primary); }
.modal-footer {
    padding: 16px 20px;
    border-top: 1px solid var(--border-color);
    display: flex;
    justify-content: flex-end;
    gap: 8px;
}

.toast-custom {
    position: fixed;
    bottom: 24px;
    right: 24px;
    padding: 14px 20px;
    border-radius: 12px;
    z-index: 99999;
    max-width: 400px;
    transform: translateY(100px);
    opacity: 0;
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    display: flex;
    align-items: center;
    gap: 12px;
    color: white;
    box-shadow: 0 8px 30px rgba(0,0,0,0.15);
}
.toast-custom.show { transform: translateY(0); opacity: 1; }
.toast-custom.success { background: #059669; }
.toast-custom.error { background: #DC2626; }
.toast-custom.info { background: #0B5ED7; }
.toast-custom.warning { background: #D97706; }

@media (max-width: 768px) {
    .stat-card {
        min-height: 80px !important;
        padding: 12px 14px !important;
    }
    .stat-card .stat-number { font-size: 1.2rem !important; }
    .stat-card .stat-icon {
        width: 32px !important;
        height: 32px !important;
        font-size: 0.8rem !important;
    }
    .grid-cols-6 { grid-template-columns: repeat(3, 1fr) !important; }
    .action-btn {
        width: 26px !important;
        height: 26px !important;
    }
    .action-btn i { font-size: 0.65rem !important; }
    .data-table thead th {
        padding: 6px 8px !important;
        font-size: 0.5rem !important;
    }
    .data-table td {
        padding: 6px 8px !important;
        font-size: 0.7rem !important;
    }
    .btn-blue {
        padding: 8px 18px !important;
        font-size: 0.85rem !important;
        min-height: 38px !important;
    }
    
    .table-header-wrapper {
        flex-direction: column;
        align-items: stretch;
    }
    
    .table-header-left {
        width: 100%;
        flex-direction: column;
        align-items: stretch;
    }
    
    .table-search-box {
        width: 100%;
        max-width: 100%;
    }
    
    .branch-header-tag {
        font-size: 0.75rem;
        padding: 4px 12px;
    }
}

@media (max-width: 480px) {
    .grid-cols-6 { grid-template-columns: repeat(2, 1fr) !important; }
    .action-btn {
        width: 22px !important;
        height: 22px !important;
    }
    .action-btn i { font-size: 0.55rem !important; }
    .action-buttons { gap: 2px !important; }
    .btn-blue {
        padding: 6px 14px !important;
        font-size: 0.8rem !important;
        min-height: 34px !important;
    }
    .branch-header-tag {
        font-size: 0.7rem;
        padding: 3px 10px;
    }
}
</style>

<!-- SIDEBAR OVERLAY -->
<div id="sidebarOverlay"></div>

<!-- TOP NAV -->
<nav class="top-nav">
    <div class="flex items-center gap-4 flex-1">
        <button id="sidebarToggle" class="lg:hidden icon-btn">
            <i class="fas fa-bars text-lg"></i>
        </button>
        
        <div class="search-wrapper">
            <i class="fas fa-search text-gray-400 ml-3"></i>
            <input type="text" id="searchInput" placeholder="Search employees...">
            <button id="searchBtn" class="search-btn">
                <i class="fas fa-search mr-1"></i> Search
            </button>
        </div>
    </div>
    
    <div class="flex items-center gap-3">
        <select id="branchSelector" class="branch-selector" onchange="switchBranch(this.value)">
            <option value="all" <?= $selected_branch_id === 'all' ? 'selected' : '' ?>>🌐 All Branches</option>
            <?php foreach ($branches as $branch): ?>
                <option value="<?= $branch['id'] ?>" <?= $selected_branch_id == $branch['id'] ? 'selected' : '' ?>>
                    🏥 <?= htmlspecialchars($branch['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        
        <span class="datetime" id="currentDateTime"></span>
        
        <button id="darkModeToggle" class="dark-toggle-btn">
            <i id="darkIcon" class="fas fa-moon"></i>
            <span id="darkText">Dark</span>
        </button>
        
        <button class="icon-btn">
            <i class="fas fa-bell text-lg"></i>
            <span class="notif-dot <?= $unread_notifications > 0 ? 'has-notif' : 'no-notif' ?>"></span>
        </button>
        
        <a href="profile.php">
            <img src="<?= $profile_pic_url ?>" alt="Profile" class="avatar"
                 onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2240%22 height=%2240%22%3E%3Crect width=%2240%22 height=%2240%22 fill=%22%230B5ED7%22 rx=%2250%25%22/%3E%3Ctext x=%2220%22 y=%2226%22 text-anchor=%22middle%22 fill=%22white%22 font-size=%2218%22 font-weight=%22bold%22%3E<?= strtoupper(substr($user_full_name, 0, 1)) ?>%3C/text%3E%3C/svg%3E'">
        </a>
    </div>
</nav>

<!-- MAIN CONTENT -->
<main class="main-content">

    <!-- Page Header -->
    <div class="page-header flex flex-wrap justify-between items-center gap-3 mb-5">
        <div>
            <h1 class="page-title">
                <i class="fas fa-users-cog mr-2"></i> Employees Management
                <span class="branch-header-tag" id="headerBranchTag">
                    <i class="fas fa-store-alt"></i> 
                    <span id="headerBranchName"><?= htmlspecialchars($current_branch_name_display) ?></span>
                </span>
            </h1>
            <p class="page-subtitle">
                Manage all employees across branches
            </p>
        </div>
        <div class="flex gap-2 flex-wrap">
            <a href="add_employee.php?branch=<?= $selected_branch_id ?>" class="btn btn-blue" style="padding: 10px 24px; font-size: 0.95rem; border-radius: 10px; min-height: 44px;">
                <i class="fas fa-user-plus"></i> Add Employee
            </a>
            <button onclick="location.reload()" class="btn btn-outline btn-sm" style="padding: 8px 16px; border-radius: 10px; min-height: 38px; font-size: 0.85rem;">
                <i class="fas fa-sync-alt"></i> Refresh
            </button>
        </div>
    </div>

    <!-- Message -->
    <?php if ($message): ?>
        <div class="message-box <?= $message_type === 'success' ? 'success' : 'error' ?>">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
            <?= $message ?>
        </div>
    <?php endif; ?>

    <!-- STATISTICS CARDS -->
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4 mb-5">
        <div class="stat-card solid-blue">
            <div class="flex items-center justify-between">
                <div>
                    <p class="stat-label">Total Active</p>
                    <p class="stat-number"><?= number_format($total_active) ?></p>
                </div>
                <div class="stat-icon"><i class="fas fa-user-check"></i></div>
            </div>
        </div>
        
        <div class="stat-card solid-gray">
            <div class="flex items-center justify-between">
                <div>
                    <p class="stat-label">Inactive</p>
                    <p class="stat-number"><?= number_format($total_inactive) ?></p>
                </div>
                <div class="stat-icon"><i class="fas fa-user-slash"></i></div>
            </div>
        </div>
        
        <div class="stat-card solid-green">
            <div class="flex items-center justify-between">
                <div>
                    <p class="stat-label">Doctors</p>
                    <p class="stat-number"><?= number_format($doctors) ?></p>
                </div>
                <div class="stat-icon"><i class="fas fa-user-md"></i></div>
            </div>
        </div>
        
        <div class="stat-card solid-dark-blue">
            <div class="flex items-center justify-between">
                <div>
                    <p class="stat-label">Receptionists</p>
                    <p class="stat-number"><?= number_format($receptionists) ?></p>
                </div>
                <div class="stat-icon"><i class="fas fa-headset"></i></div>
            </div>
        </div>
        
        <div class="stat-card solid-purple">
            <div class="flex items-center justify-between">
                <div>
                    <p class="stat-label">Pharmacy</p>
                    <p class="stat-number"><?= number_format($pharmacists) ?></p>
                </div>
                <div class="stat-icon"><i class="fas fa-pills"></i></div>
            </div>
        </div>
        
        <div class="stat-card solid-orange">
            <div class="flex items-center justify-between">
                <div>
                    <p class="stat-label">Lab & Cashier</p>
                    <p class="stat-number"><?= number_format($lab_technicians + $cashiers) ?></p>
                </div>
                <div class="stat-icon"><i class="fas fa-flask"></i></div>
            </div>
        </div>
    </div>

    <!-- EMPLOYEES TABLE -->
    <div class="card" style="background:var(--bg-card);border-radius:14px;padding:18px 20px;border:2px solid var(--border-color);">
        <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;flex-wrap:wrap;gap:8px;">
            <h3 class="card-title" style="font-size:1rem;font-weight:600;color:var(--text-primary);">
                <i class="fas fa-list" style="color:#0B5ED7;"></i> Employee List
            </h3>
        </div>
        
        <!-- TABLE HEADER -->
        <div class="table-header-wrapper">
            <div class="table-header-left">
                <div class="table-search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" id="tableSearch" placeholder="🔍 Search..." autocomplete="off">
                </div>
                
                <div class="table-title-inline">
                    <i class="fas fa-users" style="color:#0B5ED7;"></i>
                    <span>Total:</span>
                    <span style="color:#0B5ED7;" id="visibleCount"><?= number_format(count($employees)) ?></span>
                </div>
                
                <span class="search-results-info" id="searchInfo">
                    <i class="fas fa-filter"></i> <strong id="matchCount">0</strong> match
                </span>
            </div>
            
            <div class="table-header-right">
                <span class="branch-badge" id="tableBranchBadge">
                    <i class="fas fa-store-alt"></i>
                    <span id="tableBranchName"><?= htmlspecialchars($current_branch_name_display) ?></span>
                </span>
            </div>
        </div>
        
        <div class="overflow-x-auto" style="overflow-x:auto;">
            <table class="data-table" id="employeesTable" style="width:100%;border-collapse:collapse;font-size:0.75rem;">
                <thead>
                    <tr>
                        <th style="width: 40px;">#</th>
                        <th>Employee</th>
                        <th>Role</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th style="width: 130px; min-width: 130px;">Branch</th>
                        <th>Status</th>
                        <th style="width: 120px; min-width: 120px;">Actions</th>
                    </tr>
                </thead>
                <tbody id="tableBody">
                    <?php if (count($employees) > 0): ?>
                        <?php $i = 1; foreach ($employees as $emp): ?>
                            <tr class="emp-row <?= $emp['status'] === 'inactive' ? 'inactive-row' : '' ?>" 
                                data-branch-id="<?= $emp['branch_id'] ?>"
                                data-branch-name="<?= htmlspecialchars($emp['branch_name'] ?? 'N/A') ?>"
                                data-search="<?= strtolower(htmlspecialchars($emp['full_name'] . ' ' . $emp['username'] . ' ' . $emp['email'] . ' ' . ($emp['phone'] ?? '') . ' ' . ($emp['branch_name'] ?? '') . ' ' . $emp['role'] . ' ' . $emp['status'])) ?>">
                                <td style="padding:6px 12px;border-bottom:1px solid var(--border-color);"><?= $i++ ?></td>
                                <td style="padding:6px 12px;border-bottom:1px solid var(--border-color);">
                                    <div class="flex items-center gap-3" style="display:flex;align-items:center;gap:10px;">
                                        <?php if (!empty($emp['profile_pic'])): ?>
                                            <img src="/dispensary_system/frontend/assets/uploads/profiles/<?= $emp['profile_pic'] ?>" 
                                                 alt="<?= htmlspecialchars($emp['full_name']) ?>" 
                                                 style="width:32px;height:32px;border-radius:50%;object-fit:cover;"
                                                 onerror="this.style.display='none'">
                                        <?php else: ?>
                                            <div style="width:32px;height:32px;border-radius:50%;background:#E8F0FE;color:#0B5ED7;display:flex;align-items:center;justify-content:center;font-size:0.85rem;font-weight:700;">
                                                <?= strtoupper(substr($emp['full_name'], 0, 1)) ?>
                                            </div>
                                        <?php endif; ?>
                                        <div>
                                            <p style="font-weight:500;font-size:0.8rem;margin:0;"><?= htmlspecialchars($emp['full_name']) ?></p>
                                            <p style="font-size:0.65rem;color:var(--text-secondary);margin:0;">@<?= htmlspecialchars($emp['username']) ?></p>
                                        </div>
                                    </div>
                                </td>
                                <td style="padding:6px 12px;border-bottom:1px solid var(--border-color);">
                                    <span class="role-badge role-<?= $emp['role'] ?>">
                                        <?php
                                            $role_labels = [
                                                'doctor' => 'Doctor',
                                                'reception' => 'Reception',
                                                'pharmacy' => 'Pharmacy',
                                                'laboratory' => 'Lab Tech',
                                                'cashier' => 'Cashier'
                                            ];
                                            echo $role_labels[$emp['role']] ?? ucfirst($emp['role']);
                                        ?>
                                    </span>
                                </td>
                                <td style="padding:6px 12px;border-bottom:1px solid var(--border-color);font-size:0.75rem;"><?= htmlspecialchars($emp['email']) ?></td>
                                <td style="padding:6px 12px;border-bottom:1px solid var(--border-color);font-size:0.75rem;"><?= htmlspecialchars($emp['phone'] ?? 'N/A') ?></td>
                                <td style="padding:6px 12px;border-bottom:1px solid var(--border-color);width:130px;">
                                    <span class="branch-badge">
                                        <i class="fas fa-store-alt"></i>
                                        <?= htmlspecialchars($emp['branch_name'] ?? 'N/A') ?>
                                    </span>
                                </td>
                                <td style="padding:6px 12px;border-bottom:1px solid var(--border-color);">
                                    <span class="status-badge <?= $emp['status'] === 'active' ? 'active' : 'inactive' ?>">
                                        <?= $emp['status'] === 'active' ? 'Active' : 'Inactive' ?>
                                    </span>
                                </td>
                                <td style="padding:6px 12px;border-bottom:1px solid var(--border-color);">
                                    <div class="action-buttons">
                                        <a href="view_employee.php?id=<?= $emp['id'] ?>&branch=<?= $selected_branch_id ?>" 
                                           class="btn btn-sm btn-view action-btn" title="View Employee">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="edit_employee.php?id=<?= $emp['id'] ?>&branch=<?= $selected_branch_id ?>" 
                                           class="btn btn-sm btn-edit action-btn" title="Edit Employee">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <?php if ($emp['status'] === 'active'): ?>
                                            <button onclick="confirmDelete(<?= $emp['id'] ?>, '<?= htmlspecialchars(addslashes($emp['full_name'])) ?>')" 
                                                    class="btn btn-sm btn-delete action-btn" title="Deactivate Employee">
                                                <i class="fas fa-user-slash"></i>
                                            </button>
                                        <?php else: ?>
                                            <button onclick="confirmReactivate(<?= $emp['id'] ?>, '<?= htmlspecialchars(addslashes($emp['full_name'])) ?>')" 
                                                    class="btn btn-sm btn-reactivate action-btn" title="Reactivate Employee">
                                                <i class="fas fa-undo"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <tr class="no-results-row" id="noResults" style="display:none;">
                            <td colspan="8" style="text-align:center;padding:30px;color:var(--text-secondary);">
                                <i class="fas fa-search-minus" style="font-size:2rem;color:var(--border-color);display:block;margin-bottom:8px;"></i>
                                <p style="font-size:0.9rem;">No employees match your search</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" style="text-align:center;color:var(--text-secondary);font-size:0.85rem;padding:30px;">
                                <i class="fas fa-users" style="font-size:2rem;display:block;margin-bottom:8px;"></i>
                                No employees found in <?= htmlspecialchars($current_branch_name_display) ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- FOOTER -->
    <footer class="footer" style="padding:14px 0;border-top:1px solid var(--border-color);margin-top:24px;text-align:center;font-size:0.7rem;color:var(--text-secondary);">
        <p>
            <span style="color:#0B5ED7;font-weight:600;">Braick Dispensary</span> Management System
            <span>|</span>
            Employees Management
            <span>|</span>
            <span id="footerTime"><?= date('H:i:s') ?></span>
            <span>|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<!-- DELETE MODAL -->
<div id="deleteModal" class="modal" style="display:none;">
    <div class="modal-overlay" onclick="closeModal()"></div>
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-exclamation-triangle text-red-500 mr-2"></i> Deactivate Employee</h3>
            <button onclick="closeModal()" class="modal-close">&times;</button>
        </div>
        <div class="modal-body">
            <p>Are you sure you want to deactivate <strong id="deleteName"></strong>?</p>
            <p class="text-sm text-gray-500 mt-2">
                <i class="fas fa-info-circle"></i> 
                This employee will no longer be able to login.
            </p>
        </div>
        <div class="modal-footer">
            <button onclick="closeModal()" class="btn btn-outline btn-sm">Cancel</button>
            <a href="#" id="deleteLink" class="btn btn-danger btn-sm">
                <i class="fas fa-user-slash"></i> Deactivate
            </a>
        </div>
    </div>
</div>

<!-- REACTIVATE MODAL -->
<div id="reactivateModal" class="modal" style="display:none;">
    <div class="modal-overlay" onclick="closeReactivateModal()"></div>
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-undo text-green-500 mr-2"></i> Reactivate Employee</h3>
            <button onclick="closeReactivateModal()" class="modal-close">&times;</button>
        </div>
        <div class="modal-body">
            <p>Are you sure you want to reactivate <strong id="reactivateName"></strong>?</p>
        </div>
        <div class="modal-footer">
            <button onclick="closeReactivateModal()" class="btn btn-outline btn-sm">Cancel</button>
            <a href="#" id="reactivateLink" class="btn btn-success btn-sm">
                <i class="fas fa-undo"></i> Reactivate
            </a>
        </div>
    </div>
</div>

<!-- TOAST -->
<div id="toast" class="toast-custom" style="display:none;">
    <i class="fas fa-info-circle" style="font-size:1.1rem;"></i>
    <div>
        <p style="font-weight:600;font-size:0.85rem;margin:0;" id="toastTitle">Notification</p>
        <p style="font-size:0.75rem;opacity:0.9;margin:0;" id="toastMessage"></p>
    </div>
</div>

<script>
// ================================================================
// SIDEBAR TOGGLE WITH OVERLAY
// ================================================================
var sidebar = document.getElementById('sidebar');
var sidebarToggle = document.getElementById('sidebarToggle');
var sidebarOverlay = document.getElementById('sidebarOverlay');

function openSidebar() {
    if (!sidebar) return;
    sidebar.classList.add('open');
    if (sidebarOverlay) sidebarOverlay.classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeSidebar() {
    if (!sidebar) return;
    sidebar.classList.remove('open');
    if (sidebarOverlay) sidebarOverlay.classList.remove('active');
    document.body.style.overflow = '';
}

sidebarToggle?.addEventListener('click', function(e) {
    e.preventDefault();
    e.stopPropagation();
    if (sidebar.classList.contains('open')) {
        closeSidebar();
    } else {
        openSidebar();
    }
});

sidebarOverlay?.addEventListener('click', closeSidebar);

document.querySelectorAll('.sidebar-link').forEach(function(link) {
    link.addEventListener('click', function() {
        if (window.innerWidth <= 1024) closeSidebar();
    });
});

window.addEventListener('resize', function() {
    if (window.innerWidth > 1024) closeSidebar();
});

// ================================================================
// BRANCH SWITCHER
// ================================================================
function switchBranch(branchId) {
    var url = new URL(window.location.href);
    url.searchParams.set('branch', branchId);
    window.location.href = url.toString();
}

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
// AUTO SEARCH - TABLE
// ================================================================
(function() {
    var searchInput = document.getElementById('tableSearch');
    var noResultsRow = document.getElementById('noResults');
    var visibleCountEl = document.getElementById('visibleCount');
    var searchInfo = document.getElementById('searchInfo');
    var matchCount = document.getElementById('matchCount');
    
    if (!searchInput) return;
    
    var totalRows = document.querySelectorAll('.emp-row').length;
    
    function filterTable() {
        var query = searchInput.value.toLowerCase().trim();
        var rows = document.querySelectorAll('.emp-row');
        var visibleCount = 0;
        
        rows.forEach(function(row) {
            var searchData = row.getAttribute('data-search') || '';
            if (query === '' || searchData.includes(query)) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        });
        
        if (visibleCountEl) {
            visibleCountEl.textContent = query === '' ? totalRows : visibleCount;
        }
        
        if (searchInfo && matchCount) {
            if (query === '') {
                searchInfo.style.display = 'none';
            } else {
                searchInfo.style.display = 'inline-flex';
                matchCount.textContent = visibleCount;
            }
        }
        
        if (noResultsRow) {
            noResultsRow.style.display = (visibleCount === 0 && query !== '' && totalRows > 0) ? '' : 'none';
        }
    }
    
    searchInput.addEventListener('input', filterTable);
    searchInput.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            this.value = '';
            filterTable();
            this.blur();
        }
    });
})();

// ================================================================
// GLOBAL SEARCH
// ================================================================
var searchBtn = document.getElementById('searchBtn');
var searchInput = document.getElementById('searchInput');

function performSearch() {
    var query = searchInput.value.trim();
    if (query.length > 0) {
        var branch = '<?= $selected_branch_id ?>';
        window.location.href = 'search.php?q=' + encodeURIComponent(query) + '&branch=' + branch + '&type=employees';
    }
}

searchBtn?.addEventListener('click', performSearch);
searchInput?.addEventListener('keypress', function(e) {
    if (e.key === 'Enter') performSearch();
});

// ================================================================
// DELETE MODAL
// ================================================================
function confirmDelete(id, name) {
    document.getElementById('deleteName').textContent = name;
    document.getElementById('deleteLink').href = 'employees.php?delete=' + id + '&branch=<?= $selected_branch_id ?>';
    document.getElementById('deleteModal').style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function closeModal() {
    document.getElementById('deleteModal').style.display = 'none';
    document.body.style.overflow = 'auto';
}

// ================================================================
// REACTIVATE MODAL
// ================================================================
function confirmReactivate(id, name) {
    document.getElementById('reactivateName').textContent = name;
    document.getElementById('reactivateLink').href = 'employees.php?reactivate=' + id + '&branch=<?= $selected_branch_id ?>';
    document.getElementById('reactivateModal').style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function closeReactivateModal() {
    document.getElementById('reactivateModal').style.display = 'none';
    document.body.style.overflow = 'auto';
}

// ================================================================
// DATE & TIME
// ================================================================
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
// ESC KEY CLOSE
// ================================================================
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeModal();
        closeReactivateModal();
    }
});

console.log('%c👥 Braick - Employees Management', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
console.log('%c✅ Branch column - STANDARD size (130px)', 'font-size:13px; color:#34D399;');
console.log('%c✅ Branch name updates on change', 'font-size:13px; color:#34D399;');
console.log('%c✅ Search bar - smaller (220px)', 'font-size:13px; color:#34D399;');
console.log('%c✅ Mobile sidebar full-hide', 'font-size:13px; color:#34D399;');
</script>

</body>
</html>