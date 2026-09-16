<?php
// ================================================================
// FILE: frontend/pages/admin/view_employee.php
// SUPER ADMIN - VIEW EMPLOYEE DETAILS
// ✅ Uses SHARED header & sidebar (NO DUPLICATES)
// ✅ Blue theme + full dark mode support via --page-* variables
// ================================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../../../backend/config/database.php';
require_once '../../../backend/helpers/functions.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

// ================================================================
// LOGIN PROTECTION
// ================================================================
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

// ================================================================
// GET ADMIN DATA
// ================================================================
$user_id = $_SESSION['user_id'];
$user_full_name = $_SESSION['full_name'] ?? 'Admin';
$user_role = $_SESSION['role'] ?? 'admin';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$username = $_SESSION['username'] ?? '';
$profile_pic = $_SESSION['profile_pic'] ?? '';

// Verify user
$stmt = $db->prepare("SELECT id, full_name, role, status FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user || $user['status'] !== 'active') {
    session_destroy();
    header('Location: ../login.php');
    exit;
}

// ================================================================
// GET EMPLOYEE ID
// ================================================================
$employee_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$selected_branch_id = $_GET['branch'] ?? 'all';

if ($employee_id <= 0) {
    header('Location: employees.php?branch=' . $selected_branch_id);
    exit;
}

// ================================================================
// FETCH EMPLOYEE DETAILS
// ================================================================
$stmt = $db->prepare("
    SELECT 
        u.id, u.username, u.password, u.full_name, u.email, u.phone,
        u.role, u.branch_id, u.status, u.profile_pic, u.specialty,
        u.is_online, u.last_online, u.created_at, u.updated_at,
        b.name as branch_name, b.location as branch_location, b.phone as branch_phone,
        COALESCE((SELECT COUNT(*) FROM activity_logs WHERE user_id = u.id), 0) as total_activities,
        COALESCE((SELECT COUNT(*) FROM patients WHERE created_by = u.id), 0) as total_patients,
        COALESCE((SELECT COUNT(*) FROM visits WHERE doctor_id = u.id), 0) as total_visits,
        COALESCE((SELECT COUNT(*) FROM prescriptions WHERE doctor_id = u.id), 0) as total_prescriptions
    FROM users u
    LEFT JOIN branches b ON u.branch_id = b.id
    WHERE u.id = ?
");
$stmt->execute([$employee_id]);
$employee = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$employee) {
    header('Location: employees.php?branch=' . $selected_branch_id . '&error=notfound');
    exit;
}

// ================================================================
// GET RECENT ACTIVITIES
// ================================================================
$recent_activities = [];
try {
    $stmt = $db->prepare("SELECT * FROM activity_logs WHERE user_id = ? ORDER BY created_at DESC LIMIT 10");
    $stmt->execute([$employee_id]);
    $recent_activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $recent_activities = []; }

// ================================================================
// ROLE LABEL
// ================================================================
$role_labels = [
    'admin' => 'Admin', 'doctor' => 'Doctor', 'reception' => 'Receptionist',
    'pharmacy' => 'Pharmacist', 'laboratory' => 'Lab Technician',
    'cashier' => 'Cashier', 'audit' => 'Audit Officer'
];
$role_display = $role_labels[$employee['role']] ?? ucfirst($employee['role']);

// ================================================================
// PROFILE URLS
// ================================================================
$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

$logo_url = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

// ================================================================
// TIME AGO HELPER
// ================================================================
function time_ago($timestamp) {
    if (empty($timestamp)) return 'Just now';
    $time = strtotime($timestamp);
    $diff = time() - $time;
    if ($diff < 60) return 'Just now';
    if ($diff < 3600) return floor($diff / 60) . 'm ago';
    if ($diff < 86400) return floor($diff / 3600) . 'h ago';
    if ($diff < 604800) return floor($diff / 86400) . 'd ago';
    return date('M d, Y', $time);
}

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
    /* PAGE HEADER */
    .page-header-emp-view {
        background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
        border-radius: 16px;
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

    .page-header-emp-view::before {
        content: '';
        position: absolute;
        top: -60%; right: -10%;
        width: 400px; height: 400px;
        background: rgba(255,255,255,0.05);
        border-radius: 50%;
        pointer-events: none;
    }

    .page-header-emp-view .page-title-emp {
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

    .page-header-emp-view .page-subtitle-emp {
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

    .page-header-emp-view .role-badge-display {
        background: rgba(255,255,255,0.2);
        color: white;
        padding: 4px 14px;
        border-radius: 20px;
        font-size: 0.65rem;
        font-weight: 600;
        text-transform: uppercase;
    }

    .page-header-emp-view .branch-tag-emp {
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

    .page-header-emp-view .btn-header-emp {
        background: rgba(255,255,255,0.15);
        color: white;
        border: 1px solid rgba(255,255,255,0.2);
        padding: 8px 16px;
        border-radius: 8px;
        font-weight: 600;
        font-size: 0.8rem;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        position: relative;
        z-index: 1;
        transition: all 0.3s;
    }

    .page-header-emp-view .btn-header-emp:hover {
        background: rgba(255,255,255,0.25);
        transform: translateY(-2px);
        color: white;
    }

    .page-header-emp-view .btn-header-emp.edit {
        background: linear-gradient(135deg, #F59E0B, #D97706);
        border-color: transparent;
    }

    .page-header-emp-view .btn-header-emp.edit:hover {
        background: linear-gradient(135deg, #D97706, #B45309);
    }

    /* PROFILE CARD */
    .profile-card-emp {
        background: var(--page-bg-card, #FFFFFF);
        border-radius: 16px;
        border: 1px solid var(--page-border, #E2E8F0);
        overflow: hidden;
        transition: all 0.3s ease;
        box-shadow: var(--page-shadow-sm);
        margin-bottom: 20px;
    }

    [data-theme="dark"] .profile-card-emp { background: #1E293B; border-color: #334155; }

    .profile-card-emp:hover {
        border-color: var(--page-primary, #0B5ED7);
        box-shadow: var(--page-shadow-md);
    }

    .profile-header-emp {
        display: flex;
        align-items: center;
        gap: 24px;
        padding: 24px 28px;
        background: linear-gradient(135deg, #0B5ED7 0%, #1A73E8 100%);
    }

    .profile-avatar-emp { flex-shrink: 0; }

    .profile-img-emp {
        width: 100px;
        height: 100px;
        border-radius: 50%;
        object-fit: cover;
        border: 4px solid rgba(255,255,255,0.3);
        background: white;
    }

    .profile-img-placeholder-emp {
        width: 100px;
        height: 100px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 3rem;
        font-weight: 700;
        color: white;
        background: rgba(255,255,255,0.2);
        border: 4px solid rgba(255,255,255,0.3);
    }

    .profile-info-emp h2 {
        color: white;
        font-size: 1.8rem;
        font-weight: 700;
        margin: 0;
        line-height: 1.2;
    }

    .profile-info-emp .profile-username-emp {
        color: rgba(255,255,255,0.8);
        font-size: 0.95rem;
        margin: 2px 0 8px 0;
    }

    .profile-badges-emp {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }

    .profile-badges-emp .role-badge-emp,
    .profile-badges-emp .status-badge-emp {
        padding: 4px 14px;
        font-size: 0.7rem;
        border-radius: 20px;
        font-weight: 600;
        background: rgba(255,255,255,0.2);
        color: white;
    }

    .profile-badges-emp .status-badge-emp.online {
        background: rgba(5, 150, 105, 0.3);
        color: #34D399;
    }

    .profile-badges-emp .status-badge-emp.offline {
        background: rgba(100, 116, 139, 0.3);
        color: #94A3B8;
    }

    /* CARD */
    .card-emp-view {
        background: var(--page-bg-card, #FFFFFF);
        border-radius: 12px;
        border: 1px solid var(--page-border, #E2E8F0);
        padding: 18px 20px;
        transition: all 0.3s ease;
        box-shadow: var(--page-shadow-sm);
    }

    [data-theme="dark"] .card-emp-view { background: #1E293B; border-color: #334155; }

    .card-emp-view:hover {
        border-color: var(--page-primary, #0B5ED7);
        box-shadow: var(--page-shadow-md);
    }

    .card-title-emp {
        font-size: 0.9rem;
        font-weight: 600;
        color: var(--page-text-primary, #1E293B);
        margin: 0 0 12px 0;
        display: flex;
        align-items: center;
    }

    .title-blue-emp { color: #0B5ED7; }
    .title-green-emp { color: #059669; }
    .title-purple-emp { color: #7C3AED; }
    .title-orange-emp { color: #F59E0B; }

    /* DETAIL ITEMS */
    .detail-item-emp {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 8px 0;
        border-bottom: 1px solid var(--page-border, #E2E8F0);
    }

    .detail-item-emp:last-child { border-bottom: none; }

    .detail-label-emp {
        font-size: 0.75rem;
        font-weight: 500;
        color: var(--page-text-secondary, #64748B);
        text-transform: uppercase;
        letter-spacing: 0.03em;
    }

    .detail-value-emp {
        font-size: 0.85rem;
        color: var(--page-text-primary, #1E293B);
        font-weight: 500;
    }

    /* ROLE BADGES */
    .role-badge-emp.role-admin { background: #E8F0FE; color: #0B5ED7; }
    .role-badge-emp.role-doctor { background: #E8F0FE; color: #0B5ED7; }
    .role-badge-emp.role-reception { background: #D1FAE5; color: #059669; }
    .role-badge-emp.role-pharmacy { background: #FEF3C7; color: #D97706; }
    .role-badge-emp.role-laboratory { background: #EDE9FE; color: #7B2FBE; }
    .role-badge-emp.role-cashier { background: #FCE4EC; color: #DC2626; }
    .role-badge-emp.role-audit { background: #FEE2E2; color: #DC2626; }

    [data-theme="dark"] .role-badge-emp.role-admin { background: #1E3A5F; color: #6EA8FE; }
    [data-theme="dark"] .role-badge-emp.role-doctor { background: #1E3A5F; color: #6EA8FE; }
    [data-theme="dark"] .role-badge-emp.role-reception { background: #1A3A2A; color: #34D399; }
    [data-theme="dark"] .role-badge-emp.role-pharmacy { background: #3D2E0A; color: #FBBF24; }
    [data-theme="dark"] .role-badge-emp.role-laboratory { background: #2D1B4E; color: #A78BFA; }
    [data-theme="dark"] .role-badge-emp.role-cashier { background: #3A1A1A; color: #F87171; }
    [data-theme="dark"] .role-badge-emp.role-audit { background: #3A1A1A; color: #F87171; }

    /* STATUS BADGES */
    .status-badge-emp.active { background: #D1FAE5; color: #059669; }
    .status-badge-emp.inactive { background: #FEE2E2; color: #DC2626; }

    [data-theme="dark"] .status-badge-emp.active { background: #1A3A2A; color: #34D399; }
    [data-theme="dark"] .status-badge-emp.inactive { background: #3A1A1A; color: #F87171; }

    /* ACTIVITY ITEM */
    .activity-item-emp {
        display: flex;
        align-items: flex-start;
        gap: 12px;
        padding: 8px;
        border-radius: 8px;
        transition: all 0.3s;
    }

    .activity-item-emp:hover { background: #E8F0FE; }
    [data-theme="dark"] .activity-item-emp:hover { background: #1E3A5F; }

    .activity-icon-emp {
        width: 28px;
        height: 28px;
        border-radius: 50%;
        background: #0B5ED7;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        color: white;
        margin-top: 2px;
    }

    /* BUTTONS */
    .btn-emp-view {
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
        border: none;
        text-decoration: none;
        box-shadow: var(--page-shadow-sm);
        font-family: inherit;
    }

    .btn-emp-view:hover {
        transform: translateY(-2px);
        box-shadow: var(--page-shadow-md);
    }

    .btn-emp-view.btn-sm-emp {
        padding: 5px 10px;
        font-size: 0.65rem;
        border-radius: 6px;
    }

    .btn-edit-emp {
        background: linear-gradient(135deg, #F59E0B, #D97706);
        color: white;
    }
    .btn-edit-emp:hover { background: linear-gradient(135deg, #D97706, #B45309); color: white; }

    .btn-outline-emp {
        background: transparent;
        color: var(--page-text-secondary, #64748B);
        border: 1.5px solid var(--page-border, #E2E8F0);
    }
    .btn-outline-emp:hover {
        background: var(--page-hover, #F8FAFC);
        border-color: var(--page-primary, #0B5ED7);
        color: var(--page-primary, #0B5ED7);
    }

    .btn-activity-emp {
        background: linear-gradient(135deg, #7C3AED, #6D28D9);
        color: white;
    }
    .btn-activity-emp:hover { background: linear-gradient(135deg, #6D28D9, #5B21B6); color: white; }

    .btn-delete-emp {
        background: linear-gradient(135deg, #EF4444, #DC2626);
        color: white;
    }
    .btn-delete-emp:hover { background: linear-gradient(135deg, #DC2626, #B91C1C); color: white; }

    .btn-reactivate-emp {
        background: linear-gradient(135deg, #059669, #047857);
        color: white;
    }
    .btn-reactivate-emp:hover { background: linear-gradient(135deg, #047857, #065F46); color: white; }

    /* MODAL */
    .modal-emp {
        position: fixed;
        top: 0; left: 0; right: 0; bottom: 0;
        z-index: 9999;
        display: none;
        align-items: center;
        justify-content: center;
    }

    .modal-emp.show { display: flex; }

    .modal-overlay-emp {
        position: absolute;
        top: 0; left: 0; right: 0; bottom: 0;
        background: rgba(0,0,0,0.5);
        backdrop-filter: blur(4px);
        cursor: pointer;
    }

    .modal-content-emp {
        background: var(--page-bg-card, #FFFFFF);
        border-radius: 16px;
        max-width: 480px;
        width: 90%;
        position: relative;
        z-index: 1001;
        box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        border: 1px solid var(--page-border, #E2E8F0);
        animation: modalSlideInEmp 0.3s ease;
    }

    [data-theme="dark"] .modal-content-emp { background: #1E293B; }

    @keyframes modalSlideInEmp {
        from { transform: scale(0.9) translateY(20px); opacity: 0; }
        to { transform: scale(1) translateY(0); opacity: 1; }
    }

    .modal-header-emp {
        padding: 16px 20px;
        border-bottom: 1px solid var(--page-border, #E2E8F0);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .modal-header-emp h3 {
        font-size: 1rem;
        font-weight: 600;
        color: var(--page-text-primary, #1E293B);
        margin: 0;
    }

    .modal-close-emp {
        background: none;
        border: none;
        font-size: 1.5rem;
        cursor: pointer;
        color: var(--page-text-secondary, #64748B);
        padding: 0 4px;
        transition: color 0.3s;
    }

    .modal-close-emp:hover { color: var(--page-text-primary, #1E293B); }

    .modal-body-emp {
        padding: 20px;
        color: var(--page-text-primary, #1E293B);
    }

    .modal-footer-emp {
        padding: 16px 20px;
        border-top: 1px solid var(--page-border, #E2E8F0);
        display: flex;
        justify-content: flex-end;
        gap: 8px;
    }

    /* FOOTER */
    .footer-emp-view {
        margin-top: 30px;
        padding: 16px 20px;
        background: var(--page-bg-card, #FFFFFF);
        border-radius: 12px;
        border: 1px solid var(--page-border, #E2E8F0);
        text-align: center;
    }

    [data-theme="dark"] .footer-emp-view { background: #1E293B; border-color: #334155; }

    .footer-emp-view p {
        margin: 0;
        font-size: 0.8rem;
        color: var(--page-text-secondary, #64748B);
    }

    .footer-brand-emp { font-weight: 700; color: #0B5ED7; }

    /* RESPONSIVE */
    @media (max-width: 768px) {
        .page-header-emp-view { padding: 16px 18px; }
        .page-header-emp-view .page-title-emp { font-size: 1.2rem; }
        .profile-header-emp { flex-direction: column; text-align: center; padding: 20px; }
        .profile-info-emp h2 { font-size: 1.4rem; }
        .profile-img-emp, .profile-img-placeholder-emp { width: 80px; height: 80px; font-size: 2rem; }
        .profile-badges-emp { justify-content: center; }
        .detail-item-emp { flex-direction: column; align-items: flex-start; gap: 2px; }
    }

    @media (max-width: 480px) {
        .btn-emp-view { font-size: 0.7rem; padding: 5px 10px; }
        .btn-emp-view.btn-sm-emp { font-size: 0.6rem; padding: 3px 6px; }
        .page-header-emp-view .page-title-emp { font-size: 1rem; }
        .profile-img-emp, .profile-img-placeholder-emp { width: 64px; height: 64px; font-size: 1.5rem; }
        .profile-info-emp h2 { font-size: 1.2rem; }
    }
</style>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <!-- PAGE HEADER -->
    <div class="page-header-emp-view">
        <div>
            <h1 class="page-title-emp">
                <i class="fas fa-user-circle"></i>
                Employee Details
                <span class="role-badge-display">ADMIN</span>
            </h1>
            <p class="page-subtitle-emp">
                <i class="fas fa-user"></i>
                <strong><?= htmlspecialchars($employee['full_name']) ?></strong>
                <span class="branch-tag-emp">
                    <i class="fas fa-store-alt"></i> <?= htmlspecialchars($employee['branch_name'] ?? 'N/A') ?>
                </span>
                <span class="branch-tag-emp" style="background:rgba(255,255,255,0.15);">
                    <i class="fas fa-calendar-day"></i> <?= date('F d, Y') ?>
                </span>
            </p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;position:relative;z-index:1;">
            <a href="edit_employee.php?id=<?= $employee['id'] ?>&branch=<?= $selected_branch_id ?>" class="btn-header-emp edit">
                <i class="fas fa-edit"></i> Edit
            </a>
            <a href="employees.php?branch=<?= $selected_branch_id ?>" class="btn-header-emp">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>

    <!-- EMPLOYEE PROFILE CARD -->
    <div class="profile-card-emp">
        <div class="profile-header-emp">
            <div class="profile-avatar-emp">
                <?php 
                $profile_img = !empty($employee['profile_pic']) ? '/dispensary_system/frontend/assets/uploads/profiles/' . $employee['profile_pic'] : '';
                if (!empty($profile_img) && file_exists($_SERVER['DOCUMENT_ROOT'] . $profile_img)): 
                ?>
                    <img src="<?= $profile_img ?>" alt="<?= htmlspecialchars($employee['full_name']) ?>" class="profile-img-emp">
                <?php else: ?>
                    <div class="profile-img-placeholder-emp">
                        <?= strtoupper(substr($employee['full_name'], 0, 1)) ?>
                    </div>
                <?php endif; ?>
            </div>
            <div class="profile-info-emp">
                <h2><?= htmlspecialchars($employee['full_name']) ?></h2>
                <p class="profile-username-emp">@<?= htmlspecialchars($employee['username']) ?></p>
                <div class="profile-badges-emp">
                    <span class="role-badge-emp role-<?= $employee['role'] ?>">
                        <i class="fas fa-user-tag"></i> <?= $role_display ?>
                    </span>
                    <span class="status-badge-emp <?= $employee['status'] === 'active' ? 'active' : 'inactive' ?>">
                        <?= $employee['status'] === 'active' ? 'Active' : 'Inactive' ?>
                    </span>
                    <?php if ($employee['is_online'] == 1): ?>
                        <span class="status-badge-emp online">
                            <i class="fas fa-circle"></i> Online
                        </span>
                    <?php else: ?>
                        <span class="status-badge-emp offline">
                            <i class="fas fa-circle"></i> Offline
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- EMPLOYEE DETAILS GRID -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:20px;margin-bottom:20px;">
        
        <!-- Personal Information -->
        <div class="card-emp-view">
            <h3 class="card-title-emp">
                <i class="fas fa-user title-blue-emp" style="margin-right:8px;"></i> Personal Information
            </h3>
            <div class="detail-item-emp">
                <span class="detail-label-emp">Full Name</span>
                <span class="detail-value-emp"><?= htmlspecialchars($employee['full_name']) ?></span>
            </div>
            <div class="detail-item-emp">
                <span class="detail-label-emp">Username</span>
                <span class="detail-value-emp">@<?= htmlspecialchars($employee['username']) ?></span>
            </div>
            <div class="detail-item-emp">
                <span class="detail-label-emp">Email</span>
                <span class="detail-value-emp"><?= htmlspecialchars($employee['email']) ?></span>
            </div>
            <div class="detail-item-emp">
                <span class="detail-label-emp">Phone</span>
                <span class="detail-value-emp"><?= htmlspecialchars($employee['phone'] ?? 'N/A') ?></span>
            </div>
            <?php if (!empty($employee['specialty'])): ?>
            <div class="detail-item-emp">
                <span class="detail-label-emp">Specialty</span>
                <span class="detail-value-emp"><?= htmlspecialchars($employee['specialty']) ?></span>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Branch & Role Information -->
        <div class="card-emp-view">
            <h3 class="card-title-emp">
                <i class="fas fa-building title-green-emp" style="margin-right:8px;"></i> Branch & Role
            </h3>
            <div class="detail-item-emp">
                <span class="detail-label-emp">Branch</span>
                <span class="detail-value-emp">
                    <i class="fas fa-store-alt"></i> <?= htmlspecialchars($employee['branch_name'] ?? 'N/A') ?>
                </span>
            </div>
            <div class="detail-item-emp">
                <span class="detail-label-emp">Location</span>
                <span class="detail-value-emp"><?= htmlspecialchars($employee['branch_location'] ?? 'N/A') ?></span>
            </div>
            <div class="detail-item-emp">
                <span class="detail-label-emp">Role</span>
                <span class="detail-value-emp">
                    <span class="role-badge-emp role-<?= $employee['role'] ?>" style="font-size:0.75rem;padding:2px 12px;">
                        <?= $role_display ?>
                    </span>
                </span>
            </div>
            <div class="detail-item-emp">
                <span class="detail-label-emp">Status</span>
                <span class="detail-value-emp">
                    <span class="status-badge-emp <?= $employee['status'] === 'active' ? 'active' : 'inactive' ?>" style="font-size:0.75rem;padding:2px 12px;">
                        <?= $employee['status'] === 'active' ? 'Active' : 'Inactive' ?>
                    </span>
                </span>
            </div>
            <div class="detail-item-emp">
                <span class="detail-label-emp">Online Status</span>
                <span class="detail-value-emp">
                    <?php if ($employee['is_online'] == 1): ?>
                        <span style="color:#059669;"><i class="fas fa-circle"></i> Online</span>
                    <?php else: ?>
                        <span style="color:#94A3B8;"><i class="fas fa-circle"></i> Offline</span>
                    <?php endif; ?>
                </span>
            </div>
            <?php if ($employee['last_online']): ?>
            <div class="detail-item-emp">
                <span class="detail-label-emp">Last Online</span>
                <span class="detail-value-emp"><?= date('M d, Y H:i:s', strtotime($employee['last_online'])) ?></span>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Statistics -->
        <div class="card-emp-view">
            <h3 class="card-title-emp">
                <i class="fas fa-chart-bar title-purple-emp" style="margin-right:8px;"></i> Statistics
            </h3>
            <div class="detail-item-emp">
                <span class="detail-label-emp">Total Activities</span>
                <span class="detail-value-emp" style="color:#0B5ED7;font-weight:700;"><?= number_format($employee['total_activities'] ?? 0) ?></span>
            </div>
            <div class="detail-item-emp">
                <span class="detail-label-emp">Patients Registered</span>
                <span class="detail-value-emp" style="color:#059669;font-weight:700;"><?= number_format($employee['total_patients'] ?? 0) ?></span>
            </div>
            <div class="detail-item-emp">
                <span class="detail-label-emp">Visits</span>
                <span class="detail-value-emp" style="color:#7C3AED;font-weight:700;"><?= number_format($employee['total_visits'] ?? 0) ?></span>
            </div>
            <div class="detail-item-emp">
                <span class="detail-label-emp">Prescriptions</span>
                <span class="detail-value-emp" style="color:#F59E0B;font-weight:700;"><?= number_format($employee['total_prescriptions'] ?? 0) ?></span>
            </div>
            <div class="detail-item-emp">
                <span class="detail-label-emp">Member Since</span>
                <span class="detail-value-emp"><?= date('M d, Y', strtotime($employee['created_at'])) ?></span>
            </div>
        </div>
        
    </div>

    <!-- RECENT ACTIVITIES -->
    <div class="card-emp-view" style="margin-bottom:20px;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;flex-wrap:wrap;gap:8px;">
            <h3 class="card-title-emp" style="margin:0;">
                <i class="fas fa-clock title-blue-emp" style="margin-right:8px;"></i> Recent Activities
                <span style="font-size:0.7rem;color:var(--page-text-secondary);font-weight:400;margin-left:8px;">(Last 10 activities)</span>
            </h3>
            <a href="employee_activities.php?id=<?= $employee['id'] ?>&branch=<?= $selected_branch_id ?>" 
               style="font-size:0.75rem;color:#0B5ED7;font-weight:500;text-decoration:none;">
                View All →
            </a>
        </div>
        <?php if (count($recent_activities) > 0): ?>
            <div style="max-height:240px;overflow-y:auto;">
                <?php foreach ($recent_activities as $activity): ?>
                    <div class="activity-item-emp">
                        <div class="activity-icon-emp">
                            <i class="fas fa-circle" style="font-size:6px;"></i>
                        </div>
                        <div>
                            <p style="font-weight:500;font-size:0.85rem;color:var(--page-text-primary);margin:0;">
                                <?= htmlspecialchars($activity['action'] ?? 'Action') ?>
                            </p>
                            <p style="font-size:0.75rem;color:var(--page-text-secondary);margin:2px 0;">
                                <?= htmlspecialchars($activity['details'] ?? '') ?>
                            </p>
                            <p style="font-size:0.65rem;color:var(--page-text-muted);margin:0;">
                                <?= isset($activity['created_at']) ? time_ago($activity['created_at']) : 'Just now' ?>
                            </p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div style="text-align:center;color:var(--page-text-muted);font-size:0.85rem;padding:20px;">
                <i class="fas fa-inbox" style="font-size:2rem;display:block;margin-bottom:8px;"></i>
                No activities found for this employee
            </div>
        <?php endif; ?>
    </div>

    <!-- QUICK ACTIONS -->
    <div class="card-emp-view">
        <h3 class="card-title-emp" style="margin-bottom:12px;">
            <i class="fas fa-bolt title-blue-emp" style="margin-right:8px;"></i> Quick Actions
        </h3>
        <div style="display:flex;flex-wrap:wrap;gap:8px;">
            <a href="edit_employee.php?id=<?= $employee['id'] ?>&branch=<?= $selected_branch_id ?>" 
               class="btn-emp-view btn-edit-emp btn-sm-emp">
                <i class="fas fa-edit"></i> Edit Employee
            </a>
            <a href="employee_activities.php?id=<?= $employee['id'] ?>&branch=<?= $selected_branch_id ?>" 
               class="btn-emp-view btn-activity-emp btn-sm-emp">
                <i class="fas fa-clock"></i> View All Activities
            </a>
            <?php if ($employee['status'] === 'active'): ?>
                <button onclick="confirmDeleteEmp(<?= $employee['id'] ?>, '<?= htmlspecialchars($employee['full_name']) ?>')" 
                        class="btn-emp-view btn-delete-emp btn-sm-emp">
                    <i class="fas fa-user-slash"></i> Deactivate
                </button>
            <?php else: ?>
                <button onclick="confirmReactivateEmp(<?= $employee['id'] ?>, '<?= htmlspecialchars($employee['full_name']) ?>')" 
                        class="btn-emp-view btn-reactivate-emp btn-sm-emp">
                    <i class="fas fa-undo"></i> Reactivate
                </button>
            <?php endif; ?>
            <a href="employees.php?branch=<?= $selected_branch_id ?>" 
               class="btn-emp-view btn-outline-emp btn-sm-emp">
                <i class="fas fa-arrow-left"></i> Back to List
            </a>
        </div>
    </div>

    <!-- FOOTER -->
    <footer class="footer-emp-view">
        <p>
            <span class="footer-brand-emp">Braick Dispensary</span> Management System
            <span style="color:#CBD5E1;margin:0 8px;">|</span>
            Employee Details
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
<div id="deleteModalEmp" class="modal-emp">
    <div class="modal-overlay-emp" onclick="closeModalEmp()"></div>
    <div class="modal-content-emp">
        <div class="modal-header-emp">
            <h3><i class="fas fa-exclamation-triangle" style="color:#DC2626;margin-right:8px;"></i> Deactivate Employee</h3>
            <button onclick="closeModalEmp()" class="modal-close-emp">&times;</button>
        </div>
        <div class="modal-body-emp">
            <p>Are you sure you want to deactivate <strong id="deleteNameEmp"></strong>?</p>
            <p style="font-size:0.85rem;color:var(--page-text-secondary);margin-top:8px;">
                <i class="fas fa-info-circle"></i> 
                This employee will no longer be able to login. Their records will remain in the system for historical data.
            </p>
        </div>
        <div class="modal-footer-emp">
            <button onclick="closeModalEmp()" class="btn-emp-view btn-outline-emp btn-sm-emp">Cancel</button>
            <a href="#" id="deleteLinkEmp" class="btn-emp-view btn-delete-emp btn-sm-emp">
                <i class="fas fa-user-slash"></i> Deactivate
            </a>
        </div>
    </div>
</div>

<!-- ================================================================ -->
<!-- REACTIVATE MODAL -->
<!-- ================================================================ -->
<div id="reactivateModalEmp" class="modal-emp">
    <div class="modal-overlay-emp" onclick="closeReactivateModalEmp()"></div>
    <div class="modal-content-emp">
        <div class="modal-header-emp">
            <h3><i class="fas fa-undo" style="color:#059669;margin-right:8px;"></i> Reactivate Employee</h3>
            <button onclick="closeReactivateModalEmp()" class="modal-close-emp">&times;</button>
        </div>
        <div class="modal-body-emp">
            <p>Are you sure you want to reactivate <strong id="reactivateNameEmp"></strong>?</p>
            <p style="font-size:0.85rem;color:var(--page-text-secondary);margin-top:8px;">
                <i class="fas fa-info-circle"></i> 
                This employee will be able to login again.
            </p>
        </div>
        <div class="modal-footer-emp">
            <button onclick="closeReactivateModalEmp()" class="btn-emp-view btn-outline-emp btn-sm-emp">Cancel</button>
            <a href="#" id="reactivateLinkEmp" class="btn-emp-view btn-reactivate-emp btn-sm-emp">
                <i class="fas fa-undo"></i> Reactivate
            </a>
        </div>
    </div>
</div>

<!-- ================================================================ -->
<!-- PAGE-SPECIFIC JAVASCRIPT -->
<!-- ================================================================ -->
<script>
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

    // ================================================================
    // MODAL FUNCTIONS
    // ================================================================
    function confirmDeleteEmp(id, name) {
        document.getElementById('deleteNameEmp').textContent = name;
        document.getElementById('deleteLinkEmp').href = 'employees.php?delete=' + id + '&branch=<?= $selected_branch_id ?>';
        document.getElementById('deleteModalEmp').classList.add('show');
    }
    
    function closeModalEmp() {
        document.getElementById('deleteModalEmp').classList.remove('show');
    }

    function confirmReactivateEmp(id, name) {
        document.getElementById('reactivateNameEmp').textContent = name;
        document.getElementById('reactivateLinkEmp').href = 'employees.php?reactivate=' + id + '&branch=<?= $selected_branch_id ?>';
        document.getElementById('reactivateModalEmp').classList.add('show');
    }
    
    function closeReactivateModalEmp() {
        document.getElementById('reactivateModalEmp').classList.remove('show');
    }

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeModalEmp();
            closeReactivateModalEmp();
        }
    });

    console.log('%c👤 Braick - Employee Details', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c✅ Uses SHARED header & sidebar', 'font-size:13px; color:#34D399;');
    console.log('%c✅ NO duplicate header JavaScript', 'font-size:13px; color:#34D399;');
    console.log('%c🌙 Dark mode: Handled by header', 'font-size:13px; color:#7C3AED;');
    console.log('%c👤 Employee: <?= htmlspecialchars($employee['full_name']) ?>', 'font-size:13px; color:#059669;');
    console.log('%c🎭 Role: <?= $role_display ?>', 'font-size:13px; color:#7B2FBE;');
</script>

</body>
</html>