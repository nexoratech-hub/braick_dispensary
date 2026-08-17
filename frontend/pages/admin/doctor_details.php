<?php
// ================================================================
// FILE: frontend/pages/admin/doctor_details.php
// DOCTOR DETAILS - VIEW ALL DOCTOR INFORMATION
// BRAICK DISPENSARY
// ================================================================

// ================================================================
// START SESSION
// ================================================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ================================================================
// LOGIN PROTECTION - CHECK IF USER IS LOGGED IN
// ================================================================
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: ../login.php');
    exit;
}

// ================================================================
// CHECK IF USER IS ADMIN
// ================================================================
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

// ================================================================
// GET ADMIN DATA FROM SESSION
// ================================================================
$user_id = $_SESSION['user_id'];
$user_full_name = $_SESSION['full_name'] ?? 'Admin';
$user_role = $_SESSION['role'] ?? 'admin';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$username = $_SESSION['username'] ?? '';
$profile_pic = $_SESSION['profile_pic'] ?? '';

// ================================================================
// INCLUDE DATABASE AND HELPERS
// ================================================================
require_once '../../../backend/config/database.php';
require_once '../../../backend/helpers/functions.php';

$db = Database::getInstance()->getConnection();

// ================================================================
// VARIABLES
// ================================================================
$doctor_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$selected_branch_id = $_GET['branch'] ?? 'all';

if ($doctor_id <= 0) {
    header('Location: doctors_list.php?branch=' . $selected_branch_id);
    exit;
}

// ================================================================
// GET DOCTOR DATA
// ================================================================
$stmt = $db->prepare("
    SELECT u.*, b.name as branch_name,
           (SELECT COUNT(*) FROM patients WHERE assigned_doctor_id = u.id) as total_patients,
           (SELECT COUNT(*) FROM visits WHERE doctor_id = u.id) as total_visits,
           (SELECT COUNT(*) FROM prescriptions WHERE doctor_id = u.id) as total_prescriptions,
           (SELECT COUNT(*) FROM lab_tests WHERE doctor_id = u.id) as total_lab_tests,
           (SELECT COUNT(*) FROM appointments WHERE doctor_id = u.id AND status != 'cancelled') as total_appointments
    FROM users u
    LEFT JOIN branches b ON u.branch_id = b.id
    WHERE u.id = ? AND u.role = 'doctor'
");
$stmt->execute([$doctor_id]);
$doctor = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$doctor) {
    header('Location: doctors_list.php?branch=' . $selected_branch_id);
    exit;
}

// ================================================================
// GET DOCTOR STATUS HISTORY
// ================================================================
$status_history = [];
try {
    $stmt = $db->prepare("
        SELECT * FROM doctor_status 
        WHERE doctor_id = ? 
        ORDER BY updated_at DESC 
        LIMIT 10
    ");
    $stmt->execute([$doctor_id]);
    $status_history = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $status_history = [];
}

// ================================================================
// GET ASSIGNED PATIENTS
// ================================================================
$stmt = $db->prepare("
    SELECT p.*, 
           (SELECT COUNT(*) FROM visits WHERE patient_id = p.id) as total_visits,
           (SELECT COUNT(*) FROM patient_bills WHERE patient_id = p.id AND status != 'cancelled') as total_bills
    FROM patients p
    WHERE p.assigned_doctor_id = ?
    ORDER BY p.created_at DESC
    LIMIT 10
");
$stmt->execute([$doctor_id]);
$assigned_patients = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// GET RECENT VISITS
// ================================================================
$stmt = $db->prepare("
    SELECT v.*, p.full_name as patient_name, p.patient_id as patient_number,
           CASE 
               WHEN v.status = 'pending' THEN 'warning'
               WHEN v.status = 'completed' THEN 'success'
               WHEN v.status = 'cancelled' THEN 'danger'
               ELSE 'info'
           END as status_color
    FROM visits v
    INNER JOIN patients p ON v.patient_id = p.id
    WHERE v.doctor_id = ?
    ORDER BY v.created_at DESC
    LIMIT 10
");
$stmt->execute([$doctor_id]);
$recent_visits = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// GET RECENT PRESCRIPTIONS
// ================================================================
$stmt = $db->prepare("
    SELECT p.*, pat.full_name as patient_name, pat.patient_id as patient_number,
           CASE 
               WHEN p.status = 'pending' THEN 'warning'
               WHEN p.status = 'confirmed' THEN 'info'
               WHEN p.status = 'dispensed' THEN 'success'
               WHEN p.status = 'cancelled' THEN 'danger'
               ELSE 'secondary'
           END as status_color
    FROM prescriptions p
    INNER JOIN patients pat ON p.patient_id = pat.id
    WHERE p.doctor_id = ?
    ORDER BY p.created_at DESC
    LIMIT 10
");
$stmt->execute([$doctor_id]);
$recent_prescriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// GET RECENT LAB TESTS
// ================================================================
$stmt = $db->prepare("
    SELECT lt.*, p.full_name as patient_name, p.patient_id as patient_number,
           CASE 
               WHEN lt.status = 'pending' THEN 'warning'
               WHEN lt.status = 'in_progress' THEN 'info'
               WHEN lt.status = 'completed' THEN 'success'
               WHEN lt.status = 'cancelled' THEN 'danger'
               ELSE 'secondary'
           END as status_color
    FROM lab_tests lt
    INNER JOIN visits v ON lt.visit_id = v.id
    INNER JOIN patients p ON v.patient_id = p.id
    WHERE lt.doctor_id = ?
    ORDER BY lt.created_at DESC
    LIMIT 10
");
$stmt->execute([$doctor_id]);
$recent_lab_tests = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// GET RECENT APPOINTMENTS
// ================================================================
$stmt = $db->prepare("
    SELECT a.*, p.full_name as patient_name, p.patient_id as patient_number,
           CASE 
               WHEN a.status = 'scheduled' THEN 'warning'
               WHEN a.status = 'confirmed' THEN 'info'
               WHEN a.status = 'completed' THEN 'success'
               WHEN a.status = 'cancelled' THEN 'danger'
               ELSE 'secondary'
           END as status_color
    FROM appointments a
    INNER JOIN patients p ON a.patient_id = p.id
    WHERE a.doctor_id = ?
    ORDER BY a.created_at DESC
    LIMIT 10
");
$stmt->execute([$doctor_id]);
$recent_appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// GET BRANCHES FOR FILTER
// ================================================================
$branches_list = [];
$stmt = $db->query("SELECT id, name FROM branches WHERE status = 'active' ORDER BY name");
$branches_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// PROFILE PICTURE URL
// ================================================================
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

<style>
    /* ================================================================
       ADDITIONAL STYLES
       ================================================================ */
    
    /* Profile Header */
    .profile-header {
        background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
        border-radius: 16px;
        padding: 24px 30px;
        color: white;
        position: relative;
        overflow: hidden;
    }
    
    .profile-header::before {
        content: '';
        position: absolute;
        top: -50%;
        right: -20%;
        width: 300px;
        height: 300px;
        background: rgba(255,255,255,0.05);
        border-radius: 50%;
    }
    
    .profile-header .profile-avatar {
        width: 80px;
        height: 80px;
        border-radius: 50%;
        background: rgba(255,255,255,0.2);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 2.5rem;
        border: 3px solid rgba(255,255,255,0.3);
        flex-shrink: 0;
    }
    
    .profile-header .profile-name {
        font-size: 1.5rem;
        font-weight: 700;
    }
    
    .profile-header .profile-badge {
        background: rgba(255,255,255,0.15);
        padding: 4px 14px;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: 600;
        backdrop-filter: blur(4px);
        border: 1px solid rgba(255,255,255,0.1);
    }
    
    /* Status Badges */
    .status-badge {
        padding: 4px 16px;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    
    .status-badge.online {
        background: #D1FAE5;
        color: #059669;
    }
    
    .status-badge.offline {
        background: #FEE2E2;
        color: #EF4444;
    }
    
    [data-theme="dark"] .status-badge.online {
        background: #1A3A2A;
        color: #34D399;
    }
    
    [data-theme="dark"] .status-badge.offline {
        background: #3A1A1A;
        color: #F87171;
    }
    
    .status-badge.warning { background: #FEF3C7; color: #D97706; }
    .status-badge.success { background: #D1FAE5; color: #059669; }
    .status-badge.danger { background: #FEE2E2; color: #EF4444; }
    .status-badge.info { background: #E8F0FE; color: #0B5ED7; }
    .status-badge.secondary { background: #E2E8F0; color: #64748B; }
    
    [data-theme="dark"] .status-badge.warning { background: #3A2A1A; color: #FBBF24; }
    [data-theme="dark"] .status-badge.success { background: #1A3A2A; color: #34D399; }
    [data-theme="dark"] .status-badge.danger { background: #3A1A1A; color: #F87171; }
    [data-theme="dark"] .status-badge.info { background: #1E3A5F; color: #6EA8FE; }
    [data-theme="dark"] .status-badge.secondary { background: #2D3748; color: #94A3B8; }
    
    /* Stat Cards */
    .stat-card-mini {
        background: var(--bg-card);
        border-radius: 12px;
        padding: 14px 18px;
        border: 1px solid var(--border-color);
        transition: all 0.3s ease;
        text-align: center;
    }
    
    .stat-card-mini:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.08);
        border-color: #0B5ED7;
    }
    
    .stat-card-mini .stat-number {
        font-size: 1.8rem;
        font-weight: 700;
        color: #0B5ED7;
    }
    
    .stat-card-mini .stat-number.green { color: #059669; }
    .stat-card-mini .stat-number.orange { color: #F59E0B; }
    .stat-card-mini .stat-number.purple { color: #7B2FBE; }
    .stat-card-mini .stat-number.red { color: #EF4444; }
    
    .stat-card-mini .stat-label {
        font-size: 0.7rem;
        color: var(--text-secondary);
        font-weight: 500;
        text-transform: uppercase;
        letter-spacing: 0.03em;
    }
    
    .stat-card-mini .stat-icon {
        font-size: 1.5rem;
        margin-bottom: 4px;
    }
    
    [data-theme="dark"] .stat-card-mini {
        background: #1E293B;
        border-color: #334155;
    }
    
    [data-theme="dark"] .stat-card-mini:hover {
        border-color: #0B5ED7;
    }
    
    [data-theme="dark"] .stat-card-mini .stat-number {
        color: #6EA8FE;
    }
    
    [data-theme="dark"] .stat-card-mini .stat-number.green {
        color: #34D399;
    }
    
    /* Table Header - Blue Theme */
    .table-blue thead th {
        background: linear-gradient(135deg, #0B5ED7, #0A4CA8) !important;
        color: #FFFFFF !important;
        font-weight: 700 !important;
        font-size: 0.65rem !important;
        text-transform: uppercase !important;
        letter-spacing: 0.05em !important;
        padding: 10px 14px !important;
        border-bottom: 3px solid #0A4CA8 !important;
        white-space: nowrap !important;
        position: sticky;
        top: 0;
        z-index: 5;
    }
    
    .table-blue thead th:first-child {
        border-radius: 8px 0 0 0 !important;
    }
    
    .table-blue thead th:last-child {
        border-radius: 0 8px 0 0 !important;
    }
    
    .table-blue tbody td {
        padding: 8px 14px !important;
        border-bottom: 1px solid #E2E8F0 !important;
        color: #1E293B !important;
        vertical-align: middle !important;
        font-size: 0.82rem;
    }
    
    /* FIXED: Text color in table rows */
    .table-blue tbody td,
    .table-blue tbody td .font-mono,
    .table-blue tbody td .font-semibold,
    .table-blue tbody td .badge,
    .table-blue tbody td .text-xs,
    .table-blue tbody td .text-gray-500,
    .table-blue tbody td .text-gray-400,
    .table-blue tbody td span,
    .table-blue tbody td a {
        color: #1E293B !important;
    }
    
    [data-theme="dark"] .table-blue tbody td,
    [data-theme="dark"] .table-blue tbody td .font-mono,
    [data-theme="dark"] .table-blue tbody td .font-semibold,
    [data-theme="dark"] .table-blue tbody td .badge,
    [data-theme="dark"] .table-blue tbody td .text-xs,
    [data-theme="dark"] .table-blue tbody td .text-gray-500,
    [data-theme="dark"] .table-blue tbody td .text-gray-400,
    [data-theme="dark"] .table-blue tbody td span,
    [data-theme="dark"] .table-blue tbody td a {
        color: #F1F5F9 !important;
    }
    
    .table-blue tbody tr:hover td {
        background: #E8F0FE !important;
    }
    
    [data-theme="dark"] .table-blue tbody tr:hover td {
        background: #1A3A5F !important;
    }
    
    /* Badge styles */
    .badge {
        padding: 3px 12px !important;
        border-radius: 20px !important;
        font-size: 0.6rem !important;
        font-weight: 600 !important;
        display: inline-flex !important;
        align-items: center !important;
        gap: 4px !important;
        border: none !important;
    }
    
    .badge-success {
        background: #D1FAE5 !important;
        color: #059669 !important;
    }
    
    .badge-warning {
        background: #FEF3C7 !important;
        color: #D97706 !important;
    }
    
    .badge-danger {
        background: #FEE2E2 !important;
        color: #EF4444 !important;
    }
    
    .badge-info {
        background: #E8F0FE !important;
        color: #0B5ED7 !important;
    }
    
    .badge-secondary {
        background: #E2E8F0 !important;
        color: #64748B !important;
    }
    
    .badge-blue {
        background: #E8F0FE !important;
        color: #0B5ED7 !important;
    }
    
    .badge-green {
        background: #D1FAE5 !important;
        color: #059669 !important;
    }
    
    [data-theme="dark"] .badge-success {
        background: #1A3A2A !important;
        color: #34D399 !important;
    }
    
    [data-theme="dark"] .badge-warning {
        background: #3A2A1A !important;
        color: #FBBF24 !important;
    }
    
    [data-theme="dark"] .badge-danger {
        background: #3A1A1A !important;
        color: #F87171 !important;
    }
    
    [data-theme="dark"] .badge-info {
        background: #1E3A5F !important;
        color: #6EA8FE !important;
    }
    
    [data-theme="dark"] .badge-secondary {
        background: #2D3748 !important;
        color: #94A3B8 !important;
    }
    
    [data-theme="dark"] .badge-blue {
        background: #1E3A5F !important;
        color: #6EA8FE !important;
    }
    
    [data-theme="dark"] .badge-green {
        background: #1A3A2A !important;
        color: #34D399 !important;
    }
    
    /* Card */
    .card {
        background: var(--bg-card);
        border-radius: 16px;
        padding: 18px 20px;
        border: 1px solid var(--border-color);
        transition: all 0.3s;
    }
    
    .card:hover {
        border-color: #0B5ED7;
        box-shadow: 0 4px 12px rgba(11, 94, 215, 0.05);
    }
    
    .card-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 10px;
        flex-wrap: wrap;
        gap: 8px;
    }
    
    .card-title {
        font-size: 0.9rem;
        font-weight: 600;
        color: var(--text-primary);
    }
    
    .title-blue { color: #0B5ED7; }
    .title-green { color: #059669; }
    
    /* Info Row */
    .info-row {
        display: flex;
        padding: 6px 0;
        border-bottom: 1px solid var(--border-color);
    }
    
    .info-row .info-label {
        width: 140px;
        font-weight: 600;
        color: var(--text-secondary);
        font-size: 0.82rem;
        flex-shrink: 0;
    }
    
    .info-row .info-value {
        flex: 1;
        color: var(--text-primary);
        font-size: 0.85rem;
    }
    
    /* ================================================================
       ACTION BUTTONS FOR PATIENTS
       ================================================================ */
    .action-buttons {
        display: flex;
        gap: 4px;
        flex-wrap: wrap;
    }
    
    .btn-action {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 4px 10px;
        border-radius: 6px;
        font-size: 0.6rem;
        font-weight: 600;
        transition: all 0.3s ease;
        text-decoration: none;
        border: none;
        cursor: pointer;
    }
    
    .btn-action i { font-size: 0.65rem; }
    
    .btn-view {
        background: #E8F0FE;
        color: #0B5ED7;
    }
    
    .btn-view:hover {
        background: #0B5ED7;
        color: white;
        transform: translateY(-1px);
        box-shadow: 0 2px 8px rgba(11, 94, 215, 0.3);
    }
    
    .btn-edit {
        background: #FEF3C7;
        color: #D97706;
    }
    
    .btn-edit:hover {
        background: #D97706;
        color: white;
        transform: translateY(-1px);
        box-shadow: 0 2px 8px rgba(217, 119, 6, 0.3);
    }
    
    .btn-delete {
        background: #FEE2E2;
        color: #EF4444;
    }
    
    .btn-delete:hover {
        background: #EF4444;
        color: white;
        transform: translateY(-1px);
        box-shadow: 0 2px 8px rgba(239, 68, 68, 0.3);
    }
    
    [data-theme="dark"] .btn-view {
        background: #1E3A5F;
        color: #6EA8FE;
    }
    [data-theme="dark"] .btn-view:hover {
        background: #0B5ED7;
        color: white;
    }
    
    [data-theme="dark"] .btn-edit {
        background: #3A2A1A;
        color: #FBBF24;
    }
    [data-theme="dark"] .btn-edit:hover {
        background: #D97706;
        color: white;
    }
    
    [data-theme="dark"] .btn-delete {
        background: #3A1A1A;
        color: #F87171;
    }
    [data-theme="dark"] .btn-delete:hover {
        background: #EF4444;
        color: white;
    }
    
    /* Responsive */
    @media (max-width: 640px) {
        .profile-header {
            padding: 16px 18px;
        }
        .profile-header .profile-avatar {
            width: 60px;
            height: 60px;
            font-size: 1.8rem;
        }
        .profile-header .profile-name {
            font-size: 1.2rem;
        }
        .info-row {
            flex-direction: column;
            gap: 2px;
        }
        .info-row .info-label {
            width: 100%;
            font-size: 0.75rem;
        }
        .stat-card-mini .stat-number {
            font-size: 1.4rem;
        }
        .table-blue tbody td {
            font-size: 0.7rem;
            padding: 6px 10px !important;
        }
        .btn {
            font-size: 0.7rem;
            padding: 4px 10px;
        }
        .action-buttons .btn-action {
            font-size: 0.55rem;
            padding: 3px 8px;
        }
    }
</style>

<!-- ================================================================ -->
<!-- TOP NAVIGATION -->
<!-- ================================================================ -->
<nav class="top-nav">
    <div class="flex items-center gap-4 flex-1">
        <button id="sidebarToggle" class="lg:hidden icon-btn">
            <i class="fas fa-bars text-lg"></i>
        </button>
        
        <div class="search-wrapper">
            <i class="fas fa-search text-gray-400 ml-3"></i>
            <form method="GET" action="doctors_list.php" class="flex-1 flex">
                <input type="hidden" name="branch" value="<?= htmlspecialchars($selected_branch_id) ?>">
                <input type="text" name="search" placeholder="Search doctors..." 
                       class="flex-1 px-3 py-2 bg-transparent border-none outline-none text-sm" 
                       style="color: var(--text-primary);">
                <button type="submit" class="search-btn">
                    <i class="fas fa-search mr-1"></i> Search
                </button>
            </form>
        </div>
    </div>
    
    <div class="flex items-center gap-3">
        <select id="branchSelector" class="branch-selector" onchange="switchBranch(this.value)">
            <option value="all" <?= $selected_branch_id === 'all' ? 'selected' : '' ?>>🌐 All Branches</option>
            <?php foreach ($branches_list as $branch): ?>
                <option value="<?= $branch['id'] ?>" <?= $selected_branch_id == $branch['id'] ? 'selected' : '' ?>>
                    🏥 <?= htmlspecialchars($branch['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        
        <span class="datetime" id="currentDateTime"></span>
        
        <button id="darkModeToggle" class="dark-toggle-btn" title="Toggle Dark Mode">
            <i id="darkIcon" class="fas fa-moon"></i>
            <span id="darkText">Dark</span>
        </button>
        
        <button class="icon-btn">
            <i class="fas fa-bell text-lg"></i>
            <span class="notif-dot"></span>
        </button>
        
        <a href="profile.php">
            <img src="<?= $profile_pic_url ?>" alt="Profile" class="avatar"
                 onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2240%22 height=%2240%22%3E%3Crect width=%2240%22 height=%2240%22 fill=%22%230B5ED7%22 rx=%2250%25%22/%3E%3Ctext x=%2220%22 y=%2226%22 text-anchor=%22middle%22 fill=%22white%22 font-size=%2218%22 font-weight=%22bold%22%3E<?= strtoupper(substr($user_full_name, 0, 1)) ?>%3C/text%3E%3C/svg%3E'">
        </a>
    </div>
</nav>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <!-- ================================================================ -->
    <!-- PROFILE HEADER -->
    <!-- ================================================================ -->
    <div class="profile-header mb-5">
        <div class="flex items-center gap-4 flex-wrap" style="position:relative;z-index:1;">
            <div class="profile-avatar">
                <i class="fas fa-user-md"></i>
            </div>
            <div class="flex-1">
                <div class="flex items-center gap-3 flex-wrap">
                    <h1 class="profile-name">Dr. <?= htmlspecialchars($doctor['full_name']) ?></h1>
                    <span class="profile-badge">
                        <i class="fas fa-user-md"></i> Doctor
                    </span>
                    <span class="profile-badge <?= $doctor['is_online'] == 1 ? 'online' : 'offline' ?>">
                        <i class="fas fa-circle"></i>
                        <?= $doctor['is_online'] == 1 ? 'Online' : 'Offline' ?>
                    </span>
                    <?php if ($doctor['specialty']): ?>
                        <span class="profile-badge" style="background:rgba(52,211,153,0.2);border-color:rgba(52,211,153,0.2);">
                            <i class="fas fa-stethoscope"></i> <?= htmlspecialchars($doctor['specialty']) ?>
                        </span>
                    <?php endif; ?>
                </div>
                <div class="flex items-center gap-3 flex-wrap mt-1" style="opacity:0.85;">
                    <span><i class="fas fa-envelope"></i> <?= htmlspecialchars($doctor['email']) ?></span>
                    <span><i class="fas fa-phone"></i> <?= htmlspecialchars($doctor['phone'] ?? 'N/A') ?></span>
                    <span><i class="fas fa-store-alt"></i> <?= htmlspecialchars($doctor['branch_name'] ?? 'N/A') ?></span>
                    <?php if ($doctor['last_online']): ?>
                        <span><i class="fas fa-clock"></i> Last online: <?= date('M d, Y h:i A', strtotime($doctor['last_online'])) ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="flex gap-2 flex-wrap">
                <a href="edit_doctor.php?id=<?= $doctor['id'] ?>&branch=<?= $selected_branch_id ?>" class="btn btn-primary btn-sm" style="background:rgba(255,255,255,0.2);color:white;border:1px solid rgba(255,255,255,0.2);">
                    <i class="fas fa-edit"></i> Edit
                </a>
                <a href="doctors_list.php?branch=<?= $selected_branch_id ?>" class="btn btn-outline btn-sm" style="background:rgba(255,255,255,0.1);color:white;border:1px solid rgba(255,255,255,0.15);">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
            </div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- STATISTICS CARDS -->
    <!-- ================================================================ -->
    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-5 gap-3 mb-5">
        
        <div class="stat-card-mini">
            <div class="stat-icon">👤</div>
            <p class="stat-number"><?= $doctor['total_patients'] ?? 0 ?></p>
            <p class="stat-label">Total Patients</p>
        </div>
        
        <div class="stat-card-mini">
            <div class="stat-icon">📋</div>
            <p class="stat-number green"><?= $doctor['total_visits'] ?? 0 ?></p>
            <p class="stat-label">Total Visits</p>
        </div>
        
        <div class="stat-card-mini">
            <div class="stat-icon">💊</div>
            <p class="stat-number purple"><?= $doctor['total_prescriptions'] ?? 0 ?></p>
            <p class="stat-label">Prescriptions</p>
        </div>
        
        <div class="stat-card-mini">
            <div class="stat-icon">🔬</div>
            <p class="stat-number orange"><?= $doctor['total_lab_tests'] ?? 0 ?></p>
            <p class="stat-label">Lab Tests</p>
        </div>
        
        <div class="stat-card-mini">
            <div class="stat-icon">📅</div>
            <p class="stat-number"><?= $doctor['total_appointments'] ?? 0 ?></p>
            <p class="stat-label">Appointments</p>
        </div>
        
    </div>

    <!-- ================================================================ -->
    <!-- DOCTOR INFORMATION -->
    <!-- ================================================================ -->
    <div class="card mb-5">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-info-circle title-blue mr-2"></i> Doctor Information
            </h3>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
            <div class="info-row">
                <span class="info-label"><i class="fas fa-user"></i> Full Name</span>
                <span class="info-value">Dr. <?= htmlspecialchars($doctor['full_name']) ?></span>
            </div>
            <div class="info-row">
                <span class="info-label"><i class="fas fa-user-md"></i> Role</span>
                <span class="info-value">Doctor</span>
            </div>
            <div class="info-row">
                <span class="info-label"><i class="fas fa-envelope"></i> Email</span>
                <span class="info-value"><?= htmlspecialchars($doctor['email']) ?></span>
            </div>
            <div class="info-row">
                <span class="info-label"><i class="fas fa-phone"></i> Phone</span>
                <span class="info-value"><?= htmlspecialchars($doctor['phone'] ?? 'N/A') ?></span>
            </div>
            <div class="info-row">
                <span class="info-label"><i class="fas fa-stethoscope"></i> Specialty</span>
                <span class="info-value"><?= htmlspecialchars($doctor['specialty'] ?? 'General') ?></span>
            </div>
            <div class="info-row">
                <span class="info-label"><i class="fas fa-store-alt"></i> Branch</span>
                <span class="info-value"><?= htmlspecialchars($doctor['branch_name'] ?? 'N/A') ?></span>
            </div>
            <div class="info-row">
                <span class="info-label"><i class="fas fa-circle"></i> Status</span>
                <span class="info-value">
                    <span class="status-badge <?= $doctor['is_online'] == 1 ? 'online' : 'offline' ?>">
                        <i class="fas fa-circle"></i>
                        <?= $doctor['is_online'] == 1 ? 'Online' : 'Offline' ?>
                    </span>
                </span>
            </div>
            <div class="info-row">
                <span class="info-label"><i class="fas fa-calendar-alt"></i> Registered</span>
                <span class="info-value"><?= date('M d, Y h:i A', strtotime($doctor['created_at'])) ?></span>
            </div>
            <?php if ($doctor['last_online']): ?>
                <div class="info-row">
                    <span class="info-label"><i class="fas fa-clock"></i> Last Online</span>
                    <span class="info-value"><?= date('M d, Y h:i A', strtotime($doctor['last_online'])) ?></span>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- ASSIGNED PATIENTS - WITH VIEW, EDIT, DELETE BUTTONS -->
    <!-- ================================================================ -->
    <div class="card mb-5">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-users title-blue mr-2"></i> Assigned Patients
                <span class="badge-count">(<?= count($assigned_patients) ?> patients)</span>
            </h3>
            <a href="patients.php?doctor=<?= $doctor['id'] ?>&branch=<?= $selected_branch_id ?>" class="btn btn-outline btn-sm">
                View All <i class="fas fa-arrow-right"></i>
            </a>
        </div>
        <div class="overflow-x-auto">
            <table class="data-table table-blue w-full">
                <thead>
                    <tr>
                        <th>Patient ID</th>
                        <th>Name</th>
                        <th>Phone</th>
                        <th>Visits</th>
                        <th>Bills</th>
                        <th>Registered</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($assigned_patients) > 0): ?>
                        <?php foreach ($assigned_patients as $patient): ?>
                            <tr>
                                <td class="font-mono text-xs"><?= htmlspecialchars($patient['patient_id']) ?></td>
                                <td class="font-semibold"><?= htmlspecialchars($patient['full_name']) ?></td>
                                <td><?= htmlspecialchars($patient['phone'] ?? 'N/A') ?></td>
                                <td class="text-center">
                                    <span class="badge badge-info"><?= $patient['total_visits'] ?? 0 ?></span>
                                </td>
                                <td class="text-center">
                                    <span class="badge badge-green"><?= $patient['total_bills'] ?? 0 ?></span>
                                </td>
                                <td class="text-xs"><?= date('M d, Y', strtotime($patient['created_at'])) ?></td>
                                <td>
                                    <div class="action-buttons">
                                        <!-- View Button -->
                                        <a href="patient_details.php?id=<?= $patient['id'] ?>&branch=<?= $selected_branch_id ?>" 
                                           class="btn-action btn-view" title="View Patient">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                        
                                        <!-- Edit Button -->
                                        <a href="edit_patient.php?id=<?= $patient['id'] ?>&branch=<?= $selected_branch_id ?>" 
                                           class="btn-action btn-edit" title="Edit Patient">
                                            <i class="fas fa-edit"></i> Edit
                                        </a>
                                        
                                        <!-- Delete Button -->
                                        <a href="patients.php?delete=<?= $patient['id'] ?>&branch=<?= $selected_branch_id ?>" 
                                           class="btn-action btn-delete" 
                                           onclick="return confirm('Are you sure you want to delete this patient?\n\nPatient: <?= htmlspecialchars($patient['full_name']) ?>\nID: <?= htmlspecialchars($patient['patient_id']) ?>\n\nThis will delete ALL related data including visits, bills, prescriptions, lab tests, appointments, payments and more.')" 
                                           title="Delete Patient">
                                            <i class="fas fa-trash"></i> Delete
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="7" class="text-center py-4 text-gray-400">No patients assigned to this doctor</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- RECENT VISITS - FIXED TEXT COLOR -->
    <!-- ================================================================ -->
    <div class="card mb-5">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-notes-medical title-blue mr-2"></i> Recent Visits
                <span class="badge-count">(<?= $doctor['total_visits'] ?? 0 ?> total)</span>
            </h3>
            <a href="visits.php?doctor=<?= $doctor['id'] ?>&branch=<?= $selected_branch_id ?>" class="btn btn-outline btn-sm">
                View All <i class="fas fa-arrow-right"></i>
            </a>
        </div>
        <div class="overflow-x-auto">
            <table class="data-table table-blue w-full">
                <thead>
                    <tr>
                        <th>Visit #</th>
                        <th>Patient</th>
                        <th>Date</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($recent_visits) > 0): ?>
                        <?php foreach ($recent_visits as $visit): ?>
                            <tr>
                                <td class="font-mono text-xs"><?= htmlspecialchars($visit['visit_number']) ?></td>
                                <td class="font-semibold"><?= htmlspecialchars($visit['patient_name']) ?></td>
                                <td class="text-xs"><?= date('M d, Y', strtotime($visit['visit_date'])) ?></td>
                                <td><span class="badge badge-info"><?= ucfirst($visit['visit_type'] ?? 'N/A') ?></span></td>
                                <td>
                                    <span class="badge badge-<?= $visit['status_color'] ?? 'secondary' ?>">
                                        <?= ucfirst($visit['status'] ?? 'N/A') ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="visit_details.php?id=<?= $visit['id'] ?>&branch=<?= $selected_branch_id ?>" class="btn btn-primary btn-sm">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="6" class="text-center py-4 text-gray-400">No visits found</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- RECENT PRESCRIPTIONS -->
    <!-- ================================================================ -->
    <div class="card mb-5">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-prescription title-blue mr-2"></i> Recent Prescriptions
                <span class="badge-count">(<?= $doctor['total_prescriptions'] ?? 0 ?> total)</span>
            </h3>
            <a href="prescriptions.php?doctor=<?= $doctor['id'] ?>&branch=<?= $selected_branch_id ?>" class="btn btn-outline btn-sm">
                View All <i class="fas fa-arrow-right"></i>
            </a>
        </div>
        <div class="overflow-x-auto">
            <table class="data-table table-blue w-full">
                <thead>
                    <tr>
                        <th>Prescription #</th>
                        <th>Patient</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($recent_prescriptions) > 0): ?>
                        <?php foreach ($recent_prescriptions as $prescription): ?>
                            <tr>
                                <td class="font-mono text-xs"><?= htmlspecialchars($prescription['prescription_number']) ?></td>
                                <td><?= htmlspecialchars($prescription['patient_name']) ?></td>
                                <td>
                                    <span class="badge badge-<?= $prescription['status_color'] ?? 'secondary' ?>">
                                        <?= ucfirst($prescription['status'] ?? 'N/A') ?>
                                    </span>
                                </td>
                                <td class="text-xs"><?= date('M d, Y', strtotime($prescription['created_at'])) ?></td>
                                <td>
                                    <a href="prescription_details.php?id=<?= $prescription['id'] ?>&branch=<?= $selected_branch_id ?>" class="btn btn-primary btn-sm">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="5" class="text-center py-4 text-gray-400">No prescriptions found</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- RECENT LAB TESTS -->
    <!-- ================================================================ -->
    <div class="card mb-5">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-flask title-blue mr-2"></i> Recent Lab Tests
                <span class="badge-count">(<?= $doctor['total_lab_tests'] ?? 0 ?> total)</span>
            </h3>
            <a href="lab_tests.php?doctor=<?= $doctor['id'] ?>&branch=<?= $selected_branch_id ?>" class="btn btn-outline btn-sm">
                View All <i class="fas fa-arrow-right"></i>
            </a>
        </div>
        <div class="overflow-x-auto">
            <table class="data-table table-blue w-full">
                <thead>
                    <tr>
                        <th>Test Name</th>
                        <th>Patient</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($recent_lab_tests) > 0): ?>
                        <?php foreach ($recent_lab_tests as $test): ?>
                            <tr>
                                <td><?= htmlspecialchars($test['test_name'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($test['patient_name']) ?></td>
                                <td>
                                    <span class="badge badge-<?= $test['status_color'] ?? 'secondary' ?>">
                                        <?= ucfirst(str_replace('_', ' ', $test['status'] ?? 'N/A')) ?>
                                    </span>
                                </td>
                                <td class="text-xs"><?= date('M d, Y', strtotime($test['created_at'])) ?></td>
                                <td>
                                    <a href="lab_test_details.php?id=<?= $test['id'] ?>&branch=<?= $selected_branch_id ?>" class="btn btn-primary btn-sm">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="5" class="text-center py-4 text-gray-400">No lab tests found</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- RECENT APPOINTMENTS -->
    <!-- ================================================================ -->
    <div class="card mb-5">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-calendar-check title-blue mr-2"></i> Recent Appointments
                <span class="badge-count">(<?= $doctor['total_appointments'] ?? 0 ?> total)</span>
            </h3>
            <a href="appointments.php?doctor=<?= $doctor['id'] ?>&branch=<?= $selected_branch_id ?>" class="btn btn-outline btn-sm">
                View All <i class="fas fa-arrow-right"></i>
            </a>
        </div>
        <div class="overflow-x-auto">
            <table class="data-table table-blue w-full">
                <thead>
                    <tr>
                        <th>Patient</th>
                        <th>Appointment Date</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($recent_appointments) > 0): ?>
                        <?php foreach ($recent_appointments as $appointment): ?>
                            <tr>
                                <td><?= htmlspecialchars($appointment['patient_name']) ?></td>
                                <td class="text-xs"><?= date('M d, Y h:i A', strtotime($appointment['appointment_date'])) ?></td>
                                <td><span class="badge badge-info"><?= ucfirst($appointment['visit_type'] ?? 'N/A') ?></span></td>
                                <td>
                                    <span class="badge badge-<?= $appointment['status_color'] ?? 'secondary' ?>">
                                        <?= ucfirst($appointment['status'] ?? 'N/A') ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="appointment_details.php?id=<?= $appointment['id'] ?>&branch=<?= $selected_branch_id ?>" class="btn btn-primary btn-sm">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="5" class="text-center py-4 text-gray-400">No appointments found</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Doctor Details
            <span class="text-gray-300 mx-2">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<!-- ================================================================ -->
<!-- TOAST -->
<!-- ================================================================ -->
<div id="toast" class="toast-custom" style="display:none;">
    <i class="fas fa-info-circle" style="font-size:1.1rem;"></i>
    <div>
        <p style="font-weight:600;font-size:0.85rem;margin:0;" id="toastTitle">Notification</p>
        <p style="font-size:0.75rem;opacity:0.9;margin:0;" id="toastMessage"></p>
    </div>
</div>

<!-- ================================================================ -->
<!-- JAVASCRIPT -->
<!-- ================================================================ -->
<script>
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
    // DOM ELEMENTS
    // ================================================================
    var sidebar = document.getElementById('sidebar');
    var sidebarToggle = document.getElementById('sidebarToggle');

    // ================================================================
    // SIDEBAR TOGGLE
    // ================================================================
    sidebarToggle?.addEventListener('click', function() {
        sidebar.classList.toggle('open');
    });
    
    document.addEventListener('click', function(e) {
        if (window.innerWidth <= 1024) {
            if (!sidebar.contains(e.target) && e.target !== sidebarToggle) {
                sidebar.classList.remove('open');
            }
        }
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
            setTimeout(function() { toast.style.display = 'none'; }, 400);
        }, 3500);
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
        var el = document.getElementById('currentDateTime');
        if (el) {
            el.textContent = dateStr + ' • ' + timeStr;
        }
    }
    updateDateTime();
    setInterval(updateDateTime, 1000);

    console.log('%c🏥 Braick Dispensary - Doctor Details', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c👤 Admin: <?= htmlspecialchars($user_full_name) ?>', 'font-size:13px; color:#059669;');
    console.log('%c🔒 Login protection: ACTIVE', 'font-size:13px; color:#0B5ED7;');
    console.log('%c👨‍⚕️ Doctor: Dr. <?= htmlspecialchars($doctor['full_name']) ?>', 'font-size:13px; color:#059669;');
    console.log('%c📊 Patients: <?= $doctor['total_patients'] ?? 0 ?> | Visits: <?= $doctor['total_visits'] ?? 0 ?>', 'font-size:13px; color:#64748B;');
    console.log('%c💊 Prescriptions: <?= $doctor['total_prescriptions'] ?? 0 ?> | 🔬 Lab Tests: <?= $doctor['total_lab_tests'] ?? 0 ?>', 'font-size:13px; color:#0B5ED7;');
    console.log('%c🔘 Patient Actions: View | Edit | Delete', 'font-size:13px; color:#EF4444;');
</script>

</body>
</html>