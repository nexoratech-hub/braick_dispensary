<?php
// ================================================================
// FILE: frontend/pages/admin/lab_test_details.php
// SUPER ADMIN - LAB TEST DETAILS
// ✅ Uses SHARED header & sidebar
// ✅ Full dark mode support
// ✅ Blue theme profile header
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

require_once __DIR__ . '/../../../backend/config/database.php';
require_once __DIR__ . '/../../../backend/helpers/functions.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database connection error: " . $e->getMessage());
}

$test_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$selected_branch_id = $_GET['branch'] ?? 'all';

if ($test_id <= 0) {
    header('Location: lab_tests.php?branch=' . $selected_branch_id);
    exit;
}

// ================================================================
// FETCH LAB TEST
// ================================================================
$stmt = $db->prepare("
    SELECT 
        lt.*,
        p.id as patient_id,
        p.full_name as patient_name,
        p.patient_id as patient_id_number,
        p.phone as patient_phone,
        p.gender as patient_gender,
        p.date_of_birth as patient_dob,
        d.full_name as doctor_name,
        l.full_name as lab_technician_name,
        v.visit_number,
        v.visit_date,
        v.visit_type,
        b.name as branch_name,
        b.location as branch_location,
        (SELECT COUNT(*) FROM lab_tests lt2 WHERE lt2.patient_id = p.id) as total_lab_requests
    FROM lab_tests lt
    LEFT JOIN visits v ON lt.visit_id = v.id
    LEFT JOIN patients p ON lt.patient_id = p.id
    LEFT JOIN users d ON lt.doctor_id = d.id
    LEFT JOIN users l ON lt.lab_technician_id = l.id
    LEFT JOIN branches b ON lt.branch_id = b.id
    WHERE lt.id = ?
");
$stmt->execute([$test_id]);
$test = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$test) {
    header('Location: lab_tests.php?branch=' . $selected_branch_id . '&error=notfound');
    exit;
}

// ================================================================
// STATUS HELPERS
// ================================================================
function getStatusColor($status) {
    $colors = [
        'pending' => '#F59E0B',
        'in_progress' => '#0B5ED7',
        'completed' => '#059669',
        'cancelled' => '#EF4444'
    ];
    return $colors[$status] ?? '#64748B';
}

function getStatusIcon($status) {
    $icons = [
        'pending' => 'fa-clock',
        'in_progress' => 'fa-spinner fa-spin',
        'completed' => 'fa-check-circle',
        'cancelled' => 'fa-times-circle'
    ];
    return $icons[$status] ?? 'fa-circle';
}

// ================================================================
// GET BRANCHES
// ================================================================
$branches = [];
$stmt = $db->query("SELECT id, name FROM branches WHERE status = 'active' ORDER BY name");
$branches = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
       BLUE PROFILE HEADER
       ================================================================ */
    .lab-profile-header {
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

    .lab-profile-header::before {
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

    .lab-profile-header::after {
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

    .lab-profile-icon {
        width: 84px;
        height: 84px;
        border-radius: 20px;
        background: rgba(255,255,255,0.2);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 2.4rem;
        border: 3px solid rgba(255,255,255,0.3);
        flex-shrink: 0;
        backdrop-filter: blur(10px);
        position: relative;
        z-index: 1;
    }

    .lab-profile-info {
        flex: 1;
        min-width: 240px;
        position: relative;
        z-index: 1;
    }

    .lab-profile-name {
        font-size: 1.6rem;
        font-weight: 800;
        margin: 0 0 8px 0;
        color: white;
        letter-spacing: -0.02em;
    }

    .lab-profile-badges {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
        margin-bottom: 8px;
    }

    .lab-profile-badge {
        background: rgba(255,255,255,0.18);
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 0.72rem;
        font-weight: 600;
        backdrop-filter: blur(4px);
        border: 1px solid rgba(255,255,255,0.25);
        display: inline-flex;
        align-items: center;
        gap: 5px;
        color: white;
    }

    .lab-profile-contact {
        display: flex;
        align-items: center;
        gap: 16px;
        flex-wrap: wrap;
        font-size: 0.82rem;
        color: rgba(255,255,255,0.85);
    }

    .lab-profile-contact span {
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }

    .lab-profile-actions {
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
        cursor: pointer;
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
    .lab-stat-card {
        background: var(--page-bg-card, #FFFFFF);
        border-radius: 14px;
        padding: 18px 20px;
        border: 2px solid var(--page-border, #E2E8F0);
        transition: all 0.3s ease;
        text-align: center;
    }

    html[data-theme="dark"] .lab-stat-card {
        background: #1E293B;
        border-color: #334155;
    }

    .lab-stat-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.08);
        border-color: #0B5ED7;
    }

    html[data-theme="dark"] .lab-stat-card:hover {
        box-shadow: 0 8px 25px rgba(0,0,0,0.4);
    }

    .lab-stat-card .stat-icon { font-size: 1.6rem; margin-bottom: 6px; }
    
    .lab-stat-card .stat-number {
        font-size: 1.7rem;
        font-weight: 800;
        color: #0B5ED7;
        line-height: 1.1;
    }

    html[data-theme="dark"] .lab-stat-card .stat-number { color: #6EA8FE; }

    .lab-stat-card .stat-number.green { color: #059669; }
    .lab-stat-card .stat-number.orange { color: #F59E0B; }
    .lab-stat-card .stat-number.purple { color: #7C3AED; }

    html[data-theme="dark"] .lab-stat-card .stat-number.green { color: #34D399; }
    html[data-theme="dark"] .lab-stat-card .stat-number.orange { color: #FBBF24; }
    html[data-theme="dark"] .lab-stat-card .stat-number.purple { color: #9B4DCA; }

    .lab-stat-card .stat-label {
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
    .lab-card {
        background: var(--page-bg-card, #FFFFFF);
        border-radius: 16px;
        padding: 20px 24px;
        border: 2px solid var(--page-border, #E2E8F0);
        transition: all 0.3s ease;
        margin-bottom: 24px;
    }

    html[data-theme="dark"] .lab-card {
        background: #1E293B;
        border-color: #334155;
    }

    .lab-card:hover {
        border-color: #0B5ED7;
        box-shadow: 0 4px 12px rgba(11, 94, 215, 0.05);
    }

    .lab-card-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 14px;
        flex-wrap: wrap;
        gap: 10px;
    }

    .lab-card-title {
        font-size: 0.95rem;
        font-weight: 700;
        color: var(--page-text-primary, #1E293B);
        display: flex;
        align-items: center;
        gap: 8px;
        margin: 0;
    }

    html[data-theme="dark"] .lab-card-title { color: #F1F5F9; }
    .lab-card-title i { color: #0B5ED7; }
    html[data-theme="dark"] .lab-card-title i { color: #6EA8FE; }

    /* ================================================================
       INFO ROWS
       ================================================================ */
    .lab-info-row {
        display: flex;
        padding: 10px 0;
        border-bottom: 1px solid var(--page-border, #E2E8F0);
        gap: 12px;
        align-items: center;
    }

    html[data-theme="dark"] .lab-info-row { border-bottom-color: #334155; }
    .lab-info-row:last-child { border-bottom: none; }

    .lab-info-label {
        width: 150px;
        font-weight: 700;
        color: #0B5ED7;
        font-size: 0.78rem;
        flex-shrink: 0;
    }

    html[data-theme="dark"] .lab-info-label { color: #6EA8FE; }

    .lab-info-value {
        flex: 1;
        color: var(--page-text-primary, #1E293B);
        font-size: 0.85rem;
        font-weight: 500;
        word-break: break-word;
    }

    html[data-theme="dark"] .lab-info-value { color: #F1F5F9; }

    .empty-value { color: #94A3B8; font-style: italic; font-weight: 400; }
    .price-value { color: #059669; font-weight: 700; }
    html[data-theme="dark"] .price-value { color: #34D399; }

    /* ================================================================
       STATUS BADGES
       ================================================================ */
    .lab-status-badge {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 6px 18px;
        border-radius: 50px;
        font-size: 0.78rem;
        font-weight: 700;
        letter-spacing: 0.03em;
        text-transform: uppercase;
    }

    .lab-status-badge.pending {
        background: #FEF3C7;
        color: #D97706;
        border: 1px solid #FBBF24;
    }

    .lab-status-badge.in_progress {
        background: #E8F0FE;
        color: #0B5ED7;
        border: 1px solid #6EA8FE;
    }

    .lab-status-badge.completed {
        background: #D1FAE5;
        color: #059669;
        border: 1px solid #34D399;
    }

    .lab-status-badge.cancelled {
        background: #FEE2E2;
        color: #DC2626;
        border: 1px solid #F87171;
    }

    html[data-theme="dark"] .lab-status-badge.pending {
        background: #3D2E0A;
        color: #FBBF24;
        border-color: #FBBF24;
    }

    html[data-theme="dark"] .lab-status-badge.in_progress {
        background: #1E3A5F;
        color: #6EA8FE;
        border-color: #6EA8FE;
    }

    html[data-theme="dark"] .lab-status-badge.completed {
        background: #1A3A2A;
        color: #34D399;
        border-color: #34D399;
    }

    html[data-theme="dark"] .lab-status-badge.cancelled {
        background: #3A1A1A;
        color: #F87171;
        border-color: #F87171;
    }

    /* Small badges */
    .lab-badge {
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 0.68rem;
        font-weight: 700;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }

    .lab-badge-success { background: #D1FAE5; color: #059669; }
    .lab-badge-warning { background: #FEF3C7; color: #D97706; }
    .lab-badge-danger  { background: #FEE2E2; color: #DC2626; }
    .lab-badge-info    { background: #E8F0FE; color: #0B5ED7; }

    html[data-theme="dark"] .lab-badge-success { background: #1A3A2A; color: #34D399; }
    html[data-theme="dark"] .lab-badge-warning { background: #3D2E0A; color: #FBBF24; }
    html[data-theme="dark"] .lab-badge-danger  { background: #3A1A1A; color: #F87171; }
    html[data-theme="dark"] .lab-badge-info    { background: #1E3A5F; color: #6EA8FE; }

    /* ================================================================
       RESULTS BOX
       ================================================================ */
    .lab-results-box {
        background: #F8FAFC;
        border-radius: 12px;
        padding: 18px 20px;
        border: 1px solid #E2E8F0;
        font-family: 'Courier New', monospace;
        font-size: 0.85rem;
        white-space: pre-wrap;
        word-wrap: break-word;
        min-height: 80px;
        color: #1E293B;
        line-height: 1.6;
    }

    html[data-theme="dark"] .lab-results-box {
        background: #0F172A;
        border-color: #334155;
        color: #F1F5F9;
    }

    /* Interpretation & Notes Boxes */
    .lab-interpretation-box {
        background: #EFF6FF;
        border-radius: 12px;
        padding: 16px 20px;
        border: 1px solid #93C5FD;
        color: #1E40AF;
        font-size: 0.85rem;
        line-height: 1.6;
        margin-top: 12px;
    }

    html[data-theme="dark"] .lab-interpretation-box {
        background: #1E3A5F;
        border-color: #1E40AF;
        color: #DBEAFE;
    }

    .lab-notes-box {
        background: #FFFBEB;
        border-radius: 12px;
        padding: 16px 20px;
        border: 1px solid #FCD34D;
        color: #92400E;
        font-size: 0.85rem;
        line-height: 1.6;
    }

    html[data-theme="dark"] .lab-notes-box {
        background: #3A2A1A;
        border-color: #F59E0B;
        color: #FBBF24;
    }

    /* ================================================================
       ACTION BUTTONS
       ================================================================ */
    .lab-btn {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 8px 16px;
        border-radius: 9px;
        font-weight: 700;
        font-size: 0.75rem;
        transition: all 0.3s ease;
        cursor: pointer;
        border: none;
        text-decoration: none;
        white-space: nowrap;
    }

    .lab-btn i { font-size: 0.75rem; }

    .lab-btn-blue {
        background: #0B5ED7;
        color: white;
    }
    .lab-btn-blue:hover {
        background: #0A4CA8;
        color: white;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3);
    }

    .lab-btn-green {
        background: #059669;
        color: white;
    }
    .lab-btn-green:hover {
        background: #047857;
        color: white;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(5, 150, 105, 0.3);
    }

    .lab-btn-orange {
        background: #F59E0B;
        color: white;
    }
    .lab-btn-orange:hover {
        background: #D97706;
        color: white;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(245, 158, 11, 0.3);
    }

    .lab-btn-gray {
        background: #64748B;
        color: white;
    }
    .lab-btn-gray:hover {
        background: #475569;
        color: white;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(100, 116, 139, 0.3);
    }

    .lab-btn-outline {
        background: transparent;
        color: var(--page-text-secondary, #64748B);
        border: 2px solid var(--page-border, #E2E8F0);
    }
    .lab-btn-outline:hover {
        background: var(--page-hover, #F1F5F9);
        border-color: #0B5ED7;
        color: #0B5ED7;
        transform: translateY(-2px);
    }
    html[data-theme="dark"] .lab-btn-outline {
        color: #94A3B8;
        border-color: #334155;
    }
    html[data-theme="dark"] .lab-btn-outline:hover {
        background: #0F172A;
        border-color: #6EA8FE;
        color: #6EA8FE;
    }

    /* ================================================================
       GRID UTILITIES
       ================================================================ */
    .lab-grid-stats {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 16px;
        margin-bottom: 24px;
    }

    .lab-grid-2 {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 0 32px;
    }

    @media (max-width: 768px) {
        .lab-grid-stats { grid-template-columns: 1fr; }
        .lab-grid-2 { grid-template-columns: 1fr; gap: 0; }
        .lab-profile-header { padding: 20px; flex-direction: column; text-align: center; }
        .lab-profile-icon { width: 68px; height: 68px; font-size: 2rem; }
        .lab-profile-name { font-size: 1.25rem; }
        .lab-info-row { flex-direction: column; gap: 4px; align-items: flex-start; }
        .lab-info-label { width: 100%; }
    }

    /* Print */
    @media print {
        .lab-profile-header { background: #0B5ED7 !important; -webkit-print-color-adjust: exact; }
        .btn-glass, .lab-btn { display: none !important; }
    }
</style>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <!-- ================================================================
         BLUE PROFILE HEADER
         ================================================================ -->
    <div class="lab-profile-header">
        <div class="lab-profile-icon">
            <i class="fas fa-flask"></i>
        </div>
        <div class="lab-profile-info">
            <h1 class="lab-profile-name">
                <?= htmlspecialchars($test['test_name'] ?? 'N/A') ?>
            </h1>
            <div class="lab-profile-badges">
                <span class="lab-profile-badge">
                    <i class="fas fa-id-card"></i> Test ID: #<?= $test['id'] ?>
                </span>
                <?php if (!empty($test['patient_name'])): ?>
                    <span class="lab-profile-badge">
                        <i class="fas fa-user"></i> <?= htmlspecialchars($test['patient_name']) ?>
                    </span>
                <?php endif; ?>
                <span class="lab-profile-badge">
                    <i class="fas fa-store-alt"></i> <?= htmlspecialchars($test['branch_name'] ?? 'N/A') ?>
                </span>
                <span class="lab-profile-badge">
                    <i class="fas fa-circle" style="color:<?= getStatusColor($test['status']) ?>;font-size:0.6rem;"></i>
                    <?= ucfirst(str_replace('_', ' ', $test['status'])) ?>
                </span>
            </div>
            <div class="lab-profile-contact">
                <span><i class="fas fa-user-md"></i> Doctor: <?= htmlspecialchars($test['doctor_name'] ?? 'N/A') ?></span>
                <span><i class="fas fa-calendar-alt"></i> <?= date('M d, Y', strtotime($test['created_at'])) ?></span>
            </div>
        </div>
        <div class="lab-profile-actions">
            <a href="lab_tests.php?branch=<?= $selected_branch_id ?>" class="btn-glass">
                <i class="fas fa-arrow-left"></i> Back
            </a>
            <?php if ($test['status'] !== 'completed' && $test['status'] !== 'cancelled'): ?>
                <a href="edit_lab_test.php?id=<?= $test['id'] ?>&branch=<?= $selected_branch_id ?>" class="btn-glass">
                    <i class="fas fa-edit"></i> Edit
                </a>
            <?php endif; ?>
            <?php if ($test['status'] === 'completed'): ?>
                <button onclick="window.print()" class="btn-glass">
                    <i class="fas fa-print"></i> Print Result
                </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- ================================================================
         STATS
         ================================================================ -->
    <div class="lab-grid-stats">
        <div class="lab-stat-card">
            <div class="stat-icon">🔬</div>
            <p class="stat-number">#<?= $test['id'] ?></p>
            <p class="stat-label">Test ID</p>
        </div>
        <div class="lab-stat-card">
            <div class="stat-icon">💰</div>
            <p class="stat-number green">TSh <?= number_format($test['test_price'] ?? 0, 0) ?></p>
            <p class="stat-label">Test Price</p>
        </div>
        <div class="lab-stat-card">
            <div class="stat-icon">📋</div>
            <p class="stat-number orange"><?= $test['total_lab_requests'] ?? 0 ?></p>
            <p class="stat-label">Patient's Lab Tests</p>
        </div>
    </div>

    <!-- ================================================================
         TEST INFORMATION
         ================================================================ -->
    <div class="lab-card">
        <div class="lab-card-header">
            <h3 class="lab-card-title">
                <i class="fas fa-info-circle"></i> Test Information
            </h3>
            <span class="lab-status-badge <?= $test['status'] ?>">
                <i class="fas <?= getStatusIcon($test['status']) ?>"></i>
                <?= ucfirst(str_replace('_', ' ', $test['status'])) ?>
            </span>
        </div>
        <div class="lab-grid-2">
            <div class="lab-info-row">
                <span class="lab-info-label">Test Name</span>
                <span class="lab-info-value" style="font-weight:600;"><?= htmlspecialchars($test['test_name'] ?? '—') ?></span>
            </div>
            <div class="lab-info-row">
                <span class="lab-info-label">Test Type</span>
                <span class="lab-info-value">
                    <?php if (!empty($test['test_type']) && $test['test_type'] !== 'N/A'): ?>
                        <?= htmlspecialchars($test['test_type']) ?>
                    <?php else: ?>
                        <span class="empty-value">Not specified</span>
                    <?php endif; ?>
                </span>
            </div>
            <div class="lab-info-row">
                <span class="lab-info-label">Sample Type</span>
                <span class="lab-info-value">
                    <?php if (!empty($test['sample_type']) && $test['sample_type'] !== 'N/A'): ?>
                        <?= htmlspecialchars($test['sample_type']) ?>
                    <?php else: ?>
                        <span class="empty-value">Not specified</span>
                    <?php endif; ?>
                </span>
            </div>
            <div class="lab-info-row">
                <span class="lab-info-label">Test Price</span>
                <span class="lab-info-value price-value">TSh <?= number_format($test['test_price'] ?? 0, 0) ?></span>
            </div>
            <div class="lab-info-row">
                <span class="lab-info-label">Test Date</span>
                <span class="lab-info-value">
                    <?php if (!empty($test['test_date']) && $test['test_date'] !== '0000-00-00'): ?>
                        <?= date('M d, Y', strtotime($test['test_date'])) ?>
                    <?php else: ?>
                        <span class="empty-value">Not scheduled</span>
                    <?php endif; ?>
                </span>
            </div>
            <div class="lab-info-row">
                <span class="lab-info-label">Created</span>
                <span class="lab-info-value"><?= date('M d, Y h:i A', strtotime($test['created_at'])) ?></span>
            </div>
            <div class="lab-info-row">
                <span class="lab-info-label">Completed</span>
                <span class="lab-info-value">
                    <?php if (!empty($test['completed_at'])): ?>
                        <?= date('M d, Y h:i A', strtotime($test['completed_at'])) ?>
                    <?php else: ?>
                        <span class="empty-value">Not completed yet</span>
                    <?php endif; ?>
                </span>
            </div>
            <div class="lab-info-row">
                <span class="lab-info-label">Performed By</span>
                <span class="lab-info-value">
                    <?php if (!empty($test['lab_technician_name'])): ?>
                        <?= htmlspecialchars($test['lab_technician_name']) ?>
                    <?php else: ?>
                        <span class="empty-value">Not assigned</span>
                    <?php endif; ?>
                </span>
            </div>
            <div class="lab-info-row">
                <span class="lab-info-label">Reference Range</span>
                <span class="lab-info-value">
                    <?php if (!empty($test['reference_range']) && $test['reference_range'] !== 'N/A'): ?>
                        <?= htmlspecialchars($test['reference_range']) ?>
                    <?php else: ?>
                        <span class="empty-value">Not specified</span>
                    <?php endif; ?>
                </span>
            </div>
        </div>
    </div>

    <!-- ================================================================
         PATIENT & VISIT
         ================================================================ -->
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:24px;" class="lab-grid-2-cards">
        
        <!-- Patient -->
        <div class="lab-card" style="margin-bottom:0;">
            <div class="lab-card-header">
                <h3 class="lab-card-title">
                    <i class="fas fa-user"></i> Patient Information
                </h3>
                <?php if (!empty($test['patient_id'])): ?>
                    <a href="patient_details.php?id=<?= $test['patient_id'] ?>&branch=<?= $selected_branch_id ?>" class="lab-btn lab-btn-blue">
                        View Patient <i class="fas fa-arrow-right"></i>
                    </a>
                <?php endif; ?>
            </div>
            <?php if (!empty($test['patient_name'])): ?>
                <div class="lab-info-row">
                    <span class="lab-info-label">Full Name</span>
                    <span class="lab-info-value" style="font-weight:600;"><?= htmlspecialchars($test['patient_name']) ?></span>
                </div>
                <div class="lab-info-row">
                    <span class="lab-info-label">Patient ID</span>
                    <span class="lab-info-value" style="font-family:monospace;"><?= htmlspecialchars($test['patient_id_number'] ?? '—') ?></span>
                </div>
                <div class="lab-info-row">
                    <span class="lab-info-label">Phone</span>
                    <span class="lab-info-value"><?= htmlspecialchars($test['patient_phone'] ?? '—') ?></span>
                </div>
                <div class="lab-info-row">
                    <span class="lab-info-label">Gender</span>
                    <span class="lab-info-value"><?= htmlspecialchars($test['patient_gender'] ?? '—') ?></span>
                </div>
                <div class="lab-info-row">
                    <span class="lab-info-label">Date of Birth</span>
                    <span class="lab-info-value"><?= $test['patient_dob'] ? date('M d, Y', strtotime($test['patient_dob'])) : '—' ?></span>
                </div>
            <?php else: ?>
                <div style="text-align:center;padding:24px;color:var(--page-text-muted);">
                    <i class="fas fa-info-circle" style="font-size:1.5rem;display:block;margin-bottom:8px;opacity:0.5;"></i>
                    No patient associated with this test
                </div>
            <?php endif; ?>
        </div>

        <!-- Visit -->
        <div class="lab-card" style="margin-bottom:0;">
            <div class="lab-card-header">
                <h3 class="lab-card-title">
                    <i class="fas fa-notes-medical" style="color:#059669;"></i> Visit Information
                </h3>
                <?php if (!empty($test['visit_id']) && !empty($test['visit_number'])): ?>
                    <a href="visit_details.php?id=<?= $test['visit_id'] ?>&branch=<?= $selected_branch_id ?>" class="lab-btn lab-btn-green">
                        View Visit <i class="fas fa-arrow-right"></i>
                    </a>
                <?php endif; ?>
            </div>
            <?php if (!empty($test['visit_number'])): ?>
                <div class="lab-info-row">
                    <span class="lab-info-label">Visit Number</span>
                    <span class="lab-info-value" style="font-family:monospace;"><?= htmlspecialchars($test['visit_number']) ?></span>
                </div>
                <div class="lab-info-row">
                    <span class="lab-info-label">Visit Type</span>
                    <span class="lab-info-value"><?= ucfirst($test['visit_type'] ?? '—') ?></span>
                </div>
                <div class="lab-info-row">
                    <span class="lab-info-label">Visit Date</span>
                    <span class="lab-info-value"><?= date('M d, Y h:i A', strtotime($test['visit_date'])) ?></span>
                </div>
                <div class="lab-info-row">
                    <span class="lab-info-label">Doctor</span>
                    <span class="lab-info-value"><?= htmlspecialchars($test['doctor_name'] ?? '—') ?></span>
                </div>
            <?php else: ?>
                <div style="text-align:center;padding:24px;color:var(--page-text-muted);">
                    <i class="fas fa-info-circle" style="font-size:1.5rem;display:block;margin-bottom:8px;opacity:0.5;"></i>
                    No visit associated with this test
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ================================================================
         TEST RESULTS
         ================================================================ -->
    <div class="lab-card">
        <div class="lab-card-header">
            <h3 class="lab-card-title">
                <i class="fas fa-flask"></i> Test Results
                <span class="lab-badge <?= $test['status'] === 'completed' ? 'lab-badge-success' : 'lab-badge-warning' ?>">
                    <?= $test['status'] === 'completed' ? '✅ Completed' : '⏳ Pending' ?>
                </span>
            </h3>
        </div>
        
        <?php if (!empty($test['results'])): ?>
            <div class="lab-results-box">
                <?= htmlspecialchars($test['results']) ?>
            </div>
        <?php else: ?>
            <div style="text-align:center;padding:32px;color:var(--page-text-muted);">
                <i class="fas fa-inbox" style="font-size:2rem;display:block;margin-bottom:8px;opacity:0.5;"></i>
                No results available for this test
            </div>
        <?php endif; ?>
        
        <?php if (!empty($test['interpretation'])): ?>
            <div style="margin-top:16px;">
                <h4 style="font-weight:700;font-size:0.85rem;color:#0B5ED7;margin-bottom:8px;">
                    <i class="fas fa-comment-medical"></i> Interpretation
                </h4>
                <div class="lab-interpretation-box">
                    <?= nl2br(htmlspecialchars($test['interpretation'])) ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- ================================================================
         NOTES
         ================================================================ -->
    <?php if (!empty($test['notes'])): ?>
        <div class="lab-card">
            <div class="lab-card-header">
                <h3 class="lab-card-title">
                    <i class="fas fa-sticky-note" style="color:#F59E0B;"></i> Notes
                </h3>
            </div>
            <div class="lab-notes-box">
                <?= nl2br(htmlspecialchars($test['notes'])) ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- ================================================================
         QUICK ACTIONS
         ================================================================ -->
    <div class="lab-card">
        <div class="lab-card-header">
            <h3 class="lab-card-title">
                <i class="fas fa-bolt"></i> Quick Actions
            </h3>
        </div>
        <div style="display:flex;flex-wrap:wrap;gap:10px;">
            <a href="lab_tests.php?branch=<?= $selected_branch_id ?>" class="lab-btn lab-btn-outline">
                <i class="fas fa-arrow-left"></i> Back to Lab Tests
            </a>
            <?php if (!empty($test['patient_id']) && !empty($test['patient_name'])): ?>
                <a href="patient_details.php?id=<?= $test['patient_id'] ?>&branch=<?= $selected_branch_id ?>" class="lab-btn lab-btn-blue">
                    <i class="fas fa-user"></i> View Patient
                </a>
            <?php endif; ?>
            <?php if (!empty($test['visit_id']) && !empty($test['visit_number'])): ?>
                <a href="visit_details.php?id=<?= $test['visit_id'] ?>&branch=<?= $selected_branch_id ?>" class="lab-btn lab-btn-green">
                    <i class="fas fa-notes-medical"></i> View Visit
                </a>
            <?php endif; ?>
            <?php if ($test['status'] !== 'completed' && $test['status'] !== 'cancelled'): ?>
                <a href="edit_lab_test.php?id=<?= $test['id'] ?>&branch=<?= $selected_branch_id ?>" class="lab-btn lab-btn-orange">
                    <i class="fas fa-edit"></i> Edit Test
                </a>
            <?php endif; ?>
            <?php if ($test['status'] === 'completed'): ?>
                <button onclick="window.print()" class="lab-btn lab-btn-gray">
                    <i class="fas fa-print"></i> Print Results
                </button>
            <?php endif; ?>
        </div>
    </div>

</main>

<style>
    @media (max-width: 768px) {
        .lab-grid-2-cards {
            grid-template-columns: 1fr !important;
        }
    }
</style>

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

    console.log('%c🔵 Braick - Lab Test Details', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c✅ Uses SHARED header & sidebar', 'font-size:13px; color:#059669;');
    console.log('%c🌙 FULL DARK MODE inafanya kazi', 'font-size:13px; color:#7C3AED;');
    console.log('%c👤 Admin: <?= htmlspecialchars($user_full_name) ?>', 'font-size:13px; color:#059669;');
    console.log('%c🧪 Test: <?= htmlspecialchars($test['test_name'] ?? 'N/A') ?>', 'font-size:13px; color:#0B5ED7;');
    console.log('%c👤 Patient: <?= htmlspecialchars($test['patient_name'] ?? 'N/A') ?>', 'font-size:13px; color:#7C3AED;');
</script>

</body>
</html>