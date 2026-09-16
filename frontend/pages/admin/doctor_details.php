<?php
// ================================================================
// FILE: frontend/pages/admin/doctor_details.php
// DOCTOR DETAILS - VIEW ALL DOCTOR INFORMATION
// ✅ Uses SHARED header & sidebar
// ✅ Full dark mode support
// ✅ Blue page header card
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

$user_id = $_SESSION['user_id'];
$user_full_name = $_SESSION['full_name'] ?? 'Admin';
$user_role = $_SESSION['role'] ?? 'admin';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$username = $_SESSION['username'] ?? '';
$profile_pic = $_SESSION['profile_pic'] ?? '';

require_once '../../../backend/config/database.php';

$db = Database::getInstance()->getConnection();

$doctor_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$selected_branch_id = isset($_GET['branch']) ? $_GET['branch'] : 'all';

if ($doctor_id <= 0) {
    header('Location: doctors_list.php?branch=' . $selected_branch_id);
    exit;
}

// ================================================================
// GET DOCTOR DATA
// ================================================================
$stmt = $db->prepare("
    SELECT u.*, b.name as branch_name,
           (SELECT COUNT(*) FROM patients WHERE assigned_doctor_id = u.id AND branch_id = u.branch_id) as total_patients,
           (SELECT COUNT(*) FROM visits WHERE doctor_id = u.id) as total_visits,
           (SELECT COUNT(*) FROM prescriptions WHERE doctor_id = u.id) as total_prescriptions,
           (SELECT COUNT(*) FROM lab_tests WHERE doctor_id = u.id) as total_lab_tests
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
// GET ASSIGNED PATIENTS
// ================================================================
$stmt = $db->prepare("
    SELECT p.*, 
           (SELECT COUNT(*) FROM visits WHERE patient_id = p.id) as total_visits,
           (SELECT COUNT(*) FROM bills WHERE patient_id = p.id AND status != 'cancelled') as total_bills
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
// GET BRANCHES FOR FILTER
// ================================================================
$branches_list = [];
$stmt = $db->query("SELECT id, name FROM branches WHERE status = 'active' ORDER BY name");
$branches_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

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

    /* BODY & MAIN CONTENT */
    body { background: var(--page-bg-body, #F0F4F8); }
    html[data-theme="dark"] body { background: #0F172A !important; }
    .main-content { background: var(--page-bg-body, #F0F4F8); }
    html[data-theme="dark"] .main-content { background: #0F172A !important; color: #F1F5F9; }

    /* ================================================================
       PROFILE HEADER - BLUE CARD
       ================================================================ */
    .profile-header-card {
        background: linear-gradient(135deg, #0B5ED7 0%, #0A4CA8 50%, #083D8A 100%);
        border-radius: 20px;
        padding: 28px 32px;
        color: white;
        position: relative;
        overflow: hidden;
        margin-bottom: 24px;
        box-shadow: 0 8px 32px rgba(11, 94, 215, 0.35), 0 4px 12px rgba(11, 94, 215, 0.2);
        display: flex;
        align-items: center;
        gap: 20px;
        flex-wrap: wrap;
    }

    .profile-header-card::before {
        content: '';
        position: absolute;
        top: -50%;
        right: -10%;
        width: 400px;
        height: 400px;
        background: radial-gradient(circle, rgba(255,255,255,0.12) 0%, transparent 70%);
        border-radius: 50%;
        pointer-events: none;
    }

    .profile-header-card::after {
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

    .profile-header-avatar {
        width: 88px;
        height: 88px;
        border-radius: 20px;
        background: rgba(255,255,255,0.2);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 2.6rem;
        border: 3px solid rgba(255,255,255,0.3);
        flex-shrink: 0;
        backdrop-filter: blur(10px);
        position: relative;
        z-index: 1;
    }

    .profile-header-info {
        flex: 1;
        min-width: 240px;
        position: relative;
        z-index: 1;
    }

    .profile-header-name {
        font-size: 1.6rem;
        font-weight: 800;
        margin: 0 0 6px 0;
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
        color: white;
        letter-spacing: -0.02em;
    }

    .profile-header-badges {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
        margin-bottom: 10px;
    }

    .profile-badge-custom {
        background: rgba(255,255,255,0.18);
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: 600;
        backdrop-filter: blur(4px);
        border: 1px solid rgba(255,255,255,0.25);
        display: inline-flex;
        align-items: center;
        gap: 5px;
        color: white;
    }

    .profile-badge-custom.online {
        background: rgba(52, 211, 153, 0.25);
        border-color: rgba(52, 211, 153, 0.4);
        color: #A7F3D0;
    }

    .profile-badge-custom.offline {
        background: rgba(248, 113, 113, 0.25);
        border-color: rgba(248, 113, 113, 0.4);
        color: #FECACA;
    }

    .profile-header-contact {
        display: flex;
        align-items: center;
        gap: 16px;
        flex-wrap: wrap;
        font-size: 0.82rem;
        color: rgba(255,255,255,0.85);
    }

    .profile-header-contact span {
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }

    .profile-header-actions {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
        position: relative;
        z-index: 1;
    }

    .btn-glass {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 9px 18px;
        background: rgba(255,255,255,0.18);
        border: 1.5px solid rgba(255,255,255,0.3);
        border-radius: 10px;
        color: white;
        text-decoration: none;
        font-weight: 600;
        font-size: 0.82rem;
        transition: all 0.3s ease;
        backdrop-filter: blur(10px);
        white-space: nowrap;
    }

    .btn-glass:hover {
        background: rgba(255,255,255,0.3);
        transform: translateY(-2px);
        color: white;
        box-shadow: 0 4px 16px rgba(0,0,0,0.15);
    }

    /* ================================================================
       STAT CARDS
       ================================================================ */
    .stat-card-mini {
        background: var(--page-bg-card, #FFFFFF);
        border-radius: 14px;
        padding: 18px 20px;
        border: 2px solid var(--page-border, #E2E8F0);
        transition: all 0.3s ease;
        text-align: center;
    }

    html[data-theme="dark"] .stat-card-mini {
        background: #1E293B;
        border-color: #334155;
    }

    .stat-card-mini:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.08);
        border-color: #0B5ED7;
    }

    html[data-theme="dark"] .stat-card-mini:hover {
        box-shadow: 0 8px 25px rgba(0,0,0,0.4);
    }

    .stat-card-mini .stat-icon {
        font-size: 1.5rem;
        margin-bottom: 6px;
    }

    .stat-card-mini .stat-number {
        font-size: 1.8rem;
        font-weight: 800;
        color: #0B5ED7;
        line-height: 1;
    }

    html[data-theme="dark"] .stat-card-mini .stat-number { color: #6EA8FE; }

    .stat-card-mini .stat-number.green { color: #059669; }
    .stat-card-mini .stat-number.orange { color: #F59E0B; }
    .stat-card-mini .stat-number.purple { color: #7C3AED; }
    .stat-card-mini .stat-number.red { color: #EF4444; }

    html[data-theme="dark"] .stat-card-mini .stat-number.green { color: #34D399; }
    html[data-theme="dark"] .stat-card-mini .stat-number.orange { color: #FBBF24; }
    html[data-theme="dark"] .stat-card-mini .stat-number.purple { color: #9B4DCA; }

    .stat-card-mini .stat-label {
        font-size: 0.7rem;
        color: var(--page-text-secondary, #64748B);
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.03em;
        margin-top: 6px;
    }

    /* ================================================================
       CARD
       ================================================================ */
    .card-custom {
        background: var(--page-bg-card, #FFFFFF);
        border-radius: 16px;
        padding: 20px 24px;
        border: 2px solid var(--page-border, #E2E8F0);
        transition: all 0.3s ease;
        margin-bottom: 24px;
    }

    html[data-theme="dark"] .card-custom {
        background: #1E293B;
        border-color: #334155;
    }

    .card-custom:hover {
        border-color: #0B5ED7;
        box-shadow: 0 4px 12px rgba(11, 94, 215, 0.05);
    }

    .card-header-custom {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 14px;
        flex-wrap: wrap;
        gap: 10px;
    }

    .card-title-custom {
        font-size: 0.95rem;
        font-weight: 700;
        color: var(--page-text-primary, #1E293B);
        display: flex;
        align-items: center;
        gap: 8px;
        margin: 0;
    }

    html[data-theme="dark"] .card-title-custom { color: #F1F5F9; }
    .card-title-custom i { color: #0B5ED7; }
    html[data-theme="dark"] .card-title-custom i { color: #6EA8FE; }

    .badge-count {
        font-size: 0.72rem;
        font-weight: 400;
        color: var(--page-text-secondary, #64748B);
        margin-left: 6px;
    }

    /* ================================================================
       INFO ROWS
       ================================================================ */
    .info-row-custom {
        display: flex;
        padding: 10px 0;
        border-bottom: 1px solid var(--page-border, #E2E8F0);
        gap: 12px;
    }

    html[data-theme="dark"] .info-row-custom { border-bottom-color: #334155; }

    .info-row-custom:last-child { border-bottom: none; }

    .info-row-custom .info-label {
        width: 150px;
        font-weight: 600;
        color: var(--page-text-secondary, #64748B);
        font-size: 0.82rem;
        flex-shrink: 0;
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .info-row-custom .info-label i { color: #0B5ED7; width: 16px; }
    html[data-theme="dark"] .info-row-custom .info-label i { color: #6EA8FE; }

    .info-row-custom .info-value {
        flex: 1;
        color: var(--page-text-primary, #1E293B);
        font-size: 0.85rem;
        font-weight: 500;
    }

    html[data-theme="dark"] .info-row-custom .info-value { color: #F1F5F9; }

    /* ================================================================
       STATUS BADGES
       ================================================================ */
    .status-badge-custom {
        padding: 4px 14px;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: 700;
        display: inline-flex;
        align-items: center;
        gap: 5px;
        letter-spacing: 0.02em;
    }

    .status-badge-custom.online {
        background: #D1FAE5;
        color: #059669;
        border: 1px solid #A7F3D0;
    }

    .status-badge-custom.offline {
        background: #FEE2E2;
        color: #DC2626;
        border: 1px solid #FECACA;
    }

    html[data-theme="dark"] .status-badge-custom.online {
        background: #1A3A2A;
        color: #34D399;
        border-color: #065F46;
    }

    html[data-theme="dark"] .status-badge-custom.offline {
        background: #3A1A1A;
        color: #F87171;
        border-color: #7F1D1D;
    }

    /* ================================================================
       DATA TABLE
       ================================================================ */
    .table-wrap-custom {
        overflow-x: auto;
        border-radius: 12px;
    }

    .data-table-custom {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.82rem;
    }

    .data-table-custom thead th {
        text-align: left;
        padding: 12px 16px;
        font-weight: 700;
        font-size: 0.65rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: white;
        background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
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
        font-size: 0.68rem;
        font-weight: 700;
        display: inline-flex;
        align-items: center;
        gap: 4px;
        border: none;
    }

    .badge-success { background: #D1FAE5; color: #059669; }
    .badge-warning { background: #FEF3C7; color: #D97706; }
    .badge-danger  { background: #FEE2E2; color: #DC2626; }
    .badge-info    { background: #E8F0FE; color: #0B5ED7; }
    .badge-secondary { background: #E2E8F0; color: #64748B; }

    html[data-theme="dark"] .badge-success { background: #1A3A2A; color: #34D399; }
    html[data-theme="dark"] .badge-warning { background: #3A2A1A; color: #FBBF24; }
    html[data-theme="dark"] .badge-danger  { background: #3A1A1A; color: #F87171; }
    html[data-theme="dark"] .badge-info    { background: #1E3A5F; color: #6EA8FE; }
    html[data-theme="dark"] .badge-secondary { background: #2D3748; color: #94A3B8; }

    /* ================================================================
       ACTION BUTTONS
       ================================================================ */
    .action-buttons {
        display: flex;
        gap: 5px;
        flex-wrap: wrap;
    }

    .btn-action {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 5px 10px;
        border-radius: 7px;
        font-size: 0.65rem;
        font-weight: 700;
        transition: all 0.3s ease;
        text-decoration: none;
        border: none;
        cursor: pointer;
        white-space: nowrap;
    }

    .btn-action i { font-size: 0.7rem; }

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
    }

    .btn-delete {
        background: #FEE2E2;
        color: #DC2626;
    }
    .btn-delete:hover {
        background: #DC2626;
        color: white;
        transform: translateY(-1px);
    }

    html[data-theme="dark"] .btn-view {
        background: #1E3A5F;
        color: #6EA8FE;
    }
    html[data-theme="dark"] .btn-view:hover {
        background: #0B5ED7;
        color: white;
    }

    html[data-theme="dark"] .btn-edit {
        background: #3A2A1A;
        color: #FBBF24;
    }
    html[data-theme="dark"] .btn-edit:hover {
        background: #D97706;
        color: white;
    }

    html[data-theme="dark"] .btn-delete {
        background: #3A1A1A;
        color: #F87171;
    }
    html[data-theme="dark"] .btn-delete:hover {
        background: #DC2626;
        color: white;
    }

    /* ================================================================
       GRID UTILITIES
       ================================================================ */
    .grid-stats {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 16px;
        margin-bottom: 24px;
    }

    .grid-info {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 0 32px;
    }

    @media (max-width: 1024px) {
        .grid-stats { grid-template-columns: repeat(2, 1fr); }
    }

    @media (max-width: 768px) {
        .profile-header-card { padding: 20px; }
        .profile-header-avatar { width: 68px; height: 68px; font-size: 2rem; }
        .profile-header-name { font-size: 1.25rem; }
        .grid-info { grid-template-columns: 1fr; gap: 0; }
        .info-row-custom { flex-direction: column; gap: 4px; }
        .info-row-custom .info-label { width: 100%; }
        .data-table-custom { font-size: 0.72rem; }
        .data-table-custom th, .data-table-custom td { padding: 8px 10px; }
    }

    @media (max-width: 480px) {
        .grid-stats { grid-template-columns: 1fr; }
    }

    /* Print */
    @media print {
        .profile-header-card { background: #0B5ED7 !important; -webkit-print-color-adjust: exact; }
        .btn-glass, .btn-action { display: none !important; }
    }
</style>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <!-- ================================================================
         BLUE PROFILE HEADER
         ================================================================ -->
    <div class="profile-header-card">
        <div class="profile-header-avatar">
            <i class="fas fa-user-md"></i>
        </div>
        <div class="profile-header-info">
            <h1 class="profile-header-name">
                Dr. <?= htmlspecialchars($doctor['full_name']) ?>
            </h1>
            <div class="profile-header-badges">
                <span class="profile-badge-custom">
                    <i class="fas fa-user-md"></i> Doctor
                </span>
                <span class="profile-badge-custom <?= $doctor['is_online'] == 1 ? 'online' : 'offline' ?>">
                    <i class="fas fa-circle" style="font-size:0.5rem;"></i>
                    <?= $doctor['is_online'] == 1 ? 'Online' : 'Offline' ?>
                </span>
                <?php if ($doctor['specialty']): ?>
                    <span class="profile-badge-custom">
                        <i class="fas fa-stethoscope"></i> <?= htmlspecialchars($doctor['specialty']) ?>
                    </span>
                <?php endif; ?>
            </div>
            <div class="profile-header-contact">
                <span><i class="fas fa-envelope"></i> <?= htmlspecialchars($doctor['email']) ?></span>
                <span><i class="fas fa-phone"></i> <?= htmlspecialchars($doctor['phone'] ?? 'N/A') ?></span>
                <span><i class="fas fa-store-alt"></i> <?= htmlspecialchars($doctor['branch_name'] ?? 'N/A') ?></span>
                <?php if ($doctor['last_online']): ?>
                    <span><i class="fas fa-clock"></i> Last online: <?= date('M d, Y h:i A', strtotime($doctor['last_online'])) ?></span>
                <?php endif; ?>
            </div>
        </div>
        <div class="profile-header-actions">
            <a href="edit_doctor.php?id=<?= $doctor['id'] ?>&branch=<?= $selected_branch_id ?>" class="btn-glass">
                <i class="fas fa-edit"></i> Edit
            </a>
            <a href="doctors_list.php?branch=<?= $selected_branch_id ?>" class="btn-glass">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>

    <!-- ================================================================
         STATS
         ================================================================ -->
    <div class="grid-stats">
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
    </div>

    <!-- ================================================================
         DOCTOR INFORMATION
         ================================================================ -->
    <div class="card-custom">
        <div class="card-header-custom">
            <h3 class="card-title-custom">
                <i class="fas fa-info-circle"></i> Doctor Information
            </h3>
        </div>
        <div class="grid-info">
            <div class="info-row-custom">
                <span class="info-label"><i class="fas fa-user"></i> Full Name</span>
                <span class="info-value">Dr. <?= htmlspecialchars($doctor['full_name']) ?></span>
            </div>
            <div class="info-row-custom">
                <span class="info-label"><i class="fas fa-user-md"></i> Role</span>
                <span class="info-value">Doctor</span>
            </div>
            <div class="info-row-custom">
                <span class="info-label"><i class="fas fa-envelope"></i> Email</span>
                <span class="info-value"><?= htmlspecialchars($doctor['email']) ?></span>
            </div>
            <div class="info-row-custom">
                <span class="info-label"><i class="fas fa-phone"></i> Phone</span>
                <span class="info-value"><?= htmlspecialchars($doctor['phone'] ?? 'N/A') ?></span>
            </div>
            <div class="info-row-custom">
                <span class="info-label"><i class="fas fa-stethoscope"></i> Specialty</span>
                <span class="info-value"><?= htmlspecialchars($doctor['specialty'] ?? 'General') ?></span>
            </div>
            <div class="info-row-custom">
                <span class="info-label"><i class="fas fa-store-alt"></i> Branch</span>
                <span class="info-value"><?= htmlspecialchars($doctor['branch_name'] ?? 'N/A') ?></span>
            </div>
            <div class="info-row-custom">
                <span class="info-label"><i class="fas fa-circle"></i> Status</span>
                <span class="info-value">
                    <span class="status-badge-custom <?= $doctor['is_online'] == 1 ? 'online' : 'offline' ?>">
                        <i class="fas fa-circle" style="font-size:0.5rem;"></i>
                        <?= $doctor['is_online'] == 1 ? 'Online' : 'Offline' ?>
                    </span>
                </span>
            </div>
            <div class="info-row-custom">
                <span class="info-label"><i class="fas fa-calendar-alt"></i> Registered</span>
                <span class="info-value"><?= date('M d, Y h:i A', strtotime($doctor['created_at'])) ?></span>
            </div>
            <?php if ($doctor['last_online']): ?>
                <div class="info-row-custom">
                    <span class="info-label"><i class="fas fa-clock"></i> Last Online</span>
                    <span class="info-value"><?= date('M d, Y h:i A', strtotime($doctor['last_online'])) ?></span>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ================================================================
         ASSIGNED PATIENTS
         ================================================================ -->
    <div class="card-custom">
        <div class="card-header-custom">
            <h3 class="card-title-custom">
                <i class="fas fa-users"></i> Assigned Patients
                <span class="badge-count">(<?= count($assigned_patients) ?> patients)</span>
            </h3>
            <a href="patients.php?doctor=<?= $doctor['id'] ?>&branch=<?= $selected_branch_id ?>" class="btn-action btn-view">
                View All <i class="fas fa-arrow-right"></i>
            </a>
        </div>
        <div class="table-wrap-custom">
            <table class="data-table-custom">
                <thead>
                    <tr>
                        <th>Patient ID</th>
                        <th>Name</th>
                        <th>Phone</th>
                        <th style="text-align:center;">Visits</th>
                        <th style="text-align:center;">Bills</th>
                        <th>Registered</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($assigned_patients) > 0): ?>
                        <?php foreach ($assigned_patients as $patient): ?>
                            <tr>
                                <td style="font-family:monospace;font-size:0.72rem;"><?= htmlspecialchars($patient['patient_id']) ?></td>
                                <td style="font-weight:600;"><?= htmlspecialchars($patient['full_name']) ?></td>
                                <td><?= htmlspecialchars($patient['phone'] ?? 'N/A') ?></td>
                                <td style="text-align:center;">
                                    <span class="badge-custom badge-info"><?= $patient['total_visits'] ?? 0 ?></span>
                                </td>
                                <td style="text-align:center;">
                                    <span class="badge-custom badge-success"><?= $patient['total_bills'] ?? 0 ?></span>
                                </td>
                                <td style="font-size:0.75rem;"><?= date('M d, Y', strtotime($patient['created_at'])) ?></td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="patient_details.php?id=<?= $patient['id'] ?>&branch=<?= $selected_branch_id ?>" 
                                           class="btn-action btn-view" title="View Patient">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                        <a href="edit_patient.php?id=<?= $patient['id'] ?>&branch=<?= $selected_branch_id ?>" 
                                           class="btn-action btn-edit" title="Edit Patient">
                                            <i class="fas fa-edit"></i> Edit
                                        </a>
                                        <a href="patients.php?delete=<?= $patient['id'] ?>&branch=<?= $selected_branch_id ?>" 
                                           class="btn-action btn-delete" 
                                           onclick="return confirm('Are you sure you want to delete this patient?\n\nPatient: <?= htmlspecialchars($patient['full_name']) ?>\nID: <?= htmlspecialchars($patient['patient_id']) ?>\n\nThis will delete ALL related data.')" 
                                           title="Delete Patient">
                                            <i class="fas fa-trash"></i> Delete
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" style="text-align:center;padding:24px;color:var(--page-text-muted);">
                                <i class="fas fa-user-slash" style="font-size:1.5rem;display:block;margin-bottom:8px;opacity:0.5;"></i>
                                No patients assigned to this doctor
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ================================================================
         RECENT VISITS
         ================================================================ -->
    <div class="card-custom">
        <div class="card-header-custom">
            <h3 class="card-title-custom">
                <i class="fas fa-notes-medical"></i> Recent Visits
                <span class="badge-count">(<?= $doctor['total_visits'] ?? 0 ?> total)</span>
            </h3>
            <a href="visits.php?doctor=<?= $doctor['id'] ?>&branch=<?= $selected_branch_id ?>" class="btn-action btn-view">
                View All <i class="fas fa-arrow-right"></i>
            </a>
        </div>
        <div class="table-wrap-custom">
            <table class="data-table-custom">
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
                                <td style="font-family:monospace;font-size:0.72rem;"><?= htmlspecialchars($visit['visit_number']) ?></td>
                                <td style="font-weight:600;"><?= htmlspecialchars($visit['patient_name']) ?></td>
                                <td style="font-size:0.75rem;"><?= date('M d, Y', strtotime($visit['visit_date'])) ?></td>
                                <td><span class="badge-custom badge-info"><?= ucfirst($visit['visit_type'] ?? 'N/A') ?></span></td>
                                <td>
                                    <span class="badge-custom badge-<?= $visit['status_color'] ?? 'secondary' ?>">
                                        <?= ucfirst($visit['status'] ?? 'N/A') ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="visit_details.php?id=<?= $visit['id'] ?>&branch=<?= $selected_branch_id ?>" class="btn-action btn-view">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" style="text-align:center;padding:24px;color:var(--page-text-muted);">
                                <i class="fas fa-notes-medical" style="font-size:1.5rem;display:block;margin-bottom:8px;opacity:0.5;"></i>
                                No visits found
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ================================================================
         RECENT PRESCRIPTIONS
         ================================================================ -->
    <div class="card-custom">
        <div class="card-header-custom">
            <h3 class="card-title-custom">
                <i class="fas fa-prescription"></i> Recent Prescriptions
                <span class="badge-count">(<?= $doctor['total_prescriptions'] ?? 0 ?> total)</span>
            </h3>
            <a href="prescriptions.php?doctor=<?= $doctor['id'] ?>&branch=<?= $selected_branch_id ?>" class="btn-action btn-view">
                View All <i class="fas fa-arrow-right"></i>
            </a>
        </div>
        <div class="table-wrap-custom">
            <table class="data-table-custom">
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
                                <td style="font-family:monospace;font-size:0.72rem;"><?= htmlspecialchars($prescription['prescription_number']) ?></td>
                                <td><?= htmlspecialchars($prescription['patient_name']) ?></td>
                                <td>
                                    <span class="badge-custom badge-<?= $prescription['status_color'] ?? 'secondary' ?>">
                                        <?= ucfirst($prescription['status'] ?? 'N/A') ?>
                                    </span>
                                </td>
                                <td style="font-size:0.75rem;"><?= date('M d, Y', strtotime($prescription['created_at'])) ?></td>
                                <td>
                                    <a href="prescription_details.php?id=<?= $prescription['id'] ?>&branch=<?= $selected_branch_id ?>" class="btn-action btn-view">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" style="text-align:center;padding:24px;color:var(--page-text-muted);">
                                <i class="fas fa-prescription" style="font-size:1.5rem;display:block;margin-bottom:8px;opacity:0.5;"></i>
                                No prescriptions found
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ================================================================
         RECENT LAB TESTS
         ================================================================ -->
    <div class="card-custom">
        <div class="card-header-custom">
            <h3 class="card-title-custom">
                <i class="fas fa-flask"></i> Recent Lab Tests
                <span class="badge-count">(<?= $doctor['total_lab_tests'] ?? 0 ?> total)</span>
            </h3>
            <a href="lab_tests.php?doctor=<?= $doctor['id'] ?>&branch=<?= $selected_branch_id ?>" class="btn-action btn-view">
                View All <i class="fas fa-arrow-right"></i>
            </a>
        </div>
        <div class="table-wrap-custom">
            <table class="data-table-custom">
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
                                    <span class="badge-custom badge-<?= $test['status_color'] ?? 'secondary' ?>">
                                        <?= ucfirst(str_replace('_', ' ', $test['status'] ?? 'N/A')) ?>
                                    </span>
                                </td>
                                <td style="font-size:0.75rem;"><?= date('M d, Y', strtotime($test['created_at'])) ?></td>
                                <td>
                                    <a href="lab_test_details.php?id=<?= $test['id'] ?>&branch=<?= $selected_branch_id ?>" class="btn-action btn-view">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" style="text-align:center;padding:24px;color:var(--page-text-muted);">
                                <i class="fas fa-flask" style="font-size:1.5rem;display:block;margin-bottom:8px;opacity:0.5;"></i>
                                No lab tests found
                            </td>
                        </tr>
                    <?php endif; ?>
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

    console.log('%c🏥 Braick - Doctor Details', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c✅ Uses SHARED header & sidebar', 'font-size:13px; color:#059669;');
    console.log('%c🌙 FULL DARK MODE inafanya kazi', 'font-size:13px; color:#7C3AED;');
    console.log('%c✅ USING: bills table (NOT patient_bills)', 'font-size:13px; color:#34D399;');
    console.log('%c👤 Admin: <?= htmlspecialchars($user_full_name) ?>', 'font-size:13px; color:#059669;');
    console.log('%c👨‍⚕️ Doctor: Dr. <?= htmlspecialchars($doctor['full_name']) ?>', 'font-size:13px; color:#059669;');
</script>

</body>
</html>