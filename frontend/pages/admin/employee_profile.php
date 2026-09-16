<?php
// ================================================================
// FILE: frontend/pages/admin/employee_profile.php
// SUPER ADMIN - EMPLOYEE PROFILE
// ✅ Uses SHARED header & sidebar (NO DUPLICATES)
// ✅ Blue theme + full dark mode support via --page-* variables
// ================================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ================================================================
// LOGIN PROTECTION
// ================================================================
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

// ================================================================
// GET ADMIN DATA
// ================================================================
$user_id = $_SESSION['user_id'];
$user_full_name = $_SESSION['full_name'] ?? 'Admin';
$user_role = $_SESSION['role'] ?? 'admin';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$user_username = $_SESSION['username'] ?? '';
$profile_pic = $_SESSION['profile_pic'] ?? '';

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
$employee_id = (int)($_GET['id'] ?? 0);
$selected_branch_id = $_GET['branch'] ?? 'all';

if ($employee_id <= 0) {
    header('Location: employees.php?branch=' . $selected_branch_id);
    exit;
}

// ================================================================
// GET EMPLOYEE DATA
// ================================================================
$stmt = $db->prepare("
    SELECT u.*, b.name as branch_name 
    FROM users u
    LEFT JOIN branches b ON u.branch_id = b.id
    WHERE u.id = ? AND u.role != 'admin'
");
$stmt->execute([$employee_id]);
$employee = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$employee) {
    header('Location: employees.php?branch=' . $selected_branch_id);
    exit;
}

// ================================================================
// AVAILABLE ROLES
// ================================================================
$available_roles = [
    'doctor' => ['name' => 'Medical Doctor', 'icon' => 'fa-user-md', 'color' => '#059669'],
    'pharmacy' => ['name' => 'Pharmacy Staff', 'icon' => 'fa-prescription-bottle', 'color' => '#7C3AED'],
    'reception' => ['name' => 'Receptionist', 'icon' => 'fa-headset', 'color' => '#0B5ED7'],
    'laboratory' => ['name' => 'Lab Technician', 'icon' => 'fa-flask', 'color' => '#0D9488'],
    'cashier' => ['name' => 'Cashier', 'icon' => 'fa-cash-register', 'color' => '#D97706'],
    'audit' => ['name' => 'Audit Officer', 'icon' => 'fa-clipboard-check', 'color' => '#DC2626'],
    'admin' => ['name' => 'Administrator', 'icon' => 'fa-user-tie', 'color' => '#DC2626']
];

// ================================================================
// GET BRANCH STAFF COUNT
// ================================================================
$branch_staff_count = 0;
if (!empty($employee['branch_id'])) {
    try {
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM users WHERE branch_id = ? AND role != 'admin'");
        $stmt->execute([$employee['branch_id']]);
        $branch_staff_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    } catch (Exception $e) {
        $branch_staff_count = 0;
    }
}

$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

$logo_url = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

// ================================================================
// ✅ SHARED HEADER & SIDEBAR
// ================================================================
include_once '../../components/admin_header.php';
include_once '../../components/admin_sidebar.php';
?>

<!-- ================================================================
     PAGE-SPECIFIC CSS - TUMIA VARIABLES ZA HEADER (--page-*)
     ================================================================ -->
<style>
    /* ================================================================
       PAGE HEADER
       ================================================================ */
    .page-header-emp-profile {
        border-bottom: 3px solid var(--page-primary, #0B5ED7);
        padding-bottom: 16px;
        margin-bottom: 24px;
        display: flex;
        flex-wrap: wrap;
        justify-content: space-between;
        align-items: center;
        gap: 12px;
    }

    .page-header-emp-profile .page-title-emp {
        color: var(--page-primary, #0B5ED7);
        font-size: 1.8rem;
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 12px;
        margin: 0;
    }

    .page-header-emp-profile .page-title-emp i { font-size: 2rem; opacity: 0.9; }

    .page-header-emp-profile .page-subtitle-emp {
        color: var(--page-text-secondary, #64748B);
        font-size: 0.95rem;
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
        margin-top: 6px;
    }

    .page-header-emp-profile .branch-tag {
        background: var(--page-primary, #0B5ED7);
        color: white;
        padding: 3px 14px;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }

    /* ================================================================
       CARD
       ================================================================ */
    .card-emp-profile {
        background: var(--page-bg-card, #FFFFFF);
        border-radius: 18px;
        padding: 24px;
        border: 2px solid var(--page-border, #E2E8F0);
        transition: all 0.3s ease;
        box-shadow: var(--page-shadow-sm, 0 1px 3px rgba(0,0,0,0.06));
    }

    .card-emp-profile:hover {
        border-color: var(--page-primary, #0B5ED7);
        box-shadow: var(--page-shadow-md, 0 4px 12px rgba(0,0,0,0.08));
    }

    .card-title-emp-profile {
        font-size: 1.05rem;
        font-weight: 700;
        color: var(--page-primary, #0B5ED7);
        border-bottom: 2px solid var(--page-border, #E2E8F0);
        padding-bottom: 12px;
        margin-bottom: 16px;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .card-title-emp-profile i {
        color: var(--page-primary, #0B5ED7);
    }

    /* ================================================================
       INFO LABELS & VALUES
       ================================================================ */
    .info-label-emp-profile {
        font-size: 0.75rem;
        color: var(--page-text-secondary, #64748B);
        font-weight: 500;
        margin: 0;
    }

    .info-value-emp-profile {
        font-size: 0.95rem;
        font-weight: 600;
        color: var(--page-text-primary, #1E293B);
        margin: 2px 0 0 0;
    }

    /* ================================================================
       BADGES
       ================================================================ */
    .badge-emp-profile {
        padding: 4px 14px;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        color: white;
        border: none;
    }

    .badge-success-emp-profile { background: #059669; }
    .badge-danger-emp-profile { background: #DC2626; }
    .badge-info-emp-profile { background: var(--page-primary, #0B5ED7); }
    .badge-warning-emp-profile { background: #D97706; color: #1E293B; }
    .badge-purple-emp-profile { background: #7C3AED; }

    [data-theme="dark"] .badge-warning-emp-profile { color: #1E293B; }

    /* ================================================================
       ROLE BADGE
       ================================================================ */
    .role-badge-emp-profile {
        padding: 6px 16px;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        background: var(--page-primary-bg, #E8F0FE);
        color: var(--page-primary, #0B5ED7);
        border: 1px solid var(--page-border, #E2E8F0);
        transition: all 0.3s ease;
    }

    .role-badge-emp-profile:hover {
        transform: scale(1.02);
        box-shadow: 0 2px 8px rgba(11, 94, 215, 0.15);
    }

    /* ================================================================
       STAT BOX
       ================================================================ */
    .stat-box-emp-profile {
        text-align: center;
        padding: 16px;
        border-radius: 12px;
        background: var(--page-hover, #F8FAFC);
        border: 1px solid var(--page-border, #E2E8F0);
        transition: all 0.3s ease;
    }

    [data-theme="dark"] .stat-box-emp-profile {
        background: #0F172A;
        border-color: #334155;
    }

    .stat-box-emp-profile:hover {
        border-color: var(--page-primary, #0B5ED7);
        background: var(--page-primary-bg, #E8F0FE);
        transform: translateY(-2px);
    }

    .stat-box-emp-profile .stat-number-emp {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--page-primary, #0B5ED7);
        margin: 0;
    }

    .stat-box-emp-profile .stat-label-emp {
        font-size: 0.7rem;
        color: var(--page-text-secondary, #64748B);
        margin: 4px 0 0 0;
    }

    /* ================================================================
       BUTTONS
       ================================================================ */
    .btn-emp-profile {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        padding: 8px 20px;
        border-radius: 10px;
        font-weight: 600;
        font-size: 0.85rem;
        transition: all 0.3s ease;
        cursor: pointer;
        border: none;
        text-decoration: none;
        min-height: 38px;
        font-family: inherit;
    }

    .btn-emp-profile:hover { transform: translateY(-2px); }

    .btn-green-emp-profile {
        background: #059669;
        color: white;
        box-shadow: 0 4px 12px rgba(5, 150, 105, 0.3);
    }

    .btn-green-emp-profile:hover { 
        box-shadow: 0 8px 20px rgba(5, 150, 105, 0.4); 
        color: white;
    }

    .btn-outline-emp-profile {
        background: transparent;
        color: var(--page-text-primary, #1E293B);
        border: 2px solid var(--page-border, #E2E8F0);
    }

    .btn-outline-emp-profile:hover {
        background: var(--page-hover, #F8FAFC);
        border-color: var(--page-primary, #0B5ED7);
        color: var(--page-primary, #0B5ED7);
    }

    [data-theme="dark"] .btn-outline-emp-profile {
        color: #F1F5F9;
        border-color: #334155;
    }

    [data-theme="dark"] .btn-outline-emp-profile:hover {
        background: #0F172A;
        border-color: #6EA8FE;
        color: #6EA8FE;
    }

    .btn-sm-emp-profile {
        padding: 6px 14px;
        font-size: 0.75rem;
        min-height: 32px;
    }

    /* ================================================================
       PROFILE AVATAR
       ================================================================ */
    .profile-avatar-emp {
        width: 100px;
        height: 100px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 3rem;
        font-weight: 700;
        background: var(--page-primary-bg, #E8F0FE);
        color: var(--page-primary, #0B5ED7);
        border: 4px solid var(--page-primary, #0B5ED7);
        flex-shrink: 0;
    }

    /* ================================================================
       INFO GRID LAYOUT
       ================================================================ */
    .info-stack-emp > * + * {
        margin-top: 12px;
    }

    .grid-emp-profile {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
    }

    .grid-stats-emp {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 16px;
        margin-bottom: 20px;
    }

    /* ================================================================
       FOOTER
       ================================================================ */
    .footer-emp-profile {
        padding: 14px 0;
        border-top: 2px solid var(--page-border, #E2E8F0);
        margin-top: 20px;
        text-align: center;
        font-size: 0.7rem;
        color: var(--page-text-secondary, #64748B);
    }

    .footer-emp-profile .footer-brand-emp-profile {
        color: var(--page-primary, #0B5ED7);
        font-weight: 600;
    }

    /* ================================================================
       RESPONSIVE
       ================================================================ */
    @media (max-width: 1024px) {
        .grid-emp-profile { grid-template-columns: 1fr; }
        .grid-stats-emp { grid-template-columns: repeat(2, 1fr); }
    }

    @media (max-width: 768px) {
        .page-header-emp-profile {
            flex-direction: column;
            align-items: flex-start !important;
        }
        .page-header-emp-profile .page-title-emp { font-size: 1.3rem; }
        .card-emp-profile { padding: 16px; }
        .grid-stats-emp { grid-template-columns: 1fr 1fr; }
        .profile-avatar-emp { width: 70px; height: 70px; font-size: 2rem; }
        .stat-box-emp-profile .stat-number-emp { font-size: 1.2rem; }
    }

    @media (max-width: 480px) {
        .grid-stats-emp { grid-template-columns: 1fr; }
    }

    /* Print */
    @media print {
        .btn-emp-profile { display: none !important; }
        .card-emp-profile { break-inside: avoid; box-shadow: none !important; border: 1px solid #ddd; }
    }
</style>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <!-- ================================================================ -->
    <!-- PAGE HEADER -->
    <!-- ================================================================ -->
    <div class="page-header-emp-profile">
        <div>
            <h1 class="page-title-emp">
                <i class="fas fa-user-circle"></i> Employee Profile
            </h1>
            <p class="page-subtitle-emp">
                View employee details, role and branch information
                <span class="branch-tag">
                    <i class="fas fa-store-alt"></i> <?= htmlspecialchars($employee['branch_name'] ?? 'N/A') ?>
                </span>
                <span class="badge-emp-profile badge-info-emp-profile">
                    <i class="fas fa-user"></i> <?= htmlspecialchars($employee['full_name']) ?>
                </span>
            </p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <a href="edit_employee.php?id=<?= $employee['id'] ?>&branch=<?= $selected_branch_id ?>" class="btn-emp-profile btn-green-emp-profile btn-sm-emp-profile">
                <i class="fas fa-edit"></i> Edit
            </a>
            <a href="employees.php?branch=<?= $selected_branch_id ?>" class="btn-emp-profile btn-outline-emp-profile btn-sm-emp-profile">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- PROFILE HEADER CARD -->
    <!-- ================================================================ -->
    <div class="card-emp-profile" style="margin-bottom:20px;">
        <div style="display:flex;flex-wrap:wrap;align-items:center;gap:24px;justify-content:center;">
            <div class="profile-avatar-emp">
                <?= strtoupper(substr($employee['full_name'], 0, 1)) ?>
            </div>
            
            <div style="flex:1;text-align:center;min-width:220px;">
                <h2 style="font-size:1.5rem;font-weight:700;color:var(--page-text-primary,#1E293B);margin:0;">
                    <?= htmlspecialchars($employee['full_name']) ?>
                </h2>
                <p style="color:var(--page-text-secondary,#64748B);margin:6px 0 0 0;">
                    <i class="fas fa-briefcase"></i> 
                    <?= htmlspecialchars($available_roles[$employee['role']]['name'] ?? ucfirst($employee['role'])) ?>
                </p>
                <div style="display:flex;flex-wrap:wrap;gap:8px;margin-top:12px;justify-content:center;">
                    <span class="badge-emp-profile badge-info-emp-profile">
                        <i class="fas fa-store-alt"></i> <?= htmlspecialchars($employee['branch_name'] ?? 'Not Assigned') ?>
                    </span>
                    <span class="badge-emp-profile <?= ($employee['status'] ?? 'active') === 'active' ? 'badge-success-emp-profile' : 'badge-danger-emp-profile' ?>">
                        <i class="fas fa-circle" style="font-size:6px;"></i> <?= ucfirst($employee['status'] ?? 'Active') ?>
                    </span>
                    <span class="badge-emp-profile badge-warning-emp-profile">
                        <i class="fas fa-id-card"></i> <?= htmlspecialchars($employee['username']) ?>
                    </span>
                    <?php if (!empty($employee['specialty'])): ?>
                        <span class="badge-emp-profile badge-purple-emp-profile">
                            <i class="fas fa-stethoscope"></i> <?= htmlspecialchars($employee['specialty']) ?>
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- STATS ROW -->
    <!-- ================================================================ -->
    <div class="grid-stats-emp">
        <div class="stat-box-emp-profile">
            <p class="stat-number-emp">1</p>
            <p class="stat-label-emp"><i class="fas fa-user-tag"></i> Role</p>
        </div>
        <div class="stat-box-emp-profile">
            <p class="stat-number-emp"><?= $branch_staff_count ?></p>
            <p class="stat-label-emp"><i class="fas fa-users"></i> Branch Staff</p>
        </div>
        <div class="stat-box-emp-profile">
            <p class="stat-number-emp" style="font-size:1.1rem;"><?= date('d/m/Y', strtotime($employee['created_at'])) ?></p>
            <p class="stat-label-emp"><i class="fas fa-calendar-plus"></i> Joined</p>
        </div>
        <div class="stat-box-emp-profile">
            <p class="stat-number-emp">
                <?= ($employee['is_online'] ?? 0) == 1 ? '🟢' : '⚪' ?>
            </p>
            <p class="stat-label-emp"><i class="fas fa-circle"></i> Status</p>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- DETAILS GRID -->
    <!-- ================================================================ -->
    <div class="grid-emp-profile">
        
        <!-- PERSONAL INFORMATION -->
        <div class="card-emp-profile">
            <h3 class="card-title-emp-profile">
                <i class="fas fa-user-circle"></i> Personal Information
            </h3>
            <div class="info-stack-emp">
                <div>
                    <p class="info-label-emp-profile">Full Name</p>
                    <p class="info-value-emp-profile"><?= htmlspecialchars($employee['full_name']) ?></p>
                </div>
                <div>
                    <p class="info-label-emp-profile">Username</p>
                    <p class="info-value-emp-profile"><?= htmlspecialchars($employee['username']) ?></p>
                </div>
                <div>
                    <p class="info-label-emp-profile">Email</p>
                    <p class="info-value-emp-profile"><?= htmlspecialchars($employee['email']) ?></p>
                </div>
                <div>
                    <p class="info-label-emp-profile">Phone</p>
                    <p class="info-value-emp-profile"><?= htmlspecialchars($employee['phone'] ?? 'Not provided') ?></p>
                </div>
                <div>
                    <p class="info-label-emp-profile">Primary Role</p>
                    <p class="info-value-emp-profile"><?= htmlspecialchars($available_roles[$employee['role']]['name'] ?? ucfirst($employee['role'])) ?></p>
                </div>
                <?php if (!empty($employee['specialty'])): ?>
                    <div>
                        <p class="info-label-emp-profile">Specialty</p>
                        <p class="info-value-emp-profile"><?= htmlspecialchars($employee['specialty']) ?></p>
                    </div>
                <?php endif; ?>
                <div>
                    <p class="info-label-emp-profile">Branch</p>
                    <p class="info-value-emp-profile"><?= htmlspecialchars($employee['branch_name'] ?? 'Not Assigned') ?></p>
                </div>
                <div>
                    <p class="info-label-emp-profile">Status</p>
                    <p class="info-value-emp-profile">
                        <span class="badge-emp-profile <?= ($employee['status'] ?? 'active') === 'active' ? 'badge-success-emp-profile' : 'badge-danger-emp-profile' ?>">
                            <i class="fas fa-circle" style="font-size:6px;"></i> <?= ucfirst($employee['status'] ?? 'Active') ?>
                        </span>
                    </p>
                </div>
                <div>
                    <p class="info-label-emp-profile">Online Status</p>
                    <p class="info-value-emp-profile">
                        <?php if (($employee['is_online'] ?? 0) == 1): ?>
                            <span class="badge-emp-profile badge-success-emp-profile"><i class="fas fa-circle"></i> Online</span>
                        <?php else: ?>
                            <span class="badge-emp-profile badge-danger-emp-profile"><i class="fas fa-circle"></i> Offline</span>
                        <?php endif; ?>
                    </p>
                </div>
                <div>
                    <p class="info-label-emp-profile">Joined</p>
                    <p class="info-value-emp-profile"><?= date('F d, Y h:i A', strtotime($employee['created_at'])) ?></p>
                </div>
            </div>
        </div>

        <!-- ROLE INFORMATION -->
        <div class="card-emp-profile">
            <h3 class="card-title-emp-profile">
                <i class="fas fa-user-tag"></i> Role Information
            </h3>
            
            <div class="info-stack-emp">
                <div>
                    <p class="info-label-emp-profile">Primary Role</p>
                    <p class="info-value-emp-profile">
                        <span class="role-badge-emp-profile">
                            <i class="fas <?= $available_roles[$employee['role']]['icon'] ?? 'fa-user' ?>" 
                               style="color: <?= $available_roles[$employee['role']]['color'] ?? '#0B5ED7' ?>"></i>
                            <?= htmlspecialchars($available_roles[$employee['role']]['name'] ?? ucfirst($employee['role'])) ?>
                        </span>
                    </p>
                </div>
                
                <?php if (!empty($employee['specialty'])): ?>
                    <div>
                        <p class="info-label-emp-profile">Specialty / Department</p>
                        <p class="info-value-emp-profile">
                            <span class="role-badge-emp-profile" style="background:var(--page-success-bg,#D1FAE5);color:var(--page-success,#059669);">
                                <i class="fas fa-stethoscope"></i>
                                <?= htmlspecialchars($employee['specialty']) ?>
                            </span>
                        </p>
                    </div>
                <?php endif; ?>
                
                <div>
                    <p class="info-label-emp-profile">Role Description</p>
                    <p class="info-value-emp-profile" style="font-size:0.85rem;font-weight:400;color:var(--page-text-secondary,#64748B);">
                        <?php 
                            $role_desc = [
                                'doctor' => 'Provides medical consultations, diagnoses, and treatments to patients.',
                                'pharmacy' => 'Dispenses medications and manages pharmacy inventory.',
                                'reception' => 'Handles patient registration, appointments, and front desk operations.',
                                'laboratory' => 'Conducts laboratory tests and analyzes samples.',
                                'cashier' => 'Handles patient billing, payments, and financial transactions.',
                                'audit' => 'Monitors system compliance and performs audit checks.',
                                'admin' => 'Manages system settings, user accounts, and overall system operations.'
                            ];
                            echo htmlspecialchars($role_desc[$employee['role']] ?? 'No description available.');
                        ?>
                    </p>
                </div>
                
                <div style="margin-top:12px;padding-top:12px;border-top:1px solid var(--page-border,#E2E8F0);">
                    <p class="info-label-emp-profile">Role Key</p>
                    <p class="info-value-emp-profile">
                        <code style="background:var(--page-hover,#F8FAFC);padding:4px 12px;border-radius:6px;font-size:0.8rem;">
                            <?= htmlspecialchars($employee['role']) ?>
                        </code>
                    </p>
                </div>
                
                <div>
                    <p class="info-label-emp-profile">Available Roles</p>
                    <div style="display:flex;flex-wrap:wrap;gap:8px;margin-top:6px;">
                        <?php foreach ($available_roles as $key => $role): ?>
                            <span style="font-size:0.7rem;padding:4px 10px;border-radius:20px;
                                         background:var(--page-hover,#F8FAFC);
                                         color:var(--page-text-secondary,#64748B);
                                         border:1px solid var(--page-border,#E2E8F0);">
                                <?= htmlspecialchars($role['name']) ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer-emp-profile">
        <p>
            <span class="footer-brand-emp-profile">Braick Dispensary</span> Management System
            <span style="color:#CBD5E1;margin:0 8px;">|</span>
            Employee Profile
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
    // ✅ FOOTER TIME ONLY (header ina date/time yake)
    // ================================================================
    setInterval(function() {
        var now = new Date();
        var timeStr = now.toLocaleTimeString('en-US', {
            hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true
        });
        var ftEl = document.getElementById('footerTime');
        if (ftEl) ftEl.textContent = timeStr;
    }, 1000);

    console.log('%c👤 Braick - Employee Profile', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c✅ Uses SHARED header & sidebar', 'font-size:13px; color:#059669;');
    console.log('%c✅ NO duplicate dark mode JavaScript', 'font-size:13px; color:#059669;');
    console.log('%c🌙 Dark mode: Handled by header', 'font-size:13px; color:#7C3AED;');
    console.log('%c👤 Employee: <?= htmlspecialchars($employee['full_name']) ?>', 'font-size:13px; color:#059669;');
    console.log('%c🏢 Branch: <?= htmlspecialchars($employee['branch_name'] ?? 'Not Assigned') ?>', 'font-size:13px; color:#7C3AED;');
    console.log('%c🔑 Role: <?= htmlspecialchars($employee['role']) ?>', 'font-size:13px; color:#0B5ED7;');
</script>

</body>
</html>