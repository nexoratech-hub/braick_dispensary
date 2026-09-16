<?php
// ================================================================
// FILE: frontend/pages/admin/departments.php
// SUPER ADMIN - ROLE / DEPARTMENT MANAGEMENT
// ✅ Uses SHARED header & sidebar
// ✅ Full dark mode support
// ✅ Audit role included
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

// ================================================================
// GET STATISTICS
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
// GET BRANCHES
// ================================================================
$branches_list = [];
$stmt = $db->query("SELECT id, name FROM branches WHERE status = 'active' ORDER BY name");
$branches_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// ✅ AVAILABLE ROLES / DEPARTMENTS - WITH AUDIT
// ================================================================
$available_roles = [
    'doctor' => [
        'name' => 'Medical Doctor',
        'description' => 'Provides medical consultations, diagnoses, and treatments to patients. Prescribes medications and orders lab tests.',
        'icon' => 'fa-user-md',
        'color' => '#059669'
    ],
    'pharmacy' => [
        'name' => 'Pharmacy Staff',
        'description' => 'Dispenses medications to patients based on prescriptions. Manages medication inventory and handles OTC sales.',
        'icon' => 'fa-prescription-bottle',
        'color' => '#7C3AED'
    ],
    'reception' => [
        'name' => 'Receptionist',
        'description' => 'Handles patient registration, appointment scheduling, and front desk operations. First point of contact for patients.',
        'icon' => 'fa-headset',
        'color' => '#0B5ED7'
    ],
    'laboratory' => [
        'name' => 'Lab Technician',
        'description' => 'Conducts laboratory tests and analyzes samples. Prepares test results and maintains lab equipment.',
        'icon' => 'fa-flask',
        'color' => '#0D9488'
    ],
    'cashier' => [
        'name' => 'Cashier',
        'description' => 'Handles patient billing, payment collections, and financial transactions. Manages receipts and payment records.',
        'icon' => 'fa-cash-register',
        'color' => '#D97706'
    ],
    'audit' => [
        'name' => 'Audit Officer',
        'description' => 'Monitors system activities, reviews logs, ensures compliance with policies, and reports irregularities.',
        'icon' => 'fa-clipboard-check',
        'color' => '#DC2626'
    ],
    'admin' => [
        'name' => 'Administrator',
        'description' => 'Manages system settings, user accounts, and overall system operations. Has full access to all features.',
        'icon' => 'fa-user-tie',
        'color' => '#B91C1C'
    ]
];

// ================================================================
// ✅ GET STAFF COUNT PER ROLE - With error handling for audit role
// ================================================================
$role_counts = [];
$total_users_per_role = [];

foreach (array_keys($available_roles) as $role_key) {
    try {
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM users WHERE role = ? AND status = 'active'");
        $stmt->execute([$role_key]);
        $role_counts[$role_key] = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    } catch (Exception $e) {
        $role_counts[$role_key] = 0;
    }
    
    try {
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM users WHERE role = ?");
        $stmt->execute([$role_key]);
        $total_users_per_role[$role_key] = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    } catch (Exception $e) {
        $total_users_per_role[$role_key] = 0;
    }
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
// URLS
// ================================================================
$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

$logo_url = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

// ================================================================
// ✅ INCLUDE SHARED HEADER & SIDEBAR
// ================================================================
include_once __DIR__ . '/../../components/admin_header.php';
include_once __DIR__ . '/../../components/admin_sidebar.php';
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
        --page-hover: #E8F0FE;
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

    /* ✅ BODY & MAIN CONTENT */
    body {
        background: var(--page-bg-body, #F0F4F8);
    }

    html[data-theme="dark"] body {
        background: #0F172A !important;
    }

    .main-content {
        background: var(--page-bg-body, #F0F4F8);
    }

    html[data-theme="dark"] .main-content {
        background: #0F172A !important;
        color: #F1F5F9;
    }

    /* ================================================================
       ✅ BLUE PAGE HEADER
       ================================================================ */
    .page-header-custom {
        background: linear-gradient(135deg, #0B5ED7 0%, #0A4CA8 50%, #083D8A 100%);
        border-radius: 18px;
        padding: 28px 36px;
        margin-bottom: 28px;
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
        transition: all 0.3s ease;
    }

    .page-header-custom .header-badge:hover {
        background: rgba(255,255,255,0.25);
        transform: translateY(-1px);
    }

    .page-header-custom .btn-outline-light {
        background: rgba(255,255,255,0.15);
        color: white;
        border: 1.5px solid rgba(255,255,255,0.3);
        padding: 10px 18px;
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
        white-space: nowrap;
    }

    .page-header-custom .btn-outline-light:hover {
        background: rgba(255,255,255,0.25);
        transform: translateY(-2px);
        box-shadow: 0 4px 16px rgba(0,0,0,0.15);
        color: white;
    }

    /* ================================================================
       CARDS
       ================================================================ */
    .card-custom {
        background: var(--page-bg-card, #FFFFFF);
        border-radius: 18px;
        padding: 24px 28px;
        border: 2px solid var(--page-border, #E2E8F0);
        transition: all 0.3s ease;
        box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        margin-bottom: 24px;
    }

    html[data-theme="dark"] .card-custom {
        background: #1E293B;
        border-color: #334155;
        box-shadow: 0 2px 12px rgba(0,0,0,0.3);
    }

    .card-custom:hover {
        border-color: #0B5ED7;
        box-shadow: 0 8px 30px rgba(11, 94, 215, 0.08);
    }

    html[data-theme="dark"] .card-custom:hover {
        box-shadow: 0 8px 30px rgba(11, 94, 215, 0.15);
    }

    .card-header-custom {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 16px;
        flex-wrap: wrap;
        gap: 8px;
    }

    .card-title-custom {
        font-size: 1rem;
        font-weight: 700;
        color: var(--page-text-primary, #1E293B);
        display: flex;
        align-items: center;
        gap: 8px;
        margin: 0;
    }

    html[data-theme="dark"] .card-title-custom {
        color: #F1F5F9;
    }

    .card-title-custom i { color: #0B5ED7; }
    html[data-theme="dark"] .card-title-custom i { color: #6EA8FE; }

    /* ================================================================
       TABLE
       ================================================================ */
    .table-wrap-custom {
        overflow-x: auto;
        border-radius: 12px;
    }

    .data-table-custom {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.85rem;
    }

    .data-table-custom thead th {
        text-align: left;
        padding: 12px 16px;
        font-weight: 700;
        font-size: 0.7rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: white;
        background: #0B5ED7;
        border-bottom: 3px solid #0A4FB0;
        white-space: nowrap;
    }

    .data-table-custom thead th:first-child { border-radius: 10px 0 0 0; }
    .data-table-custom thead th:last-child { border-radius: 0 10px 0 0; }

    .data-table-custom tbody tr:nth-child(even) {
        background: var(--page-hover, #E8F0FE);
    }

    .data-table-custom tbody tr:nth-child(odd) {
        background: var(--page-bg-card, #FFFFFF);
    }

    html[data-theme="dark"] .data-table-custom tbody tr:nth-child(even) {
        background: #1E3A5F;
    }

    html[data-theme="dark"] .data-table-custom tbody tr:nth-child(odd) {
        background: #1E293B;
    }

    .data-table-custom tbody tr:hover {
        background: var(--page-hover, #E8F0FE);
    }

    html[data-theme="dark"] .data-table-custom tbody tr:hover {
        background: #1E40AF;
    }

    .data-table-custom td {
        padding: 12px 16px;
        border-bottom: 1px solid var(--page-border, #E2E8F0);
        color: var(--page-text-primary, #1E293B);
        vertical-align: middle;
    }

    html[data-theme="dark"] .data-table-custom td {
        color: #F1F5F9;
        border-bottom-color: #334155;
    }

    /* ================================================================
       BADGES
       ================================================================ */
    .badge-custom {
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 4px;
        color: white;
        border: none;
    }

    .badge-success { background: #059669; color: white; }
    .badge-danger { background: #DC2626; color: white; }
    .badge-info { background: #0B5ED7; color: white; }
    .badge-warning { background: #D97706; color: white; }

    /* ================================================================
       BUTTONS
       ================================================================ */
    .btn-custom {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 8px 16px;
        border-radius: 8px;
        font-weight: 600;
        font-size: 0.75rem;
        transition: all 0.3s ease;
        cursor: pointer;
        border: none;
        text-decoration: none;
        white-space: nowrap;
    }

    .btn-blue-custom {
        background: #0B5ED7;
        color: white;
    }

    .btn-blue-custom:hover {
        background: #0A4CA8;
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3);
        color: white;
    }

    .btn-green-custom {
        background: #059669;
        color: white;
    }

    .btn-green-custom:hover {
        background: #047857;
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(5, 150, 105, 0.3);
        color: white;
    }

    .btn-sm-custom {
        padding: 5px 12px;
        font-size: 0.7rem;
        border-radius: 6px;
    }

    /* ================================================================
       ROLE CARDS
       ================================================================ */
    .role-card-custom {
        background: var(--page-bg-card, #FFFFFF);
        border-radius: 14px;
        padding: 20px 22px;
        border: 2px solid var(--page-border, #E2E8F0);
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        gap: 16px;
    }

    html[data-theme="dark"] .role-card-custom {
        background: #1E293B;
        border-color: #334155;
    }

    .role-card-custom:hover {
        border-color: #0B5ED7;
        transform: translateY(-3px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.08);
    }

    html[data-theme="dark"] .role-card-custom:hover {
        box-shadow: 0 8px 25px rgba(0,0,0,0.4);
    }

    .role-card-custom .role-icon {
        width: 52px;
        height: 52px;
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.4rem;
        color: white;
        flex-shrink: 0;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    }

    .role-card-custom .role-info { flex: 1; min-width: 0; }

    .role-card-custom .role-info .role-name {
        font-size: 1rem;
        font-weight: 700;
        color: var(--page-text-primary, #1E293B);
    }

    html[data-theme="dark"] .role-card-custom .role-info .role-name {
        color: #F1F5F9;
    }

    .role-card-custom .role-info .role-description {
        font-size: 0.78rem;
        color: var(--page-text-secondary, #64748B);
        margin-top: 2px;
        line-height: 1.4;
    }

    .role-card-custom .role-stats {
        text-align: right;
        flex-shrink: 0;
    }

    .role-card-custom .role-stats .stat-number {
        font-size: 1.4rem;
        font-weight: 700;
    }

    .role-card-custom .role-stats .stat-label {
        font-size: 0.65rem;
        color: var(--page-text-secondary, #64748B);
    }

    /* ================================================================
       STAT CARDS
       ================================================================ */
    .stat-card-custom {
        background: var(--page-bg-card, #FFFFFF);
        border-radius: 14px;
        padding: 18px 22px;
        border: 2px solid var(--page-border, #E2E8F0);
        transition: all 0.3s ease;
        text-align: center;
    }

    html[data-theme="dark"] .stat-card-custom {
        background: #1E293B;
        border-color: #334155;
    }

    .stat-card-custom:hover {
        border-color: #0B5ED7;
        transform: translateY(-3px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.06);
    }

    .stat-card-custom .stat-number {
        font-size: 1.9rem;
        font-weight: 800;
        color: #0B5ED7;
        line-height: 1;
    }

    .stat-card-custom .stat-number.green { color: #059669; }
    .stat-card-custom .stat-number.red { color: #DC2626; }
    .stat-card-custom .stat-number.purple { color: #7C3AED; }
    .stat-card-custom .stat-number.orange { color: #D97706; }

    .stat-card-custom .stat-label {
        font-size: 0.75rem;
        color: var(--page-text-secondary, #64748B);
        font-weight: 600;
        margin-top: 6px;
    }

    /* ================================================================
       GRID UTILITIES
       ================================================================ */
    .grid-2-cols {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 16px;
    }

    .grid-4-cols {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 16px;
    }

    .flex-gap-2 { display: flex; gap: 8px; flex-wrap: wrap; }
    .justify-center { justify-content: center; }

    /* ================================================================
       RESPONSIVE
       ================================================================ */
    @media (max-width: 1024px) {
        .grid-4-cols { grid-template-columns: repeat(2, 1fr); }
    }

    @media (max-width: 768px) {
        .page-header-custom { padding: 20px; }
        .page-header-custom .page-title { font-size: 1.3rem; }
        .page-header-custom .page-title i { width: 38px; height: 38px; font-size: 1.1rem; }
        .card-custom { padding: 16px; }
        .data-table-custom { font-size: 0.75rem; }
        .data-table-custom th, .data-table-custom td { padding: 8px 10px; }
        .stat-card-custom .stat-number { font-size: 1.5rem; }
        .grid-2-cols, .grid-4-cols { grid-template-columns: 1fr; }
        .role-card-custom { flex-direction: column; text-align: center; }
        .role-card-custom .role-stats { text-align: center; }
    }

    /* ================================================================
       PRINT
       ================================================================ */
    @media print {
        .page-header-custom { background: #0B5ED7 !important; -webkit-print-color-adjust: exact; }
        .btn-custom, .btn-outline-light { display: none !important; }
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
                <i class="fas fa-users-cog"></i>
                Role & Department Management
                <span class="role-badge-display">ADMIN</span>
            </h1>
            <p class="page-subtitle">
                <span>Manage all roles and departments in the organization</span>
                <span class="header-badge">
                    <i class="fas fa-list"></i> <?= count($available_roles) ?> Roles
                </span>
                <span class="header-badge">
                    <i class="fas fa-users"></i> <?= $total_employees ?> Staff
                </span>
                <span class="header-badge">
                    <i class="fas fa-user-md"></i> <?= $total_doctors ?> Doctors
                </span>
                <span class="header-badge">
                    <i class="fas fa-store"></i> <?= $total_branches ?> Branches
                </span>
            </p>
        </div>
        <div class="flex-gap-2" style="position:relative;z-index:1;">
            <a href="add_employee.php?branch=<?= $selected_branch_id ?>" class="btn-outline-light">
                <i class="fas fa-user-plus"></i> Add Staff
            </a>
            <a href="employees.php?branch=<?= $selected_branch_id ?>" class="btn-outline-light">
                <i class="fas fa-users"></i> All Staff
            </a>
        </div>
    </div>

    <!-- ROLES CARDS -->
    <div class="card-custom">
        <div class="card-header-custom">
            <h3 class="card-title-custom">
                <i class="fas fa-user-tag"></i> Available Roles / Departments
                <span style="font-size:0.75rem;font-weight:400;color:var(--page-text-secondary);">(<?= count($available_roles) ?> roles)</span>
            </h3>
        </div>
        
        <div class="grid-2-cols">
            <?php foreach ($available_roles as $role_key => $role_data): 
                $active_count = $role_counts[$role_key] ?? 0;
                $total_count = $total_users_per_role[$role_key] ?? 0;
                $icon = $role_data['icon'] ?? 'fa-user';
                $color = $role_data['color'] ?? '#0B5ED7';
            ?>
                <div class="role-card-custom">
                    <div class="role-icon" style="background:<?= $color ?>;">
                        <i class="fas <?= $icon ?>"></i>
                    </div>
                    <div class="role-info">
                        <div class="role-name"><?= htmlspecialchars($role_data['name']) ?></div>
                        <div class="role-description"><?= htmlspecialchars($role_data['description']) ?></div>
                        <div style="margin-top:8px;display:flex;gap:6px;flex-wrap:wrap;">
                            <span class="badge-custom badge-success">
                                <i class="fas fa-user"></i> <?= $active_count ?> active
                            </span>
                            <?php if ($total_count > $active_count): ?>
                                <span class="badge-custom badge-danger">
                                    <i class="fas fa-user-slash"></i> <?= $total_count - $active_count ?> inactive
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="role-stats">
                        <div class="stat-number" style="color:<?= $color ?>;"><?= $total_count ?></div>
                        <div class="stat-label">Total Staff</div>
                        <a href="employees.php?role=<?= $role_key ?>&branch=<?= $selected_branch_id ?>" 
                           class="btn-custom btn-blue-custom btn-sm-custom" style="margin-top:6px;">
                            <i class="fas fa-eye"></i> View
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- QUICK STATS -->
    <div class="grid-4-cols" style="margin-bottom:24px;">
        <div class="stat-card-custom">
            <p class="stat-number"><?= count($available_roles) ?></p>
            <p class="stat-label">Total Roles</p>
        </div>
        <div class="stat-card-custom">
            <p class="stat-number green"><?= $total_employees ?></p>
            <p class="stat-label">Active Staff</p>
        </div>
        <div class="stat-card-custom">
            <p class="stat-number purple"><?= $total_doctors ?></p>
            <p class="stat-label">Doctors</p>
        </div>
        <div class="stat-card-custom">
            <p class="stat-number orange"><?= $total_branches ?></p>
            <p class="stat-label">Branches</p>
        </div>
    </div>

    <!-- ROLE STAFF TABLE -->
    <div class="card-custom">
        <div class="card-header-custom">
            <h3 class="card-title-custom">
                <i class="fas fa-users"></i> Staff by Role
                <span style="font-size:0.75rem;font-weight:400;color:var(--page-text-secondary);">(<?= $total_employees ?> total)</span>
            </h3>
            <div class="flex-gap-2">
                <a href="employees.php?branch=<?= $selected_branch_id ?>" class="btn-custom btn-blue-custom">
                    <i class="fas fa-users"></i> View All Staff
                </a>
            </div>
        </div>
        
        <div class="table-wrap-custom">
            <table class="data-table-custom">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Role</th>
                        <th>Description</th>
                        <th>Active Staff</th>
                        <th>Total Staff</th>
                        <th style="text-align:center;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $index = 1;
                    foreach ($available_roles as $role_key => $role_data): 
                        $active_count = $role_counts[$role_key] ?? 0;
                        $total_count = $total_users_per_role[$role_key] ?? 0;
                    ?>
                        <tr>
                            <td><?= $index++ ?></td>
                            <td>
                                <span class="badge-custom" style="background:<?= $role_data['color'] ?? '#0B5ED7' ?>;">
                                    <i class="fas <?= $role_data['icon'] ?? 'fa-user' ?>"></i>
                                    <?= htmlspecialchars($role_data['name']) ?>
                                </span>
                            </td>
                            <td style="max-width:400px;"><?= htmlspecialchars($role_data['description']) ?></td>
                            <td>
                                <span class="badge-custom badge-success"><?= $active_count ?></span>
                            </td>
                            <td>
                                <span class="badge-custom badge-info"><?= $total_count ?></span>
                            </td>
                            <td>
                                <div class="flex-gap-2 justify-center">
                                    <a href="employees.php?role=<?= $role_key ?>&branch=<?= $selected_branch_id ?>" 
                                       class="btn-custom btn-blue-custom btn-sm-custom">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="add_employee.php?role=<?= $role_key ?>&branch=<?= $selected_branch_id ?>" 
                                       class="btn-custom btn-green-custom btn-sm-custom">
                                        <i class="fas fa-plus"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

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

    console.log('%c🏢 Braick - Role & Department Management', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c✅ Uses SHARED header & sidebar', 'font-size:13px; color:#059669;');
    console.log('%c🌙 FULL DARK MODE inafanya kazi', 'font-size:13px; color:#7C3AED;');
    console.log('%c🛡️ Audit role included', 'font-size:13px; color:#DC2626;');
    console.log('%c👤 User: <?= htmlspecialchars($user_full_name) ?>', 'font-size:13px; color:#059669;');
    console.log('%c📋 Roles: <?= count($available_roles) ?>', 'font-size:13px; color:#0B5ED7;');
</script>

</body>
</html>